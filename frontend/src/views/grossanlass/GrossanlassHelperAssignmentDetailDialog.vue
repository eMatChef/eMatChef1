<template>
  <EDialog v-model="open" :title="title" :max-width="560" :retain-focus="false" scrollable>
    <p v-if="kindLabel" class="helper-assignment-dlg__kind">{{ kindLabel }}</p>

    <p v-if="assignment && !assignment.operable" class="helper-assignment-dlg__readonly">
      {{ t('grossanlass.helperAssignmentDetail.readOnlyHint') }}
    </p>

    <dl v-if="assignment" class="helper-assignment-dlg__meta">
      <div>
        <dt>{{ t('grossanlass.materialUebersicht.colRessort') }}</dt>
        <dd>{{ helperOrgLabel(assignment) }}</dd>
      </div>
      <div>
        <dt>{{ t('grossanlass.materialUebersicht.colWhen') }}</dt>
        <dd>{{ assignment.timeRangeLabel }}</dd>
      </div>
      <div v-if="showQuantity">
        <dt>{{ t('grossanlass.materialUebersicht.bookFieldQty') }}</dt>
        <dd>{{ t('grossanlass.materialUebersicht.qty', { n: assignment.qty }) }}</dd>
      </div>
      <div>
        <dt>{{ t('common.status') }}</dt>
        <dd>
          <span class="ga-helper-bar__badge" :class="helperBarKindClass(assignment)">
            {{ t(`grossanlass.materialUebersicht.status.${assignment.status}`) }}
          </span>
        </dd>
      </div>
      <div v-if="assignment.who">
        <dt>{{ t('grossanlass.materialUebersicht.colWho') }}</dt>
        <dd>{{ assignment.who }}</dd>
      </div>
      <div v-if="showDelivery">
        <dt>{{ t('grossanlass.helperAssignmentDetail.delivery') }}</dt>
        <dd>{{ deliveryLabel }}</dd>
      </div>
      <div v-if="assignment.destinationPlaceId || assignment.destinationPlaceName">
        <dt>{{ t('grossanlass.materialUebersicht.destinationLabel') }}</dt>
        <dd>{{ destinationLabel }}</dd>
      </div>
      <div v-if="assignment.taskKind === 'fahrauftrag' && chauffeurName">
        <dt>{{ t('grossanlass.materialUebersicht.chauffeurLabel') }}</dt>
        <dd>{{ chauffeurName }}</dd>
      </div>
      <div v-if="assignment.place">
        <dt>{{ t('grossanlass.helperAssignmentDetail.materialPlace') }}</dt>
        <dd>{{ materialPlaceLabel }}</dd>
      </div>
    </dl>

    <GrossanlassEinsatzSlotStrip
      v-if="assignment"
      compact
      :show-legend="false"
      :object-name="assignment.objectName"
      :from-date="isoDatePart(assignment.fromIso)"
      :to-date="isoDatePart(assignment.toIso)"
      :from-iso="assignment.fromIso"
      :to-iso="assignment.toIso"
      :bookings="[assignment]"
      :clash="false"
    />

    <div v-if="showTripFlags" class="helper-assignment-dlg__flags">
      <span v-if="assignment?.packed" class="helper-assignment-dlg__badge">
        {{ t('grossanlass.materialUebersicht.tripsPacked') }}
      </span>
      <span v-if="assignment?.tripReleased" class="helper-assignment-dlg__badge helper-assignment-dlg__badge--ok">
        {{ t('grossanlass.materialUebersicht.tripsReleased') }}
      </span>
      <span v-if="assignment?.status === 'issued'" class="helper-assignment-dlg__badge">
        {{ t('grossanlass.materialUebersicht.status.issued') }}
      </span>
      <span v-if="assignment?.delivery === 'trip' && !assignment?.tripReleased" class="helper-assignment-dlg__badge helper-assignment-dlg__badge--warn">
        {{ t('grossanlass.materialUebersicht.tripsPendingRelease') }}
      </span>
      <span v-if="assignment?.delivery === 'trip' && !assignment?.destinationPlaceId" class="helper-assignment-dlg__badge helper-assignment-dlg__badge--warn">
        {{ t('grossanlass.materialUebersicht.tripsNoDestination') }}
      </span>
    </div>

    <template #actions>
      <EButton variant="secondary" size="small" @click="open = false">
        {{ t('common.close') }}
      </EButton>
      <EButton
        v-if="canTogglePacked"
        variant="primary"
        size="small"
        :loading="busy"
        @click="$emit('toggle-packed', assignment!)"
      >
        {{
          assignment?.packed
            ? t('grossanlass.materialUebersicht.tripsUnpack')
            : t('grossanlass.materialUebersicht.tripsPack')
        }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog } from '@/components/form/base'
