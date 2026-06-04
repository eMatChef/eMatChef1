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
        @keydown="onKeydown"
      >
        <Transition name="dropdown-fade">
          <div
            v-if="showSuggestionsDropdown && (suggestions.length > 0 || isSuggestionsLoading)"
            class="suggestions-dropdown"
          >
            <div v-if="isSuggestionsLoading" class="suggestions-loading">
              {{ t('components.globalSearch.loadingSuggestions') }}
            </div>
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
            <div v-if="showAllResultsLink" class="suggestions-footer">
              <button type="button" class="suggestion-show-all" @click="goToFullSearchPage">
                {{ t('components.globalSearch.showAllResults') }}
              </button>
            </div>
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
  </div>
</template>

<script setup lang="ts">
import SearchFieldInput from '@/components/common/SearchFieldInput.vue'
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
  }>(),
  {
    mode: 'icon',
    placeholder: undefined,
    searchAllTypes: false,
  }
)

const { t } = useI18n()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'search', parsed: { type: SearchTargetType; term: string }): void
}>()

const router = useRouter()
const authStore = useAuthStore()
const { executeSearch } = useSearchNavigation()

const headerEnabledTypes = computed(() => getSearchEnabledTypes(authStore))

const query = ref(props.modelValue ?? '')
const isExpanded = ref(props.mode === 'inline')
const searchFieldRef = ref<InstanceType<typeof SearchFieldInput> | null>(null)
const rootRef = ref<HTMLElement | null>(null)
const suggestions = ref<SearchSuggestion[]>([])
const isSuggestionsLoading = ref(false)
const showSuggestionsDropdown = ref(false)

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
  const keys: Record<SearchTargetType, string> = {
    material: 'common.material',
    activity: 'components.globalSearch.typeActivity',
    reparatur: 'components.globalSearch.typeRepair',
  }
  const key = keys[type]
  return key ? t(key) : type
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

function onFieldFocus() {
  showSuggestionsDropdown.value = true
}

async function loadSuggestions() {
  if (!effectiveDepartmentId.value) return
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

function handleClickOutside(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (rootRef.value && !rootRef.value.contains(target)) {
    showSuggestionsDropdown.value = false
  }
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
}

.global-search__field {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 4px;
  width: 100%;
  min-width: 280px;
  max-width: 500px;
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

.suggestions-footer {
  border-top: 1px solid #e5e7eb;
  padding: 6px 8px;
}

.suggestion-show-all {
  width: 100%;
  padding: 8px 10px;
  border: none;
  border-radius: 6px;
  background: var(--color-primary-muted-bg, #f0fdf4);
  color: var(--color-primary-dark, #047857);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  text-align: center;
}

.suggestion-show-all:hover {
  background: #dcfce7;
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
