<?php

declare(strict_types=1);

namespace App\Service\Clock;

use App\Entity\Department;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Liefert den Demo-Ausgangspunkt eines Demo-Departments (Ziel von «Zurücksetzen»).
 * Fachbereiche registrieren sich per Autoconfigure; ohne passenden Resolver wird auf reale Zeit zurückgesetzt.
 */
#[AutoconfigureTag(self::TAG)]
interface ClockOriginResolverInterface
{
    public const TAG = 'app.clock_origin_resolver';

    public function supports(Department $department): bool;

    public function originFor(Department $department): \DateTimeInterface;
}
