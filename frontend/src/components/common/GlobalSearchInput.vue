<template>
  <div class="global-search" :class="{ expanded: isExpanded, inline: mode === 'inline' }" ref="rootRef">
    <!-- Icon-Modus: Nur Lupensymbol, expandiert bei Klick -->
    <button
      v-if="mode === 'icon' && !isExpanded"
      type="button"
      class="search-icon-btn"
      title="Suchen"
      @click="expand"
      aria-label="Suchen"
    >
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <circle cx="11" cy="11" r="8" stroke-width="2"/>
        <path d="m21 21-4.35-4.35" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </button>

    <!-- Suchfeld (expandiert oder inline) -->
    <div v-else class="search-field-wrap">
      <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <circle cx="11" cy="11" r="8" stroke-width="2"/>
        <path d="m21 21-4.35-4.35" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <input
        ref="inputRef"
        v-model="query"
        type="text"
        :placeholder="placeholder"
        class="search-input"
        :class="{ 'has-clear': mode === 'inline' && query }"
        @input="onInput"
        @focus="showSuggestionsDropdown = true"
        @keydown="onKeydown"
      />
      <button
        v-if="(mode === 'icon') || (mode === 'inline' && query)"
        type="button"
        class="search-close-btn"
        :aria-label="mode === 'icon' ? 'Suche schließen' : 'Suche löschen'"
        @click="mode === 'icon' ? collapse() : (query = '')"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 6L6 18M6 6l12 12"/>
        </svg>
      </button>
      <!-- Dropdown: bis zu 4 Vorschläge -->
      <Transition name="dropdown-fade">
        <div
          v-if="showSuggestionsDropdown && (suggestions.length > 0 || isSuggestionsLoading)"
          class="suggestions-dropdown"
        >
          <div v-if="isSuggestionsLoading" class="suggestions-loading">Suche...</div>
          <div v-else class="suggestions-list">
            <button
              v-for="s in suggestions"
              :key="`${s.type}-${s.id}`"
              type="button"
              class="suggestion-item"
              @click="selectSuggestion(s)"
            >
              <span class="suggestion-label">{{ s.label }}</span>
              <span class="suggestion-type">{{ typeLabel(s.type) }}</span>
            </button>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  useSearchNavigation,
  parseSearchQuery,
  fetchSearchSuggestions,
  type SearchTargetType,
  type SearchSuggestion,
} from '@/composables/useSearchNavigation'

const props = withDefaults(
  defineProps<{
    /** icon = expandierbar (Header), inline = immer sichtbar (in Views) */
    mode?: 'icon' | 'inline'
    /** Department-ID für Navigation */
    departmentId?: string
    /** Standard-Suchtyp ohne Prefix (z.B. in Material-View: material) */
    defaultType?: SearchTargetType
    /** Placeholder-Text */
    placeholder?: string
    /** Initialer Wert (z.B. aus route.query.q) */
    modelValue?: string
  }>(),
  {
    mode: 'icon',
    placeholder: 'Suchen... (material:, aktivität:, reparatur:)',
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'search', parsed: { type: SearchTargetType; term: string }): void
}>()

const router = useRouter()
const { executeSearch } = useSearchNavigation()

const query = ref(props.modelValue ?? '')
const isExpanded = ref(props.mode === 'inline')
const inputRef = ref<HTMLInputElement | null>(null)
const rootRef = ref<HTMLElement | null>(null)
const suggestions = ref<SearchSuggestion[]>([])
const isSuggestionsLoading = ref(false)
const showSuggestionsDropdown = ref(false)

const effectiveDepartmentId = computed(() => props.departmentId ?? '')

let debounceTimer: ReturnType<typeof setTimeout> | null = null

watch(
  () => props.modelValue,
  (val) => {
    if (val !== undefined && val !== query.value) {
      query.value = val
    }
  },
  { immediate: true }
)

watch(query, (val) => emit('update:modelValue', val))

