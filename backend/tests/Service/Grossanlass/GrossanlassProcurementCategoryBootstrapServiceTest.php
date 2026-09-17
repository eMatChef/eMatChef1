<?php

declare(strict_types=1);

namespace App\Tests\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\Department;
use App\Service\Grossanlass\GrossanlassProcurementCategoryBootstrapService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class GrossanlassProcurementCategoryBootstrapServiceTest extends TestCase
{
    public function testTemplateKeepsCoarsePackagesWithoutArticles(): void
    {
        $names = GrossanlassProcurementCategoryBootstrapService::expectedNames();
        self::assertSame([
            'J+S',
            'Werkzeuge',
            'Handwerkzeuge',
            'Elektrowerkzeuge und Maschinen',
            'Mess- und Prüfwerkzeuge',
            'Schneid- und Trennwerkzeuge',
            'Gartenwerkzeuge',
            'Druckluftwerkzeuge',
            'Sanitär- und Installationswerkzeuge',
            'Schutzausrüstung und Werkstattausstattung',
            'Fahrzeuge',
            'Anhänger',
            'Flurförderzeuge & Hebezeuge',
            'Gelände- und Utility-Fahrzeuge',
            'Logistik- und Transportfahrzeuge (Strasse)',
            'Infrastruktur',
            'Sanitär & Wasserversorgung',
            'Energie- & Stromversorgung',
            'Geländeschutz, Absperrung & Leitsysteme',
            'Bauten, Überdachungen & Raummodule',
            'Abfallmanagement & Entsorgung',
            'Kommunikation & IT-Infrastruktur',
            'Klimatisierung & Lüftung',
        ], $names);
        self::assertCount(23, $names);
        self::assertNotContains('Akkuschrauber', $names);
        self::assertNotContains('Holzschrauben', $names);
        self::assertNotContains('Befestigungsmaterial', $names);
    }

    public function testEnsureSeedsPackagesWithoutArticlesWhenEmpty(): void
    {
        $department = $this->department();
        $persisted = [];
        $em = $this->entityManager([], $persisted);
        $expected = count(GrossanlassProcurementCategoryBootstrapService::expectedNames());

        $created = (new GrossanlassProcurementCategoryBootstrapService($em))->ensureForDepartment($department);

        self::assertSame($expected, $created);
        self::assertCount($expected, $persisted);

        $byName = [];
        foreach ($persisted as $row) {
            $byName[$row->getName()] = $row;
        }
        self::assertSame(ActivityGrossanlassProcurementCategory::KIND_PACKAGE, $byName['Werkzeuge']->getKind());
        self::assertSame(ActivityGrossanlassProcurementCategory::KIND_PACKAGE, $byName['Elektrowerkzeuge und Maschinen']->getKind());
        self::assertSame('Werkzeuge', $byName['Handwerkzeuge']->getParent()?->getName());
        self::assertArrayNotHasKey('Akkuschrauber', $byName);
        self::assertArrayNotHasKey('Holzschrauben', $byName);
        self::assertArrayNotHasKey('Befestigungsmaterial', $byName);
    }

    public function testEnsureAddsMissingPackagesUnderExistingParents(): void
    {
        $department = $this->department();
        $werkzeuge = new ActivityGrossanlassProcurementCategory();
        $werkzeuge->setId('gcwerkzeuge1');
        $werkzeuge->setDepartment($department);
        $werkzeuge->setName('Werkzeuge');
        $werkzeuge->setSortOrder(10);

        $persisted = [];
        $em = $this->entityManager([$werkzeuge], $persisted);

        $created = (new GrossanlassProcurementCategoryBootstrapService($em))->ensureForDepartment($department);

        self::assertGreaterThan(1, $created);
        $byName = [];
        foreach ($persisted as $row) {
            $byName[$row->getName()] = $row;
        }
        self::assertSame('J+S', $persisted[0]->getName());
        self::assertArrayHasKey('Handwerkzeuge', $byName);
        self::assertArrayNotHasKey('Akkuschrauber', $byName);
        self::assertArrayNotHasKey('Fahrzeuge', $byName);
    }

    public function testEnsureIsNoopWhenJsAlreadyPresentWithoutOtherRoots(): void
    {
        $department = $this->department();
        $js = new ActivityGrossanlassProcurementCategory();
        $js->setId('gcjs00000001');
        $js->setDepartment($department);
        $js->setName('J+S');
        $js->setSystemKey(ActivityGrossanlassProcurementCategory::SYSTEM_KEY_JS);

        $persisted = [];
        $em = $this->entityManager([$js], $persisted);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $created = (new GrossanlassProcurementCategoryBootstrapService($em))->ensureForDepartment($department);

        self::assertSame(0, $created);
        self::assertSame([], $persisted);
    }

    private function department(): Department
    {
        $department = $this->createMock(Department::class);
        $department->method('getId')->willReturn('depttest0001');

        return $department;
    }

    /**
     * @param list<ActivityGrossanlassProcurementCategory> $existing
     * @param list<ActivityGrossanlassProcurementCategory> $persisted
     */
    private function entityManager(array $existing, array &$persisted): EntityManagerInterface
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturnCallback(
            static function () use ($existing, &$persisted): array {
                return array_merge($existing, $persisted);
            }
        );
        $repo->method('findOneBy')->willReturn(null);
        $repo->method('find')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void {
                if ($entity instanceof ActivityGrossanlassProcurementCategory) {
                    $persisted[] = $entity;
                }
            }
        );

        return $em;
    }
}
