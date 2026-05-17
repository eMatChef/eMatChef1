# Nachrichtenzentrale & Benachrichtigungen

Dokumentation, wie Meldungen erzeugt, gespeichert und in der **Nachrichtenzentrale** sowie in der **Glocke** (`TopHeader`) angezeigt werden.

Verwandt: UI-Bausteine in [wiederverwendbare-komponenten.md](./wiederverwendbare-komponenten.md) (Abschnitt Benachrichtigungen / `NotificationSenderBlock`).

---

## Überblick

```text
Ereignis (Backend)  →  InboxMessageService  →  Tabelle inbox_message
                              ↓
                         REST-API
                              ↓
              NotificationsCenterView / TopHeader
                              ↓
         NotificationSenderBlock + Betreff/Vorschau (i18n)
```

Alle Benachrichtigungstypen für Glocke und Nachrichtenzentrale liegen in der Tabelle **`inbox_message`** (siehe Tabelle unten).

Bei **Abschluss oder Storno** einer Aktivität (`completed` / `cancelled`) werden aktivitätsbezogene Einträge in `inbox_message` **gelöscht**; der Verlauf bleibt in `activity_history`.

**Migration alter JSON-Daten:** nach `doctrine:migrations:migrate` einmal  
`php bin/console app:migrate-inbox-legacy`

Die Nachrichtenzentrale (`NotificationsCenterView`) führt alle Typen in **einem Posteingang** zusammen:

- Bereiche **Ungelesen** und **Gelesen** untereinander
- jeweils nach `created_at` absteigend sortiert
- Kategorie als Badge (Aktivität, Nachricht, QR-Aufgabe, …)

---

## Nachrichtentypen

| Art | Service | Speicher | API (unverändert) | Retention |
| --- | --- | --- | --- | --- |
| Direktnachricht | `InboxMessageService` | `inbox_message` `user_message` | `…/inbox/messages` | dauerhaft |
| Aktivität MW | `InboxMessageService` | `activity_mw` | `…/mw-notifications` | löschen bei Aktivität completed/cancelled |
| Aktivität User | `InboxMessageService` | `activity_user` | `…/inbox/activity-status` | löschen bei Aktivität completed/cancelled |
| Dept-Einladung | `InboxMessageService` | `department_invite` | `…/invite/received` | löschen bei Annahme/Ablehnung |
| Camp/Anlass-Einladung | `InboxMessageService` | `activity_department_invite` | `…/department-invites/pending` | löschen bei Entscheidung / Aktivität abgeschlossen |
| QR-Kontakt | `InboxMessageService` | `qr_found` | `…/public-found-messages` | Status open/in_progress/done |
| Buchhaltung | `InboxMessageService` + `accounting_acquisition_follow_up` | `accounting_followup` (Spiegel) | `…/acquisition-followups` | Inbox-Zeile weg bei „erfasst“ |
| Einladung angenommen (für Einladende) | `InboxMessageService` | `invite_accepted` | `…/invite/notifications` | dauerhaft, nur gelesen markieren |

**Hinweis:** `join.pending_invites` bleibt die fachliche Quelle für Einladungs-Workflow (Annahme/Ablehnung). Die Inbox zeigt nur die Benachrichtigung. Buchhaltungs-Fachdaten bleiben in `accounting_acquisition_follow_up`, die Glocke liest zusätzlich `inbox_message`.

### Nachricht vs. Aufgabe (UI)

Alles liegt in `inbox_message`. In der **Nachrichtenzentrale** gilt:

| Typ | Beispiel | Ablauf |
| --- | --- | --- |
| **Nachricht** | Direktnachricht, Aktivitäts-Info, `invite_accepted` | Lesen, fertig |
| **Nachricht + Aufgabe** | QR-Kontakt, Dept-Einladung, Camp/Anlass-Einladung | Zuerst Nachricht lesen → Button **„Aufgabe bearbeiten“** → Status / Annehmen-Ablehnen / Antwort |
| **Reine Aufgabe** | Buchhaltung (`accounting_followup`) | Seite **Aufgaben** (`/{departmentId}/tasks`) |

