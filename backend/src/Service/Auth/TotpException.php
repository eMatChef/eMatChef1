<?php

declare(strict_types=1);

namespace App\Service\Auth;

/** Fachlicher TOTP-Fehler; reason ist ein stabiler Code für die API. */
final class TotpException extends \DomainException
{
    public const NOT_ACTIVE = 'totp_not_active';
    public const INVALID_CODE = 'invalid_code';
    public const CODE_REQUIRED = 'code_required';
    public const LOCKED = 'locked';
    public const REQUIRED_FOR_ADMIN = 'totp_required_for_admin';
    public const NO_PENDING = 'no_pending_enrollment';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
