<template>
  <div v-if="steps.length" class="ws-stepper-card">
    <div class="ws-stepper-track" role="list" aria-label="Reparatur-Fortschritt">
      <template v-for="(step, index) in steps" :key="step.id">
        <div
          class="ws-stepper-step"
          :class="stepState(step)"
          role="listitem"
          :aria-current="stepState(step) === 'current' ? 'step' : undefined"
        >
          <div class="ws-stepper-node">
            <v-icon v-if="stepState(step) === 'done'" icon="mdi-check" size="16" />
            <v-icon v-else-if="stepState(step) === 'skipped'" icon="mdi-minus" size="14" />
            <span v-else class="ws-stepper-num">{{ index + 1 }}</span>
          </div>
          <span class="ws-stepper-label">{{ stepLabel(step) }}</span>
        </div>

        <div
          v-if="index < steps.length - 1"
          class="ws-stepper-rail"
          :class="railState(step, steps[index + 1])"
          aria-hidden="true"
        />
      </template>
    </div>

    <p v-if="hint" class="ws-stepper-hint">
      {{ hint }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { WorkshopTicket } from '@/api/workshop'
import { ticketHasRepairSheet, ticketUsesPartsList } from '@/composables/useWorkshopTriageOptions'
import {
  getInternalWorkflowSteps,
  getWorkflowStepState,
  type InternalWorkflowStep,
} from '@/utils/workshopWorkflow'

const props = defineProps<{
  ticket: WorkshopTicket
  hint?: string | null
}>()

const { t } = useI18n()

const steps = computed(() => getInternalWorkflowSteps(props.ticket))

const usesSheet = computed(() => ticketHasRepairSheet(props.ticket))
const usesParts = computed(() => ticketUsesPartsList(props.ticket))

function stepState(step: InternalWorkflowStep) {
  return getWorkflowStepState(step, props.ticket)
}

const stepLabelKeys: Record<InternalWorkflowStep['id'], string> = {
  plan: 'workshop.workflow.stepPlan',
  procure: 'workshop.workflow.stepProcure',
  ready: 'workshop.workflow.stepReady',
  work: 'workshop.workflow.stepWork',
  done: 'workshop.workflow.stepDone',
}

function stepLabel(step: InternalWorkflowStep): string {
  if (step.id === 'plan') {
    if (usesSheet.value) return t('workshop.workflow.stepPlanSheet')
    if (usesParts.value) return t('workshop.workflow.stepPlanMaterial')
  }
  return t(stepLabelKeys[step.id])
}

function railState(step: InternalWorkflowStep, next: InternalWorkflowStep): string {
  const current = stepState(step)
  const nextState = getWorkflowStepState(next, props.ticket)
  if (current === 'skipped' && nextState === 'skipped') return 'skipped'
  if (current === 'done' || nextState === 'done' || nextState === 'current') return 'done'
  if (current === 'skipped' || nextState === 'skipped') return 'skipped'
  return 'upcoming'
}
</script>

<style scoped>
.ws-stepper-card {
  margin-bottom: 16px;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  background: #fff;
  overflow: hidden;
}

.ws-stepper-track {
  display: flex;
  align-items: flex-start;
  width: 100%;
  gap: 0;
  padding: 16px 18px 12px;
  overflow-x: auto;
  scrollbar-width: thin;
}

.ws-stepper-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  gap: 8px;
  flex: 0 0 auto;
  width: 72px;
  text-align: center;
}

.ws-stepper-node {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #e5e7eb;
  background: #fff;
  color: #9ca3af;
  flex-shrink: 0;
  transition: background 0.15s, border-color 0.15s, color 0.15s, box-shadow 0.15s;
}

.ws-stepper-num {
  font-size: 13px;
  font-weight: 700;
  line-height: 1;
}

.ws-stepper-label {
  display: block;
  width: 100%;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.25;
  color: #6b7280;
  word-break: break-word;
}

.ws-stepper-rail {
  flex: 1 1 24px;
  min-width: 20px;
  height: 2px;
  margin-top: 15px;
  background: #e5e7eb;
  border-radius: 1px;
}

.ws-stepper-rail.done {
  background: var(--color-primary-light);
}

.ws-stepper-rail.skipped {
  background: repeating-linear-gradient(
    90deg,
    #e5e7eb 0,
    #e5e7eb 4px,
    transparent 4px,
    transparent 8px
  );
}

.ws-stepper-step.current .ws-stepper-node {
  border-color: var(--color-primary);
  background: var(--color-primary);
  color: #fff;
  box-shadow: 0 0 0 4px var(--color-primary-ring);
}

.ws-stepper-step.current .ws-stepper-label {
  color: var(--color-primary-dark);
}

.ws-stepper-step.done .ws-stepper-node {
  border-color: var(--color-primary);
  background: var(--color-primary);
  color: #fff;
}

.ws-stepper-step.done .ws-stepper-label {
  color: var(--color-primary-dark);
}

.ws-stepper-step.skipped .ws-stepper-node {
  border-style: dashed;
  border-color: #d1d5db;
  background: #f9fafb;
  color: #cbd5e1;
}

.ws-stepper-step.skipped .ws-stepper-label {
  color: #9ca3af;
  text-decoration: line-through;
  text-decoration-color: #d1d5db;
}

.ws-stepper-step.upcoming .ws-stepper-node {
  border-color: #e5e7eb;
  background: #f9fafb;
  color: #9ca3af;
}

.ws-stepper-hint {
  margin: 0;
  padding: 12px 18px;
  border-top: 1px solid var(--color-border);
  background: var(--color-primary-muted-bg);
  color: var(--color-primary-dark);
  font-size: 13px;
  line-height: 1.45;
  font-weight: 500;
}
</style>
