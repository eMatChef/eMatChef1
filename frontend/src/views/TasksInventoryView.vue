<template>
  <div class="tasks-inventory-panel">
    <div class="tasks-inventory-toolbar">
      <p class="tasks-inventory-intro">{{ t('tasksInventory.intro') }}</p>
      <EButton variant="primary" size="small" @click="openCreateDialog">
        {{ t('tasksInventory.createTask') }}
      </EButton>
    </div>

    <v-tabs v-model="statusTab" class="tasks-general-tabs" color="primary">
      <v-tab value="open">
        {{ t('tasksInventory.tabOpen') }}
        <v-chip v-if="openItems.length" size="x-small" variant="tonal" class="tasks-general-tab-count">
          {{ openItems.length }}
        </v-chip>
      </v-tab>
      <v-tab value="done">
        {{ t('tasksInventory.tabDone') }}
        <v-chip v-if="doneItems.length" size="x-small" variant="tonal" class="tasks-general-tab-count">
          {{ doneItems.length }}
        </v-chip>
      </v-tab>
    </v-tabs>

    <ELoadingState v-if="loading" variant="list" :message="t('tasksInventory.loading')" />

    <div v-else-if="loadError" class="tasks-inventory-state">
      <v-alert type="error" variant="tonal" :text="loadError" />
      <EButton variant="secondary" size="small" class="mt-3" @click="loadData">{{ t('common.retry') }}</EButton>
    </div>

    <EEmptyState
      v-else-if="visibleItems.length === 0"
      :title="statusTab === 'done' ? t('tasksInventory.emptyDoneTitle') : t('tasksInventory.emptyOpenTitle')"
      :description="statusTab === 'done' ? t('tasksInventory.emptyDoneText') : t('tasksInventory.emptyOpenText')"
    />

    <div v-else class="tasks-list" role="list">
      <article
        v-for="item in visibleItems"
        :key="item.key"
        role="listitem"
        class="task-row"
      >
        <div class="task-row__main">
          <span class="task-row__kind">{{ kindLabel(item.kind) }}</span>
          <h3 class="task-row__title">{{ item.title }}</h3>
          <p class="task-row__preview">{{ item.preview }}</p>
          <time class="task-row__date">{{ formatDate(item.updatedAt) }}</time>
        </div>
        <div class="task-row__actions">
          <EButton
            v-if="statusTab === 'open'"
            variant="primary"
            size="small"
            @click="openCountDialog(item)"
          >
            {{ t('tasksInventory.actionCount') }}
          </EButton>
          <EButton
            v-else-if="item.kind === 'inspection'"
            variant="secondary"
            size="small"
            @click="openWorkshopTicket(item.ticketId)"
          >
            {{ t('tasksInventory.actionOpenTicket') }}
          </EButton>
        </div>
      </article>
    </div>

    <EDialog
      v-model="countDialogOpen"
      :title="countDialogTitle"
      :max-width="640"
      scrollable
    >
      <p v-if="countDialogSubtitle" class="count-dialog-subtitle">{{ countDialogSubtitle }}</p>

      <InventoryCountLines
        v-if="editableLines.length"
        ref="countLinesRef"
        :lines="editableLines"
        :disabled="acting"
        @update:lines="editableLines = $event"
      />
      <p v-else class="count-dialog-empty">{{ t('tasksInventory.noLines') }}</p>

      <ETextarea
        v-model="completionNotes"
        class="mt-3"
        :label="t('tasksInventory.notesLabel')"
        rows="2"
        hide-details="auto"
      />

      <p v-if="countError" class="count-dialog-error">{{ countError }}</p>

      <template #actions>
        <EButton variant="secondary" size="small" :disabled="acting" @click="closeCountDialog">
          {{ t('common.cancel') }}
        </EButton>
        <EButton
          v-if="activeItem?.kind === 'task'"
          variant="secondary"
          size="small"
          :disabled="acting || !editableLines.length"
          :loading="acting"
          @click="saveTaskProgress"
        >
          {{ t('tasksInventory.saveProgress') }}
        </EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="acting || !editableLines.length"
          :loading="acting"
          @click="completeCounting"
        >
          {{ t('tasksInventory.complete') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog v-model="createDialogOpen" :title="t('tasksInventory.createTitle')" :max-width="480">
      <ETextField
        v-model="createTitle"
        :label="t('tasksInventory.createTitleLabel')"
        hide-details="auto"
      />
      <p v-if="createError" class="count-dialog-error">{{ createError }}</p>
      <template #actions>
        <EButton variant="secondary" size="small" @click="createDialogOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :loading="acting" :disabled="!createTitle.trim()" @click="createTask">
          {{ t('common.create') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import {
  createInventoryTask,
  getInventoryTask,
  listInventoryTasks,
  updateInventoryTask,
  type InventoryTask,
  type InventoryTaskLine,
} from '@/api/inventoryTasks'
import { getWorkshopTickets, transitionWorkshopTicket, type WorkshopTicket } from '@/api/workshop'
import InventoryCountLines from '@/components/inventory/InventoryCountLines.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import { EButton, EDialog, ETextField, ETextarea } from '@/components/form/base'

type InventoryItemKind = 'task' | 'inspection'

interface InventoryListItem {
  key: string
  kind: InventoryItemKind
  title: string
  preview: string
  updatedAt: string
  ticketId?: string
  task?: InventoryTask
  ticket?: WorkshopTicket
}

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()

const departmentId = computed(() => String(route.params.departmentId || ''))

const loading = ref(true)
const loadError = ref('')
const statusTab = ref<'open' | 'done'>('open')
const inventoryTasks = ref<InventoryTask[]>([])
const inspectionTickets = ref<WorkshopTicket[]>([])

const countDialogOpen = ref(false)
const countDialogTitle = ref('')
const countDialogSubtitle = ref('')
const activeItem = ref<InventoryListItem | null>(null)
const editableLines = ref<InventoryTaskLine[]>([])
const completionNotes = ref('')
const countError = ref('')
const acting = ref(false)
const countLinesRef = ref<InstanceType<typeof InventoryCountLines> | null>(null)

const createDialogOpen = ref(false)
const createTitle = ref('')
const createError = ref('')

const openInventoryTasks = computed(() =>
  inventoryTasks.value.filter((task) => ['open', 'in_progress'].includes(task.status)),
)

const doneInventoryTasks = computed(() =>
  inventoryTasks.value.filter((task) => task.status === 'completed'),
)

const openInspectionTickets = computed(() =>
  inspectionTickets.value.filter(
    (ticket) =>
      ticket.strategy === 'inspection'
      && !['completed', 'cancelled'].includes(ticket.status),
  ),
)

const doneInspectionTickets = computed(() =>
  inspectionTickets.value.filter(
    (ticket) => ticket.strategy === 'inspection' && ticket.status === 'completed',
  ),
)

function toTaskItem(task: InventoryTask): InventoryListItem {
  const lineCount = task.lines_json?.lines?.length ?? 0
  return {
    key: `task-${task.id}`,
    kind: 'task',
    title: task.title,
    preview: t('tasksInventory.previewTask', { count: lineCount }),
    updatedAt: task.updated_at,
    task,
  }
}

function toInspectionItem(ticket: WorkshopTicket): InventoryListItem {
  const material = ticket.material_item?.name || '—'
  return {
    key: `inspection-${ticket.id}`,
    kind: 'inspection',
    title: ticket.title,
    preview: t('tasksInventory.previewInspection', { material }),
    updatedAt: ticket.updated_at,
    ticketId: ticket.id,
    ticket,
  }
}

const openItems = computed(() => [
  ...openInventoryTasks.value.map(toTaskItem),
  ...openInspectionTickets.value.map(toInspectionItem),
])

const doneItems = computed(() => [
  ...doneInventoryTasks.value.map(toTaskItem),
  ...doneInspectionTickets.value.map(toInspectionItem),
])

const visibleItems = computed(() =>
  statusTab.value === 'done' ? doneItems.value : openItems.value,
)

function kindLabel(kind: InventoryItemKind): string {
  return kind === 'task' ? t('tasksInventory.kindTask') : t('tasksInventory.kindInspection')
}

function formatDate(value: string): string {
  try {
    return new Date(value).toLocaleString()
  } catch {
    return value
  }
}

async function loadData() {
  if (!departmentId.value) return
  loading.value = true
  loadError.value = ''
  try {
    const [tasks, tickets] = await Promise.all([
      listInventoryTasks(departmentId.value),
      getWorkshopTickets(departmentId.value, { type: 'inspection' }),
    ])
    inventoryTasks.value = tasks
    inspectionTickets.value = tickets.filter((ticket) => ticket.strategy === 'inspection')
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    loadError.value = message || t('tasksInventory.loadFailed')
  } finally {
    loading.value = false
  }
}

void loadData()

function inspectionLines(ticket: WorkshopTicket): InventoryTaskLine[] {
  const expected = Math.max(1, ticket.affected_quantity ?? ticket.material_item?.total_stock ?? 1)
  return [
    {
      id: `inspection-${ticket.id}`,
      material_item_id: ticket.material_item?.id,
      material_name: ticket.material_item?.name,
      expected_qty: expected,
      counted_qty: null,
    },
  ]
}

function taskLines(task: InventoryTask): InventoryTaskLine[] {
  const lines = task.lines_json?.lines ?? []
  return lines.map((line) => ({
    ...line,
    counted_qty: line.counted_qty ?? null,
  }))
}

async function openCountDialog(item: InventoryListItem) {
  activeItem.value = item
  countError.value = ''
  completionNotes.value = ''

  if (item.kind === 'task' && item.task) {
    countDialogTitle.value = item.task.title
    countDialogSubtitle.value = t('tasksInventory.dialogTaskHint')
    try {
      const detailed = await getInventoryTask(item.task.id)
      editableLines.value = taskLines(detailed)
    } catch {
      editableLines.value = taskLines(item.task)
    }
  } else if (item.kind === 'inspection' && item.ticket) {
    countDialogTitle.value = item.ticket.title
    countDialogSubtitle.value = t('tasksInventory.dialogInspectionHint', {
      material: item.ticket.material_item?.name || '—',
    })
    editableLines.value = inspectionLines(item.ticket)
  }

  countDialogOpen.value = true
}

function closeCountDialog() {
  countDialogOpen.value = false
  activeItem.value = null
  editableLines.value = []
}

function buildLinesPayload(): { lines: InventoryTaskLine[] } {
  return {
    lines: editableLines.value.map((line) => ({
      ...line,
      counted_qty: line.counted_qty ?? 0,
    })),
  }
}

function ensureAllConfirmed(): boolean {
  if (!countLinesRef.value?.allLinesConfirmed()) {
    countError.value = t('tasksInventory.errorConfirmAll')
    return false
  }
  return true
}

async function saveTaskProgress() {
  if (!activeItem.value?.task) return
  acting.value = true
  countError.value = ''
  try {
    await updateInventoryTask(activeItem.value.task.id, {
      lines_json: buildLinesPayload(),
      status: 'in_progress',
    })
    toast.success(t('tasksInventory.savedProgress'))
    await loadData()
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    countError.value = message || t('tasksInventory.saveFailed')
  } finally {
    acting.value = false
  }
}

async function completeCounting() {
  if (!ensureAllConfirmed() || !activeItem.value) return

  acting.value = true
  countError.value = ''
  try {
    if (activeItem.value.kind === 'task' && activeItem.value.task) {
      const task = await updateInventoryTask(activeItem.value.task.id, {
        lines_json: buildLinesPayload(),
        status: 'completed',
      })
      if (task.workshop_ticket_id) {
        await transitionWorkshopTicket(task.workshop_ticket_id, {
          status: 'completed',
          resolution_action: 'ok',
          resolution_notes: completionNotes.value || undefined,
          inventory_task_id: task.id,
        })
      }
      toast.success(t('tasksInventory.completedTask'))
    } else if (activeItem.value.kind === 'inspection' && activeItem.value.ticketId) {
      await transitionWorkshopTicket(activeItem.value.ticketId, {
        status: 'completed',
        resolution_action: 'ok',
        resolution_notes: completionNotes.value || buildCompletionSummary(),
      })
      toast.success(t('tasksInventory.completedInspection'))
    }

    closeCountDialog()
    await loadData()
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    countError.value = message || t('tasksInventory.completeFailed')
  } finally {
    acting.value = false
  }
}

function buildCompletionSummary(): string {
  const parts = editableLines.value.map((line) => {
    const name = line.material_name || line.material_item_id || line.id
    return `${name}: ${line.counted_qty ?? 0} / ${line.expected_qty}`
  })
  return parts.join('; ')
}

function openWorkshopTicket(ticketId?: string) {
  if (!ticketId) return
  void router.push({
    path: `/${departmentId.value}/workshop`,
    query: { ticket: ticketId },
  })
}

function openCreateDialog() {
  createTitle.value = ''
  createError.value = ''
  createDialogOpen.value = true
}

async function createTask() {
  if (!departmentId.value || !createTitle.value.trim()) return
  acting.value = true
  createError.value = ''
  try {
    const task = await createInventoryTask({
      department_id: departmentId.value,
      title: createTitle.value.trim(),
      lines_json: { lines: [] },
    })
    createDialogOpen.value = false
    toast.success(t('tasksInventory.created'))
    await loadData()
    openCountDialog(toTaskItem(task))
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    createError.value = message || t('tasksInventory.createFailed')
  } finally {
    acting.value = false
  }
}
</script>

<style scoped>
.tasks-inventory-panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.tasks-inventory-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.tasks-inventory-intro {
  margin: 0;
  font-size: 14px;
  color: #4b5563;
  flex: 1 1 240px;
}

.tasks-inventory-state {
  padding: 16px 0;
}

.tasks-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.task-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}

.task-row__kind {
  display: inline-block;
  margin-bottom: 6px;
  padding: 2px 8px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #1d4ed8;
  background: #eff6ff;
  border-radius: 4px;
}

.task-row__title {
  margin: 0 0 4px;
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
}

.task-row__preview {
  margin: 0 0 6px;
  font-size: 0.9rem;
  color: #4b5563;
}

.task-row__date {
  font-size: 0.8rem;
  color: #9ca3af;
}

.task-row__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}

.count-dialog-subtitle {
  margin: 0 0 12px;
  font-size: 13px;
  color: #6b7280;
}

.count-dialog-empty {
  margin: 0;
  font-size: 13px;
  color: #6b7280;
}

.count-dialog-error {
  margin: 12px 0 0;
  color: #b91c1c;
  font-size: 13px;
}
</style>
