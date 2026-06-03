<template>
  <EDialog
    :model-value="isOpen"
    :max-width="760"
    :title="t('components.departmentOnboarding.title')"
    scrollable
    persistent
    card-class="onboarding-wizard-card"
    @update:model-value="onWizardDialogUpdate"
  >
        <section class="wizard-progress">
          <div class="progress-track">
            <div class="progress-fill" :style="{ width: `${(currentStep / ONBOARDING_TOTAL_STEPS) * 100}%` }"></div>
          </div>
          <p class="muted">
            {{ t('components.departmentOnboarding.stepOf', { current: currentStep, total: ONBOARDING_TOTAL_STEPS }) }}
          </p>
        </section>

        <section class="wizard-body">
          <div v-if="globalError" class="error-box">{{ globalError }}</div>

          <div v-if="currentStep === 1" class="step-content">
            <h3>{{ t('components.departmentOnboarding.step1Title') }}</h3>
            <p>
              {{ t('components.departmentOnboarding.step1Intro', { general: t('settings.addressForm.types.general'), billing: t('settings.addressForm.types.billing') }) }}
            </p>
            <p class="status" :class="{ done: onboardingState.completed.departmentAddress }">
              {{
                onboardingState.completed.departmentAddress
                  ? t('components.departmentOnboarding.statusPresent')
                  : t('components.departmentOnboarding.statusOpen')
              }}
            </p>
            <p v-if="onboardingState.completed.departmentAddress" class="muted status-hint">
              {{ t('components.departmentOnboarding.step1StatusHint') }}
            </p>
            <div class="step-actions">
              <EButton variant="primary" @click="openAddressModal('general')">
                {{ t('components.departmentOnboarding.btnDoNow') }}
              </EButton>
              <EButton variant="secondary" @click="skipStep">{{ t('components.departmentOnboarding.btnSkip') }}</EButton>
              <EButton variant="text" @click="handleLater">{{ t('components.departmentOnboarding.btnLater') }}</EButton>
            </div>
          </div>

          <div v-else-if="currentStep === 2" class="step-content">
            <div class="step-title-row">
              <h3>{{ t('components.departmentOnboarding.step2Title') }}</h3>
              <EButton variant="text" size="small" @click="showSettingsHelp = true">
                {{ t('components.departmentOnboarding.step2Info') }}
              </EButton>
            </div>
            <p>{{ t('components.departmentOnboarding.step2Intro') }}</p>
            <div class="settings-grid" @focusin="showSettingsHelp = false">
              <ETextField v-model="settingsForm.timezone" :label="t('components.departmentOnboarding.labelTimezone')" hide-details="auto" />
              <ETextField v-model="settingsForm.dateFormat" :label="t('components.departmentOnboarding.labelDateFormat')" hide-details="auto" />
              <ETextField v-model="settingsForm.timeFormat" :label="t('components.departmentOnboarding.labelTimeFormat')" hide-details="auto" />
              <ETextField v-model="settingsForm.defaultTimeStart" type="time" :label="t('components.departmentOnboarding.labelDefaultStart')" hide-details="auto" />
              <ETextField v-model="settingsForm.defaultTimeEnd" type="time" :label="t('components.departmentOnboarding.labelDefaultEnd')" hide-details="auto" />
              <ETextField v-model.number="settingsForm.materialLeadMinutes" type="number" :label="t('components.departmentOnboarding.labelMaterialLead')" hide-details="auto" />
              <ETextField v-model.number="settingsForm.materialLagMinutes" type="number" :label="t('components.departmentOnboarding.labelMaterialLag')" hide-details="auto" />
              <ETextField v-model.number="settingsForm.campMaterialLeadDays" type="number" :label="t('components.departmentOnboarding.labelCampLead')" hide-details="auto" />
              <ETextField v-model.number="settingsForm.campMaterialLagDays" type="number" :label="t('components.departmentOnboarding.labelCampLag')" hide-details="auto" />
            </div>
            <p class="status" :class="{ done: onboardingState.completed.settingsInitialized }">
              {{
                onboardingState.completed.settingsInitialized
                  ? t('components.departmentOnboarding.statusDone')
                  : t('components.departmentOnboarding.statusOpen')
              }}
            </p>
            <div class="step-actions">
              <EButton variant="primary" :loading="isSavingSettings" :disabled="isSavingSettings" @click="saveSettings">
                {{ t('components.departmentOnboarding.saveSettings') }}
              </EButton>
              <EButton variant="secondary" @click="skipStep">{{ t('components.departmentOnboarding.btnSkip') }}</EButton>
              <EButton variant="text" @click="handleLater">{{ t('components.departmentOnboarding.btnLater') }}</EButton>
            </div>
          </div>

          <div v-else-if="currentStep === 4" class="step-content">
            <h3>{{ t('components.departmentOnboarding.step4Title') }}</h3>
            <p>{{ t('components.departmentOnboarding.step4Intro') }}</p>
            <p v-if="inviteStepError" class="error-inline">{{ inviteStepError }}</p>

            <div v-if="isLoadingInviteStep" class="muted">{{ t('components.departmentOnboarding.loadInvite') }}</div>
            <div v-else class="invite-step-grid">
              <div class="invite-block">
                <h4>{{ t('components.departmentOnboarding.currentMembers', { count: members.length }) }}</h4>
                <ul v-if="members.length > 0" class="simple-list">
                  <li v-for="member in members" :key="member.user_id">
                    {{ member.name }} ({{ member.email }})
                  </li>
                </ul>
                <p v-else class="muted">{{ t('components.departmentOnboarding.noMembers') }}</p>

                <div class="invited-section">
                  <h4>{{ t('components.departmentOnboarding.invitedMembers', { count: pendingInvites.length }) }}</h4>
                  <ul v-if="pendingInvites.length > 0" class="simple-list">
                    <li v-for="invite in pendingInvites" :key="invite.id" class="pending-item">
                      <span>{{ invite.email }} ({{ invite.role }})</span>
                      <EButton variant="secondary" @click="removePendingInvite(invite.id)">
                        {{ t('common.delete') }}
                      </EButton>
                    </li>
                  </ul>
                  <p v-else class="muted">{{ t('components.departmentOnboarding.noOpenInvites') }}</p>
                </div>
              </div>

              <div class="invite-block">
                <h4>{{ t('components.departmentOnboarding.addUsersBlock') }}</h4>
                <input
                  v-model="userSearchQuery"
                  type="text"
                  class="user-search-input"
                  :placeholder="t('components.departmentOnboarding.userSearchPh')"
                  @input="onUserSearchInput"
                />
                <p v-if="isSearchingUsers" class="muted">{{ t('components.departmentOnboarding.searchRunning') }}</p>
                <p v-else-if="userSearchQuery.trim().length < 2" class="muted">{{ t('components.departmentOnboarding.typeTwoChars') }}</p>
                <ul v-else-if="availableUsers.length > 0" class="simple-list">
                  <li v-for="user in availableUsers" :key="user.id">
                    {{ user.name }} ({{ user.email }})
                  </li>
                </ul>
                <p v-else class="muted">{{ t('components.departmentOnboarding.noMatchingUsers') }}</p>

                <div v-if="userSearchQuery.trim().length >= 2 && availableUsers.length === 0" class="invite-manual-box">
                  <p class="muted">
                    {{ t('components.departmentOnboarding.manualInviteHint') }}
                  </p>
                  <input
                    v-model="inviteEmail"
                    type="email"
                    class="user-search-input"
                    :placeholder="t('components.departmentOnboarding.emailForInvite')"
                  />
                  <select v-model="inviteRole" class="role-select">
                    <option value="u">{{ t('settings.adminUsers.roles.u') }}</option>
                    <option value="l1">{{ t('settings.adminUsers.roles.l1') }}</option>
                    <option value="l2">{{ t('settings.adminUsers.roles.l2') }}</option>
                    <option value="l3">{{ t('settings.adminUsers.roles.l3') }}</option>
                    <option value="dc">{{ t('settings.adminUsers.roles.dc') }}</option>
                    <option value="mw">{{ t('settings.adminUsers.roles.mw') }}</option>
                  </select>
                  <div class="step-actions">
                    <EButton variant="secondary" @click="copyPersonalInviteLink">
                      {{ t('components.departmentOnboarding.copyPersonalLink') }}
                    </EButton>
                    <EButton variant="primary" @click="sendPersonalInvite">
                      {{ t('components.departmentOnboarding.sendInvite') }}
                    </EButton>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="inviteData" class="invite-code-box">
              <div class="invite-code-main">
                <span>{{ t('components.departmentOnboarding.joinCode', { code: inviteData.join_code }) }}</span>
                <div class="step-actions">
                  <EButton variant="secondary" @click="copyInviteCode">
                    {{ t('components.departmentOnboarding.copyCode') }}
                  </EButton>
                  <EButton variant="secondary" @click="copyInviteLink">
                    {{ t('components.departmentOnboarding.copyLink') }}
                  </EButton>
                </div>
              </div>
              <div class="invite-code-qr" v-if="inviteQrDataUrl">
                <img :src="inviteQrDataUrl" :alt="t('components.departmentOnboarding.joinQrAlt')" />
                <p class="muted">{{ t('components.departmentOnboarding.qrGlobalHint') }}</p>
              </div>
            </div>

            <div class="step-actions">
              <EButton variant="primary" @click="goToAndComplete(`/${departmentId}/settings/users`, 'inviteUsers')">
                {{ t('components.departmentOnboarding.btnDoNow') }}
              </EButton>
              <EButton variant="secondary" @click="skipStep">{{ t('components.departmentOnboarding.btnSkip') }}</EButton>
              <EButton variant="text" @click="handleLater">{{ t('components.departmentOnboarding.btnLater') }}</EButton>
            </div>
          </div>

          <div v-else-if="currentStep === 3" class="step-content">
            <h3>{{ t('components.departmentOnboarding.step3Title') }}</h3>
            <p>{{ t('components.departmentOnboarding.step3Intro') }}</p>
            <p v-if="groupCountError" class="error-inline">{{ groupCountError }}</p>
            <p v-else-if="isLoadingGroupCount" class="muted">{{ t('components.departmentOnboarding.loadGroups') }}</p>
            <p v-else class="status" :class="{ done: groupCount > 0 }">
              {{ t('components.departmentOnboarding.groupCount', { count: groupCount }) }}
            </p>
            <div class="step-actions">
              <EButton variant="primary" @click="goToAndComplete(`/${departmentId}/settings/groups`, 'createGroup')">
                {{ t('components.departmentOnboarding.btnDoNow') }}
              </EButton>
              <EButton variant="secondary" @click="skipStep">{{ t('components.departmentOnboarding.btnSkip') }}</EButton>
              <EButton variant="text" @click="handleLater">{{ t('components.departmentOnboarding.btnLater') }}</EButton>
            </div>
          </div>

          <div v-else-if="currentStep === 5" class="step-content">
            <h3>{{ t('components.departmentOnboarding.step5Title') }}</h3>
            <p>{{ t('components.departmentOnboarding.step5Intro') }}</p>
            <div class="step-actions">
              <EButton variant="primary" @click="goToAndComplete(`/${departmentId}/settings/groups`, 'assignRoles')">
                {{ t('components.departmentOnboarding.btnDoNow') }}
              </EButton>
              <EButton variant="secondary" @click="skipStep">{{ t('components.departmentOnboarding.btnSkip') }}</EButton>
              <EButton variant="text" @click="handleLater">{{ t('components.departmentOnboarding.btnLater') }}</EButton>
            </div>
          </div>

          <div v-else-if="currentStep === 6" class="step-content">
            <h3>{{ t('components.departmentOnboarding.step6Title') }}</h3>
            <p>{{ t('components.departmentOnboarding.step6Intro') }}</p>
            <p v-if="categoriesError" class="error-inline">{{ categoriesError }}</p>
            <p v-else-if="isLoadingCategories" class="muted">{{ t('components.departmentOnboarding.loadCategories') }}</p>
            <p v-else class="status" :class="{ done: categoryCount > 0 }">
              {{ t('components.departmentOnboarding.categoryCount', { count: categoryCount }) }}
            </p>

            <div class="category-preview-table">
              <table>
                <tbody>
                  <template v-for="item in STANDARD_CATEGORY_TREE" :key="item.name">
                    <tr class="cat-main">
                      <td class="cat-checkbox">
                        <input type="checkbox" v-model="categorySelection.main[item.name]" :id="`cat-${item.name}`" />
                      </td>
                      <td class="cat-name">
                        <label :for="`cat-${item.name}`">{{ item.name }}</label>
                      </td>
                    </tr>
                    <tr v-for="child in (item.children || [])" :key="child" class="cat-sub">
                      <td class="cat-checkbox">
                        <input type="checkbox" v-model="categorySelection.sub[item.name][child]" :id="`cat-${item.name}-${child}`" />
                      </td>
                      <td class="cat-name cat-indent">
                        <label :for="`cat-${item.name}-${child}`">{{ child }}</label>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
            <div class="step-actions">
              <EButton variant="primary" :disabled="!hasSelectedCategories || isApplyingCategoryTemplates" @click="applyCategoryTemplates">
                {{
                  isApplyingCategoryTemplates
                    ? t('components.departmentOnboarding.applyingTemplates')
                    : t('components.departmentOnboarding.applyTemplates')
                }}
              </EButton>
              <EButton variant="secondary" @click="goToAndComplete(`/${departmentId}/settings/categories`, 'categoriesConfigured')">
                {{ t('components.departmentOnboarding.openCategories') }}
              </EButton>
              <EButton variant="secondary" @click="skipStep">{{ t('components.departmentOnboarding.btnSkip') }}</EButton>
              <EButton variant="text" @click="handleLater">{{ t('components.departmentOnboarding.btnLater') }}</EButton>
            </div>
          </div>

          <div v-else-if="currentStep === 7" class="step-content">
            <h3>{{ t('components.departmentOnboarding.step7Title') }}</h3>
            <p>
              {{
                t('components.departmentOnboarding.step7Body', {
                  storageAddr: t('components.departmentOnboarding.wmsStorageAddr'),
                  storageType: t('components.departmentOnboarding.wmsStorageType'),
                  defaultName: t('components.departmentOnboarding.wmsDefaultStorageName')
                })
              }}
            </p>
            <p class="status" :class="{ done: onboardingState.completed.storageAddress }">
              {{
                onboardingState.completed.storageAddress
                  ? t('components.departmentOnboarding.statusPresent')
                  : t('components.departmentOnboarding.statusOpen')
              }}
            </p>
            <p v-if="onboardingState.completed.storageAddress" class="muted status-hint">
              {{ t('components.departmentOnboarding.step7StatusHint') }}
            </p>
            <div class="step-actions">
              <EButton variant="primary" @click="openAddressModal('storage')">
                {{ t('components.departmentOnboarding.addStorageAddress') }}
              </EButton>
              <EButton variant="secondary" @click="goToSettings(`/${departmentId}/settings/my-department`)">
                {{ t('components.departmentOnboarding.openSettings') }}
              </EButton>
              <EButton variant="secondary" @click="skipStep">{{ t('components.departmentOnboarding.btnSkip') }}</EButton>
              <EButton variant="text" @click="handleLater">{{ t('components.departmentOnboarding.btnLater') }}</EButton>
            </div>
          </div>

          <div v-else-if="currentStep === 8" class="step-content">
            <h3>{{ t('components.departmentOnboarding.step8Title') }}</h3>
            <p>{{ t('components.departmentOnboarding.step8Intro') }}</p>
            <p v-if="isLoadingStorageCount" class="muted">{{ t('components.departmentOnboarding.loadRacks') }}</p>
            <p v-else class="status" :class="{ done: storageRackCount >= 1 }">
              {{ t('components.departmentOnboarding.rackSlotCount', { racks: storageRackCount, slots: storageSlotCount }) }}
            </p>
            <div class="step-actions">
              <EButton variant="primary" @click="goToAndComplete(`/${departmentId}/settings/storage`, 'storageConfigured')">
                {{ t('components.departmentOnboarding.manageRacks') }}
              </EButton>
              <EButton variant="secondary" @click="skipStep">{{ t('components.departmentOnboarding.btnSkip') }}</EButton>
              <EButton variant="text" @click="handleLater">{{ t('components.departmentOnboarding.btnLater') }}</EButton>
            </div>
          </div>

          <div v-else-if="currentStep === 9" class="step-content">
            <h3>{{ t('components.departmentOnboarding.step9Title') }}</h3>
            <p>{{ t('components.departmentOnboarding.step9Intro') }}</p>
            <p v-if="materialCountError" class="error-inline">{{ materialCountError }}</p>
            <p v-else-if="isLoadingMaterialCount" class="muted">{{ t('components.departmentOnboarding.loadMaterials') }}</p>
            <p v-else class="status" :class="{ done: materialCount >= 1 }">
              {{ t('components.departmentOnboarding.materialProgress', { current: materialCount, need: 1 }) }}
            </p>
            <p class="muted">{{ t('components.departmentOnboarding.step9Note1') }}</p>
            <p class="muted">
              {{ t('components.departmentOnboarding.step9Note2', { example: t('components.departmentOnboarding.exampleRack') }) }}
            </p>
            <div class="step-actions">
              <EButton variant="primary" @click="goToAndComplete(`/${departmentId}/materials`, 'materialCaptured')">
                {{ t('components.departmentOnboarding.btnDoNow') }}
              </EButton>
              <EButton variant="secondary" @click="skipStep">{{ t('components.departmentOnboarding.btnSkip') }}</EButton>
              <EButton variant="text" @click="handleLater">{{ t('components.departmentOnboarding.btnLater') }}</EButton>
            </div>
          </div>

          <div v-else-if="currentStep === 10" class="step-content">
            <h3>{{ t('components.departmentOnboarding.step10Title') }}</h3>
            <p>{{ t('components.departmentOnboarding.step10Intro') }}</p>
            <div class="step-actions">
              <EButton variant="primary" @click="goToAndComplete(`/${departmentId}/activities`, 'miniIssueReturn')">
                {{ t('components.departmentOnboarding.btnDoNow') }}
              </EButton>
              <EButton variant="secondary" @click="skipStep">{{ t('components.departmentOnboarding.btnSkip') }}</EButton>
              <EButton variant="text" @click="finishWizard">{{ t('components.departmentOnboarding.btnLater') }}</EButton>
            </div>
          </div>
        </section>

        <footer class="wizard-footer">
          <EButton variant="secondary" :disabled="currentStep <= 1" @click="goBack">
            {{ t('components.departmentOnboarding.back') }}
          </EButton>
          <EButton variant="primary" :disabled="currentStep >= ONBOARDING_TOTAL_STEPS" @click="goNext">
            {{ t('components.departmentOnboarding.next') }}
          </EButton>
          <EButton variant="primary" @click="currentStep >= ONBOARDING_TOTAL_STEPS ? completeWizard() : finishWizard()">
            {{ currentStep >= ONBOARDING_TOTAL_STEPS ? t('components.departmentOnboarding.finish') : t('common.close') }}
          </EButton>
        </footer>
  </EDialog>

  <EDialog v-model="showSettingsHelp" :max-width="480" :title="t('components.departmentOnboarding.helpTitle')">
    <p>{{ t('components.departmentOnboarding.helpLine1') }}</p>
    <p>{{ t('components.departmentOnboarding.helpLine2') }}</p>
    <p>{{ t('components.departmentOnboarding.helpLine3') }}</p>
    <p>{{ t('components.departmentOnboarding.helpLine4') }}</p>
    <p>{{ t('components.departmentOnboarding.helpLine5') }}</p>
    <p>{{ t('components.departmentOnboarding.helpLine6') }}</p>
    <p class="info-note">{{ t('components.departmentOnboarding.helpNote') }}</p>
    <template #actions>
      <EButton variant="secondary" size="small" @click="showSettingsHelp = false">{{ t('common.close') }}</EButton>
    </template>
  </EDialog>

  <AddressModal
    v-if="isAddressModalOpen"
    :department-id="departmentId"
    :default-type="addressModalType"
    :default-name="addressModalDepartmentPrefill"
    @saved="handleAddressSaved"
    @close="isAddressModalOpen = false"
  />
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AddressModal from '@/components/AddressModal.vue'
import { EButton, EDialog, ETextField } from '@/components/form/base'
import { getAddresses } from '@/api/addresses'
import { createCategory, getCategories, type Category } from '@/api/categories'
import { getMaterials } from '@/api/materials'
import { getStorageOverview } from '@/api/storageLocations'
import {
  createPendingInvite,
  deletePendingInvite,
  getDepartmentInvite,
  getPendingInvites,
  type DepartmentInviteData,
  type PendingInvite
} from '@/api/joinRequests'
import { getAvailableUsersForDepartment, getDepartmentMembers, type AvailableUser, type DepartmentMember } from '@/api/departments'
import { getDepartmentSettings, updateDepartmentSettings } from '@/api/departmentSettings'
import { getGroups } from '@/api/groups'
import { useToast } from '@/composables/useToast'
import QRCode from 'qrcode'
import {
  ONBOARDING_TOTAL_STEPS,
  createDefaultOnboardingState,
  readOnboardingState,
  writeOnboardingState,
  type DepartmentOnboardingState,
} from '@/utils/departmentOnboarding'

