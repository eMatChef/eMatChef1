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
      <p v-if="loading" class="muted">{{ t('public.lookup.loading') }}</p>
      <p v-else-if="error" class="error">{{ error }}</p>

      <template v-else-if="data">
        <p class="card-hint">{{ t('public.lookup.cardScanHint') }}</p>

        <div class="card-layout">
          <section class="badge-card" :aria-label="t('public.lookup.cardBadgeLabel')">
            <p class="badge-kicker">{{ data.event.name }} · eMatChef</p>
            <h1 class="badge-name">{{ data.person.name }}</h1>
            <p class="badge-ressort">{{ [data.ressort, data.role].filter(Boolean).join(' · ') }}</p>
            <PublicQrTag
              class="badge-qr"
              :url="data.public_url"
              :code="data.code"
              :size="168"
              :image-label="data.person.name"
              :image-entity-id="data.code"
            />
            <p class="badge-code">{{ t('public.lookup.codePrefix') }}: {{ data.code }}</p>
            <p v-if="data.may_drive" class="badge-drive">{{ t('public.lookup.cardMayDrive') }}</p>
          </section>

          <section class="public-card overview-card">
            <h2 class="overview-title">{{ t('public.lookup.cardOverviewTitle') }}</h2>
            <p class="overview-lead">{{ t('public.lookup.cardOverviewLead') }}</p>
            <dl class="info-grid">
              <div>
                <dt>{{ t('public.lookup.cardPerson') }}</dt>
                <dd>{{ data.person.name }}</dd>
              </div>
              <div>
                <dt>{{ t('public.lookup.cardEvent') }}</dt>
                <dd>{{ data.event.name }}</dd>
              </div>
              <div v-if="data.ressort">
                <dt>{{ t('public.lookup.cardRessort') }}</dt>
                <dd>{{ data.ressort }}</dd>
              </div>
              <div v-if="data.role">
                <dt>{{ t('public.lookup.cardRole') }}</dt>
                <dd>{{ data.role }}</dd>
              </div>
              <div>
                <dt>{{ t('public.lookup.department') }}</dt>
                <dd>{{ data.department.name }}</dd>
              </div>
              <div>
                <dt>{{ t('public.lookup.cardDrive') }}</dt>
                <dd>{{ driveSummary }}</dd>
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
          </section>
        </div>
      </template>
    </main>

    <PublicSiteFooter />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getPublicUserCardByCode, type PublicLookupUserCardResponse } from '../../api/public/publicLookup'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../stores/auth'
import EmcLogoMark from '../../components/brand/EmcLogoMark.vue'
import PublicQrTag from '../../components/common/PublicQrTag.vue'
import PublicSiteFooter from '../../components/public/PublicSiteFooter.vue'
import PublicUserIdentityChip from '../../components/public/PublicUserIdentityChip.vue'
import { PAGE_HEAD_KEYS } from '../../composables/usePageHead'
import { usePageHeadStore } from '../../stores/pageHead'
import { getAppEntryTarget } from '../../utils/appLoginUrl'
import { isQrPublicHost, navigateToAppGrossanlassCards } from '../../utils/qrAppNavigation'
import { driveClassLabelKey } from '../../views/grossanlass/grossanlassDriveCategories'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const pageHeadStore = usePageHeadStore()

const cardCode = computed(() => String(route.params.cardCode || '').trim())

const loading = ref(false)
const error = ref<string | null>(null)
const data = ref<PublicLookupUserCardResponse | null>(null)

const isPublicLoggedIn = computed(() => authStore.isLoggedIn)
const publicAvatarStyle = computed(() => ({
  backgroundColor: authStore.userColors.background,
  color: authStore.userColors.text,
}))
const publicGreetingName = computed(() => authStore.userDisplayName || t('public.lookup.brandTitle'))
const publicInitials = computed(() => authStore.userInitials || '??')
const publicHomeUrl = computed(() => {
  const host = window.location.hostname.toLowerCase()
  if (host.includes('localhost') || host.includes('127.0.0.1')) {
    return window.location.origin
  }
  return 'https://ematchef.ch'
})

