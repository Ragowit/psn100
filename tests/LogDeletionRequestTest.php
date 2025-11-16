<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/LogDeletionRequest.php';

final class LogDeletionRequestTest extends TestCase
{
    public function testFromPostDataParsesSingleDeletionId(): void
    {
        $request = LogDeletionRequest::fromPostData(['delete_id' => '42']);

        $this->assertTrue($request->isSingleDeletion());
        $this->assertSame(42, $request->getSingleDeletionId());
        $this->assertFalse($request->hasError());
    }

    public function testFromPostDataReturnsErrorWhenSingleDeletionIdInvalid(): void
    {
        $request = LogDeletionRequest::fromPostData(['delete_id' => 'foo']);

        $this->assertTrue($request->hasError());
        $this->assertSame('Please provide a valid log entry ID to delete.', $request->getErrorMessage());
        $this->assertFalse($request->isSingleDeletion());
    }

    public function testFromPostDataParsesBulkDeletionIds(): void
    {
        $request = LogDeletionRequest::fromPostData([
            'delete_selected' => '1',
            'delete_ids' => ['3', '2', '2', 'invalid', ' 5 '],
        ]);

        $this->assertTrue($request->isBulkDeletion());
        $this->assertSame([2, 3, 5], $request->getBulkDeletionIds());
        $this->assertFalse($request->hasError());
    }

    public function testFromPostDataReturnsErrorWhenBulkDeletionIdsMissing(): void
    {
        $request = LogDeletionRequest::fromPostData(['delete_selected' => '1', 'delete_ids' => []]);

        $this->assertTrue($request->hasError());
        $this->assertSame('Please select at least one log entry to delete.', $request->getErrorMessage());
        $this->assertFalse($request->isBulkDeletion());
    }
}
