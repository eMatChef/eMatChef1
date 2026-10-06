<template>
  <section class="mt-5 border-t border-slate-200 pt-3" data-onboarding="profile-sessions">
    <h4 class="mb-1 text-[0.82rem] font-bold text-slate-700">{{ t('layout.profileModal.sessions.title') }}</h4>
    <p class="mb-3 text-[0.82rem] text-slate-500">{{ t('layout.profileModal.sessions.hint') }}</p>

    <p v-if="loadError" class="text-[0.85rem] text-red-700">{{ loadError }}</p>

    <template v-else>
      <ul class="mb-3 flex flex-col gap-2" data-testid="sessions">
        <li
          v-for="s in sessions"
          :key="s.id"
          class="flex flex-wrap items-center gap-2 rounded-lg border px-3 py-2 text-[0.85rem]"
          :class="s.current ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200'"
        >
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-medium text-slate-800">{{ s.label }}</span>
              <span
                v-if="s.current"
                class="rounded-full bg-emerald-100 px-2 py-0.5 text-[0.72rem] font-semibold text-emerald-800"
                data-testid="current-badge"
              >
                {{ t('layout.profileModal.sessions.current') }}
              </span>
              <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.72rem] font-semibold text-slate-700">
                {{ authMethodLabel(s.auth_method) }}
              </span>
              <span
                class="rounded-full px-2 py-0.5 text-[0.72rem] font-semibold"
                :class="s.mfa_verified ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
              >
                {{ mfaLabel(s) }}
              </span>
              <span
                v-if="s.trusted"
                class="rounded-full bg-sky-100 px-2 py-0.5 text-[0.72rem] font-semibold text-sky-800"
              >
                {{ t('layout.profileModal.sessions.trusted') }}
              </span>
            </div>
            <p class="mt-1 text-[0.78rem] text-slate-500">
              {{ t('layout.profileModal.sessions.created', { date: formatDate(s.created_at) }) }} ·
              {{ t('layout.profileModal.sessions.lastSeen', { date: formatDate(s.last_seen_at) }) }}
            </p>
          </div>
          <EButton
            v-if="!s.current"
            variant="text"
            size="small"
            :loading="busyId === s.id"
            :disabled="busyId !== null || bulkBusy"
            data-testid="revoke-session"
            @click="revoke(s)"
          >
            {{ t('layout.profileModal.sessions.signOut') }}
          </EButton>
          <span v-else class="text-[0.75rem] text-slate-500">{{ t('layout.profileModal.sessions.currentHint') }}</span>
        </li>
      </ul>

      <EButton
        variant="secondary"
        size="small"
        :loading="bulkBusy"
        :disabled="!hasOthers || busyId !== null"
        data-testid="revoke-others"
        @click="revokeOthers"
      >
        {{ t('layout.profileModal.sessions.signOutOthers') }}
      </EButton>

      <h5 class="mb-1 mt-4 text-[0.8rem] font-bold text-slate-700">{{ t('layout.profileModal.sessions.trustedTitle') }}</h5>
      <p class="mb-2 text-[0.78rem] text-slate-500">{{ t('layout.profileModal.sessions.trustedHint') }}</p>
      <p v-if="devices.length === 0" class="text-[0.82rem] text-slate-500" data-testid="no-devices">
        {{ t('layout.profileModal.sessions.noTrusted') }}
      </p>
      <ul class="flex flex-col gap-2" data-testid="devices">
        <li
          v-for="d in devices"
          :key="d.id"
          class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-[0.85rem]"
        >
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-medium text-slate-800">{{ d.label }}</span>
              <span
                v-if="d.current"
                class="rounded-full bg-emerald-100 px-2 py-0.5 text-[0.72rem] font-semibold text-emerald-800"
              >
                {{ t('layout.profileModal.sessions.thisDevice') }}
              </span>
            </div>
            <p class="mt-1 text-[0.78rem] text-slate-500">
              {{ t('layout.profileModal.sessions.trustedSince', { date: formatDate(d.trusted_at) }) }} ·
              {{ d.last_used_at ? t('layout.profileModal.sessions.lastUsed', { date: formatDate(d.last_used_at) }) : t('layout.profileModal.sessions.neverUsed') }} ·
              {{ t('layout.profileModal.sessions.validUntil', { date: formatDate(d.valid_until) }) }}
            </p>
          </div>
          <EButton
            variant="text"
            size="small"
            :loading="busyDeviceId === d.id"
            :disabled="busyDeviceId !== null"
            data-testid="revoke-device"
            @click="revokeDevice(d)"
          >
            {{ t('layout.profileModal.sessions.revokeTrust') }}
          </EButton>
        </li>
      </ul>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import {
  getSessions,
  getTrustedDevices,
  revokeOtherSessions,
  revokeSession,
  revokeTrustedDevice,
  type SecuritySession,
  type TrustedDevice,
} from '@/api/profileSecurity'

