<template>
  <EDialog v-model="open" :max-width="520" :title="helper ? t('grossanlass.helferpool.form.editTitle') : t('grossanlass.helferpool.form.createTitle')" scrollable>
    <form class="form" @submit.prevent="save">
      <div class="form__row">
        <ETextField v-model="form.first_name" :label="t('grossanlass.helferpool.field.firstName')" autofocus />
        <ETextField v-model="form.last_name" :label="t('grossanlass.helferpool.field.lastName')" />
      </div>
      <ETextField v-model="form.email" type="email" :label="t('grossanlass.helferpool.field.email')" />
      <ETextField v-model="form.phone" :label="t('grossanlass.helferpool.field.phone')" />
      <ETextarea v-model="form.note" :label="t('grossanlass.helferpool.field.note')" rows="2" />
      <p class="form__hint">{{ t('grossanlass.helferpool.form.hint') }}</p>
      <p v-if="error" class="form__error" role="alert">{{ error }}</p>
    </form>
    <template #actions>
      <EButton variant="text" @click="open = false">{{ t('common.cancel') }}</EButton>
      <EButton variant="primary" :loading="saving" :disabled="!canSave" @click="save">{{ t('common.save') }}</EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { createGaHelper, updateGaHelper, type GaHelper } from '@/api/grossanlassStaffing'
import { EButton, EDialog, ETextarea, ETextField } from '@/components/form/base'
import { apiErrorMessage } from '@/utils/apiErrorMessage'

/** Helfer erfassen oder bearbeiten. V1 erfasst nur Name, E-Mail, Telefon und Notiz; ein Login ist nicht nötig. */
const props = defineProps<{ departmentId: string; helper: GaHelper | null }>()
const emit = defineEmits<{ saved: [helper: GaHelper] }>()
const open = defineModel<boolean>({ default: false })
const { t } = useI18n()

const form = reactive({ first_name: '', last_name: '', email: '', phone: '', note: '' })
const saving = ref(false)
const error = ref('')

watch(open, (value) => {
  if (!value) return
  error.value = ''
  form.first_name = props.helper?.first_name ?? ''
  form.last_name = props.helper?.last_name ?? ''
  form.email = props.helper?.email ?? ''
  form.phone = props.helper?.phone ?? ''
  form.note = props.helper?.note ?? ''
})

const canSave = computed(() => (form.first_name.trim() !== '' || form.last_name.trim() !== '') && !saving.value)

async function save() {
  if (!canSave.value) return
  saving.value = true
  error.value = ''
  try {
    const payload = { first_name: form.first_name.trim(), last_name: form.last_name.trim(), email: form.email.trim(), phone: form.phone.trim(), note: form.note.trim() }
    const saved = props.helper
      ? await updateGaHelper(props.departmentId, props.helper.id, payload)
      : await createGaHelper(props.departmentId, payload)
    emit('saved', saved)
    open.value = false
  } catch (err) {
    error.value = apiErrorMessage(err, t('grossanlass.helferpool.error.save'))
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.form {
  display: grid;
  gap: 10px;
}
.form__row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
.form__hint {
  margin: 0;
  color: #64748b;
  font-size: 0.82rem;
}
.form__error {
  margin: 0;
  color: #b91c1c;
  font-size: 0.9rem;
}
@media (max-width: 520px) {
  .form__row {
    grid-template-columns: 1fr;
  }
}
</style>
