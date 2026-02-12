<?php

declare(strict_types=1);

final class PlayerPageAccessGuard
{
    private const DEFAULT_REDIRECT_URL = '/player/';
    private const REDIRECT_STATUS_CODE = 303;

    private function __construct(
        private readonly ?int $accountId,
        private readonly string $redirectUrl
    ) {}

    public static function fromAccountId(int|string|null $accountId, string $redirectUrl = self::DEFAULT_REDIRECT_URL): self
    {
        if ($accountId === null) {
            return new self(null, $redirectUrl);
        }

        return new self((int) $accountId, $redirectUrl);
    }

    public function requireAccountId(): int
    {
        if ($this->accountId === null) {
            $this->redirect();
        }

        return $this->accountId;
    }

    private function redirect(): void
    {
        header('Location: ' . $this->redirectUrl, true, self::REDIRECT_STATUS_CODE);
        exit();
    }
}
