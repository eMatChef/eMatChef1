<template>
  <div class="storage-crud-actions">
    <StorageActionButton
      v-if="showEdit"
      :size="size"
      title="Bearbeiten"
      icon="edit"
      @click="$emit('edit')"
    />
    <StorageActionButton
      v-if="showAdd"
      :size="size"
      title="Hinzufügen"
      icon="add"
      variant="add"
      @click="$emit('add')"
    />
    <StorageActionButton
      v-if="showDelete"
      :size="size"
      title="Löschen"
      icon="delete"
      variant="delete"
      @click="$emit('delete')"
    />
  </div>
</template>

<script setup lang="ts">
import StorageActionButton from '@/components/storage/StorageActionButton.vue'

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
