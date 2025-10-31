<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Service;

use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;

class HashcashProofVerifier implements ProofVerifierInterface
{
    public function verify(Challenge $challenge, string $proof): bool
    {
        if ($challenge->isExpired()) {
            return false;
        }

        if ($challenge->isUsed()) {
            return false;
        }

        $difficulty = $challenge->getDifficulty();
        $challengeString = $challenge->getChallenge();

        $hash = hash('sha256', $challengeString . ':' . $proof);

        $binaryHash = hex2bin($hash);
        if (false === $binaryHash) {
            return false;
        }

        $leadingZeroBits = 0;
        for ($i = 0; $i < strlen($binaryHash); ++$i) {
            $byte = ord($binaryHash[$i]);
            if (0 === $byte) {
                $leadingZeroBits += 8;
            } else {
                $leadingZeroBits += 8 - strlen(ltrim(decbin($byte), '0'));
                break;
            }
        }

        return $leadingZeroBits >= $difficulty;
    }

    public function supportsType(string $type): bool
    {
        return 'hashcash' === $type;
    }
}
