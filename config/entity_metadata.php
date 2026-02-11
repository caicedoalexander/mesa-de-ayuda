<?php
declare(strict_types=1);

/**
 * Entity Metadata Configuration
 *
 * REFACTORED (TRAIT-003): Configuration extracted from ViewDataNormalizerTrait
 *
 * This file contains field mappings for all entity types (ticket, pqrs, compra).
 * Each entity type has standardized field names so templates can work generically
 * without hardcoded field names.
 *
 * @see \App\Controller\Traits\ViewDataNormalizerTrait
 */
return [
    'ticket' => [
        'numberField' => 'ticket_number',
        'numberLabel' => 'Ticket',
        'commentsField' => 'ticket_comments',
        'attachmentsField' => 'attachments',
        'descriptionField' => 'description',
        'subjectField' => 'subject',
        'createdField' => 'created',
        'resolvedField' => 'resolved_at',
        'statusField' => 'status',
        'priorityField' => 'priority',
        'containerClass' => 'ticket-view-container',
        'marqueeClass' => 'ticket-subject',
    ],
    'pqrs' => [
        'numberField' => 'pqrs_number',
        'numberLabel' => 'PQRS',
        'commentsField' => 'pqrs_comments',
        'attachmentsField' => 'pqrs_attachments',
        'descriptionField' => 'description',
        'subjectField' => 'subject',
        'createdField' => 'created',
        'resolvedField' => 'resolved_at',
        'statusField' => 'status',
        'priorityField' => 'priority',
        'containerClass' => 'pqrs-view-container',
        'marqueeClass' => 'pqrs-subject',
    ],
    'compra' => [
        'numberField' => 'compra_number',
        'numberLabel' => 'Compra',
        'commentsField' => 'compras_comments',
        'attachmentsField' => 'compras_attachments',
        'descriptionField' => 'description',
        'subjectField' => 'subject',
        'createdField' => 'created',
        'resolvedField' => 'resolved_at',
        'statusField' => 'status',
        'priorityField' => 'priority',
        'containerClass' => 'compras-view-container',
        'marqueeClass' => 'compra-subject',
    ],
];