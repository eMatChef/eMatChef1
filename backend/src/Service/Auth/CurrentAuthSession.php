<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\UserSession;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Request-bezogener Halter:
 * - issued: Sitzung, für die in diesem Request JWT/Refresh-Token ausgestellt werden (Login, Refresh);
 * - authenticated: per JWT-`sid` geprüfte Sitzung des aktuellen Requests.
 */
final class CurrentAuthSession implements ResetInterface
{
    private ?UserSession $issued = null;

    private ?UserSession $authenticated = null;

    public function getIssued(): ?UserSession
    {
        return $this->issued;
    }

    public function setIssued(UserSession $session): void
    {
        $this->issued = $session;
    }

    public function getAuthenticated(): ?UserSession
    {
        return $this->authenticated;
    }

    public function setAuthenticated(?UserSession $session): void
    {
        $this->authenticated = $session;
    }

    public function reset(): void
    {
        $this->issued = null;
        $this->authenticated = null;
    }
}
