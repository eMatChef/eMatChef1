<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityPackContainer } from '@/api/activityContainers'
import type { ActivityPackItem } from '@/api/activityPackItems'
import EButton from '@/components/form/base/EButton.vue'

const props = defineProps<{
  open: boolean
  packItem: ActivityPackItem | null
  containers: ActivityPackContainer[]
  maxQty: number
  qty: number
  submitting: boolean
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  'update:qty': [value: number]
  confirm: [containerId: string]
}>()

const { t } = useI18n()
const selectedContainerId = ref('')

const hasContainers = computed(() => props.containers.length > 0)

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) {
      selectedContainerId.value = ''
      return
    }
    if (props.containers.length === 1) {
      selectedContainerId.value = props.containers[0].id
    }
  },
)

function close(): void {
  emit('update:open', false)
}

function clampQty(raw: number): number {
  let qty = Math.floor(Number(raw)) || 1
  if (qty < 1) qty = 1
  const max = Math.floor(props.maxQty)
  if (max > 0 && qty > max) qty = max
  return qty
}

function setQty(next: number): void {
  emit('update:qty', clampQty(next))
}

function onConfirm(): void {
  if (!selectedContainerId.value || props.submitting || props.maxQty < 1) return
  emit('confirm', selectedContainerId.value)
}
</script>

<template>
  <v-dialog
    :model-value="open"
    fullscreen
    scrollable
    transition="dialog-bottom-transition"
    @update:model-value="emit('update:open', $event)"
  >
    <div v-if="packItem" class="material-journey-sheet material-assign-crate-sheet">
      <header class="material-journey-sheet__header">
        <EButton variant="secondary" size="small" @click="close">
          {{ t('common.close') }}
        </EButton>
        <div class="material-journey-sheet__headline">
          <h2 class="material-journey-sheet__title">{{ packItem.materialName }}</h2>
          <p class="material-journey-sheet__subtitle text-muted">
            {{ t('activities.materialJourney.assignCrate.subtitle') }}
          </p>
        </div>
      </header>

      <div class="material-journey-sheet__body">
        <p v-if="!hasContainers" class="material-assign-crate-sheet__empty text-muted">
          {{ t('activities.materialJourney.assignCrate.empty') }}
        </p>

        <template v-else>
          <p class="material-assign-crate-sheet__hint text-muted">
            {{ t('activities.materialJourney.assignCrate.hint') }}
          </p>

          <div class="material-assign-crate-sheet__list" role="listbox" :aria-label="t('activities.materialJourney.assignCrate.listAria')">
            <button
              v-for="container in containers"
              :key="container.id"
              type="button"
              class="material-assign-crate-sheet__option"
              :class="{ 'material-assign-crate-sheet__option--active': selectedContainerId === container.id }"
              role="option"
              :aria-selected="selectedContainerId === container.id"
              @click="selectedContainerId = container.id"
            >
              {{ container.label }}
            </button>
          </div>

          <div class="material-assign-crate-sheet__field">
            <label class="material-assign-crate-sheet__label" for="assign-crate-qty">
              {{ t('activities.materialJourney.assignCrate.qtyLabel') }}
              <span v-if="maxQty > 0" class="text-muted">({{ t('activities.materialJourney.assignCrate.qtyMax', { max: maxQty }) }})</span>
            </label>
            <input
              id="assign-crate-qty"
              type="number"
              class="material-assign-crate-sheet__input"
              :value="qty"
              min="1"
              :max="maxQty > 0 ? maxQty : undefined"
              :disabled="submitting"
              @input="setQty(Number(($event.target as HTMLInputElement).value))"
            />
          </div>
        </template>
      </div>

      <footer class="material-journey-sheet__footer">
        <EButton
          variant="primary"
          block
          :disabled="!hasContainers || !selectedContainerId || submitting || maxQty < 1"
          :loading="submitting"
          @click="onConfirm"
        >
          {{ t('activities.materialJourney.assignCrate.confirm') }}
        </EButton>
      </footer>
    </div>
  </v-dialog>
</template>

<style scoped>
@import '@/styles/views/activities/material-journey.css';

.material-assign-crate-sheet__empty,
.material-assign-crate-sheet__hint {
  margin: 0 0 16px;
  font-size: 14px;
  line-height: 1.45;
}

.material-assign-crate-sheet__list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}

.material-assign-crate-sheet__option {
  min-height: 48px;
  padding: 12px 14px;
  border-radius: 8px;
  border: 1px solid var(--color-border, #d1d5db);
  background: #fff;
  text-align: left;
  font-size: 15px;
  font-weight: 500;
  color: var(--color-text, #111827);
  cursor: pointer;
}

.material-assign-crate-sheet__option--active {
  border-color: var(--color-primary-light, #10b981);
  box-shadow: 0 0 0 2px var(--color-primary-ring, rgba(16, 185, 129, 0.12));
}

.material-assign-crate-sheet__field {
  margin-bottom: 8px;
}

.material-assign-crate-sheet__label {
  display: block;
  margin-bottom: 6px;
  font-size: 13px;
  font-weight: 500;
}

.material-assign-crate-sheet__input {
  width: 100%;
  min-height: 48px;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid var(--color-border, #d1d5db);
  font-size: 16px;
  box-sizing: border-box;
}
</style>
