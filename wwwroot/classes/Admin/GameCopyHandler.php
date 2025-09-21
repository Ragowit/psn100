<?php

declare(strict_types=1);

class GameCopyHandler
{
    private GameCopyService $gameCopyService;

    public function __construct(GameCopyService $gameCopyService)
    {
        $this->gameCopyService = $gameCopyService;
    }

    public function handle(array $postData): string
    {
        if (!$this->hasRequiredIds($postData)) {
            return '';
        }

        $childId = $this->filterId($postData['child'] ?? null);
        $parentId = $this->filterId($postData['parent'] ?? null);

        if ($childId === null || $parentId === null) {
            return 'Child and parent must be numeric IDs.';
        }

        try {
            $this->gameCopyService->copyChildToParent($childId, $parentId);
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        return 'The group and trophy data have been copied.';
    }

    private function hasRequiredIds(array $postData): bool
    {
        return array_key_exists('child', $postData) && array_key_exists('parent', $postData);
    }

    private function filterId(null|int|string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!ctype_digit((string) $value)) {
            return null;
        }

        return (int) $value;
    }
}
