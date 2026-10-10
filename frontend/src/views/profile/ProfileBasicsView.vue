<template>
  <form class="profile-basics" @submit.prevent="onSubmit">
    <div class="profile-basics__content">
            <div class="profile-top-row" data-onboarding="profile-identity">
            <UserAvatarBadge
              class="profile-avatar-preview"
              :user="profilePreviewAvatarUser"
              variant="profile"
              size="lg"
              :show-tooltip="false"
            />
            <div class="profile-top-fields">
              <label class="form-field">
                <span>{{ t('layout.profileModal.lastName') }}</span>
                <input v-model="profileForm.last_name" type="text" maxlength="100" />
              </label>

              <label class="form-field">
                <span>{{ t('layout.profileModal.firstName') }}</span>
                <input v-model="profileForm.first_name" type="text" maxlength="100" />
              </label>

              <label class="form-field">
                <span>{{ t('layout.profileModal.email') }}</span>
                <div class="email-edit-row">
                  <input
                    v-model="profileForm.email"
                    type="email"
                    maxlength="180"
                    autocomplete="username"
                    disabled
                    class="is-readonly"
                  />
                  <router-link
                    class="email-edit-btn"
                    data-testid="manage-emails"
                    :to="emailsTab"
                    :title="t('layout.profileModal.editEmailTitle')"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                      <path d="M12 20h9" stroke-width="2" stroke-linecap="round" />
                      <path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke-width="2" stroke-linejoin="round" />
                    </svg>
                  </router-link>
                </div>
                <small class="email-edit-hint">
                  {{ t('layout.profileModal.emailManagedInSecurity') }}
                </small>
                <small v-if="pendingEmailTarget" class="email-pending-hint">
                  {{
                    t('layout.profileModal.emailPendingSent', {
                      pending: pendingEmailTarget,
                      current: authStore.profile?.email || profileForm.email,
                    })
                  }}
                </small>
              </label>
            </div>
            </div>

            <div class="profile-form-grid" data-onboarding="profile-personal">

            <label class="form-field">
              <span>{{ t('layout.profileModal.nickname') }}</span>
              <input v-model="profileForm.nickname" type="text" maxlength="50" :placeholder="t('layout.profileModal.nicknamePlaceholder')" />
            </label>

            <label class="form-field">
              <span>{{ t('layout.profileModal.initialsMax2') }}</span>
              <input
                v-model="profileForm.avatar_initials"
                type="text"
                maxlength="2"
                :placeholder="generatedInitialsTemplate"
                @input="profileForm.avatar_initials = profileForm.avatar_initials.toUpperCase()"
              />
            </label>

            <label class="form-field">
              <span>{{ t('layout.profileModal.language') }}</span>
              <select v-model="profileForm.language">
                <option value="de">{{ t('languageNames.de') }}</option>
                <option value="en">{{ t('languageNames.en') }}</option>
                <option value="fr">{{ t('languageNames.fr') }}</option>
                <option value="it">{{ t('languageNames.it') }}</option>
              </select>
            </label>
            </div>

            <details
              class="profile-accordion"
              data-onboarding="profile-password"
              :open="profileAccordion.password"
              @toggle="onProfileAccordionToggle('password', $event)"
            >
              <summary class="profile-accordion__summary">{{ t('layout.profileModal.passwordSection') }}</summary>
              <div class="profile-accordion__body">
              <!-- Chrome-Autofill ablenken -->
              <input
                type="text"
                name="emc-username-decoy"
                autocomplete="username"
                tabindex="-1"
                aria-hidden="true"
                class="profile-autofill-decoy"
              />
              <input
                type="password"
                name="emc-password-decoy"
                autocomplete="new-password"
                tabindex="-1"
                aria-hidden="true"
                class="profile-autofill-decoy"
              />
              <div class="profile-form-grid">
                <label class="form-field">
                  <span>{{ t('layout.profileModal.currentPassword') }}</span>
                  <input
                    v-model="passwordForm.current_password"
                    type="password"
                    name="emc-current-password"
                    autocomplete="off"
                    data-lpignore="true"
                    data-1p-ignore="true"
                    :placeholder="t('layout.profileModal.currentPasswordPlaceholder')"
                  />
                </label>
                <label class="form-field">
                  <span>{{ t('layout.profileModal.newPassword') }}</span>
                  <input
                    v-model="passwordForm.new_password"
                    type="password"
                    name="emc-new-password"
                    autocomplete="new-password"
                    data-lpignore="true"
                    data-1p-ignore="true"
                    :placeholder="t('layout.profileModal.newPasswordPlaceholder')"
                  />
                </label>
                <label class="form-field form-field-full">
                  <span>{{ t('layout.profileModal.confirmNewPassword') }}</span>
                  <input
                    v-model="passwordForm.confirm_new_password"
                    type="password"
                    name="emc-confirm-password"
                    autocomplete="new-password"
                    data-lpignore="true"
                    data-1p-ignore="true"
                    :placeholder="t('layout.profileModal.confirmNewPasswordPlaceholder')"
                  />
                </label>
              </div>
              <small v-if="passwordInlineError" class="password-inline-error">{{ passwordInlineError }}</small>
              <small v-else-if="passwordInlineSuccess" class="password-inline-success">{{ t('layout.profileModal.passwordOk') }}</small>
              </div>
            </details>

            <details
              class="profile-accordion"
              data-onboarding="profile-address"
              :open="profileAccordion.address"
              @toggle="onProfileAccordionToggle('address', $event)"
            >
              <summary class="profile-accordion__summary">{{ t('layout.profileModal.addressSection') }}</summary>
              <div class="profile-accordion__body">
              <p class="profile-address-hint">{{ t('layout.profileModal.addressHintJs') }}</p>
              <p v-if="!addressAvailable" class="profile-address-hint" data-testid="address-needs-department">
                {{ t('profile.page.addressNeedsDepartment') }}
              </p>
              <fieldset :disabled="!addressAvailable" class="profile-address-fields">
              <div class="profile-form-grid">
                <label class="form-field form-field-full">
                  <span>{{ t('layout.profileModal.street') }}</span>
                  <input
                    v-model="addressForm.street"
                    type="text"
                    autocomplete="street-address"
                    :placeholder="t('layout.profileModal.streetPlaceholder')"
                  />
                </label>
                <label class="form-field">
                  <span>{{ t('layout.profileModal.streetNumber') }}</span>
                  <input v-model="addressForm.street_number" type="text" autocomplete="off" />
                </label>
                <label class="form-field">
                  <span>{{ t('layout.profileModal.postalCode') }}</span>
                  <input v-model="addressForm.postal_code" type="text" autocomplete="postal-code" />
                </label>
                <label class="form-field">
                  <span>{{ t('layout.profileModal.city') }}</span>
                  <input v-model="addressForm.city" type="text" autocomplete="address-level2" />
                </label>
                <label class="form-field">
                  <span>{{ t('layout.profileModal.canton') }}</span>
                  <select v-model="addressForm.canton">
                    <option value="">{{ t('layout.profileModal.cantonEmpty') }}</option>
                    <option v-for="(label, code) in swissCantons" :key="code" :value="code">
                      {{ code }} – {{ label }}
                    </option>
                  </select>
                </label>
              </div>
              </fieldset>
              </div>
            </details>
            <ProfileDriveLicenseAccordion :open="true" />

            <details
              class="profile-accordion"
              data-onboarding="profile-colors"
              :open="profileAccordion.colors"
              @toggle="onProfileAccordionToggle('colors', $event)"
            >
              <summary class="profile-accordion__summary">{{ t('layout.profileModal.colorCombinations') }}</summary>
              <div class="profile-accordion__body">
              <div class="avatar-palette-wrap">
                <div class="palette-row-label">{{ t('layout.profileModal.paletteWhiteInitials') }}</div>
                <div class="avatar-palette-row">
                  <button
                    v-for="color in avatarPaletteColors"
                    :key="`w-${color}`"
                    type="button"
                    class="avatar-color-chip"
                    :class="{ selected: isSelectedAvatarColor(color, '#FFFFFF') }"
                    :style="{ backgroundColor: color, color: '#FFFFFF' }"
                    @click="applyAvatarColor(color, '#FFFFFF')"
                  >
                    {{ profilePreviewInitials }}
                  </button>
                </div>
                <div class="palette-row-label">{{ t('layout.profileModal.paletteBlackInitials') }}</div>
                <div class="avatar-palette-row">
                  <button
                    v-for="color in avatarPaletteColors"
                    :key="`b-${color}`"
                    type="button"
                    class="avatar-color-chip"
                    :class="{ selected: isSelectedAvatarColor(color, '#111111') }"
                    :style="{ backgroundColor: color, color: '#111111' }"
                    @click="applyAvatarColor(color, '#111111')"
                  >
                    {{ profilePreviewInitials }}
                  </button>
                </div>
              </div>

              <label class="form-field">
                <span>{{ t('layout.profileModal.backgroundColor') }}</span>
                <div class="color-field">
                  <input v-model="profileForm.background_color" type="color" />
                  <input
                    v-model="profileForm.background_color"
                    type="text"
                    maxlength="7"
                    :placeholder="t('layout.profileModal.backgroundColorPlaceholder')"
                  />
                </div>
              </label>

              <label class="form-field">
                <span>{{ t('layout.profileModal.textColor') }}</span>
                <div class="color-field">
                  <input v-model="profileForm.text_color" type="color" />
                  <input
                    v-model="profileForm.text_color"
                    type="text"
                    maxlength="7"
                    :placeholder="t('layout.profileModal.textColorPlaceholder')"
                  />
                </div>
              </label>
              </div>
            </details>
    </div>

    <div class="profile-basics__footer">
      <div class="profile-status-hint" :class="{ visible: hasUnsavedChanges }">
        <span v-if="hasUnsavedChanges">{{ t('layout.profileModal.unsavedChanges') }}</span>
      </div>
      <button type="button" class="btn-secondary btn-sm" :disabled="savingProfile" @click="leave">{{ t('common.cancel') }}</button>
      <button
        type="submit"
        class="btn-primary btn-sm"
        data-onboarding="profile-save"
        :disabled="savingProfile || (!isTourProfileSaveStep && !hasUnsavedChanges) || !!passwordInlineError"
      >
        {{ savingProfile ? t('layout.profileModal.saving') : t('common.save') }}
      </button>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { AVATAR_PALETTE_COLORS, useProfileForm } from '@/composables/useProfileForm'
