<template>
  <div class="editor">
    <header class="editor__head">
      <div class="editor__titles">
        <router-link class="editor__back" :to="backTo">← {{ t('display.layout.editor.back') }}</router-link>
        <h1>{{ t('display.layout.editor.title', { name: screenName }) }}</h1>
        <p class="muted">{{ t('display.layout.editor.intro') }}</p>
      </div>
      <div v-if="doc" class="editor__status">
        <span class="chip" :class="published ? 'chip--ok' : 'chip--idle'">
          {{ published ? t('display.layout.editor.statusPublished', { n: published.number, date: formatDate(published.published_at) }) : t('display.layout.editor.statusNotPublished') }}
        </span>
        <span v-if="dirty" class="chip chip--warn" data-testid="status-unsaved">{{ t('display.layout.editor.statusUnsaved') }}</span>
        <span v-else-if="hasUnpublished" class="chip chip--warn" data-testid="status-unpublished">{{ t('display.layout.editor.statusUnpublished') }}</span>
        <span v-else class="chip chip--ok">{{ t('display.layout.editor.statusSaved') }}</span>
      </div>
    </header>

    <ELoadingState v-if="loading" variant="inline" :message="t('settings.displayScreens.loading')" />
    <p v-else-if="loadError" class="error" role="alert">{{ loadError }}</p>

    <!-- Startpunkt wählen (noch kein Entwurf) -->
    <section v-else-if="!doc" class="card">
      <h2>{{ t('display.layout.editor.chooserTitle') }}</h2>
      <LayoutStartChooser :templates="catalog?.templates ?? []" :sources="copySources" @choose="initialize" />
    </section>

    <template v-else>
      <div class="toolbar">
        <div class="toolbar__group">
          <ESelect v-model="variantId" :items="variantItems" :label="t('display.layout.editor.variant')" hide-details class="toolbar__select" />
          <EButton variant="secondary" size="small" :disabled="doc.variants.length >= limits.max_variants" @click="newVariantOpen = true">{{ t('display.layout.editor.addVariant') }}</EButton>
        </div>
        <div class="toolbar__group toolbar__group--actions">
          <EButton variant="secondary" size="small" @click="chooserOpen = true">{{ t('display.layout.editor.replaceTemplate') }}</EButton>
          <EButton variant="secondary" size="small" @click="openRevisions">{{ t('display.layout.editor.revisions') }}</EButton>
          <EButton variant="secondary" size="small" data-testid="btn-preview" @click="previewOpen = true">{{ t('display.layout.editor.preview') }}</EButton>
          <EButton variant="secondary" size="small" :disabled="dirty" :title="dirty ? t('display.layout.editor.saveFirst') : ''" @click="openFullPreview">{{ t('display.layout.editor.previewTab') }}</EButton>
          <EButton variant="secondary" size="small" :disabled="!dirty" :loading="saving" data-testid="btn-save" @click="save">{{ t('display.layout.editor.save') }}</EButton>
          <EButton variant="primary" size="small" :loading="publishChecking" data-testid="btn-publish" @click="startPublish">{{ t('display.layout.editor.publish') }}</EButton>
        </div>
      </div>

      <div class="editor__body">
        <aside class="panel panel--left">
          <h3>{{ t('display.layout.editor.library') }}</h3>
          <WidgetPalette :widgets="catalog?.widgets ?? []" @add="addWidget" />
          <h4 class="panel__sub">{{ t('display.layout.editor.zones') }}</h4>
          <div class="panel__zones">
            <EButton variant="secondary" size="small" @click="addRotationZone">{{ t('display.layout.editor.addRotationZone') }}</EButton>
            <EButton variant="secondary" size="small" :disabled="!embeddableForCurrent.length" @click="addEmbeddedZone">{{ t('display.layout.editor.addEmbedded') }}</EButton>
          </div>
          <h3 class="panel__sub">{{ t('display.layout.editor.pages') }}</h3>
          <ul class="pages">
            <li v-for="p in doc.pages" :key="p.id" :class="{ 'pages__item--on': p.id === pageId }">
              <button type="button" class="pages__btn" :data-testid="`page-${p.id}`" @click="selectPage(p.id)">{{ p.name || p.id }}</button>
            </li>
          </ul>
          <div class="panel__zones">
            <EButton variant="secondary" size="small" :disabled="doc.pages.length >= limits.max_pages" @click="onAddPage">{{ t('display.layout.editor.addPage') }}</EButton>
            <EButton variant="secondary" size="small" @click="renameOpen = true">{{ t('display.layout.editor.renamePage') }}</EButton>
            <EButton variant="danger" size="small" :disabled="!canDeletePage" :title="canDeletePage ? '' : t('display.layout.editor.pageInUse')" @click="deletePage">{{ t('display.layout.editor.deletePage') }}</EButton>
          </div>
        </aside>

        <main class="stage">
          <div v-if="page" class="stage__bar">
            <strong>{{ page.name || page.id }}</strong>
            <span class="muted">{{ t('display.layout.editor.stageHint', { aspect: variant?.aspect_ratio ?? '' }) }}</span>
          </div>
          <EditorCanvas
            v-if="page && variant"
            :doc="doc"
            :page="page"
            :selected-id="selectedId"
            :aspect-ratio="variant.aspect_ratio"
            :font-scale="1"
            :widget-defs="widgetDefs"
            @select="selectedId = $event"
            @change="markDirty"
            @drop="onDrop"
          />
          <p v-if="pageProblems.length" class="stage__issues" role="status">{{ t('display.layout.editor.pageProblems', { n: pageProblems.length }) }}</p>
        </main>

        <aside class="panel panel--right">
          <div class="tabs" role="tablist">
            <button type="button" role="tab" :aria-selected="tab === 'item'" :class="{ 'tabs__btn--on': tab === 'item' }" class="tabs__btn" @click="tab = 'item'">{{ t('display.layout.editor.tabElement') }}</button>
            <button type="button" role="tab" :aria-selected="tab === 'display'" :class="{ 'tabs__btn--on': tab === 'display' }" class="tabs__btn" data-testid="tab-display" @click="tab = 'display'">{{ t('display.layout.editor.tabDisplay') }}</button>
          </div>
          <ItemInspector
            v-if="tab === 'item' && page"
            :doc="doc"
            :page="page"
            :item="selectedItem"
            :widget-defs="widgetDefs"
            :widgets="catalog?.widgets ?? []"
            :limits="limits"
            @mutate="mutateItem"
            @duplicate="duplicateItem"
            @remove="removeItem"
          />
          <div v-else-if="variant" class="flow">
            <ETextField :model-value="variant.name" :label="t('display.layout.editor.variantName')" maxlength="80" hide-details @update:model-value="renameVariant($event)" />
            <ESelect :model-value="variant.aspect_ratio" :items="aspectItems" :label="t('display.layout.editor.variantAspect')" hide-details @update:model-value="setAspect($event)" />
            <ECheckbox :model-value="doc.default_variant_id === variant.id" :disabled="doc.default_variant_id === variant.id" :label="t('display.layout.editor.variantDefault')" hide-details @update:model-value="doc.default_variant_id = variant.id; markDirty()" />
            <EButton variant="danger" size="small" :disabled="doc.variants.length <= 1" @click="deleteVariant">{{ t('display.layout.editor.deleteVariant') }}</EButton>

            <h4 class="panel__sub">{{ t('display.layout.editor.flowTitle') }}</h4>
            <p v-if="!flow.simple" class="muted">{{ t('display.layout.editor.flowComplex') }}</p>
            <template v-else>
              <p class="muted">{{ t('display.layout.editor.flowHint') }}</p>
              <ol class="flow__list">
                <li v-for="(id, i) in flow.pageIds" :key="`${id}-${i}`">
                  <span>{{ i + 1 }}. {{ pageName(id) }}</span>
                  <ETextField v-if="flow.pageIds.length > 1" :model-value="flow.durations[i] ? String(flow.durations[i]) : ''" type="number" :min="limits.min_interval_sec" :max="limits.max_interval_sec" :placeholder="String(flow.interval)" :label="t('display.layout.editor.stepDuration')" hide-details class="flow__dur" @update:model-value="setFlowDuration(i, $event)" />
                  <span class="flow__actions">
                    <button type="button" :disabled="i === 0" :aria-label="t('display.layout.editor.moveUp')" @click="moveFlow(i, -1)">↑</button>
                    <button type="button" :disabled="i === flow.pageIds.length - 1" :aria-label="t('display.layout.editor.moveDown')" @click="moveFlow(i, 1)">↓</button>
                    <button type="button" :disabled="flow.pageIds.length <= 1" :aria-label="t('common.delete')" @click="removeFlow(i)">✕</button>
                  </span>
                </li>
              </ol>
              <div class="flow__add">
                <ESelect v-model="flowAdd" :items="pageItems" :label="t('display.layout.editor.flowAdd')" hide-details />
                <EButton variant="secondary" size="small" :disabled="!flowAdd" @click="addFlow">{{ t('common.add') }}</EButton>
              </div>
              <template v-if="flow.pageIds.length > 1">
                <ECheckbox :model-value="flow.enabled" :label="t('display.layout.editor.rotationEnabled')" hide-details @update:model-value="setFlowEnabled($event === true)" />
                <ETextField :model-value="String(flow.interval)" type="number" :min="limits.min_interval_sec" :max="limits.max_interval_sec" :label="t('display.layout.editor.interval')" hide-details @update:model-value="setFlowInterval($event)" />
              </template>
            </template>
          </div>
        </aside>
      </div>
    </template>

    <!-- Vorschau mit Geräteprofil -->
    <EDialog v-model="previewOpen" :max-width="1100" :title="t('display.layout.editor.previewTitle')" :retain-focus="false">
      <div class="preview-bar">
        <ESelect v-model="deviceKey" :items="deviceItems" :label="t('display.layout.editor.previewDevice')" hide-details class="preview-bar__select" />
        <span class="muted">{{ t('display.layout.editor.previewVariant', { name: previewVariantName }) }}</span>
      </div>
      <div class="preview-frame" :style="{ aspectRatio: previewAspect, width: `min(100%, calc(60vh * ${previewAspect}))` }" data-testid="preview-frame">
        <DisplayLayoutRenderer v-if="doc && previewOpen" :key="`${deviceKey}-${previewVariantId}`" :layout="doc" :variant-id="previewVariantId" :font-scale="previewDevice.fontScale" />
      </div>
      <p class="muted">{{ t('display.layout.editor.previewNote') }}</p>
      <template #actions>
        <EButton variant="secondary" size="small" @click="previewOpen = false">{{ t('common.close') }}</EButton>
      </template>
    </EDialog>

    <!-- Veröffentlichen -->
    <EDialog v-model="publishOpen" :max-width="560" :title="t('display.layout.editor.publishTitle')">
      <template v-if="publishErrors.length">
        <p class="error">{{ t('display.layout.editor.publishBlocked') }}</p>
        <ul class="issues">
          <li v-for="(e, i) in publishErrors" :key="i">{{ errorText(e) }}</li>
        </ul>
      </template>
      <template v-else>
        <p>{{ t('display.layout.editor.publishText', { n: (published?.number ?? 0) + 1 }) }}</p>
        <p class="muted">{{ t('display.layout.editor.publishHint') }}</p>
      </template>
      <template #actions>
        <EButton variant="secondary" size="small" @click="publishOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton v-if="!publishErrors.length" variant="primary" size="small" :loading="publishing" data-testid="btn-publish-confirm" @click="publish">{{ t('display.layout.editor.publishConfirm') }}</EButton>
      </template>
    </EDialog>

    <!-- Speicherfehler -->
    <EDialog v-model="errorsOpen" :max-width="560" :title="t('display.layout.editor.saveErrorTitle')">
      <ul class="issues">
        <li v-for="(e, i) in saveErrors" :key="i">{{ errorText(e) }}</li>
      </ul>
      <template #actions>
        <EButton variant="secondary" size="small" @click="errorsOpen = false">{{ t('common.close') }}</EButton>
      </template>
    </EDialog>

    <!-- Versionskonflikt -->
    <EDialog v-model="conflictOpen" :max-width="480" :title="t('display.layout.editor.conflictTitle')">
      <p>{{ t('display.layout.editor.conflictText') }}</p>
      <template #actions>
        <EButton variant="secondary" size="small" @click="conflictOpen = false">{{ t('display.layout.editor.conflictKeep') }}</EButton>
        <EButton variant="primary" size="small" @click="reloadFromServer">{{ t('display.layout.editor.conflictReload') }}</EButton>
      </template>
    </EDialog>

    <!-- Neue Variante -->
    <EDialog v-model="newVariantOpen" :max-width="440" :title="t('display.layout.editor.addVariant')">
      <ETextField v-model="newVariantName" :label="t('display.layout.editor.variantName')" maxlength="80" hide-details />
      <ESelect v-model="newVariantAspect" :items="aspectItems" :label="t('display.layout.editor.variantAspect')" class="mt-3" hide-details />
      <p class="muted mt-2">{{ t('display.layout.editor.variantCopyHint') }}</p>
      <template #actions>
        <EButton variant="secondary" size="small" @click="newVariantOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :disabled="!newVariantName.trim()" @click="createVariant">{{ t('common.create') }}</EButton>
      </template>
    </EDialog>

    <!-- Seite umbenennen -->
    <EDialog v-model="renameOpen" :max-width="420" :title="t('display.layout.editor.renamePage')">
      <ETextField v-model="renameValue" :label="t('display.layout.editor.pageName')" maxlength="80" hide-details />
      <template #actions>
        <EButton variant="secondary" size="small" @click="renameOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :disabled="!renameValue.trim()" @click="applyRename">{{ t('common.save') }}</EButton>
      </template>
    </EDialog>

    <!-- Vorlage ersetzen -->
    <EDialog v-model="chooserOpen" :max-width="860" :title="t('display.layout.editor.replaceTemplate')" :retain-focus="false">
      <p class="error">{{ t('display.layout.editor.replaceWarning') }}</p>
      <LayoutStartChooser :templates="catalog?.templates ?? []" :sources="copySources.filter((s) => s.id !== screenId)" @choose="replaceFrom" />
    </EDialog>

    <!-- Revisionen -->
    <EDialog v-model="revisionsOpen" :max-width="620" :title="t('display.layout.editor.revisions')">
      <p v-if="!revisions.length" class="muted">{{ t('display.layout.editor.revisionsNone') }}</p>
      <ul v-else class="revisions">
        <li v-for="r in revisions" :key="r.number">
          <div>
            <strong>{{ t('display.layout.editor.revisionNumber', { n: r.number }) }}</strong>
            <span class="muted"> · {{ formatDate(r.published_at) }}</span>
            <span v-if="r.restored_from_number" class="muted"> · {{ t('display.layout.editor.restoredFrom', { n: r.restored_from_number }) }}</span>
            <span v-if="published?.number === r.number" class="chip chip--ok">{{ t('display.layout.editor.active') }}</span>
          </div>
          <div class="revisions__actions">
            <EButton variant="secondary" size="small" @click="restore(r.number, false)">{{ t('display.layout.editor.restoreDraft') }}</EButton>
            <EButton variant="secondary" size="small" @click="restore(r.number, true)">{{ t('display.layout.editor.restorePublish') }}</EButton>
          </div>
        </li>
      </ul>
      <template #actions>
        <EButton variant="secondary" size="small" @click="revisionsOpen = false">{{ t('common.close') }}</EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { EButton, ECheckbox, EDialog, ESelect, ETextField } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import {
  getLayoutCatalog,
  getLayoutState,
  initializeLayout,
  layoutErrorInfo,
  listLayoutRevisions,
  publishLayout,
  restoreLayoutRevision,
  saveLayoutDraft,
  validateLayoutDraft,
  type LayoutCatalog,
  type LayoutDocument,
  type LayoutError,
  type LayoutItem,
  type LayoutRevisionInfo,
  type LayoutState,
  type WidgetDefinition,
} from '@/api/displayLayout'
import { listDisplayScreens, type DisplayScreenSettings } from '@/api/displayScreens'
import { listDisplayDevices, type DisplayDeviceRow } from '@/api/displayDevice'
import DisplayLayoutRenderer from '@/components/display/layout/DisplayLayoutRenderer.vue'
import EditorCanvas from '@/components/display/layout/editor/EditorCanvas.vue'
import ItemInspector from '@/components/display/layout/editor/ItemInspector.vue'
import LayoutStartChooser from '@/components/display/layout/editor/LayoutStartChooser.vue'
import WidgetPalette from '@/components/display/layout/editor/WidgetPalette.vue'
import { cloneLayout, newId } from '@/components/display/layout/layoutModel'
import {
  addItem,
  addPage,
  addVariant,
  canEmbed,
  findFreeSpot,
  isPageReferenced,
  pageIssues,
  pickVariant,
  profileAspect,
  profileFontScale,
  ratioOf,
  removeVariant,
  setVariantFlow,
  variantFlow,
  widgetContent,
  type SimulatedDevice,
} from '@/components/display/layout/layoutEditing'

