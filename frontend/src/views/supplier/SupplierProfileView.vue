<template>
  <div class="supplier-page">
    <header class="supplier-page-header">
      <h1>{{ t('supplierProfile.title') }}</h1>
      <p v-if="!canEdit" class="supplier-page-readonly-hint">{{ t('supplierProfile.readOnlyHint') }}</p>
    </header>

    <div v-if="loading" class="supplier-page-state">{{ t('common.loading') }}</div>
    <div v-else-if="loadError" class="supplier-page-state supplier-page-state--error">{{ loadError }}</div>

    <form v-else class="supplier-profile-form" @submit.prevent="save">
      <section class="form-section">
        <h2 class="form-section-title">{{ t('supplierProfile.sectionCompany') }}</h2>
        <div class="form-row two-cols">
          <label class="form-field">
            <span>{{ t('supplierProfile.fields.name') }}</span>
            <input
              v-model="form.name"
              type="text"
              class="form-input"
              required
              maxlength="255"
              :disabled="!canEdit"
            />
          </label>
          <label class="form-field">
            <span>{{ t('supplierProfile.fields.manufacturerKey') }}</span>
            <input
              v-model="form.manufacturer_key"
              type="text"
              class="form-input"
              maxlength="120"
              :disabled="!canEdit"
              :placeholder="t('supplierProfile.fields.manufacturerKeyPlaceholder')"
            />
            <span class="form-hint">{{ t('supplierProfile.fields.manufacturerKeyHint') }}</span>
          </label>
        </div>
      </section>

      <section class="form-section">
        <h2 class="form-section-title">{{ t('supplierProfile.sectionOperator') }}</h2>
        <p class="form-hint section-intro">{{ t('supplierProfile.operatorIntro') }}</p>

        <label v-if="canEdit" class="operator-toggle">
          <input v-model="operatorForm.operator_enabled" type="checkbox" :disabled="!canEdit" />
          <span>{{ t('supplierProfile.operatorToggle') }}</span>
        </label>
        <p v-else class="operator-readonly">
          {{
            profile?.operator_enabled
              ? t('supplierProfile.operatorActive')
              : t('supplierProfile.operatorInactive')
          }}
        </p>

        <div v-if="operatorForm.operator_enabled" class="operator-details">
          <label class="form-field">
            <span>{{ t('supplierProfile.operatorDepartment') }}</span>
            <select
              v-model="operatorForm.linked_department_id"
              class="form-input"
              :disabled="!canEdit"
              required
            >
              <option value="" disabled>{{ t('supplierProfile.operatorDepartmentPlaceholder') }}</option>
              <option
                v-for="dept in eligibleDepartments"
                :key="dept.department_id"
                :value="dept.department_id"
              >
                {{ dept.name }} ({{ dept.organisation_name }})
              </option>
            </select>
          </label>

          <p v-if="linkedDepartmentLabel && !canEdit" class="form-hint">{{ linkedDepartmentLabel }}</p>

          <p v-if="canEdit && operatorForm.operator_enabled && eligibleDepartments.length === 0" class="form-warning">
            {{ t('supplierProfile.operatorNoDepartments') }}
          </p>

          <p
            v-if="operatorForm.operator_enabled && operatorForm.linked_department_id && !hasLinkedDepartmentMembership"
            class="form-warning"
          >
            {{ t('supplierProfile.operatorMembershipHint') }}
          </p>
        </div>
      </section>

      <section class="form-section">
        <h2 class="form-section-title">{{ t('supplierProfile.sectionContact') }}</h2>
        <div :class="{ 'address-readonly': !canEdit }">
          <AddressForm
            v-model="addressForm"
            :show-type="false"
            :show-extended="true"
            :show-map="false"
          />
        </div>
      </section>

      <div v-if="canEdit" class="form-actions">
        <button type="submit" class="btn btn-primary" :disabled="saving || !hasChanges">
          {{ saving ? t('common.saving') : t('common.save') }}
        </button>
        <button type="button" class="btn btn-secondary" :disabled="saving || !hasChanges" @click="resetForm">
          {{ t('common.cancel') }}
        </button>
        <p v-if="saveError" class="form-error">{{ saveError }}</p>
        <p v-if="saveSuccess" class="form-success">{{ saveSuccess }}</p>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import AddressForm from '@/components/AddressForm.vue'
import type { AddressFormData } from '@/api/addresses'
import {
  getSupplierCompany,
  patchSupplierCompany,
  type SupplierCompanyProfile,
} from '@/api/supplierCompanies'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()

