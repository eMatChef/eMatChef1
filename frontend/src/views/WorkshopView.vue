<template>
  <div class="workshop-view">
    <!-- Header -->
    <header class="workshop-header">
      <div class="header-content">
        <div>
    <h1>Werkstatt</h1>
          <p class="description">Reparaturen, Inspektionen, Wartung und Abschreibung</p>
        </div>
        <div class="header-actions">
          <button @click="showCreateModal = true" class="btn-primary">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
              <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span>Neues Ticket</span>
          </button>
        </div>
      </div>
    </header>

    <!-- Stats -->
    <div class="workshop-stats" v-if="stats">
      <div class="stat-card open">
        <div class="stat-value">{{ stats.status_counts.open || 0 }}</div>
        <div class="stat-label">Offen</div>
      </div>
      <div class="stat-card in-progress">
        <div class="stat-value">{{ stats.status_counts.in_progress || 0 }}</div>
        <div class="stat-label">In Arbeit</div>
      </div>
      <div class="stat-card waiting">
        <div class="stat-value">{{ stats.status_counts.waiting_parts || 0 }}</div>
        <div class="stat-label">Wartet auf Teile</div>
      </div>
      <div class="stat-card completed">
        <div class="stat-value">{{ stats.status_counts.completed || 0 }}</div>
        <div class="stat-label">Erledigt</div>
      </div>
      <div class="stat-card cancelled">
        <div class="stat-value">{{ stats.status_counts.cancelled || 0 }}</div>
        <div class="stat-label">Storniert</div>
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
          Kanban
        </button>
        <button :class="{ active: viewMode === 'table' }" @click="viewMode = 'table'">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
          Tabelle
        </button>
      </div>

      <div class="toolbar-search">
        <GlobalSearchInput
          mode="inline"
          :department-id="currentDepartmentId"
          default-type="reparatur"
          v-model="searchQuery"
          placeholder="Ticket suchen (material:, aktivität:, reparatur:)"
        />
      </div>

      <div class="toolbar-filters">
        <select v-model="filterType">
          <option value="">Alle Typen</option>
          <option value="repair">Reparatur</option>
          <option value="inspection">Inspektion</option>
          <option value="writeoff">Abschreibung</option>
          <option value="cleaning">Reinigung</option>
        </select>
        <select v-model="filterOriginIssueType">
          <option value="">Alle Quellen</option>
          <option value="loss">Nur Verlustmeldungen</option>
          <option value="repair">Nur Reparaturmeldungen</option>
          <option value="damage">Nur Schadensmeldungen</option>
          <option value="consumption">Nur Verbrauchsmeldungen</option>
          <option value="manual">Nur manuell erstellte Tickets</option>
        </select>
        <select v-model="filterPriority">
          <option value="">Alle Prioritäten</option>
          <option value="urgent">Dringend</option>
          <option value="high">Hoch</option>
          <option value="normal">Normal</option>
          <option value="low">Niedrig</option>
        </select>
      </div>
      <div
        v-if="quickFilter"
        style="display:flex; align-items:center; gap:8px; margin-left:auto; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:999px; padding:6px 10px; font-size:12px; font-weight:600;"
      >
        <span>{{ quickFilter === 'waiting_quote' ? 'Schnellfilter: Offerten offen' : 'Schnellfilter: Preis fehlt' }}</span>
        <button
          style="border:none; background:transparent; color:#1d4ed8; cursor:pointer; font-weight:700; font-size:14px; line-height:1;"
          @click="router.replace({ path: route.path, query: { ...route.query, qf: undefined } })"
          title="Filter entfernen"
        >
          ×
        </button>
      </div>
      <div
        v-if="materialFilterId"
        style="display:flex; align-items:center; gap:8px; margin-left:auto; background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; border-radius:999px; padding:6px 10px; font-size:12px; font-weight:600;"
      >
        <span>Nur Material: {{ materialFilterLabel || materialFilterId }}</span>
        <button
          type="button"
          style="border:none; background:transparent; color:#166534; cursor:pointer; font-weight:700; font-size:14px; line-height:1;"
          title="Filter entfernen"
          @click="clearMaterialFilter"
        >
          ×
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="workshop-loading">
      <div class="spinner"></div>
      <p style="margin-top: 12px; color: #6b7280; font-size: 14px;">Tickets werden geladen...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="tickets.length === 0 && !isLoading" class="workshop-empty">
      <svg width="100" height="100" viewBox="0 0 100 100" fill="none">
        <rect x="20" y="25" width="60" height="50" rx="6" stroke="currentColor" stroke-width="2" stroke-dasharray="4 4"/>
        <path d="M40 50L47 57L62 42" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <h2>Keine Werkstatt-Tickets</h2>
      <p>Aktuell gibt es keine offenen Reparaturen, Inspektionen oder Abschreibungen.</p>
      <button @click="showCreateModal = true" class="btn-primary">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
          <path d="M10 4V16M4 10H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        Erstes Ticket erstellen
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
                  :title="'Aus ' + getIssueOriginBadgeLabel(ticket.origin_issue_type) + ' erstellt'"
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
            Keine Tickets
          </div>
        </div>
      </div>
    </div>
    <div v-if="viewMode === 'kanban' && cancelledTickets.length > 0" class="cancelled-section">
      <div class="cancelled-section-header">
        <span>Stornierte Tickets</span>
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
            <th>Status</th>
            <th>Priorität</th>
            <th>Titel</th>
            <th>Material</th>
            <th>Typ</th>
            <th>Zugewiesen</th>
            <th>Erstellt</th>
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
                :title="'Aus ' + getIssueOriginBadgeLabel(ticket.origin_issue_type) + ' erstellt'"
              >
                {{ getIssueOriginBadgeLabel(ticket.origin_issue_type) }}
              </span>
            </td>
            <td>
              <span v-if="ticket.assigned_to" style="display: flex; align-items: center; gap: 6px;">
                <span class="assigned-avatar">{{ getInitials(ticket.assigned_to.name) }}</span>
                {{ ticket.assigned_to.name }}
              </span>
              <span v-else style="color: #9ca3af;">—</span>
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
            <div class="modal-section-title">Material</div>
            <div class="material-info-block">
              <div class="mat-icon-box">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
              </div>
              <div class="mat-details">
                <div class="mat-name">{{ selectedTicket.material_item.name }}</div>
                <div class="mat-meta">
                  <span v-if="selectedTicket.material_item.barcode_tag">Tag: {{ selectedTicket.material_item.barcode_tag }}</span>
                  <span v-if="selectedTicket.material_item.category">{{ selectedTicket.material_item.category.name }}</span>
                  <span>Zustand: {{ conditionLabels[selectedTicket.material_item.condition] || selectedTicket.material_item.condition }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Beschreibung -->
          <div v-if="selectedTicket.description" class="modal-section">
            <div class="modal-section-title">Beschreibung</div>
            <p style="font-size: 14px; color: #374151; line-height: 1.6; margin: 0; white-space: pre-wrap;">{{ selectedTicket.description }}</p>
          </div>

          <!-- Herkunft (Origin) -->
          <div v-if="selectedTicket.activity || selectedTicket.issue_report" class="modal-section">
            <div class="modal-section-title">Herkunft</div>
            <div class="origin-block">
              <!-- Aktivitäts-Info -->
              <div v-if="selectedTicket.activity" class="origin-item">
                <div class="origin-icon">📋</div>
                <div class="origin-details">
                  <div class="origin-label">Aktivität</div>
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
                  <div class="origin-label">Schadensmeldung</div>
                  <div class="origin-value">
                    {{ selectedTicket.issue_report.type_label }}
                    <span v-if="selectedTicket.issue_report.quantity && selectedTicket.issue_report.quantity > 1">
                      ({{ selectedTicket.issue_report.quantity }} Stk.)
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
                    <span v-if="selectedTicket.issue_report.resolved" class="resolved-badge">✓ Gelöst</span>
                    <span v-else class="unresolved-badge">Offen</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Details Grid -->
          <div class="modal-section">
            <div class="modal-section-title">Details</div>
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">Erstellt von</span>
                <span class="detail-value">{{ selectedTicket.created_by?.name || '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Zugewiesen an</span>
                <span class="detail-value">{{ selectedTicket.assigned_to?.name || '— nicht zugewiesen —' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Geschätzte Kosten</span>
                <span class="detail-value">{{ selectedTicket.estimated_cost ? selectedTicket.estimated_cost + ' CHF' : '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Tatsächliche Kosten</span>
                <span class="detail-value">{{ selectedTicket.actual_cost ? selectedTicket.actual_cost + ' CHF' : '—' }}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Erstellt am</span>
                <span class="detail-value">{{ formatDateTime(selectedTicket.created_at) }}</span>
              </div>
              <div v-if="selectedTicket.started_at" class="detail-item">
                <span class="detail-label">Gestartet am</span>
                <span class="detail-value">{{ formatDateTime(selectedTicket.started_at) }}</span>
              </div>
              <div v-if="selectedTicket.completed_at" class="detail-item">
                <span class="detail-label">Abgeschlossen am</span>
                <span class="detail-value">{{ formatDateTime(selectedTicket.completed_at) }}</span>
              </div>
              <div v-if="selectedTicket.resolution_action" class="detail-item">
                <span class="detail-label">Ergebnis</span>
                <span class="detail-value">{{ resolutionLabels[selectedTicket.resolution_action] || selectedTicket.resolution_action }}</span>
              </div>
            </div>
          </div>

          <!-- History Timeline (dynamisch aus API) -->
          <div class="modal-section">
            <div class="modal-section-title">Verlauf</div>
            <div v-if="isLoadingHistory" style="text-align: center; padding: 16px; color: #9ca3af; font-size: 13px;">
              Verlauf wird geladen...
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
                  <div class="timeline-label">Erstellt</div>
                  <div class="timeline-date">{{ formatDateTime(selectedTicket.created_at) }}</div>
                </div>
              </div>
              <div v-if="selectedTicket.started_at" class="timeline-item">
                <div class="timeline-dot active"></div>
                <div class="timeline-content">
                  <div class="timeline-label">Gestartet</div>
                  <div class="timeline-date">{{ formatDateTime(selectedTicket.started_at) }}</div>
                </div>
              </div>
              <div v-if="selectedTicket.completed_at" class="timeline-item">
                <div class="timeline-dot completed"></div>
                <div class="timeline-content">
                  <div class="timeline-label">Abgeschlossen</div>
                  <div class="timeline-date">{{ formatDateTime(selectedTicket.completed_at) }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Resolution Notes -->
          <div v-if="selectedTicket.resolution_notes" class="modal-section">
            <div class="modal-section-title">Abschluss-Notizen</div>
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
            ▶ Arbeit starten
          </button>
          <button
            v-if="selectedTicket.status === 'open' && isLossOriginTicket(selectedTicket)"
            class="btn-danger"
            @click="openLossAcceptModal()"
          >
            ✓ Verlust annehmen
          </button>
          <button
            v-if="selectedTicket.status === 'in_progress'"
            class="btn-warning"
            @click="openQuoteModal()"
          >
            ⏸ Wartet auf Teile
          </button>
          <button
            v-if="selectedTicket.status === 'waiting_parts'"
            class="btn-primary"
            @click="transitionTicket(selectedTicket.id, 'in_progress')"
          >
            ▶ Weiterarbeiten
          </button>
          <button
            v-if="selectedTicket.status === 'in_progress'"
            class="btn-success"
            @click="showCompleteModal = true"
          >
            ✓ Abschließen
          </button>
          <button
            class="btn-danger"
            @click="cancelSelectedTicket()"
          >
            ✕ Ticket stornieren
          </button>
          <button
            class="btn-ghost"
            @click="closeSelectedTicketDetail"
          >
            Schließen
          </button>
        </div>
        <div class="modal-footer" v-else-if="selectedTicket.status === 'cancelled'">
          <button class="btn-secondary" @click="transitionTicket(selectedTicket.id, 'open')">
            ↺ Wiedereröffnen
          </button>
        </div>
      </div>
    </div>

    <!-- === Complete Modal (Abschluss) === -->
    <div v-if="showCompleteModal && selectedTicket" class="workshop-modal-overlay">
      <div class="workshop-modal" style="max-width: 520px;">
        <div class="modal-header">
          <div class="modal-title-group">
            <h2 class="modal-title">Ticket abschließen</h2>
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
              <label>Ergebnis</label>
              <div class="resolution-options">
                <div
                  class="resolution-option"
                  :class="{ selected: completeForm.resolution_action === 'repaired' }"
                  @click="completeForm.resolution_action = 'repaired'"
                >
                  <div class="option-icon">🔧</div>
                  <div class="option-label">Repariert</div>
                  <div class="option-desc">Material wieder OK</div>
                </div>
                <div
                  class="resolution-option"
                  :class="{ selected: completeForm.resolution_action === 'ok' }"
                  @click="completeForm.resolution_action = 'ok'"
                >
                  <div class="option-icon">✅</div>
                  <div class="option-label">In Ordnung</div>
                  <div class="option-desc">Kein Defekt gefunden</div>
                </div>
                <div
                  class="resolution-option"
                  :class="{ selected: completeForm.resolution_action === 'writeoff' }"
                  @click="completeForm.resolution_action = 'writeoff'"
                >
                  <div class="option-icon">🗑️</div>
                  <div class="option-label">Abschreibung</div>
                  <div class="option-desc">Nicht reparierbar</div>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Tatsächliche Kosten (CHF)</label>
              <input v-model="completeForm.actual_cost" type="number" step="0.01" min="0" placeholder="0.00" />
            </div>
            <div class="form-group">
              <label>Abschluss-Notizen</label>
              <textarea v-model="completeForm.resolution_notes" rows="3" placeholder="Was wurde gemacht?"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="showCompleteModal = false">Abbrechen</button>
          <button class="btn-success" @click="completeTicket" :disabled="!completeForm.resolution_action || completionCostMissing">
            ✓ Abschließen
          </button>
        </div>
      </div>
    </div>

    <!-- === Loss Accept Modal === -->
    <div v-if="showLossAcceptModal && selectedTicket" class="workshop-modal-overlay">
      <div class="workshop-modal" style="max-width: 520px;">
        <div class="modal-header">
          <div class="modal-title-group">
            <h2 class="modal-title">Verlust annehmen</h2>
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
            <label>Abzuschreibende Menge</label>
            <input v-model.number="lossAcceptQty" type="number" min="1" step="1" />
          </div>
          <div class="form-group">
            <label>
              Tatsächliche Kosten (CHF)
              <span v-if="isExternalSelectedTicket" style="color: #b91c1c;">*</span>
            </label>
            <input v-model="lossAcceptActualCost" type="number" min="0" step="0.01" placeholder="0.00" />
            <p style="margin-top: 6px; color: #6b7280; font-size: 12px;">
              Vorschlag: {{ lossCostSuggestionLabel }}
            </p>
          </div>
          <p style="margin-top: 8px; color: #6b7280; font-size: 13px;">
            Das Ticket wird als <strong>abgeschlossen</strong> markiert und die Menge wird direkt abgeschrieben.
          </p>
          <p v-if="lossAcceptError" style="margin-top: 10px; color: #b91c1c; font-size: 13px;">
            {{ lossAcceptError }}
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="closeLossAcceptModal" :disabled="isAcceptingLoss">Abbrechen</button>
          <button class="btn-danger" @click="acceptLossTicket" :disabled="isAcceptingLoss || lossAcceptQty < 1 || (isExternalSelectedTicket && !lossAcceptActualCost)">
            {{ isAcceptingLoss ? 'Wird übernommen...' : '✓ Verlust annehmen' }}
          </button>
        </div>
      </div>
    </div>

    <!-- === Quote / Waiting Parts Modal === -->
    <div v-if="showQuoteModal && selectedTicket" class="workshop-modal-overlay">
      <div class="workshop-modal" style="max-width: 520px;">
        <div class="modal-header">
          <div class="modal-title-group">
            <h2 class="modal-title">Offerte / Teile ausstehend</h2>
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
              Geschätzte Kosten / Offerte (CHF)
              <span v-if="isExternalSelectedTicket" style="color: #b91c1c;">*</span>
            </label>
            <input v-model="quoteEstimatedCost" type="number" min="0" step="0.01" placeholder="0.00" />
          </div>
          <div class="form-group">
            <label>Notiz (optional)</label>
            <textarea v-model="quoteNotes" rows="3" placeholder="z.B. Offerte beim Lieferanten angefragt"></textarea>
          </div>
          <p v-if="quoteError" style="margin-top: 10px; color: #b91c1c; font-size: 13px;">
            {{ quoteError }}
          </p>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="closeQuoteModal" :disabled="isSubmittingQuote">Abbrechen</button>
          <button class="btn-warning" @click="submitQuoteTransition" :disabled="isSubmittingQuote || (isExternalSelectedTicket && !quoteEstimatedCost)">
            {{ isSubmittingQuote ? 'Wird gespeichert...' : '⏸ Wartet auf Teile setzen' }}
          </button>
        </div>
      </div>
    </div>

    <!-- === Create Ticket Modal === -->
    <div v-if="showCreateModal" class="workshop-modal-overlay">
      <div class="workshop-modal" style="max-width: 580px;">
        <div class="modal-header">
          <div class="modal-title-group">
            <h2 class="modal-title">Neues Werkstatt-Ticket</h2>
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
              <label>Titel *</label>
              <input v-model="createForm.title" type="text" placeholder="z.B. Zeltstange gebrochen" />
            </div>
            <div class="form-group">
              <label>Material *</label>
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
                <button type="button" class="ws-selected-clear" @click="clearSelectedMaterial" title="Material ändern">
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
                    placeholder="Material suchen (mind. 2 Zeichen)..."
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
                    Suche...
                  </div>
                  <div v-else-if="matSearchResults.length === 0" class="ws-mat-dropdown-msg">
                    Keine Treffer für «{{ matSearchQuery }}»
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
                          <span class="ws-mat-item-stock">{{ mat.total_stock }} Stk.</span>
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
                <label>Typ</label>
                <select v-model="createForm.type">
                  <option value="repair">Reparatur</option>
                  <option value="inspection">Inspektion</option>
                  <option value="writeoff">Abschreibung</option>
                  <option value="cleaning">Reinigung</option>
                </select>
              </div>
              <div class="form-group">
                <label>Priorität</label>
                <select v-model="createForm.priority">
                  <option value="low">Niedrig</option>
                  <option value="normal">Normal</option>
                  <option value="high">Hoch</option>
                  <option value="urgent">Dringend</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Beschreibung</label>
              <textarea v-model="createForm.description" rows="3" placeholder="Details zum Problem..."></textarea>
            </div>
            <div class="form-group">
              <label>Geschätzte Kosten (CHF)</label>
              <input v-model="createForm.estimated_cost" type="number" step="0.01" min="0" placeholder="0.00" />
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="showCreateModal = false">Abbrechen</button>
          <button
            class="btn-primary"
            @click="handleCreateTicket"
            :disabled="!createForm.title || !createForm.material_item_id"
          >
            Ticket erstellen
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
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

// === Labels ===
const statusLabels: Record<string, string> = {
  open: 'Offen',
  in_progress: 'In Arbeit',
  waiting_parts: 'Wartet auf Teile',
  completed: 'Erledigt',
  cancelled: 'Storniert',
}

const priorityLabels: Record<string, string> = {
  low: 'Niedrig',
  normal: 'Normal',
  high: 'Hoch',
  urgent: 'Dringend',
}

const typeLabels: Record<string, string> = {
  repair: 'Reparatur',
  inspection: 'Inspektion',
  writeoff: 'Abschreibung',
  cleaning: 'Reinigung',
}

const conditionLabels: Record<string, string> = {
  ok: 'OK',
  defect: 'Defekt',
  repair: 'Reparatur',
  lost: 'Verloren',
}

const resolutionLabels: Record<string, string> = {
  repaired: 'Repariert',
  ok: 'In Ordnung',
  writeoff: 'Abgeschrieben',
}

const issueTypeLabels: Record<string, string> = {
  repair: 'Reparatur',
  damage: 'Schaden',
  loss: 'Verlust',
  consumption: 'Verbrauch',
}

function getIssueOriginBadgeLabel(issueType: string): string {
  const map: Record<string, string> = {
    repair: 'Reparaturmeldung',
    damage: 'Schadensmeldung',
    loss: 'Verlustmeldung',
    consumption: 'Verbrauchsmeldung',
  }
  return map[issueType] || 'Meldung'
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
const kanbanColumns = [
  { status: 'open' as TicketStatus, label: 'Offen' },
  { status: 'in_progress' as TicketStatus, label: 'In Arbeit' },
  { status: 'waiting_parts' as TicketStatus, label: 'Wartet auf Teile' },
  { status: 'completed' as TicketStatus, label: 'Erledigt' },
]

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
  return `CHF ${amount.toFixed(2)}`
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
    toast.success('Werkstatt-Ticket erstellt')
  } catch (err: any) {
    console.error('Failed to create ticket:', err)
    toast.error('Fehler beim Erstellen: ' + (err.response?.data?.error || err.message))
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
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
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
    toast.success('Ticket erfolgreich abgeschlossen')
  } catch (err: any) {
    console.error('Complete failed:', err)
    toast.error('Fehler beim Abschließen: ' + (err.response?.data?.error || err.message))
  }
}

async function cancelSelectedTicket() {
  if (!selectedTicket.value) return
  const ticketId = selectedTicket.value.id

  try {
    await transitionWorkshopTicket(ticketId, { status: 'cancelled' })
    closeSelectedTicketDetail()
    await loadData()
    toast.info('Ticket storniert und geschlossen')
  } catch (err: any) {
    console.error('Cancel failed:', err)
    toast.error('Fehler beim Stornieren: ' + (err.response?.data?.error || err.message))
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
    quoteError.value = 'Für externe Fälle ist ein geschätzter Preis erforderlich.'
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
    toast.info('Ticket auf "Wartet auf Teile" gesetzt')
  } catch (err: any) {
    console.error('Quote transition failed:', err)
    quoteError.value = err.response?.data?.error || err.message || 'Unbekannter Fehler'
    toast.error('Fehler: ' + quoteError.value)
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
      resolution_notes: 'Verlustmeldung angenommen und abgeschrieben.',
    })

    closeLossAcceptModal()
    await loadData()

    const [detailed, history] = await Promise.all([
      getWorkshopTicket(ticketId),
      getWorkshopTicketHistory(ticketId),
    ])
    selectedTicket.value = detailed
    ticketHistory.value = history
    toast.success(`Verlust angenommen und ${writeoffQty} Stk. abgeschrieben`)
  } catch (err: any) {
    console.error('Loss acceptance failed:', err)
    lossAcceptError.value = err.response?.data?.error || err.message || 'Unbekannter Fehler'
    toast.error('Fehler beim Annehmen des Verlusts: ' + lossAcceptError.value)
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

  if (changes.status) {
    const oldLabel = statusLabels[changes.status.old] || changes.status.old
    const newLabel = statusLabels[changes.status.new] || changes.status.new
    parts.push(`Status: ${oldLabel} → ${newLabel}`)
  }

  if (changes.priority) {
    if (typeof changes.priority === 'object' && changes.priority.old) {
      const oldLabel = priorityLabels[changes.priority.old] || changes.priority.old
      const newLabel = priorityLabels[changes.priority.new] || changes.priority.new
      parts.push(`Priorität: ${oldLabel} → ${newLabel}`)
    }
  }

  if (changes.title && typeof changes.title === 'object') {
    parts.push(`Titel geändert`)
  }

  if (changes.assigned_to_user_id) {
    parts.push(changes.assigned_to_user_id.new ? 'Zugewiesen' : 'Zuweisung entfernt')
  }

  if (changes.resolution_action) {
    const label = resolutionLabels[changes.resolution_action] || changes.resolution_action
    parts.push(`Ergebnis: ${label}`)
  }

  if (changes.material_condition) {
    const oldLabel = conditionLabels[changes.material_condition.old] || changes.material_condition.old
    const newLabel = conditionLabels[changes.material_condition.new] || changes.material_condition.new
    parts.push(`Material: ${oldLabel} → ${newLabel}`)
  }

  if (changes.actual_cost) {
    parts.push(`Kosten: ${changes.actual_cost} CHF`)
  }

  if (changes.writeoff_qty) {
    parts.push(`Abgeschrieben: ${changes.writeoff_qty} Stk.`)
  }

  if (changes.issue_report_resolved) {
    parts.push('Schadensmeldung als gelöst markiert')
  }

  // Auto-Erstellung Quellen
  if (changes.source === 'issue_report') {
    parts.push(`Aus Aktivität „${changes.activity_name || '?'}"`)
    if (changes.issue_report_type) {
      const typeLabel = issueTypeLabels[changes.issue_report_type] || changes.issue_report_type
      parts.push(`Meldungstyp: ${typeLabel}`)
    }
  }
  if (changes.source === 'return_item') {
    parts.push(`Aus Rückgabe Aktivität „${changes.activity_name || '?'}"`)
    if (changes.condition_in) {
      parts.push(`Zustand bei Rückgabe: ${changes.condition_in}`)
    }
  }

  if (changes.resolution_notes && typeof changes.resolution_notes === 'string') {
    parts.push(`Notiz: ${changes.resolution_notes}`)
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
    case 'physical': return 'Einzelmaterial'
    case 'physical_combo': return 'Kombination (physisch)'
    case 'virtual_combo': return 'Kombination (virtuell)'
    default: return 'Material'
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
  return date.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit' })
}

function formatDateTime(dateStr: string): string {
  const date = new Date(dateStr)
  const tz = authStore.departmentTimezone || 'Europe/Zurich'
  return date.toLocaleDateString('de-CH', {
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
