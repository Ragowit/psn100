<?php

declare(strict_types=1);

class PageMetaData
{
    public function __construct(
        private ?string $title = null,
        private ?string $description = null,
        private ?string $image = null,
        private ?string $url = null,
    ) {
        $this->title = $this->normalize($this->title);
        $this->description = $this->normalize($this->description);
        $this->image = $this->normalize($this->image);
        $this->url = $this->normalize($this->url);
    }

    public function setTitle(?string $title): self
    {
        return $this->setNormalizedValue('title', $title);
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setDescription(?string $description): self
    {
        return $this->setNormalizedValue('description', $description);
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setImage(?string $image): self
    {
        return $this->setNormalizedValue('image', $image);
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setUrl(?string $url): self
    {
        return $this->setNormalizedValue('url', $url);
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

    private function setNormalizedValue(string $property, ?string $value): self
    {
        $this->$property = $this->normalize($value);

        return $this;
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
