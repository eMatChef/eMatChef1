<template>
  <PageShell class="notifications-center-view">
    <template #title>{{ t('notificationsCenter.title') }}</template>
    <template #subtitle>{{ subtitleText }}</template>

    <ELoadingState
      v-if="isLoading"
      variant="list"
      :message="t('notificationsCenter.loading')"
    />
    <template v-else>
      <section class="nc-mail-inbox">
        <div class="nc-mail-toolbar">
          <p class="nc-mail-toolbar__hint">{{ t('notificationsCenter.inboxUnifiedHint') }}</p>
          <div class="nc-mail-toolbar__right">
            <EButton variant="secondary" size="small" @click="showCompose = true">
              {{ t('notificationsCenter.composeButton') }}
            </EButton>
          </div>
        </div>

        <v-tabs v-model="mailTab" class="nc-mail-tabs" color="primary">
          <v-tab value="inbox">{{ t('notificationsCenter.mailTabInbox') }}</v-tab>
          <v-tab value="sent">{{ t('notificationsCenter.mailTabSent') }}</v-tab>
        </v-tabs>

        <template v-if="mailTab === 'sent'">
          <section class="nc-inbox-section">
            <div v-if="userMessagesSent.length > 0" class="nc-inbox-list" role="list">
              <button
                v-for="msg in userMessagesSent"
                :key="`sent-${msg.id}`"
                type="button"
                role="listitem"
                class="nc-inbox-row"
                @click="openSentMessage(msg)"
              >
                <div class="nc-inbox-row__body">
                  <div class="nc-inbox-row__top">
                    <span class="nc-inbox-row__from">
                      {{ t('notificationsCenter.sentTo', { name: msg.recipient_name }) }}
                    </span>
                    <time class="nc-inbox-row__date">{{ formatDate(msg.created_at) }}</time>
                  </div>
                  <div class="nc-inbox-row__meta">
                    <span class="nc-inbox-category nc-inbox-category--message">{{ t('notificationsCenter.inboxCategoryMessage') }}</span>
                  </div>
                  <div class="nc-inbox-row__subject">{{ msg.subject }}</div>
                  <div class="nc-inbox-row__preview">{{ msg.message }}</div>
                </div>
              </button>
            </div>
            <div v-else class="nc-empty-found">
              <p>{{ t('notificationsCenter.sentEmpty') }}</p>
            </div>
          </section>
        </template>

        <template v-else v-for="section in inboxSections" :key="section.key">
          <section class="nc-inbox-section">
            <h2 class="nc-inbox-section__title">
              {{ section.title }}
              <span v-if="section.items.length > 0" class="nc-inbox-section__count">({{ section.items.length }})</span>
            </h2>
            <div v-if="section.items.length > 0" class="nc-inbox-list" role="list">
              <template v-for="item in section.items" :key="item.id">
            <button
              v-if="item.kind === 'activity_mw' || item.kind === 'activity_status'"
              :id="inboxItemDomId(item)"
              type="button"
              role="listitem"
              class="nc-inbox-row"
              :class="{ 'nc-inbox-row--unread': item.unread, 'nc-row-flash': flashRowId === item.activityMw!.id }"
              @click="openActivityMw(item.activityMw!, item.kind === 'activity_status')"
            >
              <NotificationSenderBlock :sender="fromActivityMw(item.activityMw!)" size="md" />
              <div class="nc-inbox-row__body">
                <div class="nc-inbox-row__top">
                  <span class="nc-inbox-row__from">{{ senderLine(fromActivityMw(item.activityMw!)) }}</span>
                  <time class="nc-inbox-row__date">{{ formatDate(item.createdAt) }}</time>
                </div>
                <div class="nc-inbox-row__meta">
                  <span class="nc-inbox-category nc-inbox-category--activity">{{ inboxCategoryLabel(item) }}</span>
                </div>
                <div class="nc-inbox-row__subject">{{ activityInboxSubject(item.activityMw!) }}</div>
                <div class="nc-inbox-row__preview">{{ activityInboxPreview(item.activityMw!) }}</div>
              </div>
              <span v-if="item.unread" class="nc-inbox-unread-dot" :title="t('notificationsCenter.unreadLabel')" />
            </button>


            <button
              v-else-if="item.kind === 'user_message'"
              :id="inboxItemDomId(item)"
              type="button"
              role="listitem"
              class="nc-inbox-row"
              :class="{ 'nc-inbox-row--unread': item.unread, 'nc-row-flash': flashRowId === item.userMessage!.id }"
              @click="openUserMessage(item.userMessage!)"
            >
              <NotificationSenderBlock :sender="fromUserMessage(item.userMessage!)" size="md" />
              <div class="nc-inbox-row__body">
                <div class="nc-inbox-row__top">
                  <span class="nc-inbox-row__from">{{ senderLine(fromUserMessage(item.userMessage!)) }}</span>
                  <time class="nc-inbox-row__date">{{ formatDate(item.createdAt) }}</time>
                </div>
                <div class="nc-inbox-row__meta">
                  <span class="nc-inbox-category nc-inbox-category--message">{{ inboxCategoryLabel(item) }}</span>
                </div>
                <div class="nc-inbox-row__subject">{{ item.userMessage!.subject }}</div>
                <div class="nc-inbox-row__preview">{{ item.userMessage!.message }}</div>
              </div>
              <span v-if="item.unread" class="nc-inbox-unread-dot" :title="t('notificationsCenter.unreadLabel')" />
            </button>

            <button
              v-else-if="item.kind === 'invite_accepted'"
              :id="inboxItemDomId(item)"
              type="button"
              role="listitem"
              class="nc-inbox-row"
              :class="{ 'nc-inbox-row--unread': item.unread }"
              @click="openInviteAcceptedItem(item.inviteAccepted!)"
            >
              <div class="nc-inbox-row__body nc-inbox-row__body--indent">
                <div class="nc-inbox-row__top">
                  <span class="nc-inbox-row__from">{{ item.inviteAccepted!.user_name || item.inviteAccepted!.email }}</span>
                  <time class="nc-inbox-row__date">{{ formatDate(item.createdAt) }}</time>
                </div>
                <div class="nc-inbox-row__meta">
                  <span class="nc-inbox-category nc-inbox-category--message">{{ inboxCategoryLabel(item) }}</span>
                </div>
                <div class="nc-inbox-row__preview">
                  {{
                    t('settings.departmentUsers.inviteAcceptedMessage', {
                      name: item.inviteAccepted!.user_name || item.inviteAccepted!.email,
                      role: departmentInviteRoleLabel(item.inviteAccepted!.role),
                    })
                  }}
                </div>
              </div>
              <span v-if="item.unread" class="nc-inbox-unread-dot" :title="t('notificationsCenter.unreadLabel')" />
            </button>

            <button
              v-else-if="item.kind === 'grossanlass_mw_assigned'"
              :id="inboxItemDomId(item)"
              type="button"
              role="listitem"
              class="nc-inbox-row"
              :class="{ 'nc-inbox-row--unread': item.unread }"
              @click="openGrossanlassMwAssigned(item.grossanlassMwAssigned!)"
            >
              <div class="nc-inbox-row__body">
                <div class="nc-inbox-row__top">
                  <span class="nc-inbox-row__from">{{ item.grossanlassMwAssigned!.department_name }}</span>
                  <time class="nc-inbox-row__date">{{ formatDate(item.createdAt) }}</time>
                </div>
                <div class="nc-inbox-row__meta">
                  <span class="nc-inbox-category nc-inbox-category--message">{{ inboxCategoryLabel(item) }}</span>
                </div>
                <div class="nc-inbox-row__subject">{{ t('grossanlass.inbox.subject', { name: item.grossanlassMwAssigned!.department_name }) }}</div>
                <div class="nc-inbox-row__preview">{{ t('grossanlass.inbox.preview') }}</div>
              </div>
              <span v-if="item.unread" class="nc-inbox-unread-dot" :title="t('notificationsCenter.unreadLabel')" />
            </button>

            <button
              v-else-if="item.kind === 'grossanlass_round_opened'"
              :id="inboxItemDomId(item)"
              type="button"
              role="listitem"
              class="nc-inbox-row"
              :class="{ 'nc-inbox-row--unread': item.unread }"
              @click="openGrossanlassRoundOpened(item.grossanlassRoundOpened!)"
            >
              <div class="nc-inbox-row__body">
                <div class="nc-inbox-row__top">
                  <span class="nc-inbox-row__from">{{ item.grossanlassRoundOpened!.department_name }}</span>
                  <time class="nc-inbox-row__date">{{ formatDate(item.createdAt) }}</time>
                </div>
                <div class="nc-inbox-row__meta">
                  <span class="nc-inbox-category nc-inbox-category--message">{{ inboxCategoryLabel(item) }}</span>
                </div>
                <div class="nc-inbox-row__subject">{{ t('grossanlass.inbox.roundOpenedSubject', { name: item.grossanlassRoundOpened!.round_name }) }}</div>
                <div class="nc-inbox-row__preview">{{ t('grossanlass.inbox.roundOpenedPreview') }}</div>
              </div>
              <span v-if="item.unread" class="nc-inbox-unread-dot" :title="t('notificationsCenter.unreadLabel')" />
            </button>

            <button
              v-else-if="item.kind === 'department_invite'"
              :id="inboxItemDomId(item)"
              type="button"
              role="listitem"
              class="nc-inbox-row"
              :class="{ 'nc-inbox-row--unread': item.unread }"
              @click="openDepartmentInvite(item.departmentInvite!)"
            >
              <NotificationSenderBlock :sender="fromDepartmentInvite(item.departmentInvite!)" size="md" />
              <div class="nc-inbox-row__body">
                <div class="nc-inbox-row__top">
                  <span class="nc-inbox-row__from">{{ senderLine(fromDepartmentInvite(item.departmentInvite!)) }}</span>
                  <time class="nc-inbox-row__date">{{ formatDate(item.createdAt) }}</time>
                </div>
                <div class="nc-inbox-row__meta">
                  <span
                    v-for="(badge, bi) in inboxBadges(item)"
                    :key="bi"
                    class="nc-inbox-category"
                    :class="badge === t('notificationsCenter.inboxCategoryTask') ? 'nc-inbox-category--task' : 'nc-inbox-category--message'"
                  >
                    {{ badge }}
                  </span>
                </div>
                <div class="nc-inbox-row__subject">
                  {{ t('layout.notifications.departmentInviteTitle', { department: item.departmentInvite!.department_name }) }}
                </div>
                <div class="nc-inbox-row__preview">
                  {{ t('notificationsCenter.departmentInvitePreview', { role: departmentInviteRoleLabel(item.departmentInvite!.role, item.departmentInvite!.department_id) }) }}
                </div>
              </div>
              <span v-if="item.unread" class="nc-inbox-unread-dot" :title="t('notificationsCenter.unreadLabel')" />
            </button>

            <button
              v-else-if="item.kind === 'qr_found'"
              :id="inboxItemDomId(item)"
              type="button"
              role="listitem"
              class="nc-inbox-row"
              :class="{ 'nc-inbox-row--unread': item.unread, 'nc-row-flash': flashRowId === item.qrFound!.id }"
              @click="openQrMessage(item.qrFound!)"
            >
              <NotificationSenderBlock :sender="fromPublicFound(item.qrFound!)" size="md" />
              <div class="nc-inbox-row__body">
                <div class="nc-inbox-row__top">
                  <span class="nc-inbox-row__from">{{ senderLine(fromPublicFound(item.qrFound!)) }}</span>
                  <time class="nc-inbox-row__date">{{ formatDate(item.createdAt) }}</time>
                </div>
                <div class="nc-inbox-row__meta">
                  <span
                    v-for="(badge, bi) in inboxBadges(item)"
                    :key="bi"
                    class="nc-inbox-category"
                    :class="badge === t('notificationsCenter.inboxCategoryTask') ? 'nc-inbox-category--task' : 'nc-inbox-category--message'"
                  >
                    {{ badge }}
                  </span>
                </div>
                <div class="nc-inbox-row__subject">{{ item.qrFound!.material_name }}</div>
                <div class="nc-inbox-row__preview">{{ item.qrFound!.message }}</div>
              </div>
              <span v-if="item.unread" class="nc-inbox-unread-dot" :title="t('notificationsCenter.unreadLabel')" />
            </button>

            <button
              v-else-if="item.kind === 'activity_invite'"
              type="button"
              role="listitem"
              class="nc-inbox-row nc-inbox-row--unread"
              @click="openActivityInvite(item.activityInvite!)"
            >
              <NotificationSenderBlock :sender="fromActivityInvite(item.activityInvite!)" size="md" />
              <div class="nc-inbox-row__body">
                <div class="nc-inbox-row__top">
                  <span class="nc-inbox-row__from">{{ senderLine(fromActivityInvite(item.activityInvite!)) }}</span>
                  <time v-if="item.createdAt" class="nc-inbox-row__date">{{ formatDate(item.createdAt) }}</time>
                </div>
                <div class="nc-inbox-row__meta">
                  <span
                    v-for="(badge, bi) in inboxBadges(item)"
                    :key="bi"
                    class="nc-inbox-category"
                    :class="badge === t('notificationsCenter.inboxCategoryTask') ? 'nc-inbox-category--task' : 'nc-inbox-category--message'"
                  >
                    {{ badge }}
                  </span>
                </div>
                <div class="nc-inbox-row__subject">{{ item.activityInvite!.activity_name }}</div>
                <div class="nc-inbox-row__preview">
                  {{
                    item.activityInvite!.activity_type === 'camp'
                      ? t('notificationsCenter.typeCamp')
                      : t('notificationsCenter.typeEvent')
                  }}
                </div>
              </div>
              <span class="nc-inbox-unread-dot" :title="t('notificationsCenter.unreadLabel')" />
            </button>
              </template>
            </div>
            <div v-else class="nc-empty-found">
              <p>{{ section.emptyText }}</p>
            </div>
          </section>
        </template>
      </section>

      <InboxComposeModal
        v-model="showCompose"
        :department-id="departmentId"
        :members="composeMembers"
        @sent="onComposeSent"
      />
      <InboxMessageDetailModal
        :message="detailMessage"
        :mode="detailMessageMode"
        @close="closeDetailMessage"
      />
      <InboxQrDetailModal
        :message="detailQr"
        navigate-on-proceed
        @close="detailQr = null"
        @open-material="openFoundMaterial"
        @status-change="onQrDetailStatusChange"
        @open-task="goToTaskForQr"
      />

      <InboxInviteDetailModal
        :visible="!!detailDepartmentInvite"
        :title="t('notificationsCenter.deptInviteDetailTitle')"
        :subject="detailDepartmentInvite ? t('layout.notifications.departmentInviteTitle', { department: detailDepartmentInvite.department_name }) : ''"
        :preview="detailDepartmentInvite ? t('notificationsCenter.departmentInvitePreview', { role: departmentInviteRoleLabel(detailDepartmentInvite.role, detailDepartmentInvite.department_id) }) : ''"
        :sender="detailDepartmentInvite ? fromDepartmentInvite(detailDepartmentInvite) : null"
        :created-at="detailDepartmentInvite?.created_at"
        navigate-on-proceed
        @close="detailDepartmentInvite = null"
        @open-task="goToTaskForDeptInvite"
      />

      <InboxInviteDetailModal
        :visible="!!detailActivityInvite"
        :title="t('notificationsCenter.activityInviteDetailTitle')"
        :subject="detailActivityInvite?.activity_name ?? ''"
        :preview="detailActivityInvite ? (detailActivityInvite.activity_type === 'camp' ? t('notificationsCenter.typeCamp') : t('notificationsCenter.typeEvent')) : ''"
        :sender="detailActivityInvite ? fromActivityInvite(detailActivityInvite) : null"
        :created-at="detailActivityInvite?.invited_at ?? undefined"
        navigate-on-proceed
        @close="detailActivityInvite = null"
        @open-task="goToTaskForCampInvite"
      />

      <ActivityDepartmentInviteDecisionModal
        :visible="!!campInviteDecision"
        :invite="campInviteDecision"
        :department-id="departmentId || ''"
        @close="campInviteDecision = null"
        @decided="onCampInviteDecided"
      />
    </template>

  </PageShell>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import {
  getPendingDepartmentActivityInvites,
  decidePendingDepartmentInvite,
  getReceivedDepartmentInvites,
  markReceivedDepartmentInviteRead,
  acceptDepartmentInvite,
  declineDepartmentInvite,
  getInviteNotifications,
  markInviteNotificationRead,
  type InviteAcceptedNotification,
  type PendingDepartmentActivityInvite,
  type ReceivedDepartmentInviteNotification,
  type GrossanlassMwAssignedNotification,
  type GrossanlassRoundOpenedNotification,
  type ReceivedUserInboxNotification,
} from '@/api/joinRequests'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentRoleLabelsStore } from '@/stores/departmentRoleLabels'
import {
  getPublicFoundMessages,
  updatePublicFoundMessageStatus,
  type PublicFoundItemMessage,
  type PublicFoundMessageStatus,
} from '@/api/publicFoundMessages'
import {
  getActivityMwNotifications,
  markActivityMwNotificationRead,
  type ActivityMwNotification,
} from '@/api/activityNotifications'
import {
  getUserActivityStatusNotifications,
  markUserActivityStatusNotificationRead,
} from '@/api/activityUserNotifications'
import { routeForInboxActivityNotification } from '@/utils/inboxPackJourneyDeepLink'
import { useActivityNotificationText } from '@/composables/useActivityNotificationText'
import {
  getUserDirectMessages,
  getUserDirectMessagesSent,
  markUserDirectMessageRead,
  type UserDirectMessage,
  type UserDirectMessageSent,
} from '@/api/inboxMessages'
import { getDepartmentMembers, type DepartmentMember } from '@/api/departments'
import {
  ActivityDepartmentInviteDecisionModal,
  InboxComposeModal,
  InboxMessageDetailModal,
  InboxQrDetailModal,
  InboxInviteDetailModal,
  NotificationSenderBlock,
} from '@/components/notifications'
import PageShell from '@/components/layout/PageShell.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton } from '@/components/form/base'
import '@/styles/views/tasks-tabs.css'
import { taskOpenQuery } from '@/composables/useDepartmentTasks'
import { useNotificationSender } from '@/composables/useNotificationSender'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import { useUnsavedLeaveGuard } from '@/composables/useUnsavedLeaveGuard'
import { grossanlassOpenRoundWishRoute } from '@/utils/grossanlassNavigation'
import { getSenderPrimaryLine, type NotificationSenderDescriptor } from '@/utils/notificationSender'

