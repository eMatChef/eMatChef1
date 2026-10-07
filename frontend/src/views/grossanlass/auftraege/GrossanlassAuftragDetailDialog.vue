<template>
  <EDialog v-model="open" :title="order?.title ?? ''" max-width="820" :retain-focus="false">
    <div v-if="order" class="detail">
      <div class="detail__chips">
        <v-chip size="small" variant="flat" :color="TYPE_COLOR[order.type]" :prepend-icon="TYPE_ICON[order.type]">{{ t(`grossanlass.auftraege.type.${order.type}`) }}</v-chip>
        <v-chip size="small" variant="flat" :color="STATUS_COLOR[order.status]">{{ t(`grossanlass.auftraege.status.${order.status}`) }}</v-chip>
      </div>
      <p class="detail__crumb">{{ order.ressort }} › {{ order.bereich }}<template v-if="order.build"> › {{ order.build.project }}</template></p>
      <p v-if="order.description">{{ order.description }}</p>

      <section class="block block--times">
        <h4><v-icon icon="mdi-clock-outline" size="16" /> {{ t('grossanlass.auftraege.times.title') }}</h4>
        <dl class="facts">
          <div><dt>{{ t('grossanlass.auftraege.times.earliest') }}</dt><dd>{{ order.earliestStart ? stamp(order.earliestStart) : '–' }}</dd></div>
          <div><dt>{{ t('grossanlass.auftraege.times.deadline') }}</dt><dd>{{ order.deadline ? stamp(order.deadline) : '–' }}</dd></div>
          <div><dt>{{ t('grossanlass.auftraege.times.wished') }}</dt><dd>{{ order.wished ? rangeLabel(order.wished.from, order.wished.to, locale) : '–' }}</dd></div>
          <div><dt>{{ t('grossanlass.auftraege.times.planned') }}</dt><dd>{{ rangeLabel(order.startsAt, order.endsAt, locale) }}</dd></div>
          <template v-if="order.build">
            <div><dt>{{ t('grossanlass.auftraege.times.buildWindow') }}</dt><dd>{{ rangeLabel(order.build.buildFrom, order.build.buildTo, locale) }}</dd></div>
            <div><dt>{{ t('grossanlass.auftraege.times.readyBy') }}</dt><dd>{{ order.build.readyBy ? stamp(order.build.readyBy) : '–' }}</dd></div>
            <div><dt>{{ t('grossanlass.auftraege.times.teardownWindow') }}</dt><dd>{{ rangeLabel(order.build.teardownFrom, order.build.teardownTo, locale) }}</dd></div>
          </template>
        </dl>
        <p v-for="issue in timeIssues" :key="issue" class="issue"><v-icon icon="mdi-alert-outline" size="14" /> {{ t(`grossanlass.auftraege.times.issue.${issue}`) }}</p>
      </section>

      <dl class="facts">
        <div v-if="order.location"><dt>{{ t('grossanlass.auftraege.f.location') }}</dt><dd>{{ order.location }}</dd></div>
        <div><dt>{{ t('grossanlass.auftraege.f.responsible') }}</dt><dd>{{ order.responsible.join(', ') || '–' }}</dd></div>
        <div>
          <dt>{{ t('grossanlass.auftraege.f.helpers') }}</dt>
          <dd>
            <template v-if="order.helperNeed.length">{{ order.helperNeed.map((need) => `${need.count}× ${need.skill}`).join(', ') }} · {{ t('grossanlass.auftraege.staffed', { assigned: staffing.assigned, need: staffing.need }) }}</template>
            <template v-else>–</template>
          </dd>
        </div>
      </dl>

      <section v-if="order.build" class="block block--build">
        <h4><v-icon icon="mdi-hammer-wrench" size="16" /> {{ t('grossanlass.auftraege.create.buildSection') }}</h4>
        <dl class="facts">
          <div><dt>{{ t('grossanlass.auftraege.f.site') }}</dt><dd>{{ order.build.site }}</dd></div>
          <div><dt>{{ t('grossanlass.auftraege.f.machines') }}</dt><dd>{{ order.build.machines.join(', ') || '–' }}</dd></div>
          <div>
            <dt>{{ t('grossanlass.auftraege.f.teardown') }}</dt>
            <dd>{{ rangeLabel(order.build.teardownFrom, order.build.teardownTo, locale) }}<br><small>{{ order.build.teardownInfo }}</small></dd>
          </div>
        </dl>
        <div class="progress">
          <span>{{ t('grossanlass.auftraege.materialProgress') }}</span>
          <v-progress-linear :model-value="material ?? 0" height="8" rounded color="primary" /><b>{{ material === null ? '–' : `${material} %` }}</b>
        </div>
        <div class="progress">
          <span>{{ t('grossanlass.auftraege.buildProgress') }}</span>
          <v-progress-linear :model-value="progress" height="8" rounded color="success" /><b>{{ progress }} %</b>
        </div>
      </section>

      <GrossanlassAuftragAbhaengigkeiten :tasks="tasks" />
      <GrossanlassAuftragRessourcen :order-id="order.id" />

      <div class="cols">
        <section v-if="order.material.length"><h4>{{ t('grossanlass.auftraege.f.material') }}</h4><ul><li v-for="line in order.material" :key="line">{{ line }}</li></ul></section>
        <section v-if="order.tools.length"><h4>{{ t('grossanlass.auftraege.f.tools') }}</h4><ul><li v-for="line in order.tools" :key="line">{{ line }}</li></ul></section>
        <section v-if="order.transport"><h4>{{ t('grossanlass.auftraege.f.transport') }}</h4><p>{{ order.transport.from }} → {{ order.transport.to }}<br><small>{{ order.transport.note }}</small></p></section>
      </div>

      <section class="block">
        <h4>{{ t('grossanlass.auftraege.links.title') }}</h4>
        <ul class="links">
          <li>
            <router-link :to="`/${departmentId}/tasks/aufgaben`"><v-icon icon="mdi-clipboard-list" size="16" /> {{ t('grossanlass.auftraege.links.tasks', { n: tasks.length }) }}</router-link>
            <ul class="links__sub"><li v-for="task in tasks" :key="task.id">{{ task.title }} · {{ taskProgress(task) }} %</li></ul>
          </li>
          <li><router-link :to="`/${departmentId}/helferpool`"><v-icon icon="mdi-account-group-outline" size="16" /> {{ t('grossanlass.auftraege.links.helpers', { assigned: staffing.assigned, need: staffing.need }) }}</router-link></li>
          <li><router-link :to="`/${departmentId}/material/pack`"><v-icon icon="mdi-package-variant-closed" size="16" /> {{ t('grossanlass.auftraege.links.pack') }}</router-link></li>
          <li><router-link :to="`/${departmentId}/material/ausgabe`"><v-icon icon="mdi-export-variant" size="16" /> {{ t('grossanlass.auftraege.links.ausgabe') }}</router-link></li>
          <li><router-link :to="`/${departmentId}/logistik/disposition`"><v-icon icon="mdi-truck-fast-outline" size="16" /> {{ t('grossanlass.auftraege.links.logistics', transport) }}</router-link></li>
        </ul>
      </section>

      <div class="actions">
        <EButton v-for="next in nextStatuses" :key="next" variant="secondary" size="small" @click="setOrderStatus(order.id, next)">
          {{ t(`grossanlass.auftraege.setStatus.${next}`) }}
        </EButton>
      </div>
    </div>
    <template #actions><EButton variant="secondary" @click="open = false">{{ t('common.close') }}</EButton></template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog } from '@/components/form/base'
