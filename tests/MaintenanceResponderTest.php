<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/MaintenanceResponder.php';
require_once __DIR__ . '/../wwwroot/classes/MaintenanceMode.php';

final class MaintenanceResponderTest extends TestCase
{
    public function testRespondEmitsStatusHeaderAndTerminates(): void
    {
        $status = null;
        $headers = [];
        $includedTemplate = null;
        $terminated = false;
        $headersSent = false;

        $responder = new MaintenanceResponder(
            static function (int $code) use (&$status): void {
                $status = $code;
            },
            static function (string $header) use (&$headers): void {
                $headers[] = $header;
            },
            static function (string $template) use (&$includedTemplate): void {
                $includedTemplate = $template;
            },
            static function () use (&$terminated): void {
                $terminated = true;
            },
            static function () use (&$headersSent): bool {
                return $headersSent;
            }
        );

        $mode = MaintenanceMode::enabled('/path/to/maintenance.php');
        $responder->respond($mode);

        $this->assertSame(503, $status);
        $this->assertSame(['Retry-After: 300'], $headers);
        $this->assertSame('/path/to/maintenance.php', $includedTemplate);
        $this->assertTrue($terminated);
    }

    public function testRespondSkipsRetryHeaderWhenHeadersAlreadySent(): void
    {
        $headers = [];
        $headersSent = true;

        $responder = new MaintenanceResponder(
            static function (): void {
                // No-op for testing.
            },
            static function (string $header) use (&$headers): void {
                $headers[] = $header;
            },
            static function (): void {
                // No-op for testing.
            },
            static function (): void {
                // No-op for testing.
            },
            static function () use (&$headersSent): bool {
                return $headersSent;
            }
        );

        $mode = MaintenanceMode::enabled('/template.php');
        $responder->respond($mode);

        $this->assertSame([], $headers);
    }
}
