<template>
  <div class="settings-page">
    <div class="header">
      <h1>Join-Code</h1>
      <p class="description">Einladungen und Links für neue Mitglieder</p>
    </div>

    <div v-if="userDepartments.length > 1" class="card">
      <label class="label" for="department-select">Department auswählen:</label>
      <select id="department-select" v-model="selectedDepartmentId" @change="onDepartmentChange" class="input">
        <option v-for="dept in userDepartments" :key="dept.department_id" :value="dept.department_id">
          {{ dept.department?.name || dept.department_id }}
        </option>
      </select>
    </div>

    <div v-if="!canManageJoinCode" class="card">
      <p class="muted">Du hast keine Berechtigung, Join-Codes zu verwalten.</p>
    </div>

    <div v-else class="card">
      <div class="join-code-row">
        <code class="join-code">{{ inviteData?.join_code || '...' }}</code>
        <button class="btn" type="button" :disabled="!inviteData" @click="copyJoinCode">Code kopieren</button>
        <button class="btn" type="button" :disabled="!inviteData" @click="copyInviteLink">Link (mit Konto)</button>
        <button class="btn" type="button" :disabled="!inviteData?.register_invite_url" @click="copyRegisterInviteLink">
          Link (Registrierung)
        </button>
        <button class="btn" type="button" :disabled="isInviteLoading" @click="regenerateInviteCode">
          {{ isInviteLoading ? 'Lade...' : 'Neu generieren' }}
        </button>
      </div>
      <p class="muted" v-if="inviteData?.invite_url">Mit bestehendem Konto: {{ inviteData.invite_url }}</p>
      <p class="muted" v-if="inviteData?.register_invite_url">Ohne Konto (Registrierung): {{ inviteData.register_invite_url }}</p>
      <div class="qr" v-if="inviteQrDataUrl"><img :src="inviteQrDataUrl" alt="Join QR Code" /></div>

      <div v-if="pendingInvites.length > 0" class="pending-block">
        <h3>Eingeladene Mitglieder</h3>
        <div class="pending-item" v-for="invite in pendingInvites" :key="invite.id">
          <span>{{ invite.email }} ({{ formatRole(invite.role) }})</span>
          <button class="btn danger" @click="removePendingInviteItem(invite.id)">Löschen</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { deletePendingInvite, getDepartmentInvite, getPendingInvites, regenerateDepartmentInvite, type DepartmentInviteData, type PendingInvite } from '@/api/joinRequests'
import QRCode from 'qrcode'

const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const selectedDepartmentId = ref<string | null>(null)
const inviteData = ref<DepartmentInviteData | null>(null)
const inviteQrDataUrl = ref('')
const isInviteLoading = ref(false)
const pendingInvites = ref<PendingInvite[]>([])

const userDepartments = computed(() => authStore.departments || [])
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
    const qrPayload = ((inviteData.value.register_qr_payload || inviteData.value.qr_payload || '').trim()) || inviteData.value.invite_url
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
    const qrPayload = ((inviteData.value.register_qr_payload || inviteData.value.qr_payload || '').trim()) || inviteData.value.invite_url
    inviteQrDataUrl.value = await QRCode.toDataURL(qrPayload, { width: 180, margin: 1 })
    toast.success('Join-Code wurde neu generiert.')
  } catch {
    toast.error('Join-Code konnte nicht neu erstellt werden.')
  } finally {
    isInviteLoading.value = false
  }
}

async function removePendingInviteItem(inviteId: string) {
  if (!selectedDepartmentId.value) return
  const ok = await confirm.confirm({
    title: 'Pending Einladung loeschen?',
    message: 'Die Einladung wird entfernt und der Link ist nicht mehr in der Liste sichtbar.',
    confirmText: 'Loeschen',
    cancelText: 'Abbrechen',
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deletePendingInvite(selectedDepartmentId.value, inviteId)
    pendingInvites.value = pendingInvites.value.filter((entry) => entry.id !== inviteId)
    toast.success('Pending Einladung geloescht.')
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Pending Einladung konnte nicht geloescht werden.')
  }
}

async function copyJoinCode() {
  if (!inviteData.value) return
  await navigator.clipboard.writeText(inviteData.value.join_code)
  toast.success('Join-Code kopiert.')
}
async function copyInviteLink() {
  if (!inviteData.value) return
  await navigator.clipboard.writeText(inviteData.value.invite_url)
  toast.success('Link zur Join-Seite kopiert.')
}
async function copyRegisterInviteLink() {
  const url = inviteData.value?.register_invite_url?.trim()
  if (!url) return
  await navigator.clipboard.writeText(url)
  toast.success('Registrierungslink kopiert.')
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
.label { display: block; margin-bottom: 8px; font-size: 13px; color: #6b7280; }
.input { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; background: #fff; }
.join-code-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.join-code { display: inline-block; background: #111827; color: #fff; border-radius: 8px; padding: 8px 12px; font-weight: 700; letter-spacing: 1px; }
.btn { border: none; border-radius: 8px; background: #2563eb; color: #fff; padding: 8px 12px; cursor: pointer; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn.danger { background: #dc2626; }
.qr img { margin-top: 10px; width: 120px; height: 120px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 4px; background: #fff; }
.pending-block { margin-top: 12px; padding-top: 12px; border-top: 1px dashed #d1d5db; }
.pending-block h3 { margin: 0 0 10px; font-size: 15px; }
.pending-item { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 6px 0; }
</style>
