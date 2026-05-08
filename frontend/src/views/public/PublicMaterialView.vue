<template>
  <div class="public-layout">
    <header class="public-header" role="banner">
      <a :href="publicHomeUrl" class="public-brand" :title="t('public.lookup.brandTitle')">
        <EmcLogoMark size="sm" />
        <span class="public-brand-text">eMatChef</span>
      </a>
      <div class="public-header-actions">
        <button
          v-if="!isPublicLoggedIn"
          type="button"
          class="public-login-btn"
          @click="goToLogin"
        >
          {{ t('public.lookup.toApp') }}
        </button>
        <button
          v-else
          type="button"
          class="public-user-link"
          :title="lookupLoggedInActionTitle"
          :aria-label="t('public.lookup.loggedInAs', { name: publicGreetingName })"
          @click="goToApp"
        >
          <PublicUserIdentityChip
            :display-name="publicGreetingName"
            :initials="publicInitials"
            :background-color="publicAvatarStyle.backgroundColor"
            :text-color="publicAvatarStyle.color"
          />
        </button>
      </div>
    </header>

    <main class="public-page">
    <section class="public-card">
      <h1 class="public-title">{{ routeType === 'b' ? t('public.lookup.serialInfoTitle') : t('public.lookup.materialInfoTitle') }}</h1>

      <p v-if="loading" class="muted">{{ t('public.lookup.loading') }}</p>
      <p v-else-if="error" class="error">{{ error }}</p>

      <template v-else-if="data">
        <p class="public-code">{{ t('public.lookup.codePrefix') }}: {{ routeCode }}</p>
        <p v-if="routeType === 'b'" class="public-code">
          {{ t('public.lookup.serialPrefix') }}: {{ data.batch?.serial_number || data.batch?.label || data.batch?.id }}
        </p>
        <h2 class="material-name">{{ data.material.name }}</h2>

        <p v-if="data.material.description" class="material-desc">
          {{ data.material.description }}
        </p>

        <dl class="info-grid">
          <div>
            <dt>{{ t('public.lookup.department') }}</dt>
            <dd>{{ data.department.name }}</dd>
          </div>
          <div v-if="data.material.manufacturer">
            <dt>{{ t('public.lookup.manufacturer') }}</dt>
            <dd>{{ data.material.manufacturer }}</dd>
          </div>
          <div v-if="data.material.model">
            <dt>{{ t('public.lookup.model') }}</dt>
            <dd>{{ data.material.model }}</dd>
          </div>
        </dl>

        <div v-if="isPublicLoggedIn" class="public-material-app-link">
          <button
            type="button"
            class="public-login-btn public-login-btn--primary"
            @click="goToApp"
          >
            {{ lookupLoggedInActionLabel }}
          </button>
        </div>

        <div v-if="showPublicContactForm" class="contact-collapsible">
          <button
            type="button"
            class="contact-toggle"
            :aria-expanded="contactExpanded"
            aria-controls="public-contact-panel"
            id="public-contact-toggle"
            @click="contactExpanded = !contactExpanded"
          >
            <span class="contact-toggle-label">{{ t('public.lookup.contactMaintainer') }}</span>
            <span class="contact-toggle-chevron" :class="{ 'is-open': contactExpanded }" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9" />
              </svg>
            </span>
          </button>

          <div
            v-show="contactExpanded"
            id="public-contact-panel"
            class="contact-panel"
            role="region"
            aria-labelledby="public-contact-toggle"
          >
            <div v-if="data.contact || data.contact_note" class="contact-box contact-box--in-panel">
              <h3>{{ t('public.lookup.contactTitle') }}</h3>
              <p v-if="data.contact?.email">{{ t('public.lookup.emailPrefix') }}: {{ data.contact.email }}</p>
              <p v-if="data.contact_note" class="contact-note">{{ data.contact_note }}</p>
            </div>

            <div v-if="canDeliverPublicMessage" class="found-form-box">
              <h3 class="found-form-title">{{ t('public.lookup.sendMessageTitle') }}</h3>
              <p class="found-form-hint">
                {{ t('public.lookup.sendMessageHint') }}
              </p>
              <form class="found-form" @submit.prevent="submitFoundContact">
                <label class="found-label hp" aria-hidden="true">
                  {{ t('public.lookup.websiteHoneyLabel') }}
                  <input v-model="foundForm.website" type="text" name="website" tabindex="-1" autocomplete="off" />
                </label>
                <label class="found-label">
                  {{ t('public.lookup.yourName') }} <span class="optional">({{ t('common.optional') }})</span>
                  <input v-model="foundForm.sender_name" type="text" maxlength="120" :placeholder="t('public.lookup.yourNamePlaceholder')" />
                </label>
                <label class="found-label">
                  {{ t('public.lookup.yourEmail') }} <span class="optional">({{ t('public.lookup.optionalForQuestions') }})</span>
                  <input
                    v-model="foundForm.sender_email"
                    type="email"
                    maxlength="200"
                    :placeholder="t('public.lookup.yourEmailPlaceholder')"
                  />
                </label>
                <label class="found-label">
                  {{ t('public.lookup.messageLabel') }} <span class="req">*</span>
                  <textarea
                    v-model="foundForm.message"
                    rows="4"
                    maxlength="4000"
                    required
                    :placeholder="t('public.lookup.messagePlaceholder')"
                  />
                </label>
                <p v-if="foundFormError" class="error found-form-msg">{{ foundFormError }}</p>
                <p v-else-if="foundFormSuccess" class="found-form-msg success">{{ t('public.lookup.messageSentSuccess') }}</p>
                <button type="submit" class="found-submit" :disabled="foundFormSubmitting">
                  {{ foundFormSubmitting ? t('public.lookup.sending') : t('public.lookup.sendToMaintainer') }}
                </button>
              </form>
            </div>
            <div v-else class="found-form-box found-form-unavailable">
              <p class="muted">
                {{ t('public.lookup.deliveryUnavailable') }}
              </p>
            </div>
          </div>
        </div>
      </template>
    </section>
    </main>

    <PublicSiteFooter :compact="true" />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getPublicBatchByCode,
  getPublicMaterialByCode,
  submitPublicFoundItemContact,
  type PublicLookupBatchResponse,
  type PublicLookupMaterialResponse,
} from '../../api/public/publicLookup'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../stores/auth'
import EmcLogoMark from '../../components/brand/EmcLogoMark.vue'
import PublicSiteFooter from '../../components/public/PublicSiteFooter.vue'
import PublicUserIdentityChip from '../../components/public/PublicUserIdentityChip.vue'
import { PAGE_HEAD_KEYS } from '../../composables/usePageHead'
import { usePageHeadStore } from '../../stores/pageHead'
import { isQrPublicHost, navigateToAppMaterialDetail } from '../../utils/qrAppNavigation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const pageHeadStore = usePageHeadStore()
const PUBLIC_SESSION_POLL_MS = 60_000
let sessionPollTimer: number | null = null

