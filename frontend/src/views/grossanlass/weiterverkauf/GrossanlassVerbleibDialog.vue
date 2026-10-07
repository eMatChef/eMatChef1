<template>
  <EDialog v-model="open" :title="t('grossanlass.verkauf.after.title')" max-width="720" :retain-focus="false">
    <div v-if="!current" class="vb">
      <p class="vb__hint">{{ t('grossanlass.verkauf.after.hint') }}</p>
      <ul class="vb__list">
        <li v-for="purchase in purchases" :key="purchase.id">
          <div class="vb__main">
            <strong>{{ purchase.qty }}× {{ purchase.label }}</strong>
            <small>{{ t(`grossanlass.verkauf.after.source.${purchase.source}`) }} · {{ purchase.supplier }} · {{ purchase.project }}</small>
          </div>
          <v-chip size="small" variant="flat" :color="CHIP[purchase.afterUse]">{{ t(`grossanlass.verkauf.after.value.${purchase.afterUse}`) }}</v-chip>
          <EButton size="small" variant="secondary" @click="pick(purchase.id)">{{ t('grossanlass.verkauf.after.choose') }}</EButton>
        </li>
      </ul>
    </div>

    <div v-else class="vb">
      <EButton variant="text" size="small" @click="current = null"><v-icon icon="mdi-arrow-left" start size="16" /> {{ t('grossanlass.aufgaben.mine.back') }}</EButton>
      <p class="vb__title"><strong>{{ current.qty }}× {{ current.label }}</strong> · {{ t(`grossanlass.verkauf.after.source.${current.source}`) }} · {{ current.supplier }}</p>
      <h4>{{ t('grossanlass.verkauf.after.field') }}</h4>
      <div class="vb__values" role="radiogroup" :aria-label="t('grossanlass.verkauf.after.field')">
        <button v-for="value in GA_AFTER_USE" :key="value" type="button" role="radio" :aria-checked="form.afterUse === value" class="value" :class="{ 'value--on': form.afterUse === value }" @click="form.afterUse = value">
          {{ t(`grossanlass.verkauf.after.value.${value}`) }}
        </button>
      </div>

      <ESelect v-if="form.afterUse === 'reuse'" v-model="form.reuseTarget" :items="GA_PROJECT_TARGETS" :label="t('grossanlass.rueckbau.decide.targetProject')" hide-details />

      <section v-if="form.afterUse === 'sale'" class="vb__sale">
        <p class="vb__note">{{ t('grossanlass.verkauf.plan.afterHint') }}</p>
        <div class="vb__grid">
          <ETextField v-model="saleQtyText" type="number" min="1" :max="current.qty" :label="t('grossanlass.verkauf.plan.saleQty')" hide-details />
          <ETextField v-model="form.availableFrom" type="date" :label="t('grossanlass.verkauf.plan.availableFrom')" hide-details />
          <ESelect v-model="form.expectedCondition" :items="expectedItems" :label="t('grossanlass.verkauf.plan.expectedCondition')" hide-details />
          <ETextField v-model="priceText" type="number" min="0" step="0.5" :label="t('grossanlass.verkauf.plan.priceOptional')" hide-details />
          <ESelect v-model="form.pickup" :items="GA_OFFER_PICKUPS" :label="t('grossanlass.verkauf.offer.pickup')" clearable hide-details />
        </div>
        <ECheckbox v-model="form.earlyPublish" :label="t('grossanlass.verkauf.after.publishNow')" hide-details />
        <p v-if="form.earlyPublish" class="vb__public">
          <v-icon icon="mdi-earth" size="16" />
          {{ t('grossanlass.verkauf.public.inUse') }} · {{ t('grossanlass.verkauf.public.expectedFrom', { date: form.availableFrom }) }} · {{ t(`grossanlass.verkauf.public.expected.${form.expectedCondition}`) }}<br>
          {{ t('grossanlass.verkauf.public.confirmAfter') }}
        </p>
      </section>
    </div>

    <template #actions>
      <EButton variant="secondary" @click="open = false">{{ t('common.close') }}</EButton>
      <EButton v-if="current" variant="primary" @click="save">{{ t('common.save') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ECheckbox, EDialog, ESelect, ETextField } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import { GA_PROJECT_TARGETS } from '@/views/grossanlass/rueckbau/gaRueckbauMock'
import {
  GA_AFTER_USE,
  GA_OFFER_PICKUPS,
  purchaseById,
  setAfterUse,
  useGaVerkaufMock,
  type GaAfterUse,
  type GaExpectedCondition,
  type GaPurchase,
} from './gaVerkaufMock'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { t } = useI18n()
const toast = useToast()
const { purchases } = useGaVerkaufMock()

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const CHIP: Record<GaAfterUse, string> = { lager: 'primary', reuse: 'success', sale: 'info', dispose: 'grey', open: 'warning' }
const current = ref<GaPurchase | null>(null)
const form = reactive({
  afterUse: 'open' as GaAfterUse,
  reuseTarget: GA_PROJECT_TARGETS[0]!,
  availableFrom: '',
  expectedCondition: 'wear' as GaExpectedCondition,
  pickup: '' as string | null,
  earlyPublish: false,
})
const saleQtyText = ref('1')
const priceText = ref('0')
const expectedItems = computed(() => (['new', 'used', 'wear', 'toCheck'] as const).map((value) => ({ value, title: t(`grossanlass.verkauf.plan.expected.${value}`) })))

function pick(id: string) {
  const purchase = purchaseById(id)
  if (!purchase) return
  current.value = purchase
  const offer = purchase.offerId ? useGaVerkaufMock().offers.value.find((row) => row.id === purchase.offerId) : undefined
  form.afterUse = purchase.afterUse
  form.reuseTarget = purchase.reuseTarget || GA_PROJECT_TARGETS[0]!
  form.availableFrom = offer?.availableFrom ?? new Date(Date.now() + 14 * 86_400_000).toISOString().slice(0, 10)
  form.expectedCondition = offer?.expectedCondition ?? 'wear'
  form.pickup = offer?.pickup ?? null
  form.earlyPublish = offer?.earlyPublish ?? false
  saleQtyText.value = String(offer?.qty ?? purchase.qty)
  priceText.value = String(offer?.priceChf ?? 0)
}

function save() {
  if (!current.value) return
  const offer = setAfterUse(current.value.id, {
    afterUse: form.afterUse,
    reuseTarget: form.reuseTarget,
    saleQty: Number(saleQtyText.value) || current.value.qty,
    availableFrom: form.availableFrom,
    expectedCondition: form.expectedCondition,
    priceChf: Number(priceText.value) || 0,
    pickup: form.pickup || undefined,
    earlyPublish: form.earlyPublish,
  })
  toast.success(offer && form.earlyPublish ? t('grossanlass.verkauf.after.savedPublished') : t('grossanlass.verkauf.after.saved'))
  current.value = null
}

watch(open, (isOpen) => {
  if (!isOpen) current.value = null
})
</script>

<style scoped>
.vb {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.vb__hint,
.vb__note {
  margin: 0;
  color: #64748b;
  font-size: 0.86rem;
}
.vb__list {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.vb__list li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  gap: 10px;
  align-items: center;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.vb__main {
  display: flex;
  flex-direction: column;
}
.vb__main small {
  color: #64748b;
}
.vb__title {
  margin: 0;
}
.vb h4 {
  margin: 0;
  font-size: 0.74rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #475569;
}
.vb__values {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.value {
  min-height: 44px;
  padding: 6px 16px;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  background: #fff;
  font-weight: 600;
  cursor: pointer;
}
.value--on {
  border-color: #059669;
  background: #ecfdf5;
  color: #065f46;
}
.vb__sale {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
  border: 1px solid #bae6fd;
  border-radius: 12px;
  background: #f0f9ff;
}
.vb__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 10px;
}
.vb__public {
  margin: 0;
  padding: 8px 10px;
  border-radius: 10px;
  background: #fff;
  font-size: 0.84rem;
  color: #0369a1;
}
@media (max-width: 560px) {
  .vb__list li {
    grid-template-columns: 1fr;
  }
}
</style>
