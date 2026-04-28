<template>
  <div class="storage-crud-actions">
    <StorageActionButton
      v-if="showEdit"
      :size="size"
      :title="t('common.edit')"
      icon="edit"
      @click="$emit('edit')"
    />
    <StorageActionButton
      v-if="showAdd"
      :size="size"
      :title="t('common.add')"
      icon="add"
      variant="add"
      @click="$emit('add')"
    />
    <StorageActionButton
      v-if="showDelete"
      :size="size"
      :title="t('common.delete')"
      icon="delete"
      variant="delete"
      @click="$emit('delete')"
    />
  </div>
</template>

<script setup lang="ts">
import StorageActionButton from '@/components/storage/StorageActionButton.vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

withDefaults(defineProps<{
  showEdit?: boolean
  showAdd?: boolean
  showDelete?: boolean
  size?: 'sm' | 'md'
}>(), {
  showEdit: false,
  showAdd: true,
  showDelete: true,
  size: 'md',
})

defineEmits<{
  edit: []
  add: []
  delete: []
}>()
</script>

<style scoped>
.storage-crud-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
</style>
