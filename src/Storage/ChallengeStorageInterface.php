<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Storage;

use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;

interface ChallengeStorageInterface
{
    public function save(Challenge $challenge): void;

    public function find(string $id): ?Challenge;

    /**
     * 查找所有挑战
     *
     * @return Challenge[]
     */
    public function findAll(): array;

    public function markAsUsed(string $id): void;

    public function delete(string $id): void;

    public function deleteExpired(): int;

    public function countRecentAttempts(string $clientId, int $seconds = 3600): int;
}
