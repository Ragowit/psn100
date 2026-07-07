<?php

declare(strict_types=1);

/**
 * Resolves HTTP status codes and retry policy from PlayStation API exceptions.
 *
 * Shared by PSN lookup services and player scan profile synchronization.
 */
final class PsnHttpExceptionClassifier
{
    public static function determineStatusCode(Throwable $exception): ?int
    {
        $response = self::findResponse($exception);

        if ($response !== null) {
            $statusCode = self::extractStatusCodeFromResponse($response);

            if ($statusCode !== null) {
                return $statusCode;
            }
        }

        return self::extractStatusCodeFromThrowable($exception);
    }

    public static function shouldRetryWithDifferentServiceName(Throwable $exception): bool
    {
        $statusCode = self::determineStatusCode($exception);

        if ($statusCode === 400 || $statusCode === 403 || $statusCode === 404) {
            return true;
        }

        if ($statusCode !== null) {
            return false;
        }

        return self::isRetryableKnownHttpException($exception);
    }

    public static function isRetryableKnownHttpException(Throwable $exception): bool
    {
        $retryableExceptionClasses = [
            'Tustin\\Haste\\Exception\\ApiException',
            'Tustin\\Haste\\Exception\\AccessDeniedHttpException',
            'Tustin\\Haste\\Exception\\NotFoundHttpException',
        ];

        foreach ($retryableExceptionClasses as $retryableExceptionClass) {
            if ($exception instanceof $retryableExceptionClass) {
                return true;
            }
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            return self::isRetryableKnownHttpException($previous);
        }

        return false;
    }

    public static function findResponse(Throwable $exception): ?object
    {
        if (method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();

            if (is_object($response)) {
                return $response;
            }
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            return self::findResponse($previous);
        }

        return null;
    }

    public static function extractStatusCodeFromResponse(object $response): ?int
    {
        if (method_exists($response, 'getStatusCode')) {
            $statusCode = $response->getStatusCode();

            if (is_int($statusCode)) {
                return $statusCode;
            }
        }

        if (method_exists($response, 'getStatus')) {
            $status = $response->getStatus();

            if (is_int($status)) {
                return $status;
            }
        }

        return null;
    }

    public static function extractStatusCodeFromThrowable(Throwable $exception): ?int
    {
        $code = $exception->getCode();

        if (is_int($code) && $code > 0) {
            return $code;
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            return self::extractStatusCodeFromThrowable($previous);
        }

        return null;
    }
}
