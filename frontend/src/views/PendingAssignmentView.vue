<template>
  <div class="pending-page">
    <div class="pending-card">
      <h1>{{ t('pendingAssignment.title') }}</h1>
      <p>
        {{ t('pendingAssignment.intro') }}
      </p>

      <div class="box">
        <label class="label">{{ t('pendingAssignment.searchLabel') }}</label>
        <SearchFieldInput
          v-model="departmentQuery"
          :label="t('pendingAssignment.searchPlaceholder')"
          class="search-box"
          @clear="clearDepartmentSearch"
        >
          <div v-if="displayedDepartmentResults.length > 0" class="search-results search-results--above">
            <button
              v-for="d in displayedDepartmentResults"
              :key="d.id"
              type="button"
              class="search-result-item"
              @click="selectDepartment(d)"
            >
              <span class="result-name">{{ d.name }}</span>
              <span class="result-org">{{ d.organisation_name }}</span>
            </button>
            <p v-if="departmentResults.length > 4" class="search-more-hint">
              {{ t('pendingAssignment.searchMoreHint') }}
            </p>
          </div>
        </SearchFieldInput>
        <div v-if="departmentLoading" class="hint">{{ t('pendingAssignment.searchLoading') }}</div>
        <div v-if="showManualAdminRequest" class="manual-request-box">
          <p class="hint">{{ t('pendingAssignment.manualHint') }}</p>
          <div class="form-group">
            <label class="form-label">{{ t('pendingAssignment.organisationRequired') }}</label>
            <select v-model="manualOrganisationId" class="form-select">
              <option value="" disabled hidden>&nbsp;</option>
              <option v-for="org in organisationsFiltered" :key="org.id" :value="org.id">{{ org.name }}</option>
            </select>
          </div>
          <div class="form-row">
            <input
              v-model="manualDepartmentName"
              class="form-input"
              :placeholder="t('pendingAssignment.departmentNamePlaceholder')"
            />
            <input
              v-model="manualAffiliation"
              class="form-input"
              :placeholder="t('pendingAssignment.affiliationPlaceholder')"
            />
          </div>
          <ParentDepartmentPicker
            v-if="manualOrganisationId"
            :organisation-id="manualOrganisationId"
            :disabled="loading"
            @update:model-value="manualParentPick = $event"
          />
          <button class="btn btn-secondary" type="button" :disabled="loading || !manualOrganisationId" @click="submitAdminRequest">
            {{ t('pendingAssignment.submitAdminRequest') }}
          </button>
        </div>
        <p v-if="selectedDepartment" class="success">
          {{ t('pendingAssignment.selectedDepartment', { name: selectedDepartment.name, org: selectedDepartment.organisation_name }) }}
        </p>
      </div>

      <div class="box">
        <label class="label">{{ t('pendingAssignment.joinCodeLabel') }}</label>
        <input v-model="joinCode" class="form-input" :placeholder="t('pendingAssignment.joinCodePlaceholder')" />
      </div>

      <div v-if="turnstileRequired" ref="turnstileContainerRef" class="turnstile-box" />

      <div class="actions">
        <button class="btn btn-primary" :disabled="loading" @click="submitRequest">
          {{ t('pendingAssignment.sendJoinRequest') }}
        </button>
        <button class="btn btn-secondary" type="button" @click="toggleScanner">
          {{ scannerActive ? t('pendingAssignment.scannerStop') : t('pendingAssignment.scannerStart') }}
        </button>
        <button class="btn btn-link" type="button" @click="adminContactModalOpen = true">
          {{ t('pendingAssignment.contactAdmin') }}
        </button>
      </div>

      <!-- Modal: Admin kontaktieren -->
      <EDialog
        v-model="adminContactModalOpen"
        :title="t('pendingAssignment.adminModalTitle')"
        max-width="480"
        scrollable
      >
        <p class="pending-admin-intro">{{ t('pendingAssignment.adminModalIntro') }}</p>

        <div class="pending-admin-form">
          <ESelect
            v-model="adminModalOrganisationId"
            :items="organisationSelectItems"
            :label="t('pendingAssignment.organisationRequired')"
            :disabled="adminModalLoading"
          />

          <ETextField
            v-model="adminModalDepartmentName"
            :label="t('pendingAssignment.departmentNameRequired')"
            :placeholder="t('pendingAssignment.departmentNameModalPlaceholder')"
            :disabled="adminModalLoading"
          />

          <ETextField
            v-model="adminModalAffiliation"
            :label="t('pendingAssignment.affiliationModalLabel')"
            :placeholder="t('pendingAssignment.affiliationModalPlaceholder')"
            :disabled="adminModalLoading"
          />

          <div v-if="adminModalOrganisationId" class="pending-admin-form__picker">
            <ParentDepartmentPicker
              :organisation-id="adminModalOrganisationId"
              :disabled="adminModalLoading"
              @update:model-value="adminModalParentPick = $event"
            />
          </div>

          <ETextarea
            v-model="adminModalMessage"
            :label="t('pendingAssignment.messageToAdmin')"
            :placeholder="t('pendingAssignment.messageToAdminPlaceholder')"
            :rows="3"
            :disabled="adminModalLoading"
          />
        </div>

        <v-alert
          v-if="adminModalError"
          type="error"
          variant="tonal"
          density="compact"
          class="pending-admin-alert"
        >
          {{ adminModalError }}
        </v-alert>

        <template #actions>
          <EButton variant="secondary" :disabled="adminModalLoading" @click="adminContactModalOpen = false">
            {{ t('common.cancel') }}
          </EButton>
          <EButton
            variant="primary"
            :disabled="adminModalLoading || !adminModalOrganisationId || !adminModalDepartmentName.trim()"
            :loading="adminModalLoading"
            @click="submitAdminContactModal"
          >
            {{ t('pendingAssignment.sendTicket') }}
          </EButton>
        </template>
      </EDialog>

      <BarcodeScannerPanel
        v-if="scannerActive"
        :active="scannerActive"
        mode="qr"
        :hint="t('pendingAssignment.scannerHint')"
        @detected="onQrDetected"
        @error="onScannerError"
      />
      <p v-if="scannerError" class="error">{{ scannerError }}</p>

      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="success" class="success">{{ success }}</p>
    </div>

    <div class="pending-card">
      <h2>{{ t('pendingAssignment.myOpenRequests') }}</h2>
      <p v-if="requests.length === 0">{{ t('pendingAssignment.noRequestsYet') }}</p>
      <table v-else>
        <thead>
          <tr>
            <th>{{ t('pendingAssignment.colType') }}</th>
            <th>{{ t('pendingAssignment.colDepartment') }}</th>
            <th>{{ t('common.status') }}</th>
            <th>{{ t('pendingAssignment.colCreated') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in requests" :key="`${r.request_kind || 'join'}-${r.id}`">
            <td>{{ requestKindLabel(r) }}</td>
            <td>
              <span>{{ r.department_name }}</span>
              <span v-if="r.organisation_name" class="request-org-hint">({{ r.organisation_name }})</span>
              <span v-if="r.requested_parent_department_name" class="request-org-hint">
                · {{ t('pendingAssignment.parentDept', { name: r.requested_parent_department_name }) }}
              </span>
            </td>
            <td>{{ statusLabel(r.status) }}</td>
            <td>{{ formatDate(r.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useTurnstile } from '@/composables/useTurnstile'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  createAdminJoinRequest,
  createJoinRequest,
  getMyJoinRequests,
  searchJoinableDepartments,
  type DepartmentSearchResult,
  type MyJoinRequest,
} from '@/api/joinRequests'
import { joinSupplierCompany } from '@/api/supplierMemberships'
import { getOrganisations, type Organisation } from '@/api/organisations'
import ParentDepartmentPicker, {
  type ParentDepartmentPickerValue,
} from '@/components/auth/ParentDepartmentPicker.vue'
import { filterOrganisationsForUserPickers } from '@/utils/organisationUserPicker'
import { localizedBarcodeScannerError } from '@/utils/barcodeScannerErrors'
import BarcodeScannerPanel from '@/components/common/BarcodeScannerPanel.vue'
import SearchFieldInput from '@/components/common/SearchFieldInput.vue'
import { EButton, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'

const route = useRoute()
const { t, locale } = useI18n()
const authStore = useAuthStore()
const {
  isRequired: turnstileRequired,
  containerRef: turnstileContainerRef,
  init: initTurnstile,
  getToken: getTurnstileToken,
  reset: resetTurnstile,
} = useTurnstile()
const joinCode = ref('')
const inviteRole = ref('u')
const departmentQuery = ref('')
const departmentLoading = ref(false)
const departmentResults = ref<DepartmentSearchResult[]>([])
const selectedDepartment = ref<DepartmentSearchResult | null>(null)
const manualDepartmentName = ref('')
const manualAffiliation = ref('')
const manualParentPick = ref<ParentDepartmentPickerValue | null>(null)
const manualOrganisationId = ref('')
const organisations = ref<Organisation[]>([])
const message = ref('')
const adminContactModalOpen = ref(false)
const adminModalOrganisationId = ref('')
const adminModalDepartmentName = ref('')
const adminModalAffiliation = ref('')
const adminModalParentPick = ref<ParentDepartmentPickerValue | null>(null)
const adminModalMessage = ref('')
const adminModalLoading = ref(false)
const adminModalError = ref<string | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const requests = ref<MyJoinRequest[]>([])
const scannerActive = ref(false)
const scannerError = ref<string | null>(null)
const displayedDepartmentResults = computed(() => departmentResults.value.slice(0, 4))
const organisationsFiltered = computed(() => filterOrganisationsForUserPickers(organisations.value))
const organisationSelectItems = computed(() =>
  organisationsFiltered.value.map((org) => ({
    title: org.name,
    value: org.id,
  }))
)
const showManualAdminRequest = computed(() => {
  return departmentQuery.value.trim().length >= 2 && !departmentLoading.value && departmentResults.value.length === 0
})

let searchTimer: ReturnType<typeof setTimeout> | null = null
let autoJoinTriggered = false

function statusLabel(status: string): string {
  if (status === 'approved' || status === 'assigned') return t('pendingAssignment.statusApproved')
  if (status === 'rejected') return t('pendingAssignment.statusRejected')
  return t('pendingAssignment.statusOpen')
}

function requestKindLabel(r: MyJoinRequest): string {
  if (r.request_kind === 'admin') return t('pendingAssignment.requestKindAdmin')
  return t('pendingAssignment.requestKindJoin')
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString(locale.value)
}

function selectDepartment(department: DepartmentSearchResult) {
  selectedDepartment.value = department
  manualDepartmentName.value = department.name
  manualAffiliation.value = department.organisation_name
  manualOrganisationId.value = ''
  error.value = null
}

function clearDepartmentSearch() {
  departmentQuery.value = ''
  departmentResults.value = []
  departmentLoading.value = false
}

function extractJoinCode(scannedText: string): string {
  const raw = scannedText.trim()
  try {
    const url = new URL(raw)
    const fromQuery = url.searchParams.get('join_code')
    if (fromQuery && fromQuery.trim()) return fromQuery.trim()
  } catch {
    // kein URL-Format
  }
  const directCode = raw.match(/[A-Za-z0-9]{8,12}/)
  return directCode ? directCode[0] : ''
}

function stopScanner() {
  scannerActive.value = false
}

function onQrDetected(payload: { text: string }) {
  const scanned = extractJoinCode(payload.text)
  if (!scanned) {
    scannerError.value = t('pendingAssignment.qrNoJoinCode')
    return
  }
  scannerError.value = null
  joinCode.value = scanned.toUpperCase()
  selectedDepartment.value = null
  success.value = t('pendingAssignment.joinCodeFromQr')
  stopScanner()
}

function onScannerError(message: string) {
  scannerError.value = localizedBarcodeScannerError(message, t)
}

function toggleScanner() {
  if (scannerActive.value) {
    stopScanner()
    return
  }
  scannerError.value = null
  scannerActive.value = true
}

async function loadMine() {
  try {
    requests.value = await getMyJoinRequests()
  } catch (e) {
    console.error(e)
  }
}

function requireTurnstileToken(): string | undefined {
  if (!turnstileRequired.value) return undefined
  const token = getTurnstileToken()
  if (!token) {
    error.value = t('login.validationCaptcha')
    return undefined
  }
  return token
}

async function submitRequest() {
  if (!selectedDepartment.value && !joinCode.value.trim()) {
    error.value = t('pendingAssignment.errorSelectOrCode')
    return
  }

  const turnstileToken = requireTurnstileToken()
  if (turnstileRequired.value && !turnstileToken) return

  loading.value = true
  error.value = null
  success.value = null

  try {
    const created = await createJoinRequest({
      departmentId: selectedDepartment.value?.id,
      joinCode: selectedDepartment.value ? undefined : joinCode.value.trim(),
      message: message.value.trim() || undefined,
      requestedRole: inviteRole.value,
      turnstileToken,
    })
    if (created.auto_joined) {
      await authStore.loadDepartments()
      const deptId = created.department_id || authStore.activeDepartmentId
      if (deptId) {
        window.location.href = `/${deptId}`
        return
      }
      success.value = t('pendingAssignment.successAutoJoined')
    } else {
      success.value = t('pendingAssignment.successRequestSent')
    }
    joinCode.value = ''
    selectedDepartment.value = null
    departmentQuery.value = ''
    departmentResults.value = []
    message.value = ''
    await loadMine()
  } catch (err: any) {
    const deptError = err?.response?.data?.error
    const canTrySupplierJoin =
      !selectedDepartment.value &&
      joinCode.value.trim() &&
      (err?.response?.status === 404 ||
        String(deptError || '').toLowerCase().includes('join-code') ||
        String(deptError || '').toLowerCase().includes('department'))

    if (canTrySupplierJoin) {
      try {
        const joined = await joinSupplierCompany(joinCode.value.trim())
        await authStore.loadUserSessionFromCookie(true)
        joinCode.value = ''
        message.value = ''
        window.location.href = joined.redirect_path
        return
      } catch (supplierErr: any) {
        error.value =
          supplierErr?.response?.data?.error || deptError || t('pendingAssignment.errorSendFailed')
      }
    } else {
      error.value = deptError || t('pendingAssignment.errorSendFailed')
    }
    resetTurnstile()
  } finally {
    loading.value = false
  }
}

function parentPayload(pick: ParentDepartmentPickerValue | null) {
  return {
    requestedParentDepartmentId: pick?.departmentId || undefined,
    requestedParentDepartmentName: pick?.departmentName || undefined,
  }
}

async function submitAdminContactModal() {
  const deptName = adminModalDepartmentName.value.trim()
  if (!deptName) {
    adminModalError.value = t('pendingAssignment.errorDeptName')
    return
  }
  if (!adminModalOrganisationId.value) {
    adminModalError.value = t('pendingAssignment.errorPickOrg')
    return
  }

  const turnstileToken = requireTurnstileToken()
  if (turnstileRequired.value && !turnstileToken) {
    adminModalError.value = t('login.validationCaptcha')
    return
  }

  adminModalLoading.value = true
  adminModalError.value = null
  try {
    await createAdminJoinRequest({
      requestedDepartmentName: deptName,
      requestedAffiliation: adminModalAffiliation.value.trim() || undefined,
      requestedOrganisationId: adminModalOrganisationId.value,
      ...parentPayload(adminModalParentPick.value),
      message: adminModalMessage.value.trim() || undefined,
      turnstileToken,
    })
    success.value = t('pendingAssignment.successTicketSent')
    adminContactModalOpen.value = false
    adminModalOrganisationId.value = ''
    adminModalDepartmentName.value = ''
    adminModalAffiliation.value = ''
    adminModalParentPick.value = null
    adminModalMessage.value = ''
    await loadMine()
  } catch (err: any) {
    adminModalError.value = err?.response?.data?.error || t('pendingAssignment.errorTicketSend')
    resetTurnstile()
  } finally {
    adminModalLoading.value = false
  }
}

async function submitAdminRequest() {
  const deptName = (manualDepartmentName.value || departmentQuery.value).trim()
  if (!deptName) {
    error.value = t('pendingAssignment.errorDeptName')
    return
  }
  if (!manualOrganisationId.value) {
    error.value = t('pendingAssignment.errorPickOrg')
    return
  }

  const turnstileToken = requireTurnstileToken()
  if (turnstileRequired.value && !turnstileToken) return

  loading.value = true
  error.value = null
  success.value = null
  try {
    await createAdminJoinRequest({
      requestedDepartmentName: deptName,
      requestedAffiliation: manualAffiliation.value.trim() || undefined,
      requestedOrganisationId: manualOrganisationId.value,
      ...parentPayload(manualParentPick.value),
      message: message.value.trim() || undefined,
      turnstileToken,
    })
    success.value = t('pendingAssignment.successAdminRequest')
    manualOrganisationId.value = ''
    manualDepartmentName.value = ''
    manualAffiliation.value = ''
    manualParentPick.value = null
    departmentQuery.value = ''
    departmentResults.value = []
  } catch (err: any) {
    error.value = err?.response?.data?.error || t('pendingAssignment.errorAdminRequest')
    resetTurnstile()
  } finally {
    loading.value = false
  }
}

async function loadOrganisations() {
  try {
    organisations.value = filterOrganisationsForUserPickers(await getOrganisations())
  } catch (e) {
    console.error(e)
    organisations.value = []
  }
}

onMounted(loadMine)
onMounted(loadOrganisations)
onMounted(() => {
  void initTurnstile()
})
onMounted(() => {
  const incomingCode = route.query.join_code
  if (typeof incomingCode === 'string' && incomingCode.trim().length > 0) {
    joinCode.value = incomingCode.trim().toUpperCase()
  }
  const incomingRole = route.query.invite_role
  if (typeof incomingRole === 'string' && incomingRole.trim().length > 0) {
    const normalized = incomingRole.trim().toLowerCase()
    inviteRole.value = ['mw', 'dc', 'l1', 'l2', 'l3', 'u'].includes(normalized) ? normalized : 'u'
  }

  const shouldAutoJoin = String(route.query.auto_join || '') === '1'
  if (shouldAutoJoin && joinCode.value && !autoJoinTriggered) {
    autoJoinTriggered = true
    submitRequest()
  }
})
watch(departmentQuery, (value) => {
  if (searchTimer) clearTimeout(searchTimer)
  const q = value.trim()
  if (q.length < 2) {
    departmentResults.value = []
    departmentLoading.value = false
    return
  }
  manualDepartmentName.value = q
  searchTimer = setTimeout(async () => {
    departmentLoading.value = true
    try {
      departmentResults.value = await searchJoinableDepartments(q)
    } catch (err) {
      console.error(err)
      departmentResults.value = []
    } finally {
      departmentLoading.value = false
    }
  }, 250)
})
onUnmounted(() => {
  if (searchTimer) clearTimeout(searchTimer)
  stopScanner()
})
</script>

<style scoped>
.pending-page { max-width: 980px; margin: 0 auto; }

.request-org-hint {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

.turnstile-box {
  min-height: 65px;
  margin: 12px 0;
}
.pending-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
.box { margin: 12px 0; }
.label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; }
/* Form group base uses shared ui/forms.css */
.form-label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }
/* Form select base uses shared ui/forms.css */
/* Form input/textarea base uses shared ui/forms.css */
.search-box { position: relative; }
.search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
/* Search input base uses shared ui/page-layout.css */
.clear-btn { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9ca3af; cursor: pointer; padding: 4px; border-radius: 4px; }
.clear-btn:hover { background: #f3f4f6; color: #6b7280; }
.actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
/* Buttons use shared ui/buttons.css */
.btn-link { background: transparent; border: none; color: #0b7eea; text-decoration: underline; padding-left: 0; cursor: pointer; }
.search-input-wrap { position: relative; }
.search-results { margin-top: 8px; border: 1px solid #e5e7eb; border-radius: 8px; max-height: 220px; overflow-y: auto; background: #fff; }
.search-results--above { position: absolute; left: 0; right: 0; bottom: calc(100% + 8px); z-index: 20; margin-top: 0; box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
.search-result-item {
  display: flex;
  flex-direction: column;
  width: 100%;
  text-align: left;
  border: none;
  background: #fff;
  padding: 12px 14px;
  cursor: pointer;
  border-bottom: 1px solid #f3f4f6;
  transition: all 0.15s ease;
}
.search-result-item:last-child { border-bottom: none; }
.search-result-item:hover {
  background: #f9fafb;
  box-shadow: inset 0 0 0 1px #d1d5db;
}
.result-name {
  font-weight: 700;
  color: #111827;
  font-size: 17px;
  line-height: 1.2;
}
.result-org {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}
.search-more-hint { margin: 0; padding: 8px 10px; font-size: 12px; color: #6b7280; background: #f8fafc; border-top: 1px solid #eef2f7; }
.hint { margin-top: 8px; color: #6b7280; font-size: 12px; }
.manual-request-box { margin-top: 8px; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px; }
.error { color: #b91c1c; margin-top: 12px; }
.success { color: #166534; margin-top: 12px; }

.pending-admin-intro {
  margin: 0 0 20px;
  font-size: 14px;
  line-height: 1.5;
  color: #6b7280;
}

.pending-admin-form :deep(.e-form-field) {
  margin-bottom: 20px;
}

.pending-admin-form :deep(.e-form-field:last-child) {
  margin-bottom: 0;
}

.pending-admin-form__picker {
  margin-bottom: 20px;
}

.pending-admin-alert {
  margin-top: 16px;
  font-size: 14px;
}

table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px; }
</style>