import { ONBOARDING_TOUR_QUERY, ONBOARDING_TOUR_STEP_QUERY } from '@/config/onboardingTours'
import { profileFallbackPath, profileFromQuery } from '@/utils/profileReturn'
import UserAvatarBadge from '@/components/user/UserAvatarBadge.vue'
import ProfileDriveLicenseAccordion from '@/components/layout/ProfileDriveLicenseAccordion.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const confirm = useConfirm()
const toast = useToast()

const {
  profileForm,
  passwordForm,
  addressForm,
  swissCantons,
  savingProfile,
  addressAvailable,
  generatedInitialsTemplate,
  profilePreviewInitials,
  profilePreviewAvatarUser,
  hasUnsavedChanges,
  passwordInlineError,
  passwordInlineSuccess,
  pendingEmailTarget,
  load,
  save,
  applyAvatarColor,
  isSelectedAvatarColor,
} = useProfileForm()

const avatarPaletteColors = AVATAR_PALETTE_COLORS
const emailsTab = computed(() => ({ name: 'ProfileEmails', query: route.query }))

// Onboarding-Tour «profile-overview»: Schritt 18 speichert ohne Änderungen und verlässt das Profil
const isTourProfileSaveStep = computed(
  () => route.query[ONBOARDING_TOUR_QUERY] === 'profile-overview' && route.query[ONBOARDING_TOUR_STEP_QUERY] === '18',
)

