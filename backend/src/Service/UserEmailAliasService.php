<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Profile;
use App\Entity\User;
use App\Entity\UserEmailAlias;
use App\Repository\ProfileRepository;
use App\Util\IdGenerator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * E-Mail-Adressen eines Users: Primary = Profile.email, weitere Adressen = UserEmailAlias.
 *
 * - Bewiesen (Einladungen, Besitzprüfung) sind nur verifizierte Aliase.
 * - Login und Passwort-Reset: Primary oder verifizierter, login-fähiger Alias.
 * - Eindeutigkeit: Primary, Aliase (unbestätigte bis Ablauf ihres Links) und pendingEmail anderer User.
 * Provider-E-Mails (OAuth) werden hier nie automatisch eingetragen.
 */
class UserEmailAliasService
{
    public const MAX_ADDITIONAL_EMAILS = 10;

    private const VERIFICATION_TTL = '+2 days';

    private const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProfileRepository $profileRepository,
        private VerificationEmailService $verificationEmailService,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Besitzer einer bewiesenen Adresse (Primary oder verifizierter Alias).
     */
    public function findUserByEmail(string $email): ?User
    {
        $email = $this->normalize($email);
        if ($email === '') {
            return null;
        }

        $user = $this->findUserByPrimaryEmail($email);
        if ($user instanceof User) {
            return $user;
        }

        $alias = $this->findAlias($email);

        return $alias instanceof UserEmailAlias && $alias->isVerified() ? $alias->getUser() : null;
    }

    /**
     * User für Login/Passwort-Reset: Primary oder verifizierter, login-fähiger Alias.
     */
    public function findLoginUserByEmail(string $email): ?User
    {
        $email = $this->normalize($email);
        if ($email === '') {
            return null;
        }

        $user = $this->findUserByPrimaryEmail($email);
        if ($user instanceof User) {
            return $user;
        }

        $alias = $this->findAlias($email);

        return $alias instanceof UserEmailAlias && $alias->canLogin() ? $alias->getUser() : null;
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

        $alias = $this->findAlias($email);

        return $alias instanceof UserEmailAlias && $alias->getUserId() === $user->getId() && $alias->isVerified();
    }

    /**
     * Hängt eine über den Einladungslink bewiesene Adresse an den User.
     * No-op, wenn sie schon die Hauptadresse oder eine eigene verifizierte Adresse ist.
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

        $alias = $this->findAlias($email);
        if ($alias instanceof UserEmailAlias && $alias->getUserId() === $user->getId()) {
            // Eigene, noch unbestätigte Adresse: der Einladungslink beweist sie.
            $alias->markVerified();

            return;
        }

        $owner = $this->findUserByEmail($email);
        if ($owner instanceof User && $owner->getId() !== $user->getId()) {
            throw new UserEmailAliasConflictException('Diese Einladung gehört zu einem anderen Konto. Melde dich mit dieser Adresse an.');
        }
        if ($this->isEmailTaken($email, $user)) {
            throw new UserEmailAliasConflictException('Diese E-Mail-Adresse ist bereits vergeben.');
        }
        $this->removeExpiredUnverifiedAlias($email);

        $alias = new UserEmailAlias();
        $alias->setId(IdGenerator::generateUnique($this->entityManager, UserEmailAlias::class));
        $alias->setUser($user);
        $alias->setEmail($email);
        $alias->markVerified();
        $this->entityManager->persist($alias);
    }

    public function releaseAlias(User $user, string $email): void
    {
        $email = $this->normalize($email);
        if ($email === '') {
            return;
        }
        $alias = $this->findAlias($email);
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

        $isOther = static fn (User $owner): bool => $except === null || $owner->getId() !== $except->getId();

        $primaryOwner = $this->findUserByPrimaryEmail($email);
        if ($primaryOwner instanceof User && $isOther($primaryOwner)) {
            return true;
        }

        $alias = $this->findAlias($email);
        if ($alias instanceof UserEmailAlias && !$alias->isExpiredUnverified() && $isOther($alias->getUser())) {
            return true;
        }

        $pendingOwner = $this->entityManager->getRepository(User::class)->findOneBy(['pendingEmail' => $email]);

        return $pendingOwner instanceof User && $isOther($pendingOwner);
    }

    /**
     * @return list<UserEmailAlias>
     */
    public function listForUser(User $user): array
    {
        /** @var list<UserEmailAlias> $aliases */
        $aliases = $this->entityManager->getRepository(UserEmailAlias::class)
            ->findBy(['userId' => $user->getId()], ['createdAt' => 'ASC']);

        return $aliases;
    }

