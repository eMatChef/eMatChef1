<template>
  <div class="ga-vehicle-cat">
    <p class="ga-vehicle-cat__label">{{ t('grossanlass.planung.ressorts.vehicleCategory') }}</p>
    <div v-if="names.length" class="ga-vehicle-cat__chips">
      <button
        v-for="name in names"
        :key="name"
        type="button"
        class="ga-vehicle-cat__chip"
        :class="{ 'is-on': model === name }"
        :disabled="disabled"
        @click="toggle(name)"
      >
        {{ name }}
      </button>
    </div>
    <p v-else class="ga-vehicle-cat__empty">{{ t('grossanlass.planung.ressorts.vehicleCategoryEmpty') }}</p>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { listGrossanlassProcurementCategories } from '@/api/grossanlassProcurement'

const model = defineModel<string>({ default: '' })
const props = defineProps<{
  departmentId: string
  disabled?: boolean
}>()

const { t } = useI18n()
const names = ref<string[]>([])

const fallback = [
  'Anhänger',
  'Flurförderzeuge & Hebezeuge',
  'Gelände- und Utility-Fahrzeuge',
  'Logistik- und Transportfahrzeuge (Strasse)',
]

function toggle(name: string) {
  model.value = model.value === name ? '' : name
}

onMounted(() => {
  const dept = props.departmentId
  if (!dept) {
    names.value = fallback
    return
  }
  void listGrossanlassProcurementCategories(dept)
    .then((rows) => {
      const root = rows.find((row) => row.name === 'Fahrzeuge' && !row.parent_id)
      const children = root ? rows.filter((row) => row.parent_id === root.id) : []
      const childIds = new Set(children.map((row) => row.id))
      const leaves = rows.filter((row) => row.parent_id && childIds.has(row.parent_id))
      const picked = (leaves.length ? leaves : children).map((row) => row.name)
      names.value = picked.length ? picked : fallback
    })
    .catch(() => { names.value = fallback })
})
</script>

<style scoped>
.ga-vehicle-cat__label,
.ga-vehicle-cat__empty {
  margin: 0 0 6px;
  font-size: 0.78rem;
  color: #6b7280;
}
.ga-vehicle-cat__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.ga-vehicle-cat__chip {
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 0.8rem;
  cursor: pointer;
}
.ga-vehicle-cat__chip.is-on {
  background: #0f766e;
  border-color: #0f766e;
  color: #fff;
}
.ga-vehicle-cat__chip:disabled {
  cursor: default;
  opacity: 0.7;
}
</style>
