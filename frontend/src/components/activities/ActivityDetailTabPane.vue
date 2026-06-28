<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    tabId: string
    activeTab: string
    /** Panel im Hintergrund mounten (Daten laden bevor der Tab sichtbar wird). */
    eager?: boolean
  }>(),
  {
    eager: false,
  },
)

/** Einmal besucht oder eager → gemountet lassen (v-show statt destroy). */
const mounted = ref(props.eager)

watch(
  () => props.activeTab,
  (tab) => {
    if (tab === props.tabId) mounted.value = true
  },
  { immediate: true },
)

const isActive = computed(() => props.activeTab === props.tabId)
</script>

<template>
  <div
    v-if="mounted"
    v-show="isActive"
    class="activity-detail-tab-pane"
    :data-activity-tab="tabId"
  >
    <slot />
  </div>
</template>
