<template>
  <section v-if="chain.length" class="dep">
    <h4>{{ t('grossanlass.auftraege.dep.title') }}</h4>
    <ol class="dep__chain">
      <li v-for="task in chain" :key="task.id" class="dep__node" :class="`dep__node--${task.status}`">
        <span class="dep__title">{{ task.title }}</span>
        <v-chip size="x-small" variant="flat" :color="task.status === 'done' ? 'success' : task.status === 'progress' ? 'primary' : 'grey'">
          {{ t(`grossanlass.aufgaben.status.${task.status}`) }}
        </v-chip>
        <small v-if="blockedBy(task).length" class="dep__wait">
          <v-icon icon="mdi-timer-sand" size="12" /> {{ t('grossanlass.auftraege.dep.waits', { task: blockedBy(task).map((entry) => entry.title).join(', ') }) }}
        </small>
      </li>
      <li class="dep__node dep__node--end"><v-icon icon="mdi-flag-checkered" size="16" /> {{ t('grossanlass.auftraege.dep.finished') }}</li>
    </ol>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { blockedBy, type GaAufgabe } from '@/views/grossanlass/aufgaben/gaAufgabenMock'

const props = defineProps<{ tasks: GaAufgabe[] }>()
const { t } = useI18n()

/** Aufgaben, die an einer Abhängigkeit beteiligt sind, in zeitlicher Reihenfolge. */
const chain = computed(() => {
  const ids = new Set(props.tasks.flatMap((task) => (task.dependsOn ?? []).concat(task.dependsOn?.length ? [task.id] : [])))
  return props.tasks.filter((task) => ids.has(task.id)).sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime())
})
</script>

<style scoped>
.dep h4 {
  margin: 0 0 6px;
  font-size: 0.72rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748b;
}
.dep__chain {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.dep__node {
  position: relative;
  display: inline-flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  font-size: 0.84rem;
}
.dep__node:not(:last-child)::after {
  content: '→';
  position: absolute;
  right: -14px;
  top: 10px;
  color: #94a3b8;
}
.dep__node--done {
  background: #f0fdf4;
}
.dep__node--end {
  flex-direction: row;
  align-items: center;
  gap: 6px;
  background: #ecfdf5;
  font-weight: 600;
}
.dep__title {
  font-weight: 600;
}
.dep__wait {
  display: flex;
  align-items: center;
  gap: 4px;
  color: #92400e;
}
</style>
