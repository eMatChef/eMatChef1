<?php

declare(strict_types=1);

namespace App\Service\Grossanlass;

/** Abruf einer externen Quelle fehlgeschlagen. Die Meldung ist bewusst frei von Zugangsdaten und anzeigbar. */
final class GrossanlassExternalFetchException extends \DomainException
{
    public function __construct(string $message, public readonly ?int $httpStatus = null)
    {
        parent::__construct($message);
    }
}
