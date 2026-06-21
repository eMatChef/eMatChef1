<template>
  <div>
    <div class="step-header">
      <span class="step-title">{{ t('activities.wizard.typeStepTitle') }}</span>
    </div>
    <div class="activity-type-chip-row" role="group" :aria-label="t('activities.wizard.typeStepTitle')">
      <v-btn
        v-for="opt in options"
        :key="opt.type"
        type="button"
        variant="outlined"
        elevation="0"
        class="activity-type-chip-btn"
        :data-onboarding="`activity-type-${opt.type}`"
        :class="[
          `activity-type-chip-btn--${opt.type}`,
          { 'activity-type-chip-btn--selected': selected === opt.type },
        ]"
        :aria-pressed="selected === opt.type"
        @click="$emit('select', opt.type)"
      >
        <v-icon :icon="typeIcons[opt.type]" start />
        <span class="activity-type-chip-btn__label">{{ opt.label }}</span>
      </v-btn>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityCreateType } from '@/composables/useActivityCreateWizard'
import { useActivityGroupMemberScope } from '@/composables/useActivityGroupMemberScope'

defineProps<{
  selected: ActivityCreateType | null
}>()

defineEmits<{
  select: [type: ActivityCreateType]
}>()

const { t } = useI18n()
const { allowedCreateActivityTypes } = useActivityGroupMemberScope()

const typeIcons: Record<ActivityCreateType, string> = {
  activity: 'mdi-white-balance-sunny',
  camp: 'mdi-home-variant-outline',
  event: 'mdi-star-outline',
  external: 'mdi-earth',
}

const typeLabels: Record<ActivityCreateType, string> = {
  activity: 'activities.types.activity',
  camp: 'activities.types.camp',
  event: 'activities.types.event',
  external: 'activities.types.external',
}

/** MW/DC: alle Typen; l1–l3 oder «u» + Gruppenchef: +camp/event; «extern» nur MW/DC. */
const options = computed(() => {
  const allowed = [...allowedCreateActivityTypes.value]
  return allowed.map((type) => ({
    type,
    label: t(typeLabels[type]),
  }))
})
</script>
