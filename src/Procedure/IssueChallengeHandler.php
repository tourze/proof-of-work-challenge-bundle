<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Procedure;

use Tourze\ProofOfWorkChallengeBundle\Service\ChallengeGeneratorInterface;
use Tourze\ProofOfWorkChallengeBundle\Service\DifficultyAdjusterInterface;
use Tourze\ProofOfWorkChallengeBundle\Storage\ChallengeStorageInterface;

class IssueChallengeHandler
{
    private ChallengeGeneratorInterface $generator;

    private ChallengeStorageInterface $storage;

    private DifficultyAdjusterInterface $difficultyAdjuster;

    public function __construct(
        ChallengeGeneratorInterface $generator,
        ChallengeStorageInterface $storage,
        DifficultyAdjusterInterface $difficultyAdjuster,
    ) {
        $this->generator = $generator;
        $this->storage = $storage;
        $this->difficultyAdjuster = $difficultyAdjuster;
    }

    /**
     * @return array{success: bool, challenge: array{id: string, type: string, challenge: string, difficulty: int, expire_time: int, resource: ?string}}
     */
    public function __invoke(string $resource, ?string $clientId = null): array
    {
        $difficulty = $this->difficultyAdjuster->calculateDifficulty($resource, $clientId);

        $challenge = $this->generator->generate($resource, $difficulty, $clientId);

        $this->storage->save($challenge);

        return [
            'success' => true,
            'challenge' => [
                'id' => $challenge->getId(),
                'type' => $challenge->getType(),
                'challenge' => $challenge->getChallenge(),
                'difficulty' => $challenge->getDifficulty(),
                'expire_time' => $challenge->getExpireTime(),
                'resource' => $challenge->getResource(),
            ],
        ];
    }
}
