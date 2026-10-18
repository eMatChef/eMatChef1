<template>
  <section class="needs">
    <div class="needs__filters">
      <ESelect v-model="groupFilter" :items="groupItems" :label="t('grossanlass.helferpool.needs.group')" clearable hide-details />
      <ESwitch v-model="onlyOpen" :label="t('grossanlass.helferpool.needs.onlyOpen')" hide-details />
    </div>

    <EEmptyState
      v-if="!visibleGroups.length"
      variant="generic"
      icon="mdi-clipboard-account-outline"
      :title="t('grossanlass.helferpool.needs.emptyTitle')"
      :description="t('grossanlass.helferpool.needs.emptyText')"
    />

    <article v-for="group in visibleGroups" :key="group.id" class="group">
      <header class="group__head">
        <h3>{{ group.name }}</h3>
        <span class="group__sum">
          {{ t('grossanlass.helferpool.needs.groupSum', { required: group.rollup?.required_total ?? 0, peak: group.rollup?.peak_required ?? 0 }) }}
        </span>
        <v-chip size="x-small" variant="flat" :color="fillColor(group.rollup?.fill_ratio ?? null)">{{ percent(group.rollup?.fill_ratio ?? null) }}</v-chip>
      </header>

      <ul class="rows">
        <li v-for="need in group.needs" :key="need.id" class="row" :class="`row--${needState(need)}`">
          <div class="row__main">
            <div class="row__title">
              <strong>{{ need.task_title ?? t('grossanlass.helferpool.needs.projectNeed') }}</strong>
              <v-chip v-if="need.label" size="x-small" variant="tonal" color="primary">{{ need.label }}</v-chip>
              <v-chip size="x-small" variant="flat" :color="NEED_STATE_COLOR[needState(need)]">{{ t(`grossanlass.helferpool.needs.state.${needState(need)}`) }}</v-chip>
            </div>
            <p class="row__when">
              <template v-if="need.dated && need.starts_at && need.ends_at">{{ windowLabel(need.starts_at, need.ends_at, locale) }}</template>
              <template v-else>{{ t('grossanlass.helferpool.needs.undatedHint') }}</template>
            </p>
            <div v-if="need.coverage" class="row__bar" :title="percent(need.coverage.fill_ratio)">
              <span class="row__fill" :style="{ width: `${Math.round((need.coverage.fill_ratio ?? 0) * 100)}%` }" />
            </div>
            <ul v-if="need.coverage?.gaps.length" class="row__gaps">
              <li v-for="gap in need.coverage.gaps" :key="gap.from">
                {{ t('grossanlass.helferpool.needs.gap', { from: clock(gap.from), to: clock(gap.to), missing: gap.missing }) }}
              </li>
            </ul>
          </div>
          <div class="row__side">
            <strong class="row__count">{{ t('grossanlass.helferpool.needs.count', { assigned: need.assigned_count, required: need.required_count }) }}</strong>
            <span class="row__fillpct">{{ percent(need.coverage?.fill_ratio ?? null) }}</span>
            <div v-if="canManage" class="row__buttons">
              <EButton size="small" variant="primary" :disabled="!need.dated" @click="emit('assign', need)">{{ t('grossanlass.helferpool.assign.button') }}</EButton>
              <EButton size="small" variant="text" @click="toggle(need)">
                {{ expanded === need.id ? t('grossanlass.helferpool.needs.hideAssignments') : t('grossanlass.helferpool.needs.showAssignments') }}
              </EButton>
            </div>
          </div>

          <div v-if="canManage && expanded === need.id" class="row__detail">
            <p v-if="!details[need.id]" class="muted">{{ t('common.loading') }}</p>
            <template v-else>
              <p v-if="!details[need.id]!.assignments.length" class="muted">{{ t('grossanlass.helferpool.noAssignments') }}</p>
              <ul class="assigned">
                <li v-for="entry in details[need.id]!.assignments" :key="entry.id">
                  <span>
                    <strong>{{ entry.helper_name }}</strong>
                    <small>{{ windowLabel(entry.starts_at, entry.ends_at, locale) }}</small>
                    <v-chip v-if="entry.status === 'conflict'" size="x-small" variant="flat" color="error" prepend-icon="mdi-alert-outline" :title="t('grossanlass.helferpool.api.conflict.' + (entry.conflict?.detail ?? 'shift_changed'), { name: entry.helper_name, when: windowLabel(entry.starts_at, entry.ends_at, locale) })" data-testid="ga-assignment-conflict">{{ t('grossanlass.helferpool.assignmentConflict') }}</v-chip>
                    <v-chip v-if="entry.override_reason" size="x-small" variant="tonal" color="warning" :title="entry.override_reason">{{ t('grossanlass.helferpool.override') }}</v-chip>
                  </span>
                  <span class="assigned__buttons">
                    <v-btn icon="mdi-pencil-outline" size="x-small" variant="text" :aria-label="t('common.edit')" @click="emit('edit', need, entry)" />
                    <v-btn icon="mdi-account-remove-outline" size="x-small" variant="text" :aria-label="t('grossanlass.helferpool.unassign')" @click="emit('cancel', entry.id)" />
                  </span>
                </li>
              </ul>
            </template>
          </div>
        </li>
      </ul>
    </article>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { GaNeedAssignment, GaStaffingGroupRow, GaStaffingNeedDetail, GaStaffingNeedRow } from '@/api/grossanlassStaffing'
