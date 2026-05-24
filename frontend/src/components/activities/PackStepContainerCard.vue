<script setup lang="ts">
import type { ActivityPackContainer } from '@/api/activityContainers'
import PackConfirmedPackedContainerCard from '@/components/activities/PackConfirmedPackedContainerCard.vue'
import PackEventReturnContainerCard from '@/components/activities/PackEventReturnContainerCard.vue'
import type { PackContainerCardMode } from '@/components/activities/packStepUi'
import PackWarehouseIssueContainerCard from '@/components/activities/PackWarehouseIssueContainerCard.vue'

defineOptions({ name: 'PackStepContainerCard' })

defineProps<{
  container: ActivityPackContainer
  mode: PackContainerCardMode
  stageRightLabel?: string
  containerDomIdPrefix?: string
  showStorageLocation?: boolean
  useSubsections?: boolean
}>()
</script>

<template>
  <PackConfirmedPackedContainerCard
    v-if="mode === 'confirmed_packed_target'"
    :container="container"
  />
  <PackWarehouseIssueContainerCard
    v-else-if="mode === 'warehouse_issue' || mode === 'warehouse_issue_mirror'"
    :container="container"
    :stage-right-label="stageRightLabel"
    :container-dom-id-prefix="containerDomIdPrefix"
    :use-subsections="useSubsections ?? false"
    :show-storage-location="showStorageLocation"
  />
  <PackEventReturnContainerCard
    v-else
    :container="container"
  />
</template>
