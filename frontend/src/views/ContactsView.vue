<template>
  <div class="contacts-view">
    <!-- Detail View (ersetzt Liste wenn Kontakt ausgewählt) -->
      <ContactDetailView
      v-if="showDetailView && selectedContactId"
      :contact-id="selectedContactId"
      :department-id="currentDepartmentId"
      @close="closeDetailView"
      @updated="handleContactUpdated"
      @deleted="handleContactDeleted"
    />

    <!-- Liste View -->
    <PageShell v-else class="contacts-view">
      <template #title>{{ t('contacts.title') }}</template>
      <template #subtitle>
        {{ isUserRole ? t('contacts.descriptionUser') : t('contacts.description') }}
      </template>
      <template v-if="canCreateContact" #actions>
        <EButton variant="primary" @click="openCreateModal">
          <v-icon icon="mdi-plus" start size="20" />
          {{ t('contacts.newAddress') }}
        </EButton>
      </template>

      <template #filters>
        <EFilterRow>
          <v-col class="e-filter-row__search">
            <ESearchField
              v-model="searchQuery"
              :label="t('contacts.searchPlaceholder')"
            />
          </v-col>
          <v-col cols="auto" class="e-filter-row__select">
            <ESelect
              v-model="selectedType"
              :items="typeSelectItems"
              :label="t('contacts.colType')"
              hide-details
            />
          </v-col>
          <v-col cols="auto" class="e-filter-row__select">
            <ESelect
              v-model="selectedCanton"
              :items="cantonSelectItems"
              :label="t('contacts.allCantons')"
              hide-details
            />
          </v-col>
          <v-col cols="auto" class="e-filter-row__actions d-flex align-center ga-2">
            <EButton
              variant="text"
              size="small"
              :style="{ visibility: hasActiveFilters ? 'visible' : 'hidden' }"
              :aria-hidden="!hasActiveFilters"
              @click="resetFilters"
            >
              {{ t('contacts.resetFilters') }}
            </EButton>
            <ECheckbox
              v-if="canManageDeletedContacts"
              v-model="showDeleted"
              class="e-filter-row__checkbox"
              density="compact"
              :label="t('contacts.showDeleted')"
              hide-details
            />
          </v-col>
        </EFilterRow>
      </template>

      <!-- Loading State -->
      <ELoadingState
        v-if="isLoading"
        variant="table"
        :rows="8"
        :message="t('contacts.loadingList')"
      />

      <div v-else-if="error" class="contacts-view-error">
        <v-alert type="error" variant="tonal" :text="error" />
        <EButton variant="secondary" class="mt-3" @click="loadContacts">{{ t('common.retry') }}</EButton>
      </div>

      <!-- Empty State -->
      <EEmptyState
        v-else-if="contacts.length === 0"
        variant="create"
        :title="t('contacts.emptyTitle')"
        :description="t('contacts.emptyText')"
      >
        <template v-if="canCreateContact" #actions>
          <EButton size="large" @click="openCreateModal">
            {{ t('contacts.emptyCta') }}
          </EButton>
        </template>
      </EEmptyState>

      <!-- No Results State -->
      <EEmptyState
        v-else-if="filteredContacts.length === 0"
        variant="search"
        :title="t('contacts.noResultsTitle')"
        :description="t('contacts.noResultsText')"
      >
        <template #actions>
          <EButton variant="secondary" @click="resetFilters">
            {{ t('contacts.resetFilters') }}
          </EButton>
        </template>
      </EEmptyState>

      <div v-else class="contacts-table-wrapper">
        <EResponsiveDataList>
          <template #table>
            <ContactListDataTable
              :items="filteredContacts"
              :can-manage-deleted-contacts="canManageDeletedContacts"
              :type-label="addressTypeLabel"
              @open="openContactDetail"
              @restore="restoreContact"
              @permanent-delete="confirmPermanentDelete"
            />
          </template>
          <template #mobile>
            <ContactListMobile
              :items="filteredContacts"
              :type-label="addressTypeLabel"
              @open="openContactDetail"
            />
          </template>
        </EResponsiveDataList>
        <p class="table-hint">{{ t('contacts.tableHint') }}</p>
      </div>

      <!-- Address Modal (Create only) -->
      <AddressModal
        v-if="showModal"
        :department-id="currentDepartmentId"
        :address="null"
        :default-type="createDefaultType"
        :allowed-types="createAllowedTypes"
        @close="closeModal"
        @saved="handleSaved"
      />
    </PageShell>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  getAddresses,
  restoreAddress,
  permanentDeleteAddress,
  ADDRESS_TYPES,
  SWISS_CANTONS,
  type Address,
} from '@/api/addresses'
import { useToast } from '@/composables/useToast'
import PageShell from '@/components/layout/PageShell.vue'
import EFilterRow from '@/components/layout/EFilterRow.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EResponsiveDataList from '@/components/layout/EResponsiveDataList.vue'
import { ESearchField, ESelect, ECheckbox, EButton } from '@/components/form/base'
import AddressModal from '@/components/AddressModal.vue'
import ContactDetailView from '@/components/contacts/ContactDetailView.vue'
import ContactListDataTable from '@/components/contacts/ContactListDataTable.vue'
import ContactListMobile from '@/components/contacts/ContactListMobile.vue'
import {
  useDepartmentMemberRole,
  USER_CONTACT_VIEW_TYPES,
  USER_CONTACT_CREATE_TYPES,
} from '@/composables/useDepartmentMemberRole'
import '@/styles/contacts-view.css'

