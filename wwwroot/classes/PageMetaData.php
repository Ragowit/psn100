<?php

declare(strict_types=1);

class PageMetaData
{
    private ?string $title;
    private ?string $description;
    private ?string $image;
    private ?string $url;

    public function __construct(?string $title = null, ?string $description = null, ?string $image = null, ?string $url = null)
    {
        $this->title = $this->normalize($title);
        $this->description = $this->normalize($description);
        $this->image = $this->normalize($image);
        $this->url = $this->normalize($url);
    }

    public function setTitle(?string $title): self
    {
        $this->title = $this->normalize($title);
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $this->normalize($description);
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setImage(?string $image): self
    {
        $this->image = $this->normalize($image);
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $this->normalize($url);
        return $this;
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

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
