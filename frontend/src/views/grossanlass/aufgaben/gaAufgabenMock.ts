/**
 * UI-Prototyp «Aufgaben» (Grossanlass): lokale Demo-Daten und lokaler UI-State.
 * Keine API, kein Backend. Später: Ressort/Bauauftrag → Planung → Material/Logistik → Aufgabe → Helfer/Fahrer → Fortschritt.
 */
// Live-Lagebild: Aktionen werden im Ereignislog protokolliert.
import { logEvent, markTripStarted } from '@/views/grossanlass/live/gaLiveEvents'
import { computed, reactive } from 'vue'

export type GaAufgabeKind = 'arbeit' | 'material' | 'logistik' | 'werkstatt'
const ROUTE_STEP_TEXT: Record<string, string> = {
  accepted: 'angenommen',
  atSupplier: 'beim Abholort',
  loaded: 'beladen',
  onTheWay: 'unterwegs',
  arrived: 'angekommen',
  unloaded: 'entladen',
}

export type GaAufgabeStatus = 'open' | 'progress' | 'done'
export type GaAufgabeStatusFilter = 'all' | GaAufgabeStatus | 'overdue'
export type GaAufgabePeriod = 'all' | 'today' | 'week'

export const GA_FAHRAUFTRAG_STEPS = [
  'accepted',
  'atSupplier',
  'loaded',
  'onTheWay',
  'arrived',
  'unloaded',
] as const
export type GaFahrauftragStep = (typeof GA_FAHRAUFTRAG_STEPS)[number]

export type GaAufgabeMaterial = { label: string; qty: number; pack?: string }
export type GaAufgabeStep = { label: string; done: boolean }
export type GaAufgabeRoute = {
  /** geschätzte Fahrzeit in Minuten (ETA-Simulation) */
  etaMin?: number
  from: string
  to: string
  vehicle: string
  cargo: string[]
}

export type GaAufgabe = {
  id: string
  kind: GaAufgabeKind
  title: string
  /** Ursprung als Breadcrumb, z. B. Ressort › Bereich › Bauprojekt */
  origin: string[]
  ressort: string
  bereich: string
  startsAt: Date
  endsAt: Date
  /** frei formulierte Frist, z. B. «Benötigt bis 10:00» */
  deadlineLabel?: string
  people: string[]
  status: GaAufgabeStatus
  description: string
  materials: GaAufgabeMaterial[]
  /** Arbeit/Material/Werkstatt: Checkliste */
  steps: GaAufgabeStep[]
  /** Fahrauftrag: -1 = noch nicht angenommen, sonst Index in GA_FAHRAUFTRAG_STEPS */
  routeStepIndex: number
  route?: GaAufgabeRoute
  /** Helferpool: benötigte Helfer mit Fähigkeit (Zeitraum = startsAt–endsAt) */
  helperNeed?: { skill: string; count: number }
  /** optionale Abhängigkeit: erst möglich, wenn diese Aufgaben erledigt sind */
  dependsOn?: string[]
}

/** Demo-Helfer für «Meine Aufgaben». */
export const GA_DEMO_ME = 'Peter'
export const GA_DEMO_PEOPLE = ['Peter', 'Lea', 'Marco', 'Sina', 'Jonas']

function at(dayOffset: number, hour: number, minute = 0): Date {
  const date = new Date()
  date.setDate(date.getDate() + dayOffset)
  date.setHours(hour, minute, 0, 0)
  return date
}

/** Fixes Datum in der Anlass-Woche (Demo, unabhängig vom heutigen Tag). */
export function anlassAt(day: number, hour: number, minute = 0): Date {
  return new Date(2026, 10, day, hour, minute, 0, 0)
}

function steps(labels: string[], doneCount: number): GaAufgabeStep[] {
  return labels.map((label, index) => ({ label, done: index < doneCount }))
}