    public function findForUser(User $user, string $aliasId): ?UserEmailAlias
    {
        $alias = $this->entityManager->getRepository(UserEmailAlias::class)->find($aliasId);

        return $alias instanceof UserEmailAlias && $alias->getUserId() === $user->getId() ? $alias : null;
    }

    /**
     * Zusätzliche Adresse anlegen (unbestätigt) und Bestätigungslink senden.
     *
     * @throws \InvalidArgumentException       ungültige Eingabe oder Limit
     * @throws UserEmailAliasConflictException Adresse gehört bereits einem Konto oder ist schon eingetragen
     */
    public function addEmail(User $user, string $email): UserEmailAlias
    {
        $email = $this->normalize($email);
        if ($email === '' || mb_strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Ungültige E-Mail-Adresse');
        }
        if ($this->normalize((string) ($user->getProfile()?->getEmail() ?? '')) === $email) {
            throw new UserEmailAliasConflictException('Diese Adresse ist bereits deine Hauptadresse.');
        }
        $own = $this->findAlias($email);
        if ($own instanceof UserEmailAlias && $own->getUserId() === $user->getId()) {
            throw new UserEmailAliasConflictException('Diese Adresse ist bereits eingetragen.');
        }
        if ($this->isEmailTaken($email, $user)) {
            throw new UserEmailAliasConflictException('Diese E-Mail-Adresse ist bereits vergeben.');
        }
        if (\count($this->listForUser($user)) >= self::MAX_ADDITIONAL_EMAILS) {
            throw new \InvalidArgumentException('Es können höchstens ' . self::MAX_ADDITIONAL_EMAILS . ' weitere Adressen eingetragen werden.');
        }
        $this->removeExpiredUnverifiedAlias($email);

        $alias = new UserEmailAlias();
        $alias->setId(IdGenerator::generateUnique($this->entityManager, UserEmailAlias::class));
        $alias->setUser($user);
        $alias->setEmail($email);
        $token = $this->issueVerificationToken($alias);
        $this->entityManager->persist($alias);
        $this->log($user, 'profile_email_added', ['email' => ['old' => null, 'new' => $email]]);
        $this->entityManager->flush();

        $this->sendVerification($user, $alias, $token);

        return $alias;
    }

    /**
     * @throws \InvalidArgumentException bereits bestätigt
     * @throws \RuntimeException         Cooldown
     */
    public function resendVerification(User $user, UserEmailAlias $alias): void
    {
        if ($alias->isVerified()) {
            throw new \InvalidArgumentException('Diese Adresse ist bereits bestätigt.');
        }
        $sentAt = $alias->getVerificationSentAt();
        if ($sentAt !== null && $sentAt > new \DateTime('-' . self::RESEND_COOLDOWN_SECONDS . ' seconds')) {
            throw new \RuntimeException('Bitte warte kurz, bevor du den Link erneut anforderst.');
        }

        $token = $this->issueVerificationToken($alias);
        $this->entityManager->flush();
        $this->sendVerification($user, $alias, $token);
    }

    /**
     * Bestätigungslink einer zusätzlichen Adresse einlösen.
     *
     * @return UserEmailAlias|null null = Token gehört zu keiner zusätzlichen Adresse
     *
     * @throws \DomainException                Link abgelaufen
     * @throws UserEmailAliasConflictException Adresse inzwischen von einem anderen Konto belegt
     */
    public function verifyByToken(string $token): ?UserEmailAlias
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $alias = $this->entityManager->getRepository(UserEmailAlias::class)
            ->findOneBy(['verificationTokenHash' => hash('sha256', $token)]);
        if (!$alias instanceof UserEmailAlias) {
            return null;
        }
        if ($alias->getVerificationExpiresAt() === null || $alias->getVerificationExpiresAt() < new \DateTime()) {
            throw new \DomainException('Bestätigungslink ist abgelaufen.');
        }
        if ($this->isEmailTaken($alias->getEmail(), $alias->getUser())) {
            throw new UserEmailAliasConflictException('Diese E-Mail-Adresse ist bereits vergeben.');
        }