**Nachricht lesen** → Nachrichtenzentrale. **Aufgabe bearbeiten** → Button leitet zur Aufgaben-Seite (`?open=kind:id`). In der **Glocke**: Nachrichten oben, Buchhaltung unter **Aufgaben** (Link zur Aufgaben-Seite).

---

## Aktivitäten-Meldungen (MW/DC)

### Auslöser

`ActivityController` ruft `ActivityMwNotificationService::notifyActivitySubmitted()` auf, wenn eine Aktivität **eingereicht** wird (`status = submitted`):

1. **Anlegen** einer Aktivität direkt mit Status `submitted`
2. **Statuswechsel** von einem anderen Status auf `submitted`

Dateien:

- `backend/src/Controller/ActivityController.php` (create + status change)
- `backend/src/Service/ActivityMwNotificationService.php`

### Gespeicherte Felder (`buildEntry`)

Jeder Eintrag enthält u. a.:

| Feld | Bedeutung |
| --- | --- |
| `id` | eindeutige Meldungs-ID |
| `type` | Ereignistyp, aktuell: `activity_submitted` |
| `activity_id`, `activity_name`, `activity_type`, `activity_no`, `activity_status` | Aktivität |
| `group_id`, `group_name` | optionale Gruppe |
| `creator_*` | Profil des auslösenden Users (Name, Avatar-Farben) |
| `created_at` | ISO-Zeitstempel |
| `read`, `read_at` | Gelesen-Status |

Duplikate: Vor dem Anlegen wird eine **ungelesene** Meldung derselben Aktivität mit gleichem `type` entfernt (max. eine offene „eingereicht“-Meldung pro Aktivität).

Aktivitäts-Meldungen werden bei **completed** oder **cancelled** der zugehörigen Aktivität aus der Inbox entfernt (siehe `InboxMessageService::purgeByActivity` in `ActivityController::changeStatus`).

### Anzeige im Frontend

**Absender (links)** — `NotificationSenderBlock` via `fromActivityMw()`:

- `kind: system`, Variante `activity` (blaues System-Icon)
- Label: „Aktivitäten“ (`notificationsCenter.senderSystemActivity`)
- `sublabel`: Name des Erstellers
- kleines **User-Avatar** als Overlay (Ersteller)

**Textzeilen** in `NotificationsCenterView.vue`:

| Zeile | Funktion | Beispiel |
| --- | --- | --- |
| Von | `getSenderPrimaryLine(fromActivityMw(entry))` | `Aktivitäten · Max Muster` |
| Betreff | `activityMwInboxSubject()` | `«Sommerlager»` |
| Vorschau | `activityMwInboxPreview()` | `Lager · Gruppe Nord · #042` |
| Badge | `inboxCategoryActivity` | `Aktivität` |

Klick: öffnet die Aktivität (`/{departmentId}/activities/{activityId}`), markiert als gelesen.

**Glocke** (`TopHeader.vue`): nutzt dieselben Daten, Text etwas anders über `layout.notifications.newActivitySubtitle` (i18n mit `{activity}` und Meta Typ/Gruppe).

