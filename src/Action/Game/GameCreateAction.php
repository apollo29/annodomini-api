<?php

namespace App\Action\Game;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GameCreateAction extends GameAction
{
    /**
     * Action.
     *
     * @param ServerRequestInterface $request The request
     * @param ResponseInterface $response The response
     * @param array $args The routing arguments
     *
     * @return ResponseInterface The response
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface
    {
        // v6: player_id from JSON body; legacy: from route args
        if (array_key_exists('player_id', $args)) {
            $player_id = (string)$args['player_id'];
        } else {
            $body = (array)$request->getParsedBody();
            $player_id = (string)($body['player_id'] ?? '');
        }

        if (empty($player_id)) {
            return $this->renderer
                ->json($response, ['error' => ['message' => 'player_id is required']])
                ->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        $id = $this->service->create($player_id);

        return $this->renderer
            ->json($response, $id)
            ->withStatus(StatusCodeInterface::STATUS_CREATED);
    }
}
