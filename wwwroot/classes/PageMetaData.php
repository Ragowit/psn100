<?php

declare(strict_types=1);

final readonly class PageMetaData
{
    private ?string $title;
    private ?string $description;
    private ?string $image;
    private ?string $url;

    public function __construct(
        ?string $title = null,
        ?string $description = null,
        ?string $image = null,
        ?string $url = null,
    ) {
        $this->title = self::normalize($title);
        $this->description = self::normalize($description);
        $this->image = self::normalize($image);
        $this->url = self::normalize($url);
    }

    public function withTitle(?string $title): self
    {
        return new self($title, $this->description, $this->image, $this->url);
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function withDescription(?string $description): self
    {
        return new self($this->title, $description, $this->image, $this->url);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function withImage(?string $image): self
    {
        return new self($this->title, $this->description, $image, $this->url);
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function withUrl(?string $url): self
    {
        return new self($this->title, $this->description, $this->image, $url);
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function isEmpty(): bool
    {
        return $this->title === null
            && $this->description === null
            && $this->image === null
            && $this->url === null;
    }

    private static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
