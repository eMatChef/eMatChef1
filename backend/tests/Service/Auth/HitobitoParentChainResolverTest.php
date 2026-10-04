<?php

declare(strict_types=1);

namespace App\Tests\Service\Auth;

use App\Entity\ExternalStructureIdentity;
use App\Service\Auth\HitobitoApiException;
use App\Service\Auth\HitobitoGroup;
use App\Service\Auth\HitobitoGroupLookup;
use App\Service\Auth\HitobitoParentChainResolver;
use PHPUnit\Framework\TestCase;

final class HitobitoParentChainResolverTest extends TestCase
{
    public function testCandidateIsSelf(): void
    {
        $api = $this->createMock(HitobitoGroupLookup::class);
        $api->expects(self::never())->method('getGroup');

        self::assertTrue($this->resolver($api)->isDescendantOrSelf('midata', '100', '100', 'token'));
    }

    public function testDirectAndNestedChildrenAreDescendants(): void
    {
        $api = $this->api([
            '111' => new HitobitoGroup('111', '110', 'Group::Woelfe', 'Meute'),
            '110' => new HitobitoGroup('110', '100', 'Group::Abteilung', 'Abteilung'),
        ]);
        $resolver = $this->resolver($api);

        self::assertTrue($resolver->isDescendantOrSelf('midata', '110', '100', 'token'));
        self::assertTrue($resolver->isDescendantOrSelf('midata', '111', '100', 'token'));
    }

    public function testDeeplyNestedChildIsResolved(): void
    {
        $groups = [];
        for ($id = 1; $id <= 12; $id++) {
            $groups[(string) $id] = new HitobitoGroup(
                (string) $id,
                $id === 12 ? '100' : (string) ($id + 1),
                'Group::Pfadi',
                'Group ' . $id,
            );
        }

        self::assertTrue($this->resolver($this->api($groups))->isDescendantOrSelf('midata', '1', '100', 'token'));
    }

    public function testUnrelatedGroupIsNotADescendant(): void
    {
        $api = $this->api([
            '111' => new HitobitoGroup('111', '110', 'Group::Woelfe', 'Meute'),
            '110' => new HitobitoGroup('110', '100', 'Group::Abteilung', 'Pfadi Thun'),
            '999' => new HitobitoGroup('999', null, 'Group::Abteilung', 'Pfadi Bern'),
        ]);

        self::assertFalse($this->resolver($api)->isDescendantOrSelf('midata', '111', '999', 'token'));
    }

    public function testMissingParentAndMissingGroupFailClosed(): void
    {
        $missingParent = $this->api([
            '111' => new HitobitoGroup('111', null, 'Group::Woelfe', 'Meute'),
        ]);
        $missingGroup = $this->api([]);
        $resolverWithMissingParent = $this->resolver($missingParent);
        $resolverWithMissingGroup = $this->resolver($missingGroup);

        self::assertFalse($resolverWithMissingParent->isDescendantOrSelf('midata', '111', '100', 'token'));
        self::assertFalse($resolverWithMissingGroup->isDescendantOrSelf('midata', '111', '100', 'token'));
    }

    public function testMismatchedGroupResponseFailsClosed(): void
    {
        $api = $this->api([
            '111' => new HitobitoGroup('different-id', '100', 'Group::Woelfe', 'Meute'),
        ]);

        self::assertFalse($this->resolver($api)->isDescendantOrSelf('midata', '111', '100', 'token'));
    }

    public function testCycleAndSelfCycleFailClosed(): void
    {
        $cycle = $this->api([
            '111' => new HitobitoGroup('111', '110', 'Group::Pfadi', 'A'),
            '110' => new HitobitoGroup('110', '111', 'Group::Pfadi', 'B'),
            'self' => new HitobitoGroup('self', 'self', 'Group::Pfadi', 'Self'),
        ]);
        $resolver = $this->resolver($cycle);

        self::assertFalse($resolver->isDescendantOrSelf('midata', '111', '999', 'token'));
        self::assertFalse($resolver->isDescendantOrSelf('midata', 'self', '999', 'token'));
    }

    public function testDepthLimitFailsClosed(): void
    {
        $api = $this->api([
            '1' => new HitobitoGroup('1', '2', 'Group::Pfadi', 'One'),
            '2' => new HitobitoGroup('2', '3', 'Group::Pfadi', 'Two'),
            '3' => new HitobitoGroup('3', '100', 'Group::Abteilung', 'Ancestor'),
        ]);

        self::assertFalse($this->resolver($api, 2)->isDescendantOrSelf('midata', '1', '100', 'token'));
    }

    public function testApiErrorsPropagateInsteadOfConfirmingMembership(): void
    {
        $api = $this->createMock(HitobitoGroupLookup::class);
        $api->method('getGroup')->willThrowException(
            new HitobitoApiException('permission_denied', 'Hitobito API request failed', 403)
        );

        $this->expectException(HitobitoApiException::class);
        $this->resolver($api)->isDescendantOrSelf('midata', '111', '100', 'token');
    }

    public function testUsesMappedExternalIdentityAndRejectsWrongProvider(): void
    {
        $mapping = (new ExternalStructureIdentity())
            ->setProvider('midata')
            ->setExternalGroupId('100');
        $api = $this->api([
            '111' => new HitobitoGroup('111', '100', 'Group::Woelfe', 'Meute'),
        ]);
        $resolver = $this->resolver($api);

        self::assertTrue($resolver->isDescendantOrSelfOfMapping('midata', '111', 'token', $mapping));
        self::assertFalse($resolver->isDescendantOrSelfOfMapping('cevidb', '111', 'token', $mapping));
    }

    public function testMissingInputsFailClosed(): void
    {
        $api = $this->createMock(HitobitoGroupLookup::class);
        $api->expects(self::never())->method('getGroup');

        self::assertFalse($this->resolver($api)->isDescendantOrSelf('midata', '', '100', 'token'));
        self::assertFalse($this->resolver($api)->isDescendantOrSelf('midata', '111', '100', ''));
    }

    /**
     * @param array<string, HitobitoGroup> $groups
     */
    private function api(array $groups): HitobitoGroupLookup
    {
        $api = $this->createMock(HitobitoGroupLookup::class);
        $api->method('getGroup')->willReturnCallback(
            static fn (string $provider, string $token, string $id): ?HitobitoGroup => $groups[$id] ?? null
        );

        return $api;
    }

    private function resolver(HitobitoGroupLookup $api, int $maxDepth = HitobitoParentChainResolver::MAX_DEPTH): HitobitoParentChainResolver
    {
        return new HitobitoParentChainResolver($api, $maxDepth);
    }
}
