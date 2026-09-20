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
        <section class="period-block mt-3">
          <EDateRangeField
            v-model:start="periodStart"
            v-model:end="periodEnd"
            :label="t('grossanlass.planung.stammdaten.period')"
            :department-id="departmentId"
            :disabled="!canManage"
            :allow-past="true"
            :block-closed-dates="false"
            :show-presets="false"
            :show-markers="true"
          />
          <GrossanlassKeyDatesPanel
            :department-id="departmentId"
            hide-event-period
            embedded
          />
        </section>
        <div class="mt-3 venue-wrap">
          <template v-if="!venueAddressId">
            <label class="venue-label">{{ t('grossanlass.planung.stammdaten.location') }}</label>
            <p class="hint">{{ t('grossanlass.planung.stammdaten.locationHint') }}</p>
            <button
              v-if="canManage"
              type="button"
              class="venue-set-cta"
              data-onboarding="activity-venue-add"
              @click="openAddVenueAddressModal()"
            >
              <span class="venue-set-cta-plus" aria-hidden="true">+</span>
              <span>{{ t('grossanlass.planung.stammdaten.setEventVenue') }}</span>
            </button>
            <p v-else class="venue-readonly">{{ venueAddressSummary }}</p>
          </template>
          <ActivityVenueOverviewBlock
            v-if="venueAddressId"
            :venue-address-id="venueAddressId"
            :department-id="departmentId"
            :ga-department-id="departmentId"
            ga-map-mode="starred"
            :read-only="!canManage"
            @updated="loadRentalAddresses"
          />
          <p v-if="venueAddressId && canManage" class="hint venue-standorte-link">
            <router-link :to="`/${departmentId}/einstellungen/standorte`">
              {{ t('grossanlass.planung.stammdaten.locationStandorteLink') }}
            </router-link>
          </p>
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
        <section class="card logistics-card">
          <h3>{{ t('grossanlass.planung.stammdaten.logisticsNode') }}</h3>
          <p class="hint">{{ t('grossanlass.planung.stammdaten.logisticsNodeHint') }}</p>
          <p v-if="logisticsName" class="logistics-set">
            {{ t('grossanlass.planung.stammdaten.logisticsNodeSet', { name: logisticsName }) }}
          </p>
          <p v-else class="hint">{{ t('grossanlass.planung.stammdaten.logisticsNodeUnset') }}</p>
          <router-link
            v-if="canManage"
            class="logistics-link"
            :to="`/${departmentId}/einstellungen/ressorts`"
          >
            {{ t('grossanlass.planung.stammdaten.logisticsNodeOpenRessorts') }}
          </router-link>
        </section>
        <ETextarea
          v-model="notes"
          class="notes-field"
          :label="t('grossanlass.planung.stammdaten.notes')"
          :placeholder="t('grossanlass.planung.stammdaten.notesPlaceholder')"
          :disabled="!canManage"
          rows="3"
          hide-details="auto"
        />
      </div>
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
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import GrossanlassKeyDatesPanel from '@/components/grossanlass/GrossanlassKeyDatesPanel.vue'
import ActivityVenueOverviewBlock from '@/components/activities/ActivityVenueOverviewBlock.vue'
import ContactDetailView from '@/components/contacts/ContactDetailView.vue'
import { EDateRangeField, ETextField, ETextarea } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { getAddresses, type Address } from '@/api/addresses'
import { getGrossanlassPlanung, updateGrossanlassPlanung, type GrossanlassGuestActivityType, type GrossanlassPlanungOverview } from '@/api/grossanlassPlanung'
import { useGrossanlassGuestDepartments } from '@/composables/useGrossanlassGuestDepartments'
import { bumpCalendarPeriodsCache } from '@/composables/useCalendarPeriodsCache'
import { DEFAULT_AUTO_SAVE_DELAY_MS } from '@/composables/useAutoSaveField'
import { formatAddressOption } from '@/utils/departmentAddressSearch'
import { grossanlassGroupPathTitle } from '@/utils/grossanlassCostPayer'
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
const isHydrating = ref(false)
const error = ref('')
const periodStart = ref('')
const periodEnd = ref('')
const venueAddressId = ref<string | null>(null)
const rentalAddresses = ref<Address[]>([])
const showVenueContactModal = ref(false)
const venueContactModalMode = ref<'view' | 'create'>('view')
const venueContactModalId = ref<string | null>(null)
const venueContactInitialName = ref('')
const notes = ref('')
const deptNameDraft = ref('')
const guestType = ref<GrossanlassGuestActivityType>('camp')
const hasGuestDepartments = ref(false)
const logisticsGroupId = ref<string | null>(null)
const { setHasGuestDepartments } = useGrossanlassGuestDepartments(() => departmentId.value)
const canManage = computed(() => pack.value?.can_manage !== false)
const logisticsName = computed(() => {
  const id = logisticsGroupId.value
  if (!id) return ''
  const all = pack.value?.ressorts ?? []
  const row = all.find((item) => item.id === id)
  if (!row) return ''
  return grossanlassGroupPathTitle(row, all)
})

