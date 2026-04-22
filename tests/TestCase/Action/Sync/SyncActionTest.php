<?php

namespace App\Test\TestCase\Action\Sync;

use App\Test\Traits\AppTestTrait;
use Fig\Http\Message\StatusCodeInterface;
use PHPUnit\Framework\TestCase;

class SyncActionTest extends TestCase
{
    use AppTestTrait;

    private function authHeader(): string
    {
        $settings = $this->container->get('settings');

        return 'Bearer ' . $settings['apikey']['api_key'];
    }

    public function testSyncRequiresAuth(): void
    {
        $request = $this->createRequest('GET', '/v6/sync');
        $response = $this->app->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testSyncReturnsJsonStructure(): void
    {
        $request = $this->createRequest('GET', '/v6/sync?since=0')
            ->withHeader('Authorization', $this->authHeader())
            ->withHeader('Accept', 'application/json');
        $response = $this->app->handle($request);

        $this->assertSame(StatusCodeInterface::STATUS_OK, $response->getStatusCode());

        $data = $this->getJsonData($response);

        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('update_types', $data);
        $this->assertArrayHasKey('updates', $data);
        $this->assertArrayHasKey('removals', $data);
        $this->assertIsInt($data['timestamp']);
    }

    public function testSyncUpdatesContainsAllKeys(): void
    {
        $request = $this->createRequest('GET', '/v6/sync?since=0')
            ->withHeader('Authorization', $this->authHeader())
            ->withHeader('Accept', 'application/json');
        $response = $this->app->handle($request);

        $data = $this->getJsonData($response);
        $updates = $data['updates'];

        $this->assertArrayHasKey('sets', $updates);
        $this->assertArrayHasKey('cards', $updates);
        $this->assertArrayHasKey('opponents', $updates);
        $this->assertArrayHasKey('skills', $updates);
        $this->assertArrayHasKey('virtual_sets', $updates);
        $this->assertArrayHasKey('available_sets', $updates);
        $this->assertArrayHasKey('virtual_cards', $updates);
    }

    public function testSyncWithFutureDateReturnsEmptyUpdates(): void
    {
        $request = $this->createRequest('GET', '/v6/sync?since=99999999')
            ->withHeader('Authorization', $this->authHeader())
            ->withHeader('Accept', 'application/json');
        $response = $this->app->handle($request);

        $data = $this->getJsonData($response);

        $this->assertEmpty($data['updates']['sets']);
        $this->assertEmpty($data['updates']['cards']);
    }

    public function testSyncTimestampIsCurrentDate(): void
    {
        $request = $this->createRequest('GET', '/v6/sync?since=0')
            ->withHeader('Authorization', $this->authHeader())
            ->withHeader('Accept', 'application/json');
        $response = $this->app->handle($request);

        $data = $this->getJsonData($response);

        $this->assertSame((int)date('Ymd'), $data['timestamp']);
    }

    public function testEachSetInSyncResponseHasIconUrl(): void
    {
        // Issue #196: icon_url must be included for every set so clients can
        // fetch icons as separate static files instead of inline base64 blobs.
        $request = $this->createRequest('GET', '/v6/sync?since=0')
            ->withHeader('Authorization', $this->authHeader())
            ->withHeader('Accept', 'application/json');
        $response = $this->app->handle($request);

        $data = $this->getJsonData($response);
        $sets = $data['updates']['sets'];

        $this->assertNotEmpty($sets, 'Expected at least one set in sync response');

        foreach ($sets as $set) {
            $this->assertArrayHasKey('icon_url', $set, "Set {$set['uid']} must contain 'icon_url'");
            $this->assertIsString($set['icon_url'], "Set {$set['uid']} 'icon_url' must be a string");
            $this->assertMatchesRegularExpression(
                '#^https?://[^/]+/icons/\d+\.svg$#',
                $set['icon_url'],
                "Set {$set['uid']} 'icon_url' must match pattern https://host/icons/{uid}.svg"
            );
            $this->assertStringEndsWith(
                '/icons/' . $set['uid'] . '.svg',
                $set['icon_url'],
                "Set {$set['uid']} 'icon_url' must reference its own uid"
            );
        }
    }

    public function testSyncResponseKeepsIconBase64ForBackwardCompatibility(): void
    {
        // During the transition, existing clients still rely on the base64
        // `icon` field. It must remain present alongside icon_url.
        $request = $this->createRequest('GET', '/v6/sync?since=0')
            ->withHeader('Authorization', $this->authHeader())
            ->withHeader('Accept', 'application/json');
        $response = $this->app->handle($request);

        $data = $this->getJsonData($response);
        $sets = $data['updates']['sets'];

        $this->assertNotEmpty($sets);
        foreach ($sets as $set) {
            $this->assertArrayHasKey('icon', $set, "Set {$set['uid']} must keep legacy 'icon' field");
        }
    }
}
