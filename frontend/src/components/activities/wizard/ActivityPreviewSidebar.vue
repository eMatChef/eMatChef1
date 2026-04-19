<template>
  <aside class="material-wizard-sidebar activity-preview-sidebar">
    <h3>Vorschau</h3>
    <div class="material-preview">
      <div class="preview-image" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
          <line x1="16" y1="2" x2="16" y2="6" />
          <line x1="8" y1="2" x2="8" y2="6" />
          <line x1="3" y1="10" x2="21" y2="10" />
        </svg>
      </div>
      <div class="preview-info">
        <h4>{{ previewTitle }}</h4>
        <span v-if="selectedActivityType" class="preview-badge" :class="selectedActivityType">
          {{ activityTypeLabel(selectedActivityType) }}
        </span>
        <span v-else class="preview-badge preview-badge--muted">Noch kein Typ</span>
        <template v-if="selectedActivityType">
          <p v-if="previewGroupLine" class="preview-meta">
            <span class="preview-dates-label">Gruppe</span>
            {{ previewGroupLine }}
          </p>
          <p v-if="previewVenueLine" class="preview-meta">
            <span class="preview-dates-label">Eventstandort</span>
            {{ previewVenueLine }}
          </p>
          <p v-if="previewMieterLine" class="preview-meta">
            <span class="preview-dates-label">Mieter</span>
            {{ previewMieterLine }}
          </p>
          <p v-if="previewMaterialLine" class="preview-meta">
            <span class="preview-dates-label">Material</span>
            {{ previewMaterialLine }}
          </p>
          <p v-if="previewInvitedLine" class="preview-meta">
            <span class="preview-dates-label">Eingeladen</span>
            {{ previewInvitedLine }}
          </p>
          <p class="preview-dates">
            <span class="preview-dates-label">{{ usageLabel }}</span>{{ previewUsageLine }}
          </p>
          <p class="preview-dates preview-dates--secondary">
            <span class="preview-dates-label">{{ materialLabel }}</span>{{ previewPlanningLine }}
          </p>
        </template>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ActivityCreateType } from '@/composables/useActivityCreateWizard'
import { activityPreviewMaterialLabel, activityPreviewUsageLabel } from './activityPreviewLabels'
import { activityTypeLabel } from './activityTypeLabels'

const props = defineProps<{
  previewTitle: string
  previewUsageLine: string
  previewPlanningLine: string
  selectedActivityType: ActivityCreateType | null
  /** Gruppenname (Aktivität / Lager / Event) */
  previewGroupLine?: string | null
  /** Bezeichnung gewählter Eventstandort-Adresse */
  previewVenueLine?: string | null
  /** Name/Firma der Mieter-Adresse (extern) */
  previewMieterLine?: string | null
  /** Kurzliste gewählter Materialpositionen */
  previewMaterialLine?: string | null
  /** Weitere Departments (Lager/Event) */
  previewInvitedLine?: string | null
}>()

const usageLabel = computed(() =>
  props.selectedActivityType ? activityPreviewUsageLabel(props.selectedActivityType) : '',
)
const materialLabel = computed(() =>
  props.selectedActivityType ? activityPreviewMaterialLabel(props.selectedActivityType) : '',
)
</script>
