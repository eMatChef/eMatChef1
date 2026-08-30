<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\Department;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Standard-Beschaffungskategorien für neue Grossanlässe (Stand PFF 2027).
 * Nur Namen und Baum — keine Budgets, Positionen oder Anfragen.
 */
final class GrossanlassProcurementCategoryBootstrapService
{
    /**
     * @var list<array{name: string, children?: list<string>}>
     */
    public const TREE = [
        ['name' => ActivityGrossanlassProcurementCategory::JS_NAME],
        [
            'name' => 'Werkzeuge',
            'children' => [
                'Handwerkzeuge',
                'Elektrowerkzeuge und Maschinen',
                'Mess- und Prüfwerkzeuge',
                'Schneid- und Trennwerkzeuge',
                'Gartenwerkzeuge',
                'Druckluftwerkzeuge',
                'Sanitär- und Installationswerkzeuge',
                'Schutzausrüstung und Werkstattausstattung',
            ],
        ],
        [
            'name' => 'Fahrzeuge',
            'children' => [
                'Anhänger',
                'Flurförderzeuge & Hebezeuge',
                'Gelände- und Utility-Fahrzeuge',
                'Logistik- und Transportfahrzeuge (Strasse)',
            ],
        ],
        [
            'name' => 'Infrastruktur',
            'children' => [
                'Sanitär & Wasserversorgung',
                'Energie- & Stromversorgung',
                'Geländeschutz, Absperrung & Leitsysteme',
                'Bauten, Überdachungen & Raummodule',
                'Abfallmanagement & Entsorgung',
                'Kommunikation & IT-Infrastruktur',
                'Klimatisierung & Lüftung',
            ],
        ],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Leeres Department: voller Vorlagen-Baum. Sonst nur J+S nachziehen.
     *
     * @return int Anzahl neu angelegter Kategorien
     */
    public function ensureForDepartment(Department $department): int
    {
        $existing = $this->load($department);
        if ($existing === []) {
            return $this->seedTree($department);
        }

        return $this->ensureJs($department, $existing) ? 1 : 0;
    }

    /**
     * @return list<string>
     */
    public static function expectedNames(): array
    {
        $names = [];
        foreach (self::TREE as $node) {
            $names[] = $node['name'];
            foreach ($node['children'] ?? [] as $child) {
                $names[] = $child;
            }
        }

        return $names;
    }

    /**
     * @param list<ActivityGrossanlassProcurementCategory> $existing
     */
    private function ensureJs(Department $department, array $existing): bool
    {
        foreach ($existing as $row) {
            if ($row->getSystemKey() === ActivityGrossanlassProcurementCategory::SYSTEM_KEY_JS) {
                return $this->normalizeJs($row);
            }
        }
        foreach ($existing as $row) {
            if ($row->getParentId() !== null) {
                continue;
            }
            if (!ActivityGrossanlassProcurementCategory::isJsNameAlias($row->getName())) {
                continue;
            }
            $row->setSystemKey(ActivityGrossanlassProcurementCategory::SYSTEM_KEY_JS);
            $this->normalizeJs($row);
            $this->entityManager->flush();

            return true;
        }

        $this->createCategory(
            $department,
            ActivityGrossanlassProcurementCategory::JS_NAME,
            null,
            0,
            ActivityGrossanlassProcurementCategory::SYSTEM_KEY_JS,
        );
        $this->entityManager->flush();

        return true;
    }

    private function normalizeJs(ActivityGrossanlassProcurementCategory $category): bool
    {
        $changed = false;
        if ($category->getName() !== ActivityGrossanlassProcurementCategory::JS_NAME) {
            $category->setName(ActivityGrossanlassProcurementCategory::JS_NAME);
            $changed = true;
        }
        if ($category->getParentId() !== null) {
            $category->setParent(null);
            $changed = true;
        }
        if ($changed) {
            $category->touchUpdatedAt();
            $this->entityManager->flush();
        }

        return false;
    }

    private function seedTree(Department $department): int
    {
        $created = 0;
        $topSort = 0;
        foreach (self::TREE as $node) {
            $isJs = ActivityGrossanlassProcurementCategory::isJsNameAlias($node['name']);
            if (!$isJs) {
                $topSort += 10;
            }
            $parent = $this->createCategory(
                $department,
                $isJs ? ActivityGrossanlassProcurementCategory::JS_NAME : $node['name'],
                null,
                $isJs ? 0 : $topSort,
                $isJs ? ActivityGrossanlassProcurementCategory::SYSTEM_KEY_JS : null,
            );
            ++$created;
            $childSort = 10;
            foreach ($node['children'] ?? [] as $childName) {
                $this->createCategory($department, $childName, $parent, $childSort, null);
                ++$created;
                $childSort += 10;
            }
        }
        $this->entityManager->flush();

        return $created;
    }

    private function createCategory(
        Department $department,
        string $name,
        ?ActivityGrossanlassProcurementCategory $parent,
        int $sortOrder,
        ?string $systemKey,
    ): ActivityGrossanlassProcurementCategory {
        $category = new ActivityGrossanlassProcurementCategory();
        $category->setId(GrossanlassIdGenerator::unique(
            $this->entityManager,
            GrossanlassIdGenerator::PROCUREMENT_CATEGORY,
            ActivityGrossanlassProcurementCategory::class,
        ));
        $category->setDepartment($department);
        $category->setParent($parent);
        $category->setName($name);
        $category->setSortOrder($sortOrder);
        if ($systemKey !== null) {
            $category->setSystemKey($systemKey);
        }
        $this->entityManager->persist($category);

        return $category;
    }

    /**
     * @return list<ActivityGrossanlassProcurementCategory>
     */
    private function load(Department $department): array
    {
        $rows = $this->entityManager->getRepository(ActivityGrossanlassProcurementCategory::class)
            ->findBy(['departmentId' => $department->getId()]);

        $result = [];
        foreach ($rows as $row) {
            if ($row instanceof ActivityGrossanlassProcurementCategory) {
                $result[] = $row;
            }
        }

        return $result;
    }
}
