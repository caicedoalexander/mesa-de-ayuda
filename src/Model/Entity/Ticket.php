<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Ticket Entity
 *
 * @property int $id
 * @property string $ticket_number
 * @property string|null $gmail_message_id
 * @property string|null $gmail_thread_id
 * @property string $subject
 * @property string|null $description
 * @property string $status
 * @property string $priority
 * @property int $requester_id
 * @property int|null $assignee_id
 * @property int|null $organization_id
 * @property string $channel
 * @property string|null $source_email
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \Cake\I18n\DateTime|null $resolved_at
 * @property \Cake\I18n\DateTime|null $first_response_at
 *
 * @property \App\Model\Entity\User $requester
 * @property \App\Model\Entity\User $assignee
 * @property \App\Model\Entity\Organization $organization
 * @property \App\Model\Entity\Attachment[] $attachments
 * @property \App\Model\Entity\TicketComment[] $ticket_comments
 * @property \App\Model\Entity\TicketFollower[] $ticket_followers
 * @property \App\Model\Entity\TicketTag[] $ticket_tags
 */
class Ticket extends Entity
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
        'ticket_number' => true,
        'gmail_message_id' => true,
        'gmail_thread_id' => true,
        'subject' => true,
        'description' => true,
        'status' => true,
        'priority' => true,
        'requester_id' => true,
        'assignee_id' => true,
        'organization_id' => true,
        'channel' => true,
        'source_email' => true,
        'created' => true,
        'modified' => true,
        'resolved_at' => true,
        'first_response_at' => true,
        'requester' => true,
        'assignee' => true,
        'organization' => true,
        'attachments' => true,
        'ticket_comments' => true,
        'ticket_followers' => true,
        'ticket_tags' => true,
    ];
}
