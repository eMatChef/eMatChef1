<?php

declare(strict_types=1);

namespace App\Service\Auth;

/** Fehler der MFA-Challenge; reason ist ein stabiler API-Code. */
final class MfaException extends \DomainException
{
    /** Unbekannt, abgelaufen, verbraucht oder zu oft falsch: nicht unterscheidbar (keine Enumeration). */
    public const INVALID_CHALLENGE = 'invalid_challenge';
    public const INVALID_CODE = 'invalid_code';
    public const LOCKED = 'locked';
    public const INACTIVE = 'inactive';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
