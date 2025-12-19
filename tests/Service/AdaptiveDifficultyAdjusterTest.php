<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;
use Tourze\ProofOfWorkChallengeBundle\Service\AdaptiveDifficultyAdjuster;
use Tourze\ProofOfWorkChallengeBundle\Storage\CacheChallengeStorage;

/**
 * AdaptiveDifficultyAdjuster 集成测试
 *
 * @internal
 */
#[CoversClass(AdaptiveDifficultyAdjuster::class)]
#[RunTestsInSeparateProcesses]
final class AdaptiveDifficultyAdjusterTest extends AbstractIntegrationTestCase
{
    private AdaptiveDifficultyAdjuster $adjuster;
    private CacheChallengeStorage $storage;

    protected function onSetUp(): void
    {
        $this->adjuster = self::getService(AdaptiveDifficultyAdjuster::class);
        $this->storage = self::getService(CacheChallengeStorage::class);

        // 清理存储中的所有挑战数据
        $this->cleanStorage();
    }

    private function cleanStorage(): void
    {
        $allChallenges = $this->storage->findAll();
        foreach ($allChallenges as $challenge) {
            $this->storage->delete($challenge->getId());
        }
    }

    public function testCalculateDifficulty(): void
    {
        // 没有客户端 ID 时，使用基础难度
        $difficulty = $this->adjuster->calculateDifficulty('resource', null);
        $this->assertEquals(4, $difficulty); // 配置的基础难度
    }

    public function testBaseDifficulty(): void
    {
        // 没有客户端 ID 时，使用基础难度
        $difficulty = $this->adjuster->calculateDifficulty('resource', null);
        $this->assertEquals(4, $difficulty); // 配置的基础难度
    }

    public function testBaseDifficultyWithClientIdNoHistory(): void
    {
        // 有客户端 ID 但没有历史记录时，使用基础难度
        $difficulty = $this->adjuster->calculateDifficulty('resource', 'new-client');
        $this->assertEquals(4, $difficulty);
    }

    public function testHighSecurityResourceLogin(): void
    {
        // 'login' 资源应该有 1.5 倍难度
        $difficulty = $this->adjuster->calculateDifficulty('login', null);
        $this->assertEquals(6, $difficulty); // 4 * 1.5 = 6
    }

    public function testHighSecurityResourceRegister(): void
    {
        // 'register' 资源应该有 1.5 倍难度
        $difficulty = $this->adjuster->calculateDifficulty('register', null);
        $this->assertEquals(6, $difficulty);
    }

    public function testHighSecurityResourcePasswordReset(): void
    {
        // 'password-reset' 资源应该有 1.5 倍难度
        $difficulty = $this->adjuster->calculateDifficulty('password-reset', null);
        $this->assertEquals(6, $difficulty);
    }

    public function testHighSecurityResourcePayment(): void
    {
        // 'payment' 资源应该有 1.5 倍难度
        $difficulty = $this->adjuster->calculateDifficulty('payment', null);
        $this->assertEquals(6, $difficulty);
    }

    public function testHighSecurityResourceTransfer(): void
    {
        // 'transfer' 资源应该有 1.5 倍难度
        $difficulty = $this->adjuster->calculateDifficulty('transfer', null);
        $this->assertEquals(6, $difficulty);
    }

    public function testHighSecurityResourceWithPartialMatch(): void
    {
        // 包含 'login' 的资源也应该有 1.5 倍难度
        $difficulty = $this->adjuster->calculateDifficulty('admin-login', null);
        $this->assertEquals(6, $difficulty);

        $difficulty = $this->adjuster->calculateDifficulty('user-payment-confirm', null);
        $this->assertEquals(6, $difficulty);
    }

    public function testDifficultyIncreaseWith5Attempts(): void
    {
        $clientId = 'test-client-5';
        $now = time();

        // 创建 5 个挑战
        for ($i = 1; $i <= 5; ++$i) {
            $challenge = new Challenge(
                "5-attempts-{$i}",
                'hashcash',
                "challenge-{$i}",
                4,
                $now,
                $now + 300
            );
            $challenge->setClientId($clientId);
            $this->storage->save($challenge);
        }

        // 难度应该增加（5 次尝试触发 1.2 倍增）
        $difficulty = $this->adjuster->calculateDifficulty('resource', $clientId);
        // 4 * 1.2 = 4.8 -> 取整为 4
        $this->assertEquals(4, $difficulty);
    }

