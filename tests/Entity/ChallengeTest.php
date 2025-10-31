<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;

/**
 * @internal
 */
#[CoversClass(Challenge::class)]
final class ChallengeTest extends TestCase
{
    public function testChallengeCreation(): void
    {
        $id = 'test-id';
        $type = 'hashcash';
        $challengeString = 'test-challenge';
        $difficulty = 4;
        $createTime = time();
        $expireTime = $createTime + 300;

        $challenge = new Challenge(
            $id,
            $type,
            $challengeString,
            $difficulty,
            $createTime,
            $expireTime
        );

        $this->assertEquals($id, $challenge->getId());
        $this->assertEquals($type, $challenge->getType());
        $this->assertEquals($challengeString, $challenge->getChallenge());
        $this->assertEquals($difficulty, $challenge->getDifficulty());
        $this->assertEquals($createTime, $challenge->getCreateTime());
        $this->assertEquals($expireTime, $challenge->getExpireTime());
        $this->assertNull($challenge->getResource());
        $this->assertNull($challenge->getClientId());
        $this->assertFalse($challenge->isUsed());
        $this->assertFalse($challenge->isExpired());
        $this->assertEquals([], $challenge->getMetadata());
    }

    public function testSettersAndGetters(): void
    {
        $challenge = new Challenge(
            'id',
            'type',
            'challenge',
            4,
            time(),
            time() + 300
        );

        $challenge->setResource('login');
        $this->assertEquals('login', $challenge->getResource());

        $challenge->setClientId('client-123');
        $this->assertEquals('client-123', $challenge->getClientId());

        $challenge->markAsUsed();
        $this->assertTrue($challenge->isUsed());

        $metadata = ['key' => 'value'];
        $challenge->setMetadata($metadata);
        $this->assertEquals($metadata, $challenge->getMetadata());

        $challenge->addMetadata('another', 'data');
        $this->assertEquals(['key' => 'value', 'another' => 'data'], $challenge->getMetadata());
    }

    public function testExpiredChallenge(): void
    {
        $challenge = new Challenge(
            'id',
            'type',
            'challenge',
            4,
            time() - 600,
            time() - 300
        );

        $this->assertTrue($challenge->isExpired());
    }

    public function testToArray(): void
    {
        $id = 'test-id';
        $type = 'hashcash';
        $challengeString = 'test-challenge';
        $difficulty = 4;
        $createTime = time();
        $expireTime = $createTime + 300;
        $resource = 'login';
        $clientId = 'client-123';
        $metadata = ['key' => 'value'];

        $challenge = new Challenge(
            $id,
            $type,
            $challengeString,
            $difficulty,
            $createTime,
            $expireTime
        );
        $challenge->setResource($resource);
        $challenge->setClientId($clientId);
        $challenge->setMetadata($metadata);
        $challenge->markAsUsed();

        $array = $challenge->toArray();

        $this->assertEquals($id, $array['id']);
        $this->assertEquals($type, $array['type']);
        $this->assertEquals($challengeString, $array['challenge']);
        $this->assertEquals($difficulty, $array['difficulty']);
        $this->assertEquals($createTime, $array['create_time']);
        $this->assertEquals($expireTime, $array['expire_time']);
        $this->assertEquals($resource, $array['resource']);
        $this->assertEquals($clientId, $array['client_id']);
        $this->assertTrue($array['used']);
        $this->assertEquals($metadata, $array['metadata']);
    }
}
