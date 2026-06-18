<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\MaterialItem;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * J+S-Leihkatalog «Lagersport & Trekking» — Bestellformular-Positionen in dept_js00000.
 */
class JsLeihkatalogCatalogService
{
    public const DEPARTMENT_ID = 'dept_js00000';
    public const ORDER_FORM_CATEGORY_ID = 'catjslagtr01';
    public const ORDER_FORM_CATEGORY_NAME = 'Lagersport & Trekking';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private JsPdfCatalogSyncService $pdfCatalogSync,
    ) {
    }

    public function findOrderFormCategory(): ?Category
    {
        $category = $this->entityManager->find(Category::class, self::ORDER_FORM_CATEGORY_ID);
        if ($category instanceof Category) {
            return $category;
        }

        return $this->entityManager->getRepository(Category::class)->findOneBy([
            'departmentId' => self::DEPARTMENT_ID,
            'name' => self::ORDER_FORM_CATEGORY_NAME,
        ]);
    }

    public function applyOrderFormFilters(QueryBuilder $qb, string $alias = 'm'): void
    {
        $qb->andWhere("$alias.deletedAt IS NULL")
            ->andWhere("$alias.isJsMaterial = true")
            ->andWhere("$alias.departmentId = :jsDept")
            ->setParameter('jsDept', self::DEPARTMENT_ID);

        $category = $this->findOrderFormCategory();
        if ($category instanceof Category) {
            $qb->andWhere("$alias.category = :jsOrderCategory")
                ->setParameter('jsOrderCategory', $category);

            return;
        }

        $orderableIds = $this->pdfCatalogSync->orderableMaterialIds();
        if ($orderableIds !== []) {
            $qb->andWhere("$alias.id IN (:jsOrderableIds)")
                ->setParameter('jsOrderableIds', $orderableIds);
        }
    }

    public function applyOrderFormSort(QueryBuilder $qb, string $alias = 'm'): void
    {
        $qb->addOrderBy("CASE WHEN $alias.no IS NULL THEN 9999 ELSE $alias.no END", 'ASC')
            ->addOrderBy("$alias.name", 'ASC');
    }

    /** 0-basierte PDF-Zeilennummer für Dropdown */
    public function pdfLineOrderForMaterial(MaterialItem $material): ?int
    {
        $no = $material->getNo();
        if ($no !== null && $no >= 1) {
            return $no - 1;
        }

        $sortIndex = $this->pdfCatalogSync->orderableMaterialSortIndex();
        $id = $material->getId();
        if ($id !== null && isset($sortIndex[$id])) {
            return $sortIndex[$id];
        }

        return null;
    }
}
