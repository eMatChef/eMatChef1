<script setup lang="ts">
import type { ActivityPackItem } from '@/api/activityPackItems'
import PackMaterialStorageStack from '@/components/activities/PackMaterialStorageStack.vue'
import {
  isPhysicalComboPackItem,
  isVirtualComboPackItem,
  packMaterialDisplayName,
} from '@/components/activities/packMaterialDisplay'
import { useI18n } from 'vue-i18n'

defineOptions({ name: 'PackMaterialMeta' })

withDefaults(
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
      {{ item.linkedContainerLabel }}
    </div>
    <PackMaterialStorageStack
      v-if="showStorageLocation"
      :storage="item"
      :show-rack="showRack"
    />
  </div>
</template>
