<?php

declare(strict_types=1);

require_once __DIR__ . '/TestSuiteResult.php';

interface TestSuiteInterface
{
    public function run(): TestSuiteResult;
}
