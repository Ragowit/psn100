<?php

declare(strict_types=1);

require_once __DIR__ . '/LogEntry.php';
require_once __DIR__ . '/LogEntryFormatter.php';

final class LogService
{
    private PDO $database;

    private LogEntryFormatter $formatter;

    private ?string $logTable = null;

    public function __construct(PDO $database, LogEntryFormatter $formatter)
    {
        $this->database = $database;
        $this->formatter = $formatter;
    }

    /**
     * @return list<LogEntry>
     */
    public function fetchEntriesForPage(int $page, int $perPage): array
    {
        $normalizedPerPage = max(1, $perPage);
        $offset = max(0, ($page - 1) * $normalizedPerPage);

        $table = $this->getLogTable();
        $queryString = sprintf(
            'SELECT id, time, message FROM %s ORDER BY id ASC LIMIT :limit OFFSET :offset',
            $this->quoteIdentifier($table)
        );

        try {
            $statement = $this->database->prepare($queryString);
        } catch (PDOException $exception) {
            return [];
        }

        $statement->bindValue(':limit', $normalizedPerPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);

        try {
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            return [];
        }

        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $entries = [];

        foreach ($rows as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $time = $this->createDateTime($row['time'] ?? null);
            $message = isset($row['message']) ? (string) $row['message'] : '';
            $formattedMessage = $this->formatter->format($message);

            $entries[] = new LogEntry($id, $time, $formattedMessage);
        }

        return $entries;
    }

    public function countEntries(): int
    {
        $table = $this->getLogTable();
        $queryString = sprintf('SELECT COUNT(*) FROM %s', $this->quoteIdentifier($table));

        try {
            $statement = $this->database->prepare($queryString);
        } catch (PDOException $exception) {
            return 0;
        }

        try {
            $statement->execute();
            $count = $statement->fetchColumn();
        } catch (PDOException $exception) {
            return 0;
        }

        return (int) $count;
    }

    public function deleteLogById(int $logId): bool
    {
        return $this->deleteLogsByIds([$logId]) > 0;
    }

    /**
     * @param iterable<int|string> $logIds
     */
    public function deleteLogsByIds(iterable $logIds): int
    {
        $uniqueIds = [];

        foreach ($logIds as $logId) {
            if (is_int($logId)) {
                $normalizedId = $logId;
            } elseif (is_string($logId)) {
                $trimmed = trim($logId);

                if ($trimmed === '' || !ctype_digit($trimmed)) {
                    continue;
                }

                $normalizedId = (int) $trimmed;
            } else {
                continue;
            }

            if ($normalizedId <= 0) {
                continue;
            }

            $uniqueIds[$normalizedId] = $normalizedId;
        }

        if ($uniqueIds === []) {
            return 0;
        }

        $ids = array_values($uniqueIds);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $table = $this->getLogTable();
        $queryString = sprintf(
            'DELETE FROM %s WHERE id IN (%s)',
            $this->quoteIdentifier($table),
            $placeholders
        );

        try {
            $statement = $this->database->prepare($queryString);
        } catch (PDOException $exception) {
            return 0;
        }

        foreach ($ids as $index => $id) {
            $statement->bindValue($index + 1, $id, PDO::PARAM_INT);
        }

        try {
            $statement->execute();
        } catch (PDOException $exception) {
            return 0;
        }

        return (int) $statement->rowCount();
    }

    private function createDateTime(?string $value): DateTimeImmutable
    {
        $trimmedValue = trim((string) ($value ?? ''));

        if ($trimmedValue === '') {
            return (new DateTimeImmutable('@0'))->setTimezone(new DateTimeZone('UTC'));
        }

        try {
            return (new DateTimeImmutable($trimmedValue, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            return (new DateTimeImmutable('@0'))->setTimezone(new DateTimeZone('UTC'));
        }
    }

    private function getLogTable(): string
    {
        if ($this->logTable === null) {
            $this->logTable = 'log';
        }

        return $this->logTable;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
