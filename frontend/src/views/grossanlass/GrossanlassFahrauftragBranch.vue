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
      <ul v-if="section.vehicles.length" class="ga-fahr-branch__vehicles">
        <li v-for="need in section.vehicles" :key="need.id">
          <button type="button" class="ga-fahr-branch__vehicle" @click="emit('open-vehicle', need.id)">
            <span class="ga-fahr-branch__vehicle-title">
              {{ need.vehicle_label || t('grossanlass.fahrzeuge.openVehicle') }}
              <span :class="need.committed ? 'ga-fahr-branch__chip ga-fahr-branch__chip--on' : 'ga-fahr-branch__chip'">
                {{ need.committed
                  ? t('grossanlass.fahrzeuge.covered')
                  : t('grossanlass.fahrzeuge.openVehicle') }}
              </span>
            </span>
            <span v-if="need.category" class="ga-fahr-branch__vehicle-meta">{{ need.category }}</span>
            <span class="ga-fahr-branch__vehicle-meta">
              <span v-if="need.task_label">{{ need.task_label }}</span>
              <span v-if="need.when">{{ need.when }}</span>
            </span>
          </button>
        </li>
      </ul>
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
      <p v-else-if="!section.children.length && !section.vehicles.length" class="ga-fahr-branch__empty">
        {{ t('grossanlass.material.noFahrauftragYet') }}
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
          @open-vehicle="emit('open-vehicle', $event)"
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

export type GaFahrauftragVehicleRow = {
  id: string
  vehicle_label: string
  task_label: string
  category: string
  when: string
  committed: boolean
}

export type GaFahrauftragSection = {
  group: GrossanlassGroup
  trips: GaPreviewEinsatz[]
  vehicles: GaFahrauftragVehicleRow[]
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
  'open-vehicle': [id: string]
}>()

const { t } = useI18n()
const childIds = computed(() => props.section.children.map((child) => child.group.id))

const tripCount = computed(() => {
  const walk = (section: GaFahrauftragSection): number =>
    section.trips.length
    + section.vehicles.length
    + section.children.reduce((sum, child) => sum + walk(child), 0)
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
.ga-fahr-branch__vehicles {
  list-style: none;
  margin: 0 0 12px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ga-fahr-branch__vehicles li {
  padding: 0;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
}
.ga-fahr-branch__vehicle {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  width: 100%;
  padding: 10px 12px;
  border: 0;
  background: transparent;
  text-align: left;
  cursor: pointer;
}
.ga-fahr-branch__vehicle-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  font-weight: 600;
}
.ga-fahr-branch__vehicle-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  margin: 0;
  color: #64748b;
  font-size: 0.85rem;
}
.ga-fahr-branch__chip {
  display: inline-flex;
  align-items: center;
  padding: 0 8px;
  border-radius: 999px;
  background: #ffedd5;
  color: #9a3412;
  font-size: 0.75rem;
  font-weight: 500;
}
.ga-fahr-branch__chip--on {
  background: #dcfce7;
  color: #166534;
}
.ga-fahr-branch__list {
  margin-bottom: 12px;
}
.ga-fahr-branch__nested {
  margin-top: 4px;
}
</style>
