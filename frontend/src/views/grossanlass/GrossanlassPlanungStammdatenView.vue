<template>
  <div class="ga-live-page">
    <p class="intro">{{ t('grossanlass.planung.stammdaten.intro') }}</p>
    <ELoadingState v-if="loading" variant="list" :message="t('common.loading')" />
    <p v-else-if="error" class="warn">{{ error }}</p>
    <template v-else>
      <div class="form">
        <ETextField
          v-model="deptNameDraft"
          :label="t('grossanlass.planung.stammdaten.name')"
          :disabled="!canManage"
          hide-details
        />
        <EDateRangeField
          v-model:start="periodStart"
          v-model:end="periodEnd"
          class="mt-3"
          :label="t('grossanlass.planung.stammdaten.period')"
          :department-id="departmentId"
          :disabled="!canManage"
          :allow-past="true"
          :block-closed-dates="false"
          :show-presets="false"
          :show-markers="true"
        />
        <div class="mt-3 venue-wrap">
          <label class="venue-label" for="ga-venue-address-search">{{ t('grossanlass.planung.stammdaten.location') }}</label>
          <p class="hint">{{ t('grossanlass.planung.stammdaten.locationHint') }}</p>
          <DepartmentAddressAutocomplete
            v-if="canManage"
            ref="venueAddressAutocompleteRef"
            input-id="ga-venue-address-search"
            :addresses="rentalAddresses"
            :selected-id="venueAddressId"
            primary-type="event"
            :placeholder="t('grossanlass.planung.stammdaten.locationPlaceholder')"
            :add-button-title="t('activities.wizard.form.addVenueAddressTitle')"
            :edit-button-title="t('activities.wizard.form.editVenueAddressTitle')"
            :empty-addresses-label="t('activities.wizard.form.noAddressesWithAdd')"
            inline-create-label-key="addresses.search.createEventVenueInline"
            show-edit-button
            @update:selected-id="onVenueSelected"
            @create="openAddVenueAddressModal"
            @edit="openEditVenueAddressModal"
          />
          <p v-else class="venue-readonly">{{ venueAddressSummary }}</p>
          <p v-if="canManage && venueAddressId" class="selected-address">
            {{ t('activities.wizard.form.selectedPrefix') }}{{ venueAddressSummary }}
            <button type="button" class="clear-selection" :title="t('activities.wizard.form.clearSelectionTitle')" @click="clearVenueAddress">
              ×
            </button>
          </p>
          <ActivityVenueOverviewBlock
            v-if="venueAddressId"
            :venue-address-id="venueAddressId"
            :department-id="departmentId"
            :read-only="!canManage"
            @updated="loadRentalAddresses"
          />
        </div>
        <section class="card guest-card">
          <h3>{{ t('grossanlass.planung.stammdaten.guestTitle') }}</h3>
          <p class="hint">{{ t('grossanlass.planung.stammdaten.guestLead') }}</p>
          <div class="modus-grid">
            <button
              type="button"
              class="modus-card"
              :class="{ 'is-active': guestType === 'camp' }"
              :disabled="!canManage || saving"
              @click="guestType = 'camp'"
            >
              <strong>{{ t('grossanlass.planung.activities.guestCamp') }}</strong>
              <span>{{ t('grossanlass.planung.activities.guestCampHelp') }}</span>
            </button>
            <button
              type="button"
              class="modus-card"
              :class="{ 'is-active': guestType === 'event' }"
              :disabled="!canManage || saving"
              @click="guestType = 'event'"
            >
              <strong>{{ t('grossanlass.planung.activities.guestEvent') }}</strong>
              <span>{{ t('grossanlass.planung.activities.guestEventHelp') }}</span>
            </button>
          </div>
          <div class="modus-grid mt-3">
            <button
              type="button"
              class="modus-card"
              :class="{ 'is-active': hasGuestDepartments }"
              :disabled="!canManage || saving"
              @click="hasGuestDepartments = true"
            >
              <strong>{{ t('grossanlass.planung.stammdaten.hasGuests') }}</strong>
              <span>{{ t('grossanlass.planung.stammdaten.hasGuestsHelp') }}</span>
            </button>
            <button
              type="button"
              class="modus-card"
              :class="{ 'is-active': !hasGuestDepartments }"
              :disabled="!canManage || saving"
              @click="hasGuestDepartments = false"
            >
              <strong>{{ t('grossanlass.planung.stammdaten.hasGuestsNo') }}</strong>
            </button>
          </div>
        </section>
        <ETextarea
          v-model="notes"
          class="mt-3"
          :label="t('grossanlass.planung.stammdaten.notes')"
          :placeholder="t('grossanlass.planung.stammdaten.notesPlaceholder')"
          :disabled="!canManage"
          rows="3"
          hide-details="auto"
        />
        <div v-if="canManage" class="actions">
          <EButton variant="primary" size="small" :loading="saving" @click="save">
            {{ t('common.save') }}
          </EButton>
        </div>
      </div>

      <section class="panel">
        <GrossanlassKeyDatesPanel :department-id="departmentId" />
      </section>
    </template>

    <v-dialog
      v-model="showVenueContactModal"
      class="contact-create-dialog"
      max-width="960"
      scrollable
      content-class="contact-create-dialog__content"
      :z-index="2400"
    >
      <v-card class="contact-create-dialog__card" rounded="lg">
        <v-card-text class="contact-create-dialog__body">
          <ContactDetailView
            v-if="showVenueContactModal"
            :key="venueContactModalKey"
            :mode="venueContactModalMode"
            as-modal
            :department-id="departmentId"
            :contact-id="venueContactModalId"
            default-type="event"
            :initial-name="venueContactInitialName"
            @close="closeVenueContactModal"
            @created="onVenueContactCreated"
            @updated="onVenueContactUpdated"
            @deleted="onVenueContactDeleted"
          />
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import GrossanlassKeyDatesPanel from '@/components/grossanlass/GrossanlassKeyDatesPanel.vue'
import { DepartmentAddressAutocomplete } from '@/components/addresses'
import ActivityVenueOverviewBlock from '@/components/activities/ActivityVenueOverviewBlock.vue'
import ContactDetailView from '@/components/contacts/ContactDetailView.vue'
import { EButton, EDateRangeField, ETextField, ETextarea } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { getAddresses, type Address } from '@/api/addresses'
import { getGrossanlassPlanung, updateGrossanlassPlanung, type GrossanlassGuestActivityType, type GrossanlassPlanungOverview } from '@/api/grossanlassPlanung'
import { bumpCalendarPeriodsCache } from '@/composables/useCalendarPeriodsCache'
import { formatAddressOption } from '@/utils/departmentAddressSearch'
import '@/styles/contacts-view.css'

