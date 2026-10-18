<template>
  <div class="studio" data-testid="layout-editor">
    <StudioToolbar
      :back-to="backTo"
      :screen-name="screenName"
      :has-doc="!!doc"
      :published="published"
      :dirty="dirty"
      :has-unpublished="hasUnpublished"
      :saving="saving"
      :publish-checking="publishChecking"
      :variant-id="variantId"
      :variants="variantItems"
      :can-add-variant="!!doc && doc.variants.length < limits.max_variants"
      :can-undo="history.canUndo.value"
      :can-redo="history.canRedo.value"
      :zoom="zoom"
      :show-grid="showGrid"
      :has-selection="selectedIds.length > 0"
      :can-group="canGroup"
      :can-ungroup="canUngroup"
      :can-paste="!!clipboard"
      :preview-mode="previewMode"
      :library-open="libraryOpen"
      :props-open="propsOpen"
      @action="onToolbar"
      @update:variant-id="variantId = $event"
      @update:preview-mode="previewMode = $event"
    />

    <ELoadingState v-if="loading" variant="inline" :message="t('settings.displayScreens.loading')" class="studio__center" />
    <p v-else-if="loadError" class="studio__center error" role="alert">{{ loadError }}</p>

    <!-- Startpunkt wählen (noch kein Entwurf) -->
    <section v-else-if="!doc" class="studio__center studio__chooser">
      <h2>{{ t('display.layout.editor.chooserTitle') }}</h2>
      <LayoutStartChooser :templates="catalog?.templates ?? []" :sources="copySources" @choose="initialize" />
    </section>

    <div v-else class="studio__body" :style="bodyStyle">
      <aside v-show="libraryOpen" class="panel panel--left" :aria-label="t('display.layout.editor.library')">
        <StudioLibrary
          :entries="libraryEntries"
          :widget-defs="widgetDefs"
          :highlight-no-live="previewMode === 'live'"
          @add="(entry) => addEntry(entry)"
          @rename="openRenameBlock"
          @remove="removeBlock"
        />
      </aside>

      <main class="stage">
        <div class="stage__canvas">
          <StudioCanvas
            v-if="stageHost && page && variant"
            ref="canvasRef"
            :host="stageHost"
            :logical-width="stageSize.w"
            :logical-height="stageSize.h"
            :unit="unit"
            :nested="path.length > 0 || !!isolateId"
            :selected-ids="selectedIds"
            :widget-defs="widgetDefs"
            :show-grid="showGrid && !isolateId"
            :label-for="labelFor"
            :fit-key="fitKey"
            :locked="!!isolateId"
            @select="selectedIds = $event"
            @checkpoint="history.checkpoint()"
            @changed="markDirty"
            @open="openItem"
            @drop="onDrop"
            @zoom="zoom = $event"
            @blocked="notifyBlocked"
          />
          <div class="stage__badges" aria-live="polite">
            <span class="badge" :class="previewMode === 'demo' ? 'badge--demo' : 'badge--live'" data-testid="mode-badge">
              {{ previewMode === 'demo' ? t('display.studio.mode.badgeDemo') : t('display.studio.mode.badgeLive') }}
            </span>
            <span v-if="isolateId" class="badge badge--iso">{{ t('display.studio.isolate.badge') }}</span>
            <span v-if="rotationNav" class="badge badge--btn badge--nav" data-testid="rotation-nav">
              <button type="button" :disabled="rotationNav.index === 0" :aria-label="t('display.studio.rotation.prev')" @click="gotoRotationPage(-1)">‹</button>
              {{ t('display.studio.rotation.pageOf', { n: rotationNav.index + 1, count: rotationNav.count }) }}
              <button type="button" :disabled="rotationNav.index >= rotationNav.count - 1" :aria-label="t('display.studio.rotation.next')" @click="gotoRotationPage(1)">›</button>
            </span>
            <button v-if="isolateId || path.length" type="button" class="badge badge--btn" data-testid="btn-exit-edit" @click="exitLevel">
              <v-icon icon="mdi-arrow-u-left-top" size="14" aria-hidden="true" /> {{ t('display.studio.isolate.back') }}
            </button>
          </div>
          <p v-if="blockedHint" class="stage__blocked" role="status">{{ blockedHint }}</p>
        </div>
        <StudioPagesBar
          :pages="doc.pages"
          :page-id="pageId"
          :crumbs="crumbs"
          :can-add="doc.pages.length < limits.max_pages"
          :can-delete="canDeletePage"
          :hint="pageProblems.length ? t('display.layout.editor.pageProblems', { n: pageProblems.length }) : ''"
          @select="selectPage"
          @add="onAddPage"
          @rename="renameOpen = true"
          @remove="deletePage"
          @navigate="navigateTo"
        />
      </main>

      <aside v-show="propsOpen" class="panel panel--right" :aria-label="t('display.studio.props.title')" data-testid="props-panel">
        <div class="tabs" role="tablist">
          <button type="button" role="tab" :aria-selected="tab === 'item'" :class="{ 'tabs__btn--on': tab === 'item' }" class="tabs__btn" @click="tab = 'item'">{{ t('display.layout.editor.tabElement') }}</button>
          <button type="button" role="tab" :aria-selected="tab === 'display'" :class="{ 'tabs__btn--on': tab === 'display' }" class="tabs__btn" data-testid="tab-display" @click="tab = 'display'">{{ t('display.layout.editor.tabDisplay') }}</button>
        </div>
        <div class="panel__scroll">
          <template v-if="tab === 'item'">
            <ItemInspector
              v-if="selectedItem && page && hostForInspector"
              :doc="doc"
              :host="hostForInspector"
              :page-id="page.id"
              :item="selectedItem"
              :widget-defs="widgetDefs"
              :widgets="catalog?.widgets ?? []"
              :limits="limits"
              :isolated="!!isolateId"
              @mutate="mutateItem"
              @duplicate="duplicateSelection"
              @remove="removeSelection"
              @open="openItem"
              @open-step="openRotationStep"
              @ungroup="ungroupSelection"
              @save-block="openSaveBlock"
              @refine="refineGroup"
              @coarsen="coarsenGroup"
              @blocked="notifyBlocked"
            />
            <div v-else-if="selectedIds.length > 1" class="multi" data-testid="multi-selection">
              <h4>{{ t('display.studio.multi.title', { n: selectedIds.length }) }}</h4>
              <p class="muted">{{ t('display.studio.multi.hint') }}</p>
              <div class="multi__actions">
                <EButton variant="primary" size="small" :disabled="!canGroup" @click="groupSelection">{{ t('display.studio.actions.group') }}</EButton>
                <EButton variant="secondary" size="small" @click="copySelection">{{ t('display.studio.actions.copy') }}</EButton>
                <EButton variant="secondary" size="small" @click="duplicateSelection">{{ t('display.studio.actions.duplicate') }}</EButton>
                <EButton variant="secondary" size="small" @click="openSaveBlock">{{ t('display.studio.actions.saveAsBlock') }}</EButton>
                <EButton variant="danger" size="small" @click="removeSelection">{{ t('display.studio.actions.delete') }}</EButton>
              </div>
            </div>
            <div v-else class="empty">
              <p class="muted">{{ t('display.studio.props.nothingSelected') }}</p>
              <ul class="shortcuts">
                <li>{{ t('display.studio.shortcuts.select') }}</li>
                <li>{{ t('display.studio.shortcuts.move') }}</li>
                <li>{{ t('display.studio.shortcuts.pan') }}</li>
                <li>{{ t('display.studio.shortcuts.keys') }}</li>
              </ul>
            </div>
          </template>
          <StudioDisplayPanel
            v-else-if="variant"
            :doc="doc"
            :variant="variant"
            :limits="limits"
            :widget-defs="widgetDefs"
            @mutate="mutateDoc"
            @delete-variant="deleteVariant"
          />
        </div>
      </aside>
    </div>

    <!-- Vorschau mit Geräteprofil -->
    <EDialog v-model="previewOpen" :max-width="1100" :title="t('display.layout.editor.previewTitle')" :retain-focus="false">
      <div class="preview-bar">
        <ESelect v-model="deviceKey" :items="deviceItems" :label="t('display.layout.editor.previewDevice')" hide-details class="preview-bar__select" />
        <span class="muted">{{ t('display.layout.editor.previewVariant', { name: previewVariantName }) }}</span>
        <span class="badge" :class="previewMode === 'demo' ? 'badge--demo' : 'badge--live'">{{ previewMode === 'demo' ? t('display.studio.mode.badgeDemo') : t('display.studio.mode.badgeLive') }}</span>
      </div>
      <div class="preview-frame" :style="{ aspectRatio: previewAspect, width: `min(100%, calc(60vh * ${previewAspect}))` }" data-testid="preview-frame">
        <DisplayLayoutRenderer v-if="doc && previewOpen" :key="`${deviceKey}-${previewVariantId}-${previewMode}`" :layout="doc" :variant-id="previewVariantId" :font-scale="previewDevice.fontScale" :map-data="previewMapData" :live-data="liveData" :data-mode="previewMode" :show-mode-badge="doc.data_mode === 'demo'" />
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
        <p class="publish-mode" :class="{ 'publish-mode--demo': doc?.data_mode === 'demo' }" data-testid="publish-mode">
          {{ doc?.data_mode === 'demo' ? t('display.studio.publishedMode.confirmDemo') : t('display.studio.publishedMode.confirmLive') }}
        </p>
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

    <!-- Eigenen Baustein speichern / umbenennen -->
    <EDialog v-model="blockDialogOpen" :max-width="460" :title="blockEditId ? t('display.studio.library.rename') : t('display.studio.blocks.saveTitle')">
      <ETextField v-model="blockName" :label="t('display.studio.blocks.name')" maxlength="80" hide-details data-testid="block-name" />
      <p v-if="!blockEditId" class="muted mt-2">{{ t('display.studio.blocks.saveHint') }}</p>
      <template #actions>
        <EButton variant="secondary" size="small" @click="blockDialogOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :disabled="!blockName.trim()" :loading="blockSaving" data-testid="block-save" @click="submitBlock">{{ t('common.save') }}</EButton>
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
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import {
  createDisplayBlock,
  deleteDisplayBlock,
  getLayoutCatalog,
  getLayoutState,
  initializeLayout,
  layoutErrorInfo,
  listDisplayBlocks,
  listLayoutRevisions,
  publishLayout,
  renameDisplayBlock,
  restoreLayoutRevision,
  saveLayoutDraft,
  validateLayoutDraft,
  type CustomBlock,
  type DisplayMapData,
  type GaLiveData,
  type LayoutCatalog,
  type LayoutContent,
  type LayoutDocument,
  type LayoutError,
  type LayoutGroupContent,
  type LayoutItem,
  type LayoutRevisionInfo,
  type LayoutState,
  type LayoutVariant,
  type WidgetDefinition,
} from '@/api/displayLayout'
import { getDisplayPreviewLiveData, getDisplayPreviewMapData, listDisplayScreens, type DisplayScreenSettings } from '@/api/displayScreens'
import { listDisplayDevices, type DisplayDeviceRow } from '@/api/displayDevice'
import DisplayLayoutRenderer from '@/components/display/layout/DisplayLayoutRenderer.vue'
import ItemInspector from '@/components/display/layout/editor/ItemInspector.vue'
import LayoutStartChooser from '@/components/display/layout/editor/LayoutStartChooser.vue'
import StudioCanvas from '@/components/display/layout/studio/StudioCanvas.vue'
import StudioDisplayPanel from '@/components/display/layout/studio/StudioDisplayPanel.vue'
import StudioLibrary from '@/components/display/layout/studio/StudioLibrary.vue'
import StudioPagesBar from '@/components/display/layout/studio/StudioPagesBar.vue'
import StudioToolbar, { type ToolbarAction } from '@/components/display/layout/studio/StudioToolbar.vue'
import { buildLibrary, contentOf, type LibraryEntry } from '@/components/display/layout/studio/libraryModel'
import { LAYOUT_DATA_MODE_KEY, LAYOUT_FREEZE_KEY, LAYOUT_LIVE_DATA_KEY, LAYOUT_MAP_DATA_KEY, LAYOUT_PAGES_KEY, type LayoutDataMode } from '@/components/display/layout/layoutContext'
import { cloneLayout } from '@/components/display/layout/layoutModel'
import { addPage, addVariant, isPageReferenced, pageIssues, pickVariant, profileAspect, profileFontScale, ratioOf, removeVariant, variantFlow, widgetContent, type SimulatedDevice } from '@/components/display/layout/layoutEditing'
import {
  MAX_GROUP_NESTING,
  coarsenGroupGrid,
  copyItems,
  ensureStepGroup,
  groupDepth,
  groupItems,
  hostShare,
  newRotation,
  pasteItems,
  placeItem,
  planMove,
  planResize,
  refineGroupGrid,
  removeItems,
  resolveHost,
  selectionAsGroup,
  stepToken,
  ungroupItem,
  type ClipboardEntry,
  type GridHost,
} from '@/components/display/layout/studioModel'
import { useStudioHistory } from '@/components/display/layout/useStudioHistory'

