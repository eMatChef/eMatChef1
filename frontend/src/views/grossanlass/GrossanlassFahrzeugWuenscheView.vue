<template>
  <div class="ga-preview-page">
    <p class="ga-preview-intro">{{ t('grossanlass.fahrzeuge.wishIntro') }}</p>

    <div class="ga-fleet-section__head">
      <span />
      <EButton v-if="canManage" variant="primary" size="small" :disabled="!wishRound" @click="openWish()">
        {{ t('grossanlass.fahrzeuge.addWish') }}
      </EButton>
    </div>
    <p v-if="!loading && !wishRound" class="ga-fleet-empty">{{ t('grossanlass.fahrzeuge.noRound') }}</p>

    <ELoadingState v-if="loading" variant="inline" :message="t('common.loading')" />

    <template v-else>
      <p v-if="vehicleWishes.length === 0" class="ga-fleet-empty">{{ t('grossanlass.fahrzeuge.emptyWishes') }}</p>
      <ul v-else class="ga-fleet-list">
        <li v-for="wish in vehicleWishes" :key="wish.id" class="ga-fleet-card">
          <div class="ga-fleet-card__main">
            <p class="ga-fleet-card__title">{{ wish.label }}</p>
            <p class="ga-fleet-card__meta">
              <span class="ga-fleet-chip">{{ t('grossanlass.fahrzeuge.qtyShort', { n: wish.qty }) }}</span>
              <span v-if="wish.ressort">{{ wish.ressort }}</span>
              <span>{{ windowText(wish.from, wish.to) }}</span>
              <span :class="wish.covered ? 'ga-fleet-chip ga-fleet-chip--on' : 'ga-fleet-chip'">
                {{ wish.covered ? t('grossanlass.fahrzeuge.covered') : t('grossanlass.fahrzeuge.openVehicle') }}
              </span>
            </p>
            <p v-if="wish.covered && wish.objectName" class="ga-fleet-card__meta">{{ wish.objectName }}</p>
          </div>
          <EButton
            v-if="canManage && !wish.covered"
            variant="secondary"
            size="small"
            @click="openCommit(wish)"
          >
            {{ t('grossanlass.fahrzeuge.fulfill') }}
          </EButton>
        </li>
      </ul>

      <section v-if="openTrips.length" class="ga-fleet-section">
        <h2>{{ t('grossanlass.fahrzeuge.sourceTrip') }}</h2>
        <ul class="ga-fleet-list">
          <li v-for="trip in openTrips" :key="trip.id" class="ga-fleet-card">
            <div class="ga-fleet-card__main">
              <p class="ga-fleet-card__title">{{ trip.label }}</p>
              <p class="ga-fleet-card__meta">
                <span v-if="trip.ressort">{{ trip.ressort }}</span>
                <span>{{ windowText(trip.from, trip.to) }}</span>
                <span class="ga-fleet-card__open">{{ t('grossanlass.fahrzeuge.openVehicle') }}</span>
              </p>
            </div>
            <EButton v-if="canManage" variant="secondary" size="small" @click="openCommitTrip(trip)">
              {{ t('grossanlass.fahrzeuge.fulfill') }}
            </EButton>
          </li>
        </ul>
      </section>
    </template>

    <EDialog v-model="wishOpen" :title="t('grossanlass.fahrzeuge.wishDialogTitle')" :max-width="560">
      <div class="ga-wish-form">
      <ETextField
        v-model="wishLabel"
        :label="t('grossanlass.fahrzeuge.fieldClass')"
        :placeholder="t('grossanlass.fahrzeuge.fieldClassPlaceholder')"
        hide-details
      />
      <ETextField v-model="quantity" type="number" min="1" :label="t('grossanlass.fahrzeuge.fieldQty')" hide-details />
      <ESelect
        v-model="groupId"
        :items="groupItems"
        item-title="title"
        item-value="value"
        :label="t('grossanlass.fahrzeuge.fieldGroup')"
        hide-details
      />
      <ETextField v-model="place" :label="t('grossanlass.fahrzeuge.fieldPlace')" hide-details />
      <EDateRangeField
        v-model:start="fromDate"
        v-model:end="toDate"
        :department-id="departmentId"
        :label="t('grossanlass.fahrzeuge.colWindow')"
      />
      <ESelect
        v-for="field in phaseFields"
        :key="field.id"
        v-model="customValues[field.id]"
        :items="choiceItems(field)"
        item-title="title"
        item-value="value"
        :label="field.label"
        :multiple="field.options?.multiple === true"
        hide-details
      />
      <ETextarea v-model="note" :label="t('grossanlass.fahrzeuge.fieldNote')" rows="2" hide-details />
      </div>
      <template #actions>
        <EButton variant="secondary" size="small" @click="wishOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :disabled="!canSaveWish || savingWish" @click="saveWish">
          {{ t('grossanlass.fahrzeuge.addWish') }}
        </EButton>
      </template>
    </EDialog>

    <GrossanlassZusageCreatePreviewDialog
      v-model="commitOpen"
      vehicle-only
      :preset="commitPreset"
      @created="onCommitted"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { EButton, EDateRangeField, EDialog, ESelect, ETextarea, ETextField } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassZusageCreatePreviewDialog from '@/views/grossanlass/GrossanlassZusageCreatePreviewDialog.vue'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import { getGrossanlassPlanningRounds, type GrossanlassPlanningRound } from '@/api/grossanlassRounds'
