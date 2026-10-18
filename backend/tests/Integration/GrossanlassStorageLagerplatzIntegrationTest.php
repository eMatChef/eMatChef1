<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Address;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassPlaceCodes;
use App\Service\Grossanlass\GrossanlassPlaceService;
use App\Service\Grossanlass\GrossanlassStorageLagerplatzService;
use App\Tests\Wiring\FreshKernel;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Lageradresse ↔ GA-Lagerplatz: Schreibpfad und schreibfreie GET-Listen (Demo-Grossanlass-Event).
 */
final class GrossanlassStorageLagerplatzIntegrationTest extends TestCase
{
    private const EVENT_DEPT = '2d42e87c5197';
    private const GELAENDE_PLACE = 'plf873a2b455';
    private const CONTAINER_PLACE = 'plce35e2f40a';
    private const GELAENDE_ADDR = '70020a03680a';
    private const CONTAINER_ADDR = '4b1446b6054c';

    /** @param callable(EntityManagerInterface, GrossanlassStorageLagerplatzService, GrossanlassPlaceService, User): void $test */
    private function withServices(callable $test): void
    {
        try {
            $url = GrossanlassStaffingIntegrationTest::url();
        } catch (\Throwable $e) {
            self::markTestSkipped($e->getMessage());
        }
        FreshKernel::run(function ($kernel) use ($test): void {
            $c = $kernel->getContainer();
            /** @var EntityManagerInterface $em */
            $em = $c->get('doctrine')->getManager();
            $conn = $em->getConnection();
            $conn->setNestTransactionsWithSavepoints(true);
            $conn->beginTransaction();
            try {
                $profile = $em->getRepository(Profile::class)->findOneByEmailInsensitive('ga-mw@demo.ematchef.ch');
                $user = $profile === null ? null : $em->getRepository(User::class)->findOneBy(['profile' => $profile]);
                self::assertInstanceOf(User::class, $user);
                $test(
                    $em,
                    $c->get(GrossanlassStorageLagerplatzService::class),
                    $c->get(GrossanlassPlaceService::class),
                    $user,
                );
            } finally {
                $conn->rollBack();
            }
        }, $url, [GrossanlassStorageLagerplatzService::class, GrossanlassPlaceService::class]);
    }

    public function testDemoStockLinksPreserved(): void
    {
        $this->withServices(function (EntityManagerInterface $em): void {
            $gel = $em->find(DepartmentGrossanlassPlace::class, self::GELAENDE_PLACE);
            $box = $em->find(DepartmentGrossanlassPlace::class, self::CONTAINER_PLACE);
            self::assertInstanceOf(DepartmentGrossanlassPlace::class, $gel);
            self::assertInstanceOf(DepartmentGrossanlassPlace::class, $box);
            self::assertSame(self::GELAENDE_ADDR, $gel->getStorageAddressId());
            self::assertSame(self::CONTAINER_ADDR, $box->getStorageAddressId());
        });
    }

    public function testListLagerplaetzeIsReadOnly(): void
    {
        $this->withServices(function (EntityManagerInterface $em, GrossanlassStorageLagerplatzService $sync, GrossanlassPlaceService $places, User $user): void {
            $department = $em->find(Department::class, self::EVENT_DEPT);
            self::assertInstanceOf(Department::class, $department);
            $before = (int) $em->createQueryBuilder()
                ->select('COUNT(p.id)')
                ->from(DepartmentGrossanlassPlace::class, 'p')
                ->where('p.departmentId = :d')
                ->andWhere('p.kind = :k')
                ->setParameter('d', self::EVENT_DEPT)
                ->setParameter('k', 'lagerplatz')
                ->getQuery()
                ->getSingleScalarResult();
            $places->list($department, $user);
            $listed = $places->listLagerplaetze($department);
            $after = (int) $em->createQueryBuilder()
                ->select('COUNT(p.id)')
                ->from(DepartmentGrossanlassPlace::class, 'p')
                ->where('p.departmentId = :d')
                ->andWhere('p.kind = :k')
                ->setParameter('d', self::EVENT_DEPT)
                ->setParameter('k', 'lagerplatz')
                ->getQuery()
                ->getSingleScalarResult();
            self::assertSame($before, $after);
            self::assertCount(2, $listed);
            self::assertSame(
                ['Langzeit-Lagercontainer', 'Lagerplatz Gelände'],
                array_column($listed, 'name'),
            );
        });
    }

    public function testRenameKeepsLagerplatzId(): void
    {
        $this->withServices(function (EntityManagerInterface $em, GrossanlassStorageLagerplatzService $sync): void {
            $address = $em->find(Address::class, self::CONTAINER_ADDR);
            self::assertInstanceOf(Address::class, $address);
            $address->setName('Langzeit-Lagercontainer Test');
            $sync->syncStorageAddress($address);
            $place = $sync->findByStorageAddressId(self::CONTAINER_ADDR);
            self::assertInstanceOf(DepartmentGrossanlassPlace::class, $place);
            self::assertSame(self::CONTAINER_PLACE, $place->getId());
            self::assertSame('Langzeit-Lagercontainer Test', $place->getName());
        });
    }

    public function testCreateStorageAddressCreatesOneLagerplatz(): void
    {
        $this->withServices(function (EntityManagerInterface $em, GrossanlassStorageLagerplatzService $sync): void {
            $address = new Address();
            $address->setId(IdGenerator::generateUnique($em, Address::class));
            $address->setScope(Address::SCOPE_DEPARTMENT);
            $address->setDepartmentId(self::EVENT_DEPT);
            $address->setType('storage');
            $address->setName('Integration Lager ' . substr($address->getId(), -4));
            $em->persist($address);
            $em->flush();
            $sync->syncStorageAddress($address);
            $place = $sync->findByStorageAddressId($address->getId());
            self::assertInstanceOf(DepartmentGrossanlassPlace::class, $place);
            self::assertSame(GrossanlassPlaceCodes::KIND_LAGERPLATZ, $place->getKind());
            $sync->syncStorageAddress($address);
            self::assertSame($place->getId(), $sync->findByStorageAddressId($address->getId())?->getId());
        });
    }
}
