<template>
  <header class="tb" data-testid="studio-toolbar">
    <div class="tb__group tb__group--title">
      <router-link class="tb__back" :to="backTo" :title="t('display.studio.toolbar.backTitle')" data-testid="studio-back">
        <v-icon icon="mdi-arrow-left" size="18" aria-hidden="true" />
        <span class="tb__back-text">{{ t('display.studio.toolbar.back') }}</span>
      </router-link>
      <div class="tb__title">
        <strong class="tb__name" :title="screenName">{{ screenName }}</strong>
        <span class="tb__status">
          <span class="chip" :class="published ? 'chip--ok' : 'chip--idle'">{{ publishedText }}</span>
          <span v-if="dirty" class="chip chip--warn" data-testid="status-unsaved">{{ t('display.layout.editor.statusUnsaved') }}</span>
          <span v-else-if="hasUnpublished" class="chip chip--warn" data-testid="status-unpublished">{{ t('display.layout.editor.statusUnpublished') }}</span>
          <span v-else class="chip chip--ok">{{ t('display.layout.editor.statusSaved') }}</span>
        </span>
      </div>
    </div>

    <template v-if="hasDoc">
      <div class="tb__group">
        <select class="tb__select" :value="variantId" :aria-label="t('display.layout.editor.variant')" data-testid="studio-variant" @change="emit('update:variantId', ($event.target as HTMLSelectElement).value)">
          <option v-for="v in variants" :key="v.value" :value="v.value">{{ v.title }}</option>
        </select>
        <button type="button" class="tb__btn" :disabled="!canAddVariant" :title="t('display.layout.editor.addVariant')" @click="emit('action', 'addVariant')">
          <v-icon icon="mdi-plus" size="18" />
        </button>
      </div>

      <div class="tb__group">
        <button type="button" class="tb__btn" :disabled="!canUndo" :title="t('display.studio.toolbar.undo')" data-testid="btn-undo" @click="emit('action', 'undo')"><v-icon icon="mdi-undo" size="18" /></button>
        <button type="button" class="tb__btn" :disabled="!canRedo" :title="t('display.studio.toolbar.redo')" data-testid="btn-redo" @click="emit('action', 'redo')"><v-icon icon="mdi-redo" size="18" /></button>
      </div>

      <div class="tb__group">
        <button type="button" class="tb__btn" :title="t('display.studio.toolbar.zoomOut')" @click="emit('action', 'zoomOut')"><v-icon icon="mdi-magnify-minus-outline" size="18" /></button>
        <span class="tb__zoom" data-testid="studio-zoom">{{ Math.round(zoom * 100) }} %</span>
        <button type="button" class="tb__btn" :title="t('display.studio.toolbar.zoomIn')" @click="emit('action', 'zoomIn')"><v-icon icon="mdi-magnify-plus-outline" size="18" /></button>
        <button type="button" class="tb__btn" :title="t('display.studio.toolbar.fit')" data-testid="btn-fit" @click="emit('action', 'fit')"><v-icon icon="mdi-fit-to-screen-outline" size="18" /></button>
        <button type="button" class="tb__btn" :class="{ 'tb__btn--on': showGrid }" :aria-pressed="showGrid" :title="t('display.studio.toolbar.grid')" data-testid="btn-grid" @click="emit('action', 'toggleGrid')"><v-icon icon="mdi-grid" size="18" /></button>
      </div>

      <div class="tb__group">
        <button type="button" class="tb__btn" :disabled="!canGroup" :title="t('display.studio.actions.group')" data-testid="btn-group" @click="emit('action', 'group')"><v-icon icon="mdi-group" size="18" /></button>
        <button type="button" class="tb__btn" :disabled="!canUngroup" :title="t('display.studio.actions.ungroup')" @click="emit('action', 'ungroup')"><v-icon icon="mdi-ungroup" size="18" /></button>
        <button type="button" class="tb__btn" :disabled="!hasSelection" :title="t('display.studio.actions.copy')" @click="emit('action', 'copy')"><v-icon icon="mdi-content-copy" size="18" /></button>
        <button type="button" class="tb__btn" :disabled="!canPaste" :title="t('display.studio.actions.paste')" @click="emit('action', 'paste')"><v-icon icon="mdi-content-paste" size="18" /></button>
        <button type="button" class="tb__btn" :disabled="!hasSelection" :title="t('display.studio.actions.duplicate')" data-testid="btn-duplicate" @click="emit('action', 'duplicate')"><v-icon icon="mdi-content-duplicate" size="18" /></button>
        <button type="button" class="tb__btn" :disabled="!hasSelection" :title="t('display.studio.actions.delete')" data-testid="btn-delete" @click="emit('action', 'delete')"><v-icon icon="mdi-trash-can-outline" size="18" /></button>
      </div>

      <div class="tb__group tb__mode" role="group" :aria-label="t('display.studio.mode.label')" data-testid="data-mode">
        <button type="button" class="tb__seg" :class="{ 'tb__seg--on': previewMode === 'demo' }" :aria-pressed="previewMode === 'demo'" data-testid="mode-demo" :title="t('display.studio.mode.demoHint')" @click="emit('update:previewMode', 'demo')">{{ t('display.studio.mode.demo') }}</button>
        <button type="button" class="tb__seg" :class="{ 'tb__seg--on tb__seg--live': previewMode === 'live' }" :aria-pressed="previewMode === 'live'" data-testid="mode-live" :title="t('display.studio.mode.liveHint')" @click="emit('update:previewMode', 'live')">{{ t('display.studio.mode.live') }}</button>
      </div>
    </template>

    <div class="tb__spacer" />

    <div v-if="hasDoc" class="tb__group">
      <button type="button" class="tb__btn tb__btn--text" @click="emit('action', 'template')">{{ t('display.studio.toolbar.template') }}</button>
      <button type="button" class="tb__btn tb__btn--text" @click="emit('action', 'revisions')">{{ t('display.layout.editor.revisions') }}</button>
      <button type="button" class="tb__btn tb__btn--text" data-testid="btn-preview" @click="emit('action', 'preview')">{{ t('display.layout.editor.preview') }}</button>
      <button type="button" class="tb__btn tb__btn--text" :disabled="dirty" :title="dirty ? t('display.layout.editor.saveFirst') : ''" @click="emit('action', 'previewTab')">{{ t('display.layout.editor.previewTab') }}</button>
      <button type="button" class="tb__btn tb__btn--text" :disabled="!dirty || saving" data-testid="btn-save" @click="emit('action', 'save')">{{ saving ? t('display.studio.toolbar.saving') : t('display.layout.editor.save') }}</button>
      <button type="button" class="tb__btn tb__btn--primary" :disabled="publishChecking" data-testid="btn-publish" @click="emit('action', 'publish')">{{ t('display.layout.editor.publish') }}</button>
      <button type="button" class="tb__btn" :class="{ 'tb__btn--on': libraryOpen }" :aria-pressed="libraryOpen" :title="t('display.studio.toolbar.toggleLibrary')" @click="emit('action', 'toggleLibrary')"><v-icon icon="mdi-dock-left" size="18" /></button>
      <button type="button" class="tb__btn" :class="{ 'tb__btn--on': propsOpen }" :aria-pressed="propsOpen" :title="t('display.studio.toolbar.toggleProps')" data-testid="btn-toggle-props" @click="emit('action', 'toggleProps')"><v-icon icon="mdi-dock-right" size="18" /></button>
    </div>
  </header>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { LayoutRevisionInfo } from '@/api/displayLayout'
