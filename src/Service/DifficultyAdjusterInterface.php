<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Service;

interface DifficultyAdjusterInterface
{
    public function calculateDifficulty(string $resource, ?string $clientId = null): int;
}
