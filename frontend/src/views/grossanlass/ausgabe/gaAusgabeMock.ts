/**
 * UI-Prototyp «Ausgabe» (Lager-Schalter): Demo-Artikel, -Personen, -Aufträge, offene Ausgaben und Verlauf.
 * Lokaler State, ohne API und ohne Persistenz. Ausgabe ≠ Packen: gepacktes Material ist nicht frei ausgebbar.
 */
import { logEvent } from '@/views/grossanlass/live/gaLiveEvents'
import { computed, reactive } from 'vue'

export type GaArticleKind = 'consumable' | 'returnable'
export type GaWarningKind = 'noStock' | 'packed' | 'lent' | 'defect' | 'overdue' | 'unknown'

export type GaAusgabeArticle = {
  id: string
  code: string
  name: string
  kind: GaArticleKind
  /** frei in der Ausgabe */
  stock: number
  /** bereits gepackt (Pack/Palette), nicht frei ausgebbar */
  packed: number
  /** defekt oder in der Werkstatt */
  defect: number
  unit: string
  place: string
}

export type GaAusgabePerson = { id: string; name: string; code: string; ressort: string }

export type GaAusgabeContext = {
  id: string
  label: string
  ressort: string
  /** geplanter Bedarf: articleId → Menge */
  plan: Array<{ articleId: string; planned: number }>
}

export type GaLoan = {
  id: string
  personId: string
  personName: string
  articleId: string
  name: string
  qty: number
  since: Date
  contextId: string
  contextLabel: string
  dueAt: Date | null
}

export type GaHistoryEntry = {
  id: string
  at: Date
  kind: 'issue' | 'return' | 'transfer' | 'reassign'
  person: string
  article: string
  qty: number
  context: string
  note: string
}

export type GaSessionLine = { articleId: string; qty: number }
export type GaSession = {
  personId: string
  personName: string
  contextId: string
  lines: GaSessionLine[]
}

export const GA_NO_CONTEXT = 'none'

type State = {
  articles: GaAusgabeArticle[]
  people: GaAusgabePerson[]
  contexts: GaAusgabeContext[]
  loans: GaLoan[]
  history: GaHistoryEntry[]
  session: GaSession
  last: { person: string; context: string; lines: number } | null
  nextId: number
}

function ago(hours: number): Date {
  return new Date(Date.now() - hours * 3_600_000)
}
function inH(hours: number): Date {
  return new Date(Date.now() + hours * 3_600_000)
}

