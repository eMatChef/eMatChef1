<template>
  <button type="button" class="detail-th-sort" :title="title" @click="$emit('toggle', sortKey)">
    <span>{{ label }}</span>
    <span class="detail-th-sort-arrows" aria-hidden="true">
      <span class="detail-sort-chev" :class="{ active: activeKey === sortKey && direction === 'asc' }">▲</span>
      <span class="detail-sort-chev" :class="{ active: activeKey === sortKey && direction === 'desc' }">▼</span>
    </span>
  </button>
</template>

<script setup lang="ts">
defineOptions({ name: 'SortHeaderButton' })

defineProps<{
  label: string
  title: string
  sortKey: string
  activeKey: string | null
  direction: 'asc' | 'desc'
}>()

defineEmits<{
  toggle: [key: string]
}>()
</script>
