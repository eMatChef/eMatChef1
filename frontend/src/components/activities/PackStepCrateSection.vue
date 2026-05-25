<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { PackCrateSectionPreset } from '@/components/activities/packStepUi'

defineOptions({ name: 'PackStepCrateSection' })

defineProps<{
  preset: PackCrateSectionPreset
  showEmptyHint?: boolean
  /** Hint ausblenden (z. B. Bestätigt→Gepackt nur bei editierbarer Liste). Standard: an wenn preset.hintKey gesetzt. */
  showHint?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div
    class="pack-workflow-section pack-workflow-section--kisten"
    :class="preset.sectionClass"
  >
    <div class="pack-workflow-section-title">{{ t(preset.titleKey) }}</div>
    <div
      class="pack-containers-section"
      :class="{ 'pack-containers-section--at-event-select': preset.atEventSelect }"
    >
      <div v-if="preset.showContainersHeading" class="pack-containers-heading">
        <span class="pack-containers-title text-muted">{{
          t('activities.packList.sectionContainers')
        }}</span>
      </div>
      <p
        v-if="preset.hintKey && showHint !== false"
        class="pack-containers-at-event-hint text-muted"
      >
        {{ t(preset.hintKey) }}
      </p>
      <div class="pack-containers-children" role="group" :aria-label="t(preset.ariaKey)">
        <p v-if="showEmptyHint && preset.emptyHintKey" class="pack-containers-empty-hint text-muted">
          {{ t(preset.emptyHintKey) }}
        </p>
        <slot />
      </div>
    </div>
  </div>
</template>
