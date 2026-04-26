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

          <div v-if="!inviteOrganisationLocked" class="form-group">
            <label for="requestedOrganisationId" class="form-label">Organisation *</label>
            <select
              id="requestedOrganisationId"
              v-model="requestedOrganisationId"
              class="form-input"
              required
              :disabled="isLoading"
            >
              <option value="" disabled hidden>&nbsp;</option>
              <option v-for="org in organisations" :key="org.id" :value="org.id">{{ org.name }}</option>
            </select>
          </div>
          <div v-else class="form-group">
            <label class="form-label">Organisation *</label>
            <input
              type="text"
              class="form-input"
              :value="inviteOrganisationName || inviteOrganisationId"
              disabled
            />
            <p class="required-note">Aus Einladungslink übernommen.</p>
          </div>

          <div class="form-group">
            <label for="requestedDepartmentName" class="form-label">Deine Abteilung *</label>
            <input
              id="requestedDepartmentName"
              v-model="requestedDepartmentName"
              type="text"
              class="form-input"
              placeholder="z. B. Pfadi Musterstadt"
              required
              :disabled="isLoading"
            />
          </div>

          <!-- Honeypot: Bots fuellen das oft aus -->
          <div class="form-group" style="position:absolute; left:-10000px; top:auto; width:1px; height:1px; overflow:hidden;">
            <label for="website" class="form-label">Website</label>
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

          <div v-if="turnstileSiteKey" class="form-group turnstile-wrap">
            <div ref="turnstileContainerRef" class="turnstile-box" />
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
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { confirmPasswordReset, register as apiRegister, requestPasswordReset, resendVerification } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import { getOrganisations, type Organisation } from '@/api/organisations'
import { filterOrganisationsForUserPickers } from '@/utils/organisationUserPicker'

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
const requestedOrganisationId = ref('')
const requestedDepartmentName = ref('')
const inviteOrganisationId = ref('')
const inviteOrganisationName = ref('')
// Honeypot gegen Bots: unsichtbar, muss leer bleiben
const website = ref('')
const organisations = ref<Organisation[]>([])
const turnstileContainerRef = ref<HTMLElement | null>(null)
const turnstileWidgetId = ref<string | null>(null)
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
  if (mode.value === 'register') return 'Ein Konto erstellen'
  if (mode.value === 'forgot') return 'Passwort vergessen'
  return 'Anmelden'
})
const cardSubtitle = computed(() => {
  if (mode.value === 'register') {
    if (inviteFlowActive.value) {
      return 'Registrieren Sie sich. Organisation und Abteilung sind aus der Einladung vorausgefuellt; nach der Registrierung geht es mit dem Join-Code weiter.'
    }
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

  if (!wantsRegister && !orgId && !deptName) {
    return
  }

  if (wantsRegister) {
    mode.value = 'register'
  }

  const applyFields = () => {
    if (!wantsRegister) return
    inviteOrganisationId.value = orgId
    inviteOrganisationName.value = orgName
    if (orgId && organisations.value.some((o) => o.id === orgId)) {
      requestedOrganisationId.value = orgId
    }
    if (deptName) {
      requestedDepartmentName.value = deptName
    }
  }

  if (organisations.value.length > 0) {
    applyFields()
    return
  }
  loadOrganisationsForRegister().then(() => applyFields())
}

onMounted(() => {
  applyRegisterPrefillFromQuery()
})

watch(
  () => ({
    register: route.query.register,
    org_id: route.query.org_id,
    org_name: route.query.org_name,
    dept_name: route.query.dept_name
  }),
  () => applyRegisterPrefillFromQuery(),
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
  requestedDepartmentName.value = ''
  inviteOrganisationId.value = ''
  inviteOrganisationName.value = ''
  website.value = ''
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
  cleanupTurnstile()
})

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
  if (nextMode === 'register') {
    loadOrganisationsForRegister().then(() => {
      applyRegisterPrefillFromQuery()
    })
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

  if (!effectiveRequestedOrganisationId.value) {
    error.value = 'Bitte eine Organisation waehlen'
    return
  }
  if (!requestedDepartmentName.value.trim()) {
    error.value = 'Bitte den Namen deiner Abteilung eingeben'
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

  let turnstileToken: string | undefined
  if (turnstileSiteKey.value) {
    const wid = turnstileWidgetId.value
    const t = wid && window.turnstile ? window.turnstile.getResponse(wid) : undefined
    if (!t) {
      error.value = 'Bitte das Captcha abschliessen.'
      return
    }
    turnstileToken = t
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
      requestedDepartmentName: requestedDepartmentName.value.trim(),
      website: website.value,
      turnstileToken
    })

    successMessage.value = response.message || 'Konto erfolgreich erstellt'
    mode.value = 'login'
    email.value = registerEmail.value.trim()
    password.value = ''
    resetRegisterForm()
  } catch (err: any) {
    error.value = err?.response?.data?.error || 'Registrierung fehlgeschlagen'
    resetTurnstileWidget()
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

watch([firstName, lastName, nickname, registerEmail, registerPassword, registerPasswordConfirm, language, acceptTerms, requestedOrganisationId, requestedDepartmentName], () => {
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
  background: linear-gradient(160deg, #f1f5f9 0%, #e2e8f0 100%);
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
  color: #0f172a;
  margin: 0 0 8px;
}

.brand-subtitle {
  color: #64748b;
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

.turnstile-wrap {
  margin-top: 4px;
}

.turnstile-box {
  min-height: 65px;
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