/**
 * Infoscreen-Studio: vollflächiger WYSIWYG-Editor für das Layout eines Infoscreens (Entwurf bearbeiten, Demo- und
 * Live-Vorschau, Entwurf speichern, explizit veröffentlichen, Revisionen wiederherstellen). Geräte sehen ausschliesslich
 * die veröffentlichte Revision; der Editor arbeitet nur am Entwurf, und der Demo/Live-Umschalter betrifft nur die Vorschau.
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
const customBlocks = ref<CustomBlock[]>([])
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
/** Gruppen-Element-IDs ab der Seite: welche Rasterfläche gerade bearbeitet wird. */
const path = ref<string[]>([])
/** Element der aktuellen Fläche, das einzeln («separat») bearbeitet wird. */
const isolateId = ref<string | null>(null)
const selectedIds = ref<string[]>([])
const tab = ref<'item' | 'display'>('item')
const zoom = ref(1)
const showGrid = ref(true)
const wide = typeof window === 'undefined' ? 1600 : window.innerWidth
const libraryOpen = ref(wide >= 900)
const propsOpen = ref(wide >= 1150)
/** Editor-Vorschau: Demo ist der Standard; der Schalter ändert nie die veröffentlichte Anzeige. */
const previewMode = ref<LayoutDataMode>('demo')
const clipboard = ref<ClipboardEntry | null>(null)
const canvasRef = ref<InstanceType<typeof StudioCanvas> | null>(null)
const blockedHint = ref('')

