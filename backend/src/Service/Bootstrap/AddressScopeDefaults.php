<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

/** Address-Scope-Konstanten (ersetzt GLOBAL000000-Hack seit Paket 0). */
final class AddressScopeDefaults
{
    public const SCOPE_DEPARTMENT = 'department';
    public const SCOPE_SUPPLIER = 'supplier';
    public const SCOPE_GLOBAL = 'global';

    /** Nur noch für Legacy-Seed-Import (subset.json vor Paket 15). */
    public const LEGACY_GLOBAL_SUPPLIER_DEPARTMENT_ID = 'GLOBAL000000';
}
