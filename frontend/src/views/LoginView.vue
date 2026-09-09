<template>
  <div class="login-page" :class="{ 'login-page--register': mode === 'register' }">
    <div class="login-container">
      <div class="login-header">
        <div class="logo">
          <EmcLogoMark size="lg" />
        </div>
        <h1 class="brand-title">eMatChef</h1>
        <p class="brand-subtitle">{{ t('login.brandSubtitle') }}</p>
      </div>

      <ECard class="login-card" variant="elevated">
        <div class="card-header">
          <h2 class="card-title">{{ cardTitle }}</h2>
          <p class="card-subtitle">
            {{ cardSubtitle }}
          </p>
        </div>

        <v-alert
          v-if="inviteFlowActive"
          type="info"
          variant="tonal"
          density="compact"
          class="login-alert mb-3"
        >
          {{ t('login.inviteDetected') }}<span v-if="inviteJoinCode">{{ t('login.inviteCodeInParens', { code: inviteJoinCode }) }}</span>.
          {{ t('login.inviteHint') }}
        </v-alert>

        <v-alert
          v-if="successMessage"
          type="success"
          variant="tonal"
          density="compact"
          class="login-alert mb-3"
        >
          {{ successMessage }}
        </v-alert>

        <form v-if="mode === 'login'" class="login-form" @submit.prevent="handleSubmit">
          <ETextField
            id="email"
            v-model="email"
            type="email"
            :label="t('login.emailLabel')"
            :placeholder="t('login.emailPlaceholder')"
            autocomplete="username"
            :disabled="isLoading"
          />

          <ETextField
            id="password"
            v-model="password"
            class="login-password-field"
            :type="loginPasswordVisible ? 'text' : 'password'"
            :label="t('login.passwordLabel')"
            :placeholder="t('login.passwordPlaceholder')"
            autocomplete="current-password"
            :disabled="isLoading"
          >
            <template #append-inner>
              <div class="password-field-actions">
                <button
                  type="button"
                  class="forgot-inline-btn"
                  :disabled="isLoading"
                  :aria-label="t('login.forgotPassword')"
                  @click.stop.prevent="setMode('forgot')"
                >
                  {{ t('login.forgotPasswordShort') }}
                </button>
                <PasswordRevealToggle :visible="loginPasswordVisible" @toggle="toggleLoginPassword" />
              </div>
            </template>
          </ETextField>

          <v-alert
            v-if="error"
            type="error"
            variant="tonal"
            density="compact"
            class="login-alert mb-3"
          >
            {{ error }}
          </v-alert>
          <div v-if="showResendVerification" class="resend-wrap">
            <EButton variant="secondary" :disabled="isLoading" @click="handleResendVerification">
              {{ t('login.resendVerification') }}
            </EButton>
          </div>

          <EButton
            type="submit"
            variant="primary"
            block
            class="btn-submit"
            :disabled="isLoading"
            :loading="isLoading && !isRedirecting"
          >
            {{
              isRedirecting
                ? t('login.redirecting')
                : isLoading
                  ? t('common.loading')
                  : t('login.loginWithEmatchef')
            }}
          </EButton>

          <div class="login-or-divider" role="separator" :aria-label="t('login.orWith')">
            <span class="login-or-divider__line" />
            <span class="login-or-divider__label">{{ t('login.orWith') }}</span>
            <span class="login-or-divider__line" />
          </div>

          <div class="social-login">
            <button
              v-for="provider in socialProviders"
              :key="provider.id"
              type="button"
              class="social-login-btn"
              :disabled="isLoading"
              :aria-label="t(provider.labelKey)"
              :title="t(provider.labelKey)"
              @click="onSocialLogin(provider.id)"
            >
              <v-icon :icon="provider.icon" size="22" />
            </button>
          </div>

          <div class="form-footer">
            <p class="help-text">
              {{ t('login.noAccount') }}
              <EButton variant="text" size="small" class="link-btn" :disabled="isLoading" @click="setMode('register')">
                {{ t('login.registerNow') }}
              </EButton>
            </p>
          </div>
        </form>

        <form v-else-if="mode === 'forgot'" class="login-form" @submit.prevent="forgotStep === 'request' ? handleForgotRequest() : handleForgotConfirm()">
          <ETextField
            id="forgotEmail"
            v-model="forgotEmail"
            type="email"
            :label="t('login.emailAddressLabel')"
            :placeholder="t('login.emailPlaceholder')"
            autocomplete="email"
            :disabled="isLoading"
          />

          <template v-if="forgotStep === 'confirm'">
            <EOtpInput
              id="resetCode"
              v-model="resetCode"
              :label="t('login.hexCodeLabel')"
              autofocus
              :disabled="isLoading"
            />

            <ETextField
              id="resetPassword"
              v-model="resetPassword"
              type="password"
              :label="t('login.newPasswordLabel')"
              :placeholder="t('login.minPasswordPlaceholder')"
              autocomplete="new-password"
              :disabled="isLoading"
            />

            <ETextField
              id="resetPasswordConfirm"
              v-model="resetPasswordConfirm"
              type="password"
              :label="t('login.confirmNewPasswordLabel')"
              :placeholder="t('login.passwordRepeatPlaceholder')"
              autocomplete="new-password"
              :disabled="isLoading"
              :error-messages="resetPasswordConfirmError"
              :hide-details="!resetPasswordConfirmError"
            />
          </template>

          <v-alert
            v-if="error"
            type="error"
            variant="tonal"
            density="compact"
            class="login-alert mb-3"
          >
            {{ error }}
          </v-alert>

          <EButton
            type="submit"
            variant="primary"
            block
            class="btn-submit"
            :disabled="isLoading"
            :loading="isLoading"
          >
            {{
              forgotStep === 'request'
                ? t('login.sendCode')
                : t('login.resetPassword')
            }}
          </EButton>

          <div class="form-footer">
            <p class="help-text">
              <EButton
                v-if="forgotStep === 'confirm'"
                variant="text"
                size="small"
                class="link-btn"
                :disabled="isLoading"
                @click="forgotStep = 'request'"
              >
                {{ t('login.requestNewCode') }}
              </EButton>
            </p>
            <p v-if="!(openedForgotFromEmailLink && forgotStep === 'confirm')" class="help-text">
              {{ t('login.backToLogin') }}
              <EButton variant="text" size="small" class="link-btn" :disabled="isLoading" @click="setMode('login')">
                {{ t('login.loginButton') }}
              </EButton>
            </p>
          </div>
        </form>

        <form v-else class="login-form" @submit.prevent="handleRegister">
          <ETextField
            id="firstName"
            v-model="firstName"
            :label="t('login.firstNameLabel')"
            :placeholder="t('login.exampleFirstName')"
            :disabled="isLoading"
          />

          <ETextField
            id="lastName"
            v-model="lastName"
            :label="t('login.lastNameLabel')"
            :placeholder="t('login.exampleLastName')"
            :disabled="isLoading"
          />

          <ETextField
            id="nickname"
            v-model="nickname"
            :label="t('login.nicknameLabel')"
            :placeholder="t('login.nicknamePlaceholder')"
            :disabled="isLoading"
          />

          <ESelect
            v-if="!inviteFlowActive && !inviteOrganisationLocked"
            id="requestedOrganisationId"
            v-model="requestedOrganisationId"
            :items="organisationSelectItems"
            :label="t('login.organisationLabel')"
            :disabled="isLoading"
          />
          <ETextField
            v-else-if="inviteFlowActive || inviteOrganisationLocked"
            :label="t('login.organisationLabel')"
            :model-value="inviteOrganisationName || inviteOrganisationId"
            readonly
            disabled
          />
          <p v-if="inviteOrganisationLocked && !inviteFlowActive" class="required-note">{{ t('login.organisationFromInvite') }}</p>

          <RegisterDepartmentPicker
            v-if="!inviteFlowActive"
            :organisation-id="effectiveRequestedOrganisationId"
            :disabled="isLoading"
            :initial-query="registerDepartmentInitialQuery"
            @update:selected="registerSelectedDepartment = $event"
            @update:organisation-id="onRegisterOrganisationFromDepartment"
            @update:manual="registerManualDepartment = $event"
          />
          <p v-else class="required-note">{{ t('login.departmentFromInvite') }}</p>

          <!-- Honeypot: Bots fuellen das oft aus -->
          <div class="form-group" style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden;">
            <label for="website" class="form-label">{{ t('login.websiteLabel') }}</label>
            <input
              id="website"
              v-model="website"
              type="text"
              class="form-input"
              autocomplete="off"
              tabindex="-1"
              :disabled="isLoading"
            />
          </div>

          <ETextField
            id="registerEmail"
            v-model="registerEmail"
            type="email"
            :label="t('login.emailAddressLabel')"
            :placeholder="t('login.emailPlaceholder')"
            autocomplete="email"
            :disabled="isLoading || inviteEmailLocked"
          />

          <ETextField
            id="registerPassword"
            v-model="registerPassword"
            :type="registerPasswordVisible ? 'text' : 'password'"
            :label="t('login.registerPasswordLabel')"
            :placeholder="t('login.minPasswordPlaceholder')"
            autocomplete="new-password"
            :disabled="isLoading"
          >
            <template #append-inner>
              <PasswordRevealToggle
                :visible="registerPasswordVisible"
                @toggle="toggleRegisterPassword"
              />
            </template>
          </ETextField>

          <ETextField
            id="registerPasswordConfirm"
            v-model="registerPasswordConfirm"
            :type="registerPasswordConfirmVisible ? 'text' : 'password'"
            :label="t('login.registerPasswordConfirmLabel')"
            :placeholder="t('login.registerPasswordConfirmPlaceholder')"
            autocomplete="new-password"
            :disabled="isLoading"
          >
            <template #append-inner>
              <PasswordRevealToggle
                :visible="registerPasswordConfirmVisible"
                @toggle="toggleRegisterPasswordConfirm"
              />
            </template>
          </ETextField>

          <ESelect
            id="language"
            v-model="language"
            :items="languageSelectItems"
            :label="t('login.languageLabel')"
            :hint="isRegisterLanguageRestricted ? t('login.languageRestrictedHint') : undefined"
            :persistent-hint="isRegisterLanguageRestricted"
            :disabled="isLoading"
          />

          <ECheckbox
            v-model="acceptTerms"
            class="terms-group"
            :label="t('login.acceptTerms')"
            :disabled="isLoading"
          />

          <div v-if="turnstileSiteKey" class="form-group turnstile-wrap">
            <div ref="turnstileContainerRef" class="turnstile-box" />
          </div>

          <p class="required-note">{{ t('login.requiredFields') }}</p>

          <v-alert
            v-if="error"
            type="error"
            variant="tonal"
            density="compact"
            class="login-alert mb-3"
          >
            {{ error }}
          </v-alert>

          <EButton
            type="submit"
            variant="primary"
            block
            class="btn-submit"
            :disabled="isLoading"
            :loading="isLoading"
          >
            {{ t('login.registerButton') }}
          </EButton>

          <div class="form-footer">
            <p class="help-text">
              {{ t('login.haveAccount') }}
              <EButton variant="text" size="small" class="link-btn" :disabled="isLoading" @click="setMode('login')">
                {{ t('login.loginButton') }}
              </EButton>
            </p>
          </div>
        </form>
      </ECard>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { confirmPasswordReset, googleAuthStartUrl, register as apiRegister, requestPasswordReset, resendVerification } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import { EButton, ECard, ECheckbox, EOtpInput, ESelect, ETextField } from '@/components/form/base'