type OptionalStepKey = 'inviteUsers' | 'createGroup' | 'assignRoles' | 'categoriesConfigured' | 'storageConfigured' | 'materialCaptured' | 'miniIssueReturn'

const props = defineProps<{
  isOpen: boolean
  departmentId: string
  profileId: string
}>()

const emit = defineEmits<{
  close: []
  complete: []
}>()

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()
const onboardingState = ref<DepartmentOnboardingState>(createDefaultOnboardingState())
const currentStep = ref(1)
const globalError = ref('')
const isAddressModalOpen = ref(false)
const addressModalType = ref<'general' | 'storage'>('general')

/** Departmentsname für Vorschlag bei „Allgemein“-Adresse (Bezeichnung + Firma/Organisation) */
const departmentNameForAddressPrefill = computed(() => {
  const id = props.departmentId
  if (!id) return ''
  const row = authStore.departments.find((d) => d.department_id === id)
  const fromMembership = (row?.department?.name || '').trim()
  if (fromMembership) return fromMembership
  if (authStore.activeDepartmentId === id) {
    return (authStore.activeDepartmentName || '').trim()
  }
  return ''
})

const addressModalDepartmentPrefill = computed(() => {
  if (addressModalType.value === 'general') return departmentNameForAddressPrefill.value
  if (addressModalType.value === 'storage') return t('components.departmentOnboarding.storageDefaultName')
  return ''
})
const isSavingSettings = ref(false)
const showSettingsHelp = ref(false)
const members = ref<DepartmentMember[]>([])
const availableUsers = ref<AvailableUser[]>([])
const userSearchQuery = ref('')
const isSearchingUsers = ref(false)
const inviteEmail = ref('')
const inviteRole = ref<'u' | 'l1' | 'l2' | 'l3' | 'dc' | 'mw'>('u')
const inviteData = ref<DepartmentInviteData | null>(null)
const inviteQrDataUrl = ref('')
const pendingInvites = ref<PendingInvite[]>([])
const isLoadingInviteStep = ref(false)
const inviteStepError = ref('')
const isLoadingCategories = ref(false)
const isApplyingCategoryTemplates = ref(false)
const categoriesError = ref('')
const categoryCount = ref(0)
const groupCount = ref(0)
const isLoadingGroupCount = ref(false)
const groupCountError = ref('')
const storageRackCount = ref(0)
const storageSlotCount = ref(0)
const isLoadingStorageCount = ref(false)
const materialCount = ref(0)
const isLoadingMaterialCount = ref(false)
const materialCountError = ref('')
let userSearchTimer: ReturnType<typeof setTimeout> | null = null

