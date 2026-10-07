<?php

declare(strict_types=1);

namespace App\Service\Auth;

final class MiDataOAuthException extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $reason, 0, $previous);
    }
}
