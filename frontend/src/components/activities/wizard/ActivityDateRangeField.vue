<template>
  <VDateInput
    :model-value="rangeModel"
    class="activity-date-range-field activity-v-date-input e-form-field"
    variant="outlined"
    density="compact"
    hide-details
    prepend-inner-icon="mdi-calendar-range"
    multiple="range"
    :disabled="disabled"
    :min="minDate"
    :events="datePickerEvents"
    event-color="primary"
    color="primary"
    :display-format="displayFormatActivityRange"
    :placeholder="t('activities.dateRangePicker.placeholder')"
    :menu-props="menuProps"
    hide-actions
    :picker-props="{ showAdjacentMonths: true }"
    @update:model-value="onRangeUpdate"
  />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { VDateInput } from 'vuetify/labs/VDateInput'
import { useActivityDateMenuProps } from '@/composables/useActivityDateMenuProps'
import { useActivityDatePickerEvents } from '@/composables/useActivityDatePickerEvents'
import { formatActivityDateRangeDe } from '@/utils/activityDateIso'
import { startOfToday } from '@/utils/swissMovableFeasts'

const props = withDefaults(
  defineProps<{
    modelValue: [Date, Date] | null
    /** Legacy: Preset-Sidebar (@vuepic) — VDatePicker-Follow-up in separatem Schritt */
    showPresetSidebar?: boolean
    departmentId?: string | null
    disabled?: boolean
    teleportTo?: string
  }>(),
  { showPresetSidebar: true, departmentId: null, disabled: false, teleportTo: '.material-wizard-modal' },
)

const emit = defineEmits<{
  'update:modelValue': [value: [Date, Date] | null]
}>()

const { t } = useI18n()
const minDate = computed(() => startOfToday())
const menuProps = useActivityDateMenuProps(() => props.teleportTo)
const { datePickerEvents } = useActivityDatePickerEvents(() => props.departmentId)

function displayFormatActivityRange(value: unknown): string {
  if (!Array.isArray(value) || value.length < 2) return ''
  const a = value[0] instanceof Date ? value[0] : new Date(String(value[0]))
  const b = value[1] instanceof Date ? value[1] : new Date(String(value[1]))
  if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime())) return ''
  const sorted: [Date, Date] = a.getTime() <= b.getTime() ? [a, b] : [b, a]
  return formatActivityDateRangeDe(sorted)
}

function onRangeUpdate(value: Date | Date[] | null) {
  if (!value || !Array.isArray(value) || value.length < 2) {
    emit('update:modelValue', null)
    return
  }
  const sorted = [...value].sort((a, b) => a.getTime() - b.getTime())
  emit('update:modelValue', [sorted[0], sorted[1]])
}

const rangeModel = computed((): Date[] | null => {
  if (!props.modelValue) return null
  return [props.modelValue[0], props.modelValue[1]]
})
</script>

<style scoped>
.activity-date-range-field {
  width: 100%;
}
</style>
