<template>
  <section v-if="state?.applicable" class="ga-order-acceptance" data-testid="order-acceptance">
    <h4>
      {{ t('grossanlass.aufgaben.acceptance.title') }}
      <v-chip size="x-small" variant="flat" :color="COLOR[state.status]" data-testid="order-acceptance-status">
        {{ t(`grossanlass.aufgaben.acceptance.status.${state.status}`) }}
      </v-chip>
    </h4>
    <p class="ga-order-acceptance__progress">
      {{ t('grossanlass.aufgaben.acceptance.progress', { done: state.tasks_done, total: state.tasks_total }) }}
    </p>
    <p v-if="state.status === 'open' && !state.eligible" class="ga-order-acceptance__hint">{{ t('grossanlass.aufgaben.acceptance.notEligible') }}</p>
    <p v-if="state.rework_open > 0" class="ga-order-acceptance__hint" data-testid="order-rework-open">{{ t('grossanlass.aufgaben.rework.open', { n: state.rework_open }) }}</p>

    <div class="ga-order-acceptance__actions">
      <EButton v-if="state.status === 'open' && state.eligible && state.permissions.report" variant="primary" size="small" @click="run('report')">
        {{ t('grossanlass.aufgaben.acceptance.report') }}
      </EButton>
      <EButton v-if="state.status === 'review' && state.permissions.report" variant="secondary" size="small" @click="run('withdraw')">
        {{ t('grossanlass.aufgaben.acceptance.withdraw') }}
      </EButton>
      <template v-if="state.status === 'review' && state.permissions.approve">
        <EButton variant="primary" size="small" @click="run('accept')">{{ t('grossanlass.aufgaben.acceptance.accept') }}</EButton>
        <EButton variant="secondary" size="small" @click="asking = 'return'">{{ t('grossanlass.aufgaben.acceptance.return') }}</EButton>
      </template>
      <EButton v-if="state.status === 'accepted' && state.permissions.approve" variant="secondary" size="small" @click="asking = 'reopen'">
        {{ t('grossanlass.aufgaben.acceptance.reopen') }}
      </EButton>
    </div>
    <div v-if="asking" class="ga-order-acceptance__reason">
      <ETextField v-model="reason" :label="t(`grossanlass.aufgaben.acceptance.reason.${asking}`)" density="compact" hide-details />
      <EButton variant="primary" size="small" :disabled="!reason.trim()" @click="submitReason">{{ t('grossanlass.aufgaben.acceptance.confirm') }}</EButton>
      <EButton variant="secondary" size="small" @click="asking = null">{{ t('common.cancel') }}</EButton>
    </div>
    <p v-if="error" class="ga-order-acceptance__error" role="alert">{{ error }}</p>

    <ul v-if="state.events.length" class="ga-order-acceptance__history" data-testid="order-acceptance-history">
      <li v-for="event in state.events" :key="event.id">
        <strong>{{ t(`grossanlass.aufgaben.acceptance.event.${event.type}`) }}</strong>
        · {{ event.by_name ?? '?' }} · {{ time(event.at) }}
        <span v-if="event.note" class="ga-order-acceptance__note">— {{ event.note }}</span>
        <div v-if="event.type === 'returned'" class="ga-order-acceptance__rework" data-testid="order-rework">
          <strong>{{ t('grossanlass.aufgaben.rework.title') }}</strong>
          <ul v-if="event.rework_tasks?.length">
            <li v-for="task in event.rework_tasks" :key="task.id">
              {{ task.title }}
              <v-chip size="x-small" variant="tonal" :color="task.status === 'done' ? 'success' : 'warning'">{{ t(`grossanlass.aufgaben.status.${task.status}`) }}</v-chip>
            </li>
          </ul>
          <span v-else class="ga-order-acceptance__note">{{ t('grossanlass.aufgaben.rework.none') }}</span>
          <EButton
            v-if="canCreateRework && event.id === latestReturnedId && !creating"
            variant="secondary"
            size="small"
            data-testid="order-rework-create"
            @click="creating = true"
          >
            {{ t('grossanlass.aufgaben.rework.create') }}
          </EButton>
          <div v-if="creating && event.id === latestReturnedId" class="ga-order-acceptance__create">
            <p class="ga-order-acceptance__note">{{ suggestionText }}</p>
            <ETextField v-model="reworkTitle" :label="t('grossanlass.aufgaben.rework.titleLabel')" density="compact" hide-details />
            <ESelect v-model="reworkAssignee" :items="assigneeItems" :label="t('grossanlass.aufgaben.rework.assignee')" density="compact" hide-details clearable />
            <div class="ga-order-acceptance__actions">
              <EButton variant="primary" size="small" :disabled="!reworkTitle.trim()" @click="createRework(event.id)">{{ t('grossanlass.aufgaben.rework.createSubmit') }}</EButton>
              <EButton variant="secondary" size="small" @click="creating = false">{{ t('common.cancel') }}</EButton>
            </div>
          </div>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ESelect, ETextField } from '@/components/form/base'
