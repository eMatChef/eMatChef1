<template>
  <header class="top-header">
    <!-- Hauptzeile: Tabs links, Icons rechts -->
    <div class="header-main-row">
    <!-- Left Section: Tabs (offene Detail-Ansichten) -->
    <div class="header-left">
      <div v-if="detailTabsStore.hasTabs" class="tabs-scroll">
        <div
          v-for="tab in detailTabsStore.tabs"
          :key="`${tab.type}-${tab.id}`"
          role="tab"
          tabindex="0"
          class="detail-tab"
          :class="{ active: isTabActive(tab) }"
          @click="navigateToTab(tab)"
          @keydown.enter="navigateToTab(tab)"
          @keydown.space.prevent="navigateToTab(tab)"
        >
          <span class="tab-label">{{ tab.label }}</span>
          <span v-if="tab.hasUnsavedChanges" class="tab-dirty" :title="t('layout.tabs.unsavedChangesTooltip')">●</span>
          <button
            type="button"
            class="tab-close"
            :aria-label="t('layout.tabs.closeAria')"
            @click.stop="closeTab(tab)"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>
    </div>
    
    <!-- Right Section: Actions & User -->
    <div class="header-right">
      <div class="search-wrapper" v-if="searchDepartmentId">
        <GlobalSearchInput
          ref="globalSearchRef"
          mode="icon"
          :department-id="searchDepartmentId"
        />
      </div>
      <button
        type="button"
        class="header-icon-btn"
        :title="t('layout.notifications.title')"
        :aria-label="t('layout.notifications.title')"
        :aria-expanded="showNotifications"
        aria-haspopup="true"
        @click="toggleNotifications"
      >
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span v-if="unreadCount > 0" class="notification-badge">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
      </button>
      <div v-if="showNotifications" class="notifications-dropdown" role="dialog" :aria-label="t('layout.notifications.title')">
        <div class="notifications-header">{{ t('layout.notifications.title') }}</div>
        <div class="notifications-dropdown-body">
          <div v-if="isLoadingNotifications" class="notifications-empty">{{ t('layout.notifications.loading') }}</div>
          <div
            v-else-if="bellEmpty"
            class="notifications-empty"
          >
            {{ t('layout.notifications.empty') }}
          </div>
          <div v-else class="notifications-list">
            <template v-if="hasBellMessages">
              <p class="notifications-section-label">{{ t('layout.notifications.sectionMessages') }}</p>
              <button
                v-for="msg in userMessagePreview"
                :key="`um-${msg.id}`"
                type="button"
                class="notification-item notification-item--user-message"
                :class="{ 'notification-item--unread': !msg.read }"
                @click="openUserMessageFromBell(msg)"
              >
                <NotificationSenderBlock
                  :sender="fromUserMessage(msg)"
                  size="sm"
                  class="notification-item__avatar"
                  :show-tooltip="false"
                />
                <div class="notification-item__body">
                  <div class="notification-title">{{ msg.subject }}</div>
                  <div class="notification-subtitle">{{ truncateMessage(msg.message) }}</div>
                </div>
              </button>
              <button
                v-for="entry in bellActivityMessages"
                :key="`act-${entry.bellScope}-${entry.id}`"
                type="button"
                class="notification-item notification-item--activity-mw"
                @click="openActivityBellEntry(entry)"
              >
                <NotificationSenderBlock
                  :sender="fromActivityMw(entry)"
                  size="sm"
                  class="notification-item__avatar"
                />
                <div class="notification-item__body">
                  <div class="notification-title">{{ bellLine(entry) }}</div>
                  <div class="notification-subtitle">{{ bellSubtitle(entry) }}</div>
                </div>
              </button>
              <button
                v-for="note in inviteAcceptedPreview"
                :key="`inv-acc-${note.id}`"
                type="button"
                class="notification-item notification-item--invite-accepted"
                :class="{ 'notification-item--unread': !note.read }"
                @click="openInviteAcceptedFromBell(note)"
              >
                <div class="notification-item__body notification-item__body--full">
                  <div class="notification-title">{{ inviteAcceptedBellTitle(note) }}</div>
                  <div class="notification-subtitle">{{ inviteAcceptedBellSubtitle(note) }}</div>
                </div>
              </button>
              <button
                v-for="msg in notificationPreviewFound"
                :key="`pf-${msg.id}`"
                type="button"
                class="notification-item notification-item--found"
                @click="openFoundMessageFromBell(msg)"
              >
                <NotificationSenderBlock
                  :sender="fromPublicFound(msg)"
                  size="sm"
                  class="notification-item__avatar"
                  :show-tooltip="false"
                />
                <div class="notification-item__body">
                  <div class="notification-title">{{ t('layout.notifications.qrContactTitle', { name: msg.material_name }) }}</div>
                  <div class="notification-subtitle">{{ truncateMessage(msg.message) }}</div>
                </div>
              </button>
              <button
                v-for="inv in receivedDepartmentInvitePreview"
                :key="`dept-inv-${inv.id}`"
                type="button"
                class="notification-item notification-item--dept-invite"
                :class="{ 'notification-item--unread': !inv.read }"
                @click="openDepartmentInviteFromBell(inv)"
              >
                <NotificationSenderBlock
                  :sender="fromDepartmentInvite(inv)"
                  size="sm"
                  class="notification-item__avatar"
                  :show-tooltip="false"
                />
                <div class="notification-item__body">
                  <div class="notification-title">
                    {{ t('layout.notifications.departmentInviteTitle', { department: inv.department_name }) }}
                  </div>
                  <div class="notification-subtitle">
                    {{ truncateMessage(t('layout.notifications.departmentInviteSubtitle', {
                      name: inv.invited_by_name || t('layout.userFallback'),
                      role: departmentInviteRoleLabel(inv.role),
                    })) }}
                  </div>
                </div>
              </button>
              <button
                v-for="invite in notificationPreviewInvites"
                :key="`inv-${invite.activity_id}-${invite.source_department_id}`"
                type="button"
                class="notification-item notification-item--activity-invite"
                @click="openCampInviteFromBell(invite)"
              >
                <div class="notification-item__body notification-item__body--full">
                  <div class="notification-title">{{ notificationInviteTitle(invite) }}</div>
                  <div class="notification-subtitle">{{ truncateMessage(invite.activity_name) }}</div>
                </div>
              </button>
            </template>

            <template v-if="hasBellTasks">
              <p class="notifications-section-label">{{ t('layout.notifications.sectionTasks') }}</p>
              <button
                v-if="showAccountingInBell"
                type="button"
                class="notification-item notification-item--accounting"
                @click="goToAccountingAssign"
              >
                <div class="notification-title">{{ t('layout.notifications.accountingTitle') }}</div>
                <div class="notification-subtitle">
                  {{
                    pendingFollowUpCount === 1
                      ? t('layout.notifications.accountingFollowUpOne', { count: pendingFollowUpCount })
                      : t('layout.notifications.accountingFollowUpMany', { count: pendingFollowUpCount })
                  }}
                </div>
              </button>
            </template>
          </div>
        </div>
        <div v-if="!isLoadingNotifications" class="notifications-dropdown-footer">
          <button
            type="button"
            class="btn btn-secondary btn-sm notifications-more-fullwidth"
            :title="notificationsShowAllTitle"
            @click.stop="goToNotificationsCenter"
          >
            {{ t('layout.notifications.showAll') }}
          </button>
        </div>
      </div>
      
      <button type="button" class="header-icon-btn" :title="t('layout.header.helpTitle')" :aria-label="t('layout.header.helpAria')" @click="showHelp">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
          <circle cx="12" cy="12" r="10" stroke-width="2"/>
          <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M12 17h.01" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>
      
      <button type="button" class="header-icon-btn" :title="t('layout.header.infoTitle')" :aria-label="t('layout.header.infoAria')" @click="showInfo">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
          <circle cx="12" cy="12" r="10" stroke-width="2"/>
          <path d="M12 16v-4" stroke-width="2" stroke-linecap="round"/>
          <path d="M12 8h.01" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>
      
      <!-- User Menu -->
      <div class="user-menu" @click="toggleUserMenu">
        <div class="user-avatar" :style="avatarStyle">
          {{ userInitials }}
        </div>
        <span class="user-name">{{ userName }}</span>
        <svg class="chevron-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      
      <!-- User Dropdown -->
      <div v-if="showUserDropdown" class="user-dropdown">
        <div class="user-info">
          <div class="user-avatar-large" :style="avatarStyle">
            {{ userInitials }}
          </div>
          <div class="user-details">
            <div class="user-name-full">{{ userFullName }}</div>
            <div class="user-email">{{ userEmail }}</div>
          </div>
        </div>
        <div class="dropdown-divider"></div>
        <button class="dropdown-item" @click="editProfile">
          <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="12" cy="7" r="4" stroke-width="2"/>
          </svg>
          {{ t('layout.userMenu.editProfile') }}
        </button>
        <button v-if="authStore.departments.length > 1" class="dropdown-item" @click="switchDepartment">
          <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M3 7C3 5.89543 3.89543 5 5 5H9.58579C10.1162 5 10.6249 5.21071 11 5.58579L12.4142 7H19C20.1046 7 21 7.89543 21 9V17C21 18.1046 20.1046 19 19 19H5C3.89543 19 3 18.1046 3 17V7Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M8 13h8M12 9v8" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          <span class="dept-switch-text">
            {{ t('layout.userMenu.switchDepartment') }}
            <span class="dept-switch-hint">{{ authStore.activeDepartmentName }}</span>
          </span>
        </button>
        <div class="dropdown-divider"></div>
        <button class="dropdown-item logout" @click="doLogout">
          <svg class="item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <polyline points="16 17 21 12 16 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <line x1="21" y1="12" x2="9" y2="12" stroke-width="2" stroke-linecap="round"/>
          </svg>
          {{ t('layout.userMenu.logout') }}
        </button>
      </div>
    </div>
    </div>

    <div v-if="showEditProfileModal" class="profile-modal-overlay">
      <div class="profile-modal">
        <div class="profile-modal-header">
          <h3>{{ t('layout.profileModal.title') }}</h3>
          <button class="modal-close-btn" @click="requestCloseEditProfileModal" :aria-label="t('layout.profileModal.closeAria')">×</button>
        </div>

        <form class="profile-modal-form" @submit.prevent="saveProfile">
          <div class="profile-modal-content">
            <div class="profile-top-row">
            <div class="user-avatar-large profile-avatar-preview" :style="profilePreviewStyle">
              {{ profilePreviewInitials }}
            </div>
            <div class="profile-top-fields">
              <label class="form-field">
                <span>{{ t('layout.profileModal.lastName') }}</span>
                <input v-model="profileForm.last_name" type="text" maxlength="100" />
              </label>

              <label class="form-field">
                <span>{{ t('layout.profileModal.firstName') }}</span>
                <input v-model="profileForm.first_name" type="text" maxlength="100" />
              </label>

              <label class="form-field">
                <span>{{ t('layout.profileModal.email') }}</span>
                <div class="email-edit-row">
                  <input
                    v-model="profileForm.email"
                    type="email"
                    maxlength="180"
                    autocomplete="username"
                    :disabled="!isEmailEditEnabled"
                    :class="{ 'is-readonly': !isEmailEditEnabled }"
                  />
                  <button
                    type="button"
                    class="email-edit-btn"
                    :class="{ active: isEmailEditEnabled }"
                    @click="toggleEmailEdit"
                    :title="t('layout.profileModal.editEmailTitle')"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                      <path d="M12 20h9" stroke-width="2" stroke-linecap="round" />
                      <path d="M16.5 3.5a2.12 2.12 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke-width="2" stroke-linejoin="round" />
                    </svg>
                  </button>
                </div>
                <small v-if="isEmailEditEnabled" class="email-edit-hint">
                  {{ t('layout.profileModal.emailNewMustVerify') }}
                </small>
                <small v-if="pendingEmailTarget" class="email-pending-hint">
                  {{
                    t('layout.profileModal.emailPendingSent', {
                      pending: pendingEmailTarget,
                      current: authStore.profile?.email || profileForm.email,
                    })
                  }}
                </small>
              </label>
            </div>
            </div>

            <div class="profile-form-grid">

            <label class="form-field">
              <span>{{ t('layout.profileModal.nickname') }}</span>
              <input v-model="profileForm.nickname" type="text" maxlength="50" :placeholder="t('layout.profileModal.nicknamePlaceholder')" />
            </label>

            <label class="form-field">
              <span>{{ t('layout.profileModal.initialsMax2') }}</span>
              <input
                v-model="profileForm.avatar_initials"
                type="text"
                maxlength="2"
                :placeholder="generatedInitialsTemplate"
                @input="profileForm.avatar_initials = profileForm.avatar_initials.toUpperCase()"
              />
            </label>

            <label class="form-field">
              <span>{{ t('layout.profileModal.language') }}</span>
              <select v-model="profileForm.language">
                <option value="de">{{ t('languageNames.de') }}</option>
                <option value="en">{{ t('languageNames.en') }}</option>
                <option value="fr">{{ t('languageNames.fr') }}</option>
                <option value="it">{{ t('languageNames.it') }}</option>
              </select>
            </label>

            <div class="form-field form-field-full">
              <span>{{ t('layout.profileModal.passwordSection') }}</span>
              <div class="profile-form-grid">
                <label class="form-field">
                  <span>{{ t('layout.profileModal.currentPassword') }}</span>
                  <input
                    v-model="passwordForm.current_password"
                    type="password"
                    autocomplete="current-password"
                    :placeholder="t('layout.profileModal.currentPasswordPlaceholder')"
                  />
                </label>
                <label class="form-field">
                  <span>{{ t('layout.profileModal.newPassword') }}</span>
                  <input
                    v-model="passwordForm.new_password"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="t('layout.profileModal.newPasswordPlaceholder')"
                  />
                </label>
                <label class="form-field form-field-full">
                  <span>{{ t('layout.profileModal.confirmNewPassword') }}</span>
                  <input
                    v-model="passwordForm.confirm_new_password"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="t('layout.profileModal.confirmNewPasswordPlaceholder')"
                  />
                </label>
              </div>
              <small v-if="passwordInlineError" class="password-inline-error">{{ passwordInlineError }}</small>
              <small v-else-if="passwordInlineSuccess" class="password-inline-success">{{ t('layout.profileModal.passwordOk') }}</small>
            </div>

            <div class="form-field form-field-full">
              <span>{{ t('layout.profileModal.colorCombinations') }}</span>
              <div class="avatar-palette-wrap">
                <div class="palette-row-label">{{ t('layout.profileModal.paletteWhiteInitials') }}</div>
                <div class="avatar-palette-row">
                  <button
                    v-for="color in avatarPaletteColors"
                    :key="`w-${color}`"
                    type="button"
                    class="avatar-color-chip"
                    :class="{ selected: isSelectedAvatarColor(color, '#FFFFFF') }"
                    :style="{ backgroundColor: color, color: '#FFFFFF' }"
                    @click="applyAvatarColor(color, '#FFFFFF')"
                  >
                    {{ profilePreviewInitials }}
                  </button>
                </div>
                <div class="palette-row-label">{{ t('layout.profileModal.paletteBlackInitials') }}</div>
                <div class="avatar-palette-row">
                  <button
                    v-for="color in avatarPaletteColors"
                    :key="`b-${color}`"
                    type="button"
                    class="avatar-color-chip"
                    :class="{ selected: isSelectedAvatarColor(color, '#111111') }"
                    :style="{ backgroundColor: color, color: '#111111' }"
                    @click="applyAvatarColor(color, '#111111')"
                  >
                    {{ profilePreviewInitials }}
                  </button>
                </div>
              </div>
            </div>

            <label class="form-field">
              <span>{{ t('layout.profileModal.backgroundColor') }}</span>
              <div class="color-field">
                <input v-model="profileForm.background_color" type="color" />
                <input
                  v-model="profileForm.background_color"
                  type="text"
                  maxlength="7"
                  :placeholder="t('layout.profileModal.backgroundColorPlaceholder')"
                />
              </div>
            </label>

            <label class="form-field">
              <span>{{ t('layout.profileModal.textColor') }}</span>
              <div class="color-field">
                <input v-model="profileForm.text_color" type="color" />
                <input
                  v-model="profileForm.text_color"
                  type="text"
                  maxlength="7"
                  :placeholder="t('layout.profileModal.textColorPlaceholder')"
                />
              </div>
            </label>
            </div>
          </div>

            <div class="profile-modal-footer">
            <div class="profile-status-hint" :class="{ visible: hasUnsavedProfileChanges }">
              <span v-if="hasUnsavedProfileChanges">{{ t('layout.profileModal.unsavedChanges') }}</span>
            </div>
            <button type="button" class="btn-secondary btn-sm" @click="requestCloseEditProfileModal" :disabled="savingProfile">{{ t('layout.profileModal.cancel') }}</button>
            <button type="submit" class="btn-primary btn-sm" :disabled="savingProfile || (!hasUnsavedProfileChanges && !hasPasswordInput) || !!passwordInlineError">
              {{ savingProfile ? t('layout.profileModal.saving') : t('layout.profileModal.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>

  </header>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { changePassword, login as apiLogin, updateProfile } from '../../api/auth'
import { useToast } from '../../composables/useToast'
import { useConfirm } from '../../composables/useConfirm'
import {
  getPendingDepartmentActivityInvites,
  decideDepartmentActivityInvite,
  getReceivedDepartmentInvites,
  markReceivedDepartmentInviteRead,
  acceptDepartmentInvite,
  declineDepartmentInvite,
  getInviteNotifications,
  markInviteNotificationRead,
  type InviteAcceptedNotification,
  type PendingDepartmentActivityInvite,
  type ReceivedDepartmentInviteNotification,
} from '../../api/joinRequests'
import {
  getPublicFoundMessages,
  updatePublicFoundMessageStatus,
  type PublicFoundItemMessage,
} from '../../api/publicFoundMessages'
// @ts-ignore Vetur false positive in Vue 3 script-setup import
import GlobalSearchInput from '../common/GlobalSearchInput.vue'
import { useDetailTabsStore } from '../../stores/detailTabs'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import { getPostLogoutPath } from '@/utils/appLoginUrl'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import {
  getActivityMwNotifications,
  markActivityMwNotificationRead,
  type ActivityMwNotification,
} from '@/api/activityNotifications'
import {
  getUserActivityStatusNotifications,
  markUserActivityStatusNotificationRead,
} from '@/api/activityUserNotifications'
import { listAcquisitionFollowups } from '@/api/accountingAcquisitionFollowups'
import { departmentHasAccountingRole } from '@/composables/useCostBookingFollowUp'
import { useActivityNotificationText } from '@/composables/useActivityNotificationText'
import {
  getUserDirectMessages,
  markUserDirectMessageRead,
  type UserDirectMessage,
} from '@/api/inboxMessages'
import { NotificationSenderBlock } from '@/components/notifications'
import { useNotificationSender } from '@/composables/useNotificationSender'
import { useUnreadDocumentTitleAlert } from '@/composables/useUnreadDocumentTitleAlert'
import { getSenderPrimaryLine } from '@/utils/notificationSender'
const { t } = useI18n()
const router = useRouter()
const detailTabsStore = useDetailTabsStore()
const headerNotificationsStore = useHeaderNotificationsStore()
const route = useRoute()
const authStore = useAuthStore()
const { isUserRole, canManageQrContact, canManageMaterials } = useDepartmentMemberRole()
const { fromActivityMw, fromDepartmentInvite, fromPublicFound, fromUserMessage } =
  useNotificationSender()
const { bellLine, bellSubtitle } = useActivityNotificationText()

type BellActivityEntry = ActivityMwNotification & { bellScope: 'user' | 'mw' }

const globalSearchRef = ref<InstanceType<typeof GlobalSearchInput> | null>(null)
const toast = useToast()
const confirm = useConfirm()

const showUserDropdown = ref(false)

const searchDepartmentId = computed(() => {
  const deptId = route.params.departmentId as string | undefined
  return deptId || authStore.activeDepartmentId || ''
})
const showEditProfileModal = ref(false)
const isEmailEditEnabled = ref(false)
const savingProfile = ref(false)
const unreadCount = ref(0)
const showNotifications = ref(false)
const isLoadingNotifications = ref(false)
const pendingDepartmentInvites = ref<PendingDepartmentActivityInvite[]>([])
const receivedDepartmentInvitePreview = ref<ReceivedDepartmentInviteNotification[]>([])
const receivedDepartmentInviteUnread = ref(0)
const publicFoundPreview = ref<PublicFoundItemMessage[]>([])
const activityMwPreview = ref<ActivityMwNotification[]>([])
const activityMwUnreadCount = ref(0)
const activityUserPreview = ref<ActivityMwNotification[]>([])
const activityUserUnreadCount = ref(0)
const pendingFollowUpCount = ref(0)
const accountingBellDismissed = ref(false)
const userMessagePreview = ref<UserDirectMessage[]>([])
const userMessageUnreadCount = ref(0)
const inviteAcceptedPreview = ref<InviteAcceptedNotification[]>([])
const inviteAcceptedUnreadCount = ref(0)

useUnreadDocumentTitleAlert(unreadCount)

const trialDays = ref(29)
const showTrialWarning = ref(true)
const profileForm = ref({
  first_name: '',
  last_name: '',
  email: '',
  nickname: '',
  avatar_initials: '',
  language: 'de',
  background_color: '#EC4899',
  text_color: '#FFFFFF',
})
const passwordForm = ref({
  current_password: '',
  new_password: '',
  confirm_new_password: '',
})
const initialProfileFormSnapshot = ref('')
const avatarPaletteColors = [
  '#2563EB',
  '#0EA5E9',
  '#14B8A6',
  '#22C55E',
  '#EAB308',
  '#F97316',
  '#EF4444',
  '#EC4899',
  '#A855F7',
  '#6B7280',
]

const userInitials = computed(() => authStore.userInitials)
const userName = computed(() => {
  if (!authStore.profile) return t('layout.userFallback')
  return authStore.profile.nickname || authStore.profile.firstName || authStore.profile.first_name || t('layout.userFallback')
})
const userFullName = computed(() => {
  const first = authStore.profile?.firstName || authStore.profile?.first_name || ''
  const last = authStore.profile?.lastName || authStore.profile?.last_name || ''
  const fullName = `${first} ${last}`.trim()
  if (fullName) return fullName
  return authStore.userDisplayName
})
const userEmail = computed(() => authStore.userEmail)
const avatarStyle = computed(() => ({
  backgroundColor: authStore.userColors.background,
  color: authStore.userColors.text,
}))
const profilePreviewInitials = computed(() => {
  return buildAvatarInitials(
    profileForm.value.avatar_initials,
    profileForm.value.nickname,
    profileForm.value.first_name,
    profileForm.value.last_name
  )
})
const generatedInitialsTemplate = computed(() =>
  buildAvatarInitials('', profileForm.value.nickname, profileForm.value.first_name, profileForm.value.last_name)
)
const profilePreviewStyle = computed(() => ({
  backgroundColor: normalizeHexColor(profileForm.value.background_color, '#EC4899'),
  color: normalizeHexColor(profileForm.value.text_color, '#FFFFFF'),
}))
const hasUnsavedProfileChanges = computed(() => {
  if (!initialProfileFormSnapshot.value) return false
  return serializeProfileForm(profileForm.value) !== initialProfileFormSnapshot.value
})
const hasPasswordInput = computed(() =>
  !!passwordForm.value.current_password || !!passwordForm.value.new_password || !!passwordForm.value.confirm_new_password
)
const passwordInlineError = computed(() => {
  if (!hasPasswordInput.value) return ''
  const currentPassword = passwordForm.value.current_password
  const newPassword = passwordForm.value.new_password
  const confirmPassword = passwordForm.value.confirm_new_password

  if (!currentPassword || !newPassword || !confirmPassword) {
    return t('layout.passwordValidation.fillAll')
  }
  if (newPassword.length < 8) {
    return t('layout.passwordValidation.minLength')
  }
  if (newPassword !== confirmPassword) {
    return t('layout.passwordValidation.mismatch')
  }
  return ''
})
const passwordInlineSuccess = computed(() => hasPasswordInput.value && !passwordInlineError.value)
const pendingEmailTarget = computed(() =>
  (authStore.profile?.pendingEmail || authStore.profile?.pending_email || '').trim()
)

const notificationPreviewInvites = computed(() => pendingDepartmentInvites.value.slice(0, 5))
const notificationPreviewFound = computed(() =>
  canManageQrContact.value
    ? publicFoundPreview.value.filter((m) => m.status === 'open').slice(0, 5)
    : [],
)

const showAccountingInBell = computed(
  () => pendingFollowUpCount.value > 0 && !accountingBellDismissed.value,
)

const bellActivityMessages = computed((): BellActivityEntry[] => {
  const user = activityUserPreview.value.map((e) => ({ ...e, bellScope: 'user' as const }))
  const mw = activityMwPreview.value.map((e) => ({ ...e, bellScope: 'mw' as const }))
  return [...user, ...mw]
})

const hasBellMessages = computed(
  () =>
    userMessagePreview.value.length > 0 ||
    bellActivityMessages.value.length > 0 ||
    inviteAcceptedPreview.value.length > 0 ||
    notificationPreviewFound.value.length > 0 ||
    receivedDepartmentInvitePreview.value.length > 0 ||
    notificationPreviewInvites.value.length > 0,
)

const hasBellTasks = computed(() => showAccountingInBell.value)

const bellEmpty = computed(() => !hasBellMessages.value && !hasBellTasks.value)

const notificationsShowAllTitle = computed(() => t('layout.notifications.showAllFooterTitle'))

const DEPT_INVITE_ROLE_KEYS: Record<string, string> = {
  mw: 'settings.departmentUsers.roles.mw',
  dc: 'settings.departmentUsers.roles.dc',
  l1: 'settings.departmentUsers.roles.l1',
  l2: 'settings.departmentUsers.roles.l2',
  l3: 'settings.departmentUsers.roles.l3',
  u: 'settings.departmentUsers.roles.u',
}

function departmentInviteRoleLabel(role: string): string {
  const key = DEPT_INVITE_ROLE_KEYS[role]
  return key ? t(key) : role
}

async function acceptDepartmentInviteFromBell(inv: ReceivedDepartmentInviteNotification) {
  showNotifications.value = false
  receivedDepartmentInvitePreview.value = receivedDepartmentInvitePreview.value.filter((e) => e.id !== inv.id)
  receivedDepartmentInviteUnread.value = Math.max(0, receivedDepartmentInviteUnread.value - 1)
  decrementUnreadCount()
  try {
    const result = await acceptDepartmentInvite({
      notificationId: inv.id,
      departmentId: inv.department_id,
      inviteId: inv.invite_id,
    })
    await authStore.loadDepartments()
    toast.success(t('layout.notifications.departmentInviteAccepted', { department: result.department_name }))
    if (result.department_id) {
      void router.push(`/${result.department_id}`)
    }
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('layout.notifications.departmentInviteAcceptFailed'))
    void loadDepartmentInvites()
  } finally {
    syncBellBadge()
  }
}

async function declineDepartmentInviteFromBell(inv: ReceivedDepartmentInviteNotification) {
  showNotifications.value = false
  receivedDepartmentInvitePreview.value = receivedDepartmentInvitePreview.value.filter((e) => e.id !== inv.id)
  receivedDepartmentInviteUnread.value = Math.max(0, receivedDepartmentInviteUnread.value - 1)
  decrementUnreadCount()
  try {
    await declineDepartmentInvite({
      notificationId: inv.id,
      departmentId: inv.department_id,
      inviteId: inv.invite_id,
    })
    syncBellBadge()
    toast.success(t('layout.notifications.departmentInviteDeclined'))
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('layout.notifications.departmentInviteDeclineFailed'))
    void loadDepartmentInvites()
  }
}

function notificationInviteTitle(invite: PendingDepartmentActivityInvite): string {
  const activityType =
    invite.activity_type === 'camp'
      ? t('layout.notifications.activityCamp')
      : t('layout.notifications.activityEvent')
  return t('layout.notifications.inviteTitle', {
    department: invite.source_department_name,
    activityType,
  })
}

const BELL_PREVIEW_MAX = 52

function truncateMessage(text: string, max = BELL_PREVIEW_MAX): string {
  const trimmed = String(text || '').trim().replace(/\s+/g, ' ')
  if (trimmed.length <= max) return trimmed
  return `${trimmed.slice(0, max)}…`
}

function decrementUnreadCount(n = 1) {
  unreadCount.value = Math.max(0, unreadCount.value - n)
}

function syncBellBadge() {
  headerNotificationsStore.requestRefresh()
}

function isTabActive(tab: { path: string }) {
  const basePath = tab.path.split('?')[0]
  return route.fullPath === tab.path || route.fullPath === basePath || route.fullPath.startsWith(basePath + '/')
}

function navigateToTab(tab: { path: string }) {
  router.push(tab.path)
}

async function closeTab(tab: { id: string; type: 'material' | 'activity'; departmentId: string; path: string; hasUnsavedChanges: boolean }) {
  if (tab.hasUnsavedChanges) {
    const ok = await confirm.confirm({
      title: t('layout.confirm.unsavedTitle'),
      message: t('layout.confirm.unsavedMessage'),
      confirmText: t('layout.confirm.close'),
      cancelText: t('layout.confirm.back'),
      variant: 'warning',
    })
    if (!ok) return
  }
  detailTabsStore.removeTab(tab.id, tab.type, tab.departmentId)
  const basePath = tab.path.split('?')[0]
  if (route.fullPath === tab.path || route.fullPath === basePath || route.fullPath.startsWith(basePath + '/')) {
    const base = `/${tab.departmentId}`
    router.push(tab.type === 'material' ? `${base}/materials` : `${base}/activities`)
  }
}

function toggleUserMenu() {
  showUserDropdown.value = !showUserDropdown.value
}

async function toggleNotifications() {
  showNotifications.value = !showNotifications.value
  if (!showNotifications.value) return
  await loadDepartmentInvites()
}

function goToNotificationsCenter() {
  const deptId =
    (route.params.departmentId as string | undefined) || authStore.activeDepartmentId || ''
  if (!deptId) return
  showNotifications.value = false
  router.push(`/${deptId}/notifications`)
}

async function openActivityBellEntry(entry: BellActivityEntry) {
  const deptId = authStore.activeDepartmentId
  if (!deptId || !entry.activity_id) return
  showNotifications.value = false
  if (entry.bellScope === 'user') {
    activityUserPreview.value = activityUserPreview.value.filter((e) => e.id !== entry.id)
    activityUserUnreadCount.value = Math.max(0, activityUserUnreadCount.value - 1)
  } else {
    activityMwPreview.value = activityMwPreview.value.filter((e) => e.id !== entry.id)
    activityMwUnreadCount.value = Math.max(0, activityMwUnreadCount.value - 1)
  }
  decrementUnreadCount()
  try {
    if (entry.bellScope === 'user') {
      await markUserActivityStatusNotificationRead(deptId, entry.id)
    } else {
      await markActivityMwNotificationRead(deptId, entry.id)
    }
    syncBellBadge()
  } catch {
    /* navigate anyway */
  }
  void router.push(`/${deptId}/activities/${entry.activity_id}`)
}

function goToAccountingAssign() {
  const deptId = authStore.activeDepartmentId
  if (!deptId) return
  showNotifications.value = false
  accountingBellDismissed.value = true
  const n = pendingFollowUpCount.value
  if (n > 0) decrementUnreadCount(n)
  void router.push({
    path: `/${deptId}/tasks`,
    query: { open: 'accounting_followup:all' },
  })
}

async function openDepartmentInviteFromBell(inv: ReceivedDepartmentInviteNotification) {
  const deptId = authStore.activeDepartmentId
  if (!deptId) return
  showNotifications.value = false
  receivedDepartmentInvitePreview.value = receivedDepartmentInvitePreview.value.filter((e) => e.id !== inv.id)
  receivedDepartmentInviteUnread.value = Math.max(0, receivedDepartmentInviteUnread.value - 1)
  decrementUnreadCount()
  if (!inv.read) {
    try {
      await markReceivedDepartmentInviteRead(inv.id)
      syncBellBadge()
    } catch {
      /* navigate anyway */
    }
  }
  void router.push({ path: `/${deptId}/notifications` })
}

function openCampInviteFromBell(invite: PendingDepartmentActivityInvite) {
  const deptId = authStore.activeDepartmentId
  if (!deptId) return
  showNotifications.value = false
  void router.push({ path: `/${deptId}/notifications` })
}

function inviteAcceptedBellTitle(note: InviteAcceptedNotification): string {
  return t('layout.notifications.inviteAcceptedTitle', { name: note.user_name || note.email })
}

function inviteAcceptedBellSubtitle(note: InviteAcceptedNotification): string {
  return t('settings.departmentUsers.inviteAcceptedMessage', {
    name: note.user_name || note.email,
    role: departmentInviteRoleLabel(note.role),
  })
}

async function openInviteAcceptedFromBell(note: InviteAcceptedNotification) {
  const deptId = authStore.activeDepartmentId
  if (!deptId) return
  showNotifications.value = false
  inviteAcceptedPreview.value = inviteAcceptedPreview.value.filter((n) => n.id !== note.id)
  if (!note.read) {
    inviteAcceptedUnreadCount.value = Math.max(0, inviteAcceptedUnreadCount.value - 1)
    decrementUnreadCount()
    try {
      await markInviteNotificationRead(deptId, note.id)
      syncBellBadge()
    } catch {
      /* navigate anyway */
    }
  }
  void router.push({ path: `/${deptId}/notifications` })
}

/** Glocke: nur ungelesene Nachrichten + offene Aufgaben (QR, Einladungen). */
async function loadDepartmentInvites() {
  const deptId = authStore.activeDepartmentId
  if (!deptId) return
  isLoadingNotifications.value = true
  try {
    const receivedInvitesPromise = getReceivedDepartmentInvites({ bucket: 'unread', limit: 5 }).catch(() => ({
      count: 0,
      unread_count: 0,
      items: [] as ReceivedDepartmentInviteNotification[],
    }))

    const userMsgPromise = getUserDirectMessages(deptId, { bucket: 'unread', limit: 5 }).catch(() => ({
      unread_count: 0,
      items: [] as UserDirectMessage[],
    }))

    const campInvitesPromise = isUserRole.value
      ? Promise.resolve({ count: 0, items: [] as PendingDepartmentActivityInvite[] })
      : getPendingDepartmentActivityInvites(deptId).catch(() => ({
          count: 0,
          items: [] as PendingDepartmentActivityInvite[],
        }))

    const foundPromise =
      !isUserRole.value && canManageQrContact.value
        ? getPublicFoundMessages(deptId, { bucket: 'open', limit: 5 }).catch(() => ({
            unread_count: 0,
            items: [] as PublicFoundItemMessage[],
          }))
        : Promise.resolve({ unread_count: 0, items: [] as PublicFoundItemMessage[] })

    const activityMwPromise = canManageMaterials.value
      ? getActivityMwNotifications(deptId, { bucket: 'unread', limit: 5 }).catch(() => ({
          unread_count: 0,
          items: [] as ActivityMwNotification[],
        }))
      : Promise.resolve({ unread_count: 0, items: [] as ActivityMwNotification[] })

    const activityUserPromise = getUserActivityStatusNotifications(deptId, {
      bucket: 'unread',
      limit: 5,
    }).catch(() => ({ unread_count: 0, items: [] as ActivityMwNotification[] }))

    const followUpPromise =
      !isUserRole.value && departmentHasAccountingRole(deptId)
        ? listAcquisitionFollowups(deptId, 'pending').catch(() => [])
        : Promise.resolve([])

    const inviteAcceptedPromise = !isUserRole.value
      ? getInviteNotifications(deptId, { bucket: 'unread', limit: 5 }).catch(() => [] as InviteAcceptedNotification[])
      : Promise.resolve([] as InviteAcceptedNotification[])

    const [
      receivedInvites,
      userMsg,
      inviteResult,
      foundResult,
      activityMwResult,
      activityUserResult,
      pendingFollowUps,
      inviteAcceptedItems,
    ] = await Promise.all([
      receivedInvitesPromise,
      userMsgPromise,
      campInvitesPromise,
      foundPromise,
      activityMwPromise,
      activityUserPromise,
      followUpPromise,
      inviteAcceptedPromise,
    ])

    userMessagePreview.value = userMsg.items || []
    userMessageUnreadCount.value =
      typeof userMsg.unread_count === 'number'
        ? userMsg.unread_count
        : userMessagePreview.value.length

    receivedDepartmentInvitePreview.value = (receivedInvites.items || []).slice(0, 5)
    receivedDepartmentInviteUnread.value =
      typeof receivedInvites.unread_count === 'number'
        ? receivedInvites.unread_count
        : receivedDepartmentInvitePreview.value.filter((e) => !e.read).length

    pendingDepartmentInvites.value = inviteResult.items || []
    publicFoundPreview.value = foundResult.items || []

    activityMwPreview.value = canManageMaterials.value ? (activityMwResult.items || []).slice(0, 5) : []
    activityMwUnreadCount.value = canManageMaterials.value
      ? typeof activityMwResult.unread_count === 'number'
        ? activityMwResult.unread_count
        : activityMwPreview.value.length
      : 0

    activityUserPreview.value = (activityUserResult.items || []).slice(0, 5)
    activityUserUnreadCount.value =
      typeof activityUserResult.unread_count === 'number'
        ? activityUserResult.unread_count
        : activityUserPreview.value.length

    inviteAcceptedPreview.value = (inviteAcceptedItems || []).slice(0, 5)
    inviteAcceptedUnreadCount.value = inviteAcceptedPreview.value.filter((n) => !n.read).length

    const followUpLen = Array.isArray(pendingFollowUps) ? pendingFollowUps.length : 0
    if (followUpLen === 0) {
      accountingBellDismissed.value = false
    }
    pendingFollowUpCount.value = followUpLen

    const accountingInBell =
      pendingFollowUpCount.value > 0 && !accountingBellDismissed.value ? pendingFollowUpCount.value : 0

    const taskCount = accountingInBell

    const qrUnread =
      !isUserRole.value && canManageQrContact.value
        ? typeof foundResult.unread_count === 'number'
          ? foundResult.unread_count
          : publicFoundPreview.value.filter((m) => m.status === 'open').length
        : 0

    const messageCount =
      userMessageUnreadCount.value +
      activityMwUnreadCount.value +
      activityUserUnreadCount.value +
      inviteAcceptedUnreadCount.value +
      qrUnread +
      receivedDepartmentInviteUnread.value +
      pendingDepartmentInvites.value.length
    unreadCount.value = taskCount + messageCount
  } catch {
    pendingDepartmentInvites.value = []
    receivedDepartmentInvitePreview.value = []
    receivedDepartmentInviteUnread.value = 0
    publicFoundPreview.value = []
    activityMwPreview.value = []
    activityMwUnreadCount.value = 0
    activityUserPreview.value = []
    activityUserUnreadCount.value = 0
    inviteAcceptedPreview.value = []
    inviteAcceptedUnreadCount.value = 0
    pendingFollowUpCount.value = 0
    userMessagePreview.value = []
    userMessageUnreadCount.value = 0
    unreadCount.value = 0
  } finally {
    isLoadingNotifications.value = false
  }
}

watch(
  () => headerNotificationsStore.refreshNonce,
  () => {
    void loadDepartmentInvites()
  }
)

async function openUserMessageFromBell(msg: UserDirectMessage) {
  const deptId = authStore.activeDepartmentId
  if (!deptId) return
  showNotifications.value = false
  userMessagePreview.value = userMessagePreview.value.filter((m) => m.id !== msg.id)
  if (!msg.read) {
    userMessageUnreadCount.value = Math.max(0, userMessageUnreadCount.value - 1)
    decrementUnreadCount()
  }
  try {
    if (!msg.read) {
      await markUserDirectMessageRead(deptId, msg.id)
      syncBellBadge()
    }
  } catch {
    /* navigate anyway */
  }
  void router.push({
    path: `/${deptId}/notifications`,
    query: { openMessage: msg.id },
  })
}

async function openFoundMessageFromBell(msg: PublicFoundItemMessage) {
  const deptId = authStore.activeDepartmentId
  if (!deptId) return
  showNotifications.value = false
  void router.push({
    path: `/${deptId}/notifications`,
    query: { highlight: msg.id },
  })
}

async function decideInvite(invite: PendingDepartmentActivityInvite, decision: 'accepted' | 'rejected') {
  const deptId = authStore.activeDepartmentId
  if (!deptId) return
  showNotifications.value = false
  pendingDepartmentInvites.value = pendingDepartmentInvites.value.filter(
    (entry) =>
      !(
        entry.activity_id === invite.activity_id &&
        entry.source_department_id === invite.source_department_id
      ),
  )
  decrementUnreadCount()
  try {
    await decideDepartmentActivityInvite({
      activityId: invite.activity_id,
      departmentId: deptId,
      decision,
    })
    syncBellBadge()
    toast.success(
      decision === 'accepted' ? t('layout.toast.inviteAccepted') : t('layout.toast.inviteRejected'),
    )
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('layout.toast.decisionSaveFailed'))
    void loadDepartmentInvites()
  }
}

