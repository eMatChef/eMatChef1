<template>
  <EDialog v-model="open" :title="t('grossanlass.materials.zusage.dialogTitle')" :max-width="720" :retain-focus="false" scrollable>
    <p class="zusage-hint">{{ t('grossanlass.beschaffung.zusagen.createHint') }}</p>

    <ETextField v-model="name" :label="t('grossanlass.materials.zusage.fieldName')" hide-details />
    <div class="zusage-grid">
      <ESelect
        v-model="family"
        :items="familyItems"
        item-title="title"
        item-value="value"
        :label="t('grossanlass.materials.zusage.fieldFamily')"
        hide-details
      />
      <ESelect
        v-model="origin"
        :items="originItems"
        item-title="title"
        item-value="value"
        :label="t('grossanlass.materials.zusage.fieldOrigin')"
        hide-details
      />
    </div>
    <ETextField v-model="source" :label="t('grossanlass.materials.zusage.fieldPartner')" hide-details />
    <ETextField
      v-if="family === 'vehicle'"
      v-model="plate"
      :label="t('grossanlass.materials.colPlate')"
      hide-details
    />

    <h3 class="zusage-section">{{ t('grossanlass.materials.zusage.sectionArticle') }}</h3>
    <p class="zusage-hint zusage-hint--muted">{{ t('grossanlass.materials.zusage.articleHint') }}</p>
    <div class="zusage-grid">
      <ETextField v-model="quantity" type="number" :label="t('grossanlass.materials.zusage.fieldQuantity')" hide-details />
      <ETextField v-model="weight" :label="t('grossanlass.materials.zusage.fieldWeight')" hide-details />
    </div>
    <div class="zusage-grid">
      <ETextField v-model="packUnit" :label="t('grossanlass.materials.zusage.fieldPackUnit')" hide-details />
      <ETextField v-model="packSize" :label="t('grossanlass.materials.zusage.fieldPackSize')" hide-details />
    </div>
    <ETextarea v-model="notes" :label="t('grossanlass.materials.zusage.fieldNotes')" rows="2" hide-details />
    <p class="zusage-hint zusage-hint--muted">{{ t('grossanlass.materials.zusage.partsHint') }}</p>
    <div v-for="(part, index) in parts" :key="index" class="zusage-part">
      <ETextField v-model="part.name" :label="t('grossanlass.materials.zusage.fieldPartName')" hide-details />
      <ETextField v-model="part.qty" type="number" :label="t('grossanlass.materials.zusage.fieldPartQty')" hide-details />
      <EButton variant="text" size="small" @click="removePart(index)">{{ t('grossanlass.materials.zusage.removePart') }}</EButton>
    </div>
    <EButton variant="secondary" size="small" @click="addPart">{{ t('grossanlass.materials.zusage.addPart') }}</EButton>

    <h3 class="zusage-section">{{ t('grossanlass.materials.zusage.sectionPresent') }}</h3>
    <EDateRangeField
      v-model:start="presentFromDate"
      v-model:end="presentToDate"
      :department-id="departmentId"
      :label="t('grossanlass.materials.zusage.fieldPresent')"
      allow-past
    />
    <div class="zusage-grid">
      <ETimeField v-model="presentFromTime" :label="t('grossanlass.materialUebersicht.fieldFromTime')" />
      <ETimeField v-model="presentToTime" :label="t('grossanlass.materialUebersicht.fieldToTime')" />
    </div>

    <h3 class="zusage-section">{{ t('grossanlass.materials.zusage.sectionHandover') }}</h3>
    <EDateField
      v-model="handoverDate"
      :department-id="departmentId"
      :label="t('grossanlass.materials.zusage.fieldHandoverDay')"
      allow-past
    />
    <div class="zusage-grid">
      <ETimeField v-model="handoverFromTime" :label="t('grossanlass.materials.zusage.fieldFrom')" />
      <ETimeField v-model="handoverToTime" :label="t('grossanlass.materials.zusage.fieldTo')" />
    </div>

    <h3 class="zusage-section">{{ t('grossanlass.materials.zusage.sectionReturn') }}</h3>
    <EDateField
      v-model="returnDate"
      :department-id="departmentId"
      :label="t('grossanlass.materials.zusage.fieldReturnDay')"
      allow-past
    />
    <div class="zusage-grid">
      <ETimeField v-model="returnFromTime" :label="t('grossanlass.materials.zusage.fieldFrom')" />
      <ETimeField v-model="returnToTime" :label="t('grossanlass.materials.zusage.fieldTo')" />
    </div>

    <ESwitch
      v-model="released"
      :label="t('grossanlass.materials.zusage.fieldRelease')"
      :hint="t('grossanlass.materials.zusage.fieldReleaseHint')"
      persistent-hint
    />

    <template v-if="family === 'vehicle'">
      <h3 class="zusage-section">{{ t('grossanlass.materials.zusage.sectionService') }}</h3>
      <p class="zusage-hint zusage-hint--muted">{{ t('grossanlass.materials.zusage.serviceHint') }}</p>
      <ESelect
        v-model="firstServiceKind"
        :items="serviceItems"
        item-title="title"
        item-value="value"
        :label="t('grossanlass.materials.zusage.fieldService')"
        clearable
        hide-details
      />
      <template v-if="firstServiceKind">
        <EDateField
          v-model="firstServiceDate"
          :department-id="departmentId"
          :label="t('grossanlass.materials.zusage.fieldServiceDay')"
          allow-past
        />
        <div class="zusage-grid">
          <ETimeField v-model="firstServiceFromTime" :label="t('grossanlass.materials.zusage.fieldFrom')" />
          <ETimeField v-model="firstServiceToTime" :label="t('grossanlass.materials.zusage.fieldTo')" />
        </div>
      </template>
    </template>

    <template #actions>
      <EButton variant="secondary" size="small" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton variant="primary" size="small" :disabled="!canSubmit" @click="submit">
          {{ t('grossanlass.beschaffung.zusagen.confirmCreate') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import {
  EButton,
  EDateField,
  EDateRangeField,
  EDialog,
  ESelect,
  ESwitch,
  ETextarea,
  ETextField,
  ETimeField,
} from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { combineIso } from '@/views/grossanlass/grossanlassZusagePreviewData'
import type { GaParkServiceKind, GaZusageOrigin } from '@/views/grossanlass/grossanlassZusagePreviewData'
import type { GaZusageCreateDraft } from '@/views/grossanlass/grossanlassZusagePreviewStore'
import {
  createGrossanlassCommitment,
  type GrossanlassCommitment,
  type GrossanlassCommitmentPart,
} from '@/api/grossanlassCommitments'

const open = defineModel<boolean>({ default: false })
const props = defineProps<{
  preset?: Partial<GaZusageCreateDraft> | null
}>()
const emit = defineEmits<{
  created: [article: GrossanlassCommitment]
}>()

const route = useRoute()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(() => String(route.params.departmentId || ''))

const name = ref('')
const family = ref<'vehicle' | 'material'>('material')
const origin = ref<GaZusageOrigin>('loan')
const source = ref('')
const plate = ref('')
const presentFromDate = ref('2027-07-16')
const presentToDate = ref('2027-07-18')
const presentFromTime = ref('08:00')
const presentToTime = ref('18:00')
const handoverDate = ref('2027-07-16')
const handoverFromTime = ref('07:00')
const handoverToTime = ref('08:00')
const returnDate = ref('2027-07-18')
const returnFromTime = ref('08:00')
const returnToTime = ref('12:00')
const released = ref(false)
const quantity = ref<number | string>(1)
const weight = ref('')
const packUnit = ref('')
const packSize = ref('')
const notes = ref('')
const parts = ref<GrossanlassCommitmentPart[]>([])
const firstServiceKind = ref<GaParkServiceKind | ''>('')
const firstServiceDate = ref('2027-07-18')
const firstServiceFromTime = ref('06:00')
const firstServiceToTime = ref('08:00')

const familyItems = computed(() => [
  { title: t('grossanlass.materials.zusage.familyMaterial'), value: 'material' },
  { title: t('grossanlass.materials.zusage.familyVehicle'), value: 'vehicle' },
])
const originItems = computed(() => [
  { title: t('grossanlass.materials.lifecycle.loan'), value: 'loan' },
  { title: t('grossanlass.materials.lifecycle.reusable'), value: 'buy' },
  { title: t('grossanlass.materials.lifecycle.buy_resale'), value: 'buy_resale' },
])
const serviceItems = computed(() => [
  { title: t('grossanlass.materials.zusage.service.clean'), value: 'clean' },
  { title: t('grossanlass.materials.zusage.service.grease'), value: 'grease' },
  { title: t('grossanlass.materials.zusage.service.other'), value: 'other' },
])

const canSubmit = computed(() =>
  Boolean(name.value.trim() && source.value.trim() && presentFromDate.value && presentToDate.value
    && handoverDate.value && returnDate.value),
)

function applyPreset() {
  const preset = props.preset ?? {}
  name.value = preset.name ?? ''
  family.value = preset.family ?? 'material'
  origin.value = preset.origin ?? 'loan'
  source.value = preset.source ?? ''
  plate.value = preset.plate ?? ''
  presentFromDate.value = preset.presentFromDate ?? '2027-07-16'
  presentToDate.value = preset.presentToDate ?? '2027-07-18'
  presentFromTime.value = preset.presentFromTime ?? '08:00'
  presentToTime.value = preset.presentToTime ?? '18:00'
  handoverDate.value = preset.handoverDate ?? presentFromDate.value
  handoverFromTime.value = preset.handoverFromTime ?? '07:00'
  handoverToTime.value = preset.handoverToTime ?? '08:00'
  returnDate.value = preset.returnDate ?? presentToDate.value
  returnFromTime.value = preset.returnFromTime ?? '08:00'
  returnToTime.value = preset.returnToTime ?? '12:00'
  released.value = preset.released ?? false
  quantity.value = 1
  weight.value = ''
  packUnit.value = ''
  packSize.value = ''
  notes.value = ''
  parts.value = []
  firstServiceKind.value = preset.firstServiceKind ?? ''
  firstServiceDate.value = preset.firstServiceDate ?? returnDate.value
  firstServiceFromTime.value = preset.firstServiceFromTime ?? '06:00'
  firstServiceToTime.value = preset.firstServiceToTime ?? '08:00'
}

watch(open, (isOpen) => {
  if (isOpen) applyPreset()
})

function addPart() {
  parts.value = [...parts.value, { name: '', qty: 1 }]
}

function removePart(index: number) {
  parts.value = parts.value.filter((_, i) => i !== index)
}

async function submit() {
  if (!canSubmit.value || !departmentId.value) return
  try {
    const created = await createGrossanlassCommitment(departmentId.value, {
      name: name.value.trim(),
      source: source.value.trim(),
      family: family.value,
      origin: origin.value,
      plate: plate.value.trim(),
      quantity: Math.max(1, Number(quantity.value) || 1),
      item_details: {
        weight: weight.value.trim() || undefined,
        pack_unit: packUnit.value.trim() || undefined,
        pack_size: packSize.value.trim() || undefined,
        notes: notes.value.trim() || undefined,
        parts: parts.value
          .filter((part) => part.name.trim())
          .map((part) => ({ name: part.name.trim(), qty: Math.max(1, Number(part.qty) || 1) })),
      },
      released: released.value,
      present_from: combineIso(presentFromDate.value, presentFromTime.value),
      present_to: combineIso(presentToDate.value, presentToTime.value),
      handover_from: combineIso(handoverDate.value, handoverFromTime.value),
      handover_to: combineIso(handoverDate.value, handoverToTime.value),
      return_from: combineIso(returnDate.value, returnFromTime.value),
      return_to: combineIso(returnDate.value, returnToTime.value),
      services: family.value === 'vehicle' && firstServiceKind.value
        ? [{
            kind: firstServiceKind.value,
            fromIso: combineIso(firstServiceDate.value, firstServiceFromTime.value),
            toIso: combineIso(firstServiceDate.value, firstServiceToTime.value),
          }]
        : [],
    })
    toast.success(t('grossanlass.beschaffung.zusagen.createdToast'))
    open.value = false
    emit('created', created)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}
</script>

<style scoped>
.zusage-hint {
  margin: 0 0 12px;
  font-size: 0.85rem;
  color: #9a3412;
}
.zusage-hint--muted {
  color: #64748b;
}
.zusage-section {
  margin: 16px 0 8px;
  font-size: 0.82rem;
  font-weight: 700;
}
.zusage-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
@media (max-width: 640px) {
  .zusage-grid,
  .zusage-part { grid-template-columns: 1fr; }
}
.zusage-part {
  display: grid;
  grid-template-columns: 1fr 88px auto;
  gap: 8px;
  align-items: end;
  margin-bottom: 8px;
}
</style>
