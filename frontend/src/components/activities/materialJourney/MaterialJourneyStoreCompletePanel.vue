<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'

defineProps<{
  totalCount: number
  loading?: boolean
  disabled?: boolean
}>()

const emit = defineEmits<{
  continue: []
}>()

const { t } = useI18n()
</script>

<template>
  <section class="material-journey-pack-complete section-card">
    <EEmptyState
      icon="mdi-check-circle-outline"
      :title="t('activities.materialJourney.storeComplete.title')"
      :description="t('activities.materialJourney.storeComplete.description', { count: totalCount })"
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
          {{ t('activities.materialJourney.storeComplete.hint') }}
        </p>
      </template>
    </EEmptyState>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
