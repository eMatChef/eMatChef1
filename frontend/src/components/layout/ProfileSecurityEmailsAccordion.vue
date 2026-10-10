<template>
  <details class="profile-accordion" data-onboarding="profile-security" :open="expanded || undefined">
    <summary class="profile-accordion__summary">{{ t('layout.profileModal.securitySection') }}</summary>
    <div class="profile-accordion__body">
      <ProfileSecurityExternalIdentitiesSection :open="open" class="!mt-3 !border-t-0 !pt-0" />
      <ProfileSecurityTotpSection :open="open" />

      <h4 id="profile-email-management" class="mb-1 mt-5 border-t border-slate-200 pt-3 text-[0.82rem] font-bold text-slate-700">{{ t('layout.profileModal.emails.title') }}</h4>
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
          <p class="w-full text-[0.75rem] text-slate-500" data-testid="used-for-primary">
            {{ usageText(data.primary.email) }}
          </p>
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
          <span v-if="entry.login_enabled" class="rounded-full bg-sky-50 px-2 py-0.5 text-[0.72rem] font-semibold text-sky-800">
            {{ t('layout.profileModal.emails.loginEnabled') }}
          </span>
          <p class="w-full text-[0.75rem] text-slate-500" :data-testid="`used-for-${entry.id}`">
            {{ usageText(entry.email) }}
          </p>
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

      <form
        class="grid grid-cols-1 gap-x-2 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start"
        data-testid="add-email-form"
        @submit.prevent="add"
      >
        <ETextField
          v-model="newEmail"
          class="w-full min-w-0"
          type="email"
          autocomplete="email"
          :label="t('layout.profileModal.emails.addLabel')"
          hide-details="auto"
        />
        <EButton variant="primary" size="small" type="submit" :loading="adding" :disabled="!newEmail.trim()">
          {{ t('layout.profileModal.emails.add') }}
        </EButton>
      </form>

      <div v-if="assignments.length > 0" class="mt-5 border-t border-slate-200 pt-3" data-testid="department-emails">
        <h5 class="mb-1 text-[0.8rem] font-bold text-slate-700">{{ t('layout.profileModal.emails.departmentsTitle') }}</h5>
        <p class="mb-2 text-[0.78rem] text-slate-500">{{ t('layout.profileModal.emails.departmentsHint') }}</p>
        <ul class="flex flex-col gap-2">
          <li
            v-for="a in assignments"
            :key="a.department_id"
            class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-slate-200 px-3 py-2 text-[0.85rem]"
            :data-testid="`assignment-${a.department_id}`"
          >
            <div class="min-w-0 flex-1 basis-40">
              <span class="font-medium text-slate-800">{{ a.name }}</span>
              <span v-if="a.is_grossanlass" class="ml-1 text-[0.72rem] text-slate-500">({{ t('layout.profileModal.emails.grossanlass') }})</span>
              <p v-if="a.parent_name" class="text-[0.75rem] text-slate-500">{{ a.parent_name }}</p>
            </div>
            <div class="w-full min-w-0 sm:w-80">
              <ESelect
                :model-value="a.selected_email ?? PRIMARY"
                :items="departmentItems"
                :label="t('layout.profileModal.emails.departmentSelectLabel')"
                :disabled="savingDepartmentId !== null"
                hide-details="auto"
                @update:model-value="(value: unknown) => changeDepartmentEmail(a, value)"
              />
            </div>
          </li>
        </ul>
      </div>

      <ProfileSecuritySessionsSection :open="open" />
      <ProfileSecurityActivitySection :open="open" />
    </div>
  </details>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ESelect, ETextField } from '@/components/form/base'
import ProfileSecurityExternalIdentitiesSection from '@/components/layout/ProfileSecurityExternalIdentitiesSection.vue'
import ProfileSecurityTotpSection from '@/components/layout/ProfileSecurityTotpSection.vue'
import ProfileSecuritySessionsSection from '@/components/layout/ProfileSecuritySessionsSection.vue'
import ProfileSecurityActivitySection from '@/components/layout/ProfileSecurityActivitySection.vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import {
  addProfileEmail,
  getDepartmentEmailAssignments,
  getProfileEmails,
  makeProfileEmailPrimary,
  removeProfileEmail,
  resendProfileEmailVerification,
  type AdditionalEmail,
  type DepartmentEmailAssignment,
  type ProfileEmails,
} from '@/api/profileEmails'
import { setDepartmentNotificationEmail } from '@/api/departmentNotificationEmail'

