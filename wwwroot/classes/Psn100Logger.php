<?php

declare(strict_types=1);

final class Psn100Logger
{
    public function __construct(private readonly PDO $database)
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
