<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PqrsHistory Entity
 *
 * @property int $id
 * @property int $pqrs_id
 * @property int $changed_by
 * @property string $field_name
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $description
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\Pqr $pqr
 * @property \App\Model\Entity\User $user
 */
class PqrsHistory extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'pqrs_id' => true,
        'changed_by' => false,
        'field_name' => true,
        'old_value' => true,
        'new_value' => true,
        'description' => true,
        'created' => false,
        'pqr' => true,
        'user' => true,
    ];
}
