<template>
  <SidebarNavigation v-model="drawerOpen" />
  <TopHeader v-model:drawer-open="drawerOpen" />

  <v-main class="page-main">
    <div class="page-content">
      <router-view v-slot="{ Component }">
        <keep-alive :include="['MaterialsView', 'ActivitiesView']" :max="8">
          <component :is="Component" :key="route.path" />
        </keep-alive>
      </router-view>
    </div>
  </v-main>

  <button
      v-if="showResumeButton"
      class="onboarding-resume-btn"
      @click="isOnboardingOpen = true"
    >
      {{ t('layout.onboardingResume') }}
    </button>

    <DepartmentOnboardingWizard
      v-if="canUseOnboarding"
      :is-open="isOnboardingOpen"
      :department-id="departmentId"
      :profile-id="profileId"
      @close="isOnboardingOpen = false"
      @complete="handleOnboardingComplete"
    />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useUnsavedChangesReminder } from '@/composables/useUnsavedChangesReminder'
import DepartmentOnboardingWizard from '@/components/DepartmentOnboardingWizard.vue'
import { isOnboardingDismissed, isOnboardingDone, markOnboardingDone } from '@/utils/departmentOnboarding'
import { getDepartmentOnboardingStatus, markDepartmentOnboardingDone } from '@/api/departmentSettings'
import SidebarNavigation from './SidebarNavigation.vue'
import TopHeader from './TopHeader.vue'

const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()

useUnsavedChangesReminder()
const drawerOpen = ref(false)
const isOnboardingOpen = ref(false)
const backendOnboardingDone = ref<boolean | null>(null)

const departmentId = computed(() => {
  const value = route.params.departmentId
  return typeof value === 'string' ? value : ''
})

const profileId = computed(() => authStore.profileId || '')

function normalizeDeptRole(role: string): string {
  return String(role || '').toLowerCase().trim()
}

/** Depchef / Materialchef — Zielgruppe des Einrichtungs-Onboardings */
const hasOnboardingRole = computed(() => {
  const role = normalizeDeptRole(authStore.currentDepartmentRole)
  return ['dc', 'depchef', 'mw', 'matwart'].includes(role)
})

/** SA / Org / Sub und globaler Superadmin — kein persönliches Department-Onboarding */
const skipsPersonalDepartmentOnboarding = computed(() => {
  const role = normalizeDeptRole(authStore.currentDepartmentRole)
  if (
    ['sa', 'superadmin', 'org', 'organisationschef', 'sub', 'suborgchef'].includes(role)
  ) {
    return true
  }
  return authStore.userRoles.includes('ROLE_SUPERADMIN')
})

const canUseOnboarding = computed(() => {
  return (
    authStore.isLoggedIn &&
    !!departmentId.value &&
    !!profileId.value &&
    hasOnboardingRole.value &&
    !skipsPersonalDepartmentOnboarding.value
  )
})

const isOnboardingCompleted = computed(() => {
  if (!canUseOnboarding.value) return true
  if (backendOnboardingDone.value !== null) return backendOnboardingDone.value
  return isOnboardingDone(profileId.value, departmentId.value)
})

const isDismissed = computed(() => {
  if (!canUseOnboarding.value) return false
  return isOnboardingDismissed(profileId.value, departmentId.value)
})

const showResumeButton = computed(() => {
  return canUseOnboarding.value && !isOnboardingCompleted.value && !isDismissed.value && !isOnboardingOpen.value
})

async function handleOnboardingComplete() {
  if (!canUseOnboarding.value) return
  try {
    await markDepartmentOnboardingDone(departmentId.value)
    backendOnboardingDone.value = true
  } catch (err) {
    console.warn('Konnte Backend-Onboarding-Status nicht speichern, nutze lokalen Fallback.', err)
  }
  markOnboardingDone(profileId.value, departmentId.value)
  isOnboardingOpen.value = false
}

watch(
  [
    departmentId,
    profileId,
    () => authStore.isLoggedIn,
    () => authStore.currentDepartmentRole,
    () => authStore.userRoles.join(','),
  ],
  async ([depId, profId, loggedIn]) => {
    if (
      !loggedIn ||
      !depId ||
      !profId ||
      !hasOnboardingRole.value ||
      skipsPersonalDepartmentOnboarding.value
    ) {
      backendOnboardingDone.value = null
      isOnboardingOpen.value = false
      return
    }

    try {
      const status = await getDepartmentOnboardingStatus(depId)
      backendOnboardingDone.value = status.doneAll
    } catch (err) {
      console.warn('Konnte Backend-Onboarding-Status nicht laden, nutze lokalen Fallback.', err)
      backendOnboardingDone.value = null
    }

    const isDone = backendOnboardingDone.value ?? isOnboardingDone(profId, depId)
    const dismissed = isOnboardingDismissed(profId, depId)
    if (isDone) {
      isOnboardingOpen.value = false
      return
    }
    if (dismissed) {
      isOnboardingOpen.value = false
      return
    }
    isOnboardingOpen.value = true
  },
  { immediate: true }
)
</script>

<style scoped>
.page-main {
  flex: 1 1 auto !important;
  min-height: 0 !important;
  overflow-y: auto !important;
}

.page-content {
  padding: 24px;
  padding-bottom: calc(24px + var(--emc-safe-bottom));
}

.onboarding-resume-btn {
  position: fixed;
  right: calc(18px + var(--emc-safe-right));
  bottom: calc(18px + var(--emc-safe-bottom));
  z-index: 1100;
  border: none;
  border-radius: 999px;
  padding: 10px 14px;
  background: #0284c7;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 8px 24px rgba(2, 132, 199, 0.35);
}
</style>
