<template>
  <div class="aufgabe-detail">
    <nav class="aufgabe-detail__crumbs" :aria-label="t('grossanlass.aufgaben.detail.origin')">
      <template v-for="(part, index) in task.origin" :key="`${part}-${index}`">
        <span>{{ part }}</span>
        <v-icon v-if="index < task.origin.length - 1" icon="mdi-chevron-right" size="14" />
      </template>
    </nav>

    <div class="aufgabe-detail__head">
      <v-chip size="small" variant="tonal" :prepend-icon="KIND_ICON[task.kind]">
        {{ t(`grossanlass.aufgaben.kind.${task.kind}`) }}
      </v-chip>
      <v-chip size="small" variant="flat" :color="STATUS_COLOR[statusKey(task, now)]">
        {{ t(`grossanlass.aufgaben.status.${statusKey(task, now)}`) }}
      </v-chip>
    </div>

    <dl class="aufgabe-detail__facts">
      <div>
        <dt>{{ t('grossanlass.aufgaben.detail.time') }}</dt>
        <dd>
          {{ dayLabel(task.startsAt, locale) }} · {{ whenLabel(task, locale) }}
          <span v-if="task.deadlineLabel" class="aufgabe-detail__deadline">{{ task.deadlineLabel }}</span>
        </dd>
      </div>
      <div>
        <dt>{{ t('grossanlass.aufgaben.detail.people') }}</dt>
        <dd>
          <span v-if="!task.people.length" class="aufgabe-detail__unassigned">
            {{ t('grossanlass.aufgaben.unassigned') }}
          </span>
          <v-chip
            v-for="person in task.people"
            :key="person"
            size="small"
            variant="outlined"
            :closable="canAssign"
            class="mr-1"
            @click:close="emit('assign', null)"
          >
            {{ person }}
          </v-chip>
        </dd>
        <div v-if="canAssign" class="aufgabe-detail__assign">
          <ESelect
            v-model="assignee"
            :items="assignableItems"
            :label="t('grossanlass.aufgaben.detail.assign')"
            density="compact"
            hide-details
            clearable
          />
          <EButton variant="secondary" size="small" :disabled="!assignee" @click="addAssignee">
            {{ t('grossanlass.aufgaben.assign') }}
          </EButton>
        </div>
      </div>
    </dl>

    <section v-if="task.helperNeed" class="aufgabe-detail__section aufgabe-detail__need">
      <h4>{{ t('grossanlass.aufgaben.detail.helperNeed') }}</h4>
      <p>
        <strong>{{ t('grossanlass.aufgaben.detail.helperNeedLine', { count: task.helperNeed.count, skill: task.helperNeed.skill, assigned: task.people.length }) }}</strong>
        <router-link v-if="departmentId" :to="`/${departmentId}/ga/helper-pool`" class="aufgabe-detail__link">{{ t('grossanlass.aufgaben.detail.toHelferpool') }}</router-link>
      </p>
    </section>

    <section v-if="task.description" class="aufgabe-detail__section">
      <h4>{{ t('grossanlass.aufgaben.detail.description') }}</h4>
      <p>{{ task.description }}</p>
    </section>

    <section v-if="task.blocked" class="aufgabe-detail__section aufgabe-detail__blocked" data-testid="task-blocked">
      <h4><v-icon icon="mdi-alert-octagon-outline" size="18" /> {{ t('grossanlass.aufgaben.blocked.title') }}</h4>
      <p>{{ task.blocked.reason }}</p>
      <EButton v-if="task.permissions?.unblock" variant="secondary" size="small" @click="emit('unblock')">
        {{ t('grossanlass.aufgaben.blocked.release') }}
      </EButton>
    </section>

    <section v-if="task.rework" class="aufgabe-detail__section aufgabe-detail__rework" data-testid="task-rework">
      <h4><v-icon icon="mdi-wrench-outline" size="18" /> {{ t('grossanlass.aufgaben.rework.chip') }}</h4>
      <p>{{ t('grossanlass.aufgaben.rework.reasonLine', { reason: task.rework.reason ?? '–' }) }}</p>
    </section>

    <p v-if="task.orderStatus && task.orderStatus !== 'open'" class="aufgabe-detail__order" data-testid="task-order-status">
      <v-icon icon="mdi-lock-outline" size="16" /> {{ t(`grossanlass.aufgaben.acceptance.taskNote.${task.orderStatus}`) }}
    </p>

    <section v-if="task.waiting" class="aufgabe-detail__section aufgabe-detail__waiting" data-testid="task-waiting">
      <h4><v-icon icon="mdi-timer-sand" size="18" /> {{ waitingText }}</h4>
    </section>

    <section v-if="task.dependencies?.length || (task.groupId && task.permissions?.edit && dependencyCandidates?.length)" class="aufgabe-detail__section">
      <h4>{{ t('grossanlass.aufgaben.deps.title') }}</h4>
      <GrossanlassDependencyList
        :dependencies="task.dependencies ?? []"
        :can-edit="!!task.permissions?.edit && !readOnly"
        :candidates="dependencyCandidates ?? []"
        allow-simultaneous
        @add="(id, mode) => emit('dependencyAdd', id, mode)"
        @change-mode="(dep, mode) => emit('dependencyMode', dep, mode)"
        @remove="(dep) => emit('dependencyRemove', dep)"
        @release="(dep, reason) => emit('dependencyRelease', dep, reason)"
      />
    </section>

    <section v-if="task.status === 'review'" class="aufgabe-detail__section aufgabe-detail__review" data-testid="task-review">
      <h4><v-icon icon="mdi-clipboard-check-outline" size="18" /> {{ t('grossanlass.aufgaben.review.title') }}</h4>
      <p>{{ t('grossanlass.aufgaben.review.waiting') }}</p>
      <div v-if="task.permissions?.approve && !readOnly && !returning" class="aufgabe-detail__actions">
        <EButton variant="primary" size="small" @click="emit('accept')">{{ t('grossanlass.aufgaben.review.accept') }}</EButton>
        <EButton variant="secondary" size="small" @click="returning = true">{{ t('grossanlass.aufgaben.review.giveBack') }}</EButton>
      </div>
      <div v-if="returning" class="aufgabe-detail__section">
        <ETextField v-model="returnReason" :label="t('grossanlass.aufgaben.review.reason')" density="compact" hide-details />
        <div class="aufgabe-detail__actions">
          <EButton variant="primary" size="small" :disabled="!returnReason.trim()" @click="submitReturn">{{ t('grossanlass.aufgaben.review.giveBackSubmit') }}</EButton>
          <EButton variant="secondary" size="small" @click="returning = false">{{ t('common.cancel') }}</EButton>
        </div>
      </div>
    </section>

    <section v-if="task.steps.length || (task.groupId && task.permissions?.edit)" class="aufgabe-detail__section" data-testid="task-checklist">
      <h4>{{ t('grossanlass.aufgaben.checklist.title') }}</h4>
      <ul class="aufgabe-checklist">
        <li v-for="(step, index) in task.steps" :key="step.id ?? step.label">
          <v-checkbox-btn
            :model-value="step.done"
            :disabled="!step.id || !task.permissions?.check || !!task.blocked || readOnly"
            color="primary"
            :aria-label="step.label"
            @update:model-value="(value) => emit('stepDone', step.id!, !!value)"
          />
          <span :class="{ 'aufgabe-checklist__done': step.done }">{{ step.label }}</span>
          <span v-if="task.permissions?.edit && !readOnly" class="aufgabe-checklist__tools">
            <v-btn icon="mdi-arrow-up" size="x-small" variant="text" :disabled="index === 0" :aria-label="t('grossanlass.aufgaben.checklist.up')" @click="emit('stepMove', step.id!, -1)" />
            <v-btn icon="mdi-arrow-down" size="x-small" variant="text" :disabled="index === task.steps.length - 1" :aria-label="t('grossanlass.aufgaben.checklist.down')" @click="emit('stepMove', step.id!, 1)" />
            <v-btn icon="mdi-delete-outline" size="x-small" variant="text" :aria-label="t('common.delete')" @click="emit('stepRemove', step.id!)" />
          </span>
        </li>
      </ul>
      <div v-if="task.permissions?.edit && !readOnly" class="aufgabe-checklist__add">
        <ETextField v-model="newStep" :label="t('grossanlass.aufgaben.checklist.add')" density="compact" hide-details @keyup.enter="addStep" />
        <EButton variant="secondary" size="small" :disabled="!newStep.trim()" @click="addStep">{{ t('grossanlass.aufgaben.checklist.addButton') }}</EButton>
      </div>
    </section>

    <section class="aufgabe-detail__section">
      <h4>{{ t('grossanlass.aufgaben.detail.progress') }}</h4>
      <div class="aufgabe-progress">
        <v-progress-linear :model-value="progress" height="10" rounded color="primary" />
        <span>{{ progress }} %</span>
      </div>
    </section>

    <section v-if="task.events?.length" class="aufgabe-detail__section" data-testid="task-history">
      <h4>{{ t('grossanlass.aufgaben.review.history') }}</h4>
      <ul class="aufgabe-history">
        <li v-for="event in task.events" :key="event.id">
          <strong>{{ t(`grossanlass.aufgaben.review.event.${event.type}`) }}</strong>
          · {{ event.byName ?? '?' }} · {{ eventTime(event.at) }}
          <span v-if="event.note" class="aufgabe-history__note">— {{ event.note }}</span>
        </li>
      </ul>
    </section>

    <p v-if="error" class="aufgabe-detail__error" role="alert">{{ error === 'error' ? t('grossanlass.aufgaben.actionError') : error }}</p>

    <div v-if="blocking" class="aufgabe-detail__section">
      <ETextField v-model="blockReason" :label="t('grossanlass.aufgaben.blocked.reason')" density="compact" hide-details />
      <div class="aufgabe-detail__actions">
        <EButton variant="primary" size="small" :disabled="!blockReason.trim()" @click="submitBlock">{{ t('grossanlass.aufgaben.blocked.submit') }}</EButton>
        <EButton variant="secondary" size="small" @click="blocking = false">{{ t('common.cancel') }}</EButton>
      </div>
    </div>

    <div v-if="!readOnly && (task.permissions?.report ?? true)" class="aufgabe-detail__actions">
      <EButton v-if="task.status === 'open'" variant="primary" size="large" :disabled="!!task.blocked || task.waiting" @click="emit('status', 'progress')">
        {{ t('grossanlass.aufgaben.start') }}
      </EButton>
      <EButton v-if="task.status === 'open' || task.status === 'progress'" variant="secondary" size="large" :disabled="!!task.blocked || task.waiting" @click="emit('report', '')">
        {{ t('grossanlass.aufgaben.review.report') }}
      </EButton>
      <EButton v-if="task.permissions?.report && (task.status === 'open' || task.status === 'progress') && !task.blocked && !blocking" variant="secondary" size="large" @click="blocking = true">
        {{ t('grossanlass.aufgaben.blocked.report') }}
      </EButton>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, ESelect, ETextField } from '@/components/form/base'
