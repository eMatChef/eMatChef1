<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { MaterialJourneyAccordionLine } from '@/components/activities/materialJourneyAccordionLines'
import type { LooseTakeConfirmPayload } from '@/composables/useMaterialJourneyLooseTake'
import EButton from '@/components/form/base/EButton.vue'

const props = defineProps<{
  open: boolean
  sourceContainer: ActivityPackContainer | null
  sourceLine: MaterialJourneyAccordionLine | null
  maxQty: number
  submitting: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: [payload: LooseTakeConfirmPayload]
}>()

const { t } = useI18n()
const quantity = ref(1)

const effectiveMax = computed(() => Math.max(0, props.maxQty))

const canSubmit = computed(
  () =>
    !props.submitting &&
    props.sourceLine != null &&
    effectiveMax.value > 0 &&
    quantity.value >= 1 &&
    quantity.value <= effectiveMax.value,
)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      quantity.value = 1
      return
    }
    quantity.value = Math.max(1, effectiveMax.value)
  },
)

watch(effectiveMax, (max) => {
  if (!props.open) return
  if (quantity.value > max) quantity.value = Math.max(1, max)
})

function close(): void {
  emit('update:open', false)
}

function onQtyInput(event: Event): void {
  const raw = parseInt((event.target as HTMLInputElement).value, 10)
  let next = Number.isFinite(raw) ? raw : quantity.value
  if (next < 1) next = 1
  if (next > effectiveMax.value) next = effectiveMax.value
  quantity.value = next
}

function onConfirm(): void {
  if (!canSubmit.value) return
  emit('confirm', { quantity: quantity.value })
}
</script>

<template>
  <v-dialog
    :model-value="open"
    max-width="480"
    scrollable
    @update:model-value="emit('update:open', $event)"
  >
    <div v-if="sourceContainer && sourceLine" class="material-loose-take-sheet">
      <header class="material-loose-take-sheet__header">
        <h2 class="material-loose-take-sheet__title">
          {{ t('activities.materialJourney.looseTake.title') }}
        </h2>
        <p class="material-loose-take-sheet__subtitle text-muted">
          {{ t('activities.materialJourney.looseTake.subtitle', { name: sourceLine.name }) }}
        </p>
        <p class="material-loose-take-sheet__from text-muted">
          {{ t('activities.materialJourney.looseTake.fromCrate', { label: sourceContainer.label }) }}
        </p>
      </header>

      <div class="material-loose-take-sheet__body">
        <label class="material-loose-take-sheet__field">
          <span class="material-loose-take-sheet__label">
            {{ t('activities.materialJourney.looseTake.quantityLabel') }}
          </span>
          <div class="material-loose-take-sheet__qty-row">
            <input
              :value="quantity"
              type="number"
              min="1"
              :max="effectiveMax"
              class="material-loose-take-sheet__qty-input"
              @input="onQtyInput"
            />
            <span class="material-loose-take-sheet__qty-max text-muted">
              {{ t('activities.materialJourney.looseTake.quantityMax', { max: effectiveMax }) }}
            </span>
          </div>
        </label>
        <p class="material-loose-take-sheet__hint text-muted">
          {{ t('activities.materialJourney.looseTake.hint') }}
        </p>
      </div>

      <footer class="material-loose-take-sheet__footer">
        <EButton variant="secondary" @click="close">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :disabled="!canSubmit" :loading="submitting" @click="onConfirm">
          {{ t('activities.materialJourney.looseTake.confirm') }}
        </EButton>
      </footer>
    </div>
  </v-dialog>
</template>

<style scoped>
.material-loose-take-sheet {
  padding: 20px 20px 16px;
  background: rgb(var(--v-theme-surface));
}

.material-loose-take-sheet__header {
  margin-bottom: 16px;
}

.material-loose-take-sheet__title {
  margin: 0 0 6px;
  font-size: 1.125rem;
  font-weight: 600;
}

.material-loose-take-sheet__subtitle,
.material-loose-take-sheet__from,
.material-loose-take-sheet__hint {
  margin: 0 0 4px;
  font-size: 0.9375rem;
}

.material-loose-take-sheet__body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.material-loose-take-sheet__field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.material-loose-take-sheet__label {
  font-size: 0.8125rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.material-loose-take-sheet__qty-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.material-loose-take-sheet__qty-input {
  width: 5rem;
  padding: 8px 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  font: inherit;
}

.material-loose-take-sheet__qty-max {
  font-size: 0.875rem;
}

.material-loose-take-sheet__footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