import { getOrganisations, type Organisation } from '@/api/organisations'
import PasswordRevealToggle from '@/components/auth/PasswordRevealToggle.vue'
import RegisterDepartmentPicker, {
  type RegisterDepartmentManualRequest,
} from '@/components/auth/RegisterDepartmentPicker.vue'
import { useTimedPasswordReveal } from '@/composables/useTimedPasswordReveal'
import type { PublicDepartmentSearchResult } from '@/api/publicDepartments'
import { filterOrganisationsForUserPickers } from '@/utils/organisationUserPicker'
import { setLocale, SUPPORTED_LOCALES } from '@/i18n'
import { consumeDemoLogin } from '@/utils/demoLogins'
import { parseInternalRedirectPath } from '@/utils/appHomeRedirect'

/** Site-Key nur wenn nicht bewusst per VITE_TURNSTILE_SKIP übersprungen (lokal testen) */
const turnstileSiteKey = computed(() => {
  const skip =
    import.meta.env.VITE_TURNSTILE_SKIP === 'true' || import.meta.env.VITE_TURNSTILE_SKIP === '1'
  if (skip) {
    return ''
  }
  return (import.meta.env.VITE_TURNSTILE_SITE_KEY || '').trim()
})

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()

const mode = ref<'login' | 'register' | 'forgot'>('login')
const email = ref('')
const password = ref('')
const {
  visible: loginPasswordVisible,
  toggle: toggleLoginPassword,
  hide: hideLoginPassword,
} = useTimedPasswordReveal()
const {
  visible: registerPasswordVisible,
  toggle: toggleRegisterPassword,
  hide: hideRegisterPassword,
} = useTimedPasswordReveal()
const {
  visible: registerPasswordConfirmVisible,
  toggle: toggleRegisterPasswordConfirm,
  hide: hideRegisterPasswordConfirm,
} = useTimedPasswordReveal()

