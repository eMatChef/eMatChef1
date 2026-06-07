<template>
  <EDialog
    v-model="open"
    :max-width="520"
    :title="dialogTitle"
  >
    <p v-if="line" class="purchase-line-subtitle">
      {{ line.material_name || line.material_item_id }} — {{ line.quantity }}×
    </p>

    <ELoadingState
      v-if="isLoadingSuppliers"
      variant="inline"
      :message="t('workshop.purchaseLine.loading')"
    />

    <template v-else>
      <div class="form-group">
        <label>{{ t('workshop.purchaseLine.supplier') }}</label>
        <div class="supplier-search-wrap">
          <input
            v-model="supplierSearch"
            type="text"
            class="form-input"
            :placeholder="t('workshop.purchaseLine.supplierPlaceholder')"
            @focus="showSupplierDropdown = true"
            @blur="hideSupplierDropdownDelayed"
            @input="filterSuppliers"
          />
          <div v-if="showSupplierDropdown && filteredSuppliers.length" class="supplier-dropdown">
            <button
              v-for="addr in filteredSuppliers"
              :key="addr.id"
              type="button"
              class="supplier-dropdown-item"
              @mousedown.prevent="selectSupplier(addr)"
            >
              {{ addr.name || addr.company }}
            </button>
          </div>
        </div>
      </div>

      <ETextField
        v-model="form.purchase_location"
        class="mt-3"
        :label="t('workshop.purchaseLine.location')"
        :placeholder="t('workshop.purchaseLine.locationPlaceholder')"
        hide-details="auto"
      />

      <ETextField
        v-model="form.purchase_total"
        class="mt-3"
        type="number"
        :label="t('workshop.purchaseLine.total')"
        :hint="mode === 'receive' ? t('workshop.purchaseLine.totalHintReceive') : undefined"
        placeholder="0.00"
        hide-details="auto"
      />

      <ETextField
        v-model="form.document_date"
        class="mt-3"
        type="date"
        :label="t('workshop.purchaseLine.documentDate')"
        hide-details="auto"
      />

      <div v-if="mode === 'receive'" class="form-group mt-3">
        <label>{{ t('workshop.purchaseLine.receipt') }}</label>
        <p class="field-hint">{{ t('workshop.purchaseLine.receiptHint') }}</p>
        <input
          ref="receiptInputRef"
          type="file"
          accept="image/jpeg,image/png,image/webp,image/gif,application/pdf"
          class="receipt-file-input"
          @change="onReceiptSelected"
        />
        <div v-if="receiptPreview" class="receipt-preview">
          <span>{{ receiptPreview }}</span>
          <button type="button" class="receipt-clear" @click="clearReceipt">×</button>
        </div>
      </div>
    </template>

    <p v-if="errorMessage" class="purchase-line-error">{{ errorMessage }}</p>

    <template #actions>
      <EButton variant="secondary" size="small" @click="open = false">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        size="small"
        :loading="isSubmitting"
        :disabled="isSubmitting || isLoadingSuppliers"
        @click="submit"
      >
        {{ submitLabel }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAddresses, type Address } from '@/api/addresses'
import {
  orderWorkshopPurchaseLine,
  receiveWorkshopPurchaseLine,
  uploadWorkshopTicketPhoto,
  type WorkshopTicket,
} from '@/api/workshop'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog, ETextField } from '@/components/form/base'
import type { RepairPartLine } from '@/types/repairPartsList'

const props = defineProps<{
  modelValue: boolean
  ticketId: string
  departmentId: string
  line: RepairPartLine | null
  mode: 'order' | 'receive'
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  updated: [ticket: WorkshopTicket]
}>()

const { t } = useI18n()

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const isLoadingSuppliers = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')
const allSuppliers = ref<Address[]>([])
const filteredSuppliers = ref<Address[]>([])
const supplierSearch = ref('')
const showSupplierDropdown = ref(false)
const receiptFile = ref<File | null>(null)
const receiptPreview = ref('')
const receiptInputRef = ref<HTMLInputElement | null>(null)

const form = ref({
  supplier_id: '',
  purchase_location: '',
  purchase_total: '',
  document_date: '',
})

const dialogTitle = computed(() =>
  props.mode === 'order'
    ? t('workshop.purchaseLine.titleOrder')
    : t('workshop.purchaseLine.titleReceive'),
)

const submitLabel = computed(() =>
  props.mode === 'order'
    ? t('workshop.purchaseLine.submitOrder')
    : t('workshop.purchaseLine.submitReceive'),
)

