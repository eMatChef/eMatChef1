<template>
  <div class="w-image">
    <EmcLogoMark v-if="source === 'emc_logo'" size="lg" />
    <img v-else-if="url" :src="url" :alt="alt" :style="{ objectFit: fit }" referrerpolicy="no-referrer" draggable="false">
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'

const props = defineProps<{ config: Record<string, unknown> }>()
const source = computed(() => String(props.config.source ?? 'emc_logo'))
const url = computed(() => String(props.config.url ?? ''))
const alt = computed(() => String(props.config.alt ?? ''))
const fit = computed(() => (props.config.fit === 'cover' ? 'cover' : 'contain'))
</script>

<style scoped>
.w-image {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  overflow: hidden;
}
.w-image img {
  width: 100%;
  height: 100%;
}
</style>
