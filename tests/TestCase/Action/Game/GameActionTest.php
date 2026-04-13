<?php

namespace App\Test\TestCase\Action\Game;

use App\Test\Traits\AppTestTrait;
use Fig\Http\Message\StatusCodeInterface;
use PHPUnit\Framework\TestCase;

class GameActionTest extends TestCase
{
    use AppTestTrait;

    private function authHeader(): string
    {
        $settings = $this->container->get('settings');

        return 'Bearer ' . $settings['apikey']['api_key'];
    }

    public function testCreateGameRequiresAuth(): void
    {
        $request = $this->createJsonRequest('POST', '/v6/game', ['player_id' => 'test']);
        $response = $this->app->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testCreateGameRequiresPlayerId(): void
    {
        $request = $this->createJsonRequest('POST', '/v6/game', [])
            ->withHeader('Authorization', $this->authHeader());
        $response = $this->app->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_BAD_REQUEST, $response->getStatusCode());
    }

    public function testDeleteGameRequiresAuth(): void
    {
        $request = $this->createRequest('DELETE', '/v6/game/1234');
        $response = $this->app->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetExpiredGamesRequiresAuth(): void
    {
        $request = $this->createRequest('GET', '/v6/game/expired');
        $response = $this->app->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }
}
