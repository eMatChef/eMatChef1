<template>
  <Teleport to="body">
    <div v-if="isOpen" class="nachbuchung-overlay" @click.self="close">
      <div class="nachbuchung-dialog" role="dialog" aria-modal="true" aria-labelledby="nachbuchung-title">
        <div class="nachbuchung-header">
          <h3 id="nachbuchung-title">{{ t('components.activityNachbuchungModal.title') }}</h3>
          <button type="button" class="nachbuchung-close" :aria-label="t('components.activityNachbuchungModal.closeAria')" @click="close">×</button>
        </div>
        <div class="nachbuchung-body">
          <p class="nachbuchung-lead">
            {{ t('components.activityNachbuchungModal.leadPrefix') }}<strong>{{ materialLabel }}</strong>{{ t('components.activityNachbuchungModal.leadSuffix') }}
          </p>
          <div class="nachbuchung-field">
            <label for="nachbuchung-qty">{{ t('components.activityNachbuchungModal.labelQty') }}</label>
            <p v-if="effectivePackSize" class="nachbuchung-ve-note text-muted">
              {{
                t('components.activityNachbuchungModal.packPreset', {
                  unit: packUnitLabel || t('components.activityNachbuchungModal.packUnitFallback'),
                  pieces: effectivePackSize,
                })
              }}
            </p>
            <div class="adjust-qty-row">
              <button type="button" class="btn-qty" :disabled="qty <= 1" @click="qty = Math.max(1, qty - 1)">−</button>
              <input
                id="nachbuchung-qty"
                v-model.number="qty"
                type="number"
                min="1"
                class="form-input nachbuchung-qty-input"
              />
              <button type="button" class="btn-qty" @click="qty++">+</button>
            </div>
            <div
              v-if="effectivePackSize != null && effectivePackSize > 1"
              class="nachbuchung-pack-btns"
            >
              <button
                type="button"
                class="mat-quick-btn mat-set-btn"
                :title="'+1 ' + (packUnitLabel || t('components.activityNachbuchungModal.packUnitFallback'))"
                @click="bumpByPacks(1)"
              >
                +1 {{ packUnitLabel || t('components.activityNachbuchungModal.packUnitFallback') }}
              </button>
              <button
                type="button"
                class="mat-quick-btn mat-set-btn"
                :title="'+5 ' + (packUnitLabel || t('components.activityNachbuchungModal.packUnitFallback'))"
                @click="bumpByPacks(5)"
              >
                +5 {{ packUnitLabel || t('components.activityNachbuchungModal.packUnitFallback') }}
              </button>
              <span class="nachbuchung-pack-hint text-muted">
                {{
                  t('components.activityNachbuchungModal.packEquals', {
                    unit: packUnitLabel || t('components.activityNachbuchungModal.packUnitFallback'),
                    pieces: effectivePackSize,
                  })
                }}
              </span>
            </div>
          </div>
          <p v-if="departmentId && materialItemId" class="nachbuchung-hint text-muted">
            {{ t('components.activityNachbuchungModal.hintWarehouse') }}
            <router-link class="nachbuchung-link" :to="materialDetailPath">{{
              t('components.activityNachbuchungModal.openMaterial')
            }}</router-link>
          </p>
        </div>
        <div class="nachbuchung-footer">
          <button type="button" class="btn btn-outline" :disabled="submitting" @click="close">{{ t('common.cancel') }}</button>
          <button type="button" class="btn btn-primary" :disabled="submitting || qty < 1" @click="submit">
            {{ submitting ? t('components.activityNachbuchungModal.submitting') : t('components.activityNachbuchungModal.submit') }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { addActivityItem } from '@/api/activities'
import { useToast } from '@/composables/useToast'

const props = defineProps<{
  isOpen: boolean
  activityId: string
  departmentId: string
  materialItemId: string
  materialLabel: string
  /** Stück pro Verpackung (Bündel, Kiste, Set …); optional */
  packSize?: number | null
  packUnit?: string | null
}>()

const emit = defineEmits<{
  close: []
  success: []
}>()

const { t } = useI18n()
const toast = useToast()
const qty = ref(1)
const submitting = ref(false)

const materialDetailPath = computed(() => `/${props.departmentId}/materials/${props.materialItemId}`)

const effectivePackSize = computed(() => {
  const n = props.packSize
  if (n == null || !Number.isFinite(n)) return null
  const i = Math.floor(n)
  return i > 1 ? i : null
})

const packUnitLabel = computed(() => (props.packUnit?.trim() ? props.packUnit.trim() : null))

watch(
  () => props.isOpen,
  (open) => {
    if (!open) return
    // Einkauf/Lager oft pro VE (Sack, Bündel …): Standard = Stückzahl einer VE, nicht 1 Stk.
    const ps = effectivePackSize.value
    qty.value = ps != null ? ps : 1
  },
)

function bumpByPacks(packCount: number) {
  const ps = effectivePackSize.value
  if (ps == null || packCount < 1) return
  const add = ps * packCount
  const n = Number(qty.value)
  qty.value = (Number.isFinite(n) ? Math.max(1, Math.floor(n)) : 1) + add
}

function close() {
  emit('close')
}

async function submit() {
  if (qty.value < 1 || submitting.value || !props.materialItemId) return
  submitting.value = true
  try {
    await addActivityItem(props.activityId, {
      material_item_id: props.materialItemId,
      quantity: qty.value,
    })
    emit('success')
    close()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('components.activityNachbuchungModal.submitError'))
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.nachbuchung-overlay {
  position: fixed;
  inset: 0;
  z-index: 2200;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.nachbuchung-dialog {
  width: 100%;
  max-width: 440px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
}

.nachbuchung-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 18px;
  border-bottom: 1px solid #e5e7eb;
}

.nachbuchung-header h3 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
}

.nachbuchung-close {
  border: none;
  background: none;
  font-size: 1.5rem;
  line-height: 1;
  color: #64748b;
  cursor: pointer;
  padding: 4px 8px;
}

.nachbuchung-body {
  padding: 16px 18px;
}

.nachbuchung-lead {
  margin: 0 0 16px;
  font-size: 14px;
  line-height: 1.5;
  color: #334155;
}

.nachbuchung-field label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: #475569;
  margin-bottom: 8px;
}

.nachbuchung-ve-note {
  margin: 0 0 10px;
  font-size: 12px;
  line-height: 1.45;
}

.adjust-qty-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-qty {
  width: 36px;
  height: 38px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
  font-size: 1.1rem;
  cursor: pointer;
}

.nachbuchung-qty-input {
  width: 5rem;
  text-align: center;
}

.nachbuchung-pack-btns {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-top: 10px;
}

.mat-quick-btn.mat-set-btn {
  padding: 6px 10px;
  border-radius: 6px;
  border: 1px solid #86efac;
  background: #f0fdf4;
  color: #166534;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
}

.mat-quick-btn.mat-set-btn:hover {
  background: #dcfce7;
}

.nachbuchung-pack-hint {
  font-size: 12px;
  width: 100%;
}

.nachbuchung-hint {
  margin: 14px 0 0;
  font-size: 13px;
  line-height: 1.45;
}

.nachbuchung-link {
  color: #2563eb;
  font-weight: 500;
}

.nachbuchung-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 12px 18px 16px;
  border-top: 1px solid #e5e7eb;
}
</style>
