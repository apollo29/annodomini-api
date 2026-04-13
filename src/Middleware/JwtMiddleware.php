<?php

namespace App\Middleware;

use App\Support\JwtAuth;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class JwtMiddleware implements MiddlewareInterface
{
    private JwtAuth $jwtAuth;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(JwtAuth $jwtAuth, ResponseFactoryInterface $responseFactory)
    {
        $this->jwtAuth = $jwtAuth;
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = explode(' ', $request->getHeaderLine('Authorization'));
        $token = $authorization[1] ?? '';

        if (!$token || !$this->jwtAuth->validateToken($token)) {
            $response = $this->responseFactory->createResponse(401);
            $response->getBody()->write(json_encode([
                'error' => ['message' => 'Unauthorized'],
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        // Append valid token
        $parsedToken = $this->jwtAuth->createParsedToken($token);
        $request = $request->withAttribute('token', $parsedToken);

        // Append the user id as request attribute
        $request = $request->withAttribute('user', $parsedToken->claims()->get(JwtAuth::UID));

        return $handler->handle($request);
    }
}
