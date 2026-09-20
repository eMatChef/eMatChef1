<template>
  <span class="ga-group-node-icon" :class="'node-' + type">
    <v-icon :icon="grossanlassGroupNodeIcon(type)" :size="size" />
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  grossanlassGroupNodeIcon,
  resolveGrossanlassNodeType,
} from '@/utils/grossanlassGroupNode'

defineOptions({ name: 'GrossanlassGroupNodeIcon' })

const props = withDefaults(defineProps<{
  nodeType?: string | null
  size?: number | string
}>(), {
  size: 18,
})

const type = computed(() => resolveGrossanlassNodeType(props.nodeType ?? undefined))
</script>

<style scoped>
.ga-group-node-icon {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  flex-shrink: 0;
}

.ga-group-node-icon :deep(.v-icon) {
  color: inherit;
}

.ga-group-node-icon.node-ressort {
  background: #eef2ff;
  color: var(--color-primary, #0f766e);
}

.ga-group-node-icon.node-unterressort {
  background: #e0f2fe;
  color: #0369a1;
}

.ga-group-node-icon.node-bauprojekt {
  background: #fef3c7;
  color: #b45309;
}
</style>
