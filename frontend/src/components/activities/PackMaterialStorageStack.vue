<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

defineOptions({ name: 'PackMaterialStorageStack' })

export type PackStorageLocationFields = {
  storageRackName?: string | null
  storageAddressName?: string | null
  storageSlotName?: string | null
}

const props = withDefaults(
  defineProps<{
    storage: PackStorageLocationFields
    /** Gestell-Zeile (Regal) */
    showRack?: boolean
    /** `shell`: hervorgehobener Block in Kisten-Karten */
    variant?: 'inline' | 'shell'
  }>(),
  {
    showRack: true,
    variant: 'inline',
  },
)

const { t } = useI18n()

const rackName = computed(() => props.storage.storageRackName?.trim() || '')

const hasAny = computed(
  () =>
    Boolean(rackName.value && props.showRack !== false) ||
    Boolean(props.storage.storageAddressName?.trim()) ||
    Boolean(props.storage.storageSlotName?.trim()),
)
</script>

<template>
  <div
    v-if="hasAny"
    class="pack-card-storage-stack text-muted"
    :class="{ 'pack-shell-storage': variant === 'shell' }"
  >
    <div v-if="showRack !== false && rackName" class="pack-card-storage">
      {{ t('activities.packList.rackLabel', { name: rackName }) }}
    </div>
    <div v-if="storage.storageAddressName" class="pack-card-storage">
      {{ t('activities.packList.storageLabel', { name: storage.storageAddressName }) }}
    </div>
    <div v-if="storage.storageSlotName" class="pack-card-storage">
      {{ t('activities.packList.slotLabel', { name: storage.storageSlotName }) }}
    </div>
  </div>
</template>
