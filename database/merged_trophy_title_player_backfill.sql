-- Recalculate trophy_title_player progress for merged titles.
-- Run during a maintenance window.

INSERT INTO trophy_title_player (
    np_communication_id,
    account_id,
    bronze,
    silver,
    gold,
    platinum,
    progress,
    last_updated_date
)
WITH merged_parents AS (
    SELECT DISTINCT parent_np_communication_id
    FROM trophy_merge
),
child_updates AS (
    SELECT
        tm.parent_np_communication_id,
        ttp.account_id,
        MAX(ttp.last_updated_date) AS last_updated_date
    FROM trophy_merge tm
    JOIN trophy_title_player ttp
        ON ttp.np_communication_id = tm.child_np_communication_id
    GROUP BY tm.parent_np_communication_id, ttp.account_id
),
parent_scores AS (
    SELECT
        tgp.np_communication_id AS parent_np_communication_id,
        tgp.account_id,
        SUM(tgp.bronze) AS bronze,
        SUM(tgp.silver) AS silver,
        SUM(tgp.gold) AS gold,
        SUM(tgp.platinum) AS platinum,
        SUM(tgp.bronze) * 15 + SUM(tgp.silver) * 30 + SUM(tgp.gold) * 90 AS score
    FROM trophy_group_player tgp
    WHERE tgp.np_communication_id IN (SELECT parent_np_communication_id FROM merged_parents)
    GROUP BY tgp.np_communication_id, tgp.account_id
),
title_info AS (
    SELECT
        np_communication_id,
        platinum,
        bronze * 15 + silver * 30 + gold * 90 AS max_score
    FROM trophy_title
)
SELECT
    src.np_communication_id,
    src.account_id,
    src.bronze,
    src.silver,
    src.gold,
    src.platinum,
    src.progress,
    src.last_updated_date
FROM (
    SELECT
        ps.parent_np_communication_id AS np_communication_id,
        ps.account_id,
        ps.bronze,
        ps.silver,
        ps.gold,
        ps.platinum,
        CASE
            WHEN ti.max_score = 0 THEN 0
            WHEN ps.score = 0 THEN 0
            ELSE IFNULL(
                GREATEST(
                    FLOOR(
                        IF(
                            (ps.score / ti.max_score) * 100 = 100
                                AND ti.platinum = 1
                                AND ps.platinum = 0,
                            99,
                            (ps.score / ti.max_score) * 100
                        )
                    ),
                    1
                ),
                0
            )
        END AS progress,
        cu.last_updated_date
    FROM parent_scores ps
    JOIN child_updates cu
        ON cu.parent_np_communication_id = ps.parent_np_communication_id
        AND cu.account_id = ps.account_id
    JOIN title_info ti
        ON ti.np_communication_id = ps.parent_np_communication_id
) AS src
ON DUPLICATE KEY UPDATE
    bronze = src.bronze,
    silver = src.silver,
    gold = src.gold,
    platinum = src.platinum,
    progress = src.progress,
    last_updated_date = GREATEST(trophy_title_player.last_updated_date, src.last_updated_date);


INSERT IGNORE INTO trophy_title_player (
    np_communication_id,
    account_id,
    bronze,
    silver,
    gold,
    platinum,
    progress,
    last_updated_date
)
WITH empty_players AS (
    SELECT
        tm.parent_np_communication_id,
        ttp.account_id,
        MAX(ttp.last_updated_date) AS last_updated_date,
        SUM(ttp.bronze + ttp.silver + ttp.gold + ttp.platinum) AS trophy_total
    FROM trophy_merge tm
    JOIN trophy_title_player ttp
        ON ttp.np_communication_id = tm.child_np_communication_id
    GROUP BY tm.parent_np_communication_id, ttp.account_id
    HAVING trophy_total = 0
)
SELECT
    empty_players.parent_np_communication_id,
    empty_players.account_id,
    0,
    0,
    0,
    0,
    0,
    empty_players.last_updated_date
FROM empty_players;
