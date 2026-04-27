<template>
  <!-- Explizites 00/15/30/45-Raster — unabhängig vom Browser (manche ignorieren step bei type="time"). -->
  <div class="activity-time-quarter-grid" :class="{ 'is-disabled': !modelValue || locked }">
    <div class="activity-time-slot" :title="t('activities.timeField.editHint')" @dblclick.prevent="openHourEdit">
      <label class="sr-only" :for="hourEditing ? hourInputId : hourId">{{ t('activities.timeField.hour') }}</label>
      <select
        v-show="!hourEditing"
        :id="hourId"
        ref="hourSelectRef"
        class="form-input activity-time-part activity-time-hour"
        :disabled="!modelValue || locked"
        :value="hourStr"
        :aria-label="(ariaLabel ? ariaLabel + ', ' : '') + t('activities.timeField.hour')"
        @change="onHourChange"
      >
        <option v-for="h in hourOptions" :key="h" :value="h" :disabled="hourOptionDisabled(h)">{{ h }}</option>
      </select>
      <input
        v-show="hourEditing"
        :id="hourInputId"
        ref="hourInputRef"
        v-model="hourDraft"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        maxlength="2"
        class="form-input activity-time-part activity-time-hour activity-time-edit"
        :disabled="!modelValue || locked"
        :aria-label="(ariaLabel ? ariaLabel + ', ' : '') + t('activities.timeField.hourKeyboard')"
        @blur="commitHourEdit"
        @keydown="onHourEditKeydown"
      />
    </div>
    <span class="activity-time-sep" aria-hidden="true">:</span>
    <div class="activity-time-slot" :title="t('activities.timeField.editHint')" @dblclick.prevent="openMinuteEdit">
      <label class="sr-only" :for="minuteEditing ? minuteInputId : minuteId">{{ t('activities.timeField.minute') }}</label>
      <select
        v-show="!minuteEditing"
        :id="minuteId"
        ref="minuteSelectRef"
        class="form-input activity-time-part activity-time-minute"
        :disabled="!modelValue || locked"
        :value="minuteStr"
        :aria-label="(ariaLabel ? ariaLabel + ', ' : '') + t('activities.timeField.minuteQuarter')"
        @change="onMinuteChange"
      >
        <option v-for="m in minuteOptions" :key="m" :value="m" :disabled="minuteOptionDisabled(m)">{{ m }}</option>
      </select>
      <input
        v-show="minuteEditing"
        :id="minuteInputId"
        ref="minuteInputRef"
        v-model="minuteDraft"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        maxlength="2"
        class="form-input activity-time-part activity-time-minute activity-time-edit"
        :disabled="!modelValue || locked"
        :aria-label="(ariaLabel ? ariaLabel + ', ' : '') + t('activities.timeField.minuteKeyboard')"
        @blur="commitMinuteEdit"
        @keydown="onMinuteEditKeydown"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { snapDateToQuarterHour } from '@/utils/activityPlanningFromDefaults'
import { startOfLocalDay } from '@/utils/activityDateTimeParts'
import {
  isInstantInsideClosedUsage,
  nearestAllowedQuarterOnDayOutsideUsage,
} from '@/utils/activityPlanningUsageConstraint'

const idBase = Math.random().toString(36).slice(2, 11)
const hourId = `activity-time-h-${idBase}`
const minuteId = `activity-time-m-${idBase}`
const hourInputId = `activity-time-hi-${idBase}`
const minuteInputId = `activity-time-mi-${idBase}`

const hourSelectRef = ref<HTMLSelectElement | null>(null)
const minuteSelectRef = ref<HTMLSelectElement | null>(null)
const hourInputRef = ref<HTMLInputElement | null>(null)
const minuteInputRef = ref<HTMLInputElement | null>(null)

const hourEditing = ref(false)
const minuteEditing = ref(false)
const hourDraft = ref('')
const minuteDraft = ref('')

