<?php

declare(strict_types=1);

namespace App\Middleware;

use App\DTO\ApiResponseDTO;
use Libraries\DayLog;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * API Authentication Middleware for Slim 4
 * Validates API keys and implements rate limiting
 * 
 * @version 1.0.0
 * @author SimpleData Corp
 */
class VideoAuthMiddleware implements MiddlewareInterface
{
    private DayLog $log;
    private array $validApiKeys;
    
    // Rate limiting: requests per minute per API key
    private const RATE_LIMIT = 60;
    private const RATE_WINDOW = 60; // seconds
    
    private static array $rateLimitCache = [];

    public function __construct()
    {
        $this->log = new DayLog(BASE_HOME_PATH, 'VideoAuth');
        
        // Load valid API keys from environment
        $apiKeysEnv = $_ENV['VALID_API_KEYS'] ?? '';
        $this->validApiKeys = array_filter(explode(',', $apiKeysEnv));
        
        if (empty($this->validApiKeys)) {
            $tx = substr(uniqid(), 3);
            $this->log->setTx($tx);
            $this->log->writeLog("{$tx} [auth_warning] No valid API keys configured\n");
        }
    }

    /**
     * Process middleware
     * 
     * @param Request $request
     * @param RequestHandlerInterface $handler
     * @return Response
     */
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $tx = substr(uniqid(), 3);
        $this->log->setTx($tx);
        
        // Skip authentication for health check
        $uri = $request->getUri()->getPath();
        if (preg_match('#/health$#', $uri)) {
            return $handler->handle($request);
        }

        // Get API key from request
        $apiKey = $this->getApiKeyFromRequest($request);
        
        if (empty($apiKey)) {
            $this->log->writeLog("{$tx} [auth] Missing API key\n");
            return $this->unauthorizedResponse('API key is required', 401);
        }

        // Validate API key
        if (!$this->isValidApiKey($apiKey)) {
            $this->log->writeLog("{$tx} [auth] Invalid API key: " . substr($apiKey, 0, 8) . "...\n");
            return $this->unauthorizedResponse('Invalid API key', 401);
        }

        // Check rate limit
        if (!$this->checkRateLimit($apiKey)) {
            $this->log->writeLog("{$tx} [auth] Rate limit exceeded for key: " . substr($apiKey, 0, 8) . "...\n");
            return $this->unauthorizedResponse(
                'Rate limit exceeded. Maximum ' . self::RATE_LIMIT . ' requests per minute.',
                429
            );
        }

        $this->log->writeLog("{$tx} [auth] Authentication successful\n");
        
        // Clean old cache entries periodically
        self::cleanRateLimitCache();
        
        // Continue to next middleware/route
        return $handler->handle($request);
    }

    /**
     * Get API key from request headers
     * @param Request $request
     * @return string|null
     */
    private function getApiKeyFromRequest(Request $request): ?string
    {
        // Check X-API-Key header
        $headers = $request->getHeaders();
        if (isset($headers['X-API-Key'][0])) {
            return trim($headers['X-API-Key'][0]);
        }

        // Check Authorization Bearer token
        if (isset($headers['Authorization'][0])) {
            $auth = $headers['Authorization'][0];
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
     * Clean old rate limit cache entries
     */
    private static function cleanRateLimitCache(): void
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

    /**
     * Return unauthorized response
     * @param string $message
     * @param int $code
     * @return Response
     */
    private function unauthorizedResponse(string $message, int $code): Response
    {
        $result = ApiResponseDTO::error($message, $code);
        $response = new SlimResponse();
        $response->getBody()->write(json_encode($result->toArray()));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($code);
    }
}
