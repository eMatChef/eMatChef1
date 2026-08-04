# System-Vorlagen (JSON v5)

Import:

```bash
php bin/console app:templates:import --global --all --force
# oder einzeln:
php bin/console app:templates:import --global --file=data/templates/zelthangar-phoenix.json --force
```

| Datei | Art | Hinweis |
|-------|-----|---------|
| `sarasani.json` | flache virtuelle Kombo (`combo`) | feste Stückliste inkl. Mast `self_provided` — Pack-DoD Paket 8 |
| `zelthangar-phoenix.json` | Konfigurator (`configurator`) | Beispiel mit `option_groups` / Deltas (Innenzelt, Aufbau, Toggle) — C11 |

Schema: [docs/material/templates-import-export.md](../../../docs/material/templates-import-export.md)
