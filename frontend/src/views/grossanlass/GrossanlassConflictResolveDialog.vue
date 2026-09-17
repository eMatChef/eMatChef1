<template>
  <EDialog v-model="open" :title="title" :max-width="720" :retain-focus="false" scrollable>
    <p class="conflict-hint">{{ hint }}</p>
    <p v-if="stillClash" class="conflict-warn">{{ clashText }}</p>
    <p v-else class="conflict-ok">{{ t('grossanlass.materialUebersicht.conflictResolveFits') }}</p>

    <div v-if="previewWindow" class="conflict-preview" aria-hidden="true">
      <div
        v-for="(draft, index) in drafts"
        :key="`lane-${draft.id || index}`"
        class="conflict-preview__lane"
      >
        <span>{{ draft.label }}</span>
        <div class="conflict-preview__track">
          <span
            class="conflict-preview__bar"
            :class="{
              'is-clash': stillClash && !draft.readonly,
              'is-firm': draft.readonly,
            }"
            :style="barBox(draft)"
          />
        </div>
      </div>
    </div>

    <section
      v-for="(draft, index) in drafts"
      :key="draft.id || `ro-${index}`"
      class="conflict-row"
    >
      <h3>{{ draft.label }}</h3>
      <p class="conflict-row__meta">{{ draft.meta }}</p>
      <template v-if="draft.readonly">
        <p class="conflict-row__fixed">{{ t('grossanlass.materialUebersicht.conflictResolveFirmFixed') }}</p>
      </template>
      <template v-else-if="draft.locked">
        <p class="conflict-row__fixed">{{ t('grossanlass.materialUebersicht.conflictResolveIssued') }}</p>
      </template>
      <template v-else>
        <EDateRangeField
          v-model:start="draft.fromDate"
          v-model:end="draft.toDate"
          :department-id="departmentId"
          :label="t('grossanlass.materialUebersicht.bookFieldPeriod')"
          allow-past
        />
        <div class="conflict-times">
          <ETimeField v-model="draft.fromTime" :label="t('grossanlass.materialUebersicht.fieldFromTime')" />
          <ETimeField v-model="draft.toTime" :label="t('grossanlass.materialUebersicht.fieldToTime')" />
        </div>
        <ETextField
          v-if="showQty"
          v-model="draft.qty"
          type="number"
          min="1"
          :label="t('grossanlass.materialUebersicht.bookFieldQty')"
          hide-details
        />
      </template>
    </section>

    <template #actions>
      <EButton variant="secondary" size="small" @click="open = false">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        size="small"
        :disabled="!canSave"
        :loading="saving"
        @click="save"
      >
        {{ saveLabel }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, EDateRangeField, EDialog, ETextField, ETimeField } from '@/components/form/base'
