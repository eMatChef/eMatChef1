<template>
  <div class="organisations-settings">
    <div class="header-section">
      <div>
        <h1>{{ t('settings.organisations.title') }}</h1>
        <p class="description">{{ t('settings.organisations.description') }}</p>
      </div>
      <button 
        v-if="canManageOrganisations"
        @click="openAddModal" 
        class="add-button" 
        :title="t('settings.organisations.addTitle')"
      >
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
          <path
            d="M10 4V16M4 10H16"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
          />
        </svg>
        <span>{{ t('common.add') }}</span>
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="loading-state">
      <p>{{ t('settings.organisations.loading') }}</p>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="error-state">
      <p class="error-message">{{ error }}</p>
      <button @click="loadOrganisations" class="retry-button">{{ t('common.retry') }}</button>
    </div>

    <!-- Organisationen Liste -->
    <div v-else-if="displayOrganisations.length > 0" class="organisations-list">
      <div
        v-for="org in displayOrganisations"
        :key="org.id"
        class="organisation-item"
        :class="{ 'organisation-item--not-member': isSuperAdmin && !memberOrganisationIds.has(org.id) }"
      >
        <div class="organisation-info">
          <h3 class="organisation-name">{{ org.name }}</h3>
          <p class="organisation-id">ID: {{ org.id }}</p>
        </div>
        <div v-if="canManageOrganisations && (isSuperAdmin || memberOrganisationIds.has(org.id))" class="organisation-actions">
          <button @click="editOrganisation(org)" class="edit-button" :title="t('common.edit')">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <path
                d="M11.3333 2.00001C11.5084 1.8249 11.7163 1.68601 11.9444 1.59124C12.1726 1.49648 12.4163 1.44775 12.6625 1.44775C12.9087 1.44775 13.1524 1.49648 13.3806 1.59124C13.6087 1.68601 13.8166 1.8249 13.9917 2.00001C14.1668 2.17512 14.3057 2.38301 14.4005 2.61118C14.4952 2.83935 14.544 3.08306 14.544 3.32918C14.544 3.5753 14.4952 3.81901 14.4005 4.04718C14.3057 4.27535 14.1668 4.48324 13.9917 4.65835L5.32499 13.325L2 14L2.67499 10.675L11.3333 2.00001Z"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <p>{{ t('settings.organisations.empty') }}</p>
    </div>

    <!-- Organisation Modal -->
    <OrganisationModal
      :is-open="isModalOpen"
      :organisation="editingOrganisation"
      @close="closeModal"
      @saved="handleOrganisationSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import OrganisationModal from '@/components/OrganisationModal.vue'
import { getOrganisations, type Organisation } from '@/api/organisations'
import { useAuthStore } from '@/stores/auth'
import { apiErrorMessage } from '@/utils/apiErrorMessage'
import {
  memberOrganisationIdsFromUserDepartments,
  prepareOrganisationsForOrgSubAdminList
} from '@/utils/organisationUserPicker'

const authStore = useAuthStore()
const { t } = useI18n()
const isLoading = ref(false)
const error = ref<string | null>(null)
const organisationsRaw = ref<Organisation[]>([])

// Modal State
const isModalOpen = ref(false)
const editingOrganisation = ref<Organisation | null>(null)

/**
 * Berechtigung: Nur SUPERADMIN, ORGANISATIONSCHEF oder SUBORGCHEF können Organisationen verwalten
 * Rollen kommen als Abkürzung vom Backend (sa, org, sub, etc.)
 */
const canManageOrganisations = computed(() =>
  authStore.canAdmin('organisations.create') || authStore.canAdmin('organisations.edit')
)

const isSuperAdmin = computed(() =>
  (authStore.userRoles || []).includes('ROLE_SUPERADMIN')
)

/** Organisationen, in denen der User mindestens ein Department hat (für Org-Chef-UI) */
const memberOrganisationIds = computed(() =>
  memberOrganisationIdsFromUserDepartments(authStore.departments)
)

/** Gefiltert/sortiert: SA alle (Mitglieds-Orgs zuerst), Org-/Suborg-Chef nur eigene Orgs */
const displayOrganisations = computed(() =>
  prepareOrganisationsForOrgSubAdminList(organisationsRaw.value, {
    isSuperAdmin: isSuperAdmin.value,
    memberOrganisationIds: memberOrganisationIds.value
  })
)

/**
 * Lädt Organisationen aus der API
 */
async function loadOrganisations() {
  try {
    isLoading.value = true
    error.value = null

    organisationsRaw.value = await getOrganisations()
  } catch (err: unknown) {
    error.value = apiErrorMessage(err, t('settings.organisations.loadError'))
  } finally {
    isLoading.value = false
  }
}

function openAddModal() {
  editingOrganisation.value = null
  isModalOpen.value = true
}

function editOrganisation(org: Organisation) {
  editingOrganisation.value = org
  isModalOpen.value = true
}

function closeModal() {
  isModalOpen.value = false
  editingOrganisation.value = null
}

async function handleOrganisationSaved() {
  // Organisationen neu laden
  await loadOrganisations()
}

// Beim Mounten Daten laden
onMounted(() => {
  loadOrganisations()
})
</script>

<style scoped>
.organisations-settings {
  padding: 0;
  max-width: 100%;
}

.header-section {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;
}

h1 {
  font-size: 24px;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 8px;
}

.description {
  color: #6b7280;
  font-size: 14px;
  margin-bottom: 0;
}

.add-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: #10b981;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.add-button:hover {
  background: #059669;
}

/* Loading/error/empty base uses shared ui/states.css */

.error-message {
  margin-bottom: 16px;
  font-weight: 500;
}

/* Retry button uses shared ui/states.css (.retry-button) */

.organisations-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.organisation-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  transition: box-shadow 0.2s;
}

.organisation-item:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.organisation-info {
  flex: 1;
}

.organisation-name {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 4px 0;
}

.organisation-id {
  font-size: 12px;
  color: #6b7280;
  margin: 0;
}

.organisation-actions {
  display: flex;
  gap: 8px;
}

.edit-button {
  background: none;
  border: none;
  cursor: pointer;
  padding: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  border-radius: 6px;
  transition: all 0.2s;
}

.edit-button:hover {
  background: #f3f4f6;
  color: #3b82f6;
}
</style>