const { t } = useI18n()

const props = defineProps<{
  modelValue: Date | null
  /** Für Barrierefreiheit, falls kein sichtbares Label */
  ariaLabel?: string
  /** z. B. gesperrt, solange Materialpositionen im Wizard (vgl. v4.01) */
  locked?: boolean
  /**
   * Viertelstunden in diesem Intervall sind in den Dropdowns nicht wählbar
   * (z. B. Nutzungszeit – Abhol-/Rückgabe darf nicht hineinliegen).
   */
  blockedUsageRange?: { start: Date; end: Date } | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Date | null]
}>()

const minuteOptions = ['00', '15', '30', '45'] as const
const hourOptions = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'))

function dayAnchorForBlocked(): Date | null {
  const d = safeSnap(props.modelValue)
  if (!d) return null
  return startOfLocalDay(d)
}

function instantOnDay(day: Date, hourStr: string, minuteStr: string): Date {
  const hh = parseInt(hourStr, 10)
  const mm = parseInt(minuteStr, 10)
  const x = new Date(day.getFullYear(), day.getMonth(), day.getDate(), hh, mm, 0, 0)
  return snapDateToQuarterHour(x)
}

function hourOptionDisabled(h: string): boolean {
  const r = props.blockedUsageRange
  if (!r || props.locked || !props.modelValue) return false
  const day = dayAnchorForBlocked()
  if (!day) return false
  return minuteOptions.every((m) => isInstantInsideClosedUsage(instantOnDay(day, h, m), r.start, r.end))
}

function minuteOptionDisabled(m: string): boolean {
  const r = props.blockedUsageRange
  if (!r || props.locked || !props.modelValue) return false
  const day = dayAnchorForBlocked()
  if (!day) return false
  const d = safeSnap(props.modelValue)
  if (!d) return false
  const hh = String(Math.max(0, Math.min(23, d.getHours()))).padStart(2, '0')
  return isInstantInsideClosedUsage(instantOnDay(day, hh, m), r.start, r.end)
}

function safeSnap(d: Date | null): Date | null {
  if (!d || !Number.isFinite(d.getTime())) return null
  return snapDateToQuarterHour(d)
}

/** Stunden/Minuten immer als gültige Werte ausgeben, damit select nie „leer“ bleibt (kein NaN, kein Mismatch). */
const hourStr = computed(() => {
  const d = safeSnap(props.modelValue)
  if (!d) return '12'
  const hh = Math.max(0, Math.min(23, d.getHours()))
  return String(hh).padStart(2, '0')
})

const minuteStr = computed(() => {
  const d = safeSnap(props.modelValue)
  if (!d) return '00'
  const mm = d.getMinutes()
  const allowed = [0, 15, 30, 45]
  const closest = allowed.reduce((prev, cur) => (Math.abs(cur - mm) < Math.abs(prev - mm) ? cur : prev))
  return String(closest).padStart(2, '0')
})

function snapMinuteToQuarter(m: number): number {
  const allowed = [0, 15, 30, 45]
  const x = Math.max(0, Math.min(59, Math.round(m)))
  return allowed.reduce((prev, cur) => (Math.abs(cur - x) < Math.abs(prev - x) ? cur : prev))
}

function applyHhMm(hh: number, mm: number) {
  const base = safeSnap(props.modelValue)
  if (!base) return
  let out = new Date(base.getTime())
  out.setHours(hh, mm, 0, 0)
  out = snapDateToQuarterHour(out)
  const br = props.blockedUsageRange
  if (br && isInstantInsideClosedUsage(out, br.start, br.end)) {
    const fixed = nearestAllowedQuarterOnDayOutsideUsage(out, br.start, br.end)
    if (!fixed) return
    out = fixed
  }
  emit('update:modelValue', out)
}

