<?php
declare(strict_types=1);

namespace App\Service;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Cake\Core\Configure;
use Cake\Log\Log;
use Cake\Http\Exception\InternalErrorException;

/**
 * S3 Service
 *
 * Handles all AWS S3 operations for file storage:
 * - Upload files to S3
 * - Download files from S3
 * - Delete files from S3
 * - Generate presigned URLs for secure access
 */
class S3Service
{
    private ?S3Client $client = null;
    private string $bucket;
    private string $region;
    private bool $enabled;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->enabled = (bool)Configure::read('AWS.S3.enabled', false);
        $this->bucket = Configure::read('AWS.S3.bucket', '');
        $this->region = Configure::read('AWS.S3.region', 'us-east-1');

        if ($this->enabled) {
            $this->initializeClient();
        }
    }

    /**
     * Initialize S3 Client
     *
     * @return void
     */
    private function initializeClient(): void
    {
        try {
            $this->client = new S3Client([
                'version' => 'latest',
                'region' => $this->region,
                'credentials' => [
                    'key' => Configure::read('AWS.S3.key'),
                    'secret' => Configure::read('AWS.S3.secret'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to initialize S3 client: ' . $e->getMessage());
            throw new InternalErrorException('S3 service initialization failed');
        }
    }

    /**
     * Check if S3 is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->client !== null;
    }

    /**
     * Upload file to S3
     *
     * @param string $localPath Local file path
     * @param string $s3Path S3 object key (path in bucket)
     * @param string $contentType File MIME type
     * @return bool Success status
     */
    public function uploadFile(string $localPath, string $s3Path, string $contentType = 'application/octet-stream'): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('S3Service: Cannot upload, S3 is disabled');
            return false;
        }

        if (!file_exists($localPath)) {
            Log::error("S3Service: Local file not found: {$localPath}");
            return false;
        }

        try {
            $result = $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Path,
                'SourceFile' => $localPath,
                'ContentType' => $contentType,
                'ServerSideEncryption' => 'AES256',
            ]);

            Log::info("S3Service: File uploaded successfully to {$s3Path}");
            return true;
        } catch (AwsException $e) {
            Log::error("S3Service: Failed to upload file to S3: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Download file from S3
     *
     * @param string $s3Path S3 object key (path in bucket)
     * @param string $localPath Local destination path
     * @return bool Success status
     */
    public function downloadFile(string $s3Path, string $localPath): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('S3Service: Cannot download, S3 is disabled');
            return false;
        }

        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Path,
                'SaveAs' => $localPath,
            ]);

            Log::info("S3Service: File downloaded successfully from {$s3Path}");
            return true;
        } catch (AwsException $e) {
            Log::error("S3Service: Failed to download file from S3: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Delete file from S3
     *
     * @param string $s3Path S3 object key (path in bucket)
     * @return bool Success status
     */
    public function deleteFile(string $s3Path): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('S3Service: Cannot delete, S3 is disabled');
            return false;
        }

        try {
            $result = $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Path,
            ]);

            Log::info("S3Service: File deleted successfully from {$s3Path}");
            return true;
        } catch (AwsException $e) {
            Log::error("S3Service: Failed to delete file from S3: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Generate presigned URL for secure file access
     *
     * @param string $s3Path S3 object key (path in bucket)
     * @param int $expirationMinutes URL expiration time in minutes (default: 60)
     * @return string|null Presigned URL or null on failure
     */
    public function getPresignedUrl(string $s3Path, int $expirationMinutes = 60): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $cmd = $this->client->getCommand('GetObject', [
                'Bucket' => $this->bucket,
                'Key' => $s3Path,
            ]);

            $request = $this->client->createPresignedRequest($cmd, "+{$expirationMinutes} minutes");

            return (string)$request->getUri();
        } catch (AwsException $e) {
            Log::error("S3Service: Failed to generate presigned URL: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Stream file directly from S3
     *
     * @param string $s3Path S3 object key (path in bucket)
     * @return resource|null Stream resource or null on failure
     */
    public function getFileStream(string $s3Path)
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Path,
            ]);

            return $result->get('Body')->detach();
        } catch (AwsException $e) {
            Log::error("S3Service: Failed to get file stream: {$e->getMessage()}");
            return null;
        }
    }

}
