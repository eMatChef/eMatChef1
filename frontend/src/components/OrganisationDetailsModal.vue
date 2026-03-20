<template>
  <div v-if="isOpen" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Details: {{ organisation?.name }}</h2>
        <button @click="close" class="close-button">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
      
      <div class="modal-body">
        <div v-if="isLoading" class="loading-state">
          <div class="spinner"></div>
          <p>Lade Organisations-Details...</p>
        </div>
        
        <div v-else-if="error" class="error-state">
          <p class="error-message">{{ error }}</p>
          <button @click="loadOrganisationData" class="retry-button">Erneut versuchen</button>
        </div>
        
        <div v-else class="details-content">
          <!-- Organisation Info -->
          <div class="info-section">
            <h3>Organisations-Informationen</h3>
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">ID:</span>
                <span class="info-value">{{ organisation?.id }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Name:</span>
                <span class="info-value">{{ organisation?.name }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Haupt-Departments:</span>
                <span class="info-value">{{ departments.length }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Gesamt User:</span>
                <span class="info-value">{{ totalUsers }}</span>
              </div>
            </div>
          </div>
          
          <!-- Departments -->
          <div class="departments-section">
            <h3>Haupt-Departments ({{ departments.length }})</h3>
            <div v-if="departments.length > 0" class="departments-list">
              <div v-for="dept in departments" :key="dept.id" class="department-item">
                <div class="department-header">
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" class="folder-icon">
                    <path
                      d="M2 4C2 3.44772 2.44772 3 3 3H6.58579C6.851 3 7.10536 3.10536 7.29289 3.29289L8.70711 4.70711C8.89464 4.89464 9.149 5 9.41421 5H13C13.5523 5 14 5.44772 14 6V12C14 12.5523 13.5523 13 13 13H3C2.44772 13 2 12.5523 2 12V4Z"
                      fill="currentColor"
                    />
                  </svg>
                  <span class="department-name">{{ dept.name }}</span>
                  <span class="user-count">{{ dept.users?.length || 0 }} User</span>
                </div>
                
                <!-- User dieses Departments -->
                <div v-if="dept.users && dept.users.length > 0" class="users-list">
                  <div v-for="user in dept.users" :key="user.id" class="user-item">
                    <div class="user-avatar">
                      {{ user.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div class="user-info">
                      <span class="user-name">{{ user.name }}</span>
                      <span class="user-email">{{ user.email }}</span>
                    </div>
                    <span class="user-role">{{ formatRole(user.role) }}</span>
                  </div>
                </div>
                <p v-else class="empty-users">Keine User in diesem Department.</p>
              </div>
            </div>
            <p v-else class="empty-departments">Keine Haupt-Departments vorhanden.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { getDepartments, getDepartment, type Department, type DepartmentUser } from '@/api/departments'
import type { Organisation } from '@/api/organisations'

interface Props {
  isOpen: boolean
  organisation: Organisation | null
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'close': []
}>()

const isLoading = ref(false)
const error = ref<string | null>(null)
const departments = ref<Array<Department & { users: DepartmentUser[] }>>([])

const totalUsers = computed(() => {
  return departments.value.reduce((sum, dept) => sum + (dept.users?.length || 0), 0)
})

function formatRole(role: string): string {
  const roleMap: Record<string, string> = {
    'sa': 'Superadmin',
    'org': 'Organisationschef',
    'dep': 'Department-Chef',
    'user': 'User'
  }
  return roleMap[role] || role
}

watch(() => [props.isOpen, props.organisation], async ([open, org]) => {
  if (open && org) {
    await loadOrganisationData()
  } else {
    departments.value = []
    error.value = null
  }
})

async function loadOrganisationData() {
  if (!props.organisation) return
  
  try {
    isLoading.value = true
    error.value = null

    // Lade alle Departments dieser Organisation
    const allDepartments = await getDepartments()
    
    // WICHTIG: Nur Haupt-Departments (ohne parent_id), keine Unter-Departments
    const orgMainDepartments = allDepartments.filter(
      d => d.organisation_id === props.organisation!.id && !d.parent_id
    )

    // Lade User für jedes Haupt-Department
    const departmentsWithUsers = await Promise.all(
      orgMainDepartments.map(async (dept) => {
        try {
          const fullDept = await getDepartment(dept.id)
          return {
            ...dept,
            users: fullDept.users || []
          }
        } catch {
          return {
            ...dept,
            users: []
          }
        }
      })
    )

    // Sortiere alphabetisch
    departments.value = departmentsWithUsers.sort((a, b) => {
      return a.name.localeCompare(b.name, 'de')
    })

  } catch (err: any) {
    error.value = err.response?.data?.error || 'Fehler beim Laden der Daten'
  } finally {
    isLoading.value = false
  }
}

function close() {
  emit('close')
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 700px;
  max-height: 80vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
}

.close-button {
  background: none;
  border: none;
  cursor: pointer;
  padding: 8px;
  color: #6b7280;
  border-radius: 6px;
  transition: all 0.2s;
}

.close-button:hover {
  background: #f3f4f6;
  color: #1f2937;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 40px;
}

.error-state {
  text-align: center;
  padding: 40px;
}

.error-message {
  color: #dc2626;
  margin-bottom: 16px;
}

.retry-button {
  padding: 8px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.2s;
}

.retry-button:hover {
  background: #2563eb;
}

.details-content {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.info-section h3,
.departments-section h3 {
  margin: 0 0 16px 0;
  font-size: 16px;
  font-weight: 600;
  color: #374151;
}

.info-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.info-item {
  display: flex;
  gap: 12px;
}

.info-label {
  font-weight: 500;
  color: #6b7280;
  min-width: 160px;
}

.info-value {
  color: #1f2937;
  font-family: 'Monaco', 'Menlo', monospace;
  font-size: 13px;
}

.departments-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.department-item {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 16px;
  background: #f9fafb;
}

.department-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.folder-icon {
  color: #6b7280;
  flex-shrink: 0;
}

.department-name {
  font-weight: 600;
  color: #1f2937;
  flex: 1;
}

.user-count {
  font-size: 12px;
  color: #6b7280;
  background: #e5e7eb;
  padding: 2px 8px;
  border-radius: 10px;
}

.users-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 12px;
  margin-left: 24px;
}

.user-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  background: white;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #3b82f6;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 14px;
  flex-shrink: 0;
}

.user-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.user-name {
  font-weight: 500;
  color: #1f2937;
  font-size: 14px;
}

.user-email {
  font-size: 12px;
  color: #6b7280;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-role {
  padding: 4px 10px;
  background: #e0e7ff;
  color: #4338ca;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  flex-shrink: 0;
}

.empty-users,
.empty-departments {
  color: #6b7280;
  font-style: italic;
  text-align: center;
  padding: 16px;
  font-size: 14px;
}
</style>
