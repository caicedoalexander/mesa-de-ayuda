<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PqrsComment Entity
 *
 * @property int $id
 * @property int $pqrs_id
 * @property int|null $user_id
 * @property string $comment_type
 * @property string $body
 * @property bool $is_system_comment
 * @property bool $sent_as_email
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Pqr $pqr
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\PqrsAttachment[] $pqrs_attachments
 */
class PqrsComment extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'pqrs_id' => true,
        'user_id' => false,
        'comment_type' => true,
        'body' => true,
        'is_system_comment' => false,
        'sent_as_email' => false,
        'email_to' => true,
        'email_cc' => true,
        'created' => false,
        'modified' => false,
        'pqr' => true,
        'user' => true,
        'pqrs_attachments' => true,
    ];
}
