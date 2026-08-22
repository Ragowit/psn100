-- Add an index for GameHeaderService::fetchReplacedTitles().
--
-- obsolete_ids remains the editable CSV field used by the admin UI. The generated
-- JSON array lets MySQL maintain a multi-valued index over each obsolete title ID.
-- Safe to re-run on databases which have already applied either change.

SET @add_obsolete_ids_json := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `trophy_title_meta` ADD COLUMN `obsolete_ids_json` JSON GENERATED ALWAYS AS (CAST(CONCAT(''['', `obsolete_ids`, '']'') AS JSON)) STORED AFTER `obsolete_ids`',
        'DO 0'
    )
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'trophy_title_meta'
      AND column_name = 'obsolete_ids_json'
);

PREPARE add_obsolete_ids_json_stmt FROM @add_obsolete_ids_json;
EXECUTE add_obsolete_ids_json_stmt;
DEALLOCATE PREPARE add_obsolete_ids_json_stmt;

SET @add_obsolete_ids_index := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `trophy_title_meta` ADD INDEX `idx_ttm_obsolete_ids` ((CAST(`obsolete_ids_json` AS UNSIGNED ARRAY)))',
        'DO 0'
    )
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'trophy_title_meta'
      AND index_name = 'idx_ttm_obsolete_ids'
);

PREPARE add_obsolete_ids_index_stmt FROM @add_obsolete_ids_index;
EXECUTE add_obsolete_ids_index_stmt;
DEALLOCATE PREPARE add_obsolete_ids_index_stmt;
