/**
 * Aufgaben der Grossanlass-Übersicht aus der REST-API (`department_grossanlass_task`).
 * Eine Aufgabe gehört zu einem Bauprojekt oder Auftrag (`group_id`); Ressort und Bereich ergeben sich aus dem Gruppenbaum.
 */
import { computed, ref } from 'vue'
import {
  acceptGrossanlassTask,
  addGrossanlassTaskDependency,
  changeGrossanlassTaskDependencyMode,
  releaseGrossanlassGroupDependency,
  releaseGrossanlassTaskDependency,
  removeGrossanlassGroupDependency,
  removeGrossanlassTaskDependency,
  type GaDependency,
  type GaDependencyMode,
  addGrossanlassTaskStep,
  blockGrossanlassTask,
  createGrossanlassBauprojektTask,
  deleteGrossanlassTaskStep,
  reorderGrossanlassTaskSteps,
  reportGrossanlassTaskDone,
  returnGrossanlassTask,
  unblockGrossanlassTask,
  updateGrossanlassTaskStep,
  getGrossanlassTasks,
  updateGrossanlassBauprojektTask,
  type GaBauprojektTask,
  type GaTaskStatus,
} from '@/api/grossanlassBauprojekt'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import { assigneesFor, buildGrossanlassGroupPathLabel, isBauprojektGroup } from '@/utils/grossanlassGroupHierarchy'
import type { GaAufgabe } from './gaAufgabenMock'

const SEP = '\u0000'
const DEFAULT_MINUTES = 60

/** Reine Abbildung API → Anzeige-Modell; Aufgaben ohne Termin übernehmen das Fenster ihres Projekts. */
export function mapApiTask(task: GaBauprojektTask, groups: GrossanlassGroup[]): GaAufgabe {
  const group = groups.find((row) => row.id === task.group_id)
  const origin = buildGrossanlassGroupPathLabel(task.group_id, groups, SEP).split(SEP).filter(Boolean)
  const fallbackStart = group?.window_start ? new Date(`${group.window_start.slice(0, 10)}T08:00:00`) : new Date(task.created_at)
  const startsAt = task.starts_at ? new Date(task.starts_at) : fallbackStart
  const endFromWindow = !task.starts_at && group?.window_end ? new Date(`${group.window_end.slice(0, 10)}T18:00:00`) : null
  const endsAt = endFromWindow ?? new Date(startsAt.getTime() + (task.duration_minutes ?? DEFAULT_MINUTES) * 60_000)
  const assignee = task.assignee_user_id
    ? (assigneesFor(task.group_id, groups).find((person) => person.value === task.assignee_user_id)?.title ?? task.assignee_user_id)
    : null
  return {
    id: task.id,
    groupId: task.group_id,
    assigneeId: task.assignee_user_id ?? null,
    scheduled: !!task.starts_at,
    kind: 'arbeit',
    title: task.title || task.description || '',
    origin,
    ressort: origin[0] ?? '',
    bereich: origin.length >= 3 ? origin[1]! : '',
    startsAt,
    endsAt,
    people: assignee ? [assignee] : [],
    status: task.status,
    description: task.title ? (task.description ?? '') : '',
    materials: [],
    steps: (task.steps ?? []).map((step) => ({ id: step.id, label: step.label, done: step.done })),
    blocked: task.blocked ? { reason: task.blocked.reason, at: task.blocked.at ?? null } : null,
    permissions: task.permissions ?? null,
    dependencies: task.dependencies ?? [],
    waiting: task.waiting === true,
    waitingOn: task.waiting_on ?? [],
    waitingHidden: task.waiting_hidden ?? 0,
    released: task.released === true,
    assignedToMe: task.assigned_to_me === true,
    orderStatus: task.order_status ?? 'open',
    rework: task.rework ? { eventId: task.rework.event_id, reason: task.rework.reason, at: task.rework.at } : null,
    events: (task.events ?? []).map((event) => ({ id: event.id, type: event.type, byName: event.by_name, note: event.note, at: event.at })),
    routeStepIndex: -1,
  }
}

