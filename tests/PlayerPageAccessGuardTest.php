<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerPageAccessGuard.php';

final class PlayerPageAccessGuardTest extends TestCase
{
    public function testRequireAccountIdReturnsProvidedInteger(): void
    {
        $guard = PlayerPageAccessGuard::fromAccountId(1234);

        $this->assertSame(1234, $guard->requireAccountId());
    }

    public function testRequireAccountIdCastsStringAccountIdToInt(): void
    {
        $guard = PlayerPageAccessGuard::fromAccountId('4321');

        $this->assertSame(4321, $guard->requireAccountId());
    }

    public function testFromAccountIdWithNullUsesDefaultRedirectUrl(): void
    {
        $guard = PlayerPageAccessGuard::fromAccountId(null);

        $this->assertGuardProperties($guard, null, '/player/');
    }

    public function testFromAccountIdWithNullAndCustomRedirectUrl(): void
    {
        $guard = PlayerPageAccessGuard::fromAccountId(null, '/player/search');

        $this->assertGuardProperties($guard, null, '/player/search');
    }

    private function assertGuardProperties(PlayerPageAccessGuard $guard, ?int $accountId, string $redirectUrl): void
    {
        $reflection = new ReflectionClass($guard);

        $accountIdProperty = $reflection->getProperty('accountId');
        $accountIdProperty->setAccessible(true);

        $redirectUrlProperty = $reflection->getProperty('redirectUrl');
        $redirectUrlProperty->setAccessible(true);

        $this->assertSame($accountId, $accountIdProperty->getValue($guard));
        $this->assertSame($redirectUrl, $redirectUrlProperty->getValue($guard));
    }
}
