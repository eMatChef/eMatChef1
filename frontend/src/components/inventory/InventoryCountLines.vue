<template>
  <ul class="inventory-count-lines">
    <PackShellMiniCountRow
      v-for="line in lines"
      :key="line.id"
      :label="lineLabel(line)"
      :expected-qty="line.expected_qty"
      :counted-qty="line.counted_qty ?? 0"
      :review-status="lineReviews[line.id] ?? null"
      :disabled="disabled"
      :minus-title="t('tasksInventory.countMinus')"
      :plus-title="t('tasksInventory.countPlus')"
      :ok-title="t('tasksInventory.countConfirm')"
      :ok-aria-label="t('tasksInventory.countConfirmAria', { label: lineLabel(line) })"
      @update:counted-qty="onCountedChange(line.id, $event)"
      @ok="confirmLine(line.id)"
    />
  </ul>
</template>

<script setup lang="ts">
import { reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PackShellMiniCountRow from '@/components/activities/PackShellMiniCountRow.vue'
import type { InventoryTaskLine } from '@/api/inventoryTasks'

const props = defineProps<{
  lines: InventoryTaskLine[]
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:lines': [lines: InventoryTaskLine[]]
}>()

const { t } = useI18n()

const lineReviews = reactive<Record<string, 'ok' | null>>({})

watch(
  () => props.lines.map((line) => line.id).join(','),
  () => {
    for (const line of props.lines) {
      if (!(line.id in lineReviews)) {
        lineReviews[line.id] = null
      }
    }
  },
  { immediate: true },
)

function lineLabel(line: InventoryTaskLine): string {
  return line.material_name || line.material_item_id || line.id
}

function onCountedChange(lineId: string, value: number) {
  const next = props.lines.map((line) =>
    line.id === lineId ? { ...line, counted_qty: value } : line,
  )
  lineReviews[lineId] = null
  emit('update:lines', next)
}

function confirmLine(lineId: string) {
  lineReviews[lineId] = 'ok'
}

function allLinesConfirmed(): boolean {
  if (props.lines.length === 0) return false
  return props.lines.every((line) => lineReviews[line.id] === 'ok')
}

defineExpose({ allLinesConfirmed })
</script>

<style scoped>
.inventory-count-lines {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
</style>
