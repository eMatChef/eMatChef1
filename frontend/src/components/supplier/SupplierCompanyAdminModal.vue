<template>
  <EDialog
    v-model="dialogOpen"
    :max-width="720"
    scrollable
    persistent
  >
    <template #title>
      <div>
        <div>{{ t('globalAddressesPage.supplierAdminModal.title', { name: company.name }) }}</div>
        <p class="modal-subtitle">{{ t('globalAddressesPage.supplierAdminModal.subtitle') }}</p>
      </div>
    </template>

    <section class="section">
      <h4>{{ t('globalAddressesPage.supplierAdminModal.companySection') }}</h4>
      <form id="supplier-admin-company-form" class="company-form" @submit.prevent="saveCompany">
        <ETextField
          v-model="form.name"
          :label="t('globalAddressesPage.supplierModal.name')"
          hide-details="auto"
          class="field-grow"
        />
        <ETextField
          v-model="form.manufacturer_key"
          :label="t('globalAddressesPage.supplierModal.manufacturerKey')"
          hide-details="auto"
          class="field-grow"
        />
        <ESelect
          v-model="form.status"
          :items="statusItems"
          :label="t('globalAddressesPage.tableStatus')"
          hide-details="auto"
          class="field-grow"
        />
        <fieldset class="capabilities-fieldset">
          <legend>{{ t('globalAddressesPage.supplierAdminModal.capabilities') }}</legend>
          <ECheckbox
            v-for="option in capabilityOptions"
            :key="option.value"
            :model-value="form.capabilities.includes(option.value)"
            :label="option.label"
            hide-details
            class="capability-option"
            @update:model-value="toggleCapability(option.value, $event)"
          />
        </fieldset>
        <div class="company-actions">
          <EButton
            variant="primary"
            size="small"
            type="submit"
            form="supplier-admin-company-form"
            :disabled="savingCompany"
            :loading="savingCompany"
          >
            {{ savingCompany ? t('common.saving') : t('common.save') }}
          </EButton>
          <EButton variant="danger" size="small" :disabled="deleting" :loading="deleting" @click="deleteCompany">
            {{ t('common.delete') }}
          </EButton>
        </div>
      </form>
    </section>

    <section v-if="form.manufacturer_key" class="section">
      <h4>{{ t('globalAddressesPage.supplierAdminModal.legacyTemplatesSection') }}</h4>
      <ELoadingState
        v-if="loadingLegacyPreview"
        variant="inline"
        :message="t('globalAddressesPage.loading')"
      />
      <p v-else-if="legacyPreview && legacyPreview.available_count === 0" class="hint">
        {{ t('globalAddressesPage.supplierAdminModal.legacyTemplatesNone', {
          imported: legacyPreview.already_imported_count,
        }) }}
      </p>
      <template v-else-if="legacyPreview">
        <p class="hint">
          {{ t('globalAddressesPage.supplierAdminModal.legacyTemplatesAvailable', {
            count: legacyPreview.available_count,
          }) }}
        </p>
        <ul v-if="legacyPreview.templates.length" class="legacy-list">
          <li v-for="item in legacyPreview.templates.filter((t) => !t.already_imported)" :key="item.legacy_material_template_id">
            {{ item.name }}
            <span class="muted">({{ item.component_count }} {{ t('supplierTemplates.columns.components') }})</span>
          </li>
        </ul>
        <EButton
          variant="secondary"
          size="small"
          :disabled="importingLegacy || legacyPreview.available_count === 0"
          :loading="importingLegacy"
          @click="importLegacyTemplates"
        >
          {{ importingLegacy
            ? t('globalAddressesPage.supplierAdminModal.legacyTemplatesImporting')
            : t('globalAddressesPage.supplierAdminModal.legacyTemplatesImport') }}
        </EButton>
      </template>
    </section>

    <section class="section">
      <h4>{{ t('globalAddressesPage.supplierAdminModal.membersSection') }}</h4>
      <ELoadingState
        v-if="loadingMembers"
        variant="inline"
        :message="t('globalAddressesPage.loading')"
      />
      <p v-else-if="memberships.length === 0" class="hint">
        {{ t('globalAddressesPage.supplierAdminModal.noMembers') }}
      </p>
      <table v-else class="members-table">
        <thead>
          <tr>
            <th>{{ t('supplierTeam.columns.name') }}</th>
            <th>{{ t('supplierTeam.columns.email') }}</th>
            <th>{{ t('supplierTeam.columns.role') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="member in memberships" :key="member.user_id">
            <td>{{ member.name }}</td>
            <td>{{ member.email || '—' }}</td>
            <td>
              <ESelect
                :model-value="member.role"
                :items="roleSelectItems"
                hide-details
                :disabled="savingUserId === member.user_id"
                @update:model-value="onRoleChange(member, $event as SupplierMembershipRole)"
              />
            </td>
            <td class="actions-cell">
              <EButton
                variant="danger"
                size="small"
                :disabled="savingUserId === member.user_id"
                @click="removeMember(member)"
              >
                {{ t('common.remove') }}
              </EButton>
            </td>
          </tr>
        </tbody>
      </table>

      <form id="supplier-admin-add-member-form" class="add-member-form" @submit.prevent="addMember">
        <ETextField
          v-model="addForm.user_email"
          type="email"
          :label="t('globalAddressesPage.supplierAdminModal.addMemberEmail')"
          hide-details="auto"
          class="field-grow"
        />
        <ESelect
          v-model="addForm.role"
          :items="roleSelectItems"
          :label="t('supplierTeam.columns.role')"
          hide-details="auto"
          class="field-narrow"
        />
        <EButton
          variant="secondary"
          size="small"
          type="submit"
          form="supplier-admin-add-member-form"
          :disabled="addingMember"
          :loading="addingMember"
        >
          {{ addingMember ? t('common.saving') : t('globalAddressesPage.supplierAdminModal.addMember') }}
        </EButton>
      </form>
    </section>

    <v-alert v-if="error" type="error" variant="tonal" :text="error" />

    <template #actions>
      <EButton variant="secondary" size="small" @click="close">{{ t('common.cancel') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  addAdminSupplierMembership,
  deleteAdminSupplierCompany,
  importLegacySupplierTemplates,
  listAdminSupplierMemberships,
  patchAdminSupplierCompany,
  previewLegacySupplierTemplates,
  removeAdminSupplierMembership,
  updateAdminSupplierMembership,
  type AdminSupplierCompany,
  type AdminSupplierMembership,
  type LegacyTemplatePreview,
} from '@/api/adminSupplierCompanies'
import type { SupplierCompanyStatus, SupplierMembershipRole } from '@/api/supplier'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { EButton, ECheckbox, EDialog, ESelect, ETextField } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'

const props = defineProps<{
  company: AdminSupplierCompany
}>()

const emit = defineEmits<{
  close: []
  saved: []
  deleted: []
}>()

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const dialogOpen = ref(true)

const form = reactive({
  name: props.company.name,
  manufacturer_key: props.company.manufacturer_key || '',
  status: props.company.status as SupplierCompanyStatus,
  capabilities: [...(props.company.capabilities || [])],
})

const capabilityOptions = computed(() => [
  { value: 'catalog', label: t('globalAddressesPage.supplierAdminModal.capabilityCatalog') },
  { value: 'delivery', label: t('globalAddressesPage.supplierAdminModal.capabilityDelivery') },
  { value: 'templates', label: t('globalAddressesPage.supplierAdminModal.capabilityTemplates') },
  { value: 'repairs', label: t('globalAddressesPage.supplierAdminModal.capabilityRepairs') },
  { value: 'operator', label: t('globalAddressesPage.supplierAdminModal.capabilityOperator') },
])

const statusItems = computed(() => [
  { title: t('globalAddressesPage.supplierAdminModal.statusPending'), value: 'pending' as const },
  { title: t('globalAddressesPage.supplierAdminModal.statusActive'), value: 'active' as const },
  { title: t('globalAddressesPage.supplierAdminModal.statusSuspended'), value: 'suspended' as const },
])

const roleSelectItems = computed(() => [
  { title: t('supplierTeam.roles.admin'), value: 'admin' as const },
  { title: t('supplierTeam.roles.member'), value: 'member' as const },
])

const addForm = reactive({
  user_email: '',
  role: 'member' as SupplierMembershipRole,
})

const loadingMembers = ref(true)
const savingCompany = ref(false)
const deleting = ref(false)
const addingMember = ref(false)
const savingUserId = ref<string | null>(null)
const error = ref<string | null>(null)
const memberships = ref<AdminSupplierMembership[]>([])
const loadingLegacyPreview = ref(false)
const importingLegacy = ref(false)
const legacyPreview = ref<LegacyTemplatePreview | null>(null)

watch(dialogOpen, (open) => {
  if (!open) emit('close')
})

function close() {
  dialogOpen.value = false
}

function toggleCapability(value: string, checked: boolean | null) {
  if (checked) {
    if (!form.capabilities.includes(value)) {
      form.capabilities.push(value)
    }
  } else {
    form.capabilities = form.capabilities.filter((c) => c !== value)
  }
}

async function loadLegacyPreview() {
  if (!form.manufacturer_key.trim()) {
    legacyPreview.value = null
    return
  }
  loadingLegacyPreview.value = true
  try {
    legacyPreview.value = await previewLegacySupplierTemplates(props.company.id)
  } catch {
    legacyPreview.value = null
  } finally {
    loadingLegacyPreview.value = false
  }
}

async function importLegacyTemplates() {
  if (!legacyPreview.value || legacyPreview.value.available_count === 0) return

  importingLegacy.value = true
  error.value = null
  try {
    const result = await importLegacySupplierTemplates(props.company.id)
    toast.success(result.message || t('globalAddressesPage.supplierAdminModal.legacyTemplatesSuccess'))
    await loadLegacyPreview()
    emit('saved')
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('globalAddressesPage.supplierAdminModal.legacyTemplatesError')
  } finally {
    importingLegacy.value = false
  }
}

async function loadMembers() {
  loadingMembers.value = true
  error.value = null
  try {
    const result = await listAdminSupplierMemberships(props.company.id)
    memberships.value = result.memberships
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('globalAddressesPage.supplierAdminModal.errorLoadMembers')
  } finally {
    loadingMembers.value = false
  }
}

async function saveCompany() {
  savingCompany.value = true
  error.value = null
  try {
    await patchAdminSupplierCompany(props.company.id, {
      name: form.name,
      manufacturer_key: form.manufacturer_key || null,
      status: form.status,
      capabilities: form.capabilities,
    })
    toast.success(t('globalAddressesPage.supplierAdminModal.saveSuccess'))
    emit('saved')
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('globalAddressesPage.supplierAdminModal.errorSave')
  } finally {
    savingCompany.value = false
  }
}

async function deleteCompany() {
  const ok = await confirm.confirm({
    title: t('globalAddressesPage.supplierAdminModal.deleteTitle'),
    message: t('globalAddressesPage.supplierAdminModal.deleteMessage', { name: props.company.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return

  deleting.value = true
  error.value = null
  try {
    await deleteAdminSupplierCompany(props.company.id)
    toast.success(t('globalAddressesPage.supplierAdminModal.deleteSuccess'))
    emit('deleted')
    close()
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('globalAddressesPage.supplierAdminModal.errorDelete')
  } finally {
    deleting.value = false
  }
}

async function addMember() {
  addingMember.value = true
  error.value = null
  try {
    await addAdminSupplierMembership(props.company.id, {
      user_email: addForm.user_email,
      role: addForm.role,
    })
    addForm.user_email = ''
    addForm.role = 'member'
    await loadMembers()
    emit('saved')
    toast.success(t('globalAddressesPage.supplierAdminModal.addMemberSuccess'))
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('globalAddressesPage.supplierAdminModal.errorAddMember')
  } finally {
    addingMember.value = false
  }
}

async function onRoleChange(member: AdminSupplierMembership, role: SupplierMembershipRole) {
  if (role === member.role) return
  savingUserId.value = member.user_id
  error.value = null
  try {
    await updateAdminSupplierMembership(props.company.id, member.user_id, { role })
    member.role = role
    emit('saved')
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('globalAddressesPage.supplierAdminModal.errorUpdateMember')
  } finally {
    savingUserId.value = null
  }
}

async function removeMember(member: AdminSupplierMembership) {
  const ok = await confirm.confirm({
    title: t('globalAddressesPage.supplierAdminModal.removeMemberTitle'),
    message: t('globalAddressesPage.supplierAdminModal.removeMemberMessage', { name: member.name }),
    confirmText: t('common.remove'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return

  savingUserId.value = member.user_id
  error.value = null
  try {
    await removeAdminSupplierMembership(props.company.id, member.user_id)
    memberships.value = memberships.value.filter((m) => m.user_id !== member.user_id)
    emit('saved')
    toast.success(t('globalAddressesPage.supplierAdminModal.removeMemberSuccess'))
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('globalAddressesPage.supplierAdminModal.errorRemoveMember')
  } finally {
    savingUserId.value = null
  }
}

onMounted(() => {
  loadMembers()
  loadLegacyPreview()
})
</script>

<style scoped>
.modal-subtitle {
  margin: 4px 0 0;
  color: #6b7280;
  font-size: 13px;
  font-weight: 400;
}

.section {
  margin-bottom: 20px;
}

.section h4 {
  margin: 0 0 12px;
  font-size: 15px;
}

.company-form,
.add-member-form {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: flex-end;
}

.field-grow {
  flex: 1 1 180px;
}

.field-narrow {
  flex: 0 1 140px;
}

.company-actions {
  display: flex;
  gap: 8px;
  flex: 1 1 100%;
}

.members-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
  margin-bottom: 12px;
}

.members-table th,
.members-table td {
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
  padding: 8px;
}

.actions-cell {
  text-align: right;
}

.hint {
  color: #6b7280;
  font-size: 14px;
}

.capability-option {
  margin-bottom: 4px;
}

.capabilities-fieldset {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1 1 100%;
}

.capabilities-fieldset legend {
  padding: 0 4px;
  font-size: 14px;
  color: #374151;
}

.legacy-list {
  margin: 8px 0 12px;
  padding-left: 20px;
  font-size: 14px;
}

.muted {
  color: #6b7280;
}
</style>
