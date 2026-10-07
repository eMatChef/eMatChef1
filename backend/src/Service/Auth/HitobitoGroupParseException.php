<?php

declare(strict_types=1);

namespace App\Service\Auth;

final class HitobitoGroupParseException extends \UnexpectedValueException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