const routeType = computed(() => String(route.params.type || 'm').trim().toLowerCase())
const routeCode = computed(() => String(route.params.code || '').trim())

const loading = ref(false)
const error = ref<string | null>(null)
type PublicLookupViewData = PublicLookupMaterialResponse | PublicLookupBatchResponse
const data = ref<PublicLookupViewData | null>(null)

const isPublicLoggedIn = computed(() => authStore.isLoggedIn)
const publicAvatarStyle = computed(() => ({
  backgroundColor: authStore.userColors.background,
  color: authStore.userColors.text,
}))
const publicGreetingName = computed(() =>
  authStore.userDisplayName || t('public.lookup.materialFallback')
)
const publicInitials = computed(() => authStore.userInitials || '??')
const publicHomeUrl = computed(() => {
  const host = window.location.hostname.toLowerCase()
  if (host.includes('localhost') || host.includes('127.0.0.1')) {
    return window.location.origin
  }
  return 'https://ematchef.ch'
})

const lookupLoggedInActionLabel = computed(() => t('public.lookup.toMaterial'))
const lookupLoggedInActionTitle = computed(() => t('public.lookup.toMaterialTitle'))

const pageTitle = computed(() => {
  if (loading.value) return t(PAGE_HEAD_KEYS.defaultTitle)
  if (error.value) return t('router.meta.titles.publicLookup')
  if (!data.value) return t(PAGE_HEAD_KEYS.defaultTitle)
  const d = data.value
  const name = d.material?.name?.trim() || t('public.lookup.materialFallback')
  if (routeType.value === 'b' && d.entity_type === 'batch' && d.batch) {
    const serial = String(d.batch.serial_number || d.batch.label || '').trim()
    return serial ? `${serial} · ${name} · eMatChef` : `${name} · eMatChef`
  }
  return `${name} · eMatChef`
})