import { taskProgress } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import GrossanlassAuftragAbhaengigkeiten from './GrossanlassAuftragAbhaengigkeiten.vue'
import GrossanlassAuftragRessourcen from './GrossanlassAuftragRessourcen.vue'
import { buildProgress, deadlineIssues, helperStaffing, linkedTasks, materialProgress, orderById, setOrderStatus, transportStatus, type GaOrderStatus } from './gaAuftraegeMock'
import { STATUS_COLOR, TYPE_COLOR, TYPE_ICON, rangeLabel } from './gaAuftraegeUi'

const props = defineProps<{ modelValue: boolean; orderId: string | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { t, locale } = useI18n()
const route = useRoute()

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const departmentId = computed(() => String(route.params.departmentId || ''))
const order = computed(() => orderById(props.orderId))
const tasks = computed(() => (order.value ? linkedTasks(order.value) : []))
const staffing = computed(() => (order.value ? helperStaffing(order.value) : { need: 0, assigned: 0, people: [] as string[] }))
const timeIssues = computed(() => (order.value ? deadlineIssues(order.value) : []))
function stamp(date: Date): string {
  return date.toLocaleString(locale.value, { weekday: 'short', day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
}
const progress = computed(() => (order.value ? buildProgress(order.value) : 0))
const material = computed(() => (order.value ? materialProgress(order.value) : null))
const transport = computed(() => (order.value ? transportStatus(order.value) : { open: 0, underway: 0, done: 0 }))
const FLOW: Record<GaOrderStatus, GaOrderStatus[]> = { draft: ['planned'], planned: ['active'], active: ['done'], done: [] }
const nextStatuses = computed(() => (order.value ? FLOW[order.value.status] : []))
</script>

<style scoped>
.detail {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.detail__chips {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.detail__crumb {
  margin: 0;
  color: #64748b;
  font-size: 0.86rem;
}
.facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 10px;
  margin: 0;
}
.facts dt,
h4 {
  margin: 0 0 4px;
  font-size: 0.72rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748b;
  font-weight: 600;
}
.facts dd {
  margin: 0;
}
.block {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fafafa;
}
.block--times {
  background: #f8fafc;
}
.issue {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  color: #b91c1c;
  font-size: 0.86rem;
}
.block--build {
  border-color: #fdba74;
  background: #fff7ed;
}
.progress {
  display: grid;
  grid-template-columns: 130px minmax(0, 1fr) 48px;
  gap: 10px;
  align-items: center;
  font-size: 0.84rem;
}
.cols {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}
.cols ul,
.links,
.links__sub {
  margin: 0;
  padding-left: 18px;
}
.links {
  list-style: none;
  padding-left: 0;
  display: grid;
  gap: 6px;
}
.links__sub {
  font-size: 0.82rem;
  color: #64748b;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
@media (max-width: 560px) {
  .progress {
    grid-template-columns: 1fr;
  }
}
</style>
