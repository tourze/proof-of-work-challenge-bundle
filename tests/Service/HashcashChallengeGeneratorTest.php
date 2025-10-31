<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProofOfWorkChallengeBundle\Service\HashcashChallengeGenerator;

/**
 * @internal
 */
#[CoversClass(HashcashChallengeGenerator::class)]
final class HashcashChallengeGeneratorTest extends TestCase
{
    public function testGenerateChallenge(): void
    {
        $generator = new HashcashChallengeGenerator(300);
        $resource = 'login';
        $difficulty = 4;
        $clientId = 'client-123';

        $challenge = $generator->generate($resource, $difficulty, $clientId);

        $this->assertNotEmpty($challenge->getId());
        $this->assertEquals('hashcash', $challenge->getType());
        $this->assertNotEmpty($challenge->getChallenge());
        $this->assertEquals($difficulty, $challenge->getDifficulty());
        $this->assertEquals($resource, $challenge->getResource());
        $this->assertEquals($clientId, $challenge->getClientId());

        // Check challenge format
        $parts = explode(':', $challenge->getChallenge());
        $this->assertCount(5, $parts);
        $this->assertEquals($difficulty, (int) $parts[0]);
        $this->assertEquals($resource, $parts[2]);
        $this->assertEquals($clientId, $parts[4]);

        // Check metadata
        $metadata = $challenge->getMetadata();
        $this->assertArrayHasKey('random_data', $metadata);
        $this->assertArrayHasKey('algorithm', $metadata);
        $this->assertEquals('SHA-256', $metadata['algorithm']);

        // Check expiration
        $this->assertFalse($challenge->isExpired());
        $this->assertEquals(300, $challenge->getExpireTime() - $challenge->getCreateTime());
    }

    public function testGenerateWithoutClientId(): void
    {
        $generator = new HashcashChallengeGenerator();
        $resource = 'api-call';
        $difficulty = 6;

        $challenge = $generator->generate($resource, $difficulty, null);

        $this->assertNull($challenge->getClientId());

        // Check challenge format without client ID
        $parts = explode(':', $challenge->getChallenge());
        $this->assertCount(5, $parts);
        $this->assertEquals('', $parts[4]); // Empty client ID part
    }

    public function testCustomTimeLimit(): void
    {
        $timeLimit = 600; // 10 minutes
        $generator = new HashcashChallengeGenerator($timeLimit);
        $challenge = $generator->generate('resource', 4);

        $this->assertEquals($timeLimit, $challenge->getExpireTime() - $challenge->getCreateTime());
    }

    public function testUniqueIdGeneration(): void
    {
        $generator = new HashcashChallengeGenerator();

        $challenge1 = $generator->generate('resource', 4);
        $challenge2 = $generator->generate('resource', 4);

        $this->assertNotEquals($challenge1->getId(), $challenge2->getId());
        $this->assertNotEquals($challenge1->getChallenge(), $challenge2->getChallenge());
    }
}
