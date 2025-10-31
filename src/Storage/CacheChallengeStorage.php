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

        return $this->hydrate($data);
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
        $challenge = new Challenge(
            $data['id'],
            $data['type'],
            $data['challenge'],
            $data['difficulty'],
            $data['create_time'],
            $data['expire_time']
        );

        if (isset($data['resource'])) {
            $challenge->setResource($data['resource']);
        }

        if (isset($data['client_id'])) {
            $challenge->setClientId($data['client_id']);
        }

        if (isset($data['metadata'])) {
            $challenge->setMetadata($data['metadata']);
        }

        if (isset($data['used']) && true === $data['used']) {
            $challenge->markAsUsed();
        }

        return $challenge;
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

        return is_array($index) ? $index : [];
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
