<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BarcodeScannerPanel from '@/components/common/BarcodeScannerPanel.vue'
import EButton from '@/components/form/base/EButton.vue'
import type { MaterialJourneyScanTouchPref } from '@/components/activities/materialJourney/materialJourneyScanTouchPref'
import {
  readMaterialJourneyScanTouchPref,
  writeMaterialJourneyScanTouchPref,
} from '@/components/activities/materialJourney/materialJourneyScanTouchPref'
import type { MaterialScanSessionEntry } from '@/composables/useMaterialJourneyScan'
import { useMaterialJourneySheetDialog } from '@/composables/useMaterialJourneySheetDialog'
import { useToast } from '@/composables/useToast'
import { canRequestCamera } from '@/utils/cameraAccess'
import { localizedBarcodeScannerError } from '@/utils/barcodeScannerErrors'
import { isScanLikeInput } from '@/utils/scanParser'

export type MaterialJourneyScanSuggestion = {
  id: string
  label: string
  subtitle?: string | null
  categoryName?: string | null
}

const props = defineProps<{
  modelValue: string
  loading: boolean
  sessionLog: MaterialScanSessionEntry[]
  labelKey?: string
  placeholderKey?: string
  inputId?: string
  packTargetLabel?: string | null
  suggestions?: MaterialJourneyScanSuggestion[]
  typeaheadMinChars?: number
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  submit: []
  clear: []
  deselect: []
  'select-suggestion': [item: MaterialJourneyScanSuggestion]
}>()

const { t } = useI18n()
const toast = useToast()
const { sheetFullscreen: cameraFullscreen, sheetMaxWidth: cameraMaxWidth } =
  useMaterialJourneySheetDialog({ maxWidth: 520 })
const inputRef = ref<HTMLInputElement | null>(null)
const menuOpen = ref(false)
const cameraOpen = ref(false)
const keyboardEnabled = ref(false)
const coarsePointer = ref(false)
const scanCooldown = ref(false)
const rememberForSession = ref(false)
const sessionPref = ref<MaterialJourneyScanTouchPref | null>(null)
const typeaheadOpen = ref(false)
const activeSuggestionIndex = ref(0)

const typeaheadEnabled = computed(() => (props.suggestions?.length ?? 0) > 0)

const effectivePlaceholder = computed(() =>
  t(props.placeholderKey ?? 'activities.materialJourney.scan.placeholder'),
)

const effectiveTypeaheadMinChars = computed(() => props.typeaheadMinChars ?? 1)

const filteredSuggestions = computed((): MaterialJourneyScanSuggestion[] => {
  if (!typeaheadEnabled.value || !props.suggestions) return []
  const raw = props.modelValue
  if (isScanLikeInput(raw)) return []
  const q = raw.trim().toLowerCase()
  if (q.length < effectiveTypeaheadMinChars.value) return []
  return props.suggestions
    .filter(
      (item) =>
        item.label.toLowerCase().includes(q) ||
        (item.subtitle?.toLowerCase().includes(q) ?? false) ||
        (item.categoryName?.toLowerCase().includes(q) ?? false),
    )
    .slice(0, 12)
})

const showTypeaheadDropdown = computed(
  () => typeaheadOpen.value && filteredSuggestions.value.length > 0,
)

const inputReadonly = computed(
  () => coarsePointer.value && !keyboardEnabled.value && sessionPref.value !== 'type',
)

const showTouchHints = computed(() => coarsePointer.value)

onMounted(() => {
  coarsePointer.value =
    typeof window !== 'undefined' &&
    window.matchMedia('(pointer: coarse)').matches
  sessionPref.value = readMaterialJourneyScanTouchPref()
  if (sessionPref.value === 'type') {
    keyboardEnabled.value = true
  }
  window.addEventListener('keydown', onSlashFocus)
})

onUnmounted(() => {
  window.removeEventListener('keydown', onSlashFocus)
})

watch(sessionPref, (pref) => {
  if (pref === 'type') keyboardEnabled.value = true
})

