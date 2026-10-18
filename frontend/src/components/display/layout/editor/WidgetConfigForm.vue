<template>
  <div class="cfg">
    <template v-for="[key, field] in visibleFields" :key="key">
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
        v-else-if="key === 'group_id' && def.data_source === 'ga_live'"
        :model-value="(config[key] as string) || null"
        :items="groupItems"
        :label="fieldLabel(key)"
        :hint="t('display.layout.field.group_id_hint')"
        persistent-hint
        clearable
        @update:model-value="emit('update', key, $event ?? '')"
      />
      <fieldset v-else-if="field.type === 'set'" class="cfg__set">
        <legend>{{ fieldLabel(key) }}</legend>
        <ECheckbox
          v-for="value in field.values ?? []"
          :key="value"
          :model-value="setValues(key).includes(value)"
          :label="enumItems(key, [value])[0].title"
          hide-details
          @update:model-value="toggleSet(key, value, $event === true, field.values ?? [])"
        />
      </fieldset>
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
        v-else-if="field.type === 'float'"
        :model-value="String(config[key] ?? '')"
        type="number"
        step="any"
        :min="field.min"
        :max="field.max"
        :label="fieldLabel(key)"
        hide-details
        @update:model-value="onFloat(key, $event, field.min, field.max)"
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
    <p v-else-if="def.data_source === 'ga_live'" class="cfg__live">{{ t('display.layout.liveDataHint') }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { getGrossanlassGroups } from '@/api/grossanlassGroups'
import type { WidgetDefinition, WidgetField } from '@/api/displayLayout'
import { ECheckbox, ESelect, ETextField, ETextarea } from '@/components/form/base'
import { useGaAuftraegeMock } from '@/views/grossanlass/auftraege/gaAuftraegeMock'
import { filterOptions } from '@/views/grossanlass/live/gaLiveAdapter'
import { LAYOUT_DATA_MODE_KEY, LAYOUT_LIVE_DATA_KEY } from '../layoutContext'

/** Konfigurationsformular, aus dem Schema des Widgets (Registry) erzeugt: nur deklarierte Felder, nie freies HTML. */
const props = defineProps<{ def: WidgetDefinition; config: Record<string, unknown> }>()
const emit = defineEmits<{ update: [key: string, value: unknown] }>()
const { t, te } = useI18n()

const { orders } = useGaAuftraegeMock()
// Auswahl der Filterwerte folgt der Vorschau: Demo = Demo-Aufträge, Live = echte Ressorts, Bereiche und Aufträge des Grossanlasses.
const dataMode = inject(LAYOUT_DATA_MODE_KEY, () => 'demo' as const)
const liveSource = inject(LAYOUT_LIVE_DATA_KEY, () => null)
const route = useRoute()

/** Felder des Widgets; ältere Filterfelder echter Daten-Widgets und Karten-Felder ohne Wirkung werden ausgeblendet. */
const visibleFields = computed(() =>
  (Object.entries(props.def.fields) as [string, WidgetField][]).filter(([key]) => {
    if (props.def.data_source === 'ga_live' && key.startsWith('filter_')) return false
    if (props.def.type === 'ga_site_map' && ['center_lat', 'center_lng', 'zoom'].includes(key)) return props.config.view === 'fixed'
    return true
  }),
)

/** Bauprojekt/Bereich-Auswahl der Karte: Gruppen des Grossanlasses (Department kommt aus der Route). */
const groups = ref<{ value: string; title: string }[]>([])
onMounted(async () => {
  if (props.def.data_source !== 'ga_live') return
  try {
    const rows = await getGrossanlassGroups(String(route.params.departmentId ?? ''))
    groups.value = rows.filter((g) => g.node_type !== 'auftrag').map((g) => ({ value: g.id, title: `${'– '.repeat(g.level)}${g.name}` }))
  } catch {
    groups.value = []
  }
})
const groupItems = computed(() => {
  const current = String(props.config.group_id ?? '')
  return current && !groups.value.some((g) => g.value === current) ? [...groups.value, { value: current, title: current }] : groups.value
})

function setValues(key: string): string[] {
  const value = props.config[key]
  return Array.isArray(value) ? value.map(String) : []
}

function toggleSet(key: string, value: string, on: boolean, all: string[]) {
  const next = new Set(setValues(key))
  if (on) next.add(value)
  else next.delete(value)
  emit('update', key, all.filter((v) => next.has(v)))
}

/** GA-Widgets werden nach Ressort, Bereich und Bauauftrag gefiltert (Auswahl aus den Demo-Aufträgen). */
function isFilterField(key: string): boolean {
  return props.def.data_source === 'ga_demo' && ['filter_ressort', 'filter_bereich', 'filter_project'].includes(key)
}

function filterItems(key: string) {
  if (dataMode() === 'live') {
    const current = String(props.config[key] ?? '')
    const values = new Set(filterOptions(liveSource()?.groups ?? [], key as 'filter_ressort' | 'filter_bereich' | 'filter_project', {
      ressort: String(props.config.filter_ressort ?? ''),
      bereich: String(props.config.filter_bereich ?? ''),
    }))
    if (current) values.add(current)
    return [...values].sort().map((v) => ({ value: v, title: v }))
  }
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

function onFloat(key: string, raw: unknown, min = -1e9, max = 1e9) {
  const n = Number(raw)
  if (raw === '' || Number.isNaN(n)) return
  emit('update', key, Math.min(max, Math.max(min, n)))
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
.cfg__live { margin: 0; font-size: 0.8rem; color: #166534; }
.cfg__set { display: flex; flex-direction: column; gap: 2px; margin: 0; padding: 0; border: 0; }
.cfg__set legend { padding: 0; margin-bottom: 2px; font-size: 0.8rem; opacity: 0.75; }
</style>
