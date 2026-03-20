import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useAuthStore } from './auth'
import apiClient from '@/api/apiClient'

export interface VisibilityConfig {
  [feature: string]: string[]
}

/**
 * Gibt die Default-Visibility zurück
 */
function getDefaultVisibility(): VisibilityConfig {
  return {
    dashboard: ['user', 'editor', 'manager', 'owner', 'admin', 'superadmin'],
    materials: ['user', 'editor', 'manager', 'owner', 'admin', 'superadmin'],
    materials_create: ['editor', 'manager', 'owner', 'admin', 'superadmin'],
    materials_edit: ['owner', 'admin', 'superadmin'],
    materials_delete: ['owner', 'admin', 'superadmin'],
    activities: ['user', 'editor', 'manager', 'owner', 'admin', 'superadmin'],
    activities_create: ['editor', 'manager', 'owner', 'admin', 'superadmin'],
    activities_edit: ['manager', 'owner', 'admin', 'superadmin'],
    activities_delete: ['owner', 'admin', 'superadmin'],
    settings: ['editor', 'manager', 'owner', 'admin', 'superadmin'],
    admin_area: ['admin', 'superadmin'],
  }
}

export const usePermissionsStore = defineStore('permissions', () => {
  const authStore = useAuthStore()
  
  // State
  const visibility = ref<VisibilityConfig>(getDefaultVisibility())
  const loading = ref(false)
  const error = ref<string | null>(null)
  
  /**
   * Lädt die Visibility-Konfiguration für ein Department
   */
  async function loadVisibility(departmentId: string): Promise<void> {
    if (!departmentId) {
      return
    }
    
    try {
      loading.value = true
      error.value = null
      
      const response = await apiClient.get(`/api/departments/${departmentId}`, {
        timeout: 15000, // 15s – bei Timeout/Slow: Fallback zu Defaults
      })
      
      // Visibility aus permissions extrahieren (API liefert evtl. keine permissions)
      const visibilityData = response.data?.permissions?.visibility || {}
      visibility.value = Object.keys(visibilityData).length > 0 ? visibilityData : getDefaultVisibility()
    } catch (err: any) {
      console.error('Failed to load visibility config:', err)
      error.value = err.response?.data?.error || 'Fehler beim Laden der Sichtbarkeit'
      
      // Fallback zu Default-Werten
      visibility.value = getDefaultVisibility()
    } finally {
      loading.value = false
    }
  }
  
  /**
   * Prüft ob die aktuelle Rolle ein Feature sehen darf
   */
  function canSee(feature: string): boolean {
    const role = authStore.currentDepartmentRole
    
    if (!role) {
      return false
    }
    
    // Admin und Superadmin sehen ALLES
    if (role === 'admin' || role === 'superadmin') {
      return true
    }
    
    // Erlaubte Rollen für das Feature holen
    const allowedRoles = visibility.value[feature] || []
    
    return allowedRoles.includes(role)
  }
  
  /**
   * Cache leeren (beim Logout)
   */
  function clearCache(): void {
    visibility.value = getDefaultVisibility()
  }
  
  /**
   * Computed: Ist der aktuelle User ein Owner/Admin?
   */
  const isOwner = computed(() => {
    const role = authStore.currentDepartmentRole
    return ['owner', 'admin', 'superadmin'].includes(role)
  })
  
  return {
    // State
    visibility,
    loading,
    error,
    
    // Computed
    isOwner,
    
    // Actions
    loadVisibility,
    canSee,
    getDefaultVisibility,
    clearCache,
  }
})