import type { GrossanlassUserCard } from '@/api/grossanlassUserCards'
import {
  chauffeurNameForAssignment,
  helperBarKindClass,
  helperOrgLabel,
  type GaHelperAssignment,
} from '@/views/grossanlass/grossanlassHelperAssignment'
import GrossanlassEinsatzSlotStrip from '@/views/grossanlass/GrossanlassEinsatzSlotStrip.vue'
import { isoDatePart } from '@/views/grossanlass/grossanlassZusagePreviewData'

const props = defineProps<{
  assignment: GaHelperAssignment | null
  cards?: GrossanlassUserCard[]
  busy?: boolean
  canTogglePacked?: boolean
}>()

defineEmits<{
  'toggle-packed': [assignment: GaHelperAssignment]
}>()

const open = defineModel<boolean>({ default: false })
const { t } = useI18n()

const title = computed(() => props.assignment?.objectName || t('grossanlass.helperAssignmentDetail.title'))

const kindLabel = computed(() => {
  const kind = props.assignment?.taskKind
  if (kind === 'fahrauftrag') return t('grossanlass.helperAssignmentDetail.kindFahrauftrag')
  if (kind === 'bauauftrag') return t('grossanlass.helperAssignmentDetail.kindBauauftrag')
  if (kind === 'einsatz') return t('grossanlass.helperAssignmentDetail.kindEinsatz')
  return ''
})

const showQuantity = computed(
  () => props.assignment?.kind === 'quantity' || (props.assignment?.qty ?? 0) > 1,
)

const showDelivery = computed(
  () => props.assignment?.taskKind === 'fahrauftrag'
    || props.assignment?.taskKind === 'einsatz'
    || props.assignment?.delivery === 'trip',
)

const showTripFlags = computed(
  () => props.assignment?.taskKind === 'fahrauftrag'
    || props.assignment?.delivery === 'trip',
)

const deliveryLabel = computed(() => {
  if (props.assignment?.delivery === 'trip') {
    return t('grossanlass.helperAssignmentDetail.deliveryTrip')
  }
  return t('grossanlass.helperAssignmentDetail.deliveryPickup')
})

const chauffeurName = computed(() => {
  if (!props.assignment) return ''
  return chauffeurNameForAssignment(props.assignment, props.cards ?? [])
})

const destinationLabel = computed(() => {
  if (!props.assignment) return '–'
  if (props.assignment.destinationPlaceName) return props.assignment.destinationPlaceName
  if (props.assignment.destinationPlaceId) return '–'
  return t('grossanlass.materialUebersicht.tripsNoDestination')
})

const materialPlaceLabel = computed(() => {
  const place = props.assignment?.place
  if (place === 'lager') return t('grossanlass.helperAssignmentDetail.placeLager')
  if (place === 'out') return t('grossanlass.helperAssignmentDetail.placeOut')
  if (place === 'assigned') return t('grossanlass.helperAssignmentDetail.placeAssigned')
  return '–'
})
</script>

<style scoped>
@import '@/views/grossanlass/grossanlassHelperBarColors.css';

.helper-assignment-dlg__kind {
  margin: 0 0 12px;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
}

.helper-assignment-dlg__readonly {
  margin: 0 0 12px;
  padding: 8px 10px;
  border-radius: 8px;
  background: #fff7ed;
  color: #9a3412;
  font-size: 0.82rem;
}

.helper-assignment-dlg__meta {
  display: grid;
  gap: 8px 0;
  margin: 0;
}

.helper-assignment-dlg__meta > div {
  display: grid;
  grid-template-columns: 7.5rem minmax(0, 1fr);
  gap: 8px;
  font-size: 0.85rem;
}

.helper-assignment-dlg__meta dt {
  margin: 0;
  color: #64748b;
}

.helper-assignment-dlg__meta dd {
  margin: 0;
}

.helper-assignment-dlg__flags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 14px;
}

.helper-assignment-dlg__badge {
  font-size: 11px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  background: #e2e8f0;
  color: #334155;
}

.helper-assignment-dlg__badge--ok {
  background: #ccfbf1;
  color: #0f766e;
}

.helper-assignment-dlg__badge--warn {
  background: #fee2e2;
  color: #b91c1c;
}
</style>
