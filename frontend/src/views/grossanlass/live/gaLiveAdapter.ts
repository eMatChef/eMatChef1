/**
 * Live-Adapter der GA-Anzeigen: wandelt die echten Daten des Grossanlasses (`GaLiveData`, vom Backend aus den bestehenden
 * Fachservices) in dieselben Sichten um, die die Anzeigekomponenten (`DisplayMaterial`, `DisplayLogistik`, `DisplayGesamt`,
 * `DisplayProjekt`, `DisplayFeed`) aus den Demo-Daten erhalten. Beim Umschalten Demo ↔ Live ändert sich dadurch nur die
 * Datenquelle, nie die Darstellung.
 *
 * Die Statusberechnungen des Packens (`projectStatus`, `projectTotals`, `lineMissing`, …) sind die der Demo-Ansicht; die
 * Zahlen kommen aus dem Materialfortschritt des Backends. Es werden keine Werte erfunden: was die Fachdaten nicht kennen
 * (ETA, GPS, Terminvorschläge), bleibt leer.
 */
import type {
  GaLiveData,
  GaLiveFeedEvent,
  GaLiveGroup,
  GaLiveMaterialItem,
  GaLiveNeed,
  GaLivePack,
  GaLiveTask,
  GaLiveWarning,
} from '@/api/displayLayout'
import { GA_FAHRAUFTRAG_STEPS, taskProgress, type GaAufgabe } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import {
  linePackableNow,
  lineMissing,
  packStatusCounts,
  projectStatus,
  projectTotals,
  type GaPackNeedLine,
  type GaPackProject,
  type GaPalette,
  type GaPaletteStatus,
  type GaSupplyInfo,
} from '@/views/grossanlass/packen/gaPackenMock'
import type { GaLiveEvent } from './gaLiveEvents'
import type { GaDisplayFilter, GaHealth, GaProjectBoard, GaTrip, logistikBoard, materialBoard, overall } from './gaLiveModel'

/** Wanduhrzeit ohne Zeitzone (Business-Zeit des Anlasses) als lokales Datum. */
export function parseWall(iso: string | null | undefined): Date | null {
  if (!iso) return null
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? null : d
}

export interface LiveFilterContext {
  groups: Map<string, GaLiveGroup>
  filter: GaDisplayFilter
}

function context(data: GaLiveData, filter: GaDisplayFilter): LiveFilterContext {
  return { groups: new Map((data.groups ?? []).map((g) => [g.id, g])), filter }
}

function filterActive(f: GaDisplayFilter): boolean {
  return !!(f.ressort || f.bereich || f.project.trim())
}

/**
 * Ressort-/Bereichsfilter der Widgets auf Basis der echten GA-Hierarchie: `ressort` und `bereich` müssen dem Namen des
 * Ressorts bzw. Bereichs entsprechen, `project` ist ein Namensteil der Kette Ressort › … › Auftrag. Zeilen ohne bekannte
 * Gruppe (z. B. Fahrzeugbedarf ohne Bauprojekt) erscheinen nur ohne aktiven Filter.
 */
export function matchesGroup(ctx: LiveFilterContext, groupId: string | null | undefined): boolean {
  if (!filterActive(ctx.filter)) return true
  const g = groupId ? ctx.groups.get(groupId) : undefined
  if (!g) return false
  const needle = ctx.filter.project.trim().toLowerCase()
  return (!ctx.filter.ressort || g.ressort === ctx.filter.ressort)
    && (!ctx.filter.bereich || g.bereich === ctx.filter.bereich)
    && (!needle || g.origin.join(' ').toLowerCase().includes(needle))
}

/** Auswahlwerte der Filterfelder (für den Eigenschaftendialog) aus den echten Gruppen. */
export function filterOptions(groups: GaLiveGroup[], key: 'filter_ressort' | 'filter_bereich' | 'filter_project', current: { ressort: string; bereich: string }): string[] {
  const rows = groups.filter((g) => (key === 'filter_ressort' || !current.ressort || g.ressort === current.ressort)
    && (key !== 'filter_project' || !current.bereich || g.bereich === current.bereich))
  const values = new Set(rows.map((g) => (key === 'filter_ressort' ? g.ressort : key === 'filter_bereich' ? g.bereich : (g.kind === 'auftrag' ? g.name : ''))))
  return [...values].filter(Boolean).sort()
}

// ── Material ───────────────────────────────────────────────────────────────

const PALETTE_STATUS: Record<GaLivePack['status'], GaPaletteStatus> = {
  staging: 'packing',
  trip_released: 'ready',
  in_transit: 'sent',
  at_place: 'delivered',
}

