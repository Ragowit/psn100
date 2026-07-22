<?php

declare(strict_types=1);

require_once __DIR__ . '/Platform.php';
require_once __DIR__ . '/RequestParameter.php';

final readonly class PlayerRandomGamesFilter
{
    /**
     * @var array<string, bool>
     */
    private array $selectedPlatforms;

    private function __construct(array $selectedPlatforms)
    {
        $this->selectedPlatforms = $selectedPlatforms;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[\NoDiscard]
    public static function fromArray(array $parameters): self
    {
        $selectedPlatforms = [];
        foreach (Platform::values() as $platformKey) {
            $selectedPlatforms[$platformKey] = RequestParameter::toBool($parameters[$platformKey] ?? null);
        }

        return new self($selectedPlatforms);
    }

    public function isPlatformSelected(string $platform): bool
    {
        return $this->selectedPlatforms[$platform] ?? false;
    }
}
