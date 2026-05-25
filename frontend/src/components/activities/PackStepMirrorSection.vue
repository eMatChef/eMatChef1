<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { PackMirrorSectionPreset } from '@/components/activities/packStepUi'

defineOptions({ name: 'PackStepMirrorSection' })

defineProps<{
  preset: PackMirrorSectionPreset
  /** Hint unter Kisten-Titel (z. B. «Kiste wählen …») — nur wenn preset.cratesHintKey gesetzt. */
  showCratesHint?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="pack-workflow-section" :class="preset.sectionClass">
    <div class="pack-workflow-section-title">{{ t(preset.titleKey) }}</div>

    <div
      v-if="$slots.crates"
      class="pack-containers-section pack-containers-section--at-event-select"
    >
      <p
        v-if="preset.cratesHintKey && showCratesHint !== false"
        class="pack-containers-at-event-hint text-muted"
      >
        {{ t(preset.cratesHintKey) }}
      </p>
      <div
        class="pack-containers-children"
        role="group"
        :aria-label="t(preset.cratesAriaKey)"
      >
        <slot name="crates" />
      </div>
    </div>

    <div
      v-if="$slots.loose && preset.looseSectionClass"
      class="pack-workflow-section"
      :class="preset.looseSectionClass"
    >
      <div v-if="preset.looseTitleKey" class="pack-workflow-section-title">
        {{ t(preset.looseTitleKey) }}
      </div>
      <slot name="loose" />
    </div>
    <slot v-else-if="$slots.loose" name="loose" />

    <slot />
  </div>
</template>
