<template>
  <div class="activities-view">
    <!-- ═══ Detail-Ansicht (bei Doppelklick) ═══ -->
    <ActivitiesDetailView v-if="showDetail && selectedActivity" />

    <!-- ═══ Listen-Ansicht ═══ -->
    <template v-else>
      <!-- Header -->
      <div class="activities-header page-header header-content">
        <div class="header-left">
          <h1>Aktivitäten</h1>
          <span class="subtitle">Events, Vermietungen & Ausleihen verwalten</span>
        </div>
      </div>

      <!-- Stats Bar -->
      <div v-if="!isLoading && activities.length > 0" class="stats-bar">
        <div class="stat-item">
          <span class="stat-value">{{ activities.length }}</span>
          <span class="stat-label">Gesamt</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-draft">{{ activities.filter(a => a.status === 'draft').length }}</span>
          <span class="stat-label">Entwürfe</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-submitted">{{ activities.filter(a => a.status === 'submitted').length }}</span>
          <span class="stat-label">Eingereicht</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-approved">{{ activities.filter(a => ['approved', 'packing', 'packed'].includes(a.status)).length }}</span>
          <span class="stat-label">In Bearbeitung</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-issued">{{ activities.filter(a => a.status === 'issued').length }}</span>
          <span class="stat-label">Ausgegeben</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-completed">{{ activities.filter(a => a.status === 'completed').length }}</span>
          <span class="stat-label">Abgeschlossen</span>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="filter-bar">
        <div class="filter-tabs">
          <button 
            v-for="tab in tabs" 
            :key="tab.key"
            class="filter-tab"
            :class="{ active: activeTab === tab.key }"
            @click="activeTab = tab.key"
          >
            {{ tab.label }}
            <span v-if="tab.count !== undefined" class="tab-count">{{ tab.count }}</span>
          </button>
        </div>
        <div class="filter-actions">
          <div class="type-filter-chips">
            <button 
              v-for="tpl in typeFilterChips"
              :key="tpl.type"
              class="type-chip"
              :class="{ active: activeTypeFilter === tpl.type }"
              @click="activeTypeFilter = activeTypeFilter === tpl.type ? '' : tpl.type"
            >{{ tpl.label }}</button>
          </div>
          <div class="search-box">
            <GlobalSearchInput
              mode="inline"
              :department-id="departmentId"
              default-type="activity"
              v-model="searchQuery"
              placeholder="Suchen (material:, aktivität:, reparatur:)"
            />
          </div>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="isLoading" class="loading-state">
        <div class="spinner"></div>
        <p>Aktivitäten werden geladen...</p>
      </div>

      <!-- Activities Table -->
      <div v-else class="activities-table-wrapper">
        <table class="activities-table">
          <thead>
            <tr>
              <th class="col-status"></th>
              <th class="col-name" @click="toggleSort('name')">
                Name
                <span v-if="sortField === 'name'" class="sort-icon">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </th>
              <th class="col-type">Typ</th>
              <th class="col-customer">Gruppe / Kunde</th>
              <th class="col-period" @click="toggleSort('date')">
                Zeitraum
                <span v-if="sortField === 'date'" class="sort-icon">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </th>
              <th class="col-items">Material</th>
              <th class="col-price" @click="toggleSort('price')">
                Preis
                <span v-if="sortField === 'price'" class="sort-icon">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </th>
              <th class="col-progress">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredActivities.length === 0">
              <td colspan="8" class="empty-state">
                <div class="empty-content">
                  <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                  </svg>
                  <p>{{ searchQuery || activeTypeFilter ? 'Keine Treffer' : 'Noch keine Aktivitäten vorhanden' }}</p>
                </div>
              </td>
            </tr>
            <tr 
              v-for="activity in filteredActivities" 
              :key="activity.id"
              class="activity-row"
              :class="{ 'row-draft': activity.status === 'draft' }"
              @dblclick="openActivity(activity)"
              @click="selectActivity(activity)"
            >
              <td class="col-status">
                <span class="status-dot" :class="activity.status"></span>
              </td>
              <td class="col-name">
                <div class="activity-name">{{ activity.name }}</div>
                <div class="activity-no" v-if="activity.no">{{ activity.no }}</div>
                <div class="activity-share-hint" v-if="getActivityShareHint(activity)">{{ getActivityShareHint(activity) }}</div>
                <div class="activity-share-status" v-if="getActivityShareStatus(activity)">{{ getActivityShareStatus(activity) }}</div>
              </td>
              <td class="col-type">
                <span class="type-badge" :class="activity.type">{{ getTypeLabel(activity.type) }}</span>
              </td>
              <td class="col-customer">
                <span v-if="activity.groupName" class="customer-group">{{ activity.groupName }}</span>
                <span v-if="activity.customerName" class="customer-name">{{ activity.customerName }}</span>
                <span v-if="!activity.groupName && !activity.customerName" class="text-muted">–</span>
              </td>
              <td class="col-period">
                <span v-if="activity.usageStart" class="period-display">
                  <span class="period-date">{{ formatDateShort(activity.usageStart) }}</span>
                  <span v-if="activity.usageEnd && !isSameDay(activity.usageStart, activity.usageEnd)" class="period-separator">–</span>
                  <span v-if="activity.usageEnd && !isSameDay(activity.usageStart, activity.usageEnd)" class="period-date">{{ formatDateShort(activity.usageEnd) }}</span>
                  <span class="period-relative">{{ getRelativeDate(activity.usageStart) }}</span>
                </span>
                <span v-else class="text-muted">–</span>
              </td>
              <td class="col-items">
                <span v-if="activity.itemCount" class="items-badge">{{ activity.itemCount }}</span>
                <span v-else class="text-muted">0</span>
              </td>
              <td class="col-price">
                <span v-if="activity.totalPrice" class="price-display">CHF {{ activity.totalPrice.toFixed(2) }}</span>
                <span v-else class="text-muted">–</span>
              </td>
              <td class="col-progress">
                <span class="status-label" :class="activity.status">{{ getStatusLabel(activity.status) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="filteredActivities.length > 0" class="table-footer">
          <span>{{ filteredActivities.length }} von {{ activities.length }} Aktivitäten</span>
        </div>
      </div>
    </template>

    <div
      v-if="showCompletionBlockedModal"
      style="position: fixed; inset: 0; background: rgba(17,24,39,0.45); display: flex; align-items: center; justify-content: center; z-index: 2600;"
    >
      <div style="width: min(720px, 94vw); background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 18px 40px rgba(0,0,0,0.22);">
        <div style="padding: 14px 16px; border-bottom: 1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; gap:10px;">
          <div style="font-size: 18px; font-weight: 700; color: #111827;">Aktivität kann noch nicht abgeschlossen werden</div>
          <button class="btn btn-sm btn-secondary" @click="closeCompletionBlockedModal">Schließen</button>
        </div>
        <div style="padding: 14px 16px; font-size: 14px; color: #374151; display: grid; gap: 10px;">
          <div>Es gibt noch offene Fälle, die zuerst in der Werkstatt geklärt werden müssen.</div>
          <div v-if="completionBlockers">
            <strong>Offene Werkstatt-Tickets:</strong>
            {{ completionBlockers.open_workshop_tickets_count || 0 }}
            ·
            <strong>Offene Meldungen:</strong>
            {{ completionBlockers.open_issue_reports_count || 0 }}
          </div>
          <div
            v-if="completionBlockers?.open_workshop_tickets?.length"
            style="display:grid; gap:6px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:10px;"
          >
            <div style="font-weight:600; color:#111827;">Offene Werkstatt-Tickets (direkt öffnen):</div>
            <div style="display:flex; flex-wrap:wrap; gap:6px;">
              <button
                v-for="ticket in completionBlockers.open_workshop_tickets"
                :key="ticket.id"
                class="btn btn-sm btn-secondary"
                @click="openWorkshopFromCompletionBlocker(ticket.id)"
              >
                {{ ticket.title || ticket.id }}
              </button>
            </div>
          </div>
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button class="btn btn-sm btn-warning" @click="switchDetailTab('issues')">Reparaturen / Verluste öffnen</button>
            <button class="btn btn-sm btn-primary" @click="openWorkshopFromCompletionBlocker">Werkstatt öffnen</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineOptions({ name: 'ActivitiesView' })
import { ref, computed, watch, onMounted, provide, unref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/api/apiClient'
import type { Address } from '@/api/addresses'
import type { DepartmentSearchResult } from '@/api/joinRequests'
import { getWorkshopTickets, type WorkshopTicket } from '@/api/workshop'
import {
  createActivityPackContainer,
  createActivityPackContainerItem,
  deleteActivityPackContainerItem,
  getActivityPackContainerItems,
  getActivityPackContainers,
  type ActivityPackContainer,
  type ActivityPackContainerItem,
} from '@/api/activityContainers'
import GlobalSearchInput from '@/components/common/GlobalSearchInput.vue'
import ActivitiesDetailView from './components/ActivitiesDetailView.vue'
import { ACTIVITIES_DETAIL_INJECT } from './components/activitiesInjectKeys'
import { snapTimeHHMM, snapDatetimeLocalToStep } from '@/utils/timeQuarter'
import { useAuthStore } from '@/stores/auth'
import { usePageHeadStore } from '@/stores/pageHead'
import { useDetailTabsStore } from '@/stores/detailTabs'
import { syncDocumentHead } from '@/composables/usePageHead'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { usePrompt } from '@/composables/usePrompt'
import { createAvailabilityMaterialLookupFetcher } from '@/composables/useMaterialLookup'
import QRCode from 'qrcode'

const route = useRoute()
const router = useRouter()

const authStore = useAuthStore()
const pageHeadStore = usePageHeadStore()
const toast = useToast()
const confirm = useConfirm()
const prompt = usePrompt()
const departmentId = computed(() => route.params.departmentId as string)
const detailTabsStore = useDetailTabsStore()
const showCompletionBlockedModal = ref(false)
const completionBlockers = ref<any | null>(null)

// Rollen-Check: Wer darf Material bearbeiten?
// Department-Rollen: mw, dc. Globale Admins (sa/org/sub) aus profile.roles.
const DEPT_MW_ROLES = ['mw', 'dc']
const LEADER_ROLES = ['l1', 'l2', 'l3']
const hasGlobalAdminRole = computed(() =>
  authStore.userRoles.includes('ROLE_SUPERADMIN') ||
  authStore.userRoles.includes('ROLE_ORGANISATIONSCHEF') ||
  authStore.userRoles.includes('ROLE_SUBORGCHEF')
)
const canEditMaterial = computed(() => {
  const role = (authStore.currentDepartmentRole || '').toLowerCase()
  if (DEPT_MW_ROLES.includes(role) || hasGlobalAdminRole.value) return true
  // Leader dürfen Material im Entwurf bearbeiten
  if (LEADER_ROLES.includes(role) && selectedActivity.value?.status === 'draft') return true
  return false
})

// MW+ darf bis "issued" (Am Event) den Bestand über Modal anpassen
const MW_EDITABLE_STATUSES = ['submitted', 'approved', 'packing', 'packed', 'issued']
const canMwAdjustMaterial = computed(() => {
  const role = (authStore.currentDepartmentRole || '').toLowerCase()
  const status = selectedActivity.value?.status || ''
  const hasMwPrivilege = DEPT_MW_ROLES.includes(role) || hasGlobalAdminRole.value
  return hasMwPrivilege && MW_EDITABLE_STATUSES.includes(status)
})

// MW+ darf Material hinzufügen (Suchfeld) bis "issued"
const canMwAddMaterial = computed(() => {
  const role = (authStore.currentDepartmentRole || '').toLowerCase()
  const status = selectedActivity.value?.status || ''
  const hasMwPrivilege = DEPT_MW_ROLES.includes(role) || hasGlobalAdminRole.value
  if (hasMwPrivilege && status === 'draft') return true
  return hasMwPrivilege && MW_EDITABLE_STATUSES.includes(status)
})
const canUseDetailJsMaterialSource = computed(() => {
  const type = selectedActivity.value?.type || activityDetail.value?.type || ''
  return type === 'camp' || type === 'event'
})

/** Alle angenommenen Einladungen (eine oder mehrere andere Departments) */
const acceptedInvitedDepartmentIds = computed(() => {
  const raw = activityDetail.value?.invited_departments
  if (!Array.isArray(raw)) return []
  return raw
    .filter((entry: any) => entry?.id && entry?.status === 'accepted')
    .map((entry: any) => entry.id as string)
})
const canUseDetailInvitedMaterialSource = computed(() => acceptedInvitedDepartmentIds.value.length > 0)

// Doppelklick auf Material-Zeile → Modal öffnen (draft bis issued)
function onMaterialRowDblClick(item: any) {
  const status = selectedActivity.value?.status || ''
  if (status === 'draft' && canEditMaterial.value) {
    openAdjustModal(item)
  } else if (canMwAdjustMaterial.value) {
    openAdjustModal(item)
  }
}

// Bestand-Ändern Modal State
const showAdjustModal = ref(false)
const adjustItem = ref<any>(null)
const adjustNewQty = ref(0)
const adjustAvailable = ref(0)
const adjustAvailableLoading = ref(false)

// Computed: Set-Infos für das Modal
const adjustPackSize = computed(() => adjustItem.value?.packSize || null)
const adjustPackUnit = computed(() => adjustItem.value?.packUnit || null)
const adjustMaxAllowed = computed(() => (adjustItem.value?.quantity || 0) + adjustAvailable.value)
const adjustSetsAvailable = computed(() => {
  if (!adjustPackSize.value || adjustPackSize.value <= 1) return 0
  return Math.floor(adjustMaxAllowed.value / adjustPackSize.value)
})

async function openAdjustModal(item: any) {
  adjustItem.value = item
  adjustNewQty.value = item.quantity
  adjustAvailable.value = 0
  adjustAvailableLoading.value = true
  showAdjustModal.value = true

  // Verfügbarkeit für dieses Material im Zeitraum laden
  try {
    const startDate = activityDetail.value?.planning_start || activityDetail.value?.usage_start
    const endDate = activityDetail.value?.planning_end || activityDetail.value?.usage_end
    const params: any = {
      departmentId: departmentId.value,
      activityId: selectedActivity.value?.id,
      search: item.materialName,
      limit: 1,
      excludeActivityId: selectedActivity.value?.id,
    }
    if (startDate && endDate) {
      params.startDate = startDate
      params.endDate = endDate
    }
    if (canUseDetailJsMaterialSource.value) {
      params.source = 'all'
      params.includeGlobalJs = true
    } else {
      params.source = 'internal'
      params.internalScope = 'both'
    }
    const response = await apiClient.get('/api/materials/available-for-period', { params })
    const found = (response.data || []).find((m: any) => m.materialItemId === item.materialItemId)
    adjustAvailable.value = found ? found.availableForPeriod : 0
  } catch (err) {
    console.error('Fehler beim Laden der Verfügbarkeit:', err)
    adjustAvailable.value = 999 // Bei Fehler kein Limit erzwingen
  } finally {
    adjustAvailableLoading.value = false
  }
}

function closeAdjustModal() {
  showAdjustModal.value = false
  adjustItem.value = null
}

async function confirmAdjust() {
  if (!adjustItem.value || !selectedActivity.value) return

  const newQty = adjustNewQty.value
  if (newQty > adjustMaxAllowed.value && !adjustAvailableLoading.value) {
    toast.warning(`Nicht genug verfügbar. Maximal ${adjustMaxAllowed.value} Stück möglich.`)
    return
  }

  try {
    if (newQty === 0) {
      // Material entfernen
      const items = detailItems.value
        .filter(i => i.id !== adjustItem.value.id)
        .map(i => ({ material_item_id: i.materialItemId, quantity: i.quantity, priority: 'normal' }))
      await apiClient.put(`/api/activities/${selectedActivity.value.id}/items`, { items })
    } else {
      // Menge ändern
      const items = detailItems.value.map(i => ({
        material_item_id: i.materialItemId,
        quantity: i.id === adjustItem.value.id ? newQty : i.quantity,
        priority: 'normal',
      }))
      await apiClient.put(`/api/activities/${selectedActivity.value.id}/items`, { items })
    }
    await reloadDetailItems()
    closeAdjustModal()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

// State
const activeTab = ref('upcoming')
const activeTypeFilter = ref('')
const searchQuery = ref('')
const sortField = ref('date')
const sortDir = ref<'asc' | 'desc'>('asc')

// Detail-Ansicht State
const showDetail = ref(false)
const selectedActivity = ref<Activity | null>(null)
const activityDetail = ref<any>(null)
const activeDetailTab = ref('overview')
const detailItems = ref<any[]>([])
const isLoadingDetailItems = ref(false)
const activityHistory = ref<any[]>([])
const isLoadingHistory = ref(false)

watch(
  () => {
    if (!showDetail.value || !selectedActivity.value?.name) return ''
    return String(selectedActivity.value.name).trim()
  },
  (name) => {
    if (!name) {
      pageHeadStore.clearDynamic()
      syncDocumentHead(route)
      return
    }
    pageHeadStore.setDynamic(`${name} · eMatChef`, `${name} – Aktivität in eMatChef.`)
  },
  { immediate: true }
)

// Draft-Edit State
const isEditingDraft = ref(false)
const draftEditData = ref({
  name: '',
  usage_start: '',
  usage_end: '',
  planning_start: '',
  planning_end: '',
  notes: '',
  group_id: null as string | null,
})
/** Bearbeiten-Modus: Eingeladene inkl. Gruppenzuordnung (Kopie von activityDetail.invited_departments) */
const draftEditInvitedDepartments = ref<DepartmentSearchResult[]>([])
const draftEditInviteGroupsById = ref<Record<string, { id: string; name: string }[]>>({})
const showDateChangeWarning = ref(false)

const isDraftEditable = computed(() => {
  return selectedActivity.value?.status === 'draft'
})

function startEditDraft() {
  if (!activityDetail.value || !isDraftEditable.value) return
  draftEditData.value = {
    name: activityDetail.value.name || '',
    usage_start: activityDetail.value.usage_start ? toLocalDatetimeInput(activityDetail.value.usage_start) : '',
    usage_end: activityDetail.value.usage_end ? toLocalDatetimeInput(activityDetail.value.usage_end) : '',
    planning_start: activityDetail.value.planning_start ? toLocalDatetimeInput(activityDetail.value.planning_start) : '',
    planning_end: activityDetail.value.planning_end ? toLocalDatetimeInput(activityDetail.value.planning_end) : '',
    notes: activityDetail.value.notes || '',
    group_id: activityDetail.value.group_id || null,
  }
  const rawInv = activityDetail.value.invited_departments
  draftEditInvitedDepartments.value = Array.isArray(rawInv)
    ? rawInv.map((inv: Record<string, unknown>) => ({
        id: String(inv.id ?? ''),
        name: String(inv.name ?? ''),
        organisation_name: String(inv.organisation_name ?? ''),
        status: String(inv.status ?? 'pending'),
        group_id: (inv.group_id as string | null | undefined) ?? null,
        group_name: (inv.group_name as string | null | undefined) ?? null,
      }))
    : []
  draftEditInviteGroupsById.value = {}
  isEditingDraft.value = true
  showDateChangeWarning.value = false
  if (myGroups.value.length === 0) {
    loadMyGroups()
  }
  for (const inv of draftEditInvitedDepartments.value) {
    if (inv.id) {
      void loadDraftEditInviteGroups(inv.id)
    }
  }
}

async function loadDraftEditInviteGroups(deptId: string) {
  if (draftEditInviteGroupsById.value[deptId]?.length) return
  try {
    const res = await apiClient.get('/api/groups', { params: { department_id: deptId } })
    const raw = res.data || []
    draftEditInviteGroupsById.value = {
      ...draftEditInviteGroupsById.value,
      [deptId]: raw.map((g: { id: string; name: string }) => ({ id: g.id, name: g.name })),
    }
  } catch {
    draftEditInviteGroupsById.value = { ...draftEditInviteGroupsById.value, [deptId]: [] }
  }
}

function setDraftEditInviteGroup(deptId: string, groupIdRaw: string) {
  const gid = groupIdRaw || null
  const idx = draftEditInvitedDepartments.value.findIndex((d) => d.id === deptId)
  if (idx < 0) return
  const row = draftEditInvitedDepartments.value[idx]
  row.group_id = gid
  const list = draftEditInviteGroupsById.value[deptId] || []
  row.group_name = gid ? list.find((x) => x.id === gid)?.name || null : null
  draftEditInvitedDepartments.value = [...draftEditInvitedDepartments.value]
}

function cancelEditDraft() {
  isEditingDraft.value = false
  showDateChangeWarning.value = false
  draftEditInvitedDepartments.value = []
  draftEditInviteGroupsById.value = {}
}

function onDraftDateChange() {
  // Warnung anzeigen wenn Material vorhanden und Datum geändert wird
  if (detailItems.value.length > 0) {
    const origStart = activityDetail.value?.usage_start ? toLocalDatetimeInput(activityDetail.value.usage_start) : ''
    const origEnd = activityDetail.value?.usage_end ? toLocalDatetimeInput(activityDetail.value.usage_end) : ''
    if (draftEditData.value.usage_start !== origStart || draftEditData.value.usage_end !== origEnd) {
      showDateChangeWarning.value = true
    } else {
      showDateChangeWarning.value = false
    }
  }
}

async function removeAllMaterialsAndSave() {
  if (!selectedActivity.value) return
  const ok = await confirm.confirm({
    title: 'Alle Materialien entfernen?',
    message: 'Um das Datum zu ändern, müssen alle Materialien entfernt werden.',
    confirmText: 'Entfernen',
    cancelText: 'Abbrechen',
    variant: 'warning',
  })
  if (!ok) return
  try {
    await apiClient.put(`/api/activities/${selectedActivity.value.id}/items`, { items: [] })
    await reloadDetailItems()
    showDateChangeWarning.value = false
    // Jetzt speichern
    await saveDraftEdit()
  } catch (err: any) {
    toast.error('Fehler beim Entfernen der Materialien: ' + (err.response?.data?.error || err.message))
  }
}

async function saveDraftEdit() {
  if (!selectedActivity.value || !activityDetail.value) return

  // Wenn Datum geändert und Material vorhanden -> warnen
  if (showDateChangeWarning.value && detailItems.value.length > 0) {
    return // User muss erst Material löschen
  }

  try {
    const payload: Record<string, any> = {
      name: draftEditData.value.name,
      usage_start: draftEditData.value.usage_start ? new Date(draftEditData.value.usage_start).toISOString() : null,
      usage_end: draftEditData.value.usage_end ? new Date(draftEditData.value.usage_end).toISOString() : null,
      planning_start: draftEditData.value.planning_start ? new Date(draftEditData.value.planning_start).toISOString() : null,
      planning_end: draftEditData.value.planning_end ? new Date(draftEditData.value.planning_end).toISOString() : null,
      notes: draftEditData.value.notes || null,
      group_id: draftEditData.value.group_id || null,
    }

    if (draftEditInvitedDepartments.value.length > 0) {
      payload.invited_departments = draftEditInvitedDepartments.value.map((entry) => ({
        id: entry.id,
        name: entry.name,
        organisation_name: entry.organisation_name,
        group_id: entry.group_id ?? null,
      }))
    }

    const response = await apiClient.patch(`/api/activities/${selectedActivity.value.id}`, payload)
    activityDetail.value = response.data

    // Auch die Liste aktualisieren
    if (selectedActivity.value) {
      selectedActivity.value.name = response.data.name
      if (response.data.group_name) {
        selectedActivity.value.groupName = response.data.group_name
      }
    }

    isEditingDraft.value = false
    showDateChangeWarning.value = false
    await loadActivities()
  } catch (err: any) {
    toast.error('Fehler beim Speichern: ' + (err.response?.data?.error || err.message))
  }
}

function toLocalDatetimeInput(isoStr: string): string {
  if (!isoStr) return ''
  const d = new Date(isoStr)
  const tz = authStore.departmentTimezone || 'Europe/Zurich'
  // Formatiere in der konfigurierten Timezone für datetime-local Input
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: tz,
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', hour12: false,
  }).formatToParts(d)
  const get = (type: string) => parts.find(p => p.type === type)?.value || '00'
  return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`
}

// Detail Material-Suche State (für Materialchef+)
const detailMatSearch = ref('')
const detailMatSearchResults = ref<any[]>([])
const isDetailMatLoading = ref(false)
const showDetailMatDropdown = ref(false)
const detailMatActiveIndex = ref(-1)
const detailMaterialSource = ref<'internal' | 'js'>('internal')
/** Nur Nicht-J&S: eigenes | ein eingeladenes Dept. (single) | alle erlaubten Depots */
const detailInternalScope = ref<'own' | 'both' | 'single'>('own')
const detailSingleDepartmentId = ref<string | null>(null)
let detailMatSearchTimer: ReturnType<typeof setTimeout> | null = null

const detailMaterialLookupFetcher = createAvailabilityMaterialLookupFetcher(() => {
  if (!selectedActivity.value?.id) return null
  const startDate = activityDetail.value?.planning_start || activityDetail.value?.usage_start
  const endDate = activityDetail.value?.planning_end || activityDetail.value?.usage_end
  const src = canUseDetailJsMaterialSource.value ? detailMaterialSource.value : 'internal'
  const int = detailInternalScope.value
  let internalScopeParam: 'own' | 'invited' | 'both' | 'single' = 'both'
  let singleDepartmentId: string | undefined
  if (int === 'single' && detailSingleDepartmentId.value) {
    internalScopeParam = 'single'
    singleDepartmentId = detailSingleDepartmentId.value
  } else if (int === 'own' || int === 'both') {
    internalScopeParam = int
  }
  return {
    departmentId: departmentId.value,
    activityId: selectedActivity.value.id,
    excludeActivityId: selectedActivity.value.id,
    startDate: startDate || undefined,
    endDate: endDate || undefined,
    source: src,
    internalScope: src === 'internal' ? internalScopeParam : undefined,
    singleDepartmentId: src === 'internal' ? singleDepartmentId : undefined,
    includeGlobalJs: canUseDetailJsMaterialSource.value,
    limit: 20,
  }
})

// Workflow-State
const availableTransitions = ref<StatusTransition[]>([])
const packItems = ref<PackItem[]>([])
const isLoadingPackItems = ref(false)
const packContainers = ref<ActivityPackContainer[]>([])
const selectedPackContainerId = ref('')
const selectedPackContainerItems = ref<ActivityPackContainerItem[]>([])
const newPackContainerLabel = ref('')
const newPackContainerItemMaterialId = ref('')
const newPackContainerItemQty = ref(1)
const issueReports = ref<IssueReport[]>([])
const isLoadingIssues = ref(false)
const returnItems = ref<ReturnItem[]>([])
const isLoadingReturns = ref(false)
const workshopCostTickets = ref<WorkshopTicket[]>([])
const isLoadingWorkshopCosts = ref(false)

// QR-Code für Auftrag-Status (Link zur Aktivität)
const activityQrDataUrl = ref<string>('')
const activityQrUrl = computed(() => {
  if (!selectedActivity.value?.id || !departmentId.value) return ''
  return `${window.location.origin}/${departmentId.value}/activities/${selectedActivity.value.id}/packlist`
})
watch([selectedActivity, departmentId], async () => {
  const url = activityQrUrl.value
  if (!url) {
    activityQrDataUrl.value = ''
    return
  }
  try {
    activityQrDataUrl.value = await QRCode.toDataURL(url, { width: 160, margin: 1 })
  } catch {
    activityQrDataUrl.value = ''
  }
}, { immediate: true })

function printActivity() {
  window.print()
}

async function loadPackContainers() {
  if (!selectedActivity.value?.id) return
  try {
    packContainers.value = await getActivityPackContainers(selectedActivity.value.id)
    if (!packContainers.value.some((c) => c.id === selectedPackContainerId.value)) {
      selectedPackContainerId.value = packContainers.value[0]?.id || ''
    }
    await loadSelectedPackContainerItems()
  } catch (err) {
    console.error('Fehler beim Laden der Kisten-Instanzen:', err)
    packContainers.value = []
    selectedPackContainerItems.value = []
  }
}

async function loadSelectedPackContainerItems() {
  if (!selectedActivity.value?.id || !selectedPackContainerId.value) {
    selectedPackContainerItems.value = []
    return
  }
  selectedPackContainerItems.value = await getActivityPackContainerItems(
    selectedActivity.value.id,
    selectedPackContainerId.value
  ).catch(() => [])
}

async function createPackContainer() {
  const label = newPackContainerLabel.value.trim()
  if (!label || !selectedActivity.value?.id) return
  try {
    await createActivityPackContainer(selectedActivity.value.id, { label })
    newPackContainerLabel.value = ''
    await loadPackContainers()
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Kiste konnte nicht erstellt werden')
  }
}

async function addItemToPackContainer() {
  if (!selectedActivity.value?.id || !selectedPackContainerId.value || !newPackContainerItemMaterialId.value) return
  try {
    await createActivityPackContainerItem(selectedActivity.value.id, selectedPackContainerId.value, {
      material_item_id: newPackContainerItemMaterialId.value,
      quantity_packed: Math.max(1, newPackContainerItemQty.value || 1),
    })
    newPackContainerItemMaterialId.value = ''
    newPackContainerItemQty.value = 1
    await loadSelectedPackContainerItems()
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Container-Inhalt konnte nicht hinzugefügt werden')
  }
}

async function removeItemFromPackContainer(itemId: string) {
  if (!selectedActivity.value?.id || !selectedPackContainerId.value) return
  try {
    await deleteActivityPackContainerItem(selectedActivity.value.id, selectedPackContainerId.value, itemId)
    await loadSelectedPackContainerItems()
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Container-Inhalt konnte nicht entfernt werden')
  }
}

// Issue-Report Formular
const showIssueForm = ref(false)
const newIssue = ref({
  materialItemId: '',
  materialName: '',
  type: 'repair' as string,
  quantity: 1,
  description: '',
})
// Suchbare Material-Auswahl im Issue-Formular
const issueMatSearch = ref('')
const showIssueMatDropdown = ref(false)
const filteredDetailItems = computed(() => {
  if (detailMaterialSource.value === 'js') {
    return detailItems.value.filter((item: any) => item.isJsMaterial)
  }
  const ownId = departmentId.value
  const nonJs = detailItems.value.filter((item: any) => !item.isJsMaterial)
  if (detailInternalScope.value === 'own') {
    return nonJs.filter((item: any) => item.sourceDepartmentId === ownId)
  }
  if (detailInternalScope.value === 'single' && detailSingleDepartmentId.value) {
    return nonJs.filter((item: any) => item.sourceDepartmentId === detailSingleDepartmentId.value)
  }
  return nonJs
})
const issueMatFiltered = computed(() => {
  const q = issueMatSearch.value.toLowerCase().trim()
  if (!q) return detailItems.value
  return detailItems.value.filter((item: any) =>
    item.materialName?.toLowerCase().includes(q)
  )
})

function truncateDeptTabLabel(name: string, maxLen = 22): string {
  const t = (name || '').trim()
  if (!t.length) return '—'
  if (t.length <= maxLen) return t
  return `${t.slice(0, maxLen - 1)}…`
}

function setDetailMaterialInternalScope(scope: 'own' | 'both') {
  if (
    detailMaterialSource.value === 'internal' &&
    detailInternalScope.value === scope &&
    detailSingleDepartmentId.value === null
  ) {
    return
  }
  detailMaterialSource.value = 'internal'
  detailInternalScope.value = scope
  detailSingleDepartmentId.value = null
  detailMatSearchResults.value = []
  detailMatActiveIndex.value = -1
  if (detailMatSearch.value.trim().length >= 1) {
    searchDetailMaterials()
  }
}

function setDetailInvitedDepartmentTab(deptId: string) {
  if (!acceptedInvitedDepartmentIds.value.includes(deptId)) return
  if (
    detailMaterialSource.value === 'internal' &&
    detailInternalScope.value === 'single' &&
    detailSingleDepartmentId.value === deptId
  ) {
    return
  }
  detailMaterialSource.value = 'internal'
  detailInternalScope.value = 'single'
  detailSingleDepartmentId.value = deptId
  detailMatSearchResults.value = []
  detailMatActiveIndex.value = -1
  if (detailMatSearch.value.trim().length >= 1) {
    searchDetailMaterials()
  }
}

function setDetailMaterialSource(source: 'internal' | 'js') {
  if (source === 'js') {
    if (!canUseDetailJsMaterialSource.value) return
    if (detailMaterialSource.value === 'js') return
    detailMaterialSource.value = 'js'
    detailMatSearchResults.value = []
    detailMatActiveIndex.value = -1
    if (detailMatSearch.value.trim().length >= 1) {
      searchDetailMaterials()
    }
    return
  }
  setDetailMaterialInternalScope('own')
}

// Detail-Tabs dynamisch basierend auf Status
const detailTabs = computed(() => {
  const tabs = [
    { key: 'overview', label: 'Übersicht' },
    { key: 'material', label: 'Material' },
  ]
  
  const status = selectedActivity.value?.status
  if (!status) return tabs

  // Packliste: ab Status "packing" sichtbar
  if (['packing', 'packed', 'issued', 'returned', 'completed'].includes(status)) {
    tabs.push({ key: 'packlist', label: 'Auftrag Status' })
  }

  // Reparaturen/Verluste + Verbrauchsmaterial: ab Status "issued" sichtbar
  if (['issued', 'returned', 'completed'].includes(status)) {
    tabs.push({ key: 'issues', label: 'Reparaturen / Verluste' })
    tabs.push({ key: 'consumables', label: 'Verbrauchsmaterial' })
  }

  // Kosten: ab Status "packing" sichtbar (sobald Material zugeordnet)
  if (['packing', 'packed', 'issued', 'returned', 'completed'].includes(status)) {
    tabs.push({ key: 'costs', label: 'Kosten' })
  }

  // Rückgabe: ab Status "returned" sichtbar
  if (['returned', 'completed'].includes(status)) {
    tabs.push({ key: 'returns', label: 'Rückgabe' })
  }

  if (['camp', 'event'].includes(selectedActivity.value?.type || '')) {
    tabs.push({ key: 'invited_departments', label: 'Eingeladene Departments' })
  }

  tabs.push({ key: 'history', label: 'Verlauf' })
  return tabs
})

const invitedDepartmentsDetail = computed(() => {
  const raw = activityDetail.value?.invited_departments
  if (!Array.isArray(raw)) return []
  return raw.filter((entry: any) => entry && (entry.name || entry.organisation_name)).map((entry: any) => ({
    id: entry.id || '',
    name: entry.name || 'Unbekanntes Department',
    organisation_name: entry.organisation_name || '',
    status: entry.status || 'pending',
  }))
})

/** Nur angenommene Einladungen – ein Material-Reiter pro Department */
const acceptedInvitedDepartmentsForTabs = computed(() =>
  invitedDepartmentsDetail.value.filter((d) => d.id && d.status === 'accepted'),
)

const typeFilterChips = [
  { type: 'activity', label: 'Aktivität' },
  { type: 'camp', label: 'Lager' },
  { type: 'event', label: 'Event' },
  { type: 'external', label: 'Extern' },
]


// Gruppen-Auswahl State
interface MyGroup {
  id: string
  name: string
  parent_id: string | null
  level: number
  role: string | null        // 'leader', 'member', oder null
  selectable: boolean         // true = User darf wählen, false = ausgegraut
  is_direct_member: boolean
  member_count: number
  hasChildren?: boolean
}
const myGroups = ref<MyGroup[]>([])
const isLoadingGroups = ref(false)

async function loadMyGroups() {
  if (isLoadingGroups.value) return
  isLoadingGroups.value = true
  try {
    const response = await apiClient.get(`/api/departments/${departmentId.value}/my-groups`)
    const groups = response.data || []
    // hasChildren Flag berechnen
    const parentIds = new Set(groups.map((g: any) => g.parent_id).filter(Boolean))
    myGroups.value = groups.map((g: any) => ({
      id: g.id,
      name: g.name,
      parent_id: g.parent_id,
      level: g.level ?? 0,
      role: g.role ?? null,
      selectable: g.selectable ?? false,
      is_direct_member: g.is_direct_member ?? false,
      member_count: g.member_count ?? 0,
      hasChildren: parentIds.has(g.id) || groups.some((c: any) => c.parent_id === g.id),
    }))
  } catch (err) {
    console.error('Fehler beim Laden der Gruppen:', err)
    myGroups.value = []
  } finally {
    isLoadingGroups.value = false
  }
}

/** Klick ins Feld öffnet Kalender/Uhr (nicht nur das Icon). Chromium/Safari; Firefox ignoriert ohne showPicker. */
function tryShowNativePicker(e: MouseEvent) {
  const el = e.currentTarget as HTMLInputElement
  if (typeof el.showPicker !== 'function') return
  try {
    const r = el.showPicker() as Promise<void> | void
    if (r && typeof (r as Promise<void>).catch === 'function') {
      void (r as Promise<void>).catch(() => {})
    }
  } catch {
    /* unsupported */
  }
}

onMounted(() => {
  loadActivities()
})

interface Activity {
  id: string
  no?: string
  name: string
  departmentId?: string
  departmentName?: string
  type: 'activity' | 'camp' | 'event' | 'external'
  status: 'draft' | 'submitted' | 'approved' | 'packing' | 'packed' | 'issued' | 'returned' | 'completed' | 'cancelled'
  invitedDepartments?: Array<{ id?: string; name?: string; organisation_name?: string; status?: string }>
  customerName?: string
  groupName?: string
  usageStart?: string
  usageEnd?: string
  itemCount?: number
  totalPrice?: number
  createdAt: string
  updatedAt: string
}

// Workflow-bezogene Typen
interface StatusTransition {
  status: string
  label: string
  allowed: boolean
  reason?: string
}

interface PackItem {
  id: string
  materialItemId: string
  materialName: string
  categoryName: string | null
  categoryId: string | null
  packSize: number | null
  packUnit: string | null
  quantityOrdered: number
  quantityPacked: number
  quantityIssued: number
  quantityReturned: number
  conditionOut: string
  batchNumbers?: string
  notes?: string
  isFullyPacked: boolean
  isFullyIssued: boolean
  isFullyReturned: boolean
  packDifference: number
  issueDifference: number
  returnDifference: number
  isConsumable: boolean
  isJsMaterial?: boolean
  externalSource?: string | null
  packedAt?: string
}

interface IssueReport {
  id: string
  materialItemId?: string
  materialName?: string
  type: 'repair' | 'loss' | 'consumption' | 'damage'
  typeLabel: string
  quantity: number
  description?: string
  photoUrl?: string
  notes?: string
  resolved: boolean
  resolvedAt?: string
  reportedAt: string
  isJsMaterial?: boolean
  externalSource?: string | null
}

interface ReturnItem {
  id: string
  materialItemId: string
  materialName: string
  quantityPacked: number
  quantityReturned: number
  quantityDamaged: number
  quantityMissing: number
  quantityOk: number
  conditionIn: string
  notes?: string
  hasDifferences: boolean
  returnedAt?: string
  isJsMaterial?: boolean
  externalSource?: string | null
}

const activities = ref<Activity[]>([])
const isLoading = ref(false)

function mapActivityListItem(a: any): Activity {
  return {
    id: a.id,
    no: a.no ? `#${String(a.no).padStart(3, '0')}` : undefined,
    name: a.name,
    departmentId: a.department_id,
    departmentName: a.department_name,
    type: a.type,
    status: a.status,
    invitedDepartments: Array.isArray(a.invited_departments) ? a.invited_departments : [],
    customerName: a.customer_name,
    groupName: a.group_name,
    usageStart: a.usage_start,
    usageEnd: a.usage_end,
    itemCount: a.item_count ?? 0,
    totalPrice: a.total_price,
    createdAt: a.created_at,
    updatedAt: a.updated_at,
  }
}

function getActivityShareHint(activity: Activity): string | null {
  if (activity.departmentId && activity.departmentId !== departmentId.value) {
    return `Geteilt von: ${activity.departmentName || 'anderem Department'}`
  }
  const accepted = (activity.invitedDepartments || []).filter((entry) => entry?.status === 'accepted')
  if (accepted.length > 0) {
    const names = accepted
      .map((entry) => entry?.name)
      .filter((name): name is string => !!name)
      .slice(0, 2)
    if (names.length === 0) return 'Geteilt mit anderen Departments'
    const suffix = accepted.length > 2 ? ` +${accepted.length - 2}` : ''
    return `Geteilt mit: ${names.join(', ')}${suffix}`
  }
  return null
}

function getActivityShareStatus(activity: Activity): string | null {
  const invited = activity.invitedDepartments || []
  if (activity.departmentId && activity.departmentId !== departmentId.value) {
    const ownInvite = invited.find((entry) => entry?.id === departmentId.value)
    if (!ownInvite) return null
    if (ownInvite.status === 'accepted') return 'Status: angenommen'
    if (ownInvite.status === 'rejected') return 'Status: abgelehnt'
    return 'Status: ausstehend'
  }

  if (invited.length === 0) return null
  const accepted = invited.filter((entry) => entry?.status === 'accepted').length
  const pending = invited.filter((entry) => entry?.status === 'pending').length
  const rejected = invited.filter((entry) => entry?.status === 'rejected').length

  const parts: string[] = []
  if (accepted > 0) parts.push(`${accepted} angenommen`)
  if (pending > 0) parts.push(`${pending} ausstehend`)
  if (rejected > 0) parts.push(`${rejected} abgelehnt`)
  return parts.length > 0 ? `Freigabe: ${parts.join(' · ')}` : null
}

// === Aktivitäten aus der API laden ===
async function loadActivities() {
  isLoading.value = true
  try {
    const response = await apiClient.get('/api/activities', {
      params: {
        department_id: departmentId.value,
      }
    })
    activities.value = (response.data || []).map((a: any) => mapActivityListItem(a))
  } catch (err: any) {
    console.error('Fehler beim Laden der Aktivitäten:', err)
    const msg = err?.code === 'ECONNABORTED' ? 'Zeitüberschreitung – Backend antwortet nicht.' : (err?.response?.data?.error || err?.message)
    toast.error('Aktivitäten konnten nicht geladen werden: ' + msg)
  } finally {
    isLoading.value = false
  }
}

// Anstehend = Status nicht abgeschlossen/storniert UND Nutzungszeitraum noch nicht vorbei (usage_end >= heute)
function isUpcomingActivity(a: Activity): boolean {
  if (!['draft', 'submitted', 'approved', 'packing', 'packed', 'issued', 'returned'].includes(a.status)) return false
  if (!a.usageEnd) return true // Kein Enddatum = Draft oder noch offen
  const endDate = new Date(a.usageEnd)
  const todayStart = new Date()
  todayStart.setHours(0, 0, 0, 0)
  return endDate >= todayStart
}

// Vergangen = abgeschlossen ODER Nutzungszeitraum vorbei (usage_end < heute)
function isPastActivity(a: Activity): boolean {
  if (a.status === 'cancelled') return false
  if (a.status === 'completed') return true
  if (!a.usageEnd) return false
  const endDate = new Date(a.usageEnd)
  const todayStart = new Date()
  todayStart.setHours(0, 0, 0, 0)
  return endDate < todayStart
}

// Tabs
const tabs = computed(() => [
  { key: 'upcoming', label: 'Anstehend', count: activities.value.filter(isUpcomingActivity).length },
  { key: 'past', label: 'Vergangen', count: activities.value.filter(isPastActivity).length },
  { key: 'all', label: 'Alle', count: activities.value.length },
  { key: 'cancelled', label: 'Storniert', count: activities.value.filter(a => a.status === 'cancelled').length },
])

// Filtered + Sorted activities
const filteredActivities = computed(() => {
  let result = activities.value

  // Tab-Filter
  if (activeTab.value === 'upcoming') {
    result = result.filter(isUpcomingActivity)
  } else if (activeTab.value === 'past') {
    result = result.filter(isPastActivity)
  } else if (activeTab.value === 'cancelled') {
    result = result.filter(a => a.status === 'cancelled')
  }

  // Typ-Filter
  if (activeTypeFilter.value) {
    result = result.filter(a => a.type === activeTypeFilter.value)
  }

  // Text-Suche
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(a => 
      a.name.toLowerCase().includes(q) || 
      a.customerName?.toLowerCase().includes(q) ||
      a.groupName?.toLowerCase().includes(q) ||
      a.no?.toLowerCase().includes(q)
    )
  }

  // Sortierung
  result = [...result].sort((a, b) => {
    let cmp = 0
    // Vergangen: immer nach Datum absteigend (heute/neueste zuerst)
    if (activeTab.value === 'past') {
      const da = new Date(a.usageEnd || a.usageStart || 0).getTime()
      const db = new Date(b.usageEnd || b.usageStart || 0).getTime()
      return db - da // absteigend = neueste zuerst
    }
    if (sortField.value === 'name') {
      cmp = a.name.localeCompare(b.name, 'de')
    } else if (sortField.value === 'date') {
      const da = a.usageStart ? new Date(a.usageStart).getTime() : 0
      const db = b.usageStart ? new Date(b.usageStart).getTime() : 0
      cmp = da - db
    } else if (sortField.value === 'price') {
      cmp = (a.totalPrice || 0) - (b.totalPrice || 0)
    }
    return sortDir.value === 'desc' ? -cmp : cmp
  })

  return result
})

// Helpers
function getTypeLabel(type: string): string {
  const raw = unref(type as any)
  const key = typeof raw === 'string' ? raw : String(raw ?? '')
  const labels: Record<string, string> = {
    activity: 'Aktivität',
    camp: 'Lager',
    event: 'Event',
    external: 'Extern',
  }
  return labels[key] || key
}

function getStatusLabel(status: string): string {
  const raw = unref(status as any)
  const key = typeof raw === 'string' ? raw : String(raw ?? '')
  const labels: Record<string, string> = {
    draft: 'Entwurf',
    submitted: 'Eingereicht',
    approved: 'Bestätigt',
    packing: 'Wird gepackt',
    packed: 'Gepackt',
    issued: 'Ausgegeben',
    returned: 'Retour',
    completed: 'Abgeschlossen',
    cancelled: 'Storniert',
    // Legacy (Abwärtskompatibel)
    confirmed: 'Bestätigt',
    active: 'Aktiv',
  }
  return labels[key] || key
}

function getConditionLabel(condition: string): string {
  const labels: Record<string, string> = {
    ok: 'OK',
    leicht_beschaedigt: 'Leicht beschädigt',
    beschaedigt: 'Beschädigt',
    defekt: 'Defekt',
  }
  return labels[condition] || condition
}

function getMaterialSourceLabel(item: any): string {
  if (item?.sourceDepartmentName) {
    if (item.sourceDepartmentId && item.sourceDepartmentId === departmentId.value) {
      return 'Eigenes Department'
    }
    return item.sourceDepartmentName
  }
  if (item?.isJsMaterial) return 'J&S'
  if (item?.externalSource) return item.externalSource
  return 'Eigenes Department'
}

function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatDateTime(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const tz = authStore.departmentTimezone || 'Europe/Zurich'
  return d.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: tz }) + 
    ' ' + d.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit', timeZone: tz })
}

