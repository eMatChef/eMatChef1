<template>
  <div class="activity-detail-panel">
      <div class="detail-header">
        <button class="detail-back-btn" @click="id.closeDetail">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><polyline points="15 18 9 12 15 6"/></svg>
          Zurück
        </button>
        <div class="detail-title-row">
          <span class="type-badge" :class="id.selectedActivity.type">{{ id.getTypeLabel(id.selectedActivity.type) }}</span>
          <h1 class="detail-title">{{ id.selectedActivity.name }}</h1>
          <span class="status-label" :class="id.selectedActivity.status">{{ id.getStatusLabel(id.selectedActivity.status) }}</span>
        </div>
        <div class="detail-subtitle-row">
          <span v-if="id.selectedActivity.groupName" class="detail-subtitle-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            {{ id.selectedActivity.groupName }}
          </span>
          <span v-if="id.activityDetail?.customer_name" class="detail-subtitle-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            {{ id.activityDetail.customer_name }}
          </span>
          <span v-if="id.selectedActivity.type === 'external'" class="detail-subtitle-badge external">Extern</span>
          <span class="detail-subtitle-sep" v-if="(id.selectedActivity.groupName || id.activityDetail?.customer_name) && id.activityDetail?.usage_start">·</span>
          <span v-if="id.activityDetail?.usage_start" class="detail-subtitle-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            {{ id.formatDateShort(id.activityDetail.usage_start) }}
            <template v-if="id.activityDetail.usage_end && !id.isSameDay(id.activityDetail.usage_start, id.activityDetail.usage_end)">
              – {{ id.formatDateShort(id.activityDetail.usage_end) }}
            </template>
          </span>
          <!-- Material-Zeitraum: Abholung bis Rückgabe -->
          <template v-if="id.activityDetail?.planning_start">
            <span class="detail-subtitle-sep">·</span>
            <span class="detail-subtitle-item detail-subtitle-planning">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
              {{ id.formatDateTimeShort(id.activityDetail.planning_start) }}
              – {{ id.formatDateTimeShort(id.activityDetail.planning_end || id.activityDetail.planning_start) }}
            </span>
          </template>
        </div>
        <div v-if="id.activityDetail?.notes" class="detail-notes-bar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <span class="detail-notes-text">{{ id.activityDetail.notes }}</span>
        </div>
        <div class="detail-actions">
          <!-- Workflow-Buttons: Pack-Workflow-Transitions (packed/issued/returned) navigieren zum Auftrag-Status-Tab -->
          <template v-for="transition in availableTransitionsList">
            <!-- Pack-Workflow: Button navigiert zum Auftrag-Status-Tab statt Status direkt zu ändern -->
            <button 
              v-if="transition.allowed && transition.status !== 'cancelled' && id.isPackWorkflowTarget(transition.status)"
              :key="'nav-' + transition.status"
              class="btn btn-sm"
              :class="id.getTransitionBtnClass(transition.status)"
              @click="id.navigateToPackTab(transition.status)"
            >
              <svg v-if="id.getTransitionIcon(transition.status)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" v-html="id.getTransitionIcon(transition.status)"></svg>
              {{ transition.label }}
            </button>
            <!-- Nicht-Pack-Transitions: direkter Statuswechsel -->
            <button 
              v-else-if="transition.allowed && transition.status !== 'cancelled' && !id.isPackWorkflowTarget(transition.status)"
              :key="transition.status"
              class="btn btn-sm"
              :class="id.getTransitionBtnClass(transition.status)"
              @click="id.handleTransition(transition.status)"
            >
              <svg v-if="id.getTransitionIcon(transition.status)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" v-html="id.getTransitionIcon(transition.status)"></svg>
              {{ transition.label }}
            </button>
          </template>
          <!-- Stornieren (spezielle Darstellung) -->
          <button 
            v-if="availableTransitionsList.some(t => t.status === 'cancelled' && t.allowed)"
            class="btn btn-sm btn-danger-outline" 
            @click="id.cancelActivity"
          >
            Stornieren
          </button>
        </div>
      </div>

      <!-- Hinweis-Banner für Entwürfe -->
      <div v-if="id.selectedActivity.status === 'draft'" class="draft-hint-banner">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <span>Diese Aktivität ist noch ein <strong>Entwurf</strong>. Bitte prüfe alle Angaben und das Material und klicke dann auf <strong>«Einreichen»</strong>, um sie an den Materialwart zu senden.</span>
      </div>

      <!-- Detail-Tabs -->
      <div class="detail-tab-bar">
        <button 
          v-for="dt in id.detailTabs" :key="dt.key"
          class="detail-tab" 
          :class="{ active: id.activeDetailTab === dt.key }"
          @click="id.switchDetailTab(dt.key)"
        >{{ dt.label }}</button>
      </div>

      <!-- Tab: Übersicht -->
      <div v-if="id.activeDetailTab === 'overview'" class="detail-body">

        <!-- Bearbeiten-Button (nur im Entwurf) -->
        <div v-if="id.isDraftEditable && !id.isEditingDraft" class="draft-edit-bar">
          <button class="btn btn-sm btn-outline" @click="id.startEditDraft">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Angaben bearbeiten
          </button>
        </div>

        <!-- ═══ EDIT-MODUS ═══ -->
        <div v-if="id.isEditingDraft" class="detail-grid">
          <!-- Name -->
          <div class="detail-card detail-card-full">
            <h3 class="detail-card-title">Name</h3>
            <input v-model="id.draftEditData.name" type="text" class="form-input" placeholder="Name der Aktivität" />
          </div>

          <!-- Zeitraum -->
          <div class="detail-card">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Zeitraum
            </h3>
            <div class="detail-field">
              <span class="detail-label">Nutzung von</span>
              <input v-model="id.draftEditData.usage_start" type="datetime-local" step="900" class="form-input" @click="id.tryShowNativePicker" @change="id.draftEditData.usage_start = id.snapDatetimeLocalToStep(id.draftEditData.usage_start); id.onDraftDateChange()" />
            </div>
            <div class="detail-field">
              <span class="detail-label">Nutzung bis</span>
              <input v-model="id.draftEditData.usage_end" type="datetime-local" step="900" class="form-input" @click="id.tryShowNativePicker" @change="id.draftEditData.usage_end = id.snapDatetimeLocalToStep(id.draftEditData.usage_end); id.onDraftDateChange()" />
            </div>
            <div class="detail-field">
              <span class="detail-label">Material Abholung</span>
              <input v-model="id.draftEditData.planning_start" type="datetime-local" step="900" class="form-input" @click="id.tryShowNativePicker" @change="id.draftEditData.planning_start = id.snapDatetimeLocalToStep(id.draftEditData.planning_start)" />
            </div>
            <div class="detail-field">
              <span class="detail-label">Material Rückgabe</span>
              <input v-model="id.draftEditData.planning_end" type="datetime-local" step="900" class="form-input" @click="id.tryShowNativePicker" @change="id.draftEditData.planning_end = id.snapDatetimeLocalToStep(id.draftEditData.planning_end)" />
            </div>

            <!-- Warnung: Datum-Änderung mit vorhandenem Material -->
            <div v-if="id.showDateChangeWarning" class="date-change-warning">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
              <div class="date-change-warning-content">
                <strong>Achtung:</strong> Es sind bereits <strong>{{ id.detailItems.length }} Material-Position{{ id.detailItems.length !== 1 ? 'en' : '' }}</strong> erfasst.
                Bei einer Datum-Änderung muss die Verfügbarkeit neu geprüft werden.
                Bitte entferne zuerst das Material und füge es nach der Datum-Änderung erneut hinzu.
                <button class="btn btn-sm btn-danger" style="margin-top: 8px;" @click="id.removeAllMaterialsAndSave">
                  Alle Materialien entfernen &amp; speichern
                </button>
              </div>
            </div>
          </div>

          <!-- Gruppen nach Department (Host + eingeladen) -->
          <div v-if="id.selectedActivity.type !== 'external'" class="detail-card detail-card-full">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              Gruppen nach Department
            </h3>
            <p class="text-muted draft-group-intro">
              Pro Department die Gruppe wählen, die an der Aktivität beteiligt ist (Organisator und eingeladene Departments).
            </p>
            <div class="draft-group-by-dept">
              <div class="draft-group-dept-block">
                <label class="draft-group-dept-label">{{ id.activityDetail?.department_name || 'Organisator' }} <span class="dept-role-tag">Organisator</span></label>
                <select v-model="id.draftEditData.group_id" class="form-input">
                  <option :value="null">– Keine Gruppe –</option>
                  <option v-for="grp in id.myGroups" :key="grp.id" :value="grp.id">{{ grp.name }}</option>
                </select>
              </div>
              <div
                v-for="inv in id.draftEditInvitedDepartments"
                :key="`draft-inv-${inv.id}`"
                class="draft-group-dept-block"
              >
                <label class="draft-group-dept-label">
                  {{ inv.name || inv.id }}
                  <span v-if="inv.organisation_name" class="text-muted"> ({{ inv.organisation_name }})</span>
                  <span class="invite-status-badge invite-status-badge--inline" :class="inv.status === 'accepted' ? 'accepted' : inv.status === 'rejected' ? 'rejected' : 'pending'">
                    {{ inv.status === 'accepted' ? 'Angenommen' : inv.status === 'rejected' ? 'Abgelehnt' : 'Ausstehend' }}
                  </span>
                </label>
                <select
                  class="form-input"
                  :value="inv.group_id || ''"
                  @focus="id.loadDraftEditInviteGroups(inv.id)"
                  @change="id.setDraftEditInviteGroup(inv.id, ($event.target as HTMLSelectElement).value)"
                >
                  <option value="">– Keine Gruppe –</option>
                  <option v-for="g in (id.draftEditInviteGroupsById[inv.id] || [])" :key="g.id" :value="g.id">{{ g.name }}</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Notizen -->
          <div class="detail-card detail-card-full">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Notizen
            </h3>
            <textarea v-model="id.draftEditData.notes" class="form-input form-textarea" rows="3" placeholder="Optionale Notizen..."></textarea>
          </div>

          <!-- Aktions-Buttons -->
          <div class="detail-card detail-card-full draft-edit-actions">
            <button class="btn btn-primary" @click="id.saveDraftEdit" :disabled="id.showDateChangeWarning && id.detailItems.length > 0">
              Änderungen speichern
            </button>
            <button class="btn btn-outline" @click="id.cancelEditDraft">Abbrechen</button>
          </div>
        </div>

        <!-- ═══ LESE-MODUS ═══ -->
        <div v-else class="detail-grid">
          <div class="detail-card">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Zeitraum
            </h3>
            <div class="detail-field">
              <span class="detail-label">Nutzung</span>
              <span class="detail-value">
                <template v-if="id.activityDetail?.usage_start">
                  {{ id.formatDateTime(id.activityDetail.usage_start) }} – {{ id.formatDateTime(id.activityDetail.usage_end || '') }}
                </template>
                <span v-else class="text-muted">Nicht festgelegt</span>
              </span>
            </div>
            <div v-if="id.activityDetail?.planning_start" class="detail-field">
              <span class="detail-label">Material Abhol-/Rückgabe</span>
              <span class="detail-value">
                {{ id.formatDateTime(id.activityDetail.planning_start) }} – {{ id.formatDateTime(id.activityDetail.planning_end || '') }}
              </span>
            </div>
          </div>

          <div
            v-if="id.selectedActivity.type !== 'external' && (id.activityDetail?.invited_departments?.length || id.activityDetail?.department_name)"
            class="detail-card detail-card-full"
          >
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              Departments &amp; Beteiligung
            </h3>
            <ul class="dept-participation-list">
              <li class="dept-participation-row">
                <span class="dept-participation-name">{{ id.activityDetail?.department_name || '—' }}</span>
                <span class="dept-participation-badge host">Organisator</span>
                <span class="dept-participation-group">
                  <template v-if="id.selectedActivity.groupName || id.activityDetail?.group_name">
                    Gruppe: {{ id.selectedActivity.groupName || id.activityDetail?.group_name }}
                  </template>
                  <span v-else class="text-muted">Keine Gruppe zugeordnet</span>
                </span>
              </li>
              <li
                v-for="inv in (id.activityDetail?.invited_departments || [])"
                :key="`part-${inv.id}`"
                class="dept-participation-row"
              >
                <span class="dept-participation-name">
                  {{ inv.name || inv.id }}
                  <span v-if="inv.organisation_name" class="text-muted"> ({{ inv.organisation_name }})</span>
                </span>
                <span
                  class="dept-participation-badge invite-status-badge"
                  :class="inv.status === 'accepted' ? 'accepted' : inv.status === 'rejected' ? 'rejected' : 'pending'"
                >
                  {{ inv.status === 'accepted' ? 'Angenommen' : inv.status === 'rejected' ? 'Abgelehnt' : 'Ausstehend' }}
                </span>
                <span class="dept-participation-group">
                  <template v-if="inv.group_name">Gruppe: {{ inv.group_name }}</template>
                  <span v-else-if="inv.status === 'accepted'" class="text-muted">Gruppe: noch nicht festgelegt</span>
                  <span v-else class="text-muted">—</span>
                </span>
              </li>
            </ul>
            <p class="dept-participation-hint text-muted">
              Gruppenleiter der zugeordneten Gast-Gruppe können nach Annahme der Einladung mitarbeiten (Material, Workflow).
            </p>
          </div>

          <div class="detail-card">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              {{ id.selectedActivity.type === 'external' ? 'Kunde' : 'Gruppe' }}
            </h3>
            <div v-if="id.selectedActivity.groupName" class="detail-field">
              <span class="detail-label">Gruppe</span>
              <span class="detail-value">{{ id.selectedActivity.groupName }}</span>
            </div>
            <div v-if="id.activityDetail?.customer_name" class="detail-field">
              <span class="detail-label">Kunde</span>
              <span class="detail-value">{{ id.activityDetail.customer_name }}</span>
            </div>
            <div v-if="id.activityDetail?.customer_email" class="detail-field">
              <span class="detail-label">E-Mail</span>
              <span class="detail-value"><a :href="'mailto:' + id.activityDetail.customer_email">{{ id.activityDetail.customer_email }}</a></span>
            </div>
            <div v-if="id.activityDetail?.customer_phone" class="detail-field">
              <span class="detail-label">Telefon</span>
              <span class="detail-value">{{ id.activityDetail.customer_phone }}</span>
            </div>
            <div v-if="!id.selectedActivity.groupName && !id.activityDetail?.customer_name" class="detail-field">
              <span class="text-muted">Keine Zuordnung</span>
            </div>
          </div>

          <div v-if="id.selectedActivity.type === 'external'" class="detail-card">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              Finanzen
            </h3>
            <div class="detail-field">
              <span class="detail-label">Preismodus</span>
              <span class="detail-value">
                <span class="pricing-mode-badge" :class="id.activityDetail?.pricing_mode || 'item_price'">
                  {{ id.activityDetail?.pricing_mode === 'set_price' ? '📦 Setpreis' : '📋 Einzelpreis' }}
                </span>
              </span>
            </div>
            <div class="detail-field">
              <span class="detail-label">Gesamtpreis</span>
              <span class="detail-value detail-price">{{ id.activityDetail?.total_price ? 'CHF ' + id.activityDetail.total_price.toFixed(2) : '–' }}</span>
            </div>
            <!-- Mini-Rechner bei Einzelpreis: zeige Aufschlüsselung -->
            <div v-if="id.activityDetail?.pricing_mode !== 'set_price' && id.detailItems.length > 0" class="detail-price-breakdown">
              <div v-for="di in id.detailItems.filter(i => i.lineTotal)" :key="di.id" class="breakdown-row">
                <span class="breakdown-name">{{ di.materialName }}</span>
                <span class="breakdown-calc">{{ di.quantity }} × CHF {{ ((di.unitPrice || 0)).toFixed(2) }}</span>
                <span class="breakdown-total">CHF {{ (di.lineTotal || 0).toFixed(2) }}</span>
              </div>
            </div>
            <div class="detail-field">
              <span class="detail-label">Kaution</span>
              <span class="detail-value">
                {{ id.activityDetail?.deposit_amount ? 'CHF ' + id.activityDetail.deposit_amount.toFixed(2) : '–' }}
                <span v-if="id.activityDetail?.deposit_paid" class="badge-green">Bezahlt</span>
                <span v-else-if="id.activityDetail?.deposit_amount" class="badge-yellow">Offen</span>
              </span>
            </div>
            <div class="detail-field">
              <span class="detail-label">Bezahlung</span>
              <span class="detail-value">
                <span v-if="id.activityDetail?.is_paid" class="badge-green">Bezahlt</span>
                <span v-else class="badge-yellow">Offen</span>
              </span>
            </div>
          </div>

          <div v-if="id.activityDetail?.notes || id.isDraftEditable" class="detail-card detail-card-full">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Notizen
            </h3>
            <p v-if="id.activityDetail?.notes" class="detail-notes">{{ id.activityDetail.notes }}</p>
            <p v-else class="text-muted">Keine Notizen</p>
          </div>
        </div>

        <div class="detail-meta">
          <span>Erstellt: {{ id.formatDateTime(id.selectedActivity.createdAt) }}</span>
          <span>Aktualisiert: {{ id.formatDateTime(id.selectedActivity.updatedAt) }}</span>
          <span v-if="id.selectedActivity.no" class="detail-meta-id">#{{ id.selectedActivity.no }}</span>
        </div>
      </div>

      <!-- Tab: Eingeladene Departments -->
      <div v-if="id.activeDetailTab === 'invited_departments'" class="detail-body">
        <div class="detail-card detail-card-full">
          <h3 class="detail-card-title">Eingeladene Departments</h3>
          <div v-if="id.invitedDepartmentsDetail.length === 0" class="empty-hint">
            <p>Keine Departments eingeladen.</p>
          </div>
          <div v-else class="department-invited-list">
            <div v-for="dep in id.invitedDepartmentsDetail" :key="`detail-invited-${dep.id || dep.name}`" class="department-invited-item department-invited-item-with-group">
              <div class="department-invited-main">
                <span>{{ dep.name }}<template v-if="dep.organisation_name"> ({{ dep.organisation_name }})</template></span>
                <span class="invite-status-badge" :class="dep.status || 'pending'">
                  {{ dep.status === 'accepted' ? 'Angenommen' : dep.status === 'rejected' ? 'Abgelehnt' : 'Ausstehend' }}
                </span>
              </div>
              <div v-if="dep.group_name" class="department-invited-group text-muted">Gruppe: {{ dep.group_name }}</div>
              <div v-else-if="dep.status === 'accepted'" class="department-invited-group text-muted">Gruppe: noch nicht festgelegt</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab: Material -->
      <div v-if="id.activeDetailTab === 'material'" class="detail-body">
        <!-- Material hinzufügen (Draft: alle mit Edit-Rolle, danach: MW+ bis issued) -->
        <div v-if="id.canMwAddMaterial" class="detail-material-add">
          <div class="detail-material-add-header">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Material hinzufügen
            </h3>
          </div>
          <div class="mat-source-switch mat-source-switch-wrap" role="tablist" aria-label="Materialquelle Detailansicht">
            <button
              type="button"
              class="mat-source-btn"
              :class="{ active: id.detailMaterialSource === 'internal' && id.detailInternalScope === 'own' }"
              @click="id.setDetailMaterialInternalScope('own')"
            >
              Eigenes
            </button>
            <button
              v-for="dep in id.acceptedInvitedDepartmentsForTabs"
              :key="`mat-tab-inv-${dep.id}`"
              type="button"
              class="mat-source-btn mat-source-btn-dept"
              :class="{
                active:
                  id.detailMaterialSource === 'internal' &&
                  id.detailInternalScope === 'single' &&
                  id.detailSingleDepartmentId === dep.id,
              }"
              :title="dep.organisation_name ? `${dep.name} (${dep.organisation_name})` : dep.name"
              @click="id.setDetailInvitedDepartmentTab(dep.id)"
            >
              {{ id.truncateDeptTabLabel(dep.name) }}
            </button>
            <button
              type="button"
              class="mat-source-btn"
              :class="{ active: id.detailMaterialSource === 'internal' && id.detailInternalScope === 'both' }"
              title="Eigenes Lager und alle eingeladenen Departments"
              @click="id.setDetailMaterialInternalScope('both')"
            >
              Alle
            </button>
            <button
              type="button"
              class="mat-source-btn"
              :class="{ active: id.detailMaterialSource === 'js' }"
              :disabled="!id.canUseDetailJsMaterialSource"
              @click="id.setDetailMaterialSource('js')"
            >
              J&amp;S
            </button>
          </div>
          <p v-if="id.acceptedInvitedDepartmentsForTabs.length > 0" class="mat-source-hint">
            Pro Reiter ein eingeladenes Department; «Alle» sucht bei dir und bei allen angenommenen Einladungen ({{ id.acceptedInvitedDepartmentsForTabs.length }}).
          </p>
          <p v-else-if="!id.canUseDetailJsMaterialSource" class="mat-source-hint">J&amp;S-Material ist nur bei Event oder Camp verfügbar.</p>
          <div class="detail-material-search-wrapper">
            <MaterialLookupInput
              v-model="id.detailMatSearch"
              :fetcher="id.detailMaterialLookupFetcher"
              :min-chars="1"
              :max-suggestions="20"
              placeholder="Material suchen (z.B. Zelt, Kocher, Blache...)"
              :input-class="'detail-mat-search-input'"
              :loading-text="'Suche...'"
              :empty-text="`Keine Treffer für «${id.detailMatSearch}»`"
              @select="id.handleDetailLookupSelect"
            >
              <template #results="{ results, isLoading, activeIndex, setActiveIndex }">
                <div v-if="isLoading" class="mat-dropdown-loading">Suche...</div>
                <div v-else-if="results.length === 0" class="mat-dropdown-empty">
                  Keine Treffer für «{{ id.detailMatSearch }}»
                </div>
                <div v-else class="mat-dropdown-list">
                  <div
                    v-for="(mat, index) in results"
                    :key="mat.materialItemId"
                    class="mat-dropdown-item"
                    :class="{
                      active: activeIndex === index,
                      'already-added': id.detailItems.some(i => i.materialItemId === mat.materialItemId),
                      'unavailable': mat.availableForPeriod <= 0
                    }"
                    @mouseenter="setActiveIndex(index)"
                  >
                    <div class="mat-dropdown-info">
                      <span class="mat-dropdown-name">
                        <span v-if="mat.isConsumable" class="mat-type-icon consumable" title="Verbrauchsmaterial">🔥</span>
                        <span v-else class="mat-type-icon rental" title="Ausleihmaterial">📦</span>
                        {{ mat.name }}
                        <span v-if="mat.isJsMaterial" class="mat-source-badge">J&amp;S</span>
                        <span v-if="mat.packSize && mat.packUnit" class="mat-pack-badge">{{ mat.packSize }}&thinsp;Stk./{{ mat.packUnit }}</span>
                      </span>
                      <span class="mat-dropdown-meta">
                        <span class="mat-dropdown-stock">
                          <span :class="mat.availableForPeriod > 0 ? 'text-green' : 'text-red'">
                            {{ mat.availableForPeriod }}
                          </span>
                          <span class="text-muted"> / {{ mat.totalStock }}</span>
                          <span v-if="mat.packSize && mat.packUnit" class="text-muted">
                            ({{ Math.floor(mat.availableForPeriod / mat.packSize) }} {{ mat.packUnit }})
                          </span>
                        </span>
                        <span class="text-muted">Von: {{ id.getMaterialSourceLabel(mat) }}</span>
                        <span v-if="mat.isConsumable && mat.salePrice" class="mat-dropdown-price consumable">
                          CHF {{ Number(mat.salePrice).toFixed(2) }}/Stk
                        </span>
                      </span>
                    </div>
                    <div class="mat-dropdown-actions">
                      <template v-if="id.detailItems.some(i => i.materialItemId === mat.materialItemId)">
                        <span class="mat-already-badge">✓ vorhanden</span>
                      </template>
                      <template v-else-if="mat.availableForPeriod > 0">
                        <template v-if="mat.packSize && mat.packSize > 1">
                          <button v-if="mat.availableForPeriod >= mat.packSize" class="mat-quick-btn mat-set-btn" @click="id.addDetailMaterial(mat, mat.packSize)" :title="'1 ' + (mat.packUnit || 'Set')">1 {{ mat.packUnit || 'Set' }}</button>
                          <button v-if="mat.availableForPeriod >= mat.packSize * 5" class="mat-quick-btn mat-set-btn" @click="id.addDetailMaterial(mat, mat.packSize * 5)" :title="'5 ' + (mat.packUnit || 'Sets')">5 {{ mat.packUnit || 'Sets' }}</button>
                          <span class="mat-btn-divider">|</span>
                        </template>
                        <button class="mat-quick-btn" @click="id.addDetailMaterial(mat, 1)" title="+1">+1</button>
                        <button v-if="mat.availableForPeriod >= 5" class="mat-quick-btn" @click="id.addDetailMaterial(mat, 5)" title="+5">+5</button>
                        <button v-if="mat.availableForPeriod >= 10" class="mat-quick-btn" @click="id.addDetailMaterial(mat, 10)" title="+10">+10</button>
                      </template>
                      <template v-else>
                        <span class="mat-unavailable-badge">nicht verfügbar</span>
                      </template>
                    </div>
                  </div>
                </div>
              </template>
            </MaterialLookupInput>
          </div>
        </div>

        <!-- Materialliste -->
        <div v-if="id.isLoadingDetailItems" class="loading-hint">Materialien werden geladen...</div>
        <div v-else-if="id.filteredDetailItems.length === 0 && !id.canEditMaterial" class="empty-hint">
          <p>Keine Materialien zugeordnet.</p>
        </div>
        <div v-else-if="id.filteredDetailItems.length === 0 && id.canEditMaterial" class="empty-hint">
          <p>Noch keine Materialien – nutze die Suche oben um Material hinzuzufügen.</p>
        </div>
        <div v-else class="detail-material-list">
          <div class="detail-material-header">
            <span>Material</span>
            <span>Von wem</span>
            <span>Menge</span>
          </div>
          <div
            v-for="item in id.filteredDetailItems"
            :key="item.id"
            class="detail-material-row"
            :class="{ 'row-clickable': id.canEditMaterial && ['draft', 'submitted', 'approved'].includes(id.selectedActivity?.status || '') }"
            @dblclick="id.onMaterialRowDblClick(item)"
          >
            <div class="detail-material-info">
              <span class="detail-material-name">
                <span v-if="item.isConsumable" class="mat-type-icon consumable" title="Verbrauchsmaterial">🔥</span>
                <span v-else class="mat-type-icon rental" title="Ausleihmaterial">📦</span>
                {{ item.materialName }}
                <span v-if="item.isJsMaterial" class="mat-source-badge">J&amp;S</span>
                <span v-if="item.packSize && item.packUnit" class="mat-pack-badge">{{ item.packSize }}&thinsp;Stk./{{ item.packUnit }}</span>
              </span>
              <span v-if="item.isConsumable && item.salePrice" class="detail-material-price">
                CHF {{ Number(item.salePrice).toFixed(2) }}/Stk
                <span v-if="item.quantity > 1" class="detail-material-total-price">= CHF {{ (Number(item.salePrice) * item.quantity).toFixed(2) }}</span>
              </span>
            </div>
            <div class="detail-material-origin">{{ id.getMaterialSourceLabel(item) }}</div>
            <div class="detail-material-qty">
              <!-- Entwurf: +/- Buttons -->
              <template v-if="id.selectedActivity?.status === 'draft' && id.canEditMaterial">
                <button class="btn-qty" @click="id.changeDetailMaterialQty(item, -1)">−</button>
                <strong>{{ item.quantity }}</strong> Stk.
                <span v-if="item.packSize && item.packUnit" class="mat-qty-sets">({{ Math.floor(item.quantity / item.packSize) }} {{ item.packUnit }})</span>
                <button class="btn-qty" @click="id.changeDetailMaterialQty(item, 1)">+</button>
                <button class="btn-remove-sm" @click="id.removeDetailMaterial(item)" title="Entfernen">✕</button>
              </template>
              <!-- Eingereicht/Bestätigt: gesperrt, MW kann über Modal ändern -->
              <template v-else>
                <strong>{{ item.quantity }}</strong> Stk.
                <span v-if="item.packSize && item.packUnit" class="mat-qty-sets">({{ Math.floor(item.quantity / item.packSize) }} {{ item.packUnit }})</span>
                <button
                  v-if="id.canMwAdjustMaterial"
                  class="btn-adjust-sm"
                  @click="id.openAdjustModal(item)"
                  title="Bestand ändern"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
              </template>
            </div>
          </div>
          <div class="detail-material-total">
            <span>Gesamt: {{ id.filteredDetailItems.reduce((s, i) => s + i.quantity, 0) }} Stück · {{ id.filteredDetailItems.length }} Position{{ id.filteredDetailItems.length !== 1 ? 'en' : '' }}</span>
          </div>
        </div>

        <!-- Bestand-Ändern-Modal -->
        <Teleport to="body">
          <div v-if="id.showAdjustModal" class="modal-overlay">
            <div class="modal-dialog adjust-modal">
              <div class="modal-header">
                <h3>Bestand ändern</h3>
                <button class="modal-close" @click="id.closeAdjustModal">&times;</button>
              </div>
              <div class="modal-body">
                <div class="adjust-material-name">
                  {{ id.adjustItem?.materialName }}
                  <span v-if="id.adjustPackSize && id.adjustPackUnit" class="mat-pack-badge">{{ id.adjustPackSize }}&thinsp;Stk./{{ id.adjustPackUnit }}</span>
                </div>
                <div class="adjust-info-grid">
                  <div class="adjust-info-item">
                    <span class="adjust-info-label">Aktuell bestellt</span>
                    <span class="adjust-info-value">
                      {{ id.adjustItem?.quantity }} Stk.
                      <span v-if="id.adjustPackSize && id.adjustPackUnit" class="adjust-info-sets">
                        ({{ Math.floor((id.adjustItem?.quantity || 0) / id.adjustPackSize) }} {{ id.adjustPackUnit }}<span v-if="(id.adjustItem?.quantity || 0) % id.adjustPackSize !== 0"> +{{ (id.adjustItem?.quantity || 0) % id.adjustPackSize }}</span>)
                      </span>
                    </span>
                  </div>
                  <div class="adjust-info-item">
                    <span class="adjust-info-label">Verfügbar im Zeitraum</span>
                    <span class="adjust-info-value" :class="id.adjustAvailable >= 0 ? 'text-green' : 'text-red'">
                      {{ id.adjustAvailableLoading ? '...' : id.adjustAvailable }} Stk.
                      <span v-if="!id.adjustAvailableLoading && id.adjustPackSize && id.adjustPackUnit" class="adjust-info-sets">
                        ({{ Math.floor(id.adjustAvailable / id.adjustPackSize) }} {{ id.adjustPackUnit }}<span v-if="id.adjustAvailable % id.adjustPackSize !== 0"> +{{ id.adjustAvailable % id.adjustPackSize }}</span>)
                      </span>
                    </span>
                  </div>
                </div>

                <!-- Set-Schnellbuttons wenn Verpackungseinheit vorhanden -->
                <div v-if="id.adjustPackSize && id.adjustPackUnit && id.adjustPackSize > 1" class="adjust-set-buttons">
                  <label>Schnellauswahl {{ id.adjustPackUnit }}</label>
                  <div class="adjust-set-row">
                    <button
                      v-for="n in [1, 2, 5, 10]"
                      :key="'set-' + n"
                      class="mat-set-btn"
                      :disabled="n * id.adjustPackSize > id.adjustMaxAllowed"
                      @click="id.adjustNewQty = n * id.adjustPackSize"
                      :class="{ active: id.adjustNewQty === n * id.adjustPackSize }"
                    >
                      {{ n }} {{ id.adjustPackUnit }}
                      <span class="set-btn-detail">({{ n * id.adjustPackSize }} Stk.)</span>
                    </button>
                  </div>
                </div>

                <div class="adjust-input-group">
                  <label>Neue Menge (Einzelstück)</label>
                  <div class="adjust-qty-row">
                    <button class="btn-qty" @click="id.adjustNewQty = Math.max(0, id.adjustNewQty - 1)">−</button>
                    <input v-model.number="id.adjustNewQty" type="number" min="0" class="form-input adjust-qty-input" />
                    <button class="btn-qty" @click="id.adjustNewQty++">+</button>
                  </div>
                  <div v-if="id.adjustPackSize && id.adjustPackUnit && id.adjustNewQty > 0" class="adjust-qty-summary">
                    = {{ Math.floor(id.adjustNewQty / id.adjustPackSize) }} {{ id.adjustPackUnit }}<span v-if="id.adjustNewQty % id.adjustPackSize !== 0"> + {{ id.adjustNewQty % id.adjustPackSize }} Stk.</span>
                  </div>
                  <span v-if="id.adjustNewQty > id.adjustMaxAllowed && !id.adjustAvailableLoading" class="adjust-warning">
                    Nicht genug verfügbar! Max. {{ id.adjustMaxAllowed }} Stk.
                    <span v-if="id.adjustPackSize && id.adjustPackUnit">({{ Math.floor(id.adjustMaxAllowed / id.adjustPackSize) }} {{ id.adjustPackUnit }})</span>
                  </span>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-outline" @click="id.closeAdjustModal">Abbrechen</button>
                <button
                  v-if="id.adjustNewQty === 0"
                  class="btn btn-danger"
                  @click="id.confirmAdjust"
                >
                  Material entfernen
                </button>
                <button
                  v-else
                  class="btn btn-primary"
                  @click="id.confirmAdjust"
                  :disabled="id.adjustNewQty > id.adjustMaxAllowed && !id.adjustAvailableLoading"
                >
                  Menge auf {{ id.adjustNewQty }} Stk. ändern
                  <span v-if="id.adjustPackSize && id.adjustPackUnit">({{ Math.floor(id.adjustNewQty / id.adjustPackSize) }} {{ id.adjustPackUnit }})</span>
                </button>
              </div>
            </div>
          </div>
        </Teleport>
      </div>

      <!-- Tab: Auftrag Status (4-Stufen Workflow) -->
      <div v-if="id.activeDetailTab === 'packlist'" class="detail-body">
        <div v-if="id.isLoadingPackItems" class="loading-hint">Auftrag wird geladen...</div>
        <div v-else-if="id.packItems.length === 0" class="empty-hint">
          <p>Noch kein Auftrag vorhanden.</p>
          <button v-if="id.selectedActivity?.status === 'packing'" class="btn btn-sm btn-primary" @click="id.initPackItems">
            Auftrag starten
          </button>
        </div>
        <div v-else class="pack-workflow">
          <!-- Stufen-Tabs -->
          <div class="pack-stage-tabs">
            <button
              v-for="stage in id.PACK_STAGES"
              :key="stage.key"
              class="pack-stage-tab"
              :class="{ active: id.activePackStage === stage.key }"
              @click="id.activePackStage = stage.key; id.initMoveQtyInputs()"
            >
              {{ stage.leftLabel }} <span class="stage-arrow">&rarr;</span> {{ stage.rightLabel }}
            </button>
          </div>

          <!-- Fortschritt + Workflow-Actions -->
          <div class="pack-progress-bar">
            <div class="pack-progress-info">
              <span>{{ id.stageProgress }}% {{ id.activeStageConfig.rightLabel }}</span>
              <div class="pack-progress-actions">
                <button
                  v-if="id.stageLeftItems.length > 0"
                  class="btn btn-xs btn-outline btn-move-all"
                  @click="id.moveAllToNextStage"
                >
                  ALLES &rarr; {{ id.activeStageConfig.rightLabel }}
                </button>
                <!-- Workflow-Button: Nächster Status (immer sichtbar wenn Transition erlaubt) -->
                <button
                  v-if="id.nextWorkflowTransition"
                  class="btn btn-sm btn-progress-action"
                  :class="[id.getTransitionBtnClass(id.nextWorkflowTransition.status), { 'btn-progress-warn': id.stageProgress < 100 }]"
                  @click="id.handleWorkflowTransition"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" v-html="id.getTransitionIcon(id.nextWorkflowTransition.status)"></svg>
                  {{ id.nextWorkflowTransition.label }}
                  <span v-if="id.stageProgress < 100" class="btn-progress-warn-badge">{{ id.stageProgress }}%</span>
                </button>
              </div>
            </div>
            <div class="pack-progress-track">
              <div class="pack-progress-fill" :class="{ 'progress-complete': id.stageProgress === 100 }" :style="{ width: id.stageProgress + '%' }"></div>
            </div>
          </div>
          <div v-if="id.jsWorkflowSummary.items > 0" class="js-workflow-summary">
            <span class="mat-source-badge">J&amp;S</span>
            <span>Positionen: <strong>{{ id.jsWorkflowSummary.items }}</strong></span>
            <span>Erhalten: <strong>{{ id.jsWorkflowSummary.received }}</strong></span>
            <span>Rückgabe: <strong>{{ id.jsWorkflowSummary.returned }}</strong></span>
            <span>Verluste: <strong>{{ id.jsWorkflowSummary.losses }}</strong></span>
          </div>

          <!-- QR-Code + Drucken -->
          <div class="pack-qr-print-bar">
            <div v-if="id.activityQrDataUrl" class="pack-qr-box">
              <img :src="id.activityQrDataUrl" alt="QR-Code" class="pack-qr-img" />
              <span class="pack-qr-label">Scan zum Öffnen des Auftrags</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline pack-print-btn" @click="id.printActivity">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
              Auftrag drucken
            </button>
          </div>

          <div class="detail-card detail-card-full">
            <h3 class="detail-card-title">Kisten-Instanzen</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Kiste wählen</label>
                <select v-model="id.selectedPackContainerId" class="form-input" @change="id.loadSelectedPackContainerItems">
                  <option value="">– Kiste auswählen –</option>
                  <option v-for="container in id.packContainers" :key="container.id" :value="container.id">
                    {{ container.label }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Neue Kiste</label>
                <div style="display:flex; gap:8px;">
                  <input v-model="id.newPackContainerLabel" type="text" class="form-input" placeholder="z.B. Rako 01" />
                  <button class="btn btn-sm btn-outline" @click="id.createPackContainer">Anlegen</button>
                </div>
              </div>
            </div>
            <div v-if="id.selectedPackContainerId" class="form-row">
              <div class="form-group">
                <label>Material</label>
                <select v-model="id.newPackContainerItemMaterialId" class="form-input">
                  <option value="">– Material –</option>
                  <option v-for="item in id.detailItems" :key="item.materialItemId" :value="item.materialItemId">
                    {{ item.materialName }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Menge</label>
                <div style="display:flex; gap:8px;">
                  <input v-model.number="id.newPackContainerItemQty" type="number" min="1" class="form-input" />
                  <button class="btn btn-sm btn-primary" @click="id.addItemToPackContainer">Hinzufügen</button>
                </div>
              </div>
            </div>
            <div v-if="id.selectedPackContainerItems.length > 0" class="issues-list">
              <div v-for="item in id.selectedPackContainerItems" :key="item.id" class="issue-card">
                <div class="issue-header">
                  <span class="issue-material">{{ item.material_name }}</span>
                  <span class="issue-qty">&times;{{ item.quantity_packed }}</span>
                </div>
                <div class="issue-footer">
                  <span class="text-muted">{{ item.batch_label || item.serial_number || 'bulk' }}</span>
                  <button class="btn btn-sm btn-danger-outline" @click="id.removeItemFromPackContainer(item.id)">Entfernen</button>
                </div>
              </div>
            </div>
          </div>

          <div class="pack-panels">
            <!-- ═══ LINKE SEITE: Aktuelle Stufe ═══ -->
            <div class="pack-panel pack-panel-left">
              <div class="pack-panel-header">
                <span class="pack-panel-title">{{ id.activeStageConfig.leftLabel }}</span>
                <span class="pack-panel-count">{{ id.stageLeftItems.length }}</span>
              </div>
              <div v-if="id.stageLeftItems.length === 0" class="pack-panel-empty">
                Alles verschoben!
              </div>
              <div v-for="group in id.groupsLeft" :key="'l-' + group.categoryName" class="pack-group">
                <div class="pack-group-header" @click="id.toggleGroup('l-' + group.categoryName)">
                  <span class="pack-group-name">{{ group.categoryName }}</span>
                  <span class="pack-group-toggle">{{ id.collapsedGroups['l-' + group.categoryName] ? '&#9654;' : '&#9660;' }}</span>
                </div>
                <div v-if="!id.collapsedGroups['l-' + group.categoryName]" class="pack-group-items">
                  <div v-for="pi in group.items" :key="pi.id" class="pack-card">
                    <div class="pack-card-main">
                      <div class="pack-card-info">
                        <span class="pack-card-name">{{ pi.materialName }} <span v-if="pi.isJsMaterial" class="mat-source-badge">J&amp;S</span></span>
                        <span class="pack-card-detail">{{ id.getStageLeftQty(pi) }} / {{ id.getStageTotalQty(pi) }}</span>
                      </div>
                      <div class="pack-card-actions">
                        <!-- Am Event | Retour: Melde-Buttons auf linker Seite (Items am Event) -->
                        <template v-if="id.activePackStage === 'issued_returned'">
                          <template v-if="pi.isConsumable">
                            <button class="btn-issue-quick btn-issue-consumed" @click="id.openPackEditModal(pi, 'consumption')" title="Verbrauch melden">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/><path d="M8 12l3 3 5-5"/></svg>
                              Gebraucht
                            </button>
                          </template>
                          <template v-else>
                            <button class="btn-issue-quick btn-issue-loss" @click="id.openPackEditModal(pi, 'loss')" title="Verlust melden">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                              Verlust
                            </button>
                            <button class="btn-issue-quick btn-issue-repair" @click="id.openPackEditModal(pi, 'repair')" title="Reparatur melden">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                              Reparatur
                            </button>
                          </template>
                        </template>
                        <!-- Mengen-Input + Move-Button -->
                        <div class="pack-move-inline">
                          <input
                            v-model.number="id.moveQtyInputs[pi.id]"
                            type="number"
                            min="1"
                            :max="id.getStageLeftQty(pi)"
                            class="pack-move-input"
                            @keyup.enter="id.moveToNextStage(pi)"
                          />
                          <button class="btn-move-arrow" @click="id.moveToNextStage(pi)" title="Verschieben">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><polyline points="12 5 19 12 12 19"/></svg>
                          </button>
                        </div>
                        <!-- 3-Punkte (nur in Bestätigt/Gepackt Stufen) -->
                        <button v-if="id.activePackStage !== 'issued_returned'" class="btn-pack-menu" @click="id.openPackEditModal(pi)" title="Optionen">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ═══ RECHTE SEITE: Nächste Stufe ═══ -->
            <div class="pack-panel pack-panel-right">
              <div class="pack-panel-header pack-panel-header-done">
                <span class="pack-panel-title">{{ id.activeStageConfig.rightLabel }}</span>
                <span class="pack-panel-count">{{ id.stageRightItems.length }}</span>
              </div>
              <div v-if="id.stageRightItems.length === 0" class="pack-panel-empty">
                Noch nichts verschoben
              </div>
              <div v-for="group in id.groupsRight" :key="'r-' + group.categoryName" class="pack-group">
                <div class="pack-group-header pack-group-header-done" @click="id.toggleGroup('r-' + group.categoryName)">
                  <span class="pack-group-name">{{ group.categoryName }}</span>
                  <span class="pack-group-toggle">{{ id.collapsedGroups['r-' + group.categoryName] ? '&#9654;' : '&#9660;' }}</span>
                </div>
                <div v-if="!id.collapsedGroups['r-' + group.categoryName]" class="pack-group-items">
                  <div v-for="pi in group.items" :key="pi.id" class="pack-card pack-card-done">
                    <div class="pack-card-main">
                      <div class="pack-card-actions pack-card-actions-left">
                        <!-- Zurück-Button: < Input -->
                        <button class="btn-moveback-arrow" @click="id.moveToPrevStage(pi)" title="Zurückverschieben">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
                        </button>
                        <input
                          v-model.number="id.moveBackQtyInputs[pi.id]"
                          type="number"
                          min="1"
                          :max="id.getStageRightQty(pi)"
                          class="pack-moveback-input"
                          @keyup.enter="id.moveToPrevStage(pi)"
                        />
                      </div>
                      <div class="pack-card-info">
                        <span class="pack-card-name">{{ pi.materialName }} <span v-if="pi.isJsMaterial" class="mat-source-badge">J&amp;S</span></span>
                        <span class="pack-card-detail">{{ id.getStageRightQty(pi) }} Stk.</span>
                      </div>
                      <div class="pack-card-actions">
                        <!-- Am Event / Retour Stufen: Aktionsbuttons -->
                        <template v-if="id.activePackStage === 'packed_issued' || id.activePackStage === 'issued_returned'">
                          <!-- Verbrauchsmaterial: "Gebraucht"-Button -->
                          <template v-if="pi.isConsumable">
                            <button class="btn-issue-quick btn-issue-consumed" @click="id.openPackEditModal(pi, 'consumption')" title="Verbrauch melden">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/><path d="M8 12l3 3 5-5"/></svg>
                              Gebraucht
                            </button>
                          </template>
                          <!-- Ausleihmaterial: Verlust/Reparatur-Buttons -->
                          <template v-else>
                            <button class="btn-issue-quick btn-issue-loss" @click="id.openPackEditModal(pi, 'loss')" title="Verlust melden">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                              Verlust
                            </button>
                            <button class="btn-issue-quick btn-issue-repair" @click="id.openPackEditModal(pi, 'repair')" title="Reparatur melden">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                              Reparatur
                            </button>
                          </template>
                        </template>
                        <!-- Andere Stufen: 3-Punkte-Menü -->
                        <button v-else class="btn-pack-menu" @click="id.openPackEditModal(pi)" title="Optionen">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal: Pack-Position bearbeiten / Verlust / Reparatur -->
          <Teleport to="body">
            <div v-if="id.showPackEditModal" class="modal-overlay">
              <div class="modal-dialog pack-edit-modal">
                <div class="modal-header">
                  <h3>
                    <template v-if="id.packEditAction === 'edit'">Position bearbeiten</template>
                    <template v-else-if="id.packEditAction === 'loss'">Verlust melden</template>
                    <template v-else-if="id.packEditAction === 'consumption'">Verbrauch erfassen</template>
                    <template v-else>Reparatur melden</template>
                  </h3>
                  <button class="modal-close" @click="id.closePackEditModal">&times;</button>
                </div>
                <div class="modal-body">
                  <div class="pack-edit-name">{{ id.packEditItem?.materialName }}</div>

                  <!-- Action-Tabs im Modal -->
                  <div v-if="id.packEditAction === 'edit'" class="pack-edit-actions-bar">
                    <template v-if="id.packEditItem?.isConsumable">
                      <button class="btn btn-xs btn-success" @click="id.packEditAction = 'consumption'">Verbrauch erfassen</button>
                    </template>
                    <template v-else>
                      <button class="btn btn-xs btn-warning" @click="id.packEditAction = 'loss'">Verlust melden</button>
                      <button class="btn btn-xs btn-danger" @click="id.packEditAction = 'repair'">Reparatur melden</button>
                    </template>
                  </div>

                  <template v-if="id.packEditAction === 'edit'">
                    <div class="pack-edit-ordered">Bestellt: <strong>{{ id.packEditItem?.quantityOrdered }}</strong> Stk.</div>
                    <div class="pack-edit-field">
                      <label>Menge</label>
                      <div class="adjust-qty-row">
                        <button class="btn-qty" @click="id.packEditQty = Math.max(0, id.packEditQty - 1)">−</button>
                        <input v-model.number="id.packEditQty" type="number" min="0" class="form-input adjust-qty-input" />
                        <button class="btn-qty" @click="id.packEditQty++">+</button>
                      </div>
                    </div>
                    <div class="pack-edit-field">
                      <label>Zustand</label>
                      <select v-model="id.packEditCondition" class="form-input">
                        <option value="ok">OK</option>
                        <option value="leicht_beschaedigt">Leicht beschädigt</option>
                        <option value="beschaedigt">Beschädigt</option>
                      </select>
                    </div>
                    <div class="pack-edit-field">
                      <label>Notiz</label>
                      <textarea v-model="id.packEditNotes" class="form-input form-textarea" rows="2" placeholder="Optional..."></textarea>
                    </div>
                  </template>

                  <template v-else>
                    <!-- Verlust / Reparatur / Verbrauch -->
                    <div class="pack-edit-field">
                      <label>{{ id.packEditAction === 'loss' ? 'Verlorene Menge' : id.packEditAction === 'consumption' ? 'Gebrauchte Menge' : 'Defekte Menge' }}</label>
                      <div class="adjust-qty-row">
                        <button class="btn-qty" @click="id.packEditQty = Math.max(1, id.packEditQty - 1)">−</button>
                        <input v-model.number="id.packEditQty" type="number" min="1" class="form-input adjust-qty-input" />
                        <button class="btn-qty" @click="id.packEditQty++">+</button>
                      </div>
                      <!-- Set-Schnellbuttons bei Verbrauch mit packSize -->
                      <div v-if="id.packEditAction === 'consumption' && id.packEditItem?.packSize && id.packEditItem.packSize > 1" class="pack-edit-set-btns">
                        <button class="mat-quick-btn mat-set-btn" @click="id.packEditQty = (id.packEditItem?.packSize || 1)" :title="'1 ' + (id.packEditItem?.packUnit || 'Set')">
                          1 {{ id.packEditItem?.packUnit || 'Set' }}
                        </button>
                        <button class="mat-quick-btn mat-set-btn" @click="id.packEditQty = (id.packEditItem?.packSize || 1) * 2" :title="'2 ' + (id.packEditItem?.packUnit || 'Sets')">
                          2 {{ id.packEditItem?.packUnit || 'Sets' }}
                        </button>
                        <button class="mat-quick-btn mat-set-btn" @click="id.packEditQty = (id.packEditItem?.packSize || 1) * 5" :title="'5 ' + (id.packEditItem?.packUnit || 'Sets')">
                          5 {{ id.packEditItem?.packUnit || 'Sets' }}
                        </button>
                        <span class="pack-edit-set-hint">1 {{ id.packEditItem?.packUnit || 'Set' }} = {{ id.packEditItem?.packSize }} Stk.</span>
                      </div>
                    </div>
                    <div class="pack-edit-field">
                      <label>{{ id.packEditAction === 'consumption' ? 'Notiz (optional)' : 'Beschreibung' }}</label>
                      <textarea v-model="id.packEditNotes" class="form-input form-textarea" rows="3" :placeholder="id.packEditAction === 'consumption' ? 'Optional...' : 'Was ist passiert?'"></textarea>
                    </div>
                  </template>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-outline" :disabled="id.isPackEditSubmitting" @click="id.closePackEditModal">Abbrechen</button>
                  <button
                    class="btn"
                    :class="id.packEditAction === 'edit' ? 'btn-primary' : id.packEditAction === 'consumption' ? 'btn-success' : id.packEditAction === 'loss' ? 'btn-warning' : 'btn-danger'"
                    :disabled="id.isPackEditSubmitting"
                    @click="id.confirmPackEdit"
                  >
                    {{ id.isPackEditSubmitting ? 'Wird gesendet…' : (id.packEditAction === 'edit' ? 'Speichern' : id.packEditAction === 'consumption' ? 'Verbrauch buchen' : id.packEditAction === 'loss' ? 'Verlust melden' : 'Reparatur melden') }}
                  </button>
                </div>
              </div>
            </div>
          </Teleport>
        </div>
      </div>

      <!-- Tab: Reparaturen / Verluste -->
      <div v-if="id.activeDetailTab === 'issues'" class="detail-body">
        <div v-if="id.isLoadingIssues" class="loading-hint">Reparaturen / Verluste werden geladen...</div>
        <div v-else>
          <!-- Neue Meldung erstellen -->
          <div v-if="id.selectedActivity && ['issued', 'returned'].includes(id.selectedActivity.status)" class="issue-actions">
            <button v-if="!id.showIssueForm" class="btn btn-sm btn-warning" @click="id.showIssueForm = true">
              + Meldung erstellen
            </button>
            <div v-if="id.showIssueForm" class="issue-form">
              <h4>Neue Meldung</h4>
              <div class="form-row">
                <label>Typ</label>
                <select v-model="id.newIssue.type" class="form-input">
                  <option value="repair">Reparatur</option>
                  <option value="loss">Verlust</option>
                </select>
              </div>
              <div class="form-row">
                <label>Material <span class="issue-required">*</span></label>
                <div class="issue-mat-autocomplete">
                  <div class="issue-mat-selected" v-if="id.newIssue.materialItemId">
                    <span class="issue-mat-selected-name">{{ id.newIssue.materialName }}</span>
                    <button class="issue-mat-clear" @click="id.newIssue.materialItemId = ''; id.newIssue.materialName = ''; id.issueMatSearch = ''" title="Entfernen">&times;</button>
                  </div>
                  <input
                    v-else
                    v-model="id.issueMatSearch"
                    type="text"
                    class="form-input"
                    :class="{ 'input-required': !id.newIssue.materialItemId }"
                    placeholder="Material suchen (Pflichtfeld)..."
                    @focus="id.showIssueMatDropdown = true"
                    @blur="setTimeout(() => id.showIssueMatDropdown = false, 200)"
                    @keydown.escape="id.showIssueMatDropdown = false"
                  />
                  <Transition name="dropdown-fade">
                    <div v-if="id.showIssueMatDropdown && !id.newIssue.materialItemId" class="issue-mat-dropdown">
                      <div v-if="id.issueMatFiltered.length === 0" class="issue-mat-dropdown-empty">
                        Kein Material gefunden
                      </div>
                      <div
                        v-for="item in id.issueMatFiltered"
                        :key="item.materialItemId"
                        class="issue-mat-dropdown-item"
                        @click="id.selectIssueMaterial(item)"
                      >
                        <span class="issue-mat-item-name">
                          <span v-if="item.isConsumable" class="mat-type-icon consumable">🔥</span>
                          <span v-else class="mat-type-icon rental">📦</span>
                          {{ item.materialName }}
                        </span>
                        <span class="issue-mat-item-qty">{{ item.quantity }} Stk.</span>
                      </div>
                    </div>
                  </Transition>
                </div>
              </div>
              <div class="form-row">
                <label>Menge</label>
                <input type="number" v-model.number="id.newIssue.quantity" class="form-input" min="1" />
              </div>
              <div class="form-row">
                <label>Beschreibung</label>
                <textarea v-model="id.newIssue.description" class="form-input form-textarea" rows="3" placeholder="Was ist passiert?"></textarea>
              </div>
              <div class="form-actions">
                <button class="btn btn-sm btn-primary" @click="id.createIssue">Meldung speichern</button>
                <button class="btn btn-sm btn-secondary" @click="id.showIssueForm = false; id.issueMatSearch = ''; id.showIssueMatDropdown = false">Abbrechen</button>
              </div>
            </div>
          </div>

          <!-- Gefilterte Liste (nur repair, loss, damage - kein consumption) -->
          <div v-if="id.issueReportsFiltered.length === 0 && !id.showIssueForm" class="empty-hint">
            <p>Keine Reparaturen oder Verluste gemeldet.</p>
          </div>
          <div v-else class="issues-list">
            <div v-for="issue in id.issueReportsFiltered" :key="issue.id" class="issue-card" :class="{ resolved: issue.resolved }">
              <div class="issue-header">
                <span class="issue-type-badge" :class="issue.type">{{ issue.typeLabel }}</span>
                <span v-if="issue.materialName" class="issue-material">{{ issue.materialName }}</span>
                <span class="issue-qty">&times;{{ issue.quantity }}</span>
                <span class="issue-time">{{ id.formatDateTimeWithSeconds(issue.reportedAt) }}</span>
              </div>
              <div v-if="issue.description" class="issue-description">{{ issue.description }}</div>
              <div class="issue-footer">
                <span v-if="issue.resolved" class="issue-resolved">
                  Erledigt {{ issue.resolvedAt ? id.formatDateTime(issue.resolvedAt) : '' }}
                </span>
                <span v-if="id.getWorkshopTicketForIssue(issue.id)" class="issue-workshop-state" :class="id.getWorkshopTicketForIssue(issue.id)?.status">
                  Werkstatt: {{ id.getWorkshopStatusLabel(id.getWorkshopTicketForIssue(issue.id)?.status || '') }}
                </span>
                <button
                  v-if="id.getWorkshopTicketForIssue(issue.id)"
                  class="btn btn-xs btn-workshop-open"
                  title="Werkstatt-Ticket öffnen"
                  @click="id.openWorkshopForIssue(issue.id)"
                >In Werkstatt öffnen</button>
                <span v-else-if="!issue.resolved" class="issue-workshop-missing">
                  Kein Werkstatt-Ticket verknüpft
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab: Verbrauchsmaterial -->
      <div v-if="id.activeDetailTab === 'consumables'" class="detail-body">
        <div v-if="id.isLoadingIssues || id.isLoadingDetailItems" class="loading-hint">Verbrauchsmaterial wird geladen...</div>
        <div v-else>
          <div v-if="id.consumableItems.length === 0" class="empty-hint">
            <p>Kein Verbrauchsmaterial in dieser Aktivität.</p>
          </div>
          <div v-else class="consumables-list">
            <div class="consumable-hint">
              Verbrauchtes Material hier abbuchen. Die Menge wird vom Bestand abgezogen.
            </div>
            <div v-for="ci in id.consumableItems" :key="ci.materialItemId" class="consumable-card">
              <div class="consumable-info">
                <span class="consumable-name">{{ ci.materialName }}</span>
                <span class="consumable-ordered">Bestellt: {{ ci.quantity }} Stk.</span>
                <span v-if="id.getConsumableUsed(ci.materialItemId) > 0" class="consumable-used">
                  Verbraucht: {{ id.getConsumableUsed(ci.materialItemId) }} Stk.
                </span>
              </div>
              <div class="consumable-actions">
                <div class="consumable-qty-row">
                  <button class="btn-qty" @click="id.consumableQtyInputs[ci.materialItemId] = Math.max(1, (id.consumableQtyInputs[ci.materialItemId] || 1) - 1)">−</button>
                  <input
                    v-model.number="id.consumableQtyInputs[ci.materialItemId]"
                    type="number"
                    min="1"
                    class="consumable-qty-input"
                  />
                  <button class="btn-qty" @click="id.consumableQtyInputs[ci.materialItemId] = (id.consumableQtyInputs[ci.materialItemId] || 1) + 1">+</button>
                </div>
                <button class="btn btn-sm btn-warning" @click="id.reportConsumption(ci)">
                  Verbrauch buchen
                </button>
              </div>
            </div>
          </div>

          <!-- Verbrauch-Historie -->
          <div v-if="id.consumptionReports.length > 0" class="consumable-history">
            <h4>Gebuchter Verbrauch</h4>
            <div v-for="cr in id.consumptionReports" :key="cr.id" class="consumable-history-item">
              <span class="consumable-history-name">{{ cr.materialName || 'Material' }}</span>
              <span class="consumable-history-qty">&times;{{ cr.quantity }}</span>
              <span class="consumable-history-time">{{ id.formatDateTime(cr.reportedAt) }}</span>
              <span v-if="cr.description" class="consumable-history-desc">{{ cr.description }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab: Kosten -->
      <div v-if="id.activeDetailTab === 'costs'" class="detail-body">
        <div v-if="id.isLoadingDetailItems || id.isLoadingWorkshopCosts" class="loading-hint">Kostenaufstellung wird geladen...</div>
        <div v-else>
          <div class="costs-overview">
            <!-- Verbrauchsmaterial-Kosten -->
            <div class="costs-section">
              <h3 class="costs-section-title">
                <span class="costs-icon">🔥</span> Verbrauchsmaterial
              </h3>
              <div v-if="id.costConsumableItems.length === 0" class="costs-empty">Kein Verbrauchsmaterial</div>
              <div v-else>
                <div class="costs-table">
                  <div class="costs-row costs-row-header">
                    <span class="costs-col-name">Material</span>
                    <span class="costs-col-qty">Bestellt</span>
                    <span class="costs-col-used">Verbraucht</span>
                    <span class="costs-col-price">Stückpreis</span>
                    <span class="costs-col-total">Betrag</span>
                  </div>
                  <div v-for="item in id.costConsumableItems" :key="item.materialItemId" class="costs-row">
                    <span class="costs-col-name">{{ item.materialName }}</span>
                    <span class="costs-col-qty">{{ item.quantity }}</span>
                    <span class="costs-col-used">{{ id.getConsumableUsed(item.materialItemId) || '–' }}</span>
                    <span class="costs-col-price">CHF {{ item.salePrice ? Number(item.salePrice).toFixed(2) : '–' }}</span>
                    <span class="costs-col-total">
                      <template v-if="item.salePrice && id.getConsumableUsed(item.materialItemId) > 0">
                        CHF {{ (Number(item.salePrice) * id.getConsumableUsed(item.materialItemId)).toFixed(2) }}
                      </template>
                      <template v-else>CHF 0.00</template>
                    </span>
                  </div>
                </div>
                <div class="costs-subtotal">
                  <span>Verbrauchsmaterial Gesamt:</span>
                  <strong>CHF {{ id.costConsumableTotal.toFixed(2) }}</strong>
                </div>
              </div>
            </div>

            <!-- Ausleihmaterial-Kosten (nur bei Extern) -->
            <div v-if="id.selectedActivity?.type === 'external'" class="costs-section">
              <h3 class="costs-section-title">
                <span class="costs-icon">📦</span> Ausleihmaterial
              </h3>
              <div v-if="id.costRentalItems.length === 0" class="costs-empty">Kein Ausleihmaterial</div>
              <div v-else>
                <div class="costs-table">
                  <div class="costs-row costs-row-header">
                    <span class="costs-col-name">Material</span>
                    <span class="costs-col-qty">Menge</span>
                    <span class="costs-col-used"></span>
                    <span class="costs-col-price">Stückpreis</span>
                    <span class="costs-col-total">Betrag</span>
                  </div>
                  <div v-for="item in id.costRentalItems" :key="item.materialItemId" class="costs-row">
                    <span class="costs-col-name">{{ item.materialName }}</span>
                    <span class="costs-col-qty">{{ item.quantity }}</span>
                    <span class="costs-col-used"></span>
                    <span class="costs-col-price">{{ item.unitPrice ? 'CHF ' + item.unitPrice.toFixed(2) : '–' }}</span>
                    <span class="costs-col-total">{{ item.lineTotal ? 'CHF ' + item.lineTotal.toFixed(2) : '–' }}</span>
                  </div>
                </div>
                <div class="costs-subtotal">
                  <span>Ausleihmaterial Gesamt:</span>
                  <strong>CHF {{ id.costRentalTotal.toFixed(2) }}</strong>
                </div>
              </div>
            </div>

            <!-- Verluste -->
            <div v-if="id.costLossItems.length > 0" class="costs-section costs-section-warn">
              <h3 class="costs-section-title">
                <span class="costs-icon">⚠️</span> Verluste
              </h3>
              <div class="costs-table">
                <div class="costs-row costs-row-header">
                  <span class="costs-col-name">Material</span>
                  <span class="costs-col-qty">Menge</span>
                  <span class="costs-col-used"></span>
                  <span class="costs-col-price"></span>
                  <span class="costs-col-total">Beschreibung</span>
                </div>
                <div v-for="loss in id.costLossItems" :key="loss.id" class="costs-row">
                  <span class="costs-col-name">{{ loss.materialName || '–' }}</span>
                  <span class="costs-col-qty">{{ loss.quantity }}</span>
                  <span class="costs-col-used"></span>
                  <span class="costs-col-price"></span>
                  <span class="costs-col-total costs-loss-desc">{{ loss.description || '–' }}</span>
                </div>
              </div>
            </div>

            <!-- Werkstattkosten: Reparatur -->
            <div v-if="id.costRepairTickets.length > 0" class="costs-section">
              <h3 class="costs-section-title">
                <span class="costs-icon">🔧</span> Werkstatt: Reparaturen
              </h3>
              <div class="costs-table">
                <div class="costs-row costs-row-header">
                  <span class="costs-col-name">Ticket</span>
                  <span class="costs-col-qty">Status</span>
                  <span class="costs-col-used"></span>
                  <span class="costs-col-price"></span>
                  <span class="costs-col-total">Kosten</span>
                </div>
                <div v-for="t in id.costRepairTickets" :key="t.id" class="costs-row">
                  <span class="costs-col-name">{{ t.title }}</span>
                  <span class="costs-col-qty">{{ t.status_label }}</span>
                  <span class="costs-col-used"></span>
                  <span class="costs-col-price"></span>
                  <span class="costs-col-total">CHF {{ Number(t.actual_cost || 0).toFixed(2) }}</span>
                </div>
              </div>
              <div class="costs-subtotal">
                <span>Reparaturkosten Gesamt:</span>
                <strong>CHF {{ id.costRepairTotal.toFixed(2) }}</strong>
              </div>
            </div>

            <!-- Werkstattkosten: Abschreibung -->
            <div v-if="id.costWriteoffTickets.length > 0" class="costs-section costs-section-warn">
              <h3 class="costs-section-title">
                <span class="costs-icon">🗑️</span> Werkstatt: Abschreibungen
              </h3>
              <div class="costs-table">
                <div class="costs-row costs-row-header">
                  <span class="costs-col-name">Ticket</span>
                  <span class="costs-col-qty">Status</span>
                  <span class="costs-col-used"></span>
                  <span class="costs-col-price"></span>
                  <span class="costs-col-total">Kosten</span>
                </div>
                <div v-for="t in id.costWriteoffTickets" :key="t.id" class="costs-row">
                  <span class="costs-col-name">{{ t.title }}</span>
                  <span class="costs-col-qty">{{ t.status_label }}</span>
                  <span class="costs-col-used"></span>
                  <span class="costs-col-price"></span>
                  <span class="costs-col-total">CHF {{ Number(t.actual_cost || 0).toFixed(2) }}</span>
                </div>
              </div>
              <div class="costs-subtotal">
                <span>Abschreibungskosten Gesamt:</span>
                <strong>CHF {{ id.costWriteoffTotal.toFixed(2) }}</strong>
              </div>
            </div>

            <!-- Gesamt / Endabrechnung -->
            <div class="costs-total-section" :class="{ 'costs-final': ['returned', 'completed'].includes(id.selectedActivity?.status || '') }">
              <div class="costs-total-label">
                <template v-if="['returned', 'completed'].includes(id.selectedActivity?.status || '')">
                  <strong>Endabrechnung</strong>
                </template>
                <template v-else>
                  <strong>Zwischenstand</strong>
                  <span class="costs-total-hint">(Endabrechnung bei Retour)</span>
                </template>
              </div>
              <div class="costs-total-rows">
                <div v-if="id.costConsumableTotal > 0" class="costs-total-row">
                  <span>Verbrauchsmaterial</span>
                  <span>CHF {{ id.costConsumableTotal.toFixed(2) }}</span>
                </div>
                <div v-if="id.selectedActivity?.type === 'external' && id.costRentalTotal > 0" class="costs-total-row">
                  <span>Ausleihmaterial</span>
                  <span>CHF {{ id.costRentalTotal.toFixed(2) }}</span>
                </div>
                <div v-if="id.costRepairTotal > 0" class="costs-total-row">
                  <span>Werkstatt Reparatur</span>
                  <span>CHF {{ id.costRepairTotal.toFixed(2) }}</span>
                </div>
                <div v-if="id.costWriteoffTotal > 0" class="costs-total-row">
                  <span>Werkstatt Abschreibung</span>
                  <span>CHF {{ id.costWriteoffTotal.toFixed(2) }}</span>
                </div>
                <div class="costs-total-row costs-grand-total">
                  <span>{{ id.selectedActivity?.type === 'external' ? 'Gesamtbetrag (extern)' : 'Interne Gesamtkosten' }}</span>
                  <span>CHF {{ id.selectedActivity?.type === 'external' ? id.costExternalGrandTotal.toFixed(2) : id.costInternalGrandTotal.toFixed(2) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab: Rückgabe -->
      <div v-if="id.activeDetailTab === 'returns'" class="detail-body">
        <div v-if="id.isLoadingReturns" class="loading-hint">Rückgabeliste wird geladen...</div>
        <div v-else-if="id.returnItems.length === 0" class="empty-hint">
          <p>Noch keine Rückgabe erfasst.</p>
          <button v-if="id.selectedActivity?.status === 'returned'" class="btn btn-sm btn-primary" @click="id.initReturnItems">
            Rückgabeliste erstellen
          </button>
        </div>
        <div v-else class="detail-returns">
          <!-- Zusammenfassung -->
          <div class="return-summary">
            <div class="summary-item" :class="{ 'has-issues': id.returnItems.some(r => r.hasDifferences) }">
              <span class="summary-label">Positionen mit Differenzen:</span>
              <span class="summary-value">{{ id.returnItems.filter(r => r.hasDifferences).length }} / {{ id.returnItems.length }}</span>
            </div>
          </div>

          <!-- Return-Items Tabelle -->
          <table class="workflow-table">
            <thead>
              <tr>
                <th>Material</th>
                <th class="col-qty">Gepackt</th>
                <th class="col-qty">Zurück</th>
                <th class="col-qty">Beschädigt</th>
                <th class="col-qty">Fehlt</th>
                <th class="col-condition">Zustand</th>
                <th class="col-status-icon"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="ri in id.returnItems" :key="ri.id" :class="{ 'row-difference': ri.hasDifferences }">
                <td class="material-name">{{ ri.materialName }}</td>
                <td class="col-qty">{{ ri.quantityPacked }}</td>
                <td class="col-qty">
                  <input
                    v-if="id.selectedActivity && id.selectedActivity.status === 'returned'"
                    type="number"
                    class="qty-input"
                    :value="ri.quantityReturned"
                    min="0"
                    @change="(e) => id.updateReturnItem(ri, 'quantity_returned', parseInt(e.target.value) || 0)"
                  />
                  <span v-else>{{ ri.quantityReturned }}</span>
                </td>
                <td class="col-qty">
                  <input
                    v-if="id.selectedActivity && id.selectedActivity.status === 'returned'"
                    type="number"
                    class="qty-input qty-damaged"
                    :value="ri.quantityDamaged"
                    min="0"
                    @change="(e) => id.updateReturnItem(ri, 'quantity_damaged', parseInt(e.target.value) || 0)"
                  />
                  <span v-else :class="{ 'text-danger': ri.quantityDamaged > 0 }">{{ ri.quantityDamaged }}</span>
                </td>
                <td class="col-qty">
                  <input
                    v-if="id.selectedActivity && id.selectedActivity.status === 'returned'"
                    type="number"
                    class="qty-input qty-missing"
                    :value="ri.quantityMissing"
                    min="0"
                    @change="(e) => id.updateReturnItem(ri, 'quantity_missing', parseInt(e.target.value) || 0)"
                  />
                  <span v-else :class="{ 'text-danger': ri.quantityMissing > 0 }">{{ ri.quantityMissing }}</span>
                </td>
                <td class="col-condition">
                  <select
                    v-if="id.selectedActivity && id.selectedActivity.status === 'returned'"
                    class="condition-select"
                    :value="ri.conditionIn"
                    @change="id.onReturnConditionChange(ri, $event)"
                  >
                    <option value="ok">OK</option>
                    <option value="leicht_beschaedigt">Leicht beschädigt</option>
                    <option value="beschaedigt">Beschädigt</option>
                    <option value="defekt">Defekt</option>
                  </select>
                  <span v-else class="condition-badge" :class="ri.conditionIn">{{ id.getConditionLabel(ri.conditionIn) }}</span>
                </td>
                <td class="col-status-icon">
                  <span v-if="ri.hasDifferences" class="warning-icon">⚠️</span>
                  <span v-else class="check-icon">✅</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab: Verlauf -->
      <div v-if="id.activeDetailTab === 'history'" class="detail-body">
        <div v-if="id.isLoadingHistory" class="loading-hint">Verlauf wird geladen...</div>
        <div v-else-if="id.activityHistory.length === 0" class="empty-hint">
          <p>Noch keine Einträge im Verlauf.</p>
        </div>
        <div v-else class="detail-history">
          <div v-for="entry in id.activityHistory" :key="entry.id" class="history-entry">
            <div class="history-dot" :class="entry.action"></div>
            <div class="history-content">
              <div class="history-header">
                <span class="history-action">{{ id.getHistoryActionLabel(entry.action) }}</span>
                <span class="history-time">{{ id.formatDateTime(entry.createdAt) }}</span>
              </div>
              <div v-if="entry.changes && Object.keys(entry.changes).length > 0" class="history-changes">
                <div v-for="(change, field) in entry.changes" :key="field" class="history-change">
                  <span class="history-field">{{ field }}:</span>
                  <template v-if="typeof change === 'object' && change.old !== undefined">
                    <span class="history-old">{{ change.old || '–' }}</span>
                    <span class="history-arrow">→</span>
                    <span class="history-new">{{ change.new || '–' }}</span>
                  </template>
                  <template v-else>
                    <span class="history-new">{{ change }}</span>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
  </div>
</template>

<script setup lang="ts">
import { computed, inject, unref } from 'vue'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import { ACTIVITIES_DETAIL_INJECT } from './activitiesInjectKeys'

defineOptions({ name: 'ActivitiesDetailView' })

const id = inject(ACTIVITIES_DETAIL_INJECT) as Record<string, unknown>

/** provide() übergibt Refs; in Templates wird bei Methoden wie .some() nicht immer entpackt — immer als Array verwenden */
const availableTransitionsList = computed(() => {
  const raw = unref(id.availableTransitions as any)
  return Array.isArray(raw) ? raw : []
})
</script>

<style scoped>
@import '@/styles/views/activities/index.css';
</style>
