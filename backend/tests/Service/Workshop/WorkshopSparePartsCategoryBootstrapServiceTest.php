<?php

declare(strict_types=1);

namespace App\Tests\Service\Workshop;

use App\Entity\Category;
use App\Entity\Department;
use App\Entity\DepartmentSetting;
use App\Service\Workshop\WorkshopSparePartsCategoryBootstrapService;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class WorkshopSparePartsCategoryBootstrapServiceTest extends TestCase
{
    public function testEnsureCreatesCategoryAndSetting(): void
    {
        $department = $this->createMock(Department::class);
        $department->method('getId')->willReturn('dept_test01');

        $settingRepo = $this->createMock(EntityRepository::class);
        $settingRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [Department::class, $this->repoFind($department)],
            [DepartmentSetting::class, $settingRepo],
        ]);
        $em->method('createQueryBuilder')->willReturn($this->queryBuilderReturning([]));
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->exactly(2))->method('flush');

        $service = new WorkshopSparePartsCategoryBootstrapService($em);
        $categoryId = $service->ensure($department);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{12}$/', $categoryId);
    }

    public function testEnsureReusesExistingCategory(): void
    {
        $department = $this->createMock(Department::class);
        $department->method('getId')->willReturn('dept_test01');

        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn('ca1234567890');
        $category->method('getName')->willReturn('Repair-Parts');

        $setting = $this->createMock(DepartmentSetting::class);
        $setting->method('getSettingValue')->willReturn('ca1234567890');

        $settingRepo = $this->createMock(EntityRepository::class);
        $settingRepo->method('findOneBy')->willReturn($setting);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([
            [Department::class, $this->repoFind($department)],
            [DepartmentSetting::class, $settingRepo],
        ]);
        $em->method('createQueryBuilder')->willReturn($this->queryBuilderReturning([$category]));
        $em->expects($this->never())->method('persist');

        $service = new WorkshopSparePartsCategoryBootstrapService($em);

        $this->assertSame('ca1234567890', $service->ensure($department));
    }

    private function repoFind(?object $entity): EntityRepository
    {
        $repo = $this->createMock(EntityRepository::class);
        if ($entity !== null) {
            $repo->method('find')->willReturn($entity);
        }

        return $repo;
    }

    /**
     * @param list<object> $result
     */
    private function queryBuilderReturning(array $result): QueryBuilder
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn($result);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }
}
