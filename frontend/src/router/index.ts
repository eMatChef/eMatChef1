import { createRouter, createWebHistory } from 'vue-router'
import type { RouteLocationNormalized, RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usePermissionsStore } from '@/stores/permissions'
import { usePageHeadStore } from '@/stores/pageHead'
import { syncDocumentHead } from '@/composables/usePageHead'

/** Standard-Beschreibung für route.meta (SEO / Open Graph) */
const PAGE_DESC = 'eMatChef – Materialverwaltung für Vermietungen.'

const routes: RouteRecordRaw[] = [
  {
    path: '/i/:type/:code',
    name: 'PublicLookup',
    component: () => import('@/views/public/PublicMaterialView.vue'),
    meta: {
      requiresAuth: false,
      pageTitle: 'Material-Info · eMatChef',
      pageDescription: 'Öffentliche Material- und Seriennummern-Informationen in eMatChef.',
    }
  },
  {
    path: '/impressum',
    name: 'Impressum',
    component: () => import('@/views/public/ImpressumView.vue'),
    meta: {
      requiresAuth: false,
      pageTitle: 'Impressum · eMatChef',
      pageDescription: 'Impressum und Anbieterkennzeichnung für eMatChef – Materialverwaltung.',
    }
  },
  {
    path: '/datenschutz',
    name: 'Datenschutz',
    component: () => import('@/views/public/DatenschutzView.vue'),
    meta: {
      requiresAuth: false,
      pageTitle: 'Datenschutz · eMatChef',
      pageDescription: 'Datenschutzhinweise für die Nutzung von eMatChef.',
    }
  },
  {
    path: '/',
    name: 'Login',
    alias: ['/login'],
    component: () => import('@/views/LoginView.vue'),
    meta: {
      requiresAuth: false,
      pageTitle: 'Anmelden · eMatChef',
      pageDescription: PAGE_DESC,
    }
  },
  {
    path: '/verify',
    name: 'VerifyEmail',
    component: () => import('@/views/VerifyEmailView.vue'),
    meta: {
      requiresAuth: false,
      pageTitle: 'E-Mail bestätigen · eMatChef',
      pageDescription: PAGE_DESC,
    }
  },
  {
    path: '/pending-assignment',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'PendingAssignment',
        component: () => import('@/views/PendingAssignmentView.vue'),
        meta: {
          pageTitle: 'Abteilung zuweisen · eMatChef',
          pageDescription: PAGE_DESC,
        }
      }
    ]
  },
  /** Superadmin ohne Department: Home ohne Verwaltungs-Subnavigation (nicht /admin-dashboard/verwaltung/…) */
  {
    path: '/dashboard',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'SuperadminHomeDashboard',
        component: () => import('@/views/DashboardView.vue'),
        meta: {
          pageTitle: 'Dashboard · eMatChef',
          pageDescription: PAGE_DESC,
        }
      }
    ]
  },
  {
    path: '/admin-dashboard',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true, requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'] },
    children: [
      {
        path: 'verwaltung',
        alias: '',
        component: () => import('@/views/VerwaltungView.vue'),
        children: [
          {
            path: '',
            name: 'AdminGlobalAddresses',
            component: () => import('@/views/GlobalAddressesView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              pageTitle: 'Globale Adressen · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'dashboard',
            name: 'AdminDashboard',
            component: () => import('@/views/DashboardView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              pageTitle: 'Dashboard · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'support-requests',
            name: 'AdminSupportRequests',
            component: () => import('@/views/SupportRequestsView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              pageTitle: 'Support-Anfragen · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'jobs',
            name: 'AdminJobs',
            component: () => import('@/views/JobsView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              pageTitle: 'System-Jobs · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'organisations',
            name: 'AdminSettingsOrganisations',
            component: () => import('@/views/settings/OrganisationsSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              pageTitle: 'Organisationen · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'departments',
            name: 'AdminSettingsDepartments',
            component: () => import('@/views/settings/DepartmentsSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              pageTitle: 'Abteilungen · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'users',
            name: 'AdminSettingsUsers',
            component: () => import('@/views/settings/AdminUsersSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              pageTitle: 'Benutzer · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'mail-templates',
            redirect: (to) => ({ path: to.path.replace(/\/mail-templates\/?$/, '/mail/versand') }),
          },
          {
            path: 'mail',
            component: () => import('@/views/mail/MailVerwaltungLayout.vue'),
            redirect: { name: 'AdminMailVersand' },
            meta: {
              requiredRoles: ['superadmin'],
              pageTitle: 'E-Mail · eMatChef',
              pageDescription: PAGE_DESC,
            },
            children: [
              {
                path: 'versand',
                name: 'AdminMailVersand',
                component: () => import('@/views/settings/MailTemplatesSettingsView.vue'),
                meta: {
                  requiredRoles: ['superadmin'],
                  pageTitle: 'E-Mail · Vorlagen · eMatChef',
                  pageDescription: PAGE_DESC,
                },
              },
              {
                path: 'einstellungen',
                name: 'AdminMailEinstellungen',
                component: () => import('@/views/mail/MailOutboundSettingsView.vue'),
                meta: {
                  requiredRoles: ['superadmin'],
                  pageTitle: 'E-Mail · Einstellungen · eMatChef',
                  pageDescription: PAGE_DESC,
                },
              },
              {
                path: 'log',
                name: 'AdminMailLog',
                component: () => import('@/views/mail/MailSendLogView.vue'),
                meta: {
                  requiredRoles: ['superadmin'],
                  pageTitle: 'E-Mail · Log · eMatChef',
                  pageDescription: PAGE_DESC,
                },
              },
            ],
          },
          {
            path: 'permissions',
            name: 'AdminVerwaltungPermissions',
            component: () => import('@/views/settings/PermissionsSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              pageTitle: 'Berechtigungen · eMatChef',
              pageDescription: PAGE_DESC,
            }
          }
        ]
      }
    ]
  },
  {
    path: '/:departmentId',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        alias: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/DashboardView.vue'),
        meta: {
          pageTitle: 'Dashboard · eMatChef',
          pageDescription: PAGE_DESC,
        }
      },
      {
        path: 'verwaltung',
        component: () => import('@/views/VerwaltungView.vue'),
        children: [
          {
            path: '',
            name: 'DepartmentGlobalAddresses',
            component: () => import('@/views/GlobalAddressesView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              pageTitle: 'Globale Adressen · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'jobs',
            name: 'Jobs',
            component: () => import('@/views/JobsView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              pageTitle: 'System-Jobs · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'support-requests',
            name: 'SupportRequests',
            component: () => import('@/views/SupportRequestsView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              pageTitle: 'Support-Anfragen · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'organisations',
            name: 'DepartmentVerwaltungOrganisations',
            component: () => import('@/views/settings/OrganisationsSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              pageTitle: 'Organisationen · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'departments',
            name: 'DepartmentVerwaltungDepartments',
            component: () => import('@/views/settings/DepartmentsSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              pageTitle: 'Abteilungen · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'mail-templates',
            redirect: (to) => ({ path: to.path.replace(/\/mail-templates\/?$/, '/mail/versand') }),
          },
          {
            path: 'mail',
            component: () => import('@/views/mail/MailVerwaltungLayout.vue'),
            redirect: { name: 'DepartmentMailVersand' },
            meta: {
              requiredRoles: ['superadmin'],
              pageTitle: 'E-Mail · eMatChef',
              pageDescription: PAGE_DESC,
            },
            children: [
              {
                path: 'versand',
                name: 'DepartmentMailVersand',
                component: () => import('@/views/settings/MailTemplatesSettingsView.vue'),
                meta: {
                  requiredRoles: ['superadmin'],
                  pageTitle: 'E-Mail · Vorlagen · eMatChef',
                  pageDescription: PAGE_DESC,
                },
              },
              {
                path: 'einstellungen',
                name: 'DepartmentMailEinstellungen',
                component: () => import('@/views/mail/MailOutboundSettingsView.vue'),
                meta: {
                  requiredRoles: ['superadmin'],
                  pageTitle: 'E-Mail · Einstellungen · eMatChef',
                  pageDescription: PAGE_DESC,
                },
              },
              {
                path: 'log',
                name: 'DepartmentMailLog',
                component: () => import('@/views/mail/MailSendLogView.vue'),
                meta: {
                  requiredRoles: ['superadmin'],
                  pageTitle: 'E-Mail · Log · eMatChef',
                  pageDescription: PAGE_DESC,
                },
              },
            ],
          },
          {
            path: 'permissions',
            name: 'DepartmentVerwaltungPermissions',
            component: () => import('@/views/settings/PermissionsSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              pageTitle: 'Berechtigungen · eMatChef',
              pageDescription: PAGE_DESC,
            }
          }
        ]
      },
      {
        path: 'activities',
        name: 'Activities',
        component: () => import('@/views/ActivitiesView.vue'),
        meta: {
          pageTitle: 'Aktivitäten · eMatChef',
          pageDescription: PAGE_DESC,
        },
        children: [
          {
            path: ':activityId',
            name: 'ActivityDetail',
            component: () => import('@/views/ActivitiesView.vue'),
            meta: {
              pageTitle: 'Aktivität · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: ':activityId/:tab',
            name: 'ActivityDetailTab',
            component: () => import('@/views/ActivitiesView.vue'),
            meta: {
              pageTitle: 'Aktivität · eMatChef',
              pageDescription: PAGE_DESC,
            }
          }
        ]
      },
      {
        path: 'materials',
        name: 'Materials',
        component: () => import('@/views/MaterialsView.vue'),
        meta: {
          pageTitle: 'Materialien · eMatChef',
          pageDescription: PAGE_DESC,
        },
        children: [
          {
            path: 'alle',
            name: 'MaterialsTabAll',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              pageTitle: 'Alle Materialien · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'kombos',
            name: 'MaterialsTabCombos',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              pageTitle: 'Kombos · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'virtuelle-kombis',
            name: 'MaterialsTabVirtualCombos',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              pageTitle: 'Virtuelle Kombis · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'verbrauchsmaterial',
            name: 'MaterialsTabConsumables',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              pageTitle: 'Verbrauchsmaterial · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'esswaren',
            name: 'MaterialsTabFood',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              pageTitle: 'Esswaren · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'regale',
            name: 'MaterialsTabStorage',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              pageTitle: 'Regale · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: ':materialId',
            name: 'MaterialDetail',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              pageTitle: 'Material · eMatChef',
              pageDescription: 'Materialdetails in eMatChef.',
            }
          }
        ]
      },
      {
        path: 'contacts',
        name: 'Contacts',
        component: () => import('@/views/ContactsView.vue'),
        meta: {
          pageTitle: 'Ansprechpartner · eMatChef',
          pageDescription: PAGE_DESC,
        },
        children: [
          {
            path: ':contactId',
            name: 'ContactDetail',
            component: () => import('@/views/ContactsView.vue'),
            meta: {
              pageTitle: 'Kontakt · eMatChef',
              pageDescription: PAGE_DESC,
            }
          }
        ]
      },
      {
        path: 'tasks',
        name: 'Tasks',
        component: () => import('@/views/TasksShellView.vue'),
        redirect: { name: 'TasksGeneral' },
        meta: {
          pageTitle: 'Aufgaben · eMatChef',
          pageDescription: PAGE_DESC,
        },
        children: [
          {
            path: 'allgemein',
            name: 'TasksGeneral',
            component: () => import('@/views/TasksGeneralView.vue'),
            meta: {
              pageTitle: 'Aufgaben · Allgemein · eMatChef',
              pageDescription: PAGE_DESC,
            },
          },
          {
            path: 'druck',
            name: 'TasksPrint',
            component: () => import('@/views/TasksPrintView.vue'),
            meta: {
              pageTitle: 'Aufgaben · Drucken · eMatChef',
              pageDescription: PAGE_DESC,
            },
          },
        ],
      },
      {
        path: 'notifications',
        name: 'NotificationsCenter',
        component: () => import('@/views/NotificationsCenterView.vue'),
        meta: {
          pageTitle: 'Nachrichtenzentrale · eMatChef',
          pageDescription: PAGE_DESC,
        }
      },
      {
        path: 'workshop',
        name: 'Workshop',
        component: () => import('@/views/WorkshopView.vue'),
        meta: {
          pageTitle: 'Workshop · eMatChef',
          pageDescription: PAGE_DESC,
        }
      },
      {
        path: 'statistics',
        name: 'Statistics',
        component: () => import('@/views/StatisticsView.vue'),
        meta: {
          pageTitle: 'Statistik · eMatChef',
          pageDescription: PAGE_DESC,
        }
      },
      {
        path: 'settings',
        component: () => import('@/views/SettingsView.vue'),
        children: [
          {
            path: '',
            redirect: { name: 'SettingsMyDepartment' },
          },
          {
            path: 'general',
            redirect: { name: 'SettingsZeit' },
          },
          {
            path: 'zeit',
            name: 'SettingsZeit',
            component: () => import('@/views/settings/GeneralSettingsView.vue'),
            meta: {
              pageTitle: 'Einstellungen · Zeit/Ort · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'categories',
            name: 'SettingsCategories',
            component: () => import('@/views/settings/CategoriesSettingsView.vue'),
            meta: {
              pageTitle: 'Einstellungen · Kategorien · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'my-department',
            name: 'SettingsMyDepartment',
            component: () => import('@/views/settings/MyDepartmentSettingsView.vue'),
            meta: {
              pageTitle: 'Einstellungen · Mein Department · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'users',
            name: 'SettingsUsers',
            component: () => import('@/views/settings/UsersSettingsView.vue'),
            meta: {
              pageTitle: 'Einstellungen · Benutzer · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'groups',
            name: 'SettingsGroups',
            component: () => import('@/views/settings/GroupsSettingsView.vue'),
            meta: {
              pageTitle: 'Einstellungen · Gruppen · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'activities',
            name: 'SettingsActivities',
            component: () => import('@/views/settings/ActivitySettingsView.vue'),
            meta: {
              pageTitle: 'Einstellungen · Aktivitäten · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'storage',
            name: 'SettingsStorage',
            component: () => import('@/views/settings/StorageSettingsView.vue'),
            meta: {
              pageTitle: 'Einstellungen · Lager · eMatChef',
              pageDescription: PAGE_DESC,
            }
          },
          {
            path: 'templates',
            name: 'SettingsTemplates',
            component: () => import('@/views/settings/TemplatesSettingsView.vue'),
            meta: {
              pageTitle: 'Einstellungen · Vorlagen · eMatChef',
              pageDescription: PAGE_DESC,
            }
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

/**
 * QR-Subdomain: öffentliche Links bleiben unter /i/…, Login & Rechtstexte auf anderen Origins.
 * Konfiguration: VITE_QR_PUBLIC_HOST, VITE_APP_ORIGIN, VITE_MAIN_SITE_ORIGIN (siehe .env.example).
 */
function applyQrHostRedirects(to: RouteLocationNormalized): boolean {
  const qrHost = (import.meta.env.VITE_QR_PUBLIC_HOST || '').trim().toLowerCase()
  const appOrigin = (import.meta.env.VITE_APP_ORIGIN || '').trim().replace(/\/$/, '')
  const mainSite = (import.meta.env.VITE_MAIN_SITE_ORIGIN || 'https://ematchef.ch').trim().replace(/\/$/, '')

  if (!qrHost || typeof window === 'undefined') return false

  const host = window.location.hostname.toLowerCase()
  if (host !== qrHost) return false

  const path = to.path

  // Login-Start → App-Instanz (Query z. B. ?redirect= bleibt erhalten)
  if ((path === '/' || path === '/login') && appOrigin) {
    window.location.replace(`${appOrigin}${to.fullPath}`)
    return true
  }

  // Impressum & Datenschutz liegen auf der Hauptdomain (ematchef.ch)
  if (path === '/impressum' || path === '/datenschutz') {
    window.location.replace(`${mainSite}${path}`)
    return true
  }

  return false
}

// Navigation Guard
router.beforeEach(async (to, from, next) => {
  if (applyQrHostRedirects(to)) {
    return
  }

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

    // Superadmin-Home nur ohne Department; mit Department immer Abteilungs-Dashboard
    if (to.path === '/dashboard') {
      if (!isSuperAdmin()) {
        if (primaryDepartmentId) {
          return next(`/${primaryDepartmentId}`)
        }
        return next('/pending-assignment')
      }
      if (primaryDepartmentId) {
        return next(`/${primaryDepartmentId}`)
      }
    }

    // SA ohne Department: Admin-„Übersicht“ unter /verwaltung/dashboard → schlankes /dashboard
    if (
      isSuperAdmin() &&
      !primaryDepartmentId &&
      (to.path === '/admin-dashboard' || to.path === '/admin-dashboard/verwaltung/dashboard')
    ) {
      return next('/dashboard')
    }

    // User ohne Department werden auf Pending-Seite geleitet
    if (!primaryDepartmentId) {
      if (
        isSuperAdmin() &&
        (to.path.startsWith('/admin-dashboard') || to.path === '/dashboard')
      ) {
        // SA darf ohne Department im Admin-Bereich bzw. globalem Dashboard arbeiten
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

    // Superadmin: gleiches Dashboard wie alle; mit Department → /{id}
    if (to.path === '/' && isSuperAdmin()) {
      if (primaryDepartmentId) {
        return next(`/${primaryDepartmentId}`)
      }
      return next('/dashboard')
    }

    // Andere User: zu Dashboard mit primärer Department-ID
    if (to.path === '/' && primaryDepartmentId) {
      return next(`/${primaryDepartmentId}`)
    }

    // Wenn User inzwischen Department hat, Pending-Seite verlassen
    if (to.path === '/pending-assignment' && primaryDepartmentId) {
      return next(`/${primaryDepartmentId}`)
    }

    // SA ohne Department: Pending-Seite → globales Dashboard (kein Wartebereich wie neue Nutzer)
    if (to.path === '/pending-assignment' && !primaryDepartmentId && isSuperAdmin()) {
      return next('/dashboard')
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
        return next(`/${fallbackDept}`)
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
      // Keine Berechtigung - redirect zu Einstellungen (Standard: Mein Department)
      const deptId = to.params.departmentId || authStore.activeDepartmentId
      if (deptId) {
        return next(`/${deptId}/settings`)
      }
      return next('/')
    }
  }

  next()
})

router.afterEach((to) => {
  usePageHeadStore().clearDynamic()
  syncDocumentHead(to)
})

export default router
