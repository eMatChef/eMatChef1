<template>
  <Teleport to="body">
    <div v-if="isOpen" class="consumption-modal-overlay js-order-modal-overlay" @click.self="closeAsCancel">
      <div
        class="consumption-modal-dialog js-order-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="js-order-modal-title"
      >
        <div class="consumption-modal-header">
          <h3 id="js-order-modal-title">{{ t('activities.jsMaterial.order.modalTitle') }}</h3>
          <button
            type="button"
            class="consumption-modal-close"
            :aria-label="t('activities.jsMaterial.order.closeAria')"
            @click="closeAsCancel"
          >
            ×
          </button>
        </div>

        <div class="consumption-modal-body js-order-modal-body">
          <div v-if="loading" class="consumption-modal-loading text-muted">
            {{ t('activities.jsMaterial.order.loading') }}
          </div>
          <div v-else-if="loadError" class="js-order-load-error" role="alert">
            {{ loadError }}
          </div>
          <template v-else-if="order">
            <p class="field-hint text-muted js-order-modal-intro">
              {{ t('activities.jsMaterial.order.modalIntro') }}
            </p>

            <section class="js-order-block">
              <h4>{{ t('activities.jsMaterial.order.block1Title') }}</h4>
              <div class="js-order-grid">
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.firstName') }}</label>
                  <input v-model="form.block1.first_name" type="text" class="form-input" @input="markOverridden('block1', 'first_name')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.lastName') }}</label>
                  <input v-model="form.block1.last_name" type="text" class="form-input" @input="markOverridden('block1', 'last_name')" />
                </div>
                <div class="js-order-field span-2">
                  <label>{{ t('activities.jsMaterial.order.fields.email') }}</label>
                  <input v-model="form.block1.email" type="email" class="form-input" @input="markOverridden('block1', 'email')" />
                </div>
                <div class="js-order-field span-2">
                  <label>{{ t('activities.jsMaterial.order.fields.address') }}</label>
                  <input v-model="form.block1.address" type="text" class="form-input" @input="markOverridden('block1', 'address')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.postalCode') }}</label>
                  <input v-model="form.block1.postal_code" type="text" class="form-input" @input="markOverridden('block1', 'postal_code')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.city') }}</label>
                  <input v-model="form.block1.city" type="text" class="form-input" @input="markOverridden('block1', 'city')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.canton') }}</label>
                  <input v-model="form.block1.canton" type="text" class="form-input" @input="markOverridden('block1', 'canton')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.phone') }}</label>
                  <input v-model="form.block1.phone" type="text" class="form-input" @input="markOverridden('block1', 'phone')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.personNr') }}</label>
                  <input v-model="form.block1.person_nr" type="text" class="form-input" @input="markOverridden('block1', 'person_nr')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.offerNumber') }}</label>
                  <input v-model="form.block1.offer_number" type="text" class="form-input" @input="markOverridden('block1', 'offer_number')" />
                </div>
              </div>
            </section>

            <section class="js-order-block">
              <h4>{{ t('activities.jsMaterial.order.block2Title') }}</h4>
              <div class="js-order-grid">
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.courseType') }}</label>
                  <select v-model="form.block2.course_type" class="form-input" @change="markOverridden('block2', 'course_type')">
                    <option value="">{{ t('activities.jsMaterial.order.courseTypeChoose') }}</option>
                    <option value="lager">{{ t('activities.jsMaterial.order.courseTypeLager') }}</option>
                    <option value="kaderbildung">{{ t('activities.jsMaterial.order.courseTypeKader') }}</option>
                  </select>
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.participantCountLabel') }}</label>
                  <input
                    v-model.number="participantCountField"
                    type="number"
                    min="1"
                    step="1"
                    class="form-input"
                    @input="markOverridden('block2', 'participant_count')"
                  />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.deliveryDate') }}</label>
                  <input v-model="form.block2.delivery_date" type="date" class="form-input" @input="markOverridden('block2', 'delivery_date')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.returnDate') }}</label>
                  <input v-model="form.block2.return_date" type="date" class="form-input" @input="markOverridden('block2', 'return_date')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.coachFirstName') }}</label>
                  <input v-model="form.block2.coach_first_name" type="text" class="form-input" @input="markOverridden('block2', 'coach_first_name')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.coachLastName') }}</label>
                  <input v-model="form.block2.coach_last_name" type="text" class="form-input" @input="markOverridden('block2', 'coach_last_name')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.coachPersonNr') }}</label>
                  <input v-model="form.block2.coach_person_nr" type="text" class="form-input" @input="markOverridden('block2', 'coach_person_nr')" />
                </div>
              </div>
            </section>

            <section class="js-order-block">
              <h4>{{ t('activities.jsMaterial.order.block3Title') }}</h4>
              <div class="js-order-delivery-type">
                <label class="js-order-radio">
                  <input v-model="deliveryType" type="radio" value="franko" />
                  {{ t('settings.activitySettings.jsDeliveryOptions.franko') }}
                </label>
                <label class="js-order-radio">
                  <input v-model="deliveryType" type="radio" value="pickup_thun" />
                  {{ t('settings.activitySettings.jsDeliveryOptions.pickupThun') }}
                </label>
              </div>
              <div class="js-order-grid">
                <div class="js-order-field span-2">
                  <label>{{ t('activities.jsMaterial.order.fields.venueName') }}</label>
                  <input v-model="form.block3.venue_name" type="text" class="form-input" @input="markOverridden('block3', 'venue_name')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.contactFirstName') }}</label>
                  <input v-model="form.block3.contact_first_name" type="text" class="form-input" @input="markOverridden('block3', 'contact_first_name')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.contactLastName') }}</label>
                  <input v-model="form.block3.contact_last_name" type="text" class="form-input" @input="markOverridden('block3', 'contact_last_name')" />
                </div>
                <div class="js-order-field span-2">
                  <label>{{ t('activities.jsMaterial.order.fields.address') }}</label>
                  <input v-model="form.block3.address" type="text" class="form-input" @input="markOverridden('block3', 'address')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.postalCode') }}</label>
                  <input v-model="form.block3.postal_code" type="text" class="form-input" @input="markOverridden('block3', 'postal_code')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.city') }}</label>
                  <input v-model="form.block3.city" type="text" class="form-input" @input="markOverridden('block3', 'city')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.canton') }}</label>
                  <input v-model="form.block3.canton" type="text" class="form-input" @input="markOverridden('block3', 'canton')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.deliveryPhone') }}</label>
                  <input v-model="form.block3.delivery_phone" type="text" class="form-input" @input="markOverridden('block3', 'delivery_phone')" />
                </div>
                <div class="js-order-field">
                  <label>{{ t('activities.jsMaterial.order.fields.campLeaderPhone') }}</label>
                  <input v-model="form.block3.camp_leader_phone" type="text" class="form-input" @input="markOverridden('block3', 'camp_leader_phone')" />
                </div>
              </div>
            </section>

            <p class="field-hint text-muted js-order-phase-hint">
              {{ t('activities.jsMaterial.order.block4PhaseHint') }}
            </p>
          </template>
        </div>

        <div class="consumption-modal-footer">
          <EButton variant="secondary" :disabled="saving" @click="closeAsCancel">
            {{ t('common.cancel') }}
          </EButton>
          <EButton variant="secondary" :disabled="loading || saving || !order" @click="onPrefill">
            {{ t('activities.jsMaterial.order.prefillButton') }}
          </EButton>
          <EButton variant="primary" :loading="saving" :disabled="loading || !order || readOnly" @click="onSave">
            {{ t('activities.jsMaterial.order.saveButton') }}
          </EButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EButton } from '@/components/form/base'