function buildDemoTasks(): GaAufgabe[] {
  return [
    {
      id: 'ga-demo-arbeit-1',
      kind: 'arbeit',
      title: 'Tresen-Unterkonstruktion montieren',
      origin: ['Demo-Bauten', 'Bühne & Gerüst', 'Bar West · Holzbau'],
      ressort: 'Demo-Bauten',
      bereich: 'Bühne & Gerüst',
      startsAt: at(0, 9),
      endsAt: at(0, 12),
      people: ['Peter', 'Lea'],
      status: 'progress',
      description: 'Unterkonstruktion für den Tresen der Bar West nach Bauplan montieren, ausrichten und für die Deckplatte vorbereiten.',
      materials: [
        { label: 'Kantholz 6×12 cm', qty: 14, pack: 'Pack #41' },
        { label: 'Schraubensatz 6×120', qty: 2, pack: 'Pack #41' },
      ],
      steps: steps(
        ['Material prüfen', 'Zuschnitt', 'Rahmen links', 'Rahmen rechts', 'Querstreben', 'Verschrauben', 'Ausrichten', 'Deckplatte auflegen', 'Kanten schleifen', 'Abnahme'],
        7,
      ),
      routeStepIndex: -1,
    },
    {
      id: 'ga-demo-material-1',
      kind: 'material',
      title: 'Pack #42 für Bar West bereitstellen',
      origin: ['Demo-Bauten', 'Bühne & Gerüst', 'Bar West · Bauauftrag'],
      ressort: 'Demo-Bauten',
      bereich: 'Bühne & Gerüst',
      startsAt: at(0, 8),
      endsAt: at(0, 10),
      deadlineLabel: 'Benötigt bis 10:00',
      people: [],
      status: 'open',
      description: 'Pack #42 im Lager A zusammenstellen und am Ausgabeplatz für das Bau-Team bereitstellen.',
      materials: [
        { label: 'Tresenplatte 200×60', qty: 2, pack: 'Pack #42' },
        { label: 'Winkelverbinder', qty: 40, pack: 'Pack #42' },
        { label: 'Akkuschrauber', qty: 2, pack: 'Pack #42' },
      ],
      steps: steps(['Pack zusammenstellen', 'Vollständigkeit prüfen', 'Am Ausgabeplatz bereitstellen'], 0),
      routeStepIndex: -1,
    },
    {
      id: 'ga-demo-logistik-1',
      kind: 'logistik',
      title: 'Häberli Holz → Zentrallager',
      origin: ['Demo-Material&Logistik', 'Fahrauftrag'],
      ressort: 'Demo-Material&Logistik',
      bereich: 'Transporte',
      startsAt: at(0, 13, 30),
      endsAt: at(0, 16),
      deadlineLabel: 'Abholung 13:30',
      people: ['Peter'],
      status: 'open',
      description: 'Bestelltes Holz bei Häberli Holz abholen und ins Zentrallager liefern.',
      materials: [{ label: 'Kantholz 6×12 cm', qty: 14 }, { label: 'Schwartenbund', qty: 1 }],
      steps: [],
      routeStepIndex: -1,
      route: {
        from: 'Häberli Holz, Sägerei',
        to: 'Zentrallager',
        vehicle: 'Sprinter 2',
        etaMin: 30,
        cargo: ['14× Kantholz 6×12 cm', '1× Schwartenbund'],
      },
    },
    {
      id: 'ga-demo-werkstatt-1',
      kind: 'werkstatt',
      title: 'Rücklicht prüfen',
      origin: ['Fahrzeuge', 'Anhänger 4'],
      ressort: 'Demo-Material&Logistik',
      bereich: 'Fahrzeuge',
      startsAt: at(0, 7),
      endsAt: at(1, 17),
      deadlineLabel: 'Benötigt bis morgen',
      people: [],
      status: 'open',
      description: 'Rücklicht links am Anhänger 4 funktioniert nicht. Ursache suchen und beheben, bevor der Anhänger wieder ausgeliehen wird.',
      materials: [{ label: 'Glühlampe 21 W', qty: 2 }],
      steps: steps(['Sichtprüfung', 'Stecker und Kabel messen', 'Leuchte ersetzen', 'Funktionstest'], 0),
      routeStepIndex: -1,
    },
    {
      id: 'ga-demo-aufbau-1',
      kind: 'arbeit',
      title: 'Aufbau Bar West: Grundgerüst',
      origin: ['Demo-Bauten', 'Bühne & Gerüst', 'Bar West · Holzbau'],
      ressort: 'Demo-Bauten',
      bereich: 'Bühne & Gerüst',
      startsAt: anlassAt(3, 9),
      endsAt: anlassAt(3, 15),
      people: ['Nora', 'Tim'],
      status: 'open',
      description: 'Grundgerüst der Bar West stellen.',
      materials: [{ label: 'Kantholz 6×12 cm', qty: 14, pack: 'Pack #42' }],
      steps: steps(['Standort einmessen', 'Gerüst stellen', 'Verschrauben'], 0),
      routeStepIndex: -1,
    },
    {
      id: 'ga-demo-holz-need',
      kind: 'arbeit',
      title: 'Tresen-Verkleidung Bar West',
      origin: ['Demo-Bauten', 'Bühne & Gerüst', 'Bar West · Holzbau'],
      ressort: 'Demo-Bauten',
      bereich: 'Bühne & Gerüst',
      startsAt: anlassAt(4, 8),
      endsAt: anlassAt(4, 12),
      people: [],
      status: 'open',
      description: 'Tresen der Bar West verkleiden. Braucht vier Helfer mit Holzbau-Erfahrung.',
      materials: [{ label: 'Schalbretter 24×200', qty: 40, pack: 'Pack #44' }],
      steps: steps(['Zuschnitt', 'Verkleiden', 'Kanten schleifen'], 0),
      routeStepIndex: -1,
      helperNeed: { skill: 'Holzbau', count: 4 },
    },
    {
      id: 'ga-demo-zelt-1',
      kind: 'arbeit',
      title: 'Crew-Zelt aufstellen',
      origin: ['Demo-Bauten', 'Zelte', 'Crew-Zelt Backstage'],
      ressort: 'Demo-Bauten',
      bereich: 'Zelte',
      startsAt: anlassAt(4, 8),
      endsAt: anlassAt(4, 12),
      people: ['Marco'],
      status: 'open',
      description: 'Crew-Zelt Backstage aufstellen und verspannen.',
      materials: [],
      steps: steps(['Boden legen', 'Zelt aufstellen', 'Verspannen'], 0),
      routeStepIndex: -1,
    },
    {
      id: 'ga-demo-stapler-1',
      kind: 'material',
      title: 'Paletten verladen',
      origin: ['Demo-Material&Logistik', 'Zentrallager'],
      ressort: 'Demo-Material&Logistik',
      bereich: 'Zentrallager',
      startsAt: anlassAt(4, 7),
      endsAt: anlassAt(4, 9),
      people: ['Felix'],
      status: 'open',
      description: 'Fertige Paletten mit dem Stapler verladen.',
      materials: [{ label: 'Palette PK-0042', qty: 1 }],
      steps: steps(['Paletten bereitstellen', 'Verladen'], 0),
      routeStepIndex: -1,
    },
    {
      id: 'ga-demo-kueche-1',
      kind: 'arbeit',
      title: 'Catering-Zelt einrichten',
      origin: ['Demo-Verpflegung', 'Küche', 'Catering-Zelt'],
      ressort: 'Demo-Verpflegung',
      bereich: 'Küche',
      startsAt: anlassAt(5, 8),
      endsAt: anlassAt(5, 12),
      people: ['Mira', 'Anja'],
      status: 'open',
      description: 'Küche im Catering-Zelt einrichten.',
      materials: [],
      steps: steps(['Geräte aufstellen', 'Tische stellen', 'Hygienecheck'], 0),
      routeStepIndex: -1,
    },
    {
      id: 'ga-demo-abbau-1',
      kind: 'arbeit',
      title: 'Abbau Info-Pagode',
      origin: ['Demo-Bauten', 'Eingang', 'Info-Pagode Eingang'],
      ressort: 'Demo-Bauten',
      bereich: 'Eingang',
      startsAt: anlassAt(7, 14),
      endsAt: anlassAt(7, 18),
      people: ['Ben'],
      status: 'open',
      description: 'Pagode abbauen und verpacken.',
      materials: [],
      steps: steps(['Beschilderung ab', 'Abspannen', 'Abbauen', 'Verpacken'], 0),
      routeStepIndex: -1,
    },
    {
      id: 'ga-demo-nov-logistik',
      kind: 'logistik',
      title: 'Zeltbau AG → Zentrallager',
      origin: ['Demo-Material&Logistik', 'Fahrauftrag'],
      ressort: 'Demo-Material&Logistik',
      bereich: 'Transporte',
      startsAt: anlassAt(5, 13),
      endsAt: anlassAt(5, 16),
      people: ['Luca'],
      status: 'open',
      description: 'Mietzelt bei der Zeltbau AG abholen.',
      materials: [{ label: 'Festzelt 6×12 m', qty: 1 }],
      steps: [],
      routeStepIndex: -1,
      route: { from: 'Zeltbau AG', to: 'Zentrallager', vehicle: 'LKW 7.5 t', etaMin: 25, cargo: ['Festzelt 6×12 m'] },
    },
    {
      id: 'ga-demo-bw-material',
      kind: 'material',
      title: 'Material geliefert',
      origin: ['Demo-Bauten', 'Bühne & Gerüst', 'Bar West · Holzbau'],
      ressort: 'Demo-Bauten',
      bereich: 'Bühne & Gerüst',
      startsAt: anlassAt(3, 7),
      endsAt: anlassAt(3, 8),
      people: [],
      status: 'done',
      description: 'Holz und Schrauben sind im Zentrallager eingetroffen.',
      materials: [],
      steps: steps(['Wareneingang quittieren'], 1),
      routeStepIndex: -1,
    },
    {
      id: 'ga-demo-bw-holz',
      kind: 'arbeit',
      title: 'Holzkonstruktion',
      origin: ['Demo-Bauten', 'Bühne & Gerüst', 'Bar West · Holzbau'],
      ressort: 'Demo-Bauten',
      bereich: 'Bühne & Gerüst',
      startsAt: anlassAt(3, 9),
      endsAt: anlassAt(3, 17),
      people: [],
      status: 'progress',
      description: 'Rahmen und Tresen der Bar West.',
      materials: [],
      steps: steps(['Rahmen', 'Tresen', 'Dachstuhl'], 1),
      routeStepIndex: -1,
      dependsOn: ['ga-demo-bw-material'],
    },
    {
      id: 'ga-demo-bw-kran',
      kind: 'arbeit',
      title: 'Kranarbeit',
      origin: ['Demo-Bauten', 'Bühne & Gerüst', 'Bar West · Holzbau'],
      ressort: 'Demo-Bauten',
      bereich: 'Bühne & Gerüst',
      startsAt: anlassAt(4, 10, 30),
      endsAt: anlassAt(4, 12, 30),
      people: [],
      status: 'open',
      description: 'Dachelemente mit dem Mobilkran heben.',
      materials: [],
      steps: steps(['Kran einrichten', 'Elemente heben'], 0),
      routeStepIndex: -1,
      dependsOn: ['ga-demo-bw-holz'],
    },
    {
      id: 'ga-demo-bw-dach',
      kind: 'arbeit',
      title: 'Dachmontage',
      origin: ['Demo-Bauten', 'Bühne & Gerüst', 'Bar West · Holzbau'],
      ressort: 'Demo-Bauten',
      bereich: 'Bühne & Gerüst',
      startsAt: anlassAt(4, 13),
      endsAt: anlassAt(4, 17),
      people: [],
      status: 'open',
      description: 'Dach montieren und abdichten.',
      materials: [],
      steps: steps(['Befestigen', 'Abdichten'], 0),
      routeStepIndex: -1,
      dependsOn: ['ga-demo-bw-kran'],
    },
    {
      id: 'ga-demo-bw-elektro',
      kind: 'arbeit',
      title: 'Elektrik',
      origin: ['Demo-Bauten', 'Bühne & Gerüst', 'Bar West · Holzbau'],
      ressort: 'Demo-Bauten',
      bereich: 'Bühne & Gerüst',
      startsAt: anlassAt(5, 8),
      endsAt: anlassAt(5, 12),
      people: [],
      status: 'open',
      description: 'Beleuchtung und Steckdosen installieren.',
      materials: [],
      steps: steps(['Leitungen', 'Leuchten', 'Prüfung'], 0),
      routeStepIndex: -1,
      dependsOn: ['ga-demo-bw-dach'],
    },
    {
      id: 'ga-demo-arbeit-2',
      kind: 'arbeit',
      title: 'Zeltboden Crew-Zelt verlegen',
      origin: ['Demo-Bauten', 'Zelte', 'Crew-Zelt Backstage'],
      ressort: 'Demo-Bauten',
      bereich: 'Zelte',
      startsAt: at(-2, 8),
      endsAt: at(-1, 12),
      people: ['Marco'],
      status: 'progress',
      description: 'Bodenplatten im Crew-Zelt verlegen und verschrauben.',
      materials: [{ label: 'Bodenplatten 1×2 m', qty: 24, pack: 'Pack #38' }],
      steps: steps(['Untergrund ebnen', 'Platten auslegen', 'Verschrauben', 'Kontrolle'], 2),
      routeStepIndex: -1,
    },
    {
      id: 'ga-demo-arbeit-3',
      kind: 'arbeit',
      title: 'Info-Pagode Eingang aufbauen',
      origin: ['Demo-Bauten', 'Eingang', 'Info-Pagode Eingang'],
      ressort: 'Demo-Bauten',
      bereich: 'Eingang',
      startsAt: at(1, 9),
      endsAt: at(1, 15),
      people: ['Sina', 'Jonas'],
      status: 'open',
      description: 'Pagode am Haupteingang aufstellen, verspannen und beschildern.',
      materials: [{ label: 'Pagode 3×3 m', qty: 1, pack: 'Pack #44' }],
      steps: steps(['Standort markieren', 'Aufstellen', 'Verspannen', 'Beschildern'], 0),
      routeStepIndex: -1,
    },
    {
      id: 'ga-demo-logistik-2',
      kind: 'logistik',
      title: 'Zentrallager → Festgelände Süd',
      origin: ['Demo-Material&Logistik', 'Fahrauftrag'],
      ressort: 'Demo-Material&Logistik',
      bereich: 'Transporte',
      startsAt: at(1, 8),
      endsAt: at(1, 11),
      deadlineLabel: 'Abfahrt 08:00',
      people: ['Lea'],
      status: 'open',
      description: 'Pack #42 und #44 auf das Festgelände bringen.',
      materials: [{ label: 'Pack #42', qty: 1 }, { label: 'Pack #44', qty: 1 }],
      steps: [],
      routeStepIndex: -1,
      route: {
        from: 'Zentrallager',
        to: 'Festgelände Süd',
        vehicle: 'Sprinter 1',
        etaMin: 20,
        cargo: ['Pack #42', 'Pack #44'],
      },
    },
    {
      id: 'ga-demo-material-2',
      kind: 'material',
      title: 'Wareneingang Schwartenbund quittieren',
      origin: ['Demo-Bauten', 'Bühne & Gerüst', 'Holzbau Eingang'],
      ressort: 'Demo-Bauten',
      bereich: 'Bühne & Gerüst',
      startsAt: at(-3, 10),
      endsAt: at(-3, 11),
      people: ['Sina'],
      status: 'done',
      description: 'Lieferung geprüft, QR geklebt, im Lager eingebucht.',
      materials: [{ label: 'Schwartenbund', qty: 1 }],
      steps: steps(['Lieferung prüfen', 'QR kleben', 'Einbuchen'], 3),
      routeStepIndex: -1,
    },
    {
      id: 'ga-demo-werkstatt-2',
      kind: 'werkstatt',
      title: 'Generator 2 warten',
      origin: ['Technik', 'Generator 2'],
      ressort: 'Demo-Technik',
      bereich: 'Strom',
      startsAt: at(-4, 9),
      endsAt: at(-2, 12),
      people: ['Jonas'],
      status: 'progress',
      description: 'Ölwechsel und Filter. Rückgabe an den Verleiher vorbereiten.',
      materials: [{ label: 'Motoröl 5 l', qty: 1 }, { label: 'Luftfilter', qty: 1 }],
      steps: steps(['Öl ablassen', 'Filter wechseln', 'Öl einfüllen', 'Probelauf'], 1),
      routeStepIndex: -1,
    },
  ]
}

