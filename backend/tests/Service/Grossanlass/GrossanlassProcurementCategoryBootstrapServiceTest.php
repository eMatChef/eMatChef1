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
    public function testTemplateMatchesPffTree(): void
    {
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
        ], GrossanlassProcurementCategoryBootstrapService::expectedNames());
        self::assertCount(23, GrossanlassProcurementCategoryBootstrapService::expectedNames());
        self::assertSame(
            ActivityGrossanlassProcurementCategory::JS_NAME,
            GrossanlassProcurementCategoryBootstrapService::TREE[0]['name'],
        );
    }

    public function testEnsureSeedsFullTreeWhenEmpty(): void
    {
        $department = $this->department();
        $persisted = [];
        $em = $this->entityManager([], $persisted);

        $created = (new GrossanlassProcurementCategoryBootstrapService($em))->ensureForDepartment($department);

        self::assertSame(23, $created);
        self::assertCount(23, $persisted);

        $byName = [];
        foreach ($persisted as $row) {
            self::assertInstanceOf(ActivityGrossanlassProcurementCategory::class, $row);
            $byName[$row->getName()] = $row;
        }
        self::assertArrayHasKey('J+S', $byName);
        self::assertSame(ActivityGrossanlassProcurementCategory::SYSTEM_KEY_JS, $byName['J+S']->getSystemKey());
        self::assertNull($byName['J+S']->getParentId());
        self::assertSame(0, $byName['J+S']->getSortOrder());
        self::assertSame(10, $byName['Werkzeuge']->getSortOrder());
        self::assertSame(20, $byName['Fahrzeuge']->getSortOrder());
        self::assertSame(30, $byName['Infrastruktur']->getSortOrder());
        self::assertSame('Werkzeuge', $byName['Handwerkzeuge']->getParent()?->getName());
        self::assertSame('Fahrzeuge', $byName['Anhänger']->getParent()?->getName());
        self::assertSame('Infrastruktur', $byName['Sanitär & Wasserversorgung']->getParent()?->getName());
        self::assertSame(10, $byName['Handwerkzeuge']->getSortOrder());
        self::assertSame(80, $byName['Schutzausrüstung und Werkstattausstattung']->getSortOrder());
    }

    public function testEnsureOnlyAddsJsWhenOtherCategoriesExist(): void
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

        self::assertSame(1, $created);
        self::assertCount(1, $persisted);
        self::assertSame('J+S', $persisted[0]->getName());
        self::assertSame(ActivityGrossanlassProcurementCategory::SYSTEM_KEY_JS, $persisted[0]->getSystemKey());
    }

    public function testEnsureIsNoopWhenJsAlreadyPresent(): void
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
        $repo->method('findBy')->willReturn($existing);
        $repo->method('findOneBy')->willReturn(null);

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