import {
  EMPTY_JS_ORDER_FORM,
  loadOrCreateActivityJsOrder,
  prefillActivityJsOrder,
  saveActivityJsOrder,
  type ActivityJsOrderApi,
  type JsOrderDeliveryType,
  type JsOrderFormData,
} from '@/api/activityJsOrder'

type JsOrderFormBlockKey = 'block1' | 'block2' | 'block3'

const props = defineProps<{
  isOpen: boolean
  activityId: string
  readOnly?: boolean
}>()

const emit = defineEmits<{
  close: []
  saved: [order: ActivityJsOrderApi]
}>()

const { t } = useI18n()
const toast = useToast()

const loading = ref(false)
const saving = ref(false)
const loadError = ref('')
const order = ref<ActivityJsOrderApi | null>(null)
const form = reactive<JsOrderFormData>(structuredClone(EMPTY_JS_ORDER_FORM))
const deliveryType = ref<JsOrderDeliveryType>('franko')

const participantCountField = computed({
  get: () => form.block2.participant_count ?? '',
  set(v: number | string) {
    if (v === '' || v == null) {
      form.block2.participant_count = null
      return
    }
    const n = typeof v === 'number' ? v : Number.parseInt(String(v), 10)
    form.block2.participant_count = Number.isFinite(n) && n >= 1 ? n : null
  },
})