watch(
  () => [props.modelValue, props.line?.id, props.mode],
  () => {
    if (props.modelValue) {
      void initDialog()
    }
  },
)

async function initDialog() {
  errorMessage.value = ''
  receiptFile.value = null
  receiptPreview.value = ''
  form.value = {
    supplier_id: props.line?.supplier_id || '',
    purchase_location: props.line?.purchase_location || '',
    purchase_total: props.line?.purchase_total || '',
    document_date: props.line?.document_date || '',
  }
  supplierSearch.value = ''

  isLoadingSuppliers.value = true
  try {
    const result = await getAddresses(props.departmentId, 'supplier')
    allSuppliers.value = result.addresses || []
    filteredSuppliers.value = allSuppliers.value.slice(0, 10)
    if (form.value.supplier_id) {
      const match = allSuppliers.value.find((a) => a.id === form.value.supplier_id)
      if (match) {
        supplierSearch.value = match.name || match.company || ''
      }
    }
  } catch {
    allSuppliers.value = []
    filteredSuppliers.value = []
  } finally {
    isLoadingSuppliers.value = false
  }
}

function filterSuppliers() {
  const query = supplierSearch.value.toLowerCase().trim()
  if (!query) {
    filteredSuppliers.value = allSuppliers.value.slice(0, 10)
    return
  }
  filteredSuppliers.value = allSuppliers.value
    .filter(
      (a) =>
        a.name?.toLowerCase().includes(query) || a.company?.toLowerCase().includes(query),
    )
    .slice(0, 10)
}

function selectSupplier(addr: Address) {
  form.value.supplier_id = addr.id
  supplierSearch.value = addr.name || addr.company || ''
  showSupplierDropdown.value = false
}

function hideSupplierDropdownDelayed() {
  setTimeout(() => {
    showSupplierDropdown.value = false
  }, 200)
}

function onReceiptSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  receiptFile.value = file
  receiptPreview.value = file.name
}

function clearReceipt() {
  receiptFile.value = null
  receiptPreview.value = ''
  if (receiptInputRef.value) {
    receiptInputRef.value.value = ''
  }
}

function buildPayload(): Record<string, string> {
  const payload: Record<string, string> = {}
  if (form.value.supplier_id) payload.supplier_id = form.value.supplier_id
  if (form.value.purchase_location.trim()) payload.purchase_location = form.value.purchase_location.trim()
  if (form.value.purchase_total.trim()) payload.purchase_total = form.value.purchase_total.trim()
  if (form.value.document_date.trim()) payload.document_date = form.value.document_date.trim()
  return payload
}

async function submit() {
  if (!props.line || !props.ticketId) return

  errorMessage.value = ''
  isSubmitting.value = true
  try {
    const payload = buildPayload()

    if (props.mode === 'receive' && receiptFile.value) {
      const photos = await uploadWorkshopTicketPhoto(props.ticketId, receiptFile.value)
      const url = photos[photos.length - 1]?.url
      if (url) payload.receipt_url = url
    }

    const updated =
      props.mode === 'order'
        ? await orderWorkshopPurchaseLine(props.ticketId, props.line.id, payload)
        : await receiveWorkshopPurchaseLine(props.ticketId, props.line.id, payload)

    emit('updated', updated)
    open.value = false
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    errorMessage.value = message || t('workshop.purchaseLine.submitError')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<style scoped>
.purchase-line-subtitle {
  margin: 0 0 12px;
  font-size: 13px;
  color: #6b7280;
}

.form-group label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 6px;
  color: #374151;
}

.field-hint {
  margin: 0 0 8px;
  font-size: 12px;
  color: #6b7280;
}

.supplier-search-wrap {
  position: relative;
}

.supplier-dropdown {
  position: absolute;
  z-index: 10;
  left: 0;
  right: 0;
  top: 100%;
  margin-top: 4px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
  max-height: 200px;
  overflow-y: auto;
}

.supplier-dropdown-item {
  display: block;
  width: 100%;
  padding: 10px 12px;
  border: none;
  background: transparent;
  text-align: left;
  cursor: pointer;
  font-size: 13px;
}

.supplier-dropdown-item:hover {
  background: #f3f4f6;
}

.receipt-file-input {
  font-size: 13px;
}

.receipt-preview {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 8px;
  font-size: 13px;
  color: #374151;
}

.receipt-clear {
  border: none;
  background: transparent;
  color: #6b7280;
  cursor: pointer;
  font-size: 18px;
  line-height: 1;
}

.purchase-line-error {
  margin-top: 12px;
  font-size: 13px;
  color: #b91c1c;
}
</style>
