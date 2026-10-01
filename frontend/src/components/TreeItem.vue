<template>
  <div class="tree-item" :class="{ 'is-group': item.type === 'group', 'is-selected': isSelected }">
    <!-- Item Row -->
    <div class="tree-row" :style="{ paddingLeft: `${level * 24 + 16}px` }">
      <!-- Checkbox -->
      <div class="tree-cell checkbox-cell">
        <input
          type="checkbox"
          :checked="isSelected"
          @change="handleToggleSelect"
          class="tree-checkbox"
        />
      </div>

      <!-- Expand/Collapse -->
      <div class="tree-cell expand-cell">
        <button
          v-if="hasChildren"
          @click="handleToggleExpand"
          class="expand-button"
          :class="{ expanded: isExpanded }"
        >
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path
              d="M6 4L10 8L6 12"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </button>
      </div>

      <!-- Content -->
      <div class="tree-cell content-cell">
        <div
          class="item-content"
          :class="{ 'group-content-clickable': item.type === 'group' }"
          @click="handleContentClick"
          @dblclick.stop="handleContentDoubleClick"
        >
          <!-- Group Icon (Organisation / Department / Grossanlass) -->
          <span
            v-if="item.type === 'group'"
            class="tree-node-icon-wrap"
            :class="`tree-node-icon-wrap--${nodeKind}`"
            :title="groupIconTitle"
          >
            <v-icon
              :icon="groupIconName"
              class="tree-node-icon"
              size="16"
              aria-hidden="true"
            />
          </span>

          <!-- Item Indent Icon -->
          <span v-else class="item-indent">└</span>

          <span class="item-label" :class="{ 'is-bold': item.type === 'group' }">
            {{ item.label }}
          </span>
          <span v-if="nodeKind === 'grossanlass'" class="tree-node-badge tree-node-badge--grossanlass">
            {{ t('components.treeItem.badgeGrossanlass') }}
          </span>
          <span v-else-if="nodeKind === 'department' && item.type === 'group'" class="tree-node-badge tree-node-badge--department">
            {{ t('components.treeItem.badgeDepartment') }}
          </span>
        </div>
      </div>

      <!-- Actions -->
      <div class="tree-cell actions-cell" @dblclick.stop>
        <!-- Organisationen: Bleistift UND 3-Punkte-Menü -->
        <div v-if="item.type === 'group' && item.id.startsWith('org-')" class="actions-group">
          <button
            v-if="allowManageUsers"
            @click.stop="handleManageUsers"
            class="users-button"
            :title="t('components.treeItem.manageUsers')"
          >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path
                d="M8 8C9.65685 8 11 6.65685 11 5C11 3.34315 9.65685 2 8 2C6.34315 2 5 3.34315 5 5C5 6.65685 6.34315 8 8 8Z"
                fill="currentColor"
              />
              <path
                d="M2 14C2 11.7909 4.23858 10 7 10H9C11.7614 10 14 11.7909 14 14V14.5H2V14Z"
                fill="currentColor"
              />
            </svg>
          </button>
          <button @click.stop="handleEdit" class="edit-button" :title="t('common.edit')">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path
                d="M11.3333 2.00001C11.5084 1.8249 11.7163 1.68601 11.9444 1.59124C12.1726 1.49648 12.4163 1.44775 12.6625 1.44775C12.9087 1.44775 13.1524 1.49648 13.3806 1.59124C13.6087 1.68601 13.8166 1.8249 13.9917 2.00001C14.1668 2.17512 14.3057 2.38301 14.4005 2.61118C14.4952 2.83935 14.544 3.08306 14.544 3.32918C14.544 3.5753 14.4952 3.81901 14.4005 4.04718C14.3057 4.27535 14.1668 4.48324 13.9917 4.65835L5.32499 13.325L2 14L2.67499 10.675L11.3333 2.00001Z"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </button>
          <div class="menu-container" :data-menu-id="item.id">
            <button
              ref="menuButtonRef"
              @click.stop="toggleMenu"
              class="menu-button"
              :title="t('components.treeItem.titleMenu')"
            >
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <circle cx="4" cy="8" r="1.5" fill="currentColor"/>
                <circle cx="8" cy="8" r="1.5" fill="currentColor"/>
                <circle cx="12" cy="8" r="1.5" fill="currentColor"/>
              </svg>
            </button>
          </div>
        </div>
        <!-- Departments: Bleistift UND 3-Punkte-Menü -->
        <div v-else-if="item.type === 'group' && item.id.startsWith('dept-')" class="actions-group">
          <button
            v-if="allowManageUsers"
            @click.stop="handleManageUsers"
            class="users-button"
            :title="t('components.treeItem.manageUsers')"
          >
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path
                d="M8 8C9.65685 8 11 6.65685 11 5C11 3.34315 9.65685 2 8 2C6.34315 2 5 3.34315 5 5C5 6.65685 6.34315 8 8 8Z"
                fill="currentColor"
              />
              <path
                d="M2 14C2 11.7909 4.23858 10 7 10H9C11.7614 10 14 11.7909 14 14V14.5H2V14Z"
                fill="currentColor"
              />
            </svg>
          </button>
          <button @click.stop="handleEdit" class="edit-button" :title="t('common.edit')">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path
                d="M11.3333 2.00001C11.5084 1.8249 11.7163 1.68601 11.9444 1.59124C12.1726 1.49648 12.4163 1.44775 12.6625 1.44775C12.9087 1.44775 13.1524 1.49648 13.3806 1.59124C13.6087 1.68601 13.8166 1.8249 13.9917 2.00001C14.1668 2.17512 14.3057 2.38301 14.4005 2.61118C14.4952 2.83935 14.544 3.08306 14.544 3.32918C14.544 3.5753 14.4952 3.81901 14.4005 4.04718C14.3057 4.27535 14.1668 4.48324 13.9917 4.65835L5.32499 13.325L2 14L2.67499 10.675L11.3333 2.00001Z"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </button>
          <div class="menu-container" :data-menu-id="item.id">
            <button
              ref="menuButtonRef"
              @click.stop="toggleMenu"
              class="menu-button"
              :title="t('components.treeItem.titleMenu')"
            >
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <circle cx="4" cy="8" r="1.5" fill="currentColor"/>
                <circle cx="8" cy="8" r="1.5" fill="currentColor"/>
                <circle cx="12" cy="8" r="1.5" fill="currentColor"/>
              </svg>
            </button>
          </div>
        </div>
        <!-- Bearbeiten-Button für User -->
        <button v-else @click.stop="handleEdit" class="edit-button" :title="t('common.edit')">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path
              d="M11.3333 2.00001C11.5084 1.8249 11.7163 1.68601 11.9444 1.59124C12.1726 1.49648 12.4163 1.44775 12.6625 1.44775C12.9087 1.44775 13.1524 1.49648 13.3806 1.59124C13.6087 1.68601 13.8166 1.8249 13.9917 2.00001C14.1668 2.17512 14.3057 2.38301 14.4005 2.61118C14.4952 2.83935 14.544 3.08306 14.544 3.32918C14.544 3.5753 14.4952 3.81901 14.4005 4.04718C14.3057 4.27535 14.1668 4.48324 13.9917 4.65835L5.32499 13.325L2 14L2.67499 10.675L11.3333 2.00001Z"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="showMenu && isOrgGroup"
        class="menu-dropdown menu-dropdown--teleported"
        :data-tree-menu-id="item.id"
        :style="menuDropdownStyle"
        @click.stop
      >
        <button @click="handleShowDetails" class="menu-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M8 8C10.2091 8 12 6.20914 12 4C12 1.79086 10.2091 0 8 0C5.79086 0 4 1.79086 4 4C4 6.20914 5.79086 8 8 8Z" fill="currentColor"/>
            <path d="M0 14C0 11.2386 2.23858 9 5 9H11C13.7614 9 16 11.2386 16 14V16H0V14Z" fill="currentColor"/>
          </svg>
          <span>{{ t('components.treeItem.showDetails') }}</span>
        </button>
        <button @click="handleAddDepartment" class="menu-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M8 4V12M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span>{{ t('components.treeItem.addDepartment') }}</span>
        </button>
        <button v-if="allowAddGrossanlass" @click="handleAddGrossanlass" class="menu-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M8 4V12M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span>{{ t('components.treeItem.addGrossanlass') }}</span>
        </button>
      </div>
      <div
        v-if="showMenu && isDeptGroup"
        class="menu-dropdown menu-dropdown--teleported"
        :data-tree-menu-id="item.id"
        :style="menuDropdownStyle"
        @click.stop
      >
        <button @click="handleShowDepartmentDetails" class="menu-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M8 3C4.5 3 1.5 6 1.5 8C1.5 10 4.5 13 8 13C11.5 13 14.5 10 14.5 8C14.5 6 11.5 3 8 3Z" stroke="currentColor" stroke-width="1.5"/>
            <circle cx="8" cy="8" r="2" fill="currentColor"/>
          </svg>
          <span>{{ t('components.treeItem.showDetails') }}</span>
        </button>
        <button @click="handleAddDepartment" class="menu-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M8 4V12M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span>{{ t('components.treeItem.addSubDepartment') }}</span>
        </button>
        <button v-if="allowAddGrossanlass" @click="handleAddGrossanlass" class="menu-item">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M8 4V12M4 8H12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span>{{ t('components.treeItem.addGrossanlass') }}</span>
        </button>
      </div>
    </Teleport>

    <!-- Children (wenn expanded) -->
    <div v-if="hasChildren && isExpanded" class="tree-children">
      <TreeItem
        v-for="child in item.children"
        :key="child.id"
        :item="child"
        :level="level + 1"
        :selected-items="selectedItems"
        :expanded-items="expandedItems"
        @toggle-select="(id, checked) => $emit('toggle-select', id, checked)"
        @toggle-expand="(id) => $emit('toggle-expand', id)"
        @edit="(item) => $emit('edit', item)"
        @show-users="(item) => $emit('show-users', item)"
        @show-details="(item) => $emit('show-details', item)"
        @show-department-details="(item) => $emit('show-department-details', item)"
        @add-department="(item) => $emit('add-department', item)"
        @add-grossanlass="(item) => $emit('add-grossanlass', item)"
        @manage-users="(item) => $emit('manage-users', item)"
        :allow-add-grossanlass="allowAddGrossanlass"
        :allow-manage-users="allowManageUsers"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { TreeItemData } from './TreeList.vue'

