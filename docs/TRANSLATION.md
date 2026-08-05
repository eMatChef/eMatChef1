# Übersetzungen (Weblate)

Instanz: [translate.ematchef.ch](https://translate.ematchef.ch)  
Dateien: `frontend/src/locales/*.json`  
App-Codes: siehe `frontend/src/config/languages.ts` und `backend` `app.supported_languages`.

## Erlaubte Sprachen (kein Wildwuchs)

Nur diese Dateien gehören ins Repo und in Weblate:

| Code | Datei | Rolle |
|------|--------|--------|
| `de` | `de.json` | **Quelle** (Schweizer Deutsch) |
| `en` | `en.json` | Vollübersetzung |
| `fr` | `fr.json` | Vollübersetzung |
| `it` | `it.json` | Vollübersetzung |
| `ch-rm` | `ch-rm.json` | Rumantsch (Lücken → de/en) |
| `de-pfadi` | `de-pfadi.json` | Delta zu `de` (Pfadi-Wortschatz) |
| `de-cevi` | `de-cevi.json` | Delta zu `de` (Cevi-Wortschatz) |

Keine `en-US`, `fr-FR`, `it-IT`, `de-CH`, … — kurze Codes = Dateiname = App/DB.

Neue Org-Variante (z. B. `de-jubla`): kleines Delta-JSON + Eintrag in `SUPPORTED_LANGUAGE_CODES` / Backend — **nicht** als Vollkopie von `de.json`.

## Zwei Schichten

| Schicht | Was | Wo |
|--------|-----|-----|
| **App-UI** | alle Produkt-Strings | Weblate → Locale-JSON |
| **Org-Typ-Delta** | viele gemeinsame Begriffe (Pfadi/Cevi/Jubla) | `de-pfadi.json` usw. (Weblate-Component „Varianten“) |
| **Org-Wörterbuch** (später) | wenige Overrides pro einzelner Org | DB, nicht Weblate |

`de-pfadi` bleibt bewusst in Weblate: der Pfadi-Wortschatz ist gross und organisationsübergreifend. Ein DB-Wörterbuch ist nur für Ausnahmen pro Abteilung sinnvoll, nicht als Ersatz dafür.

## Weblate-Component (Pflicht-Setup)

### Component A — „App UI“ (Ist-Stand auf translate.ematchef.ch)

| Feld | Soll-Wert |
|------|-----------|
| Quellcode-Repository | `git@github.com:eMatChef/eMatChef1.git` |
| Repository-Branch | `prod` |
| Push-Branch | `weblate` |
| Dateiformat | **JSON-Datei mit verschachtelter Struktur** (`json-nested`) — nicht i18next |
| Dateimaske | `frontend/src/locales/*.json` |
| Sprachfilter | `^(en\|fr\|it\|ch-rm)$` |
| Basissprachdatei | `frontend/src/locales/de.json` |
| Ausgangssprache | Deutsch (Schweiz) `de_CH` — Datei trotzdem `de.json` |
| Neue Übersetzung hinzufügen | **deaktivieren** (`none`) |
| Stil des Sprachcodes | BCP mit Bindestrich |
| Sprachen | `en`, `fr`, `it`, `rm` (Datei `ch-rm.json`) |
| Projekt-Alias | `ch-rm:rm` (sonst wird `ch-rm` zu Chamorro) |

`de-pfadi` / `de-cevi` **nicht** in dieser Component (sonst volle Key-Liste).

**SSH:** Deploy Key + `known_hosts` unter `/app/data/ssh/`. Ohne Hostkeys: `Host key verification failed`. Ohne Clone in `/app/data/vcs/…`: Merge/Push `Errno 2`.

**Push-Branch:** `weblate` → PR nach `prod`/`develop`.

### Component B — „DE Varianten“ (Pfadi / Cevi / später Jubla)

Linked Component auf demselben Git-Clone wie App UI (`weblate://ematchef/app-ui`):

| Feld | Soll-Wert |
|------|-----------|
| Name / Slug | `DE Varianten` / `de-varianten` |
| Repository | `weblate://ematchef/app-ui` |
| Dateiformat | `json-nested` |
| Dateimaske | `frontend/src/locales/*.json` |
| Sprachfilter | `^(de-pfadi\|de-cevi)$` |
| Basissprachdatei | `frontend/src/locales/de.json` |
| Ausgangssprache | wie App UI (`de_CH`) |
| Neue Übersetzung | deaktivieren |
| Sprachcodes | BCP mit Bindestrich |

Custom-Sprachen in Weblate anlegen: `de-pfadi` („Deutsch (Pfadi)“), `de-cevi` („Deutsch (Cevi)“).  
Übersetzer pflegen nur abweichende Keys; fehlende fallen in der App auf `de` zurück.

Neue Org-Variante (z. B. `de-jubla`): Locale-JSON + `languages.ts` / Backend + Sprachfilter um `|de-jubla` erweitern + Language in Weblate.

## Sync-Flow

1. Dev fügt Keys in **`de.json`** ein → Push auf den Weblate-Branch.  
2. Weblate **Update** (Webhook oder manuell: Component → Repository → Update).  
3. Übersetzer arbeiten in Weblate.  
4. Weblate commit/push (Branch `weblate` oder PR) → Review → Merge → Deploy.

## Anti-Wildwuchs-Checkliste

- [ ] File mask trifft nur echte Locales unter `frontend/src/locales/`  
- [ ] Keine automatische Spracherkennung aus Dateinamen-Junk  
- [ ] Nach Repo-Update: keine neuen `*-US` / `*-FR`-Sprachen entstanden  
- [ ] Neue Sprache = Code in `languages.ts` + Backend + Locale-Datei + Weblate-Sprache  
- [ ] Varianten bleiben Deltas, keine Vollkopien von `de.json`
