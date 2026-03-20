import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usePermissionsStore } from '@/stores/permissions'

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'Login',
    component: () => import('@/views/LoginView.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/login',
    redirect: '/'
  },
  {
    path: '/verify',
    name: 'VerifyEmail',
    component: () => import('@/views/VerifyEmailView.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/pending-assignment',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'PendingAssignment',
        component: () => import('@/views/PendingAssignmentView.vue')
      }
    ]
  },
  {
    path: '/admin-dashboard',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true, requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'] },
    redirect: '/admin-dashboard/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'AdminDashboard',
        component: () => import('@/views/AdminDashboardView.vue'),
        meta: { requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'] }
      },
      {
        path: 'support-requests',
        name: 'AdminSupportRequests',
        component: () => import('@/views/SupportRequestsView.vue'),
        meta: { requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'] }
      },
      {
        path: 'jobs',
        name: 'AdminJobs',
        component: () => import('@/views/JobsView.vue'),
        meta: { requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'] }
      },
      {
        path: 'settings',
        component: () => import('@/views/SettingsView.vue'),
        redirect: '/admin-dashboard/settings/organisations',
        children: [
          {
            path: 'organisations',
            name: 'AdminSettingsOrganisations',
            component: () => import('@/views/settings/OrganisationsSettingsView.vue'),
            meta: { requiredRoles: ['superadmin'] }
          },
          {
            path: 'departments',
            name: 'AdminSettingsDepartments',
            component: () => import('@/views/settings/DepartmentsSettingsView.vue'),
            meta: { requiredRoles: ['superadmin'] }
          },
          {
            path: 'users',
            name: 'AdminSettingsUsers',
            component: () => import('@/views/settings/AdminUsersSettingsView.vue'),
            meta: { requiredRoles: ['superadmin'] }
          },
          {
            path: 'mail-templates',
            name: 'AdminSettingsMailTemplates',
            component: () => import('@/views/settings/MailTemplatesSettingsView.vue'),
            meta: { requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'] }
          }
        ]
      }
    ]
  },
  {
    path: '/:departmentId',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    redirect: (to) => `/${to.params.departmentId}/dashboard`,
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/DashboardView.vue')
      },
      {
        path: 'jobs',
        name: 'Jobs',
        component: () => import('@/views/JobsView.vue'),
        meta: { requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'] }
      },
      {
        path: 'support-requests',
        name: 'SupportRequests',
        component: () => import('@/views/SupportRequestsView.vue'),
        meta: { requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'] }
      },
      {
        path: 'activities',
        name: 'Activities',
        component: () => import('@/views/ActivitiesView.vue'),
        children: [
          {
            path: ':activityId',
            name: 'ActivityDetail',
            component: () => import('@/views/ActivitiesView.vue'),
          },
          {
            path: ':activityId/:tab',
            name: 'ActivityDetailTab',
            component: () => import('@/views/ActivitiesView.vue'),
          }
        ]
      },
      {
        path: 'materials',
        name: 'Materials',
        component: () => import('@/views/MaterialsView.vue'),
        children: [
          {
            path: 'alle',
            name: 'MaterialsTabAll',
            component: () => import('@/views/MaterialsView.vue')
          },
          {
            path: 'kombos',
            name: 'MaterialsTabCombos',
            component: () => import('@/views/MaterialsView.vue')
          },
          {
            path: 'virtuelle-kombis',
            name: 'MaterialsTabVirtualCombos',
            component: () => import('@/views/MaterialsView.vue')
          },
          {
            path: 'verbrauchsmaterial',
            name: 'MaterialsTabConsumables',
            component: () => import('@/views/MaterialsView.vue')
          },
          {
            path: 'esswaren',
            name: 'MaterialsTabFood',
            component: () => import('@/views/MaterialsView.vue')
          },
          {
            path: 'regale',
            name: 'MaterialsTabStorage',
            component: () => import('@/views/MaterialsView.vue')
          },
          {
            path: ':materialId',
            name: 'MaterialDetail',
            component: () => import('@/views/MaterialsView.vue')
          }
        ]
      },
      {
        path: 'contacts',
        name: 'Contacts',
        component: () => import('@/views/ContactsView.vue'),
        children: [
          {
            path: ':contactId',
            name: 'ContactDetail',
            component: () => import('@/views/ContactsView.vue')
          }
        ]
      },
      {
        path: 'tasks',
        name: 'Tasks',
        component: () => import('@/views/TasksView.vue')
      },
      {
        path: 'workshop',
        name: 'Workshop',
        component: () => import('@/views/WorkshopView.vue')
      },
      {
        path: 'statistics',
        name: 'Statistics',
        component: () => import('@/views/StatisticsView.vue')
      },
      {
        path: 'settings',
        component: () => import('@/views/SettingsView.vue'),
        redirect: (to) => `/${to.params.departmentId}/settings/general`,
        children: [
          {
            path: 'general',
            name: 'SettingsGeneral',
            component: () => import('@/views/settings/GeneralSettingsView.vue')
          },
          {
            path: 'categories',
            name: 'SettingsCategories',
            component: () => import('@/views/settings/CategoriesSettingsView.vue')
          },
          {
            path: 'my-department',
            name: 'SettingsMyDepartment',
            component: () => import('@/views/settings/MyDepartmentSettingsView.vue')
          },
          {
            path: 'departments',
            name: 'SettingsDepartments',
            component: () => import('@/views/settings/DepartmentsSettingsView.vue'),
            meta: { requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'] }
          },
          {
            path: 'organisations',
            name: 'SettingsOrganisations',
            component: () => import('@/views/settings/OrganisationsSettingsView.vue'),
            meta: { requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'] }
          },
          {
            path: 'users',
            name: 'SettingsUsers',
            component: () => import('@/views/settings/UsersSettingsView.vue')
          },
          {
            path: 'groups',
            name: 'SettingsGroups',
            component: () => import('@/views/settings/GroupsSettingsView.vue')
          },
          {
            path: 'permissions',
            name: 'SettingsPermissions',
            component: () => import('@/views/settings/PermissionsSettingsView.vue'),
            meta: { requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'] }
          },
          {
            path: 'activities',
            name: 'SettingsActivities',
            component: () => import('@/views/settings/ActivitySettingsView.vue')
          },
          {
            path: 'storage',
            name: 'SettingsStorage',
            component: () => import('@/views/settings/StorageSettingsView.vue')
          },
          {
            path: 'templates',
            name: 'SettingsTemplates',
            component: () => import('@/views/settings/TemplatesSettingsView.vue')
          },
          {
            path: 'mail-templates',
            name: 'SettingsMailTemplates',
            component: () => import('@/views/settings/MailTemplatesSettingsView.vue'),
            meta: { requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'] }
          }
        ]
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation Guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  const permissionsStore = usePermissionsStore()
  const isSuperAdmin = () => {
    const userRoles = authStore.userRoles || []
    return userRoles.includes('ROLE_SUPERADMIN')
  }

  // Token vorhanden?
  const token = localStorage.getItem('auth_token')
  if (token) {
    // Token im Store setzen (falls noch nicht gesetzt)
    if (!authStore.token) {
      authStore.token = token
    }
    
    // Session laden falls noch nicht geladen
    if (!authStore.isLoggedIn) {
      try {
        const loaded = await authStore.loadUserSession()
        if (!loaded) {
          // Session konnte nicht geladen werden
          if (to.path !== '/') {
            return next('/')
          }
        }
      } catch (error) {
        console.error('Session loading failed:', error)
        localStorage.removeItem('auth_token')
        localStorage.removeItem('refresh_token')
        localStorage.removeItem('user_id')
        localStorage.removeItem('profile_id')
        // Wenn Session-Laden fehlschlägt, zur Login-Seite
        if (to.path !== '/') {
          return next('/')
        }
      }
    }
  }

  // Auth-Requirement prüfen
  if (to.meta.requiresAuth && !authStore.isLoggedIn) {
    return next(`/?redirect=${encodeURIComponent(to.fullPath)}`)
  }

  // Falsche URL: /{departmentId}/admin-dashboard/... → /admin-dashboard/...
  if (to.params.departmentId && to.path.includes('/admin-dashboard')) {
    const correctPath = to.path.replace(/^\/[^/]+\/(admin-dashboard.*)/, '/$1')
    if (correctPath !== to.path) {
      return next(correctPath)
    }
  }

  // Wenn eingeloggt: immer primäre Department-ID verwenden
  if (authStore.isLoggedIn) {
    // Primäre Department-ID ermitteln
    let primaryDepartmentId = authStore.activeDepartmentId
    
    if (!primaryDepartmentId && authStore.departments.length > 0) {
      const primaryDept = authStore.departments.find(d => d.is_primary) || authStore.departments[0]
      if (primaryDept) {
        primaryDepartmentId = primaryDept.department_id
        authStore.setActiveDepartment(primaryDepartmentId)
      }
    }

    // User ohne Department werden auf Pending-Seite geleitet
    if (!primaryDepartmentId) {
      if (isSuperAdmin() && to.path.startsWith('/admin-dashboard')) {
        // SA darf ohne Department im Admin-Bereich arbeiten
      } else if (to.path !== '/pending-assignment') {
        if (to.meta.requiresAuth || to.path === '/') {
          return next('/pending-assignment')
        }
      }
    }

    // Wenn Route /app/* ist oder geschützte Department-Route ohne Department-ID: primäre Department-ID verwenden
    // Admin-Dashboard (/admin-dashboard) darf NICHT mit Department-ID versehen werden
    const isAdminPath = to.path.startsWith('/admin-dashboard')
    if (!isAdminPath && (to.path.startsWith('/app/') || (to.meta.requiresAuth && !to.params.departmentId && to.path !== '/pending-assignment'))) {
      if (primaryDepartmentId) {
        // Route mit primärer Department-ID ersetzen
        let newPath = to.path
        if (newPath.startsWith('/app/')) {
          newPath = newPath.replace('/app', `/${primaryDepartmentId}`)
        } else if (!newPath.startsWith(`/${primaryDepartmentId}`)) {
          newPath = `/${primaryDepartmentId}${newPath.startsWith('/') ? newPath : `/${newPath}`}`
        }
        return next(newPath)
      }
    }

    // SA landet immer zuerst im Admin-Dashboard (unabhängig von Department-Mitgliedschaft)
    if (to.path === '/' && isSuperAdmin()) {
      return next('/admin-dashboard/dashboard')
    }

    // Andere User: zu Dashboard mit primärer Department-ID
    if (to.path === '/' && primaryDepartmentId) {
      return next(`/${primaryDepartmentId}/dashboard`)
    }

    // Wenn User inzwischen Department hat, Pending-Seite verlassen
    if (to.path === '/pending-assignment' && primaryDepartmentId) {
      return next(`/${primaryDepartmentId}/dashboard`)
    }

    // SA ohne Department: Pending-Seite in Admin-Bereich umleiten
    if (to.path === '/pending-assignment' && !primaryDepartmentId && isSuperAdmin()) {
      return next('/admin-dashboard/dashboard')
    }
  }

  // Department-ID aus Route extrahieren
  if (to.params.departmentId && authStore.isLoggedIn) {
    const departmentId = to.params.departmentId as string
    const hasDepartmentAccess = authStore.departments.some(d => d.department_id === departmentId)
    if (!hasDepartmentAccess) {
      // Kein Zugriff auf fremdes Department
      const fallbackDept = authStore.activeDepartmentId || authStore.departments[0]?.department_id
      if (fallbackDept) {
        return next(`/${fallbackDept}/dashboard`)
      }
      return next('/pending-assignment')
    }
    
    // Department wechseln falls nötig
    if (authStore.activeDepartmentId !== departmentId) {
      authStore.setActiveDepartment(departmentId)
    }
    
    // Visibility für Department laden
    if (departmentId && departmentId !== 'login') {
      permissionsStore.loadVisibility(departmentId)
    }
  } else if (authStore.isLoggedIn && authStore.activeDepartmentId) {
    // Visibility für aktives Department laden
    permissionsStore.loadVisibility(authStore.activeDepartmentId)
  }

  // Rollen-basierte Zugriffskontrolle
  if (to.meta.requiredRoles && Array.isArray(to.meta.requiredRoles)) {
    const requiredRoles = to.meta.requiredRoles as string[]
    const currentRole = String(authStore.currentDepartmentRole || '').toLowerCase().trim()
    const userRoles = authStore.userRoles || []
    const roleAliasByRequired: Record<string, string[]> = {
      superadmin: ['sa'],
      organisationschef: ['org'],
      suborgchef: ['sub'],
      matwart: ['mw'],
      depchef: ['dc'],
      leader1: ['l1'],
      leader2: ['l2'],
      leader3: ['l3'],
      user: ['u']
    }
    
    // Prüfe ob User eine der erforderlichen Rollen hat
    const hasRequiredRole = requiredRoles.some(role => {
      const normalizedRequired = role.toLowerCase()
      // Prüfe currentDepartmentRole
      if (currentRole === normalizedRequired) return true
      if ((roleAliasByRequired[normalizedRequired] || []).includes(currentRole)) return true
      // Prüfe Symfony-Rollen
      const symfonyRole = `ROLE_${role.toUpperCase()}`
      if (userRoles.includes(symfonyRole)) return true
      return false
    })
    
    if (!hasRequiredRole) {
      // Keine Berechtigung - redirect zu Settings/General
      const deptId = to.params.departmentId || authStore.activeDepartmentId
      if (deptId) {
        return next(`/${deptId}/settings/general`)
      }
      return next('/')
    }
  }

  next()
})

export default router