/**
 * WYSIWYG-Editor für das Layout eines Infoscreens: Entwurf bearbeiten, Vorschau für verschiedene Bildschirmformate,
 * Entwurf speichern, explizit veröffentlichen und frühere Revisionen wiederherstellen. Geräte sehen ausschliesslich
 * die veröffentlichte Revision; der Editor arbeitet nur am Entwurf.
 */
const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const departmentId = computed(() => String(route.params.departmentId || ''))
const screenId = computed(() => String(route.params.screenId || ''))
const backTo = computed(() => `/${departmentId.value}/ga/displays`)

const loading = ref(true)
const loadError = ref<string | null>(null)
const catalog = ref<LayoutCatalog | null>(null)
const screenName = ref('')
const screens = ref<DisplayScreenSettings[]>([])
const devices = ref<DisplayDeviceRow[]>([])

const doc = ref<LayoutDocument | null>(null)
const version = ref(0)
const published = ref<LayoutRevisionInfo | null>(null)
const hasUnpublished = ref(false)
const dirty = ref(false)
const saving = ref(false)

const variantId = ref('')
const pageId = ref('')
const selectedId = ref<string | null>(null)
const tab = ref<'item' | 'display'>('item')

const limits = computed(() => catalog.value?.limits ?? {
  columns: 12, default_rows: 12, max_rows: 48, max_depth: 6, max_variants: 6, max_pages: 20, max_items: 40, max_steps: 20,
  min_interval_sec: 3, max_interval_sec: 3600, default_interval_sec: 20,
})
const widgetDefs = computed(() => new Map<string, WidgetDefinition>((catalog.value?.widgets ?? []).map((w) => [w.type, w])))
const copySources = computed(() => screens.value.filter((s) => !s.revoked_at && s.id !== screenId.value && s.layout?.has_draft))

