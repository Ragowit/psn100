<?php

declare(strict_types=1);

enum HttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';
    case Head = 'HEAD';
    case Options = 'OPTIONS';

    #[\NoDiscard]
    public static function fromMixed(mixed $value): self
    {
        if (!is_string($value)) {
            return self::Get;
        }

        $normalized = $value |> trim(...) |> strtoupper(...);

        if ($normalized === '') {
            return self::Get;
        }

        return self::tryFrom($normalized) ?? self::Get;
    }

    #[\NoDiscard]
    public static function fromServer(array $serverData): self
    {
        return self::fromMixed($serverData['REQUEST_METHOD'] ?? self::Get->value);
    }

    public function isPost(): bool
    {
        return $this === self::Post;
    }

    public function isGet(): bool
    {
        return $this === self::Get;
    }
}
