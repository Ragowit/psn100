<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/MergeNpCommunicationId.php';

final class MergeNpCommunicationIdTest extends TestCase
{
    public function testMatchesRecognizesMergePrefix(): void
    {
        $this->assertTrue(MergeNpCommunicationId::matches('MERGE_000001'));
        $this->assertTrue(MergeNpCommunicationId::matches('MERGE'));
        $this->assertFalse(MergeNpCommunicationId::matches('NPWR10853_00'));
        $this->assertFalse(MergeNpCommunicationId::matches('merge_000001'));
    }

    public function testForGameIdBuildsZeroPaddedIdentifier(): void
    {
        $this->assertSame('MERGE_000001', MergeNpCommunicationId::forGameId(1));
        $this->assertSame('MERGE_048500', MergeNpCommunicationId::forGameId(48500));
        $this->assertSame('MERGE_123456', MergeNpCommunicationId::forGameId(123456));
    }
}
