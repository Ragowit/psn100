<?php

declare(strict_types=1);

final class PlayStationFixtureLoader
{
    /**
     * @return array<string, mixed>
     */
    public static function loadJson(string $relativePath): array
    {
        $absolutePath = __DIR__ . '/fixtures/playstation/' . ltrim($relativePath, '/');
        $raw = file_get_contents($absolutePath);

        if (!is_string($raw)) {
            throw new RuntimeException(sprintf('Unable to read fixture: %s', $absolutePath));
        }

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Fixture did not decode to array: %s', $absolutePath));
        }

        return $decoded;
    }
}
