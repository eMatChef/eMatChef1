<template>
  <div class="mail-templates-view">
    <div class="header">
      <h1>Mailversandvorlagen</h1>
      <p class="subtitle">Uebersicht aller aktuell verwendeten E-Mail-Vorlagen im System.</p>
    </div>

    <div v-if="isLoading" class="state-box">Lade Mailvorlagen...</div>
    <div v-else-if="error" class="state-box error">{{ error }}</div>

    <div v-else class="template-list">
      <article v-for="tpl in templates" :key="tpl.key" class="template-card">
        <div class="template-head">
          <h2>{{ tpl.title }}</h2>
          <span class="template-key">{{ tpl.key }}</span>
        </div>
        <p class="template-description">{{ tpl.description }}</p>

        <div class="template-block">
          <p class="block-label">Betreff</p>
          <code class="subject">{{ tpl.subject }}</code>
        </div>

        <div class="template-block">
          <p class="block-label">Text-Vorschau</p>
          <pre class="body">{{ tpl.body_preview }}</pre>
        </div>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { getMailTemplates, type MailTemplateDefinition } from '@/api/mailTemplates'

const route = useRoute()
const templates = ref<MailTemplateDefinition[]>([])
const isLoading = ref(true)
const error = ref('')

const departmentId = computed(() => {
  const raw = route.params.departmentId
  return typeof raw === 'string' && raw.trim() ? raw : undefined
})

async function loadTemplates() {
  isLoading.value = true
  error.value = ''
  try {
    templates.value = await getMailTemplates(departmentId.value)
  } catch (err: any) {
    error.value = err.response?.data?.error || 'Mailvorlagen konnten nicht geladen werden.'
    templates.value = []
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadTemplates()
})
</script>

<style scoped>
.mail-templates-view {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.header h1 {
  margin: 0 0 6px 0;
  font-size: 24px;
}

.subtitle {
  margin: 0;
  color: #6b7280;
  font-size: 14px;
}

.state-box {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px;
  background: #f9fafb;
}

.state-box.error {
  border-color: #fecaca;
  background: #fef2f2;
  color: #991b1b;
}

.template-list {
  display: grid;
  gap: 12px;
}

.template-card {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  padding: 14px;
}

.template-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.template-head h2 {
  margin: 0;
  font-size: 16px;
}

.template-key {
  font-family: Consolas, Monaco, 'Courier New', monospace;
  font-size: 12px;
  color: #4b5563;
  background: #f3f4f6;
  border-radius: 6px;
  padding: 3px 8px;
}

.template-description {
  margin: 8px 0 10px 0;
  color: #6b7280;
  font-size: 13px;
}

.template-block {
  margin-top: 10px;
}

.block-label {
  margin: 0 0 6px 0;
  font-size: 12px;
  color: #6b7280;
  text-transform: uppercase;
}

.subject {
  display: block;
  padding: 8px 10px;
  border-radius: 8px;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  font-size: 13px;
}

.body {
  margin: 0;
  white-space: pre-wrap;
  padding: 10px;
  border-radius: 8px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  font-size: 12px;
  color: #334155;
}
</style>