function build(): State {
  const articles: GaAusgabeArticle[] = [
    { id: 'a-akku', code: 'AS-001', name: 'Akkuschrauber', kind: 'returnable', stock: 3, packed: 0, defect: 0, unit: 'Stk', place: 'Regal A2' },
    { id: 'a-kabelrolle', code: 'KR-050', name: 'Kabelrolle 50 m', kind: 'returnable', stock: 2, packed: 0, defect: 1, unit: 'Stk', place: 'Regal B1' },
    { id: 'a-funk', code: 'FG-010', name: 'Funkgerät', kind: 'returnable', stock: 4, packed: 0, defect: 0, unit: 'Stk', place: 'Schrank Funk' },
    { id: 'a-stichsaege', code: 'SS-002', name: 'Stichsäge', kind: 'returnable', stock: 1, packed: 0, defect: 0, unit: 'Stk', place: 'Regal A3' },
    { id: 'a-bohrhammer', code: 'BH-004', name: 'Bohrhammer', kind: 'returnable', stock: 0, packed: 0, defect: 1, unit: 'Stk', place: 'Werkstatt' },
    { id: 'a-leiter', code: 'LT-003', name: 'Leiter 3 m', kind: 'returnable', stock: 0, packed: 0, defect: 0, unit: 'Stk', place: 'Aussenlager' },
    { id: 'a-kabelbinder', code: 'KB-100', name: 'Kabelbinder', kind: 'consumable', stock: 500, packed: 0, defect: 0, unit: 'Stk', place: 'Regal C1' },
    { id: 'a-schrauben', code: 'SC-612', name: 'Schrauben 6×120', kind: 'consumable', stock: 120, packed: 0, defect: 0, unit: 'Stk', place: 'Regal C2' },
    { id: 'a-handschuhe', code: 'HS-020', name: 'Arbeitshandschuhe', kind: 'consumable', stock: 8, packed: 0, defect: 0, unit: 'Paar', place: 'Regal C3' },
    { id: 'a-kantholz', code: 'KH-612', name: 'Kantholz 6×12 cm', kind: 'consumable', stock: 0, packed: 14, defect: 0, unit: 'Stk', place: 'Pack PK-0042' },
  ]
  return {
    articles,
    people: [
      { id: 'p-peter', name: 'Peter Muster', code: 'UK-1001', ressort: 'Demo-Bauten' },
      { id: 'p-lea', name: 'Lea Meier', code: 'UK-1002', ressort: 'Demo-Material&Logistik' },
      { id: 'p-marco', name: 'Marco Rossi', code: 'UK-1003', ressort: 'Demo-Bauten' },
      { id: 'p-sina', name: 'Sina Keller', code: 'UK-1004', ressort: 'Demo-Verpflegung' },
    ],
    contexts: [
      { id: 'c-bar-west', label: 'Bar West', ressort: 'Demo-Bauten · Bühne & Gerüst', plan: [{ articleId: 'a-akku', planned: 2 }, { articleId: 'a-kabelrolle', planned: 1 }, { articleId: 'a-kabelbinder', planned: 50 }, { articleId: 'a-schrauben', planned: 200 }] },
      { id: 'c-crew-zelt', label: 'Crew-Zelt Backstage', ressort: 'Demo-Bauten · Zelte', plan: [{ articleId: 'a-akku', planned: 1 }, { articleId: 'a-handschuhe', planned: 12 }] },
      { id: 'c-pagode', label: 'Info-Pagode Eingang', ressort: 'Demo-Bauten · Eingang', plan: [{ articleId: 'a-funk', planned: 2 }] },
    ],
    loans: [
      { id: 'l-1', personId: 'p-peter', personName: 'Peter Muster', articleId: 'a-akku', name: 'Akkuschrauber', qty: 1, since: ago(3), contextId: 'c-bar-west', contextLabel: 'Bar West', dueAt: inH(5) },
      { id: 'l-2', personId: 'p-marco', personName: 'Marco Rossi', articleId: 'a-funk', name: 'Funkgerät', qty: 2, since: ago(30), contextId: 'c-pagode', contextLabel: 'Info-Pagode Eingang', dueAt: ago(6) },
      { id: 'l-3', personId: 'p-sina', personName: 'Sina Keller', articleId: 'a-leiter', name: 'Leiter 3 m', qty: 1, since: ago(48), contextId: GA_NO_CONTEXT, contextLabel: '', dueAt: inH(20) },
      { id: 'l-4', personId: 'p-lea', personName: 'Lea Meier', articleId: 'a-kabelrolle', name: 'Kabelrolle 50 m', qty: 1, since: ago(75), contextId: 'c-crew-zelt', contextLabel: 'Crew-Zelt Backstage', dueAt: ago(24) },
    ],
    history: [
      { id: 'h-1', at: ago(3), kind: 'issue', person: 'Peter Muster', article: 'Akkuschrauber', qty: 1, context: 'Bar West', note: '' },
      { id: 'h-2', at: ago(5), kind: 'issue', person: 'Peter Muster', article: 'Kabelbinder', qty: 25, context: 'Bar West', note: 'Verbrauch' },
      { id: 'h-3', at: ago(26), kind: 'return', person: 'Jonas Frei', article: 'Bohrhammer', qty: 1, context: 'Info-Pagode Eingang', note: 'defekt zurück, Werkstatt' },
      { id: 'h-4', at: ago(30), kind: 'issue', person: 'Marco Rossi', article: 'Funkgerät', qty: 2, context: 'Info-Pagode Eingang', note: '' },
    ],
    session: { personId: '', personName: '', contextId: '', lines: [] },
    last: null,
    nextId: 10,
  }
}

const state = reactive<State>(build())

export function resetGaAusgabeDemo(): void {
  const fresh = build()
  state.articles = fresh.articles
  state.people = fresh.people
  state.contexts = fresh.contexts
  state.loans = fresh.loans
  state.history = fresh.history
  state.session = fresh.session
  state.last = fresh.last
  state.nextId = fresh.nextId
}

export function useGaAusgabeMock() {
  return {
    articles: computed(() => state.articles),
    people: computed(() => state.people),
    contexts: computed(() => state.contexts),
    loans: computed(() => state.loans),
    history: computed(() => state.history),
    session: computed(() => state.session),
    last: computed(() => state.last),
  }
}

// ── Lookup ─────────────────────────────────────────────────────

export function articleById(id: string): GaAusgabeArticle | undefined {
  return state.articles.find((row) => row.id === id)
}
export function contextById(id: string): GaAusgabeContext | undefined {
  return state.contexts.find((row) => row.id === id)
}
export function contextLabel(id: string): string {
  return contextById(id)?.label ?? ''
}