function cloneForm(source: JsOrderFormData): JsOrderFormData {
  return structuredClone(source)
}

function applyOrderToForm(next: ActivityJsOrderApi) {
  order.value = next
  Object.assign(form, cloneForm(next.form_data))
  deliveryType.value = next.delivery_type
  if (next.participant_count != null && next.participant_count >= 1) {
    form.block2.participant_count = next.participant_count
  }
}

function markOverridden(block: JsOrderFormBlockKey, field: string) {
  const list = form[block].user_overridden
  if (!list.includes(field)) {
    list.push(field)
  }
}

async function loadOrder() {
  if (!props.activityId) return
  loading.value = true
  loadError.value = ''
  try {
    const loaded = await loadOrCreateActivityJsOrder(props.activityId)
    applyOrderToForm(loaded)
  } catch (err) {
    console.error(err)
    loadError.value = t('activities.jsMaterial.order.loadError')
    order.value = null
  } finally {
    loading.value = false
  }
}

async function onPrefill() {
  if (!props.activityId) return
  saving.value = true
  try {
    const next = await prefillActivityJsOrder(props.activityId)
    applyOrderToForm(next)
    toast.success(t('activities.jsMaterial.order.prefillSuccess'))
  } catch (err) {
    console.error(err)
    toast.error(t('activities.jsMaterial.order.prefillError'))
  } finally {
    saving.value = false
  }
}

async function onSave() {
  if (!props.activityId || !order.value || props.readOnly) return
  saving.value = true
  try {
    const participant =
      form.block2.participant_count != null && form.block2.participant_count >= 1
        ? form.block2.participant_count
        : null
    const saved = await saveActivityJsOrder(props.activityId, {
      form_data: cloneForm(form),
      participant_count: participant,
      delivery_type: deliveryType.value,
      status: 'draft',
    })
    applyOrderToForm(saved)
    toast.success(t('activities.jsMaterial.order.saveSuccess'))
    emit('saved', saved)
  } catch (err) {
    console.error(err)
    toast.error(t('activities.jsMaterial.order.saveError'))
  } finally {
    saving.value = false
  }
}

function closeAsCancel() {
  emit('close')
}

watch(
  () => props.isOpen,
  (open) => {
    if (typeof document !== 'undefined') {
      document.body.style.overflow = open ? 'hidden' : ''
    }
    if (open) {
      void loadOrder()
    }
  },
)

onBeforeUnmount(() => {
  if (typeof document !== 'undefined') {
    document.body.style.overflow = ''
  }
})
</script>

<style scoped>
.consumption-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 2100;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.consumption-modal-dialog {
  width: 100%;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}

.consumption-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 18px;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.consumption-modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
}

.consumption-modal-close {
  border: none;
  background: none;
  font-size: 1.5rem;
  line-height: 1;
  color: #64748b;
  cursor: pointer;
  padding: 4px 8px;
}

.consumption-modal-close:hover {
  color: #0f172a;
}

.consumption-modal-body {
  padding: 16px 18px;
  overflow-y: auto;
  flex: 1;
  min-height: 0;
}

.consumption-modal-loading {
  font-size: 13px;
  margin-bottom: 10px;
}

.consumption-modal-footer {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px;
  padding: 12px 18px 16px;
  border-top: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.js-order-modal-dialog {
  width: min(920px, calc(100vw - 32px));
  max-width: none;
  max-height: calc(100vh - 32px);
}

.js-order-modal-body {
  max-height: calc(100vh - 180px);
  overflow-y: auto;
}

.js-order-modal-intro {
  margin: 0 0 16px;
}

.js-order-block {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e5e7eb;
}

.js-order-block:last-of-type {
  border-bottom: none;
}

.js-order-block h4 {
  margin: 0 0 12px;
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

.js-order-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px 16px;
}

.js-order-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.js-order-field.span-2 {
  grid-column: span 2;
}

.js-order-field label {
  font-size: 12px;
  font-weight: 500;
  color: #374151;
}

.js-order-delivery-type {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 12px;
}

.js-order-radio {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #374151;
}

.js-order-load-error {
  padding: 12px;
  border-radius: 8px;
  background: #fef2f2;
  color: #991b1b;
  font-size: 13px;
}

.js-order-phase-hint {
  margin: 0;
}
</style>
