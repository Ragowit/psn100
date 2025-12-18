<?php

declare(strict_types=1);

require_once __DIR__ . '/../GameAvailabilityStatus.php';

final readonly class GameDetail
{
    public function __construct(
        private int $id,
        private ?string $npCommunicationId,
        private string $name,
        private string $iconUrl,
        private string $platform,
        private string $message,
        private string $setVersion,
        private ?string $region,
        private ?string $psnprofilesId,
        private GameAvailabilityStatus $status,
        private ?string $obsoleteIds,
    ) {
    }

    public static function fromArray(int $id, array $row): self
    {
        $npCommunicationId = isset($row['np_communication_id']) ? (string) $row['np_communication_id'] : null;
        $name = (string) ($row['name'] ?? '');
        $iconUrl = (string) ($row['icon_url'] ?? '');
        $platform = (string) ($row['platform'] ?? '');
        $message = (string) ($row['message'] ?? '');
        $setVersion = (string) ($row['set_version'] ?? '');
        $region = array_key_exists('region', $row) ? ($row['region'] !== null ? (string) $row['region'] : null) : null;
        $psnprofilesId = array_key_exists('psnprofiles_id', $row)
            ? ($row['psnprofiles_id'] !== null ? (string) $row['psnprofiles_id'] : null)
            : null;
        $status = GameAvailabilityStatus::fromInt((int) ($row['status'] ?? 0));
        $obsoleteIds = array_key_exists('obsolete_ids', $row)
            ? ($row['obsolete_ids'] !== null ? (string) $row['obsolete_ids'] : null)
            : null;

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