const headerNotificationsStore = useHeaderNotificationsStore()
const { confirmLeaveIfDirty } = useUnsavedLeaveGuard()

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const roleLabelsStore = useDepartmentRoleLabelsStore()
const toast = useToast()
const { t } = useI18n()
const { fromActivityMw, fromDepartmentInvite, fromPublicFound, fromActivityInvite, fromUserMessage } =
  useNotificationSender()
const { inboxSubject: activityInboxSubject, inboxPreview: activityInboxPreview } =
  useActivityNotificationText()
const isLoading = ref(true)
const mailTab = ref<'inbox' | 'sent'>('inbox')
const userMessagesAll = ref<UserDirectMessage[]>([])
const userMessagesSent = ref<UserDirectMessageSent[]>([])
const departmentMembers = ref<DepartmentMember[]>([])
const showCompose = ref(false)
const detailMessage = ref<UserDirectMessage | UserDirectMessageSent | null>(null)
const detailMessageMode = ref<'inbox' | 'sent'>('inbox')
const detailQr = ref<PublicFoundItemMessage | null>(null)
const detailDepartmentInvite = ref<ReceivedDepartmentInviteNotification | null>(null)
const detailActivityInvite = ref<PendingDepartmentActivityInvite | null>(null)
const campInviteDecision = ref<PendingDepartmentActivityInvite | null>(null)
const inviteItems = ref<PendingDepartmentActivityInvite[]>([])
const departmentInviteAll = ref<ReceivedDepartmentInviteNotification[]>([])
const grossanlassMwAssignedAll = ref<GrossanlassMwAssignedNotification[]>([])
const grossanlassRoundOpenedAll = ref<GrossanlassRoundOpenedNotification[]>([])
const departmentInviteUnreadCount = ref(0)
const activityMwAll = ref<ActivityMwNotification[]>([])
const activityMwUnreadCount = ref(0)
const activityUserStatusAll = ref<ActivityMwNotification[]>([])
const inviteAcceptedAll = ref<InviteAcceptedNotification[]>([])
const allFoundMessages = ref<PublicFoundItemMessage[]>([])
const flashRowId = ref('')

