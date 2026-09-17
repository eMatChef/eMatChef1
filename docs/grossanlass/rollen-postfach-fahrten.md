# Grossanlass — Rollen, Postfach, Fahrten

Produktklärung 30.08.2026. **Dieses Dokument gilt**, wo es von [README §3.6 / §17](./README.md#36-einstellungen-grossanlass-dept) (nur MW/DC/U + Leader/Member) oder [Konzept §7 / §12](./20260823_New_concept.md) (ein `canManagePlanung` für Gmail, Fahrer nur an der Karte) abweicht.

**Status:** Soll — Phase R1–R7 umgesetzt. Checkboxen in [§11](#11-phasen-abarbeiten) abhaken.

**Verwandt:** [README.md](./README.md) · [20260823_New_concept.md](./20260823_New_concept.md) · [kosten.md](./kosten.md)

---

## 1. Satz Zielbild

Am Grossanlass arbeiten nicht nur Materialwart und Ressort-Mitglieder. Es braucht **wenige Zugriffsstufen**, **Heimat im Baum** und **Fahren als Funktion**. Ein **gemeinsames Gmail** (Label `eMatChef`). **Wünsche** an Ressort/Bauprojekt werden zu **Einsätzen** (Material oder Werkzeug). Ein Einsatz **kann** Fahrauftrag sein — nur wenn die Checkbox **Fahrt** gewählt ist (sonst **Selbst abholen**). Pack/Palette hat den QR, Ziel-Scan setzt den Standort. Packen und Fahren dürfen **unvollständig** sein (Warnung); nach Teilpack + MW-Freigabe ist der **Materialplatz schon leer**, Fahrt auch vor `starts_at`. Werkzeug nur im Zeitfenster am Ziel. Dashboard: Box **Fahraufträge** (Klick → Liste).

---

## 2. Drei Ebenen (nicht sieben Department-Rollen für Jobs)

| Ebene | Werte | Speicherung |
| --- | --- | --- |
| **Zugriffsstufe** | MW, CMW, OK-Leitung, Kommunikation, Sponsoring, Helfer | `membership.role` |
| **Bereich** | Bereichsleitung (Leader) oder Mitglied am Knoten | `group_membership.role` = `leader` \| `member` am Ressort / Unterressort / Bauprojekt |
| **Fahren** | keine Rolle | Profil (Ausweise) + User-Karte (Freigabe **dieser** Anlass) |

Bereichsleitung ist **kein** Dept-Flag. Dieselbe Person kann Helfer in Bau und Leader an «Bühne» sein. Fahren kann jede Stufe haben.

**Nicht tun:** `fahrer`, `helfer`, `bauprojekt` als `DepartmentRole`. Bauprojekt bleibt `group.grossanlass_kind`. Helfer = `u` + Membership im Baum.

---

## 3. Rollen

### 3.1 MW (`mw`)

Alles: Struktur, Schichten, Ausgabe, Einsätze, Konflikte, Wünsche, Beschaffung, Budget, Benutzer, Gmail verbinden, **Anfrage-Welle Entwürfe**, **Senden**, nehmen/nicht nehmen, Fahrt freigeben, Fahraufträge.

### 3.2 CMW (`cmw`) — neu

Betrieb wie MW, ohne Kampagnen-Start und ohne Mailbox-Hoheit:

- Struktur und Schichten
- Ausgabe, Einsätze, Konflikte, Wünsche: sehen, bearbeiten, ablegen
- Postfach: Label `eMatChef` inkl. neuer Mails (MW abwesend)
- **Nehmen / nicht nehmen** → App-Status + Folge-**Entwurf** im gemeinsamen Postfach
- **Keine** Wellen-Aktion «Entwürfe erzeugen»
- **Kein** Senden (Draft bleibt Draft, bis MW sendet)
- **Kein** Gmail OAuth verbinden/trennen
- Fahrt freigeben, Pack/Palette, Scan

### 3.3 OK-Leitung — bestehende Rolle `dc`, Label im Grossanlass umbenennen

Anlassweit, nicht nur eigener Stamm:

- Einsätze, Material, Wünsche: alles sehen
- Struktur / Bauprojekte anlegen (Wurzeln und Kinder)
- Einsätze der Bereichsleitung **freigeben** (`pending_approval` → Einsatz frei)
- **Kein** Postfach, kein Nehmen, kein Senden, keine Fahrt-Freigabe, keine Ausgabe-Keys

Heute ist `dc` über `canManagePlanung` fast gleich MW — Rechte **runter** auf Sicht + Struktur + Einsatz-Freigabe.

### 3.4 Bereichsleitung — `group_membership.role = leader`

Scope = dieser Knoten plus Kinder (Ressort **oder** Unterressort **oder** Bauprojekt).

- Übersicht Einsätze / Material / Wünsche nur dort
- Einsatz **einreichen** → `pending_approval`
- Nach Freigabe durch OK-Leitung / MW / CMW gilt der Einsatz; Ausgabe und Fahrt bleiben MW/CMW
- Unter sich Kinder anlegen; keine Wurzel-Ressorts; kein Löschen fremder Äste

### 3.5 Kommunikation (`komm`) / Sponsoring (`spon`) — neu

Kein Material-Kommando. **Dept-Rolle**, nicht «Helfer im Ressort Kommunikation» (das gibt kein Postfach).

- Vorlagen sehen und bearbeiten
- Dasselbe Postfach: Label `eMatChef`
- Ablegen / Labels
- **Kein** Nehmen, **kein** Senden, **kein** Wellen-Draft, **kein** OAuth, **kein** Firma-nehmen

### 3.6 Helfer (`u` + Group-Membership)

Sieht nur den Baum, dem er zugeteilt ist. Eigene Schichten, eigene Fahrten (wenn zugewiesen), QR-Karte. Keine Einsätze einreichen.

### 3.7 Homes (Sidebar)

| Rolle | Default | Extra |
| --- | --- | --- |
| MW / CMW | Dashboard | CMW ohne Entwürfe-Welle, OAuth, Benutzer-Gefahr |
| OK-Leitung | Übersicht Anlass | Struktur, Freigabe-Queue, Material, Wünsche — kein Postfach |
| Bereichsleitung | Mein Bereich | gefilterte Einsätze/Wünsche, «Einsatz einreichen» |
| Komm / Spon | Anfragen (ohne «Entwürfe erzeugen») + Vorlagen | Label `eMatChef` |
| Helfer | Mein Bereich (Struktur) | Meine Fahrten nur bei zugewiesenem Pack/Fahrt; Dashboard-Box wenn Fahrt-Einsätze |
| + Fahrfunktion | Profil-Accordion + Karte | Scan Pack/Ziel; Dashboard **Fahraufträge** |

### 3.8 UI: wo setze ich was?

Zwei Orte, nicht alles im Ressort-Dropdown.

**A — `/einstellungen/ressorts` (Baum + Mitglieder)** = Heimat im Knoten

Heute: Bleistift an der **Zeile** bearbeitet das Ressort (Name/Parent), nicht die Person. Mitglieder = Avatare; **Leader** = Stern oben rechts an `UserAvatarBadge` (`show-leader-star`). Mitglieder-Dialog (Icon Person+): Select Leader / Mitglied.

Soll:

- Am **Avatar** (Liste und Mitglieder-Dialog) kleine Eck-Icons, klickbar bzw. nach Bleistift an der **Person**:
  - **Leader (Bereichsleitung)** — Stern, Ist schon oben rechts. Genau diese Markierung.
  - **Primary (Stamm-Knoten)** — zweites Icon **unten rechts** (Heimat, wenn jemand in mehreren Knoten ist). Ist in Pfadi-Gruppen als Badge, im Grossanlass-Tab noch nicht am Avatar.
- Im Mitglieder-Dialog: Leader und Primary umschalten (nicht nur Select). Kein drittes Select «CMW».
- Helfer = Mitglied ohne Leader-Stern.

**B — Einstellungen → Benutzer** (Bleistift am User) = Zugriffsstufe für den **ganzen** Anlass

Eine `membership.role`: MW, CMW, OK-Leitung, Kommunikation, Sponsoring, Helfer (`u`). Accordion «Mitgliedschaft» wie heute Rolle + Primary-Dept.

Diese Stufen **nicht** pro Ressort wählen (sonst wäre jemand in Bau CMW und in Küche Helfer).

**Anzeige am Ressort-Avatar (nur lesen):** wer CMW / OK-L / Komm / Spon ist, darf ein **zusätzliches** Mini-Icon am Avatar haben (z. B. unten links), gesetzt bleibt unter Benutzer.

**Fahren:** kein Icon «Fahrer» als Rolle. Ausweise = Profil-Accordion; Anlass-Freigabe = User-Karten.

```
Avatar
  oben rechts   ★  Bereichsleitung (Leader)
  unten rechts  ⌂  Primary in diesem Knoten
  unten links   optional Dept-Stufe (CMW / Komm / …) — gesetzt unter Benutzer
```

**Mitglieder-Liste = gleiche Zeile wie Benutzer.** Panel «Mitglieder» unter Ressorts ist heute nur Avatar + Name + Knoten (keine Aktionen). **Soll:** dieselbe Interaktion wie Einstellungen → Benutzer (`UsersSettingsView`):

- **Details** → dasselbe User-Modal (Profil, Dept-Rolle MW/CMW/OK-L/Komm/Spon/Helfer, Accordion Fahrausweise später)
- **Entfernen** (Person-minus) → **Warnung/Confirm**, dann raus aus dem **Grossanlass-Department** (nicht still nur aus einem Knoten)
- Aktionen sichtbar wie in der Benutzer-Tabelle (Hover/Zeile); nicht nur für `u`, auch für MW/CMW soweit `canManageMember` es erlaubt
- Aus einem **einzelnen** Ressort nehmen bleibt im Knoten-Dialog (Person+ an der Ressort-Zeile), mit eigener Confirm-Warnung

Die beiden Listen sollen gleich *bedienbar* sein. Unterschied nur die zweite Spalte/Unterzeile: bei Ressorts die Knoten («BL Wasser und Sanitär»), bei Benutzer E-Mail + Rollen-Badge.

**Codeweit (nicht in den Views duplizieren):**

- Rechte + Entfernen mit Confirm: `frontend/src/composables/useDepartmentMemberAdmin.ts` + `frontend/src/utils/departmentMemberRoles.ts`
- Zeile / Aktionen / Detail-Dialog: `frontend/src/components/members/` (`DepartmentMemberRow`, `DepartmentMemberActions`, `DepartmentMemberDetailDialog`)
- Nutzer: `UsersSettingsView` (Benutzer-Tabelle), `GrossanlassRessortsTab` (Panel Mitglieder + Details im Knoten-Dialog). Aus einem Knoten nehmen bleibt `removeGrossanlassGroupMember`.

---

## 4. Berechtigungs-Matrix (Soll)

| Aktion | MW | CMW | OK-L | Bereich | Komm/Spon | Helfer |
| --- | --- | --- | --- | --- | --- | --- |
| Struktur Wurzel | ✓ | ✓ | ✓ | — | — | — |
| Struktur Kinder am eigenen Knoten | ✓ | ✓ | ✓ | ✓ | — | — |
| Einsatz einreichen (eigener Baum) | ✓ direkt frei | ✓ direkt frei | ✓ direkt frei | ✓ → pending | — | — |
| Einsatz **freigeben** | ✓ | ✓ | ✓ | — | — | — |
| Packen / Teilpacken | ✓ | ✓ | sehen | eigenen Baum sehen | — | Pack scannen wenn zugewiesen |
| **Fahrt** freigeben | ✓ | ✓ | — | — | — | — |
| Ausgabe / Keys / Konflikte | ✓ | ✓ | sehen | eigenen Baum | — | — |
| Wünsche alle / eigener Baum | alle | alle | alle | eigener | — | sehen zugeteilt |
| Postfach Label `eMatChef` | ✓ | ✓ | — | — | ✓ | — |
| Vorlagen | ✓ | ✓ | — | — | ✓ | — |
| Nehmen / nicht nehmen | ✓ | ✓ | — | — | — | — |
| Wellen-Entwürfe erzeugen | ✓ | — | — | — | — | — |
| Senden | ✓ | — | — | — | — | — |
| Gmail verbinden | ✓ | — | — | — | — | — |
| Fahrausweise bestätigen (Karte) | ✓ | ✓ | — | — | — | eigenes Profil pflegen |

Backend: `canManagePlanung` **nicht** mehr für alles. Mindestens:

| Check | Wer |
| --- | --- |
| `canWorkMailbox` | mw, cmw, komm, spon |
| `canTakeInquiry` | mw, cmw |
| `canCreateMailDrafts` (Welle) | mw |
| `canSendMail` | mw |
| `canConnectGmail` | mw |
| `canApproveEinsatz` | mw, cmw, dc |
| `canReleaseTrip` | mw, cmw |
| `canSubmitEinsatz` | Leader im Knoten; mw/cmw/dc direkt frei |

---

## 5. Postfach — ein Konto, Label `eMatChef`

Ein OK-Gmail (`DepartmentGrossanlassGmailAccount`). In der App **nur** Threads mit Wurzel-Label **`eMatChef`** (Default schon `GrossanlassGmailRouting::DEFAULT_ROOT`). Unterlabels Firmenanfragen/Status bleiben. Mail ohne dieses Label erscheint nicht in der App.

MW, CMW, Kommunikation, Sponsoring sehen **dieselbe** gefilterte Inbox.

### 5.1 Nehmen vs. Welle vs. Senden

| Schritt | Wer | Was passiert |
| --- | --- | --- |
| Nehmen / nicht nehmen | MW, CMW | Status in der App (Commitment bei Zusage); **Folge-Entwurf** (Vorlage Nehmen / Nicht genommen) im Postfach unter `eMatChef` |
| «Entwürfe erzeugen» (Anfrage-Welle) | nur MW | Gmail-Drafts für Prospects |
| Senden | nur MW | Draft in Gmail wirklich raus (Button nur `mw`, oder MW sendet in Gmail) |

CMW-Nehmen erzeugt **keinen** Wellen-Draft, sondern die Entscheidungs-Mail. Komm/Spon weder nehmen noch senden.

Ist: `GrossanlassGmailAccountService` split: `assertMailbox` / `assertTakeInquiry` / `assertCreateMailDrafts` / `assertConnectGmail`; Senden über `GrossanlassInquiryService::assertSend`. Checks §4.

---

## 6. Fahren = Funktion

Jeder kann fahren (Helfer, Bereichsleitung, CMW, …). Zwei Speicher:

**Profil — Accordion «Fahrausweise»** (user-weit, überlebt den Anlass)

- Klassen B, BE, C1, Stapler R/S, Kran, … — Katalog existiert (`grossanlassDriveCategories.ts` / `GrossanlassDriveCategories`)
- Scan/Foto, Gültig-bis
- User pflegt selbst

**User-Karte am Grossanlass** (schon da: `may_drive`, `drive_classes`, `drive_verified`)

- MW/CMW bestätigt für **diesen** Anlass
- Ausgabe und Fahrt nur wenn Karte verifiziert **und** Klasse passt
- Profil allein reicht nicht zum Losfahren

---

## 7. Einsatz aus Wunsch — Fahrt ist eine Checkbox

Kette, nicht zwei Module:

```
Ressort / Unterressort / Bauprojekt
    → Wünsche (Material oder Werkzeug)
    → Einsatz (Reservation: was, Menge, von–bis, wohin)
    → Zustellung: Checkbox Fahrt  XOR  Checkbox Selbst abholen
```

`kind: order` in der Materialübersicht bleibt **Nachbedarf**, nicht die Fahrt. Keine parallele Fahrauftrag-Tabelle.

| Checkbox | Was der Einsatz ist | Fahrt-Frei / Fahrer / Pack-QR |
| --- | --- | --- |
| **Fahrt** | Dieser Einsatz **ist** der Fahrauftrag | ja — Fahrzeug, Fahrer (`chauffeur_user_id`), Pack/Palette, Ziel |
| **Selbst abholen** | Ausgabe am Materialplatz | nein — Abholer holt selbst; kein Dashboard-Fahrauftrag |

Genau eine der beiden. Nicht «jeder Einsatz fährt».

```
Einsatz «Bühne» (aus Wunsch Holz / Akkuschrauber)
  Zustellung: Fahrt  oder  Selbst abholen
  Zeilen mit eigenem von–bis
  wenn Fahrt: Pack/Palette (QR) = was wirklich auf der Fahrt liegt
              Fahrer mit bestätigter Klasse
              Ziel = Substandort mit QR
```

### 7.1 Zwei Freigaben (nur bei Fahrt)

1. **Einsatz frei** — OK-Leitung / MW / CMW (von `pending_approval` oder direkt). Gilt für Fahrt **und** Selbstabholung.
2. **Fahrt frei** — nur MW/CMW, **nach** Pack (auch Teilpack). Nur wenn Checkbox Fahrt. Ohne Fahrt-Frei kein Losfahren.

OK-Leitung gibt Einsätze frei, **nicht** Fahrten.

### 7.2 Ablauf

**Selbst abholen**

```
Einsatz frei
    → packen / bereitstellen (Teilpack = Warnung)
    → MW gibt Ausgabe frei
    → Materialplatz gilt als leer (Position nicht mehr lagernd)
    → Abholer nimmt am Platz
```

**Fahrt** (Checkbox)

```
Einsatz frei
    → packen, sobald etwas im Mat liegt (unvollständig = Warnung)
    → Pack/Palette bekommt QR (für das, was drauf ist)
    → Fahrt frei (dieses Pack) — MW/CMW
    → Materialplatz gilt als leer, auch wenn noch nicht gefahren
         und auch vor starts_at
    → Pack-QR scannen → unterwegs (Vorholen, Firma abholen, früher als Ist)
    → Ziel-QR scannen → Standort dieses Packs = dieser Substandort
```

Blocker für **Start der Fahrt:** kein Haken Fahrt · Einsatz nicht frei · Fahrt nicht frei · kein Ziel-Standort · Fahrer ohne passende Karten-Freigabe.  
**Nicht** Blocker: unvollständige Packliste (Warnung) · `starts_at` noch in der Zukunft · Materialplatz noch «voll» in der Realität, wenn MW Teilpack + Fahrt frei gesetzt hat (Soll-Bestand am Platz ist dann schon weg).

Falscher Ziel-QR: nicht verbuchen.

### 7.3 Materialplatz leer vor der Fahrt

Teilpacken + MW-Freigabe (**Fahrt frei** bzw. bei Abholung Ausgabe frei) zieht die Menge **sofort** vom Materialplatz. Der Platz darf in der Übersicht schon leer / reduziert sein, **bevor** jemand fährt oder abholt. Zweck: Holz darf raus und der Platz ist frei, auch wenn Schrauben fehlen und die Fahrt erst später startet.

### 7.4 Dashboard — Box Fahraufträge

Wenn Einsätze mit Checkbox **Fahrt** existieren: auf dem Grossanlass-**Dashboard** eine klickbare Box **Fahraufträge** (Anzahl offener Aufträge).

- Klick → Liste dieser Einsätze (gefiltert: Fahraufträge), nicht ein neues Modul
- Fahrer / Helfer mit zugewiesener Fahrt: eigene Aufträge
- MW / CMW: alle offenen Fahraufträge (Dispatch)
- Checkbox **Selbst abholen**: erscheint **nicht** in dieser Box

Kein Sidebar-Punkt «Fahrten» neben der Materialübersicht.

### 7.5 Teilpacken mit Warnung

Kein hartes «alles da, sonst kein Pack». Sperriges muss raus können, während Kleinteile fehlen.

- Pack/Ausgabe erlaubt, sobald **mindestens eine** Position bereit ist
- Warnung sichtbar, z. B. «Holz 40× gepackt, Schrauben noch nicht im Mat»
- Paletten-QR am **tatsächlichen** Pack, nicht erst bei 100 % grün
- Offene Zeilen: Nachfahrt / zweite Palette / gleicher Einsatz «noch nachliefern»
- Fahrt-Freigabe gilt für **dieses Pack**, nicht «ganzer Einsatz komplett»

Bereichsleitung sieht den Warnstatus (Holz unterwegs, Schrauben offen). Kein stilles Vollständig.

### 7.6 Zeitfenster pro Zeile

Werkzeug, das nur am Samstag braucht wird, darf nicht die ganze Woche am Ziel liegen.

- Packzeile (oder Kind-Einsatz) hat eigenes `von–bis` (Wunsch `valid_from`/`valid_to`, Einsatz `starts_at`/`ends_at`, `pack_phase` Aufbau/Anlass als grobe Stufe)
- Holz Fr–So kann Freitag fahren; Akkuschrauber nur Sa = eigene Zeile, erst dann packen/fahren
- Ziel-Scan bewegt **diesen** Pack; Zeilen, die noch im Mat liegen, ändern den Standort nicht

### 7.7 QR

| QR | Träger | Scan |
| --- | --- | --- |
| **Pack / Palette** | Etikett am realen Pack | Fahrt übernehmen / unterwegs (Identität des Einsatz-Packs) |
| **Substandort** | Schild am Ort (Bühne, Küche Nord, Tor, Unterlager) | Standort des Packs wird dieser Ort |
| **User-Karte** `/i/c/…` | schon da | Ausgabe / «darf fahren» |

Neu z. B. `/i/p/:placeCode` für Substandorte. Orte: Event-POI / Zustellpunkt, Unterlager, Bauprojekt-Knoten mit Ort — ein Typ **Grossanlass-Ort** mit `public_code` reicht.

Zweite Palette = zweites Pack (eigener QR), gleicher Einsatz oder Kind.

---

## 8. Ist vs. Soll

| Thema | Ist | Soll |
| --- | --- | --- |
| Dept-Rollen GA | mw, dc, u | + `cmw`, `komm`, `spon`; `dc` = OK-Leitung (eingeschränkt) |
| Gmail-Recht | `canManagePlanung` (mw+dc) | Mailbox / nehmen / Welle / senden getrennt §4 |
| Label-Wurzel | `eMatChef` Default | App-Inbox = nur dieses Label |
| Nehmen | wer planen darf | mw+cmw; Komm/Spon nein |
| Wellen-Drafts | wer planen darf | nur mw |
| Fahrer | Klassen an der Karte | + Profil-Accordion; Karte = Anlass-Freigabe |
| Einsatz-Status | `pending_approval` u. a. | Einsatz-Frei; **Fahrt-Frei** nur wenn Checkbox Fahrt |
| Zustellung | — | Checkbox **Fahrt** XOR **Selbst abholen**; nur Fahrt = Fahrauftrag |
| `kind: order` | Nachbedarf | bleibt Nachbedarf, **nicht** Fahrt |
| Chauffeur | Feld am Einsatz | nur bei Fahrt: Einsatz + Pack-QR + Ziel-Ort |
| Materialplatz | Bestand bis Ausgabe | leer schon nach Teilpack + MW-Freigabe, vor Fahrt/`starts_at` |
| Dashboard | Kennzahlen Beschaffung | Box **Fahraufträge** (Klick → Liste), nur Einsätze mit Fahrt |
| Pack | Checkbox `packed` am Commitment | Zeilen, Teilpack+Warnung, Palette-QR, Standort nach Ziel-Scan |
| Substandort-QR | — | Ort mit `public_code`, Scan setzt Standort |
| Mein Ressort | User sieht eigenen Baum | Bereichsleitung + Helfer Homes; OK-L anlassweit |

---

## 9. Datenmodell (Ziel, knapp)

Keine parallele «Fahrauftrag»-Tabelle. Fahrauftrag = Einsatz mit Checkbox Fahrt. Ergänzungen am bestehenden `department_grossanlass_einsatz` (und Pack-Zeilen):

```
einsatz
  status                 pending_approval | planned (einsatz frei) | …
  delivery               trip | pickup     — Checkbox Fahrt XOR Selbst abholen
  trip_released_at       nullable     — Fahrt frei (mw/cmw), nur wenn delivery=trip
  chauffeur_user_id      schon da     — nur trip
  pack_id / public_code  Palette/Pack-QR (1 Einsatz : n Packs möglich)
  current_place_id       Materialplatz leer nach Teilpack+MW-Frei; Ziel nach Scan

pack_line
  einsatz_id / pack_id
  commitment oder wish
  valid_from, valid_to   Zeile nur in diesem Fenster am Ziel nötig
  qty_packed vs qty_needed
  missing → Warnung, kein hartes Lock

place (Substandort)
  public_code            /i/p/…
  group_id?              Bauprojekt / Unterlager
```

Profil: Ausweis-Klassen + Nachweis (nicht nur an `department_grossanlass_user_card`).

Membership: neue Rollenwerte `cmw`, `komm`, `spon` in Grossanlass-Dept (Pfadi-Depts unverändert).

---

## 10. Bewusst nicht hier

- Zweites Gmail-Konto oder zweite Inbox-UI
- Fahrer als Department-Rolle
- Fahrt als `kind: order`
- Jeder Einsatz automatisch eine Fahrt (ohne Checkbox)
- Eigenes Sidebar-Menü «Fahrten» (Dashboard-Box + gefilterte Einsatzliste)
- Hartes «Pack erst bei 100 % im Mat»
- OK-Leitung sendet oder nimmt Firmen
- Komm/Spon nehmen Firmen
- CMW verbindet OAuth oder sendet die Welle

---

## 11. Phasen (Abarbeiten)

Checkboxen in diesem File. Nicht alles in einem PR. Reihenfolge = Abhängigkeit.

### Phase R1 — Rollen + Access-Checks

- [x] `DepartmentRole` / Membership: `cmw`, `komm`, `spon` im Grossanlass-Dept
- [x] `dc` im Grossanlass als OK-Leitung labeln (i18n), Rechte von `canManagePlanung` trennen
- [x] `GrossanlassAccessService`: Checks §4 (`canWorkMailbox`, `canTakeInquiry`, `canCreateMailDrafts`, `canSendMail`, `canConnectGmail`, `canApproveEinsatz`, `canReleaseTrip`, `canSubmitEinsatz`)
- [x] `GroupAccessService::DEPARTMENT_GROUP_MANAGER_ROLES`: CMW wie MW für Struktur; DC nicht mehr voll Gmail/Beschaffung
- [x] Benutzer-UI: Rollen vergeben (kein Pfadi-L1–L3 im GA)

**DoD:** API verweigert CMW Wellen-Drafts, Komm Nehmen, DC Gmail; OK-Leitung darf Struktur + Einsatz-Freigabe.

### Phase R2 — Homes / Sidebar

- [x] CMW: Dashboard wie MW, ohne OAuth / «Entwürfe erzeugen» / Benutzer-Gefahrenzone
- [x] OK-Leitung: anlassweite Einsätze/Material/Wünsche, Freigabe-Queue, kein Postfach
- [x] Bereichsleitung: Mein Bereich, Einsatz einreichen
- [x] Komm/Spon: Anfragen+Vorlagen ohne Nehmen/Welle
- [x] Helfer: nur zugeteilte Struktur
- [x] Ressort-Avatare: Leader-Stern (Ist) + Primary unten rechts; Mitglieder-Dialog: Leader/Primary an der Person, nicht Bleistift-am-Ressort
- [x] Dept-Stufe (CMW/Komm/…) unter Benutzer; optional Mini-Icon am Avatar nur Anzeige
- [x] Panel «Mitglieder» unter Ressorts: gleiche Aktionen wie Benutzer-Tabelle — Details (gleiches Modal) + Entfernen mit Confirm (Dept)

**DoD:** Sidebar und Default-Route pro Rolle; Helfer sieht kein Beschaffungs-Kommando.

### Phase R3 — Postfach

- [x] App-Inbox filtert auf Label-Wurzel `eMatChef` (schon Default — erzwingen, nicht nur setzen)
- [x] `assertManage` Gmail splitten: sehen vs. nehmen vs. Welle vs. senden vs. connect
- [x] Nehmen (CMW/MW): Status + Folge-Entwurf; Button Senden nur MW
- [x] Komm/Spon: lesen, Vorlagen, ablegen — keine Take-Buttons

**DoD:** Ein Konto, ein Label; CMW nimmt und sieht Draft; nur MW sendet / erzeugt Welle.

### Phase R4 — Fahrausweise Profil

- [x] Profil-Accordion: Klassen + Scan/Gültig-bis (User)
- [x] Karte: MW/CMW bestätigt Anlass-Klassen (bestehende Karte erweitern, nicht ersetzen)
- [x] Ausgabe/Fahrt blockt ohne Karten-Freigabe + passende Klasse

**DoD:** Ausweis im Profil, Losfahren erst nach Karten-Haken.

### Phase R5 — Einsatz frei vs. Fahrt frei + Checkbox

- [x] Wunsch (Ressort/Bauprojekt, Material oder Werkzeug) → Einsatz
- [x] Checkbox **Fahrt** XOR **Selbst abholen** am Einsatz (`delivery`)
- [x] `pending_approval` → Freigabe durch OK-L / MW / CMW = Einsatz frei
- [x] Bereichsleitung nur einreichen
- [x] Zweites Flag `trip_released` nur MW/CMW und nur wenn Fahrt
- [x] Start der Fahrt nur wenn Haken Fahrt **und** Einsatz frei **und** Fahrt frei **und** Ziel-Ort gesetzt
- [x] `starts_at` blockiert den Start **nicht** (früher ausführen erlaubt)
- [x] Teilpack + MW-Freigabe: Materialplatz-Bestand schon weg
- [x] Dashboard: klickbare Box **Fahraufträge** → Liste der Einsätze mit Fahrt (eigene vs. MW alle)

**DoD:** Selbstabholung ohne Fahrt-Frei; Fahrauftrag nur mit Checkbox; Box auf dem Dashboard; Platz leer vor der Fahrt.

### Phase R6 — Packzeilen, Warnung, Paletten-QR

- [x] Pack pro Zeile mit Fenster `von–bis`
- [x] Teilpacken/Ausgabe mit Warnung, wenn Positionen fehlen
- [x] Palette/Pack-Entity mit `public_code` (QR)
- [x] Fahrt-Frei pro Pack, nicht «100 % Einsatz»
- [x] Zweite Palette = weiteres Pack am gleichen Einsatz

**DoD:** Holz kann raus trotz fehlender Schrauben; Werkzeug-Sa nicht in der Freitag-Palette Pflicht.

### Phase R7 — Substandort-QR + Standort

- [x] Grossanlass-Ort mit QR (`/i/p/…` oder analog)
- [x] Pack-QR scannen → unterwegs
- [x] Ziel-QR scannen → `current_place_id` dieses Packs
- [x] Falscher Ort: Fehler
- [x] User-Karte unverändert für Fahrer-Check

**DoD:** Scan-Kette Pack → Ziel setzt den Standort; Kalender zeigt den neuen Ort.

---

## 12. Offene Punkte (nicht blockierend)

| # | Frage | Tendenz |
| --- | --- | --- |
| 1 | Mehrere Packs pro Einsatz vs. Kind-Einsätze | Packs am Einsatz, weniger Tabellen |
| 2 | Schichten (CMW «alles Schichten») | später; nicht in R1–R7 |
| 3 | Volunteer-Koordination / Dispatch-Übersicht | nach R7, wenn Fahrten massenhaft |
| 4 | Senden nur in Gmail vs. App-Button nur MW | App-Button nur `mw` plus Gmail bleibt gültig |

---

## Siehe auch

- [README.md](./README.md) §3.6 Rollen, §11 Materialübersicht, §17 Matrix (älter: CM = MW/DC)
- [20260823_New_concept.md](./20260823_New_concept.md) §7 Gmail-Labels, §12.1 darf fahren, §12.3 Einsatz
- [kosten.md](./kosten.md) — Zahler/Ledger, unabhängig von Rollen-R1
