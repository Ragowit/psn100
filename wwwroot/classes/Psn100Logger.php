<?php

declare(strict_types=1);

final readonly class Psn100Logger
{
    public function __construct(final private PDO $database)
    {
    }

    public function log(string $message): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO log (message) VALUES (:message)'
        );
        $statement->bindValue(':message', $message, PDO::PARAM_STR);
        $statement->execute();
    }
}
