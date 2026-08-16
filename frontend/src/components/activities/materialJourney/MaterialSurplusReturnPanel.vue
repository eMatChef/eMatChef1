<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import ESelect from '@/components/form/base/ESelect.vue'
import ETextField from '@/components/form/base/ETextField.vue'
import type {
  ActivitySurplusReport,
  ActivitySurplusReportKind,
} from '@/api/activitySurplusReports'

const props = defineProps<{
  reports: ActivitySurplusReport[]
  submitting: boolean
  canEdit: boolean
}>()

const emit = defineEmits<{
  submit: [
    payload: {
      nameFreeText: string
      qty: number
      kind: ActivitySurplusReportKind
      expiryDate: string | null
      notes: string | null
    },
  ]
  remove: [reportId: string]
  dismiss: [reportId: string]
}>()

const { t } = useI18n()

const nameFreeText = ref('')
const qty = ref(1)
const kind = ref<ActivitySurplusReportKind>('food')
const expiryDate = ref('')
const notes = ref('')

const kindItems = computed(() => [
  { title: t('activities.materialJourney.surplusReturn.kindFood'), value: 'food' },
  { title: t('activities.materialJourney.surplusReturn.kindConsumable'), value: 'consumable' },
  { title: t('activities.materialJourney.surplusReturn.kindOther'), value: 'other' },
])

const canSubmit = computed(
  () => props.canEdit && nameFreeText.value.trim() !== '' && qty.value >= 1 && !props.submitting,
)

function resetForm(): void {
  nameFreeText.value = ''
  qty.value = 1
  kind.value = 'food'
  expiryDate.value = ''
  notes.value = ''
}

function onSubmit(): void {
  if (!canSubmit.value) return
  emit('submit', {
    nameFreeText: nameFreeText.value.trim(),
    qty: qty.value,
    kind: kind.value,
    expiryDate: expiryDate.value.trim() || null,
    notes: notes.value.trim() || null,
  })
}

function statusLabel(report: ActivitySurplusReport): string {
  if (report.status === 'open') return t('activities.materialJourney.surplusReturn.statusOpen')
  if (report.status === 'matched') return t('activities.materialJourney.surplusReturn.statusMatched')
  if (report.status === 'resolved') return t('activities.materialJourney.surplusReturn.statusResolved')
  return t('activities.materialJourney.surplusReturn.statusDismissed')
}

function kindLabel(report: ActivitySurplusReport): string {
  if (report.kind === 'food') return t('activities.materialJourney.surplusReturn.kindFood')
  if (report.kind === 'consumable') return t('activities.materialJourney.surplusReturn.kindConsumable')
  return t('activities.materialJourney.surplusReturn.kindOther')
}

defineExpose({ resetForm })
</script>

<template>
  <div class="material-surplus-return-panel section-card">
    <h2 class="material-surplus-return-panel__title">
      {{ t('activities.materialJourney.surplusReturn.title') }}
    </h2>
    <p class="material-surplus-return-panel__hint text-muted">
      {{ t('activities.materialJourney.surplusReturn.hint') }}
    </p>

    <div v-if="canEdit" class="material-surplus-return-panel__form">
      <ETextField
        v-model="nameFreeText"
        :label="t('activities.materialJourney.surplusReturn.nameLabel')"
        :placeholder="t('activities.materialJourney.surplusReturn.namePlaceholder')"
      />

      <div class="material-surplus-return-panel__row">
        <ETextField
          v-model.number="qty"
          type="number"
          :label="t('activities.materialJourney.surplusReturn.qtyLabel')"
          :min="1"
          inputmode="numeric"
        />
        <ESelect
          v-model="kind"
          :label="t('activities.materialJourney.surplusReturn.kindLabel')"
          :items="kindItems"
          item-title="title"
          item-value="value"
        />
      </div>

      <ETextField
        v-if="kind === 'food'"
        v-model="expiryDate"
        type="date"
        :label="t('activities.materialJourney.surplusReturn.expiryLabel')"
      />

      <ETextField
        v-model="notes"
        :label="t('activities.materialJourney.surplusReturn.notesLabel')"
        :placeholder="t('activities.materialJourney.surplusReturn.notesPlaceholder')"
      />

      <EButton variant="primary" :disabled="!canSubmit" :loading="submitting" @click="onSubmit">
        {{ t('activities.materialJourney.surplusReturn.submit') }}
      </EButton>
    </div>

    <div v-if="reports.length" class="material-surplus-return-panel__list-wrap">
      <h3 class="material-surplus-return-panel__subtitle">
        {{ t('activities.materialJourney.surplusReturn.listTitle') }}
      </h3>
      <ul class="material-surplus-return-panel__list">
        <li v-for="report in reports" :key="report.id" class="material-surplus-return-panel__item">
          <div class="material-surplus-return-panel__item-main">
            <span class="material-surplus-return-panel__item-name">
              {{ report.nameFreeText }}
              <span class="text-muted">({{ report.qty }})</span>
            </span>
            <span class="material-surplus-return-panel__item-meta text-muted">
              {{ kindLabel(report) }} — {{ statusLabel(report) }}
              <template v-if="report.expiryDate">
                · {{ t('activities.materialJourney.surplusReturn.expiryShort', { date: report.expiryDate }) }}
              </template>
            </span>
          </div>
          <div v-if="canEdit && report.status === 'open'" class="material-surplus-return-panel__item-actions">
            <EButton
              variant="secondary"
              size="small"
              :disabled="submitting"
              @click="emit('remove', report.id)"
            >
              {{ t('common.delete') }}
            </EButton>
            <EButton
              variant="text"
              size="small"
              :disabled="submitting"
              @click="emit('dismiss', report.id)"
            >
              {{ t('activities.materialJourney.surplusReturn.dismiss') }}
            </EButton>
          </div>
        </li>
      </ul>
    </div>
    <p v-else class="material-surplus-return-panel__empty text-muted">
      {{ t('activities.materialJourney.surplusReturn.empty') }}
    </p>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';

.material-surplus-return-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 12px;
}

.material-surplus-return-panel__title {
  margin: 0;
  font-size: 1rem;
}

.material-surplus-return-panel__hint {
  margin: 0;
  font-size: 0.875rem;
}

.material-surplus-return-panel__form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.material-surplus-return-panel__row {
  display: grid;
  grid-template-columns: minmax(5rem, 7rem) 1fr;
  gap: 10px;
}

.material-surplus-return-panel__subtitle {
  margin: 0 0 8px;
  font-size: 0.9375rem;
}

.material-surplus-return-panel__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.material-surplus-return-panel__item {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}

.material-surplus-return-panel__item-main {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.material-surplus-return-panel__item-name {
  font-weight: 600;
}

.material-surplus-return-panel__item-meta {
  font-size: 0.8125rem;
}

.material-surplus-return-panel__item-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.material-surplus-return-panel__empty {
  margin: 0;
  font-size: 0.875rem;
}
</style>
