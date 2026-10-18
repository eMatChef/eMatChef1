<template>
  <EDialog v-model="open" :title="tour ? `${tour.name}` : ''" max-width="820" :retain-focus="false">
    <div v-if="tour" class="tour" data-testid="dispo-tour-dialog">
      <div class="tour__head">
        <v-chip size="small" variant="flat" :color="TOUR_STATUS_COLOR[tour.status]">
          {{ t(`grossanlass.dispo.tourStatus.${tour.status}`) }}
        </v-chip>
        <v-chip v-if="tour.trailer" size="small" variant="tonal" prepend-icon="mdi-truck-trailer">{{ tour.trailer }}</v-chip>
        <v-chip v-if="tour.startsAt" size="small" variant="tonal" prepend-icon="mdi-clock-outline">{{ windowLabel }}</v-chip>
        <v-chip v-if="tour.confirmed" size="small" variant="tonal" color="success" prepend-icon="mdi-check-decagram-outline">
          {{ t('grossanlass.dispo.tourConfirmedNotReleased') }}
        </v-chip>
      </div>

      <v-alert v-if="tour.conflicts.length" type="error" variant="tonal" density="compact" data-testid="dispo-tour-conflicts">
        <div v-for="(conflict, index) in tour.conflicts" :key="index">
          {{ t(`grossanlass.dispo.conflict.${conflict.kind}`, { with: conflict.with, when: conflictWindow(conflict) }) }}
        </div>
      </v-alert>
      <v-alert v-if="error" type="warning" variant="tonal" density="compact" data-testid="dispo-tour-error">{{ error }}</v-alert>
      <v-alert v-if="tour.locked" type="info" variant="tonal" density="compact">{{ tour.status === 'closed' ? t('grossanlass.dispo.tourClosed') : t('grossanlass.dispo.tourLocked') }}</v-alert>
      <v-alert v-else-if="!canManage" type="info" variant="tonal" density="compact">{{ t('grossanlass.dispo.noManage') }}</v-alert>

      <section class="tour__resources">
        <ETextField v-model="form.name" :label="t('grossanlass.dispo.tourName')" :disabled="!editable" hide-details />
        <ESelect v-model="form.vehicleId" :items="vehicleItems" :label="t('grossanlass.dispo.vehicle')" clearable :disabled="!editable" hide-details />
        <ESelect v-model="form.chauffeurId" :items="driverItems" :label="t('grossanlass.dispo.driver')" clearable :disabled="!editable" hide-details />
        <ETextField v-model="form.trailer" :label="t('grossanlass.dispo.trailer')" :disabled="!editable" hide-details />
        <EButton v-if="editable" variant="secondary" :disabled="busy || !resourcesDirty" data-testid="dispo-tour-save-resources" @click="saveResources">
          {{ t('common.save') }}
        </EButton>
      </section>

      <h4 class="tour__h">{{ t('grossanlass.dispo.tourNeeds') }}</h4>
      <ul class="tour__needs">
        <li v-for="need in tourNeeds" :key="need.id" :data-testid="`dispo-tour-need-${need.id}`">
          <span>
            <strong>{{ need.title }}</strong> · {{ need.from }} → {{ need.to }} · {{ need.timingLabel }}
            <v-chip v-if="need.einsatzId" size="x-small" variant="flat" :color="need.released ? 'success' : 'grey'">
              {{ need.released ? t('grossanlass.dispo.exec.released') : t('grossanlass.dispo.exec.notReleased') }}
            </v-chip>
            <v-chip v-if="need.einsatzId" size="x-small" variant="tonal">{{ t(`grossanlass.dispo.packStatus.${need.packStatus}`) }}</v-chip>
            <v-chip v-if="outcomeOf(need.id)" size="x-small" variant="flat" :color="outcomeOf(need.id) === 'delivered' ? 'success' : 'warning'">
              {{ t(`grossanlass.dispo.outcome.${outcomeOf(need.id)}`) }}
            </v-chip>
          </span>
          <EButton v-if="editable" size="small" variant="secondary" :disabled="busy" @click="removeNeed(need.id)">{{ t('grossanlass.dispo.removeFromTour') }}</EButton>
        </li>
      </ul>
      <div v-if="editable && addItems.length" class="tour__add">
        <ESelect v-model="addNeedId" :items="addItems" :label="t('grossanlass.dispo.addNeed')" clearable hide-details />
        <EButton variant="secondary" :disabled="!addNeedId || busy" @click="addNeed">{{ t('grossanlass.dispo.addNeedAction') }}</EButton>
      </div>

      <h4 class="tour__h">{{ t('grossanlass.dispo.tourStops') }}</h4>
      <ol class="tour__stops" data-testid="dispo-tour-stops">
        <li v-for="(stop, index) in draftStops" :key="stop.id" class="stop" :data-testid="`dispo-stop-${index}`">
          <span class="stop__icon"><v-icon :icon="STOP_ICON[stop.kind]" size="18" /></span>
          <span class="stop__body">
            <strong>{{ stop.label || (stop.kind === 'unload' ? '–' : t('grossanlass.dispo.depot')) }}</strong>
            <span>{{ t(`grossanlass.dispo.stopKind.${stop.kind}`) }}<template v-if="stopNeedTitle(stop)"> · {{ stopNeedTitle(stop) }}</template></span>
          </span>
          <input
            class="stop__time"
            type="time"
            :value="timeOf(stop.plannedAt)"
            :disabled="!editable"
            :aria-label="t('grossanlass.dispo.stopTime')"
            @change="setTime(stop, ($event.target as HTMLInputElement).value)"
          />
          <span v-if="editable" class="stop__moves">
            <v-btn icon="mdi-arrow-up" size="x-small" variant="text" :disabled="index === 0" :aria-label="t('grossanlass.dispo.moveUp')" @click="move(index, -1)" />
            <v-btn icon="mdi-arrow-down" size="x-small" variant="text" :disabled="index === draftStops.length - 1" :aria-label="t('grossanlass.dispo.moveDown')" @click="move(index, 1)" />
          </span>
        </li>
      </ol>
      <div v-if="editable && stopsDirty" class="tour__row">
        <EButton variant="primary" :disabled="busy" data-testid="dispo-tour-save-stops" @click="saveStops">{{ t('grossanlass.dispo.saveStops') }}</EButton>
        <EButton variant="secondary" :disabled="busy" @click="resetStops">{{ t('common.cancel') }}</EButton>
      </div>

      <details v-if="tour.events.length" class="tour__log">
        <summary>{{ t('grossanlass.dispo.tourLog') }}</summary>
        <ul>
          <li v-for="(event, index) in tour.events" :key="index">
            {{ event.at.replace('T', ' ').slice(0, 16) }} · {{ event.user }} · {{ t(`grossanlass.dispo.tourEvent.${event.action}`) }}<template v-if="event.note"> · {{ event.note }}</template>
          </li>
        </ul>
      </details>
    </div>

    <template #actions>
      <EButton variant="secondary" @click="open = false">{{ t('common.close') }}</EButton>
      <template v-if="canExecute">
        <input v-if="closing" v-model="closeNote" class="tour__note" type="text" :placeholder="t('grossanlass.dispo.closeNote')" data-testid="dispo-tour-close-note" />
        <EButton variant="secondary" :disabled="busy" data-testid="dispo-tour-close" @click="closeTour">
          {{ closing ? t('grossanlass.dispo.closeTourConfirm') : t('grossanlass.dispo.closeTour') }}
        </EButton>
        <EButton v-if="hasUnreleased" variant="primary" :disabled="busy" data-testid="dispo-tour-release" @click="releaseTour">
          {{ t('grossanlass.dispo.exec.releaseTour') }}
        </EButton>
      </template>
      <EButton v-if="editable" variant="secondary" :disabled="busy" data-testid="dispo-tour-delete" @click="remove">{{ t('grossanlass.dispo.deleteTour') }}</EButton>
      <EButton v-if="editable && tour?.confirmed" variant="secondary" :disabled="busy" @click="unconfirm">{{ t('grossanlass.dispo.unconfirmTour') }}</EButton>
      <EButton v-if="editable && tour && !tour.confirmed" variant="primary" :disabled="busy || stopsDirty || resourcesDirty" data-testid="dispo-tour-confirm" @click="confirm">
        {{ t('grossanlass.dispo.confirmTour') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import {
  dispatchActions,
  liveTourById,
  useGaDispo,
  type GaDispoLiveNeed,
  type GaDispoLiveTour,
} from './useGaDispo'
import { STOP_ICON, TOUR_STATUS_COLOR } from './gaDispoUi'

const props = defineProps<{ modelValue: boolean; tourId: string | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { t } = useI18n()
const route = useRoute()
const { needs, drivers, vehicles, canManage } = useGaDispo()
const departmentId = computed(() => String(route.params.departmentId || ''))

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const tour = computed(() => liveTourById(props.tourId))
const editable = computed(() => canManage.value && !!tour.value && !tour.value.locked)
/** Freigeben und Abschliessen gehören zur bestätigten, noch nicht abgeschlossenen Tour (auch während der Ausführung). */
const canExecute = computed(() => canManage.value && !!tour.value?.confirmed && tour.value.status !== 'closed')
const hasUnreleased = computed(() => tourNeeds.value.some((need) => !!need.einsatzId && !need.released && (need.status === 'open' || need.status === 'planned')))
const closing = ref(false)
const closeNote = ref('')
function outcomeOf(needId: string): string | null {
  return tour.value?.outcomes.find((item) => item.needId === needId)?.outcome ?? null
}
const busy = ref(false)
const error = ref('')

const form = reactive({ name: '', vehicleId: null as string | null, chauffeurId: null as string | null, trailer: '' })
const draftStops = ref<GaDispoLiveTour['stops']>([])
function load() {
  const current = tour.value
  if (!current) return
  form.name = current.name
  form.vehicleId = current.vehicleId
  form.chauffeurId = current.chauffeurId
  form.trailer = current.trailer
  draftStops.value = current.stops.map((stop) => ({ ...stop }))
}
watch(() => [props.tourId, tour.value], load, { immediate: true })
watch(() => props.modelValue, (value) => {
  if (value) error.value = ''
  closing.value = false
})

const resourcesDirty = computed(() => {
  const current = tour.value
  return !!current && (form.name !== current.name || form.vehicleId !== current.vehicleId || form.chauffeurId !== current.chauffeurId || form.trailer !== current.trailer)
})
const stopsDirty = computed(() => {
  const current = tour.value
  return !!current && JSON.stringify(draftStops.value) !== JSON.stringify(current.stops)
})

const tourNeeds = computed(() => (tour.value?.needIds ?? []).map((id) => needs.value.find((need) => need.id === id)).filter((need): need is GaDispoLiveNeed => !!need))
const addItems = computed(() =>
  needs.value
    .filter((need) => !need.tourId && (need.status === 'open' || need.status === 'planned'))
    .map((need) => ({ title: `${need.title} (${need.timingLabel})`, value: need.id })),
)
const addNeedId = ref<string | null>(null)
const vehicleItems = computed(() => vehicles.value.map((vehicle) => ({ title: vehicle.name, value: vehicle.id })))
const driverItems = computed(() => drivers.value.map((driver) => ({ title: driver.name, value: driver.id })))

const windowLabel = computed(() => {
  const start = tour.value?.startsAt
  const end = tour.value?.endsAt
  if (!start || !end) return ''
  const clock = (date: Date) => `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
  return `${start.toLocaleDateString(undefined, { day: 'numeric', month: 'numeric' })} ${clock(start)}–${clock(end)}`
})
function conflictWindow(conflict: { from: string; to: string }): string {
  return `${conflict.from.slice(11, 16)}–${conflict.to.slice(11, 16)}`
}
function stopNeedTitle(stop: GaDispoLiveTour['stops'][number]): string {
  return needs.value.find((need) => need.id === stop.needId)?.title ?? ''
}
function timeOf(plannedAt: string | null): string {
  return plannedAt ? plannedAt.slice(11, 16) : ''
}
function pad(value: number): string {
  return String(value).padStart(2, '0')
}
/** Tag der Tour (Datum des ersten geplanten Stopps, sonst des ersten Bedarfs); der Stopp ändert nur die Uhrzeit. */
function tourDay(): string {
  const planned = draftStops.value.find((stop) => stop.plannedAt)?.plannedAt
  if (planned) return planned.slice(0, 10)
  const start = tourNeeds.value[0]?.startsAt
  return start ? `${start.getFullYear()}-${pad(start.getMonth() + 1)}-${pad(start.getDate())}` : ''
}
function setTime(stop: GaDispoLiveTour['stops'][number], value: string) {
  const day = stop.plannedAt ? stop.plannedAt.slice(0, 10) : tourDay()
  stop.plannedAt = value && day ? `${day}T${value}:00` : null
}
function move(index: number, delta: number) {
  const list = draftStops.value.slice()
  const [item] = list.splice(index, 1)
  list.splice(index + delta, 0, item!)
  draftStops.value = list
}
function resetStops() {
  draftStops.value = (tour.value?.stops ?? []).map((stop) => ({ ...stop }))
}

async function act(call: () => Promise<{ ok: true } | { ok: false; error: string }>): Promise<boolean> {
  busy.value = true
  error.value = ''
  // Bei einem Serverfehler bleiben die Eingaben erhalten (die Disposition wird trotzdem neu geladen)
  const keepForm = { ...form }
  const keepStops = draftStops.value.map((stop) => ({ ...stop }))
  const result = await call()
  busy.value = false
  if (!result.ok) {
    error.value = result.error
    await nextTick()
    Object.assign(form, keepForm)
    draftStops.value = keepStops
    return false
  }
  load()
  return true
}
function saveResources() {
  if (!tour.value) return
  const { id } = tour.value
  return act(() => dispatchActions.updateTour(departmentId.value, id, {
    name: form.name,
    vehicle_id: form.vehicleId,
    chauffeur_user_id: form.chauffeurId,
    trailer: form.trailer,
  }))
}
function saveStops() {
  if (!tour.value) return
  const { id } = tour.value
  return act(() => dispatchActions.replaceStops(departmentId.value, id, draftStops.value))
}
function addNeed() {
  if (!tour.value || !addNeedId.value) return
  const { id } = tour.value
  const needId = addNeedId.value
  addNeedId.value = null
  return act(() => dispatchActions.addNeed(departmentId.value, id, needId))
}
async function removeNeed(needId: string) {
  if (!tour.value) return
  const { id } = tour.value
  const ok = await act(() => dispatchActions.removeNeed(departmentId.value, id, needId))
  if (ok && !liveTourById(id)) open.value = false
}
function releaseTour() {
  if (!tour.value) return
  const { id } = tour.value
  return act(() => dispatchActions.releaseTour(departmentId.value, id))
}
async function closeTour() {
  if (!tour.value) return
  if (!closing.value) {
    closing.value = true
    return
  }
  const { id } = tour.value
  if (await act(() => dispatchActions.closeTour(departmentId.value, id, closeNote.value))) {
    closing.value = false
    closeNote.value = ''
  }
}
function confirm() {
  if (!tour.value) return
  const { id } = tour.value
  return act(() => dispatchActions.confirmTour(departmentId.value, id))
}
function unconfirm() {
  if (!tour.value) return
  const { id } = tour.value
  return act(() => dispatchActions.unconfirmTour(departmentId.value, id))
}
async function remove() {
  if (!tour.value) return
  const { id } = tour.value
  if (await act(() => dispatchActions.deleteTour(departmentId.value, id))) open.value = false
}
</script>

<style scoped>
.tour {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.tour__head {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.tour__stops {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
}
.stop {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.stop--next {
  border-color: #059669;
  background: #ecfdf5;
}
.stop--done {
  opacity: 0.7;
}
.stop__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: #f1f5f9;
  flex: 0 0 34px;
}
.stop__body {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  font-size: 0.88rem;
}
.stop__body span {
  color: #64748b;
}
.leg {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 2px 0 2px 28px;
  padding-left: 8px;
  border-left: 3px solid #059669;
  font-size: 0.78rem;
  color: #065f46;
}
.leg--empty {
  border-left: 3px dashed #f59e0b;
  color: #b45309;
  font-weight: 600;
}
.tour__resources {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 10px;
  align-items: end;
}
.tour__h {
  margin: 4px 0 0;
  font-size: 0.75rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}
.tour__needs {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.tour__needs li {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  font-size: 0.86rem;
}
.tour__add,
.tour__row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
}
.tour__row {
  display: flex;
}
.stop__time {
  width: 92px;
  padding: 4px 6px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.86rem;
}
.stop__moves {
  display: inline-flex;
}
.tour__log {
  font-size: 0.82rem;
  color: #475569;
}
.tour__log ul {
  margin: 6px 0 0;
  padding-left: 18px;
}
.tour__note {
  min-width: 200px;
  padding: 6px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.86rem;
}
</style>
