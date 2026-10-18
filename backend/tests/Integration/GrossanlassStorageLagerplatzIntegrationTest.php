<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Address;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassPlace;
use App\Entity\User;
use App\Service\Grossanlass\GrossanlassPlaceCodes;
use App\Service\Grossanlass\GrossanlassPlaceService;
use App\Service\Demo\Scenario\DemoScenarioKey;
use App\Service\Demo\Scenario\DemoScenarioRegistry;
use App\Service\Demo\Scenario\DemoScenarioRunner;
use App\Service\Demo\Scenario\SeedResult;
use App\Service\Grossanlass\GrossanlassStorageLagerplatzService;
use App\Tests\Wiring\FreshKernel;
use App\Util\DemoAccounts;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Lageradresse ↔ GA-Lagerplatz: Schreibpfad und schreibfreie GET-Listen (Demo-Grossanlass-Event, in der isolierten
 * Test-DB über den Szenario-Runner aufgebaut; Rollback am Ende).
 */
final class GrossanlassStorageLagerplatzIntegrationTest extends TestCase
{
    private const GELAENDE = 'Lagerplatz Gelände';
    private const CONTAINER = 'Langzeit-Lagercontainer';

    private string $eventDept = '';

    /** @var array<string, array{place: string, address: string}> Lagerplätze des Events nach Name */
    private array $lager = [];

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
                $runner = $c->get(DemoScenarioRunner::class);
                $scenario = $c->get(DemoScenarioRegistry::class)->all()[DemoScenarioKey::GROSSANLASS_EVENT];
                self::assertSame(SeedResult::OK, $runner->sync($scenario)->status);
                $em->clear();
                $this->eventDept = (string) $conn->fetchOne("SELECT id FROM department WHERE demo_scenario_key='grossanlass-event'");
                // Zweite Lageradresse über den produktiven Sync-Pfad (der Demo-Seed liefert nur den Geländeplatz).
                $container = new Address();
                $container->setId(IdGenerator::generateUnique($em, Address::class));
                $container->setScope(Address::SCOPE_DEPARTMENT);
                $container->setDepartmentId($this->eventDept);
                $container->setType('storage');
                $container->setName(self::CONTAINER);
                $em->persist($container);
                $em->flush();
                $c->get(GrossanlassStorageLagerplatzService::class)->syncStorageAddress($container);
                $em->flush();
                $em->clear();
                $this->lager = [];
                foreach ($conn->fetchAllAssociative("SELECT id, name, storage_address_id FROM department_grossanlass_place WHERE department_id=? AND kind='lagerplatz' AND storage_address_id IS NOT NULL", [$this->eventDept]) as $row) {
                    $this->lager[(string) $row['name']] = ['place' => (string) $row['id'], 'address' => (string) $row['storage_address_id']];
                }
                $userId = $conn->fetchOne('SELECT u.id FROM "user" u JOIN profile p ON p.id=u.profile_id WHERE p.email=?', [DemoAccounts::email('ga-mw')]);
                $user = $em->find(User::class, $userId);
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
        }, $url, [DemoScenarioRunner::class, DemoScenarioRegistry::class, GrossanlassStorageLagerplatzService::class, GrossanlassPlaceService::class]);
    }

    public function testDemoStockLinksPreserved(): void
    {
        $this->withServices(function (EntityManagerInterface $em): void {
            self::assertArrayHasKey(self::GELAENDE, $this->lager);
            self::assertArrayHasKey(self::CONTAINER, $this->lager);
            $gel = $em->find(DepartmentGrossanlassPlace::class, $this->lager[self::GELAENDE]['place']);
            $box = $em->find(DepartmentGrossanlassPlace::class, $this->lager[self::CONTAINER]['place']);
            self::assertInstanceOf(DepartmentGrossanlassPlace::class, $gel);
            self::assertInstanceOf(DepartmentGrossanlassPlace::class, $box);
            self::assertSame($this->lager[self::GELAENDE]['address'], $gel->getStorageAddressId());
            self::assertSame($this->lager[self::CONTAINER]['address'], $box->getStorageAddressId());
            self::assertInstanceOf(Address::class, $em->find(Address::class, $this->lager[self::GELAENDE]['address']));
            self::assertInstanceOf(Address::class, $em->find(Address::class, $this->lager[self::CONTAINER]['address']));
        });
    }

    public function testListLagerplaetzeIsReadOnly(): void
    {
        $this->withServices(function (EntityManagerInterface $em, GrossanlassStorageLagerplatzService $sync, GrossanlassPlaceService $places, User $user): void {
            $department = $em->find(Department::class, $this->eventDept);
            self::assertInstanceOf(Department::class, $department);
            $before = (int) $em->createQueryBuilder()
                ->select('COUNT(p.id)')
                ->from(DepartmentGrossanlassPlace::class, 'p')
                ->where('p.departmentId = :d')
                ->andWhere('p.kind = :k')
                ->setParameter('d', $this->eventDept)
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
                ->setParameter('d', $this->eventDept)
                ->setParameter('k', 'lagerplatz')
                ->getQuery()
                ->getSingleScalarResult();
            self::assertSame($before, $after);
            self::assertCount(2, $listed);
            self::assertEqualsCanonicalizing(
                [self::CONTAINER, self::GELAENDE],
                array_column($listed, 'name'),
            );
        });
    }

    public function testRenameKeepsLagerplatzId(): void
    {
        $this->withServices(function (EntityManagerInterface $em, GrossanlassStorageLagerplatzService $sync): void {
            $container = $this->lager[self::CONTAINER] ?? null;
            self::assertNotNull($container);
            $address = $em->find(Address::class, $container['address']);
            self::assertInstanceOf(Address::class, $address);
            $address->setName('Langzeit-Lagercontainer Test');
            $sync->syncStorageAddress($address);
            $place = $sync->findByStorageAddressId($container['address']);
            self::assertInstanceOf(DepartmentGrossanlassPlace::class, $place);
            self::assertSame($container['place'], $place->getId());
            self::assertSame('Langzeit-Lagercontainer Test', $place->getName());
        });
    }

    public function testCreateStorageAddressCreatesOneLagerplatz(): void
    {
        $this->withServices(function (EntityManagerInterface $em, GrossanlassStorageLagerplatzService $sync): void {
            $address = new Address();
            $address->setId(IdGenerator::generateUnique($em, Address::class));
            $address->setScope(Address::SCOPE_DEPARTMENT);
            $address->setDepartmentId($this->eventDept);
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