type InboxItemKind =
  | 'activity_mw'
  | 'activity_status'
  | 'department_invite'
  | 'grossanlass_mw_assigned'
  | 'grossanlass_round_opened'
  | 'invite_accepted'
  | 'qr_found'
  | 'activity_invite'
  | 'user_message'

interface UnifiedInboxItem {
  id: string
  kind: InboxItemKind
  createdAt: string
  unread: boolean
  activityMw?: ActivityMwNotification
  activityStatusUser?: boolean
  departmentInvite?: ReceivedDepartmentInviteNotification
  grossanlassMwAssigned?: GrossanlassMwAssignedNotification
  grossanlassRoundOpened?: GrossanlassRoundOpenedNotification
  qrFound?: PublicFoundItemMessage
  activityInvite?: PendingDepartmentActivityInvite
  inviteAccepted?: InviteAcceptedNotification
  userMessage?: UserDirectMessage
}

const departmentId = computed(() => String(route.params.departmentId || ''))
const { isUserRole, canManageQrContact, canManageMaterials } = useDepartmentMemberRole()

const subtitleText = computed(() =>
  isUserRole.value ? t('notificationsCenter.subtitleUser') : t('notificationsCenter.subtitle'),
)

const showDepartmentInviteSection = computed(
  () => isUserRole.value || departmentInviteAll.value.length > 0 || departmentInviteUnreadCount.value > 0,
)

