<?php
declare(strict_types=1);

namespace App\Service\Traits;

use Cake\Datasource\EntityInterface;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * EntityConversionTrait
 *
 * Provides generic methods for copying data between entities (Tickets ↔ Compras)
 * Eliminates ~160 lines of duplicated code
 *
 * Requirements:
 * - Using class must use LocatorAwareTrait
 * - Entity numbering must follow: {prefix}_number (e.g., ticket_number, compra_number)
 */
trait EntityConversionTrait
{
    use LocatorAwareTrait;

    /**
     * Copy comments from source entity to target entity
     *
     * Generic method that works for any entity type conversion
     *
     * @param string $sourceType Source entity type ('ticket', 'compra', 'pqrs')
     * @param EntityInterface $sourceEntity Source entity with comments loaded
     * @param string $targetType Target entity type ('ticket', 'compra', 'pqrs')
     * @param EntityInterface $targetEntity Target entity
     * @return int Number of comments copied
     */
    protected function copyComments(
        string $sourceType,
        EntityInterface $sourceEntity,
        string $targetType,
        EntityInterface $targetEntity
    ): int {
        // Get table names and foreign keys
        $sourceCommentsTable = $this->getCommentsTableName($sourceType);
        $targetCommentsTable = $this->getCommentsTableName($targetType);
        $targetForeignKey = $this->getForeignKeyName($targetType);

        // Get association name for source comments
        $sourceCommentsAssoc = $this->getCommentsAssociationName($sourceType);

        // Get loaded comments from source entity
        $sourceComments = $sourceEntity->get($sourceCommentsAssoc);

        if (empty($sourceComments)) {
            return 0;
        }

        $targetTable = $this->fetchTable($targetCommentsTable);
        $copiedCount = 0;

        foreach ($sourceComments as $comment) {
            $newComment = $targetTable->newEntity([
                $targetForeignKey => $targetEntity->id,
                'user_id' => $comment->user_id,
                'comment_type' => $comment->comment_type,
                'body' => $comment->body,
                'is_system_comment' => $comment->is_system_comment,
                'sent_as_email' => false, // Never send email for copied comments
            ]);

            if ($targetTable->save($newComment)) {
                $copiedCount++;
            } else {
                Log::error('Failed to copy comment', [
                    'source_type' => $sourceType,
                    'target_type' => $targetType,
                    'errors' => $newComment->getErrors(),
                ]);
            }
        }

        return $copiedCount;
    }

