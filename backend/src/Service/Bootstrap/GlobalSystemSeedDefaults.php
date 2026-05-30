<?php

declare(strict_types=1);

namespace App\Service\Bootstrap;

/**
 * Feste IDs/Namen der globalen System-Organisation (Subset-Seed, Legacy-Bootstrap bis Paket 15).
 *
 * DEPARTMENT_ID / ORGANISATION_ID: Legacy — globale Lieferanten nutzen seit Paket 0 address.scope=global.
 */
final class GlobalSystemSeedDefaults
{
    public const ORGANISATION_ID = 'GLOBALORG001';
    public const ORGANISATION_NAME = 'Global System';
    public const DEPARTMENT_ID = 'GLOBAL000000';
    public const DEPARTMENT_NAME = 'Global Suppliers';

    public const ADDRESS_SCOPE_DEPARTMENT = 'department';
    public const ADDRESS_SCOPE_SUPPLIER = 'supplier';
    public const ADDRESS_SCOPE_GLOBAL = 'global';
}