const history = useStudioHistory<LayoutDocument>(
  () => doc.value,
  (snapshot) => {
    doc.value = snapshot
    dirty.value = true
    validateLocation()
  },
)

const limits = computed(() => catalog.value?.limits ?? {
  columns: 12, max_columns: 48, default_rows: 12, max_rows: 48, max_depth: 6, max_variants: 6, max_pages: 20, max_items: 40, max_steps: 20,
  min_interval_sec: 3, max_interval_sec: 3600, default_interval_sec: 20,
})
const widgetDefs = computed(() => new Map<string, WidgetDefinition>((catalog.value?.widgets ?? []).map((w) => [w.type, w])))
const copySources = computed(() => screens.value.filter((s) => !s.revoked_at && s.id !== screenId.value && s.layout?.has_draft))
const libraryEntries = computed(() => buildLibrary(catalog.value?.widgets ?? [], catalog.value?.blocks ?? [], customBlocks.value))

const variant = computed(() => doc.value?.variants.find((v) => v.id === variantId.value) ?? doc.value?.variants[0] ?? null)
const page = computed(() => doc.value?.pages.find((p) => p.id === pageId.value) ?? doc.value?.pages[0] ?? null)
const resolved = computed(() => (page.value ? resolveHost(page.value, path.value) : null))
const editHost = computed<GridHost | null>(() => resolved.value?.host ?? null)
const isolatedItem = computed<LayoutItem | null>(() => (isolateId.value && editHost.value ? editHost.value.items.find((i) => i.id === isolateId.value) ?? null : null))

/** Fläche, die auf der Arbeitsfläche dargestellt wird: die bearbeitete Fläche oder – bei Einzelbearbeitung – nur das Element. */
const stageHost = computed<GridHost | null>(() => {
  const item = isolatedItem.value
  if (item) return { rows: 1, columns: 1, items: [{ id: item.id, x: 0, y: 0, w: 1, h: 1, content: item.content }] }
  return editHost.value
})
const hostForInspector = computed(() => editHost.value)

const variantItems = computed(() => (doc.value?.variants ?? []).map((v) => ({ value: v.id, title: `${v.name} (${v.aspect_ratio})${v.id === doc.value?.default_variant_id ? ' ★' : ''}` })))
const ASPECTS = ['16:9', '16:10', '4:3', '3:2', '21:9', '9:16']
const aspectItems = ASPECTS.map((a) => ({ value: a, title: a }))
const canDeletePage = computed(() => !!doc.value && !!page.value && doc.value.pages.length > 1 && !isPageReferenced(doc.value, page.value.id))
const pageProblems = computed(() => (editHost.value ? pageIssues(editHost.value, widgetDefs.value) : []))

const selectedItem = computed<LayoutItem | null>(() => (selectedIds.value.length === 1 ? editHost.value?.items.find((i) => i.id === selectedIds.value[0]) ?? null : null))
const selectedSet = computed(() => new Set(selectedIds.value))
const canGroup = computed(() => selectedIds.value.length >= 1 && !isolateId.value)
const canUngroup = computed(() => !isolateId.value && selectedIds.value.length === 1 && selectedItem.value?.content.type === 'group')

