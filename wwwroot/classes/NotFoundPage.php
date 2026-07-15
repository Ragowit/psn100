<?php

declare(strict_types=1);

final readonly class NotFoundPage
{
    private function __construct(
        private string $title,
        private string $heading,
        private string $message,
    ) {
    }

    public static function createDefault(): self
    {
        return new self(
            '404 ~ PSN 100%',
            '404',
            'There are no trophies here.'
        );
    }

    public function withHeading(string $heading): self
    {
        return clone($this, ['heading' => $heading]);
    }

    public function withMessage(string $message): self
    {
        return clone($this, ['message' => $message]);
    }

    public function withTitle(string $title): self
    {
        return clone($this, ['title' => $title]);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getHeading(): string
    {
        return $this->heading;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
