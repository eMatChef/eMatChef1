import { createRouter, createWebHistory } from 'vue-router'
import type { NavigationGuardNext, RouteLocationNormalized, RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usePermissionsStore } from '@/stores/permissions'
import { usePageHeadStore } from '@/stores/pageHead'
import { syncDocumentHead } from '@/composables/usePageHead'
import { getMainSiteOrigin, isAppOrigin } from '@/utils/appLoginUrl'
import { shouldProbeUserSession } from '@/api/unauthorizedRedirect'
import {
  applyDevicesHostRedirects,
  canAccessDevicesWarehouse,
  getPinnedDepartmentId,
  isDevicesHost,
} from '@/utils/devicesHost'
import {
  DEPARTMENT_BASIC_MEMBER_ROLES,
  DEPARTMENT_MW_DC_ROLES,
  isDepartmentBasicMemberRole,
} from '@/composables/useDepartmentMemberRole'
import { isDevToolsEnvironment } from '@/utils/devEnvironmentBanner'

/** Routen-Sperre für Basissicht (u, l1–l3) — gleich wie früher nur «u». */
const DENY_BASIC_MEMBER_ROLES = [...DEPARTMENT_BASIC_MEMBER_ROLES]

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

function defaultSupplierPath(): string | null {
  const authStore = useAuthStore()
  const companies = authStore.activeSupplierCompanies
  if (companies.length === 0) return null
  const id = authStore.activeSupplierCompanyId || companies[0]?.id
  return id ? `/supplier/${id}/profile` : null
}

function hasSupplierCompanyAccess(companyId: string): boolean {
  const authStore = useAuthStore()
  return authStore.activeSupplierCompanies.some((c) => c.id === companyId)
}

function hasSupplierCatalogCapability(companyId: string): boolean {
  const authStore = useAuthStore()
  const company = authStore.activeSupplierCompanies.find((c) => c.id === companyId)
  return company?.capabilities?.includes('catalog') ?? false
}

function hasSupplierDeliveryCapability(companyId: string): boolean {
  const authStore = useAuthStore()
  const company = authStore.activeSupplierCompanies.find((c) => c.id === companyId)
  return company?.capabilities?.includes('delivery') ?? false
}

function hasSupplierTemplatesCapability(companyId: string): boolean {
  const authStore = useAuthStore()
  const company = authStore.activeSupplierCompanies.find((c) => c.id === companyId)
  return company?.capabilities?.includes('templates') ?? false
}

function hasSupplierRepairsCapability(companyId: string): boolean {
  const authStore = useAuthStore()
  const company = authStore.activeSupplierCompanies.find((c) => c.id === companyId)
  return company?.capabilities?.includes('repairs') ?? false
}

function devicesWarehouseRoleOk(departmentId: string): boolean {
  const authStore = useAuthStore()
  const userRoles = authStore.userRoles || []
  if (userRoles.includes('ROLE_SUPERADMIN')) return true
  const dept = authStore.departments.find((d) => d.department_id === departmentId)
  return canAccessDevicesWarehouse(dept?.role)
}

function devicesModeHostGuard(
  to: RouteLocationNormalized,
  _from: RouteLocationNormalized,
  next: NavigationGuardNext,
) {
  if (!to.meta.devicesMode) {
    return next()
  }
  if (!isDevicesHost()) {
    const deptId = String(to.params.departmentId || '')
    const activityId = String(to.params.activityId || '')
    if (activityId && deptId) {
      return next({
        name: 'ActivityDetailTab',
        params: { departmentId: deptId, activityId, tab: 'packs' },
        replace: true,
      })
    }
    if (deptId) {
      if (to.name === 'Dashboard') {
        return next()
      }
      return next({ name: 'Dashboard', params: { departmentId: deptId }, replace: true })
    }
    return next({ path: '/', replace: true })
  }
  const deptId = String(to.params.departmentId || '')
  if (deptId && !devicesWarehouseRoleOk(deptId)) {
    return next({ path: `/${deptId}/settings`, replace: true })
  }
  return next()
}

