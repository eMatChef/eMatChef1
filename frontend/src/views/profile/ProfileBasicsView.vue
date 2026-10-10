<template>
  <form class="profile-basics" @submit.prevent="onSubmit">
    <div class="profile-top-row" data-onboarding="profile-identity">
      <UserAvatarBadge class="profile-avatar-preview" :user="profilePreviewAvatarUser" variant="profile" size="lg" :show-tooltip="false" />
      <div class="profile-top-fields">
        <ETextField v-model="profileForm.last_name" :label="t('layout.profileModal.lastName')" maxlength="100" />
        <ETextField v-model="profileForm.first_name" :label="t('layout.profileModal.firstName')" maxlength="100" />
        <div>
          <div class="email-edit-row">
            <div class="email-edit-row__field">
              <ETextField
                v-model="profileForm.email"
                :label="t('layout.profileModal.email')"
                type="email"
                maxlength="180"
                autocomplete="username"
                disabled
              />
            </div>
            <EButton
              variant="secondary"
              size="small"
              class="email-edit-btn"
              data-testid="manage-emails"
              :title="t('layout.profileModal.editEmailTitle')"
              :aria-label="t('layout.profileModal.editEmailTitle')"
              @click="context.openTab('emails')"
            >
              <v-icon icon="mdi-pencil-outline" size="18" />
            </EButton>
          </div>
          <small class="profile-hint profile-hint--warning">{{ t('layout.profileModal.emailManagedInSecurity') }}</small>
          <small v-if="pendingEmailTarget" class="profile-hint profile-hint--warning">
            {{
              t('layout.profileModal.emailPendingSent', {
                pending: pendingEmailTarget,
                current: authStore.profile?.email || profileForm.email,
              })
            }}
          </small>
        </div>
      </div>
    </div>

    <div class="profile-form-grid" data-onboarding="profile-personal">
      <ETextField
        v-model="profileForm.nickname"
        :label="t('layout.profileModal.nickname')"
        maxlength="50"
        :placeholder="t('layout.profileModal.nicknamePlaceholder')"
      />
      <ETextField
        v-model="profileForm.avatar_initials"
        :label="t('layout.profileModal.initialsMax2')"
        maxlength="2"
        :placeholder="generatedInitialsTemplate"
        @update:model-value="profileForm.avatar_initials = String($event ?? '').toUpperCase()"
      />
      <ESelect v-model="profileForm.language" :label="t('layout.profileModal.language')" :items="languageItems" />
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
        <input type="text" name="emc-username-decoy" autocomplete="username" tabindex="-1" aria-hidden="true" class="profile-autofill-decoy" />
        <input type="password" name="emc-password-decoy" autocomplete="new-password" tabindex="-1" aria-hidden="true" class="profile-autofill-decoy" />
        <div class="profile-form-grid">
          <ETextField
            v-model="passwordForm.current_password"
            :label="t('layout.profileModal.currentPassword')"
            type="password"
            name="emc-current-password"
            autocomplete="off"
            data-lpignore="true"
            data-1p-ignore="true"
            :placeholder="t('layout.profileModal.currentPasswordPlaceholder')"
          />
          <ETextField
            v-model="passwordForm.new_password"
            :label="t('layout.profileModal.newPassword')"
            type="password"
            name="emc-new-password"
            autocomplete="new-password"
            data-lpignore="true"
            data-1p-ignore="true"
            :placeholder="t('layout.profileModal.newPasswordPlaceholder')"
          />
          <ETextField
            v-model="passwordForm.confirm_new_password"
            class="profile-form-grid__full"
            :label="t('layout.profileModal.confirmNewPassword')"
            type="password"
            name="emc-confirm-password"
            autocomplete="new-password"
            data-lpignore="true"
            data-1p-ignore="true"
            :placeholder="t('layout.profileModal.confirmNewPasswordPlaceholder')"
          />
        </div>
        <small v-if="passwordInlineError" class="profile-hint profile-hint--error">{{ passwordInlineError }}</small>
        <small v-else-if="passwordInlineSuccess" class="profile-hint profile-hint--success">{{ t('layout.profileModal.passwordOk') }}</small>
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
        <p class="profile-hint">{{ t('layout.profileModal.addressHintJs') }}</p>
        <p v-if="!addressAvailable" class="profile-hint profile-hint--warning" data-testid="address-needs-department">
          {{ t('profile.page.addressNeedsDepartment') }}
        </p>
        <div class="profile-form-grid">
          <ETextField
            v-model="addressForm.street"
            class="profile-form-grid__full"
            :label="t('layout.profileModal.street')"
            autocomplete="street-address"
            :disabled="!addressAvailable"
            :placeholder="t('layout.profileModal.streetPlaceholder')"
          />
          <ETextField v-model="addressForm.street_number" :label="t('layout.profileModal.streetNumber')" autocomplete="off" :disabled="!addressAvailable" />
          <ETextField v-model="addressForm.postal_code" :label="t('layout.profileModal.postalCode')" autocomplete="postal-code" :disabled="!addressAvailable" />
          <ETextField v-model="addressForm.city" :label="t('layout.profileModal.city')" autocomplete="address-level2" :disabled="!addressAvailable" />
          <ESelect v-model="addressForm.canton" :label="t('layout.profileModal.canton')" :items="cantonItems" :disabled="!addressAvailable" />
        </div>
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

        <div class="profile-form-grid">
          <div class="color-field">
            <input v-model="profileForm.background_color" type="color" :aria-label="t('layout.profileModal.backgroundColor')" />
            <ETextField
              v-model="profileForm.background_color"
              :label="t('layout.profileModal.backgroundColor')"
              maxlength="7"
              :placeholder="t('layout.profileModal.backgroundColorPlaceholder')"
            />
          </div>
          <div class="color-field">
            <input v-model="profileForm.text_color" type="color" :aria-label="t('layout.profileModal.textColor')" />
            <ETextField
              v-model="profileForm.text_color"
              :label="t('layout.profileModal.textColor')"
              maxlength="7"
              :placeholder="t('layout.profileModal.textColorPlaceholder')"
            />
          </div>
        </div>
      </div>
    </details>

    <div class="profile-basics__footer">
      <div class="profile-status-hint" :class="{ visible: hasUnsavedChanges }">
        <span v-if="hasUnsavedChanges">{{ t('layout.profileModal.unsavedChanges') }}</span>
      </div>
      <EButton variant="secondary" size="small" :disabled="savingProfile" @click="leave">{{ t('common.cancel') }}</EButton>
      <EButton
        type="submit"
        size="small"
        data-onboarding="profile-save"
        :loading="savingProfile"
        :disabled="savingProfile || (!isTourProfileSaveStep && !hasUnsavedChanges) || !!passwordInlineError"
      >
        {{ savingProfile ? t('layout.profileModal.saving') : t('common.save') }}
      </EButton>
    </div>
  </form>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { AVATAR_PALETTE_COLORS, useProfileForm } from '@/composables/useProfileForm'
