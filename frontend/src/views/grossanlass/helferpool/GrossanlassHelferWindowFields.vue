<template>
  <div class="window">
    <div class="window__row">
      <EDateField v-model="startDate" :label="startLabel" allow-past :disabled="disabled" />
      <ETimeField v-model="startTime" :label="t('grossanlass.helferpool.field.time')" :disabled="disabled" hide-details />
    </div>
    <div class="window__row">
      <EDateField v-model="endDate" :label="endLabel" allow-past :disabled="disabled" />
      <ETimeField v-model="endTime" :label="t('grossanlass.helferpool.field.time')" :disabled="disabled" hide-details />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EDateField, ETimeField } from '@/components/form/base'
import { splitIso, toIso } from './gaHelferUi'

/** Zeitfenster aus Start und Ende (lokale ISO-Zeitpunkte `YYYY-MM-DDTHH:mm:ss`), je Datum und Uhrzeit. */
const props = defineProps<{ startLabel?: string; endLabel?: string; disabled?: boolean }>()
const start = defineModel<string>('start', { default: '' })
const end = defineModel<string>('end', { default: '' })
const { t } = useI18n()

const startLabel = computed(() => props.startLabel ?? t('grossanlass.helferpool.field.from'))
const endLabel = computed(() => props.endLabel ?? t('grossanlass.helferpool.field.to'))

function part(model: { value: string }, key: 'date' | 'time') {
  return computed({
    get: () => splitIso(model.value)[key],
    set: (value: string) => {
      const current = splitIso(model.value)
      model.value = toIso(key === 'date' ? value : current.date, key === 'time' ? value : current.time)
    },
  })
}
// defineModel liefert Refs; `.value` ist der aktuelle Zeitpunkt
const startDate = part(start, 'date')
const startTime = part(start, 'time')
const endDate = part(end, 'date')
const endTime = part(end, 'time')
</script>

<style scoped>
.window {
  display: grid;
  gap: 8px;
}
.window__row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 120px;
  gap: 10px;
  align-items: start;
}
</style>
