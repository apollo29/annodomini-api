<?php

// Define app routes

use App\Action\AvailableSet\AvailableSetFindAction;
use App\Action\Card\CardFindAction;
use App\Action\Game\GameCreateAction;
use App\Action\Game\GameDeleteAction;
use App\Action\Game\GameFindAction;
use App\Action\Home\HomeAction;
use App\Action\Opponent\OpponentFindAction;
use App\Action\Remove\RemovalsByDateAction;
use App\Action\Review\ReviewCardAction;
use App\Action\Set\SetFindAction;
use App\Action\Skills\SkillsFindAction;
use App\Action\Sync\SyncAction;
use App\Action\Update\UpdateByDateAction;
use App\Action\VirtualCard\VirtualCardFindAction;
use App\Action\VirtualSet\VirtualSetFindAction;
use App\Middleware\ApiKeyMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->get('/', HomeAction::class);

    // =========================================================================
    // v6 API — consolidated and simplified
    // =========================================================================

    $app->group(
        '/v6',
        function (RouteCollectorProxy $app) {

            // Consolidated sync endpoint — replaces all /update/* and /removal/*
            $app->get('/sync', SyncAction::class);

            // Game endpoints (POST for create, DELETE with body)
            // Static routes must come before variable routes
            $app->post('/game', GameCreateAction::class);
            $app->get('/game/expired', GameFindAction::class);
            $app->get('/game/{game_id}', GameFindAction::class);
            $app->delete('/game/{game_id}', GameDeleteAction::class);

            // Review
            $app->post('/review/cards', ReviewCardAction::class);
        }
    )->add(ApiKeyMiddleware::class);

    // =========================================================================
    // Legacy v5 routes — keep for backwards compatibility during migration
    // =========================================================================

    // UPDATE
    $app->group(
        '/update',
        function (RouteCollectorProxy $app) {
            $app->get('/{date}', UpdateByDateAction::class);
            $app->get('/sets/{date}', SetFindAction::class);
            $app->get('/cards/{date}', CardFindAction::class);
            $app->get('/opponents/{date}', OpponentFindAction::class);
            $app->get('/skills/{date}', SkillsFindAction::class);
            $app->get('/virtual_sets/{date}', VirtualSetFindAction::class);
            $app->get('/available_sets/{date}', AvailableSetFindAction::class);
            $app->get('/virtual_cards/{date}', VirtualCardFindAction::class);
            $app->get('/playing_cards/{date}', CardFindAction::class);
        }
    )->add(ApiKeyMiddleware::class);

    // REMOVE
    $app->group(
        '/removal',
        function (RouteCollectorProxy $app) {
            $app->get('/{date}', RemovalsByDateAction::class);
        }
    )->add(ApiKeyMiddleware::class);

    // REVIEW
    $app->group(
        '/review',
        function (RouteCollectorProxy $app) {
            $app->put('/cards', ReviewCardAction::class);
        }
    )->add(ApiKeyMiddleware::class);

    // GAMES
    $app->group(
        '/game',
        function (RouteCollectorProxy $app) {
            $app->put('/{player_id}', GameCreateAction::class);
            $app->get('/verify/{game_id}', GameFindAction::class);
            $app->get('/expired', GameFindAction::class);
            $app->delete('/{game_id}', GameDeleteAction::class);
        }
    )->add(ApiKeyMiddleware::class);
};