const composeMembers = computed(() => {
  const selfId = authStore.userId
  return departmentMembers.value.filter((m) => m.user_id && m.user_id !== selfId)
})

const unreadInboxItems = computed(() =>
  allInboxItems.value.filter((item) => item.unread),
)

const readInboxItems = computed(() =>
  allInboxItems.value.filter((item) => !item.unread),
)

const inboxSections = computed(() => [
  {
    key: 'unread',
    title: t('notificationsCenter.inboxSectionUnread'),
    items: unreadInboxItems.value,
    emptyText: t('notificationsCenter.inboxEmptyUnread'),
  },
  {
    key: 'read',
    title: t('notificationsCenter.inboxSectionRead'),
    items: readInboxItems.value,
    emptyText: t('notificationsCenter.inboxEmptyRead'),
  },
])

const allInboxItems = computed((): UnifiedInboxItem[] => {
  const items: UnifiedInboxItem[] = []

  for (const msg of userMessagesAll.value) {
    items.push({
      id: `um-${msg.id}`,
      kind: 'user_message',
      createdAt: msg.created_at,
      unread: !msg.read,
      userMessage: msg,
    })
  }

  for (const entry of activityUserStatusAll.value) {
    items.push({
      id: `act-st-${entry.id}`,
      kind: 'activity_status',
      createdAt: entry.created_at,
      unread: !entry.read,
      activityMw: entry,
      activityStatusUser: true,
    })
  }

  if (canManageMaterials.value) {
    for (const entry of activityMwAll.value) {
      items.push({
        id: `act-mw-${entry.id}`,
        kind: 'activity_mw',
        createdAt: entry.created_at,
        unread: !entry.read,
        activityMw: entry,
        activityStatusUser: false,
      })
    }
  }

  for (const note of inviteAcceptedAll.value) {
    items.push({
      id: `inv-acc-${note.id}`,
      kind: 'invite_accepted',
      createdAt: note.accepted_at,
      unread: !note.read,
      inviteAccepted: note,
    })
  }

  if (showDepartmentInviteSection.value) {
    for (const inv of departmentInviteAll.value) {
      items.push({
        id: `dept-inv-${inv.id}`,
        kind: 'department_invite',
        createdAt: inv.created_at,
        unread: !inv.read,
        departmentInvite: inv,
      })
    }
    for (const note of grossanlassMwAssignedAll.value) {
      items.push({
        id: `ga-mw-${note.id}`,
        kind: 'grossanlass_mw_assigned',
        createdAt: note.created_at,
        unread: !note.read,
        grossanlassMwAssigned: note,
      })
    }
    for (const note of grossanlassRoundOpenedAll.value) {
      items.push({
        id: `ga-round-${note.id}`,
        kind: 'grossanlass_round_opened',
        createdAt: note.created_at,
        unread: !note.read,
        grossanlassRoundOpened: note,
      })
    }
  }

  if (canManageQrContact.value) {
    for (const msg of allFoundMessages.value) {
      if (msg.status === 'done') continue
      items.push({
        id: `qr-${msg.id}`,
        kind: 'qr_found',
        createdAt: msg.created_at,
        unread: msg.status === 'open',
        qrFound: msg,
      })
    }
  }

  for (const invite of inviteItems.value) {
    items.push({
      id: `act-inv-${invite.activity_id}-${invite.source_department_id}`,
      kind: 'activity_invite',
      createdAt: invite.invited_at || '',
      unread: true,
      activityInvite: invite,
    })
  }

  return items.sort((a, b) => b.createdAt.localeCompare(a.createdAt))
})