import type { GaUebersichtConflict } from '@/api/grossanlassUebersicht'
import {
  barStyleInWindow,
  isoRangesOverlap,
  isOutsidePresentWindow,
  parseLocalDate,
  spanningMonthWindow,
  type GaPreviewEinsatz,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { useGaUebersicht } from '@/views/grossanlass/gaUebersicht'
import { combineIso, isoDatePart, isoTimePart } from '@/views/grossanlass/grossanlassZusagePreviewData'
import { useToast } from '@/composables/useToast'

type Draft = {
  id: string
  label: string
  meta: string
  fromDate: string
  toDate: string
  fromTime: string
  toTime: string
  qty: string
  locked: boolean
  readonly: boolean
}

const props = defineProps<{
  conflict: GaUebersichtConflict | null
  rows: GaPreviewEinsatz[]
  presentFromIso?: string
  presentToIso?: string
  stock?: number
}>()

const emit = defineEmits<{
  saved: []
}>()

const open = defineModel<boolean>({ default: false })
const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const uebersicht = useGaUebersicht()
const saving = ref(false)
const drafts = reactive<Draft[]>([])

const departmentId = computed(() => String(route.params.departmentId || ''))

const title = computed(() =>
  props.conflict?.title || t('grossanlass.materialUebersicht.conflictResolveTitle'),
)

const hint = computed(() => {
  if (props.conflict?.kind === 'outside_window') {
    return t('grossanlass.materialUebersicht.conflictResolveOutsideHint')
  }
  return t('grossanlass.materialUebersicht.conflictResolveHint')
})

const showQty = computed(() => props.conflict?.kind === 'quantity_overbook')

const saveLabel = computed(() =>
  editable.value.length > 1
    ? t('grossanlass.materialUebersicht.conflictResolveSave')
    : t('grossanlass.materialUebersicht.conflictResolveSaveOne'),
)

function draftIso(draft: Draft): { from: string; to: string } {
  return {
    from: combineIso(draft.fromDate, draft.fromTime || '00:00'),
    to: combineIso(draft.toDate, draft.toTime || '00:00'),
  }
}

const editable = computed(() => drafts.filter((draft) => !draft.readonly && !draft.locked && draft.id))

const stillClash = computed(() => {
  if (!props.conflict) return false
  const slots = drafts
    .filter((draft) => draft.fromDate && draft.toDate)
    .map((draft) => draftIso(draft))
    .filter((slot) => slot.from && slot.to)
  if (props.conflict.kind === 'outside_window') {
    const row = slots[0]
    if (!row) return false
    return isOutsidePresentWindow(
      { presentFromIso: props.presentFromIso, presentToIso: props.presentToIso },
      row.from,
      row.to,
    )
  }
  if (slots.length < 2) return false
  const overlap = isoRangesOverlap(slots[0].from, slots[0].to, slots[1].from, slots[1].to)
  if (!overlap) return false
  if (props.conflict.kind === 'quantity_overbook') {
    const used = drafts
      .filter((draft) => !draft.readonly)
      .reduce((sum, draft) => sum + Math.max(1, Number(draft.qty) || 1), 0)
    return used > (props.stock ?? 1)
  }
  return true
})

const clashText = computed(() => {
  if (props.conflict?.kind === 'outside_window') {
    return t('grossanlass.materialUebersicht.conflictResolveOutsideStill')
  }
  if (props.conflict?.kind === 'quantity_overbook') {
    return t('grossanlass.materialUebersicht.conflictResolveQtyStill')
  }
  return t('grossanlass.materialUebersicht.conflictResolveStill')
})

const previewWindow = computed(() => {
  const dates: Date[] = []
  for (const draft of drafts) {
    if (!draft.fromDate || !draft.toDate) continue
    const slot = draftIso(draft)
    const from = parseLocalDate(slot.from)
    const to = parseLocalDate(slot.to)
    if (!Number.isNaN(from.getTime())) dates.push(from)
    if (!Number.isNaN(to.getTime())) dates.push(to)
  }
  return spanningMonthWindow(dates)
})

function barBox(draft: Draft): Record<string, string> {
  if (!previewWindow.value || !draft.fromDate) return { display: 'none' }
  const slot = draftIso(draft)
  const pos = barStyleInWindow(
    { fromIso: slot.from, toIso: slot.to } as GaPreviewEinsatz,
    previewWindow.value.start,
    previewWindow.value.end,
    'month',
  )
  if (!pos) return { display: 'none' }
  return { left: pos.left, width: pos.width }
}

const canSave = computed(() => {
  if (editable.value.length === 0 || saving.value) return false
  return editable.value.every((draft) => {
    if (!draft.fromDate || !draft.toDate || !draft.fromTime || !draft.toTime) return false
    const slot = draftIso(draft)
    return parseLocalDate(slot.from) < parseLocalDate(slot.to)
  })
})

watch(
  () => [open.value, props.conflict?.id] as const,
  () => {
    drafts.splice(0, drafts.length)
    if (!open.value || !props.conflict) return
    const involved = props.conflict.einsatz_ids
      .map((id) => props.rows.find((row) => row.id === id))
      .filter((row): row is GaPreviewEinsatz => Boolean(row))
    for (const row of involved) {
      drafts.push({
        id: row.id,
        label: `${row.ressort || row.who} · ${row.objectName}`,
        meta: `${row.fromLabel} – ${row.toLabel}`,
        fromDate: isoDatePart(row.fromIso),
        toDate: isoDatePart(row.toIso),
        fromTime: isoTimePart(row.fromIso) || '08:00',
        toTime: isoTimePart(row.toIso) || '18:00',
        qty: String(row.qty || 1),
        locked: row.status === 'issued' || row.status === 'returned',
        readonly: false,
      })
    }
    if (
      props.conflict.kind === 'outside_window'
      && props.presentFromIso
      && props.presentToIso
    ) {
      drafts.push({
        id: '',
        label: t('grossanlass.materialUebersicht.conflictResolveFirmLane'),
        meta: '',
        fromDate: isoDatePart(props.presentFromIso),
        toDate: isoDatePart(props.presentToIso),
        fromTime: isoTimePart(props.presentFromIso) || '00:00',
        toTime: isoTimePart(props.presentToIso) || '23:59',
        qty: '0',
        locked: true,
        readonly: true,
      })
    }
  },
  { immediate: true },
)

async function save() {
  if (!canSave.value) return
  saving.value = true
  try {
    for (const draft of editable.value) {
      const slot = draftIso(draft)
      await uebersicht.updateEinsatz(draft.id, {
        from: slot.from,
        to: slot.to,
        ...(showQty.value ? { qty: Math.max(1, Number(draft.qty) || 1) } : {}),
      })
    }
    toast.success(t('grossanlass.materialUebersicht.conflictResolveSaved'))
    open.value = false
    emit('saved')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    saving.value = false
  }
}

watch(() => props.conflict?.id, () => {
  saving.value = false
})
</script>

<style scoped>
.conflict-hint {
  margin: 0 0 10px;
  color: #64748b;
  font-size: 0.85rem;
}
.conflict-warn {
  margin: 0 0 12px;
  color: #9a3412;
  font-weight: 700;
  font-size: 0.85rem;
}
.conflict-ok {
  margin: 0 0 12px;
  color: #166534;
  font-weight: 700;
  font-size: 0.85rem;
}
.conflict-preview {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0 0 16px;
}
.conflict-preview__lane {
  display: grid;
  grid-template-columns: minmax(7rem, 11rem) minmax(0, 1fr);
  gap: 8px;
  align-items: center;
  font-size: 0.72rem;
  color: #64748b;
}
.conflict-preview__track {
  position: relative;
  height: 16px;
  border-radius: 6px;
  background: #f3f4f6;
  overflow: hidden;
}
.conflict-preview__bar {
  position: absolute;
  inset: 2px auto 2px 0;
  border-radius: 4px;
  background: #0f766e;
}
.conflict-preview__bar.is-clash {
  background: #dc2626;
}
.conflict-preview__bar.is-firm {
  background: #64748b;
}
.conflict-row {
  margin: 0 0 16px;
  padding: 12px 0 0;
  border-top: 1px solid #e5e7eb;
}
.conflict-row h3 {
  margin: 0 0 4px;
  font-size: 0.95rem;
}
.conflict-row__meta,
.conflict-row__fixed {
  margin: 0 0 10px;
  font-size: 0.82rem;
  color: #64748b;
}
.conflict-times {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin: 8px 0;
}
</style>
