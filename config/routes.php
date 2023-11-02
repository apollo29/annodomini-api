<?php

// Define app routes

use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->get('/', \App\Action\Home\HomeAction::class)->setName('home');

    // API
    $app->group(
        '/api',
        function (RouteCollectorProxy $app) {
            $app->get('/customers', \App\Action\Customer\CustomerFinderAction::class);
            $app->post('/customers', \App\Action\Customer\CustomerCreatorAction::class);
            $app->get('/customers/{customer_id}', \App\Action\Customer\CustomerReaderAction::class);
            $app->put('/customers/{customer_id}', \App\Action\Customer\CustomerUpdaterAction::class);
            $app->delete('/customers/{customer_id}', \App\Action\Customer\CustomerDeleterAction::class);
        }
    );

    // SETS
    $app->group(
        '/sets',
        function (RouteCollectorProxy $app) {
            $app->get('', \App\Action\Set\SetFindAction::class);
            $app->get('/{id}', \App\Action\Set\SetReadAction::class);
            $app->post('', \App\Action\Set\SetCreateAction::class);
            $app->put('/{id}', \App\Action\Set\SetUpdateAction::class);
            $app->delete('/{uid}', \App\Action\Set\SetDeleteAction::class);
        }
    )->add(HttpBasicAuthentication::class);
};