const totalUnreadCount = computed(() => unreadInboxItems.value.length)

function formatDate(iso: string): string {
  try {
    const d = new Date(iso)
    return d.toLocaleString('de-CH', { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return iso
  }
}

function inboxItemDomId(item: UnifiedInboxItem): string {
  if (item.kind === 'activity_mw' && item.activityMw) return `nc-activity-${item.activityMw.id}`
  if (item.kind === 'qr_found' && item.qrFound) return `nc-found-${item.qrFound.id}`
  return `nc-item-${item.id}`
}

function inboxCategoryLabel(item: UnifiedInboxItem): string {
  switch (item.kind) {
    case 'activity_mw':
      return t('notificationsCenter.inboxCategoryActivity')
    case 'activity_status':
      return t('notificationsCenter.inboxCategoryActivityStatus')
    case 'qr_found':
      return t('notificationsCenter.inboxCategoryMessage')
    case 'department_invite':
      return t('notificationsCenter.inboxCategoryMessage')
    case 'grossanlass_mw_assigned':
      return t('grossanlass.inbox.category')
    case 'grossanlass_round_opened':
      return t('grossanlass.inbox.category')
    case 'invite_accepted':
      return t('notificationsCenter.inboxCategoryInviteAccepted')
    case 'activity_invite':
      return t('notificationsCenter.inboxCategoryMessage')
    case 'user_message':
      return t('notificationsCenter.inboxCategoryMessage')
    default:
      return ''
  }
}

function inboxBadges(item: UnifiedInboxItem): string[] {
  const message = t('notificationsCenter.inboxCategoryMessage')
  const task = t('notificationsCenter.inboxCategoryTask')
  switch (item.kind) {
    case 'qr_found':
      return [message, task]
    case 'department_invite':
    case 'activity_invite':
      return [message, task]
    default:
      return [inboxCategoryLabel(item)].filter(Boolean)
  }
}

async function openGrossanlassRoundOpened(note: GrossanlassRoundOpenedNotification) {
  const canLeave = await confirmLeaveIfDirty(t)
  if (!canLeave) return
  if (!note.read) {
    try {
      await markReceivedDepartmentInviteRead(note.id)
      grossanlassRoundOpenedAll.value = grossanlassRoundOpenedAll.value.map((e) =>
        e.id === note.id ? { ...e, read: true } : e,
      )
      headerNotificationsStore.requestRefresh()
    } catch {
      /* navigate anyway */
    }
  }
  try {
    await authStore.loadDepartments()
    await authStore.setActiveDepartment(note.department_id)
  } catch {
    /* navigate anyway */
  }
  await router.push(
    note.round_id
      ? grossanlassOpenRoundWishRoute(note.department_id, note.round_id)
      : (note.planung_url || `/${note.department_id}/planung`),
  )
}

async function openGrossanlassMwAssigned(note: GrossanlassMwAssignedNotification) {
  const canLeave = await confirmLeaveIfDirty(t)
  if (!canLeave) return
  if (!note.read) {
    try {
      await markReceivedDepartmentInviteRead(note.id)
      grossanlassMwAssignedAll.value = grossanlassMwAssignedAll.value.map((e) =>
        e.id === note.id ? { ...e, read: true } : e,
      )
      headerNotificationsStore.requestRefresh()
    } catch {
      /* navigate anyway */
    }
  }
  try {
    await authStore.loadDepartments()
    await authStore.setActiveDepartment(note.department_id)
  } catch {
    /* navigate anyway */
  }
  const path = note.dashboard_url || `/${note.department_id}/dashboard`
  await router.push(path)
}

async function openDepartmentInvite(inv: ReceivedDepartmentInviteNotification) {
  if (!inv.read) {
    try {
      await markReceivedDepartmentInviteRead(inv.id)
      departmentInviteAll.value = departmentInviteAll.value.map((e) =>
        e.id === inv.id ? { ...e, read: true } : e,
      )
      headerNotificationsStore.requestRefresh()
    } catch {
      /* open anyway */
    }
  }
  detailDepartmentInvite.value = inv
}

function openActivityInvite(invite: PendingDepartmentActivityInvite) {
  campInviteDecision.value = invite
}

async function onCampInviteDecided(decision: 'accepted' | 'rejected') {
  inviteItems.value = inviteItems.value.filter(
    (e) =>
      !(
        campInviteDecision.value &&
        e.activity_id === campInviteDecision.value.activity_id &&
        e.source_department_id === campInviteDecision.value.source_department_id
      ),
  )
  toast.success(
    decision === 'accepted'
      ? t('notificationsCenter.toastInviteAccepted')
      : t('notificationsCenter.toastInviteRejected'),
  )
  headerNotificationsStore.requestRefresh()
  await load()
}

function goToTasksPage(query?: Record<string, string>) {
  if (!departmentId.value) return
  void router.push({
    path: `/${departmentId.value}/tasks`,
    query,
  })
}

function goToTaskForQr(msg: PublicFoundItemMessage) {
  detailQr.value = null
  goToTasksPage({ open: taskOpenQuery('qr_found', msg.id) })
}

function goToTaskForDeptInvite() {
  const inv = detailDepartmentInvite.value
  detailDepartmentInvite.value = null
  if (!inv) return
  goToTasksPage({ open: taskOpenQuery('department_invite', inv.id) })
}

function goToTaskForCampInvite() {
  const inv = detailActivityInvite.value
  detailActivityInvite.value = null
  if (!inv) return
  goToTasksPage({
    open: taskOpenQuery('activity_invite', `${inv.activity_id}:${inv.source_department_id}`),
  })
}

async function onComposeSent() {
  mailTab.value = 'sent'
  await load()
  headerNotificationsStore.requestRefresh()
}

function closeDetailMessage() {
  detailMessage.value = null
  detailMessageMode.value = 'inbox'
}

function openSentMessage(msg: UserDirectMessageSent) {
  detailMessageMode.value = 'sent'
  detailMessage.value = msg
}

async function openUserMessage(msg: UserDirectMessage) {
  if (!departmentId.value) return
  let current = msg
  if (!msg.read) {
    try {
      await markUserDirectMessageRead(departmentId.value, msg.id)
      current = { ...msg, read: true }
      userMessagesAll.value = userMessagesAll.value.map((m) => (m.id === msg.id ? current : m))
      headerNotificationsStore.requestRefresh()
    } catch {
      /* keep open */
    }
  }
  detailMessageMode.value = 'inbox'
  detailMessage.value = current
}

function senderLine(sender: NotificationSenderDescriptor): string {
  return getSenderPrimaryLine(sender)
}

function qrInboxSubject(): string {
  return t('notificationsCenter.qrTaskSubject')
}

async function load() {
  if (!departmentId.value) {
    isLoading.value = false
    return
  }
  isLoading.value = true
  try {
    const invPromise = getPendingDepartmentActivityInvites(departmentId.value).catch(() => ({
      count: 0,
      items: [] as PendingDepartmentActivityInvite[],
    }))
    const deptInvPromise = getReceivedDepartmentInvites({ bucket: 'all', limit: 200 }).catch(() => ({
      count: 0,
      unread_count: 0,
      items: [] as ReceivedUserInboxNotification[],
    }))
    const foundPromise = canManageQrContact.value
      ? getPublicFoundMessages(departmentId.value, { bucket: 'all', limit: 200 }).catch(() => ({
          items: [] as PublicFoundItemMessage[],
        }))
      : Promise.resolve({ items: [] as PublicFoundItemMessage[] })

    const activityMwPromise = canManageMaterials.value
      ? getActivityMwNotifications(departmentId.value, { bucket: 'all', limit: 200 }).catch(() => ({
          unread_count: 0,
          items: [] as ActivityMwNotification[],
        }))
      : Promise.resolve({ unread_count: 0, items: [] as ActivityMwNotification[] })

    const userMsgPromise = getUserDirectMessages(departmentId.value, { bucket: 'all', limit: 200 }).catch(() => ({
      unread_count: 0,
      items: [] as UserDirectMessage[],
    }))
    const userSentPromise = getUserDirectMessagesSent(departmentId.value, { limit: 200 }).catch(() => ({
      count: 0,
      items: [] as UserDirectMessageSent[],
    }))
    const activityUserPromise = getUserActivityStatusNotifications(departmentId.value, {
      bucket: 'all',
      limit: 200,
    }).catch(() => ({ unread_count: 0, items: [] as ActivityMwNotification[] }))
    const membersPromise = getDepartmentMembers(departmentId.value).catch(() => [] as DepartmentMember[])

    const inviteAcceptedPromise = !isUserRole.value
      ? getInviteNotifications(departmentId.value, { bucket: 'all', limit: 200 }).catch(
          () => [] as InviteAcceptedNotification[],
        )
      : Promise.resolve([] as InviteAcceptedNotification[])

    const [inv, found, activityMw, deptInv, userMsg, userSent, activityUser, members, inviteAccepted] =
      await Promise.all([
      invPromise,
      foundPromise,
      activityMwPromise,
      deptInvPromise,
      userMsgPromise,
      userSentPromise,
      activityUserPromise,
      membersPromise,
      inviteAcceptedPromise,
    ])
    inviteItems.value = inv.items || []
    const receivedItems = deptInv.items || []
    departmentInviteAll.value = receivedItems.filter(
      (i): i is ReceivedDepartmentInviteNotification => i.type === 'department_invite',
    )
    grossanlassMwAssignedAll.value = receivedItems.filter(
      (i): i is GrossanlassMwAssignedNotification => i.type === 'grossanlass_mw_assigned',
    )
    grossanlassRoundOpenedAll.value = receivedItems.filter(
      (i): i is GrossanlassRoundOpenedNotification => i.type === 'grossanlass_round_opened',
    )
    departmentInviteUnreadCount.value =
      typeof deptInv.unread_count === 'number' ? deptInv.unread_count : 0
    allFoundMessages.value = found.items || []
    activityMwAll.value = activityMw.items || []
    activityMwUnreadCount.value =
      typeof activityMw.unread_count === 'number' ? activityMw.unread_count : 0
    userMessagesAll.value = userMsg.items || []
    userMessagesSent.value = userSent.items || []
    activityUserStatusAll.value = activityUser.items || []
    inviteAcceptedAll.value = inviteAccepted || []
    departmentMembers.value = members || []
  } catch {
    inviteItems.value = []
    inviteAcceptedAll.value = []
    departmentInviteAll.value = []
    grossanlassMwAssignedAll.value = []
    grossanlassRoundOpenedAll.value = []
    departmentInviteUnreadCount.value = 0
    allFoundMessages.value = []
    activityMwAll.value = []
    activityMwUnreadCount.value = 0
    userMessagesAll.value = []
    userMessagesSent.value = []
    activityUserStatusAll.value = []
    departmentMembers.value = []
    toast.error(t('notificationsCenter.toastLoadFailed'))
  } finally {
    isLoading.value = false
  }
}

async function openActivityMw(entry: ActivityMwNotification, forUserStatus = false) {
  if (!departmentId.value || !entry.activity_id) return
  if (String(entry.activity_id).startsWith('demo-')) {
    toast.info(t('notificationsCenter.toastDemoActivity'))
    return
  }
  if (!entry.read) {
    try {
      if (forUserStatus) {
        await markUserActivityStatusNotificationRead(departmentId.value, entry.id)
        activityUserStatusAll.value = activityUserStatusAll.value.map((e) =>
          e.id === entry.id ? { ...e, read: true } : e,
        )
      } else {
        await markActivityMwNotificationRead(departmentId.value, entry.id)
        activityMwAll.value = activityMwAll.value.map((e) =>
          e.id === entry.id ? { ...e, read: true } : e,
        )
        activityMwUnreadCount.value = Math.max(0, activityMwUnreadCount.value - 1)
      }
      headerNotificationsStore.requestRefresh()
    } catch {
      /* navigate anyway */
    }
  }
  void router.push(
    routeForInboxActivityNotification(departmentId.value, entry, {
      canManageMaterials: !forUserStatus,
    }),
  )
}

function departmentInviteRoleLabel(role: string, inviteDepartmentId?: string | null): string {
  return roleLabelsStore.labelFor(role, inviteDepartmentId || departmentId.value, t)
}

async function openInviteAcceptedItem(note: InviteAcceptedNotification) {
  if (!departmentId.value || note.read) return
  try {
    await markInviteNotificationRead(departmentId.value, note.id)
    inviteAcceptedAll.value = inviteAcceptedAll.value.map((n) =>
      n.id === note.id ? { ...n, read: true } : n,
    )
    headerNotificationsStore.requestRefresh()
  } catch {
    /* ignore */
  }
}

async function acceptDepartmentInviteItem(inv: ReceivedDepartmentInviteNotification) {
  const canLeave = await confirmLeaveIfDirty(t)
  if (!canLeave) return
  detailDepartmentInvite.value = null
  try {
    const result = await acceptDepartmentInvite({
      notificationId: inv.id,
      departmentId: inv.department_id,
      inviteId: inv.invite_id,
    })
    departmentInviteAll.value = departmentInviteAll.value.filter((e) => e.id !== inv.id)
    departmentInviteUnreadCount.value = Math.max(0, departmentInviteUnreadCount.value - 1)
    headerNotificationsStore.requestRefresh()
    toast.success(t('notificationsCenter.toastDeptInviteAccepted', { department: result.department_name }))
    if (result.department_id) {
      await authStore.refreshAfterInviteAccepted(result.department_id)
      return
    }
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('notificationsCenter.toastDeptInviteAcceptFailed'))
  }
}

async function declineDepartmentInviteItem(inv: ReceivedDepartmentInviteNotification) {
  detailDepartmentInvite.value = null
  try {
    await declineDepartmentInvite({
      notificationId: inv.id,
      departmentId: inv.department_id,
      inviteId: inv.invite_id,
    })
    departmentInviteAll.value = departmentInviteAll.value.filter((e) => e.id !== inv.id)
    departmentInviteUnreadCount.value = Math.max(0, departmentInviteUnreadCount.value - 1)
    headerNotificationsStore.requestRefresh()
    toast.success(t('notificationsCenter.toastDeptInviteDeclined'))
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('notificationsCenter.toastDeptInviteDeclineFailed'))
  }
}

