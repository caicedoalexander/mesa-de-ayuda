<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Attachment Entity
 *
 * @property int $id
 * @property int|null $ticket_id
 * @property int|null $comment_id
 * @property string $filename
 * @property string $original_filename
 * @property string $file_path
 * @property string $mime_type
 * @property int $file_size
 * @property bool $is_inline
 * @property string|null $content_id
 * @property int $uploaded_by
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\Ticket $ticket
 * @property \App\Model\Entity\TicketComment $comment
 */
class Attachment extends Entity
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
        'comment_id' => true,
        'filename' => true,
        'original_filename' => true,
        'file_path' => true,
        'mime_type' => true,
        'file_size' => true,
        'is_inline' => true,
        'content_id' => true,
        'uploaded_by' => true,
        'created' => true,
        'ticket' => true,
        'comment' => true,
    ];
}
