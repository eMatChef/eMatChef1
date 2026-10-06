<template>
  <details class="profile-accordion" data-onboarding="profile-security" :open="expanded || undefined">
    <summary class="profile-accordion__summary">{{ t('layout.profileModal.securitySection') }}</summary>
    <div class="profile-accordion__body">
      <h4 class="mb-1 mt-3 text-[0.82rem] font-bold text-slate-700">{{ t('layout.profileModal.emails.title') }}</h4>
      <p class="mb-3 text-[0.82rem] text-slate-500">{{ t('layout.profileModal.emails.hint') }}</p>

      <p v-if="loadError" class="text-[0.85rem] text-red-700">{{ loadError }}</p>
      <ul v-else class="mb-3 flex flex-col gap-2">
        <li
          v-if="data?.primary.email"
          class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-[0.85rem]"
        >
          <span class="min-w-0 flex-1 break-all font-medium text-slate-800">{{ data.primary.email }}</span>
          <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[0.72rem] font-semibold text-emerald-800">
            {{ t('layout.profileModal.emails.primary') }}
          </span>
          <span
            v-if="!data.primary.verified"
            class="rounded-full bg-amber-100 px-2 py-0.5 text-[0.72rem] font-semibold text-amber-800"
          >
            {{ t('layout.profileModal.emails.unverified') }}
          </span>
        </li>
        <li
          v-for="entry in data?.emails ?? []"
          :key="entry.id"
          class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-[0.85rem]"
        >
          <span class="min-w-0 flex-1 break-all text-slate-800">{{ entry.email }}</span>
          <span
            v-if="entry.verified"
            class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.72rem] font-semibold text-slate-700"
          >
            {{ t('layout.profileModal.emails.verified') }}
          </span>
          <span
            v-else
            class="rounded-full bg-amber-100 px-2 py-0.5 text-[0.72rem] font-semibold text-amber-800"
            :title="entry.verification_expires_at ? t('layout.profileModal.emails.pendingUntil', { date: formatDate(entry.verification_expires_at) }) : ''"
          >
            {{ t('layout.profileModal.emails.pending') }}
          </span>
          <span class="flex w-full flex-wrap justify-end gap-1 sm:w-auto">
            <EButton
              v-if="entry.verified"
              variant="text"
              size="small"
              :loading="busyId === entry.id"
              @click="makePrimary(entry)"
            >
              {{ t('layout.profileModal.emails.makePrimary') }}
            </EButton>
            <EButton
              v-else
              variant="text"
              size="small"
              :loading="busyId === entry.id"
              @click="resend(entry)"
            >
              {{ t('layout.profileModal.emails.resend') }}
            </EButton>
            <EButton
              variant="text"
              size="small"
              :disabled="busyId !== null"
              @click="remove(entry)"
            >
              {{ t('layout.profileModal.emails.remove') }}
            </EButton>
          </span>
        </li>
      </ul>

      <form class="flex flex-col gap-2 sm:flex-row sm:items-end" @submit.prevent="add">
        <ETextField
          v-model="newEmail"
          class="flex-1"
          type="email"
          autocomplete="email"
          :label="t('layout.profileModal.emails.addLabel')"
          hide-details="auto"
        />
        <EButton variant="primary" size="small" type="submit" :loading="adding" :disabled="!newEmail.trim()">
          {{ t('layout.profileModal.emails.add') }}
        </EButton>
      </form>

      <ProfileSecurityTotpSection :open="open" />
    </div>
  </details>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ETextField } from '@/components/form/base'
import ProfileSecurityTotpSection from '@/components/layout/ProfileSecurityTotpSection.vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import {
  addProfileEmail,
  getProfileEmails,
  makeProfileEmailPrimary,
  removeProfileEmail,
  resendProfileEmailVerification,
  type AdditionalEmail,
  type ProfileEmails,
} from '@/api/profileEmails'

const props = defineProps<{ open?: boolean; expanded?: boolean }>()

const { t, locale } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const data = ref<ProfileEmails | null>(null)
const loadError = ref('')
const newEmail = ref('')
const adding = ref(false)
const busyId = ref<string | null>(null)

const profileId = () => authStore.profileId || authStore.profile?.id || ''

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) void load()
  },
  { immediate: true },
)

function errorMessage(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { error?: string } } }
  return err.response?.data?.error || fallback
}

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString(locale.value)
}

async function load() {
  const id = profileId()
  if (!id) return
  loadError.value = ''
  try {
    data.value = await getProfileEmails(id)
  } catch (e: unknown) {
    loadError.value = errorMessage(e, t('layout.profileModal.emails.loadError'))
  }
}

async function add() {
  const id = profileId()
  const email = newEmail.value.trim()
  if (!id || !email || adding.value) return
  adding.value = true
  try {
    data.value = await addProfileEmail(id, email)
    newEmail.value = ''
    toast.success(t('layout.profileModal.emails.added', { email }))
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('layout.profileModal.emails.saveError')))
    await load()
  } finally {
    adding.value = false
  }
}

async function resend(entry: AdditionalEmail) {
  const id = profileId()
  if (!id) return
  busyId.value = entry.id
  try {
    data.value = await resendProfileEmailVerification(id, entry.id)
    toast.success(t('layout.profileModal.emails.resent', { email: entry.email }))
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('layout.profileModal.emails.saveError')))
  } finally {
    busyId.value = null
  }
}

async function makePrimary(entry: AdditionalEmail) {
  const id = profileId()
  if (!id) return
  const ok = await confirm.confirm({
    title: t('layout.profileModal.emails.makePrimaryTitle'),
    message: t('layout.profileModal.emails.makePrimaryMessage', { email: entry.email }),
    confirmText: t('layout.profileModal.emails.makePrimary'),
  })
  if (!ok) return
  busyId.value = entry.id
  try {
    data.value = await makeProfileEmailPrimary(id, entry.id)
    await authStore.loadUserSessionFromCookie(true)
    toast.success(t('layout.profileModal.emails.primaryChanged', { email: entry.email }))
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('layout.profileModal.emails.saveError')))
  } finally {
    busyId.value = null
  }
}

async function remove(entry: AdditionalEmail) {
  const id = profileId()
  if (!id) return
  const ok = await confirm.confirm({
    title: t('layout.profileModal.emails.removeTitle'),
    message: t('layout.profileModal.emails.removeMessage', { email: entry.email }),
    confirmText: t('layout.profileModal.emails.remove'),
    variant: 'danger',
  })
  if (!ok) return
  busyId.value = entry.id
  try {
    data.value = await removeProfileEmail(id, entry.id)
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('layout.profileModal.emails.saveError')))
  } finally {
    busyId.value = null
  }
}
</script>

<style scoped>
.profile-accordion {
  margin: 0 0 10px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fafafa;
  overflow: hidden;
}
.profile-accordion__summary {
  cursor: pointer;
  list-style: none;
  padding: 12px 14px;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
  user-select: none;
}
.profile-accordion__summary::-webkit-details-marker { display: none; }
.profile-accordion__summary::after {
  content: '▾';
  float: right;
  color: #94a3b8;
}
.profile-accordion[open] > .profile-accordion__summary::after {
  transform: rotate(-180deg);
}
.profile-accordion__body {
  padding: 0 14px 14px;
  background: #fff;
  border-top: 1px solid #e5e7eb;
}
</style>
