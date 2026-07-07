<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PsnHttpExceptionClassifier.php';

if (!class_exists('Tustin\\Haste\\Exception\\NotFoundHttpException')) {
    eval('namespace Tustin\\Haste\\Exception; final class NotFoundHttpException extends \RuntimeException {}');
}

final class PsnHttpExceptionClassifierTest extends TestCase
{
    public function testDetermineStatusCodeReadsStatusFromResponse(): void
    {
        $exception = new ClassifierStubHttpException(
            new ClassifierStubResponse(404)
        );

        $this->assertSame(404, PsnHttpExceptionClassifier::determineStatusCode($exception));
    }

    public function testDetermineStatusCodeFallsBackToExceptionCode(): void
    {
        $exception = new RuntimeException('Forbidden', 403);

        $this->assertSame(403, PsnHttpExceptionClassifier::determineStatusCode($exception));
    }

    public function testDetermineStatusCodeWalksPreviousExceptions(): void
    {
        $inner = new RuntimeException('Not found', 404);
        $outer = new RuntimeException('Wrapped', 0, $inner);

        $this->assertSame(404, PsnHttpExceptionClassifier::determineStatusCode($outer));
    }

    public function testShouldRetryWithDifferentServiceNameForClientErrors(): void
    {
        $this->assertTrue(
            PsnHttpExceptionClassifier::shouldRetryWithDifferentServiceName(
                new RuntimeException('Bad request', 400)
            )
        );
        $this->assertTrue(
            PsnHttpExceptionClassifier::shouldRetryWithDifferentServiceName(
                new RuntimeException('Forbidden', 403)
            )
        );
        $this->assertTrue(
            PsnHttpExceptionClassifier::shouldRetryWithDifferentServiceName(
                new RuntimeException('Not found', 404)
            )
        );
    }

    public function testShouldNotRetryWithDifferentServiceNameForOtherStatusCodes(): void
    {
        $this->assertFalse(
            PsnHttpExceptionClassifier::shouldRetryWithDifferentServiceName(
                new RuntimeException('Server error', 500)
            )
        );
    }

    public function testShouldRetryWithDifferentServiceNameForKnownHttpExceptionTypes(): void
    {
        $this->assertTrue(
            PsnHttpExceptionClassifier::shouldRetryWithDifferentServiceName(
                new \Tustin\Haste\Exception\NotFoundHttpException('Not found')
            )
        );
    }

    public function testIsRetryableKnownHttpExceptionWalksPreviousExceptions(): void
    {
        $inner = new \Tustin\Haste\Exception\NotFoundHttpException('Not found');
        $outer = new RuntimeException('Wrapped', 0, $inner);

        $this->assertTrue(PsnHttpExceptionClassifier::isRetryableKnownHttpException($outer));
    }
}

final class ClassifierStubResponse
{
    public function __construct(private readonly int $statusCode)
    {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

final class ClassifierStubHttpException extends RuntimeException
{
    public function __construct(private readonly ClassifierStubResponse $response)
    {
        parent::__construct('HTTP error');
    }

    public function getResponse(): ClassifierStubResponse
    {
        return $this->response;
    }
}
