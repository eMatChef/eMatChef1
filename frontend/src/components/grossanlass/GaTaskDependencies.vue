<template>
  <section class="ga-task-deps" data-testid="task-dependencies-block">
    <GaDependencyList
      v-if="dependencies.length"
      :dependencies="dependencies"
      :can-edit="canEdit"
      :candidates="[]"
      allow-simultaneous
      @remove="remove"
      @release="release"
      @change-mode="changeMode"
    />
    <EButton
      v-if="canEdit && !adding"
      variant="secondary"
      size="small"
      data-testid="task-dependency-add"
      :loading="loading"
      @click="openAdd"
    >
      <v-icon icon="mdi-link-variant-plus" size="16" /> {{ t('grossanlass.aufgaben.deps.addAction') }}
    </EButton>
    <div v-if="adding" class="ga-task-deps__add">
      <GaDependencyList
        :dependencies="[]"
        can-edit
        :candidates="candidates"
        allow-simultaneous
        @add="add"
      />
      <EButton variant="secondary" size="small" @click="adding = false">{{ t('common.cancel') }}</EButton>
    </div>
    <p v-if="error" class="ga-task-deps__error" role="alert">{{ error }}</p>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import GaDependencyList from '@/components/grossanlass/GaDependencyList.vue'
import {
  addGrossanlassTaskDependency,
  changeGrossanlassTaskDependencyMode,
  getGrossanlassTaskDependencies,
  getGrossanlassTasks,
  releaseGrossanlassTaskDependency,
  removeGrossanlassTaskDependency,
  type GaBauprojektTask,
  type GaDependency,
  type GaDependencyMode,
} from '@/api/grossanlassBauprojekt'
import { getGrossanlassGroups, type GrossanlassGroup } from '@/api/grossanlassGroups'
import { buildGrossanlassGroupPathLabel } from '@/utils/grossanlassGroupHierarchy'

/**
 * «Abhängigkeit hinzufügen» an einer Aufgabe im Bauauftrag-Dialog: nacheinander (Startsperre) oder gleichzeitig (Zeitkoordination).
 * Kandidaten sind alle für den Benutzer sichtbaren Aufgaben des Grossanlasses (auch andere Bauaufträge/Ressorts); Rechte setzt der Server durch.
 */
const props = defineProps<{ departmentId: string; groupId: string; taskId: string; canEdit: boolean }>()
const { t } = useI18n()
const dependencies = ref<GaDependency[]>([])
const adding = ref(false)
const loading = ref(false)
const error = ref('')
const allTasks = ref<GaBauprojektTask[]>([])
const groups = ref<GrossanlassGroup[]>([])

const candidates = computed(() => {
  const linked = new Set(dependencies.value.filter((dep) => dep.kind === 'task' && dep.predecessor_id).map((dep) => dep.predecessor_id))
  return allTasks.value
    .filter((task) => task.id !== props.taskId && !linked.has(task.id))
    .map((task) => ({ value: task.id, title: `${buildGrossanlassGroupPathLabel(task.group_id, groups.value, ' › ')} · ${task.title}` }))
    .sort((a, b) => a.title.localeCompare(b.title, 'de'))
})

function applyTask(task: Pick<GaBauprojektTask, 'dependencies'>) {
  // Gruppen-Abhängigkeiten (geerbt) gehören zum Bauauftrag und werden dort bearbeitet.
  dependencies.value = (task.dependencies ?? []).filter((dep) => dep.kind === 'task')
}
async function run(action: () => Promise<Pick<GaBauprojektTask, 'dependencies'>>) {
  error.value = ''
  try {
    applyTask(await action())
  } catch (e) {
    error.value = (e as { response?: { data?: { error?: string } } }).response?.data?.error || t('grossanlass.aufgaben.actionError')
  }
}
async function openAdd() {
  loading.value = true
  error.value = ''
  try {
    if (!allTasks.value.length) {
      ;[allTasks.value, groups.value] = await Promise.all([getGrossanlassTasks(props.departmentId), getGrossanlassGroups(props.departmentId)])
    }
    adding.value = true
  } catch {
    error.value = t('grossanlass.aufgaben.actionError')
  } finally {
    loading.value = false
  }
}
const add = async (predecessorId: string, mode: GaDependencyMode) => {
  await run(() => addGrossanlassTaskDependency(props.departmentId, props.groupId, props.taskId, predecessorId, mode))
  if (!error.value) adding.value = false
}
const remove = (dep: GaDependency) => run(() => removeGrossanlassTaskDependency(props.departmentId, props.groupId, props.taskId, dep.id))
const release = (dep: GaDependency, reason: string) => run(() => releaseGrossanlassTaskDependency(props.departmentId, props.groupId, props.taskId, dep.id, reason))
const changeMode = (dep: GaDependency, mode: GaDependencyMode) => run(() => changeGrossanlassTaskDependencyMode(props.departmentId, props.groupId, props.taskId, dep.id, mode))

onMounted(() => run(() => getGrossanlassTaskDependencies(props.departmentId, props.groupId, props.taskId)))
</script>

<style scoped>
.ga-task-deps {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  grid-column: 1 / -1;
}
.ga-task-deps__add {
  width: 100%;
}
.ga-task-deps__error {
  margin: 0;
  color: rgb(var(--v-theme-error));
  font-size: 0.85rem;
}
</style>
