<?php

declare(strict_types=1);

require_once __DIR__ . '/PsnGameLookupException.php';

/**
 * Normalizes PSN trophy API payloads and validates npCommunicationId integrity.
 */
final class PsnTrophyApiPayloadInspector
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(mixed $response): array
    {
        if (is_array($response)) {
            return $response;
        }

        if (is_object($response)) {
            try {
                $encoded = json_encode($response, JSON_THROW_ON_ERROR);
                $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (JsonException) {
            }

            return get_object_vars($response);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    public function extractNpCommunicationIds(array $payload): array
    {
        $detected = [];
        $seen = [];

        $this->addNpCommunicationIdCandidates(
            $this->extractNpCommunicationIdFromArray($payload),
            $detected,
            $seen
        );

        $this->addNpCommunicationIdCandidates(
            $this->extractNpCommunicationIdFromTrophies($payload['trophies'] ?? null),
            $detected,
            $seen
        );

        $trophyGroups = $payload['trophyGroups'] ?? null;
        if (!is_array($trophyGroups)) {
            return $detected;
        }

        foreach ($trophyGroups as $trophyGroup) {
            if (!is_array($trophyGroup)) {
                continue;
            }

            $this->addNpCommunicationIdCandidates(
                $this->extractNpCommunicationIdFromArray($trophyGroup),
                $detected,
                $seen
            );

            $this->addNpCommunicationIdCandidates(
                $this->extractNpCommunicationIdFromTrophies($trophyGroup['trophies'] ?? null),
                $detected,
                $seen
            );
        }

        return $detected;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function assertMatchesRequested(string $requested, array $payload, string $endpointLabel): void
    {
        $detected = $this->extractNpCommunicationIds($payload);
        if ($detected === []) {
            return;
        }

        $normalizedRequested = $requested |> trim(...) |> strtoupper(...);
        foreach ($detected as $normalizedDetected) {
            if ($normalizedDetected === $normalizedRequested) {
                continue;
            }

            throw new PsnGameLookupException(sprintf(
                'PSN response integrity check failed for endpoint "%s": requested npCommunicationId "%s" but received "%s".',
                $endpointLabel,
                $normalizedRequested,
                $normalizedDetected
            ));
        }
    }

    private function extractNpCommunicationIdFromTrophies(mixed $trophies): array
    {
        if (!is_array($trophies) || $trophies === []) {
            return [];
        }

        $detected = [];
        $seen = [];

        foreach ($trophies as $trophy) {
            if (!is_array($trophy)) {
                continue;
            }

            $this->addNpCommunicationIdCandidates(
                $this->extractNpCommunicationIdFromArray($trophy),
                $detected,
                $seen
            );

            $trophyIconUrl = $trophy['trophyIconUrl'] ?? null;
            if (!is_string($trophyIconUrl) || trim($trophyIconUrl) === '') {
                continue;
            }

            if (preg_match('/\/(NP[A-Z0-9]{2}[0-9]{5}_[0-9]{2})_/i', $trophyIconUrl, $matches) !== 1) {
                continue;
            }

            $this->addNpCommunicationIdCandidates($matches[1], $detected, $seen);
        }

        return $detected;
    }

    /**
     * @param list<string>|string|null $candidates
     * @param list<string>             $detected
     * @param array<string, true>      $seen
     */
    private function addNpCommunicationIdCandidates(array|string|null $candidates, array &$detected, array &$seen): void
    {
        if ($candidates === null) {
            return;
        }

        if (is_string($candidates)) {
            $candidates = [$candidates];
        }

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $normalizedCandidate = $candidate |> trim(...) |> strtoupper(...);
            if (isset($seen[$normalizedCandidate])) {
                continue;
            }

            $detected[] = $normalizedCandidate;
            $seen[$normalizedCandidate] = true;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractNpCommunicationIdFromArray(array $payload): ?string
    {
        $topLevelCandidateKeys = ['npCommunicationId', 'np_communication_id'];
        foreach ($topLevelCandidateKeys as $candidateKey) {
            $candidate = $payload[$candidateKey] ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