const pageDescription = computed(() => {
  if (loading.value) {
    return t('router.meta.descriptions.publicLookup')
  }
  if (error.value) {
    return t('router.meta.descriptions.publicLookupError')
  }
  if (!data.value) {
    return t('router.meta.descriptions.publicLookupNoData')
  }
  const d = data.value
  const mat = d.material.name
  const dept = d.department?.name
  const bit = routeType.value === 'b' ? t('public.lookup.serialOrBatch') : t('public.lookup.materialFallback')
  return `${[mat, dept, bit].filter(Boolean).join(' · ')}. eMatChef.`
})

watch(
  [pageTitle, pageDescription],
  () => {
    pageHeadStore.setDynamic(pageTitle.value, pageDescription.value)
  },
  { immediate: true }
)

/** Department-Einstellung: Kontaktformular-Bereich anzeigen (Standard: an). */
const showPublicContactForm = computed(() => {
  const d = data.value
  if (!d) return false
  if (isPublicLoggedIn.value) return false
  return d.public_ui?.show_contact_form !== false
})

/** Ob serverseitig eine Zustell-Adresse existiert (auch wenn E-Mail auf der Seite ausgeblendet ist). */
const canDeliverPublicMessage = computed(() => {
  const d = data.value
  if (!d) return false
  if (d.public_ui?.can_deliver_message !== undefined) {
    return d.public_ui.can_deliver_message
  }
  return !!d.contact?.email
})

const foundForm = ref({
  sender_name: '',
  sender_email: '',
  message: '',
  website: '',
})
const foundFormSubmitting = ref(false)
const foundFormError = ref<string | null>(null)
const foundFormSuccess = ref(false)

/** Aufklapp-Bereich „Materialwart kontaktieren“ (immer sichtbar, Inhalt erst nach Klick). */
const contactExpanded = ref(false)

function resetFoundForm() {
  foundForm.value = { sender_name: '', sender_email: '', message: '', website: '' }
  foundFormError.value = null
  foundFormSuccess.value = false
  contactExpanded.value = false
}

async function submitFoundContact() {
  const d = data.value
  if (!d || !canDeliverPublicMessage.value) return

  foundFormError.value = null
  foundFormSuccess.value = false
  const msg = foundForm.value.message.trim()
  if (msg.length < 5) {
    foundFormError.value = t('public.lookup.errorMessageTooShort')
    return
  }

  foundFormSubmitting.value = true
  try {
    await submitPublicFoundItemContact({
      entity_type: d.entity_type === 'batch' ? 'batch' : 'material',
      public_code: d.code,
      message: msg,
      sender_name: foundForm.value.sender_name.trim() || undefined,
      sender_email: foundForm.value.sender_email.trim() || undefined,
      website: foundForm.value.website,
    })
    foundFormSuccess.value = true
    foundForm.value.message = ''
    foundForm.value.website = ''
  } catch (e: any) {
    foundFormError.value =
      e?.response?.data?.error || t('public.lookup.errorSendFailed')
  } finally {
    foundFormSubmitting.value = false
  }
}

