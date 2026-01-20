<?php
declare(strict_types=1);

namespace App\Controller\Traits;

use Cake\Http\Response;
use Cake\Datasource\EntityInterface;

/**
 * EntityCrudTrait
 *
 * Handles basic CRUD operations for Tickets, PQRS, and Compras.
 * Extracted from TicketSystemControllerTrait for SRP compliance.
 *
 * Responsibilities:
 * - Entity assignment to agents
 * - Status changes
 * - Priority changes
 * - Comment addition
 * - Attachment downloads
 *
 * Required controller properties:
 * - $this->Authentication (CakePHP Authentication component)
 * - $this->Flash (CakePHP Flash component)
 * - $this->ticketService, $this->pqrsService, $this->comprasService
 * - $this->responseService
 */
trait EntityCrudTrait
{
    // EntityConfigTrait is included via TicketSystemControllerTrait facade
    // Methods used: getEntityComponents(), getResolvedStatuses(), isEntityLocked()

    /**
     * Assign entity (ticket, pqrs, or compra) to agent
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @param int $entityId Entity ID
     * @param mixed $assigneeId Agent ID (can be empty string, 0, or int)
     * @param string $redirectAction Action to redirect to (default: 'index')
     * @return Response Redirect response
     */
    protected function assignEntity(
        string $entityType,
        int $entityId,
        $assigneeId,
        string $redirectAction = 'index'
    ): Response {
        $this->request->allowMethod(['post', 'put']);

        $assigneeId = $this->normalizeAssigneeId($assigneeId);
        $userId = $this->getCurrentUserId();

        $components = $this->getEntityComponents($entityType);
        $entity = $components['table']->get($entityId);
        $service = $components['service'];
        $entityName = $components['displayName'];

        if ($this->isEntityLocked($entityType, $entity)) {
            $this->Flash->error(__("No se puede modificar una {$entityName} en estado final."));
            return $this->redirect(['action' => $redirectAction]);
        }

        $result = $service->assign($entity, $assigneeId, $userId);

        if ($result) {
            $this->Flash->success(__("{$entityName} asignada correctamente."));
        } else {
            $this->Flash->error(__("No se pudo asignar la {$entityName}."));
        }

        return $this->redirect(['action' => $redirectAction]);
    }

    /**
     * Change entity status
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @param int $entityId Entity ID
     * @param string $newStatus New status value
     * @param string $redirectAction Action to redirect to (default: 'index')
     * @return Response Redirect response
     */
    protected function changeEntityStatus(
        string $entityType,
        int $entityId,
        string $newStatus,
        string $redirectAction = 'index'
    ): Response {
        $this->request->allowMethod(['post', 'put']);

        $userId = $this->getCurrentUserId();

        $components = $this->getEntityComponents($entityType);
        $entity = $components['table']->get($entityId);
        $service = $components['service'];
        $entityName = $entityType === 'compra' ? 'compra' : 'solicitud';

        $finalStatuses = $this->getResolvedStatuses($entityType);
        $isCurrentlyLocked = in_array($entity->status, $finalStatuses, true);
        $isTargetFinal = in_array($newStatus, $finalStatuses, true);

        if ($isCurrentlyLocked && $isTargetFinal) {
            $this->Flash->error(__("No se puede cambiar el estado de una {$entityName} que ya está cerrada."));
            return $this->redirect(['action' => $redirectAction, $entityId]);
        }

        $result = $service->changeStatus($entity, $newStatus, $userId);

        if ($result) {
            $this->Flash->success(__('Estado actualizado correctamente.'));
        } else {
            $this->Flash->error(__('No se pudo actualizar el estado.'));
        }

        return $this->redirect(['action' => $redirectAction, $entityId]);
    }