const { t } = useI18n()

interface Props {
  item: TreeItemData
  level: number
  selectedItems: string[]
  expandedItems: string[]
  allowAddGrossanlass?: boolean
  allowManageUsers?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  allowAddGrossanlass: false,
  allowManageUsers: false,
})

const emit = defineEmits<{
  'toggle-select': [itemId: string, checked: boolean]
  'toggle-expand': [itemId: string]
  'edit': [item: TreeItemData]
  'show-users': [item: TreeItemData]
  'show-details': [item: TreeItemData]
  'show-department-details': [item: TreeItemData]
  'add-department': [item: TreeItemData]
  'add-grossanlass': [item: TreeItemData]
  'manage-users': [item: TreeItemData]
}>()

const showMenu = ref(false)
const menuButtonRef = ref<HTMLButtonElement | null>(null)
const menuDropdownStyle = ref<Record<string, string>>({})
let contentClickTimer: ReturnType<typeof setTimeout> | null = null

const isOrgGroup = computed(
  () => props.item.type === 'group' && props.item.id.startsWith('org-'),
)
const isDeptGroup = computed(
  () => props.item.type === 'group' && props.item.id.startsWith('dept-'),
)

type TreeNodeKind = 'organisation' | 'department' | 'grossanlass'

const nodeKind = computed((): TreeNodeKind => {
  const kind = props.item.data?.nodeKind
  if (kind === 'organisation' || kind === 'department' || kind === 'grossanlass') {
    return kind
  }
  if (props.item.id.startsWith('org-')) return 'organisation'
  if (props.item.data?.isGrossanlass === true) return 'grossanlass'
  return 'department'
})

