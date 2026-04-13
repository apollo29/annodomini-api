<?php

namespace App\Action\Game;

use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GameDeleteAction extends GameAction
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
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // Support player_id from query params (legacy) or JSON body (v6)
        $queryParams = $request->getQueryParams();
        $body = (array)$request->getParsedBody();
        $player_id = $queryParams['player_id'] ?? $body['player_id'] ?? null;

        if ($player_id !== null) {
            $game_id = (int)$args['game_id'];
            $result = $this->service->delete($game_id, (string)$player_id);
            if ($result) {
                return $this->renderer->json($response);
            }
        }

        return $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND, "Game not found");
    }
}