import { EButton, ESelect, ESwitch } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { NEED_STATE_COLOR, clock, needState, percent, windowLabel } from './gaHelferUi'

/**
 * Personalbedarf je Bauprojekt/Auftrag: benötigte und zugeteilte Personen, zeitabhängige Deckung und Lücken. Die Zahlen kommen
 * aus der Übersicht und enthalten keine Helfernamen; Namen und Zuteilen gibt es nur für GA-HV/GA-SUBHV (`canManage`).
 */
const props = defineProps<{
  needs: GaStaffingNeedRow[]
  groups: GaStaffingGroupRow[]
  groupNames: Record<string, string>
  canManage: boolean
  details: Record<string, GaStaffingNeedDetail>
}>()
const emit = defineEmits<{
  assign: [need: GaStaffingNeedRow]
  edit: [need: GaStaffingNeedRow, assignment: GaNeedAssignment]
  cancel: [assignmentId: string]
  'load-detail': [needId: string]
}>()
const { t, locale } = useI18n()

const groupFilter = ref<string | null>(null)
const onlyOpen = ref(false)
const expanded = ref('')

const groupItems = computed(() => props.groups.map((g) => ({ value: g.group_id, title: props.groupNames[g.group_id] ?? g.group_id })))

const visibleGroups = computed(() => {
  const rollups = new Map(props.groups.map((g) => [g.group_id, g]))
  const byGroup = new Map<string, GaStaffingNeedRow[]>()
  for (const need of props.needs) {
    if (need.required_count <= 0 && !need.assigned_count) continue
    if (groupFilter.value && need.group_id !== groupFilter.value) continue
    if (onlyOpen.value && ['covered', 'over'].includes(needState(need))) continue
    byGroup.set(need.group_id, [...(byGroup.get(need.group_id) ?? []), need])
  }
  return [...byGroup.entries()]
    .map(([id, needs]) => ({ id, name: props.groupNames[id] ?? id, needs, rollup: rollups.get(id) }))
    .sort((a, b) => a.name.localeCompare(b.name))
})

function fillColor(ratio: number | null): string {
  if (ratio === null) return 'grey'
  return ratio >= 1 ? 'success' : ratio > 0 ? 'warning' : 'error'
}

function toggle(need: GaStaffingNeedRow) {
  expanded.value = expanded.value === need.id ? '' : need.id
  if (expanded.value) emit('load-detail', need.id)
}
</script>

<style scoped>
.needs {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.needs__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
}
.needs__filters > :first-child {
  flex: 1 1 260px;
  max-width: 360px;
}
.group {
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #fff;
  overflow: hidden;
}
.group__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: #f0fdf4;
  border-bottom: 1px solid #d1fae5;
}
.group__head h3 {
  margin: 0;
  font-size: 1rem;
  flex: 1 1 auto;
}
.group__sum {
  color: #64748b;
  font-size: 0.85rem;
}
.rows {
  margin: 0;
  padding: 0;
  list-style: none;
}
.row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px 16px;
  padding: 12px 14px;
  border-top: 1px solid #f1f5f9;
}
.row:first-child {
  border-top: 0;
}
.row__title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.row__when {
  margin: 2px 0 6px;
  color: #64748b;
  font-size: 0.85rem;
}
.row__bar {
  height: 6px;
  max-width: 320px;
  border-radius: 999px;
  background: #e5e7eb;
  overflow: hidden;
}
.row__fill {
  display: block;
  height: 100%;
  background: #059669;
}
.row--open .row__fill,
.row--partial .row__fill {
  background: #d97706;
}
.row__gaps {
  margin: 6px 0 0;
  padding-left: 16px;
  color: #b45309;
  font-size: 0.82rem;
}
.row__side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}
.row__count {
  font-size: 1.05rem;
}
.row__fillpct {
  color: #64748b;
  font-size: 0.8rem;
}
.row__buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: flex-end;
}
.row__detail {
  grid-column: 1 / -1;
  padding: 8px 10px;
  border-radius: 10px;
  background: #f8fafc;
}
.assigned {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 6px;
}
.assigned li {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: center;
}
.assigned small {
  margin-left: 8px;
  color: #64748b;
}
.muted {
  margin: 0;
  color: #94a3b8;
  font-size: 0.86rem;
}
@media (max-width: 600px) {
  .row {
    grid-template-columns: 1fr;
  }
  .row__side {
    align-items: flex-start;
  }
}
</style>
