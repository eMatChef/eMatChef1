<template>
  <div class="ga-gaeste">
    <p class="tab-intro">{{ t('grossanlass.materials.gaeste.intro') }}</p>

    <div class="ga-gaeste__toolbar">
      <div class="view-toggle" role="tablist">
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'bestand' }"
          @click="view = 'bestand'"
        >
          {{ t('grossanlass.materials.gaeste.viewBestand') }}
        </button>
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'loan' }"
          @click="view = 'loan'"
        >
          {{ t('grossanlass.materials.gaeste.viewLoan') }}
        </button>
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'sale' }"
          @click="view = 'sale'"
        >
          {{ t('grossanlass.materials.gaeste.viewSale') }}
        </button>
      </div>
      <ESearchField
        v-model="query"
        class="ga-gaeste__search"
        :label="t('grossanlass.materials.gaeste.search')"
      />
    </div>

    <ELoadingState v-if="loading" variant="inline" :message="t('common.loading')" />

    <template v-else-if="view === 'bestand'">
      <p class="section-hint">{{ t('grossanlass.materials.gaeste.bestandHint') }}</p>
      <v-expansion-panels v-if="bestandGroups.length" v-model="openBestand" multiple class="e-accordions">
        <v-expansion-panel v-for="group in bestandGroups" :key="group.id" :value="group.id">
          <v-expansion-panel-title>
            <span class="panel-head">
              <span class="panel-head__label">
                {{ group.label }}
                <span class="panel-head__count">{{ group.rows.length }}</span>
              </span>
            </span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <ul class="loan-list">
              <li v-for="row in group.rows" :key="row.id" class="loan-card">
                <div class="loan-card__head">
                  <div>
                    <strong>{{ row.name }}</strong>
                    <span class="chip is-no">{{ t('grossanlass.materials.gaeste.campChip') }}</span>
                    <span v-if="row.share_status === 'offered' || row.share_status === 'accepted'" class="chip is-ok">
                      {{ t('grossanlass.materials.gaeste.releasedToUs') }}
                    </span>
                  </div>
                  <span class="qty">{{ t('grossanlass.materials.gaeste.qtyLine', { n: row.qty }) }}</span>
                </div>
              </li>
            </ul>
            <p v-if="!group.rows.length" class="muted">{{ t('grossanlass.materials.gaeste.emptyDeptStock') }}</p>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
      <EEmptyState
        v-else
        :title="t('grossanlass.materials.gaeste.emptyGuestsTitle')"
        :description="t('grossanlass.materials.gaeste.emptyGuestsText')"
      />
    </template>

    <template v-else-if="view === 'loan'">
      <p class="section-hint">{{ t('grossanlass.materials.gaeste.loanHint') }}</p>
      <v-expansion-panels v-if="loanGroups.length" v-model="openLoan" multiple class="e-accordions">
        <v-expansion-panel v-for="group in loanGroups" :key="group.id" :value="group.id">
          <v-expansion-panel-title>
            <span class="panel-head">
              <span class="panel-head__label">
                {{ group.label }}
                <span class="panel-head__count">{{ group.rows.length }}</span>
                <span v-if="group.offeredCount" class="chip is-wait">
                  {{ t('grossanlass.materials.gaeste.offeredCount', { count: group.offeredCount }) }}
                </span>
              </span>
            </span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <ul class="loan-list">
              <li v-for="row in group.rows" :key="row.id" class="loan-card">
                <div class="loan-card__head">
                  <div>
                    <strong>{{ row.name }}</strong>
                    <span class="chip" :class="statusClass(row.status)">
                      {{ t(`grossanlass.materials.gaeste.loanStatus.${row.status}`) }}
                    </span>
                  </div>
                  <span class="qty">{{ t('grossanlass.materials.gaeste.qtyLine', { n: row.qty }) }}</span>
                </div>
                <p v-if="row.from_label && row.to_label">
                  {{ t('grossanlass.materials.gaeste.loanWindow', { from: row.from_label, to: row.to_label }) }}
                </p>
                <div class="loan-card__actions">
                  <EButton
                    v-if="row.status === 'offered'"
                    variant="primary"
                    size="small"
                    @click="acceptLoan(row.id)"
                  >
                    {{ t('grossanlass.materials.gaeste.acceptLoan') }}
                  </EButton>
                  <EButton
                    variant="secondary"
                    size="small"
                    :disabled="!row.bookable"
                    @click="bookLoan(row)"
                  >
                    {{ t('grossanlass.materials.gaeste.bookEinsatz') }}
                  </EButton>
                </div>
              </li>
            </ul>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
      <EEmptyState
        v-else
        :title="t('grossanlass.materials.gaeste.emptyLoanTitle')"
        :description="t('grossanlass.materials.gaeste.emptyLoanText')"
      />
    </template>

    <template v-else-if="view === 'sale'">
      <p class="section-hint">{{ t('grossanlass.materials.gaeste.saleHint') }}</p>
      <div class="sale-form">
        <ESelect
          v-model="saleGuestId"
          :label="t('grossanlass.materials.gaeste.saleGuest')"
          :items="guestSelectItems"
          hide-details
        />
        <ESelect
          v-model="saleStockId"
          :label="t('grossanlass.materials.gaeste.saleItem')"
          :items="saleStockItems"
          hide-details
        />
        <ETextField
          v-model="saleAmountChf"
          type="number"
          inputmode="decimal"
          step="0.01"
          min="0"
          :label="t('grossanlass.materials.gaeste.saleAmount')"
          :hint="t('grossanlass.materials.gaeste.saleAmountHint')"
          persistent-hint
        />
        <EButton variant="primary" size="small" :disabled="!canSell" @click="sellNow">
          {{ t('grossanlass.materials.gaeste.saleConfirm') }}
        </EButton>
      </div>
      <ul v-if="filteredSales.length" class="loan-list">
        <li v-for="row in filteredSales" :key="row.id" class="loan-card">
          <div class="loan-card__head">
            <div>
              <strong>{{ row.name }}</strong>
              <span class="chip is-ok">{{ t('grossanlass.materials.gaeste.soldChip') }}</span>
            </div>
            <span class="qty">{{ t('grossanlass.materials.gaeste.qtyLine', { n: row.qty }) }}</span>
          </div>
          <p>{{ row.guest_name }}</p>
        </li>
      </ul>
      <p v-else class="muted">{{ t('grossanlass.materials.gaeste.emptySales') }}</p>
    </template>

    <GrossanlassEinsatzBookPreviewDialog
      v-model="bookOpen"
      v-model:draft="bookDraft"
      mode="einsatz"
      :wishes="[]"
      :free-picks="guestPicks"
      :resources="guestResources"
      :chauffeurs="chauffeurs"
      :places="uebersicht.data?.places ?? []"
      @confirm="onBooked"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EButton, ESearchField, ESelect, ETextField } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GrossanlassEinsatzBookPreviewDialog, {
  type GaBookPreviewDraft,
} from '@/views/grossanlass/GrossanlassEinsatzBookPreviewDialog.vue'
import {
  acceptGrossanlassGuestLoan,
  getGrossanlassGaeste,
  sellGrossanlassToGuest,
  type GaGaestePayload,
  type GaGuestShare,
  type GaGuestShareStatus,
} from '@/api/grossanlassGaeste'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import { resourceToPickTemplate, type GaEinsatzResource } from '@/views/grossanlass/grossanlassEinsatzPreviewData'

