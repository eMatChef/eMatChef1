/**
 * UI-Prototyp «Aufträge» unter Planung: normaler Auftrag und Bauauftrag in einer gemeinsamen Ansicht.
 * Reiner Frontend-Demo-State. Verknüpfungen zeigen auf die bestehenden Demos (Aufgaben, Helferpool,
 * Pack, Ausgabe, Disposition). Die Abbildung auf Backend-Entities wird erst beim API-Abgleich entschieden.
 */
import { computed, reactive } from 'vue'
import {
  anlassAt,
  taskProgress,
  useGaAufgabenMock,
  type GaAufgabe,
} from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import { needById } from '@/views/grossanlass/logistik/gaDispoMock'
import { addNeed, type GaResourceDraft, type GaTimeSlot } from '@/views/grossanlass/ressourcen/gaRessourcenMock'
import { projectTotals, useGaPackenMock } from '@/views/grossanlass/packen/gaPackenMock'

export type GaOrderType = 'order' | 'build'
export type GaOrderStatus = 'draft' | 'planned' | 'active' | 'done'
export type GaOrderFilter = 'all' | GaOrderType

export type GaOrderHelperNeed = { skill: string; count: number }
export type GaOrderTransport = { from: string; to: string; note: string }

export type GaOrder = {
  id: string
  type: GaOrderType
  title: string
  description: string
  ressort: string
  bereich: string
  /** geplanter Zeitraum (tatsächlich/geplant) */
  startsAt: Date
  endsAt: Date
  /** frühester Start */
  earliestStart: Date | null
  /** spätester Abschluss / Deadline */
  deadline: Date | null
  /** gewünschter Zeitraum */
  wished: GaTimeSlot | null
  location: string
  responsible: string[]
  helperNeed: GaOrderHelperNeed[]
  /** Materialbedarf, eine Zeile pro Position */
  material: string[]
  tools: string[]
  transport: GaOrderTransport | null
  /** Titel der Aufgaben, die zu diesem Auftrag gehören (nur für neue Aufträge) */
  taskTitles: string[]
  status: GaOrderStatus
  /** Verknüpfungen zu den bestehenden Mockups */
  links: {
    taskIds: string[]
    dispoNeedIds: string[]
    packProjectId?: string
    ausgabeContextId?: string
  }
  /** nur Bauauftrag */
  build?: {
    project: string
    site: string
    /** Aufbauzeitfenster */
    buildFrom: Date
    buildTo: Date
    /** «fertig bis» */
    readyBy: Date | null
    machines: string[]
    teardownFrom: Date | null
    teardownTo: Date | null
    teardownInfo: string
    /** manuell erfasster Fortschritt, wenn keine Aufgaben verknüpft sind */
    manualProgress: number
  }
}

type State = { orders: GaOrder[]; nextId: number }

