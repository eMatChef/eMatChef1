<template>
  <div class="supplier-page">
    <header class="supplier-page-header">
      <h1>{{ t('supplierTeam.title') }}</h1>
      <p class="supplier-page-subtitle">{{ companyName }}</p>
    </header>

    <ELoadingState
      v-if="loading"
      variant="inline"
      :message="t('common.loading')"
    />
    <div v-else-if="loadError" class="supplier-page-error">
      <v-alert type="error" variant="tonal" :text="loadError" />
    </div>

    <template v-else>
      <section class="team-section">
        <h2 class="section-title">{{ t('supplierTeam.joinCodeTitle') }}</h2>
        <p class="section-hint">{{ t('supplierTeam.joinCodeHint') }}</p>
        <div class="join-code-row">
          <code class="join-code">{{ joinCodeData?.join_code || '…' }}</code>
          <EButton variant="secondary" size="small" :disabled="!joinCodeData" @click="copyJoinCode">
            {{ t('supplierTeam.copyCode') }}
          </EButton>
          <EButton variant="secondary" size="small" :disabled="!joinCodeData" @click="copyInviteLink">
            {{ t('supplierTeam.copyLink') }}
          </EButton>
          <EButton
            variant="secondary"
            size="small"
            :disabled="joinCodeLoading"
            :loading="joinCodeLoading"
            @click="regenerateJoinCode"
          >
            {{ joinCodeLoading ? t('common.saving') : t('supplierTeam.regenerate') }}
          </EButton>
        </div>
        <p v-if="joinCodeData?.invite_url" class="invite-url-hint">{{ joinCodeData.invite_url }}</p>
      </section>

      <section class="team-section">
        <h2 class="section-title">{{ t('supplierTeam.membersTitle') }}</h2>
        <EEmptyState
          v-if="memberships.length === 0"
          :title="t('supplierTeam.emptyMembers')"
        />
        <table v-else class="team-table">
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
                  :disabled="savingUserId === member.user_id || member.user_id === authStore.userId"
                  @click="removeMember(member)"
                >
                  {{ t('common.remove') }}
                </EButton>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="actionError" class="form-error">{{ actionError }}</p>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import type { SupplierMembershipRole } from '@/api/supplier'
import {
  getSupplierJoinCode,
  listSupplierMemberships,
  regenerateSupplierJoinCode,
  removeSupplierMembership,
  updateSupplierMembershipRole,
  type SupplierJoinCodeData,
  type SupplierMembershipRow,
} from '@/api/supplierMemberships'
import { EButton, ESelect } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'

const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const companyId = computed(() => route.params.companyId as string)
const companyName = computed(() => {
  const company = authStore.activeSupplierCompanies.find((c) => c.id === companyId.value)
  return company?.name || authStore.activeSupplierCompanyName
})

const roleSelectItems = computed(() => [
  { title: t('supplierTeam.roles.admin'), value: 'admin' as const },
  { title: t('supplierTeam.roles.member'), value: 'member' as const },
])

const loading = ref(true)
const loadError = ref('')
const actionError = ref('')
const joinCodeLoading = ref(false)
const savingUserId = ref<string | null>(null)
const memberships = ref<SupplierMembershipRow[]>([])
const joinCodeData = ref<SupplierJoinCodeData | null>(null)

async function loadTeam() {
  loading.value = true
  loadError.value = ''
  try {
    const [membersResult, joinResult] = await Promise.all([
      listSupplierMemberships(companyId.value),
      getSupplierJoinCode(companyId.value),
    ])
    memberships.value = membersResult.memberships
    joinCodeData.value = joinResult
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    loadError.value = e?.response?.data?.error || t('supplierTeam.loadFailed')
  } finally {
    loading.value = false
  }
}

async function regenerateJoinCode() {
  const ok = await confirm.confirm({
    title: t('supplierTeam.regenerateConfirmTitle'),
    message: t('supplierTeam.regenerateConfirmMessage'),
    confirmText: t('supplierTeam.regenerate'),
    cancelText: t('common.cancel'),
    variant: 'warning',
  })
  if (!ok) return

  joinCodeLoading.value = true
  actionError.value = ''
  try {
    joinCodeData.value = await regenerateSupplierJoinCode(companyId.value)
    toast.success(t('supplierTeam.regenerateSuccess'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    actionError.value = e?.response?.data?.error || t('supplierTeam.regenerateFailed')
  } finally {
    joinCodeLoading.value = false
  }
}

async function copyJoinCode() {
  if (!joinCodeData.value) return
  await navigator.clipboard.writeText(joinCodeData.value.join_code)
  toast.success(t('supplierTeam.copyCodeSuccess'))
}

async function copyInviteLink() {
  if (!joinCodeData.value) return
  await navigator.clipboard.writeText(joinCodeData.value.invite_url)
  toast.success(t('supplierTeam.copyLinkSuccess'))
}

async function onRoleChange(member: SupplierMembershipRow, role: SupplierMembershipRole) {
  if (member.role === role) return
  savingUserId.value = member.user_id
  actionError.value = ''
  try {
    const { membership } = await updateSupplierMembershipRole(companyId.value, member.user_id, role)
    memberships.value = memberships.value.map((m) => (m.user_id === member.user_id ? membership : m))
    toast.success(t('supplierTeam.roleUpdated'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    actionError.value = e?.response?.data?.error || t('supplierTeam.roleUpdateFailed')
  } finally {
    savingUserId.value = null
  }
}

async function removeMember(member: SupplierMembershipRow) {
  const ok = await confirm.confirm({
    title: t('supplierTeam.removeConfirmTitle'),
    message: t('supplierTeam.removeConfirmMessage', { name: member.name }),
    confirmText: t('common.remove'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return

  savingUserId.value = member.user_id
  actionError.value = ''
  try {
    await removeSupplierMembership(companyId.value, member.user_id)
    memberships.value = memberships.value.filter((m) => m.user_id !== member.user_id)
    toast.success(t('supplierTeam.removeSuccess'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    actionError.value = e?.response?.data?.error || t('supplierTeam.removeFailed')
  } finally {
    savingUserId.value = null
  }
}

watch(companyId, () => {
  void loadTeam()
})

onMounted(() => {
  void loadTeam()
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

.supplier-page-subtitle {
  margin: 0;
  color: #6b7280;
}

.supplier-page-error {
  margin-top: 24px;
}

.team-section {
  margin-top: 28px;
}

.section-title {
  margin: 0 0 8px;
  font-size: 1.125rem;
  font-weight: 600;
  color: #374151;
}

.section-hint {
  margin: 0 0 12px;
  color: #6b7280;
  font-size: 0.9rem;
}

.join-code-row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.join-code {
  display: inline-block;
  background: #111827;
  color: #fff;
  border-radius: 8px;
  padding: 8px 12px;
  font-weight: 700;
  letter-spacing: 1px;
}

.invite-url-hint {
  margin-top: 10px;
  font-size: 0.85rem;
  color: #6b7280;
  word-break: break-all;
}

.team-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 8px;
}

.team-table th,
.team-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
}

.team-table th {
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b7280;
}

.actions-cell {
  text-align: right;
}

.form-error {
  margin-top: 12px;
  color: #b91c1c;
  font-size: 0.875rem;
}
</style>
