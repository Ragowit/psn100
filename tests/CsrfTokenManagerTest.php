<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/CsrfTokenManager.php';
require_once __DIR__ . '/../wwwroot/classes/SessionManager.php';

final class CsrfTokenManagerTest extends TestCase
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

    public function testGetTokenReturnsStableValueForScope(): void
    {
        $firstToken = CsrfTokenManager::getToken('public');
        $secondToken = CsrfTokenManager::getToken('public');

        $this->assertSame($firstToken, $secondToken);
        $this->assertTrue($firstToken !== '');
    }

    public function testValidateAcceptsMatchingToken(): void
    {
        $token = CsrfTokenManager::getToken('admin');

        $this->assertTrue(CsrfTokenManager::validate('admin', $token));
    }

    public function testValidateRejectsMismatchedToken(): void
    {
        CsrfTokenManager::getToken('admin');

        $this->assertFalse(CsrfTokenManager::validate('admin', 'invalid-token'));
    }

    public function testHiddenFieldContainsCurrentToken(): void
    {
        $token = CsrfTokenManager::getToken('public');
        $field = CsrfTokenManager::hiddenField('public');

        $this->assertStringContainsString('name="_csrf_token"', $field);
        $this->assertStringContainsString('value="' . $token . '"', $field);
    }
}
