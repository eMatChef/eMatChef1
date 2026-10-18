import apiClient from './apiClient'
import type { GaMaterialProgressItem } from './grossanlassUebersicht'
import type { GrossanlassGroup } from './grossanlassGroups'
import type { GrossanlassWishLine } from './grossanlassWishes'
import type { GaLogisticsPack, GaMap, GaPlace } from './grossanlassLogistics'

/** `review` = fertig gemeldet, wartet auf Abnahme; `done` entsteht erst durch die Abnahme. */
export type GaTaskStatus = 'open' | 'progress' | 'review' | 'done'

export type GaTaskStep = {
  id: string
  label: string
  sort_order: number
  done: boolean
  done_by_user_id?: string | null
  done_at?: string | null
}

/** Serverseitig berechnete Rechte des angemeldeten Benutzers an dieser Aufgabe. */
export type GaTaskPermissions = { edit: boolean; check: boolean; report: boolean; unblock: boolean; approve: boolean }

export type GaTaskEvent = {
  id: string
  type: 'reported' | 'accepted' | 'returned'
  by_user_id: string
  by_name: string | null
  note: string | null
  at: string
}

export type GaTaskBlock = { reason: string; by_user_id: string | null; at: string | null }

export type GaDependencyMode = 'sequential' | 'simultaneous'

export type GaDependencySchedule = {
  /** none = Zeitfenster überschneiden sich; no_overlap = Konflikt; unscheduled = eine Startzeit fehlt. */
  conflict: 'none' | 'no_overlap' | 'unscheduled'
  own: { starts_at: string | null; duration_minutes: number | null }
  partner: { starts_at: string | null; duration_minutes: number | null }
  start_diff_minutes: number | null
}

/** Abhängigkeit: Aufgabe→Aufgabe (`task`) oder Bauauftrag→Bauauftrag (`group`; an der Aufgabe `inherited`). */
export type GaDependency = {
  id: string
  kind: 'task' | 'group'
  /** `sequential`: Vorgänger muss abgenommen sein. `simultaneous`: zeitliche Koordination ohne Startsperre (nur Aufgaben). */
  mode: GaDependencyMode
  /** Nur «gleichzeitig»: Die Verknüpfung wurde von der anderen Aufgabe aus angelegt; `predecessor_*` beschreibt dann diese Aufgabe. */
  incoming?: boolean
  /** Nur «gleichzeitig»: Zeitvergleich. Es wird nie automatisch etwas an der anderen Aufgabe geändert. */
  schedule?: GaDependencySchedule | null
  created_by_name?: string | null
  mode_changed?: { by_name: string | null; at: string } | null
  successor_id: string
  /** false = Benutzer hat kein Sichtrecht auf den Vorgänger; dann sind ID, Titel und Stand `null`. */
  predecessor_visible: boolean
  predecessor_id: string | null
  predecessor_title: string | null
  predecessor_complete: boolean | null
  /** Vorgänger noch nicht abgenommen und nicht freigegeben. */
  open: boolean
  inherited?: boolean
  released: { by_user_id: string | null; by_name: string | null; reason: string | null; at: string | null } | null
  created_at: string
}

/** Formelle Gesamtabnahme eines Bauauftrags (getrennt von der Abnahme der Einzelaufgaben). */
export type GaOrderAcceptance = {
  applicable: boolean
  status: 'open' | 'review' | 'accepted'
  tasks_total: number
  tasks_done: number
  /** Hat Aufgaben und alle sind abgenommen (`done`): erst dann kann gemeldet werden. */
  eligible: boolean
  /** Noch nicht abgenommene Nachbesserungsaufgaben (verhindern die erneute Meldung). */
  rework_open: number
  /** Nur Vorschläge für neue Nachbesserungen; nichts wird automatisch zugewiesen. */
  rework_suggestion: { ressort: { id: string; name: string } | null; assignees: { user_id: string; name: string | null }[] }
  events: {
    id: string
    type: 'reported' | 'accepted' | 'returned' | 'withdrawn' | 'reopened'
    by_user_id: string
    by_name: string | null
    note: string | null
    at: string
    /** Nur bei `returned`: Nachbesserungsaufgaben zu dieser Rückweisung. */
    rework_tasks: { id: string; title: string; status: GaTaskStatus; assignee_user_id: string | null }[] | null
  }[]
  permissions: { report: boolean; approve: boolean }
}

