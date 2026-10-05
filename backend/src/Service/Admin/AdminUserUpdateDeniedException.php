<?php

declare(strict_types=1);

namespace App\Service\Admin;

/**
 * Admin-User-Update abgelehnt; trägt den HTTP-Status für die API-Antwort.
 */
final class AdminUserUpdateDeniedException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 403)
    {
        parent::__construct($message);
    }
}