async function decide(invite: PendingDepartmentActivityInvite, decision: 'accepted' | 'rejected') {
  if (!departmentId.value) return
  detailActivityInvite.value = null
  try {
    await decidePendingDepartmentInvite({
      invite,
      departmentId: departmentId.value,
      decision,
    })
    inviteItems.value = inviteItems.value.filter(
      (e) => !(e.activity_id === invite.activity_id && e.source_department_id === invite.source_department_id),
    )
    toast.success(
      decision === 'accepted'
        ? t('notificationsCenter.toastInviteAccepted')
        : t('notificationsCenter.toastInviteRejected'),
    )
    headerNotificationsStore.requestRefresh()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('notificationsCenter.toastDecisionFailed'))
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

async function openQrMessage(msg: PublicFoundItemMessage) {
  const current = allFoundMessages.value.find((m) => m.id === msg.id) ?? msg
  detailQr.value = current
}

async function onQrDetailStatusChange(msg: PublicFoundItemMessage, status: PublicFoundMessageStatus) {
  await onFoundStatusChange(msg, status)
  if (detailQr.value?.id === msg.id) {
    detailQr.value = allFoundMessages.value.find((m) => m.id === msg.id) ?? detailQr.value
  }
}

function onFoundStatusSelect(msg: PublicFoundItemMessage, ev: Event) {
  const el = ev.target as HTMLSelectElement
  const status = el.value as PublicFoundMessageStatus
  void onFoundStatusChange(msg, status)
}

async function onFoundStatusChange(msg: PublicFoundItemMessage, status: PublicFoundMessageStatus) {
  if (!departmentId.value || msg.status === status) return
  try {
    const { item } = await updatePublicFoundMessageStatus(departmentId.value, msg.id, status)
    const i = allFoundMessages.value.findIndex((m) => m.id === msg.id)
    if (i >= 0) allFoundMessages.value[i] = item
    headerNotificationsStore.requestRefresh()
    if (status === 'done') detailQr.value = null
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('notificationsCenter.toastSaveFailed'))
    await load()
  }
}

