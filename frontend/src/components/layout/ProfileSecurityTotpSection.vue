<template>
  <section class="mt-5 border-t border-slate-200 pt-3" data-onboarding="profile-totp">
    <h4 class="mb-1 text-[0.82rem] font-bold text-slate-700">{{ t('layout.profileModal.totp.title') }}</h4>
    <p class="mb-3 text-[0.82rem] text-slate-500">{{ t('layout.profileModal.totp.hint') }}</p>

    <p v-if="loadError" class="text-[0.85rem] text-red-700">{{ loadError }}</p>

    <template v-else-if="status">
      <div class="mb-3 flex flex-wrap items-center gap-2 text-[0.85rem]">
        <span
          class="rounded-full px-2 py-0.5 text-[0.72rem] font-semibold"
          :class="status.enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
        >
          {{ status.enabled ? t('layout.profileModal.totp.active') : t('layout.profileModal.totp.inactive') }}
        </span>
        <span
          v-if="status.required"
          class="rounded-full bg-amber-100 px-2 py-0.5 text-[0.72rem] font-semibold text-amber-800"
        >
          {{ t('layout.profileModal.totp.requiredBadge') }}
        </span>
      </div>

      <p v-if="status.admin_blocked" class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-[0.82rem] text-amber-900">
        {{ t('layout.profileModal.totp.adminBlocked') }}
      </p>
      <p v-else-if="status.required" class="mb-3 text-[0.82rem] text-slate-600">
        {{ t('layout.profileModal.totp.requiredHint') }}
      </p>
      <p v-if="status.locked" class="mb-3 text-[0.82rem] text-red-700">
        {{ t('layout.profileModal.totp.locked') }}
      </p>

      <!-- Einrichtung: QR-Code, Setup-Key, erster Code -->
      <div v-if="setup" class="mb-3 rounded-lg border border-slate-200 px-3 py-3">
        <p class="mb-2 text-[0.85rem] text-slate-700">{{ t('layout.profileModal.totp.scanHint') }}</p>
        <img
          v-if="qrDataUrl"
          :src="qrDataUrl"
          :alt="t('layout.profileModal.totp.qrAlt')"
          class="mb-2 h-44 w-44 rounded border border-slate-200"
        />
        <p class="mb-1 text-[0.78rem] text-slate-500">{{ t('layout.profileModal.totp.manualKey') }}</p>
        <code class="mb-3 block break-all rounded bg-slate-100 px-2 py-1 text-[0.85rem] tracking-wider">{{ formattedSecret }}</code>
        <form class="flex flex-col gap-2 sm:flex-row sm:items-end" @submit.prevent="confirmSetup">
          <ETextField
            v-model="confirmCode"
            class="flex-1"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="7"
            :label="t('layout.profileModal.totp.codeLabel')"
            hide-details="auto"
          />
          <EButton variant="primary" size="small" type="submit" :loading="busy" :disabled="confirmCode.replace(/\s/g, '').length !== 6">
            {{ t('layout.profileModal.totp.confirm') }}
          </EButton>
          <EButton variant="text" size="small" type="button" :disabled="busy" @click="cancelSetup">
            {{ t('common.cancel') }}
          </EButton>
        </form>
      </div>

      <!-- Recovery Codes: einmalig anzeigen -->
      <div v-if="shownCodes.length" class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-3">
        <p class="mb-1 text-[0.85rem] font-semibold text-amber-900">{{ t('layout.profileModal.totp.recoveryTitle') }}</p>
        <p class="mb-2 text-[0.82rem] text-amber-900">{{ t('layout.profileModal.totp.recoveryWarning') }}</p>
        <ul class="mb-2 flex flex-col gap-1">
          <li v-for="c in shownCodes" :key="c">
            <code class="rounded bg-white px-2 py-1 text-[0.95rem] font-semibold tracking-widest">{{ c }}</code>
          </li>
        </ul>
        <div class="flex flex-wrap gap-2">
          <EButton variant="secondary" size="small" @click="copyCodes">{{ t('layout.profileModal.totp.copyCodes') }}</EButton>
          <EButton variant="primary" size="small" @click="shownCodes = []">{{ t('layout.profileModal.totp.codesSaved') }}</EButton>
        </div>
      </div>

      <!-- Aktiv: Recovery-Status und Aktionen -->
      <template v-if="status.enabled && !setup">
        <p v-if="status.recovery_codes_empty" class="mb-2 text-[0.82rem] font-semibold text-red-700">
          {{ t('layout.profileModal.totp.recoveryEmpty') }}
        </p>
        <p v-else-if="status.recovery_codes_low" class="mb-2 text-[0.82rem] font-semibold text-amber-800">
          {{ t('layout.profileModal.totp.recoveryLow') }}
        </p>
        <p class="mb-3 text-[0.82rem] text-slate-600">
          {{ t('layout.profileModal.totp.recoveryRemaining', { n: status.recovery_codes_remaining ?? 0, total: status.recovery_codes_total }) }}
        </p>

        <ETextField
          v-model="actionCode"
          class="mb-2"
          autocomplete="one-time-code"
          :label="t('layout.profileModal.totp.actionCodeLabel')"
          :hint="t('layout.profileModal.totp.actionCodeHint')"
          persistent-hint
        />
        <div class="mt-3 flex flex-wrap gap-2">
          <EButton variant="secondary" size="small" :loading="busy" :disabled="!actionCode.trim()" @click="regenerate">
            {{ t('layout.profileModal.totp.regenerate') }}
          </EButton>
          <EButton variant="secondary" size="small" :disabled="busy || !actionCode.trim()" @click="restart">
            {{ t('layout.profileModal.totp.reset') }}
          </EButton>
          <EButton
            v-if="status.can_disable"
            variant="text"
            size="small"
            :disabled="busy || !actionCode.trim()"
            @click="disable"
          >
            {{ t('layout.profileModal.totp.disable') }}
          </EButton>
        </div>
      </template>

      <EButton v-else-if="!setup" variant="primary" size="small" :loading="busy" @click="start()">
        {{ t('layout.profileModal.totp.enable') }}
      </EButton>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import QRCode from 'qrcode'
