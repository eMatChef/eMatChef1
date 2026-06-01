<template>
  <VDateInput
    :model-value="modelValue"
    class="activity-date-field activity-v-date-input e-form-field"
    variant="outlined"
    density="compact"
    hide-details
    prepend-inner-icon="mdi-calendar"
    :disabled="disabled"
    :min="minDate"
    :events="datePickerEvents"
    event-color="primary"
    color="primary"
    :display-format="displayFormatActivityDate"
    :menu-props="menuProps"
    hide-actions
    @update:model-value="onUpdate"
  />
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { VDateInput } from 'vuetify/labs/VDateInput'
import { useActivityDateMenuProps } from '@/composables/useActivityDateMenuProps'
import { useActivityDatePickerEvents } from '@/composables/useActivityDatePickerEvents'
import { formatActivityDateDe } from '@/utils/activityDateIso'
import { startOfToday } from '@/utils/swissMovableFeasts'

const props = withDefaults(
  defineProps<{
    modelValue: Date | null
    departmentId?: string | null
    disabled?: boolean
    teleportTo?: string
  }>(),
  { departmentId: null, disabled: false, teleportTo: '.material-wizard-modal' },
)

const emit = defineEmits<{
  'update:modelValue': [value: Date | null]
}>()

const minDate = computed(() => startOfToday())
const menuProps = useActivityDateMenuProps(() => props.teleportTo)
const { datePickerEvents } = useActivityDatePickerEvents(() => props.departmentId)

function displayFormatActivityDate(value: unknown): string {
  if (value instanceof Date) return formatActivityDateDe(value)
  if (value == null) return ''
  const d = new Date(String(value))
  return formatActivityDateDe(Number.isNaN(d.getTime()) ? null : d)
}

function onUpdate(value: Date | Date[] | null) {
  if (value == null || Array.isArray(value)) {
    emit('update:modelValue', Array.isArray(value) && value[0] ? value[0] : null)
    return
  }
  emit('update:modelValue', value)
}
</script>

<style scoped>
.activity-date-field {
  width: 100%;
}
</style>
