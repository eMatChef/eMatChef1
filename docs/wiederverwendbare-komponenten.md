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

**Geplant / sinnvoll einsetzbar in:** Aktivitäten (Historie, Kommentare), Aufgaben, Nachrichten, Reparaturen/Workshop — sobald die API die Profilfelder mitliefert.

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


---

## Zentrale CSS

Ausführlich: `[docs/Archiv/HANDOUT_CSS_ZENTRALISIERUNG.md](Archiv/HANDOUT_CSS_ZENTRALISIERUNG.md)`


| Modul           | Pfad                                      | Inhalt                                      |
| --------------- | ----------------------------------------- | ------------------------------------------- |
| Buttons         | `styles/ui/buttons.css`                   | `.btn`, `.btn-primary`, `.btn-secondary`, … |
| Layout          | `styles/ui/page-layout.css`               | `.page-header`, Filter, …                   |
| Formulare       | `styles/ui/forms.css`                     | Inputs, Labels                              |
| Karten          | `styles/ui/cards.css`                     | Card-Patterns                               |
| Modals          | `styles/ui/modals.css`                    | Modal-Grundgerüst                           |
| Tabellen        | `styles/ui/tables.css`                    | Tabellen-Basis                              |
| States          | `styles/ui/states.css`                    | Loading, Empty, Error                       |
| History         | `styles/ui/history.css`                   | Änderungs-Historie                          |
| Storage         | `styles/ui/storage.css`                   | Lager/Regale                                |
| **User-Avatar** | `styles/components/user-avatar-badge.css` | Avatar-Badge + Liste                        |


**Faustregel:** Kommt ein Style in 2+ Screens vor → zentral in `styles/ui/`* oder `styles/components/*`. Domain-spezifisch bleibt in der View (`styles/views/…`).

Globaler Einstieg: `frontend/src/style.css`

---

## Neue Bausteine dokumentieren

Wenn du eine Komponente/Util für mehrere Bereiche einführst:

1. Datei unter `components/<bereich>/` oder `utils/`
2. Optional CSS unter `styles/components/`
3. **Eintrag in dieser Datei** (Tabelle + kurzes Code-Beispiel)
4. API-Felder dokumentieren, falls Backend-Daten nötig sind

