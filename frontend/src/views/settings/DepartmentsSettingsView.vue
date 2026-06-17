<template>
  <div class="departments-settings">
    <div class="header-section">
      <div>
        <h1>{{ t('settings.departments.title') }}</h1>
        <p class="description">{{ t('settings.departments.description') }}</p>
      </div>
      <div v-if="canManageDepartments" class="header-actions">
        <v-menu v-if="canCreateGrossanlass">
          <template #activator="{ props: menuProps }">
            <EButton
              v-bind="menuProps"
              variant="primary"
              :title="t('settings.departments.addTitle')"
            >
              <v-icon icon="mdi-plus" start size="20" />
              {{ t('common.add') }}
              <v-icon icon="mdi-chevron-down" end size="18" />
            </EButton>
          </template>
          <v-list density="compact">
            <v-list-item :title="t('settings.departments.addDepartment')" @click="openAddModal" />
            <v-list-item :title="t('settings.departments.addGrossanlass')" @click="openGrossanlassWizard" />
          </v-list>
        </v-menu>
        <EButton
          v-else
          variant="primary"
          :title="t('settings.departments.addTitle')"
          @click="openAddModal"
        >
          <v-icon icon="mdi-plus" start size="20" />
          {{ t('common.add') }}
        </EButton>
      </div>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="list"
      :rows="5"
      :message="t('settings.departments.loading')"
    />

    <div v-else-if="error" class="error-block">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadDepartments">{{ t('common.retry') }}</EButton>
    </div>

    <!-- Tree List für Departments -->
    <div v-else-if="treeItems.length > 0" class="tree-container">
      <TreeList
        :items="treeItems"
        :header-label="t('common.name')"
        :selected-items="selectedItems"
        :expanded-items="expandedItems"
        @update:selected-items="selectedItems = $event"
        @update:expanded-items="handleExpandChange"
        @selection-change="handleSelectionChange"
        @edit-item="handleEditItem"
        @show-users="handleShowUsers"
        @show-details="handleShowOrganisationDetails"
        @show-department-details="handleShowDepartmentDetails"
        @add-department="handleAddDepartment"
      />
    </div>

    <EEmptyState
      v-else
      variant="create"
      :title="t('settings.departments.empty')"
    />

    <!-- Debug Info (temporär) -->
    <div v-if="isDev" class="debug-info">
      <h3>{{ t('settings.departments.debugTitle') }}</h3>
      <p>{{ t('settings.departments.debugSelected', { n: selectedItems.length }) }}</p>
      <p>{{ t('settings.departments.debugIds', { ids: selectedItems.join(', ') }) }}</p>
    </div>

    <!-- Department Modal -->
    <DepartmentModal
      :is-open="isModalOpen"
      :department="editingDepartment"
      :preselected-organisation-id="preselectedOrganisationId"
      :preselected-parent-id="preselectedParentId"
      @close="closeModal"
      @saved="handleDepartmentSaved"
    />

    <GrossanlassCreateWizard
      :is-open="isGrossanlassWizardOpen"
      :preselected-organisation-id="preselectedOrganisationId"
      :preselected-parent-id="preselectedParentId"
      @close="closeGrossanlassWizard"
      @created="handleGrossanlassCreated"
    />

    <!-- Organisation Details Modal -->
    <OrganisationDetailsModal
      :is-open="isOrganisationDetailsModalOpen"
      :organisation="selectedOrganisation"
      @close="closeOrganisationDetailsModal"
    />

    <!-- Organisation Edit Modal -->
    <OrganisationModal
      :is-open="isOrganisationModalOpen"
      :organisation="editingOrganisation"
      @close="closeOrganisationModal"
      @saved="handleOrganisationSaved"
    />

    <!-- Department Details Modal -->
    <DepartmentDetailsModal
      :is-open="isDepartmentDetailsModalOpen"
      :department-id="selectedDepartmentId"
      @close="closeDepartmentDetailsModal"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import TreeList, { type TreeItemData } from '@/components/TreeList.vue'