const route = useRoute()
const router = useRouter()
const { t, te } = useI18n()
const toast = useToast()
const { isUserRole, canManageContacts, canManageDeletedContacts } = useDepartmentMemberRole()
const currentDepartmentId = computed(() => route.params.departmentId as string)

const canCreateContact = computed(() => canManageContacts.value || isUserRole.value)

const createDefaultType = computed(() => (isUserRole.value ? 'meeting' : 'customer'))

const createAllowedTypes = computed(() =>
  isUserRole.value ? [...USER_CONTACT_CREATE_TYPES] : null
)

const visibleAddressTypeKeys = computed(() => {
  const all = Object.keys(ADDRESS_TYPES) as (keyof typeof ADDRESS_TYPES)[]
  if (!isUserRole.value) return all
  return all.filter((key) =>
    USER_CONTACT_VIEW_TYPES.includes(key as (typeof USER_CONTACT_VIEW_TYPES)[number])
  )
})

function addressTypeLabel(type: string): string {
  const path = `settings.addressForm.types.${type}` as const
  return te(path) ? t(path) : type
}

const typeSelectItems = computed(() => [
  { title: t('contacts.allTypes'), value: '' },
  ...visibleAddressTypeKeys.value.map((key) => ({
    title: addressTypeLabel(String(key)),
    value: key,
  })),
])

const cantonSelectItems = computed(() => [
  { title: t('contacts.allCantons'), value: '' },
  ...Object.entries(SWISS_CANTONS).map(([code, name]) => ({
    title: `${code} - ${name}`,
    value: code,
  })),
])

// State
const contacts = ref<Address[]>([])
const isLoading = ref(false)
const error = ref<string | null>(null)

// Filter State
const searchQuery = ref('')
const selectedType = ref('')
const selectedCanton = ref('')
const showDeleted = ref(false)

// Modal State
const showModal = ref(false)

// Detail View State (gesteuert über Route-Parameter)
const selectedContactId = computed(() => route.params.contactId as string | undefined || null)
const showDetailView = computed(() => !!selectedContactId.value)

// Computed: Filtered Contacts
const filteredContacts = computed(() => {
  let result = [...contacts.value]
  
  // Textsuche
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(c => 
      (c.name && c.name.toLowerCase().includes(query)) ||
      (c.company && c.company.toLowerCase().includes(query)) ||
      (c.email && c.email.toLowerCase().includes(query)) ||
      (c.phone && c.phone.includes(query)) ||
      (c.mobile && c.mobile.includes(query)) ||
      (c.city && c.city.toLowerCase().includes(query)) ||
      (c.street && c.street.toLowerCase().includes(query)) ||
      (c.postal_code && c.postal_code.includes(query))
    )
  }
  
  // Typ-Filter
  if (selectedType.value) {
    result = result.filter(c => c.type === selectedType.value)
  }
  
  // Kanton-Filter
  if (selectedCanton.value) {
    result = result.filter(c => c.canton === selectedCanton.value)
  }
  
  return result
})

const hasActiveFilters = computed(() => {
  return searchQuery.value || selectedType.value || selectedCanton.value
})

// Methods
async function loadContacts() {
  if (!currentDepartmentId.value) return
  
  isLoading.value = true
  error.value = null
  
  try {
    const data = await getAddresses(currentDepartmentId.value, {
      includeDeleted: showDeleted.value && canManageDeletedContacts.value,
    })
    contacts.value = data.addresses
  } catch (err: any) {
    error.value = err.response?.data?.error || t('contacts.loadError')
  } finally {
    isLoading.value = false
  }
}

function resetFilters() {
  searchQuery.value = ''
  selectedType.value = ''
  selectedCanton.value = ''
}

// Detail View
function openContactDetail(contact: Address) {
  router.push(`/${currentDepartmentId.value}/contacts/${contact.id}`)
}

function closeDetailView() {
  router.push(`/${currentDepartmentId.value}/contacts`)
}

async function handleContactUpdated() {
  await loadContacts()
}

async function handleContactDeleted() {
  closeDetailView()
  await loadContacts()
}

// Modal (nur für Neu-Erstellung)
function openCreateModal() {
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSaved() {
  closeModal()
  await loadContacts()
}

async function restoreContact(contact: Address) {
  try {
    await restoreAddress(contact.id)
    toast.success(t('contacts.restoreSuccess'))
    await loadContacts()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('contacts.restoreError'))
  }
}

async function confirmPermanentDelete(contact: Address) {
  const name = contact.name || contact.company || t('contacts.unnamed')
  if (!window.confirm(t('contacts.permanentDeleteMessage', { name }))) return
  try {
    await permanentDeleteAddress(contact.id)
    await loadContacts()
  } catch (err: any) {
    toast.error(err.response?.data?.error || t('contacts.permanentDeleteError'))
  }
}

// Watchers
watch(showDeleted, () => {
  loadContacts()
})

watch(currentDepartmentId, () => {
  if (selectedContactId.value) {
    router.replace(`/${currentDepartmentId.value}/contacts`)
  }
  loadContacts()
})

// Lifecycle
onMounted(() => {
  loadContacts()
})
</script>

<style scoped>
.contacts-view-error {
  padding: 24px;
  text-align: center;
}
</style>
