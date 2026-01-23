-- Recalculate trophy_title_player progress for merged titles.
-- Run during a maintenance window.

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
SELECT
    parent_scores.parent_np_communication_id,
    parent_scores.account_id,
    parent_scores.bronze,
    parent_scores.silver,
    parent_scores.gold,
    parent_scores.platinum,
    CASE
        WHEN title_info.max_score = 0 THEN 0
        WHEN parent_scores.score = 0 THEN 0
        ELSE IFNULL(
            GREATEST(
                FLOOR(
                    IF(
                        (parent_scores.score / title_info.max_score) * 100 = 100
                            AND title_info.platinum = 1
                            AND parent_scores.platinum = 0,
                        99,
                        (parent_scores.score / title_info.max_score) * 100
                    )
                ),
                1
            ),
            0
        )
    END AS progress,
    child_updates.last_updated_date
FROM parent_scores
JOIN child_updates
    ON child_updates.parent_np_communication_id = parent_scores.parent_np_communication_id
    AND child_updates.account_id = parent_scores.account_id
JOIN title_info
    ON title_info.np_communication_id = parent_scores.parent_np_communication_id
ON DUPLICATE KEY UPDATE
    bronze = VALUES(bronze),
    silver = VALUES(silver),
    gold = VALUES(gold),
    platinum = VALUES(platinum),
    progress = VALUES(progress),
    last_updated_date = IF(
        VALUES(last_updated_date) > trophy_title_player.last_updated_date,
        VALUES(last_updated_date),
        trophy_title_player.last_updated_date
    );

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
