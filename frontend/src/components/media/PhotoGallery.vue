<template>
  <div v-if="photos.length" class="photo-gallery" :class="layoutClass">
    <figure
      v-for="(photo, index) in photos"
      :key="mediaPhotoKey(photo, index)"
      class="photo-gallery-item"
    >
      <img
        :src="photo.url"
        :alt="photo.original_filename || ''"
        class="photo-gallery-thumb"
      />
      <figcaption
        v-if="showMeta && (photo.uploaded_by_name || photo.uploaded_at)"
        class="photo-gallery-meta"
      >
        <span v-if="photo.uploaded_by_name">{{ photo.uploaded_by_name }}</span>
        <span v-if="photo.uploaded_at">
          <template v-if="photo.uploaded_by_name"> · </template>{{ formatUploadedAt(photo.uploaded_at) }}
        </span>
      </figcaption>
    </figure>
  </div>
  <p v-else-if="showEmpty" class="photo-gallery-empty">{{ emptyText ?? t('media.galleryEmpty') }}</p>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { MediaPhoto } from '@/api/media'
import { mediaPhotoKey } from '@/api/media'
import '@/styles/components/photo-gallery.css'

const props = withDefaults(
  defineProps<{
    photos: MediaPhoto[]
    readonly?: boolean
    showMeta?: boolean
    showEmpty?: boolean
    emptyText?: string
    layout?: 'grid' | 'stacked'
    formatDate?: (value: string) => string
  }>(),
  {
    readonly: true,
    showMeta: true,
    showEmpty: false,
    layout: 'grid',
  },
)

const { t } = useI18n()

const layoutClass = computed(() =>
  props.layout === 'stacked' ? 'photo-gallery--stacked' : 'photo-gallery--grid',
)

function formatUploadedAt(value: string): string {
  if (props.formatDate) {
    return props.formatDate(value)
  }
  try {
    return new Date(value).toLocaleString()
  } catch {
    return value
  }
}
</script>
