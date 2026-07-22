<?php

declare(strict_types=1);

require_once __DIR__ . '/MaintenanceMode.php';

final readonly class MaintenanceResponder
{
    private const \Closure DEFAULT_STATUS_EMITTER = http_response_code(...);

    private const \Closure DEFAULT_HEADER_EMITTER = header(...);

    private const \Closure DEFAULT_TEMPLATE_INCLUDER = static function (string $template): void {
        require_once $template;
    };

    private const \Closure DEFAULT_TERMINATOR = static function (): never {
        exit();
    };

    private const \Closure DEFAULT_HEADERS_SENT_DETECTOR = headers_sent(...);

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
        $this->statusEmitter = $this->toClosure($statusEmitter, self::DEFAULT_STATUS_EMITTER);
        $this->headerEmitter = $this->toClosure($headerEmitter, self::DEFAULT_HEADER_EMITTER);
        $this->templateIncluder = $this->toClosure($templateIncluder, self::DEFAULT_TEMPLATE_INCLUDER);
        $this->terminator = $this->toClosure($terminator, self::DEFAULT_TERMINATOR);
        $this->headersSentDetector = $this->toClosure($headersSentDetector, self::DEFAULT_HEADERS_SENT_DETECTOR);
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
        return ($callable ?? $fallback)(...);
    }
}