// Standard-Kategorien: Hauptkategorien + Unterkategorien wie vom Nutzer vorgegeben
const STANDARD_CATEGORY_TREE: Array<{ name: string; children?: string[] }> = [
  { name: 'Pionier', children: ['Werkzeug', 'Seil', 'Kisten', 'Zeltbau', 'Diverses'] },
  { name: 'Küche', children: ['Ausrüstung', 'Essen', 'Kochbuch', 'Abwasch', 'Diverses'] },
  { name: 'Verkleidung' },
  { name: 'Zelt' },
  { name: 'Bastelmat' },
  { name: 'Spiele' },
  { name: 'Verpackung' },
  { name: 'Diverses' },
  { name: 'Werbematerial' },
]

// Auswahl pro Kategorie (Haupt + Unter)
function initCategorySelection() {
  const main: Record<string, boolean> = {}
  const sub: Record<string, Record<string, boolean>> = {}
  for (const item of STANDARD_CATEGORY_TREE) {
    main[item.name] = true
    if (item.children?.length) {
      sub[item.name] = {}
      for (const c of item.children) {
        sub[item.name][c] = true
      }
    }
  }
  return { main, sub }
}
const categorySelection = reactive(initCategorySelection())

const personalizedInviteLink = computed(() => {
  if (!inviteData.value) return ''
  const url = new URL(inviteData.value.invite_url, window.location.origin)
  if (inviteEmail.value.trim()) {
    url.searchParams.set('invite_email', inviteEmail.value.trim())
  } else {
    url.searchParams.delete('invite_email')
  }
  url.searchParams.set('invite_role', inviteRole.value)
  url.searchParams.set('auto_join', '1')
  return url.toString()
})