import { getGrossanlassRoundForm, type GrossanlassRoundFormField } from '@/api/grossanlassRoundForm'
import { createGrossanlassWish } from '@/api/grossanlassWishes'
import { updateGrossanlassEinsatz, type GaUebersichtWish } from '@/api/grossanlassUebersicht'
import type { GrossanlassCommitment } from '@/api/grossanlassCommitments'
import { useGaCommitmentCatalog } from '@/views/grossanlass/gaCommitmentCatalog'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import { combineIso, formatGaIsoLabel } from '@/views/grossanlass/grossanlassZusagePreviewData'
import type { GaZusageCreateDraft } from '@/views/grossanlass/grossanlassZusagePreviewStore'
import { gaCanManageProcurement } from '@/utils/grossanlassAccess'
import { useToast } from '@/composables/useToast'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { t, locale } = useI18n()
const toast = useToast()
const catalog = useGaCommitmentCatalog()
const uebersicht = useGaUebersicht()

const wishRound = ref<GrossanlassPlanningRound | null>(null)
const groups = ref<GrossanlassGroup[]>([])
const phaseFields = ref<GrossanlassRoundFormField[]>([])
const wishOpen = ref(false)
const savingWish = ref(false)
const wishLabel = ref('')
const quantity = ref<number | string>(1)
const groupId = ref<string | null>(null)
const place = ref('')
const fromDate = ref('')
const toDate = ref('')
const note = ref('')
const customValues = reactive<Record<string, string | string[]>>({})

const commitOpen = ref(false)
const commitPreset = ref<Partial<GaZusageCreateDraft> | null>(null)
const pendingEinsatzId = ref('')

const departmentId = computed(() => (route.params.departmentId as string) || authStore.activeDepartmentId || '')
const canManage = computed(() => gaCanManageProcurement(authStore.currentDepartmentRole))
const loading = computed(() => catalog.loading.value || uebersicht.loading.value)

const vehicleIds = computed(() => new Set(
  catalog.commitments.value.filter((row) => row.family === 'vehicle').map((row) => row.id),
))

const vehicleWishes = computed(() =>
  (uebersicht.data.value?.wishes ?? [])
    .filter((wish) => wish.wish_kind === 'fahrzeug' || wish.wish_kind === 'beides')
    .map((wish) => ({
      ...wish,
      covered: Boolean(wish.object_id && vehicleIds.value.has(wish.object_id)),
      objectName: wish.object_name,
    })),
)

const openTrips = computed(() => {
  const rows = [
    ...(uebersicht.data.value?.einsaetze ?? []),
    ...(uebersicht.data.value?.orders ?? []),
  ]
  return rows
    .filter((row) => row.delivery === 'trip')
    .filter((row) => row.object_family !== 'vehicle' && !(row.object_id && vehicleIds.value.has(row.object_id)))
    .filter((row) => !row.object_id)
    .map((row) => ({
      id: row.id,
      label: row.object_name || row.who || t('grossanlass.fahrzeuge.openVehicle'),
      ressort: row.ressort,
      from: row.from,
      to: row.to,
      wishId: row.wish_line_id,
    }))
})

const groupItems = computed(() =>
  groups.value
    .slice()
    .sort((a, b) => a.name.localeCompare(b.name, locale.value))
    .map((group) => ({ title: group.name, value: group.id })),
)

const canSaveWish = computed(() => Boolean(
  wishRound.value
  && wishLabel.value.trim()
  && groupId.value
  && fromDate.value
  && toDate.value
  && phaseFields.value.every((field) => {
    const value = customValues[field.id]
    return Array.isArray(value) ? value.length > 0 : Boolean(value)
  }),
))

function choiceItems(field: GrossanlassRoundFormField) {
  return (field.options?.choices ?? []).map((choice) => ({ title: choice, value: choice }))
}

function windowText(from: string | null | undefined, to: string | null | undefined): string {
  if (!from && !to) return t('grossanlass.fahrzeuge.noWindow')
  const loc = locale.value
  const start = from ? formatGaIsoLabel(from, loc) : '…'
  const end = to ? formatGaIsoLabel(to, loc) : '…'
  return `${start} – ${end}`
}

function splitIso(iso: string | null | undefined): { date: string; time: string } | null {
  if (!iso) return null
  const match = iso.match(/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/)
  if (!match) return null
  return { date: match[1], time: match[2] }
}

