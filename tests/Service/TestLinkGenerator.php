<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests\Service;

use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;

/**
 * 测试用的 LinkGenerator 实现
 */
class TestLinkGenerator implements LinkGeneratorInterface
{
    public function getCurdListPage(string $entityClass): string
    {
        return match ($entityClass) {
            Challenge::class => '/admin/challenges',
            default => '',
        };
    }

    public function extractEntityFqcn(string $url): ?string
    {
        return null;
    }

    public function setDashboard(string $dashboardControllerFqcn): void
    {
        // Mock 实现：不需要实际操作
    }
}
