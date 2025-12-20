<?php

declare(strict_types=1);

final readonly class WorkerPageSortLink
{
    public function __construct(
        private string $field,
        private string $url,
        private string $indicator,
    ) {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getIndicator(): string
    {
        return $this->indicator;
    }
}
