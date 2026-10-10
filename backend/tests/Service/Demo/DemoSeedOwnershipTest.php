<?php

declare(strict_types=1);

namespace App\Tests\Service\Demo;

use App\Entity\Department;
use App\Service\Bootstrap\DemoGrossanlassSeedService;
use App\Service\Bootstrap\DemoGrossanlassWipeService;
use App\Service\Bootstrap\DevBootstrapContextService;
use App\Service\DepartmentResetService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class DemoSeedOwnershipTest extends TestCase
{
    private function department(string $id, bool $demo, bool $grossanlass = false): Department
    {
        $d = new Department();
        $d->setId($id);
        $org = new \App\Entity\Organisation();
        $org->setId('org_t1');
        $d->setOrganisation($org);
        $d->setName('Demo Grossanlass');
        $d->setDemoMode($demo);
        $d->setIsGrossanlass($grossanlass);

        return $d;
    }

    private function emWithRepo(EntityRepository $repo): EntityManagerInterface
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return $em;
    }

    public function testWipeRefusesDepartmentWithoutDemoMode(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn($this->department('d1', false, true));
        $em = $this->emWithRepo($repo);
        $em->expects(self::never())->method('getConnection');

        $wipe = new DemoGrossanlassWipeService($em, $this->createMock(DepartmentResetService::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('kein Demo-Department');
        $wipe->wipeDepartment('d1');
    }

    public function testWipeByNameRefusesAmbiguousNames(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([$this->department('d1', true, true), $this->department('d2', true, true)]);
        $em = $this->emWithRepo($repo);
        $em->expects(self::never())->method('getConnection');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('nicht eindeutig');
        (new DemoGrossanlassWipeService($em, $this->createMock(DepartmentResetService::class)))->wipeByName('Demo Grossanlass');
    }

    public function testRoleUsersAreNeverAssignedToAnArbitraryVisibleDepartment(): void
    {
        $real = $this->department('real', false);
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([]);
        $repo->method('findAll')->willReturn([$real]);
        $em = $this->emWithRepo($repo);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $service = (new \ReflectionClass(DevBootstrapContextService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(DevBootstrapContextService::class, 'entityManager'))->setValue($service, $em);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Kein eindeutiges Demo-Department');
        $service->findOwnedDemoOrganisationAndDepartment();
    }

    public function testExplicitNonDemoDepartmentNeedsExplicitMarking(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn($this->department('real', false));
        $em = $this->emWithRepo($repo);
        $em->expects(self::never())->method('flush');

        $service = (new \ReflectionClass(DevBootstrapContextService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(DevBootstrapContextService::class, 'entityManager'))->setValue($service, $em);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('kein Demo-Department');
        $service->findOwnedDemoOrganisationAndDepartment('real');
    }

    public function testSeedDoesNotAdoptSameNamedDepartmentWithoutDemoMode(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($this->department('real', false, true));
        $em = $this->emWithRepo($repo);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $seed = (new \ReflectionClass(DemoGrossanlassSeedService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(DemoGrossanlassSeedService::class, 'entityManager'))->setValue($seed, $em);

        $org = $this->createMock(\App\Entity\Organisation::class);
        $org->method('getId')->willReturn('o1');

        $this->expectException(\RuntimeException::class);
        $seed->ensureDepartment($org, $this->createMock(\App\Entity\User::class));
    }
}
