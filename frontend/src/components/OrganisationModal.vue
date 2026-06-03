<template>
  <EDialog
    v-model="dialogOpen"
    :max-width="500"
    :title="isEdit ? t('components.organisationModal.editTitle') : t('components.organisationModal.addTitle')"
    scrollable
    persistent
  >
    <form id="organisation-modal-form" @submit.prevent="handleSubmit">
      <ETextField
        id="organisation-name"
        ref="nameInput"
        v-model="formData.name"
        :label="t('components.organisationModal.nameLabel')"
        :placeholder="t('components.organisationModal.namePlaceholder')"
        :disabled="isSubmitting"
        hide-details="auto"
        class="mb-3"
      />

      <div class="language-section">
        <p class="language-label">{{ t('components.organisationModal.allowedLanguagesLabel') }}</p>
        <p class="helper-text">{{ t('components.organisationModal.allowedLanguagesHint') }}</p>
        <div class="language-grid">
          <ECheckbox
            v-for="item in supportedLanguages"
            :key="item.code"
            :model-value="formData.allowedLanguages.includes(item.code)"
            :label="item.label"
            :disabled="isSubmitting"
            hide-details
            @update:model-value="toggleLanguage(item.code, $event)"
          />
        </div>
      </div>

      <v-alert
        v-if="error"
        type="error"
        variant="tonal"
        class="mt-3"
        :text="error"
      />
    </form>

    <template #actions>
      <EButton variant="secondary" :disabled="isSubmitting" @click="close">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        type="submit"
        form="organisation-modal-form"
        :loading="isSubmitting"
        :disabled="isSubmitting"
      >
        {{
          isSubmitting
            ? t('components.organisationModal.saving')
            : (isEdit ? t('common.save') : t('common.add'))
        }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { createOrganisation, updateOrganisation, type Organisation } from '@/api/organisations'
import { SUPPORTED_LANGUAGE_CODES } from '@/config/languages'
import { EButton, ECheckbox, EDialog, ETextField } from '@/components/form/base'

interface Props {
  isOpen: boolean
  organisation?: Organisation | null
}

const props = withDefaults(defineProps<Props>(), {
  organisation: null
})

const emit = defineEmits<{
  'close': []
  'saved': []
}>()

const { t } = useI18n()
const toast = useToast()
const isEdit = computed(() => !!props.organisation)
const isSubmitting = ref(false)
const error = ref<string | null>(null)
const nameInput = ref<{ focus?: () => void; select?: () => void } | null>(null)

const dialogOpen = computed({
  get: () => props.isOpen,
  set: (value: boolean) => {
    if (!value) close()
  },
})

const formData = ref({
  name: '',
  allowedLanguages: [] as string[]
})

const supportedLanguages = computed(() =>
  SUPPORTED_LANGUAGE_CODES.map((code) => ({
    code,
    label: t(`languageNames.${code}` as 'languageNames.de')
  }))
)

function toggleLanguage(code: string, checked: boolean | null) {
  if (checked) {
    if (!formData.value.allowedLanguages.includes(code)) {
      formData.value.allowedLanguages.push(code)
    }
  } else {
    formData.value.allowedLanguages = formData.value.allowedLanguages.filter((c) => c !== code)
  }
}

watch(
  () => [props.isOpen, props.organisation],
  async (tuple) => {
    const open = tuple[0]
    const org = tuple[1] as Organisation | null | undefined
    if (open) {
      error.value = null
      if (org && org.id) {
        formData.value = {
          name: org.name || '',
          allowedLanguages: Array.isArray(org.allowed_languages) ? [...org.allowed_languages] : []
        }
      } else {
        formData.value = {
          name: '',
          allowedLanguages: []
        }
      }
      await nextTick()
      await nextTick()
      nameInput.value?.focus?.()
      nameInput.value?.select?.()
    }
  },
  { immediate: true }
)

async function handleSubmit() {
  if (!formData.value.name) {
    error.value = t('components.organisationModal.nameRequired')
    return
  }

  try {
    isSubmitting.value = true
    error.value = null

    if (isEdit.value && props.organisation && props.organisation.id) {
      await updateOrganisation(props.organisation.id, {
        name: formData.value.name,
        allowed_languages: formData.value.allowedLanguages.length > 0 ? formData.value.allowedLanguages : null
      })
    } else {
      await createOrganisation({
        name: formData.value.name,
        allowed_languages: formData.value.allowedLanguages.length > 0 ? formData.value.allowedLanguages : null
      })
    }

    emit('saved')
    close()
  } catch (err: any) {
    const msg = err.response?.data?.error || t('components.organisationModal.saveError')
    error.value = msg
    toast.error(msg)
  } finally {
    isSubmitting.value = false
  }
}

function close() {
  emit('close')
  formData.value = { name: '', allowedLanguages: [] }
  error.value = null
}
</script>

<style scoped>
.language-section {
  margin-top: 8px;
}

.language-label {
  margin: 0 0 4px;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
}

.helper-text {
  margin: 0 0 10px;
  color: #6b7280;
  font-size: 12px;
}

.language-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 4px 12px;
}
</style>
