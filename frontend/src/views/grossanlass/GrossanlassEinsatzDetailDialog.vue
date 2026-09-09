<template>
  <EDialog v-model="open" :title="title" :max-width="560" :retain-focus="false" scrollable>
    <p v-if="hint" class="einsatz-dlg__hint">{{ hint }}</p>

    <dl v-if="booking" class="einsatz-dlg__meta">
      <div>
        <dt>{{ t('grossanlass.materialUebersicht.colWho') }}</dt>
        <dd>{{ booking.who || '–' }}</dd>
      </div>
      <div>
        <dt>{{ t('grossanlass.materialUebersicht.colRessort') }}</dt>
        <dd>
          {{ booking.ressort || '–' }}
          <template v-if="booking.bauprojekt"> · {{ booking.bauprojekt }}</template>
        </dd>
      </div>
      <div>
        <dt>{{ t('grossanlass.materialUebersicht.colWhen') }}</dt>
        <dd>{{ booking.fromLabel }} – {{ booking.toLabel }}</dd>
      </div>
      <div>
        <dt>{{ t('common.status') }}</dt>
        <dd class="einsatz-dlg__chips">
          <span v-if="stayText" class="ga-einsatz-status" :class="`ga-einsatz-status--${stayMode}`">
            {{ stayText }}
          </span>
          <span class="ga-einsatz-status" :class="`ga-einsatz-status--${kind}`">
            {{ statusText }}
          </span>
        </dd>
      </div>
    </dl>

    <template v-if="editing">
      <EDateRangeField
        v-model:start="fromDate"
        v-model:end="toDate"
        :department-id="departmentId"
        :label="t('grossanlass.materialUebersicht.bookFieldPeriod')"
        allow-past
      />
      <div class="einsatz-dlg__times">
        <ETimeField v-model="fromTime" :label="t('grossanlass.materialUebersicht.fieldFromTime')" />
        <ETimeField v-model="toTime" :label="t('grossanlass.materialUebersicht.fieldToTime')" />
      </div>
      <ETextField
        v-if="booking?.kind === 'quantity'"
        v-model="qty"
        type="number"
        min="1"
        :label="t('grossanlass.materialUebersicht.bookFieldQty')"
        hide-details
      />
    </template>

    <template #actions>
      <EButton variant="secondary" size="small" @click="open = false">
        {{ editing ? t('common.cancel') : t('common.close') }}
      </EButton>
      <EButton
        v-if="showTasks"
        variant="secondary"
        size="small"
        @click="goTasks"
      >
        {{ t('grossanlass.materialUebersicht.einsatzDialogTasks') }}
      </EButton>
      <EButton
        v-if="showAgreements"
        variant="secondary"
        size="small"
        @click="goAgreements"
      >
        {{ t('grossanlass.materialUebersicht.einsatzDialogAgreements') }}
      </EButton>
      <EButton
        v-if="editable && !editing"
        variant="primary"
        size="small"
        @click="editing = true"
      >
        {{ t('grossanlass.materialUebersicht.einsatzDialogEdit') }}
      </EButton>
      <EButton
        v-if="editing"
        variant="primary"
        size="small"
        :disabled="!canSave"
        :loading="saving"
        @click="save"
      >
        {{ t('grossanlass.materialUebersicht.conflictResolveSaveOne') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, EDateRangeField, EDialog, ETextField, ETimeField } from '@/components/form/base'
import {
  einsatzBarKind,
  parseLocalDate,
  type GaEinsatzStayMode,
  type GaPreviewEinsatz,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import { combineIso, isoDatePart, isoTimePart } from '@/views/grossanlass/grossanlassZusagePreviewData'
import { useToast } from '@/composables/useToast'

const props = defineProps<{
  booking: GaPreviewEinsatz | null
  stayMode?: GaEinsatzStayMode | null
}>()

const open = defineModel<boolean>({ default: false })
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const uebersicht = useGaUebersicht()
const saving = ref(false)
const editing = ref(false)
const fromDate = ref('')
const toDate = ref('')
const fromTime = ref('08:00')
const toTime = ref('18:00')
const qty = ref('1')

const departmentId = computed(() => String(route.params.departmentId || ''))
const kind = computed(() => (props.booking ? einsatzBarKind(props.booking) : 'planned'))
const showAgreements = computed(() =>
  kind.value === 'handover' || kind.value === 'giveback' || kind.value === 'service',
)
const showTasks = computed(() => {
  if (!props.booking) return false
  if (kind.value === 'fixed') return false
  return !showAgreements.value
})
const title = computed(() =>
  props.booking?.objectName || t('grossanlass.materialUebersicht.einsatzDialogTitle'),
)
const statusText = computed(() =>
  t(`grossanlass.materialUebersicht.status.${kind.value}`),
)
const stayText = computed(() => {
  if (!props.stayMode) return ''
  return props.stayMode === 'return'
    ? t('grossanlass.materialUebersicht.stayReturn')
    : t('grossanlass.materialUebersicht.stayUntilEnd')
})

const editable = computed(() => {
  const row = props.booking
  if (!row || !row.id) return false
  const role = row.barRole ?? 'einsatz'
  if (role !== 'einsatz') return false
  if (row.status === 'issued' || row.status === 'returned') return false
  return Boolean(departmentId.value)
})

const hint = computed(() => {
  if (!props.booking) return ''
  if (kind.value === 'fixed') return t('grossanlass.materialUebersicht.einsatzDialogFixedHint')
  if (kind.value === 'handover' || kind.value === 'giveback' || kind.value === 'service') {
    return t('grossanlass.materialUebersicht.occupancyFixedHint')
  }
  if (props.booking.status === 'issued' || props.booking.status === 'returned') {
    return t('grossanlass.materialUebersicht.conflictResolveIssued')
  }
  if (editable.value) return t('grossanlass.materialUebersicht.einsatzDialogHint')
  if (kind.value === 'pending_approval') return t('grossanlass.materialUebersicht.pendingMwHint')
  return ''
})

const slot = computed(() => ({
  from: combineIso(fromDate.value, fromTime.value || '00:00'),
  to: combineIso(toDate.value, toTime.value || '00:00'),
}))

const canSave = computed(() => {
  if (!editable.value || saving.value || !fromDate.value || !toDate.value) return false
  return parseLocalDate(slot.value.from) < parseLocalDate(slot.value.to)
})

watch(
  () => [open.value, props.booking?.id] as const,
  () => {
    const row = props.booking
    if (!open.value || !row) return
    fromDate.value = isoDatePart(row.fromIso)
    toDate.value = isoDatePart(row.toIso)
    fromTime.value = isoTimePart(row.fromIso) || '08:00'
    toTime.value = isoTimePart(row.toIso) || '18:00'
    qty.value = String(row.qty || 1)
    saving.value = false
    editing.value = false
  },
  { immediate: true },
)

function goTasks() {
  const id = departmentId.value
  if (!id) return
  open.value = false
  void router.push(`/${id}/tasks/allgemein`)
}

function goAgreements() {
  const id = departmentId.value
  if (!id) return
  open.value = false
  void router.push(`/${id}/beschaffung/zusagen`)
}

async function save() {
  if (!canSave.value || !props.booking) return
  saving.value = true
  try {
    await uebersicht.updateEinsatz(props.booking.id, {
      from: slot.value.from,
      to: slot.value.to,
      ...(props.booking.kind === 'quantity'
        ? { qty: Math.max(1, Number(qty.value) || 1) }
        : {}),
    })
    toast.success(t('grossanlass.materialUebersicht.einsatzDialogSaved'))
    open.value = false
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.einsatz-dlg__hint {
  margin: 0 0 12px;
  color: #64748b;
  font-size: 0.85rem;
}

.einsatz-dlg__meta {
  display: grid;
  gap: 8px 0;
  margin: 0 0 16px;
}

.einsatz-dlg__meta > div {
  display: grid;
  grid-template-columns: 7.5rem minmax(0, 1fr);
  gap: 8px;
  font-size: 0.85rem;
}

.einsatz-dlg__meta dt {
  margin: 0;
  color: #64748b;
}

.einsatz-dlg__meta dd {
  margin: 0;
}

.einsatz-dlg__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.einsatz-dlg__times {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin: 8px 0 12px;
}

.ga-einsatz-status {
  display: inline-flex;
  align-items: center;
  padding: 1px 8px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.ga-einsatz-status--planned {
  background: var(--color-primary-subtle-bg);
  color: var(--color-primary-dark);
}

.ga-einsatz-status--pending_approval {
  background: #fff7ed;
  color: #9a3412;
}

.ga-einsatz-status--issued {
  background: var(--activity-status-packing-bg, #e0f2fe);
  color: var(--activity-status-packing);
}

.ga-einsatz-status--returned,
.ga-einsatz-status--fixed {
  background: #e2e8f0;
  color: #334155;
}

.ga-einsatz-status--handover {
  background: #ccfbf1;
  color: #0f766e;
}

.ga-einsatz-status--giveback {
  background: #ede9fe;
  color: #6d28d9;
}

.ga-einsatz-status--service {
  background: #fef3c7;
  color: #a16207;
}

.ga-einsatz-status--return {
  background: var(--color-primary-subtle-bg);
  color: var(--color-primary-dark);
}

.ga-einsatz-status--stay {
  background: var(--activity-status-at_event-bg, #ecfeff);
  color: var(--activity-status-at_event-fg, #0e7490);
}
</style>
