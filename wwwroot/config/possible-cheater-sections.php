<?php

declare(strict_types=1);

require_once __DIR__ . '/../classes/PlayerStatus.php';

$flaggedStatus = PlayerStatus::FLAGGED->value;

return [
        [
            'title' => 'FUEL',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, fuel_start.earned_date, fuel_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned fuel_start ON
                    fuel_start.account_id = ttp.account_id
                    AND fuel_start.np_communication_id = ttp.np_communication_id
                    AND fuel_start.order_id = 33
                    AND fuel_start.earned = 1
                JOIN trophy_earned fuel_end ON
                    fuel_end.account_id = ttp.account_id
                    AND fuel_end.np_communication_id = 'NPWR00481_00'
                    AND fuel_end.order_id = 34
                    AND fuel_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR00481_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4390-fuel/%s?sort=date',
        ],
        [
            'title' => 'SOCOM: U.S. NAVY SEALS CONFRONTATION',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, socom_start.earned_date, socom_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned socom_start ON
                    socom_start.account_id = ttp.account_id
                    AND socom_start.np_communication_id = ttp.np_communication_id
                    AND socom_start.order_id = 32
                    AND socom_start.earned = 1
                JOIN trophy_earned socom_end ON
                    socom_end.account_id = ttp.account_id
                    AND socom_end.np_communication_id = 'NPWR00302_00'
                    AND socom_end.order_id = 33
                    AND socom_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR00302_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4233-socom-us-navy-seals-confrontation/%s?sort=date',
        ],
        [
            'title' => 'Resonance of Fate (Lap Two Complete < A New Beginning)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 38
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR01103_00'
                    AND trophy_end.order_id = 48
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR01103_00'
                HAVING
                    time_difference <= 0
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/2704-resonance-of-fate/%s?sort=date',
        ],
        [
            'title' => 'End of Eternity (2周目クリア < 2周目突入)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 38
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR00987_00'
                    AND trophy_end.order_id = 48
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR00987_00'
                HAVING
                    time_difference <= 0
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/5703-end-of-eternity/%s?sort=date',
        ],
        [
            'title' => 'Catherine: Full Body',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 50
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR17582_00'
                    AND trophy_end.order_id = 51
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR17582_00'
                HAVING
                    time_difference <= 0
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4556-catherine-full-body/%s',
        ],
        [
            'title' => '凱薩琳FULL BODY',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 50
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR17415_00'
                    AND trophy_end.order_id = 51
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR17415_00'
                HAVING
                    time_difference <= 0
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/7556-kai-sa-linfull-body/%s',
        ],
        [
            'title' => 'キャサリン・フルボディ',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 50
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR14836_00'
                    AND trophy_end.order_id = 51
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR14836_00'
                HAVING
                    time_difference <= 0
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/6489-kyasarinfurubodi/%s',
        ],
        [
            'title' => 'Lost Planet 2 (200-Chapter Playback <-> 300-Chapter Playback)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 10
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR00928_00'
                    AND trophy_end.order_id = 11
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR00928_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4237-lost-planet-2/%s?sort=date',
        ],
        [
            'title' => 'Lost Planet 2 (Snow Pirate Leader <-> Snow Pirate Commander)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 19
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR00928_00'
                    AND trophy_end.order_id = 20
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR00928_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4237-lost-planet-2/%s?sort=date',
        ],
        [
            'title' => 'Resident Evil: Revelations [PS4] (Bonus Legend <-> Bonus Demi-god)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, rer_start.earned_date, rer_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned rer_start ON
                    rer_start.account_id = ttp.account_id
                    AND rer_start.np_communication_id = ttp.np_communication_id
                    AND rer_start.order_id = 54
                    AND rer_start.earned = 1
                JOIN trophy_earned rer_end ON
                    rer_end.account_id = ttp.account_id
                    AND rer_end.np_communication_id = 'NPWR11777_00'
                    AND rer_end.order_id = 55
                    AND rer_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR11777_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4663-resident-evil-revelations/%s?sort=date',
        ],
        [
            'title' => 'Resident Evil: Revelations [PS4] (Meteoric Rise <-> Top of My Game)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, rer_start.earned_date, rer_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned rer_start ON
                    rer_start.account_id = ttp.account_id
                    AND rer_start.np_communication_id = ttp.np_communication_id
                    AND rer_start.order_id = 37
                    AND rer_start.earned = 1
                JOIN trophy_earned rer_end ON
                    rer_end.account_id = ttp.account_id
                    AND rer_end.np_communication_id = 'NPWR11777_00'
                    AND rer_end.order_id = 38
                    AND rer_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR11777_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4663-resident-evil-revelations/%s?sort=date',
        ],
        [
            'title' => 'Resident Evil: Revelations [PS3] (Bonus Legend <-> Bonus Demi-god)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, rer_start.earned_date, rer_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned rer_start ON
                    rer_start.account_id = ttp.account_id
                    AND rer_start.np_communication_id = ttp.np_communication_id
                    AND rer_start.order_id = 49
                    AND rer_start.earned = 1
                JOIN trophy_earned rer_end ON
                    rer_end.account_id = ttp.account_id
                    AND rer_end.np_communication_id = 'NPWR03903_00'
                    AND rer_end.order_id = 50
                    AND rer_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR03903_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3804-resident-evil-revelations/%s?sort=date',
        ],
        [
            'title' => 'Resident Evil: Revelations [PS3] (Meteoric Rise <-> Top of My Game)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, rer_start.earned_date, rer_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned rer_start ON
                    rer_start.account_id = ttp.account_id
                    AND rer_start.np_communication_id = ttp.np_communication_id
                    AND rer_start.order_id = 36
                    AND rer_start.earned = 1
                JOIN trophy_earned rer_end ON
                    rer_end.account_id = ttp.account_id
                    AND rer_end.np_communication_id = 'NPWR03903_00'
                    AND rer_end.order_id = 37
                    AND rer_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR03903_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3804-resident-evil-revelations/%s?sort=date',
        ],
        [
            'title' => 'Angry Birds Trilogy [PS3] (Block Breaker <-> Block Annihilator)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, abt_start.earned_date, abt_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned abt_start ON
                    abt_start.account_id = ttp.account_id
                    AND abt_start.np_communication_id = ttp.np_communication_id
                    AND abt_start.order_id = 30
                    AND abt_start.earned = 1
                JOIN trophy_earned abt_end ON
                    abt_end.account_id = ttp.account_id
                    AND abt_end.np_communication_id = 'NPWR03771_00'
                    AND abt_end.order_id = 31
                    AND abt_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR03771_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3810-angry-birds-trilogy/%s?sort=date',
        ],
        [
            'title' => 'Terminator Salvation',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    trophy_counter.trophy_count
                FROM (
                    SELECT
                        ttp.account_id,
                        COUNT(*) AS trophy_count
                    FROM
                        trophy_title_player ttp
                    INNER JOIN trophy_earned marker ON
                        marker.account_id = ttp.account_id
                        AND marker.np_communication_id = ttp.np_communication_id
                        AND marker.order_id = 9
                        AND marker.earned = 1
                    INNER JOIN trophy_earned te ON
                        te.account_id = ttp.account_id
                        AND te.np_communication_id = ttp.np_communication_id
                        AND te.order_id != 9
                        AND te.earned = 1
                        AND te.earned_date >= marker.earned_date
                    WHERE
                        ttp.np_communication_id = 'NPWR00623_00'
                    GROUP BY
                        ttp.account_id
                    HAVING
                        trophy_count >= 9
                ) trophy_counter
                INNER JOIN player p ON
                    p.account_id = trophy_counter.account_id
                    AND p.status != {$flaggedStatus}
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/294-terminator-salvation/%s?sort=date',
        ],
        [
            'title' => 'F1 Race Stars',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 3
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR03734_00'
                    AND trophy_end.order_id = 4
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR03734_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4866-f1-race-stars/%s?sort=date',
        ],
        [
            'title' => 'Mega Man: Legacy Collection',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 6
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR09098_00'
                    AND trophy_end.order_id = 7
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR09098_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/179-mega-man-legacy-collection/%s?sort=date',
        ],
        [
            'title' => 'Batman: Arkham Asylum',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 31
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR00626_00'
                    AND trophy_end.order_id = 32
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR00626_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/333-batman-arkham-asylum/%s?sort=date',
        ],
        [
            'title' => 'Batman: Arkham Asylum (JP)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 31
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR01012_00'
                    AND trophy_end.order_id = 32
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR01012_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3131-batman-arkham-asylum/%s?sort=date',
        ],
        [
            'title' => 'Dead Space',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 19
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR00464_00'
                    AND trophy_end.order_id = 20
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR00464_00'
                HAVING
                    time_difference <= 60
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3200-dead-space/%s?sort=date',
        ],
        [
            'title' => 'Street Fighter X Tekken [PSVITA] (Transcend All You Know <-> Your Legend Will Never Die)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 36
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR03139_00'
                    AND trophy_end.order_id = 37
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR03139_00'
                HAVING
                    time_difference <= 600
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3474-street-fighter-x-tekken/%s?sort=date',
        ],
        [
            'title' => 'Street Fighter X Tekken [PS3] (Transcend All You Know <-> Your Legend Will Never Die)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 38
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR01781_00'
                    AND trophy_end.order_id = 39
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR01781_00'
                HAVING
                    time_difference <= 600
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/4253-street-fighter-x-tekken/%s?sort=date',
        ],
        [
            'title' => 'Fat Princess',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    ABS(TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date)) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 0
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR00737_00'
                    AND trophy_end.order_id = 26
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR00737_00'
                HAVING
                    time_difference <= 300
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/279-fat-princess/%s?sort=date',
        ],
        [
            'title' => 'Code Vein (Determiner of Fate <-> Heirs)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 2
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR14318_00'
                    AND trophy_end.order_id = 39
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR14318_00'
                HAVING
                    time_difference >= 10
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3243-code-vein/%s?sort=date',
        ],
        [
            'title' => 'Code Vein (Determiner of Fate <-> To Eternity)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 2
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR14318_00'
                    AND trophy_end.order_id = 40
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR14318_00'
                HAVING
                    time_difference >= 10
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3243-code-vein/%s?sort=date',
        ],
        [
            'title' => 'Code Vein (Determiner of Fate <-> Dweller in the Dark)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 2
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR14318_00'
                    AND trophy_end.order_id = 41
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR14318_00'
                HAVING
                    time_difference >= 10
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/3243-code-vein/%s?sort=date',
        ],
        [
            'title' => 'Final Fantasy X-2 HD Remaster (Giant Tower <-> Almost There)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 34
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR05019_00'
                    AND trophy_end.order_id = 32
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR05019_00'
                HAVING
                    time_difference >= 10
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/2424-final-fantasy-x2-hd-remaster/%s?sort=date',
        ],
        [
            'title' => 'Pic-a-Pix Color 2 [VITA, EU] (Casual Puzzler <-> Casual Completionist)',
            'query' => <<<SQL
                SELECT
                    p.account_id,
                    p.online_id,
                    TIMESTAMPDIFF(SECOND, trophy_start.earned_date, trophy_end.earned_date) AS time_difference
                FROM
                    trophy_title_player ttp
                JOIN trophy_earned trophy_start ON
                    trophy_start.account_id = ttp.account_id
                    AND trophy_start.np_communication_id = ttp.np_communication_id
                    AND trophy_start.order_id = 1
                    AND trophy_start.earned = 1
                JOIN trophy_earned trophy_end ON
                    trophy_end.account_id = ttp.account_id
                    AND trophy_end.np_communication_id = 'NPWR18592_00'
                    AND trophy_end.order_id = 4
                    AND trophy_end.earned = 1
                JOIN player p ON
                    p.account_id = ttp.account_id
                    AND p.status != {$flaggedStatus}
                WHERE
                    ttp.np_communication_id = 'NPWR18592_00'
                HAVING
                    time_difference <= 10
                ORDER BY
                    p.online_id
            SQL,
            'linkPattern' => '/game/5992-picapix-color-2/%s?sort=date',
        ],
];
