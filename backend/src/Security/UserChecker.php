<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Nur User mit state `active` dürfen authentifiziert arbeiten (Login, Refresh, jeder JWT-Request).
 *
 * Prüfung erst nach erfolgreicher Credential-Prüfung (checkPostAuth), damit der Login
 * ohne gültiges Passwort keinen Account-Status preisgibt.
 */
final class UserChecker implements UserCheckerInterface
{
    public const ACTIVE_STATE = 'active';

    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getState() !== self::ACTIVE_STATE) {
            throw new CustomUserMessageAccountStatusException('Dieses Konto ist nicht aktiv.');
        }
    }
}