const firstName = ref('')
const lastName = ref('')
const nickname = ref('')
const registerEmail = ref('')
const registerPassword = ref('')
const registerPasswordConfirm = ref('')
const language = ref('de')
const acceptTerms = ref(false)
const requestedOrganisationId = ref('')
const registerSelectedDepartment = ref<PublicDepartmentSearchResult | null>(null)
const registerManualDepartment = ref<RegisterDepartmentManualRequest | null>(null)
const registerDepartmentInitialQuery = ref('')
const inviteOrganisationId = ref('')
const inviteOrganisationName = ref('')
// Honeypot gegen Bots: unsichtbar, muss leer bleiben
const website = ref('')
const organisations = ref<Organisation[]>([])
const turnstileContainerRef = ref<HTMLElement | null>(null)
const turnstileWidgetId = ref<string | null>(null)
const forgotStep = ref<'request' | 'confirm'>('request')
const openedForgotFromEmailLink = ref(false)
const forgotEmail = ref('')
const resetCode = ref('')
const resetPassword = ref('')
const resetPasswordConfirm = ref('')

const registerLoading = ref(false)
const isRedirecting = ref(false) // Verhindert Doppelklick nach erfolgreichem Login
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const socialProviders = [
  { id: 'google' as const, icon: 'mdi-google', labelKey: 'login.socialGoogle' },
]
const INVITE_REDIRECT_STORAGE_KEY = 'pending_invite_redirect'
const isLoading = computed(() => authStore.loadingUser || registerLoading.value || isRedirecting.value)
const RESEND_VERIFICATION_ERROR_MARKERS = ['bestaetig', 'confirm your email', 'verify your email', 'verif']
const showResendVerification = computed(
  () =>
    mode.value === 'login' &&
    !!error.value &&
    RESEND_VERIFICATION_ERROR_MARKERS.some((m) => error.value!.toLowerCase().includes(m))
)
const inviteRedirect = computed(() => {
  const nested = parseInternalRedirectPath(route.query.redirect)
  if (nested && extractJoinCodeFromPath(nested)) {
    return nested
  }
  const join = queryParamFirst(route.query.join_code).trim()
  if (join) {
    const params = new URLSearchParams()
    params.set('join_code', join.toUpperCase())
    for (const key of ['invite_role', 'invite_email', 'invite_id', 'department_id', 'auto_join'] as const) {
      const value = queryParamFirst(route.query[key]).trim()
      if (value) params.set(key, value)
    }
    return `/pending-assignment?${params.toString()}`
  }
  return nested
})
const inviteFlowActive = computed(() => !!extractJoinCodeFromPath(inviteRedirect.value || ''))
const inviteJoinCode = computed(() => extractJoinCodeFromPath(inviteRedirect.value || ''))
const inviteEmailLocked = computed(() => {
  const fromQuery = queryParamFirst(route.query.email).trim().toLowerCase()
  const fromRedirect = extractInviteEmailFromPath(inviteRedirect.value || '')
  return inviteFlowActive.value && (!!fromQuery || !!fromRedirect)
})
const inviteOrganisationLocked = computed(() => {
  if (mode.value !== 'register' || !inviteFlowActive.value) return false
  const orgId = inviteOrganisationId.value.trim()
  if (!orgId) return false
  return !organisations.value.some((o) => o.id === orgId)
})
const effectiveRequestedOrganisationId = computed(() => {
  const selected = requestedOrganisationId.value.trim()
  if (selected) return selected
  if (mode.value === 'register' && inviteFlowActive.value) {
    return inviteOrganisationId.value.trim()
  }
  return ''
})
const cardTitle = computed(() => {
  if (mode.value === 'register') return t('login.titleRegister')
  if (mode.value === 'forgot') return t('login.titleForgot')
  return t('login.titleLogin')
})
const cardSubtitle = computed(() => {
  if (mode.value === 'register') {
    if (inviteFlowActive.value) {
      return t('login.subtitleRegisterInvite')
    }
    return t('login.subtitleRegister')
  }
  if (mode.value === 'forgot') {
    return forgotStep.value === 'confirm'
      ? t('login.subtitleForgotConfirm')
      : t('login.subtitleForgotRequest')
  }
  return t('login.subtitleLogin')
})

