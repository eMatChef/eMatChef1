<template>
  <ActivityResponsiveDateTimeRow
    :label="label"
    :label-from="labelFrom"
    :label-to="labelTo"
    :aria-label="ariaLabel"
    :layout="layout"
  >
    <template #date>
      <ActivityDateField
        v-if="dateMode === 'single'"
        v-model="dayModel"
        :department-id="departmentId"
        :disabled="disabled"
        :show-presets="showPresets"
        :show-markers="showMarkers"
        :preset-mode="presetMode === 'fixed-periods' ? 'fixed-periods' : 'single'"
      />
      <ActivityDateRangeField
        v-else
        v-model="rangeModel"
        :department-id="departmentId"
        :disabled="disabled"
        :show-presets="showPresets"
        :show-markers="showMarkers"
        :preset-mode="presetMode"
      />
    </template>
    <template v-if="showTime" #timeFrom>
      <ActivityTimeField
        v-model="timeFromModel"
        :locked="timesLocked"
        :blocked-usage-range="blockedUsageRange"
      />
    </template>
    <template v-if="showTime" #timeTo>
      <ActivityTimeField
        v-model="timeToModel"
        :locked="timesLocked"
        :blocked-usage-range="blockedUsageRange"
      />
    </template>
  </ActivityResponsiveDateTimeRow>
</template>

<script setup lang="ts">
import ActivityDateField from './ActivityDateField.vue'
import ActivityDateRangeField from './ActivityDateRangeField.vue'
import ActivityResponsiveDateTimeRow from './ActivityResponsiveDateTimeRow.vue'
import ActivityTimeField from './ActivityTimeField.vue'

export type ActivityDateTimeFieldsDateMode = 'single' | 'range'

const props = withDefaults(
  defineProps<{
    dateMode: ActivityDateTimeFieldsDateMode
    departmentId?: string | null
    /** Schnellauswahl im Kalender-Menü */
    showPresets?: boolean
    /** Punkte/Tooltip im Kalender (Feiertage, Fixe Daten, fcal) */
    showMarkers?: boolean
    /** Von/Bis-Uhrzeit anzeigen */
    showTime?: boolean
    disabled?: boolean
    timesLocked?: boolean
    /** Material-Uhrzeiten: Nutzungsintervall in der Uhr nicht wählbar */
    blockedUsageRange?: { start: Date; end: Date } | null
    /** range = Samstage + Fixe Daten; fixed-periods = nur Fixe Daten */
    presetMode?: 'range' | 'fixed-periods'
    label?: string
    labelFrom: string
    labelTo: string
    ariaLabel?: string
    layout?: 'auto' | 'pill' | 'stacked'
  }>(),
  {
    departmentId: null,
    showPresets: false,
    showMarkers: true,
    showTime: true,
    disabled: false,
    timesLocked: false,
    blockedUsageRange: null,
    presetMode: 'range',
    layout: 'auto',
  },
)

const dayModel = defineModel<Date | null>('day')
const rangeModel = defineModel<[Date, Date] | null>('range')
const timeFromModel = defineModel<Date | null>('timeFrom')
const timeToModel = defineModel<Date | null>('timeTo')
</script>
