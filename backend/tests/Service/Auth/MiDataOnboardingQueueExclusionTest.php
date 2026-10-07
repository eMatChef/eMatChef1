<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Repository\MiDataDepartmentOnboardingRepository;
use App\Service\Auth\MiDataOnboardingQueueExclusion;
use PHPUnit\Framework\TestCase;

final class MiDataOnboardingQueueExclusionTest extends TestCase
{
    public function testUsersWithAnOpenNotExpiredOfferAreExcludedFromTheGenericQueue(): void
    {
        $repository = $this->createMock(MiDataDepartmentOnboardingRepository::class);
        $repository->expects(self::once())
            ->method('findUserIdsWithOpenOffer')
            ->with(
                ['user00000001', 'user00000002'],
                self::callback(static fn (\DateTimeInterface $now): bool => abs($now->getTimestamp() - time()) <= 5),
            )
            ->willReturn(['user00000002']);

        $excluded = (new MiDataOnboardingQueueExclusion($repository))->excludedUserIds(['user00000001', 'user00000002']);

        self::assertSame(['user00000002'], $excluded);
    }
}
