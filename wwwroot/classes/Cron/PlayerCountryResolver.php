<?php

declare(strict_types=1);

use Tustin\PlayStation\Client;

/**
 * Resolves and persists player country codes during PSN profile scans.
 *
 * Encapsulates npId decoding, stored-country lookup, live PSN search, and
 * country persistence that were previously embedded in PlayerScanProfileSynchronizer.
 */
final class PlayerCountryResolver
{
    public function __construct(
        private readonly PDO $database,
    ) {
    }

    public function extractCountryFromNpId(mixed $npId): ?string
    {
        if (!is_string($npId) || $npId === '') {
            return null;
        }

        $decoded = base64_decode($npId, true);
        if ($decoded === false || $decoded === '') {
            return null;
        }

        $trimmed = trim($decoded);
        if ($trimmed === '') {
            return null;
        }

        if (strlen($trimmed) < 2) {
            return null;
        }

        return strtolower(substr($trimmed, -2));
    }

    public function resolveCountry(
        Client $client,
        string $accountId,
        string $onlineId,
        ?string $countryFromNpId = null,
    ): string {
        $country = $countryFromNpId;

        if ($country === null || strtolower($country) === 'zz') {
            $storedCountry = $this->fetchStoredCountryByAccountId($accountId);

            if (is_string($storedCountry) && $storedCountry !== '') {
                $country = $storedCountry;
            } else {
                $country = 'zz';
            }

            if (strtolower($country) === 'zz') {
                $resolvedCountry = $this->findPlayerCountry($client, $onlineId);

                if ($resolvedCountry !== null) {
                    $country = $resolvedCountry;
                    $this->updatePlayerCountry($accountId, $resolvedCountry);
                }
            }
        } else {
            $this->updatePlayerCountry($accountId, $country);
        }

        if (!is_string($country) || $country === '') {
            return 'zz';
        }

        return $country;
    }

    public function fetchStoredCountryByAccountId(string $accountId): ?string
    {
        $query = $this->database->prepare('SELECT country FROM player WHERE account_id = :account_id');
        $query->bindValue(':account_id', $accountId, PDO::PARAM_STR);
        $query->execute();

        $country = $query->fetchColumn();

        if (!is_string($country) || $country === '') {
            return null;
        }

        return $country;
    }

    public function findPlayerCountry(Client $client, string $onlineId): ?string
    {
        $normalizedOnlineId = strtolower($onlineId);
        $userCounter = 0;

        try {
            foreach ($client->users()->search($onlineId) as $result) {
                if (strtolower($result->onlineId()) === $normalizedOnlineId) {
                    $country = $result->country();

                    if (!is_string($country) || $country === '') {
                        return null;
                    }

                    $normalizedCountry = strtolower($country);

                    if ($normalizedCountry === 'zz') {
                        return null;
                    }

                    return $normalizedCountry;
                }

                $userCounter++;

                if ($userCounter >= 50) {
                    break;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    public function updatePlayerCountry(string $accountId, string $country): void
    {
        $query = $this->database->prepare(
            'UPDATE player SET country = :country WHERE account_id = :account_id'
        );
        $query->bindValue(':country', strtolower($country), PDO::PARAM_STR);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_STR);
        $query->execute();
    }
}
