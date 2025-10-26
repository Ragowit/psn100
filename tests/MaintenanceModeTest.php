<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/MaintenanceMode.php';

final class MaintenanceModeTest extends TestCase
{
    private ?string $previousEnv;

    protected function setUp(): void
    {
        $this->previousEnv = getenv('MAINTENANCE_MODE') === false ? null : (string) getenv('MAINTENANCE_MODE');
        putenv('MAINTENANCE_MODE');
    }

    protected function tearDown(): void
    {
        if ($this->previousEnv === null) {
            putenv('MAINTENANCE_MODE');
            return;
        }

        putenv('MAINTENANCE_MODE=' . $this->previousEnv);
    }

    public function testFromEnvironmentEnablesWhenFlagIsTruthy(): void
    {
        $mode = MaintenanceMode::fromEnvironment(['MAINTENANCE_MODE' => 'On'], '/path/to/template.php');

        $this->assertTrue($mode->isEnabled());
        $this->assertSame('/path/to/template.php', $mode->getTemplatePath());
    }

    public function testFromEnvironmentDisablesWhenFlagIsFalsey(): void
    {
        $mode = MaintenanceMode::fromEnvironment(['MAINTENANCE_MODE' => 'NO'], '/path/to/template.php', true);

        $this->assertFalse($mode->isEnabled());
    }

    public function testFromEnvironmentFallsBackToDefaultWhenFlagIsUnrecognized(): void
    {
        $mode = MaintenanceMode::fromEnvironment(['MAINTENANCE_MODE' => 'sometimes'], '/template.php', true);

        $this->assertTrue($mode->isEnabled());
        $this->assertSame('/template.php', $mode->getTemplatePath());
    }

    public function testFromEnvironmentUsesEnvironmentVariableWhenServerValueMissing(): void
    {
        putenv('MAINTENANCE_MODE=1');

        $mode = MaintenanceMode::fromEnvironment([], '/template.php');

        $this->assertTrue($mode->isEnabled());
    }
}
