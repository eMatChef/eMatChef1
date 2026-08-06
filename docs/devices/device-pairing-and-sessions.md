# Geräte-Kopplung, User-PIN & Zugriffs-Tracking

**Stand:** August 2026  
**Status:** Produktidee / Spezifikation — noch nicht umgesetzt  
**Kontext:** Lager-Handhelds (`devices.ematchef.ch`), später optional auch normale App-Logins

Verwandte Doku:

- [concept.md](./concept.md) — Lager-Geräte Gesamtkonzept
- [rollout-plan.md](./rollout-plan.md) — Phasen D0–D8
- [zebra-tc700h.md](./zebra-tc700h.md) — TC700H / Enterprise Browser
- Infoscreen-Vorbild: PIN + langlebige Cookie-Session (`DepartmentDisplayScreen`)
- Auth heute: [deploy/CROSS-SUBDOMAIN-LOGIN.md](../../deploy/CROSS-SUBDOMAIN-LOGIN.md)

---

## 1. Problem

Heute auf `devices.`:

- Volle **User-Session** (JWT + Refresh-Cookie), Login mit E-Mail/Passwort
- Abteilung per localStorage pinnen
- **Idle-Auto-Logout** gilt auch auf dem Devices-Host

Auf einem Zebra TC700H (Touch, oft Handschuhe, Schichtbetrieb) ist das umständlich: jedes Mal Passwort tippen, oder eine dauerhaft eingeloggte Session ohne klare User-Zuordnung.

**Infoscreens** lösen „Gerät merken“ schon mit PIN + Cookie — aber **ohne** User-Rechte und ohne Pack-Audit. Lager-Geräte brauchen beides.

---

## 2. Zielbild

| Ziel | Kurz |
|------|------|
| Gerät einmal freigeben | Eingeloggter User mit Rechten koppelt Handheld per **QR** |
| Alltag ohne Passwort | Gekoppeltes Gerät → **User wählen** → **persönlicher PIN** |
| Audit bleibt klar | Jede Pack-/Scan-Aktion hat **User + Gerät** |
| Verwaltung in Einstellungen | Liste gekoppelter Geräte, Widerruf, letzter Zugriff |
| Transparenz für den User | Eigene Logins/Zugriffe sehen: App Desktop, Handy, Lager-Gerät |

**Nicht Ziel v1:** Native Android-App, Hardware-Attestation, Offline-PIN-Verifikation.

---

## 3. Rollen im Modell

```text
┌─────────────┐     koppelt (QR)      ┌──────────────────┐
│  Admin / MW │ ────────────────────► │  Geräte-Eintrag  │
│  (Account)  │                       │  ↔ Abteilung     │
└─────────────┘                       └────────┬─────────┘
                                               │
                          Unlock               │ langlebiges
                     (User + PIN)              │ Geräte-Cookie
                                               ▼
                                      ┌──────────────────┐
                                      │ Session auf      │
                                      │ devices.: User   │
                                      │ + device_id      │
                                      └──────────────────┘
```

| Entität | Bedeutung |
|---------|-----------|
| **Gerät** | Physisches Handheld/PC-Bookmark; gebunden an **eine Abteilung** (Regel bleibt: ein Gerät ≈ eine Abteilung) |
| **User** | Konkrete Person, die auf dem Gerät arbeitet; wählbar aus freigeschalteten Abteilungs-Usern |
| **User-PIN** | Persönlicher Kurzcode des Users (nicht Geräte-PIN allein) — für Unlock und User-Wechsel |
| **Kopplungs-QR** | Einmalig / kurzlebig; nur von eingeloggtem Account mit Recht erzeugt |

---

## 4. Flows

### 4.1 Gerät koppeln (Freigabe)

1. In der App (eingeloggt, passende Rolle): **Einstellungen → Geräte → „Gerät koppeln“**
2. Dialog: Name (z. B. «TC70 Lager 1»), Abteilung (vorausgefüllt), optional Hinweis
3. App zeigt **Kopplungs-QR** (kurzlebig, z. B. 5–15 Min, einmalig nutzbar)
4. Handheld öffnet `devices.` (oder Enterprise-Browser Start-URL) und scannt den QR **oder** tippt/öffnet die Pair-URL
5. Gerät setzt langlebiges **Geräte-Cookie** / Device-Token (HttpOnly), erscheint in der Geräteliste als «aktiv»
6. Optional beim ersten Setup: Hinweis «PIN in den persönlichen Einstellungen setzen», falls User noch keinen hat

```text
app.…/settings/devices  →  QR: devices.…/pair/{oneTimeToken}
TC70 scannt / öffnet     →  Gerät registriert, Cookie gesetzt
                            → Unlock-Screen (User + PIN)
```

