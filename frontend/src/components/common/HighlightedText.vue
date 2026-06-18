<template>
  <span class="highlighted-text">
    <template v-for="(seg, index) in segments" :key="index">
      <strong v-if="seg.highlight" class="highlighted-text__match">{{ seg.text }}</strong>
      <template v-else>{{ seg.text }}</template>
    </template>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { textSegments } from '@/utils/searchHighlight'

const props = defineProps<{
  text?: string | null
  query?: string
}>()

const segments = computed(() => textSegments(props.text, props.query ?? ''))
</script>

<style scoped>
.highlighted-text__match {
  font-weight: 700;
  color: inherit;
}
</style>
