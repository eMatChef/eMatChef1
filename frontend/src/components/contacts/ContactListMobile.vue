<template>
  <v-list v-if="items.length > 0" class="contact-list-mobile" lines="three">
    <v-list-item
      v-for="item in items"
      :key="item.id"
      class="contact-list-mobile__item"
      :class="{ 'contact-list-mobile__item--deleted': item.is_deleted }"
      @click="emit('open', item)"
    >
      <template #prepend>
        <div class="contact-avatar" :class="item.type">
          {{ getInitials(item) }}
        </div>
      </template>

      <v-list-item-title class="contact-list-mobile__title">
        {{ item.name || item.company || t('contacts.unnamed') }}
        <span v-if="item.is_primary" class="primary-badge">{{ t('contacts.primaryBadge') }}</span>
        <span v-if="item.is_deleted" class="deleted-badge">{{ t('contacts.deletedBadge') }}</span>
      </v-list-item-title>

      <v-list-item-subtitle v-if="item.company && item.name" class="contact-list-mobile__meta">
        {{ item.company }}
      </v-list-item-subtitle>

      <v-list-item-subtitle v-if="item.email || item.phone" class="contact-list-mobile__meta">
        {{ item.email || item.phone }}
      </v-list-item-subtitle>

      <v-list-item-subtitle v-if="item.city_line" class="contact-list-mobile__address">
        {{ item.city_line }}
      </v-list-item-subtitle>

      <template #append>
        <span class="address-type-badge address-type-badge--compact" :class="item.type">{{ typeLabel(item.type) }}</span>
      </template>
    </v-list-item>
  </v-list>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Address } from '@/api/addresses'
import '@/styles/contacts-view.css'
import '@/styles/components/contact-list-mobile.css'

defineOptions({ name: 'ContactListMobile' })

defineProps<{
  items: Address[]
  typeLabel: (type: string) => string
}>()

const emit = defineEmits<{
  open: [item: Address]
}>()

const { t } = useI18n()

function getInitials(contact: Address): string {
  if (contact.name) return contact.name.substring(0, 2)
  if (contact.company) return contact.company.substring(0, 2)
  return '??'
}
</script>
