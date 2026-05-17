<template>
  <div>
    <div class="step-header">
      <span class="step-title">{{ t('activities.wizard.typeStepTitle') }}</span>
    </div>
    <div class="type-chip-row">
      <button
        v-for="opt in options"
        :key="opt.type"
        type="button"
        class="type-chip"
        :class="{ active: selected === opt.type, [opt.type]: true }"
        @click="$emit('select', opt.type)"
      >
        <span class="type-chip-icon" aria-hidden="true">
          <svg v-if="opt.type === 'activity'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" />
          </svg>
          <svg v-else-if="opt.type === 'camp'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 21h18M5 21V10l7-5 7 5v11M9 21v-4h6v4" />
          </svg>
          <svg v-else-if="opt.type === 'event'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
          </svg>
        </span>
        <span class="type-chip-name">{{ opt.label }}</span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityCreateType } from '@/composables/useActivityCreateWizard'
import { useAuthStore } from '@/stores/auth'
import { useActivityGroupMemberScope } from '@/composables/useActivityGroupMemberScope'

defineProps<{
  selected: ActivityCreateType | null
}>()

defineEmits<{
  select: [type: ActivityCreateType]
}>()

const { t } = useI18n()
const auth = useAuthStore()
const { allowedCreateActivityTypes } = useActivityGroupMemberScope()

const typeLabels: Record<ActivityCreateType, string> = {
  activity: 'activities.types.activity',
  camp: 'activities.types.camp',
  event: 'activities.types.event',
  external: 'activities.types.external',
}

/** Typ «extern» nur für DC/MW; Gruppenmitglied (u) nur «Aktivität». */
const options = computed(() => {
  const role = auth.currentDepartmentRole
  let allowed = [...allowedCreateActivityTypes.value]
  if (role !== 'mw' && role !== 'dc') {
    allowed = allowed.filter((type) => type !== 'external')
  }
  return allowed.map((type) => ({
    type,
    label: t(typeLabels[type]),
  }))
})
</script>
