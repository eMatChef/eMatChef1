<template>
  <section class="entsorgung">
    <h3 class="entsorgung__title">{{ t('grossanlass.rueckbau.dispose.title') }}</h3>
    <p class="entsorgung__hint">{{ t('grossanlass.rueckbau.dispose.hint') }}</p>

    <EEmptyState
      v-if="!rows.length"
      variant="generic"
      icon="mdi-delete-outline"
      :title="t('grossanlass.rueckbau.dispose.emptyTitle')"
      :description="t('grossanlass.rueckbau.dispose.emptyText')"
    />

    <article v-for="row in rows" :key="row.id" class="dispose" :class="{ 'dispose--done': row.disposed }">
      <div class="dispose__main">
        <strong>{{ row.qty }}× {{ row.name }}</strong>
        <span>{{ row.project }} · {{ row.origin }}</span>
        <v-chip size="x-small" variant="tonal" :color="CONDITION_COLOR[row.condition]">
          {{ t(`grossanlass.rueckbau.condition.${row.condition}`) }}
        </v-chip>
        <span v-if="row.plannedLabel" class="dispose__planned">{{ row.plannedLabel }}</span>
      </div>
      <div class="dispose__actions">
        <ESelect
          :model-value="row.disposeRoute ?? 'kehricht'"
          :items="routeItems"
          :label="t('grossanlass.rueckbau.decide.disposeRoute')"
          :disabled="row.disposed"
          hide-details
          @update:model-value="setDisposeRoute(row.id, $event as GaDisposeRoute)"
        />
        <EButton v-if="!row.disposed" variant="primary" @click="done(row.id)">
          <v-icon icon="mdi-check" start size="18" /> {{ t('grossanlass.rueckbau.dispose.markDone') }}
        </EButton>
        <v-chip v-else size="small" variant="flat" color="success">{{ t('grossanlass.rueckbau.dispose.done') }}</v-chip>
      </div>
    </article>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ESelect } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useToast } from '@/composables/useToast'
import {
  GA_DISPOSE_ROUTES,
  disposeItems,
  markDisposed,
  setDisposeRoute,
  useGaRueckbauMock,
  type GaDisposeRoute,
} from './gaRueckbauMock'
import { CONDITION_COLOR } from './gaRueckbauUi'

const { t } = useI18n()
const toast = useToast()
const { items } = useGaRueckbauMock()

const rows = computed(() => {
  void items.value
  return disposeItems()
})
const routeItems = computed(() => GA_DISPOSE_ROUTES.map((value) => ({ value, title: t(`grossanlass.rueckbau.disposeRoute.${value}`) })))

function done(id: string) {
  if (markDisposed(id)) toast.success(t('grossanlass.rueckbau.dispose.doneToast'))
}
</script>

<style scoped>
.entsorgung {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.entsorgung__title {
  margin: 0;
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}
.entsorgung__hint {
  margin: 0;
  color: #64748b;
  font-size: 0.86rem;
}
.dispose {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-left: 4px solid #94a3b8;
  border-radius: 14px;
  background: #fff;
}
.dispose--done {
  opacity: 0.75;
  border-left-color: #16a34a;
}
.dispose__main {
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: flex-start;
  font-size: 0.88rem;
}
.dispose__main span {
  color: #64748b;
}
.dispose__planned {
  font-size: 0.78rem;
}
.dispose__actions {
  display: grid;
  grid-template-columns: minmax(180px, 1fr) auto;
  gap: 8px;
  align-items: center;
}
</style>
