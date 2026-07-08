<?php

declare(strict_types=1);

require_once __DIR__ . '/Html.php';

final class GameMessageSanitizer
{
    public static function sanitize(string $message): string
    {
        if ($message === '') {
            return '';
        }

        $pattern = '/<a\b([^>]*)>(.*?)<\/a>/is';
        $offset = 0;
        $sanitized = '';

        while (preg_match($pattern, $message, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $matchText = $matches[0][0];
            $matchPosition = $matches[0][1];

            $sanitized .= self::sanitizePlainText(substr($message, $offset, $matchPosition - $offset));

            $attributes = $matches[1][0];
            $linkContent = $matches[2][0];
            $href = self::extractHref($attributes);
            $validatedUrl = $href !== null ? filter_var($href, FILTER_VALIDATE_URL) : false;

            if ($validatedUrl !== false && preg_match('/^https?:\/\//i', (string) $validatedUrl) === 1) {
                $targetAttributes = self::hasTargetBlank($attributes)
                    ? ' target="_blank" rel="noopener noreferrer"'
                    : '';

                $sanitized .= sprintf(
                    '<a href="%s"%s>%s</a>',
                    Html::escape((string) $validatedUrl),
                    $targetAttributes,
                    self::sanitizeLinkText($linkContent)
                );
            } else {
                $sanitized .= Html::escape($matchText);
            }

            $offset = $matchPosition + strlen($matchText);
        }

        $sanitized .= self::sanitizePlainText(substr($message, $offset));

        return $sanitized;
    }

    public static function escapeTextareaContent(string $message): string
    {
        return preg_replace('/<\/textarea/i', '&lt;/textarea', $message) ?? $message;
    }

    private static function sanitizePlainText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $pattern = '/<br\s*\/?>/i';
        $offset = 0;
        $sanitized = '';

        while (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $matchText = $matches[0][0];
            $matchPosition = $matches[0][1];

            $sanitized .= Html::escape(substr($text, $offset, $matchPosition - $offset));
            $sanitized .= '<br>';
            $offset = $matchPosition + strlen($matchText);
        }

        $sanitized .= Html::escape(substr($text, $offset));

        return $sanitized;
    }

    private static function extractHref(string $attributes): ?string
    {
        if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/is', $attributes, $matches) !== 1) {
            return null;
        }

        $href = trim($matches[2]);

        if ($href === '') {
            return null;
        }

        return self::decodeHtmlEntities($href);
    }

    private static function hasTargetBlank(string $attributes): bool
    {
        return preg_match('/\btarget\s*=\s*(["\'])?_blank\1/i', $attributes) === 1;
    }

    private static function sanitizeLinkText(string $content): string
    {
        return Html::escape(self::decodeHtmlEntities(strip_tags($content)));
    }

    private static function decodeHtmlEntities(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
