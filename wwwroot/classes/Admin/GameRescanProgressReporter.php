<?php

declare(strict_types=1);

require_once __DIR__ . '/GameRescanProgressListener.php';

/**
 * Reports monotonic rescan progress and formats trophy/group labels for status messages.
 */
final class GameRescanProgressReporter
{
    private int $lastProgress = 0;

    public function __construct(
        private readonly ?GameRescanProgressListener $listener = null,
    ) {
    }

    public function reset(): void
    {
        $this->lastProgress = 0;
    }

    public function notify(int $percent, string $message): void
    {
        if ($this->listener === null) {
            return;
        }

        $clampedPercent = max(0, min(100, $percent));
        if ($clampedPercent < $this->lastProgress) {
            $clampedPercent = $this->lastProgress;
        } else {
            $this->lastProgress = $clampedPercent;
        }

        $this->listener->onProgress($clampedPercent, $message);
    }

    public function notifyRange(
        int $startPercent,
        int $endPercent,
        int $step,
        int $totalSteps,
        string $message
    ): void {
        if ($this->listener === null || $totalSteps <= 0) {
            return;
        }

        $boundedStep = max(0, min($totalSteps, $step));
        $progressSpan = $endPercent - $startPercent;

        if ($progressSpan === 0) {
            $targetPercent = $startPercent;
        } else {
            $progressRatio = $boundedStep / $totalSteps;
            $interpolated = $startPercent + ($progressSpan * $progressRatio);

            if ($boundedStep === $totalSteps) {
                $interpolated = $endPercent;
            }

            $targetPercent = (int) floor($interpolated);

            if ($progressSpan > 0) {
                $targetPercent = min($targetPercent, $endPercent);
            } else {
                $targetPercent = max($targetPercent, $endPercent);
            }
        }

        $this->notify($targetPercent, $message);
    }

    public function describeTrophyGroup(object $trophyGroup): string
    {
        $name = $this->normalizeProgressLabel((string) $trophyGroup->name());
        if ($name !== '') {
            return $name;
        }

        $detail = $this->normalizeProgressLabel((string) $trophyGroup->detail());
        if ($detail !== '') {
            return $detail;
        }

        return sprintf('Group %s', $this->normalizeProgressLabel((string) $trophyGroup->id()) ?: (string) $trophyGroup->id());
    }

    public function describeTrophy(object $trophy): string
    {
        $name = $this->normalizeProgressLabel((string) $trophy->name());
        if ($name !== '') {
            return $name;
        }

        $detail = $this->normalizeProgressLabel((string) $trophy->detail());
        if ($detail !== '') {
            return $detail;
        }

        return sprintf('Trophy %s', $this->normalizeProgressLabel((string) $trophy->id()) ?: (string) $trophy->id());
    }

    private function normalizeProgressLabel(string $label): string
    {
        $normalized = trim($label);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/', ' ', $normalized);
        if (!is_string($normalized)) {
            return '';
        }

        return $normalized;
    }
}
