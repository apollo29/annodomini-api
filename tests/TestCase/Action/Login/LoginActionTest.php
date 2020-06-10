<?php

namespace App\Test\TestCase\Action\Login;

use App\Test\AppTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Test.
 */
class LoginActionTest extends TestCase
{
    use AppTestTrait;

    /**
     * Test.
     *
     * @return void
     */
    public function testLoginAction(): void
    {
        $request = $this->createRequest('GET', '/login');
        $response = $this->app->handle($request);

        static::assertSame(200, $response->getStatusCode());

        $body = (string)$response->getBody();
        static::assertStringContainsString('<input type="text" name="username"', $body);
        static::assertStringContainsString('<input type="password" name="password"', $body);
        static::assertStringContainsString('<button id="btn_login"', $body);
    }
}