const groupIconName = computed(() => {
  switch (nodeKind.value) {
    case 'organisation':
      return 'mdi-domain'
    case 'grossanlass':
      return 'mdi-tent'
    default:
      return 'mdi-sitemap'
  }
})

const groupIconTitle = computed(() => {
  switch (nodeKind.value) {
    case 'organisation':
      return t('components.treeItem.iconOrganisation')
    case 'grossanlass':
      return t('components.treeItem.iconGrossanlass')
    default:
      return t('components.treeItem.iconDepartment')
  }
})

const hasChildren = computed(() => {
  // Groups (Departments) haben immer einen Expand-Button, auch wenn Children noch nicht geladen
  if (props.item.type === 'group') {
    return true
  }
  // Items haben nur Children wenn sie existieren und nicht leer sind
  return props.item.children && props.item.children.length > 0
})

const isExpanded = computed(() => {
  return props.expandedItems.includes(props.item.id)
})

const isSelected = computed(() => {
  return props.selectedItems.includes(props.item.id)
})

function handleToggleSelect(event: Event) {
  const target = event.target as HTMLInputElement
  emit('toggle-select', props.item.id, target.checked)
}

function handleToggleExpand() {
  emit('toggle-expand', props.item.id)
}

function handleEdit() {
  showMenu.value = false
  emit('edit', props.item)
}

