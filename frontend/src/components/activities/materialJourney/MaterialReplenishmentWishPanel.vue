<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import EButton from '@/components/form/base/EButton.vue'
import ETextField from '@/components/form/base/ETextField.vue'
import type { ActivityPeriodAvailabilityMaterial } from '@/components/activities/shared/activityAvailabilityMaterial'
import { createAvailabilityMaterialLookupFetcher } from '@/composables/useMaterialLookup'
import { fetchMaterialsAvailableForPeriodByIds } from '@/api/materialAvailabilityPeriod'
import type { ActivityReplenishmentWish } from '@/api/activityReplenishmentWishes'

const props = defineProps<{
  departmentId: string
  activityId: string
  planningStartIso: string | null
  planningEndIso: string | null
  myWishes: ActivityReplenishmentWish[]
  submitting: boolean
}>()

const emit = defineEmits<{
  submit: [
    payload: {
      materialItemId: string
      quantity: number
      notes: string | null
      availabilitySnapshot: Record<string, unknown> | null
    },
  ]
  cancel: [wishId: string]
}>()

const { t } = useI18n()

const matSearch = ref('')
const selectedMaterial = ref<ActivityPeriodAvailabilityMaterial | null>(null)
const quantity = ref(1)
const notes = ref('')
const availability = ref<ActivityPeriodAvailabilityMaterial | null>(null)
const availabilityLoading = ref(false)

const materialLookupFetcher = createAvailabilityMaterialLookupFetcher(() => ({
  departmentId: props.departmentId,
  activityId: props.activityId,
  excludeActivityId: props.activityId,
  startDate: props.planningStartIso ?? undefined,
  endDate: props.planningEndIso ?? undefined,
  source: 'internal',
  internalScope: 'own',
  limit: 20,
}))

const canSubmit = computed(
  () =>
    !!selectedMaterial.value &&
    quantity.value >= 1 &&
    !props.submitting &&
    !availabilityLoading.value,
)

const availabilityHint = computed(() => {
  if (!selectedMaterial.value) return null
  if (availabilityLoading.value) return t('activities.materialJourney.replenishmentWish.checkingAvailability')
  const avail = availability.value?.availableForPeriod
  if (avail == null) return null
  if (avail >= quantity.value) {
    return t('activities.materialJourney.replenishmentWish.availability', { n: avail })
  }
  if (avail > 0) {
    return t('activities.materialJourney.replenishmentWish.partialAvailability', { n: avail })
  }
  return t('activities.materialJourney.replenishmentWish.unavailable')
})

const availabilityClass = computed(() => {
  const avail = availability.value?.availableForPeriod
  if (avail == null) return ''
  if (avail >= quantity.value) return 'material-replenishment-wish-panel__availability--ok'
  if (avail > 0) return 'material-replenishment-wish-panel__availability--partial'
  return 'material-replenishment-wish-panel__availability--none'
})

async function loadAvailability(materialItemId: string): Promise<void> {
  availabilityLoading.value = true
  availability.value = null
  try {
    const rows = await fetchMaterialsAvailableForPeriodByIds({
      departmentId: props.departmentId,
      activityId: props.activityId,
      startDateIso: props.planningStartIso,
      endDateIso: props.planningEndIso,
      materialItemIds: [materialItemId],
      scope: { source: 'internal', internalScope: 'own' },
    })
    availability.value = rows[0] ?? null
  } finally {
    availabilityLoading.value = false
  }
}

function onMaterialSelect(item: ActivityPeriodAvailabilityMaterial): void {
  selectedMaterial.value = item
  matSearch.value = item.name
  void loadAvailability(item.materialItemId)
}

function resetForm(): void {
  selectedMaterial.value = null
  matSearch.value = ''
  quantity.value = 1
  notes.value = ''
  availability.value = null
}

function onSubmit(): void {
  if (!selectedMaterial.value || !canSubmit.value) return
  const snapshot = availability.value
    ? {
        available_for_period: availability.value.availableForPeriod,
        total_stock: availability.value.totalStock,
      }
    : null
  emit('submit', {
    materialItemId: selectedMaterial.value.materialItemId,
    quantity: quantity.value,
    notes: notes.value.trim() || null,
    availabilitySnapshot: snapshot,
  })
}

