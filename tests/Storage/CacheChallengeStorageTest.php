<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;
use Tourze\ProofOfWorkChallengeBundle\Storage\CacheChallengeStorage;

/**
 * @internal
 */
#[CoversClass(CacheChallengeStorage::class)]
final class CacheChallengeStorageTest extends TestCase
{
    private CacheItemPoolInterface $cache;

    private CacheChallengeStorage $storage;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->storage = new CacheChallengeStorage($this->cache, 'test_');
    }

    public function testSaveChallenge(): void
    {
        $challenge = new Challenge(
            'test-id',
            'hashcash',
            'test-challenge',
            4,
            time(),
            time() + 300
        );

        $challengeItem = $this->createMock(CacheItemInterface::class);
        $indexItem = $this->createMock(CacheItemInterface::class);

        // Mock getItem calls (challenge + index get + index save = 3 calls)
        $this->cache->expects($this->exactly(3))
            ->method('getItem')
            ->willReturnCallback(function ($key) use ($challengeItem, $indexItem) {
                if ('test_test-id' === $key) {
                    return $challengeItem;
                }
                if ('test_index' === $key) {
                    return $indexItem;
                }
                throw new \InvalidArgumentException("Unexpected key: {$key}");
            })
        ;

        // Mock challenge item operations
        $challengeItem->expects($this->once())
            ->method('set')
            ->with($challenge->toArray())
        ;

        $challengeItem->expects($this->once())
            ->method('expiresAt')
            ->with(self::isInstanceOf(\DateTime::class))
        ;

        // Mock index item operations
        $indexItem->expects($this->atLeastOnce())
            ->method('isHit')
            ->willReturn(false) // index doesn't exist yet
        ;

        $indexItem->expects($this->atLeastOnce())
            ->method('set')
            ->with(['test-id'])
        ;

        $indexItem->expects($this->atLeastOnce())
            ->method('expiresAfter')
            ->with(86400 * 7) // 7 days
        ;

        // Expect save to be called twice (for challenge and index)
        $this->cache->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function ($item) use ($challengeItem, $indexItem) {
                self::assertThat($item, self::logicalOr(
                    self::identicalTo($challengeItem),
                    self::identicalTo($indexItem)
                ));

                return true;
            })
        ;

        $this->storage->save($challenge);
    }

    public function testSaveChallengeWithClientId(): void
    {
        $challenge = new Challenge(
            'test-id',
            'hashcash',
            'test-challenge',
            4,
            time(),
            time() + 300
        );
        $challenge->setClientId('client-123');

        $challengeItem = $this->createMock(CacheItemInterface::class);
        $indexItem = $this->createMock(CacheItemInterface::class);
        $historyItem = $this->createMock(CacheItemInterface::class);

        // Now expects 4 calls: challenge + index (2 calls) + history (1 call)
        $this->cache->expects($this->exactly(4))
            ->method('getItem')
            ->willReturnCallback(function ($key) use ($challengeItem, $indexItem, $historyItem) {
                if ('test_test-id' === $key) {
                    return $challengeItem;
                }
                if ('test_index' === $key) {
                    return $indexItem;
                }
                if ('test_history_client-123' === $key) {
                    return $historyItem;
                }
                throw new \InvalidArgumentException("Unexpected key: {$key}");
            })
        ;

        // Challenge item operations
        $challengeItem->expects($this->once())
            ->method('set')
            ->with($challenge->toArray())
        ;

        $challengeItem->expects($this->once())
            ->method('expiresAt')
        ;

        // Index item operations
        $indexItem->expects($this->atLeastOnce())
            ->method('isHit')
            ->willReturn(false)
        ;

        $indexItem->expects($this->atLeastOnce())
            ->method('set')
            ->with(['test-id'])
        ;

        $indexItem->expects($this->atLeastOnce())
            ->method('expiresAfter')
            ->with(86400 * 7)
        ;

        // History item operations
        $historyItem->expects($this->atLeastOnce())
            ->method('isHit')
            ->willReturn(false)
        ;

        $historyItem->expects($this->atLeastOnce())
            ->method('set')
            ->with(self::callback(fn ($value) => is_array($value)))
        ;

        $historyItem->expects($this->atLeastOnce())
            ->method('expiresAfter')
            ->with(86400)
        ;

        // Expect save to be called 3 times (challenge + index + history)
        $this->cache->expects($this->exactly(3))
            ->method('save')
        ;

        $this->storage->save($challenge);
    }

    public function testFindChallenge(): void
    {
        $challengeData = [
            'id' => 'test-id',
            'type' => 'hashcash',
            'challenge' => 'test-challenge',
            'difficulty' => 4,
            'create_time' => time(),
            'expire_time' => time() + 300,
            'resource' => 'login',
            'client_id' => 'client-123',
            'used' => false,
            'metadata' => ['key' => 'value'],
        ];

        $cacheItem = $this->createMock(CacheItemInterface::class);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('test_test-id')
            ->willReturn($cacheItem)
        ;

        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true)
        ;

        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($challengeData)
        ;

        $challenge = $this->storage->find('test-id');

        $this->assertNotNull($challenge);
        $this->assertEquals('test-id', $challenge->getId());
        $this->assertEquals('hashcash', $challenge->getType());
        $this->assertEquals('login', $challenge->getResource());
        $this->assertEquals('client-123', $challenge->getClientId());
        $this->assertFalse($challenge->isUsed());
        $this->assertEquals(['key' => 'value'], $challenge->getMetadata());
    }

    public function testFindNonExistentChallenge(): void
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('test_non-existent')
            ->willReturn($cacheItem)
        ;

        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false)
        ;

        $challenge = $this->storage->find('non-existent');
        $this->assertNull($challenge);
    }

    public function testMarkAsUsed(): void
    {
        $challengeData = [
            'id' => 'test-id',
            'type' => 'hashcash',
            'challenge' => 'test-challenge',
            'difficulty' => 4,
            'create_time' => time(),
            'expire_time' => time() + 300,
            'used' => false,
        ];

        $findItem = $this->createMock(CacheItemInterface::class);
        $saveItem = $this->createMock(CacheItemInterface::class);
        $indexItem1 = $this->createMock(CacheItemInterface::class);
        $indexItem2 = $this->createMock(CacheItemInterface::class);

        $this->cache->expects($this->exactly(3))
            ->method('getItem')
            ->willReturnCallback(function (string $key) use ($findItem, $saveItem, $indexItem1) {
                static $calls = 0;
                ++$calls;

                if (str_ends_with($key, 'test-id')) {
                    return 1 === $calls ? $findItem : $saveItem;
                }

                return $indexItem1;
            })
        ;

        $findItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true)
        ;

        $findItem->expects($this->once())
            ->method('get')
            ->willReturn($challengeData)
        ;

        $saveItem->expects($this->once())
            ->method('set')
            ->with(self::callback(function ($data) {
                return is_array($data) && true === $data['used'];
            }))
        ;

        $saveItem->expects($this->once())
            ->method('expiresAt')
        ;

        // Mock index operations - ID already exists in index
        $indexItem1->expects($this->once())
            ->method('isHit')
            ->willReturn(true)
        ;

        $indexItem1->expects($this->once())
            ->method('get')
            ->willReturn(['test-id']) // ID already exists, so no saveIndex call
        ;

        $this->cache->expects($this->once())
            ->method('save')
            ->with($saveItem)
        ;

        $this->storage->markAsUsed('test-id');
    }

    public function testDelete(): void
    {
        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with('test_test-id')
        ;

        $this->storage->delete('test-id');
    }

    public function testCountRecentAttempts(): void
    {
        $history = [
            time() - 100 => 'id1',
            time() - 200 => 'id2',
            time() - 3700 => 'id3', // Older than 1 hour
        ];

        $cacheItem = $this->createMock(CacheItemInterface::class);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('test_history_client-123')
            ->willReturn($cacheItem)
        ;

        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true)
        ;

        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn($history)
        ;

        $count = $this->storage->countRecentAttempts('client-123', 3600);
        $this->assertEquals(2, $count); // Only 2 attempts within the last hour
    }

    public function testCountRecentAttemptsNoHistory(): void
    {
        $cacheItem = $this->createMock(CacheItemInterface::class);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('test_history_client-123')
            ->willReturn($cacheItem)
        ;

        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false)
        ;

        $count = $this->storage->countRecentAttempts('client-123');
        $this->assertEquals(0, $count);
    }

    public function testDeleteExpired(): void
    {
        $deletedCount = $this->storage->deleteExpired();
        $this->assertEquals(0, $deletedCount);
    }

    public function testFindAll(): void
    {
        $indexItem = $this->createMock(CacheItemInterface::class);
        $challengeItem = $this->createMock(CacheItemInterface::class);

        // Mock index retrieval
        $this->cache->expects($this->exactly(2))
            ->method('getItem')
            ->willReturnCallback(function ($key) use ($indexItem, $challengeItem) {
                if ('test_index' === $key) {
                    return $indexItem;
                }
                if ('test_test-id' === $key) {
                    return $challengeItem;
                }
                throw new \InvalidArgumentException("Unexpected key: {$key}");
            })
        ;

        $indexItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true)
        ;

        $indexItem->expects($this->once())
            ->method('get')
            ->willReturn(['test-id'])
        ;

        // Mock challenge retrieval
        $challengeItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true)
        ;

        $challengeData = [
            'id' => 'test-id',
            'type' => 'hashcash',
            'challenge' => 'test-challenge',
            'difficulty' => 4,
            'create_time' => time(),
            'expire_time' => time() + 300,
        ];

        $challengeItem->expects($this->once())
            ->method('get')
            ->willReturn($challengeData)
        ;

        $challenges = $this->storage->findAll();
        $this->assertCount(1, $challenges);
        $this->assertEquals('test-id', $challenges[0]->getId());
    }

    public function testFindAllWithEmptyIndex(): void
    {
        $indexItem = $this->createMock(CacheItemInterface::class);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with('test_index')
            ->willReturn($indexItem)
        ;

        $indexItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false)
        ;

        $challenges = $this->storage->findAll();
        $this->assertEmpty($challenges);
    }
}
