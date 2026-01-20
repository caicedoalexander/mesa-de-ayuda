<?php
declare(strict_types=1);

namespace App\Controller\Traits;

use App\Service\Traits\GenericAttachmentTrait;

/**
 * TicketSystemControllerTrait
 *
 * Unified controller trait for Tickets, PQRS, and Compras management.
 * Composes specialized traits following Single Responsibility Principle.
 *
 * REFACTORED (CTRL-004): Original 1,257 lines split into focused traits:
 * - EntityConfigTrait: Configuration and metadata (~220 lines)
 * - EntityCrudTrait: CRUD operations (~250 lines)
 * - BulkOperationsTrait: Bulk operations (~220 lines)
 * - EntityIndexTrait: Listing and filtering (~280 lines)
 * - EntityViewTrait: Detail view and history (~200 lines)
 *
 * This trait now serves as a facade that composes all sub-traits,
 * maintaining backward compatibility with existing controllers.
 *
 * Usage:
 * ```php
 * class TicketsController extends AppController {
 *     use TicketSystemControllerTrait;
 *
 *     public function index() {
 *         $this->indexEntity('ticket');
 *     }
 *
 *     public function view($id) {
 *         return $this->viewEntity('ticket', (int)$id);
 *     }
 *
 *     public function assign($id) {
 *         return $this->assignEntity('ticket', (int)$id, $this->request->getData('agent_id'));
 *     }
 * }
 * ```
 *
 * @package App\Controller\Traits
 */
trait TicketSystemControllerTrait
{
    // Attachment handling for file downloads
    use GenericAttachmentTrait;

    // View data normalization
    use ViewDataNormalizerTrait;

    // Configuration and metadata (statuses, priorities, entity components)
    use EntityConfigTrait;

    // CRUD operations (assign, status, priority, comments, attachments)
    use EntityCrudTrait;

    // Bulk operations (bulk assign, bulk delete, bulk priority, bulk tags)
    use BulkOperationsTrait;

    // Index/listing operations (filtering, sorting, pagination)
    use EntityIndexTrait;

    // View/detail operations (single entity view, history AJAX)
    use EntityViewTrait;
}