    /**
     * Copy attachments from source entity to target entity
     *
     * Generic method that handles file copying and database records
     *
     * @param string $sourceType Source entity type ('ticket', 'compra', 'pqrs')
     * @param EntityInterface $sourceEntity Source entity with attachments loaded
     * @param string $targetType Target entity type ('ticket', 'compra', 'pqrs')
     * @param EntityInterface $targetEntity Target entity
     * @param string $targetEntityNumber Target entity number (for directory naming)
     * @return int Number of attachments copied
     */
    protected function copyAttachments(
        string $sourceType,
        EntityInterface $sourceEntity,
        string $targetType,
        EntityInterface $targetEntity,
        string $targetEntityNumber
    ): int {
        // Get table names and foreign keys
        $sourceAttachmentsTable = $this->getAttachmentsTableName($sourceType);
        $targetAttachmentsTable = $this->getAttachmentsTableName($targetType);
        $targetForeignKey = $this->getForeignKeyName($targetType);

        // Get association name for source attachments
        $sourceAttachmentsAssoc = $this->getAttachmentsAssociationName($sourceType);

        // Get loaded attachments from source entity
        $sourceAttachments = $sourceEntity->get($sourceAttachmentsAssoc);

        if (empty($sourceAttachments)) {
            return 0;
        }

        // Create target directory
        $targetDir = $this->getAttachmentsDirectory($targetType, $targetEntityNumber);
        $targetPath = WWW_ROOT . $targetDir;

        if (!file_exists($targetPath)) {
            mkdir($targetPath, 0755, true);
        }

        $targetTable = $this->fetchTable($targetAttachmentsTable);
        $copiedCount = 0;

        foreach ($sourceAttachments as $attachment) {
            try {
                // Copy physical file
                $oldPath = WWW_ROOT . $attachment->file_path;
                $newFilePath = $targetPath . $attachment->filename;

                if (file_exists($oldPath)) {
                    copy($oldPath, $newFilePath);
                } else {
                    Log::warning('Source attachment file not found', [
                        'path' => $oldPath,
                        'attachment_id' => $attachment->id,
                    ]);
                    continue; // Skip this attachment
                }

                // Create database record
                $newAttachment = $targetTable->newEntity([
                    $targetForeignKey => $targetEntity->id,
                    $this->getCommentForeignKey($targetType) => null, // Not linked to specific comment
                    'filename' => $attachment->filename,
                    'original_filename' => $attachment->original_filename,
                    'file_path' => $targetDir . $attachment->filename,
                    'mime_type' => $attachment->mime_type,
                    'file_size' => $attachment->file_size,
                    'is_inline' => $attachment->is_inline,
                    'content_id' => $attachment->content_id,
                    'uploaded_by' => $this->getUploadedByField($attachment, $sourceType),
                ]);

                if ($targetTable->save($newAttachment)) {
                    $copiedCount++;
                } else {
                    Log::error('Failed to save attachment record', [
                        'errors' => $newAttachment->getErrors(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error copying attachment', [
                    'source_type' => $sourceType,
                    'target_type' => $targetType,
                    'filename' => $attachment->filename,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $copiedCount;
    }

    /**
     * Get attachments table name for entity type
     *
     * @param string $entityType Entity type
     * @return string Table name
     */
    private function getAttachmentsTableName(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'Attachments',
            'pqrs' => 'PqrsAttachments',
            'compra' => 'ComprasAttachments',
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };
    }

    /**
     * Get comment foreign key field name for attachments table
     *
     * @param string $entityType Entity type
     * @return string Comment foreign key field name
     */
    private function getCommentForeignKey(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'comment_id',
            'pqrs' => 'pqrs_comment_id',
            'compra' => 'compras_comment_id',
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };
    }

    /**
     * Get comments association name for entity
     *
     * @param string $entityType Entity type
     * @return string Association name
     */
    private function getCommentsAssociationName(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'ticket_comments',
            'pqrs' => 'pqrs_comments',
            'compra' => 'compras_comments',
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };
    }

    /**
     * Get attachments association name for entity
     *
     * @param string $entityType Entity type
     * @return string Association name
     */
    private function getAttachmentsAssociationName(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'attachments',
            'pqrs' => 'pqrs_attachments',
            'compra' => 'compras_attachments',
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };
    }

    /**
     * Get attachments directory for entity type
     *
     * @param string $entityType Entity type
     * @param string $entityNumber Entity number (e.g., TKT-2025-00001)
     * @return string Directory path (relative to WWW_ROOT)
     */
    private function getAttachmentsDirectory(string $entityType, string $entityNumber): string
    {
        $baseDir = match ($entityType) {
            'ticket' => 'uploads' . DS . 'attachments',
            'pqrs' => 'uploads' . DS . 'pqrs',
            'compra' => 'uploads' . DS . 'compras',
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };

        return $baseDir . DS . $entityNumber . DS;
    }

    /**
     * Get uploaded_by field value (handles different field names across entities)
     *
     * @param EntityInterface $attachment Source attachment
     * @param string $sourceType Source entity type
     * @return int|null User ID
     */
    private function getUploadedByField(EntityInterface $attachment, string $sourceType): ?int
    {
        // Different entity types use different field names
        if ($sourceType === 'ticket' && isset($attachment->uploaded_by)) {
            return $attachment->uploaded_by;
        }

        if (isset($attachment->uploaded_by_user_id)) {
            return $attachment->uploaded_by_user_id;
        }

        return null;
    }
}