function supplyOf(item: GaLiveMaterialItem, missing: number): GaSupplyInfo | null {
  if (missing <= 0) return null
  const coveredNotHere = Math.max(0, item.covered - Math.max(item.received, item.packed))
  return { cause: coveredNotHere > 0 ? (item.open > 0 ? 'partial' : 'ordered') : 'notProcured', eta: null, note: '' }
}

export function liveMaterialBoard(data: GaLiveData, filter: GaDisplayFilter): ReturnType<typeof materialBoard> {
  const ctx = context(data, filter)
  const items = (data.material?.items ?? []).filter((i) => matchesGroup(ctx, i.group_id))
  const byGroup = new Map<string, GaLiveMaterialItem[]>()
  for (const item of items) byGroup.set(item.group_id, [...(byGroup.get(item.group_id) ?? []), item])

  const projects = [...byGroup.entries()].map(([groupId, rows]) => {
    const group = ctx.groups.get(groupId)
    const needLines = rows.map((item): GaPackNeedLine => {
      const packed = Math.min(item.packed, item.required)
      // «verfügbar» = physisch vorhanden, aber noch nicht gepackt
      const available = Math.max(0, Math.min(item.received, item.required) - packed)
      return { id: item.id, label: item.label, needed: item.required, available, packed }
    })
    const project: GaPackProject = { id: groupId, ressort: group?.ressort ?? '', bereich: group?.bereich ?? '', name: group?.name ?? '', target: '', lines: needLines }
    const palettes: GaPalette[] = (data.material?.packs ?? [])
      .filter((p) => p.group_id === groupId)
      .map((p, i) => ({
        id: p.id, number: i + 1, code: p.code, projectId: groupId, projectName: project.name, ressort: project.ressort, target: p.target,
        title: p.title, lines: p.lines, status: PALETTE_STATUS[p.status], labelPrinted: false, fahrauftragSent: p.status !== 'staging',
      }))
    const totals = projectTotals(project)
    return {
      project,
      status: projectStatus(project),
      progress: totals.progress,
      needed: totals.needed,
      available: totals.available,
      packed: totals.packed,
      missing: totals.missing,
      palettes,
      deadline: parseWall(group?.window_end),
      lines: needLines.map((line, i) => {
        const item = rows[i]!
        const missing = lineMissing(line)
        return {
          line,
          missing,
          availableNotPacked: linePackableNow(line),
          packedWaiting: Math.max(0, line.packed - Math.min(line.packed, item.at_place)),
          onSite: Math.min(line.packed, item.at_place),
          supply: supplyOf(item, missing),
        }
      }),
    }
  })

  const palettes = projects.flatMap((p) => p.palettes)
  const counts = packStatusCounts(projects.map((p) => p.project))
  const missingLines = projects.flatMap((p) => p.lines.filter((s) => s.missing > 0).map((state) => ({ project: p.project, state })))
  return {
    projects,
    kpis: {
      packOpen: projects.filter((p) => p.status !== 'done').length,
      blocked: counts.waiting,
      packsReady: palettes.filter((p) => p.status === 'ready' || p.status === 'sent' || p.status === 'delivered').length,
      waitingTransport: palettes.filter((p) => p.status === 'ready' || p.status === 'sent').length,
      missingItems: missingLines.length,
    },
    missing: missingLines,
    waitingTransport: palettes.filter((p) => p.status === 'ready' || p.status === 'sent'),
    incoming: missingLines.filter((m) => m.state.supply && ['ordered', 'partial'].includes(m.state.supply.cause)),
  } as unknown as ReturnType<typeof materialBoard>
}

// ── Logistik ───────────────────────────────────────────────────────────────

function pad(n: number): string {
  return String(n).padStart(2, '0')
}

function timingLabel(need: GaLiveNeed, now: string | undefined): string {
  const a = parseWall(need.starts_at)
  const b = parseWall(need.ends_at)
  if (!a) return ''
  const day = need.starts_at.slice(0, 10) === (now ?? '').slice(0, 10) ? '' : `${pad(a.getDate())}.${pad(a.getMonth() + 1)}. `
  return `${day}${pad(a.getHours())}:${pad(a.getMinutes())}${b ? `–${pad(b.getHours())}:${pad(b.getMinutes())}` : ''}`
}