onMounted(load)
watch(departmentId, () => load())

function parseHighlightId(raw: unknown): string {
  if (Array.isArray(raw)) return String(raw[0] ?? '').trim()
  if (typeof raw === 'string') return raw.trim()
  return ''
}

watch(
  () => [route.query.openMessage, isLoading.value, userMessagesAll.value] as const,
  async ([raw, loading, msgs]) => {
    if (loading) return
    const id = parseHighlightId(raw)
    if (!id) return
    const msg = msgs.find((m) => m.id === id)
    if (!msg) return
    mailTab.value = 'inbox'
    await openUserMessage(msg)
    const q = { ...route.query }
    delete q.openMessage
    void router.replace({ path: route.path, query: q })
  },
  { flush: 'post' },
)

watch(
  () => [route.query.openDeptInvite, isLoading.value, departmentInviteAll.value] as const,
  async ([raw, loading, items]) => {
    if (loading) return
    const id = parseHighlightId(raw)
    if (!id) return
    const inv = items.find((e) => e.id === id)
    if (!inv) return
    mailTab.value = 'inbox'
    await openDepartmentInvite(inv)
    const q = { ...route.query }
    delete q.openDeptInvite
    void router.replace({ path: route.path, query: q })
  },
  { flush: 'post' },
)

watch(
  () => [route.query.openCampInvite, isLoading.value, inviteItems.value] as const,
  async ([raw, loading, items]) => {
    if (loading) return
    const key = parseHighlightId(raw)
    if (!key) return
    const [actId, srcId] = key.split(':')
    const inv = items.find((e) => e.activity_id === actId && e.source_department_id === srcId)
    if (!inv) return
    mailTab.value = 'inbox'
    openActivityInvite(inv)
    const q = { ...route.query }
    delete q.openCampInvite
    void router.replace({ path: route.path, query: q })
  },
  { flush: 'post' },
)

