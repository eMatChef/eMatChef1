<template>
  <div class="organisations-settings">
    <div class="header-section">
      <div>
        <h1>{{ t('settings.organisations.title') }}</h1>
        <p class="description">{{ t('settings.organisations.description') }}</p>
      </div>
      <EButton
        v-if="canManageOrganisations"
        variant="primary"
        :title="t('settings.organisations.addTitle')"
        @click="openAddModal"
      >
        <v-icon icon="mdi-plus" start size="20" />
        {{ t('common.add') }}
      </EButton>
    </div>

    <ELoadingState
      v-if="isLoading"
      variant="list"
      :rows="4"
      :message="t('settings.organisations.loading')"
    />

    <div v-else-if="error" class="error-block">
      <v-alert type="error" variant="tonal" :text="error" />
      <EButton variant="secondary" class="mt-3" @click="loadOrganisations">{{ t('common.retry') }}</EButton>
    </div>

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
          <EButton
            variant="text"
            size="small"
            :title="t('common.edit')"
            @click="editOrganisation(org)"
          >
            <v-icon icon="mdi-pencil-outline" size="18" />
          </EButton>
        </div>
      </div>
    </div>

    <EEmptyState
      v-else
      variant="create"
      :title="t('settings.organisations.empty')"
    />

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
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton } from '@/components/form/base'

const authStore = useAuthStore()
const { t } = useI18n()
const isLoading = ref(false)
const error = ref<string | null>(null)
const organisationsRaw = ref<Organisation[]>([])

const isModalOpen = ref(false)
const editingOrganisation = ref<Organisation | null>(null)

const canManageOrganisations = computed(() =>
  authStore.canAdmin('organisations.create') || authStore.canAdmin('organisations.edit')
)

const isSuperAdmin = computed(() =>
  (authStore.userRoles || []).includes('ROLE_SUPERADMIN')
)

const memberOrganisationIds = computed(() =>
  memberOrganisationIdsFromUserDepartments(authStore.departments)
)

const displayOrganisations = computed(() =>
  prepareOrganisationsForOrgSubAdminList(organisationsRaw.value, {
    isSuperAdmin: isSuperAdmin.value,
    memberOrganisationIds: memberOrganisationIds.value
  })
)

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
  await loadOrganisations()
}

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

.error-block {
  padding: 8px 0;
}

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
</style>