export function liveLogistikBoard(data: GaLiveData, filter: GaDisplayFilter): ReturnType<typeof logistikBoard> {
  const ctx = context(data, filter)
  const logistics = data.logistics
  const needs = (logistics?.needs ?? []).filter((n) => matchesGroup(ctx, n.group_id))
  const tours = (logistics?.tours ?? []).filter((t) => !filterActive(filter) || t.group_ids.some((id) => matchesGroup(ctx, id)))
  const toNeed = (n: GaLiveNeed) => ({
    id: n.id, source: 'planung', title: n.title, from: n.from, to: n.to, timingKind: 'window', timingLabel: timingLabel(n, data.generated_at),
    dayOffset: 0, hour: parseWall(n.starts_at)?.getHours() ?? 0, priority: n.urgent ? 'urgent' : 'normal', ready: n.ready, cargo: n.cargo,
    target: n.target, requirements: [] as string[], scope: 'intern', status: n.status, tourId: n.tour_id ?? undefined,
  })
  const transports = needs.filter((n) => n.kind === 'transport')
  const tourById = new Map((logistics?.tours ?? []).map((t) => [t.id, t]))
  const trips = transports.filter((n) => n.status === 'underway').map((n) => {
    const tour = n.tour_id ? tourById.get(n.tour_id) : undefined
    return {
      id: n.id, task: { id: n.id }, stepIndex: GA_FAHRAUFTRAG_STEPS.indexOf('onTheWay'), stepKey: 'onTheWay' as const, mode: 'none' as const,
      progress: null, startedAt: null, eta: null, underway: true, done: false, from: n.from || n.title, to: n.to || n.target,
      vehicle: tour?.vehicle ?? '', driver: tour?.driver ?? '',
    }
  }) as unknown as GaTrip[]
  const busyDrivers = tours.filter((t) => t.driver && t.status !== 'done').length
  const busyVehicles = tours.filter((t) => t.vehicle && t.status !== 'done').length
  const problems = tours.filter((t) => t.has_conflict).map((tour) => ({ kind: 'tour' as const, tour: { id: tour.id, name: tour.name, problem: { kind: 'other', note: '' } } }))
  return {
    kpis: {
      open: transports.filter((n) => n.status === 'open').length,
      planned: transports.filter((n) => n.status === 'planned').length,
      underway: transports.filter((n) => n.status === 'underway').length,
      urgent: transports.filter((n) => n.urgent).length,
      problems: problems.length,
      driversFree: Math.max(0, (logistics?.drivers ?? 0) - busyDrivers),
      vehiclesFree: Math.max(0, (logistics?.vehicles ?? 0) - busyVehicles),
    },
    openNeeds: transports.filter((n) => n.status === 'open').map(toNeed),
    plannedNeeds: transports.filter((n) => n.status === 'planned').map(toNeed),
    urgent: transports.filter((n) => n.urgent).map(toNeed),
    trips,
    tours: tours.map((tour) => ({
      tour: {
        id: tour.id, name: tour.name, trailer: tour.trailer, status: tour.status, delayMin: 0, problem: tour.has_conflict ? { kind: 'other', note: '' } : undefined,
        stops: tour.stops.map((s, i) => ({ id: `${tour.id}-${i}`, kind: s.kind, place: s.label, label: '', done: s.done })),
      },
      driver: tour.driver,
      vehicle: tour.vehicle,
      legs: [],
      nextStop: (() => {
        const i = tour.stops.findIndex((s) => !s.done)
        return i < 0 ? null : { id: `${tour.id}-${i}` }
      })(),
      done: tour.stops.filter((s) => s.done).length,
    })),
    problems,
    // Fahrzeugbedarf aus der Planung (ohne Transportauftrag), «Eingeplant», sobald er einer Tour zugeteilt ist.
    resources: needs.filter((n) => n.kind === 'vehicle').map((n) => ({
      id: n.id, qty: 1, label: n.title, note: n.cargo, status: n.tour_id ? 'planned' : 'need',
      planned: n.tour_id ? { from: parseWall(n.starts_at)!, to: parseWall(n.ends_at)! } : null,
      earliest: parseWall(n.starts_at)!, latest: parseWall(n.ends_at)!,
    })),
  } as unknown as ReturnType<typeof logistikBoard>
}

// ── Aufgaben, Projekte, Gesamt ──────────────────────────────────────────────

function toTask(task: GaLiveTask): GaAufgabe {
  return {
    id: task.id, kind: 'bau', title: task.title, origin: task.origin, ressort: task.ressort, bereich: task.bereich, people: [],
    status: task.status, startsAt: parseWall(task.starts_at) ?? new Date(0), endsAt: parseWall(task.ends_at) ?? new Date(0),
    steps: Array.from({ length: task.steps_total }, (_, i) => ({ id: `${task.id}-${i}`, label: '', done: i < task.steps_done })),
    routeStepIndex: 0, blocked: task.blocked ? { reason: task.blocked_reason ?? '', at: null } : null,
  } as unknown as GaAufgabe
}

export function liveTasks(data: GaLiveData, filter: GaDisplayFilter): GaAufgabe[] {
  const ctx = context(data, filter)
  return (data.tasks ?? []).filter((t) => matchesGroup(ctx, t.group_id)).map(toTask)
}

