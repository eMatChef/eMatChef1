<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Department;
use App\Entity\ExternalStructureIdentity;
use App\Entity\Group;
use App\Entity\Organisation;
use PHPUnit\Framework\TestCase;

final class ExternalStructureIdentityTest extends TestCase
{
    public function testCanMapExternalStructureToDepartment(): void
    {
        $department = new Department();
        $department->setId('department-1');

        $identity = $this->identity()->setDepartment($department);

        self::assertTrue($identity->hasExactlyOneInternalTarget());
        $identity->assertExactlyOneInternalTarget();
        self::assertSame($department, $identity->getDepartment());
        self::assertNull($identity->getGroup());
    }

    public function testCanMapExternalStructureToGroup(): void
    {
        $group = new Group();
        $group->setId('group-1');

        $identity = $this->identity()->setGroup($group);

        self::assertTrue($identity->hasExactlyOneInternalTarget());
        $identity->assertExactlyOneInternalTarget();
        self::assertSame($group, $identity->getGroup());
        self::assertNull($identity->getDepartment());
    }

    public function testCanMapExternalStructureToOrganisation(): void
    {
        $organisation = new Organisation();
        $organisation->setId('organisation-1');

        $identity = $this->identity()->setOrganisation($organisation);

        self::assertTrue($identity->hasExactlyOneInternalTarget());
        $identity->assertExactlyOneInternalTarget();
        self::assertSame($organisation, $identity->getOrganisation());
        self::assertNull($identity->getDepartment());
        self::assertNull($identity->getGroup());
    }

    public function testRejectsMissingInternalTarget(): void
    {
        $identity = $this->identity();
        self::assertFalse($identity->hasExactlyOneInternalTarget());
        $this->expectException(\LogicException::class);
        $identity->assertExactlyOneInternalTarget();
    }

    public function testRejectsMultipleInternalTargets(): void
    {
        $department = new Department();
        $department->setId('department-1');
        $group = new Group();
        $group->setId('group-1');
        $organisation = new Organisation();
        $organisation->setId('organisation-1');
        $identity = $this->identity()
            ->setDepartment($department)
            ->setGroup($group)
            ->setOrganisation($organisation);

        self::assertFalse($identity->hasExactlyOneInternalTarget());
        $this->expectException(\LogicException::class);
        $identity->assertExactlyOneInternalTarget();
    }

    private function identity(): ExternalStructureIdentity
    {
        return (new ExternalStructureIdentity())
            ->setId('identity-123')
            ->setProvider('midata')
            ->setExternalGroupId('external-100')
            ->setExternalType('Group::Region')
            ->setExternalName('Pfadi Thun')
            ->setExternalParentId(null);
    }
}
