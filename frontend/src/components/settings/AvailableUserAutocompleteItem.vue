<template>
  <v-list-item v-bind="itemProps" class="available-user-ac-item">
    <div class="available-user-ac-item__body">
      <div class="available-user-ac-item__name">
        <HighlightedText :text="user.name" :query="searchQuery" />
      </div>
      <div class="available-user-ac-item__meta">
        <span class="available-user-ac-item__field">
          <span class="available-user-ac-item__label">{{ firstNameLabel }}</span>
          <HighlightedText :text="firstNameDisplay" :query="searchQuery" />
        </span>
        <span class="available-user-ac-item__sep" aria-hidden="true">·</span>
        <span class="available-user-ac-item__field">
          <span class="available-user-ac-item__label">{{ emailLabel }}</span>
          <HighlightedText :text="user.email" :query="searchQuery" />
        </span>
        <span class="available-user-ac-item__sep" aria-hidden="true">·</span>
        <span class="available-user-ac-item__field available-user-ac-item__field--dept">
          <span class="available-user-ac-item__label">{{ departmentLabel }}</span>
          <HighlightedText :text="departmentDisplay" :query="searchQuery" />
        </span>
      </div>
    </div>
  </v-list-item>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { AvailableUser } from '@/api/departments'
import HighlightedText from '@/components/common/HighlightedText.vue'

const props = defineProps<{
  user: AvailableUser
  itemProps: Record<string, unknown>
  searchQuery: string
  firstNameLabel: string
  emailLabel: string
  departmentLabel: string
  noDepartmentText: string
}>()

const firstNameDisplay = computed(() => props.user.first_name?.trim() || '—')
const departmentDisplay = computed(
  () => props.user.departments_label?.trim() || props.user.primary_department_name?.trim() || props.noDepartmentText,
)
</script>

<style scoped>
.available-user-ac-item {
  min-height: 56px;
  padding-top: 8px !important;
  padding-bottom: 8px !important;
}

.available-user-ac-item__body {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
  width: 100%;
}

.available-user-ac-item__name {
  font-size: 14px;
  font-weight: 500;
  color: #1e293b;
  line-height: 1.3;
}

.available-user-ac-item__name :deep(.highlighted-text__match),
.available-user-ac-item__meta :deep(.highlighted-text__match) {
  font-weight: 700;
  color: var(--color-primary, #059669);
}

.available-user-ac-item__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px 6px;
  font-size: 12px;
  line-height: 1.35;
  color: #64748b;
}

.available-user-ac-item__field {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 4px;
  min-width: 0;
}

.available-user-ac-item__field--dept {
  flex: 1 1 120px;
}

.available-user-ac-item__label {
  font-weight: 600;
  color: #94a3b8;
  flex-shrink: 0;
}

.available-user-ac-item__sep {
  color: #cbd5e1;
  user-select: none;
}
</style>
