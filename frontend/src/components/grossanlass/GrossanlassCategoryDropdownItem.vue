<template>
  <v-list-item
    v-bind="listItemProps"
    class="ga-cat-dd-item"
    :class="{ 'ga-cat-dd-item--child': isChild }"
  >
    <template #title>
      <span class="ga-cat-dd-item__label">
        <span v-if="isChild" class="ga-cat-dd-item__mark" aria-hidden="true">↳</span>
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

const isChild = computed(() => Number(source.value.depth ?? 0) > 0)

const label = computed(() => {
  const fromSource = source.value.name
  if (fromSource) return fromSource
  const title = String(source.value.title || props.item.title || '')
  const sep = title.lastIndexOf(' / ')
  return sep >= 0 ? title.slice(sep + 3) : title
})
</script>

<style>
.ga-cat-dd-item--child {
  padding-inline-start: 2.75rem !important;
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