export type GaAufgabenState = { tasks: GaAufgabe[] }

const state = reactive<GaAufgabenState>({ tasks: buildDemoTasks() })

export function resetGaAufgabenDemo(): void {
  state.tasks = buildDemoTasks()
}

export function useGaAufgabenMock() {
  const tasks = computed(() => state.tasks)
  return { tasks, state }
}

// ── Ableitungen ────────────────────────────────────────────────

export function isSameDay(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
}

export function isOverdue(task: Pick<GaAufgabe, 'status' | 'endsAt'>, now = new Date()): boolean {
  return task.status !== 'done' && task.endsAt.getTime() < now.getTime()
}

/** Fortschritt 0–100: Fahrauftrag über Schritte, sonst über Checkliste, erledigt = 100. */
export function taskProgress(task: GaAufgabe): number {
  if (task.status === 'done') return 100
  if (task.kind === 'logistik') {
    return Math.round(((task.routeStepIndex + 1) / GA_FAHRAUFTRAG_STEPS.length) * 100)
  }
  if (!task.steps.length) return task.status === 'progress' ? 50 : 0
  return Math.round((task.steps.filter((step) => step.done).length / task.steps.length) * 100)
}

export function statusCounts(list: GaAufgabe[], now = new Date()) {
  return {
    open: list.filter((task) => task.status === 'open').length,
    progress: list.filter((task) => task.status === 'progress').length,
    overdue: list.filter((task) => isOverdue(task, now)).length,
    done: list.filter((task) => task.status === 'done').length,
  }
}

