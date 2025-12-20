<?php

declare(strict_types=1);

class GameCopyHandler
{
    public function __construct(private readonly GameCopyService $gameCopyService)
    {
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

        $copyIconUrl = $this->shouldCopySetting($postData, 'copy_icon_url');
        $copySetVersion = $this->shouldCopySetting($postData, 'copy_set_version');

        try {
            $this->gameCopyService->copyChildToParent(
                $childId,
                $parentId,
                $copyIconUrl,
                $copySetVersion
            );
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

    private function shouldCopySetting(array $postData, string $key): bool
    {
        if (!array_key_exists($key, $postData)) {
            return true;
        }

        $value = $postData[$key];

        if (is_array($value)) {
            $value = end($value);
        }

        $value = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $value ?? true;
    }
}
