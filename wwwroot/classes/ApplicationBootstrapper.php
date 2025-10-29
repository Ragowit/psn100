<?php

declare(strict_types=1);

require_once __DIR__ . '/ApplicationContainer.php';
require_once __DIR__ . '/ApplicationRunner.php';
require_once __DIR__ . '/MaintenanceResponder.php';

final class ApplicationBootstrapper
{
    private ApplicationContainer $applicationContainer;

    private function __construct(ApplicationContainer $applicationContainer)
    {
        $this->applicationContainer = $applicationContainer;
    }

    public static function bootstrap(?ApplicationContainer $applicationContainer = null): self
    {
        return new self($applicationContainer ?? ApplicationContainer::create());
    }

    public function getApplicationContainer(): ApplicationContainer
    {
        return $this->applicationContainer;
    }

    public function getDatabase(): Database
    {
        return $this->applicationContainer->getDatabase();
    }

    public function getUtility(): Utility
    {
        return $this->applicationContainer->getUtility();
    }

    public function getPaginationRenderer(): PaginationRenderer
    {
        return $this->applicationContainer->getPaginationRenderer();
    }

    public function createApplicationRunner(MaintenanceMode $maintenanceMode): ApplicationRunner
    {
        return ApplicationRunner::create(
            $this->applicationContainer,
            $maintenanceMode,
            new MaintenanceResponder()
        );
    }
}
