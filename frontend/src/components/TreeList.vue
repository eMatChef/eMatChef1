<template>
  <div class="tree-list">
    <!-- Header -->
    <div class="tree-header">
      <div class="tree-header-cell checkbox-cell">
        <input
          type="checkbox"
          :checked="allSelected"
          :indeterminate="someSelected"
          @change="toggleSelectAll"
          class="tree-checkbox"
        />
      </div>
      <div class="tree-header-cell expand-cell"></div>
      <div class="tree-header-cell content-cell">
        <span class="header-label">{{ headerLabel || t('common.name') }}</span>
        <button class="sort-button" @click="toggleSort">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M4 6L8 2L12 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M4 10L8 14L12 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <span class="item-count">{{ totalCount }}</span>
      </div>
      <div class="tree-header-cell actions-cell">
        <span class="header-label">{{ t('common.actions') }}</span>
      </div>
    </div>

    <!-- Tree Content -->
    <div class="tree-content" ref="treeContentRef">
      <TreeItem
        v-for="item in sortedItems"
        :key="item.id"
        :item="item"
        :level="0"
        :selected-items="selectedItems"
        :expanded-items="expandedItems"
        @toggle-select="handleToggleSelect"
        @toggle-expand="handleToggleExpand"
        @edit="(item) => $emit('edit-item', item)"
        @show-users="(item) => $emit('show-users', item)"
        @show-details="(item) => $emit('show-details', item)"
        @show-department-details="(item) => $emit('show-department-details', item)"
        @add-department="(item) => $emit('add-department', item)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import TreeItem from './TreeItem.vue'

const { t } = useI18n()

export interface TreeItemData {
  id: string
  label: string
  type: 'group' | 'item'
  children?: TreeItemData[]
  data?: any // Zusätzliche Daten
}

interface Props {
  items: TreeItemData[]
  headerLabel?: string
  selectedItems?: string[]
  expandedItems?: string[]
}

const props = withDefaults(defineProps<Props>(), {
  headerLabel: '',
  selectedItems: () => [],
  expandedItems: () => []
})

const emit = defineEmits<{
  'update:selectedItems': [items: string[]]
  'update:expandedItems': [items: string[]]
  'selection-change': [selectedIds: string[]]
  'edit-item': [item: TreeItemData]
  'show-users': [item: TreeItemData]
  'show-details': [item: TreeItemData]
  'show-department-details': [item: TreeItemData]
  'add-department': [item: TreeItemData]
}>()

const treeContentRef = ref<HTMLElement>()
const sortAscending = ref(true)

// Lokale Kopien für v-model
const selectedItems = ref<string[]>([...props.selectedItems])
const expandedItems = ref<string[]>([...props.expandedItems])

// Watch props changes
watch(() => props.selectedItems, (newVal) => {
  selectedItems.value = [...newVal]
}, { deep: true })

watch(() => props.expandedItems, (newVal) => {
  expandedItems.value = [...newVal]
}, { deep: true })

// Gesamtzahl der Items (inkl. Kinder)
const totalCount = computed(() => {
  const countItems = (items: TreeItemData[]): number => {
    return items.reduce((count, item) => {
      return count + 1 + (item.children ? countItems(item.children) : 0)
    }, 0)
  }
  return countItems(props.items)
})

// Sortierte Items
const sortedItems = computed(() => {
  const sort = (items: TreeItemData[]): TreeItemData[] => {
    return [...items].sort((a, b) => {
      const comparison = a.label.localeCompare(b.label, 'de')
      return sortAscending.value ? comparison : -comparison
    }).map(item => ({
      ...item,
      children: item.children ? sort(item.children) : undefined
    }))
  }
  return sort(props.items)
})

// Alle ausgewählt?
const allSelected = computed(() => {
  const getAllIds = (items: TreeItemData[]): string[] => {
    const ids: string[] = []
    items.forEach(item => {
      ids.push(item.id)
      if (item.children) {
        ids.push(...getAllIds(item.children))
      }
    })
    return ids
  }
  const allIds = getAllIds(props.items)
  return allIds.length > 0 && allIds.every(id => selectedItems.value.includes(id))
})

// Einige ausgewählt?
const someSelected = computed(() => {
  const getAllIds = (items: TreeItemData[]): string[] => {
    const ids: string[] = []
    items.forEach(item => {
      ids.push(item.id)
      if (item.children) {
        ids.push(...getAllIds(item.children))
      }
    })
    return ids
  }
  const allIds = getAllIds(props.items)
  const selectedCount = allIds.filter(id => selectedItems.value.includes(id)).length
  return selectedCount > 0 && selectedCount < allIds.length
})

function toggleSelectAll(event: Event) {
  const target = event.target as HTMLInputElement
  const getAllIds = (items: TreeItemData[]): string[] => {
    const ids: string[] = []
    items.forEach(item => {
      ids.push(item.id)
      if (item.children) {
        ids.push(...getAllIds(item.children))
      }
    })
    return ids
  }
  
  const allIds = getAllIds(props.items)
  
  if (target.checked) {
    selectedItems.value = [...new Set([...selectedItems.value, ...allIds])]
  } else {
    selectedItems.value = selectedItems.value.filter(id => !allIds.includes(id))
  }
  
  emit('update:selectedItems', selectedItems.value)
  emit('selection-change', selectedItems.value)
}

function toggleSort() {
  sortAscending.value = !sortAscending.value
}

function handleToggleSelect(itemId: string, checked: boolean) {
  if (checked) {
    if (!selectedItems.value.includes(itemId)) {
      selectedItems.value.push(itemId)
    }
  } else {
    selectedItems.value = selectedItems.value.filter(id => id !== itemId)
  }
  
  emit('update:selectedItems', selectedItems.value)
  emit('selection-change', selectedItems.value)
}

function handleToggleExpand(itemId: string) {
  const index = expandedItems.value.indexOf(itemId)
  if (index > -1) {
    expandedItems.value.splice(index, 1)
  } else {
    expandedItems.value.push(itemId)
  }
  
  emit('update:expandedItems', expandedItems.value)
}
</script>

<style scoped>
.tree-list {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: white;
  overflow: hidden;
}

.tree-header {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  font-weight: 600;
  font-size: 14px;
  color: #374151;
}

.tree-header-cell {
  display: flex;
  align-items: center;
}

.checkbox-cell {
  width: 40px;
  flex-shrink: 0;
}

.expand-cell {
  width: 24px;
  flex-shrink: 0;
}

.content-cell {
  flex: 1;
  gap: 8px;
}

.actions-cell {
  width: 120px;
  flex-shrink: 0;
  justify-content: center;
}

.header-label {
  color: #3b82f6;
}

.sort-button {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  color: #6b7280;
  transition: color 0.2s;
}

.sort-button:hover {
  color: #374151;
}

.item-count {
  color: #6b7280;
  font-weight: normal;
  margin-left: auto;
}

.tree-content {
  max-height: 600px;
  overflow-y: auto;
}

.tree-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: #3b82f6;
}
</style>