function formatDateTimeShort(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const tz = authStore.departmentTimezone || 'Europe/Zurich'
  return d.toLocaleDateString('de-CH', { day: '2-digit', month: 'short', timeZone: tz }) + 
    ' ' + d.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit', timeZone: tz })
}

// Mit Sekunden für Meldungen (damit mehrere zur gleichen Minute unterscheidbar sind)
function formatDateTimeWithSeconds(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const tz = authStore.departmentTimezone || 'Europe/Zurich'
  return d.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: tz }) + 
    ' ' + d.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: tz })
}

function selectActivity(activity: Activity) {
  selectedActivity.value = activity
}

// Bestimmt den initialen Tab basierend auf dem Activity-Status
const PACK_STATUSES = ['packing', 'packed', 'issued', 'returned']

function getInitialTab(status: string): string {
  if (PACK_STATUSES.includes(status)) return 'packlist'
  return 'overview'
}

async function openActivity(activity: Activity) {
  selectedActivity.value = activity
  activityDetail.value = null
  const initialTab = getInitialTab(activity.status)
  activeDetailTab.value = initialTab

  detailTabsStore.addOrUpdateTab({
    id: activity.id,
    type: 'activity',
    label: activity.name || activity.no || `Aktivität ${activity.id}`,
    departmentId: departmentId.value,
    path: `/${departmentId.value}/activities/${activity.id}`,
  })
  detailItems.value = []
  activityHistory.value = []
  packItems.value = []
  issueReports.value = []
  returnItems.value = []
  workshopCostTickets.value = []
  availableTransitions.value = []
  isEditingDraft.value = false
  showDateChangeWarning.value = false
  draftEditInvitedDepartments.value = []
  draftEditInviteGroupsById.value = {}
  showDetail.value = true

  // URL aktualisieren
  router.replace(`/${departmentId.value}/activities/${activity.id}/${initialTab}`)

  // Detail-Daten + Transitions parallel laden
  try {
    const [detailResponse] = await Promise.all([
      apiClient.get(`/api/activities/${activity.id}`),
      loadTransitions(),
    ])
    activityDetail.value = detailResponse.data
  } catch (err) {
    console.error('Fehler beim Laden der Detail-Daten:', err)
  }

  // Tab-Daten direkt laden (Watcher wird nicht getriggert wenn Tab gleich bleibt)
  if (initialTab === 'packlist') {
    activePackStage.value = autoPackStage.value
    await loadPackItems()
    initMoveQtyInputs()
  } else if (initialTab === 'material') {
    await reloadDetailItems()
  }
}

