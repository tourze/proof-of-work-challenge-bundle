<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;
use Tourze\ProofOfWorkChallengeBundle\Procedure\IssueChallengeHandler;
use Tourze\ProofOfWorkChallengeBundle\Procedure\VerifyChallengeHandler;
use Tourze\ProofOfWorkChallengeBundle\Storage\CacheChallengeStorage;

/**
 * VerifyChallengeHandler 集成测试
 *
 * @internal
 */
#[CoversClass(VerifyChallengeHandler::class)]
#[RunTestsInSeparateProcesses]
final class VerifyChallengeHandlerTest extends AbstractIntegrationTestCase
{
    private VerifyChallengeHandler $verifyHandler;
    private IssueChallengeHandler $issueHandler;
    private CacheChallengeStorage $storage;

    protected function onSetUp(): void
    {
        $this->verifyHandler = self::getService(VerifyChallengeHandler::class);
        $this->issueHandler = self::getService(IssueChallengeHandler::class);
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

    /**
     * 为挑战找到有效的 proof
     * 这是一个简单的暴力搜索，用于测试目的
     */
    private function findValidProof(string $challengeString, int $difficulty): string
    {
        $nonce = 0;
        while (true) {
            $proof = (string) $nonce;
            $hash = hash('sha256', $challengeString . ':' . $proof);
            $binaryHash = hex2bin($hash);

            if (false === $binaryHash) {
                ++$nonce;
                continue;
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

            if ($leadingZeroBits >= $difficulty) {
                return $proof;
            }

            ++$nonce;

            // 安全限制：防止无限循环
            if ($nonce > 10000000) {
                throw new \RuntimeException('Failed to find valid proof within limit');
            }
        }
    }

    public function testVerifyValidChallenge(): void
    {
        // 使用较低难度以加快测试
        $resource = 'test-verify';
        $clientId = 'test-client';

        // 创建一个难度为1的挑战用于测试
        $now = time();
        $challenge = new Challenge(
            'verify-test-id',
            'hashcash',
            '1:' . $now . ':test-verify:abc123:test-client',
            1,
            $now,
            $now + 300
        );
        $challenge->setResource($resource);
        $challenge->setClientId($clientId);
        $this->storage->save($challenge);

        // 找到有效的 proof
        $proof = $this->findValidProof($challenge->getChallenge(), $challenge->getDifficulty());

        $result = ($this->verifyHandler)($challenge->getId(), $proof);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('resource', $result);
        $this->assertArrayHasKey('client_id', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals($resource, $result['resource']);
        $this->assertEquals($clientId, $result['client_id']);
    }

    public function testVerifyChallengeNotFound(): void
    {
        $result = ($this->verifyHandler)('non-existent-id', 'some-proof');

        $this->assertFalse($result['success']);
        $this->assertEquals('CHALLENGE_NOT_FOUND', $result['code']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testVerifyExpiredChallenge(): void
    {
        // 创建一个已过期的挑战（直接存储到缓存会被拒绝，所以我们需要手动构建场景）
        // 由于缓存存储会根据 expireTime 设置过期，我们需要使用一个特殊的方法
        // 这里我们创建一个已标记为过期的挑战

        $now = time();
        // 创建一个即将过期的挑战，然后等待它过期
        $challenge = new Challenge(
            'expired-test-id',
            'hashcash',
            '4:' . ($now - 600) . ':test:random:client',
            4,
            $now - 600,
            $now - 300 // 已过期
        );
        $challenge->setResource('test');

        // 直接通过反射或手动方式创建过期场景比较复杂
        // 我们使用另一种方式：创建有效挑战，然后模拟过期检查
        // 实际上，CacheChallengeStorage 无法保存已过期的挑战
        // 所以我们使用 Entity 的 isExpired() 方法来验证逻辑

        // 创建一个有效挑战
        $validChallenge = new Challenge(
            'will-expire-test',
            'hashcash',
            '4:' . $now . ':test:random:client',
            4,
            $now,
            $now + 1 // 1秒后过期
        );
        $this->storage->save($validChallenge);

        // 等待挑战过期
        sleep(2);

        $result = ($this->verifyHandler)('will-expire-test', 'some-proof');

        $this->assertFalse($result['success']);
        $this->assertEquals('CHALLENGE_EXPIRED', $result['code']);
    }

    public function testVerifyUsedChallenge(): void
    {
        $now = time();
        $challenge = new Challenge(
            'used-test-id',
            'hashcash',
            '1:' . $now . ':test:abc:client',
            1,
            $now,
            $now + 300
        );
        $challenge->setResource('test');

        // 先找到有效 proof
        $proof = $this->findValidProof($challenge->getChallenge(), $challenge->getDifficulty());

        // 保存挑战
        $this->storage->save($challenge);

        // 第一次验证应该成功
        $result1 = ($this->verifyHandler)($challenge->getId(), $proof);
        $this->assertTrue($result1['success']);

        // 第二次验证应该失败（挑战已使用）
        $result2 = ($this->verifyHandler)($challenge->getId(), $proof);
        $this->assertFalse($result2['success']);
        $this->assertEquals('CHALLENGE_ALREADY_USED', $result2['code']);
    }

    public function testVerifyInvalidProof(): void
    {
        $now = time();
        $challenge = new Challenge(
            'invalid-proof-test',
            'hashcash',
            '10:' . $now . ':test:random:client', // 高难度确保 'invalid' 不会意外通过
            10,
            $now,
            $now + 300
        );
        $challenge->setResource('test');
        $this->storage->save($challenge);

        $result = ($this->verifyHandler)($challenge->getId(), 'definitely-invalid-proof');

        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_PROOF', $result['code']);
    }

    public function testVerifySuccessMarksAsUsed(): void
    {
        $now = time();
        $challenge = new Challenge(
            'mark-used-test',
            'hashcash',
            '1:' . $now . ':test:xyz:client',
            1,
            $now,
            $now + 300
        );
        $challenge->setResource('test');
        $this->storage->save($challenge);

        // 验证挑战未使用
        $storedBefore = $this->storage->find($challenge->getId());
        $this->assertNotNull($storedBefore);
        $this->assertFalse($storedBefore->isUsed());

        // 找到有效 proof 并验证
        $proof = $this->findValidProof($challenge->getChallenge(), $challenge->getDifficulty());
        $result = ($this->verifyHandler)($challenge->getId(), $proof);

        $this->assertTrue($result['success']);

        // 验证挑战已标记为使用
        $storedAfter = $this->storage->find($challenge->getId());
        $this->assertNotNull($storedAfter);
        $this->assertTrue($storedAfter->isUsed());
    }

    public function testVerifyReturnsMetadata(): void
    {
        $now = time();
        $challenge = new Challenge(
            'metadata-test',
            'hashcash',
            '1:' . $now . ':test:meta:client',
            1,
            $now,
            $now + 300
        );
        $challenge->setResource('test-resource');
        $challenge->setClientId('test-client');
        $challenge->setMetadata(['custom_key' => 'custom_value']);
        $this->storage->save($challenge);

        $proof = $this->findValidProof($challenge->getChallenge(), $challenge->getDifficulty());
        $result = ($this->verifyHandler)($challenge->getId(), $proof);

        $this->assertTrue($result['success']);
        $this->assertEquals('test-resource', $result['resource']);
        $this->assertEquals('test-client', $result['client_id']);
        $this->assertIsArray($result['metadata']);
        $this->assertEquals('custom_value', $result['metadata']['custom_key']);
    }
}
