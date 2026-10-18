<template>
  <div class="dl-grid" :style="{ gridTemplateRows: `repeat(${rows}, minmax(0, 1fr))`, gridTemplateColumns: `repeat(${columns ?? 12}, minmax(0, 1fr))` }">
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

defineProps<{ rows: number; columns?: number; items: LayoutItem[]; depth: number }>()

const LayoutContent = defineAsyncComponent(() => import('./LayoutContent.vue'))
</script>

<style scoped>
.dl-grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: calc(var(--u) * 1.2);
  width: 100%;
  height: 100%;
  padding: calc(var(--u) * 1.2) calc(var(--u) * 1.5);
  box-sizing: border-box;
}
.dl-cell .dl-grid {
  gap: calc(var(--u) * 0.6);
  padding: 0;
}
.dl-cell {
  min-width: 0;
  min-height: 0;
  overflow: hidden;
}
</style>