export function liveProjectBoards(data: GaLiveData, filter: GaDisplayFilter): GaProjectBoard[] {
  const ctx = context(data, filter)
  const needs = data.logistics?.needs ?? []
  return (data.projects ?? []).filter((p) => matchesGroup(ctx, p.group_id)).map((p) => {
    const group = ctx.groups.get(p.group_id)
    const tasks = (data.tasks ?? []).filter((t) => t.group_id === p.group_id)
    const blockers = [
      ...tasks.filter((t) => t.blocked).map((t) => ({ kind: 'problem' as const, text: `${t.title}${t.blocked_reason ? `: ${t.blocked_reason}` : ''}` })),
      ...tasks.filter((t) => !t.blocked && t.overdue).map((t) => ({ kind: 'late' as const, text: t.title })),
    ]
    const own = needs.filter((n) => n.group_id === p.group_id && n.kind === 'transport')
    const attention: string[] = []
    if (p.helpers.need > p.helpers.assigned) attention.push('helpers')
    if (p.material && p.material.open_items > 0) attention.push('material')
    if (own.some((n) => n.status === 'open' || n.status === 'planned')) attention.push('transport')
    const health: GaHealth = p.progress >= 100 && !blockers.length ? 'ok' : blockers.length ? 'critical' : attention.length ? 'attention' : 'ok'
    const current = tasks.find((t) => t.status === 'progress') ?? tasks.find((t) => t.status === 'open')
    return {
      order: { id: p.group_id, title: p.name, ressort: group?.ressort ?? '', bereich: group?.bereich ?? '', build: null },
      health,
      progress: p.progress,
      material: p.material && p.material.required > 0 ? Math.round((p.material.packed / p.material.required) * 100) : null,
      deadline: parseWall(p.deadline),
      currentTask: current ? { title: current.title } : null,
      helpers: { need: p.helpers.need, assigned: p.helpers.assigned, people: [] },
      packs: (data.material?.packs ?? []).filter((k) => k.group_id === p.group_id).map((k) => ({ code: k.code, status: PALETTE_STATUS[k.status] })),
      transport: {
        open: own.filter((n) => n.status === 'open' || n.status === 'planned').length,
        underway: own.filter((n) => n.status === 'underway').length,
        done: 0,
      },
      nextResources: [],
      blockers,
      attention,
    } as unknown as GaProjectBoard
  })
}

export function liveOverall(data: GaLiveData, filter: GaDisplayFilter): ReturnType<typeof overall> {
  const tasks = liveTasks(data, filter)
  const boards = liveProjectBoards(data, filter)
  const material = liveMaterialBoard(data, filter)
  const logistics = liveLogistikBoard(data, filter)
  return {
    running: tasks.filter((t) => t.status === 'progress'),
    next: tasks.filter((t) => t.status === 'open').sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime()).slice(0, 6),
    tasks: {
      open: tasks.filter((t) => t.status === 'open').length,
      progress: tasks.filter((t) => t.status === 'progress').length,
      done: tasks.filter((t) => t.status === 'done').length,
      avg: tasks.length ? Math.round(tasks.reduce((sum, t) => sum + taskProgress(t), 0) / tasks.length) : 0,
    },
    boards,
    material,
    logistics,
    problems: boards.flatMap((b) => b.blockers.map((blocker) => ({ project: b.order.title, blocker }))),
  } as unknown as ReturnType<typeof overall>
}

// ── Ereignisse und Warnungen ─────────────────────────────────────────────────

export function liveFeed(data: GaLiveData, filter: GaDisplayFilter, text: (e: GaLiveFeedEvent) => string, areas?: string[]): GaLiveEvent[] {
  const ctx = context(data, filter)
  return (data.feed ?? [])
    .filter((e) => (!filterActive(filter) || matchesGroup(ctx, e.group_id)) && (!areas || areas.includes(e.area)))
    .map((e) => ({
      id: e.id, at: parseWall(e.at) ?? new Date(0), area: e.area === 'logistics' ? 'logistics' : 'task',
      severity: e.severity === 'warning' ? 'warning' : e.severity === 'success' ? 'success' : 'info',
      // Das Protokoll kennt keine Personen auf dem Bildschirm: der Verfasser bleibt leer.
      actor: '', text: text(e), detail: e.detail, project: ctx.groups.get(e.group_id ?? '')?.name ?? '',
    }))
}

export function liveWarnings(data: GaLiveData, filter: GaDisplayFilter): GaLiveWarning[] {
  const ctx = context(data, filter)
  return (data.warnings ?? []).filter((w) => !filterActive(filter) || (w.group_ids?.length ? w.group_ids.some((id) => matchesGroup(ctx, id)) : matchesGroup(ctx, w.group_id)))
}
