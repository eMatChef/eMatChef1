<template>
  <ActivityPillDateTimeRow
    v-if="usePillLayout"
    :label="label"
    :label-from="labelFrom"
    :label-to="labelTo"
    :aria-label="ariaLabel"
  >
    <template #date>
      <slot name="date" />
    </template>
    <template #timeFrom>
      <slot name="timeFrom" />
    </template>
    <template #timeTo>
      <slot name="timeTo" />
    </template>
  </ActivityPillDateTimeRow>

  <div
    v-else
    class="activity-datetime-mobile"
    role="group"
    :aria-label="ariaLabel || label || undefined"
  >
    <span v-if="label != null && label !== ''" class="field-label">{{ label }}</span>
    <div class="activity-datetime-mobile__date">
      <slot name="date" />
    </div>
    <div class="activity-datetime-mobile__times">
      <div class="activity-datetime-mobile__time-slot">
        <span class="activity-datetime-mobile__time-lbl">{{ labelFrom }}</span>
        <slot name="timeFrom" />
      </div>
      <div class="activity-datetime-mobile__time-slot">
        <span class="activity-datetime-mobile__time-lbl">{{ labelTo }}</span>
        <slot name="timeTo" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useSmAndUp } from '@/composables/useSmAndUp'
import ActivityPillDateTimeRow from './ActivityPillDateTimeRow.vue'

const props = withDefaults(
  defineProps<{
    label?: string
    labelFrom: string
    labelTo: string
    ariaLabel?: string
    /** auto: Pill ab sm (600px), darunter 2 Zeilen */
    layout?: 'auto' | 'pill' | 'stacked'
  }>(),
  { layout: 'auto' },
)

const smAndUp = useSmAndUp()

const usePillLayout = computed(() => {
  if (props.layout === 'pill') return true
  if (props.layout === 'stacked') return false
  return smAndUp.value
})
</script>