import DepartmentModal from '@/components/DepartmentModal.vue'
import GrossanlassCreateWizard from '@/components/GrossanlassCreateWizard.vue'
import OrganisationModal from '@/components/OrganisationModal.vue'
import OrganisationDetailsModal from '@/components/OrganisationDetailsModal.vue'
import DepartmentDetailsModal from '@/components/DepartmentDetailsModal.vue'
import { getDepartments, getDepartment, type Department, type DepartmentUser } from '@/api/departments'
import { getOrganisations, type Organisation } from '@/api/organisations'
import { useAuthStore } from '@/stores/auth'
import {
  memberOrganisationIdsFromUserDepartments,
  prepareOrganisationsForOrgSubAdminList
} from '@/utils/organisationUserPicker'
import { filterDepartmentsByAccessibleIds } from '@/utils/adminCapabilities'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton } from '@/components/form/base'

const authStore = useAuthStore()
const { t } = useI18n()
const isDev = computed(() => import.meta.env.DEV)

/**
 * Berechtigung: Nur SUPERADMIN, ORGANISATIONSCHEF oder SUBORGCHEF können Departments verwalten
 */
const canManageDepartments = computed(() =>
  authStore.canAdmin('departments.create') || authStore.canAdmin('departments.edit')
)

/** Grossanlass anlegen: nur org / sub / sa (GL) */
const canCreateGrossanlass = computed(() =>
  authStore.userRoles.includes('ROLE_SUPERADMIN') ||
  authStore.userRoles.includes('ROLE_ORGANISATIONSCHEF') ||
  authStore.userRoles.includes('ROLE_SUBORGCHEF')
)

const isSuperAdmin = computed(() =>
  (authStore.userRoles || []).includes('ROLE_SUPERADMIN')
)

const memberOrganisationIds = computed(() =>
  memberOrganisationIdsFromUserDepartments(authStore.departments)
)

const isLoading = ref(false)
const error = ref<string | null>(null)

const treeItems = ref<TreeItemData[]>([])
const selectedItems = ref<string[]>([])
const expandedItems = ref<string[]>([])

// Modal State
const isModalOpen = ref(false)
const isGrossanlassWizardOpen = ref(false)
const editingDepartment = ref<Department | null>(null)
const preselectedOrganisationId = ref<string | null>(null)
const preselectedParentId = ref<string | null>(null)
const isOrganisationDetailsModalOpen = ref(false)
const selectedOrganisation = ref<Organisation | null>(null)
const isOrganisationModalOpen = ref(false)
const editingOrganisation = ref<Organisation | null>(null)
const isDepartmentDetailsModalOpen = ref(false)
const selectedDepartmentId = ref<string | null>(null)

// Cache für geladene User pro Department
const loadedUsers = ref<Record<string, DepartmentUser[]>>({})

// Cache für Organisationen
const organisations = ref<Organisation[]>([])

/**
 * Konvertiert Organisationen und Departments zu hierarchischer TreeItemData-Struktur
 * Struktur: Organisation → Haupt-Department → Unter-Departments → User
 */
