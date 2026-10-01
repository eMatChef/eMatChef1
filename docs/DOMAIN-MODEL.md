# Domänenmodell

Beziehungen, die in `backend/src/Entity/` stehen. Keine Zielbilder. Tiefere Regeln stehen in der verlinkten Fachdoku. Weicht die Fachdoku von diesem Ist ab, gilt nicht automatisch der Code: Soll-Text bleibt Soll, offensichtlich veraltete Sätze werden ersetzt, unklare Konflikte werden entschieden und danach hier oder in der Fachdatei festgehalten.

## Organisation und Zugang

```text
Organisation 1—* Department (Baum über Department.parent)
Department 1—* Membership *—1 User
Department 1—* Group (Baum über Group.parent) 1—* GroupMembership *—1 User
```

- `Membership.role` ist der Department-Rollenwert (`DepartmentRole`, Spalte kurz: `sa`, `org`, `sub`, `mw`, `cmw`, `dc`, `bl`, `komm`, `spon`, `l1`, `l2`, `l3`, `u`). Hierarchie und Symfony-Rollen: `backend/src/Enum/DepartmentRole.php`. Wer welche Rolle vergeben darf: `backend/src/Service/MembershipRoleCatalog.php`.
- `Membership.isJsCoach` ist ein Flag, keine Rolle.
- `GroupMembership.role` ist nur `leader` (Gruppenchef) oder `member`.
- `User` hat `Profile`, optionales `lastUsedDepartment` und optionales `lastUsedSupplierCompany`. Ein User kann mehrere Department-Memberships haben.
- `Department.isGrossanlass` markiert ein Grossanlass-Department; optionale `DepartmentGrossanlassConfig` (1:1).

Grossanlass-spezifische Rollen (`cmw`, `bl`, `komm`, `spon`, …): `backend/src/Service/Grossanlass/GrossanlassAccessRoles.php` und [grossanlass/README.md](./grossanlass/README.md).

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
