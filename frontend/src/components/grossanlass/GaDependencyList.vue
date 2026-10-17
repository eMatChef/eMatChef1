<template>
  <div class="ga-deps" data-testid="task-dependencies">
    <p v-if="!dependencies.length" class="ga-deps__empty">{{ t('grossanlass.aufgaben.deps.none') }}</p>
    <ul v-else class="ga-deps__list">
      <li v-for="dep in dependencies" :key="dep.id" :class="{ 'ga-deps__item--open': dep.open }">
        <div class="ga-deps__main">
          <v-icon :icon="dep.open ? 'mdi-timer-sand' : 'mdi-check-circle-outline'" size="16" :color="dep.open ? 'warning' : 'success'" />
          <strong>{{ dep.predecessor_visible ? dep.predecessor_title : t('grossanlass.aufgaben.deps.hidden') }}</strong>
          <v-chip v-if="dep.kind === 'group'" size="x-small" variant="tonal">{{ t('grossanlass.aufgaben.deps.viaOrder') }}</v-chip>
          <v-chip v-if="dep.mode === 'simultaneous'" size="x-small" variant="tonal" color="info" data-testid="dep-mode-simultaneous">
            {{ t('grossanlass.aufgaben.deps.mode.simultaneous') }}
          </v-chip>
          <span class="ga-deps__state">{{ stateLabel(dep) }}</span>
        </div>
        <p
          v-if="dep.mode === 'simultaneous' && dep.schedule"
          class="ga-deps__schedule"
          :class="{ 'ga-deps__schedule--conflict': dep.schedule.conflict !== 'none' }"
          :role="dep.schedule.conflict !== 'none' ? 'alert' : undefined"
          data-testid="dep-schedule"
        >
          <v-icon :icon="dep.schedule.conflict === 'none' ? 'mdi-calendar-check' : 'mdi-calendar-alert'" size="16" />
          {{ scheduleText(dep.schedule) }}
        </p>
        <p class="ga-deps__trace" data-testid="dep-trace">
          {{ traceText(dep) }}
        </p>
        <p v-if="dep.released" class="ga-deps__released">
          {{ t('grossanlass.aufgaben.deps.releasedBy', { name: dep.released.by_name ?? '?' }) }}: {{ dep.released.reason }}
        </p>
        <div v-if="canEdit" class="ga-deps__actions">
          <EButton v-if="dep.open && dep.mode !== 'simultaneous'" variant="secondary" size="small" @click="startRelease(dep)">{{ t('grossanlass.aufgaben.deps.release') }}</EButton>
          <EButton
            v-if="dep.kind === 'task'"
            variant="secondary"
            size="small"
            data-testid="dep-switch-mode"
            @click="emit('changeMode', dep, dep.mode === 'simultaneous' ? 'sequential' : 'simultaneous')"
          >
            {{ dep.mode === 'simultaneous' ? t('grossanlass.aufgaben.deps.switchToSequential') : t('grossanlass.aufgaben.deps.switchToSimultaneous') }}
          </EButton>
          <EButton variant="secondary" size="small" @click="emit('remove', dep)">{{ t('grossanlass.aufgaben.deps.remove') }}</EButton>
        </div>
        <div v-if="releasing === dep.id" class="ga-deps__release">
          <ETextField v-model="reason" :label="t('grossanlass.aufgaben.deps.reason')" density="compact" hide-details />
          <EButton variant="primary" size="small" :disabled="!reason.trim()" @click="submitRelease(dep)">{{ t('grossanlass.aufgaben.deps.releaseSubmit') }}</EButton>
          <EButton variant="secondary" size="small" @click="releasing = null">{{ t('common.cancel') }}</EButton>
        </div>
      </li>
    </ul>
    <div v-if="canEdit && candidates.length" class="ga-deps__add">
      <ESelect
        v-if="allowSimultaneous"
        v-model="newMode"
        :items="modeItems"
        :label="t('grossanlass.aufgaben.deps.mode.label')"
        density="compact"
        hide-details
        data-testid="dep-mode-select"
      />
      <ESelect v-model="selected" :items="candidates" :label="newMode === 'simultaneous' ? t('grossanlass.aufgaben.deps.addSimultaneous') : t('grossanlass.aufgaben.deps.add')" density="compact" hide-details clearable data-testid="dep-candidate-select" />
      <EButton variant="secondary" size="small" :disabled="!selected" data-testid="dep-add-submit" @click="add">{{ t('grossanlass.aufgaben.deps.addButton') }}</EButton>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ESelect, ETextField } from '@/components/form/base'
import type { GaDependency, GaDependencyMode, GaDependencySchedule } from '@/api/grossanlassBauprojekt'

