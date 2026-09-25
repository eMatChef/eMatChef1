<template>
  <div class="ga-preview-page">
    <p class="ga-preview-intro">{{ t('grossanlass.fahrzeuge.fleetIntro') }}</p>

    <ELoadingState v-if="loading" variant="inline" :message="t('common.loading')" />

    <template v-else>
      <section class="ga-fleet-section">
        <div class="ga-fleet-section__head">
          <h2>{{ t('grossanlass.fahrzeuge.sectionFleet') }}</h2>
          <EButton v-if="canManage" variant="primary" size="small" @click="openCreate()">
            {{ t('grossanlass.fahrzeuge.add') }}
          </EButton>
        </div>
        <p v-if="vehicles.length === 0" class="ga-fleet-empty">{{ t('grossanlass.fahrzeuge.emptyFleet') }}</p>
        <ul v-else class="ga-fleet-list">
          <li v-for="vehicle in vehicles" :key="vehicle.id" class="ga-fleet-card">
            <button type="button" class="ga-fleet-card__main ga-fleet-card__link" @click="openVehicle(vehicle.id)">
              <p class="ga-fleet-card__title">
                {{ vehicle.name }}
                <span v-if="vehicle.plate" class="ga-fleet-card__plate">{{ vehicle.plate }}</span>
              </p>
              <p class="ga-fleet-card__meta">
                <span>{{ originLabel(vehicle.origin) }}</span>
                <span v-if="vehicle.source">{{ vehicle.source }}</span>
                <span :class="vehicle.released ? 'ga-fleet-chip ga-fleet-chip--on' : 'ga-fleet-chip'">
                  {{ vehicle.released
                    ? t('grossanlass.fahrzeuge.released')
                    : t('grossanlass.fahrzeuge.held') }}
                </span>
              </p>
              <p class="ga-fleet-card__meta">
                <span>{{ t('grossanlass.fahrzeuge.colWindow') }}: {{ windowText(vehicle.present_from, vehicle.present_to) }}</span>
              </p>
              <p class="ga-fleet-card__meta">
                <span>{{ t('grossanlass.fahrzeuge.colHandover') }}: {{ windowText(vehicle.handover_from, vehicle.handover_to) }}</span>
                <span>{{ t('grossanlass.fahrzeuge.colReturn') }}: {{ windowText(vehicle.return_from, vehicle.return_to) }}</span>
              </p>
              <p class="ga-fleet-card__meta">
                {{ serviceText(vehicle) }}
              </p>
            </button>
          </li>
        </ul>
      </section>
    </template>

    <GrossanlassZusageCreatePreviewDialog
      v-model="createOpen"
      vehicle-only
      :preset="createPreset"
      @created="onCreated"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { EButton } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassZusageCreatePreviewDialog from '@/views/grossanlass/GrossanlassZusageCreatePreviewDialog.vue'
import type { GrossanlassCommitment } from '@/api/grossanlassCommitments'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import { formatGaIsoLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'
import type { GaZusageCreateDraft } from '@/views/grossanlass/grossanlassZusagePreviewStore'
import { gaCanManageProcurement } from '@/utils/grossanlassAccess'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t, locale } = useI18n()
const catalog = useGaCommitmentCatalog()
const uebersicht = useGaUebersicht()

const createOpen = ref(false)
const createPreset = ref<Partial<GaZusageCreateDraft> | null>(null)

const departmentId = computed(() => {
  return (route.params.departmentId as string) || authStore.activeDepartmentId || ''
})

const canManage = computed(() => gaCanManageProcurement(authStore.currentDepartmentRole))

const loading = computed(() => catalog.loading.value || uebersicht.loading.value)

const vehicles = computed(() =>
  catalog.commitments.value
    .filter((row) => row.family === 'vehicle')
    .slice()
    .sort((a, b) => a.name.localeCompare(b.name, locale.value)),
)

function windowText(from: string | null | undefined, to: string | null | undefined): string {
  if (!from && !to) return t('grossanlass.fahrzeuge.noWindow')
  const loc = locale.value
  const start = from ? formatGaIsoLabel(from, loc) : '…'
  const end = to ? formatGaIsoLabel(to, loc) : '…'
  return `${start} – ${end}`
}

function originLabel(origin: GrossanlassCommitment['origin']): string {
  if (origin === 'buy') return t('grossanlass.materials.lifecycle.reusable')
  if (origin === 'buy_resale') return t('grossanlass.materials.lifecycle.buy_resale')
  return t('grossanlass.materials.lifecycle.loan')
}

function serviceText(vehicle: GrossanlassCommitment): string {
  const services = vehicle.services ?? []
  if (services.length === 0) return t('grossanlass.fahrzeuge.noService')
  const labels = services.map((service) => {
    if (service.kind === 'clean' || service.kind === 'grease' || service.kind === 'other') {
      return t(`grossanlass.materials.zusage.service.${service.kind}`)
    }
    return service.label || service.kind
  })
  return `${t('grossanlass.fahrzeuge.colService')}: ${labels.join(', ')}`
}

function openVehicle(id: string) {
  const department = departmentId.value
  if (!department) return
  void router.push(`/${department}/fahrzeuge/artikel/${id}`)
}

function openCreate() {
  createPreset.value = {
    family: 'vehicle',
    origin: 'loan',
    name: '',
    source: '',
  }
  createOpen.value = true
}

function onCreated(row: GrossanlassCommitment) {
  catalog.upsert(row)
  const department = departmentId.value
  if (department) {
    void router.push(`/${department}/fahrzeuge/artikel/${row.id}`)
  }
}
</script>

<style scoped>
.ga-preview-page { padding: 8px 0 24px; }
.ga-preview-intro { margin: 0 0 20px; color: #64748b; font-size: 0.9rem; }
.ga-fleet-section { margin-bottom: 28px; }
.ga-fleet-section__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}
.ga-fleet-section__head h2 { margin: 0; font-size: 1.05rem; }
.ga-fleet-empty { margin: 0; color: #64748b; font-size: 0.9rem; }
.ga-fleet-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.ga-fleet-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #fff;
}
.ga-fleet-card__main { min-width: 0; text-align: left; }
.ga-fleet-card__link {
  flex: 1;
  border: 0;
  background: transparent;
  padding: 0;
  cursor: pointer;
  color: inherit;
  font: inherit;
}
.ga-fleet-card__title { margin: 0 0 4px; font-weight: 600; }
.ga-fleet-card__plate { margin-left: 8px; font-weight: 500; color: #475569; }
.ga-fleet-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  margin: 0 0 4px;
  color: #64748b;
  font-size: 0.85rem;
}
.ga-fleet-card__open { margin: 4px 0 0; color: #9a3412; font-size: 0.85rem; }
.ga-fleet-chip {
  display: inline-flex;
  align-items: center;
  padding: 0 8px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #334155;
  font-size: 0.78rem;
}
.ga-fleet-chip--on { background: #dcfce7; color: #166534; }
</style>
