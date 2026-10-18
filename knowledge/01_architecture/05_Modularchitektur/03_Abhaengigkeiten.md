# 03 Abhängigkeiten

## Soll-Richtung

`ENTSCHIEDEN` (`AGENTS.md`, `docs/devices/infoscreen.md`): Bereiche nutzen zentrale Module; zentrale Module sollen keinen Bereich voraussetzen.

```text
Controller ──► Service (bereichsspezifisch) ──► zentrale Services (Audit, Mail, Inbox, Media, Clock, Display, Zugriff)
                                          └──► Entity / Repository ──► PostgreSQL
Frontend: View ──► Composable/Store ──► api/*.ts ──► apiClient ──► /api
```

## Ist-Beobachtungen (Quellcode-Stichprobe)

| Beobachtung | Beleg |
| --- | --- |
| Grossanlass nutzt zentrale Module breit | `GrossanlassHelperService`, `GrossanlassDepartmentCreateService` → `AuditLogger`; ≈18 GA-Services → `BusinessClock`; GA-Services → `InboxMessageService`, `MediaStorageService`, `PublicCodeService` |
| Foto-/Dateispeicherung zentral, Kontext-Services dünn | `WorkshopPhotoStorageService`, `MaterialPhotoStorageService`, `IssuePhotoStorageService`, `Grossanlass*StorageService` → `MediaStorageService` |
| Ein Infoscreen-System für beide Departmenttypen | `DisplayLiveDataService`, `DepartmentController` nutzen GA-Services; GA hat keine eigene Display-Entity |
| **Rückrichtung zentral → GA vorhanden** | Dateien ausserhalb von `Service/Grossanlass/` und `Controller/Grossanlass*`, die GA-Services importieren: `Controller/DepartmentController.php`, `ProfileController.php`, `UserController.php`, `Controller/Public/PublicLookupController.php`, `Service/Auth/SessionContextResolver.php`, `Service/Media/MediaFileAccessService.php`, `Service/Display/DisplayLiveDataService.php`, `EventSubscriber/GrossanlassSetupGateSubscriber.php`, `GrossanlassAvailabilityConflictSubscriber.php`, `Service/Demo/*`, `Service/Bootstrap/*` |
| Department-Anlage erzeugt Buchhaltungs-Defaults | `GrossanlassDepartmentCreateService`, `MiDataDepartmentStructureProvisioner`, `DemoOrganisationSeeder` → `Service/Accounting/` |
| Auth ist Basis für alles | `AdminMfaGuardSubscriber` (kernel.request) wirkt auf alle `/api`-Routen; `JwtSessionSubscriber` prüft jede JWT-Anfrage gegen `UserSession` |
| Demo/Bootstrap greifen quer auf Fachservices zu | `Service/Demo/Scenario/Grossanlass*Scenario.php`, `Service/Bootstrap/DemoGrossanlass*.php` |

`IMPLEMENTIERT` Die Kopplung zentral → GA ist real (z. B. GA-Setup-Gate als globaler Subscriber, GA-Sicht in `MediaFileAccessService`). Bewertung oder Auflösung ist nicht Teil dieser Dokumentation.

## Frontend-Abhängigkeiten

- Alle Views/Stores → `api/*.ts` → `apiClient`. `apiClient` ruft Callbacks, die `main.ts` registriert (`setSessionExpiredHandler`, `setAdminMfaHandlers`, `setApiSuccessRefreshCallback`), statt Stores direkt zu importieren (`frontend/src/api/apiClient.ts`, `frontend/src/main.ts`).
- Router → `stores/auth.ts`, `stores/permissions.ts`; Guards lesen Route-Meta.
- GA-Frontend nutzt zentrale UI-Basis (`components/form/base`, `layout`) und eigene `components/grossanlass/`, `views/grossanlass/`, `api/grossanlass*.ts`, `composables/useGrossanlass*.ts`.

## Verhinderung von Doppelimplementierung (Zusammenfassung)

Siehe [01_Modulprinzipien.md](./01_Modulprinzipien.md) Regeln 1–10. Technisch erzwungen sind davon nur: Autowiring-Konvention, Route-Guards im Router, zentrale Admin-MFA-Tabelle, ESLint/PHPStan/Typecheck in CI. `IMPLEMENTIERT` `frontend/eslint.config.js` enthält keine Regel gegen `fetch`/`axios.create`; die `apiClient`-Pflicht ist reine Konvention.

## Offen

- `UNKLAR` Zyklen innerhalb von `Service/` (kein Abhängigkeitsgraph erzeugt).
