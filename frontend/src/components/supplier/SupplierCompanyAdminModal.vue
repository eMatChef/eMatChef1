<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <header class="modal-header">
        <div>
          <h3>{{ t('globalAddressesPage.supplierAdminModal.title', { name: company.name }) }}</h3>
          <p class="modal-subtitle">{{ t('globalAddressesPage.supplierAdminModal.subtitle') }}</p>
        </div>
        <button type="button" class="btn btn-secondary btn-inline" @click="emit('close')">
          {{ t('common.cancel') }}
        </button>
      </header>

      <div class="modal-body">
        <section class="section">
          <h4>{{ t('globalAddressesPage.supplierAdminModal.companySection') }}</h4>
          <form class="company-form" @submit.prevent="saveCompany">
            <label class="field">
              <span>{{ t('globalAddressesPage.supplierModal.name') }}</span>
              <input v-model.trim="form.name" type="text" required />
            </label>
            <label class="field">
              <span>{{ t('globalAddressesPage.supplierModal.manufacturerKey') }}</span>
              <input v-model.trim="form.manufacturer_key" type="text" />
            </label>
            <label class="field">
              <span>{{ t('globalAddressesPage.tableStatus') }}</span>
              <select v-model="form.status">
                <option value="pending">{{ t('globalAddressesPage.supplierAdminModal.statusPending') }}</option>
                <option value="active">{{ t('globalAddressesPage.supplierAdminModal.statusActive') }}</option>
                <option value="suspended">{{ t('globalAddressesPage.supplierAdminModal.statusSuspended') }}</option>
              </select>
            </label>
            <div class="company-actions">
              <button type="submit" class="btn btn-primary btn-sm" :disabled="savingCompany">
                {{ savingCompany ? t('common.saving') : t('common.save') }}
              </button>
              <button type="button" class="btn btn-danger btn-sm" :disabled="deleting" @click="deleteCompany">
                {{ t('common.delete') }}
              </button>
            </div>
          </form>
        </section>

        <section class="section">
          <h4>{{ t('globalAddressesPage.supplierAdminModal.membersSection') }}</h4>
          <p v-if="loadingMembers" class="hint">{{ t('globalAddressesPage.loading') }}</p>
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
                  <select
                    class="role-select"
                    :value="member.role"
                    :disabled="savingUserId === member.user_id"
                    @change="onRoleChange(member, ($event.target as HTMLSelectElement).value as SupplierMembershipRole)"
                  >
                    <option value="admin">{{ t('supplierTeam.roles.admin') }}</option>
                    <option value="member">{{ t('supplierTeam.roles.member') }}</option>
                  </select>
                </td>
                <td class="actions-cell">
                  <button
                    type="button"
                    class="btn btn-danger btn-sm"
                    :disabled="savingUserId === member.user_id"
                    @click="removeMember(member)"
                  >
                    {{ t('common.remove') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <form class="add-member-form" @submit.prevent="addMember">
            <label class="field">
              <span>{{ t('globalAddressesPage.supplierAdminModal.addMemberEmail') }}</span>
              <input v-model.trim="addForm.user_email" type="email" required />
            </label>
            <label class="field field-narrow">
              <span>{{ t('supplierTeam.columns.role') }}</span>
              <select v-model="addForm.role">
                <option value="admin">{{ t('supplierTeam.roles.admin') }}</option>
                <option value="member">{{ t('supplierTeam.roles.member') }}</option>
              </select>
            </label>
            <button type="submit" class="btn btn-secondary btn-sm" :disabled="addingMember">
              {{ addingMember ? t('common.saving') : t('globalAddressesPage.supplierAdminModal.addMember') }}
            </button>
          </form>
        </section>

        <p v-if="error" class="error">{{ error }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  addAdminSupplierMembership,
  deleteAdminSupplierCompany,
  listAdminSupplierMemberships,
  patchAdminSupplierCompany,
  removeAdminSupplierMembership,
  updateAdminSupplierMembership,
  type AdminSupplierCompany,
  type AdminSupplierMembership,
} from '@/api/adminSupplierCompanies'
import type { SupplierCompanyStatus, SupplierMembershipRole } from '@/api/supplier'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

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

const form = reactive({
  name: props.company.name,
  manufacturer_key: props.company.manufacturer_key || '',
  status: props.company.status as SupplierCompanyStatus,
})

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
    emit('close')
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
})
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 720px;
  max-height: 90vh;
  overflow: auto;
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.15);
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
}

.modal-subtitle {
  margin: 4px 0 0;
  color: #6b7280;
  font-size: 13px;
}

.modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 20px;
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

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
  flex: 1 1 180px;
}

.field-narrow {
  flex: 0 1 140px;
}

.field input,
.field select,
.role-select {
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
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

.error {
  color: #b91c1c;
  font-size: 14px;
}

.btn-inline,
.btn-sm {
  padding: 6px 10px;
  font-size: 12px;
}
</style>
