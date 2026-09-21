<template>
  <v-expansion-panel :value="group.id">
    <v-expansion-panel-title>
      <span class="branch__title">
        <GrossanlassGroupNodeIcon :node-type="group.node_type" />
        <strong>{{ group.name }}</strong>
        <span class="kind-badge">{{ kindLabel(group) }}</span>
        <span
          v-if="buildStatusChip(group)"
          class="status-chip"
          :class="`status-chip--${resolveBuildStatus(group)}`"
        >{{ buildStatusChip(group) }}</span>
        <span v-if="usageWindow(group)" class="window-chip">{{ usageWindow(group) }}</span>
        <span v-if="shareCaption(group)" class="share-badge">{{ shareCaption(group) }}</span>
        <span class="branch__count">{{ childCount }}</span>
      </span>
    </v-expansion-panel-title>
    <v-expansion-panel-text>
      <div v-if="canCreateChild(group) || canEditGroup(group) || showSelfDelete" class="branch__add">
        <EButton
          v-if="canCreateChild(group)"
          variant="secondary"
          size="small"
          @click="openCreateChild('ressort', group.id)"
        >
          {{ t('grossanlass.planung.ressorts.modalNewUnterressort') }}
        </EButton>
        <EButton
          v-if="canShareGroup(group)"
          variant="secondary"
          size="small"
          @click="openShare(group)"
        >
          {{ t('grossanlass.planung.ressorts.shareWith') }}
        </EButton>
        <EButton
          v-if="canEditGroup(group)"
          variant="secondary"
          size="small"
          @click="openEditGroup(group)"
        >
          {{ t('common.edit') }}
        </EButton>
        <EButton
          v-if="showSelfDelete"
          variant="danger"
          size="small"
          :disabled="deletingGroupId === group.id"
          @click="confirmDeleteGroup(group)"
        >
          {{ t('common.delete') }}
        </EButton>
      </div>

      <v-expansion-panels v-if="childAreas.length" v-model="expandedChildIds" multiple class="branch__nested">
        <GrossanlassMeinRessortBranch
          v-for="child in childAreas"
          :key="child.id"
          :group="child"
        />
      </v-expansion-panels>

      <ul v-if="projects.length" class="bauprojekt-rows">
        <li v-for="project in projects" :key="project.id" class="bauprojekt-row">
          <GrossanlassGroupNodeIcon node-type="bauprojekt" />
          <div class="bauprojekt-row__main">
            <button type="button" class="bauprojekt-row__name" @click="openProject(project)">
              {{ project.name }}
            </button>
            <span
              v-if="buildStatusChip(project)"
              class="status-chip"
              :class="`status-chip--${resolveBuildStatus(project)}`"
            >{{ buildStatusChip(project) }}</span>
            <span class="bauprojekt-row__meta">{{ projectMaterialSummary(project.id) }}</span>
            <span v-if="shareCaption(project)" class="share-badge">{{ shareCaption(project) }}</span>
          </div>
          <div class="bauprojekt-row__actions">
            <EButton variant="secondary" size="small" @click="openProject(project)">
              {{ t('grossanlass.planung.ressorts.openProject') }}
            </EButton>
            <EButton
              v-if="canShareGroup(project)"
              variant="secondary"
              size="small"
              @click="openShare(project)"
            >
              {{ t('grossanlass.planung.ressorts.shareWith') }}
            </EButton>
            <EButton
              v-if="canDeleteGroup(project)"
              variant="danger"
              size="small"
              :disabled="deletingGroupId === project.id"
              @click="confirmDeleteGroup(project)"
            >
              {{ t('common.delete') }}
            </EButton>
          </div>
        </li>
      </ul>
      <p v-else-if="!childAreas.length" class="no-wishes">{{ t('grossanlass.meinRessort.noChildrenYet') }}</p>
    </v-expansion-panel-text>
  </v-expansion-panel>
</template>

