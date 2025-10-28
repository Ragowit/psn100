<?php

declare(strict_types=1);

final class GameRescanDifferenceTracker
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $differences = [];

    public function recordTitleChange(string $field, mixed $previous, mixed $current): void
    {
        $this->addDifference('Trophy Title', $field, $previous, $current);
    }

    public function recordGroupChange(string $groupId, string $groupLabel, string $field, mixed $previous, mixed $current): void
    {
        $displayLabel = $groupLabel !== '' ? $groupLabel : $groupId;
        $context = $groupLabel !== ''
            ? sprintf('Group "%s" (%s)', $displayLabel, $groupId)
            : sprintf('Group %s', $groupId);

        $this->addDifference($context, $field, $previous, $current);
    }

    public function recordTrophyChange(
        string $groupId,
        int $orderId,
        string $groupLabel,
        string $trophyLabel,
        string $field,
        mixed $previous,
        mixed $current
    ): void {
        $displayGroup = $groupLabel !== '' ? $groupLabel : $groupId;
        $displayTrophy = $trophyLabel !== '' ? $trophyLabel : ('#' . $orderId);
        $context = sprintf(
            'Trophy "%s" (#%d) in group "%s" (%s)',
            $displayTrophy,
            $orderId,
            $displayGroup,
            $groupId
        );

        $this->addDifference($context, $field, $previous, $current);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDifferences(): array
    {
        return $this->differences;
    }

    private function addDifference(string $context, string $field, mixed $previous, mixed $current): void
    {
        $previousValue = $this->normalizeValue($previous);
        $currentValue = $this->normalizeValue($current);

        if ($previousValue === $currentValue) {
            return;
        }

        $this->differences[] = [
            'context' => $context,
            'field' => $field,
            'previous' => $previousValue,
            'current' => $currentValue,
        ];
    }

    private function normalizeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value === '' ? null : $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

        return $encoded === false ? null : $encoded;
    }
}
