<template>
  <EDialog v-model="open" :max-width="520" :title="dialogTitle" :retain-focus="false">
    <template v-if="step === 'scan'">
      <p class="muted">{{ t('display.pairDialog.scanHint') }}</p>
      <BarcodeScannerPanel
        :active="open && step === 'scan'"
        mode="qr"
        :hint="t('display.pairDialog.scannerHint')"
        @detected="onDetected"
      />
      <p v-if="scanError" class="pair-dialog-error">{{ scanError }}</p>
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
      <EButton variant="secondary" size="small" :disabled="submitting" @click="open = false">
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

/**
 * Infoscreen mit einem Fernseher koppeln: QR (display.-Host) scannen → Prüfcode vergleichen → bestätigen.
 * Ohne `screen` wird beim Bestätigen ein neuer Infoscreen im Department angelegt, mit `screen` ein bestehender verbunden.
 */
const props = defineProps<{
  modelValue: boolean
  departmentId: string
  screen?: DisplayScreenSettings | null
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

const step = ref<'scan' | 'confirm'>('scan')
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
  props.screen ? t('display.pairDialog.titleExisting') : t('display.pairDialog.titleNew'),
)

function reset() {
  step.value = 'scan'
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
    scanError.value = statusOf(err) === 410 ? t('display.pairDialog.expired') : t('display.pairDialog.failed')
  } finally {
    resolving = false
  }
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
    emit('paired')
    open.value = false
  } catch (err) {
    const status = statusOf(err)
    if (status === 410) {
      scanError.value = t('display.pairDialog.expired')
      backToScan()
    } else if (status === 403) {
      submitError.value = t('display.pair.forbidden')
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

.pair-dialog-error {
  color: #b91c1c;
  margin: 8px 0 0;
}

.muted {
  color: #6b7280;
}
</style>
