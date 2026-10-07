/**
 * UI-Prototyp «Packen nach Bedarf» (Grossanlass): lokale Demo-Daten und lokaler State.
 * Keine API. Packen geht von Ressort/Bauprojekt/Bedarf und verfügbarem Material aus, nicht von bestehenden Packs.
 * «Fahrauftrag absenden» meldet die fertige Palette nur lokal an Logistik.
 */
import { anlassAt } from '@/views/grossanlass/aufgaben/gaAufgabenMock'
import { logEvent } from '@/views/grossanlass/live/gaLiveEvents'
import { computed, reactive } from 'vue'

export type GaPackStatus = 'packable' | 'partial' | 'waiting' | 'done'
export type GaPaletteStatus = 'packing' | 'ready' | 'sent' | 'delivered'

/** Warum fehlt Material? (Beschaffungsstand) */
export type GaSupplyCause = 'ordered' | 'pickupPlanned' | 'notProcured' | 'partial' | 'late' | 'unknown'
export type GaSupplyInfo = { cause: GaSupplyCause; eta: Date | null; note: string }

export type GaPackNeedLine = {
  id: string
  label: string
  needed: number
  /** frei verfügbar (noch nicht gepackt) */
  available: number
  packed: number
  /** Beschaffungsstand für den fehlenden Teil */
  supply?: GaSupplyInfo
}

export type GaPackProject = {
  id: string
  ressort: string
  bereich: string
  name: string
  /** Standort auf dem Gelände (für Palette/Fahrer) */
  target: string
  lines: GaPackNeedLine[]
}

export type GaPaletteLine = { label: string; qty: number }

export type GaPalette = {
  id: string
  number: number
  code: string
  projectId: string
  projectName: string
  ressort: string
  target: string
  title: string
  lines: GaPaletteLine[]
  status: GaPaletteStatus
  labelPrinted: boolean
  /** UI-only: an Logistik gemeldet */
  fahrauftragSent: boolean
}

export const GA_PACK_TARGETS = [
  'Bar West, Festgelände Nord',
  'Crew-Zelt Backstage, Festgelände Ost',
  'Info-Pagode Eingang, Haupteingang',
  'Catering-Zelt, Festgelände Süd',
]

function buildProjects(): GaPackProject[] {
  return [
    {
      id: 'pp-bar-west',
      ressort: 'Demo-Bauten',
      bereich: 'Bühne & Gerüst',
      name: 'Bar West · Holzbau',
      target: GA_PACK_TARGETS[0]!,
      lines: [
        { id: 'pl-bw-holz', label: 'Kantholz 6×12 cm', needed: 14, available: 14, packed: 0 },
        { id: 'pl-bw-schrauben', label: 'Schrauben 6×120', needed: 200, available: 120, packed: 0, supply: { cause: 'partial', eta: anlassAt(3, 10), note: '120 vorhanden, Rest bestellt' } },
        { id: 'pl-bw-platte', label: 'Tresenplatte 200×60', needed: 2, available: 0, packed: 0, supply: { cause: 'pickupPlanned', eta: anlassAt(3, 13, 30), note: 'Abholung Häberli Holz (Tour 1)' } },
      ],
    },
    {
      id: 'pp-crew-zelt',
      ressort: 'Demo-Bauten',
      bereich: 'Zelte',
      name: 'Crew-Zelt Backstage',
      target: GA_PACK_TARGETS[1]!,
      lines: [
        { id: 'pl-cz-boden', label: 'Bodenplatten 1×2 m', needed: 24, available: 24, packed: 0 },
        { id: 'pl-cz-schrauben', label: 'Terrassenschrauben', needed: 100, available: 100, packed: 0 },
      ],
    },
    {
      id: 'pp-info-pagode',
      ressort: 'Demo-Bauten',
      bereich: 'Eingang',
      name: 'Info-Pagode Eingang',
      target: GA_PACK_TARGETS[2]!,
      lines: [
        { id: 'pl-ip-pagode', label: 'Pagode 3×3 m', needed: 1, available: 0, packed: 1 },
        { id: 'pl-ip-heringe', label: 'Heringe', needed: 24, available: 0, packed: 24 },
      ],
    },
    {
      id: 'pp-catering',
      ressort: 'Demo-Verpflegung',
      bereich: 'Küche',
      name: 'Catering-Zelt',
      target: GA_PACK_TARGETS[3]!,
      lines: [
        { id: 'pl-ca-zelt', label: 'Festzelt 6×12 m', needed: 1, available: 0, packed: 0, supply: { cause: 'late', eta: anlassAt(5, 14), note: 'Lieferant meldet Verzug' } },
        { id: 'pl-ca-tische', label: 'Festbank-Garnitur', needed: 12, available: 0, packed: 0, supply: { cause: 'ordered', eta: anlassAt(5, 8), note: 'Bestellt, Eingang erwartet' } },
        { id: 'pl-ca-kuehl', label: 'Kühlschrank', needed: 1, available: 0, packed: 0, supply: { cause: 'notProcured', eta: null, note: 'Noch nicht beschafft' } },
        { id: 'pl-ca-geschirr', label: 'Geschirr-Set', needed: 1, available: 0, packed: 0, supply: { cause: 'unknown', eta: null, note: 'Termin unbekannt' } },
      ],
    },
  ]
}

