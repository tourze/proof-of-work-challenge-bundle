<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Service;

use Tourze\ProofOfWorkChallengeBundle\Storage\ChallengeStorageInterface;

class AdaptiveDifficultyAdjuster implements DifficultyAdjusterInterface
{
    private ChallengeStorageInterface $storage;

    private int $baseDifficulty;

    private int $maxDifficulty;

    /** @var array<int, float> */
    private array $thresholds;

    /**
     * @param array<int, float> $thresholds
     */
    public function __construct(
        ChallengeStorageInterface $storage,
        int $baseDifficulty = 4,
        int $maxDifficulty = 20,
        array $thresholds = [],
    ) {
        $this->storage = $storage;
        $this->baseDifficulty = $baseDifficulty;
        $this->maxDifficulty = $maxDifficulty;
        $this->thresholds = [] !== $thresholds ? $thresholds : $this->getDefaultThresholds();
    }

    public function calculateDifficulty(string $resource, ?string $clientId = null): int
    {
        $difficulty = $this->baseDifficulty;

        if (null !== $clientId) {
            $recentAttempts = $this->storage->countRecentAttempts($clientId, 3600);
            $difficulty = $this->adjustByAttempts($difficulty, $recentAttempts);
        }

        $difficulty = $this->adjustByResource($difficulty, $resource);

        return min($difficulty, $this->maxDifficulty);
    }

    private function adjustByAttempts(int $baseDifficulty, int $attempts): int
    {
        foreach ($this->thresholds as $threshold => $multiplier) {
            if ($attempts >= $threshold) {
                return (int) ($baseDifficulty * $multiplier);
            }
        }

        return $baseDifficulty;
    }

    private function adjustByResource(int $difficulty, string $resource): int
    {
        $highSecurityResources = [
            'login',
            'register',
            'password-reset',
            'payment',
            'transfer',
        ];

        foreach ($highSecurityResources as $secureResource) {
            if (str_contains($resource, $secureResource)) {
                return (int) ($difficulty * 1.5);
            }
        }

        return $difficulty;
    }

    /**
     * @return array<int, float>
     */
    private function getDefaultThresholds(): array
    {
        return [
            100 => 3.0,
            50 => 2.5,
            20 => 2.0,
            10 => 1.5,
            5 => 1.2,
        ];
    }
}
