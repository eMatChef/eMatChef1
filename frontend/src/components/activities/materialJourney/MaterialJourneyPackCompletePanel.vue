<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { activityTransitionActionLabel } from '@/components/activities/activityTransitionLabels'
import type { ActivityTransitionRow } from '@/api/activities'

const props = withDefaults(
  defineProps<{
    mode?: 'pack' | 'transport'
    totalCount: number
    doneCount?: number
    unitsTransported?: number
    transition?: ActivityTransitionRow | null
    loading?: boolean
    currentStatus?: string | null
    actionDisabled?: boolean
  }>(),
  {
    mode: 'pack',
    doneCount: 0,
    unitsTransported: 0,
    transition: null,
    loading: false,
    actionDisabled: false,
  },
)

const emit = defineEmits<{
  confirm: []
}>()

const { t, te } = useI18n()

const isTransport = computed(() => props.mode === 'transport')

const title = computed(() =>
  isTransport.value
    ? t('activities.materialJourney.transportComplete.title')
    : t('activities.materialJourney.packComplete.title'),
)

const description = computed(() => {
  if (isTransport.value) {
    return t('activities.materialJourney.transportComplete.description', {
      positions: props.totalCount,
      units: props.unitsTransported,
    })
  }
  return t('activities.materialJourney.packComplete.description', { count: props.totalCount })
})

const buttonLabel = computed(() => {
  if (isTransport.value) {
    return t('activities.materialJourney.transportComplete.markTransported')
  }
  if (!props.transition) return t('activities.transitionActions.packed')
  return activityTransitionActionLabel(
    props.transition.status,
    props.currentStatus,
    t,
    te,
    props.transition.label,
  )
})

const showAction = computed(() => {
  if (isTransport.value) return !props.actionDisabled
  return props.transition != null
})

const actionDisabled = computed(() => {
  if (isTransport.value) return props.actionDisabled || props.loading
  return !props.transition?.allowed || props.loading
})

const actionTitle = computed(() => {
  if (isTransport.value) return buttonLabel.value
  if (!props.transition) return undefined
  if (props.transition.allowed) return buttonLabel.value
  return props.transition.reason ?? buttonLabel.value
})

const hintText = computed(() => {
  if (isTransport.value) {
    return t('activities.materialJourney.transportComplete.markTransportedHint')
  }
  if (props.transition?.allowed) {
    return t('activities.materialJourney.packComplete.markPackedHint')
  }
  if (props.transition?.reason) return props.transition.reason
  return t('activities.materialJourney.packComplete.noPermission')
})
</script>

<template>
  <section class="material-journey-pack-complete section-card">
    <EEmptyState
      icon="mdi-check-circle-outline"
      :title="title"
      :description="description"
    >
      <template v-if="showAction" #actions>
        <EButton
          variant="primary"
          size="default"
          class="material-journey-pack-complete__action"
          :disabled="actionDisabled"
          :loading="loading"
          :title="actionTitle"
          @click="emit('confirm')"
        >
          {{ buttonLabel }}
        </EButton>
        <p class="material-journey-pack-complete__hint text-muted">
          {{ hintText }}
        </p>
      </template>
      <template v-else #actions>
        <p class="material-journey-pack-complete__hint text-muted">
          {{ hintText }}
        </p>
      </template>
    </EEmptyState>
  </section>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
