<?php

declare(strict_types=1);

require_once __DIR__ . '/Game/GameDetails.php';

/**
 * Utility responsible for rendering the visual diff blocks that appear on the
 * game history page.  The renderer keeps all of the formatting logic inside a
 * dedicated class instead of relying on a collection of global helper
 * functions, which makes the behaviour easier to test and reuse.
 */
final class GameHistoryRenderer
{
    /**
     * @param array{previous: mixed, current: mixed}|null $diff
     */
    public function renderTextDiff(?array $diff, bool $isMultiline = false, bool $hidePrevious = false): string
    {
        if ($diff === null) {
            return '';
        }

        $previousValue = is_string($diff['previous'] ?? null) ? $diff['previous'] : null;
        $currentValue = is_string($diff['current'] ?? null) ? $diff['current'] : null;

        if ($previousValue !== null && $currentValue !== null) {
            $highlighted = $this->highlightTextDiff($previousValue, $currentValue, $isMultiline);
            $previous = $highlighted['previous'];
            $current = $highlighted['current'];
        } else {
            $previous = $this->formatText($previousValue, $isMultiline);
            $current = $this->formatText($currentValue, $isMultiline);
        }

        return $this->renderDiffBlocks($previous, $current, $hidePrevious);
    }

    /**
     * @param array{previous: mixed, current: mixed}|null $diff
     */
    public function renderNumberDiff(?array $diff, bool $hidePrevious = false): string
    {
        if ($diff === null) {
            return '';
        }

        $previous = $this->formatNumber(isset($diff['previous']) && is_int($diff['previous']) ? $diff['previous'] : null);
        $current = $this->formatNumber(isset($diff['current']) && is_int($diff['current']) ? $diff['current'] : null);

        return $this->renderDiffBlocks($previous, $current, $hidePrevious);
    }

    public function renderSingleText(?string $value, bool $isMultiline = false): string
    {
        return $this->formatText($value, $isMultiline);
    }

    public function renderSingleNumber(?int $value): string
    {
        return $this->formatNumber($value);
    }

    /**
     * @param array{previous: mixed, current: mixed}|null $diff
     */
    public function renderIconDiff(
        ?array $diff,
        GameDetails $game,
        string $type,
        ?string $name,
        bool $hidePrevious = false
    ): string {
        if ($diff === null) {
            return '';
        }

        $previous = $this->formatIcon(
            is_string($diff['previous'] ?? null) ? $diff['previous'] : null,
            $game,
            $type,
            $name,
            'previous'
        );
        $current = $this->formatIcon(
            is_string($diff['current'] ?? null) ? $diff['current'] : null,
            $game,
            $type,
            $name,
            'current'
        );

        return $this->renderDiffBlocks($previous, $current, $hidePrevious);
    }

