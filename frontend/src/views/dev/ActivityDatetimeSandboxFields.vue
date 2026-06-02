<template>
  <!-- Produktions-API: ActivityDateTimeFields (wie Create-Wizard über ActivityZeitraumDatetimeFields). -->
  <div class="activity-datetime-host sandbox-activity-datetime-fields" :class="datetimeHostClasses">
    <ActivityOutlinedDatetimeSection
      :title="t('devSandbox.activityDatetime.usageSingle')"
      icon="calendar"
    >
      <ActivityDateTimeFields
        v-model:day="dayModel"
        v-model:time-from="timeFromModel"
        v-model:time-to="timeToModel"
        date-mode="single"
        :department-id="departmentId"
        :layout="layout"
        :show-presets="true"
        :show-markers="true"
        :label-from="t('devSandbox.activityDatetime.timeFrom')"
        :label-to="t('devSandbox.activityDatetime.timeTo')"
        :aria-label="t('devSandbox.activityDatetime.usageSingle')"
      />
    </ActivityOutlinedDatetimeSection>

    <ActivityOutlinedDatetimeSection
      :title="t('devSandbox.activityDatetime.usageRange')"
      icon="calendar"
      class="sandbox-activity-datetime-fields__range-block"
    >
      <ActivityDateTimeFields
        v-model:range="rangeModel"
        v-model:time-from="timeFromModel"
        v-model:time-to="timeToModel"
        date-mode="range"
        :department-id="departmentId"
        :layout="layout"
        :show-presets="true"
        :show-markers="true"
        :label-from="t('devSandbox.activityDatetime.timeFrom')"
        :label-to="t('devSandbox.activityDatetime.timeTo')"
        :aria-label="t('devSandbox.activityDatetime.usageRange')"
      />
    </ActivityOutlinedDatetimeSection>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import ActivityDateTimeFields from '@/components/activities/wizard/ActivityDateTimeFields.vue'
import ActivityOutlinedDatetimeSection from '@/components/activities/wizard/ActivityOutlinedDatetimeSection.vue'
import { useDisplayHostClasses } from '@/composables/useDisplayHostClasses'
import '@/styles/components/activity-datetime-field.css'
import '@/styles/components/activity-datetime-layout.css'

const datetimeHostClasses = useDisplayHostClasses('activity-datetime-host')

const props = withDefaults(
  defineProps<{
    day: Date | null
    range: [Date, Date] | null
    timeFrom: Date | null
    timeTo: Date | null
    layout?: 'auto' | 'pill' | 'stacked'
  }>(),
  { layout: 'auto' },
)

const emit = defineEmits<{
  'update:day': [value: Date | null]
  'update:range': [value: [Date, Date] | null]
  'update:timeFrom': [value: Date | null]
  'update:timeTo': [value: Date | null]
}>()

const { t } = useI18n()
const route = useRoute()

const departmentId = computed(
  () => (route.params.departmentId as string | undefined) ?? null,
)

const dayModel = computed({
  get: () => props.day,
  set: (v) => emit('update:day', v),
})

const rangeModel = computed({
  get: () => props.range,
  set: (v) => emit('update:range', v),
})

const timeFromModel = computed({
  get: () => props.timeFrom,
  set: (v) => emit('update:timeFrom', v),
})

const timeToModel = computed({
  get: () => props.timeTo,
  set: (v) => emit('update:timeTo', v),
})
</script>

<style scoped>
.sandbox-activity-datetime-fields {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sandbox-activity-datetime-fields__range-block {
  margin-top: 0;
}
</style>
