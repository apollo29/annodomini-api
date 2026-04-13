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

    public function __construct(SyncService $service, JsonRenderer $renderer)
    {
        $this->service = $service;
        $this->renderer = $renderer;
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

        $data = $this->service->sync($since, $only);

        return $this->renderer->json($response, $data);
    }
}