function showHelp() {
  window.open('https://www.ematchef.ch/help', '_blank')
}

function showInfo() {
  // Info dialog
}

function editProfile() {
  const profile = authStore.profile
  profileForm.value = {
    first_name: profile?.firstName || profile?.first_name || '',
    last_name: profile?.lastName || profile?.last_name || '',
    email: profile?.email || '',
    nickname: profile?.nickname || '',
    avatar_initials: (profile?.avatarInitials || profile?.avatar_initials || '').toUpperCase().slice(0, 2),
    language: profile?.language || 'de',
    background_color: profile?.backgroundColor || profile?.background_color || '#EC4899',
    text_color: profile?.textColor || profile?.text_color || '#FFFFFF',
  }
  initialProfileFormSnapshot.value = serializeProfileForm(profileForm.value)
  resetPasswordForm()
  isEmailEditEnabled.value = false
  showEditProfileModal.value = true
  showUserDropdown.value = false
}

function switchDepartment() {
  const deptId = authStore.activeDepartmentId
  if (deptId) {
    router.push(`/${deptId}/settings/my-department`)
  }
  showUserDropdown.value = false
}

async function doLogout() {
  await authStore.logout()
  router.replace(getPostLogoutPath())
}

function activateLicense() {
  // License activation
}

function closeEditProfileModal() {
  showEditProfileModal.value = false
  isEmailEditEnabled.value = false
  initialProfileFormSnapshot.value = ''
  resetPasswordForm()
}

