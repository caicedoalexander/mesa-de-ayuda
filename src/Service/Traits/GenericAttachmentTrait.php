<?php
declare(strict_types=1);

namespace App\Service\Traits;

use Cake\Datasource\EntityInterface;
use Cake\Log\Log;
use Cake\Utility\Text;
use Psr\Http\Message\UploadedFileInterface;

/**
 * GenericAttachmentTrait
 *
 * Unified attachment handling for Tickets, PQRS, and Compras
 * with robust security validation
 *
 * Requires using class to have:
 * - fetchTable() method (from LocatorAwareTrait)
 */
trait GenericAttachmentTrait
{
    /**
     * Allowed file extensions with their valid MIME types
     */
    private const ALLOWED_TYPES = [
        // Images
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'bmp' => ['image/bmp', 'image/x-ms-bmp'],
        'webp' => ['image/webp'],

        // Documents
        'pdf' => ['application/pdf', 'application/octet-stream'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],

        // Text
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],

        // Archives
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'rar' => ['application/x-rar-compressed', 'application/octet-stream'],
        '7z' => ['application/x-7z-compressed'],
    ];

    /**
     * Dangerous executable extensions that are NEVER allowed
     */
    private const FORBIDDEN_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar',
        'sh', 'app', 'deb', 'rpm', 'dmg', 'pkg', 'run', 'msi', 'dll',
        'sys', 'drv', 'cpl', 'scf', 'lnk', 'inf', 'reg',
    ];

    /**
     * Maximum file size in bytes (10MB)
     */
    private const MAX_FILE_SIZE = 10485760;

    /**
     * Maximum file size for images (5MB)
     */
    private const MAX_IMAGE_SIZE = 5242880;

    /**
     * Save uploaded file (generic for all entity types) with robust security validation
     *
     * @param string $entityType 'ticket', 'pqrs', 'compra'
     * @param EntityInterface $entity Entity instance
     * @param UploadedFileInterface $file Uploaded file
     * @param int|null $commentId Associated comment ID
     * @param int|null $userId Uploader user ID
     * @return EntityInterface|null Attachment entity or null
     */
    public function saveGenericUploadedFile(
        string $entityType,
        EntityInterface $entity,
        UploadedFileInterface $file,
        ?int $commentId = null,
        ?int $userId = null
    ): ?EntityInterface {
        // Validate file upload
        if ($file->getError() !== UPLOAD_ERR_OK) {
            Log::error("{$entityType} file upload error", ['error' => $file->getError()]);
            return null;
        }

        // Get file info
        $originalFilename = $file->getClientFilename();
        $mimeType = $file->getClientMediaType();
        $size = $file->getSize();

        // Sanitize filename
        $originalFilename = $this->sanitizeFilename($originalFilename);

        // Robust validation (security-critical)
        $validation = $this->validateFile($originalFilename, $size, $mimeType);
        if ($validation !== true) {
            Log::error("{$entityType} file validation failed", [
                'filename' => $originalFilename,
                'reason' => $validation,
            ]);
            return null;
        }

        // Additional security: verify actual MIME type from file content
        $tempPath = $file->getStream()->getMetadata('uri');
        if ($tempPath && !$this->verifyMimeTypeFromContent($tempPath, $mimeType, $originalFilename)) {
            Log::error("{$entityType} MIME type verification failed", [
                'filename' => $originalFilename,
                'claimed_mime' => $mimeType,
            ]);
            return null;
        }

        // Generate unique filename with UUID
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $filename = Text::uuid() . '.' . $extension;

        // Get entity number for directory
        $entityNumber = $this->getEntityNumber($entityType, $entity);

        // Create directory
        $uploadDir = $this->getUploadDirectory($entityType, $entityNumber);
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                Log::error("Failed to create {$entityType} upload directory", ['dir' => $uploadDir]);
                return null;
            }
        }

        // Move file
        $filePath = $uploadDir . DS . $filename;
        try {
            $file->moveTo($filePath);
        } catch (\Exception $e) {
            Log::error("Failed to move {$entityType} file", ['error' => $e->getMessage()]);
            return null;
        }

        // Save to database
        $attachmentTableName = $this->getAttachmentTableName($entityType);
        $attachmentsTable = $this->fetchTable($attachmentTableName);

        $data = $this->buildAttachmentData(
            $entityType,
            $entity->id,
            $commentId,
            $userId,
            $originalFilename,
            $filename,
            $entityNumber,
            $mimeType,
            $size
        );

        $attachment = $attachmentsTable->newEntity($data);

        if (!$attachmentsTable->save($attachment)) {
            Log::error("Failed to save {$entityType} attachment to database", [
                'errors' => $attachment->getErrors()
            ]);
            // Clean up file
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            return null;
        }

        return $attachment;
    }

    /**
     * Get entity number (ticket_number, pqrs_number, compra_number)
     *
     * @param string $entityType Entity type
     * @param EntityInterface $entity Entity instance
     * @return string Entity number
     */
    private function getEntityNumber(string $entityType, EntityInterface $entity): string
    {
        return match ($entityType) {
            'ticket' => $entity->ticket_number,
            'pqrs' => $entity->pqrs_number,
            'compra' => $entity->compra_number,
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    /**
     * Get upload directory path for entity type
     *
     * @param string $entityType Entity type
     * @param string $entityNumber Entity number
     * @return string Full directory path
     */
    private function getUploadDirectory(string $entityType, string $entityNumber): string
    {
        $basePaths = [
            'ticket' => 'uploads' . DS . 'attachments' . DS,
            'pqrs' => 'uploads' . DS . 'pqrs' . DS,
            'compra' => 'uploads' . DS . 'compras' . DS,
        ];

        if (!isset($basePaths[$entityType])) {
            throw new \InvalidArgumentException("Unknown entity type: {$entityType}");
        }

        return WWW_ROOT . $basePaths[$entityType] . $entityNumber;
    }

    /**
     * Get attachment table name for entity type
     *
     * @param string $entityType Entity type
     * @return string Table name
     */
    private function getAttachmentTableName(string $entityType): string
    {
        return match ($entityType) {
            'ticket' => 'Attachments',
            'pqrs' => 'PqrsAttachments',
            'compra' => 'ComprasAttachments',
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    /**
     * Build attachment data array for entity type
     *
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param int|null $commentId Comment ID
     * @param int|null $userId User ID
     * @param string $originalFilename Original filename
     * @param string $filename Generated filename
     * @param string $entityNumber Entity number
     * @param string $mimeType MIME type
     * @param int $size File size
     * @return array Attachment data
     */
    private function buildAttachmentData(
        string $entityType,
        int $entityId,
        ?int $commentId,
        ?int $userId,
        string $originalFilename,
        string $filename,
        string $entityNumber,
        string $mimeType,
        int $size
    ): array {
        $relativePath = match ($entityType) {
            'ticket' => 'attachments/' . $entityNumber . '/' . $filename,
            'pqrs' => 'pqrs/' . $entityNumber . '/' . $filename,
            'compra' => 'compras/' . $entityNumber . '/' . $filename,
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };

        $data = [
            'original_filename' => $originalFilename,
            'filename' => $filename,
            'file_path' => 'uploads/' . $relativePath,
            'mime_type' => $mimeType,
            'file_size' => $size,
        ];

        // Entity-specific fields
        switch ($entityType) {
            case 'ticket':
                $data['ticket_id'] = $entityId;
                $data['comment_id'] = $commentId;
                $data['uploaded_by'] = $userId;
                $data['is_inline'] = false;
                break;

            case 'pqrs':
                $data['pqrs_id'] = $entityId;
                $data['pqrs_comment_id'] = $commentId;
                $data['user_id'] = $userId;
                break;

            case 'compra':
                $data['compra_id'] = $entityId;
                $data['compras_comment_id'] = $commentId;
                $data['user_id'] = $userId;
                break;

            default:
                throw new \InvalidArgumentException("Unknown entity type: {$entityType}");
        }

        return $data;
    }

    /**
     * Save attachment from binary content (for email attachments)
     *
     * @param string $entityType Entity type ('ticket', 'pqrs', 'compra')
     * @param EntityInterface $entity Entity instance
     * @param string $filename Original filename
     * @param string $binaryContent Binary file content
     * @param string $mimeType MIME type
     * @param int|null $commentId Comment ID
     * @param int $userId User ID
     * @return EntityInterface|null Attachment entity or null
     */
    public function saveAttachmentFromBinary(
        string $entityType,
        EntityInterface $entity,
        string $filename,
        string $binaryContent,
        string $mimeType,
        ?int $commentId,
        int $userId
    ): ?EntityInterface {
        // Sanitize filename
        $filename = $this->sanitizeFilename($filename);

        // Validate file
        $size = strlen($binaryContent);
        $validation = $this->validateFile($filename, $size, $mimeType);
        if ($validation !== true) {
            Log::error("{$entityType} attachment validation failed", [
                'filename' => $filename,
                'reason' => $validation,
            ]);
            return null;
        }

        // Generate unique filename
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $uniqueFilename = Text::uuid() . '.' . $extension;

        // Get entity number and create directory
        $entityNumber = $this->getEntityNumber($entityType, $entity);
        $uploadDir = $this->getUploadDirectory($entityType, $entityNumber);

        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                Log::error("Failed to create {$entityType} upload directory", ['dir' => $uploadDir]);
                return null;
            }
        }

        // Save file
        $fullPath = $uploadDir . DS . $uniqueFilename;
        if (file_put_contents($fullPath, $binaryContent) === false) {
            Log::error("Failed to write {$entityType} file", ['path' => $fullPath]);
            return null;
        }

        // Build attachment data
        $data = $this->buildAttachmentData(
            $entityType,
            $entity->id,
            $commentId,
            $userId,
            $filename,
            $uniqueFilename,
            $entityNumber,
            $mimeType,
            $size
        );

        // For tickets, add is_inline flag
        if ($entityType === 'ticket') {
            $data['is_inline'] = false;
        }

        // Save to database
        $attachmentTableName = $this->getAttachmentTableName($entityType);
        $attachmentsTable = $this->fetchTable($attachmentTableName);
        $attachment = $attachmentsTable->newEntity($data);

        if ($attachmentsTable->save($attachment)) {
            Log::info("{$entityType} attachment saved from binary", [
                'entity_id' => $entity->id,
                'filename' => $filename,
            ]);
            return $attachment;
        }

        // Cleanup on failure
        @unlink($fullPath);
        Log::error("Failed to save {$entityType} attachment to database", [
            'errors' => $attachment->getErrors()
        ]);
        return null;
    }

    /**
     * Save inline image from email (tickets only)
     *
     * @param EntityInterface $ticket Ticket entity
     * @param string $filename Original filename
     * @param string $binaryContent Binary file content
     * @param string $mimeType MIME type
     * @param string $contentId Content-ID from email
     * @param int $userId User ID
     * @return EntityInterface|null Attachment entity or null
     */
    public function saveInlineImage(
        EntityInterface $ticket,
        string $filename,
        string $binaryContent,
        string $mimeType,
        string $contentId,
        int $userId
    ): ?EntityInterface {
        // Validate that it's actually an image
        if (!str_starts_with($mimeType, 'image/')) {
            Log::error('Invalid image MIME type for inline image', [
                'mime_type' => $mimeType,
                'filename' => $filename,
            ]);
            return null;
        }

        $size = strlen($binaryContent);

        // Validate image
        $validation = $this->validateFile($filename, $size, $mimeType);
        if ($validation !== true) {
            Log::error('Inline image validation failed', [
                'filename' => $filename,
                'reason' => $validation,
            ]);
            return null;
        }

        // Generate unique filename
        $extension = $this->getExtensionFromMimeType($mimeType);
        $uniqueFilename = Text::uuid() . '.' . $extension;

        // Create directory
        $ticketNumber = $ticket->ticket_number;
        $uploadDir = $this->getUploadDirectory('ticket', $ticketNumber);

        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return null;
            }
        }

        // Save file
        $fullPath = $uploadDir . DS . $uniqueFilename;
        if (file_put_contents($fullPath, $binaryContent) === false) {
            Log::error('Failed to write inline image', ['path' => $fullPath]);
            return null;
        }

        // Save to database with inline flag
        $attachmentsTable = $this->fetchTable('Attachments');
        $attachment = $attachmentsTable->newEntity([
            'ticket_id' => $ticket->id,
            'comment_id' => null,
            'filename' => $uniqueFilename,
            'original_filename' => $filename,
            'file_path' => 'attachments/' . $ticketNumber . '/' . $uniqueFilename,
            'mime_type' => $mimeType,
            'file_size' => $size,
            'is_inline' => true,
            'content_id' => $contentId,
            'uploaded_by' => $userId,
        ]);

        if ($attachmentsTable->save($attachment)) {
            return $attachment;
        }

        @unlink($fullPath);
        return null;
    }

    /**
     * Delete attachment file and database record
     *
     * @param string $entityType Entity type
     * @param int $attachmentId Attachment ID
     * @return bool Success status
     */
    public function deleteGenericAttachment(string $entityType, int $attachmentId): bool
    {
        try {
            $attachmentTableName = $this->getAttachmentTableName($entityType);
            $attachmentsTable = $this->fetchTable($attachmentTableName);

            $attachment = $attachmentsTable->get($attachmentId);

            // Delete physical file
            $fullPath = WWW_ROOT . $attachment->file_path;
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            // Delete from database
            return $attachmentsTable->delete($attachment);
        } catch (\Exception $e) {
            Log::error("Failed to delete {$entityType} attachment", [
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Validate file for security threats
     *
     * @param string $filename Filename
     * @param int $size File size in bytes
     * @param string|null $mimeType MIME type to verify
     * @return bool|string True if valid, error message otherwise
     */
    private function validateFile(string $filename, int $size, ?string $mimeType = null)
    {
        // Get extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Check for forbidden extensions (executables)
        if (in_array($extension, self::FORBIDDEN_EXTENSIONS)) {
            return 'Executable files are not allowed';
        }

        // Check if extension is allowed
        if (!isset(self::ALLOWED_TYPES[$extension])) {
            return 'File type not allowed: ' . $extension;
        }

        // Check size based on file type
        $maxSize = str_starts_with($mimeType ?? '', 'image/')
            ? self::MAX_IMAGE_SIZE
            : self::MAX_FILE_SIZE;

        if ($size > $maxSize) {
            $maxMB = round($maxSize / 1048576, 1);
            return "File too large. Maximum size: {$maxMB}MB";
        }

        if ($size === 0) {
            return 'File is empty';
        }

        // Verify MIME type matches extension
        if ($mimeType !== null) {
            $allowedMimes = self::ALLOWED_TYPES[$extension];
            if (!in_array($mimeType, $allowedMimes)) {
                return 'MIME type does not match file extension';
            }
        }

        // Check for double extensions (e.g., file.pdf.exe)
        $parts = explode('.', $filename);
        if (count($parts) > 2) {
            foreach ($parts as $part) {
                $partExt = strtolower($part);
                if (in_array($partExt, self::FORBIDDEN_EXTENSIONS)) {
                    return 'Suspicious filename detected';
                }
            }
        }

        return true;
    }

    /**
     * Verify MIME type from actual file content using finfo
     *
     * @param string $filePath Path to file (temporary file)
     * @param string $claimedMime Claimed MIME type
     * @param string $originalFilename Original filename with extension
     * @return bool True if matches
     */
    private function verifyMimeTypeFromContent(string $filePath, string $claimedMime, string $originalFilename): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        // Use finfo to detect actual MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return true; // Fail open if finfo not available
        }

        $actualMime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        if ($actualMime === false) {
            return true; // Fail open
        }

        // Get extension from ORIGINAL filename, not temp file path
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_TYPES[$extension])) {
            return false;
        }

        $allowedMimes = self::ALLOWED_TYPES[$extension];

        // Direct match - ideal case
        if (in_array($actualMime, $allowedMimes)) {
            return true;
        }

        // Special cases: Modern Office files (docx, xlsx, pptx) are ZIP archives
        if ($actualMime === 'application/zip' && in_array($extension, ['docx', 'xlsx', 'pptx'])) {
            return true;
        }

        // Allow claimed MIME if it's in the allowed list for this extension
        if (in_array($claimedMime, $allowedMimes)) {
            return true;
        }

        return false;
    }

    /**
     * Sanitize filename to prevent path traversal and other attacks
     *
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove any path components
        $filename = basename($filename);

        // Remove null bytes
        $filename = str_replace("\0", '', $filename);

        // Remove directory traversal attempts
        $filename = str_replace(['../', '..\\'], '', $filename);

        // Remove special characters except dots, dashes, underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Limit length
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = pathinfo($filename, PATHINFO_FILENAME);
            $basename = substr($basename, 0, 250 - strlen($extension));
            $filename = $basename . '.' . $extension;
        }

        return $filename;
    }

    /**
     * Get file extension from MIME type
     *
     * @param string $mimeType MIME type
     * @return string Extension
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/webp' => 'webp',
        ];

        return $map[$mimeType] ?? 'jpg';
    }

    /**
     * Get full filesystem path for an attachment
     *
     * @param EntityInterface $attachment Attachment entity
     * @return string Full file path
     */
    public function getFullPath(EntityInterface $attachment): string
    {
        return WWW_ROOT . $attachment->file_path;
    }

    /**
     * Get web URL for an attachment
     *
     * @param EntityInterface $attachment Attachment entity
     * @return string Web URL
     */
    public function getWebUrl(EntityInterface $attachment): string
    {
        return '/' . str_replace(DS, '/', $attachment->file_path);
    }
}
