<template>
  <section class="rueckgabe">
    <h3 class="rueckgabe__title">{{ t('grossanlass.rueckbau.return.title') }}</h3>
    <p class="rueckgabe__hint">{{ t('grossanlass.rueckbau.return.hint') }}</p>

    <EEmptyState
      v-if="!firms.length && !sentFirms.length"
      variant="generic"
      icon="mdi-keyboard-return"
      :title="t('grossanlass.rueckbau.return.emptyTitle')"
      :description="t('grossanlass.rueckbau.return.emptyText')"
    />

    <article v-for="firm in firms" :key="firm" class="firm">
      <header class="firm__head">
        <div>
          <h4>{{ firm }}</h4>
          <span v-if="group(firm).packCode" class="firm__code">{{ group(firm).packCode }}</span>
        </div>
        <v-chip size="small" variant="flat" color="warning">{{ t('grossanlass.rueckbau.return.open', { n: rows(firm).length }) }}</v-chip>
      </header>

      <ol class="firm__steps">
        <li
          v-for="(step, index) in RETURN_STEPS"
          :key="step"
          :class="{ 'is-done': index < group(firm).step, 'is-current': index === group(firm).step }"
        >
          <span class="dot">{{ index < group(firm).step ? '✓' : index + 1 }}</span>
          {{ t(`grossanlass.rueckbau.return.steps.${step}`) }}
        </li>
      </ol>

      <div v-if="group(firm).step === 0" class="firm__scan">
        <ESearchField v-model="scan[firm]" :label="t('grossanlass.rueckbau.return.scan')" @keydown.enter="doScan(firm)" />
        <EButton variant="secondary" @click="doScan(firm)">
          <v-icon icon="mdi-qrcode-scan" start size="18" /> {{ t('grossanlass.rueckbau.return.scanAction') }}
        </EButton>
      </div>

      <ul class="firm__items">
        <li v-for="row in rows(firm)" :key="row.id">
          <ECheckbox
            :model-value="row.collected"
            :label="`${openQty(row)}× ${row.name}`"
            hide-details
            :disabled="group(firm).step > 0"
            @update:model-value="toggleCollected(row.id)"
          />
          <v-chip size="x-small" variant="tonal" :color="CONDITION_COLOR[row.condition]">
            {{ t(`grossanlass.rueckbau.condition.${row.condition}`) }}
          </v-chip>
        </li>
      </ul>

      <p v-if="group(firm).step === 1" class="firm__note">{{ t('grossanlass.rueckbau.return.checkNote') }}</p>
      <p v-if="group(firm).step === 2" class="firm__note">{{ t('grossanlass.rueckbau.return.packNote', { code: group(firm).packCode }) }}</p>

      <footer class="firm__actions">
        <EButton
          v-if="group(firm).step < RETURN_STEPS.length - 1"
          variant="primary"
          size="large"
          :disabled="!canAdvanceReturn(firm)"
          @click="advance(firm)"
        >
          {{ t(`grossanlass.rueckbau.return.next.${RETURN_STEPS[group(firm).step]}`) }}
        </EButton>
        <EButton v-if="group(firm).step === 3" variant="secondary" size="large" @click="openLabel(firm)">
          <v-icon icon="mdi-printer" start size="20" /> {{ t('grossanlass.rueckbau.return.printLabel') }}
        </EButton>
        <EButton v-if="group(firm).step === RETURN_STEPS.length - 1" variant="primary" size="large" @click="sendTransport(firm)">
          <v-icon icon="mdi-truck-fast-outline" start size="20" /> {{ t('grossanlass.rueckbau.return.sendTransport') }}
        </EButton>
      </footer>
    </article>

    <article v-for="firm in sentFirms" :key="`sent-${firm}`" class="firm firm--sent">
      <v-icon icon="mdi-check-circle" color="success" size="20" />
      <span>{{ t('grossanlass.rueckbau.return.sent', { firm, code: group(firm).packCode }) }}</span>
    </article>

    <GrossanlassLabelPrintDialog
      v-model="labelOpen"
      :code="labelCode"
      :title="t('grossanlass.rueckbau.return.labelTitle', { firm: labelFirm })"
      :lines="labelLines"
      @printed="onPrinted"
    />
  </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ECheckbox, ESearchField } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useToast } from '@/composables/useToast'
import GrossanlassLabelPrintDialog from '@/views/grossanlass/packen/GrossanlassLabelPrintDialog.vue'
import {
  RETURN_STEPS,
  advanceReturn,
  canAdvanceReturn,
  firmItems,
  markReturnLabel,
  openQty,
  returnFirms,
  returnGroup,
  scanReturnItem,
  sendReturnTransport,
  toggleCollected,
  useGaRueckbauMock,
} from './gaRueckbauMock'
import { CONDITION_COLOR } from './gaRueckbauUi'

const { t } = useI18n()
const toast = useToast()
const { items, returnGroups } = useGaRueckbauMock()

const firms = computed(() => returnFirms().filter((firm) => !returnGroups.value[firm]?.transportSent))
const sentFirms = computed(() => Object.values(returnGroups.value).filter((entry) => entry.transportSent).map((entry) => entry.firm))
const scan = reactive<Record<string, string>>({})
void items

function group(firm: string) {
  return returnGroup(firm)
}
function rows(firm: string) {
  return firmItems(firm)
}
function doScan(firm: string) {
  const hit = scanReturnItem(firm, scan[firm] ?? '')
  if (hit) toast.success(t('grossanlass.rueckbau.return.scanned', { name: hit.name }))
  else toast.error(t('grossanlass.rueckbau.return.notFound'))
  scan[firm] = ''
}
function advance(firm: string) {
  advanceReturn(firm)
}
function sendTransport(firm: string) {
  if (sendReturnTransport(firm)) toast.success(t('grossanlass.rueckbau.return.sentToast'))
}

const labelOpen = ref(false)
const labelFirm = ref('')
const labelCode = computed(() => (labelFirm.value ? returnGroup(labelFirm.value).packCode : ''))
const labelLines = computed(() => (labelFirm.value ? firmItems(labelFirm.value).map((row) => `${openQty(row)}× ${row.name}`) : []))
function openLabel(firm: string) {
  labelFirm.value = firm
  labelOpen.value = true
}
function onPrinted() {
  if (labelFirm.value) markReturnLabel(labelFirm.value)
}
</script>

<style scoped>
.rueckgabe {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.rueckgabe__title {
  margin: 0;
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}
.rueckgabe__hint {
  margin: 0;
  color: #64748b;
  font-size: 0.86rem;
}
.firm {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-left: 4px solid #f59e0b;
  border-radius: 14px;
  background: #fff;
}
.firm--sent {
  flex-direction: row;
  align-items: center;
  border-left-color: #16a34a;
  background: #f0fdf4;
  font-size: 0.88rem;
}
.firm__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}
.firm__head h4 {
  margin: 0;
}
.firm__code {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  color: #475569;
  font-size: 0.82rem;
}
.firm__steps {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 14px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: 0.8rem;
  color: #94a3b8;
}
.firm__steps li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.firm__steps .dot {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  border: 2px solid currentColor;
  font-size: 0.68rem;
}
.firm__steps .is-current {
  color: #059669;
  font-weight: 700;
}
.firm__steps .is-done {
  color: #0f766e;
}
.firm__scan {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
}
.firm__items {
  display: grid;
  gap: 2px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.firm__items li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.firm__note {
  margin: 0;
  padding: 8px 10px;
  border-radius: 10px;
  background: #f1f5f9;
  font-size: 0.86rem;
  color: #475569;
}
.firm__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