const settingsForm = reactive({
  timezone: 'Europe/Zurich',
  dateFormat: 'dd.MM.yyyy',
  timeFormat: 'HH:mm',
  defaultTimeStart: '14:00',
  defaultTimeEnd: '17:00',
  materialLeadMinutes: 60,
  materialLagMinutes: 60,
  campMaterialLeadDays: 1,
  campMaterialLagDays: 1,
})

const hasSelectedCategories = computed(() => {
  for (const item of STANDARD_CATEGORY_TREE) {
    if (categorySelection.main[item.name]) return true
    const subs = categorySelection.sub[item.name]
    if (subs && Object.values(subs).some(Boolean)) return true
  }
  return false
})

function persistState() {
  onboardingState.value.currentStep = currentStep.value
  writeOnboardingState(props.profileId, props.departmentId, onboardingState.value)
}

function goNext() {
  currentStep.value = Math.min(currentStep.value + 1, ONBOARDING_TOTAL_STEPS)
  persistState()
}

function goBack() {
  currentStep.value = Math.max(currentStep.value - 1, 1)
  persistState()
}

function skipStep() {
  goNext()
}

function onWizardDialogUpdate(open: boolean) {
  if (!open) handleLater()
}

function handleLater() {
  persistState()
  emit('close')
}

