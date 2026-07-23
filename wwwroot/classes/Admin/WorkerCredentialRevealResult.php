<?php

declare(strict_types=1);

require_once __DIR__ . '/../JsonResponseStatus.php';

final readonly class WorkerCredentialRevealResult
{
    private function __construct(
        final private bool $success,
        final private ?string $credential,
        final private ?string $errorMessage,
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
                'status' => JsonResponseStatus::Ok->value,
                'credential' => $this->credential ?? '',
            ];
        }

        return [
            'status' => JsonResponseStatus::Error->value,
            'message' => $this->errorMessage ?? 'Unable to reveal credential.',
        ];
    }
}
