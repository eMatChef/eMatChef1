<template>
  <EDialog
    :model-value="modelValue"
    :max-width="620"
    :title="
      member
        ? t('settings.departmentUsers.memberDetailsTitle', { name: member.name })
        : t('layout.profileModal.title')
    "
    data-onboarding="settings-user-edit-dialog"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <template v-if="member">
      <div class="member-profile-edit">
        <details class="member-profile-accordion" open>
          <summary class="member-profile-accordion__summary">
            {{ t('settings.departmentUsers.editSectionProfile') }}
          </summary>
          <div class="member-profile-accordion__body" data-onboarding="settings-user-edit-profile">
            <div class="member-profile-top-row">
              <UserAvatarBadge
                class="member-profile-avatar"
                :user="editPreviewAvatarUser"
                variant="profile"
                size="lg"
                :show-tooltip="false"
              />
              <div class="member-profile-top-fields">
                <label class="member-form-field">
                  <span>{{ t('layout.profileModal.lastName') }}</span>
                  <input v-model="editForm.last_name" type="text" maxlength="100" />
                </label>
                <label class="member-form-field">
                  <span>{{ t('layout.profileModal.firstName') }}</span>
                  <input v-model="editForm.first_name" type="text" maxlength="100" />
                </label>
                <label class="member-form-field">
                  <span>{{ t('layout.profileModal.email') }}</span>
                  <div class="member-email-edit-row">
                    <input
                      v-model="editForm.email"
                      type="email"
                      maxlength="180"
                      autocomplete="off"
                      :disabled="!isEditEmailEnabled"
                      :class="{ 'is-readonly': !isEditEmailEnabled }"
                    />
                    <button
                      type="button"
                      class="member-email-edit-btn"
                      :class="{ active: isEditEmailEnabled }"
                      :title="t('layout.profileModal.editEmailTitle')"
                      @click="isEditEmailEnabled = !isEditEmailEnabled"
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path d="M12 20h9" stroke-width="2" stroke-linecap="round" />
                        <path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke-width="2" stroke-linejoin="round" />
                      </svg>
                    </button>
                  </div>
                  <small v-if="isEditEmailEnabled" class="member-field-hint">
                    {{ t('layout.profileModal.emailNewMustVerify') }}
                  </small>
                  <small v-if="editPendingEmail" class="member-field-hint member-field-hint--pending">
                    {{
                      t('layout.profileModal.emailPendingSent', {
                        pending: editPendingEmail,
                        current: member.email,
                      })
                    }}
                  </small>
                </label>
              </div>
            </div>

            <div class="member-profile-form-grid">
              <label class="member-form-field">
                <span>{{ t('layout.profileModal.nickname') }}</span>
                <input
                  v-model="editForm.nickname"
                  type="text"
                  maxlength="50"
                  :placeholder="t('layout.profileModal.nicknamePlaceholder')"
                />
              </label>
              <label class="member-form-field">
                <span>{{ t('layout.profileModal.initialsMax2') }}</span>
                <input
                  v-model="editForm.avatar_initials"
                  type="text"
                  maxlength="2"
                  :placeholder="editGeneratedInitials"
                  @input="editForm.avatar_initials = editForm.avatar_initials.toUpperCase()"
                />
              </label>
              <label class="member-form-field member-form-field--full">
                <span>{{ t('layout.profileModal.language') }}</span>
                <select v-model="editForm.language">
                  <option value="de">{{ t('languageNames.de') }}</option>
                  <option value="en">{{ t('languageNames.en') }}</option>
                  <option value="fr">{{ t('languageNames.fr') }}</option>
                  <option value="it">{{ t('languageNames.it') }}</option>
                </select>
              </label>
            </div>
          </div>
        </details>

        <details class="member-profile-accordion" open>
          <summary class="member-profile-accordion__summary">
            {{ t('layout.profileModal.passwordSection') }}
          </summary>
          <div class="member-profile-accordion__body">
            <p class="member-field-hint">{{ t('settings.departmentUsers.passwordResetHint') }}</p>
            <EButton
              variant="secondary"
              size="small"
              :disabled="isSendingPasswordReset || isSaving"
              :loading="isSendingPasswordReset"
              @click="handleSendPasswordReset"
            >
              {{ t('settings.departmentUsers.sendPasswordReset') }}
            </EButton>
          </div>
        </details>

        <details class="member-profile-accordion" :open="addressAccordionOpen || undefined">
          <summary class="member-profile-accordion__summary">
            {{ t('layout.profileModal.addressSection') }}
          </summary>
          <div class="member-profile-accordion__body" data-onboarding="settings-user-edit-address">
            <p class="member-field-hint">{{ t('layout.profileModal.addressHintJs') }}</p>
            <div class="member-profile-form-grid">
              <label class="member-form-field member-form-field--full">
                <span>{{ t('layout.profileModal.street') }}</span>
                <input
                  v-model="editAddressForm.street"
                  type="text"
                  autocomplete="street-address"
                  :placeholder="t('layout.profileModal.streetPlaceholder')"
                />
              </label>
              <label class="member-form-field">
                <span>{{ t('layout.profileModal.streetNumber') }}</span>
                <input v-model="editAddressForm.street_number" type="text" autocomplete="off" />
              </label>
              <label class="member-form-field">
                <span>{{ t('layout.profileModal.postalCode') }}</span>
                <input v-model="editAddressForm.postal_code" type="text" autocomplete="postal-code" />
              </label>
              <label class="member-form-field">
                <span>{{ t('layout.profileModal.city') }}</span>
                <input v-model="editAddressForm.city" type="text" autocomplete="address-level2" />
              </label>
              <label class="member-form-field">
                <span>{{ t('layout.profileModal.canton') }}</span>
                <select v-model="editAddressForm.canton">
                  <option value="">{{ t('layout.profileModal.cantonEmpty') }}</option>
                  <option v-for="(label, code) in swissCantons" :key="code" :value="code">
                    {{ code }} – {{ label }}
                  </option>
                </select>
              </label>
            </div>
          </div>
        </details>

        <details class="member-profile-accordion" :open="membershipAccordionOpen || undefined">
          <summary class="member-profile-accordion__summary">
            {{ t('settings.departmentUsers.editSectionMembership') }}
          </summary>
          <div class="member-profile-accordion__body member-membership-fields">
            <ESelect
              v-model="editForm.role"
              :label="t('common.role')"
              :items="editRoleSelectItems"
              hide-details
            />
            <div v-if="!hideJsCoach" data-onboarding="settings-user-edit-coach">
              <ECheckbox
                v-model="editForm.is_js_coach"
                :label="t('settings.departmentUsers.jsCoachFlag')"
                hide-details
              />
            </div>
            <ECheckbox
              v-model="editForm.is_primary"
              :label="t('settings.departmentUsers.primaryDepartment')"
              hide-details
            />
          </div>
        </details>
      </div>
    </template>
    <template #actions>
      <EButton
        v-if="member && canManageMember(member)"
        variant="danger"
        size="small"
        :disabled="isSendingPasswordReset || isSaving"
        @click="handleRemoveFromDepartment"
      >
        {{ t('settings.departmentUsers.titleRemoveFromDept') }}
      </EButton>
      <EButton
        variant="secondary"
        size="small"
        :disabled="isSendingPasswordReset || isSaving"
        :loading="isSendingPasswordReset"
        @click="handleSendPasswordReset"
      >
        {{ t('settings.departmentUsers.sendPasswordReset') }}
      </EButton>
      <EButton variant="secondary" size="small" @click="$emit('update:modelValue', false)">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        size="small"
        :disabled="isSaving"
        :loading="isSaving"
        @click="handleUpdate"
      >
        {{ isSaving ? t('settings.departmentUsers.saving') : t('common.save') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import UserAvatarBadge from '@/components/user/UserAvatarBadge.vue'
import { EButton, EDialog, ESelect, ECheckbox } from '@/components/form/base'
import { buildAvatarInitials, type UserAvatarFields } from '@/utils/userAvatar'
import {
  createAddress,
  getAddresses,
  SWISS_CANTONS,
  updateAddress,
} from '@/api/addresses'
import {
  findAddressForProfile,
  profileAddressMarker,
  USER_ADDRESS_TYPE,
} from '@/utils/profileUserAddress'
import {
  sendDepartmentMemberPasswordReset,
  updateDepartmentMember,
  updateDepartmentMemberProfile,
  type DepartmentMember,
} from '@/api/departments'
import { useDepartmentMemberAdmin } from '@/composables/useDepartmentMemberAdmin'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    member: DepartmentMember | null
    departmentId: string
    hideJsCoach?: boolean
    membershipAccordionOpen?: boolean
    addressAccordionOpen?: boolean
  }>(),
  {
    hideJsCoach: false,
    membershipAccordionOpen: false,
    addressAccordionOpen: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: []
  removed: []
}>()

const { t } = useI18n()
const toast = useToast()
const { editRoleSelectItems, canManageMember, removeFromDepartment } = useDepartmentMemberAdmin(
  () => props.departmentId,
)

const swissCantons = SWISS_CANTONS
const isSaving = ref(false)
const isSendingPasswordReset = ref(false)
const isEditEmailEnabled = ref(false)
const editPendingEmail = ref<string | null>(null)
const editAddressRecordId = ref<string | null>(null)
const editForm = ref({
  role: 'u',
  is_primary: false,
  is_js_coach: false,
  first_name: '',
  last_name: '',
  nickname: '',
  email: '',
  avatar_initials: '',
  language: 'de',
})
const editAddressForm = ref({
  street: '',
  street_number: '',
  postal_code: '',
  city: '',
  canton: '',
})

const editGeneratedInitials = computed(() =>
  buildAvatarInitials(
    '',
    editForm.value.nickname,
    editForm.value.first_name,
    editForm.value.last_name,
  ),
)

const editPreviewAvatarUser = computed((): UserAvatarFields => ({
  first_name: editForm.value.first_name,
  last_name: editForm.value.last_name,
  nickname: editForm.value.nickname,
  avatar_initials: editForm.value.avatar_initials,
  background_color: props.member?.background_color ?? null,
  text_color: props.member?.text_color ?? null,
}))

function resetEditAddressForm() {
  editAddressForm.value = {
    street: '',
    street_number: '',
    postal_code: '',
    city: '',
    canton: '',
  }
  editAddressRecordId.value = null
}

function hydrateFromMember(member: DepartmentMember) {
  editForm.value = {
    role: member.role,
    is_primary: member.is_primary,
    is_js_coach: !!member.is_js_coach,
    first_name: member.first_name || '',
    last_name: member.last_name || '',
    nickname: member.nickname || '',
    email: member.email || '',
    avatar_initials: member.avatar_initials || '',
    language: member.language || 'de',
  }
  isEditEmailEnabled.value = false
  editPendingEmail.value = member.pending_email || null
  void loadMemberPrivateAddress(member)
}

async function loadMemberPrivateAddress(member: DepartmentMember) {
  resetEditAddressForm()
  try {
    const { addresses } = await getAddresses(props.departmentId, { type: USER_ADDRESS_TYPE })
    const match = findAddressForProfile(addresses, member.profile_id)
    if (!match) return
    editAddressRecordId.value = match.id
    editAddressForm.value = {
      street: match.street || '',
      street_number: match.street_number || '',
      postal_code: match.postal_code || '',
      city: match.city || '',
      canton: match.canton || '',
    }
  } catch {
    /* Adresse optional */
  }
}

async function saveMemberPrivateAddress(member: DepartmentMember) {
  const street = editAddressForm.value.street.trim()
  const postal = editAddressForm.value.postal_code.trim()
  const city = editAddressForm.value.city.trim()
  const hasAny =
    street
    || postal
    || city
    || editAddressForm.value.street_number.trim()
    || editAddressForm.value.canton.trim()
  if (!hasAny) return
  if (!street || !postal || !city) {
    throw new Error(t('layout.profileModal.addressIncomplete'))
  }
  const payload = {
    department_id: props.departmentId,
    type: USER_ADDRESS_TYPE,
    name: t('layout.profileModal.addressContactName', {
      name: `${editForm.value.first_name} ${editForm.value.last_name}`.trim() || member.name,
    }),
    street,
    street_number: editAddressForm.value.street_number.trim() || null,
    postal_code: postal,
    city,
    canton: editAddressForm.value.canton.trim() || null,
    country: 'Schweiz',
    contact_first_name: editForm.value.first_name.trim() || null,
    contact_last_name: editForm.value.last_name.trim() || null,
    email: editForm.value.email.trim() || member.email || null,
    additional_info: profileAddressMarker(member.profile_id),
  }
  if (editAddressRecordId.value) {
    await updateAddress(editAddressRecordId.value, payload)
  } else {
    const created = await createAddress(payload)
    editAddressRecordId.value = created.address.id
  }
}

watch(
  () => [props.modelValue, props.member?.user_id] as const,
  ([open]) => {
    if (open && props.member) hydrateFromMember(props.member)
    if (!open) {
      isEditEmailEnabled.value = false
      resetEditAddressForm()
    }
  },
)

async function handleSendPasswordReset() {
  if (!props.member || isSendingPasswordReset.value) return
  isSendingPasswordReset.value = true
  try {
    const result = await sendDepartmentMemberPasswordReset(props.departmentId, props.member.user_id)
    toast.success(result.message || t('settings.departmentUsers.toastPasswordResetSent'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('settings.departmentUsers.errPasswordReset'))
  } finally {
    isSendingPasswordReset.value = false
  }
}

async function handleUpdate() {
  if (!props.member || isSaving.value) return
  isSaving.value = true
  try {
    const member = props.member
    await Promise.all([
      updateDepartmentMemberProfile(props.departmentId, member.user_id, {
        first_name: editForm.value.first_name.trim() || null,
        last_name: editForm.value.last_name.trim() || null,
        nickname: editForm.value.nickname.trim() || null,
        email: editForm.value.email.trim(),
        avatar_initials: editForm.value.avatar_initials.trim() || null,
        language: editForm.value.language,
      }),
      updateDepartmentMember(props.departmentId, member.user_id, {
        role: editForm.value.role,
        is_primary: editForm.value.is_primary,
        is_js_coach: editForm.value.is_js_coach,
      }),
    ])
    await saveMemberPrivateAddress(member)
    emit('update:modelValue', false)
    emit('saved')
    toast.success(t('settings.departmentUsers.toastMemberUpdated'))
  } catch (err: unknown) {
    const e = err as { message?: string; response?: { data?: { error?: string } } }
    const message =
      (typeof e?.message === 'string' && !e?.response ? e.message : null)
      || e.response?.data?.error
      || t('settings.departmentUsers.errUpdateMember')
    toast.error(message)
  } finally {
    isSaving.value = false
  }
}

async function handleRemoveFromDepartment() {
  if (!props.member) return
  const removed = await removeFromDepartment(props.member)
  if (!removed) return
  emit('update:modelValue', false)
  emit('removed')
}
</script>

<style src="@/styles/components/department-member-detail.css"></style>
