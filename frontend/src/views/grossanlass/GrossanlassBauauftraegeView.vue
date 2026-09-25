<template>
  <div class="ga-bauauftraege">
    <div class="ga-bauauftraege__subtabs">
      <v-tabs v-model="listMode" class="materials-view-tabs" color="primary">
        <v-tab value="mine">{{ t('grossanlass.planung.bauListMine') }}</v-tab>
        <v-tab value="tree">{{ t('grossanlass.planung.bauListTree') }}</v-tab>
      </v-tabs>
      <EButton
        v-if="canAdd"
        variant="primary"
        size="small"
        @click="openCreate"
      >
        {{ t('grossanlass.materialUebersicht.addBauauftrag') }}
      </EButton>
    </div>

    <ELoadingState v-if="loading" variant="inline" :message="t('common.loading')" />

    <EEmptyState
      v-else-if="listMode === 'mine' ? !projectRows.length : !sections.length"
      icon="mdi-hammer-wrench"
      :title="t('grossanlass.materialUebersicht.emptyBauauftraegeTitle')"
      :description="t('grossanlass.materialUebersicht.emptyBauauftraegeText')"
    >
      <template v-if="canAdd" #actions>
        <EButton @click="openCreate">{{ t('grossanlass.materialUebersicht.addBauauftrag') }}</EButton>
      </template>
    </EEmptyState>

    <ul v-else-if="listMode === 'mine'" class="ga-bauauftraege__list">
      <li v-for="project in projectRows" :key="project.id" @click="openProject(project)">
        <span class="ga-bauauftraege__name">{{ project.name }}</span>
        <span v-if="parentPath(project)" class="ga-bauauftraege__path">{{ parentPath(project) }}</span>
        <span
          v-if="statusChip(project)"
          class="status-chip"
          :class="`status-chip--${resolveBuildStatus(project)}`"
        >{{ statusChip(project) }}</span>
        <span v-if="windowText(project)" class="window-chip">{{ windowText(project) }}</span>
        <EButton variant="secondary" size="small" @click.stop="openProject(project)">
          {{ t('grossanlass.planung.ressorts.openProject') }}
        </EButton>
      </li>
    </ul>

    <v-expansion-panels v-else v-model="openIds" multiple class="e-accordions ga-bauauftraege__tree">
      <GrossanlassBauauftragBranch
        v-for="section in sections"
        :key="section.group.id"
        :section="section"
        :kind-label="kindLabel"
        @open="openProject"
      />
    </v-expansion-panels>

    <EDialog
      v-model="showCreate"
      :title="t('grossanlass.materialUebersicht.addBauauftragTitle')"
      max-width="640"
      :retain-focus="false"
    >
      <div class="ga-bauauftraege__create">
        <ETextField
          v-model="createForm.name"
          :label="t('grossanlass.planung.ressorts.nameLabelBauprojekt')"
          :placeholder="t('grossanlass.planung.ressorts.namePlaceholderBauprojekt')"
          hide-details="auto"
        />
        <ESelect
          v-model="createForm.parent_id"
          :items="parentItems"
          :label="t('grossanlass.materialUebersicht.bauauftragBereichLabel')"
          hide-details
        />
        <GaBuildMetaFields
          :department-id="departmentId"
          v-model:start="createForm.window_start"
          v-model:end="createForm.window_end"
          v-model:status="createForm.build_status"
          :window-label="t('grossanlass.planung.ressorts.windowLabel')"
        />
      </div>
      <template #actions>
        <EButton variant="secondary" @click="showCreate = false">{{ t('common.cancel') }}</EButton>
        <EButton
          :disabled="!createForm.name.trim() || !createForm.parent_id || saving"
          :loading="saving"
          @click="submitCreate"
        >
          {{ t('common.save') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="showProject"
      :max-width="1400"
      :title="projectTitle"
      :retain-focus="false"
      highlight-outside
    >
      <GrossanlassBauprojektPanel
        v-if="projectGroup && departmentId"
        :department-id="departmentId"
        :group-id="projectGroup.id"
        @meta-saved="onProjectMetaSaved"
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="showProject = false">
          {{ t('settings.groups.close') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from '@/composables/useToast'
import { useGrossanlassRessortScope } from '@/composables/useGrossanlassRessortScope'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import GaBuildMetaFields from '@/components/grossanlass/GaBuildMetaFields.vue'
import GrossanlassBauprojektPanel from '@/components/grossanlass/GrossanlassBauprojektPanel.vue'
import GrossanlassBauauftragBranch, {
  type GaBauauftragSection,
} from '@/views/grossanlass/GrossanlassBauauftragBranch.vue'
import {
  createGrossanlassGroup,
  getGrossanlassGroups,
  type GrossanlassGroup,
} from '@/api/grossanlassGroups'
import {
  flattenTreeWithLevel,
  grossanlassGroupIndentTitle,
  nestTreeWithLevel,
  type NestedTreeNode,
} from '@/utils/grossanlassGroupHierarchy'
import { grossanlassGroupNodeKindKey } from '@/utils/grossanlassGroupNode'
import { formatBauprojektWindow } from '@/utils/grossanlassBauprojektWindow'
import {
  gaBuildStatusI18nKey,
  resolveBuildStatus,
  showsGaBuildStatus,
} from '@/utils/grossanlassBuildStatus'
import { gaBauauftragComposerKey } from '@/views/grossanlass/gaBauauftragComposer'
import '@/styles/views/materials-view-tabs.css'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(() => String(route.params.departmentId || ''))
const groups = ref<GrossanlassGroup[]>([])
const loading = ref(true)
const openIds = ref<string[]>([])
const listMode = ref<'mine' | 'tree'>('mine')
const showCreate = ref(false)
const showProject = ref(false)
const projectGroup = ref<GrossanlassGroup | null>(null)
const saving = ref(false)
const createForm = ref({
  name: '',
  parent_id: '',
  window_start: '',
  window_end: '',
  build_status: '',
})

const {
  canCreateChild,
  isInAssignedRessortBranch,
} = useGrossanlassRessortScope(groups)

const visibleGroups = computed(() =>
  groups.value.filter((group) => isInAssignedRessortBranch(group)),
)

function kindLabel(group: GrossanlassGroup): string {
  return t(grossanlassGroupNodeKindKey(group.node_type))
}

function toSection(node: NestedTreeNode<GrossanlassGroup>): GaBauauftragSection {
  return {
    group: node,
    projects: node.children.filter((child) => child.node_type === 'bauprojekt'),
    children: node.children
      .filter((child) => child.node_type !== 'bauprojekt')
      .map(toSection),
  }
}

const sections = computed(() =>
  nestTreeWithLevel(visibleGroups.value)
    .filter((node) => node.node_type !== 'bauprojekt')
    .map(toSection),
)

const projectRows = computed(() =>
  visibleGroups.value
    .filter((group) => group.node_type === 'bauprojekt')
    .slice()
    .sort((a, b) => a.name.localeCompare(b.name, 'de')),
)

function parentPath(group: GrossanlassGroup): string {
  const names: string[] = []
  let parentId = group.parent_id
  const guard = new Set<string>()
  while (parentId && !guard.has(parentId)) {
    guard.add(parentId)
    const parent = groups.value.find((row) => row.id === parentId)
    if (!parent) break
    names.unshift(parent.name)
    parentId = parent.parent_id
  }
  return names.join(' · ')
}

function windowText(group: GrossanlassGroup): string {
  return formatBauprojektWindow(group.window_start, group.window_end)
}

function statusChip(group: GrossanlassGroup): string {
  if (!showsGaBuildStatus(group)) return ''
  return t(gaBuildStatusI18nKey(resolveBuildStatus(group)))
}

const parentItems = computed(() =>
  flattenTreeWithLevel(visibleGroups.value)
    .filter((group) => canCreateChild(group))
    .map((group) => ({
      title: `${grossanlassGroupIndentTitle(group)} · ${kindLabel(group)}`,
      value: group.id,
    })),
)

const canAdd = computed(() => parentItems.value.length > 0)
const projectTitle = computed(() =>
  projectGroup.value
    ? t('grossanlass.planung.ressorts.projectTitle', { name: projectGroup.value.name })
    : t('grossanlass.planung.ressorts.openProject'),
)

function openCreate() {
  createForm.value = {
    name: '',
    parent_id: parentItems.value[0]?.value || '',
    window_start: '',
    window_end: '',
    build_status: '',
  }
  showCreate.value = true
}

function openProject(group: GrossanlassGroup) {
  projectGroup.value = group
  showProject.value = true
}

function onProjectMetaSaved(group: { id: string; window_start?: string | null; window_end?: string | null; build_status?: string | null }) {
  groups.value = groups.value.map((row) => (row.id === group.id ? { ...row, ...group } : row))
  if (projectGroup.value?.id === group.id) {
    projectGroup.value = { ...projectGroup.value, ...group }
  }
}

async function refresh() {
  if (!departmentId.value) return
  groups.value = await getGrossanlassGroups(departmentId.value)
  const rootIds = sections.value.map((section) => section.group.id)
  const kept = openIds.value.filter((id) => rootIds.includes(id))
  openIds.value = kept.length > 0 ? kept : rootIds
}

async function load() {
  if (!departmentId.value) return
  loading.value = true
  try {
    await refresh()
  } catch {
    groups.value = []
  } finally {
    loading.value = false
  }
}

async function submitCreate() {
  if (!departmentId.value || !createForm.value.name.trim() || !createForm.value.parent_id || saving.value) {
    return
  }
  saving.value = true
  try {
    const created = await createGrossanlassGroup(departmentId.value, {
      name: createForm.value.name.trim(),
      parent_id: createForm.value.parent_id,
      kind: 'teilbereich',
      window_start: createForm.value.window_start || null,
      window_end: createForm.value.window_end || null,
      build_status: createForm.value.build_status || null,
    })
    await refresh()
    showCreate.value = false
    toast.success(t('grossanlass.materialUebersicht.bauauftragCreated'))
    openProject(groups.value.find((row) => row.id === created.id) ?? created)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.planung.ressorts.errorSave'))
  } finally {
    saving.value = false
  }
}

const composer = inject(gaBauauftragComposerKey, null)
watch(canAdd, (value) => {
  if (composer) composer.canAdd = value
}, { immediate: true })

onMounted(() => {
  if (composer) composer.open = openCreate
  void load().then(() => {
    const projectId = String(route.query.project || '')
    if (projectId) {
      const found = groups.value.find((group) => group.id === projectId)
      if (found) openProject(found)
    }
    if (String(route.query.create || '') === '1') openCreate()
    if (!projectId && String(route.query.create || '') !== '1') return
    const { project: _project, create: _create, ...rest } = route.query
    void router.replace({ query: rest })
  })
})

onBeforeUnmount(() => {
  if (!composer) return
  composer.open = () => {}
  composer.canAdd = false
})
</script>

<style scoped>
.ga-bauauftraege {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 4px 0 24px;
}
.ga-bauauftraege__subtabs {
  display: flex;
  align-items: center;
  gap: 12px;
}
.ga-bauauftraege__subtabs :deep(.v-tabs.materials-view-tabs) {
  flex: 0 0 auto;
  width: fit-content;
  max-width: 100%;
}
.ga-bauauftraege__tree {
  border-radius: 10px;
}
.ga-bauauftraege__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.ga-bauauftraege__list li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
}
.ga-bauauftraege__list li:hover {
  background: #f8fafc;
}
.ga-bauauftraege__name {
  font-weight: 650;
}
.ga-bauauftraege__path {
  color: #64748b;
  font-size: 0.82rem;
}
.window-chip,
.status-chip {
  font-size: 0.72rem;
  font-weight: 600;
  border-radius: 999px;
  padding: 1px 8px;
  white-space: nowrap;
}
.window-chip { color: #475569; background: #f1f5f9; }
.status-chip--planned { background: #e2e8f0; color: #334155; }
.status-chip--build { background: #fde68a; color: #92400e; }
.status-chip--use { background: #99f6e4; color: #115e59; }
.status-chip--teardown { background: #fed7aa; color: #9a3412; }
.status-chip--done { background: #bbf7d0; color: #166534; }
.status-chip--aborted { background: #fecaca; color: #991b1b; }
.ga-bauauftraege__create {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
</style>
