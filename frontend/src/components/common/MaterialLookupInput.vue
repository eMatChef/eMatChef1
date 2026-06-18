<template>
  <div class="material-lookup" ref="rootRef">
    <div class="material-lookup-input-wrap">
      <svg class="material-lookup-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/>
        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input
        ref="inputRef"
        :value="internalValue"
        type="text"
        class="form-input material-lookup-input"
        :class="inputClass"
        :placeholder="placeholder || t('components.materialLookup.placeholderDefault')"
        @input="handleInput"
        @focus="handleFocus"
        @blur="handleBlur"
        @keydown="handleKeydown"
      />
      <span v-if="lookup.isLoading.value" class="material-lookup-spinner">⟳</span>
    </div>

    <Transition v-if="!teleportDropdown" name="dropdown-fade">
      <div
        v-if="lookup.isOpen.value && shouldShowDropdown"
        class="material-lookup-dropdown"
      >
        <slot
          name="results"
          :results="lookup.results.value"
          :is-loading="lookup.isLoading.value"
          :query="internalValue"
          :active-index="lookup.activeIndex.value"
          :set-active-index="lookup.setActiveIndex"
          :select-result="selectResult"
          :close-dropdown="lookup.closeNow"
        >
          <div v-if="lookup.isLoading.value" class="mat-dropdown-loading">{{ loadingText || t('components.materialLookup.loading') }}</div>
          <div v-else-if="lookup.results.value.length === 0" class="mat-dropdown-empty">{{ emptyText || t('components.materialLookup.empty') }}</div>
          <div v-else class="mat-dropdown-list">
            <button
              v-for="(item, index) in lookup.results.value"
              :key="String(resolveResultKey(item, index))"
              type="button"
              class="mat-dropdown-item material-lookup-item"
              :class="{ active: lookup.activeIndex.value === index }"
              @mousedown.prevent="selectResult(item)"
              @mouseenter="lookup.setActiveIndex(index)"
            >
              <span class="mat-dropdown-name">{{ resolveResultLabel(item) }}</span>
              <span v-if="resolveResultSecondary(item)" class="mat-dropdown-meta">{{ resolveResultSecondary(item) }}</span>
            </button>
          </div>
        </slot>
      </div>
    </Transition>

    <Teleport v-else to="body">
      <Transition name="dropdown-fade">
        <div
          v-if="lookup.isOpen.value && shouldShowDropdown"
          class="material-lookup-dropdown material-lookup-dropdown--teleported"
          :style="dropdownStyle"
        >
          <slot
            name="results"
            :results="lookup.results.value"
            :is-loading="lookup.isLoading.value"
            :query="internalValue"
            :active-index="lookup.activeIndex.value"
            :set-active-index="lookup.setActiveIndex"
            :select-result="selectResult"
            :close-dropdown="lookup.closeNow"
          >
            <div v-if="lookup.isLoading.value" class="mat-dropdown-loading">{{ loadingText || t('components.materialLookup.loading') }}</div>
            <div v-else-if="lookup.results.value.length === 0" class="mat-dropdown-empty">{{ emptyText || t('components.materialLookup.empty') }}</div>
            <div v-else class="mat-dropdown-list">
              <button
                v-for="(item, index) in lookup.results.value"
                :key="String(resolveResultKey(item, index))"
                type="button"
                class="mat-dropdown-item material-lookup-item"
                :class="{ active: lookup.activeIndex.value === index }"
                @mousedown.prevent="selectResult(item)"
                @mouseenter="lookup.setActiveIndex(index)"
              >
                <span class="mat-dropdown-name">{{ resolveResultLabel(item) }}</span>
                <span v-if="resolveResultSecondary(item)" class="mat-dropdown-meta">{{ resolveResultSecondary(item) }}</span>
              </button>
            </div>
          </slot>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useMaterialLookup, type UseMaterialLookupOptions } from '@/composables/useMaterialLookup'

const DROPDOWN_MAX_HEIGHT = 260
const DROPDOWN_Z_INDEX = 2500

const { t } = useI18n()

type GenericItem = Record<string, any>

