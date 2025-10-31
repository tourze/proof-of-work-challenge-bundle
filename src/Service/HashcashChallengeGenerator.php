<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Service;

use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;

class HashcashChallengeGenerator implements ChallengeGeneratorInterface
{
    private int $defaultTimeLimit;

    public function __construct(int $defaultTimeLimit = 300)
    {
        $this->defaultTimeLimit = $defaultTimeLimit;
    }

    public function generate(string $resource, int $difficulty, ?string $clientId = null): Challenge
    {
        $id = $this->generateId();
        $timestamp = time();
        $expireTime = $timestamp + $this->defaultTimeLimit;

        $randomData = bin2hex(random_bytes(16));
        $challengeString = sprintf(
            '%d:%d:%s:%s:%s',
            $difficulty,
            $timestamp,
            $resource,
            $randomData,
            $clientId ?? ''
        );

        $challenge = new Challenge(
            $id,
            $this->getType(),
            $challengeString,
            $difficulty,
            $timestamp,
            $expireTime
        );

        $challenge->setResource($resource);
        if (null !== $clientId) {
            $challenge->setClientId($clientId);
        }

        $challenge->addMetadata('random_data', $randomData);
        $challenge->addMetadata('algorithm', 'SHA-256');

        return $challenge;
    }

    public function getType(): string
    {
        return 'hashcash';
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