function closeDetail() {
  // Tab bleibt offen, Änderungen bleiben erhalten (keep-alive)
  showDetail.value = false
  selectedActivity.value = null
  activityDetail.value = null
  isEditingDraft.value = false
  showDateChangeWarning.value = false
  draftEditInvitedDepartments.value = []
  draftEditInviteGroupsById.value = {}
  router.replace(`/${departmentId.value}/activities`)
}

function switchDetailTab(tabKey: string) {
  activeDetailTab.value = tabKey
  // URL aktualisieren mit aktuellem Tab
  if (selectedActivity.value) {
    router.replace(`/${departmentId.value}/activities/${selectedActivity.value.id}/${tabKey}`)
  }
}

// Route-Params überwachen: Detail-Ansicht aus URL wiederherstellen
watch(
  () => route.params,
  async (params) => {
    const activityId = params.activityId as string | undefined
    const tab = params.tab as string | undefined

    if (activityId && !showDetail.value) {
      // Aktivität aus URL öffnen (Direktnavigation / Browser-Refresh)
      const activity = activities.value.find(a => a.id === activityId)
      if (activity) {
        await openActivity(activity)
        if (tab) {
          activeDetailTab.value = tab
        }
      } else {
        // Aktivität noch nicht geladen → versuche direkt aus API zu laden
        try {
          const response = await apiClient.get(`/api/activities/${activityId}`)
          if (response.data) {
            const act: Activity = {
              id: response.data.id,
              no: response.data.no,
              name: response.data.name,
              type: response.data.type,
              status: response.data.status,
              usageStart: response.data.usage_start,
              usageEnd: response.data.usage_end,
              groupName: response.data.group_name || null,
              itemCount: response.data.item_count || 0,
              createdAt: response.data.created_at,
              updatedAt: response.data.updated_at,
            }
            selectedActivity.value = act
            activityDetail.value = response.data
            activeDetailTab.value = tab || getInitialTab(act.status)
            detailItems.value = []
            activityHistory.value = []
            packItems.value = []
            issueReports.value = []
            returnItems.value = []
            workshopCostTickets.value = []
            availableTransitions.value = []
            showDetail.value = true

            detailTabsStore.addOrUpdateTab({
              id: act.id,
              type: 'activity',
              label: act.name || act.no || `Aktivität ${act.id}`,
              departmentId: departmentId.value,
              path: `/${departmentId.value}/activities/${act.id}`,
            })

            await loadTransitions()

            // Tab-Daten direkt laden
            const activeTab = activeDetailTab.value
            if (activeTab === 'packlist') {
              activePackStage.value = autoPackStage.value
              await loadPackItems()
              initMoveQtyInputs()
            } else if (activeTab === 'material') {
              await reloadDetailItems()
            }
          }
        } catch (err) {
          console.error('Aktivität aus URL nicht gefunden:', err)
          router.replace(`/${departmentId.value}/activities`)
        }
      }
    } else if (activityId && showDetail.value && tab && tab !== activeDetailTab.value) {
      // Nur Tab wechseln (z.B. Browser back/forward)
      activeDetailTab.value = tab
    } else if (!activityId && showDetail.value) {
      // URL zeigt Liste an, aber Detail ist offen → schliessen (Tab bleibt offen)
      showDetail.value = false
      selectedActivity.value = null
      activityDetail.value = null
    }
  },
  { immediate: true }
)

