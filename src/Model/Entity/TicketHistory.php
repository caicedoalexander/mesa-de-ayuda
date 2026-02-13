<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * TicketHistory Entity
 *
 * @property int $id
 * @property int $ticket_id
 * @property int|null $changed_by
 * @property string $field_name
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $description
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\Ticket $ticket
 * @property \App\Model\Entity\User $user
 */
class TicketHistory extends Entity
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
        'changed_by' => false,
        'field_name' => true,
        'old_value' => true,
        'new_value' => true,
        'description' => true,
        'created' => false,
        'ticket' => true,
        'user' => true,
    ];
}