        $alias->markVerified();
        $this->log($alias->getUser(), 'profile_email_verified', ['email' => ['old' => null, 'new' => $alias->getEmail()]]);
        $this->entityManager->flush();

        return $alias;
    }

    /**
     * Verifizierte zusätzliche Adresse wird Primary; die bisherige Primary bleibt als zusätzliche Adresse erhalten.
     * Atomar: beide Adressen werden in einer Transaktion getauscht.
     *
     * @throws \InvalidArgumentException Adresse nicht verifiziert
     */
    public function makePrimary(User $user, UserEmailAlias $alias): void
    {
        $profile = $user->getProfile();
        if (!$profile instanceof Profile || $alias->getUserId() !== $user->getId()) {
            throw new \InvalidArgumentException('Adresse nicht gefunden.');
        }
        if (!$alias->isVerified()) {
            throw new \InvalidArgumentException('Nur bestätigte Adressen können Hauptadresse werden.');
        }

        $oldPrimary = $this->normalize($profile->getEmail());
        $newPrimary = $alias->getEmail();
        $oldPrimaryVerified = $user->isEmailVerified();

        $this->entityManager->wrapInTransaction(function () use ($user, $profile, $alias, $oldPrimary, $newPrimary, $oldPrimaryVerified): void {
            $profile->setEmail($newPrimary);
            $profile->setUpdatedAt(new \DateTime());
            $alias->setEmail($oldPrimary);
            if ($oldPrimaryVerified) {
                $alias->markVerified();
            } else {
                $alias->resetVerification();
            }
            $user->setEmailVerified(true);
            if ($user->getPendingEmail() !== null) {
                // Offener Wechsel über das Profilformular würde die neue Primary sonst überschreiben.
                $user->setPendingEmail(null);
                $user->setEmailVerificationToken(null);
                $user->setEmailVerificationExpiresAt(null);
            }
            $this->log($user, 'profile_primary_email_changed', ['email' => ['old' => $oldPrimary, 'new' => $newPrimary]]);
            $this->entityManager->flush();
        });
    }

    public function removeEmail(User $user, UserEmailAlias $alias): void
    {
        if ($alias->getUserId() !== $user->getId()) {
            throw new \InvalidArgumentException('Adresse nicht gefunden.');
        }

        foreach ($user->getMemberships() as $membership) {
            if ($membership->getNotificationEmail() === $alias->getEmail()) {
                $membership->setNotificationEmail(null);
            }
        }
        $this->log($user, 'profile_email_removed', ['email' => ['old' => $alias->getEmail(), 'new' => null]]);
        $this->entityManager->remove($alias);
        $this->entityManager->flush();
    }

    private function findUserByPrimaryEmail(string $email): ?User
    {
        $profile = $this->profileRepository->findOneByEmailInsensitive($email);
        if (!$profile instanceof Profile) {
            return null;
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['profileId' => $profile->getId()]);

        return $user instanceof User ? $user : null;
    }

    private function findAlias(string $email): ?UserEmailAlias
    {
        $alias = $this->entityManager->getRepository(UserEmailAlias::class)->findOneBy(['email' => $email]);

        return $alias instanceof UserEmailAlias ? $alias : null;
    }

    /** Fremde unbestätigte Adresse mit abgelaufenem Link gibt die Adresse frei. */
    private function removeExpiredUnverifiedAlias(string $email): void
    {
        $alias = $this->findAlias($email);
        if ($alias instanceof UserEmailAlias && $alias->isExpiredUnverified()) {
            $this->entityManager->remove($alias);
            $this->entityManager->flush();
        }
    }

    private function issueVerificationToken(UserEmailAlias $alias): string
    {
        $token = bin2hex(random_bytes(32));
        $alias->setVerificationToken(hash('sha256', $token), new \DateTime(self::VERIFICATION_TTL));

        return $token;
    }

    private function sendVerification(User $user, UserEmailAlias $alias, string $token): void
    {
        $expiresAt = $alias->getVerificationExpiresAt() ?? new \DateTime(self::VERIFICATION_TTL);
        $this->verificationEmailService->sendPendingEmailChangeVerification($user, $alias->getEmail(), $token, $expiresAt);
    }

    /**
     * @param array<string, array{old: mixed, new: mixed}> $changes
     */
    private function log(User $user, string $action, array $changes): void
    {
        $this->auditLogger->log('profile', (string) $user->getProfileId(), $action, $user, $user, null, $changes);
    }

    private function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
