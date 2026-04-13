<?php

namespace App\Test\TestCase\Middleware;

use App\Test\Traits\AppTestTrait;
use PHPUnit\Framework\TestCase;

class SecurityHeadersMiddlewareTest extends TestCase
{
    use AppTestTrait;

    public function testSecurityHeadersArePresent(): void
    {
        $request = $this->createRequest('GET', '/');
        $response = $this->app->handle($request);

        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame('1; mode=block', $response->getHeaderLine('X-XSS-Protection'));
        $this->assertStringContainsString('max-age=', $response->getHeaderLine('Strict-Transport-Security'));
        $this->assertSame("default-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
        $this->assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
    }
}
