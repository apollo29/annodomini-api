<?php

namespace App\Test\TestCase\Action\Login;

use App\Domain\User\Data\UserAuthData;
use App\Test\DatabaseTestTrait;
use App\Test\Fixture\UserFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Test.
 */
class LoginSubmitActionTest extends TestCase
{
    use DatabaseTestTrait;

    /**
     * Test.
     *
     * @return void
     */
    public function testLoginAdminAction(): void
    {
        $this->insertFixtures([
            UserFixture::class,
        ]);

        $request = $this->createJsonRequest('POST', '/login', ['username' => 'admin', 'password' => 'admin']);
        $response = $this->app->handle($request);

        static::assertSame(302, $response->getStatusCode());
        static::assertSame('/users', $response->getHeaderLine('Location'));
        static::assertEquals('', (string)$response->getBody());

        // User session
        $session = $this->container->get(Session::class);

        /** @var UserAuthData $user */
        $user = $session->get('user');
        static::assertInstanceOf(UserAuthData::class, $user);
        static::assertSame(1, $user->id);
        static::assertSame('en_US', $user->locale);
        static::assertSame('admin@example.com', $user->email);
    }

    /**
     * Test.
     *
     * @return void
     */
    public function testLoginUserAction(): void
    {
        $this->insertFixtures([
            UserFixture::class,
        ]);

        $request = $this->createJsonRequest('POST', '/login', ['username' => 'user', 'password' => 'user']);
        $response = $this->app->handle($request);

        static::assertSame(302, $response->getStatusCode());
        static::assertSame('/users', $response->getHeaderLine('Location'));
        static::assertEquals('', (string)$response->getBody());

        // User session
        $session = $this->container->get(Session::class);

        /** @var UserAuthData $user */
        $user = $session->get('user');
        static::assertInstanceOf(UserAuthData::class, $user);
        static::assertSame(2, $user->id);
        static::assertSame('de_DE', $user->locale);
        static::assertSame('user@example.com', $user->email);
    }
}
