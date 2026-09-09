<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

use App\Entity\ActivityGrossanlassProcurementCategory;
use App\Entity\ActivityGrossanlassProcurementLine;
use App\Entity\Department;
use App\Entity\DepartmentGrossanlassInquiry;
use App\Util\GrossanlassIdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Grobe Beschaffungskategorien (Bereiche / Unterbereiche). Kein Artikel-Katalog.
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

    /**
     * Früher fälschlich als dritte Ebene angelegt — nur noch zum Aufräumen.
     *
     * @var array<string, list<string>>
     */
    private const SEEDED_ITEMS = [
        'Handwerkzeuge' => [
            'Schraubenschlüssel',
            'Zangen',
            'Hämmer',
            'Schaufeln',
            'Pickel',
            'Besen',
            'Werkzeugkoffer',
            'Schraubzwingen',
            'Leitern',
            'Speizeisen',
            'Schrauben',
            'Holzschrauben',
            'Maschinenschrauben',
            'Muttern',
            'Unterlegscheiben',
            'Gewindestangen',
            'Nägel',
            'Winkelverbinder',
            'Lochplatten',
            'Dübel',
        ],
        'Elektrowerkzeuge und Maschinen' => [
            'Akkuschrauber',
            'Bohrmaschinen',
            'Bohrhämmer',
            'Winkelschleifer',
            'Stichsägen',
            'Handkreissägen',
            'Betonmischer',
        ],
        'Mess- und Prüfwerkzeuge' => [
            'Messbänder',
            'Wasserwaagen',
            'Laser-Distanzmesser',
        ],
        'Schneid- und Trennwerkzeuge' => [
            'Bolzenschneider',
            'Trennschleifer',
            'Sägen',
        ],
        'Gartenwerkzeuge' => [
            'Rechen',
            'Spaten',
            'Heckenscheren',
        ],
        'Druckluftwerkzeuge' => [
            'Kompressoren',
            'Druckluftschläuche',
            'Nagler',
        ],
        'Sanitär- und Installationswerkzeuge' => [
            'Rohrzangen',
            'Rohrschneider',
            'Installationswerkzeuge',
        ],
        'Schutzausrüstung und Werkstattausstattung' => [
            'Helme',
            'Handschuhe',
            'Warnwesten',
            'Schutzbrillen',
            'Gehörschutz',
            'persönliche Schutzausrüstung',
        ],
        'Anhänger' => [
            'Autoanhänger',
            'Kippanhänger',
            'Tieflader',
        ],
        'Flurförderzeuge & Hebezeuge' => [
            'Hubwagen',
            'Gabelstapler',
            'Teleskoplader',
            'Krane',
        ],
        'Gelände- und Utility-Fahrzeuge' => [
            'Traktoren',
            'Hoflader',
            'Gator',
            'Quad',
            'Arbeitsmaschinen',
            'Bagger',
            'Dumper',
            'Radlader',
            'Rüttelplatten',
            'Grabenstampfer',
        ],
        'Logistik- und Transportfahrzeuge (Strasse)' => [
            'Transporter',
            'Lastwagen',
        ],
        'Sanitär & Wasserversorgung' => [
            'Wasserschläuche',
            'Schlauchkupplungen',
            'Verteiler',
            'Armaturen',
            'Wasserhähne',
            'PE-Rohre',
            'Rohrverbinder',
            'Absperrventile',
            'Waschbecken',
            'Ausgussbecken',
        ],
        'Energie- & Stromversorgung' => [
            'Verlängerungskabel',
            'Kabelrollen',
            'Kabelbrücken',
            'Stromverteiler',
            'Generatoren',
        ],
        'Geländeschutz, Absperrung & Leitsysteme' => [
            'Bauzäune',
            'Baustellenabschrankungen',
            'Absperrbänder',
        ],
        'Bauten, Überdachungen & Raummodule' => [
            'Schaltafeln',
            'Festzelte',
            'Container',
            'Raummodule',
            'Rundholz',
            'Stangenholz',
            'Kantholz',
            'Balken',
            'Bretter',
            'Latten',
            'Schalungsbretter',
            'Holzreste',
        ],
        'Abfallmanagement & Entsorgung' => [
            'Mulden',
            'Abfallcontainer',
        ],
        'Kommunikation & IT-Infrastruktur' => [
            'Funkgeräte',
            'Netzwerktechnik',
        ],
        'Klimatisierung & Lüftung' => [
            'Ventilatoren',
            'Heizgeräte',
        ],
    ];

    /** @var array<string, string> extra Unterkategorien aus dem Artikel-Seed → grobe Zielkategorie */
    private const REHOME_PACKAGES = [
        'Befestigungsmaterial' => 'Handwerkzeuge',
        'Holzmaterial' => 'Bauten, Überdachungen & Raummodule',
        'Baumaschinen' => 'Gelände- und Utility-Fahrzeuge',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return int Anzahl neu angelegter Kategorien
     */
    public function ensureForDepartment(Department $department): int
    {
        $existing = $this->load($department);
        if ($existing === []) {
            return $this->seedTree($department);
        }

        $created = 0;
        if ($this->ensureJs($department, $existing)) {
            ++$created;
            $existing = $this->load($department);
        }
        $created += $this->ensureMissingFromTemplate($department, $existing);
        $this->removeSeededItems($department);
        $this->rehomeExtraPackages($department);

        return $created;
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
            ActivityGrossanlassProcurementCategory::KIND_PACKAGE,
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
                ActivityGrossanlassProcurementCategory::KIND_PACKAGE,
            );
            ++$created;
            $childSort = 10;
            foreach ($node['children'] ?? [] as $childName) {
                $this->createCategory(
                    $department,
                    $childName,
                    $parent,
                    $childSort,
                    null,
                    ActivityGrossanlassProcurementCategory::KIND_PACKAGE,
                );
                ++$created;
                $childSort += 10;
            }
        }
        $this->entityManager->flush();

        return $created;
    }

    /**
     * @param list<ActivityGrossanlassProcurementCategory> $existing
     */
    private function ensureMissingFromTemplate(Department $department, array $existing): int
    {
        $created = 0;
        $index = $this->indexByParentAndName($existing);
        foreach (self::TREE as $node) {
            $isJs = ActivityGrossanlassProcurementCategory::isJsNameAlias($node['name']);
            $root = $this->findInIndex($index, null, $isJs ? ActivityGrossanlassProcurementCategory::JS_NAME : $node['name']);
            if ($root === null) {
                continue;
            }
            $sort = 10;
            foreach ($node['children'] ?? [] as $childName) {
                $row = $this->findInIndex($index, $root->getId(), $childName);
                if ($row === null) {
                    $row = $this->createCategory(
                        $department,
                        $childName,
                        $root,
                        $sort,
                        null,
                        ActivityGrossanlassProcurementCategory::KIND_PACKAGE,
                    );
                    $index[$this->indexKey($root->getId(), $childName)] = $row;
                    ++$created;
                }
                $sort += 10;
            }
        }
        if ($created > 0) {
            $this->entityManager->flush();
        }

        return $created;
    }

    private function removeSeededItems(Department $department): void
    {
        $seeded = [];
        foreach (self::SEEDED_ITEMS as $names) {
            foreach ($names as $name) {
                $seeded[mb_strtolower($name, 'UTF-8')] = true;
            }
        }
        $changed = false;
        foreach ($this->load($department) as $row) {
            $key = mb_strtolower($row->getName(), 'UTF-8');
            $parent = $row->getParent();
            if (!isset($seeded[$key]) || $parent === null) {
                continue;
            }
            $this->rewriteInquiryCategoryId($department, $row->getId(), $parent->getId());
            $this->rewriteLineCategoryId($department, $row->getId(), $parent->getId());
            $this->entityManager->remove($row);
            $changed = true;
        }
        if ($changed) {
            $this->entityManager->flush();
        }
    }

    private function rehomeExtraPackages(Department $department): void
    {
        $existing = $this->load($department);
        $byName = [];
        foreach ($existing as $row) {
            $byName[mb_strtolower($row->getName(), 'UTF-8')] = $row;
        }
        $changed = false;
        foreach (self::REHOME_PACKAGES as $fromName => $toName) {
            $from = $byName[mb_strtolower($fromName, 'UTF-8')] ?? null;
            $to = $byName[mb_strtolower($toName, 'UTF-8')] ?? null;
            if ($from === null || $to === null || $from->getId() === $to->getId()) {
                continue;
            }
            foreach ($existing as $child) {
                if ($child->getParentId() !== $from->getId()) {
                    continue;
                }
                $child->setParent($to);
                $child->touchUpdatedAt();
                $changed = true;
            }
            $this->rewriteInquiryCategoryId($department, $from->getId(), $to->getId());
            $this->rewriteLineCategoryId($department, $from->getId(), $to->getId());
            $this->entityManager->remove($from);
            unset($byName[mb_strtolower($fromName, 'UTF-8')]);
            $changed = true;
        }
        if ($changed) {
            $this->entityManager->flush();
        }
    }

    private function rewriteInquiryCategoryId(Department $department, string $fromId, string $toId): void
    {
        $rows = $this->entityManager->getRepository(DepartmentGrossanlassInquiry::class)
            ->findBy(['departmentId' => $department->getId()]);
        foreach ($rows as $inquiry) {
            if (!$inquiry instanceof DepartmentGrossanlassInquiry) {
                continue;
            }
            $ids = $inquiry->getCategoryIds();
            $next = [];
            $hit = false;
            foreach ($ids as $id) {
                if ($id === $fromId) {
                    $next[] = $toId;
                    $hit = true;
                    continue;
                }
                $next[] = $id;
            }
            if ($hit) {
                $inquiry->setCategoryIds(array_values(array_unique($next)));
            }
        }
    }

    private function rewriteLineCategoryId(Department $department, string $fromId, string $toId): void
    {
        $lines = $this->entityManager->getRepository(ActivityGrossanlassProcurementLine::class)
            ->findBy(['departmentId' => $department->getId(), 'categoryId' => $fromId]);
        foreach ($lines as $line) {
            if (!$line instanceof ActivityGrossanlassProcurementLine) {
                continue;
            }
            $target = $this->entityManager->find(ActivityGrossanlassProcurementCategory::class, $toId);
            if ($target instanceof ActivityGrossanlassProcurementCategory) {
                $line->setCategory($target);
            }
        }
    }

    /**
     * @param list<ActivityGrossanlassProcurementCategory> $rows
     * @return array<string, ActivityGrossanlassProcurementCategory>
     */
    private function indexByParentAndName(array $rows): array
    {
        $index = [];
        foreach ($rows as $row) {
            $index[$this->indexKey($row->getParentId(), $row->getName())] = $row;
        }

        return $index;
    }

    /**
     * @param array<string, ActivityGrossanlassProcurementCategory> $index
     */
    private function findInIndex(array $index, ?string $parentId, string $name): ?ActivityGrossanlassProcurementCategory
    {
        return $index[$this->indexKey($parentId, $name)] ?? null;
    }

    private function indexKey(?string $parentId, string $name): string
    {
        return ($parentId ?? '') . "\n" . mb_strtolower(trim($name), 'UTF-8');
    }

    private function createCategory(
        Department $department,
        string $name,
        ?ActivityGrossanlassProcurementCategory $parent,
        int $sortOrder,
        ?string $systemKey,
        string $kind,
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
        $category->setKind($kind);
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