function resetPasswordForm() {
  passwordForm.value.current_password = ''
  passwordForm.value.new_password = ''
  passwordForm.value.confirm_new_password = ''
}

async function requestCloseEditProfileModal() {
  if (savingProfile.value) return
  if (hasUnsavedProfileChanges.value) {
    const shouldClose = await confirm.confirm({
      title: t('layout.confirm.unsavedTitle'),
      message: t('layout.confirm.unsavedMessage'),
      confirmText: t('layout.confirm.close'),
      cancelText: t('layout.confirm.back'),
      variant: 'warning',
    })
    if (!shouldClose) return
  }
  closeEditProfileModal()
}

function normalizeHexColor(value: string, fallback: string): string {
  const normalized = value.trim().toUpperCase()
  if (/^#[0-9A-F]{6}$/.test(normalized)) {
    return normalized
  }
  return fallback
}

async function toggleEmailEdit() {
  if (isEmailEditEnabled.value) {
    isEmailEditEnabled.value = false
    return
  }

  const confirmed = await confirm.confirm({
    title: t('layout.confirm.changeEmailTitle'),
    message: t('layout.confirm.changeEmailMessage'),
    confirmText: t('layout.confirm.enableEmailEdit'),
    cancelText: t('common.cancel'),
    variant: 'warning',
  })
  if (!confirmed) return

  isEmailEditEnabled.value = true
}

