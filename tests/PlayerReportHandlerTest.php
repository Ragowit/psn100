<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportHandler.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportRequest.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportResult.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportService.php';

final class PlayerReportServiceStub extends PlayerReportService
{
    private PlayerReportResult $result;

    public ?int $submittedAccountId = null;

    public ?string $submittedIpAddress = null;

    public ?string $submittedExplanation = null;

    public int $submitReportCallCount = 0;

    public function __construct(PlayerReportResult $result)
    {
        parent::__construct(new PDO('sqlite::memory:'));
        $this->result = $result;
    }

    public function submitReport(int $accountId, string $ipAddress, string $explanation): PlayerReportResult
    {
        $this->submittedAccountId = $accountId;
        $this->submittedIpAddress = $ipAddress;
        $this->submittedExplanation = $explanation;
        $this->submitReportCallCount++;

        return $this->result;
    }
}

final class PlayerReportHandlerTest extends TestCase
{
    public function testHandleReportRequestReturnsEmptyResultWhenExplanationNotSubmitted(): void
    {
        $service = new PlayerReportServiceStub(PlayerReportResult::success('irrelevant'));
        $handler = new PlayerReportHandler($service);
        $request = PlayerReportRequest::fromArrays([], ['REMOTE_ADDR' => '127.0.0.1']);

        $result = $handler->handleReportRequest(123, $request);

        $this->assertFalse($result->hasMessage());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('', $result->getMessage());
        $this->assertSame(0, $service->submitReportCallCount);
    }

    public function testHandleReportRequestReturnsErrorWhenExplanationIsEmpty(): void
    {
        $service = new PlayerReportServiceStub(PlayerReportResult::success('irrelevant'));
        $handler = new PlayerReportHandler($service);
        $request = PlayerReportRequest::fromArrays(['explanation' => '   '], ['REMOTE_ADDR' => '192.0.2.1']);

        $result = $handler->handleReportRequest(456, $request);

        $this->assertTrue($result->hasMessage());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('Please provide an explanation for your report.', $result->getMessage());
        $this->assertSame(0, $service->submitReportCallCount);
    }

    public function testHandleReportRequestDelegatesToServiceWhenExplanationProvided(): void
    {
        $service = new PlayerReportServiceStub(PlayerReportResult::success('Player reported successfully.'));
        $handler = new PlayerReportHandler($service);
        $request = PlayerReportRequest::fromArrays(['explanation' => '  Cheating behavior observed  '], ['REMOTE_ADDR' => '203.0.113.5']);

        $result = $handler->handleReportRequest(789, $request);

        $this->assertSame(1, $service->submitReportCallCount);
        $this->assertSame(789, $service->submittedAccountId);
        $this->assertSame('203.0.113.5', $service->submittedIpAddress);
        $this->assertSame('Cheating behavior observed', $service->submittedExplanation);
        $this->assertTrue($result->hasMessage());
        $this->assertTrue($result->isSuccess());
        $this->assertSame('Player reported successfully.', $result->getMessage());
    }
}
