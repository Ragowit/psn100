<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueuePollTokenManager.php';
require_once __DIR__ . '/../wwwroot/classes/SessionManager.php';

final class PlayerQueuePollTokenManagerTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        session_start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public function testIssueAndValidateMatchingToken(): void
    {
        $manager = new PlayerQueuePollTokenManager();
        $token = $manager->issue('ExamplePlayer');

        $this->assertTrue($token !== '');
        $this->assertTrue($manager->validate('ExamplePlayer', $token));
    }

    public function testValidateRejectsMissingOrMismatchedToken(): void
    {
        $manager = new PlayerQueuePollTokenManager();
        $manager->issue('ExamplePlayer');

        $this->assertFalse($manager->validate('ExamplePlayer', ''));
        $this->assertFalse($manager->validate('ExamplePlayer', 'invalid-token'));
        $this->assertFalse($manager->validate('OtherPlayer', 'invalid-token'));
    }
}