function applyAvatarColor(backgroundColor: string, textColor: string) {
  profileForm.value.background_color = backgroundColor
  profileForm.value.text_color = textColor
}

function isSelectedAvatarColor(backgroundColor: string, textColor: string): boolean {
  return (
    normalizeHexColor(profileForm.value.background_color, '#EC4899') === backgroundColor &&
    normalizeHexColor(profileForm.value.text_color, '#FFFFFF') === textColor
  )
}

function serializeProfileForm(form: typeof profileForm.value): string {
  return JSON.stringify({
    first_name: form.first_name.trim(),
    last_name: form.last_name.trim(),
    email: form.email.trim(),
    nickname: form.nickname.trim(),
    avatar_initials: form.avatar_initials.trim().toUpperCase().slice(0, 2),
    language: form.language,
    background_color: normalizeHexColor(form.background_color, '#EC4899'),
    text_color: normalizeHexColor(form.text_color, '#FFFFFF'),
  })
}

function buildAvatarInitials(explicitInitials: string, nickname: string, firstName: string, lastName: string): string {
  const explicit = explicitInitials.trim()
  if (explicit.length > 0) {
    return explicit.slice(0, 2).toUpperCase()
  }
  const nick = nickname.trim()
  if (nick.length > 0) {
    const cleanedNick = nick.replace(/\s+/g, '')
    return cleanedNick.slice(0, 2).toUpperCase()
  }
  const first = firstName.trim().charAt(0)
  const last = lastName.trim().charAt(0)
  return (first + last).toUpperCase() || '??'
}

