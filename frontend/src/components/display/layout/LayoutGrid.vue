<template>
  <div class="dl-grid" :style="{ gridTemplateRows: `repeat(${rows}, minmax(0, 1fr))` }">
    <div
      v-for="item in items"
      :key="item.id"
      class="dl-cell"
      :style="{ gridColumn: `${item.x + 1} / span ${item.w}`, gridRow: `${item.y + 1} / span ${item.h}` }"
    >
      <LayoutContent :content="item.content" :depth="depth" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { defineAsyncComponent } from 'vue'
import type { LayoutItem } from '@/api/displayLayout'

defineProps<{ rows: number; items: LayoutItem[]; depth: number }>()

const LayoutContent = defineAsyncComponent(() => import('./LayoutContent.vue'))
</script>

<style scoped>
.dl-grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: calc(var(--u) * 0.6);
  width: 100%;
  height: 100%;
  padding: calc(var(--u) * 0.6);
  box-sizing: border-box;
}
.dl-cell {
  min-width: 0;
  min-height: 0;
  overflow: hidden;
}
</style>
