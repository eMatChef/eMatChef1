<template>
  <EDialog
    v-model="open"
    :max-width="560"
    :title="dialogTitle"
    scrollable
  >
    <GrossanlassProcurementLineSummary v-if="line" :line="line" />

    <ELoadingState
      v-if="isLoadingAddresses"
      variant="inline"
      :message="t('common.loading')"
      class="mt-3"
    />

    <template v-else>
      <div class="form-group mt-3">
        <label class="form-label">{{ t('grossanlass.beschaffung.offerten.pdf') }}</label>
        <p class="field-hint">{{ t('grossanlass.beschaffung.offerten.pdfHint') }}</p>
        <input
          ref="pdfInputRef"
          type="file"
          accept="application/pdf"
          class="pdf-file-input"
          @change="onPdfSelected"
        />
        <div v-if="pdfPreview" class="pdf-preview">
          <span>{{ pdfPreview }}</span>
          <button type="button" class="pdf-clear" @click="clearPdf">×</button>
        </div>
        <p v-if="extractHint" class="extract-hint">{{ extractHint }}</p>
      </div>

      <div class="form-group mt-3">
        <label class="form-label">{{ t('grossanlass.beschaffung.offerten.supplier') }}</label>
        <p class="field-hint">{{ t('grossanlass.beschaffung.offerten.supplierHint') }}</p>
        <DepartmentAddressAutocomplete
          :addresses="addresses"
          :selected-id="supplierAddressId"
          :extra-items="inquiryItems"
          :extra-items-divider-label="t('grossanlass.beschaffung.offerten.inquiriesGroup')"
          :address-group-label="t('grossanlass.beschaffung.offerten.contactsGroup')"
          :selected-extra-label="selectedInquiryLabel"
          primary-type="supplier"
          :placeholder="t('grossanlass.beschaffung.offerten.supplierPlaceholder')"
          :inline-create-label-key="'grossanlass.beschaffung.offerten.createSupplierInline'"
          @update:selected-id="onSupplierSelected"
          @select-extra="onInquirySelected"
          @create="openCreateSupplier"
        />
        <div class="supplier-actions">
          <EButton variant="secondary" size="small" type="button" @click="openCreateSupplier(addressModalDefaultName)">
            {{ t('grossanlass.beschaffung.offerten.newSupplier') }}
          </EButton>
          <EButton
            v-if="supplierAddressId"
            variant="secondary"
            size="small"
            type="button"
            @click="openEditSupplier"
          >
            {{ t('grossanlass.beschaffung.offerten.editSupplier') }}
          </EButton>
        </div>
      </div>

      <ETextField
        v-model="form.supplier"
        class="mt-3"
        :label="t('grossanlass.beschaffung.offerten.supplierDisplay')"
        hide-details="auto"
      />

      <ETextField
        v-model="form.amount_chf"
        class="mt-3"
        type="number"
        min="0"
        step="0.05"
        :label="t('grossanlass.beschaffung.offerten.amountChf')"
        hide-details="auto"
      />

      <ETextField
        v-model="form.notes"
        class="mt-3"
        :label="t('grossanlass.beschaffung.offerten.notes')"
        hide-details="auto"
      />
    </template>

    <p v-if="errorMessage" class="quote-dialog-error">{{ errorMessage }}</p>

    <template #actions>
      <EButton variant="secondary" size="small" @click="open = false">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        size="small"
        :loading="isSubmitting"
        :disabled="isSubmitting || isLoadingAddresses"
        @click="submit"
      >
        {{ submitLabel }}
      </EButton>
    </template>
  </EDialog>

  <AddressModal
    v-if="addressModalOpen"
    :department-id="departmentId"
    :address="addressModalAddress"
    :edit-address-id="addressModalEditId"
    default-type="supplier"
    :default-name="addressModalDefaultName"
    :allowed-types="['supplier', 'general']"
    @close="closeAddressModal"
    @saved="onAddressSaved"
  />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getAddresses, type Address } from '@/api/addresses'
import {
  getGrossanlassInquiries,
  type GrossanlassInquiry,
} from '@/api/grossanlassInquiries'
import {
  createGrossanlassProcurementQuote,
  extractGrossanlassProcurementQuoteContact,
  updateGrossanlassProcurementQuote,
  uploadGrossanlassProcurementQuotePdf,
  type GrossanlassProcurementLine,
  type GrossanlassProcurementQuote,
} from '@/api/grossanlassProcurement'
import AddressModal from '@/components/AddressModal.vue'
import DepartmentAddressAutocomplete from '@/components/addresses/DepartmentAddressAutocomplete.vue'
import GrossanlassProcurementLineSummary from '@/components/grossanlass/GrossanlassProcurementLineSummary.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog, ETextField } from '@/components/form/base'
import { formatAddressSelectionLabel } from '@/utils/departmentAddressSearch'

