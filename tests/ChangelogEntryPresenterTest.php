<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/ChangelogEntry.php';
require_once __DIR__ . '/../wwwroot/classes/ChangelogEntryPresenter.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class ChangelogEntryPresenterTest extends TestCase
{
    public function testGetMessageEscapesUnsafeExtraText(): void
    {
        $entry = ChangelogEntry::fromArray([
            'time' => '2026-01-01 12:00:00',
            'change_type' => 'GAME_DELETE',
            'extra' => '<script>alert(1)</script>',
        ]);

        $presenter = new ChangelogEntryPresenter($entry, new Utility());
        $message = $presenter->getMessage();

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $message);
        $this->assertFalse(str_contains($message, '<script>'));
    }
}
