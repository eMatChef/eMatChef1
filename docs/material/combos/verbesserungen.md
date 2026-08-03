# Kombos — Verbesserungen (Restarbeit)

**Stand:** August 2026  
**Status:** Pakete 0–8 erledigt · **1d A+B (C1–C7) erledigt** · Stammdaten **C8–C12 erledigt**  
**Eingegliedert in:** Top-10 **#1** Slice **1d** (Kern #1 erledigt; Stammdaten-Rest geschlossen) — [pack-steps-spezifikation.md](../../activities/pack-steps-spezifikation.md)

Konzept & Ist: [README.md](./README.md) · Pack-Flow: [virtual-combo-activities.md](./virtual-combo-activities.md) · Plan 0–8: [plan.md](./plan.md)

---

## Ausgangslage

| Bereich | Stand |
|---------|--------|
| **Erstellen** (Wizard, Detail, Optionen, Draft→Ready) | weitgehend fertig |
| **Buchen** (Konfigurator, `pack_mode`, Combine, Set-Anzeige) | weitgehend fertig |
| **Packliste Legacy** (together/loose, `self_provided`-Hinweis) | weitgehend fertig |
| **Journey / Scan / Nachbearbeitung** | ✅ C1–C7 (Aug 2026) |

---

## Priorisierte Restarbeit

### A — Journey & Packen (Teil von #1 / 1d)

| ID | Thema | Warum | Aufwand |
|----|--------|-------|---------|
| **C1** | Journey: virtuell `together` als echte Set-Zeile + Sheet (ohne Shell-Batch) | Logische Container haben keinen `container_batch_id` / Shell-Pack-Item; `MaterialCrateCheckSheet` bricht ab. SPEC will `virtual_crate` | ✅ Aug 2026 |
| **C2** | Scan `in_virtual_crate` reparieren | Resolve matched falsch (`source_activity_item_id` vs. Pack-Item); öffnet ggf. Phys-Sheet statt Set | ✅ Aug 2026 |
| **C3** | `self_provided` in der Journey | Legacy zeigt «Vom Leiter mitzubringen»; Journey fehlt Block + Ack bei Ausgabe | ✅ Aug 2026 (Hinweis + Issue-Checkbox session) |
| **C4** | Badge/Kind `virtual_crate` vs. echte Packkiste | MW soll Set von Rakokiste unterscheiden | ✅ Aug 2026 |

### B — Nachbearbeitung in der Aktivität (Teil von #1 / 1d, nach A)

| ID | Thema | Warum | Aufwand |
|----|--------|-------|---------|
| **C5** | Konfiguration nachträglich ändern (Menge + Optionen) | Eltern-Menge/Optionen nach Buchung hart gesperrt; falsches Set = neu anlegen. Backend re-expandiert schon bei Sync | ✅ Aug 2026 (Menge + «Konfiguration ändern») |
| **C6** | Set-Inhalt auch bei `pack_mode=loose` anzeigen | Sonst kein Audit der BOM nach Buchung | ✅ Aug 2026 |
| **C7** | Verfügbarkeit / Rest auf Eltern-Zeile | «Noch X× dieses Sets» fehlt auf der Kombo-Zeile | ✅ Aug 2026 |

### C — Erstellen / Stammdaten (nicht Blocker für #1; eigener Slice möglich)

| ID | Thema | Warum | Aufwand |
|----|--------|-------|---------|
| **C8** | Warnung bei BOM-Edit wenn Kombo in offenen Aktivitäten | Sonst veralten `config_snapshot`s | ✅ Aug 2026 |
| **C9** | Draft zurück / Unfinalize nach BOM-Änderung | Finalize ist one-way; Live-BOM-Risiko | ✅ Aug 2026 (`unfinalize-combo` + UI) |
| **C10** | Wizard→Detail Handoff | Deep-Link Zusammensetzung + Checkliste vor Finalize | ✅ Aug 2026 |
| **C11** | System-Vorlagen als Konfigurator-Inhalte | Editor da; JSON oft noch flach (z. B. Sarasani) | ✅ Aug 2026 (Phoenix-Beispiel + Sarasani=`combo`) |
| **C12** | Doku-Cleanup README (veraltete «offen»/«Lücke») | Verwirrt Folgearbeitschats | ✅ Aug 2026 |