function finishWizard() {
  persistState()
  emit('close')
}

function completeWizard() {
  onboardingState.value.currentStep = ONBOARDING_TOTAL_STEPS
  persistState()
  emit('complete')
}

function openAddressModal(type: 'general' | 'storage') {
  addressModalType.value = type
  isAddressModalOpen.value = true
}

async function handleAddressSaved() {
  isAddressModalOpen.value = false
  await refreshAddressStatus()
  persistState()
  goNext()
}

async function refreshAddressStatus() {
  try {
    const response = await getAddresses(props.departmentId)
    const addresses = response.addresses || []
    onboardingState.value.completed.departmentAddress = addresses.some((a) => a.type === 'general' || a.type === 'billing')
    onboardingState.value.completed.storageAddress = addresses.some((a) => a.type === 'storage')
  } catch (err: any) {
    globalError.value = err.response?.data?.error || t('components.departmentOnboarding.errLoadAddresses')
  }
}

function applySettingsFromRaw(raw: Record<string, string>) {
  settingsForm.timezone = raw['general.timezone'] || 'Europe/Zurich'
  settingsForm.dateFormat = raw['general.date_format'] || 'dd.MM.yyyy'
  settingsForm.timeFormat = raw['general.time_format'] || 'HH:mm'
  settingsForm.defaultTimeStart = raw['activity.default_time_start'] || '14:00'
  settingsForm.defaultTimeEnd = raw['activity.default_time_end'] || '17:00'
  settingsForm.materialLeadMinutes = Number(raw['activity.material_lead_minutes'] || 60)
  settingsForm.materialLagMinutes = Number(raw['activity.material_lag_minutes'] || 60)
  settingsForm.campMaterialLeadDays = Number(raw['activity.camp_material_lead_days'] || 1)
  settingsForm.campMaterialLagDays = Number(raw['activity.camp_material_lag_days'] || 1)
}