**Hinweis:** Das Feld `type` (`activity_submitted`) wird in der Zentrale **noch nicht** in einen Satz wie „wurde eingereicht“ übersetzt — nur Typ, Gruppe und Nummer stehen in der Vorschau. Für explizite Aktions-Texte siehe Abschnitt [Neue Meldungstexte](#neue-meldungstexte-hinzufügen).

---

## Direktnachrichten

### Erzeugen

`UserDirectMessageService::send()` — ausgelöst durch `POST /api/departments/{id}/inbox/messages` (`DepartmentInboxController`).

- Empfänger muss **Mitglied** desselben Departments sein
- Eintrag landet im Posteingang des **Empfängers** (`inbox.direct_messages.{recipientUserId}`), nicht beim Sender

Felder: `subject`, `message`, `sender_*` (Profil), `read`, `created_at`.

### Anzeige

- Absender: `fromUserMessage()` → User-Avatar
- Betreff/Vorschau: `subject` / `message`
- Klick: **Detail-Modal** (`InboxMessageDetailModal`), markiert als gelesen

### Senden (UI)

`InboxComposeModal`:

- **Mitglieder**: Suche mit Avatar, Name, Spitzname, E-Mail → In-App-Versand
- **Kundenadressen** (`type = customer`): Badge „Extern“ → Versand per **E-Mail** (`mailto:`), wenn E-Mail hinterlegt

---

## Weitere Typen (Kurz)

### Department-Einladung

- Absender: einladende Person (`fromDepartmentInvite`)
- Betreff: Einladung in Abteilung X
- Aktionen: Annehmen / Ablehnen (kein separates Detail-Modal)

### QR-Aufgabe (öffentliche Seite)

- Absender: `fromPublicFound` → Aufgaben-Icon, Materialname, Finder
- Betreff: `notificationsCenter.qrTaskSubject`
- Status: offen / in Bearbeitung / erledigt (steuert Ungelesen)
- „Antworten“: `mailto` an Finder-E-Mail

### Camp/Anlass-Einladung

- Absender: einladende Abteilung (`fromActivityInvite`)
- immer als ungelesen in der Liste, bis entschieden

---

## Frontend: einheitlicher Posteingang

**View:** `frontend/src/views/NotificationsCenterView.vue`

1. Paralleles Laden aller Quellen (`load()`)
2. Zusammenführen in `allInboxItems` (typisierte `UnifiedInboxItem`)
3. Aufteilen in `unreadInboxItems` / `readInboxItems` → `inboxSections`
4. Pro Zeile: `NotificationSenderBlock` + Meta + Betreff + Vorschau

**Komponenten:**

| Komponente | Pfad |
| --- | --- |
| `NotificationSenderBlock` | `components/notifications/NotificationSenderBlock.vue` |
| `InboxComposeModal` | `components/notifications/InboxComposeModal.vue` |
| `InboxMessageDetailModal` | `components/notifications/InboxMessageDetailModal.vue` |

**Utils / Composable:**

- `utils/notificationSender.ts` — Deskriptoren, Factories, `getSenderPrimaryLine`
- `composables/useNotificationSender.ts` — Factories mit i18n-Labels

**API-Clients:**

- `api/activityNotifications.ts`
- `api/inboxMessages.ts`
- `api/joinRequests.ts` (Einladungen)
- `api/publicFoundMessages.ts`

---

## Neue Aktivitäts-Meldung (Backend)

Beispiel: „Aktivität bestätigt“ (`activity_approved`).

1. In `ActivityMwNotificationService` neue Methode, z. B. `notifyActivityApproved(Activity $activity, User $actor)`
2. `buildEntry($activity, $actor, 'activity_approved')` aufrufen
3. Im `ActivityController` (oder passendem Service) bei Statuswechsel auf `approved` triggern
4. Optional: Deduplizierungslogik wie bei `activity_submitted`

---

## Neue Meldungstexte hinzufügen

### Variante A — Frontend (empfohlen bei wenigen Typen)

1. i18n-Keys in `de.json` unter `notificationsCenter`, z. B.:

   ```json
   "activityNotificationType": {
     "activity_submitted": "Neue Aktivität eingereicht",
     "activity_approved": "Aktivität bestätigt"
   }
   ```

2. In `NotificationsCenterView.vue` (und ggf. `TopHeader.vue`) `entry.type` mappen und in Vorschau oder Betreff einbauen

3. Glocke und Zentrale **gleiche Textlogik** nutzen (gemeinsame Hilfsfunktion, z. B. in `utils/activityNotificationText.ts`)

### Variante B — Backend

Feld `summary` oder `message` in `buildEntry()` setzen — weniger flexibel für Mehrsprachigkeit, dafür zentral.

---

## Neue System-/Sender-Art (UI)

Siehe [wiederverwendbare-komponenten.md](./wiederverwendbare-komponenten.md):

1. `notificationSender.ts`: Factory + ggf. `systemVariant` / `taskVariant`
2. `useNotificationSender` + `de.json`
3. `NotificationSenderBlock.vue`: Icon-Styling
4. `NotificationsCenterView`: neuen `InboxItemKind` + Zeilen-Template

---

## Glocke (`TopHeader`)

Die Glocke zeigt nur **Nachrichten** und **Aufgaben** (Kurzvorschau, max. 5 pro Quelle). Vollständige Liste: Nachrichtenzentrale.

Lädt periodisch (60 s) und bei `headerNotificationsStore.requestRefresh()`.

### Nachrichten (Badge + Dropdown)

| Quelle | API | Wer |
| --- | --- | --- |
| Direktnachrichten | `inbox/messages` · `bucket: unread` | alle Mitglieder |
| Aktivität eingereicht | `mw-notifications` · `bucket: unread` | `canManageMaterials` |
| Aktivitäts-Status (bestätigt, Retour, zurückgewiesen) | `inbox/activity-status` · `bucket: unread` | Verantwortliche/r der Aktivität |

Typen (`activityNotificationType` in `de.json`): `activity_submitted`, `activity_approved`, `activity_returned`, `activity_rejected`.

### Aufgaben (Badge + Dropdown)

| Quelle | API | Wer |
| --- | --- | --- |
| QR-Kontakt (Kontakt **mit** Aufgabe) | `public-found-messages` · `bucket: active` | `canManageQrContact` |
| Buchhaltung: Buchung zuordnen | `listAcquisitionFollowups` · `pending` | MW/DC mit Buchhaltung |
| Einladung Abteilung | `invite/received` · `bucket: unread` | eingeladener User |
| Einladung Camp/Anlass | `pending` activity invites | MW/DC (nicht User-Rolle) |

### Entfernt aus der Glocke

| Früher in Glocke | Jetzt |
| --- | --- |
| Kommende Aktivitäten (Dashboard-Liste) | Navigation **Aktivitäten** |

**Tab-Hinweis:** Bei ungelesenen Meldungen und Hintergrund-Tab wechselt der Browsertitel (`useUnreadDocumentTitleAlert`).

---

## Gelesen / Ungelesen

| Typ | Ungelesen wenn |
| --- | --- |
| Aktivität MW | `read === false` |
| Direktnachricht | `read === false` |
| Dept-Einladung | `read === false` |
| QR-Aufgabe | `status === 'open'` |
| Camp-Einladung | bis Entscheidung (immer `unread: true` in der View) |

Markieren als gelesen: jeweilige `PATCH …/read`-Endpoints; danach `headerNotificationsStore.requestRefresh()` für die Glocke.

---

## Datei-Referenz (Backend)

| Datei | Rolle |
| --- | --- |
| `Service/ActivityMwNotificationService.php` | Aktivitäten-Meldungen MW/DC |
| `Service/UserDirectMessageService.php` | Direktnachrichten |
| `Service/UserDepartmentInviteNotificationService.php` | Dept-Einladungen (User-Inbox) |
| `Controller/DepartmentInboxController.php` | REST Inbox / Nachrichten senden |
| `Controller/ActivityController.php` | Trigger + `mw-notifications` API |

---

## Datei-Referenz (Frontend)

| Datei | Rolle |
| --- | --- |
| `views/NotificationsCenterView.vue` | Posteingang, Sektionen, Formatierung Aktivität |
| `components/layout/TopHeader.vue` | Glocke, Kurzliste |
| `components/notifications/*` | Sender-Block, Compose-, Detail-Modal |
| `locales/de.json` | `notificationsCenter.*`, `layout.notifications.*` |
