# 07 Sicherheit und Berechtigungen

Übersicht auf Architekturebene. Detailregeln zu MFA, Step-up, Trusted Devices, OAuth/MiData: `docs/authentication-security.md` (nicht vollständig neu verifiziert; unten nur Punkte mit Code-Beleg als `IMPLEMENTIERT`).

## Grundsatz

`ENTSCHIEDEN` (`AGENTS.md`): Frontend-Guards und UI-Sichtbarkeit sind keine Autorisierungsgrenze. Sicherheitsrelevante Operationen autorisiert das Backend. Keine Token in `localStorage`/`sessionStorage`; Auth-Änderungen nur auf Basis der bestehenden Architektur.

## Authentifizierung

| Aspekt | Stand | Beleg |
| --- | --- | --- |
| Login | `POST /api/auth/login_check` (JSON `email`/`password`), Throttling 5 / 15 min | `backend/config/packages/security.yaml` (Firewall `login`) |
| Token | JWT (RS-Schlüsselpaar, TTL 3600 s) im HttpOnly-Cookie `BEARER`, SameSite Lax, Domain `AUTH_COOKIE_DOMAIN`, `secure` per Env | `lexik_jwt_authentication.yaml` |
| Refresh | Gesdinet-Refresh-Token, 30 Tage, `single_use`, HttpOnly-Cookie; `POST /api/token/refresh` | `gesdinet_jwt_refresh_token.yaml` |
| Sitzung | serverseitige `UserSession`; Claim `sid` im JWT; Prüfung bei jedem JWT-Request, Widerruf wirkt sofort | `EventSubscriber/JwtSessionSubscriber.php`, `Service/Auth/UserSessionManager.php`, `Entity/UserSession.php` |
| Kontostatus | nur `state = active` darf Login, Refresh, API | `Security/UserChecker.php` |
| Passwort | Symfony-Hasher `auto` | `security.yaml` |
| MFA | TOTP (`spomky-labs/otphp`), Recovery Codes, Challenge beim Login, Trusted Device | `Service/Auth/TotpService.php`, `MfaChallengeService.php`, `TrustedDeviceService.php`, `Controller/MfaController.php` |
| OAuth-Login | Google, MiData (Hitobito/OIDC); eigene Firewalls mit `security: false`, Zustand in signiertem State-Cookie | `security.yaml`, `Controller/GoogleOAuthController.php`, `MiDataOAuthController.php`, `Service/Auth/OAuthCallbackSessionResolver.php` |
| Bot-Schutz | Cloudflare Turnstile bei Registrierung/Join (`TURNSTILE_SKIP_VERIFY` nur lokal) | `Service/TurnstileVerifier.php` |

`IMPLEMENTIERT` Die Firewall `/api/auth/microsoft` bedient nur den Outlook-Callback des Grossanlass-Postfachs (`Controller/GrossanlassOutlookOAuthCallbackController.php`, Route `/api/auth/microsoft/outlook/callback`); ein Microsoft-**Login** wurde im Code nicht gefunden (`docs/ARCHITECTURE.md` nennt ihn, Widerspruch: Code gilt). Analog dient Gmail dem GA-Postfach (`GrossanlassGmailOAuthCallbackController`).

Frontend-Seite: Sitzung über `authStore.loadUserSessionFromCookie()` (`GET /api/auth/session`), Router lädt sie bei `requiresAuth`. `frontend/src/utils/authStorage.ts` entfernt Altlasten (`auth_token`, `refresh_token` …) aus `localStorage`; erlaubt bleiben UI-Präferenzen (`active_department_id`, `active_context`, `session_last_activity_at`).

## Rollenmodell

Drei getrennte Ebenen:

| Ebene | Speicher | Zweck |
| --- | --- | --- |
| Globale Rollen | `Profile.roles` → `User::getRoles()` (`ROLE_USER`, `ROLE_SUPERADMIN`, `ROLE_ORGANISATIONSCHEF`, `ROLE_SUBORGCHEF`, `ROLE_WEBADMIN`; `ROLE_SUPPLIER` bei aktiver Lieferanten-Mitgliedschaft) | Systemweite Verwaltung |
| Verwaltungs-Scope | `Profile.adminCapabilities` (Organisationen / Department-Wurzeln) | Wo ein Org-/Sub-Chef verwalten darf |
| Operative Department-Rolle | `Membership.role` (`DepartmentRole`) | Rechte **innerhalb** eines Departments |
| Gruppenrolle | `GroupMembership.role` (`leader`/`member`) | Gruppenchef |
| Lieferantenrolle | `SupplierMembership` | Lieferantenportal |

