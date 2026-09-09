<template>
  <v-list-item
    v-bind="listItemProps"
    class="ga-cat-dd-item"
    :class="{ 'ga-cat-dd-item--nested': depth > 0 }"
    :style="{ paddingInlineStart: `${12 + depth * 16}px` }"
  >
    <template #title>
      <span class="ga-cat-dd-item__label">
        <span v-if="depth > 0" class="ga-cat-dd-item__mark" aria-hidden="true">↳</span>
        {{ label }}
      </span>
    </template>
  </v-list-item>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  itemProps: Record<string, unknown>
  item: { raw?: { depth?: number; name?: string; title?: string }; title?: string; depth?: number; name?: string }
}>()

const listItemProps = computed(() => {
  const { title: _title, ...rest } = props.itemProps
  return rest
})

const source = computed(() => {
  const raw = props.item.raw
  if (raw && (raw.depth != null || raw.name)) return raw
  return props.item
})

const depth = computed(() => Math.max(0, Number(source.value.depth ?? 0)))

const label = computed(() => {
  const fromSource = source.value.name
  if (fromSource) return fromSource
  const title = String(source.value.title || props.item.title || '')
  const sep = title.lastIndexOf(' / ')
  return sep >= 0 ? title.slice(sep + 3) : title
})
</script>

<style>
.ga-cat-dd-item--nested {
  min-height: 40px;
}
.ga-cat-dd-item__label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.ga-cat-dd-item__mark {
  color: #64748b;
  font-weight: 600;
}
</style>