// Query ?q=: Header-Suche: Suchbegriff übernehmen
watch(
  () => route.query.q,
  (q) => {
    if (route.path.includes('/activities')) {
      searchQuery.value = (q as string) ?? ''
    }
  },
  { immediate: true }
)

// ═══════════════════════════════════════════════
// DETAIL: MATERIAL HINZUFÜGEN (MW+ Rollen)
// ═══════════════════════════════════════════════

function onDetailMatSearchInput() {
  if (detailMatSearchTimer) clearTimeout(detailMatSearchTimer)

  if (detailMatSearch.value.trim().length < 1) {
    detailMatSearchResults.value = []
    showDetailMatDropdown.value = false
    detailMatActiveIndex.value = -1
    return
  }

  showDetailMatDropdown.value = true
  detailMatActiveIndex.value = -1
  detailMatSearchTimer = setTimeout(() => {
    searchDetailMaterials()
  }, 300)
}

async function searchDetailMaterials() {
  if (!selectedActivity.value) return
  const query = detailMatSearch.value.trim()
  if (query.length < 1) return

  isDetailMatLoading.value = true
  try {
    detailMatSearchResults.value = await detailMaterialLookupFetcher(query)
    showDetailMatDropdown.value = true
    detailMatActiveIndex.value = detailMatSearchResults.value.length > 0 ? 0 : -1
  } catch (err) {
    console.error('Fehler beim Suchen der Materialien:', err)
    detailMatSearchResults.value = []
    detailMatActiveIndex.value = -1
  } finally {
    isDetailMatLoading.value = false
  }
}

