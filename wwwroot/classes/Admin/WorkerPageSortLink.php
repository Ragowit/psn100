<?php

declare(strict_types=1);

final class WorkerPageSortLink
{
    private string $field;

    private string $url;

    private string $indicator;

    public function __construct(string $field, string $url, string $indicator)
    {
        $this->field = $field;
        $this->url = $url;
        $this->indicator = $indicator;
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