    /**
     * Change entity priority
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @param int $entityId Entity ID
     * @param string $newPriority New priority value
     * @param string $redirectAction Action to redirect to (default: 'view')
     * @return Response Redirect response
     */
    protected function changeEntityPriority(
        string $entityType,
        int $entityId,
        string $newPriority,
        string $redirectAction = 'view'
    ): Response {
        $this->request->allowMethod(['post', 'put']);

        $userId = $this->getCurrentUserId();

        $components = $this->getEntityComponents($entityType);
        $entity = $components['table']->get($entityId);
        $service = $components['service'];
        $entityName = $entityType === 'compra' ? 'compra' : 'solicitud';

        if ($this->isEntityLocked($entityType, $entity)) {
            $this->Flash->error(__("No se puede modificar la prioridad de una {$entityName} en estado final."));
            return $this->redirect(['action' => $redirectAction, $entityId]);
        }

        $result = $service->changePriority($entity, $newPriority, $userId);

        if ($result) {
            $this->Flash->success(__('Prioridad actualizada correctamente.'));
        } else {
            $this->Flash->error(__('No se pudo actualizar la prioridad.'));
        }

        return $this->redirect(['action' => $redirectAction, $entityId]);
    }

    /**
     * Add comment to entity using ResponseService
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @param int $entityId Entity ID
     * @return Response Redirect response
     */
    protected function addEntityComment(string $entityType, int $entityId): Response
    {
        $this->request->allowMethod(['post']);

        $user = $this->Authentication->getIdentity();
        $userId = $user ? $user->get('id') : null;

        $components = $this->getEntityComponents($entityType);
        $entity = $components['table']->get($entityId);

        if ($this->isEntityLocked($entityType, $entity)) {
            $entityName = $components['displayName'];
            $this->Flash->error(__("No se pueden agregar comentarios a una {$entityName} en estado final."));
            return $this->redirect(['action' => 'view', $entityId]);
        }

        $data = $this->request->getData();
        $files = $this->request->getUploadedFiles();

        $result = $this->responseService->processResponse(
            $entityType,
            $entityId,
            (int) $userId,
            $data,
            $files
        );

        if ($result['success']) {
            $this->Flash->success($result['message']);
        } else {
            $this->Flash->error($result['message']);
        }

        return $this->redirect(['action' => 'view', $entityId]);
    }

    /**
     * Download attachment for entity
     *
     * @param string $entityType 'ticket', 'pqrs', or 'compra'
     * @param int $attachmentId Attachment ID
     * @return Response File download response
     * @throws \Cake\Http\Exception\NotFoundException If file not found
     */
    protected function downloadEntityAttachment(string $entityType, int $attachmentId): Response
    {
        $attachmentsTableName = match ($entityType) {
            'ticket' => 'Attachments',
            'pqrs' => 'PqrsAttachments',
            'compra' => 'ComprasAttachments',
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };

        $attachmentsTable = $this->fetchTable($attachmentsTableName);
        $attachment = $attachmentsTable->get($attachmentId);
        $filePath = $this->getFullPath($attachment);

        if (!file_exists($filePath)) {
            throw new \Cake\Http\Exception\NotFoundException('Archivo no encontrado.');
        }

        return $this->response
            ->withFile($filePath, ['download' => true, 'name' => $attachment->original_filename])
            ->withType($attachment->mime_type ?? 'application/octet-stream');
    }

    /**
     * Get current user ID from authentication
     *
     * @return int User ID (defaults to 1 if not authenticated)
     */
    protected function getCurrentUserId(): int
    {
        $user = $this->Authentication->getIdentity();
        return $user ? (int) $user->get('id') : 1;
    }

    /**
     * Normalize assignee ID value
     *
     * @param mixed $value Raw assignee ID value
     * @return int|null Normalized assignee ID
     */
    protected function normalizeAssigneeId($value): ?int
    {
        return ($value === '' || $value === '0' || $value === 0) ? null : (int) $value;
    }

    /**
     * Handle service result with flash message and redirect
     *
     * @param array<string, mixed> $result Service result array
     * @param string $redirectUrl URL to redirect to
     * @return Response Redirect response
     */
    protected function handleServiceResult(array $result, string $redirectUrl): Response
    {
        if ($result['success']) {
            $this->Flash->success($result['message']);
        } else {
            $this->Flash->error($result['message']);
        }

        return $this->redirect($redirectUrl);
    }
}