### 4.2 Alltag: Unlock

1. Handheld startet `devices.` → erkennt gültiges Geräte-Cookie
2. **Unlock-Screen:** Liste / Suche der User dieser Abteilung (nur freigeschaltete Rollen)
3. User tippt **persönlichen PIN**
4. Server prüft PIN (Rate-Limit), stellt **User-Session** für Pack-APIs aus, verknüpft `device_id`
5. Idle → nicht hart ausloggen auf Passwort-Login, sondern zurück auf **Unlock** (PIN-Lock)

### 4.3 User-Wechsel (gleiche Schicht, anderes Gesicht)

Auf dem Geräte-UI: «Benutzer wechseln» → zurück zu Unlock → anderer User + dessen PIN.  
Geräte-Cookie bleibt; nur die User-Session wechselt.

### 4.4 Widerruf

In **Einstellungen → Geräte**:

- Gerät umbenennen, Abteilung (nur wenn idle), **sperren / entkoppeln**
- Sofort ungültig: Geräte-Cookie-Version / Token-Version erhöhen (wie Infoscreen Code-Versioning)
- Optional: alle offenen User-Sessions auf dem Gerät killen

---

## 5. Einstellungen: Geräteliste

Ort (Vorschlag): **Meine Abteilung / Konfiguration → Lager-Geräte** (neben Infoscreens), plus ggf. Eintrag unter persönlichem Profil für «meine Zugriffe».

| Spalte / Info | Beispiel |
|---------------|----------|
| Name | TC70 Lager 1 |
| Typ | `handheld` / `desktop_lager` / `unknown` |
| Abteilung | Materiallager XY |
| Status | aktiv / gesperrt / abgelaufen |
| Gekoppelt von | User, Datum |
| Letzter Unlock | User, Zeit |
| Letzter Scan / API | Zeit (optional) |
| Aktionen | Umbenennen, Sperren, Entkoppeln, QR neu |

Rechte: wer Infoscreens verwalten darf bzw. MW/DC/DepChef — final mit Fachbereich klären.

---

## 6. Persönlicher PIN

| Thema | Vorschlag |
|--------|-----------|
| Länge | 4–8 Ziffern (oder alphanumerisch kurz) |
| Speicherung | nur gehasht (wie Infoscreen-Code) |
| Setzen | User selbst unter Profil / Sicherheit; optional Admin-Reset |
| Pflicht | Für Unlock auf gekoppelten Geräten nötig; App-Passwort-Login bleibt parallel |
| Rate-Limit | Lockout nach N Fehlversuchen (Gerät und/oder User) |
| Nicht | PIN = Passwort-Ersatz für `app.` Voll-Login in v1 |

**Warum User-PIN statt nur Geräte-PIN:** Mehrere Personen teilen ein Handheld; Audit und Rechte brauchen die Person, nicht nur «das Lager-Ding».

---

## 7. Zugriffs- / Session-Tracking (User sieht «womit ich eingeloggt bin»)

Ergänzend zur Geräte-Kopplung: der User (und optional Admins) soll sehen, **über welche Clients** der Account genutzt wird.

### 7.1 Was tracken?

Bei Login, Token-Refresh und Geräte-Unlock einen **Session-/Zugriffseintrag** führen:

| Feld | Beispiel |
|------|----------|
| `client_kind` | `desktop_app` · `mobile_app` · `devices_handheld` · `devices_desktop` |
| `client_label` | abgeleitet: «Chrome Windows», «Safari iPhone», «TC70 Lager 1» |
| `user_agent` / grobe Plattform | OS + Browser-Familie (nicht roh ewig speichern, falls Datenschutz eng) |
| `device_id` | wenn gekoppeltes Lager-Gerät |
| `ip` / grobe Geo | optional, Retention kurz |
| `created_at` / `last_seen_at` | |
| `revoked_at` | bei Logout / Admin-Kick / Geräte-Entkopplung |

Erkennung `mobile` vs. `desktop` grob über User-Agent + Viewport-Hinweis vom Client (nicht 100 % sicher, für Übersicht reicht es).  
Lager-Geräte: `client_kind` kommt aus dem gekoppelten Gerätetyp, nicht nur UA.

### 7.2 UI für den User

Unter **Profil / Sicherheit → Aktive Zugriffe** (oder «Geräte & Sitzungen»):

| Gerät / Client | Zuletzt | Aktion |
|----------------|---------|--------|
| Desktop · Chrome · Windows | heute 09:12 | Sitzung beenden |
| Handy · Safari · iOS | gestern | Sitzung beenden |
| Lager · TC70 Lager 1 | heute 08:01 | (Hinweis: Geräte-Verwaltung bei Admin) |

