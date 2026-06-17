<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer, ActivityPackContainerItem } from '@/api/activityContainers'
import { postMovePackItem, type ActivityPackItem } from '@/api/activityPackItems'
import { getComboComponents, type ComboComponent } from '@/api/materials'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import type { JourneyStep } from '@/components/activities/materialJourneySteps'
import { isJourneyReturnStep, isJourneyStoreStep } from '@/components/activities/materialJourneySteps'
import { getBackendStage, type PackStage } from '@/components/activities/packStageQuantities'
import { packMaterialDisplayName } from '@/components/activities/packMaterialDisplay'
import EButton from '@/components/form/base/EButton.vue'
import { peekSectionsForJourneyCombo } from '@/composables/useMaterialJourneyCrateSections'

const props = defineProps<{
  open: boolean
  packItem: ActivityPackItem | null
  packContainers: ActivityPackContainer[]
  containerItemsByContainerId: Record<string, ActivityPackContainerItem[]>
  journeyStep: JourneyStep
  packStage: PackStage
  activityId: string
  canSubmit: boolean
  maxForwardQty: number
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  completed: [item: ActivityPackItem]
}>()

const { t } = useI18n()
const submitting = ref(false)
const loading = ref(false)
const expandedPanels = ref<string[]>([])
const comboComponentsByMaterialId = ref<Record<string, ComboComponent[]>>({})
const checkedLineIds = ref<Set<string>>(new Set())

const sections = computed((): PackCrateShellPeekSection[] => {
  if (!props.packItem) return []
  return peekSectionsForJourneyCombo(
    props.packItem,
    props.packContainers,
    props.containerItemsByContainerId,
    comboComponentsByMaterialId.value,
    t,
  )
})

const allLineIds = computed(() =>
  sections.value.flatMap((sec) => sec.lines.map((line) => `${sec.subsectionKey}:${line.id}`)),
)

const allChecked = computed(
  () => allLineIds.value.length > 0 && allLineIds.value.every((id) => checkedLineIds.value.has(id)),
)

const primaryLabel = computed(() => {
  if (props.journeyStep === 'pack') {
    return t('activities.materialJourney.comboSheet.primaryPack')
  }
  if (isJourneyReturnStep(props.journeyStep)) {
    return t('activities.materialJourney.comboSheet.primaryReturn')
  }
  if (isJourneyStoreStep(props.journeyStep)) {
    return t('activities.materialJourney.comboSheet.primaryStore')
  }
  return t('activities.materialJourney.comboSheet.primaryIssue')
})

const canPrimary = computed(
  () => props.canSubmit && props.maxForwardQty > 0 && allChecked.value && !submitting.value,
)

watch(
  () => [props.open, props.packItem?.id] as const,
  async ([isOpen, packItemId]) => {
    if (!isOpen || !packItemId || !props.packItem) return
    checkedLineIds.value = new Set()
    expandedPanels.value = []
    loading.value = true
    try {
      const mid = props.packItem.materialItemId
      if (!comboComponentsByMaterialId.value[mid]) {
        const list = await getComboComponents(mid).catch(() => [] as ComboComponent[])
        comboComponentsByMaterialId.value = {
          ...comboComponentsByMaterialId.value,
          [mid]: list,
        }
      }
    } finally {
      loading.value = false
    }
  },
)

function close(): void {
  emit('update:open', false)
}

function toggleLine(key: string): void {
  const next = new Set(checkedLineIds.value)
  if (next.has(key)) next.delete(key)
  else next.add(key)
  checkedLineIds.value = next
}

async function onPrimary(): Promise<void> {
  if (!props.packItem || !canPrimary.value) return
  submitting.value = true
  try {
    const updated = await postMovePackItem(props.activityId, props.packItem.id, {
      stage: getBackendStage(props.packStage),
      quantity: props.maxForwardQty,
      source: 'tap',
    })
    emit('completed', updated)
    close()
  } finally {
    submitting.value = false
  }
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
    <div v-if="packItem" class="material-journey-sheet">
      <header class="material-journey-sheet__header">
        <EButton variant="secondary" size="small" @click="close">
          {{ t('common.close') }}
        </EButton>
        <div class="material-journey-sheet__headline">
          <h2 class="material-journey-sheet__title">{{ packMaterialDisplayName(packItem) }}</h2>
          <p class="material-journey-sheet__subtitle text-muted">
            {{ t('activities.materialJourney.comboSheet.subtitle') }}
          </p>
        </div>
      </header>

      <div class="material-journey-sheet__body">
        <p v-if="loading" class="text-muted material-journey-sheet__empty">
          {{ t('activities.materialJourney.comboSheet.loading') }}
        </p>
        <p v-else-if="sections.length === 0" class="text-muted material-journey-sheet__empty">
          {{ t('activities.materialJourney.comboSheet.empty') }}
        </p>
        <v-expansion-panels v-else v-model="expandedPanels" multiple class="material-journey-sheet__accordion">
          <v-expansion-panel
            v-for="sec in sections"
            :key="sec.subsectionKey"
            :value="sec.subsectionKey"
          >
            <v-expansion-panel-title>{{ sec.title }}</v-expansion-panel-title>
            <v-expansion-panel-text>
              <ul class="material-journey-sheet__lines">
                <li v-for="line in sec.lines" :key="line.id" class="material-journey-sheet__line">
                  <label class="material-journey-sheet__check-row">
                    <input
                      type="checkbox"
                      class="material-journey-sheet__checkbox"
                      :checked="checkedLineIds.has(`${sec.subsectionKey}:${line.id}`)"
                      :disabled="!canSubmit"
                      @change="toggleLine(`${sec.subsectionKey}:${line.id}`)"
                    />
                    <span class="material-journey-sheet__line-name">{{ line.materialName }}</span>
                    <span class="material-journey-sheet__line-qty">{{ line.quantity }}</span>
                  </label>
                </li>
              </ul>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>
      </div>

      <footer class="material-journey-sheet__footer">
        <p v-if="!allChecked && sections.length > 0" class="material-journey-sheet__hint text-muted">
          {{ t('activities.materialJourney.comboSheet.checkAllHint') }}
        </p>
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
