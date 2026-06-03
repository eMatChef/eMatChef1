<template>
  <div class="settings-page">
    <div class="header">
      <h1>{{ t('settings.joinCode.title') }}</h1>
      <p class="description">{{ t('settings.joinCode.description') }}</p>
    </div>

    <div v-if="userDepartments.length > 1" class="card">
      <ESelect
        id="department-select"
        v-model="selectedDepartmentId"
        :items="departmentSelectItems"
        :label="t('settings.common.selectDepartment')"
        hide-details
        @update:model-value="onDepartmentChange"
      />
    </div>

    <div v-if="!canManageJoinCode" class="card">
      <p class="muted">{{ t('settings.joinCode.noPermission') }}</p>
    </div>

    <ELoadingState
      v-else-if="isInviteLoading && !inviteData"
      variant="inline"
      :message="t('settings.joinCode.loading')"
    />

    <div v-else class="card">
      <div class="join-code-row">
        <code class="join-code">{{ inviteData?.join_code || '...' }}</code>
        <EButton variant="secondary" size="small" :disabled="!inviteData" @click="copyJoinCode">
          {{ t('settings.joinCode.copyCode') }}
        </EButton>
        <EButton variant="secondary" size="small" :disabled="!inviteData" @click="copyInviteLink">
          {{ t('settings.joinCode.copyInviteLink') }}
        </EButton>
        <EButton variant="secondary" size="small" :disabled="!inviteData?.register_invite_url" @click="copyRegisterInviteLink">
          {{ t('settings.joinCode.copyRegisterLink') }}
        </EButton>
        <EButton variant="primary" size="small" :disabled="isInviteLoading" :loading="isInviteLoading" @click="regenerateInviteCode">
          {{ isInviteLoading ? t('settings.joinCode.loading') : t('settings.joinCode.regenerate') }}
        </EButton>
      </div>
      <p v-if="inviteData?.invite_url" class="muted">{{ t('settings.joinCode.withAccount') }} {{ inviteData.invite_url }}</p>
      <p v-if="inviteData?.register_invite_url" class="muted">{{ t('settings.joinCode.withoutAccount') }} {{ inviteData.register_invite_url }}</p>
      <div v-if="inviteQrDataUrl" class="qr"><img :src="inviteQrDataUrl" alt="Join QR Code" /></div>

      <div v-if="pendingInvites.length > 0" class="pending-block">
        <h3>{{ t('settings.joinCode.pendingTitle') }}</h3>
        <div v-for="invite in pendingInvites" :key="invite.id" class="pending-item">
          <span>{{ invite.email }} ({{ formatRole(invite.role) }})</span>
          <EButton variant="danger" size="small" @click="removePendingInviteItem(invite.id)">
            {{ t('common.delete') }}
          </EButton>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { deletePendingInvite, getDepartmentInvite, getPendingInvites, regenerateDepartmentInvite, type DepartmentInviteData, type PendingInvite } from '@/api/joinRequests'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, ESelect } from '@/components/form/base'
import QRCode from 'qrcode'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()

const selectedDepartmentId = ref<string | null>(null)
const inviteData = ref<DepartmentInviteData | null>(null)
const inviteQrDataUrl = ref('')
const isInviteLoading = ref(false)
const pendingInvites = ref<PendingInvite[]>([])

const userDepartments = computed(() => authStore.departments || [])

const departmentSelectItems = computed(() =>
  userDepartments.value.map((dept) => ({
    title: dept.department?.name || dept.department_id,
    value: dept.department_id,
  })),
)