function buildPalettes(): GaPalette[] {
  return [
    {
      id: 'pal-41',
      number: 41,
      code: 'PK-0041',
      projectId: 'pp-info-pagode',
      projectName: 'Info-Pagode Eingang',
      ressort: 'Demo-Bauten',
      target: GA_PACK_TARGETS[2]!,
      title: 'Pagode komplett',
      lines: [
        { label: 'Pagode 3×3 m', qty: 1 },
        { label: 'Heringe', qty: 24 },
      ],
      status: 'ready',
      labelPrinted: true,
      fahrauftragSent: false,
    },
  ]
}

export type GaPackenState = {
  projects: GaPackProject[]
  palettes: GaPalette[]
  nextNumber: number
}

const state = reactive<GaPackenState>({
  projects: buildProjects(),
  palettes: buildPalettes(),
  nextNumber: 42,
})

export function resetGaPackenDemo(): void {
  state.projects = buildProjects()
  state.palettes = buildPalettes()
  state.nextNumber = 42
}

export function useGaPackenMock() {
  return {
    projects: computed(() => state.projects),
    palettes: computed(() => state.palettes),
  }
}

// ── Ableitungen ────────────────────────────────────────────────

export function lineMissing(line: GaPackNeedLine): number {
  return Math.max(0, line.needed - line.packed - line.available)
}

/** Was jetzt gepackt werden kann: min(Rest-Bedarf, verfügbar). */
export function linePackableNow(line: GaPackNeedLine): number {
  return Math.max(0, Math.min(line.needed - line.packed, line.available))
}

export function projectTotals(project: GaPackProject) {
  const needed = project.lines.reduce((sum, line) => sum + line.needed, 0)
  const packed = project.lines.reduce((sum, line) => sum + line.packed, 0)
  const available = project.lines.reduce((sum, line) => sum + Math.min(line.available, line.needed - line.packed), 0)
  const missing = project.lines.reduce((sum, line) => sum + lineMissing(line), 0)
  return {
    needed,
    packed,
    available,
    missing,
    progress: needed ? Math.round((packed / needed) * 100) : 0,
  }
}

export function projectStatus(project: GaPackProject): GaPackStatus {
  const open = project.lines.filter((line) => line.packed < line.needed)
  if (!open.length) return 'done'
  const packableLines = open.filter((line) => linePackableNow(line) > 0)
  if (!packableLines.length) return 'waiting'
  const allComplete = open.every((line) => lineMissing(line) === 0)
  return allComplete ? 'packable' : 'partial'
}

