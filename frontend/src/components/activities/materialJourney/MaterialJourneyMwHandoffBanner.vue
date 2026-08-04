<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  /** packed = Ausgabe-Übergabe, at_event = Retour-Übergabe */
  variant: 'issue' | 'return'
}>()

const { t } = useI18n()

const title = computed(() =>
  props.variant === 'return'
    ? t('activities.packList.mwReturnHandoffBannerTitle')
    : t('activities.packList.mwHandoffBannerTitle'),
)

const body = computed(() =>
  props.variant === 'return'
    ? t('activities.packList.mwReturnHandoffBannerBody')
    : t('activities.packList.mwHandoffBannerBody'),
)
</script>

<template>
  <section
    class="material-journey-mw-handoff-banner section-card"
    role="status"
  >
    <p class="material-journey-mw-handoff-banner__title">{{ title }}</p>
    <p class="material-journey-mw-handoff-banner__body text-muted">{{ body }}</p>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';

.material-journey-mw-handoff-banner {
  margin-bottom: 12px;
  border: 1px solid rgba(var(--v-theme-info), 0.45);
  background: rgba(var(--v-theme-info), 0.08);
}

.material-journey-mw-handoff-banner__title {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 600;
}

.material-journey-mw-handoff-banner__body {
  margin: 0;
  font-size: 13px;
  line-height: 1.4;
}
</style>
