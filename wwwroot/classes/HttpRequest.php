<?php

declare(strict_types=1);

class HttpRequest
{
    /**
     * @param array<string, mixed> $server
     */
    public function __construct(private array $server = [])
    {
    }

    public static function fromGlobals(): self
    {
        return new self($_SERVER ?? []);
    }

    public function getScriptUrl(): ?string
    {
        return $this->getStringServerValue('SCRIPT_URL');
    }

    public function getRequestUri(): ?string
    {
        return $this->getStringServerValue('REQUEST_URI');
    }

    public function getResolvedUri(): string
    {
        $scriptUrl = $this->getScriptUrl();
        if ($scriptUrl !== null) {
            return $scriptUrl;
        }

        $requestUri = $this->getRequestUri();
        if ($requestUri !== null) {
            return $requestUri;
        }

        return '/';
    }

    private function getStringServerValue(string $key): ?string
    {
        $value = $this->server[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return $trimmed;
    }
}