const registerLanguageCodes = computed(() => {
  const orgId = effectiveRequestedOrganisationId.value
  if (!orgId) return [...SUPPORTED_LOCALES]
  const org = organisations.value.find((o) => o.id === orgId)
  const allowed = Array.isArray(org?.allowed_languages)
    ? org.allowed_languages
        .map((lang) => String(lang).toLowerCase().trim())
        .filter((lang): lang is (typeof SUPPORTED_LOCALES)[number] =>
          SUPPORTED_LOCALES.includes(lang as (typeof SUPPORTED_LOCALES)[number])
        )
    : []
  return allowed.length > 0 ? allowed : [...SUPPORTED_LOCALES]
})
const registerLanguageOptions = computed(() =>
  registerLanguageCodes.value.map((code) => ({
    code,
    label: t(`languageNames.${code}`)
  }))
)
const languageSelectItems = computed(() =>
  registerLanguageOptions.value.map((item) => ({
    title: item.label,
    value: item.code,
  }))
)
const organisationSelectItems = computed(() =>
  organisations.value.map((org) => ({
    title: org.name,
    value: org.id,
  }))
)
const isRegisterLanguageRestricted = computed(() => registerLanguageCodes.value.length < SUPPORTED_LOCALES.length)

const resetPasswordConfirmError = computed(() => {
  if (mode.value !== 'forgot' || forgotStep.value !== 'confirm') return undefined
  if (!resetPasswordConfirm.value) return undefined
  if (resetPassword.value === resetPasswordConfirm.value) return undefined
  return t('login.validationPasswordMismatch')
})

function extractJoinCodeFromPath(path: string): string | null {
  if (!path.startsWith('/')) return null
  try {
    const url = new URL(path, window.location.origin)
    if (url.pathname !== '/pending-assignment') return null
    const code = (url.searchParams.get('join_code') || '').trim()
    return code.length > 0 ? code.toUpperCase() : null
  } catch {
    return null
  }
}

function extractInviteEmailFromPath(path: string): string {
  if (!path.startsWith('/')) return ''
  try {
    const url = new URL(path, window.location.origin)
    return (url.searchParams.get('invite_email') || '').trim().toLowerCase()
  } catch {
    return ''
  }
}

function getStoredInviteRedirect(): string | null {
  const stored = localStorage.getItem(INVITE_REDIRECT_STORAGE_KEY)
  if (!stored) return null
  return parseInternalRedirectPath(stored)
}

