<script setup lang="ts">
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackMaterialMeta from '@/components/activities/PackMaterialMeta.vue'

defineOptions({ name: 'PackMaterialRow' })

withDefaults(
  defineProps<{
    item: ActivityPackItem
    showStorageLocation?: boolean
    showLinkedKiste?: boolean
    /** @deprecated — Regal/Fach hängen an showStorageLocation */
    showRack?: boolean
  }>(),
  {
    showStorageLocation: false,
    showLinkedKiste: false,
    showRack: true,
  },
)
</script>

<template>
  <div class="pack-card">
    <div class="pack-card-main">
      <slot name="leading" />
      <div class="pack-card-info">
        <PackMaterialMeta
          :item="item"
          :show-storage-location="showStorageLocation"
          :show-linked-kiste="showLinkedKiste"
          :show-rack="showRack"
        />
        <slot name="detail" />
        <slot name="info-extra" />
      </div>
      <slot name="trailing" />
    </div>
  </div>
</template>