### Bewusst später / optional

| Thema | Hinweis |
|-------|---------|
| Direktbuchungs-Schutz (B) | Entscheidung A bleibt Default — Komponenten einzeln buchbar |
| Packen nach Gestell | eigenes Backlog, nicht Kombo |

---

## Empfohlene Reihenfolge

```
C1 + C2   Journey together nutzbar (Sheet + Scan)
 → C3 + C4  self_provided + Badge
 → C5 + C6 + C7  Nachbearbeitung / Lesbarkeit
 → C8–C12   Stammdaten / Vorlagen / Docs (nach #1 oder parallel)
```

---

## Definition of Done — Slice 1d (Kombos in #1)

1. ✅ Virtuell `together` in der **Journey** wie eine Packkiste pack-/checkbar (Sheet ohne Shell-Crash).
2. ✅ Scan eines Teils im Set öffnet das **richtige** Set-Sheet.
3. ✅ `self_provided` in Journey sichtbar (Ersteller/MW) mindestens analog Legacy.
4. ✅ C5–C7: Menge/Optionen änderbar (vor Packstart), Set-Inhalt auch bei `loose`, Rest «Noch X×» auf Eltern-Zeile.

Stammdaten C8–C12 schliessen **1d** nicht; sie können danach oder in einem Stammdaten-Chat laufen.

---

## Chat-Prompt (1d)

```
Thema: Top-10 #1d — Kombos in Journey / Aktivität

Ziel:
- Virtuelle Kombo pack_mode=together in der Material-Journey nutzbar machen (Set-Zeile + Sheet ohne Shell-Crash)
- Scan in_virtual_crate reparieren (richtiges Set öffnen)
- self_provided in der Journey analog Legacy (Hinweis + ggf. Ack bei Ausgabe)
- Danach optional: Badge virtual_crate; Nachbearbeitung Menge/Optionen (C5–C7) nur wenn C1–C3 grün

Kontext:
- docs/activities/pack-steps-spezifikation.md  (Slice 1d)
- docs/material/combos/verbesserungen.md       (C1–C12, DoD)
- docs/material/combos/virtual-combo-activities.md
- docs/activities/newUI/SPEC.md               (§5.3 virtual_crate / self_provided)
- docs/activities/pack-workflow-rules.md

Ist: Pakete 0–8 fertig; Journey C1–C7 fertig; Stammdaten C8–C12 fertig.

Nicht anfassen ohne Rückfrage:
- Abrechnung (#7) / Pack-Steps 1c
- Offline / Scanseite devices (#2), Geräte-PIN (#3)
- Pack-Steps 1a Logistics-Abnahme (eigener Chat)

Vorgehen:
1. Ist-Stand Journey vs. Legacy für together / self_provided / Scan kurz belegen
2. C1+C2 spezifizieren und umsetzen
3. C3+C4
4. Stopp / Rückfrage vor C5–C7
```

---

## Code-Einstieg

| Thema | Ort |
|-------|-----|
| Buchungs-Konfigurator | `ComboConfiguratorDialog.vue` |
| Materialliste / pack_mode | `ActivityMaterialLinesTable.vue`, `virtualComboMaterial.ts` |
| Container-Sync | `ActivityController` (`syncVirtualComboPackContainers`, `expandVirtualComboLine`) |
| Journey Tasks / Sheets | `materialJourneyTaskList.ts`, `MaterialCrateCheckSheet`, Journey SPEC §5.3 |
| Pack-Regeln | `packWorkflowRules.ts` |
| Anzeige-Helfer | `comboDisplay.ts` |

---

## Siehe auch

- [virtual-combo-activities.md](./virtual-combo-activities.md) — Pack-Flow Spec (Paket 8)
- [activities/newUI/SPEC.md](../../activities/newUI/SPEC.md) — Journey `virtual_crate`, `self_provided`
- [pack-workflow-rules.md](../../activities/pack-workflow-rules.md) — Placement together/loose
