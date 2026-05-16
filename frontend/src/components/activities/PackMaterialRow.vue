<script setup lang="ts">
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackMaterialMeta from '@/components/activities/PackMaterialMeta.vue'

defineOptions({ name: 'PackMaterialRow' })

withDefaults(
  defineProps<{
    item: ActivityPackItem
    showRack?: boolean
    showLinkedKiste?: boolean
  }>(),
  {
    showRack: false,
    showLinkedKiste: true,
  },
)
</script>

<template>
  <div class="pack-card">
    <div class="pack-card-main">
      <slot name="leading" />
      <div class="pack-card-info">
        <PackMaterialMeta :item="item" :show-rack="showRack" :show-linked-kiste="showLinkedKiste" />
        <slot name="detail" />
        <slot name="info-extra" />
      </div>
      <slot name="trailing" />
    </div>
  </div>
</template>