/** Scan (Code) oder Suche (Name). Gleicher Code gewinnt vor Namenstreffer. */
export function findArticle(query: string): GaAusgabeArticle | undefined {
  const q = query.trim().toLowerCase()
  if (!q) return undefined
  return state.articles.find((row) => row.code.toLowerCase() === q)
    ?? state.articles.find((row) => row.name.toLowerCase().includes(q))
}
export function searchArticles(query: string): GaAusgabeArticle[] {
  const q = query.trim().toLowerCase()
  if (!q) return []
  return state.articles.filter((row) => row.code.toLowerCase().includes(q) || row.name.toLowerCase().includes(q)).slice(0, 6)
}
export function findPerson(query: string): GaAusgabePerson | undefined {
  const q = query.trim().toLowerCase()
  if (!q) return undefined
  return state.people.find((row) => row.code.toLowerCase() === q) ?? state.people.find((row) => row.name.toLowerCase().includes(q))
}

/** Echte Benutzerkarten in die Personenliste übernehmen (nur neue). */
export function mergePeople(list: GaAusgabePerson[]): void {
  for (const person of list) {
    if (!state.people.some((row) => row.id === person.id || row.name === person.name)) state.people.push(person)
  }
}

// ── Warnungen ──────────────────────────────────────────────────

export type GaWarning = { kind: GaWarningKind; blocking: boolean; params?: Record<string, string | number> }

export function isOverdue(loan: Pick<GaLoan, 'dueAt'>, now = new Date()): boolean {
  return !!loan.dueAt && loan.dueAt.getTime() < now.getTime()
}

export function lentQty(articleId: string): number {
  return state.loans.filter((row) => row.articleId === articleId).reduce((sum, row) => sum + row.qty, 0)
}

export function lineWarnings(line: GaSessionLine): GaWarning[] {
  const article = articleById(line.articleId)
  if (!article) return [{ kind: 'unknown', blocking: true }]
  const out: GaWarning[] = []
  if (article.stock <= 0 && article.packed > 0) out.push({ kind: 'packed', blocking: true, params: { n: article.packed } })
  else if (article.stock <= 0 && article.defect > 0 && lentQty(article.id) === 0) out.push({ kind: 'defect', blocking: true })
  else if (article.stock <= 0 && lentQty(article.id) > 0) out.push({ kind: 'lent', blocking: true, params: { n: lentQty(article.id), who: state.loans.filter((row) => row.articleId === article.id).map((row) => row.personName).join(', ') } })
  else if (article.stock <= 0) out.push({ kind: 'noStock', blocking: true, params: { have: 0 } })
  else if (line.qty > article.stock) out.push({ kind: 'noStock', blocking: true, params: { have: article.stock } })
  if (article.defect > 0 && article.stock > 0) out.push({ kind: 'defect', blocking: false })
  return out
}

export function personWarnings(personName: string): GaWarning[] {
  const overdue = state.loans.filter((row) => row.personName === personName && isOverdue(row))
  return overdue.length ? [{ kind: 'overdue', blocking: false, params: { n: overdue.length } }] : []
}

export function canConfirm(): boolean {
  const s = state.session
  if (!s.personId || !s.lines.length) return false
  return s.lines.every((line) => line.qty > 0 && !lineWarnings(line).some((warning) => warning.blocking))
}

// ── Session ────────────────────────────────────────────────────

export function setRecipient(person: GaAusgabePerson | null): void {
  state.session.personId = person?.id ?? ''
  state.session.personName = person?.name ?? ''
}
export function setContext(contextId: string): void {
  state.session.contextId = contextId
}

/** Artikel zur Ausgabe hinzufügen (Scan/Suche): erhöht die Menge, wenn schon vorhanden. */
export function addArticle(articleId: string, qty = 1): GaSessionLine | null {
  const article = articleById(articleId)
  if (!article) return null
  const existing = state.session.lines.find((row) => row.articleId === articleId)
  if (existing) {
    existing.qty += qty
    return existing
  }
  const line = { articleId, qty: Math.max(1, qty) }
  state.session.lines.push(line)
  return line
}

export function scanArticle(query: string): { ok: boolean; article?: GaAusgabeArticle; line?: GaSessionLine } {
  const article = findArticle(query)
  if (!article) return { ok: false }
  return { ok: true, article, line: addArticle(article.id, 1) ?? undefined }
}

export function changeQty(articleId: string, delta: number): void {
  const line = state.session.lines.find((row) => row.articleId === articleId)
  if (!line) return
  line.qty = Math.max(0, line.qty + delta)
  if (line.qty === 0) removeLine(articleId)
}
export function setLineQty(articleId: string, qty: number): void {
  const line = state.session.lines.find((row) => row.articleId === articleId)
  if (!line) return
  line.qty = Math.max(0, Math.floor(qty))
  if (line.qty === 0) removeLine(articleId)
}
export function removeLine(articleId: string): void {
  state.session.lines = state.session.lines.filter((row) => row.articleId !== articleId)
}
export function clearSession(): void {
  state.session = { personId: '', personName: '', contextId: '', lines: [] }
}

