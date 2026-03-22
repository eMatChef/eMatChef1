<template>
  <div class="login-page">
    <div class="login-container">
      <div class="login-header">
        <div class="logo">
          <EmcLogoMark size="lg" />
        </div>
        <h1 class="brand-title">eMatChef</h1>
        <p class="brand-subtitle">Materialverwaltung & Ausleihe</p>
      </div>

      <div class="login-card">
        <div class="card-header">
          <h2 class="card-title">{{ cardTitle }}</h2>
          <p class="card-subtitle">
            {{ cardSubtitle }}
          </p>
        </div>

        <div v-if="inviteFlowActive" class="invite-message">
          Einladung erkannt
          <span v-if="inviteJoinCode"> (Code: {{ inviteJoinCode }})</span>.
          Bitte anmelden oder Konto erstellen. Danach wirst du direkt zur Join-Anfrage gefuehrt.
        </div>

        <div v-if="successMessage" class="success-message">
          {{ successMessage }}
        </div>

        <form v-if="mode === 'login'" class="login-form" @submit.prevent="handleSubmit">
          <div class="form-group">
            <label for="email" class="form-label">E-Mail *</label>
            <input
              id="email"
              v-model="email"
              type="email"
              class="form-input"
              placeholder="ihre.email@beispiel.de"
              required
              autocomplete="username"
              :disabled="isLoading"
            />
          </div>

          <div class="form-group">
            <label for="password" class="form-label">Passwort *</label>
            <input
              id="password"
              v-model="password"
              type="password"
              class="form-input"
              placeholder="Ihr Passwort"
              required
              autocomplete="current-password"
              :disabled="isLoading"
            />
          </div>

          <div class="link-row">
            <button type="button" class="inline-link" :disabled="isLoading" @click="setMode('forgot')">
              Passwort vergessen?
            </button>
          </div>

          <div v-if="error" class="error-message">{{ error }}</div>
          <div v-if="showResendVerification" class="resend-wrap">
            <button type="button" class="btn-secondary" :disabled="isLoading" @click="handleResendVerification">
              Verifikationsmail erneut senden
            </button>
          </div>

          <button type="submit" class="btn-primary btn-submit" :disabled="isLoading">
            {{ isRedirecting ? 'Weiterleitung...' : isLoading ? 'Bitte warten...' : 'Anmelden' }}
          </button>

          <div class="form-footer">
            <p class="help-text">
              Hast du noch keinen Account?
              <button type="button" class="inline-link" :disabled="isLoading" @click="setMode('register')">
                Jetzt registrieren
              </button>
            </p>
          </div>
        </form>

        <form v-else-if="mode === 'forgot'" class="login-form" @submit.prevent="forgotStep === 'request' ? handleForgotRequest() : handleForgotConfirm()">
          <div class="form-group">
            <label for="forgotEmail" class="form-label">E-Mail-Adresse *</label>
            <input
              id="forgotEmail"
              v-model="forgotEmail"
              type="email"
              class="form-input"
              placeholder="ihre.email@beispiel.de"
              required
              autocomplete="email"
              :disabled="isLoading"
            />
          </div>

          <template v-if="forgotStep === 'confirm'">
            <div class="form-group">
              <label for="resetCode" class="form-label">6-stelliger HEX-Code *</label>
              <input
                id="resetCode"
                v-model="resetCode"
                type="text"
                class="form-input"
                placeholder="z.B. 1A2B3C"
                maxlength="6"
                required
                :disabled="isLoading"
              />
            </div>

            <div class="form-group">
              <label for="resetPassword" class="form-label">Neues Passwort *</label>
              <input
                id="resetPassword"
                v-model="resetPassword"
                type="password"
                class="form-input"
                placeholder="Mindestens 8 Zeichen"
                required
                autocomplete="new-password"
                :disabled="isLoading"
              />
            </div>

            <div class="form-group">
              <label for="resetPasswordConfirm" class="form-label">Neues Passwort bestaetigen *</label>
              <input
                id="resetPasswordConfirm"
                v-model="resetPasswordConfirm"
                type="password"
                class="form-input"
                placeholder="Passwort erneut eingeben"
                required
                autocomplete="new-password"
                :disabled="isLoading"
              />
            </div>
          </template>

          <div v-if="error" class="error-message">{{ error }}</div>

          <button type="submit" class="btn-primary btn-submit" :disabled="isLoading">
            {{ isLoading ? 'Bitte warten...' : forgotStep === 'request' ? 'Code senden' : 'Passwort zuruecksetzen' }}
          </button>

          <div class="form-footer">
            <p class="help-text">
              <button
                v-if="forgotStep === 'confirm'"
                type="button"
                class="inline-link"
                :disabled="isLoading"
                @click="forgotStep = 'request'"
              >
                Neuen Code anfordern
              </button>
            </p>
            <p class="help-text">
              Zurueck zum Login?
              <button type="button" class="inline-link" :disabled="isLoading" @click="setMode('login')">
                Anmelden
              </button>
            </p>
          </div>
        </form>

        <form v-else class="login-form" @submit.prevent="handleRegister">
          <div class="form-group">
            <label for="firstName" class="form-label">Vorname *</label>
            <input
              id="firstName"
              v-model="firstName"
              type="text"
              class="form-input"
              placeholder="Max"
              required
              :disabled="isLoading"
            />
          </div>

          <div class="form-group">
            <label for="lastName" class="form-label">Nachname *</label>
            <input
              id="lastName"
              v-model="lastName"
              type="text"
              class="form-input"
              placeholder="Muster"
              required
              :disabled="isLoading"
            />
          </div>

          <div class="form-group">
            <label for="nickname" class="form-label">Spitzname</label>
            <input
              id="nickname"
              v-model="nickname"
              type="text"
              class="form-input"
              placeholder="Optional"
              :disabled="isLoading"
            />
          </div>

          <div class="form-group">
            <label for="registerEmail" class="form-label">E-Mail-Adresse *</label>
            <input
              id="registerEmail"
              v-model="registerEmail"
              type="email"
              class="form-input"
              placeholder="ihre.email@beispiel.de"
              required
              autocomplete="email"
              :disabled="isLoading"
            />
          </div>

          <div class="form-group">
            <label for="registerPassword" class="form-label">Passwort *</label>
            <input
              id="registerPassword"
              v-model="registerPassword"
              type="password"
              class="form-input"
              placeholder="Mindestens 8 Zeichen"
              required
              autocomplete="new-password"
              :disabled="isLoading"
            />
          </div>

          <div class="form-group">
            <label for="registerPasswordConfirm" class="form-label">Passwort erneut eingeben *</label>
            <input
              id="registerPasswordConfirm"
              v-model="registerPasswordConfirm"
              type="password"
              class="form-input"
              placeholder="Passwort bestaetigen"
              required
              autocomplete="new-password"
              :disabled="isLoading"
            />
          </div>

          <div class="form-group">
            <label for="language" class="form-label">Sprache *</label>
            <select id="language" v-model="language" class="form-input" :disabled="isLoading">
              <option value="de">Deutsch</option>
              <option value="fr">Franzoesisch</option>
              <option value="it">Italienisch</option>
              <option value="en">Englisch</option>
            </select>
          </div>

          <div class="form-group terms-group">
            <label class="checkbox-label">
              <input v-model="acceptTerms" type="checkbox" required :disabled="isLoading" />
              <span>Nutzungsbedingungen akzeptieren *</span>
            </label>
          </div>

          <p class="required-note">* Pflichtfelder</p>

          <div v-if="error" class="error-message">{{ error }}</div>

          <button type="submit" class="btn-primary btn-submit" :disabled="isLoading">
            {{ isLoading ? 'Bitte warten...' : 'Registrieren' }}
          </button>

          <div class="form-footer">
            <p class="help-text">
              Hast du bereits ein Konto?
              <button type="button" class="inline-link" :disabled="isLoading" @click="setMode('login')">
                Anmelden
              </button>
            </p>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { confirmPasswordReset, register as apiRegister, requestPasswordReset, resendVerification } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const mode = ref<'login' | 'register' | 'forgot'>('login')
