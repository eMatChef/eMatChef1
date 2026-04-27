<template>
  <div v-if="isOpen" class="modal-overlay">
    <div class="modal-dialog organisation-modal-dialog">
      <div class="modal-header">
        <h2>{{ isEdit ? t('components.organisationModal.editTitle') : t('components.organisationModal.addTitle') }}</h2>
        <button @click="close" class="modal-close">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path
              d="M15 5L5 15M5 5L15 15"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
            />
          </svg>
        </button>
      </div>

      <div class="modal-body">
        <form @submit.prevent="handleSubmit">
          <!-- Organisation Name -->
          <div class="form-group">
            <label for="organisation-name" class="form-label">Name *</label>
            <input
              id="organisation-name"
              ref="nameInput"
              v-model="formData.name"
              type="text"
              class="form-input"
              placeholder="Organisation Name"
              required
              :disabled="isSubmitting"
            />
          </div>

          <div class="form-group">
            <label class="form-label">{{ t('components.organisationModal.allowedLanguagesLabel') }}</label>
            <p class="helper-text">{{ t('components.organisationModal.allowedLanguagesHint') }}</p>
            <div class="language-grid">
              <label v-for="item in supportedLanguages" :key="item.code" class="checkbox-row">
                <input v-model="formData.allowedLanguages" type="checkbox" :value="item.code" :disabled="isSubmitting" />
                <span>{{ item.label }}</span>
              </label>
            </div>
          </div>

          <!-- Error Message -->
          <div v-if="error" class="error-message">
            {{ error }}
          </div>

          <!-- Buttons -->
          <div class="modal-footer">
            <button type="button" @click="close" class="btn-secondary">
              {{ t('common.cancel') }}
            </button>
            <button type="submit" class="btn-primary" :disabled="isSubmitting">
              {{
                isSubmitting
                  ? t('components.organisationModal.saving')
                  : (isEdit ? t('common.save') : t('components.organisationModal.addSubmit'))
              }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { createOrganisation, updateOrganisation, type Organisation } from '@/api/organisations'
import { SUPPORTED_LANGUAGE_CODES } from '@/config/languages'

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
const nameInput = ref<HTMLInputElement | null>(null)

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

// Watch für Modal-Öffnung und Organisation-Änderungen
watch(
  () => [props.isOpen, props.organisation],
  async (tuple) => {
    const open = tuple[0]
    const org = tuple[1] as Organisation | null | undefined
    if (open) {
      error.value = null
      // Stelle sicher, dass formData korrekt gesetzt ist
      if (org && org.id) {
        // Bearbeiten: Setze den Namen der Organisation
        formData.value = {
          name: org.name || '',
          allowedLanguages: Array.isArray(org.allowed_languages) ? [...org.allowed_languages] : []
        }
      } else {
        // Neu erstellen: Leeres Formular
        formData.value = {
          name: '',
          allowedLanguages: []
        }
      }
      // Fokussiere das Input-Feld nach dem Rendern
      await nextTick()
      await nextTick() // Doppeltes nextTick für sicherere DOM-Updates
      if (nameInput.value) {
        nameInput.value.focus()
        nameInput.value.select()
      }
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
/* Modal overlay/dialog/header/body/footer base uses shared ui/modals.css */
.organisation-modal-dialog {
  width: min(500px, calc(100vw - 48px));
  max-height: calc(100vh - 48px);
  padding: 0;
  overflow: hidden;
}

.modal-header h2 {
  font-size: 20px;
  font-weight: 600;
  color: #1f2937;
  margin: 0;
}

/* Form group/input base uses shared ui/forms.css */

.form-label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 8px;
}

.form-input:disabled {
  background-color: #f3f4f6;
  cursor: not-allowed;
  opacity: 0.6;
}

.helper-text {
  margin: -4px 0 10px;
  color: #6b7280;
  font-size: 12px;
}

.language-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px 12px;
}

.checkbox-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #374151;
}

.error-message {
  background: #fee2e2;
  color: #dc2626;
  padding: 12px;
  border-radius: 6px;
  font-size: 14px;
  margin-bottom: 20px;
}

</style>
