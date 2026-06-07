# Plattform Zeltblatt-Templates (`repair_template`)

Stamm-Struktur für den Materialwart-Workflow 2026. Departments importieren über
`POST /api/departments/{id}/repair-templates/import` und pflegen CHF-Preise selbst.

## Vorlagen (Paket 22)

| `template_key` | Name | Sektionen |
|----------------|------|-----------|
| `spatz` | Spatz | Aussenzelt, Innenzelt, Vordach, **Apsis**, Sonderposten |
| `phoenix` | Phönix (Zelthangar) | Aussenzelt, Innenzelt, Vordach, Sonderposten (+ Hochstelleinheit, Winkel) |
| `hajk` | hajk | Aussenzelt, Innenzelt, Vordach, Sonderposten |
| `wico` | Wico | Aussenzelt, Innenzelt, Vordach, Sonderposten |

Datenquelle: `backend/src/Data/RepairTemplatePlatformSeeds.php`  
Migration: `Version20260605170000`

## `repair_template_key` am Material

Automatisch gesetzt (Migration) anhand Hersteller/Modell:

| Bedingung | Key |
|-----------|-----|
| `manufacturer` = Spatz (case-insensitive) | `spatz` |
| `manufacturer` = hajk | `hajk` |
| `manufacturer` = Wico | `wico` |
| `manufacturer` = Zelthangar **oder** Name/Modell enthält „phoenix“ | `phoenix` |

Neue Materialien: Key manuell in der Materialbearbeitung setzen oder per API (`repair_template_key`).

Varianten (z. B. Spatz 6er vs. 8er) teilen sich vorerst denselben Stamm; feinere Keys (`spatz_8er`) sind Phase 7 optional.

## Reparatur-Positionen (pro Stoff-Sektion)

- Öse / Ring
- Reissverschluss
- Naht
- Planenflicken
- Heringlasche
- Abspannöse / Schnur
- Klettband

**Sonderposten:** Gestänge/Gummiring, Bodenplane, Verstärkungsband, Sonstiges (+ typspezifische Extras bei Phönix).
