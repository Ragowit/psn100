<?php

declare(strict_types=1);

interface GameRescanProgressListener
{
    public function onProgress(int $percent, string $message): void;
}
