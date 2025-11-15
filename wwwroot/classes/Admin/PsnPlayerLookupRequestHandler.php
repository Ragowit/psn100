<?php

declare(strict_types=1);

require_once __DIR__ . '/PsnPlayerLookupService.php';
require_once __DIR__ . '/PsnPlayerLookupException.php';
require_once __DIR__ . '/PsnPlayerLookupRequestResult.php';

final class PsnPlayerLookupRequestHandler
{
    public static function handle(PsnPlayerLookupService $lookupService, string $onlineId): PsnPlayerLookupRequestResult
    {
        $normalizedOnlineId = trim($onlineId);
        $result = null;
        $errorMessage = null;
        $decodedNpId = null;
        $npCountry = null;

        if ($normalizedOnlineId !== '') {
            try {
                $result = $lookupService->lookup($normalizedOnlineId);
                [$decodedNpId, $npCountry] = self::extractNpIdMetadata($result);
            } catch (PsnPlayerLookupException $exception) {
                $errorMessage = $exception->getMessage();
            } catch (Throwable $exception) {
                $message = trim($exception->getMessage());

                if ($message === '') {
                    $message = 'An unexpected error occurred while looking up the player. Please try again later.';
                }

                $errorMessage = $message;
            }
        }

        return new PsnPlayerLookupRequestResult(
            $normalizedOnlineId,
            $result,
            $errorMessage,
            $decodedNpId,
            $npCountry
        );
    }

    /**
     * @param array<string, mixed>|null $result
     * @return array{0: ?string, 1: ?string}
     */
    private static function extractNpIdMetadata(?array $result): array
    {
        if (!is_array($result)) {
            return [null, null];
        }

        $npId = $result['profile']['npId'] ?? null;

        if (!is_string($npId) || $npId === '') {
            return [null, null];
        }

        $decoded = base64_decode($npId, true);

        if ($decoded === false || $decoded === '') {
            return [null, null];
        }

        $trimmed = trim($decoded);

        if ($trimmed === '') {
            return [null, null];
        }

        $country = null;

        if (strlen($trimmed) >= 2) {
            $country = strtoupper(substr($trimmed, -2));
        }

        return [$trimmed, $country];
    }
}
