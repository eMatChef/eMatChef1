<template>
  <div class="public-layout">
    <header class="public-header" role="banner">
      <RouterLink to="/" class="public-brand" title="eMatChef">
        <EmcLogoMark size="sm" />
        <span class="public-brand-text">eMatChef</span>
      </RouterLink>
      <div class="public-header-actions">
        <button
          v-if="!authStore.isLoggedIn"
          type="button"
          class="public-login-btn"
          @click="goToLogin"
        >
          Anmelden
        </button>
        <div v-else class="public-header-logged-in">
          <div
            class="public-user-chip"
            :title="authStore.userEmail || undefined"
            :aria-label="`Angemeldet als ${authStore.userDisplayName}`"
          >
            <span class="public-user-avatar" :style="publicAvatarStyle">
              {{ authStore.userInitials }}
            </span>
            <span class="public-user-name">{{ authStore.userDisplayName }}</span>
          </div>
          <button
            type="button"
            class="public-login-btn public-login-btn--primary"
            @click="goToApp"
          >
            Zur App
          </button>
        </div>
      </div>
    </header>

    <main class="public-page">
    <section class="public-card">
      <h1 class="public-title">{{ routeType === 'b' ? 'Seriennummer-Info' : 'Material-Info' }}</h1>

      <p v-if="loading" class="muted">Lade Daten...</p>
      <p v-else-if="error" class="error">{{ error }}</p>

      <template v-else-if="data">
        <p class="public-code">Code: {{ routeCode }}</p>
        <p v-if="routeType === 'b'" class="public-code">
          Serie: {{ data.batch?.serial_number || data.batch?.label || data.batch?.id }}
        </p>
        <h2 class="material-name">{{ data.material.name }}</h2>

        <p v-if="data.material.description" class="material-desc">
          {{ data.material.description }}
        </p>

        <dl class="info-grid">
          <div>
            <dt>Abteilung</dt>
            <dd>{{ data.department.name }}</dd>
          </div>
          <div v-if="data.material.manufacturer">
            <dt>Hersteller</dt>
            <dd>{{ data.material.manufacturer }}</dd>
          </div>
          <div v-if="data.material.model">
            <dt>Modell</dt>
            <dd>{{ data.material.model }}</dd>
          </div>
        </dl>

        <div v-if="showPublicContactForm" class="contact-collapsible">
          <button
            type="button"
            class="contact-toggle"
            :aria-expanded="contactExpanded"
            aria-controls="public-contact-panel"
            id="public-contact-toggle"
            @click="contactExpanded = !contactExpanded"
          >
            <span class="contact-toggle-label">Materialwart kontaktieren</span>
            <span class="contact-toggle-chevron" :class="{ 'is-open': contactExpanded }" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9" />
              </svg>
            </span>
          </button>

          <div
            v-show="contactExpanded"
            id="public-contact-panel"
            class="contact-panel"
            role="region"
            aria-labelledby="public-contact-toggle"
          >
            <div v-if="data.contact || data.contact_note" class="contact-box contact-box--in-panel">
              <h3>Kontakt</h3>
              <p v-if="data.contact?.email">E-Mail: {{ data.contact.email }}</p>
              <p v-if="data.contact_note" class="contact-note">{{ data.contact_note }}</p>
            </div>

            <div v-if="canDeliverPublicMessage" class="found-form-box">
              <h3 class="found-form-title">Nachricht senden</h3>
              <p class="found-form-hint">
                Du hast diesen Artikel gefunden oder möchtest den Materialwart erreichen? Sende eine kurze Nachricht.
              </p>
              <form class="found-form" @submit.prevent="submitFoundContact">
                <label class="found-label hp" aria-hidden="true">
                  Website
                  <input v-model="foundForm.website" type="text" name="website" tabindex="-1" autocomplete="off" />
                </label>
                <label class="found-label">
                  Dein Name <span class="optional">(optional)</span>
                  <input v-model="foundForm.sender_name" type="text" maxlength="120" placeholder="z. B. Vorname" />
                </label>
                <label class="found-label">
                  Deine E-Mail <span class="optional">(optional, für Rückfragen)</span>
                  <input
                    v-model="foundForm.sender_email"
                    type="email"
                    maxlength="200"
                    placeholder="name@beispiel.ch"
                  />
                </label>
                <label class="found-label">
                  Nachricht <span class="req">*</span>
                  <textarea
                    v-model="foundForm.message"
                    rows="4"
                    maxlength="4000"
                    required
                    placeholder="z. B. Wo liegt der Artikel? Wann hast du ihn gefunden?"
                  />
                </label>
                <p v-if="foundFormError" class="error found-form-msg">{{ foundFormError }}</p>
                <p v-else-if="foundFormSuccess" class="found-form-msg success">Nachricht wurde gesendet. Vielen Dank.</p>
                <button type="submit" class="found-submit" :disabled="foundFormSubmitting">
                  {{ foundFormSubmitting ? 'Wird gesendet…' : 'An Materialwart senden' }}
                </button>
              </form>
            </div>
            <div v-else class="found-form-box found-form-unavailable">
              <p class="muted">
                Für diese Abteilung ist aktuell keine Kontakt-E-Mail hinterlegt – eine Nachricht kann hier nicht
                zugestellt werden.
              </p>
            </div>
          </div>
        </div>
      </template>
    </section>
    </main>

    <PublicSiteFooter />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  getPublicBatchByCode,
  getPublicMaterialByCode,
  submitPublicFoundItemContact,
  type PublicLookupBatchResponse,
  type PublicLookupMaterialResponse,
} from '../../api/public/publicLookup'
import { useAuthStore } from '../../stores/auth'
import EmcLogoMark from '../../components/brand/EmcLogoMark.vue'
import PublicSiteFooter from '../../components/public/PublicSiteFooter.vue'
import { DEFAULT_DOCUMENT_TITLE } from '../../composables/usePageHead'
import { usePageHeadStore } from '../../stores/pageHead'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const pageHeadStore = usePageHeadStore()

