<template>
  <div v-if="modelValue" class="modal-overlay" @click.self="close">
    <div
      class="modal-dialog nc-compose-modal"
      role="dialog"
      aria-modal="true"
      :aria-label="t('notificationsCenter.composeTitle')"
      @click.stop
    >
      <header class="modal-header">
        <h3>{{ t('notificationsCenter.composeTitle') }}</h3>
        <button type="button" class="modal-close" :aria-label="t('common.cancel')" @click="close">
          ×
        </button>
      </header>
      <form class="modal-body nc-compose-modal__form" @submit.prevent="submit">
        <label class="nc-compose-field">
          <span>{{ t('notificationsCenter.composeRecipient') }}</span>
          <div v-if="selectedRecipient" class="nc-compose-recipient-chip">
            <UserAvatarBadge v-if="selectedRecipient.avatar" :user="selectedRecipient.avatar" size="sm" />
            <span
              v-else
              class="nc-compose-recipient-chip__icon"
              :class="{ 'nc-compose-recipient-chip__icon--external': selectedRecipient.kind === 'external' }"
              aria-hidden="true"
            >
              {{ selectedRecipient.kind === 'external' ? '◎' : '?' }}
            </span>
            <span class="nc-compose-recipient-chip__text">
              <span class="nc-compose-recipient-chip__name">{{ selectedRecipient.label }}</span>
              <span v-if="selectedRecipient.sublabel" class="nc-compose-recipient-chip__sub">{{ selectedRecipient.sublabel }}</span>
            </span>
            <span v-if="selectedRecipient.kind === 'external'" class="nc-compose-recipient-badge">
              {{ t('notificationsCenter.composeRecipientExternal') }}
            </span>
            <button type="button" class="nc-compose-recipient-chip__clear" @click="clearRecipient">×</button>
          </div>
          <div v-else class="autocomplete-wrapper">
            <input
              v-model="recipientQuery"
              type="text"
              class="form-input"
              :placeholder="t('notificationsCenter.composeRecipientSearch')"
              autocomplete="off"
              @focus="showDropdown = true"
              @blur="onRecipientBlur"
            />
            <div
              v-if="showDropdown && recipientQueryTrimmed.length >= 1 && filteredOptions.length > 0"
              class="autocomplete-dropdown nc-compose-autocomplete"
            >
              <button
                v-for="opt in filteredOptions"
                :key="opt.id"
                type="button"
                class="autocomplete-item nc-compose-ac-item"
                @mousedown.prevent="selectRecipient(opt)"
              >
                <UserAvatarBadge v-if="opt.avatar" :user="opt.avatar" size="sm" />
                <span
                  v-else
                  class="nc-compose-ac-item__icon nc-compose-ac-item__icon--external"
                  aria-hidden="true"
                >◎</span>
                <span class="nc-compose-ac-item__text">
                  <span class="ac-name">{{ opt.label }}</span>
                  <span v-if="opt.sublabel" class="ac-email">{{ opt.sublabel }}</span>
                </span>
                <span v-if="opt.kind === 'external'" class="nc-compose-recipient-badge">
                  {{ t('notificationsCenter.composeRecipientExternal') }}
                </span>
              </button>
            </div>
            <div
              v-else-if="showDropdown && recipientQueryTrimmed.length >= 1 && !isLoadingAddresses"
              class="autocomplete-dropdown nc-compose-autocomplete"
            >
              <div class="autocomplete-empty">{{ t('notificationsCenter.composeNoResults') }}</div>
            </div>
          </div>
          <p v-if="selectedRecipient?.kind === 'external'" class="nc-compose-external-hint">
            {{ t('notificationsCenter.composeExternalHint') }}
          </p>
        </label>
        <label class="nc-compose-field">
          <span>{{ t('notificationsCenter.composeSubject') }}</span>
          <input v-model="subject" type="text" required maxlength="200" class="form-input" />
        </label>
        <label class="nc-compose-field">
          <span>{{ t('notificationsCenter.composeMessage') }}</span>
          <textarea v-model="message" rows="5" required maxlength="5000" class="form-input" />
        </label>
        <footer class="modal-footer nc-compose-modal__footer">
          <button type="button" class="btn-outline btn-sm" @click="close">
            {{ t('common.cancel') }}
          </button>
          <button type="submit" class="btn-primary btn-sm" :disabled="!canSubmit || isSending">
            {{ isSending ? t('notificationsCenter.composeSending') : t('notificationsCenter.composeSend') }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAddresses, type Address } from '@/api/addresses'
