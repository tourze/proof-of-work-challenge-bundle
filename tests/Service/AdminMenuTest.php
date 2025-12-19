<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;
use Tourze\ProofOfWorkChallengeBundle\Service\AdminMenu;
use Tourze\ProofOfWorkChallengeBundle\Storage\CacheChallengeStorage;

/**
 * AdminMenu 服务集成测试
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private CacheChallengeStorage $storage;

    private AdminMenu $adminMenu;

    protected function onSetUp(): void
    {
        // 使用真实的存储服务
        $this->storage = self::getService(CacheChallengeStorage::class);
        $this->adminMenu = self::getService(AdminMenu::class);

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

    public function testGetMenuItemsReturnsCorrectStructure(): void
    {
        // 创建一个活跃的挑战
        $challenge = new Challenge(
            'test-id',
            'hashcash',
            'test-challenge',
            20,
            time(),
            time() + 3600
        );
        $this->storage->save($challenge);

        $menuItems = $this->adminMenu->getMenuItems();

        $this->assertIsArray($menuItems);
        $this->assertArrayHasKey('proof_of_work_challenges', $menuItems);

        $challengeMenu = $menuItems['proof_of_work_challenges'];
        $this->assertIsArray($challengeMenu);
        $this->assertArrayHasKey('label', $challengeMenu);
        $this->assertArrayHasKey('icon', $challengeMenu);
        $this->assertArrayHasKey('route', $challengeMenu);
        $this->assertArrayHasKey('group', $challengeMenu);
        $this->assertArrayHasKey('order', $challengeMenu);
        $this->assertArrayHasKey('badge', $challengeMenu);
        $this->assertArrayHasKey('submenu', $challengeMenu);

        $this->assertEquals('工作证明挑战', $challengeMenu['label']);
        $this->assertEquals('fas fa-shield-alt', $challengeMenu['icon']);
        $this->assertEquals('admin_challenge_index', $challengeMenu['route']);
        $this->assertEquals('安全管理', $challengeMenu['group']);
        $this->assertEquals(200, $challengeMenu['order']);
        $this->assertEquals(1, $challengeMenu['badge']); // 一个活跃挑战
    }

    public function testGetActiveChallengeCountWithMixedChallenges(): void
    {
        $now = time();

        // 创建不同状态的挑战
        $activeChallenge1 = new Challenge('active-1', 'hashcash', 'test', 20, $now, $now + 3600);
        $activeChallenge2 = new Challenge('active-2', 'hashcash', 'test', 20, $now, $now + 3600);

        $expiredChallenge = new Challenge('expired', 'hashcash', 'test', 20, $now - 7200, $now - 3600);

        $usedChallenge = new Challenge('used', 'hashcash', 'test', 20, $now, $now + 3600);
        $usedChallenge->markAsUsed();

        // 保存所有挑战
        $this->storage->save($activeChallenge1);
        $this->storage->save($activeChallenge2);
        $this->storage->save($expiredChallenge);
        $this->storage->save($usedChallenge);

        $menuItems = $this->adminMenu->getMenuItems();
        $challengeMenu = $menuItems['proof_of_work_challenges'];
        $this->assertIsArray($challengeMenu);
        $this->assertEquals(2, $challengeMenu['badge']); // 只有两个活跃挑战
    }

    public function testGetStatisticsReturnsCorrectCounts(): void
    {
        $now = time();

        // 创建测试数据
        $activeChallenge = new Challenge('active', 'hashcash', 'test', 20, $now, $now + 3600);
        // 注意：已过期的挑战无法保存到缓存（缓存会立即清除），因此不创建
        $usedChallenge = new Challenge('used', 'hashcash', 'test', 20, $now, $now + 3600);
        $usedChallenge->markAsUsed();

        $this->storage->save($activeChallenge);
        $this->storage->save($usedChallenge);

        $stats = $this->adminMenu->getStatistics();

        // 注意：由于 CacheChallengeStorage 会在保存时设置缓存项在 expireTime 自动失效，
        // 已过期的挑战无法被保存，因此 total=2 而非 3
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['active']);
        $this->assertEquals(0, $stats['expired']); // 无法保存已过期的挑战
        $this->assertEquals(1, $stats['used']);
        $this->assertArrayNotHasKey('error', $stats);
    }

    public function testGetStatisticsWithEmptyStorage(): void
    {
        $stats = $this->adminMenu->getStatistics();

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['active']);
        $this->assertEquals(0, $stats['expired']);
        $this->assertEquals(0, $stats['used']);
        $this->assertArrayNotHasKey('error', $stats);
    }

    public function testGetDashboardWidgetReturnsCorrectStructure(): void
    {
        $widget = $this->adminMenu->getDashboardWidget();

        $this->assertIsArray($widget);
        $this->assertArrayHasKey('title', $widget);
        $this->assertArrayHasKey('template', $widget);
        $this->assertArrayHasKey('data', $widget);
        $this->assertArrayHasKey('priority', $widget);

        $this->assertEquals('工作证明挑战', $widget['title']);
        $this->assertEquals('@ProofOfWorkChallenge/admin/widget/dashboard.html.twig', $widget['template']);
        $this->assertEquals(300, $widget['priority']);
        $this->assertIsArray($widget['data']);
    }

    public function testActiveChallengeCountWithEmptyStorage(): void
    {
        $menuItems = $this->adminMenu->getMenuItems();
        $challengeMenu = $menuItems['proof_of_work_challenges'];
        $this->assertIsArray($challengeMenu);

        // 空存储时，徽章应该显示0
        $this->assertEquals(0, $challengeMenu['badge']);
    }
}
