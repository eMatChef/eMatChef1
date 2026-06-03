import 'vue-router'

declare module 'proj4leaflet'

declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, any>
  export default component
}

declare module 'vue-router' {
  interface RouteMeta {
    /** vue-i18n-Key, z. B. `router.meta.titles.login` */
    pageTitleKey?: string
    /** vue-i18n-Key; fehlt → `router.meta.routeDescriptionDefault` */
    pageDescriptionKey?: string
    requiresAuth?: boolean
    /** Öffentliche Start-/Infoseiten (Hauptdomain); auf App-Origin ggf. zur Hauptdomain oder Login umleiten */
    publicMarketing?: boolean
    /** Nur ROLE_SUPERADMIN / ROLE_WEBADMIN */
    requiresSiteEditor?: boolean
    /** @deprecated Nur für entfernte Dev-Routen; Meta kann in alten Branches noch vorkommen */
    devToolsOnly?: boolean
    requiredRoles?: string[]
    /** Department-Mitgliedschaftsrollen, die diese Route nicht öffnen dürfen (z. B. `u` für Werkstatt) */
    denyDepartmentRoles?: string[]
    /** Bei denyDepartmentRoles: Ziel-Route statt Dashboard (z. B. TasksGeneral) */
    denyRedirectTo?: { name: string }
  }
}
