<script setup lang="ts">
import { computed } from 'vue'
import type { ActivityPackItem } from '@/api/activityPackItems'
import {
  isPhysicalComboPackItem,
  isVirtualComboPackItem,
  packMaterialDisplayName,
  packRackLabel,
} from '@/components/activities/packMaterialDisplay'
import { useI18n } from 'vue-i18n'

defineOptions({ name: 'PackMaterialMeta' })

const props = withDefaults(
  defineProps<{
    item: ActivityPackItem
    /** Lagerort, Regal, Fach — nur solange noch im Lager */
    showStorageLocation?: boolean
    /** Referenz-Kiste am Material (Phys.-Kombi) */
    showLinkedKiste?: boolean
    showRack?: boolean
  }>(),
  {
    showStorageLocation: false,
    showLinkedKiste: false,
    showRack: true,
  },
)

const showRackLine = computed(
  () => props.showStorageLocation && props.showRack !== false && Boolean(packRackLabel(props.item)),
)

const { t } = useI18n()
</script>

<template>
  <div class="pack-card-name-block">
    <span class="pack-card-name">
      {{ packMaterialDisplayName(item) }}
      <span
        v-if="isPhysicalComboPackItem(item)"
        class="pack-combo-badge"
        :title="t('activities.detail.comboPhysicalTitle')"
        >{{ t('activities.detail.comboPhysicalShort') }}</span
      >
      <span
        v-else-if="isVirtualComboPackItem(item)"
        class="pack-combo-badge pack-combo-badge--virtual"
        :title="t('activities.detail.comboVirtualTitle')"
        >{{ t('activities.detail.comboVirtualShort') }}</span
      >
      <span v-if="item.isJsMaterial" class="mat-source-badge">{{ t('activities.common.jsBadge') }}</span>
    </span>
    <div v-if="showLinkedKiste && item.linkedContainerLabel" class="pack-card-kiste text-muted">
      {{ t('activities.packList.kisteLabel', { label: item.linkedContainerLabel }) }}
    </div>
    <template v-if="showStorageLocation">
      <div v-if="item.storageAddressName" class="pack-card-storage text-muted">
        {{ t('activities.packList.storageLabel', { name: item.storageAddressName }) }}
      </div>
      <div v-if="showRackLine" class="pack-card-storage text-muted">
        {{ t('activities.packList.rackLabel', { name: packRackLabel(item) }) }}
      </div>
      <div v-if="item.storageSlotName" class="pack-card-storage text-muted">
        {{ t('activities.packList.slotLabel', { name: item.storageSlotName }) }}
      </div>
    </template>
  </div>
</template>
