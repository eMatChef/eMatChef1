<template>
  <div v-if="steps.length" class="workflow-stepper">
    <div
      v-for="(step, index) in steps"
      :key="step.id"
      class="workflow-step"
      :class="stepState(step)"
    >
      <div class="workflow-step-marker">
        <span v-if="stepState(step) === 'done'">✓</span>
        <span v-else-if="stepState(step) === 'skipped'">–</span>
        <span v-else>{{ index + 1 }}</span>
      </div>
      <div class="workflow-step-label">{{ stepLabel(step) }}</div>
      <div
        v-if="index < steps.length - 1"
        class="workflow-step-connector"
        :class="connectorState(step, steps[index + 1])"
      />
    </div>
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

function connectorState(step: InternalWorkflowStep, next: InternalWorkflowStep): string {
  const current = stepState(step)
  const nextState = getWorkflowStepState(next, props.ticket)
  if (current === 'done' || nextState === 'done' || nextState === 'current') {
    return 'done'
  }
  if (current === 'skipped' || nextState === 'skipped') return 'skipped'
  return 'upcoming'
}
</script>

<style scoped>
.workflow-stepper {
  display: flex;
  align-items: flex-start;
  gap: 0;
  padding: 12px 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow-x: auto;
}

.workflow-step {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 88px;
  flex: 1;
  text-align: center;
}

.workflow-step-marker {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  background: #e2e8f0;
  color: #64748b;
  z-index: 1;
}

.workflow-step.current .workflow-step-marker {
  background: #2563eb;
  color: #fff;
  box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
}

.workflow-step.done .workflow-step-marker {
  background: #16a34a;
  color: #fff;
}

.workflow-step.skipped .workflow-step-marker {
  background: #f1f5f9;
  color: #94a3b8;
}

.workflow-step-label {
  margin-top: 6px;
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  line-height: 1.3;
  max-width: 92px;
}

.workflow-step.current .workflow-step-label {
  color: #1d4ed8;
}

.workflow-step.done .workflow-step-label {
  color: #15803d;
}

.workflow-step-connector {
  position: absolute;
  top: 14px;
  left: calc(50% + 18px);
  width: calc(100% - 36px);
  height: 2px;
  background: #e2e8f0;
  z-index: 0;
}

.workflow-step-connector.done {
  background: #86efac;
}

.workflow-step-connector.skipped {
  background: #f1f5f9;
}
</style>