const variant = computed(() => doc.value?.variants.find((v) => v.id === variantId.value) ?? doc.value?.variants[0] ?? null)
const page = computed(() => doc.value?.pages.find((p) => p.id === pageId.value) ?? doc.value?.pages[0] ?? null)
const selectedItem = computed<LayoutItem | null>(() => page.value?.items.find((i) => i.id === selectedId.value) ?? null)
const variantItems = computed(() => (doc.value?.variants ?? []).map((v) => ({ value: v.id, title: `${v.name} (${v.aspect_ratio})${v.id === doc.value?.default_variant_id ? ' ★' : ''}` })))
const pageItems = computed(() => (doc.value?.pages ?? []).map((p) => ({ value: p.id, title: p.name || p.id })))
const ASPECTS = ['16:9', '16:10', '4:3', '3:2', '21:9', '9:16']
const aspectItems = ASPECTS.map((a) => ({ value: a, title: a }))
const embeddableForCurrent = computed(() => (doc.value && page.value ? doc.value.pages.filter((p) => canEmbed(doc.value!, page.value!.id, p.id)) : []))
const canDeletePage = computed(() => !!doc.value && !!page.value && doc.value.pages.length > 1 && !isPageReferenced(doc.value, page.value.id))
const pageProblems = computed(() => (page.value ? pageIssues(page.value, widgetDefs.value) : []))
const flow = computed(() => (variant.value ? variantFlow(variant.value) : { rotation: false, enabled: false, interval: 20, pageIds: [], durations: [], simple: false }))