async function saveProfile() {
  const profileId = authStore.profileId
  if (!profileId) {
    toast.error(t('layout.toast.profileLoadFailed'))
    return
  }
  const shouldUpdateProfile = hasUnsavedProfileChanges.value
  const shouldChangePassword = hasPasswordInput.value
  if (!shouldUpdateProfile && !shouldChangePassword) {
    toast.info(t('layout.toast.noChanges'))
    return
  }

  savingProfile.value = true
  try {
    if (shouldUpdateProfile) {
      const email = profileForm.value.email.trim()
      if (!email) {
        toast.error(t('layout.toast.enterEmail'))
        return
      }

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
      if (!emailRegex.test(email)) {
        toast.error(t('layout.toast.invalidEmail'))
        return
      }

      const payload = {
        email: isEmailEditEnabled.value ? email : (authStore.profile?.email || email),
        first_name: profileForm.value.first_name.trim(),
        last_name: profileForm.value.last_name.trim(),
        nickname: profileForm.value.nickname.trim(),
        avatar_initials: profileForm.value.avatar_initials.trim().toUpperCase().slice(0, 2),
        language: profileForm.value.language,
        background_color: normalizeHexColor(profileForm.value.background_color, '#EC4899'),
        text_color: normalizeHexColor(profileForm.value.text_color, '#FFFFFF'),
      }

      const updatedProfile = await updateProfile(profileId, payload)
      authStore.profile = updatedProfile
      if (isEmailEditEnabled.value && updatedProfile.pending_email) {
        toast.info(t('layout.toast.confirmationLinkSent'))
        isEmailEditEnabled.value = false
        profileForm.value.email = updatedProfile.email || profileForm.value.email
      }
    }

    if (shouldChangePassword) {
      const currentPassword = passwordForm.value.current_password
      const newPassword = passwordForm.value.new_password
      const confirmPassword = passwordForm.value.confirm_new_password

      if (passwordInlineError.value) {
        toast.error(passwordInlineError.value)
        return
      }

      const result = await changePassword(profileId, {
        current_password: currentPassword,
        new_password: newPassword,
        confirm_new_password: confirmPassword,
      })
      if (result.message) {
        toast.success(result.message)
      }
      const loginEmail = (authStore.profile?.email || profileForm.value.email || '').trim().toLowerCase()
      if (loginEmail) {
        try {
          await apiLogin(loginEmail, newPassword)
          await authStore.loadUserSession()
          toast.success(t('layout.toast.reloginSuccess'))
        } catch {
          // Falls Re-Login fehlschlaegt, bleibt die aktuelle Session bestehen solange der Token gueltig ist.
        }
      }
      resetPasswordForm()
    }

    if (shouldUpdateProfile && !shouldChangePassword) {
      toast.success(t('layout.toast.profileSaved'))
    } else if (shouldUpdateProfile && shouldChangePassword) {
      toast.success(t('layout.toast.profileAndPasswordSaved'))
    }

    closeEditProfileModal()
  } catch (error: any) {
    const message = error?.response?.data?.error || t('layout.toast.profileSaveFailed')
    toast.error(message)
  } finally {
    savingProfile.value = false
  }
}

