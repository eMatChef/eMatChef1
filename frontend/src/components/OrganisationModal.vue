<template>
  <div v-if="isOpen" class="modal-overlay">
    <div class="modal-dialog organisation-modal-dialog">
      <div class="modal-header">
        <h2>{{ isEdit ? 'Organisation bearbeiten' : 'Neue Organisation hinzufügen' }}</h2>
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

          <!-- Error Message -->
          <div v-if="error" class="error-message">
            {{ error }}
          </div>

          <!-- Buttons -->
          <div class="modal-footer">
            <button type="button" @click="close" class="btn-secondary">
              Abbrechen
            </button>
            <button type="submit" class="btn-primary" :disabled="isSubmitting">
              {{ isSubmitting ? 'Wird gespeichert...' : (isEdit ? 'Speichern' : 'Hinzufügen') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed, nextTick } from 'vue'
import { useToast } from '@/composables/useToast'
import { createOrganisation, updateOrganisation, type Organisation } from '@/api/organisations'

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

const toast = useToast()
const isEdit = computed(() => !!props.organisation)
const isSubmitting = ref(false)
const error = ref<string | null>(null)
const nameInput = ref<HTMLInputElement | null>(null)

const formData = ref({
  name: ''
})

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
          name: org.name || ''
        }
      } else {
        // Neu erstellen: Leeres Formular
        formData.value = {
          name: ''
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
    error.value = 'Bitte geben Sie einen Namen ein'
    return
  }

  try {
    isSubmitting.value = true
    error.value = null

    if (isEdit.value && props.organisation && props.organisation.id) {
      await updateOrganisation(props.organisation.id, {
        name: formData.value.name
      })
    } else {
      await createOrganisation({
        name: formData.value.name
      })
    }

    emit('saved')
    close()
  } catch (err: any) {
    const msg = err.response?.data?.error || 'Fehler beim Speichern der Organisation'
    error.value = msg
    toast.error(msg)
  } finally {
    isSubmitting.value = false
  }
}

function close() {
  emit('close')
  formData.value = { name: '' }
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

.error-message {
  background: #fee2e2;
  color: #dc2626;
  padding: 12px;
  border-radius: 6px;
  font-size: 14px;
  margin-bottom: 20px;
}

</style>
