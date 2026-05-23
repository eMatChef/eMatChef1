# Öffentliche QR-Seiten (`qr.ematchef.ch`)

Was Besucher und Scanner **ohne App** sehen, wenn sie einen QR von `qr.ematchef.ch` öffnen. Alles auf dieser Subdomain ist **bewusst öffentlich** — schmal, ohne interne Bestände oder Workflows.

**Stand:** Mai 2026 · **Status:** Umgesetzt (Material, Aktivität, Werkstatt); Display auf `app.`

Linkschema: [link-schema.md](./link-schema.md) · Lager/Scanner: [../devices/README.md](../devices/README.md)

---

## Rolle von `qr.ematchef.ch`

| Ja (auf `qr.`) | Nein (auf `qr.`) |
|----------------|------------------|
| Material + Charge/Serie, Kontakt | Packliste, Mengen, Lagerplätze |
| Aktivität (Anlass kurz), Kontakt | Status-Workflow, Packen |
| Werkstatt-Ticket (kurz), Kontakt | Ticket bearbeiten, Kosten |
| Abteilungs-Kontakt (Formular/E-Mail) | Login-Pflicht-Inhalte |

**Eingeloggt** darf dieselbe URL optional Links zeigen („In App öffnen“, „Lager“) — der **Seiteninhalt** bleibt öffentlich schlank.

Interne Bearbeitung: `app.ematchef.ch`, Lager-Scan: `devices.ematchef.ch`.

---

## 1. Material + Charge (`/i/m/{mat}/b/{batch}`)

### URL

- **Kanonisch (Etikett, PDF):** `https://qr.ematchef.ch/i/m/{materialCode}/b/{batchCode}`

Es gibt **kein** gültiges Material-Etikett nur mit `/i/m/{materialCode}` ohne Batch und **keine** `/i/b/{batchCode}`-Only-URLs mehr.

Auf `devices.ematchef.ch` (geplant) kann der Scanner später Rohcodes oder kanonische URLs verarbeiten — nicht über eine separate Kurz-Route auf `qr.`.

### Öffentliche Anzeige (Ziel)

Nur das Nötige für Finder und Neugierige:

| Anzeigen | Nicht anzeigen |
|----------|----------------|
| Materialname (ggf. Hersteller/Modell, wenn öffentlich ok) | Bestand, verfügbare Menge |
| Kurzhinweis Charge: Einkaufsdatum, Seriennummer, Label der Charge | Einkaufspreis, interne Notizen |
| Abteilungsname | Packliste, Lagerort, Gestell/Fach |
| Kontakt: Formular und/oder E-Mail (laut Abteilungs-Einstellung) | Benutzerliste, Historie |
| Optional: kurzer Hinweistext der Abteilung (`contact_note`) | Workshop, Aktivitäten |

### Technik (Ist)

- Route: `/i/m/:matCode/b/:batchCode`
- View: `frontend/src/views/public/PublicMaterialView.vue`
- API: `GET /api/public/lookup/m/{code}` und `…/b/{code}` (`PublicLookupController`, `PublicCodeService`)
- Sichtbarkeit: `public_ui` aus Department-Settings (`show_contact_form`, `show_contact_email`, …)

### Geplante Anpassungen

- Eine Route/View für **lange URL** `/i/m/:mat/b/:batch` (Validierung: Batch gehört zu Material)
- Einheitliche öffentliche Shell für Kurz- und Lang-URL
- Batch-QR auch für **Massen-Einkäufe** (jeder Einkauf = eigener `{batchCode}`)

---

## 2. Aktivität (`/i/a/{activityCode}`)

### URL

```text
https://qr.ematchef.ch/i/a/{activityCode}
```

`{activityCode}` = `public_code` der Aktivität (neu, analog Material — **noch umzusetzen**).

Verwendung: QR auf **Packplan-PDF**, auf dem **Abteilungs-Display** (`app.…/display/{publicId}`), nicht auf jedem Material-Stück.

### Öffentliche Anzeige (Ziel)

| Anzeigen | Nicht anzeigen |
|----------|----------------|
| Name des Anlasses (z. B. «Sommerlager 2026») | Packliste, Materialpositionen |
| Zeitraum (von–bis), optional Typ (Camp/Event) | Rollen, interne Kommentare |
| Abteilungsname | Status-Übergänge (packing, …) |
| Kontakt Abteilung (wie bei Material) | Kosten, Partner-Details |

### Intern (nach Scan mit App / Link von Seite)

- `app.ematchef.ch/{departmentId}/activities/{activityId}`
- Lager: `devices.ematchef.ch/{departmentId}/pack/{activityId}` (Packen)

Öffentliche Seite verlinkt nur, führt Packen **nicht** auf `qr.` aus.

---

## 3. Werkstatt / Reparatur (`/i/w/{workshopCode}`)

### URL

