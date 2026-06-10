<template>
  <PageShell class="workshop-view">
    <template #title>{{ t('workshop.title') }}</template>
    <template #subtitle>{{ t('workshop.description') }}</template>
    <template #actions>
      <EButton variant="primary" @click="showCreateModal = true">
        <v-icon icon="mdi-plus" start size="20" />
        {{ t('workshop.newTicket') }}
      </EButton>
    </template>

    <template #filters>
    <div class="workshop-toolbar">
      <v-btn-toggle
        v-model="viewMode"
        mandatory
        density="comfortable"
        color="primary"
        class="workshop-view-toggle"
      >
        <v-btn value="kanban" size="small">
          <v-icon icon="mdi-view-column" start size="18" />
          {{ t('workshop.viewKanban') }}
        </v-btn>
        <v-btn value="table" size="small">
          <v-icon icon="mdi-format-list-bulleted" start size="18" />
          {{ t('workshop.viewTable') }}
        </v-btn>
      </v-btn-toggle>

      <div class="search-box">
        <GlobalSearchInput
          mode="inline"
          :department-id="currentDepartmentId"
          default-type="reparatur"
          v-model="searchQuery"
          :placeholder="t('workshop.searchListPlaceholder')"
        />
      </div>

      <div class="toolbar-filters">
        <ESelect
          v-model="filterType"
          :items="typeFilterItems"
          :label="t('workshop.filterAllTypes')"
          hide-details="auto"
          class="workshop-filter-select"
        />
        <ESelect
          v-model="filterOriginIssueType"
          :items="originFilterItems"
          :label="t('workshop.filterAllSources')"
          hide-details="auto"
          class="workshop-filter-select"
        />
        <ESelect
          v-model="filterPriority"
          :items="priorityFilterItems"
          :label="t('workshop.filterAllPriorities')"
          hide-details="auto"
          class="workshop-filter-select"
        />
      </div>
      <div
        v-if="quickFilter"
        style="display:flex; align-items:center; gap:8px; margin-left:auto; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:999px; padding:6px 10px; font-size:12px; font-weight:600;"
      >
        <span>{{ quickFilter === 'waiting_quote' ? t('workshop.quickFilterQuotes') : t('workshop.quickFilterPrice') }}</span>
        <button
          style="border:none; background:transparent; color:#1d4ed8; cursor:pointer; font-weight:700; font-size:14px; line-height:1;"
          @click="router.replace({ path: route.path, query: { ...route.query, qf: undefined } })"
          :title="t('workshop.removeFilterTitle')"
        >
          ×
        </button>
      </div>
      <div
        v-if="materialFilterId"
        style="display:flex; align-items:center; gap:8px; margin-left:auto; background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; border-radius:999px; padding:6px 10px; font-size:12px; font-weight:600;"
      >
        <span>{{ t('workshop.materialOnly', { label: materialFilterLabel || materialFilterId }) }}</span>
        <button
          type="button"
          style="border:none; background:transparent; color:#166534; cursor:pointer; font-weight:700; font-size:14px; line-height:1;"
          :title="t('workshop.removeFilterTitle')"
          @click="clearMaterialFilter"
        >
          ×
        </button>
      </div>
    </div>
    </template>

    <div class="workshop-body">
      <div v-if="stats" class="workshop-stats">
        <div
          v-for="phase in statsPhases"
          :key="phase"
          class="stat-card"
          :class="phase"
        >
          <div class="stat-value">{{ stats.phase_counts?.[phase] || 0 }}</div>
          <div class="stat-label">{{ phaseLabels[phase] }}</div>
        </div>
      </div>

      <ELoadingState v-if="isLoading" variant="page" :message="t('workshop.loadingTickets')" />

      <EEmptyState
        v-else-if="tickets.length === 0 && !isLoading"
        variant="create"
        :title="t('workshop.emptyTitle')"
        :description="t('workshop.emptyText')"
      >
        <template #actions>
          <EButton variant="primary" @click="showCreateModal = true">
            <v-icon icon="mdi-plus" start size="20" />
            {{ t('workshop.createFirstTicket') }}
          </EButton>
        </template>
      </EEmptyState>

      <p
        v-else-if="viewMode === 'kanban' && kanbanColumns.length === 0"
        class="kanban-no-columns-hint"
      >
        {{ t('workshop.kanbanNoVisibleColumns') }}
      </p>

      <div v-else-if="viewMode === 'kanban'" class="kanban-board">
        <div
          v-for="col in kanbanColumns"
          :key="col.phase"
          class="kanban-column"
          :class="col.phase"
        >
          <div class="kanban-column-header">
            <span class="column-title">{{ col.label }}</span>
            <span class="column-count">{{ col.count }}</span>
          </div>
          <div class="kanban-column-body">
            <div
              v-for="ticket in col.tickets"
              :key="ticket.id"
              class="kanban-card"
              @click="openTicketDetail(ticket)"
            >
              <div class="card-header">
                <span class="card-title">{{ ticket.title }}</span>
                <span class="priority-badge" :class="ticket.priority">
                  {{ priorityLabels[ticket.priority] }}
                </span>
              </div>
              <div class="card-material">
                <svg
                  class="mat-icon"
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                {{ ticket.material_item.name }}
              </div>
              <div class="card-footer">
                <div class="card-meta">
                  <span class="type-badge" :class="ticket.type">{{ typeLabels[ticket.type] }}</span>
                  <span
                    v-if="ticket.strategy_label"
                    class="workflow-badge strategy"
                    :class="ticket.strategy"
                  >
                    {{ ticket.strategy_label }}
                  </span>
                  <span v-if="ticket.phase_label" class="workflow-badge phase">
                    {{ ticket.phase_label }}
                  </span>
                  <span
                    v-if="ticket.origin_issue_type"
                    class="origin-badge"
                    :class="getIssueOriginBadgeClass(ticket.origin_issue_type)"
                    :title="t('workshop.issueOriginCreatedTitle', { origin: getIssueOriginBadgeLabel(ticket.origin_issue_type) })"
                  >
                    {{ getIssueOriginBadgeLabel(ticket.origin_issue_type) }}
                  </span>
                </div>
                <div class="card-meta">
                  <span v-if="ticket.assigned_to" class="assigned-avatar" :title="ticket.assigned_to.name">
                    {{ getInitials(ticket.assigned_to.name) }}
                  </span>
                  <span class="card-date">{{ formatDateShort(ticket.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="viewMode === 'kanban' && cancelledTickets.length > 0"
        class="cancelled-section"
      >
        <div class="cancelled-section-header">
          <span>{{ t('workshop.cancelledSection') }}</span>
          <span class="cancelled-count">{{ cancelledTickets.length }}</span>
        </div>
        <div class="cancelled-list">
          <div
            v-for="ticket in cancelledTickets"
            :key="ticket.id"
            class="cancelled-item"
            @click="openTicketDetail(ticket)"
          >
            <span class="cancelled-title">{{ ticket.title }}</span>
            <span class="cancelled-date">{{ formatDateShort(ticket.created_at) }}</span>
          </div>
        </div>
      </div>

      <div v-else-if="viewMode === 'table'" class="table-wrapper">
      <table class="workshop-table">
        <thead>
          <tr>
            <th>{{ t('common.status') }}</th>
            <th>{{ t('workshop.tablePriority') }}</th>
            <th>{{ t('workshop.tableTitle') }}</th>
            <th>{{ t('common.material') }}</th>
            <th>{{ t('workshop.tableType') }}</th>
            <th>{{ t('workshop.tableAssigned') }}</th>
            <th>{{ t('workshop.tableCreated') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="ticket in filteredTickets"
            :key="ticket.id"
            @click="openTicketDetail(ticket)"
          >
            <td>
              <div class="table-status-cell">
                <span class="phase-badge" :class="getTicketDisplayPhase(ticket)">
                  <span class="phase-dot" :class="getTicketDisplayPhase(ticket)"></span>
                  {{ getTicketPhaseLabel(ticket) }}
                </span>
                <span
                  v-if="ticket.strategy_label"
                  class="workflow-badge strategy"
                  :class="ticket.strategy"
                >
                  {{ ticket.strategy_label }}
                </span>
              </div>
            </td>
            <td>
              <span class="priority-badge" :class="ticket.priority">
                {{ priorityLabels[ticket.priority] }}
              </span>
            </td>
            <td style="font-weight: 500; max-width: 280px; overflow: hidden; text-overflow: ellipsis;">
              {{ ticket.title }}
            </td>
            <td>{{ ticket.material_item.name }}</td>
            <td>
              <span class="type-badge" :class="ticket.type">{{ typeLabels[ticket.type] }}</span>
              <span
                v-if="ticket.origin_issue_type"
                class="origin-badge"
                :class="getIssueOriginBadgeClass(ticket.origin_issue_type)"
                style="margin-left: 8px;"
                :title="t('workshop.issueOriginCreatedTitle', { origin: getIssueOriginBadgeLabel(ticket.origin_issue_type) })"
              >
                {{ getIssueOriginBadgeLabel(ticket.origin_issue_type) }}
              </span>
            </td>
            <td>
              <span v-if="ticket.assigned_to" style="display: flex; align-items: center; gap: 6px;">
                <span class="assigned-avatar">{{ getInitials(ticket.assigned_to.name) }}</span>
                {{ ticket.assigned_to.name }}
              </span>
              <span v-else style="color: #9ca3af;">{{ t('workshop.dash') }}</span>
            </td>
            <td>{{ formatDateShort(ticket.created_at) }}</td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <EDialog
      v-model="detailDialogOpen"
      :max-width="920"
      scrollable
      card-class="workshop-detail-dialog"
    >
      <template v-if="selectedTicket" #title>
        <div class="workshop-detail-dialog-title">
          <div class="workshop-detail-dialog-title-row">
            <span class="workshop-detail-dialog-title-text">{{ selectedTicket.title }}</span>
            <button
              v-if="isTerminalPhase(selectedTicket)"
              type="button"
              class="workshop-detail-dialog-close"
              :aria-label="t('common.close')"
              @click="closeSelectedTicketDetail"
            >
              <v-icon icon="mdi-close" size="18" />
              <span>{{ t('common.close') }}</span>
            </button>
          </div>
          <div class="modal-subtitle">
            <span class="phase-badge" :class="getTicketDisplayPhase(selectedTicket)">
              <span class="phase-dot" :class="getTicketDisplayPhase(selectedTicket)"></span>
              {{ getTicketPhaseLabel(selectedTicket) }}
            </span>
            <span class="priority-badge" :class="selectedTicket.priority">
              {{ priorityLabels[selectedTicket.priority] }}
            </span>
            <span class="type-badge" :class="selectedTicket.type">
              {{ typeLabels[selectedTicket.type] }}
            </span>
            <span
              v-if="selectedTicket.strategy_label"
              class="workflow-badge strategy"
              :class="selectedTicket.strategy"
            >
              {{ selectedTicket.strategy_label }}
            </span>
            <span v-if="selectedTicket.phase_label" class="workflow-badge phase">
              {{ selectedTicket.phase_label }}
            </span>
          </div>
        </div>
      </template>

      <div v-if="selectedTicket" ref="detailDialogBodyRef" class="workshop-detail-dialog-body">
        <v-tabs
          v-model="detailTab"
          class="workshop-detail-tabs"
          color="primary"
          density="comfortable"
        >
          <v-tab value="meldung">{{ t('workshop.detailTab.report') }}</v-tab>
          <v-tab value="arbeit">{{ t('workshop.detailTab.work') }}</v-tab>
          <v-tab value="verlauf">{{ t('workshop.detailTab.history') }}</v-tab>
        </v-tabs>

        <div v-show="detailTab === 'meldung'" class="workshop-detail-tab-panel">
          <div v-if="canManageWorkshopQr && workshopPublicUrl" class="workshop-qr-header-actions">
            <PublicQrTag
              :url="workshopPublicUrl"
              :code="selectedTicket.public_code"
              :size="56"
              :clickable="true"
              :image-label="selectedTicket.title"
              :image-entity-id="selectedTicket.id"
              @activate="openWorkshopQrActionModal"
            />
          </div>
          <!-- Material Info -->
          <div class="modal-section">
            <div class="modal-section-title">{{ t('common.material') }}</div>
            <div class="material-info-block">
              <div class="mat-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
              </div>
              <div class="mat-details">
                <div class="mat-name">{{ selectedTicket.material_item.name }}</div>
                <div class="mat-meta">
                  <span v-if="selectedTicket.material_item.barcode_tag">{{ t('workshop.tagPrefix') }} {{ selectedTicket.material_item.barcode_tag }}</span>
                  <span v-if="selectedTicket.material_item.category">{{ selectedTicket.material_item.category.name }}</span>
                  <span v-if="selectedTicket.affected_quantity && !selectedTicket.material_batch">
                    {{ t('workshop.createAffectedQtyOfStock', { qty: selectedTicket.affected_quantity, stock: selectedTicket.material_item.total_stock ?? '—' }) }}
                  </span>
                  <span>{{ t('workshop.conditionPrefix') }} {{ conditionLabels[selectedTicket.material_item.condition] || selectedTicket.material_item.condition }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Beschreibung -->
          <div v-if="selectedTicket.description" class="modal-section">
            <div class="modal-section-title">{{ t('common.description') }}</div>
            <p style="font-size: 14px; color: #374151; line-height: 1.6; margin: 0; white-space: pre-wrap;">{{ selectedTicket.description }}</p>
          </div>

          <!-- Herkunft (Origin) -->
          <div v-if="selectedTicket.activity || selectedTicket.issue_report" class="modal-section">
            <div class="modal-section-title">{{ t('workshop.sectionOrigin') }}</div>
            <div class="origin-block">
              <!-- Aktivitäts-Info -->
              <div v-if="selectedTicket.activity" class="origin-item">
                <div class="origin-icon">📋</div>
                <div class="origin-details">
                  <div class="origin-label">{{ t('workshop.originActivity') }}</div>
                  <div class="origin-value">{{ selectedTicket.activity.name }}</div>
                  <div class="origin-meta">
                    <span class="type-badge" :class="selectedTicket.activity.type" style="font-size: 10px;">
                      {{ selectedTicket.activity.type }}
                    </span>
                    <span class="status-badge" :class="selectedTicket.activity.status" style="font-size: 10px; padding: 1px 6px;">
                      {{ selectedTicket.activity.status }}
                    </span>
                  </div>
                </div>
              </div>
              <!-- Schadensmeldung-Info -->
              <div v-if="selectedTicket.issue_report" class="origin-item">
                <div class="origin-icon">⚠️</div>
                <div class="origin-details">
                  <div class="origin-label">{{ t('workshop.originDamageReport') }}</div>
                  <div class="origin-value">
                    {{ selectedTicket.issue_report.type_label }}
                    <span v-if="selectedTicket.issue_report.quantity && selectedTicket.issue_report.quantity > 1">
                      ({{ selectedTicket.issue_report.quantity }} {{ t('workshop.stockUnit') }})
                    </span>
                  </div>
                  <div v-if="selectedTicket.issue_report.description" class="origin-description">
                    {{ selectedTicket.issue_report.description }}
                  </div>
                  <PhotoGallery
                    v-if="issueReportPhotos.length"
                    :photos="issueReportPhotos"
                    :show-meta="false"
                  />
                  <div class="origin-meta">
                    <span v-if="selectedTicket.issue_report.reported_by">
                      👤 {{ selectedTicket.issue_report.reported_by.name }}
                    </span>
                    <span>{{ formatDateTime(selectedTicket.issue_report.reported_at) }}</span>
                    <span v-if="selectedTicket.issue_report.resolved" class="resolved-badge">{{ t('workshop.issueResolved') }}</span>
                    <span v-else class="unresolved-badge">{{ t('workshop.issueOpen') }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Ticket-Fotos -->
          <div class="modal-section">
            <div class="modal-section-title">{{ t('workshop.sectionPhotos') }}</div>
            <PhotoGallery
              :photos="ticketPhotos"
              :show-empty="true"
              :empty-text="t('workshop.photosEmpty')"
              :format-date="formatDateTime"
            />
            <PhotoUpload
              v-if="canUploadWorkshopPhotos"
              :upload-fn="uploadTicketPhoto"
              :label="t('workshop.uploadPhoto')"
              @uploaded="onTicketPhotoUploaded"
              @error="onTicketPhotoError"
            />
          </div>
        </div>

        <div v-show="detailTab === 'arbeit'" class="workshop-detail-tab-panel">
          <WorkshopWorkflowStepper
            v-if="selectedTicket.strategy === 'internal_repair'"
            :ticket="selectedTicket"
            :hint="workflowHintText(selectedTicket)"
          />

          <div
            v-else-if="workflowHintText(selectedTicket)"
            class="workflow-next-hint"
          >
            <span>{{ workflowHintText(selectedTicket) }}</span>
          </div>

          <div v-if="selectedTicket" ref="repairSheetSectionRef" class="modal-section">
            <WorkshopTicketRepairSheetPanel
              :ticket="selectedTicket"
              :department-id="currentDepartmentId"
              @updated="onRepairSheetUpdated"
            />
          </div>

          <div v-if="selectedTicket?.strategy === 'external_cleaning'" class="modal-section">
            <WorkshopExternalCleaningPanel
              :ticket="selectedTicket"
              :department-id="currentDepartmentId"
            />
          </div>

          <div v-if="selectedTicket" ref="repairPartsSectionRef" class="modal-section">
            <RepairPartsList
              ref="repairPartsListRef"
              :ticket="selectedTicket"
              :department-id="currentDepartmentId"
              @updated="onRepairSheetUpdated"
            />
          </div>

          <div class="modal-section">
            <div class="modal-section-title">{{ t('workshop.sectionDetails') }}</div>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">{{ t('workshop.detailCreatedBy') }}</span>
                <span class="detail-value">{{ selectedTicket.created_by?.name || t('workshop.dash') }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">{{ t('workshop.detailAssignedTo') }}</span>
                <span class="detail-value">{{ selectedTicket.assigned_to?.name || t('workshop.notAssigned') }}</span>
              </div>
              <div v-if="selectedTicket.assigned_to_supplier_company" class="detail-item">
                <span class="detail-label">{{ t('workshop.detailAssignedSupplier') }}</span>
                <span class="detail-value">{{ selectedTicket.assigned_to_supplier_company.name }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">{{ t('workshop.detailEstimatedCost') }}</span>
                <span class="detail-value">{{ selectedTicket.estimated_cost ? selectedTicket.estimated_cost + t('workshop.chfSuffix') : t('workshop.dash') }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">{{ t('workshop.detailActualCost') }}</span>
                <span class="detail-value">{{ selectedTicket.actual_cost ? selectedTicket.actual_cost + t('workshop.chfSuffix') : t('workshop.dash') }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">{{ t('workshop.detailCreatedAt') }}</span>
                <span class="detail-value">{{ formatDateTime(selectedTicket.created_at) }}</span>
              </div>
              <div v-if="selectedTicket.started_at" class="detail-item">
                <span class="detail-label">{{ t('workshop.detailStartedAt') }}</span>
                <span class="detail-value">{{ formatDateTime(selectedTicket.started_at) }}</span>
              </div>
              <div v-if="selectedTicket.completed_at" class="detail-item">
                <span class="detail-label">{{ t('workshop.detailCompletedAt') }}</span>
                <span class="detail-value">{{ formatDateTime(selectedTicket.completed_at) }}</span>
              </div>
              <div v-if="selectedTicket.resolution_action" class="detail-item">
                <span class="detail-label">{{ t('workshop.detailOutcome') }}</span>
                <span class="detail-value">{{ resolutionLabels[selectedTicket.resolution_action] || selectedTicket.resolution_action }}</span>
              </div>
            </div>
          </div>
        </div>

        <div v-show="detailTab === 'verlauf'" class="workshop-detail-tab-panel">
          <div class="modal-section">
            <div class="modal-section-title">{{ t('workshop.sectionHistory') }}</div>
            <div v-if="isLoadingHistory" style="text-align: center; padding: 16px; color: #9ca3af; font-size: 13px;">
              {{ t('workshop.loadingHistory') }}
            </div>
            <div v-else-if="ticketHistory.length > 0" class="status-timeline">
              <div
                v-for="entry in ticketHistory"
                :key="entry.id"
                class="timeline-item"
              >
                <div class="timeline-dot" :class="getHistoryDotClass(entry.action)"></div>
                <div class="timeline-content">
                  <div class="timeline-header">
                    <span class="timeline-icon">{{ getHistoryIcon(entry.action) }}</span>
                    <span class="timeline-label">{{ entry.action_label }}</span>
                  </div>
                  <div v-if="getHistoryDescription(entry)" class="timeline-description">
                    {{ getHistoryDescription(entry) }}
                  </div>
                  <div class="timeline-meta">
                    <span v-if="entry.user" class="timeline-user">{{ entry.user.name }}</span>
                    <span class="timeline-date">{{ formatDateTime(entry.created_at) }}</span>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="status-timeline">
              <!-- Fallback: statische Timeline wenn keine History-Daten -->
              <div class="timeline-item">
                <div class="timeline-dot created"></div>
                <div class="timeline-content">
                  <div class="timeline-label">{{ t('workshop.timelineCreated') }}</div>
                  <div class="timeline-date">{{ formatDateTime(selectedTicket.created_at) }}</div>
                </div>
              </div>
              <div v-if="selectedTicket.started_at" class="timeline-item">
                <div class="timeline-dot active"></div>
                <div class="timeline-content">
                  <div class="timeline-label">{{ t('workshop.timelineStarted') }}</div>
                  <div class="timeline-date">{{ formatDateTime(selectedTicket.started_at) }}</div>
                </div>
              </div>
              <div v-if="selectedTicket.completed_at" class="timeline-item">
                <div class="timeline-dot completed"></div>
                <div class="timeline-content">
                  <div class="timeline-label">{{ t('workshop.timelineCompleted') }}</div>
                  <div class="timeline-date">{{ formatDateTime(selectedTicket.completed_at) }}</div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="selectedTicket.resolution_notes" class="modal-section">
            <div class="modal-section-title">{{ t('workshop.sectionResolutionNotes') }}</div>
            <p style="font-size: 14px; color: #374151; line-height: 1.6; margin: 0; background: #f0fdf4; padding: 10px 14px; border-radius: 8px; white-space: pre-wrap;">{{ selectedTicket.resolution_notes }}</p>
          </div>
        </div>
      </div>

      <template v-if="selectedTicket && isActivePhase(selectedTicket)" #actions>
        <div class="workshop-detail-actions">
          <div class="workshop-detail-actions-primary">
            <EButton
              v-if="selectedTicket.strategy === 'triage'"
              variant="primary"
              size="small"
              @click="openTriageDialog(selectedTicket)"
            >
              {{ t('workshop.triage.start') }}
            </EButton>
            <EButton
              v-else-if="isLossOriginTicket(selectedTicket) && ['triage', 'writeoff'].includes(selectedTicket.strategy)"
              variant="danger"
              size="small"
              @click="openLossAcceptModal()"
            >
              {{ t('workshop.btnAcceptLoss') }}
            </EButton>
            <EButton
              v-if="canSendToSupplier"
              variant="primary"
              size="small"
              :loading="isSendingToSupplier"
              @click="openSendToSupplierModal"
            >
              {{ t('workshop.sendToSupplier') }}
            </EButton>
            <EButton
              v-if="workflowPrimaryAction === 'advance_ready'"
              variant="primary"
              size="small"
              :loading="isAdvancingPhase"
              @click="advanceTicketPhase('ready')"
            >
              {{ t('workshop.btnAdvanceReady') }}
            </EButton>
            <EButton
              v-else-if="workflowPrimaryAction === 'advance_work'"
              variant="primary"
              size="small"
              :loading="isAdvancingPhase"
              @click="advanceTicketPhase('in_progress')"
            >
              {{ t('workshop.btnStartRepair') }}
            </EButton>
            <EButton
              v-else-if="workflowPrimaryAction === 'complete'"
              variant="primary"
              size="small"
              @click="openCompleteModal"
            >
              {{ t('workshop.btnRepairDone') }}
            </EButton>
            <EButton
              v-else-if="workflowPrimaryAction === 'order_parts'"
              variant="secondary"
              size="small"
              @click="focusRepairPartsSection"
            >
              {{ t('workshop.btnOrderParts') }}
            </EButton>
            <span
              v-else-if="workflowPrimaryAction === 'waiting_parts'"
              class="workflow-waiting-label"
            >
              {{ t('workshop.btnWaitingParts') }}
            </span>
          </div>
          <div class="workshop-detail-actions-secondary">
            <EButton variant="danger" size="small" @click="requestCancelSelectedTicket">
              {{ t('workshop.btnCancelTicket') }}
            </EButton>
            <EButton variant="text" size="small" @click="closeSelectedTicketDetail">
              {{ t('common.close') }}
            </EButton>
          </div>
        </div>
      </template>
      <template v-else-if="selectedTicket && getTicketDisplayPhase(selectedTicket) === 'cancelled'" #actions>
        <EButton variant="secondary" size="small" @click="transitionTicket(selectedTicket.id, 'open')">
          {{ t('workshop.btnReopen') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="showCompleteModal"
      :max-width="520"
      :title="t('workshop.completeTitle')"
    >
      <p v-if="selectedTicket" class="workshop-dialog-subtitle">{{ selectedTicket.title }}</p>
      <div class="resolution-options">
        <div
          class="resolution-option"
          :class="{ selected: completeForm.resolution_action === 'repaired' }"
          @click="completeForm.resolution_action = 'repaired'"
        >
          <div class="option-icon">🔧</div>
          <div class="option-label">{{ t('workshop.resolutionRepaired') }}</div>
          <div class="option-desc">{{ t('workshop.resolutionRepairedDesc') }}</div>
        </div>
        <div
          class="resolution-option"
          :class="{ selected: completeForm.resolution_action === 'ok' }"
          @click="completeForm.resolution_action = 'ok'"
        >
          <div class="option-icon">✅</div>
          <div class="option-label">{{ t('workshop.resolutionOk') }}</div>
          <div class="option-desc">{{ t('workshop.resolutionOkDesc') }}</div>
        </div>
        <div
          class="resolution-option"
          :class="{ selected: completeForm.resolution_action === 'writeoff' }"
          @click="completeForm.resolution_action = 'writeoff'"
        >
          <div class="option-icon">🗑️</div>
          <div class="option-label">{{ t('workshop.resolutionWriteoff') }}</div>
          <div class="option-desc">{{ t('workshop.resolutionWriteoffDesc') }}</div>
        </div>
      </div>
      <div
        v-if="completionPartsWarningVisible"
        class="completion-parts-warning"
      >
        <p class="completion-parts-warning-title">{{ t('workshop.completePartsWarningTitle') }}</p>
        <p class="completion-parts-warning-text">{{ t('workshop.completePartsWarningText') }}</p>
        <ul v-if="completionPartsLines.length" class="completion-parts-list">
          <li v-for="line in completionPartsLines" :key="line.id">
            {{ line.material_name || line.material_item_id }} — {{ formatRepairPartQuantity(line) }}
            <span class="completion-part-source">({{ line.source === 'purchase' ? t('workshop.repairPartsList.source.purchase') : t('workshop.repairPartsList.source.stock') }})</span>
            <span v-if="line.unit_cost">({{ t('workshop.completePartsLineCost', { amount: formatChfAmount(Number(line.unit_cost) * line.quantity) }) }})</span>
          </li>
        </ul>
        <div v-if="completionSurplusVisible" class="completion-surplus-block">
          <p class="completion-surplus-title">{{ t('workshop.completeSurplusTitle') }}</p>
          <p class="completion-surplus-hint">{{ t('workshop.completeSurplusHint') }}</p>
          <div
            v-for="line in completionPurchaseParts"
            :key="`surplus-${line.id}`"
            class="completion-surplus-row"
          >
            <label :for="`surplus-${line.id}`">{{ line.material_name || line.material_item_id }}</label>
            <input
              :id="`surplus-${line.id}`"
              v-model.number="completeForm.parts_surplus[line.id]"
              type="number"
              min="0"
              :max="line.quantity"
              step="any"
              class="form-input completion-surplus-input"
            />
          </div>
        </div>
        <p v-if="completionMaterialCost > 0" class="completion-parts-cost-hint">
          {{ t('workshop.completePartsMaterialCost', { amount: formatChfAmount(completionMaterialCost) }) }}
        </p>
      </div>
      <p
        v-else-if="completionWriteoffPartsHintVisible"
        class="completion-parts-writeoff-hint"
      >
        {{ t('workshop.completePartsWriteoffHint') }}
      </p>
      <WorkshopWriteoffRepurposePanel
        v-if="selectedTicket && completeForm.resolution_action === 'writeoff'"
        v-model="completeForm.writeoff_repurpose"
        :department-id="currentDepartmentId"
        :source-material="selectedTicket.material_item"
        @create-material="openRepurposeMaterialWizard"
      />
      <WorkshopCostSummary
        v-if="completionCostSummaryVisible"
        :ticket="selectedTicket!"
        :department-id="currentDepartmentId"
        :hourly-rate-chf="workshopHourlyRate"
        :parts-material-cost="completionMaterialCost"
        :has-repair-parts="completionPartsLines.length > 0"
        @update:actual-cost="completeForm.actual_cost = $event"
        @update:cost-breakdown="completeCostBreakdown = $event"
      />
      <ETextField
        v-else
        v-model="completeForm.actual_cost"
        class="mt-3"
        type="number"
        :label="t('workshop.labelActualCostChf')"
        :hint="completionMaterialCostHint"
        placeholder="0.00"
        hide-details="auto"
      />
      <ETextarea
        v-model="completeForm.resolution_notes"
        class="mt-3"
        :label="t('workshop.completeNotesLabel')"
        :placeholder="t('workshop.completeNotesPlaceholder')"
        rows="3"
        hide-details="auto"
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="showCompleteModal = false">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!completeForm.resolution_action || completionCostMissing || completionRepurposeInvalid"
          @click="completeTicket"
        >
          {{ t('workshop.btnFinishComplete') }}
        </EButton>
      </template>
    </EDialog>

    <MaterialCreateWizard
      :key="repurposeWizardKey"
      v-model="showRepurposeMaterialWizard"
      :department-id="currentDepartmentId"
      :prefill-from-material-id="selectedTicket?.material_item.id ?? null"
      prefill-force-tracking-type="bulk"
      :prefill-source-note="repurposeWizardSourceNote"
      @created="onRepurposeMaterialCreated"
    />

    <EDialog v-model="showLossAcceptModal" :max-width="520" :title="t('workshop.lossTitle')">
      <p v-if="selectedTicket" class="workshop-dialog-subtitle">{{ selectedTicket.title }}</p>
      <ETextField
        v-model.number="lossAcceptQty"
        type="number"
        :label="t('workshop.lossQtyLabel')"
        hide-details="auto"
      />
      <ETextField
        v-model="lossAcceptActualCost"
        class="mt-3"
        type="number"
        :label="t('workshop.labelActualCostChf')"
        :hint="lossCostSuggestionLabel"
        placeholder="0.00"
        hide-details="auto"
      />
      <p class="workshop-dialog-hint">{{ t('workshop.lossExplanation') }}</p>
      <p v-if="lossAcceptError" class="workshop-dialog-error">{{ lossAcceptError }}</p>
      <template #actions>
        <EButton variant="secondary" size="small" :disabled="isAcceptingLoss" @click="closeLossAcceptModal">
          {{ t('common.cancel') }}
        </EButton>
        <EButton
          variant="danger"
          size="small"
          :loading="isAcceptingLoss"
          :disabled="lossAcceptQty < 1 || (isExternalSelectedTicket && !lossAcceptActualCost)"
          @click="acceptLossTicket"
        >
          {{ isAcceptingLoss ? t('workshop.lossAccepting') : t('workshop.lossBtnAccept') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog v-model="showSendToSupplierModal" :max-width="520" :title="t('workshop.sendToSupplierTitle')">
      <p v-if="selectedTicket" class="workshop-dialog-subtitle">{{ selectedTicket.title }}</p>
      <p class="workshop-dialog-hint">{{ t('workshop.sendToSupplierHint') }}</p>
      <p v-if="selectedTicket?.assigned_to_supplier_company" class="workshop-dialog-hint">
        {{ t('workshop.sendToSupplierTarget', { name: selectedTicket.assigned_to_supplier_company.name }) }}
      </p>
      <ETextField
        v-model="sendEstimatedCost"
        class="mt-3"
        type="number"
        :label="t('workshop.sendToSupplierEstimatedLabel')"
        :hint="t('workshop.sendToSupplierEstimatedHint')"
        placeholder="0.00"
        hide-details="auto"
      />
      <p v-if="sendToSupplierError" class="workshop-dialog-error">{{ sendToSupplierError }}</p>
      <template #actions>
        <EButton variant="secondary" size="small" :disabled="isSendingToSupplier" @click="closeSendToSupplierModal">
          {{ t('common.cancel') }}
        </EButton>
        <EButton
          variant="primary"
          size="small"
          :loading="isSendingToSupplier"
          @click="submitSendToSupplier"
        >
          {{ isSendingToSupplier ? t('workshop.sendToSupplierSending') : t('workshop.sendToSupplierConfirm') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog v-model="showCreateModal" :max-width="580" :retain-focus="false" :title="t('workshop.createTitle')">
          <div class="create-form">
            <ETextField
              v-model="createForm.title"
              :label="t('workshop.createTitleLabel')"
              :placeholder="t('workshop.createTitlePlaceholder')"
              hide-details="auto"
            />
            <div class="form-group">
              <label>{{ t('workshop.createMaterialLabel') }}</label>
              <!-- Ausgewähltes Material anzeigen -->
              <div v-if="selectedMaterial" class="ws-selected-material">
                <span class="ws-mat-type-icon" :title="materialTypeLabel(selectedMaterial.material_type)">
                  {{ materialTypeIcon(selectedMaterial.material_type) }}
                </span>
                <div class="ws-selected-info">
                  <span class="ws-selected-name">{{ selectedMaterial.name }}</span>
                  <span class="ws-selected-meta">
                    <span v-if="selectedSerialLabel">🔢 {{ selectedSerialLabel }}</span>
                    <span v-if="selectedMaterial.barcode_tag">🏷️ {{ selectedMaterial.barcode_tag }}</span>
                    <span v-if="selectedMaterial.category">{{ selectedMaterial.category.name }}</span>
                    <span v-if="!isSelectedMaterialSerialized && createForm.affected_quantity">
                      {{ t('workshop.createAffectedQtyOfStock', { qty: createForm.affected_quantity, stock: selectedMaterial.total_stock }) }}
                    </span>
                    <span v-if="!isSelectedMaterialSerialized" :class="'condition-' + selectedMaterial.condition">{{ conditionLabels[selectedMaterial.condition] || selectedMaterial.condition }}</span>
                  </span>
                </div>
                <button type="button" class="ws-selected-clear" @click="clearSelectedMaterial" :title="t('workshop.createChangeMaterialTitle')">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
              </div>
              <template v-if="isSelectedMaterialSerialized">
                <p class="ws-serial-hint">{{ t('workshop.createSerialHint') }}</p>
                <ESelect
                  v-model="createForm.material_batch_id"
                  :items="serialBatchSelectItems"
                  :label="t('workshop.createSerialLabel')"
                  :placeholder="t('workshop.createSerialPlaceholder')"
                  hide-details="auto"
                  class="mt-2"
                />
              </template>
              <template v-else-if="selectedMaterial">
                <p class="ws-serial-hint">{{ t('workshop.createAffectedQtyHint') }}</p>
                <ETextField
                  v-model.number="createForm.affected_quantity"
                  type="number"
                  min="1"
                  :max="selectedMaterialTotalStock || undefined"
                  :label="t('workshop.createAffectedQtyLabel')"
                  hide-details="auto"
                  class="mt-2"
                />
              </template>
              <GlobalSearchInput
                v-else
                ref="matSearchFieldRef"
                mode="inline"
                pick-on-select
                teleport-dropdown
                :department-id="currentDepartmentId"
                default-type="material"
                v-model="matSearchQuery"
                :placeholder="t('workshop.createSearchPlaceholder')"
                :pick-empty-text="t('workshop.createNoMatches', { query: matSearchQuery })"
                class="ws-create-material-search"
                @select="onMatSearchSuggestionSelect"
              />
            </div>
            <ETextarea
              v-model="createForm.description"
              class="mt-3"
              :label="t('common.description')"
              :placeholder="t('workshop.createDescriptionPlaceholder')"
              rows="3"
              hide-details="auto"
            />
            <p class="create-workflow-hint">{{ t('workshop.createWorkflowHint') }}</p>
          </div>
      <template #actions>
        <EButton variant="secondary" size="small" @click="showCreateModal = false">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!canSubmitCreateTicket"
          @click="handleCreateTicket"
        >
          {{ t('workshop.createSubmit') }}
        </EButton>
      </template>
    </EDialog>

    <PublicQrActionModal
      :open="showWorkshopQrActionModal"
      :label="selectedTicket?.title"
      :code="selectedTicket?.public_code"
      :url="workshopPublicUrl"
      @close="closeWorkshopQrActionModal"
      @add-to-print-cart="handleWorkshopQrAddToPrintCart"
      @print="handleWorkshopQrPrint"
    />

    <WorkshopTriageDialog
      v-model="showTriageDialog"
      v-model:ticket="triageTicket"
      :department-id="currentDepartmentId"
      @triaged="onTicketTriaged"
      @writeoff="onTriageWriteoff"
      @resolve-ok="onTriageResolveOk"
    />
  </PageShell>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDetailTabsStore } from '@/stores/detailTabs'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import {
  getWorkshopTickets,
  getWorkshopTicket,
  createWorkshopTicket,
  updateWorkshopTicket,
  transitionWorkshopTicket,
  setWorkshopTicketPhase,
  sendWorkshopTicketToSupplier,
  getWorkshopStats,
  getWorkshopTicketHistory,
  ensureWorkshopPublicCode,
  uploadWorkshopTicketPhoto,
  type WorkshopTicket,
  type WorkshopStats,
  type WorkshopHistoryEntry,
  type TicketStatus,
  type TicketType,
  type TicketPriority,
} from '@/api/workshop'
import { ticketHasRepairSheet, ticketUsesPartsList } from '@/composables/useWorkshopTriageOptions'
import { getMaterial, type Material, type MaterialBatch } from '@/api/materials'
import GlobalSearchInput from '@/components/common/GlobalSearchInput.vue'
import { useListSearchQueryRoute } from '@/composables/useListSearchQueryRoute'
import { parseSearchQuery, type SearchSuggestion } from '@/composables/useSearchNavigation'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import PublicQrActionModal from '@/components/common/PublicQrActionModal.vue'
import { addPrintCartItem } from '@/api/tasks'
import { printHtmlDocument } from '@/utils/printHtml'
import { resolveWorkshopPublicUrl } from '@/utils/publicQrUrl'
import {
  getTicketDisplayPhase,
  isActivePhase,
  isTerminalPhase,
  KANBAN_PHASES,
  STATS_PHASES,
  type TicketDisplayPhase,
} from '@/utils/workshopPhase'
import { filterMediaPhotos, normalizeMediaPhotos } from '@/api/media'
import type { MediaPhoto } from '@/api/media'
import PhotoGallery from '@/components/media/PhotoGallery.vue'
import PhotoUpload from '@/components/media/PhotoUpload.vue'
import WorkshopTriageDialog from '@/components/workshop/WorkshopTriageDialog.vue'
import WorkshopTicketRepairSheetPanel from '@/components/workshop/WorkshopTicketRepairSheetPanel.vue'
import WorkshopCostSummary from '@/components/workshop/WorkshopCostSummary.vue'
import WorkshopExternalCleaningPanel from '@/components/workshop/WorkshopExternalCleaningPanel.vue'
import RepairPartsList from '@/components/workshop/RepairPartsList.vue'
import WorkshopWorkflowStepper from '@/components/workshop/WorkshopWorkflowStepper.vue'
import WorkshopWriteoffRepurposePanel, {
  type WriteoffRepurposeForm,
} from '@/components/workshop/WorkshopWriteoffRepurposePanel.vue'
import MaterialCreateWizard from '@/components/material/MaterialCreateWizard.vue'
import {
  getWorkflowPrimaryAction,
  hasOpenPurchase,
  hasOrderedPurchase,
} from '@/utils/workshopWorkflow'
import { getWorkshopSettings, DEFAULT_WORKSHOP_SETTINGS } from '@/api/departmentSettings'
import type { WorkshopCostBreakdown } from '@/types/workshopCostSummary'
import { listDepartmentSupplierRepairTemplates } from '@/api/supplierRepairTemplates'
import { normalizeRepairChecklist } from '@/types/repairChecklist'
import {
  estimateExternalCleaningCost,
  formatCleaningCostSuggestion,
  getCleaningServiceKey,
  resolveCleaningServiceOption,
  supplierTemplateToCleaningSheetInput,
} from '@/utils/workshopExternalCleaning'
import {
  estimatePartsMaterialCost,
  formatChfAmount,
  formatRepairPartQuantity,
  getCompletionPartsLines,
  getReceivedPurchasePartsForCompletion,
  getStockPartsForCompletion,
} from '@/utils/workshopPartsCompletion'
import QRCode from 'qrcode'
import PageShell from '@/components/layout/PageShell.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { EButton, EDialog, ESelect, ETextField, ETextarea } from '@/components/form/base'
import '@/styles/workshop-view.css'

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const authStore = useAuthStore()
const detailTabsStore = useDetailTabsStore()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()
const currentDepartmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)

/** Query ?material_id= — aus Material-Detail (Werkstatt nur für dieses Material) */
const materialFilterId = computed(() => {
  const q = route.query.material_id
  return typeof q === 'string' && q.trim() !== '' ? q.trim() : undefined
})
const materialFilterLabel = ref('')

// === State ===
const tickets = ref<WorkshopTicket[]>([])
const stats = ref<WorkshopStats | null>(null)
const isLoading = ref(false)
const viewMode = ref<'kanban' | 'table'>('kanban')
const selectedTicket = ref<WorkshopTicket | null>(null)
const showCreateModal = ref(false)
const showCompleteModal = ref(false)
const showLossAcceptModal = ref(false)
const ticketHistory = ref<WorkshopHistoryEntry[]>([])
const isLoadingHistory = ref(false)
const isAcceptingLoss = ref(false)
const lossAcceptQty = ref(1)
const lossAcceptActualCost = ref('')
const lossAcceptError = ref('')
const showSendToSupplierModal = ref(false)
const sendEstimatedCost = ref('')
const sendToSupplierError = ref('')
const isSendingToSupplier = ref(false)
const showWorkshopQrActionModal = ref(false)
const detailTab = ref<'meldung' | 'arbeit' | 'verlauf'>('meldung')
const showTriageDialog = ref(false)
const triageTicket = ref<WorkshopTicket | null>(null)
const repairSheetSectionRef = ref<HTMLElement | null>(null)
const repairPartsSectionRef = ref<HTMLElement | null>(null)
const repairPartsListRef = ref<InstanceType<typeof RepairPartsList> | null>(null)
const detailDialogBodyRef = ref<HTMLElement | null>(null)

const detailDialogOpen = computed({
  // Detail-Dialog erst nach Triage — sonst liegt das alte Modal unter dem Triage-Dialog
  get: () => selectedTicket.value !== null && !showTriageDialog.value,
  set: (open: boolean) => {
    if (!open) closeSelectedTicketDetail()
  },
})

const typeFilterItems = computed(() => [
  { title: t('workshop.filterAllTypes'), value: '' },
  { title: t('workshop.typeRepair'), value: 'repair' },
  { title: t('workshop.typeInspection'), value: 'inspection' },
  { title: t('workshop.typeWriteoff'), value: 'writeoff' },
  { title: t('workshop.typeCleaning'), value: 'cleaning' },
])

const originFilterItems = computed(() => [
  { title: t('workshop.filterAllSources'), value: '' },
  { title: t('workshop.originLossOnly'), value: 'loss' },
  { title: t('workshop.originRepairOnly'), value: 'repair' },
  { title: t('workshop.originDamageOnly'), value: 'damage' },
  { title: t('workshop.originConsumptionOnly'), value: 'consumption' },
  { title: t('workshop.originManualOnly'), value: 'manual' },
])

const priorityFilterItems = computed(() => [
  { title: t('workshop.filterAllPriorities'), value: '' },
  { title: t('workshop.priorityUrgent'), value: 'urgent' },
  { title: t('workshop.priorityHigh'), value: 'high' },
  { title: t('workshop.priorityNormal'), value: 'normal' },
  { title: t('workshop.priorityLow'), value: 'low' },
])

const departmentRole = computed(() => String(authStore.currentDepartmentRole || 'u').toLowerCase())
const canManageWorkshopQr = computed(() =>
  ['mw', 'dc', 'matwart', 'depchef'].includes(departmentRole.value)
)
const canUploadWorkshopPhotos = computed(() => canManageWorkshopQr.value)

const ticketPhotos = computed((): MediaPhoto[] => {
  return filterMediaPhotos(selectedTicket.value?.photos)
})

const issueReportPhotos = computed((): MediaPhoto[] => {
  const report = selectedTicket.value?.issue_report
  if (!report) return []
  return normalizeMediaPhotos(report.photos, report.photo_url)
})
const workshopPublicUrl = computed(() =>
  resolveWorkshopPublicUrl(selectedTicket.value?.public_url, selectedTicket.value?.public_code),
)
// Filter
const searchQuery = ref('')
const filterType = ref<TicketType | ''>('')
const filterOriginIssueType = ref<'repair' | 'loss' | 'damage' | 'consumption' | 'manual' | ''>('')
const filterPriority = ref<TicketPriority | ''>('')
const quickFilter = ref<'waiting_quote' | 'missing_estimated_cost' | ''>('')

useListSearchQueryRoute({
  searchQuery,
  route,
  router,
  pathIncludes: '/workshop',
  isListView: () => true,
})

// Create Form
const createForm = ref({
  title: '',
  material_item_id: '',
  material_batch_id: '' as string,
  affected_quantity: 1,
  type: 'repair' as TicketType,
  priority: 'normal' as TicketPriority,
  description: '',
  estimated_cost: '',
})

const isSelectedMaterialSerialized = computed(
  () => selectedMaterial.value?.tracking_type === 'serialized'
)

const serialBatchSelectItems = computed(() => {
  const batches = selectedMaterial.value?.batches ?? []
  return batches
    .filter((batch) => batch.status === 'active' && (batch.qty ?? 0) > 0)
    .map((batch) => {
      const serial = batch.serial_number?.trim()
      const label = batch.label?.trim()
      const title = serial || label || batch.id
      const subtitle = serial && label ? label : undefined
      return {
        title: subtitle ? `${title} · ${subtitle}` : title,
        value: batch.id,
      }
    })
})

const selectedMaterialTotalStock = computed(() => selectedMaterial.value?.total_stock ?? 0)

const canSubmitCreateTicket = computed(() => {
  if (!createForm.value.title || !createForm.value.material_item_id) return false
  if (isSelectedMaterialSerialized.value && !createForm.value.material_batch_id) return false
  if (!isSelectedMaterialSerialized.value) {
    const qty = Number(createForm.value.affected_quantity)
    if (!Number.isFinite(qty) || qty < 1) return false
    const stock = selectedMaterialTotalStock.value
    if (stock > 0 && qty > stock) return false
  }
  return true
})

const selectedSerialLabel = computed(() => {
  const batchId = createForm.value.material_batch_id
  if (!batchId || !selectedMaterial.value?.batches) return ''
  const batch = selectedMaterial.value.batches.find((item) => item.id === batchId)
  return batch ? formatBatchLabel(batch) : ''
})

function createEmptyWriteoffRepurpose(): WriteoffRepurposeForm {
  return {
    enabled: false,
    material_item_id: '',
    material_name: '',
    quantity: null,
    quantity_unit: 'Stk',
    stock_already_booked: false,
  }
}

const showRepurposeMaterialWizard = ref(false)
const repurposeWizardKey = ref(0)

// Complete Form
const completeForm = ref({
  resolution_action: '' as string,
  actual_cost: '',
  resolution_notes: '',
  parts_surplus: {} as Record<string, number>,
  writeoff_repurpose: createEmptyWriteoffRepurpose(),
})
const completeCostBreakdown = ref<WorkshopCostBreakdown | null>(null)
const workshopHourlyRate = ref(DEFAULT_WORKSHOP_SETTINGS.hourlyRateChf)

// Material-Auswahl (globale Suche)
const matSearchQuery = ref('')
const selectedMaterial = ref<Material | null>(null)
const matSearchFieldRef = ref<InstanceType<typeof GlobalSearchInput> | null>(null)

// === Labels (vue-i18n) ===
const statsPhases = STATS_PHASES

const phaseLabels = computed(() => ({
  triage: t('workshop.phase.triage'),
  planning: t('workshop.phase.planning'),
  ordered: t('workshop.phase.ordered'),
  ready: t('workshop.phase.ready'),
  in_progress: t('workshop.phase.in_progress'),
  awaiting_quote: t('workshop.phase.awaiting_quote'),
  completed: t('workshop.phase.completed'),
  cancelled: t('workshop.phase.cancelled'),
}))

function getTicketPhaseLabel(ticket: WorkshopTicket): string {
  const phase = getTicketDisplayPhase(ticket)
  return phaseLabels.value[phase] ?? ticket.phase_label ?? phase
}

const priorityLabels = computed(() => ({
  low: t('workshop.priority.low'),
  normal: t('workshop.priority.normal'),
  high: t('workshop.priority.high'),
  urgent: t('workshop.priority.urgent'),
}))

const typeLabels = computed(() => ({
  repair: t('workshop.ticketType.repair'),
  inspection: t('workshop.ticketType.inspection'),
  writeoff: t('workshop.ticketType.writeoff'),
  cleaning: t('workshop.ticketType.cleaning'),
}))

const conditionLabels = computed(() => ({
  ok: t('workshop.condition.ok'),
  defect: t('workshop.condition.defect'),
  repair: t('workshop.condition.repair'),
  lost: t('workshop.condition.lost'),
}))

const resolutionLabels = computed(() => ({
  repaired: t('workshop.resolution.repaired'),
  ok: t('workshop.resolution.ok'),
  writeoff: t('workshop.resolution.writeoff'),
}))

const issueTypeLabels = computed(() => ({
  repair: t('workshop.simpleIssue.repair'),
  damage: t('workshop.simpleIssue.damage'),
  loss: t('workshop.simpleIssue.loss'),
  consumption: t('workshop.simpleIssue.consumption'),
}))

function getIssueOriginBadgeLabel(issueType: string): string {
  const map: Record<string, string> = {
    repair: t('workshop.issueOrigin.repair'),
    damage: t('workshop.issueOrigin.damage'),
    loss: t('workshop.issueOrigin.loss'),
    consumption: t('workshop.issueOrigin.consumption'),
  }
  return map[issueType] || t('workshop.issueOrigin.fallback')
}

function getIssueOriginBadgeClass(issueType: string): string {
  const map: Record<string, string> = {
    repair: 'repair',
    damage: 'damage',
    loss: 'loss',
    consumption: 'consumption',
  }
  return map[issueType] || 'neutral'
}

function isLossOriginTicket(ticket: WorkshopTicket): boolean {
  return ticket.origin_issue_type === 'loss' || ticket.issue_report?.type === 'loss'
}

// === Computed ===
const filteredTickets = computed(() => {
  let result = [...tickets.value]

  if (searchQuery.value) {
    const parsed = parseSearchQuery(searchQuery.value, 'reparatur')
    const q = (parsed?.term ?? searchQuery.value).toLowerCase()
    if (q) {
      result = result.filter(t =>
        t.title.toLowerCase().includes(q) ||
        t.material_item.name.toLowerCase().includes(q) ||
        (t.description && t.description.toLowerCase().includes(q))
      )
    }
  }

  if (filterType.value) {
    result = result.filter(t => t.type === filterType.value)
  }

  if (filterOriginIssueType.value) {
    if (filterOriginIssueType.value === 'manual') {
      result = result.filter(t => !t.origin_issue_type)
    } else {
      result = result.filter(t => t.origin_issue_type === filterOriginIssueType.value)
    }
  }

  if (filterPriority.value) {
    result = result.filter(t => t.priority === filterPriority.value)
  }

  if (quickFilter.value === 'waiting_quote') {
    result = result.filter(t => getTicketDisplayPhase(t) === 'awaiting_quote' && t.activity_type === 'external')
  } else if (quickFilter.value === 'missing_estimated_cost') {
    result = result.filter(t =>
      t.activity_type === 'external' &&
      isActivePhase(t) &&
      ['repair', 'writeoff'].includes(t.type) &&
      t.estimated_cost == null
    )
  }

  return result
})

function getColumnTickets(phase: TicketDisplayPhase): WorkshopTicket[] {
  return filteredTickets.value.filter(t => getTicketDisplayPhase(t) === phase)
}

const kanbanColumns = computed(() =>
  KANBAN_PHASES.map((phase) => {
    const ticketsInPhase = getColumnTickets(phase)
    return {
      phase,
      label: phaseLabels.value[phase],
      tickets: ticketsInPhase,
      count: ticketsInPhase.length,
    }
  }).filter((col) => col.count > 0),
)

const cancelledTickets = computed(() => {
  return filteredTickets.value.filter(t => getTicketDisplayPhase(t) === 'cancelled')
})

const isExternalSelectedTicket = computed(() => {
  return selectedTicket.value?.activity?.type === 'external'
})

const canSendToSupplier = computed(() => {
  const ticket = selectedTicket.value
  if (!ticket) return false
  if (!['external_repair', 'external_cleaning'].includes(ticket.strategy)) return false
  if (!ticket.assigned_to_supplier_company?.id) return false
  if (isTerminalPhase(ticket)) return false
  if (ticket.strategy === 'external_cleaning') {
    const serviceKey = (ticket.repair_checklist as { cleaning_service_key?: string } | null)?.cleaning_service_key
    if (!serviceKey) return false
  }
  return ticket.phase === 'planning' || ticket.phase === null
})

const workflowPrimaryAction = computed(() => {
  if (!selectedTicket.value) return null
  return getWorkflowPrimaryAction(selectedTicket.value)
})

const isAdvancingPhase = ref(false)

const lossCostSuggestion = computed(() => {
  if (!selectedTicket.value) return 0
  const qty = Math.max(1, Number(lossAcceptQty.value || 1))
  const salePrice = Number(selectedTicket.value.material_item?.sale_price || 0)
  if (salePrice > 0) return qty * salePrice
  const est = Number(selectedTicket.value.estimated_cost || 0)
  if (est > 0) return est
  return 0
})

const lossCostSuggestionLabel = computed(() => {
  const amount = Number(lossCostSuggestion.value || 0)
  return t('workshop.lossCostSuggestion', { amount: `CHF ${amount.toFixed(2)}` })
})

const completionCostSummaryVisible = computed(() => {
  if (!selectedTicket.value) return false
  return ['repaired', 'ok'].includes(completeForm.value.resolution_action || '')
})

const completionCostMissing = computed(() => {
  if (!isExternalSelectedTicket.value) return false
  if (!['repaired', 'writeoff'].includes(completeForm.value.resolution_action || '')) return false
  return !completeForm.value.actual_cost
})

const completionStockParts = computed(() =>
  selectedTicket.value ? getStockPartsForCompletion(selectedTicket.value) : [],
)

const completionPurchaseParts = computed(() =>
  selectedTicket.value ? getReceivedPurchasePartsForCompletion(selectedTicket.value) : [],
)

const completionPartsLines = computed(() =>
  selectedTicket.value ? getCompletionPartsLines(selectedTicket.value) : [],
)

const completionMaterialCost = computed(() => estimatePartsMaterialCost(completionPartsLines.value))

const completionPartsWarningVisible = computed(() => {
  if (!selectedTicket.value) return false
  if (selectedTicket.value.strategy !== 'internal_repair') return false
  if (!['repaired', 'ok'].includes(completeForm.value.resolution_action || '')) return false
  return completionPartsLines.value.length > 0
})

const completionSurplusVisible = computed(() => {
  if (!completionPartsWarningVisible.value) return false
  return completionPurchaseParts.value.length > 0
})

const completionWriteoffPartsHintVisible = computed(() => {
  if (!selectedTicket.value) return false
  if (selectedTicket.value.strategy !== 'internal_repair') return false
  if (completeForm.value.resolution_action !== 'writeoff') return false
  return (selectedTicket.value.parts_used?.length ?? 0) > 0
})

const completionRepurposeInvalid = computed(() => {
  const repurpose = completeForm.value.writeoff_repurpose
  if (completeForm.value.resolution_action !== 'writeoff' || !repurpose.enabled) return false
  if (!repurpose.material_item_id) return true
  if (repurpose.stock_already_booked) return false
  const qty = Number(repurpose.quantity)
  return !Number.isFinite(qty) || qty <= 0
})

const completionMaterialCostHint = computed(() => {
  if (!completionPartsWarningVisible.value || completionMaterialCost.value <= 0) return undefined
  return t('workshop.completePartsMaterialCost', { amount: formatChfAmount(completionMaterialCost.value) })
})

// === Methods ===
async function loadData() {
  if (!currentDepartmentId.value) return

  isLoading.value = true
  try {
    const listOpts = materialFilterId.value ? { material_item_id: materialFilterId.value } : undefined
    const [ticketsData, statsData, workshopSettings] = await Promise.all([
      getWorkshopTickets(currentDepartmentId.value, listOpts),
      getWorkshopStats(currentDepartmentId.value),
      getWorkshopSettings(currentDepartmentId.value).catch(() => DEFAULT_WORKSHOP_SETTINGS),
    ])
    workshopHourlyRate.value = workshopSettings.hourlyRateChf
    tickets.value = ticketsData
    stats.value = statsData
    if (materialFilterId.value) {
      try {
        const m = await getMaterial(materialFilterId.value)
        materialFilterLabel.value = m.name || ''
      } catch {
        materialFilterLabel.value = ''
      }
    } else {
      materialFilterLabel.value = ''
    }
  } catch (err: any) {
    console.error('Failed to load workshop data:', err)
  } finally {
    isLoading.value = false
  }

  await tryOpenTicketFromQuery()
}

function clearMaterialFilter() {
  const nextQuery = { ...route.query }
  delete (nextQuery as Record<string, unknown>).material_id
  router.replace({ path: route.path, query: nextQuery })
}

async function ensureTicketPublicCodeQuiet(ticket: WorkshopTicket): Promise<WorkshopTicket> {
  if (!canManageWorkshopQr.value || resolveWorkshopPublicUrl(ticket.public_url, ticket.public_code)) {
    return ticket
  }
  try {
    return await ensureWorkshopPublicCode(ticket.id)
  } catch (err) {
    console.warn('Workshop public code ensure failed:', err)
    return ticket
  }
}

function openWorkshopQrActionModal() {
  showWorkshopQrActionModal.value = true
}

function closeWorkshopQrActionModal() {
  showWorkshopQrActionModal.value = false
}

async function handleWorkshopQrAddToPrintCart() {
  const ticket = selectedTicket.value
  const url = workshopPublicUrl.value
  if (!ticket?.id || !url || !currentDepartmentId.value) {
    toast.info(t('workshop.toastNoPublicLink'))
    return
  }
  try {
    const result = await addPrintCartItem({
      department_id: currentDepartmentId.value,
      entity_type: 'workshop',
      entity_id: ticket.id,
      label: ticket.title || t('workshop.title'),
      public_code: ticket.public_code || null,
      public_url: url,
    })
    toast.success(
      result.created ? t('workshop.toastPrintCartAdded') : t('workshop.toastPrintCartAlready')
    )
    closeWorkshopQrActionModal()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('workshop.errPrintCartAdd'))
  }
}

async function handleWorkshopQrPrint() {
  const ticket = selectedTicket.value
  const url = workshopPublicUrl.value
  if (!url || !ticket) {
    toast.info(t('workshop.toastNoPublicLink'))
    return
  }
  const qrDataUrl = await QRCode.toDataURL(url, { width: 300, margin: 1 })
  const safeTitle = String(ticket.title || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
  const safeCode = String(ticket.public_code || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
  printHtmlDocument(`<!doctype html>
<html><head><meta charset="utf-8" /><title>${safeTitle}</title>
<style>body{font-family:Arial,sans-serif;text-align:center;padding:24px}img{width:280px;height:280px}.title{margin-top:12px;font-weight:700}.code{font-family:monospace;color:#64748b;margin-top:6px}</style>
</head><body>
<img src="${qrDataUrl}" alt="QR" />
<div class="title">${safeTitle}</div>
<div class="code">${safeCode}</div>
</body></html>`)
  closeWorkshopQrActionModal()
}

function registerWorkshopDetailTab(ticket: WorkshopTicket) {
  const dept = currentDepartmentId.value
  if (!dept || !ticket.id) return
  detailTabsStore.addOrUpdateTab({
    id: ticket.id,
    type: 'workshop',
    label: ticket.title?.trim() || t('workshop.fallbackTabLabel', { id: ticket.id }),
    departmentId: dept,
    path: `/${dept}/workshop?ticket=${encodeURIComponent(ticket.id)}`,
  })
}

async function openTicketDetail(ticket: WorkshopTicket) {
  registerWorkshopDetailTab(ticket)
  if (route.query.ticket !== ticket.id) {
    router.replace({ path: route.path, query: { ...route.query, ticket: ticket.id } })
  }
  try {
    // Lade Ticket-Details und History parallel
    selectedTicket.value = ticket // Sofort zeigen
    isLoadingHistory.value = true

    const [detailed, history] = await Promise.all([
      getWorkshopTicket(ticket.id),
      getWorkshopTicketHistory(ticket.id),
    ])

    selectedTicket.value = await ensureTicketPublicCodeQuiet(detailed)
    registerWorkshopDetailTab(selectedTicket.value)
    ticketHistory.value = history
    detailTab.value = selectedTicket.value.strategy === 'triage' ? 'meldung' : 'arbeit'

    if (
      detailed.strategy === 'triage' &&
      detailed.status !== 'completed' &&
      detailed.status !== 'cancelled'
    ) {
      openTriageDialog(detailed)
    }
  } catch (err) {
    console.error('Failed to load ticket details:', err)
    selectedTicket.value = ticket
    ticketHistory.value = []
  } finally {
    isLoadingHistory.value = false
  }
}

function openTriageDialog(ticket: WorkshopTicket) {
  triageTicket.value = ticket
  showTriageDialog.value = true
}

function workflowHintKey(ticket: WorkshopTicket): string | null {
  if (ticket.strategy === 'internal_repair') {
    const phase = getTicketDisplayPhase(ticket)
    if (phase === 'planning') {
      if (hasOrderedPurchase(ticket)) return 'workshop.workflow.hintWaitingDelivery'
      if (hasOpenPurchase(ticket)) return 'workshop.workflow.hintOrderParts'
      return ticketHasRepairSheet(ticket)
        ? 'workshop.workflow.hintPlanSheet'
        : 'workshop.workflow.hintPlanMaterial'
    }
    if (phase === 'ordered') return 'workshop.workflow.hintWaitingDelivery'
    if (phase === 'ready') return 'workshop.workflow.hintStartRepair'
    if (phase === 'in_progress') return 'workshop.workflow.hintCompleteRepair'
    return null
  }

  switch (ticket.strategy) {
    case 'external_repair':
      return ticketHasRepairSheet(ticket)
        ? 'workshop.postTriage.hintExternal'
        : 'workshop.postTriage.hintExternalNoSheet'
    case 'external_cleaning':
      return 'workshop.postTriage.hintExternalCleaning'
    case 'inspection':
      return 'workshop.postTriage.hintInspection'
    default:
      return null
  }
}

function workflowHintText(ticket: WorkshopTicket): string | null {
  const key = workflowHintKey(ticket)
  return key ? t(key) : null
}

async function focusRepairPartsSection() {
  detailTab.value = 'arbeit'
  await nextTick()
  repairPartsSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function advanceTicketPhase(phase: 'ready' | 'in_progress') {
  if (!selectedTicket.value) return

  isAdvancingPhase.value = true
  try {
    const partsSaved = (await repairPartsListRef.value?.saveIfDirty?.()) ?? true
    if (!partsSaved) return

    const updated = await setWorkshopTicketPhase(selectedTicket.value.id, { phase })
    selectedTicket.value = mergeTicketDetailFields(updated, selectedTicket.value)
    const idx = tickets.value.findIndex((t) => t.id === updated.id)
    if (idx !== -1) {
      tickets.value[idx] = mergeTicketDetailFields(updated, tickets.value[idx])
    }
    if (currentDepartmentId.value) {
      stats.value = await getWorkshopStats(currentDepartmentId.value)
    }
    detailTab.value = 'arbeit'
    toast.success(
      phase === 'ready'
        ? t('workshop.toast.phaseReady')
        : t('workshop.toast.phaseInProgress'),
    )
  } catch (err: unknown) {
    const message = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
    toast.error(message || t('workshop.toast.phaseAdvanceError'))
  } finally {
    isAdvancingPhase.value = false
  }
}

async function focusPostTriageWork(ticket: WorkshopTicket) {
  detailTab.value = 'arbeit'
  await nextTick()
  const target =
    ticket.strategy === 'internal_repair' && ticketHasRepairSheet(ticket)
      ? repairSheetSectionRef.value
      : ticketUsesPartsList(ticket)
        ? repairPartsSectionRef.value
        : detailDialogBodyRef.value
  target?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function refreshTicketAfterTriage(ticketId: string) {
  const [detailed, history] = await Promise.all([
    getWorkshopTicket(ticketId),
    getWorkshopTicketHistory(ticketId),
  ])
  selectedTicket.value = await ensureTicketPublicCodeQuiet(detailed)
  registerWorkshopDetailTab(selectedTicket.value)
  ticketHistory.value = history
  detailTab.value = 'arbeit'

  const idx = tickets.value.findIndex((t) => t.id === ticketId)
  if (idx !== -1) {
    tickets.value[idx] = { ...tickets.value[idx], ...detailed }
  }

  if (currentDepartmentId.value) {
    stats.value = await getWorkshopStats(currentDepartmentId.value)
  }
}

async function onTicketTriaged(ticket: WorkshopTicket) {
  try {
    await refreshTicketAfterTriage(ticket.id)
    if (selectedTicket.value) {
      await focusPostTriageWork(selectedTicket.value)
    }
    toast.success(t('workshop.triage.toastDone'))
  } catch (err) {
    console.error('Failed to refresh after triage:', err)
  }
}

async function onTriageResolveOk(ticket: WorkshopTicket) {
  try {
    await loadData()
    await refreshTicketAfterTriage(ticket.id)
    toast.success(t('workshop.triage.toastOk'))
  } catch (err) {
    console.error('Failed to refresh after resolve ok:', err)
  }
}

function mergeTicketDetailFields(
  incoming: WorkshopTicket,
  previous?: WorkshopTicket | null,
): WorkshopTicket {
  if (!previous || previous.id !== incoming.id) return incoming
  return {
    ...incoming,
    parts_used: incoming.parts_used ?? previous.parts_used,
    repair_checklist: incoming.repair_checklist ?? previous.repair_checklist,
    photos: incoming.photos ?? previous.photos,
    activity: incoming.activity ?? previous.activity,
    issue_report: incoming.issue_report ?? previous.issue_report,
  }
}

function onRepairSheetUpdated(ticket: WorkshopTicket) {
  selectedTicket.value = mergeTicketDetailFields(ticket, selectedTicket.value)
  const idx = tickets.value.findIndex((t) => t.id === ticket.id)
  if (idx !== -1) {
    tickets.value[idx] = mergeTicketDetailFields(ticket, tickets.value[idx])
  }
}

function onTriageWriteoff(ticket: WorkshopTicket) {
  selectedTicket.value = ticket
  if (isLossOriginTicket(ticket)) {
    openLossAcceptModal()
    return
  }
  completeForm.value = {
    resolution_action: 'writeoff',
    actual_cost: '',
    resolution_notes: '',
    parts_surplus: {},
    writeoff_repurpose: createEmptyWriteoffRepurpose(),
  }
  showCompleteModal.value = true
}

async function uploadTicketPhoto(file: File) {
  const ticket = selectedTicket.value
  if (!ticket) {
    throw new Error(t('workshop.uploadPhotoError'))
  }
  const photos = await uploadWorkshopTicketPhoto(ticket.id, file)
  return { photos, ticketId: ticket.id }
}

function onTicketPhotoUploaded(payload: unknown) {
  const result = payload as { photos: MediaPhoto[]; ticketId: string }
  const ticket = selectedTicket.value
  if (!ticket || ticket.id !== result.ticketId) return
  selectedTicket.value = { ...ticket, photos: result.photos }
  toast.success(t('media.uploadSuccess'))
}

function onTicketPhotoError(message: string) {
  toast.error(message || t('media.uploadError'))
}

async function tryOpenTicketFromQuery() {
  const queryTicketId = route.query.ticket
  if (!queryTicketId || typeof queryTicketId !== 'string') return
  if (selectedTicket.value?.id === queryTicketId) return

  const listTicket = tickets.value.find(t => t.id === queryTicketId)
  if (listTicket) {
    await openTicketDetail(listTicket)
    return
  }

  try {
    // Fallback: Ticket direkt laden, falls es im aktuellen Listen-Filter nicht enthalten ist
    const detailed = await getWorkshopTicket(queryTicketId)
    await openTicketDetail(detailed)
  } catch (err) {
    console.error('Deep-link Ticket nicht gefunden:', err)
  }
}

function closeSelectedTicketDetail() {
  // Modal schliessen: Tab im Header bleibt (nur × im Header entfernt Chip)
  selectedTicket.value = null
  ticketHistory.value = []
  detailTab.value = 'meldung'
  if (route.query.ticket) {
    const nextQuery = { ...route.query }
    delete nextQuery.ticket
    router.replace({ path: route.path, query: nextQuery })
  }
}

function applyQuickFilterFromRoute() {
  const qf = route.query.qf
  if (qf === 'waiting_quote' || qf === 'missing_estimated_cost') {
    quickFilter.value = qf
  } else {
    quickFilter.value = ''
  }
}

async function handleCreateTicket() {
  if (!canSubmitCreateTicket.value) return

  try {
    const created = await createWorkshopTicket({
      department_id: currentDepartmentId.value,
      material_item_id: createForm.value.material_item_id,
      material_batch_id: createForm.value.material_batch_id || undefined,
      affected_quantity: isSelectedMaterialSerialized.value
        ? undefined
        : createForm.value.affected_quantity,
      title: createForm.value.title,
      type: 'repair',
      priority: 'normal',
      description: createForm.value.description || undefined,
    })

    createForm.value = {
      title: '',
      material_item_id: '',
      material_batch_id: '',
      affected_quantity: 1,
      type: 'repair',
      priority: 'normal',
      description: '',
      estimated_cost: '',
    }
    selectedMaterial.value = null
    matSearchQuery.value = ''
    showCreateModal.value = false
    await loadData()

    const withQr = await ensureTicketPublicCodeQuiet(created)
    registerWorkshopDetailTab(withQr)
    if (route.query.ticket !== withQr.id) {
      router.replace({ path: route.path, query: { ...route.query, ticket: withQr.id } })
    }
    selectedTicket.value = withQr
    detailTab.value = 'meldung'
    openTriageDialog(withQr)
    toast.success(t('workshop.toast.createdTriageNext'))
  } catch (err: any) {
    console.error('Failed to create ticket:', err)
    toast.error(t('workshop.toast.createError') + ' ' + (err.response?.data?.error || err.message))
  }
}

async function transitionTicket(ticketId: string, newStatus: TicketStatus) {
  try {
    const updated = await transitionWorkshopTicket(ticketId, { status: newStatus })

    // Update in der Liste
    const idx = tickets.value.findIndex(t => t.id === ticketId)
    if (idx !== -1) {
      tickets.value[idx] = { ...tickets.value[idx], ...updated }
    }

    // Detail-Ansicht + History aktualisieren
    if (selectedTicket.value?.id === ticketId) {
      const [detailed, history] = await Promise.all([
        getWorkshopTicket(ticketId),
        getWorkshopTicketHistory(ticketId),
      ])
      selectedTicket.value = detailed
      ticketHistory.value = history
    }

    // Stats neu laden
    if (currentDepartmentId.value) {
      stats.value = await getWorkshopStats(currentDepartmentId.value)
    }
  } catch (err: any) {
    console.error('Transition failed:', err)
    toast.error(t('workshop.toast.transitionError') + ' ' + (err.response?.data?.error || err.message))
  }
}

const repurposeWizardSourceNote = computed(() => {
  if (!selectedTicket.value) return ''
  return t('workshop.writeoffRepurpose.wizardSourceNote', {
    title: selectedTicket.value.title,
    id: selectedTicket.value.id,
  })
})

function openRepurposeMaterialWizard() {
  if (!selectedTicket.value) return
  completeForm.value.writeoff_repurpose.enabled = true
  repurposeWizardKey.value += 1
  showRepurposeMaterialWizard.value = true
}

function onRepurposeMaterialCreated(material: Material) {
  showRepurposeMaterialWizard.value = false
  const bookedQty = Number(material.total_stock ?? material.available ?? material.in_warehouse ?? 0)
  const packUnit = (material.pack_unit || '').trim()
  completeForm.value.writeoff_repurpose = {
    ...completeForm.value.writeoff_repurpose,
    enabled: true,
    material_item_id: material.id,
    material_name: material.name,
    stock_already_booked: bookedQty > 0,
    quantity: bookedQty > 0 ? null : completeForm.value.writeoff_repurpose.quantity,
    quantity_unit: packUnit || completeForm.value.writeoff_repurpose.quantity_unit || 'Stk',
  }
}

function openCompleteModal() {
  if (!selectedTicket.value) return
  const partsCost = estimatePartsMaterialCost(getStockPartsForCompletion(selectedTicket.value))
  const surplus: Record<string, number> = {}
  for (const line of getReceivedPurchasePartsForCompletion(selectedTicket.value)) {
    surplus[line.id] = 0
  }
  completeForm.value = {
    resolution_action: 'repaired',
    actual_cost: partsCost > 0 ? formatChfAmount(partsCost) : '',
    resolution_notes: '',
    parts_surplus: surplus,
    writeoff_repurpose: createEmptyWriteoffRepurpose(),
  }
  completeCostBreakdown.value = null
  showCompleteModal.value = true
}

async function completeTicket() {
  if (!selectedTicket.value || !completeForm.value.resolution_action) return

  try {
    const surplusPayload: Record<string, number> = {}
    for (const [lineId, qty] of Object.entries(completeForm.value.parts_surplus)) {
      const value = Number(qty)
      if (Number.isFinite(value) && value > 0) {
        surplusPayload[lineId] = value
      }
    }

    const repurpose = completeForm.value.writeoff_repurpose
    const writeoffRepurposePayload =
      completeForm.value.resolution_action === 'writeoff'
      && repurpose.enabled
      && repurpose.material_item_id
        ? {
            material_item_id: repurpose.material_item_id,
            quantity: repurpose.stock_already_booked ? undefined : Number(repurpose.quantity),
            quantity_unit: repurpose.quantity_unit || undefined,
            stock_already_booked: repurpose.stock_already_booked || undefined,
            unit_cost: selectedTicket.value.material_item.reference_purchase_unit_chf || undefined,
          }
        : undefined

    await transitionWorkshopTicket(selectedTicket.value.id, {
      status: 'completed',
      resolution_action: completeForm.value.resolution_action as any,
      resolution_notes: completeForm.value.resolution_notes || undefined,
      actual_cost: completeForm.value.actual_cost || undefined,
      cost_breakdown: completeCostBreakdown.value ?? undefined,
      parts_surplus: Object.keys(surplusPayload).length ? surplusPayload : undefined,
      writeoff_repurpose: writeoffRepurposePayload,
    })

    showCompleteModal.value = false
    completeForm.value = {
      resolution_action: '',
      actual_cost: '',
      resolution_notes: '',
      parts_surplus: {},
      writeoff_repurpose: createEmptyWriteoffRepurpose(),
    }
    completeCostBreakdown.value = null

    // Alles neu laden
    await loadData()

    // Detail + History aktualisieren
    if (selectedTicket.value) {
      const [detailed, history] = await Promise.all([
        getWorkshopTicket(selectedTicket.value.id),
        getWorkshopTicketHistory(selectedTicket.value.id),
      ])
      selectedTicket.value = detailed
      ticketHistory.value = history
    }
    toast.success(t('workshop.toast.completed'))
  } catch (err: any) {
    console.error('Complete failed:', err)
    toast.error(t('workshop.toast.completeError') + ' ' + (err.response?.data?.error || err.message))
  }
}

async function requestCancelSelectedTicket() {
  if (!selectedTicket.value) return

  const ok = await confirmDialog({
    title: t('workshop.cancelConfirmTitle'),
    message: t('workshop.cancelConfirmMessage', { title: selectedTicket.value.title }),
    confirmText: t('workshop.cancelConfirmAction'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return

  await cancelSelectedTicket()
}

async function cancelSelectedTicket() {
  if (!selectedTicket.value) return
  const ticketId = selectedTicket.value.id

  try {
    await transitionWorkshopTicket(ticketId, { status: 'cancelled' })
    closeSelectedTicketDetail()
    await loadData()
    toast.info(t('workshop.toast.cancelled'))
  } catch (err: any) {
    console.error('Cancel failed:', err)
    toast.error(t('workshop.toast.cancelError') + ' ' + (err.response?.data?.error || err.message))
  }
}

function openLossAcceptModal() {
  if (!selectedTicket.value) return
  lossAcceptQty.value = Math.max(1, selectedTicket.value.issue_report?.quantity || 1)
  lossAcceptActualCost.value = Number(lossCostSuggestion.value || 0).toFixed(2)
  lossAcceptError.value = ''
  showLossAcceptModal.value = true
}

function closeLossAcceptModal() {
  showLossAcceptModal.value = false
  lossAcceptError.value = ''
}

async function openSendToSupplierModal() {
  if (!selectedTicket.value) return
  sendEstimatedCost.value = selectedTicket.value.estimated_cost || ''
  if (
    selectedTicket.value.strategy === 'external_cleaning'
    && !sendEstimatedCost.value
    && selectedTicket.value.assigned_to_supplier_company?.id
  ) {
    try {
      const templates = await listDepartmentSupplierRepairTemplates(
        currentDepartmentId.value,
        selectedTicket.value.assigned_to_supplier_company.id,
      )
      const serviceKey = getCleaningServiceKey(selectedTicket.value.repair_checklist)
      const service = resolveCleaningServiceOption(templates, serviceKey)
      const templateMatch = service
        ? templates.find((tpl) => tpl.template_key === service.template_key)
        : null
      const sheetTemplate = templateMatch && selectedTicket.value.material_item.repair_template_key
        ? supplierTemplateToCleaningSheetInput(templateMatch)
        : null
      const checklist = sheetTemplate
        ? normalizeRepairChecklist(selectedTicket.value.repair_checklist, sheetTemplate)
        : null
      const total = estimateExternalCleaningCost(service, sheetTemplate, checklist)
      sendEstimatedCost.value = formatCleaningCostSuggestion(total)
    } catch (err) {
      console.error('Failed to prefill cleaning estimate:', err)
    }
  }
  sendToSupplierError.value = ''
  showSendToSupplierModal.value = true
}

function closeSendToSupplierModal() {
  showSendToSupplierModal.value = false
  sendToSupplierError.value = ''
}

async function submitSendToSupplier() {
  if (!selectedTicket.value) return
  isSendingToSupplier.value = true
  sendToSupplierError.value = ''
  try {
    const updated = await sendWorkshopTicketToSupplier(selectedTicket.value.id, {
      estimated_cost: sendEstimatedCost.value.trim() || undefined,
    })
    selectedTicket.value = updated
    showSendToSupplierModal.value = false
    await loadData()
    toast.success(t('workshop.toast.sentToSupplier'))
  } catch (err: any) {
    sendToSupplierError.value = err?.response?.data?.error || t('workshop.toast.sendToSupplierError')
  } finally {
    isSendingToSupplier.value = false
  }
}

async function acceptLossTicket() {
  if (!selectedTicket.value) return
  if (!isLossOriginTicket(selectedTicket.value)) return

  try {
    isAcceptingLoss.value = true
    lossAcceptError.value = ''
    const ticketId = selectedTicket.value.id
    const writeoffQty = Math.max(1, Number(lossAcceptQty.value || 1))

    // Workflow-Regel: open -> in_progress -> completed
    if (selectedTicket.value.status === 'open') {
      await transitionWorkshopTicket(ticketId, {
        status: 'in_progress',
      })
    }

    await transitionWorkshopTicket(ticketId, {
      status: 'completed',
      resolution_action: 'writeoff',
      writeoff_qty: writeoffQty,
      actual_cost: lossAcceptActualCost.value || undefined,
      resolution_notes: t('workshop.internal.lossResolutionNotes'),
    })

    closeLossAcceptModal()
    await loadData()

    const [detailed, history] = await Promise.all([
      getWorkshopTicket(ticketId),
      getWorkshopTicketHistory(ticketId),
    ])
    selectedTicket.value = detailed
    ticketHistory.value = history
    toast.success(t('workshop.toast.lossAccepted', { qty: String(writeoffQty) }))
  } catch (err: any) {
    console.error('Loss acceptance failed:', err)
    lossAcceptError.value = err.response?.data?.error || err.message || ''
    toast.error(t('workshop.toast.lossError') + ' ' + lossAcceptError.value)
  } finally {
    isAcceptingLoss.value = false
  }
}

// === Material-Auswahl (globale Suche) ===

function formatBatchLabel(batch: MaterialBatch) {
  const serial = batch.serial_number?.trim()
  const label = batch.label?.trim()
  if (serial && label) return `${serial} · ${label}`
  return serial || label || batch.id
}

function resolveBatchIdFromSearch(mat: Material, searchTerm: string): string {
  const q = searchTerm.trim().toLowerCase()
  if (!q) return ''
  const match = (mat.batches ?? []).find((batch) => {
    if (batch.status !== 'active' || (batch.qty ?? 0) <= 0) return false
    const serial = batch.serial_number?.toLowerCase() ?? ''
    const label = batch.label?.toLowerCase() ?? ''
    return serial.includes(q) || label.includes(q)
  })
  return match?.id ?? ''
}

function selectMaterial(mat: Material, options?: { searchTerm?: string }) {
  selectedMaterial.value = mat
  createForm.value.material_item_id = mat.id
  createForm.value.material_batch_id = ''
  createForm.value.affected_quantity = 1
  if (mat.tracking_type === 'serialized') {
    const fromSearch = resolveBatchIdFromSearch(mat, options?.searchTerm ?? matSearchQuery.value)
    if (fromSearch) {
      createForm.value.material_batch_id = fromSearch
    } else if (serialBatchSelectItems.value.length === 1) {
      createForm.value.material_batch_id = String(serialBatchSelectItems.value[0].value)
    }
  }
  matSearchQuery.value = ''
}

async function onMatSearchSuggestionSelect(suggestion: SearchSuggestion) {
  if (suggestion.type !== 'material') return
  try {
    const mat = await getMaterial(suggestion.id)
    selectMaterial(mat, { searchTerm: matSearchQuery.value })
  } catch (err) {
    console.error('Material laden fehlgeschlagen:', err)
    toast.error(t('workshop.toast.createError'))
  }
}

function clearSelectedMaterial() {
  selectedMaterial.value = null
  createForm.value.material_item_id = ''
  createForm.value.material_batch_id = ''
  createForm.value.affected_quantity = 1
  matSearchQuery.value = ''
  nextTick(() => matSearchFieldRef.value?.focus())
}

// === Helpers ===

/**
 * Formatiert die Änderungen eines History-Eintrags als lesbaren Text
 */
function getHistoryDescription(entry: WorkshopHistoryEntry): string {
  const changes = entry.changes
  const parts: string[] = []
  const pl = phaseLabels.value as Record<string, string>
  const plPriority = priorityLabels.value as Record<string, string>
  const rl = resolutionLabels.value as Record<string, string>
  const cl = conditionLabels.value as Record<string, string>
  const il = issueTypeLabels.value as Record<string, string>

  if (changes.phase) {
    const oldLabel = pl[String(changes.phase.old ?? 'triage')] || String(changes.phase.old ?? 'triage')
    const newLabel = pl[String(changes.phase.new ?? 'triage')] || String(changes.phase.new ?? 'triage')
    parts.push(t('workshop.history.phaseChange', { old: oldLabel, new: newLabel }))
  } else if (changes.status) {
    const statusToPhase: Record<string, string> = {
      open: 'triage',
      in_progress: 'in_progress',
      waiting_parts: 'awaiting_quote',
      completed: 'completed',
      cancelled: 'cancelled',
    }
    const oldLabel = pl[statusToPhase[String(changes.status.old)] ?? String(changes.status.old)] || String(changes.status.old)
    const newLabel = pl[statusToPhase[String(changes.status.new)] ?? String(changes.status.new)] || String(changes.status.new)
    parts.push(t('workshop.history.phaseChange', { old: oldLabel, new: newLabel }))
  }

  if (changes.priority) {
    if (typeof changes.priority === 'object' && changes.priority.old) {
      const oldLabel = plPriority[String(changes.priority.old)] || changes.priority.old
      const newLabel = plPriority[String(changes.priority.new)] || changes.priority.new
      parts.push(t('workshop.history.priorityChange', { old: oldLabel, new: newLabel }))
    }
  }

  if (changes.title && typeof changes.title === 'object') {
    parts.push(t('workshop.history.titleChanged'))
  }

  if (changes.assigned_to_user_id) {
    parts.push(changes.assigned_to_user_id.new ? t('workshop.history.assigned') : t('workshop.history.assignmentRemoved'))
  }

  if (changes.resolution_action) {
    const label = rl[String(changes.resolution_action)] || changes.resolution_action
    parts.push(t('workshop.history.outcome', { label }))
  }

  if (changes.material_condition) {
    const oldLabel = cl[String(changes.material_condition.old)] || changes.material_condition.old
    const newLabel = cl[String(changes.material_condition.new)] || changes.material_condition.new
    parts.push(t('workshop.history.materialCondition', { old: oldLabel, new: newLabel }))
  }

  if (changes.actual_cost) {
    parts.push(t('workshop.history.cost', { amount: String(changes.actual_cost) }))
  }

  if (changes.cost_breakdown && typeof changes.cost_breakdown === 'object') {
    const breakdown = changes.cost_breakdown as Record<string, unknown>
    const breakdownParts: string[] = []
    if (breakdown.labor_enabled && breakdown.labor_total_chf) {
      breakdownParts.push(t('workshop.history.costLabor', {
        hours: String(breakdown.labor_hours ?? 0),
        amount: String(breakdown.labor_total_chf),
      }))
    }
    if (breakdown.flat_rate_enabled && breakdown.flat_rate_chf) {
      breakdownParts.push(t('workshop.history.costFlatRate', { amount: String(breakdown.flat_rate_chf) }))
    }
    if (breakdown.material_enabled && breakdown.material_total_chf) {
      breakdownParts.push(t('workshop.history.costMaterial', { amount: String(breakdown.material_total_chf) }))
    }
    if (breakdownParts.length) {
      parts.push(t('workshop.history.costBreakdown', { parts: breakdownParts.join(' + ') }))
    }
  }

  if (changes.writeoff_qty) {
    parts.push(t('workshop.history.writeoffQty', { qty: String(changes.writeoff_qty) }))
  }

  if (changes.issue_report_resolved) {
    parts.push(t('workshop.history.issueResolved'))
  }

  if (changes.source === 'issue_report') {
    parts.push(t('workshop.history.fromActivity', { name: changes.activity_name || '?' }))
    if (changes.issue_report_type) {
      const typeLabel = il[String(changes.issue_report_type)] || changes.issue_report_type
      parts.push(t('workshop.history.reportType', { type: typeLabel }))
    }
  }
  if (changes.source === 'return_item') {
    parts.push(t('workshop.history.fromReturnActivity', { name: changes.activity_name || '?' }))
    if (changes.condition_in) {
      parts.push(t('workshop.history.conditionReturn', { condition: changes.condition_in }))
    }
  }

  if (changes.resolution_notes && typeof changes.resolution_notes === 'string') {
    parts.push(t('workshop.history.note', { text: changes.resolution_notes }))
  }

  return parts.join(' · ')
}

/**
 * Icon für History-Action
 */
function getHistoryIcon(action: string): string {
  switch (action) {
    case 'created': return '🆕'
    case 'auto_created_issue': return '⚠️'
    case 'auto_created_return': return '📦'
    case 'status_changed': return '🔄'
    case 'assigned': return '👤'
    case 'updated': return '✏️'
    case 'completed': return '✅'
    case 'cancelled': return '❌'
    default: return '📋'
  }
}

/**
 * CSS-Klasse für History-Dot
 */
function getHistoryDotClass(action: string): string {
  switch (action) {
    case 'created':
    case 'auto_created_issue':
    case 'auto_created_return':
      return 'created'
    case 'completed':
      return 'completed'
    case 'cancelled':
      return 'cancelled'
    case 'status_changed':
      return 'active'
    default:
      return ''
  }
}

function materialTypeIcon(type: string): string {
  switch (type) {
    case 'physical': return '📦'
    case 'physical_combo': return '🧩'
    case 'virtual_combo': return '📋'
    default: return '📦'
  }
}

function materialTypeLabel(type: string): string {
  switch (type) {
    case 'physical': return t('workshop.materialTypePhysical')
    case 'physical_combo': return t('workshop.materialTypePhysicalCombo')
    case 'virtual_combo': return t('workshop.materialTypeVirtualCombo')
    default: return t('common.material')
  }
}

function getInitials(name: string): string {
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
  }
  return name.substring(0, 2).toUpperCase()
}

function formatDateShort(dateStr: string): string {
  const date = new Date(dateStr)
  return date.toLocaleDateString(locale.value, { day: '2-digit', month: '2-digit' })
}

function formatDateTime(dateStr: string): string {
  const date = new Date(dateStr)
  const tz = authStore.departmentTimezone || 'Europe/Zurich'
  return date.toLocaleString(locale.value, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: tz,
  })
}

// === Lifecycle ===
watch([currentDepartmentId, materialFilterId], () => {
  loadData()
})

watch(
  () => route.query.ticket,
  (ticketId) => {
    if (!ticketId || typeof ticketId !== 'string') {
      if (selectedTicket.value) {
        selectedTicket.value = null
        ticketHistory.value = []
      }
      return
    }
    void tryOpenTicketFromQuery()
  }
)

watch(
  () => route.query.qf,
  () => {
    applyQuickFilterFromRoute()
  }
)

watch(showCreateModal, async (open) => {
  if (!open || !materialFilterId.value) return
  createForm.value.material_item_id = materialFilterId.value
  try {
    const m = await getMaterial(materialFilterId.value)
    selectedMaterial.value = m
  } catch {
    selectedMaterial.value = null
  }
})

onMounted(() => {
  applyQuickFilterFromRoute()
  loadData()
})
</script>
