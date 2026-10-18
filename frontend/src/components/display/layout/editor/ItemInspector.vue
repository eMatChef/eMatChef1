<template>
  <div v-if="item" class="insp" data-testid="item-inspector">
    <h4 class="insp__title">{{ title }}</h4>

    <div v-if="!isolated" class="insp__grid">
      <ETextField :model-value="String(item.x + 1)" type="number" min="1" :max="columns" :label="t('display.layout.editor.col')" hide-details @update:model-value="geo('x', $event, -1)" />
      <ETextField :model-value="String(item.y + 1)" type="number" min="1" :max="rows" :label="t('display.layout.editor.row')" hide-details @update:model-value="geo('y', $event, -1)" />
      <ETextField :model-value="String(item.w)" type="number" min="1" :max="columns" :label="t('display.layout.editor.width')" hide-details @update:model-value="geo('w', $event, 0)" />
      <ETextField :model-value="String(item.h)" type="number" min="1" :max="rows" :label="t('display.layout.editor.height')" hide-details @update:model-value="geo('h', $event, 0)" />
    </div>
    <p v-if="issueText" class="insp__issue" role="alert">{{ issueText }}</p>

    <!-- Widget -->
    <template v-if="item.content.type === 'widget' && widgetDef">
      <WidgetConfigForm :def="widgetDef" :config="item.content.config" @update="(k, v) => setConfig(k, v)" />
    </template>

    <!-- Eingebettetes Layout -->
    <template v-else-if="item.content.type === 'page'">
      <ESelect :model-value="item.content.page_id" :items="embeddablePages" :label="t('display.layout.editor.embeddedPage')" hide-details @update:model-value="setPage($event)" />
      <p class="insp__hint">{{ t('display.layout.editor.embeddedHint') }}</p>
    </template>

    <!-- Rotation -->
    <template v-else-if="item.content.type === 'rotation'">
      <ECheckbox :model-value="item.content.enabled" :label="t('display.layout.editor.rotationEnabled')" hide-details @update:model-value="mutateRotation((r) => (r.enabled = $event === true))" />
      <ETextField :model-value="String(item.content.interval_sec)" type="number" :min="limits.min_interval_sec" :max="limits.max_interval_sec" :label="t('display.layout.editor.interval')" hide-details @update:model-value="mutateRotation((r) => (r.interval_sec = clampInterval($event)))" />
      <p class="insp__hint">{{ t('display.studio.rotation.hint') }}</p>
      <ol class="insp__steps" data-testid="rotation-pages">
        <li
          v-for="(step, i) in item.content.steps"
          :key="i"
          :class="{ 'insp__step--on': i === stepIndex, 'insp__step--drop': dropIndex === i }"
          :data-testid="`rotation-page-${i}`"
          draggable="true"
          @dragstart="dragFrom = i"
          @dragover.prevent="dropIndex = i"
          @dragleave="dropIndex = null"
          @drop.prevent="dropOn(i)"
          @dragend="dragFrom = null; dropIndex = null"
        >
          <button type="button" class="insp__step-btn" @click="stepIndex = i" @dblclick="emit('openStep', item.id, i)">
            {{ i + 1 }}. {{ stepTitle(step, i) }}<small> · {{ step.duration_sec ?? item.content.interval_sec }} s</small>
          </button>
          <span class="insp__step-actions">
            <button type="button" :title="t('display.studio.rotation.edit')" :aria-label="t('display.studio.rotation.edit')" :data-testid="`rotation-edit-${i}`" @click="emit('openStep', item.id, i)">✎</button>
            <button type="button" :title="t('display.layout.editor.moveUp')" :aria-label="t('display.layout.editor.moveUp')" :disabled="i === 0" @click="moveStep(i, -1)">↑</button>
            <button type="button" :title="t('display.layout.editor.moveDown')" :aria-label="t('display.layout.editor.moveDown')" :disabled="i === item.content.steps.length - 1" @click="moveStep(i, 1)">↓</button>
            <button type="button" :title="t('display.layout.editor.duplicate')" :aria-label="t('display.layout.editor.duplicate')" :disabled="item.content.steps.length >= limits.max_steps" @click="duplicateStep(i)">⧉</button>
            <button type="button" :title="t('common.delete')" :aria-label="t('common.delete')" :disabled="item.content.steps.length <= 1" @click="removeStep(i)">✕</button>
          </span>
        </li>
      </ol>
      <EButton variant="secondary" size="small" :disabled="item.content.steps.length >= limits.max_steps" data-testid="btn-add-rotation-page" @click="addPage">{{ t('display.studio.rotation.addPage') }}</EButton>
      <template v-if="currentStep">
        <ETextField :model-value="currentStep.name ?? ''" maxlength="80" :label="t('display.studio.rotation.pageTitle')" hide-details data-testid="rotation-page-name" @update:model-value="setStepName($event)" />
        <ETextField :model-value="currentStep.duration_sec ? String(currentStep.duration_sec) : ''" type="number" :min="limits.min_interval_sec" :max="limits.max_interval_sec" :label="t('display.layout.editor.stepDuration')" :placeholder="t('display.layout.editor.stepDurationDefault')" hide-details data-testid="rotation-page-duration" @update:model-value="setDuration($event)" />
        <WidgetConfigForm v-if="currentStep.content.type === 'widget' && stepWidgetDef" :def="stepWidgetDef" :config="currentStep.content.config" @update="(k, v) => setStepConfig(k, v)" />
        <ESelect v-else-if="currentStep.content.type === 'page'" :model-value="currentStep.content.page_id" :items="embeddablePages" :label="t('display.layout.editor.embeddedPage')" hide-details @update:model-value="setStepPage($event)" />
      </template>
    </template>

    <!-- Gruppe mit eigenem Raster -->
    <template v-else-if="item.content.type === 'group'">
      <ETextField :model-value="item.content.name ?? ''" maxlength="80" :label="t('display.studio.group.name')" hide-details data-testid="group-name" @update:model-value="setGroupName($event)" />
      <p class="insp__hint">{{ t('display.studio.group.gridInfo', { cols: item.content.columns ?? 12, rows: item.content.rows, n: item.content.items.length }) }}</p>
      <div class="insp__actions">
        <EButton variant="secondary" size="small" :disabled="!canRefine" @click="emit('refine', item.id, 2)">{{ t('display.studio.group.refine') }}</EButton>
        <EButton variant="secondary" size="small" :disabled="!canCoarsen" @click="emit('coarsen', item.id, 2)">{{ t('display.studio.group.coarsen') }}</EButton>
      </div>
    </template>

    <div class="insp__actions">
      <EButton v-if="item.content.type === 'widget' || item.content.type === 'group' || item.content.type === 'rotation'" variant="secondary" size="small" data-testid="btn-isolate" @click="emit('open', item.id)">{{ t('display.studio.actions.editSeparately') }}</EButton>
      <EButton v-if="item.content.type === 'group'" variant="secondary" size="small" data-testid="btn-ungroup" @click="emit('ungroup')">{{ t('display.studio.actions.ungroup') }}</EButton>
      <EButton v-if="item.content.type === 'widget' || item.content.type === 'group' || item.content.type === 'rotation'" variant="secondary" size="small" data-testid="btn-save-block" @click="emit('saveBlock')">{{ t('display.studio.actions.saveAsBlock') }}</EButton>
      <EButton v-if="item.content.type === 'widget'" variant="secondary" size="small" @click="wrapInRotation">{{ t('display.layout.editor.toRotation') }}</EButton>
      <EButton variant="secondary" size="small" @click="emit('duplicate')">{{ t('display.layout.editor.duplicate') }}</EButton>
      <EButton variant="danger" size="small" @click="emit('remove')">{{ t('display.layout.editor.remove') }}</EButton>
    </div>
  </div>
  <p v-else class="insp__hint">{{ t('display.layout.editor.nothingSelected') }}</p>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { LayoutCatalog, LayoutContent, LayoutDocument, LayoutItem, LayoutRotationContent, WidgetDefinition } from '@/api/displayLayout'
