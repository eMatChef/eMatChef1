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
          @click="goToMainSite"
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
        <h1 class="public-title">{{ publicPageTitle }}</h1>

        <p v-if="loading" class="muted">{{ t('public.lookup.loading') }}</p>
        <p v-else-if="error" class="error">{{ error }}</p>

        <template v-else-if="data">
          <p class="public-code">{{ t('public.lookup.codePrefix') }}: {{ data.code }}</p>

          <dl class="info-grid">
            <div v-if="workshopTypeLabel">
              <dt>{{ t('public.lookup.workshopType') }}</dt>
              <dd>{{ workshopTypeLabel }}</dd>
            </div>
            <div v-if="workshopStatusLabel">
              <dt>{{ t('common.status') }}</dt>
              <dd>{{ workshopStatusLabel }}</dd>
            </div>
            <div v-if="data.workshop.material_name">
              <dt>{{ t('common.material') }}</dt>
              <dd>{{ data.workshop.material_name }}</dd>
            </div>
            <div>
              <dt>{{ t('public.lookup.department') }}</dt>
              <dd>{{ data.department.name }}</dd>
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
                <p class="found-form-hint">{{ t('public.lookup.sendMessageHint') }}</p>
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
                <p class="muted">{{ t('public.lookup.deliveryUnavailable') }}</p>
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
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getPublicWorkshopByCode,
  submitPublicFoundItemContact,
  type PublicLookupWorkshopResponse,
} from '../../api/public/publicLookup'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../stores/auth'
import EmcLogoMark from '../../components/brand/EmcLogoMark.vue'
import PublicSiteFooter from '../../components/public/PublicSiteFooter.vue'
import PublicUserIdentityChip from '../../components/public/PublicUserIdentityChip.vue'
import { PAGE_HEAD_KEYS } from '../../composables/usePageHead'
import { usePageHeadStore } from '../../stores/pageHead'
import { getAppEntryTarget } from '../../utils/appLoginUrl'
import { isQrPublicHost, navigateToAppWorkshopTicket } from '../../utils/qrAppNavigation'

const { t, te } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const pageHeadStore = usePageHeadStore()

const workshopCode = computed(() => String(route.params.workshopCode || '').trim())

const loading = ref(false)
const error = ref<string | null>(null)
const data = ref<PublicLookupWorkshopResponse | null>(null)

const isPublicLoggedIn = computed(() => authStore.isLoggedIn)
const publicAvatarStyle = computed(() => ({
  backgroundColor: authStore.userColors.background,
  color: authStore.userColors.text,
}))
const publicGreetingName = computed(() =>
  authStore.userDisplayName || t('common.material')
)
const publicInitials = computed(() => authStore.userInitials || '??')
const publicHomeUrl = computed(() => {
  const host = window.location.hostname.toLowerCase()
  if (host.includes('localhost') || host.includes('127.0.0.1')) {
    return window.location.origin
  }
  return 'https://ematchef.ch'
})

const lookupLoggedInActionLabel = computed(() => t('public.lookup.toWorkshop'))
const lookupLoggedInActionTitle = computed(() => t('public.lookup.toWorkshopTitle'))

const workshopTypeLabel = computed(() => {
  const type = data.value?.workshop?.type
  if (!type) return ''
  const key = `public.lookup.workshopTypes.${type}`
  return te(key) ? t(key) : type
})

const workshopStatusLabel = computed(() => {
  const status = data.value?.workshop?.status
  if (!status) return ''
  const key = `public.lookup.workshopStatuses.${status}`
  return te(key) ? t(key) : status
})

const publicPageTitle = computed(() => {
  const title = data.value?.workshop?.title?.trim()
  if (title) return title
  return t('public.lookup.workshopInfoTitle')
})

const pageTitle = computed(() => {
  if (loading.value) return t(PAGE_HEAD_KEYS.defaultTitle)
  if (error.value) return t('router.meta.titles.publicLookup')
  const title = data.value?.workshop?.title?.trim() || t('public.lookup.workshopFallback')
  return `${title} · eMatChef`
})

