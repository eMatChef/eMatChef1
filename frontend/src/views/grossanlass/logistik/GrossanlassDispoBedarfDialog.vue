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

      <v-alert v-if="need.problem" type="error" variant="tonal" density="compact">
        {{ t(`grossanlass.dispo.problem.${need.problem.kind}`) }}<template v-if="need.problem.note"> · {{ need.problem.note }}</template>
        <template #append>
          <EButton size="small" variant="secondary" @click="resolveNeedProblem(need.id)">{{ t('grossanlass.dispo.resolve') }}</EButton>
        </template>
      </v-alert>

      <section v-if="need.status === 'open'" class="bedarf__dispo">
        <h4>{{ t('grossanlass.dispo.dispose') }}</h4>

        <div v-if="suggestions.length" class="bedarf__suggest">
          <p><v-icon icon="mdi-lightbulb-on-outline" size="16" /> {{ t('grossanlass.dispo.suggestions') }}</p>
          <button
            v-for="hit in suggestions"
            :key="hit.tour.id"
            type="button"
            class="suggest"
            :class="{ 'suggest--active': mode === 'existing' && existingTourId === hit.tour.id }"
            @click="pickSuggestion(hit.tour.id)"
          >
            <strong>{{ hit.tour.name }} · {{ driverName(hit.tour.driverId) }} · {{ vehicleName(hit.tour.vehicleId) }}</strong>
            <span>{{ t(`grossanlass.dispo.suggestReason.${hit.reason}`, { place: hit.place }) }}</span>
          </button>
        </div>

        <v-btn-toggle v-model="mode" mandatory density="compact" color="primary" variant="outlined" class="bedarf__mode">
          <v-btn value="new" size="small">{{ t('grossanlass.dispo.newTour') }}</v-btn>
          <v-btn value="existing" size="small">{{ t('grossanlass.dispo.addToTour') }}</v-btn>
        </v-btn-toggle>

        <div v-if="mode === 'new'" class="bedarf__form">
          <ESelect v-model="driverId" :items="driverItems" :label="t('grossanlass.dispo.driver')" hide-details />
          <ESelect v-model="vehicleId" :items="vehicleItems" :label="t('grossanlass.dispo.vehicle')" hide-details />
          <ESelect v-model="trailer" :items="trailerItems" :label="t('grossanlass.dispo.trailer')" clearable hide-details />
        </div>
        <div v-else class="bedarf__form">
          <ESelect v-model="existingTourId" :items="tourItems" :label="t('grossanlass.dispo.existingTour')" hide-details />
        </div>

        <EButton variant="primary" size="large" :disabled="!canAssign" @click="assign">
          <v-icon icon="mdi-source-branch-plus" start size="20" />
          {{ t('grossanlass.dispo.assign') }}
        </EButton>
      </section>

      <section v-else-if="tour" class="bedarf__dispo">
        <h4>{{ t('grossanlass.dispo.tour') }}</h4>
        <p>{{ tour.name }} · {{ driverName(tour.driverId) }} · {{ vehicleName(tour.vehicleId) }}</p>
        <div class="bedarf__row">
          <EButton variant="secondary" @click="$emit('open-tour', tour.id)">{{ t('grossanlass.dispo.openTour') }}</EButton>
          <EButton v-if="need.status === 'planned'" variant="secondary" @click="removeNeedFromTour(need.id)">
            {{ t('grossanlass.dispo.unassign') }}
          </EButton>
        </div>
      </section>

      <section v-if="need.status !== 'done'" class="bedarf__live">
        <h4>{{ t('grossanlass.dispo.live.markProblem') }}</h4>
        <div class="bedarf__row">
          <EButton variant="secondary" size="small" @click="markNeedProblem(need.id, 'supplierNotReady')">
            {{ t('grossanlass.dispo.live.supplierNotReady') }}
          </EButton>
          <EButton variant="secondary" size="small" @click="markNeedProblem(need.id, 'targetRefuses')">
            {{ t('grossanlass.dispo.live.targetRefuses') }}
          </EButton>
          <EButton variant="secondary" size="small" @click="markNeedProblem(need.id, 'other', t('grossanlass.dispo.live.otherNote'))">
            {{ t('grossanlass.dispo.live.other') }}
          </EButton>
        </div>
      </section>
    </div>
    <template #actions>
      <EButton variant="secondary" @click="open = false">{{ t('common.close') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog, ESelect } from '@/components/form/base'
import {
  GA_DISPO_TRAILERS,
  assignNeed,
  driverName,
  freeDrivers,
  freeVehicles,
  markNeedProblem,
  needById,
  removeNeedFromTour,
  resolveNeedProblem,
  suggestionsFor,
  tourById,
  useGaDispoMock,
  vehicleName,
} from './gaDispoMock'
import { PRIORITY_COLOR, SOURCE_ICON, STATUS_COLOR } from './gaDispoUi'

const props = defineProps<{ modelValue: boolean; needId: string | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; 'open-tour': [id: string] }>()
const { t } = useI18n()
const { tours } = useGaDispoMock()

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const need = computed(() => needById(props.needId ?? undefined))
const tour = computed(() => tourById(need.value?.tourId))
const suggestions = computed(() => (need.value ? suggestionsFor(need.value) : []))

const mode = ref<'new' | 'existing'>('new')
const driverId = ref<string | null>(null)
const vehicleId = ref<string | null>(null)
const trailer = ref<string | null>(null)
const existingTourId = ref<string | null>(null)

const driverItems = computed(() => freeDrivers().map((driver) => ({ title: driver.name, value: driver.id })))
const vehicleItems = computed(() => freeVehicles().map((vehicle) => ({ title: `${vehicle.name} (${vehicle.kind})`, value: vehicle.id })))
const trailerItems = computed(() => GA_DISPO_TRAILERS)
const tourItems = computed(() =>
  tours.value
    .filter((row) => row.status !== 'done')
    .map((row) => ({
      title: `${row.name} · ${driverName(row.driverId)} · ${vehicleName(row.vehicleId)}${row.status === 'underway' ? ` (${t('grossanlass.dispo.tourStatus.underway')})` : ''}`,
      value: row.id,
    })),
)

const canAssign = computed(() =>
  mode.value === 'new' ? !!driverId.value && !!vehicleId.value : !!existingTourId.value,
)

function pickSuggestion(id: string) {
  mode.value = 'existing'
  existingTourId.value = id
}

function assign() {
  if (!need.value) return
  if (mode.value === 'new' && driverId.value && vehicleId.value) {
    assignNeed(need.value.id, { mode: 'new', driverId: driverId.value, vehicleId: vehicleId.value, trailer: trailer.value ?? '' })
  } else if (mode.value === 'existing' && existingTourId.value) {
    assignNeed(need.value.id, { mode: 'existing', tourId: existingTourId.value })
  }
}

watch(
  () => props.needId,
  () => {
    mode.value = 'new'
    driverId.value = null
    vehicleId.value = null
    trailer.value = null
    existingTourId.value = null
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
</style>
