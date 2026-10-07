<template>
  <div class="meine">
    <template v-if="!selected">
      <header class="meine__head">
        <div>
          <h2>{{ t('grossanlass.aufgaben.mine.title') }}</h2>
          <p>{{ dayLabel(new Date(), locale) }} · {{ t('grossanlass.aufgaben.mine.subtitle', { name: GA_DEMO_ME }) }}</p>
        </div>
        <v-chip size="x-small" variant="tonal" color="warning">{{ t('grossanlass.aufgaben.prototype') }}</v-chip>
      </header>

      <EEmptyState
        v-if="!mine.length"
        variant="generic"
        icon="mdi-check-all"
        :title="t('grossanlass.aufgaben.mine.emptyTitle')"
        :description="t('grossanlass.aufgaben.mine.emptyText')"
      />

      <ul v-else class="meine__list">
        <li v-for="task in mine" :key="task.id">
          <button type="button" class="meine-card" :class="`meine-card--${statusKey(task)}`" @click="selectedId = task.id">
            <span class="meine-card__icon"><v-icon :icon="KIND_ICON[task.kind]" size="22" /></span>
            <span class="meine-card__body">
              <span class="meine-card__kind">{{ t(`grossanlass.aufgaben.kind.${task.kind}`) }}</span>
              <strong>{{ task.title }}</strong>
              <span class="meine-card__meta">{{ task.origin[task.origin.length - 1] }} · {{ whenLabel(task, locale) }}</span>
              <v-progress-linear
                v-if="task.status !== 'open'"
                :model-value="taskProgress(task)"
                height="6"
                rounded
                color="primary"
                class="mt-2"
              />
            </span>
            <v-chip size="x-small" variant="flat" :color="STATUS_COLOR[statusKey(task)]">
              {{ t(`grossanlass.aufgaben.status.${statusKey(task)}`) }}
            </v-chip>
          </button>
        </li>
      </ul>
    </template>

    <template v-else>
      <EButton variant="text" size="small" class="meine__back" @click="selectedId = null">
        <v-icon icon="mdi-arrow-left" start size="18" />
        {{ t('grossanlass.aufgaben.mine.back') }}
      </EButton>
      <h2 class="meine__title">{{ selected.title }}</h2>
      <GrossanlassAufgabeDetail :task="selected" />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassAufgabeDetail from './GrossanlassAufgabeDetail.vue'
import { GA_DEMO_ME, myTodayTasks, taskProgress, useGaAufgabenMock } from './gaAufgabenMock'
import { KIND_ICON, STATUS_COLOR, dayLabel, statusKey, whenLabel } from './gaAufgabenUi'

const { t, locale } = useI18n()
const { tasks } = useGaAufgabenMock()

const mine = computed(() => myTodayTasks(tasks.value))
const selectedId = ref<string | null>(null)
const selected = computed(() => tasks.value.find((task) => task.id === selectedId.value) ?? null)
</script>

<style scoped>
.meine {
  display: flex;
  flex-direction: column;
  gap: 14px;
  max-width: 560px;
  margin: 0 auto;
  padding: 4px 0 32px;
}
.meine__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}
.meine__head h2,
.meine__title {
  margin: 0;
  font-size: 1.25rem;
}
.meine__head p {
  margin: 2px 0 0;
  color: #64748b;
  font-size: 0.88rem;
}
.meine__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 10px;
}
.meine-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  width: 100%;
  min-height: 72px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-left: 4px solid #f59e0b;
  border-radius: 14px;
  background: #fff;
  text-align: left;
  cursor: pointer;
}
.meine-card--progress {
  border-left-color: #059669;
}
.meine-card--overdue {
  border-left-color: #dc2626;
}
.meine-card--done {
  border-left-color: #16a34a;
  opacity: 0.8;
}
.meine-card__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: #f1f5f9;
  flex: 0 0 40px;
}
.meine-card__body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1 1 auto;
  min-width: 0;
}
.meine-card__kind {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}
.meine-card__meta {
  font-size: 0.84rem;
  color: #475569;
}
.meine__back {
  align-self: flex-start;
}
</style>
