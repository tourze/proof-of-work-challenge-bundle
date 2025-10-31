<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Service;

use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;

interface ChallengeGeneratorInterface
{
    public function generate(string $resource, int $difficulty, ?string $clientId = null): Challenge;

    public function getType(): string;
}