const routeType = computed(() => String(route.params.type || 'm').trim().toLowerCase())
const routeCode = computed(() => String(route.params.code || '').trim())

const loading = ref(false)
const error = ref<string | null>(null)
type PublicLookupViewData = PublicLookupMaterialResponse | PublicLookupBatchResponse
const data = ref<PublicLookupViewData | null>(null)

const publicAvatarStyle = computed(() => ({
  backgroundColor: authStore.userColors.background,
  color: authStore.userColors.text,
}))

const pageTitle = computed(() => {
  if (loading.value) return DEFAULT_DOCUMENT_TITLE
  if (error.value) return 'Material-Info · eMatChef'
  if (!data.value) return DEFAULT_DOCUMENT_TITLE
  const d = data.value
  const name = d.material?.name?.trim() || 'Material'
  if (routeType.value === 'b' && d.entity_type === 'batch' && d.batch) {
    const serial = String(d.batch.serial_number || d.batch.label || '').trim()
    return serial ? `${serial} · ${name} · eMatChef` : `${name} · eMatChef`
  }
  return `${name} · eMatChef`
})

const pageDescription = computed(() => {
  if (loading.value) {
    return 'Öffentliche Material- und Seriennummern-Informationen in eMatChef.'
  }
  if (error.value) {
    return 'Die angeforderte Material- oder Seriennummer wurde nicht gefunden oder ist nicht aktiv.'
  }
  if (!data.value) {
    return 'Öffentliche Material-Information in eMatChef.'
  }
  const d = data.value
  const mat = d.material.name
  const dept = d.department?.name
  const bit = routeType.value === 'b' ? 'Seriennummer / Charge' : 'Material'
  return `${[mat, dept, bit].filter(Boolean).join(' · ')}. eMatChef.`
})

watch(
  [pageTitle, pageDescription],
  () => {
    pageHeadStore.setDynamic(pageTitle.value, pageDescription.value)
  },
  { immediate: true }
)

/** Department-Einstellung: Kontaktformular-Bereich anzeigen (Standard: an). */
const showPublicContactForm = computed(() => {
  const d = data.value
  if (!d) return false
  return d.public_ui?.show_contact_form !== false
})

/** Ob serverseitig eine Zustell-Adresse existiert (auch wenn E-Mail auf der Seite ausgeblendet ist). */
const canDeliverPublicMessage = computed(() => {
  const d = data.value
  if (!d) return false
  if (d.public_ui?.can_deliver_message !== undefined) {
    return d.public_ui.can_deliver_message
  }
  return !!d.contact?.email
})

