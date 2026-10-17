<template>
  <div class="e-form-field autosave-field parent-tree-select">
    <v-menu
      v-model="menuOpen"
      :close-on-content-click="false"
      :disabled="disabled"
      location="bottom"
      max-height="420"
      @update:model-value="onMenuToggle"
    >
      <template #activator="{ props: activatorProps }">
        <div class="autosave-control">
          <div class="autosave-field-frame">
            <label v-if="label" class="field-outline-label autosave-label">{{ label }}</label>
            <button
              v-bind="activatorProps"
              type="button"
              class="parent-tree-select__field"
              :disabled="disabled"
              aria-haspopup="tree"
              :aria-expanded="menuOpen"
            >
              <span v-if="selectedNode" class="parent-tree-select__value">
                <GrossanlassGroupNodeIcon :node-type="selectedNode.group.node_type" :size="16" />
                <span class="parent-tree-select__text">
                  <span class="parent-tree-select__name">{{ selectedNode.group.name }}</span>
                  <span v-if="selectedPath" class="parent-tree-select__path">{{ selectedPath }}</span>
                </span>
              </span>
              <span v-else-if="noneLabel" class="parent-tree-select__value">{{ noneLabel }}</span>
              <span v-else class="parent-tree-select__value parent-tree-select__placeholder">&nbsp;</span>
              <v-icon icon="mdi-menu-down" size="22" />
            </button>
          </div>
        </div>
      </template>

      <v-card class="parent-tree-select__panel" elevation="4">
        <div class="parent-tree-select__search">
          <v-icon icon="mdi-magnify" size="18" />
          <input
            v-model="query"
            type="search"
            class="parent-tree-select__search-input"
            :placeholder="t('grossanlass.planung.ressorts.parentSearch')"
            :aria-label="t('grossanlass.planung.ressorts.parentSearch')"
          >
        </div>

        <ul class="parent-tree-select__list" role="tree">
          <li v-if="noneLabel && !isSearching" role="none">
            <button
              type="button"
              role="treeitem"
              class="parent-tree-select__row"
              :class="{ 'parent-tree-select__row--on': !model }"
              :aria-selected="!model"
              @click="pick(null)"
            >
              <span class="parent-tree-select__toggle-spacer" />
              <span class="parent-tree-select__name">{{ noneLabel }}</span>
            </button>
          </li>

          <template v-if="isSearching">
            <li v-for="hit in searchHits" :key="hit.id" role="none">
              <button
                type="button"
                role="treeitem"
                class="parent-tree-select__row parent-tree-select__row--hit"
                :class="{ 'parent-tree-select__row--on': model === hit.id }"
                :aria-selected="model === hit.id"
                :disabled="!hit.selectable"
                @click="pick(hit.id)"
              >
                <GrossanlassGroupNodeIcon :node-type="hit.group.node_type" :size="16" />
                <span class="parent-tree-select__text">
                  <span class="parent-tree-select__name">{{ hit.group.name }}</span>
                  <span v-if="hit.path" class="parent-tree-select__path">{{ hit.path }}</span>
                </span>
              </button>
            </li>
            <li v-if="!searchHits.length" class="parent-tree-select__empty">
              {{ t('grossanlass.planung.ressorts.parentSearchEmpty') }}
            </li>
          </template>

          <template v-else>
            <li v-for="row in visibleRows" :key="row.id" role="none">
              <div
                class="parent-tree-select__row"
                :class="{ 'parent-tree-select__row--on': model === row.id }"
                :style="{ paddingLeft: `${8 + row.depth * 20}px` }"
              >
                <button
                  v-if="row.hasChildren"
                  type="button"
                  class="parent-tree-select__toggle"
                  :aria-label="expanded.has(row.id)
                    ? t('grossanlass.planung.ressorts.parentCollapse')
                    : t('grossanlass.planung.ressorts.parentExpand')"
                  @click.stop="toggle(row.id)"
                >
                  <v-icon :icon="expanded.has(row.id) ? 'mdi-chevron-down' : 'mdi-chevron-right'" size="18" />
                </button>
                <span v-else class="parent-tree-select__toggle-spacer" />
                <button
                  type="button"
                  role="treeitem"
                  class="parent-tree-select__pick"
                  :aria-selected="model === row.id"
                  :aria-expanded="row.hasChildren ? expanded.has(row.id) : undefined"
                  :disabled="!row.selectable"
                  @click="pick(row.id)"
                >
                  <GrossanlassGroupNodeIcon :node-type="row.group.node_type" :size="16" />
                  <span class="parent-tree-select__name">{{ row.group.name }}</span>
                  <v-icon v-if="model === row.id" icon="mdi-check" size="16" class="parent-tree-select__check" />
                </button>
              </div>
            </li>
            <li v-if="!visibleRows.length && !noneLabel" class="parent-tree-select__empty">
              {{ t('grossanlass.planung.ressorts.parentSearchEmpty') }}
            </li>
          </template>
        </ul>
      </v-card>
    </v-menu>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useExclusiveOverlay } from '@/composables/useExclusiveOverlay'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import GrossanlassGroupNodeIcon from '@/components/grossanlass/GrossanlassGroupNodeIcon.vue'