import { EButton, ECheckbox, ESelect, ETextField } from '@/components/form/base'
import { COLUMNS, canEmbed, clampGeometry, pageIssues } from '../layoutEditing'
import { MAX_GRID, addRotationPage, colsOf, duplicateRotationPage, isFree, moveRotationPage, type GridHost } from '../studioModel'
import WidgetConfigForm from './WidgetConfigForm.vue'

/**
 * Eigenschaften des gewählten Elements. Änderungen laufen über `mutate`, damit der Editor die Bearbeitung
 * zentral als „geändert“ markieren kann.
 */
const props = defineProps<{
  doc: LayoutDocument
  /** Rasterfläche, in der das Element liegt (Seite oder Gruppe). */
  host: GridHost
  /** Seite, auf der die Bearbeitung stattfindet (für die Zyklusprüfung eingebetteter Seiten). */
  pageId: string
  item: LayoutItem | null
  /** Einzelbearbeitung: Lage im Raster ist fest, nur Inhalt und Eigenschaften sind änderbar. */
  isolated?: boolean
  widgetDefs: Map<string, WidgetDefinition>
  widgets: WidgetDefinition[]
  limits: LayoutCatalog['limits']
}>()

const emit = defineEmits<{
  mutate: [fn: (item: LayoutItem) => void]
  duplicate: []
  remove: []
  open: [id: string]
  openStep: [id: string, index: number]
  ungroup: []
  saveBlock: []
  refine: [id: string, factor: number]
  coarsen: [id: string, factor: number]
  blocked: []
}>()

