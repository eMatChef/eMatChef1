<template>
  <div class="global-search" :class="{ expanded: isExpanded, inline: mode === 'inline' }" ref="rootRef">
    <button
      v-if="mode === 'icon' && !isExpanded"
      type="button"
      class="search-icon-btn"
      :title="t('common.search')"
      @click="expand"
      :aria-label="t('common.search')"
    >
      <v-icon icon="mdi-magnify" size="20" />
    </button>

    <div v-else class="global-search__field">
      <SearchFieldInput
        ref="searchFieldRef"
        v-model="query"
        :label="effectiveLabel"
        :clearable="mode === 'inline'"
        class="global-search__input"
        @input="onInput"
        @focus="onFieldFocus"
        @blur="onFieldBlur"
        @keydown="onKeydown"
      >
        <Transition v-if="!teleportDropdown" name="dropdown-fade">
          <div
            v-if="dropdownVisible"
            class="suggestions-dropdown"
            @mousedown="clearCloseTimer"
          >
            <SuggestionsPanel
              :suggestions="suggestions"
              :is-loading="isSuggestionsLoading"
              :show-empty="pickOnSelect && query.trim().length >= minSearchChars && !isSuggestionsLoading && suggestions.length === 0"
              :empty-text="pickEmptyText"
              :show-all-results-link="showAllResultsLink && !pickOnSelect"
              :show-type-label="!pickOnSelect || searchAllTypes"
              @select="selectSuggestion"
              @show-all="goToFullSearchPage"
            />
          </div>
        </Transition>
      </SearchFieldInput>
      <button
        v-if="mode === 'icon'"
        type="button"
        class="global-search__collapse"
        :aria-label="t('common.searchClose')"
        @click="collapse"
      >
        <v-icon icon="mdi-close" size="20" />
      </button>
    </div>

    <Teleport v-if="teleportDropdown" to="body">
      <Transition name="dropdown-fade">
        <div
          v-if="dropdownVisible"
          ref="dropdownRef"
          class="suggestions-dropdown suggestions-dropdown--teleported"
          :style="dropdownStyle"
          @mousedown="clearCloseTimer"
        >
          <SuggestionsPanel
            :suggestions="suggestions"
            :is-loading="isSuggestionsLoading"
            :show-empty="pickOnSelect && query.trim().length >= minSearchChars && !isSuggestionsLoading && suggestions.length === 0"
            :empty-text="pickEmptyText"
            :show-all-results-link="showAllResultsLink && !pickOnSelect"
            :show-type-label="!pickOnSelect || searchAllTypes"
            @select="selectSuggestion"
            @show-all="goToFullSearchPage"
          />
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import SearchFieldInput from '@/components/common/SearchFieldInput.vue'
import SuggestionsPanel from '@/components/common/GlobalSearchSuggestionsPanel.vue'
import { ref, watch, nextTick, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import {
  useSearchNavigation,
  parseSearchQuery,
  fetchSearchSuggestions,
  getSearchEnabledTypes,
  getGlobalSearchPageTarget,
  hasExplicitSearchPrefix,
  type SearchTargetType,
  type SearchSuggestion,
} from '@/composables/useSearchNavigation'

const props = withDefaults(
  defineProps<{
    mode?: 'icon' | 'inline'
    departmentId?: string
    defaultType?: SearchTargetType
    /** Header: ohne Prefix Material, Aktivität und Reparatur (je nach Rolle) */
    searchAllTypes?: boolean
    placeholder?: string
    modelValue?: string
    /** Auswahl-Modus: Vorschlag wählen statt navigieren (z. B. Material im Ticket-Dialog) */
    pickOnSelect?: boolean
    /** Dropdown per Teleport (z. B. in scrollbaren Dialogen) */
    teleportDropdown?: boolean
    /** Leertext im Pick-Modus (z. B. «Keine Treffer») */
    pickEmptyText?: string
  }>(),
  {
    mode: 'icon',
    placeholder: undefined,
    searchAllTypes: false,
    pickOnSelect: false,
    teleportDropdown: false,
    pickEmptyText: undefined,
  }
)

const DROPDOWN_MAX_HEIGHT = 260
const DROPDOWN_Z_INDEX = 10100
const PICK_CLOSE_DELAY_MS = 180

const { t } = useI18n()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'search', parsed: { type: SearchTargetType; term: string }): void
  (e: 'select', suggestion: SearchSuggestion): void
}>()

