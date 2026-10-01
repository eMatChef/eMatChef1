<template>
  <section v-if="pendingInvites.length > 0" class="pending-dept-invites" :class="{ 'pending-dept-invites--compact': compact }">
    <header class="pending-dept-invites__head">
      <div>
        <h2 class="pending-dept-invites__title">{{ t('dashboard.pendingDepartmentInvitesTitle') }}</h2>
        <p class="pending-dept-invites__hint">{{ t('dashboard.pendingDepartmentInvitesHint') }}</p>
      </div>
      <span class="pending-dept-invites__count">{{ pendingInvites.length }}</span>
    </header>

    <ul class="pending-dept-invites__list">
      <li v-for="inv in pendingInvites" :key="inv.id" class="pending-dept-invites__item">
        <div class="pending-dept-invites__meta">
          <strong>{{ inv.department_name }}</strong>
          <span>
            {{ t('layout.notifications.departmentInviteSubtitle', {
              name: inv.invited_by_name,
              role: roleLabel(inv.role, inv.department_id),
            }) }}
          </span>
        </div>
        <div class="pending-dept-invites__actions">
          <EButton
            variant="primary"
            size="small"
            :loading="actingId === inv.id && actingKind === 'accept'"
            @click="acceptInvite(inv)"
          >
            {{ t('notificationsCenter.accept') }}
          </EButton>
          <EButton
            variant="danger"
            size="small"
            :loading="actingId === inv.id && actingKind === 'decline'"
            @click="declineInvite(inv)"
          >
            {{ t('notificationsCenter.reject') }}
          </EButton>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { useUnsavedLeaveGuard } from '@/composables/useUnsavedLeaveGuard'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentRoleLabelsStore } from '@/stores/departmentRoleLabels'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import {
  acceptDepartmentInvite,
  declineDepartmentInvite,
  getReceivedDepartmentInvites,
  type ReceivedDepartmentInviteNotification,
} from '@/api/joinRequests'

withDefaults(
  defineProps<{
    compact?: boolean
  }>(),
  {
    compact: false,
  },
)

const { t } = useI18n()
const toast = useToast()
const authStore = useAuthStore()
const roleLabelsStore = useDepartmentRoleLabelsStore()
const headerNotificationsStore = useHeaderNotificationsStore()
const { confirmLeaveIfDirty } = useUnsavedLeaveGuard()

const pendingInvites = ref<ReceivedDepartmentInviteNotification[]>([])
const actingId = ref('')
const actingKind = ref<'accept' | 'decline' | ''>('')

function roleLabel(role: string, inviteDepartmentId?: string | null): string {
  return roleLabelsStore.labelFor(role, inviteDepartmentId || authStore.activeDepartmentId, t)
}

async function loadInvites() {
  if (!authStore.isLoggedIn) {
    pendingInvites.value = []
    return
  }
  try {
    const response = await getReceivedDepartmentInvites({ bucket: 'all', limit: 50 })
    if (response.repaired_memberships?.length) {
      const repaired = response.repaired_memberships[0]
      headerNotificationsStore.requestRefresh()
      toast.success(t('notificationsCenter.toastDeptInviteAccepted', { department: repaired.department_name }))
      await authStore.refreshAfterInviteAccepted(repaired.department_id)
      return
    }
    pendingInvites.value = response.items.filter(
      (item): item is ReceivedDepartmentInviteNotification =>
        item.type === 'department_invite' &&
        item.status === 'pending' &&
        !authStore.departments.some((d) => d.department_id === item.department_id),
    )
  } catch {
    pendingInvites.value = []
  }
}

async function acceptInvite(inv: ReceivedDepartmentInviteNotification) {
  const canLeave = await confirmLeaveIfDirty(t)
  if (!canLeave) return
  actingId.value = inv.id
  actingKind.value = 'accept'
  try {
    const result = await acceptDepartmentInvite({
      ...(inv.id ? { notificationId: inv.id } : {}),
      departmentId: inv.department_id,
      inviteId: inv.invite_id,
    })
    pendingInvites.value = pendingInvites.value.filter((row) => row.id !== inv.id)
    headerNotificationsStore.requestRefresh()
    toast.success(t('notificationsCenter.toastDeptInviteAccepted', { department: result.department_name }))
    if (result.department_id) {
      await authStore.refreshAfterInviteAccepted(result.department_id)
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('notificationsCenter.toastDeptInviteAcceptFailed'))
    await loadInvites()
  } finally {
    actingId.value = ''
    actingKind.value = ''
  }
}

async function declineInvite(inv: ReceivedDepartmentInviteNotification) {
  actingId.value = inv.id
  actingKind.value = 'decline'
  try {
    await declineDepartmentInvite({
      ...(inv.id ? { notificationId: inv.id } : {}),
      departmentId: inv.department_id,
      inviteId: inv.invite_id,
    })
    pendingInvites.value = pendingInvites.value.filter((row) => row.id !== inv.id)
    headerNotificationsStore.requestRefresh()
    toast.success(t('notificationsCenter.toastDeptInviteDeclined'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e.response?.data?.error || t('notificationsCenter.toastDeptInviteDeclineFailed'))
    await loadInvites()
  } finally {
    actingId.value = ''
    actingKind.value = ''
  }
}

onMounted(loadInvites)

watch(
  () => headerNotificationsStore.refreshNonce,
  () => {
    void loadInvites()
  },
)

watch(
  () => authStore.isLoggedIn,
  () => {
    void loadInvites()
  },
)
</script>

<style scoped>
.pending-dept-invites {
  margin-bottom: 20px;
  padding: 16px 18px;
  border: 1px solid #fde68a;
  border-radius: 12px;
  background: linear-gradient(180deg, #fffbeb 0%, #fff 100%);
}

.pending-dept-invites--compact {
  margin-bottom: 16px;
  padding: 12px 14px;
}

.pending-dept-invites__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.pending-dept-invites__title {
  margin: 0 0 4px;
  font-size: 1rem;
  font-weight: 700;
  color: #92400e;
}

.pending-dept-invites__hint {
  margin: 0;
  font-size: 0.82rem;
  color: #b45309;
  line-height: 1.45;
}

.pending-dept-invites__count {
  flex-shrink: 0;
  min-width: 28px;
  height: 28px;
  padding: 0 8px;
  border-radius: 999px;
  background: #f59e0b;
  color: #fff;
  font-size: 0.82rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.pending-dept-invites__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.pending-dept-invites__item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px 16px;
  padding: 12px 14px;
  border: 1px solid #fde68a;
  border-radius: 10px;
  background: #fff;
}

.pending-dept-invites__meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.pending-dept-invites__meta strong {
  font-size: 0.95rem;
  color: #1e293b;
}

.pending-dept-invites__meta span {
  font-size: 0.8rem;
  color: #64748b;
}

.pending-dept-invites__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
