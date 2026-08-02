<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import type { MaterialJourneyFilterTab } from '@/components/activities/materialJourneyTaskList'

const props = defineProps<{
  doneCount: number
  totalCount: number
  /** Quick Ausgabe: «Noch ausgeben» / «Mit mir unterwegs» statt Offen/Erledigt. */
  filterVariant?: 'default' | 'quickIssue'
  showByShelfFilter: boolean
  presenceLabels?: string[]
  showAddPackCrate?: boolean
  addPackCrateLoading?: boolean
  showMarkPacked?: boolean
  markPackedLabel?: string
  markPackedDisabled?: boolean
  markPackedLoading?: boolean
  markPackedHint?: string
  packCompleteDescription?: string
  /** Einlagern: nur Fortschritt, keine Offen/Erledigt-Tabs */
  hideFilterTabs?: boolean
  /** Quick Ausgabe: «Retour bringen» neben «Mit mir unterwegs». */
  showBringBack?: boolean
  bringBackDisabled?: boolean
  bringBackDisabledHint?: string
  /** Quick Teilausgabe: Status «Mit mir unterwegs» ohne Rest auszugeben. */
  showPartialTaken?: boolean
  partialTakenDisabled?: boolean
  partialTakenLoading?: boolean
  partialTakenTitle?: string
  /** Aufschlüsselung: Kisten / Verbrauch / lose */
  progressBreakdown?: string | null
}>()

const emit = defineEmits<{
  'add-pack-crate': []
  'mark-packed': []
  'bring-back': []
  'partial-taken': []
}>()

const filterTab = defineModel<MaterialJourneyFilterTab>('filterTab', { required: true })

const { t } = useI18n()

const openFilterLabel = computed(() =>
  props.filterVariant === 'quickIssue'
    ? t('activities.materialJourney.filter.openQuickIssue')
    : t('activities.materialJourney.filter.open'),
)

const doneFilterLabel = computed(() =>
  props.filterVariant === 'quickIssue'
    ? t('activities.materialJourney.filter.doneQuickIssue')
    : t('activities.materialJourney.filter.done'),
)

const progressLabel = computed(() =>
  props.filterVariant === 'quickIssue'
    ? t('activities.materialJourney.toolbar.progressQuickIssue', {
        done: props.doneCount,
        total: props.totalCount,
      })
    : t('activities.materialJourney.toolbar.progress', {
        done: props.doneCount,
        total: props.totalCount,
      }),
)

function selectTab(tab: MaterialJourneyFilterTab): void {
  filterTab.value = tab
}
</script>

<template>
  <div class="material-journey-toolbar section-card">
    <div v-if="showMarkPacked" class="material-journey-toolbar__packed-row">
      <div class="material-journey-toolbar__packed-copy">
        <p class="material-journey-toolbar__packed-title">
          {{ t('activities.materialJourney.packComplete.title') }}
        </p>
        <p v-if="packCompleteDescription" class="material-journey-toolbar__packed-desc text-muted">
          {{ packCompleteDescription }}
        </p>
        <p v-if="markPackedHint" class="material-journey-toolbar__packed-hint text-muted">
          {{ markPackedHint }}
        </p>
      </div>
      <EButton
        v-if="markPackedLabel"
        variant="primary"
        size="default"
        class="material-journey-toolbar__packed-action"
        :disabled="markPackedDisabled"
        :loading="markPackedLoading"
        :title="markPackedHint || markPackedLabel"
        @click="emit('mark-packed')"
      >
        {{ markPackedLabel }}
      </EButton>
    </div>

    <div
      v-if="!hideFilterTabs"
      class="material-journey-toolbar__filters"
      role="tablist"
      :aria-label="t('activities.materialJourney.filter.aria')"
    >
      <button
        type="button"
        class="material-journey-toolbar__chip"
        :class="{ 'material-journey-toolbar__chip--active': filterTab === 'open' }"
        role="tab"
        :aria-selected="filterTab === 'open'"
        @click="selectTab('open')"
      >
        {{ openFilterLabel }}
      </button>
      <button
        type="button"
        class="material-journey-toolbar__chip"
        :class="{ 'material-journey-toolbar__chip--active': filterTab === 'done' }"
        role="tab"
        :aria-selected="filterTab === 'done'"
        @click="selectTab('done')"
      >
        {{ doneFilterLabel }}
      </button>
      <button
        v-if="showBringBack"
        type="button"
        class="material-journey-toolbar__chip material-journey-toolbar__chip--action"
        :disabled="bringBackDisabled"
        :title="bringBackDisabled ? bringBackDisabledHint : undefined"
        @click="emit('bring-back')"
      >
        {{ t('activities.materialJourney.quickPhase.bringBack') }}
      </button>
      <button
        v-if="showPartialTaken"
        type="button"
        class="material-journey-toolbar__chip material-journey-toolbar__chip--action"
        :disabled="partialTakenDisabled || partialTakenLoading"
        :title="partialTakenTitle"
        @click="emit('partial-taken')"
      >
        {{ t('activities.materialJourney.partialTaken.action') }}
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
    <div v-if="showAddPackCrate" class="material-journey-toolbar__actions">
      <EButton
        variant="secondary"
        size="small"
        class="material-journey-toolbar__add-crate"
        :loading="addPackCrateLoading"
        :disabled="addPackCrateLoading"
        :title="t('activities.packList.addPackCrateSingleTitle')"
        @click="emit('add-pack-crate')"
      >
        <v-icon icon="mdi-package-variant-plus" start size="18" />
        {{ t('activities.packList.addPackCrateSingleButton') }}
      </EButton>
    </div>
    <p v-if="totalCount > 0" class="material-journey-toolbar__progress text-muted">
      {{ progressLabel }}
    </p>
    <p v-if="progressBreakdown" class="material-journey-toolbar__breakdown text-muted">
      {{ progressBreakdown }}
    </p>
    <p
      v-if="(presenceLabels ?? []).length > 0"
      class="material-journey-toolbar__presence-title text-muted"
    >
      {{ t('activities.materialJourney.toolbar.alsoHere') }}
    </p>
    <p
      v-for="(label, idx) in presenceLabels ?? []"
      :key="idx"
      class="material-journey-toolbar__presence text-muted"
    >
      {{ label }}
    </p>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';
</style>