const currentRole = computed(() => {
  if (!selectedDepartmentId.value) return 'user'
  const dept = userDepartments.value.find((d) => d.department_id === selectedDepartmentId.value)
  return dept?.role || 'user'
})
const canManageJoinCode = computed(() => {
  const normalizedRole = String(currentRole.value || '').toLowerCase().trim()
  return ['dc', 'depchef', 'mw', 'matwart', 'sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(normalizedRole)
})

const roleNames: Record<string, string> = {
  sa: 'Superadmin', superadmin: 'Superadmin', org: 'Organisationschef', organisationschef: 'Organisationschef',
  sub: 'Suborgchef', suborgchef: 'Suborgchef', mw: 'Materialchef', matwart: 'Materialchef', dc: 'Departmentchef',
  depchef: 'Departmentchef', l1: 'Leiter 1', leader1: 'Leiter 1', l2: 'Leiter 2', leader2: 'Leiter 2',
  l3: 'Leiter 3', leader3: 'Leiter 3', u: 'Mitglied', user: 'Mitglied',
}
function formatRole(role: string): string { return roleNames[String(role).toLowerCase().trim()] || role }

async function loadInviteCode(deptId: string) {
  if (!canManageJoinCode.value) {
    inviteData.value = null
    inviteQrDataUrl.value = ''
    pendingInvites.value = []
    return
  }
  isInviteLoading.value = true
  try {
    inviteData.value = await getDepartmentInvite(deptId)
    const qrPayload =
      (inviteData.value.qr_payload || inviteData.value.invite_url || inviteData.value.register_qr_payload || '').trim()
    inviteQrDataUrl.value = await QRCode.toDataURL(qrPayload, { width: 180, margin: 1 })
    pendingInvites.value = await getPendingInvites(deptId)
  } catch {
    inviteData.value = null
    inviteQrDataUrl.value = ''
    pendingInvites.value = []
  } finally {
    isInviteLoading.value = false
  }
}

async function regenerateInviteCode() {
  if (!selectedDepartmentId.value) return
  isInviteLoading.value = true
  try {
    inviteData.value = await regenerateDepartmentInvite(selectedDepartmentId.value)
    const qrPayload =
      (inviteData.value.qr_payload || inviteData.value.invite_url || inviteData.value.register_qr_payload || '').trim()
    inviteQrDataUrl.value = await QRCode.toDataURL(qrPayload, { width: 180, margin: 1 })
    toast.success(t('settings.joinCode.toastRegenerated'))
  } catch {
    toast.error(t('settings.joinCode.toastRegenerateError'))
  } finally {
    isInviteLoading.value = false
  }
}

async function removePendingInviteItem(inviteId: string) {
  if (!selectedDepartmentId.value) return
  const ok = await confirm.confirm({
    title: t('settings.joinCode.confirmDeleteTitle'),
    message: t('settings.joinCode.confirmDeleteMessage'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deletePendingInvite(selectedDepartmentId.value, inviteId)
    pendingInvites.value = pendingInvites.value.filter((entry) => entry.id !== inviteId)
    toast.success(t('settings.joinCode.toastPendingDeleted'))
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.joinCode.toastPendingDeleteError'))
  }
}

async function copyJoinCode() {
  if (!inviteData.value) return
  await navigator.clipboard.writeText(inviteData.value.join_code)
  toast.success(t('settings.joinCode.toastCopiedCode'))
}
async function copyInviteLink() {
  if (!inviteData.value) return
  await navigator.clipboard.writeText(inviteData.value.invite_url)
  toast.success(t('settings.joinCode.toastCopiedInviteLink'))
}
async function copyRegisterInviteLink() {
  const url = inviteData.value?.register_invite_url?.trim()
  if (!url) return
  await navigator.clipboard.writeText(url)
  toast.success(t('settings.joinCode.toastCopiedRegisterLink'))
}

async function onDepartmentChange() {
  if (!selectedDepartmentId.value) return
  const newDeptId = selectedDepartmentId.value
  await authStore.setActiveDepartment(newDeptId)
  const oldDeptId = route.params.departmentId as string | undefined
  if (oldDeptId && oldDeptId !== newDeptId) {
    const newPath = route.path.replace(`/${oldDeptId}`, `/${newDeptId}`)
    window.location.assign(newPath)
    return
  }
  await loadInviteCode(newDeptId)
}

onMounted(async () => {
  selectedDepartmentId.value = authStore.activeDepartmentId || (userDepartments.value[0]?.department_id ?? null)
  if (selectedDepartmentId.value) await loadInviteCode(selectedDepartmentId.value)
})
</script>

<style scoped>
.settings-page { display: flex; flex-direction: column; gap: 16px; }
.header h1 { margin: 0; font-size: 24px; }
.description, .muted { color: #6b7280; }
.card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
.join-code-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.join-code { display: inline-block; background: #111827; color: #fff; border-radius: 8px; padding: 8px 12px; font-weight: 700; letter-spacing: 1px; }
.qr img { margin-top: 10px; width: 120px; height: 120px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 4px; background: #fff; }
.pending-block { margin-top: 12px; padding-top: 12px; border-top: 1px dashed #d1d5db; }
.pending-block h3 { margin: 0 0 10px; font-size: 15px; }
.pending-item { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 6px 0; flex-wrap: wrap; }
</style>
