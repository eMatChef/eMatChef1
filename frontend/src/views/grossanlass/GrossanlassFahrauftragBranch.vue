<template>
  <v-expansion-panel :value="section.group.id">
    <v-expansion-panel-title>
      <span class="ga-fahr-branch__title">
        <GrossanlassGroupNodeIcon :node-type="section.group.node_type" />
        <strong>{{ section.group.name }}</strong>
        <span class="ga-fahr-branch__kind">{{ kindLabel(section.group) }}</span>
        <span class="ga-fahr-branch__count">{{ tripCount }}</span>
      </span>
    </v-expansion-panel-title>
    <v-expansion-panel-text>
      <GrossanlassFahrauftragList
        v-if="section.trips.length"
        class="ga-fahr-branch__list"
        :rows="section.trips"
        :busy-id="busyId"
        :can-start-trip="canStartTrip"
        :read-only="readOnly"
        clickable
        :show-title="false"
        @toggle-packed="emit('toggle-packed', $event)"
        @release="emit('release', $event)"
        @issue="emit('issue', $event)"
        @open="emit('open', $event)"
      />
      <p v-else-if="!section.children.length" class="ga-fahr-branch__empty">
        {{ t('grossanlass.materialUebersicht.noFahrauftragYet') }}
      </p>
      <v-expansion-panels
        v-if="section.children.length"
        :model-value="childIds"
        multiple
        class="ga-fahr-branch__nested"
      >
        <GrossanlassFahrauftragBranch
          v-for="child in section.children"
          :key="child.group.id"
          :section="child"
          :kind-label="kindLabel"
          :busy-id="busyId"
          :can-start-trip="canStartTrip"
          :read-only="readOnly"
          @toggle-packed="emit('toggle-packed', $event)"
          @release="emit('release', $event)"
          @issue="emit('issue', $event)"
          @open="emit('open', $event)"
        />
      </v-expansion-panels>
    </v-expansion-panel-text>
  </v-expansion-panel>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import GrossanlassGroupNodeIcon from '@/components/grossanlass/GrossanlassGroupNodeIcon.vue'
import type { GrossanlassGroup } from '@/api/grossanlassGroups'
import type { GaPreviewEinsatz } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import GrossanlassFahrauftragList from '@/views/grossanlass/GrossanlassFahrauftragList.vue'

export type GaFahrauftragSection = {
  group: GrossanlassGroup
  trips: GaPreviewEinsatz[]
  children: GaFahrauftragSection[]
}

defineOptions({ name: 'GrossanlassFahrauftragBranch' })

const props = defineProps<{
  section: GaFahrauftragSection
  kindLabel: (group: GrossanlassGroup) => string
  busyId?: string | null
  canStartTrip?: (row: GaPreviewEinsatz) => boolean
  readOnly?: boolean
}>()

const emit = defineEmits<{
  'toggle-packed': [row: GaPreviewEinsatz]
  release: [row: GaPreviewEinsatz]
  issue: [row: GaPreviewEinsatz]
  open: [row: GaPreviewEinsatz]
}>()

const { t } = useI18n()
const childIds = computed(() => props.section.children.map((child) => child.group.id))

const tripCount = computed(() => {
  const walk = (section: GaFahrauftragSection): number =>
    section.trips.length + section.children.reduce((sum, child) => sum + walk(child), 0)
  return walk(props.section)
})
</script>

<style scoped>
.ga-fahr-branch__title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.ga-fahr-branch__kind,
.ga-fahr-branch__empty {
  font-size: 0.78rem;
  color: #6b7280;
}
.ga-fahr-branch__count {
  margin-left: auto;
  font-size: 0.78rem;
  color: #64748b;
}
.ga-fahr-branch__list {
  margin-bottom: 12px;
}
.ga-fahr-branch__nested {
  margin-top: 4px;
}
</style>
