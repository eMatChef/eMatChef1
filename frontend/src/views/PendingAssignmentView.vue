<template>
  <div class="pending-page">
    <div class="pending-card">
      <h1>Warten auf Abteilungszuordnung</h1>
      <p>
        Dein Konto ist bestaetigt, aber noch keiner Abteilung zugeordnet.
        Du kannst jetzt direkt eine Join-Anfrage senden.
      </p>

      <div class="box">
        <label class="label">Abteilung suchen (Name / Organisation)</label>
        <div class="search-box search-input-wrap">
          <svg class="search-icon" width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M9 17A8 8 0 1 0 9 1a8 8 0 0 0 0 16zM19 19l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <input
            v-model="departmentQuery"
            class="search-input"
            placeholder="z. B. Pfadi Musterstadt"
          />
          <button v-if="departmentQuery" @click="clearDepartmentSearch" class="clear-btn" type="button" aria-label="Suche leeren">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
              <path d="M15 5L5 15M5 5l10 10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </button>
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
              Weitere Treffer vorhanden - weiter tippen zum Verfeinern.
            </p>
          </div>
        </div>
        <div v-if="departmentLoading" class="hint">Suche laeuft...</div>
        <div v-if="showManualAdminRequest" class="manual-request-box">
          <p class="hint">Keine passende Abteilung gefunden. Du kannst einen Antrag an das Admin-Team senden. Wähle die Organisation, damit Org/SubOrg-Chefs deinen Antrag sehen.</p>
          <div class="form-group">
            <label class="form-label">Organisation *</label>
            <select v-model="manualOrganisationId" class="form-select">
              <option value="" disabled hidden>&nbsp;</option>
              <option v-for="org in organisationsFiltered" :key="org.id" :value="org.id">{{ org.name }}</option>
            </select>
          </div>
          <div class="form-row">
            <input
              v-model="manualDepartmentName"
              class="form-input"
              placeholder="Abteilungsname"
            />
            <input
              v-model="manualAffiliation"
              class="form-input"
              placeholder="Zugehoerigkeit (optional)"
            />
          </div>
          <div class="form-group">
            <label class="form-label">Hat es übergeordnete Abteilungen? (optional)</label>
            <input
              v-model="manualParentDepartment"
              class="form-input"
              placeholder="z. B. Pfadi Kanton XY"
            />
          </div>
          <button class="btn btn-secondary" type="button" :disabled="loading || !manualOrganisationId" @click="submitAdminRequest">
            Antrag beim Admin stellen
          </button>
        </div>
        <p v-if="selectedDepartment" class="success">
          Ausgewaehlte Abteilung: {{ selectedDepartment.name }} ({{ selectedDepartment.organisation_name }})
        </p>
      </div>

      <div class="box">
        <label class="label">Join-Code / Department-Code</label>
        <input v-model="joinCode" class="form-input" placeholder="z. B. abc123def456" />
      </div>

      <div class="actions">
        <button class="btn btn-primary" :disabled="loading" @click="submitRequest">
          Join-Request senden
        </button>
        <button class="btn btn-secondary" type="button" @click="toggleScanner">
          {{ scannerActive ? 'Scanner stoppen' : 'Abteilungs-QR scannen' }}
        </button>
        <button class="btn btn-link" type="button" @click="adminContactModalOpen = true">
          Admin kontaktieren
        </button>
      </div>

      <!-- Modal: Admin kontaktieren -->
      <div v-if="adminContactModalOpen" class="modal-overlay">
        <div class="modal-dialog pending-admin-modal-dialog">
          <div class="modal-header">
            <h2>Admin kontaktieren</h2>
            <button type="button" class="modal-close" @click="adminContactModalOpen = false">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
            </button>
          </div>
          <div class="modal-body">
            <p class="modal-intro">Gib deine gewünschte Abteilung und Organisation ein. Das Admin-Team erhält dein Ticket und ordnet dich zu.</p>
            <div class="form-group">
              <label class="form-label">Organisation *</label>
              <select v-model="adminModalOrganisationId" class="form-select">
                <option value="" disabled hidden>&nbsp;</option>
                <option v-for="org in organisationsFiltered" :key="org.id" :value="org.id">{{ org.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Abteilungsname *</label>
              <input
                v-model="adminModalDepartmentName"
                class="form-input"
                placeholder="z. B. Pfadi Musterstadt"
              />
            </div>
            <div class="form-group">
              <label class="form-label">Zugehörigkeit (optional)</label>
              <input
                v-model="adminModalAffiliation"
                class="form-input"
                placeholder="z. B. Stamm, Gruppe"
              />
            </div>
            <div class="form-group">
              <label class="form-label">Hat es übergeordnete Abteilungen? (optional)</label>
              <input
                v-model="adminModalParentDepartment"
                class="form-input"
                placeholder="z. B. Pfadi Kanton XY, Stamm Musterstadt"
              />
            </div>
            <div class="form-group">
              <label class="form-label">Nachricht an Admin (optional)</label>
              <textarea
                v-model="adminModalMessage"
                class="form-textarea"
                rows="3"
                placeholder="Kurze Notiz für den Admin"
              />
            </div>
            <div v-if="adminModalError" class="error-message">{{ adminModalError }}</div>
            <div class="modal-footer">
              <button type="button" class="btn-secondary" @click="adminContactModalOpen = false">Abbrechen</button>
              <button
                type="button"
                class="btn-primary"
                :disabled="adminModalLoading || !adminModalOrganisationId || !adminModalDepartmentName.trim()"
                @click="submitAdminContactModal"
              >
                {{ adminModalLoading ? 'Wird gesendet...' : 'Ticket senden' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <BarcodeScannerPanel
        v-if="scannerActive"
        :active="scannerActive"
        mode="qr"
        hint="Richte den QR-Code ins Kamerabild."
        @detected="onQrDetected"
        @error="onScannerError"
      />
      <p v-if="scannerError" class="error">{{ scannerError }}</p>

      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="success" class="success">{{ success }}</p>
    </div>

    <div class="pending-card">
      <h2>Meine offenen Anfragen</h2>
      <p v-if="requests.length === 0">Noch keine Join-Anfragen vorhanden.</p>
      <table v-else>
        <thead>
          <tr>
            <th>Department</th>
            <th>Status</th>
            <th>Erstellt</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in requests" :key="r.id">
            <td>{{ r.department_name }}</td>
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
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  createAdminJoinRequest,
  createJoinRequest,
  getMyJoinRequests,
  searchJoinableDepartments,
  type DepartmentSearchResult,
  type MyJoinRequest
} from '@/api/joinRequests'
import { getOrganisations, type Organisation } from '@/api/organisations'
import { filterOrganisationsForUserPickers } from '@/utils/organisationUserPicker'
import BarcodeScannerPanel from '@/components/common/BarcodeScannerPanel.vue'

const route = useRoute()
const authStore = useAuthStore()
const joinCode = ref('')
const inviteRole = ref('u')
const departmentQuery = ref('')
const departmentLoading = ref(false)
const departmentResults = ref<DepartmentSearchResult[]>([])
const selectedDepartment = ref<DepartmentSearchResult | null>(null)
const manualDepartmentName = ref('')
const manualAffiliation = ref('')
const manualParentDepartment = ref('')
const manualOrganisationId = ref('')
const organisations = ref<Organisation[]>([])
const message = ref('')
const adminContactModalOpen = ref(false)
const adminModalOrganisationId = ref('')
const adminModalDepartmentName = ref('')
const adminModalAffiliation = ref('')
const adminModalParentDepartment = ref('')
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
const showManualAdminRequest = computed(() => {
  return departmentQuery.value.trim().length >= 2 && !departmentLoading.value && departmentResults.value.length === 0
})

let searchTimer: ReturnType<typeof setTimeout> | null = null
let autoJoinTriggered = false

function statusLabel(status: string): string {
  if (status === 'approved') return 'Genehmigt'
  if (status === 'rejected') return 'Abgelehnt'
  return 'Offen'
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('de-CH')
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
    scannerError.value = 'QR-Code erkannt, aber kein gueltiger Join-Code gefunden.'
    return
  }
  scannerError.value = null
  joinCode.value = scanned.toUpperCase()
  selectedDepartment.value = null
  success.value = 'Join-Code aus QR uebernommen.'
  stopScanner()
}

function onScannerError(message: string) {
  scannerError.value = message
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

async function submitRequest() {
  if (!selectedDepartment.value && !joinCode.value.trim()) {
    error.value = 'Bitte Abteilung auswaehlen oder Join-Code eingeben'
    return
  }

  loading.value = true
  error.value = null
  success.value = null

  try {
    const created = await createJoinRequest({
      departmentId: selectedDepartment.value?.id,
      joinCode: selectedDepartment.value ? undefined : joinCode.value.trim(),
      message: message.value.trim() || undefined,
      requestedRole: inviteRole.value
    })
    if (created.auto_joined) {
      await authStore.loadDepartments()
      const deptId = created.department_id || authStore.activeDepartmentId
      if (deptId) {
        window.location.href = `/${deptId}`
        return
      }
      success.value = 'Einladung akzeptiert. Du wurdest direkt dem Department zugeordnet.'
    } else {
      success.value = 'Join-Request gesendet. Das Admin-Team sieht die Anfrage im Dashboard.'
    }
    joinCode.value = ''
    selectedDepartment.value = null
    departmentQuery.value = ''
    departmentResults.value = []
    message.value = ''
    await loadMine()
  } catch (err: any) {
    error.value = err?.response?.data?.error || 'Join-Request konnte nicht gesendet werden'
  } finally {
    loading.value = false
  }
}

async function submitAdminContactModal() {
  const deptName = adminModalDepartmentName.value.trim()
  if (!deptName) {
    adminModalError.value = 'Bitte Abteilungsname eingeben'
    return
  }
  if (!adminModalOrganisationId.value) {
    adminModalError.value = 'Bitte Organisation wählen'
    return
  }

  adminModalLoading.value = true
  adminModalError.value = null
  try {
    await createAdminJoinRequest({
      requestedDepartmentName: deptName,
      requestedAffiliation: adminModalAffiliation.value.trim() || undefined,
      requestedOrganisationId: adminModalOrganisationId.value,
      requestedParentDepartmentName: adminModalParentDepartment.value.trim() || undefined,
      message: adminModalMessage.value.trim() || undefined
    })
    success.value = 'Ticket gesendet. SA sieht alle Anträge, Org/SubOrg-Chefs sehen Anträge ihrer Organisation.'
    adminContactModalOpen.value = false
    adminModalOrganisationId.value = ''
    adminModalDepartmentName.value = ''
    adminModalAffiliation.value = ''
    adminModalParentDepartment.value = ''
    adminModalMessage.value = ''
    await loadMine()
  } catch (err: any) {
    adminModalError.value = err?.response?.data?.error || 'Ticket konnte nicht gesendet werden'
  } finally {
    adminModalLoading.value = false
  }
}

async function submitAdminRequest() {
  const deptName = (manualDepartmentName.value || departmentQuery.value).trim()
  if (!deptName) {
    error.value = 'Bitte Abteilungsname eingeben'
    return
  }
  if (!manualOrganisationId.value) {
    error.value = 'Bitte Organisation wählen'
    return
  }

  loading.value = true
  error.value = null
  success.value = null
  try {
    await createAdminJoinRequest({
      requestedDepartmentName: deptName,
      requestedAffiliation: manualAffiliation.value.trim() || undefined,
      requestedOrganisationId: manualOrganisationId.value,
      requestedParentDepartmentName: manualParentDepartment.value.trim() || undefined,
      message: message.value.trim() || undefined
    })
    success.value = 'Admin-Antrag gesendet. SA sieht alle Anträge, Org/SubOrg-Chefs sehen Anträge ihrer Organisation.'
    manualOrganisationId.value = ''
    manualDepartmentName.value = ''
    manualAffiliation.value = ''
    manualParentDepartment.value = ''
    departmentQuery.value = ''
    departmentResults.value = []
  } catch (err: any) {
    error.value = err?.response?.data?.error || 'Admin-Antrag konnte nicht gesendet werden'
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
/* Modal overlay/header/body/footer base uses shared ui/modals.css */
.pending-admin-modal-dialog {
  width: min(480px, calc(100vw - 48px));
  max-height: calc(100vh - 48px);
  padding: 0;
  overflow: hidden;
}

.modal-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
}
.modal-intro { font-size: 14px; color: #6b7280; margin: 0 0 20px; }
.error-message {
  background: #fee2e2;
  color: #dc2626;
  padding: 12px;
  border-radius: 6px;
  font-size: 14px;
  margin-bottom: 16px;
}
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px; }
</style>
