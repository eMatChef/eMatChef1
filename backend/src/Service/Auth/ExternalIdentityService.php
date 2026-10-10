<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\ExternalIdentity;
use App\Entity\User;
use App\Repository\ExternalIdentityRepository;
use App\Service\AuditLogger;
use App\Util\IdGenerator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Verknüpfte Anmeldungen (externe Login-Identitäten) eines angemeldeten Users.
 *
 * - Ein User kann mehrere Identitäten haben, auch mehrere desselben Anbieters; provider + externe ID sind global eindeutig.
 * - Keine Zusammenführung über E-Mail, keine Übernahme der Anbieter-Adresse als UserEmailAlias.
 * - Trennen entfernt ausschliesslich die ExternalIdentity: Memberships, Gruppen, Rollen, Struktur-Mappings bleiben.
 * - Gespeichert und protokolliert wird nie ein Token, Code oder Secret.
 */
class ExternalIdentityService
{
    /** Unterstützte Anbieter mit Anzeigename (weitere später hier ergänzen). */
    public const PROVIDERS = [
        'google' => 'Google',
        'midata' => 'MiData / db.scout.ch',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExternalIdentityRepository $identities,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Tatsächlich nutzbare Anmeldemethoden des Users, ohne die ausgenommene Identität:
     * «password» (aktives Konto mit bestätigter Primary-Adresse: Passwort bekannt oder per Reset setzbar)
     * und «identity:<id>» je weitere Identität. Ein inaktives Konto hat keine nutzbare Methode.
     *
     * @return list<string>
     */
    public function remainingLoginMethods(User $user, ?ExternalIdentity $excluding = null): array
    {
        if ($user->getState() !== 'active') {
            return [];
        }
        $methods = [];
        if ($user->isEmailVerified() && trim((string) $user->getProfile()?->getEmail()) !== '') {
            $methods[] = 'password';
        }
        foreach ($user->getExternalIdentities() as $identity) {
            if ($identity !== $excluding && $identity->getId() !== $excluding?->getId()) {
                $methods[] = 'identity:' . $identity->getId();
            }
        }

        return $methods;
    }

    public function canUnlink(User $user, ExternalIdentity $identity): bool
    {
        return $this->remainingLoginMethods($user, $identity) !== [];
    }

    public function find(User $user, string $identityId): ?ExternalIdentity
    {
        foreach ($user->getExternalIdentities() as $identity) {
            if ($identity->getId() === $identityId) {
                return $identity;
            }
        }

        return null;
    }

    /**
     * @throws ExternalIdentityException NOT_FOUND (auch fremde IDs), LAST_LOGIN_METHOD
     */
    public function unlink(User $user, string $identityId): ExternalIdentity
    {
        $identity = $this->find($user, $identityId);
        if (!$identity instanceof ExternalIdentity) {
            throw new ExternalIdentityException(ExternalIdentityException::NOT_FOUND, 'External identity not found');
        }
        if (!$this->canUnlink($user, $identity)) {
            throw new ExternalIdentityException(ExternalIdentityException::LAST_LOGIN_METHOD, 'The identity is the only remaining login method');
        }

        $provider = $identity->getProvider();
        $user->removeExternalIdentity($identity);
        $this->entityManager->remove($identity);
        if ($provider === 'google' && $user->getGoogleId() === $identity->getExternalUserId()) {
            // Legacy-Feld nachziehen: nächste verbleibende Google-Identität oder leer.
            $next = null;
            foreach ($user->getExternalIdentities() as $other) {
                if ($other->getProvider() === 'google') {
                    $next = $other->getExternalUserId();
                    break;
                }
            }
            $user->setGoogleId($next);
        }
        $this->auditLogger->log('user', $user->getId(), 'external_identity_unlinked', $user, $user, null, [
            'provider' => ['old' => $provider, 'new' => null],
        ]);
        $this->entityManager->flush();

        return $identity;
    }

    /**
     * Verbindet eine bereits authentifizierte Anbieter-Identität mit dem angemeldeten User (kein Login, kein neuer User).
     * Idempotent für dieselbe Identität desselben Users.
     *
     * @throws ExternalIdentityException LINK_CONFLICT (gehört einem anderen User), INACTIVE
     */
    public function attach(User $user, string $provider, string $externalUserId, ?string $email, ?string $displayName): ExternalIdentity
    {
        if (!\array_key_exists($provider, self::PROVIDERS)) {
            throw new ExternalIdentityException(ExternalIdentityException::UNSUPPORTED_PROVIDER, 'Provider is not supported');
        }
        if ($user->getState() !== 'active') {
            throw new ExternalIdentityException(ExternalIdentityException::INACTIVE, 'Account is not active');
        }

        $existing = $this->identities->findOneByProviderAndExternalUserId($provider, $externalUserId);
        if ($existing instanceof ExternalIdentity) {
            if ($existing->getUser()->getId() !== $user->getId()) {
                // Nie übernehmen oder verschieben; ohne Details zum anderen Konto.
                throw new ExternalIdentityException(ExternalIdentityException::LINK_CONFLICT, 'External account is already linked to another user');
            }
            $existing->setEmail($email);
            $existing->setDisplayName($displayName ?? $existing->getDisplayName());
            $existing->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            return $existing;
        }

        $identity = new ExternalIdentity();
        $identity->setId(IdGenerator::generateUnique($this->entityManager, ExternalIdentity::class));
        $identity->setUser($user);
        $identity->setProvider($provider);
        $identity->setExternalUserId($externalUserId);
        $identity->setEmail($email);
        $identity->setDisplayName($displayName);
        $user->addExternalIdentity($identity);
        if ($provider === 'google' && $user->getGoogleId() === null) {
            $user->setGoogleId($externalUserId);
        }
        $this->entityManager->persist($identity);
        $this->auditLogger->log('user', $user->getId(), 'external_identity_linked', $user, $user, null, [
            'provider' => ['old' => null, 'new' => $provider],
        ]);
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new ExternalIdentityException(ExternalIdentityException::LINK_CONFLICT, 'External account is already linked to another user');
        }

        return $identity;
    }
}
