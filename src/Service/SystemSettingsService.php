<?php
declare(strict_types=1);

namespace App\Service;

use App\Utility\SettingsEncryptionTrait;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Cache\Cache;
use Cake\Log\Log;

/**
 * System Settings Service
 *
 * Centralized service for loading and managing system-wide configuration settings.
 * Eliminates redundant database queries by implementing cache layer.
 *
 * Benefits:
 * - Single source of truth for system settings
 * - Automatic encryption/decryption of sensitive settings
 * - Cache layer reduces database load (1 query/hour vs 1 query/request)
 * - Type-safe API for accessing settings
 *
 * Usage:
 *   $service = new SystemSettingsService();
 *   $gmailToken = $service->get('gmail_refresh_token'); // Auto-decrypted
 *   $all = $service->getAll(); // All settings cached
 *
 * Resolves: ARCH-002 (Query directa estática en múltiples servicios)
 */
class SystemSettingsService
{
    use LocatorAwareTrait;
    use SettingsEncryptionTrait;

    private const CACHE_KEY = 'system_settings';
    private const CACHE_CONFIG = '_cake_core_';
    private const CACHE_DURATION = 3600; // 1 hour

    private ?array $settings = null;

    /**
     * Get all system settings
     *
     * Returns all settings as associative array with automatic decryption
     * of sensitive values (tokens, passwords, secrets, API keys).
     *
     * @param bool $refresh Force refresh from database (bypass cache)
     * @return array All settings as setting_key => setting_value
     */
    public function getAll(bool $refresh = false): array
    {
        if ($this->settings !== null && !$refresh) {
            return $this->settings;
        }

        if ($refresh) {
            Cache::delete(self::CACHE_KEY, self::CACHE_CONFIG);
        }

        $this->settings = Cache::remember(
            self::CACHE_KEY,
            function () {
                return $this->loadFromDatabase();
            },
            self::CACHE_CONFIG
        );

        return $this->settings;
    }

    /**
     * Get single setting value by key
     *
     * Returns null if setting doesn't exist.
     * Automatically decrypts sensitive settings.
     *
     * @param string $key Setting key (e.g., 'gmail_refresh_token')
     * @param mixed $default Default value if setting not found
     * @return mixed Setting value or default
     */
    public function get(string $key, $default = null)
    {
        $settings = $this->getAll();
        return $settings[$key] ?? $default;
    }

    /**
     * Get multiple settings by keys
     *
     * Efficiently retrieves multiple settings in one call.
     *
     * @param array $keys Array of setting keys
     * @return array Associative array of found settings
     */
    public function getMany(array $keys): array
    {
        $settings = $this->getAll();
        $result = [];

        foreach ($keys as $key) {
            if (isset($settings[$key])) {
                $result[$key] = $settings[$key];
            }
        }

        return $result;
    }

    /**
     * Check if setting exists
     *
     * @param string $key Setting key
     * @return bool True if setting exists
     */
    public function has(string $key): bool
    {
        $settings = $this->getAll();
        return isset($settings[$key]);
    }

    /**
     * Get setting as boolean
     *
     * Useful for flag settings (enabled/disabled).
     * Recognizes: '1', 'true', 'yes', 'on' as true (case-insensitive)
     *
     * @param string $key Setting key
     * @param bool $default Default value if setting not found
     * @return bool Setting value as boolean
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower((string)$value);
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Get setting as integer
     *
     * @param string $key Setting key
     * @param int $default Default value if setting not found
     * @return int Setting value as integer
     */
    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return (int)$value;
    }

    /**
     * Refresh settings cache
     *
     * Forces reload from database and updates cache.
     * Useful after settings are updated in admin panel.
     *
     * @return void
     */
    public function refresh(): void
    {
        $this->getAll(refresh: true);
    }

    /**
     * Clear settings cache
     *
     * Removes cached settings. Next getAll() will reload from database.
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::delete(self::CACHE_KEY, self::CACHE_CONFIG);
        $this->settings = null;
    }

    /**
     * Load settings from database
     *
     * Private method that performs actual database query.
     * Automatically decrypts sensitive settings using SettingsEncryptionTrait.
     *
     * @return array Settings as setting_key => setting_value
     */
    private function loadFromDatabase(): array
    {
        try {
            $settingsTable = $this->fetchTable('SystemSettings');

            $settings = $settingsTable->find()
                ->select(['setting_key', 'setting_value'])
                ->all()
                ->combine('setting_key', 'setting_value')
                ->toArray();

            // Process settings to decrypt sensitive values
            return $this->processSettings($settings);

        } catch (\Exception $e) {
            Log::error('Failed to load system settings from database', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty array on error to prevent application crash
            return [];
        }
    }

    /**
     * Process settings array (decrypt sensitive values)
     *
     * Uses SettingsEncryptionTrait to automatically decrypt settings
     * that contain sensitive keywords (token, secret, password, key).
     *
     * @param array $settings Raw settings from database
     * @return array Processed settings with decrypted values
     */
    private function processSettings(array $settings): array
    {
        $processed = [];

        foreach ($settings as $key => $value) {
            // Decrypt if this is a sensitive setting
            $processed[$key] = $this->decryptSetting($value, $key);
        }

        return $processed;
    }

    /**
     * Get cache statistics
     *
     * Useful for debugging and monitoring cache performance.
     *
     * @return array Cache statistics
     */
    public function getCacheStats(): array
    {
        $isCached = Cache::read(self::CACHE_KEY, self::CACHE_CONFIG) !== null;

        return [
            'cache_key' => self::CACHE_KEY,
            'cache_config' => self::CACHE_CONFIG,
            'is_cached' => $isCached,
            'cache_duration' => self::CACHE_DURATION,
            'settings_loaded' => $this->settings !== null,
            'settings_count' => $this->settings ? count($this->settings) : 0,
        ];
    }
}
