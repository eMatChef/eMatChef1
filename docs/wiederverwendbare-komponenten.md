# Wiederverwendbare Bausteine (Frontend)

Übersicht über zentrale Vue-Komponenten, Composables, Utils und CSS, die in mehreren Bereichen der App genutzt werden sollen — statt Logik oder Styles pro View neu zu schreiben.

**Stand:** Juni 2026 (Aktivitäten Datum/Zeitraum ergänzt)

---

## Medien / Fotos

Zentrale Foto-Uploads über **kontextspezifische APIs** und **gemeinsame Vue-Bausteine**. Konzept und Umbauplan: [media/README.md](./media/README.md) · Checkliste: [media/plan.md](./media/plan.md).

**Ist-Zustand (Mai 2026):** Werkstatt (MW + Lieferant), Schaden melden, Material-Abbildung — einheitliche Bausteine `PhotoUpload` / `PhotoGallery` / `useMediaUpload`.

### Backend (geplant / teilweise vorhanden)


| Baustein | Pfad | Verwendung |
| -------- | ---- | ---------- |
| **MediaStorageService** | `backend/src/Service/Media/MediaStorageService.php` *(geplant)* | Speichern unter `var/uploads/{context}/…`, Kompression, Löschen |
| **MediaCompressionService** | `backend/src/Service/Media/MediaCompressionService.php` *(geplant)* | Resize max. 1920 px, WebP/JPEG |
| **WorkshopPhotoStorageService** | `backend/src/Service/Workshop/WorkshopPhotoStorageService.php` | **Prototyp** — wird in Paket 0/1 durch `MediaStorageService` ersetzt |
| **MediaPhoto** (JSON-Shape) | siehe [media/README.md §2.3](./media/README.md#23-einheitliches-foto-json-metadaten) | Einheitliche Metadaten: `uploaded_at`, `uploaded_by_name`, `filename`, … |


**Kontexte & API-Routes (Ziel)**


| Kontext | Upload | Galerie in |
| ------- | ------ | ---------- |
| Werkstatt-Ticket (MW) | `POST /api/workshop/tickets/{id}/photos` | `WorkshopView` |
| Werkstatt-Ticket (Lieferant) | `POST /api/supplier-companies/{companyId}/repairs/{ticketId}/photos` | `SupplierRepairsView` |
| Schadenmeldung | `POST /api/activities/{activityId}/issues/{issueId}/photos` | `DamageReportWizard` |
| Material | `POST /api/material/{materialId}/photos` | `MaterialDetailView` |


**Ordnerstruktur:** `var/uploads/workshop/{departmentId}/{ticketId}/`, `issues/…`, `material/…` — Retention für abgeschlossene Tickets nach X Jahren ([media/plan.md Paket 5](./media/plan.md)).

---

### Vue-Komponenten


| Baustein | Pfad | Verwendung |
| -------- | ---- | ---------- |
| **MaterialImagePicker** | `frontend/src/components/media/MaterialImagePicker.vue` | Material-Detail: Upload / Kamera / URL (+ Google-Suche) |
| **PhotoUpload** | `frontend/src/components/media/PhotoUpload.vue` | Datei wählen, Upload (sofort oder defer mit `v-model:files`) |
| **PhotoGallery** | `frontend/src/components/media/PhotoGallery.vue` | Thumbnails + Meta (Wer, Wann) |
| **useMediaUpload** | `frontend/src/composables/useMediaUpload.ts` | FormData-Upload via `uploadMediaFile` |
| **media.ts** | `frontend/src/api/media.ts` | Typ `MediaPhoto`, `uploadMediaFile`, Hilfsfunktionen |


**PhotoUpload — Props**

```vue
import PhotoUpload from '@/components/media/PhotoUpload.vue'

<!-- Sofort-Upload (URL oder domain-spezifische uploadFn) -->
<PhotoUpload
  :upload-fn="(file) => uploadWorkshopTicketPhoto(ticketId, file)"
  @uploaded="onPhotoUploaded"
  @error="onUploadError"
/>

<!-- Defer-Modus (z. B. Schaden melden, max. 3 Fotos) -->
<PhotoUpload
  v-model:files="selectedPhotos"
  multiple
  :auto-upload="false"
  :max-files="3"
  @error="onUploadError"
/>
```

**PhotoGallery — Props**

```vue
import PhotoGallery from '@/components/media/PhotoGallery.vue'
import type { MediaPhoto } from '@/api/media'

<PhotoGallery :photos="ticket.photos ?? []" readonly />
<PhotoGallery :photos="ticket.photos ?? []" :format-date="formatDateTime" show-empty />
```

**`MediaPhoto`-Objekt** (API, snake_case):

- `id`, `filename`, `url`
- `uploaded_at`, `uploaded_by_id`, `uploaded_by_name`
- `original_filename`
- optional `context`, `context_id`, `bytes`, `width`, `height`, `mime`
- `legacy?: true` — alter reiner URL-String

**Eingebunden in:** `SupplierRepairsView.vue`, `WorkshopView.vue`, `MaterialDetailView.vue` (`MaterialImagePicker`), `DamageReportWizard.vue`.

**Zukunft:** Department-Mediathek — [mediathek-zukunft.md](./media/mediathek-zukunft.md)

**Styles:** `frontend/src/styles/components/photo-gallery.css` (von PhotoUpload/PhotoGallery importiert)

---

## Vue-Komponenten

### User / Identität


| Baustein                   | Pfad                                                        | Verwendung                                                                                 |
| -------------------------- | ----------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| **UserAvatarBadge**        | `frontend/src/components/user/UserAvatarBadge.vue`          | Farbiger Avatar mit Initialen, Hover-Tooltip (Name, Spitzname), optional ★ für Gruppenchef |
| **PublicUserIdentityChip** | `frontend/src/components/public/PublicUserIdentityChip.vue` | Avatar + Anzeigename (öffentliche Seiten)                                                  |


**UserAvatarBadge — Import & Props**

```vue
import { UserAvatarBadge } from '@/components/user'

<UserAvatarBadge :user="actor" />
<UserAvatarBadge :user="leader" show-leader-star />
<UserAvatarBadge :user="actor" size="md" :show-tooltip="false" />

<div class="user-avatar-badge-list">
  <UserAvatarBadge v-for="u in users" :key="u.user_id" :user="u" />
</div>
```

`**user`-Objekt** (API-Felder, snake_case):

- `first_name`, `last_name`, `nickname`
- `avatar_initials`, `background_color`, `text_color`
- optional `name` (Anzeigename-Fallback)

**Tooltip (Hover):**

- Zeile 1: `Name: Nachname Vorname`
- Zeile 2: `Spitzname: …` (nur wenn gesetzt)

Tooltip wird per Teleport im `body` gerendert (`z-index: 10000`), damit er nicht von Tabellen-`overflow` abgeschnitten wird.

**Styles:** `frontend/src/styles/components/user-avatar-badge.css`  
**Logik:** `frontend/src/utils/userAvatar.ts` (`UserAvatarFields`, `getUserAvatarStyle`, `getMemberHoverTooltip`, …)

**Geplant / sinnvoll einsetzbar in:** Aktivitäten (Historie, Kommentare), Reparaturen/Workshop — sobald die API die Profilfelder mitliefert.

**Hinweis:** In der Nachrichtenzentrale und Glocke nicht direkt `UserAvatarBadge` für alle Einträge nutzen, sondern `NotificationSenderBlock` (siehe unten) — dort gibt es auch System- und Aufgaben-Quellen ohne App-Benutzer.

---

### Benachrichtigungen / Posteingang


| Baustein                     | Pfad                                                                   | Verwendung                                                                 |
| ---------------------------- | ---------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| **NotificationSenderBlock**  | `frontend/src/components/notifications/NotificationSenderBlock.vue`    | Linkes Icon/Avatar in Posteingangszeilen (Glocke, Nachrichtenzentrale)     |
| **notificationSender** (Util)| `frontend/src/utils/notificationSender.ts`                             | Deskriptoren, Factories, `getSenderPrimaryLine`                            |
| **useNotificationSender**    | `frontend/src/composables/useNotificationSender.ts`                      | Factories mit i18n-Labels (`notificationsCenter.sender*`)                |


**Quellen-Typen (`NotificationSenderKind`)**


| `kind`       | Bedeutung                         | Darstellung                                      | Typische Quelle                          |
| ------------ | --------------------------------- | ------------------------------------------------ | ---------------------------------------- |
| `user`       | App-Benutzer                      | `UserAvatarBadge`                                | Department-Einladung (eingeladen von …)  |
| `system`     | System-/Workflow-Kanal            | Blaues Layer-Icon, optional kleiner User-Overlay | Neue Aktivität eingereicht (MW/DC)       |
| `task`       | Aufgabe, kein App-Benutzer        | Oranges Clipboard-Icon                           | QR-Kontakt von öffentlicher Material-Seite |
| `department` | Abteilung als Absender            | Abteilungs-Avatar (Initialen)                    | Einladung zu Camp/Anlass                 |


**Import & Verwendung**

```vue
<script setup lang="ts">
import { NotificationSenderBlock } from '@/components/notifications'
import { useNotificationSender } from '@/composables/useNotificationSender'
import { getSenderPrimaryLine } from '@/utils/notificationSender'

const { fromActivityMw, fromPublicFound, fromDepartmentInvite, fromActivityInvite } =
  useNotificationSender()
</script>

<template>
  <!-- Nur das linke Bild/Icon -->
  <NotificationSenderBlock :sender="fromActivityMw(entry)" size="md" />

  <!-- „Von“-Zeile daneben im Eltern-Layout -->
  <span class="nc-inbox-row__from">
    {{ getSenderPrimaryLine(fromActivityMw(entry)) }}
  </span>
</template>
```

**Factories (`useNotificationSender`)**


| Methode                 | API-Typ                              | Ergebnis                                                                 |
| ----------------------- | ------------------------------------ | ------------------------------------------------------------------------ |
| `fromActivityMw`        | `ActivityMwNotification`             | `system` + Label „Aktivitäten“, `sublabel` = Ersteller, Overlay-Avatar   |
| `fromPublicFound`       | `PublicFoundItemMessage`               | `task` / `qr_contact`, Label = Materialname, `sublabel` = Finder (falls vorhanden) |
| `fromDepartmentInvite`  | `ReceivedDepartmentInviteNotification` | `user`, Profil des Einladenden                                         |
| `fromActivityInvite`    | `PendingDepartmentActivityInvite`      | `department`, Name der einladenden Abteilung                             |


**`NotificationSenderBlock` — Props**

- `sender` — `NotificationSenderDescriptor` (Pflicht)
- `size` — `'sm' | 'md' | 'lg'` (Standard: `sm`)
- `showTooltip` — boolean (Standard: `false`); bei `user` wird an `UserAvatarBadge` durchgereicht

**Textzeilen in der Inbox (Konvention)**


| Quelle        | `getSenderPrimaryLine`     | Betreff (Beispiel)              | Vorschau (Beispiel)        |
| ------------- | -------------------------- | ------------------------------- | -------------------------- |
| Aktivität     | `Aktivitäten · Max Muster` | `«Sommerlager»`                 | Typ · Gruppe · #001        |
| QR-Aufgabe    | `Zelt · finder@mail.ch`    | `Kontaktanfrage (QR)`           | Nachrichtentext            |
| Dept-Einladung| `Anna Beispiel`            | `Einladung: Abteilung XY`       | `Rolle: Materialwart`      |


**i18n** (`notificationsCenter` in `de.json` u. a.):

- `senderSystemDefault`, `senderSystemActivity`, `senderTaskQr`
- `qrTaskSubject` — Betreffzeile für QR-Einträge (Aufgabe, keine Personen-Nachricht)

**Styles:** `frontend/src/styles/components/notification-sender-block.css`

**Eingebunden in:** `NotificationsCenterView.vue`, `TopHeader.vue` (Glocke)

**Architektur (Erzeugung, Speicher, APIs):** [nachrichtenzentrale.md](./nachrichtenzentrale.md)

**Neue System-Meldung hinzufügen:**

1. In `notificationSender.ts`: Factory + ggf. `systemVariant` erweitern
2. Label in `useNotificationSender` / `de.json` ergänzen
3. In `NotificationSenderBlock.vue`: optional eigenes Icon für die Variante
4. Posteingangs-View: `NotificationSenderBlock` + `getSenderPrimaryLine` wie oben

---

---

### Aktivitäten / Datum, Zeitraum & Uhrzeit

Zentrale Datepicker- und Uhrzeit-Bausteine (Vuetify `VDatePicker` / `VTimePicker`). **Detail-Doku:** [ui/activity-datetime-fields.md](./ui/activity-datetime-fields.md) · Referenz-UI: Sandbox `/{departmentId}/sandbox` → «Aktivität Zeitraum» · Wizard: `ActivityZeitraumDatetimeFields`.

| Baustein | Pfad | Verwendung |
| -------- | ---- | ---------- |
| **ActivityDateTimeFields** | `frontend/src/components/activities/wizard/ActivityDateTimeFields.vue` | **Empfohlen:** eine Zeile Datum (ein Tag **oder** Zeitraum) + optional Von/Bis-Uhr |
| **ActivityZeitraumDatetimeFields** | `frontend/src/components/activities/shared/ActivityZeitraumDatetimeFields.vue` | Create-Wizard + Draft: Nutzung + Material-Planung inkl. Aktivitätstyp |
| **ActivityDateField** | `…/wizard/ActivityDateField.vue` | Einzeldatum |
| **ActivityDateRangeField** | `…/wizard/ActivityDateRangeField.vue` | Zeitraum (Doppelkalender ab `sm`) |
| **ActivityTimeField** | `…/wizard/ActivityTimeField.vue` | Uhrzeit (15-Min-Raster, VTimePicker) |
| **ActivityResponsiveDateTimeRow** | `…/wizard/ActivityResponsiveDateTimeRow.vue` | Nur Layout: Pill ab `sm`, Mobile 2-zeilig |
| **useActivityDatePickerEvents** | `frontend/src/composables/useActivityDatePickerEvents.ts` | Marker (Feiertage, fcal, Fixe Daten), `allowedDates`, Mat-Büro-Sperre |
| **useActivityDatePresets** | `frontend/src/composables/useActivityDatePresets.ts` | Schnellauswahl-Liste (Samstage + Fixe Daten) |

**ActivityDateTimeFields — Import & Props**

```vue
import { ActivityDateTimeFields } from '@/components/activities/wizard'

<!-- Ein Tag + Uhrzeiten, mit Kalender-Punkten -->
<ActivityDateTimeFields
  v-model:day="day"
  v-model:time-from="timeFrom"
  v-model:time-to="timeTo"
  date-mode="single"
  :department-id="departmentId"
  :show-presets="false"
  :show-markers="true"
  label-from="Von"
  label-to="Bis"
/>

<!-- Zeitraum + Schnellauswahl (Lager/Event) -->
<ActivityDateTimeFields
  v-model:range="range"
  v-model:time-from="timeFrom"
  v-model:time-to="timeTo"
  date-mode="range"
  :department-id="departmentId"
  :show-presets="true"
  :show-markers="true"
  label-from="Von"
  label-to="Bis"
/>

<!-- Nur Datum, ohne Uhr -->
<ActivityDateTimeFields
  v-model:day="day"
  date-mode="single"
  :show-time="false"
  label-from=""
  label-to=""
/>
```

| Prop | Default | Bedeutung |
| ---- | ------- | --------- |
| `date-mode` | — | `'single'` \| `'range'` |
| `show-presets` | `false` | Schnellauswahl (Samstage + Lagerwoche/Sonstiges) |
| `show-markers` | `true` | Punkte/Tooltip im Kalender; `false` = keine Punkte, keine Sperre über Fixe Daten |
| `show-time` | `true` | Von/Bis-`ActivityTimeField` |
| `department-id` | `null` | Marker + Fixe Daten laden |
| `disabled` / `times-locked` | `false` | Datum / Uhr sperren |
| `blocked-usage-range` | `null` | Material-Uhr: Nutzungsintervall nicht wählbar |
| `layout` | `'auto'` | `'auto'` \| `'pill'` \| `'stacked'` |

**v-model:** `day` (single), `range` (range), `timeFrom`, `timeTo`.

**Kalender-UX:** Desktop Zeitraum = Doppelkalender + Schnellauswahl rechts; Mobile = ein Kalender + Schnellauswahl unten; Navigation = Pfeile, Mausrad, Touch **links/rechts**. Schnellauswahl Zeitraum: nur **Lagerwoche** / **Sonstiges** (+ Samstage), keine Schulferien/Mat-Büro in der Liste.

**Einzelbausteine** (`ActivityDateField` / `ActivityDateRangeField`): Props `show-presets`, `show-markers`, `department-id`. Zeitraum: `show-preset-sidebar` ist Alias für `show-presets` (deprecated).

**Eingebunden in:** `ActivityCreateWizardForm.vue`, `ActivityDraftOverviewForm.vue`, Sandbox `ActivityDatetimeSandboxFields.vue`.

**Styles:** `frontend/src/styles/components/activity-datetime-field.css`, `activity-datetime-layout.css` (global in `style.css` bzw. Wizard-CSS).

**Fixe Daten / Marker:** [setting/fixe-daten.md](./setting/fixe-daten.md)

---

### Aktivitäten / Packliste (Pack-Step-UI)


| Baustein | Pfad | Verwendung |
| -------- | ---- | ---------- |
| **packStepUi** | `frontend/src/components/activities/packStepUi.ts` | Presets für Kisten-Links, Spiegel-Rechts, Confirm-Config |
| **PackStepCrateSection** | `…/PackStepCrateSection.vue` | Linkes Kisten-Panel (Titel, Hint, Aria) |
| **PackStepMirrorSection** | `…/PackStepMirrorSection.vue` | Rechtes «Bereits …»-Panel (Slots `#crates`, `#loose`) |
| **PackStepContainerCard** | `…/PackStepContainerCard.vue` | Router zu Issue- oder Return-Kistenkarte |
| **confirmWorkflowStatusTransition** | `…/usePackWorkflowConfirm.ts` | Gemeinsame Confirm-Logik vor `at_event` / `returned` |

**Design-Vorlage:** Stufe **Gepackt → Am Event** (`PackWarehouseIssueContainerCard`). Retour und weitere Schritte spiegeln dieses Layout über Presets — Details: [activities/pack-step-ui.md](./activities/pack-step-ui.md).

**Import & Verwendung (Auszug)**

```vue
<script setup lang="ts">
import PackStepCrateSection from '@/components/activities/PackStepCrateSection.vue'
import PackStepMirrorSection from '@/components/activities/PackStepMirrorSection.vue'
import PackStepContainerCard from '@/components/activities/PackStepContainerCard.vue'
import {
  PACK_CRATE_SECTION_FORWARD_WAREHOUSE_LEFT,
  PACK_MIRROR_SECTION_FORWARD_AT_EVENT,
} from '@/components/activities/packStepUi'
</script>

<template>
  <PackStepCrateSection :preset="PACK_CRATE_SECTION_FORWARD_WAREHOUSE_LEFT">
    <PackStepContainerCard
      v-for="c in crates"
      :key="c.id"
      :container="c"
      mode="warehouse_issue"
      :stage-right-label="rightLabel"
    />
  </PackStepCrateSection>

  <PackStepMirrorSection :preset="PACK_MIRROR_SECTION_FORWARD_AT_EVENT">
    <template #crates><!-- … --></template>
    <template #loose><!-- … --></template>
  </PackStepMirrorSection>
</template>
```

**Preset-Konstanten (Auswahl)**


| Konstante | Pack-Stufe |
| --------- | ---------- |
| `PACK_CRATE_SECTION_CONFIRMED_PACKED_RIGHT` | Bestätigt → Gepackt (rechts, Kisten) |
| `PACK_MIRROR_SECTION_CONFIRMED_PACKED_LOOSE` | Bestätigt → Gepackt (rechts, lose) |
| `PACK_CRATE_SECTION_FORWARD_WAREHOUSE_LEFT` | Gepackt → Am Event (links) |
| `PACK_MIRROR_SECTION_FORWARD_AT_EVENT` | Gepackt → Am Event (rechts, «Bereits ans Event») |
| `PACK_CRATE_SECTION_RETURN_AT_EVENT_LEFT` | Am Event → Retour (links) |
| `PACK_MIRROR_SECTION_RETURN_DONE` | Am Event → Retour (rechts, «Bereits retourniert») |

**Karten-Modi (`PackContainerCardMode`):** `confirmed_packed_target`, `warehouse_issue`, `warehouse_issue_mirror`, `at_event_return`, `at_event_return_mirror` — siehe [pack-step-ui.md](./activities/pack-step-ui.md).

**Eingebunden in:** `ActivityPackListTab.vue` (Packliste-Tab). Karten-Logik weiter über `PACK_WAREHOUSE_ISSUE_INJECT_KEY` aus der Packliste.

**Neue Pack-Stufe:** Preset in `packStepUi.ts`, Computeds in `ActivityPackListTab.vue`, i18n-Keys — Checkliste in [pack-step-ui.md](./activities/pack-step-ui.md#neue-pack-stufe-anbinden).

---

### Adressen / Department (`frontend/src/components/addresses/`)


| Baustein                           | Pfad                                                              | Verwendung                                                                 |
| ---------------------------------- | ----------------------------------------------------------------- | -------------------------------------------------------------------------- |
| **DepartmentAddressAutocomplete**  | `frontend/src/components/addresses/DepartmentAddressAutocomplete.vue` | Suche in Department-Adressen mit Typ-Priorität, Trenner, Inline-Anlegen   |
| **departmentAddressSearch** (Util) | `frontend/src/utils/departmentAddressSearch.ts`                   | `formatAddressOption`, `addressMatchesQuery`, `groupDepartmentAddressesForSearch` |


**DepartmentAddressAutocomplete — Import & Props**

```vue
import { DepartmentAddressAutocomplete } from '@/components/addresses'

<DepartmentAddressAutocomplete
  input-id="activity-venue-address-search"
  :addresses="rentalAddresses"
  :selected-id="venueAddressId"
  primary-type="event"
  :placeholder="t('activities.wizard.form.addressSearchPlaceholder')"
  :add-button-title="t('activities.wizard.form.addVenueAddressTitle')"
  inline-create-label-key="addresses.search.createEventVenueInline"
  @update:selected-id="emit('update:venueAddressId', $event)"
  @create="openAddVenueAddressModal"
/>
```

**Verhalten:**

- Treffer mit `primary-type` (z. B. `event`) oben
- Übrige Adresstypen darunter mit Trenner «Andere Standorte»
- Kein Treffer: klickbarer Inline-Vorschlag «{query} als … anlegen» → Eltern öffnet `AddressModal` mit `:default-name`
- Nach `@saved` im Modal: Eltern lädt Adressen neu und setzt `selected-id`

**Styles:** Layout in `department-address-autocomplete.css`; Typ-Badges global via `address-type-badge.css` (`.address-type-badge` + `.address-type-badge--compact`)

**Eingebunden in:** `ActivityCreateWizardForm.vue` (Eventstandort bei Lager/Event/Extern)

**Adress-Typ-Badge (global)**

```vue
<span class="address-type-badge" :class="address.type">{{ typeLabel }}</span>
<!-- Kompakt (Autocomplete-Dropdown): -->
<span class="address-type-badge address-type-badge--compact" :class="address.type">{{ typeLabel }}</span>
```

Typ-Schlüssel entsprechen `ADDRESS_TYPES` in `api/addresses.ts` (`storage`, `event`, `customer`, …).

---

---

### Layout (`frontend/src/components/layout/`)

| Komponente | Kurzbeschreibung |
| ---------- | ---------------- |
| `PageShell` | Seiten-Grundgerüst: `v-container`, Slots `#title`, `#subtitle`, `#actions`, `#filters`, default |
| `EFilterRow` | Filter-Zeile (Suche + Selects + Aktionen) — Inhalt via `v-col`-Slots |
| `EEmptyState` | Leer-/Keine-Treffer-Zustände: Icon-Kreis, Titel, Beschreibung, `#actions` |
| `ELoadingState` | Ladezustände: Spinner oder `v-skeleton-loader` (table/list/card/inline) |
| `EResponsiveDataList` | Responsive Listen: Desktop `#table`, Mobile `#mobile` — Breakpoint `useDisplay().mdAndUp` |

**EEmptyState — Import & Props**

```vue
import EEmptyState from '@/components/layout/EEmptyState.vue'

<EEmptyState
  variant="create"
  :title="t('contacts.emptyTitle')"
  :description="t('contacts.emptyText')"
>
  <template #actions>
    <EButton @click="openCreateModal">{{ t('contacts.emptyCta') }}</EButton>
  </template>
</EEmptyState>
```

| Prop | Werte | Default |
| ---- | ----- | ------- |
| `variant` | `generic` \| `create` \| `search` | `generic` |
| `icon` | MDI-Name (überschreibt variant-Icon) | — |
| `compact` | weniger Padding (z. B. Tabellenzellen) | `false` |
| `headingLevel` | `2` \| `3` | `2` |

Slots: `#illustration` (eigenes SVG statt Icon), `#title`, `#description`, `#actions`, default.

**Pilot:** `ContactsView` (leere Liste + keine Filter-Treffer). **Sandbox:** `/{departmentId}/dev/ui-playground`.

**ELoadingState — Import & Props**

```vue
import ELoadingState from '@/components/layout/ELoadingState.vue'

<!-- Listen/Tabellen (Initial-Load) -->
<ELoadingState variant="table" :rows="8" :message="t('contacts.loadingList')" />

<!-- Einfache Seite -->
<ELoadingState variant="page" :message="t('common.loading')" />

<!-- Inline neben Text/Button -->
<ELoadingState variant="inline" message="Speichert…" />
```

| Prop | Werte | Default |
| ---- | ----- | ------- |
| `variant` | `page` \| `table` \| `list` \| `card` \| `inline` | `page` |
| `rows` | Anzahl Skeleton-Zeilen (`table`, `list`) | `6` |
| `message` | Optionaler Text unter/neben dem Loader | — |
| `compact` | Weniger Padding (`page`) | `false` |

**Wann welche Variante:** `table` für HTML-Tabellen-Views; `list` für Avatar-Zeilen; `page` für einfache Full-Page-Loads; `inline` für AutoSave/Buttons. Legacy `.loading-state` / `.spinner` bleiben in nicht migrierten Views.

**Pilot:** `ContactsView` (table). **Sandbox:** Abschnitt Ladezustände.

**EResponsiveDataList — Import & Slots**

```vue
import EResponsiveDataList from '@/components/layout/EResponsiveDataList.vue'
import MaterialListDataTable from '@/components/material/MaterialListDataTable.vue'
import MaterialListMobile from '@/components/material/MaterialListMobile.vue'

<EResponsiveDataList>
  <template #table>
    <MaterialListDataTable … />
  </template>
  <template #mobile>
    <MaterialListMobile … />
  </template>
</EResponsiveDataList>
```

| Slot | Inhalt |
| ---- | ------ |
| `#table` | Desktop ab `md` (960px): HTML-`<table>`, `v-data-table` in List-Komponente, … |
| `#mobile` | Mobile `< md`: typisch `v-list` mit Karten-Zeilen |

**Wann nutzen:** Views mit hohem Mobile-Anteil und unterschiedlicher Darstellung (nicht nur horizontaler Scroll). Entscheid: [table-patterns.md](ui/table-patterns.md).

**Pilot:** `MaterialsView` (058). **Sandbox:** Dev UI Playground → Material Data Table.

**Tabellen:** Entscheid und Kategorien A/B/C — [table-patterns.md](ui/table-patterns.md). Kurz: reiche Listen → HTML-`<table>` oder `v-data-table` in List-Komponente; Mobile-Karten über `EResponsiveDataList`.

---

### Formular-Basis E* (`frontend/src/components/form/base/`)

Öffentliche Mittelschicht für Inputs, Buttons und Dialoge auf Vuetify. **Views importieren nur `E*`**, nie direkt `VTextField` & Co. (Ausnahme: Dev UI Playground mit rohen `V*` zum Vergleich).

| Komponente | Wraps | Kurzbeschreibung |
| ---------- | ----- | ---------------- |
| `ETextField` | `v-text-field` | Text/Email/Password, outlined, 16px Input |
| `ESearchField` | `SearchFieldInput` | Suche mit Lupe, Label auf Rahmen, grüner Fokus — für Filter-Zeilen |
| `ESelect` | `v-select` | Auswahl, gleiche Defaults |
| `ETextarea` | `v-textarea` | Mehrzeilig |
| `ECheckbox` / `ESwitch` | `v-checkbox` / `v-switch` | Boolean |
| `EButton` | `v-btn` | Varianten: primary, secondary, text, danger |
| `ECard` | `v-card` | Karten-Grundgerüst |
| `EDialog` | `v-dialog` + `ECard` | Modal mit Slots `title`, default, `actions` |

**Import**

```vue
import { ETextField, EButton, EDialog } from '@/components/form/base'

<ETextField v-model="email" :label="t('login.emailLabel')" autocomplete="username" />
<EButton type="submit" variant="primary" block :loading="saving">Speichern</EButton>
```

Gemeinsame Props: `label`, `disabled`, `readonly`, `hint`, `rules`, `errorMessages`. Details: [`form/base/README.md`](../../frontend/src/components/form/base/README.md) und [vuetify-standards.md](ui/vuetify-standards.md).

**Styles:** `e-form-field.css` (min. 16px Input für iOS). Legacy `.form-group` / `outlined-field.css` bleiben in nicht migrierten Views.

**Pilot:** `LoginView` (Login-Modus). **Sandbox:** `/{departmentId}/dev/ui-playground` — Abschnitt E*-Komponenten.

---

### Common (`frontend/src/components/common/`)


| Komponente                           | Kurzbeschreibung                                   |
| ------------------------------------ | -------------------------------------------------- |
| `GlobalConfirmDialog`                | App-weiter Bestätigungsdialog (`useConfirm`)       |
| `GlobalPromptDialog`                 | App-weiter Eingabe-Dialog (`usePrompt`)            |
| `GlobalToastContainer`               | Toasts (`useToast`)                                |
| `GlobalSearchInput`                  | Zentrale Suche mit Prefix/Navigation (Header, Material, Workshop) |
| `SearchFieldInput`                   | Outlined Listen-Suche: Lupe links (grün bei Hover), Label auf dem Rahmen |
| `MaterialLookupInput`                | Material-Suche/Autocomplete                        |
| `CategoryAutocompleteInput`          | Kategorie-Autocomplete                             |
| `BarcodeScannerPanel`                | Barcode/QR-Scanner                                 |
| `PublicQrTag`                        | QR-Badge für öffentliche Material-Links            |
| `DevEnvironmentBanner`               | Hinweis Testumgebung                               |
| `PhysicalComboContainerWarningModal` | Warnung physische Combo in Container               |
| **AutoSaveField** / **AutoSaveFieldShell** | Auto-Save: Hülle unverändert (Label, Balken, Diskette, Retry); Standardtypen innen **Vuetify** (`v-text-field` usw., Look wie `ETextField` via `e-form-field.css`) |


Diese werden meist global in `App.vue` eingebunden oder direkt in Views importiert.

**AutoSaveField — Import & Props**

```vue
import { AutoSaveField, useFormFieldBaselines } from '@/components/common/autoSave'

const formData = reactive({ name: '', category_id: '' })
const { baselines, syncBaselines, syncBaselineFor } = useFormFieldBaselines(formData)

<!-- Einfaches Textfeld -->
<AutoSaveField
  v-model="formData.name"
  :baseline="baselines.name"
  label="Name"
  :save="(v) => saveField('name', v)"
/>

<!-- Eigenes Steuerelement (Slot) -->
<AutoSaveField
  v-model="formData.category_id"
  :baseline="baselines.category_id"
  label="Kategorie"
  :save="(v) => saveField('category_id', v)"
>
  <template #default="{ onFocus, onBlur, onChange }">
    <CategoryAutocompleteInput
      v-model="formData.category_id"
      @focus="onFocus"
      @blur="onBlur"
      @change="onChange"
    />
  </template>
</AutoSaveField>
```

| Prop | Typ | Beschreibung |
| ---- | --- | ------------ |
| `v-model` | `string \| number \| boolean \| null` | Aktueller Feldwert |
| `baseline` | wie v-model | Letzter DB-Stand (Revert bei Blur ohne Änderung) |
| `label` | `string` | Floating Label |
| `save` | `(value) => Promise<void>` | PATCH/Speichern — wirft bei Fehler |
| `type` | `'text' \| 'number' \| 'date' \| 'textarea' \| 'select' \| 'checkbox'` | Standard: `text` |
| `autoSaveDelay` | `number` | Debounce in ms (Standard: 800) |
| `disabled` | `boolean` | Feld deaktivieren |

**Verhalten:** Debounce beim Tippen, zwei indeterminate Balken **unten** am Rahmen, Diskette nur kurz nach Erfolg, Blur ohne Änderung → Revert auf `baseline`, Fehler → Retry + Abbrechen, Select/Checkbox → sofort speichern.

**Innenumbau (Phase 3):** Views und Props bleiben gleich. Nur `AutoSaveField.vue` rendert für `text`/`number`/`date`/`textarea`/`select`/`checkbox` Vuetify-Felder; **Custom-Slots** (z. B. Kategorie-Autocomplete) bleiben native Inputs mit Klasse `autosave-input` / `form-input`. Shell: `e-form-field` + MDI (`mdi-content-save`, `mdi-refresh`, `mdi-close`).

**Eingebunden in:** `MaterialDetailView.vue` (Stammdaten, Vermietung, Kisten-Editor).

**Styles:** `auto-save-field.css` (Rahmen, Loader, Diskette) + global `e-form-field.css` (Vuetify-Feldlook). Marken-Tokens: `styles/ui/brand-tokens.css`.

**Composables:** `useAutoSaveField`, `useFormFieldBaselines`, Utils `normalizeAutoSaveValue` / `parseAutoSaveInputValue` — Export über `@/components/common/autoSave`.

---

### Icons (`frontend/src/components/icons/`)

SVG-Icons als Vue-Komponenten (`IconDashboard`, `IconMaterials`, `IconActivities`, …). In Navigation und Buttons per `markRaw(Icon…)` nutzen.

---

## Composables (`frontend/src/composables/`)


| Composable                           | Zweck                                                                         |
| ------------------------------------ | ----------------------------------------------------------------------------- |
| `useDepartmentMemberRole`            | Rolle im Department: `isUserRole`, `canManageMaterials`, `canManageQrContact` |
| `useConfirm`                         | Bestätigungsdialog                                                            |
| `usePrompt`                          | Text-Eingabe-Dialog                                                           |
| `useToast`                           | Erfolg/Fehler-Meldungen                                                       |
| `useMaterialLookup`                  | Material-Suche                                                                |
| `useListSearchQueryRoute`            | Such-Query in URL sync                                                        |
| `useUnsavedChangesReminder`          | Warnung bei ungespeicherten Änderungen                                        |
| `usePageHead`                        | Dokument-Titel/Meta                                                           |
| `useDepartmentSettingsManagerAccess` | Zugriff auf Department-Einstellungen                                          |
| `useActivityCreateWizard`            | Aktivität anlegen (Wizard-State)                                              |
| `useStorageStructure`                | Lager-Struktur                                                                |
| `useAutoLogout`                      | Session-Timeout                                                               |
| `useAutoSaveField`                   | Auto-Save-Logik pro Feld (Debounce, Loader, Blur-Revert, Retry) — siehe `AutoSaveField` |
| `useFormFieldBaselines`              | DB-Baseline pro Formularfeld für `:baseline` in `AutoSaveField`               |
| `useNotificationSender`              | Factories für `NotificationSenderBlock` (Posteingang/Glocke, inkl. i18n)      |
| `useActivityDatePickerEvents`        | Kalender-Marker + `department_break`-Sperre — siehe [activity-datetime-fields.md](./ui/activity-datetime-fields.md) |
| `useActivityDatePresets`             | Schnellauswahl-Liste für Datepicker (single/range)                            |
| `useActivityDateRangePicker`         | Range-Hover, Menü-Schliessen nach 2. Klick                                    |
| `confirmWorkflowStatusTransition`    | Confirm vor Pack-Workflow-Status `at_event` / `returned` (siehe `usePackWorkflowConfirm.ts`) |
| `useMediaUpload` *(geplant)*         | FormData-Foto-Upload für kontextspezifische Routes — siehe [media/plan.md](./media/plan.md) Paket 6 |


---

## UI-System (Vuetify, Schichten V → E → AutoSave)

Verbindliche Regeln und Präfix **`E*`** (eMatChef-Design-Wrapper): [ui/vuetify-standards.md](./ui/vuetify-standards.md). Migrationsplan (gemeinsam abarbeiten): [ui/vuetify-migration-plan.md](./ui/vuetify-migration-plan.md).

---

## Zentrale CSS

Ausführlich: `[docs/Archiv/HANDOUT_CSS_ZENTRALISIERUNG.md](Archiv/HANDOUT_CSS_ZENTRALISIERUNG.md)`


| Modul           | Pfad                                      | Inhalt                                      |
| --------------- | ----------------------------------------- | ------------------------------------------- |
| Buttons         | `styles/ui/buttons.css`                   | `.btn`, `.btn-primary`, `.btn-secondary`, … |
| Inline «+»      | `styles/ui/inline-add-button.css`         | `.add-inline-btn` — gestrichelter Rand, grünes «+» neben Autocomplete |
| Layout          | `styles/ui/page-layout.css`               | `.page-header`, Filter, …                   |
| Formulare       | `styles/ui/forms.css`                     | Inputs, Labels                              |
| Karten          | `styles/ui/cards.css`                     | Card-Patterns                               |
| Modals          | `styles/ui/modals.css`                    | Modal-Grundgerüst                           |
| Tabellen        | `styles/ui/tables.css`                    | Listen-Basis — siehe [table-patterns.md](ui/table-patterns.md) |
| States          | `styles/ui/states.css`                    | Loading, Empty, Error                       |
| History         | `styles/ui/history.css`                   | Änderungs-Historie                          |
| Storage         | `styles/ui/storage.css`                   | Lager/Regale                                |
| **User-Avatar** | `styles/components/user-avatar-badge.css` | Avatar-Badge + Liste                        |
| **Adress-Typ**  | `styles/components/address-type-badge.css` | `.address-type-badge.{type}` + Avatar-Farben (Kontakte, Autocomplete) |
| **Auto-Save-Feld** | `styles/components/auto-save-field.css` | AutoSaveField: Loader, Diskette, Fokus/Fehler — Marken-Tokens |
| **Sender (Inbox)** | `styles/components/notification-sender-block.css` | System-/Aufgaben-Icons, Actor-Overlay |
| **Activity Datum/Zeitraum** | `styles/components/activity-datetime-field.css`, `activity-datetime-layout.css` | VDatePicker-Menü, Pill-Zeile, Range-Farben — siehe [activity-datetime-fields.md](./ui/activity-datetime-fields.md) |


**Faustregel:** Kommt ein Style in 2+ Screens vor → zentral in `styles/ui/`* oder `styles/components/*`. Domain-spezifisch bleibt in der View (`styles/views/…`).

Globaler Einstieg: `frontend/src/style.css`

---

## Neue Bausteine dokumentieren

Wenn du eine Komponente/Util für mehrere Bereiche einführst:

1. Datei unter `components/<bereich>/` oder `utils/`
2. Optional CSS unter `styles/components/`
3. **Eintrag in dieser Datei** (Tabelle + kurzes Code-Beispiel); bei größeren Themen zusätzlich Detail-Doku unter `docs/ui/` (z. B. [activity-datetime-fields.md](./ui/activity-datetime-fields.md))
4. API-Felder dokumentieren, falls Backend-Daten nötig sind