export type GaAufgabeFilters = {
  kind: 'all' | GaAufgabeKind
  ressort: string
  bereich: string
  person: string
  period: GaAufgabePeriod
  status: GaAufgabeStatusFilter
  search: string
}

export const emptyAufgabeFilters = (): GaAufgabeFilters => ({
  kind: 'all',
  ressort: '',
  bereich: '',
  person: '',
  period: 'all',
  status: 'all',
  search: '',
})

function overlapsRange(task: GaAufgabe, from: Date, to: Date): boolean {
  return task.startsAt.getTime() <= to.getTime() && task.endsAt.getTime() >= from.getTime()
}

export function matchesFilters(
  task: GaAufgabe,
  filters: GaAufgabeFilters,
  options: { ignoreStatus?: boolean; now?: Date } = {},
): boolean {
  const now = options.now ?? new Date()
  if (filters.kind !== 'all' && task.kind !== filters.kind) return false
  if (filters.ressort && task.ressort !== filters.ressort) return false
  if (filters.bereich && task.bereich !== filters.bereich) return false
  if (filters.person && !task.people.includes(filters.person)) return false
  if (filters.period === 'today') {
    const dayStart = new Date(now)
    dayStart.setHours(0, 0, 0, 0)
    const dayEnd = new Date(now)
    dayEnd.setHours(23, 59, 59, 999)
    if (!overlapsRange(task, dayStart, dayEnd)) return false
  }
  if (filters.period === 'week') {
    const from = new Date(now)
    from.setHours(0, 0, 0, 0)
    const to = new Date(from)
    to.setDate(to.getDate() + 7)
    if (!overlapsRange(task, from, to)) return false
  }
  if (!options.ignoreStatus && filters.status !== 'all') {
    if (filters.status === 'overdue') {
      if (!isOverdue(task, now)) return false
    } else if (task.status !== filters.status) {
      return false
    }
  }
  const q = filters.search.trim().toLowerCase()
  if (q) {
    const haystack = [task.title, task.origin.join(' '), task.people.join(' '), task.description].join(' ').toLowerCase()
    if (!haystack.includes(q)) return false
  }
  return true
}

