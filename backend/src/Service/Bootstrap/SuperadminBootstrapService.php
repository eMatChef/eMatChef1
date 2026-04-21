<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

use App\Entity\Department;
use App\Entity\Membership;
use App\Entity\Organisation;
use App\Entity\Profile;
use App\Entity\User;
use App\Enum\DepartmentRole;
use App\Service\Accounting\AccountingCostCenterBootstrapService;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Legt bei Bedarf Standard-Organisation/Department an und stellt einen Superadmin sicher
 * (gleiche Logik wie bei app:org-subset:import --ensure-superadmin).
 */
final class SuperadminBootstrapService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private AccountingCostCenterBootstrapService $accountingCostCenterBootstrap,
        private LoggerInterface $logger,
    ) {
    }

    public function ensure(string $email, string $plaintextPassword): User
    {
        $em = $this->entityManager;

        $organisation = $em->getRepository(Organisation::class)->findOneBy([]);
        if (!$organisation) {
            $organisation = new Organisation();
            $organisation->setId(IdGenerator::generateUnique($em, Organisation::class));
            $organisation->setName('Standard Organisation');
            $em->persist($organisation);
            $em->flush();
            $this->logger->warning('Superadmin-Bootstrap: Standard-Organisation angelegt.');
        }

        $department = $em->getRepository(Department::class)->findOneBy(['organisationId' => $organisation->getId()]);
        if (!$department) {
            $department = new Department();
            $department->setId(IdGenerator::generateUnique($em, Department::class));
            $department->setName('Standard Department');
            $department->setOrganisation($organisation);
            $em->persist($department);
            $em->flush();
            $this->accountingCostCenterBootstrap->ensureDefaultCostCenters($em, $department);
            $this->logger->warning('Superadmin-Bootstrap: Standard-Department angelegt.');
        }

        $profile = $em->getRepository(Profile::class)->findOneBy(['email' => $email]);
        if (!$profile) {
            $profile = new Profile();
            $profile->setId(IdGenerator::generateUnique($em, Profile::class));
            $profile->setEmail($email);
            $profile->setFirstName('Superadmin');
            $profile->setLastName('User');
            $profile->setNickname('Superadmin');
            $em->persist($profile);
        }
        $profile->setRoles(['ROLE_USER', 'ROLE_SUPERADMIN']);

        $user = $em->getRepository(User::class)->findOneBy(['profileId' => $profile->getId()]);
        if (!$user) {
            $user = new User();
            $user->setId(IdGenerator::generateUnique($em, User::class));
            $user->setProfile($profile);
            $user->setProfileId($profile->getId());
            $user->setState('active');
            $em->persist($user);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plaintextPassword));
        $user->setEmailVerified(true);

        $membership = $em->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId(),
        ]);
        if (!$membership) {
            $membership = new Membership();
            $membership->setUser($user);
            $membership->setDepartment($department);
            $em->persist($membership);
        }
        $membership->setRole(DepartmentRole::MATWART->value);
        $membership->setIsPrimary(true);

        $em->flush();

        return $user;
    }
}
