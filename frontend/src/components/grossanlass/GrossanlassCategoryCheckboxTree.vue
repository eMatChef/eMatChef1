<template>
  <div v-if="groups.length" class="cat-tree">
    <section v-for="group in groups" :key="group.root.id" class="cat-tree__col">
      <label class="cat-tree__root">
        <input
          type="checkbox"
          :checked="isOn(group.root.id)"
          :indeterminate="isPartial(group.root.id)"
          @change="onToggle(group.root.id, ($event.target as HTMLInputElement).checked)"
        >
        <span>{{ group.root.name }}</span>
      </label>
      <label
        v-for="row in group.children"
        :key="row.id"
        class="cat-tree__child"
        :class="{ 'cat-tree__child--item': row.isLeaf }"
        :style="{ paddingInlineStart: `${10 + row.depth * 14}px` }"
      >
        <input
          type="checkbox"
          :checked="isOn(row.id)"
          :indeterminate="isPartial(row.id)"
          @change="onToggle(row.id, ($event.target as HTMLInputElement).checked)"
        >
        <span>{{ row.name }}</span>
      </label>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { GrossanlassProcurementCategory } from '@/api/grossanlassProcurement'
import {
  childrenOfProcurementCategory,
  isProcurementArticle,
  isProcurementCategoryFullySelected,
  isProcurementCategoryIndeterminate,
  toggleProcurementCategorySelection,
} from '@/utils/grossanlassProcurementCategoryTree'

const props = defineProps<{
  categories: GrossanlassProcurementCategory[]
}>()

const selected = defineModel<string[]>({ default: () => [] })

type TreeGroup = {
  root: { id: string; name: string }
  children: { id: string; name: string; depth: number; isLeaf: boolean }[]
}

const groups = computed((): TreeGroup[] => {
  const all = props.categories
  const out: TreeGroup[] = []
  const walk = (parentId: string, depth: number, bucket: TreeGroup['children']) => {
    for (const cat of childrenOfProcurementCategory(all, parentId)) {
      bucket.push({
        id: cat.id,
        name: cat.name,
        depth,
        isLeaf: isProcurementArticle(cat),
      })
      walk(cat.id, depth + 1, bucket)
    }
  }
  for (const root of childrenOfProcurementCategory(all, null)) {
    const children: TreeGroup['children'] = []
    walk(root.id, 1, children)
    out.push({ root: { id: root.id, name: root.name }, children })
  }
  const nested = new Set(out.flatMap((g) => [g.root.id, ...g.children.map((c) => c.id)]))
  for (const cat of all) {
    if (!nested.has(cat.id)) {
      out.push({ root: { id: cat.id, name: cat.name }, children: [] })
    }
  }
  return out
})

function isOn(id: string): boolean {
  return isProcurementCategoryFullySelected(props.categories, selected.value, id)
}

function isPartial(id: string): boolean {
  return isProcurementCategoryIndeterminate(props.categories, selected.value, id)
}

function onToggle(id: string, checked: boolean) {
  selected.value = toggleProcurementCategorySelection(
    props.categories,
    selected.value,
    id,
    checked,
  )
}
</script>

<style scoped>
.cat-tree {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 10px;
  margin: 0 0 12px;
}
.cat-tree__col {
  min-width: 0;
  padding: 8px 10px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #f8fafc;
  max-height: min(70vh, 520px);
  overflow: auto;
}
.cat-tree__root,
.cat-tree__child {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  line-height: 1.35;
}
.cat-tree__root {
  font-size: 0.88rem;
  font-weight: 650;
  color: #0f172a;
  margin-bottom: 4px;
}
.cat-tree__child {
  font-size: 0.8rem;
  color: #334155;
  margin-top: 4px;
}
.cat-tree__child--item {
  font-size: 0.78rem;
  font-weight: 450;
  color: #1e293b;
}
.cat-tree__root input,
.cat-tree__child input {
  margin-top: 3px;
  flex-shrink: 0;
}
</style>
