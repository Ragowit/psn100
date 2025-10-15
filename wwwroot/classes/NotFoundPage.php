<?php

declare(strict_types=1);

final class NotFoundPage
{
    private string $title;

    private string $heading;

    private string $message;

    private function __construct(string $title, string $heading, string $message)
    {
        $this->title = $title;
        $this->heading = $heading;
        $this->message = $message;
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
        $clone = clone $this;
        $clone->heading = $heading;

        return $clone;
    }

    public function withMessage(string $message): self
    {
        $clone = clone $this;
        $clone->message = $message;

        return $clone;
    }

    public function withTitle(string $title): self
    {
        $clone = clone $this;
        $clone->title = $title;

        return $clone;
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
