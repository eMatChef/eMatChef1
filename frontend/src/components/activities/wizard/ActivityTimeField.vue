<template>
  <VTextField
    class="activity-v-time-picker-field activity-time-field e-form-field"
    variant="outlined"
    :density="density"
    hide-details
    readonly
    prepend-inner-icon="mdi-clock-outline"
    :disabled="isFieldDisabled"
    :model-value="displayValue"
    :focused="menuOpen"
    :aria-label="ariaLabel"
    @click:control="onFieldClick"
    @click:prepend-inner="onFieldClick"
  >
    <VMenu
      v-model="menuOpen"
      activator="parent"
      attach="body"
      :z-index="ACTIVITY_PICKER_MENU_Z_INDEX"
      location="bottom start"
      :close-on-content-click="false"
      :open-on-click="false"
      min-width="0"
      content-class="activity-time-picker-menu"
    >
      <VTimePicker
        v-model="pickerModel"
        v-model:view-mode="pickerViewMode"
        format="24hr"
        color="primary"
        :allowed-hours="allowedHours"
        :allowed-minutes="allowedQuarterMinutes"
        @update:hour="onHourPicked"
        @update:minute="onMinutePicked"
      />
    </VMenu>
  </VTextField>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { VMenu, VTextField, VTimePicker } from 'vuetify/components'
import { ACTIVITY_PICKER_MENU_Z_INDEX } from '@/composables/useActivityDateMenuProps'
import { snapDateToQuarterHour } from '@/utils/activityPlanningFromDefaults'
import { startOfLocalDay } from '@/utils/activityDateTimeParts'
import {
  isInstantInsideClosedUsage,
  nearestAllowedQuarterOnDayOutsideUsage,
} from '@/utils/activityPlanningUsageConstraint'

const props = withDefaults(
  defineProps<{
    modelValue: Date | null
    density?: 'default' | 'comfortable' | 'compact'
    ariaLabel?: string
    disabled?: boolean
    locked?: boolean
    blockedUsageRange?: { start: Date; end: Date } | null
  }>(),
  { density: 'compact', disabled: false, locked: false },
)

const emit = defineEmits<{
  'update:modelValue': [value: Date | null]
}>()

const menuOpen = ref(false)
const pickerModel = ref<string | null>(null)
const pickerViewMode = ref<'hour' | 'minute' | 'second'>('hour')
const draftHour = ref<number | null>(null)

const QUARTER_MINUTES = [0, 15, 30, 45] as const

const isFieldDisabled = computed(() => props.disabled || props.locked || !props.modelValue)

function pad2(n: number): string {
  return String(n).padStart(2, '0')
}

function safeSnap(d: Date | null): Date | null {
  if (!d || !Number.isFinite(d.getTime())) return null
  return snapDateToQuarterHour(d)
}

function dayAnchorForBlocked(): Date | null {
  const d = safeSnap(props.modelValue)
  if (!d) return null
  return startOfLocalDay(d)
}

function instantOnDay(day: Date, hour: number, minute: number): Date {
  const x = new Date(day.getFullYear(), day.getMonth(), day.getDate(), hour, minute, 0, 0)
  return snapDateToQuarterHour(x)
}

function isBlockedInstant(hour: number, minute: number): boolean {
  const r = props.blockedUsageRange
  if (!r || props.locked || !props.modelValue) return false
  const day = dayAnchorForBlocked()
  if (!day) return false
  return isInstantInsideClosedUsage(instantOnDay(day, hour, minute), r.start, r.end)
}

const displayValue = computed(() => {
  const d = safeSnap(props.modelValue)
  if (!d) return ''
  return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`
})

function allowedHours(hour: number): boolean {
  if (isFieldDisabled.value) return false
  return QUARTER_MINUTES.some((m) => !isBlockedInstant(hour, m))
}

function allowedQuarterMinutes(minute: number): boolean {
  if (!QUARTER_MINUTES.includes(minute as (typeof QUARTER_MINUTES)[number])) return false
  if (isFieldDisabled.value) return false
  const hour = draftHour.value ?? props.modelValue?.getHours() ?? 0
  return !isBlockedInstant(hour, minute)
}

watch(menuOpen, (open) => {
  if (open) {
    draftHour.value = null
    pickerModel.value = displayValue.value || null
    pickerViewMode.value = 'hour'
  }
})

function onFieldClick(e: MouseEvent) {
  if (isFieldDisabled.value) return
  e.preventDefault()
  e.stopPropagation()
  menuOpen.value = true
}

function applyHourMinute(hour: number, minute: number) {
  const base = safeSnap(props.modelValue)
  if (!base) return
  let out = new Date(base.getFullYear(), base.getMonth(), base.getDate(), hour, minute, 0, 0)
  out = snapDateToQuarterHour(out)
  const br = props.blockedUsageRange
  if (br && isInstantInsideClosedUsage(out, br.start, br.end)) {
    const fixed = nearestAllowedQuarterOnDayOutsideUsage(out, br.start, br.end)
    if (!fixed) return
    out = fixed
  }
  emit('update:modelValue', out)
}

function onHourPicked(hour: number) {
  draftHour.value = hour
}

function onMinutePicked(minute: number) {
  if (!props.modelValue) return
  if (!allowedQuarterMinutes(minute)) return
  const hour = draftHour.value ?? props.modelValue.getHours()
  applyHourMinute(hour, minute)
  menuOpen.value = false
}
</script>

<style scoped>
.activity-v-time-picker-field {
  width: 100%;
}
</style>
