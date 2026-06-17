<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { MaterialJourneyFilterTab } from '@/components/activities/materialJourneyTaskList'

defineProps<{
  doneCount: number
  totalCount: number
  showByShelfFilter: boolean
}>()

const filterTab = defineModel<MaterialJourneyFilterTab>('filterTab', { required: true })

const { t } = useI18n()

function selectTab(tab: MaterialJourneyFilterTab): void {
  filterTab.value = tab
}
</script>

<template>
  <div class="material-journey-toolbar section-card">
    <div class="material-journey-toolbar__filters" role="tablist" :aria-label="t('activities.materialJourney.filter.aria')">
      <button
        type="button"
        class="material-journey-toolbar__chip"
        :class="{ 'material-journey-toolbar__chip--active': filterTab === 'open' }"
        role="tab"
        :aria-selected="filterTab === 'open'"
        @click="selectTab('open')"
      >
        {{ t('activities.materialJourney.filter.open') }}
      </button>
      <button
        type="button"
        class="material-journey-toolbar__chip"
        :class="{ 'material-journey-toolbar__chip--active': filterTab === 'done' }"
        role="tab"
        :aria-selected="filterTab === 'done'"
        @click="selectTab('done')"
      >
        {{ t('activities.materialJourney.filter.done') }}
      </button>
      <button
        v-if="showByShelfFilter"
        type="button"
        class="material-journey-toolbar__chip"
        :class="{ 'material-journey-toolbar__chip--active': filterTab === 'byShelf' }"
        role="tab"
        :aria-selected="filterTab === 'byShelf'"
        @click="selectTab('byShelf')"
      >
        {{ t('activities.materialJourney.filter.byShelf') }}
      </button>
    </div>
    <p v-if="totalCount > 0" class="material-journey-toolbar__progress text-muted">
      {{ t('activities.materialJourney.toolbar.progress', { done: doneCount, total: totalCount }) }}
    </p>
    <div class="material-journey-toolbar__presence-slot" aria-hidden="true" />
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
