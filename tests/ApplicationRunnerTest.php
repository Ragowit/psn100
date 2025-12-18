<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/ApplicationRunner.php';
require_once __DIR__ . '/../wwwroot/classes/ApplicationContainer.php';
require_once __DIR__ . '/../wwwroot/classes/Application.php';
require_once __DIR__ . '/../wwwroot/classes/HttpRequest.php';
require_once __DIR__ . '/../wwwroot/classes/MaintenanceMode.php';
require_once __DIR__ . '/../wwwroot/classes/MaintenanceResponder.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';
require_once __DIR__ . '/../wwwroot/classes/PaginationRenderer.php';

final class ApplicationRunnerTestDatabase extends Database
{
    public function __construct()
    {
        // Intentionally bypass parent constructor to avoid connecting to the database.
    }
}

final class ThrowingApplicationContainerStub extends ApplicationContainer
{
    public function __construct()
    {
        parent::__construct(
            new ApplicationRunnerTestDatabase(),
            new Utility(),
            new PaginationRenderer()
        );
    }

    public function createRequestFromGlobals(): HttpRequest
    {
        throw new RuntimeException('createRequestFromGlobals should not be called when in maintenance mode.');
    }

    public function createApplication(HttpRequest $request): Application
    {
        throw new RuntimeException('createApplication should not be called when in maintenance mode.');
    }
}

final class ApplicationSpy extends Application
{
    public int $runCalls = 0;

    public function __construct()
    {
        // Parent constructor intentionally not called; overrides provide behaviour.
    }

    public function run(): void
    {
        $this->runCalls++;
    }
}

final class ApplicationContainerSpy extends ApplicationContainer
{
    private HttpRequest $request;

    private ApplicationSpy $application;

    public ?HttpRequest $receivedRequest = null;

    public function __construct(HttpRequest $request, ApplicationSpy $application)
    {
        parent::__construct(
            new ApplicationRunnerTestDatabase(),
            new Utility(),
            new PaginationRenderer()
        );

        $this->request = $request;
        $this->application = $application;
    }

    public function createRequestFromGlobals(): HttpRequest
    {
        return $this->request;
    }

    public function createApplication(HttpRequest $request): Application
    {
        $this->receivedRequest = $request;

        return $this->application;
    }
}

final class ApplicationRunnerTest extends TestCase
{
    public function testRunSkipsApplicationWhenMaintenanceModeEnabled(): void
    {
        $maintenanceMode = MaintenanceMode::enabled('/maintenance.php');
        $headers = [];
        $includedTemplate = null;
        $terminated = false;
        $responder = new MaintenanceResponder(
            static function (): void {
                // No-op for testing.
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
            static function (): bool {
                return false;
            }
        );
        $container = new ThrowingApplicationContainerStub();

        $runner = new ApplicationRunner($container, $maintenanceMode, $responder);
        $runner->run();

        $this->assertSame(['Retry-After: 300'], $headers);
        $this->assertSame('/maintenance.php', $includedTemplate);
        $this->assertTrue($terminated);
    }

    public function testRunDelegatesToContainerWhenMaintenanceModeDisabled(): void
    {
        $maintenanceMode = MaintenanceMode::disabled('/maintenance.php');
        $request = new HttpRequest(['REQUEST_URI' => '/example']);
        $application = new ApplicationSpy();
        $container = new ApplicationContainerSpy($request, $application);
        $headers = [];
        $includedTemplate = null;
        $terminated = false;
        $responder = new MaintenanceResponder(
            static function (): void {
                // No-op for testing.
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
            static function (): bool {
                return false;
            }
        );

        $runner = new ApplicationRunner($container, $maintenanceMode, $responder);
        $runner->run();

        $this->assertSame($request, $container->receivedRequest);
        $this->assertSame(1, $application->runCalls);
        $this->assertSame([], $headers);
        $this->assertSame(null, $includedTemplate);
        $this->assertFalse($terminated);
    }
}