```text
https://qr.ematchef.ch/i/w/{workshopCode}
```

`{workshopCode}` = `public_code` des Workshop-Tickets (**noch umzusetzen**).

Verwendung: Reparatur-PDF, Eintrag auf Abteilungs-Display.

### Öffentliche Anzeige (Ziel)

| Anzeigen | Nicht anzeigen |
|----------|----------------|
| Kurz: «Reparatur / Werkstatt» + Materialbezug (Name des Artikels) | Ticket-Historie, Kosten |
| Status grob (z. B. «offen» / «in Bearbeitung») — optional, ohne Details | Zuständiger Benutzer intern |
| Abteilungsname + Kontakt | Interne Notizen |

Für Finder selten relevant; Hauptzweck: QR auf Listen/Display, den MW mit dem Handy **kurz** identifiziert, ohne sensible Daten zu leaken.

### Intern

- `app.ematchef.ch/{departmentId}/workshop` — Ticket aus Code auflösen und fokussieren

---

## 4. Abteilungs-Display (nicht auf `qr.`)

Der **Infoscreen** läuft auf der App-Origin, **ohne** App-Login am Gerät (8-stelliger Zugangscode + Cookie):

```text
https://app.ematchef.ch/display/{publicId}
```

Screens verwalten MW/DC unter **Konfiguration → Infoscreens** (nach Join-Code).

**Zweck:** Am Lager/Büro ein Tablet/Monitor zeigt anstehende Anlässe, Werkstatt, Hinweise — jeweils mit **QR**, die auf `qr.ematchef.ch` zeigen.

| Auf dem Display | QR-Ziel |
|-----------------|---------|
| Anlass «Sommerlager» | `qr…/i/a/{activityCode}` |
| Reparatur «Zelt 6P» | `qr…/i/w/{workshopCode}` |
| (optional) Material-Hinweis | `qr…/i/m/…/b/…` |

Nutzer scannt mit privatem Handy → **öffentliche** Kurzseite. MW mit App kann zusätzlich in `app.` wechseln.

**Umsetzung:** Route und View unter `app.` — **nicht** unter `qr.ematchef.ch`.

---

## Kontakt & Datenschutz

Öffentliche Kontaktdaten kommen aus Abteilungs-Einstellungen (`PublicCodeService::resolvePublicSettings`):

- Kontaktformular ein/aus
- E-Mail anzeigen / nur Formular
- Hinweistext (`contact_note`)
- «Gefunden»-Meldung → Posteingang (`PublicFoundItemContactService`)

Öffentliche Seiten dürfen **keine** personenbezogenen internen Daten (Mitgliederlisten, vollständige Adressen) zeigen, die nicht explizit für Public freigegeben sind.

---

## Unterschied: Scan mit Handy vs. Pistole

| Quelle | Typisches Ziel | Ergebnis |
|--------|----------------|----------|
| Handy-Kamera | URL in Browser | Öffentliche Seite auf `qr.` |
| Pistole/TC70 auf `devices.` | URL in Scan-Input | **Kein** Tab-Wechsel zu `qr.` — Auflösung im Lager-UI (Packen, …) |
| Handy + eingeloggt in App | optional `OpenFromQrView` | Weiterleitung `app.…/materials/…` (Material), nicht Ersatz für öffentliche Finder-Seite |

---

## Druckkorb & PDF

Workflow heute: `PrintTaskItem` + `TasksPrintView` — Einträge mit `public_url` und `public_code`.

**Regeln für `public_url` in Druckjobs:**

| `entity_type` (Beispiel) | `public_url` |
|--------------------------|--------------|
| `material` / `batch` | `…/i/m/{mat}/b/{batch}` |
| `activity` | `…/i/a/{activityCode}` |
| `workshop` | `…/i/w/{workshopCode}` |

Nach Druck: QR auf PDF/Display entspricht exakt dem Linkschema.

---

## Implementierungs-Backlog (öffentliche Schicht)

1. Route `PublicMaterialBatchView` oder erweiterte `PublicMaterialView` für `/i/m/:mat/b/:batch`
2. `PublicActivityView` + `public_code` auf `Activity`
3. `PublicWorkshopView` + `public_code` auf `WorkshopTicket`
4. Einheitliches Layout: Header (Name), Kontaktblock, optional «In App» nur bei Session-Cookie
5. `DepartmentDisplayView` auf `app.…/display/{publicId}` mit QR-Generierung aus denselben URL-Buildern

---

## Siehe auch

- [link-schema.md](./link-schema.md) — vollständige URL-Tabelle und Resolver
- [../devices/README.md](../devices/README.md) — Zebra TC70, Datalogic, `devices.ematchef.ch`
- `deploy/CROSS-SUBDOMAIN-LOGIN.md` — Cookies über `*.ematchef.ch`
- `docs/wiederverwendbare-komponenten.md` — `PublicQrTag`, UI-Bausteine