import { sendUserDirectMessage } from '@/api/inboxMessages'
import type { DepartmentMember } from '@/api/departments'
import { UserAvatarBadge } from '@/components/user'
import { useToast } from '@/composables/useToast'
import { buildAvatarInitials, type UserAvatarFields } from '@/utils/userAvatar'

const props = defineProps<{
  modelValue: boolean
  departmentId: string
  members: DepartmentMember[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  sent: []
}>()

const { t } = useI18n()
const toast = useToast()

type ComposeRecipientKind = 'member' | 'external'

interface ComposeRecipientOption {
  id: string
  kind: ComposeRecipientKind
  label: string
  sublabel?: string
  avatar?: UserAvatarFields
  userId?: string
  email?: string
}

interface SelectedRecipient extends ComposeRecipientOption {}

const recipientQuery = ref('')
const showDropdown = ref(false)
const selectedRecipient = ref<SelectedRecipient | null>(null)
const subject = ref('')
const message = ref('')
const isSending = ref(false)
const externalAddresses = ref<Address[]>([])
const isLoadingAddresses = ref(false)

const recipientQueryTrimmed = computed(() => recipientQuery.value.trim())

const memberOptions = computed((): ComposeRecipientOption[] =>
  props.members
    .filter((m) => m.user_id)
    .map((m) => ({
      id: `member-${m.user_id}`,
      kind: 'member' as const,
      label: formatMemberName(m),
      sublabel: m.email,
      userId: m.user_id,
      avatar: {
        name: m.name,
        first_name: m.first_name,
        last_name: m.last_name,
        nickname: m.nickname,
        avatar_initials: m.avatar_initials,
        background_color: m.background_color,
        text_color: m.text_color,
      },
    })),
)

const externalOptions = computed((): ComposeRecipientOption[] =>
  externalAddresses.value
    .filter((a) => !a.is_deleted)
    .map((a) => ({
      id: `external-${a.id}`,
      kind: 'external' as const,
      label: addressLabel(a),
      sublabel: [a.email, a.city_line || a.city].filter(Boolean).join(' · ') || a.type_label,
      email: (a.email || '').trim() || undefined,
      avatar: addressAvatarFields(a),
    })),
)

const allOptions = computed(() => [...memberOptions.value, ...externalOptions.value])

const filteredOptions = computed(() => {
  const q = recipientQueryTrimmed.value.toLowerCase()
  if (!q) return allOptions.value.slice(0, 20)
  return allOptions.value.filter((opt) => recipientMatchesQuery(opt, q)).slice(0, 30)
})

const canSubmit = computed(
  () =>
    Boolean(selectedRecipient.value) &&
    subject.value.trim().length > 0 &&
    message.value.trim().length >= 2,
)

function joinNonEmpty(values: Array<string | null | undefined>, separator: string): string {
  return values.map((v) => (v || '').trim()).filter(Boolean).join(separator)
}

function formatMemberName(member: DepartmentMember): string {
  const legalName = joinNonEmpty([member.first_name, member.last_name], ' ')
  const nickname = (member.nickname || '').trim()
  if (legalName && nickname) return `${legalName} (${nickname})`
  if (legalName) return legalName
  if (nickname) return nickname
  return member.name
}

function addressLabel(a: Address): string {
  return (a.name || a.company || a.street_line || a.type_label || '').trim() || '—'
}

function addressAvatarFields(a: Address): UserAvatarFields {
  const label = addressLabel(a)
  const parts = label.split(/\s+/).filter(Boolean)
  const first = parts[0] ?? ''
  const last = parts.length > 1 ? parts[parts.length - 1] : ''
  return {
    name: label,
    first_name: first,
    last_name: last,
    nickname: null,
    avatar_initials: buildAvatarInitials(null, null, first, last),
    background_color: '#64748b',
    text_color: '#ffffff',
  }
}

function recipientMatchesQuery(opt: ComposeRecipientOption, q: string): boolean {
  const hay = [opt.label, opt.sublabel, opt.email].filter(Boolean).join(' ').toLowerCase()
  return hay.includes(q)
}

function isExternalCustomerAddress(a: Address): boolean {
  if (a.type === 'customer') return true
  const label = `${a.type_label} ${a.type}`.toLowerCase()
  return label.includes('kunde') || label.includes('customer')
}

async function loadExternalAddresses() {
  if (!props.departmentId) return
  isLoadingAddresses.value = true
  try {
    const { addresses } = await getAddresses(props.departmentId)
    externalAddresses.value = addresses
      .filter((a) => isExternalCustomerAddress(a))
      .sort((a, b) => addressLabel(a).localeCompare(addressLabel(b), 'de'))
  } catch {
    externalAddresses.value = []
  } finally {
    isLoadingAddresses.value = false
  }
}

function selectRecipient(opt: ComposeRecipientOption) {
  if (opt.kind === 'external' && !opt.email) {
    toast.error(t('notificationsCenter.composeExternalNoEmail'))
    return
  }
  selectedRecipient.value = { ...opt }
  recipientQuery.value = opt.label
  showDropdown.value = false
}

function clearRecipient() {
  selectedRecipient.value = null
  recipientQuery.value = ''
}

function onRecipientBlur() {
  window.setTimeout(() => {
    showDropdown.value = false
  }, 150)
}

function close() {
  emit('update:modelValue', false)
}

function resetForm() {
  recipientQuery.value = ''
  selectedRecipient.value = null
  subject.value = ''
  message.value = ''
  showDropdown.value = false
}

function openMailto(email: string, subj: string, body: string) {
  const params = new URLSearchParams({
    subject: subj,
    body: body,
  })
  window.location.href = `mailto:${encodeURIComponent(email)}?${params.toString()}`
}

async function submit() {
  if (!props.departmentId || !selectedRecipient.value || !canSubmit.value) return
  const rec = selectedRecipient.value
  const subj = subject.value.trim()
  const body = message.value.trim()

  if (rec.kind === 'external') {
    if (!rec.email) {
      toast.error(t('notificationsCenter.composeExternalNoEmail'))
      return
    }
    openMailto(rec.email, subj, body)
    toast.success(t('notificationsCenter.toastExternalMailOpened'))
    resetForm()
    close()
    emit('sent')
    return
  }

  if (!rec.userId) return
  isSending.value = true
  try {
    await sendUserDirectMessage(props.departmentId, {
      recipient_user_id: rec.userId,
      subject: subj,
      message: body,
    })
    toast.success(t('notificationsCenter.toastMessageSent'))
    resetForm()
    close()
    emit('sent')
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('notificationsCenter.toastMessageSendFailed'))
  } finally {
    isSending.value = false
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      void loadExternalAddresses()
    } else {
      resetForm()
    }
  },
)
</script>

