<template>
  <div class="ga-live-page">
    <p class="intro">{{ t('grossanlass.planung.struktur.intro') }}</p>
    <ELoadingState v-if="loading" variant="list" :message="t('common.loading')" />
    <p v-else-if="error" class="warn">{{ error }}</p>
    <template v-else-if="!hasGuestDepartments">
      <EEmptyState
        :title="t('grossanlass.planung.struktur.guestsOffTitle')"
        :description="t('grossanlass.planung.struktur.guestsOffText')"
      >
        <template #actions>
          <router-link :to="`/${departmentId}/einstellungen/stammdaten`">
            {{ t('grossanlass.planung.struktur.openStammdaten') }}
          </router-link>
        </template>
      </EEmptyState>
    </template>
    <template v-else>
      <v-expansion-panels v-model="openSetup" multiple class="ga-setup-accordions">
        <v-expansion-panel value="invite">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.struktur.inviteTitle') }}
            <span v-if="canAddGuests" class="setup-badge is-done">{{ t('grossanlass.planung.struktur.stepDone') }}</span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
        <p class="hint">{{ t('grossanlass.planung.struktur.inviteHint') }}</p>
        <ul v-if="hierarchicalRessorts.length" class="invite-list">
          <li
            v-for="row in hierarchicalRessorts"
            :key="row.id"
            class="invite-row"
            :style="{ paddingLeft: `${row._level * 20}px` }"
          >
            <label>
              <input
                type="checkbox"
                :checked="inviteSet.has(row.id)"
                :disabled="!pack?.can_manage || saving"
                @change="toggleInvite(row.id)"
              >
              <span v-if="row._level > 0" class="indent-icon">↳</span>
              <strong>{{ row.name }}</strong>
              <span class="kind-badge">{{ kindLabel(row) }}</span>
            </label>
          </li>
        </ul>
        <p v-else class="hint">
          {{ t('grossanlass.planung.struktur.inviteEmpty') }}
          <router-link :to="`/${departmentId}/einstellungen/ressorts`">
            {{ t('grossanlass.planung.struktur.openRessorts') }}
          </router-link>
        </p>
        <div v-if="pack?.can_manage && wizardStep === 'invite'" class="setup-next">
          <EButton variant="primary" size="small" :disabled="!canAddGuests || saving" @click="goInviteNext">
            {{ t('common.next') }}
          </EButton>
        </div>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel value="modus">
          <v-expansion-panel-title>
            {{ t('grossanlass.planung.struktur.modusTitle') }}
            <span v-if="wizardStep === 'done'" class="setup-badge is-done">{{ t('grossanlass.planung.struktur.stepDone') }}</span>
          </v-expansion-panel-title>
          <v-expansion-panel-text>
        <p class="hint">{{ t('grossanlass.planung.struktur.modusLead') }}</p>
        <div class="modus-grid">
          <button
            v-for="modus in modi"
            :key="modus"
            type="button"
            class="modus-card"
            :class="{ 'is-active': pack?.config.struktur_modus === modus }"
            :disabled="!pack?.can_manage || saving"
            @click="setModus(modus)"
          >
            <strong>{{ t(`grossanlass.planung.struktur.modus${cap(modus)}`) }}</strong>
            <span>{{ t(`grossanlass.planung.struktur.modusHelp${cap(modus)}`) }}</span>
          </button>
        </div>
        <p class="example">{{ t(`grossanlass.planung.struktur.modusExample${cap(pack?.config.struktur_modus || 'offen')}`) }}</p>
        <div v-if="pack?.can_manage && wizardStep === 'modus'" class="setup-next">
          <EButton variant="primary" size="small" :disabled="saving" @click="goModusNext">
            {{ t('common.next') }}
          </EButton>
        </div>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>

      <section v-if="wizardStep === 'done'" class="card">
        <div class="head">
          <h3>{{ t('grossanlass.planung.struktur.participantsTitle') }}</h3>
          <div v-if="pack?.can_manage && canAddGuests" class="head-actions">
            <EButton variant="secondary" size="small" :disabled="saving" @click="openUnterlagerDialog()">
              {{ t('grossanlass.planung.struktur.unterlagerCreate') }}
            </EButton>
            <EButton variant="primary" size="small" :disabled="saving" @click="openAddDeptDialog()">
              {{ t('grossanlass.planung.struktur.addDepartment') }}
            </EButton>
          </div>
        </div>
        <p class="hint">{{ t('grossanlass.planung.struktur.participantsLater') }}</p>
        <p v-if="!canAddGuests" class="hint">{{ t('grossanlass.planung.struktur.inviteNeeded') }}</p>
        <input
          v-if="participants.length || unterlagerRows.length"
          v-model="participantFilter"
          type="search"
          class="search-input list-filter"
          :placeholder="t('grossanlass.planung.struktur.searchPlaceholder')"
        >
        <v-expansion-panels
          v-if="visibleUnterlager.length"
          v-model="openUnterlager"
          multiple
          class="ga-struktur-accordion"
        >
          <v-expansion-panel v-for="site in visibleUnterlager" :key="site.id" :value="site.id">
            <v-expansion-panel-title>
              <span class="group-title" :style="{ paddingLeft: `${site._level * 16}px` }">
                <span class="kind-badge">{{ t('grossanlass.planung.struktur.unterlagerBadge') }}</span>
                <strong>{{ site.name }}</strong>
                <span class="group-count">{{ t('grossanlass.planung.struktur.memberCount', { count: deptsInUnterlager(site.id).length }) }}</span>
              </span>
            </v-expansion-panel-title>
            <v-expansion-panel-text>
              <div v-if="pack?.can_manage" class="guest-actions">
                <EButton variant="secondary" size="small" :disabled="saving" @click="openAddDeptDialog(site.id)">
                  {{ t('grossanlass.planung.struktur.addDepartment') }}
                </EButton>
                <EButton variant="secondary" size="small" :disabled="saving" @click="openUnterlagerDialog(site.id)">
                  {{ t('grossanlass.planung.struktur.unterlagerChild') }}
                </EButton>
                <button type="button" class="remove" :disabled="saving" @click="removeUnterlager(site.id)">
                  {{ t('grossanlass.planung.struktur.unterlagerDelete') }}
                </button>
              </div>
              <ul v-if="deptsInUnterlager(site.id).length" class="child-list">
                <li v-for="row in deptsInUnterlager(site.id)" :key="row.id" class="child-row">
                  <div class="child-head">
                    <strong>{{ row.name }}</strong>
                    <span class="meta">{{ row.organisation_name }}</span>
                    <span class="tag">{{ t(`grossanlass.planung.struktur.status.${row.status}`) }}</span>
                    <ESelect
                      v-if="pack?.can_manage"
                      class="move-select"
                      :model-value="row.unterlager_id || ''"
                      :items="unterlagerSelectItems"
                      hide-details
                      :disabled="saving"
                      @update:model-value="(v) => moveGuest(row.id, String(v ?? ''))"
                    />
                    <button
                      v-if="pack?.can_manage && row.status !== 'accepted'"
                      type="button"
                      class="remove"
                      :disabled="saving"
                      @click="removeGuest(row.id)"
                    >
                      {{ t('common.remove') }}
                    </button>
                  </div>
                </li>
              </ul>
              <p v-else class="hint">{{ t('grossanlass.planung.struktur.unterlagerNoDepts') }}</p>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>
        <section v-if="unassignedDepts.length" class="unassigned">
          <h4>{{ t('grossanlass.planung.struktur.unterlagerUnassigned') }}</h4>
          <ul class="child-list">
            <li v-for="row in unassignedDepts" :key="row.id" class="child-row">
              <div class="child-head">
                <strong>{{ row.name }}</strong>
                <span class="meta">{{ row.organisation_name }}</span>
                <span class="tag">{{ t(`grossanlass.planung.struktur.status.${row.status}`) }}</span>
                <ESelect
                  v-if="pack?.can_manage"
                  class="move-select"
                  :model-value="''"
                  :items="unterlagerSelectItems"
                  hide-details
                  :disabled="saving"
                  @update:model-value="(v) => moveGuest(row.id, String(v ?? ''))"
                />
                <button
                  v-if="pack?.can_manage && row.status !== 'accepted'"
                  type="button"
                  class="remove"
                  :disabled="saving"
                  @click="removeGuest(row.id)"
                >
                  {{ t('common.remove') }}
                </button>
              </div>
            </li>
          </ul>
        </section>
        <p v-else-if="!unterlagerRows.length && !participants.length" class="hint">
          {{ t('grossanlass.planung.struktur.participantsEmpty') }}
        </p>
      </section>

      <EDialog v-model="unterlagerDialog" :title="t('grossanlass.planung.struktur.unterlagerCreate')" :max-width="420">
        <ETextField v-model="unterlagerName" :label="t('grossanlass.planung.struktur.unterlagerName')" hide-details />
        <ESelect
          v-model="unterlagerParentId"
          class="dialog-field"
          :label="t('grossanlass.planung.struktur.unterlagerParent')"
          :items="unterlagerParentItems"
          hide-details
        />
        <template #actions>
          <EButton variant="secondary" @click="unterlagerDialog = false">{{ t('common.cancel') }}</EButton>
          <EButton variant="primary" :disabled="!unterlagerName.trim() || saving" @click="saveUnterlager">
            {{ t('common.create') }}
          </EButton>
        </template>
      </EDialog>

      <EDialog v-model="addDeptDialog" :title="t('grossanlass.planung.struktur.addDepartment')" :max-width="460">
        <ESelect
          v-model="addDeptUnterlagerId"
          :label="t('grossanlass.planung.struktur.assignTo')"
          :items="unterlagerSelectItems"
          hide-details
        />
        <ESearchField
          v-model="searchQuery"
          class="dialog-field"
          :label="t('grossanlass.planung.struktur.searchLabel')"
          hide-details
          @update:model-value="onSearchInput"
        />
        <ul v-if="hits.length" class="hits dialog-hits">
          <li v-for="hit in hits" :key="hit.id">
            <button type="button" :disabled="saving" @click="addGuest(hit.id)">
              <strong>{{ hit.parent_id ? '↳ ' : '' }}{{ hit.name }}</strong>
              <span class="meta">{{ hit.organisation_name }}</span>
            </button>
          </li>
        </ul>
        <p v-else-if="searchQuery.trim().length >= 2 && !searching" class="hint">
          {{ t('grossanlass.planung.struktur.searchEmpty') }}
        </p>
        <template #actions>
          <EButton variant="secondary" @click="addDeptDialog = false">{{ t('common.cancel') }}</EButton>
        </template>
      </EDialog>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton, EDialog, ESearchField, ESelect, ETextField } from '@/components/form/base'
