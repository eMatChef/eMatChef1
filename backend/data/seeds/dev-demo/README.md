# Dev-Demo Seeds

Demo-Konten kommen aus `app:create-role-users` / `app:dev-demo:reset` (nur mit `EMATCHEF_DEV_TOOLS`, nie Production).
Einzige Quelle ist [`demo-accounts.json`](./demo-accounts.json): Adressen `<rolle>@demo.ematchef.ch`, Passwort `test!ematchef`,
fixe Test-TOTP-Secrets für `superadmin`, `orgchef`, `suborgchef` (Reseed ändert sie nicht). Dieselbe Datei erzeugt die Seite
`docs.ematchef.ch → Anleitung → Entwicklung → Testumgebung` (Login, Rolle, QR, Setup-Key). Bestehende `*@ematchef.ch`-Konten werden beim Reseed umgestellt.
Dazu `supplier@demo.ematchef.ch` (Testfirma, Supplier-Bereich)
und Grossanlass-Rollen in der Abteilung **Demo Grossanlass**:
`ga-mw@`, `ga-cmw@`, `ga-ok@`, `ga-komm@`, `ga-spon@`, `ga-bereich@` (Leader), `ga-helfer@` (jeweils `@demo.ematchef.ch`).

## Grossanlass-Demo-Szenario (PFF-inspiriert)

Nach `app:create-role-users` / `app:dev-demo:reset` legt `DemoGrossanlassSeedService` an:

**Struktur**

```
Infrastruktur
├── Material & Logistik   ← Anlass-Topf, ga-mw ★
├── Bauten                ← ga-bereich ★, ga-helfer
└── Wasser & Sanitär
```

**Zuordnungen**

| User | Rolle / Knoten |
|------|----------------|
| ga-mw | Leader Material & Logistik |
| ga-bereich | Leader Bauten |
| ga-helfer | Mitglied Bauten (+ Fahrrecht Demo) |
| ga-ok | Mitglied Infrastruktur (anlassweite Übersicht) |
| ga-komm / ga-spon | nur Dept-Rolle, kein Baum-Zwang |

**Fachdaten**

- Zusagen: Demo · Festtische, Demo · Transporter
- Einsätze: Selbstabholung + Fahrauftrag (Chauffeur Helfer) + pending für OK + Sanitär
- Orte: `Demo · Bauten Lagerplatz`, `Demo · Sanitär Lagerplatz` (Cross-Ressort-Scan)

```bash
# Demo-Grossanlass komplett löschen (Dev):
php bin/console app:demo-grossanlass:wipe

# Rollen-User ohne Demo-Grossanlass (Standard):
php bin/console app:create-role-users --skip-delete

# Demo-Szenario wieder anlegen (optional):
php bin/console app:create-role-users --skip-delete --with-ga-demo

# oder voller Dev-Reset:
php bin/console app:dev-demo:reset --e2e-password='test!ematchef'
```

Org-Subset-Export für erweiterte Demos kann hier abgelegt werden, z. B.:

```text
backend/data/seeds/dev-demo/subset.json
```
