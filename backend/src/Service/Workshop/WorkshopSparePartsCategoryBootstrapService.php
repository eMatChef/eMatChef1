<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Category;
use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Legt pro Department die Standard-Ersatzteile-Kategorie «Repair-Parts» an (idempotent)
 * und verknüpft workshop.spare_parts_category_id.
 */
final class WorkshopSparePartsCategoryBootstrapService
{
    public const CATEGORY_NAME = 'Repair-Parts';
    public const SETTING_KEY = 'workshop.spare_parts_category_id';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return string category.id (12 Zeichen)
     */
    public function ensure(Department|string $department): string
    {
        $departmentEntity = $department instanceof Department
            ? $department
            : $this->entityManager->getRepository(Department::class)->find($department);

        if (!$departmentEntity instanceof Department) {
            throw new \InvalidArgumentException('Department nicht gefunden');
        }

        $category = $this->findRepairPartsCategory($departmentEntity->getId());
        if (!$category instanceof Category) {
            $category = new Category();
            $category->setId(IdGenerator::generate());
            $category->setDepartment($departmentEntity);
            $category->setName(self::CATEGORY_NAME);
            $category->setDescription('Ersatzteile für interne Werkstatt-Reparaturen');
            $category->setSortOrder(900);
            $this->entityManager->persist($category);
            $this->entityManager->flush();
        }

        $this->ensureSetting($departmentEntity, $category->getId());

        return (string) $category->getId();
    }

    private function findRepairPartsCategory(string $departmentId): ?Category
    {
        $categories = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Category::class, 'c')
            ->where('c.departmentId = :dept')
            ->andWhere('c.parentId IS NULL')
            ->setParameter('dept', $departmentId)
            ->getQuery()
            ->getResult();

        $target = strtolower(self::CATEGORY_NAME);
        foreach ($categories as $category) {
            if (!$category instanceof Category) {
                continue;
            }
            if (strtolower(trim($category->getName())) === $target) {
                return $category;
            }
        }

        return null;
    }

    private function ensureSetting(Department $department, string $categoryId): void
    {
        $setting = $this->entityManager->getRepository(DepartmentSetting::class)->findOneBy([
            'departmentId' => $department->getId(),
            'settingKey' => self::SETTING_KEY,
        ]);

        if ($setting instanceof DepartmentSetting) {
            if (trim($setting->getSettingValue()) !== $categoryId) {
                $setting->setSettingValue($categoryId);
                $setting->setUpdatedAt(new \DateTime());
                $this->entityManager->flush();
            }

            return;
        }

        $setting = new DepartmentSetting();
        $setting->setId(IdGenerator::generate());
        $setting->setDepartment($department);
        $setting->setSettingKey(self::SETTING_KEY);
        $setting->setSettingValue($categoryId);
        $this->entityManager->persist($setting);
        $this->entityManager->flush();
    }
}
