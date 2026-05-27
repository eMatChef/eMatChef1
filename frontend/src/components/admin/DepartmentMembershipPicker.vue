<template>
  <div class="dept-picker autocomplete-wrapper">
    <div v-if="selectedDepartment" class="selected-chip">
      <span class="chip-label">{{ formatDepartmentLabel(selectedDepartment) }}</span>
      <button type="button" class="chip-remove" :title="t('settings.adminUsers.removeDepartment')" @click="clearSelection">
        ×
      </button>
    </div>
    <template v-else>
      <input
        ref="inputRef"
        v-model="searchQuery"
        type="text"
        class="form-input dept-picker-input"
        :placeholder="placeholder || t('settings.adminUsers.deptSearchPlaceholder')"
        autocomplete="off"
        @focus="onFocus"
        @input="dropdownOpen = true"
        @blur="onBlur"
      />
      <Teleport to="body">
        <div
          v-if="dropdownOpen"
          class="autocomplete-dropdown autocomplete-dropdown--teleported"
          :style="dropdownStyle"
        >
          <div v-if="searchQuery.trim().length < minChars" class="autocomplete-hint">
            {{ t('settings.adminUsers.deptSearchMinChars', { n: minChars }) }}
          </div>
          <template v-else>
            <button
              v-for="dept in filteredDepartments"
              :key="dept.id"
              type="button"
              class="autocomplete-item"
              :class="{ 'is-child': !!dept.parent_id }"
              :style="dept.parent_id ? { paddingLeft: `${12 + getLevel(dept) * 14}px` } : undefined"
              @mousedown.prevent="selectDepartment(dept)"
            >
              <span class="ac-name">{{ dept.name }}</span>
              <span class="ac-meta">{{ formatDepartmentMeta(dept) }}</span>
            </button>
            <div v-if="filteredDepartments.length === 0" class="autocomplete-empty">
              {{ t('settings.adminUsers.deptSearchEmpty') }}
            </div>
          </template>
        </div>
      </Teleport>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Department } from '@/api/departments'

const DROPDOWN_Z_INDEX = 2500
const DROPDOWN_MAX_HEIGHT = 240

const props = withDefaults(
  defineProps<{
    modelValue: string
    departments: Department[]
    organisationNameById: Map<string, string>
    excludedDepartmentIds?: string[]
    placeholder?: string
    minChars?: number
    autoFocus?: boolean
  }>(),
  {
    excludedDepartmentIds: () => [],
    minChars: 1,
    autoFocus: false,
  }
)

const emit = defineEmits<{
  'update:modelValue': [id: string]
}>()

const { t } = useI18n()

const searchQuery = ref('')
const dropdownOpen = ref(false)
const inputRef = ref<HTMLInputElement | null>(null)
const dropdownStyle = ref<Record<string, string>>({})

let positionListenersBound = false

const deptById = computed(() => new Map(props.departments.map((d) => [d.id, d])))

const selectedDepartment = computed(() =>
  props.modelValue ? deptById.value.get(props.modelValue) : undefined
)

const selectableDepartments = computed(() => {
  const excluded = new Set(props.excludedDepartmentIds.filter((id) => id && id !== props.modelValue))
  return props.departments.filter((d) => !excluded.has(d.id))
})

const filteredDepartments = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (q.length < props.minChars) return []

  const matched = selectableDepartments.value.filter((d) => {
    const name = d.name.toLowerCase()
    const org = (props.organisationNameById.get(d.organisation_id) || '').toLowerCase()
    const path = getParentPath(d).toLowerCase()
    const full = formatDepartmentLabel(d).toLowerCase()
    return name.includes(q) || org.includes(q) || path.includes(q) || full.includes(q)
  })

  return matched
    .sort((a, b) => {
      const aName = a.name.toLowerCase()
      const bName = b.name.toLowerCase()
      const aStarts = aName.startsWith(q) ? 0 : 1
      const bStarts = bName.startsWith(q) ? 0 : 1
      if (aStarts !== bStarts) return aStarts - bStarts
      return formatDepartmentLabel(a).localeCompare(formatDepartmentLabel(b), 'de')
    })
    .slice(0, 40)
})

watch(
  () => props.autoFocus,
  (focus) => {
    if (focus) {
      setTimeout(() => {
        inputRef.value?.focus()
        dropdownOpen.value = true
      }, 50)
    }
  },
  { immediate: true }
)

watch(
  () => props.modelValue,
  (id) => {
    if (!id) searchQuery.value = ''
  }
)

