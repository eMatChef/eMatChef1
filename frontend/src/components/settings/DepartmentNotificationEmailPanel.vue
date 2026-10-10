<template>
  <div v-if="data" class="mt-3 rounded-lg border border-slate-200 px-3 py-3">
    <ESelect
      :model-value="selectValue"
      :items="items"
      :label="t('settings.myDepartment.notificationEmail.label')"
      :hint="t('settings.myDepartment.notificationEmail.hint')"
      persistent-hint
      :disabled="saving"
      @update:model-value="onChange"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ESelect } from '@/components/form/base'
import { useToast } from '@/composables/useToast'
import {
  getDepartmentNotificationEmail,
  setDepartmentNotificationEmail,
  type DepartmentNotificationEmail,
} from '@/api/departmentNotificationEmail'

const PRIMARY = '__primary__'

const props = defineProps<{ departmentId: string }>()

const { t } = useI18n()
const toast = useToast()

const data = ref<DepartmentNotificationEmail | null>(null)
const saving = ref(false)

const selectValue = computed(() => data.value?.selected_email ?? PRIMARY)

const items = computed(() => {
  if (!data.value) return []
  const primary = data.value.primary_email
  return [
    { title: t('settings.myDepartment.notificationEmail.usePrimary', { email: primary }), value: PRIMARY },
    ...data.value.options.filter((email) => email !== primary).map((email) => ({ title: email, value: email })),
  ]
})

function errorMessage(e: unknown, fallback: string): string {
  const err = e as { response?: { data?: { error?: string } } }
  return err.response?.data?.error || fallback
}

async function load() {
  data.value = null
  if (!props.departmentId) return
  try {
    data.value = await getDepartmentNotificationEmail(props.departmentId)
  } catch {
    // Keine Mitgliedschaft oder Ladefehler: Bereich nicht anzeigen.
    data.value = null
  }
}

async function onChange(value: unknown) {
  if (typeof value !== 'string' || saving.value) return
  saving.value = true
  try {
    data.value = await setDepartmentNotificationEmail(props.departmentId, value === PRIMARY ? null : value)
    toast.success(t('settings.myDepartment.notificationEmail.saved', { email: data.value.effective_email }))
  } catch (e: unknown) {
    toast.error(errorMessage(e, t('settings.myDepartment.notificationEmail.saveError')))
    await load()
  } finally {
    saving.value = false
  }
}

watch(() => props.departmentId, () => void load(), { immediate: true })

// Profil → Sicherheit hat Adressen geändert (Hauptadresse/zusätzliche): Auswahl und Anzeige neu laden.
const reload = () => void load()
onMounted(() => window.addEventListener('emc-profile-emails-changed', reload))
onUnmounted(() => window.removeEventListener('emc-profile-emails-changed', reload))
</script>
