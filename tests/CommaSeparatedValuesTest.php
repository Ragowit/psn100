<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/CommaSeparatedValues.php';

final class CommaSeparatedValuesTest extends TestCase
{
    public function testParseTrimmedReturnsEmptyListForEmptyString(): void
    {
        $this->assertSame([], CommaSeparatedValues::parseTrimmed(''));
    }

    public function testParseTrimmedTrimsAndFiltersEmptySegments(): void
    {
        $this->assertSame(['PS4', 'PS5'], CommaSeparatedValues::parseTrimmed(' PS4 , , PS5 '));
    }

    public function testParseUppercaseTrimmedNormalizesCase(): void
    {
        $this->assertSame(['PS4', 'PS5'], CommaSeparatedValues::parseUppercaseTrimmed('ps4, ps5'));
    }
}
