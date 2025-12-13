<?php

declare(strict_types=1);

namespace App\Middleware;

use Libraries\DayLog;

/**
 * API Authentication Middleware
 * Validates API keys and implements rate limiting
 *
 * @version 1.0.0
 * @author SimpleData Corp
 */
class ApiAuthMiddleware
{
    private DayLog $log;
    private array $validApiKeys;
    private string $tx;

    // Rate limiting: requests per minute per API key
    private const RATE_LIMIT = 60;
    private const RATE_WINDOW = 60; // seconds

    private static array $rateLimitCache = [];

    public function __construct(DayLog $log, string $tx)
    {
        $this->log = $log;
        $this->tx = $tx;

        // Load valid API keys from environment
        $apiKeysEnv = $_ENV['VALID_API_KEYS'] ?? '';
        $this->validApiKeys = array_filter(explode(',', $apiKeysEnv));

        if (empty($this->validApiKeys)) {
            $this->log->writeLog("{$this->tx} [auth_warning] No valid API keys configured\n");
        }
    }

    /**
     * Validate authentication
     * @return array ['authenticated' => bool, 'error' => string|null, 'code' => int]
     */
    public function authenticate(): array
    {
        // Check for API key in header
        $apiKey = $this->getApiKeyFromRequest();

        if (empty($apiKey)) {
            $this->log->writeLog("{$this->tx} [auth] Missing API key\n");
            return [
                'authenticated' => false,
                'error' => 'API key is required',
                'code' => 401
            ];
        }

        // Validate API key
        if (!$this->isValidApiKey($apiKey)) {
            $this->log->writeLog("{$this->tx} [auth] Invalid API key: " . substr($apiKey, 0, 8) . "...\n");
            return [
                'authenticated' => false,
                'error' => 'Invalid API key',
                'code' => 401
            ];
        }

        // Check rate limit
        if (!$this->checkRateLimit($apiKey)) {
            $this->log->writeLog("{$this->tx} [auth] Rate limit exceeded for key: " . substr($apiKey, 0, 8) . "...\n");
            return [
                'authenticated' => false,
                'error' => 'Rate limit exceeded. Maximum ' . self::RATE_LIMIT . ' requests per minute.',
                'code' => 429
            ];
        }

        $this->log->writeLog("{$this->tx} [auth] Authentication successful\n");

        return [
            'authenticated' => true,
            'error' => null,
            'code' => 200
        ];
    }

    /**
     * Get API key from request headers
     * @return string|null
     */
    private function getApiKeyFromRequest(): ?string
    {
        // Check X-API-Key header
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            return trim($_SERVER['HTTP_X_API_KEY']);
        }

        // Check Authorization Bearer token
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Validate API key
     * @param string $apiKey
     * @return bool
     */
    private function isValidApiKey(string $apiKey): bool
    {
        // Use constant-time comparison to prevent timing attacks
        foreach ($this->validApiKeys as $validKey) {
            if (hash_equals(trim($validKey), $apiKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check rate limit for API key
     * @param string $apiKey
     * @return bool
     */
    private function checkRateLimit(string $apiKey): bool
    {
        $now = time();
        $keyHash = hash('sha256', $apiKey);

        // Initialize rate limit data if not exists
        if (!isset(self::$rateLimitCache[$keyHash])) {
            self::$rateLimitCache[$keyHash] = [
                'requests' => [],
                'window_start' => $now
            ];
        }

        $cache = &self::$rateLimitCache[$keyHash];

        // Remove requests outside the current window
        $cache['requests'] = array_filter(
            $cache['requests'],
            fn($timestamp) => $timestamp > ($now - self::RATE_WINDOW)
        );

        // Check if limit exceeded
        if (count($cache['requests']) >= self::RATE_LIMIT) {
            return false;
        }

        // Add current request
        $cache['requests'][] = $now;

        return true;
    }

    /**
     * Clean old rate limit cache entries (call periodically)
     */
    public static function cleanRateLimitCache(): void
    {
        $now = time();

        foreach (self::$rateLimitCache as $key => &$data) {
            // Remove entries older than the rate window
            $data['requests'] = array_filter(
                $data['requests'],
                fn($timestamp) => $timestamp > ($now - self::RATE_WINDOW)
            );

            // Remove empty entries
            if (empty($data['requests'])) {
                unset(self::$rateLimitCache[$key]);
            }
        }
    }
}
