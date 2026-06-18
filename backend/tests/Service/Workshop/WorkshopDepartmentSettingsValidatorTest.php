<?php

declare(strict_types=1);

namespace App\Tests\Service\Workshop;

use App\Entity\Category;
use App\Entity\DepartmentSetting;
use App\Service\Workshop\WorkshopDepartmentSettingsValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class WorkshopDepartmentSettingsValidatorTest extends TestCase
{
    public function testFilterAllowedKeepsOnlyWorkshopKeys(): void
    {
        $validator = $this->createValidator();

        $filtered = $validator->filterAllowed([
            'workshop.hourly_rate_chf' => '50',
            'activity.default_time_start' => '14:00',
            'workshop.unknown' => 'x',
        ]);

        $this->assertSame(['workshop.hourly_rate_chf' => '50'], $filtered);
    }

    public function testValidateAcceptsDefaults(): void
    {
        $validator = $this->createValidator();

        $errors = $validator->validate(DepartmentSetting::getWorkshopDefaults(), 'dept_test01');

        $this->assertSame([], $errors);
    }

    public function testValidateRejectsInvalidHourlyRate(): void
    {
        $validator = $this->createValidator();

        $errors = $validator->validate([
            'workshop.hourly_rate_chf' => '-1',
        ], 'dept_test01');

        $this->assertNotEmpty($errors);
    }

    public function testValidateRejectsInvalidReminderMode(): void
    {
        $validator = $this->createValidator();

        $errors = $validator->validate([
            'workshop.order_reminder_mode' => 'weekly',
        ], 'dept_test01');

        $this->assertNotEmpty($errors);
    }

    public function testNormalizeFormatsHourlyRate(): void
    {
        $validator = $this->createValidator();

        $normalized = $validator->normalize([
            'workshop.hourly_rate_chf' => '45,5',
        ]);

        $this->assertSame('45.50', $normalized['workshop.hourly_rate_chf']);
    }

    public function testValidateSparePartsCategoryMustBelongToDepartment(): void
    {
        $category = $this->createMock(Category::class);
        $category->method('getDepartmentId')->willReturn('other_dept01');

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn($category);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Category::class)->willReturn($repo);

        $validator = new WorkshopDepartmentSettingsValidator($em);

        $errors = $validator->validate([
            'workshop.spare_parts_category_id' => 'ca1234567890',
        ], 'dept_test01');

        $this->assertNotEmpty($errors);
    }

    private function createValidator(): WorkshopDepartmentSettingsValidator
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->willReturn(null);
        $em->method('getRepository')->with(Category::class)->willReturn($repo);

        return new WorkshopDepartmentSettingsValidator($em);
    }
}