const pageDescription = computed(() => {
  if (!data.value) return t('router.meta.descriptions.publicLookup')
  const w = data.value.workshop
  const dept = data.value.department?.name
  return `${[w.title, w.material_name, dept, t('public.lookup.workshopFallback')].filter(Boolean).join(' · ')}. eMatChef.`
})

watch([pageTitle, pageDescription], () => {
  pageHeadStore.setDynamic(pageTitle.value, pageDescription.value)
}, { immediate: true })

const showPublicContactForm = computed(() => {
  const d = data.value
  if (!d) return false
  if (isPublicLoggedIn.value) return false
  return d.public_ui?.show_contact_form !== false
})

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
      entity_type: 'workshop',
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
    foundFormError.value = e?.response?.data?.error || t('public.lookup.errorSendFailed')
  } finally {
    foundFormSubmitting.value = false
  }
}

function goToMainSite() {
  const host = window.location.hostname.toLowerCase()
  const shouldOpenInNewTab = isQrPublicHost() || host === 'ematchef.test'
  const target = getAppEntryTarget()
  if (shouldOpenInNewTab && target.startsWith('http')) {
    window.open(target, '_blank', 'noopener,noreferrer')
    return
  }
  void router.push('/')
}

function goToApp() {
  if (!authStore.isLoggedIn) {
    goToMainSite()
    return
  }
  const d = data.value
  if (d?.department?.id && d?.workshop?.id) {
    navigateToAppWorkshopTicket(router, d.department.id, d.workshop.id)
    return
  }
  const deptId = authStore.activeDepartmentId
  if (deptId) {
    void router.push(`/${deptId}`)
    return
  }
  void router.push('/pending-assignment')
}

async function loadData() {
  if (!workshopCode.value) {
    error.value = t('public.lookup.errorInvalidCode')
    return
  }

  loading.value = true
  error.value = null
  resetFoundForm()

  try {
    data.value = await getPublicWorkshopByCode(workshopCode.value)
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
})

watch(workshopCode, loadData)
</script>

<style scoped>
.public-header-actions { flex-shrink: 0; }
.public-user-link { border: none; background: transparent; padding: 6px 8px; cursor: pointer; border-radius: 10px; }
.public-login-btn { padding: 8px 16px; border-radius: 8px; border: 1px solid var(--public-btn-outline-border); background: var(--public-btn-outline-bg); color: var(--public-btn-outline-fg); font: inherit; font-weight: 600; cursor: pointer; }
.public-login-btn--primary { background: var(--public-btn-primary-bg); color: var(--public-btn-primary-fg); border-color: var(--public-btn-primary-border); }
.public-title { margin: 0 0 12px; font-size: 1.4rem; }
.public-code { margin: 0 0 6px; color: #64748b; font-family: ui-monospace, monospace; }
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
.info-grid dt { font-size: 0.8rem; color: #64748b; }
.info-grid dd { margin: 0; font-weight: 600; }
.public-material-app-link { margin-top: 16px; }
.contact-collapsible { margin-top: 16px; }
.contact-toggle { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border: 1px solid #cbd5e1; border-radius: 12px; background: #fff; font: inherit; font-weight: 600; cursor: pointer; text-align: left; }
.contact-toggle-chevron.is-open { transform: rotate(180deg); }
.contact-panel { margin-top: 10px; border-radius: 12px; overflow: hidden; }
.contact-box--in-panel { margin-top: 0; border-radius: 12px 12px 0 0; border: 1px solid #e2e8f0; border-bottom: none; background: #f1f5f9; padding: 12px; }
.found-form-box { padding: 16px; border: 1px solid #e2e8f0; background: #fafafa; border-radius: 12px; }
.found-form { display: flex; flex-direction: column; gap: 12px; }
.found-label { display: flex; flex-direction: column; gap: 6px; font-size: 0.9rem; }
.found-label input, .found-label textarea { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
.found-label.hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
.found-submit { align-self: flex-start; padding: 10px 18px; border-radius: 8px; border: none; background: #0f172a; color: #fff; font-weight: 600; cursor: pointer; }
.muted { color: #64748b; }
.error { color: #b91c1c; }
.found-form-msg.success { color: #15803d; }
</style>
