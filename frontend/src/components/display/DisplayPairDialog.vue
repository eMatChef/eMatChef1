<template>
  <EDialog v-model="open" :max-width="520" :title="dialogTitle" :retain-focus="false">
    <template v-if="step === 'scan'">
      <ol class="pair-dialog-steps">
        <li>{{ t('display.pairDialog.steps.browser') }}</li>
        <li>
          {{ t('display.pairDialog.steps.address') }}
          <code class="pair-dialog-url" data-testid="kiosk-url">{{ kioskUrl }}</code>
        </li>
        <li>{{ t('display.pairDialog.steps.qr') }}</li>
        <li>{{ t('display.pairDialog.steps.scan') }}</li>
        <li>{{ t('display.pairDialog.steps.code') }}</li>
        <li>{{ t('display.pairDialog.steps.confirm') }}</li>
      </ol>
      <p class="muted pair-dialog-manual">{{ t('display.pairDialog.manualAlternative') }}</p>
      <BarcodeScannerPanel
        :active="open && step === 'scan'"
        mode="qr"
        :hint="t('display.pairDialog.scannerHint')"
        @detected="onDetected"
        @error="onScannerError"
      />
      <p v-if="scanError" class="pair-dialog-error" role="alert">{{ scanError }}</p>
      <ETextField
        v-model="pastedLink"
        class="mt-3"
        :label="t('display.pairDialog.pasteLabel')"
        :placeholder="t('display.pairDialog.pastePlaceholder')"
        autocomplete="off"
        spellcheck="false"
        hide-details
        @keydown.enter.prevent="onPaste"
      />
      <EButton class="mt-2" variant="secondary" size="small" :disabled="!pastedLink.trim()" @click="onPaste">
        {{ t('display.pairDialog.pasteSubmit') }}
      </EButton>
    </template>

    <template v-else-if="step === 'done'">
      <h3 class="pair-dialog-done-title">{{ t('display.pairDialog.done.title') }}</h3>
      <dl class="pair-dialog-summary">
        <dt>{{ t('display.pairDialog.done.device') }}</dt>
        <dd>{{ result?.deviceName }}</dd>
        <dt>{{ t('display.pairDialog.done.screen') }}</dt>
        <dd>{{ result?.screenName }}</dd>
        <dt>{{ t('display.pairDialog.done.status') }}</dt>
        <dd>{{ t('display.pairDialog.done.statusValue') }}</dd>
      </dl>
      <p class="muted">{{ t('display.pairDialog.done.autostart') }}</p>
    </template>

    <template v-else>
      <p>{{ t('display.pairDialog.verify') }}</p>
      <p class="pair-dialog-code">{{ userCode }}</p>
      <p v-if="screen" class="muted">{{ t('display.pairDialog.connectExisting', { name: screen.name }) }}</p>
      <ETextField
        v-else
        v-model="name"
        :label="t('settings.displayScreens.nameLabel')"
        :placeholder="t('settings.displayScreens.namePlaceholder')"
        maxlength="120"
        :disabled="submitting"
        hide-details
      />
      <ETextField
        v-model="deviceName"
        class="mt-3"
        :label="t('display.pairDialog.deviceName')"
        :placeholder="t('display.pairDialog.deviceNamePlaceholder')"
        maxlength="120"
        :disabled="submitting"
        hide-details
      />
      <p v-if="submitError" class="pair-dialog-error">{{ submitError }}</p>
    </template>

    <template #actions>
      <EButton v-if="step === 'done'" variant="primary" size="small" @click="open = false">
        {{ t('display.pairDialog.done.close') }}
      </EButton>
      <EButton v-else variant="secondary" size="small" :disabled="submitting" @click="open = false">
        {{ t('common.cancel') }}
      </EButton>
      <EButton v-if="step === 'confirm'" variant="secondary" size="small" :disabled="submitting" @click="backToScan">
        {{ t('display.pairDialog.rescan') }}
      </EButton>
      <EButton
        v-if="step === 'confirm'"
        variant="primary"
        size="small"
        :disabled="submitting || (!screen && !name.trim()) || !deviceName.trim()"
        :loading="submitting"
        @click="confirm"
      >
        {{ t('display.pairDialog.confirm') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BarcodeScannerPanel from '@/components/common/BarcodeScannerPanel.vue'
import { EButton, EDialog, ETextField } from '@/components/form/base'
import { approveDisplayPairing, getDisplayPairingRequest } from '@/api/displayPairing'
import { createAndPairDisplayScreen, type DisplayScreenSettings } from '@/api/displayScreens'
import { extractDisplayPairingToken } from '@/utils/displayPairingLink'
import { resolveKioskHomeUrl } from '@/utils/displayHost'

/**
 * Infoscreen mit einem Fernseher koppeln: QR (display.-Host) scannen → Prüfcode vergleichen → bestätigen.
 * Ohne `screen` wird beim Bestätigen ein neuer Infoscreen im Department angelegt, mit `screen` ein bestehender verbunden.
 */
const props = defineProps<{
  modelValue: boolean
  departmentId: string
  screen?: DisplayScreenSettings | null
  /** `display_url` vorhandener Infoscreens: bestimmt die konfigurierte Display-Domain. */
  knownDisplayUrls?: string[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  paired: []
}>()

const { t } = useI18n()

const open = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
})

const step = ref<'scan' | 'confirm' | 'done'>('scan')
const result = ref<{ deviceName: string; screenName: string } | null>(null)
const kioskUrl = computed(() => resolveKioskHomeUrl(props.knownDisplayUrls ?? []))
const token = ref('')
const userCode = ref('')
const name = ref('')
const deviceName = ref('')
const pastedLink = ref('')
const scanError = ref<string | null>(null)
const submitError = ref<string | null>(null)
const submitting = ref(false)
let resolving = false

const dialogTitle = computed(() =>
  step.value === 'done'
    ? t('display.pairDialog.done.dialogTitle')
    : props.screen ? t('display.pairDialog.titleExisting') : t('display.pairDialog.titleNew'),
)

function reset() {
  step.value = 'scan'
  result.value = null
  token.value = ''
  userCode.value = ''
  name.value = ''
  deviceName.value = props.screen?.name ?? ''
  pastedLink.value = ''
  scanError.value = null
  submitError.value = null
  submitting.value = false
  resolving = false
}

watch(open, (isOpen) => {
  if (isOpen) reset()
})

// Neuer Infoscreen: Gerätename übernimmt den Namen, solange er nicht selbst geändert wurde.
watch(name, (value, previous) => {
  if (!props.screen && (deviceName.value === '' || deviceName.value === previous)) deviceName.value = value
})

function statusOf(err: unknown): number | undefined {
  return (err as { response?: { status?: number } })?.response?.status
}

async function resolveToken(text: string) {
  if (resolving) return
  const found = extractDisplayPairingToken(text)
  if (!found) {
    scanError.value = t('display.pairDialog.notDisplayQr')
    return
  }
  resolving = true
  scanError.value = null
  try {
    // Der Scan allein gibt nichts frei: hier wird nur der Prüfcode geladen.
    const info = await getDisplayPairingRequest(found)
    token.value = found
    userCode.value = info.user_code
    step.value = 'confirm'
  } catch (err) {
    const status = statusOf(err)
    scanError.value = status === 410 || status === 404
      ? t('display.pairDialog.expired')
      : status === undefined ? t('display.pairDialog.noConnection') : t('display.pairDialog.failed')
  } finally {
    resolving = false
  }
}

function onScannerError() {
  // Kamera verweigert oder nicht verfügbar: Hinweis auf den Link-Weg unter dem Scanner.
  scanError.value = t('display.pairDialog.cameraUnavailable')
}

function onDetected(payload: { text: string }) {
  void resolveToken(payload.text)
}

function onPaste() {
  void resolveToken(pastedLink.value)
}

function backToScan() {
  step.value = 'scan'
  token.value = ''
  submitError.value = null
}

async function confirm() {
  submitting.value = true
  submitError.value = null
  try {
    if (props.screen) {
      await approveDisplayPairing(token.value, props.screen.id, deviceName.value.trim())
    } else {
      await createAndPairDisplayScreen(props.departmentId, token.value, name.value.trim(), deviceName.value.trim())
    }
    result.value = {
      deviceName: deviceName.value.trim(),
      screenName: props.screen ? props.screen.name : name.value.trim(),
    }
    step.value = 'done'
    emit('paired')
  } catch (err) {
    const status = statusOf(err)
    if (status === 410) {
      scanError.value = t('display.pairDialog.expired')
      backToScan()
    } else if (status === 403) {
      submitError.value = t('display.pair.forbidden')
    } else if (status === undefined) {
      submitError.value = t('display.pairDialog.noConnection')
    } else {
      submitError.value = t('display.pairDialog.failed')
    }
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.pair-dialog-code {
  margin: 8px 0;
  text-align: center;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 2rem;
  font-weight: 700;
  letter-spacing: 0.25em;
}

.pair-dialog-steps {
  margin: 0 0 12px;
  padding-left: 1.3rem;
  list-style: decimal;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.pair-dialog-url {
  display: block;
  margin-top: 4px;
  padding: 8px 12px;
  border-radius: 8px;
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 1.15rem;
  font-weight: 700;
  user-select: all;
  word-break: break-all;
}

.pair-dialog-manual {
  font-size: 0.875rem;
  margin: 0 0 12px;
}

.pair-dialog-done-title {
  margin: 0 0 8px;
  color: #166534;
}

.pair-dialog-summary {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 4px 16px;
  margin: 0 0 12px;
}

.pair-dialog-summary dt {
  color: #6b7280;
}

.pair-dialog-summary dd {
  margin: 0;
  font-weight: 600;
}

.pair-dialog-error {
  color: #b91c1c;
  margin: 8px 0 0;
}

.muted {
  color: #6b7280;
}
</style>
