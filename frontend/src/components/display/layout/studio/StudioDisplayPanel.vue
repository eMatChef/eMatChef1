<template>
  <div class="flow" data-testid="panel-display">
    <!-- Datenmodus der veröffentlichten Anzeige -->
    <section class="flow__mode" :class="{ 'flow__mode--demo': demoOnDisplay }">
      <ECheckbox
        :model-value="demoOnDisplay"
        :label="t('display.studio.publishedMode.label')"
        hide-details
        data-testid="published-demo-toggle"
        @update:model-value="setDataMode($event === true)"
      />
      <p class="flow__hint">{{ demoOnDisplay ? t('display.studio.publishedMode.demoOn') : t('display.studio.publishedMode.demoOff') }}</p>
      <p v-if="demoOnDisplay" class="flow__badge">{{ t('display.studio.publishedMode.badgeNote') }}</p>
      <p v-else-if="unsupported.length" class="flow__warn" role="alert" data-testid="live-unsupported">
        {{ t('display.studio.publishedMode.unsupported', { list: unsupported.map((w) => t(`display.layout.widget.${w}`)).join(', ') }) }}
      </p>
    </section>

    <ETextField :model-value="variant.name" :label="t('display.layout.editor.variantName')" maxlength="80" hide-details @update:model-value="mutate((_d, v) => (v.name = String($event ?? '')))" />
    <ESelect :model-value="variant.aspect_ratio" :items="aspectItems" :label="t('display.layout.editor.variantAspect')" hide-details @update:model-value="mutate((_d, v) => (v.aspect_ratio = String($event)))" />
    <ECheckbox :model-value="doc.default_variant_id === variant.id" :disabled="doc.default_variant_id === variant.id" :label="t('display.layout.editor.variantDefault')" hide-details @update:model-value="mutate((d, v) => (d.default_variant_id = v.id))" />
    <EButton variant="danger" size="small" :disabled="doc.variants.length <= 1" @click="emit('deleteVariant')">{{ t('display.layout.editor.deleteVariant') }}</EButton>

    <h4 class="flow__sub">{{ t('display.layout.editor.flowTitle') }}</h4>
    <p v-if="!flow.simple" class="flow__hint">{{ t('display.layout.editor.flowComplex') }}</p>
    <template v-else>
      <p class="flow__hint">{{ t('display.layout.editor.flowHint') }}</p>
      <ol class="flow__list">
        <li v-for="(id, i) in flow.pageIds" :key="`${id}-${i}`">
          <span>{{ i + 1 }}. {{ pageName(id) }}</span>
          <ETextField v-if="flow.pageIds.length > 1" :model-value="flow.durations[i] ? String(flow.durations[i]) : ''" type="number" :min="limits.min_interval_sec" :max="limits.max_interval_sec" :placeholder="String(flow.interval)" :label="t('display.layout.editor.stepDuration')" hide-details class="flow__dur" @update:model-value="setDuration(i, $event)" />
          <span class="flow__actions">
            <button type="button" :disabled="i === 0" :aria-label="t('display.layout.editor.moveUp')" @click="move(i, -1)">↑</button>
            <button type="button" :disabled="i === flow.pageIds.length - 1" :aria-label="t('display.layout.editor.moveDown')" @click="move(i, 1)">↓</button>
            <button type="button" :disabled="flow.pageIds.length <= 1" :aria-label="t('common.delete')" @click="remove(i)">✕</button>
          </span>
        </li>
      </ol>
      <div class="flow__add">
        <ESelect v-model="addId" :items="pageItems" :label="t('display.layout.editor.flowAdd')" hide-details />
        <EButton variant="secondary" size="small" :disabled="!addId" @click="add">{{ t('common.add') }}</EButton>
      </div>
      <template v-if="flow.pageIds.length > 1">
        <ECheckbox :model-value="flow.enabled" :label="t('display.layout.editor.rotationEnabled')" hide-details @update:model-value="apply(flow.pageIds, $event === true, flow.interval, flow.durations)" />
        <ETextField :model-value="String(flow.interval)" type="number" :min="limits.min_interval_sec" :max="limits.max_interval_sec" :label="t('display.layout.editor.interval')" hide-details @update:model-value="setInterval_($event)" />
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { LayoutCatalog, LayoutDocument, LayoutVariant, WidgetDefinition } from '@/api/displayLayout'
import { EButton, ECheckbox, ESelect, ETextField } from '@/components/form/base'
import { setVariantFlow, variantFlow } from '../layoutEditing'
import { collectWidgets } from './liveSupport'

