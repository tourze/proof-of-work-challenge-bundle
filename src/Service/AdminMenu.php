<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Service;

use Knp\Menu\ItemInterface;
use Tourze\EasyAdminMenuBundle\Attribute\MenuProvider;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;
use Tourze\ProofOfWorkChallengeBundle\Storage\CacheChallengeStorage;

/**
 * AdminMenu 服务
 *
 * 为后台管理系统提供工作证明挑战管理菜单项
 */
#[MenuProvider]
final class AdminMenu implements MenuProviderInterface
{
    private CacheChallengeStorage $challengeStorage;

    public function __construct(
        CacheChallengeStorage $challengeStorage,
        private LinkGeneratorInterface $linkGenerator,
    ) {
        $this->challengeStorage = $challengeStorage;
    }

    /**
     * 获取菜单配置
     *
     * @return array<string, mixed>
     */
    public function getMenuItems(): array
    {
        return [
            'proof_of_work_challenges' => [
                'label' => '工作证明挑战',
                'icon' => 'fas fa-shield-alt',
                'route' => 'admin_challenge_index',
                'group' => '安全管理',
                'order' => 200,
                'badge' => $this->getActiveChallengeCount(),
                'submenu' => [
                    'challenge_list' => [
                        'label' => '挑战列表',
                        'route' => 'admin_challenge_index',
                        'icon' => 'fas fa-list',
                    ],
                ],
            ],
        ];
    }

    /**
     * 获取活跃挑战数量（用于菜单徽章显示）
     */
    private function getActiveChallengeCount(): int
    {
        try {
            $challenges = $this->challengeStorage->findAll();
            $activeCount = 0;

            foreach ($challenges as $challenge) {
                if (!$challenge->isExpired() && !$challenge->isUsed()) {
                    ++$activeCount;
                }
            }

            return $activeCount;
        } catch (\Exception $e) {
            // 如果获取失败，返回0，避免影响菜单显示
            return 0;
        }
    }

    /**
     * 获取统计信息
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        try {
            $challenges = $this->challengeStorage->findAll();
            $total = count($challenges);
            $active = 0;
            $expired = 0;
            $used = 0;

            foreach ($challenges as $challenge) {
                if ($challenge->isUsed()) {
                    ++$used;
                } elseif ($challenge->isExpired()) {
                    ++$expired;
                } else {
                    ++$active;
                }
            }

            return [
                'total' => $total,
                'active' => $active,
                'expired' => $expired,
                'used' => $used,
            ];
        } catch (\Exception $e) {
            return [
                'total' => 0,
                'active' => 0,
                'expired' => 0,
                'used' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 获取面板小部件配置
     *
     * @return array<string, mixed>
     */
    public function getDashboardWidget(): array
    {
        $stats = $this->getStatistics();

        return [
            'title' => '工作证明挑战',
            'template' => '@ProofOfWorkChallenge/admin/widget/dashboard.html.twig',
            'data' => $stats,
            'priority' => 300,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(ItemInterface $item): void
    {
        // 检查父菜单是否存在
        if (null === $item->getChild('安全管理')) {
            $item->addChild('安全管理');
        }

        $parentMenu = $item->getChild('安全管理');
        if (null === $parentMenu) {
            return;
        }

        // 添加工作证明挑战菜单项
        $parentMenu->addChild('工作证明挑战')
            ->setUri($this->linkGenerator->getCurdListPage(Challenge::class))
            ->setAttribute('icon', 'fas fa-shield-alt')
            ->setExtra('badge', $this->getActiveChallengeCount())
        ;
    }
}
