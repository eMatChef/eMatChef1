<template>
  <v-data-table
    class="contact-list-dt__table"
    :headers="headers"
    :items="items"
    :items-per-page="-1"
    item-value="id"
    hover
    hide-default-footer
    :row-props="rowProps"
    @click:row="(_e, { item }) => emit('open', item)"
  >
    <template #item.name="{ item }">
      <div class="name-cell">
        <div class="contact-avatar" :class="item.type">
          {{ getInitials(item) }}
        </div>
        <div class="name-info">
          <span class="contact-name">
            {{ item.name || item.company || t('contacts.unnamed') }}
            <span v-if="item.is_primary" class="primary-badge">{{ t('contacts.primaryBadge') }}</span>
            <span v-if="item.is_deleted" class="deleted-badge">{{ t('contacts.deletedBadge') }}</span>
          </span>
          <span v-if="item.company && item.name" class="contact-company">{{ item.company }}</span>
        </div>
      </div>
    </template>

    <template #item.contact="{ item }">
      <div v-if="item.email || item.phone || item.mobile" class="contact-info-cell">
        <div v-if="item.email" class="contact-detail">
          <v-icon icon="mdi-email-outline" size="14" class="mr-1" />
          <a :href="'mailto:' + item.email" @click.stop>{{ item.email }}</a>
        </div>
        <div v-if="item.phone" class="contact-detail">
          <v-icon icon="mdi-phone-outline" size="14" class="mr-1" />
          <a :href="'tel:' + item.phone" @click.stop>{{ item.phone }}</a>
        </div>
        <div v-if="item.mobile" class="contact-detail">
          <v-icon icon="mdi-cellphone" size="14" class="mr-1" />
          <a :href="'tel:' + item.mobile" @click.stop>{{ item.mobile }}</a>
        </div>
      </div>
      <span v-else class="no-contact">-</span>
    </template>

    <template #item.address="{ item }">
      <div class="address-cell">
        <span class="address-street">{{ item.street_line }}</span>
        <span class="address-city">{{ item.city_line }}</span>
      </div>
    </template>

    <template #item.type="{ item }">
      <span class="address-type-badge" :class="item.type">{{ typeLabel(item.type) }}</span>
    </template>

    <template #item.actions="{ item }">
      <div class="action-buttons">
        <template v-if="item.is_deleted && canManageDeletedContacts">
          <v-btn
            icon
            variant="text"
            size="small"
            density="compact"
            :title="t('contacts.restore')"
            @click.stop="emit('restore', item)"
          >
            <v-icon icon="mdi-restore" size="20" />
          </v-btn>
          <v-btn
            icon
            variant="text"
            size="small"
            density="compact"
            color="error"
            :title="t('contacts.permanentDelete')"
            @click.stop="emit('permanent-delete', item)"
          >
            <v-icon icon="mdi-delete-forever-outline" size="20" />
          </v-btn>
        </template>
        <v-btn
          v-else
          icon
          variant="text"
          size="small"
          density="compact"
          :title="t('contacts.openDetailsTitle')"
          @click.stop="emit('open', item)"
        >
          <v-icon icon="mdi-eye-outline" size="20" />
        </v-btn>
      </div>
    </template>
  </v-data-table>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Address } from '@/api/addresses'
import '@/styles/contacts-view.css'
import '@/styles/components/contact-list-data-table.css'

defineOptions({ name: 'ContactListDataTable' })

const props = defineProps<{
  items: Address[]
  canManageDeletedContacts: boolean
  typeLabel: (type: string) => string
}>()

const emit = defineEmits<{
  open: [item: Address]
  restore: [item: Address]
  'permanent-delete': [item: Address]
}>()

const { t } = useI18n()

const headers = computed(() => [
  { title: t('common.name'), key: 'name', sortable: false },
  { title: t('contacts.colContact'), key: 'contact', sortable: false },
  { title: t('contacts.colAddress'), key: 'address', sortable: false },
  { title: t('contacts.colType'), key: 'type', sortable: false, width: '140px' },
  { title: '', key: 'actions', sortable: false, width: '100px', align: 'end' as const },
])

function getInitials(contact: Address): string {
  if (contact.name) return contact.name.substring(0, 2)
  if (contact.company) return contact.company.substring(0, 2)
  return '??'
}

function rowProps({ item }: { item: Address }) {
  return {
    class: item.is_deleted ? 'contact-row--deleted' : undefined,
  }
}
</script>
