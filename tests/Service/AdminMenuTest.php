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
 * AdminMenu 服务测试
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    private CacheChallengeStorage $challengeStorage;

    private AdminMenu $adminMenu;

    protected function onSetUp(): void
    {
        // 模拟依赖服务
        $this->challengeStorage = $this->createMock(CacheChallengeStorage::class);
        $linkGenerator = $this->createMock(LinkGeneratorInterface::class);

        // 将模拟服务注入容器
        self::getContainer()->set(CacheChallengeStorage::class, $this->challengeStorage);
        self::getContainer()->set(LinkGeneratorInterface::class, $linkGenerator);
    }

    public function testGetMenuItemsReturnsCorrectStructure(): void
    {
        $this->adminMenu = self::getService(AdminMenu::class);

        // 模拟返回一个活跃的挑战
        $challenge = new Challenge(
            'test-id',
            'hashcash',
            'test-challenge',
            20,
            time(),
            time() + 3600
        );

        $this->challengeStorage
            ->method('findAll')
            ->willReturn([$challenge])
        ;

        $menuItems = $this->adminMenu->getMenuItems();

        $this->assertIsArray($menuItems);
        $this->assertArrayHasKey('proof_of_work_challenges', $menuItems);

        $challengeMenu = $menuItems['proof_of_work_challenges'];
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
        $this->adminMenu = self::getService(AdminMenu::class);
        $now = time();

        // 创建不同状态的挑战
        $activeChallenges = [
            new Challenge('active-1', 'hashcash', 'test', 20, $now, $now + 3600),
            new Challenge('active-2', 'hashcash', 'test', 20, $now, $now + 3600),
        ];

        $expiredChallenge = new Challenge('expired', 'hashcash', 'test', 20, $now - 7200, $now - 3600);

        $usedChallenge = new Challenge('used', 'hashcash', 'test', 20, $now, $now + 3600);
        $usedChallenge->markAsUsed();

        $allChallenges = array_merge($activeChallenges, [$expiredChallenge, $usedChallenge]);

        $this->challengeStorage
            ->method('findAll')
            ->willReturn($allChallenges)
        ;

        $menuItems = $this->adminMenu->getMenuItems();
        $this->assertEquals(2, $menuItems['proof_of_work_challenges']['badge']);
    }

    public function testGetStatisticsReturnsCorrectCounts(): void
    {
        $this->adminMenu = self::getService(AdminMenu::class);
        $now = time();

        // 创建测试数据
        $activeChallenge = new Challenge('active', 'hashcash', 'test', 20, $now, $now + 3600);
        $expiredChallenge = new Challenge('expired', 'hashcash', 'test', 20, $now - 7200, $now - 3600);
        $usedChallenge = new Challenge('used', 'hashcash', 'test', 20, $now, $now + 3600);
        $usedChallenge->markAsUsed();

        $this->challengeStorage
            ->method('findAll')
            ->willReturn([$activeChallenge, $expiredChallenge, $usedChallenge])
        ;

        $stats = $this->adminMenu->getStatistics();

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['active']);
        $this->assertEquals(1, $stats['expired']);
        $this->assertEquals(1, $stats['used']);
        $this->assertArrayNotHasKey('error', $stats);
    }

    public function testGetStatisticsHandlesStorageException(): void
    {
        $this->adminMenu = self::getService(AdminMenu::class);
        $this->challengeStorage
            ->method('findAll')
            ->willThrowException(new \RuntimeException('Storage error'))
        ;

        $stats = $this->adminMenu->getStatistics();

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['active']);
        $this->assertEquals(0, $stats['expired']);
        $this->assertEquals(0, $stats['used']);
        $this->assertArrayHasKey('error', $stats);
        $this->assertEquals('Storage error', $stats['error']);
    }

    public function testGetDashboardWidgetReturnsCorrectStructure(): void
    {
        $this->adminMenu = self::getService(AdminMenu::class);
        $this->challengeStorage
            ->method('findAll')
            ->willReturn([])
        ;

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

    public function testActiveChallengeCountHandlesStorageException(): void
    {
        $this->adminMenu = self::getService(AdminMenu::class);
        $this->challengeStorage
            ->method('findAll')
            ->willThrowException(new \RuntimeException('Storage error'))
        ;

        $menuItems = $this->adminMenu->getMenuItems();

        // 当存储出错时，徽章应该显示0
        $this->assertEquals(0, $menuItems['proof_of_work_challenges']['badge']);
    }
}
