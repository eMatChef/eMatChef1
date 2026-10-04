<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Department;
use App\Entity\ExternalStructureIdentity;
use App\Entity\Group;
use App\Entity\Organisation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ExternalStructureIdentityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExternalStructureIdentity::class);
    }

    public function findOneByProviderAndExternalGroupId(string $provider, string $externalGroupId): ?ExternalStructureIdentity
    {
        return $this->findOneBy([
            'provider' => $provider,
            'externalGroupId' => $externalGroupId,
        ]);
    }

    /**
     * @return list<ExternalStructureIdentity>
     */
    public function findByDepartment(Department $department): array
    {
        return $this->findBy(['department' => $department]);
    }

    /**
     * @return list<ExternalStructureIdentity>
     */
    public function findByGroup(Group $group): array
    {
        return $this->findBy(['group' => $group]);
    }

    /**
     * @return list<ExternalStructureIdentity>
     */
    public function findByOrganisation(Organisation $organisation): array
    {
        return $this->findBy(['organisation' => $organisation]);
    }
}
