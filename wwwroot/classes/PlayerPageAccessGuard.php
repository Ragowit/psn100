<?php

declare(strict_types=1);

final class PlayerPageAccessGuard
{
    private const DEFAULT_REDIRECT_URL = '/player/';
    private const REDIRECT_STATUS_CODE = 303;

    private ?int $accountId;

    private string $redirectUrl;

    private function __construct(?int $accountId, string $redirectUrl)
    {
        $this->accountId = $accountId;
        $this->redirectUrl = $redirectUrl;
    }

    /**
     * @param int|string|null $accountId
     */
    public static function fromAccountId($accountId, string $redirectUrl = self::DEFAULT_REDIRECT_URL): self
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
