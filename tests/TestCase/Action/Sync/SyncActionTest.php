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

    public function testSetsWithStoredIconUrlExposeIt(): void
    {
        // Issue #196: when a set has icon_url stored in DB (migrate.php ran
        // successfully for this uid), it MUST be sent. Clients use it to
        // fetch /icons/{uid}.svg instead of parsing base64.
        $request = $this->createRequest('GET', '/v6/sync?since=0')
            ->withHeader('Authorization', $this->authHeader())
            ->withHeader('Accept', 'application/json');
        $response = $this->app->handle($request);

        $data = $this->getJsonData($response);
        $sets = $data['updates']['sets'];

        $this->assertNotEmpty($sets, 'Expected at least one set in sync response');

        $anyWithUrl = false;
        foreach ($sets as $set) {
            if (!isset($set['icon_url']) || $set['icon_url'] === null || $set['icon_url'] === '') {
                // Set without icon_url — client will fall back to base64 `icon`.
                // This is expected for sets whose base64 icon could not be
                // extracted (e.g. invalid SVG). Verify base64 is still there.
                $this->assertArrayHasKey('icon', $set);
                continue;
            }

            $anyWithUrl = true;
            $this->assertIsString($set['icon_url']);
            $this->assertMatchesRegularExpression(
                '#^https?://[^/]+/icons/\d+\.svg$#',
                $set['icon_url'],
                "Set {$set['uid']} icon_url must be a valid /icons/{uid}.svg URL"
            );
            $this->assertStringEndsWith(
                '/icons/' . $set['uid'] . '.svg',
                $set['icon_url'],
                "Set {$set['uid']} icon_url must reference its own uid"
            );
        }

        $this->assertTrue(
            $anyWithUrl,
            'At least one set must have icon_url set (did migrate.php/extract-icons.php run?)'
        );
    }

    public function testSyncResponseDoesNotFabricateIconUrlFromUid(): void
    {
        // Regression: earlier implementation generated icon_url from uid even
        // if no SVG file existed, producing 404s. icon_url must come from the
        // DB column only — set by the extraction script once it actually wrote
        // the file.
        //
        // This is enforced indirectly: if icon_url is present, a corresponding
        // SVG file MUST exist on disk under public/icons/{uid}.svg.
        $request = $this->createRequest('GET', '/v6/sync?since=0')
            ->withHeader('Authorization', $this->authHeader())
            ->withHeader('Accept', 'application/json');
        $response = $this->app->handle($request);

        $data = $this->getJsonData($response);
        $sets = $data['updates']['sets'];
        $iconsDir = dirname(__DIR__, 4) . '/public/icons';

        foreach ($sets as $set) {
            if (empty($set['icon_url'])) {
                continue;
            }
            $expectedFile = $iconsDir . '/' . $set['uid'] . '.svg';
            $this->assertFileExists(
                $expectedFile,
                "Set {$set['uid']} advertises icon_url '{$set['icon_url']}' " .
                "but public/icons/{$set['uid']}.svg does not exist — would 404."
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