function handleClickOutside(event: MouseEvent) {
  const target = event.target as HTMLElement
  if (!target.closest('.user-menu') && !target.closest('.user-dropdown')) {
    showUserDropdown.value = false
  }
  if (!target.closest('.header-icon-btn') && !target.closest('.notifications-dropdown')) {
    showNotifications.value = false
  }
  // Such-Icon nicht kollabieren: Klick auf Lupensymbol (kann nach expand aus DOM entfernt sein)
  if (target.closest('.search-icon-btn')) return
  if (!target.closest('.search-wrapper') && !target.closest('.global-search')) {
    globalSearchRef.value?.collapse()
  }
}

let notificationsPollTimer: ReturnType<typeof setInterval> | null = null

function startNotificationsPolling() {
  if (notificationsPollTimer) {
    clearInterval(notificationsPollTimer)
    notificationsPollTimer = null
  }
  if (!authStore.activeDepartmentId) return
  notificationsPollTimer = setInterval(() => {
    void loadDepartmentInvites()
  }, 60_000)
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  void loadDepartmentInvites()
  startNotificationsPolling()
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  if (notificationsPollTimer) {
    clearInterval(notificationsPollTimer)
    notificationsPollTimer = null
  }
})

watch(
  () => authStore.activeDepartmentId,
  () => {
    unreadCount.value = 0
    pendingDepartmentInvites.value = []
    publicFoundPreview.value = []
    userMessagePreview.value = []
    userMessageUnreadCount.value = 0
    accountingBellDismissed.value = false
    void loadDepartmentInvites()
    startNotificationsPolling()
  }
)
</script>

