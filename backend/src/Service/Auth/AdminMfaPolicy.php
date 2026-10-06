<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Symfony\Component\HttpFoundation\Request;

/**
 * Einzige Stelle, die festlegt, welche Endpoints geschützte Adminfunktionen sind.
 *
 * - LEVEL_ADMIN: für globale Security-Admins (superadmin/orgchef/suborgchef) nur mit aktivem TOTP
 *   und MFA-verifizierter Sitzung.
 * - LEVEL_STEP_UP: zusätzlich frische MFA-Bestätigung (StepUpService::FRESHNESS_SECONDS).
 *
 * Die Regeln gelten nur für globale Admins; alle anderen Benutzer und alle nicht aufgeführten Routen bleiben
 * unberührt. Rollen- und Scope-Prüfungen bleiben in den Controllern/AdminCapabilityChecker.
 * Die erste passende Regel gilt, Step-up-Regeln stehen deshalb vor den Admin-Regeln.
 */
final class AdminMfaPolicy
{
    public const LEVEL_ADMIN = 'admin';
    public const LEVEL_STEP_UP = 'step_up';

    /**
     * @var list<array{methods: list<string>|null, pattern: string, level: string, bodyKeys?: list<string>}>
     */
    private const RULES = [
        // Step-up: Admin-Rollen/-Scope, Konto-/Identitätsänderungen durch Admin (nur wenn sicherheitsrelevante Felder gesetzt)
        ['methods' => ['PATCH'], 'pattern' => '#^/api/users/[^/]+/admin$#', 'level' => self::LEVEL_STEP_UP,
            'bodyKeys' => ['global_admin_role', 'admin_capabilities', 'email', 'state']],
        // Step-up: Security-Verwaltung (Absender-/Mail-Transport, Integrationen, reservierter Pfad für Admin-2FA-Reset)
        ['methods' => ['PATCH', 'PUT', 'POST'], 'pattern' => '#^/api/mail/settings$#', 'level' => self::LEVEL_STEP_UP],
        ['methods' => ['PUT', 'POST', 'PATCH', 'DELETE'], 'pattern' => '#^/api/admin/integrations/#', 'level' => self::LEVEL_STEP_UP],
        ['methods' => null, 'pattern' => '#^/api/admin/security/#', 'level' => self::LEVEL_STEP_UP],

        // Adminfunktionen
        ['methods' => null, 'pattern' => '#^/api/admin/#', 'level' => self::LEVEL_ADMIN],
        ['methods' => null, 'pattern' => '#^/api/users/admin/#', 'level' => self::LEVEL_ADMIN],
        ['methods' => null, 'pattern' => '#^/api/users/[^/]+/admin(-detail)?$#', 'level' => self::LEVEL_ADMIN],
        ['methods' => ['POST', 'PATCH', 'PUT', 'DELETE'], 'pattern' => '#^/api/organisations(/|$)#', 'level' => self::LEVEL_ADMIN],
        ['methods' => null, 'pattern' => '#^/api/mail/#', 'level' => self::LEVEL_ADMIN],
        ['methods' => ['PUT'], 'pattern' => '#^/api/mail-templates/messages$#', 'level' => self::LEVEL_ADMIN],
        ['methods' => ['POST', 'PATCH', 'PUT', 'DELETE'], 'pattern' => '#^/api/global-addresses(/|$)#', 'level' => self::LEVEL_ADMIN],
        ['methods' => ['GET'], 'pattern' => '#^/api/join-requests/admin-request/(pending|history)$#', 'level' => self::LEVEL_ADMIN],
        ['methods' => ['PATCH'], 'pattern' => '#^/api/join-requests/admin-request/[^/]+(/assign)?$#', 'level' => self::LEVEL_ADMIN],
    ];

    /** @return self::LEVEL_*|null */
    public function levelFor(Request $request): ?string
    {
        $path = $request->getPathInfo();
        $method = strtoupper($request->getMethod());

        foreach (self::RULES as $rule) {
            if ($rule['methods'] !== null && !\in_array($method, $rule['methods'], true)) {
                continue;
            }
            if (preg_match($rule['pattern'], $path) !== 1) {
                continue;
            }
            if (isset($rule['bodyKeys']) && !$this->bodyHasAnyKey($request, $rule['bodyKeys'])) {
                // Regel nur für sicherheitsrelevante Änderungen; sonst gilt die nächste Regel (Admin-Level).
                continue;
            }

            return $rule['level'];
        }

        return null;
    }

    /**
     * @param list<string> $keys
     */
    private function bodyHasAnyKey(Request $request, array $keys): bool
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return false;
        }
        foreach ($keys as $key) {
            if (\array_key_exists($key, $data)) {
                return true;
            }
        }

        return false;
    }
}
