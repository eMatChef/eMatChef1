import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { changePassword, login as apiLogin, updateProfile } from '@/api/auth'
import { createAddress, getAddresses, updateAddress, SWISS_CANTONS } from '@/api/addresses'
import { findAddressForProfile, profileAddressMarker, USER_ADDRESS_TYPE } from '@/utils/profileUserAddress'
import { useToast } from '@/composables/useToast'
import type { UserAvatarFields } from '@/utils/userAvatar'

export const AVATAR_PALETTE_COLORS = [
  '#2563EB',
  '#0EA5E9',
  '#14B8A6',
  '#22C55E',
  '#EAB308',
  '#F97316',
  '#EF4444',
  '#EC4899',
  '#A855F7',
  '#6B7280',
]

const DEFAULT_BACKGROUND = '#EC4899'
const DEFAULT_TEXT = '#FFFFFF'

export function normalizeHexColor(value: string, fallback: string): string {
  const normalized = value.trim().toUpperCase()
  return /^#[0-9A-F]{6}$/.test(normalized) ? normalized : fallback
}

export function buildAvatarInitials(explicitInitials: string, nickname: string, firstName: string, lastName: string): string {
  const explicit = explicitInitials.trim()
  if (explicit.length > 0) return explicit.slice(0, 2).toUpperCase()
  const nick = nickname.trim()
  if (nick.length > 0) return nick.replace(/\s+/g, '').slice(0, 2).toUpperCase()
  return (firstName.trim().charAt(0) + lastName.trim().charAt(0)).toUpperCase() || '??'
}

type ProfileFormState = {
  first_name: string
  last_name: string
  email: string
  nickname: string
  avatar_initials: string
  language: string
  background_color: string
  text_color: string
}

type AddressFormState = {
  street: string
  street_number: string
  postal_code: string
  city: string
  canton: string
}

function serializeProfileForm(form: ProfileFormState): string {
  return JSON.stringify({
    first_name: form.first_name.trim(),
    last_name: form.last_name.trim(),
    email: form.email.trim(),
    nickname: form.nickname.trim(),
    avatar_initials: form.avatar_initials.trim().toUpperCase().slice(0, 2),
    language: form.language,
    background_color: normalizeHexColor(form.background_color, DEFAULT_BACKGROUND),
    text_color: normalizeHexColor(form.text_color, DEFAULT_TEXT),
  })
}

function serializeAddressForm(form: AddressFormState): string {
  return JSON.stringify({
    street: form.street.trim(),
    street_number: form.street_number.trim(),
    postal_code: form.postal_code.trim(),
    city: form.city.trim(),
    canton: form.canton.trim(),
  })
}

const emptyAddress = (): AddressFormState => ({ street: '', street_number: '', postal_code: '', city: '', canton: '' })

/**
 * State and save logic of the personal profile page (name, language, colours, address, password).
 * The address is stored as a department address record, so it needs an active department.
 */
