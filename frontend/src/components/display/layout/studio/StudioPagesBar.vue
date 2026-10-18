<template>
  <nav class="pb" data-testid="studio-pages" :aria-label="t('display.layout.editor.pages')">
    <ol class="pb__crumbs" data-testid="studio-breadcrumb">
      <li v-for="(crumb, i) in crumbs" :key="crumb.key">
        <button type="button" class="pb__crumb" :class="{ 'pb__crumb--last': i === crumbs.length - 1 }" :disabled="i === crumbs.length - 1" @click="emit('navigate', crumb.pathLength)">{{ crumb.label }}</button>
        <v-icon v-if="i < crumbs.length - 1" icon="mdi-chevron-right" size="16" aria-hidden="true" />
      </li>
    </ol>
    <div class="pb__tabs" role="tablist">
      <button
        v-for="p in pages"
        :key="p.id"
        type="button"
        role="tab"
        class="pb__tab"
        :class="{ 'pb__tab--on': p.id === pageId }"
        :aria-selected="p.id === pageId"
        :data-testid="`page-${p.id}`"
        @click="emit('select', p.id)"
      >{{ p.name || p.id }}</button>
      <button type="button" class="pb__icon" :disabled="!canAdd" :title="t('display.layout.editor.addPage')" data-testid="btn-add-page" @click="emit('add')"><v-icon icon="mdi-plus" size="16" /></button>
      <button type="button" class="pb__icon" :title="t('display.layout.editor.renamePage')" @click="emit('rename')"><v-icon icon="mdi-pencil-outline" size="16" /></button>
      <button type="button" class="pb__icon" :disabled="!canDelete" :title="canDelete ? t('display.layout.editor.deletePage') : t('display.layout.editor.pageInUse')" @click="emit('remove')"><v-icon icon="mdi-trash-can-outline" size="16" /></button>
    </div>
    <span v-if="hint" class="pb__hint" role="status">{{ hint }}</span>
  </nav>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'

/** Seitenleiste am unteren Rand der Arbeitsfläche samt Pfad (Seite › Gruppe › Baustein). */
defineProps<{
  pages: { id: string; name: string }[]
  pageId: string
  crumbs: { key: string; label: string; pathLength: number }[]
  canAdd: boolean
  canDelete: boolean
  hint?: string
}>()

const emit = defineEmits<{
  select: [id: string]
  add: []
  rename: []
  remove: []
  /** Länge des Pfades, zu dem zurückgekehrt wird. */
  navigate: [pathLength: number]
}>()

const { t } = useI18n()
</script>

<style scoped>
.pb { display: flex; align-items: center; gap: 12px; padding: 6px 12px; background: #fff; border-top: 1px solid #e5e7eb; overflow-x: auto; min-height: 44px; scrollbar-width: thin; }
.pb__crumbs { display: flex; align-items: center; list-style: none; margin: 0; padding: 0; flex: 0 0 auto; }
.pb__crumbs li { display: inline-flex; align-items: center; }
.pb__crumb { border: 0; background: none; padding: 4px 6px; border-radius: 6px; color: #2563eb; cursor: pointer; font-size: 0.85rem; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pb__crumb:hover:not(:disabled) { background: #eff6ff; }
.pb__crumb--last { color: #111827; font-weight: 700; cursor: default; }
.pb__tabs { display: flex; align-items: center; gap: 4px; flex: 1 1 auto; }
.pb__tab { border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; padding: 4px 12px; font-size: 0.85rem; cursor: pointer; white-space: nowrap; }
.pb__tab:hover { background: #f3f4f6; }
.pb__tab--on { border-color: #2563eb; background: #eff6ff; font-weight: 600; }
.pb__icon { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: 0; border-radius: 6px; background: transparent; color: #4b5563; cursor: pointer; }
.pb__icon:hover:not(:disabled) { background: #f3f4f6; }
.pb__icon:disabled { opacity: 0.4; cursor: not-allowed; }
.pb__hint { color: #b91c1c; font-size: 0.8rem; white-space: nowrap; }
</style>
