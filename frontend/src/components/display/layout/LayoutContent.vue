<template>
  <LayoutWidget v-if="content.type === 'widget'" :content="content" />
  <LayoutGrid v-else-if="content.type === 'group'" :rows="content.rows" :columns="content.columns" :items="content.items" :depth="depth + 1" />
  <LayoutGrid v-else-if="content.type === 'page' && page && depth < MAX_RENDER_DEPTH" :rows="page.rows" :columns="page.columns" :items="page.items" :depth="depth + 1" />
  <LayoutRotation v-else-if="content.type === 'rotation'" :content="content" :depth="depth + 1" />
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent, inject } from 'vue'
import type { LayoutContent } from '@/api/displayLayout'
import LayoutGrid from './LayoutGrid.vue'
import LayoutWidget from './LayoutWidget.vue'
import { LAYOUT_PAGES_KEY, MAX_RENDER_DEPTH } from './layoutContext'

const props = defineProps<{ content: LayoutContent; depth: number }>()

// Rekursive Komponenten (Rotation enthält wieder Inhalte) werden asynchron aufgelöst.
const LayoutRotation = defineAsyncComponent(() => import('./LayoutRotation.vue'))

const pages = inject(LAYOUT_PAGES_KEY, () => [])
const page = computed(() => (props.content.type === 'page' ? pages().find((p) => p.id === (props.content as { page_id: string }).page_id) : undefined))
</script>