function onHourChange(e: Event) {
  const v = (e.target as HTMLSelectElement).value
  const hh = parseInt(v, 10)
  if (!Number.isFinite(hh)) return
  const d = props.modelValue ? snapDateToQuarterHour(props.modelValue) : null
  const mm = d ? d.getMinutes() : 0
  applyHhMm(hh, mm)
}

function onMinuteChange(e: Event) {
  const v = (e.target as HTMLSelectElement).value
  const mm = parseInt(v, 10)
  if (!Number.isFinite(mm)) return
  const d = props.modelValue ? snapDateToQuarterHour(props.modelValue) : null
  const hh = d ? d.getHours() : 12
  applyHhMm(hh, mm)
}

function openHourEdit() {
  if (!props.modelValue || props.locked) return
  hourEditing.value = true
  minuteEditing.value = false
  hourDraft.value = hourStr.value
  hourSelectRef.value?.blur()
  void nextTick(() => hourInputRef.value?.focus())
}

function openMinuteEdit() {
  if (!props.modelValue || props.locked) return
  minuteEditing.value = true
  hourEditing.value = false
  minuteDraft.value = minuteStr.value
  minuteSelectRef.value?.blur()
  void nextTick(() => minuteInputRef.value?.focus())
}

function commitHourEdit() {
  if (!hourEditing.value) return
  const raw = hourDraft.value.replace(/\D/g, '')
  const n = raw === '' ? parseInt(hourStr.value, 10) : parseInt(raw, 10)
  const hh = Number.isFinite(n) ? Math.max(0, Math.min(23, n)) : parseInt(hourStr.value, 10)
  const d = props.modelValue ? snapDateToQuarterHour(props.modelValue) : null
  const mm = d ? d.getMinutes() : 0
  applyHhMm(hh, mm)
  hourEditing.value = false
}

function commitMinuteEdit() {
  if (!minuteEditing.value) return
  const raw = minuteDraft.value.replace(/\D/g, '')
  const n = raw === '' ? parseInt(minuteStr.value, 10) : parseInt(raw, 10)
  const mm = Number.isFinite(n) ? snapMinuteToQuarter(n) : parseInt(minuteStr.value, 10)
  const d = props.modelValue ? snapDateToQuarterHour(props.modelValue) : null
  const hh = d ? d.getHours() : 12
  applyHhMm(hh, mm)
  minuteEditing.value = false
}

function onHourEditKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter') {
    e.preventDefault()
    commitHourEdit()
  } else if (e.key === 'Escape') {
    e.preventDefault()
    hourEditing.value = false
  }
}

function onMinuteEditKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter') {
    e.preventDefault()
    commitMinuteEdit()
  } else if (e.key === 'Escape') {
    e.preventDefault()
    minuteEditing.value = false
  }
}
</script>

<style scoped>
.activity-time-quarter-grid {
  display: flex;
  align-items: center;
  gap: 4px;
  width: 100%;
  min-height: var(--activity-pill-control-h, 40px);
}

.activity-time-quarter-grid.is-disabled {
  opacity: 0.55;
}

.activity-time-slot {
  flex: 1 1 0;
  min-width: 3rem;
  max-width: 3.5rem;
}

.activity-time-part {
  min-height: var(--activity-pill-control-h, 40px);
  height: var(--activity-pill-control-h, auto);
  width: 100%;
  box-sizing: border-box;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  color: #111827;
  background: #fff;
  padding: 0 6px;
  cursor: pointer;
  text-align: center;
  font-variant-numeric: tabular-nums;
  line-height: var(--activity-pill-control-h, 40px);
}

.activity-time-edit {
  cursor: text;
}

.activity-time-sep {
  font-weight: 600;
  color: #6b7280;
  user-select: none;
  flex: 0 0 auto;
}

.activity-time-part:focus {
  outline: none;
  border-color: var(--emc-brand-accent, #059669);
  box-shadow: 0 0 0 3px rgb(5 150 105 / 18%);
}

.activity-time-part:disabled {
  cursor: not-allowed;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