<style scoped>
.nc-compose-modal {
  width: min(520px, calc(100vw - 48px));
  padding: 0;
  overflow: visible;
}

.nc-compose-modal__form {
  display: grid;
  gap: 14px;
}

.nc-compose-modal__footer {
  margin: 8px -20px -20px;
  border-radius: 0 0 12px 12px;
}

.nc-compose-field {
  display: grid;
  gap: 6px;
  font-size: 0.85rem;
}

.nc-compose-field > span {
  font-weight: 500;
  color: #374151;
}

.nc-compose-external-hint {
  margin: 0;
  font-size: 0.8rem;
  color: #6b7280;
  line-height: 1.35;
}

.autocomplete-wrapper {
  position: relative;
}

.nc-compose-autocomplete {
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  z-index: 10;
  max-height: 240px;
  overflow-y: auto;
  margin-top: 4px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}

.nc-compose-ac-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 12px;
  border: none;
  background: transparent;
  text-align: left;
  font: inherit;
  cursor: pointer;
}

.nc-compose-ac-item:hover {
  background: #f3f4f6;
}

.nc-compose-ac-item__text {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 2px;
}

.nc-compose-ac-item .ac-name {
  font-weight: 600;
  color: #111827;
  font-size: 0.9rem;
}

.nc-compose-ac-item .ac-email {
  font-size: 0.8rem;
  color: #6b7280;
}

.nc-compose-ac-item__icon {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #e2e8f0;
  color: #475569;
  font-size: 0.85rem;
}

.nc-compose-recipient-chip {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #f9fafb;
}

.nc-compose-recipient-chip__text {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 2px;
}

.nc-compose-recipient-chip__name {
  font-weight: 600;
  font-size: 0.9rem;
}

.nc-compose-recipient-chip__sub {
  font-size: 0.8rem;
  color: #6b7280;
}

.nc-compose-recipient-chip__icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #e2e8f0;
  color: #475569;
  font-size: 0.85rem;
}

.nc-compose-recipient-chip__clear {
  border: none;
  background: transparent;
  font-size: 1.25rem;
  line-height: 1;
  color: #6b7280;
  cursor: pointer;
  padding: 0 4px;
}

.nc-compose-recipient-badge {
  flex-shrink: 0;
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 2px 6px;
  border-radius: 4px;
  background: #fef3c7;
  color: #92400e;
}

.autocomplete-empty {
  padding: 12px;
  color: #6b7280;
  font-size: 0.85rem;
}
</style>