import {
  addGrossanlassParticipant,
  createGrossanlassUnterlager,
  getGrossanlassPlanung,
  removeGrossanlassParticipant,
  removeGrossanlassUnterlager,
  searchGrossanlassGuests,
  updateGrossanlassParticipant,
  updateGrossanlassPlanung,
  type GrossanlassGuestSearchHit,
  type GrossanlassParticipant,
  type GrossanlassPlanungOverview,
  type GrossanlassPlanungRessort,
  type GrossanlassStrukturModus,
} from '@/api/grossanlassPlanung'
import { flattenTreeWithLevel } from '@/utils/grossanlassGroupHierarchy'

defineOptions({ name: 'GrossanlassPlanungStruktur' })

const route = useRoute()
const authStore = useAuthStore()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)
const modi: GrossanlassStrukturModus[] = ['offen', 'verschachtelt', 'parallel']
const pack = ref<GrossanlassPlanungOverview | null>(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const searchQuery = ref('')
const hits = ref<GrossanlassGuestSearchHit[]>([])
const searching = ref(false)
let searchTimer: ReturnType<typeof setTimeout> | null = null
const participantFilter = ref('')
const wizardStep = ref<'invite' | 'modus' | 'done'>('invite')
const openSetup = ref<string[]>(['invite'])
const openUnterlager = ref<string[]>([])
const unterlagerDialog = ref(false)
const unterlagerName = ref('')
const unterlagerParentId = ref('')
const addDeptDialog = ref(false)
const addDeptUnterlagerId = ref('')

const hasGuestDepartments = computed(
  () => pack.value?.config.has_guest_departments === true
    || (pack.value?.participants?.length ?? 0) > 0,
)
const inviteSet = computed(() => new Set(pack.value?.config.invite_group_ids ?? []))
const canAddGuests = computed(() => inviteSet.value.size > 0)

function applyWizardFromData() {
  if ((pack.value?.config.invite_group_ids ?? []).length > 0) {
    wizardStep.value = 'done'
    openSetup.value = []
    return
  }
  wizardStep.value = 'invite'
  openSetup.value = ['invite']
}

function goInviteNext() {
  if (!canAddGuests.value) return
  wizardStep.value = 'modus'
  openSetup.value = ['modus']
}

function goModusNext() {
  wizardStep.value = 'done'
  openSetup.value = []
}

const hierarchicalRessorts = computed(() =>
  flattenTreeWithLevel(
    (pack.value?.ressorts ?? []).map((row) => ({
      ...row,
      parent_id: row.parent_id ?? null,
    })),
  ),
)

const unterlagerRows = computed(() => pack.value?.unterlager ?? [])
const participants = computed(() => pack.value?.participants ?? [])

const unterlagerTree = computed(() =>
  flattenTreeWithLevel(
    unterlagerRows.value.map((row) => ({
      ...row,
      parent_id: row.parent_id ?? null,
    })),
  ),
)

const visibleUnterlager = computed(() => {
  const q = participantFilter.value.trim().toLowerCase()
  if (!q) return unterlagerTree.value
  return unterlagerTree.value.filter((site) => {
    if (site.name.toLowerCase().includes(q)) return true
    return deptsInUnterlager(site.id).some(
      (row) => row.name.toLowerCase().includes(q) || row.organisation_name.toLowerCase().includes(q),
    )
  })
})

const unassignedDepts = computed(() => {
  const q = participantFilter.value.trim().toLowerCase()
  return participants.value.filter((row) => {
    if (row.unterlager_id) return false
    if (!q) return true
    return row.name.toLowerCase().includes(q) || row.organisation_name.toLowerCase().includes(q)
  })
})

const unterlagerSelectItems = computed(() => [
  { title: t('grossanlass.planung.struktur.unterlagerNone'), value: '' },
  ...unterlagerTree.value.map((row) => ({
    title: `${'— '.repeat(row._level)}${row.name}`,
    value: row.id,
  })),
])

const unterlagerParentItems = computed(() => unterlagerSelectItems.value)

function deptsInUnterlager(unterlagerId: string): GrossanlassParticipant[] {
  const q = participantFilter.value.trim().toLowerCase()
  return participants.value.filter((row) => {
    if (row.unterlager_id !== unterlagerId) return false
    if (!q) return true
    return row.name.toLowerCase().includes(q) || row.organisation_name.toLowerCase().includes(q)
  })
}

function kindLabel(row: GrossanlassPlanungRessort): string {
  if (row.node_type === 'bauprojekt') return String(t('grossanlass.planung.ressorts.kindBauprojekt'))
  if (row.node_type === 'unterressort') return String(t('grossanlass.planung.ressorts.kindUnterressort'))
  return String(t('grossanlass.planung.ressorts.kindRessort'))
}

async function toggleInvite(groupId: string) {
  if (!departmentId.value || !pack.value?.can_manage) return
  const next = new Set(inviteSet.value)
  if (next.has(groupId)) next.delete(groupId)
  else next.add(groupId)
  saving.value = true
  try {
    pack.value = await updateGrossanlassPlanung(departmentId.value, { invite_group_ids: [...next] })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

function cap(modus: string): string {
  return modus.charAt(0).toUpperCase() + modus.slice(1)
}

async function load() {
  if (!departmentId.value) return
  loading.value = true
  error.value = ''
  try {
    pack.value = await getGrossanlassPlanung(departmentId.value)
    applyWizardFromData()
    openUnterlager.value = (pack.value.unterlager ?? []).map((row) => row.id)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.beschaffung.anfragen.loadError')
  } finally {
    loading.value = false
  }
}

function onSearchInput(value?: string) {
  if (searchTimer) clearTimeout(searchTimer)
  const q = (value ?? searchQuery.value).trim()
  if (q.length < 2) {
    hits.value = []
    return
  }
  searchTimer = setTimeout(() => {
    void runSearch(q)
  }, 280)
}

async function runSearch(q: string) {
  if (!departmentId.value) return
  searching.value = true
  try {
    hits.value = await searchGrossanlassGuests(departmentId.value, q)
  } catch {
    hits.value = []
  } finally {
    searching.value = false
  }
}

async function addGuest(guestId: string) {
  if (!departmentId.value) return
  saving.value = true
  try {
    pack.value = await addGrossanlassParticipant(
      departmentId.value,
      guestId,
      addDeptUnterlagerId.value || null,
    )
    searchQuery.value = ''
    hits.value = []
    addDeptDialog.value = false
    if (addDeptUnterlagerId.value) {
      openUnterlager.value = [...new Set([...openUnterlager.value, addDeptUnterlagerId.value])]
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

async function moveGuest(participantId: string, unterlagerId: string) {
  if (!departmentId.value) return
  saving.value = true
  try {
    pack.value = await updateGrossanlassParticipant(departmentId.value, participantId, {
      unterlager_id: unterlagerId || null,
    })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

function openUnterlagerDialog(parentId?: string) {
  unterlagerName.value = ''
  unterlagerParentId.value = parentId ?? ''
  unterlagerDialog.value = true
}

function openAddDeptDialog(unterlagerId?: string) {
  searchQuery.value = ''
  hits.value = []
  addDeptUnterlagerId.value = unterlagerId ?? ''
  addDeptDialog.value = true
}

async function saveUnterlager() {
  if (!departmentId.value || !unterlagerName.value.trim()) return
  saving.value = true
  try {
    pack.value = await createGrossanlassUnterlager(departmentId.value, {
      name: unterlagerName.value.trim(),
      parent_id: unterlagerParentId.value || null,
    })
    unterlagerDialog.value = false
    openUnterlager.value = (pack.value.unterlager ?? []).map((row) => row.id)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

async function removeUnterlager(unterlagerId: string) {
  if (!departmentId.value) return
  saving.value = true
  try {
    pack.value = await removeGrossanlassUnterlager(departmentId.value, unterlagerId)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

async function removeGuest(participantId: string) {
  if (!departmentId.value) return
  saving.value = true
  try {
    pack.value = await removeGrossanlassParticipant(departmentId.value, participantId)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

async function setModus(modus: GrossanlassStrukturModus) {
  if (!departmentId.value || pack.value?.config.struktur_modus === modus) return
  saving.value = true
  try {
    pack.value = await updateGrossanlassPlanung(departmentId.value, { struktur_modus: modus })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.ga-live-page { padding: 4px 0 24px; }
.intro, .hint { margin: 0 0 10px; color: #64748b; font-size: 0.9rem; }
.warn { color: #9a3412; }
.card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 14px;
}
.card h3 { margin: 0 0 10px; font-size: 0.95rem; }
.ga-setup-accordions { margin-bottom: 14px; }
.ga-setup-accordions :deep(.v-expansion-panel) {
  border: 1px solid #e5e7eb;
  border-radius: 12px !important;
  margin-bottom: 8px;
}
.ga-setup-accordions :deep(.v-expansion-panel-title) {
  font-weight: 600;
  font-size: 0.95rem;
}
.setup-badge {
  margin-left: 10px;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
}
.setup-badge.is-done {
  background: #dcfce7;
  color: #166534;
}
.setup-next {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}
.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; }
.head h3 { margin: 0; }
.head-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.head a { font-size: 0.85rem; color: #166534; }
.modus-grid { display: grid; gap: 10px; }
@media (min-width: 720px) {
  .modus-grid { grid-template-columns: 1fr 1fr 1fr; }
}
.modus-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  text-align: left;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  background: #f8fafc;
  cursor: pointer;
  color: #334155;
}
.modus-card strong { font-size: 0.95rem; }
.modus-card span { font-size: 0.8rem; line-height: 1.35; color: #64748b; }
.modus-card:disabled { cursor: default; }
.modus-card.is-active {
  border-color: #86efac;
  background: #ecfdf5;
}
.modus-card.is-active strong { color: #166534; }
.example {
  margin: 12px 0 0;
  font-size: 0.82rem;
  color: #334155;
  background: #f8fafc;
  border-radius: 8px;
  padding: 10px 12px;
}
.invite-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
.invite-row label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem; }
.list-filter { margin: 8px 0 12px; }
.guest-actions { margin-bottom: 8px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.dialog-field { margin-top: 12px; }
.dialog-hits { max-width: none; }
.move-select { min-width: 180px; max-width: 240px; }
.unassigned { margin-top: 16px; }
.unassigned h4 { margin: 0 0 8px; font-size: 0.88rem; color: #475569; }
.hint a { color: #166534; }
.search { margin: 10px 0 12px; }
.search-input {
  width: 100%;
  max-width: 420px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 0.9rem;
}
.hits { list-style: none; margin: 8px 0 0; padding: 0; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; max-width: 420px; }
.hits button {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 8px 10px;
  background: #fff;
  border: 0;
  border-bottom: 1px solid #f1f5f9;
  cursor: pointer;
  text-align: left;
}
.hits button:hover { background: #f8fafc; }
.remove {
  margin-left: auto;
  border: 0;
  background: transparent;
  color: #9a3412;
  cursor: pointer;
  font-size: 0.8rem;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}
.meta { color: #64748b; font-size: 0.8rem; }
.tag {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  background: #ecfdf5;
  color: #166534;
}
.ga-struktur-accordion { margin-top: 4px; }
.ga-struktur-accordion :deep(.v-expansion-panel) {
  border: 1px solid #e5e7eb;
  border-radius: 10px !important;
  margin-bottom: 8px;
}
.ga-struktur-accordion :deep(.v-expansion-panel-title) {
  min-height: 48px;
  font-size: 0.9rem;
}
.group-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.group-count { color: #64748b; font-size: 0.8rem; }
.kind-badge {
  font-size: 0.7rem;
  font-weight: 600;
  padding: 2px 7px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #475569;
}
.member-list, .child-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 6px;
}
.member-list { margin-bottom: 10px; font-size: 0.88rem; }
.member-list.is-nested { margin: 6px 0 0; padding-left: 22px; color: #475569; }
.child-row { padding: 8px 0; border-top: 1px solid #f1f5f9; }
.child-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.indent-icon { color: #94a3b8; font-size: 0.85rem; }
</style>
