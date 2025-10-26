<?php

declare(strict_types=1);

require_once __DIR__ . '/ApplicationContainer.php';
require_once __DIR__ . '/MaintenanceMode.php';

final class ApplicationRunner
{
    private ApplicationContainer $applicationContainer;

    private MaintenanceMode $maintenanceMode;

    public function __construct(ApplicationContainer $applicationContainer, MaintenanceMode $maintenanceMode)
    {
        $this->applicationContainer = $applicationContainer;
        $this->maintenanceMode = $maintenanceMode;
    }

    public static function create(ApplicationContainer $applicationContainer, MaintenanceMode $maintenanceMode): self
    {
        return new self($applicationContainer, $maintenanceMode);
    }

    public function run(): void
    {
        if ($this->maintenanceMode->isEnabled()) {
            $this->renderMaintenancePage();

            return;
        }

        $request = $this->applicationContainer->createRequestFromGlobals();
        $application = $this->applicationContainer->createApplication($request);
        $application->run();
    }

    private function renderMaintenancePage(): void
    {
        http_response_code(503);

        if (!headers_sent()) {
            header('Retry-After: 300');
        }

        require_once $this->maintenanceMode->getTemplatePath();
        exit();
    }
}
