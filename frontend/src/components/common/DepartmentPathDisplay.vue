<template>
  <span class="department-path-display" :class="{ 'department-path-display--compact': compact }">
    <template v-for="(segment, index) in segments" :key="segmentKey(segment, index)">
      <span v-if="index > 0" class="department-path-separator" aria-hidden="true"> › </span>
      <strong v-if="segment.current" class="department-path-current">{{ segment.name }}</strong>
      <span v-else class="department-path-segment">{{ segment.name }}</span>
    </template>
  </span>
</template>

<script setup lang="ts">
import type { DepartmentPathSegment } from '@/api/publicDepartments'

withDefaults(
  defineProps<{
    segments: DepartmentPathSegment[]
    compact?: boolean
  }>(),
  {
    compact: false,
  },
)

function segmentKey(segment: DepartmentPathSegment, index: number): string {
  return segment.id ?? `${segment.type}-${index}`
}
</script>

<style scoped>
.department-path-display {
  font-size: 14px;
  line-height: 1.4;
  color: #374151;
}

.department-path-display--compact {
  font-size: 12px;
  color: #6b7280;
}

.department-path-separator {
  color: #9ca3af;
  font-weight: 400;
}

.department-path-current {
  font-weight: 600;
  color: #166534;
}

.department-path-display--compact .department-path-current {
  color: #374151;
}
</style>
