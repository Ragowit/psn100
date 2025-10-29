<?php

declare(strict_types=1);

require_once __DIR__ . '/MaintenanceMode.php';

final class MaintenanceResponder
{
    /**
     * @var callable(int): void
     */
    private $statusEmitter;

    /**
     * @var callable(string): void
     */
    private $headerEmitter;

    /**
     * @var callable(string): void
     */
    private $templateIncluder;

    /**
     * @var callable(): void
     */
    private $terminator;

    /**
     * @var callable(): bool
     */
    private $headersSentDetector;

    public function __construct(
        ?callable $statusEmitter = null,
        ?callable $headerEmitter = null,
        ?callable $templateIncluder = null,
        ?callable $terminator = null,
        ?callable $headersSentDetector = null
    ) {
        $this->statusEmitter = $statusEmitter ?? static function (int $status): void {
            http_response_code($status);
        };
        $this->headerEmitter = $headerEmitter ?? static function (string $header): void {
            header($header);
        };
        $this->templateIncluder = $templateIncluder ?? static function (string $template): void {
            require_once $template;
        };
        $this->terminator = $terminator ?? static function (): void {
            exit();
        };
        $this->headersSentDetector = $headersSentDetector ?? static function (): bool {
            return headers_sent();
        };
    }

    public function respond(MaintenanceMode $maintenanceMode): void
    {
        ($this->statusEmitter)(503);

        if (!($this->headersSentDetector)()) {
            ($this->headerEmitter)('Retry-After: 300');
        }

        ($this->templateIncluder)($maintenanceMode->getTemplatePath());

        ($this->terminator)();
    }
}