const email = ref('')
const password = ref('')

const firstName = ref('')
const lastName = ref('')
const nickname = ref('')
const registerEmail = ref('')
const registerPassword = ref('')
const registerPasswordConfirm = ref('')
const language = ref('de')
const acceptTerms = ref(false)
const forgotStep = ref<'request' | 'confirm'>('request')
const forgotEmail = ref('')
const resetCode = ref('')
const resetPassword = ref('')
const resetPasswordConfirm = ref('')

const registerLoading = ref(false)
const isRedirecting = ref(false) // Verhindert Doppelklick nach erfolgreichem Login
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const INVITE_REDIRECT_STORAGE_KEY = 'pending_invite_redirect'
const isLoading = computed(() => authStore.loadingUser || registerLoading.value || isRedirecting.value)
const showResendVerification = computed(() =>
  mode.value === 'login' && !!error.value && error.value.toLowerCase().includes('bestaetige')
)
const inviteRedirect = computed(() => parseInternalRedirect(route.query.redirect))
const inviteFlowActive = computed(() => !!extractJoinCodeFromPath(inviteRedirect.value || ''))
const inviteJoinCode = computed(() => extractJoinCodeFromPath(inviteRedirect.value || ''))
const cardTitle = computed(() => {
  if (mode.value === 'register') return 'Ein Konto erstellen'
  if (mode.value === 'forgot') return 'Passwort vergessen'
  return 'Anmelden'
})
const cardSubtitle = computed(() => {
  if (mode.value === 'register') {
    return 'Registrieren Sie sich. Die Zuordnung zu einer Abteilung erfolgt spaeter durch Admins.'
  }
  if (mode.value === 'forgot') {
    return 'Fordern Sie einen 6-stelligen HEX-Code per E-Mail an und setzen Sie Ihr Passwort sicher zurueck.'
  }
  return 'Bitte melden Sie sich mit Ihrer E-Mail-Adresse an.'
})

