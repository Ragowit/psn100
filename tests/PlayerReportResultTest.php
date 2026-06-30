<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerReportResult.php';

final class PlayerReportResultTest extends TestCase
{
    public function testSuccessResultContainsMessageAndIsSuccessful(): void
    {
        $result = PlayerReportResult::success('All good');

        $this->assertTrue($result->hasMessage());
        $this->assertTrue($result->isSuccess());
        $this->assertSame('All good', $result->getMessage());
    }

    public function testErrorResultContainsMessageAndIsNotSuccessful(): void
    {
        $result = PlayerReportResult::error('Something went wrong');

        $this->assertTrue($result->hasMessage());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('Something went wrong', $result->getMessage());
    }

    public function testEmptyResultDoesNotHaveMessageAndIsNotSuccessful(): void
    {
        $result = PlayerReportResult::empty();

        $this->assertFalse($result->hasMessage());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('', $result->getMessage());
        $this->assertSame('', $result->getEscapedMessage());
    }

    public function testGetEscapedMessageEscapesHtmlEntities(): void
    {
        $result = PlayerReportResult::error('<script>alert(1)</script>');

        $this->assertSame('<script>alert(1)</script>', $result->getMessage());
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $result->getEscapedMessage());
    }
}
