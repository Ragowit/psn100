-- Insert a colon after Arcade/Console Archives series prefixes for existing titles.
-- Safe to re-run: rows that already include the colon are skipped.
--
-- Examples:
--   Arcade Archives 2 Ace Driver  -> Arcade Archives 2: Ace Driver
--   Arcade Archives Ace Driver    -> Arcade Archives: Ace Driver
--   Console Archives Cool Boarders -> Console Archives: Cool Boarders

UPDATE trophy_title
SET name = REGEXP_REPLACE(name, '^(Arcade Archives 2)[[:space:]]+', '$1: ', 1, 0, 'i')
WHERE REGEXP_LIKE(name, '^Arcade Archives 2[[:space:]]+', 'i')
  AND NOT REGEXP_LIKE(name, '^Arcade Archives 2[[:space:]]*:', 'i');

UPDATE trophy_title
SET name = REGEXP_REPLACE(name, '^(Arcade Archives)[[:space:]]+', '$1: ', 1, 0, 'i')
WHERE REGEXP_LIKE(name, '^Arcade Archives[[:space:]]+', 'i')
  AND NOT REGEXP_LIKE(name, '^Arcade Archives[[:space:]]*:', 'i')
  AND NOT REGEXP_LIKE(name, '^Arcade Archives 2[[:space:]]', 'i');

UPDATE trophy_title
SET name = REGEXP_REPLACE(name, '^(Console Archives)[[:space:]]+', '$1: ', 1, 0, 'i')
WHERE REGEXP_LIKE(name, '^Console Archives[[:space:]]+', 'i')
  AND NOT REGEXP_LIKE(name, '^Console Archives[[:space:]]*:', 'i');
