<template>
  <EDialog v-model="open" :max-width="760" :title="t('display.create.title')" :retain-focus="false">
    <p class="muted">{{ t('display.create.hint') }}</p>
    <ETextField
      v-model="name"
      :label="t('settings.displayScreens.nameLabel')"
      :placeholder="t('settings.displayScreens.namePlaceholder')"
      maxlength="120"
      :disabled="creating"
      hide-details
    />

    <template v-if="catalog?.enabled">
      <p class="label mt-4">{{ t('display.create.startWith') }}</p>
      <div class="create-options" role="radiogroup" :aria-label="t('display.create.startWith')">
        <button
          v-for="template in catalog.templates"
          :key="template.id"
          type="button"
          class="create-option"
          :class="{ 'create-option--on': choice === template.id }"
          role="radio"
          :aria-checked="choice === template.id"
          @click="choice = template.id"
        >
          <LayoutThumbnail :layout="template.layout" />
          <strong>{{ t(`display.layout.templates.${template.id}.name`) }}</strong>
          <small>{{ t(`display.layout.templates.${template.id}.description`) }}</small>
        </button>
      </div>
      <ESelect
        v-if="copySources.length"
        :model-value="choice.startsWith('copy:') ? choice.slice(5) : null"
        :items="copySources.map((s) => ({ value: s.id, title: s.name }))"
        :label="t('display.create.copyFrom')"
        clearable
        hide-details
        class="mt-3"
        @update:model-value="choice = $event ? `copy:${$event}` : 'blank'"
      />
      <p class="muted mt-2">{{ t('display.create.independentCopy') }}</p>
    </template>
    <p v-else class="muted mt-3">{{ t('display.create.noDeviceNeeded') }}</p>

    <template #actions>
      <EButton variant="secondary" size="small" :disabled="creating" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton variant="primary" size="small" :disabled="!name.trim() || creating" :loading="creating" @click="create">
        {{ t('display.create.submit') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { EButton, EDialog, ESelect, ETextField } from '@/components/form/base'
import { createDisplayScreen, type DisplayScreenSettings } from '@/api/displayScreens'
import type { LayoutCatalog } from '@/api/displayLayout'
import LayoutThumbnail from './layout/LayoutThumbnail.vue'

/**
 * „Infoscreen erstellen“: Name und Startpunkt (leer, Vorlage oder Kopie eines vorhandenen Infoscreens). Es wird kein
 * Gerät benötigt; ein Fernseher wird später über „Bildschirm hinzufügen“ verbunden.
 */
const props = defineProps<{
  modelValue: boolean
  departmentId: string
  catalog: LayoutCatalog | null
  /** Vorhandene Infoscreens mit Layout (Quellen zum Duplizieren). */
  sources: DisplayScreenSettings[]
}>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; created: [screen: DisplayScreenSettings] }>()

const { t } = useI18n()
const toast = useToast()
const open = computed({ get: () => props.modelValue, set: (v: boolean) => emit('update:modelValue', v) })
const name = ref('')
const choice = ref('blank')
const creating = ref(false)

const copySources = computed(() => props.sources.filter((s) => !s.revoked_at && s.layout?.has_draft))

watch(open, (isOpen) => {
  if (isOpen) {
    name.value = ''
    choice.value = 'blank'
  }
})

async function create() {
  creating.value = true
  try {
    const seed = !props.catalog?.enabled
      ? undefined
      : choice.value.startsWith('copy:')
        ? { source_screen_id: choice.value.slice(5) }
        : { template_id: choice.value }
    const screen = await createDisplayScreen(props.departmentId, name.value.trim(), seed)
    emit('created', screen)
    open.value = false
  } catch (err) {
    const msg = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(msg || t('settings.displayScreens.toastCreateError'))
  } finally {
    creating.value = false
  }
}
</script>

<style scoped>
.create-options {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 10px;
  margin-top: 6px;
}
.create-option {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px;
  text-align: left;
  border: 2px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
}
.create-option--on { border-color: #2563eb; background: #eff6ff; }
.create-option small { color: #6b7280; }
.muted { color: #6b7280; }
</style>
