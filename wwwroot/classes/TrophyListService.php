<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyListItem.php';

class TrophyListService
{
    public const PAGE_SIZE = 50;

    public function __construct(private readonly PDO $database)
    {
    }

    public function countTrophies(): int
    {
        $query = $this->database->prepare(
            'SELECT COUNT(*)
            FROM trophy t
            JOIN trophy_meta tm ON tm.trophy_id = t.id
            JOIN trophy_title tt USING (np_communication_id)
            JOIN trophy_title_meta ttm USING (np_communication_id)
            WHERE tm.status = 0 AND ttm.status = 0'
        );

        $query->execute();
        $total = $query->fetchColumn();

        return $total === false ? 0 : (int) $total;
    }

    /**
     * @return TrophyListItem[]
     */
    public function getTrophies(int $offset, int $limit = self::PAGE_SIZE): array
    {
        $offset = max($offset, 0);
        $limit = max($limit, 1);

        $query = $this->database->prepare(
            'SELECT
                t.id AS trophy_id,
                t.type AS trophy_type,
                t.name AS trophy_name,
                t.detail AS trophy_detail,
                t.icon_url AS trophy_icon,
                tm.rarity_percent,
                tm.in_game_rarity_percent,
                t.progress_target_value,
                t.reward_name,
                t.reward_image_url,
                tt.id AS game_id,
                tt.name AS game_name,
                tt.icon_url AS game_icon,
                tt.platform
            FROM trophy t
            JOIN trophy_meta tm ON tm.trophy_id = t.id
            JOIN trophy_title tt USING(np_communication_id)
            JOIN trophy_title_meta ttm USING (np_communication_id)
            WHERE tm.status = 0 AND ttm.status = 0
            ORDER BY tm.rarity_percent DESC
            LIMIT :offset, :limit'
        );

        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        /** @var array<int, array<string, mixed>> $trophies */
        $trophies = $query->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            static fn (array $trophy): TrophyListItem => TrophyListItem::fromArray($trophy),
            $trophies
        );
    }
}
