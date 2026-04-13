<?php

use App\Middleware\CorsMiddleware;
use App\Middleware\ExceptionMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use Selective\BasePath\BasePathMiddleware;
use Slim\App;

return function (App $app) {
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    $app->add(BasePathMiddleware::class);
    $app->add(SecurityHeadersMiddleware::class);
    $app->add(CorsMiddleware::class);
    $app->add(RateLimitMiddleware::class);
    $app->add(ExceptionMiddleware::class);
};
