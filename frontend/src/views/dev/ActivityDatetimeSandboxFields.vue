<template>
  <div class="activity-datetime-host sandbox-activity-datetime-fields">
    <ActivityOutlinedDatetimeSection
      :title="t('devSandbox.activityDatetime.usageSingle')"
      icon="calendar"
    >
      <ActivityResponsiveDateTimeRow
        label=""
        :layout="layout"
        :label-from="t('devSandbox.activityDatetime.timeFrom')"
        :label-to="t('devSandbox.activityDatetime.timeTo')"
        :aria-label="t('devSandbox.activityDatetime.usageSingle')"
      >
        <template #date>
          <ActivityDateField v-model="dayModel" :department-id="departmentId" />
        </template>
        <template #timeFrom>
          <ActivityTimeField v-model="timeFromModel" />
        </template>
        <template #timeTo>
          <ActivityTimeField v-model="timeToModel" />
        </template>
      </ActivityResponsiveDateTimeRow>
    </ActivityOutlinedDatetimeSection>

    <ActivityOutlinedDatetimeSection
      :title="t('devSandbox.activityDatetime.usageRange')"
      icon="calendar"
      class="sandbox-activity-datetime-fields__range-block"
    >
      <ActivityResponsiveDateTimeRow
        label=""
        :layout="layout"
        :label-from="t('devSandbox.activityDatetime.timeFrom')"
        :label-to="t('devSandbox.activityDatetime.timeTo')"
        :aria-label="t('devSandbox.activityDatetime.usageRange')"
      >
        <template #date>
          <ActivityDateRangeField
            v-model="rangeModel"
            :department-id="departmentId"
            :show-preset-sidebar="showRangePresets"
          />
        </template>
        <template #timeFrom>
          <ActivityTimeField v-model="timeFromModel" />
        </template>
        <template #timeTo>
          <ActivityTimeField v-model="timeToModel" />
        </template>
      </ActivityResponsiveDateTimeRow>
    </ActivityOutlinedDatetimeSection>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useSmAndUp } from '@/composables/useSmAndUp'
import ActivityDateField from '@/components/activities/wizard/ActivityDateField.vue'
import ActivityDateRangeField from '@/components/activities/wizard/ActivityDateRangeField.vue'
import ActivityOutlinedDatetimeSection from '@/components/activities/wizard/ActivityOutlinedDatetimeSection.vue'
import ActivityResponsiveDateTimeRow from '@/components/activities/wizard/ActivityResponsiveDateTimeRow.vue'
import ActivityTimeField from '@/components/activities/wizard/ActivityTimeField.vue'
import '@/styles/components/activity-datetime-field.css'
import '@/styles/components/activity-datetime-layout.css'

const props = withDefaults(
  defineProps<{
    day: Date | null
    range: [Date, Date] | null
    timeFrom: Date | null
    timeTo: Date | null
    /** auto | pill | stacked — für Mobile-Vorschau im Sandbox-Rahmen */
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
const smAndUp = useSmAndUp()

const departmentId = computed(
  () => (route.params.departmentId as string | undefined) ?? null,
)

const showRangePresets = computed(() => smAndUp.value && props.layout !== 'stacked')

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
