import { createRouter, createWebHistory } from 'vue-router'
import type { RouteLocationNormalized, RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usePermissionsStore } from '@/stores/permissions'
import { usePageHeadStore } from '@/stores/pageHead'
import { syncDocumentHead } from '@/composables/usePageHead'
import { getMainSiteOrigin, isAppOrigin } from '@/utils/appLoginUrl'

/**
 * route.meta: Titel/Description per vue-i18n (`router.meta.titles.*` / `descriptions.*`).
 * Ohne `descriptionKey` → `router.meta.routeDescriptionDefault`.
 */
function routeHead(titleKey: string, descriptionKey?: string) {
  const meta: { pageTitleKey: string; pageDescriptionKey?: string } = {
    pageTitleKey: `router.meta.titles.${titleKey}`,
  }
  if (descriptionKey) {
    meta.pageDescriptionKey = `router.meta.descriptions.${descriptionKey}`
  }
  return meta
}

const routes: RouteRecordRaw[] = [
  {
    path: '/i/:type/:code',
    name: 'PublicLookup',
    component: () => import('@/views/public/PublicMaterialView.vue'),
    meta: {
      requiresAuth: false,
      ...routeHead('publicLookup', 'publicLookup'),
    }
  },
  {
    path: '/open-from-qr',
    name: 'OpenFromQr',
    component: () => import('@/views/public/OpenFromQrView.vue'),
    meta: {
      requiresAuth: false,
      ...routeHead('openFromQr'),
    }
  },
  {
    path: '/',
    component: () => import('@/components/layout/PublicSiteLayout.vue'),
    meta: { requiresAuth: false },
    children: [
      {
        path: '',
        name: 'LandingHome',
        component: () => import('@/views/public/LandingHomeView.vue'),
        meta: {
          publicMarketing: true,
          requiresAuth: false,
          ...routeHead('landingHome'),
        }
      },
      {
        path: 'blog',
        name: 'Blog',
        component: () => import('@/views/public/BlogView.vue'),
        meta: {
          publicMarketing: true,
          requiresAuth: false,
          ...routeHead('blog'),
        }
      },
      {
        path: 'faq',
        name: 'Faq',
        component: () => import('@/views/public/FaqView.vue'),
        meta: {
          publicMarketing: true,
          requiresAuth: false,
          ...routeHead('faq'),
        }
      },
      {
        path: 'tos',
        name: 'Tos',
        component: () => import('@/views/public/TosView.vue'),
        meta: {
          publicMarketing: true,
          requiresAuth: false,
          ...routeHead('tos', 'tos'),
        }
      },
      {
        path: 'impressum',
        name: 'Impressum',
        component: () => import('@/views/public/ImpressumView.vue'),
        meta: {
          publicMarketing: true,
          requiresAuth: false,
          ...routeHead('impressum', 'impressum'),
        }
      },
      {
        path: 'datenschutz',
        redirect: () => ({ path: '/tos', hash: '#datenschutz' }),
      },
    ],
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/LoginView.vue'),
    meta: {
      requiresAuth: false,
      publicMarketing: true,
      ...routeHead('login'),
    }
  },
  {
    path: '/site-inhalt',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true, requiresSiteEditor: true },
    children: [
      {
        path: '',
        component: () => import('@/views/site/WebsiteContentLayout.vue'),
        children: [
          {
            path: '',
            redirect: { name: 'SitePageEditor', params: { slug: 'landing' } },
          },
          {
            path: 'allgemein',
            redirect: { name: 'SiteGeneralEditor', params: { tab: 'faq' } },
          },
          {
            path: 'allgemein/:tab',
            name: 'SiteGeneralEditor',
            component: () => import('@/views/site/SiteGeneralEditorView.vue'),
            meta: {
              requiresSiteEditor: true,
              ...routeHead('siteGeneralEditor'),
            },
          },
          {
            path: 'faq',
            redirect: { name: 'SiteGeneralEditor', params: { tab: 'faq' } },
          },
          {
            path: 'tos',
            redirect: { name: 'SiteGeneralEditor', params: { tab: 'tos' } },
          },
          {
            path: 'impressum',
            redirect: { name: 'SiteGeneralEditor', params: { tab: 'impressum' } },
          },
          {
            path: ':slug',
            name: 'SitePageEditor',
            component: () => import('@/views/site/SitePageEditorView.vue'),
            meta: {
              requiresSiteEditor: true,
              ...routeHead('sitePageEditor'),
            }
          },
        ],
      },
    ],
  },
  {
    path: '/verify',
    name: 'VerifyEmail',
    component: () => import('@/views/VerifyEmailView.vue'),
    meta: {
      requiresAuth: false,
      ...routeHead('verifyEmail'),
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
          ...routeHead('pendingAssignment'),
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
          ...routeHead('dashboard'),
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
              ...routeHead('globalAddresses'),
            }
          },
          {
            path: 'dashboard',
            name: 'AdminDashboard',
            component: () => import('@/views/DashboardView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              ...routeHead('dashboard'),
            }
          },
          {
            path: 'support-requests',
            name: 'AdminSupportRequests',
            component: () => import('@/views/SupportRequestsView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              ...routeHead('supportRequests'),
            }
          },
          {
            path: 'jobs',
            name: 'AdminJobs',
            component: () => import('@/views/JobsView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              ...routeHead('systemJobs'),
            }
          },
          {
            path: 'integrations',
            name: 'AdminIntegrations',
            component: () => import('@/views/settings/IntegrationsSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              ...routeHead('integrations'),
            }
          },
          {
            path: 'organisations',
            name: 'AdminSettingsOrganisations',
            component: () => import('@/views/settings/OrganisationsSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              ...routeHead('organisations'),
            }
          },
          {
            path: 'departments',
            name: 'AdminSettingsDepartments',
            component: () => import('@/views/settings/DepartmentsSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              ...routeHead('departments'),
            }
          },
          {
            path: 'users',
            name: 'AdminSettingsUsers',
            component: () => import('@/views/settings/AdminUsersSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              ...routeHead('usersAdmin'),
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
              ...routeHead('mailRoot'),
            },
            children: [
              {
                path: 'versand',
                name: 'AdminMailVersand',
                component: () => import('@/views/settings/MailTemplatesSettingsView.vue'),
                meta: {
                  requiredRoles: ['superadmin'],
                  ...routeHead('mailTemplates'),
                },
              },
              {
                path: 'einstellungen',
                name: 'AdminMailEinstellungen',
                component: () => import('@/views/mail/MailOutboundSettingsView.vue'),
                meta: {
                  requiredRoles: ['superadmin'],
                  ...routeHead('mailSettings'),
                },
              },
              {
                path: 'log',
                name: 'AdminMailLog',
                component: () => import('@/views/mail/MailSendLogView.vue'),
                meta: {
                  requiredRoles: ['superadmin'],
                  ...routeHead('mailLog'),
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
              ...routeHead('permissions'),
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
          ...routeHead('dashboard'),
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
              ...routeHead('globalAddresses'),
            }
          },
          {
            path: 'jobs',
            name: 'Jobs',
            component: () => import('@/views/JobsView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              ...routeHead('systemJobs'),
            }
          },
          {
            path: 'support-requests',
            name: 'SupportRequests',
            component: () => import('@/views/SupportRequestsView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              ...routeHead('supportRequests'),
            }
          },
          {
            path: 'organisations',
            name: 'DepartmentVerwaltungOrganisations',
            component: () => import('@/views/settings/OrganisationsSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              ...routeHead('organisations'),
            }
          },
          {
            path: 'departments',
            name: 'DepartmentVerwaltungDepartments',
            component: () => import('@/views/settings/DepartmentsSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              ...routeHead('departments'),
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
              ...routeHead('mailRoot'),
            },
            children: [
              {
                path: 'versand',
                name: 'DepartmentMailVersand',
                component: () => import('@/views/settings/MailTemplatesSettingsView.vue'),
                meta: {
                  requiredRoles: ['superadmin'],
                  ...routeHead('mailTemplates'),
                },
              },
              {
                path: 'einstellungen',
                name: 'DepartmentMailEinstellungen',
                component: () => import('@/views/mail/MailOutboundSettingsView.vue'),
                meta: {
                  requiredRoles: ['superadmin'],
                  ...routeHead('mailSettings'),
                },
              },
              {
                path: 'log',
                name: 'DepartmentMailLog',
                component: () => import('@/views/mail/MailSendLogView.vue'),
                meta: {
                  requiredRoles: ['superadmin'],
                  ...routeHead('mailLog'),
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
              ...routeHead('permissions'),
            }
          }
        ]
      },
      {
        path: 'activities',
        name: 'Activities',
        component: () => import('@/views/ActivitiesView.vue'),
        meta: {
          ...routeHead('activities'),
        },
        children: [
          {
            path: ':activityId',
            name: 'ActivityDetail',
            component: () => import('@/views/ActivitiesView.vue'),
            meta: {
              ...routeHead('activityDetail'),
            }
          },
          {
            path: ':activityId/:tab',
            name: 'ActivityDetailTab',
            component: () => import('@/views/ActivitiesView.vue'),
            meta: {
              ...routeHead('activityDetail'),
            }
          }
        ]
      },
      {
        path: 'materials',
        name: 'Materials',
        component: () => import('@/views/MaterialsView.vue'),
        meta: {
          ...routeHead('materials'),
        },
        children: [
          {
            path: 'alle',
            name: 'MaterialsTabAll',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              ...routeHead('materialsAll'),
            }
          },
          {
            path: 'kombos',
            name: 'MaterialsTabCombos',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              ...routeHead('materialsCombos'),
            }
          },
          {
            path: 'virtuelle-kombis',
            name: 'MaterialsTabVirtualCombos',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              ...routeHead('materialsVirtualCombos'),
            }
          },
          {
            path: 'verbrauchsmaterial',
            name: 'MaterialsTabConsumables',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              ...routeHead('materialsConsumables'),
            }
          },
          {
            path: 'esswaren',
            name: 'MaterialsTabFood',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              ...routeHead('materialsFood'),
            }
          },
          {
            path: 'regale',
            name: 'MaterialsTabStorage',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              ...routeHead('materialsStorage'),
            }
          },
          {
            path: ':materialId',
            name: 'MaterialDetail',
            component: () => import('@/views/MaterialsView.vue'),
            meta: {
              ...routeHead('materialDetail', 'materialDetail'),
            }
          }
        ]
      },
      {
        path: 'accounting',
        component: () => import('@/views/accounting/AccountingShellView.vue'),
        meta: {
          requiredRoles: ['matwart', 'depchef'],
          ...routeHead('accounting'),
        },
        children: [
          {
            path: '',
            name: 'AccountingOverview',
            component: () => import('@/views/accounting/AccountingOverviewView.vue'),
            meta: {
              requiredRoles: ['matwart', 'depchef'],
              ...routeHead('accounting'),
            },
          },
          {
            path: 'kostenstellen',
            name: 'AccountingCostCenters',
            component: () => import('@/views/accounting/AccountingCostCentersView.vue'),
            meta: {
              requiredRoles: ['matwart', 'depchef'],
              ...routeHead('accountingCostCenters'),
            },
          },
          {
            path: 'buchungen',
            name: 'AccountingBookings',
            component: () => import('@/views/accounting/AccountingBookingsView.vue'),
            meta: {
              requiredRoles: ['matwart', 'depchef'],
              ...routeHead('accountingBookings'),
            },
          },
          {
            path: 'materialkosten',
            name: 'AccountingMaterialCosts',
            component: () => import('@/views/accounting/AccountingMaterialCostsView.vue'),
            meta: {
              requiredRoles: ['matwart', 'depchef'],
              ...routeHead('accountingMaterialCosts'),
            },
          },
          {
            path: 'budget',
            name: 'AccountingBudget',
            component: () => import('@/views/accounting/AccountingBudgetView.vue'),
            meta: {
              requiredRoles: ['matwart', 'depchef'],
              ...routeHead('accountingBudget'),
            },
          },
        ],
      },
      {
        path: 'contacts',
        name: 'Contacts',
        component: () => import('@/views/ContactsView.vue'),
        meta: {
          ...routeHead('contacts'),
        },
        children: [
          {
            path: ':contactId',
            name: 'ContactDetail',
            component: () => import('@/views/ContactsView.vue'),
            meta: {
              ...routeHead('contactDetail'),
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
          ...routeHead('tasks'),
        },
        children: [
          {
            path: 'allgemein',
            name: 'TasksGeneral',
            component: () => import('@/views/TasksGeneralView.vue'),
            meta: {
              ...routeHead('tasksGeneral'),
            },
          },
          {
            path: 'druck',
            name: 'TasksPrint',
            component: () => import('@/views/TasksPrintView.vue'),
            meta: {
              ...routeHead('tasksPrint'),
            },
          },
        ],
      },
      {
        path: 'notifications',
        name: 'NotificationsCenter',
        component: () => import('@/views/NotificationsCenterView.vue'),
        meta: {
          ...routeHead('notificationsCenter'),
        }
      },
      {
        path: 'workshop',
        name: 'Workshop',
        component: () => import('@/views/WorkshopView.vue'),
        meta: {
          ...routeHead('workshop'),
        }
      },
      {
        path: 'statistics',
        name: 'Statistics',
        component: () => import('@/views/StatisticsView.vue'),
        meta: {
          ...routeHead('statistics'),
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
              ...routeHead('settingsTime'),
            }
          },
          {
            path: 'categories',
            name: 'SettingsCategories',
            component: () => import('@/views/settings/CategoriesSettingsView.vue'),
            meta: {
              ...routeHead('settingsCategories'),
            }
          },
          {
            path: 'my-department',
            name: 'SettingsMyDepartment',
            component: () => import('@/views/settings/MyDepartmentSettingsView.vue'),
            meta: {
              ...routeHead('settingsMyDepartment'),
            }
          },
          {
            path: 'my-department/join-code',
            name: 'SettingsMyDepartmentJoinCode',
            component: () => import('@/views/settings/MyDepartmentJoinCodeView.vue'),
            meta: {
              ...routeHead('settingsJoinCode'),
            }
          },
          {
            path: 'my-department/storage-locations',
            name: 'SettingsMyDepartmentStorageLocations',
            component: () => import('@/views/settings/MyDepartmentAddressSettingsView.vue'),
            meta: {
              ...routeHead('settingsStorageLocations'),
              addressKind: 'storage',
            }
          },
          {
            path: 'my-department/billing-address',
            name: 'SettingsMyDepartmentBillingAddress',
            component: () => import('@/views/settings/MyDepartmentAddressSettingsView.vue'),
            meta: {
              ...routeHead('settingsBillingAddress'),
              addressKind: 'billing',
            }
          },
          {
            path: 'my-department/public-material-page',
            name: 'SettingsMyDepartmentPublicMaterialPage',
            component: () => import('@/views/settings/MyDepartmentPublicMaterialPageView.vue'),
            meta: {
              ...routeHead('settingsPublicMaterialPage'),
            }
          },
          {
            path: 'addons',
            name: 'SettingsAddons',
            component: () => import('@/views/settings/AddonsSettingsView.vue'),
            meta: {
              ...routeHead('settingsAddons'),
            }
          },
          {
            path: 'users',
            name: 'SettingsUsers',
            component: () => import('@/views/settings/UsersSettingsView.vue'),
            meta: {
              ...routeHead('settingsUsers'),
            }
          },
          {
            path: 'groups',
            name: 'SettingsGroups',
            component: () => import('@/views/settings/GroupsSettingsView.vue'),
            meta: {
              ...routeHead('settingsGroups'),
            }
          },
          {
            path: 'activities',
            name: 'SettingsActivities',
            component: () => import('@/views/settings/ActivitySettingsView.vue'),
            meta: {
              ...routeHead('settingsActivities'),
            }
          },
          {
            path: 'storage',
            name: 'SettingsStorage',
            component: () => import('@/views/settings/StorageSettingsView.vue'),
            meta: {
              ...routeHead('settingsStorage'),
            }
          },
          {
            path: 'templates',
            name: 'SettingsTemplates',
            component: () => import('@/views/settings/TemplatesSettingsView.vue'),
            meta: {
              ...routeHead('settingsTemplates'),
            }
          }
        ]
      }
    ]
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, _from, savedPosition) {
    if (to.hash) {
      return { el: to.hash, behavior: 'smooth' }
    }
    if (savedPosition) {
      return savedPosition
    }
    return { top: 0 }
  },
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

  // Öffentlicher /i/m|b/…-Lookup: auf App-Instanz weiterleiten (OpenFromQr-Logik)
  const parts = path.split('/').filter(Boolean)
  if (parts[0] === 'i' && (parts[1] === 'm' || parts[1] === 'b') && parts[2] && appOrigin) {
    const code = parts[2]
    const type = parts[1]
    const sp = new URLSearchParams()
    sp.set('type', type)
    sp.set('code', code)
    for (const [key, val] of Object.entries(to.query)) {
      if (key === 'type' || key === 'code') continue
      if (Array.isArray(val)) val.forEach((x) => sp.append(key, String(x)))
      else if (val != null) sp.append(key, String(val))
    }
    window.location.replace(`${appOrigin}/open-from-qr?${sp.toString()}`)
    return true
  }

  // Login-Start → App-Instanz (Query z. B. ?redirect= bleibt erhalten)
  if ((path === '/' || path === '/login') && appOrigin) {
    window.location.replace(`${appOrigin}${to.fullPath}`)
    return true
  }

  // Rechtstexte & Marketing auf der Hauptdomain
  if (['/impressum', '/tos', '/blog', '/faq'].includes(path)) {
    window.location.replace(`${mainSite}${path}${to.hash || ''}`)
    return true
  }
  if (path === '/datenschutz') {
    window.location.replace(`${mainSite}/tos#datenschutz`)
    return true
  }

  return false
}