import { useProfileContext } from '@/composables/useProfileContext'
import { ONBOARDING_TOUR_QUERY, ONBOARDING_TOUR_STEP_QUERY } from '@/config/onboardingTours'
import { EButton, ESelect, ETextField } from '@/components/form/base'
import UserAvatarBadge from '@/components/user/UserAvatarBadge.vue'
import ProfileDriveLicenseAccordion from '@/components/layout/ProfileDriveLicenseAccordion.vue'

const { t } = useI18n()
const route = useRoute()
const authStore = useAuthStore()
const confirm = useConfirm()
const toast = useToast()
const context = useProfileContext()

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
const languageItems = computed(() =>
  ['de', 'en', 'fr', 'it'].map((code) => ({ title: t(`languageNames.${code}`), value: code })),
)
const cantonItems = computed(() => [
  { title: t('layout.profileModal.cantonEmpty'), value: '' },
  ...Object.entries(swissCantons).map(([code, label]) => ({ title: `${code} – ${label}`, value: code })),
])

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

/** Page: back to where the profile was opened from. Modal: close it (the modal asks about unsaved input itself). */
async function leave() {
  if (savingProfile.value) return
  leaving = true
  try {
    await context.leave()
  } finally {
    leaving = false
  }
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

// Seite: ungespeicherte Eingaben beim Verlassen der Route bestätigen. Modal: der Dialog meldet sich über setDirty.
if (context.mode === 'page') {
  onBeforeRouteLeave(async () => {
    if (leaving && !hasUnsavedChanges.value) return true
    return confirmDiscard()
  })
}
watch(hasUnsavedChanges, (dirty) => context.setDirty(dirty))
onBeforeUnmount(() => context.setDirty(false))

onMounted(() => void load())
</script>

<style scoped>
.profile-top-row {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 12px;
  align-items: start;
  margin-bottom: 10px;
}

.profile-top-fields {
  display: grid;
  gap: 4px;
}

.profile-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px 12px;
  margin-bottom: 10px;
}

.profile-form-grid__full {
  grid-column: 1 / -1;
}

.email-edit-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.email-edit-row__field {
  flex: 1 1 auto;
  min-width: 0;
}

.email-edit-btn {
  margin-top: 4px;
}

.profile-hint {
  display: block;
  margin: 2px 0 8px;
  font-size: 0.75rem;
  line-height: 1.4;
  color: var(--color-text-muted);
}

.profile-hint--warning {
  color: var(--color-warning-text);
}

.profile-hint--error {
  color: var(--color-error);
}

.profile-hint--success {
  color: var(--color-primary-dark);
}

.profile-autofill-decoy {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}

.profile-accordion {
  margin: 0 0 10px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  background: var(--color-surface-muted);
  overflow: hidden;
}

.profile-accordion__summary {
  cursor: pointer;
  list-style: none;
  padding: 12px 14px;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
  user-select: none;
}

.profile-accordion__summary::-webkit-details-marker {
  display: none;
}

.profile-accordion__summary::after {
  content: '▾';
  float: right;
  color: var(--color-text-muted);
  transition: transform 0.15s ease;
}

.profile-accordion[open] > .profile-accordion__summary::after {
  transform: rotate(-180deg);
}

.profile-accordion__body {
  padding: 12px 14px 14px;
  background: rgb(var(--v-theme-surface));
  border-top: 1px solid var(--color-border);
}

.avatar-palette-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.palette-row-label {
  font-size: 0.6875rem;
  color: var(--color-text-muted);
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
  border-color: var(--color-text);
  box-shadow: 0 0 0 2px var(--color-primary-ring);
}

.color-field {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.color-field input[type='color'] {
  width: 40px;
  height: 40px;
  margin-top: 4px;
  padding: 2px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  background: transparent;
  cursor: pointer;
}

.color-field > :last-child {
  flex: 1 1 auto;
  min-width: 0;
}

.profile-basics__footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
}

.profile-status-hint {
  margin-right: auto;
  min-height: 16px;
  font-size: 0.6875rem;
  color: var(--color-warning-text);
  opacity: 0;
  transition: opacity 0.15s ease;
}

.profile-status-hint.visible {
  opacity: 1;
}

@media (max-width: 599px) {
  .profile-form-grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .profile-top-row {
    grid-template-columns: minmax(0, 1fr);
    justify-items: center;
  }

  .profile-top-fields {
    width: 100%;
  }
}
</style>
