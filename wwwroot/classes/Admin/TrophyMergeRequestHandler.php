<?php

declare(strict_types=1);

class TrophyMergeRequestHandler
{
    private TrophyMergeService $trophyMergeService;

    public function __construct(TrophyMergeService $trophyMergeService)
    {
        $this->trophyMergeService = $trophyMergeService;
    }

    public function handle(array $postData): string
    {
        if ($postData === []) {
            return '';
        }

        try {
            if ($this->isSpecificTrophyMerge($postData)) {
                return $this->handleSpecificTrophyMerge($postData);
            }

            if ($this->isGameMerge($postData)) {
                return $this->handleGameMerge($postData);
            }

            if ($this->isCloneRequest($postData)) {
                return $this->handleCloneRequest($postData);
            }
        } catch (InvalidArgumentException | RuntimeException $exception) {
            return $exception->getMessage();
        } catch (Throwable $exception) {
            return 'An unexpected error occurred: ' . $exception->getMessage();
        }

        return '';
    }

    private function isSpecificTrophyMerge(array $postData): bool
    {
        if (!array_key_exists('trophyparent', $postData) || !array_key_exists('trophychild', $postData)) {
            return false;
        }

        return $this->isNumeric($postData['trophyparent']);
    }

    private function handleSpecificTrophyMerge(array $postData): string
    {
        $parentId = (int) $postData['trophyparent'];
        $childIds = $this->parseChildTrophyIds((string) ($postData['trophychild'] ?? ''));

        return $this->trophyMergeService->mergeSpecificTrophies($parentId, $childIds);
    }

    /**
     * @return int[]
     */
    private function parseChildTrophyIds(string $childTrophies): array
    {
        $childTrophyIds = [];
        $childTrophiesRaw = array_map('trim', explode(',', $childTrophies));

        foreach ($childTrophiesRaw as $childId) {
            if ($childId === '') {
                continue;
            }

            if (!$this->isNumeric($childId)) {
                throw new InvalidArgumentException('Child trophy ids must be numeric.');
            }

            $childTrophyIds[] = (int) $childId;
        }

        return $childTrophyIds;
    }

    private function isGameMerge(array $postData): bool
    {
        return $this->isNumericValueFromArray($postData, 'parent')
            && $this->isNumericValueFromArray($postData, 'child');
    }

    private function handleGameMerge(array $postData): string
    {
        $childId = (int) $postData['child'];
        $parentId = (int) $postData['parent'];
        $method = strtolower((string) ($postData['method'] ?? 'order'));

        return $this->trophyMergeService->mergeGames($childId, $parentId, $method);
    }

    private function isCloneRequest(array $postData): bool
    {
        return $this->isNumericValueFromArray($postData, 'child');
    }

    private function handleCloneRequest(array $postData): string
    {
        $childId = (int) $postData['child'];

        return $this->trophyMergeService->cloneGame($childId);
    }

    private function isNumericValueFromArray(array $values, string $key): bool
    {
        if (!array_key_exists($key, $values)) {
            return false;
        }

        return $this->isNumeric($values[$key]);
    }

    private function isNumeric(null|int|string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return ctype_digit((string) $value);
    }
}
