<?php

namespace App\Service\Grossanlass;

use App\Entity\Department;
use App\Entity\Group;
use App\Entity\GroupMembership;
use App\Entity\Membership;
use App\Entity\Profile;
use App\Entity\User;
use App\Service\AuditLogger;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GrossanlassHelperService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GrossanlassAccessService $access,
        private GrossanlassUserCardService $cards,
        private UserPasswordHasherInterface $passwordHasher,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Helfer (Abholer / Fahrer) als Grossanlass-User anlegen oder zuordnen und User-Karte erzeugen.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function inviteOrCreate(Department $department, User $actor, Group $group, array $data): array
    {
        $this->access->assertGrossanlassDepartment($department);
        if ($group->getDepartmentId() !== $department->getId()) {
            throw new \InvalidArgumentException('Gruppe gehört nicht zu diesem Grossanlass');
        }
        if (!$this->access->canManageGroupMembers($actor, $department, $group)) {
            throw new \RuntimeException('Keine Berechtigung, Helfer anzulegen');
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Ungültige E-Mail-Adresse');
        }

        $name = trim((string) ($data['name'] ?? ''));
        $mayDrive = !empty($data['may_drive']);

        $createdUser = false;
        $user = $this->findUserByEmail($email);
        if ($user === null) {
            $user = $this->createUser($email, $name, $actor);
            $createdUser = true;
        }
        if ($user->hasSuperAdminProfile()) {
            throw new \InvalidArgumentException('Superadmin-Konten können keinem Ressort zugewiesen werden');
        }

        $addedToDepartment = $this->ensureDepartmentMembership($department, $user, $actor);
        $addedToRessort = $this->ensureGroupMembership($group, $user);

        $this->entityManager->flush();

        $membership = $this->entityManager->getRepository(GroupMembership::class)
            ->findOneBy(['userId' => $user->getId(), 'groupId' => $group->getId()]);
        $role = $membership instanceof GroupMembership ? $membership->getRoleLabel() : 'Mitglied';
        $card = $this->cards->upsertForMember($department, $user, $group->getName(), $role, $mayDrive);

        return [
            'created_user' => $createdUser,
            'added_to_department' => $addedToDepartment,
            'added_to_ressort' => $addedToRessort,
            'user_id' => $user->getId(),
            'name' => $user->getProfile()?->getDisplayName() ?? $email,
            'email' => $email,
            'card' => $card,
        ];
    }

    private function findUserByEmail(string $email): ?User
    {
        $profile = $this->entityManager->getRepository(Profile::class)->findOneBy(['email' => $email]);
        if (!$profile instanceof Profile) {
            return null;
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['profileId' => $profile->getId()]);

        return $user instanceof User ? $user : null;
    }

    private function createUser(string $email, string $name, User $actor): User
    {
        [$firstName, $lastName, $nickname] = $this->splitName($name, $email);

        $profile = new Profile();
        $profile->setId(IdGenerator::generateUnique($this->entityManager, Profile::class));
        $profile->setEmail($email);
        $profile->setFirstName($firstName);
        $profile->setLastName($lastName);
        $profile->setNickname($nickname);
        $profile->setLanguage('de');
        $profile->setRoles(['ROLE_USER']);

        $user = new User();
        $user->setId(IdGenerator::generateUnique($this->entityManager, User::class));
        $user->setProfile($profile);
        $user->setState('active');
        $user->setEmailVerified(false);
        $user->setCreatedBy($actor);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));

        $this->entityManager->persist($profile);
        $this->entityManager->persist($user);
        $this->auditLogger->log(
            'user',
            $user->getId(),
            'user_created',
            $actor,
            $user,
            null,
            [
                'source' => ['old' => null, 'new' => 'grossanlass_helper'],
                'email' => ['old' => null, 'new' => $email],
            ]
        );

        return $user;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function splitName(string $name, string $email): array
    {
        if ($name === '') {
            $local = explode('@', $email)[0] ?? $email;

            return [null, null, $local];
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) === 1) {
            return [$parts[0], null, $parts[0]];
        }

        $first = array_shift($parts);

        return [$first, implode(' ', $parts), null];
    }

    private function ensureDepartmentMembership(Department $department, User $user, User $actor): bool
    {
        $existing = $this->entityManager->getRepository(Membership::class)->findOneBy([
            'userId' => $user->getId(),
            'departmentId' => $department->getId(),
        ]);
        if ($existing instanceof Membership) {
            return false;
        }

        $membership = new Membership();
        $membership->setUser($user);
        $membership->setDepartment($department);
        $membership->setRole('u');
        $hasAny = count($this->entityManager->getRepository(Membership::class)->findBy([
            'userId' => $user->getId(),
        ])) > 0;
        $membership->setIsPrimary(!$hasAny);

        $this->auditLogger->log(
            'membership',
            AuditLogger::buildMembershipEntityId($user->getId(), $department->getId()),
            'membership_created',
            $actor,
            $user,
            $department,
            [
                'role' => ['old' => null, 'new' => 'u'],
                'source' => ['old' => null, 'new' => 'grossanlass_helper'],
            ]
        );
        $this->entityManager->persist($membership);

        return true;
    }

    private function ensureGroupMembership(Group $group, User $user): bool
    {
        $existing = $this->entityManager->getRepository(GroupMembership::class)->findOneBy([
            'userId' => $user->getId(),
            'groupId' => $group->getId(),
        ]);
        if ($existing instanceof GroupMembership) {
            return false;
        }

        $membership = new GroupMembership();
        $membership->setUser($user);
        $membership->setGroup($group);
        $membership->setRole('member');
        $membership->setIsPrimary(false);
        $this->entityManager->persist($membership);

        return true;
    }
}