// --- Abmessungen der Arbeitsfläche ---------------------------------------------------------------------------------
// Die Fläche wird in der logischen Auflösung der Anzeige gerendert (Referenz 1920 px lange Seite). Gruppen und
// Einzelbausteine zeigen nur ihren Anteil; die Grundgrösse bleibt die der Gesamtanzeige (massstabsgetreu).
const REF = 1920
const rootRatio = computed(() => (variant.value ? ratioOf(variant.value.aspect_ratio) : 16 / 9))
const rootW = computed(() => (rootRatio.value >= 1 ? REF : REF * rootRatio.value))
const rootH = computed(() => (rootRatio.value >= 1 ? REF / rootRatio.value : REF))
const unit = computed(() => rootH.value / 60)
const chain = computed(() => {
  const trail = resolved.value?.trail ?? []
  return isolatedItem.value ? [...trail, { item: isolatedItem.value }] : trail
})
const stageSize = computed(() => {
  if (!page.value) return { w: rootW.value, h: rootH.value }
  const share = hostShare(page.value, chain.value)
  return { w: Math.max(40, rootW.value * share.w), h: Math.max(40, rootH.value * share.h) }
})
const fitKey = computed(() => `${variantId.value}|${pageId.value}|${path.value.join('/')}|${isolateId.value ?? ''}|${Math.round(stageSize.value.w)}x${Math.round(stageSize.value.h)}`)
const bodyStyle = computed(() => ({
  gridTemplateColumns: `${libraryOpen.value ? 'minmax(220px, 260px)' : '0px'} minmax(0, 1fr) ${propsOpen.value ? 'minmax(260px, 300px)' : '0px'}`,
}))

function formatDate(iso: string): string {
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleString(String(locale.value).startsWith('de') ? 'de-CH' : 'en-CH', { dateStyle: 'medium', timeStyle: 'short' })
}

function markDirty() {
  dirty.value = true
}

function labelFor(item: LayoutItem): string {
  const c = item.content
  if (c.type === 'widget') return t(`display.layout.widget.${c.widget}`)
  if (c.type === 'group') return c.name || t('display.studio.group.unnamed')
  if (c.type === 'rotation') return t('display.studio.rotation.label', { n: c.steps.length })
  if (c.type === 'page') return t('display.layout.content.page', { name: doc.value?.pages.find((p) => p.id === c.page_id)?.name ?? '' })
  return t('display.layout.content.group')
}

const crumbs = computed(() => {
  const list: { key: string; label: string; pathLength: number }[] = [{ key: 'page', label: page.value?.name || page.value?.id || '', pathLength: 0 }]
  const trail = resolved.value?.trail ?? []
  trail.forEach((step, i) => {
    if (step.rotation) {
      // Rotation › Rotationsseite (Zurück führt zur Rotation bzw. zur übergeordneten Fläche)
      list.push({ key: `${step.itemId}-rot`, label: t('display.layout.content.rotation'), pathLength: i })
      list.push({ key: step.itemId, label: step.rotation.steps[step.rotationIndex!]?.name || t('display.studio.rotation.pageName', { n: step.rotationIndex! + 1 }), pathLength: i + 1 })
    } else {
      list.push({ key: step.itemId, label: step.group.name || t('display.studio.group.unnamed'), pathLength: i + 1 })
    }
  })
  if (isolatedItem.value) list.push({ key: `iso-${isolatedItem.value.id}`, label: labelFor(isolatedItem.value), pathLength: trail.length })
  return list
})

/** Befindet sich die Bearbeitung in einer Rotationsseite: Position für die Blätterschaltflächen. */
const rotationNav = computed(() => {
  const last = resolved.value?.trail[resolved.value.trail.length - 1]
  return last?.rotation && !isolateId.value ? { index: last.rotationIndex!, count: last.rotation.steps.length, itemId: last.item.id } : null
})

function applyState(state: LayoutState, keepSelection = false) {
  doc.value = state.draft ? cloneLayout(state.draft) : null
  version.value = state.draft_version
  published.value = state.published
  hasUnpublished.value = state.has_unpublished_changes
  dirty.value = false
  // Speichern und Veröffentlichen behalten den Verlauf; ein anderes Dokument (Laden, Vorlage, Revision) beginnt neu.
  if (!keepSelection) history.reset()
  if (doc.value) {
    if (!doc.value.variants.some((v) => v.id === variantId.value)) variantId.value = doc.value.default_variant_id
    if (!doc.value.pages.some((p) => p.id === pageId.value)) pageId.value = firstPageOf(doc.value)
  }
  validateLocation()
  if (!keepSelection) selectedIds.value = []
}

function firstPageOf(d: LayoutDocument): string {
  const v = d.variants.find((x) => x.id === variantId.value) ?? d.variants[0]
  const first = v ? variantFlow(v).pageIds[0] : undefined
  return first && d.pages.some((p) => p.id === first) ? first : (d.pages[0]?.id ?? '')
}

/** Nach Undo, Neuladen oder Löschen: Pfad und Auswahl auf vorhandene Elemente zurücksetzen. */
function validateLocation() {
  if (!page.value) {
    path.value = []
    isolateId.value = null
    return
  }
  if (!resolveHost(page.value, path.value)) {
    path.value = []
    isolateId.value = null
  }
  const host = resolveHost(page.value, path.value)?.host
  if (host) {
    if (isolateId.value && !host.items.some((i) => i.id === isolateId.value)) isolateId.value = null
    selectedIds.value = selectedIds.value.filter((id) => host.items.some((i) => i.id === id))
  }
}

// --- Provider für die Renderer-Komponenten (Seiten, Datenmodus, Karte) ---------------------------------------------

provide(LAYOUT_PAGES_KEY, () => doc.value?.pages ?? [])
provide(LAYOUT_FREEZE_KEY, true)
provide(LAYOUT_DATA_MODE_KEY, () => previewMode.value)
const mapData = ref<DisplayMapData | null>(null)
provide(LAYOUT_MAP_DATA_KEY, () => mapData.value)

// Kartendaten: Demo = reproduzierbare Beispieldaten, Live = echte Orte und Pläne des Grossanlasses.
watch(
  [previewMode, () => !!doc.value],
  async ([mode, ready]) => {
    if (!ready) return
    try {
      if (mode === 'demo') {
        mapData.value = (await import('@/test-fixtures/displayMapData.json')).default as unknown as DisplayMapData
      } else {
        mapData.value = await getDisplayPreviewMapData(departmentId.value, screenId.value)
      }
    } catch {
      mapData.value = mode === 'live' ? { state: 'unavailable', plan: null, places: [] } : null
    }
  },
  { immediate: true },
)
const previewMapData = computed(() => mapData.value)

