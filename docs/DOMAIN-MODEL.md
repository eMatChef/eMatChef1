# Domänenmodell

Beziehungen, die in `backend/src/Entity/` stehen. Keine Zielbilder. Tiefere Regeln stehen in der verlinkten Fachdoku. Weicht die Fachdoku von diesem Ist ab, gilt nicht automatisch der Code: Soll-Text bleibt Soll, offensichtlich veraltete Sätze werden ersetzt, unklare Konflikte werden entschieden und danach hier oder in der Fachdatei festgehalten.

## Organisation und Zugang

```text
Organisation 1—* Department (Baum über Department.parent)
Department 1—* Membership *—1 User
Department 1—* Group (Baum über Group.parent) 1—* GroupMembership *—1 User
```

- `Membership.role` ist der Department-Rollenwert (`DepartmentRole`, Spalte kurz: `sa`, `org`, `sub`, `mw`, `cmw`, `dc`, `bl`, `komm`, `spon`, `lw`, `clw`, `l1`, `l2`, `l3`, `u`). Hierarchie und Symfony-Rollen: `backend/src/Enum/DepartmentRole.php`. Wer welche Rolle vergeben darf: `backend/src/Service/MembershipRoleCatalog.php`.
- **Globale, hierarchische und operative Rollen sind getrennt.** `sa`/`org`/`sub` stehen als `ROLE_SUPERADMIN`/`ROLE_ORGANISATIONSCHEF`/`ROLE_SUBORGCHEF` in `profile.roles`, nie als `Membership.role` (zulässig dort: `mw`, `cmw`, `dc`, `bl`, `komm`, `spon`, `lw`, `clw`, `l1`–`l3`, `u`). Eine Person hat ein Konto und kann je Department eine andere Mitgliedschaftsrolle haben. Superadmin, Orgchef und Suborgchef erhalten nie automatisch eine MW- oder andere operative Rolle; die Verwaltungszuständigkeit von Orgchef/Suborgchef (`profile.admin_capabilities.scope`: Organisationen und/oder Department-Wurzeln samt Unterbaum, vereinigt; ohne Zuweisung keine Verwaltungsrechte) bedeutet keine Mitgliedschaft und keine operative Rolle in diesem Baum. `AdminCapabilityChecker::canAdministerDepartment` ist die Prüfung der Verwaltungszuständigkeit je Department (Superadmin überall, Orgchef/Suborgchef nur im Scope; leerer Scope = unbeschränkt); Zugriffe auf Department-Daten kombinieren sie mit der Mitgliedschaftsrolle. Wählbare Kontexte (UserNav) liefert `AdminContextResolver` als `admin_contexts` in Login- und Session-Antwort: ein globaler Systemkontext für den Superadmin (kein Department in der Datenbank), je ein Organisations- bzw. Department-Verwaltungskontext pro ausdrücklicher Zuweisung für Orgchef/Suborgchef (kein Kontext ohne Zuweisung); die Auswahl steuert nur die Navigation, nie die Berechtigung.
- `Membership.isJsCoach` ist ein Flag, keine Rolle.
- `GroupMembership.role` ist nur `leader` (Gruppenchef) oder `member`.
- `User` ist die reale eMatChef-Person. `ExternalIdentity` ist die externe Login-Identität bei einem Provider (aktuell Google und MiData); `Membership` beschreibt Department-Zugehörigkeit und Rollen, `GroupMembership` die Gruppenzugehörigkeit.
- `ExternalStructureIdentity` verknüpft `(provider, external_group_id)` stabil mit genau einer eMatChef-Organisation, einem Department oder einer Group. Provider-Typ, Name und Parent-ID sind Metadaten; Namen sind kein Mapping-Schlüssel. Die Entity persistiert keine Provider-Tokens.
- `MiDataDepartmentOnboarding` ist ein befristetes Angebot an einen User, eine per MiData bestätigte PBS-Abteilung einzurichten. Es autorisiert nichts selbst; die Einrichtung prüft Rolle und Struktur erneut. Die PBS-Zuordnung lautet Bund → `Organisation` (nur administrativ gemappt), Kantonalverband, Region und Abteilung → `Department`; der technische Root wird nie zugeordnet.
- `User` hat `Profile`, optionales `lastUsedDepartment` und optionales `lastUsedSupplierCompany`. Ein User kann mehrere Department-Memberships haben.
- `UserSession` ist eine serverseitige Login-Sitzung eines Users (Claim `sid` im JWT); Gesdinet-`RefreshToken`s hängen über `session_id` daran. Widerrufene Sitzungen bleiben als Datensatz erhalten (`revoked_at`, `revoked_reason`).
- Primary-E-Mail ist `Profile.email`; weitere Adressen sind `UserEmailAlias` (`verified_at`, `login_enabled`, Bestätigungs-Token als sha256-Hash). Nur Primary und verifizierte, login-fähige Aliase dienen Login und Passwort-Reset (Code geht an die Primary); Adressen sind kontoübergreifend case-insensitive eindeutig, unbestätigte blockieren eine Adresse bis Linkablauf (2 Tage). Eine verifizierte Adresse kann Primary werden, die alte Primary bleibt als Alias. API: `/api/profiles/{id}/emails` (GET/POST, `/{aliasId}/resend`, PUT `/primary`, DELETE `/{aliasId}`); Bestätigung über `GET /api/auth/verify`. Provider-E-Mails (OAuth) werden nie automatisch zu Alias/Login-Adresse eines bestehenden Users.
- `TrustedDevice` ist das Login-MFA-Vertrauen eines Browsers für genau einen User (Credential-Hash, `trusted_at`, `expires_at`, `last_used_at`, `revoked_at`); `UserSession.mfa_source` (`totp`, `recovery_code`, `trusted_device`) und `UserSession.trusted_device_id` halten fest, wie eine Sitzung MFA-verifiziert wurde. Sitzungen und Trusted Devices haben getrennte Lebenszyklen.
- `UserTotp` ist der TOTP-Faktor eines Users (RFC 6238, SHA-1, 6 Stellen, 30 s, ±1 Schritt, Replay-Schutz über `last_used_step`; nach 5 Fehlversuchen 15 Minuten Sperre). Das Secret liegt nur verschlüsselt (`SecretBox`) in `secret_encrypted`; eine Neueinrichtung läuft über `pending_secret_encrypted` und ersetzt das alte Secret erst nach bestätigtem Code (bei aktivem TOTP ist dafür ein aktueller Code nötig). Aktiv ist TOTP erst mit `activated_at`. `UserRecoveryCode`: genau 3 pro aktivem TOTP, nur als HMAC gespeichert, je einmal verwendbar (`used_at`), Neuerzeugung ersetzt alle; ein Recovery Code deaktiviert TOTP nie. TOTP ist für `superadmin`/`orgchef`/`suborgchef` (`AdminCapabilityChecker::hasGlobalAdminRole`) verpflichtend und für sie nicht deaktivierbar; geschützte Adminfunktionen setzt `AdminMfaGuardSubscriber` zentral durch (siehe ARCHITECTURE.md, Admin-MFA). API: `/api/profiles/{id}/security/totp` (GET Status, POST `/enroll`, `/enroll/confirm`, `/recovery-codes/regenerate`, `/disable`); Secret und Recovery Codes stehen nur in den Antworten von enroll bzw. confirm/regenerate, nie im Status oder Audit-Log. Beim Login folgt bei aktivem TOTP eine einmalige `MfaChallenge` (5 Minuten, nur Hash gespeichert); erst ihre erfolgreiche Prüfung erzeugt Sitzung (`mfa_verified_at`) und Tokens.
- `Department.isGrossanlass` markiert ein Grossanlass-Department; optionale `DepartmentGrossanlassConfig` (1:1).
- `ExternalIdentity` ist kein Rollen-/Department- oder Provider-Token-Container. Es hält nur den Provider-Namen, die externe User-ID und optionale E-Mail-Metadaten; Email ist kein Identitätsschlüssel.

