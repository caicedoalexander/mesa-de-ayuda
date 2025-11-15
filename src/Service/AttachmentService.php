<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Log\Log;
use Cake\Utility\Text;
use Cake\Http\Exception\NotFoundException;

/**
 * Attachment Service
 *
 * Handles file attachment operations:
 * - Saving attachments from emails
 * - Saving uploaded files
 * - Managing inline images
 * - File validation
 */
class AttachmentService
{
    use LocatorAwareTrait;

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
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],

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
     * Base upload directory
     */
    private const UPLOAD_DIR = 'uploads' . DS . 'attachments' . DS;

    /**
     * Save attachment from email
     *
     * @param int $ticketId Ticket ID
     * @param int|null $commentId Comment ID (null for ticket attachments)
     * @param string $filename Original filename
     * @param string $content Binary file content
     * @param string $mimeType MIME type
     * @param int $userId User ID who uploaded
     * @return \App\Model\Entity\Attachment|null
     */
    public function saveAttachment(
        int $ticketId,
        ?int $commentId,
        string $filename,
        string $content,
        string $mimeType,
        int $userId
    ): ?\App\Model\Entity\Attachment {
        // Sanitize filename
        $filename = $this->sanitizeFilename($filename);

        // Validate file
        $validation = $this->validateFile($filename, strlen($content), $mimeType);
        if ($validation !== true) {
            Log::error('File validation failed', [
                'filename' => $filename,
                'reason' => $validation,
            ]);
            return null;
        }

        // Generate unique filename
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $uniqueFilename = Text::uuid() . '.' . $extension;

        // Create directory structure: YYYY/MM/
        $directory = $this->createUploadDirectory();

        if (!$directory) {
            Log::error('Failed to create upload directory');
            return null;
        }

        // Full file path
        $relativePath = date('Y') . DS . date('m') . DS . $uniqueFilename;
        $fullPath = $directory . $uniqueFilename;

        // Save file
        if (file_put_contents($fullPath, $content) === false) {
            Log::error('Failed to write file', ['path' => $fullPath]);
            return null;
        }

        // Save to database
        $attachmentsTable = $this->fetchTable('Attachments');
        $attachment = $attachmentsTable->newEntity([
            'ticket_id' => $ticketId,
            'comment_id' => $commentId,
            'filename' => $uniqueFilename,
            'original_filename' => $filename,
            'file_path' => $relativePath,
            'mime_type' => $mimeType,
            'file_size' => strlen($content),
            'is_inline' => false,
            'uploaded_by' => $userId,
        ]);

        if ($attachmentsTable->save($attachment)) {
            Log::info('Attachment saved', [
                'ticket_id' => $ticketId,
                'filename' => $filename,
            ]);
            return $attachment;
        }

        // Cleanup file if database save failed
        @unlink($fullPath);

        Log::error('Failed to save attachment to database', ['errors' => $attachment->getErrors()]);
        return null;
    }

    /**
     * Save inline image from email
     *
     * @param int $ticketId Ticket ID
     * @param string $filename Original filename
     * @param string $content Binary file content
     * @param string $mimeType MIME type
     * @param string $contentId Content-ID from email
     * @param int $userId User ID who uploaded
     * @return \App\Model\Entity\Attachment|null
     */
    public function saveInlineImage(
        int $ticketId,
        string $filename,
        string $content,
        string $mimeType,
        string $contentId,
        int $userId
    ): ?\App\Model\Entity\Attachment {
        // Validate image
        if (!$this->isImageMimeType($mimeType)) {
            Log::error('Invalid image MIME type', [
                'mime_type' => $mimeType,
                'filename' => $filename,
                'content_id' => $contentId,
                'size' => strlen($content)
            ]);
            return null;
        }

        // Generate unique filename
        $extension = $this->getExtensionFromMimeType($mimeType);
        $uniqueFilename = Text::uuid() . '.' . $extension;

        // Create directory
        $directory = $this->createUploadDirectory();

        if (!$directory) {
            return null;
        }

        // Save file
        $relativePath = date('Y') . DS . date('m') . DS . $uniqueFilename;
        $fullPath = $directory . $uniqueFilename;

        if (file_put_contents($fullPath, $content) === false) {
            Log::error('Failed to write inline image', ['path' => $fullPath]);
            return null;
        }

        // Save to database
        $attachmentsTable = $this->fetchTable('Attachments');
        $attachment = $attachmentsTable->newEntity([
            'ticket_id' => $ticketId,
            'comment_id' => null,
            'filename' => $uniqueFilename,
            'original_filename' => $filename,
            'file_path' => $relativePath,
            'mime_type' => $mimeType,
            'file_size' => strlen($content),
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
     * Save uploaded file from form
     *
     * @param int $ticketId Ticket ID
     * @param int|null $commentId Comment ID
     * @param \Laminas\Diactoros\UploadedFile $uploadedFile Uploaded file
     * @param int $userId User ID who uploaded
     * @return \App\Model\Entity\Attachment|null
     */
    public function saveUploadedFile(
        int $ticketId,
        ?int $commentId,
        $uploadedFile,
        int $userId
    ): ?\App\Model\Entity\Attachment {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            Log::error('File upload error', ['error' => $uploadedFile->getError()]);
            return null;
        }

        $filename = $this->sanitizeFilename($uploadedFile->getClientFilename());
        $mimeType = $uploadedFile->getClientMediaType();
        $size = $uploadedFile->getSize();

        // Validate with MIME verification
        $validation = $this->validateFile($filename, $size, $mimeType);
        if ($validation !== true) {
            Log::error('File validation failed', [
                'filename' => $filename,
                'reason' => $validation,
            ]);
            return null;
        }

        // Additional security: verify actual MIME type from file content
        $tempPath = $uploadedFile->getStream()->getMetadata('uri');
        if ($tempPath && !$this->verifyMimeTypeFromContent($tempPath, $mimeType)) {
            Log::error('MIME type mismatch detected', [
                'filename' => $filename,
                'claimed_mime' => $mimeType,
            ]);
            return null;
        }

        // Generate unique filename
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $uniqueFilename = Text::uuid() . '.' . $extension;

        // Create directory
        $directory = $this->createUploadDirectory();

        if (!$directory) {
            return null;
        }

        // Move uploaded file
        $relativePath = date('Y') . DS . date('m') . DS . $uniqueFilename;
        $fullPath = $directory . $uniqueFilename;

        try {
            $uploadedFile->moveTo($fullPath);
        } catch (\Exception $e) {
            Log::error('Failed to move uploaded file', ['error' => $e->getMessage()]);
            return null;
        }

        // Save to database
        $attachmentsTable = $this->fetchTable('Attachments');
        $attachment = $attachmentsTable->newEntity([
            'ticket_id' => $ticketId,
            'comment_id' => $commentId,
            'filename' => $uniqueFilename,
            'original_filename' => $filename,
            'file_path' => $relativePath,
            'mime_type' => $mimeType,
            'file_size' => $size,
            'is_inline' => false,
            'uploaded_by' => $userId,
        ]);

        if ($attachmentsTable->save($attachment)) {
            return $attachment;
        }

        @unlink($fullPath);
        return null;
    }

    /**
     * Delete attachment
     *
     * @param int $attachmentId Attachment ID
     * @return bool Success status
     */
    public function deleteAttachment(int $attachmentId): bool
    {
        $attachmentsTable = $this->fetchTable('Attachments');

        try {
            $attachment = $attachmentsTable->get($attachmentId);

            // Delete physical file
            $fullPath = WWW_ROOT . self::UPLOAD_DIR . $attachment->file_path;
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            // Delete from database
            return $attachmentsTable->delete($attachment);
        } catch (NotFoundException $e) {
            Log::error('Attachment not found', ['id' => $attachmentId]);
            return false;
        }
    }

    /**
     * Validate file
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
     * @param string $filePath Path to file
     * @param string $claimedMime Claimed MIME type
     * @return bool True if matches
     */
    private function verifyMimeTypeFromContent(string $filePath, string $claimedMime): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        // Use finfo to detect actual MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            Log::warning('finfo_open failed, skipping MIME verification');
            return true; // Fail open if finfo not available
        }

        $actualMime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        if ($actualMime === false) {
            Log::warning('finfo_file failed', ['path' => $filePath]);
            return true; // Fail open
        }

        // Check if actual MIME matches claimed MIME
        // Some files may report slightly different MIME types, so we're flexible
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_TYPES[$extension])) {
            return false;
        }

        $allowedMimes = self::ALLOWED_TYPES[$extension];

        // Allow if actual MIME is in the allowed list for this extension
        return in_array($actualMime, $allowedMimes);
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
     * Check if MIME type is an image
     *
     * @param string $mimeType MIME type
     * @return bool
     */
    private function isImageMimeType(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
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
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/webp' => 'webp',
        ];

        return $map[$mimeType] ?? 'jpg';
    }

    /**
     * Create upload directory structure
     *
     * @return string|false Directory path or false on failure
     */
    private function createUploadDirectory()
    {
        $yearMonth = date('Y') . DS . date('m');
        $directory = WWW_ROOT . self::UPLOAD_DIR . $yearMonth . DS;

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0775, true)) {
                Log::error('Failed to create directory', ['path' => $directory]);
                return false;
            }
        }

        return $directory;
    }

    /**
     * Get attachment full path
     *
     * @param \App\Model\Entity\Attachment $attachment Attachment entity
     * @return string Full file path
     */
    public function getFullPath($attachment): string
    {
        return WWW_ROOT . self::UPLOAD_DIR . $attachment->file_path;
    }

    /**
     * Get attachment web URL
     *
     * @param \App\Model\Entity\Attachment $attachment Attachment entity
     * @return string Web URL
     */
    public function getWebUrl($attachment): string
    {
        return '/' . self::UPLOAD_DIR . str_replace(DS, '/', $attachment->file_path);
    }
}
