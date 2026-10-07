<template>
  <section class="fahr-steps">
    <div class="fahr-route">
      <div class="fahr-route__stop">
        <v-icon icon="mdi-map-marker-outline" size="18" />
        <div>
          <span class="fahr-route__label">{{ t('grossanlass.aufgaben.route.from') }}</span>
          <strong>{{ task.route?.from }}</strong>
        </div>
      </div>
      <v-icon icon="mdi-arrow-down" size="18" class="fahr-route__arrow" />
      <div class="fahr-route__stop">
        <v-icon icon="mdi-flag-checkered" size="18" />
        <div>
          <span class="fahr-route__label">{{ t('grossanlass.aufgaben.route.to') }}</span>
          <strong>{{ task.route?.to }}</strong>
        </div>
      </div>
    </div>

    <dl class="fahr-facts">
      <div>
        <dt>{{ t('grossanlass.aufgaben.route.time') }}</dt>
        <dd>{{ whenLabel(task, locale) }}</dd>
      </div>
      <div>
        <dt>{{ t('grossanlass.aufgaben.route.vehicle') }}</dt>
        <dd>{{ task.route?.vehicle }}</dd>
      </div>
      <div class="fahr-facts__wide">
        <dt>{{ t('grossanlass.aufgaben.route.cargo') }}</dt>
        <dd>
          <ul>
            <li v-for="item in task.route?.cargo ?? []" :key="item">{{ item }}</li>
          </ul>
        </dd>
      </div>
    </dl>

    <ol class="fahr-stepper">
      <li
        v-for="(step, index) in GA_FAHRAUFTRAG_STEPS"
        :key="step"
        class="fahr-stepper__item"
        :class="{
          'fahr-stepper__item--done': index < task.routeStepIndex || task.status === 'done',
          'fahr-stepper__item--current': index === task.routeStepIndex && task.status !== 'done',
        }"
      >
        <span class="fahr-stepper__dot">
          <v-icon v-if="index < task.routeStepIndex || task.status === 'done'" icon="mdi-check" size="14" />
          <template v-else>{{ index + 1 }}</template>
        </span>
        <span class="fahr-stepper__label">{{ t(`grossanlass.aufgaben.route.steps.${step}`) }}</span>
      </li>
    </ol>

    <EButton
      v-if="task.status !== 'done' && !readOnly"
      class="fahr-next"
      variant="primary"
      size="large"
      block
      @click="advanceRoute(task)"
    >
      {{ nextLabel }}
    </EButton>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import {
  GA_FAHRAUFTRAG_STEPS,
  advanceRoute,
  nextRouteStep,
  type GaAufgabe,
} from './gaAufgabenMock'
import { whenLabel } from './gaAufgabenUi'

const props = defineProps<{ task: GaAufgabe; readOnly?: boolean }>()
const { t, locale } = useI18n()

const nextLabel = computed(() => {
  const next = nextRouteStep(props.task)
  if (props.task.routeStepIndex < 0 && next) {
    return t('grossanlass.aufgaben.route.accept')
  }
  if (!next) return t('grossanlass.aufgaben.route.finish')
  return t('grossanlass.aufgaben.route.next', { step: t(`grossanlass.aufgaben.route.steps.${next}`) })
})
</script>

<style scoped>
.fahr-steps {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.fahr-route {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.fahr-route__stop {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}
.fahr-route__label {
  display: block;
  font-size: 0.72rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.fahr-route__arrow {
  margin-left: 2px;
  color: #94a3b8;
}
.fahr-facts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin: 0;
}
.fahr-facts dt {
  font-size: 0.72rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.fahr-facts dd {
  margin: 2px 0 0;
  font-weight: 600;
}
.fahr-facts__wide {
  grid-column: 1 / -1;
}
.fahr-facts ul {
  margin: 0;
  padding-left: 18px;
  font-weight: 500;
}
.fahr-stepper {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.fahr-stepper__item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  color: #64748b;
}
.fahr-stepper__item--current {
  background: #ecfdf5;
  color: #065f46;
  font-weight: 600;
}
.fahr-stepper__item--done {
  color: #0f766e;
}
.fahr-stepper__dot {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 2px solid currentColor;
  font-size: 0.75rem;
  flex: 0 0 24px;
}
.fahr-stepper__item--done .fahr-stepper__dot {
  background: currentColor;
  color: #fff;
}
.fahr-stepper__item--done .fahr-stepper__dot :deep(.v-icon) {
  color: #fff;
}
.fahr-next {
  min-height: 52px;
}
</style>
