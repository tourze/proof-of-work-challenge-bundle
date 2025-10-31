<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProofOfWorkChallengeBundle\Service\AdaptiveDifficultyAdjuster;
use Tourze\ProofOfWorkChallengeBundle\Storage\ChallengeStorageInterface;

/**
 * @internal
 */
#[CoversClass(AdaptiveDifficultyAdjuster::class)]
final class AdaptiveDifficultyAdjusterTest extends TestCase
{
    public function testBaseDifficulty(): void
    {
        $storage = $this->createMock(ChallengeStorageInterface::class);
        $adjuster = new AdaptiveDifficultyAdjuster($storage, 4, 20);

        $storage->expects($this->never())
            ->method('countRecentAttempts')
        ;

        $difficulty = $adjuster->calculateDifficulty('resource', null);
        $this->assertEquals(4, $difficulty);
    }

    public function testDifficultyWithClientId(): void
    {
        $storage = $this->createMock(ChallengeStorageInterface::class);
        $adjuster = new AdaptiveDifficultyAdjuster($storage, 4, 20);

        $storage->expects($this->once())
            ->method('countRecentAttempts')
            ->with('client-123', 3600)
            ->willReturn(15)
        ;

        $difficulty = $adjuster->calculateDifficulty('resource', 'client-123');
        $this->assertEquals(6, $difficulty); // 4 * 1.5 for 15 attempts
    }

    public function testHighSecurityResource(): void
    {
        $storage = $this->createMock(ChallengeStorageInterface::class);
        $adjuster = new AdaptiveDifficultyAdjuster($storage, 4, 20);

        $storage->expects($this->never())
            ->method('countRecentAttempts')
        ;

        $difficulty = $adjuster->calculateDifficulty('login', null);
        $this->assertEquals(6, $difficulty); // 4 * 1.5 for login resource
    }

    public function testMaxDifficultyLimit(): void
    {
        $storage = $this->createMock(ChallengeStorageInterface::class);
        $adjuster = new AdaptiveDifficultyAdjuster($storage, 10, 20);

        $storage->expects($this->once())
            ->method('countRecentAttempts')
            ->with('client-123', 3600)
            ->willReturn(200) // Should trigger 3.0 multiplier
        ;

        $difficulty = $adjuster->calculateDifficulty('payment', 'client-123');
        $this->assertEquals(20, $difficulty); // Limited by maxDifficulty
    }

    public function testCustomThresholds(): void
    {
        $storage = $this->createMock(ChallengeStorageInterface::class);
        $customThresholds = [
            10 => 5.0,
            5 => 2.5,
        ];
        $adjuster = new AdaptiveDifficultyAdjuster($storage, 4, 20, $customThresholds);

        $storage->expects($this->once())
            ->method('countRecentAttempts')
            ->with('client-123', 3600)
            ->willReturn(7)
        ;

        $difficulty = $adjuster->calculateDifficulty('resource', 'client-123');
        $this->assertEquals(10, $difficulty); // 4 * 2.5 for 7 attempts
    }

    public function testCalculateDifficulty(): void
    {
        $storage = $this->createMock(ChallengeStorageInterface::class);
        $adjuster = new AdaptiveDifficultyAdjuster($storage, 4, 20);

        $storage->expects($this->once())
            ->method('countRecentAttempts')
            ->with('client-123', 3600)
            ->willReturn(15)
        ;

        $difficulty = $adjuster->calculateDifficulty('resource', 'client-123');
        $this->assertEquals(6, $difficulty); // 4 * 1.5 for 15 attempts
    }
}
