<?php

declare(strict_types=1);

/**
 * Resolves admin trophy-status form input into trophy IDs.
 */
final class TrophyStatusInputParser
{
    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * @return int[]
     */
    public function parseTrophyIds(string $input): array
    {
        $values = preg_split('/[\s,]+/', trim($input));
        $ids = [];

        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (!ctype_digit($value)) {
                throw new InvalidArgumentException('Invalid trophy ID: ' . $value);
            }

            $ids[] = (int) $value;
        }

        $ids = $ids
            |> array_unique(...)
            |> array_values(...);

        if (count($ids) === 0) {
            throw new InvalidArgumentException('No trophies were provided.');
        }

        return $ids;
    }

    /**
     * @return int[]
     */
    public function getTrophyIdsForGame(int $gameId): array
    {
        $query = $this->database->prepare(
            <<<'SQL'
            SELECT t.id
            FROM trophy t
            INNER JOIN trophy_title tt ON tt.np_communication_id = t.np_communication_id
            WHERE tt.id = :id
            SQL
        );
        $query->bindValue(':id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $trophies = $query->fetchAll(PDO::FETCH_COLUMN);

        if ($trophies === false || count($trophies) === 0) {
            throw new InvalidArgumentException('No trophies found for the selected game.');
        }

        return $trophies
            |> (fn(array $ids): array => array_map(intval(...), $ids))
            |> array_unique(...)
            |> array_values(...);
    }
}