const { t } = useI18n()
const stepIndex = ref(0)

watch(() => props.item?.id, () => {
  stepIndex.value = 0
})

const rows = computed(() => props.host.rows)
const columns = computed(() => colsOf(props.host))
const group = computed(() => (props.item?.content.type === 'group' ? props.item.content : null))
const canRefine = computed(() => !!group.value && (group.value.columns ?? COLUMNS) * 2 <= MAX_GRID && group.value.rows * 2 <= MAX_GRID)
const canCoarsen = computed(() => {
  const g = group.value
  if (!g) return false
  return (g.columns ?? COLUMNS) % 2 === 0 && g.rows % 2 === 0 && g.items.every((i) => i.x % 2 === 0 && i.y % 2 === 0 && i.w % 2 === 0 && i.h % 2 === 0)
})
const widgetDef = computed(() => (props.item?.content.type === 'widget' ? props.widgetDefs.get(props.item.content.widget) ?? null : null))
const title = computed(() => {
  const c = props.item?.content
  if (!c) return ''
  if (c.type === 'widget') return t(`display.layout.widget.${c.widget}`)
  return t(`display.layout.content.${c.type}`, { name: '' }).trim()
})
const issueText = computed(() => {
  if (!props.item) return ''
  const issue = pageIssues(props.host, props.widgetDefs).find((i) => i.itemId === props.item!.id)
  return issue ? t(`display.layout.editor.issue.${issue.code}`) : ''
})

const embeddablePages = computed(() =>
  props.doc.pages
    .filter((p) => canEmbed(props.doc, props.pageId, p.id) || (props.item?.content.type === 'page' && props.item.content.page_id === p.id))
    .map((p) => ({ value: p.id, title: p.name || p.id })),
)

const rotation = computed(() => (props.item?.content.type === 'rotation' ? props.item.content : null))
const currentStep = computed(() => rotation.value?.steps[stepIndex.value] ?? null)
const stepWidgetDef = computed(() => (currentStep.value?.content.type === 'widget' ? props.widgetDefs.get(currentStep.value.content.widget) ?? null : null))
function stepLabel(content: LayoutContent): string {
  if (content.type === 'widget') return t(`display.layout.widget.${content.widget}`)
  if (content.type === 'page') return t('display.layout.content.page', { name: props.doc.pages.find((p) => p.id === content.page_id)?.name ?? '' })
  return t(`display.layout.content.${content.type}`, { name: '' }).trim()
}

function clampInterval(raw: unknown): number {
  const n = Math.round(Number(raw))
  if (Number.isNaN(n)) return props.limits.default_interval_sec
  return Math.min(props.limits.max_interval_sec, Math.max(props.limits.min_interval_sec, n))
}

function geo(key: 'x' | 'y' | 'w' | 'h', raw: unknown, offset: number) {
  const n = Number(raw)
  if (raw === '' || Number.isNaN(n) || !props.item) return
  const next = clampGeometry({ x: props.item.x, y: props.item.y, w: props.item.w, h: props.item.h, [key]: n + offset }, rows.value, 1, 1, columns.value)
  // Überlappungen sind nicht erlaubt: eine blockierte Eingabe lässt das Element unverändert.
  if (!isFree(props.host, next, new Set([props.item.id]))) {
    emit('blocked')
    return
  }
  emit('mutate', (item) => {
    Object.assign(item, next)
  })
}

