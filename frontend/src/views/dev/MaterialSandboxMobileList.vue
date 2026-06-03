<template>
  <v-list v-if="items.length > 0" class="sandbox-material-mobile-list" lines="three">
    <template v-for="item in items" :key="item.id">
      <v-list-item
        class="sandbox-material-mobile-list__item"
        :class="{ 'sandbox-material-mobile-list__item--combo': item.components?.length }"
        @click="emit('select', item)"
      >
        <template #prepend>
          <div class="material-icon sandbox-material-mobile-list__icon" :class="iconClass(item)">
            <v-icon :icon="iconName(item)" size="20" />
          </div>
        </template>

        <v-list-item-title class="sandbox-material-mobile-list__title">
          {{ item.name }}
          <span v-if="item.is_js_material" class="source-badge">J&amp;S</span>
        </v-list-item-title>

        <v-list-item-subtitle class="sandbox-material-mobile-list__meta">
          {{ categoryLabel(item) }}
        </v-list-item-subtitle>

        <v-list-item-subtitle class="sandbox-material-mobile-list__stock">
          {{ t('materialsView.colTotal') }} {{ item.total_stock }}
          · {{ t('materialsView.colAvailable') }}
          <span
            class="stock-badge available sandbox-material-mobile-list__stock-badge"
            :class="{
              low: item.available < 3 && item.total_stock > 0,
              empty: item.available <= 0 && item.total_stock > 0,
            }"
          >
            {{ item.available }}
          </span>
        </v-list-item-subtitle>

        <template #append>
          <div class="sandbox-material-mobile-list__actions">
            <v-btn
              v-if="item.components?.length"
              icon
              variant="text"
              size="small"
              density="compact"
              :aria-expanded="isComboExpanded(item.id)"
              :aria-label="t('materialsView.expandComboTitle')"
              @click.stop="toggleCombo(item.id)"
            >
              <v-icon
                :icon="isComboExpanded(item.id) ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                size="20"
              />
            </v-btn>
            <v-btn
              icon
              variant="text"
              size="small"
              density="compact"
              :aria-label="t('materialsView.titleOpenDetails')"
              @click.stop="emit('detail', item)"
            >
              <v-icon icon="mdi-eye-outline" size="18" />
            </v-btn>
          </div>
        </template>
      </v-list-item>

      <v-expand-transition>
        <div
          v-if="item.components?.length && isComboExpanded(item.id)"
          class="sandbox-material-mobile-list__combo-panel"
        >
          <p class="sandbox-material-mobile-list__combo-heading">
            {{ t('materialsView.subColComponent') }}
          </p>
          <ul class="sandbox-material-mobile-list__combo-items">
            <li v-for="comp in item.components" :key="comp.id">
              <span class="comp-link">{{ comp.name }}</span>
              <span class="sandbox-material-mobile-list__combo-meta">
                ×{{ comp.qty }}
                <template v-if="comp.serial"> · {{ comp.serial }}</template>
              </span>
            </li>
          </ul>
        </div>
      </v-expand-transition>
    </template>
  </v-list>

  <EEmptyState
    v-else
    variant="search"
    compact
    :title="t('devSandbox.materialTableSamples.noResultsTitle')"
    :description="t('devSandbox.materialTableSamples.noResultsText')"
  />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import type { SandboxMaterialRow } from './materialSandboxTypes'

defineOptions({ name: 'MaterialSandboxMobileList' })

defineProps<{
  items: SandboxMaterialRow[]
}>()

const emit = defineEmits<{
  select: [item: SandboxMaterialRow]
  detail: [item: SandboxMaterialRow]
}>()

const { t } = useI18n()
const expandedComboIds = ref<Set<string>>(new Set())

function categoryLabel(item: SandboxMaterialRow) {
  if (item.category_parent && item.category_name) {
    return `${item.category_parent} → ${item.category_name}`
  }
  return item.category_name ?? '–'
}

function iconClass(item: SandboxMaterialRow) {
  if (item.is_container || item.is_combo) return 'container'
  return undefined
}

function iconName(item: SandboxMaterialRow) {
  if (item.is_container) return 'mdi-package-variant-closed'
  if (item.is_combo) return 'mdi-triangle-outline'
  if (item.is_food) return 'mdi-coffee'
  if (item.is_consumable) return 'mdi-minus-circle-outline'
  return 'mdi-cube-outline'
}

function isComboExpanded(id: string) {
  return expandedComboIds.value.has(id)
}

function toggleCombo(id: string) {
  const next = new Set(expandedComboIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expandedComboIds.value = next
}
</script>

<style src="@/styles/components/sandbox-material-mobile-list.css"></style>
