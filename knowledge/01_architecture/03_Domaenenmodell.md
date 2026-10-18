# 03 Domänenmodell

Systemebene, kein vollständiges Schema. Entities: `backend/src/Entity/`. Mapping per PHP-Attribute. Feldlisten und Workflows gehören in die Fachdoku (`knowledge/02_core` bis `04_grossanlass`, bis dahin `docs/`).

## Kernbeziehungen

```text
Organisation 1—* Department (Baum über Department.parent)
Department   1—* Membership *—1 User 1—1 Profile
Department   1—* Group (Baum über Group.parent) 1—* GroupMembership *—1 User
Department   1—* Category (Baum) 1—* MaterialItem 1—* MaterialBatch
Department   1—* Activity *—0..1 Group ; Activity 1—* ActivityItem *—1 MaterialItem
Department   1—* Accounting*   (CostCenter, BudgetLine, Booking, Rule, AcquisitionFollowUp)
Department   1—* WorkshopTicket, InboxMessage, DepartmentSetting, DepartmentVehicle …
Department   1—0..1 DepartmentGrossanlassConfig   (nur wenn isGrossanlass)
SupplierCompany 1—* SupplierMembership *—1 User ; SupplierCompany 0..1—1 Department (linkedDepartment)
```

## Zentrale Entitäten

| Entität | Rolle im System | Datei |
| --- | --- | --- |
| `Organisation` | Oberste Mandantenebene, hält Departments und erlaubte Sprachen | `Entity/Organisation.php` |
| `Department` | Hauptmandant für Daten; Baum; Flags `isGrossanlass`, `demoMode` | `Entity/Department.php` |
| `User` / `Profile` | Konto (Passwort, Status `state`) bzw. Personendaten, globale Rollen (`roles`), Verwaltungs-Scope (`adminCapabilities`) | `Entity/User.php`, `Entity/Profile.php` |
| `Membership` | User↔Department mit Rolle (`role`), `isPrimary`, `notificationEmail`, Flags `isJsCoach`, `isHelper` | `Entity/Membership.php` |
| `Group` / `GroupMembership` | Untergliederung eines Departments (Stufen; im GA «Ressorts» über `grossanlassKind`) | `Entity/Group.php`, `Entity/GroupMembership.php` |
| `MaterialItem` / `MaterialBatch` | Bestandsträger (pro Department) und konkrete Charge mit Lagerort | `Entity/MaterialItem.php`, `Entity/MaterialBatch.php` |
| `Activity` (+ `Activity*`) | Ausleihe/Anlass mit Materialpipeline (Pack, Retour, Transport, J+S-Order) | `Entity/Activity.php` |
| `Address` | Wiederverwendbare Adresse mit Scope `department` / `supplier` / `global` | `Entity/Address.php` |
| `InboxMessage` | Nachrichtenzentrale pro Department | `Entity/InboxMessage.php` |
| `SupplierCompany` | Lieferfirma ausserhalb des Department-Bestands, eigene Rollen (`SupplierMembership`) | `Entity/SupplierCompany.php` |
| `ExternalIdentity`, `ExternalStructureIdentity` | OAuth-Identität (Google/MiData) bzw. Zuordnung externer Gruppen zu Organisation/Department/Group | `Entity/ExternalIdentity.php`, `Entity/ExternalStructureIdentity.php` |
| `UserSession`, `RefreshToken`, `UserTotp`, `TrustedDevice`, `MfaChallenge` | Sitzungs- und MFA-Zustand | siehe [07](./07_Sicherheit-und-Berechtigungen.md) |
| `AuditEvent`, `SecurityAlertEvent` | Zentrales Audit-/Sicherheitsprotokoll | `Entity/AuditEvent.php` |
| `JoinRequest`, `AdminJoinRequest` | Beitritts- und Support-Anfragen | `Entity/JoinRequest.php`, `Entity/AdminJoinRequest.php` |
| `PublicCode` | Öffentliche Kurzcodes für QR-Auflösung (`/i/…`) | `Entity/PublicCode.php` |
| `DepartmentDisplayScreen/Device/Block/LayoutRevision` | Infoscreens (gilt für Department und GA) | `Entity/DepartmentDisplay*.php` |
| `DemoSeedRecord`, `OnboardingSandboxState` | Demo-/Sandbox-Zustand | `Entity/DemoSeedRecord.php` |

## Grossanlass-Entities

`IMPLEMENTIERT` 61 Entities `DepartmentGrossanlass*` und `ActivityGrossanlass*` hängen an Department bzw. Activity: Konfiguration, Budget, Beschaffung (`ActivityGrossanlassProcurement*`), Runden/Wünsche, Einsätze, Helfer, Touren, Packs, externe Quellen, Gmail-Konto. Gäste/Fremd-Departments: `DepartmentGrossanlassGuestShare` (Host- und Gast-Department). Details gehören nach `knowledge/04_grossanlass/`.

## Identifikatoren

`IMPLEMENTIERT` IDs sind überwiegend 12-stellige Strings (`CHARACTER(12)`), erzeugt in `backend/src/Util/IdGenerator.php` und beim `prePersist` via `backend/src/EventListener/IdGeneratorSubscriber.php`. Es gibt auch Präfix-IDs (`IdGenerator::generateWithPrefix`, `GrossanlassIdGenerator`) und feste System-IDs (z. B. `org_js000000` in `Service/SystemScopeVisibility.php`).

## Rollen im Modell

- `Membership.role` trägt operative Rollen (`mw`, `cmw`, `dc`, `bl`, `komm`, `spon`, `lw`, `clw`, `l1`–`l3`, `u`, GA-spezifisch `hv`, `subhv`, `h`) gemäss `backend/src/Enum/DepartmentRole.php`; welche Rollen in welchem Departmenttyp zulässig bzw. vergebbar sind, entscheidet `backend/src/Service/MembershipRoleCatalog.php` (`allowedFor`, `canAssign`, getrennt für normales Department und Grossanlass).
- Globale Rollen (`ROLE_SUPERADMIN`, `ROLE_ORGANISATIONSCHEF`, `ROLE_SUBORGCHEF`, …) stehen in `Profile.roles`, nicht in `Membership`.
- Lieferanten haben eigene Mitgliedschaft (`SupplierMembership`), nicht `DepartmentRole`.

Berechtigungslogik: [07](./07_Sicherheit-und-Berechtigungen.md).

## Offen

- `UNKLAR` Ob `Membership.role` in der Datenbank auf die Enum-Werte beschränkt ist (Spalte als String; Validierung liegt in `MembershipRoleCatalog`, nicht als DB-Constraint geprüft).