function moveDetailMatActive(step: 1 | -1) {
  if (!detailMatSearchResults.value.length) return
  if (detailMatActiveIndex.value < 0) {
    detailMatActiveIndex.value = 0
    return
  }
  const len = detailMatSearchResults.value.length
  detailMatActiveIndex.value = (detailMatActiveIndex.value + step + len) % len
}

function acceptDetailMatSelection() {
  if (!detailMatSearchResults.value.length) return
  const index = detailMatActiveIndex.value >= 0 ? detailMatActiveIndex.value : 0
  const selected = detailMatSearchResults.value[index]
  if (!selected || selected.availableForPeriod <= 0) return
  addDetailMaterial(selected, 1)
}

function handleDetailLookupSelect(selected: any) {
  if (!selected || selected.availableForPeriod <= 0) return
  addDetailMaterial(selected, 1)
}

async function addDetailMaterial(mat: any, qty: number) {
  if (!selectedActivity.value || mat.availableForPeriod <= 0) return
  const actualQty = Math.min(qty, mat.availableForPeriod)

  try {
    await apiClient.post(`/api/activities/${selectedActivity.value.id}/items`, {
      material_item_id: mat.materialItemId,
      quantity: actualQty,
      priority: 'normal',
    })
    // Materialien neu laden
    await reloadDetailItems()
    // Dropdown Suche leeren
    detailMatSearch.value = ''
    detailMatSearchResults.value = []
    showDetailMatDropdown.value = false
    detailMatActiveIndex.value = -1
  } catch (err: any) {
    toast.error('Fehler beim Hinzufügen: ' + (err.response?.data?.error || err.message))
  }
}

async function changeDetailMaterialQty(item: any, delta: number) {
  if (!selectedActivity.value) return
  const newQty = item.quantity + delta
  if (newQty <= 0) {
    await removeDetailMaterial(item)
    return
  }

  // Bei Erhöhung: Verfügbarkeit prüfen
  if (delta > 0) {
    try {
      const startDate = activityDetail.value?.planning_start || activityDetail.value?.usage_start
      const endDate = activityDetail.value?.planning_end || activityDetail.value?.usage_end
      const params: any = {
        departmentId: departmentId.value,
        activityId: selectedActivity.value.id,
        search: item.materialName,
        limit: 1,
        excludeActivityId: selectedActivity.value.id,
      }
      if (startDate && endDate) {
        params.startDate = startDate
        params.endDate = endDate
      }
      const response = await apiClient.get('/api/materials/available-for-period', { params })
      const found = (response.data || []).find((m: any) => m.materialItemId === item.materialItemId)
      const available = found ? found.availableForPeriod : 0

      if (newQty > item.quantity + available) {
        toast.warning(`Nicht genug verfügbar! Aktuell verfügbar: ${available} Stk. (zusätzlich zur bestehenden Bestellung)`)
        return
      }
    } catch (err) {
      console.error('Verfügbarkeitsprüfung fehlgeschlagen:', err)
      // Bei Fehler trotzdem erlauben, Backend validiert ggf. nochmal
    }
  }

  try {
    // Sync via PUT: aktuelle Items mit geänderter Menge
    const items = detailItems.value.map(i => ({
      material_item_id: i.materialItemId,
      quantity: i.id === item.id ? newQty : i.quantity,
      priority: 'normal',
    }))
    await apiClient.put(`/api/activities/${selectedActivity.value.id}/items`, { items })
    await reloadDetailItems()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

async function removeDetailMaterial(item: any) {
  if (!selectedActivity.value) return
  const ok = await confirm.confirm({
    title: 'Material entfernen?',
    message: `"${item.materialName}" wirklich aus der Aktivität entfernen?`,
    confirmText: 'Entfernen',
    cancelText: 'Abbrechen',
    variant: 'warning',
  })
  if (!ok) return

  try {
    // Sync via PUT: alle Items ausser dem zu entfernenden
    const items = detailItems.value
      .filter(i => i.id !== item.id)
      .map(i => ({
        material_item_id: i.materialItemId,
        quantity: i.quantity,
        priority: 'normal',
      }))
    await apiClient.put(`/api/activities/${selectedActivity.value.id}/items`, { items })
    await reloadDetailItems()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

function mapDetailItemResponse(item: any) {
  return {
    id: item.id,
    materialItemId: item.material_item_id,
    materialName: item.material_name || item.materialName || 'Unbekannt',
    sourceDepartmentId: item.source_department_id || item.sourceDepartmentId || null,
    sourceDepartmentName: item.source_department_name || item.sourceDepartmentName || null,
    quantity: item.quantity,
    unitPrice: item.unit_price != null ? Number(item.unit_price) : null,
    lineTotal: item.line_total != null ? Number(item.line_total) : null,
    priceType: item.price_type || 'free',
    packSize: item.pack_size || null,
    packUnit: item.pack_unit || null,
    isConsumable: item.is_consumable || false,
    salePrice: item.sale_price || null,
    isJsMaterial: item.is_js_material || false,
    externalSource: item.external_source || null,
  }
}

async function reloadDetailItems() {
  if (!selectedActivity.value) return
  isLoadingDetailItems.value = true
  try {
    const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/items`)
    detailItems.value = (response.data || []).map((item: any) => mapDetailItemResponse(item))
  } catch (err) {
    console.error('Fehler beim Laden der Materialien:', err)
  } finally {
    isLoadingDetailItems.value = false
  }
}

async function loadWorkshopCostsForActivity() {
  if (!selectedActivity.value) return
  isLoadingWorkshopCosts.value = true
  try {
    workshopCostTickets.value = await getWorkshopTickets(departmentId.value, {
      activity_id: selectedActivity.value.id,
    })
  } catch (err) {
    console.error('Fehler beim Laden der Werkstattkosten:', err)
    workshopCostTickets.value = []
  } finally {
    isLoadingWorkshopCosts.value = false
  }
}

// Detail-Tab wechseln → Daten nachladen
watch(activeDetailTab, async (tab) => {
  if (!selectedActivity.value) return

  if (tab === 'material' && detailItems.value.length === 0) {
    isLoadingDetailItems.value = true
    try {
      const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/items`)
      detailItems.value = (response.data || []).map((item: any) => mapDetailItemResponse(item))
    } catch (err) {
      console.error('Fehler beim Laden der Materialien:', err)
    } finally {
      isLoadingDetailItems.value = false
    }
  }

  if (tab === 'history' && activityHistory.value.length === 0) {
    isLoadingHistory.value = true
    try {
      const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/history`)
      activityHistory.value = (response.data || []).map((h: any) => ({
        id: h.id,
        action: h.action,
        changes: h.changes,
        createdAt: h.created_at,
        userId: h.user_id,
      }))
    } catch (err) {
      console.error('Fehler beim Laden des Verlaufs:', err)
    } finally {
      isLoadingHistory.value = false
    }
  }

  // Packliste laden + Stufe auto-setzen
  if (tab === 'packlist') {
    activePackStage.value = autoPackStage.value
    if (packItems.value.length === 0) {
      await loadPackItems()
    }
    initMoveQtyInputs()
  }

  // Reparaturen / Verluste laden
  if (tab === 'issues') {
    if (issueReports.value.length === 0) await loadIssues()
    await loadWorkshopCostsForActivity()
  }

  // Verbrauchsmaterial laden (Issues + Material-Items benötigt)
  if (tab === 'consumables') {
    if (issueReports.value.length === 0) await loadIssues()
    if (detailItems.value.length === 0) {
      const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/items`)
      detailItems.value = (response.data || []).map((item: any) => mapDetailItemResponse(item))
    }
    // Qty-Inputs initialisieren
    for (const ci of consumableItems.value) {
      if (!consumableQtyInputs.value[ci.materialItemId]) {
        consumableQtyInputs.value[ci.materialItemId] = 1
      }
    }
  }

  // Kosten laden (Material-Items + Issues benötigt)
  if (tab === 'costs') {
    if (detailItems.value.length === 0) await reloadDetailItems()
    if (issueReports.value.length === 0) await loadIssues()
    if (workshopCostTickets.value.length === 0) await loadWorkshopCostsForActivity()
  }

  // Rückgabe laden
  if (tab === 'returns' && returnItems.value.length === 0) {
    await loadReturnItems()
  }
})

// ═══════════════════════════════════════════════
// STATUS-ÄNDERUNGEN & WORKFLOW
// ═══════════════════════════════════════════════

