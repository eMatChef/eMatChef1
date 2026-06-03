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

    <!-- Stats -->
    <div class="workshop-stats" v-if="stats">
      <div class="stat-card open">
        <div class="stat-value">{{ stats.status_counts.open || 0 }}</div>
        <div class="stat-label">{{ t('workshop.statOpen') }}</div>
      </div>
      <div class="stat-card in-progress">
        <div class="stat-value">{{ stats.status_counts.in_progress || 0 }}</div>
        <div class="stat-label">{{ t('workshop.statInProgress') }}</div>
      </div>
      <div class="stat-card waiting">
        <div class="stat-value">{{ stats.status_counts.waiting_parts || 0 }}</div>
        <div class="stat-label">{{ t('workshop.statWaitingParts') }}</div>
      </div>
      <div class="stat-card completed">
        <div class="stat-value">{{ stats.status_counts.completed || 0 }}</div>
        <div class="stat-label">{{ t('workshop.statCompleted') }}</div>
      </div>
      <div class="stat-card cancelled">
        <div class="stat-value">{{ stats.status_counts.cancelled || 0 }}</div>
        <div class="stat-label">{{ t('workshop.statCancelled') }}</div>
      </div>
    </div>

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

    <!-- Kanban Board -->
    <div v-else-if="viewMode === 'kanban'" class="kanban-board">
      <div
        v-for="col in kanbanColumns"
        :key="col.status"
        class="kanban-column"
        :class="col.status"
      >
        <div class="kanban-column-header">
          <span class="column-title">{{ col.label }}</span>
          <span class="column-count">{{ getColumnTickets(col.status).length }}</span>
        </div>
        <div class="kanban-column-body">
          <div
            v-for="ticket in getColumnTickets(col.status)"
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
              <svg class="mat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
              </svg>
              {{ ticket.material_item.name }}
            </div>
            <div class="card-footer">
              <div class="card-meta">
                <span class="type-badge" :class="ticket.type">{{ typeLabels[ticket.type] }}</span>
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

          <!-- Leere Spalte -->
          <div v-if="getColumnTickets(col.status).length === 0" style="text-align: center; padding: 24px 8px; color: #9ca3af; font-size: 12px;">
            {{ t('workshop.noTicketsInColumn') }}
          </div>
        </div>
      </div>
    </div>
    <div v-if="viewMode === 'kanban' && cancelledTickets.length > 0" class="cancelled-section">
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

    <!-- Table View -->
    <div v-else class="table-wrapper">
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
              <span class="status-badge" :class="ticket.status">
                <span class="status-dot" :class="ticket.status"></span>
                {{ statusLabels[ticket.status] }}
              </span>
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

    <EDialog
      v-model="detailDialogOpen"
      :max-width="920"
      scrollable
      card-class="workshop-detail-dialog"
    >
      <template v-if="selectedTicket" #title>
        <div class="workshop-detail-dialog-title">
          <span>{{ selectedTicket.title }}</span>
          <div class="modal-subtitle">
            <span class="status-badge" :class="selectedTicket.status">
              <span class="status-dot" :class="selectedTicket.status"></span>
              {{ statusLabels[selectedTicket.status] }}
            </span>
            <span class="priority-badge" :class="selectedTicket.priority">
              {{ priorityLabels[selectedTicket.priority] }}
            </span>
            <span class="type-badge" :class="selectedTicket.type">
              {{ typeLabels[selectedTicket.type] }}
            </span>
          </div>
        </div>
      </template>

      <div v-if="selectedTicket" class="workshop-detail-dialog-body">
        <div v-if="canManageWorkshopQr" class="workshop-qr-header-actions">
          <EButton
            v-if="showGenerateWorkshopQrButton"
            variant="secondary"
            size="small"
            :loading="isGeneratingWorkshopPublicCode"
            @click="generateWorkshopPublicCode"
          >
            {{ isGeneratingWorkshopPublicCode ? t('workshop.qrGenLoading') : t('workshop.qrGenCreate') }}
          </EButton>
          <PublicQrTag
            v-if="workshopPublicUrl"
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

          <!-- Details Grid -->
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
              <div v-else class="detail-item detail-item--full">
                <span class="detail-label">{{ t('workshop.assignSupplier') }}</span>
                <div class="supplier-assign-row">
                  <ESelect
                    v-model="assignSupplierCompanyId"
                    :items="repairCompanySelectItems"
                    :placeholder="t('workshop.selectSupplierCompany')"
                    hide-details="auto"
                    class="supplier-select"
                  />
                  <EButton
                    variant="secondary"
                    size="small"
                    :disabled="!assignSupplierCompanyId || isAssigningSupplier"
                    :loading="isAssigningSupplier"
                    @click="assignTicketToSupplier"
                  >
                    {{ t('workshop.assignSupplierAction') }}
                  </EButton>
                </div>
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

          <!-- History Timeline (dynamisch aus API) -->
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

          <!-- Resolution Notes -->
          <div v-if="selectedTicket.resolution_notes" class="modal-section">
            <div class="modal-section-title">{{ t('workshop.sectionResolutionNotes') }}</div>
            <p style="font-size: 14px; color: #374151; line-height: 1.6; margin: 0; background: #f0fdf4; padding: 10px 14px; border-radius: 8px; white-space: pre-wrap;">{{ selectedTicket.resolution_notes }}</p>
          </div>
      </div>

      <template v-if="selectedTicket && selectedTicket.status !== 'completed' && selectedTicket.status !== 'cancelled'" #actions>
        <EButton
          v-if="selectedTicket.status === 'open' && !isLossOriginTicket(selectedTicket)"
          variant="primary"
          size="small"
          @click="transitionTicket(selectedTicket.id, 'in_progress')"
        >
          {{ t('workshop.btnStartWork') }}
        </EButton>
        <EButton
          v-if="selectedTicket.status === 'open' && isLossOriginTicket(selectedTicket)"
          variant="danger"
          size="small"
          @click="openLossAcceptModal()"
        >
          {{ t('workshop.btnAcceptLoss') }}
        </EButton>
        <EButton
          v-if="selectedTicket.status === 'in_progress'"
          variant="secondary"
          size="small"
          @click="openQuoteModal()"
        >
          {{ t('workshop.btnWaitingParts') }}
        </EButton>
        <EButton
          v-if="selectedTicket.status === 'waiting_parts'"
          variant="primary"
          size="small"
          @click="transitionTicket(selectedTicket.id, 'in_progress')"
        >
          {{ t('workshop.btnResumeWork') }}
        </EButton>
        <EButton
          v-if="selectedTicket.status === 'in_progress'"
          variant="primary"
          size="small"
          @click="showCompleteModal = true"
        >
          {{ t('workshop.btnComplete') }}
        </EButton>
        <EButton variant="danger" size="small" @click="cancelSelectedTicket()">
          {{ t('workshop.btnCancelTicket') }}
        </EButton>
        <EButton variant="text" size="small" @click="closeSelectedTicketDetail">
          {{ t('common.close') }}
        </EButton>
      </template>
      <template v-else-if="selectedTicket && selectedTicket.status === 'cancelled'" #actions>
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
      <ETextField
        v-model="completeForm.actual_cost"
        class="mt-3"
        type="number"
        :label="t('workshop.labelActualCostChf')"
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
          :disabled="!completeForm.resolution_action || completionCostMissing"
          @click="completeTicket"
        >
          {{ t('workshop.btnFinishComplete') }}
        </EButton>
      </template>
    </EDialog>

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

    <EDialog v-model="showQuoteModal" :max-width="520" :title="t('workshop.quoteTitle')">
      <p v-if="selectedTicket" class="workshop-dialog-subtitle">{{ selectedTicket.title }}</p>
      <ETextField
        v-model="quoteEstimatedCost"
        type="number"
        :label="t('workshop.quoteEstimatedLabel')"
        placeholder="0.00"
        hide-details="auto"
      />
      <ETextarea
        v-model="quoteNotes"
        class="mt-3"
        :label="t('workshop.quoteNoteLabel')"
        :placeholder="t('workshop.quoteNotePlaceholder')"
        rows="3"
        hide-details="auto"
      />
      <p v-if="quoteError" class="workshop-dialog-error">{{ quoteError }}</p>
      <template #actions>
        <EButton variant="secondary" size="small" :disabled="isSubmittingQuote" @click="closeQuoteModal">
          {{ t('common.cancel') }}
        </EButton>
        <EButton
          variant="primary"
          size="small"
          :loading="isSubmittingQuote"
          :disabled="isExternalSelectedTicket && !quoteEstimatedCost"
          @click="submitQuoteTransition"
        >
          {{ isSubmittingQuote ? t('workshop.quoteSaving') : t('workshop.quoteSetWaiting') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog v-model="showCreateModal" :max-width="580" :title="t('workshop.createTitle')">
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
                    <span v-if="selectedMaterial.barcode_tag">🏷️ {{ selectedMaterial.barcode_tag }}</span>
                    <span v-if="selectedMaterial.category">{{ selectedMaterial.category.name }}</span>
                    <span :class="'condition-' + selectedMaterial.condition">{{ conditionLabels[selectedMaterial.condition] || selectedMaterial.condition }}</span>
                  </span>
                </div>
                <button type="button" class="ws-selected-clear" @click="clearSelectedMaterial" :title="t('workshop.createChangeMaterialTitle')">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
              </div>
              <!-- Suchfeld mit Autocomplete -->
              <div v-else class="ws-mat-autocomplete">
                <div class="ws-mat-search-wrap">
                  <svg class="ws-mat-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                  </svg>
                  <input
                    ref="matSearchInputRef"
                    v-model="matSearchQuery"
                    type="text"
                    :placeholder="t('workshop.createSearchPlaceholder')"
                    @input="onMatSearchInput"
                    @focus="showMatDropdown = true"
                    @blur="hideMatDropdownDelayed"
                    @keydown.escape="showMatDropdown = false"
                  />
                  <span v-if="isMatSearching" class="ws-mat-spinner">⟳</span>
                </div>
                <!-- Dropdown-Vorschlagsliste -->
                <div v-if="showMatDropdown && matSearchQuery.trim().length >= 1" class="ws-mat-dropdown">
                  <div v-if="isMatSearching" class="ws-mat-dropdown-msg">
                    {{ t('workshop.createSearching') }}
                  </div>
                  <div v-else-if="matSearchResults.length === 0" class="ws-mat-dropdown-msg">
                    {{ t('workshop.createNoMatches', { query: matSearchQuery }) }}
                  </div>
                  <div v-else class="ws-mat-dropdown-list">
                    <div
                      v-for="mat in matSearchResults"
                      :key="mat.id"
                      class="ws-mat-dropdown-item"
                      @mousedown.prevent="selectMaterial(mat)"
                    >
                      <span class="ws-mat-type-icon" :title="materialTypeLabel(mat.material_type)">
                        {{ materialTypeIcon(mat.material_type) }}
                      </span>
                      <div class="ws-mat-item-info">
                        <span class="ws-mat-item-name">{{ mat.name }}</span>
                        <span class="ws-mat-item-meta">
                          <span v-if="mat.category" class="ws-mat-item-cat">{{ mat.category.name }}</span>
                          <span v-if="mat.barcode_tag" class="ws-mat-item-sn">🏷️ {{ mat.barcode_tag }}</span>
                          <span class="ws-mat-item-stock">{{ mat.total_stock }} {{ t('workshop.stockUnit') }}</span>
                          <span :class="'condition-dot ' + mat.condition">{{ conditionLabels[mat.condition] || mat.condition }}</span>
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="form-row mt-3">
              <ESelect
                v-model="createForm.type"
                :items="createTypeItems"
                :label="t('workshop.createType')"
                hide-details="auto"
              />
              <ESelect
                v-model="createForm.priority"
                :items="createPriorityItems"
                :label="t('workshop.createPriority')"
                hide-details="auto"
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
            <ETextField
              v-model="createForm.estimated_cost"
              class="mt-3"
              type="number"
              :label="t('workshop.createEstimatedCost')"
              placeholder="0.00"
              hide-details="auto"
            />
          </div>
      <template #actions>
        <EButton variant="secondary" size="small" @click="showCreateModal = false">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!createForm.title || !createForm.material_item_id"
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
  </PageShell>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDetailTabsStore } from '@/stores/detailTabs'
import { useToast } from '@/composables/useToast'
import {
  getWorkshopTickets,
  getWorkshopTicket,
  createWorkshopTicket,
  updateWorkshopTicket,
  transitionWorkshopTicket,
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
import { listSupplierRepairCompanies } from '@/api/supplierShop'
import { getMaterial, type Material } from '@/api/materials'
import { createBasicMaterialLookupFetcher } from '@/composables/useMaterialLookup'
import GlobalSearchInput from '@/components/common/GlobalSearchInput.vue'
import { useListSearchQueryRoute } from '@/composables/useListSearchQueryRoute'
import { parseSearchQuery } from '@/composables/useSearchNavigation'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import PublicQrActionModal from '@/components/common/PublicQrActionModal.vue'
import { addPrintCartItem } from '@/api/tasks'
import { printHtmlDocument } from '@/utils/printHtml'
import { resolveWorkshopPublicUrl } from '@/utils/publicQrUrl'
import { filterMediaPhotos, normalizeMediaPhotos } from '@/api/media'
import type { MediaPhoto } from '@/api/media'
import PhotoGallery from '@/components/media/PhotoGallery.vue'
import PhotoUpload from '@/components/media/PhotoUpload.vue'
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
const currentDepartmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)

const workshopMaterialLookup = createBasicMaterialLookupFetcher(() => currentDepartmentId.value)

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
const showQuoteModal = ref(false)
const ticketHistory = ref<WorkshopHistoryEntry[]>([])
const isLoadingHistory = ref(false)
const isAcceptingLoss = ref(false)
const lossAcceptQty = ref(1)
const lossAcceptActualCost = ref('')
const lossAcceptError = ref('')
const quoteEstimatedCost = ref('')
const quoteNotes = ref('')
const quoteError = ref('')
const isSubmittingQuote = ref(false)
const isGeneratingWorkshopPublicCode = ref(false)
const showWorkshopQrActionModal = ref(false)
const repairCompanies = ref<Array<{ id: string; name: string }>>([])
const assignSupplierCompanyId = ref('')
const isAssigningSupplier = ref(false)

const detailDialogOpen = computed({
  get: () => selectedTicket.value !== null,
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

const createTypeItems = computed(() => [
  { title: t('workshop.typeRepair'), value: 'repair' },
  { title: t('workshop.typeInspection'), value: 'inspection' },
  { title: t('workshop.typeWriteoff'), value: 'writeoff' },
  { title: t('workshop.typeCleaning'), value: 'cleaning' },
])

const createPriorityItems = computed(() => [
  { title: t('workshop.priorityLow'), value: 'low' },
  { title: t('workshop.priorityNormal'), value: 'normal' },
  { title: t('workshop.priorityHigh'), value: 'high' },
  { title: t('workshop.priorityUrgent'), value: 'urgent' },
])

const repairCompanySelectItems = computed(() =>
  repairCompanies.value.map((c) => ({ title: c.name, value: c.id }))
)

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
const showGenerateWorkshopQrButton = computed(
  () => canManageWorkshopQr.value && !workshopPublicUrl.value && !!selectedTicket.value
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
  type: 'repair' as TicketType,
  priority: 'normal' as TicketPriority,
  description: '',
  estimated_cost: '',
})

// Complete Form
const completeForm = ref({
  resolution_action: '' as string,
  actual_cost: '',
  resolution_notes: '',
})

// Material-Autocomplete
const matSearchQuery = ref('')
const matSearchResults = ref<Material[]>([])
const isMatSearching = ref(false)
const showMatDropdown = ref(false)
const selectedMaterial = ref<Material | null>(null)
const matSearchInputRef = ref<HTMLInputElement | null>(null)
let matSearchTimer: ReturnType<typeof setTimeout> | null = null

// === Labels (vue-i18n) ===
const statusLabels = computed(() => ({
  open: t('workshop.status.open'),
  in_progress: t('workshop.status.in_progress'),
  waiting_parts: t('workshop.status.waiting_parts'),
  completed: t('workshop.status.completed'),
  cancelled: t('workshop.status.cancelled'),
}))

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

// === Kanban Columns ===
const kanbanColumns = computed(() => [
  { status: 'open' as TicketStatus, label: t('workshop.status.open') },
  { status: 'in_progress' as TicketStatus, label: t('workshop.status.in_progress') },
  { status: 'waiting_parts' as TicketStatus, label: t('workshop.status.waiting_parts') },
  { status: 'completed' as TicketStatus, label: t('workshop.status.completed') },
])

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
    result = result.filter(t => t.status === 'waiting_parts' && t.activity_type === 'external')
  } else if (quickFilter.value === 'missing_estimated_cost') {
    result = result.filter(t =>
      t.activity_type === 'external' &&
      ['open', 'in_progress', 'waiting_parts'].includes(t.status) &&
      ['repair', 'writeoff'].includes(t.type) &&
      t.estimated_cost == null
    )
  }

  return result
})

