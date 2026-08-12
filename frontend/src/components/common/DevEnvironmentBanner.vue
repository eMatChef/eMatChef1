<template>
  <div
    v-if="show"
    class="dev-environment-banner"
    role="status"
    aria-live="polite"
  >
    <div class="dev-environment-banner__row">
      <span class="dev-environment-banner__text">{{ t('app.devEnvironmentBanner') }}</span>
      <button
        v-if="showDemoLogins"
        type="button"
        class="dev-environment-banner__toggle"
        :aria-expanded="demoOpen"
        @click="demoOpen = !demoOpen"
      >
        {{ demoOpen ? t('app.devDemoLoginsHide') : t('app.devDemoLoginsShow') }}
      </button>
    </div>
    <div
      v-if="showDemoLogins && demoOpen"
      class="dev-environment-banner__demos"
    >
      <p class="dev-environment-banner__demos-hint">
        {{ t('app.devDemoLoginsHint') }}
      </p>
      <div class="dev-environment-banner__demos-list">
        <button
          v-for="account in DEMO_LOGINS"
          :key="account.email"
          type="button"
          class="dev-environment-banner__demo"
          @click="fillDemoLogin(account)"
        >
          <span class="dev-environment-banner__demo-label">{{ account.label }}</span>
          <span class="dev-environment-banner__demo-email">{{ account.email }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { shouldShowDevEnvironmentBanner } from '@/utils/devEnvironmentBanner'
import { DEMO_LOGINS, stashDemoLogin, type DemoLogin } from '@/utils/demoLogins'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

const show = computed(() => shouldShowDevEnvironmentBanner())
const showDemoLogins = computed(() => show.value && !authStore.isLoggedIn)
const demoOpen = ref(false)

function fillDemoLogin(account: DemoLogin) {
  stashDemoLogin(account.email, account.password)
  demoOpen.value = false
  if (router.currentRoute.value.path === '/login') {
    // LoginView liest sessionStorage beim Mount / via Event
    window.dispatchEvent(new CustomEvent('emc-demo-login'))
    return
  }
  void router.push({ path: '/login' })
}
</script>

<style>
.emc-app:has(.dev-environment-banner) {
  --emc-dev-system-bar-height: 36px;
}

.emc-app:has(.dev-environment-banner:has(.dev-environment-banner__demos)) {
  --emc-dev-system-bar-height: auto;
}

/*
 * Banner im Flex-Layout (volle Breite) → v-main startet bereits unter dem Banner.
 * Nur der fixierte TopHeader (Listen/Shell) wird nach unten versetzt.
 * Aktivitäts-Detail: Header scrollt mit (.top-header--in-scroll) — kein top-Offset!
 */
.emc-app:has(.dev-environment-banner) .top-header.v-app-bar:not(.top-header--in-scroll) {
  top: var(--emc-dev-system-bar-height) !important;
}

.emc-app:has(.dev-environment-banner) .top-header.top-header--in-scroll {
  top: auto !important;
  left: 0 !important;
  right: auto !important;
  width: 100% !important;
  position: relative !important;
}

.emc-app:has(.dev-environment-banner) .emc-sidebar-drawer.v-navigation-drawer {
  top: var(--emc-dev-system-bar-height) !important;
  height: calc(100% - var(--emc-dev-system-bar-height)) !important;
}

.emc-app:has(.dev-environment-banner) .page-main:not(.page-main--activity-detail) {
  --v-layout-top: 64px !important;
}
</style>

<style scoped>
.dev-environment-banner {
  flex: 0 0 auto;
  width: 100%;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  align-items: stretch;
  background: #facc15;
  color: #422006;
  font-size: 0.8125rem;
  font-weight: 600;
  border-bottom: 1px solid #ca8a04;
}

.dev-environment-banner__row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.5rem 0.75rem;
  min-height: 36px;
  padding: 0.35rem 0.75rem;
}

.dev-environment-banner__text {
  line-height: 1.35;
  text-align: center;
}

.dev-environment-banner__toggle {
  appearance: none;
  border: 1px solid #a16207;
  background: #fef08a;
  color: #422006;
  border-radius: 4px;
  font: inherit;
  font-weight: 700;
  font-size: 0.75rem;
  padding: 0.2rem 0.55rem;
  cursor: pointer;
}

.dev-environment-banner__toggle:hover {
  background: #fde047;
}

.dev-environment-banner__demos {
  border-top: 1px solid #ca8a04;
  padding: 0.5rem 0.75rem 0.65rem;
  background: #fef9c3;
}

.dev-environment-banner__demos-hint {
  margin: 0 0 0.4rem;
  font-size: 0.7rem;
  font-weight: 500;
  text-align: center;
  opacity: 0.9;
}

.dev-environment-banner__demos-list {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.35rem;
}

.dev-environment-banner__demo {
  appearance: none;
  border: 1px solid #a16207;
  background: #fffbeb;
  color: #422006;
  border-radius: 4px;
  font: inherit;
  font-weight: 600;
  font-size: 0.7rem;
  padding: 0.25rem 0.45rem;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.05rem;
  max-width: 12rem;
  text-align: left;
}

.dev-environment-banner__demo:hover {
  background: #fef08a;
}

.dev-environment-banner__demo-label {
  font-weight: 700;
}

.dev-environment-banner__demo-email {
  font-weight: 500;
  font-size: 0.65rem;
  opacity: 0.85;
  word-break: break-all;
}
</style>
