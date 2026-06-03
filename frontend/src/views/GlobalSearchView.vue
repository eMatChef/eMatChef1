<template>
  <PageShell class="global-search-view">
    <template #title>{{ t('components.globalSearch.pageTitle') }}</template>
    <template #subtitle>
      <span v-if="searchTerm">{{ t('components.globalSearch.subtitleWithQuery', { q: searchTerm }) }}</span>
      <span v-else>{{ t('components.globalSearch.subtitleEmpty') }}</span>
    </template>

    <div class="global-search-view__toolbar">
      <GlobalSearchInput
        mode="inline"
        search-all-types
        :department-id="departmentId"
        v-model="localQuery"
        :placeholder="t('components.globalSearch.placeholderDefault')"
      />
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="list"
      :message="t('components.globalSearch.loadingResults')"
    />

    <EEmptyState
      v-else-if="!searchTerm || searchTerm.length < minChars"
      variant="search"
      :title="t('components.globalSearch.hintTitle')"
      :description="t('components.globalSearch.hintDescription')"
    />

    <EEmptyState
      v-else-if="totalCount === 0"
      variant="search"
      :title="t('components.globalSearch.noResultsTitle')"
      :description="t('components.globalSearch.noResultsDescription', { q: searchTerm })"
    />

    <div v-else class="global-search-sections">
      <section
        v-for="section in visibleSections"
        :key="section.type"
        class="global-search-section"
      >
        <h2 class="global-search-section__title">
          {{ sectionTitle(section.type) }}
          <span class="global-search-section__count">({{ section.items.length }})</span>
        </h2>
        <div class="global-search-section__list" role="list">
          <router-link
            v-for="item in section.items"
            :key="`${section.type}-${item.id}`"
            :to="item.path"
            class="global-search-row"
            role="listitem"
          >
            <span class="global-search-row__label">{{ item.label }}</span>
            <span class="global-search-row__type">{{ sectionTitle(section.type) }}</span>
          </router-link>
        </div>
      </section>
    </div>
  </PageShell>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import PageShell from '@/components/layout/PageShell.vue'
import GlobalSearchInput from '@/components/common/GlobalSearchInput.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import {
  fetchGlobalSearchResults,
  getSearchEnabledTypes,
  getGlobalSearchPageTarget,
  hasExplicitSearchPrefix,
  parseSearchQuery,
  type SearchTargetType,
  type SearchSuggestion,
} from '@/composables/useSearchNavigation'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t } = useI18n()

const minChars = 2
const localQuery = ref('')
const isLoading = ref(false)
const sections = ref<{ type: SearchTargetType; items: SearchSuggestion[] }[]>([])

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)

const searchTerm = computed(() => {
  const q = route.query.q
  return typeof q === 'string' ? q.trim() : ''
})

const filterType = computed((): SearchTargetType | null => {
  const raw = route.query.type
  if (raw === 'material' || raw === 'activity' || raw === 'reparatur') return raw
  return null
})

const enabledTypes = computed(() => getSearchEnabledTypes(authStore))

const visibleSections = computed(() =>
  sections.value.filter((s) => s.items.length > 0),
)

const totalCount = computed(() =>
  visibleSections.value.reduce((sum, s) => sum + s.items.length, 0),
)

function sectionTitle(type: SearchTargetType): string {
  const keys: Record<SearchTargetType, string> = {
    material: 'common.material',
    activity: 'components.globalSearch.typeActivity',
    reparatur: 'components.globalSearch.typeRepair',
  }
  const key = keys[type]
  return key ? t(key) : type
}

async function loadResults() {
  const term = searchTerm.value
  if (!departmentId.value || term.length < minChars) {
    sections.value = []
    return
  }

  isLoading.value = true
  try {
    const types = filterType.value
      ? enabledTypes.value.includes(filterType.value)
        ? [filterType.value]
        : []
      : enabledTypes.value
    sections.value = await fetchGlobalSearchResults(term, departmentId.value, types)
  } catch {
    sections.value = []
  } finally {
    isLoading.value = false
  }
}

let queryPushTimer: ReturnType<typeof setTimeout> | null = null

watch(
  () => route.query.q,
  (q) => {
    const val = typeof q === 'string' ? q : ''
    if (val !== localQuery.value) localQuery.value = val
    void loadResults()
  },
  { immediate: true },
)

watch(localQuery, (val) => {
  const trimmed = val.trim()
  if (trimmed === searchTerm.value) return
  if (queryPushTimer) clearTimeout(queryPushTimer)
  queryPushTimer = setTimeout(() => {
    if (!departmentId.value) return
    if (trimmed.length < minChars) {
      router.replace({ path: `/${departmentId.value}/search`, query: {} })
      return
    }
    const parsed = parseSearchQuery(trimmed, 'material')
    const target = getGlobalSearchPageTarget(
      departmentId.value,
      parsed?.term ?? trimmed,
      hasExplicitSearchPrefix(trimmed) ? parsed?.type : undefined,
    )
    router.replace({ path: target.path, query: target.query })
  }, 400)
})
</script>

<style scoped>
.global-search-view__toolbar {
  max-width: 520px;
  margin-bottom: 20px;
}

.global-search-sections {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.global-search-section__title {
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-muted, #64748b);
  margin: 0 0 8px;
}

.global-search-section__count {
  font-weight: 500;
}

.global-search-section__list {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
}

.global-search-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 16px;
  text-decoration: none;
  color: inherit;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
  transition: background 0.15s;
}

.global-search-row:last-child {
  border-bottom: none;
}

.global-search-row:hover {
  background: var(--color-primary-muted-bg, #f0fdf4);
}

.global-search-row__label {
  flex: 1;
  min-width: 0;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text, #1e293b);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.global-search-row__type {
  flex-shrink: 0;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  color: var(--color-text-muted, #64748b);
}
</style>
