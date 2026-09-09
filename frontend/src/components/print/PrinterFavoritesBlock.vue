<template>
  <div class="printer-block">
    <div class="card-head">
      <h2>{{ t('printSettings.favoritesTitle') }}</h2>
      <EButton
        v-if="catalog?.can_manage_presets"
        variant="primary"
        size="small"
        @click="$emit('add')"
      >
        {{ t('printSettings.addFavorite') }}
      </EButton>
    </div>

    <EEmptyState
      v-if="presets.length === 0"
      variant="generic"
      compact
      :title="t('printSettings.favoritesEmptyTitle')"
      :description="t('printSettings.favoritesEmpty')"
      icon="mdi-printer-outline"
    />

    <ul v-else class="preset-list">
      <li v-for="printer in printerRows" :key="printer.device_model_id" class="preset-row">
        <span class="printer-icon" aria-hidden="true">
          <v-icon icon="mdi-printer-outline" size="28" />
        </span>
        <div class="preset-copy">
          <strong>{{ printer.name }}</strong>
          <span v-if="printer.is_default" class="chip">{{ t('printSettings.defaultChip') }}</span>
          <p class="meta">{{ printer.device_model.brand }} {{ printer.device_model.name }}</p>
        </div>
        <div v-if="catalog?.can_manage_presets" class="row-actions">
          <EButton
            v-if="!printer.is_default"
            variant="text"
            size="small"
            @click="$emit('default', printer.preset)"
          >
            {{ t('printSettings.setDefault') }}
          </EButton>
          <EButton variant="text" size="small" @click="$emit('edit', printer.preset)">
            {{ t('common.edit') }}
          </EButton>
          <EButton variant="danger" size="small" @click="$emit('remove', printer.preset)">
            {{ t('common.remove') }}
          </EButton>
        </div>
      </li>
    </ul>

    <template v-if="catalog?.can_propose">
      <h2 class="subhead">{{ t('printSettings.proposeTitle') }}</h2>
      <p class="muted">{{ t('printSettings.proposeHint') }}</p>
      <div class="row-actions">
        <EButton variant="secondary" size="small" @click="$emit('propose-model')">
          {{ t('printSettings.proposeModel') }}
        </EButton>
        <EButton variant="secondary" size="small" @click="$emit('propose-media')">
          {{ t('printSettings.proposeMedia') }}
        </EButton>
      </div>
      <ul v-if="ownOrgItems.length" class="pending-list">
        <li v-for="item in ownOrgItems" :key="item.id">
          <span class="chip" :class="item.global_requested ? 'chip--pending' : 'chip--org'">
            {{ item.global_requested ? t('printSettings.status.pendingGlobal') : t('printSettings.scope.organisation') }}
          </span>
          {{ item.label }}
          <EButton
            v-if="!item.global_requested"
            variant="text"
            size="small"
            @click="$emit('request-global', item.kind, item.rawId)"
          >
            {{ t('printSettings.requestGlobal') }}
          </EButton>
        </li>
      </ul>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import EButton from '@/components/form/base/EButton.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import type { DepartmentPrintCatalog, DepartmentPrintPreset } from '@/api/printCatalog'

const props = defineProps<{
  catalog: DepartmentPrintCatalog | null
  presets: DepartmentPrintPreset[]
}>()

defineEmits<{
  add: []
  edit: [preset: DepartmentPrintPreset]
  remove: [preset: DepartmentPrintPreset]
  default: [preset: DepartmentPrintPreset]
  'propose-model': []
  'propose-media': []
  'request-global': [kind: 'model' | 'media', id: string]
}>()

const { t } = useI18n()

const printerRows = computed(() => {
  const groups = new Map<string, DepartmentPrintPreset[]>()
  for (const preset of props.presets) {
    const list = groups.get(preset.device_model_id) || []
    list.push(preset)
    groups.set(preset.device_model_id, list)
  }
  return [...groups.values()].map((group) => {
    const preset = group.find((item) => item.is_default) || group[0]
    return {
      device_model_id: preset.device_model_id,
      name: preset.name,
      is_default: group.some((item) => item.is_default),
      device_model: preset.device_model,
      preset,
    }
  })
})

const ownOrgItems = computed(() => {
  const models = (props.catalog?.models || [])
    .filter((item) => item.scope === 'organisation')
    .map((item) => ({
      id: `m-${item.id}`,
      rawId: item.id,
      kind: 'model' as const,
      label: `${item.brand} ${item.name}`,
      global_requested: item.global_requested,
    }))
  const media = (props.catalog?.media || [])
    .filter((item) => item.scope === 'organisation')
    .map((item) => ({
      id: `e-${item.id}`,
      rawId: item.id,
      kind: 'media' as const,
      label: item.name,
      global_requested: item.global_requested,
    }))
  return [...models, ...media]
})
</script>

<style scoped>
.card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; }
h2 { margin: 0 0 8px; font-size: 16px; }
.subhead { margin: 18px 0 8px; }
.muted { color: #6b7280; font-size: 14px; margin: 0 0 12px; }
.preset-list, .pending-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.pending-list li { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; }
.preset-row {
  display: grid;
  grid-template-columns: 40px 1fr auto;
  gap: 12px;
  align-items: center;
  border-bottom: 1px solid #f1f5f9;
  padding-bottom: 10px;
}
.preset-row:last-child { border-bottom: 0; padding-bottom: 0; }
.printer-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: #eff6ff;
  color: #1d4ed8;
}
.preset-copy { min-width: 0; }
.meta { margin: 4px 0 0; color: #64748b; font-size: 13px; }
.chip {
  display: inline-block;
  margin-left: 8px;
  font-size: 11px;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 999px;
  background: #dcfce7;
  color: #166534;
}
.chip--pending { background: #ffedd5; color: #c2410c; margin-right: 8px; margin-left: 0; }
.chip--org { background: #e0e7ff; color: #3730a3; margin-right: 8px; margin-left: 0; }
.row-actions { display: flex; flex-wrap: wrap; gap: 4px; justify-content: flex-end; }
@media (max-width: 640px) {
  .preset-row { grid-template-columns: 48px 1fr; }
  .row-actions { grid-column: 1 / -1; justify-content: flex-start; }
}
</style>
