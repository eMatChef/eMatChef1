<template>
  <ActivityOutlinedDatetimeSection :id="usageBlockId" :title="usageSectionTitle" icon="calendar" :required="true">
    <slot name="usage-before" />
    <template v-if="isActivityType">
      <ActivityPillDateTimeRow
        label=""
        :label-from="usageTimeFromLabel"
        :label-to="usageTimeToLabel"
        aria-label="Aktivität: Datum, Start- und Endzeit"
      >
        <template #date>
          <ActivityDateField
            v-model="usageDay"
            :department-id="departmentId"
            :disabled="usageDatesLocked"
            :teleport-to="teleportTo"
          />
        </template>
        <template #timeFrom>
          <ActivityTimeField v-model="usageTimeFrom" :locked="usageDatesLocked" />
        </template>
        <template #timeTo>
          <ActivityTimeField v-model="usageTimeTo" :locked="usageDatesLocked" />
        </template>
      </ActivityPillDateTimeRow>
    </template>
    <template v-else>
      <ActivityPillDateTimeRow
        :label="usageRangeRowLabel"
        :label-from="usageTimeFromLabel"
        :label-to="usageTimeToLabel"
        aria-label="Nutzung: Datumsbereich, Start- und Endzeit"
      >
        <template #date>
          <ActivityDateRangeField
            v-model="usageRange"
            :department-id="departmentId"
            :show-preset-sidebar="showDateRangePresetSidebar"
            :disabled="usageDatesLocked"
            :teleport-to="teleportTo"
          />
        </template>
        <template #timeFrom>
          <ActivityTimeField v-model="usageTimeFrom" :locked="usageDatesLocked" />
        </template>
        <template #timeTo>
          <ActivityTimeField v-model="usageTimeTo" :locked="usageDatesLocked" />
        </template>
      </ActivityPillDateTimeRow>
    </template>
  </ActivityOutlinedDatetimeSection>

  <ActivityOutlinedDatetimeSection :id="planningBlockId" :title="materialSectionTitle" icon="truck" :required="true">
    <slot name="planning-before" />
    <ActivityPillDateTimeRow
      label=""
      :label-from="materialTimeFromLabel"
      :label-to="materialTimeToLabel"
      aria-label="Material: Abhol- und Rückgabedatum sowie Zeiten"
    >
      <template #date>
        <ActivityDateRangeField
          v-model="matRange"
          :department-id="departmentId"
          :show-preset-sidebar="showDateRangePresetSidebar"
          :disabled="planningDatesLocked"
          :teleport-to="teleportTo"
        />
      </template>
      <template #timeFrom>
        <ActivityTimeField
          v-model="matStartTime"
          :locked="planningDatesLocked"
          :blocked-usage-range="materialTimesBlockedUsage"
        />
      </template>
      <template #timeTo>
        <ActivityTimeField
          v-model="matEndTime"
          :locked="planningDatesLocked"
          :blocked-usage-range="materialTimesBlockedUsage"
        />
      </template>
    </ActivityPillDateTimeRow>
    <slot name="planning-after" />
  </ActivityOutlinedDatetimeSection>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ActivityApiType } from '@/api/activities'
import ActivityDateField from '@/components/activities/wizard/ActivityDateField.vue'
import ActivityDateRangeField from '@/components/activities/wizard/ActivityDateRangeField.vue'
import ActivityOutlinedDatetimeSection from '@/components/activities/wizard/ActivityOutlinedDatetimeSection.vue'
import ActivityPillDateTimeRow from '@/components/activities/wizard/ActivityPillDateTimeRow.vue'
import ActivityTimeField from '@/components/activities/wizard/ActivityTimeField.vue'

const props = withDefaults(
  defineProps<{
    activityType: ActivityApiType
    departmentId: string
    /** Datepicker-Overlay: Wizard-Modal oder `body` in der Detailansicht */
    teleportTo?: string
    /** Sperrt Nutzungsdatum und -zeiten (z. B. sobald Materialpositionen existieren). */
    usageDatesLocked?: boolean
    /** Sperrt Abhol-/Rückgabe (Material-Planung); getrennt von Nutzung. */
    planningDatesLocked?: boolean
    showDateRangePresetSidebar: boolean
    usageBlockId?: string
    planningBlockId?: string
    /** Sichtbares Label bei Nutzung als Datumsbereich (nicht Typ „Aktivität“) */
    usageRangeRowLabel?: string
    /**
     * Nutzungsintervall: Abhol-/Rückgabe-Uhrzeiten in diesen Zeiten sind in den Dropdowns nicht wählbar.
     */
    materialTimesBlockedUsage?: { start: Date; end: Date } | null
  }>(),
  {
    teleportTo: '.material-wizard-modal',
    usageDatesLocked: false,
    planningDatesLocked: false,
    materialTimesBlockedUsage: null,
    usageBlockId: 'activity-usage-block',
    planningBlockId: 'activity-planning-block',
    usageRangeRowLabel: 'Nutzungszeitraum & Zeiten',
  },
)

const usageDay = defineModel<Date | null>('usageDay', { required: true })
const usageRange = defineModel<[Date, Date] | null>('usageRange', { required: true })
const usageTimeFrom = defineModel<Date | null>('usageTimeFrom', { required: true })
const usageTimeTo = defineModel<Date | null>('usageTimeTo', { required: true })
const matRange = defineModel<[Date, Date] | null>('matRange', { required: true })
const matStartTime = defineModel<Date | null>('matStartTime', { required: true })
const matEndTime = defineModel<Date | null>('matEndTime', { required: true })

const isActivityType = computed(() => props.activityType === 'activity')

const usageSectionTitle = computed(() => {
  switch (props.activityType) {
    case 'camp':
      return 'Lager findet statt'
    case 'event':
      return 'Event findet statt'
    case 'external':
      return 'Zeitraum'
    default:
      return 'Datum und Zeit der Aktivität'
  }
})

const materialSectionTitle = computed(() => {
  switch (props.activityType) {
    case 'camp':
    case 'event':
      return 'Material abholen & zurückbringen'
    case 'external':
      return 'Material – Abholung & Rückgabe'
    default:
      return 'Datum und Zeit der Abholung / Rückgabe'
  }
})

const usageTimeFromLabel = computed(() => (props.activityType === 'camp' ? 'Start' : 'Von'))
const usageTimeToLabel = computed(() => (props.activityType === 'camp' ? 'Ende' : 'Bis'))
const materialTimeFromLabel = computed(() => (props.activityType === 'camp' ? 'Material abholen' : 'Abholung'))
const materialTimeToLabel = computed(() => (props.activityType === 'camp' ? 'Material zurückbringen' : 'Rückgabe'))
</script>
