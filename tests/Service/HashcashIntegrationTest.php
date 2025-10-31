<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tourze\ProofOfWorkChallengeBundle\Service\AdaptiveDifficultyAdjuster;
use Tourze\ProofOfWorkChallengeBundle\Service\HashcashChallengeGenerator;
use Tourze\ProofOfWorkChallengeBundle\Service\HashcashProofVerifier;
use Tourze\ProofOfWorkChallengeBundle\Storage\CacheChallengeStorage;

/**
 * @internal
 */
#[CoversClass(HashcashChallengeGenerator::class)]
final class HashcashIntegrationTest extends TestCase
{
    public function testHashcashWorkflow(): void
    {
        $cache = new ArrayAdapter();
        $storage = new CacheChallengeStorage($cache);
        $generator = new HashcashChallengeGenerator();
        $verifier = new HashcashProofVerifier();
        $difficultyAdjuster = new AdaptiveDifficultyAdjuster($storage, 4, 20);

        $resource = 'login';
        $clientId = 'test-client';

        $difficulty = $difficultyAdjuster->calculateDifficulty($resource, $clientId);
        $this->assertEquals(6, $difficulty);

        $challenge = $generator->generate($resource, $difficulty, $clientId);
        $storage->save($challenge);

        $this->assertEquals('hashcash', $challenge->getType());
        $this->assertEquals($resource, $challenge->getResource());
        $this->assertEquals($clientId, $challenge->getClientId());
        $this->assertEquals($difficulty, $challenge->getDifficulty());

        $proof = $this->findProof($challenge->getChallenge(), $difficulty);
        $this->assertNotNull($proof);

        $isValid = $verifier->verify($challenge, $proof);
        $this->assertTrue($isValid);

        $storage->markAsUsed($challenge->getId());
        $loadedChallenge = $storage->find($challenge->getId());
        $this->assertNotNull($loadedChallenge);
        $this->assertTrue($loadedChallenge->isUsed());

        $isValidAfterUse = $verifier->verify($loadedChallenge, $proof);
        $this->assertFalse($isValidAfterUse);
    }

    public function testGenerate(): void
    {
        $generator = new HashcashChallengeGenerator();
        $resource = 'test-resource';
        $difficulty = 4;
        $clientId = 'test-client';

        $challenge = $generator->generate($resource, $difficulty, $clientId);

        $this->assertEquals('hashcash', $challenge->getType());
        $this->assertEquals($resource, $challenge->getResource());
        $this->assertEquals($difficulty, $challenge->getDifficulty());
        $this->assertEquals($clientId, $challenge->getClientId());
        $this->assertNotEmpty($challenge->getId());
        $this->assertNotEmpty($challenge->getChallenge());
    }

    private function findProof(string $challenge, int $difficulty): ?string
    {
        $maxAttempts = 1000000;
        for ($i = 0; $i < $maxAttempts; ++$i) {
            $proof = (string) $i;
            $hash = hash('sha256', $challenge . ':' . $proof);

            if ($this->countLeadingZeroBits($hash) >= $difficulty) {
                return $proof;
            }
        }

        return null;
    }

    private function countLeadingZeroBits(string $hash): int
    {
        $binaryHash = hex2bin($hash);
        if (false === $binaryHash) {
            return 0;
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

        return $leadingZeroBits;
    }
}