    public function renderSingleIcon(?string $iconUrl, GameDetails $game, string $type, ?string $name): string
    {
        $resolvedPath = $this->resolveIconPath($iconUrl, $game, $type);

        if ($resolvedPath === null) {
            return '<div class="text-center"><span class="history-diff__empty">&mdash;</span></div>';
        }

        $objectFit = 'object-fit-scale';
        $directory = 'trophy';
        $height = 3.5;

        if ($type === 'group') {
            $objectFit = 'object-fit-cover';
            $directory = 'group';
        } elseif ($type === 'title') {
            $directory = 'title';
            $height = 5.5;
        }

        return '<div class="text-center">'
            . '<img class="' . $objectFit . ' rounded" style="height: ' . $height . 'rem;" src="/img/' . $directory . '/'
            . htmlentities($resolvedPath, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlentities($name ?? '', ENT_QUOTES, 'UTF-8') . '">'
            . '</div>';
    }

    private function renderDiffBlocks(string $previousHtml, string $currentHtml, bool $hidePrevious = false): string
    {
        $html = '<div class="history-diff">';

        if (!$hidePrevious) {
            $html .= '<div class="history-diff__previous"><span class="visually-hidden">Previous value:</span>'
                . $previousHtml . '</div>';
        }

        $html .= '<div class="history-diff__current"><span class="visually-hidden">New value:</span>'
            . $currentHtml . '</div>';
        $html .= '</div>';

        return $html;
    }

    private function formatText(?string $value, bool $isMultiline = false): string
    {
        if ($value === null || $value === '') {
            return '<span class="history-diff__empty">&mdash;</span>';
        }

        $escaped = htmlentities($value, ENT_QUOTES, 'UTF-8');

        return $isMultiline ? nl2br($escaped) : $escaped;
    }

    private function formatNumber(?int $value): string
    {
        if ($value === null) {
            return '<span class="history-diff__empty">&mdash;</span>';
        }

        return htmlentities((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @return array{previous: string, current: string}
     */
    private function highlightTextDiff(string $previousValue, string $currentValue, bool $isMultiline): array
    {
        $previousTokens = $this->tokenizeString($previousValue);
        $currentTokens = $this->tokenizeString($currentValue);
        $diff = $this->buildTokenDiff($previousTokens, $currentTokens);

        return [
            'previous' => $this->renderHighlightedTokens($diff['previous'], $isMultiline, 'previous'),
            'current' => $this->renderHighlightedTokens($diff['current'], $isMultiline, 'current'),
        ];
    }

    /**
     * @return list<string>
     */
    private function tokenizeString(string $value): array
    {
        $tokens = preg_split('/(\s+)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($tokens === false) {
            return [$value];
        }

        return $tokens;
    }

    /**
     * @param list<string> $previousTokens
     * @param list<string> $currentTokens
     * @return array{previous: list<array{value: string, state: string}>, current: list<array{value: string, state: string}>}
     */
    private function buildTokenDiff(array $previousTokens, array $currentTokens): array
    {
        $previousLength = count($previousTokens);
        $currentLength = count($currentTokens);

        $lcs = array_fill(0, $previousLength + 1, array_fill(0, $currentLength + 1, 0));

        for ($i = $previousLength - 1; $i >= 0; $i--) {
            for ($j = $currentLength - 1; $j >= 0; $j--) {
                if ($previousTokens[$i] === $currentTokens[$j]) {
                    $lcs[$i][$j] = $lcs[$i + 1][$j + 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
                }
            }
        }

        $previousDiff = [];
        $currentDiff = [];
        $i = 0;
        $j = 0;

        while ($i < $previousLength && $j < $currentLength) {
            if ($previousTokens[$i] === $currentTokens[$j]) {
                $previousDiff[] = ['value' => $previousTokens[$i], 'state' => 'equal'];
                $currentDiff[] = ['value' => $currentTokens[$j], 'state' => 'equal'];
                $i++;
                $j++;
                continue;
            }

            if ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $previousDiff[] = ['value' => $previousTokens[$i], 'state' => 'removed'];
                $i++;
                continue;
            }

            $currentDiff[] = ['value' => $currentTokens[$j], 'state' => 'added'];
            $j++;
        }

        while ($i < $previousLength) {
            $previousDiff[] = ['value' => $previousTokens[$i], 'state' => 'removed'];
            $i++;
        }

        while ($j < $currentLength) {
            $currentDiff[] = ['value' => $currentTokens[$j], 'state' => 'added'];
            $j++;
        }

        return ['previous' => $previousDiff, 'current' => $currentDiff];
    }

    /**
     * @param list<array{value: string, state: string}> $tokens
     */
    private function renderHighlightedTokens(array $tokens, bool $isMultiline, string $state): string
    {
        $html = '';
        $highlightTokens = [];
        $highlightState = null;

        $flushHighlight = function () use (&$highlightTokens, &$html, &$highlightState): void {
            if ($highlightState === null) {
                return;
            }

            $html .= '<mark class="history-highlight history-highlight--' . $highlightState . '">';
            foreach ($highlightTokens as $token) {
                if ($token['isWhitespace']) {
                    $html .= $token['value'];
                    continue;
                }

                $html .= $token['value'];
            }
            $html .= '</mark>';

            $highlightTokens = [];
            $highlightState = null;
        };

        foreach ($tokens as $token) {
            $isWhitespace = trim($token['value']) === '';
            $escaped = htmlentities($token['value'], ENT_QUOTES, 'UTF-8');

            if ($isWhitespace && $isMultiline) {
                $escaped = str_replace(["\r\n", "\n", "\r"], '<br>', $escaped);
            }

            if ($token['state'] !== 'equal') {
                if ($highlightState !== $token['state']) {
                    $flushHighlight();
                }

                $highlightState = $token['state'];
                $highlightTokens[] = ['value' => $escaped, 'isWhitespace' => $isWhitespace];
                continue;
            }

            if ($highlightState !== null && $isWhitespace) {
                $highlightTokens[] = ['value' => $escaped, 'isWhitespace' => true];
                continue;
            }

            $flushHighlight();
            $html .= $escaped;
        }

        $flushHighlight();

        return $html;
    }

    private function resolveIconPath(?string $iconUrl, GameDetails $game, string $type): ?string
    {
        if ($iconUrl === null || $iconUrl === '') {
            return null;
        }

        if ($iconUrl === '.png') {
            $hasPs5Assets = str_contains($game->getPlatform(), 'PS5') || str_contains($game->getPlatform(), 'PSVR2');

            if ($type === 'group' || $type === 'title') {
                return $hasPs5Assets ? '../missing-ps5-game-and-trophy.png' : '../missing-ps4-game.png';
            }

            if ($type === 'trophy') {
                return $hasPs5Assets ? '../missing-ps5-game-and-trophy.png' : '../missing-ps4-trophy.png';
            }
        }

        return $iconUrl;
    }

    private function formatIcon(?string $iconUrl, GameDetails $game, string $type, ?string $name, string $state): string
    {
        $resolvedPath = $this->resolveIconPath($iconUrl, $game, $type);

        if ($resolvedPath === null) {
            return '<div class="text-center"><span class="history-diff__empty">&mdash;</span></div>';
        }

        $borderClass = $state === 'previous' ? 'border-danger' : 'border-success';
        $objectFit = 'object-fit-scale';
        $directory = 'trophy';
        $height = 3.5;

        if ($type === 'group') {
            $objectFit = 'object-fit-cover';
            $directory = 'group';
        } elseif ($type === 'title') {
            $directory = 'title';
            $height = 5.5;
        }

        return '<div class="text-center">'
            . '<img class="' . $objectFit . ' border border-2 ' . $borderClass . ' rounded" style="height: ' . $height . 'rem;" src="/img/'
            . $directory . '/' . htmlentities($resolvedPath, ENT_QUOTES, 'UTF-8')
            . '" alt="' . htmlentities($name ?? '', ENT_QUOTES, 'UTF-8') . '">'
            . '</div>';
    }
}

