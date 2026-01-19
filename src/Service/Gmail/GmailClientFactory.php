<?php
declare(strict_types=1);

namespace App\Service\Gmail;

use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Cake\Log\Log;
use App\Service\S3Service;

/**
 * Gmail Client Factory
 *
 * Handles Google Client initialization and OAuth2 authentication.
 * Extracted from GmailService to follow Single Responsibility Principle.
 *
 * Resolves: ARCH-001 (GmailService SRP violation)
 *
 * Responsibilities:
 * - Create and configure Google Client
 * - Handle OAuth2 token refresh
 * - Resolve client secret paths (local or S3)
 * - Provide Gmail service instance
 */
class GmailClientFactory
{
    private GoogleClient $client;
    private ?Gmail $gmailService = null;
    private array $config;
    private S3Service $s3Service;

    /**
     * Constructor
     *
     * @param array $config Configuration array with client_secret_path and refresh_token
     * @param S3Service|null $s3Service Optional S3Service instance (for DI/testing)
     */
    public function __construct(array $config = [], ?S3Service $s3Service = null)
    {
        $this->config = $config;
        $this->s3Service = $s3Service ?? new S3Service();
        $this->initializeClient();
    }

    /**
     * Initialize Google Client with OAuth2
     *
     * @return void
     */
    private function initializeClient(): void
    {
        $this->client = new GoogleClient();

        // Load client secret from file (local or S3)
        $clientSecretPath = $this->config['client_secret_path'] ?? CONFIG . 'google' . DS . 'client_secret.json';
        $actualFilePath = $this->resolveClientSecretPath($clientSecretPath);

        if ($actualFilePath && file_exists($actualFilePath)) {
            $this->client->setAuthConfig($actualFilePath);
        } else {
            Log::error('Client secret file not found: ' . $clientSecretPath);
        }

        $this->client->addScope(Gmail::GMAIL_READONLY);
        $this->client->addScope(Gmail::GMAIL_SEND);
        $this->client->addScope(Gmail::GMAIL_MODIFY);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent'); // Force to always get refresh_token

        // Set redirect URI for OAuth2 flow
        if (!empty($this->config['redirect_uri'])) {
            $this->client->setRedirectUri($this->config['redirect_uri']);
        }

        // Set refresh token and fetch access token if available
        if (!empty($this->config['refresh_token'])) {
            $this->refreshAccessToken();
        }
    }

    /**
     * Refresh access token using stored refresh token
     *
     * @return void
     * @throws \RuntimeException If token refresh fails
     */
    private function refreshAccessToken(): void
    {
        try {
            $token = $this->client->fetchAccessTokenWithRefreshToken($this->config['refresh_token']);

            if (isset($token['error'])) {
                Log::error('OAuth token refresh failed', ['error' => $token]);
                throw new \RuntimeException('Gmail authentication failed: ' . ($token['error_description'] ?? $token['error']));
            }
        } catch (\Exception $e) {
            Log::error('Failed to refresh OAuth token: ' . $e->getMessage());
            throw new \RuntimeException('Gmail authentication failed. Please re-authenticate in Admin Settings.');
        }
    }

    /**
     * Resolve client secret path - download from S3 if needed
     *
     * @param string $path Path from config (could be local path or S3 key)
     * @return string|null Actual local file path
     */
    private function resolveClientSecretPath(string $path): ?string
    {
        // If it's already a local file that exists, return it
        if (file_exists($path)) {
            return $path;
        }

        // Check if S3 is enabled using injected service
        if (!$this->s3Service->isEnabled()) {
            return null;
        }

        // Check if this looks like an S3 key
        $isS3Key = (strpos($path, 'config/') === 0) || (strpos($path, '/') !== 0 && strpos($path, DS) !== 0);

        if (!$isS3Key) {
            return null;
        }

        // Download from S3 to cache directory
        $cacheDir = TMP . 'config_cache' . DS;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $localCachePath = $cacheDir . basename($path);

        // Check if we have a recent cached version (valid for 1 hour)
        if (file_exists($localCachePath)) {
            $fileAge = time() - filemtime($localCachePath);
            if ($fileAge < 3600) {
                return $localCachePath;
            }
        }

        // Download from S3
        if ($this->s3Service->downloadFile($path, $localCachePath)) {
            Log::info('Downloaded client secret from S3', ['s3_key' => $path]);
            return $localCachePath;
        }

        Log::error('Failed to download client secret from S3', ['s3_key' => $path]);
        return null;
    }

    /**
     * Get the Google Client instance
     *
     * @return GoogleClient
     */
    public function getClient(): GoogleClient
    {
        return $this->client;
    }

    /**
     * Get Gmail service instance (lazy loaded)
     *
     * @return Gmail
     */
    public function getGmailService(): Gmail
    {
        if ($this->gmailService === null) {
            $this->gmailService = new Gmail($this->client);
        }

        return $this->gmailService;
    }

    /**
     * Get authorization URL for OAuth2 flow
     *
     * @return string
     */
    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Exchange authorization code for access token
     *
     * @param string $code Authorization code
     * @return array Token data including refresh_token
     * @throws \RuntimeException If authentication fails
     */
    public function authenticate(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            Log::error('Gmail authentication error: ' . $token['error']);
            throw new \RuntimeException('Failed to authenticate with Gmail: ' . $token['error']);
        }

        return $token;
    }
}
