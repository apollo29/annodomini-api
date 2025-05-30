<?php

namespace App\Action\Update;

use App\Domain\Skills\Service\SkillsFinder;
use App\Domain\Update\Service\UpdateFinder;
use App\Domain\Update\Service\UpdateFinderService;
use App\Renderer\JsonRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Action.
 */
final class UpdateByDateAction
{
    private UpdateFinderService $service;

    private JsonRenderer $renderer;

    /**
     * The constructor.
     *
     * @param UpdateFinderService $service The service
     * @param JsonRenderer $renderer The responder
     */
    public function __construct(UpdateFinderService $service, JsonRenderer $renderer)
    {
        $this->service = $service;
        $this->renderer = $renderer;
    }

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
        // Fetch parameters from the request
        $date = (int)$args['date'];

        // Invoke the domain (service class)
        $items = $this->service->findByDate($date);

        // Transform result
        return $this->transform($response, $items);
    }

    /**
     * Transform result to response.
     *
     * @param ResponseInterface $response The response
     * @param array $data The items
     *
     * @return ResponseInterface The response
     */
    private function transform(ResponseInterface $response, array $data): ResponseInterface
    {
        $list = [];

        foreach ($data as $item) {
            $list[] = (array)$item;
        }

        return $this->renderer->json($response, $list);
    }
}