/** Eigenschaften der Anzeige: Datenmodus der Veröffentlichung, Variante und Ablauf (Seiten, Rotation). */
const props = defineProps<{
  doc: LayoutDocument
  variant: LayoutVariant
  limits: LayoutCatalog['limits']
  widgetDefs: Map<string, WidgetDefinition>
}>()

const emit = defineEmits<{
  /** Änderung am Dokument; der Editor legt vorher den Undo-Schnappschuss ab und wendet sie an. */
  mutate: [fn: (doc: LayoutDocument, variant: LayoutVariant) => void]
  deleteVariant: []
}>()

const { t } = useI18n()
const addId = ref<string | null>(null)
const ASPECTS = ['16:9', '16:10', '4:3', '3:2', '21:9', '9:16']
const aspectItems = ASPECTS.map((a) => ({ value: a, title: a }))

const flow = computed(() => variantFlow(props.variant))
const pageItems = computed(() => props.doc.pages.map((p) => ({ value: p.id, title: p.name || p.id })))
const demoOnDisplay = computed(() => props.doc.data_mode === 'demo')
const unsupported = computed(() => [...new Set(collectWidgets(props.doc).filter((w) => props.widgetDefs.get(w)?.live === false))])

function pageName(id: string): string {
  return props.doc.pages.find((p) => p.id === id)?.name || id
}

function mutate(fn: (doc: LayoutDocument, variant: LayoutVariant) => void): void {
  emit('mutate', fn)
}

function setDataMode(demo: boolean): void {
  mutate((d) => {
    if (demo) d.data_mode = 'demo'
    else delete d.data_mode
  })
}

function apply(pageIds: string[], enabled: boolean, interval: number, durations: (number | null)[]): void {
  mutate((_d, v) => setVariantFlow(v, pageIds, enabled, interval, durations))
}
function add(): void {
  if (!addId.value) return
  const f = flow.value
  apply([...f.pageIds, addId.value], f.pageIds.length ? f.enabled || f.pageIds.length === 1 : true, f.interval, [...f.durations, null])
  addId.value = null
}
function remove(i: number): void {
  const f = flow.value
  apply(f.pageIds.filter((_, idx) => idx !== i), f.enabled, f.interval, f.durations.filter((_, idx) => idx !== i))
}
function move(i: number, delta: number): void {
  const f = flow.value
  const ids = [...f.pageIds]
  const durations = [...f.durations]
  const j = i + delta
  ;[ids[i], ids[j]] = [ids[j]!, ids[i]!]
  ;[durations[i], durations[j]] = [durations[j] ?? null, durations[i] ?? null]
  apply(ids, f.enabled, f.interval, durations)
}
function clampSeconds(raw: unknown): number {
  return Math.min(props.limits.max_interval_sec, Math.max(props.limits.min_interval_sec, Math.round(Number(raw)) || props.limits.default_interval_sec))
}
function setInterval_(raw: unknown): void {
  const f = flow.value
  apply(f.pageIds, f.enabled, clampSeconds(raw), f.durations)
}
function setDuration(i: number, raw: unknown): void {
  const f = flow.value
  const durations = [...f.durations]
  durations[i] = raw === '' || raw === null ? null : clampSeconds(raw)
  apply(f.pageIds, f.enabled, f.interval, durations)
}
</script>

<style scoped>
.flow { display: flex; flex-direction: column; gap: 10px; }
.flow__mode { padding: 10px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f9fafb; }
.flow__mode--demo { border-color: #f59e0b; background: #fffbeb; }
.flow__hint { margin: 4px 0 0; font-size: 0.8rem; color: #6b7280; }
.flow__badge { margin: 6px 0 0; font-size: 0.8rem; color: #92400e; font-weight: 600; }
.flow__warn { margin: 6px 0 0; font-size: 0.8rem; color: #b91c1c; }
.flow__sub { margin: 6px 0 0; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; }
.flow__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.flow__list li { display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap; border: 1px solid #e5e7eb; border-radius: 8px; padding: 4px 8px; }
.flow__dur { width: 100px; }
.flow__actions button { border: 0; background: none; cursor: pointer; padding: 2px 6px; }
.flow__add { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: end; }
</style>
