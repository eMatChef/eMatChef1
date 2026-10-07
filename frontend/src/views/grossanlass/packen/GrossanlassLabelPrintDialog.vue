<template>
  <EDialog v-model="open" :title="t('grossanlass.packen.label.title')" max-width="420" :retain-focus="false">
    <div class="label-preview">
      <PublicQrTag :url="qrUrl" :code="code" :size="168" :image-label="title" :image-entity-id="code" />
      <strong class="label-preview__code">{{ code }}</strong>
      <span class="label-preview__title">{{ title }}</span>
      <span v-for="line in lines" :key="line" class="label-preview__line">{{ line }}</span>
    </div>
    <p class="label-note">{{ t('grossanlass.packen.label.demoNote') }}</p>
    <template #actions>
      <EButton variant="secondary" @click="open = false">{{ t('common.close') }}</EButton>
      <EButton variant="primary" @click="print">
        <v-icon icon="mdi-printer" start size="18" />
        {{ t('grossanlass.packen.label.print') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog } from '@/components/form/base'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import { useToast } from '@/composables/useToast'

const props = defineProps<{
  modelValue: boolean
  code: string
  title: string
  lines?: string[]
}>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; printed: [] }>()
const { t } = useI18n()
const toast = useToast()

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
/** Demo-URL: das echte Label-Format und der Druckpfad folgen später. */
const qrUrl = computed(() => `${window.location.origin}/p/${props.code}`)

function print() {
  toast.success(t('grossanlass.packen.label.sent', { code: props.code }))
  emit('printed')
}
</script>

<style scoped>
.label-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 18px;
  border: 2px dashed #94a3b8;
  border-radius: 12px;
  background: #fff;
  text-align: center;
}
.label-preview__code {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 1.15rem;
  letter-spacing: 0.04em;
}
.label-preview__title {
  font-weight: 600;
}
.label-preview__line {
  color: #475569;
  font-size: 0.85rem;
}
.label-note {
  margin: 10px 0 0;
  color: #64748b;
  font-size: 0.8rem;
}
</style>