export type GaAufgabeBucket = 'overdue' | 'today' | 'tomorrow' | 'later' | 'done'

export function bucketOf(task: GaAufgabe, now = new Date()): GaAufgabeBucket {
  if (task.status === 'done') return 'done'
  if (isOverdue(task, now)) return 'overdue'
  if (task.startsAt.getTime() <= new Date(now).setHours(23, 59, 59, 999)) return 'today'
  const tomorrow = new Date(now)
  tomorrow.setDate(tomorrow.getDate() + 1)
  if (isSameDay(task.startsAt, tomorrow)) return 'tomorrow'
  return 'later'
}

/** Helfer-Sicht: nur eigene Aufgaben von heute (laufend oder anstehend, inkl. erledigte von heute). */
export function myTodayTasks(list: GaAufgabe[], me = GA_DEMO_ME, now = new Date()): GaAufgabe[] {
  const dayEnd = new Date(now)
  dayEnd.setHours(23, 59, 59, 999)
  return list
    .filter((task) => task.people.includes(me))
    .filter((task) => task.kind === 'arbeit' || task.kind === 'logistik')
    .filter((task) => task.startsAt.getTime() <= dayEnd.getTime() && task.endsAt.getTime() >= new Date(now).setHours(0, 0, 0, 0))
    .sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime())
}

