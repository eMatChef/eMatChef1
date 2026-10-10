/**
 * Registry der Grossanlass-Hilfe (GA). Kapitel hängen an stabilen Routennamen; die Texte liegen in vue-i18n
 * unter `gaHelp.topics.<id>.*` (Varianten und Fallback über die bestehende Locale-Kette).
 *
 * Strikt getrennt von der Department-Hilfe (`help/*`): nur in einem Grossanlass-Department und nur auf
 * GA-Seiten wird die GA-Hilfe angeboten. Alle anderen Seiten behalten die bisherige Hilfe.
 */

export type GaHelpGroup = 'start' | 'setup' | 'planning' | 'procurement' | 'material' | 'guest'

export type GaHelpTopic = {
  id: string
  group: GaHelpGroup
  /** Routen, die dieses Kapitel als Seitenhilfe öffnen; die erste ist das Ziel von «Seite öffnen». */
  routeNames: readonly string[]
  /** Ids der häufigen Fragen: Texte unter `gaHelp.topics.<id>.faq.<faqId>.q|a`. */
  faq: readonly string[]
}

/** Kapitel für Seiten ohne eigenes Kapitel (GA-Seiten, die noch nicht beschrieben sind). */
export const GA_HELP_FALLBACK_TOPIC = 'overview'

/** Route der ausführlichen GA-Hilfe (öffnet nie die Kontexthilfe). */
export const GA_HELP_PAGE_ROUTE = 'GrossanlassHilfe'

export const GA_HELP_GROUPS: readonly GaHelpGroup[] = [
  'start',
  'setup',
  'planning',
  'procurement',
  'material',
  'guest',
]

export const GA_HELP_TOPICS: readonly GaHelpTopic[] = [
  { id: 'overview', group: 'start', routeNames: ['Dashboard'], faq: ['roles', 'setupRelease'] },
  {
    id: 'stammdaten',
    group: 'setup',
    routeNames: ['GrossanlassPlanungStammdaten'],
    faq: ['required', 'locations'],
  },
  { id: 'ressorts', group: 'setup', routeNames: ['GrossanlassRessorts'], faq: ['levels', 'required'] },
  {
    id: 'standorte',
    group: 'setup',
    routeNames: ['GrossanlassEinstellungenStandorte'],
    faq: ['order', 'qr'],
  },
  {
    id: 'kategorien',
    group: 'setup',
    routeNames: ['GrossanlassEinstellungenKategorien'],
    faq: ['storage'],
  },
  {
    id: 'anfragenEmail',
    group: 'setup',
    routeNames: ['GrossanlassEinstellungenAnfragenEmail'],
    faq: ['mailbox'],
  },
  {
    id: 'teilnehmer',
    group: 'setup',
    routeNames: ['GrossanlassPlanungStruktur'],
    faq: ['invite', 'unterlager'],
  },
  {
    id: 'freigabe',
    group: 'setup',
    routeNames: ['GrossanlassPlanungFreigabe'],
    faq: ['effect', 'undo', 'setup'],
  },
  {
    id: 'wuensche',
    group: 'planning',
    routeNames: ['GrossanlassPlanungWuensche'],
    faq: ['enoughOnHand', 'selfOrganized'],
  },
  { id: 'bauauftraege', group: 'planning', routeNames: ['GrossanlassPlanungBauauftraege'], faq: ['status'] },
  { id: 'meinRessort', group: 'planning', routeNames: ['GrossanlassMeinRessort'], faq: ['noRessort', 'scan'] },
  {
    id: 'bedarf',
    group: 'procurement',
    routeNames: ['GrossanlassBeschaffungBedarf'],
    faq: ['partnerBuy', 'direct'],
  },
  { id: 'anfragen', group: 'procurement', routeNames: ['GrossanlassBeschaffungAnfragen'], faq: ['mailAccount'] },
  { id: 'offerten', group: 'procurement', routeNames: ['GrossanlassBeschaffungOfferten'], faq: ['later'] },
  { id: 'zusagen', group: 'procurement', routeNames: ['GrossanlassBeschaffungZusagen'], faq: ['stock'] },
  {
    id: 'bestellungen',
    group: 'procurement',
    routeNames: ['GrossanlassBeschaffungBestellungen'],
    faq: ['empty'],
  },
  { id: 'kosten', group: 'procurement', routeNames: ['GrossanlassKosten'], faq: ['cashNetto', 'payer'] },
  { id: 'wareneingang', group: 'material', routeNames: ['GrossanlassMaterialWareneingang'], faq: ['partial'] },
  { id: 'gastVorschau', group: 'guest', routeNames: ['GrossanlassGastVorschau'], faq: ['preview', 'real'] },
]

const TOPIC_BY_ID = new Map(GA_HELP_TOPICS.map((topic) => [topic.id, topic]))
const TOPIC_BY_ROUTE = new Map<string, string>(
  GA_HELP_TOPICS.flatMap((topic) => topic.routeNames.map((name) => [name, topic.id] as const)),
)

export function gaHelpTopic(id: string | null | undefined): GaHelpTopic | null {
  return id ? (TOPIC_BY_ID.get(id) ?? null) : null
}

/**
 * Kapitel der aktuellen Seite. `null` = keine GA-Seite, die bisherige Department-Hilfe bleibt zuständig.
 * GA-Seiten (Routenname `Grossanlass…`) ohne eigenes Kapitel erhalten den Überblick.
 */
export function gaHelpTopicIdForRoute(routeName: string | symbol | null | undefined): string | null {
  if (typeof routeName !== 'string' || routeName === GA_HELP_PAGE_ROUTE) return null
  const own = TOPIC_BY_ROUTE.get(routeName)
  if (own) return own
  return routeName.startsWith('Grossanlass') ? GA_HELP_FALLBACK_TOPIC : null
}

export function gaHelpTopicsByGroup(group: GaHelpGroup): GaHelpTopic[] {
  return GA_HELP_TOPICS.filter((topic) => topic.group === group)
}

export const gaHelpKey = {
  title: (id: string) => `gaHelp.topics.${id}.title`,
  summary: (id: string) => `gaHelp.topics.${id}.summary`,
  question: (id: string, faqId: string) => `gaHelp.topics.${id}.faq.${faqId}.q`,
  answer: (id: string, faqId: string) => `gaHelp.topics.${id}.faq.${faqId}.a`,
  field: (id: string) => `gaHelp.fields.${id}`,
}
