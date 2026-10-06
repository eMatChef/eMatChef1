<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Membership;
use App\Entity\User;
use App\Entity\UserEmailAlias;

/**
 * Effektive Benachrichtigungsadresse einer Membership (Department, Grossanlass ist ein Department).
 *
 * Standard ist die Primary-Adresse (Profile.email). Optional wählt der User eine eigene verifizierte
 * Adresse (Membership.notificationEmail). Ist sie nicht mehr gültig (Alias gelöscht, unbestätigt, gehört
 * nicht mehr dem User), gilt wieder die Primary. Beeinflusst weder Login noch Identität noch Rollen.
 */
final class MembershipNotificationEmailResolver
{
    public function __construct(
        private readonly UserEmailAliasService $emailAliases,
    ) {}

    /** Leer, wenn der User keine Primary-Adresse hat. */
    public function effectiveEmail(Membership $membership): string
    {
        $user = $membership->getUser();
        $primary = $this->primaryOf($user);
        $selected = $membership->getNotificationEmail();
        if ($selected !== null && $selected !== '' && $selected !== $primary && $this->emailAliases->userOwnsEmail($user, $selected)) {
            return $selected;
        }

        return $primary;
    }

    /**
     * Wählbare Adressen: Primary zuerst, dann verifizierte zusätzliche Adressen.
     *
     * @return list<string>
     */
    public function selectableEmails(User $user): array
    {
        $emails = [];
        $primary = $this->primaryOf($user);
        if ($primary !== '') {
            $emails[] = $primary;
        }
        foreach ($this->emailAliases->listForUser($user) as $alias) {
            if ($alias instanceof UserEmailAlias && $alias->isVerified()) {
                $emails[] = $alias->getEmail();
            }
        }

        return $emails;
    }

    /**
     * Setzt die Auswahl. null oder die Primary-Adresse = Standard (Primary folgen).
     *
     * @throws \InvalidArgumentException Adresse gehört nicht zu den verifizierten Adressen des Users
     */
    public function select(Membership $membership, ?string $email): void
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || $email === $this->primaryOf($membership->getUser())) {
            $membership->setNotificationEmail(null);

            return;
        }
        if (!$this->emailAliases->userOwnsEmail($membership->getUser(), $email)) {
            throw new \InvalidArgumentException('Nur eigene, bestätigte E-Mail-Adressen können gewählt werden.');
        }
        $membership->setNotificationEmail($email);
    }

    private function primaryOf(User $user): string
    {
        return strtolower(trim((string) ($user->getProfile()?->getEmail() ?? '')));
    }
}