function formatDate(iso: string): string {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleString(String(locale.value).startsWith('de') ? 'de-CH' : 'en-CH', { dateStyle: 'medium', timeStyle: 'short' })
}

function pageName(id: string): string {
  return doc.value?.pages.find((p) => p.id === id)?.name || id
}

function markDirty() {
  dirty.value = true
}

function applyState(state: LayoutState, keepSelection = false) {
  doc.value = state.draft ? cloneLayout(state.draft) : null
  version.value = state.draft_version
  published.value = state.published
  hasUnpublished.value = state.has_unpublished_changes
  dirty.value = false
  if (doc.value) {
    if (!doc.value.variants.some((v) => v.id === variantId.value)) variantId.value = doc.value.default_variant_id
    if (!doc.value.pages.some((p) => p.id === pageId.value)) pageId.value = firstPageOf(doc.value)
  }
  if (!keepSelection) selectedId.value = null
}

function firstPageOf(d: LayoutDocument): string {
  const v = d.variants.find((x) => x.id === variantId.value) ?? d.variants[0]
  const first = v ? variantFlow(v).pageIds[0] : undefined
  return first && d.pages.some((p) => p.id === first) ? first : (d.pages[0]?.id ?? '')
}

// --- Laden -----------------------------------------------------------------------------------------------------

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const [cat, state, list, devs] = await Promise.all([
      getLayoutCatalog(departmentId.value),
      getLayoutState(departmentId.value, screenId.value),
      listDisplayScreens(departmentId.value),
      listDisplayDevices(departmentId.value).catch(() => [] as DisplayDeviceRow[]),
    ])
    catalog.value = cat
    screens.value = list
    devices.value = devs.filter((d) => d.screen_id === screenId.value && d.approval_state !== 'revoked')
    screenName.value = list.find((s) => s.id === screenId.value)?.name ?? ''
    variantId.value = state.draft?.default_variant_id ?? ''
    applyState(state)
    if (doc.value) pageId.value = firstPageOf(doc.value)
  } catch (err) {
    const status = layoutErrorInfo(err).status
    loadError.value = status === 403 ? t('settings.displayScreens.noPermission') : status === 404 ? t('display.layout.editor.notFound') : t('display.layout.editor.loadError')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
  window.addEventListener('beforeunload', onBeforeUnload)
})
onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))

