<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Profile;
use App\Entity\User;
use App\Entity\UserEmailAlias;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Weitere bestätigte Adressen am selben User. Login bleibt die Profil-Mail.
 */
class UserEmailAliasService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function findUserByEmail(string $email): ?User
    {
        $email = $this->normalize($email);
        if ($email === '') {
            return null;
        }

        $profile = $this->entityManager->getRepository(Profile::class)->findOneBy(['email' => $email]);
        if ($profile instanceof Profile) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['profileId' => $profile->getId()]);
            if ($user instanceof User) {
                return $user;
            }
        }

        $alias = $this->entityManager->getRepository(UserEmailAlias::class)->findOneBy(['email' => $email]);
        if ($alias instanceof UserEmailAlias) {
            return $alias->getUser();
        }

        return null;
    }

    public function userOwnsEmail(User $user, string $email): bool
    {
        $email = $this->normalize($email);
        if ($email === '') {
            return false;
        }

        $primary = $this->normalize((string) ($user->getProfile()?->getEmail() ?? ''));
        if ($primary !== '' && $primary === $email) {
            return true;
        }

        $alias = $this->entityManager->getRepository(UserEmailAlias::class)->findOneBy(['email' => $email]);

        return $alias instanceof UserEmailAlias && $alias->getUserId() === $user->getId();
    }

    /**
     * Hängt eine über den Einladungslink bewiesene Adresse an den User.
     * No-op, wenn sie schon die Hauptadresse oder eine eigene weitere Adresse ist.
     */
    public function claimVerifiedEmail(User $user, string $email): void
    {
        $email = $this->normalize($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new UserEmailAliasConflictException('Ungültige E-Mail-Adresse');
        }
        if ($this->userOwnsEmail($user, $email)) {
            return;
        }

        $owner = $this->findUserByEmail($email);
        if ($owner instanceof User && $owner->getId() !== $user->getId()) {
            throw new UserEmailAliasConflictException('Diese Einladung gehört zu einem anderen Konto. Melde dich mit dieser Adresse an.');
        }

        $pendingOwner = $this->entityManager->getRepository(User::class)->findOneBy(['pendingEmail' => $email]);
        if ($pendingOwner instanceof User && $pendingOwner->getId() !== $user->getId()) {
            throw new UserEmailAliasConflictException('Diese E-Mail-Adresse ist bereits vergeben.');
        }

        $alias = new UserEmailAlias();
        $alias->setUser($user);
        $alias->setEmail($email);
        $this->entityManager->persist($alias);
    }

    public function releaseAlias(User $user, string $email): void
    {
        $email = $this->normalize($email);
        if ($email === '') {
            return;
        }
        $alias = $this->entityManager->getRepository(UserEmailAlias::class)->findOneBy(['email' => $email]);
        if ($alias instanceof UserEmailAlias && $alias->getUserId() === $user->getId()) {
            $this->entityManager->remove($alias);
        }
    }

    public function isEmailTaken(string $email, ?User $except = null): bool
    {
        $email = $this->normalize($email);
        if ($email === '') {
            return false;
        }

        $owner = $this->findUserByEmail($email);
        if ($owner instanceof User && ($except === null || $owner->getId() !== $except->getId())) {
            return true;
        }

        $pendingOwner = $this->entityManager->getRepository(User::class)->findOneBy(['pendingEmail' => $email]);
        if (!$pendingOwner instanceof User) {
            return false;
        }

        return $except === null || $pendingOwner->getId() !== $except->getId();
    }

    private function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