const profileAccordion = reactive({ password: false, address: false, colors: false })

watch(
  () => [route.query[ONBOARDING_TOUR_QUERY], route.query[ONBOARDING_TOUR_STEP_QUERY]] as const,
  ([tour, step]) => {
    if (tour !== 'profile-overview') return
    if (step === '15') {
      profileAccordion.password = true
      profileAccordion.address = false
      profileAccordion.colors = false
    } else if (step === '16') {
      profileAccordion.password = false
      profileAccordion.address = true
      profileAccordion.colors = false
    } else if (step === '17') {
      profileAccordion.password = false
      profileAccordion.address = false
      profileAccordion.colors = true
    }
  },
  { immediate: true },
)

function onProfileAccordionToggle(key: 'password' | 'address' | 'colors', event: Event) {
  const el = event.target as HTMLDetailsElement
  if (el?.tagName === 'DETAILS') profileAccordion[key] = el.open
}

let leaving = false

async function confirmDiscard(): Promise<boolean> {
  if (!hasUnsavedChanges.value) return true
  return confirm.confirm({
    title: t('layout.confirm.unsavedTitle'),
    message: t('layout.confirm.unsavedMessage'),
    confirmText: t('common.close'),
    cancelText: t('layout.confirm.back'),
    variant: 'warning',
  })
}

