<template>
  <div class="writeoff-repurpose">
    <label class="writeoff-repurpose-toggle">
      <input v-model="enabled" type="checkbox" />
      <span>{{ t('workshop.writeoffRepurpose.enable') }}</span>
    </label>

    <template v-if="enabled">
      <p class="writeoff-repurpose-hint">{{ t('workshop.writeoffRepurpose.hint') }}</p>

      <div v-if="modelValue.material_item_id" class="writeoff-repurpose-selected">
        <v-icon icon="mdi-package-variant-closed" size="18" />
        <div class="writeoff-repurpose-selected-text">
          <span class="writeoff-repurpose-selected-label">{{ t('workshop.writeoffRepurpose.selectedMaterial') }}</span>
          <span class="writeoff-repurpose-selected-name">{{ modelValue.material_name || modelValue.material_item_id }}</span>
          <span v-if="modelValue.stock_already_booked" class="writeoff-repurpose-selected-meta">
            {{ t('workshop.writeoffRepurpose.stockBookedInWizard') }}
          </span>
        </div>
        <button type="button" class="writeoff-repurpose-clear" @click="clearMaterial">
          {{ t('common.remove') }}
        </button>
      </div>

      <div class="writeoff-repurpose-actions">
        <EButton variant="primary" size="small" @click="emit('create-material')">
          <v-icon icon="mdi-plus" start size="18" />
          {{ t('workshop.writeoffRepurpose.createMaterial') }}
        </EButton>
      </div>

      <div v-if="!modelValue.material_item_id" class="writeoff-repurpose-or-block">
        <span class="writeoff-repurpose-or">{{ t('workshop.writeoffRepurpose.orExisting') }}</span>
        <MaterialLookupInput
          :model-value="materialQuery"
          :fetcher="materialFetcher"
          :placeholder="t('workshop.writeoffRepurpose.targetSearch')"
          :min-chars="1"
          :max-suggestions="10"
          :teleport-dropdown="true"
          :dropdown-min-width="300"
          :get-result-label="(item) => item.name"
          @select="onMaterialSelected"
          @update:model-value="onMaterialQueryChange"
        />
      </div>

      <label
        v-if="modelValue.material_item_id && !modelValue.stock_already_booked"
        class="writeoff-repurpose-field"
      >
        <span class="writeoff-repurpose-field-label">{{ quantityLabel }}</span>
        <input
          v-model.number="quantity"
          type="number"
          min="0.01"
          step="any"
          class="form-input"
          :placeholder="quantityPlaceholder"
        />
      </label>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { getMaterials, type Material } from '@/api/materials'
import { getWorkshopSettings } from '@/api/departmentSettings'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import { EButton } from '@/components/form/base'
import type { WorkshopMaterialInfo } from '@/api/workshop'

export interface WriteoffRepurposeForm {
  enabled: boolean
  material_item_id: string
  material_name: string
  quantity: number | null
  quantity_unit: string
  stock_already_booked: boolean
}

const props = defineProps<{
  modelValue: WriteoffRepurposeForm
  departmentId: string
  sourceMaterial: WorkshopMaterialInfo
}>()

const emit = defineEmits<{
  'update:modelValue': [value: WriteoffRepurposeForm]
  'create-material': []
}>()

const { t } = useI18n()

const sparePartsCategoryId = ref('')
const materialQuery = ref('')

const enabled = computed({
  get: () => props.modelValue.enabled,
  set: (value: boolean) => patch({ enabled: value }),
})

const quantity = computed({
  get: () => props.modelValue.quantity,
  set: (value: number | null) => patch({ quantity: value }),
})

const isMeterMaterial = computed(() => {
  const packUnit = (props.sourceMaterial.pack_unit || '').toLowerCase()
  return packUnit === 'm' || packUnit.includes('meter')
})

const quantityLabel = computed(() =>
  isMeterMaterial.value
    ? t('workshop.writeoffRepurpose.quantityM')
    : t('workshop.writeoffRepurpose.quantityStk'),
)

const quantityPlaceholder = computed(() => (isMeterMaterial.value ? '30' : '1'))

watch(
  () => props.departmentId,
  async (departmentId) => {
    if (!departmentId) return
    try {
      const settings = await getWorkshopSettings(departmentId)
      sparePartsCategoryId.value = settings.sparePartsCategoryId
    } catch {
      sparePartsCategoryId.value = ''
    }
  },
  { immediate: true },
)

function patch(partial: Partial<WriteoffRepurposeForm>) {
  emit('update:modelValue', { ...props.modelValue, ...partial })
}

async function materialFetcher(query: string) {
  if (!props.departmentId || !query.trim()) return []
  const materials = await getMaterials(props.departmentId, { search: query })
  const spareId = sparePartsCategoryId.value
  return materials.filter((item) => !spareId || item.category?.id === spareId).slice(0, 10)
}

function onMaterialSelected(item: Material | Record<string, unknown>) {
  const material = item as Material
  materialQuery.value = material.name
  patch({
    material_item_id: material.id,
    material_name: material.name,
    stock_already_booked: false,
    quantity_unit: material.pack_unit || (isMeterMaterial.value ? 'm' : 'Stk'),
    quantity: null,
  })
}

function onMaterialQueryChange(value: string) {
  materialQuery.value = value
  if (!value.trim()) {
    patch({
      material_item_id: '',
      material_name: '',
      stock_already_booked: false,
    })
  }
}

function clearMaterial() {
  materialQuery.value = ''
  patch({
    material_item_id: '',
    material_name: '',
    quantity: null,
    stock_already_booked: false,
  })
}
</script>

<style scoped>
.writeoff-repurpose {
  margin-top: 12px;
  padding: 14px;
  border: 1px solid var(--color-primary-muted-border);
  border-radius: 10px;
  background: var(--color-primary-muted-bg);
}

.writeoff-repurpose-toggle {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--color-primary-dark);
  cursor: pointer;
}

.writeoff-repurpose-hint {
  margin: 10px 0 0;
  font-size: 12px;
  line-height: 1.45;
  color: #4b5563;
}

.writeoff-repurpose-selected {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-top: 12px;
  padding: 10px 12px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  background: #fff;
}

.writeoff-repurpose-selected-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1;
  min-width: 0;
}

.writeoff-repurpose-selected-label {
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.writeoff-repurpose-selected-name {
  font-size: 13px;
  font-weight: 700;
  color: #111827;
}

.writeoff-repurpose-selected-meta {
  font-size: 11px;
  color: var(--color-primary-dark);
}

.writeoff-repurpose-clear {
  border: none;
  background: none;
  padding: 0;
  font-size: 12px;
  font-weight: 600;
  color: #b91c1c;
  cursor: pointer;
}

.writeoff-repurpose-actions {
  margin-top: 12px;
}

.writeoff-repurpose-or-block {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 12px;
}

.writeoff-repurpose-or {
  font-size: 12px;
  color: #6b7280;
}

.writeoff-repurpose-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 12px;
}

.writeoff-repurpose-field-label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}
</style>