function pickRound(rounds: GrossanlassPlanningRound[]): GrossanlassPlanningRound | null {
  const open = rounds.filter((row) => row.status === 'open' && row.form_purpose === 'material_wish')
  return open.find((row) => row.material_stage === 'grob') ?? open[0] ?? null
}

onMounted(async () => {
  const department = departmentId.value
  if (!department) return
  try {
    const [rounds, groupList] = await Promise.all([
      getGrossanlassPlanningRounds(department),
      getGrossanlassGroups(department),
    ])
    groups.value = groupList
    wishRound.value = pickRound(rounds)
    if (wishRound.value) {
      const form = await getGrossanlassRoundForm(department, wishRound.value.id)
      phaseFields.value = form.fields.filter((field) =>
        field.enabled
        && field.required
        && field.role === 'input'
        && field.custom_type === 'select',
      )
    }
  } catch {
    wishRound.value = null
  }
})

function openWish() {
  wishLabel.value = ''
  quantity.value = 1
  groupId.value = groupItems.value[0]?.value ?? null
  place.value = ''
  fromDate.value = ''
  toDate.value = ''
  note.value = ''
  for (const field of phaseFields.value) {
    customValues[field.id] = field.options?.multiple ? [] : (field.options?.choices?.[0] ?? '')
  }
  wishOpen.value = true
}

async function saveWish() {
  const department = departmentId.value
  const round = wishRound.value
  if (!department || !round || !canSaveWish.value || !groupId.value) return
  savingWish.value = true
  try {
    const custom_values: Record<string, unknown> = {}
    for (const field of phaseFields.value) {
      custom_values[field.id] = customValues[field.id]
    }
    await createGrossanlassWish(department, round.id, {
      wish_kind: 'fahrzeug',
      label: wishLabel.value.trim(),
      quantity: Math.max(1, Number(quantity.value) || 1),
      group_id: groupId.value,
      location: place.value.trim() || t('grossanlass.fahrzeuge.title'),
      valid_from: combineIso(fromDate.value, '08:00'),
      valid_to: combineIso(toDate.value, '18:00'),
      notes: note.value.trim() || null,
      custom_values,
    })
    toast.success(t('grossanlass.fahrzeuge.wishCreated'))
    wishOpen.value = false
    await uebersicht.load({ silent: true })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.fahrzeuge.wishError'))
  } finally {
    savingWish.value = false
  }
}

function openCommit(wish: GaUebersichtWish & { covered: boolean }) {
  pendingEinsatzId.value = ''
  const from = splitIso(wish.from)
  const to = splitIso(wish.to)
  commitPreset.value = {
    family: 'vehicle',
    origin: 'loan',
    name: wish.label,
    source: '',
    fromLineId: wish.id,
    presentFromDate: from?.date,
    presentFromTime: from?.time,
    presentToDate: to?.date,
    presentToTime: to?.time,
    handoverDate: from?.date,
    returnDate: to?.date,
  }
  commitOpen.value = true
}

function openCommitTrip(trip: { id: string; label: string; from: string; to: string; wishId: string | null }) {
  pendingEinsatzId.value = trip.id
  const from = splitIso(trip.from)
  const to = splitIso(trip.to)
  commitPreset.value = {
    family: 'vehicle',
    origin: 'loan',
    name: trip.label,
    source: '',
    fromLineId: trip.wishId || undefined,
    presentFromDate: from?.date,
    presentFromTime: from?.time,
    presentToDate: to?.date,
    presentToTime: to?.time,
    handoverDate: from?.date,
    returnDate: to?.date,
  }
  commitOpen.value = true
}

async function onCommitted(row: GrossanlassCommitment) {
  const link = pendingEinsatzId.value
  pendingEinsatzId.value = ''
  catalog.upsert(row)
  const department = departmentId.value
  if (link && department) {
    try {
      await updateGrossanlassEinsatz(department, link, { commitment_id: row.id })
    } catch (e: unknown) {
      const err = e as { response?: { data?: { error?: string } } }
      toast.error(err.response?.data?.error || t('grossanlass.fahrzeuge.linkError'))
    }
  }
  await uebersicht.load({ silent: true })
  if (department) void router.push(`/${department}/fahrzeuge/artikel/${row.id}`)
}
</script>

<style scoped>
.ga-wish-form { display: flex; flex-direction: column; gap: 12px; }
.ga-preview-page { padding: 8px 0 24px; }
.ga-preview-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.ga-fleet-section { margin-top: 28px; }
.ga-fleet-section h2 { margin: 0 0 12px; font-size: 1.05rem; }
.ga-fleet-section__head {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 12px;
}
.ga-fleet-empty { margin: 0 0 12px; color: #64748b; font-size: 0.9rem; }
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
.ga-fleet-card__main { min-width: 0; }
.ga-fleet-card__title { margin: 0 0 4px; font-weight: 600; }
.ga-fleet-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  margin: 0;
  color: #64748b;
  font-size: 0.85rem;
}
.ga-fleet-card__open { color: #9a3412; }
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
