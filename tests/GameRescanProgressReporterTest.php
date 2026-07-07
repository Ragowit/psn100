<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameRescanProgressListener.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameRescanProgressReporter.php';

final class GameRescanProgressReporterTest extends TestCase
{
    public function testNotifyIsNoOpWithoutListener(): void
    {
        $reporter = new GameRescanProgressReporter();

        $reporter->notify(50, 'Halfway');

        $this->assertTrue(true);
    }

    public function testNotifyClampsPercentAndEnforcesMonotonicProgress(): void
    {
        $events = [];
        $reporter = new GameRescanProgressReporter(new class($events) implements GameRescanProgressListener {
            /**
             * @param list<array{int, string}> $events
             */
            public function __construct(private array &$events)
            {
            }

            public function onProgress(int $percent, string $message): void
            {
                $this->events[] = [$percent, $message];
            }
        });

        $reporter->notify(10, 'Start');
        $reporter->notify(-5, 'Under min');
        $reporter->notify(40, 'Step up');
        $reporter->notify(30, 'Would regress');
        $reporter->notify(150, 'Over max');

        $this->assertSame([
            [10, 'Start'],
            [10, 'Under min'],
            [40, 'Step up'],
            [40, 'Would regress'],
            [100, 'Over max'],
        ], $events);
    }

    public function testNotifyRangeInterpolatesBetweenBounds(): void
    {
        $events = [];
        $reporter = new GameRescanProgressReporter(new class($events) implements GameRescanProgressListener {
            /**
             * @param list<array{int, string}> $events
             */
            public function __construct(private array &$events)
            {
            }

            public function onProgress(int $percent, string $message): void
            {
                $this->events[] = [$percent, $message];
            }
        });

        $reporter->notifyRange(25, 70, 1, 4, 'First quarter');
        $reporter->notifyRange(25, 70, 4, 4, 'Done');

        $this->assertSame(36, $events[0][0]);
        $this->assertSame(70, $events[1][0]);
    }

    public function testDescribeTrophyGroupPrefersNameThenDetailThenId(): void
    {
        $reporter = new GameRescanProgressReporter();

        $withName = new class {
            public function name(): string
            {
                return '  Base Game ';
            }

            public function detail(): string
            {
                return 'Ignored detail';
            }

            public function id(): string
            {
                return 'default';
            }
        };

        $withDetailOnly = new class {
            public function name(): string
            {
                return '';
            }

            public function detail(): string
            {
                return 'DLC Pack';
            }

            public function id(): string
            {
                return '002';
            }
        };

        $withIdOnly = new class {
            public function name(): string
            {
                return '';
            }

            public function detail(): string
            {
                return '';
            }

            public function id(): string
            {
                return '003';
            }
        };

        $this->assertSame('Base Game', $reporter->describeTrophyGroup($withName));
        $this->assertSame('DLC Pack', $reporter->describeTrophyGroup($withDetailOnly));
        $this->assertSame('Group 003', $reporter->describeTrophyGroup($withIdOnly));
    }

    public function testDescribeTrophyPrefersNameThenDetailThenId(): void
    {
        $reporter = new GameRescanProgressReporter();

        $withName = new class {
            public function name(): string
            {
                return 'First Steps';
            }

            public function detail(): string
            {
                return 'Complete the tutorial';
            }

            public function id(): int
            {
                return 1;
            }
        };

        $withDetailOnly = new class {
            public function name(): string
            {
                return '';
            }

            public function detail(): string
            {
                return 'Beat the boss';
            }

            public function id(): int
            {
                return 2;
            }
        };

        $withIdOnly = new class {
            public function name(): string
            {
                return '';
            }

            public function detail(): string
            {
                return '';
            }

            public function id(): int
            {
                return 3;
            }
        };

        $this->assertSame('First Steps', $reporter->describeTrophy($withName));
        $this->assertSame('Beat the boss', $reporter->describeTrophy($withDetailOnly));
        $this->assertSame('Trophy 3', $reporter->describeTrophy($withIdOnly));
    }

    public function testResetAllowsProgressToDropAgain(): void
    {
        $events = [];
        $reporter = new GameRescanProgressReporter(new class($events) implements GameRescanProgressListener {
            /**
             * @param list<array{int, string}> $events
             */
            public function __construct(private array &$events)
            {
            }

            public function onProgress(int $percent, string $message): void
            {
                $this->events[] = [$percent, $message];
            }
        });

        $reporter->notify(80, 'High');
        $reporter->reset();
        $reporter->notify(10, 'Restarted');

        $this->assertSame([
            [80, 'High'],
            [10, 'Restarted'],
        ], $events);
    }
}
