<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/RequestParameter.php';

final class RequestParameterTest extends TestCase
{
    public function testFirstScalarReturnsScalarValue(): void
    {
        $this->assertSame('player', RequestParameter::firstScalar('player'));
    }

    public function testFirstScalarReturnsFirstArrayElement(): void
    {
        $this->assertSame('first', RequestParameter::firstScalar(['first', 'second']));
    }

    public function testFirstScalarReturnsNullForEmptyArray(): void
    {
        $this->assertSame(null, RequestParameter::firstScalar([]));
    }

    public function testLastScalarReturnsLastArrayElement(): void
    {
        $this->assertSame('second', RequestParameter::lastScalar(['first', 'second']));
    }

    public function testLastScalarReturnsNullForEmptyArray(): void
    {
        $this->assertSame(null, RequestParameter::lastScalar([]));
    }
}
