<template>
  <div class="ga-bauauftraege">
    <div class="ga-bauauftraege__toolbar">
      <p class="ga-bauauftraege__intro">{{ t('grossanlass.materialUebersicht.bauauftraegeIntro') }}</p>
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
      v-else-if="!sections.length"
      icon="mdi-hammer-wrench"
      :title="t('grossanlass.materialUebersicht.emptyBauauftraegeTitle')"
      :description="t('grossanlass.materialUebersicht.emptyBauauftraegeText')"
    >
      <template v-if="canAdd" #actions>
        <EButton @click="openCreate">{{ t('grossanlass.materialUebersicht.addBauauftrag') }}</EButton>
      </template>
    </EEmptyState>

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
          :window-hint="t('grossanlass.planung.ressorts.windowHint')"
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
      :max-width="920"
      :title="projectTitle"
      :retain-focus="false"
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
import { useRoute } from 'vue-router'
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
import { gaBauauftragComposerKey } from '@/views/grossanlass/gaBauauftragComposer'

const route = useRoute()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(() => String(route.params.departmentId || ''))
const groups = ref<GrossanlassGroup[]>([])
const loading = ref(true)
const openIds = ref<string[]>([])
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
  void load()
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
.ga-bauauftraege__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}
.ga-bauauftraege__intro {
  margin: 0;
  flex: 1 1 240px;
  color: var(--color-text-muted, #6b7280);
  font-size: 0.9rem;
}
.ga-bauauftraege__tree {
  border-radius: 10px;
}
.ga-bauauftraege__create {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
</style>
