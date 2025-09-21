<?php

declare(strict_types=1);

require_once __DIR__ . '/ReportedPlayer.php';

class PlayerReportAdminService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function deleteReportById(int $reportId): void
    {
        $query = $this->database->prepare(
            'DELETE FROM player_report WHERE report_id = :report_id'
        );
        $query->bindValue(':report_id', $reportId, PDO::PARAM_INT);
        $query->execute();
    }

    /**
     * @return ReportedPlayer[]
     */
    public function getReportedPlayers(): array
    {
        $query = $this->database->prepare(
            'SELECT pr.report_id, p.online_id, pr.explanation
            FROM player_report pr
            JOIN player p USING (account_id)
            ORDER BY pr.report_id'
        );
        $query->execute();

        $reportedPlayers = [];
        while (($row = $query->fetch(PDO::FETCH_ASSOC)) !== false) {
            $reportId = isset($row['report_id']) ? (int) $row['report_id'] : 0;
            $onlineId = isset($row['online_id']) ? (string) $row['online_id'] : '';
            $explanation = isset($row['explanation']) ? (string) $row['explanation'] : '';

            $reportedPlayers[] = new ReportedPlayer($reportId, $onlineId, $explanation);
        }

        return $reportedPlayers;
    }
}
