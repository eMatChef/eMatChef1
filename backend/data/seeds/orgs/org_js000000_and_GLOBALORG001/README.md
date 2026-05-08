# Org Subset Seed

Dieser Ordner ist fuer den Teil-Export der Organisationen:

- `org_js000000`
- `GLOBALORG001`

## Standard-Datei

- `subset.json`

Erzeugen:

```bash
php bin/console app:org-subset:export --org=org_js000000 --org=GLOBALORG001 --with-global-templates --output=data/seeds/orgs/org_js000000_and_GLOBALORG001/subset.json
```

Importieren:

```bash
php bin/console app:org-subset:import --file=data/seeds/orgs/org_js000000_and_GLOBALORG001/subset.json --ensure-superadmin
```

