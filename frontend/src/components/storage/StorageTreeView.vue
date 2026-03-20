<template>
  <StorageOverviewTab
    :department-id="departmentId"
    :material-id="materialId"
    :readonly="readonly"
    :allow-move-actions="allowMoveActions"
    :allow-open-actions="allowOpenActions"
    :container-batch-id="containerBatchId"
    :search-query="searchQuery"
  />
</template>

<script setup lang="ts">
import StorageOverviewTab from '@/components/material/StorageOverviewTab.vue'

withDefaults(defineProps<{
  departmentId: string
  materialId?: string
  readonly?: boolean
  allowMoveActions?: boolean
  allowOpenActions?: boolean
  containerBatchId?: string
  searchQuery?: string
}>(), {
  materialId: '',
  readonly: false,
  allowMoveActions: false,
  allowOpenActions: false,
  containerBatchId: '',
  searchQuery: '',
})
</script>
