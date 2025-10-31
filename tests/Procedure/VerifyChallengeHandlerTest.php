<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;
use Tourze\ProofOfWorkChallengeBundle\Procedure\VerifyChallengeHandler;
use Tourze\ProofOfWorkChallengeBundle\Service\ProofVerifierInterface;
use Tourze\ProofOfWorkChallengeBundle\Storage\ChallengeStorageInterface;

/**
 * @internal
 */
#[CoversClass(VerifyChallengeHandler::class)]
final class VerifyChallengeHandlerTest extends TestCase
{
    private ProofVerifierInterface $verifier;

    private ChallengeStorageInterface $storage;

    private VerifyChallengeHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = $this->createMock(ProofVerifierInterface::class);
        $this->storage = $this->createMock(ChallengeStorageInterface::class);
        $this->handler = new VerifyChallengeHandler($this->verifier, $this->storage);
    }

    public function testVerifyValidChallenge(): void
    {
        $challengeId = 'test-id';
        $proof = '123456';
        $challenge = new Challenge(
            $challengeId,
            'hashcash',
            'test-challenge',
            4,
            time(),
            time() + 300
        );

        $this->storage->expects($this->once())
            ->method('find')
            ->with($challengeId)
            ->willReturn($challenge)
        ;

        $this->verifier->expects($this->once())
            ->method('supportsType')
            ->with('hashcash')
            ->willReturn(true)
        ;

        $this->verifier->expects($this->once())
            ->method('verify')
            ->with($challenge, $proof)
            ->willReturn(true)
        ;

        $this->storage->expects($this->once())
            ->method('markAsUsed')
            ->with($challengeId)
        ;

        $result = ($this->handler)($challengeId, $proof);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('resource', $result);
        $this->assertArrayHasKey('client_id', $result);
        $this->assertArrayHasKey('metadata', $result);
    }

    public function testVerifyChallengeNotFound(): void
    {
        $challengeId = 'non-existent';
        $proof = '123456';

        $this->storage->expects($this->once())
            ->method('find')
            ->with($challengeId)
            ->willReturn(null)
        ;

        $result = ($this->handler)($challengeId, $proof);

        $this->assertFalse($result['success']);
        $this->assertEquals('CHALLENGE_NOT_FOUND', $result['code']);
    }

    public function testVerifyExpiredChallenge(): void
    {
        $challengeId = 'test-id';
        $proof = '123456';
        $challenge = new Challenge(
            $challengeId,
            'hashcash',
            'test-challenge',
            4,
            time() - 600,
            time() - 300
        );

        $this->storage->expects($this->once())
            ->method('find')
            ->with($challengeId)
            ->willReturn($challenge)
        ;

        $result = ($this->handler)($challengeId, $proof);

        $this->assertFalse($result['success']);
        $this->assertEquals('CHALLENGE_EXPIRED', $result['code']);
    }

    public function testVerifyUsedChallenge(): void
    {
        $challengeId = 'test-id';
        $proof = '123456';
        $challenge = new Challenge(
            $challengeId,
            'hashcash',
            'test-challenge',
            4,
            time(),
            time() + 300
        );
        $challenge->markAsUsed();

        $this->storage->expects($this->once())
            ->method('find')
            ->with($challengeId)
            ->willReturn($challenge)
        ;

        $result = ($this->handler)($challengeId, $proof);

        $this->assertFalse($result['success']);
        $this->assertEquals('CHALLENGE_ALREADY_USED', $result['code']);
    }

    public function testVerifyInvalidProof(): void
    {
        $challengeId = 'test-id';
        $proof = 'invalid';
        $challenge = new Challenge(
            $challengeId,
            'hashcash',
            'test-challenge',
            4,
            time(),
            time() + 300
        );

        $this->storage->expects($this->once())
            ->method('find')
            ->with($challengeId)
            ->willReturn($challenge)
        ;

        $this->verifier->expects($this->once())
            ->method('supportsType')
            ->with('hashcash')
            ->willReturn(true)
        ;

        $this->verifier->expects($this->once())
            ->method('verify')
            ->with($challenge, $proof)
            ->willReturn(false)
        ;

        $result = ($this->handler)($challengeId, $proof);

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_PROOF', $result['code']);
    }
}