function parseInternalRedirect(rawRedirect: unknown): string | null {
  if (typeof rawRedirect !== 'string') return null
  const trimmed = rawRedirect.trim()
  if (!trimmed || !trimmed.startsWith('/')) return null
  if (trimmed.startsWith('//')) return null
  return trimmed
}

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

function getStoredInviteRedirect(): string | null {
  const stored = localStorage.getItem(INVITE_REDIRECT_STORAGE_KEY)
  if (!stored) return null
  return parseInternalRedirect(stored)
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
  () => route.query.redirect,
  (value) => {
    rememberInviteRedirect(parseInternalRedirect(value))
  },
  { immediate: true }
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

function resetRegisterForm() {
  firstName.value = ''
  lastName.value = ''
  nickname.value = ''
  registerEmail.value = ''
  registerPassword.value = ''
  registerPasswordConfirm.value = ''
  language.value = 'de'
  acceptTerms.value = false
}

function resetForgotForm() {
  forgotStep.value = 'request'
  forgotEmail.value = ''
  resetCode.value = ''
  resetPassword.value = ''
  resetPasswordConfirm.value = ''
}

function setMode(nextMode: 'login' | 'register' | 'forgot') {
  const previousMode = mode.value
  mode.value = nextMode
  if (previousMode === 'forgot' && nextMode !== 'forgot') {
    resetForgotForm()
  }
  clearMessages()
}

async function handleSubmit() {
  clearMessages()

  if (!email.value || !password.value) {
    error.value = 'Bitte geben Sie E-Mail und Passwort ein'
    return
  }

  const success = await authStore.login(email.value.trim(), password.value)
  if (!success) return

  isRedirecting.value = true // Button bleibt deaktiviert bis Weiterleitung
  const routeRedirect = parseInternalRedirect(route.query.redirect)
  const storedInviteRedirect = getStoredInviteRedirect()
  const redirectTarget = routeRedirect || storedInviteRedirect
  if (redirectTarget) {
    localStorage.removeItem(INVITE_REDIRECT_STORAGE_KEY)
    router.replace(redirectTarget)
    return
  }

  if (authStore.userRoles.includes('ROLE_SUPERADMIN')) {
    const primaryDept =
      authStore.departments.find(d => d.is_primary) || authStore.departments[0]
    if (primaryDept?.department_id) {
      router.replace(`/${primaryDept.department_id}`)
      return
    }
    router.replace('/dashboard')
    return
  }

  if (authStore.activeDepartmentId) {
    router.replace(`/${authStore.activeDepartmentId}`)
    return
  }

  router.replace('/pending-assignment')
}

async function handleRegister() {
  clearMessages()

  if (!firstName.value.trim() || !lastName.value.trim()) {
    error.value = 'Vorname und Nachname sind Pflichtfelder'
    return
  }

  if (!registerEmail.value.trim() || !registerPassword.value) {
    error.value = 'Bitte geben Sie E-Mail und Passwort ein'
    return
  }

  if (registerPassword.value.length < 8) {
    error.value = 'Das Passwort muss mindestens 8 Zeichen lang sein'
    return
  }

  if (registerPassword.value !== registerPasswordConfirm.value) {
    error.value = 'Die Passwoerter stimmen nicht ueberein'
    return
  }

  if (!acceptTerms.value) {
    error.value = 'Bitte akzeptieren Sie die Nutzungsbedingungen'
    return
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
      acceptTerms: acceptTerms.value
    })

    successMessage.value = response.message || 'Konto erfolgreich erstellt'
    mode.value = 'login'
    email.value = registerEmail.value.trim()
    password.value = ''
    resetRegisterForm()
  } catch (err: any) {
    error.value = err?.response?.data?.error || 'Registrierung fehlgeschlagen'
  } finally {
    registerLoading.value = false
  }
}

