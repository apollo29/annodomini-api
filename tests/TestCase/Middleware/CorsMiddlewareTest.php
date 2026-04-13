<?php

namespace App\Test\TestCase\Middleware;

use App\Test\Traits\AppTestTrait;
use PHPUnit\Framework\TestCase;

class CorsMiddlewareTest extends TestCase
{
    use AppTestTrait;

    public function testCorsHeadersWithWildcardConfig(): void
    {
        $request = $this->createRequest('GET', '/')
            ->withHeader('Origin', 'https://example.com');
        $response = $this->app->handle($request);

        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertNotEmpty($response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertNotEmpty($response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    public function testCorsHeadersWithoutOrigin(): void
    {
        $request = $this->createRequest('GET', '/');
        $response = $this->app->handle($request);

        // Wildcard config always sends headers
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testOptionsPreflightReturns204(): void
    {
        $request = $this->createRequest('OPTIONS', '/v6/sync')
            ->withHeader('Origin', 'https://example.com');
        $response = $this->app->handle($request);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testCorsMaxAgeIsSet(): void
    {
        $request = $this->createRequest('GET', '/')
            ->withHeader('Origin', 'https://example.com');
        $response = $this->app->handle($request);

        $this->assertSame('86400', $response->getHeaderLine('Access-Control-Max-Age'));
    }
}
