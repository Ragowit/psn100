<?php

declare(strict_types=1);

class TrophyRepository
{
    private \PDO $database;

    public function __construct(\PDO $database)
    {
        $this->database = $database;
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

        $query = $this->database->prepare('SELECT id FROM trophy WHERE id = :id');
        $query->bindValue(':id', $id, \PDO::PARAM_INT);
        $query->execute();
        $result = $query->fetchColumn();

        if ($result === false) {
            return null;
        }

        return (int) $result;
    }
}
