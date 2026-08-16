<?php

namespace App\Service\Onboarding;

use Symfony\Component\HttpFoundation\Request;

/**
 * Sichtbarkeit der Onboarding-Sandbox in API-Listen.
 */
final class OnboardingSandboxVisibility
{
    public const QUERY_PARAM = 'include_onboarding_sandbox';
    public const HEADER_NAME = 'X-Onboarding-Tour';

    public static function includeFromRequest(Request $request): bool
    {
        $q = $request->query->get(self::QUERY_PARAM);
        if ($q === '1' || $q === 1 || $q === true || $q === 'true') {
            return true;
        }

        $header = trim((string) $request->headers->get(self::HEADER_NAME, ''));

        return $header !== '';
    }

    /**
     * DQL-Fragment: Sandbox ausblenden, oder (bei include) fremde User-Sandbox-Aktivitäten ausblenden.
     *
     * @return array{0: string, 1: array<string, mixed>} [dqlAnd, params]
     */
    public static function activityListConstraint(
        string $alias,
        bool $include,
        ?string $currentUserId,
    ): array {
        if (!$include) {
            return [sprintf('(%s.onboardingSandbox = false)', $alias), []];
        }

        if ($currentUserId === null || $currentUserId === '') {
            return [sprintf('(%s.onboardingSandbox = false)', $alias), []];
        }

        return [
            sprintf(
                '(%1$s.onboardingSandbox = false OR %1$s.createdByUserId = :onboardingSandboxUserId)',
                $alias,
            ),
            ['onboardingSandboxUserId' => $currentUserId],
        ];
    }

    /**
     * Kit-Entities (Material/Adresse/Fahrzeug):
     * - ohne Tour: Sandbox ausblenden
     * - mit Tour (Include): nur Sandbox-Kit (keine Produktiv-Daten in der Suche)
     */
    public static function kitListConstraint(string $alias, bool $include): string
    {
        if ($include) {
            return sprintf('(%s.onboardingSandbox = true)', $alias);
        }

        return sprintf('(%s.onboardingSandbox = false)', $alias);
    }

    /**
     * Roh-SQL für material_item.onboarding_sandbox (Verfügbarkeits-API).
     */
    public static function kitSqlConstraint(string $alias, bool $include): string
    {
        if ($include) {
            return sprintf(' AND COALESCE(%s.onboarding_sandbox, false) = true', $alias);
        }

        return sprintf(' AND COALESCE(%s.onboarding_sandbox, false) = false', $alias);
    }
}
