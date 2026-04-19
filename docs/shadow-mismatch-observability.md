# Shadow mismatch observability

`psn_shadow_mismatch` events are emitted as JSON log lines from `ShadowExecutionUtility`, so they continue to flow through the existing log ingestion pipeline that already consumes application `error_log` output.

## Required dashboard widgets

Build dashboard widgets from JSON fields below:

- mismatch rate by `service` + `operation`
- comparison quality from `comparisonMetrics.totalCompared`, `comparisonMetrics.matched`, `comparisonMetrics.mismatched`, `comparisonMetrics.skippedNormalizationFailure`, `comparisonMetrics.newClientErrors`
- identifier coverage using `identifiers.onlineId`, `identifiers.accountId`, `identifiers.npCommunicationId`
- top changed field paths from `diffSummary.changedPaths`
- rate-limited versus sampled volume from `sampling.sampleRate` and `sampling.rateLimitPerMinute`

## Example queries

### Mismatch-rate by service and operation

```sql
SELECT
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.service')) AS service,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.operation')) AS operation,
  COUNT(*) AS mismatch_events,
  ROUND(COUNT(*) / NULLIF(SUM(COUNT(*)) OVER (), 0), 4) AS event_share
FROM observability_logs
WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'psn_shadow_mismatch'
  AND timestamp >= NOW() - INTERVAL 24 HOUR
GROUP BY service, operation
ORDER BY mismatch_events DESC;
```

### Promotion-policy rollup counters (1h window)

```sql
SELECT
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.service')) AS service,
  JSON_UNQUOTE(JSON_EXTRACT(payload, '$.operation')) AS operation,
  SUM(JSON_EXTRACT(payload, '$.comparisonMetrics.totalCompared')) AS total_compared,
  SUM(JSON_EXTRACT(payload, '$.comparisonMetrics.matched')) AS matched,
  SUM(JSON_EXTRACT(payload, '$.comparisonMetrics.mismatched')) AS mismatched,
  SUM(JSON_EXTRACT(payload, '$.comparisonMetrics.skippedNormalizationFailure')) AS normalization_skips,
  SUM(JSON_EXTRACT(payload, '$.comparisonMetrics.newClientErrors')) AS new_client_errors
FROM observability_logs
WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'psn_shadow_comparison_result'
  AND timestamp >= NOW() - INTERVAL 1 HOUR
GROUP BY service, operation
ORDER BY total_compared DESC;
```

### Identifier coverage

```sql
SELECT
  SUM(JSON_EXTRACT(payload, '$.identifiers.onlineId') IS NOT NULL) AS online_id_present,
  SUM(JSON_EXTRACT(payload, '$.identifiers.accountId') IS NOT NULL) AS account_id_present,
  SUM(JSON_EXTRACT(payload, '$.identifiers.npCommunicationId') IS NOT NULL) AS np_communication_id_present,
  COUNT(*) AS total
FROM observability_logs
WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'psn_shadow_mismatch'
  AND timestamp >= NOW() - INTERVAL 24 HOUR;
```

### High-frequency mismatch paths

```sql
SELECT
  path,
  COUNT(*) AS occurrences
FROM observability_logs,
JSON_TABLE(
  JSON_EXTRACT(payload, '$.diffSummary.changedPaths'),
  '$[*]' COLUMNS (path VARCHAR(255) PATH '$')
) AS paths
WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'psn_shadow_mismatch'
  AND timestamp >= NOW() - INTERVAL 24 HOUR
GROUP BY path
ORDER BY occurrences DESC
LIMIT 25;
```
