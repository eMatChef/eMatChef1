<template>
  <div class="w-list">
    <h3 v-if="title">{{ title }}</h3>
    <component :is="ordered ? 'ol' : 'ul'">
      <li v-for="(item, i) in items" :key="i">{{ item }}</li>
    </component>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ config: Record<string, unknown> }>()
const title = computed(() => String(props.config.title ?? ''))
const items = computed(() => (Array.isArray(props.config.items) ? props.config.items.map(String) : []))
const ordered = computed(() => props.config.ordered === true)
</script>

<style scoped>
.w-list {
  width: 100%;
  height: 100%;
  padding: 0.6em 0.9em;
  box-sizing: border-box;
  border-radius: 0.6em;
  background: #111c33;
  overflow: hidden;
}
.w-list h3 { margin: 0 0 0.4em; font-size: 1.3em; }
.w-list ul,
.w-list ol { margin: 0; padding-left: 1.2em; font-size: 1.05em; }
.w-list li { margin: 0.2em 0; overflow-wrap: anywhere; }
</style>
