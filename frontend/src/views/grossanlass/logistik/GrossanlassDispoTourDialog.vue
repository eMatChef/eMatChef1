<template>
  <EDialog v-model="open" :title="tour ? `${tour.name} · ${driverName(tour.driverId)} · ${vehicleName(tour.vehicleId)}` : ''" max-width="760" :retain-focus="false">
    <div v-if="tour" class="tour">
      <div class="tour__head">
        <v-chip size="small" variant="flat" :color="tour.status === 'done' ? 'success' : tour.status === 'underway' ? 'primary' : 'info'">
          {{ t(`grossanlass.dispo.tourStatus.${tour.status}`) }}
        </v-chip>
        <v-chip v-if="tour.trailer" size="small" variant="tonal" prepend-icon="mdi-truck-trailer">{{ tour.trailer }}</v-chip>
        <v-chip v-if="tour.delayMin" size="small" variant="flat" color="warning" prepend-icon="mdi-clock-alert-outline">
          {{ t('grossanlass.dispo.delay', { min: tour.delayMin }) }}
        </v-chip>
      </div>

      <v-alert v-if="tour.problem" :type="tour.problem.kind === 'delayed' ? 'warning' : 'error'" variant="tonal" density="compact">
        {{ t(`grossanlass.dispo.problem.${tour.problem.kind}`) }}<template v-if="tour.problem.note"> · {{ tour.problem.note }}</template>
        <template #append>
          <EButton size="small" variant="secondary" @click="clearTourProblem(tour)">{{ t('grossanlass.dispo.resolve') }}</EButton>
        </template>
      </v-alert>

      <ol class="tour__stops">
        <template v-for="(item, index) in tour.stops" :key="item.id">
          <li class="stop" :class="{ 'stop--done': item.done, 'stop--next': nextId === item.id }">
            <span class="stop__icon"><v-icon :icon="STOP_ICON[item.kind]" size="18" /></span>
            <span class="stop__body">
              <strong>{{ item.place }}</strong>
              <span>{{ t(`grossanlass.dispo.stopKind.${item.kind}`) }} · {{ item.label }}</span>
            </span>
            <v-icon v-if="item.done" icon="mdi-check-circle" color="success" size="20" />
          </li>
          <li v-if="legs[index]" class="leg" :class="{ 'leg--empty': legs[index]!.empty }">
            <v-icon icon="mdi-arrow-down" size="14" />
            <span v-if="legs[index]!.empty">{{ t('grossanlass.dispo.emptyLeg') }}</span>
            <span v-else>{{ t('grossanlass.dispo.loadedLeg') }}</span>
          </li>
        </template>
      </ol>

      <section class="tour__live">
        <h4>{{ t('grossanlass.dispo.live.title') }}</h4>
        <div class="tour__live-row">
          <ESelect
            v-model="insertNeedId"
            :items="insertItems"
            :label="t('grossanlass.dispo.live.insert')"
            clearable
            hide-details
          />
          <EButton variant="secondary" :disabled="!insertNeedId" @click="insert">
            {{ t('grossanlass.dispo.live.insertAction') }}
          </EButton>
        </div>
        <div class="tour__live-row tour__live-row--buttons">
          <EButton variant="secondary" size="small" :disabled="tour.status === 'done'" @click="delayTour(tour, 15)">
            <v-icon icon="mdi-clock-alert-outline" start size="16" /> {{ t('grossanlass.dispo.live.delayed') }}
          </EButton>
          <EButton variant="secondary" size="small" :disabled="tour.status === 'done'" @click="vehicleDefect(tour)">
            <v-icon icon="mdi-car-wrench" start size="16" /> {{ t('grossanlass.dispo.live.vehicleDefect') }}
          </EButton>
        </div>
        <div v-if="tour.problem?.kind === 'vehicleDefect'" class="tour__live-row">
          <ESelect
            v-model="replacementId"
            :items="replacementItems"
            :label="t('grossanlass.dispo.live.replacement')"
            hide-details
          />
          <EButton variant="primary" :disabled="!replacementId" @click="swapVehicle">
            {{ t('grossanlass.dispo.live.replaceAction') }}
          </EButton>
        </div>
      </section>
    </div>

    <template #actions>
      <EButton variant="secondary" @click="open = false">{{ t('common.close') }}</EButton>
      <EButton v-if="tour?.status === 'planned'" variant="primary" @click="startTour(tour)">
        {{ t('grossanlass.dispo.startTour') }}
      </EButton>
      <EButton v-if="tour && tour.status !== 'done'" variant="primary" @click="advanceTour(tour)">
        {{ t('grossanlass.dispo.nextStop') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog, ESelect } from '@/components/form/base'
import {
  advanceTour,
  assignNeed,
  clearTourProblem,
  delayTour,
  driverName,
  replaceVehicle,
  startTour,
  tourById,
  tourLegs,
  useGaDispoMock,
  vehicleDefect,
  vehicleName,
  freeVehicles,
} from './gaDispoMock'
import { STOP_ICON } from './gaDispoUi'

const props = defineProps<{ modelValue: boolean; tourId: string | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { t } = useI18n()
const { needs } = useGaDispoMock()

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const tour = computed(() => tourById(props.tourId ?? undefined))
const legs = computed(() => (tour.value ? tourLegs(tour.value) : []))
const nextId = computed(() => tour.value?.stops.find((item) => !item.done)?.id)

const insertNeedId = ref<string | null>(null)
const insertItems = computed(() =>
  needs.value
    .filter((need) => need.status === 'open')
    .map((need) => ({ title: `${need.title} (${need.timingLabel})`, value: need.id })),
)
function insert() {
  if (!tour.value || !insertNeedId.value) return
  assignNeed(insertNeedId.value, { mode: 'existing', tourId: tour.value.id })
  insertNeedId.value = null
}

const replacementId = ref<string | null>(null)
const replacementItems = computed(() => freeVehicles().map((vehicle) => ({ title: vehicle.name, value: vehicle.id })))
function swapVehicle() {
  if (!tour.value || !replacementId.value) return
  replaceVehicle(tour.value, replacementId.value)
  replacementId.value = null
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
.tour__live h4 {
  margin: 0 0 8px;
  font-size: 0.75rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}
.tour__live-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
  margin-bottom: 8px;
}
.tour__live-row--buttons {
  display: flex;
  flex-wrap: wrap;
}
</style>