watch(
  () => props.modelValue,
  (value) => {
    activeSuggestionIndex.value = 0
    if (!typeaheadEnabled.value || isScanLikeInput(value)) {
      typeaheadOpen.value = false
      return
    }
    typeaheadOpen.value = value.trim().length >= effectiveTypeaheadMinChars.value
  },
)

function onInput(event: Event): void {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}

function onKeydown(event: KeyboardEvent): void {
  if (showTypeaheadDropdown.value) {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      activeSuggestionIndex.value = Math.min(
        activeSuggestionIndex.value + 1,
        filteredSuggestions.value.length - 1,
      )
      return
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault()
      activeSuggestionIndex.value = Math.max(activeSuggestionIndex.value - 1, 0)
      return
    }
    if (event.key === 'Enter') {
      event.preventDefault()
      const item = filteredSuggestions.value[activeSuggestionIndex.value]
      if (item) selectSuggestion(item)
      return
    }
    if (event.key === 'Escape') {
      typeaheadOpen.value = false
      return
    }
  }
  if (event.key === 'Enter') {
    event.preventDefault()
    emit('submit')
  }
  if (event.key === 'Escape') {
    emit('clear')
  }
}

function onSlashFocus(event: KeyboardEvent): void {
  if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return
  const target = event.target as HTMLElement | null
  if (target?.tagName === 'INPUT' || target?.tagName === 'TEXTAREA' || target?.isContentEditable) return
  event.preventDefault()
  void applyTouchPrefOrOpenMenu('type')
}