defineOptions({ name: 'GrossanlassParentTreeSelect' })

type TreeNode = {
  id: string
  group: GrossanlassGroup
  selectable: boolean
  children: TreeNode[]
}

const props = withDefaults(defineProps<{
  /** Alle Gruppen der Struktur (liefert die Hierarchie). */
  groups: GrossanlassGroup[]
  /** IDs, die als Eltern gewählt werden dürfen; andere Knoten erscheinen nur als Pfad. */
  selectableIds: string[]
  /** Gruppe, die bearbeitet wird: sie und alle Nachkommen sind nicht wählbar und nicht sichtbar. */
  excludeId?: string | null
  /** Beschriftung der «Kein Parent»-Zeile; ohne Wert gibt es keine solche Zeile. */
  noneLabel?: string
  label?: string
  disabled?: boolean
}>(), {
  excludeId: null,
  noneLabel: '',
  label: '',
  disabled: false,
})

const model = defineModel<string | null>({ default: null })
const { t } = useI18n()

const menuOpen = ref(false)
useExclusiveOverlay(menuOpen)
const query = ref('')
const expanded = ref(new Set<string>())

const byId = computed(() => new Map(props.groups.map((g) => [g.id, g])))

const blockedIds = computed(() => {
  const blocked = new Set<string>()
  if (!props.excludeId) return blocked
  blocked.add(props.excludeId)
  const queue = [props.excludeId]
  while (queue.length) {
    const id = queue.shift()!
    for (const g of props.groups) {
      if (g.parent_id === id && !blocked.has(g.id)) {
        blocked.add(g.id)
        queue.push(g.id)
      }
    }
  }
  return blocked
})

const roots = computed<TreeNode[]>(() => {
  const selectable = new Set(props.selectableIds)
  const usable = props.groups.filter((g) => !blockedIds.value.has(g.id))
  const childrenOf = new Map<string, GrossanlassGroup[]>()
  const top: GrossanlassGroup[] = []
  const usableIds = new Set(usable.map((g) => g.id))
  for (const g of usable) {
    if (g.parent_id && usableIds.has(g.parent_id)) {
      const list = childrenOf.get(g.parent_id) ?? []
      list.push(g)
      childrenOf.set(g.parent_id, list)
    } else {
      top.push(g)
    }
  }
  const sorter = (a: GrossanlassGroup, b: GrossanlassGroup) =>
    (a.sort_order - b.sort_order) || a.name.localeCompare(b.name, 'de')
  const seen = new Set<string>()
  function build(g: GrossanlassGroup): TreeNode | null {
    if (seen.has(g.id)) return null
    seen.add(g.id)
    const children = (childrenOf.get(g.id) ?? [])
      .sort(sorter)
      .map(build)
      .filter((n): n is TreeNode => n !== null)
    const isSelectable = selectable.has(g.id)
    if (!isSelectable && children.length === 0) return null
    return { id: g.id, group: g, selectable: isSelectable, children }
  }
  return top.sort(sorter).map(build).filter((n): n is TreeNode => n !== null)
})

const flatNodes = computed(() => {
  const out: Array<{ node: TreeNode, depth: number, path: string }> = []
  function walk(nodes: TreeNode[], depth: number, trail: string[]) {
    for (const node of nodes) {
      out.push({ node, depth, path: trail.join(' › ') })
      walk(node.children, depth + 1, [...trail, node.group.name])
    }
  }
  walk(roots.value, 0, [])
  return out
})