const routes: RouteRecordRaw[] = [
  {
    path: '/i/m/:matCode/b/:batchCode',
    name: 'PublicLookupMaterialBatch',
    component: () => import('@/views/public/PublicMaterialView.vue'),
    meta: {
      requiresAuth: false,
      ...routeHead('publicLookup', 'publicLookup'),
    },
  },
  {
    path: '/i/a/:activityCode',
    name: 'PublicLookupActivity',
    component: () => import('@/views/public/PublicActivityView.vue'),
    meta: {
      requiresAuth: false,
      ...routeHead('publicLookup', 'publicLookup'),
    },
  },
  {
    path: '/i/w/:workshopCode',
    name: 'PublicLookupWorkshop',
    component: () => import('@/views/public/PublicWorkshopView.vue'),
    meta: {
      requiresAuth: false,
      ...routeHead('publicLookup', 'publicLookup'),
    },
  },
  {
    path: '/i/m/:code',
    name: 'PublicLookupMaterialLegacy',
    component: () => import('@/views/public/PublicMaterialView.vue'),
    meta: {
      requiresAuth: false,
      ...routeHead('publicLookup', 'publicLookup'),
    },
  },
  {
    path: '/i/:type/:code',
    redirect: (to) => {
      const type = String(to.params.type || '').toLowerCase()
      const code = encodeURIComponent(String(to.params.code || ''))
      if (type === 'a' && code) return `/i/a/${code}`
      if (type === 'w' && code) return `/i/w/${code}`
      if (type === 'm' && code) return `/i/m/${code}`
      return '/'
    },
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
    path: '/display',
    name: 'PublicDisplayEntry',
    component: () => import('@/views/DisplayEntryView.vue'),
    meta: {
      requiresAuth: false,
      ...routeHead('displayEntry', 'displayEntry'),
    },
  },
  {
    path: '/display/:publicId',
    name: 'PublicDepartmentDisplay',
    component: () => import('@/views/DepartmentDisplayView.vue'),
    meta: {
      requiresAuth: false,
      ...routeHead('departmentDisplay', 'departmentDisplay'),
    },
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
        path: 'blog/:slug',
        name: 'BlogPost',
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
    path: '/sandbox',
    redirect: () => {
      const authStore = useAuthStore()
      const id =
        authStore.activeDepartmentId ||
        authStore.departments.find((d) => d.is_primary)?.department_id ||
        authStore.departments[0]?.department_id
      if (id) {
        return { name: 'DevUiPlayground', params: { departmentId: id } }
      }
      return { path: '/login', query: { redirect: '/sandbox' } }
    },
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
              requiredRoles: ['superadmin'],
              ...routeHead('globalAddresses'),
            }
          },
          {
            path: 'supplier-global-review',
            name: 'AdminSupplierGlobalReview',
            component: () => import('@/views/SupplierGlobalReviewView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              ...routeHead('supplierGlobalReview'),
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
            path: 'security-monitoring',
            name: 'AdminSecurityMonitoring',
            component: () => import('@/views/settings/SecurityMonitoringView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              ...routeHead('securityMonitoring'),
            }
          },
          {
            path: 'security-monitoring/alerts',
            name: 'AdminSecurityMonitoringAlerts',
            component: () => import('@/views/settings/SecurityMonitoringView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              ...routeHead('securityMonitoring'),
            }
          },
          {
            path: 'security-monitoring/settings',
            name: 'AdminSecurityMonitoringSettings',
            component: () => import('@/views/settings/SecurityMonitoringView.vue'),
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              ...routeHead('securityMonitoring'),
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
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
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
            path: 'global-admin-roles',
            name: 'AdminGlobalAdminRoles',
            component: () => import('@/views/settings/GlobalAdminRolesSettingsView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              ...routeHead('globalAdminRoles'),
            }
          },
          {
            path: 'user-org-overview',
            name: 'AdminUserOrgOverview',
            component: () => import('@/views/settings/UserOrgOverviewView.vue'),
            meta: {
              requiredRoles: ['superadmin'],
              ...routeHead('userOrgOverview'),
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
          },
          {
            path: 'templates',
            name: 'AdminGlobalTemplates',
            component: () => import('@/views/settings/TemplatesSettingsView.vue'),
            props: { mode: 'global-admin' },
            meta: {
              requiredRoles: ['superadmin', 'organisationschef', 'suborgchef'],
              ...routeHead('globalMaterialTemplates'),
            }
          }
        ]
      }
    ]
  },
  {
    path: '/supplier/:companyId',
    component: () => import('@/components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: (to) => ({
          name: 'SupplierProfile',
          params: { companyId: to.params.companyId },
        }),
      },
      {
        path: 'profile',
        name: 'SupplierProfile',
        component: () => import('@/views/supplier/SupplierProfileView.vue'),
        meta: {
          requiresSupplierAccess: true,
          ...routeHead('supplierProfile'),
        },
      },
      {
        path: 'team',
        name: 'SupplierTeam',
        component: () => import('@/views/supplier/SupplierTeamView.vue'),
        meta: {
          requiresSupplierAccess: true,
          requiresSupplierAdmin: true,
          ...routeHead('supplierTeam'),
        },
      },
      {
        path: 'catalog',
        name: 'SupplierCatalog',
        component: () => import('@/views/supplier/SupplierCatalogView.vue'),
        meta: {
          requiresSupplierAccess: true,
          requiresSupplierCatalog: true,
          ...routeHead('supplierCatalog'),
        },
      },
      {
        path: 'deliveries',
        name: 'SupplierDeliveries',
        component: () => import('@/views/supplier/SupplierDeliveriesView.vue'),
        meta: {
          requiresSupplierAccess: true,
          requiresSupplierDelivery: true,
          ...routeHead('supplierDeliveries'),
        },
      },
      {
        path: 'templates',
        name: 'SupplierTemplates',
        component: () => import('@/views/supplier/SupplierTemplatesView.vue'),
        meta: {
          requiresSupplierAccess: true,
          requiresSupplierTemplates: true,
          ...routeHead('supplierTemplates'),
        },
      },
      {
        path: 'repairs',
        name: 'SupplierRepairs',
        component: () => import('@/views/supplier/SupplierRepairsView.vue'),
        meta: {
          requiresSupplierAccess: true,
          requiresSupplierRepairs: true,
          ...routeHead('supplierRepairs'),
        },
      },
    ],
  },
  {
    path: '/:departmentId/pack/:activityId',
    name: 'DevicesPackSession',
    component: () => import('@/views/devices/DevicesPackSessionView.vue'),
    meta: {
      requiresAuth: true,
      devicesMode: true,
      ...routeHead('devicesPack', 'devicesPack'),
    },
    beforeEnter: devicesModeHostGuard,
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
              requiredRoles: ['superadmin'],
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
            path: ':activityId/packlist',
            redirect: (to) => ({
              name: 'ActivityDetailTab',
              params: {
                departmentId: to.params.departmentId,
                activityId: to.params.activityId,
                tab: 'packs',
              },
            }),
          },
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
        path: 'supplier-shop',
        name: 'SupplierShop',
        component: () => import('@/views/SupplierShopView.vue'),
        meta: {
          requiredRoles: ['matwart', 'depchef', 'mw', 'dc'],
          ...routeHead('supplierShop'),
        },
      },
      {
        path: 'accounting',
        component: () => import('@/views/accounting/AccountingShellView.vue'),
        meta: {
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
            path: 'gruppen',
            name: 'AccountingGroupCosts',
            component: () => import('@/views/accounting/AccountingGroupCostsView.vue'),
            meta: {
              requiredRoles: ['matwart', 'depchef', 'leader1', 'leader2', 'leader3', 'user'],
              ...routeHead('accountingGroupCosts'),
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
            path: 'abschreibung',
            name: 'AccountingAmortization',
            component: () => import('@/views/accounting/AccountingAmortizationView.vue'),
            meta: {
              requiredRoles: ['matwart', 'depchef'],
              ...routeHead('accountingAmortization'),
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
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'TasksGeneral' },
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
        path: 'search',
        name: 'GlobalSearch',
        component: () => import('@/views/GlobalSearchView.vue'),
        meta: {
          ...routeHead('globalSearch'),
        }
      },
      {
        path: 'dev/ui-playground',
        alias: 'sandbox',
        name: 'DevUiPlayground',
        component: () => import('@/views/dev/DevUiPlaygroundView.vue'),
        meta: {
          devToolsOnly: true,
          ...routeHead('devUiSandbox'),
        },
      },
      {
        path: 'workshop',
        name: 'Workshop',
        component: () => import('@/views/WorkshopView.vue'),
        meta: {
          ...routeHead('workshop'),
          denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
        }
      },
      {
        path: 'statistics',
        name: 'Statistics',
        component: () => import('@/views/StatisticsView.vue'),
        meta: {
          ...routeHead('statistics'),
          denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
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
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
            }
          },
          {
            path: 'categories',
            name: 'SettingsCategories',
            component: () => import('@/views/settings/CategoriesSettingsView.vue'),
            meta: {
              ...routeHead('settingsCategories'),
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
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
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
            }
          },
          {
            path: 'my-department/fixed-dates',
            name: 'SettingsMyDepartmentFixedDates',
            component: () => import('@/views/settings/MyDepartmentFixedDatesView.vue'),
            meta: {
              ...routeHead('settingsFixedDates'),
              requireDepartmentRoles: [...DEPARTMENT_MW_DC_ROLES],
              denyRedirectTo: { name: 'SettingsMyDepartment' },
            }
          },
          {
            path: 'my-department/display-screens',
            name: 'SettingsMyDepartmentDisplayScreens',
            component: () => import('@/views/settings/MyDepartmentDisplayScreensView.vue'),
            meta: {
              ...routeHead('settingsDisplayScreens'),
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
            }
          },
          {
            path: 'my-department/storage-locations',
            name: 'SettingsMyDepartmentStorageLocations',
            component: () => import('@/views/settings/MyDepartmentAddressSettingsView.vue'),
            meta: {
              ...routeHead('settingsStorageLocations'),
              addressKind: 'storage',
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
            }
          },
          {
            path: 'my-department/billing-address',
            name: 'SettingsMyDepartmentBillingAddress',
            component: () => import('@/views/settings/MyDepartmentAddressSettingsView.vue'),
            meta: {
              ...routeHead('settingsBillingAddress'),
              addressKind: 'billing',
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
            }
          },
          {
            path: 'my-department/public-material-page',
            name: 'SettingsMyDepartmentPublicMaterialPage',
            component: () => import('@/views/settings/MyDepartmentPublicMaterialPageView.vue'),
            meta: {
              ...routeHead('settingsPublicMaterialPage'),
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
            }
          },
          {
            path: 'addons',
            name: 'SettingsAddons',
            component: () => import('@/views/settings/AddonsSettingsView.vue'),
            meta: {
              ...routeHead('settingsAddons'),
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
            }
          },
          {
            path: 'users',
            name: 'SettingsUsers',
            component: () => import('@/views/settings/UsersSettingsView.vue'),
            meta: {
              ...routeHead('settingsUsers'),
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
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
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
            }
          },
          {
            path: 'storage',
            name: 'SettingsStorage',
            component: () => import('@/views/settings/StorageSettingsView.vue'),
            meta: {
              ...routeHead('settingsStorage'),
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
            }
          },
          {
            path: 'templates',
            name: 'SettingsTemplates',
            component: () => import('@/views/settings/TemplatesSettingsView.vue'),
            meta: {
              ...routeHead('settingsTemplates'),
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
            }
          },
          {
            path: 'material-import',
            name: 'SettingsMaterialImport',
            component: () => import('@/views/settings/MaterialImportSettingsView.vue'),
            meta: {
              ...routeHead('settingsMaterialImport'),
              denyDepartmentRoles: DENY_BASIC_MEMBER_ROLES,
              denyRedirectTo: { name: 'SettingsMyDepartment' },
            }
          },
          {
            path: 'supplier-deliveries',
            redirect: (to) => ({
              name: 'SupplierShop',
              params: { departmentId: to.params.departmentId },
              query: { tab: 'deliveries' },
            }),
          },
        ]
      },
      {
        path: 'help',
        component: () => import('@/views/HelpView.vue'),
        children: [
          {
            path: '',
            redirect: { name: 'HelpOverview' },
          },
          {
            path: 'overview',
            name: 'HelpOverview',
            component: () => import('@/views/help/HelpComingSoonView.vue'),
            meta: routeHead('helpOverview'),
          },
        ],
      },
    ]
  },
  /** Nach AppLayout: sonst fängt DevicesHome jedes /:departmentId auf der App-Domain ab (Redirect-Schleife). */
  {
    path: '/:departmentId',
    name: 'DevicesHome',
    component: () => import('@/views/devices/DevicesHomeView.vue'),
    meta: {
      requiresAuth: true,
      devicesMode: true,
      ...routeHead('devicesHome', 'devicesHome'),
    },
    beforeEnter: devicesModeHostGuard,
  },
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
  const mainSite = (import.meta.env.VITE_MAIN_SITE_ORIGIN || 'https://ematchef.ch').trim().replace(/\/$/, '')

  if (!qrHost || typeof window === 'undefined') return false

  const host = window.location.hostname.toLowerCase()
  if (host !== qrHost) return false

  const path = to.path

  // Öffentlicher /i/…-Lookup bleibt auf der QR-Domain (kein automatischer Sprung zur App).
  const parts = path.split('/').filter(Boolean)
  if (parts[0] === 'i') {
    if (parts[1] === 'm' && parts[2] && parts[3] === 'b' && parts[4]) return false
    if (parts[1] === 'a' && parts[2]) return false
    if (parts[1] === 'w' && parts[2]) return false
  }

  // Start & Login → Hauptdomain (ematchef.*), nicht app.*
  if ((path === '/' || path === '/login') && mainSite) {
    window.location.replace(`${mainSite}${to.fullPath}`)
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

/**
 * devices.-Subdomain: Rechtstexte auf Hauptdomain; Lager-Routen bleiben hier.
 */
function applyDevicesHostRouting(to: RouteLocationNormalized): boolean {
  if (!isDevicesHost() || typeof window === 'undefined') return false
  return applyDevicesHostRedirects(to.path)
}

// Navigation Guard
router.beforeEach(async (to, from, next) => {
  if (applyQrHostRedirects(to)) {
    return next(false)
  }
  if (applyDevicesHostRouting(to)) {
    return next(false)
  }

  // Infoscreen-Kiosk: kein App-Login, keine Session-Probe (Display-Cookie separat).
  if (!shouldProbeUserSession(to.path)) {
    return next()
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

  // Devices-Origin: Startseite ohne Login → Login (kein Marketing-Landing)
  if (isDevicesHost() && to.path === '/') {
    if (!authStore.isLoggedIn) {
      try {
        await authStore.loadUserSessionFromCookie()
      } catch {
        // Session-Cookie ungültig oder nicht vorhanden
      }
    }
    if (!authStore.isLoggedIn) {
      return next({ path: '/login', query: { redirect: to.fullPath } })
    }
  }

  // Geschützte Routen: Session über HttpOnly-Cookies (alle Subdomains)
  if (to.meta.requiresAuth && !authStore.isLoggedIn) {
    try {
      await authStore.loadUserSessionFromCookie()
    } catch {
      if (to.path !== '/login') {
        return next({ path: '/login', query: { redirect: to.fullPath } })
      }
    }
  }

  // Auth-Requirement prüfen
  if (to.meta.requiresAuth && !authStore.isLoggedIn) {
    if (to.path !== '/login') {
      return next({ path: '/login', query: { redirect: to.fullPath } })
    }
    return next()
  }

  if (to.meta.devToolsOnly && !isDevToolsEnvironment()) {
    if (authStore.isLoggedIn) {
      const id =
        authStore.activeDepartmentId ||
        authStore.departments.find((d) => d.is_primary)?.department_id ||
        authStore.departments[0]?.department_id
      return next(id ? `/${id}` : '/login')
    }
    return next({ path: '/login', query: { redirect: to.fullPath } })
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

    // User ohne Department werden auf Pending-Seite geleitet (Supplier-only → Supplier-Bereich)
    if (!primaryDepartmentId) {
      if (
        isSuperAdmin() &&
        (to.path.startsWith('/admin-dashboard') || to.path === '/dashboard')
      ) {
        // SA darf ohne Department im Admin-Bereich bzw. globalem Dashboard arbeiten
      } else if (to.path.startsWith('/supplier/')) {
        // Supplier-Routen — Zugriff unten geprüft
      } else if (authStore.isSupplierOnly && authStore.hasSupplierAccess) {
        const supplierHome = defaultSupplierPath()
        if (supplierHome && to.path !== supplierHome && !to.path.startsWith('/supplier/')) {
          const siteEditorRoute = to.matched.some((r) => r.meta.requiresSiteEditor)
          if (!(siteEditorRoute && canEditPublicSite())) {
            return next(supplierHome)
          }
        }
      } else if (to.path !== '/pending-assignment' && !to.meta.devToolsOnly) {
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
      !to.path.startsWith('/supplier/') &&
      (to.path.startsWith('/app/') ||
        (to.meta.requiresAuth &&
        !to.params.departmentId &&
        to.path !== '/pending-assignment' &&
        !to.meta.devToolsOnly))
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

    if (appLoginOrRoot && authStore.hasSupplierAccess) {
      const supplierHome = defaultSupplierPath()
      if (supplierHome) return next(supplierHome)
    }

    if (isDevicesHost() && (to.path === '/' || to.path === '/login')) {
      const pinned =
        getPinnedDepartmentId() ||
        primaryDepartmentId ||
        authStore.departments[0]?.department_id
      if (pinned) {
        return next({ name: 'DevicesHome', params: { departmentId: pinned }, replace: true })
      }
    }

    // Wenn User inzwischen Department hat, Pending-Seite verlassen
    if (to.path === '/pending-assignment' && primaryDepartmentId) {
      return next(`/${primaryDepartmentId}`)
    }

    // Supplier-only: Pending-Seite → Supplier-Bereich
    if (to.path === '/pending-assignment' && authStore.isSupplierOnly && authStore.hasSupplierAccess) {
      const supplierHome = defaultSupplierPath()
      if (supplierHome) return next(supplierHome)
    }

    // SA ohne Department: Pending-Seite → globales Dashboard (kein Wartebereich wie neue Nutzer)
    if (to.path === '/pending-assignment' && !primaryDepartmentId && isSuperAdmin()) {
      return next('/dashboard')
    }
  }

  if (
    isDevicesHost() &&
    authStore.isLoggedIn &&
    to.params.departmentId &&
    to.meta.requiresAuth &&
    !to.meta.devicesMode
  ) {
    return next({
      name: 'DevicesHome',
      params: { departmentId: String(to.params.departmentId) },
      replace: true,
    })
  }

  // Supplier-Bereich: Membership + optional Admin-Rolle
  if (to.path.startsWith('/supplier/') && authStore.isLoggedIn) {
    const companyId = String(to.params.companyId || '')
    if (!companyId || !hasSupplierCompanyAccess(companyId)) {
      const supplierHome = defaultSupplierPath()
      if (supplierHome) return next(supplierHome)
      return next('/pending-assignment')
    }
    if (authStore.activeSupplierCompanyId !== companyId) {
      authStore.setActiveSupplierCompany(companyId)
    }
    if (to.meta.requiresSupplierAdmin && !authStore.isSupplierCompanyAdmin(companyId)) {
      return next({ name: 'SupplierProfile', params: { companyId } })
    }
    if (to.meta.requiresSupplierCatalog && !hasSupplierCatalogCapability(companyId)) {
      return next({ name: 'SupplierProfile', params: { companyId } })
    }
    if (to.meta.requiresSupplierDelivery && !hasSupplierDeliveryCapability(companyId)) {
      return next({ name: 'SupplierProfile', params: { companyId } })
    }
    if (to.meta.requiresSupplierTemplates && !hasSupplierTemplatesCapability(companyId)) {
      return next({ name: 'SupplierProfile', params: { companyId } })
    }
    if (to.meta.requiresSupplierRepairs && !hasSupplierRepairsCapability(companyId)) {
      return next({ name: 'SupplierProfile', params: { companyId } })
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

  if (to.meta.requireDepartmentRoles && Array.isArray(to.meta.requireDepartmentRoles)) {
    const allowedRoles = (to.meta.requireDepartmentRoles as string[]).map((r) => r.toLowerCase())
    const currentRole = String(authStore.currentDepartmentRole || '').toLowerCase().trim()
    if (!allowedRoles.includes(currentRole)) {
      const deptId = to.params.departmentId || authStore.activeDepartmentId
      const denyRedirectTo = to.meta.denyRedirectTo as { name?: string } | undefined
      if (denyRedirectTo?.name && deptId) {
        return next({ name: denyRedirectTo.name, params: { departmentId: String(deptId) } })
      }
      if (deptId) {
        return next(`/${deptId}`)
      }
      return next('/login')
    }
  }

  // Department-Rollen, die diese Route nicht öffnen dürfen (z. B. Werkstatt für User)
  if (to.meta.denyDepartmentRoles && Array.isArray(to.meta.denyDepartmentRoles)) {
    const deniedRoles = to.meta.denyDepartmentRoles as string[]
    const currentRole = String(authStore.currentDepartmentRole || '').toLowerCase().trim()
    const isDenied = deniedRoles.some((role) => {
      const r = role.toLowerCase()
      if (currentRole === r) return true
      if ((r === 'u' || r === 'user') && isDepartmentBasicMemberRole(currentRole)) return true
      return false
    })
    if (isDenied) {
      const deptId = to.params.departmentId || authStore.activeDepartmentId
      const denyRedirectTo = to.meta.denyRedirectTo as { name?: string } | undefined
      if (denyRedirectTo?.name && deptId) {
        return next({ name: denyRedirectTo.name, params: { departmentId: String(deptId) } })
      }
      if (deptId) {
        return next(`/${deptId}`)
      }
      return next('/login')
    }
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
