<template>
  <div
    v-if="show"
    class="dev-environment-banner"
    role="status"
    aria-live="polite"
  >
    <span class="dev-environment-banner__text">{{ t('app.devEnvironmentBanner') }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { shouldShowDevEnvironmentBanner } from '@/utils/devEnvironmentBanner'

const { t } = useI18n()

const show = computed(() => shouldShowDevEnvironmentBanner())
</script>

<style>
.emc-app:has(.dev-environment-banner) {
  --emc-dev-system-bar-height: 36px;
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
  min-height: var(--emc-dev-system-bar-height, 36px);
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #facc15;
  color: #422006;
  font-size: 0.8125rem;
  font-weight: 600;
  border-bottom: 1px solid #ca8a04;
}

.dev-environment-banner__text {
  line-height: 1.35;
  text-align: center;
  padding: 0.35rem 0.75rem;
}
</style>