import type { GaDependency, GaDependencyMode, GaTaskStatus } from '@/api/grossanlassBauprojekt'
import GrossanlassDependencyList from '@/components/grossanlass/GrossanlassDependencyList.vue'
import { taskProgress, type GaAufgabe } from './gaAufgabenMock'
import { KIND_ICON, STATUS_COLOR, dayLabel, statusKey, useGaNow, whenLabel } from './gaAufgabenUi'

const now = useGaNow()
const props = defineProps<{
  task: GaAufgabe
  canAssign?: boolean
  readOnly?: boolean
  assignees?: { value: string; title: string }[]
  /** Serverfehler der letzten Aktion */
  error?: string
  /** Mögliche Vorgänger-Aufgaben für neue Abhängigkeiten */
  dependencyCandidates?: { value: string; title: string }[]
}>()
const emit = defineEmits<{
  status: [status: GaTaskStatus]
  assign: [userId: string | null]
  stepAdd: [label: string]
  stepDone: [stepId: string, done: boolean]
  stepMove: [stepId: string, delta: -1 | 1]
  stepRemove: [stepId: string]
  block: [reason: string]
  unblock: []
  report: [note: string]
  accept: []
  giveBack: [reason: string]
  dependencyAdd: [predecessorTaskId: string, mode: GaDependencyMode]
  dependencyMode: [dependency: GaDependency, mode: GaDependencyMode]
  dependencyRemove: [dependency: GaDependency]
  dependencyRelease: [dependency: GaDependency, reason: string]
}>()
const { t, locale } = useI18n()
const route = useRoute()
const departmentId = computed(() => String(route.params.departmentId || ''))

