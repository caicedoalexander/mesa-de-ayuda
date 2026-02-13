<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * TicketComment Entity
 *
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $comment_type
 * @property string $body
 * @property bool $is_system_comment
 * @property string|null $gmail_message_id
 * @property bool $sent_as_email
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\Ticket $ticket
 * @property \App\Model\Entity\User $user
 */
class TicketComment extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'ticket_id' => true,
        'user_id' => false,
        'comment_type' => true,
        'body' => true,
        'is_system_comment' => false,
        'gmail_message_id' => false,
        'sent_as_email' => false,
        'email_to' => true,
        'email_cc' => true,
        'created' => false,
        'ticket' => true,
        'user' => true,
    ];
}
