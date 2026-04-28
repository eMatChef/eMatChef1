<template>
  <div class="dept-page notifications-center-view">
    <div class="page-header header-content">
      <div class="header-left">
        <h1>{{ t('notificationsCenter.title') }}</h1>
        <span class="subtitle">
          {{ t('notificationsCenter.subtitle') }}
        </span>
      </div>
    </div>

    <div v-if="isLoading" class="loading-state">
      <div class="spinner"></div>
      <p>{{ t('notificationsCenter.loading') }}</p>
    </div>
    <template v-else>
      <section class="nc-section">
        <div class="nc-section-head">
          <h2 class="nc-section-title">{{ t('notificationsCenter.qrSectionTitle') }}</h2>
          <div class="nc-found-tabs" role="tablist">
            <button
              type="button"
              role="tab"
              :aria-selected="foundTab === 'active'"
              class="nc-tab"
              :class="{ active: foundTab === 'active' }"
              @click="setFoundTab('active')"
            >
              {{ t('notificationsCenter.tabOpenInProgress') }}
            </button>
            <button
              type="button"
              role="tab"
              :aria-selected="foundTab === 'done'"
              class="nc-tab"
              :class="{ active: foundTab === 'done' }"
              @click="setFoundTab('done')"
            >
              {{ t('notificationsCenter.tabDone') }}
            </button>
          </div>
        </div>
        <p v-if="foundTab === 'active'" class="nc-hint">
          {{ t('notificationsCenter.replyHint') }}
        </p>
        <div v-if="foundItems.length > 0" class="notifications-table-wrapper">
          <table class="notifications-table">
            <thead>
              <tr>
                <th>{{ t('notificationsCenter.tableMaterial') }}</th>
                <th>{{ t('notificationsCenter.tableMessage') }}</th>
                <th>{{ t('notificationsCenter.tableSender') }}</th>
                <th>{{ t('notificationsCenter.tableStatus') }}</th>
                <th>{{ t('notificationsCenter.tableDate') }}</th>
                <th>{{ t('notificationsCenter.tableAction') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="msg in foundItems"
                :id="'nc-found-' + msg.id"
                :key="msg.id"
                :class="{ 'nc-row-flash': flashRowId === msg.id }"
              >
                <td>{{ msg.material_name }}</td>
                <td class="nc-msg-cell">{{ msg.message }}</td>
                <td>
                  <span v-if="msg.sender_name">{{ msg.sender_name }}</span>
                  <span v-if="msg.sender_email"><br /><span class="nc-email">{{ msg.sender_email }}</span></span>
                  <span v-if="!msg.sender_name && !msg.sender_email">–</span>
                </td>
                <td>
                  <select
                    class="nc-status-select"
                    :value="msg.status"
                    @change="onFoundStatusSelect(msg, $event)"
                  >
                    <option value="open">{{ t('notificationsCenter.statusOpen') }}</option>
                    <option value="in_progress">{{ t('notificationsCenter.statusInProgress') }}</option>
                    <option value="done">{{ t('notificationsCenter.statusDone') }}</option>
                  </select>
                </td>
                <td>{{ formatDate(msg.created_at) }}</td>
                <td class="notifications-actions-cell">
                  <button type="button" class="btn-outline btn-xs" @click="openFoundMaterial(msg)">{{ t('notificationsCenter.openMaterial') }}</button>
                  <button
                    v-if="msg.sender_email && String(msg.sender_email).trim()"
                    type="button"
                    class="btn-outline btn-xs nc-reply-link"
                    :title="t('notificationsCenter.replyTitle')"
                    @click="openPublicFoundReplyMailto(msg)"
                  >
                    {{ t('notificationsCenter.reply') }}
                  </button>
                  <button
                    v-if="msg.status !== 'done'"
                    type="button"
                    class="btn-success btn-xs"
                    @click="markFoundDone(msg)"
                  >
                    {{ t('notificationsCenter.markDone') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="nc-empty-found">
          <p v-if="foundTab === 'active'">{{ t('notificationsCenter.emptyActive') }}</p>
          <p v-else>{{ t('notificationsCenter.emptyDone') }}</p>
        </div>
      </section>

      <section v-if="inviteItems.length > 0" class="nc-section">
        <h2 class="nc-section-title">{{ t('notificationsCenter.invitesSectionTitle') }}</h2>
        <div class="notifications-table-wrapper">
          <table class="notifications-table">
            <thead>
              <tr>
                <th>{{ t('notificationsCenter.tableDepartment') }}</th>
                <th>{{ t('notificationsCenter.tableActivity') }}</th>
                <th>{{ t('notificationsCenter.tableType') }}</th>
                <th>{{ t('notificationsCenter.tableAction') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="invite in inviteItems"
                :key="`${invite.activity_id}-${invite.source_department_id}`"
              >
                <td>{{ invite.source_department_name }}</td>
                <td>{{ invite.activity_name }}</td>
                <td>{{ invite.activity_type === 'camp' ? t('notificationsCenter.typeCamp') : t('notificationsCenter.typeEvent') }}</td>
                <td class="notifications-actions-cell">
                  <button type="button" class="btn-success btn-xs" @click="decide(invite, 'accepted')">
                    {{ t('notificationsCenter.accept') }}
                  </button>
                  <button type="button" class="btn-danger-outline btn-xs" @click="decide(invite, 'rejected')">
                    {{ t('notificationsCenter.reject') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <div v-if="inviteItems.length === 0 && allFoundMessages.length === 0" class="empty-state">
        <h3>{{ t('notificationsCenter.emptyTitle') }}</h3>
        <p>{{ t('notificationsCenter.emptyBody') }}</p>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import {
  getPendingDepartmentActivityInvites,
  decideDepartmentActivityInvite,
  type PendingDepartmentActivityInvite,
} from '@/api/joinRequests'
import {
  getPublicFoundMessages,
  updatePublicFoundMessageStatus,
  openPublicFoundReplyMailto,
  type PublicFoundItemMessage,
  type PublicFoundMessageStatus,
} from '@/api/publicFoundMessages'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { t } = useI18n()
/** true bis erste Ladung fertig (verhindert frühes Entfernen von ?highlight=) */
const isLoading = ref(true)
const inviteItems = ref<PendingDepartmentActivityInvite[]>([])
const allFoundMessages = ref<PublicFoundItemMessage[]>([])
const foundTab = ref<'active' | 'done'>('active')
/** Kurz hervorheben nach Sprung von der Glocke (?highlight=) */
const flashRowId = ref('')

const departmentId = computed(() => String(route.params.departmentId || ''))

const foundItems = computed(() => {
  if (foundTab.value === 'active') {
    return allFoundMessages.value.filter((m) => m.status === 'open' || m.status === 'in_progress')
  }
  return allFoundMessages.value.filter((m) => m.status === 'done')
})

function formatDate(iso: string): string {
  try {
    const d = new Date(iso)
    return d.toLocaleString('de-CH', { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return iso
  }
}

function setFoundTab(tab: 'active' | 'done') {
  foundTab.value = tab
}

async function load() {
  if (!departmentId.value) {
    isLoading.value = false
    return
  }
  isLoading.value = true
  try {
    const [inv, found] = await Promise.all([
      getPendingDepartmentActivityInvites(departmentId.value).catch(() => ({ count: 0, items: [] })),
      getPublicFoundMessages(departmentId.value, { bucket: 'all', limit: 200 }).catch(() => ({
        items: [] as PublicFoundItemMessage[],
      })),
    ])
    inviteItems.value = inv.items || []
    allFoundMessages.value = found.items || []
  } catch {
    inviteItems.value = []
    allFoundMessages.value = []
    toast.error(t('notificationsCenter.toastLoadFailed'))
  } finally {
    isLoading.value = false
  }
}

async function decide(invite: PendingDepartmentActivityInvite, decision: 'accepted' | 'rejected') {
  if (!departmentId.value) return
  try {
    await decideDepartmentActivityInvite({
      activityId: invite.activity_id,
      departmentId: departmentId.value,
      decision,
    })
    inviteItems.value = inviteItems.value.filter(
      (e) => !(e.activity_id === invite.activity_id && e.source_department_id === invite.source_department_id)
    )
    toast.success(decision === 'accepted' ? t('notificationsCenter.toastInviteAccepted') : t('notificationsCenter.toastInviteRejected'))
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('notificationsCenter.toastDecisionFailed'))
  }
}

function openFoundMaterial(msg: PublicFoundItemMessage) {
  if (!departmentId.value || !msg.material_id) return
  const q: Record<string, string> = {}
  if (msg.batch_id) q.batch = msg.batch_id
  void router.push({
    path: `/${departmentId.value}/materials/${msg.material_id}`,
    query: Object.keys(q).length ? q : undefined,
  })
}

function onFoundStatusSelect(msg: PublicFoundItemMessage, ev: Event) {
  const el = ev.target as HTMLSelectElement
  const status = el.value as PublicFoundMessageStatus
  void onFoundStatusChange(msg, status)
}

async function onFoundStatusChange(msg: PublicFoundItemMessage, status: PublicFoundMessageStatus) {
  if (!departmentId.value || msg.status === status) return
  try {
    const { item } = await updatePublicFoundMessageStatus(departmentId.value, msg.id, status)
    const i = allFoundMessages.value.findIndex((m) => m.id === msg.id)
    if (i >= 0) allFoundMessages.value[i] = item
    toast.success(t('notificationsCenter.toastStatusSaved'))
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('notificationsCenter.toastSaveFailed'))
    await load()
  }
}

async function markFoundDone(msg: PublicFoundItemMessage) {
  await onFoundStatusChange(msg, 'done')
}

onMounted(load)
watch(departmentId, () => load())

function parseHighlightId(raw: unknown): string {
  if (Array.isArray(raw)) return String(raw[0] ?? '').trim()
  if (typeof raw === 'string') return raw.trim()
  return ''
}

watch(
  () => [route.query.highlight, isLoading.value, allFoundMessages.value] as const,
  async ([hl, loading, msgs]) => {
    if (loading) return
    const hid = parseHighlightId(hl)
    if (!hid) return
    const msg = msgs.find((m) => m.id === hid)
    if (!msg) {
      if (loading) return
      const q = { ...route.query }
      if (q.highlight !== undefined) {
        delete q.highlight
        void router.replace({ path: route.path, query: q })
      }
      return
    }
    if (msg.status === 'done') foundTab.value = 'done'
    else foundTab.value = 'active'
    await nextTick()
    await nextTick()
    const el = document.getElementById(`nc-found-${hid}`)
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    flashRowId.value = hid
    window.setTimeout(() => {
      flashRowId.value = ''
    }, 2200)
    const q = { ...route.query }
    delete q.highlight
    void router.replace({ path: route.path, query: q })
  },
  { flush: 'post' }
)
</script>

<style scoped>
.notifications-actions-cell {
  white-space: nowrap;
}
.notifications-actions-cell .btn-xs + .btn-xs {
  margin-left: 8px;
}
.nc-section {
  margin-bottom: 2rem;
}
.nc-section-title {
  font-size: 1.1rem;
  font-weight: 600;
  margin: 0 0 12px;
  color: #374151;
}
.nc-msg-cell {
  max-width: 320px;
  white-space: pre-wrap;
  word-break: break-word;
}
.nc-section-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}
.nc-found-tabs {
  display: flex;
  gap: 4px;
}
.nc-tab {
  border: 1px solid #d1d5db;
  background: #fff;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 0.85rem;
  cursor: pointer;
  color: #4b5563;
}
.nc-tab.active {
  background: #f3f4f6;
  border-color: #9ca3af;
  font-weight: 600;
  color: #111827;
}
.nc-hint {
  font-size: 0.85rem;
  color: #6b7280;
  margin: 0 0 12px;
  max-width: 52rem;
  line-height: 1.4;
}
.nc-email {
  font-size: 0.9rem;
  color: #374151;
}
.nc-status-select {
  font-size: 0.85rem;
  padding: 4px 8px;
  border-radius: 4px;
  border: 1px solid #d1d5db;
  max-width: 11rem;
}
.nc-reply-link {
  display: inline-block;
  text-decoration: none;
  text-align: center;
  line-height: 1.25;
}
.nc-empty-found {
  padding: 12px 0;
  color: #6b7280;
  font-size: 0.95rem;
}
.nc-empty-found p {
  margin: 0;
}

tr.nc-row-flash {
  animation: nc-flash-bg 1.1s ease-out 2;
}
@keyframes nc-flash-bg {
  0%,
  100% {
    background: transparent;
  }
  35% {
    background: #dbeafe;
  }
}
</style>
