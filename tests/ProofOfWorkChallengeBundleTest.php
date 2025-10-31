<?php

declare(strict_types=1);

namespace Tourze\ProofOfWorkChallengeBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;
use Tourze\ProofOfWorkChallengeBundle\ProofOfWorkChallengeBundle;

/**
 * @internal
 */
#[CoversClass(ProofOfWorkChallengeBundle::class)]
#[RunTestsInSeparateProcesses]
final class ProofOfWorkChallengeBundleTest extends AbstractBundleTestCase
{
}
