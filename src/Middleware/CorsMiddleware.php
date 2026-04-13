<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;

    public function __construct(array $allowedOrigins = [])
    {
        $this->allowedOrigins = $allowedOrigins;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            $response = (new \Nyholm\Psr7\Response(204));

            return $this->addCorsHeaders($response, $origin);
        }

        $response = $handler->handle($request);

        return $this->addCorsHeaders($response, $origin);
    }

    private function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        $allowedOrigin = $this->resolveOrigin($origin);

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept')
            ->withHeader('Access-Control-Max-Age', '86400');
    }

    private function resolveOrigin(string $origin): string
    {
        if (empty($this->allowedOrigins) || in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }

        if (in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }

        return $this->allowedOrigins[0] ?? '*';
    }
}
