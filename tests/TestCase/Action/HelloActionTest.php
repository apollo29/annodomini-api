<?php

namespace App\Test\TestCase\Action;

use App\Test\TestCase\HttpTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Test.
 */
class HelloActionTest extends TestCase
{
    use HttpTestTrait;

    /**
     * Test.
     *
     * @return void
     */
    public function testHelloAction(): void
    {
        $request = $this->createRequest('GET', '/hello/john');
        $response = $this->request($request);

        static::assertSame(200, $response->getStatusCode());
        static::assertStringContainsString('Hello, john', (string)$response->getBody());
    }
}
