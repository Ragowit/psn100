<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/RequestUriPath.php';

final class RequestUriPathTest extends TestCase
{
    public function testFromUriReturnsAsciiPathUnchanged(): void
    {
        $this->assertSame(
            '/game/123-example',
            RequestUriPath::fromUri('/game/123-example')
        );
    }

    public function testFromUriPercentEncodesDecodedUnicodeBeforeParsing(): void
    {
        $path = '/game/1163-collar-' . "\u{00D7}" . '-malice-unlimited';

        $this->assertSame(
            '/game/1163-collar-%C3%97-malice-unlimited',
            RequestUriPath::fromUri($path)
        );
    }

    public function testFromUriPreservesAlreadyEncodedUnicode(): void
    {
        $this->assertSame(
            '/game/1163-collar-%C3%97-malice-unlimited',
            RequestUriPath::fromUri('/game/1163-collar-%C3%97-malice-unlimited')
        );
    }
}
