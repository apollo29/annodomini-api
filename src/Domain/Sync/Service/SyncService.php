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
    private string $iconsDir;

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
        ?string $iconsDir = null,
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
        $this->iconsDir = $iconsDir ?? realpath(__DIR__ . '/../../../../public/icons') ?: '';
    }

    /**
     * Build a sync payload for the given since-timestamp.
     *
     * @param int    $since         since-date (YYYYMMDD) or 0 for "all"
     * @param array  $only          optional filter on dataset types
     * @param string $iconBaseUrl   base URL to prepend to icon paths; if empty,
     *                              falls back to "https://api.annodomini.app"
     *                              (tests/legacy). Caller (SyncAction) is
     *                              expected to pass the current request's URL
     *                              so dev/prod each serve their own icons.
     */
    public function sync(int $since, array $only = [], string $iconBaseUrl = ''): array
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
            $updates['sets'] = $this->setsToArray(
                $this->setService->findByDate($since),
                $iconBaseUrl ?: 'https://api.annodomini.app'
            );
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
     * Priority order for icon_url:
     *   1. stored icon_url column in DB (set by extract-icons / migrate.php)
     *   2. derived from uid — but only if the SVG file physically exists on
     *      disk, so we never hand clients a URL that will 404.
     *   3. omitted entirely (null) — client then falls back to the base64
     *      `icon` field which is always present.
     */
    private function setsToArray(array $items, string $iconBaseUrl): array
    {
        $base = rtrim($iconBaseUrl, '/');

        return array_map(function ($item) use ($base) {
            $row = (array)$item;
            unset($row['icon_url']);  // reset, we re-derive below
            $uid = $row['uid'] ?? null;

            if (empty($uid)) {
                return $row;
            }

            // Prefer stored URL (from migrate.php). If that URL was generated
            // for a different host (e.g. prod URL stored but sync requested
            // on dev), replace the host part so clients always get the URL
            // for the host they just called.
            $storedUrl = $item->icon_url ?? null;
            if (!empty($storedUrl)) {
                $row['icon_url'] = $this->replaceHost($storedUrl, $base);

                return $row;
            }

            // No stored URL — derive from uid, but only if the SVG file
            // actually exists on disk. Otherwise omit so the client falls
            // back to base64.
            if ($this->iconsDir !== '' && is_file($this->iconsDir . '/' . $uid . '.svg')) {
                $row['icon_url'] = $base . '/icons/' . $uid . '.svg';
            }

            return $row;
        }, $items);
    }

    /**
     * Replace the scheme+host of $storedUrl with $base, keeping the path.
     * Used so stored icon_urls (possibly pointing at prod) follow the
     * current request host on dev/staging.
     */
    private function replaceHost(string $storedUrl, string $base): string
    {
        $path = parse_url($storedUrl, PHP_URL_PATH) ?: '/';

        return rtrim($base, '/') . $path;
    }
}
