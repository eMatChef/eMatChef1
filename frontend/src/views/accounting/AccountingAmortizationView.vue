<template>
  <div class="accounting-subpage accounting-amortization">
    <p class="description intro">
      {{ t('accounting.amortization.intro') }}
    </p>

    <div class="am-toolbar">
      <ESelect
        v-model="year"
        :items="yearSelectItems"
        :label="t('accounting.common.year')"
        hide-details="auto"
        class="am-year-select"
        @update:model-value="reload"
      />
      <ETextField
        v-model.number="usefulLifeYears"
        type="number"
        min="1"
        max="30"
        :label="t('accounting.amortization.usefulLifeYears')"
        hide-details="auto"
        class="am-life-field"
        @update:model-value="reload"
      />
    </div>

    <p v-if="loadError" class="am-error">{{ loadError }}</p>
    <ELoadingState v-else-if="loading" variant="inline" :message="t('accounting.common.loading')" />
    <template v-else-if="data">
      <EEmptyState
        v-if="!data.suggestions.length"
        :title="t('accounting.amortization.empty')"
      />
      <div v-else class="cost-centers-table-wrap">
        <table class="cost-centers-table am-table">
          <thead>
            <tr>
              <th>{{ t('common.material') }}</th>
              <th class="col-num">{{ t('accounting.amortization.colAcquisition') }}</th>
              <th class="col-num">{{ t('accounting.amortization.colSuggested') }}</th>
              <th class="col-num">{{ t('accounting.amortization.colBooked') }}</th>
              <th class="col-num">{{ t('accounting.amortization.colRemaining') }}</th>
              <th class="col-actions">{{ t('accounting.common.action') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in data.suggestions" :key="row.material_item_id">
              <td>{{ row.material_name }}</td>
              <td class="col-num">CHF {{ formatMoney(row.acquisition_value_chf) }}</td>
              <td class="col-num">CHF {{ formatMoney(row.suggested_annual_chf) }}</td>
              <td class="col-num">CHF {{ formatMoney(row.booked_amortization_chf) }}</td>
              <td class="col-num">
                <strong>CHF {{ formatMoney(row.remaining_suggestion_chf) }}</strong>
              </td>
              <td class="col-actions">
                <EButton
                  variant="secondary"
                  size="small"
                  :disabled="parseFloat(row.remaining_suggestion_chf) <= 0 || !defaultCostCenterId"
                  @click="openCreateBooking(row)"
                >
                  {{ t('accounting.amortization.createBooking') }}
                </EButton>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <EDialog
      v-model="modalOpen"
      :max-width="520"
      :title="t('accounting.amortization.modalTitle')"
    >
      <p v-if="selectedRow" class="am-modal-lead">
        {{ selectedRow.material_name }} — CHF {{ formatMoney(selectedRow.remaining_suggestion_chf) }}
      </p>
      <ESelect
        v-model="bookingForm.cost_center_id"
        :items="costCenterSelectItems"
        :label="t('accounting.common.costCenter')"
        hide-details="auto"
      />
      <ETextField
        v-model="bookingForm.booked_at"
        type="date"
        class="mt-3"
        :label="t('common.date')"
        hide-details="auto"
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="modalOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :loading="saving" @click="saveBooking">
          {{ t('accounting.amortization.saveBooking') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  getAmortizationSuggestions,
  type AmortizationSuggestion,
  type AmortizationSuggestionsResponse,
} from '@/api/accountingAmortization'
import { listCostCenters, type AccountingCostCenter } from '@/api/accountingCostCenters'
import { createBooking } from '@/api/accountingBookings'
import { useToast } from '@/composables/useToast'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()
const departmentId = computed(() => String(route.params.departmentId || ''))

const year = ref(new Date().getFullYear())
const usefulLifeYears = ref(5)
const yearSelectItems = computed(() => {
  const y = new Date().getFullYear()
  return [y, y - 1, y - 2, y - 3, y - 4].map((v) => ({ title: String(v), value: v }))
})

const loading = ref(true)
const loadError = ref('')
const data = ref<AmortizationSuggestionsResponse | null>(null)
const costCenters = ref<AccountingCostCenter[]>([])
const defaultCostCenterId = computed(() => costCenters.value[0]?.id || '')

const costCenterSelectItems = computed(() =>
  costCenters.value.map((c) => ({ title: c.name, value: c.id }))
)

const modalOpen = ref(false)
const selectedRow = ref<AmortizationSuggestion | null>(null)
const saving = ref(false)
const bookingForm = reactive({
  cost_center_id: '',
  booked_at: `${year.value}-12-31`,
})

function formatMoney(s: string): string {
  const n = parseFloat(s)
  if (Number.isNaN(n)) return s
  return n.toFixed(2)
}

async function loadCostCenters() {
  try {
    costCenters.value = await listCostCenters(departmentId.value)
  } catch {
    costCenters.value = []
  }
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    data.value = await getAmortizationSuggestions(
      departmentId.value,
      year.value,
      usefulLifeYears.value,
    )
  } catch (e: unknown) {
    const msg =
      e && typeof e === 'object' && 'response' in e
        ? (e as { response?: { data?: { error?: string } } }).response?.data?.error
        : null
    loadError.value = msg || t('accounting.amortization.loadError')
    data.value = null
  } finally {
    loading.value = false
  }
}

function reload() {
  void load()
}

function openCreateBooking(row: AmortizationSuggestion) {
  selectedRow.value = row
  bookingForm.cost_center_id = defaultCostCenterId.value
  bookingForm.booked_at = `${year.value}-12-31`
  modalOpen.value = true
}

async function saveBooking() {
  const row = selectedRow.value
  if (!row || !bookingForm.cost_center_id) return
  saving.value = true
  try {
    await createBooking(departmentId.value, {
      amount: row.remaining_suggestion_chf,
      booked_at: bookingForm.booked_at,
      cost_center_id: bookingForm.cost_center_id,
      entry_type: 'amortization',
      payment_status: 'paid',
      material_item_id: row.material_item_id,
      receipt_label: t('accounting.amortization.receiptLabel', { name: row.material_name, year: year.value }),
    })
    toast.success(t('accounting.amortization.toastCreated'))
    modalOpen.value = false
    await load()
  } catch {
    toast.error(t('accounting.common.saveFailed'))
  } finally {
    saving.value = false
  }
}

watch(
  departmentId,
  async () => {
    await loadCostCenters()
    await load()
  },
  { immediate: true },
)
</script>

<style scoped>
@import '@/styles/accounting-view.css';

.intro {
  margin-bottom: 18px;
}

.am-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px;
  margin-bottom: 20px;
}

.am-year-select {
  max-width: 160px;
}

.am-life-field {
  max-width: 140px;
}

.am-table .col-num {
  text-align: right;
}

.am-error {
  color: #b91c1c;
  font-size: 14px;
}

.am-modal-lead {
  margin-bottom: 12px;
  font-size: 14px;
}

.accounting-amortization {
  max-width: 980px;
}
</style>