function wishStatusLabel(wish: ActivityReplenishmentWish): string {
  if (wish.status === 'pending') return t('activities.materialJourney.replenishmentWish.statusPending')
  if (wish.status === 'fulfilled') return t('activities.materialJourney.replenishmentWish.statusFulfilled')
  if (wish.status === 'rejected') return t('activities.materialJourney.replenishmentWish.statusRejected')
  return t('activities.materialJourney.replenishmentWish.statusCancelled')
}
defineExpose({ resetForm })
</script>

<template>
  <div class="material-replenishment-wish-panel section-card">
    <h2 class="material-replenishment-wish-panel__title">
      {{ t('activities.materialJourney.replenishmentWish.title') }}
    </h2>

    <MaterialLookupInput
      v-model="matSearch"
      :fetcher="materialLookupFetcher"
      :min-chars="1"
      :debounce-ms="240"
      :max-suggestions="15"
      :placeholder="t('activities.materialJourney.replenishmentWish.searchPlaceholder')"
      input-class="form-input"
      :get-result-key="(item: ActivityPeriodAvailabilityMaterial) => item.materialItemId"
      :get-result-label="(item: ActivityPeriodAvailabilityMaterial) => item.name"
      @select="onMaterialSelect"
    />

    <div v-if="selectedMaterial" class="material-replenishment-wish-panel__form">
      <p class="material-replenishment-wish-panel__selected">
        {{ selectedMaterial.name }}
      </p>

      <ETextField
        v-model.number="quantity"
        type="number"
        :label="t('activities.materialJourney.replenishmentWish.quantityLabel')"
        :min="1"
        inputmode="numeric"
      />

      <p
        v-if="availabilityHint"
        class="material-replenishment-wish-panel__availability"
        :class="availabilityClass"
      >
        {{ availabilityHint }}
      </p>

      <ETextField
        v-model="notes"
        :label="t('activities.materialJourney.replenishmentWish.notesLabel')"
        :placeholder="t('activities.materialJourney.replenishmentWish.notesPlaceholder')"
      />

      <EButton variant="primary" :disabled="!canSubmit" :loading="submitting" @click="onSubmit">
        {{ t('activities.materialJourney.replenishmentWish.submit') }}
      </EButton>
    </div>

    <div v-if="myWishes.length" class="material-replenishment-wish-panel__mine">
      <h3 class="material-replenishment-wish-panel__subtitle">
        {{ t('activities.materialJourney.replenishmentWish.myWishes') }}
      </h3>
      <ul class="material-replenishment-wish-panel__list">
        <li v-for="wish in myWishes" :key="wish.id" class="material-replenishment-wish-panel__item">
          <span>{{ wish.materialName }} ({{ wish.quantityRequested }})</span>
          <span class="text-muted"> — {{ wishStatusLabel(wish) }}</span>
          <EButton
            v-if="wish.status === 'pending'"
            variant="secondary"
            size="small"
            :disabled="submitting"
            @click="emit('cancel', wish.id)"
          >
            {{ t('common.cancel') }}
          </EButton>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';

.material-replenishment-wish-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 12px;
}

.material-replenishment-wish-panel__title {
  margin: 0;
  font-size: 1rem;
}

.material-replenishment-wish-panel__subtitle {
  margin: 0 0 8px;
  font-size: 0.9375rem;
}

.material-replenishment-wish-panel__form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.material-replenishment-wish-panel__selected {
  margin: 0;
  font-weight: 600;
}

.material-replenishment-wish-panel__availability--ok {
  color: rgb(var(--v-theme-success));
}

.material-replenishment-wish-panel__availability--partial {
  color: rgb(var(--v-theme-warning));
}

.material-replenishment-wish-panel__availability--none {
  color: rgb(var(--v-theme-error));
}

.material-replenishment-wish-panel__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.material-replenishment-wish-panel__item {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
</style>
