<template>
  <div class="tasks-general-panel">
    <div v-if="isLoading" class="tasks-loading">
      <div class="spinner" />
      <p>{{ t('tasksGeneral.loading') }}</p>
    </div>

    <div v-else-if="error" class="tasks-empty">
      <p>{{ t('tasksGeneral.loadFailed') }}</p>
      <button type="button" class="btn btn-secondary btn-sm" @click="reload">{{ t('common.retry') }}</button>
    </div>

    <div v-else-if="tasks.length === 0" class="tasks-empty">
      <h2 class="panel-heading">{{ t('tasksGeneral.emptyTitle') }}</h2>
      <p class="panel-text">{{ t('tasksGeneral.emptyText') }}</p>
    </div>

    <div v-else class="tasks-list" role="list">
      <article
        v-for="task in tasks"
        :key="task.id"
        role="listitem"
        class="task-row"
        :class="{ 'task-row--flash': flashTaskId === task.id }"
      >
        <div class="task-row__main">
          <span class="task-row__kind">{{ taskKindLabel(task.kind) }}</span>
          <h3 class="task-row__title">{{ task.title }}</h3>
          <p class="task-row__preview">{{ taskPreview(task) }}</p>
          <time v-if="task.createdAt" class="task-row__date">{{ formatDate(task.createdAt) }}</time>
        </div>
        <div class="task-row__actions">
          <template v-if="task.kind === 'qr_found' && task.qrFound">
            <button type="button" class="btn-primary btn-sm" @click="openQrTask(task.qrFound)">
              {{ t('tasksGeneral.actionHandle') }}
            </button>
            <button type="button" class="btn-outline btn-sm" @click="goToMessageForQr(task.qrFound)">
              {{ t('tasksGeneral.actionReadMessage') }}
            </button>
          </template>
          <template v-else-if="task.kind === 'department_invite' && task.departmentInvite">
            <button type="button" class="btn-success btn-sm" @click="acceptDeptInvite(task.departmentInvite)">
              {{ t('notificationsCenter.accept') }}
            </button>
            <button type="button" class="btn-danger-outline btn-sm" @click="declineDeptInvite(task.departmentInvite)">
              {{ t('notificationsCenter.reject') }}
            </button>
            <button type="button" class="btn-outline btn-sm" @click="goToMessageForDeptInvite(task.departmentInvite)">
              {{ t('tasksGeneral.actionReadMessage') }}
            </button>
          </template>
          <template v-else-if="task.kind === 'activity_invite' && task.activityInvite">
            <button type="button" class="btn-success btn-sm" @click="decideCamp(task.activityInvite, 'accepted')">
              {{ t('notificationsCenter.accept') }}
            </button>
            <button type="button" class="btn-danger-outline btn-sm" @click="decideCamp(task.activityInvite, 'rejected')">
              {{ t('notificationsCenter.reject') }}
            </button>
            <button type="button" class="btn-outline btn-sm" @click="goToMessageForCampInvite(task.activityInvite)">
              {{ t('tasksGeneral.actionReadMessage') }}
            </button>
          </template>
          <template v-else-if="task.kind === 'accounting_followup'">
            <button type="button" class="btn-primary btn-sm" @click="openAccountingTask(task)">
              {{ t('tasksGeneral.actionAccounting') }}
            </button>
          </template>
        </div>
      </article>
    </div>

    <InboxQrDetailModal
      :message="detailQr"
      :start-on-task="true"
      @close="detailQr = null"
      @open-material="openFoundMaterial"
      @status-change="onQrStatusChange"
      @proceed-to-task="onQrProceedToTask"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import { useAuthStore } from '@/stores/auth'
import {
  acceptDepartmentInvite,
  declineDepartmentInvite,
  decideDepartmentActivityInvite,
  type PendingDepartmentActivityInvite,
  type ReceivedDepartmentInviteNotification,
} from '@/api/joinRequests'
import {
  updatePublicFoundMessageStatus,
  type PublicFoundItemMessage,
  type PublicFoundMessageStatus,
} from '@/api/publicFoundMessages'
import { InboxQrDetailModal } from '@/components/notifications'
import {
  parseTaskOpenQuery,
  taskOpenQuery,
  useDepartmentTasksLoader,
  type DepartmentTaskItem,
  type DepartmentTaskKind,
} from '@/composables/useDepartmentTasks'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const authStore = useAuthStore()
const headerNotificationsStore = useHeaderNotificationsStore()
const { isUserRole, canManageQrContact } = useDepartmentMemberRole()