const lookupLoggedInActionLabel = computed(() => t('public.lookup.toUserCard'))
const lookupLoggedInActionTitle = computed(() => t('public.lookup.toUserCardTitle'))

const driveSummary = computed(() => {
  const row = data.value
  if (!row) return ''
  const labels = (row.drive_classes ?? []).map((code) => t(driveClassLabelKey(code)))
  if (!labels.length) return t('public.lookup.cardDriveNone')
  const prefix = row.may_drive
    ? t('public.lookup.cardDriveOk')
    : t('public.lookup.cardDrivePending')
  return `${prefix}: ${labels.join(', ')}`
})

const pageTitle = computed(() => {
  if (loading.value) return t(PAGE_HEAD_KEYS.defaultTitle)
  if (error.value) return t('router.meta.titles.publicLookup')
  const name = data.value?.person?.name?.trim() || t('public.lookup.cardFallback')
  return `${name} · eMatChef`
})

const pageDescription = computed(() => {
  if (!data.value) return t('router.meta.descriptions.publicLookup')
  return `${[data.value.person.name, data.value.event.name, t('public.lookup.cardFallback')].filter(Boolean).join(' · ')}. eMatChef.`
})

watch([pageTitle, pageDescription], () => {
  pageHeadStore.setDynamic(pageTitle.value, pageDescription.value)
}, { immediate: true })

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
  if (d?.department?.id) {
    navigateToAppGrossanlassCards(router, d.department.id)
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
  if (!cardCode.value) {
    error.value = t('public.lookup.errorInvalidCode')
    return
  }

  loading.value = true
  error.value = null

  try {
    data.value = await getPublicUserCardByCode(cardCode.value)
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

watch(cardCode, loadData)
</script>

<style scoped>
.public-header-actions { flex-shrink: 0; }
.public-user-link { border: none; background: transparent; padding: 6px 8px; cursor: pointer; border-radius: 10px; }
.public-login-btn { padding: 8px 16px; border-radius: 8px; border: 1px solid var(--public-btn-outline-border); background: var(--public-btn-outline-bg); color: var(--public-btn-outline-fg); font: inherit; font-weight: 600; cursor: pointer; }
.public-login-btn--primary { background: var(--public-btn-primary-bg); color: var(--public-btn-primary-fg); border-color: var(--public-btn-primary-border); }
.card-hint { margin: 0 0 14px; color: #64748b; font-size: 0.9rem; max-width: 42rem; }
.card-layout {
  display: grid;
  grid-template-columns: minmax(220px, 280px) minmax(0, 1fr);
  gap: 18px;
  align-items: start;
}
.badge-card {
  border: 1px solid #166534;
  border-radius: 16px;
  padding: 20px 16px 18px;
  background: linear-gradient(180deg, #ecfdf3 0%, #fff 55%);
  text-align: center;
}
.badge-kicker { margin: 0; font-size: 0.68rem; letter-spacing: 0.08em; text-transform: uppercase; color: #166534; font-weight: 700; }
.badge-name { margin: 10px 0 0; font-size: 1.35rem; font-weight: 800; }
.badge-ressort { margin: 4px 0 14px; color: #64748b; font-size: 0.85rem; }
.badge-qr { margin: 0 auto; }
.badge-code { margin: 10px 0 0; font-family: ui-monospace, monospace; font-size: 0.78rem; color: #334155; }
.badge-drive { margin: 8px 0 0; font-size: 0.78rem; font-weight: 700; color: #166534; }
.overview-title { margin: 0 0 8px; font-size: 1.2rem; }
.overview-lead { margin: 0 0 14px; color: #64748b; font-size: 0.9rem; }
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
.info-grid dt { font-size: 0.8rem; color: #64748b; }
.info-grid dd { margin: 2px 0 0; font-weight: 600; }
.public-material-app-link { margin-top: 18px; }
.muted { color: #64748b; }
.error { color: #b91c1c; }
@media (max-width: 720px) {
  .card-layout { grid-template-columns: 1fr; }
}
</style>