function build(): State {
  return {
    nextId: 10,
    orders: [
      {
        id: 'ao-bar-west',
        type: 'build',
        title: 'Bar West',
        description: 'Holzbau der Bar West mit Tresen, Verkleidung und Beleuchtung.',
        ressort: 'Demo-Bauten',
        bereich: 'Bühne & Gerüst',
        startsAt: anlassAt(3, 8),
        endsAt: anlassAt(5, 17),
        earliestStart: anlassAt(3, 8),
        deadline: anlassAt(5, 18),
        wished: { from: anlassAt(3, 8), to: anlassAt(5, 12) },
        location: 'Festgelände Nord',
        responsible: ['Peter'],
        helperNeed: [{ skill: 'Holzbau', count: 4 }],
        material: ['14× Kantholz 6×12 cm', '40× Schalbretter 24×200', '200× Schrauben 6×120'],
        tools: ['Akkuschrauber', 'Stichsäge', 'Stapler'],
        transport: { from: 'Zentrallager', to: 'Bar West', note: 'Palette PK-0042 und Werkzeug' },
        taskTitles: [],
        status: 'active',
        links: {
          taskIds: ['ga-demo-aufbau-1', 'ga-demo-holz-need', 'ga-demo-arbeit-1', 'ga-demo-bw-material', 'ga-demo-bw-holz', 'ga-demo-bw-kran', 'ga-demo-bw-dach', 'ga-demo-bw-elektro'],
          dispoNeedIds: ['n-pk42', 'n-werkzeug'],
          packProjectId: 'pp-bar-west',
          ausgabeContextId: 'c-bar-west',
        },
        build: {
          project: 'Bar West · Holzbau',
          site: 'Festgelände Nord, Parzelle 3',
          buildFrom: anlassAt(3, 8),
          buildTo: anlassAt(5, 17),
          readyBy: anlassAt(5, 18),
          machines: ['Stapler', 'Hebebühne'],
          teardownFrom: anlassAt(8, 8),
          teardownTo: anlassAt(9, 17),
          teardownInfo: 'Holz sortieren, Bretter zum Weiterverkauf, Rest ins Lager.',
          manualProgress: 0,
        },
      },
      {
        id: 'ao-crew-zelt',
        type: 'build',
        title: 'Crew-Zelt Backstage',
        description: 'Zelt aufstellen, Boden legen und verspannen.',
        ressort: 'Demo-Bauten',
        bereich: 'Zelte',
        startsAt: anlassAt(4, 8),
        endsAt: anlassAt(4, 16),
        earliestStart: anlassAt(4, 7),
        deadline: anlassAt(4, 18),
        wished: { from: anlassAt(4, 8), to: anlassAt(4, 16) },
        location: 'Festgelände Ost',
        responsible: ['Marco'],
        helperNeed: [{ skill: 'Aufbau', count: 1 }],
        material: ['24× Bodenplatten 1×2 m', '100× Terrassenschrauben'],
        tools: ['Akkuschrauber'],
        transport: null,
        taskTitles: [],
        status: 'planned',
        links: { taskIds: ['ga-demo-zelt-1', 'ga-demo-arbeit-2'], dispoNeedIds: [], packProjectId: 'pp-crew-zelt', ausgabeContextId: 'c-crew-zelt' },
        build: {
          project: 'Crew-Zelt Backstage',
          site: 'Festgelände Ost, Backstage',
          buildFrom: anlassAt(4, 8),
          buildTo: anlassAt(4, 16),
          readyBy: anlassAt(4, 18),
          machines: [],
          teardownFrom: anlassAt(8, 9),
          teardownTo: anlassAt(8, 15),
          teardownInfo: 'Zelt an Zeltbau AG zurück (Leihe).',
          manualProgress: 0,
        },
      },
      {
        id: 'ao-pagode',
        type: 'build',
        title: 'Info-Pagode Eingang',
        description: 'Pagode am Haupteingang aufbauen und beschildern.',
        ressort: 'Demo-Bauten',
        bereich: 'Eingang',
        startsAt: anlassAt(2, 9),
        endsAt: anlassAt(7, 18),
        earliestStart: anlassAt(2, 8),
        deadline: anlassAt(7, 18),
        wished: null,
        location: 'Haupteingang',
        responsible: ['Sina'],
        helperNeed: [{ skill: 'Aufbau', count: 2 }],
        material: ['Pagode 3×3 m', '24× Heringe'],
        tools: [],
        transport: { from: 'Zentrallager', to: 'Info-Pagode Eingang', note: 'Palette PK-0041' },
        taskTitles: [],
        status: 'planned',
        links: { taskIds: ['ga-demo-arbeit-3', 'ga-demo-abbau-1'], dispoNeedIds: ['n-pk41'], packProjectId: 'pp-info-pagode', ausgabeContextId: 'c-pagode' },
        build: {
          project: 'Info-Pagode Eingang',
          site: 'Haupteingang, Vorplatz',
          buildFrom: anlassAt(2, 9),
          buildTo: anlassAt(2, 15),
          readyBy: anlassAt(2, 16),
          machines: [],
          teardownFrom: anlassAt(7, 14),
          teardownTo: anlassAt(7, 18),
          teardownInfo: 'Pagode verpacken, Heringe ins Lager A.',
          manualProgress: 0,
        },
      },
      {
        id: 'ao-catering',
        type: 'order',
        title: 'Catering-Zelt einrichten',
        description: 'Küche, Tische und Hygienecheck im Catering-Zelt.',
        ressort: 'Demo-Verpflegung',
        bereich: 'Küche',
        startsAt: anlassAt(5, 8),
        endsAt: anlassAt(5, 12),
        earliestStart: anlassAt(5, 7),
        deadline: anlassAt(5, 14),
        wished: null,
        location: 'Catering-Zelt',
        responsible: ['Sina'],
        helperNeed: [{ skill: 'Küche', count: 2 }],
        material: ['12× Festbank-Garnitur'],
        tools: [],
        transport: { from: 'Festzelt AG', to: 'Catering-Zelt', note: 'Festzelt abholen' },
        taskTitles: [],
        status: 'planned',
        links: { taskIds: ['ga-demo-kueche-1'], dispoNeedIds: ['n-festzelt'] },
      },
      {
        id: 'ao-briefing',
        type: 'order',
        title: 'Helfer-Briefing und Sicherheit',
        description: 'Einführung der Helfer, Sicherheitsregeln und Zuteilung der Funkgeräte.',
        ressort: 'Demo-Organisation',
        bereich: 'Helferwesen',
        startsAt: anlassAt(2, 7),
        endsAt: anlassAt(2, 8),
        earliestStart: null,
        deadline: anlassAt(2, 9),
        wished: null,
        location: 'Zentrallager',
        responsible: ['Lea'],
        helperNeed: [],
        material: [],
        tools: ['Funkgeräte'],
        transport: null,
        taskTitles: [],
        status: 'done',
        links: { taskIds: [], dispoNeedIds: [] },
      },
      {
        id: 'ao-zeltbau',
        type: 'order',
        title: 'Mietzelt von Zeltbau AG holen',
        description: 'Transportauftrag für das gemietete Festzelt.',
        ressort: 'Demo-Material&Logistik',
        bereich: 'Transporte',
        startsAt: anlassAt(5, 13),
        endsAt: anlassAt(5, 16),
        earliestStart: anlassAt(5, 12),
        deadline: anlassAt(5, 17),
        wished: null,
        location: '',
        responsible: ['Luca'],
        helperNeed: [{ skill: 'LKW', count: 1 }],
        material: ['Festzelt 6×12 m'],
        tools: ['LKW 7.5 t'],
        transport: { from: 'Zeltbau AG', to: 'Zentrallager', note: 'Mietzelt' },
        taskTitles: [],
        status: 'planned',
        links: { taskIds: ['ga-demo-nov-logistik'], dispoNeedIds: [] },
      },
    ],
  }
}