export type GaGroupDependencies = { dependencies: GaDependency[]; waiting: boolean }

export type GaBauprojektTask = {
  id: string
  group_id: string
  title: string
  description?: string | null
  sort_order: number
  starts_at?: string | null
  duration_minutes?: number | null
  assignee_user_id?: string | null
  status: GaTaskStatus
  /** Benötigte Helfer dieser Aufgabe; Personen werden erst später zugewiesen. */
  helper_need?: GaTaskHelperNeed
  /** Optionale Checkliste. */
  steps?: GaTaskStep[]
  /** Gesetzt = blockiert, bis eine berechtigte Person die Blockierung aufhebt. */
  blocked?: GaTaskBlock | null
  permissions?: GaTaskPermissions | null
  dependencies?: GaDependency[]
  /** Wartet auf mindestens einen nicht abgenommenen, nicht freigegebenen Vorgänger. */
  waiting?: boolean
  waiting_on?: string[]
  /** Anzahl offener Abhängigkeiten, deren Vorgänger der Benutzer nicht sehen darf. */
  waiting_hidden?: number
  /** Gesamtabnahme-Zustand des Bauauftrags dieser Aufgabe. */
  /** Freigabe zur Ausführung (Backend: Bauauftrag im Aufbau, Betrieb, Abbau oder abgeschlossen). */
  released?: boolean
  /** Der angemeldete Benutzer ist verantwortlich oder als Helfer eingeteilt («Meine Einsätze»). */
  assigned_to_me?: boolean
  order_status?: 'open' | 'review' | 'accepted'
  /** Nachbesserung: verknüpft mit der Rückweisung der Gesamtabnahme (Grund, Zeitpunkt). */
  rework?: { event_id: string; reason: string | null; at: string | null } | null
  /** Verlauf Fertigmeldung/Abnahme/Rückgabe, neueste zuerst. */
  events?: GaTaskEvent[]
  created_at: string
}

export type GaTaskHelperNeed = {
  total: number
  functions: { label: string; count: number }[]
  unspecified?: number
}

export type GaBauprojektEinsatz = {
  id: string
  qty: number
  from: string
  to: string
  status: string
  delivery: string
  who: string
  object_id?: string | null
  object_name: string
  wish_line_id: string | null
    kind?: string
    node_type?: 'ressort' | 'unterressort' | 'bauprojekt' | string | null
}

export type GaBauprojektBriefing = {
  group: Pick<GrossanlassGroup, 'id' | 'name' | 'department_id' | 'parent_id' | 'kind' | 'node_type'> & {
    window_start?: string | null
    window_end?: string | null
    build_status?: string | null
    procurement_progress?: 'quoted' | 'build' | 'use' | null
    description?: string | null
  } | null
  window_start: string | null
  window_end: string | null
  build_status?: string | null
  description?: string | null
  place: GaPlace | null
  tasks: GaBauprojektTask[]
  /** Bisheriger gruppenweiter Helferbedarf (Altbestand), bleibt lesbar. */
  helper_need?: { total: number; functions: { label: string; count: number }[]; unspecified: number }
  vehicles?: GaBauprojektVehicleNeed[]
  material: GrossanlassWishLine[]
  /** Mengenfortschritt je Materialposition (Wunsch-ID bzw. Direkt-Bedarf-ID). */
  material_progress?: GaMaterialProgressItem[]
  direct_material?: Array<{
    id: string
    label: string
    quantity: number
    notes?: string | null
    status: string
    source: 'direct'
    self_organized?: boolean
    pickup_need?: 'can' | 'must' | null
    pickup_place?: string | null
    return_needed?: boolean
    quantity_unit?: string | null
    wish_kind?: 'material' | 'fahrzeug' | string | null
  }>
  packs: GaLogisticsPack[]
  einsaetze?: GaBauprojektEinsatz[]
  map: Pick<GaMap, 'id' | 'name' | 'image_url' | 'image_width' | 'image_height'> & {
    bounds_north?: number | null
    bounds_south?: number | null
    bounds_east?: number | null
    bounds_west?: number | null
  } | null
  can_edit: boolean
  can_set_build_status?: boolean
}

