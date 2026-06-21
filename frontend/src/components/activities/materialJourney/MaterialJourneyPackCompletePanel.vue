<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { activityTransitionActionLabel } from '@/components/activities/activityTransitionLabels'
import type { ActivityTransitionRow } from '@/api/activities'

const props = defineProps<{
  totalCount: number
  transition: ActivityTransitionRow | null
  loading?: boolean
  currentStatus?: string | null
}>()

const emit = defineEmits<{
  markPacked: []
}>()

const { t, te } = useI18n()

const buttonLabel = computed(() => {
  if (!props.transition) return t('activities.transitionActions.packed')
  return activityTransitionActionLabel(
    props.transition.status,
    props.currentStatus,
    t,
    te,
    props.transition.label,
  )
})

const showAction = computed(() => props.transition != null)
const actionDisabled = computed(() => !props.transition?.allowed || props.loading)
const actionTitle = computed(() => {
  if (!props.transition) return undefined
  if (props.transition.allowed) return buttonLabel.value
  return props.transition.reason ?? buttonLabel.value
})
</script>

<template>
  <section class="material-journey-pack-complete section-card">
    <EEmptyState
      icon="mdi-check-circle-outline"
      :title="t('activities.materialJourney.packComplete.title')"
      :description="t('activities.materialJourney.packComplete.description', { count: totalCount })"
    >
      <template v-if="showAction" #actions>
        <EButton
          variant="primary"
          size="default"
          class="material-journey-pack-complete__action"
          :disabled="actionDisabled"
          :loading="loading"
          :title="actionTitle"
          @click="emit('markPacked')"
        >
          {{ buttonLabel }}
        </EButton>
        <p v-if="transition?.allowed" class="material-journey-pack-complete__hint text-muted">
          {{ t('activities.materialJourney.packComplete.markPackedHint') }}
        </p>
        <p v-else-if="transition?.reason" class="material-journey-pack-complete__hint text-muted">
          {{ transition.reason }}
        </p>
      </template>
      <template v-else #actions>
        <p class="material-journey-pack-complete__hint text-muted">
          {{ t('activities.materialJourney.packComplete.noPermission') }}
        </p>
      </template>
    </EEmptyState>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