const props = withDefaults(
  defineProps<{
    modelValue: string
    fetcher: UseMaterialLookupOptions<GenericItem>['fetcher']
    placeholder?: string
    minChars?: number
    debounceMs?: number
    maxSuggestions?: number
    loadingText?: string
    emptyText?: string
    showEmptyWhenNoResults?: boolean
    inputClass?: string
    teleportDropdown?: boolean
    dropdownMinWidth?: number
    dropdownMaxHeight?: string
    getResultKey?: (item: GenericItem, index: number) => string | number
    getResultLabel?: (item: GenericItem) => string
    getResultSecondary?: (item: GenericItem) => string
  }>(),
  {
    placeholder: undefined,
    minChars: 1,
    debounceMs: 220,
    maxSuggestions: 5,
    loadingText: undefined,
    emptyText: undefined,
    showEmptyWhenNoResults: true,
    inputClass: '',
    teleportDropdown: true,
    dropdownMinWidth: 280,
    dropdownMaxHeight: undefined,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
  select: [item: GenericItem]
  'results-change': [items: GenericItem[]]
}>()

const rootRef = ref<HTMLElement | null>(null)
const inputRef = ref<HTMLInputElement | null>(null)
const internalValue = ref(props.modelValue || '')
const dropdownStyle = ref<Record<string, string>>({})

let positionListenersBound = false

const lookup = useMaterialLookup<GenericItem>({
  fetcher: props.fetcher,
  minChars: props.minChars,
  debounceMs: props.debounceMs,
  maxSuggestions: props.maxSuggestions,
})

const shouldShowDropdown = computed(() => {
  if (lookup.isLoading.value) return true
  if (lookup.results.value.length > 0) return true
  if (!props.showEmptyWhenNoResults) return false
  return internalValue.value.trim().length >= props.minChars
})

const dropdownVisible = computed(() => lookup.isOpen.value && shouldShowDropdown.value)

watch(
  () => props.modelValue,
  (value) => {
    const normalized = value || ''
    if (normalized === internalValue.value) return
    internalValue.value = normalized
    if (normalized === '') {
      lookup.reset()
    } else {
      lookup.query.value = normalized
      void lookup.runSearch(normalized)
    }
  },
)

watch(
  () => lookup.results.value,
  (items) => {
    emit('results-change', items)
  },
)

watch(
  () => [dropdownVisible.value, lookup.results.value.length, internalValue.value] as const,
  async ([visible]) => {
    if (!props.teleportDropdown || !visible) {
      unbindPositionListeners()
      return
    }
    await nextTick()
    syncDropdownPosition()
    bindPositionListeners()
  },
)

function syncDropdownPosition() {
  const el = inputRef.value
  if (!el) return

  const rect = el.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight
  const width = Math.min(Math.max(rect.width, props.dropdownMinWidth), vw - 16)
  const left = Math.max(8, Math.min(rect.left, vw - width - 8))
  const spaceBelow = vh - rect.bottom - 8
  const spaceAbove = rect.top - 8
  const openBelow = spaceBelow >= 120 || spaceBelow >= spaceAbove
  const preferredMaxHeight = props.dropdownMaxHeight || `${DROPDOWN_MAX_HEIGHT}px`

  if (openBelow) {
    dropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 4}px`,
      left: `${left}px`,
      width: `${width}px`,
      maxHeight: `min(${preferredMaxHeight}, ${Math.max(spaceBelow - 4, 80)}px)`,
      zIndex: String(DROPDOWN_Z_INDEX),
    }
    return
  }

  const maxHeight = `min(${preferredMaxHeight}, ${Math.max(spaceAbove - 4, 80)}px)`
  dropdownStyle.value = {
    position: 'fixed',
    left: `${left}px`,
    width: `${width}px`,
    bottom: `${vh - rect.top + 4}px`,
    maxHeight: maxHeight,
    zIndex: String(DROPDOWN_Z_INDEX),
  }
}

function bindPositionListeners() {
  if (positionListenersBound) return
  positionListenersBound = true
  window.addEventListener('resize', syncDropdownPosition, { passive: true })
  window.addEventListener('scroll', syncDropdownPosition, { passive: true, capture: true })
}

function unbindPositionListeners() {
  if (!positionListenersBound) return
  positionListenersBound = false
  window.removeEventListener('resize', syncDropdownPosition)
  window.removeEventListener('scroll', syncDropdownPosition, true)
}

onUnmounted(unbindPositionListeners)

function resolveResultLabel(item: GenericItem) {
  if (props.getResultLabel) return props.getResultLabel(item)
  return item.name || item.label || item.serial_number || ''
}

function resolveResultSecondary(item: GenericItem) {
  if (props.getResultSecondary) return props.getResultSecondary(item)
  return ''
}

function resolveResultKey(item: GenericItem, index: number) {
  if (props.getResultKey) return props.getResultKey(item, index)
  return item.id || item.materialItemId || index
}

function handleInput(event: Event) {
  const value = (event.target as HTMLInputElement).value
  internalValue.value = value
  emit('update:modelValue', value)
  lookup.onInput(value)
}

function handleFocus() {
  lookup.onFocus()
}

function handleBlur() {
  lookup.onBlur()
}

function handleKeydown(event: KeyboardEvent) {
  const selected = lookup.onKeydown(event)
  if (selected) {
    selectResult(selected)
  }
}

function selectResult(item: GenericItem) {
  const nextValue = resolveResultLabel(item)
  internalValue.value = nextValue
  lookup.query.value = nextValue
  emit('update:modelValue', nextValue)
  emit('select', item)
  lookup.closeNow()
}

defineExpose({
  focus: () => inputRef.value?.focus(),
  blur: () => inputRef.value?.blur(),
  closeDropdown: () => lookup.closeNow(),
  resetLookup: () => lookup.reset(),
})
</script>

<style scoped>
.material-lookup {
  position: relative;
}

.material-lookup-input-wrap {
  position: relative;
}

.material-lookup-input {
  width: 100%;
  padding-left: 40px;
}

.material-lookup-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 18px;
  height: 18px;
  color: #9ca3af;
  pointer-events: none;
}

.material-lookup-spinner {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: #6b7280;
  font-size: 14px;
}

.material-lookup-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  z-index: 1200;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.14);
  max-height: 260px;
  overflow-y: auto;
}

.material-lookup-dropdown--teleported {
  right: auto;
}

.mat-dropdown-list {
  display: flex;
  flex-direction: column;
}

.mat-dropdown-loading,
.mat-dropdown-empty {
  padding: 12px 14px;
  color: #6b7280;
  font-size: 13px;
}

.mat-dropdown-item {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 14px;
  border: none;
  background: transparent;
  text-align: left;
  font-size: 14px;
  color: #111827;
  cursor: pointer;
}

.mat-dropdown-item:hover,
.mat-dropdown-item.active {
  background: #f0fdf4;
}

.mat-dropdown-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mat-dropdown-meta {
  flex-shrink: 0;
  font-size: 13px;
  color: #6b7280;
}

.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}

.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