let autoSaveTimer: ReturnType<typeof setTimeout> | null = null
let autoSaveToken = 0

function toDay(iso: string | null | undefined): string {
  return iso ? iso.slice(0, 10) : ''
}

function apply(next: GrossanlassPlanungOverview) {
  isHydrating.value = true
  pack.value = next
  periodStart.value = toDay(next.config.planned_event_start)
  periodEnd.value = toDay(next.config.planned_event_end)
  venueAddressId.value = next.config.venue_address_id || null
  notes.value = next.config.notes || ''
  deptNameDraft.value = next.department_name || deptName.value
  guestType.value = next.config.guest_activity_type === 'event' ? 'event' : 'camp'
  hasGuestDepartments.value = next.config.has_guest_departments === true
  logisticsGroupId.value = next.config.logistics_group_id || null
  setHasGuestDepartments(hasGuestDepartments.value)
  void nextTick(() => {
    isHydrating.value = false
  })
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

function clearAutoSaveTimer() {
  if (autoSaveTimer) {
    clearTimeout(autoSaveTimer)
    autoSaveTimer = null
  }
}

async function persistStammdaten(immediate = false) {
  if (!departmentId.value || !canManage.value || isHydrating.value || loading.value) return
  const name = deptNameDraft.value.trim()
  if (!name) return

  const run = async () => {
    const token = ++autoSaveToken
    saving.value = true
    try {
      apply(
        await updateGrossanlassPlanung(departmentId.value, {
          department_name: name,
          venue_address_id: venueAddressId.value,
          notes: notes.value,
          planned_event_start: periodStart.value || undefined,
          planned_event_end: periodEnd.value || null,
          guest_activity_type: guestType.value,
          has_guest_departments: hasGuestDepartments.value,
        }),
      )
      if (token !== autoSaveToken) return
      bumpCalendarPeriodsCache()
    } catch (e: unknown) {
      if (token !== autoSaveToken) return
      const err = e as { response?: { data?: { error?: string } } }
      toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
    } finally {
      if (token === autoSaveToken) saving.value = false
    }
  }

  if (immediate) {
    clearAutoSaveTimer()
    await run()
    return
  }

  clearAutoSaveTimer()
  autoSaveTimer = setTimeout(() => {
    autoSaveTimer = null
    void run()
  }, DEFAULT_AUTO_SAVE_DELAY_MS)
}

watch([deptNameDraft, periodStart, periodEnd, notes], () => {
  void persistStammdaten()
})

watch([guestType, hasGuestDepartments], () => {
  void persistStammdaten(true)
})

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

async function persistVenueAddress(id: string | null) {
  if (!departmentId.value) return
  saving.value = true
  try {
    apply(await updateGrossanlassPlanung(departmentId.value, { venue_address_id: id }))
    bumpCalendarPeriodsCache()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
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

async function onVenueContactCreated(addr: Address) {
  closeVenueContactModal()
  await loadRentalAddresses()
  if (addr?.id) {
    await persistVenueAddress(addr.id)
    toast.success(t('grossanlass.planung.stammdaten.venueSaved'))
  }
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

onBeforeUnmount(() => {
  clearAutoSaveTimer()
})
</script>

<style scoped>
.ga-live-page { padding: 4px 0 24px; }
.intro, .hint { margin: 0 0 12px; color: #64748b; font-size: 0.9rem; }
.hint a { color: #166534; }
.warn { color: #9a3412; }
.form { max-width: 880px; }
.period-block { display: block; }
.venue-label { display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; }
.venue-set-cta {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: pointer;
  color: #059669;
  font-size: 0.95rem;
  font-weight: 500;
}
.venue-set-cta:hover .venue-set-cta-plus,
.venue-set-cta:focus-visible .venue-set-cta-plus {
  border-color: #059669;
  background: #ecfdf5;
}
.venue-set-cta-plus {
  width: 42px;
  height: 42px;
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  line-height: 1;
  transition: border-color 0.2s ease, background 0.2s ease;
}
.venue-readonly { margin: 0; color: #334155; }
.mt-3 { margin-top: 12px; }
.notes-field { margin-top: 28px; }
.card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 14px 16px;
  margin-top: 16px;
}
.guest-card h3, .logistics-card h3 { margin: 0 0 8px; font-size: 0.95rem; }
.logistics-set { margin: 0 0 8px; color: #166534; font-weight: 600; }
.logistics-link { color: #166534; font-size: 0.9rem; }
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
</style>