// Navigation Guard
router.beforeEach(async (to, from, next) => {
  if (applyQrHostRedirects(to)) {
    return next(false)
  }

  const authStore = useAuthStore()
  const permissionsStore = usePermissionsStore()
  const mainSiteOrigin = getMainSiteOrigin()
  const isSuperAdmin = () => {
    const userRoles = authStore.userRoles || []
    return userRoles.includes('ROLE_SUPERADMIN')
  }
  const isWebAdmin = () => {
    const userRoles = authStore.userRoles || []
    return userRoles.includes('ROLE_WEBADMIN')
  }
  const canEditPublicSite = () => isSuperAdmin() || isWebAdmin()

  // App-Origin: nur Login — Marketing unter Hauptdomain
  if (isAppOrigin() && mainSiteOrigin) {
    if (to.meta.publicMarketing && to.path !== '/login') {
      if (to.path === '/') {
        if (!authStore.isLoggedIn) {
          return next({ path: '/login', query: to.query })
        }
      } else {
        window.location.replace(mainSiteOrigin + to.fullPath)
        return next(false)
      }
    }
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
          if (to.path !== '/login') {
            return next('/login')
          }
        }
      } catch (error) {
        console.error('Session loading failed:', error)
        localStorage.removeItem('auth_token')
        localStorage.removeItem('refresh_token')
        localStorage.removeItem('user_id')
        localStorage.removeItem('profile_id')
        localStorage.removeItem('session_last_activity_at')
        // Wenn Session-Laden fehlschlägt, zur Login-Seite
        if (to.path !== '/login') {
          return next('/login')
        }
      }
    }
  }

  // Auth-Requirement prüfen
  if (to.meta.requiresAuth && !authStore.isLoggedIn) {
    return next(`/login?redirect=${encodeURIComponent(to.fullPath)}`)
  }

  if (to.meta.requiresSiteEditor && !canEditPublicSite()) {
    const id = authStore.activeDepartmentId || authStore.departments[0]?.department_id
    return next(id ? `/${id}` : '/dashboard')
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
        const siteEditorRoute = to.matched.some((r) => r.meta.requiresSiteEditor)
        if (siteEditorRoute && canEditPublicSite()) {
          /* Webseiten-Editor ohne Abteilung (z. B. Superadmin) */
        } else if (to.meta.requiresAuth || to.path === '/login') {
          return next('/pending-assignment')
        }
      }
    }

    // Wenn Route /app/* ist oder geschützte Department-Route ohne Department-ID: primäre Department-ID verwenden
    // Admin-Dashboard (/admin-dashboard) darf NICHT mit Department-ID versehen werden
    const isAdminPath = to.path.startsWith('/admin-dashboard')
    if (
      !isAdminPath &&
      !to.path.startsWith('/site-inhalt') &&
      (to.path.startsWith('/app/') ||
        (to.meta.requiresAuth && !to.params.departmentId && to.path !== '/pending-assignment'))
    ) {
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

    // App-Login / App-Root: eingeloggt → Abteilung oder Dashboard (Hauptdomain-„/“ bleibt Landing)
    const appLoginOrRoot = (isAppOrigin() && to.path === '/') || to.path === '/login'
    if (appLoginOrRoot && isSuperAdmin()) {
      if (primaryDepartmentId) {
        return next(`/${primaryDepartmentId}`)
      }
      return next('/dashboard')
    }

    if (appLoginOrRoot && primaryDepartmentId) {
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
      return next('/login')
    }
  }

  next()
})

router.afterEach((to) => {
  usePageHeadStore().clearDynamic()
  syncDocumentHead(to)
})

export default router
