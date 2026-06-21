<template>
  <div class="activity-datetime-host wish-period-host">
    <ActivityOutlinedDatetimeSection :title="title" icon="calendar" :required="required">
      <ActivityDateTimeFields
        v-model:range="dateRange"
        v-model:time-from="timeFrom"
        v-model:time-to="timeTo"
        date-mode="range"
        :department-id="departmentId"
        :show-presets="true"
        :show-markers="true"
        preset-mode="fixed-periods"
        :label-from="t('activities.zeitraum.timeStart')"
        :label-to="t('activities.zeitraum.timeEnd')"
        :aria-label="title"
      />
    </ActivityOutlinedDatetimeSection>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ActivityOutlinedDatetimeSection from '@/components/activities/wizard/ActivityOutlinedDatetimeSection.vue'
import ActivityDateTimeFields from '@/components/activities/wizard/ActivityDateTimeFields.vue'
import { combineDayAndTime, startOfLocalDay } from '@/utils/activityDateTimeParts'

defineProps<{
  title: string
  departmentId: string
  required?: boolean
}>()

const { t } = useI18n()

const startAt = ref<Date | null>(null)
const endAt = ref<Date | null>(null)

const dateRange = computed({
  get: (): [Date, Date] | null => {
    if (!startAt.value || !endAt.value) return null
    return [startOfLocalDay(startAt.value), startOfLocalDay(endAt.value)]
  },
  set: (v: [Date, Date] | null) => {
    if (!v || v.length < 2) {
      startAt.value = null
      endAt.value = null
      return
    }
    const [dStart, dEnd] = v
    const tStart = startAt.value ?? defaultQuarterTime(dStart, 9, 0)
    const tEnd = endAt.value ?? defaultQuarterTime(dEnd, 17, 0)
    startAt.value = combineDayAndTime(dStart, tStart)
    endAt.value = combineDayAndTime(dEnd, tEnd)
  },
})

const timeFrom = computed({
  get: () => startAt.value,
  set: (v: Date | null) => {
    if (!v || !startAt.value) return
    startAt.value = combineDayAndTime(startOfLocalDay(startAt.value), v)
  },
})

const timeTo = computed({
  get: () => endAt.value,
  set: (v: Date | null) => {
    if (!v || !endAt.value) return
    endAt.value = combineDayAndTime(startOfLocalDay(endAt.value), v)
  },
})

function defaultQuarterTime(day: Date, hour: number, minute: number): Date {
  return new Date(day.getFullYear(), day.getMonth(), day.getDate(), hour, minute, 0, 0)
}

function getRange(): { from: string; to: string } | null {
  if (!startAt.value || !endAt.value) return null
  return { from: startAt.value.toISOString(), to: endAt.value.toISOString() }
}

function setRange(from: string | null | undefined, to: string | null | undefined) {
  startAt.value = from ? new Date(from) : null
  endAt.value = to ? new Date(to) : null
  if (startAt.value && Number.isNaN(startAt.value.getTime())) startAt.value = null
  if (endAt.value && Number.isNaN(endAt.value.getTime())) endAt.value = null
}

function reset() {
  startAt.value = null
  endAt.value = null
}

defineExpose({ getRange, reset, setRange })
</script>

<style scoped>
.wish-period-host {
  max-width: 720px;
}
</style>

<style>
@import '@/styles/components/activity-datetime-field.css';
@import '@/styles/components/activity-datetime-layout.css';
</style>
