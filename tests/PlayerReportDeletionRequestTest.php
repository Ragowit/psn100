<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PlayerReportDeletionRequest.php';

final class PlayerReportDeletionRequestTest extends TestCase
{
    public function testFromPostDataParsesDeleteId(): void
    {
        $request = PlayerReportDeletionRequest::fromPostData(['delete_id' => '7']);

        $this->assertTrue($request->isValidDeletion());
        $this->assertSame(7, $request->getDeleteId());
        $this->assertFalse($request->hasError());
    }

    public function testFromPostDataReturnsErrorWhenDeleteIdInvalid(): void
    {
        $request = PlayerReportDeletionRequest::fromPostData(['delete_id' => 'abc']);

        $this->assertTrue($request->hasError());
        $this->assertSame('Please provide a valid report ID to delete.', $request->getErrorMessage());
        $this->assertFalse($request->isValidDeletion());
    }

    public function testFromPostDataReturnsNoDeletionWhenKeyMissing(): void
    {
        $request = PlayerReportDeletionRequest::fromPostData([]);

        $this->assertFalse($request->hasDeletionRequest());
        $this->assertFalse($request->isValidDeletion());
    }
}
