<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\ProofOfWorkChallengeBundle\Entity\Challenge;
use Tourze\ProofOfWorkChallengeBundle\Storage\CacheChallengeStorage;

/**
 * Challenge API控制器
 *
 * 此控制器为基于缓存存储的Challenge实体提供API接口。
 * 由于Challenge不是Doctrine实体，它不使用标准的EasyAdmin CRUD架构。
 */
final class ChallengeCrudController extends AbstractController
{
    private CacheChallengeStorage $challengeStorage;

    public function __construct(CacheChallengeStorage $challengeStorage)
    {
        $this->challengeStorage = $challengeStorage;
    }

    /**
     * 主要控制器入口点
     * 获取挑战的JSON数据（用于AJAX请求）
     */
    #[Route(path: '/admin/challenge/api/data', name: 'admin_challenge_api_data', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $challenges = $this->challengeStorage->findAll();

        // 转换为适合前端显示的格式
        $data = array_map(function (Challenge $challenge) {
            return [
                'id' => $challenge->getId(),
                'type' => $challenge->getType(),
                'difficulty' => $challenge->getDifficulty(),
                'createTime' => date('Y-m-d H:i:s', $challenge->getCreateTime()),
                'expireTime' => date('Y-m-d H:i:s', $challenge->getExpireTime()),
                'resource' => $challenge->getResource(),
                'clientId' => $challenge->getClientId(),
                'used' => $challenge->isUsed(),
                'expired' => $challenge->isExpired(),
                'status' => $this->getChallengeStatus($challenge),
                'ttl' => $this->getTimeToLive($challenge),
            ];
        }, $challenges);

        return new JsonResponse([
            'data' => $data,
            'total' => count($data),
        ]);
    }

    /**
     * 获取挑战状态文本
     */
    private function getChallengeStatus(Challenge $challenge): string
    {
        if ($challenge->isUsed()) {
            return '已使用';
        }
        if ($challenge->isExpired()) {
            return '已过期';
        }

        return '有效';
    }

    /**
     * 获取挑战剩余时间
     */
    private function getTimeToLive(Challenge $challenge): string
    {
        if ($challenge->isExpired()) {
            return '已过期';
        }

        $remaining = $challenge->getExpireTime() - time();
        $minutes = intval($remaining / 60);
        $seconds = $remaining % 60;

        return sprintf('%d分%d秒', $minutes, $seconds);
    }
}
