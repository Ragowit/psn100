<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/GameMessageSanitizer.php';

final class GameMessageSanitizerTest extends TestCase
{
    public function testSanitizeAllowsSafeAnchorTags(): void
    {
        $message = 'See <a href="https://example.com/path">the guide</a> for details.';

        $this->assertSame(
            'See <a href="https://example.com/path">the guide</a> for details.',
            GameMessageSanitizer::sanitize($message)
        );
    }

    public function testSanitizePreservesTargetBlankOnSafeLinks(): void
    {
        $message = '<a href="https://example.com" target="_blank">External link</a>';

        $this->assertSame(
            '<a href="https://example.com" target="_blank" rel="noopener noreferrer">External link</a>',
            GameMessageSanitizer::sanitize($message)
        );
    }

    public function testSanitizeEscapesPlainText(): void
    {
        $message = 'Use caution &amp; avoid <script>alert(1)</script>.';

        $this->assertSame(
            'Use caution &amp;amp; avoid &lt;script&gt;alert(1)&lt;/script&gt;.',
            GameMessageSanitizer::sanitize($message)
        );
    }

    public function testSanitizeRejectsUnsafeHrefSchemes(): void
    {
        $message = '<a href="javascript:alert(1)">Click me</a>';

        $this->assertSame(
            '&lt;a href=&quot;javascript:alert(1)&quot;&gt;Click me&lt;/a&gt;',
            GameMessageSanitizer::sanitize($message)
        );
    }

    public function testSanitizeStripsNestedMarkupInsideAnchorText(): void
    {
        $message = '<a href="https://example.com"><img src=x onerror=alert(1)>Guide</a>';

        $this->assertSame(
            '<a href="https://example.com">Guide</a>',
            GameMessageSanitizer::sanitize($message)
        );
    }

    public function testEscapeTextareaContentPreventsBreakout(): void
    {
        $message = 'Hello</textarea><script>alert(1)</script>';

        $this->assertSame(
            'Hello&lt;/textarea><script>alert(1)</script>',
            GameMessageSanitizer::escapeTextareaContent($message)
        );
    }
}