function typeLabel(type: SearchTargetType): string {
  const labels: Record<SearchTargetType, string> = {
    material: 'Material',
    activity: 'Aktivität',
    reparatur: 'Reparatur',
  }
  return labels[type] ?? type
}

function onInput() {
  if (debounceTimer) clearTimeout(debounceTimer)
  const raw = query.value.trim()
  if (raw.length < 2) {
    suggestions.value = []
    return
  }
  debounceTimer = setTimeout(() => loadSuggestions(), 250)
}

async function loadSuggestions() {
  if (!effectiveDepartmentId.value) return
  isSuggestionsLoading.value = true
  suggestions.value = []
  try {
    const results = await fetchSearchSuggestions(
      query.value,
      effectiveDepartmentId.value,
      props.defaultType
    )
    suggestions.value = results
  } catch {
    suggestions.value = []
  } finally {
    isSuggestionsLoading.value = false
  }
}

function selectSuggestion(s: SearchSuggestion) {
  router.push(s.path)
  showSuggestionsDropdown.value = false
  suggestions.value = []
  if (props.mode === 'icon') collapse()
}

function handleClickOutside(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (rootRef.value && !rootRef.value.contains(target)) {
    showSuggestionsDropdown.value = false
  }
}

function expand() {
  isExpanded.value = true
  nextTick(() => inputRef.value?.focus())
}

function collapse() {
  isExpanded.value = false
  query.value = ''
  suggestions.value = []
  showSuggestionsDropdown.value = false
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    showSuggestionsDropdown.value = false
    if (props.mode === 'icon') {
      collapse()
    } else {
      ;(e.target as HTMLInputElement).blur()
    }
    return
  }
  if (e.key === 'Enter') {
    submitSearch()
  }
}

function submitSearch() {
  const raw = query.value.trim()
  if (!raw) return

  if (effectiveDepartmentId.value) {
    const ok = executeSearch(raw, effectiveDepartmentId.value, props.defaultType)
    if (ok && props.mode === 'icon') {
      collapse()
    }
  } else {
    const parsed = parseSearchQuery(raw, props.defaultType ?? 'material')
    if (parsed) {
      emit('search', { type: parsed.type, term: parsed.term })
    }
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  if (debounceTimer) clearTimeout(debounceTimer)
})

defineExpose({
  expand,
  collapse,
  focus: () => inputRef.value?.focus(),
})
</script>

<style scoped>
.global-search {
  display: flex;
  align-items: center;
}

.global-search.inline {
  width: 100%;
  min-width: 0;
}

.global-search.inline .search-field-wrap {
  display: flex;
  width: 100%;
  min-width: 200px;
  max-width: 500px;
}

.search-icon-btn {
  background: none;
  border: none;
  padding: 8px;
  cursor: pointer;
  color: #666;
  display: flex;
  align-items: center;
  justify-content: center;
}

.search-icon-btn:hover {
  color: #333;
}

.search-icon-btn .icon {
  width: 20px;
  height: 20px;
}

.search-field-wrap {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
  min-width: 280px;
  max-width: 500px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  width: 18px;
  height: 18px;
  color: #999;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 10px 36px 10px 40px;
  border-radius: 10px;
  font-size: 14px;
}

.search-close-btn {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  padding: 4px;
  cursor: pointer;
  color: #999;
  display: flex;
  align-items: center;
  justify-content: center;
}

.search-close-btn:hover {
  color: #333;
}

.suggestions-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  margin-top: 4px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  max-height: 240px;
  overflow-y: auto;
  z-index: 1000;
}

.suggestions-loading {
  padding: 12px 14px;
  color: #6b7280;
  font-size: 13px;
}

.suggestions-list {
  padding: 4px 0;
}

.suggestion-item {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 14px;
  border: none;
  background: none;
  text-align: left;
  font-size: 14px;
  cursor: pointer;
  color: #111827;
}

.suggestion-item:hover {
  background: #f3f4f6;
}

.suggestion-label {
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.suggestion-type {
  flex-shrink: 0;
  font-size: 11px;
  color: #6b7280;
  text-transform: uppercase;
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
