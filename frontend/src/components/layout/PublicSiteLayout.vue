<template>
  <div class="plt-shell">
    <header class="plt-header" role="banner">
      <div class="plt-header-inner">
        <RouterLink to="/" class="plt-brand public-brand" :title="t('publicNav.brandTitle')">
          <EmcLogoMark size="sm" />
          <span class="plt-brand-text public-brand-text">eMatChef</span>
        </RouterLink>
        <nav class="plt-nav" :aria-label="t('publicNav.mainAria')">
          <RouterLink to="/blog">{{ t('publicNav.blog') }}</RouterLink>
          <RouterLink to="/faq">{{ t('publicNav.faq') }}</RouterLink>
          <RouterLink to="/tos">{{ t('publicNav.tos') }}</RouterLink>
          <select v-model="publicLocale" class="public-locale-select" :aria-label="t('publicNav.language')">
            <option value="de">DE</option>
            <option value="en">EN</option>
            <option value="fr">FR</option>
          </select>
        </nav>
        <a
          v-if="isPublicLoggedIn"
          :href="appEntryHref"
          :target="openAppInNewTab ? '_blank' : undefined"
          :rel="openAppInNewTab ? 'noopener noreferrer' : undefined"
          class="public-user-link"
          :title="t('public.lookup.toApp')"
        >
          <PublicUserIdentityChip
            :display-name="publicDisplayName"
            :initials="publicInitials"
            :background-color="avatarStyle.backgroundColor"
            :text-color="avatarStyle.color"
          />
        </a>
        <AppLoginLink v-else class="plt-nav-cta btn btn-primary plt-btn-lg">{{ t('publicNav.login') }}</AppLoginLink>
      </div>
    </header>
    <main class="plt-main">
      <RouterView />
    </main>
    <PublicSiteFooter />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import AppLoginLink from '@/components/public/AppLoginLink.vue'
import PublicSiteFooter from '@/components/public/PublicSiteFooter.vue'
import PublicUserIdentityChip from '@/components/public/PublicUserIdentityChip.vue'
import { setLocale } from '@/i18n'
import { getAppEntryTarget } from '@/utils/appLoginUrl'
import { useAuthStore } from '@/stores/auth'

const { t, locale } = useI18n()
const authStore = useAuthStore()
const PUBLIC_SESSION_POLL_MS = 10_000
const sessionPollTimer = ref<number | null>(null)

onMounted(() => {
  void authStore.loadUserSessionFromCookie(true)
  window.addEventListener('focus', refreshPublicSession)
  document.addEventListener('visibilitychange', onVisibilityChange)
  sessionPollTimer.value = window.setInterval(() => {
    if (document.visibilityState === 'visible') {
      refreshPublicSession()
    }
  }, PUBLIC_SESSION_POLL_MS)
})

onBeforeUnmount(() => {
  window.removeEventListener('focus', refreshPublicSession)
  document.removeEventListener('visibilitychange', onVisibilityChange)
  if (sessionPollTimer.value !== null) {
    window.clearInterval(sessionPollTimer.value)
    sessionPollTimer.value = null
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

function toPublicLocale(value: string): 'de' | 'en' | 'fr' {
  const v = String(value || '').toLowerCase()
  if (v.startsWith('en')) return 'en'
  if (v.startsWith('fr')) return 'fr'
  return 'de'
}

const publicLocale = computed({
  get: () => toPublicLocale(String(locale.value)),
  set: (v: string) => {
    setLocale(toPublicLocale(v))
  },
})

const isPublicLoggedIn = computed(() => authStore.isLoggedIn)
const publicDisplayName = computed(() =>
  authStore.userDisplayName || t('public.lookup.userFallback')
)
const publicInitials = computed(() =>
  authStore.userInitials || '??'
)
const avatarStyle = computed(() => {
  const colors = authStore.userColors
  return {
    backgroundColor: colors.background,
    color: colors.text,
  }
})
const appEntryHref = computed(() => getAppEntryTarget())
const openAppInNewTab = computed(() => {
  if (typeof window === 'undefined') return false
  try {
    return new URL(appEntryHref.value).origin !== window.location.origin
  } catch {
    return false
  }
})
</script>

<style scoped>
.public-locale-select {
  min-width: 4.25rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #fff;
  color: #0f172a;
  font-size: 0.82rem;
  font-weight: 600;
  padding: 0.3rem 0.45rem;
}

.public-user-link {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
  color: #0f172a;
  padding: 0.25rem 0.4rem;
  border-radius: 999px;
  transition: background-color 0.15s ease, transform 0.15s ease;
}

.public-user-link:hover {
  background: rgba(15, 23, 42, 0.08);
}

.public-user-link:active {
  transform: translateY(1px);
}

</style>
