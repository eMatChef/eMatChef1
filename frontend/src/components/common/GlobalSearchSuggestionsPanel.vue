<template>
  <div v-if="isLoading" class="suggestions-loading">
    {{ t('components.globalSearch.loadingSuggestions') }}
  </div>
  <div v-else-if="showEmpty" class="suggestions-empty">
    {{ emptyText }}
  </div>
  <div v-else class="suggestions-list">
    <button
      v-for="s in suggestions"
      :key="`${s.type}-${s.id}`"
      type="button"
      class="suggestion-item"
      @mousedown.prevent="$emit('select', s)"
    >
      <span class="suggestion-label">{{ s.label }}</span>
      <span v-if="showTypeLabel" class="suggestion-type">{{ typeLabel(s.type) }}</span>
    </button>
  </div>
  <div v-if="showAllResultsLink" class="suggestions-footer">
    <button type="button" class="suggestion-show-all" @mousedown.prevent="$emit('show-all')">
      {{ t('components.globalSearch.showAllResults') }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { SearchSuggestion, SearchTargetType } from '@/composables/useSearchNavigation'

withDefaults(
  defineProps<{
    suggestions: SearchSuggestion[]
    isLoading: boolean
    showAllResultsLink: boolean
    showTypeLabel: boolean
    showEmpty?: boolean
    emptyText?: string
  }>(),
  {
    showEmpty: false,
    emptyText: '',
  },
)

defineEmits<{
  select: [suggestion: SearchSuggestion]
  'show-all': []
}>()

const { t } = useI18n()

function typeLabel(type: SearchTargetType): string {
  const keys: Record<SearchTargetType, string> = {
    material: 'common.material',
    activity: 'components.globalSearch.typeActivity',
    reparatur: 'components.globalSearch.typeRepair',
  }
  const key = keys[type]
  return key ? t(key) : type
}
</script>

<style scoped>
.suggestions-loading,
.suggestions-empty {
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
</style>