// Live-Vorschau: echte Daten des Grossanlasses über die Verwalter-API (Benutzerberechtigung), regelmässig aktualisiert.
const liveData = ref<GaLiveData | null>(null)
provide(LAYOUT_LIVE_DATA_KEY, () => liveData.value)
let liveTimer: ReturnType<typeof setInterval> | undefined
async function refreshLive() {
  try {
    liveData.value = await getDisplayPreviewLiveData(departmentId.value, screenId.value)
  } catch {
    liveData.value = { state: 'unavailable' }
  }
}
watch(
  [previewMode, () => !!doc.value],
  ([mode, ready]) => {
    if (liveTimer) clearInterval(liveTimer)
    liveTimer = undefined
    if (mode !== 'live' || !ready) return
    void refreshLive()
    liveTimer = setInterval(() => void refreshLive(), 30_000)
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  if (liveTimer) clearInterval(liveTimer)
})

// --- Laden -----------------------------------------------------------------------------------------------------

async function load() {
  loading.value = true
  loadError.value = null
  try {
    const [cat, state, list, devs, blocks] = await Promise.all([
      getLayoutCatalog(departmentId.value),
      getLayoutState(departmentId.value, screenId.value),
      listDisplayScreens(departmentId.value),
      listDisplayDevices(departmentId.value).catch(() => [] as DisplayDeviceRow[]),
      listDisplayBlocks(departmentId.value).catch(() => [] as CustomBlock[]),
    ])
    catalog.value = cat
    customBlocks.value = blocks
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
  window.addEventListener('keydown', onWindowKeyDown)
})
onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', onBeforeUnload)
  window.removeEventListener('keydown', onWindowKeyDown)
})

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
  resetLocation()
})

function resetLocation() {
  path.value = []
  isolateId.value = null
  selectedIds.value = []
}

// --- Startpunkt ------------------------------------------------------------------------------------------------

