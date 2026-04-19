# Shadow → New promotion runbook

This runbook defines objective promotion gates for moving PSN integrations from `shadow` to `new`.

## 1) Configure thresholds

Configure thresholds in `wwwroot/config/app.php` under:

- `psn.shadow_promotion_policy.thresholds.default`
- optional service/operation overrides in `psn.shadow_promotion_policy.thresholds.services`

Threshold keys:

- `maxMismatchRate` (per window, e.g. `1h`, `24h`, `7d`)
- `minCompared` (minimum `totalCompared` volume per window)
- `maxNewClientErrorRate` (new-client failures per compared request)
- `maxNormalizationSkipRate` (normalization failures per compared request)

## 2) Build and monitor comparison metrics

`ShadowExecutionUtility` now emits `psn_shadow_comparison_result` with these counters:

- `comparisonMetrics.totalCompared`
- `comparisonMetrics.matched`
- `comparisonMetrics.mismatched`
- `comparisonMetrics.skippedNormalizationFailure`
- `comparisonMetrics.newClientErrors`

Aggregate these counters by `service` + `operation` over rolling windows (1h/24h/7d).

## 3) Enter shadow for a target service

1. Keep global `PSN_CLIENT_MODE=legacy` (or current production mode).
2. Set a per-service override (via config or `PSN_CLIENT_MODE_OVERRIDES_JSON`) for the target service to `shadow`.
3. Verify traffic starts emitting `psn_shadow_comparison_result` and (when applicable) `psn_shadow_mismatch` events.

## 4) Promote to new when thresholds pass

1. Evaluate the last 1h, 24h, and 7d windows for the target service/operation.
2. Confirm all configured thresholds pass simultaneously.
3. Change the service override from `shadow` to `new`.
4. Keep monitoring comparison/mismatch dashboards for at least one additional 24h period.

## 5) Roll back quickly on regression

If mismatch rate or new-client errors regress:

1. Set the affected service override to `legacy` immediately.
2. If regression is broad, temporarily revert global mode to `legacy`.
3. Capture top changed paths (`diffSummary.changedPaths`) and failure signatures for triage.
4. Re-enter `shadow` only after fix validation.

## Progressive rollout pattern (recommended)

Use per-service overrides to promote incrementally:

- `psn_player_lookup`: `shadow` → `new`
- `psn_game_lookup`: stay `shadow` until stable
- `psn_worker_login`: stay `legacy` until dependencies are validated

This avoids requiring a global cutover and reduces blast radius.
