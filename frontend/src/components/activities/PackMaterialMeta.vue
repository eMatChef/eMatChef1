<script setup lang="ts">
import type { ActivityPackItem } from '@/api/activityPackItems'
import { packRackLabel } from '@/components/activities/packMaterialDisplay'
import { useI18n } from 'vue-i18n'

defineOptions({ name: 'PackMaterialMeta' })

const props = withDefaults(
  defineProps<{
    item: ActivityPackItem
    showRack?: boolean
    showLinkedKiste?: boolean
  }>(),
  {
    showRack: false,
    showLinkedKiste: true,
  },
)

const { t } = useI18n()
</script>

<template>
  <div class="pack-card-name-block">
    <span class="pack-card-name">
      {{ item.materialName }}
      <span
        v-if="item.materialType === 'physical_combo'"
        class="pack-combo-badge"
        :title="t('activities.detail.comboPhysicalTitle')"
        >{{ t('activities.detail.comboPhysicalShort') }}</span
      >
      <span
        v-else-if="item.materialType === 'virtual_combo'"
        class="pack-combo-badge pack-combo-badge--virtual"
        :title="t('activities.detail.comboVirtualTitle')"
        >{{ t('activities.detail.comboVirtualShort') }}</span
      >
      <span v-if="item.isJsMaterial" class="mat-source-badge">{{ t('activities.common.jsBadge') }}</span>
    </span>
    <div v-if="showLinkedKiste && item.linkedContainerLabel" class="pack-card-kiste text-muted">
      {{ t('activities.packList.kisteLabel', { label: item.linkedContainerLabel }) }}
    </div>
    <div v-if="item.storageAddressName" class="pack-card-storage text-muted">
      {{ t('activities.packList.storageLabel', { name: item.storageAddressName }) }}
    </div>
    <div v-if="showRack && packRackLabel(item)" class="pack-card-storage text-muted">
      {{ t('activities.packList.rackLabel', { name: packRackLabel(item) }) }}
    </div>
    <div v-if="item.storageSlotName" class="pack-card-storage text-muted">
      {{ t('activities.packList.slotLabel', { name: item.storageSlotName }) }}
    </div>
  </div>
</template>
