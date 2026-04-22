<?php

namespace App\Action\Sync;

use App\Domain\Sync\Service\SyncService;
use App\Renderer\JsonRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SyncAction
{
    private SyncService $service;
    private JsonRenderer $renderer;
    private string $configuredIconBaseUrl;

    public function __construct(SyncService $service, JsonRenderer $renderer, string $configuredIconBaseUrl = '')
    {
        $this->service = $service;
        $this->renderer = $renderer;
        $this->configuredIconBaseUrl = $configuredIconBaseUrl;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $queryParams = $request->getQueryParams();

        $since = (int)($queryParams['since'] ?? 0);
        $only = [];

        if (!empty($queryParams['only'])) {
            $only = array_map('trim', explode(',', $queryParams['only']));
        }

        // Resolve icon base URL: explicit config (CDN etc.) wins over
        // auto-detected request URL. Without config, use whatever host
        // the client is talking to — so dev, staging, prod each serve
        // their own icons.
        $iconBaseUrl = $this->configuredIconBaseUrl !== ''
            ? $this->configuredIconBaseUrl
            : $this->iconBaseUrlFromRequest($request);

        $data = $this->service->sync($since, $only, $iconBaseUrl);

        return $this->renderer->json($response, $data);
    }

    private function iconBaseUrlFromRequest(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $host = $uri->getHost();

        // No host in the URI (test requests, some edge cases) -> fall back
        // to the production URL. This keeps icons at a known-good location
        // even when we cannot derive the host from the request.
        if ($host === '') {
            return 'https://api.annodomini.app';
        }

        $scheme = $uri->getScheme() ?: 'https';
        $port = $uri->getPort();

        $base = $scheme . '://' . $host;
        if ($port !== null && !in_array($port, [80, 443], true)) {
            $base .= ':' . $port;
        }

        // Detect a base path (e.g. /annodomini-api when the app lives in a
        // subdirectory). Derived from SCRIPT_NAME the same way
        // selective/basepath does it.
        $basePath = $this->detectBasePath($request->getServerParams());

        return rtrim($base . $basePath, '/');
    }

    private function detectBasePath(array $server): string
    {
        if (empty($server['SCRIPT_NAME']) || empty($server['REQUEST_URI'])) {
            return '';
        }

        // SCRIPT_NAME is e.g. /annodomini-api/public/index.php — strip the
        // file and the "public" folder to get the app-root prefix.
        $scriptDir = str_replace('\\', '/', dirname((string)$server['SCRIPT_NAME'], 2));
        if ($scriptDir === '' || $scriptDir === '/' || $scriptDir === '.') {
            return '';
        }

        // Sanity-check against REQUEST_URI so we only return a prefix that
        // actually precedes the request path.
        $requestPath = (string)parse_url((string)$server['REQUEST_URI'], PHP_URL_PATH);
        if (!str_starts_with($requestPath, $scriptDir)) {
            return '';
        }

        return $scriptDir;
    }
}