const foundForm = ref({
  sender_name: '',
  sender_email: '',
  message: '',
  website: '',
})
const foundFormSubmitting = ref(false)
const foundFormError = ref<string | null>(null)
const foundFormSuccess = ref(false)

/** Aufklapp-Bereich „Materialwart kontaktieren“ (immer sichtbar, Inhalt erst nach Klick). */
const contactExpanded = ref(false)

function resetFoundForm() {
  foundForm.value = { sender_name: '', sender_email: '', message: '', website: '' }
  foundFormError.value = null
  foundFormSuccess.value = false
  contactExpanded.value = false
}

async function submitFoundContact() {
  const d = data.value
  if (!d || !canDeliverPublicMessage.value) return

  foundFormError.value = null
  foundFormSuccess.value = false
  const msg = foundForm.value.message.trim()
  if (msg.length < 5) {
    foundFormError.value = 'Bitte eine etwas ausführlichere Nachricht eingeben.'
    return
  }

  foundFormSubmitting.value = true
  try {
    await submitPublicFoundItemContact({
      entity_type: d.entity_type === 'batch' ? 'batch' : 'material',
      public_code: d.code,
      message: msg,
      sender_name: foundForm.value.sender_name.trim() || undefined,
      sender_email: foundForm.value.sender_email.trim() || undefined,
      website: foundForm.value.website,
    })
    foundFormSuccess.value = true
    foundForm.value.message = ''
    foundForm.value.website = ''
  } catch (e: any) {
    foundFormError.value =
      e?.response?.data?.error || 'Senden fehlgeschlagen. Bitte später erneut versuchen.'
  } finally {
    foundFormSubmitting.value = false
  }
}

function isMwRole(role: string | null | undefined): boolean {
  const normalized = String(role || '').toLowerCase().trim()
  return normalized === 'mw' || normalized === 'matwart'
}

/** Login mit Rücksprung zu dieser öffentlichen Seite (Artikel-Kontext bleibt in der URL). */
function goToLogin() {
  void router.push({ path: '/', query: { redirect: route.fullPath } })
}

/** Bereits angemeldet: ins Material / Dashboard wechseln. */
function goToApp() {
  if (!authStore.isLoggedIn) {
    goToLogin()
    return
  }
  const d = data.value
  if (d?.department?.id && d?.material?.id) {
    const q: Record<string, string> = {}
    if (d.entity_type === 'batch' && d.batch?.id) {
      q.batch = d.batch.id
    }
    void router.push({ path: `/${d.department.id}/materials/${d.material.id}`, query: q })
    return
  }
  const deptId = authStore.activeDepartmentId
  if (deptId) {
    void router.push(`/${deptId}/dashboard`)
    return
  }
  void router.push('/pending-assignment')
}

async function maybeRedirectToInternalDetail(lookupData: PublicLookupViewData): Promise<void> {
  if (!authStore.isLoggedIn) return

  const departmentId = lookupData.department.id
  const membership = authStore.departments.find((d) => d.department_id === departmentId)
  if (!membership || !isMwRole(membership.role)) return

  const materialId = lookupData.material.id
  const query: Record<string, string> = {}
  if (lookupData.entity_type === 'batch' && lookupData.batch?.id) {
    query.batch = lookupData.batch.id
  }

  await router.replace({
    path: `/${departmentId}/materials/${materialId}`,
    query,
  })
}

async function loadData() {
  if (!routeCode.value) {
    error.value = 'Ungültiger Code.'
    return
  }

  loading.value = true
  error.value = null
  resetFoundForm()

  try {
    if (routeType.value === 'b') {
      data.value = await getPublicBatchByCode(routeCode.value)
    } else {
      data.value = await getPublicMaterialByCode(routeCode.value)
    }
    if (data.value) {
      await maybeRedirectToInternalDetail(data.value)
    }
  } catch {
    error.value = 'Code nicht gefunden oder nicht aktiv.'
    data.value = null
  } finally {
    loading.value = false
  }
}

onMounted(loadData)
watch([routeType, routeCode], loadData)
</script>

<style scoped>
.public-header-actions {
  flex-shrink: 0;
}