const departmentId = computed(() => String(route.params.departmentId || ''))
const roleOptions = computed(() => ({
  isUserRole: isUserRole.value,
  canManageQrContact: canManageQrContact.value,
}))

const { tasks, isLoading, error, reload } = useDepartmentTasksLoader(departmentId, roleOptions)

const detailQr = ref<PublicFoundItemMessage | null>(null)
const flashTaskId = ref('')

const DEPT_INVITE_ROLE_KEYS: Record<string, string> = {
  mw: 'settings.departmentUsers.roles.mw',
  dc: 'settings.departmentUsers.roles.dc',
  l1: 'settings.departmentUsers.roles.l1',
  l2: 'settings.departmentUsers.roles.l2',
  l3: 'settings.departmentUsers.roles.l3',
  u: 'settings.departmentUsers.roles.u',
}

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleString('de-CH', { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return iso
  }
}

function taskKindLabel(kind: DepartmentTaskKind): string {
  switch (kind) {
    case 'qr_found':
      return t('tasksGeneral.kindQr')
    case 'department_invite':
      return t('tasksGeneral.kindDeptInvite')
    case 'activity_invite':
      return t('tasksGeneral.kindCampInvite')
    case 'accounting_followup':
      return t('tasksGeneral.kindAccounting')
    default:
      return ''
  }
}

function departmentInviteRoleLabel(role: string): string {
  const key = DEPT_INVITE_ROLE_KEYS[role]
  return key ? t(key) : role
}

function taskPreview(task: DepartmentTaskItem): string {
  if (task.kind === 'department_invite' && task.departmentInvite) {
    return t('notificationsCenter.departmentInvitePreview', {
      role: departmentInviteRoleLabel(task.departmentInvite.role),
    })
  }
  if (task.kind === 'activity_invite' && task.activityInvite) {
    return task.activityInvite.activity_type === 'camp'
      ? t('notificationsCenter.typeCamp')
      : t('notificationsCenter.typeEvent')
  }
  if (task.kind === 'accounting_followup') {
    return t('notificationsCenter.accountingTaskPreview')
  }
  return task.preview
}

function openQrTask(msg: PublicFoundItemMessage) {
  detailQr.value = msg
}

async function onQrStatusChange(msg: PublicFoundItemMessage, status: PublicFoundMessageStatus) {
  if (!departmentId.value || msg.status === status) return
  try {
    const { item } = await updatePublicFoundMessageStatus(departmentId.value, msg.id, status)
    if (detailQr.value?.id === msg.id) detailQr.value = item
    await reload()
    headerNotificationsStore.requestRefresh()
    if (status === 'done') detailQr.value = null
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e?.response?.data?.error || t('notificationsCenter.toastSaveFailed'))
  }
}

async function onQrProceedToTask(msg: PublicFoundItemMessage) {
  if (msg.status === 'open' && departmentId.value) {
    await onQrStatusChange(msg, 'in_progress')
  }
}

function openFoundMaterial(msg: PublicFoundItemMessage) {
  if (!departmentId.value || !msg.material_id) return
  const q: Record<string, string> = {}
  if (msg.batch_id) q.batch = msg.batch_id
  void router.push({
    path: `/${departmentId.value}/materials/${msg.material_id}`,
    query: Object.keys(q).length ? q : undefined,
  })
}

function goToMessageForQr(msg: PublicFoundItemMessage) {
  void router.push({
    path: `/${departmentId.value}/notifications`,
    query: { highlight: msg.id },
  })
}

function goToMessageForDeptInvite(inv: ReceivedDepartmentInviteNotification) {
  void router.push({
    path: `/${departmentId.value}/notifications`,
    query: { openDeptInvite: inv.id },
  })
}

function goToMessageForCampInvite(inv: PendingDepartmentActivityInvite) {
  void router.push({
    path: `/${departmentId.value}/notifications`,
    query: {
      openCampInvite: `${inv.activity_id}:${inv.source_department_id}`,
    },
  })
}

async function acceptDeptInvite(inv: ReceivedDepartmentInviteNotification) {
  try {
    const result = await acceptDepartmentInvite({
      notificationId: inv.id,
      departmentId: inv.department_id,
      inviteId: inv.invite_id,
    })
    headerNotificationsStore.requestRefresh()
    toast.success(t('notificationsCenter.toastDeptInviteAccepted', { department: result.department_name }))
    if (result.department_id) {
      await authStore.refreshAfterInviteAccepted(result.department_id)
      return
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e?.response?.data?.error || t('notificationsCenter.toastDeptInviteAcceptFailed'))
  }
}

