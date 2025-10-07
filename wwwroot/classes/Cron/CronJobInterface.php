<?php

declare(strict_types=1);

interface CronJobInterface
{
    public function run(): void;
}