const state = reactive<State>(build())

export function resetGaAuftraegeDemo(): void {
  const fresh = build()
  state.orders = fresh.orders
  state.nextId = fresh.nextId
}

export function useGaAuftraegeMock() {
  return { orders: computed(() => state.orders) }
}

export function orderById(id: string | null | undefined): GaOrder | undefined {
  return state.orders.find((row) => row.id === id)
}

// ── Ableitungen aus den bestehenden Mockups ────────────────────

export function linkedTasks(order: GaOrder): GaAufgabe[] {
  const { tasks } = useGaAufgabenMock()
  return order.links.taskIds.flatMap((id) => tasks.value.find((task) => task.id === id) ?? [])
}

/** Baufortschritt: Mittel der Aufgabenfortschritte, sonst manueller Wert. */
export function buildProgress(order: GaOrder): number {
  const tasks = linkedTasks(order)
  if (!tasks.length) return order.build?.manualProgress ?? 0
  return Math.round(tasks.reduce((sum, task) => sum + taskProgress(task), 0) / tasks.length)
}

/** Materialfortschritt aus dem Packen-Demo (gepackt/benötigt). */
export function materialProgress(order: GaOrder): number | null {
  const id = order.links.packProjectId
  if (!id) return null
  const project = useGaPackenMock().projects.value.find((row) => row.id === id)
  return project ? projectTotals(project).progress : null
}

export function helperStaffing(order: GaOrder) {
  const need = order.helperNeed.reduce((sum, entry) => sum + entry.count, 0)
  const people = new Set(linkedTasks(order).flatMap((task) => task.people))
  return { need, assigned: Math.min(people.size, need || people.size), people: [...people] }
}

export function transportStatus(order: GaOrder): { open: number; underway: number; done: number } {
  const needs = order.links.dispoNeedIds.flatMap((id) => needById(id) ?? [])
  return {
    open: needs.filter((need) => need.status === 'open' || need.status === 'planned').length,
    underway: needs.filter((need) => need.status === 'underway').length,
    done: needs.filter((need) => need.status === 'done').length,
  }
}

