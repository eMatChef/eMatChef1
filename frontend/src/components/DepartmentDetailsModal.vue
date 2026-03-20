<template>
  <div v-if="isOpen" class="modal-overlay">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Details: {{ department?.name }}</h2>
        <button @click="close" class="close-button">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
      
      <div class="modal-body">
        <div v-if="isLoading" class="loading-state">
          <div class="spinner"></div>
          <p>Lade Department-Details...</p>
        </div>
        
        <div v-else-if="error" class="error-state">
          <p class="error-message">{{ error }}</p>
          <button @click="loadDepartmentDetails" class="retry-button">Erneut versuchen</button>
        </div>
        
        <div v-else-if="departmentDetails" class="details-content">
          <!-- Department Info -->
          <div class="info-section">
            <h3>Department-Informationen</h3>
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">ID:</span>
                <span class="info-value">{{ departmentDetails.id }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Name:</span>
                <span class="info-value">{{ departmentDetails.name }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Organisation ID:</span>
                <span class="info-value">{{ departmentDetails.organisation_id }}</span>
              </div>
              <div v-if="departmentDetails.parent_id" class="info-item">
                <span class="info-label">Parent-Department ID:</span>
                <span class="info-value">{{ departmentDetails.parent_id }}</span>
              </div>
            </div>
          </div>
          
          <!-- Users -->
          <div class="users-section">
            <h3>User in diesem Department ({{ departmentDetails.users?.length || 0 }})</h3>
            <div v-if="departmentDetails.users && departmentDetails.users.length > 0" class="users-list">
              <div v-for="user in departmentDetails.users" :key="user.id" class="user-item">
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
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { getDepartment, type Department, type DepartmentUser } from '@/api/departments'

interface Props {
  isOpen: boolean
  departmentId: string | null
}

const props = defineProps<Props>()
const emit = defineEmits<{
  close: []
}>()

const isLoading = ref(false)
const error = ref<string | null>(null)
const departmentDetails = ref<Department | null>(null)
const department = ref<{ name: string } | null>(null)

function formatRole(role: string): string {
  const roleMap: Record<string, string> = {
    'sa': 'Superadmin',
    'org': 'Organisationschef',
    'dep': 'Department-Chef',
    'user': 'User'
  }
  return roleMap[role] || role
}

async function loadDepartmentDetails() {
  if (!props.departmentId) {
    departmentDetails.value = null
    return
  }
  
  isLoading.value = true
  error.value = null
  
  try {
    // Lade nur dieses spezifische Department mit seinen Usern
    const dept = await getDepartment(props.departmentId)
    departmentDetails.value = dept
    department.value = { name: dept.name }
  } catch (err: any) {
    error.value = err.response?.data?.error || 'Fehler beim Laden der Department-Details'
  } finally {
    isLoading.value = false
  }
}

watch(() => props.isOpen, (newVal) => {
  if (newVal && props.departmentId) {
    loadDepartmentDetails()
  } else {
    departmentDetails.value = null
    department.value = null
  }
})

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
  max-width: 600px;
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
.users-section h3 {
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
  min-width: 140px;
}

.info-value {
  color: #1f2937;
  font-family: 'Monaco', 'Menlo', monospace;
  font-size: 13px;
}

.users-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.user-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f9fafb;
  border-radius: 8px;
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #3b82f6;
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 16px;
}

.user-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.user-name {
  font-weight: 500;
  color: #1f2937;
}

.user-email {
  font-size: 13px;
  color: #6b7280;
}

.user-role {
  padding: 4px 10px;
  background: #e0e7ff;
  color: #4338ca;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
}

.empty-users {
  color: #6b7280;
  font-style: italic;
  text-align: center;
  padding: 20px;
}
</style>
