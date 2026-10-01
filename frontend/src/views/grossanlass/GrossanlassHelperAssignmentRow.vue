<template>
  <button
    type="button"
    class="ga-helper-assignment-row"
    :class="helperBarKindClass(assignment)"
    @click="$emit('open', assignment)"
  >
    <div class="ga-helper-assignment-row__head">
      <strong>{{ assignment.objectName }}</strong>
      <span class="ga-helper-bar__badge" :class="helperBarKindClass(assignment)">
        {{ t(`grossanlass.materialUebersicht.status.${assignment.status}`) }}
      </span>
    </div>
    <span v-if="assignment.operable === false" class="ga-helper-assignment-row__readonly">
      {{ t('grossanlass.helperAssignmentDetail.readOnlyHint') }}
    </span>
    <span class="ga-helper-assignment-row__org">{{ helperOrgLabel(assignment) }}</span>
    <span class="ga-helper-assignment-row__when">{{ assignment.timeRangeLabel }}</span>
  </button>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import {
  helperBarKindClass,
  helperOrgLabel,
  type GaHelperAssignment,
} from '@/views/grossanlass/grossanlassHelperAssignment'

defineProps<{
  assignment: GaHelperAssignment
}>()

defineEmits<{
  open: [assignment: GaHelperAssignment]
}>()

const { t } = useI18n()
</script>

<style scoped>
@import '@/views/grossanlass/grossanlassHelperBarColors.css';

.ga-helper-assignment-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
  padding: 10px 12px 10px 14px;
  border: 1px solid #e5e7eb;
  border-left: 4px solid var(--ga-helper-bar, #cbd5e1);
  border-radius: 8px;
  background: var(--ga-helper-bar-bg, #fff);
  font-size: 0.85rem;
  color: #64748b;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
}

.ga-helper-assignment-row:hover {
  border-color: color-mix(in srgb, var(--ga-helper-bar, #cbd5e1) 45%, #e5e7eb);
  box-shadow: 0 1px 4px rgb(15 23 42 / 6%);
}

.ga-helper-assignment-row:focus-visible {
  outline: 2px solid var(--ga-helper-bar, var(--color-primary, #2563eb));
  outline-offset: 2px;
}

.ga-helper-assignment-row__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.ga-helper-assignment-row strong {
  color: #0f172a;
  font-size: 0.95rem;
}

.ga-helper-assignment-row__org {
  color: #475569;
}

.ga-helper-assignment-row__when {
  font-variant-numeric: tabular-nums;
  color: #334155;
  font-weight: 500;
}

.ga-helper-assignment-row__readonly {
  font-size: 0.75rem;
  color: #9a3412;
}
</style>
