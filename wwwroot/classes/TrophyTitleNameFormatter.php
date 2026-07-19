<?php

declare(strict_types=1);

/**
 * Normalises raw PlayStation trophy title names for storage and display.
 *
 * Strips trophy-set boilerplate, normalises separators, and applies APA title case.
 */
final class TrophyTitleNameFormatter
{
    public function format(string $name): string
    {
        return $name
            |> $this->sanitize(...)
            |> $this->toApaTitleCase(...);
    }

    public function sanitize(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return $name;
        }

        $name = $name
            |> (fn(string $value): string => str_replace(['™', '®', '©'], '', $value))
            |> (fn(string $value): string => str_replace('–', '-', $value))
            |> (fn(string $value): string => str_replace(['’', '´', '`'], '\'', $value))
            |> (fn(string $value): string => preg_replace('/\s*:\s*/', ': ', $value) ?? $value);

        if ($name === '') {
            return $name;
        }

        $prefixPatterns = [
            '/^Trophy Set For\b[:\s-]*/i',
            '/^Trophy Set\b[:\s-]*/i',
            '/^Trophyset\b[:\s-]*/i',
        ];

        foreach ($prefixPatterns as $pattern) {
            $name = preg_replace($pattern, '', $name, 1);
        }

        $suffixPatterns = [
            '/\s*Trophy Set\.$/i',
            '/\s*Trophy Set$/i',
            '/\s*Trophyset\.$/i',
            '/\s*Trophyset$/i',
            '/\s*Trophies$/i',
            '/\s*Trophy$/i',
        ];

        $matchingSuffix = array_find(
            $suffixPatterns,
            static fn(string $pattern): bool => preg_match($pattern, $name) === 1
        );
        if ($matchingSuffix !== null) {
            $name = preg_replace($matchingSuffix, '', $name) ?? $name;
        }

        if (str_ends_with($name, ' -')) {
            $name = rtrim(substr($name, 0, -2));
        }

        $separatorPosition = strpos($name, ' - ');

        if ($separatorPosition !== false) {
            $prefix = substr($name, 0, $separatorPosition);

            if (!str_contains($prefix, ':')) {
                $name = substr_replace($name, ': ', $separatorPosition, 3);
            }
        }

        $name = rtrim($name);

        if ($name !== '') {
            $name = rtrim($name, '.');
        }

        return trim($name);
    }

    public function toApaTitleCase(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            return $title;
        }

        $lowercaseWords = [
            // Articles
            'a',
            'an',
            'the',
            // Coordinating conjunctions
            'and',
            'but',
            'or',
            'nor',
            'for',
            // Short prepositions (three letters or fewer)
            'as',
            'at',
            'by',
            'in',
            'of',
            'on',
            'per',
            'to',
            'via',
            'off',
            // Other permitted lowercase forms
            'vs',
            'vs.',
        ];

        $words = preg_split('/\s+/u', $title);

        if ($words === false) {
            return $title;
        }

        $wordCount = count($words);
        $convertedWords = [];
        $capitalizeNext = false;

        for ($index = 0; $index < $wordCount; $index++) {
            $word = $words[$index];

            if ($word === '') {
                $convertedWords[] = '';
                continue;
            }

            $leadingPunctuation = '';
            $trailingPunctuation = '';
            $coreWord = $word;

            if (preg_match('/^([\\"\'"“‘\(\[{]*)(.*?)([\\"\'"”’\)\]}.,:;!?]*)$/u', $word, $matches) === 1) {
                $leadingPunctuation = $matches[1];
                $coreWord = $matches[2];
                $trailingPunctuation = $matches[3];
            }

            if ($coreWord === '') {
                $convertedWords[] = $word;
                $capitalizeNext = $this->shouldCapitalizeAfterPunctuation($word);
                continue;
            }

            $isFirstWord = $index === 0;
            $isLastWord = $index === $wordCount - 1;
            $forceCapitalize = $capitalizeNext || $isFirstWord || $isLastWord;

            $processedCore = $this->formatTitleWord($coreWord, $forceCapitalize, $lowercaseWords);

            $convertedWords[] = $leadingPunctuation . $processedCore . $trailingPunctuation;

            $capitalizeNext = $this->shouldCapitalizeAfterPunctuation($trailingPunctuation);
        }

        return implode(' ', $convertedWords);
    }

    /**
     * @param list<string> $lowercaseWords
     */
    private function formatTitleWord(string $word, bool $forceCapitalize, array $lowercaseWords): string
    {
        if (str_contains($word, '-')) {
            $segments = explode('-', $word);

            foreach ($segments as $key => $segment) {
                $segments[$key] = $this->formatTitleWord($segment, true, $lowercaseWords);
            }

            return implode('-', $segments);
        }

        $wordLower = mb_strtolower($word, 'UTF-8');

        if (!$forceCapitalize && in_array($wordLower, $lowercaseWords, true)) {
            return $wordLower;
        }

        if ($this->shouldPreserveTitleWord($word)) {
            return $word;
        }

        return $this->uppercaseFirstCharacter($wordLower);
    }

    private function shouldPreserveTitleWord(string $word): bool
    {
        if ($word === '') {
            return true;
        }

        $acronyms = [
            'VR',
            'HD',
        ];

        if (in_array($word, $acronyms, true)) {
            return true;
        }

        if (preg_match('/\d/', $word) === 1) {
            return true;
        }

        if (str_contains($word, '.')) {
            return true;
        }

        if (str_contains($word, '&')) {
            return true;
        }

        $romanNumeral = mb_strtoupper($word, 'UTF-8');

        if (preg_match('/^(?=[IVXLCDM]+$)M{0,4}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/', $romanNumeral) === 1) {
            return true;
        }

        $lower = mb_strtolower($word, 'UTF-8');
        $upper = mb_strtoupper($word, 'UTF-8');

        return $word !== $lower && $word !== $upper;
    }

    private function uppercaseFirstCharacter(string $word): string
    {
        return mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
    }

    private function shouldCapitalizeAfterPunctuation(string $punctuation): bool
    {
        if ($punctuation === '') {
            return false;
        }

        return array_any(
            [':', '!', '?', '.'],
            static fn (string $character): bool => str_contains($punctuation, $character)
        );
    }
}
