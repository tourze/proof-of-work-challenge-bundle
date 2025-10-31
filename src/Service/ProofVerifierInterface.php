<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Service;

use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;

interface ProofVerifierInterface
{
    public function verify(Challenge $challenge, string $proof): bool;

    public function supportsType(string $type): bool;
}
