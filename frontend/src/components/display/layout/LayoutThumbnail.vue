<template>
  <div class="thumb" :style="{ aspectRatio }" aria-hidden="true">
    <div class="thumb__grid" :style="{ gridTemplateRows: `repeat(${view.rows}, minmax(0, 1fr))` }">
      <div
        v-for="item in view.items"
        :key="item.id"
        class="thumb__item"
        :class="`thumb__item--${kindOf(item)}`"
        :style="{ gridColumn: `${item.x + 1} / span ${item.w}`, gridRow: `${item.y + 1} / span ${item.h}` }"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { LayoutDocument, LayoutItem } from '@/api/displayLayout'
import { firstVisibleItems } from './layoutModel'

/** Schematische Vorschau eines Layouts (Zonen als Flächen), z. B. in der Vorlagenauswahl. */
const props = defineProps<{ layout: LayoutDocument; variantId?: string }>()

const variant = computed(() => props.layout.variants.find((v) => v.id === props.variantId) ?? props.layout.variants.find((v) => v.id === props.layout.default_variant_id) ?? props.layout.variants[0])
const view = computed(() => firstVisibleItems(props.layout, variant.value))
const aspectRatio = computed(() => (variant.value?.aspect_ratio ?? '16:9').replace(':', ' / '))

function kindOf(item: LayoutItem): string {
  const c = item.content
  if (c.type === 'widget') return c.widget.startsWith('ga_') ? 'ga' : 'general'
  return c.type
}
</script>

<style scoped>
.thumb {
  width: 100%;
  background: #0b1220;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid #1e293b;
}
.thumb__grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 2px;
  width: 100%;
  height: 100%;
  padding: 2px;
  box-sizing: border-box;
}
.thumb__item { border-radius: 2px; background: #334155; }
.thumb__item--ga { background: #2563eb; }
.thumb__item--rotation { background: #7c3aed; }
.thumb__item--page,
.thumb__item--group { background: #0f766e; }
</style>