Optional: Push/Mail bei neuem Client («Neue Anmeldung von …») — später.

### 7.3 Abgrenzung zu Scan-History

| | Session-/Zugriffs-Tracking | [Scan-History](../qr/scan-and-url-history.md) |
|--|---------------------------|-----------------------------------------------|
| Zweck | Wer ist wo eingeloggt / unlocked | Was wurde gescannt |
| Granularität | Session / Gerät | einzelner Scan |
| UI | Profil / Admin | Material/Anlass-Auswertung |

Beide können `device_id` teilen, sind aber getrennte Tabellen/Konzepte.

---

## 8. Abgrenzung Infoscreen

| | Infoscreen | Gekoppeltes Lager-Gerät |
|--|------------|-------------------------|
| Host | `app.…/display/{publicId}` | `devices.…` |
| Auth | PIN am Screen, **kein** User-JWT | Geräte-Cookie + **User + PIN** → User-Session |
| Rechte | nur Anzeige | Pack-/Scan-APIs wie heute |
| Audit | Display-Events | User + Gerät an Workflow-Aktionen |
| Wiederverwenden | Hash, Cookie-Version, Rate-Limit, Widerruf | gleiches Muster, neues Entity |

---

## 9. Technische Skizze (nicht final)

### 9.1 Entities (Vorschlag)

- `WarehouseDevice` — id, department, name, type, paired_by, token_version, status, last_seen_at, …
- `UserPin` — user_id, pin_hash, updated_at (oder Feld am User-Profil)
- `AuthSession` / `UserClientSession` — user, client_kind, device_fk nullable, last_seen, revoke
- `DevicePairToken` — one-time, expires_at, created_by, department_id

### 9.2 Cookies / Tokens

| Cookie / Token | Lebensdauer | Zweck |
|----------------|-------------|--------|
| Geräte-Cookie | lang (z. B. 90 Tage), versioniert | «dieses physische Gerät ist freigegeben» |
| User-Session (JWT/Refresh) | kurz / wie heute, Idle → Unlock | Rechte für APIs |
| Pair-Token | Minuten, einmalig | QR-Kopplung |

Auf `devices.` bei gültigem Geräte-Cookie: **kein** erzwungenes Passwort-Login; Idle führt zu Unlock, nicht zu E-Mail-Login.  
Auto-Logout-Hook braucht eine **Devices-paired Ausnahme** (sonst kämpft Idle gegen das Konzept).

### 9.3 QR-Typ

Eigener Pair-Pfad, **nicht** Material/Anlass-QR:

```text
https://devices.ematchef.ch/pair/{oneTimeToken}
```

Oder signierte Kurzform über bestehende QR-Infrastruktur — Detail offen; wichtig: Parser unterscheidet Pair vs. Pack-Scan.

---

## 10. Offene Entscheidungen

1. PIN nur Ziffern oder alphanumerisch? Länge?
2. Wer darf Geräte koppeln/widerrufen (MW, DC, DepChef, Org-Admin)?
3. Dürfen User ohne gesetzten PIN das Gerät sehen (Liste grau) oder gar nicht?
4. Retention für Session-Liste und IP/UA (Datenschutz / Org-Policy)?
5. Soll dasselbe Session-Tracking auch für normales `app.`-Login in v1 mitkommen, oder zuerst nur `devices.`?
6. PowerScan-PC: gleiches Kopplungsmodell oder weiterhin Passwort-Login + Abteilungspin?

---

## 11. Vorgeschlagene Umsetzungsreihenfolge

| Schritt | Inhalt |
|---------|--------|
| A | Doku + Fach-OK (dieses Dokument) |
| B | `UserPin` setzen/ändern + Hash/Rate-Limit |
| C | `WarehouseDevice` + Pair-QR + Geräte-Cookie |
| D | Unlock-UI auf `devices.` (User-Liste + PIN) + Idle → Unlock |
| E | Einstellungen: Geräteliste + Widerruf |
| F | `UserClientSession`-Tracking + Profil «Aktive Zugriffe» (App Desktop/Mobile + Geräte) |
| G | Feinschliff: User-Wechsel, Mail bei neuem Gerät, Admin-Übersicht |

Passt thematisch als eigene Rollout-Phase nach stabilem Pack-MVP (z. B. **D6b / D9 Auth-Geräte** — Nummerierung im [rollout-plan.md](./rollout-plan.md) bei Umsetzung festziehen).

---

## 12. Siehe auch

- [README.md](./README.md) — Überblick Lager-Geräte
- [concept.md](./concept.md) §7 Organisatorische Regeln (Login heute)
- Infoscreen-Verwaltung als UI-Vorbild für Gerätelisten
