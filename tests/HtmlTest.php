<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Html.php';

final class HtmlTest extends TestCase
{
    public function testEscapeUsesHtmlspecialcharsWithUtf8(): void
    {
        $this->assertSame('&lt;script&gt;', Html::escape('<script>'));
    }
}
