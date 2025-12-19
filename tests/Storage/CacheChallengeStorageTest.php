<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;
use Tourze\ProofOfWorkChallengeBundle\Storage\CacheChallengeStorage;

/**
 * CacheChallengeStorage 集成测试
 *
 * @internal
 */
#[CoversClass(CacheChallengeStorage::class)]
#[RunTestsInSeparateProcesses]
final class CacheChallengeStorageTest extends AbstractIntegrationTestCase
{
    private CacheChallengeStorage $storage;

    protected function onSetUp(): void
    {
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

    public function testSaveChallenge(): void
    {
        $now = time();
        $challenge = new Challenge(
            'test-save-id',
            'hashcash',
            'test-challenge-string',
            4,
            $now,
            $now + 300
        );
        $challenge->setResource('login');
        $challenge->setClientId('client-123');

        $this->storage->save($challenge);

        // 验证保存成功
        $stored = $this->storage->find('test-save-id');
        $this->assertNotNull($stored);
        $this->assertEquals('test-save-id', $stored->getId());
        $this->assertEquals('hashcash', $stored->getType());
        $this->assertEquals('test-challenge-string', $stored->getChallenge());
        $this->assertEquals(4, $stored->getDifficulty());
        $this->assertEquals('login', $stored->getResource());
        $this->assertEquals('client-123', $stored->getClientId());
        $this->assertFalse($stored->isUsed());
    }

    public function testSaveChallengeWithoutClientId(): void
    {
        $now = time();
        $challenge = new Challenge(
            'test-no-client-id',
            'hashcash',
            'test-challenge',
            4,
            $now,
            $now + 300
        );

        $this->storage->save($challenge);

        $stored = $this->storage->find('test-no-client-id');
        $this->assertNotNull($stored);
        $this->assertNull($stored->getClientId());
    }

    public function testSaveChallengeWithMetadata(): void
    {
        $now = time();
        $challenge = new Challenge(
            'test-metadata-id',
            'hashcash',
            'test-challenge',
            4,
            $now,
            $now + 300
        );
        $challenge->setMetadata(['key1' => 'value1', 'key2' => 'value2']);

        $this->storage->save($challenge);

        $stored = $this->storage->find('test-metadata-id');
        $this->assertNotNull($stored);
        $this->assertEquals(['key1' => 'value1', 'key2' => 'value2'], $stored->getMetadata());
    }

    public function testFindNonExistentChallenge(): void
    {
        $challenge = $this->storage->find('non-existent-id');
        $this->assertNull($challenge);
    }

    public function testMarkAsUsed(): void
    {
        $now = time();
        $challenge = new Challenge(
            'test-mark-used-id',
            'hashcash',
            'test-challenge',
            4,
            $now,
            $now + 300
        );
        $this->storage->save($challenge);

        // 验证初始状态
        $stored = $this->storage->find('test-mark-used-id');
        $this->assertNotNull($stored);
        $this->assertFalse($stored->isUsed());

        // 标记为已使用
        $this->storage->markAsUsed('test-mark-used-id');

        // 验证状态更新
        $updated = $this->storage->find('test-mark-used-id');
        $this->assertNotNull($updated);
        $this->assertTrue($updated->isUsed());
    }

    public function testMarkAsUsedForNonExistentChallenge(): void
    {
        // 这不应该抛出异常
        $this->storage->markAsUsed('non-existent-id');
        $this->assertTrue(true); // 如果能到达这里，说明没有异常
    }

    public function testDelete(): void
    {
        $now = time();
        $challenge = new Challenge(
            'test-delete-id',
            'hashcash',
            'test-challenge',
            4,
            $now,
            $now + 300
        );
        $this->storage->save($challenge);

        // 验证保存成功
        $this->assertNotNull($this->storage->find('test-delete-id'));

        // 删除
        $this->storage->delete('test-delete-id');

        // 验证删除成功
        $this->assertNull($this->storage->find('test-delete-id'));
    }

    public function testFindAll(): void
    {
        $now = time();

        // 创建多个挑战
        for ($i = 1; $i <= 3; ++$i) {
            $challenge = new Challenge(
                "test-all-{$i}",
                'hashcash',
                "challenge-{$i}",
                4,
                $now,
                $now + 300
            );
            $this->storage->save($challenge);
        }

        $allChallenges = $this->storage->findAll();
        $this->assertCount(3, $allChallenges);

        $ids = array_map(fn ($c) => $c->getId(), $allChallenges);
        $this->assertContains('test-all-1', $ids);
        $this->assertContains('test-all-2', $ids);
        $this->assertContains('test-all-3', $ids);
    }

    public function testFindAllEmptyStorage(): void
    {
        $allChallenges = $this->storage->findAll();
        $this->assertEmpty($allChallenges);
    }

    public function testCountRecentAttempts(): void
    {
        $now = time();
        $clientId = 'rate-limit-client';

        // 创建多个挑战
        for ($i = 1; $i <= 5; ++$i) {
            $challenge = new Challenge(
                "attempt-{$i}",
                'hashcash',
                "challenge-{$i}",
                4,
                $now,
                $now + 300
            );
            $challenge->setClientId($clientId);
            $this->storage->save($challenge);
        }

        // 计算最近尝试次数
        $count = $this->storage->countRecentAttempts($clientId, 3600);
        $this->assertEquals(5, $count);
    }

    public function testCountRecentAttemptsNoHistory(): void
    {
        $count = $this->storage->countRecentAttempts('no-history-client', 3600);
        $this->assertEquals(0, $count);
    }

    public function testCountRecentAttemptsWithDifferentClients(): void
    {
        $now = time();

        // 为 client-1 创建 3 个挑战
        for ($i = 1; $i <= 3; ++$i) {
            $challenge = new Challenge(
                "client1-attempt-{$i}",
                'hashcash',
                "challenge-{$i}",
                4,
                $now,
                $now + 300
            );
            $challenge->setClientId('client-1');
            $this->storage->save($challenge);
        }

        // 为 client-2 创建 2 个挑战
        for ($i = 1; $i <= 2; ++$i) {
            $challenge = new Challenge(
                "client2-attempt-{$i}",
                'hashcash',
                "challenge-{$i}",
                4,
                $now,
                $now + 300
            );
            $challenge->setClientId('client-2');
            $this->storage->save($challenge);
        }

        // 验证各客户端的计数
        $this->assertEquals(3, $this->storage->countRecentAttempts('client-1', 3600));
        $this->assertEquals(2, $this->storage->countRecentAttempts('client-2', 3600));
    }

    public function testDeleteExpired(): void
    {
        // deleteExpired 当前返回 0（可能是缓存自动处理过期）
        $deletedCount = $this->storage->deleteExpired();
        $this->assertEquals(0, $deletedCount);
    }

    public function testSaveUpdatesExistingChallenge(): void
    {
        $now = time();
        $challenge = new Challenge(
            'update-test-id',
            'hashcash',
            'original-challenge',
            4,
            $now,
            $now + 300
        );
        $challenge->setResource('original-resource');
        $this->storage->save($challenge);

        // 更新挑战
        $challenge->setResource('updated-resource');
        $challenge->markAsUsed();
        $this->storage->save($challenge);

        // 验证更新
        $stored = $this->storage->find('update-test-id');
        $this->assertNotNull($stored);
        $this->assertEquals('updated-resource', $stored->getResource());
        $this->assertTrue($stored->isUsed());
    }

    public function testFindAllRemovesStaleIndexEntries(): void
    {
        $now = time();

        // 创建并保存一个挑战
        $challenge = new Challenge(
            'stale-test-id',
            'hashcash',
            'test',
            4,
            $now,
            $now + 300
        );
        $this->storage->save($challenge);

        // 手动删除挑战（但保留索引）
        $this->storage->delete('stale-test-id');

        // findAll 应该能处理索引中的陈旧条目
        $allChallenges = $this->storage->findAll();
        $this->assertEmpty($allChallenges);
    }

    public function testChallengeExpiresByCache(): void
    {
        $now = time();

        // 创建一个即将过期的挑战
        $challenge = new Challenge(
            'cache-expire-test',
            'hashcash',
            'test',
            4,
            $now,
            $now + 1 // 1秒后过期
        );
        $this->storage->save($challenge);

        // 立即查询应该能找到
        $stored = $this->storage->find('cache-expire-test');
        $this->assertNotNull($stored);

        // 等待过期
        sleep(2);

        // 由于缓存项已过期，查询可能返回 null
        // 但这取决于缓存驱动的行为
        // 这里我们只验证查询不会抛出异常
        $expiredStored = $this->storage->find('cache-expire-test');
        // 结果可能为 null（缓存自动清除）或非 null（需要手动清除）
        // 这取决于底层缓存实现
        $this->assertTrue($expiredStored === null || $expiredStored->isExpired());
    }
}