- `IMPLEMENTIERT` `User::getRoles()` enthält bewusst **keine** Rollen aus Department-Mitgliedschaften (Kommentar in `Entity/User.php`); operative Rechte gelten nur je Department über `Membership::getRole()`.
- `IMPLEMENTIERT` Rollenhierarchie und Zuweisungsregeln: `Enum/DepartmentRole.php`, `Service/RoleHierarchyService.php`, `Service/MembershipRoleCatalog.php` (getrennt für Grossanlass), GA-Matrix `Service/Grossanlass/GrossanlassAccessRoles.php`.
- `IMPLEMENTIERT` Superadmin/Org-/Suborgchef erhalten durch ihre globale Rolle keine operative Department-Rolle; Verwaltung im Scope-Baum prüft `Service/Admin/AdminCapabilityChecker.php` (`canAdministerDepartment`, `canAdministerOrganisation`).

## Berechtigungsprüfung im Backend

Mehrere nebeneinander bestehende Mechanismen (kein einheitliches Policy-Framework):

1. **Firewall/`access_control`**: alles unter `/api` und `/media` erfordert `ROLE_USER`, ausser explizit öffentliche Pfade (`security.yaml`).
2. **`#[IsGranted]`** an Controller-Methoden: vorwiegend `ROLE_USER` (727), `ROLE_SUPERADMIN` (8), `SupplierCompanyVoter::ACCESS` (36).
3. **Department-Zugriff**: `DepartmentAccessGuard` (Lesen = Mitglied oder Verwaltungszuständig; Schreiben = zusätzlich Rolle ausserhalb `u`, `user`, `h`, `l1`–`l3`), siehe [04](./04_Mandantenstruktur.md).
4. **Fachspezifische Services**: `ActivityAccessService`, `GroupAccessService`, `GrossanlassAccessService`, `MediaFileAccessService`.
5. **Zentrale Admin-Regeltabelle**: `Service/Auth/AdminMfaPolicy.php` mit Stufen `admin`, `step_up`, `self_step_up`, `self_sensitive` (z. B. `/api/organisations` Schreibzugriff, `/api/mail/*`, `/api/admin/*`, `/api/users/{id}/admin`, Sitzungsbeendigung, OAuth-Verknüpfung). Durchgesetzt für globale Admins in `EventSubscriber/AdminMfaGuardSubscriber.php` (kernel.request) mit Antworten `mfa_setup_required`, `mfa_required`, `step_up_required` (403). Step-up: `POST /api/auth/step-up`, `Service/Auth/StepUpService.php` (Frische 600 s).
6. **GA-Gates**: `EventSubscriber/GrossanlassSetupGateSubscriber.php`, `GrossanlassAvailabilityConflictSubscriber.php`.

Weitere Schutzmassnahmen im Code: `SecurityHeadersSubscriber` (CSP etc.), `ApiAuthRateLimitSubscriber`, `AuditLogger` + `AuditEvent` (Sicherheitsprotokoll, `SecurityActivityService`), `SecurityMonitoringAdminController` / `SecurityAlertEvent`, `SecurityMetricsSubscriber`.

## Frontend-Berechtigungen (nur UX)

- Router-Meta `requiresAuth`, `requiredRoles`, `requireDepartmentRoles`, `denyDepartmentRoles`, `requiresGrossanlassDepartment`, `globalProfile`; Auswertung im globalen `router.beforeEach` (`frontend/src/router/index.ts`, ab Zeile ~2543).
- Verwaltungskontexte (Department, global, Organisation, Department-Verwaltung) im `authStore`; Auswahl in `localStorage` (`active_context`), **keine** Berechtigung.
- `frontend/src/stores/permissions.ts` hält Sichtbarkeits-Defaults mit Rollennamen (`user`, `editor`, `manager`, `owner`, `admin`), die nicht den `DepartmentRole`-Werten entsprechen (`UNKLAR`, ob und wo sie aktiv genutzt werden; nicht geprüft).
- `IMPLEMENTIERT` Spiegel der Backend-Regeln im Frontend: `utils/departmentMemberRoles.ts`, `utils/grossanlassAccess.ts`, `composables/useDepartmentSettingsManagerAccess.ts`. Backend bleibt massgebend.

## Öffentliche und geräteseitige Flächen

- `IMPLEMENTIERT` Öffentliche API: `/api/public/*` ohne Login (`Controller/Public/`).
- `IMPLEMENTIERT` Infoscreens: separates Gerätemodell mit Pairing und eigenem Cookie (`Entity/DepartmentDisplayDevice.php`, `Service/Display/DepartmentDisplaySessionService.php`, `DisplayRateLimiter.php`, `Controller/PublicDisplay*Controller.php`); Details `docs/devices/infoscreen.md`.
- `IMPLEMENTIERT` QR-Auflösung über `PublicCode`; Routen `/i/…` im Frontend.

## Offen

- `UNKLAR` Lückenlosigkeit der Department-Prüfung pro Endpunkt (siehe [04](./04_Mandantenstruktur.md)).
- `UNKLAR` Produktive Werte (`TRUSTED_PROXIES`, `AUTH_COOKIE_SECURE`, CORS-Regex) liegen ausserhalb des Repos (`deploy/`, Server-`.env`) und wurden nicht geprüft.
