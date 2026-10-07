<template>
  <EDialog v-model="open" :title="item ? t('grossanlass.rueckbau.decide.title', { name: item.name }) : ''" max-width="560" :retain-focus="false">
    <div v-if="item" class="decide">
      <p class="decide__meta">
        {{ item.project }} · {{ t('grossanlass.rueckbau.openQty', { n: openQty(item), total: item.qty }) }}
        · {{ item.origin }}
      </p>
      <p v-if="item.plannedFate" class="decide__planned">
        <v-icon icon="mdi-lightbulb-on-outline" size="16" />
        {{ t('grossanlass.rueckbau.plannedHint', { label: item.plannedLabel }) }}
      </p>

      <div class="decide__fates" role="group" :aria-label="t('grossanlass.rueckbau.decide.fate')">
        <button
          v-for="fate in fates"
          :key="fate"
          type="button"
          class="fate"
          :class="{ 'fate--active': form.fate === fate, 'fate--planned': item.plannedFate === fate }"
          @click="form.fate = fate"
        >
          <v-icon :icon="FATE_ICON[fate]" size="22" />
          <span>{{ t(`grossanlass.rueckbau.fate.${fate}`) }}</span>
        </button>
      </div>

      <ETextField v-model="qtyText" type="number" min="1" :max="openQty(item)" :label="t('grossanlass.rueckbau.decide.qty')" hide-details />

      <ESelect v-if="form.fate === 'lager'" v-model="form.detail" :items="GA_STORAGE_PLACES" :label="t('grossanlass.rueckbau.decide.place')" hide-details />
      <template v-else-if="form.fate === 'reuse'">
        <ESelect v-model="form.detail" :items="GA_PROJECT_TARGETS" :label="t('grossanlass.rueckbau.decide.targetProject')" hide-details />
        <ECheckbox v-model="form.createTransport" :label="t('grossanlass.rueckbau.decide.createTransport')" hide-details />
      </template>
      <template v-else-if="form.fate === 'sale'">
        <section class="split">
          <h4>{{ t('grossanlass.rueckbau.sale.confirmTitle') }}</h4>
          <p class="decide__hint">{{ t('grossanlass.rueckbau.sale.confirmHint') }}</p>
          <div class="split__grid">
            <ETextField
              v-for="key in GA_CONFIRMED_KEYS"
              :key="key"
              v-model.number="split[key]"
              type="number"
              min="0"
              :label="t(`grossanlass.rueckbau.sale.split.${key}`)"
              hide-details
            />
          </div>
          <p class="split__sum" :class="{ 'split__sum--bad': splitSum !== Number(qtyText) }">
            {{ t('grossanlass.rueckbau.sale.splitSum', { sum: splitSum, qty: Number(qtyText) || 0, sellable: split.good + split.wear }) }}
          </p>
          <v-alert v-if="plannedOffer" type="info" variant="tonal" density="compact">
            {{ t('grossanlass.rueckbau.sale.planned', { planned: plannedOffer.qty, reserved: plannedReserved }) }}
          </v-alert>
          <ECheckbox v-if="plannedReserved > 0" v-model="stageForBuyers" :label="t('grossanlass.rueckbau.sale.stage', { n: plannedReserved })" hide-details />
        </section>
        <ETextField v-model="priceText" type="number" min="0" :label="t('grossanlass.rueckbau.decide.price')" hide-details />
        <ESelect v-model="form.pickup" :items="GA_STORAGE_PLACES" :label="t('grossanlass.rueckbau.decide.pickup')" hide-details />
      </template>
      <p v-else-if="form.fate === 'return'" class="decide__hint">
        {{ t('grossanlass.rueckbau.decide.returnHint', { firm: item.firm || item.origin }) }}
      </p>
      <ESelect
        v-else-if="form.fate === 'dispose'"
        v-model="form.disposeRoute"
        :items="disposeItems"
        :label="t('grossanlass.rueckbau.decide.disposeRoute')"
        hide-details
      />
      <p v-else-if="form.fate === 'workshop'" class="decide__hint">{{ t('grossanlass.rueckbau.decide.workshopHint') }}</p>
    </div>
    <template #actions>
      <EButton variant="secondary" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton v-if="item?.plannedFate" variant="secondary" @click="applyPlannedFate">
        {{ t('grossanlass.rueckbau.decide.asPlanned') }}
      </EButton>
      <EButton variant="primary" :disabled="!form.fate || (form.fate === 'sale' && splitSum !== (Number(qtyText) || 0))" @click="confirm">{{ t('grossanlass.rueckbau.decide.confirm') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ECheckbox, EDialog, ESelect, ETextField } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import {
  GA_DISPOSE_ROUTES,
  GA_PROJECT_TARGETS,
  GA_STORAGE_PLACES,
  applyPlanned,
  decide,
  lastResaleResult,
  openQty,
  useGaRueckbauMock,
  type GaDisposeRoute,
  type GaFate,
} from './gaRueckbauMock'
import { GA_CONFIRMED_KEYS, offerStats, stageReserved, type GaConfirmedSplit, useGaVerkaufMock } from '@/views/grossanlass/weiterverkauf/gaVerkaufMock'
import { FATE_ICON } from './gaRueckbauUi'

const props = defineProps<{ modelValue: boolean; itemId: string | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { t } = useI18n()
const toast = useToast()
const { items } = useGaRueckbauMock()

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const item = computed(() => items.value.find((row) => row.id === props.itemId))
const fates: GaFate[] = ['lager', 'reuse', 'sale', 'return', 'dispose', 'workshop']
const disposeItems = computed(() => GA_DISPOSE_ROUTES.map((value) => ({ value, title: t(`grossanlass.rueckbau.disposeRoute.${value}`) })))

const form = reactive<{
  fate: GaFate | null
  detail: string
  pickup: string
  createTransport: boolean
  disposeRoute: GaDisposeRoute
}>({ fate: null, detail: '', pickup: 'Zentrallager', createTransport: false, disposeRoute: 'kehricht' })
const qtyText = ref('1')
const stageForBuyers = ref(true)
const split = reactive<GaConfirmedSplit>({ good: 1, wear: 0, damaged: 0, notSellable: 0, workshop: 0 })
const splitSum = computed(() => GA_CONFIRMED_KEYS.reduce((sum, key) => sum + (Number(split[key]) || 0), 0))
const { offers } = useGaVerkaufMock()
const plannedOffer = computed(() => offers.value.find((entry) => entry.rueckbauItemId === props.itemId))
const plannedReserved = computed(() => (plannedOffer.value ? offerStats(plannedOffer.value).reserved : 0))
const priceText = ref('1')

watch(
  () => [props.modelValue, props.itemId] as const,
  () => {
    const row = item.value
    form.fate = row?.plannedFate ?? null
    form.detail = row?.plannedFate === 'reuse' ? GA_PROJECT_TARGETS[0]! : GA_STORAGE_PLACES[0]!
    form.pickup = GA_STORAGE_PLACES[0]!
    form.createTransport = true
    form.disposeRoute = 'kehricht'
    qtyText.value = String(row ? openQty(row) : 1)
    Object.assign(split, { good: row ? openQty(row) : 1, wear: 0, damaged: 0, notSellable: 0, workshop: 0 })
    priceText.value = '1'
  },
)

function confirm() {
  if (!item.value || !form.fate) return
  const ok = decide(item.value.id, {
    fate: form.fate,
    qty: Number(qtyText.value) || 1,
    detail: form.detail,
    priceChf: Number(priceText.value) || 0,
    pickup: form.pickup,
    createTransport: form.createTransport,
    disposeRoute: form.disposeRoute,
    split: { ...split },
  })
  if (ok) {
    const resale = form.fate === 'sale' ? lastResaleResult() : null
    if (resale && stageForBuyers.value && plannedReserved.value > 0) stageReserved(resale.offer.id)
    if (resale?.warnings.some((entry) => entry.kind === 'reservedExceeds')) {
      toast.error(t('grossanlass.rueckbau.sale.warnReserved'))
    } else if (resale?.planned != null && resale.planned !== resale.sellable) {
      toast.warning(t('grossanlass.rueckbau.sale.warnDeviates', { planned: resale.planned, sellable: resale.sellable }))
    }
    toast.success(t('grossanlass.rueckbau.decide.saved', { fate: t(`grossanlass.rueckbau.fate.${form.fate}`) }))
    open.value = false
  }
}

function applyPlannedFate() {
  if (item.value && applyPlanned(item.value.id)) {
    toast.success(t('grossanlass.rueckbau.decide.savedPlanned'))
    open.value = false
  }
}
</script>

<style scoped>
.decide {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.decide__meta {
  margin: 0;
  color: #64748b;
  font-size: 0.86rem;
}
.decide__planned {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  padding: 8px 10px;
  border-radius: 10px;
  background: #ecfdf5;
  color: #065f46;
  font-size: 0.86rem;
}
.decide__hint {
  margin: 0;
  color: #475569;
  font-size: 0.86rem;
}
.decide__fates {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.fate {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-height: 72px;
  padding: 10px 6px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  font-size: 0.82rem;
  cursor: pointer;
}
.split {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fafafa;
}
.split h4 {
  margin: 0;
  font-size: 0.74rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #475569;
}
.split__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 8px;
}
.split__sum {
  margin: 0;
  font-size: 0.86rem;
  color: #15803d;
}
.split__sum--bad {
  color: #b91c1c;
}
.fate--planned {
  border-style: dashed;
  border-color: #059669;
}
.fate--active {
  border: 2px solid #059669;
  background: #ecfdf5;
  font-weight: 700;
}
</style>
