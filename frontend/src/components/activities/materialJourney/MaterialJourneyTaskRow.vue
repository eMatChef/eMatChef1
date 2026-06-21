<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PackMoveControls from '@/components/activities/PackMoveControls.vue'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'

const props = defineProps<{
  row: MaterialJourneyTaskRow
  moving: boolean
  readonly: boolean
  showMoveBack?: boolean
  moveBackQty?: number
  showMoveForward?: boolean
  moveForwardQty?: number
}>()

const emit = defineEmits<{
  activate: []
  moveBack: [qty: number]
  'update:moveBackQty': [qty: number]
  moveForward: [qty: number]
  'update:moveForwardQty': [qty: number]
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

const qtyLabel = computed(() => {
  if (props.showMoveBack && props.row.isDone) {
    return t('activities.materialJourney.row.transportedQty', { count: props.row.doneQty })
  }
  if (props.row.isOpen) {
    return t('activities.materialJourney.row.openQty', { count: props.row.openQty })
  }
  return t('activities.materialJourney.row.doneQty', { count: props.row.doneQty })
})

const showMoveBackControls = computed(
  () => Boolean(props.showMoveBack) && props.row.canMoveBack && !props.readonly,
)

const showMoveForwardControls = computed(
  () =>
    Boolean(props.showMoveForward) &&
    props.row.kind === 'loose' &&
    props.row.canMove &&
    props.row.isOpen &&
    !props.readonly,
)

const effectiveMoveBackQty = computed(
  () => props.moveBackQty ?? props.row.maxMoveBackQty,
)

const effectiveMoveForwardQty = computed(
  () => props.moveForwardQty ?? props.row.maxForwardQty,
)

const hasInlineControls = computed(
  () => showMoveForwardControls.value || showMoveBackControls.value,
)

const isRowClickable = computed(
  () => !props.readonly && !hasInlineControls.value && props.row.canMove,
)

function onRowClick(): void {
  if (!isRowClickable.value) return
  emit('activate')
}
</script>

<template>
  <div
    class="material-journey-task-row section-card"
    :class="{
      'material-journey-task-row--readonly': readonly || !isRowClickable,
      'material-journey-task-row--moving': moving,
      'material-journey-task-row--inline-actions': hasInlineControls,
    }"
    :role="isRowClickable ? 'button' : undefined"
    :tabindex="isRowClickable ? 0 : undefined"
    @click="onRowClick"
    @keyup.enter="onRowClick"
  >
    <span class="material-journey-task-row__status" :class="statusClass" aria-hidden="true">
      {{ statusIcon }}
    </span>

    <span class="material-journey-task-row__body">
      <span class="material-journey-task-row__title">{{ row.title }}</span>
      <span v-if="row.subtitle" class="material-journey-task-row__subtitle text-muted">
        {{ row.subtitle }}
      </span>
      <span v-if="row.badges.length" class="material-journey-task-row__badges">
        <span
          v-for="badge in row.badges"
          :key="badge"
          class="material-journey-task-row__badge"
          :class="{ 'material-journey-task-row__badge--pack-crate': badge === 'pack_crate' }"
        >
          {{
            badge === 'physical_combo'
              ? t('activities.materialJourney.badge.set')
              : badge === 'crate'
                ? t('activities.materialJourney.badge.crate')
                : badge === 'pack_crate'
                  ? t('activities.materialJourney.badge.packCrate')
                  : badge === 'consumable'
                    ? t('activities.materialJourney.badge.consumable')
                    : t('activities.materialJourney.badge.js')
          }}
        </span>
      </span>
      <span v-if="row.packCrateHint" class="material-journey-task-row__crate-flag">
        {{ row.packCrateHint }}
      </span>
    </span>

    <div class="material-journey-task-row__trailing" @click.stop>
      <span class="material-journey-task-row__qty">{{ qtyLabel }}</span>
      <PackMoveControls
        v-if="showMoveForwardControls"
        direction="forward"
        :qty="effectiveMoveForwardQty"
        :max="row.maxForwardQty"
        :disabled="moving"
        :forward-title="t('activities.materialJourney.moveForward.action')"
        @update:qty="emit('update:moveForwardQty', $event)"
        @move="emit('moveForward', $event)"
      />
      <PackMoveControls
        v-if="showMoveBackControls"
        direction="back"
        :qty="effectiveMoveBackQty"
        :max="row.maxMoveBackQty"
        :disabled="moving"
        :back-title="t('activities.materialJourney.moveBack.action')"
        @update:qty="emit('update:moveBackQty', $event)"
        @move="emit('moveBack', $event)"
      />
    </div>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
