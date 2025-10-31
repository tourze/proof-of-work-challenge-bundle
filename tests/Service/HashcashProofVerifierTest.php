<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;
use Tourze\ProofOfWorkChallengeBundle\Service\HashcashProofVerifier;

/**
 * @internal
 */
#[CoversClass(HashcashProofVerifier::class)]
final class HashcashProofVerifierTest extends TestCase
{
    private HashcashProofVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = new HashcashProofVerifier();
    }

    public function testSupportsType(): void
    {
        $this->assertTrue($this->verifier->supportsType('hashcash'));
        $this->assertFalse($this->verifier->supportsType('other'));
        $this->assertFalse($this->verifier->supportsType(''));
    }

    public function testVerifyValidProof(): void
    {
        // Create a challenge with known difficulty
        $challenge = new Challenge(
            'test-id',
            'hashcash',
            'test:challenge',
            4, // Low difficulty for testing
            time(),
            time() + 300
        );

        // Find a valid proof
        $proof = $this->findValidProof($challenge->getChallenge(), 4);
        $this->assertNotNull($proof);

        // Verify the proof
        $isValid = $this->verifier->verify($challenge, $proof);
        $this->assertTrue($isValid);
    }

    public function testVerifyInvalidProof(): void
    {
        $challenge = new Challenge(
            'test-id',
            'hashcash',
            'test:challenge',
            8,
            time(),
            time() + 300
        );

        // Use an invalid proof
        $isValid = $this->verifier->verify($challenge, 'invalid');
        $this->assertFalse($isValid);
    }

    public function testVerifyExpiredChallenge(): void
    {
        $challenge = new Challenge(
            'test-id',
            'hashcash',
            'test:challenge',
            4,
            time() - 600,
            time() - 300
        );

        // Even with valid proof, expired challenge should fail
        $proof = '0'; // Doesn't matter
        $isValid = $this->verifier->verify($challenge, $proof);
        $this->assertFalse($isValid);
    }

    public function testVerifyUsedChallenge(): void
    {
        $challenge = new Challenge(
            'test-id',
            'hashcash',
            'test:challenge',
            4,
            time(),
            time() + 300
        );
        $challenge->markAsUsed();

        // Even with valid proof, used challenge should fail
        $proof = '0'; // Doesn't matter
        $isValid = $this->verifier->verify($challenge, $proof);
        $this->assertFalse($isValid);
    }

    public function testVerifyDifferentDifficulties(): void
    {
        // Test with difficulty 2 (should be very fast)
        $challenge2 = new Challenge(
            'test-id',
            'hashcash',
            'test:2',
            2,
            time(),
            time() + 300
        );
        $proof2 = $this->findValidProof($challenge2->getChallenge(), 2);
        $this->assertNotNull($proof2);
        $this->assertTrue($this->verifier->verify($challenge2, $proof2));

        // Test with difficulty 6 (still reasonably fast)
        $challenge6 = new Challenge(
            'test-id',
            'hashcash',
            'test:6',
            6,
            time(),
            time() + 300
        );
        $proof6 = $this->findValidProof($challenge6->getChallenge(), 6);
        $this->assertNotNull($proof6);
        $this->assertTrue($this->verifier->verify($challenge6, $proof6));
    }

    private function findValidProof(string $challenge, int $difficulty): ?string
    {
        for ($i = 0; $i < 100000; ++$i) {
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