watch(
  () =>
    [route.query.highlight, isLoading.value, allFoundMessages.value, activityMwAll.value] as const,
  async ([hl, loading, msgs, activityMsgs]) => {
    if (loading) return
    const hid = parseHighlightId(hl)
    if (!hid) return

    const activityEntry = activityMsgs.find((m) => m.id === hid)
    if (activityEntry) {
      await nextTick()
      await nextTick()
      document.getElementById(`nc-activity-${hid}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
      flashRowId.value = hid
      window.setTimeout(() => {
        flashRowId.value = ''
      }, 2200)
      const q = { ...route.query }
      delete q.highlight
      void router.replace({ path: route.path, query: q })
      return
    }

    const msg = msgs.find((m) => m.id === hid)
    if (!msg) {
      const q = { ...route.query }
      if (q.highlight !== undefined) {
        delete q.highlight
        void router.replace({ path: route.path, query: q })
      }
      return
    }
    mailTab.value = 'inbox'
    await openQrMessage(msg)
    const q = { ...route.query }
    delete q.highlight
    void router.replace({ path: route.path, query: q })
  },
  { flush: 'post' },
)
</script>

<style scoped>
.nc-mail-inbox {
  max-width: 52rem;
}

.nc-tasks-section {
  max-width: 52rem;
  margin-top: 2rem;
}

.nc-inbox-row--task-only {
  cursor: pointer;
}

.nc-mail-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.nc-mail-toolbar__hint {
  margin: 0;
  font-size: 0.85rem;
  color: #6b7280;
  line-height: 1.4;
  flex: 1;
  min-width: 12rem;
}

.nc-mail-toolbar__right {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
}

.nc-inbox-section {
  margin-bottom: 1.75rem;
}

.nc-inbox-section__title {
  margin: 0 0 10px;
  font-size: 1rem;
  font-weight: 600;
  color: #374151;
}

.nc-inbox-section__count {
  margin-left: 6px;
  font-weight: 500;
  color: #6b7280;
  font-size: 0.9rem;
}

.nc-inbox-category--message {
  background: #fce7f3;
  color: #9d174d;
}

.nc-inbox-row__meta {
  display: flex;
  align-items: center;
  gap: 6px;
}

.nc-inbox-category {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  padding: 2px 6px;
  border-radius: 4px;
  line-height: 1.2;
}

.nc-inbox-category--activity {
  background: #e0e7ff;
  color: #3730a3;
}

.nc-inbox-category--task {
  background: #ffedd5;
  color: #9a3412;
}

.nc-inbox-category--invite {
  background: #d1fae5;
  color: #065f46;
}

.nc-section {
  margin-bottom: 2rem;
}
.nc-section-title {
  font-size: 1.1rem;
  font-weight: 600;
  margin: 0;
  color: #374151;
}
.nc-section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}
.nc-found-tabs {
  display: flex;
  gap: 4px;
}
.nc-tab {
  border: 1px solid #d1d5db;
  background: #fff;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 0.85rem;
  cursor: pointer;
  color: #4b5563;
}
.nc-tab.active {
  background: #f3f4f6;
  border-color: #9ca3af;
  font-weight: 600;
  color: #111827;
}
.nc-hint {
  font-size: 0.85rem;
  color: #6b7280;
  margin: 0 0 12px;
  max-width: 52rem;
  line-height: 1.4;
}
.nc-status-select {
  font-size: 0.85rem;
  padding: 4px 8px;
  border-radius: 4px;
  border: 1px solid #d1d5db;
  max-width: 11rem;
}
.nc-empty-found {
  padding: 12px 0;
  color: #6b7280;
  font-size: 0.95rem;
}
.nc-empty-found p {
  margin: 0;
}

.nc-inbox-row.nc-row-flash {
  animation: nc-flash-bg 1.1s ease-out 2;
}
@keyframes nc-flash-bg {
  0%,
  100% {
    background: transparent;
  }
  35% {
    background: #dbeafe;
  }
}

.nc-inbox-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.25rem;
  margin-left: 8px;
  padding: 0 6px;
  border-radius: 999px;
  background: #2563eb;
  color: #fff;
  font-size: 0.75rem;
  font-weight: 700;
  vertical-align: middle;
}

.nc-tab-count {
  margin-left: 4px;
  font-weight: 600;
}

.nc-inbox-list {
  display: flex;
  flex-direction: column;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
  background: #fff;
}

.nc-inbox-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  width: 100%;
  margin: 0;
  padding: 12px 14px;
  border: none;
  border-bottom: 1px solid #f3f4f6;
  background: #fff;
  text-align: left;
  font: inherit;
  color: inherit;
  position: relative;
}

.nc-inbox-row:last-child {
  border-bottom: none;
}

button.nc-inbox-row {
  cursor: pointer;
}

button.nc-inbox-row:hover {
  background: #f9fafb;
}

.nc-inbox-row--unread {
  background: #eff6ff;
}

.nc-inbox-row--unread:hover,
button.nc-inbox-row--unread:hover {
  background: #dbeafe;
}

.nc-inbox-row--with-actions {
  flex-wrap: wrap;
  align-items: flex-start;
}

.nc-inbox-row__body {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 4px;
}

.nc-inbox-row__body--clickable {
  cursor: pointer;
}

.nc-inbox-row__top {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
}

.nc-inbox-row__from {
  font-weight: 600;
  color: #111827;
  font-size: 0.9rem;
}

.nc-inbox-row--unread .nc-inbox-row__from {
  font-weight: 700;
}

.nc-inbox-row__date {
  flex-shrink: 0;
  font-size: 0.75rem;
  color: #6b7280;
}

.nc-inbox-row__subject {
  font-size: 0.9rem;
  color: #374151;
}

.nc-inbox-row--unread .nc-inbox-row__subject {
  font-weight: 600;
  color: #111827;
}

.nc-inbox-row__preview {
  font-size: 0.85rem;
  color: #6b7280;
  line-height: 1.35;
  white-space: pre-wrap;
  word-break: break-word;
}

.nc-inbox-unread-dot {
  flex-shrink: 0;
  width: 10px;
  height: 10px;
  margin-top: 6px;
  border-radius: 50%;
  background: #2563eb;
}

.nc-inbox-row__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding-left: 52px;
  margin-top: 4px;
}

.nc-inbox-row__actions .btn-xs + .btn-xs {
  margin-left: 0;
}
</style>