export async function listGrossanlassVehicleNeeds(
  departmentId: string,
): Promise<Array<GaBauprojektVehicleNeed & { group_name: string }>> {
  const { data } = await apiClient.get<Array<GaBauprojektVehicleNeed & { group_name: string }>>(
    `/api/departments/${departmentId}/grossanlass/planung/fahrzeug-wuensche`,
  )
  return data
}

export async function getGrossanlassBauprojekt(
  departmentId: string,
  groupId: string,
): Promise<GaBauprojektBriefing> {
  const { data } = await apiClient.get<GaBauprojektBriefing>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/bauprojekt`,
  )
  return data
}

export async function patchGrossanlassBauprojektWindow(
  departmentId: string,
  groupId: string,
  data: {
    window_start?: string | null
    window_end?: string | null
    build_status?: string | null
    description?: string | null
  },
): Promise<GaBauprojektBriefing> {
  const { data: out } = await apiClient.patch<GaBauprojektBriefing>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/bauprojekt`,
    data,
  )
  return out
}

/** Aufgaben aller sichtbaren Bauprojekte/Aufträge (zentrale Aufgabenübersicht). */
export async function getGrossanlassTasks(departmentId: string, options: { includeArchived?: boolean } = {}): Promise<GaBauprojektTask[]> {
  const { data } = await apiClient.get<GaBauprojektTask[]>(
    `/api/departments/${departmentId}/grossanlass/groups/tasks`,
    options.includeArchived ? { params: { include_archived: 1 } } : undefined,
  )
  return data
}

export async function createGrossanlassBauprojektTask(
  departmentId: string,
  groupId: string,
  payload: { title: string; description?: string | null; starts_at?: string | null; duration_minutes?: number | null; assignee_user_id?: string | null; status?: GaTaskStatus; helper_need?: GaTaskHelperNeed; rework_event_id?: string },
): Promise<GaBauprojektTask> {
  const { data } = await apiClient.post<GaBauprojektTask>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/tasks`,
    payload,
  )
  return data
}

export async function updateGrossanlassBauprojektTask(
  departmentId: string,
  groupId: string,
  taskId: string,
  payload: { title?: string; description?: string | null; starts_at?: string | null; duration_minutes?: number | null; assignee_user_id?: string | null; status?: GaTaskStatus; sort_order?: number; helper_need?: GaTaskHelperNeed },
): Promise<GaBauprojektTask> {
  const { data } = await apiClient.patch<GaBauprojektTask>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/tasks/${taskId}`,
    payload,
  )
  return data
}

const taskUrl = (departmentId: string, groupId: string, taskId: string) =>
  `/api/departments/${departmentId}/grossanlass/groups/${groupId}/tasks/${taskId}`

export async function addGrossanlassTaskStep(departmentId: string, groupId: string, taskId: string, label: string): Promise<GaBauprojektTask> {
  const { data } = await apiClient.post<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/steps`, { label })
  return data
}

export async function updateGrossanlassTaskStep(
  departmentId: string,
  groupId: string,
  taskId: string,
  stepId: string,
  payload: { label?: string; done?: boolean },
): Promise<GaBauprojektTask> {
  const { data } = await apiClient.patch<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/steps/${stepId}`, payload)
  return data
}

export async function deleteGrossanlassTaskStep(departmentId: string, groupId: string, taskId: string, stepId: string): Promise<GaBauprojektTask> {
  const { data } = await apiClient.delete<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/steps/${stepId}`)
  return data
}

export async function reorderGrossanlassTaskSteps(departmentId: string, groupId: string, taskId: string, ids: string[]): Promise<GaBauprojektTask> {
  const { data } = await apiClient.put<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/steps/order`, { ids })
  return data
}

export async function reportGrossanlassTaskDone(departmentId: string, groupId: string, taskId: string, note?: string): Promise<GaBauprojektTask> {
  const { data } = await apiClient.post<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/report`, { note: note ?? '' })
  return data
}

export async function acceptGrossanlassTask(departmentId: string, groupId: string, taskId: string): Promise<GaBauprojektTask> {
  const { data } = await apiClient.post<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/accept`)
  return data
}

