<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\Entity;

/**
 * User Entity
 *
 * @property int $id
 * @property string $email
 * @property string|null $password
 * @property string $name
 * @property string|null $phone
 * @property string $role
 * @property int|null $organization_id
 * @property bool $is_active
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\Organization $organization
 * @property \App\Model\Entity\TicketComment[] $ticket_comments
 * @property \App\Model\Entity\TicketFollower[] $ticket_followers
 */
class User extends Entity
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
        'email' => true,
        'password' => true,
        'name' => true,
        'phone' => true,
        'role' => true,
        'organization_id' => true,
        'is_active' => true,
        'created' => true,
        'organization' => true,
        'ticket_comments' => true,
        'ticket_followers' => true,
    ];

    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * @var array<string>
     */
    protected array $_hidden = [
        'password',
    ];

    /**
     * Hash password before saving
     *
     * @param string $password Plain text password
     * @return string|null Hashed password
     */
    protected function _setPassword(?string $password): ?string
    {
        if ($password === null || strlen($password) === 0) {
            return null;
        }

        $hasher = new DefaultPasswordHasher();
        return $hasher->hash($password);
    }
}
