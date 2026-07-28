<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerSortField.php';

final readonly class WorkerPageSortLink
{
    public function __construct(
        final private WorkerSortField $field,
        final private string $url,
        final private string $indicator,
    ) {
    }

    public function getField(): WorkerSortField
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
