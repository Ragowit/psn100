<?php

declare(strict_types=1);

interface TrophyMergeProgressListener
{
    public function onProgress(int $percent, string $message): void;
}