async function handleResendVerification() {
  if (!email.value.trim()) {
    error.value = 'Bitte E-Mail eingeben, um die Verifikationsmail erneut zu senden'
    return
  }
  try {
    const result = await resendVerification(email.value.trim())
    successMessage.value = result.message || 'Verifikationsmail wurde erneut gesendet.'
    error.value = null
  } catch (err: any) {
    error.value = err?.response?.data?.error || 'Verifikationsmail konnte nicht gesendet werden'
  }
}

async function handleForgotRequest() {
  clearMessages()

  const normalizedEmail = forgotEmail.value.trim().toLowerCase()
  if (!normalizedEmail) {
    error.value = 'Bitte E-Mail eingeben'
    return
  }

  try {
    registerLoading.value = true
    const result = await requestPasswordReset(normalizedEmail)
    successMessage.value = result.message
    forgotEmail.value = normalizedEmail
    forgotStep.value = 'confirm'
  } catch (err: any) {
    error.value = err?.response?.data?.error || 'Reset-Code konnte nicht angefordert werden'
  } finally {
    registerLoading.value = false
  }
}

async function handleForgotConfirm() {
  clearMessages()

  const normalizedEmail = forgotEmail.value.trim().toLowerCase()
  const normalizedCode = resetCode.value.trim().toUpperCase()
  if (!normalizedEmail) {
    error.value = 'Bitte E-Mail eingeben'
    return
  }
  if (!/^[0-9A-F]{6}$/.test(normalizedCode)) {
    error.value = 'Code muss 6-stellig und hexadezimal sein'
    return
  }
  if (resetPassword.value.length < 8) {
    error.value = 'Das Passwort muss mindestens 8 Zeichen lang sein'
    return
  }
  if (resetPassword.value !== resetPasswordConfirm.value) {
    error.value = 'Die Passwoerter stimmen nicht ueberein'
    return
  }

  try {
    registerLoading.value = true
    const result = await confirmPasswordReset(normalizedEmail, normalizedCode, resetPassword.value)
    successMessage.value = result.message || 'Passwort wurde erfolgreich zurueckgesetzt'
    email.value = normalizedEmail
    password.value = ''
    resetForgotForm()
    mode.value = 'login'
  } catch (err: any) {
    error.value = err?.response?.data?.error || 'Passwort konnte nicht zurueckgesetzt werden'
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

watch([firstName, lastName, nickname, registerEmail, registerPassword, registerPasswordConfirm, language, acceptTerms], () => {
  if (mode.value === 'register' && error.value) {
    error.value = null
  }
})

watch([forgotEmail, resetCode, resetPassword, resetPasswordConfirm], () => {
  if (mode.value === 'forgot' && error.value) {
    error.value = null
  }
})
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  padding: 24px;
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
  color: #fff;
  margin: 0 0 8px;
}

.brand-subtitle {
  color: rgba(255, 255, 255, 0.95);
  font-size: 15px;
  margin: 0;
}

.login-card {
  background: #fff;
  border-radius: 16px;
  padding: 32px;
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

.form-group {
  margin-bottom: 14px;
}

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 6px;
}

.form-input {
  font-size: 16px;
}

.error-message {
  background: #fee2e2;
  color: #b91c1c;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 14px;
  margin-bottom: 14px;
}

.success-message {
  background: #dcfce7;
  color: #166534;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 14px;
  margin-bottom: 14px;
}

.invite-message {
  background: #eff6ff;
  color: #1d4ed8;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 14px;
  margin-bottom: 14px;
}

.btn-secondary {
  width: 100%;
  justify-content: center;
}

.resend-wrap {
  margin-bottom: 10px;
}

.link-row {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 12px;
}

.btn-submit {
  width: 100%;
  justify-content: center;
  font-size: 18px;
  padding: 13px;
}

.form-footer {
  text-align: center;
  margin-top: 8px;
}

.help-text {
  color: #4b5563;
  font-size: 17px;
}

.inline-link {
  border: none;
  background: transparent;
  color: #0b7eea;
  text-decoration: underline;
  font-size: 17px;
  cursor: pointer;
  padding: 0 0 0 4px;
}

.required-note {
  color: #b91c1c;
  font-size: 14px;
  margin: 0 0 12px;
}

.terms-group {
  margin-top: 6px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #374151;
  font-size: 16px;
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
