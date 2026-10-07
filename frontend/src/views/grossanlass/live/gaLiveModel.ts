/**
 * Abgeleitete Sichten für die Displays/Leitstände. Es gibt keine eigenen Mock-Daten:
 * alles wird aus den bestehenden Demo-States (Aufgaben, Packen, Disposition, Aufträge, Ressourcen) berechnet.
 */
import {
  GA_FAHRAUFTRAG_STEPS,
  blockedBy,
  taskProgress,
  useGaAufgabenMock,
  type GaAufgabe,
} from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import {
  buildProgress,
  deadlineIssues,
  helperStaffing,
  linkedTasks,
  materialProgress,
  transportStatus,
  useGaAuftraegeMock,
  type GaOrder,
} from '@/views/grossanlass/auftraege/gaAuftraegeMock'
import {
  dispoCounts,
  driverName,
  hasProblem,
  tourLegs,
  useGaDispoMock,
  vehicleName,
  type GaDispoNeed,
  type GaDispoTour,
} from '@/views/grossanlass/logistik/gaDispoMock'
import {
  linePackableNow,
  lineMissing,
  packStatusCounts,
  projectStatus,
  projectTotals,
  useGaPackenMock,
  type GaPackNeedLine,
  type GaPackProject,
  type GaPalette,
  type GaSupplyInfo,
} from '@/views/grossanlass/packen/gaPackenMock'
import {
  GA_LOGISTICS_RESOURCE_TYPES,
  needsOfOrder,
  useGaRessourcenMock,
  type GaResourceNeed,
} from '@/views/grossanlass/ressourcen/gaRessourcenMock'
import { etaAt, etaProgress, trackingOf, useGaLive, type GaDisplayConfig, type GaTrackingMode } from './gaLiveEvents'
import { hasPlace } from './gaSitePlaces'

// ── Filter ─────────────────────────────────────────────────────

export type GaDisplayFilter = GaDisplayConfig['filter']

function textMatch(haystack: string, needle: string): boolean {
  return !needle.trim() || haystack.toLowerCase().includes(needle.trim().toLowerCase())
}

export function matchesOrderFilter(order: GaOrder, filter: GaDisplayFilter): boolean {
  return (!filter.ressort || order.ressort === filter.ressort)
    && (!filter.bereich || order.bereich === filter.bereich)
    && textMatch(`${order.title} ${order.build?.project ?? ''}`, filter.project)
}

export function matchesTaskFilter(task: GaAufgabe, filter: GaDisplayFilter): boolean {
  return (!filter.ressort || task.ressort === filter.ressort)
    && (!filter.bereich || task.bereich === filter.bereich)
    && textMatch(task.origin.join(' '), filter.project)
}

export function matchesPackFilter(project: GaPackProject, filter: GaDisplayFilter): boolean {
  return (!filter.ressort || project.ressort === filter.ressort)
    && (!filter.bereich || project.bereich === filter.bereich)
    && textMatch(project.name, filter.project)
}

// ── Material-Leitstand ─────────────────────────────────────────

export type GaLineState = {
  line: GaPackNeedLine
  /** FEHLT: nicht vorhanden */
  missing: number
  /** vorhanden, aber noch nicht gepackt */
  availableNotPacked: number
  /** gepackt, wartet auf Transport (Pack/Palette nicht abgeliefert) */
  packedWaiting: number
  /** bereits vor Ort (Palette abgeliefert) */
  onSite: number
  supply: GaSupplyInfo | null
}

export type GaMaterialProject = {
  project: GaPackProject
  status: ReturnType<typeof projectStatus>
  progress: number
  needed: number
  available: number
  packed: number
  missing: number
  lines: GaLineState[]
  palettes: GaPalette[]
  deadline: Date | null
}

function deliveredQty(palettes: GaPalette[], label: string): number {
  return palettes.filter((row) => row.status === 'delivered').reduce((sum, row) => sum + (row.lines.find((line) => line.label === label)?.qty ?? 0), 0)
}

export function materialProjects(filter: GaDisplayFilter): GaMaterialProject[] {
  const { projects, palettes } = useGaPackenMock()
  const { orders } = useGaAuftraegeMock()
  return projects.value.filter((project) => matchesPackFilter(project, filter)).map((project) => {
    const own = palettes.value.filter((row) => row.projectId === project.id)
    const totals = projectTotals(project)
    const order = orders.value.find((row) => row.links.packProjectId === project.id)
    return {
      project,
      status: projectStatus(project),
      progress: totals.progress,
      needed: totals.needed,
      available: totals.available,
      packed: totals.packed,
      missing: totals.missing,
      palettes: own,
      deadline: order?.build?.readyBy ?? order?.deadline ?? null,
      lines: project.lines.map((line) => {
        const onSite = Math.min(line.packed, deliveredQty(own, line.label))
        return {
          line,
          missing: lineMissing(line),
          availableNotPacked: linePackableNow(line),
          packedWaiting: Math.max(0, line.packed - onSite),
          onSite,
          supply: lineMissing(line) > 0 ? (line.supply ?? null) : null,
        }
      }),
    }
  })
}

