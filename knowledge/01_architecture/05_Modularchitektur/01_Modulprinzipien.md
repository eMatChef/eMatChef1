# 01 Modulprinzipien

## Schichtung

`IMPLEMENTIERT` / `ENTSCHIEDEN` (`AGENTS.md`):

| Schicht | Backend | Frontend |
| --- | --- | --- |
| Transport | `Controller/*` (HTTP, Request/Response) | `api/*.ts` über `apiClient` |
| Fachlogik | `Service/<Bereich>/` | `composables/use*.ts`, `stores/*` |
| Persistenz | `Entity/`, `Repository/` (Doctrine) | – |
| Darstellung | – | `views/`, `components/` |

Controller sollen nur HTTP-Belange behandeln; die Realität ist gemischt (`CategoryController` fragt Doctrine direkt per `QueryBuilder`). `ENTSCHIEDEN`: neue Logik in den passenden bestehenden Service, bestehende Controller nicht ohne Auftrag umbauen.

## Zentral vs. bereichsspezifisch

- **Zentral**: von mehreren Bereichen (Department, Grossanlass, Lieferant, Profil) genutzt, kennt keinen Bereich als Voraussetzung. Beispiele: `AuditLogger`, `AppMailer`, `Service/Media/`, `Service/Display/`, `BusinessClock`, `DepartmentAccessGuard`.
- **Bereichsspezifisch**: gehört einem Bereich und liegt in dessen Ordner/Präfix: `Service/Grossanlass/`, `Service/Accounting/`, `Service/Workshop/`, Frontend `components/grossanlass/`, `views/grossanlass/`, Präfix `Grossanlass*`.
- Namenskonvention GA (`AGENTS.md`, `docs/DEVELOPMENT.md`): `Grossanlass*`-Komponenten, `useGrossanlass*`-Composables (view-lokal `useGa*`), Typen `Ga*`, i18n `grossanlass.*`.

## Regeln gegen Doppelimplementierung

1. `ENTSCHIEDEN` Vor neuem Code im betroffenen Verzeichnis nach bestehendem Muster suchen (Controller, Service, Entity, Repository, `frontend/src/api`, Store, Composable, Komponente) – `AGENTS.md`.
2. `ENTSCHIEDEN` Ein HTTP-Client: `apiClient`; kein `fetch`, kein zweites `axios.create()` (siehe [06](../06_API-Architektur.md)).
3. `ENTSCHIEDEN` Ein Auth-Mechanismus: Cookies + Sitzung; keine Tokens in `localStorage`/`sessionStorage`.
4. `ENTSCHIEDEN` Keine API-Platform-Ressourcen neben den Controllern.
5. `IMPLEMENTIERT` Ein Infoscreen-System für Department **und** Grossanlass (`DepartmentDisplayScreen`; keine GA-eigene Variante, `docs/devices/infoscreen.md`).
6. `IMPLEMENTIERT` Eine Tabelle für Admin-MFA/Step-up-Regeln (`Service/Auth/AdminMfaPolicy.php`), durchgesetzt zentral in `EventSubscriber/AdminMfaGuardSubscriber.php`, nicht in Controllern.
7. `IMPLEMENTIERT` Ein Audit-Ziel: `AuditEvent` über `Service/AuditLogger.php`.
8. `IMPLEMENTIERT` Ein Ort für Rollenvergabe-Regeln: `Service/MembershipRoleCatalog.php`; Frontend-Spiegel `frontend/src/utils/departmentMemberRoles.ts`, `stores/permissions.ts` (UI-Sichtbarkeit, keine Autorisierung).
9. `IMPLEMENTIERT` Wiederverwendbare Frontend-Bausteine statt Eigenbau pro View: `components/form/base/E*.vue` (siehe `components/form/base/README.md`), `components/layout/PageShell.vue`, `EEmptyState`, `ELoadingState`, `EResponsiveDataList`; globale Dialoge/Toasts über Stores (`stores/confirm.ts`, `prompt.ts`, `toast.ts`, `stepUp.ts`). Katalog (teils veraltet, Status gegen Code prüfen): `docs/wiederverwendbare-komponenten.md`.
10. `ENTSCHIEDEN` Vuetify/projekteigenes CSS bleibt führend; Tailwind nicht als Ersatz (`AGENTS.md`).

## Erweiterungspunkte (Muster)

| Muster | Ort | Zweck |
| --- | --- | --- |
| Interface + Registry | `Service/Demo/Scenario/DemoScenarioInterface.php`, `DemoScenarioRegistry.php` | Neues Demo-Szenario |
| Registry/Katalog | `Service/Display/Layout/DisplayWidgetRegistry.php`, `DisplayBlockCatalog.php`, `DisplayLayoutTemplateCatalog.php`, `DisplayLayoutValidator.php` | Neue Infoscreen-Widgets/Blöcke |
| Interface | `Service/Clock/ClockOriginResolverInterface.php` (Implementierungen: `Service/Grossanlass/GrossanlassClockOriginResolver.php`, `Service/Demo/Scenario/ScenarioClockOriginResolver.php`) | Zeitursprung pro Department |
| Policy-Tabelle | `Service/Auth/AdminMfaPolicy.php` | Neue geschützte Admin-/Step-up-Route |
| Katalog | `Service/MembershipRoleCatalog.php`, `Service/Grossanlass/GrossanlassAccessRoles.php`, `Service/Auth/MiDataSupportedRoleCatalog.php` | Rollen/Rechte |
| Symfony-Voter | `Security/Voter/SupplierCompanyVoter.php` | Objektbezogene Rechte (bisher nur Lieferanten) |
| Event Subscriber | `EventSubscriber/` | Querschnitt (Header, Rate-Limit, JWT, GA-Gates) |
| Konsolen-Command | `Command/` | Periodische/administrative Aufgaben |
| Frontend-Route-Meta | `requiresAuth`, `requiredRoles`, `requireDepartmentRoles`, `denyDepartmentRoles`, `requiresGrossanlassDepartment`, `globalProfile` (`router/index.ts`) | UI-Zugriff (nur UX) |

Services werden per Autowiring geladen (`backend/config/services.yaml`, `resource: '../src/'`); ein neuer Service braucht keine Registrierung.
