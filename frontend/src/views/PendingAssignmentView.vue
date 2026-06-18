<template>
  <div class="pending-page">
    <ECard class="pending-card" variant="elevated">
      <h1 class="pending-title">{{ t('pendingAssignment.title') }}</h1>
      <p class="pending-intro">
        {{ t('pendingAssignment.intro') }}
      </p>

      <div class="pending-section">
        <ESearchField
          v-model="departmentQuery"
          :label="t('pendingAssignment.searchPlaceholder')"
          class="pending-search"
          @clear="clearDepartmentSearch"
        >
          <div v-if="displayedDepartmentResults.length > 0" class="pending-search-results">
            <button
              v-for="d in displayedDepartmentResults"
              :key="d.id"
              type="button"
              class="pending-search-result"
              @click="selectDepartment(d)"
            >
              <span class="pending-search-result__name">{{ d.name }}</span>
              <span class="pending-search-result__org">{{ d.organisation_name }}</span>
            </button>
            <p v-if="departmentResults.length > 4" class="pending-search-more">
              {{ t('pendingAssignment.searchMoreHint') }}
            </p>
          </div>
        </ESearchField>

        <ELoadingState
          v-if="departmentLoading"
          variant="inline"
          :message="t('pendingAssignment.searchLoading')"
        />

        <div v-if="showManualAdminRequest" class="pending-manual">
          <p class="pending-hint">{{ t('pendingAssignment.manualHint') }}</p>

          <ESelect
            v-model="manualOrganisationId"
            :items="organisationSelectItems"
            :label="t('pendingAssignment.organisationRequired')"
            :disabled="loading"
          />

          <div class="pending-manual__row">
            <ETextField
              v-model="manualDepartmentName"
              :label="t('pendingAssignment.departmentNamePlaceholder')"
              :disabled="loading"
            />
            <ETextField
              v-model="manualAffiliation"
              :label="t('pendingAssignment.affiliationPlaceholder')"
              :disabled="loading"
            />
          </div>

          <ParentDepartmentPicker
            v-if="manualOrganisationId"
            :organisation-id="manualOrganisationId"
            :disabled="loading"
            @update:model-value="manualParentPick = $event"
          />

          <EButton
            variant="secondary"
            :disabled="loading || !manualOrganisationId"
            @click="submitAdminRequest"
          >
            {{ t('pendingAssignment.submitAdminRequest') }}
          </EButton>
        </div>

        <v-alert
          v-if="selectedDepartment"
          type="success"
          variant="tonal"
          density="compact"
          class="pending-alert"
        >
          {{
            t('pendingAssignment.selectedDepartment', {
              name: selectedDepartment.name,
              org: selectedDepartment.organisation_name,
            })
          }}
        </v-alert>
      </div>

      <div class="pending-section">
        <ETextField
          v-model="joinCode"
          :label="t('pendingAssignment.joinCodeLabel')"
          :placeholder="t('pendingAssignment.joinCodePlaceholder')"
          :disabled="loading"
        />
      </div>

      <div v-if="turnstileRequired" ref="turnstileContainerRef" class="pending-turnstile" />

      <div class="pending-actions">
        <EButton variant="primary" :disabled="loading" :loading="loading" @click="submitRequest">
          {{ t('pendingAssignment.sendJoinRequest') }}
        </EButton>
        <EButton variant="secondary" :disabled="loading" @click="toggleScanner">
          {{ scannerActive ? t('pendingAssignment.scannerStop') : t('pendingAssignment.scannerStart') }}
        </EButton>
        <EButton variant="text" :disabled="loading" @click="adminContactModalOpen = true">
          {{ t('pendingAssignment.contactAdmin') }}
        </EButton>
      </div>

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
      />

      <v-alert
        v-if="error"
        type="error"
        variant="tonal"
        density="compact"
        class="pending-alert"
      >
        {{ error }}
      </v-alert>

      <v-alert
        v-if="success"
        type="success"
        variant="tonal"
        density="compact"
        class="pending-alert"
      >
        {{ success }}
      </v-alert>
    </ECard>

    <ECard class="pending-card" variant="elevated">
      <h2 class="pending-subtitle">{{ t('pendingAssignment.myOpenRequests') }}</h2>

      <EEmptyState
        v-if="openRequests.length === 0"
        variant="generic"
        compact
        :heading-level="3"
        :description="t('pendingAssignment.noOpenRequests')"
      />

      <div v-else class="pending-requests-wrap">
        <v-table density="comfortable" class="pending-requests-table">
          <thead>
            <tr>
              <th>{{ t('pendingAssignment.colType') }}</th>
              <th>{{ t('pendingAssignment.colDepartment') }}</th>
              <th>{{ t('common.status') }}</th>
              <th>{{ t('pendingAssignment.colCreated') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in openRequests" :key="`${r.request_kind || 'join'}-${r.id}`">
              <td>{{ requestKindLabel(r) }}</td>
              <td>
                <span>{{ r.department_name }}</span>
                <span v-if="r.organisation_name" class="pending-request-org">({{ r.organisation_name }})</span>
                <span v-if="r.requested_parent_department_name" class="pending-request-org">
                  · {{ t('pendingAssignment.parentDept', { name: r.requested_parent_department_name }) }}
                </span>
              </td>
              <td>{{ statusLabel(r) }}</td>
              <td>{{ formatDate(r.created_at) }}</td>
            </tr>
          </tbody>
        </v-table>
      </div>
    </ECard>

    <ECard v-if="requestHistory.length > 0" class="pending-card" variant="elevated">
      <h2 class="pending-subtitle">{{ t('pendingAssignment.myRequestHistory') }}</h2>

      <div class="pending-requests-wrap">
        <v-table density="comfortable" class="pending-requests-table">
          <thead>
            <tr>
              <th>{{ t('pendingAssignment.colType') }}</th>
              <th>{{ t('pendingAssignment.colDepartment') }}</th>
              <th>{{ t('common.status') }}</th>
              <th>{{ t('pendingAssignment.colCreated') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in requestHistory" :key="`history-${r.request_kind || 'join'}-${r.id}`">
              <td>{{ requestKindLabel(r) }}</td>
              <td>
                <span>{{ r.department_name }}</span>
                <span v-if="r.organisation_name" class="pending-request-org">({{ r.organisation_name }})</span>
                <span v-if="r.requested_parent_department_name" class="pending-request-org">
                  · {{ t('pendingAssignment.parentDept', { name: r.requested_parent_department_name }) }}
                </span>
              </td>
              <td>{{ statusLabel(r) }}</td>
              <td>{{ formatDate(r.created_at) }}</td>
            </tr>
          </tbody>
        </v-table>
      </div>
    </ECard>
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
import { extractJoinCodeFromScan } from '@/utils/joinCodeFromScan'
import BarcodeScannerPanel from '@/components/common/BarcodeScannerPanel.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import {
  EButton,
  ECard,
  EDialog,
  ESearchField,
  ESelect,
  ETextField,
  ETextarea,
} from '@/components/form/base'

defineOptions({ name: 'PendingAssignmentView' })

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
const openRequests = computed(() => requests.value.filter((r) => r.status === 'pending'))
const requestHistory = computed(() => requests.value.filter((r) => r.status !== 'pending'))
const scannerActive = ref(false)
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

function statusLabel(request: MyJoinRequest): string {
  if (request.auto_joined) return t('pendingAssignment.statusAutoJoined')
  if (request.status === 'approved' || request.status === 'assigned') return t('pendingAssignment.statusApproved')
  if (request.status === 'rejected') return t('pendingAssignment.statusRejected')
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


function stopScanner() {
  scannerActive.value = false
}

function onQrDetected(payload: { text: string }) {
  const scanned = extractJoinCodeFromScan(payload.text)
  if (!scanned) {
    error.value = t('pendingAssignment.qrNoJoinCode')
    return
  }
  error.value = null
  joinCode.value = scanned.toUpperCase()
  selectedDepartment.value = null
  success.value = t('pendingAssignment.joinCodeFromQr')
  stopScanner()
}

function toggleScanner() {
  if (scannerActive.value) {
    stopScanner()
    return
  }
  error.value = null
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
        // Abteilungs-Code zuerst melden; Lieferanten-Fallback nur bei echtem Supplier-Code relevant.
        error.value =
          deptError || supplierErr?.response?.data?.error || t('pendingAssignment.errorSendFailed')
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
.pending-page {
  max-width: 980px;
  margin: 0 auto;
  padding: 16px;
}

.pending-card {
  padding: 20px;
  margin-bottom: 16px;
}

.pending-title {
  margin: 0 0 8px;
  font-size: 1.5rem;
  font-weight: 700;
  color: rgb(var(--v-theme-on-surface));
}

.pending-subtitle {
  margin: 0 0 16px;
  font-size: 1.125rem;
  font-weight: 600;
  color: rgb(var(--v-theme-on-surface));
}

.pending-intro {
  margin: 0 0 20px;
  color: rgba(var(--v-theme-on-surface), 0.7);
  line-height: 1.5;
}

.pending-section {
  margin-bottom: 20px;
}

.pending-section :deep(.e-form-field) {
  margin-bottom: 0;
}

.pending-search {
  position: relative;
}

.pending-search-results {
  position: absolute;
  left: 0;
  right: 0;
  bottom: calc(100% + 8px);
  z-index: 20;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  max-height: 220px;
  overflow-y: auto;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.pending-search-result {
  display: flex;
  flex-direction: column;
  width: 100%;
  text-align: left;
  border: none;
  background: rgb(var(--v-theme-surface));
  padding: 12px 14px;
  cursor: pointer;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: background-color 0.15s ease;
}

.pending-search-result:last-child {
  border-bottom: none;
}

.pending-search-result:hover {
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.pending-search-result__name {
  font-weight: 700;
  font-size: 1rem;
  line-height: 1.2;
}

.pending-search-result__org {
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), 0.6);
  margin-top: 2px;
}

.pending-search-more {
  margin: 0;
  padding: 8px 10px;
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), 0.6);
  background: rgba(var(--v-theme-on-surface), 0.03);
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.pending-hint {
  margin: 0 0 12px;
  font-size: 13px;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.pending-manual {
  margin-top: 12px;
  padding: 16px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.03);
}

.pending-manual :deep(.e-form-field) {
  margin-bottom: 16px;
}

.pending-manual__row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 16px;
}

@media (max-width: 600px) {
  .pending-manual__row {
    grid-template-columns: 1fr;
  }
}

.pending-turnstile {
  min-height: 65px;
  margin: 12px 0;
}

.pending-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 8px;
}

.pending-alert {
  margin-top: 16px;
}

.pending-admin-intro {
  margin: 0 0 20px;
  font-size: 14px;
  line-height: 1.5;
  color: rgba(var(--v-theme-on-surface), 0.6);
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
}

.pending-requests-wrap {
  overflow-x: auto;
}

.pending-requests-table {
  min-width: 640px;
}

.pending-request-org {
  display: block;
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), 0.6);
  margin-top: 2px;
}
</style>
