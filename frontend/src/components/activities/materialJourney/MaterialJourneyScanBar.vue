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
import { useToast } from '@/composables/useToast'
import { canRequestCamera } from '@/utils/cameraAccess'
import { localizedBarcodeScannerError } from '@/utils/barcodeScannerErrors'

const props = defineProps<{
  modelValue: string
  loading: boolean
  sessionLog: MaterialScanSessionEntry[]
  labelKey?: string
  inputId?: string
  packTargetLabel?: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  submit: []
  clear: []
  deselect: []
}>()

const { t } = useI18n()
const toast = useToast()
const inputRef = ref<HTMLInputElement | null>(null)
const menuOpen = ref(false)
const cameraOpen = ref(false)
const keyboardEnabled = ref(false)
const coarsePointer = ref(false)
const scanCooldown = ref(false)
const rememberForSession = ref(false)
const sessionPref = ref<MaterialJourneyScanTouchPref | null>(null)

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

function onInput(event: Event): void {
  emit('update:modelValue', (event.target as HTMLInputElement).value)
}

function onKeydown(event: KeyboardEvent): void {
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
        :placeholder="t('activities.materialJourney.scan.placeholder')"
        :value="modelValue"
        :disabled="loading"
        :readonly="inputReadonly"
        @input="onInput"
        @keydown="onKeydown"
        @click="onInputClick"
        @focus="onInputFocus"
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
      fullscreen
      scrollable
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
</style>