function rememberInviteRedirect(redirectPath: string | null) {
  const joinCode = redirectPath ? extractJoinCodeFromPath(redirectPath) : null
  if (redirectPath && joinCode) {
    localStorage.setItem(INVITE_REDIRECT_STORAGE_KEY, redirectPath)
    return
  }
  localStorage.removeItem(INVITE_REDIRECT_STORAGE_KEY)
}

watch(
  inviteRedirect,
  (value) => {
    rememberInviteRedirect(value)
  },
  { immediate: true }
)

function queryParamFirst(v: unknown): string {
  if (typeof v === 'string') return v
  if (Array.isArray(v) && typeof v[0] === 'string') return v[0]
  return ''
}

function applyRegisterPrefillFromQuery() {
  const reg = queryParamFirst(route.query.register)
  const wantsRegister = reg === '1' || reg.toLowerCase() === 'true'
  const orgId = queryParamFirst(route.query.org_id).trim()
  const orgName = queryParamFirst(route.query.org_name).trim()
  const deptName = queryParamFirst(route.query.dept_name).trim()
  const emailParam = queryParamFirst(route.query.email).trim().toLowerCase()
  const inviteEmail = extractInviteEmailFromPath(inviteRedirect.value || '')
  const prefillEmail = emailParam || inviteEmail

  if (inviteFlowActive.value || wantsRegister) {
    mode.value = 'register'
  }

  if (prefillEmail) {
    registerEmail.value = prefillEmail
    email.value = prefillEmail
  }

  if (!wantsRegister && !orgId && !deptName && !inviteFlowActive.value) {
    return
  }

  const applyFields = () => {
    if (!wantsRegister && !inviteFlowActive.value) return
    inviteOrganisationId.value = orgId
    inviteOrganisationName.value = orgName
    if (orgId && organisations.value.some((o) => o.id === orgId)) {
      requestedOrganisationId.value = orgId
    }
    if (deptName) {
      registerDepartmentInitialQuery.value = deptName
    }
  }

  if (organisations.value.length > 0) {
    applyFields()
    return
  }
  loadOrganisationsForRegister().then(() => applyFields())
}

function stripForgotQueryFromRoute() {
  if (!route.query.forgot && !route.query.email) return
  const nextQuery = { ...route.query }
  delete nextQuery.forgot
  delete nextQuery.email
  router.replace({ path: route.path, query: nextQuery })
}

function applyForgotPrefillFromQuery() {
  const forgot = queryParamFirst(route.query.forgot)
  const wantsForgot = forgot === '1' || forgot.toLowerCase() === 'true'
  const emailParam = queryParamFirst(route.query.email).trim().toLowerCase()
  if (!wantsForgot) return

  mode.value = 'forgot'
  forgotStep.value = 'confirm'
  openedForgotFromEmailLink.value = true
  if (emailParam) {
    forgotEmail.value = emailParam
  }
  clearMessages()
  stripForgotQueryFromRoute()
}

function applyDemoLoginPrefill() {
  const demo = consumeDemoLogin()
  if (!demo) return
  mode.value = 'login'
  email.value = demo.email
  password.value = demo.password
  clearMessages()
}

onMounted(() => {
  applyRegisterPrefillFromQuery()
  applyForgotPrefillFromQuery()
  applyDemoLoginPrefill()
  void completeGoogleOAuthReturn()
  window.addEventListener('emc-demo-login', applyDemoLoginPrefill)
})

watch(
  () => ({
    register: route.query.register,
    org_id: route.query.org_id,
    org_name: route.query.org_name,
    dept_name: route.query.dept_name,
    email: route.query.email,
    redirect: route.query.redirect,
  }),
  () => applyRegisterPrefillFromQuery(),
  { deep: true }
)

watch(
  () => ({
    forgot: route.query.forgot,
    email: route.query.email,
  }),
  () => applyForgotPrefillFromQuery(),
  { deep: true }
)

watch(
  () => authStore.error,
  (err) => {
    if (mode.value === 'login') {
      error.value = err
    }
  }
)

function clearMessages() {
  error.value = null
  successMessage.value = null
  authStore.clearError()
}

function onSocialLogin(provider: 'google') {
  if (provider !== 'google') return
  const redirect =
    parseInternalRedirectPath(route.query.redirect) || getStoredInviteRedirect()
  isRedirecting.value = true
  window.location.assign(googleAuthStartUrl(redirect))
}

function oauthErrorMessage(reason: string): string {
  const keys: Record<string, string> = {
    not_configured: 'login.oauthNotConfigured',
    denied: 'login.oauthDenied',
    invalid_state: 'login.oauthInvalidState',
    no_email: 'login.oauthNoEmail',
    unverified_email: 'login.oauthUnverifiedEmail',
    inactive: 'login.oauthInactive',
    failed: 'login.oauthFailed',
  }
  return t(keys[reason] || 'login.oauthFailed')
}

async function completeGoogleOAuthReturn() {
  const oauth = typeof route.query.oauth === 'string' ? route.query.oauth : ''
  if (!oauth) return
  if (oauth === 'error') {
    const reason = typeof route.query.reason === 'string' ? route.query.reason : 'failed'
    error.value = oauthErrorMessage(reason)
    return
  }
  if (oauth !== 'ok') return
  isRedirecting.value = true
  const ok = await authStore.loadUserSessionFromCookie(true)
  if (!ok) {
    isRedirecting.value = false
    error.value = t('login.oauthFailed')
    return
  }
  setLocale(authStore.profile?.language || 'de')
  await redirectAfterSuccessfulLogin()
}

