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
      <v-chip size="small" variant="flat" :color="STATUS_COLOR[statusKey(task)]">
        {{ t(`grossanlass.aufgaben.status.${statusKey(task)}`) }}
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
            @click:close="unassignPerson(task, person)"
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

    <section v-if="blockers.length" class="aufgabe-detail__section aufgabe-detail__wait">
      <p><v-icon icon="mdi-timer-sand" size="16" /> {{ t('grossanlass.aufgaben.detail.waitsFor', { task: blockers.map((entry) => entry.title).join(', ') }) }}</p>
    </section>

    <section v-if="task.helperNeed" class="aufgabe-detail__section aufgabe-detail__need">
      <h4>{{ t('grossanlass.aufgaben.detail.helperNeed') }}</h4>
      <p>
        <strong>{{ t('grossanlass.aufgaben.detail.helperNeedLine', { count: task.helperNeed.count, skill: task.helperNeed.skill, assigned: task.people.length }) }}</strong>
        <router-link v-if="departmentId" :to="`/${departmentId}/ga/helferpool`" class="aufgabe-detail__link">{{ t('grossanlass.aufgaben.detail.toHelferpool') }}</router-link>
      </p>
    </section>

    <section v-if="task.description" class="aufgabe-detail__section">
      <h4>{{ t('grossanlass.aufgaben.detail.description') }}</h4>
      <p>{{ task.description }}</p>
    </section>

    <GrossanlassFahrauftragSteps v-if="task.kind === 'logistik' && task.route" :task="task" :read-only="readOnly" />

    <template v-else>
      <section class="aufgabe-detail__section">
        <h4>{{ t('grossanlass.aufgaben.detail.progress') }}</h4>
        <div class="aufgabe-progress">
          <v-progress-linear :model-value="progress" height="10" rounded color="primary" />
          <span>{{ progress }} %</span>
        </div>
      </section>

      <section v-if="task.steps.length" class="aufgabe-detail__section">
        <h4>{{ t('grossanlass.aufgaben.detail.steps') }}</h4>
        <ul class="aufgabe-steps">
          <li v-for="(step, index) in task.steps" :key="step.label">
            <ECheckbox
              :model-value="step.done"
              :label="step.label"
              hide-details
              :disabled="readOnly"
              @update:model-value="toggleStep(task, index)"
            />
          </li>
        </ul>
      </section>
    </template>

    <section v-if="task.materials.length" class="aufgabe-detail__section">
      <h4>{{ t('grossanlass.aufgaben.detail.materials') }}</h4>
      <ul class="aufgabe-materials">
        <li v-for="item in task.materials" :key="item.label">
          <span>{{ item.qty }}× {{ item.label }}</span>
          <v-chip v-if="item.pack" size="x-small" variant="tonal">{{ item.pack }}</v-chip>
        </li>
      </ul>
    </section>

    <div v-if="!readOnly && !(task.kind === 'logistik' && task.route)" class="aufgabe-detail__actions">
      <EButton v-if="task.status === 'open'" variant="primary" size="large" @click="startTask(task)">
        {{ t('grossanlass.aufgaben.start') }}
      </EButton>
      <EButton v-if="task.status !== 'done'" variant="secondary" size="large" @click="completeTask(task)">
        {{ t('grossanlass.aufgaben.complete') }}
      </EButton>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, ECheckbox, ESelect } from '@/components/form/base'
import GrossanlassFahrauftragSteps from './GrossanlassFahrauftragSteps.vue'
import {
  GA_DEMO_PEOPLE,
  assignPerson,
  blockedBy,
  completeTask,
  startTask,
  taskProgress,
  toggleStep,
  unassignPerson,
  type GaAufgabe,
} from './gaAufgabenMock'
import { KIND_ICON, STATUS_COLOR, dayLabel, statusKey, whenLabel } from './gaAufgabenUi'

const props = defineProps<{ task: GaAufgabe; canAssign?: boolean; readOnly?: boolean }>()
const { t, locale } = useI18n()
const route = useRoute()
const departmentId = computed(() => String(route.params.departmentId || ''))

const assignee = ref<string | null>(null)
const blockers = computed(() => blockedBy(props.task))
const progress = computed(() => taskProgress(props.task))
const assignableItems = computed(() => GA_DEMO_PEOPLE.filter((name) => !props.task.people.includes(name)))

function addAssignee() {
  if (!assignee.value) return
  assignPerson(props.task, assignee.value)
  assignee.value = null
}
</script>

<style scoped>
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