function convertToTreeItems(organisations: Organisation[], departments: Department[]): TreeItemData[] {
  // Trenne Haupt-Departments (parent_id = null) und Unter-Departments
  const mainDepartments = departments.filter(d => !d.parent_id)
  const subDepartments = departments.filter(d => d.parent_id)
  
  // Erstelle Map für schnellen Zugriff
  const deptMap = new Map<string, Department>()
  departments.forEach(d => deptMap.set(d.id, d))
  
  // Erstelle Tree-Struktur für Departments rekursiv
  // VEREINFACHT: Zeigt nur Departments, keine User im Tree
  function createDepartmentTreeItem(dept: Department): TreeItemData {
    const deptId = dept.id
    
    // Finde alle Unter-Departments dieses Departments
    const childrenDepts = subDepartments.filter(d => d.parent_id === deptId)
    
    // Erstelle Children: NUR Unter-Departments, KEINE User
    const children: TreeItemData[] = []
    
    // Unter-Departments hinzufügen (rekursiv)
    childrenDepts.forEach(childDept => {
      children.push(createDepartmentTreeItem(childDept))
    })
    
    return {
      id: `dept-${deptId}`,
      label: dept.name,
      type: 'group' as const,
      children: children.length > 0 ? children : undefined,
      data: {
        departmentId: deptId,
        hasSubDepartments: childrenDepts.length > 0
      }
    }
  }
  
  // Erstelle Tree-Items für Organisationen mit ihren Departments
  return organisations.map(org => {
    // Finde alle Haupt-Departments dieser Organisation
    const orgMainDepartments = mainDepartments.filter(d => d.organisation_id === org.id)
    
    // Erstelle Children für die Organisation (Haupt-Departments)
    const orgChildren: TreeItemData[] = orgMainDepartments.map(dept => createDepartmentTreeItem(dept))
    
    return {
      id: `org-${org.id}`,
      label: org.name,
      type: 'group' as const,
      children: orgChildren.length > 0 ? orgChildren : undefined,
      data: {
        organisationId: org.id
      }
    }
  })
}

/**
 * Lädt Organisationen und Departments aus der API (OHNE User für Performance)
 */
async function loadDepartments() {
  try {
    isLoading.value = true
    error.value = null

    // Lade Organisationen und Departments parallel
    const [orgsRaw, depts] = await Promise.all([
      getOrganisations(),
      getDepartments()
    ])

    const orgs = prepareOrganisationsForOrgSubAdminList(orgsRaw, {
      isSuperAdmin: isSuperAdmin.value,
      memberOrganisationIds: memberOrganisationIds.value
    })
    organisations.value = orgs

    const visibleDepts = filterDepartmentsByAccessibleIds(depts, authStore.accessibleDepartmentIds)
    const visibleOrgIds = new Set(visibleDepts.map((d) => d.organisation_id))
    const visibleOrgs = orgs.filter((o) => visibleOrgIds.has(o.id))

    treeItems.value = convertToTreeItems(visibleOrgs, visibleDepts)
    
    // KEINE Items standardmäßig expanded (User werden erst bei Bedarf geladen)
    expandedItems.value = []
    
  } catch (err: any) {
    error.value = err.response?.data?.error || t('settings.departments.loadError')
  } finally {
    isLoading.value = false
  }
}

/**
 * Lädt User für ein Department (Lazy Loading)
 * WICHTIG: Behält bestehende Unter-Departments bei und fügt User nur hinzu
 * Lädt NUR User für das spezifische Department, nicht für Unter-Departments
 */
async function loadDepartmentUsers(departmentId: string) {
  // Prüfe ob bereits geladen
  if (loadedUsers.value[departmentId]) {
    // User sind bereits geladen, aber vielleicht nicht angezeigt
    // Aktualisiere die Anzeige
    updateDepartmentWithUsers(departmentId)
    return
  }

  try {
    // Lade Department mit User
    const department = await getDepartment(departmentId)
    
    // User im Cache speichern (NUR für dieses Department)
    loadedUsers.value[departmentId] = department.users || []
    
    // Aktualisiere die Anzeige
    updateDepartmentWithUsers(departmentId)
    
  } catch (err: any) {
    error.value = t('settings.departments.loadUsersError')
  }
}

/**
 * Aktualisiert ein Department im Tree und zeigt User an
 * WICHTIG: Aktualisiert NUR das spezifische Department, nicht Unter-Departments
 */
