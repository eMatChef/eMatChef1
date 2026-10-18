<template>
  <div class="lib" data-testid="studio-library">
    <div class="lib__search">
      <input
        v-model="query"
        type="search"
        class="lib__input"
        :placeholder="t('display.studio.library.search')"
        :aria-label="t('display.studio.library.search')"
        data-testid="library-search"
      />
    </div>
    <div class="lib__scroll">
      <p v-if="!groups.length" class="lib__empty">{{ t('display.studio.library.noResults') }}</p>
      <section v-for="group in groups" :key="group.category" class="lib__group" :data-testid="`library-category-${group.category}`">
        <button
          type="button"
          class="lib__head"
          :aria-expanded="isOpen(group.category)"
          @click="toggle(group.category)"
        >
          <v-icon :icon="isOpen(group.category) ? 'mdi-chevron-down' : 'mdi-chevron-right'" size="18" aria-hidden="true" />
          <span class="lib__title">{{ t(`display.studio.library.category.${group.category}`) }}</span>
          <span class="lib__count">{{ group.entries.length }}</span>
        </button>
        <ul v-show="isOpen(group.category)" class="lib__list">
          <li v-if="!group.entries.length" class="lib__hint">{{ t(group.category === 'rotation' ? 'display.studio.library.rotationEmpty' : 'display.studio.library.customEmpty') }}</li>
          <li v-for="entry in group.entries" :key="entry.key" class="lib__row">
            <button
              type="button"
              class="lib__item"
              draggable="true"
              :data-testid="entry.source === 'widget' ? `palette-${entry.id}` : `block-${entry.key}`"
              :title="descriptionOf(entry)"
              @click="emit('add', entry)"
              @dragstart="onDragStart($event, entry)"
            >
              <span class="lib__thumb" aria-hidden="true">
                <template v-if="entry.preview.length">
                  <i v-for="(r, i) in entry.preview" :key="i" :style="{ left: `${r.x * 100}%`, top: `${r.y * 100}%`, width: `${r.w * 100}%`, height: `${r.h * 100}%` }" />
                </template>
                <v-icon v-else :icon="iconOf(entry)" size="20" />
              </span>
              <span class="lib__text">
                <span class="lib__name">{{ nameOf(entry) }}</span>
                <span class="lib__desc">{{ descriptionOf(entry) }}</span>
                <span class="lib__tags">
                  <span class="lib__tag">{{ t(`display.studio.library.kind.${entry.kind}`) }}</span>
                  <span v-if="isDemoOnly(entry)" class="lib__tag lib__tag--warn">{{ t('display.studio.library.noLive') }}</span>
                </span>
              </span>
            </button>
            <span v-if="entry.source === 'custom'" class="lib__actions">
              <button type="button" class="lib__icon" :aria-label="t('display.studio.library.rename')" @click="emit('rename', entry.id)">
                <v-icon icon="mdi-pencil-outline" size="16" />
              </button>
              <button type="button" class="lib__icon" :aria-label="t('common.delete')" @click="emit('remove', entry.id)">
                <v-icon icon="mdi-trash-can-outline" size="16" />
              </button>
            </span>
          </li>
        </ul>
      </section>
    </div>
    <p class="lib__footer">{{ t('display.studio.library.hint') }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { WidgetDefinition } from '@/api/displayLayout'
import { groupLibrary, type LibraryCategory, type LibraryEntry } from './libraryModel'

/** Kompakte, durchsuchbare Bausteinbibliothek: Einzelwidgets, Widget-Gruppen, Layoutbereiche und eigene Bausteine. */
const props = defineProps<{
  entries: LibraryEntry[]
  widgetDefs: Map<string, WidgetDefinition>
  /** Im Live-Modus: Bausteine ohne Live-Anbindung kennzeichnen. */
  highlightNoLive?: boolean
}>()

const emit = defineEmits<{
  add: [entry: LibraryEntry]
  rename: [id: string]
  remove: [id: string]
}>()

const { t, te } = useI18n()
const query = ref('')
const open = reactive<Record<string, boolean>>({ rotation: true, general: true, material: true, logistics: true, templates: true, custom: true })

const ICONS: Record<string, string> = {
  header: 'mdi-page-layout-header',
  text: 'mdi-format-title',
  clock: 'mdi-clock-outline',
  image: 'mdi-image-outline',
  notice: 'mdi-alert-circle-outline',
  divider: 'mdi-minus',
  kpi: 'mdi-numeric',
  list: 'mdi-format-list-bulleted',
  progress: 'mdi-progress-check',
  ga_material_overview: 'mdi-package-variant-closed',
  ga_pack_progress: 'mdi-package-variant',
  ga_missing_material: 'mdi-package-variant-closed-remove',
  ga_goods_receipt: 'mdi-truck-delivery-outline',
  ga_transport_overview: 'mdi-truck-outline',
  ga_dispatch_tours: 'mdi-routes',
  ga_overview: 'mdi-view-dashboard-outline',
  ga_projects: 'mdi-hammer-wrench',
  ga_tasks: 'mdi-checkbox-marked-outline',
  ga_helpers: 'mdi-account-group-outline',
  ga_site_map: 'mdi-map-outline',
  ga_activity_feed: 'mdi-timeline-text-outline',
  ga_warnings: 'mdi-alert-outline',
  rotation: 'mdi-view-carousel-outline',
}

function iconOf(entry: LibraryEntry): string {
  return ICONS[entry.id] ?? 'mdi-widgets-outline'
}

function nameOf(entry: LibraryEntry): string {
  if (entry.source === 'custom') return entry.name ?? ''
  if (entry.source === 'official') return t(`display.studio.block.${entry.id}.name`)
  if (entry.source === 'builtin') return t('display.studio.library.builtin.rotation.name')
  return t(`display.layout.widget.${entry.id}`)
}

function descriptionOf(entry: LibraryEntry): string {
  if (entry.source === 'custom') return t(`display.studio.library.customDesc.${entry.kind}`)
  if (entry.source === 'builtin') return t('display.studio.library.builtin.rotation.desc')
  const key = entry.source === 'official' ? `display.studio.block.${entry.id}.desc` : `display.studio.widgetDesc.${entry.id}`
  return te(key) ? t(key) : ''
}

function isDemoOnly(entry: LibraryEntry): boolean {
  return !!props.highlightNoLive && entry.source === 'widget' && props.widgetDefs.get(entry.id)?.live === false
}

const needle = computed(() => query.value.trim().toLowerCase())
const groups = computed(() =>
  groupLibrary(
    props.entries,
    (entry) => !needle.value || `${nameOf(entry)} ${descriptionOf(entry)} ${entry.id}`.toLowerCase().includes(needle.value),
    needle.value ? [] : ['custom', 'rotation'],
  ),
)

function isOpen(category: LibraryCategory): boolean {
  // Bei einer Suche sind alle Treffer sichtbar.
  return !!needle.value || open[category] === true
}
function toggle(category: LibraryCategory): void {
  open[category] = !open[category]
}

function onDragStart(event: DragEvent, entry: LibraryEntry): void {
  event.dataTransfer?.setData('application/x-emc-block', JSON.stringify({ source: entry.source, id: entry.id }))
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copy'
}
</script>

<style scoped>
.lib { display: flex; flex-direction: column; min-height: 0; height: 100%; color: #111827; }
.lib__search { padding: 10px 10px 6px; }
.lib__input {
  width: 100%;
  padding: 7px 10px;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  background: #fff;
  color: #111827;
  font-size: 0.85rem;
}
.lib__input:focus { outline: 2px solid #3b82f6; outline-offset: 0; }
.lib__scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 0 6px 8px; }
.lib__empty { padding: 12px; color: #6b7280; font-size: 0.85rem; }
.lib__head {
  display: flex;
  align-items: center;
  gap: 4px;
  width: 100%;
  padding: 7px 6px;
  border: 0;
  background: none;
  color: #4b5563;
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  cursor: pointer;
}
.lib__head:hover { color: #111827; }
.lib__title { flex: 1; text-align: left; }
.lib__count { font-weight: 500; color: #9ca3af; }
.lib__list { list-style: none; margin: 0 0 6px; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.lib__hint { padding: 4px 8px; color: #6b7280; font-size: 0.8rem; }
.lib__row { display: flex; align-items: stretch; gap: 2px; }
.lib__item {
  flex: 1;
  min-width: 0;
  display: flex;
  gap: 10px;
  align-items: flex-start;
  padding: 7px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  color: inherit;
  text-align: left;
  cursor: grab;
}
.lib__item:hover { border-color: #2563eb; background: #eff6ff; }
.lib__item:focus-visible { outline: 2px solid #2563eb; }
.lib__thumb {
  position: relative;
  flex: 0 0 44px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 5px;
  background: #0b1220;
  border: 1px solid #1e293b;
  color: #93c5fd;
  overflow: hidden;
}
.lib__thumb i { position: absolute; box-sizing: border-box; border: 1px solid #0b1220; border-radius: 2px; background: #3b82f6; }
.lib__text { display: flex; flex-direction: column; min-width: 0; gap: 1px; }
.lib__name { font-size: 0.86rem; font-weight: 600; color: #111827; overflow-wrap: anywhere; }
.lib__desc { font-size: 0.75rem; color: #6b7280; overflow-wrap: anywhere; }
.lib__tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 2px; }
.lib__tag { padding: 0 6px; border-radius: 999px; background: #f3f4f6; color: #4b5563; font-size: 0.68rem; }
.lib__tag--warn { background: #fef3c7; color: #92400e; }
.lib__actions { display: flex; flex-direction: column; gap: 2px; }
.lib__icon { border: 0; border-radius: 6px; background: #f3f4f6; color: #4b5563; padding: 4px; cursor: pointer; }
.lib__icon:hover { background: #e5e7eb; color: #111827; }
.lib__footer { margin: 0; padding: 8px 12px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 0.75rem; }
</style>
