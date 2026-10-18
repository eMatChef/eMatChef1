<template>
  <v-combobox
    :model-value="modelValue"
    :items="catalog"
    :label="label"
    :multiple="multiple"
    :chips="multiple"
    :closable-chips="multiple"
    :disabled="disabled"
    :loading="loading"
    :hide-details="hideDetails"
    :no-data-text="t('grossanlass.helferpool.skills.noData')"
    :hint="hint"
    persistent-hint
    variant="outlined"
    density="comfortable"
    clearable
    data-testid="ga-skill-picker"
    @update:model-value="onUpdate"
  />
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { listGaSkills } from '@/api/grossanlassStaffing'

/**
 * Auswahl aus dem Fähigkeitenkatalog des GA-Departments (mit Suche). Neue Namen lassen sich direkt eintippen und
 * landen beim Speichern im Katalog. `multiple`: Liste von Fähigkeiten (Helferprofil), sonst ein einzelner Name (Funktionswunsch).
 */
const props = withDefaults(defineProps<{ departmentId: string; modelValue: string[] | string; multiple?: boolean; label?: string; disabled?: boolean; hideDetails?: boolean; hint?: string }>(), {
  multiple: false,
  label: undefined,
  disabled: false,
  hideDetails: false,
  hint: undefined,
})
const emit = defineEmits<{ 'update:modelValue': [value: string[] | string] }>()
const { t } = useI18n()
const catalog = ref<string[]>([])
const loading = ref(false)

onMounted(async () => {
  loading.value = true
  try {
    catalog.value = await listGaSkills(props.departmentId)
  } catch {
    catalog.value = [] // Auswahl bleibt als Freitext nutzbar
  } finally {
    loading.value = false
  }
})

function onUpdate(value: unknown) {
  if (props.multiple) {
    emit('update:modelValue', (Array.isArray(value) ? value : []).map((v) => String(v).trim()).filter(Boolean))
  } else {
    emit('update:modelValue', typeof value === 'string' ? value : '')
  }
}
</script>