function updateDepartmentWithUsers(departmentId: string) {
  // Rekursive Funktion um das Department im Tree zu finden und zu aktualisieren
  function updateDepartmentInTree(items: TreeItemData[]): TreeItemData[] {
    return items.map(item => {
      // Prüfe ob es das gesuchte Department ist
      if (item.id === `dept-${departmentId}`) {
        // Gefunden! Aktualisiere NUR dieses Department
        const cachedUsers = loadedUsers.value[departmentId] || []
        
        // Behalte bestehende Children (Unter-Departments)
        const existingChildren = item.children || []
        
        // Trenne Unter-Departments und User
        // WICHTIG: Behalte alle Unter-Departments (type === 'group')
        // Entferne nur User, die zu diesem Department gehören (werden neu erstellt)
        const subDepartments = existingChildren.filter(child => child.type === 'group')
        
        // Entferne bereits vorhandene User dieses Departments (falls vorhanden)
        // Diese werden durch die neuen User-Items ersetzt
        const otherItems = existingChildren.filter(child => 
          child.type === 'item' && child.data?.departmentId !== departmentId
        )
        
        // Erstelle neue User-Items (NUR für dieses Department)
        const userItems: TreeItemData[] = cachedUsers.map(user => ({
          id: `user-${user.id}`,
          label: user.name,
          type: 'item' as const,
          data: {
            userId: user.id,
            profileId: user.profile_id,
            email: user.email,
            role: user.role,
            departmentId: departmentId,
            departmentName: item.label
          }
        }))
        
        // Kombiniere: Zuerst Unter-Departments, dann andere Items, dann User dieses Departments
        const allChildren = [...subDepartments, ...otherItems, ...userItems]
        
        return {
          ...item,
          children: allChildren.length > 0 ? allChildren : undefined,
          data: {
            ...item.data,
            usersLoaded: true
          }
        }
      }
      
      // Rekursiv in Children suchen (sowohl Organisationen als auch Departments)
      // WICHTIG: Aktualisiere Unter-Departments NICHT, nur durchsuchen
      if (item.children && item.children.length > 0) {
        return {
          ...item,
          children: updateDepartmentInTree(item.children)
        }
      }
      
      return item
    })
  }
  
  // Aktualisiere Tree Items rekursiv
  treeItems.value = updateDepartmentInTree(treeItems.value)
}

/**
 * Handler für Expand-Änderungen - lädt KEINE User automatisch
 * User werden nur über das Menü "User anzeigen" geladen
 */
async function handleExpandChange(items: string[]) {
  expandedItems.value = items
  // User werden NICHT automatisch geladen beim Expandieren
}

/**
 * Handler für "User anzeigen" aus dem Menü (nicht mehr verwendet, aber für Kompatibilität behalten)
 */
async function handleShowUsers(item: TreeItemData) {
  if (item.type === 'group' && item.id.startsWith('dept-')) {
    const departmentId = item.id.replace('dept-', '')
    await loadDepartmentUsers(departmentId)
  }
}

/**
 * Handler für "Details anzeigen" bei Organisationen
 */
function handleShowOrganisationDetails(item: TreeItemData) {
  if (item.type === 'group' && item.id.startsWith('org-')) {
    const organisationId = item.id.replace('org-', '')
    const org = organisations.value.find(o => o.id === organisationId)
    if (org) {
      selectedOrganisation.value = org
      isOrganisationDetailsModalOpen.value = true
    }
  }
}

function closeOrganisationDetailsModal() {
  isOrganisationDetailsModalOpen.value = false
  selectedOrganisation.value = null
}

function closeOrganisationModal() {
  isOrganisationModalOpen.value = false
  editingOrganisation.value = null
}

async function handleOrganisationSaved() {
  await loadDepartments()
  closeOrganisationModal()
}

/**
 * Handler für "Details anzeigen" bei Departments
 * Lädt nur das ausgewählte Department, keine Sub-Departments
 */
function handleShowDepartmentDetails(item: TreeItemData) {
  if (item.type === 'group' && item.id.startsWith('dept-')) {
    const departmentId = item.id.replace('dept-', '')
    selectedDepartmentId.value = departmentId
    isDepartmentDetailsModalOpen.value = true
  }
}