export function materialBoard(filter: GaDisplayFilter) {
  const projects = materialProjects(filter)
  const palettes = projects.flatMap((entry) => entry.palettes)
  const counts = packStatusCounts(projects.map((entry) => entry.project))
  const missingLines = projects.flatMap((entry) => entry.lines.filter((state) => state.missing > 0).map((state) => ({ project: entry.project, state })))
  const waitingTransport = palettes.filter((row) => row.status === 'ready' || row.status === 'sent')
  return {
    projects,
    kpis: {
      packOpen: projects.filter((entry) => entry.status !== 'done').length,
      blocked: counts.waiting,
      packsReady: palettes.filter((row) => row.status === 'ready' || row.status === 'sent' || row.status === 'delivered').length,
      waitingTransport: waitingTransport.length,
      missingItems: missingLines.length,
    },
    missing: missingLines,
    waitingTransport,
    incoming: missingLines.filter((entry) => entry.state.supply && ['ordered', 'partial', 'late', 'pickupPlanned'].includes(entry.state.supply.cause)),
  }
}

// ── Logistik-Leitstand ─────────────────────────────────────────

export type GaTrip = {
  task: GaAufgabe
  stepIndex: number
  stepKey: (typeof GA_FAHRAUFTRAG_STEPS)[number] | null
  mode: GaTrackingMode
  /** 0–1 auf der Route, nur bei ETA-Simulation und nach «Unterwegs» */
  progress: number | null
  startedAt: Date | null
  eta: Date | null
  underway: boolean
  done: boolean
  from: string
  to: string
  vehicle: string
  driver: string
}

export function trips(filter: GaDisplayFilter): GaTrip[] {
  const { tasks } = useGaAufgabenMock()
  useGaLive()
  return tasks.value
    .filter((task) => task.kind === 'logistik' && task.route && matchesTaskFilter(task, filter))
    .map((task) => {
      const etaMin = task.route!.etaMin ?? 25
      const startedAt = useGaLive().tripStarted.value[task.id] ?? null
      const step = GA_FAHRAUFTRAG_STEPS[task.routeStepIndex] ?? null
      const underway = task.status === 'progress' && (step === 'onTheWay')
      const mode = trackingOf(task.id)
      return {
        task,
        stepIndex: task.routeStepIndex,
        stepKey: step,
        mode,
        progress: mode === 'eta' && underway ? etaProgress(task.id, etaMin) : null,
        startedAt,
        eta: etaAt(task.id, etaMin) ?? (task.status === 'done' ? null : task.endsAt),
        underway,
        done: task.status === 'done',
        from: task.route!.from,
        to: task.route!.to,
        vehicle: task.route!.vehicle,
        driver: task.people[0] ?? '',
      }
    })
}

export function logistikBoard(filter: GaDisplayFilter) {
  const { needs, tours } = useGaDispoMock()
  const { needs: resources } = useGaRessourcenMock()
  const counts = dispoCounts()
  const list = trips(filter)
  const activeTours = tours.value.filter((tour) => tour.status !== 'done')
  const urgent = needs.value.filter((need) => need.status !== 'done' && need.priority === 'urgent')
  const problems = [
    ...needs.value.filter((need) => need.status !== 'done' && hasProblem(need)).map((need) => ({ kind: 'need' as const, need })),
    ...activeTours.filter((tour) => tour.problem).map((tour) => ({ kind: 'tour' as const, tour })),
  ]
  const logisticsResources = resources.value
    .filter((need) => GA_LOGISTICS_RESOURCE_TYPES.includes(need.type))
    .sort((a, b) => (a.planned?.from ?? a.earliest).getTime() - (b.planned?.from ?? b.earliest).getTime())
  return {
    kpis: {
      open: counts.open,
      planned: counts.planned,
      underway: counts.underway + list.filter((entry) => entry.underway).length,
      urgent: urgent.length,
      problems: problems.length,
      driversFree: counts.driversFree,
      vehiclesFree: counts.vehiclesFree,
    },
    openNeeds: needs.value.filter((need) => need.status === 'open'),
    plannedNeeds: needs.value.filter((need) => need.status === 'planned'),
    urgent,
    trips: list,
    tours: activeTours.map((tour) => ({
      tour,
      driver: driverName(tour.driverId),
      vehicle: vehicleName(tour.vehicleId),
      legs: tourLegs(tour),
      nextStop: tour.stops.find((stop) => !stop.done) ?? null,
      done: tour.stops.filter((stop) => stop.done).length,
    })),
    problems,
    resources: logisticsResources,
  }
}

// ── Projektkarte ───────────────────────────────────────────────

export type GaHealth = 'ok' | 'attention' | 'critical'
export type GaBlocker = { kind: 'problem' | 'late' | 'unknown' | 'deadline' | 'vehicle' | 'dependency'; text: string }

