<template>
  <div class="offen">
    <section class="offen__kpis">
      <div class="kpi"><strong>{{ list.length }}</strong><span>{{ t('grossanlass.ausgabe.open.count') }}</span></div>
      <div class="kpi kpi--warn"><strong>{{ overdueCount }}</strong><span>{{ t('grossanlass.ausgabe.open.overdue') }}</span></div>
      <ESelect v-model="personFilter" :items="personItems" :label="t('grossanlass.ausgabe.open.filterPerson')" clearable hide-details />
    </section>

    <EEmptyState v-if="!shown.length" variant="generic" icon="mdi-check-all" :title="t('grossanlass.ausgabe.open.emptyTitle')" :description="t('grossanlass.ausgabe.open.emptyText')" />

    <ul class="loans">
      <li v-for="loan in shown" :key="loan.id" class="loan" :class="{ 'loan--overdue': isOverdue(loan) }">
        <div class="loan__main">
          <strong>{{ loan.qty }}× {{ loan.name }}</strong>
          <span class="loan__who"><v-icon icon="mdi-account-outline" size="14" /> {{ loan.personName }}</span>
          <span class="loan__meta">
            {{ t('grossanlass.ausgabe.open.since', { when: sinceLabel(loan.since) }) }}
            <template v-if="loan.contextLabel"> · {{ t('grossanlass.ausgabe.order') }}: {{ loan.contextLabel }}</template>
          </span>
          <span class="loan__meta">
            <v-chip v-if="isOverdue(loan)" size="x-small" variant="flat" color="error">{{ t('grossanlass.ausgabe.warn.overdue', { n: 1 }) }}</v-chip>
            <template v-if="loan.dueAt">{{ t('grossanlass.ausgabe.open.due', { when: sinceLabel(loan.dueAt) }) }}</template>
            <template v-else>{{ t('grossanlass.ausgabe.open.noDue') }}</template>
          </span>
        </div>

        <div class="loan__actions">
          <EButton variant="primary" size="large" @click="doReturn(loan.id)">
            <v-icon icon="mdi-keyboard-return" start size="20" /> {{ t('grossanlass.ausgabe.open.return') }}
          </EButton>
          <EButton variant="secondary" size="large" @click="toggle(loan.id, 'transfer')">{{ t('grossanlass.ausgabe.open.transfer') }}</EButton>
          <EButton variant="secondary" size="large" @click="toggle(loan.id, 'reassign')">{{ t('grossanlass.ausgabe.open.reassign') }}</EButton>
        </div>

        <div v-if="panel[loan.id]" class="loan__panel">
          <ESelect
            v-if="panel[loan.id] === 'transfer'"
            v-model="target[loan.id]"
            :items="people.filter((p) => p.id !== loan.personId).map((p) => ({ value: p.id, title: p.name }))"
            :label="t('grossanlass.ausgabe.open.toPerson')"
            hide-details
          />
          <ESelect
            v-else
            v-model="target[loan.id]"
            :items="contextItems"
            :label="t('grossanlass.ausgabe.open.toOrder')"
            hide-details
          />
          <EButton variant="primary" :disabled="!target[loan.id]" @click="apply(loan.id)">{{ t('grossanlass.ausgabe.open.apply') }}</EButton>
        </div>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ESelect } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useToast } from '@/composables/useToast'
import { GA_NO_CONTEXT, isOverdue, openLoans, reassignLoan, returnLoan, transferLoan, useGaAusgabeMock } from './gaAusgabeMock'

const { t, locale } = useI18n()
const toast = useToast()
const { loans, people, contexts } = useGaAusgabeMock()

const personFilter = ref<string | null>(null)
const panel = reactive<Record<string, 'transfer' | 'reassign' | ''>>({})
const target = reactive<Record<string, string | null>>({})

const list = computed(() => {
  void loans.value
  return openLoans()
})
const shown = computed(() => list.value.filter((loan) => !personFilter.value || loan.personName === personFilter.value))
const overdueCount = computed(() => list.value.filter((loan) => isOverdue(loan)).length)
const personItems = computed(() => [...new Set(list.value.map((loan) => loan.personName))])
const contextItems = computed(() => [
  { value: GA_NO_CONTEXT, title: t('grossanlass.ausgabe.noContext') },
  ...contexts.value.map((c) => ({ value: c.id, title: c.label })),
])

function sinceLabel(date: Date): string {
  return date.toLocaleString(locale.value, { weekday: 'short', day: 'numeric', month: 'numeric', hour: '2-digit', minute: '2-digit' })
}
function toggle(id: string, kind: 'transfer' | 'reassign') {
  panel[id] = panel[id] === kind ? '' : kind
  target[id] = null
}
function doReturn(id: string) {
  if (returnLoan(id)) toast.success(t('grossanlass.ausgabe.open.returned'))
}
function apply(id: string) {
  const value = target[id]
  if (!value) return
  const ok = panel[id] === 'transfer' ? transferLoan(id, value) : reassignLoan(id, value === GA_NO_CONTEXT ? '' : value)
  if (ok) {
    toast.success(t('grossanlass.ausgabe.open.done'))
    panel[id] = ''
  }
}
</script>

<style scoped>
.offen {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.offen__kpis {
  display: grid;
  grid-template-columns: repeat(2, minmax(110px, 160px)) minmax(200px, 1fr);
  gap: 10px;
  align-items: center;
}
.kpi {
  display: flex;
  flex-direction: column;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.kpi strong {
  font-size: 1.6rem;
  line-height: 1.1;
}
.kpi span {
  font-size: 0.78rem;
  color: #64748b;
}
.kpi--warn strong {
  color: #b91c1c;
}
.loans {
  display: grid;
  gap: 10px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.loan {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 12px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-left: 4px solid #f59e0b;
  border-radius: 14px;
  background: #fff;
}
.loan--overdue {
  border-left-color: #dc2626;
  background: #fef2f2;
}
.loan__main {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 220px;
  flex: 1 1 300px;
}
.loan__who,
.loan__meta {
  font-size: 0.86rem;
  color: #475569;
}
.loan__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.loan__panel {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
  flex: 1 1 100%;
}
@media (max-width: 720px) {
  .offen__kpis {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .offen__kpis > :last-child {
    grid-column: 1 / -1;
  }
}
</style>
