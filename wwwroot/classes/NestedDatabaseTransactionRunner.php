<?php

declare(strict_types=1);

/**
 * Runs callables inside a single PDO transaction, tracking logical nesting depth so
 * inner execute() calls reuse the outer database transaction.
 *
 * Extracted from TrophyMergeService, which nests transactions when merge steps call
 * each other through shared helpers.
 */
final class NestedDatabaseTransactionRunner
{
    private int $transactionDepth = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function execute(callable $operation): void
    {
        $this->beginTransaction();

        try {
            $operation();
            $this->commitTransaction();
        } catch (Throwable $exception) {
            $this->rollBackTransaction();
            throw $exception;
        }
    }

    private function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->database->beginTransaction();
        }

        $this->transactionDepth++;
    }

    private function commitTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }

        if ($this->transactionDepth === 1) {
            if ($this->database->inTransaction()) {
                $this->database->commit();
            }

            $this->transactionDepth = 0;

            return;
        }

        $this->transactionDepth--;
    }

    private function rollBackTransaction(): void
    {
        $this->transactionDepth = 0;

        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }
}