async function redirectAfterSuccessfulLogin() {
  const routeRedirect = parseInternalRedirectPath(route.query.redirect)
  const storedInviteRedirect = getStoredInviteRedirect()
  const redirectTarget = routeRedirect || storedInviteRedirect
  if (redirectTarget) {
    localStorage.removeItem(INVITE_REDIRECT_STORAGE_KEY)
    await router.replace(redirectTarget)
    return
  }

  if (authStore.userRoles.includes('ROLE_SUPERADMIN')) {
    await router.replace('/dashboard')
    return
  }

  if (authStore.activeDepartmentId) {
    await router.replace(`/${authStore.activeDepartmentId}`)
    return
  }

  await router.replace('/pending-assignment')
}

function resetRegisterForm() {
  firstName.value = ''
  lastName.value = ''
  nickname.value = ''
  registerEmail.value = ''
  registerPassword.value = ''
  registerPasswordConfirm.value = ''
  language.value = 'de'
  acceptTerms.value = false
  requestedOrganisationId.value = ''
  registerSelectedDepartment.value = null
  registerManualDepartment.value = null
  registerDepartmentInitialQuery.value = ''
  inviteOrganisationId.value = ''
  inviteOrganisationName.value = ''
  website.value = ''
}

function onRegisterOrganisationFromDepartment(orgId: string) {
  requestedOrganisationId.value = orgId
}

async function loadOrganisationsForRegister() {
  try {
    organisations.value = filterOrganisationsForUserPickers(await getOrganisations())
  } catch (e) {
    console.error(e)
    organisations.value = []
  }
}

const TURNSTILE_SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js'

function loadTurnstileScript(): Promise<void> {
  if (window.turnstile) {
    return Promise.resolve()
  }
  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${TURNSTILE_SCRIPT_SRC}"]`)
    if (existing) {
      existing.addEventListener('load', () => resolve(), { once: true })
      existing.addEventListener('error', () => reject(new Error('Turnstile')), { once: true })
      return
    }
    const s = document.createElement('script')
    s.src = TURNSTILE_SCRIPT_SRC
    s.async = true
    s.defer = true
    s.onload = () => resolve()
    s.onerror = () => reject(new Error('Turnstile script failed'))
    document.head.appendChild(s)
  })
}

function cleanupTurnstile(): void {
  if (turnstileWidgetId.value && window.turnstile?.remove) {
    try {
      window.turnstile.remove(turnstileWidgetId.value)
    } catch {
      // ignore
    }
  }
  turnstileWidgetId.value = null
  if (turnstileContainerRef.value) {
    turnstileContainerRef.value.innerHTML = ''
  }
}

function resetTurnstileWidget(): void {
  if (turnstileWidgetId.value && window.turnstile?.reset) {
    window.turnstile.reset(turnstileWidgetId.value)
  }
}

async function initTurnstile(): Promise<void> {
  if (!turnstileSiteKey.value) {
    return
  }
  await nextTick()
  if (!turnstileContainerRef.value) {
    return
  }
  cleanupTurnstile()
  try {
    await loadTurnstileScript()
  } catch (e) {
    console.error(e)
    return
  }
  await nextTick()
  if (!turnstileContainerRef.value || !window.turnstile) {
    return
  }
  turnstileWidgetId.value = window.turnstile.render(turnstileContainerRef.value, {
    sitekey: turnstileSiteKey.value,
    theme: 'light'
  })
}

watch(mode, async (m, prev) => {
  if (prev === 'register' && m !== 'register') {
    cleanupTurnstile()
  }
  if (m === 'register' && turnstileSiteKey.value) {
    await initTurnstile()
  }
})

onUnmounted(() => {
  window.removeEventListener('emc-demo-login', applyDemoLoginPrefill)
  cleanupTurnstile()
})

function resetForgotForm() {
  forgotStep.value = 'request'
  openedForgotFromEmailLink.value = false
  forgotEmail.value = ''
  resetCode.value = ''
  resetPassword.value = ''
  resetPasswordConfirm.value = ''
}

function hideRevealedPasswords() {
  hideLoginPassword()
  hideRegisterPassword()
  hideRegisterPasswordConfirm()
}

function setMode(nextMode: 'login' | 'register' | 'forgot') {
  const previousMode = mode.value
  mode.value = nextMode
  hideRevealedPasswords()
  if (previousMode === 'forgot' && nextMode !== 'forgot') {
    resetForgotForm()
  }
  if (nextMode === 'register') {
    language.value = 'de'
    loadOrganisationsForRegister().then(() => {
      applyRegisterPrefillFromQuery()
    })
  }
  clearMessages()
}

async function handleSubmit() {
  clearMessages()

  if (!email.value || !password.value) {
    error.value = t('login.validationEmailPassword')
    return
  }

  const success = await authStore.login(email.value.trim(), password.value)
  if (!success) return
  setLocale(authStore.profile?.language || 'de')

  isRedirecting.value = true
  await redirectAfterSuccessfulLogin()
}

