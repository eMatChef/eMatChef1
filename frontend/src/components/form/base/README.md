# E* — Formular-Basis (Vuetify)

**Ein Standard für die ganze App.** Views nutzen nur `E*`, nie direkt `VTextField` & Co.

## Architektur

```
View  →  ETextField / EButton / …  →  Vuetify (variant=outlined)  →  e-form-field.css (Look)
```

| Schicht | Verantwortung |
| ------- | ------------- |
| **E*-Komponente** | API (`label`, `v-model`, `rules`), immer `variant="outlined"` |
| **Vuetify** | Verhalten, Outline-Mechanik (`.v-field__outline`), A11y |
| **`e-form-field.css`** | eMatChef-Look: Farben, Radius, Fokus-Ring — global in `style.css` |
| **`outlined-field.css`** | Label auf dem Rahmen (`.field-outline-label`) — wiederverwendet |

Keine Vuetify-`defaults` in `vuetify.ts` für Formulare — der Standard lebt in **E*** + **CSS**.

## Komponenten

| Komponente | Vuetify | Variant |
| ---------- | ------- | ------- |
| `ETextField` | `v-text-field` | `outlined` |
| `ESearchField` | `SearchFieldInput` | Lupe, Label auf Rahmen — `search-field.css` |
| `ESelect` | `v-select` | `outlined` |
| `ETextarea` | `v-textarea` | `outlined` |
| `ECheckbox` / `ESwitch` | `v-checkbox` / `v-switch` | `color=primary` |
| `EButton` | `v-btn` | primary / secondary / text / danger → `e-button.css` |
| `ECard` / `EDialog` | `v-card` / `v-dialog` | `e-card.css` |

## Label

Label **extern** (`.field-outline-label`) — wie AutoSave/Material-Detail.  
Vuetify bekommt **kein** `label`-Prop → voller Outline-Rahmen ohne Notch-Lücke.

## Look (Tokens)

Import in **`App.vue`** (nach Vuetify-Komponenten-CSS). Sichtbarer Rand via `box-shadow: inset` auf `.v-field` — Vuetifys Outline allein war bei opacity 0.38 unsichtbar.

Aus `brand-tokens.css` / `auto-save-field.css`:

- Rahmen: Ruhe `var(--color-border)` (#d1d5db), Fokus `var(--color-primary-light)` (#10b981)
- Text: Ruhe `var(--color-text-muted)` (#6b7280), Fokus `var(--color-text)` (#111827)
- Hover: `var(--color-surface-muted)` Hintergrund
- Label: `.field-outline-label` — wie MaterialDetail / AutoSave
- Input: min. 16px (iOS)

## Migration

1. View: `<input class="form-input">` → `<ETextField v-model="…" label="…" />`
2. Kein eigenes Feld-CSS in der View
3. AutoSave (Phase 3): Shell behält Label, innen `ETextField`
4. Alte `.form-input` / `outlined-field.css` bleiben in **nicht migrierten** Views bis Umstellung

## Sandbox

Dev-Host: `/{departmentId}/dev/ui-playground` — Abschnitt **E*-Komponenten**
