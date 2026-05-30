# Org Subset Seed

Dieser Ordner ist fuer den Teil-Export der Organisation **J&S** (`org_js000000`) inkl. globaler Vorlagen und globaler Lieferanten-Adressen (`scope=global`).

> **Paket 15:** `GLOBALORG001` / `GLOBAL000000` entfallen — globale Lieferanten liegen direkt mit `scope=global` in `subset.json`.

## Standard-Datei

- `subset.json`

Erzeugen:

```bash
php bin/console app:org-subset:export --org=org_js000000 --with-global-templates --output=data/seeds/orgs/org_js000000_and_GLOBALORG001/subset.json
```

Importieren:

```bash
php bin/console app:org-subset:import --file=data/seeds/orgs/org_js000000_and_GLOBALORG001/subset.json --ensure-superadmin
```

Globale Lieferanten im Seed haben `scope=global` und `department_id=null`. Legacy-Seeds mit `department_id=GLOBAL000000` werden beim Import weiterhin normalisiert.