async function handleRegister() {
  clearMessages()

  if (!firstName.value.trim() || !lastName.value.trim()) {
    error.value = t('login.validationNameRequired')
    return
  }

  if (!inviteFlowActive.value) {
    if (!effectiveRequestedOrganisationId.value) {
      error.value = t('login.validationOrganisationRequired')
      return
    }
    if (!registerSelectedDepartment.value && !registerManualDepartment.value) {
      error.value = t('login.validationDepartmentRequired')
      return
    }
  }

  if (!registerEmail.value.trim() || !registerPassword.value) {
    error.value = t('login.validationEmailPassword')
    return
  }

  if (registerPassword.value.length < 8) {
    error.value = t('login.validationPasswordMin')
    return
  }

  if (registerPassword.value !== registerPasswordConfirm.value) {
    error.value = t('login.validationPasswordMismatch')
    return
  }

  if (!acceptTerms.value) {
    error.value = t('login.validationAcceptTerms')
    return
  }

  let turnstileToken: string | undefined
  if (turnstileSiteKey.value) {
    const wid = turnstileWidgetId.value
    const captchaToken = wid && window.turnstile ? window.turnstile.getResponse(wid) : undefined
    if (!captchaToken) {
      error.value = t('login.validationCaptcha')
      return
    }
    turnstileToken = captchaToken
  }

  try {
    registerLoading.value = true
    const response = await apiRegister({
      firstName: firstName.value.trim(),
      lastName: lastName.value.trim(),
      nickname: nickname.value.trim() || undefined,
      email: registerEmail.value.trim(),
      password: registerPassword.value,
      language: language.value,
      acceptTerms: acceptTerms.value,
      requestedOrganisationId: effectiveRequestedOrganisationId.value,
      requestedDepartmentName: (
        registerSelectedDepartment.value?.name || registerManualDepartment.value?.departmentName || ''
      ).trim(),
      requestedDepartmentId: registerSelectedDepartment.value?.id,
      requestedParentDepartmentId: registerManualDepartment.value?.parentDepartmentId || undefined,
      requestedParentDepartmentName: registerManualDepartment.value?.parentDepartmentName || undefined,
      website: website.value,
      turnstileToken,
      inviteJoinCode: inviteJoinCode.value || undefined,
    })

    if (response.invite_ready && inviteFlowActive.value) {
      const success = await authStore.login(registerEmail.value.trim(), registerPassword.value)
      if (success) {
        isRedirecting.value = true
        await redirectAfterSuccessfulLogin()
        return
      }
    }

    successMessage.value = response.message || t('login.registerSuccessFallback')
    mode.value = 'login'
    email.value = registerEmail.value.trim()
    password.value = ''
    resetRegisterForm()
  } catch (err: any) {
    if (err?.response?.status === 409 && inviteFlowActive.value) {
      mode.value = 'login'
      email.value = registerEmail.value.trim()
      error.value = t('login.inviteEmailAlreadyRegistered')
      return
    }
    error.value = err?.response?.data?.error || t('login.registerFailedFallback')
    resetTurnstileWidget()
  } finally {
    registerLoading.value = false
  }
}

async function handleResendVerification() {
  if (!email.value.trim()) {
    error.value = t('login.validationEmailRequiredForResend')
    return
  }
  try {
    const result = await resendVerification(email.value.trim())
    successMessage.value = result.message || t('login.resendSuccessFallback')
    error.value = null
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('login.resendFailedFallback')
  }
}

async function handleForgotRequest() {
  clearMessages()

  const normalizedEmail = forgotEmail.value.trim().toLowerCase()
  if (!normalizedEmail) {
    error.value = t('login.validationEmailRequired')
    return
  }

  try {
    registerLoading.value = true
    const result = await requestPasswordReset(normalizedEmail)
    resetForgotForm()
    mode.value = 'login'
    email.value = normalizedEmail
    successMessage.value = result.message
    stripForgotQueryFromRoute()
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('login.forgotRequestFailedFallback')
  } finally {
    registerLoading.value = false
  }
}

async function handleForgotConfirm() {
  clearMessages()

  const normalizedEmail = forgotEmail.value.trim().toLowerCase()
  const normalizedCode = resetCode.value.trim().toUpperCase()
  if (!normalizedEmail) {
    error.value = t('login.validationEmailRequired')
    return
  }
  if (!/^[0-9A-F]{6}$/.test(normalizedCode)) {
    error.value = t('login.validationHexCode')
    return
  }
  if (resetPassword.value.length < 8) {
    error.value = t('login.validationPasswordMin')
    return
  }
  if (resetPassword.value !== resetPasswordConfirm.value) {
    error.value = t('login.validationPasswordMismatch')
    return
  }

  try {
    registerLoading.value = true
    const result = await confirmPasswordReset(normalizedEmail, normalizedCode, resetPassword.value)
    successMessage.value = result.message || t('login.forgotConfirmSuccessFallback')
    email.value = normalizedEmail
    password.value = ''
    resetForgotForm()
    mode.value = 'login'
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('login.forgotConfirmFailedFallback')
  } finally {
    registerLoading.value = false
  }
}

