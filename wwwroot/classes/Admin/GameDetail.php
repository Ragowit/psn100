<?php

declare(strict_types=1);

require_once __DIR__ . '/../GameAvailabilityStatus.php';

final readonly class GameDetail
{
    public function __construct(
        final private int $id,
        final private ?string $npCommunicationId,
        final private string $name,
        final private string $iconUrl,
        final private string $platform,
        final private string $message,
        final private string $setVersion,
        final private ?string $region,
        final private ?string $psnprofilesId,
        final private GameAvailabilityStatus $status,
        final private ?string $obsoleteIds,
    ) {
    }

    #[\NoDiscard]
    public static function fromArray(int $id, array $row): self
    {
        $npCommunicationId = isset($row['np_communication_id']) ? (string) $row['np_communication_id'] : null;
        $name = (string) ($row['name'] ?? '');
        $iconUrl = (string) ($row['icon_url'] ?? '');
        $platform = (string) ($row['platform'] ?? '');
        $message = (string) ($row['message'] ?? '');
        $setVersion = (string) ($row['set_version'] ?? '');
        $region = isset($row['region']) ? (string) $row['region'] : null;
        $psnprofilesId = isset($row['psnprofiles_id']) ? (string) $row['psnprofiles_id'] : null;
        $status = GameAvailabilityStatus::fromInt((int) ($row['status'] ?? 0));
        $obsoleteIds = isset($row['obsolete_ids']) ? (string) $row['obsolete_ids'] : null;

        return new self(
            $id,
            $npCommunicationId,
            $name,
            $iconUrl,
            $platform,
            $message,
            $setVersion,
            $region,
            $psnprofilesId,
            $status,
            $obsoleteIds
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNpCommunicationId(): ?string
    {
        return $this->npCommunicationId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIconUrl(): string
    {
        return $this->iconUrl;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getSetVersion(): string
    {
        return $this->setVersion;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getPsnprofilesId(): ?string
    {
        return $this->psnprofilesId;
    }

    public function getStatus(): GameAvailabilityStatus
    {
        return $this->status;
    }

    public function getObsoleteIds(): ?string
    {
        return $this->obsoleteIds;
    }
}
