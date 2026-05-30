<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card">
      <header class="modal-header">
        <h3>{{ title }}</h3>
        <button type="button" class="btn btn-secondary btn-inline" @click="emit('close')">
          {{ t('common.cancel') }}
        </button>
      </header>

      <form class="modal-body" @submit.prevent="submit">
        <label v-if="showNameField" class="field">
          <span>{{ t('globalAddressesPage.supplierModal.name') }}</span>
          <input v-model.trim="form.name" type="text" required />
        </label>

        <label class="field">
          <span>{{ t('globalAddressesPage.supplierModal.manufacturerKey') }}</span>
          <input v-model.trim="form.manufacturer_key" type="text" :placeholder="manufacturerKeyPlaceholder" />
        </label>

        <label class="field">
          <span>{{ t('globalAddressesPage.supplierModal.adminEmail') }}</span>
          <input v-model.trim="form.admin_user_email" type="email" />
        </label>

        <p v-if="error" class="error">{{ error }}</p>

        <footer class="modal-footer">
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ submitLabel }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

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
  }
)

const emit = defineEmits<{
  close: []
  submit: [payload: { name: string; manufacturer_key: string; admin_user_email: string }]
}>()

const { t } = useI18n()
const saving = ref(false)
const error = ref<string | null>(null)

const form = reactive({
  name: props.initialName,
  manufacturer_key: '',
  admin_user_email: '',
})

const manufacturerKeyPlaceholder = computed(
  () => props.manufacturerKeyPlaceholder || t('globalAddressesPage.supplierModal.manufacturerKeyHint')
)

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

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  background: #fff;
  border-radius: 12px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.15);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid #e5e7eb;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
}

.modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 14px;
}

.field input {
  padding: 8px 10px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  padding-top: 8px;
}

.error {
  color: #b91c1c;
  font-size: 14px;
}

.btn-inline {
  padding: 6px 10px;
  font-size: 12px;
}
</style>