const props = defineProps<{
  departmentId: string
  line: GrossanlassProcurementLine
  quote?: GrossanlassProcurementQuote | null
}>()

const emit = defineEmits<{
  saved: []
}>()

const open = defineModel<boolean>({ required: true })

const { t } = useI18n()

const isEdit = computed(() => !!props.quote?.id)
const dialogTitle = computed(() =>
  isEdit.value
    ? t('grossanlass.beschaffung.offerten.editQuoteTitle')
    : t('grossanlass.beschaffung.offerten.addQuoteTitle'),
)
const submitLabel = computed(() =>
  isEdit.value ? t('common.save') : t('grossanlass.beschaffung.offerten.addQuote'),
)

const addresses = ref<Address[]>([])
const inquiries = ref<GrossanlassInquiry[]>([])
const isLoadingAddresses = ref(false)
const isSubmitting = ref(false)
const errorMessage = ref('')

const supplierAddressId = ref<string | null>(null)
const selectedInquiryId = ref<string | null>(null)
const form = ref({ supplier: '', amount_chf: '', notes: '' })

const inquiryItems = computed(() =>
  inquiries.value.map((firm) => ({
    id: firm.id,
    title: firm.name,
    subtitle: [firm.place, firm.email].filter(Boolean).join(' · '),
    badge: t('grossanlass.beschaffung.offerten.inquiryBadge'),
  })),
)

const selectedInquiryLabel = computed(() => {
  if (!selectedInquiryId.value) return ''
  return inquiries.value.find((firm) => firm.id === selectedInquiryId.value)?.name ?? ''
})

const pdfFile = ref<File | null>(null)
const pdfPreview = ref('')
const pdfInputRef = ref<HTMLInputElement | null>(null)
const extractHint = ref('')

const addressModalOpen = ref(false)
const addressModalAddress = ref<Address | null>(null)
const addressModalEditId = ref<string | null>(null)
const addressModalDefaultName = ref('')

function supplierDisplayFromAddress(addr: Address): string {
  return addr.company || addr.name || addr.street_line || ''
}

function matchInquiryFromSupplierName() {
  if (supplierAddressId.value) {
    selectedInquiryId.value = null
    return
  }
  const name = form.value.supplier.trim().toLowerCase()
  if (!name) {
    selectedInquiryId.value = null
    return
  }
  const hit = inquiries.value.find((firm) => firm.name.toLowerCase() === name)
  selectedInquiryId.value = hit?.id ?? null
}

function resetForm() {
  const q = props.quote
  supplierAddressId.value = q?.supplier_address_id ?? null
  selectedInquiryId.value = null
  form.value = {
    supplier: q?.supplier ?? '',
    amount_chf: q?.amount_chf != null ? String(q.amount_chf) : '',
    notes: q?.notes ?? '',
  }
  pdfFile.value = null
  pdfPreview.value = q?.pdf_filename ? q.pdf_filename : ''
  extractHint.value = ''
  errorMessage.value = ''
}

async function loadAddresses() {
  isLoadingAddresses.value = true
  try {
    const [data, firms] = await Promise.all([
      getAddresses(props.departmentId),
      getGrossanlassInquiries(props.departmentId).catch(() => [] as GrossanlassInquiry[]),
    ])
    addresses.value = data.addresses
    inquiries.value = firms
    matchInquiryFromSupplierName()
  } finally {
    isLoadingAddresses.value = false
  }
}

watch(
  [open, () => props.quote?.id],
  ([visible]) => {
    if (visible) {
      resetForm()
      void loadAddresses()
    }
  },
  { immediate: true },
)

function onSupplierSelected(id: string | null) {
  supplierAddressId.value = id
  if (!id) return
  selectedInquiryId.value = null
  const addr = addresses.value.find((a) => a.id === id)
  if (addr) {
    form.value.supplier = supplierDisplayFromAddress(addr)
  }
}

function onInquirySelected(id: string | null) {
  selectedInquiryId.value = id
  if (!id) return
  supplierAddressId.value = null
  const firm = inquiries.value.find((item) => item.id === id)
  if (firm) {
    form.value.supplier = firm.name
  }
}

function openCreateSupplier(query: string) {
  addressModalAddress.value = null
  addressModalEditId.value = null
  addressModalDefaultName.value = query
  addressModalOpen.value = true
}

function openEditSupplier() {
  if (!supplierAddressId.value) return
  addressModalAddress.value = addresses.value.find((a) => a.id === supplierAddressId.value) ?? null
  addressModalEditId.value = supplierAddressId.value
  addressModalDefaultName.value = ''
  addressModalOpen.value = true
}

