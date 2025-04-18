<?php

namespace App\Middleware;

use App\Support\JwtAuth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tuupola\Http\Factory\ResponseFactory;

/**
 * JWT Middleware
 */
class JwtMiddleware implements MiddlewareInterface
{
    protected JwtAuth $jwtAuth;

    /**
     * The constructor.
     *
     * @param JwtAuth $jwtAuth JWT Auth Service
     */
    public function __construct(JwtAuth $jwtAuth)
    {
        $this->jwtAuth = $jwtAuth;
    }

    /**
     * @param ServerRequestInterface $request Server Request
     * @param RequestHandlerInterface $handler Request Handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = explode(' ', $request->getHeaderLine('Authorization'));
        $token = $authorization[1] ?? '';

        if (!$token || !$this->jwtAuth->validateToken($token)) {
            return (new ResponseFactory())
                ->createResponse(401)
                ->withHeader(
                    "Unauthorized",
                    "Unauthorized"
                );
        }

        // Append valid token
        $parsedToken = $this->jwtAuth->createParsedToken($token);
        $request = $request->withAttribute('token', $parsedToken);

        // Append the user id as request attribute
        $request = $request->withAttribute('user', $parsedToken->claims()->get(JwtAuth::UID));

        return $handler->handle($request);
    }
}