export function counts(): Record<GaOrderFilter, number> {
  return {
    all: state.orders.length,
    order: state.orders.filter((row) => row.type === 'order').length,
    build: state.orders.filter((row) => row.type === 'build').length,
  }
}

export type GaOrderFilters = { type: GaOrderFilter; status: '' | GaOrderStatus; ressort: string; search: string }
export const emptyOrderFilters = (): GaOrderFilters => ({ type: 'all', status: '', ressort: '', search: '' })

export function matchesOrder(order: GaOrder, filters: GaOrderFilters): boolean {
  if (filters.type !== 'all' && order.type !== filters.type) return false
  if (filters.status && order.status !== filters.status) return false
  if (filters.ressort && order.ressort !== filters.ressort) return false
  const q = filters.search.trim().toLowerCase()
  if (q && !`${order.title} ${order.description} ${order.location} ${order.build?.project ?? ''} ${order.responsible.join(' ')}`.toLowerCase().includes(q)) return false
  return true
}

// ── lokale Aktionen ────────────────────────────────────────────

export type GaOrderDraft = Omit<GaOrder, 'id' | 'links' | 'status'> & { status?: GaOrderStatus; resources: GaResourceDraft[] }

/**
 * Legt einen Auftrag an. Die angegebenen Aufgabentitel werden als echte Aufgaben in der
 * Aufgaben-Demo erzeugt und mit dem Auftrag verknüpft; ein Helferbedarf wird an die erste Aufgabe gehängt.
 */
export function createOrder(draft: GaOrderDraft): GaOrder {
  const id = `ao-${state.nextId}`
  state.nextId += 1
  const { state: taskState } = useGaAufgabenMock()
  const taskIds: string[] = []
  const origin = [draft.ressort, draft.bereich, draft.build?.project || draft.title].filter(Boolean)
  draft.taskTitles.filter((title) => title.trim()).forEach((title, index) => {
    const taskId = `${id}-t${index + 1}`
    taskState.tasks.push({
      id: taskId,
      kind: 'arbeit',
      title: title.trim(),
      origin,
      ressort: draft.ressort,
      bereich: draft.bereich,
      startsAt: draft.startsAt,
      endsAt: draft.endsAt,
      people: [],
      status: 'open',
      description: draft.description,
      materials: [],
      steps: [],
      routeStepIndex: -1,
      helperNeed: index === 0 && draft.helperNeed[0] ? { ...draft.helperNeed[0] } : undefined,
    })
    taskIds.push(taskId)
  })
  const { resources, ...orderFields } = draft
  const order: GaOrder = {
    ...orderFields,
    id,
    status: draft.status ?? 'draft',
    links: { taskIds, dispoNeedIds: [] },
  }
  for (const resource of resources) addNeed(id, resource)
  state.orders.unshift(order)
  return order
}

export function setOrderStatus(orderId: string, status: GaOrderStatus): boolean {
  const order = orderById(orderId)
  if (!order || order.status === status) return false
  order.status = status
  return true
}

export function emptyDraft(type: GaOrderType): GaOrderDraft {
  const from = anlassAt(4, 8)
  const to = anlassAt(4, 12)
  return {
    type,
    title: '',
    description: '',
    ressort: '',
    bereich: '',
    startsAt: from,
    endsAt: to,
    earliestStart: null,
    deadline: null,
    wished: null,
    resources: [],
    location: '',
    responsible: [],
    helperNeed: [],
    material: [],
    tools: [],
    transport: null,
    taskTitles: [],
    build: type === 'build'
      ? { project: '', site: '', buildFrom: from, buildTo: to, readyBy: null, machines: [], teardownFrom: null, teardownTo: null, teardownInfo: '', manualProgress: 0 }
      : undefined,
  }
}

/** Deadline-Check: geplantes Ende nach Deadline oder Aufbau nach «fertig bis». */
export function deadlineIssues(order: GaOrder): Array<'afterDeadline' | 'afterReadyBy' | 'beforeEarliest'> {
  const out: Array<'afterDeadline' | 'afterReadyBy' | 'beforeEarliest'> = []
  if (order.deadline && order.endsAt > order.deadline) out.push('afterDeadline')
  if (order.build?.readyBy && order.build.buildTo > order.build.readyBy) out.push('afterReadyBy')
  if (order.earliestStart && order.startsAt < order.earliestStart) out.push('beforeEarliest')
  return out
}
