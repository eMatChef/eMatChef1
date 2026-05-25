<template>
  <Teleport to="body">
    <div v-if="isOpen" class="consumption-modal-overlay" @click.self="closeAsCancel">
      <div class="consumption-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="consumption-modal-title">
        <div class="consumption-modal-header">
          <h3 id="consumption-modal-title">{{
            isEditMode
              ? t('components.activityConsumptionModal.titleEdit')
              : t('components.activityConsumptionModal.title')
          }}</h3>
          <button type="button" class="consumption-modal-close" :aria-label="t('components.activityConsumptionModal.closeAria')" @click="closeAsCancel">×</button>
        </div>
        <div class="consumption-modal-body">
          <div v-if="loadingLimits" class="consumption-modal-loading text-muted">{{ t('components.activityConsumptionModal.loadingLimits') }}</div>
          <div class="consumption-modal-material">
            {{ displayMaterialLine }}
          </div>
          <p v-if="!loadingLimits && preset" class="consumption-modal-limits text-muted">
            {{
              t('components.activityConsumptionModal.limitsSummary', {
                booked: bookedTotal,
                consumed: consumedTotal,
                remaining: maxRemaining,
              })
            }}
          </p>
          <p v-if="!loadingLimits && maxRemaining < 1" class="consumption-modal-warn">
            {{ t('components.activityConsumptionModal.warnNoMore') }}
          </p>
          <div
            v-if="!loadingLimits && maxRemaining > 0 && canAddActivityMaterial"
            class="consumption-modal-nachlieferung"
          >
            <button type="button" class="link-btn" @click="emit('requestNachbuchung')">
              {{ t('components.activityConsumptionModal.requestNachbuchung') }}
            </button>
          </div>
          <template v-if="!loadingLimits && (maxRemaining > 0 || isEditMode)">
          <div class="consumption-modal-field">
            <label for="consumption-qty">{{ t('components.activityConsumptionModal.labelQty') }}</label>
            <div class="adjust-qty-row">
              <button
                type="button"
                class="btn-qty"
                :disabled="maxRemaining < 1 || qty <= 0"
                @click="bumpQty(-1)"
              >
                −
              </button>
              <input
                id="consumption-qty"
                v-model.number="qty"
                type="number"
                :min="0"
                :max="isEditMode ? maxQtyForEdit : maxRemaining > 0 ? maxRemaining : 0"
                class="form-input adjust-qty-input"
                @change="clampQtyInput"
              />
              <button
                type="button"
                class="btn-qty"
                :disabled="(isEditMode ? maxQtyForEdit : maxRemaining) < 1 || qty >= (isEditMode ? maxQtyForEdit : maxRemaining)"
                @click="bumpQty(1)"
              >
                +
              </button>
            </div>
            <div
              v-if="preset?.packSize != null && preset.packSize > 1 && maxRemaining > 0"
              class="pack-edit-set-btns"
            >
              <button
                type="button"
                class="mat-quick-btn mat-set-btn"
                :title="'1 ' + (preset?.packUnit || 'Set')"
                @click="applySetMultiple(1)"
              >
                1 {{ preset?.packUnit || 'Set' }}
              </button>
              <button
                type="button"
                class="mat-quick-btn mat-set-btn"
                :title="'2 ' + (preset?.packUnit || 'Sets')"
                @click="applySetMultiple(2)"
              >
                2 {{ preset?.packUnit || 'Sets' }}
              </button>
              <button
                type="button"
                class="mat-quick-btn mat-set-btn"
                :title="'5 ' + (preset?.packUnit || 'Sets')"
                @click="applySetMultiple(5)"
              >
                5 {{ preset?.packUnit || 'Sets' }}
              </button>
              <span class="pack-edit-set-hint">
                1 {{ preset?.packUnit || 'Set' }} = {{ preset?.packSize }} Stk.
              </span>
            </div>
          </div>
          <div class="consumption-modal-field">
            <label for="consumption-notes">{{ t('components.activityConsumptionModal.labelNotes') }}</label>
            <textarea
              id="consumption-notes"
              v-model="notes"
              class="form-input form-textarea"
              rows="3"
              :placeholder="t('components.activityConsumptionModal.notesPlaceholder')"
            />
          </div>
          </template>
          <div
            v-if="!loadingLimits && maxRemaining < 1 && canAddActivityMaterial"
            class="consumption-modal-nachbuchung-actions"
          >
            <p class="text-muted text-sm">
              {{ t('components.activityConsumptionModal.nachbuchungHint') }}
            </p>
            <button type="button" class="btn btn-primary" @click="emit('requestNachbuchung')">
              {{ t('components.activityConsumptionModal.nachbuchungCta') }}
            </button>
          </div>
          <p
            v-else-if="!loadingLimits && maxRemaining < 1 && !canAddActivityMaterial"
            class="consumption-modal-no-perm text-muted text-sm"
          >
            {{ t('components.activityConsumptionModal.noPermissionHint') }}
          </p>
        </div>
        <div class="consumption-modal-footer">
          <button
            v-if="isEditMode"
            type="button"
            class="btn btn-outline btn-danger-outline"
            :disabled="submitting || deleting"
            @click="confirmDelete"
          >
            {{ deleting ? t('components.activityConsumptionModal.deleting') : t('components.activityConsumptionModal.delete') }}
          </button>
          <button type="button" class="btn btn-outline" :disabled="submitting || deleting" @click="onFooterOutlineClick">{{ closeFooterLabel }}</button>
          <button
            v-if="maxRemaining > 0 || isEditMode"
            type="button"
            class="btn btn-success"
            :disabled="submitting || deleting || !canSubmit || loadingLimits"
            @click="submit"
          >
            {{ submitButtonLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getPackItems } from '@/api/activityPackItems'
import {
  createActivityIssue,
  deleteActivityConsumptionIssue,
  getActivityIssues,
  getActivityItems,
  updateActivityConsumptionIssue,
} from '@/api/activities'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'

export interface ConsumptionModalPreset {
  materialItemId: string
  materialName: string
  packSize: number | null
  packUnit: string | null
  linkedContainerLabel?: string | null
  /** Retour-Kontext: Menge die ohne Verbrauch retourniert wird */
  returnQty?: number | null
  /** Bestehende Verbrauchsmeldung bearbeiten */
  editIssueId?: string | null
  editQuantity?: number | null
  editDescription?: string | null
}

const props = withDefaults(
  defineProps<{
    isOpen: boolean
    activityId: string
    preset: ConsumptionModalPreset | null
    /** Nachbuchung / addActivityItem (Materialwart / DC) */
    canAddActivityMaterial?: boolean
  }>(),
  { canAddActivityMaterial: false },
)

const emit = defineEmits<{
  close: []
  success: []
  requestNachbuchung: []
  returnWithoutConsumption: []
  deleted: []
}>()

const { t } = useI18n()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()

const isEditMode = computed(() => Boolean((props.preset?.editIssueId ?? '').trim()))
const qty = ref(1)
const notes = ref('')
const submitting = ref(false)
const deleting = ref(false)
const loadingLimits = ref(false)
const bookedTotal = ref(0)
const consumedTotal = ref(0)

const maxRemaining = computed(() => Math.max(0, bookedTotal.value - consumedTotal.value))

const displayMaterialLine = computed(() => {
  const p = props.preset
  if (!p) return ''
  const serial = p.linkedContainerLabel?.trim()
  return serial ? `${serial} — ${p.materialName}` : p.materialName
})

const maxQtyForEdit = computed(() => {
  if (!isEditMode.value) return maxRemaining.value
  const editQty = Math.max(0, Math.floor(Number(props.preset?.editQuantity) || 0))
  return maxRemaining.value + editQty
})

const canSubmit = computed(() => {
  if (!props.preset?.materialItemId) return false
  const q = Number(qty.value)
  if (!Number.isFinite(q)) return false
  if (isEditMode.value) {
    return q >= 1 && q <= maxQtyForEdit.value
  }
  if (maxRemaining.value < 1) return false
  return q >= 0 && q <= maxRemaining.value
})

const submitButtonLabel = computed(() => {
  if (submitting.value) return t('components.activityConsumptionModal.submitting')
  if (isEditMode.value) return t('components.activityConsumptionModal.submitEdit')
  const q = Math.floor(Number(qty.value) || 0)
  if (q === 0 && (props.preset?.returnQty ?? 0) > 0) {
    return t('components.activityConsumptionModal.submitZeroReturn', {
      count: props.preset!.returnQty!,
    })
  }
  if (q === 0) {
    return t('components.activityConsumptionModal.submitZero')
  }
  return t('components.activityConsumptionModal.submit')
})

const closeFooterLabel = computed(() => {
  const rq = props.preset?.returnQty
  if (rq != null && rq > 0) {
    return t('components.activityConsumptionModal.returnWithoutConsumption', { count: rq })
  }
  return t('components.activityConsumptionModal.closeFooter')
})

function clampQtyInput() {
  const m = isEditMode.value ? maxQtyForEdit.value : maxRemaining.value
  if (m < 1 && !isEditMode.value) {
    qty.value = 0
    return
  }
  let n = Number(qty.value)
  if (!Number.isFinite(n)) n = isEditMode.value ? 1 : 0
  qty.value = Math.min(m, Math.max(isEditMode.value ? 1 : 0, Math.floor(n)))
}

function bumpQty(delta: number) {
  const m = isEditMode.value ? maxQtyForEdit.value : maxRemaining.value
  if (m < 1 && !isEditMode.value) return
  let n = Number(qty.value)
  if (!Number.isFinite(n)) n = isEditMode.value ? 1 : 0
  qty.value = Math.min(m, Math.max(isEditMode.value ? 1 : 0, Math.floor(n) + delta))
}

function applySetMultiple(sets: number) {
  const m = maxRemaining.value
  const ps = props.preset?.packSize ?? 1
  if (m < 1 || ps < 1) return
  qty.value = Math.min(m, sets * ps)
}

async function loadConsumptionLimits() {
  const mid = props.preset?.materialItemId
  if (!mid || !props.activityId) {
    bookedTotal.value = 0
    consumedTotal.value = 0
    return
  }
  loadingLimits.value = true
  try {
    const editIssueId = (props.preset?.editIssueId ?? '').trim()
    const [items, issues, pack] = await Promise.all([
      getActivityItems(props.activityId),
      getActivityIssues(props.activityId),
      getPackItems(props.activityId).catch(() => []),
    ])
    bookedTotal.value = items
      .filter((i) => i.material_item_id === mid)
      .reduce((s, i) => s + i.quantity, 0)
    const returned = pack
      .filter((p) => p.materialItemId === mid)
      .reduce((s, p) => s + (p.quantityReturned ?? 0), 0)
    const consumed = issues
      .filter((i) => i.type === 'consumption' && i.material_item_id === mid)
      .filter((i) => !editIssueId || i.id !== editIssueId)
      .reduce((s, i) => s + i.quantity, 0)
    consumedTotal.value = consumed + returned
    const editQty = Math.max(0, Math.floor(Number(props.preset?.editQuantity) || 0))
    const rem = Math.max(0, bookedTotal.value - consumedTotal.value)
    if (isEditMode.value && editQty > 0) {
      qty.value = editQty
      notes.value = (props.preset?.editDescription ?? '').trim()
    } else if (rem < 1) {
      qty.value = 0
    } else if ((props.preset?.returnQty ?? 0) > 0) {
      qty.value = 0
    } else {
      qty.value = Math.min(1, rem)
    }
  } catch {
    bookedTotal.value = 0
    consumedTotal.value = 0
    toast.error(t('components.activityConsumptionModal.toastLoadLimitsFailed'))
  } finally {
    loadingLimits.value = false
  }
}

watch(
  () => [props.isOpen, props.preset?.materialItemId, props.activityId] as const,
  async ([open]) => {
    if (open && props.preset?.materialItemId) {
      notes.value = ''
      await loadConsumptionLimits()
    } else if (!open) {
      qty.value = 1
      notes.value = ''
      bookedTotal.value = 0
      consumedTotal.value = 0
    }
  },
)

function closeAsCancel() {
  emit('close')
}

function onFooterOutlineClick() {
  const rq = props.preset?.returnQty
  if (rq != null && rq > 0) {
    emit('returnWithoutConsumption')
    return
  }
  closeAsCancel()
}

function close() {
  closeAsCancel()
}

async function confirmDelete() {
  const issueId = props.preset?.editIssueId
  if (!issueId || deleting.value) return
  const ok = await confirmDialog({
    title: t('components.activityConsumptionModal.deleteConfirmTitle'),
    message: t('components.activityConsumptionModal.deleteConfirmMessage', {
      name: displayMaterialLine.value,
      n: Math.max(1, Math.floor(Number(props.preset?.editQuantity) || 0)),
    }),
    confirmText: t('components.activityConsumptionModal.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  deleting.value = true
  try {
    await deleteActivityConsumptionIssue(props.activityId, issueId)
    emit('deleted')
    close()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('components.activityConsumptionModal.toastDeleteFailed'))
  } finally {
    deleting.value = false
  }
}

async function submit() {
  const p = props.preset
  if (!p?.materialItemId || submitting.value || deleting.value) return
  clampQtyInput()
  const bookedQty = Math.floor(Number(qty.value) || 0)
  const maxAllowed = isEditMode.value ? maxQtyForEdit.value : maxRemaining.value
  if (isEditMode.value) {
    if (bookedQty < 1 || bookedQty > maxAllowed) {
      toast.error(t('components.activityConsumptionModal.toastMaxQty', { max: maxAllowed }))
      return
    }
    submitting.value = true
    try {
      await updateActivityConsumptionIssue(props.activityId, p.editIssueId!, {
        quantity: bookedQty,
        description: notes.value.trim() || null,
      })
      emit('success')
      close()
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } }; message?: string }
      toast.error(e.response?.data?.error || e.message || t('components.activityConsumptionModal.toastUpdateFailed'))
    } finally {
      submitting.value = false
    }
    return
  }
  if (bookedQty < 0 || bookedQty > maxAllowed) {
    toast.error(
      maxAllowed < 1
        ? t('components.activityConsumptionModal.toastNoConsumptionPossible')
        : t('components.activityConsumptionModal.toastMaxQty', { max: maxAllowed }),
    )
    return
  }
  if (bookedQty === 0) {
    const rq = p.returnQty
    if (rq != null && rq > 0) {
      emit('returnWithoutConsumption')
      return
    }
    emit('success')
    close()
    return
  }
  submitting.value = true
  try {
    await createActivityIssue(props.activityId, {
      material_item_id: p.materialItemId,
      type: 'consumption',
      quantity: bookedQty,
      description: notes.value.trim() || null,
    })
    emit('success')
    close()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('components.activityConsumptionModal.toastBookFailed'))
  } finally {
    submitting.value = false
  }
}
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
  max-width: 420px;
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
}

