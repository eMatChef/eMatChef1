<template>
  <div class="sandbox-activity-datetime">
    <p class="text-caption text-medium-emphasis mb-4">
      {{ t('devSandbox.activityDatetime.lead') }}
    </p>

    <div class="sandbox-activity-datetime__block">
      <p class="text-caption font-weight-medium mb-3">
        {{ smAndUp ? t('devSandbox.activityDatetime.desktopLabel') : t('devSandbox.activityDatetime.mobileLabel') }}
      </p>
      <ActivityDatetimeSandboxFields
        v-model:day="day"
        v-model:range="range"
        v-model:time-from="timeFrom"
        v-model:time-to="timeTo"
        layout="auto"
      />
    </div>

    <div v-if="smAndUp" class="sandbox-activity-datetime__block mt-6">
      <p class="text-caption font-weight-medium mb-3">
        {{ t('devSandbox.activityDatetime.mobilePreviewLabel') }}
      </p>
      <div class="sandbox-material-dt__mobile-frame">
        <ActivityDatetimeSandboxFields
          v-model:day="day"
          v-model:range="range"
          v-model:time-from="timeFrom"
          v-model:time-to="timeTo"
          layout="stacked"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSmAndUp } from '@/composables/useSmAndUp'
import { snapDateToQuarterHour } from '@/utils/activityPlanningFromDefaults'
import { startOfToday } from '@/utils/swissMovableFeasts'
import ActivityDatetimeSandboxFields from '@/views/dev/ActivityDatetimeSandboxFields.vue'
import '@/styles/components/sandbox-material-mobile-list.css'

const { t } = useI18n()
const smAndUp = useSmAndUp()

const today = startOfToday()

function timeOnDay(h: number, m: number): Date {
  const d = new Date(today.getFullYear(), today.getMonth(), today.getDate(), h, m, 0, 0)
  return snapDateToQuarterHour(d)
}

function addDays(day: Date, n: number): Date {
  const d = new Date(day.getTime())
  d.setDate(d.getDate() + n)
  return d
}

const day = ref<Date | null>(today)
const range = ref<[Date, Date] | null>([today, addDays(today, 6)])
const timeFrom = ref<Date | null>(timeOnDay(9, 0))
const timeTo = ref<Date | null>(timeOnDay(17, 0))
</script>

<style scoped>
.sandbox-activity-datetime__block {
  padding: 16px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fafafa;
}
</style>
