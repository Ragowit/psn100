<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/Utility.php';

class PlayerHeaderViewModel
{
    /**
     * @var array<string, mixed>
     */
    private array $player;

    private PlayerSummary $playerSummary;

    private Utility $utility;

    /**
     * @param array<string, mixed> $player
     */
    public function __construct(array $player, PlayerSummary $playerSummary, Utility $utility)
    {
        $this->player = $player;
        $this->playerSummary = $playerSummary;
        $this->utility = $utility;
    }

    public function getAboutMe(): string
    {
        $aboutMe = (string) ($this->player['about_me'] ?? '');

        $escapedAboutMe = htmlentities($aboutMe, ENT_QUOTES, 'UTF-8');

        return str_replace(["\r\n", "\r", "\n"], '&#10;', $escapedAboutMe);
    }

    public function getCountryName(): string
    {
        return $this->utility->getCountryName($this->player['country'] ?? null);
    }

    public function getTotalTrophies(): int
    {
        return (int) ($this->player['bronze'] ?? 0)
            + (int) ($this->player['silver'] ?? 0)
            + (int) ($this->player['gold'] ?? 0)
            + (int) ($this->player['platinum'] ?? 0);
    }

    public function getNumberOfGames(): int
    {
        return $this->playerSummary->getNumberOfGames();
    }

    public function getNumberOfCompletedGames(): int
    {
        return $this->playerSummary->getNumberOfCompletedGames();
    }

    public function getAverageProgress(): ?float
    {
        $averageProgress = $this->playerSummary->getAverageProgress();

        return $averageProgress !== null ? (float) $averageProgress : null;
    }

    public function getUnearnedTrophies(): int
    {
        return $this->playerSummary->getUnearnedTrophies();
    }

    public function canShowPlayerStats(): bool
    {
        $status = $this->getStatus();

        return $status !== 1 && $status !== 3;
    }

    /**
     * @return string[]
     */
    public function getAlerts(): array
    {
        $alerts = [];
        $status = $this->getStatus();
        $onlineId = $this->getOnlineId();
        $accountId = $this->getAccountId();

        switch ($status) {
            case 1:
                $alerts[] = sprintf(
                    "This player has some funny looking trophy data. This doesn't necessarily mean cheating, but all data from this player will be excluded from site statistics and leaderboards. <a href=\"https://github.com/Ragowit/psn100/issues?q=label%%3Acheater+%s+OR+%d\">Dispute</a>?",
                    rawurlencode($onlineId),
                    $accountId
                );
                break;
            case 3:
                $alerts[] = "This player seems to have a <a class=\"link-underline link-underline-opacity-0 link-underline-opacity-100-hover\" href=\"https://www.playstation.com/en-us/support/account/privacy-settings-psn/\">private</a> profile. Make sure this player is no longer private, and then issue a new scan of the profile on the front page.";
                break;
            case 4:
                $alerts[] = 'This player has not played a game in over a year and is considered inactive by this site. All data from this player will be excluded from site statistics and leaderboards.';
                break;
            case 5:
                $alerts[] = 'This player seems to no longer be available from Sony, maybe removed for some reason. We will recheck after 24h and if this player is still not available it will be removed from here as well.';
                break;
            case 99:
                $alerts[] = 'This is a new player currently being scanned for the first time. Rank and stats will be done once the scan is complete.';
                break;
        }

        if ($this->isUnranked()) {
            $alerts[] = "This player isn't ranked within the top 10000 and will not have their trophies contributed to the site statistics.";
        }

        if ($this->hasHiddenTrophies()) {
            $alerts[] = sprintf(
                'This player has <a href="https://www.playstation.com/en-us/support/games/hide-games-playstation-library/">hidden %s of their trophies</a>.',
                number_format($this->getHiddenTrophyCount())
            );
        }

        return $alerts;
    }

    public function hasLastUpdatedDate(): bool
    {
        $lastUpdated = $this->player['last_updated_date'] ?? null;

        return is_string($lastUpdated) && $lastUpdated !== '';
    }

    public function getLastUpdatedDate(): ?string
    {
        if (!$this->hasLastUpdatedDate()) {
            return null;
        }

        return (string) $this->player['last_updated_date'];
    }

    public function getCountryCode(): string
    {
        return (string) ($this->player['country'] ?? '');
    }

    public function getOnlineId(): string
    {
        return (string) ($this->player['online_id'] ?? '');
    }

    private function getAccountId(): int
    {
        return (int) ($this->player['account_id'] ?? 0);
    }

    private function getStatus(): int
    {
        return (int) ($this->player['status'] ?? 0);
    }

    private function hasHiddenTrophies(): bool
    {
        if ($this->getStatus() === 3) {
            return false;
        }

        return $this->getHiddenTrophyCount() > 0;
    }

    private function getHiddenTrophyCount(): int
    {
        $sonyCount = (int) ($this->player['trophy_count_sony'] ?? 0);
        $npwrCount = (int) ($this->player['trophy_count_npwr'] ?? 0);

        return max(0, $sonyCount - $npwrCount);
    }

    private function isUnranked(): bool
    {
        return (int) ($this->player['ranking'] ?? 0) > 10000;
    }
}

