<?php

declare(strict_types=1);

require_once __DIR__ . '/../Dto/PsnProfileDto.php';

final class PsnProfileMapper
{
    public function mapLookupResponse(mixed $lookupResponse): ?PsnProfileDto
    {
        $normalized = $this->normalizeLookupResponse($lookupResponse);
        $profile = $normalized['profile'] ?? null;

        if (!is_array($profile)) {
            return null;
        }

        $accountId = (string) ($profile['accountId'] ?? '');
        if ($accountId === '') {
            return null;
        }

        return new PsnProfileDto(
            $accountId,
            (string) ($profile['onlineId'] ?? ''),
            (string) ($profile['currentOnlineId'] ?? ''),
            (string) ($profile['npId'] ?? '')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeLookupResponse(mixed $profile): array
    {
        if (is_array($profile)) {
            return $profile;
        }

        if (is_object($profile)) {
            try {
                $encoded = json_encode($profile, JSON_THROW_ON_ERROR);
                $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (JsonException) {
                // Fall back to exposing public properties.
            }

            return get_object_vars($profile);
        }

        return [];
    }
}