type GaesteView = 'bestand' | 'loan' | 'sale'

const { t } = useI18n()
const toast = useToast()

function tr(key: string, values?: Record<string, string | number>): string {
  return values ? String(t(key, values)) : String(t(key))
}
const route = useRoute()
const uebersicht = useGaUebersicht()
const view = ref<GaesteView>('bestand')
const query = ref('')
const loading = ref(false)
const payload = ref<GaGaestePayload | null>(null)
const openLoan = ref<string[]>([])
const openBestand = ref<string[]>([])
const bookOpen = ref(false)
const bookDraft = ref<GaBookPreviewDraft | null>(null)
const saleGuestId = ref<string | null>(null)
const saleStockId = ref<string | null>(null)
const saleAmountChf = ref('')

const departmentId = computed(() => String(route.params.departmentId || ''))

async function load() {
  if (!departmentId.value) return
  loading.value = true
  try {
    payload.value = await getGrossanlassGaeste(departmentId.value)
    openBestand.value = (payload.value.departments ?? []).map((row) => row.id)
    openLoan.value = [...new Set((payload.value.offers ?? []).map((row) => row.guest_department_id))]
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
    payload.value = { departments: [], offers: [], sales: [], sale_stock: [], js: { catalog_name: null, articles: [] } }
  } finally {
    loading.value = false
  }
}

