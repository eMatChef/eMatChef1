<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainerItem } from '@/api/activityContainers'

const props = defineProps<{
  items: ActivityPackContainerItem[]
}>()

const { t } = useI18n()

const visibleItems = computed(() =>
  props.items.filter((item) => (item.quantity_packed ?? 0) > 0),
)
</script>

<template>
  <section v-if="visibleItems.length" class="material-journey-active-crate-contents">
    <ul class="material-journey-active-crate-contents__items">
      <li v-for="item in visibleItems" :key="item.id" class="material-journey-active-crate-contents__item">
        <span class="material-journey-active-crate-contents__item-name">{{ item.material_name }}</span>
        <span class="material-journey-active-crate-contents__item-qty">× {{ item.quantity_packed }}</span>
      </li>
    </ul>
  </section>
  <p v-else class="material-journey-active-crate-contents__empty text-muted">
    {{ t('activities.materialJourney.activeCrate.empty') }}
  </p>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
