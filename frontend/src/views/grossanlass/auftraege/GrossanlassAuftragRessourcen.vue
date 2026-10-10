<template>
  <section class="res">
    <header class="res__head">
      <h4>{{ t('grossanlass.auftraege.res.title') }}</h4>
      <span class="res__summary">
        {{ t('grossanlass.auftraege.res.summary', counts) }}
      </span>
    </header>
    <p v-if="!needs.length" class="res__empty">{{ t('grossanlass.auftraege.res.empty') }}</p>

    <article v-for="need in needs" :key="need.id" class="need" :class="`need--${need.status}`">
      <div class="need__top">
        <span class="need__label">
          <v-icon :icon="RES_ICON[need.type]" size="20" />
          <strong>{{ need.qty > 1 ? `${need.qty}× ` : '' }}{{ need.label }}</strong>
          <small>{{ t(`grossanlass.auftraege.res.type.${need.type}`) }}</small>
        </span>
        <span class="need__chips">
          <v-chip size="x-small" variant="flat" :color="RES_STATUS_COLOR[need.status]">{{ t(`grossanlass.auftraege.res.status.${need.status}`) }}</v-chip>
          <v-chip size="x-small" variant="outlined">{{ need.flexible ? t('grossanlass.auftraege.res.flexible') : t('grossanlass.auftraege.res.fixed') }}</v-chip>
        </span>
      </div>

      <dl class="need__facts">
        <div><dt>{{ t('grossanlass.auftraege.res.duration') }}</dt><dd>{{ need.durationH }} h</dd></div>
        <div><dt>{{ t('grossanlass.auftraege.res.possible') }}</dt><dd>{{ rangeLabel(need.earliest, need.latest, locale) }}</dd></div>
        <div v-if="need.wish"><dt>{{ t('grossanlass.auftraege.res.wish') }}</dt><dd>{{ rangeLabel(need.wish.from, need.wish.to, locale) }}</dd></div>
        <div v-if="need.note"><dt>{{ t('grossanlass.auftraege.res.note') }}</dt><dd>{{ need.note }}</dd></div>
      </dl>

      <p v-if="blockerOf(need).length" class="need__dep">
        <v-icon icon="mdi-link-variant" size="14" />
        {{ t('grossanlass.auftraege.res.after', { task: blockerOf(need).map((task) => task.title).join(', ') }) }}
      </p>
      <p v-else-if="need.dependsOnTaskId" class="need__dep need__dep--ok">
        <v-icon icon="mdi-link-variant" size="14" />
        {{ t('grossanlass.auftraege.res.afterDone', { task: taskById(need.dependsOnTaskId)?.title ?? '' }) }}
      </p>

      <div v-if="need.status === 'proposal' && need.proposal" class="need__proposal">
        <p class="need__reason"><v-icon icon="mdi-lightbulb-on-outline" size="16" /> {{ need.proposal.reason }}</p>
        <p class="need__slot">{{ t('grossanlass.auftraege.res.proposal') }}: <strong>{{ rangeLabel(need.proposal.from, need.proposal.to, locale) }}</strong></p>
        <p class="need__demo">{{ t('grossanlass.auftraege.res.demoNote') }}</p>
        <div class="need__actions">
          <EButton variant="primary" size="small" @click="accept(need.id)">{{ t('grossanlass.auftraege.res.accept') }}</EButton>
          <EButton variant="secondary" size="small" @click="toggleOther(need.id)">{{ t('grossanlass.auftraege.res.otherTime') }}</EButton>
          <EButton variant="text" size="small" @click="open(need.id)">{{ t('grossanlass.auftraege.res.leaveOpen') }}</EButton>
        </div>
      </div>

      <div v-else-if="need.status === 'planned' && need.planned" class="need__planned">
        <p class="need__slot"><v-icon icon="mdi-check-circle" size="16" color="success" /> {{ t('grossanlass.auftraege.res.plannedAt') }}: <strong>{{ rangeLabel(need.planned.from, need.planned.to, locale) }}</strong></p>
        <div class="need__actions">
          <EButton variant="secondary" size="small" @click="toggleOther(need.id)">{{ t('grossanlass.auftraege.res.otherTime') }}</EButton>
          <EButton variant="text" size="small" @click="open(need.id)">{{ t('grossanlass.auftraege.res.reopen') }}</EButton>
        </div>
      </div>

      <div v-else class="need__actions">
        <EButton variant="secondary" size="small" @click="toggleOther(need.id)">{{ t('grossanlass.auftraege.res.planTime') }}</EButton>
        <router-link v-if="need.type === 'helper'" :to="`/${departmentId}/ga/helper-pool`" class="need__link">{{ t('grossanlass.auftraege.res.toHelferpool') }}</router-link>
      </div>

      <div v-if="otherOpen[need.id]" class="need__other">
        <ETextField v-model="otherValue[need.id]" type="datetime-local" :label="t('grossanlass.auftraege.res.start')" hide-details />
        <EButton variant="primary" :disabled="!otherValue[need.id]" @click="applyOther(need.id)">{{ t('grossanlass.auftraege.res.apply') }}</EButton>
        <ul v-if="issues[need.id]?.length" class="need__issues">
          <li v-for="entry in issues[need.id]" :key="entry.issue + (entry.where ?? '')">
            <v-icon icon="mdi-alert-outline" size="14" /> {{ t(`grossanlass.auftraege.res.issue.${entry.issue}`, { where: entry.where ?? '' }) }}
          </li>
        </ul>
      </div>
    </article>
  </section>
