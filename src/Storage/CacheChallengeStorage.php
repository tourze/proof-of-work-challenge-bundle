<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Storage;

use Psr\Cache\CacheItemPoolInterface;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;

class CacheChallengeStorage implements ChallengeStorageInterface
{
    private CacheItemPoolInterface $cache;

    private string $prefix;

    private string $indexKey;

    public function __construct(CacheItemPoolInterface $cache, string $prefix = 'pow_challenge_')
    {
        $this->cache = $cache;
        $this->prefix = $prefix;
        $this->indexKey = $prefix . 'index';
    }

    public function save(Challenge $challenge): void
    {
        $item = $this->cache->getItem($this->prefix . $challenge->getId());
        $item->set($challenge->toArray());
        $item->expiresAt(new \DateTime('@' . $challenge->getExpireTime()));
        $this->cache->save($item);

        // 更新索引
        $this->addToIndex($challenge->getId());

        if (null !== $challenge->getClientId()) {
            $this->addToClientHistory($challenge->getClientId(), $challenge->getId());
        }
    }

    public function find(string $id): ?Challenge
    {
        $item = $this->cache->getItem($this->prefix . $id);
        if (!$item->isHit()) {
            return null;
        }

        $data = $item->get();
        if (!is_array($data)) {
            return null;
        }

        // 确保数组键为字符串类型
        $stringKeyedData = [];
        foreach ($data as $key => $value) {
            $stringKeyedData[(string) $key] = $value;
        }

        return $this->hydrate($stringKeyedData);
    }

    public function markAsUsed(string $id): void
    {
        $challenge = $this->find($id);
        if (null === $challenge) {
            return;
        }

        $challenge->markAsUsed();
        $this->save($challenge);
    }

    public function findAll(): array
    {
        $index = $this->getIndex();
        $challenges = [];

        foreach ($index as $id) {
            $challenge = $this->find($id);
            if (null !== $challenge) {
                $challenges[] = $challenge;
            } else {
                // 如果挑战不存在，从索引中移除
                $this->removeFromIndex($id);
            }
        }

        return $challenges;
    }

    public function delete(string $id): void
    {
        $this->cache->deleteItem($this->prefix . $id);
        $this->removeFromIndex($id);
    }

    public function deleteExpired(): int
    {
        return 0;
    }

    public function countRecentAttempts(string $clientId, int $seconds = 3600): int
    {
        $historyKey = $this->prefix . 'history_' . $clientId;
        $item = $this->cache->getItem($historyKey);

        if (!$item->isHit()) {
            return 0;
        }

        $history = $item->get();
        if (!is_array($history)) {
            return 0;
        }

        $cutoff = time() - $seconds;
        $count = 0;

        foreach ($history as $timestamp => $challengeId) {
            if ($timestamp >= $cutoff) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(array $data): Challenge
    {
        $challenge = $this->createChallengeFromData($data);
        $this->populateOptionalFields($challenge, $data);
        $this->markAsUsedIfNeeded($challenge, $data);

        return $challenge;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createChallengeFromData(array $data): Challenge
    {
        $id = isset($data['id']) ? $this->convertToString($data['id']) : '';
        $type = isset($data['type']) ? $this->convertToString($data['type']) : '';
        $challenge = isset($data['challenge']) ? $this->convertToString($data['challenge']) : '';
        $difficulty = isset($data['difficulty']) ? $this->convertToInt($data['difficulty']) : 0;
        $createTime = isset($data['create_time']) ? $this->convertToInt($data['create_time']) : 0;
        $expireTime = isset($data['expire_time']) ? $this->convertToInt($data['expire_time']) : 0;

        return new Challenge($id, $type, $challenge, $difficulty, $createTime, $expireTime);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function populateOptionalFields(Challenge $challenge, array $data): void
    {
        if (isset($data['resource'])) {
            $challenge->setResource($this->convertToString($data['resource']));
        }

        if (isset($data['client_id'])) {
            $challenge->setClientId($this->convertToString($data['client_id']));
        }

        if (isset($data['metadata'])) {
            $metadata = $data['metadata'];
            if (is_array($metadata)) {
                $stringKeyedMetadata = [];
                foreach ($metadata as $key => $value) {
                    $stringKeyedMetadata[(string) $key] = $value;
                }
                $challenge->setMetadata($stringKeyedMetadata);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function markAsUsedIfNeeded(Challenge $challenge, array $data): void
    {
        if (isset($data['used']) && true === $data['used']) {
            $challenge->markAsUsed();
        }
    }

    /**
     * 安全地将 mixed 类型转换为 string
     */
    private function convertToString(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_numeric($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            null === $value => '',
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            default => '',
        };
    }

    /**
     * 安全地将 mixed 类型转换为 int
     */
    private function convertToInt(mixed $value): int
    {
        return match (true) {
            is_int($value) => $value,
            is_string($value) && is_numeric($value) => (int) $value,
            is_bool($value) => $value ? 1 : 0,
            is_float($value) => (int) $value,
            default => 0,
        };
    }

    private function addToClientHistory(string $clientId, string $challengeId): void
    {
        $historyKey = $this->prefix . 'history_' . $clientId;
        $item = $this->cache->getItem($historyKey);

        $history = $item->isHit() ? $item->get() : [];
        if (!is_array($history)) {
            $history = [];
        }

        $history[time()] = $challengeId;

        $cutoff = time() - 86400;
        $history = array_filter($history, fn ($timestamp) => $timestamp >= $cutoff, ARRAY_FILTER_USE_KEY);

        $item->set($history);
        $item->expiresAfter(86400);
        $this->cache->save($item);
    }

    /**
     * 获取挑战索引
     *
     * @return string[]
     */
    private function getIndex(): array
    {
        $item = $this->cache->getItem($this->indexKey);

        if (!$item->isHit()) {
            return [];
        }

        $index = $item->get();

        if (!is_array($index)) {
            return [];
        }

        // 确保所有元素都是字符串
        $stringIndex = [];
        foreach ($index as $indexItem) {
            $stringIndex[] = $this->convertToString($indexItem);
        }

        return $stringIndex;
    }

    /**
     * 添加挑战ID到索引
     */
    private function addToIndex(string $challengeId): void
    {
        $index = $this->getIndex();

        if (!in_array($challengeId, $index, true)) {
            $index[] = $challengeId;
            $this->saveIndex($index);
        }
    }

    /**
     * 从索引中移除挑战ID
     */
    private function removeFromIndex(string $challengeId): void
    {
        $index = $this->getIndex();
        $key = array_search($challengeId, $index, true);

        if (false !== $key) {
            unset($index[$key]);
            $this->saveIndex(array_values($index));
        }
    }

    /**
     * 保存索引
     *
     * @param string[] $index
     */
    private function saveIndex(array $index): void
    {
        $item = $this->cache->getItem($this->indexKey);
        $item->set($index);
        $item->expiresAfter(86400 * 7); // 索引保存7天
        $this->cache->save($item);
    }
}