function setGroupName(raw: unknown) {
  emit('mutate', (item) => {
    if (item.content.type !== 'group') return
    const name = String(raw ?? '').trim()
    if (name) item.content.name = name
    else delete item.content.name
  })
}

function setConfig(key: string, value: unknown) {
  emit('mutate', (item) => {
    if (item.content.type === 'widget') item.content.config[key] = value
  })
}

function setPage(pageId: unknown) {
  emit('mutate', (item) => {
    if (item.content.type === 'page') item.content.page_id = String(pageId)
  })
}

function mutateRotation(fn: (r: LayoutRotationContent) => void) {
  emit('mutate', (item) => {
    if (item.content.type === 'rotation') fn(item.content)
  })
}

function moveStep(i: number, delta: number) {
  mutateRotation((r) => {
    stepIndex.value = moveRotationPage(r, i, delta)
  })
}

function removeStep(i: number) {
  mutateRotation((r) => {
    if (r.steps.length > 1) r.steps.splice(i, 1)
  })
  stepIndex.value = 0
}

function addPage() {
  mutateRotation((r) => {
    stepIndex.value = addRotationPage(r, t('display.studio.rotation.pageName', { n: r.steps.length + 1 }))
  })
}

function duplicateStep(i: number) {
  mutateRotation((r) => {
    const name = stepTitle(r.steps[i]!, i)
    stepIndex.value = duplicateRotationPage(r, i, t('display.studio.rotation.copyName', { name }))
  })
}

/** Reihenfolge per Drag-and-drop in der Seitenliste. */
const dragFrom = ref<number | null>(null)
const dropIndex = ref<number | null>(null)
function dropOn(target: number) {
  const from = dragFrom.value
  dragFrom.value = null
  dropIndex.value = null
  if (from === null || from === target) return
  mutateRotation((r) => {
    stepIndex.value = moveRotationPage(r, from, target - from)
  })
}

function stepTitle(step: { name?: string; content: LayoutContent }, i: number): string {
  return step.name || (step.content.type === 'group' ? t('display.studio.rotation.pageName', { n: i + 1 }) : stepLabel(step.content))
}

function setStepName(raw: unknown) {
  mutateRotation((r) => {
    const step = r.steps[stepIndex.value]
    if (!step) return
    const name = String(raw ?? '').trim()
    if (name) step.name = name
    else delete step.name
  })
}

function setDuration(raw: unknown) {
  const empty = raw === '' || raw === null
  mutateRotation((r) => {
    const step = r.steps[stepIndex.value]
    if (step) step.duration_sec = empty ? null : clampInterval(raw)
  })
}

function setStepConfig(key: string, value: unknown) {
  mutateRotation((r) => {
    const content = r.steps[stepIndex.value]?.content
    if (content?.type === 'widget') content.config[key] = value
  })
}

function setStepPage(pageId: unknown) {
  mutateRotation((r) => {
    const content = r.steps[stepIndex.value]?.content
    if (content?.type === 'page') content.page_id = String(pageId)
  })
}

function wrapInRotation() {
  emit('mutate', (item) => {
    if (item.content.type !== 'widget') return
    item.content = {
      type: 'rotation',
      enabled: true,
      interval_sec: props.limits.default_interval_sec,
      steps: [{ duration_sec: null, content: item.content }],
    }
  })
}
</script>

<style scoped>
.insp { display: flex; flex-direction: column; gap: 10px; }
.insp__title { margin: 0; font-size: 1rem; }
.insp__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.insp__hint { margin: 0; font-size: 0.8rem; color: #6b7280; }
.insp__issue { margin: 0; font-size: 0.85rem; color: #b91c1c; }
.insp__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
.insp__steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.insp__steps li { display: flex; align-items: center; justify-content: space-between; gap: 6px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 2px 6px; }
.insp__step--on { border-color: #2563eb !important; background: #eff6ff; }
.insp__step--drop { border-style: dashed !important; border-color: #2563eb !important; }
.insp__step-btn { flex: 1; text-align: left; background: none; border: 0; padding: 6px 0; cursor: pointer; }
.insp__step-actions { display: flex; gap: 2px; }
.insp__step-actions button { border: 0; background: none; cursor: pointer; padding: 2px 6px; }
.insp__add-step { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: end; }
</style>