import { createGrossanlassBauprojektTask, getGrossanlassOrderAcceptance, postGrossanlassOrderAcceptance, type GaOrderAcceptance } from '@/api/grossanlassBauprojekt'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import { assigneesFor } from '@/utils/grossanlassGroupHierarchy'

/** Formelle Gesamtabnahme des Bauauftrags im bestehenden Bauauftrag-Dialog. Rechte und Regeln setzt der Server durch. */
const props = defineProps<{ departmentId: string; groupId: string; groups: GrossanlassGroup[]; refreshKey?: string }>()
const emit = defineEmits<{ changed: [] }>()
const { t, locale } = useI18n()
const COLOR = { open: 'default', review: 'info', accepted: 'success' } as const
const state = ref<GaOrderAcceptance | null>(null)
const error = ref('')
const asking = ref<'return' | 'reopen' | null>(null)
const reason = ref('')
const creating = ref(false)
const reworkTitle = ref('')
const reworkAssignee = ref<string | null>(null)

/** Nachbesserungen können zur jüngsten Rückweisung angelegt werden, solange der Bauauftrag offen ist. */
const latestReturnedId = computed(() => state.value?.events.find((e) => e.type === 'returned')?.id ?? null)
const canCreateRework = computed(() => state.value?.status === 'open' && state.value.permissions.report)
/** Auswahl: bisherige Verantwortliche zuerst als Vorschlag gekennzeichnet; nichts ist vorausgewählt. */
const assigneeItems = computed(() => {
  const suggested = new Set((state.value?.rework_suggestion.assignees ?? []).map((a) => a.user_id))
  return assigneesFor(props.groupId, props.groups)
    .map((person) => ({ value: person.value, title: suggested.has(person.value) ? `${person.title} (${t('grossanlass.aufgaben.rework.suggested')})` : person.title, suggested: suggested.has(person.value) }))
    .sort((a, b) => Number(b.suggested) - Number(a.suggested) || a.title.localeCompare(b.title, 'de'))
})
const suggestionText = computed(() => {
  const suggestion = state.value?.rework_suggestion
  const people = (suggestion?.assignees ?? []).map((a) => a.name).filter(Boolean).join(', ')
  return t('grossanlass.aufgaben.rework.suggestion', { ressort: suggestion?.ressort?.name ?? '–', people: people || '–' })
})

function message(e: unknown): string {
  return (e as { response?: { data?: { error?: string } } }).response?.data?.error || t('grossanlass.aufgaben.actionError')
}
async function load() {
  try {
    state.value = await getGrossanlassOrderAcceptance(props.departmentId, props.groupId)
  } catch (e) {
    error.value = message(e)
  }
}
async function run(action: 'report' | 'withdraw' | 'accept' | 'return' | 'reopen', text?: string) {
  error.value = ''
  try {
    state.value = await postGrossanlassOrderAcceptance(props.departmentId, props.groupId, action, text)
    emit('changed')
  } catch (e) {
    error.value = message(e)
    await load()
  }
}
async function createRework(eventId: string) {
  const title = reworkTitle.value.trim()
  if (!title) return
  error.value = ''
  try {
    await createGrossanlassBauprojektTask(props.departmentId, props.groupId, {
      title,
      assignee_user_id: reworkAssignee.value,
      rework_event_id: eventId,
    })
    creating.value = false
    reworkTitle.value = ''
    reworkAssignee.value = null
    await load()
    emit('changed')
  } catch (e) {
    error.value = message(e)
  }
}
async function submitReason() {
  if (!asking.value || !reason.value.trim()) return
  const action = asking.value
  const text = reason.value.trim()
  asking.value = null
  reason.value = ''
  await run(action, text)
}
function time(iso: string): string {
  return new Date(iso).toLocaleString(locale.value, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

onMounted(load)
watch(() => props.refreshKey, load)
</script>

<style scoped>
.ga-order-acceptance {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 16px;
}
.ga-order-acceptance h4 {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}
.ga-order-acceptance p {
  margin: 0;
}
.ga-order-acceptance__progress,
.ga-order-acceptance__hint {
  color: #64748b;
  font-size: 0.85rem;
}
.ga-order-acceptance__actions,
.ga-order-acceptance__reason {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.ga-order-acceptance__error {
  color: rgb(var(--v-theme-error));
  font-size: 0.85rem;
}
.ga-order-acceptance__history {
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 0.85rem;
}
.ga-order-acceptance__rework {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  margin: 4px 0 8px 12px;
}
.ga-order-acceptance__create {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: min(420px, 100%);
}
.ga-order-acceptance__note {
  color: #64748b;
}
</style>