const visibleRows = computed(() => {
  const rows: Array<{ id: string, group: GrossanlassGroup, selectable: boolean, depth: number, hasChildren: boolean }> = []
  function walk(nodes: TreeNode[], depth: number) {
    for (const node of nodes) {
      rows.push({
        id: node.id,
        group: node.group,
        selectable: node.selectable,
        depth,
        hasChildren: node.children.length > 0,
      })
      if (node.children.length && expanded.value.has(node.id)) walk(node.children, depth + 1)
    }
  }
  walk(roots.value, 0)
  return rows
})

const isSearching = computed(() => query.value.trim().length > 0)

const searchHits = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase('de')
  return flatNodes.value
    .filter(({ node, path }) =>
      node.group.name.toLocaleLowerCase('de').includes(needle)
      || path.toLocaleLowerCase('de').includes(needle))
    .map(({ node, path }) => ({ id: node.id, group: node.group, selectable: node.selectable, path }))
})

const selectedNode = computed(() => {
  if (!model.value) return null
  const hit = flatNodes.value.find(({ node }) => node.id === model.value)
  if (hit) return { group: hit.node.group, path: hit.path }
  const group = byId.value.get(model.value)
  return group ? { group, path: '' } : null
})
const selectedPath = computed(() => selectedNode.value?.path ?? '')

function ancestorIds(id: string): string[] {
  const ids: string[] = []
  const seen = new Set<string>([id])
  let current = byId.value.get(id)
  while (current?.parent_id && !seen.has(current.parent_id)) {
    ids.push(current.parent_id)
    seen.add(current.parent_id)
    current = byId.value.get(current.parent_id)
  }
  return ids
}

function onMenuToggle(open: boolean) {
  if (!open) {
    query.value = ''
    return
  }
  // Pfad zum aktuellen Elternteil aufklappen, bereits geöffnete Zweige bleiben offen.
  const next = new Set(expanded.value)
  if (model.value) for (const id of ancestorIds(model.value)) next.add(id)
  expanded.value = next
}

function toggle(id: string) {
  const next = new Set(expanded.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expanded.value = next
}

function pick(id: string | null) {
  model.value = id
  menuOpen.value = false
}
</script>

<style scoped>
.parent-tree-select__field {
  width: 100%;
  min-height: 48px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 6px 12px;
  border: 1px solid var(--color-border, rgba(0, 0, 0, 0.38));
  border-radius: 8px;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.parent-tree-select__field:disabled {
  opacity: 0.6;
  cursor: default;
}

.parent-tree-select__field:focus-visible {
  outline: 2px solid var(--color-primary, #0f766e);
  outline-offset: 1px;
}

.parent-tree-select__value {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.parent-tree-select__text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.parent-tree-select__name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.parent-tree-select__path {
  font-size: 0.75rem;
  opacity: 0.65;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.parent-tree-select__panel {
  min-width: 320px;
  max-height: 420px;
  display: flex;
  flex-direction: column;
}

.parent-tree-select__search {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.12);
}

.parent-tree-select__search-input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: inherit;
  font: inherit;
}

.parent-tree-select__list {
  list-style: none;
  margin: 0;
  padding: 4px;
  overflow-y: auto;
}

.parent-tree-select__row {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 2px 8px 2px 8px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: inherit;
  text-align: left;
}

.parent-tree-select__row--hit {
  padding: 6px 8px;
  cursor: pointer;
}

.parent-tree-select__row:hover {
  background: rgba(0, 0, 0, 0.04);
}

.parent-tree-select__row--on {
  background: rgba(15, 118, 110, 0.12);
  font-weight: 600;
}

.parent-tree-select__row:disabled,
.parent-tree-select__pick:disabled {
  opacity: 0.55;
  cursor: default;
}

.parent-tree-select__toggle,
.parent-tree-select__toggle-spacer {
  width: 24px;
  height: 24px;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 0;
  background: transparent;
  color: inherit;
}

.parent-tree-select__toggle {
  cursor: pointer;
  border-radius: 6px;
}

.parent-tree-select__pick {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 4px;
  border: 0;
  background: transparent;
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.parent-tree-select__check {
  margin-left: auto;
}

.parent-tree-select__empty {
  padding: 12px;
  opacity: 0.7;
}
</style>
