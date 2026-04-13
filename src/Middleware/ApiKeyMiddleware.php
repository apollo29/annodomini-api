<?php

namespace App\Middleware;

use App\Support\ApiKeyAuth;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ApiKeyMiddleware implements MiddlewareInterface
{
    private ApiKeyAuth $apiKeyAuth;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(ApiKeyAuth $apiKeyAuth, ResponseFactoryInterface $responseFactory)
    {
        $this->apiKeyAuth = $apiKeyAuth;
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = explode(' ', $request->getHeaderLine('Authorization'));
        $apikey = $authorization[1] ?? '';

        if (!$apikey || !$this->apiKeyAuth->validate($apikey)) {
            $response = $this->responseFactory->createResponse(401);
            $response->getBody()->write(json_encode([
                'error' => ['message' => 'Unauthorized'],
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
