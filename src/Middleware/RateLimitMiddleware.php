<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;
    private string $storagePath;
    private int $maxRequests;
    private int $windowSeconds;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        string $storagePath,
        int $maxRequests = 60,
        int $windowSeconds = 60,
    ) {
        $this->responseFactory = $responseFactory;
        $this->storagePath = rtrim($storagePath, '/\\');
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $clientIp = $this->getClientIp($request);
        $key = md5($clientIp);
        $file = $this->storagePath . '/' . $key . '.json';

        $now = time();
        $data = $this->loadData($file, $now);

        // Clean expired entries
        $data['requests'] = array_filter(
            $data['requests'],
            fn(int $timestamp) => $timestamp > ($now - $this->windowSeconds)
        );

        if (count($data['requests']) >= $this->maxRequests) {
            $retryAfter = $this->windowSeconds - ($now - min($data['requests']));
            $response = $this->responseFactory->createResponse(429);
            $response->getBody()->write(json_encode([
                'error' => ['message' => 'Too many requests. Please try again later.'],
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string)max(1, $retryAfter))
                ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        $data['requests'][] = $now;
        $this->saveData($file, $data);

        $remaining = $this->maxRequests - count($data['requests']);
        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string)$remaining);
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();

        // Check forwarded headers (reverse proxy)
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded) {
            $ips = array_map('trim', explode(',', $forwarded));

            return $ips[0];
        }

        return $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function loadData(string $file, int $now): array
    {
        if (!file_exists($file)) {
            return ['requests' => []];
        }

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (!is_array($data) || !isset($data['requests'])) {
            return ['requests' => []];
        }

        return $data;
    }

    private function saveData(string $file, array $data): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
