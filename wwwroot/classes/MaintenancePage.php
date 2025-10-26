<?php

declare(strict_types=1);

require_once __DIR__ . '/MaintenancePageStylesheet.php';

final class MaintenancePage
{
    private string $title;

    private string $heading;

    private string $description;

    private string $author;

    private string $message;

    /**
     * @var MaintenancePageStylesheet[]
     */
    private array $stylesheets;

    /**
     * @param MaintenancePageStylesheet[] $stylesheets
     */
    private function __construct(
        string $title,
        string $heading,
        string $description,
        string $author,
        string $message,
        array $stylesheets
    ) {
        $this->title = $title;
        $this->heading = $heading;
        $this->description = $description;
        $this->author = $author;
        $this->message = $message;
        $this->stylesheets = $stylesheets;
    }

    public static function createDefault(): self
    {
        return new self(
            'Maintenance ~ PSN 100%',
            'Maintenance',
            'Check your leaderboard position against other PlayStation trophy hunters!',
            "Markus 'Ragowit' Persson, and other contributors via GitHub project",
            'The site is undergoing some maintenance. Should be back soon!',
            [MaintenancePageStylesheet::bootstrapCdn()]
        );
    }

    public function withMessage(string $message): self
    {
        $clone = clone $this;
        $clone->message = $message;

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return MaintenancePageStylesheet[]
     */
    public function getStylesheets(): array
    {
        return $this->stylesheets;
    }
}