function closeAddressModal() {
  addressModalOpen.value = false
}

async function onAddressSaved(address?: Address) {
  addressModalOpen.value = false
  await loadAddresses()
  if (address?.id) {
    selectedInquiryId.value = null
    supplierAddressId.value = address.id
    form.value.supplier = supplierDisplayFromAddress(address)
  }
}

async function onPdfSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  pdfFile.value = file
  pdfPreview.value = file.name
  extractHint.value = ''
  errorMessage.value = ''

  try {
    const extracted = await extractGrossanlassProcurementQuoteContact(props.departmentId, file)
    if (extracted.amount_chf != null && !form.value.amount_chf) {
      form.value.amount_chf = String(extracted.amount_chf)
    }
    if (extracted.company && !form.value.supplier) {
      form.value.supplier = extracted.company
    }

    const company = (extracted.company ?? '').toLowerCase()
    const email = (extracted.email ?? '').toLowerCase()

    const match = addresses.value.find((a) => {
      const label = formatAddressSelectionLabel(a).toLowerCase()
      return (company && label.includes(company))
        || (email && (a.email ?? '').toLowerCase() === email)
    })

    const inquiryMatch = inquiries.value.find((firm) => {
      const name = firm.name.toLowerCase()
      const firmEmail = (firm.email ?? '').toLowerCase()
      return (company && name.includes(company))
        || (company && company.includes(name) && name.length > 2)
        || (email && firmEmail === email)
    })

    if (match) {
      selectedInquiryId.value = null
      supplierAddressId.value = match.id
      form.value.supplier = supplierDisplayFromAddress(match)
      extractHint.value = t('grossanlass.beschaffung.offerten.extractMatched')
    } else if (inquiryMatch) {
      supplierAddressId.value = null
      selectedInquiryId.value = inquiryMatch.id
      form.value.supplier = inquiryMatch.name
      extractHint.value = t('grossanlass.beschaffung.offerten.extractMatchedInquiry')
    } else if (extracted.company || extracted.email) {
      extractHint.value = t('grossanlass.beschaffung.offerten.extractNewHint')
      addressModalDefaultName.value = extracted.company ?? extracted.name ?? ''
      addressModalAddress.value = null
      addressModalEditId.value = null
    } else {
      extractHint.value = t('grossanlass.beschaffung.offerten.extractPartial')
    }
  } catch (e: any) {
    extractHint.value = t('grossanlass.beschaffung.offerten.extractFailed')
  }
}

function clearPdf() {
  pdfFile.value = null
  pdfPreview.value = props.quote?.pdf_filename ?? ''
  extractHint.value = ''
  if (pdfInputRef.value) pdfInputRef.value.value = ''
}

async function submit() {
  const supplier = form.value.supplier.trim()
  if (!supplier || !form.value.amount_chf) {
    errorMessage.value = t('grossanlass.beschaffung.offerten.validationRequired')
    return
  }

  isSubmitting.value = true
  errorMessage.value = ''

  try {
    const payload = {
      supplier,
      supplier_address_id: supplierAddressId.value,
      amount_chf: Number(form.value.amount_chf),
      notes: form.value.notes.trim() || null,
    }

    let saved: GrossanlassProcurementQuote
    if (isEdit.value && props.quote) {
      saved = await updateGrossanlassProcurementQuote(
        props.departmentId,
        props.line.id,
        props.quote.id,
        payload,
      )
    } else {
      saved = await createGrossanlassProcurementQuote(
        props.departmentId,
        props.line.id,
        payload,
      )
    }

    if (pdfFile.value) {
      await uploadGrossanlassProcurementQuotePdf(
        props.departmentId,
        props.line.id,
        saved.id,
        pdfFile.value,
      )
    }

    open.value = false
    emit('saved')
  } catch (e: any) {
    errorMessage.value = e.response?.data?.error || t('grossanlass.beschaffung.offerten.errorSave')
  } finally {
    isSubmitting.value = false
  }
}
</script>

<style scoped>
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-label { font-size: 0.82rem; font-weight: 600; color: #334155; }
.field-hint { margin: 0; font-size: 0.75rem; color: #64748b; }
.pdf-file-input { font-size: 0.82rem; }
.pdf-preview {
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  margin-top: 6px; padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 0.82rem;
}
.pdf-clear { border: none; background: none; font-size: 1.1rem; cursor: pointer; color: #64748b; }
.extract-hint { margin: 6px 0 0; font-size: 0.75rem; color: #0369a1; }
.supplier-actions { display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap; }
.quote-dialog-error { margin: 12px 0 0; color: #dc2626; font-size: 0.82rem; }
.mt-3 { margin-top: 12px; }
</style>
