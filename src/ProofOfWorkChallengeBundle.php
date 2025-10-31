<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ProofOfWorkChallengeBundle extends Bundle
{
    /**
     * @return array<string, bool>
     */
    public static function getBundleDependencies(): array
    {
        return ['all' => true];
    }
}
