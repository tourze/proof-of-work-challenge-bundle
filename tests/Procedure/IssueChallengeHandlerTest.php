<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;
use Tourze\ProofOfWorkChallengeBundle\Procedure\IssueChallengeHandler;
use Tourze\ProofOfWorkChallengeBundle\Service\ChallengeGeneratorInterface;
use Tourze\ProofOfWorkChallengeBundle\Service\DifficultyAdjusterInterface;
use Tourze\ProofOfWorkChallengeBundle\Storage\ChallengeStorageInterface;

/**
 * @internal
 */
#[CoversClass(IssueChallengeHandler::class)]
final class IssueChallengeHandlerTest extends TestCase
{
    public function testIssueChallenge(): void
    {
        $generator = $this->createMock(ChallengeGeneratorInterface::class);
        $storage = $this->createMock(ChallengeStorageInterface::class);
        $difficultyAdjuster = $this->createMock(DifficultyAdjusterInterface::class);

        $resource = 'login';
        $clientId = 'test-client';
        $difficulty = 5;

        $challenge = new Challenge(
            'test-id',
            'hashcash',
            'test-challenge',
            $difficulty,
            time(),
            time() + 300
        );
        $challenge->setResource($resource);
        $challenge->setClientId($clientId);

        $difficultyAdjuster->expects($this->once())
            ->method('calculateDifficulty')
            ->with($resource, $clientId)
            ->willReturn($difficulty)
        ;

        $generator->expects($this->once())
            ->method('generate')
            ->with($resource, $difficulty, $clientId)
            ->willReturn($challenge)
        ;

        $storage->expects($this->once())
            ->method('save')
            ->with($challenge)
        ;

        $handler = new IssueChallengeHandler($generator, $storage, $difficultyAdjuster);
        $result = $handler($resource, $clientId);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('challenge', $result);
        $this->assertEquals('test-id', $result['challenge']['id']);
        $this->assertEquals('hashcash', $result['challenge']['type']);
        $this->assertEquals($difficulty, $result['challenge']['difficulty']);
        $this->assertEquals($resource, $result['challenge']['resource']);
    }
}
