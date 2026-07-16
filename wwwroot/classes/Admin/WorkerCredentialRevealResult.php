<?php

declare(strict_types=1);

final readonly class WorkerCredentialRevealResult
{
    private function __construct(
        private bool $success,
        private ?string $credential,
        private ?string $errorMessage,
    ) {
    }

    #[\NoDiscard]
    public static function success(string $credential): self
    {
        return new self(true, $credential, null);
    }

    #[\NoDiscard]
    public static function error(string $errorMessage): self
    {
        return new self(false, null, $errorMessage);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getCredential(): ?string
    {
        return $this->credential;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return array{status: string, credential?: string, message?: string}
     */
    public function toPayload(): array
    {
        if ($this->success) {
            return [
                'status' => 'ok',
                'credential' => $this->credential ?? '',
            ];
        }

        return [
            'status' => 'error',
            'message' => $this->errorMessage ?? 'Unable to reveal credential.',
        ];
    }
}
