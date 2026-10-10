<?php

declare(strict_types=1);

namespace App\Service\Auth;

final class ExternalIdentityException extends \RuntimeException
{
    public const NOT_FOUND = 'not_found';
    public const LAST_LOGIN_METHOD = 'last_login_method';
    public const LINK_CONFLICT = 'link_conflict';
    public const INACTIVE = 'inactive';
    public const UNSUPPORTED_PROVIDER = 'unsupported_provider';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
