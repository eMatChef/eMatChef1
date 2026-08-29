<template>
  <v-expansion-panels
    :model-value="panelOpen"
    multiple
    class="ga-unterlager-tree"
    :class="{ 'is-nested': nested }"
    @update:model-value="onOpen"
  >
    <v-expansion-panel v-for="site in nodes" :key="site.id" :value="site.id">
      <v-expansion-panel-title>
        <span class="group-title">
          <span class="kind-badge">{{ nested ? '↳' : t('grossanlass.planung.struktur.unterlagerBadge') }}</span>
          <strong>{{ site.name }}</strong>
          <span class="group-count">{{ t('grossanlass.planung.struktur.memberCount', { count: departments(site.id).length }) }}</span>
          <span v-if="site.children.length" class="group-count">
            {{ t('grossanlass.planung.struktur.unterlagerChildCount', { count: site.children.length }) }}
          </span>
        </span>
      </v-expansion-panel-title>
      <v-expansion-panel-text>
        <div v-if="canManage" class="guest-actions">
          <EButton variant="secondary" size="small" :disabled="saving" @click="$emit('add-department', site.id)">
            {{ t('grossanlass.planung.struktur.addDepartment') }}
          </EButton>
          <EButton variant="secondary" size="small" :disabled="saving" @click="$emit('add-child', site.id)">
            {{ t('grossanlass.planung.struktur.unterlagerChild') }}
          </EButton>
          <button type="button" class="remove" :disabled="saving" @click="$emit('remove-site', site.id)">
            {{ t('grossanlass.planung.struktur.unterlagerDelete') }}
          </button>
        </div>
        <ul v-if="departments(site.id).length" class="child-list">
          <li v-for="row in departments(site.id)" :key="row.id" class="child-row">
            <div class="child-head">
              <strong>{{ row.name }}</strong>
              <span class="meta">{{ row.organisation_name }}</span>
              <span class="tag">{{ t(`grossanlass.planung.struktur.status.${row.status}`) }}</span>
              <ESelect
                v-if="canManage"
                class="move-select"
                :model-value="row.unterlager_id || ''"
                :items="selectItems"
                hide-details
                :disabled="saving"
                @update:model-value="(v) => $emit('move-guest', row.id, String(v ?? ''))"
              />
              <button
                v-if="canManage && row.status !== 'accepted'"
                type="button"
                class="remove"
                :disabled="saving"
                @click="$emit('remove-guest', row.id)"
              >
                {{ t('common.remove') }}
              </button>
            </div>
          </li>
        </ul>
        <p v-else-if="!site.children.length" class="hint">
          {{ t('grossanlass.planung.struktur.unterlagerNoDepts') }}
        </p>
        <GrossanlassUnterlagerTree
          v-if="site.children.length"
          nested
          :nodes="site.children"
          :open-ids="openIds"
          :can-manage="canManage"
          :saving="saving"
          :select-items="selectItems"
          :departments="departments"
          @update:open-ids="$emit('update:openIds', $event)"
          @add-department="$emit('add-department', $event)"
          @add-child="$emit('add-child', $event)"
          @remove-site="$emit('remove-site', $event)"
          @move-guest="(id, siteId) => $emit('move-guest', id, siteId)"
          @remove-guest="$emit('remove-guest', $event)"
        />
      </v-expansion-panel-text>
    </v-expansion-panel>
  </v-expansion-panels>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ESelect } from '@/components/form/base'
import type { GrossanlassParticipant, GrossanlassUnterlager } from '@/api/grossanlassPlanung'
import type { NestedTreeNode } from '@/utils/grossanlassGroupHierarchy'

defineOptions({ name: 'GrossanlassUnterlagerTree' })

const props = defineProps<{
  nodes: NestedTreeNode<GrossanlassUnterlager>[]
  nested?: boolean
  openIds: string[]
  canManage: boolean
  saving: boolean
  selectItems: { title: string; value: string }[]
  departments: (unterlagerId: string) => GrossanlassParticipant[]
}>()

const emit = defineEmits<{
  'update:openIds': [string[]]
  'add-department': [string]
  'add-child': [string]
  'remove-site': [string]
  'move-guest': [guestId: string, unterlagerId: string]
  'remove-guest': [string]
}>()

const { t } = useI18n()

const panelOpen = computed(() => {
  const ids = new Set(props.nodes.map((node) => node.id))
  return props.openIds.filter((id) => ids.has(id))
})

function onOpen(value: unknown) {
  const ids = new Set(props.nodes.map((node) => node.id))
  const next = Array.isArray(value) ? value.map(String).filter((id) => ids.has(id)) : []
  const kept = props.openIds.filter((id) => !ids.has(id))
  emit('update:openIds', [...kept, ...next])
}
</script>

<style scoped>
.ga-unterlager-tree :deep(.v-expansion-panel) {
  border: 1px solid #e5e7eb;
  border-radius: 10px !important;
  margin-bottom: 8px;
}
.ga-unterlager-tree :deep(.v-expansion-panel-title) {
  min-height: 48px;
  font-size: 0.9rem;
}
.ga-unterlager-tree.is-nested {
  margin: 10px 0 0;
  padding-left: 8px;
  border-left: 3px solid #d1fae5;
}
.ga-unterlager-tree.is-nested :deep(.v-expansion-panel) {
  background: #f8fafc;
}
.group-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.group-count { color: #64748b; font-size: 0.8rem; }
.kind-badge {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #475569;
}
.guest-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 8px;
}
.child-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 6px;
}
.child-row { padding: 8px 0; border-top: 1px solid #f1f5f9; }
.child-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.meta { color: #64748b; font-size: 0.8rem; }
.tag {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  background: #ecfdf5;
  color: #166534;
}
.hint { margin: 8px 0 0; color: #64748b; font-size: 0.85rem; }
.remove {
  margin-left: auto;
  border: 0;
  background: transparent;
  color: #9a3412;
  cursor: pointer;
  font-size: 0.8rem;
}
.move-select { min-width: 200px; max-width: 280px; }
</style>
