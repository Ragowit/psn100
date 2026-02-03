<?php

declare(strict_types=1);

class GameRepository
{
    public function __construct(private readonly \PDO $database)
    {
    }

    public function findIdFromSegment(?string $segment): ?int
    {
        if ($segment === null || $segment === '') {
            return null;
        }

        $parts = explode('-', $segment);
        $id = (int) $parts[0];

        if ($id <= 0) {
            return null;
        }

        return $this->findId($id);
    }

    public function findId(int $id): ?int
    {
        if ($id <= 0) {
            return null;
        }

        $query = $this->database->prepare('SELECT id FROM trophy_title WHERE id = :id');
        $query->bindValue(':id', $id, \PDO::PARAM_INT);
        $query->execute();
        $result = $query->fetchColumn();

        if ($result === false) {
            return null;
        }

        return (int) $result;
    }
}