function closeDepartmentDetailsModal() {
  isDepartmentDetailsModalOpen.value = false
  selectedDepartmentId.value = null
}

function handleSelectionChange(selectedIds: string[]) {
  // Hier können Aktionen ausgeführt werden, z.B. API-Calls
}

function openAddModal() {
  editingDepartment.value = null
  preselectedOrganisationId.value = null
  preselectedParentId.value = null
  isModalOpen.value = true
}

function openGrossanlassWizard() {
  editingDepartment.value = null
  preselectedOrganisationId.value = null
  preselectedParentId.value = null
  isGrossanlassWizardOpen.value = true
}

function closeGrossanlassWizard() {
  isGrossanlassWizardOpen.value = false
  preselectedOrganisationId.value = null
  preselectedParentId.value = null
}

async function handleGrossanlassCreated() {
  await loadDepartments()
}

async function handleAddDepartment(item: TreeItemData) {
  if (item.type === 'group' && item.id.startsWith('org-')) {
    // Organisation: Öffne Modal mit vorausgewählter Organisation
    const organisationId = item.id.replace('org-', '')
    editingDepartment.value = null
    preselectedOrganisationId.value = organisationId
    preselectedParentId.value = null
    isModalOpen.value = true
  } else if (item.type === 'group' && item.id.startsWith('dept-')) {
    // Department: Öffne Modal mit vorausgewählter Organisation und Parent-Department
    const departmentId = item.id.replace('dept-', '')
    
    try {
      // Lade Department-Daten um organisation_id zu bekommen
      const dept = await getDepartment(departmentId)
      editingDepartment.value = null
      preselectedOrganisationId.value = dept.organisation_id
      preselectedParentId.value = departmentId // Setze als Parent
      isModalOpen.value = true
    } catch (err: any) {
      error.value = t('settings.departments.loadDepartmentError')
    }
  }
}

async function handleEditItem(item: TreeItemData) {
  if (item.type === 'group' && item.id.startsWith('org-')) {
    const organisationId = item.id.replace('org-', '')
    const org = organisations.value.find(o => o.id === organisationId)
    if (org) {
      editingOrganisation.value = org
      isOrganisationModalOpen.value = true
    } else {
      error.value = t('settings.departments.organisationNotFound')
    }
    return
  }

  // Finde das Department aus den geladenen Daten
  if (item.type === 'group' && item.id.startsWith('dept-')) {
    const departmentId = item.id.replace('dept-', '')
    try {
      // Lade vollständige Department-Daten
      const department = await getDepartment(departmentId)
      editingDepartment.value = department
      isModalOpen.value = true
    } catch (err: any) {
      error.value = t('settings.departments.loadDepartmentError')
    }
  }
}

function closeModal() {
  isModalOpen.value = false
  editingDepartment.value = null
  preselectedOrganisationId.value = null
  preselectedParentId.value = null
}

async function handleDepartmentSaved() {
  // Departments neu laden
  await loadDepartments()
}

// Beim Mounten Daten laden
onMounted(() => {
  loadDepartments()
})
</script>

<style scoped>
.departments-settings {
  padding: 0;
  max-width: 100%;
}

.header-section {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
}

.header-actions {
  flex-shrink: 0;
}

h1 {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 8px;
}

.description {
  color: #6b7280;
  font-size: 14px;
  margin-bottom: 24px;
}

.tree-container {
  margin-bottom: 24px;
}

.error-block {
  padding: 8px 0;
}

.debug-info {
  margin-top: 32px;
  padding: 16px;
  background: #f3f4f6;
  border-radius: 8px;
  font-size: 12px;
  color: #6b7280;
}

.debug-info h3 {
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 8px;
  color: #374151;
}

.debug-info p {
  margin: 4px 0;
}
</style>
