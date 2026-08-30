<template>
  <EDialog v-model="open" :title="dialogTitle" :max-width="680" :retain-focus="false" scrollable>
    <p class="book-hint">{{ stepHint }}</p>

    <template v-if="step === 'pick'">
      <div class="book-toggle" role="tablist" :aria-label="t('grossanlass.materialUebersicht.sourceGroup')">
        <button
          type="button"
          role="tab"
          :aria-selected="source === 'own'"
          class="book-toggle__btn"
          :class="{ 'book-toggle__btn--on': source === 'own' }"
          @click="setSource('own')"
        >
          {{ t('grossanlass.materialUebersicht.sourceOwn') }}
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="source === 'wish'"
          class="book-toggle__btn"
          :class="{ 'book-toggle__btn--on': source === 'wish' }"
          @click="setSource('wish')"
        >
          {{ t('grossanlass.materialUebersicht.sourceWish') }}
        </button>
      </div>

      <EAutocomplete
        v-if="source === 'own'"
        v-model="pickedId"
        v-model:menu="pickMenuOpen"
        :items="freeItems"
        item-title="title"
        item-value="value"
        item-subtitle="subtitle"
        :label="t('grossanlass.materialUebersicht.objectSearchLabel')"
        :placeholder="t('grossanlass.materialUebersicht.objectSearchPlaceholder')"
        :menu-props="listMenuProps"
        :no-filter="false"
        clearable
        hide-details
      />

      <EAutocomplete
        v-if="source === 'wish'"
        v-model="pickedId"
        v-model:menu="pickMenuOpen"
        :items="wishItems"
        item-title="title"
        item-value="value"
        item-subtitle="subtitle"
        :label="t('grossanlass.materialUebersicht.wishSearchLabel')"
        :placeholder="t('grossanlass.materialUebersicht.wishSearchPlaceholder')"
        :menu-props="listMenuProps"
        :no-filter="false"
        clearable
        hide-details
      />
    </template>

    <template v-else-if="draft">
      <p v-if="draft.fromWish" class="book-from-wish">
        {{ t('grossanlass.materialUebersicht.bookFromWishBadge') }}
      </p>
      <p class="book-object">
        <strong>{{ draft.objectName }}</strong>
        <span>{{ t('grossanlass.materialUebersicht.qty', { n: draft.qty }) }} · {{ draft.ressort }}</span>
      </p>

      <EDateRangeField
        v-model:start="fromDate"
        v-model:end="toDate"
        :department-id="departmentId"
        :label="t('grossanlass.materialUebersicht.bookFieldPeriod')"
        allow-past
      />
      <div class="book-times">
        <ETimeField v-model="fromTime" :label="t('grossanlass.materialUebersicht.fieldFromTime')" />
        <ETimeField v-model="toTime" :label="t('grossanlass.materialUebersicht.fieldToTime')" />
      </div>
      <GrossanlassEinsatzSlotStrip
        v-if="draftFromIso && draftToIso"
        :object-name="draft.objectName"
        :from-date="fromDate"
        :to-date="toDate"
        :from-iso="draftFromIso"
        :to-iso="draftToIso"
        :bookings="dayBookings"
        :clash="slotBusy"
      />
      <div v-if="mode === 'einsatz'" class="book-delivery">
        <p class="book-delivery__label">{{ t('grossanlass.materialUebersicht.deliveryLabel') }}</p>
        <div class="book-delivery__row">
          <ECheckbox
            :model-value="delivery === 'trip'"
            :label="t('grossanlass.materialUebersicht.deliveryTrip')"
            hide-details
            @update:model-value="onDeliveryTrip"
          />
          <ECheckbox
            :model-value="delivery === 'pickup'"
            :label="t('grossanlass.materialUebersicht.deliveryPickup')"
            hide-details
            @update:model-value="onDeliveryPickup"
          />
        </div>
        <p class="book-delivery__hint">{{ t('grossanlass.materialUebersicht.deliveryHint') }}</p>
      </div>
      <EAutocomplete
        v-if="needsDriver"
        v-model="destinationPlaceId"
        v-model:menu="placeMenuOpen"
        :items="placeItems"
        item-title="title"
        item-value="value"
        :label="t('grossanlass.materialUebersicht.destinationLabel')"
        :placeholder="t('grossanlass.materialUebersicht.destinationPlaceholder')"
        :menu-props="listMenuProps"
        :no-filter="false"
        clearable
        hide-details
      />
      <EAutocomplete
        v-if="needsDriver"
        v-model="chauffeurId"
        v-model:menu="chauffeurMenuOpen"
        :items="chauffeurItems"
        item-title="title"
        item-value="value"
        item-subtitle="subtitle"
        :label="t('grossanlass.materialUebersicht.chauffeurLabel')"
        :placeholder="t('grossanlass.materialUebersicht.chauffeurPlaceholder')"
        :hint="t('grossanlass.materialUebersicht.chauffeurHint')"
        persistent-hint
        :menu-props="listMenuProps"
        :no-filter="false"
        clearable
        hide-details="auto"
      />
      <v-alert
        v-if="chauffeurBlocked"
        type="warning"
        variant="tonal"
        class="mt-3"
        :text="t('grossanlass.materialUebersicht.chauffeurNoLicense')"
      />
      <v-alert
        v-if="slotIssuedLock && mode === 'einsatz'"
        type="warning"
        variant="tonal"
        class="mt-3"
        :text="t('grossanlass.materialUebersicht.bookIssuedLock')"
      />
      <v-alert
        v-else-if="slotUnreleased && mode === 'einsatz'"
        type="warning"
        variant="tonal"
        class="mt-3"
        :text="t('grossanlass.materialUebersicht.bookUnreleased')"
      />
      <v-alert
        v-else-if="slotOutside && mode === 'einsatz'"
        type="warning"
        variant="tonal"
        class="mt-3"
        :text="t('grossanlass.materialUebersicht.bookOutsideWindow')"
      />
      <v-alert
        v-else-if="slotBusy && mode === 'einsatz'"
        type="warning"
        variant="tonal"
        class="mt-3"
        :text="t('grossanlass.materialUebersicht.bookConflict')"
      />
    </template>

    <template #actions>
      <EButton v-if="step === 'details'" variant="secondary" size="small" @click="step = 'pick'">
        {{ t('common.back') }}
      </EButton>
      <EButton v-else variant="secondary" size="small" @click="open = false">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        v-if="step === 'pick' && mode === 'einsatz'"
        variant="primary"
        size="small"
        :disabled="!draft"
        @click="goDetails"
      >
        {{ t('common.next') }}
      </EButton>
      <EButton
        v-else
        variant="primary"
        size="small"
        :disabled="!canConfirm"
        @click="confirm"
      >
        {{ confirmLabel }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EAutocomplete, EButton, ECheckbox, EDateRangeField, EDialog, ETimeField } from '@/components/form/base'