// ── lokale UI-Aktionen ─────────────────────────────────────────

const actorOf = (task: GaAufgabe) => task.people[0] ?? GA_DEMO_ME
const projectOf = (task: GaAufgabe) => task.origin[task.origin.length - 1] ?? ''

export function startTask(task: GaAufgabe): void {
  if (task.status !== 'open') return
  task.status = 'progress'
  if (task.kind === 'logistik' && task.routeStepIndex < 0) task.routeStepIndex = 0
  logEvent({ area: 'task', actor: actorOf(task), text: `Aufgabe «${task.title}» gestartet`, detail: projectOf(task), project: projectOf(task) })
}

export function completeTask(task: GaAufgabe): void {
  const wasDone = task.status === 'done'
  task.status = 'done'
  task.steps.forEach((step) => {
    step.done = true
  })
  if (task.kind === 'logistik') task.routeStepIndex = GA_FAHRAUFTRAG_STEPS.length - 1
  if (!wasDone) logEvent({ area: task.kind === 'logistik' ? 'logistics' : 'task', severity: 'success', actor: actorOf(task), text: `Aufgabe «${task.title}» erledigt`, detail: projectOf(task), project: projectOf(task) })
}

export function toggleStep(task: GaAufgabe, index: number): void {
  const step = task.steps[index]
  if (!step) return
  step.done = !step.done
  if (task.status === 'open' && step.done) task.status = 'progress'
  const wasDone = task.status === 'done'
  if (task.steps.every((item) => item.done)) task.status = 'done'
  else if (task.status === 'done') task.status = 'progress'
  if (!wasDone && task.status === 'done') {
    logEvent({ area: 'task', severity: 'success', actor: actorOf(task), text: `Aufgabe «${task.title}» erledigt`, detail: projectOf(task), project: projectOf(task) })
  }
}