Grossanlass-spezifische Rollen (`cmw`, `bl`, `komm`, `spon`, `lw`, `clw`, …): `backend/src/Service/Grossanlass/GrossanlassAccessRoles.php` und [grossanlass/README.md](./grossanlass/README.md).

## Material

```text
Department 1—* Category (Baum) 1—* MaterialItem 1—* MaterialBatch
```

- `MaterialItem.department` ist der Bestandsträger. `materialType`: `physical` | `physical_combo` | `virtual_combo`. `trackingType`: `serialized` | `bulk` (nullable).
- `MaterialBatch` hängt optional an Adresse, `StorageRack`, `StorageSlot`.
- Combo-Struktur: `MaterialComboComponent`, `MaterialComboOption`, `MaterialComboOptionGroup`, `MaterialComboOptionDelta`. Vorlagen analog mit `MaterialTemplate*`. Details: [material/combos/README.md](./material/combos/README.md).

## Aktivitäten

```text
Department 1—* Activity *—0..1 Group
Activity 1—* ActivityItem *—1 MaterialItem
```

- `Activity.type` Default `activity`, `Activity.status` Default `draft`. Statusliste und Übergänge: [activities/status.md](./activities/status.md), nicht hier duplizieren.
- Weitere Activity-Aggregate (Pack, Retour, Transport, J+S-Order, Grossanlass-Runden) sind eigene Entities `Activity*` am Activity- oder Department-Kontext. Pipeline: [activities/material-pipeline.md](./activities/material-pipeline.md).

