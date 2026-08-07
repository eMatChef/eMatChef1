# Übersetzungen (Weblate)

Instanz: [translate.ematchef.ch](https://translate.ematchef.ch)  
Dateien: `frontend/src/locales/*.json`  
App-Codes: siehe `frontend/src/config/languages.ts` und `backend` `app.supported_languages`.

## Erlaubte Sprachen (kein Wildwuchs)

Nur diese Dateien gehören ins Repo und in Weblate:

| Code | Datei | Rolle |
|------|--------|--------|
| `de` | `de.json` | **Quelle App-UI** (Schweizer Deutsch) |
| `en` | `en.json` | Vollübersetzung |
| `fr` | `fr.json` | Vollübersetzung |
| `it` | `it.json` | Vollübersetzung |
| `ch-rm` | `ch-rm.json` | Rumantsch (Lücken → de/en) |
| `de-pfadi` / `de-cevi` / `de-jubla` | `de-pfadi.json` usw. | Delta zu `de` |
| `fr-pfadi` / `fr-cevi` / `fr-jubla` | `fr-pfadi.json` usw. | Delta zu `fr` |
| `it-pfadi` / `it-cevi` / `it-jubla` | `it-pfadi.json` usw. | Delta zu `it` |

Keine `en-US`, `fr-FR`, `it-IT`, `de-CH`, … — kurze Codes = Dateiname = App/DB.

Org-Varianten sind **Deltas** (`{}` reicht als Stub). Fehlende Keys fallen in der App auf die Basis-Locale (`fr-pfadi` → `fr` → `de`).

## Zwei Schichten

| Schicht | Was | Wo |
|--------|-----|-----|
| **App-UI** | alle Produkt-Strings | Weblate Component „App UI“ |
| **Org-Typ-Delta** | Org-Wortschatz (Pfadi/Cevi/Jubla) pro Sprache | Weblate Components „DE/FR/IT Varianten“ |
| **Org-Wörterbuch** (später) | wenige Overrides pro einzelner Org | DB, nicht Weblate |

## Branch-Modell (wie eCamp: am Dev-Branch hängen)

| Rolle | Branch |
|-------|--------|
| Weblate liest (neue Keys aus der Entwicklung) | **`develop`** |
| Weblate pusht Commits | **`weblate`** |
| Auto-Integration | GitHub Action **Weblate integrate develop** → nur `frontend/src/locales/**` nach **`develop`** |
| Prod | später per Release `develop` → `prod` (kein Staging-Zwang) |

Staging/Prod-Kette wie eCamp ist optional für später — jetzt reicht develop + Release nach prod.

## Weblate-Component (Pflicht-Setup)

### Component A — „App UI“

| Feld | Soll-Wert |
|------|-----------|
| Repository | `git@github.com:eMatChef/eMatChef1.git` (oder linked) |
| Branch | **`develop`** / Push-Branch **`weblate`** |
| Dateiformat | `json-nested` |
| Dateimaske | `frontend/src/locales/*.json` |
| Sprachfilter | `^(en\|fr\|it\|ch-rm)$` |
| Basissprachdatei | `frontend/src/locales/de.json` |
| Ausgangssprache | `de_CH` (Datei trotzdem `de.json`) |
| Neue Übersetzung | **deaktivieren** (`none`) |
| Sprachcodes | BCP mit Bindestrich |
| Projekt-Alias | `ch-rm:rm,de:de_CH` |

Org-Varianten **nicht** in dieser Component.

### Components B/C/D — Org-Varianten (je Basis-Sprache)

Linked auf denselben Clone (`weblate://ematchef/app-ui`). Pro Basis eine Component (eigene Quelle). Branch wie App UI: **`develop`**.

**Dateimaske immer** `frontend/src/locales/*.json` — **nicht** `de-*.json`.  
Weblate setzt `*` = Sprachcode: bei Maske `de-*.json` und Code `de-jubla` entsteht fälschlich `de-de-jubla.json`.

| Component | Slug | Template (Quelle) | Dateimaske | Sprachfilter |
|-----------|------|-------------------|------------|--------------|
| DE Varianten | `de-varianten` | `frontend/src/locales/de.json` | `frontend/src/locales/*.json` | `^(de-pfadi\|de-cevi\|de-jubla)$` |
| FR Varianten | `fr-varianten` | `frontend/src/locales/fr.json` | `frontend/src/locales/*.json` | `^(fr-pfadi\|fr-cevi\|fr-jubla)$` |
| IT Varianten | `it-varianten` | `frontend/src/locales/it.json` | `frontend/src/locales/*.json` | `^(it-pfadi\|it-cevi\|it-jubla)$` |

Gemeinsam: Format `json-nested`, neue Übersetzung `none`, BCP mit Bindestrich, Ausgangssprache = Template-Sprache (`de_CH` / `fr` / `it`).

## Sync-Flow

1. Dev fügt Keys in **`de.json`** auf **`develop`** ein → Push.  
2. Weblate **Update** (Webhook oder manuell) — sieht neue Strings.  
3. Übersetzer speichern in Weblate → Push auf Branch **`weblate`**.  
4. Action **Weblate integrate develop** öffnet/merged PR `chore/weblate-locales` → **`develop`** (Branch-Protection; CD Develop deployst).  
5. Release nach **`prod`** wenn bereit (PR / Fast-forward).

Umstellung Droplet: `docs/weblate-switch-to-develop.sh`.

## Anti-Wildwuchs-Checkliste

- [ ] App UI-Filter ohne Org-Varianten  
- [ ] Varianten-Components: Maske `*.json` + enger Sprachfilter (nie `de-*.json` bei Codes `de-…`)  
- [ ] `new_lang=none` überall  
- [ ] Neue Variante = Code in `languages.ts` + Backend + Locale-Stub + Weblate-Sprache + Filter  
- [ ] Varianten bleiben Deltas, keine Vollkopien  
- [ ] Keine Dateien wie `de-de-jubla.json` / `fr-fr-pfadi.json` im Repo  
- [ ] Repo-Branch = `develop`, Push-Branch = `weblate`

## Weblate Setup-Skripte

- `docs/weblate-setup-org-variants.sh` — Sprachen + DE/FR/IT-Components  
- `docs/weblate-switch-to-develop.sh` — Branch `develop` + Clone neu