/** «Nächster Schritt» beim Fahrauftrag. Nach dem letzten Schritt gilt die Fahrt als erledigt. */
export function advanceRoute(task: GaAufgabe): void {
  if (task.kind !== 'logistik') return
  if (task.status === 'done') return
  if (task.routeStepIndex >= GA_FAHRAUFTRAG_STEPS.length - 1) {
    completeTask(task)
    return
  }
  task.routeStepIndex += 1
  task.status = 'progress'
  const step = GA_FAHRAUFTRAG_STEPS[task.routeStepIndex]
  if (step === 'onTheWay') markTripStarted(task.id)
  logEvent({
    area: 'logistics',
    actor: `${actorOf(task)}${task.route ? ` · ${task.route.vehicle}` : ''}`,
    text: `${task.title}: ${ROUTE_STEP_TEXT[step ?? 'accepted']}`,
    detail: step === 'onTheWay' ? 'Fahrt gestartet' : '',
    project: projectOf(task),
  })
}

export function nextRouteStep(task: GaAufgabe): GaFahrauftragStep | null {
  const next = GA_FAHRAUFTRAG_STEPS[task.routeStepIndex + 1]
  return next ?? null
}

export function assignPerson(task: GaAufgabe, person: string): void {
  if (person && !task.people.includes(person)) task.people.push(person)
}

export function unassignPerson(task: GaAufgabe, person: string): void {
  task.people = task.people.filter((name) => name !== person)
}

/** Noch nicht erledigte Aufgaben, von denen diese Aufgabe abhängt. */
export function blockedBy(task: Pick<GaAufgabe, 'dependsOn'>): GaAufgabe[] {
  return (task.dependsOn ?? []).flatMap((id) => state.tasks.find((row) => row.id === id && row.status !== 'done') ?? [])
}

export function taskById(id: string): GaAufgabe | undefined {
  return state.tasks.find((row) => row.id === id)
}
