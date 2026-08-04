# Material-Journey UI — Konzept (Neues Pack-/Ausgabe-UI)

Zielbild für eine **neue Oberfläche** für Packen, Ausgabe, Retour und Einlagern in der Aktivität: weg vom **Zweispalten-Board**, hin zu **einer Journey-Achse**, **Checklisten** und **Scan/Suche** als zentralem Werkzeug — für **Materialwart und Gruppenleiter** in derselben Shell.

**Stand:** Juni 2026 · **Status:** Phasen 0–8b umgesetzt (Journey Beta auf Route `pack-journey`); Legacy Tab `packs` bleibt Standard bis Phase 13

**Detail-Spec:** [SPEC.md](./SPEC.md) · **Architektur ADR:** [ADR-workflow-layers.md](./ADR-workflow-layers.md)

**Verwandt:** [material-pipeline.md](../material-pipeline.md) · [pack-workflow-rules.md](../pack-workflow-rules.md) · [pack-step-ui.md](../pack-step-ui.md) · [status.md](../status.md) · [devices/pack-workflow.md](../../devices/pack-workflow.md)

---

## Inhaltsverzeichnis

1. [Problem und Motivation](#1-problem-und-motivation)
2. [Kernidee](#2-kernidee)
3. [Tabs in der Aktivität](#3-tabs-in-der-aktivität)
4. [Journey-Achse (Steps)](#4-journey-achse-steps)
5. [Gemeinsame Shell (Packliste)](#5-gemeinsame-shell-packliste)
6. [Checklisten statt Dual-Panel](#6-checklisten-statt-dual-panel)
7. [Scan und Suche (nur Packliste)](#7-scan-und-suche-nur-packliste)
8. [Rollen: MW und Leiter](#8-rollen-mw-und-leiter)
9. [Materialarten in der neuen UI](#9-materialarten-in-der-neuen-ui)
10. [Abgrenzung zur heutigen Packliste](#10-abgrenzung-zur-heutigen-packliste)
11. [Beziehung zu devices.ematchef.ch](#11-beziehung-zu-devicesematchefch)
12. [Implementierungsphasen (Überblick)](#12-implementierungsphasen-überblick)
13. [Parallel-Entwicklung & Mobile-First](#13-parallel-entwicklung--mobile-first)
14. [Produkt-Detailregeln (Kurz)](#14-produkt-detailregeln-kurz)
15. [Integration & Erweiterungen (Kurz)](#15-integration--erweiterungen-kurz)
16. [Offene / festgelegte Entscheidungen](#16-offene--festgelegte-entscheidungen)

---

## 1. Problem und Motivation

Die heutige Packliste (`ActivityPackListTab`) ist ein **Lager-Board** mit zwei Spalten (links «noch nicht», rechts «bereits …»), Stage-Tabs, Fortschrittsleiste mit vielen Buttons, Kisten-Karten und Kategorie-Accordions.

| Stärke heute | Schwäche heute |
|--------------|----------------|
| Volle Kontrolle für MW am Desktop | Auf dem Handy: langes Scrollen, viele Ebenen |
| Pipeline-Logik korrekt abgebildet | Gruppe denkt «Was hole ich ab?», UI fragt «Was ist links/rechts?» |
| Kisten, Kombis, Sonderfälle abgedeckt | Kistencheck als Modal mitten in der Liste |
| | Scan vor allem auf `devices.` (MW-Packen), nicht in der Gruppen-Ausgabe |

**Ziel:** Dieselbe **fachliche Pipeline** im Backend ([material-pipeline.md](../material-pipeline.md)), aber eine **andere Präsentation** im Tab **Packliste**: Aufgaben-Checkliste entlang einer Journey-Achse, mit **Scan** für Lager/Abholung. Tab **Material** bleibt für Planung und Einreichen — optisch angeglichen, **ohne** Scan.

---

## 2. Kernidee

**Zwei Tabs, zwei Phasen** — nur **Packliste (Journey)** wird neu gebaut; **parallel** zur bestehenden UI.

```
Aktivität
├── Übersicht
├── Material          ← Planen, Einreichen (UI refresh, Suche wie heute)
├── Packliste         ← Legacy: ActivityPackListTab (bleibt Standard)
├── pack-journey      ← Route: neue Journey-UI (Beta, parallel)
├── J+S               ← optional eigener Tab (camp/event)
├── Verbrauch / Meldungen / …
```

Die Journey beantwortet:

| Alt (Board) | Neu (Journey) |
|-------------|----------------|
| «Wo steht die Menge in der Pipeline?» | **«Was muss ich jetzt noch tun?»** |
| Links / Rechts | **Offen / Erledigt** |
| Stage-Tab «Gepackt → Am Event» | Journey-Schritt **«Ausgabe»** |

**Kein** Journey-Schritt «Planen» — Planung bleibt im Tab Material ([§3](#3-tabs-in-der-aktivität)).

---

## 3. Tabs in der Aktivität

### Tab Material — Planung (bestehende Funktion, neues Look)

| | Regel |
|--|--------|
| **Zweck** | Material suchen, Mengen setzen, Kombos, **einreichen** (`draft` → `submitted` / `approved`) |
| **Daten** | `activity_item` — noch keine Pack-Pipeline |
| **Suche** | wie heute: `ActivityMaterialAvailabilityLookup` + Verfügbarkeit im Zeitraum — **kein QR/Scan** |
| **UI** | visuell an Journey anlehnen (Karten, Abstände, Typografie) — **keine** Scan-Bar, **kein** Stepper |
| **Einreichen** | unverändert — Übergabe an MW; danach Tab Packliste relevant |

### Tab Packliste — Legacy (bleibt)

| | Regel |
|--|--------|
| **Komponente** | `ActivityPackListTab` im Tab `packs` — **unverändert** während Entwicklung |
| **Standard** | Produktions-UI für alle bis Rollout (Phase 13) |
| **MW** | Link «Neue Packliste (Beta)» → Route `pack-journey` |

### Route `pack-journey` — Journey (neu, parallel)

| | Regel |
|--|--------|
| **Route** | `/:departmentId/activities/:activityId/pack-journey/:step?` |
| **Start** | Journey-Stepper ab **`pack`** (ab Status `packing`) |
| **Scan** | ja — ab Journey-Phase 4 ([§7](#7-scan-und-suche-nur-packliste)) |
| **Mobile-First** | Shell und Komponenten zuerst für Handy — [§13](#13-parallel-entwicklung--mobile-first) |
| **Zurück** | «Klassische Packliste» → Tab `packs` |
| **Vor `packing`** | readonly Empty-State «MW packt noch» |

### Tab J+S (camp/event) — **implementiert**

Eigenständiger Tab `ActivityJsTabView.vue` — drei Accordions (Bestellung / Empfang / Retour). Siehe [js-material/README.md](../js-material/README.md) §9.

### Ablauf Ersteller → MW

```
Material-Tab: planen → einreichen
       ↓
MW: Tab Packliste (Legacy) oder pack-journey (Beta), Step «Packen»
       ↓
Gruppe: Steps «Ausgabe» / «Retour» (Journey)
       ↓
MW: «Einlagern»
```

---

## 4. Journey-Achse (Steps)

Fortschritt **nur in der Packliste** als **horizontale Steps** oben. **Kein** Knoten «Planen».

### Quick-Profil (`activity`, `external`)

```
Packen → Ausgabe → Retour → Einlagern
```

### Logistics-Profil (`camp`, `event`)

```
Packen → Transport hin → Am Anlass → Transport zurück → Retour → Einlagern
```

| Regel | Verhalten |
|-------|-----------|
| Aktueller Schritt | hervorgehoben, Liste zeigt **Offen** für diesen Übergang |
| Vergangene Schritte | Tap = **Lesen** (Erledigt-Tab), keine Buchungen |
| Zukünftige Schritte | nicht bearbeitbar (nicht im Stepper bei anderem Profil) |
| Aktivitäts-Status | Primary-Button am Schritt-Ende (z. B. «Wir fahren los» → `at_event`) |

**Kein** sichtbares Dual-Panel «Gepackt | Am Event» — der Schritt **Ausgabe** *ist* der Übergang `quantity_packed` → `quantity_issued`.

Details: [SPEC.md §3](./SPEC.md#3-journey-steps).

---

## 5. Gemeinsame Shell (Packliste)

Jeder Journey-Schritt nutzt dieselbe Layout-Hülle:

```
┌─────────────────────────────────────────┐
│  Aktivitätsname                         │
│  ● Packen ─ Ausgabe ─ Event ─ Retour    │  Stepper
├─────────────────────────────────────────┤
│  🔍 Suchen oder scannen…                │  sticky
├─────────────────────────────────────────┤
│  Schritt-Titel · 8 / 18                 │  Fortschritt
│  [ Offen ] [ Erledigt ] [ Nach Regal ]  │  Filter (kontextabhängig)
├─────────────────────────────────────────┤
│  ○ Zeile 1                    [ Aktion ]│
│  ○ Zeile 2                    [ Öffnen ]│
│  ◐ Zeile 3 — Check offen      [ Prüfen ]│
│  ── erledigt (eingeklappt) ──           │
├─────────────────────────────────────────┤
│  [ Primary: Schritt abschliessen ]      │  optional, sticky unten
└─────────────────────────────────────────┘
```

**Zeilen-Status:**

| Symbol | Bedeutung |
|--------|-----------|
| ○ | offen — Aktion möglich |
| ◐ | teilweise / Kistencheck oder Set-Check ausstehend |
| ✓ | für diesen Schritt erledigt |

---

## 6. Checklisten statt Dual-Panel

### Lose Positionen

Eine Zeile pro Pack-Position (oder sinnvolle Bündelung). Primary-Button je Schritt:

| Schritt | Button (Leiter) | Button (MW) |
|---------|-----------------|-------------|
| Packen | — (nur Lesen) | **Packen** |
| Ausgabe | **Mitnehmen** | **Mitgeben** (Notfall) |
| Retour | **Retournieren** | wie Leiter + voller Check |
| Einlagern | — | **Ins Lager** |

Teilmengen über Sheet: «Alles» / «Nur N von M».

### Packkiste — Tap öffnet Inhalt

Kiste = **eine Zeile** in der Liste. Tap → **Vollbild oder Bottom Sheet** mit Checkliste aller Positionen in der Kiste:

- Abhaken pro Zeile (optional Scan pro Zeile)
- **[+ alle]** für ganze Zeile
- Primary unten: «Kiste als gepackt» / «Kiste mitnehmen» / «Kiste retourniert»

Entspricht fachlich dem **Kistencheck** ([pack-workflow-rules.md §4](../pack-workflow-rules.md#4-kistencheck--drei-beine-zwei-modi)) — als geführte Checkliste, nicht als Modal in einer Zwei-Spalten-Ansicht.

### Physische Kombi — gleiches Muster

Set = eine Zeile (Badge «Set»). Tap → Checkliste der BOM-Komponenten (Soll-Mengen). MW: **Set als gepackt**; Ausgabe: **Set prüfen** (outbound-Check); Einlagern: **Set ins Lager** (warehouse_store-Checkliste, vgl. pack-workflow-rules §6).

### Virtuelle Kombi

| `pack_mode` | Darstellung in Journey-UI |
|-------------|---------------------------|
| `together` | wie Packkiste (logischer Container) |
| `loose` | lose Zeilen + optional MW legt Kiste an |
| `self_provided` | eigener Block «Mitbringen» — nur Hinweis/Checkliste, keine Pipeline |

### Retour — gemischte Kisten

Am Event kann Kisteninhalt **physisch gemischt** sein. Retour nutzt ein **eigenes Sheet** (Logik von `PackReturnCrateModal`), nicht dasselbe wie Ausgabe:

- ☑ in Kiste retourniert / ☐ lose
- **Hinzugefügt** (`added`): Material liegt in der Kiste, war aber woanders eingeplant
- Verbrauch, Verlust, Reparatur im Sheet oder über Tab Meldungen

Details: [SPEC §7.5](./SPEC.md#75-materialreturncratesheet-retour).

### Material Journey — Grüner Pfeil, Kistencheck, Anzeige

Gilt für `ActivityMaterialJourneyView` (Tab Packliste, wenn Journey aktiv). **Fachlich dieselben Regeln** wie Packliste (`packWorkflowRules.ts`, `needsShellCratePresenceConfirm`); Code: `materialJourneyCrateCheckGate.ts`.

| Journey-Schritt | Phys.-Kombi (→) | Packkiste (→) | Transport-Tour aktiv |
|-----------------|-----------------|---------------|----------------------|
| **pack** | Kistencheck (outbound) | Zielkiste wählen / abwählen (kein Pfeil-Verschieben) | — |
| **issue** | Kistencheck | Kistencheck → ganz mitnehmen | Tour buchen (kein Check) |
| **transport_out / transport_back** | Tour buchen | Tour buchen | Tour buchen |
| **return / store** | Kistencheck (return / warehouse_store) | Kistencheck | — |

**Kistencheck-Modal:** nur solange für `(packItemId, leg, userId)` noch kein Eintrag in der History — danach einfaches Bestätigungs-Sheet (wie Packliste).

**Mehrere Packkisten gleicher Charge (z. B. Rakokiste 008 + 010):**

- Check-Inhalt und Buchung beziehen sich immer auf die **angeklickte** Kiste (`activeContainer`), nicht auf die erste der Charge.
- Backend `issue-all`: Shell-Material **max. 1× pro Kisten-Buchung** (nicht gesamte `quantityPacked` der Charge auf einmal).

**Liste «Mit mir unterwegs»:** Material nur in der Packkisten-Zeile, **nicht** zusätzlich lose — solange die Menge ausschließlich in Kisten gebucht ist (`looseQtyOnRightMirror === 0`). Shell-Zeile ausblenden, wenn mindestens eine sichtbare Packkiste der Charge in der Liste steht.

---

## 7. Scan und Suche (nur Packliste)

**Immer oben** in der Journey-Shell — für MW und Leiter.

### Leiter-Szenario (Abholung im Lager)

Scan eines Artikels → **Kontext-Karte** (kein Durchsuchen der Liste):

| Ergebnis | Anzeige |
|----------|---------|
| Bestellt, noch nicht gepackt | «Noch nicht bereit» + ggf. Regal |
| Gepackt, in Kiste | «In Kochkiste 2» → [Kiste öffnen] |
| Gepackt, lose | «Bereit» + Regal → [Mitnehmen] |
| Phys.-Kombi | [Set prüfen] |
| Nicht auf Liste | «Nicht für diesen Anlass bestellt» |
| Andere Aktivität | «Gehört zu …» → Wechsel anbieten |

### MW-Szenario (Packen)

Scan → «3× Taschenlampe packen?» [Ja] — oder Hinweis «in Kiste X einpacken».

MW kann parallel **nach Regal sortiert** arbeiten (ohne Scan) oder **Scan-first** (Liste als Kontrolle).

Matrix und API-Auflösung: [SPEC.md §6](./SPEC.md#6-scan-und-suche).

**Tab Material:** keine Scan-Bar — nur bestehende Materialsuche mit Verfügbarkeit.

---

## 8. Rollen: MW und Leiter

| Bereich | MW / DC | Leiter / Gruppe (L1–L3) |
|---------|---------|-------------------------|
| Packen | volle Liste, Regal-Sort, Kisten anlegen | Lesen / «MW packt noch» |
| Ausgabe | Notfall + Schalterbetrieb | **Hauptnutzer** |
| Retour | Einlagern, voller Kistencheck | leichter Retour-Check |
| Scan | Packen, Einlagern, Kontrolle | Ausgabe, «Habe ich das?» |
| Experten | Toggle **«Klassische Packliste»** ↔ **«Neue Packliste (Beta)»** | ausgeblendet |

MW verliert keine Funktion — erhält **Regal-Ansicht**, **Scan** und **Kisten-Checklisten** statt Spalten-Denken.

### Nachlieferungs-Wunsch (Ersteller → MW)

Zusätzlich zur gebuchten Nachlieferung (`is_replenishment` auf der Packliste) kann der **Ersteller** dem MW Material **wünschen** — mit **Verfügbarkeitsanzeige** aus dem Lager («ist das im Zeitraum verfügbar?»). MW sieht eine Wunsch-Queue und kann erfüllen (→ echte Nachlieferung) oder ablehnen.

Details: [SPEC §14](./SPEC.md#14-nachlieferungs-wunsch-ersteller--mw) · Implementierung Phase 10.

### Handoff MW ↔ Gruppe

Wenn MW ≠ Ersteller (`activity`/`camp`/`event`): ab `packed` übernimmt die Gruppe Ausgabe/Retour; MW sieht Info-Banner und darf nur im **Notfall** mit Confirm buchen. Nach `returned` ist die Packliste für die Gruppe readonly.

Matrix: [SPEC §19.1](./SPEC.md#191-handoff-banner--readonly-matrix).

---

## 9. Materialarten in der neuen UI

Die vier Materialarten aus [pack-workflow-rules.md §2](../pack-workflow-rules.md#2-vier-materialarten) bleiben fachlich gleich; die **Darstellung** ändert sich:

| Art | Journey-Zeile | Tap-Verhalten |
|-----|---------------|---------------|
| Loses Material | eine Zeile, Regal optional | Sheet: Menge |
| Packkiste | eine Zeile «📦 Name» | Sheet: Inhalt abhaken |
| Phys. Kombi | eine Zeile «Set» | Sheet: BOM-Checkliste |
| Virt. Kombi `together` | wie Packkiste | wie Packkiste |
| Virt. Kombi `loose` | wie loses Material | wie loses Material |

**Keine Doppelanzeige** (Kiste + gleiche Teile nochmals lose) — Regeln aus pack-workflow-rules §2 «Platzierung» bleiben gültig.

---

## 10. Abgrenzung zur heutigen Packliste

| | Heute (`ActivityPackListTab`) | Ziel (Material-Journey) |
|--|------------------------------|-------------------------|
| Layout | 2 Spalten + Stage-Tabs | 1 Liste + Stepper |
| Erledigt | rechtes Panel | Tab «Erledigt» / eingeklappt |
| Kiste | Container-Card in Spalte | Zeile + Sheet |
| Fortschritt | Progress-Bar + viele Buttons | `N / M` + ein Primary pro Schritt |
| Code | monolithisch (~9k Zeilen Tab) | neue Route + Composables, **shared** `packWorkflowRules` |

**Parallel-Entwicklung:** Legacy im Tab `packs` bleibt Standard. Journey auf Route `pack-journey` — kein Risiko für bestehende Workflows. Rollout Phase 13: Journey wird Default im Tab.

| Einstieg | Was |
|----------|-----|
| **Tab Material** | UI refresh, Funktion gleich, **kein Scan** |
| **Tab Packliste** | Legacy Dual-Panel (bis Rollout) |
| **Route pack-journey** | neue Checklisten-Journey |

Backend: **kein** neues Domänenmodell — weiterhin `activity_pack_item`, `activity_pack_container`, `PackPipelineService`, `packWorkflowRules.ts`.

---

## 11. Beziehung zu devices.ematchef.ch

| | `app.` Journey-UI | `devices.` |
|--|-------------------|------------|
| Zielgruppe | MW + Leiter in der Aktivität | MW am Lager-Terminal |
| Scan | integriert oben in Journey | Vollbild Scan-Session |
| UI | Checkliste + Steps | maximal reduziert |

Gleiche **Scan-Auflösung** und **move**-APIs. `devices.` kann als **reiner Scan-Modus** einer Journey-Phase bleiben (z. B. nur «Packen»); die Journey-Spec ist die führende UX für `app.`.

Siehe [devices/concept.md](../../devices/concept.md).

---

## 12. Implementierungsphasen (Überblick)

**Strategie:** Legacy unangetastet · Journey parallel · **Mobile-First** · Bausteine schrittweise.

| Phase | Inhalt | Status |
|-------|--------|--------|
| **0** | README + SPEC | ✓ |
| **1** | Route `pack-journey`, Shell, Stepper, leere Liste (Mobile 375px) | ✓ |
| **1b** | Material-Tab: UI refresh (ohne Scan) | ✓ |
| **2** | Lose: Packen + Ausgabe — `packWorkflowRules`, moves | ✓ |
| **3** | Kisten-/Kombi-Sheets (Vollbild Mobile) | ✓ |
| **4** | Scan-Resolve | ✓ |
| **5** | Regal-Gruppierung MW | ✓ |
| **6** | Retour + Einlagern | ✓ |
| **7** | Logistics Transport-Steps + Touren | ✓ |
| **8** | History-Audit (Pack-Events) | ✓ |
| **8b** | Benachrichtigungen (Inbox → Deep-Link `pack-journey`) | ✓ |
| **9** | J+S eigener Tab | ✓ |
| **9b** | Nutzungs-Statistik aus Moves | ✓ |
| **10** | Nachlieferungs-Wunsch | ✓ |
| **11** | ~~Kisten-Intent «Zusammen»~~ | entfernt — Zielkiste §19.2 |
| **12** | Presence light | ✓ |
| **13** | **Rollout:** Tab `packs` → Journey; Legacy «Klassische Packliste» | ✓ |

Details + Bausteine pro Phase: [SPEC.md §4.8, §12](./SPEC.md#48-zentrale-bausteine-integrationsreihenfolge).

---

## 13. Parallel-Entwicklung & Mobile-First

### Parallel-Entwicklung

| | Legacy | Journey (neu) |
|--|--------|---------------|
| **Einstieg** | Tab `packs` | Route `pack-journey` |
| **Komponente** | `ActivityPackListTab` | `ActivityMaterialJourneyView` |
| **Risiko** | keins — bleibt produktiv | isoliert, Beta-Link für MW |
| **Backend** | gleiche Pack-APIs | gleiche APIs |

Pro Phase nur **1–2 neue UI-Komponenten** + Anbindung an bestehende Bausteine (`packWorkflowRules`, `packStageQuantities`, move-API, …).

### Mobile-First

Die Journey wird **von Mobile her** gebaut — Gruppe und MW packen/ausgeben primär am Handy.

| Prinzip | Umsetzung |
|---------|-----------|
| Design-Baseline | 375px Breite |
| Layout | einspaltige Checkliste, sticky Scan-Bar, Primary unten |
| Sheets | Vollbild default; Side Sheet erst ab Tablet |
| Touch | min. 44px Tap-Targets |
| Desktop | Progressive Enhancement — gleiche IA, mehr Platz |

Jede Phase: **zuerst Mobile abnehmen**, dann Desktop smoke-test.

---

## 14. Produkt-Detailregeln (Kurz)

Vollständig in [SPEC §17–§19](./SPEC.md#17-detailregeln-produkt).

| Thema | Entscheidung |
|-------|--------------|
| **Kopfzeile vs. Journey** | Status in Kopfzeile; Mengen/Pipeline in Packliste |
| **Material nachbuchen** | MW-Block ab `pack`; Gruppe ab `at_event`; bei `packed`/`at_event` Modal (Wer holt? + Pipeline-Step) |
| **Kisten** | «In Kiste?» optional beim Regal-Wechsel; Toolbar-Scan; **Zielkiste** (grün) → Material antippen/scannen ([§19.2](./SPEC.md#192-packkiste-als-ziel--ohne-intent-gruppe)) |
| **`not_taken`** | Eigene Zeile «Nicht mitgenommen», Gruppe sichtbar |
| **Einlagern** | Scan/Tap → Regal/Fach → «Verräumt»; Toast + Ja/Nein-Countdown |
| **`moveback`** | Eine Packstufe zurück |
| **Partner-Dept** | MW nur eigenes Dept; andere readonly |
| **Handoff** | Banner-Matrix MW/Gruppe × Status — [§19.1](./SPEC.md#191-handoff-banner--readonly-matrix) |
| **Randfälle** | Netzwerk-Retry; Storno nur MW; unberechtigter Zugriff → Audit + Inbox |
| **Deep Links** | Inbox → `pack-journey/:step` (ab Phase 8b) |
| **Transport** | Mehrere Touren (A/B/C…); Fahrzeuge aus **Department-Fuhrpark** — [§19.3](./SPEC.md#193-transport--touren--department-fuhrpark) |

---

## 15. Integration & Erweiterungen (Kurz)

Vollständig: [SPEC §20–§22](./SPEC.md#20-integration--erweiterungen).

| Thema | Entscheidung |
|-------|--------------|
| **Packliste fertig** | Inbox → später **Push** an Ersteller bei `packed` — **kein SMS** |
| **Teilausgabe** | Inbox an Ersteller wenn MW Teilausgabe/«Am Event» bestätigt |
| **History/Audit** | Pack-Events + UI-Zusammenfassung; **kein** Scan-Log — [§20.2](./SPEC.md#202-history--audit-pack-journey) |
| **Nutzungs-Statistik** | aus Moves, Department — **ohne** Scans — [§20.10](./SPEC.md#2010-nutzungs-statistik-department) |
| **Scan- / QR-URL-History** | **nicht** in Aktivitäts-History — depübergreifend — [§20.11](./SPEC.md#2011-scan-history-abgegrenzung) · [scan-and-url-history.md](../../qr/scan-and-url-history.md) |
| **Letzte Scans** | nur Session unter Scan-Bar, nicht gespeichert |
| **Buchhaltung** | Completion nur Material; optional Einnahme-Vermerk im Kosten-Tab; effektive Buchung in `/accounting` — [accounting.md](../../accounting.md#zwei-abschlüsse-kernmodell) |
| **`external`** | PDF Ausgabe + Tab Kosten (Rabatt, `activity_rental`) — [§2.5](./SPEC.md#25-profil-external-vermietung) |
| **Schaden/Foto** | über Meldungen/Werkstatt — nicht neu in Journey |
| **Substitution** | Zukunft — Ersatz in Stammdaten |
| **Zeilen-Notizen** | optional, Phase 8+ |
| **Etikett beim Packen** | MW, wenn noch keins — [label-fulfillment](../../future/label-fulfillment.md) |
| **devices.** | separates Tool; Scan-Feedback etc. optional in App |
| **UX** | Virtual Scroll, Pull-refresh, Scan-Feedback — **nicht** Outdoor/i18n FR/IT |
| **Parität Legacy** | §22 **entschieden** — Shell, Accordion, QR, Retour-Stapel, Live-Sync 5s |
| **MW = Ersteller** | Kein Handoff/Notfallmodus; MW ≠ Ersteller: Notfall mit Confirm (7A) |

---

## 16. Offene / festgelegte Entscheidungen

| # | Frage | Status |
|---|-------|--------|
| 1 | Stepper dynamisch vs. fest | **entschieden:** dynamisch, keine grauen Felder |
| 2 | Schritt `plan` im Stepper | **entschieden:** **weglassen** — Planung im Tab Material |
| 3 | Material-Tab Scan | **entschieden:** **nein** — nur bestehende Suche |
| 4 | Parallel vs. Ersatz | **entschieden:** Legacy Tab `packs` + Route `pack-journey` — Rollout Phase 13 |
| 5 | Mobile-First | **entschieden:** Design ab 375px, Vollbild-Sheets — [§13](#13-parallel-entwicklung--mobile-first) |
| 6 | Bausteine schrittweise | **entschieden:** shared Rules/APIs, 1–2 UI-Komponenten/Phase — [SPEC §4.8](./SPEC.md#48-zentrale-bausteine-integrationsreihenfolge) |
| 7 | Sheet vs. Vollbild Kiste | **entschieden:** Vollbild &lt;768px |
| 8 | Journey-Route | **entschieden:** `…/pack-journey/:step?` |
| 9 | Presence v1 | **entschieden:** nein — Phase 12 |
| 10 | Nachlieferungs-Wunsch | **spezifiziert** — [SPEC §14](./SPEC.md#14-nachlieferungs-wunsch-ersteller--mw) |
| 11 | J+S eigener Tab | **tendenz:** ja — [js-material](../js-material/README.md) |
| 12 | Handoff-Banner | **entschieden** — [SPEC §19.1](./SPEC.md#191-handoff-banner--readonly-matrix) |
| 13 | «Zusammen packen» ohne Kiste | **entfällt** — direkte Zielkiste [§19.2](./SPEC.md#192-packkiste-als-ziel--ohne-intent-gruppe) |
| 14 | Transport / Touren | **entschieden** (Phase 7+) — [§19.3](./SPEC.md#193-transport--touren--department-fuhrpark) |
| 15 | Unberechtigter Zugriff | **entschieden** — Audit + Inbox [§19.4](./SPEC.md#194-unberechtigter-zugriff--audit-vs-benachrichtigung) |
| 16 | Benachrichtigungen | **entschieden** — Inbox + Push, kein SMS [§20.1](./SPEC.md#201-benachrichtigungen) |
| 17 | History/Audit | **entschieden** — Pack-Events, Aggregation, kein Scan-Log [§20.2](./SPEC.md#202-history--audit-pack-journey) |
| 18 | Nutzungs-Statistik | **entschieden** — aus Moves, Dept [§20.10](./SPEC.md#2010-nutzungs-statistik-department) |
| 19 | Scan- / QR-URL-History | **abgegrenzt** — [§20.11](./SPEC.md#2011-scan-history-abgegrenzung) · [scan-and-url-history.md](../../qr/scan-and-url-history.md) |
| 20 | Parität Legacy-Packliste | **entschieden** — [§22](./SPEC.md#22-parität-legacy-packliste--entschieden) |
| 21 | MW = Ersteller (7A) | **entschieden** — kein Handoff; MW≠Ersteller: Notfall bleibt |
| 22 | Activity vs. Material vs. Stepper | **entschieden** — [ADR-workflow-layers.md](./ADR-workflow-layers.md): Stepper = `activity.status`; Material = `quantity_*`; `pack_journey_step` entfällt |

---

## Siehe auch

- [SPEC.md](./SPEC.md) — Screens, Komponenten, API-Mapping, Datenfluss
- [Aktivitäten-Übersicht](../README.md)
- [Material-Pipeline](../material-pipeline.md)
- [Pack-Workflow-Regeln](../pack-workflow-rules.md)
