<template>
  <VueDatePicker
    :model-value="modelValue"
    :disabled="disabled"
    class="activity-date-field"
    :locale="datepickerLocale"
    format="dd.MM.yyyy"
    :enable-time-picker="false"
    :preset-dates="presetDates"
    :min-date="minDate"
    :markers="holidayMarkers"
    position="left"
    auto-apply
    :clearable="false"
    :teleport="teleportTo"
    :time-config="{ enableTimePicker: false }"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import VueDatePicker from '@vuepic/vue-datepicker'
import type { DatePickerMarker } from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import '@/styles/ui/vue-datepicker-emc.css'
import { getDepartmentCalendarMarkers, type CalendarMarkerDto } from '@/api/calendarMarkers'
import { nextSaturdayFromToday } from '@/utils/activityPlanningFromDefaults'
import { startOfLocalDay, startOfToday, swissHolidayDatePickerMarkers } from '@/utils/swissMovableFeasts'

const props = withDefaults(
  defineProps<{
    modelValue: Date | null
    departmentId?: string | null
    /** z. B. gesperrt, solange Materialpositionen im Wizard (vgl. v4.01) */
    disabled?: boolean
    /** z. B. `body` in der Aktivitäts-Detailansicht (kein Wizard-Modal) */
    teleportTo?: string
  }>(),
  { departmentId: null, disabled: false, teleportTo: '.material-wizard-modal' },
)

const { t, locale } = useI18n()

const datepickerLocale = computed(() => (String(locale.value ?? '').startsWith('de') ? 'de' : 'en'))

const minDate = computed(() => startOfToday())

/** Wie bei Datumsbereich: Schnellauswahl links im Kalender (Typ „Aktivität“ = ein Tag) */
const presetDates = computed(() => {
  const today = startOfToday()
  const sat = startOfLocalDay(nextSaturdayFromToday())
  return [
    { label: t('activities.datePresets.today'), value: today },
    { label: t('activities.datePresets.nextSaturday'), value: sat },
  ]
})

const fcalSchoolMarkers = ref<CalendarMarkerDto[]>([])

async function refreshFcalSchoolMarkers(): Promise<void> {
  if (!props.departmentId) {
    fcalSchoolMarkers.value = []
    return
  }
  try {
    const y = new Date().getFullYear()
    const res = await getDepartmentCalendarMarkers(props.departmentId, [y - 1, y, y + 1, y + 2])
    fcalSchoolMarkers.value = res.source === 'fcal' ? res.markers : []
  } catch {
    fcalSchoolMarkers.value = []
  }
}

watch(() => props.departmentId, refreshFcalSchoolMarkers, { immediate: true })

const holidayMarkers = computed<DatePickerMarker[]>(() => {
  const y = new Date().getFullYear()
  const base = swissHolidayDatePickerMarkers(y - 1, y + 6)
  const school: DatePickerMarker[] = fcalSchoolMarkers.value.map((m) => ({
    date: m.date,
    type: 'dot',
    color: '#2563eb',
    tooltip: [{ text: t('activities.dateRangePicker.schoolHolidayTooltip', { label: m.label }) }],
  }))
  return [...base, ...school]
})

defineEmits<{
  'update:modelValue': [value: Date | null]
}>()
</script>

<style scoped>
.activity-date-field {
  width: 100%;
}

.activity-date-field :deep(.dp__input) {
  width: 100%;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  color: #111827;
  background: #fff;
}

.activity-date-field :deep(.dp__input:focus) {
  outline: none;
  border-color: var(--emc-brand-accent, #059669);
  box-shadow: 0 0 0 3px rgb(5 150 105 / 18%);
}

.activity-date-field :deep(.dp--preset-range) {
  max-width: 160px;
  font-size: 12px;
  font-weight: 500;
  white-space: normal;
  line-height: 1.25;
}
</style>