function isEditableGroup(): boolean {
  return props.item.type === 'group' && (props.item.id.startsWith('org-') || props.item.id.startsWith('dept-'))
}

function handleContentClick() {
  if (props.item.type !== 'group') return
  if (contentClickTimer) {
    clearTimeout(contentClickTimer)
  }
  // Verzögert ausführen, damit Doppelklick den Single-Click überschreiben kann.
  contentClickTimer = setTimeout(() => {
    handleToggleExpand()
    contentClickTimer = null
  }, 220)
}

function handleContentDoubleClick() {
  if (contentClickTimer) {
    clearTimeout(contentClickTimer)
    contentClickTimer = null
  }
  if (!isEditableGroup()) return
  handleEdit()
}

function updateMenuDropdownPosition() {
  const button = menuButtonRef.value
  if (!button) return
  const rect = button.getBoundingClientRect()
  const menuWidth = 220
  let left = rect.right - menuWidth
  left = Math.max(8, Math.min(left, window.innerWidth - menuWidth - 8))
  menuDropdownStyle.value = {
    position: 'fixed',
    top: `${rect.bottom + 4}px`,
    left: `${left}px`,
    zIndex: '5000',
    minWidth: `${menuWidth}px`,
  }
}

function closeMenuOnScroll() {
  if (showMenu.value) showMenu.value = false
}

function toggleMenu(event: MouseEvent) {
  event.stopPropagation()
  showMenu.value = !showMenu.value
  if (showMenu.value) {
    void nextTick(() => updateMenuDropdownPosition())
  }
}

watch(showMenu, (open) => {
  if (open) {
    void nextTick(() => updateMenuDropdownPosition())
    window.addEventListener('scroll', closeMenuOnScroll, true)
    window.addEventListener('resize', closeMenuOnScroll)
  } else {
    window.removeEventListener('scroll', closeMenuOnScroll, true)
    window.removeEventListener('resize', closeMenuOnScroll)
  }
})

function handleShowUsers() {
  showMenu.value = false
  emit('show-users', props.item)
}

function handleShowDetails() {
  showMenu.value = false
  emit('show-details', props.item)
}

function handleAddDepartment() {
  showMenu.value = false
  emit('add-department', props.item)
}

function handleAddGrossanlass() {
  showMenu.value = false
  emit('add-grossanlass', props.item)
}