    public function testDifficultyIncreaseWith10Attempts(): void
    {
        $clientId = 'test-client-10';
        $now = time();

        // 创建 10 个挑战
        for ($i = 1; $i <= 10; ++$i) {
            $challenge = new Challenge(
                "10-attempts-{$i}",
                'hashcash',
                "challenge-{$i}",
                4,
                $now,
                $now + 300
            );
            $challenge->setClientId($clientId);
            $this->storage->save($challenge);
        }

        // 难度应该增加（10 次尝试触发 1.5 倍增）
        $difficulty = $this->adjuster->calculateDifficulty('resource', $clientId);
        // 4 * 1.5 = 6
        $this->assertEquals(6, $difficulty);
    }

    public function testDifficultyIncreaseWith20Attempts(): void
    {
        $clientId = 'test-client-20';
        $now = time();

        // 创建 20 个挑战
        for ($i = 1; $i <= 20; ++$i) {
            $challenge = new Challenge(
                "20-attempts-{$i}",
                'hashcash',
                "challenge-{$i}",
                4,
                $now,
                $now + 300
            );
            $challenge->setClientId($clientId);
            $this->storage->save($challenge);
        }

        // 难度应该增加（20 次尝试触发 2.0 倍增）
        $difficulty = $this->adjuster->calculateDifficulty('resource', $clientId);
        // 4 * 2.0 = 8
        $this->assertEquals(8, $difficulty);
    }

    public function testDifficultyIncreaseWith50Attempts(): void
    {
        $clientId = 'test-client-50';
        $now = time();

        // 创建 50 个挑战
        for ($i = 1; $i <= 50; ++$i) {
            $challenge = new Challenge(
                "50-attempts-{$i}",
                'hashcash',
                "challenge-{$i}",
                4,
                $now,
                $now + 300
            );
            $challenge->setClientId($clientId);
            $this->storage->save($challenge);
        }

        // 难度应该增加（50 次尝试触发 2.5 倍增）
        $difficulty = $this->adjuster->calculateDifficulty('resource', $clientId);
        // 4 * 2.5 = 10
        $this->assertEquals(10, $difficulty);
    }

    public function testMaxDifficultyLimit(): void
    {
        $clientId = 'test-client-100';
        $now = time();

        // 创建 100 个挑战
        for ($i = 1; $i <= 100; ++$i) {
            $challenge = new Challenge(
                "100-attempts-{$i}",
                'hashcash',
                "challenge-{$i}",
                4,
                $now,
                $now + 300
            );
            $challenge->setClientId($clientId);
            $this->storage->save($challenge);
        }

        // 难度不应超过最大值 20
        $difficulty = $this->adjuster->calculateDifficulty('resource', $clientId);
        // 4 * 3.0 = 12，仍然在 20 以内
        $this->assertEquals(12, $difficulty);
        $this->assertLessThanOrEqual(20, $difficulty);
    }

    public function testCombinedHighSecurityAndHighAttempts(): void
    {
        $clientId = 'test-client-combined';
        $now = time();

        // 创建 10 个挑战
        for ($i = 1; $i <= 10; ++$i) {
            $challenge = new Challenge(
                "combined-{$i}",
                'hashcash',
                "challenge-{$i}",
                4,
                $now,
                $now + 300
            );
            $challenge->setClientId($clientId);
            $this->storage->save($challenge);
        }

        // 高安全资源 + 高尝试次数
        $difficulty = $this->adjuster->calculateDifficulty('login', $clientId);
        // 先应用尝试次数倍增 (4 * 1.5 = 6)，再应用资源倍增 (6 * 1.5 = 9)
        $this->assertEquals(9, $difficulty);
    }

    public function testDifferentClientsHaveIndependentDifficulty(): void
    {
        $now = time();

        // client-a 创建 10 个挑战
        for ($i = 1; $i <= 10; ++$i) {
            $challenge = new Challenge(
                "client-a-{$i}",
                'hashcash',
                "challenge-{$i}",
                4,
                $now,
                $now + 300
            );
            $challenge->setClientId('client-a');
            $this->storage->save($challenge);
        }

        // client-b 没有挑战

        // client-a 应该有更高的难度
        $difficultyA = $this->adjuster->calculateDifficulty('resource', 'client-a');
        $this->assertEquals(6, $difficultyA); // 4 * 1.5

        // client-b 应该使用基础难度
        $difficultyB = $this->adjuster->calculateDifficulty('resource', 'client-b');
        $this->assertEquals(4, $difficultyB);
    }

    public function testNormalResourceNoDifficultyIncrease(): void
    {
        // 普通资源不应该有额外的难度增加
        $resources = [
            'download',
            'view',
            'list',
            'search',
            'api-call',
        ];

        foreach ($resources as $resource) {
            $difficulty = $this->adjuster->calculateDifficulty($resource, null);
            $this->assertEquals(4, $difficulty, "Resource '{$resource}' should have base difficulty");
        }
    }
}
