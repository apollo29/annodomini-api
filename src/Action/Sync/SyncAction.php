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

        // BasePathMiddleware stores any sub-path prefix on the request.
        // We respect it so that e.g. https://host.tld/annodomini-api/icons/...
        // works when the app lives in a subdirectory.
        $basePath = (string)$request->getAttribute('basePath', '');

        return rtrim($base . $basePath, '/');
    }
}
