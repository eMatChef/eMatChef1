<template>
  <div class="accounting-subpage cost-centers-page">
    <p class="description" style="margin-bottom: 20px">
      Kostenstellen bündeln Ausgaben für Budget und Auswertung. Optional: <strong>Kontocode</strong> für den Abgleich mit dem
      Vereins-Finanztool (keine Doppelbuch).
    </p>

    <div class="page-toolbar">
      <button type="button" class="btn btn-primary" :disabled="isLoading" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19" />
          <line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Neue Kostenstelle
      </button>
    </div>

    <div v-if="isLoading" class="loading-inline">Laden…</div>
    <div v-else-if="loadError" class="error-inline">{{ loadError }}</div>

    <div v-else-if="items.length === 0" class="empty-hint empty-hint--cc">
      <p>Noch keine Kostenstellen. Lege die erste an oder übernimm die Standard-Vorschläge für typische Vereinskosten.</p>
      <div class="empty-hint-actions">
        <button type="button" class="btn btn-primary" :disabled="isLoading" @click="openCreate">
          Kostenstelle anlegen
        </button>
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="isLoading || isApplyingStandardSeeds"
          @click="createStandardCostCenters"
        >
          {{ isApplyingStandardSeeds ? 'Wird angelegt…' : 'Standard-Vorschläge übernehmen' }}
        </button>
      </div>
    </div>

    <div v-else class="cost-centers-table-wrap">
      <table class="cost-centers-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Kontocode</th>
            <th>Sortierung</th>
            <th class="col-actions">Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in items" :key="row.id">
            <td>
              <strong>{{ row.name }}</strong>
              <div v-if="row.description" class="muted" style="font-size: 13px; margin-top: 4px">{{ row.description }}</div>
            </td>
            <td>{{ row.account_code || '–' }}</td>
            <td>{{ row.sort_order }}</td>
            <td class="col-actions">
              <button type="button" class="acc-icon-btn" title="Bearbeiten" @click="openEdit(row)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                </svg>
              </button>
              <button type="button" class="acc-icon-btn danger" title="Löschen" @click="onDelete(row)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="3 6 5 6 21 6" />
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="modalOpen" class="acc-modal-backdrop" @click.self="closeModal">
        <div class="acc-modal" role="dialog" aria-modal="true">
          <div class="acc-modal-header">
            <h2>{{ editingId ? 'Kostenstelle bearbeiten' : 'Neue Kostenstelle' }}</h2>
            <button type="button" class="acc-icon-btn" aria-label="Schließen" @click="closeModal">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18" />
                <line x1="6" y1="6" x2="18" y2="18" />
              </svg>
            </button>
          </div>
          <div class="acc-modal-body">
            <div class="acc-field">
              <label for="cc-name">Name *</label>
              <input id="cc-name" v-model="form.name" type="text" maxlength="255" placeholder="z. B. Zeltpflege, Transport" />
            </div>
            <div class="acc-field">
              <label for="cc-code">Kontocode (optional)</label>
              <input id="cc-code" v-model="form.account_code" type="text" maxlength="32" placeholder="z. B. 6200" />
            </div>
            <div class="acc-field">
              <label for="cc-sort">Sortierung</label>
              <input id="cc-sort" v-model.number="form.sort_order" type="number" />
            </div>
            <div class="acc-field">
              <label for="cc-desc">Beschreibung (optional)</label>
              <textarea id="cc-desc" v-model="form.description" placeholder="Kurznotiz" />
            </div>
            <div class="acc-modal-actions">
              <button type="button" class="btn btn-secondary" @click="closeModal">Abbrechen</button>
              <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
                {{ saving ? 'Speichern…' : 'Speichern' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  listCostCenters,
  createCostCenter,
  updateCostCenter,
  deleteCostCenter,
  type AccountingCostCenter
} from '@/api/accountingCostCenters'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()

const departmentId = computed(() => String(route.params.departmentId ?? ''))

const items = ref<AccountingCostCenter[]>([])
const isLoading = ref(true)
const loadError = ref('')
const modalOpen = ref(false)
const editingId = ref<string | null>(null)
const saving = ref(false)
/** Standard-Vorschläge-Button (expliziter Name für Template/HMR mit KeepAlive). */
const isApplyingStandardSeeds = ref(false)

const STANDARD_COST_CENTER_SEEDS: ReadonlyArray<{ name: string; description: string | null; sort_order: number }> = [
  { name: 'Material & Ausstattung', description: 'Anschaffungen aus der Lagerverwaltung', sort_order: 10 },
  { name: 'Allgemeiner Bedarf', description: 'Sonstige laufende Kosten', sort_order: 20 },
  { name: 'Events / Verpflegung', description: 'Anlässe, Bewirtung', sort_order: 30 },
]

const form = reactive({
  name: '',
  account_code: '' as string,
  description: '' as string,
  sort_order: 0
})

function resetForm() {
  form.name = ''
  form.account_code = ''
  form.description = ''
  form.sort_order = 0
  editingId.value = null
}

async function load() {
  isLoading.value = true
  loadError.value = ''
  try {
    items.value = await listCostCenters(departmentId.value)
  } catch (e: unknown) {
    const msg = e && typeof e === 'object' && 'response' in e ? (e as { response?: { data?: { error?: string } } }).response?.data?.error : null
    loadError.value = msg || 'Kostenstellen konnten nicht geladen werden.'
    items.value = []
  } finally {
    isLoading.value = false
  }
}

async function createStandardCostCenters() {
  if (items.value.length > 0 || !departmentId.value) return
  isApplyingStandardSeeds.value = true
  try {
    for (const row of STANDARD_COST_CENTER_SEEDS) {
      await createCostCenter(departmentId.value, {
        name: row.name,
        description: row.description,
        sort_order: row.sort_order,
      })
    }
    toast.success('Standard-Kostenstellen angelegt.')
    await load()
  } catch {
    toast.error('Standard-Vorschläge konnten nicht vollständig angelegt werden.')
    await load()
  } finally {
    isApplyingStandardSeeds.value = false
  }
}

onMounted(async () => {
  await load()
  if (String(route.query.openCreate) === '1') {
    await nextTick()
    openCreate()
    await router.replace({
      name: 'AccountingCostCenters',
      params: { departmentId: departmentId.value },
      query: {},
    })
  }
})

function openCreate() {
  resetForm()
  modalOpen.value = true
}

function openEdit(row: AccountingCostCenter) {
  editingId.value = row.id
  form.name = row.name
  form.account_code = row.account_code || ''
  form.description = row.description || ''
  form.sort_order = row.sort_order
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
}

async function save() {
  const name = form.name.trim()
  if (!name) {
    toast.error('Bitte einen Namen eingeben.')
    return
  }
  saving.value = true
  try {
    const payload = {
      name,
      account_code: form.account_code.trim() || null,
      description: form.description.trim() || null,
      sort_order: Number.isFinite(form.sort_order) ? form.sort_order : 0
    }
    if (editingId.value) {
      await updateCostCenter(departmentId.value, editingId.value, payload)
      toast.success('Kostenstelle gespeichert.')
    } else {
      await createCostCenter(departmentId.value, payload)
      toast.success('Kostenstelle angelegt.')
    }
    closeModal()
    await load()
  } catch {
    toast.error('Speichern fehlgeschlagen.')
  } finally {
    saving.value = false
  }
}

async function onDelete(row: AccountingCostCenter) {
  const ok = await confirmDialog({
    title: 'Kostenstelle löschen?',
    message: `«${row.name}» wirklich löschen?`,
    confirmText: 'Löschen',
    variant: 'danger'
  })
  if (!ok) return
  try {
    await deleteCostCenter(departmentId.value, row.id)
    toast.success('Gelöscht.')
    await load()
  } catch {
    toast.error('Löschen fehlgeschlagen.')
  }
}
</script>

<style scoped>
@import '@/styles/accounting-view.css';

.loading-inline,
.error-inline,
.empty-hint {
  padding: 16px;
  border-radius: 8px;
  background: #f9fafb;
  color: #6b7280;
}

.error-inline {
  background: #fef2f2;
  color: #b91c1c;
}

.empty-hint--cc p {
  margin: 0 0 14px;
  max-width: 52ch;
  line-height: 1.5;
}

.empty-hint-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}
</style>