## Accounting

Department-scoped. Kein Vereins-Hauptbuch — Einordnung: [accounting.md](./accounting.md).

```text
Department 1—* AccountingCostCenter
Department 1—* AccountingBudgetLine *— AccountingCostCenter
Department 1—* AccountingCostCenterRule *— AccountingCostCenter
Department 1—* AccountingBooking *— AccountingCostCenter, optional Group, optional MaterialItem
AccountingAcquisitionFollowUp → Department, optional MaterialBatch, Activity, MaterialItem, AccountingBooking
```

Aktivitäts-Abschluss und Buchhaltungs-Abschluss sind entkoppelt; Regeln in [accounting.md](./accounting.md).

## Werkstatt, Nachrichten, Lieferanten

| Aggregat | Beziehung |
| --- | --- |
| `WorkshopTicket` | Department, optional MaterialItem, MaterialBatch, Activity, ActivityIssueReport, SupplierCompany, User — [workshop/README.md](./workshop/README.md) |
| `InboxMessage` | Department — [nachrichtenzentrale.md](./nachrichtenzentrale.md) |
| `SupplierCompany` | optionale Address, optionales Department; `SupplierMembership` zu User (eigene Rolle, nicht `DepartmentRole`) — [supplier/supplier-portal.md](./supplier/supplier-portal.md) |

`SupplierDelivery`, `SupplierCatalogItem`, `SupplierMaterialTemplate*` hängen an der Firma, nicht am Department-Bestand.

## Grossanlass-Entities

`DepartmentGrossanlass*` und `ActivityGrossanlass*` erweitern Department bzw. Activity (Config, Budget, Pack, Beschaffung, Runden, Wünsche, …). Ressorts sind `Group` im Grossanlass-Department (`Group.grossanlassKind`, `Group.parent`). Fachregeln: [grossanlass/README.md](./grossanlass/README.md). Der Kopf dort trennt Ist und Soll; Entities und Controller bleiben der Ist-Nachweis.

## Was dieses Dokument nicht ist

Jedes Entity ist nicht aufgeführt (`Print*`, `Address`, `PublicCode`, `JoinRequest`, `InventoryTask`, `SitePage`, …). Neue zentrale Beziehung hier ergänzen; Feldlisten und Workflows in der bestehenden Fachdatei.