export async function returnGrossanlassTask(departmentId: string, groupId: string, taskId: string, reason: string): Promise<GaBauprojektTask> {
  const { data } = await apiClient.post<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/return`, { reason })
  return data
}

export async function addGrossanlassTaskDependency(
  departmentId: string,
  groupId: string,
  taskId: string,
  predecessorTaskId: string,
  mode: GaDependencyMode = 'sequential',
): Promise<GaBauprojektTask> {
  const { data } = await apiClient.post<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/dependencies`, { predecessor_task_id: predecessorTaskId, mode })
  return data
}

export type GaTaskDependencies = Pick<GaBauprojektTask, 'dependencies' | 'waiting' | 'waiting_on' | 'waiting_hidden'>

/** Verknüpfungen einer Aufgabe (Sichtrecht auf den Bauauftrag genügt). */
export async function getGrossanlassTaskDependencies(departmentId: string, groupId: string, taskId: string): Promise<GaTaskDependencies> {
  const { data } = await apiClient.get<GaTaskDependencies>(`${taskUrl(departmentId, groupId, taskId)}/dependencies`)
  return data
}

export async function changeGrossanlassTaskDependencyMode(
  departmentId: string,
  groupId: string,
  taskId: string,
  dependencyId: string,
  mode: GaDependencyMode,
): Promise<GaBauprojektTask> {
  const { data } = await apiClient.patch<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/dependencies/${dependencyId}`, { mode })
  return data
}

export async function removeGrossanlassTaskDependency(departmentId: string, groupId: string, taskId: string, dependencyId: string): Promise<GaBauprojektTask> {
  const { data } = await apiClient.delete<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/dependencies/${dependencyId}`)
  return data
}

export async function releaseGrossanlassTaskDependency(
  departmentId: string,
  groupId: string,
  taskId: string,
  dependencyId: string,
  reason: string,
): Promise<GaBauprojektTask> {
  const { data } = await apiClient.post<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/dependencies/${dependencyId}/release`, { reason })
  return data
}

const groupDependencyUrl = (departmentId: string, groupId: string) => `/api/departments/${departmentId}/grossanlass/groups/${groupId}/dependencies`

export async function getGrossanlassGroupDependencies(departmentId: string, groupId: string): Promise<GaGroupDependencies> {
  const { data } = await apiClient.get<GaGroupDependencies>(groupDependencyUrl(departmentId, groupId))
  return data
}

export async function addGrossanlassGroupDependency(departmentId: string, groupId: string, predecessorGroupId: string): Promise<GaGroupDependencies> {
  const { data } = await apiClient.post<GaGroupDependencies>(groupDependencyUrl(departmentId, groupId), { predecessor_group_id: predecessorGroupId })
  return data
}

export async function removeGrossanlassGroupDependency(departmentId: string, groupId: string, dependencyId: string): Promise<GaGroupDependencies> {
  const { data } = await apiClient.delete<GaGroupDependencies>(`${groupDependencyUrl(departmentId, groupId)}/${dependencyId}`)
  return data
}

export async function releaseGrossanlassGroupDependency(departmentId: string, groupId: string, dependencyId: string, reason: string): Promise<GaGroupDependencies> {
  const { data } = await apiClient.post<GaGroupDependencies>(`${groupDependencyUrl(departmentId, groupId)}/${dependencyId}/release`, { reason })
  return data
}

const acceptanceUrl = (departmentId: string, groupId: string) => `/api/departments/${departmentId}/grossanlass/groups/${groupId}/acceptance`

export async function getGrossanlassOrderAcceptance(departmentId: string, groupId: string): Promise<GaOrderAcceptance> {
  const { data } = await apiClient.get<GaOrderAcceptance>(acceptanceUrl(departmentId, groupId))
  return data
}

export async function postGrossanlassOrderAcceptance(
  departmentId: string,
  groupId: string,
  action: 'report' | 'withdraw' | 'accept' | 'return' | 'reopen',
  reason?: string,
): Promise<GaOrderAcceptance> {
  const { data } = await apiClient.post<GaOrderAcceptance>(`${acceptanceUrl(departmentId, groupId)}/${action}`, reason === undefined ? {} : { reason })
  return data
}

export async function blockGrossanlassTask(departmentId: string, groupId: string, taskId: string, reason: string): Promise<GaBauprojektTask> {
  const { data } = await apiClient.post<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/block`, { reason })
  return data
}