// ── Schnellausgabe ─────────────────────────────────────────────

export type GaPlanRow = { article: GaAusgabeArticle; planned: number; issued: number; needed: number; inSession: number }

export function issuedFor(contextId: string, articleId: string): number {
  return state.history
    .filter((row) => row.kind === 'issue' && row.context === contextLabel(contextId) && row.article === articleById(articleId)?.name)
    .reduce((sum, row) => sum + row.qty, 0)
}

export function contextPlan(contextId: string): GaPlanRow[] {
  const context = contextById(contextId)
  if (!context) return []
  return context.plan.flatMap((entry) => {
    const article = articleById(entry.articleId)
    if (!article) return []
    const issued = issuedFor(contextId, entry.articleId)
    return [{
      article,
      planned: entry.planned,
      issued,
      needed: Math.max(0, entry.planned - issued),
      inSession: state.session.lines.find((row) => row.articleId === entry.articleId)?.qty ?? 0,
    }]
  })
}

/** Übernimmt den noch benötigten Bedarf des Auftrags in die Ausgabe (nur ausgebbare Artikel). */
export function addNeededFromPlan(contextId: string): number {
  let added = 0
  for (const row of contextPlan(contextId)) {
    if (row.needed <= 0 || row.inSession > 0) continue
    const qty = Math.min(row.needed, Math.max(row.article.stock, 0)) || row.needed
    addArticle(row.article.id, qty)
    added += 1
  }
  return added
}

// ── Bestätigen ─────────────────────────────────────────────────

function pushHistory(entry: Omit<GaHistoryEntry, 'id' | 'at'>): void {
  state.history.unshift({ id: `h-${state.nextId}`, at: new Date(), ...entry })
  state.nextId += 1
}

export function confirmIssue(): { lines: number } | null {
  if (!canConfirm()) return null
  const s = state.session
  const label = contextLabel(s.contextId)
  let lines = 0
  for (const line of s.lines) {
    const article = articleById(line.articleId)
    if (!article) continue
    article.stock -= line.qty
    if (article.kind === 'returnable') {
      state.loans.push({
        id: `l-${state.nextId}`,
        personId: s.personId,
        personName: s.personName,
        articleId: article.id,
        name: article.name,
        qty: line.qty,
        since: new Date(),
        contextId: s.contextId || GA_NO_CONTEXT,
        contextLabel: label,
        dueAt: inH(24),
      })
      state.nextId += 1
    }
    pushHistory({ kind: 'issue', person: s.personName, article: article.name, qty: line.qty, context: label, note: article.kind === 'consumable' ? 'Verbrauch' : '' })
    lines += 1
  }
  state.last = { person: s.personName, context: label, lines }
  logEvent({ area: 'material', actor: 'Lager', text: `Ausgabe an ${s.personName}: ${lines} Position(en)`, detail: label, project: label })
  clearSession()
  return { lines }
}

// ── Offen bei Personen ─────────────────────────────────────────

export function openLoans(): GaLoan[] {
  return state.loans.slice().sort((a, b) => a.since.getTime() - b.since.getTime())
}

export function returnLoan(loanId: string): boolean {
  const loan = state.loans.find((row) => row.id === loanId)
  if (!loan) return false
  const article = articleById(loan.articleId)
  if (article) article.stock += loan.qty
  state.loans = state.loans.filter((row) => row.id !== loanId)
  pushHistory({ kind: 'return', person: loan.personName, article: loan.name, qty: loan.qty, context: loan.contextLabel, note: '' })
  return true
}

export function transferLoan(loanId: string, personId: string): boolean {
  const loan = state.loans.find((row) => row.id === loanId)
  const person = state.people.find((row) => row.id === personId)
  if (!loan || !person || person.id === loan.personId) return false
  pushHistory({ kind: 'transfer', person: `${loan.personName} → ${person.name}`, article: loan.name, qty: loan.qty, context: loan.contextLabel, note: '' })
  loan.personId = person.id
  loan.personName = person.name
  return true
}

export function reassignLoan(loanId: string, contextId: string): boolean {
  const loan = state.loans.find((row) => row.id === loanId)
  if (!loan || loan.contextId === contextId) return false
  const label = contextLabel(contextId)
  pushHistory({ kind: 'reassign', person: loan.personName, article: loan.name, qty: loan.qty, context: `${loan.contextLabel || '–'} → ${label || '–'}`, note: '' })
  loan.contextId = contextId
  loan.contextLabel = label
  return true
}
