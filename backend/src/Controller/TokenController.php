<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Route für /api/token/refresh – die eigentliche Verarbeitung erfolgt durch
 * den refresh_jwt Authenticator in der Security-Firewall.
 * Diese Route stellt sicher, dass Symfony die URL findet (verhindert 404).
 */
#[Route('/api/token', name: 'api_token_')]
class TokenController extends AbstractController
{
    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(): never
    {
        throw new \LogicException('This method should be intercepted by the refresh_jwt authenticator.');
    }
}
