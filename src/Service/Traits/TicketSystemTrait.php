<?php
declare(strict_types=1);

namespace App\Service\Traits;

/**
 * TicketSystemTrait
 *
 * Unified service trait for Tickets, PQRS, and Compras management.
 * Composes specialized traits following Single Responsibility Principle.
 *
 * REFACTORED (TRAIT-001): Original 515 lines split into focused traits:
 * - EntityTypeMapperTrait: Entity type to table/key mapping (~130 lines)
 * - EntityCommentManagementTrait: Comments and history logging (~160 lines)
 * - EntityStatusManagementTrait: Status and priority changes (~175 lines)
 * - EntityAssignmentTrait: Assignment and conversion (~165 lines)
 *
 * This trait now serves as a facade that composes all sub-traits,
 * maintaining backward compatibility with existing services.
 *
 * Usage:
 * ```php
 * class TicketService {
 *     use TicketSystemTrait;
 *     use GenericAttachmentTrait; // For getEntityNumber()
 *
 *     private EmailService $emailService;
 *     private WhatsappService $whatsappService;
 *
 *     public function someMethod($ticket) {
 *         $this->changeStatus($ticket, 'resuelto', $userId);
 *         $this->addComment($ticket->id, $userId, 'Done', 'ticket');
 *     }
 * }
 * ```
 *
 * Required properties in using class:
 * - $this->emailService (for notifications)
 * - fetchTable() method (from LocatorAwareTrait)
 *
 * @package App\Service\Traits
 */
trait TicketSystemTrait
{
    // Entity type mapping (source → type, type → table names)
    use EntityTypeMapperTrait;

    // Comments and history logging
    use EntityCommentManagementTrait;

    // Status and priority management with notifications
    use EntityStatusManagementTrait;

    // Assignment and conversion operations
    use EntityAssignmentTrait;
}