/** Login mit Rücksprung zu dieser öffentlichen Seite (Artikel-Kontext bleibt in der URL). */
function goToLogin() {
  const appOrigin = (import.meta.env.VITE_APP_ORIGIN || '').trim().replace(/\/$/, '')
  const host = window.location.hostname.toLowerCase()
  const shouldOpenInNewTab = isQrPublicHost() || host === 'ematchef.test'
  if (appOrigin && shouldOpenInNewTab) {
    const target = `${appOrigin}/login?redirect=${encodeURIComponent(route.fullPath)}`
    window.open(target, '_blank', 'noopener,noreferrer')
    return
  }
  void router.push({ path: '/login', query: { redirect: route.fullPath } })
}

/** Bereits angemeldet: ins Material / Dashboard wechseln. */
function goToApp() {
  if (!authStore.isLoggedIn) {
    goToLogin()
    return
  }
  const appOrigin = (import.meta.env.VITE_APP_ORIGIN || '').trim().replace(/\/$/, '')
  const onQrHost = isQrPublicHost()
  const shouldOpenInNewTab = onQrHost || window.location.hostname.toLowerCase() === 'ematchef.test'
  const openAppPath = (path: string) => {
    if (!appOrigin) return false
    const href = `${appOrigin}${path}`
    if (shouldOpenInNewTab) {
      window.open(href, '_blank', 'noopener,noreferrer')
    } else {
      window.location.assign(href)
    }
    return true
  }
  const d = data.value
  if (d?.department?.id && d?.material?.id) {
    if (shouldOpenInNewTab && appOrigin) {
      const params = new URLSearchParams()
      if (d.entity_type === 'batch' && d.batch?.id) {
        params.set('batch', d.batch.id)
      }
      const qs = params.toString()
      openAppPath(`/${d.department.id}/materials/${d.material.id}${qs ? `?${qs}` : ''}`)
      return
    }
    navigateToAppMaterialDetail(
      router,
      d.department.id,
      d.material.id,
      d.entity_type === 'batch' ? (d.batch?.id || null) : null
    )
    return
  }
  const deptId = authStore.activeDepartmentId
  if (deptId) {
    if ((onQrHost || shouldOpenInNewTab) && appOrigin) {
      openAppPath(`/${deptId}`)
      return
    }
    void router.push(`/${deptId}`)
    return
  }
  if ((onQrHost || shouldOpenInNewTab) && appOrigin) {
    openAppPath('/pending-assignment')
    return
  }
  void router.push('/pending-assignment')
}

async function loadData() {
  if (!routeCode.value) {
    error.value = t('public.lookup.errorInvalidCode')
    return
  }

  loading.value = true
  error.value = null
  resetFoundForm()

  try {
    if (routeType.value === 'b') {
      data.value = await getPublicBatchByCode(routeCode.value)
    } else {
      data.value = await getPublicMaterialByCode(routeCode.value)
    }
  } catch {
    error.value = t('public.lookup.errorCodeNotFound')
    data.value = null
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void authStore.loadUserSessionFromCookie(true)
  void loadData()
  window.addEventListener('focus', refreshPublicSession)
  document.addEventListener('visibilitychange', onVisibilityChange)
  sessionPollTimer = window.setInterval(() => {
    if (document.visibilityState === 'visible') {
      refreshPublicSession()
    }
  }, PUBLIC_SESSION_POLL_MS)
})

onBeforeUnmount(() => {
  window.removeEventListener('focus', refreshPublicSession)
  document.removeEventListener('visibilitychange', onVisibilityChange)
  if (sessionPollTimer !== null) {
    window.clearInterval(sessionPollTimer)
    sessionPollTimer = null
  }
})

function refreshPublicSession() {
  void authStore.loadUserSessionFromCookie(true)
}

function onVisibilityChange() {
  if (document.visibilityState === 'visible') {
    refreshPublicSession()
  }
}
watch([routeType, routeCode], loadData)
</script>

<style scoped>
.public-header-actions {
  flex-shrink: 0;
}

.public-user-link {
  border: none;
  background: transparent;
  padding: 6px 8px;
  cursor: pointer;
  border-radius: 10px;
  transition: background-color 0.15s ease, transform 0.12s ease;
}

.public-user-link:hover {
  background: var(--public-btn-outline-bg-hover);
}