<script setup lang="ts">
import { computed, inject, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import { formatBauprojektWindow } from '@/utils/grossanlassBauprojektWindow'
import {
  gaBuildStatusI18nKey,
  resolveBuildStatus,
  showsGaBuildStatus,
} from '@/utils/grossanlassBuildStatus'
import GrossanlassGroupNodeIcon from '@/components/grossanlass/GrossanlassGroupNodeIcon.vue'
import {
  MEIN_RESSORT_TREE_KEY,
  type MeinRessortTreeApi,
} from '@/components/grossanlass/meinRessortTree'

defineOptions({ name: 'GrossanlassMeinRessortBranch' })

const props = withDefaults(defineProps<{
  group: GrossanlassGroup
  allowSelfDelete?: boolean
}>(), {
  allowSelfDelete: true,
})

const { t } = useI18n()
const tree = inject<MeinRessortTreeApi>(MEIN_RESSORT_TREE_KEY)
if (!tree) {
  throw new Error('GrossanlassMeinRessortBranch needs Mein Ressort tree context')
}

const {
  unterressortsOf,
  bauprojekteOf,
  canCreateChild,
  canEditGroup,
  canDeleteGroup,
  canShareGroup,
  deletingGroupId,
  projectMaterialSummary,
  openCreateChild,
  openProject,
  openShare,
  openEditGroup,
  confirmDeleteGroup,
  kindLabel,
  shareCaption,
} = tree

const childAreas = computed(() => unterressortsOf(props.group))
const projects = computed(() => bauprojekteOf(props.group))
const childCount = computed(() => childAreas.value.length + projects.value.length)
const showSelfDelete = computed(() => props.allowSelfDelete && canDeleteGroup(props.group))
const expandedChildIds = ref<string[]>([])

function usageWindow(group: GrossanlassGroup): string {
  return formatBauprojektWindow(group.window_start, group.window_end)
}

function buildStatusChip(group: GrossanlassGroup): string {
  if (!showsGaBuildStatus(group)) return ''
  return t(gaBuildStatusI18nKey(resolveBuildStatus(group)))
}

watch(
  childAreas,
  (areas) => {
    const ids = areas.map((area) => area.id)
    const kept = expandedChildIds.value.filter((id) => ids.includes(id))
    expandedChildIds.value = kept.length > 0 ? kept : ids
  },
  { immediate: true },
)
</script>

<style scoped>
.branch__title {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.branch__count {
  margin-left: 4px;
  font-size: 0.75rem;
  color: #64748b;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 1px 8px;
}
.branch__nested {
  margin-top: 8px;
}
.branch__add {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}
.kind-badge {
  font-size: 0.78rem;
  color: #6b7280;
}
.window-chip {
  font-size: 0.75rem;
  color: #475569;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 1px 8px;
  white-space: nowrap;
}
.status-chip {
  font-size: 0.72rem;
  font-weight: 600;
  border-radius: 999px;
  padding: 1px 8px;
  white-space: nowrap;
}
.status-chip--planned {
  background: #e2e8f0;
  color: #334155;
}
.status-chip--build {
  background: #fde68a;
  color: #92400e;
}
.status-chip--use {
  background: #99f6e4;
  color: #115e59;
}
.status-chip--teardown {
  background: #fed7aa;
  color: #9a3412;
}
.status-chip--done {
  background: #bbf7d0;
  color: #166534;
}
.status-chip--aborted {
  background: #fecaca;
  color: #991b1b;
}
.share-badge {
  font-size: 0.72rem;
  color: #0f766e;
  background: #ccfbf1;
  border-radius: 999px;
  padding: 1px 8px;
}
.bauprojekt-rows {
  list-style: none;
  margin: 0;
  padding: 0;
}
.bauprojekt-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid #eef2f6;
}
.bauprojekt-row:last-child {
  border-bottom: 0;
}
.bauprojekt-row__main {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
  flex: 1;
}
.bauprojekt-row__name {
  border: 0;
  background: transparent;
  padding: 0;
  text-align: left;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
  color: inherit;
}
.bauprojekt-row__name:hover {
  text-decoration: underline;
}
.bauprojekt-row__meta {
  font-size: 0.78rem;
  color: #64748b;
}
.bauprojekt-row__actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}
.action-btn {
  border: 0;
  background: transparent;
  cursor: pointer;
  padding: 4px;
  color: #64748b;
}
.action-btn:disabled {
  opacity: 0.5;
  cursor: default;
}
.action-btn-danger {
  color: #b91c1c;
}
.no-wishes {
  margin: 0;
  font-size: 0.85rem;
  color: #9ca3af;
}
</style>