import GrossanlassEinsatzSlotStrip from '@/views/grossanlass/GrossanlassEinsatzSlotStrip.vue'
import {
  isoRangesOverlap,
  isIssuedSlotLocked,
  isOutsidePresentWindow,
  isSlotConflict,
  type GaEinsatzResource,
  type GaPreviewEinsatz,
  type GaPreviewWishTemplate,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'

export type GaBookPreviewMode = 'einsatz' | 'order'
export type GaBookPreviewDraft = GaPreviewWishTemplate & {
  fromWish: boolean
  chauffeurUserId?: string
  delivery?: 'trip' | 'pickup'
  destinationPlaceId?: string
}
type BookSource = 'own' | 'wish'
type BookStep = 'pick' | 'details'

const listMenuProps = {
  maxHeight: 180,
  location: 'bottom' as const,
  offset: 4,
  zIndex: 2400,
  scrim: false,
  contentClass: 'ga-book-autocomplete-menu',
}

const open = defineModel<boolean>({ default: false })
const props = defineProps<{
  mode: GaBookPreviewMode
  wishes: GaPreviewWishTemplate[]
  freePicks: GaPreviewWishTemplate[]
  rows?: GaPreviewEinsatz[]
  resources?: GaEinsatzResource[]
  chauffeurs?: Array<{ value: string; title: string; subtitle: string; mayDrive: boolean }>
  places?: Array<{ id: string; name: string }>
}>()

const emit = defineEmits<{
  confirm: [draft: GaBookPreviewDraft]
}>()

const draft = defineModel<GaBookPreviewDraft | null>('draft', { default: null })
const source = ref<BookSource>('own')
const pickMenuOpen = ref(false)
const chauffeurMenuOpen = ref(false)
const placeMenuOpen = ref(false)
const pickedId = ref<string | null>(null)
const step = ref<BookStep>('pick')
const fromDate = ref('')
const toDate = ref('')
const fromTime = ref('08:00')
const toTime = ref('18:00')
const chauffeurId = ref<string | null>(null)
const destinationPlaceId = ref<string | null>(null)
const delivery = ref<'trip' | 'pickup'>('pickup')

const route = useRoute()
const { t } = useI18n()

const departmentId = computed(() => String(route.params.departmentId || ''))

const dialogTitle = computed(() => {
  if (props.mode === 'order') return t('grossanlass.materialUebersicht.actionOrder')
  return step.value === 'details'
    ? t('grossanlass.materialUebersicht.bookDialogDetailsTitle')
    : t('grossanlass.materialUebersicht.bookDialogTitle')
})

const stepHint = computed(() => {
  if (props.mode === 'order') return t('grossanlass.materialUebersicht.orderHint')
  return step.value === 'details'
    ? t('grossanlass.materialUebersicht.detailsHint')
    : t('grossanlass.materialUebersicht.bookDialogHint')
})

const confirmLabel = computed(() => {
  if (props.mode === 'order') return t('grossanlass.materialUebersicht.orderConfirm')
  if (step.value === 'details' && (slotBusy.value || slotIssuedLock.value || slotUnreleased.value || slotOutside.value)) {
    return t('grossanlass.materialUebersicht.bookNotifyMw')
  }
  return t('grossanlass.materialUebersicht.bookConfirm')
})

const wishItems = computed(() =>
  props.wishes.map((item) => ({
    title: item.label,
    subtitle: `${item.qty}× ${item.objectName} · ${item.fromLabel} – ${item.toLabel}`,
    value: item.id,
  })),
)

const freeItems = computed(() =>
  props.freePicks.map((item) => ({
    title: item.objectName,
    subtitle: `${t('grossanlass.materialUebersicht.qty', { n: item.qty })} · ${item.ressort}`,
    value: item.id,
  })),
)

const chauffeurPeople = computed(() => props.chauffeurs ?? [])

const chauffeurItems = computed(() =>
  chauffeurPeople.value.map(({ value, title, subtitle }) => ({ value, title, subtitle })),
)

const placeItems = computed(() =>
  (props.places ?? []).map((place) => ({ value: place.id, title: place.name })),
)

const needsDriver = computed(() => props.mode === 'einsatz' && delivery.value === 'trip')

const selectedChauffeur = computed(() =>
  chauffeurPeople.value.find((person) => person.value === chauffeurId.value) ?? null,
)

const chauffeurBlocked = computed(() =>
  needsDriver.value && selectedChauffeur.value !== null && !selectedChauffeur.value.mayDrive,
)

const draftFromIso = computed(() =>
  fromDate.value && fromTime.value ? `${fromDate.value}T${fromTime.value}:00` : '',
)
const draftToIso = computed(() =>
  toDate.value && toTime.value ? `${toDate.value}T${toTime.value}:00` : '',
)

const dayBookings = computed(() => {
  if (!draft.value || !fromDate.value) return []
  const dayStart = `${fromDate.value}T00:00:00`
  const dayEnd = `${fromDate.value}T24:00:00`
  return (props.rows ?? []).filter(
    (row) =>
      row.objectId === draft.value?.objectId
      && isoRangesOverlap(row.fromIso, row.toIso, dayStart, dayEnd),
  )
})

const slotBusy = computed(() => {
  if (!draft.value || !draftFromIso.value || !draftToIso.value) return false
  return isSlotConflict(props.rows ?? [], draft.value, draftFromIso.value, draftToIso.value)
})

const slotIssuedLock = computed(() => {
  if (!draft.value || !draftFromIso.value || !draftToIso.value) return false
  return isIssuedSlotLocked(props.rows ?? [], draft.value.objectId, draftFromIso.value, draftToIso.value)
})

const currentResource = computed(() => {
  const objectId = draft.value?.objectId
  if (!objectId) return undefined
  return (props.resources ?? []).find((resource) => resource.id === objectId)
})

const slotUnreleased = computed(() => currentResource.value?.released === false)

const slotOutside = computed(() => {
  if (!draft.value || !draftFromIso.value || !draftToIso.value) return false
  return isOutsidePresentWindow(currentResource.value, draftFromIso.value, draftToIso.value)
})

const canConfirm = computed(() => {
  if (!draft.value) return false
  if (props.mode === 'einsatz' && step.value === 'details') {
    if (!fromDate.value || !toDate.value || !fromTime.value || !toTime.value) return false
    if (
      needsDriver.value
      && (!chauffeurId.value || chauffeurBlocked.value || !destinationPlaceId.value)
    ) return false
  }
  return true
})

watch(open, (isOpen) => {
  if (!isOpen) {
    draft.value = null
    source.value = 'own'
    pickMenuOpen.value = false
    chauffeurMenuOpen.value = false
    placeMenuOpen.value = false
    pickedId.value = null
    step.value = 'pick'
    chauffeurId.value = null
    destinationPlaceId.value = null
    delivery.value = 'pickup'
  }
})

watch(pickedId, (id) => {
  if (!id) {
    draft.value = null
    return
  }
  const pool = source.value === 'wish' ? props.wishes : props.freePicks
  const item = pool.find((row) => row.id === id)
  draft.value = item ? { ...item, fromWish: source.value === 'wish' } : null
})

function setSource(next: BookSource) {
  source.value = next
  pickedId.value = null
  draft.value = null
  pickMenuOpen.value = false
  step.value = 'pick'
}

function splitIso(iso: string): { date: string; time: string } {
  const [date, timePart] = iso.split('T')
  return { date: date || '', time: (timePart || '08:00:00').slice(0, 5) }
}

function formatSlot(date: string, time: string): string {
  const [year, month, day] = date.split('-')
  if (!year || !month || !day) return `${date} ${time}`
  return `${day}.${month}.${year}, ${time}`
}

function goDetails() {
  if (!draft.value) return
  const from = splitIso(draft.value.fromIso)
  const to = splitIso(draft.value.toIso)
  fromDate.value = from.date
  toDate.value = to.date
  fromTime.value = from.time
  toTime.value = to.time
  chauffeurId.value = null
  destinationPlaceId.value = null
  delivery.value = 'pickup'
  step.value = 'details'
}

function onDeliveryTrip(on: boolean | null) {
  delivery.value = on ? 'trip' : 'pickup'
  if (delivery.value === 'pickup') chauffeurId.value = null
}

function onDeliveryPickup(on: boolean | null) {
  delivery.value = on ? 'pickup' : 'trip'
  if (delivery.value === 'pickup') chauffeurId.value = null
}

function confirm() {
  if (!draft.value || !canConfirm.value) return
  let next = { ...draft.value }
  if (props.mode === 'einsatz' && step.value === 'details') {
    next = {
      ...next,
      fromIso: `${fromDate.value}T${fromTime.value}:00`,
      toIso: `${toDate.value}T${toTime.value}:00`,
      fromLabel: formatSlot(fromDate.value, fromTime.value),
      toLabel: formatSlot(toDate.value, toTime.value),
      who: selectedChauffeur.value?.title ?? next.who,
      chauffeurUserId: chauffeurId.value || undefined,
      destinationPlaceId: destinationPlaceId.value || undefined,
      delivery: delivery.value,
      hasConflict: slotBusy.value || slotIssuedLock.value || slotUnreleased.value || slotOutside.value,
    }
  }
  emit('confirm', next)
  open.value = false
}
</script>

<style scoped>
.book-hint { margin: 0 0 14px; color: #64748b; font-size: 0.85rem; }
.book-toggle {
  display: grid;
  grid-template-columns: 1fr 1fr;
  margin-bottom: 16px;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  overflow: hidden;
}
.book-toggle__btn {
  min-height: 56px;
  padding: 12px 10px;
  border: 0;
  background: #fff;
  font-size: 0.95rem;
  font-weight: 700;
  color: #334155;
  cursor: pointer;
}
.book-toggle__btn + .book-toggle__btn { border-left: 1px solid #e5e7eb; }
.book-toggle__btn--on { background: #0f766e; color: #fff; }
.book-from-wish {
  margin: 0 0 8px;
  font-size: 0.8rem;
  font-weight: 600;
  color: #0f766e;
}
.book-object {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin: 0 0 14px;
}
.book-object span { font-size: 0.82rem; color: #64748b; }
.book-times {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin: 8px 0 12px;
}
.book-delivery {
  margin: 4px 0 12px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f8fafc;
}
.book-delivery__label {
  margin: 0 0 6px;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}
.book-delivery__row {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 20px;
}
.book-delivery__hint {
  margin: 6px 0 0;
  font-size: 12px;
  color: #64748b;
}
</style>

<style>
.ga-book-autocomplete-menu {
  max-height: 180px !important;
}
.ga-book-autocomplete-menu .v-list {
  max-height: 180px;
  overflow-y: auto;
}
</style>
