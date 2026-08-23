<template>
  <div class="my-department-settings">
    <div class="header-section">
      <div>
        <h1>{{ t('settings.myDepartment.title') }}</h1>
      </div>
    </div>

    <!-- Department Selector (wenn User in mehreren Departments ist) -->
    <div v-if="userDepartments.length > 1" class="department-selector">
      <ESelect
        id="department-select"
        v-model="selectedDepartmentId"
        :items="departmentSelectItems"
        :label="t('settings.myDepartment.selectDepartment')"
        hide-details
        @update:model-value="onDepartmentChange"
      />
      <div class="dept-select-row">
        <EButton
          v-if="!isSelectedDeptPrimary"
          variant="secondary"
          size="small"
          :disabled="isSavingPrimary"
          :loading="isSavingPrimary"
          @click="setAsPrimary"
        >
          <v-icon icon="mdi-star-outline" start size="18" />
          {{ isSavingPrimary ? t('common.saving') : t('settings.myDepartment.setAsPrimary') }}
        </EButton>
        <span v-else class="current-primary-badge">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
            <path d="M8 1L10 5.5L15 6L11.5 9.5L12.5 14.5L8 12L3.5 14.5L4.5 9.5L1 6L6 5.5L8 1Z" fill="#f59e0b" stroke="#f59e0b" stroke-width="1"/>
          </svg>
          {{ t('settings.myDepartment.primaryDepartment') }}
        </span>
      </div>
      <p class="selector-hint">{{ t('settings.myDepartment.departmentMembershipHint', { count: userDepartments.length }) }}</p>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="inline"
      :message="t('settings.myDepartment.loadingDepartmentData')"
    />

    <div v-else-if="error" class="my-dept-settings-error">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadDepartment">{{ t('common.retry') }}</EButton>
    </div>

    <div v-else-if="department" class="department-content">
      <div class="dept-identity">
        <h2 class="dept-identity__name">{{ department.name }}</h2>
        <p class="dept-identity__meta">
          <span class="dept-identity__label">{{ t('settings.myDepartment.fields.departmentId') }}</span>
          <span class="dept-identity__value mono">{{ department.id }}</span>
        </p>
        <p v-if="departmentHierarchyLabel" class="dept-identity__meta">
          <span class="dept-identity__label">{{ t('settings.myDepartment.identityBelongsTo') }}</span>
          <span class="dept-identity__value">{{ departmentHierarchyLabel }}</span>
        </p>
      </div>

      <details
        v-if="canManageJoinCode"
        class="info-card dept-accordion"
        :open="openAccordion === 'join-code'"
      >
        <summary class="dept-accordion__summary" @click="onDeptAccordionSummaryClick('join-code', $event)">
          <span class="dept-accordion__title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="card-icon" aria-hidden="true">
              <path d="M3 11H11V13H3V11ZM3 7H11V9H3V7ZM3 15H11V17H3V15ZM13 7H21V17H13V7Z" fill="#3b82f6"/>
            </svg>
            {{ t('settings.nav.joinCode') }}
          </span>
          <span class="dept-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="dept-accordion__body">
          <ELoadingState
            v-if="isInviteLoading && !inviteData"
            variant="inline"
            :message="t('settings.joinCode.loading')"
          />
          <template v-else>
            <div class="join-code-row">
              <code class="join-code">{{ inviteData?.join_code || '...' }}</code>
            </div>
            <p v-if="inviteData?.invite_url" class="join-meta">{{ t('settings.joinCode.withAccount') }} {{ inviteData.invite_url }}</p>
            <p v-if="inviteData?.register_invite_url" class="join-meta">{{ t('settings.joinCode.withoutAccount') }} {{ inviteData.register_invite_url }}</p>
            <div v-if="inviteQrDataUrl" class="join-qr">
              <img :src="inviteQrDataUrl" alt="" />
            </div>
            <div v-if="pendingInvites.length > 0" class="pending-invites-block">
              <h3>{{ t('settings.joinCode.pendingTitle') }}</h3>
              <div v-for="invite in pendingInvites" :key="invite.id" class="pending-invite-item">
                <span>{{ invite.email }} ({{ formatRole(invite.role) }})</span>
                <EButton variant="danger" size="small" @click="removePendingInviteItem(invite.id)">
                  {{ t('common.delete') }}
                </EButton>
              </div>
            </div>
            <div class="join-actions-row">
              <EButton variant="secondary" size="small" :disabled="!inviteData" @click="copyJoinCode">
                {{ t('settings.joinCode.copyCode') }}
              </EButton>
              <EButton variant="secondary" size="small" :disabled="!inviteData" @click="copyInviteLink">
                {{ t('settings.joinCode.copyInviteLink') }}
              </EButton>
              <EButton variant="secondary" size="small" :disabled="!inviteData?.register_invite_url" @click="copyRegisterInviteLink">
                {{ t('settings.joinCode.copyRegisterLink') }}
              </EButton>
              <EButton
                variant="secondary"
                size="x-small"
                :disabled="isInviteLoading"
                :loading="isInviteLoading"
                @click="regenerateInviteCode"
              >
                {{ isInviteLoading ? t('settings.joinCode.loading') : t('settings.joinCode.regenerate') }}
              </EButton>
            </div>
          </template>
        </div>
      </details>

      <details
        class="info-card dept-accordion"
        data-onboarding="settings-members-accordion"
        :open="openAccordion === 'members'"
      >
        <summary class="dept-accordion__summary" @click="onDeptAccordionSummaryClick('members', $event)">
          <span class="dept-accordion__title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="card-icon" aria-hidden="true">
              <path d="M12 12C14.7614 12 17 9.76142 17 7C17 4.23858 14.7614 2 12 2C9.23858 2 7 4.23858 7 7C7 9.76142 9.23858 12 12 12Z" fill="#3b82f6"/>
              <path d="M3 20C3 16.6863 6.13401 14 10 14H14C17.866 14 21 16.6863 21 20V22H3V20Z" fill="#3b82f6"/>
            </svg>
            {{ t('settings.myDepartment.membersTitle', { n: displayMembersCount }) }}
          </span>
          <span class="dept-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="dept-accordion__body">
          <!-- Basis-Mitglieder (u): nur einfache Liste -->
          <template v-if="membersReadOnly">
            <div v-if="department.users && department.users.length > 0" class="users-list">
              <div v-for="user in department.users" :key="user.id" class="user-item">
                <UserAvatarBadge :user="user" size="md" />
                <div class="user-info">
                  <span class="user-name">{{ user.name }}</span>
                  <span class="user-email">{{ user.email }}</span>
                </div>
                <span class="user-role-badge">{{ formatRole(user.role) }}</span>
              </div>
            </div>
            <p v-else class="empty-users">{{ t('settings.myDepartment.noMembers') }}</p>
          </template>
          <!-- MW/DC / L1–L3: volle Benutzer-Verwaltung (wie /settings/users) -->
          <UsersSettingsView
            v-else-if="membersPanelMounted && selectedDepartmentId"
            :department-id="selectedDepartmentId"
            embedded
            @changed="onMembersPanelChanged"
          />
        </div>
      </details>

      <details
        v-if="selectedDepartmentId && !isSelectedDeptGrossanlass"
        class="info-card dept-accordion"
        :open="openAccordion === 'groups'"
      >
        <summary class="dept-accordion__summary" @click="onDeptAccordionSummaryClick('groups', $event)">
          <span class="dept-accordion__title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="card-icon" aria-hidden="true">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" fill="#3b82f6"/>
              <circle cx="9" cy="7" r="4" fill="#3b82f6"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" stroke="#60a5fa" stroke-width="2"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75" stroke="#60a5fa" stroke-width="2"/>
            </svg>
            {{ t('settings.myDepartment.groupsTitle', { n: displayGroupsCount }) }}
          </span>
          <span class="dept-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="dept-accordion__body">
          <GroupsSettingsView
            v-if="groupsPanelMounted && selectedDepartmentId"
            :department-id="selectedDepartmentId"
            embedded
            @changed="onGroupsPanelChanged"
          />
        </div>
      </details>

      <details
        v-if="!isUserRole"
        class="info-card dept-accordion"
        :open="openAccordion === 'stats'"
      >
        <summary class="dept-accordion__summary" @click="onDeptAccordionSummaryClick('stats', $event)">
          <span class="dept-accordion__title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="card-icon" aria-hidden="true">
              <path d="M4 20V10H8V20H4ZM10 20V4H14V20H10ZM16 20V14H20V20H16Z" fill="#3b82f6"/>
            </svg>
            {{ t('settings.myDepartment.statsTitle') }}
          </span>
          <span class="dept-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="dept-accordion__body">
          <div class="stats-grid">
            <div class="stat-item">
              <span class="stat-value">{{ department.users?.length || 0 }}</span>
              <span class="stat-label">{{ t('settings.myDepartment.stats.members') }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-value">{{ subDepartmentsCount }}</span>
              <span class="stat-label">{{ t('settings.myDepartment.stats.subDepartments') }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-value">{{ storageAddresses.length }}</span>
              <span class="stat-label">{{ t('settings.myDepartment.stats.storageLocations') }}</span>
            </div>
            <div class="stat-item">
              <span class="stat-value">{{ addresses.length }}</span>
              <span class="stat-label">{{ t('settings.myDepartment.stats.addresses') }}</span>
            </div>
          </div>
        </div>
      </details>

      <details
        v-if="!isUserRole && selectedDepartmentId"
        class="info-card dept-accordion"
        :open="openAccordion === 'storage'"
      >
        <summary
          class="dept-accordion__summary"
          data-onboarding="settings-dept-storage-accordion"
          @click="onDeptAccordionSummaryClick('storage', $event)"
        >
          <span class="dept-accordion__title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="card-icon" aria-hidden="true">
              <path d="M20 7H4C2.9 7 2 7.9 2 9V19C2 20.1 2.9 21 4 21H20C21.1 21 22 20.1 22 19V9C22 7.9 21.1 7 20 7Z" fill="#3b82f6" />
              <path d="M12 3L2 7H22L12 3Z" fill="#60a5fa" />
            </svg>
            {{ t('settings.myDepartment.storageTitle', { n: storageAddresses.length }) }}
          </span>
          <span class="dept-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="dept-accordion__body">
          <DepartmentAddressKindPanel
            v-if="storagePanelMounted && selectedDepartmentId"
            :department-id="selectedDepartmentId"
            address-kind="storage"
            @changed="refreshAddressCounts"
          />
        </div>
      </details>

      <details
        v-if="!isUserRole && selectedDepartmentId"
        class="info-card dept-accordion"
        :open="openAccordion === 'billing'"
      >
        <summary
          class="dept-accordion__summary"
          data-onboarding="settings-dept-billing-accordion"
          @click="onDeptAccordionSummaryClick('billing', $event)"
        >
          <span class="dept-accordion__title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="card-icon" aria-hidden="true">
              <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3Z" fill="#3b82f6" />
              <path d="M7 7H17V9H7V7ZM7 11H17V13H7V11ZM7 15H13V17H7V15Z" fill="white" />
            </svg>
            {{ t('settings.myDepartment.billingTitle', { n: billingAddresses.length }) }}
          </span>
          <span class="dept-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="dept-accordion__body">
          <DepartmentAddressKindPanel
            v-if="billingPanelMounted && selectedDepartmentId"
            :department-id="selectedDepartmentId"
            address-kind="billing"
            @changed="refreshAddressCounts"
          />
        </div>
      </details>

      <details
        v-if="showDevTools && canManageJoinCode"
        class="info-card db-reset-card dept-accordion"
        :open="openAccordion === 'activities-reset'"
      >
        <summary class="dept-accordion__summary" @click="onDeptAccordionSummaryClick('activities-reset', $event)">
          <span class="dept-accordion__title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="card-icon card-icon-danger" aria-hidden="true">
              <path d="M6 19C6 20.1 6.9 21 8 21H16C17.1 21 18 20.1 18 19V7H6V19ZM19 4H15.5L14.5 3H9.5L8.5 4H5V6H19V4Z" fill="#dc2626"/>
            </svg>
            {{ t('settings.myDepartment.activitiesResetTitle') }}
          </span>
          <span class="dept-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="dept-accordion__body">
          <div class="db-reset-row">
            <p class="db-reset-desc">
              {{ t('settings.myDepartment.activitiesResetDescription') }}
              <strong>{{ t('settings.myDepartment.activitiesResetDescriptionStrong') }}</strong>
            </p>
            <EButton
              variant="danger"
              size="small"
              :disabled="isResettingActivities"
              :loading="isResettingActivities"
              @click="resetDepartmentActivitiesAction"
            >
              {{ isResettingActivities ? t('settings.myDepartment.resetting') : t('settings.myDepartment.resetActivities') }}
            </EButton>
          </div>
          <p class="selector-hint db-reset-warning">
            {{ t('settings.myDepartment.activitiesResetWarning') }}
          </p>
        </div>
      </details>

      <details
        v-if="showDevTools && canManageJoinCode"
        class="info-card db-reset-card dept-accordion"
        :open="openAccordion === 'db-reset'"
      >
        <summary class="dept-accordion__summary" @click="onDeptAccordionSummaryClick('db-reset', $event)">
          <span class="dept-accordion__title">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="card-icon card-icon-danger" aria-hidden="true">
              <path d="M4 7V4H20V7M9 20H15V10H9V20M5 7H19V20H5V7Z" fill="#dc2626"/>
            </svg>
            {{ t('settings.myDepartment.dbResetTitle') }}
          </span>
          <span class="dept-accordion__chevron" aria-hidden="true">▾</span>
        </summary>
        <div class="dept-accordion__body">
          <div class="db-reset-row">
            <p class="db-reset-desc">
              {{ t('settings.myDepartment.dbResetDescription') }}
              <strong>{{ t('settings.myDepartment.dbResetDescriptionStrong') }}</strong>
            </p>
            <EButton
              variant="danger"
              size="small"
              :disabled="isResettingDb"
              :loading="isResettingDb"
              @click="resetDepartmentDb"
            >
              {{ isResettingDb ? t('settings.myDepartment.resetting') : t('settings.myDepartment.resetDb') }}
            </EButton>
          </div>
          <p class="selector-hint db-reset-warning">
            {{ t('settings.myDepartment.dbResetWarning') }}
          </p>
        </div>
      </details>
    </div>

    <!-- No Department -->
    <EEmptyState
      v-else
      variant="default"
      :title="t('settings.myDepartment.noDepartmentSelected')"
    />

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useDepartmentRoleLabelsStore } from '@/stores/departmentRoleLabels'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { getDepartment, getDepartments, type Department } from '@/api/departments'
import { getOrganisation } from '@/api/organisations'
import UserAvatarBadge from '@/components/user/UserAvatarBadge.vue'
import { setPrimaryDepartment as apiSetPrimaryDepartment } from '@/api/auth'
import {
  getAddresses,
  type Address,
} from '@/api/addresses'
import { getGroups } from '@/api/groups'
import {
  deletePendingInvite,
  getDepartmentInvite,
  getPendingInvites,
  regenerateDepartmentInvite,
  type DepartmentInviteData,
  type PendingInvite
} from '@/api/joinRequests'
import {
  getPublicSharingSettings,
  resetDepartmentDb as apiResetDepartmentDb,
  resetDepartmentActivities as apiResetDepartmentActivities,
  savePublicSharingSettings,
  type PublicFoundContactDelivery,
} from '@/api/departmentSettings'
import { buildOnboardingDoneKey, buildOnboardingPausedKey, buildOnboardingStateKey } from '@/utils/departmentOnboarding'
import { departmentDisplayName, departmentHomePath, isGrossanlassDepartment } from '@/utils/departmentSwitch'
import { isDevToolsEnvironment } from '@/utils/devEnvironmentBanner'
import QRCode from 'qrcode'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import DepartmentAddressKindPanel from '@/components/settings/DepartmentAddressKindPanel.vue'
import UsersSettingsView from '@/views/settings/UsersSettingsView.vue'
import GroupsSettingsView from '@/views/settings/GroupsSettingsView.vue'
import { EButton, ESelect } from '@/components/form/base'
import { ONBOARDING_TOUR_QUERY, ONBOARDING_TOUR_STEP_QUERY } from '@/config/onboardingTours'

const route = useRoute()
const authStore = useAuthStore()
const roleLabelsStore = useDepartmentRoleLabelsStore()
const toast = useToast()
const confirm = useConfirm()
const { t } = useI18n()
const { isUserRole, isDepartmentLeader } = useDepartmentMemberRole()

/** Reines Mitglied «u»: nur Liste; L1–L3 und MW/DC: volle Verwaltung. */
const membersReadOnly = computed(() => isUserRole.value && !isDepartmentLeader.value)

const isSelectedDeptGrossanlass = computed(() => {
  const id = selectedDepartmentId.value || authStore.activeDepartmentId
  return authStore.isDepartmentGrossanlass(id)
})

const isLoading = ref(false)
const error = ref<string | null>(null)
const department = ref<Department | null>(null)
const subDepartmentsCount = ref(0)
const selectedDepartmentId = ref<string | null>(null)
const organisationName = ref('')
const parentDepartmentNames = ref<string[]>([])
const inviteData = ref<DepartmentInviteData | null>(null)
const inviteQrDataUrl = ref('')
const isInviteLoading = ref(false)
const pendingInvites = ref<PendingInvite[]>([])
const isResettingDb = ref(false)
const isResettingActivities = ref(false)
const showDevTools = computed(() => isDevToolsEnvironment())
const isSavingPublicSettings = ref(false)
const publicContactEmail = ref('')
const publicContactNote = ref('')
const publicShowContactForm = ref(true)
const publicShowContactEmail = ref(true)
const publicShowContactNote = ref(true)
const publicFoundContactDelivery = ref<PublicFoundContactDelivery>('both')

// Primary Department State
const isSavingPrimary = ref(false)

// Ist das aktuell ausgewählte Department das primäre?
const isSelectedDeptPrimary = computed(() => {
  if (!selectedDepartmentId.value) return false
  const dept = userDepartments.value.find(d => d.department_id === selectedDepartmentId.value)
  return dept?.is_primary === true
})

// Adressen-Counts für Statistiken / Accordion-Titel
const addresses = ref<Address[]>([])
const storagePanelMounted = ref(false)
const billingPanelMounted = ref(false)
const membersPanelMounted = ref(false)
const membersPanelCount = ref<number | null>(null)
const groupsPanelMounted = ref(false)
const groupsPanelCount = ref<number | null>(null)

const storageAddresses = computed(() => addresses.value.filter(a => a.type === 'storage'))
const billingAddresses = computed(() => addresses.value.filter(a => a.type === 'billing'))

const displayMembersCount = computed(
  () => membersPanelCount.value ?? department.value?.users?.length ?? 0,
)

const displayGroupsCount = computed(() => groupsPanelCount.value ?? 0)

/** Organisation + Eltern-Departments (Namen, ohne IDs), Wurzel → Eltern. */
const departmentHierarchyLabel = computed(() => {
  const parts: string[] = []
  if (organisationName.value.trim()) {
    parts.push(organisationName.value.trim())
  }
  parts.push(...parentDepartmentNames.value)
  return parts.join(' · ')
})

async function resolveParentDepartmentNames(dept: Department, catalog: Department[]) {
  const byId = new Map(catalog.map((d) => [d.id, d]))
  const namesClosestFirst: string[] = []
  let parentId = dept.parent_id || null
  const seen = new Set<string>()

  while (parentId && !seen.has(parentId)) {
    seen.add(parentId)
    let parent = byId.get(parentId) || null
    if (!parent) {
      try {
        parent = await getDepartment(parentId)
        byId.set(parent.id, parent)
      } catch {
        break
      }
    }
    namesClosestFirst.push(parent.name)
    parentId = parent.parent_id || null
  }

  // Wurzel → … → direktes Eltern-Department
  parentDepartmentNames.value = namesClosestFirst.reverse()
}

type DeptAccordionId =
  | 'join-code'
  | 'members'
  | 'groups'
  | 'stats'
  | 'storage'
  | 'billing'
  | 'activities-reset'
  | 'db-reset'

/** Nur ein Accordion gleichzeitig offen; Start: alle geschlossen. */
const openAccordion = ref<DeptAccordionId | null>(null)
/** Verhindert Scroll beim initialen Mount von :open. */
const accordionScrollEnabled = ref(false)

/** Touren: passendes Accordion öffnen und bei Schlüssel-Schritten nach oben scrollen. */
watch(
  () => [route.query[ONBOARDING_TOUR_QUERY], route.query[ONBOARDING_TOUR_STEP_QUERY]] as const,
  async ([tourId, stepId]) => {
    const step = Number(stepId || 0)
    const inviteTour = tourId === 'invite-users' && step >= 2 && step <= 10
    const coachTour = tourId === 'default-coach' && step >= 2 && step <= 5
    const detailsTour = tourId === 'department-details' && step >= 3

    if (inviteTour || coachTour) {
      openAccordion.value = 'members'
      membersPanelMounted.value = true
      if (
        (tourId === 'invite-users' && (step === 3 || step === 7))
        || (tourId === 'default-coach' && step === 3)
      ) {
        await nextTick()
        requestAnimationFrame(() => {
          const panel = document.querySelector(
            '[data-onboarding="settings-members-accordion"]'
          ) as HTMLElement | null
          panel?.scrollIntoView({ behavior: 'auto', block: 'start' })
        })
      }
      return
    }

    if (!detailsTour) return

    // Schritte 5–8: Einstellungen → Lager (andere View / Modal)
    if (step >= 5 && step <= 8) return

    if (step <= 4) {
      openAccordion.value = 'storage'
      storagePanelMounted.value = true
    } else if (step >= 9 && step <= 10) {
      openAccordion.value = 'billing'
      billingPanelMounted.value = true
    } else if (step === 11) {
      // Abschluss: Billing sichtbar lassen (Kontext der Tour)
      openAccordion.value = 'billing'
      billingPanelMounted.value = true
    } else {
      return
    }

    if (step === 3 || step === 9 || step === 10) {
      await nextTick()
      await nextTick()
      requestAnimationFrame(() => {
        const sel =
          step === 3
            ? '[data-onboarding="settings-dept-storage-accordion"]'
            : step === 10
              ? '[data-onboarding="settings-dept-billing-panel"]'
              : '[data-onboarding="settings-dept-billing-accordion"]'
        const panel = document.querySelector(sel) as HTMLElement | null
        panel?.scrollIntoView({ behavior: 'auto', block: 'start' })
      })
    }
  },
  { immediate: true },
)

function mountAccordionPanel(id: DeptAccordionId) {
  if (id === 'members') membersPanelMounted.value = true
  if (id === 'groups') groupsPanelMounted.value = true
  if (id === 'storage') storagePanelMounted.value = true
  if (id === 'billing') billingPanelMounted.value = true
}

function scrollAccordionIntoView(el: HTMLElement) {
  if (!accordionScrollEnabled.value) return
  nextTick(() => {
    requestAnimationFrame(() => {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
  })
}

function onDeptAccordionSummaryClick(id: DeptAccordionId, event: MouseEvent) {
  event.preventDefault()
  const el = (event.currentTarget as HTMLElement).closest('details') as HTMLDetailsElement | null
  if (!el) return

  if (openAccordion.value === id) {
    openAccordion.value = null
    return
  }

  openAccordion.value = id
  mountAccordionPanel(id)
  scrollAccordionIntoView(el)
}

function onMembersPanelChanged(count: number) {
  membersPanelCount.value = count
}

function onGroupsPanelChanged(count: number) {
  groupsPanelCount.value = count
}

async function refreshAddressCounts() {
  const deptId = selectedDepartmentId.value
  if (!deptId) return
  await loadAddresses(deptId)
}

// Liste aller Departments des Users
const userDepartments = computed(() => authStore.departments || [])

const departmentSelectItems = computed(() =>
  userDepartments.value.map((dept) => {
    const name = departmentDisplayName(dept, t('grossanlass.label'))
    const primary = dept.is_primary ? ` ⭐ (${t('settings.myDepartment.primary')})` : ''
    return {
      title: `${name}${primary} – ${formatRole(dept.role)}`,
      value: dept.department_id,
    }
  }),
)

// Aktuelle Rolle für das ausgewählte Department
const currentRole = computed(() => {
  if (!selectedDepartmentId.value) return 'user'
  const dept = userDepartments.value.find(d => d.department_id === selectedDepartmentId.value)
  return dept?.role || 'user'
})

const canManageJoinCode = computed(() => {
  const normalizedRole = String(currentRole.value || '').toLowerCase().trim()
  return ['dc', 'depchef', 'mw', 'matwart', 'sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(normalizedRole)
})

// Wenn sich das ausgewählte Department ändert – Store + URL, dann voller Seiten-Reload (frischer State)
async function onDepartmentChange() {
  if (!selectedDepartmentId.value) return
  const newDeptId = selectedDepartmentId.value
  const newDept = userDepartments.value.find((d) => d.department_id === newDeptId)
  await authStore.setActiveDepartment(newDeptId)

  if (newDept && isGrossanlassDepartment(newDept)) {
    window.location.assign(departmentHomePath(newDeptId))
    return
  }

  const oldDeptId = route.params.departmentId as string | undefined
  if (oldDeptId && oldDeptId !== newDeptId) {
    const newPath = route.path.replace(`/${oldDeptId}`, `/${newDeptId}`)
    window.location.assign(newPath)
    return
  }
  window.location.reload()
}

// Primäres Department in der DB speichern
async function setAsPrimary() {
  if (!selectedDepartmentId.value || isSavingPrimary.value) return
  
  isSavingPrimary.value = true
  
  try {
    // In der DB speichern
    const uid = authStore.userId
    if (!uid) throw new Error('Nicht angemeldet')
    await apiSetPrimaryDepartment(uid, selectedDepartmentId.value)
    
    // Auth Store lokal aktualisieren (is_primary Flags updaten)
    authStore.departments.forEach(d => {
      d.is_primary = d.department_id === selectedDepartmentId.value
    })
    
    // Auch den aktiven Department-ID im Store setzen
    authStore.setActiveDepartment(selectedDepartmentId.value)
    
    toast.success(t('settings.myDepartment.toastPrimarySaved'))
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.myDepartment.toastPrimarySaveError'))
  } finally {
    isSavingPrimary.value = false
  }
}

function formatRole(role: string): string {
  return roleLabelsStore.labelFor(role, selectedDepartmentId.value || authStore.activeDepartmentId, t)
}

async function loadDepartment(departmentId?: string) {
  const deptId = departmentId || selectedDepartmentId.value || authStore.activeDepartmentId
  if (!deptId) {
    error.value = t('settings.myDepartment.noDepartmentSelected')
    return
  }

  isLoading.value = true
  error.value = null

  try {
    // Lade Department mit Users
    department.value = await getDepartment(deptId)
    
    // Zähle Unter-Departments + Eltern-Namen für Zugehörigkeit
    const allDepartments = await getDepartments()
    subDepartmentsCount.value = allDepartments.filter(d => d.parent_id === deptId).length

    organisationName.value = ''
    parentDepartmentNames.value = []
    try {
      const org = await getOrganisation(department.value.organisation_id)
      organisationName.value = org.name || ''
    } catch {
      organisationName.value = ''
    }
    await resolveParentDepartmentNames(department.value, allDepartments)
    
    // Lade Adressen (Lagerplätze, Rechnungsadressen, etc.)
    await loadAddresses(deptId)
    if (authStore.isDepartmentGrossanlass(deptId)) {
      groupsPanelCount.value = 0
      if (openAccordion.value === 'groups') openAccordion.value = null
    } else {
      await loadGroupsCount(deptId)
    }
    await loadInviteCode(deptId)

    if (!canManageJoinCode.value && openAccordion.value === 'join-code') {
      openAccordion.value = null
    }
  } catch (err: any) {
    error.value = err.response?.data?.error || t('settings.myDepartment.loadError')
  } finally {
    isLoading.value = false
  }
}

async function loadPublicSettings(deptId: string) {
  try {
    const settings = await getPublicSharingSettings(deptId)
    publicContactEmail.value = settings.publicContactEmail
    publicContactNote.value = settings.publicContactNote
    publicShowContactForm.value = settings.publicShowContactForm
    publicShowContactEmail.value = settings.publicShowContactEmail
    publicShowContactNote.value = settings.publicShowContactNote
    publicFoundContactDelivery.value = settings.publicFoundContactDelivery
  } catch {
    publicContactEmail.value = ''
    publicContactNote.value = ''
    publicShowContactForm.value = true
    publicShowContactEmail.value = true
    publicShowContactNote.value = true
    publicFoundContactDelivery.value = 'both'
  }
}

async function savePublicSettings() {
  if (!selectedDepartmentId.value || isSavingPublicSettings.value) return
  isSavingPublicSettings.value = true
  try {
    await savePublicSharingSettings(selectedDepartmentId.value, {
      publicContactEmail: publicContactEmail.value.trim(),
      publicContactNote: publicContactNote.value.trim(),
      publicShowContactForm: publicShowContactForm.value,
      publicShowContactEmail: publicShowContactEmail.value,
      publicShowContactNote: publicShowContactNote.value,
      publicFoundContactDelivery: publicFoundContactDelivery.value,
    })
    toast.success(t('settings.publicMaterialPage.saveSuccess'))
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.publicMaterialPage.saveError'))
  } finally {
    isSavingPublicSettings.value = false
  }
}

async function resetDepartmentActivitiesAction() {
  if (!selectedDepartmentId.value || isResettingActivities.value) return

  const ok = await confirm.confirm({
    title: t('settings.myDepartment.activitiesReset.confirmTitle'),
    message: t('settings.myDepartment.activitiesReset.confirmMessage'),
    confirmText: t('settings.myDepartment.activitiesReset.confirmAction'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return

  isResettingActivities.value = true
  try {
    const result = await apiResetDepartmentActivities(selectedDepartmentId.value)
    toast.success(result.message || t('settings.myDepartment.activitiesReset.toastSuccess'))
    window.location.href = `/${selectedDepartmentId.value}/activities`
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.myDepartment.activitiesReset.toastError'))
  } finally {
    isResettingActivities.value = false
  }
}

async function resetDepartmentDb() {
  if (!selectedDepartmentId.value || isResettingDb.value) return

  const ok = await confirm.confirm({
    title: t('settings.myDepartment.dbReset.confirmTitle'),
    message: t('settings.myDepartment.dbReset.confirmMessage'),
    confirmText: t('settings.myDepartment.dbReset.confirmAction'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return

  isResettingDb.value = true
  try {
    const result = await apiResetDepartmentDb(selectedDepartmentId.value)
    // Onboarding-LocalStorage für dieses Department löschen, damit Onboarding wieder erscheint
    const profileId = authStore.profileId
    const departmentId = selectedDepartmentId.value
    if (profileId && departmentId) {
      localStorage.removeItem(buildOnboardingDoneKey(profileId, departmentId))
      localStorage.removeItem(buildOnboardingPausedKey(profileId, departmentId))
      localStorage.removeItem(buildOnboardingStateKey(profileId, departmentId))
      sessionStorage.removeItem(`onboarding_prompted_${profileId}_${departmentId}`)
    }
    toast.success(result.message || t('settings.myDepartment.dbReset.toastSuccess'))
    // Zur Dashboard weiterleiten, damit Onboarding beim nächsten Laden erscheint
    window.location.href = `/${departmentId}`
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.myDepartment.dbReset.toastError'))
  } finally {
    isResettingDb.value = false
  }
}

async function loadInviteCode(deptId: string) {
  if (!canManageJoinCode.value) {
    inviteData.value = null
    inviteQrDataUrl.value = ''
    return
  }

  isInviteLoading.value = true
  try {
    inviteData.value = await getDepartmentInvite(deptId)
    const qrPayload =
      (inviteData.value.qr_payload || inviteData.value.invite_url || inviteData.value.register_qr_payload || '').trim()
    inviteQrDataUrl.value = await QRCode.toDataURL(qrPayload, {
      width: 180,
      margin: 1,
    })
    pendingInvites.value = await getPendingInvites(deptId)
  } catch (err) {
    console.error(t('settings.joinCode.toastRegenerateError'), err)
    inviteData.value = null
    inviteQrDataUrl.value = ''
    pendingInvites.value = []
  } finally {
    isInviteLoading.value = false
  }
}

async function regenerateInviteCode() {
  if (!selectedDepartmentId.value) return

  isInviteLoading.value = true
  try {
    inviteData.value = await regenerateDepartmentInvite(selectedDepartmentId.value)
    const qrPayload =
      (inviteData.value.qr_payload || inviteData.value.invite_url || inviteData.value.register_qr_payload || '').trim()
    inviteQrDataUrl.value = await QRCode.toDataURL(qrPayload, {
      width: 180,
      margin: 1,
    })
    toast.success(t('settings.joinCode.toastRegenerated'))
  } catch (err) {
    console.error(t('settings.joinCode.toastRegenerateError'), err)
    toast.error(t('settings.joinCode.toastRegenerateError'))
  } finally {
    isInviteLoading.value = false
  }
}

async function removePendingInviteItem(inviteId: string) {
  if (!selectedDepartmentId.value) return
  const ok = await confirm.confirm({
    title: t('settings.joinCode.confirmDeleteTitle'),
    message: t('settings.joinCode.confirmDeleteMessage'),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return

  try {
    await deletePendingInvite(selectedDepartmentId.value, inviteId)
    pendingInvites.value = pendingInvites.value.filter((entry) => entry.id !== inviteId)
    toast.success(t('settings.joinCode.toastPendingDeleted'))
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('settings.joinCode.toastPendingDeleteError'))
  }
}

async function copyJoinCode() {
  if (!inviteData.value) return
  await navigator.clipboard.writeText(inviteData.value.join_code)
  toast.success(t('settings.joinCode.toastCopiedCode'))
}

async function copyInviteLink() {
  if (!inviteData.value) return
  await navigator.clipboard.writeText(inviteData.value.invite_url)
  toast.success(t('settings.joinCode.toastCopiedInviteLink'))
}

async function copyRegisterInviteLink() {
  const url = inviteData.value?.register_invite_url?.trim()
  if (!url) return
  await navigator.clipboard.writeText(url)
  toast.success(t('settings.joinCode.toastCopiedRegisterLink'))
}

// === Adressen-Counts für Statistiken ===

async function loadAddresses(deptId: string) {
  try {
    const result = await getAddresses(deptId)
    addresses.value = result.addresses
  } catch (err: unknown) {
    console.error(err)
    addresses.value = []
  }
}

async function loadGroupsCount(deptId: string) {
  try {
    const groups = await getGroups(deptId)
    groupsPanelCount.value = groups.length
  } catch {
    groupsPanelCount.value = 0
  }
}

// Lade Department bei Änderung des aktiven Departments (z.B. über Navigation)
watch(() => authStore.activeDepartmentId, (newId) => {
  if (newId && newId !== selectedDepartmentId.value) {
    selectedDepartmentId.value = newId
    loadDepartment(newId)
  }
})

onMounted(() => {
  // Setze initiales Department auf das aktive Department
  selectedDepartmentId.value = authStore.activeDepartmentId || 
    (userDepartments.value[0]?.department_id ?? null)
  
  if (selectedDepartmentId.value) {
    loadDepartment(selectedDepartmentId.value)
  }

  nextTick(() => {
    accordionScrollEnabled.value = true
  })
})
</script>

<style scoped>
.my-dept-settings-error {
  margin-top: 8px;
}

.my-department-settings {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.header-section h1 {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.dept-identity {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 4px;
}

.dept-identity__name {
  margin: 0 0 4px;
  font-size: 22px;
  font-weight: 700;
  color: #111827;
  line-height: 1.25;
}

.dept-identity__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 6px 10px;
  margin: 0;
  font-size: 13px;
  line-height: 1.4;
}

.dept-identity__label {
  flex: 0 0 auto;
  color: #6b7280;
  font-weight: 500;
}

.dept-identity__label::after {
  content: ':';
}

.dept-identity__value {
  color: #374151;
  word-break: break-word;
}

.dept-identity__value.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 12px;
  color: #4b5563;
}

.join-qr {
  margin-top: 12px;
}

.join-qr img {
  width: 180px;
  height: 180px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: white;
}

.description {
  color: #6b7280;
  font-size: 14px;
  margin: 0;
}

/* Department Selector */
.department-selector {
  background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
  border: 1px solid #bfdbfe;
  border-radius: 12px;
  padding: 20px;
}

.selector-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #1e40af;
  margin-bottom: 8px;
}

.select-wrapper {
  position: relative;
  display: inline-block;
  width: 100%;
  max-width: 400px;
}

.department-select {
  width: 100%;
  padding: 12px 40px 12px 16px;
  font-size: 15px;
  font-weight: 500;
  color: #1f2937;
  background: white;
  border: 2px solid #3b82f6;
  border-radius: 8px;
  cursor: pointer;
  appearance: none;
  transition: all 0.2s;
}

.department-select:hover {
  border-color: #2563eb;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
}

.department-select:focus {
  outline: none;
  border-color: #1d4ed8;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
}

.select-icon {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: #3b82f6;
  pointer-events: none;
}

.dept-select-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.set-primary-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
}

.set-primary-btn:hover:not(:disabled) {
  background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
  box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
}

.set-primary-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.current-primary-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fcd34d;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
}

.save-success-msg {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 10px;
  padding: 8px 14px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  color: #166534;
  font-size: 13px;
  font-weight: 500;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

.selector-hint {
  margin: 12px 0 0 0;
  font-size: 13px;
  color: #3b82f6;
  font-weight: 500;
}

/* Loading/error base uses shared ui/states.css */

.error-message {
  color: #dc2626;
  margin-bottom: 16px;
}

/* Retry button uses shared ui/states.css (.retry-button) */

.department-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.info-card {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
}

.join-share-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 14px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
}

.join-share-main {
  flex: 1;
  min-width: 0;
}

.join-code-row {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.join-code {
  display: inline-block;
  padding: 8px 12px;
  border-radius: 8px;
  background: #111827;
  color: #f9fafb;
  font-weight: 700;
  letter-spacing: 1px;
  font-size: 1rem;
}

.join-meta {
  margin: 8px 0 0;
  color: #6b7280;
  font-size: 0.85rem;
  word-break: break-all;
}

.join-regenerate-row {
  margin-top: 14px;
  padding-top: 10px;
  border-top: 1px dashed #e5e7eb;
  display: flex;
  justify-content: flex-start;
}

.join-actions-row {
  margin-top: 14px;
  padding-top: 10px;
  border-top: 1px dashed #e5e7eb;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.pending-invites-block {
  margin-top: 10px;
  padding-top: 8px;
  border-top: 1px dashed #d1d5db;
}

.pending-invite-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 6px 0;
  font-size: 13px;
  color: #374151;
}

.join-qr img {
  width: 120px;
  height: 120px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  padding: 6px;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e5e7eb;
}

.card-icon {
  flex-shrink: 0;
}

.card-header h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #1f2937;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.public-visibility-toggles .public-toggle-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 14px;
  color: #374151;
  margin-bottom: 10px;
  cursor: pointer;
}

.public-visibility-toggles .public-toggle-row input {
  margin-top: 3px;
  flex-shrink: 0;
}

.info-label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.info-value {
  font-size: 15px;
  color: #1f2937;
}

.info-value.mono {
  font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
  font-size: 13px;
  color: #374151;
}

.role-badge {
  display: inline-block;
  padding: 4px 12px;
  background: #dbeafe;
  color: #1d4ed8;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
}

.users-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.user-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: white;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.user-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.user-name {
  font-weight: 500;
  color: #1f2937;
  font-size: 14px;
}

.user-email {
  font-size: 12px;
  color: #6b7280;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-role-badge {
  padding: 4px 10px;
  background: #e0e7ff;
  color: #4338ca;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
  flex-shrink: 0;
}

.empty-users {
  color: #6b7280;
  font-style: italic;
  text-align: center;
  padding: 20px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 16px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 16px;
  background: white;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #3b82f6;
}

.stat-label {
  font-size: 12px;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Empty state base uses shared ui/states.css */

/* === Storage Locations === */
.storage-section .card-header {
  flex-wrap: wrap;
}

.add-storage-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  margin-left: auto;
  transition: background 0.2s;
}

.add-storage-btn:hover {
  background: #2563eb;
}

.loading-storage {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 40px;
  color: #6b7280;
}

.storage-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.storage-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  transition: all 0.2s;
}

.storage-item:hover {
  border-color: #d1d5db;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.storage-item.is-primary {
  border-color: #3b82f6;
  background: #f0f7ff;
}

.storage-item.is-inactive {
  opacity: 0.6;
}

.storage-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #e5e7eb;
  border-radius: 8px;
  color: #6b7280;
  flex-shrink: 0;
}

.storage-item.is-primary .storage-icon {
  background: #dbeafe;
  color: #3b82f6;
}

.storage-info {
  flex: 1;
  min-width: 0;
}

.storage-name-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.storage-name {
  font-weight: 600;
  color: #1f2937;
  font-size: 15px;
}

.primary-badge {
  padding: 2px 8px;
  background: #3b82f6;
  color: white;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
}

.inactive-badge {
  padding: 2px 8px;
  background: #fecaca;
  color: #dc2626;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
}

.storage-type {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

.storage-address {
  display: block;
  font-size: 13px;
  color: #4b5563;
  margin-top: 6px;
}

.storage-actions {
  display: flex;
  gap: 4px;
  flex-shrink: 0;
}

.action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn:hover {
  background: #f3f4f6;
  color: #1f2937;
}

.action-btn.delete:hover {
  background: #fef2f2;
  color: #dc2626;
}

.empty-storage {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 40px 20px;
  text-align: center;
  color: #9ca3af;
}

.empty-storage p {
  margin: 0;
}

.add-first-btn {
  padding: 10px 20px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.add-first-btn:hover {
  background: #2563eb;
}

.storage-map-section {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #e5e7eb;
}

.storage-map-section h3 {
  margin: 0 0 16px 0;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

/* === Billing Address === */
.billing-address {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.address-card {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  gap: 16px;
}

.address-content {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.address-content strong {
  font-size: 15px;
  color: #1f2937;
}

.address-content span {
  font-size: 14px;
  color: #6b7280;
}

.address-actions {
  display: flex;
  gap: 4px;
  flex-shrink: 0;
}

.empty-billing {
  color: #6b7280;
  font-style: italic;
  font-size: 14px;
}

/* DB Reset Card */
.db-reset-card {
  border-color: #fecaca;
  background: #fef2f2;
}

.card-icon-danger {
  color: #dc2626;
}

.db-reset-row {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.db-reset-desc {
  margin: 0;
  font-size: 14px;
  color: #374151;
  line-height: 1.5;
}

.db-reset-btn {
  padding: 10px 20px;
  background: #dc2626;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  align-self: flex-start;
  transition: background 0.2s;
}

.db-reset-btn:hover:not(:disabled) {
  background: #b91c1c;
}

.db-reset-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.db-reset-warning {
  color: #b91c1c !important;
  margin-top: 12px;
}

.address-page-links {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 12px;
}

.address-page-link {
  display: inline-flex;
  align-items: center;
  padding: 10px 14px;
  border-radius: 8px;
  text-decoration: none;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
  background: #eff6ff;
  font-weight: 600;
}

.address-page-link:hover {
  background: #dbeafe;
}

.dept-accordion {
  padding: 0;
  overflow: hidden;
  scroll-margin-top: 24px;
}

.dept-accordion__summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  cursor: pointer;
  list-style: none;
  user-select: none;
}

.dept-accordion__summary::-webkit-details-marker {
  display: none;
}

.dept-accordion__title {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-size: 1rem;
  font-weight: 650;
  color: #0f172a;
}

.dept-accordion__chevron {
  flex-shrink: 0;
  color: #64748b;
  transition: transform 0.15s ease;
}

.dept-accordion[open] .dept-accordion__chevron {
  transform: rotate(180deg);
}

.dept-accordion__body {
  padding: 0 16px 16px;
  border-top: 1px solid #e5e7eb;
}

.dept-accordion__body .selector-hint {
  margin-top: 12px;
}

.dept-accordion__body .info-grid,
.dept-accordion__body .users-list,
.dept-accordion__body .stats-grid,
.dept-accordion__body .db-reset-row,
.dept-accordion__body .users-settings,
.dept-accordion__body .groups-settings {
  margin-top: 12px;
}
</style>
