<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'

const props = defineProps<{
  row: MaterialJourneyTaskRow
  moving: boolean
  readonly: boolean
}>()

const emit = defineEmits<{
  activate: []
}>()

const { t } = useI18n()

const statusIcon = computed(() => {
  if (props.row.isDone) return '✓'
  if (props.row.openQty > 0 && props.row.doneQty > 0) return '◐'
  return '○'
})

const statusClass = computed(() => {
  if (props.row.isDone) return 'material-journey-task-row__status--done'
  if (props.row.openQty > 0 && props.row.doneQty > 0) return 'material-journey-task-row__status--partial'
  return 'material-journey-task-row__status--open'
})

const kindIcon = computed(() => {
  if (props.row.kind === 'crate') return 'mdi-package-variant'
  if (props.row.kind === 'combo') return 'mdi-set-merge'
  return null
})

const qtyLabel = computed(() => {
  if (props.row.kind === 'crate' && props.row.isDone) {
    return t('activities.materialJourney.row.crateDone')
  }
  if (props.row.isOpen) {
    return t('activities.materialJourney.row.openQty', { count: props.row.openQty })
  }
  return t('activities.materialJourney.row.doneQty', { count: props.row.doneQty })
})

const isInteractive = computed(() => props.row.canMove || props.row.canOpenSheet)

function badgeLabel(badge: MaterialJourneyTaskRow['badges'][number]): string {
  if (badge === 'physical_combo') return t('activities.materialJourney.badge.set')
  if (badge === 'crate') return t('activities.materialJourney.badge.crate')
  if (badge === 'consumable') return t('activities.materialJourney.badge.consumable')
  return t('activities.materialJourney.badge.js')
}

function onActivate(): void {
  emit('activate')
}
</script>

<template>
  <button
    type="button"
    class="material-journey-task-row section-card"
    :class="{
      'material-journey-task-row--readonly': readonly || !isInteractive,
      'material-journey-task-row--moving': moving,
      'material-journey-task-row--crate': row.kind === 'crate',
      'material-journey-task-row--combo': row.kind === 'combo',
    }"
    :disabled="moving"
    @click="onActivate"
  >
    <span class="material-journey-task-row__status" :class="statusClass" aria-hidden="true">
      {{ statusIcon }}
    </span>
    <span v-if="kindIcon" class="material-journey-task-row__kind-icon" aria-hidden="true">
      <v-icon :icon="kindIcon" size="20" />
    </span>
    <span class="material-journey-task-row__body">
      <span class="material-journey-task-row__title">{{ row.title }}</span>
      <span v-if="row.subtitle" class="material-journey-task-row__subtitle text-muted">
        {{ row.subtitle }}
      </span>
      <span v-if="row.badges.length" class="material-journey-task-row__badges">
        <span v-for="badge in row.badges" :key="badge" class="material-journey-task-row__badge">
          {{ badgeLabel(badge) }}
        </span>
      </span>
    </span>
    <span class="material-journey-task-row__qty">{{ qtyLabel }}</span>
  </button>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
