<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerLogPageContext.php';

final class PlayerLogPageContextTest extends TestCase
{
    public function testContextAggregatesDependencies(): void
    {
        $filter = PlayerLogFilter::fromArray(['ps5' => 'true', 'sort' => 'rarity']);
        $entries = [
            PlayerLogEntry::fromArray([
                'trophy_id' => 123,
                'trophy_type' => 'gold',
                'trophy_name' => 'Gold Trophy',
                'trophy_detail' => 'Do something impressive',
                'trophy_icon' => 'gold.png',
                'rarity_percent' => '1.5',
                'trophy_status' => 0,
                'game_id' => 456,
                'game_name' => 'Example Game',
                'game_status' => 0,
                'game_icon' => 'example.png',
                'platform' => 'PS5',
                'earned_date' => '2024-01-01 00:00:00',
            ]),
        ];

        $playerLogPage = $this->createPlayerLogPage($filter, $entries, PlayerStatus::NORMAL, 99);
        $playerSummary = new PlayerSummary(10, 4, 75.0, 12);

        $context = PlayerLogPageContext::fromComponents(
            $playerLogPage,
            $playerSummary,
            $filter,
            'ExampleUser',
            99,
            PlayerStatus::NORMAL
        );

        $this->assertSame($playerLogPage, $context->getPlayerLogPage());
        $this->assertSame($playerSummary, $context->getPlayerSummary());
        $this->assertSame("ExampleUser's Trophy Log ~ PSN 100%", $context->getTitle());
        $this->assertSame('ExampleUser', $context->getPlayerOnlineId());
        $this->assertSame(99, $context->getPlayerAccountId());
        $this->assertTrue($context->shouldDisplayLog());
        $this->assertFalse($context->isPlayerFlagged());
        $this->assertFalse($context->isPlayerPrivate());
        $this->assertCount(1, $context->getTrophies());

        $navigation = $context->getPlayerNavigation();
        $links = $navigation->getLinks();
        $this->assertSame('/player/ExampleUser/log', $links[1]->getUrl());
        $this->assertTrue($links[1]->isActive());

        $platformOptions = $context->getPlatformFilterOptions()->getOptions();
        $ps5Option = null;
        foreach ($platformOptions as $option) {
            if ($option->getInputName() === 'ps5') {
                $ps5Option = $option;
                break;
            }
        }

        $this->assertTrue($ps5Option instanceof PlayerPlatformFilterOption);
        $this->assertTrue($ps5Option->isSelected());
        $this->assertTrue($context->getTrophyRarityFormatter() instanceof TrophyRarityFormatter);
    }

    public function testContextReflectsPlayerStatuses(): void
    {
        $filter = PlayerLogFilter::fromArray([]);
        $playerSummary = new PlayerSummary(0, 0, null, 0);

        $flaggedPage = $this->createPlayerLogPage($filter, [], PlayerStatus::FLAGGED, 50);
        $flaggedContext = PlayerLogPageContext::fromComponents(
            $flaggedPage,
            $playerSummary,
            $filter,
            'FlaggedUser',
            50,
            PlayerStatus::FLAGGED
        );

        $this->assertTrue($flaggedContext->isPlayerFlagged());
        $this->assertFalse($flaggedContext->shouldDisplayLog());

        $privatePage = $this->createPlayerLogPage($filter, [], PlayerStatus::PRIVATE, 75);
        $privateContext = PlayerLogPageContext::fromComponents(
            $privatePage,
            $playerSummary,
            $filter,
            'PrivateUser',
            75,
            PlayerStatus::PRIVATE
        );

        $this->assertTrue($privateContext->isPlayerPrivate());
        $this->assertFalse($privateContext->shouldDisplayLog());
    }

    /**
     * @param PlayerLogEntry[] $entries
     */
    private function createPlayerLogPage(
        PlayerLogFilter $filter,
        array $entries,
        PlayerStatus $playerStatus,
        int $accountId
    ): PlayerLogPage {
        $service = new class($entries) extends PlayerLogService {
            /** @var PlayerLogEntry[] */
            private array $entries;

            public function __construct(array $entries)
            {
                $this->entries = $entries;
            }

            public function countTrophies(int $accountId, PlayerLogFilter $filter): int
            {
                return count($this->entries);
            }

            public function getTrophies(
                int $accountId,
                PlayerLogFilter $filter,
                int $offset,
                int $limit = self::PAGE_SIZE
            ): array {
                return $this->entries;
            }
        };

        return new PlayerLogPage($service, $filter, $accountId, $playerStatus);
    }
}
