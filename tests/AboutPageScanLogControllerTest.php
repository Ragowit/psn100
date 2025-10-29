<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/AboutPageScanLogController.php';
require_once __DIR__ . '/../wwwroot/classes/AboutPageService.php';
require_once __DIR__ . '/../wwwroot/classes/AboutPageScanSummary.php';
require_once __DIR__ . '/../wwwroot/classes/AboutPagePlayer.php';
require_once __DIR__ . '/../wwwroot/classes/AboutPagePlayerArraySerializer.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';
require_once __DIR__ . '/../wwwroot/classes/JsonResponseEmitter.php';

final class FakeAboutPageService extends AboutPageService
{
    private AboutPageScanSummary $summary;

    /**
     * @var array<int, AboutPagePlayer>
     */
    private array $players;

    private ?int $capturedLimit = null;

    /**
     * @param array<int, AboutPagePlayer> $players
     */
    public function __construct(AboutPageScanSummary $summary, array $players)
    {
        $this->summary = $summary;
        $this->players = $players;
    }

    public function getScanSummary(): AboutPageScanSummary
    {
        return $this->summary;
    }

    /**
     * @return array<int, AboutPagePlayer>
     */
    public function getScanLogPlayers(int $limit = 10): array
    {
        $this->capturedLimit = $limit;

        return array_slice($this->players, 0, $limit);
    }

    public function getCapturedLimit(): ?int
    {
        return $this->capturedLimit;
    }
}

final class FailingAboutPageService extends AboutPageService
{
    public function __construct()
    {
    }

    public function getScanSummary(): AboutPageScanSummary
    {
        throw new \RuntimeException('Failed to load summary');
    }
}

final class AboutPageScanLogControllerTest extends TestCase
{
    public function testHandleRespondsWithSerializedPlayersAndSummary(): void
    {
        $utility = new Utility();

        $players = [
            new AboutPagePlayer($utility, 'Ragowit', 'se', 'avatar1.png', '2024-01-01 00:00:00', 999, '50', 0, 0, 10, 10, 1),
            new AboutPagePlayer($utility, 'Hunter', 'us', 'avatar2.png', '2024-01-02 12:00:00', 123, '25', 1, 0, 5, 10, 2),
        ];
        $summary = new AboutPageScanSummary(25, 7);
        $service = new FakeAboutPageService($summary, $players);
        $controller = AboutPageScanLogController::create($service, new JsonResponseEmitter());

        header_remove();
        ob_start();

        $controller->handle();

        $output = ob_get_clean();

        $this->assertSame(30, $service->getCapturedLimit());
        $this->assertSame(200, http_response_code());

        $decodedOutput = json_decode((string) $output, true);
        $this->assertTrue(is_array($decodedOutput));
        $this->assertSame('ok', $decodedOutput['status'] ?? null);
        $this->assertSame([
            'scannedPlayers' => 25,
            'newPlayers' => 7,
        ], $decodedOutput['summary'] ?? null);
        $this->assertSame(
            AboutPagePlayerArraySerializer::serializeCollection($players),
            $decodedOutput['players'] ?? null
        );
    }

    public function testHandleRespectsLimitQueryParameter(): void
    {
        $utility = new Utility();
        $players = [
            new AboutPagePlayer($utility, 'SoloPlayer', 'gb', 'avatar3.png', '2024-01-03 08:30:00', 75, '90', 0, 0, 1, 1, 5),
        ];
        $summary = new AboutPageScanSummary(3, 1);
        $service = new FakeAboutPageService($summary, $players);
        $controller = new AboutPageScanLogController($service, new JsonResponseEmitter());

        header_remove();
        ob_start();

        $controller->handle(['limit' => '5']);

        ob_end_clean();

        $this->assertSame(5, $service->getCapturedLimit());
    }

    public function testHandleRespondsWithErrorWhenExceptionThrown(): void
    {
        $controller = AboutPageScanLogController::create(new FailingAboutPageService(), new JsonResponseEmitter());

        header_remove();
        ob_start();

        $controller->handle();

        $output = ob_get_clean();

        $this->assertSame(500, http_response_code());

        $decodedOutput = json_decode((string) $output, true);
        $this->assertTrue(is_array($decodedOutput));
        $this->assertSame('error', $decodedOutput['status'] ?? null);
        $this->assertSame('Unable to load scan log data at this time.', $decodedOutput['message'] ?? null);
    }
}