const props = defineProps<{ open?: boolean; expanded?: boolean }>()
const emit = defineEmits<{ (e: 'primary-changed'): void }>()

/** Andere Anzeigen (z. B. Benachrichtigungsadresse je Department) laden bei Adressänderungen neu. */
const EMAILS_CHANGED_EVENT = 'emc-profile-emails-changed'
function notifyEmailsChanged() {
  window.dispatchEvent(new CustomEvent(EMAILS_CHANGED_EVENT, { detail: { source: 'security' } }))
  void loadAssignments()
}

const { t, locale } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const data = ref<ProfileEmails | null>(null)
const loadError = ref('')
const newEmail = ref('')
const adding = ref(false)
const busyId = ref<string | null>(null)

const PRIMARY = '__primary__'
const assignments = ref<DepartmentEmailAssignment[]>([])
const departmentOptions = ref<string[]>([])
const savingDepartmentId = ref<string | null>(null)

const departmentItems = computed(() => {
  const primary = data.value?.primary.email ?? ''
  return [
    { title: t('layout.profileModal.emails.departmentDefault', { email: primary }), value: PRIMARY },
    ...departmentOptions.value.filter((email) => email !== primary.toLowerCase()).map((email) => ({ title: email, value: email })),
  ]
})

/** «Verwendet für: Abteilung A, Abteilung C» je Adresse (aus der tatsächlich wirksamen Benachrichtigungsadresse). */
function usageText(email: string | null): string {
  const names = assignments.value
    .filter((a) => email !== null && a.effective_email === email.toLowerCase())
    .map((a) => a.name)
  return names.length > 0
    ? t('layout.profileModal.emails.usedFor', { departments: names.join(', ') })
    : t('layout.profileModal.emails.usedForNone')
}

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
  await loadAssignments()
}

async function loadAssignments() {
  const id = profileId()
  if (!id) return
  try {
    const result = await getDepartmentEmailAssignments(id)
    assignments.value = result.assignments
    departmentOptions.value = result.options
  } catch {
    assignments.value = []
  }
}

async function changeDepartmentEmail(assignment: DepartmentEmailAssignment, value: unknown) {
  if (typeof value !== 'string' || savingDepartmentId.value !== null) return
  const next = value === PRIMARY ? null : value
  if (next === assignment.selected_email) return
  savingDepartmentId.value = assignment.department_id
  try {
    // Bestehende Department-API: ändert nur die persönliche Benachrichtigungseinstellung dieser Mitgliedschaft.
    await setDepartmentNotificationEmail(assignment.department_id, next)
    toast.success(t('layout.profileModal.emails.departmentSaved', { department: assignment.name }))
    window.dispatchEvent(new CustomEvent(EMAILS_CHANGED_EVENT, { detail: { source: 'security' } }))
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('layout.profileModal.emails.saveError')))
  } finally {
    savingDepartmentId.value = null
    await loadAssignments()
  }
}

// Änderungen in den Department-Einstellungen sofort übernehmen (kein zweiter Speicherweg, nur Neuladen).
function onEmailsChangedElsewhere(event: Event) {
  if ((event as CustomEvent<{ source?: string }>).detail?.source === 'security') return
  void loadAssignments()
}
onMounted(() => window.addEventListener(EMAILS_CHANGED_EVENT, onEmailsChangedElsewhere))
onBeforeUnmount(() => window.removeEventListener(EMAILS_CHANGED_EVENT, onEmailsChangedElsewhere))

async function add() {
  const id = profileId()
  const email = newEmail.value.trim()
  if (!id || !email || adding.value) return
  adding.value = true
  try {
    data.value = await addProfileEmail(id, email)
    newEmail.value = ''
    toast.success(t('layout.profileModal.emails.added', { email }))
    notifyEmailsChanged()
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
    emit('primary-changed')
    notifyEmailsChanged()
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
    notifyEmailsChanged()
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
