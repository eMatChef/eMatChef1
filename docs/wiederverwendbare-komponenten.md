# Wiederverwendbare Bausteine (Frontend)

Übersicht über zentrale Vue-Komponenten, Composables, Utils und CSS, die in mehreren Bereichen der App genutzt werden sollen — statt Logik oder Styles pro View neu zu schreiben.

**Stand:** Mai 2026

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

### Common (`frontend/src/components/common/`)


| Komponente                           | Kurzbeschreibung                                   |
| ------------------------------------ | -------------------------------------------------- |
| `GlobalConfirmDialog`                | App-weiter Bestätigungsdialog (`useConfirm`)       |
| `GlobalPromptDialog`                 | App-weiter Eingabe-Dialog (`usePrompt`)            |
| `GlobalToastContainer`               | Toasts (`useToast`)                                |
| `GlobalSearchInput`                  | Einheitliche Suchleiste (z. B. Material, Workshop) |
| `MaterialLookupInput`                | Material-Suche/Autocomplete                        |
| `CategoryAutocompleteInput`          | Kategorie-Autocomplete                             |
| `BarcodeScannerPanel`                | Barcode/QR-Scanner                                 |
| `PublicQrTag`                        | QR-Badge für öffentliche Material-Links            |
| `DevEnvironmentBanner`               | Hinweis Testumgebung                               |
| `PhysicalComboContainerWarningModal` | Warnung physische Combo in Container               |


Diese werden meist global in `App.vue` eingebunden oder direkt in Views importiert.

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
| `useNotificationSender`              | Factories für `NotificationSenderBlock` (Posteingang/Glocke, inkl. i18n)      |
| `confirmWorkflowStatusTransition`    | Confirm vor Pack-Workflow-Status `at_event` / `returned` (siehe `usePackWorkflowConfirm.ts`) |


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
| Tabellen        | `styles/ui/tables.css`                    | Tabellen-Basis                              |
| States          | `styles/ui/states.css`                    | Loading, Empty, Error                       |
| History         | `styles/ui/history.css`                   | Änderungs-Historie                          |
| Storage         | `styles/ui/storage.css`                   | Lager/Regale                                |
| **User-Avatar** | `styles/components/user-avatar-badge.css` | Avatar-Badge + Liste                        |
| **Adress-Typ**  | `styles/components/address-type-badge.css` | `.address-type-badge.{type}` + Avatar-Farben (Kontakte, Autocomplete) |
| **Sender (Inbox)** | `styles/components/notification-sender-block.css` | System-/Aufgaben-Icons, Actor-Overlay |


**Faustregel:** Kommt ein Style in 2+ Screens vor → zentral in `styles/ui/`* oder `styles/components/*`. Domain-spezifisch bleibt in der View (`styles/views/…`).

Globaler Einstieg: `frontend/src/style.css`

---

## Neue Bausteine dokumentieren

Wenn du eine Komponente/Util für mehrere Bereiche einführst:

1. Datei unter `components/<bereich>/` oder `utils/`
2. Optional CSS unter `styles/components/`
3. **Eintrag in dieser Datei** (Tabelle + kurzes Code-Beispiel)
4. API-Felder dokumentieren, falls Backend-Daten nötig sind