watch([email, password], () => {
  if (mode.value === 'login' && error.value) {
    error.value = null
    authStore.clearError()
  }
})

watch([firstName, lastName, nickname, registerEmail, registerPassword, registerPasswordConfirm, language, acceptTerms, requestedOrganisationId, registerSelectedDepartment, registerManualDepartment], () => {
  if (mode.value === 'register' && error.value) {
    error.value = null
  }
})

watch([forgotEmail, resetCode, resetPassword, resetPasswordConfirm], () => {
  if (mode.value === 'forgot' && error.value) {
    error.value = null
  }
})

watch(
  registerLanguageCodes,
  (codes) => {
    if (codes.length === 0) return
    if (!codes.includes(language.value as (typeof SUPPORTED_LOCALES)[number])) {
      language.value = codes[0]
    }
  },
  { immediate: true }
)

</script>

<style scoped>
.login-page {
  min-height: calc(100dvh - 36px);
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(160deg, #f1f5f9 0%, #e2e8f0 100%);
  padding: 24px;
}

.login-page--register {
  align-items: flex-start;
  min-height: calc(100dvh - 36px);
  height: auto;
  overflow: visible;
  padding-top: 16px;
  padding-bottom: 24px;
}

.login-container {
  width: 100%;
  max-width: 560px;
}

.login-header {
  text-align: center;
  margin-bottom: 28px;
}

.logo {
  display: flex;
  justify-content: center;
  margin-bottom: 14px;
}

.brand-title {
  font-size: 30px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 8px;
}

.brand-subtitle {
  color: #64748b;
  font-size: 15px;
  margin: 0;
}

.login-card {
  border-radius: 16px;
  padding: 32px;
}

.login-card.e-card {
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.28);
}

.card-header {
  text-align: center;
  margin-bottom: 24px;
}

.card-title {
  font-size: 42px;
  line-height: 1.1;
  font-weight: 500;
  color: #111827;
  margin: 0 0 8px;
}

.card-subtitle {
  color: #6b7280;
  font-size: 14px;
  margin: 0;
}

.login-form {
  margin-bottom: 6px;
}

.login-form :deep(.e-form-field) {
  margin-bottom: 14px;
}

.login-form :deep(.e-button.btn-submit) {
  margin-top: 8px;
}

.login-alert {
  font-size: 14px;
}

.link-btn {
  text-transform: none;
  letter-spacing: normal;
  font-size: inherit;
  min-width: 0;
  padding: 0 0 0 4px;
  height: auto;
  vertical-align: baseline;
}

.link-btn :deep(.v-btn__content) {
  text-decoration: underline;
}

.required-note {
  color: #b91c1c;
  font-size: 14px;
  margin: 0 0 12px;
}

.turnstile-wrap {
  margin-top: 4px;
}

.turnstile-box {
  min-height: 65px;
}

.terms-group {
  margin-top: 6px;
}

.resend-wrap {
  margin-bottom: 10px;
}

.password-field-actions {
  display: inline-flex;
  align-items: center;
  gap: 2px;
}

.forgot-inline-btn {
  margin: 0;
  padding: 0 4px;
  border: 0;
  background: transparent;
  color: #2563eb;
  cursor: pointer;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.2;
  text-decoration: underline;
  text-underline-offset: 2px;
  white-space: nowrap;
}

.forgot-inline-btn:hover,
.forgot-inline-btn:focus-visible {
  color: #1d4ed8;
}

.forgot-inline-btn:focus-visible {
  outline: 2px solid var(--color-primary, #059669);
  outline-offset: 2px;
  border-radius: 4px;
}

.forgot-inline-btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.login-form :deep(.login-password-field.e-form-field:has(.password-reveal-toggle) .v-field__input) {
  padding-inline-end: 118px;
}

.btn-submit {
  font-size: 18px;
}

.login-or-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 18px 0 14px;
}

.login-or-divider__line {
  flex: 1;
  height: 1px;
  background: #d1d5db;
}

.login-or-divider__label {
  flex: none;
  color: #6b7280;
  font-size: 13px;
  line-height: 1;
}

.social-login {
  display: flex;
  flex-direction: row;
  justify-content: center;
  align-items: center;
  gap: 10px;
}

.social-login-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  margin: 0;
  padding: 0;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  background: #fff;
  color: #111827;
  cursor: pointer;
}

.social-login-btn:hover:not(:disabled),
.social-login-btn:focus-visible {
  border-color: #9ca3af;
  background: #f3f4f6;
}

.social-login-btn:focus-visible {
  outline: 2px solid var(--color-primary, #059669);
  outline-offset: 2px;
}

.social-login-btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.form-footer {
  text-align: center;
  margin-top: 8px;
}

.help-text {
  color: #4b5563;
  font-size: 17px;
}

@media (max-width: 640px) {
  .login-card {
    padding: 22px;
  }

  .card-title {
    font-size: 36px;
  }
}
</style>
