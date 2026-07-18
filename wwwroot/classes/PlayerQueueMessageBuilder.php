<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerUrlBuilder.php';
require_once __DIR__ . '/PlayerStatusNotice.php';

final class PlayerQueueMessageBuilder
{
    /** @var list<array<string, mixed>> */
    private array $parts = [];

    public static function create(): self
    {
        return new self();
    }

    public function text(string $value): self
    {
        if ($value !== '') {
            $this->parts[] = [
                'type' => 'text',
                'value' => $value,
            ];
        }

        return $this;
    }

    public function playerLink(string $playerName): self
    {
        $this->parts[] = [
            'type' => 'link',
            'href' => PlayerUrlBuilder::playerPath($playerName),
            'label' => $playerName,
        ];

        return $this;
    }

    public function link(string $href, string $label): self
    {
        $this->parts[] = [
            'type' => 'link',
            'href' => $href,
            'label' => $label,
        ];

        return $this;
    }

    public function emphasis(string $value): self
    {
        if ($value !== '') {
            $this->parts[] = [
                'type' => 'emphasis',
                'value' => $value,
            ];
        }

        return $this;
    }

    public function spinner(): self
    {
        $this->parts[] = ['type' => 'spinner'];

        return $this;
    }

    public function progress(int $percentage, ?string $title = null, ?string $summary = null): self
    {
        $part = [
            'type' => 'progress',
            'percentage' => $percentage,
        ];

        if ($title !== null && $title !== '') {
            $part['title'] = $title;
        }

        if ($summary !== null && $summary !== '') {
            $part['summary'] = $summary;
        }

        $this->parts[] = $part;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function build(): array
    {
        return $this->parts;
    }

    public function toPlainText(): string
    {
        $text = '';

        foreach ($this->parts as $part) {
            $type = $part['type'] ?? '';

            $text .= match ($type) {
                'text', 'emphasis' => (string) ($part['value'] ?? ''),
                'link' => (string) ($part['label'] ?? ''),
                // Progress bars are rendered separately in the client.
                'progress' => '',
                default => '',
            };
        }

        return trim($text);
    }
}
