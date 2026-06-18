<?php

declare(strict_types=1);

namespace App\Service\Workshop;

final class WorkshopTicketCompletionException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'completion_failed',
    ) {
        parent::__construct($message);
    }
}