const props = defineProps<{ open?: boolean }>()

const { t, locale } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const sessions = ref<SecuritySession[]>([])
const devices = ref<TrustedDevice[]>([])
const loadError = ref('')
const busyId = ref<string | null>(null)
const busyDeviceId = ref<string | null>(null)
const bulkBusy = ref(false)

const hasOthers = computed(() => sessions.value.some((s) => !s.current))
const profileId = () => authStore.profileId || authStore.profile?.id || ''

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) void load()
  },
  { immediate: true },
)

function errorMessage(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { error?: string; message?: string } } }
  return err.response?.data?.message || err.response?.data?.error || fallback
}

function formatDate(value: string): string {
  return new Date(value).toLocaleString(locale.value, { dateStyle: 'short', timeStyle: 'short' })
}

function authMethodLabel(method: string): string {
  const key = `layout.profileModal.sessions.method.${method}`
  return t(key) === key ? method : t(key)
}

function mfaLabel(s: SecuritySession): string {
  if (!s.mfa_verified) return t('layout.profileModal.sessions.mfaNone')
  if (s.mfa_source === 'trusted_device') return t('layout.profileModal.sessions.mfaTrusted')
  return t('layout.profileModal.sessions.mfaVerified')
}

async function load() {
  const id = profileId()
  if (!id) return
  loadError.value = ''
  try {
    ;[sessions.value, devices.value] = await Promise.all([getSessions(id), getTrustedDevices(id)])
  } catch (e: unknown) {
    loadError.value = errorMessage(e, t('layout.profileModal.sessions.loadError'))
  }
}

async function revoke(s: SecuritySession) {
  const id = profileId()
  if (!id || s.current) return
  busyId.value = s.id
  try {
    sessions.value = await revokeSession(id, s.id)
    toast.success(t('layout.profileModal.sessions.signedOut', { label: s.label }))
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('layout.profileModal.sessions.saveError')))
    await load()
  } finally {
    busyId.value = null
  }
}

async function revokeOthers() {
  const id = profileId()
  if (!id) return
  const ok = await confirm.confirm({
    title: t('layout.profileModal.sessions.signOutOthersTitle'),
    message: t('layout.profileModal.sessions.signOutOthersMessage'),
    confirmText: t('layout.profileModal.sessions.signOutOthers'),
    variant: 'danger',
  })
  if (!ok) return
  bulkBusy.value = true
  try {
    // Bei aktivem TOTP antwortet das Backend step_up_required; der apiClient fragt nach und wiederholt genau einmal.
    const result = await revokeOtherSessions(id)
    sessions.value = result.sessions
    toast.success(t('layout.profileModal.sessions.signedOutOthers', { n: result.revoked }))
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('layout.profileModal.sessions.saveError')))
    await load()
  } finally {
    bulkBusy.value = false
  }
}

async function revokeDevice(d: TrustedDevice) {
  const id = profileId()
  if (!id) return
  const ok = await confirm.confirm({
    title: t('layout.profileModal.sessions.revokeTrustTitle'),
    message: t('layout.profileModal.sessions.revokeTrustMessage', { label: d.label }),
    confirmText: t('layout.profileModal.sessions.revokeTrust'),
  })
  if (!ok) return
  busyDeviceId.value = d.id
  try {
    devices.value = await revokeTrustedDevice(id, d.id)
    toast.success(t('layout.profileModal.sessions.trustRevoked'))
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('layout.profileModal.sessions.saveError')))
    await load()
  } finally {
    busyDeviceId.value = null
  }
}
</script>
