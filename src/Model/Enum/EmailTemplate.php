<?php
declare(strict_types=1);

namespace App\Model\Enum;

/**
 * Email Template Keys Enum
 *
 * Centralizes all email template identifiers used to look up
 * templates from the database. These keys must match the
 * `template_key` column in the `email_templates` table.
 *
 * Pattern:
 * - Creation: nuevo_ticket, nuevo_pqrs, nueva_compra
 * - Status change: ticket_estado, pqrs_estado, compra_estado
 * - Comment: nuevo_comentario, pqrs_comentario, compra_comentario
 * - Response: ticket_respuesta, pqrs_respuesta, compra_respuesta
 */
enum EmailTemplate: string
{
    // Ticket templates
    case NuevoTicket = 'nuevo_ticket';
    case TicketEstado = 'ticket_estado';
    case NuevoComentario = 'nuevo_comentario';
    case TicketRespuesta = 'ticket_respuesta';

    // PQRS templates
    case NuevoPqrs = 'nuevo_pqrs';
    case PqrsEstado = 'pqrs_estado';
    case PqrsComentario = 'pqrs_comentario';
    case PqrsRespuesta = 'pqrs_respuesta';

    // Compras templates
    case NuevaCompra = 'nueva_compra';
    case CompraEstado = 'compra_estado';
    case CompraComentario = 'compra_comentario';
    case CompraRespuesta = 'compra_respuesta';
}