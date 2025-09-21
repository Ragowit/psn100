<?php

class RouteResult
{
    private ?string $include;
    private ?string $redirect;
    private bool $notFound;
    /**
     * @var array<string, mixed>
     */
    private array $variables;
    private ?int $statusCode;

    private function __construct(?string $include, ?string $redirect, bool $notFound, array $variables = [], ?int $statusCode = null)
    {
        $this->include = $include;
        $this->redirect = $redirect;
        $this->notFound = $notFound;
        $this->variables = $variables;
        $this->statusCode = $statusCode;
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
