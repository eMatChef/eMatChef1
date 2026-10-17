<template>
  <div class="cfg">
    <template v-for="(field, key) in def.fields" :key="key">
      <ESelect
        v-if="isFilterField(key)"
        :model-value="(config[key] as string) || null"
        :items="filterItems(key)"
        :label="fieldLabel(key)"
        clearable
        hide-details
        @update:model-value="emit('update', key, $event ?? '')"
      />
      <ESelect
        v-else-if="field.type === 'enum'"
        :model-value="config[key] as string"
        :items="enumItems(key, field.values ?? [])"
        :label="fieldLabel(key)"
        hide-details
        @update:model-value="emit('update', key, $event)"
      />
      <ECheckbox
        v-else-if="field.type === 'bool'"
        :model-value="config[key] === true"
        :label="fieldLabel(key)"
        hide-details
        @update:model-value="emit('update', key, $event === true)"
      />
      <ETextField
        v-else-if="field.type === 'int'"
        :model-value="String(config[key] ?? '')"
        type="number"
        :min="field.min"
        :max="field.max"
        :label="fieldLabel(key)"
        hide-details
        @update:model-value="onInt(key, $event, field.min, field.max)"
      />
      <ETextarea
        v-else-if="field.type === 'lines'"
        :model-value="linesText(key)"
        :label="fieldLabel(key)"
        rows="4"
        hide-details
        @update:model-value="emit('update', key, String($event ?? '').split('\n').slice(0, field.max_items ?? 20))"
      />
      <ETextarea
        v-else-if="field.type === 'text'"
        :model-value="String(config[key] ?? '')"
        :label="fieldLabel(key)"
        rows="3"
        :maxlength="field.max"
        hide-details
        @update:model-value="emit('update', key, String($event ?? ''))"
      />
      <ETextField
        v-else
        :model-value="String(config[key] ?? '')"
        :label="fieldLabel(key)"
        :maxlength="field.max"
        :placeholder="field.type === 'url' ? 'https://' : undefined"
        hide-details
        @update:model-value="emit('update', key, String($event ?? ''))"
      />
    </template>
    <p v-if="def.data_source === 'ga_demo'" class="cfg__demo">{{ t('display.layout.demoDataHint') }}</p>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { WidgetDefinition } from '@/api/displayLayout'
import { ECheckbox, ESelect, ETextField, ETextarea } from '@/components/form/base'
import { useGaAuftraegeMock } from '@/views/grossanlass/auftraege/gaAuftraegeMock'

/** Konfigurationsformular, aus dem Schema des Widgets (Registry) erzeugt: nur deklarierte Felder, nie freies HTML. */
const props = defineProps<{ def: WidgetDefinition; config: Record<string, unknown> }>()
const emit = defineEmits<{ update: [key: string, value: unknown] }>()
const { t, te } = useI18n()

const { orders } = useGaAuftraegeMock()

/** GA-Widgets werden nach Ressort, Bereich und Bauauftrag gefiltert (Auswahl aus den Demo-Aufträgen). */
function isFilterField(key: string): boolean {
  return props.def.data_source === 'ga_demo' && ['filter_ressort', 'filter_bereich', 'filter_project'].includes(key)
}

function filterItems(key: string) {
  const ressort = String(props.config.filter_ressort ?? '')
  const bereich = String(props.config.filter_bereich ?? '')
  const rows = orders.value.filter((o) => (key === 'filter_ressort' || !ressort || o.ressort === ressort) && (key !== 'filter_project' || !bereich || o.bereich === bereich))
  const values = new Set(rows.map((o) => (key === 'filter_ressort' ? o.ressort : key === 'filter_bereich' ? o.bereich : o.build?.project || o.title)))
  const current = String(props.config[key] ?? '')
  if (current) values.add(current)
  return [...values].filter(Boolean).sort().map((v) => ({ value: v, title: v }))
}

function fieldLabel(key: string): string {
  const specific = `display.layout.field.${props.def.type}.${key}`
  return te(specific) ? t(specific) : t(`display.layout.field.${key}`)
}

function enumItems(key: string, values: string[]) {
  return values.map((v) => ({ value: v, title: te(`display.layout.option.${key}.${v}`) ? t(`display.layout.option.${key}.${v}`) : v }))
}

function linesText(key: string): string {
  const value = props.config[key]
  return Array.isArray(value) ? value.join('\n') : ''
}

function onInt(key: string, raw: unknown, min = 0, max = 100000) {
  const n = Number(raw)
  if (raw === '' || Number.isNaN(n)) return
  emit('update', key, Math.min(max, Math.max(min, Math.round(n))))
}
</script>

<style scoped>
.cfg { display: flex; flex-direction: column; gap: 10px; }
.cfg__demo { margin: 0; font-size: 0.8rem; color: #92400e; }
</style>