export function useProfileForm() {
  const { t } = useI18n()
  const authStore = useAuthStore()
  const toast = useToast()

  const profileForm = ref<ProfileFormState>({
    first_name: '',
    last_name: '',
    email: '',
    nickname: '',
    avatar_initials: '',
    language: 'de',
    background_color: DEFAULT_BACKGROUND,
    text_color: DEFAULT_TEXT,
  })
  const passwordForm = ref({ current_password: '', new_password: '', confirm_new_password: '' })
  const addressForm = ref<AddressFormState>(emptyAddress())
  const addressRecordId = ref<string | null>(null)
  const initialProfileFormSnapshot = ref('')
  const initialAddressSnapshot = ref('')
  const savingProfile = ref(false)

  const addressAvailable = computed(() => !!authStore.activeDepartmentId)

  const generatedInitialsTemplate = computed(() =>
    buildAvatarInitials('', profileForm.value.nickname, profileForm.value.first_name, profileForm.value.last_name),
  )
  const profilePreviewInitials = computed(() =>
    buildAvatarInitials(
      profileForm.value.avatar_initials,
      profileForm.value.nickname,
      profileForm.value.first_name,
      profileForm.value.last_name,
    ),
  )
  const profilePreviewAvatarUser = computed(
    (): UserAvatarFields => ({
      first_name: profileForm.value.first_name,
      last_name: profileForm.value.last_name,
      nickname: profileForm.value.nickname,
      avatar_initials: profileForm.value.avatar_initials,
      background_color: profileForm.value.background_color,
      text_color: profileForm.value.text_color,
    }),
  )

  const hasUnsavedProfileChanges = computed(
    () => !!initialProfileFormSnapshot.value && serializeProfileForm(profileForm.value) !== initialProfileFormSnapshot.value,
  )
  const hasAddressChanges = computed(
    () => !!initialAddressSnapshot.value && serializeAddressForm(addressForm.value) !== initialAddressSnapshot.value,
  )
  const hasPasswordInput = computed(() => !!passwordForm.value.new_password || !!passwordForm.value.confirm_new_password)
  const hasUnsavedChanges = computed(() => hasUnsavedProfileChanges.value || hasAddressChanges.value || hasPasswordInput.value)

  const passwordInlineError = computed(() => {
    if (!hasPasswordInput.value) return ''
    const { current_password: current, new_password: next, confirm_new_password: confirm } = passwordForm.value
    if (!current || !next || !confirm) return t('layout.passwordValidation.fillAll')
    if (next.length < 8) return t('layout.passwordValidation.minLength')
    if (next !== confirm) return t('layout.passwordValidation.mismatch')
    return ''
  })
  const passwordInlineSuccess = computed(() => hasPasswordInput.value && !passwordInlineError.value)

  const pendingEmailTarget = computed(() => (authStore.profile?.pendingEmail || authStore.profile?.pending_email || '').trim())

  function resetPasswordForm() {
    passwordForm.value = { current_password: '', new_password: '', confirm_new_password: '' }
  }

  function loadProfileForm() {
    const profile = authStore.profile
    profileForm.value = {
      first_name: profile?.firstName || profile?.first_name || '',
      last_name: profile?.lastName || profile?.last_name || '',
      email: profile?.email || '',
      nickname: profile?.nickname || '',
      avatar_initials: (profile?.avatarInitials || profile?.avatar_initials || '').toUpperCase().slice(0, 2),
      language: profile?.language || 'de',
      background_color: profile?.backgroundColor || profile?.background_color || DEFAULT_BACKGROUND,
      text_color: profile?.textColor || profile?.text_color || DEFAULT_TEXT,
    }
    initialProfileFormSnapshot.value = serializeProfileForm(profileForm.value)
    resetPasswordForm()
  }

  async function loadAddress() {
    addressForm.value = emptyAddress()
    addressRecordId.value = null
    initialAddressSnapshot.value = serializeAddressForm(addressForm.value)
    const profileId = authStore.profileId
    const departmentId = authStore.activeDepartmentId
    if (!profileId || !departmentId) return
    try {
      const { addresses } = await getAddresses(departmentId, { type: USER_ADDRESS_TYPE })
      const match = findAddressForProfile(addresses, profileId)
      if (!match) return
      addressRecordId.value = match.id
      addressForm.value = {
        street: match.street || '',
        street_number: match.street_number || '',
        postal_code: match.postal_code || '',
        city: match.city || '',
        canton: match.canton || '',
      }
      initialAddressSnapshot.value = serializeAddressForm(addressForm.value)
    } catch {
      /* Adresse optional — Fehler nicht blockierend */
    }
  }

  /** Loads the form from the signed-in profile and its address. */
  async function load() {
    loadProfileForm()
    await loadAddress()
  }

  /** Primary email was changed on the emails tab: pick it up without touching other edits. */
  function syncPrimaryEmail() {
    const email = authStore.profile?.email
    if (!email) return
    profileForm.value.email = email
    initialProfileFormSnapshot.value = serializeProfileForm(profileForm.value)
  }

  function applyAvatarColor(backgroundColor: string, textColor: string) {
    profileForm.value.background_color = backgroundColor
    profileForm.value.text_color = textColor
  }

  function isSelectedAvatarColor(backgroundColor: string, textColor: string): boolean {
    return (
      normalizeHexColor(profileForm.value.background_color, DEFAULT_BACKGROUND) === backgroundColor &&
      normalizeHexColor(profileForm.value.text_color, DEFAULT_TEXT) === textColor
    )
  }

  /**
   * Saves changed profile data, password and address.
   * @returns 'saved' | 'unchanged' | 'failed'
   */
  async function save(): Promise<'saved' | 'unchanged' | 'failed'> {
    const profileId = authStore.profileId
    if (!profileId) {
      toast.error(t('layout.toast.profileLoadFailed'))
      return 'failed'
    }
    const shouldUpdateProfile = hasUnsavedProfileChanges.value
    const shouldChangePassword = hasPasswordInput.value
    const shouldSaveAddress = hasAddressChanges.value
    if (!shouldUpdateProfile && !shouldChangePassword && !shouldSaveAddress) return 'unchanged'

    savingProfile.value = true
    try {
      if (shouldUpdateProfile) {
        const email = profileForm.value.email.trim()
        if (!email) {
          toast.error(t('layout.toast.enterEmail'))
          return 'failed'
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          toast.error(t('layout.toast.invalidEmail'))
          return 'failed'
        }
        const payload = {
          email: authStore.profile?.email || email,
          first_name: profileForm.value.first_name.trim(),
          last_name: profileForm.value.last_name.trim(),
          nickname: profileForm.value.nickname.trim(),
          avatar_initials: profileForm.value.avatar_initials.trim().toUpperCase().slice(0, 2),
          language: profileForm.value.language,
          background_color: normalizeHexColor(profileForm.value.background_color, DEFAULT_BACKGROUND),
          text_color: normalizeHexColor(profileForm.value.text_color, DEFAULT_TEXT),
        }
        authStore.profile = await updateProfile(profileId, payload)
      }

      if (shouldChangePassword) {
        if (passwordInlineError.value) {
          toast.error(passwordInlineError.value)
          return 'failed'
        }
        const newPassword = passwordForm.value.new_password
        const result = await changePassword(profileId, {
          current_password: passwordForm.value.current_password,
          new_password: newPassword,
          confirm_new_password: passwordForm.value.confirm_new_password,
        })
        if (result.message) toast.success(result.message)
        const loginEmail = (authStore.profile?.email || profileForm.value.email || '').trim().toLowerCase()
        if (loginEmail) {
          try {
            await apiLogin(loginEmail, newPassword)
            await authStore.loadUserSessionFromCookie(true)
            toast.success(t('layout.toast.reloginSuccess'))
          } catch {
            // Falls Re-Login fehlschlaegt, bleibt die aktuelle Session bestehen solange der Token gueltig ist.
          }
        }
        resetPasswordForm()
      }

      if (shouldSaveAddress) {
        const departmentId = authStore.activeDepartmentId
        if (!departmentId) {
          toast.error(t('layout.toast.profileSaveFailed'))
          return 'failed'
        }
        const street = addressForm.value.street.trim()
        const postal = addressForm.value.postal_code.trim()
        const city = addressForm.value.city.trim()
        const hasAny = street || postal || city || addressForm.value.street_number.trim() || addressForm.value.canton.trim()
        if (hasAny && (!street || !postal || !city)) {
          toast.error(t('layout.profileModal.addressIncomplete'))
          return 'failed'
        }
        if (hasAny) {
          const payload = {
            department_id: departmentId,
            type: USER_ADDRESS_TYPE,
            name: t('layout.profileModal.addressContactName', {
              name: `${profileForm.value.first_name} ${profileForm.value.last_name}`.trim() || t('layout.profileModal.title'),
            }),
            street,
            street_number: addressForm.value.street_number.trim() || null,
            postal_code: postal,
            city,
            canton: addressForm.value.canton.trim() || null,
            country: 'Schweiz',
            contact_first_name: profileForm.value.first_name.trim() || null,
            contact_last_name: profileForm.value.last_name.trim() || null,
            email: (authStore.profile?.email || profileForm.value.email || '').trim() || null,
            additional_info: profileAddressMarker(profileId),
          }
          if (addressRecordId.value) {
            await updateAddress(addressRecordId.value, payload)
          } else {
            const created = await createAddress(payload)
            addressRecordId.value = created.address.id
          }
          initialAddressSnapshot.value = serializeAddressForm(addressForm.value)
        }
      }

      if (shouldUpdateProfile && shouldChangePassword) {
        toast.success(t('layout.toast.profileAndPasswordSaved'))
      } else if (shouldUpdateProfile || shouldSaveAddress) {
        toast.success(t('layout.toast.profileSaved'))
      }
      initialProfileFormSnapshot.value = serializeProfileForm(profileForm.value)
      return 'saved'
    } catch (error) {
      const err = error as { response?: { data?: { error?: string } } }
      toast.error(err?.response?.data?.error || t('layout.toast.profileSaveFailed'))
      return 'failed'
    } finally {
      savingProfile.value = false
    }
  }

  return {
    profileForm,
    passwordForm,
    addressForm,
    swissCantons: SWISS_CANTONS,
    savingProfile,
    addressAvailable,
    generatedInitialsTemplate,
    profilePreviewInitials,
    profilePreviewAvatarUser,
    hasUnsavedProfileChanges,
    hasAddressChanges,
    hasPasswordInput,
    hasUnsavedChanges,
    passwordInlineError,
    passwordInlineSuccess,
    pendingEmailTarget,
    load,
    save,
    syncPrimaryEmail,
    applyAvatarColor,
    isSelectedAvatarColor,
  }
}