async function initialize(seed: { template_id?: string; source_screen_id?: string }) {
  try {
    const state = await initializeLayout(departmentId.value, screenId.value, { ...seed, expected_version: version.value })
    variantId.value = state.draft?.default_variant_id ?? ''
    resetLocation()
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
  resetLocation()
}

function openItem(id: string) {
  const host = editHost.value
  const item = host?.items.find((i) => i.id === id)
  if (!item) return
  if (item.content.type === 'group') {
    path.value = [...path.value, id]
    isolateId.value = null
    selectedIds.value = []
  } else if (item.content.type === 'page') {
    pageId.value = item.content.page_id
    resetLocation()
  } else if (item.content.type === 'rotation') {
    openRotationStep(id, 0)
  } else {
    isolateId.value = id
    selectedIds.value = [id]
    tab.value = 'item'
    propsOpen.value = true
  }
}

/**
 * Öffnet eine Rotationsseite zur Gestaltung. Ein bisheriger Schritt mit einem einzelnen Widget wird erst jetzt (auf
 * ausdrückliche Aktion) in eine Seite mit eigenem Raster übernommen; das Widget bleibt unverändert und füllt die Seite.
 */
function openRotationStep(itemId: string, index: number) {
  const item = editHost.value?.items.find((i) => i.id === itemId)
  if (!item || item.content.type !== 'rotation' || !item.content.steps[index]) return
  if (item.content.steps[index]!.content.type !== 'group') {
    if (!checkpointEdit()) return
    if (!ensureStepGroup(item.content, index)) {
      history.discardIfUnchanged()
      return
    }
    markDirty()
  }
  path.value = [...path.value, stepToken(itemId, index)]
  isolateId.value = null
  selectedIds.value = []
}

function gotoRotationPage(delta: number) {
  const nav = rotationNav.value
  if (!nav) return
  const next = nav.index + delta
  if (next < 0 || next >= nav.count) return
  const parent = path.value.slice(0, -1)
  const item = resolveHost(page.value!, parent)?.host.items.find((i) => i.id === nav.itemId)
  if (!item || item.content.type !== 'rotation') return
  if (item.content.steps[next]!.content.type !== 'group') {
    if (!checkpointEdit()) return
    ensureStepGroup(item.content, next)
    markDirty()
  }
  path.value = [...parent, stepToken(nav.itemId, next)]
  selectedIds.value = []
}

/** Nach dem Verlassen einer Rotationsseite ist die Rotation wieder gewählt. */
function reselectRotation(removed: string | undefined) {
  selectedIds.value = removed?.includes('#') ? [removed.split('#')[0]!] : []
}

function exitLevel() {
  if (isolateId.value) {
    isolateId.value = null
    selectedIds.value = []
  } else if (path.value.length) {
    const removed = path.value[path.value.length - 1]
    path.value = path.value.slice(0, -1)
    reselectRotation(removed)
  }
}

function navigateTo(pathLength: number) {
  const removed = path.value[pathLength]
  path.value = path.value.slice(0, pathLength)
  isolateId.value = null
  reselectRotation(removed)
}

function notifyBlocked() {
  blockedHint.value = t('display.studio.blocked')
  setTimeout(() => {
    blockedHint.value = ''
  }, 2500)
}

function mutateDoc(fn: (doc: LayoutDocument, variant: LayoutVariant) => void) {
  if (!doc.value || !variant.value || !checkpointEdit()) return
  fn(doc.value, variant.value)
  markDirty()
}

function checkpointEdit(): boolean {
  if (!doc.value) return false
  history.checkpoint()
  return true
}

function mutateItem(fn: (item: LayoutItem) => void) {
  const item = selectedItem.value
  if (!item || !checkpointEdit()) return
  fn(item)
  markDirty()
}

function addEntry(entry: LibraryEntry, at?: { x: number; y: number }) {
  if (!doc.value || !catalog.value) return
  if (isolateId.value) isolateId.value = null
  const host = editHost.value
  if (!host) return
  if (host.items.length >= limits.value.max_items) {
    toast.warning(t('display.studio.errors.tooManyItems', { n: limits.value.max_items }))
    return
  }
  const content = contentOf(entry, widgetDefs.value, catalog.value.blocks, customBlocks.value, widgetContent, () => newRotation(limits.value.default_interval_sec, t('display.studio.rotation.pageName', { n: 1 })))
  if (!content) return
  if (groupDepth(content) + path.value.length > MAX_GROUP_NESTING) {
    toast.warning(t('display.studio.errors.tooDeep', { n: MAX_GROUP_NESTING }))
    return
  }
  const size = (entry.source === 'widget' ? widgetDefs.value.get(entry.id)?.default_size : entry.defaultSize) ?? [4, 4]
  checkpointEdit()
  const placed = placeItem(host, content, [size[0], size[1]], at)
  if (!placed) {
    history.discardIfUnchanged()
    toast.warning(t('display.studio.errors.noSpace'))
    return
  }
  selectedIds.value = [placed.id]
  tab.value = 'item'
  markDirty()
}

function onDrop(payload: { data: string; x: number; y: number }) {
  try {
    const parsed = JSON.parse(payload.data) as { source?: string; id?: string }
    const entry = libraryEntries.value.find((e) => e.source === parsed.source && e.id === parsed.id)
    if (entry) addEntry(entry, { x: payload.x, y: payload.y })
  } catch {
    // Fremde Ziehdaten ignorieren.
  }
}

function copySelection() {
  const host = editHost.value
  if (!host || !selectedIds.value.length) return
  clipboard.value = copyItems(host, selectedSet.value)
  if (clipboard.value) toast.info(t('display.studio.copied', { n: clipboard.value.items.length }))
}

function pasteSelection() {
  const host = editHost.value
  if (!host || !clipboard.value || isolateId.value) return
  if (host.items.length + clipboard.value.items.length > limits.value.max_items) {
    toast.warning(t('display.studio.errors.tooManyItems', { n: limits.value.max_items }))
    return
  }
  const deepest = Math.max(0, ...clipboard.value.items.map((i) => groupDepth(i.content)))
  if (deepest + path.value.length > MAX_GROUP_NESTING) {
    toast.warning(t('display.studio.errors.tooDeep', { n: MAX_GROUP_NESTING }))
    return
  }
  checkpointEdit()
  const created = pasteItems(host, clipboard.value)
  if (!created) {
    history.discardIfUnchanged()
    toast.warning(t('display.studio.errors.noSpace'))
    return
  }
  selectedIds.value = created.map((i) => i.id)
  markDirty()
}

function duplicateSelection() {
  const host = editHost.value
  if (!host || !selectedIds.value.length) return
  const entry = copyItems(host, selectedSet.value)
  if (!entry) return
  checkpointEdit()
  const created = pasteItems(host, entry)
  if (!created || host.items.length > limits.value.max_items) {
    if (created) removeItems(host, new Set(created.map((i) => i.id)))
    history.discardIfUnchanged()
    toast.warning(t('display.studio.errors.noSpace'))
    return
  }
  selectedIds.value = created.map((i) => i.id)
  markDirty()
}

function removeSelection() {
  const host = editHost.value
  if (!host || !selectedIds.value.length) return
  if (isolateId.value) isolateId.value = null
  checkpointEdit()
  removeItems(host, selectedSet.value)
  selectedIds.value = []
  markDirty()
}

function groupSelection() {
  const host = editHost.value
  if (!host || !selectedIds.value.length || isolateId.value) return
  const picked = host.items.filter((i) => selectedSet.value.has(i.id))
  const inner = Math.max(0, ...picked.map((i) => groupDepth(i.content)))
  if (inner + 1 + path.value.length > MAX_GROUP_NESTING) {
    toast.warning(t('display.studio.errors.tooDeep', { n: MAX_GROUP_NESTING }))
    return
  }
  checkpointEdit()
  const group = groupItems(host, selectedSet.value, t('display.studio.group.defaultName', { n: host.items.filter((i) => i.content.type === 'group').length + 1 }))
  if (!group) {
    history.discardIfUnchanged()
    return
  }
  selectedIds.value = [group.id]
  tab.value = 'item'
  markDirty()
}

function ungroupSelection() {
  const host = editHost.value
  const item = selectedItem.value
  if (!host || !item || item.content.type !== 'group') return
  if (host.items.length - 1 + item.content.items.length > limits.value.max_items) {
    toast.warning(t('display.studio.errors.tooManyItems', { n: limits.value.max_items }))
    return
  }
  checkpointEdit()
  const restored = ungroupItem(host, item.id)
  if (!restored) {
    history.discardIfUnchanged()
    toast.warning(t('display.studio.errors.ungroupImpossible'))
    return
  }
  selectedIds.value = restored.map((i) => i.id)
  markDirty()
}

function refineGroup(id: string, factor: number) {
  const item = editHost.value?.items.find((i) => i.id === id)
  if (!item || item.content.type !== 'group') return
  checkpointEdit()
  if (!refineGroupGrid(item.content, factor)) {
    history.discardIfUnchanged()
    return
  }
  markDirty()
}

function coarsenGroup(id: string, factor: number) {
  const item = editHost.value?.items.find((i) => i.id === id)
  if (!item || item.content.type !== 'group') return
  checkpointEdit()
  if (!coarsenGroupGrid(item.content, factor)) {
    history.discardIfUnchanged()
    return
  }
  markDirty()
}

function onAddPage() {
  if (!doc.value || !checkpointEdit()) return
  const created = addPage(doc.value, t('display.layout.editor.newPageName', { n: doc.value.pages.length + 1 }))
  pageId.value = created.id
  resetLocation()
  markDirty()
}

const renameOpen = ref(false)
const renameValue = ref('')
watch(renameOpen, (open) => {
  if (open) renameValue.value = page.value?.name ?? ''
})
function applyRename() {
  if (page.value && renameValue.value.trim()) {
    checkpointEdit()
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
  checkpointEdit()
  const id = page.value.id
  doc.value.pages = doc.value.pages.filter((p) => p.id !== id)
  pageId.value = firstPageOf(doc.value)
  resetLocation()
  markDirty()
}

// Varianten
const newVariantOpen = ref(false)
const newVariantName = ref('')
const newVariantAspect = ref('16:9')
function createVariant() {
  if (!doc.value || !checkpointEdit()) return
  const created = addVariant(doc.value, newVariantName.value.trim(), newVariantAspect.value, variant.value ?? undefined)
  variantId.value = created.id
  newVariantOpen.value = false
  newVariantName.value = ''
  tab.value = 'display'
  markDirty()
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
  checkpointEdit()
  if (removeVariant(doc.value, variant.value.id)) {
    variantId.value = doc.value.default_variant_id
    markDirty()
  } else {
    history.discardIfUnchanged()
  }
}

// --- Eigene Bausteine ---------------------------------------------------------------------------------------------

const blockDialogOpen = ref(false)
const blockName = ref('')
const blockEditId = ref<string | null>(null)
const blockSaving = ref(false)

function openSaveBlock() {
  const host = editHost.value
  if (!host || !selectedIds.value.length) return
  blockEditId.value = null
  const only = selectedItem.value
  blockName.value = only?.content.type === 'group' ? (only.content.name ?? '') : ''
  blockDialogOpen.value = true
}

function openRenameBlock(id: string) {
  const block = customBlocks.value.find((b) => b.id === id)
  if (!block) return
  blockEditId.value = id
  blockName.value = block.name
  blockDialogOpen.value = true
}

/** Inhalt und Standardgrösse des zu speichernden Bausteins aus der Auswahl (unabhängige Kopie). */
function blockPayload(): { content: LayoutContent; w: number; h: number } | null {
  const host = editHost.value
  if (!host) return null
  const only = selectedItem.value
  if (only && (only.content.type === 'widget' || only.content.type === 'group' || only.content.type === 'rotation')) {
    return { content: cloneLayout(only.content), w: only.w, h: only.h }
  }
  const group = selectionAsGroup(host, selectedSet.value)
  return group ? { content: group.content as LayoutGroupContent, w: group.w, h: group.h } : null
}

async function submitBlock() {
  const name = blockName.value.trim()
  if (!name) return
  blockSaving.value = true
  try {
    if (blockEditId.value) {
      const updated = await renameDisplayBlock(departmentId.value, blockEditId.value, name)
      customBlocks.value = customBlocks.value.map((b) => (b.id === updated.id ? updated : b))
      toast.success(t('display.studio.blocks.renamed'))
    } else {
      const payload = blockPayload()
      if (!payload) return
      const created = await createDisplayBlock(departmentId.value, { name, content: payload.content, default_w: payload.w, default_h: payload.h })
      customBlocks.value = [...customBlocks.value, created].sort((a, b) => a.name.localeCompare(b.name))
      toast.success(t('display.studio.blocks.saved'))
    }
    blockDialogOpen.value = false
  } catch (err) {
    const info = layoutErrorInfo(err)
    const first = info.errors[0]
    toast.error(first ? errorText(first) : t('display.studio.blocks.saveError'))
  } finally {
    blockSaving.value = false
  }
}

async function removeBlock(id: string) {
  const block = customBlocks.value.find((b) => b.id === id)
  if (!block) return
  const ok = await confirm.confirm({
    title: t('display.studio.blocks.deleteTitle'),
    message: t('display.studio.blocks.deleteConfirm', { name: block.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteDisplayBlock(departmentId.value, id)
    customBlocks.value = customBlocks.value.filter((b) => b.id !== id)
    toast.success(t('display.studio.blocks.deleted'))
  } catch {
    toast.error(t('display.studio.blocks.saveError'))
  }
}

// --- Werkzeugleiste und Tastatur ----------------------------------------------------------------------------------

function onToolbar(action: ToolbarAction) {
  switch (action) {
    case 'undo': undo(); break
    case 'redo': redo(); break
    case 'zoomIn': canvasRef.value?.zoomIn(); break
    case 'zoomOut': canvasRef.value?.zoomOut(); break
    case 'fit': canvasRef.value?.fit(); break
    case 'toggleGrid': showGrid.value = !showGrid.value; break
    case 'group': groupSelection(); break
    case 'ungroup': ungroupSelection(); break
    case 'copy': copySelection(); break
    case 'paste': pasteSelection(); break
    case 'duplicate': duplicateSelection(); break
    case 'delete': removeSelection(); break
    case 'save': void save(); break
    case 'publish': void startPublish(); break
    case 'preview': previewOpen.value = true; break
    case 'previewTab': openFullPreview(); break
    case 'revisions': void openRevisions(); break
    case 'template': chooserOpen.value = true; break
    case 'toggleLibrary': libraryOpen.value = !libraryOpen.value; break
    case 'toggleProps': propsOpen.value = !propsOpen.value; break
    case 'addVariant': newVariantOpen.value = true; break
  }
}

function undo() {
  if (history.undo()) selectedIds.value = selectedIds.value.filter((id) => editHost.value?.items.some((i) => i.id === id))
}
function redo() {
  if (history.redo()) selectedIds.value = selectedIds.value.filter((id) => editHost.value?.items.some((i) => i.id === id))
}

function isTyping(target: EventTarget | null): boolean {
  const el = target as HTMLElement | null
  return !!el && (el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName))
}

function anyDialogOpen(): boolean {
  return previewOpen.value || publishOpen.value || errorsOpen.value || conflictOpen.value || newVariantOpen.value || renameOpen.value || blockDialogOpen.value || chooserOpen.value || revisionsOpen.value
}

function moveByKey(key: string, resize: boolean) {
  const host = editHost.value
  if (!host || !selectedIds.value.length || isolateId.value) return
  const delta: Record<string, [number, number]> = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }
  const [dx, dy] = delta[key] ?? [0, 0]
  if (resize) {
    const item = selectedItem.value
    if (!item) return
    const next = planResize(host, item.id, { x: item.x, y: item.y, w: item.w, h: item.h }, 'se', dx, dy)
    if (!next) return notifyBlocked()
    checkpointEdit()
    Object.assign(item, next)
    markDirty()
    return
  }
  const origins = new Map(host.items.filter((i) => selectedSet.value.has(i.id)).map((i) => [i.id, { x: i.x, y: i.y, w: i.w, h: i.h }]))
  const result = planMove(host, origins, dx, dy, { dx: 0, dy: 0 })
  if (!result.exact) return notifyBlocked()
  checkpointEdit()
  for (const [id, o] of origins) {
    const it = host.items.find((i) => i.id === id)
    if (it) Object.assign(it, { x: o.x + dx, y: o.y + dy })
  }
  markDirty()
}

function onWindowKeyDown(event: KeyboardEvent) {
  if (!doc.value || isTyping(event.target) || anyDialogOpen()) return
  const mod = event.ctrlKey || event.metaKey
  const key = event.key
  if (mod && key.toLowerCase() === 'z') {
    event.preventDefault()
    if (event.shiftKey) redo()
    else undo()
  } else if (mod && key.toLowerCase() === 'y') {
    event.preventDefault()
    redo()
  } else if (mod && key.toLowerCase() === 'c') {
    event.preventDefault()
    copySelection()
  } else if (mod && key.toLowerCase() === 'v') {
    event.preventDefault()
    pasteSelection()
  } else if (mod && key.toLowerCase() === 'd') {
    event.preventDefault()
    duplicateSelection()
  } else if (mod && key.toLowerCase() === 'g') {
    event.preventDefault()
    if (event.shiftKey) ungroupSelection()
    else groupSelection()
  } else if (mod && key.toLowerCase() === 'a') {
    event.preventDefault()
    selectedIds.value = (editHost.value?.items ?? []).map((i) => i.id)
  } else if (mod && key.toLowerCase() === 's') {
    event.preventDefault()
    if (dirty.value) void save()
  } else if (mod && key === '0') {
    event.preventDefault()
    canvasRef.value?.fit()
  } else if (key === 'Delete' || key === 'Backspace') {
    if (selectedIds.value.length) {
      event.preventDefault()
      removeSelection()
    }
  } else if (key === 'Escape') {
    if (isolateId.value || (!selectedIds.value.length && path.value.length)) exitLevel()
    else selectedIds.value = []
  } else if (key.startsWith('Arrow') && selectedIds.value.length) {
    event.preventDefault()
    moveByKey(key, event.shiftKey)
  } else if (!mod && (key === '+' || key === '=')) {
    canvasRef.value?.zoomIn()
  } else if (!mod && key === '-') {
    canvasRef.value?.zoomOut()
  }
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

// --- Vorschau mit Geräteprofil -----------------------------------------------------------------------------------

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
.studio {
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 100%;
  min-height: 0;
  background: #f3f4f6;
  outline: none;
}
.studio__center { flex: 1; display: flex; align-items: center; justify-content: center; padding: 24px; }
.studio__chooser { flex-direction: column; align-items: stretch; overflow-y: auto; max-width: 980px; margin: 0 auto; width: 100%; }
.studio__body { flex: 1; min-height: 0; display: grid; transition: grid-template-columns 0.15s ease; }
.panel { display: flex; flex-direction: column; min-width: 0; min-height: 0; background: #fff; overflow: hidden; }
.panel--left { border-right: 1px solid #e5e7eb; }
.panel--right { border-left: 1px solid #e5e7eb; }
.panel__scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 12px; }
.stage { display: flex; flex-direction: column; min-width: 0; min-height: 0; }
.stage__canvas { position: relative; flex: 1; min-height: 0; }
.stage__badges { position: absolute; top: 10px; left: 12px; display: flex; gap: 6px; pointer-events: none; z-index: 5; }
.stage__badges .badge--btn { pointer-events: auto; }
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 12px; border-radius: 999px; font-size: 0.78rem; font-weight: 700; color: #fff; box-shadow: 0 1px 4px rgba(0, 0, 0, 0.3); }
.badge--demo { background: #f59e0b; }
.badge--live { background: #16a34a; }
.badge--iso { background: #7c3aed; }
.badge--btn { background: #1e293b; border: 1px solid #475569; cursor: pointer; }
.badge--nav { gap: 8px; }
.badge--nav button { border: 0; background: none; color: #fff; font-size: 1.1rem; line-height: 1; cursor: pointer; }
.badge--nav button:disabled { opacity: 0.35; cursor: default; }
.stage__blocked { position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%); margin: 0; padding: 6px 14px; border-radius: 8px; background: #991b1b; color: #fff; font-size: 0.85rem; z-index: 6; }
.tabs { display: flex; gap: 4px; padding: 8px 12px 0; }
.tabs__btn { flex: 1; padding: 6px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; cursor: pointer; }
.tabs__btn--on { border-color: #2563eb; background: #eff6ff; font-weight: 600; }
.multi { display: flex; flex-direction: column; gap: 10px; }
.multi h4 { margin: 0; font-size: 1rem; }
.multi__actions { display: flex; flex-wrap: wrap; gap: 8px; }
.empty .shortcuts { margin: 10px 0 0; padding-left: 1.1rem; color: #6b7280; font-size: 0.8rem; display: flex; flex-direction: column; gap: 4px; }
.preview-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 10px; }
.preview-bar__select { min-width: 280px; }
.preview-frame { margin: 0 auto; border-radius: 8px; overflow: hidden; background: #0b1220; }
.publish-mode { margin: 6px 0; padding: 8px 10px; border-radius: 8px; background: #dcfce7; color: #166534; font-weight: 600; }
.publish-mode--demo { background: #fef3c7; color: #92400e; }
.issues { margin: 0; padding-left: 1.2rem; }
.revisions { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.revisions li { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 8px; }
.revisions__actions { display: flex; gap: 6px; flex-wrap: wrap; }
.chip { padding: 2px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
.chip--ok { background: #dcfce7; color: #166534; }
.muted { color: #6b7280; }
.error { color: #b91c1c; }
@media (max-width: 760px) {
  .studio__body { grid-template-columns: minmax(0, 1fr) !important; }
  .panel--left, .panel--right { position: absolute; z-index: 20; top: 52px; bottom: 44px; width: min(92vw, 340px); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3); }
  .panel--right { right: 0; }
  .panel--left { left: 0; }
}
</style>
