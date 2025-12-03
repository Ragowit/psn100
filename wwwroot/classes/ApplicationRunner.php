<?php

declare(strict_types=1);

require_once __DIR__ . '/ApplicationContainer.php';
require_once __DIR__ . '/MaintenanceMode.php';
require_once __DIR__ . '/MaintenanceResponder.php';

final readonly class ApplicationRunner
{
    private MaintenanceResponder $maintenanceResponder;

    public function __construct(
        private ApplicationContainer $applicationContainer,
        private MaintenanceMode $maintenanceMode,
        ?MaintenanceResponder $maintenanceResponder = null
    ) {
        $this->maintenanceResponder = $maintenanceResponder ?? new MaintenanceResponder();
    }

    public static function create(
        ApplicationContainer $applicationContainer,
        MaintenanceMode $maintenanceMode,
        ?MaintenanceResponder $maintenanceResponder = null
    ): self {
        return new self($applicationContainer, $maintenanceMode, $maintenanceResponder);
    }

    public function run(): void
    {
        if ($this->maintenanceMode->isEnabled()) {
            $this->maintenanceResponder->respond($this->maintenanceMode);

            return;
        }

        $request = $this->applicationContainer->createRequestFromGlobals();
        $application = $this->applicationContainer->createApplication($request);
        $application->run();
    }
}