export type GaProjectBoard = {
  order: GaOrder
  health: GaHealth
  progress: number
  material: number | null
  deadline: Date | null
  currentTask: GaAufgabe | null
  helpers: ReturnType<typeof helperStaffing>
  packs: GaPalette[]
  transport: ReturnType<typeof transportStatus>
  nextResources: GaResourceNeed[]
  blockers: GaBlocker[]
  attention: string[]
}

export function projectBoard(order: GaOrder): GaProjectBoard {
  const tasks = linkedTasks(order)
  const { palettes, projects } = useGaPackenMock()
  const { needs: dispoNeeds } = useGaDispoMock()
  const blockers: GaBlocker[] = []
  const attention: string[] = []

  const packProject = projects.value.find((row) => row.id === order.links.packProjectId)
  if (packProject) {
    for (const line of packProject.lines) {
      if (lineMissing(line) > 0 && line.supply?.cause === 'late') blockers.push({ kind: 'late', text: `${line.label}: ${line.supply.note}` })
      if (lineMissing(line) > 0 && line.supply?.cause === 'unknown') blockers.push({ kind: 'unknown', text: `${line.label}: ${line.supply.note}` })
    }
  }
  for (const id of order.links.dispoNeedIds) {
    const need = dispoNeeds.value.find((row) => row.id === id)
    if (need?.problem && need.status !== 'done') blockers.push({ kind: 'problem', text: need.title })
    const tour = need?.tourId ? useGaDispoMock().tours.value.find((row) => row.id === need.tourId) : undefined
    if (tour?.problem?.kind === 'vehicleDefect') blockers.push({ kind: 'vehicle', text: tour.name })
  }
  for (const issue of deadlineIssues(order)) blockers.push({ kind: 'deadline', text: issue })
  for (const task of tasks) {
    const waiting = blockedBy(task)
    if (task.status === 'progress' && waiting.length) blockers.push({ kind: 'dependency', text: `${task.title} wartet auf ${waiting.map((entry) => entry.title).join(', ')}` })
  }

  const helpers = helperStaffing(order)
  if (helpers.need > helpers.assigned) attention.push('helpers')
  const resources = needsOfOrder(order.id)
  if (resources.some((row) => row.status !== 'planned')) attention.push('resources')
  const material = materialProgress(order)
  if (packProject && projectTotals(packProject).missing > 0) attention.push('material')
  const transport = transportStatus(order)
  if (transport.open > 0) attention.push('transport')

  const health: GaHealth = order.status === 'done' ? 'ok' : blockers.length ? 'critical' : attention.length ? 'attention' : 'ok'
  const currentTask = tasks.find((task) => task.status === 'progress')
    ?? tasks.filter((task) => task.status === 'open').sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime())[0]
    ?? null

  return {
    order,
    health,
    progress: buildProgress(order),
    material,
    deadline: order.build?.readyBy ?? order.deadline,
    currentTask,
    helpers,
    packs: packProject ? palettes.value.filter((row) => row.projectId === packProject.id) : [],
    transport,
    nextResources: resources.filter((row) => row.status !== 'planned' || (row.planned && row.planned.from > new Date())).slice(0, 3),
    blockers,
    attention,
  }
}

export function projectBoards(filter: GaDisplayFilter): GaProjectBoard[] {
  return useGaAuftraegeMock().orders.value
    .filter((order) => order.type === 'build' && matchesOrderFilter(order, filter))
    .map(projectBoard)
}

/** Position des Auftrags auf der Geländekarte. */
export function orderPlaceName(order: GaOrder, known: (name: string) => boolean = hasPlace): string {
  return [order.title, order.location, order.build?.project ?? ''].find((name) => known(name)) ?? 'Zentrallager'
}

// ── Gesamt ─────────────────────────────────────────────────────

export function overall(filter: GaDisplayFilter) {
  const { tasks } = useGaAufgabenMock()
  const visibleTasks = tasks.value.filter((task) => matchesTaskFilter(task, filter))
  const running = visibleTasks.filter((task) => task.status === 'progress' && task.kind !== 'logistik')
  const next = visibleTasks
    .filter((task) => task.status === 'open')
    .sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime())
    .slice(0, 6)
  const boards = projectBoards(filter)
  const material = materialBoard(filter)
  const logistics = logistikBoard(filter)
  return {
    running,
    next,
    tasks: {
      open: visibleTasks.filter((task) => task.status === 'open').length,
      progress: visibleTasks.filter((task) => task.status === 'progress').length,
      done: visibleTasks.filter((task) => task.status === 'done').length,
      avg: visibleTasks.length ? Math.round(visibleTasks.reduce((sum, task) => sum + taskProgress(task), 0) / visibleTasks.length) : 0,
    },
    boards,
    material,
    logistics,
    problems: [
      ...boards.flatMap((board) => board.blockers.map((blocker) => ({ project: board.order.title, blocker }))),
    ],
  }
}

export type { GaDispoNeed, GaDispoTour }
