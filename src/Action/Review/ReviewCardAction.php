<?php

namespace App\Action\Review;

use App\Domain\Card\Service\CardReviewService;
use App\Renderer\JsonRenderer;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ReviewCardAction
{
    private CardReviewService $service;
    private JsonRenderer $renderer;

    public function __construct(CardReviewService $service, JsonRenderer $renderer)
    {
        $this->service = $service;
        $this->renderer = $renderer;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $body = (array)$request->getParsedBody();

        if (empty($body)) {
            return $this->renderer
                ->json($response, ['error' => ['message' => 'Request body is required']])
                ->withStatus(StatusCodeInterface::STATUS_BAD_REQUEST);
        }

        $result = $this->service->create($body);

        return $this->renderer
            ->json($response, $result)
            ->withStatus(StatusCodeInterface::STATUS_CREATED);
    }
}
