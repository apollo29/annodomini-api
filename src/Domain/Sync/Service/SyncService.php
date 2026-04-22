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
    private string $iconBaseUrl;

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
        string $iconBaseUrl = 'https://api.annodomini.app',
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
        $this->iconBaseUrl = rtrim($iconBaseUrl, '/');
    }

    public function sync(int $since, array $only = []): array
    {
        $shouldInclude = fn(string $key) => empty($only) || in_array($key, $only, true);

        // Check which types have updates — used to skip unnecessary DB queries
        $updateTypes = $this->updateService->findByDate($since);

        $typesWithUpdates = [];
        $updatedTypeNames = [];
        foreach ($updateTypes as $update) {
            $typesWithUpdates[] = (array)$update;
            $updatedTypeNames[] = $update->type;
        }

        $hasUpdate = fn(string $type) => in_array($type, $updatedTypeNames, true);

        // Only fetch data for types that actually have updates
        $updates = [];

        if ($shouldInclude('sets') && $hasUpdate('sets')) {
            $updates['sets'] = $this->setsToArray($this->setService->findByDate($since));
        } else {
            $updates['sets'] = [];
        }

        if ($shouldInclude('cards') && $hasUpdate('cards')) {
            $updates['cards'] = $this->toArray($this->cardService->findByDate($since));
        } else {
            $updates['cards'] = [];
        }

        if ($shouldInclude('opponents') && $hasUpdate('opponents')) {
            $updates['opponents'] = $this->toArray($this->opponentService->findByDate($since));
        } else {
            $updates['opponents'] = [];
        }

        if ($shouldInclude('skills') && ($hasUpdate('opponentskills') || $hasUpdate('skills'))) {
            $updates['skills'] = $this->toArray($this->skillsService->findByDate($since));
        } else {
            $updates['skills'] = [];
        }

        if ($shouldInclude('virtual_sets') && $hasUpdate('virtual_sets')) {
            $updates['virtual_sets'] = $this->toArray($this->virtualSetService->findByDate($since));
        } else {
            $updates['virtual_sets'] = [];
        }

        if ($shouldInclude('available_sets') && $hasUpdate('available_sets')) {
            $updates['available_sets'] = $this->toArray($this->availableSetService->findByDate($since));
        } else {
            $updates['available_sets'] = [];
        }

        if ($shouldInclude('virtual_cards') && $hasUpdate('virtual_cards')) {
            $updates['virtual_cards'] = $this->toArray($this->virtualCardService->findByDate($since));
        } else {
            $updates['virtual_cards'] = [];
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

    /**
     * Convert SetData objects to arrays and inject icon_url.
     *
     * Issue #196: Icons are served as static SVG files under /icons/{uid}.svg.
     * If the SetData already carries a stored icon_url (post-extraction), we
     * use it as-is; otherwise we derive the URL from the configured base URL
     * and the set's uid so clients always receive a usable URL.
     */
    private function setsToArray(array $items): array
    {
        return array_map(function ($item) {
            $row = (array)$item;

            $storedUrl = $row['icon_url'] ?? null;
            if (!empty($storedUrl)) {
                $row['icon_url'] = $storedUrl;
            } elseif (!empty($row['uid'])) {
                $row['icon_url'] = $this->iconBaseUrl . '/icons/' . $row['uid'] . '.svg';
            }

            return $row;
        }, $items);
    }
}
