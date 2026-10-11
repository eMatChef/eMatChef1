<template>
  <section class="helper-need" :aria-label="t('grossanlass.helperNeed.title')">
    <h4>{{ t('grossanlass.helperNeed.title') }}</h4>
    <p class="helper-need__hint">{{ t('grossanlass.helperNeed.hint') }}</p>
    <ETextField
      :model-value="String(model.total)"
      type="number"
      min="0"
      :label="t('grossanlass.helperNeed.total')"
      hide-details="auto"
      data-testid="helper-need-total"
      @update:model-value="onTotal"
    />
    <div v-for="(fn, index) in model.functions" :key="index" class="helper-need__fn">
      <ETextField v-model="fn.label" :label="t('grossanlass.helperNeed.function')" hide-details="auto" />
      <ETextField
        :model-value="String(fn.count)"
        type="number"
        min="1"
        :label="t('grossanlass.helperNeed.count')"
        hide-details="auto"
        @update:model-value="(v) => onCount(fn, v)"
      />
      <EButton variant="secondary" size="small" type="button" @click="model.functions.splice(index, 1)">
        {{ t('grossanlass.helperNeed.remove') }}
      </EButton>
    </div>
    <p v-if="model.functions.length > 0" class="helper-need__hint">
      {{ t('grossanlass.helperNeed.split', { sum: functionSum, total: effectiveTotal }) }}
    </p>
    <div class="helper-need__actions">
      <EButton variant="secondary" size="small" type="button" @click="model.functions.push({ label: '', count: 1 })">
        {{ t('grossanlass.helperNeed.addFunction') }}
      </EButton>
      <EButton v-if="groupId" size="small" type="button" :loading="saving" @click="save">
        {{ t('grossanlass.helperNeed.save') }}
      </EButton>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, ETextField } from '@/components/form/base'
import {
  getGrossanlassHelperNeed,
  saveGrossanlassHelperNeed,
  type GrossanlassHelperNeed,
} from '@/api/grossanlassGroups'
import { useToast } from '@/composables/useToast'
import { apiErrorMessage } from '@/utils/apiErrorMessage'

/**
 * Helferbedarf eines Auftrags oder Bauauftrags: nur Anzahl, optional aufgeteilt in Funktionen.
 * Ohne groupId (Anlegen) steuert der Aufrufer das Speichern; mit groupId lädt und speichert die Komponente selbst.
 * Wer eingeteilt ist, steht nicht hier, sondern in Helferpool und Aufgabenzuweisung.
 */
export type HelperNeedModel = { total: number; functions: { label: string; count: number }[] }

const props = defineProps<{ departmentId?: string; groupId?: string | null }>()
const model = defineModel<HelperNeedModel>({ default: () => ({ total: 0, functions: [] }) })
const { t } = useI18n()
const toast = useToast()
const saving = ref(false)

const functionSum = computed(() => model.value.functions.reduce((sum, f) => sum + (f.label.trim() ? f.count : 0), 0))
/** Funktionen teilen den Gesamtbedarf auf: der Gesamtbedarf ist mindestens ihre Summe. */
const effectiveTotal = computed(() => Math.max(model.value.total, functionSum.value))

function onTotal(value: string | number | null) {
  model.value.total = Math.max(0, Math.floor(Number(value) || 0))
}
function onCount(fn: { count: number }, value: string | number | null) {
  fn.count = Math.max(1, Math.floor(Number(value) || 1))
}

function apply(need: GrossanlassHelperNeed) {
  model.value = { total: need.total, functions: need.functions.map((f) => ({ ...f })) }
}

async function load() {
  if (!props.groupId || !props.departmentId) return
  try {
    apply(await getGrossanlassHelperNeed(props.departmentId, props.groupId))
  } catch {
    model.value = { total: 0, functions: [] }
  }
}

async function save() {
  if (!props.groupId || !props.departmentId) return
  saving.value = true
  try {
    apply(await saveGrossanlassHelperNeed(props.departmentId, props.groupId, toPayload(model.value)))
    toast.success(t('grossanlass.helperNeed.saved'))
  } catch (e) {
    toast.error(apiErrorMessage(e, t('grossanlass.helperNeed.failed')))
  } finally {
    saving.value = false
  }
}

onMounted(load)
watch(() => props.groupId, load)
</script>

<script lang="ts">
/** Nutzlast fürs Backend: Funktionen ohne Namen entfallen, der Gesamtbedarf umfasst ihre Summe. */
export function toPayload(need: HelperNeedModel): HelperNeedModel {
  const functions = need.functions
    .map((f) => ({ label: f.label.trim(), count: f.count }))
    .filter((f) => f.label && f.count > 0)
  return { total: Math.max(need.total, functions.reduce((sum, f) => sum + f.count, 0)), functions }
}
</script>

<style scoped>
.helper-need {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.helper-need h4 {
  margin: 0;
  font-size: 0.95rem;
}
.helper-need__hint {
  margin: 0;
  color: #64748b;
  font-size: 0.85rem;
}
.helper-need__fn {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto;
  gap: 8px;
  align-items: start;
}
.helper-need__actions {
  display: flex;
  gap: 8px;
}
</style>