/** Back to the page the profile was opened from (validated `from`), else the active department. */
async function leave() {
  if (savingProfile.value) return
  leaving = true
  const target = profileFromQuery(route.query) ?? profileFallbackPath(authStore.activeDepartmentId)
  await router.push(target)
  leaving = false
}

async function onSubmit() {
  const result = await save()
  if (result === 'unchanged') {
    if (isTourProfileSaveStep.value) {
      await leave()
      return
    }
    toast.info(t('layout.toast.noChanges'))
  } else if (result === 'saved' && isTourProfileSaveStep.value) {
    await leave()
  }
}

onBeforeRouteLeave(async () => {
  if (leaving && !hasUnsavedChanges.value) return true
  return confirmDiscard()
})

onMounted(() => void load())
</script>

<style scoped>
.profile-basics__content {
  padding: 14px 0;
}

.profile-basics__footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 0 0;
  border-top: 1px solid #e5e7eb;
}

.profile-avatar-preview-wrap {
  display: flex;
  justify-content: center;
  margin-bottom: 12px;
}

.profile-top-row {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 12px;
  align-items: start;
  margin-bottom: 10px;
}

.profile-top-fields {
  display: grid;
  gap: 7px;
}

.profile-form-grid {
  margin-bottom: 10px;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 9px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-field-full {
  grid-column: 1 / -1;
}

.form-field span {
  font-size: 11px;
  color: #6b7280;
}

.form-field input,
.form-field select {
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 8px 9px;
  font-size: 13px;
  background: #fff;
}

.form-field input.is-readonly {
  background: #f9fafb;
  color: #6b7280;
}

.form-field input:focus,
.form-field select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.color-field {
  display: flex;
  gap: 8px;
}

.color-field input[type='color'] {
  width: 40px;
  min-width: 40px;
  padding: 2px;
  cursor: pointer;
}

.email-edit-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.email-edit-row input {
  flex: 1;
}

.email-edit-btn {
  width: 32px;
  height: 32px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  color: #4b5563;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.email-edit-btn svg {
  width: 14px;
  height: 14px;
}

.email-edit-btn.active {
  border-color: #2563eb;
  color: #2563eb;
  background: #eff6ff;
}

.email-edit-hint {
  color: #92400e;
  font-size: 11px;
}

.email-pending-hint {
  color: #1d4ed8;
  font-size: 11px;
}

.password-inline-error {
  color: #b91c1c;
  font-size: 11px;
}

.profile-address-hint {
  margin: 4px 0 10px;
  font-size: 12px;
  line-height: 1.4;
  color: #64748b;
}

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

.profile-accordion__summary::-webkit-details-marker {
  display: none;
}

.profile-accordion__summary::after {
  content: '▾';
  float: right;
  color: #94a3b8;
  transition: transform 0.15s ease;
}

.profile-accordion[open] > .profile-accordion__summary::after {
  transform: rotate(-180deg);
}

.profile-accordion__body {
  padding: 0 14px 14px;
  background: #fff;
  border-top: 1px solid #e5e7eb;
}

.password-inline-success {
  color: #166534;
  font-size: 11px;
}

.avatar-palette-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.palette-row-label {
  font-size: 11px;
  color: #6b7280;
}

.avatar-palette-row {
  display: grid;
  grid-template-columns: repeat(10, minmax(0, 1fr));
  gap: 6px;
}

.avatar-color-chip {
  width: 30px;
  height: 30px;
  border-radius: 9999px;
  border: 2px solid transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
  cursor: pointer;
  padding: 0;
}

.avatar-color-chip.selected {
  border-color: #111827;
  box-shadow: 0 0 0 2px rgba(17, 24, 39, 0.15);
}

.profile-status-hint {
  margin-right: auto;
  font-size: 11px;
  color: #d97706;
  min-height: 16px;
  opacity: 0;
  transition: opacity 0.15s ease;
}

.profile-status-hint.visible {
  opacity: 1;
}


.profile-address-fields {
  margin: 0;
  padding: 0;
  border: 0;
  min-width: 0;
}

.email-edit-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

</style>