const assignee = ref<string | null>(null)
const progress = computed(() => taskProgress(props.task))
const assignableItems = computed(() => (props.assignees ?? []).filter((person) => person.value !== props.task.assigneeId))

const waitingText = computed(() => {
  const parts = [...(props.task.waitingOn ?? [])]
  const hidden = props.task.waitingHidden ?? 0
  if (hidden > 0) parts.push(t('grossanlass.aufgaben.deps.waitingHidden', { n: hidden }))
  return t('grossanlass.aufgaben.deps.waitingOn', { names: parts.join(', ') })
})
const newStep = ref('')
const blocking = ref(false)
const blockReason = ref('')
const returning = ref(false)
const returnReason = ref('')

function submitReturn() {
  const reason = returnReason.value.trim()
  if (!reason) return
  emit('giveBack', reason)
  returnReason.value = ''
  returning.value = false
}

function eventTime(iso: string): string {
  return new Date(iso).toLocaleString(locale.value, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function addStep() {
  const label = newStep.value.trim()
  if (!label) return
  emit('stepAdd', label)
  newStep.value = ''
}

function submitBlock() {
  const reason = blockReason.value.trim()
  if (!reason) return
  emit('block', reason)
  blockReason.value = ''
  blocking.value = false
}

function addAssignee() {
  if (!assignee.value) return
  emit('assign', assignee.value)
  assignee.value = null
}
</script>

<style scoped>
.aufgabe-detail__blocked {
  padding: 10px 12px;
  border: 1px solid rgb(var(--v-theme-error));
  border-radius: 8px;
}
.aufgabe-detail__blocked h4 {
  display: flex;
  align-items: center;
  gap: 6px;
  color: rgb(var(--v-theme-error));
}
.aufgabe-detail__rework {
  padding: 8px 12px;
  border: 1px solid rgb(var(--v-theme-info));
  border-radius: 8px;
}
.aufgabe-detail__rework h4 {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 4px;
}
.aufgabe-detail__rework p {
  margin: 0;
}
.aufgabe-detail__order {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  color: #64748b;
  font-size: 0.85rem;
}
.aufgabe-detail__waiting {
  padding: 8px 12px;
  border: 1px solid rgb(var(--v-theme-warning));
  border-radius: 8px;
}
.aufgabe-detail__waiting h4 {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
}
.aufgabe-detail__review {
  padding: 10px 12px;
  border: 1px solid rgb(var(--v-theme-info));
  border-radius: 8px;
}
.aufgabe-history {
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 0.85rem;
}
.aufgabe-history__note {
  color: #64748b;
}
.aufgabe-detail__error {
  color: rgb(var(--v-theme-error));
  font-size: 0.85rem;
}
.aufgabe-checklist {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
}
.aufgabe-checklist li {
  display: flex;
  align-items: center;
  gap: 4px;
}
.aufgabe-checklist :deep(.v-selection-control) {
  flex: 0 0 auto;
}
.aufgabe-checklist li > span:not(.aufgabe-checklist__tools) {
  flex: 1;
}
.aufgabe-checklist__done {
  color: #64748b;
  text-decoration: line-through;
}
.aufgabe-checklist__tools {
  margin-left: auto;
  white-space: nowrap;
}
.aufgabe-checklist__add {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
}
.aufgabe-detail {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.aufgabe-detail__crumbs {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 2px;
  font-size: 0.82rem;
  color: #64748b;
}
.aufgabe-detail__head {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.aufgabe-detail__facts {
  display: grid;
  gap: 14px;
  margin: 0;
}
.aufgabe-detail__facts dt,
.aufgabe-detail__section h4 {
  margin: 0 0 4px;
  font-size: 0.72rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: 600;
}
.aufgabe-detail__facts dd {
  margin: 0;
}
.aufgabe-detail__deadline {
  margin-left: 8px;
  color: #b45309;
  font-weight: 600;
}
.aufgabe-detail__unassigned {
  color: #b45309;
  font-weight: 600;
}
.aufgabe-detail__assign {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
  margin-top: 8px;
}
.aufgabe-detail__section p {
  margin: 0;
}
.aufgabe-progress {
  display: flex;
  align-items: center;
  gap: 12px;
}
.aufgabe-progress :deep(.v-progress-linear) {
  flex: 1 1 auto;
}
.aufgabe-steps,
.aufgabe-materials {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 2px;
}
.aufgabe-materials li {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding: 6px 0;
  border-bottom: 1px solid #f1f5f9;
}
.aufgabe-detail__wait p {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  border-radius: 10px;
  background: #fffbeb;
  color: #92400e;
}
.aufgabe-detail__link {
  margin-left: 10px;
  font-size: 0.86rem;
}
.aufgabe-detail__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
