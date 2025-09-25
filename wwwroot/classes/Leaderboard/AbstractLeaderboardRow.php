<?php

declare(strict_types=1);

require_once __DIR__ . '/../PlayerLeaderboardFilter.php';
require_once __DIR__ . '/../Utility.php';

abstract class AbstractLeaderboardRow
{
    private const NEW_PLAYER_RANK_VALUE = 16777215;

    /**
     * @var array<string, mixed>
     */
    protected array $player;

    private PlayerLeaderboardFilter $filter;

    private Utility $utility;

    private ?string $highlightedPlayerId;

    /**
     * @var array<string, int|string>
     */
    private array $filterParameters;

    private string $rankingField;

    private string $rankingLastWeekField;

    private string $countryRankingField;

    private string $countryRankingLastWeekField;

    /**
     * @param array<string, mixed> $player
     * @param array<string, int|string> $filterParameters
     */
    public function __construct(
        array $player,
        PlayerLeaderboardFilter $filter,
        Utility $utility,
        ?string $highlightedPlayerId,
        array $filterParameters,
        string $rankingField,
        string $rankingLastWeekField,
        string $countryRankingField,
        string $countryRankingLastWeekField
    ) {
        $this->player = $player;
        $this->filter = $filter;
        $this->utility = $utility;
        $this->highlightedPlayerId = $highlightedPlayerId !== null ? trim($highlightedPlayerId) : null;
        $this->filterParameters = $filterParameters;
        $this->rankingField = $rankingField;
        $this->rankingLastWeekField = $rankingLastWeekField;
        $this->countryRankingField = $countryRankingField;
        $this->countryRankingLastWeekField = $countryRankingLastWeekField;
    }

    public function getRowId(): string
    {
        return (string) ($this->player['online_id'] ?? '');
    }

    public function getRowCssClass(): string
    {
        return $this->isHighlighted() ? 'table-primary' : '';
    }

    public function getOnlineId(): string
    {
        return (string) ($this->player['online_id'] ?? '');
    }

    public function getAvatarUrl(): string
    {
        return (string) ($this->player['avatar_url'] ?? '');
    }

    public function getCountryCode(): string
    {
        return (string) ($this->player['country'] ?? '');
    }

    public function getCountryName(): string
    {
        return $this->utility->getCountryName($this->getCountryCode());
    }

    /**
     * @return array<string, int|string>
     */
    public function getAvatarQueryParameters(): array
    {
        $parameters = $this->filterParameters;
        $parameters['avatar'] = $this->getAvatarUrl();

        return $parameters;
    }

    /**
     * @return array<string, int|string>
     */
    public function getCountryQueryParameters(): array
    {
        $parameters = $this->filterParameters;
        $parameters['country'] = $this->getCountryCode();

        return $parameters;
    }

    public function getLevel(): int
    {
        return (int) ($this->player['level'] ?? 0);
    }

    public function getProgress(): int
    {
        return (int) ($this->player['progress'] ?? 0);
    }

    public function getRankCellHtml(): string
    {
        if ($this->filter->hasCountry()) {
            return $this->renderRankCell(
                (int) ($this->player[$this->countryRankingField] ?? 0),
                (int) ($this->player[$this->countryRankingLastWeekField] ?? 0)
            );
        }

        return $this->renderRankCell(
            (int) ($this->player[$this->rankingField] ?? 0),
            (int) ($this->player[$this->rankingLastWeekField] ?? 0)
        );
    }

    public function hasHiddenTrophies(): bool
    {
        return (int) ($this->player['trophy_count_npwr'] ?? 0) < (int) ($this->player['trophy_count_sony'] ?? 0);
    }

    public function getHiddenIndicatorHtml(): string
    {
        if (!$this->hasHiddenTrophies()) {
            return '';
        }

        return " <span style='color: #9d9d9d;'>(H)</span>";
    }

    protected function getInt(string $key): int
    {
        return (int) ($this->player[$key] ?? 0);
    }

    private function renderRankCell(int $currentRank, int $previousRank): string
    {
        if ($previousRank === 0 || $previousRank === self::NEW_PLAYER_RANK_VALUE) {
            return 'New!' . $this->getHiddenIndicatorHtml();
        }

        $delta = $previousRank - $currentRank;
        $parts = ["<div class='vstack'>"];

        if ($delta > 0) {
            $parts[] = "<span style='color: #0bd413; cursor: default;' title='+" . $delta . "'>&#9650;</span>";
        }

        $parts[] = (string) $currentRank . $this->getHiddenIndicatorHtml();

        if ($delta < 0) {
            $parts[] = "<span style='color: #d40b0b; cursor: default;' title='" . $delta . "'>&#9660;</span>";
        }

        $parts[] = '</div>';

        return implode('', $parts);
    }

    private function isHighlighted(): bool
    {
        $onlineId = $this->getOnlineId();

        if ($onlineId === '') {
            return false;
        }

        return $this->highlightedPlayerId !== null && strcasecmp($this->highlightedPlayerId, $onlineId) === 0;
    }
}
