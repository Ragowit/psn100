<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerScanProgress.php';

final class AdminWorkerScanProgressTest extends TestCase
{
    public function testFromJsonParsesValidPayload(): void
    {
        $progress = WorkerScanProgress::fromJson('{"current": 3, "total": 5, "title": "Example", "npCommunicationId": "foo"}');

        $this->assertTrue($progress instanceof WorkerScanProgress, 'Progress data should decode into value object.');
        $this->assertSame(3, $progress->getCurrent());
        $this->assertSame(5, $progress->getTotal());
        $this->assertSame('Example', $progress->getTitle());
        $this->assertSame('foo', $progress->getNpCommunicationId());
        $this->assertSame('3 / 5', $progress->getProgressSummary());
        $this->assertSame(60.0, $progress->getPercentage());
    }

    public function testFromJsonReturnsNullWhenNoData(): void
    {
        $this->assertSame(null, WorkerScanProgress::fromJson('{}'));
        $this->assertSame(null, WorkerScanProgress::fromJson(null));
    }
}
