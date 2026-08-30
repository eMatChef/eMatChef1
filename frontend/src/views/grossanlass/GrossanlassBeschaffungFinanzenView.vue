<template>
  <PageShell
    class="grossanlass-kosten"
    :title="t('grossanlass.beschaffung.kosten.pageTitle')"
    :subtitle="t('grossanlass.beschaffung.kosten.intro')"
  >
    <template #actions>
      <EButton variant="secondary" @click="openEditor(null)">
        {{ t('grossanlass.beschaffung.kosten.add') }}
      </EButton>
      <EButton variant="primary" :loading="isSaving" :disabled="isLoading || !overview" @click="save">
        {{ t('grossanlass.beschaffung.finanzen.save') }}
      </EButton>
    </template>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <template v-else-if="overview">
      <div class="rahmen-card">
        <ETextField
          v-model="rahmenInput"
          type="number"
          inputmode="decimal"
          step="0.01"
          min="0"
          :label="t('grossanlass.beschaffung.finanzen.rahmenLabel')"
          hide-details
        />
        <p v-if="overview.logistics_group_name" class="rahmen-meta">
          {{ t('grossanlass.beschaffung.kosten.rahmenOnNode', { name: overview.logistics_group_name }) }}
        </p>
        <p class="rahmen-meta">
          {{ t('grossanlass.beschaffung.finanzen.statSoll') }} {{ formatChf(overview.totals.soll_chf) }}
          · {{ t('grossanlass.beschaffung.finanzen.statOpenQuotes') }} {{ overview.totals.open_quotes_count }}
          · {{ t('grossanlass.beschaffung.finanzen.statOrderedOpen') }} {{ overview.totals.ordered_not_received_count }}
        </p>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.finanzen.statRahmen') }}</span>
          <strong class="stat-value">{{ formatChf(overview.totals.rahmen_chf) }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.kosten.statCash') }}</span>
          <strong class="stat-value">{{ formatChf(overview.totals.cash_chf) }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.kosten.statNetto') }}</span>
          <strong class="stat-value">{{ formatChf(overview.totals.netto_chf) }}</strong>
        </div>
        <div class="stat-card">
          <span class="stat-label">{{ t('grossanlass.beschaffung.kosten.statRestNetto') }}</span>
          <strong class="stat-value">{{ formatChf(overview.totals.rahmen_minus_ist_chf) }}</strong>
        </div>
      </div>

      <div class="kind-grid">
        <button
          v-for="kind in kindCards"
          :key="kind.cost_kind"
          type="button"
          class="kind-card"
          :class="{ 'is-active': kindFilter === kind.cost_kind }"
          @click="toggleKindFilter(kind.cost_kind)"
        >
          <span class="stat-label">{{ t(`grossanlass.beschaffung.kosten.kind.${kind.cost_kind}`) }}</span>
          <strong>{{ formatChf(kind.netto_chf) }}</strong>
          <span class="kind-meta">{{ t('grossanlass.beschaffung.kosten.cashLine', { amount: formatChf(kind.cash_chf) }) }}</span>
        </button>
      </div>

      <div class="toolbar">
        <div class="toggle" role="group">
          <button type="button" :class="{ 'is-active': slice === 'payer' }" @click="slice = 'payer'">
            {{ t('grossanlass.beschaffung.kosten.slicePayer') }}
          </button>
          <button type="button" :class="{ 'is-active': slice === 'requester' }" @click="slice = 'requester'">
            {{ t('grossanlass.beschaffung.kosten.sliceRequester') }}
          </button>
        </div>
        <div class="filter-chips">
          <button
            type="button"
            :class="{ 'is-active': kindFilter === null }"
            @click="kindFilter = null"
          >
            {{ t('grossanlass.beschaffung.kosten.filterAll') }}
          </button>
          <button
            type="button"
            :class="{ 'is-active': excludeCentral }"
            @click="excludeCentral = !excludeCentral"
          >
            {{ t('grossanlass.beschaffung.kosten.filterNotLogistics') }}
          </button>
        </div>
      </div>

      <v-expansion-panels v-model="openSections" multiple class="e-accordions">
        <v-expansion-panel value="lines">
          <v-expansion-panel-title>
            <span class="panel-head">
              <span class="panel-head__label">
                {{ t('grossanlass.beschaffung.kosten.linesTitle') }}
                <span class="panel-head__count">{{ filteredCosts.length }}</span>
              </span>
            </span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <p class="section-hint">{{ t('grossanlass.beschaffung.kosten.rowHint') }}</p>
            <EEmptyState
              v-if="filteredCosts.length === 0"
              variant="default"
              icon="mdi-cash-multiple"
              :title="t('grossanlass.beschaffung.finanzen.noDataTitle')"
              :description="t('grossanlass.beschaffung.kosten.noDataDescription')"
            />
            <div v-else class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>{{ t('grossanlass.beschaffung.kosten.colLabel') }}</th>
                    <th>{{ t('grossanlass.beschaffung.kosten.colKind') }}</th>
                    <th>{{ t('grossanlass.beschaffung.kosten.colRequester') }}</th>
                    <th>{{ t('grossanlass.beschaffung.kosten.colPayer') }}</th>
                    <th>{{ t('grossanlass.beschaffung.finanzen.colSoll') }}</th>
                    <th>{{ t('grossanlass.beschaffung.kosten.statCash') }}</th>
                    <th>{{ t('grossanlass.beschaffung.kosten.statNetto') }}</th>
                    <th>{{ t('grossanlass.beschaffung.kosten.colStatus') }}</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in filteredCosts"
                    :key="row.id"
                    class="is-clickable"
                    @click="openEditor(row)"
                  >
                    <td>{{ row.label }}</td>
                    <td>{{ t(`grossanlass.beschaffung.kosten.kind.${row.cost_kind}`) }}</td>
                    <td>{{ row.requesting_group_name || '—' }}</td>
                    <td>{{ payerLabel(row.payer_group_id, row.payer_group_name) }}</td>
                    <td>{{ formatChf(row.soll_chf) }}</td>
                    <td>{{ formatChf(row.cash_chf) }}</td>
                    <td>{{ formatChf(row.netto_chf) }}</td>
                    <td>{{ t(`grossanlass.beschaffung.kosten.status.${row.status}`) }}</td>
                    <td class="row-actions" @click.stop>
                      <EButton variant="text" size="small" @click="openEditor(row)">{{ t('common.edit') }}</EButton>
                      <EButton
                        v-if="row.cost_kind !== 'ancillary'"
                        variant="text"
                        size="small"
                        @click="openAncillary(row)"
                      >
                        {{ t('grossanlass.beschaffung.kosten.addAncillary') }}
                      </EButton>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="payers">
          <v-expansion-panel-title>
            <span class="panel-head">
              <span class="panel-head__label">
                {{ slice === 'payer' ? t('grossanlass.beschaffung.kosten.byPayer') : t('grossanlass.beschaffung.kosten.byRequester') }}
                <span class="panel-head__count">{{ slice === 'payer' ? payerRows.length : requesterRows.length }}</span>
              </span>
            </span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <div class="table-wrap">
              <table v-if="slice === 'payer'" class="data-table">
                <thead>
                  <tr>
                    <th>{{ t('grossanlass.beschaffung.kosten.colPayer') }}</th>
                    <th>{{ t('grossanlass.beschaffung.finanzen.colRahmen') }}</th>
                    <th>{{ t('grossanlass.beschaffung.finanzen.colLines') }}</th>
                    <th>{{ t('grossanlass.beschaffung.kosten.statCash') }}</th>
                    <th>{{ t('grossanlass.beschaffung.kosten.statNetto') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in payerRows" :key="row.payer_group_id ?? 'central'">
                    <td>{{ payerLabel(row.payer_group_id, row.payer_name) }}</td>
                    <td>
                      <input
                        :value="payerRahmenValue(row)"
                        class="rahmen-input"
                        type="number"
                        min="0"
                        step="0.01"
                        :aria-label="t('grossanlass.beschaffung.finanzen.colRahmen')"
                        @input="setPayerRahmen(row, ($event.target as HTMLInputElement).value)"
                      >
                    </td>
                    <td>{{ row.line_count }}</td>
                    <td>{{ formatChf(row.cash_chf) }}</td>
                    <td>{{ formatChf(row.netto_chf) }}</td>
                  </tr>
                </tbody>
              </table>
              <table v-else class="data-table">
                <thead>
                  <tr>
                    <th>{{ t('grossanlass.beschaffung.finanzen.colRessort') }}</th>
                    <th>{{ t('grossanlass.beschaffung.finanzen.colLines') }}</th>
                    <th>{{ t('grossanlass.beschaffung.finanzen.colSoll') }}</th>
                    <th>{{ t('grossanlass.beschaffung.kosten.statNetto') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in requesterRows" :key="row.group_id">
                    <td>{{ row.group_name }}</td>
                    <td>{{ row.line_count }}</td>
                    <td>{{ formatChf(row.soll_chf) }}</td>
                    <td>{{ formatChf(row.netto_chf ?? row.ist_chf) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="categories">
          <v-expansion-panel-title>
            <span class="panel-head">
              <span class="panel-head__label">
                {{ t('grossanlass.beschaffung.finanzen.byCategory') }}
                <span class="panel-head__count">{{ categoryRows.length }}</span>
              </span>
            </span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <p class="section-hint">{{ t('grossanlass.beschaffung.finanzen.byCategoryHint') }}</p>
            <div v-if="categoryRows.length > 0" class="table-wrap">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>{{ t('grossanlass.beschaffung.finanzen.colCategory') }}</th>
                    <th>{{ t('grossanlass.beschaffung.finanzen.colRahmen') }}</th>
                    <th>{{ t('grossanlass.beschaffung.finanzen.colLines') }}</th>
                    <th>{{ t('grossanlass.beschaffung.finanzen.colSoll') }}</th>
                    <th>{{ t('grossanlass.beschaffung.kosten.statNetto') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in categoryRows" :key="row.category_id ?? 'uncategorized'">
                    <td>{{ categoryRowLabel(row) }}</td>
                    <td>
                      <input
                        v-if="row.category_id"
                        v-model="categoryRahmen[row.category_id]"
                        class="rahmen-input"
                        type="number"
                        min="0"
                        step="0.01"
                        :aria-label="t('grossanlass.beschaffung.finanzen.colRahmen')"
                      >
                      <span v-else>—</span>
                    </td>
                    <td>{{ row.line_count }}</td>
                    <td>{{ formatChf(row.soll_chf) }}</td>
                    <td>{{ formatChf(row.netto_chf ?? row.ist_chf) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </template>

    <EDialog v-model="editorOpen" :title="editorTitle" :max-width="560" scrollable>
      <ETextField v-model="form.label" :label="t('grossanlass.beschaffung.kosten.colLabel')" hide-details />
      <div class="zusage-grid">
        <ESelect
          v-model="form.cost_kind"
          :items="kindItems"
          item-title="title"
          item-value="value"
          :label="t('grossanlass.beschaffung.kosten.colKind')"
          hide-details
        />
        <ESelect
          v-model="form.status"
          :items="statusItems"
          item-title="title"
          item-value="value"
          :label="t('grossanlass.beschaffung.kosten.colStatus')"
          hide-details
        />
      </div>
      <div class="zusage-grid">
        <ESelect
          v-model="form.requesting_group_id"
          :items="groupItems"
          item-title="title"
          item-value="value"
          :label="t('grossanlass.beschaffung.kosten.colRequester')"
          clearable
          hide-details
        />
        <ESelect
          v-model="form.payer_group_id"
          :items="payerItems"
          item-title="title"
          item-value="value"
          :label="t('grossanlass.beschaffung.kosten.colPayer')"
          hide-details
        />
      </div>
      <ESelect
        v-if="form.cost_kind === 'purchase'"
        v-model="form.asset_treatment"
        :items="assetItems"
        item-title="title"
        item-value="value"
        :label="t('grossanlass.beschaffung.kosten.assetTreatment')"
        hide-details
      />
      <div class="zusage-grid">
        <ETextField v-model="form.soll_chf" type="number" step="0.01" min="0" :label="t('grossanlass.beschaffung.finanzen.colSoll')" hide-details />
        <ETextField v-model="form.cash_out_chf" type="number" step="0.01" min="0" :label="t('grossanlass.beschaffung.kosten.statCash')" hide-details />
      </div>
      <div v-if="form.cost_kind === 'rental'" class="zusage-grid">
        <ETextField v-model="form.deposit_chf" type="number" step="0.01" min="0" :label="t('grossanlass.beschaffung.kosten.deposit')" hide-details />
        <ETextField v-model="form.deposit_returned_chf" type="number" step="0.01" min="0" :label="t('grossanlass.beschaffung.kosten.depositReturned')" hide-details />
      </div>
      <div v-if="form.cost_kind === 'buy_resale'" class="zusage-grid">
        <ETextField v-model="form.proceeds_expected_chf" type="number" step="0.01" min="0" :label="t('grossanlass.beschaffung.kosten.proceedsExpected')" hide-details />
        <ETextField v-model="form.proceeds_actual_chf" type="number" step="0.01" min="0" :label="t('grossanlass.beschaffung.kosten.proceedsActual')" hide-details />
      </div>
      <template #actions>
        <EButton variant="secondary" size="small" @click="editorOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :loading="isSavingCost" @click="saveCost">{{ t('common.save') }}</EButton>
      </template>
    </EDialog>
  </PageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import PageShell from '@/components/layout/PageShell.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import {
  isGrossanlassLogisticsPayer,
  grossanlassPayerSelectItems,
} from '@/utils/grossanlassCostPayer'
import {
  createGrossanlassCost,
  formatChf,
  getGrossanlassProcurementOverview,
  saveGrossanlassProcurementRahmen,
  updateGrossanlassCost,
  type GrossanlassCost,
  type GrossanlassCostKind,
  type GrossanlassCostPayload,
  type GrossanlassCostStatus,
  type GrossanlassProcurementOverview,
} from '@/api/grossanlassProcurement'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()

const departmentId = () => String(route.params.departmentId || '')
const isLoading = ref(true)
const isSaving = ref(false)
const isSavingCost = ref(false)
const overview = ref<GrossanlassProcurementOverview | null>(null)
const groups = ref<GrossanlassGroup[]>([])
const rahmenInput = ref<string | number | null>(null)
const categoryRahmen = reactive<Record<string, string>>({})
const payerRahmen = reactive<Record<string, string>>({})
const slice = ref<'payer' | 'requester'>('payer')
const kindFilter = ref<GrossanlassCostKind | null>(null)
const excludeCentral = ref(false)
const openSections = ref<string[]>(['lines', 'payers'])
const editorOpen = ref(false)
const editingId = ref<string | null>(null)
const ancillaryMode = ref(false)
const form = reactive<GrossanlassCostPayload>({
  label: '',
  cost_kind: 'loan',
  status: 'planned',
  payer_group_id: null,
  requesting_group_id: null,
  procurement_line_id: null,
  commitment_id: null,
  asset_treatment: 'expense',
})

const kindCards = computed(() => overview.value?.by_kind ?? [])
const payerRows = computed(() => overview.value?.by_payer ?? [])
const requesterRows = computed(() => overview.value?.by_requester ?? overview.value?.by_group ?? [])
const categoryRows = computed(() => overview.value?.by_category ?? [])
const filteredCosts = computed(() => {
  let rows = overview.value?.costs ?? []
  if (kindFilter.value) {
    rows = rows.filter((row) => row.cost_kind === kindFilter.value)
  }
  if (excludeCentral.value) {
    const logisticsId = overview.value?.logistics_group_id
    rows = rows.filter((row) => !isGrossanlassLogisticsPayer(row.payer_group_id, logisticsId))
  }
  return rows
})

const kindItems = computed(() =>
  (['purchase', 'rental', 'loan', 'buy_resale', 'ancillary'] as GrossanlassCostKind[]).map((value) => ({
    title: t(`grossanlass.beschaffung.kosten.kind.${value}`),
    value,
  })),
)
const statusItems = computed(() =>
  (['planned', 'committed', 'paid', 'for_sale', 'sold', 'returned', 'cancelled'] as GrossanlassCostStatus[]).map((value) => ({
    title: t(`grossanlass.beschaffung.kosten.status.${value}`),
    value,
  })),
)
const assetItems = computed(() => [
  { title: t('grossanlass.beschaffung.kosten.assetExpense'), value: 'expense' },
  { title: t('grossanlass.beschaffung.kosten.assetInventory'), value: 'inventory' },
])
const groupItems = computed(() => groups.value.map((g) => ({ title: g.name, value: g.id })))
const payerItems = computed(() =>
  grossanlassPayerSelectItems(groups.value, overview.value?.logistics_group_id, {
    central: t('grossanlass.beschaffung.kosten.payerCentral'),
    potSuffix: t('grossanlass.beschaffung.kosten.payerPotSuffix'),
  }),
)
const editorTitle = computed(() => {
  if (editingId.value) return t('grossanlass.beschaffung.kosten.editTitle')
  if (ancillaryMode.value) return t('grossanlass.beschaffung.kosten.addAncillary')
  return t('grossanlass.beschaffung.kosten.add')
})

function toggleKindFilter(kind: GrossanlassCostKind) {
  kindFilter.value = kindFilter.value === kind ? null : kind
}

function payerLabel(id: string | null | undefined, name: string | null | undefined): string {
  if (isGrossanlassLogisticsPayer(id, overview.value?.logistics_group_id)) {
    return name || t('grossanlass.beschaffung.kosten.payerCentral')
  }
  if (!id) return t('grossanlass.beschaffung.kosten.payerCentral')
  return name || id
}

function isAnlassPotRow(row: { payer_group_id: string | null }): boolean {
  return isGrossanlassLogisticsPayer(row.payer_group_id, overview.value?.logistics_group_id)
}

function optionalChf(value: unknown): number | null {
  if (value === '' || value === null || value === undefined) return null
  const n = Number(value)
  return Number.isFinite(n) ? n : null
}

function payerRahmenValue(row: { payer_group_id: string | null }): string {
  if (isAnlassPotRow(row)) return String(rahmenInput.value ?? '')
  return payerRahmen[row.payer_group_id ?? 'central'] ?? ''
}

function setPayerRahmen(row: { payer_group_id: string | null }, value: string) {
  if (isAnlassPotRow(row)) {
    rahmenInput.value = value
    return
  }
  payerRahmen[row.payer_group_id ?? 'central'] = value
}

function categoryRowLabel(row: GrossanlassProcurementOverview['by_category'][number]): string {
  if (!row.category_name) {
    return t('grossanlass.beschaffung.bedarf.categoryUncategorized')
  }
  if (row.parent_name) {
    return `${row.parent_name} / ${row.category_name}`
  }
  return row.category_name
}

function amountToInput(value: number | null | undefined): string {
  if (value == null) return ''
  return String(value)
}

function parseAmount(value: string | number | null | undefined): number | null {
  if (value === null || value === undefined || value === '') return null
  const normalized = String(value).replace(/['’\s]/g, '').replace(',', '.')
  if (normalized === '') return null
  const n = Number(normalized)
  if (Number.isNaN(n) || n < 0) {
    throw new Error('invalid')
  }
  return n
}

function applyOverview(data: GrossanlassProcurementOverview) {
  overview.value = data
  rahmenInput.value = amountToInput(data.totals.rahmen_chf)
  Object.keys(categoryRahmen).forEach((key) => {
    delete categoryRahmen[key]
  })
  for (const row of data.by_category) {
    if (row.category_id) {
      categoryRahmen[row.category_id] = amountToInput(row.rahmen_chf)
    }
  }
  Object.keys(payerRahmen).forEach((key) => {
    delete payerRahmen[key]
  })
  for (const row of data.by_payer ?? []) {
    payerRahmen[row.payer_group_id ?? 'central'] = amountToInput(row.rahmen_chf)
  }
}

async function load() {
  if (!departmentId()) return
  isLoading.value = true
  try {
    const [data, groupList] = await Promise.all([
      getGrossanlassProcurementOverview(departmentId()),
      getGrossanlassGroups(departmentId()),
    ])
    groups.value = groupList
    applyOverview(data)
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.finanzen.errorLoad'))
  } finally {
    isLoading.value = false
  }
}

async function save() {
  if (!departmentId()) return
  isSaving.value = true
  try {
    const categories = Object.entries(categoryRahmen).map(([category_id, value]) => ({
      category_id,
      rahmen_chf: parseAmount(value),
    }))
    const logisticsId = overview.value?.logistics_group_id
    const payer_budgets = Object.entries(payerRahmen)
      .filter(([key]) => key !== 'central' && key !== logisticsId)
      .map(([payer_group_id, value]) => ({
        payer_group_id,
        rahmen_chf: parseAmount(value),
      }))
    applyOverview(
      await saveGrossanlassProcurementRahmen(departmentId(), {
        rahmen_chf: parseAmount(rahmenInput.value),
        categories,
        payer_budgets,
      }),
    )
    toast.success(t('grossanlass.beschaffung.finanzen.saved'))
  } catch (e: any) {
    if (e instanceof Error && e.message === 'invalid') {
      toast.error(t('grossanlass.beschaffung.finanzen.errorAmount'))
    } else {
      toast.error(e.response?.data?.error || t('grossanlass.beschaffung.finanzen.errorSave'))
    }
  } finally {
    isSaving.value = false
  }
}

function openEditor(row: GrossanlassCost | null) {
  editingId.value = row?.id ?? null
  ancillaryMode.value = false
  form.label = row?.label ?? ''
  form.cost_kind = row?.cost_kind ?? 'loan'
  form.status = row?.status ?? 'planned'
  form.requesting_group_id = row?.requesting_group_id ?? null
  form.payer_group_id = row?.payer_group_id ?? overview.value?.logistics_group_id ?? null
  form.procurement_line_id = row?.procurement_line_id ?? null
  form.commitment_id = row?.commitment_id ?? null
  form.asset_treatment = row?.asset_treatment ?? 'expense'
  form.soll_chf = row?.soll_chf ?? null
  form.cash_out_chf = row?.cash_out_chf ?? null
  form.deposit_chf = row?.deposit_chf ?? null
  form.deposit_returned_chf = row?.deposit_returned_chf ?? null
  form.proceeds_expected_chf = row?.proceeds_expected_chf ?? null
  form.proceeds_actual_chf = row?.proceeds_actual_chf ?? null
  editorOpen.value = true
}

function openAncillary(parent: GrossanlassCost) {
  editingId.value = null
  ancillaryMode.value = true
  form.label = t('grossanlass.beschaffung.kosten.ancillaryFor', { label: parent.label })
  form.cost_kind = 'ancillary'
  form.status = 'planned'
  form.requesting_group_id = parent.requesting_group_id
  form.payer_group_id = parent.payer_group_id
  form.procurement_line_id = parent.procurement_line_id
  form.commitment_id = parent.commitment_id
  form.asset_treatment = null
  form.soll_chf = null
  form.cash_out_chf = null
  form.deposit_chf = null
  form.deposit_returned_chf = null
  form.proceeds_expected_chf = null
  form.proceeds_actual_chf = null
  editorOpen.value = true
}

async function saveCost() {
  if (!departmentId() || !form.label?.trim()) return
  isSavingCost.value = true
  try {
    const payload: GrossanlassCostPayload = {
      label: form.label.trim(),
      cost_kind: form.cost_kind,
      status: form.status,
      requesting_group_id: form.requesting_group_id || null,
      payer_group_id: form.payer_group_id ?? null,
      asset_treatment: form.cost_kind === 'purchase' ? (form.asset_treatment ?? 'expense') : null,
      soll_chf: optionalChf(form.soll_chf),
      cash_out_chf: optionalChf(form.cash_out_chf),
      deposit_chf: optionalChf(form.deposit_chf),
      deposit_returned_chf: optionalChf(form.deposit_returned_chf),
      proceeds_expected_chf: optionalChf(form.proceeds_expected_chf),
      proceeds_actual_chf: optionalChf(form.proceeds_actual_chf),
    }
    if (!editingId.value) {
      payload.procurement_line_id = form.procurement_line_id || null
      payload.commitment_id = form.commitment_id || null
    }
    if (editingId.value) {
      await updateGrossanlassCost(departmentId(), editingId.value, payload)
    } else {
      await createGrossanlassCost(departmentId(), payload)
    }
    editorOpen.value = false
    await load()
    toast.success(t('grossanlass.beschaffung.kosten.saved'))
  } catch (e: any) {
    toast.error(e.response?.data?.error || t('grossanlass.beschaffung.finanzen.errorSave'))
  } finally {
    isSavingCost.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.rahmen-card {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px 24px;
  margin-bottom: 16px;
}
.rahmen-card :deep(.e-form-field) {
  flex: 1 1 220px;
  max-width: 280px;
}
.rahmen-meta {
  margin: 0;
  font-size: 0.8rem;
  color: #64748b;
}
.stats-grid, .kind-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.stat-card, .kind-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  background: #fff;
  text-align: left;
}
.kind-card {
  font: inherit;
  cursor: pointer;
}
.kind-card.is-active {
  border-color: #0f172a;
  box-shadow: inset 0 0 0 1px #0f172a;
}
.stat-label { display: block; font-size: 0.75rem; color: #64748b; margin-bottom: 4px; }
.stat-value { font-size: 1.1rem; color: #0f172a; }
.kind-card strong { display: block; font-size: 1.05rem; }
.kind-meta { display: block; margin-top: 4px; font-size: 0.75rem; color: #64748b; }
.toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin: 0 0 16px; }
.toggle, .filter-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.toggle button, .filter-chips button {
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 999px;
  padding: 6px 12px;
  font: inherit;
  font-size: 0.82rem;
  cursor: pointer;
}
.toggle button.is-active, .filter-chips button.is-active {
  background: #0f172a;
  color: #fff;
  border-color: #0f172a;
}
.section-hint { margin: 0 0 10px; color: #64748b; font-size: 0.82rem; }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; text-align: left; }
.data-table th { background: #f8fafc; font-weight: 600; }
.data-table tr.is-clickable { cursor: pointer; }
.data-table tr.is-clickable:hover { background: #f8fafc; }
.row-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  justify-content: flex-end;
  white-space: nowrap;
}
.rahmen-input {
  width: 120px;
  padding: 6px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font: inherit;
}
.zusage-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin: 10px 0;
}
@media (max-width: 640px) {
  .zusage-grid { grid-template-columns: 1fr; }
}
</style>