export async function unblockGrossanlassTask(departmentId: string, groupId: string, taskId: string): Promise<GaBauprojektTask> {
  const { data } = await apiClient.delete<GaBauprojektTask>(`${taskUrl(departmentId, groupId, taskId)}/block`)
  return data
}

export type GaBauprojektVehicleNeed = {
  id: string
  group_id: string
  vehicle_label: string
  task_label: string
  category_label?: string | null
  /** `transport`: Fahrzeug soll etwas transportieren (überführbar in einen Fahrauftrag); `site_use`: Einsatz auf der Baustelle. */
  purpose?: 'site_use' | 'transport'
  sort_order: number
  starts_at?: string | null
  duration_minutes?: number | null
  procurement_line_id?: string | null
  created_at: string
}

export async function createGrossanlassBauprojektVehicle(
  departmentId: string,
  groupId: string,
  payload: { vehicle_label: string; task_label: string; category_label?: string | null; purpose?: 'site_use' | 'transport'; starts_at?: string | null; duration_minutes?: number | null },
): Promise<GaBauprojektVehicleNeed> {
  const { data } = await apiClient.post<GaBauprojektVehicleNeed>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/vehicles`,
    payload,
  )
  return data
}

export async function updateGrossanlassBauprojektVehicle(
  departmentId: string,
  groupId: string,
  needId: string,
  payload: { vehicle_label?: string; task_label?: string; category_label?: string | null; purpose?: 'site_use' | 'transport'; starts_at?: string | null; duration_minutes?: number | null },
): Promise<GaBauprojektVehicleNeed> {
  const { data } = await apiClient.patch<GaBauprojektVehicleNeed>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/vehicles/${needId}`,
    payload,
  )
  return data
}

export async function deleteGrossanlassBauprojektVehicle(
  departmentId: string,
  groupId: string,
  needId: string,
): Promise<void> {
  await apiClient.delete(`/api/departments/${departmentId}/grossanlass/groups/${groupId}/vehicles/${needId}`)
}

export async function deleteGrossanlassBauprojektTask(
  departmentId: string,
  groupId: string,
  taskId: string,
): Promise<void> {
  await apiClient.delete(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/tasks/${taskId}`,
  )
}

export async function updateGrossanlassBauprojektMaterial(
  departmentId: string,
  groupId: string,
  lineId: string,
  payload: {
    label: string
    quantity?: number
    mode?: 'wish' | 'direct'
    pickup_need?: 'can' | 'must' | null
    pickup_place?: string | null
    return_needed?: boolean
    quantity_unit?: string | null
    wish_kind?: 'material' | 'fahrzeug'
    self_organized?: boolean
  },
): Promise<void> {
  await apiClient.patch(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/material/${lineId}`,
    payload,
  )
}

export async function addGrossanlassBauprojektMaterial(
  departmentId: string,
  groupId: string,
  payload: {
    label: string
    quantity?: number
    location?: string
    notes?: string | null
    mode?: 'wish' | 'direct'
    pickup_need?: 'can' | 'must' | null
    pickup_place?: string | null
    return_needed?: boolean
    quantity_unit?: string | null
    wish_kind?: 'material' | 'fahrzeug'
    self_organized?: boolean
  },
): Promise<GrossanlassWishLine> {
  const { data } = await apiClient.post<GrossanlassWishLine>(
    `/api/departments/${departmentId}/grossanlass/groups/${groupId}/material`,
    payload,
  )
  return data
}

export async function getGrossanlassPlaceBriefing(
  departmentId: string,
  placeId: string,
): Promise<GaBauprojektBriefing> {
  const { data } = await apiClient.get<GaBauprojektBriefing>(
    `/api/departments/${departmentId}/grossanlass/places/${placeId}/briefing`,
  )
  return data
}
