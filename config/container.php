<?php

use App\Middleware\ApiKeyMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\ExceptionMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Renderer\JsonRenderer;
use App\Support\ApiKeyAuth;
use App\Domain\Sync\Service\SyncService;
use App\Domain\Update\Service\UpdateFinderService;
use App\Domain\Set\Service\SetFinderService;
use App\Domain\Card\Service\CardFinderService;
use App\Domain\Opponent\Service\OpponentFinderService;
use App\Domain\Skills\Service\SkillsFinderService;
use App\Domain\VirtualSet\Service\VirtualSetFinderService;
use App\Domain\AvailableSet\Service\AvailableSetFinderService;
use App\Domain\VirtualCard\Service\VirtualCardFinderService;
use App\Domain\Remove\Service\RemovalFinderService;
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

    ApiKeyAuth::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['apikey'];
        return new ApiKeyAuth($settings['api_key']);
    },

    ApiKeyMiddleware::class => function (ContainerInterface $container) {
        $apiKeyAuth = $container->get(ApiKeyAuth::class);
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        return new ApiKeyMiddleware($apiKeyAuth, $responseFactory);
    },

    // New Middleware

    CorsMiddleware::class => function (ContainerInterface $container) {
        $settings = $container->get('settings');
        $allowedOrigins = $settings['cors']['allowed_origins'] ?? ['*'];

        return new CorsMiddleware($allowedOrigins);
    },

    SecurityHeadersMiddleware::class => function () {
        return new SecurityHeadersMiddleware();
    },

    RateLimitMiddleware::class => function (ContainerInterface $container) {
        $settings = $container->get('settings');
        $storagePath = $settings['rate_limit']['storage_path']
            ?? __DIR__ . '/../tmp/rate_limit';
        $maxRequests = $settings['rate_limit']['max_requests'] ?? 60;
        $windowSeconds = $settings['rate_limit']['window_seconds'] ?? 60;

        return new RateLimitMiddleware(
            $container->get(ResponseFactoryInterface::class),
            $storagePath,
            $maxRequests,
            $windowSeconds,
        );
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

        return $method->invoke($driver);
    },

    LoggerInterface::class => function (ContainerInterface $container) {
        $settings = $container->get('settings')['logger'];
        $logger = new Logger('app');

        $filename = sprintf('%s/app.log', $settings['path']);
        $level = $settings['level'];
        $rotatingFileHandler = new RotatingFileHandler($filename, 0, $level, true, 0640);
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

    // Issue #196: Sync service — iconsDir let us verify the SVG exists before
    // emitting a URL (otherwise clients get 404s).
    SyncService::class => function (ContainerInterface $container) {
        $settings = $container->get('settings');
        $iconPathPrefix = (string)($settings['icon_path_prefix'] ?? 'icons');

        return new SyncService(
            $container->get(UpdateFinderService::class),
            $container->get(SetFinderService::class),
            $container->get(CardFinderService::class),
            $container->get(OpponentFinderService::class),
            $container->get(SkillsFinderService::class),
            $container->get(VirtualSetFinderService::class),
            $container->get(AvailableSetFinderService::class),
            $container->get(VirtualCardFinderService::class),
            $container->get(RemovalFinderService::class),
            // Resolve icons dir relative to public/
            realpath(__DIR__ . '/../public/icons') ?: (__DIR__ . '/../public/icons'),
            $iconPathPrefix,
        );
    },

    \App\Action\Sync\SyncAction::class => function (ContainerInterface $container) {
        // icon_base_url from config is optional: empty means "use whatever
        // host the request came in on" (handled inside SyncAction).
        $configured = (string)($container->get('settings')['icon_base_url'] ?? '');

        return new \App\Action\Sync\SyncAction(
            $container->get(SyncService::class),
            $container->get(JsonRenderer::class),
            $configured,
        );
    },
];
