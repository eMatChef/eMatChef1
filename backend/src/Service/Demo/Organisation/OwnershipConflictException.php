<?php

declare(strict_types=1);

namespace App\Service\Demo\Organisation;

/** Ein vorhandener Datensatz gehört nicht nachweisbar dem Seed: nicht übernehmen, nicht verändern. */
final class OwnershipConflictException extends \RuntimeException
{
}
