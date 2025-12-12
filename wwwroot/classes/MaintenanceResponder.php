<?php

declare(strict_types=1);

require_once __DIR__ . '/MaintenanceMode.php';

final readonly class MaintenanceResponder
{
    private \Closure $statusEmitter;

    private \Closure $headerEmitter;

    private \Closure $templateIncluder;

    private \Closure $terminator;

    private \Closure $headersSentDetector;

    public function __construct(
        ?callable $statusEmitter = null,
        ?callable $headerEmitter = null,
        ?callable $templateIncluder = null,
        ?callable $terminator = null,
        ?callable $headersSentDetector = null
    ) {
        $this->statusEmitter = $this->toClosure(
            $statusEmitter,
            static function (int $status): void {
                http_response_code($status);
            }
        );

        $this->headerEmitter = $this->toClosure(
            $headerEmitter,
            static function (string $header): void {
                header($header);
            }
        );

        $this->templateIncluder = $this->toClosure(
            $templateIncluder,
            static function (string $template): void {
                require_once $template;
            }
        );

        $this->terminator = $this->toClosure(
            $terminator,
            static function (): void {
                exit();
            }
        );

        $this->headersSentDetector = $this->toClosure(
            $headersSentDetector,
            static function (): bool {
                return headers_sent();
            }
        );
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

    private function toClosure(?callable $callable, callable $fallback): \Closure
    {
        return $callable instanceof \Closure
            ? $callable
            : \Closure::fromCallable($callable ?? $fallback);
    }
}
