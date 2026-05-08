<template>
  <StorageOverviewTab
    :department-id="departmentId"
    :material-id="materialId"
    :readonly="readonly"
    :allow-move-actions="allowMoveActions"
    :allow-open-actions="allowOpenActions"
    :open-material-without-batch-query="openMaterialWithoutBatchQuery"
    :embedded-detail-material-id="embeddedDetailMaterialId"
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
  openMaterialWithoutBatchQuery?: boolean
  /** Im Material-Detail: Kiste öffnen → Tab „Inhalt Kiste/Tasche“ derselben Ansicht */
  embeddedDetailMaterialId?: string
  containerBatchId?: string
  searchQuery?: string
}>(), {
  materialId: '',
  readonly: false,
  allowMoveActions: false,
  allowOpenActions: false,
  openMaterialWithoutBatchQuery: false,
  embeddedDetailMaterialId: '',
  containerBatchId: '',
  searchQuery: '',
})
</script>