import { EButton, ETextField } from '@/components/form/base'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import {
  confirmTotpEnrollment,
  disableTotp,
  getTotpStatus,
  regenerateTotpRecoveryCodes,
  startTotpEnrollment,
  type TotpEnrollment,
  type TotpStatus,
} from '@/api/profileTotp'

const props = defineProps<{ open?: boolean }>()

const { t } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const status = ref<TotpStatus | null>(null)
const loadError = ref('')
const busy = ref(false)
const setup = ref<TotpEnrollment | null>(null)
const qrDataUrl = ref('')
const confirmCode = ref('')
const actionCode = ref('')
const shownCodes = ref<string[]>([])

const profileId = () => authStore.profileId || authStore.profile?.id || ''

const formattedSecret = computed(() => (setup.value?.secret ?? '').replace(/(.{4})/g, '$1 ').trim())

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      void load()
    } else {
      // Secret und Recovery Codes nicht im Speicher stehen lassen, wenn der Dialog schließt
      setup.value = null
      qrDataUrl.value = ''
      shownCodes.value = []
      confirmCode.value = ''
      actionCode.value = ''
    }
  },
  { immediate: true },
)

function errorMessage(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { error?: string } } }
  return err.response?.data?.error || fallback
}

async function load() {
  const id = profileId()
  if (!id) return
  loadError.value = ''
  try {
    status.value = await getTotpStatus(id)
  } catch (e: unknown) {
    loadError.value = errorMessage(e, t('layout.profileModal.totp.loadError'))
  }
}

async function start(code?: string) {
  const id = profileId()
  if (!id || busy.value) return
  busy.value = true
  try {
    const result = await startTotpEnrollment(id, code)
    setup.value = result
    status.value = result.status
    qrDataUrl.value = await QRCode.toDataURL(result.otpauth_uri, { width: 176, margin: 1 })
    confirmCode.value = ''
    actionCode.value = ''
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('layout.profileModal.totp.saveError')))
  } finally {
    busy.value = false
  }
}

async function restart() {
  const ok = await confirm.confirm({
    title: t('layout.profileModal.totp.resetTitle'),
    message: t('layout.profileModal.totp.resetMessage'),
    confirmText: t('layout.profileModal.totp.reset'),
  })
  if (ok) await start(actionCode.value.trim())
}

function cancelSetup() {
  setup.value = null
  qrDataUrl.value = ''
  confirmCode.value = ''
  void load()
}

async function confirmSetup() {
  const id = profileId()
  if (!id || busy.value) return
  busy.value = true
  try {
    const result = await confirmTotpEnrollment(id, confirmCode.value)
    status.value = result.status
    shownCodes.value = result.recovery_codes
    setup.value = null
    qrDataUrl.value = ''
    confirmCode.value = ''
    toast.success(t('layout.profileModal.totp.enabled'))
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('layout.profileModal.totp.saveError')))
  } finally {
    busy.value = false
  }
}

async function regenerate() {
  const id = profileId()
  if (!id || busy.value) return
  const ok = await confirm.confirm({
    title: t('layout.profileModal.totp.regenerateTitle'),
    message: t('layout.profileModal.totp.regenerateMessage'),
    confirmText: t('layout.profileModal.totp.regenerate'),
  })
  if (!ok) return
  busy.value = true
  try {
    const result = await regenerateTotpRecoveryCodes(id, actionCode.value.trim())
    status.value = result.status
    shownCodes.value = result.recovery_codes
    actionCode.value = ''
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('layout.profileModal.totp.saveError')))
    await load()
  } finally {
    busy.value = false
  }
}

async function disable() {
  const id = profileId()
  if (!id || busy.value) return
  const ok = await confirm.confirm({
    title: t('layout.profileModal.totp.disableTitle'),
    message: t('layout.profileModal.totp.disableMessage'),
    confirmText: t('layout.profileModal.totp.disable'),
    variant: 'danger',
  })
  if (!ok) return
  busy.value = true
  try {
    status.value = await disableTotp(id, actionCode.value.trim())
    actionCode.value = ''
    shownCodes.value = []
    toast.success(t('layout.profileModal.totp.disabled'))
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('layout.profileModal.totp.saveError')))
    await load()
  } finally {
    busy.value = false
  }
}

async function copyCodes() {
  try {
    await navigator.clipboard.writeText(shownCodes.value.join('\n'))
    toast.success(t('layout.profileModal.totp.copied'))
  } catch {
    toast.error(t('layout.profileModal.totp.copyFailed'))
  }
}
</script>
