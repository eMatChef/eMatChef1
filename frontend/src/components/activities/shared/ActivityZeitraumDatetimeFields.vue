<template>
  <ActivityOutlinedDatetimeSection :id="usageBlockId" :title="usageSectionTitle" icon="calendar" :required="true">
    <slot name="usage-before" />
    <ActivityDateTimeFields
      v-if="isActivityType"
      v-model:day="usageDay"
      v-model:time-from="usageTimeFrom"
      v-model:time-to="usageTimeTo"
      date-mode="single"
      :department-id="departmentId"
      :disabled="usageDatesLocked"
      :times-locked="usageDatesLocked"
      :show-presets="true"
      :show-markers="true"
      :label-from="usageTimeFromLabel"
      :label-to="usageTimeToLabel"
      :aria-label="t('activities.zeitraum.ariaUsageActivityDay')"
    />
    <ActivityDateTimeFields
      v-else
      v-model:range="usageRange"
      v-model:time-from="usageTimeFrom"
      v-model:time-to="usageTimeTo"
      date-mode="range"
      :department-id="departmentId"
      :disabled="usageDatesLocked"
      :times-locked="usageDatesLocked"
      :show-presets="showDateRangePresetSidebar"
      :show-markers="true"
      :label="usageRangeRowLabelDisplay"
      :label-from="usageTimeFromLabel"
      :label-to="usageTimeToLabel"
      :aria-label="t('activities.zeitraum.ariaUsageRange')"
    />
  </ActivityOutlinedDatetimeSection>

  <ActivityOutlinedDatetimeSection :id="planningBlockId" :title="materialSectionTitle" icon="truck" :required="true">
    <slot name="planning-before" />
    <ActivityDateTimeFields
      v-model:range="matRange"
      v-model:time-from="matStartTime"
      v-model:time-to="matEndTime"
      date-mode="range"
      :department-id="departmentId"
      :disabled="planningDatesLocked"
      :times-locked="planningDatesLocked"
      :show-presets="showDateRangePresetSidebar"
      :show-markers="true"
      :blocked-usage-range="materialTimesBlockedUsage"
      :label-from="materialTimeFromLabel"
      :label-to="materialTimeToLabel"
      :aria-label="t('activities.zeitraum.ariaMaterialRange')"
    />
    <slot name="planning-after" />
  </ActivityOutlinedDatetimeSection>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityApiType } from '@/api/activities'
import ActivityDateTimeFields from '@/components/activities/wizard/ActivityDateTimeFields.vue'
import ActivityOutlinedDatetimeSection from '@/components/activities/wizard/ActivityOutlinedDatetimeSection.vue'

const props = withDefaults(
  defineProps<{
    activityType: ActivityApiType
    departmentId: string
    teleportTo?: string
    usageDatesLocked?: boolean
    planningDatesLocked?: boolean
    usageBlockId?: string
    planningBlockId?: string
    usageRangeRowLabel?: string
    materialTimesBlockedUsage?: { start: Date; end: Date } | null
  }>(),
  {
    teleportTo: 'body',
    usageDatesLocked: false,
    planningDatesLocked: false,
    materialTimesBlockedUsage: null,
    usageBlockId: 'activity-usage-block',
    planningBlockId: 'activity-planning-block',
  },
)

const { t } = useI18n()

const usageDay = defineModel<Date | null>('usageDay', { required: true })
const usageRange = defineModel<[Date, Date] | null>('usageRange', { required: true })
const usageTimeFrom = defineModel<Date | null>('usageTimeFrom', { required: true })
const usageTimeTo = defineModel<Date | null>('usageTimeTo', { required: true })
const matRange = defineModel<[Date, Date] | null>('matRange', { required: true })
const matStartTime = defineModel<Date | null>('matStartTime', { required: true })
const matEndTime = defineModel<Date | null>('matEndTime', { required: true })

const isActivityType = computed(() => props.activityType === 'activity')

const showDateRangePresetSidebar = computed(
  () => props.activityType === 'camp' || props.activityType === 'event',
)

const usageRangeRowLabelDisplay = computed(() => {
  const v = props.usageRangeRowLabel
  if (v === '') return ''
  if (v != null && v !== '') return v
  return t('activities.zeitraum.usageRangeDefault')
})

const usageSectionTitle = computed(() => {
  switch (props.activityType) {
    case 'camp':
      return t('activities.zeitraum.usageCamp')
    case 'event':
      return t('activities.zeitraum.usageEvent')
    case 'external':
      return t('activities.zeitraum.usageExternal')
    default:
      return t('activities.zeitraum.usageActivity')
  }
})

const materialSectionTitle = computed(() => {
  switch (props.activityType) {
    case 'camp':
    case 'event':
      return t('activities.zeitraum.materialCampEvent')
    case 'external':
      return t('activities.zeitraum.materialExternal')
    default:
      return t('activities.zeitraum.materialActivity')
  }
})

const usageTimeFromLabel = computed(() =>
  props.activityType === 'camp' ? t('activities.zeitraum.timeStart') : t('activities.zeitraum.timeFrom'),
)
const usageTimeToLabel = computed(() =>
  props.activityType === 'camp' ? t('activities.zeitraum.timeEnd') : t('activities.zeitraum.timeTo'),
)
const materialTimeFromLabel = computed(() =>
  props.activityType === 'camp' ? t('activities.zeitraum.materialPickup') : t('activities.zeitraum.pickup'),
)
const materialTimeToLabel = computed(() =>
  props.activityType === 'camp' ? t('activities.zeitraum.materialReturnCamp') : t('activities.zeitraum.return'),
)
</script>