function formatTime(at: Date): string {
  return at.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

async function focusForTyping(): Promise<void> {
  keyboardEnabled.value = true
  await nextTick()
  inputRef.value?.focus()
}

function persistPrefIfNeeded(mode: MaterialJourneyScanTouchPref): void {
  if (!rememberForSession.value) return
  sessionPref.value = mode
  writeMaterialJourneyScanTouchPref(mode)
}

async function applyTouchPrefOrOpenMenu(fallback?: MaterialJourneyScanTouchPref): Promise<void> {
  const pref = sessionPref.value ?? fallback
  if (pref === 'camera') {
    openCamera()
    return
  }
  if (pref === 'type') {
    await focusForTyping()
    return
  }
  menuOpen.value = true
}

function onInputClick(): void {
  if (!coarsePointer.value) return
  if (sessionPref.value) {
    void applyTouchPrefOrOpenMenu()
    return
  }
  menuOpen.value = true
}

function onInputFocus(event: FocusEvent): void {
  if (!coarsePointer.value || sessionPref.value === 'type') return
  if (sessionPref.value) {
    ;(event.target as HTMLInputElement).blur()
    void applyTouchPrefOrOpenMenu()
    return
  }
  ;(event.target as HTMLInputElement).blur()
  menuOpen.value = true
}

async function chooseTypeFromMenu(): Promise<void> {
  persistPrefIfNeeded('type')
  menuOpen.value = false
  await focusForTyping()
}

function chooseCameraFromMenu(): void {
  persistPrefIfNeeded('camera')
  menuOpen.value = false
  openCamera()
}

function resetSessionPref(): void {
  sessionPref.value = null
  writeMaterialJourneyScanTouchPref(null)
  keyboardEnabled.value = false
  rememberForSession.value = false
}

function openCamera(): void {
  if (!canRequestCamera()) {
    toast.error(t('components.barcodeScanner.errorSecureContext'))
    return
  }
  cameraOpen.value = true
}

function closeCamera(): void {
  cameraOpen.value = false
}

function onScanDetected(payload: { text: string }): void {
  if (scanCooldown.value) return
  const text = payload.text.trim()
  if (!text) return

  scanCooldown.value = true
  emit('update:modelValue', text)
  closeCamera()
  void nextTick(() => {
    emit('submit')
    window.setTimeout(() => {
      scanCooldown.value = false
    }, 1200)
  })
}

function onScanError(message: string): void {
  toast.error(localizedBarcodeScannerError(message, t))
}

function selectSuggestion(item: MaterialJourneyScanSuggestion): void {
  typeaheadOpen.value = false
  emit('select-suggestion', item)
}

function onInputFocusTypeahead(event: FocusEvent): void {
  if (typeaheadEnabled.value && !isScanLikeInput(props.modelValue)) {
    typeaheadOpen.value = props.modelValue.trim().length >= effectiveTypeaheadMinChars.value
  }
  onInputFocus(event)
}

defineExpose({
  focus: () => void focusForTyping(),
  openCamera,
})
</script>

<template>
  <div
    class="material-journey-scan-bar"
    :class="{ 'material-journey-scan-bar--crate-target': Boolean(packTargetLabel) }"
  >
    <div class="material-journey-scan-bar__header">
      <div v-if="packTargetLabel" class="material-journey-scan-bar__target">
        <p class="material-journey-scan-bar__target-hint">
          {{ t('activities.materialJourney.activeCrate.packTargetHint', { label: packTargetLabel }) }}
        </p>
        <button
          type="button"
          class="material-journey-scan-bar__deselect"
          @click="emit('deselect')"
        >
          {{ t('activities.materialJourney.activeCrate.deselect') }}
        </button>
      </div>

      <label
        v-else
        class="material-journey-scan-bar__label"
        :for="inputId ?? 'material-journey-scan-input'"
      >
        {{ t(labelKey ?? 'activities.materialJourney.scan.label') }}
      </label>
    </div>

    <div class="material-journey-scan-bar__row-wrap">
      <div class="material-journey-scan-bar__row">
        <button
          type="button"
          class="material-journey-scan-bar__scan-btn"
          :aria-label="t('activities.materialJourney.scan.openCamera')"
          :disabled="loading"
          @click="openCamera"
        >
          <v-icon icon="mdi-barcode-scan" size="22" />
        </button>
        <input
          :id="inputId ?? 'material-journey-scan-input'"
          ref="inputRef"
          class="material-journey-scan-bar__input"
          type="search"
          autocomplete="off"
          autocapitalize="off"
          spellcheck="false"
          enterkeyhint="search"
          :placeholder="effectivePlaceholder"
          :value="modelValue"
          :disabled="loading"
          :readonly="inputReadonly"
          @input="onInput"
          @keydown="onKeydown"
          @click="onInputClick"
          @focus="onInputFocusTypeahead"
        />

        <v-menu
          v-if="coarsePointer"
          v-model="menuOpen"
          location="bottom end"
          :close-on-content-click="false"
          offset="6"
        >
          <template #activator="{ props: menuActivatorProps }">
            <button
              type="button"
              class="material-journey-scan-bar__menu-btn"
              :aria-label="t('activities.materialJourney.scan.openMenu')"
              :disabled="loading"
              v-bind="menuActivatorProps"
            >
              <v-icon icon="mdi-chevron-down" size="20" />
            </button>
          </template>

          <div class="material-journey-scan-menu section-card">
            <p class="material-journey-scan-menu__title">
              {{ t('activities.materialJourney.scan.chooseActionTitle') }}
            </p>
            <v-list density="compact" class="material-journey-scan-menu__list">
              <v-list-item
                :title="t('activities.materialJourney.scan.chooseCamera')"
                prepend-icon="mdi-camera"
                @click="chooseCameraFromMenu"
              />
              <v-list-item
                :title="t('activities.materialJourney.scan.chooseType')"
                prepend-icon="mdi-keyboard"
                @click="chooseTypeFromMenu"
              />
            </v-list>
            <label class="material-journey-scan-menu__remember">
              <input v-model="rememberForSession" type="checkbox" />
              <span>{{ t('activities.materialJourney.scan.rememberForSession') }}</span>
            </label>
            <p class="material-journey-scan-menu__hint text-muted">
              {{ t('activities.materialJourney.scan.menuBarcodeHint') }}
            </p>
          </div>
        </v-menu>

        <button
          v-if="modelValue.trim()"
          type="button"
          class="material-journey-scan-bar__clear"
          :aria-label="t('common.searchClear')"
          @click="emit('clear')"
        >
          <v-icon icon="mdi-close-circle" size="20" />
        </button>
      </div>

      <ul
        v-if="showTypeaheadDropdown"
        class="material-journey-scan-typeahead"
        role="listbox"
        :aria-label="t('activities.materialJourney.scan.typeaheadAria')"
      >
        <li
          v-for="(item, index) in filteredSuggestions"
          :key="item.id"
          role="presentation"
        >
          <button
            type="button"
            class="material-journey-scan-typeahead__item"
            :class="{ 'material-journey-scan-typeahead__item--active': index === activeSuggestionIndex }"
            role="option"
            :aria-selected="index === activeSuggestionIndex"
            @mousedown.prevent="selectSuggestion(item)"
          >
            <span class="material-journey-scan-typeahead__label">{{ item.label }}</span>
            <span v-if="item.subtitle" class="material-journey-scan-typeahead__meta text-muted">
              {{ item.subtitle }}
            </span>
          </button>
        </li>
      </ul>
    </div>

    <p v-if="showTouchHints" class="material-journey-scan-bar__touch-hint text-muted">
      {{ t('activities.materialJourney.scan.barcodeShortcutHint') }}
      <template v-if="sessionPref">
        {{ ' ' }}
        <button type="button" class="material-journey-scan-bar__reset-pref" @click="resetSessionPref">
          {{ t('activities.materialJourney.scan.resetSessionPref') }}
        </button>
      </template>
    </p>
    <p v-if="showTouchHints && sessionPref" class="material-journey-scan-bar__session-note text-muted">
      {{
        sessionPref === 'camera'
          ? t('activities.materialJourney.scan.sessionPrefCamera')
          : t('activities.materialJourney.scan.sessionPrefType')
      }}
    </p>

    <ul v-if="sessionLog.length" class="material-journey-scan-bar__recent">
      <li
        v-for="entry in sessionLog"
        :key="entry.id"
        class="material-journey-scan-bar__recent-item"
        :class="`material-journey-scan-bar__recent-item--${entry.tone}`"
      >
        <time>{{ formatTime(entry.at) }}</time>
        <span>{{ entry.label }}</span>
      </li>
    </ul>

    <v-dialog
      v-model="cameraOpen"
      :fullscreen="cameraFullscreen"
      :max-width="cameraMaxWidth"
      scrollable
      class="material-journey-sheet-dialog material-journey-scan-camera-dialog"
      transition="dialog-bottom-transition"
    >
      <div class="material-journey-scan-camera">
        <header class="material-journey-scan-camera__header">
          <EButton variant="secondary" size="small" @click="closeCamera">
            {{ t('common.close') }}
          </EButton>
          <h2 class="material-journey-scan-camera__title">
            {{ t('activities.materialJourney.scan.cameraTitle') }}
          </h2>
        </header>
        <div class="material-journey-scan-camera__body">
          <BarcodeScannerPanel
            :active="cameraOpen"
            mode="all"
            :hint="t('activities.materialJourney.scan.cameraHint')"
            @detected="onScanDetected"
            @error="onScanError"
          />
        </div>
      </div>
    </v-dialog>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';

.material-journey-scan-camera-dialog:not(.v-dialog--fullscreen) .material-journey-scan-camera {
  max-height: min(85vh, 640px);
}

.material-journey-scan-camera-dialog:not(.v-dialog--fullscreen) .material-journey-scan-camera__body {
  overflow-y: auto;
}

.material-journey-scan-bar__row-wrap {
  position: relative;
}

.material-journey-scan-typeahead {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  z-index: 12;
  margin: 0;
  padding: 4px 0;
  list-style: none;
  max-height: min(40vh, 280px);
  overflow-y: auto;
  border: 1px solid var(--color-border, #d1d5db);
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}

.material-journey-scan-typeahead__item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  width: 100%;
  padding: 10px 12px;
  border: 0;
  background: transparent;
  text-align: left;
  cursor: pointer;
}

.material-journey-scan-typeahead__item:hover,
.material-journey-scan-typeahead__item--active {
  background: var(--color-primary-muted-bg, #ecfdf5);
}

.material-journey-scan-typeahead__label {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--color-text, #111827);
}

.material-journey-scan-typeahead__meta {
  font-size: 0.75rem;
  line-height: 1.35;
}
</style>

<style src="@/styles/views/activities/material-journey-sheet.css"></style>
