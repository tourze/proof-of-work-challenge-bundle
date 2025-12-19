<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use Tourze\ProofOfWorkChallengeBundle\Controller\Admin\ChallengeApiController;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;
use Tourze\ProofOfWorkChallengeBundle\Storage\CacheChallengeStorage;

/**
 * ChallengeApiController API 控制器集成测试
 *
 * @internal
 */
#[CoversClass(ChallengeApiController::class)]
#[RunTestsInSeparateProcesses]
final class ChallengeApiControllerTest extends AbstractWebTestCase
{
    private CacheChallengeStorage $storage;
    private ChallengeApiController $controller;

    protected function onSetUp(): void
    {
        // 创建客户端以启动内核
        self::createClientWithDatabase();

        $this->storage = self::getService(CacheChallengeStorage::class);
        $this->controller = self::getService(ChallengeApiController::class);
        $this->cleanStorage();
    }

    private function cleanStorage(): void
    {
        $allChallenges = $this->storage->findAll();
        foreach ($allChallenges as $challenge) {
            $this->storage->delete($challenge->getId());
        }
    }

    public function testApiDataEndpointReturnsJsonResponse(): void
    {
        $request = new Request();
        $response = $this->controller->__invoke($request);

        $this->assertInstanceOf(JsonResponse::class, $response);

        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
    }

    public function testApiDataEndpointWithChallenges(): void
    {
        // 创建一些挑战数据
        $now = time();
        $challenge1 = new Challenge('test-id-1', 'hashcash', 'test-challenge-1', 4, $now, $now + 300);
        $challenge1->setResource('login');
        $challenge1->setClientId('client-1');

        $challenge2 = new Challenge('test-id-2', 'hashcash', 'test-challenge-2', 5, $now, $now + 600);
        $challenge2->setResource('register');

        $this->storage->save($challenge1);
        $this->storage->save($challenge2);

        $request = new Request();
        $response = $this->controller->__invoke($request);

        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertEquals(2, $data['total']);
        $this->assertCount(2, $data['data']);

        // 验证数据结构
        $challengeData = $data['data'][0];
        $this->assertArrayHasKey('id', $challengeData);
        $this->assertArrayHasKey('type', $challengeData);
        $this->assertArrayHasKey('difficulty', $challengeData);
        $this->assertArrayHasKey('createTime', $challengeData);
        $this->assertArrayHasKey('expireTime', $challengeData);
        $this->assertArrayHasKey('resource', $challengeData);
        $this->assertArrayHasKey('clientId', $challengeData);
        $this->assertArrayHasKey('used', $challengeData);
        $this->assertArrayHasKey('expired', $challengeData);
        $this->assertArrayHasKey('status', $challengeData);
        $this->assertArrayHasKey('ttl', $challengeData);
    }

    public function testApiDataEndpointEmptyStorage(): void
    {
        $request = new Request();
        $response = $this->controller->__invoke($request);

        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertEquals(0, $data['total']);
        $this->assertCount(0, $data['data']);
    }

    public function testChallengeStatusDisplay(): void
    {
        $now = time();

        // 创建一个已使用的挑战
        $usedChallenge = new Challenge('used-id', 'hashcash', 'test', 4, $now, $now + 300);
        $usedChallenge->markAsUsed();
        $this->storage->save($usedChallenge);

        // 创建一个活跃的挑战
        $activeChallenge = new Challenge('active-id', 'hashcash', 'test', 4, $now, $now + 300);
        $this->storage->save($activeChallenge);

        $request = new Request();
        $response = $this->controller->__invoke($request);

        $content = $response->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);

        $statuses = array_column($data['data'], 'status');
        $this->assertContains('已使用', $statuses);
        $this->assertContains('有效', $statuses);
    }

    #[Test]
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        // 由于控制器使用 #[Route] 属性限制为 GET 方法
        // 这里验证控制器路由属性配置正确
        // 实际的方法限制由路由系统处理，在集成测试中验证路由配置
        $reflection = new \ReflectionClass(ChallengeApiController::class);
        $invokeMethod = $reflection->getMethod('__invoke');
        $attributes = $invokeMethod->getAttributes(\Symfony\Component\Routing\Attribute\Route::class);

        $this->assertNotEmpty($attributes, 'Controller should have Route attribute');

        $routeAttribute = $attributes[0]->newInstance();
        $this->assertContains('GET', $routeAttribute->methods);
        $this->assertNotContains($method, $routeAttribute->methods);
    }
}
