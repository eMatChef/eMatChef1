<template>
  <EDialog v-model="open" :title="need?.title ?? ''" max-width="760" :retain-focus="false">
    <div v-if="need" class="bedarf">
      <div class="bedarf__chips">
        <v-chip size="small" variant="tonal" :prepend-icon="SOURCE_ICON[need.source]">
          {{ t(`grossanlass.dispo.source.${need.source}`) }}
        </v-chip>
        <v-chip size="small" variant="flat" :color="PRIORITY_COLOR[need.priority]">
          {{ t(`grossanlass.dispo.priority.${need.priority}`) }}
        </v-chip>
        <v-chip size="small" variant="flat" :color="need.ready ? 'success' : 'grey'">
          {{ need.ready ? t('grossanlass.dispo.ready') : t('grossanlass.dispo.waiting') }}
        </v-chip>
        <v-chip size="small" variant="flat" :color="STATUS_COLOR[need.status]">
          {{ t(`grossanlass.dispo.status.${need.status}`) }}
        </v-chip>
      </div>

      <dl class="bedarf__facts">
        <div><dt>{{ t('grossanlass.dispo.route') }}</dt><dd>{{ need.from }} <v-icon icon="mdi-arrow-right" size="14" /> {{ need.to }}</dd></div>
        <div><dt>{{ t('grossanlass.dispo.timing') }}</dt><dd>{{ t(`grossanlass.dispo.timingKind.${need.timingKind}`) }} · {{ need.timingLabel }}</dd></div>
        <div><dt>{{ t('grossanlass.dispo.cargo') }}</dt><dd>{{ need.cargo }}</dd></div>
        <div><dt>{{ t('grossanlass.dispo.target') }}</dt><dd>{{ need.target }}</dd></div>
        <div v-if="need.requirements.length">
          <dt>{{ t('grossanlass.dispo.requirements') }}</dt>
          <dd><v-chip v-for="req in need.requirements" :key="req" size="x-small" variant="outlined" class="mr-1">{{ req }}</v-chip></dd>
        </div>
      </dl>

      <p v-if="need.readyNote && !need.ready" class="bedarf__warn" data-testid="dispo-ready-note">
        <v-icon icon="mdi-timer-sand" size="14" /> {{ need.readyNote }}
      </p>
      <p v-if="driver" class="bedarf__driver"><v-icon icon="mdi-steering" size="14" /> {{ driver }}</p>
      <v-alert v-if="error" type="warning" variant="tonal" density="compact" data-testid="dispo-need-error">{{ error }}</v-alert>

      <section v-if="canConvert" class="bedarf__dispo" data-testid="dispo-need-convert">
        <h4>{{ t('grossanlass.dispo.convert.title') }}</h4>
        <p class="bedarf__hint">{{ t('grossanlass.dispo.convert.hint') }}</p>
        <EButton variant="secondary" :disabled="busy" data-testid="dispo-need-convert-action" @click="convert">
          {{ t('grossanlass.dispo.convert.action') }}
        </EButton>
      </section>

      <section v-if="need.einsatzId" class="bedarf__dispo" data-testid="dispo-need-exec">
        <h4>{{ t('grossanlass.dispo.exec.title') }}</h4>
        <p class="bedarf__exec">
          <v-chip size="x-small" variant="flat" :color="need.released ? 'success' : 'grey'" data-testid="dispo-need-released">
            {{ need.released ? t('grossanlass.dispo.exec.released') : t('grossanlass.dispo.exec.notReleased') }}
          </v-chip>
          <v-chip size="x-small" variant="tonal" data-testid="dispo-need-pack-status">{{ t(`grossanlass.dispo.packStatus.${need.packStatus}`) }}</v-chip>
        </p>
        <div class="bedarf__row">
          <EButton v-if="canRelease" variant="primary" :disabled="busy || !releasable" data-testid="dispo-need-release" @click="release">
            {{ t('grossanlass.dispo.exec.release') }}
          </EButton>
          <EButton v-if="canRevoke" variant="secondary" :disabled="busy" data-testid="dispo-need-revoke" @click="revoke">
            {{ t('grossanlass.dispo.exec.revoke') }}
          </EButton>
        </div>
        <p v-if="canRelease && !releasable" class="bedarf__hint">{{ t('grossanlass.dispo.exec.confirmTourFirst') }}</p>
        <div v-if="canDeliverManually" class="bedarf__manual" data-testid="dispo-need-manual">
          <p class="bedarf__hint">{{ t('grossanlass.dispo.exec.manualHint') }}</p>
          <textarea v-model="reason" class="bedarf__reason" rows="2" :placeholder="t('grossanlass.dispo.exec.reason')" data-testid="dispo-need-manual-reason" />
          <EButton variant="secondary" :disabled="busy || reason.trim().length < 5" data-testid="dispo-need-manual-action" @click="deliverManually">
            {{ t('grossanlass.dispo.exec.manual') }}
          </EButton>
        </div>
      </section>

      <section v-if="requests.length" class="bedarf__dispo" data-testid="dispo-need-requests">
        <h4>{{ t('grossanlass.dispo.requests.title') }}</h4>
        <p class="bedarf__hint">{{ t('grossanlass.dispo.requests.planStillValid') }}</p>
        <article v-for="item in requests" :key="item.id" class="bedarf__request" :data-testid="`dispo-request-${item.id}`">
          <p>
            <strong>{{ item.requested_by || '–' }}</strong>
            <v-chip size="x-small" variant="flat" :color="REQUEST_COLOR[item.status]">{{ t(`grossanlass.dispo.requests.status.${item.status}`) }}</v-chip>
          </p>
          <p v-if="item.note">{{ item.note }}</p>
          <p v-if="item.proposed_starts_at" class="bedarf__hint">{{ t('grossanlass.dispo.requests.proposedWindow') }}: {{ item.proposed_starts_at.slice(0, 16).replace('T', ' ') }} – {{ (item.proposed_ends_at ?? '').slice(11, 16) }}</p>
          <p v-if="item.proposed_destination_place_id" class="bedarf__hint">{{ t('grossanlass.dispo.requests.proposedPlace') }}</p>
          <p v-if="item.decision_note" class="bedarf__hint">{{ t('grossanlass.dispo.requests.decisionNote') }}: {{ item.decision_note }}</p>
          <template v-if="item.status === 'open' && canManage">
            <input v-model="decisionNote" class="bedarf__reason" type="text" :placeholder="t('grossanlass.dispo.requests.notePlaceholder')" :data-testid="`dispo-request-note-${item.id}`" />
            <div v-if="adjusting === item.id" class="bedarf__form">
              <input v-model="adjustFrom" type="datetime-local" class="bedarf__reason" :aria-label="t('grossanlass.dispo.requests.from')" />
              <input v-model="adjustTo" type="datetime-local" class="bedarf__reason" :aria-label="t('grossanlass.dispo.requests.to')" />
            </div>
            <div class="bedarf__row">
              <EButton size="small" variant="primary" :disabled="busy" :data-testid="`dispo-request-accept-${item.id}`" @click="decide(item, 'accept')">{{ t('grossanlass.dispo.requests.accept') }}</EButton>
              <EButton size="small" variant="secondary" :disabled="busy" @click="startAdjust(item)">{{ adjusting === item.id ? t('grossanlass.dispo.requests.adjustApply') : t('grossanlass.dispo.requests.adjust') }}</EButton>
              <EButton size="small" variant="secondary" :disabled="busy || decisionNote.trim().length < 5" :data-testid="`dispo-request-reject-${item.id}`" @click="decide(item, 'reject')">{{ t('grossanlass.dispo.requests.reject') }}</EButton>
            </div>
          </template>
        </article>
      </section>

      <section v-if="tour" class="bedarf__dispo" data-testid="dispo-need-tour">
        <h4>{{ t('grossanlass.dispo.tour') }}</h4>
        <p>
          {{ tour.name }}
          <v-chip size="x-small" variant="flat" :color="TOUR_STATUS_COLOR[tour.status]">{{ t(`grossanlass.dispo.tourStatus.${tour.status}`) }}</v-chip>
          <template v-if="tour.chauffeurId"> · {{ driverName(tour.chauffeurId) }}</template>
          <template v-if="tour.vehicleId"> · {{ vehicleName(tour.vehicleId) }}</template>
        </p>
        <div class="bedarf__row">
          <EButton variant="secondary" @click="$emit('open-tour', tour.id)">{{ t('grossanlass.dispo.openTour') }}</EButton>
          <EButton v-if="canManage && !tour.locked" variant="secondary" :disabled="busy" @click="unassign">{{ t('grossanlass.dispo.unassign') }}</EButton>
        </div>
      </section>

      <section v-else-if="assignable" class="bedarf__dispo" data-testid="dispo-need-assign">
        <h4>{{ t('grossanlass.dispo.dispose') }}</h4>
        <v-btn-toggle v-model="mode" mandatory density="compact" color="primary" variant="outlined" class="bedarf__mode">
          <v-btn value="new" size="small">{{ t('grossanlass.dispo.newTour') }}</v-btn>
          <v-btn value="existing" size="small" :disabled="!draftTours.length">{{ t('grossanlass.dispo.addToTour') }}</v-btn>
        </v-btn-toggle>
        <div v-if="mode === 'new'" class="bedarf__form">
          <ESelect v-model="driverId" :items="driverItems" :label="t('grossanlass.dispo.driver')" clearable hide-details />
          <ESelect v-model="vehicleId" :items="vehicleItems" :label="t('grossanlass.dispo.vehicle')" clearable hide-details />
        </div>
        <div v-else class="bedarf__form">
          <ESelect v-model="existingTourId" :items="tourItems" :label="t('grossanlass.dispo.existingTour')" hide-details />
        </div>
        <EButton variant="primary" size="large" :disabled="!canAssign || busy" data-testid="dispo-need-assign-action" @click="assign">
          <v-icon icon="mdi-source-branch-plus" start size="20" />
          {{ mode === 'new' ? t('grossanlass.dispo.createDraftTour') : t('grossanlass.dispo.assign') }}
        </EButton>
        <p class="bedarf__hint">{{ t('grossanlass.dispo.draftHint') }}</p>
      </section>

      <v-alert v-else-if="!canManage && need.status !== 'done'" type="info" variant="tonal" density="compact">{{ t('grossanlass.dispo.noManage') }}</v-alert>
    </div>
    <template #actions>
      <EButton variant="secondary" @click="open = false">{{ t('common.close') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { EButton, EDialog, ESelect } from '@/components/form/base'
import { dispatchActions, liveDriverName, liveNeedById, liveTourById, liveVehicleName, useGaDispo, type GaDispoActionResult } from './useGaDispo'
import { PRIORITY_COLOR, REQUEST_COLOR, SOURCE_ICON, STATUS_COLOR, TOUR_STATUS_COLOR } from './gaDispoUi'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{ modelValue: boolean; needId: string | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; 'open-tour': [id: string] }>()
const { t } = useI18n()
const route = useRoute()
const { tours, drivers, vehicles, canManage, changeRequests } = useGaDispo()
const authStore = useAuthStore()
const departmentId = computed(() => String(route.params.departmentId || ''))
const driverName = liveDriverName
const vehicleName = liveVehicleName

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const need = computed(() => liveNeedById(props.needId))
const driver = computed(() => (need.value?.chauffeurId ? liveDriverName(need.value.chauffeurId) : ''))
const tour = computed(() => liveTourById(need.value?.tourId))
const assignable = computed(() => canManage.value && !!need.value && !need.value.tourId && (need.value.status === 'open' || need.value.status === 'planned'))

const mode = ref<'new' | 'existing'>('new')
const driverId = ref<string | null>(null)
const vehicleId = ref<string | null>(null)
const existingTourId = ref<string | null>(null)
const busy = ref(false)
const error = ref('')

const driverItems = computed(() => drivers.value.map((row) => ({ title: row.name, value: row.id })))
const vehicleItems = computed(() => vehicles.value.map((row) => ({ title: row.name, value: row.id })))
const draftTours = computed(() => tours.value.filter((row) => !row.locked))
const tourItems = computed(() => draftTours.value.map((row) => ({ title: `${row.name} · ${t(`grossanlass.dispo.tourStatus.${row.status}`)}`, value: row.id })))
const canAssign = computed(() => (mode.value === 'new' ? !!need.value : !!existingTourId.value))

const requests = computed(() => changeRequests.value.filter((item) => item.need_id === need.value?.id && (item.status === 'open' || canManage.value)))
const reason = ref('')
const decisionNote = ref('')
const adjusting = ref<string | null>(null)
const adjustFrom = ref('')
const adjustTo = ref('')

/** Fahrauftrag in einer Tour darf erst nach bestätigter Tour freigegeben werden; ohne Tour gilt die bestehende Regel. */
const releasable = computed(() => !tour.value || tour.value.confirmed)
/** Fahrzeugwunsch ohne Fahrauftrag (`vn:`-Bedarf): die Logistik kann ihn in einen Fahrauftrag überführen. */
const canConvert = computed(() => canManage.value && !!need.value && !need.value.einsatzId && need.value.purpose === 'transport' && need.value.status !== 'done')
const canRelease = computed(() => canManage.value && !!need.value && !need.value.released && (need.value.status === 'open' || need.value.status === 'planned'))
const canRevoke = computed(() => canManage.value && !!need.value?.released && need.value.status === 'planned' && need.value.packStatus !== 'in_transit')
/** Ohne lesbaren Ziel-QR: Chauffeur des Fahrauftrags oder Logistik, solange das Material unterwegs ist. */
const canDeliverManually = computed(
  () => !!need.value && need.value.packStatus === 'in_transit' && (canManage.value || (!!authStore.userId && need.value.chauffeurId === authStore.userId)),
)

async function act(call: () => Promise<GaDispoActionResult>) {
  busy.value = true
  error.value = ''
  const result = await call()
  busy.value = false
  if (!result.ok) error.value = result.error
}
async function convert() {
  if (!need.value) return
  const id = need.value.id
  await act(() => dispatchActions.convertVehicleNeed(departmentId.value, id))
  // Der Wunsch ist nun ein Fahrauftrag mit eigener ID; der Dialog zum alten Bedarf entfällt.
  if (!error.value) open.value = false
}
function release() {
  if (!need.value) return
  const id = need.value.id
  return act(() => dispatchActions.releaseNeed(departmentId.value, id))
}
function revoke() {
  if (!need.value) return
  const id = need.value.id
  return act(() => dispatchActions.revokeRelease(departmentId.value, id))
}
async function deliverManually() {
  if (!need.value) return
  const packs = need.value.packIds
  const text = reason.value
  await act(async () => {
    for (const packId of packs) {
      const result = await dispatchActions.arriveManually(departmentId.value, packId, text)
      if (!result.ok) return result
    }
    return { ok: true } as const
  })
  reason.value = ''
}
function startAdjust(item: { id: string; proposed_starts_at: string | null; proposed_ends_at: string | null }) {
  if (adjusting.value !== item.id) {
    adjusting.value = item.id
    adjustFrom.value = (item.proposed_starts_at ?? need.value?.startsAt.toISOString() ?? '').slice(0, 16)
    adjustTo.value = (item.proposed_ends_at ?? '').slice(0, 16)
    return
  }
  return decide(item as { id: string }, 'adjust')
}
function decide(item: { id: string }, decision: 'accept' | 'adjust' | 'reject') {
  const payload = { decision, note: decisionNote.value } as Parameters<typeof dispatchActions.decideChange>[2]
  if (decision === 'adjust') {
    payload.starts_at = adjustFrom.value ? `${adjustFrom.value}:00` : null
    payload.ends_at = adjustTo.value ? `${adjustTo.value}:00` : null
  }
  adjusting.value = null
  decisionNote.value = ''
  return act(() => dispatchActions.decideChange(departmentId.value, item.id, payload))
}
function assign() {
  if (!need.value) return
  const id = need.value.id
  if (mode.value === 'new') {
    return act(() => dispatchActions.createTour(departmentId.value, { need_ids: [id], vehicle_id: vehicleId.value, chauffeur_user_id: driverId.value }))
  }
  if (existingTourId.value) {
    const tourId = existingTourId.value
    return act(() => dispatchActions.addNeed(departmentId.value, tourId, id))
  }
}
function unassign() {
  if (!need.value || !tour.value) return
  const tourId = tour.value.id
  const id = need.value.id
  return act(() => dispatchActions.removeNeed(departmentId.value, tourId, id))
}

watch(
  () => props.needId,
  () => {
    mode.value = 'new'
    driverId.value = null
    vehicleId.value = null
    existingTourId.value = null
    error.value = ''
    reason.value = ''
    decisionNote.value = ''
    adjusting.value = null
  },
)
</script>

<style scoped>
.bedarf {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.bedarf__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.bedarf__facts {
  display: grid;
  gap: 10px;
  margin: 0;
}
.bedarf__facts dt,
.bedarf__dispo h4,
.bedarf__live h4 {
  margin: 0 0 2px;
  font-size: 0.72rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748b;
  font-weight: 600;
}
.bedarf__facts dd {
  margin: 0;
}
.bedarf__dispo,
.bedarf__live {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fafafa;
}
.bedarf__suggest p {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 6px;
  font-size: 0.85rem;
  font-weight: 600;
  color: #065f46;
}
.suggest {
  display: flex;
  flex-direction: column;
  gap: 2px;
  width: 100%;
  margin-bottom: 6px;
  padding: 10px 12px;
  border: 1px solid #a7f3d0;
  border-radius: 10px;
  background: #ecfdf5;
  text-align: left;
  cursor: pointer;
}
.suggest span {
  font-size: 0.82rem;
  color: #047857;
}
.suggest--active {
  border-color: #059669;
  box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.2);
}
.bedarf__mode {
  align-self: flex-start;
}
.bedarf__form {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}
.bedarf__row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.bedarf__dispo {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
}
.bedarf__form {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 10px;
}
.bedarf__row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.bedarf__hint {
  margin: 0;
  font-size: 0.8rem;
  color: #64748b;
}
.bedarf__reason {
  width: 100%;
  padding: 6px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.88rem;
}
.bedarf__manual,
.bedarf__request {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.bedarf__request {
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
}
.bedarf__request p,
.bedarf__exec {
  margin: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
</style>
