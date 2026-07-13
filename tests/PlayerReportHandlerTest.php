<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportHandler.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportRequest.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportResult.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportService.php';
require_once __DIR__ . '/../wwwroot/classes/SessionManager.php';
require_once __DIR__ . '/../wwwroot/classes/CsrfTokenManager.php';
require_once __DIR__ . '/../wwwroot/classes/IpRateLimitService.php';

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
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        session_start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

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
        $request = $this->createRequestWithCsrf(['explanation' => '   '], ['REMOTE_ADDR' => '192.0.2.1']);

        $result = $handler->handleReportRequest(456, $request);

        $this->assertTrue($result->hasMessage());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('Please provide an explanation for your report.', $result->getMessage());
        $this->assertSame(0, $service->submitReportCallCount);
    }

    public function testHandleReportRequestReturnsErrorWhenCsrfTokenIsInvalid(): void
    {
        $service = new PlayerReportServiceStub(PlayerReportResult::success('irrelevant'));
        $handler = new PlayerReportHandler($service);
        $request = PlayerReportRequest::fromArrays(
            ['explanation' => 'Cheating behavior', '_csrf_token' => 'invalid-token'],
            ['REMOTE_ADDR' => '192.0.2.1']
        );

        $result = $handler->handleReportRequest(456, $request);

        $this->assertTrue($result->hasMessage());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('Your session has expired. Please reload the page and try again.', $result->getMessage());
        $this->assertSame(0, $service->submitReportCallCount);
    }

    public function testHandleReportRequestDelegatesToServiceWhenExplanationProvided(): void
    {
        $service = new PlayerReportServiceStub(PlayerReportResult::success('Player reported successfully.'));
        $handler = new PlayerReportHandler($service);
        $request = $this->createRequestWithCsrf(
            ['explanation' => '  Cheating behavior observed  '],
            ['REMOTE_ADDR' => '203.0.113.5']
        );

        $result = $handler->handleReportRequest(789, $request);

        $this->assertSame(1, $service->submitReportCallCount);
        $this->assertSame(789, $service->submittedAccountId);
        $this->assertSame('203.0.113.5', $service->submittedIpAddress);
        $this->assertSame('Cheating behavior observed', $service->submittedExplanation);
        $this->assertTrue($result->hasMessage());
        $this->assertTrue($result->isSuccess());
        $this->assertSame('Player reported successfully.', $result->getMessage());
    }

    public function testHandleReportRequestReturnsRateLimitedErrorWhenIpLimitExceeded(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE ip_rate_limit (
                bucket_key TEXT PRIMARY KEY,
                window_start TEXT NOT NULL,
                request_count INTEGER NOT NULL
            )
            SQL
        );

        $rateLimitService = new IpRateLimitService($pdo);
        $service = new PlayerReportServiceStub(PlayerReportResult::success('unused'));
        $handler = new PlayerReportHandler($service, $rateLimitService);

        for ($index = 0; $index < 5; $index++) {
            $handler->handleReportRequest(
                789,
                $this->createRequestWithCsrf(
                    ['explanation' => 'Report attempt ' . $index],
                    ['REMOTE_ADDR' => '192.0.2.88']
                )
            );
        }

        $service->submitReportCallCount = 0;
        $result = $handler->handleReportRequest(
            789,
            $this->createRequestWithCsrf(
                ['explanation' => 'One report too many'],
                ['REMOTE_ADDR' => '192.0.2.88']
            )
        );

        $this->assertTrue($result->hasMessage());
        $this->assertFalse($result->isSuccess());
        $this->assertSame(
            'Too many report submissions. Please wait a moment and try again.',
            $result->getMessage()
        );
        $this->assertSame(0, $service->submitReportCallCount);
    }

    public function testHandleReportRequestDoesNotConsumeRateLimitWhenValidationFails(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            <<<'SQL'
            CREATE TABLE ip_rate_limit (
                bucket_key TEXT PRIMARY KEY,
                window_start TEXT NOT NULL,
                request_count INTEGER NOT NULL
            )
            SQL
        );

        $rateLimitService = new IpRateLimitService($pdo);
        $service = new PlayerReportServiceStub(PlayerReportResult::success('unused'));
        $handler = new PlayerReportHandler($service, $rateLimitService);

        for ($index = 0; $index < 20; $index++) {
            $handler->handleReportRequest(
                789,
                PlayerReportRequest::fromArrays(
                    ['explanation' => '   ', '_csrf_token' => 'invalid-token'],
                    ['REMOTE_ADDR' => '192.0.2.89']
                )
            );
        }

        $result = $handler->handleReportRequest(
            789,
            $this->createRequestWithCsrf(
                ['explanation' => 'Valid report after failed attempts'],
                ['REMOTE_ADDR' => '192.0.2.89']
            )
        );

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1, $service->submitReportCallCount);
    }

    /**
     * @param array<string, mixed> $postParameters
     * @param array<string, mixed> $serverParameters
     */
    private function createRequestWithCsrf(array $postParameters, array $serverParameters): PlayerReportRequest
    {
        $postParameters['_csrf_token'] = CsrfTokenManager::getToken('public');

        return PlayerReportRequest::fromArrays($postParameters, $serverParameters);
    }
}
