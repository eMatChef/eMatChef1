# Abnahme A — Nass / Trocknen (laufendes System)

**Umgebung:** `https://app.ematchef.test` (Docker lokal)  
**Voraussetzung:** Migration `Version20260810210000` ist auf DB (**erledigt**).  
**Rolle:** MW (Materialwirtschaft)  
**Testdaten:** Aktivität **Fadä** `#004` (`e763c9393b2c`) — 11.08.2026

## Ergebnis

| Schritt | Erwartung | Status |
|--------|-----------|--------|
| Nass setzen (3 Blache nicht aufgehängt, 2 Seil aufgehängt + Ort) | API/UI speichert `qty_wet` | ✅ |
| MW-Inbox | `activity_material_wet_not_hung` für Blache A (1×) | ✅ |
| Einlagern-UI | Sektion **Nass / zum Trocknen** nur wenn nass | ✅ |
| Aufgehängt-Anzeige | «Noch aufhängen» / «Aufgehängt zum Trocknen · Ort» | ✅ |
| Mengen in Nass-Sektion | «n nass» (nicht trockene Offen-Menge) | ✅ |
| Trocken verräumen | Rest ohne Nass einlagerbar | ✅ |
| Complete trotz Nass | Panel «Trocken eingelagert — Nass noch offen» + CTA | ✅ |
| Abschluss → Tickets | Cleaning «Trocknen / Einlagern: …» open | ✅ |
| Post-Complete Queue | Banner + Nass-Sektion weiter bedienbar | ✅ |
| `from_wet` Einlagern | `qty_wet` → 0; Nass-Sektion weg | ✅ |
| Ticket erledigen | nach Nass-Einlagerung `open→in_progress→completed` | ✅ |

## UI-Hinweis Aufgehängt

- Nur in Sektion **Nass / zum Trocknen** (nur wenn `qty_wet` > 0)
- Aufgehängt → «Aufgehängt zum Trocknen» (± Standort)
- Nicht aufgehängt → «Noch aufhängen»
- Kein Nass → keine Aufgehängt-Anzeige / keine Sektion

## Checkliste

- [x] Retour/Disposition Nass (Kiste + lose; hier lose via API auf retournierten Positionen)
- [x] Aufgehängt → Ort/Label
- [x] Nicht aufgehängt → MW-Inbox
- [x] Nass-Sektion + Aufgehängt-Text + «n nass»
- [x] Complete trotz Nass → Ticket
- [x] Store-from-wet → Ticket erledigt
- [ ] Optional: Retour-UI Regentropfen manuell tippen (Kiste+lose) noch einmal visuell durchklicken