const companyId = computed(() => route.params.companyId as string)
const loading = ref(true)
const saving = ref(false)
const loadError = ref('')
const saveError = ref('')
const saveSuccess = ref('')
const profile = ref<SupplierCompanyProfile | null>(null)

const form = ref({
  name: '',
  manufacturer_key: '',
})

const operatorForm = ref({
  operator_enabled: false,
  linked_department_id: '',
})

const addressForm = ref<AddressFormData>(emptyAddressForm())

const canEdit = computed(() => profile.value?.can_edit === true)
const eligibleDepartments = computed(() => profile.value?.eligible_operator_departments ?? [])
const hasLinkedDepartmentMembership = computed(() => {
  if (!operatorForm.value.operator_enabled || !operatorForm.value.linked_department_id) {
    return true
  }
  return authStore.departments.some(
    (dept) => dept.department_id === operatorForm.value.linked_department_id
  )
})

const linkedDepartmentLabel = computed(() => {
  const linked = profile.value?.linked_department
  if (!linked?.name) return ''
  const org = linked.organisation_name ? ` (${linked.organisation_name})` : ''
  return `${linked.name}${org}`
})

const hasChanges = computed(() => {
  if (!profile.value) return false
  const snapshot = buildPatchPayload()
  const original = buildPayloadFromProfile(profile.value)
  return JSON.stringify(snapshot) !== JSON.stringify(original)
})

function emptyAddressForm(): AddressFormData {
  return {
    department_id: '',
    type: 'supplier',
    name: '',
    company: '',
    street: '',
    street_number: '',
    postal_code: '',
    city: '',
    canton: '',
    country: 'Schweiz',
    contact_first_name: '',
    contact_last_name: '',
    email: '',
    phone: '',
    mobile: '',
    additional_info: '',
    is_primary: false,
  }
}

function applyProfileToForm(data: SupplierCompanyProfile) {
  form.value = {
    name: data.name,
    manufacturer_key: data.manufacturer_key || '',
  }
  operatorForm.value = {
    operator_enabled: data.operator_enabled,
    linked_department_id: data.linked_department_id || '',
  }
  const addr = data.address
  addressForm.value = {
    department_id: '',
    type: 'supplier',
    name: addr?.name || '',
    company: addr?.company || data.name,
    street: addr?.street || '',
    street_number: addr?.street_number || '',
    postal_code: addr?.postal_code || '',
    city: addr?.city || '',
    canton: addr?.canton || '',
    country: addr?.country || 'Schweiz',
    contact_first_name: addr?.contact_first_name || '',
    contact_last_name: addr?.contact_last_name || '',
    email: addr?.email || '',
    phone: addr?.phone || '',
    mobile: addr?.mobile || '',
    additional_info: addr?.additional_info || '',
    is_primary: false,
  }
}

function buildPayloadFromProfile(data: SupplierCompanyProfile) {
  return buildPatchPayloadFromState(
    { name: data.name, manufacturer_key: data.manufacturer_key || '' },
    {
      operator_enabled: data.operator_enabled,
      linked_department_id: data.linked_department_id || '',
    },
    addressFromProfile(data)
  )
}

function addressFromProfile(data: SupplierCompanyProfile): AddressFormData {
  const addr = data.address
  return {
    department_id: '',
    type: 'supplier',
    name: addr?.name || '',
    company: addr?.company || data.name,
    street: addr?.street || '',
    street_number: addr?.street_number || '',
    postal_code: addr?.postal_code || '',
    city: addr?.city || '',
    canton: addr?.canton || '',
    country: addr?.country || 'Schweiz',
    contact_first_name: addr?.contact_first_name || '',
    contact_last_name: addr?.contact_last_name || '',
    email: addr?.email || '',
    phone: addr?.phone || '',
    mobile: addr?.mobile || '',
    additional_info: addr?.additional_info || '',
    is_primary: false,
  }
}

function nullableField(value: string | null | undefined): string | null {
  const trimmed = (value ?? '').trim()
  return trimmed === '' ? null : trimmed
}