/** Abhängigkeiten anzeigen, vorzeitig freigeben (mit Begründung), entfernen und hinzufügen. Rechte setzt der Server durch. */
const props = defineProps<{
  dependencies: GaDependency[]
  canEdit?: boolean
  /** Art «gleichzeitig» anbieten (nur Aufgaben, nicht Bauaufträge). */
  allowSimultaneous?: boolean
  /** Mögliche Vorgänger (Aufgaben bzw. Bauaufträge). */
  candidates: { value: string; title: string }[]
}>()
const emit = defineEmits<{
  add: [predecessorId: string, mode: GaDependencyMode]
  changeMode: [dependency: GaDependency, mode: GaDependencyMode]
  remove: [dependency: GaDependency]
  release: [dependency: GaDependency, reason: string]
}>()
const { t } = useI18n()
const selected = ref<string | null>(null)
const newMode = ref<GaDependencyMode>('sequential')
const modeItems = computed(() => [
  { value: 'sequential', title: t('grossanlass.aufgaben.deps.mode.sequential') },
  { value: 'simultaneous', title: t('grossanlass.aufgaben.deps.mode.simultaneous') },
])
const releasing = ref<string | null>(null)
const reason = ref('')

function stateLabel(dep: GaDependency): string {
  if (dep.mode === 'simultaneous') return t('grossanlass.aufgaben.deps.stateSimultaneous')
  if (dep.released) return t('grossanlass.aufgaben.deps.stateReleased')
  // Bauauftrag als Vorgänger: vorläufige Bedingung «alle Aufgaben erledigt», keine formelle Gesamtabnahme.
  const group = dep.kind === 'group' ? 'Group' : ''
  return dep.open ? t(`grossanlass.aufgaben.deps.stateWaiting${group}`) : t(`grossanlass.aufgaben.deps.stateDone${group}`)
}
function add() {
  if (!selected.value) return
  emit('add', selected.value, props.allowSimultaneous ? newMode.value : 'sequential')
  selected.value = null
}
function formatSlot(slot: GaDependencySchedule['own']): string {
  if (!slot.starts_at) return t('grossanlass.aufgaben.deps.schedule.noTime')
  const start = new Date(slot.starts_at)
  const text = start.toLocaleString('de-CH', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
  return slot.duration_minutes ? `${text} (${slot.duration_minutes} min)` : text
}
/** Zeitvergleich bei «gleichzeitig»; nur Anzeige, die andere Aufgabe wird nie automatisch geändert. */
function scheduleText(schedule: GaDependencySchedule): string {
  const slots = { own: formatSlot(schedule.own), partner: formatSlot(schedule.partner) }
  return t(`grossanlass.aufgaben.deps.schedule.${schedule.conflict}`, slots)
}
function traceText(dep: GaDependency): string {
  const parts = [t('grossanlass.aufgaben.deps.trace.created', { name: dep.created_by_name ?? '?', at: new Date(dep.created_at).toLocaleDateString('de-CH') })]
  if (dep.incoming) parts.push(t('grossanlass.aufgaben.deps.trace.incoming'))
  if (dep.mode_changed) {
    parts.push(t('grossanlass.aufgaben.deps.trace.modeChanged', { name: dep.mode_changed.by_name ?? '?', at: new Date(dep.mode_changed.at).toLocaleDateString('de-CH') }))
  }
  return parts.join(' · ')
}
function startRelease(dep: GaDependency) {
  releasing.value = dep.id
  reason.value = ''
}
function submitRelease(dep: GaDependency) {
  const text = reason.value.trim()
  if (!text) return
  emit('release', dep, text)
  releasing.value = null
}
</script>

<style scoped>
.ga-deps__empty {
  margin: 0;
  color: #64748b;
  font-size: 0.85rem;
}
.ga-deps__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.ga-deps__main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.ga-deps__state {
  color: #64748b;
  font-size: 0.82rem;
}
.ga-deps__schedule {
  display: flex;
  align-items: center;
  gap: 4px;
  margin: 2px 0 0;
  font-size: 0.82rem;
  color: #64748b;
}
.ga-deps__schedule--conflict {
  color: rgb(var(--v-theme-warning));
}
.ga-deps__trace {
  margin: 2px 0 0;
  font-size: 0.75rem;
  color: #94a3b8;
}
.ga-deps__released {
  margin: 2px 0 0;
  font-size: 0.82rem;
  color: #64748b;
}
.ga-deps__actions,
.ga-deps__release,
.ga-deps__add {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
}
.ga-deps__add {
  margin-top: 10px;
}
</style>
