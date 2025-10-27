<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerReportRequest.php';

final class PlayerReportRequestTest extends TestCase
{
    public function testFromArraysTrimsExplanationAndCapturesIpAddress(): void
    {
        $queryParameters = ['explanation' => '  Needs review  '];
        $serverParameters = ['REMOTE_ADDR' => '198.51.100.24'];

        $request = PlayerReportRequest::fromArrays($queryParameters, $serverParameters);

        $this->assertSame('Needs review', $request->getExplanation());
        $this->assertTrue($request->wasExplanationSubmitted());
        $this->assertSame('198.51.100.24', $request->getIpAddress());
    }

    public function testFromArraysHandlesMissingExplanationAndInvalidIp(): void
    {
        $request = PlayerReportRequest::fromArrays([], ['REMOTE_ADDR' => 'invalid']);

        $this->assertSame('', $request->getExplanation());
        $this->assertFalse($request->wasExplanationSubmitted());
        $this->assertSame('', $request->getIpAddress());
    }

    public function testFromArraysSanitizesNonScalarExplanationValues(): void
    {
        $queryParameters = ['explanation' => ['value', 'other']];
        $serverParameters = ['REMOTE_ADDR' => new class {
            public function __toString(): string
            {
                return '203.0.113.12';
            }
        }];

        $request = PlayerReportRequest::fromArrays($queryParameters, $serverParameters);

        $this->assertSame('', $request->getExplanation());
        $this->assertTrue($request->wasExplanationSubmitted());
        $this->assertSame('203.0.113.12', $request->getIpAddress());
    }
}