onMounted(() => { void load() })

const q = computed(() => query.value.trim().toLowerCase())

const bestandGroups = computed(() =>
  (payload.value?.departments ?? [])
    .map((dept) => ({
      id: dept.id,
      label: dept.name,
      rows: dept.items.filter((item) => {
        if (!q.value) return true
        return item.name.toLowerCase().includes(q.value) || dept.name.toLowerCase().includes(q.value)
      }),
    }))
    .filter((group) => group.rows.length || !q.value),
)

const loanGroups = computed(() => {
  const rows = (payload.value?.offers ?? []).filter((row) => {
    if (!q.value) return true
    return row.name.toLowerCase().includes(q.value) || row.guest_name.toLowerCase().includes(q.value)
  })
  const buckets = new Map<string, { id: string; label: string; rows: GaGuestShare[] }>()
  for (const row of rows) {
    const bucket = buckets.get(row.guest_department_id) ?? {
      id: row.guest_department_id,
      label: row.guest_name,
      rows: [],
    }
    bucket.rows.push(row)
    buckets.set(row.guest_department_id, bucket)
  }
  return [...buckets.values()].map((group) => ({
    ...group,
    offeredCount: group.rows.filter((row) => row.status === 'offered').length,
  }))
})

const filteredSales = computed(() => {
  const rows = payload.value?.sales ?? []
  if (!q.value) return rows
  return rows.filter((row) =>
    row.name.toLowerCase().includes(q.value) || row.guest_name.toLowerCase().includes(q.value),
  )
})

const guestSelectItems = computed(() =>
  (payload.value?.departments ?? []).map((dept) => ({ title: dept.name, value: dept.id })),
)

const saleStockItems = computed(() =>
  (payload.value?.sale_stock ?? []).map((row) => ({
    title: `${row.name} · ${originLabel(row.origin)} · ${t('grossanlass.materials.gaeste.qtyLine', { n: row.qty })}`,
    value: row.id,
  })),
)

function originLabel(origin: string): string {
  if (origin === 'buy_resale') return String(t('grossanlass.materials.lifecycle.buy_resale'))
  if (origin === 'buy') return String(t('grossanlass.materials.lifecycle.reusable'))
  return String(t('grossanlass.materials.lifecycle.loan'))
}