.public-header-logged-in {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.public-user-chip {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  max-width: min(200px, 42vw);
}

.public-user-avatar {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  font-size: 0.7rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

.public-user-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--public-brand-title);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

@media (max-width: 380px) {
  .public-user-name {
    display: none;
  }
}

.public-login-btn {
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid var(--public-btn-outline-border);
  background: var(--public-btn-outline-bg);
  color: var(--public-btn-outline-fg);
  font: inherit;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.public-login-btn:hover {
  background: var(--public-btn-outline-bg-hover);
  border-color: var(--public-btn-outline-border-hover);
}

.public-login-btn:focus-visible {
  outline: 2px solid var(--public-btn-focus-ring);
  outline-offset: 2px;
}

.public-login-btn--primary {
  background: var(--public-btn-primary-bg);
  color: var(--public-btn-primary-fg);
  border-color: var(--public-btn-primary-border);
}

.public-login-btn--primary:hover {
  background: var(--public-btn-primary-bg-hover);
  border-color: var(--public-btn-primary-border-hover);
}

.public-title {
  margin: 0 0 12px;
  font-size: 1.4rem;
}

.public-code {
  margin: 0 0 6px;
  color: #64748b;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}

.material-name {
  margin: 0 0 8px;
}

.material-desc {
  margin: 0 0 14px;
  color: #334155;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}

.info-grid dt {
  font-size: 0.8rem;
  color: #64748b;
}

.info-grid dd {
  margin: 0;
  font-weight: 600;
}

.contact-box {
  margin-top: 16px;
  padding: 12px;
  border-radius: 10px;
  background: #f1f5f9;
}

/* Kontakt oben im aufgeklappten Panel, optisch mit Formular verbunden */
.contact-box--in-panel {
  margin-top: 0;
  margin-bottom: 0;
  border-radius: 12px 12px 0 0;
  border: 1px solid #e2e8f0;
  border-bottom: none;
}

.contact-panel .contact-box--in-panel + .found-form-box {
  margin-top: 0;
  border-top-left-radius: 0;
  border-top-right-radius: 0;
}

.contact-note {
  margin-top: 8px;
  color: #475569;
  white-space: pre-wrap;
}

.contact-collapsible {
  margin-top: 16px;
}

.contact-toggle {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid #cbd5e1;
  border-radius: 12px;
  background: #fff;
  font: inherit;
  font-weight: 600;
  color: #0f172a;
  cursor: pointer;
  text-align: left;
  transition: background 0.15s ease, border-color 0.15s ease;
}

.contact-toggle:hover {
  background: #f8fafc;
  border-color: #94a3b8;
}

.contact-toggle:focus-visible {
  outline: 2px solid #3b82f6;
  outline-offset: 2px;
}

.contact-toggle-label {
  flex: 1;
}

.contact-toggle-chevron {
  display: flex;
  color: #64748b;
  transition: transform 0.2s ease;
}

.contact-toggle-chevron.is-open {
  transform: rotate(180deg);
}

.contact-panel {
  margin-top: 10px;
  border-radius: 12px;
  overflow: hidden;
}

.found-form-unavailable .contact-note {
  margin-top: 12px;
}

.muted {
  color: #64748b;
}

.error {
  color: #b91c1c;
}

.found-form-box {
  margin-top: 0;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  background: #fafafa;
}

.found-form-title {
  margin: 0 0 8px;
  font-size: 1.1rem;
}

.found-form-hint {
  margin: 0 0 12px;
  color: #475569;
  font-size: 0.95rem;
}

.found-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.found-label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 0.9rem;
  color: #334155;
}

.found-label input,
.found-label textarea {
  padding: 10px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font: inherit;
}

.found-label textarea {
  resize: vertical;
  min-height: 100px;
}

.found-label .optional {
  font-weight: 400;
  color: #64748b;
  font-size: 0.85rem;
}

.found-label .req {
  color: #b91c1c;
}

.found-label.hp {
  position: absolute;
  left: -9999px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}

.found-submit {
  align-self: flex-start;
  padding: 10px 18px;
  border-radius: 8px;
  border: none;
  background: #0f172a;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
}

.found-submit:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.found-form-msg.success {
  color: #15803d;
  margin: 0;
}

.found-form-msg {
  margin: 0;
  font-size: 0.9rem;
}
</style>

