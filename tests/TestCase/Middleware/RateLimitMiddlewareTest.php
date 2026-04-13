<?php

namespace App\Test\TestCase\Middleware;

use App\Middleware\RateLimitMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RateLimitMiddlewareTest extends TestCase
{
    private string $storagePath;
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/rate_limit_test_' . uniqid();
        mkdir($this->storagePath, 0755, true);
        $this->factory = new Psr17Factory();
    }

    protected function tearDown(): void
    {
        // Clean up rate limit files
        $files = glob($this->storagePath . '/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
        if (is_dir($this->storagePath)) {
            rmdir($this->storagePath);
        }
    }

    private function createMiddleware(int $maxRequests = 5, int $windowSeconds = 60): RateLimitMiddleware
    {
        return new RateLimitMiddleware($this->factory, $this->storagePath, $maxRequests, $windowSeconds);
    }

    private function createRequest(): ServerRequestInterface
    {
        return $this->factory->createServerRequest('GET', '/', ['REMOTE_ADDR' => '192.168.1.1']);
    }

    private function createHandler(): RequestHandlerInterface
    {
        return new class ($this->factory) implements RequestHandlerInterface {
            private Psr17Factory $factory;

            public function __construct(Psr17Factory $factory)
            {
                $this->factory = $factory;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = $this->factory->createResponse(200);
                $response->getBody()->write('OK');

                return $response;
            }
        };
    }

    public function testRequestsWithinLimitPass(): void
    {
        $middleware = $this->createMiddleware(5);
        $request = $this->createRequest();
        $handler = $this->createHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('5', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame('4', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testRateLimitRemainingDecreases(): void
    {
        $middleware = $this->createMiddleware(5);
        $request = $this->createRequest();
        $handler = $this->createHandler();

        $middleware->process($request, $handler);
        $middleware->process($request, $handler);
        $response = $middleware->process($request, $handler);

        $this->assertSame('2', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function testRequestsExceedingLimitReturn429(): void
    {
        $middleware = $this->createMiddleware(3);
        $request = $this->createRequest();
        $handler = $this->createHandler();

        // Use up all allowed requests
        $middleware->process($request, $handler);
        $middleware->process($request, $handler);
        $middleware->process($request, $handler);

        // This one should be blocked
        $response = $middleware->process($request, $handler);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('0', $response->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertNotEmpty($response->getHeaderLine('Retry-After'));
    }

    public function testDifferentIpsHaveSeparateLimits(): void
    {
        $middleware = $this->createMiddleware(1);
        $handler = $this->createHandler();

        $request1 = $this->factory->createServerRequest('GET', '/', ['REMOTE_ADDR' => '10.0.0.1']);
        $request2 = $this->factory->createServerRequest('GET', '/', ['REMOTE_ADDR' => '10.0.0.2']);

        $response1 = $middleware->process($request1, $handler);
        $response2 = $middleware->process($request2, $handler);

        // Both should pass since they are from different IPs
        $this->assertSame(200, $response1->getStatusCode());
        $this->assertSame(200, $response2->getStatusCode());
    }
}
