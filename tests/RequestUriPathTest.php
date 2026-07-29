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

    public function testFromUriStripsQueryAndFragment(): void
    {
        $this->assertSame(
            '/player/example',
            RequestUriPath::fromUri('/player/example?tab=log#latest')
        );
    }

    public function testFromUriReturnsRootForEmptyInput(): void
    {
        $this->assertSame('/', RequestUriPath::fromUri(''));
        $this->assertSame('/', RequestUriPath::fromUri('/'));
    }

    public function testFromUriReturnsEmptyStringForMalformedAbsoluteUri(): void
    {
        $this->assertSame('', RequestUriPath::fromUri('http://['));
    }

    public function testFromUriPercentEncodesSpaces(): void
    {
        $this->assertSame('/a%20b', RequestUriPath::fromUri('/a b'));
    }
}