function handleManageUsers() {
  showMenu.value = false
  emit('manage-users', props.item)
}

function handleShowDepartmentDetails() {
  showMenu.value = false
  emit('show-department-details', props.item)
}

// Schließe Menü beim Klicken außerhalb
function handleClickOutside(event: MouseEvent) {
  if (!showMenu.value) return

  const target = event.target as HTMLElement
  if (menuButtonRef.value?.contains(target)) return
  if (target.closest(`[data-tree-menu-id="${props.item.id}"]`)) return

  showMenu.value = false
}

onMounted(() => {
  // Verwende nextTick um sicherzustellen, dass das Event nach dem Rendering registriert wird
  setTimeout(() => {
    document.addEventListener('click', handleClickOutside, true)
  }, 100)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside, true)
  window.removeEventListener('scroll', closeMenuOnScroll, true)
  window.removeEventListener('resize', closeMenuOnScroll)
  if (contentClickTimer) {
    clearTimeout(contentClickTimer)
  }
})
</script>

<style scoped>
.tree-item {
  border-bottom: 1px solid #f3f4f6;
}

.tree-item:last-child {
  border-bottom: none;
}

.tree-item.is-selected {
  background: #eff6ff;
}

.tree-row {
  display: flex;
  align-items: center;
  min-height: 40px;
  transition: background-color 0.2s;
}

.tree-row:hover {
  background: #f9fafb;
}

.tree-item.is-selected .tree-row:hover {
  background: #dbeafe;
}

.tree-cell {
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
  min-width: 0;
}

.actions-cell {
  width: 156px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.actions-group {
  display: flex;
  align-items: center;
  gap: 4px;
}

.item-content {
  display: flex;
  align-items: center;
  gap: 8px;
}

.group-content-clickable {
  cursor: pointer;
}

.tree-node-icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 6px;
  flex-shrink: 0;
}

.tree-node-icon-wrap--organisation {
  background: #dbeafe;
  color: #1d4ed8;
}

.tree-node-icon-wrap--department {
  background: #d1fae5;
  color: #047857;
}

.tree-node-icon-wrap--grossanlass {
  background: #ffedd5;
  color: #c2410c;
}

.tree-node-icon {
  flex-shrink: 0;
}

.tree-node-badge {
  flex-shrink: 0;
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.tree-node-badge--department {
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #a7f3d0;
}

.tree-node-badge--grossanlass {
  background: #fff7ed;
  color: #c2410c;
  border: 1px solid #fdba74;
}

.item-indent {
  color: #d1d5db;
  font-size: 14px;
  width: 16px;
  display: inline-block;
  text-align: center;
}

.item-label {
  color: #374151;
  font-size: 14px;
}

.item-label.is-bold {
  font-weight: 600;
  color: #1f2937;
}

.tree-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: #3b82f6;
}

.expand-button {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  transition: transform 0.2s, color 0.2s;
}

.expand-button:hover {
  color: #374151;
}

.expand-button.expanded {
  transform: rotate(90deg);
}

.tree-children {
  background: #fafafa;
}

.edit-button,
.users-button {
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  border-radius: 4px;
  transition: all 0.2s;
}

.edit-button:hover,
.users-button:hover {
  background: #f3f4f6;
  color: #3b82f6;
}

.users-button:hover {
  color: #059669;
}

.menu-container {
  position: relative;
}

.menu-button {
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  border-radius: 4px;
  transition: all 0.2s;
}

.menu-button:hover {
  background: #f3f4f6;
  color: #3b82f6;
}

.menu-dropdown {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.menu-dropdown--teleported {
  position: fixed;
}

.menu-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: none;
  border: none;
  cursor: pointer;
  color: #374151;
  font-size: 14px;
  text-align: left;
  transition: background-color 0.2s;
}

.menu-item:hover {
  background: #f3f4f6;
}

.menu-item svg {
  flex-shrink: 0;
  color: #6b7280;
}

.menu-item:hover svg {
  color: #3b82f6;
}
</style>
