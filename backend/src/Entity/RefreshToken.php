<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\Index(name: 'idx_refresh_tokens_session', columns: ['session_id'])]
class RefreshToken extends BaseRefreshToken
{
    /**
     * Logische Sitzung; bleibt bei single_use-Rotation gleich. NULL nur für Tokens aus der Zeit vor UserSession.
     */
    #[ORM\ManyToOne(targetEntity: UserSession::class)]
    #[ORM\JoinColumn(name: 'session_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?UserSession $session = null;

    public function getSession(): ?UserSession
    {
        return $this->session;
    }

    public function setSession(?UserSession $session): self
    {
        $this->session = $session;

        return $this;
    }
}