function isSettingsInitialized(raw: Record<string, string>): boolean {
  // Nur als erledigt werten, wenn der Nutzer explizit im Onboarding gespeichert hat.
  // Das Backend liefert Defaults für fehlende Keys – daher darf nicht auf das Vorhandensein
  // der Keys geprüft werden, sonst wäre Schritt 3 fälschlich "erledigt" bei neuen Departments.
  return String(raw['onboarding.phase1_settings_done'] || '0') === '1'
}

async function refreshSettingsStatus() {
  try {
    const raw = await getDepartmentSettings(props.departmentId)
    applySettingsFromRaw(raw)
    onboardingState.value.completed.settingsInitialized = isSettingsInitialized(raw)
  } catch (err: any) {
    globalError.value = err.response?.data?.error || t('components.departmentOnboarding.errLoadSettings')
  }
}

async function saveSettings() {
  globalError.value = ''
  isSavingSettings.value = true
  try {
    await updateDepartmentSettings(props.departmentId, {
      'general.timezone': settingsForm.timezone,
      'general.date_format': settingsForm.dateFormat,
      'general.time_format': settingsForm.timeFormat,
      'activity.default_time_start': settingsForm.defaultTimeStart,
      'activity.default_time_end': settingsForm.defaultTimeEnd,
      'activity.material_lead_minutes': String(settingsForm.materialLeadMinutes),
      'activity.material_lag_minutes': String(settingsForm.materialLagMinutes),
      'activity.camp_material_lead_days': String(settingsForm.campMaterialLeadDays),
      'activity.camp_material_lag_days': String(settingsForm.campMaterialLagDays),
      'onboarding.phase1_settings_done': '1',
    })
    onboardingState.value.completed.settingsInitialized = true
    toast.success(t('components.departmentOnboarding.toastSettingsSaved'))
    persistState()
    goNext()
  } catch (err: any) {
    const msg = err.response?.data?.error || t('components.departmentOnboarding.errSaveSettings')
    globalError.value = msg
    toast.error(msg)
  } finally {
    isSavingSettings.value = false
  }
}

async function goToAndComplete(path: string, key: OptionalStepKey) {
  onboardingState.value.completed[key] = true
  persistState()
  await router.push(path)
  emit('close')
}

async function goToSettings(path: string) {
  persistState()
  await router.push(path)
  emit('close')
}

async function initializeWizardState() {
  onboardingState.value = readOnboardingState(props.profileId, props.departmentId)
  currentStep.value = onboardingState.value.currentStep || 1
  globalError.value = ''
  await Promise.all([refreshAddressStatus(), refreshSettingsStatus()])

  if (onboardingState.value.completed.departmentAddress && onboardingState.value.completed.storageAddress && onboardingState.value.completed.settingsInitialized) {
    currentStep.value = Math.max(currentStep.value, 4)
  }

  persistState()
}

async function loadInviteStepData() {
  isLoadingInviteStep.value = true
  inviteStepError.value = ''
  try {
    const [membersData, invite, pending] = await Promise.all([
      getDepartmentMembers(props.departmentId),
      getDepartmentInvite(props.departmentId),
      getPendingInvites(props.departmentId),
    ])
    members.value = membersData
    availableUsers.value = []
    userSearchQuery.value = ''
    inviteEmail.value = ''
    inviteRole.value = 'u'
    inviteData.value = invite
    pendingInvites.value = pending
    const qrPayload =
      (invite.register_qr_payload || invite.register_invite_url || invite.qr_payload || '').trim() ||
      invite.invite_url
    inviteQrDataUrl.value = await QRCode.toDataURL(qrPayload, {
      width: 140,
      margin: 1,
    })
  } catch (err: any) {
    inviteStepError.value = err.response?.data?.error || t('components.departmentOnboarding.errLoadInvite')
  } finally {
    isLoadingInviteStep.value = false
  }
}

function normalizeKey(value: string): string {
  return value.trim().toLowerCase()
}

function findCategoryByName(categories: Category[], name: string, parentId: string | null): Category | undefined {
  const targetName = normalizeKey(name)
  return categories.find((category) => normalizeKey(category.name) === targetName && (category.parent_id || null) === parentId)
}

async function loadGroupCount() {
  isLoadingGroupCount.value = true
  groupCountError.value = ''
  try {
    const groups = await getGroups(props.departmentId)
    groupCount.value = groups.length
    onboardingState.value.completed.createGroup = groupCount.value > 0
    persistState()
  } catch (err: any) {
    groupCount.value = 0
    groupCountError.value = err.response?.data?.error || t('components.departmentOnboarding.errLoadGroups')
  } finally {
    isLoadingGroupCount.value = false
  }
}

async function loadCategoryStatus() {
  isLoadingCategories.value = true
  categoriesError.value = ''
  try {
    const categories = await getCategories(props.departmentId)
    categoryCount.value = categories.length
    onboardingState.value.completed.categoriesConfigured = categories.length > 0
    persistState()
  } catch (err: any) {
    categoryCount.value = 0
    categoriesError.value = err.response?.data?.error || t('components.departmentOnboarding.errLoadCat')
  } finally {
    isLoadingCategories.value = false
  }
}

