import { gaCanManageProcurement, gaCanSeeAnlassOverview, gaIsMailboxOnly } from '@/utils/grossanlassAccess'

export type GaEinstellungenTabId =
  | 'general'
  | 'units'
  | 'users'
  | 'locations'
  | 'categories'
  | 'inquiry-email'
  | 'participants'
  | 'approval'

export interface GaEinstellungenTab {
  id: GaEinstellungenTabId
  labelKey: string
  icon: string
  /** Vor der Freigabe der Ersteinrichtung sichtbar, aber nicht nutzbar. */
  locked: boolean
}

/** Bereiche der Ersteinrichtung: Stammdaten/GA-Typ, Ressorts/Bereiche, Benutzer/Mitgliedschaften. */
export const GA_SETUP_ACTIVE_TABS: ReadonlySet<GaEinstellungenTabId> = new Set(['general', 'units', 'users'])

const GUEST_TABS: ReadonlySet<GaEinstellungenTabId> = new Set(['participants', 'approval'])

const ALL_TABS: Array<Omit<GaEinstellungenTab, 'locked'>> = [
  { id: 'general', labelKey: 'grossanlass.planung.tabStammdaten', icon: 'mdi-card-account-details-outline' },
  { id: 'units', labelKey: 'grossanlass.planung.tabRessorts', icon: 'mdi-sitemap' },
  { id: 'users', labelKey: 'grossanlass.einstellungen.tabBenutzer', icon: 'mdi-account-multiple-outline' },
  { id: 'locations', labelKey: 'grossanlass.einstellungen.tabStandorte', icon: 'mdi-map-marker-radius-outline' },
  { id: 'categories', labelKey: 'grossanlass.einstellungen.tabKategorien', icon: 'mdi-folder-outline' },
  { id: 'inquiry-email', labelKey: 'grossanlass.einstellungen.tabAnfragenEmail', icon: 'mdi-email-edit-outline' },
  { id: 'participants', labelKey: 'grossanlass.planung.tabTeilnehmer', icon: 'mdi-account-group-outline' },
  { id: 'approval', labelKey: 'grossanlass.planung.tabFreigabe', icon: 'mdi-check-decagram-outline' },
]

/** Reiter, die nur MW/Co-MW/OK-Leitung sehen. Allgemeine Einstellungen (Zeit, Druck, Mein Department …) bleiben unter /dept/settings. */
const MANAGEMENT_TABS: ReadonlySet<GaEinstellungenTabId> = new Set(['users'])

export interface GaEinstellungenTabContext {
  role: string | null | undefined
  setupPending: boolean
  /** Teilnehmer/Freigabe nur zeigen, wenn Gast-Departments vorgesehen sind (oder noch unbekannt). */
  guestTabsVisible: boolean
}

/** Reiter der GA-Einstellungen. Offene Ersteinrichtung: alle Reiter sichtbar, ausser den Einrichtungs-Bereichen gesperrt. */
export function buildGrossanlassEinstellungenTabs(ctx: GaEinstellungenTabContext): GaEinstellungenTab[] {
  if (gaIsMailboxOnly(ctx.role)) {
    return ALL_TABS.filter((tab) => tab.id === 'inquiry-email').map((tab) => ({ ...tab, locked: ctx.setupPending }))
  }
  return ALL_TABS.filter((tab) => {
    if (tab.id === 'categories' && !gaCanManageProcurement(ctx.role)) return false
    if (MANAGEMENT_TABS.has(tab.id) && !gaCanSeeAnlassOverview(ctx.role)) return false
    if (!ctx.setupPending && GUEST_TABS.has(tab.id) && !ctx.guestTabsVisible) return false
    return true
  }).map((tab) => ({ ...tab, locked: ctx.setupPending && !GA_SETUP_ACTIVE_TABS.has(tab.id) }))
}
