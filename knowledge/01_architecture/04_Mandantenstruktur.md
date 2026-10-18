# 04 Mandantenstruktur

## Hierarchie

```text
Organisation
  └─ Department (Baum: Department.parent / children)
       ├─ normales Department   (isGrossanlass = false)
       └─ Grossanlass-Department (isGrossanlass = true)  ← ein Grossanlass IST ein Department
            └─ Group (Baum), Membership (Rolle je Department)
```

- `IMPLEMENTIERT` `Department.organisation` ist Pflicht (`Entity/Department.php`); `Department.parent` ist optional und bildet den Baum innerhalb einer Organisation.
- `IMPLEMENTIERT` Der Departmenttyp ist nur das Flag `Department.isGrossanlass`; es gibt keine eigene GA-Mandantentabelle. GA-Zusatzdaten hängen per `department_id` an Department-/Activity-Entities (`DepartmentGrossanlass*`, `ActivityGrossanlass*`).
- `IMPLEMENTIERT` Frontend leitet den Typ aus der Mitgliedschaft ab (`authStore.isDepartmentGrossanlass`, `frontend/src/stores/auth.ts`); Routen unter `ga/` tragen `requiresGrossanlassDepartment` (`frontend/src/router/index.ts`). Backend prüft GA-Zugehörigkeit u. a. in `GrossanlassAccessService::assertGrossanlassDepartment` (`backend/src/Service/Grossanlass/GrossanlassAccessService.php`).

## Zuordnung von Benutzern

| Beziehung | Entität | Hinweis |
| --- | --- | --- |
| Konto ↔ Department | `Membership` (Rolle, `isPrimary`, Benachrichtigungs-Mail) | Ein User kann mehrere Memberships haben; höchstens eine primäre, durchgesetzt in `Service/PrimaryDepartmentService.php`; der dort genannte Unique-Index `uniq_membership_one_primary_per_user` ist in `backend/migrations/` nicht auffindbar (`UNKLAR`) |
| Konto ↔ Gruppe | `GroupMembership` (`leader` / `member`) | Gruppen gehören einem Department |
| Konto ↔ Lieferfirma | `SupplierMembership` | Eigenes Rollenmodell |
| Verwaltung ↔ Scope | `Profile.adminCapabilities` (Organisationen und/oder Department-Wurzeln samt Unterbaum) | Keine Mitgliedschaft nötig; `Service/Admin/AdminCapabilityChecker.php`, `AdminCapabilityDepartmentScope.php` |
| Aktives Department | `User.lastUsedDepartment`, Frontend `activeDepartmentId` | UI-Präferenz in `localStorage`, keine Berechtigung |

Verwaltungskontexte (global, Organisation, Department-Verwaltung) liefert `Service/Auth/AdminContextResolver.php` als `admin_contexts` in Login-/Session-Antwort; sie steuern nur die Navigation.

## Datentrennung

`IMPLEMENTIERT` Datentrennung erfolgt über die Spalte `department_id` an fachlichen Entities (z. B. `Activity`, `Group`, `MaterialItem`, `Category`, `Membership`; Index `idx_activity_department`) und explizite Zugriffsprüfungen im Code.

`IMPLEMENTIERT` Es gibt **keinen** automatischen Mandantenfilter: keine Doctrine-SQL-Filter und keine globale Query-Erweiterung (`backend/config/packages/doctrine.yaml` enthält nur DBAL/ORM-Mapping). Jede Abfrage muss `department_id` selbst setzen, jeder Controller muss die Prüfung selbst aufrufen.

Zugriffsprüfungen (Details: [07](./07_Sicherheit-und-Berechtigungen.md)):

| Mechanismus | Datei | Verwendung |
| --- | --- | --- |
| `DepartmentAccessGuard` (`canAccess`, `canManage`, `deny`, `denyManage`) | `Service/Security/DepartmentAccessGuard.php` | Direkt in `CategoryController`, `DepartmentController`, `DepartmentSettingController`, `TemplateController` |
| Direkte `Membership`-Abfrage im Controller/Service | z. B. `Service/ActivityAccessService.php`, `Service/GroupAccessService.php` | ca. 28 Controller referenzieren `Membership` direkt |
| `GrossanlassAccessService` und `GrossanlassAccessRoles` | `Service/Grossanlass/` | GA-Rollenmatrix (Planung, Staffing, Beschaffung, Postfach …) |
| `AdminCapabilityChecker::canAdministerDepartment` | `Service/Admin/AdminCapabilityChecker.php` | Verwaltungszuständigkeit im Scope-Baum |
| `SupplierCompanyVoter` | `Security/Voter/SupplierCompanyVoter.php` | Lieferantenportal (`#[IsGranted(SupplierCompanyVoter::ACCESS, subject: 'companyId')]`) |

`UNKLAR` Ob alle ~120 Controller jeweils eine passende Department-Prüfung enthalten, wurde nicht geprüft (kein Repository-Audit in dieser Phase). Das Muster ist nicht an einer Stelle erzwungen; bei neuen Endpunkten das Muster im Nachbar-Controller des gleichen Bereichs übernehmen.

## Bereichsübergreifende Daten

- `Address` hat Scope `department`, `supplier` oder `global` (`Entity/Address.php`, `Service/Bootstrap/AddressScopeDefaults.php`).
- Zentrale Inhalte ohne Department (`department_id IS NULL`), z. B. zentrale Material-Vorlagen und Druckkatalog, gehören laut `docs/authentication-security.md` dem Superadmin (nicht im Detail gegen Code geprüft: `UNKLAR`).
- Grossanlass-Gäste: Host-Department teilt Daten gezielt mit Gast-Departments (`DepartmentGrossanlassGuestShare`, `GrossanlassGaesteGuestController`); `Activity.invited_departments` (JSON) lädt weitere Departments ein.
- Benutzersuche über Departmentgrenzen: `Service/Security/UserPickerScope.php`, `Service/OrganisationUserPickerFilter.php`; System-Organisationen werden über `Service/SystemScopeVisibility.php` ausgeblendet.
- Öffentliche, mandantenlose Endpunkte: `/api/public/*` (`Controller/Public/`), `/api/health`, QR-Auflösung via `PublicCode`.

## Reset und Demo

`IMPLEMENTIERT` `Department.demoMode`, `demoScenarioKey`, `demoClockOffsetSeconds` markieren Demo-Departments; Reset-Logik in `Service/DepartmentResetService.php` und `Service/Demo/Reset/`. Zeit pro Department über `Service/Clock/BusinessClock.php`.

## Offen

- `UNKLAR` Vollständigkeit der Department-Prüfung über alle Controller (siehe oben).
- `UNKLAR` Verhalten der Isolation bei Querverweisen (z. B. ID eines fremden Departments im Request-Body); nicht stichprobenartig getestet.