function buildPatchPayloadFromState(
  companyForm: { name: string; manufacturer_key: string },
  operator: { operator_enabled: boolean; linked_department_id: string },
  address: AddressFormData
) {
  return {
    name: companyForm.name.trim(),
    manufacturer_key: nullableField(companyForm.manufacturer_key),
    operator_enabled: operator.operator_enabled,
    linked_department_id: operator.operator_enabled
      ? operator.linked_department_id || null
      : null,
    address: {
      company: nullableField(address.company),
      name: nullableField(address.name),
      street: nullableField(address.street),
      street_number: nullableField(address.street_number),
      postal_code: nullableField(address.postal_code),
      city: nullableField(address.city),
      canton: nullableField(address.canton),
      country: (address.country || 'Schweiz').trim() || 'Schweiz',
      contact_first_name: nullableField(address.contact_first_name),
      contact_last_name: nullableField(address.contact_last_name),
      email: nullableField(address.email),
      phone: nullableField(address.phone),
      mobile: nullableField(address.mobile),
      additional_info: nullableField(address.additional_info),
    },
  }
}

function buildPatchPayload() {
  return buildPatchPayloadFromState(form.value, operatorForm.value, addressForm.value)
}

async function loadProfile() {
  loading.value = true
  loadError.value = ''
  try {
    const { supplier_company } = await getSupplierCompany(companyId.value)
    profile.value = supplier_company
    applyProfileToForm(supplier_company)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    loadError.value = e?.response?.data?.error || t('supplierProfile.loadFailed')
  } finally {
    loading.value = false
  }
}

function resetForm() {
  if (profile.value) {
    applyProfileToForm(profile.value)
  }
  saveError.value = ''
  saveSuccess.value = ''
}

async function save() {
  if (!canEdit.value || !profile.value) return
  if (operatorForm.value.operator_enabled && !operatorForm.value.linked_department_id) {
    saveError.value = t('supplierProfile.operatorDepartmentRequired')
    return
  }
  saving.value = true
  saveError.value = ''
  saveSuccess.value = ''
  try {
    const payload = buildPatchPayload()
    const { supplier_company, message } = await patchSupplierCompany(companyId.value, payload)
    profile.value = supplier_company
    applyProfileToForm(supplier_company)
    saveSuccess.value = message || t('supplierProfile.saveSuccess')
    const idx = authStore.supplierCompanies.findIndex((c) => c.id === supplier_company.id)
    if (idx >= 0) {
      authStore.supplierCompanies[idx] = {
        ...authStore.supplierCompanies[idx],
        name: supplier_company.name,
      }
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    saveError.value = e?.response?.data?.error || t('supplierProfile.saveFailed')
  } finally {
    saving.value = false
  }
}

watch(companyId, () => {
  void loadProfile()
})

onMounted(() => {
  void loadProfile()
})
</script>

<style scoped>
.supplier-page {
  max-width: 960px;
  padding: 24px;
}

.supplier-page-header h1 {
  margin: 0 0 8px;
  font-size: 1.75rem;
  font-weight: 600;
  color: #111827;
}

.supplier-page-readonly-hint {
  margin: 0;
  color: #6b7280;
  font-size: 0.95rem;
}

.supplier-page-state {
  margin-top: 24px;
  color: #4b5563;
}

.supplier-page-state--error {
  color: #b91c1c;
}

.supplier-profile-form {
  margin-top: 24px;
}

.form-section {
  margin-bottom: 32px;
}

.form-section-title {
  margin: 0 0 16px;
  font-size: 1.125rem;
  font-weight: 600;
  color: #374151;
}

.form-row {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}

.form-row.two-cols .form-field {
  flex: 1 1 240px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-field span {
  font-size: 0.875rem;
  font-weight: 500;
  color: #374151;
}

.form-input {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.95rem;
}

.form-input:disabled {
  background: #f3f4f6;
  color: #6b7280;
}

.form-hint {
  font-size: 0.8rem;
  color: #6b7280;
}

.section-intro {
  margin: 0 0 16px;
}

.operator-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
  font-size: 0.95rem;
  color: #374151;
}

.operator-readonly {
  margin: 0 0 12px;
  color: #4b5563;
}

.operator-details {
  margin-top: 8px;
}

.form-warning {
  margin: 12px 0 0;
  padding: 10px 12px;
  border-radius: 6px;
  background: #fffbeb;
  border: 1px solid #fcd34d;
  color: #92400e;
  font-size: 0.875rem;
}

.form-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding-top: 8px;
  border-top: 1px solid #e5e7eb;
}

.form-error {
  flex-basis: 100%;
  margin: 0;
  color: #b91c1c;
  font-size: 0.875rem;
}

.form-success {
  flex-basis: 100%;
  margin: 0;
  color: #047857;
  font-size: 0.875rem;
}

.address-readonly :deep(input),
.address-readonly :deep(select),
.address-readonly :deep(textarea),
.address-readonly :deep(button) {
  pointer-events: none;
  background: #f3f4f6;
  color: #6b7280;
}
</style>
