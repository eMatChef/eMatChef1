<template>
  <div
    v-if="show"
    class="dev-environment-banner"
    role="status"
    aria-live="polite"
  >
    {{ t('app.devEnvironmentBanner') }}
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { shouldShowDevEnvironmentBanner } from '@/utils/devEnvironmentBanner'

const { t } = useI18n()

const show = computed(() => shouldShowDevEnvironmentBanner())
</script>

<style scoped>
.dev-environment-banner {
  position: sticky;
  top: 0;
  z-index: 10050;
  width: 100%;
  box-sizing: border-box;
  padding: 0.4rem 0.75rem;
  text-align: center;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.35;
  color: #422006;
  background: #facc15;
  border-bottom: 1px solid #ca8a04;
}
</style>
