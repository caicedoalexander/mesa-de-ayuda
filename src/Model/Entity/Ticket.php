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
 * @property string|null $email_to
 * @property string|null $email_cc
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
        'ticket_number' => false,
        'gmail_message_id' => false,
        'gmail_thread_id' => false,
        'email_to' => true,
        'email_cc' => true,
        'subject' => true,
        'description' => true,
        'status' => false,
        'priority' => true,
        'requester_id' => false,
        'assignee_id' => true,
        'organization_id' => true,
        'channel' => false,
        'source_email' => false,
        'created' => false,
        'modified' => false,
        'resolved_at' => false,
        'first_response_at' => false,
        'requester' => true,
        'assignee' => true,
        'organization' => true,
        'attachments' => true,
        'ticket_comments' => true,
        'ticket_followers' => true,
        'ticket_tags' => true,
    ];

    /**
     * Set email_to as JSON (encode array)
     *
     * @param array|string|null $value Array of recipients or JSON string
     * @return string|null JSON string or null
     */
    protected function _setEmailTo($value): ?string
    {
        if (is_array($value)) {
            // Return null for empty arrays
            if (empty($value)) {
                return null;
            }
            return json_encode($value);
        }

        return $value;
    }

    /**
     * Set email_cc as JSON (encode array)
     *
     * @param array|string|null $value Array of recipients or JSON string
     * @return string|null JSON string or null
     */
    protected function _setEmailCc($value): ?string
    {
        if (is_array($value)) {
            // Return null for empty arrays
            if (empty($value)) {
                return null;
            }
            return json_encode($value);
        }

        return $value;
    }

    /**
     * Get decoded email_to as array (virtual property)
     *
     * Access via $ticket->email_to_array (not $ticket->email_to)
     *
     * @return array Array of recipients with 'name' and 'email' keys
     */
    protected function _getEmailToArray(): array
    {
        $value = $this->_fields['email_to'] ?? null;

        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get decoded email_cc as array (virtual property)
     *
     * Access via $ticket->email_cc_array (not $ticket->email_cc)
     *
     * @return array Array of recipients with 'name' and 'email' keys
     */
    protected function _getEmailCcArray(): array
    {
        $value = $this->_fields['email_cc'] ?? null;

        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
