<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'

export type QuickReturnHandoffBannerMode = 'handoff' | 'handoffDone' | 'storeForMw'

const props = defineProps<{
  mode: QuickReturnHandoffBannerMode
  loading?: boolean
  disabled?: boolean
}>()

const emit = defineEmits<{
  action: []
}>()

const { t } = useI18n()

const i18nBase = computed(() => `activities.materialJourney.quickReturnHandoff.${props.mode}`)
</script>

<template>
  <section
    class="material-journey-handoff-banner section-card"
    :class="{
      'material-journey-handoff-banner--done': mode === 'handoffDone',
      'material-journey-handoff-banner--mw': mode === 'storeForMw',
    }"
  >
    <div class="material-journey-handoff-banner__copy">
      <p class="material-journey-handoff-banner__title">
        {{ t(`${i18nBase}.title`) }}
      </p>
      <p class="material-journey-handoff-banner__desc text-muted">
        {{ t(`${i18nBase}.description`) }}
      </p>
    </div>
    <EButton
      v-if="mode === 'handoff' || mode === 'storeForMw'"
      variant="primary"
      size="default"
      class="material-journey-handoff-banner__action"
      :loading="loading"
      :disabled="disabled"
      @click="emit('action')"
    >
      {{ t(`${i18nBase}.action`) }}
    </EButton>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';

.material-journey-handoff-banner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px 16px;
  margin-bottom: 12px;
  border: 1px solid rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.06);
}

.material-journey-handoff-banner--done {
  border-color: #86efac;
  background: #f0fdf4;
}

.material-journey-handoff-banner--mw {
  border-color: #fcd34d;
  background: #fffbeb;
}

.material-journey-handoff-banner__title {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 600;
}

.material-journey-handoff-banner__desc {
  margin: 0;
  font-size: 13px;
}

.material-journey-handoff-banner__action {
  flex-shrink: 0;
}
</style>
