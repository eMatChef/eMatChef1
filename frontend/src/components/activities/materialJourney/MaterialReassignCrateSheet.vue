<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { MaterialJourneyAccordionLine } from '@/components/activities/materialJourneyAccordionLines'
import type { ReassignCrateConfirmPayload } from '@/composables/useMaterialJourneyCrateTransfer'
import EButton from '@/components/form/base/EButton.vue'

const props = defineProps<{
  open: boolean
  sourceContainer: ActivityPackContainer | null
  sourceLine?: MaterialJourneyAccordionLine | null
  targetContainers: ActivityPackContainer[]
  submitting: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: [payload: ReassignCrateConfirmPayload]
}>()

const { t } = useI18n()
const selectedContainerId = ref('')
const quantity = ref(1)

const maxQty = computed(() => {
  const line = props.sourceLine
  if (!line) return 1
  const packed = line.maxReassignQty ?? 0
  if (packed > 0) return packed
  return Math.max(1, line.quantity)
})

const canSubmit = computed(
  () =>
    !!selectedContainerId.value &&
    !props.submitting &&
    props.targetContainers.length > 0 &&
    quantity.value >= 1 &&
    quantity.value <= maxQty.value,
)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      selectedContainerId.value = ''
      quantity.value = 1
      return
    }
    quantity.value = maxQty.value
    if (props.targetContainers.length === 1) {
      selectedContainerId.value = props.targetContainers[0].id
    }
  },
)

watch(maxQty, (max) => {
  if (!props.open) return
  if (quantity.value > max) quantity.value = max
  if (quantity.value < 1) quantity.value = Math.min(1, max)
})

function close(): void {
  emit('update:open', false)
}

function onQtyInput(event: Event): void {
  const raw = parseInt((event.target as HTMLInputElement).value, 10)
  let next = Number.isFinite(raw) ? raw : quantity.value
  if (next < 1) next = 1
  if (next > maxQty.value) next = maxQty.value
  quantity.value = next
}

function onConfirm(): void {
  if (!canSubmit.value) return
  emit('confirm', {
    targetContainerId: selectedContainerId.value,
    quantity: quantity.value,
  })
}
</script>

<template>
  <v-dialog
    :model-value="open"
    max-width="480"
    scrollable
    @update:model-value="emit('update:open', $event)"
  >
    <div v-if="sourceContainer && sourceLine" class="material-reassign-crate-sheet">
      <header class="material-reassign-crate-sheet__header">
        <h2 class="material-reassign-crate-sheet__title">
          {{ t('activities.materialJourney.reassignCrate.title') }}
        </h2>
        <p class="material-reassign-crate-sheet__subtitle text-muted">
          {{ t('activities.materialJourney.reassignCrate.subtitleItem', { name: sourceLine.name }) }}
        </p>
        <p class="material-reassign-crate-sheet__from text-muted">
          {{ t('activities.materialJourney.reassignCrate.fromCrate', { label: sourceContainer.label }) }}
        </p>
      </header>

      <div class="material-reassign-crate-sheet__body">
        <label class="material-reassign-crate-sheet__field">
          <span class="material-reassign-crate-sheet__label">
            {{ t('activities.materialJourney.reassignCrate.targetLabel') }}
          </span>
          <div
            class="material-reassign-crate-sheet__list"
            role="listbox"
            :aria-label="t('activities.materialJourney.reassignCrate.listAria')"
          >
            <button
              v-for="container in targetContainers"
              :key="container.id"
              type="button"
              class="material-reassign-crate-sheet__option"
              :class="{ 'material-reassign-crate-sheet__option--active': selectedContainerId === container.id }"
              role="option"
              :aria-selected="selectedContainerId === container.id"
              @click="selectedContainerId = container.id"
            >
              {{ container.label }}
            </button>
          </div>
        </label>

        <label class="material-reassign-crate-sheet__field">
          <span class="material-reassign-crate-sheet__label">
            {{ t('activities.materialJourney.reassignCrate.quantityLabel') }}
          </span>
          <div class="material-reassign-crate-sheet__qty-row">
            <input
              :value="quantity"
              type="number"
              min="1"
              :max="maxQty"
              class="material-reassign-crate-sheet__qty-input"
              @input="onQtyInput"
            />
            <span class="material-reassign-crate-sheet__qty-max text-muted">
              {{ t('activities.materialJourney.reassignCrate.quantityMax', { max: maxQty }) }}
            </span>
          </div>
        </label>
      </div>

      <footer class="material-reassign-crate-sheet__footer">
        <EButton variant="secondary" @click="close">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :disabled="!canSubmit" :loading="submitting" @click="onConfirm">
          {{ t('activities.materialJourney.reassignCrate.confirmItem') }}
        </EButton>
      </footer>
    </div>
  </v-dialog>
</template>

<style scoped>
.material-reassign-crate-sheet {
  padding: 20px 20px 16px;
  background: rgb(var(--v-theme-surface));
}

.material-reassign-crate-sheet__header {
  margin-bottom: 16px;
}

.material-reassign-crate-sheet__title {
  margin: 0 0 6px;
  font-size: 1.125rem;
  font-weight: 600;
}

.material-reassign-crate-sheet__subtitle,
.material-reassign-crate-sheet__from {
  margin: 0 0 4px;
  font-size: 0.9375rem;
}

.material-reassign-crate-sheet__body {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.material-reassign-crate-sheet__field {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.material-reassign-crate-sheet__label {
  font-size: 0.8125rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), 0.65);
}

.material-reassign-crate-sheet__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 220px;
  overflow-y: auto;
}

.material-reassign-crate-sheet__option {
  display: block;
  width: 100%;
  padding: 12px 14px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  background: rgb(var(--v-theme-surface));
  text-align: left;
  font: inherit;
  cursor: pointer;
}

.material-reassign-crate-sheet__option--active {
  border-color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.08);
}

.material-reassign-crate-sheet__qty-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.material-reassign-crate-sheet__qty-input {
  width: 5rem;
  padding: 8px 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  font: inherit;
}

.material-reassign-crate-sheet__qty-max {
  font-size: 0.875rem;
}

.material-reassign-crate-sheet__footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