async function applyCategoryTemplates() {
  if (!hasSelectedCategories.value) {
    toast.error(t('components.departmentOnboarding.toastSelectCategory'))
    return
  }
  isApplyingCategoryTemplates.value = true
  categoriesError.value = ''
  try {
    const categories = await getCategories(props.departmentId)
    let createdCount = 0

    for (const item of STANDARD_CATEGORY_TREE) {
      const mainSelected = categorySelection.main[item.name]
      const subs = categorySelection.sub[item.name]
      const anySubSelected = subs && Object.values(subs).some(Boolean)
      if (!mainSelected && !anySubSelected) continue

      let mainCategory = findCategoryByName(categories, item.name, null)
      if (!mainCategory && (mainSelected || anySubSelected)) {
        mainCategory = await createCategory({
          department_id: props.departmentId,
          name: item.name,
          parent_id: null,
        })
        createdCount += 1
        categories.push(mainCategory)
      }

      if (!mainCategory) continue

      const childNames = item.children || []
      for (const childName of childNames) {
        if (!subs?.[childName]) continue
        const childExists = findCategoryByName(categories, childName, mainCategory!.id)
        if (childExists) continue

        const createdChild = await createCategory({
          department_id: props.departmentId,
          name: childName,
          parent_id: mainCategory!.id,
        })
        createdCount += 1
        categories.push(createdChild)
      }
    }

    await loadCategoryStatus()
    onboardingState.value.completed.categoriesConfigured = true
    persistState()
    toast.success(
      createdCount > 0
        ? t('components.departmentOnboarding.toastTemplatesNew', { count: createdCount })
        : t('components.departmentOnboarding.toastTemplatesNoNew')
    )
  } catch (err: any) {
    categoriesError.value = err.response?.data?.error || t('components.departmentOnboarding.errCreateTemplates')
    toast.error(categoriesError.value)
  } finally {
    isApplyingCategoryTemplates.value = false
  }
}

async function loadMaterialCount() {
  isLoadingMaterialCount.value = true
  materialCountError.value = ''
  try {
    const materials = await getMaterials(props.departmentId, {
      material_source: 'all',
      include_global_js: false,
    })
    materialCount.value = materials.length
    if (materialCount.value >= 1) {
      onboardingState.value.completed.materialCaptured = true
      persistState()
    }
  } catch (err: any) {
    materialCount.value = 0
    materialCountError.value = err.response?.data?.error || t('components.departmentOnboarding.errLoadMaterial')
  } finally {
    isLoadingMaterialCount.value = false
  }
}

function onUserSearchInput() {
  if (userSearchTimer) clearTimeout(userSearchTimer)
  const query = userSearchQuery.value.trim()
  inviteEmail.value = query

  if (query.length < 2) {
    availableUsers.value = []
    isSearchingUsers.value = false
    return
  }

  isSearchingUsers.value = true
  userSearchTimer = setTimeout(async () => {
    try {
      const results = await getAvailableUsersForDepartment(props.departmentId, query)
      const memberIds = new Set(members.value.map((m) => m.user_id))
      availableUsers.value = results.filter((u) => !memberIds.has(u.id)).slice(0, 10)
    } catch {
      availableUsers.value = []
    } finally {
      isSearchingUsers.value = false
    }
  }, 250)
}

async function copyPersonalInviteLink() {
  if (!personalizedInviteLink.value) return
  try {
    await navigator.clipboard.writeText(personalizedInviteLink.value)
    toast.success(t('components.departmentOnboarding.toastPersonalLink'))
  } catch {
    toast.error(t('components.departmentOnboarding.errCopyPersonal'))
  }
}

async function sendPersonalInvite() {
  const email = inviteEmail.value.trim()
  if (!email || !email.includes('@')) {
    toast.error(t('components.departmentOnboarding.errEmail'))
    return
  }
  try {
    const created = await createPendingInvite({
      departmentId: props.departmentId,
      email,
      role: inviteRole.value,
    })
    pendingInvites.value = [created, ...pendingInvites.value]
    userSearchQuery.value = ''
    inviteEmail.value = ''
    availableUsers.value = []
    isSearchingUsers.value = false
    await navigator.clipboard.writeText(created.invite_url)
    toast.success(t('components.departmentOnboarding.toastInviteCreated'))
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('components.departmentOnboarding.errCreateInvite'))
  }
}

async function removePendingInvite(inviteId: string) {
  try {
    await deletePendingInvite(props.departmentId, inviteId)
    pendingInvites.value = pendingInvites.value.filter((entry) => entry.id !== inviteId)
    toast.success(t('components.departmentOnboarding.toastPendingDeleted'))
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('components.departmentOnboarding.errDeletePending'))
  }
}

async function copyInviteCode() {
  if (!inviteData.value) return
  try {
    await navigator.clipboard.writeText(inviteData.value.join_code)
    toast.success(t('components.departmentOnboarding.toastCodeCopied'))
  } catch {
    toast.error(t('components.departmentOnboarding.errCodeCopy'))
  }
}

async function copyInviteLink() {
  if (!inviteData.value) return
  try {
    await navigator.clipboard.writeText(inviteData.value.invite_url)
    toast.success(t('components.departmentOnboarding.toastUrlCopied'))
  } catch {
    toast.error(t('components.departmentOnboarding.errUrlCopy'))
  }
}

watch(
  () => props.isOpen,
  async (open) => {
    if (!open) return
    await initializeWizardState()
  }
)

watch(
  [() => props.isOpen, currentStep],
  async ([open, step]) => {
    if (!open || step !== 3) return
    await loadGroupCount()
  },
  { immediate: true }
)

watch(
  [() => props.isOpen, currentStep],
  async ([open, step]) => {
    if (!open || step !== 4) return
    await loadInviteStepData()
  },
  { immediate: true }
)

