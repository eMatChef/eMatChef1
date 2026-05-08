<template>
  <div class="modal-overlay">
    <div class="modal-dialog category-modal">
      <div class="modal-header">
        <h2>{{ isEditing ? t('settings.categories.modal.editTitle') : t('settings.categories.modal.newTitle') }}</h2>
        <button @click="close" class="modal-close">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
            <path d="M15 5L5 15M5 5L15 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
      
      <form @submit.prevent="handleSubmit" class="modal-body">
        <!-- Name -->
        <div class="form-group">
          <label class="form-label">{{ t('settings.categories.modal.nameLabel') }} <span class="required">*</span></label>
          <input 
            v-model="formData.name" 
            type="text" 
            class="form-input" 
            :placeholder="t('settings.categories.modal.namePlaceholder')"
            required
            ref="nameInput"
          />
        </div>

        <!-- Übergeordnete Kategorie -->
        <div class="form-group">
          <label class="form-label">{{ t('settings.categories.modal.parentLabel') }}</label>
          <select v-model="formData.parent_id" class="form-select">
            <option :value="null">{{ t('settings.categories.modal.parentMain') }}</option>
            <option 
              v-for="cat in availableParents" 
              :key="cat.id" 
              :value="cat.id"
            >
              {{ cat.parent_id ? '  └ ' : '' }}{{ cat.name }}
            </option>
          </select>
          <p class="form-hint">{{ t('settings.categories.modal.parentHint') }}</p>
        </div>

        <!-- Beschreibung -->
        <div class="form-group">
          <label class="form-label">{{ t('settings.categories.modal.descriptionLabel') }}</label>
          <textarea 
            v-model="formData.description" 
            class="form-textarea" 
            rows="2"
            :placeholder="t('settings.categories.modal.descriptionPlaceholder')"
          ></textarea>
        </div>

        <!-- Fehler -->
        <div v-if="error" class="error-message">
          {{ error }}
        </div>

        <!-- Buttons -->
        <div class="modal-footer">
          <button type="button" @click="close" class="btn-secondary">
            {{ t('common.cancel') }}
          </button>
          <button type="submit" class="btn-primary" :disabled="isLoading || !formData.name">
            {{ isLoading ? t('common.saving') : (isEditing ? t('common.save') : t('common.create')) }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { getCategories, createCategory, updateCategory, type Category, type CreateCategoryRequest, type UpdateCategoryRequest } from '@/api/categories'

interface Props {
  departmentId: string
  category?: Category | null
  defaultName?: string
  defaultParentId?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  category: null,
  defaultName: '',
  defaultParentId: null
})

const emit = defineEmits<{
  close: []
  saved: [category: Category]
}>()

const { t } = useI18n()
const toast = useToast()
const nameInput = ref<HTMLInputElement>()
const isLoading = ref(false)
const error = ref('')
const allCategories = ref<Category[]>([])

const formData = ref({
  name: props.defaultName || props.category?.name || '',
  description: props.category?.description || '',
  parent_id: props.defaultParentId || props.category?.parent_id || null
})

const isEditing = computed(() => !!props.category)

// Verfügbare Eltern-Kategorien (ohne sich selbst und eigene Kinder)
const availableParents = computed(() => {
  if (!isEditing.value) {
    // Beim Erstellen: Alle Hauptkategorien als mögliche Eltern
    return allCategories.value.filter(c => !c.parent_id)
  }
  
  // Beim Bearbeiten: Sich selbst und eigene Kinder ausschließen
  const excludeIds = new Set<string>([props.category!.id])
  
  // Finde alle Kinder rekursiv
  const findChildren = (parentId: string) => {
    allCategories.value
      .filter(c => c.parent_id === parentId)
      .forEach(c => {
        excludeIds.add(c.id)
        findChildren(c.id)
      })
  }
  findChildren(props.category!.id)
  
  return allCategories.value.filter(c => !excludeIds.has(c.id) && !c.parent_id)
})

function close() {
  emit('close')
}

async function loadCategories() {
  try {
    allCategories.value = await getCategories(props.departmentId)
  } catch (err) {
    console.error('Fehler beim Laden der Kategorien:', err)
  }
}

async function handleSubmit() {
  if (!formData.value.name.trim()) {
    error.value = t('settings.categories.modal.nameRequired')
    return
  }

  isLoading.value = true
  error.value = ''

  try {
    let savedCategory: Category

    if (isEditing.value && props.category) {
      // Aktualisieren
      const updateData: UpdateCategoryRequest = {
        name: formData.value.name.trim(),
        description: formData.value.description?.trim() || null,
        parent_id: formData.value.parent_id
      }
      savedCategory = await updateCategory(props.category.id, updateData)
    } else {
      // Erstellen
      const createData: CreateCategoryRequest = {
        department_id: props.departmentId,
        name: formData.value.name.trim(),
        description: formData.value.description?.trim() || null,
        parent_id: formData.value.parent_id
      }
      savedCategory = await createCategory(createData)
    }

    emit('saved', savedCategory)
  } catch (err: any) {
    const msg = err.response?.data?.error || t('settings.categories.modal.saveError')
    error.value = msg
    toast.error(msg)
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await loadCategories()
  // Fokus auf Name-Feld
  await nextTick()
  nameInput.value?.focus()
})
</script>

<style scoped>
/* Modal overlay/dialog/header/body/footer base uses shared ui/modals.css */
.category-modal {
  width: min(480px, calc(100vw - 48px));
  max-height: calc(100vh - 48px);
  padding: 0;
  overflow: hidden;
}

.modal-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

/* Form group/input/select/textarea base uses shared ui/forms.css */

.form-group:last-of-type {
  margin-bottom: 0;
}

.required {
  color: #ef4444;
}

.form-textarea {
  resize: vertical;
  min-height: 60px;
}

.form-hint {
  font-size: 12px;
  color: #6b7280;
  margin-top: 4px;
}

.error-message {
  background: #fef2f2;
  color: #dc2626;
  padding: 10px 14px;
  border-radius: 8px;
  font-size: 13px;
  margin-top: 16px;
}

</style>
