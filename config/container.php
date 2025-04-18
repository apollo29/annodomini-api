<?php

use App\Handler\DefaultErrorHandler;
use App\Middleware\AclMiddleware;
use App\Middleware\ApiKeyMiddleware;
use App\Middleware\ExceptionMiddleware;
use App\Middleware\JwtMiddleware;
use App\Renderer\JsonRenderer;
use App\Support\ApiKeyAuth;
use App\Support\JwtAuth;
use App\Support\PDOAuth;
use Cake\Database\Connection;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Selective\BasePath\BasePathMiddleware;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteParserInterface;
use Tuupola\Middleware\HttpBasicAuthentication;

return [
    // Application settings
    'settings' => fn() => require __DIR__ . '/settings.php',

    App::class => function (ContainerInterface $container) {
        $app = AppFactory::createFromContainer($container);

        // Register routes
        (require __DIR__ . '/routes.php')($app);

        // Register middleware
        (require __DIR__ . '/middleware.php')($app);

        return $app;
    },

    // Auth

    AclMiddleware::class => function (ContainerInterface $container) {
        $connection = $container->get(Connection::class);
        $logger = $container->get(LoggerInterface::class);

        return new AclMiddleware($connection, $logger);
    },

    PDOAuth::class => function (ContainerInterface $container) {
        $connection = $container->get(Connection::class);
        $logger = $container->get(LoggerInterface::class);

        return new PDOAuth($connection, $logger);
    },

    JwtAuth::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['jwt_auth'];

        return new JwtAuth(
            (string)$settings['issuer'],
            (int)$settings['lifetime'],
            (string)$settings['private_key'],
            (string)$settings['public_key']
        );
    },

    JwtMiddleware::class => function (ContainerInterface $container) {
        $jwtAuth = $container->get(JwtAuth::class);

        return new JwtMiddleware($jwtAuth);
    },

    ApiKeyAuth::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['apikey'];
        return new ApiKeyAuth($settings['api_key']);
    },

    ApiKeyMiddleware::class => function (ContainerInterface $container) {
        $apiKeyAuth = $container->get(ApiKeyAuth::class);

        return new ApiKeyMiddleware($apiKeyAuth);
    },

    HttpBasicAuthentication::class => function (ContainerInterface $container) {
        $pdoAuth = $container->get(PDOAuth::class);

        return new HttpBasicAuthentication([
            "secure" => true,
            "relaxed" => ["localhost"],
            "realm" => "Protected",
            "authenticator" => $pdoAuth,
            "before" => function ($request, $arguments) {
                return $request->withAttribute("user", $arguments["user"]);
            }]);
    },

    // HTTP factories
    ResponseFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },

    ServerRequestFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },

    StreamFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },

    UploadedFileFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },

    UriFactoryInterface::class => function (ContainerInterface $container) {
        return $container->get(Psr17Factory::class);
    },

    // The Slim RouterParser
    RouteParserInterface::class => function (ContainerInterface $container) {
        return $container->get(App::class)->getRouteCollector()->getRouteParser();
    },

    BasePathMiddleware::class => function (ContainerInterface $container) {
        return new BasePathMiddleware($container->get(App::class));
    },

    // Database connection
    Connection::class => function (ContainerInterface $container) {
        return new Connection($container->get('settings')['db']);
    },

    PDO::class => function (ContainerInterface $container) {
        $driver = $container->get(Connection::class)->getDriver();

        $class = new ReflectionClass($driver);
        $method = $class->getMethod('getPdo');
        //$method->setAccessible(true);

        return $method->invoke($driver);
    },

    LoggerInterface::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['logger'];
        $logger = new Logger('app');

        $filename = sprintf('%s/app.log', $settings['path']);
        $level = $settings['level'];
        $rotatingFileHandler = new RotatingFileHandler($filename, 0, $level, true, 0777);
        $rotatingFileHandler->setFormatter(new LineFormatter(null, null, false, true));
        $logger->pushHandler($rotatingFileHandler);

        return $logger;
    },

    ExceptionMiddleware::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['error'];

        return new ExceptionMiddleware(
            $container->get(ResponseFactoryInterface::class),
            $container->get(JsonRenderer::class),
            $container->get(LoggerInterface::class),
            (bool)$settings['display_error_details'],
        );
    },
];