.consumption-modal-loading {
  font-size: 13px;
  margin-bottom: 10px;
}

.consumption-modal-material {
  font-weight: 600;
  margin-bottom: 10px;
  line-height: 1.4;
}

.consumption-modal-limits {
  font-size: 13px;
  line-height: 1.45;
  margin: 0 0 12px;
}

.consumption-modal-warn {
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: 8px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  font-size: 13px;
}

.consumption-modal-nachlieferung {
  margin: 0 0 14px;
}

.link-btn {
  background: none;
  border: none;
  padding: 0;
  color: #2563eb;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  text-decoration: underline;
}

.link-btn:hover {
  color: #1d4ed8;
}

.consumption-modal-nachbuchung-actions {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.consumption-modal-no-perm {
  margin: 8px 0 0;
}

.consumption-modal-field {
  margin-bottom: 14px;
}

.consumption-modal-field label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: #475569;
  margin-bottom: 6px;
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

.btn-qty:hover {
  background: #e2e8f0;
}

.adjust-qty-input {
  width: 5rem;
  text-align: center;
}

.pack-edit-set-btns {
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

.pack-edit-set-hint {
  font-size: 12px;
  color: #64748b;
  width: 100%;
}

.form-textarea {
  width: 100%;
  resize: vertical;
  min-height: 72px;
}

.consumption-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 12px 18px 16px;
  border-top: 1px solid #e5e7eb;
}

.btn-success {
  background: #16a34a;
  border-color: #16a34a;
  color: #fff;
}

.btn-success:hover:not(:disabled) {
  background: #15803d;
}

.btn-danger-outline {
  color: #b91c1c;
  border-color: #fecaca;
  margin-right: auto;
}

.btn-danger-outline:hover:not(:disabled) {
  background: #fef2f2;
}
</style>