</template>

<script setup lang="ts">
import { computed, reactive } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, ETextField } from '@/components/form/base'
import { blockedBy, taskById } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import {
  acceptProposal,
  leaveOpen,
  needsOfOrder,
  planAtOtherTime,
  useGaRessourcenMock,
  type GaResourceNeed,
  type GaResourceStatus,
  type GaResourceType,
  type GaSlotIssue,
} from '@/views/grossanlass/ressourcen/gaRessourcenMock'
import { fromInput, rangeLabel } from './gaAuftraegeUi'

const props = defineProps<{ orderId: string }>()
const { t, locale } = useI18n()
const route = useRoute()
const { needs: allNeeds } = useGaRessourcenMock()

const RES_ICON: Record<GaResourceType, string> = {
  crane: 'mdi-crane',
  forklift: 'mdi-forklift',
  machine: 'mdi-cog-outline',
  vehicle: 'mdi-truck-outline',
  trailer: 'mdi-truck-trailer',
  tool: 'mdi-tools',
  helper: 'mdi-account-group-outline',
}
const RES_STATUS_COLOR: Record<GaResourceStatus, string> = { need: 'warning', proposal: 'info', planned: 'success' }

const departmentId = computed(() => String(route.params.departmentId || ''))
const needs = computed(() => {
  void allNeeds.value
  return needsOfOrder(props.orderId)
})
const counts = computed(() => ({
  need: needs.value.filter((entry) => entry.status === 'need').length,
  proposal: needs.value.filter((entry) => entry.status === 'proposal').length,
  planned: needs.value.filter((entry) => entry.status === 'planned').length,
}))

const otherOpen = reactive<Record<string, boolean>>({})
const otherValue = reactive<Record<string, string>>({})
const issues = reactive<Record<string, Array<{ issue: GaSlotIssue; where?: string }>>>({})

function blockerOf(need: GaResourceNeed) {
  return need.dependsOnTaskId ? blockedBy({ dependsOn: [need.dependsOnTaskId] }) : []
}
function accept(id: string) {
  acceptProposal(id)
  otherOpen[id] = false
}
function open(id: string) {
  leaveOpen(id)
  otherOpen[id] = false
}
function toggleOther(id: string) {
  otherOpen[id] = !otherOpen[id]
  issues[id] = []
}
function applyOther(id: string) {
  const from = fromInput(otherValue[id] ?? '')
  if (!from) return
  const need = allNeeds.value.find((entry) => entry.id === id)
  const dependencyEnd = need?.dependsOnTaskId ? taskById(need.dependsOnTaskId)?.endsAt : null
  const result = planAtOtherTime(id, from, dependencyEnd)
  issues[id] = result.issues
  if (result.ok) otherOpen[id] = false
}
</script>

<style scoped>
.res {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.res__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 6px;
}
.res__head h4 {
  margin: 0;
  font-size: 0.72rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748b;
}
.res__summary,
.res__empty {
  font-size: 0.82rem;
  color: #64748b;
}
.need {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-left: 4px solid #f59e0b;
  border-radius: 12px;
  background: #fff;
}
.need--proposal {
  border-left-color: #0ea5e9;
}
.need--planned {
  border-left-color: #16a34a;
}
.need__top {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 8px;
}
.need__label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.need__label small {
  color: #64748b;
}
.need__chips {
  display: inline-flex;
  gap: 4px;
}
.need__facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 8px;
  margin: 0;
}
.need__facts dt {
  font-size: 0.68rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748b;
}
.need__facts dd {
  margin: 0;
  font-size: 0.88rem;
}
.need__dep {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  padding: 6px 10px;
  border-radius: 8px;
  background: #fffbeb;
  color: #92400e;
  font-size: 0.84rem;
}
.need__dep--ok {
  background: #f0fdf4;
  color: #166534;
}
.need__proposal,
.need__planned {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #f0f9ff;
}
.need__planned {
  background: #f0fdf4;
}
.need__reason,
.need__slot,
.need__demo {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 0.88rem;
}
.need__demo {
  color: #64748b;
  font-size: 0.78rem;
}
.need__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.need__link {
  font-size: 0.86rem;
}
.need__other {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
}
.need__issues {
  grid-column: 1 / -1;
  margin: 0;
  padding: 0;
  list-style: none;
  color: #b91c1c;
  font-size: 0.84rem;
}
</style>