.public-user-link:active {
  transform: translateY(1px);
}

.public-user-link:focus-visible {
  outline: 2px solid var(--public-btn-focus-ring);
  outline-offset: 2px;
  border-radius: 10px;
}

.public-login-btn {
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid var(--public-btn-outline-border);
  background: var(--public-btn-outline-bg);
  color: var(--public-btn-outline-fg);
  font: inherit;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.public-login-btn:hover {
  background: var(--public-btn-outline-bg-hover);
  border-color: var(--public-btn-outline-border-hover);
}

.public-login-btn:focus-visible {
  outline: 2px solid var(--public-btn-focus-ring);
  outline-offset: 2px;
}

.public-login-btn--primary {
  background: var(--public-btn-primary-bg);
  color: var(--public-btn-primary-fg);
  border-color: var(--public-btn-primary-border);
}

.public-login-btn--primary:hover {
  background: var(--public-btn-primary-bg-hover);
  border-color: var(--public-btn-primary-border-hover);
}

.public-title {
  margin: 0 0 12px;
  font-size: 1.4rem;
}

.public-code {
  margin: 0 0 6px;
  color: #64748b;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

.material-name {
  margin: 0 0 8px;
}

.material-desc {
  margin: 0 0 14px;
  color: #334155;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}

.info-grid dt {
  font-size: 0.8rem;
  color: #64748b;
}

.info-grid dd {
  margin: 0;
  font-weight: 600;
}

.contact-box {
  margin-top: 16px;
  padding: 12px;
  border-radius: 10px;
  background: #f1f5f9;
}

/* Kontakt oben im aufgeklappten Panel, optisch mit Formular verbunden */
.contact-box--in-panel {
  margin-top: 0;
  margin-bottom: 0;
  border-radius: 12px 12px 0 0;
  border: 1px solid #e2e8f0;
  border-bottom: none;
}

.contact-panel .contact-box--in-panel + .found-form-box {
  margin-top: 0;
  border-top-left-radius: 0;
  border-top-right-radius: 0;
}

.contact-note {
  margin-top: 8px;
  color: #475569;
  white-space: pre-wrap;
}

.contact-collapsible {
  margin-top: 16px;
}

.public-material-app-link {
  margin-top: 16px;
}

.contact-toggle {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  background: #fff;
  font: inherit;
  font-weight: 600;
  color: #0f172a;
  cursor: pointer;
  text-align: left;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.contact-toggle:hover {
  background: #f8fafc;
  border-color: #94a3b8;
}

.contact-toggle:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
}

.contact-toggle-label {
  flex: 1;
}

.contact-toggle-chevron {
  display: flex;
  color: #64748b;
  transition: transform 0.2s ease;
}

.contact-toggle-chevron.is-open {
  transform: rotate(180deg);
}

.contact-panel {
  margin-top: 10px;
  border-radius: 12px;
  overflow: hidden;
}

.found-form-unavailable .contact-note {
  margin-top: 12px;
}

.muted {
  color: #64748b;
}

.error {
  color: #b91c1c;
}

.found-form-box {
  margin-top: 0;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #fafafa;
}

.found-form-title {
  margin: 0 0 8px;
  font-size: 1.1rem;
}

.found-form-hint {
  margin: 0 0 12px;
  color: #475569;
  font-size: 0.95rem;
}

.found-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.found-label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 0.9rem;
  color: #334155;
}

.found-label input,
.found-label textarea {
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font: inherit;
}

.found-label textarea {
  resize: vertical;
  min-height: 100px;
}

.found-label .optional {
  font-weight: 400;
  color: #64748b;
  font-size: 0.85rem;
}

.found-label .req {
  color: #b91c1c;
}

.found-label.hp {
  position: absolute;
  left: -9999px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}

.found-submit {
  align-self: flex-start;
  padding: 10px 18px;
  border-radius: 8px;
  border: none;
  background: #0f172a;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}

.found-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.found-form-msg.success {
  color: #15803d;
  margin: 0;
}

.found-form-msg {
  margin: 0;
  font-size: 0.9rem;
}
</style>