export function packStatusCounts(projects: GaPackProject[]): Record<GaPackStatus, number> {
  const counts: Record<GaPackStatus, number> = { packable: 0, partial: 0, waiting: 0, done: 0 }
  for (const project of projects) counts[projectStatus(project)] += 1
  return counts
}

/** Linien, die jetzt sinnvoll gepackt werden können (Menge > 0). */
export function packableLines(project: GaPackProject): GaPackNeedLine[] {
  return project.lines.filter((line) => linePackableNow(line) > 0)
}

export function suggestedPaletteTitle(project: GaPackProject, lines: GaPackNeedLine[]): string {
  const base = project.name.split('·')[0]!.trim()
  if (lines.length === 1) return `${lines[0]!.label} für ${base}`
  const everything = lines.length === project.lines.filter((line) => line.packed < line.needed).length
    && lines.every((line) => lineMissing(line) === 0)
  return everything ? `Alles für ${base}` : `Teilpack für ${base}`
}

// ── lokale Aktionen ────────────────────────────────────────────

export type GaPaletteDraftLine = { lineId: string; qty: number }

export function createPalette(
  projectId: string,
  draft: GaPaletteDraftLine[],
  options: { target?: string; title?: string } = {},
): GaPalette | null {
  const project = state.projects.find((row) => row.id === projectId)
  if (!project) return null
  const picked: GaPaletteLine[] = []
  for (const entry of draft) {
    const line = project.lines.find((row) => row.id === entry.lineId)
    if (!line) continue
    const qty = Math.min(Math.max(0, Math.floor(entry.qty)), linePackableNow(line))
    if (qty <= 0) continue
    line.available -= qty
    line.packed += qty
    picked.push({ label: line.label, qty })
  }
  if (!picked.length) return null
  const number = state.nextNumber
  state.nextNumber += 1
  const palette: GaPalette = {
    id: `pal-${number}`,
    number,
    code: `PK-${String(number).padStart(4, '0')}`,
    projectId: project.id,
    projectName: project.name,
    ressort: project.ressort,
    target: options.target || project.target,
    title: options.title?.trim() || `Palette für ${project.name}`,
    lines: picked,
    status: 'packing',
    labelPrinted: false,
    fahrauftragSent: false,
  }
  state.palettes.unshift(palette)
  logEvent({ area: 'material', actor: 'Peter', text: `Pack ${palette.code} begonnen`, detail: palette.projectName, project: projectLabel(palette.projectName) })
  return palette
}

export function markPaletteReady(palette: GaPalette): void {
  if (palette.status !== 'packing') return
  palette.status = 'ready'
  logEvent({ area: 'material', severity: 'success', actor: 'Peter', text: `Pack ${palette.code} fertig → ${palette.projectName}`, detail: palette.target, project: projectLabel(palette.projectName) })
}

export function markLabelPrinted(palette: GaPalette): void {
  palette.labelPrinted = true
}

/** UI-only: meldet die fertige Palette an Logistik (kein Fahrauftrag im Backend). */
export function sendFahrauftrag(palette: GaPalette): boolean {
  if (palette.status !== 'ready' || palette.fahrauftragSent) return false
  palette.fahrauftragSent = true
  palette.status = 'sent'
  logEvent({ area: 'material', actor: 'Peter', text: `Palette ${palette.code} transportbereit`, detail: `${palette.projectName} → ${palette.target}`, project: projectLabel(palette.projectName) })
  return true
}

export function markPaletteDelivered(palette: GaPalette): void {
  palette.status = 'delivered'
  logEvent({ area: 'logistics', severity: 'success', actor: 'Fahrer', text: `Palette ${palette.code} abgeliefert`, detail: palette.target, project: projectLabel(palette.projectName) })
}

function projectLabel(name: string): string {
  return name.split('·')[0]!.trim()
}

export function paletteSummary(palette: Pick<GaPalette, 'lines'>): string {
  return palette.lines.map((line) => `${line.qty}× ${line.label}`).join(' · ')
}