<style scoped>
.top-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid #e0e0e0;
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 0;
  z-index: 999;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.tabs-scroll {
  display: flex;
  align-items: center;
  gap: 4px;
  overflow-x: auto;
  flex: 1;
  min-width: 0;
}

.tabs-scroll::-webkit-scrollbar {
  height: 4px;
}

.detail-tab {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: #fff;
  font-size: 13px;
  color: #374151;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: border-color 0.2s, background 0.2s;
}

.detail-tab:hover {
  border-color: #9ca3af;
  background: #f9fafb;
}

.detail-tab.active {
  border-color: #3b82f6;
  background: #eff6ff;
  color: #1d4ed8;
}

.tab-label {
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.tab-dirty {
  color: #f59e0b;
  font-size: 10px;
  font-weight: bold;
}

.tab-close {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2px;
  border: none;
  background: none;
  color: #9ca3af;
  cursor: pointer;
  border-radius: 4px;
}

.tab-close:hover {
  color: #ef4444;
  background: #fef2f2;
}

.header-main-row {
  display: flex;
  align-items: center;
  gap: 0;
  height: 64px;
  padding: 0 24px;
}

.header-left {
  flex: 1;
  min-width: 0;
  display: flex;
  align-items: center;
}

.trial-warning {
  display: flex;
  align-items: center;
  gap: 12px;
  color: #666;
  font-size: 14px;
}

.btn-license {
  background-color: #ec4899;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}

.btn-license:hover {
  background-color: #db2777;
}

.header-center {
  flex: 1;
  display: flex;
  justify-content: center;
  padding: 0 24px;
}

.search-wrapper {
  display: flex;
  align-items: center;
}

.header-right {
  flex: 0 0 auto;
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 16px;
  position: relative;
}

.header-icon-btn {
  position: relative;
  background: none;
  border: none;
  padding: 8px;
  cursor: pointer;
  color: #666;
  transition: color 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.header-icon-btn:hover {
  color: #333;
}

.icon {
  width: 20px;
  height: 20px;
}

.notification-badge {
  position: absolute;
  top: 4px;
  right: 4px;
  background-color: #ef4444;
  color: white;
  font-size: 10px;
  font-weight: bold;
  padding: 2px 5px;
  border-radius: 10px;
  min-width: 18px;
  text-align: center;
}

.user-menu {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 12px;
  border-radius: 20px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.user-menu:hover {
  background-color: rgba(0, 0, 0, 0.05);
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 14px;
}

.user-name {
  font-size: 14px;
  font-weight: 500;
  color: #333;
}

.chevron-icon {
  width: 16px;
  height: 16px;
  color: #666;
}

.user-dropdown {
  position: absolute;
  top: 100%;
  right: 24px;
  margin-top: 8px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  min-width: 280px;
  z-index: 1000;
  overflow: hidden;
}

.notifications-dropdown {
  position: absolute;
  top: 100%;
  right: 150px;
  margin-top: 8px;
  width: 360px;
  max-height: min(420px, calc(100vh - 100px));
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 12px 28px rgba(0, 0, 0, 0.18);
  border: 1px solid #e5e7eb;
  z-index: 1200;
  overflow: hidden;
}

.notifications-dropdown-body {
  flex: 1;
  min-height: 0;
  overflow: auto;
}

.notifications-header {
  flex-shrink: 0;
  padding: 10px 12px;
  font-weight: 700;
  border-bottom: 1px solid #e5e7eb;
}

.notifications-section-label {
  margin: 0;
  padding: 8px 12px 4px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b7280;
}

.notifications-list .notifications-section-label:not(:first-child) {
  margin-top: 6px;
  padding-top: 10px;
  border-top: 1px solid #f3f4f6;
}

.notifications-dropdown-footer {
  flex-shrink: 0;
  padding: 8px 12px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
}

.notifications-more-fullwidth {
  width: 100%;
  box-sizing: border-box;
}

.notifications-empty {
  padding: 12px;
  color: #6b7280;
  font-size: 13px;
}

.notifications-list {
  display: grid;
}

.notification-item {
  padding: 10px 12px;
  border-bottom: 1px solid #f3f4f6;
  display: grid;
  gap: 6px;
}

button.notification-item--found {
  width: 100%;
  margin: 0;
  border: none;
  border-bottom: 1px solid #f3f4f6;
  background: transparent;
  text-align: left;
  cursor: pointer;
  font: inherit;
  color: inherit;
  padding: 10px 12px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

button.notification-item--found .notification-item__avatar {
  flex-shrink: 0;
  margin-top: 2px;
}

button.notification-item--found .notification-item__body {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 4px;
}

button.notification-item--found:hover {
  background: #f9fafb;
}

button.notification-item--activity {
  width: 100%;
  margin: 0;
  border: none;
  border-bottom: 1px solid #f3f4f6;
  background: transparent;
  text-align: left;
  cursor: pointer;
  font: inherit;
  color: inherit;
  padding: 10px 12px;
  display: grid;
  gap: 6px;
}

button.notification-item--activity:hover {
  background: #f9fafb;
}

button.notification-item--activity-mw {
  width: 100%;
  margin: 0;
  border: none;
  border-bottom: 1px solid #f3f4f6;
  background: transparent;
  text-align: left;
  cursor: pointer;
  font: inherit;
  color: inherit;
  padding: 10px 12px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

button.notification-item--activity-mw:hover {
  background: #f9fafb;
}

button.notification-item--user-message {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  width: 100%;
  padding: 10px 12px;
  border: none;
  border-bottom: 1px solid #f3f4f6;
  background: #fff;
  text-align: left;
  font: inherit;
  color: inherit;
  cursor: pointer;
}

button.notification-item--user-message:hover {
  background: #f9fafb;
}

button.notification-item--user-message.notification-item--unread {
  background: #eff6ff;
}

button.notification-item--user-message .notification-item__avatar {
  flex-shrink: 0;
  margin-top: 2px;
}

button.notification-item--user-message .notification-item__body {
  flex: 1;
  min-width: 0;
}

button.notification-item--invite-accepted {
  width: 100%;
  padding: 10px 12px;
  border: none;
  border-bottom: 1px solid #f3f4f6;
  background: #fff;
  text-align: left;
  font: inherit;
  color: inherit;
  cursor: pointer;
}

button.notification-item--invite-accepted:hover {
  background: #f9fafb;
}

button.notification-item--invite-accepted.notification-item--unread {
  background: #eff6ff;
}

.notification-item__body--full {
  flex: 1;
  min-width: 0;
}

button.notification-item--activity-mw .notification-item__avatar {
  flex-shrink: 0;
  margin-top: 2px;
}

button.notification-item--activity-mw .notification-item__body {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 4px;
}

button.notification-item--dept-invite {
  width: 100%;
  margin: 0;
  border: none;
  border-bottom: 1px solid #f3f4f6;
  background: transparent;
  text-align: left;
  cursor: pointer;
  font: inherit;
  color: inherit;
  padding: 10px 12px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

button.notification-item--dept-invite .notification-item__avatar {
  flex-shrink: 0;
  margin-top: 2px;
}

button.notification-item--dept-invite .notification-item__body {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 4px;
}

button.notification-item--dept-invite:hover {
  background: #f9fafb;
}

button.notification-item--dept-invite.notification-item--unread {
  background: #eff6ff;
  border-left: 3px solid #3b82f6;
}

button.notification-item--accounting {
  width: 100%;
  margin: 0;
  border: none;
  border-bottom: 1px solid #f3f4f6;
  background: linear-gradient(90deg, rgba(245, 158, 11, 0.12) 0%, transparent 12px);
  text-align: left;
  cursor: pointer;
  font: inherit;
  color: inherit;
  padding: 10px 12px;
  display: grid;
  gap: 6px;
}

button.notification-item--accounting:hover {
  background: linear-gradient(90deg, rgba(245, 158, 11, 0.18) 0%, #f9fafb 14px);
}

.notification-hint {
  font-size: 11px;
  color: #9ca3af;
}

.notification-title {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.notification-subtitle {
  font-size: 12px;
  color: #64748b;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notification-item--clickable {
  cursor: pointer;
}

.notification-item--clickable:hover {
  background: #f9fafb;
}

.notification-item--dept-invite.notification-item--clickable {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border-bottom: 1px solid #f3f4f6;
}

.notification-item--dept-invite.notification-item--clickable .notification-item__body {
  flex: 1;
  min-width: 0;
  display: grid;
  gap: 4px;
}

.notification-item--dept-invite.notification-item--clickable.notification-item--unread {
  background: #eff6ff;
  border-left: 3px solid #3b82f6;
}

.notification-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

/* Invite action buttons use shared ui/buttons.css */

.user-info {
  display: flex;
  align-items: center;
  padding: 16px;
  gap: 12px;
}

.user-avatar-large {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 18px;
}

.user-details {
  flex: 1;
}

.user-name-full {
  font-weight: 600;
  font-size: 14px;
  color: #333;
  margin-bottom: 4px;
}

.user-email {
  font-size: 12px;
  color: #666;
}

.dropdown-divider {
  height: 1px;
  background-color: #e0e0e0;
  margin: 8px 0;
}

.dropdown-item {
  width: 100%;
  display: flex;
  align-items: center;
  padding: 12px 16px;
  background: none;
  border: none;
  text-align: left;
  cursor: pointer;
  transition: background-color 0.2s;
  font-size: 14px;
  color: #333;
  gap: 12px;
}

.dropdown-item:hover {
  background-color: #f5f5f5;
}

.dropdown-item.logout {
  color: #ef4444;
}

.item-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.dept-switch-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.dept-switch-hint {
  font-size: 11px;
  color: #3b82f6;
  font-weight: 500;
}

.profile-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  padding: 12px;
}

.profile-modal {
  width: 100%;
  max-width: 620px;
  max-height: calc(100vh - 24px);
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 12px 36px rgba(15, 23, 42, 0.25);
  overflow: auto;
}

.profile-modal-form {
  margin: 0;
}

.profile-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid #e5e7eb;
}

.profile-modal-header h3 {
  margin: 0;
  font-size: 16px;
  color: #1f2937;
}

.modal-close-btn {
  border: none;
  background: transparent;
  font-size: 24px;
  line-height: 1;
  cursor: pointer;
  color: #6b7280;
}

.profile-modal-content {
  padding: 14px 16px;
}

.profile-avatar-preview-wrap {
  display: flex;
  justify-content: center;
  margin-bottom: 12px;
}

.profile-avatar-preview {
  width: 54px;
  height: 54px;
  font-size: 20px;
}

.profile-top-row {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 12px;
  align-items: start;
  margin-bottom: 10px;
}

.profile-top-fields {
  display: grid;
  gap: 7px;
}

.profile-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 9px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-field-full {
  grid-column: 1 / -1;
}

.form-field span {
  font-size: 11px;
  color: #6b7280;
}

.form-field input,
.form-field select {
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 8px 9px;
  font-size: 13px;
  background: #fff;
}

.form-field input.is-readonly {
  background: #f9fafb;
  color: #6b7280;
}

.form-field input:focus,
.form-field select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

.color-field {
  display: flex;
  gap: 8px;
}

.color-field input[type='color'] {
  width: 40px;
  min-width: 40px;
  padding: 2px;
  cursor: pointer;
}

.email-edit-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.email-edit-row input {
  flex: 1;
}

.email-edit-btn {
  width: 32px;
  height: 32px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  color: #4b5563;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.email-edit-btn svg {
  width: 14px;
  height: 14px;
}

.email-edit-btn.active {
  border-color: #2563eb;
  color: #2563eb;
  background: #eff6ff;
}

.email-edit-hint {
  color: #92400e;
  font-size: 11px;
}

.email-pending-hint {
  color: #1d4ed8;
  font-size: 11px;
}

.password-inline-error {
  color: #b91c1c;
  font-size: 11px;
}

.password-inline-success {
  color: #166534;
  font-size: 11px;
}

.avatar-palette-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.palette-row-label {
  font-size: 11px;
  color: #6b7280;
}

.avatar-palette-row {
  display: grid;
  grid-template-columns: repeat(10, minmax(0, 1fr));
  gap: 6px;
}

.avatar-color-chip {
  width: 30px;
  height: 30px;
  border-radius: 9999px;
  border: 2px solid transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
  cursor: pointer;
  padding: 0;
}

.avatar-color-chip.selected {
  border-color: #111827;
  box-shadow: 0 0 0 2px rgba(17, 24, 39, 0.15);
}

.profile-modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid #e5e7eb;
}

.profile-status-hint {
  margin-right: auto;
  font-size: 11px;
  color: #d97706;
  min-height: 16px;
  opacity: 0;
  transition: opacity 0.15s ease;
}

.profile-status-hint.visible {
  opacity: 1;
}

/* Profile modal footer buttons use shared ui/buttons.css */
</style>
