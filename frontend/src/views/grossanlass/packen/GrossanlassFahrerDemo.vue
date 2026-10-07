<template>
  <v-dialog v-model="open" :fullscreen="!mdAndUp" max-width="440" scrollable>
    <v-card class="fahrer">
      <v-card-title class="fahrer__bar">
        <span>{{ t('grossanlass.packen.driver.title') }}</span>
        <v-chip size="x-small" variant="tonal" color="warning">{{ t('grossanlass.packen.prototype') }}</v-chip>
        <v-btn icon="mdi-close" variant="text" :aria-label="t('common.close')" @click="open = false" />
      </v-card-title>

      <v-card-text v-if="palette" class="fahrer__body">
        <ol class="fahrer__steps">
          <li
            v-for="(label, index) in stepLabels"
            :key="label"
            :class="{ 'is-done': index < step, 'is-current': index === step }"
          >
            <span class="dot">{{ index < step ? '✓' : index + 1 }}</span>
            <span>{{ label }}</span>
          </li>
        </ol>

        <section class="fahrer__card">
          <template v-if="step === 0">
            <h3>{{ t('grossanlass.packen.driver.newOrder') }}</h3>
            <p>{{ t('grossanlass.packen.driver.scanHint') }}</p>
            <div v-if="scanned" class="fahrer__scan">
              <strong>{{ palette.code }} · {{ palette.title }}</strong>
              <span>{{ paletteSummary(palette) }}</span>
            </div>
          </template>

          <template v-else-if="step === 1">
            <h3>{{ t('grossanlass.packen.driver.pickup') }}</h3>
            <p class="fahrer__big">{{ PICKUP_PLACE }}</p>
            <p>{{ t('grossanlass.packen.driver.pickupHint') }}</p>
          </template>

          <template v-else-if="step === 2">
            <h3>{{ t('grossanlass.packen.driver.destination') }}</h3>
            <p class="fahrer__big">{{ palette.projectName }}</p>
            <p>{{ palette.ressort }} · {{ palette.target }}</p>
          </template>

          <template v-else-if="step === 3">
            <h3>{{ t('grossanlass.packen.driver.map') }}</h3>
            <div class="fahrer__map" role="img" :aria-label="t('grossanlass.packen.driver.mapPlaceholder')">
              <span class="fahrer__pin"><v-icon icon="mdi-map-marker" size="32" color="error" /></span>
              <span class="fahrer__map-label">{{ palette.projectName }}</span>
              <small>{{ t('grossanlass.packen.driver.mapPlaceholder') }}</small>
            </div>
            <p>{{ palette.target }}</p>
          </template>

          <template v-else-if="step === 4">
            <h3>{{ t('grossanlass.packen.driver.arrived') }}</h3>
            <p>{{ t('grossanlass.packen.driver.arrivedHint', { place: palette.target }) }}</p>
          </template>

          <template v-else>
            <h3>{{ t('grossanlass.packen.driver.doneTitle') }}</h3>
            <p>{{ t('grossanlass.packen.driver.doneText', { code: palette.code }) }}</p>
          </template>
        </section>
      </v-card-text>

      <v-card-actions v-if="palette" class="fahrer__actions">
        <EButton v-if="step === 0 && !scanned" variant="primary" size="x-large" block @click="scanned = true">
          <v-icon icon="mdi-qrcode-scan" start size="22" />
          {{ t('grossanlass.packen.driver.scan') }}
        </EButton>
        <EButton v-else-if="step < lastStep" variant="primary" size="x-large" block @click="next">
          {{ nextLabel }}
        </EButton>
        <EButton v-else variant="secondary" size="x-large" block @click="open = false">
          {{ t('common.close') }}
        </EButton>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { EButton } from '@/components/form/base'
import { markPaletteDelivered, paletteSummary, type GaPalette } from './gaPackenMock'

const props = defineProps<{ modelValue: boolean; palette: GaPalette | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { t } = useI18n()
const { mdAndUp } = useDisplay()

const PICKUP_PLACE = 'Zentrallager, Rampe 2'
const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const step = ref(0)
const scanned = ref(false)
const stepLabels = computed(() => [
  t('grossanlass.packen.driver.steps.scan'),
  t('grossanlass.packen.driver.steps.pickup'),
  t('grossanlass.packen.driver.steps.destination'),
  t('grossanlass.packen.driver.steps.map'),
  t('grossanlass.packen.driver.steps.unload'),
  t('grossanlass.packen.driver.steps.done'),
])
const lastStep = computed(() => stepLabels.value.length - 1)
const nextLabel = computed(() => {
  if (step.value === 3) return t('grossanlass.packen.driver.arrive')
  if (step.value === 4) return t('grossanlass.packen.driver.unloaded')
  return t('grossanlass.packen.driver.next')
})

function next() {
  if (!props.palette) return
  step.value += 1
  if (step.value === lastStep.value) markPaletteDelivered(props.palette)
}

watch(open, (value) => {
  if (value) {
    step.value = 0
    scanned.value = false
  }
})
</script>

<style scoped>
.fahrer__bar {
  display: flex;
  align-items: center;
  gap: 8px;
}
.fahrer__bar span:first-child {
  flex: 1 1 auto;
}
.fahrer__body {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.fahrer__steps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  gap: 4px;
  overflow-x: auto;
}
.fahrer__steps li {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  min-width: 56px;
  font-size: 0.65rem;
  color: #94a3b8;
  text-align: center;
}
.fahrer__steps .dot {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 2px solid currentColor;
  font-size: 0.72rem;
}
.fahrer__steps .is-current {
  color: #059669;
  font-weight: 700;
}
.fahrer__steps .is-done {
  color: #0f766e;
}
.fahrer__card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 16px;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #fff;
}
.fahrer__card h3 {
  margin: 0;
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}
.fahrer__card p {
  margin: 0;
}
.fahrer__big {
  font-size: 1.35rem;
  font-weight: 700;
}
.fahrer__scan {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #ecfdf5;
  color: #065f46;
}
.fahrer__map {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  min-height: 220px;
  border-radius: 12px;
  background:
    linear-gradient(#cbd5e1 1px, transparent 1px) 0 0 / 32px 32px,
    linear-gradient(90deg, #cbd5e1 1px, transparent 1px) 0 0 / 32px 32px,
    #e2e8f0;
}
.fahrer__map-label {
  font-weight: 700;
}
.fahrer__map small {
  color: #64748b;
}
.fahrer__actions {
  padding: 12px 16px 16px;
}
</style>
