<?php

declare(strict_types=1);

final class Psn100Logger
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
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
