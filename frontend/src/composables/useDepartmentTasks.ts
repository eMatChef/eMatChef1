import { ref, type Ref } from 'vue'
import {
  getPendingDepartmentActivityInvites,
  getReceivedDepartmentInvites,
  type GrossanlassMwAssignedNotification,
  type PendingDepartmentActivityInvite,
  type ReceivedDepartmentInviteNotification,
} from '@/api/joinRequests'
import { getPublicFoundMessages, type PublicFoundItemMessage } from '@/api/publicFoundMessages'
import { listAcquisitionFollowups, type AccountingAcquisitionFollowUp } from '@/api/accountingAcquisitionFollowups'
import { departmentHasAccountingRole } from '@/composables/useCostBookingFollowUp'

export type DepartmentTaskKind =
  | 'qr_found'
  | 'department_invite'
  | 'grossanlass_mw_assigned'
  | 'activity_invite'
  | 'accounting_followup'
export type DepartmentTaskStatus = 'open' | 'in_progress' | 'done'

export interface DepartmentTaskItem {
  id: string
  kind: DepartmentTaskKind
  status: DepartmentTaskStatus
  createdAt: string
  title: string
  preview: string
  qrFound?: PublicFoundItemMessage
  departmentInvite?: ReceivedDepartmentInviteNotification
  grossanlassMwAssigned?: GrossanlassMwAssignedNotification
  activityInvite?: PendingDepartmentActivityInvite
  accounting?: AccountingAcquisitionFollowUp
}

export function taskOpenQuery(kind: DepartmentTaskKind, id: string): string {
  return `${kind}:${id}`
}

export function parseTaskOpenQuery(raw: unknown): { kind: DepartmentTaskKind; id: string } | null {
  const s = Array.isArray(raw) ? String(raw[0] ?? '') : String(raw ?? '')
  const i = s.indexOf(':')
  if (i <= 0) return null
  const kind = s.slice(0, i) as DepartmentTaskKind
  const id = s.slice(i + 1)
  if (!id) return null
  if (!['qr_found', 'department_invite', 'grossanlass_mw_assigned', 'activity_invite', 'accounting_followup'].includes(kind)) {
    return null
  }
  return { kind, id }
}

export async function loadDepartmentTasks(
  departmentId: string,
  options: {
    isUserRole: boolean
    canManageQrContact: boolean
  },
): Promise<DepartmentTaskItem[]> {
  const items: DepartmentTaskItem[] = []

  const deptInvPromise = getReceivedDepartmentInvites({ bucket: 'all', limit: 200 }).catch(() => ({
    items: [] as ReceivedDepartmentInviteNotification[],
  }))

  const campPromise = options.isUserRole
    ? Promise.resolve({ items: [] as PendingDepartmentActivityInvite[] })
    : getPendingDepartmentActivityInvites(departmentId).catch(() => ({
        items: [] as PendingDepartmentActivityInvite[],
      }))

  const qrPromise = options.canManageQrContact
    ? getPublicFoundMessages(departmentId, { bucket: 'all', limit: 200 }).catch(() => ({
        items: [] as PublicFoundItemMessage[],
      }))
    : Promise.resolve({ items: [] as PublicFoundItemMessage[] })

  const accountingPromise =
    !options.isUserRole && departmentHasAccountingRole(departmentId)
      ? listAcquisitionFollowups(departmentId, 'pending').catch(() => [])
      : Promise.resolve([] as AccountingAcquisitionFollowUp[])

  const [deptInv, camp, qr, accounting] = await Promise.all([
    deptInvPromise,
    campPromise,
    qrPromise,
    accountingPromise,
  ])

  for (const msg of qr.items || []) {
    items.push({
      id: `qr-${msg.id}`,
      kind: 'qr_found',
      status: msg.status,
      createdAt: msg.created_at,
      title: msg.material_name,
      preview: msg.message,
      qrFound: msg,
    })
  }

  for (const inv of deptInv.items || []) {
    if (inv.type === 'grossanlass_mw_assigned') {
      items.push({
        id: `ga-${inv.id}`,
        kind: 'grossanlass_mw_assigned',
        status: 'open',
        createdAt: inv.created_at,
        title: inv.department_name,
        preview: inv.dashboard_url,
        grossanlassMwAssigned: inv,
      })
      continue
    }
    items.push({
      id: `dept-${inv.id}`,
      kind: 'department_invite',
      status: 'open',
      createdAt: inv.created_at,
      title: inv.department_name,
      preview: inv.role,
      departmentInvite: inv,
    })
  }

  for (const inv of camp.items || []) {
    items.push({
      id: `camp-${inv.activity_id}-${inv.source_department_id}`,
      kind: 'activity_invite',
      status: 'open',
      createdAt: inv.invited_at || '',
      title: inv.activity_name,
      preview: inv.source_department_name,
      activityInvite: inv,
    })
  }

  for (const row of accounting) {
    items.push({
      id: `acc-${row.id}`,
      kind: 'accounting_followup',
      status: 'open',
      createdAt: row.created_at,
      title: row.material_name || row.receipt_label || row.id,
      preview: row.amount,
      accounting: row,
    })
  }

  return items.sort((a, b) => b.createdAt.localeCompare(a.createdAt))
}

export function useDepartmentTasksLoader(
  departmentId: Ref<string>,
  options: Ref<{ isUserRole: boolean; canManageQrContact: boolean }>,
) {
  const tasks = ref<DepartmentTaskItem[]>([])
  const isLoading = ref(false)
  const error = ref('')

  async function reload() {
    const deptId = departmentId.value
    if (!deptId) {
      tasks.value = []
      return
    }
    isLoading.value = true
    error.value = ''
    try {
      tasks.value = await loadDepartmentTasks(deptId, options.value)
    } catch {
      tasks.value = []
      error.value = 'load_failed'
    } finally {
      isLoading.value = false
    }
  }

  return { tasks, isLoading, error, reload }
}
