<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class ProofOfWorkChallengeExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
