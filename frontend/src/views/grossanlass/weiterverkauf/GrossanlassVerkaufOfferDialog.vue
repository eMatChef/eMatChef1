<template>
  <EDialog v-model="open" :title="isNew ? t('grossanlass.verkauf.offer.newTitle') : t('grossanlass.verkauf.offer.editTitle')" max-width="640" :retain-focus="false">
    <div class="offer-form">
      <ETextField v-model="form.name" :label="t('grossanlass.verkauf.offer.name')" hide-details="auto" />
      <ETextarea v-model="form.description" :label="t('grossanlass.verkauf.offer.description')" rows="3" hide-details />
      <section class="offer-form__block">
        <h4>{{ t('grossanlass.verkauf.plan.useTitle') }}</h4>
        <div class="offer-form__grid">
          <ETextField v-model="purchasedText" type="number" min="0" :label="t('grossanlass.verkauf.plan.purchased')" hide-details />
          <ETextField v-model="useText" type="number" min="0" :label="t('grossanlass.verkauf.plan.used')" hide-details />
        </div>
      </section>
      <section class="offer-form__block">
        <h4>{{ t('grossanlass.verkauf.plan.afterTitle') }}</h4>
        <p class="offer-form__note">{{ t('grossanlass.verkauf.plan.afterHint') }}</p>
        <div class="offer-form__grid">
          <ETextField v-model="qtyText" type="number" min="1" :label="t('grossanlass.verkauf.plan.saleQty')" hide-details />
          <ETextField v-model="form.availableFrom" type="date" :label="t('grossanlass.verkauf.plan.availableFrom')" hide-details />
          <ESelect v-model="form.expectedCondition" :items="expectedItems" :label="t('grossanlass.verkauf.plan.expectedCondition')" hide-details />
          <ETextField v-model="priceText" type="number" min="0" step="0.5" :label="t('grossanlass.verkauf.plan.priceOptional')" hide-details />
        </div>
        <ECheckbox v-model="form.earlyPublish" :label="t('grossanlass.verkauf.plan.earlyPublish')" hide-details />
      </section>
      <div class="offer-form__grid">
        <ESelect v-model="form.condition" :items="conditionItems" :label="t('grossanlass.verkauf.offer.condition')" hide-details />
        <ESelect v-model="form.pickup" :items="GA_OFFER_PICKUPS" :label="t('grossanlass.verkauf.offer.pickup')" hide-details />
        <ESelect v-model="form.visibility" :items="visibilityItems" :label="t('grossanlass.verkauf.offer.visibility')" hide-details />
      </div>
      <div class="offer-form__images">
        <div v-for="index in form.images" :key="index" class="img-ph"><v-icon icon="mdi-image-outline" size="26" /></div>
        <EButton variant="secondary" size="small" @click="form.images += 1">
          <v-icon icon="mdi-image-plus" start size="18" /> {{ t('grossanlass.verkauf.offer.addImage') }}
        </EButton>
        <EButton v-if="form.images > 0" variant="text" size="small" @click="form.images -= 1">{{ t('grossanlass.verkauf.offer.removeImage') }}</EButton>
      </div>
      <p class="offer-form__note">{{ t('grossanlass.verkauf.offer.demoNote') }}</p>
    </div>
    <template #actions>
      <EButton variant="secondary" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton variant="primary" :disabled="!valid" @click="save">{{ t('common.save') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ECheckbox, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'
import {
  GA_OFFER_PICKUPS,
  offerById,
  saveOffer,
  type GaExpectedCondition,
  type GaOfferCondition,
  type GaOfferVisibility,
} from './gaVerkaufMock'

const props = defineProps<{ modelValue: boolean; offerId: string | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; saved: [id: string] }>()
const { t } = useI18n()

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const isNew = computed(() => !props.offerId)

const form = reactive({
  name: '',
  description: '',
  condition: 'used' as GaOfferCondition,
  availableFrom: '',
  pickup: GA_OFFER_PICKUPS[0]!,
  visibility: 'departments' as GaOfferVisibility,
  images: 0,
  expectedCondition: 'wear' as GaExpectedCondition,
  earlyPublish: false,
})
const purchasedText = ref('')
const useText = ref('')
const qtyText = ref('1')
const priceText = ref('0')

const conditionItems = computed(() => (['good', 'used', 'damaged'] as const).map((value) => ({ value, title: t(`grossanlass.verkauf.condition.${value}`) })))
const expectedItems = computed(() => (['new', 'used', 'wear', 'toCheck'] as const).map((value) => ({ value, title: t(`grossanlass.verkauf.plan.expected.${value}`) })))
const visibilityItems = computed(() => (['departments', 'public'] as const).map((value) => ({ value, title: t(`grossanlass.verkauf.visibility.${value}`) })))
const valid = computed(() => form.name.trim().length > 0 && Number(qtyText.value) >= 1 && Number(priceText.value) >= 0)

watch(
  () => [props.modelValue, props.offerId] as const,
  () => {
    const offer = offerById(props.offerId)
    form.name = offer?.name ?? ''
    form.description = offer?.description ?? ''
    form.condition = offer?.condition ?? 'used'
    form.availableFrom = offer?.availableFrom ?? new Date().toISOString().slice(0, 10)
    form.pickup = offer?.pickup ?? GA_OFFER_PICKUPS[0]!
    form.visibility = offer?.visibility ?? 'departments'
    form.images = offer?.images ?? 0
    form.expectedCondition = offer?.expectedCondition ?? 'wear'
    form.earlyPublish = offer?.earlyPublish ?? false
    purchasedText.value = offer?.purchasedQty != null ? String(offer.purchasedQty) : ''
    useText.value = offer?.useQty != null ? String(offer.useQty) : ''
    qtyText.value = String(offer?.qty ?? 1)
    priceText.value = String(offer?.priceChf ?? 0)
  },
)

function save() {
  if (!valid.value) return
  const saved = saveOffer({
    id: props.offerId ?? undefined,
    name: form.name.trim(),
    description: form.description.trim(),
    qty: Math.floor(Number(qtyText.value)),
    priceChf: Number(priceText.value),
    condition: form.condition,
    images: form.images,
    availableFrom: form.availableFrom,
    pickup: form.pickup,
    visibility: form.visibility,
    purchasedQty: purchasedText.value === '' ? null : Math.floor(Number(purchasedText.value)),
    useQty: useText.value === '' ? null : Math.floor(Number(useText.value)),
    expectedCondition: form.expectedCondition,
    earlyPublish: form.earlyPublish,
  })
  emit('saved', saved.id)
  open.value = false
}
</script>

<style scoped>
.offer-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.offer-form__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}
.offer-form__block {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fafafa;
}
.offer-form__block h4 {
  margin: 0;
  font-size: 0.74rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #475569;
}
.offer-form__images {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.img-ph {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  border-radius: 10px;
  background: #f1f5f9;
  color: #94a3b8;
}
.offer-form__note {
  margin: 0;
  color: #64748b;
  font-size: 0.8rem;
}
</style>
