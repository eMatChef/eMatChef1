<template>
  <div v-if="item" class="insp" data-testid="item-inspector">
    <h4 class="insp__title">{{ title }}</h4>

    <div class="insp__grid">
      <ETextField :model-value="String(item.x + 1)" type="number" min="1" max="12" :label="t('display.layout.editor.col')" hide-details @update:model-value="geo('x', $event, -1)" />
      <ETextField :model-value="String(item.y + 1)" type="number" min="1" :max="rows" :label="t('display.layout.editor.row')" hide-details @update:model-value="geo('y', $event, -1)" />
      <ETextField :model-value="String(item.w)" type="number" min="1" max="12" :label="t('display.layout.editor.width')" hide-details @update:model-value="geo('w', $event, 0)" />
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
      <ol class="insp__steps">
        <li v-for="(step, i) in item.content.steps" :key="i" :class="{ 'insp__step--on': i === stepIndex }">
          <button type="button" class="insp__step-btn" @click="stepIndex = i">
            {{ i + 1 }}. {{ stepLabel(step.content) }}<small v-if="step.duration_sec"> · {{ step.duration_sec }} s</small>
          </button>
          <span class="insp__step-actions">
            <button type="button" :aria-label="t('display.layout.editor.moveUp')" :disabled="i === 0" @click="moveStep(i, -1)">↑</button>
            <button type="button" :aria-label="t('display.layout.editor.moveDown')" :disabled="i === item.content.steps.length - 1" @click="moveStep(i, 1)">↓</button>
            <button type="button" :aria-label="t('common.delete')" :disabled="item.content.steps.length <= 1" @click="removeStep(i)">✕</button>
          </span>
        </li>
      </ol>
      <div class="insp__add-step">
        <ESelect v-model="newStep" :items="stepChoices" :label="t('display.layout.editor.addStep')" hide-details />
        <EButton variant="secondary" size="small" :disabled="!newStep || item.content.steps.length >= limits.max_steps" @click="addStep">{{ t('common.add') }}</EButton>
      </div>
      <template v-if="currentStep">
        <ETextField :model-value="currentStep.duration_sec ? String(currentStep.duration_sec) : ''" type="number" :min="limits.min_interval_sec" :max="limits.max_interval_sec" :label="t('display.layout.editor.stepDuration')" :placeholder="t('display.layout.editor.stepDurationDefault')" hide-details @update:model-value="setDuration($event)" />
        <WidgetConfigForm v-if="currentStep.content.type === 'widget' && stepWidgetDef" :def="stepWidgetDef" :config="currentStep.content.config" @update="(k, v) => setStepConfig(k, v)" />
        <ESelect v-else-if="currentStep.content.type === 'page'" :model-value="currentStep.content.page_id" :items="embeddablePages" :label="t('display.layout.editor.embeddedPage')" hide-details @update:model-value="setStepPage($event)" />
      </template>
    </template>

    <p v-else class="insp__hint">{{ t('display.layout.editor.groupHint') }}</p>

    <div class="insp__actions">
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
import type { LayoutCatalog, LayoutContent, LayoutDocument, LayoutItem, LayoutPage, LayoutRotationContent, WidgetDefinition } from '@/api/displayLayout'
import { EButton, ECheckbox, ESelect, ETextField } from '@/components/form/base'
import { canEmbed, clampGeometry, pageIssues, widgetContent } from '../layoutEditing'
import WidgetConfigForm from './WidgetConfigForm.vue'

/**
 * Eigenschaften des gewählten Elements. Änderungen laufen über `mutate`, damit der Editor die Bearbeitung
 * zentral als „geändert“ markieren kann.
 */
const props = defineProps<{
  doc: LayoutDocument
  page: LayoutPage
  item: LayoutItem | null
  widgetDefs: Map<string, WidgetDefinition>
  widgets: WidgetDefinition[]
  limits: LayoutCatalog['limits']
}>()

const emit = defineEmits<{
  mutate: [fn: (item: LayoutItem) => void]
  duplicate: []
  remove: []
}>()

const { t } = useI18n()
const stepIndex = ref(0)
const newStep = ref<string | null>(null)

watch(() => props.item?.id, () => {
  stepIndex.value = 0
  newStep.value = null
})

const rows = computed(() => props.page.rows)
const widgetDef = computed(() => (props.item?.content.type === 'widget' ? props.widgetDefs.get(props.item.content.widget) ?? null : null))
const title = computed(() => {
  const c = props.item?.content
  if (!c) return ''
  if (c.type === 'widget') return t(`display.layout.widget.${c.widget}`)
  return t(`display.layout.content.${c.type}`, { name: '' }).trim()
})
const issueText = computed(() => {
  if (!props.item) return ''
  const issue = pageIssues(props.page, props.widgetDefs).find((i) => i.itemId === props.item!.id)
  return issue ? t(`display.layout.editor.issue.${issue.code}`) : ''
})

const embeddablePages = computed(() =>
  props.doc.pages
    .filter((p) => canEmbed(props.doc, props.page.id, p.id) || (props.item?.content.type === 'page' && props.item.content.page_id === p.id))
    .map((p) => ({ value: p.id, title: p.name || p.id })),
)

const rotation = computed(() => (props.item?.content.type === 'rotation' ? props.item.content : null))
const currentStep = computed(() => rotation.value?.steps[stepIndex.value] ?? null)
const stepWidgetDef = computed(() => (currentStep.value?.content.type === 'widget' ? props.widgetDefs.get(currentStep.value.content.widget) ?? null : null))
const stepChoices = computed(() => [
  ...props.widgets.map((w) => ({ value: `w:${w.type}`, title: t(`display.layout.widget.${w.type}`) })),
  ...embeddablePages.value.map((p) => ({ value: `p:${p.value}`, title: t('display.layout.content.page', { name: p.title }) })),
])

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
  if (raw === '' || Number.isNaN(n)) return
  emit('mutate', (item) => {
    const next = { x: item.x, y: item.y, w: item.w, h: item.h, [key]: n + offset }
    Object.assign(item, clampGeometry(next, rows.value))
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
    const j = i + delta
    if (j < 0 || j >= r.steps.length) return
    const [step] = r.steps.splice(i, 1)
    r.steps.splice(j, 0, step!)
  })
  stepIndex.value = Math.max(0, Math.min(rotation.value ? rotation.value.steps.length - 1 : 0, i + delta))
}

function removeStep(i: number) {
  mutateRotation((r) => {
    if (r.steps.length > 1) r.steps.splice(i, 1)
  })
  stepIndex.value = 0
}

function addStep() {
  const choice = newStep.value
  if (!choice) return
  const [kind, id] = [choice.slice(0, 1), choice.slice(2)]
  mutateRotation((r) => {
    if (kind === 'w') {
      const def = props.widgetDefs.get(id)
      if (def) r.steps.push({ duration_sec: null, content: widgetContent(def) })
    } else {
      r.steps.push({ duration_sec: null, content: { type: 'page', page_id: id } })
    }
  })
  newStep.value = null
  stepIndex.value = (rotation.value?.steps.length ?? 1) - 1
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
.insp__step-btn { flex: 1; text-align: left; background: none; border: 0; padding: 6px 0; cursor: pointer; }
.insp__step-actions { display: flex; gap: 2px; }
.insp__step-actions button { border: 0; background: none; cursor: pointer; padding: 2px 6px; }
.insp__add-step { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: end; }
</style>
