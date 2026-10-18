# 01 Systemübersicht

## Was eMatChef ist

`IMPLEMENTIERT` Webanwendung für Materialverwaltung und Ausleihe in Organisationen (Pfadi-Umfeld; Lokalisierungsvarianten `de-pfadi`, `de-cevi`, `de-jubla`, `fr-pfadi` in `frontend/src/i18n.ts`, `frontend/src/locales/`). Eine Single-Page-App (Vue) spricht mit einer JSON-API (Symfony) auf PostgreSQL. Lizenz AGPL-3.0 (`backend/composer.json`).

## Hauptbereiche

| Bereich | Zweck | Einstieg Frontend | Einstieg Backend |
| --- | --- | --- | --- |
| **Department** (normal) | Material, Kategorien, Aktivitäten (Ausleihe/Packen/Retour), Werkstatt, Buchhaltung, Einstellungen, Nachrichten | `/{departmentId}/dept/*` | `backend/src/Controller/`, `backend/src/Service/` |
| **Grossanlass (GA)** | Event-Planung als Department mit `isGrossanlass`: Ressorts, Wünsche, Beschaffung, Logistik, Personal, Infoscreens | `/{departmentId}/ga/*`, `frontend/src/views/grossanlass/` | `Grossanlass*Controller`, `backend/src/Service/Grossanlass/` |
| **Globale Verwaltung** | Organisationen, Benutzer, Support-Anfragen, Integrationen, Mail, Seiteninhalt | `/admin-dashboard`, `/site-inhalt` | `AdminCapability*`, `Organisation*`, `IntegrationAdminController` |
| **Lieferantenportal** | Lieferfirmen mit Katalog, Lieferungen, Reparaturvorlagen | `/supplier/:companyId`, `frontend/src/views/supplier/` | `Supplier*Controller` |
| **Profil / Konto** | Login, MFA, Sitzungen, E-Mail-Adressen, verknüpfte Anmeldungen | `/profile/*`, `/login` | `AuthController`, `Profile*Controller`, `backend/src/Service/Auth/` |
| **Öffentliche Flächen** | Landing, QR-Auflösung (`/i/…`), Infoscreens (`/display`), Geräte-UI (devices-Host) | `frontend/src/views/public`, `DisplayHomeView.vue`, `views/devices/` | `backend/src/Controller/Public/`, `Public*Controller` |
| **Demo / Sandbox** | Reproduzierbare Demo-Daten und Zeitmodell | `DemoResetControl.vue` | `backend/src/Service/Demo/`, `Service/Bootstrap/` |

Department und Grossanlass sind Teilbereiche desselben Systems; ein Grossanlass ist technisch ein `Department` (siehe [04](./04_Mandantenstruktur.md)).

## Aufgaben des Gesamtsystems

- Material, Chargen, Kombos, Lagerorte und QR-Codes verwalten (`MaterialItem`, `MaterialBatch`, `StorageRack/Slot`, `PublicCode`).
- Aktivitäten mit Materialbedarf planen, packen, transportieren, zurücknehmen (`Activity*`).
- Rollenbasierte Zusammenarbeit pro Department und über Verwaltungsbereiche (Organisation/Department-Baum).
- Event-Prozesse (Grossanlass): Wünsche → Beschaffung → Logistik → Einsatzplanung.
- Kosten-Einordnung pro Department (`Accounting*`, kein Hauptbuch, vgl. `docs/accounting.md`).
- Transaktionsmail, Nachrichtenzentrale, Infoscreens, Druck/PDF.

## Nicht Teil des Repositories

- Öffentliche Benutzer- und Entwicklerdoku (`docs.ematchef.ch`): separates Repository (laut `AGENTS.md`).
- Weblate-Instanz für Übersetzungen (`docs/TRANSLATION.md`).
