<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Middleware\ApiAuthMiddleware;
use Libraries\DayLog;

/**
 * Unit tests for ApiAuthMiddleware
 */
class ApiAuthMiddlewareTest extends TestCase
{
    private DayLog $log;
    private string $tx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->log = new DayLog();
        $this->tx = 'test-tx-' . uniqid();
        
        // Set test API keys
        $_ENV['VALID_API_KEYS'] = 'test-key-1,test-key-2,test-key-3';
    }

    protected function tearDown(): void
    {
        // Clean up server variables
        unset($_SERVER['HTTP_X_API_KEY']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
        
        // Reset rate limit cache using reflection
        $reflection = new \ReflectionClass(ApiAuthMiddleware::class);
        $property = $reflection->getProperty('rateLimitCache');
        $property->setAccessible(true);
        $property->setValue(null, []);
        
        parent::tearDown();
    }

    public function testAuthenticationSuccessWithXApiKey(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'test-key-1';
        
        $middleware = new ApiAuthMiddleware($this->log, $this->tx);
        $result = $middleware->authenticate();

        $this->assertTrue($result['authenticated']);
        $this->assertNull($result['error']);
        $this->assertEquals(200, $result['code']);
    }

    public function testAuthenticationSuccessWithBearerToken(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-key-2';
        
        $middleware = new ApiAuthMiddleware($this->log, $this->tx);
        $result = $middleware->authenticate();

        $this->assertTrue($result['authenticated']);
        $this->assertNull($result['error']);
        $this->assertEquals(200, $result['code']);
    }

    public function testAuthenticationFailsWithMissingApiKey(): void
    {
        $middleware = new ApiAuthMiddleware($this->log, $this->tx);
        $result = $middleware->authenticate();

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('API key is required', $result['error']);
        $this->assertEquals(401, $result['code']);
    }

    public function testAuthenticationFailsWithInvalidApiKey(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'invalid-key';
        
        $middleware = new ApiAuthMiddleware($this->log, $this->tx);
        $result = $middleware->authenticate();

        $this->assertFalse($result['authenticated']);
        $this->assertEquals('Invalid API key', $result['error']);
        $this->assertEquals(401, $result['code']);
    }

    public function testRateLimitEnforcement(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'test-key-1';
        
        $middleware = new ApiAuthMiddleware($this->log, $this->tx);
        
        // Make 59 requests (just under the limit)
        for ($i = 0; $i < 59; $i++) {
            $result = $middleware->authenticate();
            $this->assertTrue($result['authenticated'], "Request {$i} should succeed");
        }
        
        // 60th request should succeed (exactly at limit)
        $result = $middleware->authenticate();
        $this->assertTrue($result['authenticated'], "Request 60 should succeed (at limit)");
        
        // 61st request should fail (over limit)
        $result = $middleware->authenticate();
        $this->assertFalse($result['authenticated'], "Request 61 should fail (over limit)");
        $this->assertEquals(429, $result['code']);
        $this->assertStringContainsString('Rate limit exceeded', $result['error']);
    }
}