const router = useRouter()
const authStore = useAuthStore()
const { executeSearch } = useSearchNavigation()

const headerEnabledTypes = computed(() => getSearchEnabledTypes(authStore))

const query = ref(props.modelValue ?? '')
const isExpanded = ref(props.mode === 'inline')
const searchFieldRef = ref<InstanceType<typeof SearchFieldInput> | null>(null)
const rootRef = ref<HTMLElement | null>(null)
const dropdownRef = ref<HTMLElement | null>(null)
const suggestions = ref<SearchSuggestion[]>([])
const isSuggestionsLoading = ref(false)
const showSuggestionsDropdown = ref(false)
const dropdownStyle = ref<Record<string, string>>({})

let positionListenersBound = false
let closeTimer: ReturnType<typeof setTimeout> | null = null

const effectiveDepartmentId = computed(() => props.departmentId ?? '')
const effectiveLabel = computed(
  () => props.placeholder || t('components.globalSearch.placeholderDefault'),
)

const showAllResultsLink = computed(
  () =>
    props.searchAllTypes &&
    !!effectiveDepartmentId.value &&
    query.value.trim().length >= 2 &&
    (suggestions.value.length > 0 || !isSuggestionsLoading.value)
)

/** Zentrale Suche: mind. 2 Zeichen (fetchSearchSuggestions) */
const minSearchChars = 2

const pickEmptyText = computed(
  () => props.pickEmptyText ?? t('components.materialLookup.empty')
)

const dropdownVisible = computed(() => {
  if (!showSuggestionsDropdown.value) return false
  if (isSuggestionsLoading.value || suggestions.value.length > 0) return true
  return props.pickOnSelect && query.value.trim().length >= minSearchChars
})

let debounceTimer: ReturnType<typeof setTimeout> | null = null
let suggestionRequestToken = 0

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

function clearCloseTimer() {
  if (!closeTimer) return
  clearTimeout(closeTimer)
  closeTimer = null
}

function onInput() {
  if (debounceTimer) clearTimeout(debounceTimer)
  const raw = query.value.trim()
  if (raw.length < minSearchChars) {
    suggestions.value = []
    return
  }
  showSuggestionsDropdown.value = true
  if (props.teleportDropdown) {
    nextTick(() => syncDropdownPosition())
  }
  debounceTimer = setTimeout(() => loadSuggestions(), 250)
}

function onFieldFocus() {
  clearCloseTimer()
  showSuggestionsDropdown.value = true
  if (props.teleportDropdown) {
    nextTick(() => syncDropdownPosition())
  }
  if (query.value.trim().length >= minSearchChars) {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => loadSuggestions(), 0)
  }
}

function onFieldBlur() {
  if (!props.pickOnSelect && !props.teleportDropdown) return
  clearCloseTimer()
  closeTimer = setTimeout(() => {
    showSuggestionsDropdown.value = false
  }, PICK_CLOSE_DELAY_MS)
}

async function loadSuggestions() {
  if (!effectiveDepartmentId.value) return
  const raw = query.value.trim()
  if (raw.length < minSearchChars) return

  const requestToken = ++suggestionRequestToken
  isSuggestionsLoading.value = true
  suggestions.value = []
  try {
    const results = await fetchSearchSuggestions(
      query.value,
      effectiveDepartmentId.value,
      props.defaultType ?? 'material',
      {
        searchAllTypes: props.searchAllTypes,
        enabledTypes: props.searchAllTypes ? headerEnabledTypes.value : undefined,
      }
    )
    if (requestToken !== suggestionRequestToken) return
    suggestions.value = results
  } catch {
    if (requestToken === suggestionRequestToken) {
      suggestions.value = []
    }
  } finally {
    if (requestToken === suggestionRequestToken) {
      isSuggestionsLoading.value = false
      if (props.teleportDropdown) {
        await nextTick()
        syncDropdownPosition()
      }
    }
  }
}

function selectSuggestion(s: SearchSuggestion) {
  clearCloseTimer()
  if (props.pickOnSelect) {
    emit('select', s)
    query.value = ''
    suggestions.value = []
    showSuggestionsDropdown.value = false
    return
  }
  router.push(s.path)
  showSuggestionsDropdown.value = false
  suggestions.value = []
  if (props.mode === 'icon') collapse()
}

