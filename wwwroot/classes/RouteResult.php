<?php

declare(strict_types=1);

readonly class RouteResult
{
    /**
     * @param array<string, mixed> $variables
     */
    private function __construct(
        private ?string $include,
        private ?string $redirect,
        private bool $notFound,
        private array $variables = [],
        private ?int $statusCode = null,
    ) {
    }

    public static function include(string $file, array $variables = []): self
    {
        return new self($file, null, false, $variables);
    }

    public static function redirect(string $location, int $statusCode = 303): self
    {
        return new self(null, $location, false, [], $statusCode);
    }

    public static function notFound(): self
    {
        return new self(null, null, true, [], 404);
    }

    public function shouldInclude(): bool
    {
        return $this->include !== null;
    }

    public function getInclude(): ?string
    {
        return $this->include;
    }

    public function shouldRedirect(): bool
    {
        return $this->redirect !== null;
    }

    public function getRedirect(): ?string
    {
        return $this->redirect;
    }

    public function isNotFound(): bool
    {
        return $this->notFound;
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