function getColumnTickets(status: TicketStatus): WorkshopTicket[] {
  return filteredTickets.value.filter(t => t.status === status)
}

const cancelledTickets = computed(() => {
  return filteredTickets.value.filter(t => t.status === 'cancelled')
})

const isExternalSelectedTicket = computed(() => {
  return selectedTicket.value?.activity?.type === 'external'
})

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

const completionCostMissing = computed(() => {
  if (!isExternalSelectedTicket.value) return false
  if (!['repaired', 'writeoff'].includes(completeForm.value.resolution_action || '')) return false
  return !completeForm.value.actual_cost
})

// === Methods ===
async function loadData() {
  if (!currentDepartmentId.value) return

  isLoading.value = true
  try {
    const listOpts = materialFilterId.value ? { material_item_id: materialFilterId.value } : undefined
    const [ticketsData, statsData] = await Promise.all([
      getWorkshopTickets(currentDepartmentId.value, listOpts),
      getWorkshopStats(currentDepartmentId.value),
    ])
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

async function generateWorkshopPublicCode() {
  const ticket = selectedTicket.value
  if (!ticket?.id || isGeneratingWorkshopPublicCode.value) return
  isGeneratingWorkshopPublicCode.value = true
  try {
    selectedTicket.value = await ensureWorkshopPublicCode(ticket.id)
    toast.success(t('workshop.toastQrCreated'))
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('workshop.errQrCreate'))
  } finally {
    isGeneratingWorkshopPublicCode.value = false
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

    selectedTicket.value = detailed
    registerWorkshopDetailTab(detailed)
    ticketHistory.value = history
    assignSupplierCompanyId.value = ''
    try {
      repairCompanies.value = await listSupplierRepairCompanies(currentDepartmentId.value)
    } catch {
      repairCompanies.value = []
    }
  } catch (err) {
    console.error('Failed to load ticket details:', err)
    selectedTicket.value = ticket
    ticketHistory.value = []
  } finally {
    isLoadingHistory.value = false
  }
}

async function assignTicketToSupplier() {
  if (!selectedTicket.value || !assignSupplierCompanyId.value) return
  isAssigningSupplier.value = true
  try {
    await updateWorkshopTicket(selectedTicket.value.id, {
      assigned_to_supplier_company_id: assignSupplierCompanyId.value,
    })
    const detailed = await getWorkshopTicket(selectedTicket.value.id)
    selectedTicket.value = detailed
    assignSupplierCompanyId.value = ''
    await loadData()
    toast.success(t('workshop.toast.supplierAssigned'))
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('workshop.toast.supplierAssignError'))
  } finally {
    isAssigningSupplier.value = false
  }
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
  if (!createForm.value.title || !createForm.value.material_item_id) return

  try {
    await createWorkshopTicket({
      department_id: currentDepartmentId.value,
      material_item_id: createForm.value.material_item_id,
      title: createForm.value.title,
      type: createForm.value.type,
      priority: createForm.value.priority,
      description: createForm.value.description || undefined,
      estimated_cost: createForm.value.estimated_cost || undefined,
    })

    // Reset form
    createForm.value = {
      title: '',
      material_item_id: '',
      type: 'repair',
      priority: 'normal',
      description: '',
      estimated_cost: '',
    }
    selectedMaterial.value = null
    matSearchQuery.value = ''
    showCreateModal.value = false
    await loadData()
    toast.success(t('workshop.toast.created'))
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

async function completeTicket() {
  if (!selectedTicket.value || !completeForm.value.resolution_action) return

  try {
    await transitionWorkshopTicket(selectedTicket.value.id, {
      status: 'completed',
      resolution_action: completeForm.value.resolution_action as any,
      resolution_notes: completeForm.value.resolution_notes || undefined,
      actual_cost: completeForm.value.actual_cost || undefined,
    })

    showCompleteModal.value = false
    completeForm.value = { resolution_action: '', actual_cost: '', resolution_notes: '' }

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

function openQuoteModal() {
  if (!selectedTicket.value) return
  quoteEstimatedCost.value = selectedTicket.value.estimated_cost || ''
  quoteNotes.value = ''
  quoteError.value = ''
  showQuoteModal.value = true
}

function closeQuoteModal() {
  showQuoteModal.value = false
  quoteError.value = ''
}

async function submitQuoteTransition() {
  if (!selectedTicket.value) return
  const ticketId = selectedTicket.value.id

  if (isExternalSelectedTicket.value && !quoteEstimatedCost.value) {
    quoteError.value = t('workshop.toast.quoteNeedEstimate')
    return
  }

  try {
    isSubmittingQuote.value = true
    quoteError.value = ''

    await transitionWorkshopTicket(ticketId, {
      status: 'waiting_parts',
      estimated_cost: quoteEstimatedCost.value || undefined,
    })

    if (quoteEstimatedCost.value || quoteNotes.value) {
      await updateWorkshopTicket(ticketId, {
        estimated_cost: quoteEstimatedCost.value || undefined,
        description: quoteNotes.value
          ? `${selectedTicket.value.description || ''}${selectedTicket.value.description ? '\n\n' : ''}[Offerte] ${quoteNotes.value}`
          : undefined,
      })
    }

    closeQuoteModal()
    await loadData()

    const [detailed, history] = await Promise.all([
      getWorkshopTicket(ticketId),
      getWorkshopTicketHistory(ticketId),
    ])
    selectedTicket.value = detailed
    ticketHistory.value = history
    toast.info(t('workshop.toast.quoteSetWaiting'))
  } catch (err: any) {
    console.error('Quote transition failed:', err)
    quoteError.value = err.response?.data?.error || err.message || ''
    toast.error(t('workshop.toast.quoteError') + ' ' + quoteError.value)
  } finally {
    isSubmittingQuote.value = false
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

// === Material Autocomplete ===

function onMatSearchInput() {
  if (matSearchTimer) clearTimeout(matSearchTimer)

  const q = matSearchQuery.value.trim()
  if (q.length < 1) {
    matSearchResults.value = []
    showMatDropdown.value = false
    isMatSearching.value = false
    return
  }

  showMatDropdown.value = true
  isMatSearching.value = true
  matSearchTimer = setTimeout(() => {
    void searchMaterialsForTicket()
  }, 300)
}

async function searchMaterialsForTicket() {
  const query = matSearchQuery.value.trim()
  if (query.length < 1 || !currentDepartmentId.value) {
    isMatSearching.value = false
    return
  }

  isMatSearching.value = true
  try {
    matSearchResults.value = await workshopMaterialLookup(query)
    showMatDropdown.value = true
  } catch (err) {
    console.error('Material-Suche fehlgeschlagen:', err)
    matSearchResults.value = []
  } finally {
    isMatSearching.value = false
  }
}

function selectMaterial(mat: Material) {
  selectedMaterial.value = mat
  createForm.value.material_item_id = mat.id
  matSearchQuery.value = ''
  matSearchResults.value = []
  showMatDropdown.value = false
}

function clearSelectedMaterial() {
  selectedMaterial.value = null
  createForm.value.material_item_id = ''
  matSearchQuery.value = ''
  matSearchResults.value = []
  nextTick(() => matSearchInputRef.value?.focus())
}

function hideMatDropdownDelayed() {
  setTimeout(() => { showMatDropdown.value = false }, 200)
}

// === Helpers ===

/**
 * Formatiert die Änderungen eines History-Eintrags als lesbaren Text
 */
function getHistoryDescription(entry: WorkshopHistoryEntry): string {
  const changes = entry.changes
  const parts: string[] = []
  const sl = statusLabels.value as Record<string, string>
  const pl = priorityLabels.value as Record<string, string>
  const rl = resolutionLabels.value as Record<string, string>
  const cl = conditionLabels.value as Record<string, string>
  const il = issueTypeLabels.value as Record<string, string>

  if (changes.status) {
    const oldLabel = sl[String(changes.status.old)] || changes.status.old
    const newLabel = sl[String(changes.status.new)] || changes.status.new
    parts.push(t('workshop.history.statusChange', { old: oldLabel, new: newLabel }))
  }

  if (changes.priority) {
    if (typeof changes.priority === 'object' && changes.priority.old) {
      const oldLabel = pl[String(changes.priority.old)] || changes.priority.old
      const newLabel = pl[String(changes.priority.new)] || changes.priority.new
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
