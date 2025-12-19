<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;
use Tourze\ProofOfWorkChallengeBundle\Procedure\IssueChallengeHandler;
use Tourze\ProofOfWorkChallengeBundle\Storage\CacheChallengeStorage;

/**
 * IssueChallengeHandler 集成测试
 *
 * @internal
 */
#[CoversClass(IssueChallengeHandler::class)]
#[RunTestsInSeparateProcesses]
final class IssueChallengeHandlerTest extends AbstractIntegrationTestCase
{
    private IssueChallengeHandler $handler;
    private CacheChallengeStorage $storage;

    protected function onSetUp(): void
    {
        $this->handler = self::getService(IssueChallengeHandler::class);
        $this->storage = self::getService(CacheChallengeStorage::class);

        // 清理存储中的所有挑战数据和客户端历史
        $this->cleanStorage();
    }

    private function cleanStorage(): void
    {
        // 清理挑战数据
        $allChallenges = $this->storage->findAll();
        foreach ($allChallenges as $challenge) {
            $this->storage->delete($challenge->getId());
        }

        // 清理测试用的客户端历史记录
        // 通过容器直接获取 cache.app 服务
        $cache = self::getContainer()->get('cache.app');
        $testClientIds = ['test-client', 'client-123', 'frequent-client'];
        foreach ($testClientIds as $clientId) {
            $cache->deleteItem('pow_challenge_history_' . $clientId);
        }
    }

    public function testIssueChallengeReturnsSuccessResponse(): void
    {
        $resource = 'test-resource';
        $clientId = 'test-client';

        $result = ($this->handler)($resource, $clientId);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('challenge', $result);
        $this->assertArrayHasKey('id', $result['challenge']);
        $this->assertArrayHasKey('type', $result['challenge']);
        $this->assertArrayHasKey('challenge', $result['challenge']);
        $this->assertArrayHasKey('difficulty', $result['challenge']);
        $this->assertArrayHasKey('expire_time', $result['challenge']);
        $this->assertArrayHasKey('resource', $result['challenge']);

        $this->assertEquals('hashcash', $result['challenge']['type']);
        $this->assertEquals($resource, $result['challenge']['resource']);
    }

    public function testIssueChallengeWithoutClientId(): void
    {
        $resource = 'anonymous-resource';

        $result = ($this->handler)($resource, null);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('challenge', $result);
        $this->assertEquals($resource, $result['challenge']['resource']);
    }

    public function testIssueChallengeStoresInStorage(): void
    {
        $resource = 'storage-test';
        $clientId = 'client-123';

        $result = ($this->handler)($resource, $clientId);

        // 验证挑战已存储
        $challengeId = $result['challenge']['id'];
        $storedChallenge = $this->storage->find($challengeId);

        $this->assertNotNull($storedChallenge);
        $this->assertEquals($challengeId, $storedChallenge->getId());
        $this->assertEquals($resource, $storedChallenge->getResource());
        $this->assertEquals($clientId, $storedChallenge->getClientId());
        $this->assertFalse($storedChallenge->isUsed());
        $this->assertFalse($storedChallenge->isExpired());
    }

    public function testIssueChallengeWithHighSecurityResource(): void
    {
        $resource = 'login';
        $clientId = 'test-client';

        $result = ($this->handler)($resource, $clientId);

        // 高安全资源应该有更高的难度（基础4 * 1.5 = 6）
        $this->assertTrue($result['success']);
        $this->assertEquals(6, $result['challenge']['difficulty']);
    }

    public function testIssueChallengeWithPaymentResource(): void
    {
        $resource = 'payment';
        $clientId = 'test-client';

        $result = ($this->handler)($resource, $clientId);

        // payment 资源应该有更高的难度
        $this->assertTrue($result['success']);
        $this->assertEquals(6, $result['challenge']['difficulty']);
    }

    public function testIssueChallengeWithNormalResource(): void
    {
        $resource = 'normal-action';
        $clientId = 'test-client';

        $result = ($this->handler)($resource, $clientId);

        // 普通资源应该有基础难度 4
        $this->assertTrue($result['success']);
        $this->assertEquals(4, $result['challenge']['difficulty']);
    }

    public function testIssueChallengeIncreaseDifficultyWithManyAttempts(): void
    {
        $resource = 'rate-limit-test';
        $clientId = 'frequent-client';
        $now = time();

        // 直接向存储中添加挑战记录来模拟历史尝试
        for ($i = 1; $i <= 10; ++$i) {
            $challenge = new Challenge(
                "rate-limit-{$i}",
                'hashcash',
                "challenge-{$i}",
                4,
                $now,
                $now + 300
            );
            $challenge->setClientId($clientId);
            $this->storage->save($challenge);
        }

        // 再次发起挑战，难度应该增加
        $result = ($this->handler)($resource, $clientId);

        $this->assertTrue($result['success']);
        // 难度应该因为频繁尝试而增加（10次尝试触发 1.5 倍增）
        // 基础难度4 * 1.5 = 6
        $this->assertGreaterThanOrEqual(6, $result['challenge']['difficulty']);
    }

    public function testIssueChallengeExpireTime(): void
    {
        $resource = 'expire-test';
        $clientId = 'test-client';

        $beforeTime = time();
        $result = ($this->handler)($resource, $clientId);
        $afterTime = time();

        $this->assertTrue($result['success']);

        // 默认过期时间为 300 秒
        $expireTime = $result['challenge']['expire_time'];
        $this->assertGreaterThanOrEqual($beforeTime + 300, $expireTime);
        $this->assertLessThanOrEqual($afterTime + 300 + 1, $expireTime); // +1 允许时间误差
    }

    public function testIssueChallengeGeneratesUniqueIds(): void
    {
        $resource = 'unique-test';
        $ids = [];

        for ($i = 0; $i < 10; ++$i) {
            $result = ($this->handler)($resource, null);
            $ids[] = $result['challenge']['id'];
        }

        // 所有 ID 应该唯一
        $this->assertEquals(10, count(array_unique($ids)));
    }

    public function testIssueChallengeHashcashFormat(): void
    {
        $resource = 'format-test';
        $clientId = 'client-123';

        $result = ($this->handler)($resource, $clientId);

        $challengeString = $result['challenge']['challenge'];

        // Hashcash 格式: difficulty:timestamp:resource:randomData:clientId
        $parts = explode(':', $challengeString);
        $this->assertGreaterThanOrEqual(5, count($parts));

        // 验证各部分
        $this->assertEquals((string) $result['challenge']['difficulty'], $parts[0]);
        $this->assertEquals($resource, $parts[2]);
    }
}
