# Material-Journey — Abnahme (Phase D)

**Stand:** Juni 2026  
**Automatisiert:** `frontend/src/utils/journeyPackWorkflowAbnahme.spec.ts`  
**Todo:** [journey-pack-workflow-todo.md](./journey-pack-workflow-todo.md)

---

## Tests ausführen

```bash
cd frontend && npm install && npm test
```

Backend D5:

```bash
docker compose exec backend composer test
# oder gezielt D5:
docker compose exec backend ./vendor/bin/phpunit tests/Service/ActivityAccountingConsumptionSyncTest.php
```

`tests/bootstrap.php` aktiviert `dg/bypass-finals` (Mocks für `final`-Services).

---

## D1 — Rakokiste + Fackeln Verbrauch + lose (müüsli)

**Fixture:** `test-fixtures/journeyAbnahmeFixtures.ts` → `muusliIssuedFixtures()`

| Schritt | Erwartung |
|---------|-----------|
| Ausgabe | 5 erledigt: Rakokiste + Statikseil + Blache (+ Fackeln in Kiste) |
| Retour | Offen: Rakokiste + Statikseil + Blache — **keine** lose Fackeln-Zeile |

**Manuell:** Aktivität mit Rakokiste befüllt → Journey Ausgabe → Retour; Zähler-Hinweis prüfen.

---

## D2 — Teilmenge in Kiste (7+3)

**Fixture:** `partialCrateSevenThreeFixtures()`

| Erwartung |
|-----------|
| Kiste sichtbar mit 7 Stück (`qtyInContainersForItem`) |
| Lose Zeile «Testartikel» offen; `looseQtyForPackItem` = 3 |

---

## D3 — Quick Teilausgabe → `at_event`

| Aktion | Erwartung |
|--------|-----------|
| Teilausgabe, Status noch `packed` | Aktiver Checkpoint = Ausgabe |
| «Habe nur das mitgenommen» | Status → `at_event`, Checkpoint = Retour |
| «Retour bringen» | Nur Navigation, kein Statuswechsel |

---

## D4 — Camp/Event Transport-Kette

**Fixtures:** `logisticsAtEventFixtures()`, `logisticsTransportBackFixtures()`

| Status / Schritt | Erwartung |
|------------------|-----------|
| `at_event` | Aktiver Stepper = **Am Anlass** (nicht Retour) |
| Transport zurück | Kistencheck-Bein `return` |
| Retour nach Transport | Kisten retournierbar sichtbar |

**Manuell:** Camp/Event durchklicken: Transport hin → Anlass → Touren → Transport zurück → Retour.

### D4 Bug-Liste (Pfila 2026, Aug 2026)

| Prio | Bug | Status |
|------|-----|--------|
| **P0** | Bulk «Alles zurück… ist da» bucht nicht `quantity_returned` | ✅ fix (`STAGE_RETURNED`) + Re-Check |
| **P0** | «Weiter» bei `packed` zielt `at_event` (API 422) | ✅ fix → zuerst `transport_out` |
| **P1** | Tour-Item desync vs. `quantity_transport_*` | ✅ fix (qty++ / Rollback) |
| **P2** | Kein Fahrzeug im Fuhrpark | bewusst offen (Abnahme-Hindernis, kein Kern-Bug) |
| kosmetisch | Stepper A11y Doppelnummer «22.» | ✅ fix (ein `aria-label`) |

---

## D5 — Verbrauch ohne MW-Auftrag vor Retour

| Erwartung |
|-----------|
| Verbrauch **melden** ab `at_event` möglich |
| Buchhaltungs-Auftrag Verbrauch erst ab `returned` / `storing` / `completed` |

---

## Ergebnis-Protokoll

| ID | Auto-Test | Manuell | Datum | Notizen |
|----|-----------|---------|-------|---------|
| D1 | ✅ spec | ✅ | 2026-06-30 | Aktivität **müüsli** (Hardscout). Ausgabe: 5/5 mitgenommen, Zähler «1 in Kisten · 1 Verbrauch · 3 lose». Retour: 0/3 (Kiste + Statikseil + Blache), Zähler «1 in Kisten · 2 lose». Fackeln lose-Zeile sichtbar weil DB `quantity_issued=5` lose + 10 in Kiste (Abweichung vom Spec-Fixture, kein reiner 100 %-in-Kiste-Fall). |
| D2 | ✅ spec | — | | Keine Test-Aktivität «7+3» in DB; nur Spec. |
| D3 | ✅ spec | ✅ | 2026-06-30 | **humpä** / **müüsli** `at_event`: «Retour bringen» → nur `packStep=return`, Status bleibt `at_event`. Retour-Schritt aktiv. Ausgabe-Ansicht read-only. |
| D4 | ✅ spec | ✅ | 2026-08-03 | Pfila 2026: volle Kette bis `storing` + `quantity_stored` (10+5). P0/P1 gefixt. Abschluss-Rest = Kosten freigeben → **#7**. |
| D5 | ✅ spec + PHPUnit | ✅ | 2026-06-30 | «Gebraucht» auf Ausgabe ab `at_event` (müüsli). Buchhaltung: `activity_consumption` pending in DB — prüfen ob aus früherem Durchlauf; `syncConsumptionFollowUp` sync erst ab `returned`. |