import type { LayoutDataMode } from '../layoutContext'

export type ToolbarAction =
  | 'undo' | 'redo' | 'zoomIn' | 'zoomOut' | 'fit' | 'toggleGrid'
  | 'group' | 'ungroup' | 'copy' | 'paste' | 'duplicate' | 'delete'
  | 'save' | 'publish' | 'preview' | 'previewTab' | 'revisions' | 'template'
  | 'toggleLibrary' | 'toggleProps' | 'addVariant'

/** Kompakte Werkzeugleiste des Studios; enthält keine Logik, sondern meldet nur Aktionen. */
const props = defineProps<{
  backTo: string
  screenName: string
  hasDoc: boolean
  published: LayoutRevisionInfo | null
  dirty: boolean
  hasUnpublished: boolean
  saving: boolean
  publishChecking: boolean
  variantId: string
  variants: { value: string; title: string }[]
  canAddVariant: boolean
  canUndo: boolean
  canRedo: boolean
  zoom: number
  showGrid: boolean
  hasSelection: boolean
  canGroup: boolean
  canUngroup: boolean
  canPaste: boolean
  previewMode: LayoutDataMode
  libraryOpen: boolean
  propsOpen: boolean
}>()

const emit = defineEmits<{
  action: [name: ToolbarAction]
  'update:variantId': [id: string]
  'update:previewMode': [mode: LayoutDataMode]
}>()

