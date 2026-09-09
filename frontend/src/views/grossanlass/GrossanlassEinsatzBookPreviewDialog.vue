<template>
  <EDialog v-model="open" :title="dialogTitle" :max-width="680" :retain-focus="false" scrollable>
    <p class="book-hint">{{ stepHint }}</p>

    <template v-if="step === 'pick'">
      <div class="book-toggle" role="tablist" :aria-label="t('grossanlass.materialUebersicht.bookScope')">
        <button
          type="button"
          role="tab"
          :aria-selected="scope === 'single'"
          class="book-toggle__btn"
          :class="{ 'book-toggle__btn--on': scope === 'single' }"
          @click="setScope('single')"
        >
          {{ t('grossanlass.materialUebersicht.bookScopeSingle') }}
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="scope === 'project'"
          class="book-toggle__btn"
          :class="{ 'book-toggle__btn--on': scope === 'project' }"
          @click="setScope('project')"
        >
          {{ t('grossanlass.materialUebersicht.bookScopeProject') }}
        </button>
      </div>

      <template v-if="scope === 'single'">
        <div class="book-toggle book-toggle--sub" role="tablist" :aria-label="t('grossanlass.materialUebersicht.sourceGroup')">
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

      <template v-else>
        <EAutocomplete
          v-model="projectId"
          v-model:menu="projectMenuOpen"
          :items="projectItems"
          item-title="title"
          item-value="value"
          :label="t('grossanlass.materialUebersicht.bookProjectLabel')"
          :placeholder="t('grossanlass.materialUebersicht.bookProjectPlaceholder')"
          :menu-props="projectMenuProps"
          :no-filter="false"
          clearable
          hide-details
        >
          <template #item="{ props: itemProps, item }">
            <v-list-item
              v-bind="projectItemBind(itemProps)"
              :disabled="projectRow(item).wishCount === 0"
              class="book-project-dd"
              :class="{ 'book-project-dd--nested': projectRow(item).depth > 0 }"
              :style="{ paddingInlineStart: `${12 + projectRow(item).depth * 16}px` }"
            >
              <template #title>
                <span class="book-project-dd__row">
                  <span class="book-project-dd__name">
                    <span v-if="projectRow(item).depth > 0" class="book-project-dd__mark" aria-hidden="true">↳</span>
                    {{ projectRow(item).name }}
                  </span>
                  <span class="book-project-dd__meta">
                    <span
                      v-if="projectRow(item).wishCount > 0"
                      class="book-project-dd__count"
                    >
                      {{ projectRow(item).wishCount }}
                    </span>
                    <span v-if="projectRow(item).belowCount > 0" class="book-project-dd__below">
                      +{{ projectRow(item).belowCount }}
                    </span>
                  </span>
                </span>
              </template>
              <template #subtitle>
                {{ projectItemSubtitle(projectRow(item)) }}
              </template>
            </v-list-item>
          </template>
        </EAutocomplete>
        <p v-if="projectId && projectWishes.length === 0" class="book-project-empty">
          {{ t('grossanlass.materialUebersicht.bookProjectEmpty') }}
        </p>
        <ul v-else-if="projectWishes.length" class="book-project-list">
          <li
            v-for="wish in projectWishes"
            :key="wish.id"
            class="book-project-row"
            :class="{ 'is-order': !canBookWish(wish) }"
          >
            <ECheckbox
              v-if="canBookWish(wish)"
              :model-value="selectedWishIds.includes(wish.id)"
              hide-details
              @update:model-value="toggleWish(wish.id, Boolean($event))"
            />
            <span v-else class="book-project-skip" />
            <div class="book-project-copy">
              <strong>{{ wish.qty }}× {{ wish.label }}</strong>
              <span>{{ t('grossanlass.materialUebersicht.bookProjectWishWindow', { from: wish.fromLabel, to: wish.toLabel }) }}</span>
              <span v-if="wishWarn(wish)" class="book-project-warn">{{ wishWarn(wish) }}</span>
            </div>
            <EButton
              v-if="!canBookWish(wish) && !orderedIds.has(wish.id)"
              variant="secondary"
              size="small"
              :loading="orderingId === wish.id"
              @click="orderWish(wish)"
            >
              {{ t('grossanlass.materialUebersicht.actionOrder') }}
            </EButton>
            <span v-else-if="orderedIds.has(wish.id)" class="book-project-noted">
              {{ t('grossanlass.materialUebersicht.orderNoted') }}
            </span>
          </li>
        </ul>
        <div v-if="projectId && selectedWishIds.length" class="book-project-period">
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
          <p class="book-project-period__hint">{{ t('grossanlass.materialUebersicht.bookProjectPeriodHint') }}</p>
        </div>
        <div v-if="projectId && selectedWishIds.length" class="book-delivery">
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
          v-if="scope === 'project' && needsDriver"
          v-model="destinationPlaceId"
          v-model:menu="placeMenuOpen"
          :items="placeItems"
          item-title="title"
          item-value="value"
          :label="t('grossanlass.materialUebersicht.destinationLabel')"
          :placeholder="t('grossanlass.materialUebersicht.destinationPlaceholder')"
          :menu-props="listMenuProps"
          :no-filter="false"
          :disabled="placeSaving"
          clearable
          hide-details
        />
        <EAutocomplete
          v-if="scope === 'project' && needsDriver"
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
          v-if="scope === 'project' && chauffeurBlocked"
          type="warning"
          variant="tonal"
          class="mt-3"
          :text="t('grossanlass.materialUebersicht.chauffeurNoLicense')"
        />
        <v-alert
          v-if="scope === 'project' && projectSlotOutside"
          type="warning"
          variant="tonal"
          class="mt-3"
          :text="t('grossanlass.materialUebersicht.bookOutsideWindow')"
        />
      </template>
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
      <div v-if="draftFromIso && draftToIso && slotYmds.length" class="book-slots" :class="{ 'book-slots--stack': slotStacked }">
        <div v-if="slotStacked" class="book-slots__head">
          <strong>{{ slotHeading }}</strong>
          <span>{{ slotLegend }}</span>
        </div>
        <div v-if="slotStacked" class="book-slots__hours" aria-hidden="true">
          <span class="book-slots__gutter" />
          <div class="book-slots__hour-scale">
            <span v-for="hour in slotHourLabels" :key="hour.key">{{ hour.label }}</span>
          </div>
        </div>
        <div class="book-slots__days">
          <GrossanlassEinsatzSlotStrip
            v-for="ymd in slotYmds"
            :key="ymd"
            :object-name="draft.objectName"
            :from-date="ymd"
            :to-date="ymd"
            :from-iso="draftFromIso"
            :to-iso="draftToIso"
            :bookings="dayBookings"
            :clash="slotBusy"
            :compact="slotStacked"
            :show-hours="!slotStacked"
            :show-legend="!slotStacked"
          />
        </div>
      </div>
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
        :disabled="placeSaving"
        clearable
        hide-details
      >
        <template #append-inner>
          <button
            type="button"
            class="book-place-plus"
            :class="{ 'is-open': showPlaceCreate }"
            :title="t('grossanlass.materialUebersicht.destinationAdd')"
            :aria-label="t('grossanlass.materialUebersicht.destinationAdd')"
            :aria-expanded="showPlaceCreate"
            :disabled="placeSaving"
            @mousedown.prevent
            @click.stop="togglePlaceCreate"
          >
            <v-icon :icon="showPlaceCreate ? 'mdi-close' : 'mdi-plus'" size="20" />
          </button>
        </template>
      </EAutocomplete>
      <div v-if="needsDriver && showPlaceCreate" ref="placeCreateEl" class="book-place-create">
        <ETextField
          v-model="newPlaceName"
          :label="t('grossanlass.materialUebersicht.destinationAddName')"
          :placeholder="t('grossanlass.einstellungen.placesName')"
          hide-details
          :disabled="placeSaving"
          @keydown.enter.prevent="createPlace"
        />
        <EButton
          variant="primary"
          size="small"
          :disabled="!newPlaceName.trim()"
          :loading="placeSaving"
          @click="createPlace"
        >
          {{ t('grossanlass.einstellungen.placesAdd') }}
        </EButton>
      </div>
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
        v-if="step === 'pick' && scope === 'single' && mode === 'einsatz'"
        variant="primary"
        size="small"
        :disabled="!draft"
        @click="goDetails"
      >
        {{ t('common.next') }}
      </EButton>
      <EButton
        v-else-if="scope === 'project' && step === 'pick'"
        variant="primary"
        size="small"
        :disabled="!canConfirmProject"
        :loading="savingProject"
        @click="confirmProject"
      >
        {{ projectConfirmLabel }}
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
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EAutocomplete, EButton, ECheckbox, EDateRangeField, EDialog, ETextField, ETimeField } from '@/components/form/base'
import GrossanlassEinsatzSlotStrip from '@/views/grossanlass/GrossanlassEinsatzSlotStrip.vue'
import { createGrossanlassPlace, type GaPlace } from '@/api/grossanlassLogistics'
import { useToast } from '@/composables/useToast'
import {
  formatCalendarTitle,
  isoRangesOverlap,
  isIssuedSlotLocked,
  isOutsidePresentWindow,
  isSlotConflict,
  parseLocalDate,
  type GaEinsatzResource,
  type GaPreviewEinsatz,
  type GaPreviewWishTemplate,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { combineIso } from '@/views/grossanlass/grossanlassZusagePreviewData'
import { normalizeDepartmentTimeHHMM } from '@/utils/activityPlanningFromDefaults'
import {
  BOOK_PROJECT_UNASSIGNED,
  buildBookProjectPickerItems,
  isEinsatzBookableWish,
} from '@/utils/grossanlassBookProjectPicker'

export type GaBookPreviewMode = 'einsatz' | 'order'
export type GaBookPreviewDraft = GaPreviewWishTemplate & {
  fromWish: boolean
  chauffeurUserId?: string
  delivery?: 'trip' | 'pickup'
  destinationPlaceId?: string
  asOrder?: boolean
}
export type GaBookGroup = {
  id: string
  name: string
  parent_id?: string | null
  node_type?: string
  sort_order?: number | null
}
type BookSource = 'own' | 'wish'
type BookScope = 'single' | 'project'
type BookStep = 'pick' | 'details'

const listMenuProps = {
  maxHeight: 180,
  location: 'bottom' as const,
  offset: 4,
  zIndex: 2400,
  scrim: false,
  contentClass: 'ga-book-autocomplete-menu',
}

const projectMenuProps = {
  ...listMenuProps,
  maxHeight: 280,
  contentClass: 'ga-book-project-autocomplete-menu',
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
  presetObjectId?: string
  presetWishId?: string | null
  groups?: GaBookGroup[]
  defaultScope?: BookScope
}>()

const emit = defineEmits<{
  confirm: [draft: GaBookPreviewDraft]
  confirmMany: [drafts: GaBookPreviewDraft[]]
  order: [draft: GaBookPreviewDraft]
  placeCreated: [place: GaPlace]
}>()

const draft = defineModel<GaBookPreviewDraft | null>('draft', { default: null })
const source = ref<BookSource>('own')
const scope = ref<BookScope>('single')
function initialScope(): BookScope {
  return props.defaultScope === 'project' ? 'project' : 'single'
}
const pickMenuOpen = ref(false)
const projectMenuOpen = ref(false)
const chauffeurMenuOpen = ref(false)
const placeMenuOpen = ref(false)
const pickedId = ref<string | null>(null)
const projectId = ref<string | null>(null)
const selectedWishIds = ref<string[]>([])
const orderedIds = ref(new Set<string>())
const orderingId = ref<string | null>(null)
const savingProject = ref(false)
const step = ref<BookStep>('pick')
const fromDate = ref('')
const toDate = ref('')
const fromTime = ref('08:00')
const toTime = ref('18:00')
const chauffeurId = ref<string | null>(null)
const destinationPlaceId = ref<string | null>(null)
const delivery = ref<'trip' | 'pickup'>('pickup')
const extraPlaces = ref<Array<{ id: string; name: string }>>([])
const showPlaceCreate = ref(false)
const newPlaceName = ref('')
const placeSaving = ref(false)
const placeCreateEl = ref<HTMLElement | null>(null)

const route = useRoute()
const { t, locale } = useI18n()
const toast = useToast()

const departmentId = computed(() => String(route.params.departmentId || ''))

const dialogTitle = computed(() => {
  if (props.mode === 'order') return t('grossanlass.materialUebersicht.actionOrder')
  if (scope.value === 'project') return t('grossanlass.materialUebersicht.bookProjectTitle')
  return step.value === 'details'
    ? t('grossanlass.materialUebersicht.bookDialogDetailsTitle')
    : t('grossanlass.materialUebersicht.bookDialogTitle')
})

const stepHint = computed(() => {
  if (props.mode === 'order') return t('grossanlass.materialUebersicht.orderHint')
  if (scope.value === 'project') return t('grossanlass.materialUebersicht.bookProjectHint')
  return step.value === 'details'
    ? t('grossanlass.materialUebersicht.detailsHint')
    : t('grossanlass.materialUebersicht.bookDialogHint')
})

const confirmLabel = computed(() => {
  if (props.mode === 'order' || (draft.value && !draft.value.objectId)) {
    return t('grossanlass.materialUebersicht.orderConfirm')
  }
  if (step.value === 'details' && (slotBusy.value || slotIssuedLock.value || slotUnreleased.value || slotOutside.value)) {
    return t('grossanlass.materialUebersicht.bookNotifyMw')
  }
  return t('grossanlass.materialUebersicht.bookConfirm')
})

const wishItems = computed(() =>
  scopedWishes.value.map((item) => ({
    title: item.label,
    subtitle: `${item.qty}× ${item.objectName} · ${item.fromLabel} – ${item.toLabel}`,
    value: item.id,
  })),
)

const freeItems = computed(() =>
  scopedPicks.value.map((item) => ({
    title: item.objectName,
    subtitle: `${t('grossanlass.materialUebersicht.qty', { n: item.qty })} · ${item.ressort}`,
    value: item.id,
  })),
)

const scopedWishes = computed(() => {
  const bookable = props.wishes.filter((wish) => isEinsatzBookableWish(wish))
  const objectId = props.presetObjectId
  if (!objectId) return bookable
  return bookable.filter((wish) => wish.objectId === objectId || wish.id === props.presetWishId)
})

const scopedPicks = computed(() => {
  const objectId = props.presetObjectId
  if (!objectId) return props.freePicks
  return props.freePicks.filter((item) => item.objectId === objectId)
})

const chauffeurPeople = computed(() => props.chauffeurs ?? [])

const chauffeurItems = computed(() =>
  chauffeurPeople.value.map(({ value, title, subtitle }) => ({ value, title, subtitle })),
)

const placeItems = computed(() => {
  const seen = new Set<string>()
  const items: Array<{ value: string; title: string }> = []
  for (const place of [...(props.places ?? []), ...extraPlaces.value]) {
    if (seen.has(place.id)) continue
    seen.add(place.id)
    items.push({ value: place.id, title: place.name })
  }
  return items
})

const needsDriver = computed(() =>
  (props.mode === 'einsatz' || scope.value === 'project') && delivery.value === 'trip',
)

const selectedChauffeur = computed(() =>
  chauffeurPeople.value.find((person) => person.value === chauffeurId.value) ?? null,
)

const chauffeurBlocked = computed(() =>
  needsDriver.value && selectedChauffeur.value !== null && !selectedChauffeur.value.mayDrive,
)

const draftFromIso = computed(() =>
  fromDate.value && fromTime.value ? combineIso(fromDate.value, fromTime.value) : '',
)
const draftToIso = computed(() =>
  toDate.value && toTime.value ? combineIso(toDate.value, toTime.value) : '',
)

function eachYmd(fromYmd: string, toYmd: string): string[] {
  if (!fromYmd) return []
  const start = parseLocalDate(`${fromYmd}T00:00:00`)
  const end = parseLocalDate(`${(toYmd || fromYmd)}T00:00:00`)
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return []
  const last = end < start ? start : end
  const dates: string[] = []
  const cursor = new Date(start.getTime())
  while (cursor <= last) {
    const year = cursor.getFullYear()
    const month = String(cursor.getMonth() + 1).padStart(2, '0')
    const day = String(cursor.getDate()).padStart(2, '0')
    dates.push(`${year}-${month}-${day}`)
    cursor.setDate(cursor.getDate() + 1)
  }
  return dates
}

const slotYmds = computed(() => eachYmd(fromDate.value, toDate.value))
const slotStacked = computed(() => slotYmds.value.length > 1)
const slotHourLabels = computed(() =>
  Array.from({ length: 24 }, (_, hour) => ({
    key: String(hour),
    label: hour % 3 === 0 ? String(hour).padStart(2, '0') : '',
  })),
)
const slotHeading = computed(() => {
  const first = slotYmds.value[0]
  const last = slotYmds.value[slotYmds.value.length - 1]
  if (!first) return ''
  const start = parseLocalDate(`${first}T00:00:00`)
  if (!slotStacked.value) {
    return start.toLocaleDateString(locale.value, {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
  }
  const end = parseLocalDate(`${last}T00:00:00`)
  end.setDate(end.getDate() + 1)
  return formatCalendarTitle('week', start, end, locale.value)
})
const slotLegend = computed(() =>
  slotBusy.value
    ? t('grossanlass.materialUebersicht.slotLegendClash')
    : t('grossanlass.materialUebersicht.slotLegendFree'),
)

const dayBookings = computed(() => {
  if (!draft.value || !fromDate.value) return []
  const rangeStart = `${fromDate.value}T00:00:00`
  const rangeEnd = `${toDate.value || fromDate.value}T24:00:00`
  return (props.rows ?? []).filter(
    (row) =>
      row.objectId === draft.value?.objectId
      && isoRangesOverlap(row.fromIso, row.toIso, rangeStart, rangeEnd),
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
      draft.value.objectId
      && needsDriver.value
      && (!chauffeurId.value || chauffeurBlocked.value || !destinationPlaceId.value)
    ) return false
  }
  return true
})

function projectItemBind(itemProps: Record<string, unknown> | undefined) {
  if (!itemProps) return {}
  const { title: _title, subtitle: _subtitle, ...rest } = itemProps
  return rest
}

function projectRow(item: { raw?: Record<string, unknown>; [key: string]: unknown } | null | undefined) {
  const raw = (item?.raw && typeof item.raw === 'object' ? item.raw : item) || {}
  return {
    name: String(raw.name ?? raw.title ?? ''),
    depth: Math.max(0, Number(raw.depth ?? 0)),
    wishCount: Number(raw.wishCount ?? 0),
    belowCount: Number(raw.belowCount ?? 0),
    nodeType: String(raw.nodeType ?? ''),
  }
}

function projectKindLabel(nodeType: string): string {
  if (nodeType === 'bauprojekt') return t('grossanlass.planung.ressorts.kindBauprojekt')
  if (nodeType === 'unterressort') return t('grossanlass.planung.ressorts.kindUnterressort')
  if (nodeType === 'ressort') return t('grossanlass.planung.ressorts.kindRessort')
  return ''
}

function projectItemSubtitle(row: { nodeType: string; belowCount: number }): string {
  const kind = projectKindLabel(row.nodeType)
  const below = row.belowCount > 0
    ? t('grossanlass.materialUebersicht.bookProjectWishBelow', row.belowCount)
    : ''
  return [kind, below].filter(Boolean).join(' · ')
}

const projectItems = computed(() =>
  buildBookProjectPickerItems(
    props.groups ?? [],
    scopedWishes.value,
    t('grossanlass.materialUebersicht.bookProjectUnassigned'),
  ),
)

const projectWishes = computed(() => {
  if (!projectId.value) return []
  if (projectId.value === BOOK_PROJECT_UNASSIGNED) {
    return scopedWishes.value.filter((wish) => !wish.groupId)
  }
  return scopedWishes.value.filter((wish) => wish.groupId === projectId.value)
})

const projectConfirmLabel = computed(() =>
  t('grossanlass.materialUebersicht.bookProjectConfirm', selectedWishIds.value.length),
)

const projectSlotOutside = computed(() => {
  if (!draftFromIso.value || !draftToIso.value) return false
  return selectedWishIds.value.some((id) => {
    const wish = projectWishes.value.find((row) => row.id === id)
    if (!wish?.objectId) return false
    const resource = (props.resources ?? []).find((row) => row.id === wish.objectId)
    return isOutsidePresentWindow(resource, draftFromIso.value, draftToIso.value)
  })
})

const canConfirmProject = computed(() => {
  if (selectedWishIds.value.length === 0) return false
  if (!fromDate.value || !toDate.value || !fromTime.value || !toTime.value) return false
  if (
    needsDriver.value
    && (!chauffeurId.value || chauffeurBlocked.value || !destinationPlaceId.value)
  ) return false
  return true
})

const projectPeriodWishId = computed(() => selectedWishIds.value[0] ?? '')

watch(open, async (isOpen) => {
  if (!isOpen) {
    draft.value = null
    source.value = 'own'
    scope.value = initialScope()
    pickMenuOpen.value = false
    projectMenuOpen.value = false
    chauffeurMenuOpen.value = false
    placeMenuOpen.value = false
    pickedId.value = null
    projectId.value = null
    selectedWishIds.value = []
    orderedIds.value = new Set()
    orderingId.value = null
    savingProject.value = false
    step.value = 'pick'
    chauffeurId.value = null
    destinationPlaceId.value = null
    delivery.value = 'pickup'
    showPlaceCreate.value = false
    newPlaceName.value = ''
    return
  }
  if (props.presetWishId) {
    scope.value = 'single'
    source.value = 'wish'
    pickedId.value = props.presetWishId
    await nextTick()
    goDetails()
    return
  }
  if (props.presetObjectId && scopedPicks.value[0]) {
    scope.value = 'single'
    source.value = 'own'
    pickedId.value = scopedPicks.value[0].id
    await nextTick()
    goDetails()
    return
  }
  scope.value = initialScope()
  step.value = 'pick'
})

watch(pickedId, (id) => {
  if (!id) {
    draft.value = null
    return
  }
  const pool = source.value === 'wish' ? scopedWishes.value : scopedPicks.value
  const item = pool.find((row) => row.id === id)
  draft.value = item ? { ...item, fromWish: source.value === 'wish' } : null
})

watch(projectId, (id) => {
  if (!id) {
    selectedWishIds.value = []
    return
  }
  selectedWishIds.value = projectWishes.value.filter((wish) => canBookWish(wish)).map((wish) => wish.id)
})

watch(projectPeriodWishId, (id) => {
  if (scope.value !== 'project') return
  const wish = projectWishes.value.find((row) => row.id === id)
  if (wish) applyWishPeriod(wish)
})

function setScope(next: BookScope) {
  scope.value = next
  pickedId.value = null
  draft.value = null
  projectId.value = null
  selectedWishIds.value = []
  pickMenuOpen.value = false
  projectMenuOpen.value = false
  step.value = 'pick'
  chauffeurId.value = null
  destinationPlaceId.value = null
  delivery.value = 'pickup'
}

function setSource(next: BookSource) {
  source.value = next
  pickedId.value = null
  draft.value = null
  pickMenuOpen.value = false
  step.value = 'pick'
}

function splitIso(iso: string): { date: string; time: string } {
  const [date, timePart] = iso.split('T')
  return {
    date: date || '',
    time: normalizeDepartmentTimeHHMM((timePart || '08:00:00').slice(0, 5)),
  }
}

function applyWishPeriod(wish: GaPreviewWishTemplate) {
  const from = splitIso(wish.fromIso)
  const to = splitIso(wish.toIso)
  fromDate.value = from.date
  toDate.value = to.date
  fromTime.value = from.time
  toTime.value = to.time
}

function projectEinsatzIso(): { fromIso: string; toIso: string; fromLabel: string; toLabel: string } | null {
  if (!fromDate.value || !toDate.value || !fromTime.value || !toTime.value) return null
  return {
    fromIso: combineIso(fromDate.value, fromTime.value),
    toIso: combineIso(toDate.value, toTime.value),
    fromLabel: formatSlot(fromDate.value, fromTime.value),
    toLabel: formatSlot(toDate.value, toTime.value),
  }
}

function wishEinsatzIso(wish: GaPreviewWishTemplate): { fromIso: string; toIso: string } {
  if (scope.value === 'project' && selectedWishIds.value.includes(wish.id)) {
    const slot = projectEinsatzIso()
    if (slot) return slot
  }
  return { fromIso: wish.fromIso, toIso: wish.toIso }
}

function formatSlot(date: string, time: string): string {
  const [year, month, day] = date.split('-')
  if (!year || !month || !day) return `${date} ${time}`
  return `${day}.${month}.${year}, ${time}`
}

function goDetails() {
  if (!draft.value) return
  applyWishPeriod(draft.value)
  chauffeurId.value = null
  destinationPlaceId.value = null
  delivery.value = 'pickup'
  step.value = 'details'
}

function onDeliveryTrip(on: boolean | null) {
  delivery.value = on ? 'trip' : 'pickup'
  if (delivery.value === 'pickup') {
    chauffeurId.value = null
    showPlaceCreate.value = false
  }
}

function onDeliveryPickup(on: boolean | null) {
  delivery.value = on ? 'pickup' : 'trip'
  if (delivery.value === 'pickup') {
    chauffeurId.value = null
    showPlaceCreate.value = false
  }
}

function togglePlaceCreate() {
  showPlaceCreate.value = !showPlaceCreate.value
  if (!showPlaceCreate.value) return
  placeMenuOpen.value = false
  void nextTick(() => {
    placeCreateEl.value?.querySelector('input')?.focus()
  })
}

async function createPlace() {
  const name = newPlaceName.value.trim()
  if (!name || !departmentId.value || placeSaving.value) return
  placeSaving.value = true
  try {
    const created = await createGrossanlassPlace(departmentId.value, { name })
    extraPlaces.value = [...extraPlaces.value, created]
    destinationPlaceId.value = created.id
    newPlaceName.value = ''
    showPlaceCreate.value = false
    placeMenuOpen.value = false
    emit('placeCreated', created)
    toast.success(t('grossanlass.materialUebersicht.destinationAdded', { name: created.name }))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.einstellungen.placesAddError'))
  } finally {
    placeSaving.value = false
  }
}

function canBookWish(wish: GaPreviewWishTemplate): boolean {
  return Boolean(wish.objectId)
}

function wishWarn(wish: GaPreviewWishTemplate): string {
  if (!wish.objectId) return t('grossanlass.materialUebersicht.bookProjectNoStock')
  const resource = (props.resources ?? []).find((row) => row.id === wish.objectId)
  const { fromIso, toIso } = wishEinsatzIso(wish)
  if (resource?.released === false) return t('grossanlass.materialUebersicht.bookProjectUnreleased')
  if (isOutsidePresentWindow(resource, fromIso, toIso)) {
    return t('grossanlass.materialUebersicht.bookProjectOutside')
  }
  const fake: GaBookPreviewDraft = { ...wish, fromWish: true }
  if (isSlotConflict(props.rows ?? [], fake, fromIso, toIso)) {
    return t('grossanlass.materialUebersicht.bookProjectConflict')
  }
  if (isIssuedSlotLocked(props.rows ?? [], wish.objectId, fromIso, toIso)) {
    return t('grossanlass.materialUebersicht.bookIssuedLock')
  }
  return ''
}

function toggleWish(id: string, on: boolean) {
  if (on) {
    if (!selectedWishIds.value.includes(id)) selectedWishIds.value = [...selectedWishIds.value, id]
    return
  }
  selectedWishIds.value = selectedWishIds.value.filter((row) => row !== id)
}

function draftFromWish(wish: GaPreviewWishTemplate, asOrder = false): GaBookPreviewDraft {
  const hasConflict = asOrder
    ? false
    : Boolean(wishWarn(wish)) && canBookWish(wish)
  const slot = asOrder ? null : projectEinsatzIso()
  return {
    ...wish,
    ...(slot ?? {}),
    fromWish: true,
    chauffeurUserId: chauffeurId.value || undefined,
    destinationPlaceId: destinationPlaceId.value || undefined,
    delivery: delivery.value,
    hasConflict,
    asOrder,
  }
}

function orderWish(wish: GaPreviewWishTemplate) {
  if (orderingId.value || orderedIds.value.has(wish.id)) return
  orderingId.value = wish.id
  orderedIds.value = new Set([...orderedIds.value, wish.id])
  emit('order', draftFromWish(wish, true))
  orderingId.value = null
}

function confirmProject() {
  if (!canConfirmProject.value || savingProject.value) return
  const drafts = selectedWishIds.value
    .map((id) => projectWishes.value.find((wish) => wish.id === id))
    .filter((wish): wish is GaPreviewWishTemplate => Boolean(wish && canBookWish(wish)))
    .map((wish) => draftFromWish(wish))
  if (drafts.length === 0) return
  savingProject.value = true
  emit('confirmMany', drafts)
  open.value = false
}

function confirm() {
  if (!draft.value || !canConfirm.value) return
  let next = { ...draft.value }
  if (props.mode === 'einsatz' && step.value === 'details') {
    next = {
      ...next,
      fromIso: combineIso(fromDate.value, fromTime.value),
      toIso: combineIso(toDate.value, toTime.value),
      fromLabel: formatSlot(fromDate.value, fromTime.value),
      toLabel: formatSlot(toDate.value, toTime.value),
      who: selectedChauffeur.value?.title ?? next.who,
      chauffeurUserId: chauffeurId.value || undefined,
      destinationPlaceId: destinationPlaceId.value || undefined,
      delivery: delivery.value,
      hasConflict: slotBusy.value || slotIssuedLock.value || slotUnreleased.value || slotOutside.value,
    }
  }
  emit('confirm', {
    ...next,
    asOrder: props.mode === 'order' || !next.objectId,
  })
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
.book-toggle--sub {
  margin-top: -8px;
  margin-bottom: 16px;
}
.book-toggle--sub .book-toggle__btn {
  min-height: 44px;
  font-size: 0.85rem;
  font-weight: 600;
}
.book-project-empty {
  margin: 12px 0 0;
  font-size: 0.85rem;
  color: #64748b;
}
.book-project-list {
  list-style: none;
  margin: 14px 0 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.book-project-row {
  display: grid;
  grid-template-columns: 28px minmax(0, 1fr) auto;
  gap: 8px;
  align-items: start;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}
.book-project-row.is-order { background: #f8fafc; }
.book-project-skip { width: 28px; }
.book-project-copy {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.book-project-copy span { font-size: 0.78rem; color: #64748b; }
.book-project-warn { color: #b45309 !important; font-weight: 600; }
.book-project-noted { font-size: 0.78rem; color: #0f766e; font-weight: 600; }
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
.book-project-period {
  margin: 12px 0 4px;
}
.book-project-period__hint {
  margin: 0 0 12px;
  font-size: 12px;
  color: #64748b;
}
.book-place-plus {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  margin-right: 2px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #0f766e;
  cursor: pointer;
}
.book-place-plus.is-open { color: #64748b; }
.book-place-create {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 8px;
  align-items: end;
  margin: 8px 0 12px;
}
.book-slots--stack {
  margin: 4px 0 12px;
}
.book-slots__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 4px 12px;
  margin-bottom: 6px;
  font-size: 0.78rem;
  color: #6b7280;
}
.book-slots__head strong {
  color: #111827;
  font-weight: 600;
}
.book-slots__hours {
  position: sticky;
  top: 0;
  z-index: 1;
  display: grid;
  grid-template-columns: 5.6rem minmax(0, 1fr);
  gap: 6px;
  margin-bottom: 2px;
  padding-bottom: 2px;
  background: #fff;
}
.book-slots__hour-scale {
  display: grid;
  grid-template-columns: repeat(24, minmax(0, 1fr));
  font-size: 0.55rem;
  font-variant-numeric: tabular-nums;
  color: #6b7280;
  text-align: center;
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
.ga-book-project-autocomplete-menu {
  max-height: 280px !important;
}
.ga-book-project-autocomplete-menu .v-list {
  max-height: 280px;
  overflow-y: auto;
}
.ga-book-project-autocomplete-menu .book-project-dd--nested {
  min-height: 44px;
}
.ga-book-project-autocomplete-menu .book-project-dd__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  width: 100%;
}
.ga-book-project-autocomplete-menu .book-project-dd__name {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}
.ga-book-project-autocomplete-menu .book-project-dd__mark {
  color: #64748b;
  font-weight: 600;
}
.ga-book-project-autocomplete-menu .book-project-dd__meta {
  display: inline-flex;
  align-items: baseline;
  gap: 4px;
  flex-shrink: 0;
}
.ga-book-project-autocomplete-menu .book-project-dd__count {
  min-width: 1.4rem;
  padding: 0 6px;
  border-radius: 999px;
  background: #ccfbf1;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  font-size: 0.78rem;
  line-height: 1.4rem;
  color: #0f766e;
  text-align: center;
}
.ga-book-project-autocomplete-menu .book-project-dd__below {
  font-size: 0.72rem;
  font-weight: 600;
  color: #64748b;
}
</style>