// Transitions für den aktuellen User laden
async function loadTransitions() {
  if (!selectedActivity.value) return
  try {
    const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/transitions`)
    const rawTransitions = response.data?.transitions
    const list = Array.isArray(rawTransitions) ? rawTransitions : []
    availableTransitions.value = list.map((t: any) => ({
      status: t.status,
      label: t.label,
      allowed: t.allowed,
      reason: t.reason,
    }))
  } catch (err) {
    console.error('Fehler beim Laden der Transitions:', err)
    availableTransitions.value = []
  }
}

async function changeActivityStatus(newStatus: string, comment?: string) {
  if (!selectedActivity.value) return
  try {
    const payload: any = { status: newStatus }
    if (comment) payload.comment = comment

    await apiClient.patch(`/api/activities/${selectedActivity.value.id}/status`, payload)
    selectedActivity.value.status = newStatus as Activity['status']
    
    // Transitions + Liste neu laden
    await Promise.all([loadActivities(), loadTransitions()])

    // Nach Wechsel zu packing/packed/issued/returned → automatisch Packliste öffnen + Stufe weiterschalten
    if (PACK_STATUSES.includes(newStatus)) {
      switchDetailTab('packlist')
      packItems.value = [] // Reset damit sie neu geladen werden
      await loadPackItems()
      // Passenden Stufen-Tab aktivieren
      activePackStage.value = autoPackStage.value
      initMoveQtyInputs()
    }
  } catch (err: any) {
    const apiError = err.response?.data
    if (apiError?.code === 'activity_completion_blocked') {
      completionBlockers.value = apiError.blockers || null
      showCompletionBlockedModal.value = true
      toast.warning(apiError.error || 'Aktivität kann noch nicht abgeschlossen werden')
      return
    }
    toast.error('Fehler: ' + (apiError?.error || err.message))
  }
}

function closeCompletionBlockedModal() {
  showCompletionBlockedModal.value = false
}

function openWorkshopFromCompletionBlocker(ticketId?: string) {
  closeCompletionBlockedModal()
  if (ticketId) {
    router.push({ path: `/${departmentId.value}/workshop`, query: { ticket: ticketId } })
    return
  }
  router.push(`/${departmentId.value}/workshop`)
}

// Prüft ob ein Zielstatus ein Pack-Workflow-Schritt ist (Aktion gehört in den Auftrag-Status-Tab)
function isPackWorkflowTarget(targetStatus: string): boolean {
  return ['packed', 'issued', 'returned'].includes(targetStatus)
}

// Navigiert zum Auftrag-Status-Tab und setzt den passenden Stufen-Tab
function navigateToPackTab(targetStatus: string) {
  switchDetailTab('packlist')
  // Passende Stufe aktivieren
  if (targetStatus === 'packed') {
    activePackStage.value = 'confirmed_packed'
  } else if (targetStatus === 'issued') {
    activePackStage.value = 'packed_issued'
  } else if (targetStatus === 'returned') {
    activePackStage.value = 'issued_returned'
  }
  initMoveQtyInputs()
}

async function handleTransition(targetStatus: string) {
  // Spezialfall: Zurückweisung (approved → submitted)
  if (selectedActivity.value?.status === 'approved' && targetStatus === 'submitted') {
    const comment = await prompt.prompt({
      title: 'Zurückweisen',
      message: 'Bitte gib einen Grund für die Zurückweisung an. Die Bestellung geht zurück an den Gruppenleiter.',
      placeholder: 'z.B. Material nicht verfügbar, Termin passt nicht...',
      confirmText: 'Zurückweisen',
      cancelText: 'Abbrechen',
      required: true,
    })
    if (comment === null) return // abgebrochen
    changeActivityStatus(targetStatus, comment)
    return
  }

  // Bestätigungs-Dialoge für wichtige Übergänge
  const confirmConfigs: Record<string, { title: string; message: string; variant?: 'info' | 'warning' | 'danger' }> = {
    submitted: {
      title: 'Bestellung einreichen?',
      message: 'Die Bestellung wird an den Materialwart gesendet.',
      variant: 'info',
    },
    issued: {
      title: 'Material als ausgegeben markieren?',
      message: 'Ab hier können Meldungen (Schaden, Reparatur) erstellt werden.',
      variant: 'warning',
    },
    completed: {
      title: 'Aktivität abschliessen?',
      message: 'Bestandsänderungen werden verbucht.',
      variant: 'warning',
    },
  }

  const cfg = confirmConfigs[targetStatus]
  if (cfg) {
    const ok = await confirm.confirm({
      ...cfg,
      confirmText: targetStatus === 'submitted' ? 'Einreichen' : targetStatus === 'completed' ? 'Abschliessen' : 'Bestätigen',
      cancelText: 'Abbrechen',
    })
    if (!ok) return
  }

  changeActivityStatus(targetStatus)
}

async function cancelActivity() {
  const ok = await confirm.confirm({
    title: 'Aktivität stornieren?',
    message: 'Die Aktivität wird endgültig storniert.',
    confirmText: 'Stornieren',
    cancelText: 'Abbrechen',
    variant: 'danger',
  })
  if (ok) {
    changeActivityStatus('cancelled')
  }
}

function getTransitionBtnClass(status: string): string {
  const classes: Record<string, string> = {
    submitted: 'btn-primary',
    approved: 'btn-success',
    packing: 'btn-info',
    packed: 'btn-info',
    issued: 'btn-warning',
    returned: 'btn-secondary',
    completed: 'btn-success',
  }
  return classes[status] || 'btn-secondary'
}

function getTransitionIcon(status: string): string {
  const icons: Record<string, string> = {
    submitted: '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    approved: '<polyline points="20 6 9 17 4 12"/>',
    packing: '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>',
    packed: '<polyline points="20 6 9 17 4 12"/>',
    issued: '<polygon points="5 3 19 12 5 21 5 3"/>',
    returned: '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
    completed: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
  }
  return icons[status] || ''
}

// ═══════════════════════════════════════════════
// PACKLISTE
// ═══════════════════════════════════════════════

async function loadPackItems() {
  if (!selectedActivity.value) return
  isLoadingPackItems.value = true
  try {
    const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/pack-items`)
    packItems.value = (response.data || []).map((pi: any) => ({
      id: pi.id,
      materialItemId: pi.material_item_id,
      materialName: pi.material_name,
      categoryName: pi.category_name || null,
      categoryId: pi.category_id || null,
      packSize: pi.pack_size || null,
      packUnit: pi.pack_unit || null,
      quantityOrdered: pi.quantity_ordered,
      quantityPacked: pi.quantity_packed,
      quantityIssued: pi.quantity_issued ?? 0,
      quantityReturned: pi.quantity_returned ?? 0,
      conditionOut: pi.condition_out,
      batchNumbers: pi.batch_numbers,
      notes: pi.notes,
      isFullyPacked: pi.is_fully_packed,
      isFullyIssued: pi.is_fully_issued ?? false,
      isFullyReturned: pi.is_fully_returned ?? false,
      packDifference: pi.pack_difference,
      issueDifference: pi.issue_difference ?? 0,
      returnDifference: pi.return_difference ?? 0,
      isConsumable: pi.is_consumable || false,
      isJsMaterial: pi.is_js_material || false,
      externalSource: pi.external_source || null,
      packedAt: pi.packed_at,
    }))
    
    // Falls leer und Status packing → automatisch initialisieren
    if (packItems.value.length === 0 && selectedActivity.value.status === 'packing') {
      await initPackItems()
    }
    await loadPackContainers()
  } catch (err) {
    console.error('Fehler beim Laden der Packliste:', err)
  } finally {
    isLoadingPackItems.value = false
    initMoveQtyInputs()
  }
}

async function initPackItems() {
  if (!selectedActivity.value) return
  try {
    await apiClient.post(`/api/activities/${selectedActivity.value.id}/pack-items/init`)
    await loadPackItems()
  } catch (err) {
    console.error('Fehler beim Initialisieren der Packliste:', err)
  }
}