async function declineDeptInvite(inv: ReceivedDepartmentInviteNotification) {
  try {
    await declineDepartmentInvite({
      notificationId: inv.id,
      departmentId: inv.department_id,
      inviteId: inv.invite_id,
    })
    await reload()
    headerNotificationsStore.requestRefresh()
    toast.success(t('notificationsCenter.toastDeptInviteDeclined'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e?.response?.data?.error || t('notificationsCenter.toastDeptInviteDeclineFailed'))
  }
}

async function decideCamp(invite: PendingDepartmentActivityInvite, decision: 'accepted' | 'rejected') {
  if (!departmentId.value) return
  try {
    await decideDepartmentActivityInvite({
      activityId: invite.activity_id,
      departmentId: departmentId.value,
      decision,
    })
    await reload()
    headerNotificationsStore.requestRefresh()
    toast.success(
      decision === 'accepted'
        ? t('notificationsCenter.toastInviteAccepted')
        : t('notificationsCenter.toastInviteRejected'),
    )
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e?.response?.data?.error || t('notificationsCenter.toastDecisionFailed'))
  }
}

function openAccountingTask(task: DepartmentTaskItem) {
  if (!departmentId.value) return
  void router.push({
    name: 'AccountingBookings',
    params: { departmentId: departmentId.value },
    query: { sub: 'assign' },
  })
}

function findTaskByOpenQuery(parsed: { kind: DepartmentTaskKind; id: string }): DepartmentTaskItem | undefined {
  return tasks.value.find((row) => {
    if (parsed.kind === 'qr_found') return row.qrFound?.id === parsed.id
    if (parsed.kind === 'department_invite') return row.departmentInvite?.id === parsed.id
    if (parsed.kind === 'activity_invite') {
      const [actId, srcId] = parsed.id.split(':')
      return row.activityInvite?.activity_id === actId && row.activityInvite?.source_department_id === srcId
    }
    if (parsed.kind === 'accounting_followup') {
      return parsed.id === 'all' || row.accounting?.id === parsed.id
    }
    return false
  })
}

async function applyOpenQuery() {
  const parsed = parseTaskOpenQuery(route.query.open)
  if (!parsed || tasks.value.length === 0) return

  const row = findTaskByOpenQuery(parsed)
  if (!row) return

  flashTaskId.value = row.id
  window.setTimeout(() => {
    flashTaskId.value = ''
  }, 2200)

  if (parsed.kind === 'qr_found' && row.qrFound) {
    openQrTask(row.qrFound)
  } else if (parsed.kind === 'accounting_followup') {
    const target =
      parsed.id === 'all'
        ? tasks.value.find((t) => t.kind === 'accounting_followup') ?? row
        : row
    if (target) openAccountingTask(target)
  }

  const q = { ...route.query }
  delete q.open
  void router.replace({ path: route.path, query: q })
}

watch(
  () => route.query.open,
  () => {
    void applyOpenQuery()
  },
)

watch(tasks, () => {
  void applyOpenQuery()
})

onMounted(() => {
  void reload()
})

watch(departmentId, () => {
  void reload()
})
</script>

<style scoped>
.tasks-general-panel {
  max-width: 52rem;
}

.tasks-loading,
.tasks-empty {
  padding: 24px;
  text-align: center;
  color: #6b7280;
}

.tasks-empty .panel-heading {
  margin: 0 0 8px;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.tasks-empty .panel-text {
  margin: 0 0 16px;
  font-size: 14px;
  color: #6b7280;
}

.tasks-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.task-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
}

.task-row--flash {
  animation: task-flash 2s ease;
}

@keyframes task-flash {
  0%,
  100% {
    background: #fff;
  }
  30% {
    background: #eff6ff;
  }
}

.task-row__kind {
  display: inline-block;
  margin-bottom: 6px;
  padding: 2px 8px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #b45309;
  background: #fffbeb;
  border-radius: 4px;
}

.task-row__title {
  margin: 0 0 4px;
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
}

.task-row__preview {
  margin: 0 0 6px;
  font-size: 0.9rem;
  color: #4b5563;
}

.task-row__date {
  font-size: 0.8rem;
  color: #9ca3af;
}

.task-row__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
</style>
