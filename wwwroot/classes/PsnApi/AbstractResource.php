<?php

declare(strict_types=1);

namespace PsnApi;

abstract class AbstractResource
{
    protected HttpClient $httpClient;

    private ?object $data;

    public function __construct(HttpClient $httpClient, ?object $data = null)
    {
        $this->httpClient = $httpClient;
        $this->data = $data;
    }

    protected function getData(): object
    {
        if ($this->data === null) {
            $this->data = $this->fetch();
        }

        return $this->data;
    }

    protected function setData(object $data): void
    {
        $this->data = $data;
    }

    abstract protected function fetch(): object;

    protected function pluck(string $path)
    {
        $segments = explode('.', $path);
        $value = $this->getData();

        foreach ($segments as $segment) {
            if (is_object($value) && property_exists($value, $segment)) {
                $value = $value->{$segment};
            } elseif (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return null;
            }
        }

        return $value;
    }
}