defineOptions({ name: 'GrossanlassPlanungStammdaten' })

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)
const membership = computed(() =>
  authStore.departments.find((d) => d.department_id === departmentId.value),
)
const deptName = computed(() => membership.value?.department?.name || '')

const pack = ref<GrossanlassPlanungOverview | null>(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const periodStart = ref('')
const periodEnd = ref('')
const venueAddressId = ref<string | null>(null)
const rentalAddresses = ref<Address[]>([])
const venueAddressAutocompleteRef = ref<InstanceType<typeof DepartmentAddressAutocomplete> | null>(null)
const showVenueContactModal = ref(false)
const venueContactModalMode = ref<'view' | 'create'>('view')
const venueContactModalId = ref<string | null>(null)
const venueContactInitialName = ref('')
const notes = ref('')
const deptNameDraft = ref('')
const guestType = ref<GrossanlassGuestActivityType>('camp')
const hasGuestDepartments = ref(false)
const canManage = computed(() => pack.value?.can_manage !== false)

function toDay(iso: string | null | undefined): string {
  return iso ? iso.slice(0, 10) : ''
}

function apply(next: GrossanlassPlanungOverview) {
  pack.value = next
  periodStart.value = toDay(next.config.planned_event_start)
  periodEnd.value = toDay(next.config.planned_event_end)
  venueAddressId.value = next.config.venue_address_id || null
  notes.value = next.config.notes || ''
  deptNameDraft.value = next.department_name || deptName.value
  guestType.value = next.config.guest_activity_type === 'event' ? 'event' : 'camp'
  hasGuestDepartments.value = next.config.has_guest_departments === true
}

async function load() {
  if (!departmentId.value) return
  loading.value = true
  error.value = ''
  try {
    apply(await getGrossanlassPlanung(departmentId.value))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.beschaffung.anfragen.loadError')
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!departmentId.value) return
  saving.value = true
  try {
    apply(await updateGrossanlassPlanung(departmentId.value, {
      department_name: deptNameDraft.value.trim(),
      venue_address_id: venueAddressId.value,
      notes: notes.value,
      planned_event_start: periodStart.value || undefined,
      planned_event_end: periodEnd.value || null,
      guest_activity_type: guestType.value,
      has_guest_departments: hasGuestDepartments.value,
    }))
    bumpCalendarPeriodsCache()
    toast.success(t('grossanlass.planung.stammdaten.saved'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

const venueAddressSummary = computed(() => {
  if (!venueAddressId.value) return t('activities.wizard.form.summaryEmpty')
  const a = rentalAddresses.value.find((x) => x.id === venueAddressId.value)
  if (!a) return venueAddressId.value
  return (a.full_address && a.full_address.trim()) || formatAddressOption(a)
})

const venueContactModalKey = computed(() =>
  venueContactModalMode.value === 'create'
    ? 'venue-create'
    : `venue-view-${venueContactModalId.value ?? 'none'}`,
)

async function loadRentalAddresses() {
  if (!departmentId.value) return
  try {
    const { addresses } = await getAddresses(departmentId.value)
    rentalAddresses.value = [...addresses].sort((a, b) =>
      formatAddressOption(a).localeCompare(formatAddressOption(b), 'de'),
    )
  } catch {
    rentalAddresses.value = []
  }
}

function onVenueSelected(id: string | null) {
  venueAddressId.value = id
}

function clearVenueAddress() {
  venueAddressId.value = null
}

function closeVenueContactModal() {
  showVenueContactModal.value = false
  venueContactModalId.value = null
}

function openAddVenueAddressModal(presetName = '') {
  venueContactModalMode.value = 'create'
  venueContactModalId.value = null
  venueContactInitialName.value = String(presetName ?? '').trim()
  showVenueContactModal.value = true
}

function openEditVenueAddressModal(id: string) {
  venueContactModalMode.value = 'view'
  venueContactModalId.value = id
  showVenueContactModal.value = true
}

async function onVenueContactCreated(addr: Address) {
  closeVenueContactModal()
  await loadRentalAddresses()
  if (addr?.id) venueAddressId.value = addr.id
}

async function onVenueContactUpdated() {
  await loadRentalAddresses()
}

async function onVenueContactDeleted() {
  const deletedId = venueContactModalId.value
  closeVenueContactModal()
  await loadRentalAddresses()
  if (deletedId && venueAddressId.value === deletedId) {
    venueAddressId.value = null
  }
}

onMounted(() => {
  void load()
  void loadRentalAddresses()
})
</script>

<style scoped>
.ga-live-page { padding: 4px 0 24px; }
.intro, .hint { margin: 0 0 12px; color: #64748b; font-size: 0.9rem; }
.hint a { color: #166534; }
.warn { color: #9a3412; }
.form { max-width: 880px; }
.venue-label { display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; }
.venue-readonly { margin: 0; color: #334155; }
.selected-address { margin: 8px 0 0; font-size: 0.85rem; color: #475569; }
.clear-selection {
  margin-left: 8px;
  border: 0;
  background: transparent;
  cursor: pointer;
  font-size: 1.1rem;
  line-height: 1;
  color: #64748b;
}
.mt-3 { margin-top: 12px; }
.actions { margin-top: 16px; }
.card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 14px 16px;
  margin-top: 16px;
}
.guest-card h3 { margin: 0 0 8px; font-size: 0.95rem; }
.modus-grid { display: grid; gap: 10px; }
@media (min-width: 640px) {
  .modus-grid { grid-template-columns: 1fr 1fr; }
}
.modus-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  text-align: left;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: #f8fafc;
  cursor: pointer;
  color: #334155;
}
.modus-card span { font-size: 0.8rem; line-height: 1.35; color: #64748b; }
.modus-card:disabled { cursor: default; }
.modus-card.is-active {
  border-color: #86efac;
  background: #ecfdf5;
}
.modus-card.is-active strong { color: #166534; }
.panel {
  margin-top: 24px;
  max-width: 880px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
}
</style>
