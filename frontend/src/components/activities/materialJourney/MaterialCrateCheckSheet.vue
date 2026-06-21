<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackContainerItem } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  issueAllPackContainerItems,
  returnAllPackContainerItems,
} from '@/api/activityContainers'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import { isJourneyReturnStep, isJourneyStoreStep } from '@/components/activities/materialJourneySteps'
import { getBackendStage, type PackStage } from '@/components/activities/packStageQuantities'
import EButton from '@/components/form/base/EButton.vue'
import { peekSectionsForJourneyContainer } from '@/composables/useMaterialJourneyCrateSections'
import type { MaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'
import { emptyMaterialJourneyCratePeekMaps } from '@/composables/materialJourneyCratePeekLoad'

const props = defineProps<{
  open: boolean
  container: ActivityPackContainer | null
  shellPackItem: ActivityPackItem | null
  packItems: ActivityPackItem[]
  packContainers: ActivityPackContainer[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
  cratePeekMaps?: MaterialJourneyCratePeekMaps
  journeyStep: JourneyStep
  packStage: PackStage
  activityId: string
  canSubmit: boolean
  issueableUnits: number
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  completed: []
}>()

const { t } = useI18n()
const submitting = ref(false)
const expandedPanels = ref<string[]>([])

const sections = computed((): PackCrateShellPeekSection[] => {
  if (!props.container) return []
  const peekMaps = props.cratePeekMaps ?? emptyMaterialJourneyCratePeekMaps()
  return peekSectionsForJourneyContainer(
    props.container,
    {
      containerItemsByContainerId: props.containerItemsByContainerId,
      ...peekMaps,
    },
    props.shellPackItem,
    t,
    props.packItems,
    props.packContainers,
  )
})

const primaryLabel = computed(() => {
  if (props.journeyStep === 'pack') {
    return t('activities.materialJourney.crateSheet.primaryPack')
  }
  if (isJourneyReturnStep(props.journeyStep)) {
    return t('activities.materialJourney.crateSheet.primaryReturn')
  }
  if (isJourneyStoreStep(props.journeyStep)) {
    return t('activities.materialJourney.crateSheet.primaryStore')
  }
  return t('activities.materialJourney.crateSheet.primaryIssue')
})

const subtitle = computed(() => {
  if (isJourneyReturnStep(props.journeyStep)) {
    return t('activities.materialJourney.crateSheet.subtitleReturn')
  }
  if (isJourneyStoreStep(props.journeyStep)) {
    return t('activities.materialJourney.crateSheet.subtitleStore')
  }
  return t('activities.materialJourney.crateSheet.subtitle')
})

const canPrimary = computed(
  () => props.canSubmit && props.issueableUnits > 0 && !submitting.value,
)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return
    expandedPanels.value = []
  },
)

function close(): void {
  emit('update:open', false)
}

async function onPrimary(): Promise<void> {
  if (!props.container || !canPrimary.value) return
  submitting.value = true
  try {
    if (isJourneyReturnStep(props.journeyStep)) {
      await returnAllPackContainerItems(props.activityId, props.container.id, 'bulk')
    } else {
      await issueAllPackContainerItems(
        props.activityId,
        props.container.id,
        getBackendStage(props.packStage),
        'bulk',
      )
    }
    emit('completed')
    close()
  } finally {
    submitting.value = false
  }
}

function sectionSummary(sec: PackCrateShellPeekSection): string {
  const open = sec.lines.reduce((sum, line) => sum + Math.max(0, line.quantity), 0)
  return t('activities.materialJourney.crateSheet.sectionSummary', { count: open })
}
</script>

<template>
  <v-dialog
    :model-value="open"
    fullscreen
    scrollable
    transition="dialog-bottom-transition"
    @update:model-value="emit('update:open', $event)"
  >
    <div v-if="container" class="material-journey-sheet">
      <header class="material-journey-sheet__header">
        <EButton variant="secondary" size="small" @click="close">
          {{ t('common.close') }}
        </EButton>
        <div class="material-journey-sheet__headline">
          <h2 class="material-journey-sheet__title">{{ container.label }}</h2>
          <p class="material-journey-sheet__subtitle text-muted">
            {{ subtitle }}
          </p>
        </div>
      </header>

      <div class="material-journey-sheet__body">
        <p v-if="sections.length === 0" class="text-muted material-journey-sheet__empty">
          {{ t('activities.materialJourney.crateSheet.empty') }}
        </p>
        <v-expansion-panels v-else v-model="expandedPanels" multiple class="material-journey-sheet__accordion">
          <v-expansion-panel
            v-for="sec in sections"
            :key="sec.subsectionKey"
            :value="sec.subsectionKey"
          >
            <v-expansion-panel-title>
              <span>{{ sec.title }}</span>
              <span class="material-journey-sheet__section-meta text-muted">{{ sectionSummary(sec) }}</span>
            </v-expansion-panel-title>
            <v-expansion-panel-text>
              <ul class="material-journey-sheet__lines">
                <li v-for="line in sec.lines" :key="line.id" class="material-journey-sheet__line">
                  <span class="material-journey-sheet__line-name">{{ line.materialName }}</span>
                  <span class="material-journey-sheet__line-qty">{{ line.quantity }}</span>
                </li>
              </ul>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>
      </div>

      <footer class="material-journey-sheet__footer">
        <EButton
          variant="primary"
          class="material-journey-sheet__primary"
          :disabled="!canPrimary"
          :loading="submitting"
          @click="onPrimary"
        >
          {{ primaryLabel }}
        </EButton>
      </footer>
    </div>
  </v-dialog>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey-sheet.css';
</style>
