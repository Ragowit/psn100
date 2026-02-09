<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyRarity.php';

final class TrophyRarityFormatter
{
    private const META_THRESHOLDS = [
        ['max' => 0.02, 'label' => 'Legendary', 'class' => 'trophy-legendary'],
        ['max' => 0.2, 'label' => 'Epic', 'class' => 'trophy-epic'],
        ['max' => 2.0, 'label' => 'Rare', 'class' => 'trophy-rare'],
        ['max' => 10.0, 'label' => 'Uncommon', 'class' => 'trophy-uncommon'],
    ];

    private const IN_GAME_THRESHOLDS = [
        ['max' => 1.0, 'label' => 'Legendary', 'class' => 'trophy-legendary'],
        ['max' => 5.0, 'label' => 'Epic', 'class' => 'trophy-epic'],
        ['max' => 20.0, 'label' => 'Rare', 'class' => 'trophy-rare'],
        ['max' => 60.0, 'label' => 'Uncommon', 'class' => 'trophy-uncommon'],
    ];

    /**
     * @param float|int|string|null $rarityPercent
     */
    public function format(float|int|string|null $rarityPercent, int $status = 0): TrophyRarity
    {
        return $this->formatMeta($rarityPercent, $status);
    }

    /**
     * @param float|int|string|null $rarityPercent
     */
    public function formatMeta(float|int|string|null $rarityPercent, int $status = 0): TrophyRarity
    {
        return $this->formatWithThresholds($rarityPercent, $status, self::META_THRESHOLDS);
    }

    /**
     * @param float|int|string|null $rarityPercent
     */
    public function formatInGame(float|int|string|null $rarityPercent, int $status = 0): TrophyRarity
    {
        return $this->formatWithThresholds($rarityPercent, $status, self::IN_GAME_THRESHOLDS);
    }

    /**
     * @param float|int|string|null $rarityPercent
     */
    private function normalizePercentage(float|int|string|null $rarityPercent, ?float $normalizedValue): ?string
    {
        if ($rarityPercent === null) {
            return null;
        }

        if ($normalizedValue !== null) {
            return number_format($normalizedValue, 2, '.', '');
        }

        if (is_string($rarityPercent)) {
            $rarityPercent = trim($rarityPercent);

            if ($rarityPercent === '') {
                return null;
            }

            return $rarityPercent;
        }

        if (is_int($rarityPercent) || is_float($rarityPercent)) {
            return number_format((float) $rarityPercent, 2, '.', '');
        }

        return null;
    }

    /**
     * @param float|int|string|null $rarityPercent
     */
    private function toFloat(float|int|string|null $rarityPercent): ?float
    {
        if ($rarityPercent === null) {
            return null;
        }

        if (is_string($rarityPercent)) {
            $rarityPercent = trim($rarityPercent);

            if ($rarityPercent === '') {
                return null;
            }
        }

        if (is_numeric($rarityPercent)) {
            return (float) $rarityPercent;
        }

        return null;
    }

    /**
     * @param float|int|string|null $rarityPercent
     * @param array<int, array{max: float, label: string, class: string}> $thresholds
     */
    private function formatWithThresholds(
        float|int|string|null $rarityPercent,
        int $status,
        array $thresholds
    ): TrophyRarity {
        $value = $this->toFloat($rarityPercent);
        $percentageString = $this->normalizePercentage($rarityPercent, $value);

        if ($status === 1) {
            return new TrophyRarity($percentageString, 'Unobtainable', null, true);
        }

        if ($value !== null) {
            foreach ($thresholds as $threshold) {
                if ($value <= $threshold['max']) {
                    return new TrophyRarity(
                        $percentageString,
                        $threshold['label'],
                        $threshold['class'],
                        false
                    );
                }
            }
        }

        return new TrophyRarity($percentageString, 'Common', 'trophy-common', false);
    }
}
