<template>
  <EDialog
    v-model="dialogOpen"
    :max-width="480"
    :title="title"
    persistent
  >
    <form id="supplier-onboard-form" @submit.prevent="submit">
      <ETextField
        v-if="showNameField"
        v-model="form.name"
        :label="t('globalAddressesPage.supplierModal.name')"
        hide-details="auto"
        class="mb-3"
      />

      <ETextField
        v-model="form.manufacturer_key"
        :label="t('globalAddressesPage.supplierModal.manufacturerKey')"
        :placeholder="manufacturerKeyPlaceholder"
        hide-details="auto"
        class="mb-3"
      />

      <ETextField
        v-model="form.admin_user_email"
        type="email"
        :label="t('globalAddressesPage.supplierModal.adminEmail')"
        hide-details="auto"
        class="mb-3"
      />

      <v-alert v-if="error" type="error" variant="tonal" :text="error" />
    </form>

    <template #actions>
      <EButton variant="secondary" size="small" @click="close">{{ t('common.cancel') }}</EButton>
      <EButton
        variant="primary"
        size="small"
        type="submit"
        form="supplier-onboard-form"
        :disabled="saving"
        :loading="saving"
      >
        {{ submitLabel }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { EButton, EDialog, ETextField } from '@/components/form/base'

const props = withDefaults(
  defineProps<{
    title: string
    submitLabel: string
    initialName?: string
    showNameField?: boolean
    manufacturerKeyPlaceholder?: string
  }>(),
  {
    initialName: '',
    showNameField: true,
    manufacturerKeyPlaceholder: '',
  },
)

const emit = defineEmits<{
  close: []
  submit: [payload: { name: string; manufacturer_key: string; admin_user_email: string }]
}>()

const { t } = useI18n()
const dialogOpen = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

const form = reactive({
  name: props.initialName,
  manufacturer_key: '',
  admin_user_email: '',
})

const manufacturerKeyPlaceholder = computed(
  () => props.manufacturerKeyPlaceholder || t('globalAddressesPage.supplierModal.manufacturerKeyHint'),
)

watch(dialogOpen, (open) => {
  if (!open) emit('close')
})

function close() {
  dialogOpen.value = false
}

function submit() {
  error.value = null
  if (props.showNameField && !form.name.trim()) {
    error.value = t('globalAddressesPage.supplierModal.errorNameRequired')
    return
  }
  saving.value = true
  emit('submit', {
    name: form.name.trim(),
    manufacturer_key: form.manufacturer_key.trim(),
    admin_user_email: form.admin_user_email.trim(),
  })
  saving.value = false
}
</script>
