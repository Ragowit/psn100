<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerScanProgress.php';

final class PlayerScanProgressTest extends TestCase
{
    public function testFromArrayReturnsNullWhenNoDataProvided(): void
    {
        $this->assertSame(null, PlayerScanProgress::fromArray(null));
        $this->assertSame(null, PlayerScanProgress::fromArray([]));
    }

    public function testFromArrayNormalizesValues(): void
    {
        $progress = PlayerScanProgress::fromArray([
            'current' => '5',
            'total' => 10,
            'title' => ' Example ',
            'npCommunicationId' => ' NPWR123 ',
        ]);

        $this->assertTrue($progress instanceof PlayerScanProgress, 'Expected PlayerScanProgress instance.');
        if (!$progress instanceof PlayerScanProgress) {
            return;
        }
        $this->assertSame(5, $progress->getCurrent());
        $this->assertSame(10, $progress->getTotal());
        $this->assertSame('Example', $progress->getTitle());
        $this->assertSame('NPWR123', $progress->getNpCommunicationId());
        $this->assertSame('(5/10)', $progress->getProgressSummary());
        $this->assertSame(50, $progress->getPercentage());
    }

    public function testPercentageClampsValuesWithinRange(): void
    {
        $progress = PlayerScanProgress::fromArray([
            'current' => 15,
            'total' => 10,
        ]);

        $this->assertTrue($progress instanceof PlayerScanProgress, 'Expected PlayerScanProgress instance.');
        if (!$progress instanceof PlayerScanProgress) {
            return;
        }
        $this->assertSame('(10/10)', $progress->getProgressSummary());
        $this->assertSame(100, $progress->getPercentage());
    }
}
