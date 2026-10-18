# 02 Modulübersicht

Nur Module, die im Code nachweisbar von mehreren Bereichen genutzt werden. «Nutzer» = per Suche nach Klassenverwendung gefundene Bereiche, keine vollständige Liste. Fachdetails später in `knowledge/02_core/`.

## Backend

| Modul | Pfad | Nutzer (Auszug) | Erweiterungspunkt |
| --- | --- | --- | --- |
| Department-Zugriff | `backend/src/Service/Security/DepartmentAccessGuard.php`, `Service/Admin/AdminCapabilityChecker.php`, `Service/MembershipRoleCatalog.php` | Department-Controller, Verwaltung | neue Rolle: Katalog; neuer Endpunkt: Guard aufrufen |
| Authentifizierung & Sitzungen | `backend/src/Service/Auth/`, `Security/`, `EventSubscriber/JwtSessionSubscriber.php` | alle `/api`-Aufrufe, Profil | Provider-Klassen (`GoogleOAuth*`, `HitobitoOAuthClient`), `AuthIntent` |
| Admin-MFA / Step-up | `Service/Auth/AdminMfaPolicy.php`, `AdminMfaGuard.php`, `StepUpService.php`, `EventSubscriber/AdminMfaGuardSubscriber.php` | Admin-Endpunkte | Eintrag in `AdminMfaPolicy` |
| Audit | `backend/src/Service/AuditLogger.php`, `Entity/AuditEvent.php` | Auth, Benutzer, Grossanlass (≈20 Dateien) | neue Aktion loggen; Anzeige-Allowlist in `Service/Auth/SecurityActivityService.php` |
| Mail | `backend/src/Service/Mail/` (`AppMailer`, `MailTransportResolver`, `MailTemplateContentStore`, `MailOutboundSettingsStore`) | Verifikation, J+S-Coach-Mail, Fundmeldungen, Admin | Vorlagen über `MailTemplateController` |
| Nachrichtenzentrale | `Service/InboxMessageService.php` (+ Traits), `Entity/InboxMessage.php` | Workshop, Einladungen, Direktnachrichten, Grossanlass (≈25 Dateien) | neue Nachrichtenart: `InboxMessageKindsTrait` |
| Medien/Dateien | `backend/src/Service/Media/` (`MediaStorageService`, `MediaCompressionService`, `MediaRetentionService`, `MediaFileAccessService`) | je ein Foto-/Dateiservice für Workshop, Material, Issue, Grossanlass (delegieren an `MediaStorageService`) | neuer Kontext: eigener `*StorageService`, der `MediaStorageService` nutzt |
| Öffentliche Codes / QR | `Service/Public/PublicCodeService.php`, `Service/Storage/StorageQrService.php`, `Service/Print/` | Material, Lager, Lieferanten-Import, Grossanlass, Display | Code-Typen in `PublicCode`; Frontend `/i/…`-Routen |
| Infoscreens | `backend/src/Service/Display/` (+ `Layout/`), `Entity/DepartmentDisplay*.php` | Department und Grossanlass; öffentliche Kiosk-Controller | `DisplayWidgetRegistry`, `DisplayBlockCatalog`, `DisplayLayoutTemplateCatalog` |
| Geschäftszeit | `Service/Clock/BusinessClock.php`, `ClockOriginResolverInterface` | Grossanlass-Services (≈18 Dateien), Demo | neuer Resolver |
| Buchhaltung | `backend/src/Service/Accounting/`, `Entity/Accounting*.php` | Department, Aktivitäten, Grossanlass-Anlage, Demo | per Department-Bootstrap (`AccountingCostCenterBootstrapService`) |
| Integrationen (Einstellungen) | `Service/Integration/IntegrationSettingsStore.php`, `Controller/IntegrationAdminController.php` | Admin (Step-up-pflichtig, siehe [07](../07_Sicherheit-und-Berechtigungen.md)) | – |
| Demo/Bootstrap | `Service/Demo/` (Scenario, Reset, Organisation), `Service/Bootstrap/`, `Command/Demo*` | Entwicklung, Testumgebung | `DemoScenarioInterface` + `DemoScenarioRegistry` |
| Lieferantenportal | `backend/src/Service/Supplier/`, `Security/Voter/SupplierCompanyVoter.php` | `Supplier*Controller` | Voter-Attribut `ACCESS` |

Bereichsspezifisch (nicht zentral): `Service/Grossanlass/` (88 Dateien), `Service/Activity/`, `Service/Workshop/`, `Service/Material/`, `Service/Onboarding/`, `Service/Support/`.

## Frontend

| Modul | Pfad | Zweck |
| --- | --- | --- |
| HTTP-Schicht | `frontend/src/api/apiClient.ts` + 120 Dateien in `frontend/src/api/` | einziger Axios-Client, Fachmodule pro Bereich ([06](../06_API-Architektur.md)) |
| Auth-Store | `frontend/src/stores/auth.ts`, `utils/adminContext.ts`, `utils/authStorage.ts` | Sitzung, aktives Department, Verwaltungskontexte |
| Berechtigungs-UI | `stores/permissions.ts`, `utils/departmentMemberRoles.ts`, `utils/grossanlassAccess.ts`, `composables/useDepartmentMemberRole.ts` | Sichtbarkeit/Navigation (keine Autorisierung) |
| Router & Guards | `frontend/src/router/index.ts`, `utils/departmentRoute.ts`, `utils/departmentSwitch.ts` | Route-Meta, Host-Weichen, Redirect-Bereinigung |
| UI-Basis | `components/form/base/` (`ETextField`, `ESelect`, `EDialog`, `EButton`, `ECard` …), `components/layout/` (`AppLayout`, `PageShell`, `EEmptyState` …) | einheitliche Formulare/Layouts |
| Globale Dienste | `components/common/Global*.vue`, `stores/toast.ts`, `confirm.ts`, `prompt.ts`, `stepUp.ts` | Toast, Bestätigung, Eingabe, Step-up-Dialog |
| i18n | `frontend/src/i18n.ts`, `frontend/src/locales/*.json` | Sprachen und Organisationsvarianten (`de-pfadi`, `de-cevi`, `de-jubla`, `fr-*`, `it-*`, `en`) |
| Scanner/QR | `components/common/BarcodeScannerPanel.vue`, `PublicQrTag.vue`, `utils/displayPairingLink.ts` | Barcode/QR |
| Infoscreen-Engine | `views/DepartmentDisplayView.vue`, `components/display/` | Kiosk und Vorschau für Department und GA |
| Kartenansicht | `components/MapView.vue`, `utils/geoSearch.ts` | Adress-/Kartensuche (nutzt `fetch` für externe Geodienste, siehe [06](../06_API-Architektur.md)) |

## Offen

- `UNKLAR` Vollständigkeit: Es wurden die Module aufgenommen, die bei der Architektursichtung auffielen; kleinere gemeinsame Helfer (`Util/`, `utils/`) sind nicht inventarisiert.
