<template>
  <v-system-bar
    v-if="show"
    class="dev-environment-banner"
    color="#facc15"
    height="36"
    role="status"
    aria-live="polite"
  >
    <span class="dev-environment-banner__text">{{ t('app.devEnvironmentBanner') }}</span>
  </v-system-bar>
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
</style>

<style scoped>
.dev-environment-banner {
  color: #422006 !important;
  font-size: 0.8125rem;
  font-weight: 600;
  justify-content: center;
  border-bottom: 1px solid #ca8a04;
}

.dev-environment-banner__text {
  line-height: 1.35;
  text-align: center;
}
</style>
