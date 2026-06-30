<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Admin/AdminAuthConfig.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/AdminAuthService.php';
require_once __DIR__ . '/../wwwroot/classes/SessionManager.php';

final class AdminAuthServiceTest extends TestCase
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

    public function testLoginSucceedsWithMatchingPlainPassword(): void
    {
        $config = new AdminAuthConfig('admin-user', 'secret-password', '');
        $service = new AdminAuthService($config);

        $this->assertTrue($service->login('admin-user', 'secret-password'));
        $this->assertTrue($service->isAuthenticated());
    }

    public function testLoginSucceedsWithPasswordHash(): void
    {
        $passwordHash = password_hash('hashed-password', PASSWORD_DEFAULT);
        $config = new AdminAuthConfig('admin-user', '', $passwordHash);
        $service = new AdminAuthService($config);

        $this->assertTrue($service->login('admin-user', 'hashed-password'));
        $this->assertTrue($service->isAuthenticated());
    }

    public function testLoginFailsWithInvalidCredentials(): void
    {
        $config = new AdminAuthConfig('admin-user', 'secret-password', '');
        $service = new AdminAuthService($config);

        $this->assertFalse($service->login('admin-user', 'wrong-password'));
        $this->assertFalse($service->isAuthenticated());
    }

    public function testLogoutClearsAuthenticationState(): void
    {
        $config = new AdminAuthConfig('admin-user', 'secret-password', '');
        $service = new AdminAuthService($config);
        $service->login('admin-user', 'secret-password');

        $service->logout();

        $this->assertFalse($service->isAuthenticated());
    }
}