function onBeforeUnload(event: BeforeUnloadEvent) {
  if (dirty.value) {
    event.preventDefault()
    event.returnValue = ''
  }
}

onBeforeRouteLeave(async () => {
  if (!dirty.value) return true
  return confirm.confirm({
    title: t('display.layout.editor.leaveTitle'),
    message: t('display.layout.editor.leaveMessage'),
    confirmText: t('display.layout.editor.leaveConfirm'),
    cancelText: t('common.cancel'),
    variant: 'warning',
  })
})

watch(variantId, () => {
  if (doc.value && variant.value) {
    const pages = variantFlow(variant.value).pageIds
    if (!pages.includes(pageId.value) && pages[0]) pageId.value = pages[0]
  }
  selectedId.value = null
})

// --- Startpunkt ------------------------------------------------------------------------------------------------

async function initialize(seed: { template_id?: string; source_screen_id?: string }) {
  try {
    const state = await initializeLayout(departmentId.value, screenId.value, { ...seed, expected_version: version.value })
    variantId.value = state.draft?.default_variant_id ?? ''
    applyState(state)
    if (doc.value) pageId.value = firstPageOf(doc.value)
    toast.success(t('display.layout.editor.toastInitialized'))
  } catch (err) {
    handleSaveError(err)
  }
}