async function updatePackItem(packItem: PackItem, field: string, value: any) {
  if (!selectedActivity.value) return
  try {
    await apiClient.patch(`/api/activities/${selectedActivity.value.id}/pack-items/${packItem.id}`, {
      [field]: value,
    })
    // Lokal aktualisieren
    const idx = packItems.value.findIndex(p => p.id === packItem.id)
    if (idx !== -1) {
      (packItems.value[idx] as any)[field === 'quantity_packed' ? 'quantityPacked' : field === 'condition_out' ? 'conditionOut' : field] = value
      packItems.value[idx].isFullyPacked = packItems.value[idx].quantityPacked >= packItems.value[idx].quantityOrdered
      packItems.value[idx].packDifference = packItems.value[idx].quantityOrdered - packItems.value[idx].quantityPacked
    }
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

// ── Packliste: 4-Stufen Workflow Board ──
// Stufen: Bestätigt → Gepackt → Am Event → Retour
// Immer 2 Panels sichtbar, Navigation per Tab

type PackStage = 'confirmed_packed' | 'packed_issued' | 'issued_returned'

const PACK_STAGES: { key: PackStage; leftLabel: string; rightLabel: string; leftField: string; rightField: string }[] = [
  { key: 'confirmed_packed', leftLabel: 'Bestätigt', rightLabel: 'Gepackt', leftField: 'pack', rightField: 'packed' },
  { key: 'packed_issued', leftLabel: 'Gepackt', rightLabel: 'Am Event', leftField: 'issue', rightField: 'issued' },
  { key: 'issued_returned', leftLabel: 'Am Event', rightLabel: 'Retour', leftField: 'return', rightField: 'returned' },
]

const activePackStage = ref<PackStage>('confirmed_packed')

// Auto-Auswahl der Stufe basierend auf Activity-Status
const autoPackStage = computed<PackStage>(() => {
  const status = selectedActivity.value?.status
  if (status === 'packed') return 'packed_issued'       // Gepackt → zeige "Gepackt | Am Event"
  if (status === 'issued') return 'issued_returned'     // Am Event → zeige "Am Event | Retour"
  if (status === 'returned') return 'issued_returned'
  return 'confirmed_packed' // packing
})

// Aktives Stufen-Config
const activeStageConfig = computed(() => PACK_STAGES.find(s => s.key === activePackStage.value) || PACK_STAGES[0])

// Pro Stufe: Items für linkes und rechtes Panel berechnen
function getStageLeftQty(item: PackItem): number {
  switch (activePackStage.value) {
    case 'confirmed_packed': return item.quantityOrdered - item.quantityPacked
    case 'packed_issued': return item.quantityPacked - item.quantityIssued
    case 'issued_returned': return item.quantityIssued - item.quantityReturned
    default: return 0
  }
}

function getStageRightQty(item: PackItem): number {
  switch (activePackStage.value) {
    case 'confirmed_packed': return item.quantityPacked
    case 'packed_issued': return item.quantityIssued
    case 'issued_returned': return item.quantityReturned
    default: return 0
  }
}

function getStageTotalQty(item: PackItem): number {
  switch (activePackStage.value) {
    case 'confirmed_packed': return item.quantityOrdered
    case 'packed_issued': return item.quantityPacked
    case 'issued_returned': return item.quantityIssued
    default: return 0
  }
}

// Items mit Restmenge > 0 auf der linken Seite
const stageLeftItems = computed(() => packItems.value.filter(p => getStageLeftQty(p) > 0))
// Items die bereits verschoben wurden (rechte Seite)
const stageRightItems = computed(() => packItems.value.filter(p => getStageRightQty(p) > 0))

// Fortschritt der aktuellen Stufe
const stageProgress = computed(() => {
  const total = packItems.value.reduce((sum, p) => sum + getStageTotalQty(p), 0)
  const done = packItems.value.reduce((sum, p) => sum + getStageRightQty(p), 0)
  return total > 0 ? Math.round((done / total) * 100) : 0
})
const jsWorkflowSummary = computed(() => {
  const jsPackItems = packItems.value.filter(item => item.isJsMaterial)
  const losses = issueReports.value
    .filter(issue => issue.isJsMaterial && issue.type === 'loss')
    .reduce((sum, issue) => sum + (issue.quantity || 0), 0)

  return {
    items: jsPackItems.length,
    received: jsPackItems.reduce((sum, item) => sum + (item.quantityIssued || 0), 0),
    returned: jsPackItems.reduce((sum, item) => sum + (item.quantityReturned || 0), 0),
    losses,
  }
})

// Der passende Workflow-Transition-Button für die aktuelle Stufe (bei 100%)
const nextWorkflowTransition = computed(() => {
  // Welcher Zielstatus passt zur aktuellen Stufe?
  const stageToStatus: Record<string, string> = {
    'confirmed_packed': 'packed',   // Bestätigt→Gepackt fertig → "Gepackt markieren"
    'packed_issued': 'issued',      // Gepackt→AmEvent fertig → "Ausgeben"
    'issued_returned': 'returned',  // AmEvent→Retour fertig → "Retour erfassen" (wird vom completed abgelöst)
  }
  const targetStatus = stageToStatus[activePackStage.value]
  if (!targetStatus) return null
  
  // Prüfen ob diese Transition verfügbar und erlaubt ist
  const transition = availableTransitions.value.find(t => t.status === targetStatus && t.allowed)
  return transition || null
})

// Workflow-Button in der Fortschrittsleiste: bei < 100% mit Warnung, danach nächste Stufe
async function handleWorkflowTransition() {
  if (!nextWorkflowTransition.value) return
  
  if (stageProgress.value < 100) {
    const remaining = stageLeftItems.value.length
    const ok = await confirm.confirm({
      title: `Achtung: ${stageProgress.value}% abgeschlossen!`,
      message: `${remaining} Position(en) wurden noch nicht verschoben. Trotzdem als "${nextWorkflowTransition.value.label}" fortfahren?`,
      confirmText: 'Fortfahren',
      cancelText: 'Abbrechen',
      variant: 'warning',
    })
    if (!ok) return
  }
  
  // Status wechseln → changeActivityStatus kümmert sich um Stage-Wechsel + Tab
  await changeActivityStatus(nextWorkflowTransition.value.status)
}

// Gruppierung nach Kategorie
interface PackGroup {
  categoryName: string
  collapsed: boolean
  items: PackItem[]
}

const collapsedGroups = ref<Record<string, boolean>>({})

function groupPackItems(items: PackItem[]): PackGroup[] {
  const groups: Record<string, PackItem[]> = {}
  for (const item of items) {
    const cat = item.categoryName || 'Ohne Kategorie'
    if (!groups[cat]) groups[cat] = []
    groups[cat].push(item)
  }
  return Object.entries(groups)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([name, items]) => ({
      categoryName: name,
      collapsed: !!collapsedGroups.value[name],
      items,
    }))
}

const groupsLeft = computed(() => groupPackItems(stageLeftItems.value))
const groupsRight = computed(() => groupPackItems(stageRightItems.value))

function toggleGroup(groupName: string) {
  collapsedGroups.value[groupName] = !collapsedGroups.value[groupName]
}

// ── Move-Logik: Item zur nächsten Stufe verschieben ──

const moveQtyInputs = ref<Record<string, number>>({})
const moveBackQtyInputs = ref<Record<string, number>>({})

// Setzt die Mengen-Inputs für alle Items auf den aktuellen Restwert
function initMoveQtyInputs() {
  for (const item of packItems.value) {
    const leftQty = getStageLeftQty(item)
    moveQtyInputs.value[item.id] = Math.max(0, leftQty)
    const rightQty = getStageRightQty(item)
    moveBackQtyInputs.value[item.id] = Math.max(0, rightQty)
  }
}

function getBackendStage(): string {
  switch (activePackStage.value) {
    case 'confirmed_packed': return 'packed'
    case 'packed_issued': return 'issued'
    case 'issued_returned': return 'returned'
    default: return 'packed'
  }
}

async function moveToNextStage(item: PackItem, qty?: number) {
  if (!selectedActivity.value) return
  const moveQty = qty ?? moveQtyInputs.value[item.id] ?? getStageLeftQty(item)
  if (moveQty <= 0) return

  try {
    const response = await apiClient.post(
      `/api/activities/${selectedActivity.value.id}/pack-items/${item.id}/move`,
      { stage: getBackendStage(), quantity: moveQty }
    )
    // Lokal aktualisieren aus der Server-Antwort
    const updated = response.data
    const idx = packItems.value.findIndex(p => p.id === item.id)
    if (idx !== -1) {
      packItems.value[idx].quantityPacked = updated.quantity_packed
      packItems.value[idx].quantityIssued = updated.quantity_issued
      packItems.value[idx].quantityReturned = updated.quantity_returned
      packItems.value[idx].isFullyPacked = updated.is_fully_packed
      packItems.value[idx].isFullyIssued = updated.is_fully_issued
      packItems.value[idx].isFullyReturned = updated.is_fully_returned
      packItems.value[idx].packDifference = updated.pack_difference
      packItems.value[idx].issueDifference = updated.issue_difference
      packItems.value[idx].returnDifference = updated.return_difference
    }
    // Input auf neuen Restwert setzen
    const newLeft = getStageLeftQty(packItems.value[packItems.value.findIndex(p => p.id === item.id)])
    moveQtyInputs.value[item.id] = Math.max(0, newLeft)
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

async function moveToPrevStage(item: PackItem) {
  if (!selectedActivity.value) return
  const moveQty = moveBackQtyInputs.value[item.id] ?? getStageRightQty(item)
  if (moveQty <= 0) return

  try {
    const response = await apiClient.post(
      `/api/activities/${selectedActivity.value.id}/pack-items/${item.id}/moveback`,
      { stage: getBackendStage(), quantity: moveQty }
    )
    // Lokal aktualisieren aus der Server-Antwort
    const updated = response.data
    const idx = packItems.value.findIndex(p => p.id === item.id)
    if (idx !== -1) {
      packItems.value[idx].quantityPacked = updated.quantity_packed
      packItems.value[idx].quantityIssued = updated.quantity_issued
      packItems.value[idx].quantityReturned = updated.quantity_returned
      packItems.value[idx].isFullyPacked = updated.is_fully_packed
      packItems.value[idx].isFullyIssued = updated.is_fully_issued
      packItems.value[idx].isFullyReturned = updated.is_fully_returned
      packItems.value[idx].packDifference = updated.pack_difference
      packItems.value[idx].issueDifference = updated.issue_difference
      packItems.value[idx].returnDifference = updated.return_difference
    }
    // Inputs aktualisieren
    const newLeft = getStageLeftQty(packItems.value[idx])
    const newRight = getStageRightQty(packItems.value[idx])
    moveQtyInputs.value[item.id] = Math.max(0, newLeft)
    moveBackQtyInputs.value[item.id] = Math.max(0, newRight)
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

async function moveAllToNextStage() {
  if (!selectedActivity.value) return
  try {
    await apiClient.post(
      `/api/activities/${selectedActivity.value.id}/pack-items/move-all`,
      { stage: getBackendStage() }
    )
    // Packliste neu laden für konsistenten State
    await loadPackItems()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

// ── 3-Punkte-Menü: Pack-Item bearbeiten / Meldung erstellen ──

const showPackEditModal = ref(false)
const packEditItem = ref<PackItem | null>(null)
const packEditQty = ref(0)
const packEditCondition = ref('ok')
const packEditNotes = ref('')
const packEditAction = ref<'edit' | 'loss' | 'repair' | 'consumption'>('edit')
const isPackEditSubmitting = ref(false)

function openPackEditModal(item: PackItem, action: 'edit' | 'loss' | 'repair' | 'consumption' = 'edit') {
  packEditItem.value = item
  packEditAction.value = action
  if (action === 'edit') {
    // Je nach Stufe die richtige Menge anzeigen
    switch (activePackStage.value) {
      case 'confirmed_packed': packEditQty.value = item.quantityPacked; break
      case 'packed_issued': packEditQty.value = item.quantityIssued; break
      case 'issued_returned': packEditQty.value = item.quantityReturned; break
    }
    packEditCondition.value = item.conditionOut || 'ok'
    packEditNotes.value = item.notes || ''
  } else {
    packEditQty.value = 1
    packEditNotes.value = ''
  }
  showPackEditModal.value = true
}

function closePackEditModal() {
  showPackEditModal.value = false
  packEditItem.value = null
}

async function confirmPackEdit() {
  if (!packEditItem.value || !selectedActivity.value) return
  if (isPackEditSubmitting.value) return

  isPackEditSubmitting.value = true
  try {
    if (packEditAction.value === 'loss' || packEditAction.value === 'repair' || packEditAction.value === 'consumption') {
      // Verlust, Reparatur oder Verbrauch melden → Issue Report erstellen
      const actionType = packEditAction.value
      const response = await apiClient.post(`/api/activities/${selectedActivity.value.id}/issues`, {
        material_item_id: packEditItem.value.materialItemId,
        type: actionType,
        quantity: packEditQty.value,
        description: packEditNotes.value || null,
      })
      closePackEditModal()
      // Hinweis wenn Werkstatt-Ticket erstellt (bei Verlust/Reparatur/Schaden)
      if (response.data?.workshop_ticket_created) {
        const msg = actionType === 'loss'
          ? 'Verlust gemeldet. Ein Abschreibungs-Ticket wurde in der Werkstatt erstellt.'
          : 'Meldung erstellt. Ein Reparatur-Ticket wurde in der Werkstatt erstellt.'
        toast.success(msg)
      }
      // Issues nachladen falls Tab offen
      await loadIssues()
      // Bei Verlust/Verbrauch: PackItems neu laden (Menge wurde im Backend reduziert)
      if (actionType === 'loss' || actionType === 'consumption') {
        await loadPackItems()
        initMoveQtyInputs()
      }
      return
    }

    // Menge ändern
    const updateData: Record<string, any> = {
      condition_out: packEditCondition.value,
      notes: packEditNotes.value || null,
    }
    // Je nach Stufe die richtige Menge aktualisieren
    switch (activePackStage.value) {
      case 'confirmed_packed': updateData.quantity_packed = packEditQty.value; break
      case 'packed_issued': updateData.quantity_issued = packEditQty.value; break
      case 'issued_returned': updateData.quantity_returned = packEditQty.value; break
    }

    const response = await apiClient.patch(
      `/api/activities/${selectedActivity.value.id}/pack-items/${packEditItem.value.id}`,
      updateData
    )
    const updated = response.data
    const idx = packItems.value.findIndex(p => p.id === packEditItem.value!.id)
    if (idx !== -1) {
      packItems.value[idx].quantityPacked = updated.quantity_packed
      packItems.value[idx].quantityIssued = updated.quantity_issued
      packItems.value[idx].quantityReturned = updated.quantity_returned
      packItems.value[idx].conditionOut = updated.condition_out
      packItems.value[idx].notes = updated.notes
      packItems.value[idx].isFullyPacked = updated.is_fully_packed
      packItems.value[idx].isFullyIssued = updated.is_fully_issued
      packItems.value[idx].isFullyReturned = updated.is_fully_returned
      packItems.value[idx].packDifference = updated.pack_difference
      packItems.value[idx].issueDifference = updated.issue_difference
      packItems.value[idx].returnDifference = updated.return_difference
    }
    closePackEditModal()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  } finally {
    isPackEditSubmitting.value = false
  }
}

// Schnell-Move: komplette Restmenge verschieben (1-Klick)
async function quickMove(item: PackItem) {
  const leftQty = getStageLeftQty(item)
  if (leftQty > 0) {
    await moveToNextStage(item, leftQty)
  }
}

// ═══════════════════════════════════════════════
// MELDUNGEN (Issue Reports)
// ═══════════════════════════════════════════════

async function loadIssues() {
  if (!selectedActivity.value) return
  isLoadingIssues.value = true
  try {
    const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/issues`)
    issueReports.value = (response.data || []).map((ir: any) => ({
      id: ir.id,
      materialItemId: ir.material_item_id,
      materialName: ir.material_name,
      type: ir.type,
      typeLabel: ir.type_label,
      quantity: ir.quantity,
      description: ir.description,
      photoUrl: ir.photo_url,
      notes: ir.notes,
      resolved: ir.resolved,
      resolvedAt: ir.resolved_at,
      reportedAt: ir.reported_at,
      isJsMaterial: ir.is_js_material || false,
      externalSource: ir.external_source || null,
    }))
  } catch (err) {
    console.error('Fehler beim Laden der Meldungen:', err)
  } finally {
    isLoadingIssues.value = false
  }
}

function selectIssueMaterial(item: any) {
  newIssue.value.materialItemId = item.materialItemId
  newIssue.value.materialName = item.materialName
  issueMatSearch.value = ''
  showIssueMatDropdown.value = false
}

async function createIssue() {
  if (!selectedActivity.value) return
  // Material ist Pflicht
  if (!newIssue.value.materialItemId) {
    toast.warning('Bitte wähle ein Material aus.')
    return
  }
  try {
    await apiClient.post(`/api/activities/${selectedActivity.value.id}/issues`, {
      material_item_id: newIssue.value.materialItemId || null,
      type: newIssue.value.type,
      quantity: newIssue.value.quantity,
      description: newIssue.value.description,
    })
    showIssueForm.value = false
    newIssue.value = { materialItemId: '', materialName: '', type: 'repair', quantity: 1, description: '' }
    issueMatSearch.value = ''
    showIssueMatDropdown.value = false
    await loadIssues()
    await loadWorkshopCostsForActivity()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

// Gefilterte Listen: Reparaturen/Verluste vs. Verbrauch (sortiert nach Zeit absteigend, dann ID)
const issueReportsFiltered = computed(() =>
  issueReports.value
    .filter(ir => ['repair', 'loss', 'damage'].includes(ir.type))
    .sort((a, b) => {
      const ta = new Date(a.reportedAt).getTime()
      const tb = new Date(b.reportedAt).getTime()
      if (tb !== ta) return tb - ta
      return (b.id || '').localeCompare(a.id || '')
    })
)
const consumptionReports = computed(() =>
  issueReports.value.filter(ir => ir.type === 'consumption')
)

const workshopTicketsByIssueId = computed(() => {
  const map = new Map<string, WorkshopTicket>()
  for (const t of workshopCostTickets.value) {
    if (t.issue_report_id) {
      map.set(t.issue_report_id, t)
    }
  }
  return map
})

function getWorkshopTicketForIssue(issueId: string): WorkshopTicket | null {
  return workshopTicketsByIssueId.value.get(issueId) || null
}

function getWorkshopStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    open: 'Offen',
    in_progress: 'In Arbeit',
    waiting_parts: 'Wartet auf Teile',
    completed: 'Erledigt',
    cancelled: 'Storniert',
  }
  return labels[status] || status || '—'
}

function openWorkshopForIssue(issueId: string) {
  const ticket = getWorkshopTicketForIssue(issueId)
  const query = ticket?.id ? `?ticket=${ticket.id}` : ''
  router.push(`/${departmentId.value}/workshop${query}`)
}

// Verbrauchsmaterial-Items aus der Material-Liste
const consumableItems = computed(() =>
  detailItems.value.filter((item: any) => item.isConsumable)
)

// Wie viel wurde von einem Material bereits verbraucht?
function getConsumableUsed(materialItemId: string): number {
  return consumptionReports.value
    .filter(cr => cr.materialItemId === materialItemId)
    .reduce((sum, cr) => sum + cr.quantity, 0)
}

// ═══ Kosten-Tab: Computed Properties ═══
const costConsumableItems = computed(() =>
  detailItems.value.filter((item: any) => item.isConsumable)
)

const costRentalItems = computed(() =>
  detailItems.value.filter((item: any) => !item.isConsumable)
)

const costConsumableTotal = computed(() => {
  return costConsumableItems.value.reduce((sum, item: any) => {
    if (!item.salePrice) return sum
    const used = getConsumableUsed(item.materialItemId)
    // Nur tatsächlich verbrauchte Menge zählt
    if (used <= 0) return sum
    return sum + Number(item.salePrice) * used
  }, 0)
})

const costRentalTotal = computed(() => {
  return costRentalItems.value.reduce((sum, item: any) => {
    return sum + (item.lineTotal || 0)
  }, 0)
})

const costGrandTotal = computed(() => costConsumableTotal.value + costRentalTotal.value)

const costLossItems = computed(() =>
  issueReports.value.filter(ir => ir.type === 'loss')
)

const workshopResolvedTickets = computed(() =>
  workshopCostTickets.value.filter(t => t.status === 'completed' && (t.resolution_action === 'repaired' || t.resolution_action === 'writeoff'))
)

const costRepairTickets = computed(() =>
  workshopResolvedTickets.value.filter(t => t.resolution_action === 'repaired')
)

const costWriteoffTickets = computed(() =>
  workshopResolvedTickets.value.filter(t => t.resolution_action === 'writeoff')
)

const costRepairTotal = computed(() => {
  return costRepairTickets.value.reduce((sum, t) => sum + Number(t.actual_cost || 0), 0)
})

const costWriteoffTotal = computed(() => {
  return costWriteoffTickets.value.reduce((sum, t) => sum + Number(t.actual_cost || 0), 0)
})

const costExternalGrandTotal = computed(() => {
  if (selectedActivity.value?.type !== 'external') return 0
  return costConsumableTotal.value + costRentalTotal.value + costRepairTotal.value + costWriteoffTotal.value
})

const costInternalGrandTotal = computed(() => {
  return costConsumableTotal.value + costRepairTotal.value + costWriteoffTotal.value
})

// Mengen-Inputs für Verbrauch
const consumableQtyInputs = ref<Record<string, number>>({})

// Verbrauch buchen (= Issue Report mit Typ "consumption")
async function reportConsumption(item: any) {
  if (!selectedActivity.value) return
  const qty = consumableQtyInputs.value[item.materialItemId] || 1
  try {
    await apiClient.post(`/api/activities/${selectedActivity.value.id}/issues`, {
      material_item_id: item.materialItemId,
      type: 'consumption',
      quantity: qty,
      description: null,
    })
    // Inputs zurücksetzen + Issues neu laden
    consumableQtyInputs.value[item.materialItemId] = 1
    await loadIssues()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

// ═══════════════════════════════════════════════
// RÜCKGABE
// ═══════════════════════════════════════════════

async function loadReturnItems() {
  if (!selectedActivity.value) return
  isLoadingReturns.value = true
  try {
    const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/return-items`)
    returnItems.value = (response.data || []).map((ri: any) => ({
      id: ri.id,
      materialItemId: ri.material_item_id,
      materialName: ri.material_name,
      quantityPacked: ri.quantity_packed,
      quantityReturned: ri.quantity_returned,
      quantityDamaged: ri.quantity_damaged,
      quantityMissing: ri.quantity_missing,
      quantityOk: ri.quantity_ok,
      conditionIn: ri.condition_in,
      notes: ri.notes,
      hasDifferences: ri.has_differences,
      returnedAt: ri.returned_at,
      isJsMaterial: ri.is_js_material || false,
      externalSource: ri.external_source || null,
    }))
    
    // Falls leer und Status returned → automatisch initialisieren
    if (returnItems.value.length === 0 && selectedActivity.value.status === 'returned') {
      await initReturnItems()
    }
  } catch (err) {
    console.error('Fehler beim Laden der Rückgabeliste:', err)
  } finally {
    isLoadingReturns.value = false
  }
}

async function initReturnItems() {
  if (!selectedActivity.value) return
  try {
    await apiClient.post(`/api/activities/${selectedActivity.value.id}/return-items/init`)
    await loadReturnItems()
  } catch (err) {
    console.error('Fehler beim Initialisieren der Rückgabeliste:', err)
  }
}

function onReturnConditionChange(returnItem: ReturnItem, event: Event) {
  const target = event.target as HTMLSelectElement | null
  updateReturnItem(returnItem, 'condition_in', target?.value || 'ok')
}

async function updateReturnItem(returnItem: ReturnItem, field: string, value: any) {
  if (!selectedActivity.value) return
  try {
    await apiClient.patch(`/api/activities/${selectedActivity.value.id}/return-items/${returnItem.id}`, {
      [field]: value,
    })
    // Lokal aktualisieren
    const idx = returnItems.value.findIndex(r => r.id === returnItem.id)
    if (idx !== -1) {
      const fieldMap: Record<string, string> = {
        quantity_returned: 'quantityReturned',
        quantity_damaged: 'quantityDamaged', 
        quantity_missing: 'quantityMissing',
        condition_in: 'conditionIn',
        notes: 'notes',
      }
      const localField = fieldMap[field] || field
      ;(returnItems.value[idx] as any)[localField] = value
      returnItems.value[idx].hasDifferences = returnItems.value[idx].quantityDamaged > 0 || returnItems.value[idx].quantityMissing > 0
      returnItems.value[idx].quantityOk = Math.max(0, returnItems.value[idx].quantityReturned - returnItems.value[idx].quantityDamaged)
    }
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

function getHistoryActionLabel(action: string): string {
  const labels: Record<string, string> = {
    created: 'Erstellt',
    updated: 'Bearbeitet',
    deleted: 'Gelöscht',
    status_changed: 'Status geändert',
    material_added: 'Material hinzugefügt',
    material_removed: 'Material entfernt',
    issue_reported: 'Meldung erstellt',
    pack_started: 'Packen begonnen',
    return_started: 'Rückgabe begonnen',
  }
  return labels[action] || action
}

// Sortierung
function toggleSort(field: string) {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDir.value = 'asc'
  }
}

// Datums-Hilfsfunktionen
function isSameDay(date1: string, date2: string): boolean {
  const d1 = new Date(date1)
  const d2 = new Date(date2)
  return d1.toDateString() === d2.toDateString()
}

function formatDateShort(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH', { day: '2-digit', month: 'short' })
}

function getRelativeDate(dateStr: string): string {
  const now = new Date()
  const d = new Date(dateStr)
  const diffMs = d.getTime() - now.getTime()
  const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24))
  
  if (diffDays === 0) return 'heute'
  if (diffDays === 1) return 'morgen'
  if (diffDays === -1) return 'gestern'
  if (diffDays > 0 && diffDays <= 7) return `in ${diffDays} Tagen`
  if (diffDays < 0 && diffDays >= -7) return `vor ${Math.abs(diffDays)} Tagen`
  if (diffDays > 7 && diffDays <= 30) return `in ${Math.ceil(diffDays / 7)} Wochen`
  if (diffDays < -7 && diffDays >= -30) return `vor ${Math.ceil(Math.abs(diffDays) / 7)} Wochen`
  return ''
}

provide(ACTIVITIES_DETAIL_INJECT, {
  PACK_STAGES,
  acceptedInvitedDepartmentsForTabs,
  activeDetailTab,
  activePackStage,
  activeStageConfig,
  activityDetail,
  activityHistory,
  activityQrDataUrl,
  addDetailMaterial,
  addItemToPackContainer,
  adjustAvailable,
  adjustAvailableLoading,
  adjustItem,
  adjustMaxAllowed,
  adjustNewQty,
  adjustPackSize,
  adjustPackUnit,
  availableTransitions,
  canEditMaterial,
  canMwAddMaterial,
  canMwAdjustMaterial,
  canUseDetailJsMaterialSource,
  cancelActivity,
  cancelEditDraft,
  changeDetailMaterialQty,
  closeAdjustModal,
  closeDetail,
  closePackEditModal,
  collapsedGroups,
  confirmAdjust,
  confirmPackEdit,
  consumableItems,
  consumableQtyInputs,
  consumptionReports,
  costConsumableItems,
  costConsumableTotal,
  costExternalGrandTotal,
  costInternalGrandTotal,
  costLossItems,
  costRentalItems,
  costRentalTotal,
  costRepairTickets,
  costRepairTotal,
  costWriteoffTickets,
  costWriteoffTotal,
  createIssue,
  createPackContainer,
  detailInternalScope,
  detailItems,
  detailMatSearch,
  detailMaterialLookupFetcher,
  detailMaterialSource,
  detailSingleDepartmentId,
  detailTabs,
  draftEditData,
  draftEditInviteGroupsById,
  draftEditInvitedDepartments,
  filteredDetailItems,
  formatDateShort,
  formatDateTime,
  formatDateTimeShort,
  formatDateTimeWithSeconds,
  getConditionLabel,
  getConsumableUsed,
  getHistoryActionLabel,
  getMaterialSourceLabel,
  getStageLeftQty,
  getStageRightQty,
  getStageTotalQty,
  getStatusLabel,
  getTransitionBtnClass,
  getTransitionIcon,
  getTypeLabel,
  getWorkshopStatusLabel,
  getWorkshopTicketForIssue,
  groupsLeft,
  groupsRight,
  handleDetailLookupSelect,
  handleTransition,
  handleWorkflowTransition,
  initMoveQtyInputs,
  initPackItems,
  initReturnItems,
  invitedDepartmentsDetail,
  isDraftEditable,
  isEditingDraft,
  isLoadingDetailItems,
  isLoadingHistory,
  isLoadingIssues,
  isLoadingPackItems,
  isLoadingReturns,
  isLoadingWorkshopCosts,
  isPackEditSubmitting,
  isPackWorkflowTarget,
  isSameDay,
  issueMatFiltered,
  issueMatSearch,
  issueReportsFiltered,
  jsWorkflowSummary,
  loadDraftEditInviteGroups,
  loadSelectedPackContainerItems,
  moveAllToNextStage,
  moveBackQtyInputs,
  moveQtyInputs,
  moveToNextStage,
  moveToPrevStage,
  myGroups,
  navigateToPackTab,
  newIssue,
  newPackContainerItemMaterialId,
  newPackContainerItemQty,
  newPackContainerLabel,
  nextWorkflowTransition,
  onDraftDateChange,
  onMaterialRowDblClick,
  onReturnConditionChange,
  openAdjustModal,
  openPackEditModal,
  openWorkshopForIssue,
  packContainers,
  packEditAction,
  packEditCondition,
  packEditItem,
  packEditNotes,
  packEditQty,
  packItems,
  printActivity,
  removeAllMaterialsAndSave,
  removeDetailMaterial,
  removeItemFromPackContainer,
  reportConsumption,
  returnItems,
  saveDraftEdit,
  selectIssueMaterial,
  selectedActivity,
  selectedPackContainerId,
  selectedPackContainerItems,
  setDetailInvitedDepartmentTab,
  setDetailMaterialInternalScope,
  setDetailMaterialSource,
  setDraftEditInviteGroup,
  showAdjustModal,
  showDateChangeWarning,
  showIssueForm,
  showIssueMatDropdown,
  showPackEditModal,
  snapDatetimeLocalToStep,
  stageLeftItems,
  stageProgress,
  stageRightItems,
  startEditDraft,
  switchDetailTab,
  toggleGroup,
  truncateDeptTabLabel,
  tryShowNativePicker,
  updateReturnItem,
})
</script>

<style scoped>
@import '@/styles/views/activities/index.css';
</style>
