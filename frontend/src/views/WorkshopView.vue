<template>
  <div class="workshop-view">
    <!-- Header -->
    <header class="workshop-header">
      <div class="header-content">
        <div>
    <h1>{{ t('workshop.title') }}</h1>
          <p class="description">{{ t('workshop.description') }}</p>
        </div>
        <div class="header-actions">
          <button @click="showCreateModal = true" class="btn-primary">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
              <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span>{{ t('workshop.newTicket') }}</span>
          </button>
        </div>
      </div>
    </header>

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

    <!-- Toolbar -->
    <div class="workshop-toolbar">
      <div class="view-toggle">
        <button :class="{ active: viewMode === 'kanban' }" @click="viewMode = 'kanban'">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="5" height="18" rx="1"/>
            <rect x="10" y="3" width="5" height="12" rx="1"/>
            <rect x="17" y="3" width="5" height="15" rx="1"/>
          </svg>
          {{ t('workshop.viewKanban') }}
        </button>
        <button :class="{ active: viewMode === 'table' }" @click="viewMode = 'table'">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
          {{ t('workshop.viewTable') }}
        </button>
      </div>

      <div class="toolbar-search">
        <GlobalSearchInput
          mode="inline"
          :department-id="currentDepartmentId"
          default-type="reparatur"
          v-model="searchQuery"
          :placeholder="t('workshop.searchPlaceholder')"
        />
      </div>

      <div class="toolbar-filters">
        <select v-model="filterType">
          <option value="">{{ t('workshop.filterAllTypes') }}</option>
          <option value="repair">{{ t('workshop.typeRepair') }}</option>
          <option value="inspection">{{ t('workshop.typeInspection') }}</option>
          <option value="writeoff">{{ t('workshop.typeWriteoff') }}</option>
          <option value="cleaning">{{ t('workshop.typeCleaning') }}</option>
        </select>
        <select v-model="filterOriginIssueType">
          <option value="">{{ t('workshop.filterAllSources') }}</option>
          <option value="loss">{{ t('workshop.originLossOnly') }}</option>
          <option value="repair">{{ t('workshop.originRepairOnly') }}</option>
          <option value="damage">{{ t('workshop.originDamageOnly') }}</option>
          <option value="consumption">{{ t('workshop.originConsumptionOnly') }}</option>
          <option value="manual">{{ t('workshop.originManualOnly') }}</option>
        </select>
        <select v-model="filterPriority">
          <option value="">{{ t('workshop.filterAllPriorities') }}</option>
          <option value="urgent">{{ t('workshop.priorityUrgent') }}</option>
          <option value="high">{{ t('workshop.priorityHigh') }}</option>
          <option value="normal">{{ t('workshop.priorityNormal') }}</option>
          <option value="low">{{ t('workshop.priorityLow') }}</option>
        </select>
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

    <!-- Loading -->
    <div v-if="isLoading" class="workshop-loading">
      <div class="spinner"></div>
      <p style="margin-top: 12px; color: #6b7280; font-size: 14px;">{{ t('workshop.loadingTickets') }}</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="tickets.length === 0 && !isLoading" class="workshop-empty">
      <svg width="100" height="100" viewBox="0 0 100 100" fill="none">
        <rect x="20" y="25" width="60" height="50" rx="6" stroke="currentColor" stroke-width="2" stroke-dasharray="4 4"/>
        <path d="M40 50L47 57L62 42" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <h2>{{ t('workshop.emptyTitle') }}</h2>
      <p>{{ t('workshop.emptyText') }}</p>
      <button @click="showCreateModal = true" class="btn-primary">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
          <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        {{ t('workshop.createFirstTicket') }}
      </button>
    </div>

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
            <th>{{ t('workshop.tableStatus') }}</th>
            <th>{{ t('workshop.tablePriority') }}</th>
            <th>{{ t('workshop.tableTitle') }}</th>
            <th>{{ t('workshop.tableMaterial') }}</th>
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

    <!-- === Ticket Detail Modal === -->
    <div v-if="selectedTicket" class="workshop-modal-overlay">
      <div class="workshop-modal">
        <div class="modal-header">
          <div class="modal-title-group">
            <h2 class="modal-title">{{ selectedTicket.title }}</h2>
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
          <button class="modal-close" @click="closeSelectedTicketDetail">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <div class="modal-body">
          <!-- Material Info -->
          <div class="modal-section">
            <div class="modal-section-title">{{ t('workshop.sectionMaterial') }}</div>
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
            <div class="modal-section-title">{{ t('workshop.sectionDescription') }}</div>
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

        <!-- Modal Footer: Aktionen -->
        <div class="modal-footer" v-if="selectedTicket.status !== 'completed' && selectedTicket.status !== 'cancelled'">
          <!-- Status-Übergänge -->
          <button
            v-if="selectedTicket.status === 'open' && !isLossOriginTicket(selectedTicket)"
            class="btn-primary"
            @click="transitionTicket(selectedTicket.id, 'in_progress')"
          >
            {{ t('workshop.btnStartWork') }}
          </button>
          <button
            v-if="selectedTicket.status === 'open' && isLossOriginTicket(selectedTicket)"
            class="btn-danger"
            @click="openLossAcceptModal()"
          >
            {{ t('workshop.btnAcceptLoss') }}
          </button>
          <button
            v-if="selectedTicket.status === 'in_progress'"
            class="btn-warning"
            @click="openQuoteModal()"
          >
            {{ t('workshop.btnWaitingParts') }}
          </button>
          <button
            v-if="selectedTicket.status === 'waiting_parts'"
            class="btn-primary"
            @click="transitionTicket(selectedTicket.id, 'in_progress')"
          >
            {{ t('workshop.btnResumeWork') }}
          </button>
          <button
            v-if="selectedTicket.status === 'in_progress'"
            class="btn-success"
            @click="showCompleteModal = true"
          >
            {{ t('workshop.btnComplete') }}
          </button>
          <button
            class="btn-danger"
            @click="cancelSelectedTicket()"
          >
            {{ t('workshop.btnCancelTicket') }}
          </button>
          <button
            class="btn-ghost"
            @click="closeSelectedTicketDetail"
          >
            {{ t('workshop.btnClose') }}
          </button>
        </div>
        <div class="modal-footer" v-else-if="selectedTicket.status === 'cancelled'">
          <button class="btn-secondary" @click="transitionTicket(selectedTicket.id, 'open')">
            {{ t('workshop.btnReopen') }}
          </button>
        </div>
      </div>
    </div>

    <!-- === Complete Modal (Abschluss) === -->
    <div v-if="showCompleteModal && selectedTicket" class="workshop-modal-overlay">
      <div class="workshop-modal" style="max-width: 520px;">
        <div class="modal-header">
          <div class="modal-title-group">
            <h2 class="modal-title">{{ t('workshop.completeTitle') }}</h2>
            <div class="modal-subtitle">{{ selectedTicket.title }}</div>
          </div>
          <button class="modal-close" @click="showCompleteModal = false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <div class="complete-form">
            <div class="form-group">
              <label>{{ t('workshop.completeOutcome') }}</label>
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
            </div>
            <div class="form-group">
              <label>{{ t('workshop.labelActualCostChf') }}</label>
              <input v-model="completeForm.actual_cost" type="number" step="0.01" min="0" placeholder="0.00" />
            </div>
            <div class="form-group">
              <label>{{ t('workshop.completeNotesLabel') }}</label>
              <textarea v-model="completeForm.resolution_notes" rows="3" :placeholder="t('workshop.completeNotesPlaceholder')"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="showCompleteModal = false">{{ t('common.cancel') }}</button>
          <button class="btn-success" @click="completeTicket" :disabled="!completeForm.resolution_action || completionCostMissing">
            {{ t('workshop.btnFinishComplete') }}
          </button>
        </div>
      </div>
    </div>

    <!-- === Loss Accept Modal === -->
    <div v-if="showLossAcceptModal && selectedTicket" class="workshop-modal-overlay">
      <div class="workshop-modal" style="max-width: 520px;">
        <div class="modal-header">
          <div class="modal-title-group">
            <h2 class="modal-title">{{ t('workshop.lossTitle') }}</h2>
            <div class="modal-subtitle">{{ selectedTicket.title }}</div>
          </div>
          <button class="modal-close" @click="closeLossAcceptModal">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>{{ t('workshop.lossQtyLabel') }}</label>
            <input v-model.number="lossAcceptQty" type="number" min="1" step="1" />
          </div>
          <div class="form-group">
            <label>
              {{ t('workshop.labelActualCostChf') }}
              <span v-if="isExternalSelectedTicket" style="color: #b91c1c;">*</span>
            </label>
            <input v-model="lossAcceptActualCost" type="number" min="0" step="0.01" placeholder="0.00" />
            <p style="margin-top: 6px; color: #6b7280; font-size: 12px;">
              {{ lossCostSuggestionLabel }}
            </p>
          </div>
          <p style="margin-top: 8px; color: #6b7280; font-size: 13px;">
            {{ t('workshop.lossExplanation') }}
          </p>
          <p v-if="lossAcceptError" style="margin-top: 10px; color: #b91c1c; font-size: 13px;">
            {{ lossAcceptError }}
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="closeLossAcceptModal" :disabled="isAcceptingLoss">{{ t('common.cancel') }}</button>
          <button class="btn-danger" @click="acceptLossTicket" :disabled="isAcceptingLoss || lossAcceptQty < 1 || (isExternalSelectedTicket && !lossAcceptActualCost)">
            {{ isAcceptingLoss ? t('workshop.lossAccepting') : t('workshop.lossBtnAccept') }}
          </button>
        </div>
      </div>
    </div>

    <!-- === Quote / Waiting Parts Modal === -->
    <div v-if="showQuoteModal && selectedTicket" class="workshop-modal-overlay">
      <div class="workshop-modal" style="max-width: 520px;">
        <div class="modal-header">
          <div class="modal-title-group">
            <h2 class="modal-title">{{ t('workshop.quoteTitle') }}</h2>
            <div class="modal-subtitle">{{ selectedTicket.title }}</div>
          </div>
          <button class="modal-close" @click="closeQuoteModal">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>
              {{ t('workshop.quoteEstimatedLabel') }}
              <span v-if="isExternalSelectedTicket" style="color: #b91c1c;">*</span>
            </label>
            <input v-model="quoteEstimatedCost" type="number" min="0" step="0.01" placeholder="0.00" />
          </div>
          <div class="form-group">
            <label>{{ t('workshop.quoteNoteLabel') }}</label>
            <textarea v-model="quoteNotes" rows="3" :placeholder="t('workshop.quoteNotePlaceholder')"></textarea>
          </div>
          <p v-if="quoteError" style="margin-top: 10px; color: #b91c1c; font-size: 13px;">
            {{ quoteError }}
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="closeQuoteModal" :disabled="isSubmittingQuote">{{ t('common.cancel') }}</button>
          <button class="btn-warning" @click="submitQuoteTransition" :disabled="isSubmittingQuote || (isExternalSelectedTicket && !quoteEstimatedCost)">
            {{ isSubmittingQuote ? t('workshop.quoteSaving') : t('workshop.quoteSetWaiting') }}
          </button>
        </div>
      </div>
    </div>

    <!-- === Create Ticket Modal === -->
    <div v-if="showCreateModal" class="workshop-modal-overlay">
      <div class="workshop-modal" style="max-width: 580px;">
        <div class="modal-header">
          <div class="modal-title-group">
            <h2 class="modal-title">{{ t('workshop.createTitle') }}</h2>
          </div>
          <button class="modal-close" @click="showCreateModal = false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <div class="modal-body">
          <div class="create-form">
            <div class="form-group">
              <label>{{ t('workshop.createTitleLabel') }}</label>
              <input v-model="createForm.title" type="text" :placeholder="t('workshop.createTitlePlaceholder')" />
            </div>
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
                <div v-if="showMatDropdown && matSearchQuery.length >= 2" class="ws-mat-dropdown">
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
            <div class="form-row">
              <div class="form-group">
                <label>{{ t('workshop.createType') }}</label>
                <select v-model="createForm.type">
                  <option value="repair">{{ t('workshop.typeRepair') }}</option>
                  <option value="inspection">{{ t('workshop.typeInspection') }}</option>
                  <option value="writeoff">{{ t('workshop.typeWriteoff') }}</option>
                  <option value="cleaning">{{ t('workshop.typeCleaning') }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>{{ t('workshop.createPriority') }}</label>
                <select v-model="createForm.priority">
                  <option value="low">{{ t('workshop.priorityLow') }}</option>
                  <option value="normal">{{ t('workshop.priorityNormal') }}</option>
                  <option value="high">{{ t('workshop.priorityHigh') }}</option>
                  <option value="urgent">{{ t('workshop.priorityUrgent') }}</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>{{ t('workshop.createDescription') }}</label>
              <textarea v-model="createForm.description" rows="3" :placeholder="t('workshop.createDescriptionPlaceholder')"></textarea>
            </div>
            <div class="form-group">
              <label>{{ t('workshop.createEstimatedCost') }}</label>
              <input v-model="createForm.estimated_cost" type="number" step="0.01" min="0" placeholder="0.00" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="showCreateModal = false">{{ t('common.cancel') }}</button>
          <button
            class="btn-primary"
            @click="handleCreateTicket"
            :disabled="!createForm.title || !createForm.material_item_id"
          >
            {{ t('workshop.createSubmit') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import {
  getWorkshopTickets,
  getWorkshopTicket,
  createWorkshopTicket,
  updateWorkshopTicket,
  transitionWorkshopTicket,
  getWorkshopStats,
  getWorkshopTicketHistory,
  type WorkshopTicket,
  type WorkshopStats,
  type WorkshopHistoryEntry,
  type TicketStatus,
  type TicketType,
  type TicketPriority,
} from '@/api/workshop'
import { getMaterials, getMaterial, type Material } from '@/api/materials'
import GlobalSearchInput from '@/components/common/GlobalSearchInput.vue'
import '@/styles/workshop-view.css'

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const authStore = useAuthStore()
const toast = useToast()
const currentDepartmentId = computed(() => route.params.departmentId as string)

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

// Filter
const searchQuery = ref('')
const filterType = ref<TicketType | ''>('')
const filterOriginIssueType = ref<'repair' | 'loss' | 'damage' | 'consumption' | 'manual' | ''>('')
const filterPriority = ref<TicketPriority | ''>('')
const quickFilter = ref<'waiting_quote' | 'missing_estimated_cost' | ''>('')

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
    const q = searchQuery.value.toLowerCase()
    result = result.filter(t =>
      t.title.toLowerCase().includes(q) ||
      t.material_item.name.toLowerCase().includes(q) ||
      (t.description && t.description.toLowerCase().includes(q))
    )
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

async function openTicketDetail(ticket: WorkshopTicket) {
  try {
    // Lade Ticket-Details und History parallel
    selectedTicket.value = ticket // Sofort zeigen
    isLoadingHistory.value = true

    const [detailed, history] = await Promise.all([
      getWorkshopTicket(ticket.id),
      getWorkshopTicketHistory(ticket.id),
    ])

    selectedTicket.value = detailed
    ticketHistory.value = history
  } catch (err) {
    console.error('Failed to load ticket details:', err)
    selectedTicket.value = ticket
    ticketHistory.value = []
  } finally {
    isLoadingHistory.value = false
  }
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

  if (matSearchQuery.value.length < 2) {
    matSearchResults.value = []
    showMatDropdown.value = false
    return
  }

  showMatDropdown.value = true
  isMatSearching.value = true
  matSearchTimer = setTimeout(() => {
    searchMaterialsForTicket()
  }, 300)
}

async function searchMaterialsForTicket() {
  const query = matSearchQuery.value.trim()
  if (query.length < 2 || !currentDepartmentId.value) return

  isMatSearching.value = true
  try {
    const results = await getMaterials(currentDepartmentId.value, { search: query })
    matSearchResults.value = results
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
    default: return t('workshop.materialTypeFallback')
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
  () => {
    tryOpenTicketFromQuery()
  }
)

watch(
  () => route.query.qf,
  () => {
    applyQuickFilterFromRoute()
  }
)

watch(
  () => route.query.q,
  (q) => {
    if (route.path.includes('/workshop')) {
      searchQuery.value = (q as string) ?? ''
    }
  },
  { immediate: true }
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
