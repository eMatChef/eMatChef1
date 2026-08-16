<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'

const props = defineProps<{
  totalCount: number
  loading?: boolean
  disabled?: boolean
  /** Trockene Positionen fertig, nasse Warteschlange noch offen (A2). */
  wetPendingCount?: number
}>()

const emit = defineEmits<{
  continue: []
}>()

const { t } = useI18n()

const wetPending = computed(() => Math.max(0, props.wetPendingCount ?? 0))

const title = computed(() =>
  wetPending.value > 0
    ? t('activities.materialJourney.storeComplete.wetPendingTitle')
    : t('activities.materialJourney.storeComplete.title'),
)

const description = computed(() =>
  wetPending.value > 0
    ? t('activities.materialJourney.storeComplete.wetPendingDescription', {
        count: props.totalCount,
        wet: wetPending.value,
      })
    : t('activities.materialJourney.storeComplete.description', { count: props.totalCount }),
)

const hint = computed(() =>
  wetPending.value > 0
    ? t('activities.materialJourney.storeComplete.wetPendingHint')
    : t('activities.materialJourney.storeComplete.hint'),
)
</script>

<template>
  <section class="material-journey-pack-complete section-card">
    <EEmptyState
      :icon="wetPending > 0 ? 'mdi-water-outline' : 'mdi-check-circle-outline'"
      :title="title"
      :description="description"
    >
      <template #actions>
        <EButton
          variant="primary"
          size="default"
          class="material-journey-pack-complete__action"
          :loading="loading"
          :disabled="disabled"
          @click="emit('continue')"
        >
          {{ t('activities.materialJourney.storeComplete.continue') }}
        </EButton>
        <p class="material-journey-pack-complete__hint text-muted">
          {{ hint }}
        </p>
      </template>
    </EEmptyState>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