const chooserOpen = ref(false)
async function replaceFrom(seed: { template_id?: string; source_screen_id?: string }) {
  const ok = await confirm.confirm({
    title: t('display.layout.editor.replaceTemplate'),
    message: t('display.layout.editor.replaceConfirm'),
    confirmText: t('display.layout.editor.replaceTemplate'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  chooserOpen.value = false
  await initialize(seed)
}

// --- Bearbeiten ------------------------------------------------------------------------------------------------

function selectPage(id: string) {
  pageId.value = id
  selectedId.value = null
}

function addWidget(def: WidgetDefinition, at?: { x: number; y: number }) {
  if (!page.value) return
  const item = addItem(page.value, widgetContent(def), def.default_size)
  if (at) {
    const w = item.w
    const h = item.h
    item.x = Math.max(0, Math.min(12 - w, at.x))
    item.y = Math.max(0, Math.min(page.value.rows - h, at.y))
  }
  selectedId.value = item.id
  tab.value = 'item'
  markDirty()
}

function onDrop(payload: { widget: string; x: number; y: number }) {
  const def = widgetDefs.value.get(payload.widget)
  if (def) addWidget(def, { x: payload.x, y: payload.y })
}

function addRotationZone() {
  if (!page.value) return
  const text = widgetDefs.value.get('text')
  if (!text) return
  const item = addItem(
    page.value,
    { type: 'rotation', enabled: true, interval_sec: limits.value.default_interval_sec, steps: [{ duration_sec: null, content: widgetContent(text) }] },
    [6, 4],
  )
  selectedId.value = item.id
  tab.value = 'item'
  markDirty()
}

function addEmbeddedZone() {
  if (!page.value) return
  const target = embeddableForCurrent.value[0]
  if (!target) return
  const item = addItem(page.value, { type: 'page', page_id: target.id }, [6, 6])
  selectedId.value = item.id
  tab.value = 'item'
  markDirty()
}

function mutateItem(fn: (item: LayoutItem) => void) {
  if (!selectedItem.value) return
  fn(selectedItem.value)
  markDirty()
}

function duplicateItem() {
  const source = selectedItem.value
  const target = page.value
  if (!source || !target) return
  const copy = cloneLayout(source)
  copy.id = newId('i', target.items.map((i) => i.id))
  const spot = findFreeSpot(target.items, target.rows, source.w, source.h)
  Object.assign(copy, spot)
  target.items.push(copy)
  selectedId.value = copy.id
  markDirty()
}

function removeItem() {
  const target = page.value
  if (!target || !selectedId.value) return
  target.items = target.items.filter((i) => i.id !== selectedId.value)
  selectedId.value = null
  markDirty()
}

function onAddPage() {
  if (!doc.value) return
  const created = addPage(doc.value, t('display.layout.editor.newPageName', { n: doc.value.pages.length + 1 }))
  pageId.value = created.id
  selectedId.value = null
  markDirty()
}

const renameOpen = ref(false)
const renameValue = ref('')
watch(renameOpen, (open) => {
  if (open) renameValue.value = page.value?.name ?? ''
})
function applyRename() {
  if (page.value && renameValue.value.trim()) {
    page.value.name = renameValue.value.trim()
    markDirty()
  }
  renameOpen.value = false
}

async function deletePage() {
  if (!doc.value || !page.value || !canDeletePage.value) return
  const ok = await confirm.confirm({
    title: t('display.layout.editor.deletePage'),
    message: t('display.layout.editor.deletePageConfirm', { name: page.value.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  const id = page.value.id
  doc.value.pages = doc.value.pages.filter((p) => p.id !== id)
  pageId.value = firstPageOf(doc.value)
  selectedId.value = null
  markDirty()
}

// Varianten und Ablauf der Gesamtanzeige
const newVariantOpen = ref(false)
const newVariantName = ref('')
const newVariantAspect = ref('16:9')
function createVariant() {
  if (!doc.value) return
  const created = addVariant(doc.value, newVariantName.value.trim(), newVariantAspect.value, variant.value ?? undefined)
  variantId.value = created.id
  newVariantOpen.value = false
  newVariantName.value = ''
  tab.value = 'display'
  markDirty()
}

function renameVariant(name: unknown) {
  if (variant.value) {
    variant.value.name = String(name ?? '')
    markDirty()
  }
}

function setAspect(aspect: unknown) {
  if (variant.value) {
    variant.value.aspect_ratio = String(aspect)
    markDirty()
  }
}

async function deleteVariant() {
  if (!doc.value || !variant.value) return
  const ok = await confirm.confirm({
    title: t('display.layout.editor.deleteVariant'),
    message: t('display.layout.editor.deleteVariantConfirm', { name: variant.value.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  if (removeVariant(doc.value, variant.value.id)) {
    variantId.value = doc.value.default_variant_id
    markDirty()
  }
}

const flowAdd = ref<string | null>(null)
function applyFlow(pageIds: string[], enabled: boolean, interval: number, durations: (number | null)[]) {
  if (!variant.value) return
  setVariantFlow(variant.value, pageIds, enabled, interval, durations)
  markDirty()
}
function addFlow() {
  if (!flowAdd.value) return
  const f = flow.value
  applyFlow([...f.pageIds, flowAdd.value], f.pageIds.length ? f.enabled || f.pageIds.length === 1 : true, f.interval, [...f.durations, null])
  flowAdd.value = null
}
function removeFlow(i: number) {
  const f = flow.value
  const ids = f.pageIds.filter((_, idx) => idx !== i)
  applyFlow(ids, f.enabled, f.interval, f.durations.filter((_, idx) => idx !== i))
}
function moveFlow(i: number, delta: number) {
  const f = flow.value
  const ids = [...f.pageIds]
  const durations = [...f.durations]
  const j = i + delta
  ;[ids[i], ids[j]] = [ids[j]!, ids[i]!]
  ;[durations[i], durations[j]] = [durations[j] ?? null, durations[i] ?? null]
  applyFlow(ids, f.enabled, f.interval, durations)
}
function setFlowEnabled(enabled: boolean) {
  const f = flow.value
  applyFlow(f.pageIds, enabled, f.interval, f.durations)
}
function setFlowInterval(raw: unknown) {
  const n = Math.min(limits.value.max_interval_sec, Math.max(limits.value.min_interval_sec, Math.round(Number(raw)) || limits.value.default_interval_sec))
  const f = flow.value
  applyFlow(f.pageIds, f.enabled, n, f.durations)
}
function setFlowDuration(i: number, raw: unknown) {
  const f = flow.value
  const durations = [...f.durations]
  durations[i] = raw === '' || raw === null ? null : Math.min(limits.value.max_interval_sec, Math.max(limits.value.min_interval_sec, Math.round(Number(raw)) || limits.value.default_interval_sec))
  applyFlow(f.pageIds, f.enabled, f.interval, durations)
}

// --- Speichern / Veröffentlichen / Revisionen -----------------------------------------------------------------

const errorsOpen = ref(false)
const saveErrors = ref<LayoutError[]>([])
const conflictOpen = ref(false)

function uniqueErrors(list: LayoutError[]): LayoutError[] {
  const seen = new Set<string>()
  return list.filter((e) => {
    const key = `${e.code}|${e.path}`
    if (seen.has(key)) return false
    seen.add(key)
    return true
  })
}

function errorText(e: LayoutError): string {
  const key = `display.layout.error.${e.code}`
  return `${t(key) === key ? e.message : t(key)}${e.path ? ` (${e.path})` : ''}`
}

function handleSaveError(err: unknown) {
  const info = layoutErrorInfo(err)
  if (info.status === 409 && info.code === 'version_conflict') {
    conflictOpen.value = true
  } else if (info.status === 422) {
    saveErrors.value = uniqueErrors(info.errors)
    errorsOpen.value = true
  } else if (info.status === 409) {
    toast.error((err as { response?: { data?: { error?: string } } }).response?.data?.error ?? t('display.layout.editor.saveError'))
  } else {
    toast.error(t('display.layout.editor.saveError'))
  }
}

async function save(): Promise<boolean> {
  if (!doc.value) return false
  saving.value = true
  try {
    const state = await saveLayoutDraft(departmentId.value, screenId.value, doc.value, version.value)
    applyState(state, true)
    toast.success(t('display.layout.editor.toastSaved'))
    return true
  } catch (err) {
    handleSaveError(err)
    return false
  } finally {
    saving.value = false
  }
}

async function reloadFromServer() {
  conflictOpen.value = false
  try {
    applyState(await getLayoutState(departmentId.value, screenId.value))
  } catch {
    toast.error(t('display.layout.editor.loadError'))
  }
}

const publishOpen = ref(false)
const publishErrors = ref<LayoutError[]>([])
const publishChecking = ref(false)
const publishing = ref(false)

async function startPublish() {
  if (!doc.value) return
  publishChecking.value = true
  try {
    if (dirty.value && !(await save())) return
    const result = await validateLayoutDraft(departmentId.value, screenId.value)
    publishErrors.value = uniqueErrors(result.errors)
    publishOpen.value = true
  } catch {
    toast.error(t('display.layout.editor.saveError'))
  } finally {
    publishChecking.value = false
  }
}

async function publish() {
  publishing.value = true
  try {
    const state = await publishLayout(departmentId.value, screenId.value, version.value)
    applyState(state, true)
    publishOpen.value = false
    toast.success(t('display.layout.editor.toastPublished', { n: state.published?.number ?? '' }))
  } catch (err) {
    publishOpen.value = false
    handleSaveError(err)
  } finally {
    publishing.value = false
  }
}

const revisionsOpen = ref(false)
const revisions = ref<LayoutRevisionInfo[]>([])
async function openRevisions() {
  revisionsOpen.value = true
  try {
    revisions.value = await listLayoutRevisions(departmentId.value, screenId.value)
  } catch {
    revisions.value = []
  }
}

async function restore(number: number, andPublish: boolean) {
  const ok = await confirm.confirm({
    title: t('display.layout.editor.revisions'),
    message: andPublish ? t('display.layout.editor.restorePublishConfirm', { n: number }) : t('display.layout.editor.restoreDraftConfirm', { n: number }),
    confirmText: andPublish ? t('display.layout.editor.restorePublish') : t('display.layout.editor.restoreDraft'),
    cancelText: t('common.cancel'),
    variant: 'warning',
  })
  if (!ok) return
  try {
    // Eine ungespeicherte Bearbeitung wird dabei verworfen; die Serverversion des Entwurfs bleibt die Grundlage.
    const state = await restoreLayoutRevision(departmentId.value, screenId.value, number, version.value, andPublish)
    applyState(state)
    revisions.value = await listLayoutRevisions(departmentId.value, screenId.value)
    toast.success(t('display.layout.editor.toastRestored', { n: number }))
  } catch (err) {
    handleSaveError(err)
  }
}

// --- Vorschau ---------------------------------------------------------------------------------------------------

const previewOpen = ref(false)
const deviceKey = ref('auto')
const PRESETS: { key: string; aspect: number; fontScale: number }[] = [
  { key: 'tv', aspect: 16 / 9, fontScale: 1 },
  { key: 'led', aspect: 3 / 2, fontScale: 1.25 },
  { key: 'compact', aspect: 4 / 3, fontScale: 1 },
  { key: 'portrait', aspect: 9 / 16, fontScale: 1 },
]
const deviceItems = computed(() => [
  { value: 'auto', title: t('display.layout.editor.deviceAuto') },
  ...PRESETS.map((p) => ({ value: `preset:${p.key}`, title: t(`display.layout.editor.preset.${p.key}`) })),
  ...devices.value.map((d) => ({ value: `device:${d.id}`, title: t('display.layout.editor.deviceReal', { name: d.name }) })),
])
const previewDevice = computed<SimulatedDevice>(() => {
  const key = deviceKey.value
  if (key.startsWith('preset:')) {
    const p = PRESETS.find((x) => `preset:${x.key}` === key)
    if (p) return { key, aspect: p.aspect, fontScale: p.fontScale }
  }
  if (key.startsWith('device:')) {
    const d = devices.value.find((x) => `device:${x.id}` === key)
    if (d) return { key, aspect: profileAspect(d.profile) ?? 16 / 9, fontScale: profileFontScale(d.profile), variantId: d.profile?.variant_id }
  }
  // Automatisch: Format der gewählten Variante
  return { key: 'auto', aspect: variant.value ? ratioOf(variant.value.aspect_ratio) : 16 / 9, fontScale: 1, variantId: variantId.value }
})
const previewVariantId = computed(() => (doc.value ? pickVariant(doc.value, previewDevice.value) : ''))
const previewVariantName = computed(() => doc.value?.variants.find((v) => v.id === previewVariantId.value)?.name ?? '')
const previewAspect = computed(() => `${previewDevice.value.aspect}`)

function openFullPreview() {
  const href = router.resolve({
    name: 'DisplayPreview',
    params: { departmentId: departmentId.value, screenId: screenId.value },
    query: { source: 'draft', variant: variantId.value, back: route.fullPath },
  }).href
  window.open(href, '_blank')
}
</script>

<style scoped>
.editor { display: flex; flex-direction: column; gap: 14px; padding: 4px 0 24px; }
.editor__head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; align-items: flex-start; }
.editor__titles h1 { margin: 2px 0; font-size: 1.4rem; }
.editor__titles p { margin: 0; }
.editor__back { font-size: 0.9rem; }
.editor__status { display: flex; flex-wrap: wrap; gap: 6px; }
.chip { padding: 2px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
.chip--ok { background: #dcfce7; color: #166534; }
.chip--idle { background: #f3f4f6; color: #374151; }
.chip--warn { background: #fef3c7; color: #92400e; }
.toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 10px; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
.toolbar__group { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.toolbar__select { min-width: 240px; }
.editor__body { display: grid; grid-template-columns: 230px minmax(0, 1fr) 300px; gap: 14px; align-items: start; }
.panel { display: flex; flex-direction: column; gap: 10px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
.panel h3 { margin: 0; font-size: 0.95rem; }
.panel__sub { margin: 6px 0 0; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; }
.panel__zones { display: flex; flex-direction: column; gap: 6px; align-items: flex-start; }
.pages { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.pages__btn { width: 100%; text-align: left; padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; cursor: pointer; }
.pages__item--on .pages__btn { border-color: #2563eb; background: #eff6ff; font-weight: 600; }
.stage { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.stage__bar { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: baseline; }
.stage__issues { margin: 0; color: #b91c1c; font-size: 0.85rem; }
.tabs { display: flex; gap: 4px; }
.tabs__btn { flex: 1; padding: 6px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; cursor: pointer; }
.tabs__btn--on { border-color: #2563eb; background: #eff6ff; font-weight: 600; }
.flow { display: flex; flex-direction: column; gap: 10px; }
.flow__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.flow__list li { display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap; border: 1px solid #e5e7eb; border-radius: 8px; padding: 4px 8px; }
.flow__dur { width: 100px; }
.flow__actions button { border: 0; background: none; cursor: pointer; padding: 2px 6px; }
.flow__add { display: grid; grid-template-columns: 1fr auto; gap: 8px; align-items: end; }
.preview-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 10px; }
.preview-bar__select { min-width: 280px; }
.preview-frame { margin: 0 auto; border-radius: 8px; overflow: hidden; background: #0b1220; }
.issues { margin: 0; padding-left: 1.2rem; }
.revisions { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.revisions li { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 8px; }
.revisions__actions { display: flex; gap: 6px; flex-wrap: wrap; }
.card { padding: 16px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
.muted { color: #6b7280; }
.error { color: #b91c1c; }
@media (max-width: 1100px) {
  .editor__body { grid-template-columns: minmax(0, 1fr); }
}
</style>
