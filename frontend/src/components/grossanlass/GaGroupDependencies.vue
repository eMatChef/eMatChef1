<template>
  <section class="ga-group-deps" data-testid="group-dependencies">
    <h4>{{ t('grossanlass.aufgaben.deps.groupTitle') }}</h4>
    <p v-if="waiting" class="ga-group-deps__waiting">{{ t('grossanlass.aufgaben.deps.groupWaiting') }}</p>
    <GaDependencyList
      :dependencies="dependencies"
      :can-edit="canEdit"
      :candidates="candidates"
      @add="add"
      @remove="remove"
      @release="release"
    />
    <p v-if="error" class="ga-group-deps__error" role="alert">{{ error }}</p>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import GaDependencyList from '@/components/grossanlass/GaDependencyList.vue'
import {
  addGrossanlassGroupDependency,
  getGrossanlassGroupDependencies,
  releaseGrossanlassGroupDependency,
  removeGrossanlassGroupDependency,
  type GaDependency,
  type GaGroupDependencies,
} from '@/api/grossanlassBauprojekt'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import { buildGrossanlassGroupPathLabel, isLeafGroup } from '@/utils/grossanlassGroupHierarchy'

/** Bauauftrag wartet auf andere Bauaufträge (auch aus anderen Ressorts desselben Grossanlasses). */
const props = defineProps<{ departmentId: string; groupId: string; groups: GrossanlassGroup[]; canEdit: boolean }>()
const emit = defineEmits<{ changed: [] }>()
const { t } = useI18n()
const state = ref<GaGroupDependencies>({ dependencies: [], waiting: false })
const error = ref('')
const dependencies = computed(() => state.value.dependencies)
const waiting = computed(() => state.value.waiting)
const candidates = computed(() =>
  props.groups
    .filter((group) => group.id !== props.groupId && isLeafGroup(group) && !state.value.dependencies.some((dep) => dep.predecessor_id === group.id))
    .map((group) => ({ value: group.id, title: buildGrossanlassGroupPathLabel(group.id, props.groups, ' › ') }))
    .sort((a, b) => a.title.localeCompare(b.title, 'de')),
)

async function run(action: () => Promise<GaGroupDependencies>) {
  error.value = ''
  try {
    state.value = await action()
    emit('changed')
  } catch (e) {
    error.value = (e as { response?: { data?: { error?: string } } }).response?.data?.error || t('grossanlass.aufgaben.actionError')
  }
}
const add = (predecessorId: string) => run(() => addGrossanlassGroupDependency(props.departmentId, props.groupId, predecessorId))
const remove = (dep: GaDependency) => run(() => removeGrossanlassGroupDependency(props.departmentId, props.groupId, dep.id))
const release = (dep: GaDependency, reason: string) => run(() => releaseGrossanlassGroupDependency(props.departmentId, props.groupId, dep.id, reason))

onMounted(() => run(() => getGrossanlassGroupDependencies(props.departmentId, props.groupId)))
</script>

<style scoped>
.ga-group-deps {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 16px;
}
.ga-group-deps h4 {
  margin: 0;
}
.ga-group-deps__waiting {
  margin: 0;
  color: rgb(var(--v-theme-warning));
  font-size: 0.85rem;
}
.ga-group-deps__error {
  margin: 0;
  color: rgb(var(--v-theme-error));
  font-size: 0.85rem;
}
</style>
