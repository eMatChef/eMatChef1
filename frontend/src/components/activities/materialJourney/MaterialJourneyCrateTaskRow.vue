<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import type { MaterialJourneyTaskRow } from '@/components/activities/materialJourneyTaskList'

const props = defineProps<{
  row: MaterialJourneyTaskRow
  moving: boolean
  readonly: boolean
  packTargetActive: boolean
  contents: ActivityPackContainerItem[]
}>()

const emit = defineEmits<{
  activate: []
}>()

const { t } = useI18n()
const expanded = ref(false)

const hasContents = computed(() => props.contents.length > 0)

const qtyLabel = computed(() => {
  if (props.row.isOpen) {
    return t('activities.materialJourney.row.openQty', { count: props.row.openQty })
  }
  return t('activities.materialJourney.row.doneQty', { count: props.row.doneQty })
})

function badgeLabel(badge: MaterialJourneyTaskRow['badges'][number]): string {
  if (badge === 'crate') return t('activities.materialJourney.badge.crate')
  return badge
}

function toggleExpanded(event: Event): void {
  event.stopPropagation()
  expanded.value = !expanded.value
}

function onActivate(): void {
  emit('activate')
}
</script>

<template>
  <div
    class="material-journey-crate-row section-card"
    :class="{
      'material-journey-crate-row--target': packTargetActive,
      'material-journey-crate-row--filled': hasContents,
      'material-journey-crate-row--expanded': expanded && hasContents,
      'material-journey-crate-row--readonly': readonly,
      'material-journey-crate-row--moving': moving,
    }"
  >
    <div class="material-journey-crate-row__header">
      <button
        v-if="hasContents"
        type="button"
        class="material-journey-crate-row__chevron-btn"
        :aria-expanded="expanded"
        :aria-label="t('activities.packList.ariaToggleContainer')"
        @click="toggleExpanded"
      >
        <span
          class="material-journey-crate-row__chevron"
          :class="{ 'material-journey-crate-row__chevron--open': expanded }"
          aria-hidden="true"
        >▶</span>
      </button>
      <button
        type="button"
        class="material-journey-crate-row__main"
        :aria-pressed="packTargetActive"
        :disabled="moving"
        @click="onActivate"
      >
        <span class="material-journey-task-row__status material-journey-task-row__status--open" aria-hidden="true">
          ○
        </span>
        <span class="material-journey-task-row__kind-icon" aria-hidden="true">
          <v-icon icon="mdi-package-variant" size="20" />
        </span>
        <span class="material-journey-task-row__body">
          <span class="material-journey-task-row__title">{{ row.title }}</span>
          <span v-if="row.subtitle" class="material-journey-task-row__subtitle text-muted">
            {{ row.subtitle }}
          </span>
          <span v-if="row.badges.length" class="material-journey-task-row__badges">
            <span
              v-for="badge in row.badges"
              :key="badge"
              class="material-journey-task-row__badge"
            >
              {{ badgeLabel(badge) }}
            </span>
          </span>
          <span v-if="packTargetActive" class="material-journey-crate-row__target-badge">
            {{ t('activities.packList.crateTargetBadge') }}
          </span>
        </span>
        <span class="material-journey-task-row__qty">{{ qtyLabel }}</span>
      </button>
    </div>

    <div v-show="expanded && hasContents" class="material-journey-crate-row__contents">
      <ul class="material-journey-crate-row__content-list">
        <li v-for="item in contents" :key="item.id" class="material-journey-crate-row__content-line">
          <span class="material-journey-crate-row__content-name">
            {{ item.material_name || t('common.material') }}
          </span>
          <span class="material-journey-crate-row__content-qty text-muted">
            {{ t('activities.packList.qtyInContainerLine', { n: item.quantity_packed ?? 0 }) }}
          </span>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
