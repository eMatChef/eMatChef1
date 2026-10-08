# Dev-Demo Seeds

Demo-Konten kommen aus `app:create-role-users` / `app:dev-demo:reset` (nur mit `EMATCHEF_DEV_TOOLS` **und** gesetztem `EMATCHEF_ENV_NAME` = `local`/`develop`/`staging`; nie Production). Die Commands löschen standardmässig nichts; Löschen (`--delete-demo-users`, Wipe) nur lokal oder auf Develop mit `EMATCHEF_DEMO_DESTRUCTIVE=1`, nie auf Staging/Production. Rollen-User gehen nur in ein Department mit `demo_mode` (`--department=<id> [--mark-department-demo]`). Siehe `docs/demo/SEED-KONZEPT.md` §3.2.
Einzige Quelle ist [`demo-accounts.json`](./demo-accounts.json): Adressen `<rolle>@demo.ematchef.ch`, Passwort `test!ematchef`,
fixe Test-TOTP-Secrets für `superadmin`, `orgchef`, `suborgchef` (Reseed ändert sie nicht). Dieselbe Datei erzeugt die Seite
`docs.ematchef.ch → Anleitung → Entwicklung → Testumgebung` (Login, Rolle, QR, Setup-Key). Bestehende `*@ematchef.ch`-Konten werden beim Reseed umgestellt.
Dazu `supplier@demo.ematchef.ch` (Testfirma, Supplier-Bereich)
und Grossanlass-Rollen in der Abteilung **Demo Grossanlass**:
feat/grossanlass-material-flow
`ga-mw@`, `ga-cmw@`, `ga-ok@`, `ga-komm@`, `ga-spon@`, `ga-lw@`, `ga-clw@`, `ga-bereich@` (Leader), `ga-helfer@` (jeweils `@demo.ematchef.ch`).
develop

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
| ga-komm / ga-spon / ga-lw / ga-clw | nur Dept-Rolle, kein Baum-Zwang (LW/CLW noch ohne Logistikrechte) |

**Fachdaten**

- Zusagen: Demo · Festtische, Demo · Transporter
- Einsätze: Selbstabholung + Fahrauftrag (Chauffeur Helfer) + pending für OK + Sanitär
- Orte: `Demo · Bauten Lagerplatz`, `Demo · Sanitär Lagerplatz` (Cross-Ressort-Scan)

```bash
# Demo-Grossanlass komplett löschen (Dev):
php bin/console app:demo-grossanlass:wipe --confirm='Demo Grossanlass'

# Rollen-User ohne Demo-Grossanlass (Standard):
php bin/console app:create-role-users

# Demo-Szenario wieder anlegen (optional):
php bin/console app:create-role-users --with-ga-demo

# oder voller Dev-Reset:
php bin/console app:dev-demo:reset --e2e-password='test!ematchef'
```

Szenario-Gerüst (Phase 1) und Organisationsstruktur (Phase 2, Katalog `organisations.json`; löscht nichts, legt Organisation/Department/Gruppen/Benutzer/Mitgliedschaften der drei Szenarien an): `php bin/console app:demo:status`, `app:demo:verify --all`, `app:demo:sync --all --dry-run`, `app:demo:adopt --scenario=<key> --department=<id>`. `app:demo:reset` ist für alle Szenarien noch gesperrt. Die Camp-Konten (`camp-*@`) entstehen erst durch `app:demo:sync`, nicht durch `app:create-role-users`. Siehe `docs/demo/SEED-KONZEPT.md` §7.0.

Org-Subset-Export für erweiterte Demos kann hier abgelegt werden, z. B.:

```text
backend/data/seeds/dev-demo/subset.json
```
