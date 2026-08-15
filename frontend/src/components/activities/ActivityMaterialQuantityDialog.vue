<template>
  <Teleport to="body">
    <div class="modal-overlay activity-mat-qty-overlay" @click.self="cancel">
      <div class="modal-dialog activity-mat-qty-dialog">
        <div class="amqd-head">
          <h3>{{ t('activities.materialAvailability.quantityDialogTitle') }}</h3>
          <p class="amqd-intro text-muted">
            {{ t('activities.materialAvailability.quantityDialogIntro', { name: materialName }) }}
          </p>
        </div>

        <div class="amqd-field">
          <label class="amqd-label" for="activity-mat-qty-input">
            {{ t('activities.materialAvailability.quantityDialogLabel') }}
          </label>
          <input
            id="activity-mat-qty-input"
            ref="inputRef"
            v-model.number="quantity"
            type="number"
            class="form-input amqd-input"
            :min="1"
            :max="maxQuantity"
            @keydown.enter.prevent="confirm"
          />
          <p class="amqd-hint text-muted">
            {{ t('activities.materialAvailability.quantityDialogMax', { max: maxQuantity }) }}
          </p>
          <p v-if="packHint" class="amqd-pack-hint text-muted">{{ packHint }}</p>
        </div>

        <div class="modal-actions amqd-actions">
          <button type="button" class="btn-secondary btn-sm" @click="cancel">
            {{ t('common.cancel') }}
          </button>
          <button type="button" class="btn-primary btn-sm" :disabled="!isValid" @click="confirm">
            {{ t('activities.materialAvailability.quantityDialogConfirm', { n: clampedQuantity }) }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  materialName: string
  maxQuantity: number
  packSize?: number | null
  packUnit?: string | null
}>()

const emit = defineEmits<{
  confirm: [quantity: number]
  cancel: []
}>()

const { t } = useI18n()

const inputRef = ref<HTMLInputElement | null>(null)
const quantity = ref(1)

const clampedQuantity = computed(() => {
  const raw = Number(quantity.value)
  if (!Number.isFinite(raw)) return 1
  return Math.min(props.maxQuantity, Math.max(1, Math.floor(raw)))
})

const isValid = computed(() => clampedQuantity.value >= 1 && clampedQuantity.value <= props.maxQuantity)

const packHint = computed(() => {
  const ps = props.packSize
  if (!ps || ps <= 1) return ''
  const unit = (props.packUnit || t('activities.materialAvailability.packUnitSet')).trim()
  return t('activities.materialAvailability.quantityDialogPackHint', { n: ps, unit })
})

function confirm() {
  if (!isValid.value) return
  emit('confirm', clampedQuantity.value)
}

function cancel() {
  emit('cancel')
}

onMounted(() => {
  inputRef.value?.focus()
  inputRef.value?.select()
})
</script>

<style scoped>
.activity-mat-qty-overlay {
  /* Über Tour-Dimmer (10040), unter Tour-Karte (10060) — Mengenwahl während Spotlight nutzbar */
  z-index: 10055;
}

.activity-mat-qty-dialog {
  max-width: 420px;
  width: 100%;
}

.amqd-head h3 {
  margin: 0;
  font-size: 1.05rem;
}

.amqd-intro {
  margin: 0.35rem 0 0;
  font-size: 0.84rem;
  line-height: 1.4;
}

.amqd-field {
  margin-top: 1rem;
}

.amqd-label {
  display: block;
  font-size: 0.84rem;
  font-weight: 600;
  margin-bottom: 0.35rem;
}

.amqd-input {
  width: 100%;
  max-width: 8rem;
  font-variant-numeric: tabular-nums;
}

.amqd-hint,
.amqd-pack-hint {
  margin: 0.35rem 0 0;
  font-size: 0.78rem;
}

.amqd-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1.1rem;
}

.text-muted {
  color: #6b7280;
}
</style>
