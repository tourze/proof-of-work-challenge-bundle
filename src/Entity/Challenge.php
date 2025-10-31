<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Entity;

class Challenge
{
    private string $id;

    private string $type;

    private string $challenge;

    private int $difficulty;

    private int $createTime;

    private int $expireTime;

    private ?string $resource = null;

    private ?string $clientId = null;

    private bool $used = false;

    /** @var array<string, mixed> */
    private array $metadata = [];

    public function __construct(
        string $id,
        string $type,
        string $challenge,
        int $difficulty,
        int $createTime,
        int $expireTime,
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->challenge = $challenge;
        $this->difficulty = $difficulty;
        $this->createTime = $createTime;
        $this->expireTime = $expireTime;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getChallenge(): string
    {
        return $this->challenge;
    }

    public function getDifficulty(): int
    {
        return $this->difficulty;
    }

    public function getCreateTime(): int
    {
        return $this->createTime;
    }

    public function getExpireTime(): int
    {
        return $this->expireTime;
    }

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function setResource(?string $resource): void
    {
        $this->resource = $resource;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(?string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function markAsUsed(): void
    {
        $this->used = true;
    }

    public function isExpired(): bool
    {
        return time() > $this->expireTime;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'challenge' => $this->challenge,
            'difficulty' => $this->difficulty,
            'create_time' => $this->createTime,
            'expire_time' => $this->expireTime,
            'resource' => $this->resource,
            'client_id' => $this->clientId,
            'used' => $this->used,
            'metadata' => $this->metadata,
        ];
    }
}
