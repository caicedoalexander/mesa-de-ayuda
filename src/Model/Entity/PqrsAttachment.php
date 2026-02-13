<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PqrsAttachment Entity
 *
 * @property int $id
 * @property int $pqrs_id
 * @property int|null $pqrs_comment_id
 * @property string $filename
 * @property string $original_filename
 * @property string $file_path
 * @property int $file_size
 * @property string $mime_type
 * @property bool $is_inline
 * @property string|null $content_id
 * @property int|null $uploaded_by_user_id
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\Pqr $pqr
 * @property \App\Model\Entity\PqrsComment $pqrs_comment
 * @property \App\Model\Entity\User $uploaded_by_user
 */
class PqrsAttachment extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'pqrs_id' => true,
        'pqrs_comment_id' => true,
        'filename' => false,
        'original_filename' => true,
        'file_path' => false,
        'file_size' => false,
        'mime_type' => false,
        'is_inline' => false,
        'content_id' => false,
        'uploaded_by_user_id' => false,
        'created' => false,
        'pqr' => true,
        'pqrs_comment' => true,
        'uploaded_by_user' => true,
    ];
}
