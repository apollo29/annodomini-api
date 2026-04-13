<?php

namespace App\Domain\Sync\Service;

use App\Domain\AvailableSet\Service\AvailableSetFinderService;
use App\Domain\Card\Service\CardFinderService;
use App\Domain\Opponent\Service\OpponentFinderService;
use App\Domain\Remove\Service\RemovalFinderService;
use App\Domain\Set\Service\SetFinderService;
use App\Domain\Skills\Service\SkillsFinderService;
use App\Domain\Update\Service\UpdateFinderService;
use App\Domain\VirtualCard\Service\VirtualCardFinderService;
use App\Domain\VirtualSet\Service\VirtualSetFinderService;

final class SyncService
{
    private UpdateFinderService $updateService;
    private SetFinderService $setService;
    private CardFinderService $cardService;
    private OpponentFinderService $opponentService;
    private SkillsFinderService $skillsService;
    private VirtualSetFinderService $virtualSetService;
    private AvailableSetFinderService $availableSetService;
    private VirtualCardFinderService $virtualCardService;
    private RemovalFinderService $removalService;

    public function __construct(
        UpdateFinderService $updateService,
        SetFinderService $setService,
        CardFinderService $cardService,
        OpponentFinderService $opponentService,
        SkillsFinderService $skillsService,
        VirtualSetFinderService $virtualSetService,
        AvailableSetFinderService $availableSetService,
        VirtualCardFinderService $virtualCardService,
        RemovalFinderService $removalService,
    ) {
        $this->updateService = $updateService;
        $this->setService = $setService;
        $this->cardService = $cardService;
        $this->opponentService = $opponentService;
        $this->skillsService = $skillsService;
        $this->virtualSetService = $virtualSetService;
        $this->availableSetService = $availableSetService;
        $this->virtualCardService = $virtualCardService;
        $this->removalService = $removalService;
    }

    public function sync(int $since, array $only = []): array
    {
        $shouldInclude = fn(string $key) => empty($only) || in_array($key, $only, true);

        // First check which types have updates
        $updateTypes = $this->updateService->findByDate($since);

        $updates = [];
        $typesWithUpdates = [];
        foreach ($updateTypes as $update) {
            $typesWithUpdates[] = (array)$update;
        }

        // Fetch data for each type that has updates
        if ($shouldInclude('sets')) {
            $updates['sets'] = $this->toArray($this->setService->findByDate($since));
        }

        if ($shouldInclude('cards')) {
            $updates['cards'] = $this->toArray($this->cardService->findByDate($since));
        }

        if ($shouldInclude('opponents')) {
            $updates['opponents'] = $this->toArray($this->opponentService->findByDate($since));
        }

        if ($shouldInclude('skills')) {
            $updates['skills'] = $this->toArray($this->skillsService->findByDate($since));
        }

        if ($shouldInclude('virtual_sets')) {
            $updates['virtual_sets'] = $this->toArray($this->virtualSetService->findByDate($since));
        }

        if ($shouldInclude('available_sets')) {
            $updates['available_sets'] = $this->toArray($this->availableSetService->findByDate($since));
        }

        if ($shouldInclude('virtual_cards')) {
            $updates['virtual_cards'] = $this->toArray($this->virtualCardService->findByDate($since));
        }

        // Fetch removals — force object encoding even when empty
        $removals = $this->removalService->findByDate($since);
        if (empty($removals)) {
            $removals = new \stdClass();
        }

        return [
            'timestamp' => (int)date('Ymd'),
            'update_types' => $typesWithUpdates,
            'updates' => $updates,
            'removals' => $removals,
        ];
    }

    private function toArray(array $items): array
    {
        return array_map(fn($item) => (array)$item, $items);
    }
}