function goToFullSearchPage() {
  const raw = query.value.trim()
  if (!raw || !effectiveDepartmentId.value) return
  const parsed = parseSearchQuery(raw, props.defaultType ?? 'material')
  const term = parsed?.term ?? raw
  const target = getGlobalSearchPageTarget(
    effectiveDepartmentId.value,
    term,
    props.searchAllTypes && parsed && hasExplicitSearchPrefix(raw) ? parsed.type : undefined
  )
  router.push({ path: target.path, query: target.query })
  showSuggestionsDropdown.value = false
  suggestions.value = []
  if (props.mode === 'icon') collapse()
}

function getAnchorElement(): HTMLElement | null {
  const field = rootRef.value?.querySelector('.search-field')
  if (field instanceof HTMLElement) return field
  const input = searchFieldRef.value?.inputRef
  return input instanceof HTMLElement ? input : null
}

function syncDropdownPosition() {
  const el = getAnchorElement()
  if (!el) return

  const rect = el.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight
  const width = Math.min(rect.width, vw - 16)
  const left = Math.max(8, Math.min(rect.left, vw - width - 8))
  const spaceBelow = vh - rect.bottom - 8
  const spaceAbove = rect.top - 8
  const openBelow = spaceBelow >= 120 || spaceBelow >= spaceAbove
  const maxHeightPx = DROPDOWN_MAX_HEIGHT

  if (openBelow) {
    dropdownStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 4}px`,
      left: `${left}px`,
      width: `${width}px`,
      maxHeight: `${Math.min(maxHeightPx, Math.max(spaceBelow - 4, 80))}px`,
      zIndex: String(DROPDOWN_Z_INDEX),
    }
    return
  }

  dropdownStyle.value = {
    position: 'fixed',
    left: `${left}px`,
    width: `${width}px`,
    bottom: `${vh - rect.top + 4}px`,
    maxHeight: `${Math.min(maxHeightPx, Math.max(spaceAbove - 4, 80))}px`,
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

watch(
  () => [dropdownVisible.value, props.teleportDropdown] as const,
  async ([visible, teleported]) => {
    if (!teleported || !visible) {
      unbindPositionListeners()
      return
    }
    await nextTick()
    syncDropdownPosition()
    bindPositionListeners()
  }
)

function handleClickOutside(e: MouseEvent) {
  if (props.pickOnSelect && props.teleportDropdown) return
  const target = e.target as HTMLElement
  if (rootRef.value?.contains(target)) return
  if (dropdownRef.value?.contains(target)) return
  showSuggestionsDropdown.value = false
}

function expand() {
  isExpanded.value = true
  nextTick(() => searchFieldRef.value?.focus())
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
      searchFieldRef.value?.blur()
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

  if (props.pickOnSelect) {
    if (suggestions.value.length > 0) {
      selectSuggestion(suggestions.value[0])
    }
    return
  }

  if (effectiveDepartmentId.value) {
    const ok = executeSearch(raw, effectiveDepartmentId.value, props.defaultType ?? 'material', {
      searchAllTypes: props.searchAllTypes,
    })
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
  unbindPositionListeners()
  clearCloseTimer()
  if (debounceTimer) clearTimeout(debounceTimer)
})

defineExpose({
  expand,
  collapse,
  focus: () => searchFieldRef.value?.focus(),
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
  overflow: visible;
}

.global-search__field {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 4px;
  width: 100%;
  min-width: 280px;
  max-width: 500px;
  overflow: visible;
}

.global-search.inline .global-search__field {
  max-width: none;
}

.global-search__input {
  flex: 1;
  min-width: 0;
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
  transition: color 0.15s ease;
}

.search-icon-btn:hover {
  color: var(--color-primary, #10b981);
}

.search-icon-btn .icon {
  width: 20px;
  height: 20px;
}

.global-search__collapse {
  flex-shrink: 0;
  align-self: center;
  margin-top: 10px;
  background: none;
  border: none;
  padding: 8px;
  cursor: pointer;
  color: #999;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.15s ease;
}

.global-search__collapse:hover {
  color: var(--color-primary, #10b981);
}

.suggestions-dropdown {
  position: absolute;
  top: calc(100% - 6px);
  left: 0;
  right: 0;
  margin-top: 4px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  max-height: 240px;
  overflow-y: auto;
  z-index: 2500;
}

.suggestions-dropdown--teleported {
  position: fixed;
  right: auto;
  margin-top: 0;
  overflow-y: auto;
  pointer-events: auto;
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
