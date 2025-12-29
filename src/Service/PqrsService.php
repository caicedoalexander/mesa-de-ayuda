<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;
use App\Service\EmailService;
use App\Service\WhatsappService;

/**
 * PQRS Service
 *
 * Handles PQRS (Peticiones, Quejas, Reclamos, Sugerencias) business logic:
 * - Creation from public form
 * - Status changes
 * - Comments
 * - Assignments
 * - Priority changes
 * - Attachments
 * - Notifications (Email + WhatsApp)
 */
class PqrsService
{
    use LocatorAwareTrait;
    use \App\Service\Traits\TicketSystemTrait {
        \App\Service\Traits\TicketSystemTrait::addComment as protected traitAddComment;
    }
    use \App\Service\Traits\NotificationDispatcherTrait;
    use \App\Service\Traits\GenericAttachmentTrait;
    use \App\Service\Traits\SLAManagementTrait;

    private EmailService $emailService;
    private WhatsappService $whatsappService;

    /**
     * Constructor
     *
     * @param array|null $systemConfig Optional system configuration to avoid redundant DB queries
     */
    public function __construct(?array $systemConfig = null)
    {
        $this->emailService = new EmailService($systemConfig);
        $this->whatsappService = new WhatsappService($systemConfig);
    }

    /**
     * Add comment to PQRS (wrapper to ensure isPqrs is always true)
     *
     * @param int $pqrsId PQRS ID
     * @param int|null $userId User ID
     * @param string $body Comment body
     * @param string $type Comment type
     * @param bool $isSystem Is system comment
     * @param bool $sendNotifications Send notifications
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function addComment(
        int $pqrsId,
        ?int $userId,
        string $body,
        string $type = 'public',
        bool $isSystem = false,
        bool $sendNotifications = false
    ): ?\Cake\Datasource\EntityInterface {
        // Call trait method with isPqrs = true
        return $this->traitAddComment(
            $pqrsId,
            $userId,
            $body,
            $type,
            $isSystem,
            $sendNotifications,
            true  // Always true for PQRS
        );
    }

    /**
     * Create PQRS from public form submission
     *
     * @param array $formData Form data
     * @param array $files Uploaded files
     * @return \App\Model\Entity\Pqr|null Created PQRS or null on failure
     */
    public function createFromForm(array $formData, array $files = []): ?\App\Model\Entity\Pqr
    {
        $pqrsTable = $this->fetchTable('Pqrs');

        // Generate PQRS number
        $pqrsNumber = $pqrsTable->generatePqrsNumber();

        // Get PQRS type for SLA calculation
        $type = $formData['type'] ?? 'peticion';

        // Calculate SLA deadlines based on PQRS type
        $slas = $this->calculatePqrsSLA($type, new \DateTime());

        // Create PQRS entity
        $pqrs = $pqrsTable->newEntity([
            'pqrs_number' => $pqrsNumber,
            'requester_name' => $formData['requester_name'] ?? '',
            'requester_email' => $formData['requester_email'] ?? '',
            'requester_phone' => $formData['requester_phone'] ?? null,
            'type' => $type,
            'subject' => $formData['subject'] ?? '',
            'description' => $formData['description'] ?? '',
            'status' => 'nuevo',
            'priority' => $formData['priority'] ?? 'media',
            'channel' => 'web',
            'first_response_sla_due' => $slas['first_response'],
            'resolution_sla_due' => $slas['resolution'],
        ]);
        assert($pqrs instanceof \App\Model\Entity\Pqr);

        if (!$pqrsTable->save($pqrs)) {
            \Cake\Log\Log::error('Failed to create PQRS from form', ['errors' => $pqrs->getErrors()]);
            return null;
        }

        \Cake\Log\Log::info("PQRS created: {$pqrs->pqrs_number} from {$pqrs->requester_email}");

        // Process attachments
        if (!empty($files)) {
            $this->processAttachments($pqrs, $files, null, null);
        }

        // Send creation notifications (Email + WhatsApp)
        $this->dispatchCreationNotifications('pqrs', $pqrs);

        return $pqrs;
    }

    /**
     * Process and save attachments
     *
     * @param \App\Model\Entity\Pqr $pqrs PQRS entity
     * @param array $files Uploaded files
     * @param int|null $commentId Optional comment ID
     * @param int|null $userId User ID
     * @return void
     */
    private function processAttachments(
        \App\Model\Entity\Pqr $pqrs,
        array $files,
        ?int $commentId = null,
        ?int $userId = null
    ): void {
        $attachmentsTable = $this->fetchTable('PqrsAttachments');

        foreach ($files as $file) {
            if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
                continue;
            }

            // Validate and save file
            $result = $this->saveUploadedFile($pqrs, $file, $commentId, $userId);
            if ($result) {
                \Cake\Log\Log::info("Attachment saved for PQRS {$pqrs->pqrs_number}: {$result->original_filename}");
            }
        }
    }

    /**
     * Save uploaded file (using GenericAttachmentTrait)
     *
     * @param \App\Model\Entity\Pqr $pqrs PQRS entity
     * @param \Psr\Http\Message\UploadedFileInterface $file Uploaded file
     * @param int|null $commentId Comment ID
     * @param int|null $userId User ID
     * @return \App\Model\Entity\PqrsAttachment|null
     */
    public function saveUploadedFile(
        \App\Model\Entity\Pqr $pqrs,
        \Psr\Http\Message\UploadedFileInterface $file,
        ?int $commentId = null,
        ?int $userId = null
    ): ?\App\Model\Entity\PqrsAttachment {
        $result = $this->saveGenericUploadedFile('pqrs', $pqrs, $file, $commentId, $userId);
        assert($result instanceof \App\Model\Entity\PqrsAttachment || $result === null);
        return $result;
    }

    /**
     * Recalculate SLA for a PQRS after configuration changes
     *
     * @param int $pqrsId PQRS ID
     * @return bool Success
     */
    public function recalculateSLA(int $pqrsId): bool
    {
        $pqrsTable = $this->fetchTable('Pqrs');
        $pqrs = $pqrsTable->get($pqrsId);

        return $this->recalculateSLAForEntity($pqrs);
    }

    /**
     * Get all PQRS with breached first response SLA
     *
     * @return array Array of PQRS entities
     */
    public function getBreachedFirstResponseSLA(): array
    {
        $closedStatuses = ['resuelto', 'cerrado'];
        return $this->getBreachedSLAEntities('Pqrs', $closedStatuses, 'first_response');
    }

    /**
     * Get all PQRS with breached resolution SLA
     *
     * @return array Array of PQRS entities
     */
    public function getBreachedResolutionSLA(): array
    {
        $closedStatuses = ['resuelto', 'cerrado'];
        return $this->getBreachedSLAEntities('Pqrs', $closedStatuses, 'resolution');
    }
}