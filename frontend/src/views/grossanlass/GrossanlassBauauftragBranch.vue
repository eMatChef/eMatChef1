<template>
  <v-expansion-panel :value="section.group.id">
    <v-expansion-panel-title>
      <span class="ga-bau-branch__title">
        <GrossanlassGroupNodeIcon :node-type="section.group.node_type" />
        <strong>{{ section.group.name }}</strong>
        <span class="ga-bau-branch__kind">{{ kindLabel(section.group) }}</span>
        <span class="ga-bau-branch__count">{{ projectCount }}</span>
      </span>
    </v-expansion-panel-title>
    <v-expansion-panel-text>
      <ul v-if="section.projects.length" class="ga-bau-branch__projects">
        <li v-for="project in section.projects" :key="project.id" class="ga-bau-branch__project">
          <GrossanlassGroupNodeIcon node-type="bauprojekt" />
          <button type="button" class="ga-bau-branch__name" @click="emit('open', project)">
            {{ project.name }}
          </button>
          <span
            v-if="statusChip(project)"
            class="status-chip"
            :class="`status-chip--${resolveBuildStatus(project)}`"
          >{{ statusChip(project) }}</span>
          <span v-if="windowText(project)" class="window-chip">{{ windowText(project) }}</span>
          <EButton variant="secondary" size="small" @click="emit('open', project)">
            {{ t('grossanlass.planung.ressorts.openProject') }}
          </EButton>
        </li>
      </ul>
      <p v-else-if="!section.children.length" class="ga-bau-branch__empty">
        {{ t('grossanlass.materialUebersicht.noBauauftragYet') }}
      </p>
      <v-expansion-panels
        v-if="section.children.length"
        :model-value="childIds"
        multiple
        class="ga-bau-branch__nested"
      >
        <GrossanlassBauauftragBranch
          v-for="child in section.children"
          :key="child.group.id"
          :section="child"
          :kind-label="kindLabel"
          @open="emit('open', $event)"
        />
      </v-expansion-panels>
    </v-expansion-panel-text>
  </v-expansion-panel>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import GrossanlassGroupNodeIcon from '@/components/grossanlass/GrossanlassGroupNodeIcon.vue'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import { formatBauprojektWindow } from '@/utils/grossanlassBauprojektWindow'
import {
  gaBuildStatusI18nKey,
  resolveBuildStatus,
  showsGaBuildStatus,
} from '@/utils/grossanlassBuildStatus'

export type GaBauauftragSection = {
  group: GrossanlassGroup
  projects: GrossanlassGroup[]
  children: GaBauauftragSection[]
}

defineOptions({ name: 'GrossanlassBauauftragBranch' })

const props = defineProps<{
  section: GaBauauftragSection
  kindLabel: (group: GrossanlassGroup) => string
}>()

const emit = defineEmits<{
  open: [group: GrossanlassGroup]
}>()

const { t } = useI18n()
const childIds = computed(() => props.section.children.map((child) => child.group.id))

const projectCount = computed(() => {
  const walk = (section: GaBauauftragSection): number =>
    section.projects.length + section.children.reduce((sum, child) => sum + walk(child), 0)
  return walk(props.section)
})

function windowText(group: GrossanlassGroup): string {
  return formatBauprojektWindow(group.window_start, group.window_end)
}

function statusChip(group: GrossanlassGroup): string {
  if (!showsGaBuildStatus(group)) return ''
  return t(gaBuildStatusI18nKey(resolveBuildStatus(group)))
}
</script>

<style scoped>
.ga-bau-branch__title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.ga-bau-branch__kind,
.ga-bau-branch__empty {
  font-size: 0.78rem;
  color: #6b7280;
}
.ga-bau-branch__count {
  margin-left: auto;
  font-size: 0.78rem;
  color: #64748b;
}
.ga-bau-branch__projects {
  list-style: none;
  margin: 0 0 12px;
  padding: 0;
  display: grid;
  gap: 8px;
}
.ga-bau-branch__project {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
}
.ga-bau-branch__name {
  border: 0;
  background: transparent;
  padding: 0;
  font: inherit;
  font-weight: 600;
  text-align: left;
  cursor: pointer;
}
.ga-bau-branch__name:hover {
  text-decoration: underline;
}
.window-chip,
.status-chip {
  font-size: 0.72rem;
  font-weight: 600;
  border-radius: 999px;
  padding: 1px 8px;
  white-space: nowrap;
}
.window-chip {
  color: #475569;
  background: #f1f5f9;
}
.status-chip--planned { background: #e2e8f0; color: #334155; }
.status-chip--build { background: #fde68a; color: #92400e; }
.status-chip--use { background: #99f6e4; color: #115e59; }
.status-chip--teardown { background: #fed7aa; color: #9a3412; }
.status-chip--done { background: #bbf7d0; color: #166534; }
.status-chip--aborted { background: #fecaca; color: #991b1b; }
.ga-bau-branch__nested {
  margin-top: 4px;
}
</style>
