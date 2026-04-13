<?php

namespace App\Test\TestCase\Middleware;

use App\Test\Traits\AppTestTrait;
use Fig\Http\Message\StatusCodeInterface;
use PHPUnit\Framework\TestCase;

class ApiKeyMiddlewareTest extends TestCase
{
    use AppTestTrait;

    private function authHeader(): string
    {
        $settings = $this->container->get('settings');

        return 'Bearer ' . $settings['apikey']['api_key'];
    }

    public function testMissingAuthHeaderReturns401(): void
    {
        $request = $this->createRequest('GET', '/v6/sync');
        $response = $this->app->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testInvalidApiKeyReturns401(): void
    {
        $request = $this->createRequest('GET', '/v6/sync')
            ->withHeader('Authorization', 'Bearer invalid-key');
        $response = $this->app->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testValidApiKeyPassesThrough(): void
    {
        $request = $this->createRequest('GET', '/v6/sync?since=99999999')
            ->withHeader('Authorization', $this->authHeader());
        $response = $this->app->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());
    }

    public function testRawKeyWithoutBearerPrefixReturns401(): void
    {
        $settings = $this->container->get('settings');
        $rawKey = $settings['apikey']['api_key'];

        $request = $this->createRequest('GET', '/v6/sync')
            ->withHeader('Authorization', $rawKey);
        $response = $this->app->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }
}
