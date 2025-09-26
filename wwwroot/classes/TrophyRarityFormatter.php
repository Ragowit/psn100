<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyRarity.php';

class TrophyRarityFormatter
{
    /**
     * @param float|int|string|null $rarityPercent
     */
    public function format($rarityPercent, int $status = 0): TrophyRarity
    {
        $percentageString = $this->normalizePercentage($rarityPercent);

        if ($status === 1) {
            return new TrophyRarity($percentageString, 'Unobtainable', null, true);
        }

        $value = $this->toFloat($rarityPercent);

        if ($value !== null) {
            if ($value <= 0.02) {
                return new TrophyRarity($percentageString, 'Legendary', 'trophy-legendary', false);
            }

            if ($value <= 0.2) {
                return new TrophyRarity($percentageString, 'Epic', 'trophy-epic', false);
            }

            if ($value <= 2) {
                return new TrophyRarity($percentageString, 'Rare', 'trophy-rare', false);
            }

            if ($value <= 10) {
                return new TrophyRarity($percentageString, 'Uncommon', 'trophy-uncommon', false);
            }
        }

        return new TrophyRarity($percentageString, 'Common', 'trophy-common', false);
    }

    /**
     * @param float|int|string|null $rarityPercent
     */
    private function normalizePercentage($rarityPercent): ?string
    {
        if ($rarityPercent === null) {
            return null;
        }

        if (is_string($rarityPercent)) {
            return trim($rarityPercent);
        }

        if (is_int($rarityPercent) || is_float($rarityPercent)) {
            return (string) $rarityPercent;
        }

        return null;
    }

    /**
     * @param float|int|string|null $rarityPercent
     */
    private function toFloat($rarityPercent): ?float
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
}