watch(dropdownOpen, async (open) => {
  if (!open) {
    unbindPositionListeners()
    return
  }
  await nextTick()
  syncDropdownPosition()
  bindPositionListeners()
})

onUnmounted(() => {
  unbindPositionListeners()
})

function syncDropdownPosition() {
  const el = inputRef.value
  if (!el) return

  const rect = el.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight
  const width = Math.min(Math.max(rect.width, 280), vw - 16)
  const left = Math.max(8, Math.min(rect.left, vw - width - 8))
  const spaceBelow = vh - rect.bottom - 8
  const spaceAbove = rect.top - 8
  const openBelow = spaceBelow >= 120 || spaceBelow >= spaceAbove

  if (openBelow) {
    dropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 4}px`,
      left: `${left}px`,
      width: `${width}px`,
      maxHeight: `${Math.min(DROPDOWN_MAX_HEIGHT, Math.max(spaceBelow - 4, 80))}px`,
      zIndex: String(DROPDOWN_Z_INDEX),
    }
    return
  }

  dropdownStyle.value = {
    position: 'fixed',
    left: `${left}px`,
    width: `${width}px`,
    bottom: `${vh - rect.top + 4}px`,
    maxHeight: `${Math.min(DROPDOWN_MAX_HEIGHT, Math.max(spaceAbove - 4, 80))}px`,
    zIndex: String(DROPDOWN_Z_INDEX),
  }
}

function onPositionChange() {
  if (dropdownOpen.value) syncDropdownPosition()
}

function bindPositionListeners() {
  if (positionListenersBound) return
  positionListenersBound = true
  window.addEventListener('resize', onPositionChange)
  window.addEventListener('scroll', onPositionChange, true)
}

function unbindPositionListeners() {
  if (!positionListenersBound) return
  positionListenersBound = false
  window.removeEventListener('resize', onPositionChange)
  window.removeEventListener('scroll', onPositionChange, true)
}

function getLevel(d: Department): number {
  if (!d.parent_id) return 0
  const parent = deptById.value.get(d.parent_id)
  return parent ? 1 + getLevel(parent) : 0
}

function getParentPath(d: Department): string {
  if (!d.parent_id) return ''
  const parent = deptById.value.get(d.parent_id)
  if (!parent) return ''
  const prefix = getParentPath(parent)
  return prefix ? `${prefix} › ${parent.name}` : parent.name
}

function formatDepartmentMeta(d: Department): string {
  const org = props.organisationNameById.get(d.organisation_id) || ''
  const path = getParentPath(d)
  if (path) return `${org} › ${path}`
  return org
}

function formatDepartmentLabel(d: Department): string {
  const meta = formatDepartmentMeta(d)
  return meta ? `${meta} › ${d.name}` : d.name
}

function selectDepartment(dept: Department) {
  emit('update:modelValue', dept.id)
  searchQuery.value = ''
  dropdownOpen.value = false
}

function clearSelection() {
  emit('update:modelValue', '')
  searchQuery.value = ''
  setTimeout(() => inputRef.value?.focus(), 0)
}

function onFocus() {
  dropdownOpen.value = true
  void nextTick().then(syncDropdownPosition)
}

function onBlur() {
  window.setTimeout(() => {
    dropdownOpen.value = false
  }, 150)
}
</script>

<style scoped>
.dept-picker {
  position: relative;
  min-width: 0;
}

.dept-picker-input {
  width: 100%;
}

.selected-chip {
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  padding: 0.4rem 0.5rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #f8fafc;
  font-size: 0.85rem;
  line-height: 1.35;
}

.chip-label {
  flex: 1;
  min-width: 0;
  word-break: break-word;
}

.chip-remove {
  flex-shrink: 0;
  border: none;
  background: transparent;
  color: #64748b;
  cursor: pointer;
  font-size: 1.1rem;
  line-height: 1;
  padding: 0 0.15rem;
}

.chip-remove:hover {
  color: #b91c1c;
}

.autocomplete-dropdown--teleported {
  overflow-y: auto;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}

.autocomplete-item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.1rem;
  width: 100%;
  padding: 0.45rem 0.65rem;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
  font: inherit;
}

.autocomplete-item:hover {
  background: #f1f5f9;
}

.ac-name {
  font-weight: 600;
  font-size: 0.85rem;
  color: #0f172a;
}

.ac-meta {
  font-size: 0.72rem;
  color: #64748b;
}

.autocomplete-empty,
.autocomplete-hint {
  padding: 0.65rem 0.75rem;
  font-size: 0.8rem;
  color: #64748b;
}
</style>
