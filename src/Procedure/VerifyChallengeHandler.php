<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Procedure;

use Tourze\ProofOfWorkChallengeBundle\Service\ProofVerifierInterface;
use Tourze\ProofOfWorkChallengeBundle\Storage\ChallengeStorageInterface;

class VerifyChallengeHandler
{
    private ProofVerifierInterface $verifier;

    private ChallengeStorageInterface $storage;

    public function __construct(
        ProofVerifierInterface $verifier,
        ChallengeStorageInterface $storage,
    ) {
        $this->verifier = $verifier;
        $this->storage = $storage;
    }

    /**
     * @return array{success: true, resource?: ?string, client_id?: ?string, metadata?: array<string, mixed>}|array{success: false, error: string, code: string}
     */
    public function __invoke(string $challengeId, string $proof): array
    {
        $challenge = $this->storage->find($challengeId);

        if (null === $challenge) {
            return [
                'success' => false,
                'error' => 'Challenge not found',
                'code' => 'CHALLENGE_NOT_FOUND',
            ];
        }

        if ($challenge->isExpired()) {
            return [
                'success' => false,
                'error' => 'Challenge has expired',
                'code' => 'CHALLENGE_EXPIRED',
            ];
        }

        if ($challenge->isUsed()) {
            return [
                'success' => false,
                'error' => 'Challenge has already been used',
                'code' => 'CHALLENGE_ALREADY_USED',
            ];
        }

        if (!$this->verifier->supportsType($challenge->getType())) {
            return [
                'success' => false,
                'error' => 'Unsupported challenge type',
                'code' => 'UNSUPPORTED_TYPE',
            ];
        }

        $isValid = $this->verifier->verify($challenge, $proof);

        if ($isValid) {
            $this->storage->markAsUsed($challenge->getId());

            return [
                'success' => true,
                'resource' => $challenge->getResource(),
                'client_id' => $challenge->getClientId(),
                'metadata' => $challenge->getMetadata(),
            ];
        }

        return [
            'success' => false,
            'error' => 'Invalid proof',
            'code' => 'INVALID_PROOF',
        ];
    }
}