watch(
  [() => props.isOpen, currentStep],
  async ([open, step]) => {
    if (!open || step !== 6) return
    await loadCategoryStatus()
  },
  { immediate: true }
)

async function loadStorageCount() {
  isLoadingStorageCount.value = true
  try {
    const overview = await getStorageOverview(props.departmentId)
    storageRackCount.value = overview.racks?.length ?? 0
    storageSlotCount.value = overview.racks?.reduce((sum, r) => sum + (r.slots?.length ?? 0), 0) ?? 0
    if (storageRackCount.value >= 1) {
      onboardingState.value.completed.storageConfigured = true
      persistState()
    }
  } catch {
    storageRackCount.value = 0
    storageSlotCount.value = 0
  } finally {
    isLoadingStorageCount.value = false
  }
}

watch(
  [() => props.isOpen, currentStep],
  async ([open, step]) => {
    if (!open || step !== 8) return
    await loadStorageCount()
  },
  { immediate: true }
)

watch(
  [() => props.isOpen, currentStep],
  async ([open, step]) => {
    if (!open || step !== 9) return
    await loadMaterialCount()
  },
  { immediate: true }
)

</script>

<style scoped>
:deep(.onboarding-wizard-card) {
  max-height: 92vh;
}

.wizard-header,
.wizard-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.wizard-footer {
  border-top: 1px solid #e5e7eb;
  border-bottom: none;
  gap: 10px;
}

.wizard-header h2 {
  margin: 0;
  font-size: 1.2rem;
}

.close-btn {
  border: none;
  background: transparent;
  font-size: 18px;
  cursor: pointer;
  color: #6b7280;
}

.wizard-progress {
  padding: 12px 20px;
  border-bottom: 1px solid #eef2f7;
}

.progress-track {
  width: 100%;
  height: 8px;
  background: #e5e7eb;
  border-radius: 999px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #16a34a, #22c55e);
}

.wizard-body {
  padding: 20px;
  overflow-y: auto;
}

.step-content h3 {
  margin: 0 0 8px 0;
}

.step-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.info-btn {
  border: 1px solid #93c5fd;
  background: #eff6ff;
  color: #1d4ed8;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  padding: 5px 10px;
  cursor: pointer;
}

.step-content p {
  margin: 0 0 12px 0;
}

.settings-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px 12px;
  margin-bottom: 12px;
}

.settings-grid label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 13px;
  color: #334155;
}

.settings-grid input {
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 14px;
}

.step-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

/* Buttons use shared ui/buttons.css */

.muted {
  margin: 4px 0 0 0;
  font-size: 13px;
  color: #6b7280;
}

.status {
  font-weight: 600;
  color: #b45309;
}

.status.done {
  color: #15803d;
}

.status-hint {
  margin-top: 6px;
  line-height: 1.45;
}

.error-box {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
  border-radius: 8px;
  padding: 10px 12px;
  margin-bottom: 12px;
}

.error-inline {
  color: #b91c1c;
  font-size: 13px;
}

.invite-step-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 12px;
}

.invite-block {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px;
  background: #fafafa;
}

.invite-block h4 {
  margin: 0 0 8px 0;
  font-size: 14px;
}

.simple-list {
  margin: 0;
  padding-left: 18px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 13px;
}

.pending-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.category-preview-table {
  margin: 12px 0 16px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  background: #fafafa;
}

.category-preview-table table {
  width: 100%;
  border-collapse: collapse;
}

.category-preview-table td {
  padding: 8px 14px;
  font-size: 14px;
  border-bottom: 1px solid #f3f4f6;
}

.category-preview-table .cat-checkbox {
  width: 36px;
  padding-right: 0;
  vertical-align: middle;
}

.category-preview-table .cat-checkbox input {
  width: 16px;
  height: 16px;
  cursor: pointer;
}

.category-preview-table .cat-name label {
  cursor: pointer;
}

.category-preview-table tr:last-child td {
  border-bottom: none;
}

.category-preview-table .cat-main td {
  font-weight: 600;
  color: #111827;
  background: #fff;
}

.category-preview-table .cat-sub td {
  color: #4b5563;
}

.category-preview-table .cat-indent {
  padding-left: 32px;
}

.invited-section {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px dashed #d1d5db;
}

.invite-code-box {
  border: 1px solid #dbeafe;
  background: #eff6ff;
  border-radius: 8px;
  padding: 10px;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.invite-code-main {
  min-width: 0;
}

.invite-code-qr img {
  width: 96px;
  height: 96px;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  background: #fff;
  padding: 4px;
}

.user-search-input {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 14px;
  margin-bottom: 8px;
}

.invite-manual-box {
  margin-top: 10px;
  border-top: 1px dashed #cbd5e1;
  padding-top: 10px;
}

.role-select {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 14px;
  margin-bottom: 8px;
  background: white;
}

.info-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2300;
  padding: 16px;
}

.info-modal {
  width: min(560px, 96vw);
  background: white;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.24);
  overflow: hidden;
}

.info-modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  border-bottom: 1px solid #e5e7eb;
}

.info-modal-header h4 {
  margin: 0;
  font-size: 16px;
}

.info-close {
  border: none;
  background: transparent;
  color: #6b7280;
  cursor: pointer;
  font-size: 16px;
}

.info-modal-body {
  padding: 14px 16px;
}

.info-modal-body p {
  margin: 0 0 10px 0;
  color: #1f2937;
  font-size: 14px;
  line-height: 1.45;
}

.info-note {
  margin-top: 12px;
  padding: 10px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

@media (max-width: 700px) {
  .settings-grid {
    grid-template-columns: 1fr;
  }
  .invite-step-grid {
    grid-template-columns: 1fr;
  }
  .invite-code-box {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