export function useGaAufgaben(departmentId: () => string, includeArchived: () => boolean = () => false) {
  const groups = ref<GrossanlassGroup[]>([])
  const raw = ref<GaBauprojektTask[]>([])
  const loading = ref(false)
  const error = ref(false)
  /** Serverfehler der letzten Checklisten-/Blockier-/Statusaktion (z. B. fehlende Berechtigung). */
  const actionError = ref('')

  const tasks = computed<GaAufgabe[]>(() => raw.value.map((task) => mapApiTask(task, groups.value)))
  /** Bauprojekte und Aufträge, in denen Aufgaben angelegt werden können. */
  const projects = computed(() =>
    groups.value
      .filter((group) => isBauprojektGroup(group) || group.node_type === 'auftrag' || group.kind === 'auftrag')
      .map((group) => ({ value: group.id, title: buildGrossanlassGroupPathLabel(group.id, groups.value, ' › ') }))
      .sort((a, b) => a.title.localeCompare(b.title, 'de')),
  )

  async function load() {
    const id = departmentId()
    if (!id) return
    loading.value = true
    error.value = false
    try {
      const [loadedGroups, loadedTasks] = await Promise.all([
        getGrossanlassGroups(id, { includeArchived: includeArchived() }),
        getGrossanlassTasks(id, { includeArchived: includeArchived() }),
      ])
      groups.value = loadedGroups
      raw.value = loadedTasks
    } catch {
      error.value = true
    } finally {
      loading.value = false
    }
  }

  function replace(updated: GaBauprojektTask) {
    const index = raw.value.findIndex((row) => row.id === updated.id)
    if (index >= 0) raw.value.splice(index, 1, updated)
    else raw.value.push(updated)
  }

  async function patch(task: GaAufgabe, payload: Parameters<typeof updateGrossanlassBauprojektTask>[3]) {
    replace(await updateGrossanlassBauprojektTask(departmentId(), task.groupId!, task.id, payload))
  }

  async function run(action: () => Promise<void>) {
    actionError.value = ''
    try {
      await action()
    } catch (e) {
      const message = (e as { response?: { data?: { error?: string } } }).response?.data?.error
      actionError.value = message || 'error'
    }
  }

  const setStatus = (task: GaAufgabe, status: GaTaskStatus) => run(() => patch(task, { status }))
  const setAssignee = (task: GaAufgabe, userId: string | null) => patch(task, { assignee_user_id: userId })

  const groupOf = (task: GaAufgabe) => task.groupId!

  const addStep = (task: GaAufgabe, label: string) =>
    run(async () => replace(await addGrossanlassTaskStep(departmentId(), groupOf(task), task.id, label)))
  const setStepDone = (task: GaAufgabe, stepId: string, done: boolean) =>
    run(async () => replace(await updateGrossanlassTaskStep(departmentId(), groupOf(task), task.id, stepId, { done })))
  const removeStep = (task: GaAufgabe, stepId: string) =>
    run(async () => replace(await deleteGrossanlassTaskStep(departmentId(), groupOf(task), task.id, stepId)))
  const moveStep = (task: GaAufgabe, stepId: string, delta: -1 | 1) => run(async () => {
    const ids = task.steps.map((step) => step.id!).filter(Boolean)
    const from = ids.indexOf(stepId)
    const to = from + delta
    if (from < 0 || to < 0 || to >= ids.length) return
    ;[ids[from], ids[to]] = [ids[to]!, ids[from]!]
    replace(await reorderGrossanlassTaskSteps(departmentId(), groupOf(task), task.id, ids))
  })
  const reportDone = (task: GaAufgabe, note?: string) =>
    run(async () => replace(await reportGrossanlassTaskDone(departmentId(), groupOf(task), task.id, note)))
  const accept = (task: GaAufgabe) => run(async () => replace(await acceptGrossanlassTask(departmentId(), groupOf(task), task.id)))
  const giveBack = (task: GaAufgabe, reason: string) =>
    run(async () => replace(await returnGrossanlassTask(departmentId(), groupOf(task), task.id, reason)))
  /** Aufgabe-Abhängigkeit direkt; Bauauftrag-Abhängigkeit (geerbt) betrifft alle Aufgaben des Bauauftrags, daher neu laden. */
  const addDependency = (task: GaAufgabe, predecessorTaskId: string, mode: GaDependencyMode = 'sequential') =>
    run(async () => replace(await addGrossanlassTaskDependency(departmentId(), groupOf(task), task.id, predecessorTaskId, mode)))
  const changeDependencyMode = (task: GaAufgabe, dependency: GaDependency, mode: GaDependencyMode) =>
    run(async () => replace(await changeGrossanlassTaskDependencyMode(departmentId(), groupOf(task), task.id, dependency.id, mode)))
  const removeDependency = (task: GaAufgabe, dependency: GaDependency) =>
    run(async () => {
      if (dependency.kind === 'group') {
        await removeGrossanlassGroupDependency(departmentId(), groupOf(task), dependency.id)
        await load()
      } else {
        replace(await removeGrossanlassTaskDependency(departmentId(), groupOf(task), task.id, dependency.id))
      }
    })
  const releaseDependency = (task: GaAufgabe, dependency: GaDependency, reason: string) =>
    run(async () => {
      if (dependency.kind === 'group') {
        await releaseGrossanlassGroupDependency(departmentId(), groupOf(task), dependency.id, reason)
        await load()
      } else {
        replace(await releaseGrossanlassTaskDependency(departmentId(), groupOf(task), task.id, dependency.id, reason))
      }
    })
  const block = (task: GaAufgabe, reason: string) =>
    run(async () => replace(await blockGrossanlassTask(departmentId(), groupOf(task), task.id, reason)))
  const unblock = (task: GaAufgabe) => run(async () => replace(await unblockGrossanlassTask(departmentId(), groupOf(task), task.id)))

  async function create(payload: { group_id: string; title: string; starts_at?: string | null; duration_minutes?: number | null; assignee_user_id?: string | null }) {
    const { group_id, ...body } = payload
    replace(await createGrossanlassBauprojektTask(departmentId(), group_id, body))
  }

  return { tasks, projects, groups, loading, error, load, actionError, setStatus, setAssignee, create, addStep, setStepDone, removeStep, moveStep, block, unblock, reportDone, accept, giveBack, addDependency, changeDependencyMode, removeDependency, releaseDependency }
}