const { t, locale } = useI18n()

const publishedText = computed(() => {
  if (!props.published) return t('display.layout.editor.statusNotPublished')
  const d = new Date(props.published.published_at)
  const date = Number.isNaN(d.getTime()) ? props.published.published_at : d.toLocaleString(String(locale.value).startsWith('de') ? 'de-CH' : 'en-CH', { dateStyle: 'medium', timeStyle: 'short' })
  return t('display.layout.editor.statusPublished', { n: props.published.number, date })
})
</script>

<style scoped>
.tb {
  display: flex;
  align-items: center;
  gap: 6px 14px;
  flex-wrap: wrap;
  padding: 6px 12px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  min-height: 52px;
}
.tb__group { display: flex; align-items: center; gap: 4px; flex: 0 0 auto; }
.tb__group--title { gap: 12px; }
.tb__spacer { flex: 1 1 auto; min-width: 8px; }
.tb__back { display: inline-flex; align-items: center; gap: 4px; padding: 6px 8px; border-radius: 8px; color: #374151; text-decoration: none; font-size: 0.85rem; }
.tb__back:hover { background: #f3f4f6; }
.tb__title { display: flex; flex-direction: column; min-width: 0; line-height: 1.25; }
.tb__name { font-size: 0.95rem; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tb__status { display: flex; gap: 4px; flex-wrap: nowrap; }
.chip { padding: 0 8px; border-radius: 999px; font-size: 0.7rem; font-weight: 600; white-space: nowrap; }
.chip--ok { background: #dcfce7; color: #166534; }
.chip--idle { background: #f3f4f6; color: #374151; }
.chip--warn { background: #fef3c7; color: #92400e; }
.tb__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  min-width: 32px;
  height: 32px;
  padding: 0 6px;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  color: #374151;
  font-size: 0.85rem;
  white-space: nowrap;
  cursor: pointer;
}
.tb__btn:hover:not(:disabled) { background: #f3f4f6; }
.tb__btn:disabled { opacity: 0.4; cursor: not-allowed; }
.tb__btn:focus-visible, .tb__seg:focus-visible, .tb__select:focus-visible { outline: 2px solid #2563eb; outline-offset: 1px; }
.tb__btn--on { background: #dbeafe; color: #1d4ed8; }
.tb__btn--text { padding: 0 10px; border-color: #e5e7eb; }
.tb__btn--primary { padding: 0 14px; background: #2563eb; color: #fff; font-weight: 600; }
.tb__btn--primary:hover:not(:disabled) { background: #1d4ed8; }
.tb__zoom { min-width: 48px; text-align: center; font-size: 0.8rem; color: #374151; font-variant-numeric: tabular-nums; }
.tb__select { max-width: 200px; height: 32px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; padding: 0 8px; font-size: 0.85rem; }
.tb__mode { border: 1px solid #d1d5db; border-radius: 999px; padding: 2px; background: #f9fafb; gap: 0; }
.tb__seg { border: 0; border-radius: 999px; padding: 4px 14px; background: transparent; font-size: 0.82rem; font-weight: 600; color: #4b5563; cursor: pointer; }
.tb__seg--on { background: #f59e0b; color: #fff; }
.tb__seg--live.tb__seg--on { background: #16a34a; }
@media (max-width: 900px) {
  .tb__back-text { display: none; }
}
</style>