const canSell = computed(() => {
  if (!saleGuestId.value || !saleStockId.value || saleAmountChf.value === '') return false
  const n = Number(String(saleAmountChf.value).replace(/['’\s]/g, '').replace(',', '.'))
  return !Number.isNaN(n) && n >= 0
})

const guestResources = computed<GaEinsatzResource[]>(() =>
  (payload.value?.offers ?? [])
    .filter((row) => row.bookable && row.commitment_id)
    .map((row) => ({
      id: row.commitment_id as string,
      name: `${row.name} (${row.guest_name})`,
      family: row.family,
      stayMode: 'return' as const,
      categoryId: row.family === 'vehicle' ? 'fahrzeuge' : 'infra',
      kind: row.family === 'vehicle' ? 'unique' : 'quantity',
      stock: row.qty,
      presentFromIso: row.from || '',
      presentToIso: row.to || '',
      released: true,
    })),
)

const guestPicks = computed(() =>
  guestResources.value.map((resource) => resourceToPickTemplate(resource, tr)),
)

const chauffeurs = computed(() =>
  (uebersicht.data.value?.cards ?? []).map((card) => ({
    value: card.user_id,
    title: card.name,
    subtitle: card.may_drive
      ? t('grossanlass.materialUebersicht.chauffeurMayDrive')
      : t('grossanlass.materialUebersicht.chauffeurNoLicenseShort'),
    mayDrive: card.may_drive,
  })),
)

function statusClass(status: GaGuestShareStatus | string): string {
  if (status === 'accepted' || status === 'completed') return 'is-ok'
  if (status === 'declined') return 'is-no'
  return 'is-wait'
}

async function acceptLoan(id: string) {
  try {
    payload.value = await acceptGrossanlassGuestLoan(departmentId.value, id)
    toast.success(t('grossanlass.materials.gaeste.acceptedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

function bookLoan(row: GaGuestShare) {
  const pick = guestPicks.value.find((item) => item.objectId === row.commitment_id)
  bookDraft.value = pick ? { ...pick, fromWish: false } : null
  bookOpen.value = true
}

async function onBooked(current: GaBookPreviewDraft) {
  try {
    await uebersicht.create({
      kind: 'einsatz',
      commitment_id: current.objectId || undefined,
      qty: current.qty,
      from: current.fromIso,
      to: current.toIso,
      who: current.who,
      chauffeur_user_id: current.chauffeurUserId || null,
      pending: current.hasConflict,
      has_conflict: current.hasConflict,
    })
    toast.success(t('grossanlass.materials.gaeste.bookedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

async function sellNow() {
  if (!saleGuestId.value || !saleStockId.value || !canSell.value) return
  const amount = Number(String(saleAmountChf.value).replace(/['’\s]/g, '').replace(',', '.'))
  try {
    payload.value = await sellGrossanlassToGuest(departmentId.value, {
      guest_department_id: saleGuestId.value,
      commitment_id: saleStockId.value,
      amount_chf: amount,
    })
    toast.success(t('grossanlass.materials.gaeste.soldToast'))
    saleStockId.value = null
    saleAmountChf.value = ''
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

</script>

<style scoped>
.ga-gaeste { padding: 8px 0 24px; }
.tab-intro { margin: 0 0 12px; color: #64748b; font-size: 0.9rem; }
.ga-gaeste__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 12px;
  align-items: center;
  margin-bottom: 14px;
}
.ga-gaeste__search { flex: 1 1 220px; min-width: min(100%, 180px); }
.view-toggle {
  display: inline-flex;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
}
.view-toggle__btn {
  border: 0;
  background: transparent;
  padding: 8px 12px;
  font: inherit;
  font-size: 0.85rem;
  color: #64748b;
  cursor: pointer;
}
.view-toggle__btn.is-active {
  background: var(--color-primary-muted-bg, #ecfdf3);
  color: var(--color-primary-dark, #166534);
}
.section-hint { margin: 0 0 12px; color: #64748b; font-size: 0.82rem; }
.muted { margin: 8px 0 0; color: #64748b; font-size: 0.85rem; }
.sale-form {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: flex-end;
  margin-bottom: 14px;
}
.sale-form > * { flex: 1 1 180px; }
.loan-list { list-style: none; margin: 0 0 8px; padding: 0; display: grid; gap: 10px; }
.loan-card {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px 14px;
  display: grid;
  gap: 6px;
  font-size: 0.85rem;
}
.loan-card p { margin: 0; color: #334155; }
.loan-card__head {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: flex-start;
}
.loan-card__head > div { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.qty { font-size: 0.8rem; color: #64748b; font-weight: 600; }
.loan-card__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
.chip {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 999px;
}
.chip.is-ok { background: #dcfce7; color: #166534; }
.chip.is-wait { background: #ffedd5; color: #c2410c; }
.chip.is-no { background: #fee2e2; color: #b91c1c; }
</style>
