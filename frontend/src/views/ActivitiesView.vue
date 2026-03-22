<template>
  <div class="activities-view">
    <!-- ═══ Detail-Ansicht (bei Doppelklick) ═══ -->
    <div v-if="showDetail && selectedActivity" class="activity-detail-panel">
      <div class="detail-header">
        <button class="detail-back-btn" @click="closeDetail">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><polyline points="15 18 9 12 15 6"/></svg>
          Zurück
        </button>
        <div class="detail-title-row">
          <span class="type-badge" :class="selectedActivity.type">{{ getTypeLabel(selectedActivity.type) }}</span>
          <h1 class="detail-title">{{ selectedActivity.name }}</h1>
          <span class="status-label" :class="selectedActivity.status">{{ getStatusLabel(selectedActivity.status) }}</span>
        </div>
        <div class="detail-subtitle-row">
          <span v-if="selectedActivity.groupName" class="detail-subtitle-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            {{ selectedActivity.groupName }}
          </span>
          <span v-if="activityDetail?.customer_name" class="detail-subtitle-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            {{ activityDetail.customer_name }}
          </span>
          <span v-if="selectedActivity.type === 'external'" class="detail-subtitle-badge external">Extern</span>
          <span class="detail-subtitle-sep" v-if="(selectedActivity.groupName || activityDetail?.customer_name) && activityDetail?.usage_start">·</span>
          <span v-if="activityDetail?.usage_start" class="detail-subtitle-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            {{ formatDateShort(activityDetail.usage_start) }}
            <template v-if="activityDetail.usage_end && !isSameDay(activityDetail.usage_start, activityDetail.usage_end)">
              – {{ formatDateShort(activityDetail.usage_end) }}
            </template>
          </span>
          <!-- Material-Zeitraum: Abholung bis Rückgabe -->
          <template v-if="activityDetail?.planning_start">
            <span class="detail-subtitle-sep">·</span>
            <span class="detail-subtitle-item detail-subtitle-planning">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
              {{ formatDateTimeShort(activityDetail.planning_start) }}
              – {{ formatDateTimeShort(activityDetail.planning_end || activityDetail.planning_start) }}
            </span>
          </template>
        </div>
        <div v-if="activityDetail?.notes" class="detail-notes-bar">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <span class="detail-notes-text">{{ activityDetail.notes }}</span>
        </div>
        <div class="detail-actions">
          <!-- Workflow-Buttons: Pack-Workflow-Transitions (packed/issued/returned) navigieren zum Auftrag-Status-Tab -->
          <template v-for="transition in availableTransitions">
            <!-- Pack-Workflow: Button navigiert zum Auftrag-Status-Tab statt Status direkt zu ändern -->
            <button 
              v-if="transition.allowed && transition.status !== 'cancelled' && isPackWorkflowTarget(transition.status)"
              :key="'nav-' + transition.status"
              class="btn btn-sm"
              :class="getTransitionBtnClass(transition.status)"
              @click="navigateToPackTab(transition.status)"
            >
              <svg v-if="getTransitionIcon(transition.status)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" v-html="getTransitionIcon(transition.status)"></svg>
              {{ transition.label }}
            </button>
            <!-- Nicht-Pack-Transitions: direkter Statuswechsel -->
            <button 
              v-else-if="transition.allowed && transition.status !== 'cancelled' && !isPackWorkflowTarget(transition.status)"
              :key="transition.status"
              class="btn btn-sm"
              :class="getTransitionBtnClass(transition.status)"
              @click="handleTransition(transition.status)"
            >
              <svg v-if="getTransitionIcon(transition.status)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" v-html="getTransitionIcon(transition.status)"></svg>
              {{ transition.label }}
            </button>
          </template>
          <!-- Stornieren (spezielle Darstellung) -->
          <button 
            v-if="availableTransitions.some(t => t.status === 'cancelled' && t.allowed)"
            class="btn btn-sm btn-danger-outline" 
            @click="cancelActivity"
          >
            Stornieren
          </button>
        </div>
      </div>

      <!-- Hinweis-Banner für Entwürfe -->
      <div v-if="selectedActivity.status === 'draft'" class="draft-hint-banner">
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
          v-for="dt in detailTabs" :key="dt.key"
          class="detail-tab" 
          :class="{ active: activeDetailTab === dt.key }"
          @click="switchDetailTab(dt.key)"
        >{{ dt.label }}</button>
      </div>

      <!-- Tab: Übersicht -->
      <div v-if="activeDetailTab === 'overview'" class="detail-body">

        <!-- Bearbeiten-Button (nur im Entwurf) -->
        <div v-if="isDraftEditable && !isEditingDraft" class="draft-edit-bar">
          <button class="btn btn-sm btn-outline" @click="startEditDraft">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Angaben bearbeiten
          </button>
        </div>

        <!-- ═══ EDIT-MODUS ═══ -->
        <div v-if="isEditingDraft" class="detail-grid">
          <!-- Name -->
          <div class="detail-card detail-card-full">
            <h3 class="detail-card-title">Name</h3>
            <input v-model="draftEditData.name" type="text" class="form-input" placeholder="Name der Aktivität" />
          </div>

          <!-- Zeitraum -->
          <div class="detail-card">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Zeitraum
            </h3>
            <div class="detail-field">
              <span class="detail-label">Nutzung von</span>
              <input v-model="draftEditData.usage_start" type="datetime-local" class="form-input" @change="onDraftDateChange" />
            </div>
            <div class="detail-field">
              <span class="detail-label">Nutzung bis</span>
              <input v-model="draftEditData.usage_end" type="datetime-local" class="form-input" @change="onDraftDateChange" />
            </div>
            <div class="detail-field">
              <span class="detail-label">Material Abholung</span>
              <input v-model="draftEditData.planning_start" type="datetime-local" class="form-input" />
            </div>
            <div class="detail-field">
              <span class="detail-label">Material Rückgabe</span>
              <input v-model="draftEditData.planning_end" type="datetime-local" class="form-input" />
            </div>

            <!-- Warnung: Datum-Änderung mit vorhandenem Material -->
            <div v-if="showDateChangeWarning" class="date-change-warning">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
              <div class="date-change-warning-content">
                <strong>Achtung:</strong> Es sind bereits <strong>{{ detailItems.length }} Material-Position{{ detailItems.length !== 1 ? 'en' : '' }}</strong> erfasst.
                Bei einer Datum-Änderung muss die Verfügbarkeit neu geprüft werden.
                Bitte entferne zuerst das Material und füge es nach der Datum-Änderung erneut hinzu.
                <button class="btn btn-sm btn-danger" style="margin-top: 8px;" @click="removeAllMaterialsAndSave">
                  Alle Materialien entfernen &amp; speichern
                </button>
              </div>
            </div>
          </div>

          <!-- Gruppe -->
          <div v-if="selectedActivity.type !== 'external'" class="detail-card">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              Gruppe
            </h3>
            <select v-model="draftEditData.group_id" class="form-input">
              <option :value="null">– Keine Gruppe –</option>
              <option v-for="grp in myGroups" :key="grp.id" :value="grp.id">{{ grp.name }}</option>
            </select>
          </div>

          <!-- Notizen -->
          <div class="detail-card detail-card-full">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Notizen
            </h3>
            <textarea v-model="draftEditData.notes" class="form-input form-textarea" rows="3" placeholder="Optionale Notizen..."></textarea>
          </div>

          <!-- Aktions-Buttons -->
          <div class="detail-card detail-card-full draft-edit-actions">
            <button class="btn btn-primary" @click="saveDraftEdit" :disabled="showDateChangeWarning && detailItems.length > 0">
              Änderungen speichern
            </button>
            <button class="btn btn-outline" @click="cancelEditDraft">Abbrechen</button>
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
                <template v-if="activityDetail?.usage_start">
                  {{ formatDateTime(activityDetail.usage_start) }} – {{ formatDateTime(activityDetail.usage_end || '') }}
                </template>
                <span v-else class="text-muted">Nicht festgelegt</span>
              </span>
            </div>
            <div v-if="activityDetail?.planning_start" class="detail-field">
              <span class="detail-label">Material Abhol-/Rückgabe</span>
              <span class="detail-value">
                {{ formatDateTime(activityDetail.planning_start) }} – {{ formatDateTime(activityDetail.planning_end || '') }}
              </span>
            </div>
          </div>

          <div class="detail-card">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              {{ selectedActivity.type === 'external' ? 'Kunde' : 'Gruppe' }}
            </h3>
            <div v-if="selectedActivity.groupName" class="detail-field">
              <span class="detail-label">Gruppe</span>
              <span class="detail-value">{{ selectedActivity.groupName }}</span>
            </div>
            <div v-if="activityDetail?.customer_name" class="detail-field">
              <span class="detail-label">Kunde</span>
              <span class="detail-value">{{ activityDetail.customer_name }}</span>
            </div>
            <div v-if="activityDetail?.customer_email" class="detail-field">
              <span class="detail-label">E-Mail</span>
              <span class="detail-value"><a :href="'mailto:' + activityDetail.customer_email">{{ activityDetail.customer_email }}</a></span>
            </div>
            <div v-if="activityDetail?.customer_phone" class="detail-field">
              <span class="detail-label">Telefon</span>
              <span class="detail-value">{{ activityDetail.customer_phone }}</span>
            </div>
            <div v-if="!selectedActivity.groupName && !activityDetail?.customer_name" class="detail-field">
              <span class="text-muted">Keine Zuordnung</span>
            </div>
          </div>

          <div v-if="selectedActivity.type === 'external'" class="detail-card">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
              Finanzen
            </h3>
            <div class="detail-field">
              <span class="detail-label">Preismodus</span>
              <span class="detail-value">
                <span class="pricing-mode-badge" :class="activityDetail?.pricing_mode || 'item_price'">
                  {{ activityDetail?.pricing_mode === 'set_price' ? '📦 Setpreis' : '📋 Einzelpreis' }}
                </span>
              </span>
            </div>
            <div class="detail-field">
              <span class="detail-label">Gesamtpreis</span>
              <span class="detail-value detail-price">{{ activityDetail?.total_price ? 'CHF ' + activityDetail.total_price.toFixed(2) : '–' }}</span>
            </div>
            <!-- Mini-Rechner bei Einzelpreis: zeige Aufschlüsselung -->
            <div v-if="activityDetail?.pricing_mode !== 'set_price' && detailItems.length > 0" class="detail-price-breakdown">
              <div v-for="di in detailItems.filter(i => i.lineTotal)" :key="di.id" class="breakdown-row">
                <span class="breakdown-name">{{ di.materialName }}</span>
                <span class="breakdown-calc">{{ di.quantity }} × CHF {{ ((di.unitPrice || 0)).toFixed(2) }}</span>
                <span class="breakdown-total">CHF {{ (di.lineTotal || 0).toFixed(2) }}</span>
              </div>
            </div>
            <div class="detail-field">
              <span class="detail-label">Kaution</span>
              <span class="detail-value">
                {{ activityDetail?.deposit_amount ? 'CHF ' + activityDetail.deposit_amount.toFixed(2) : '–' }}
                <span v-if="activityDetail?.deposit_paid" class="badge-green">Bezahlt</span>
                <span v-else-if="activityDetail?.deposit_amount" class="badge-yellow">Offen</span>
              </span>
            </div>
            <div class="detail-field">
              <span class="detail-label">Bezahlung</span>
              <span class="detail-value">
                <span v-if="activityDetail?.is_paid" class="badge-green">Bezahlt</span>
                <span v-else class="badge-yellow">Offen</span>
              </span>
            </div>
          </div>

          <div v-if="activityDetail?.notes || isDraftEditable" class="detail-card detail-card-full">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Notizen
            </h3>
            <p v-if="activityDetail?.notes" class="detail-notes">{{ activityDetail.notes }}</p>
            <p v-else class="text-muted">Keine Notizen</p>
          </div>
        </div>

        <div class="detail-meta">
          <span>Erstellt: {{ formatDateTime(selectedActivity.createdAt) }}</span>
          <span>Aktualisiert: {{ formatDateTime(selectedActivity.updatedAt) }}</span>
          <span v-if="selectedActivity.no" class="detail-meta-id">#{{ selectedActivity.no }}</span>
        </div>
      </div>

      <!-- Tab: Eingeladene Departments -->
      <div v-if="activeDetailTab === 'invited_departments'" class="detail-body">
        <div class="detail-card detail-card-full">
          <h3 class="detail-card-title">Eingeladene Departments</h3>
          <div v-if="invitedDepartmentsDetail.length === 0" class="empty-hint">
            <p>Keine Departments eingeladen.</p>
          </div>
          <div v-else class="department-invited-list">
            <div v-for="dep in invitedDepartmentsDetail" :key="`detail-invited-${dep.id || dep.name}`" class="department-invited-item">
              <span>{{ dep.name }}<template v-if="dep.organisation_name"> ({{ dep.organisation_name }})</template></span>
              <span class="invite-status-badge" :class="dep.status || 'pending'">
                {{ dep.status === 'accepted' ? 'Angenommen' : dep.status === 'rejected' ? 'Abgelehnt' : 'Ausstehend' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab: Material -->
      <div v-if="activeDetailTab === 'material'" class="detail-body">
        <!-- Material hinzufügen (Draft: alle mit Edit-Rolle, danach: MW+ bis issued) -->
        <div v-if="canMwAddMaterial" class="detail-material-add">
          <div class="detail-material-add-header">
            <h3 class="detail-card-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Material hinzufügen
            </h3>
          </div>
          <div class="mat-source-switch" role="tablist" aria-label="Materialquelle Detailansicht">
            <button type="button" class="mat-source-btn" :class="{ active: detailMaterialSource === 'internal' }" @click="setDetailMaterialSource('internal')">Eigenes</button>
            <button
              type="button"
              class="mat-source-btn"
              :class="{ active: detailMaterialSource === 'js' }"
              :disabled="!canUseDetailJsMaterialSource"
              @click="setDetailMaterialSource('js')"
            >
              J&amp;S
            </button>
          </div>
          <p v-if="!canUseDetailJsMaterialSource" class="mat-source-hint">J&amp;S-Material ist nur bei Event oder Camp verfügbar.</p>
          <div class="detail-material-search-wrapper">
            <MaterialLookupInput
              v-model="detailMatSearch"
              :fetcher="detailMaterialLookupFetcher"
              :min-chars="2"
              :max-suggestions="20"
              placeholder="Material suchen (z.B. Zelt, Kocher, Blache...)"
              :input-class="'detail-mat-search-input'"
              :loading-text="'Suche...'"
              :empty-text="`Keine Treffer für «${detailMatSearch}»`"
              @select="handleDetailLookupSelect"
            >
              <template #results="{ results, isLoading, activeIndex, setActiveIndex }">
                <div v-if="isLoading" class="mat-dropdown-loading">Suche...</div>
                <div v-else-if="results.length === 0" class="mat-dropdown-empty">
                  Keine Treffer für «{{ detailMatSearch }}»
                </div>
                <div v-else class="mat-dropdown-list">
                  <div
                    v-for="(mat, index) in results"
                    :key="mat.materialItemId"
                    class="mat-dropdown-item"
                    :class="{
                      active: activeIndex === index,
                      'already-added': detailItems.some(i => i.materialItemId === mat.materialItemId),
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
                        <span class="text-muted">Von: {{ getMaterialSourceLabel(mat) }}</span>
                        <span v-if="mat.isConsumable && mat.salePrice" class="mat-dropdown-price consumable">
                          CHF {{ Number(mat.salePrice).toFixed(2) }}/Stk
                        </span>
                      </span>
                    </div>
                    <div class="mat-dropdown-actions">
                      <template v-if="detailItems.some(i => i.materialItemId === mat.materialItemId)">
                        <span class="mat-already-badge">✓ vorhanden</span>
                      </template>
                      <template v-else-if="mat.availableForPeriod > 0">
                        <template v-if="mat.packSize && mat.packSize > 1">
                          <button v-if="mat.availableForPeriod >= mat.packSize" class="mat-quick-btn mat-set-btn" @click="addDetailMaterial(mat, mat.packSize)" :title="'1 ' + (mat.packUnit || 'Set')">1 {{ mat.packUnit || 'Set' }}</button>
                          <button v-if="mat.availableForPeriod >= mat.packSize * 5" class="mat-quick-btn mat-set-btn" @click="addDetailMaterial(mat, mat.packSize * 5)" :title="'5 ' + (mat.packUnit || 'Sets')">5 {{ mat.packUnit || 'Sets' }}</button>
                          <span class="mat-btn-divider">|</span>
                        </template>
                        <button class="mat-quick-btn" @click="addDetailMaterial(mat, 1)" title="+1">+1</button>
                        <button v-if="mat.availableForPeriod >= 5" class="mat-quick-btn" @click="addDetailMaterial(mat, 5)" title="+5">+5</button>
                        <button v-if="mat.availableForPeriod >= 10" class="mat-quick-btn" @click="addDetailMaterial(mat, 10)" title="+10">+10</button>
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
        <div v-if="isLoadingDetailItems" class="loading-hint">Materialien werden geladen...</div>
        <div v-else-if="filteredDetailItems.length === 0 && !canEditMaterial" class="empty-hint">
          <p>Keine Materialien zugeordnet.</p>
        </div>
        <div v-else-if="filteredDetailItems.length === 0 && canEditMaterial" class="empty-hint">
          <p>Noch keine Materialien – nutze die Suche oben um Material hinzuzufügen.</p>
        </div>
        <div v-else class="detail-material-list">
          <div class="detail-material-header">
            <span>Material</span>
            <span>Von wem</span>
            <span>Menge</span>
          </div>
          <div
            v-for="item in filteredDetailItems"
            :key="item.id"
            class="detail-material-row"
            :class="{ 'row-clickable': canEditMaterial && ['draft', 'submitted', 'approved'].includes(selectedActivity?.status || '') }"
            @dblclick="onMaterialRowDblClick(item)"
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
            <div class="detail-material-origin">{{ getMaterialSourceLabel(item) }}</div>
            <div class="detail-material-qty">
              <!-- Entwurf: +/- Buttons -->
              <template v-if="selectedActivity?.status === 'draft' && canEditMaterial">
                <button class="btn-qty" @click="changeDetailMaterialQty(item, -1)">−</button>
                <strong>{{ item.quantity }}</strong> Stk.
                <span v-if="item.packSize && item.packUnit" class="mat-qty-sets">({{ Math.floor(item.quantity / item.packSize) }} {{ item.packUnit }})</span>
                <button class="btn-qty" @click="changeDetailMaterialQty(item, 1)">+</button>
                <button class="btn-remove-sm" @click="removeDetailMaterial(item)" title="Entfernen">✕</button>
              </template>
              <!-- Eingereicht/Bestätigt: gesperrt, MW kann über Modal ändern -->
              <template v-else>
                <strong>{{ item.quantity }}</strong> Stk.
                <span v-if="item.packSize && item.packUnit" class="mat-qty-sets">({{ Math.floor(item.quantity / item.packSize) }} {{ item.packUnit }})</span>
                <button
                  v-if="canMwAdjustMaterial"
                  class="btn-adjust-sm"
                  @click="openAdjustModal(item)"
                  title="Bestand ändern"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
              </template>
            </div>
          </div>
          <div class="detail-material-total">
            <span>Gesamt: {{ filteredDetailItems.reduce((s, i) => s + i.quantity, 0) }} Stück · {{ filteredDetailItems.length }} Position{{ filteredDetailItems.length !== 1 ? 'en' : '' }}</span>
          </div>
        </div>

        <!-- Bestand-Ändern-Modal -->
        <Teleport to="body">
          <div v-if="showAdjustModal" class="modal-overlay">
            <div class="modal-dialog adjust-modal">
              <div class="modal-header">
                <h3>Bestand ändern</h3>
                <button class="modal-close" @click="closeAdjustModal">&times;</button>
              </div>
              <div class="modal-body">
                <div class="adjust-material-name">
                  {{ adjustItem?.materialName }}
                  <span v-if="adjustPackSize && adjustPackUnit" class="mat-pack-badge">{{ adjustPackSize }}&thinsp;Stk./{{ adjustPackUnit }}</span>
                </div>
                <div class="adjust-info-grid">
                  <div class="adjust-info-item">
                    <span class="adjust-info-label">Aktuell bestellt</span>
                    <span class="adjust-info-value">
                      {{ adjustItem?.quantity }} Stk.
                      <span v-if="adjustPackSize && adjustPackUnit" class="adjust-info-sets">
                        ({{ Math.floor((adjustItem?.quantity || 0) / adjustPackSize) }} {{ adjustPackUnit }}<span v-if="(adjustItem?.quantity || 0) % adjustPackSize !== 0"> +{{ (adjustItem?.quantity || 0) % adjustPackSize }}</span>)
                      </span>
                    </span>
                  </div>
                  <div class="adjust-info-item">
                    <span class="adjust-info-label">Verfügbar im Zeitraum</span>
                    <span class="adjust-info-value" :class="adjustAvailable >= 0 ? 'text-green' : 'text-red'">
                      {{ adjustAvailableLoading ? '...' : adjustAvailable }} Stk.
                      <span v-if="!adjustAvailableLoading && adjustPackSize && adjustPackUnit" class="adjust-info-sets">
                        ({{ Math.floor(adjustAvailable / adjustPackSize) }} {{ adjustPackUnit }}<span v-if="adjustAvailable % adjustPackSize !== 0"> +{{ adjustAvailable % adjustPackSize }}</span>)
                      </span>
                    </span>
                  </div>
                </div>

                <!-- Set-Schnellbuttons wenn Verpackungseinheit vorhanden -->
                <div v-if="adjustPackSize && adjustPackUnit && adjustPackSize > 1" class="adjust-set-buttons">
                  <label>Schnellauswahl {{ adjustPackUnit }}</label>
                  <div class="adjust-set-row">
                    <button
                      v-for="n in [1, 2, 5, 10]"
                      :key="'set-' + n"
                      class="mat-set-btn"
                      :disabled="n * adjustPackSize > adjustMaxAllowed"
                      @click="adjustNewQty = n * adjustPackSize"
                      :class="{ active: adjustNewQty === n * adjustPackSize }"
                    >
                      {{ n }} {{ adjustPackUnit }}
                      <span class="set-btn-detail">({{ n * adjustPackSize }} Stk.)</span>
                    </button>
                  </div>
                </div>

                <div class="adjust-input-group">
                  <label>Neue Menge (Einzelstück)</label>
                  <div class="adjust-qty-row">
                    <button class="btn-qty" @click="adjustNewQty = Math.max(0, adjustNewQty - 1)">−</button>
                    <input v-model.number="adjustNewQty" type="number" min="0" class="form-input adjust-qty-input" />
                    <button class="btn-qty" @click="adjustNewQty++">+</button>
                  </div>
                  <div v-if="adjustPackSize && adjustPackUnit && adjustNewQty > 0" class="adjust-qty-summary">
                    = {{ Math.floor(adjustNewQty / adjustPackSize) }} {{ adjustPackUnit }}<span v-if="adjustNewQty % adjustPackSize !== 0"> + {{ adjustNewQty % adjustPackSize }} Stk.</span>
                  </div>
                  <span v-if="adjustNewQty > adjustMaxAllowed && !adjustAvailableLoading" class="adjust-warning">
                    Nicht genug verfügbar! Max. {{ adjustMaxAllowed }} Stk.
                    <span v-if="adjustPackSize && adjustPackUnit">({{ Math.floor(adjustMaxAllowed / adjustPackSize) }} {{ adjustPackUnit }})</span>
                  </span>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-outline" @click="closeAdjustModal">Abbrechen</button>
                <button
                  v-if="adjustNewQty === 0"
                  class="btn btn-danger"
                  @click="confirmAdjust"
                >
                  Material entfernen
                </button>
                <button
                  v-else
                  class="btn btn-primary"
                  @click="confirmAdjust"
                  :disabled="adjustNewQty > adjustMaxAllowed && !adjustAvailableLoading"
                >
                  Menge auf {{ adjustNewQty }} Stk. ändern
                  <span v-if="adjustPackSize && adjustPackUnit">({{ Math.floor(adjustNewQty / adjustPackSize) }} {{ adjustPackUnit }})</span>
                </button>
              </div>
            </div>
          </div>
        </Teleport>
      </div>

      <!-- Tab: Auftrag Status (4-Stufen Workflow) -->
      <div v-if="activeDetailTab === 'packlist'" class="detail-body">
        <div v-if="isLoadingPackItems" class="loading-hint">Auftrag wird geladen...</div>
        <div v-else-if="packItems.length === 0" class="empty-hint">
          <p>Noch kein Auftrag vorhanden.</p>
          <button v-if="selectedActivity?.status === 'packing'" class="btn btn-sm btn-primary" @click="initPackItems">
            Auftrag starten
          </button>
        </div>
        <div v-else class="pack-workflow">
          <!-- Stufen-Tabs -->
          <div class="pack-stage-tabs">
            <button
              v-for="stage in PACK_STAGES"
              :key="stage.key"
              class="pack-stage-tab"
              :class="{ active: activePackStage === stage.key }"
              @click="activePackStage = stage.key; initMoveQtyInputs()"
            >
              {{ stage.leftLabel }} <span class="stage-arrow">&rarr;</span> {{ stage.rightLabel }}
            </button>
          </div>

          <!-- Fortschritt + Workflow-Actions -->
          <div class="pack-progress-bar">
            <div class="pack-progress-info">
              <span>{{ stageProgress }}% {{ activeStageConfig.rightLabel }}</span>
              <div class="pack-progress-actions">
                <button
                  v-if="stageLeftItems.length > 0"
                  class="btn btn-xs btn-outline btn-move-all"
                  @click="moveAllToNextStage"
                >
                  ALLES &rarr; {{ activeStageConfig.rightLabel }}
                </button>
                <!-- Workflow-Button: Nächster Status (immer sichtbar wenn Transition erlaubt) -->
                <button
                  v-if="nextWorkflowTransition"
                  class="btn btn-sm btn-progress-action"
                  :class="[getTransitionBtnClass(nextWorkflowTransition.status), { 'btn-progress-warn': stageProgress < 100 }]"
                  @click="handleWorkflowTransition"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" v-html="getTransitionIcon(nextWorkflowTransition.status)"></svg>
                  {{ nextWorkflowTransition.label }}
                  <span v-if="stageProgress < 100" class="btn-progress-warn-badge">{{ stageProgress }}%</span>
                </button>
              </div>
            </div>
            <div class="pack-progress-track">
              <div class="pack-progress-fill" :class="{ 'progress-complete': stageProgress === 100 }" :style="{ width: stageProgress + '%' }"></div>
            </div>
          </div>
          <div v-if="jsWorkflowSummary.items > 0" class="js-workflow-summary">
            <span class="mat-source-badge">J&amp;S</span>
            <span>Positionen: <strong>{{ jsWorkflowSummary.items }}</strong></span>
            <span>Erhalten: <strong>{{ jsWorkflowSummary.received }}</strong></span>
            <span>Rückgabe: <strong>{{ jsWorkflowSummary.returned }}</strong></span>
            <span>Verluste: <strong>{{ jsWorkflowSummary.losses }}</strong></span>
          </div>

          <!-- QR-Code + Drucken -->
          <div class="pack-qr-print-bar">
            <div v-if="activityQrDataUrl" class="pack-qr-box">
              <img :src="activityQrDataUrl" alt="QR-Code" class="pack-qr-img" />
              <span class="pack-qr-label">Scan zum Öffnen des Auftrags</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline pack-print-btn" @click="printActivity">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
              Auftrag drucken
            </button>
          </div>

          <div class="detail-card detail-card-full">
            <h3 class="detail-card-title">Kisten-Instanzen</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Kiste wählen</label>
                <select v-model="selectedPackContainerId" class="form-input" @change="loadSelectedPackContainerItems">
                  <option value="">– Kiste auswählen –</option>
                  <option v-for="container in packContainers" :key="container.id" :value="container.id">
                    {{ container.label }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Neue Kiste</label>
                <div style="display:flex; gap:8px;">
                  <input v-model="newPackContainerLabel" type="text" class="form-input" placeholder="z.B. Rako 01" />
                  <button class="btn btn-sm btn-outline" @click="createPackContainer">Anlegen</button>
                </div>
              </div>
            </div>
            <div v-if="selectedPackContainerId" class="form-row">
              <div class="form-group">
                <label>Material</label>
                <select v-model="newPackContainerItemMaterialId" class="form-input">
                  <option value="">– Material –</option>
                  <option v-for="item in detailItems" :key="item.materialItemId" :value="item.materialItemId">
                    {{ item.materialName }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Menge</label>
                <div style="display:flex; gap:8px;">
                  <input v-model.number="newPackContainerItemQty" type="number" min="1" class="form-input" />
                  <button class="btn btn-sm btn-primary" @click="addItemToPackContainer">Hinzufügen</button>
                </div>
              </div>
            </div>
            <div v-if="selectedPackContainerItems.length > 0" class="issues-list">
              <div v-for="item in selectedPackContainerItems" :key="item.id" class="issue-card">
                <div class="issue-header">
                  <span class="issue-material">{{ item.material_name }}</span>
                  <span class="issue-qty">&times;{{ item.quantity_packed }}</span>
                </div>
                <div class="issue-footer">
                  <span class="text-muted">{{ item.batch_label || item.serial_number || 'bulk' }}</span>
                  <button class="btn btn-sm btn-danger-outline" @click="removeItemFromPackContainer(item.id)">Entfernen</button>
                </div>
              </div>
            </div>
          </div>

          <div class="pack-panels">
            <!-- ═══ LINKE SEITE: Aktuelle Stufe ═══ -->
            <div class="pack-panel pack-panel-left">
              <div class="pack-panel-header">
                <span class="pack-panel-title">{{ activeStageConfig.leftLabel }}</span>
                <span class="pack-panel-count">{{ stageLeftItems.length }}</span>
              </div>
              <div v-if="stageLeftItems.length === 0" class="pack-panel-empty">
                Alles verschoben!
              </div>
              <div v-for="group in groupsLeft" :key="'l-' + group.categoryName" class="pack-group">
                <div class="pack-group-header" @click="toggleGroup('l-' + group.categoryName)">
                  <span class="pack-group-name">{{ group.categoryName }}</span>
                  <span class="pack-group-toggle">{{ collapsedGroups['l-' + group.categoryName] ? '&#9654;' : '&#9660;' }}</span>
                </div>
                <div v-if="!collapsedGroups['l-' + group.categoryName]" class="pack-group-items">
                  <div v-for="pi in group.items" :key="pi.id" class="pack-card">
                    <div class="pack-card-main">
                      <div class="pack-card-info">
                        <span class="pack-card-name">{{ pi.materialName }} <span v-if="pi.isJsMaterial" class="mat-source-badge">J&amp;S</span></span>
                        <span class="pack-card-detail">{{ getStageLeftQty(pi) }} / {{ getStageTotalQty(pi) }}</span>
                      </div>
                      <div class="pack-card-actions">
                        <!-- Am Event | Retour: Melde-Buttons auf linker Seite (Items am Event) -->
                        <template v-if="activePackStage === 'issued_returned'">
                          <template v-if="pi.isConsumable">
                            <button class="btn-issue-quick btn-issue-consumed" @click="openPackEditModal(pi, 'consumption')" title="Verbrauch melden">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/><path d="M8 12l3 3 5-5"/></svg>
                              Gebraucht
                            </button>
                          </template>
                          <template v-else>
                            <button class="btn-issue-quick btn-issue-loss" @click="openPackEditModal(pi, 'loss')" title="Verlust melden">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                              Verlust
                            </button>
                            <button class="btn-issue-quick btn-issue-repair" @click="openPackEditModal(pi, 'repair')" title="Reparatur melden">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                              Reparatur
                            </button>
                          </template>
                        </template>
                        <!-- Mengen-Input + Move-Button -->
                        <div class="pack-move-inline">
                          <input
                            v-model.number="moveQtyInputs[pi.id]"
                            type="number"
                            min="1"
                            :max="getStageLeftQty(pi)"
                            class="pack-move-input"
                            @keyup.enter="moveToNextStage(pi)"
                          />
                          <button class="btn-move-arrow" @click="moveToNextStage(pi)" title="Verschieben">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><polyline points="12 5 19 12 12 19"/></svg>
                          </button>
                        </div>
                        <!-- 3-Punkte (nur in Bestätigt/Gepackt Stufen) -->
                        <button v-if="activePackStage !== 'issued_returned'" class="btn-pack-menu" @click="openPackEditModal(pi)" title="Optionen">
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
                <span class="pack-panel-title">{{ activeStageConfig.rightLabel }}</span>
                <span class="pack-panel-count">{{ stageRightItems.length }}</span>
              </div>
              <div v-if="stageRightItems.length === 0" class="pack-panel-empty">
                Noch nichts verschoben
              </div>
              <div v-for="group in groupsRight" :key="'r-' + group.categoryName" class="pack-group">
                <div class="pack-group-header pack-group-header-done" @click="toggleGroup('r-' + group.categoryName)">
                  <span class="pack-group-name">{{ group.categoryName }}</span>
                  <span class="pack-group-toggle">{{ collapsedGroups['r-' + group.categoryName] ? '&#9654;' : '&#9660;' }}</span>
                </div>
                <div v-if="!collapsedGroups['r-' + group.categoryName]" class="pack-group-items">
                  <div v-for="pi in group.items" :key="pi.id" class="pack-card pack-card-done">
                    <div class="pack-card-main">
                      <div class="pack-card-actions pack-card-actions-left">
                        <!-- Zurück-Button: < Input -->
                        <button class="btn-moveback-arrow" @click="moveToPrevStage(pi)" title="Zurückverschieben">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
                        </button>
                        <input
                          v-model.number="moveBackQtyInputs[pi.id]"
                          type="number"
                          min="1"
                          :max="getStageRightQty(pi)"
                          class="pack-moveback-input"
                          @keyup.enter="moveToPrevStage(pi)"
                        />
                      </div>
                      <div class="pack-card-info">
                        <span class="pack-card-name">{{ pi.materialName }} <span v-if="pi.isJsMaterial" class="mat-source-badge">J&amp;S</span></span>
                        <span class="pack-card-detail">{{ getStageRightQty(pi) }} Stk.</span>
                      </div>
                      <div class="pack-card-actions">
                        <!-- Am Event / Retour Stufen: Aktionsbuttons -->
                        <template v-if="activePackStage === 'packed_issued' || activePackStage === 'issued_returned'">
                          <!-- Verbrauchsmaterial: "Gebraucht"-Button -->
                          <template v-if="pi.isConsumable">
                            <button class="btn-issue-quick btn-issue-consumed" @click="openPackEditModal(pi, 'consumption')" title="Verbrauch melden">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/><path d="M8 12l3 3 5-5"/></svg>
                              Gebraucht
                            </button>
                          </template>
                          <!-- Ausleihmaterial: Verlust/Reparatur-Buttons -->
                          <template v-else>
                            <button class="btn-issue-quick btn-issue-loss" @click="openPackEditModal(pi, 'loss')" title="Verlust melden">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                              Verlust
                            </button>
                            <button class="btn-issue-quick btn-issue-repair" @click="openPackEditModal(pi, 'repair')" title="Reparatur melden">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                              Reparatur
                            </button>
                          </template>
                        </template>
                        <!-- Andere Stufen: 3-Punkte-Menü -->
                        <button v-else class="btn-pack-menu" @click="openPackEditModal(pi)" title="Optionen">
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
            <div v-if="showPackEditModal" class="modal-overlay">
              <div class="modal-dialog pack-edit-modal">
                <div class="modal-header">
                  <h3>
                    <template v-if="packEditAction === 'edit'">Position bearbeiten</template>
                    <template v-else-if="packEditAction === 'loss'">Verlust melden</template>
                    <template v-else-if="packEditAction === 'consumption'">Verbrauch erfassen</template>
                    <template v-else>Reparatur melden</template>
                  </h3>
                  <button class="modal-close" @click="closePackEditModal">&times;</button>
                </div>
                <div class="modal-body">
                  <div class="pack-edit-name">{{ packEditItem?.materialName }}</div>

                  <!-- Action-Tabs im Modal -->
                  <div v-if="packEditAction === 'edit'" class="pack-edit-actions-bar">
                    <template v-if="packEditItem?.isConsumable">
                      <button class="btn btn-xs btn-success" @click="packEditAction = 'consumption'">Verbrauch erfassen</button>
                    </template>
                    <template v-else>
                      <button class="btn btn-xs btn-warning" @click="packEditAction = 'loss'">Verlust melden</button>
                      <button class="btn btn-xs btn-danger" @click="packEditAction = 'repair'">Reparatur melden</button>
                    </template>
                  </div>

                  <template v-if="packEditAction === 'edit'">
                    <div class="pack-edit-ordered">Bestellt: <strong>{{ packEditItem?.quantityOrdered }}</strong> Stk.</div>
                    <div class="pack-edit-field">
                      <label>Menge</label>
                      <div class="adjust-qty-row">
                        <button class="btn-qty" @click="packEditQty = Math.max(0, packEditQty - 1)">−</button>
                        <input v-model.number="packEditQty" type="number" min="0" class="form-input adjust-qty-input" />
                        <button class="btn-qty" @click="packEditQty++">+</button>
                      </div>
                    </div>
                    <div class="pack-edit-field">
                      <label>Zustand</label>
                      <select v-model="packEditCondition" class="form-input">
                        <option value="ok">OK</option>
                        <option value="leicht_beschaedigt">Leicht beschädigt</option>
                        <option value="beschaedigt">Beschädigt</option>
                      </select>
                    </div>
                    <div class="pack-edit-field">
                      <label>Notiz</label>
                      <textarea v-model="packEditNotes" class="form-input form-textarea" rows="2" placeholder="Optional..."></textarea>
                    </div>
                  </template>

                  <template v-else>
                    <!-- Verlust / Reparatur / Verbrauch -->
                    <div class="pack-edit-field">
                      <label>{{ packEditAction === 'loss' ? 'Verlorene Menge' : packEditAction === 'consumption' ? 'Gebrauchte Menge' : 'Defekte Menge' }}</label>
                      <div class="adjust-qty-row">
                        <button class="btn-qty" @click="packEditQty = Math.max(1, packEditQty - 1)">−</button>
                        <input v-model.number="packEditQty" type="number" min="1" class="form-input adjust-qty-input" />
                        <button class="btn-qty" @click="packEditQty++">+</button>
                      </div>
                      <!-- Set-Schnellbuttons bei Verbrauch mit packSize -->
                      <div v-if="packEditAction === 'consumption' && packEditItem?.packSize && packEditItem.packSize > 1" class="pack-edit-set-btns">
                        <button class="mat-quick-btn mat-set-btn" @click="packEditQty = (packEditItem?.packSize || 1)" :title="'1 ' + (packEditItem?.packUnit || 'Set')">
                          1 {{ packEditItem?.packUnit || 'Set' }}
                        </button>
                        <button class="mat-quick-btn mat-set-btn" @click="packEditQty = (packEditItem?.packSize || 1) * 2" :title="'2 ' + (packEditItem?.packUnit || 'Sets')">
                          2 {{ packEditItem?.packUnit || 'Sets' }}
                        </button>
                        <button class="mat-quick-btn mat-set-btn" @click="packEditQty = (packEditItem?.packSize || 1) * 5" :title="'5 ' + (packEditItem?.packUnit || 'Sets')">
                          5 {{ packEditItem?.packUnit || 'Sets' }}
                        </button>
                        <span class="pack-edit-set-hint">1 {{ packEditItem?.packUnit || 'Set' }} = {{ packEditItem?.packSize }} Stk.</span>
                      </div>
                    </div>
                    <div class="pack-edit-field">
                      <label>{{ packEditAction === 'consumption' ? 'Notiz (optional)' : 'Beschreibung' }}</label>
                      <textarea v-model="packEditNotes" class="form-input form-textarea" rows="3" :placeholder="packEditAction === 'consumption' ? 'Optional...' : 'Was ist passiert?'"></textarea>
                    </div>
                  </template>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-outline" :disabled="isPackEditSubmitting" @click="closePackEditModal">Abbrechen</button>
                  <button
                    class="btn"
                    :class="packEditAction === 'edit' ? 'btn-primary' : packEditAction === 'consumption' ? 'btn-success' : packEditAction === 'loss' ? 'btn-warning' : 'btn-danger'"
                    :disabled="isPackEditSubmitting"
                    @click="confirmPackEdit"
                  >
                    {{ isPackEditSubmitting ? 'Wird gesendet…' : (packEditAction === 'edit' ? 'Speichern' : packEditAction === 'consumption' ? 'Verbrauch buchen' : packEditAction === 'loss' ? 'Verlust melden' : 'Reparatur melden') }}
                  </button>
                </div>
              </div>
            </div>
          </Teleport>
        </div>
      </div>

      <!-- Tab: Reparaturen / Verluste -->
      <div v-if="activeDetailTab === 'issues'" class="detail-body">
        <div v-if="isLoadingIssues" class="loading-hint">Reparaturen / Verluste werden geladen...</div>
        <div v-else>
          <!-- Neue Meldung erstellen -->
          <div v-if="selectedActivity && ['issued', 'returned'].includes(selectedActivity.status)" class="issue-actions">
            <button v-if="!showIssueForm" class="btn btn-sm btn-warning" @click="showIssueForm = true">
              + Meldung erstellen
            </button>
            <div v-if="showIssueForm" class="issue-form">
              <h4>Neue Meldung</h4>
              <div class="form-row">
                <label>Typ</label>
                <select v-model="newIssue.type" class="form-input">
                  <option value="repair">Reparatur</option>
                  <option value="loss">Verlust</option>
                </select>
              </div>
              <div class="form-row">
                <label>Material <span class="issue-required">*</span></label>
                <div class="issue-mat-autocomplete">
                  <div class="issue-mat-selected" v-if="newIssue.materialItemId">
                    <span class="issue-mat-selected-name">{{ newIssue.materialName }}</span>
                    <button class="issue-mat-clear" @click="newIssue.materialItemId = ''; newIssue.materialName = ''; issueMatSearch = ''" title="Entfernen">&times;</button>
                  </div>
                  <input
                    v-else
                    v-model="issueMatSearch"
                    type="text"
                    class="form-input"
                    :class="{ 'input-required': !newIssue.materialItemId }"
                    placeholder="Material suchen (Pflichtfeld)..."
                    @focus="showIssueMatDropdown = true"
                    @blur="setTimeout(() => showIssueMatDropdown = false, 200)"
                    @keydown.escape="showIssueMatDropdown = false"
                  />
                  <Transition name="dropdown-fade">
                    <div v-if="showIssueMatDropdown && !newIssue.materialItemId" class="issue-mat-dropdown">
                      <div v-if="issueMatFiltered.length === 0" class="issue-mat-dropdown-empty">
                        Kein Material gefunden
                      </div>
                      <div
                        v-for="item in issueMatFiltered"
                        :key="item.materialItemId"
                        class="issue-mat-dropdown-item"
                        @click="selectIssueMaterial(item)"
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
                <input type="number" v-model.number="newIssue.quantity" class="form-input" min="1" />
              </div>
              <div class="form-row">
                <label>Beschreibung</label>
                <textarea v-model="newIssue.description" class="form-input form-textarea" rows="3" placeholder="Was ist passiert?"></textarea>
              </div>
              <div class="form-actions">
                <button class="btn btn-sm btn-primary" @click="createIssue">Meldung speichern</button>
                <button class="btn btn-sm btn-secondary" @click="showIssueForm = false; issueMatSearch = ''; showIssueMatDropdown = false">Abbrechen</button>
              </div>
            </div>
          </div>

          <!-- Gefilterte Liste (nur repair, loss, damage - kein consumption) -->
          <div v-if="issueReportsFiltered.length === 0 && !showIssueForm" class="empty-hint">
            <p>Keine Reparaturen oder Verluste gemeldet.</p>
          </div>
          <div v-else class="issues-list">
            <div v-for="issue in issueReportsFiltered" :key="issue.id" class="issue-card" :class="{ resolved: issue.resolved }">
              <div class="issue-header">
                <span class="issue-type-badge" :class="issue.type">{{ issue.typeLabel }}</span>
                <span v-if="issue.materialName" class="issue-material">{{ issue.materialName }}</span>
                <span class="issue-qty">&times;{{ issue.quantity }}</span>
                <span class="issue-time">{{ formatDateTimeWithSeconds(issue.reportedAt) }}</span>
              </div>
              <div v-if="issue.description" class="issue-description">{{ issue.description }}</div>
              <div class="issue-footer">
                <span v-if="issue.resolved" class="issue-resolved">
                  Erledigt {{ issue.resolvedAt ? formatDateTime(issue.resolvedAt) : '' }}
                </span>
                <span v-if="getWorkshopTicketForIssue(issue.id)" class="issue-workshop-state" :class="getWorkshopTicketForIssue(issue.id)?.status">
                  Werkstatt: {{ getWorkshopStatusLabel(getWorkshopTicketForIssue(issue.id)?.status || '') }}
                </span>
                <button
                  v-if="getWorkshopTicketForIssue(issue.id)"
                  class="btn btn-xs btn-workshop-open"
                  title="Werkstatt-Ticket öffnen"
                  @click="openWorkshopForIssue(issue.id)"
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
      <div v-if="activeDetailTab === 'consumables'" class="detail-body">
        <div v-if="isLoadingIssues || isLoadingDetailItems" class="loading-hint">Verbrauchsmaterial wird geladen...</div>
        <div v-else>
          <div v-if="consumableItems.length === 0" class="empty-hint">
            <p>Kein Verbrauchsmaterial in dieser Aktivität.</p>
          </div>
          <div v-else class="consumables-list">
            <div class="consumable-hint">
              Verbrauchtes Material hier abbuchen. Die Menge wird vom Bestand abgezogen.
            </div>
            <div v-for="ci in consumableItems" :key="ci.materialItemId" class="consumable-card">
              <div class="consumable-info">
                <span class="consumable-name">{{ ci.materialName }}</span>
                <span class="consumable-ordered">Bestellt: {{ ci.quantity }} Stk.</span>
                <span v-if="getConsumableUsed(ci.materialItemId) > 0" class="consumable-used">
                  Verbraucht: {{ getConsumableUsed(ci.materialItemId) }} Stk.
                </span>
              </div>
              <div class="consumable-actions">
                <div class="consumable-qty-row">
                  <button class="btn-qty" @click="consumableQtyInputs[ci.materialItemId] = Math.max(1, (consumableQtyInputs[ci.materialItemId] || 1) - 1)">−</button>
                  <input
                    v-model.number="consumableQtyInputs[ci.materialItemId]"
                    type="number"
                    min="1"
                    class="consumable-qty-input"
                  />
                  <button class="btn-qty" @click="consumableQtyInputs[ci.materialItemId] = (consumableQtyInputs[ci.materialItemId] || 1) + 1">+</button>
                </div>
                <button class="btn btn-sm btn-warning" @click="reportConsumption(ci)">
                  Verbrauch buchen
                </button>
              </div>
            </div>
          </div>

          <!-- Verbrauch-Historie -->
          <div v-if="consumptionReports.length > 0" class="consumable-history">
            <h4>Gebuchter Verbrauch</h4>
            <div v-for="cr in consumptionReports" :key="cr.id" class="consumable-history-item">
              <span class="consumable-history-name">{{ cr.materialName || 'Material' }}</span>
              <span class="consumable-history-qty">&times;{{ cr.quantity }}</span>
              <span class="consumable-history-time">{{ formatDateTime(cr.reportedAt) }}</span>
              <span v-if="cr.description" class="consumable-history-desc">{{ cr.description }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab: Kosten -->
      <div v-if="activeDetailTab === 'costs'" class="detail-body">
        <div v-if="isLoadingDetailItems || isLoadingWorkshopCosts" class="loading-hint">Kostenaufstellung wird geladen...</div>
        <div v-else>
          <div class="costs-overview">
            <!-- Verbrauchsmaterial-Kosten -->
            <div class="costs-section">
              <h3 class="costs-section-title">
                <span class="costs-icon">🔥</span> Verbrauchsmaterial
              </h3>
              <div v-if="costConsumableItems.length === 0" class="costs-empty">Kein Verbrauchsmaterial</div>
              <div v-else>
                <div class="costs-table">
                  <div class="costs-row costs-row-header">
                    <span class="costs-col-name">Material</span>
                    <span class="costs-col-qty">Bestellt</span>
                    <span class="costs-col-used">Verbraucht</span>
                    <span class="costs-col-price">Stückpreis</span>
                    <span class="costs-col-total">Betrag</span>
                  </div>
                  <div v-for="item in costConsumableItems" :key="item.materialItemId" class="costs-row">
                    <span class="costs-col-name">{{ item.materialName }}</span>
                    <span class="costs-col-qty">{{ item.quantity }}</span>
                    <span class="costs-col-used">{{ getConsumableUsed(item.materialItemId) || '–' }}</span>
                    <span class="costs-col-price">CHF {{ item.salePrice ? Number(item.salePrice).toFixed(2) : '–' }}</span>
                    <span class="costs-col-total">
                      <template v-if="item.salePrice && getConsumableUsed(item.materialItemId) > 0">
                        CHF {{ (Number(item.salePrice) * getConsumableUsed(item.materialItemId)).toFixed(2) }}
                      </template>
                      <template v-else>CHF 0.00</template>
                    </span>
                  </div>
                </div>
                <div class="costs-subtotal">
                  <span>Verbrauchsmaterial Gesamt:</span>
                  <strong>CHF {{ costConsumableTotal.toFixed(2) }}</strong>
                </div>
              </div>
            </div>

            <!-- Ausleihmaterial-Kosten (nur bei Extern) -->
            <div v-if="selectedActivity?.type === 'external'" class="costs-section">
              <h3 class="costs-section-title">
                <span class="costs-icon">📦</span> Ausleihmaterial
              </h3>
              <div v-if="costRentalItems.length === 0" class="costs-empty">Kein Ausleihmaterial</div>
              <div v-else>
                <div class="costs-table">
                  <div class="costs-row costs-row-header">
                    <span class="costs-col-name">Material</span>
                    <span class="costs-col-qty">Menge</span>
                    <span class="costs-col-used"></span>
                    <span class="costs-col-price">Stückpreis</span>
                    <span class="costs-col-total">Betrag</span>
                  </div>
                  <div v-for="item in costRentalItems" :key="item.materialItemId" class="costs-row">
                    <span class="costs-col-name">{{ item.materialName }}</span>
                    <span class="costs-col-qty">{{ item.quantity }}</span>
                    <span class="costs-col-used"></span>
                    <span class="costs-col-price">{{ item.unitPrice ? 'CHF ' + item.unitPrice.toFixed(2) : '–' }}</span>
                    <span class="costs-col-total">{{ item.lineTotal ? 'CHF ' + item.lineTotal.toFixed(2) : '–' }}</span>
                  </div>
                </div>
                <div class="costs-subtotal">
                  <span>Ausleihmaterial Gesamt:</span>
                  <strong>CHF {{ costRentalTotal.toFixed(2) }}</strong>
                </div>
              </div>
            </div>

            <!-- Verluste -->
            <div v-if="costLossItems.length > 0" class="costs-section costs-section-warn">
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
                <div v-for="loss in costLossItems" :key="loss.id" class="costs-row">
                  <span class="costs-col-name">{{ loss.materialName || '–' }}</span>
                  <span class="costs-col-qty">{{ loss.quantity }}</span>
                  <span class="costs-col-used"></span>
                  <span class="costs-col-price"></span>
                  <span class="costs-col-total costs-loss-desc">{{ loss.description || '–' }}</span>
                </div>
              </div>
            </div>

            <!-- Werkstattkosten: Reparatur -->
            <div v-if="costRepairTickets.length > 0" class="costs-section">
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
                <div v-for="t in costRepairTickets" :key="t.id" class="costs-row">
                  <span class="costs-col-name">{{ t.title }}</span>
                  <span class="costs-col-qty">{{ t.status_label }}</span>
                  <span class="costs-col-used"></span>
                  <span class="costs-col-price"></span>
                  <span class="costs-col-total">CHF {{ Number(t.actual_cost || 0).toFixed(2) }}</span>
                </div>
              </div>
              <div class="costs-subtotal">
                <span>Reparaturkosten Gesamt:</span>
                <strong>CHF {{ costRepairTotal.toFixed(2) }}</strong>
              </div>
            </div>

            <!-- Werkstattkosten: Abschreibung -->
            <div v-if="costWriteoffTickets.length > 0" class="costs-section costs-section-warn">
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
                <div v-for="t in costWriteoffTickets" :key="t.id" class="costs-row">
                  <span class="costs-col-name">{{ t.title }}</span>
                  <span class="costs-col-qty">{{ t.status_label }}</span>
                  <span class="costs-col-used"></span>
                  <span class="costs-col-price"></span>
                  <span class="costs-col-total">CHF {{ Number(t.actual_cost || 0).toFixed(2) }}</span>
                </div>
              </div>
              <div class="costs-subtotal">
                <span>Abschreibungskosten Gesamt:</span>
                <strong>CHF {{ costWriteoffTotal.toFixed(2) }}</strong>
              </div>
            </div>

            <!-- Gesamt / Endabrechnung -->
            <div class="costs-total-section" :class="{ 'costs-final': ['returned', 'completed'].includes(selectedActivity?.status || '') }">
              <div class="costs-total-label">
                <template v-if="['returned', 'completed'].includes(selectedActivity?.status || '')">
                  <strong>Endabrechnung</strong>
                </template>
                <template v-else>
                  <strong>Zwischenstand</strong>
                  <span class="costs-total-hint">(Endabrechnung bei Retour)</span>
                </template>
              </div>
              <div class="costs-total-rows">
                <div v-if="costConsumableTotal > 0" class="costs-total-row">
                  <span>Verbrauchsmaterial</span>
                  <span>CHF {{ costConsumableTotal.toFixed(2) }}</span>
                </div>
                <div v-if="selectedActivity?.type === 'external' && costRentalTotal > 0" class="costs-total-row">
                  <span>Ausleihmaterial</span>
                  <span>CHF {{ costRentalTotal.toFixed(2) }}</span>
                </div>
                <div v-if="costRepairTotal > 0" class="costs-total-row">
                  <span>Werkstatt Reparatur</span>
                  <span>CHF {{ costRepairTotal.toFixed(2) }}</span>
                </div>
                <div v-if="costWriteoffTotal > 0" class="costs-total-row">
                  <span>Werkstatt Abschreibung</span>
                  <span>CHF {{ costWriteoffTotal.toFixed(2) }}</span>
                </div>
                <div class="costs-total-row costs-grand-total">
                  <span>{{ selectedActivity?.type === 'external' ? 'Gesamtbetrag (extern)' : 'Interne Gesamtkosten' }}</span>
                  <span>CHF {{ selectedActivity?.type === 'external' ? costExternalGrandTotal.toFixed(2) : costInternalGrandTotal.toFixed(2) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab: Rückgabe -->
      <div v-if="activeDetailTab === 'returns'" class="detail-body">
        <div v-if="isLoadingReturns" class="loading-hint">Rückgabeliste wird geladen...</div>
        <div v-else-if="returnItems.length === 0" class="empty-hint">
          <p>Noch keine Rückgabe erfasst.</p>
          <button v-if="selectedActivity?.status === 'returned'" class="btn btn-sm btn-primary" @click="initReturnItems">
            Rückgabeliste erstellen
          </button>
        </div>
        <div v-else class="detail-returns">
          <!-- Zusammenfassung -->
          <div class="return-summary">
            <div class="summary-item" :class="{ 'has-issues': returnItems.some(r => r.hasDifferences) }">
              <span class="summary-label">Positionen mit Differenzen:</span>
              <span class="summary-value">{{ returnItems.filter(r => r.hasDifferences).length }} / {{ returnItems.length }}</span>
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
              <tr v-for="ri in returnItems" :key="ri.id" :class="{ 'row-difference': ri.hasDifferences }">
                <td class="material-name">{{ ri.materialName }}</td>
                <td class="col-qty">{{ ri.quantityPacked }}</td>
                <td class="col-qty">
                  <input
                    v-if="selectedActivity && selectedActivity.status === 'returned'"
                    type="number"
                    class="qty-input"
                    :value="ri.quantityReturned"
                    min="0"
                    @change="(e) => updateReturnItem(ri, 'quantity_returned', parseInt(e.target.value) || 0)"
                  />
                  <span v-else>{{ ri.quantityReturned }}</span>
                </td>
                <td class="col-qty">
                  <input
                    v-if="selectedActivity && selectedActivity.status === 'returned'"
                    type="number"
                    class="qty-input qty-damaged"
                    :value="ri.quantityDamaged"
                    min="0"
                    @change="(e) => updateReturnItem(ri, 'quantity_damaged', parseInt(e.target.value) || 0)"
                  />
                  <span v-else :class="{ 'text-danger': ri.quantityDamaged > 0 }">{{ ri.quantityDamaged }}</span>
                </td>
                <td class="col-qty">
                  <input
                    v-if="selectedActivity && selectedActivity.status === 'returned'"
                    type="number"
                    class="qty-input qty-missing"
                    :value="ri.quantityMissing"
                    min="0"
                    @change="(e) => updateReturnItem(ri, 'quantity_missing', parseInt(e.target.value) || 0)"
                  />
                  <span v-else :class="{ 'text-danger': ri.quantityMissing > 0 }">{{ ri.quantityMissing }}</span>
                </td>
                <td class="col-condition">
                  <select
                    v-if="selectedActivity && selectedActivity.status === 'returned'"
                    class="condition-select"
                    :value="ri.conditionIn"
                    @change="onReturnConditionChange(ri, $event)"
                  >
                    <option value="ok">OK</option>
                    <option value="leicht_beschaedigt">Leicht beschädigt</option>
                    <option value="beschaedigt">Beschädigt</option>
                    <option value="defekt">Defekt</option>
                  </select>
                  <span v-else class="condition-badge" :class="ri.conditionIn">{{ getConditionLabel(ri.conditionIn) }}</span>
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
      <div v-if="activeDetailTab === 'history'" class="detail-body">
        <div v-if="isLoadingHistory" class="loading-hint">Verlauf wird geladen...</div>
        <div v-else-if="activityHistory.length === 0" class="empty-hint">
          <p>Noch keine Einträge im Verlauf.</p>
        </div>
        <div v-else class="detail-history">
          <div v-for="entry in activityHistory" :key="entry.id" class="history-entry">
            <div class="history-dot" :class="entry.action"></div>
            <div class="history-content">
              <div class="history-header">
                <span class="history-action">{{ getHistoryActionLabel(entry.action) }}</span>
                <span class="history-time">{{ formatDateTime(entry.createdAt) }}</span>
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

    <!-- ═══ Listen-Ansicht ═══ -->
    <template v-else>
      <!-- Header -->
      <div class="activities-header page-header header-content">
        <div class="header-left">
          <h1>Aktivitäten</h1>
          <span class="subtitle">Events, Vermietungen & Ausleihen verwalten</span>
        </div>
        <div class="header-right">
          <button class="btn btn-primary" @click="openNewWizard">
            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="12" y1="5" x2="12" y2="19"/>
              <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Neue Aktivität
          </button>
        </div>
      </div>

      <!-- Stats Bar -->
      <div v-if="!isLoading && activities.length > 0" class="stats-bar">
        <div class="stat-item">
          <span class="stat-value">{{ activities.length }}</span>
          <span class="stat-label">Gesamt</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-draft">{{ activities.filter(a => a.status === 'draft').length }}</span>
          <span class="stat-label">Entwürfe</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-submitted">{{ activities.filter(a => a.status === 'submitted').length }}</span>
          <span class="stat-label">Eingereicht</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-approved">{{ activities.filter(a => ['approved', 'packing', 'packed'].includes(a.status)).length }}</span>
          <span class="stat-label">In Bearbeitung</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-issued">{{ activities.filter(a => a.status === 'issued').length }}</span>
          <span class="stat-label">Ausgegeben</span>
        </div>
        <div class="stat-item">
          <span class="stat-value stat-completed">{{ activities.filter(a => a.status === 'completed').length }}</span>
          <span class="stat-label">Abgeschlossen</span>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="filter-bar">
        <div class="filter-tabs">
          <button 
            v-for="tab in tabs" 
            :key="tab.key"
            class="filter-tab"
            :class="{ active: activeTab === tab.key }"
            @click="activeTab = tab.key"
          >
            {{ tab.label }}
            <span v-if="tab.count !== undefined" class="tab-count">{{ tab.count }}</span>
          </button>
        </div>
        <div class="filter-actions">
          <div class="type-filter-chips">
            <button 
              v-for="tpl in typeFilterChips"
              :key="tpl.type"
              class="type-chip"
              :class="{ active: activeTypeFilter === tpl.type }"
              @click="activeTypeFilter = activeTypeFilter === tpl.type ? '' : tpl.type"
            >{{ tpl.label }}</button>
          </div>
          <div class="search-box">
            <GlobalSearchInput
              mode="inline"
              :department-id="departmentId"
              default-type="activity"
              v-model="searchQuery"
              placeholder="Suchen (material:, aktivität:, reparatur:)"
            />
          </div>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="isLoading" class="loading-state">
        <div class="spinner"></div>
        <p>Aktivitäten werden geladen...</p>
      </div>

      <!-- Activities Table -->
      <div v-else class="activities-table-wrapper">
        <table class="activities-table">
          <thead>
            <tr>
              <th class="col-status"></th>
              <th class="col-name" @click="toggleSort('name')">
                Name
                <span v-if="sortField === 'name'" class="sort-icon">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </th>
              <th class="col-type">Typ</th>
              <th class="col-customer">Gruppe / Kunde</th>
              <th class="col-period" @click="toggleSort('date')">
                Zeitraum
                <span v-if="sortField === 'date'" class="sort-icon">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </th>
              <th class="col-items">Material</th>
              <th class="col-price" @click="toggleSort('price')">
                Preis
                <span v-if="sortField === 'price'" class="sort-icon">{{ sortDir === 'asc' ? '↑' : '↓' }}</span>
              </th>
              <th class="col-progress">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredActivities.length === 0">
              <td colspan="8" class="empty-state">
                <div class="empty-content">
                  <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                  </svg>
                  <p>{{ searchQuery || activeTypeFilter ? 'Keine Treffer' : 'Noch keine Aktivitäten vorhanden' }}</p>
                  <button v-if="!searchQuery && !activeTypeFilter" class="btn btn-outline" @click="openNewWizard">
                    Erste Aktivität erstellen
                  </button>
                </div>
              </td>
            </tr>
            <tr 
              v-for="activity in filteredActivities" 
              :key="activity.id"
              class="activity-row"
              :class="{ 'row-draft': activity.status === 'draft' }"
              @dblclick="openActivity(activity)"
              @click="selectActivity(activity)"
            >
              <td class="col-status">
                <span class="status-dot" :class="activity.status"></span>
              </td>
              <td class="col-name">
                <div class="activity-name">{{ activity.name }}</div>
                <div class="activity-no" v-if="activity.no">{{ activity.no }}</div>
                <div class="activity-share-hint" v-if="getActivityShareHint(activity)">{{ getActivityShareHint(activity) }}</div>
                <div class="activity-share-status" v-if="getActivityShareStatus(activity)">{{ getActivityShareStatus(activity) }}</div>
              </td>
              <td class="col-type">
                <span class="type-badge" :class="activity.type">{{ getTypeLabel(activity.type) }}</span>
              </td>
              <td class="col-customer">
                <span v-if="activity.groupName" class="customer-group">{{ activity.groupName }}</span>
                <span v-if="activity.customerName" class="customer-name">{{ activity.customerName }}</span>
                <span v-if="!activity.groupName && !activity.customerName" class="text-muted">–</span>
              </td>
              <td class="col-period">
                <span v-if="activity.usageStart" class="period-display">
                  <span class="period-date">{{ formatDateShort(activity.usageStart) }}</span>
                  <span v-if="activity.usageEnd && !isSameDay(activity.usageStart, activity.usageEnd)" class="period-separator">–</span>
                  <span v-if="activity.usageEnd && !isSameDay(activity.usageStart, activity.usageEnd)" class="period-date">{{ formatDateShort(activity.usageEnd) }}</span>
                  <span class="period-relative">{{ getRelativeDate(activity.usageStart) }}</span>
                </span>
                <span v-else class="text-muted">–</span>
              </td>
              <td class="col-items">
                <span v-if="activity.itemCount" class="items-badge">{{ activity.itemCount }}</span>
                <span v-else class="text-muted">0</span>
              </td>
              <td class="col-price">
                <span v-if="activity.totalPrice" class="price-display">CHF {{ activity.totalPrice.toFixed(2) }}</span>
                <span v-else class="text-muted">–</span>
              </td>
              <td class="col-progress">
                <span class="status-label" :class="activity.status">{{ getStatusLabel(activity.status) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="filteredActivities.length > 0" class="table-footer">
          <span>{{ filteredActivities.length }} von {{ activities.length }} Aktivitäten</span>
        </div>
      </div>
    </template>

    <!-- New Activity Wizard (Teleport like Material Wizard) -->
    <Teleport to="body">
      <div v-if="showNewDialog" class="wizard-overlay" @keydown.escape.stop="handleCloseWizard">
        <div class="wizard-modal" @keydown.enter.prevent="handleWizardEnter">
          <!-- Header -->
          <div class="wizard-header">
            <div class="header-title">
              <span v-if="wizardMode === 'wizard' && selectedTemplate" class="header-type-badge" :class="selectedTemplate" v-html="getSelectedTemplateIcon()"></span>
              <h2>{{ wizardMode === 'quick' ? 'AKTIVITÄT ERSTELLEN' : getTypeLabel(selectedTemplate).toUpperCase() + ' ERSTELLEN' }}</h2>
              <span v-if="wizardMode === 'wizard' && wizardStep > 0" class="header-step-indicator">Schritt {{ wizardStep }} von 4</span>
            </div>
            <button class="close-btn" @click="handleCloseWizard" title="Schliessen (Esc)">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>

          <!-- Content -->
          <div class="wizard-content" :class="{ 'quick-mode': wizardMode === 'quick' }">
            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ═══ MODUS A: SCHNELLFORMULAR (Aktivität) ═══════ -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div v-if="wizardMode === 'quick'" class="wizard-form quick-form">

              <!-- Typ-Auswahl (Icon-Chips) -->
              <div class="type-chip-row">
                <button 
                  v-for="tmpl in activityTemplates" 
                  :key="tmpl.type"
                  class="type-chip"
                  :class="[tmpl.type, { active: selectedTemplate === tmpl.type }]"
                  @click="selectTemplate(tmpl.type)"
                >
                  <span class="type-chip-icon" v-html="tmpl.icon"></span>
                  <span class="type-chip-name">{{ tmpl.name }}</span>
                </button>
              </div>

              <!-- Name -->
              <div class="form-group">
                <label>Name *</label>
                <input 
                  ref="nameInputRef"
                  v-model="newActivity.name" 
                  type="text" 
                  class="form-input"
                  :placeholder="getNamePlaceholder()"
                />
              </div>

              <!-- Datum + Uhrzeit (eine Zeile) -->
              <div class="form-row form-row-3">
                <div class="form-group">
                  <label>Datum *</label>
                  <input v-model="activityDate" type="date" class="form-input" :min="todayStr" />
                </div>
                <div class="form-group">
                  <label>Von *</label>
                  <input v-model="activityTimeStart" type="time" step="900" class="form-input" />
                </div>
                <div class="form-group">
                  <label>Bis *</label>
                  <input v-model="activityTimeEnd" type="time" step="900" class="form-input" />
                </div>
              </div>
              <div v-if="activityDuration" class="duration-info">
                {{ activityDuration }}
              </div>

              <!-- Gruppe (Hierarchie-Picker) -->
              <div class="form-group">
                <label>Gruppe *</label>
                <div v-if="isLoadingGroups" class="form-input form-input-placeholder">Gruppen werden geladen...</div>
                <div v-else class="group-picker">
                  <!-- Trigger-Button -->
                  <button 
                    type="button"
                    class="group-picker-trigger form-input"
                    :class="{ open: showGroupDropdown, 'has-value': newActivity.groupId }"
                    @click="showGroupDropdown = !showGroupDropdown"
                  >
                    <template v-if="newActivity.groupId">
                      <svg class="group-picker-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                      {{ getSelectedGroupName() }}
                    </template>
                    <template v-else>
                      <span class="group-picker-placeholder">Gruppe wählen...</span>
                    </template>
                    <svg class="group-picker-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="6 9 12 15 18 9"/></svg>
                  </button>
                  <!-- Dropdown -->
                  <Transition name="dropdown-fade">
                    <div v-if="showGroupDropdown" class="group-dropdown">
                      <div v-if="myGroups.length > 5" class="group-dropdown-search">
                        <input v-model="groupSearchQuery" type="text" class="form-input" placeholder="Gruppe suchen..." @click.stop />
                      </div>
                      <div class="group-dropdown-list">
                        <div 
                          v-for="grp in filteredGroups" 
                          :key="grp.id"
                          class="group-dropdown-item"
                          :class="{ 
                            selected: newActivity.groupId === grp.id,
                            disabled: !grp.selectable,
                            'is-folder': grp.hasChildren && !grp.selectable
                          }"
                          :style="{ paddingLeft: `${12 + grp.level * 20}px` }"
                          @click="grp.selectable ? (newActivity.groupId = grp.id, showGroupDropdown = false, groupSearchQuery = '') : null"
                        >
                          <svg v-if="grp.hasChildren" class="group-item-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                          <svg v-else class="group-item-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                          <span class="group-dropdown-name">{{ grp.name }}</span>
                          <span v-if="grp.role === 'leader'" class="group-dropdown-role role-leader">Leiter</span>
                          <span v-else-if="grp.role === 'member'" class="group-dropdown-role role-member">Mitglied</span>
                          <span v-else-if="!grp.selectable" class="group-dropdown-role role-none">—</span>
                          <svg v-if="newActivity.groupId === grp.id" class="group-dropdown-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <div v-if="filteredGroups.length === 0" class="group-dropdown-empty">Keine Gruppen gefunden</div>
                      </div>
                    </div>
                  </Transition>
                </div>
              </div>

              <!-- Material * (Pflicht) -->
              <div class="form-group">
                <label>Material *</label>
                <!-- Vorschläge (Quick-Add-Chips) -->
                <div v-if="materialSuggestions.length > 0" class="suggestion-chips">
                  <div class="suggestion-label">{{ suggestionsLabel }}:</div>
                  <div class="suggestion-list">
                    <button 
                      v-for="sug in materialSuggestions" 
                      :key="sug.material_item_id"
                      class="suggestion-chip"
                      :class="{ active: isMaterialSelected(sug.material_item_id) }"
                      @click="addSuggestion(sug)"
                    >
                      <span class="suggestion-chip-plus">{{ isMaterialSelected(sug.material_item_id) ? '✓' : '+' }}</span>
                      {{ sug.name }}
                      <span class="suggestion-chip-qty">&times;{{ sug.avg_quantity }}</span>
                    </button>
                  </div>
                </div>
                <div v-else-if="isLoadingSuggestions" class="suggestion-chips">
                  <div class="suggestion-label suggestion-loading">Vorschläge werden geladen...</div>
                </div>
                <div v-if="canUseJsMaterialSource" class="mat-source-switch" role="tablist" aria-label="Materialquelle">
                  <button type="button" class="mat-source-btn" :class="{ active: materialSource === 'internal' }" @click="setMaterialSource('internal')">Eigenes</button>
                  <button type="button" class="mat-source-btn" :class="{ active: materialSource === 'js' }" @click="setMaterialSource('js')">J&amp;S</button>
                </div>
                <p v-else class="mat-source-hint">J&amp;S-Material ist nur bei Event oder Camp verfügbar.</p>
                <div v-if="canUseJsMaterialSource && materialSource === 'js'" class="js-order-card">
                  <div class="js-order-header">
                    <strong>J&amp;S-Bestellformular</strong>
                    <button type="button" class="btn btn-xs btn-secondary" @click="showJsOrderForm = !showJsOrderForm">
                      {{ showJsOrderForm ? 'Ausblenden' : 'Ausfüllen' }}
                    </button>
                  </div>
                  <div v-if="showJsOrderForm" class="js-order-body">
                    <div class="form-row">
                      <div class="form-group">
                        <label>Kursart</label>
                        <input v-model="jsOrderForm.courseType" type="text" class="form-input" placeholder="z.B. Lager / Trekking" />
                      </div>
                      <div class="form-group">
                        <label>Anzahl Teilnehmende</label>
                        <input v-model.number="jsOrderForm.participants" type="number" min="1" class="form-input" placeholder="z.B. 42" />
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group">
                        <label>Lieferdatum</label>
                        <input v-model="jsOrderForm.deliveryDate" type="date" class="form-input" />
                      </div>
                      <div class="form-group">
                        <label>Rücklieferung</label>
                        <input v-model="jsOrderForm.returnDate" type="date" class="form-input" />
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group">
                        <label>Logistik</label>
                        <select v-model="jsOrderForm.logistics" class="form-select">
                          <option value="lieferung">Lieferung ins Lager</option>
                          <option value="abholung">Abholung im J&amp;S Lager</option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label>Kontaktperson</label>
                        <input v-model="jsOrderForm.contactPerson" type="text" class="form-input" placeholder="Name / Tel / Mail" />
                      </div>
                    </div>
                    <div class="form-group">
                      <label>Bemerkungen</label>
                      <textarea v-model="jsOrderForm.notes" class="form-input form-textarea" rows="2" placeholder="Optionale Hinweise zur Bestellung..."></textarea>
                    </div>
                    <div class="js-order-actions">
                      <button type="button" class="btn btn-sm btn-secondary" @click="resetJsOrderForm">Zurücksetzen</button>
                      <button type="button" class="btn btn-sm btn-next" @click="applyJsOrderToActivityNotes">In Notizen übernehmen</button>
                    </div>
                  </div>
                </div>
                <!-- Material-Suchfeld -->
                <div class="mat-autocomplete">
                  <MaterialLookupInput
                    ref="materialSearchInput"
                    v-model="materialSearch"
                    :fetcher="activityMaterialLookupFetcher"
                    :min-chars="2"
                    :max-suggestions="20"
                    placeholder="Material suchen..."
                    :input-class="'mat-search-input'"
                    :loading-text="'Suche...'"
                    :empty-text="'Keine Treffer'"
                    @select="handleActivityLookupSelect"
                  >
                    <template #results="{ results, isLoading, activeIndex, setActiveIndex }">
                      <div v-if="isLoading" class="mat-dropdown-loading">Suche...</div>
                      <div v-else-if="results.length === 0" class="mat-dropdown-empty">Keine Treffer</div>
                      <div v-else class="mat-dropdown-list">
                        <div
                          v-for="(mat, index) in results"
                          :key="mat.materialItemId"
                          class="mat-dropdown-item"
                          :class="{ active: activeIndex === index, 'already-added': isMaterialSelected(mat.materialItemId), 'unavailable': mat.availableForPeriod <= 0 }"
                          @mouseenter="setActiveIndex(index)"
                        >
                          <div class="mat-dropdown-info">
                            <span class="mat-dropdown-name">
                              <span v-if="mat.isConsumable" class="mat-type-icon consumable" title="Verbrauchsmaterial">&#x1F525;</span>
                              <span v-else class="mat-type-icon rental" title="Ausleihmaterial">&#x1F4E6;</span>
                              {{ mat.name }}
                              <span v-if="mat.isJsMaterial" class="mat-source-badge">J&amp;S</span>
                            </span>
                            <span class="mat-dropdown-meta">
                              <span class="mat-dropdown-stock">
                                <span :class="mat.availableForPeriod > 0 ? 'text-green' : 'text-red'">{{ mat.availableForPeriod }}</span>
                                <span class="text-muted"> / {{ mat.totalStock }}</span>
                              </span>
                            </span>
                          </div>
                          <div class="mat-dropdown-actions">
                            <template v-if="isMaterialSelected(mat.materialItemId)"><span class="mat-already-badge">&#x2713; drin</span></template>
                            <template v-else-if="mat.availableForPeriod > 0">
                              <button class="mat-quick-btn" @click="addMaterialWithQty(mat, 1)">+1</button>
                              <button v-if="mat.availableForPeriod >= 5" class="mat-quick-btn" @click="addMaterialWithQty(mat, 5)">+5</button>
                              <button v-if="mat.availableForPeriod >= 10" class="mat-quick-btn" @click="addMaterialWithQty(mat, 10)">+10</button>
                            </template>
                            <template v-else><span class="mat-unavailable-badge">nicht verfügbar</span></template>
                          </div>
                        </div>
                      </div>
                    </template>
                  </MaterialLookupInput>
                </div>
                <!-- Ausgewählte Materialien (kompakt) -->
                <div v-if="newActivity.selectedItems.length > 0" class="mat-selected">
                  <div class="mat-selected-list">
                    <div v-for="item in newActivity.selectedItems" :key="item.materialItemId" class="mat-selected-row">
                      <span class="mat-selected-name">
                        {{ item.materialName }}
                        <span v-if="item.isJsMaterial" class="mat-source-badge">J&amp;S</span>
                      </span>
                      <div class="mat-selected-controls">
                        <button class="btn-qty" @click="changeMaterialQty(item.materialItemId, -1)">−</button>
                        <span class="material-qty" :class="{ 'qty-exceeded': isJsQuantityExceeded(item) }">{{ item.quantity }}</span>
                        <button class="btn-qty" @click="changeMaterialQty(item.materialItemId, 1)" :disabled="item.quantity >= item.availableQuantity">+</button>
                        <span v-if="isJsQuantityExceeded(item)" class="js-qty-warning">max {{ getJsAllowedQty(item) }}</span>
                        <span v-else-if="needsJsParticipants(item)" class="js-qty-hint">Teilnehmende erfassen</span>
                        <button class="btn-remove" @click="removeMaterial(item.materialItemId)">&#x2715;</button>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Hinweis wenn noch kein Material -->
                <div v-if="newActivity.selectedItems.length === 0 && !isLoadingSuggestions" class="mat-empty-hint">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  Mindestens 1 Material hinzufügen
                </div>
              </div>

              <!-- ─── Optional ─── -->
              <div class="quick-divider">
                <span>optional</span>
              </div>

              <!-- Notizen: Expandable -->
              <div class="quick-expand-section">
                <button class="quick-expand-btn" :class="{ expanded: showQuickNotes || newActivity.notes?.trim() }" @click="showQuickNotes = !showQuickNotes">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                  Notizen
                  <svg class="quick-expand-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <Transition name="step-slide">
                  <div v-if="showQuickNotes || newActivity.notes?.trim()" class="quick-expand-content">
                    <textarea v-model="newActivity.notes" class="form-input form-textarea" placeholder="Optionale Anmerkungen..." rows="2"></textarea>
                  </div>
                </Transition>
              </div>

            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ═══ MODUS B: 4-STEP WIZARD (Camp/Event/Extern) ═ -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div v-else class="wizard-form">

              <!-- Wizard Progress Bar -->
              <div class="wizard-progress">
                <div v-for="s in 4" :key="s" class="wizard-progress-step" :class="{ active: wizardStep === s, done: wizardStep > s }" @click="s < wizardStep ? wizardStep = s : null">
                  <span class="wizard-progress-number">
                    <template v-if="wizardStep > s">&#x2713;</template>
                    <template v-else>{{ s }}</template>
                  </span>
                  <span class="wizard-progress-label">{{ ['Grunddaten', 'Zeitraum', 'Material', 'Übersicht'][s - 1] }}</span>
                </div>
              </div>

              <!-- ═══ Wizard Step 1: Grunddaten (typ-spezifisch) ═══ -->
              <div v-if="wizardStep === WIZARD_STEP_GRUNDDATEN" class="wizard-step-content">
                <!-- Typ-Auswahl (Icon-Chips) -->
                <div class="type-chip-row">
                  <button 
                    v-for="tmpl in activityTemplates" 
                    :key="tmpl.type"
                    class="type-chip"
                    :class="[tmpl.type, { active: selectedTemplate === tmpl.type }]"
                    @click="selectTemplate(tmpl.type)"
                  >
                    <span class="type-chip-icon" v-html="tmpl.icon"></span>
                    <span class="type-chip-name">{{ tmpl.name }}</span>
                  </button>
                </div>

                <!-- Name (immer) -->
                <div class="form-group">
                  <label>{{ selectedTemplate === 'camp' ? 'Name des Lagers' : selectedTemplate === 'event' ? 'Name des Events' : 'Bezeichnung' }} *</label>
                  <input 
                    ref="nameInputRef"
                    v-model="newActivity.name" 
                    type="text" 
                    class="form-input"
                    :placeholder="getNamePlaceholder()"
                  />
                </div>

                <!-- Gruppe (Camp = Pflicht, Event = Optional) -->
                <div v-if="selectedTemplate !== 'external'" class="form-group">
                  <label>Gruppe {{ selectedTemplate === 'camp' ? '*' : '(optional)' }}</label>
                  <div v-if="isLoadingGroups" class="form-input form-input-placeholder">Gruppen werden geladen...</div>
                  <div v-else class="group-picker">
                    <!-- Trigger-Button -->
                    <button 
                      type="button"
                      class="group-picker-trigger form-input"
                      :class="{ open: showGroupDropdownWizard, 'has-value': newActivity.groupId }"
                      @click="showGroupDropdownWizard = !showGroupDropdownWizard"
                    >
                      <template v-if="newActivity.groupId">
                        <svg class="group-picker-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        {{ getSelectedGroupName() }}
                      </template>
                      <template v-else>
                        <span class="group-picker-placeholder">{{ selectedTemplate === 'camp' ? 'Gruppe wählen...' : 'Keine Gruppe' }}</span>
                      </template>
                      <svg class="group-picker-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <!-- Dropdown -->
                    <Transition name="dropdown-fade">
                      <div v-if="showGroupDropdownWizard" class="group-dropdown">
                        <div v-if="myGroups.length > 5" class="group-dropdown-search">
                          <input v-model="groupSearchQuery" type="text" class="form-input" placeholder="Gruppe suchen..." @click.stop />
                        </div>
                        <div class="group-dropdown-list">
                          <div 
                            v-for="grp in filteredGroups" 
                            :key="grp.id"
                            class="group-dropdown-item"
                            :class="{ 
                              selected: newActivity.groupId === grp.id,
                              disabled: !grp.selectable,
                              'is-folder': grp.hasChildren && !grp.selectable
                            }"
                            :style="{ paddingLeft: `${12 + grp.level * 20}px` }"
                            @click="grp.selectable ? (newActivity.groupId = grp.id, showGroupDropdownWizard = false, groupSearchQuery = '') : null"
                          >
                            <svg v-if="grp.hasChildren" class="group-item-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            <svg v-else class="group-item-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            <span class="group-dropdown-name">{{ grp.name }}</span>
                            <span v-if="grp.role === 'leader'" class="group-dropdown-role role-leader">Leiter</span>
                            <span v-else-if="grp.role === 'member'" class="group-dropdown-role role-member">Mitglied</span>
                            <span v-else-if="!grp.selectable" class="group-dropdown-role role-none">—</span>
                            <svg v-if="newActivity.groupId === grp.id" class="group-dropdown-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><polyline points="20 6 9 17 4 12"/></svg>
                          </div>
                          <div v-if="filteredGroups.length === 0" class="group-dropdown-empty">Keine Gruppen gefunden</div>
                        </div>
                      </div>
                    </Transition>
                  </div>
                  <button
                    v-if="selectedTemplate === 'camp' || selectedTemplate === 'event'"
                    type="button"
                    class="btn btn-sm btn-secondary mt-8"
                    @click="toggleDepartmentInvitePanel"
                  >
                    Andere Departments einladen
                  </button>
                  <div v-if="showDepartmentInvitePanel" class="department-invite-panel">
                    <div class="address-search-box">
                      <svg class="address-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                      <input
                        v-model="departmentInviteQuery"
                        type="text"
                        class="form-input address-search-input"
                        placeholder="Department suchen (ab 2 Buchstaben)..."
                      />
                    </div>
                    <div v-if="isDepartmentInviteLoading" class="address-loading"><span class="address-spinner"></span> Suche läuft...</div>
                    <div v-else-if="departmentInviteQuery.trim().length >= 2" class="department-invite-results">
                      <div v-for="dep in displayedDepartmentInviteResults" :key="dep.id" class="department-invite-item">
                        <div class="department-invite-info">
                          <div class="department-invite-name">{{ dep.name }}</div>
                          <div class="department-invite-org">{{ dep.organisation_name }}</div>
                        </div>
                        <button
                          type="button"
                          class="btn btn-sm btn-primary"
                          :disabled="invitedDepartments.some((entry) => entry.id === dep.id)"
                          @click="addInvitedDepartment(dep)"
                        >
                          {{ invitedDepartments.some((entry) => entry.id === dep.id) ? 'Eingeladen' : departmentInviteActionLabel }}
                        </button>
                      </div>
                      <div v-if="displayedDepartmentInviteResults.length === 0" class="address-no-results">Keine Departments gefunden</div>
                    </div>
                    <div v-if="invitedDepartments.length > 0" class="department-invited-list">
                      <div class="department-invited-title">Eingeladene Departments</div>
                      <div v-for="dep in invitedDepartments" :key="`invited-${dep.id}`" class="department-invited-item">
                        <span>{{ dep.name }} ({{ dep.organisation_name }})</span>
                        <button type="button" class="btn btn-sm btn-secondary" @click="removeInvitedDepartment(dep.id)">Entfernen</button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Kundendaten (nur Extern) -->
                <template v-if="selectedTemplate === 'external'">
                  <div class="form-group">
                    <label>Kunde / Mieter *</label>
                    <input v-model="newActivity.customerName" type="text" class="form-input" placeholder="Firma oder Person..." />
                    <button class="btn btn-sm btn-create-address mt-8" @click="openAddressModal('customer')">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                      Neue Kundenadresse
                    </button>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label>E-Mail</label>
                      <input v-model="newActivity.customerEmail" type="email" class="form-input" placeholder="kunde@beispiel.ch" />
                    </div>
                    <div class="form-group">
                      <label>Telefon</label>
                      <input v-model="newActivity.customerPhone" type="tel" class="form-input" placeholder="+41 79 ..." />
                    </div>
                  </div>
                </template>

                <!-- Standort / Adresse (Camp, Event, Extern) -->
                <div class="form-group">
                  <label>{{ selectedTemplate === 'camp' ? 'Veranstaltungsort' : selectedTemplate === 'event' ? 'Veranstaltungsort' : 'Lieferadresse' }}</label>
                  <!-- Ausgewählte Adresse -->
                  <div v-if="selectedAddress" class="selected-address-card">
                    <div class="address-card-content">
                      <div class="address-card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                      </div>
                      <div class="address-card-details">
                        <div v-if="selectedAddress.name || selectedAddress.company" class="address-card-name">{{ selectedAddress.name || selectedAddress.company }}</div>
                        <div class="address-card-line">{{ selectedAddress.street_line }}</div>
                        <div class="address-card-line">{{ selectedAddress.city_line }}</div>
                      </div>
                      <button class="address-card-remove" @click="clearSelectedAddress" title="Adresse entfernen">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M18 6L6 18M6 6l12 12"/></svg>
                      </button>
                    </div>
                  </div>
                  <!-- Adress-Suche -->
                  <div v-else class="address-picker">
                    <div class="address-search-box">
                      <svg class="address-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                      <input v-model="addressSearchQuery" type="text" class="form-input address-search-input" :placeholder="selectedTemplate === 'camp' || selectedTemplate === 'event' ? 'Veranstaltungsort suchen...' : 'Adresse suchen...'" @focus="loadDepartmentAddresses" />
                    </div>
                    <div v-if="isLoadingAddresses" class="address-loading"><span class="address-spinner"></span> Laden...</div>
                    <div v-else-if="departmentAddresses.length > 0" class="address-results-list">
                      <div v-for="addr in filteredAddresses" :key="addr.id" class="address-result-item" @click="selectAddress(addr)">
                        <div class="address-result-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
                        <div class="address-result-details">
                          <div class="address-result-name">{{ addr.name || addr.company || addr.street_line }} <span v-if="addr.type_label" class="address-type-tag">{{ addr.type_label }}</span></div>
                          <div class="address-result-line">{{ addr.full_address }}</div>
                        </div>
                      </div>
                      <div v-if="filteredAddresses.length === 0 && addressSearchQuery" class="address-no-results">Keine Adresse gefunden</div>
                    </div>
                    <button class="btn btn-sm btn-create-address" @click="openAddressModal()">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                      Neue Adresse
                    </button>
                  </div>
                </div>

                <button class="btn btn-sm btn-next mt-8" :disabled="!isWizardStep1Valid" @click="wizardAdvanceFromGrunddaten">
                  Weiter
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
              </div>

              <!-- ═══ Wizard Step 2: Zeitraum ═══ -->
              <div v-if="wizardStep === WIZARD_STEP_ZEITRAUM" class="wizard-step-content">
                <div class="time-section-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  {{ selectedTemplate === 'camp' ? 'Lager findet statt' : selectedTemplate === 'event' ? 'Event findet statt' : 'Zeitraum der Nutzung' }}
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Start *</label>
                    <input v-model="newActivity.usageStart" type="datetime-local" step="900" class="form-input" :min="todayTimeStr" />
                  </div>
                  <div class="form-group">
                    <label>Ende *</label>
                    <input v-model="newActivity.usageEnd" type="datetime-local" step="900" class="form-input" :min="newActivity.usageStart || todayTimeStr" />
                  </div>
                </div>

                <div class="time-section-label mt-16">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="1" y="3" width="15" height="13" rx="2" ry="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                  Material Abholen / Zurückbringen
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Material abholen *</label>
                    <input v-model="newActivity.planningStart" type="datetime-local" step="900" class="form-input" :min="todayTimeStr" />
                  </div>
                  <div class="form-group">
                    <label>Material zurückbringen *</label>
                    <input v-model="newActivity.planningEnd" type="datetime-local" step="900" class="form-input" :min="newActivity.planningStart || todayTimeStr" />
                  </div>
                </div>
                <div v-if="eventDuration" class="duration-info">{{ eventDuration }}</div>
                <p class="step-hint" style="margin-top: 8px; font-size: 12px; color: #9ca3af;">Material-Zeiten werden automatisch aus den Department-Einstellungen berechnet und können manuell angepasst werden.</p>

                <div class="wizard-step-actions mt-8">
                  <button class="btn btn-sm btn-secondary" @click="wizardStep = WIZARD_STEP_GRUNDDATEN">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="15 18 9 12 15 6"/></svg>
                    Zurück
                  </button>
                  <button class="btn btn-sm btn-next" :disabled="!isStep3Complete" @click="wizardAdvanceFromZeitraum">
                    Weiter
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="9 18 15 12 9 6"/></svg>
                  </button>
                </div>
              </div>

              <!-- ═══ Wizard Step 3: Material (Pflicht) ═══ -->
              <div v-if="wizardStep === WIZARD_STEP_MATERIAL" class="wizard-step-content">
                <p class="step-hint">Mindestens 1 Material hinzufügen.</p>

                <!-- Vorschläge (Quick-Add-Chips) -->
                <div v-if="materialSuggestions.length > 0" class="suggestion-chips">
                  <div class="suggestion-label">{{ suggestionsLabel }}:</div>
                  <div class="suggestion-list">
                    <button 
                      v-for="sug in materialSuggestions" 
                      :key="sug.material_item_id"
                      class="suggestion-chip"
                      :class="{ active: isMaterialSelected(sug.material_item_id) }"
                      @click="addSuggestion(sug)"
                    >
                      <span class="suggestion-chip-plus">{{ isMaterialSelected(sug.material_item_id) ? '✓' : '+' }}</span>
                      {{ sug.name }}
                      <span class="suggestion-chip-qty">&times;{{ sug.avg_quantity }}</span>
                    </button>
                  </div>
                </div>
                <div v-else-if="isLoadingSuggestions" class="suggestion-chips">
                  <div class="suggestion-label suggestion-loading">Vorschläge werden geladen...</div>
                </div>
                <div v-if="canUseJsMaterialSource" class="mat-source-switch" role="tablist" aria-label="Materialquelle">
                  <button type="button" class="mat-source-btn" :class="{ active: materialSource === 'internal' }" @click="setMaterialSource('internal')">Eigenes</button>
                  <button type="button" class="mat-source-btn" :class="{ active: materialSource === 'js' }" @click="setMaterialSource('js')">J&amp;S</button>
                </div>
                <p v-else class="mat-source-hint">J&amp;S-Material ist nur bei Event oder Camp verfügbar.</p>
                <div v-if="canUseJsMaterialSource && materialSource === 'js'" class="js-order-card">
                  <div class="js-order-header">
                    <strong>J&amp;S-Bestellformular</strong>
                    <button type="button" class="btn btn-xs btn-secondary" @click="showJsOrderForm = !showJsOrderForm">
                      {{ showJsOrderForm ? 'Ausblenden' : 'Ausfüllen' }}
                    </button>
                  </div>
                  <div v-if="showJsOrderForm" class="js-order-body">
                    <div class="form-row">
                      <div class="form-group">
                        <label>Kursart</label>
                        <input v-model="jsOrderForm.courseType" type="text" class="form-input" placeholder="z.B. Lager / Trekking" />
                      </div>
                      <div class="form-group">
                        <label>Anzahl Teilnehmende</label>
                        <input v-model.number="jsOrderForm.participants" type="number" min="1" class="form-input" placeholder="z.B. 42" />
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group">
                        <label>Lieferdatum</label>
                        <input v-model="jsOrderForm.deliveryDate" type="date" class="form-input" />
                      </div>
                      <div class="form-group">
                        <label>Rücklieferung</label>
                        <input v-model="jsOrderForm.returnDate" type="date" class="form-input" />
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group">
                        <label>Logistik</label>
                        <select v-model="jsOrderForm.logistics" class="form-select">
                          <option value="lieferung">Lieferung ins Lager</option>
                          <option value="abholung">Abholung im J&amp;S Lager</option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label>Kontaktperson</label>
                        <input v-model="jsOrderForm.contactPerson" type="text" class="form-input" placeholder="Name / Tel / Mail" />
                      </div>
                    </div>
                    <div class="form-group">
                      <label>Bemerkungen</label>
                      <textarea v-model="jsOrderForm.notes" class="form-input form-textarea" rows="2" placeholder="Optionale Hinweise zur Bestellung..."></textarea>
                    </div>
                    <div class="js-order-actions">
                      <button type="button" class="btn btn-sm btn-secondary" @click="resetJsOrderForm">Zurücksetzen</button>
                      <button type="button" class="btn btn-sm btn-next" @click="applyJsOrderToActivityNotes">In Notizen übernehmen</button>
                    </div>
                  </div>
                </div>

                <!-- Material-Suchfeld (wiederverwendet) -->
                <div class="mat-autocomplete">
                  <MaterialLookupInput
                    ref="materialSearchInput"
                    v-model="materialSearch"
                    :fetcher="activityMaterialLookupFetcher"
                    :min-chars="2"
                    :max-suggestions="20"
                    placeholder="Material suchen (z.B. Zelt, Kocher, Blache...)"
                    :input-class="'mat-search-input'"
                    :loading-text="'Suche...'"
                    :empty-text="`Keine Treffer für «${materialSearch}»`"
                    @select="handleActivityLookupSelect"
                  >
                    <template #results="{ results, isLoading, activeIndex, setActiveIndex }">
                      <div v-if="isLoading" class="mat-dropdown-loading">Suche...</div>
                      <div v-else-if="results.length === 0" class="mat-dropdown-empty">Keine Treffer für &laquo;{{ materialSearch }}&raquo;</div>
                      <div v-else class="mat-dropdown-list">
                        <div
                          v-for="(mat, index) in results"
                          :key="mat.materialItemId"
                          class="mat-dropdown-item"
                          :class="{ active: activeIndex === index, 'already-added': isMaterialSelected(mat.materialItemId), 'unavailable': mat.availableForPeriod <= 0 }"
                          @mouseenter="setActiveIndex(index)"
                        >
                          <div class="mat-dropdown-info">
                            <span class="mat-dropdown-name">
                              <span v-if="mat.isConsumable" class="mat-type-icon consumable">&#x1F525;</span>
                              <span v-else class="mat-type-icon rental">&#x1F4E6;</span>
                              {{ mat.name }}
                              <span v-if="mat.isJsMaterial" class="mat-source-badge">J&amp;S</span>
                              <span v-if="mat.packSize && mat.packUnit" class="mat-pack-badge">{{ mat.packSize }}&thinsp;Stk./{{ mat.packUnit }}</span>
                            </span>
                            <span class="mat-dropdown-meta">
                              <span class="mat-dropdown-stock">
                                <span :class="mat.availableForPeriod > 0 ? 'text-green' : 'text-red'">{{ mat.availableForPeriod }}</span>
                                <span class="text-muted"> / {{ mat.totalStock }}</span>
                              </span>
                              <span v-if="mat.isConsumable && mat.salePrice" class="mat-dropdown-price consumable">CHF {{ Number(mat.salePrice).toFixed(2) }}/Stk</span>
                              <span v-else-if="selectedTemplate === 'external' && !mat.isConsumable && mat.rentalPriceDay" class="mat-dropdown-price rental">CHF {{ Number(mat.rentalPriceDay).toFixed(2) }}/Tag</span>
                            </span>
                          </div>
                          <div class="mat-dropdown-actions">
                            <template v-if="isMaterialSelected(mat.materialItemId)"><span class="mat-already-badge">&#x2713; hinzugefügt</span></template>
                            <template v-else-if="mat.availableForPeriod > 0">
                              <template v-if="mat.packSize && mat.packSize > 1">
                                <button v-if="mat.availableForPeriod >= mat.packSize" class="mat-quick-btn mat-set-btn" @click="addMaterialWithQty(mat, mat.packSize)">1 {{ mat.packUnit || 'Set' }}</button>
                                <span class="mat-btn-divider">|</span>
                              </template>
                              <button class="mat-quick-btn" @click="addMaterialWithQty(mat, 1)">+1</button>
                              <button v-if="mat.availableForPeriod >= 5" class="mat-quick-btn" @click="addMaterialWithQty(mat, 5)">+5</button>
                              <button v-if="mat.availableForPeriod >= 10" class="mat-quick-btn" @click="addMaterialWithQty(mat, 10)">+10</button>
                            </template>
                            <template v-else><span class="mat-unavailable-badge">nicht verfügbar</span></template>
                          </div>
                        </div>
                      </div>
                    </template>
                  </MaterialLookupInput>
                </div>

                <!-- Ausgewählte Materialien -->
                <div v-if="newActivity.selectedItems.length > 0" class="mat-selected">
                  <div class="mat-selected-header">
                    <strong>{{ newActivity.selectedItems.length }} Material{{ newActivity.selectedItems.length !== 1 ? 'ien' : '' }}</strong>
                    <span class="text-muted">({{ newActivity.selectedItems.reduce((s, i) => s + i.quantity, 0) }} Stk.)</span>
                  </div>
                  <div class="mat-selected-list">
                    <div v-for="item in newActivity.selectedItems" :key="item.materialItemId" class="mat-selected-row">
                      <span class="mat-selected-name">
                        <span v-if="item.isConsumable" class="mat-type-icon consumable">&#x1F525;</span>
                        <span v-else class="mat-type-icon rental">&#x1F4E6;</span>
                        {{ item.materialName }}
                        <span v-if="item.isJsMaterial" class="mat-source-badge">J&amp;S</span>
                      </span>
                      <div class="mat-selected-controls">
                        <button class="btn-qty" @click="changeMaterialQty(item.materialItemId, -1)">−</button>
                        <span class="material-qty" :class="{ 'qty-exceeded': isJsQuantityExceeded(item) }">{{ item.quantity }}</span>
                        <button class="btn-qty" @click="changeMaterialQty(item.materialItemId, 1)" :disabled="item.quantity >= item.availableQuantity">+</button>
                        <span class="mat-avail-hint">max {{ item.availableQuantity }}</span>
                        <span v-if="isJsQuantityExceeded(item)" class="js-qty-warning">J&amp;S max {{ getJsAllowedQty(item) }}</span>
                        <span v-else-if="needsJsParticipants(item)" class="js-qty-hint">Teilnehmende erfassen</span>
                        <span v-if="item.lineTotal != null && item.priceType !== 'free'" class="mat-item-price">CHF {{ item.lineTotal.toFixed(2) }}</span>
                        <button class="btn-remove" @click="removeMaterial(item.materialItemId)">&#x2715;</button>
                      </div>
                    </div>
                  </div>
                  <!-- Preis bei Extern -->
                  <div v-if="isExternalActivity && estimatedTotalPrice > 0" class="calc-sum" style="margin-top: 8px;">
                    <span class="calc-sum-label">Geschätzter Preis:</span>
                    <span class="calc-sum-value">CHF {{ estimatedTotalPrice.toFixed(2) }}</span>
                  </div>
                </div>

                <div class="wizard-step-actions mt-8">
                  <button class="btn btn-sm btn-secondary" @click="wizardStep = WIZARD_STEP_ZEITRAUM">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="15 18 9 12 15 6"/></svg>
                    Zurück
                  </button>
                  <button class="btn btn-sm btn-next" :disabled="newActivity.selectedItems.length === 0" @click="wizardAdvanceFromMaterial">
                    Weiter
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="9 18 15 12 9 6"/></svg>
                  </button>
                  <span v-if="newActivity.selectedItems.length === 0" class="mat-empty-hint" style="margin-top: 4px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    Mindestens 1 Material hinzufügen um fortzufahren
                  </span>
                </div>
              </div>

              <!-- ═══ Wizard Step 4: Zusammenfassung ═══ -->
              <div v-if="wizardStep === WIZARD_STEP_SUMMARY" class="wizard-step-content">
                <div class="summary-card">
                  <div class="summary-row">
                    <span class="summary-label">Typ:</span>
                    <span class="summary-value"><span class="preview-badge" :class="selectedTemplate">{{ getTypeLabel(selectedTemplate) }}</span></span>
                  </div>
                  <div class="summary-row">
                    <span class="summary-label">Name:</span>
                    <span class="summary-value summary-value-bold">{{ newActivity.name }}</span>
                  </div>
                  <div v-if="newActivity.groupId" class="summary-row">
                    <span class="summary-label">Gruppe:</span>
                    <span class="summary-value">{{ getSelectedGroupName() }}</span>
                  </div>
                  <div v-if="selectedAddress" class="summary-row">
                    <span class="summary-label">{{ selectedTemplate === 'camp' || selectedTemplate === 'event' ? 'Veranstaltungsort:' : 'Adresse:' }}</span>
                    <span class="summary-value">{{ selectedAddress.street_line }}, {{ selectedAddress.city_line }}</span>
                  </div>
                  <div v-if="newActivity.customerName" class="summary-row">
                    <span class="summary-label">Kunde:</span>
                    <span class="summary-value">{{ newActivity.customerName }}</span>
                  </div>
                  <div v-if="newActivity.usageStart" class="summary-row">
                    <span class="summary-label">Zeitraum:</span>
                    <span class="summary-value">{{ formatDateTime(newActivity.usageStart) }} – {{ formatDateTime(newActivity.usageEnd) }}</span>
                  </div>
                  <div v-if="newActivity.planningStart" class="summary-row">
                    <span class="summary-label">Material:</span>
                    <span class="summary-value">{{ formatDateTime(newActivity.planningStart) }} – {{ formatDateTime(newActivity.planningEnd) }}</span>
                  </div>
                  <div v-if="newActivity.selectedItems.length > 0" class="summary-row">
                    <span class="summary-label">Artikel:</span>
                    <span class="summary-value">{{ newActivity.selectedItems.length }} Material ({{ newActivity.selectedItems.reduce((s, i) => s + i.quantity, 0) }} Stk.)</span>
                  </div>
                  <div v-if="estimatedTotalPrice > 0" class="summary-row">
                    <span class="summary-label">Preis:</span>
                    <span class="summary-value" style="color: #059669; font-weight: 600;">CHF {{ estimatedTotalPrice.toFixed(2) }}</span>
                  </div>
                </div>

                <!-- Notizen -->
                <div class="form-group" style="margin-top: 16px;">
                  <label>Notizen (optional)</label>
                  <textarea v-model="newActivity.notes" class="form-input form-textarea" placeholder="Optionale Anmerkungen..." rows="3"></textarea>
                </div>

                <div class="wizard-draft-hint">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  Die Aktivität wird als <strong>Entwurf</strong> gespeichert. Du kannst sie danach noch prüfen und über die Detailansicht einreichen.
                </div>

                <div class="wizard-step-actions mt-8">
                  <button class="btn btn-sm btn-secondary" @click="wizardStep = WIZARD_STEP_MATERIAL">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="15 18 9 12 15 6"/></svg>
                    Zurück
                  </button>
                </div>
              </div>

            </div>

              <!-- AddressModal (Teleport to body für z-index) -->
              <Teleport to="body">
                <AddressModal
                  v-if="showAddressModal"
                  :department-id="departmentId"
                  :default-type="addressModalDefaultType"
                  @close="showAddressModal = false"
                  @saved="onAddressCreated"
                />
              </Teleport>

            <!-- Right: Live Preview (Desktop & Tablet) -->
            <div v-if="selectedTemplate" class="wizard-sidebar">
              <h3>Vorschau</h3>
              <div class="activity-preview">
                <div class="preview-icon" :class="selectedTemplate || 'empty'">
                  <div v-if="selectedTemplate" class="preview-type-icon" v-html="getSelectedTemplateIcon()"></div>
                </div>
                <div class="preview-info">
                  <h4>{{ newActivity.name || 'Neue Aktivität' }}</h4>
                  <span v-if="selectedTemplate" class="preview-badge" :class="selectedTemplate">{{ getTypeLabel(selectedTemplate) }}</span>
                </div>
              </div>
              <div v-if="selectedTemplate" class="preview-details">
                <div v-if="newActivity.usageStart" class="info-row">
                  <span class="info-label">Zeitraum:</span>
                  <span class="info-value">{{ formatDateTime(newActivity.usageStart) }} – {{ newActivity.usageEnd ? formatDateTime(newActivity.usageEnd) : '?' }}</span>
                </div>
                <div v-if="newActivity.planningStart" class="info-row">
                  <span class="info-label">Material:</span>
                  <span class="info-value">{{ formatDateTime(newActivity.planningStart) }} – {{ newActivity.planningEnd ? formatDateTime(newActivity.planningEnd) : '?' }}</span>
                </div>
                <div v-if="newActivity.groupId" class="info-row">
                  <span class="info-label">Gruppe:</span>
                  <span class="info-value">{{ getSelectedGroupName() }}</span>
                </div>
                <div v-if="selectedAddress" class="info-row">
                  <span class="info-label">Adresse:</span>
                  <span class="info-value">{{ selectedAddress.street_line }}, {{ selectedAddress.city_line }}</span>
                </div>
                <div v-if="newActivity.customerName" class="info-row">
                  <span class="info-label">Kunde:</span>
                  <span class="info-value">{{ newActivity.customerName }}</span>
                </div>
                <div v-if="newActivity.selectedItems.length > 0" class="info-row">
                  <span class="info-label">Artikel:</span>
                  <span class="info-value">{{ newActivity.selectedItems.length }} Pos. ({{ newActivity.selectedItems.reduce((s, i) => s + i.quantity, 0) }} Stk.)</span>
                </div>
                <div v-if="estimatedTotalPrice > 0" class="info-row">
                  <span class="info-label">Preis:</span>
                  <span class="info-value" style="color: #059669; font-weight: 600;">CHF {{ estimatedTotalPrice.toFixed(2) }}</span>
                </div>
                <div class="info-row">
                  <span class="info-label">Status:</span>
                  <span class="info-value">
                    <span v-if="wizardMode === 'quick'" class="status-label submitted">Wird eingereicht</span>
                    <span v-else class="status-label draft">{{ draftId ? 'Entwurf (gespeichert)' : 'Entwurf' }}</span>
                  </span>
                </div>
              </div>
              <div v-if="missingSteps.length > 0" class="preview-missing">
                <div class="missing-header">Noch offen:</div>
                <ul><li v-for="step in missingSteps" :key="step">{{ step }}</li></ul>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="wizard-footer">
            <div class="footer-left">
              <span v-if="draftId" class="draft-badge">
                <span class="draft-dot"></span>
                Entwurf
                <span v-if="lastSavedAt" class="draft-time">&middot; {{ lastSavedAt }}</span>
              </span>
              <span v-if="isSaving" class="saving-indicator">&#x1F4BE; Speichern...</span>
            </div>
            <div class="footer-actions">
              <div v-if="missingSteps.length > 0 && (wizardMode === 'quick' || wizardStep === WIZARD_STEP_SUMMARY)" class="missing-hint">
                <span class="missing-icon">&#x26A0;&#xFE0F;</span>
                <span class="missing-text">{{ missingSteps[0] }}</span>
              </div>
              <span class="footer-kbd-hint" v-if="canSubmit">
                <kbd>Enter</kbd> {{ wizardMode === 'quick' ? 'zum Einreichen' : 'zum Erstellen' }}
              </span>
              <button class="btn btn-secondary" @click="handleCloseWizard">Abbrechen</button>
              <button 
                v-if="wizardMode === 'quick' || wizardStep === WIZARD_STEP_SUMMARY"
                class="btn btn-primary" 
                :class="{ 'btn-loading': isCreating }"
                :disabled="!canSubmit || isCreating"
                @click="createActivity"
              >
                <span v-if="isCreating" class="btn-spinner"></span>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg>
                <template v-if="isCreating">{{ wizardMode === 'quick' ? 'Wird eingereicht...' : 'Wird erstellt...' }}</template>
                <template v-else>{{ wizardMode === 'quick' ? 'Aktivität einreichen' : 'Aktivität erstellen' }}</template>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <div
      v-if="showCompletionBlockedModal"
      style="position: fixed; inset: 0; background: rgba(17,24,39,0.45); display: flex; align-items: center; justify-content: center; z-index: 2600;"
    >
      <div style="width: min(720px, 94vw); background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 18px 40px rgba(0,0,0,0.22);">
        <div style="padding: 14px 16px; border-bottom: 1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; gap:10px;">
          <div style="font-size: 18px; font-weight: 700; color: #111827;">Aktivität kann noch nicht abgeschlossen werden</div>
          <button class="btn btn-sm btn-secondary" @click="closeCompletionBlockedModal">Schließen</button>
        </div>
        <div style="padding: 14px 16px; font-size: 14px; color: #374151; display: grid; gap: 10px;">
          <div>Es gibt noch offene Fälle, die zuerst in der Werkstatt geklärt werden müssen.</div>
          <div v-if="completionBlockers">
            <strong>Offene Werkstatt-Tickets:</strong>
            {{ completionBlockers.open_workshop_tickets_count || 0 }}
            ·
            <strong>Offene Meldungen:</strong>
            {{ completionBlockers.open_issue_reports_count || 0 }}
          </div>
          <div
            v-if="completionBlockers?.open_workshop_tickets?.length"
            style="display:grid; gap:6px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:10px;"
          >
            <div style="font-weight:600; color:#111827;">Offene Werkstatt-Tickets (direkt öffnen):</div>
            <div style="display:flex; flex-wrap:wrap; gap:6px;">
              <button
                v-for="ticket in completionBlockers.open_workshop_tickets"
                :key="ticket.id"
                class="btn btn-sm btn-secondary"
                @click="openWorkshopFromCompletionBlocker(ticket.id)"
              >
                {{ ticket.title || ticket.id }}
              </button>
            </div>
          </div>
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button class="btn btn-sm btn-warning" @click="switchDetailTab('issues')">Reparaturen / Verluste öffnen</button>
            <button class="btn btn-sm btn-primary" @click="openWorkshopFromCompletionBlocker">Werkstatt öffnen</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
defineOptions({ name: 'ActivitiesView' })
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '@/api/apiClient'
import { getActivityDefaults, type ActivityDefaults } from '@/api/departmentSettings'
import { getAddresses, type Address } from '@/api/addresses'
import { searchJoinableDepartments, type DepartmentSearchResult } from '@/api/joinRequests'
import { getMaterialSuggestions, type MaterialSuggestion } from '@/api/materialSuggestions'
import { getWorkshopTickets, type WorkshopTicket } from '@/api/workshop'
import {
  createActivityPackContainer,
  createActivityPackContainerItem,
  deleteActivityPackContainerItem,
  getActivityPackContainerItems,
  getActivityPackContainers,
  type ActivityPackContainer,
  type ActivityPackContainerItem,
} from '@/api/activityContainers'
import AddressModal from '@/components/AddressModal.vue'
import GlobalSearchInput from '@/components/common/GlobalSearchInput.vue'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import { useAuthStore } from '@/stores/auth'
import { usePageHeadStore } from '@/stores/pageHead'
import { useDetailTabsStore } from '@/stores/detailTabs'
import { syncDocumentHead } from '@/composables/usePageHead'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { usePrompt } from '@/composables/usePrompt'
import { createAvailabilityMaterialLookupFetcher } from '@/composables/useMaterialLookup'
import QRCode from 'qrcode'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const pageHeadStore = usePageHeadStore()
const toast = useToast()
const confirm = useConfirm()
const prompt = usePrompt()
const departmentId = computed(() => route.params.departmentId as string)
const detailTabsStore = useDetailTabsStore()
const showCompletionBlockedModal = ref(false)
const completionBlockers = ref<any | null>(null)

// Rollen-Check: Wer darf Material bearbeiten?
// Department-Rollen: mw, dc. Globale Admins (sa/org/sub) aus profile.roles.
const DEPT_MW_ROLES = ['mw', 'dc']
const LEADER_ROLES = ['l1', 'l2', 'l3']
const hasGlobalAdminRole = computed(() =>
  authStore.userRoles.includes('ROLE_SUPERADMIN') ||
  authStore.userRoles.includes('ROLE_ORGANISATIONSCHEF') ||
  authStore.userRoles.includes('ROLE_SUBORGCHEF')
)
const canEditMaterial = computed(() => {
  const role = (authStore.currentDepartmentRole || '').toLowerCase()
  if (DEPT_MW_ROLES.includes(role) || hasGlobalAdminRole.value) return true
  // Leader dürfen Material im Entwurf bearbeiten
  if (LEADER_ROLES.includes(role) && selectedActivity.value?.status === 'draft') return true
  return false
})

// MW+ darf bis "issued" (Am Event) den Bestand über Modal anpassen
const MW_EDITABLE_STATUSES = ['submitted', 'approved', 'packing', 'packed', 'issued']
const canMwAdjustMaterial = computed(() => {
  const role = (authStore.currentDepartmentRole || '').toLowerCase()
  const status = selectedActivity.value?.status || ''
  const hasMwPrivilege = DEPT_MW_ROLES.includes(role) || hasGlobalAdminRole.value
  return hasMwPrivilege && MW_EDITABLE_STATUSES.includes(status)
})

// MW+ darf Material hinzufügen (Suchfeld) bis "issued"
const canMwAddMaterial = computed(() => {
  const role = (authStore.currentDepartmentRole || '').toLowerCase()
  const status = selectedActivity.value?.status || ''
  const hasMwPrivilege = DEPT_MW_ROLES.includes(role) || hasGlobalAdminRole.value
  if (hasMwPrivilege && status === 'draft') return true
  return hasMwPrivilege && MW_EDITABLE_STATUSES.includes(status)
})
const canUseDetailJsMaterialSource = computed(() => {
  const type = selectedActivity.value?.type || activityDetail.value?.type || ''
  return type === 'camp' || type === 'event'
})

// Doppelklick auf Material-Zeile → Modal öffnen (draft bis issued)
function onMaterialRowDblClick(item: any) {
  const status = selectedActivity.value?.status || ''
  if (status === 'draft' && canEditMaterial.value) {
    openAdjustModal(item)
  } else if (canMwAdjustMaterial.value) {
    openAdjustModal(item)
  }
}

// Bestand-Ändern Modal State
const showAdjustModal = ref(false)
const adjustItem = ref<any>(null)
const adjustNewQty = ref(0)
const adjustAvailable = ref(0)
const adjustAvailableLoading = ref(false)

// Computed: Set-Infos für das Modal
const adjustPackSize = computed(() => adjustItem.value?.packSize || null)
const adjustPackUnit = computed(() => adjustItem.value?.packUnit || null)
const adjustMaxAllowed = computed(() => (adjustItem.value?.quantity || 0) + adjustAvailable.value)
const adjustSetsAvailable = computed(() => {
  if (!adjustPackSize.value || adjustPackSize.value <= 1) return 0
  return Math.floor(adjustMaxAllowed.value / adjustPackSize.value)
})

async function openAdjustModal(item: any) {
  adjustItem.value = item
  adjustNewQty.value = item.quantity
  adjustAvailable.value = 0
  adjustAvailableLoading.value = true
  showAdjustModal.value = true

  // Verfügbarkeit für dieses Material im Zeitraum laden
  try {
    const startDate = activityDetail.value?.planning_start || activityDetail.value?.usage_start
    const endDate = activityDetail.value?.planning_end || activityDetail.value?.usage_end
    const params: any = {
      departmentId: departmentId.value,
      search: item.materialName,
      source: canUseDetailJsMaterialSource.value ? 'all' : 'internal',
      includeGlobalJs: canUseDetailJsMaterialSource.value,
      limit: 1,
      excludeActivityId: selectedActivity.value?.id,
    }
    if (startDate && endDate) {
      params.startDate = startDate
      params.endDate = endDate
    }
    const response = await apiClient.get('/api/materials/available-for-period', { params })
    const found = (response.data || []).find((m: any) => m.materialItemId === item.materialItemId)
    adjustAvailable.value = found ? found.availableForPeriod : 0
  } catch (err) {
    console.error('Fehler beim Laden der Verfügbarkeit:', err)
    adjustAvailable.value = 999 // Bei Fehler kein Limit erzwingen
  } finally {
    adjustAvailableLoading.value = false
  }
}

function closeAdjustModal() {
  showAdjustModal.value = false
  adjustItem.value = null
}

async function confirmAdjust() {
  if (!adjustItem.value || !selectedActivity.value) return

  const newQty = adjustNewQty.value
  if (newQty > adjustMaxAllowed.value && !adjustAvailableLoading.value) {
    toast.warning(`Nicht genug verfügbar. Maximal ${adjustMaxAllowed.value} Stück möglich.`)
    return
  }

  try {
    if (newQty === 0) {
      // Material entfernen
      const items = detailItems.value
        .filter(i => i.id !== adjustItem.value.id)
        .map(i => ({ material_item_id: i.materialItemId, quantity: i.quantity, priority: 'normal' }))
      await apiClient.put(`/api/activities/${selectedActivity.value.id}/items`, { items })
    } else {
      // Menge ändern
      const items = detailItems.value.map(i => ({
        material_item_id: i.materialItemId,
        quantity: i.id === adjustItem.value.id ? newQty : i.quantity,
        priority: 'normal',
      }))
      await apiClient.put(`/api/activities/${selectedActivity.value.id}/items`, { items })
    }
    await reloadDetailItems()
    closeAdjustModal()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

// State
const activeTab = ref('upcoming')
const activeTypeFilter = ref('')
const searchQuery = ref('')
const showNewDialog = ref(false)
const selectedTemplate = ref('')
// Wizard mode: 'quick' = one-screen (activity), 'wizard' = multi-step (camp/event/external)
const wizardMode = computed(() => {
  if (!selectedTemplate.value || selectedTemplate.value === 'activity') return 'quick'
  return 'wizard'
})
const sortField = ref('date')
const sortDir = ref<'asc' | 'desc'>('asc')

// Detail-Ansicht State
const showDetail = ref(false)
const selectedActivity = ref<Activity | null>(null)
const activityDetail = ref<any>(null)
const activeDetailTab = ref('overview')
const detailItems = ref<any[]>([])
const isLoadingDetailItems = ref(false)
const activityHistory = ref<any[]>([])
const isLoadingHistory = ref(false)

watch(
  () => {
    if (!showDetail.value || !selectedActivity.value?.name) return ''
    return String(selectedActivity.value.name).trim()
  },
  (name) => {
    if (!name) {
      pageHeadStore.clearDynamic()
      syncDocumentHead(route)
      return
    }
    pageHeadStore.setDynamic(`${name} · eMatChef`, `${name} – Aktivität in eMatChef.`)
  },
  { immediate: true }
)

// Draft-Edit State
const isEditingDraft = ref(false)
const draftEditData = ref({
  name: '',
  usage_start: '',
  usage_end: '',
  planning_start: '',
  planning_end: '',
  notes: '',
  group_id: null as string | null,
})
const showDateChangeWarning = ref(false)

const isDraftEditable = computed(() => {
  return selectedActivity.value?.status === 'draft'
})

function startEditDraft() {
  if (!activityDetail.value || !isDraftEditable.value) return
  draftEditData.value = {
    name: activityDetail.value.name || '',
    usage_start: activityDetail.value.usage_start ? toLocalDatetimeInput(activityDetail.value.usage_start) : '',
    usage_end: activityDetail.value.usage_end ? toLocalDatetimeInput(activityDetail.value.usage_end) : '',
    planning_start: activityDetail.value.planning_start ? toLocalDatetimeInput(activityDetail.value.planning_start) : '',
    planning_end: activityDetail.value.planning_end ? toLocalDatetimeInput(activityDetail.value.planning_end) : '',
    notes: activityDetail.value.notes || '',
    group_id: activityDetail.value.group_id || null,
  }
  isEditingDraft.value = true
  showDateChangeWarning.value = false
  // Gruppen laden für Dropdown
  if (myGroups.value.length === 0) {
    loadMyGroups()
  }
}

function cancelEditDraft() {
  isEditingDraft.value = false
  showDateChangeWarning.value = false
}

function onDraftDateChange() {
  // Warnung anzeigen wenn Material vorhanden und Datum geändert wird
  if (detailItems.value.length > 0) {
    const origStart = activityDetail.value?.usage_start ? toLocalDatetimeInput(activityDetail.value.usage_start) : ''
    const origEnd = activityDetail.value?.usage_end ? toLocalDatetimeInput(activityDetail.value.usage_end) : ''
    if (draftEditData.value.usage_start !== origStart || draftEditData.value.usage_end !== origEnd) {
      showDateChangeWarning.value = true
    } else {
      showDateChangeWarning.value = false
    }
  }
}

async function removeAllMaterialsAndSave() {
  if (!selectedActivity.value) return
  const ok = await confirm.confirm({
    title: 'Alle Materialien entfernen?',
    message: 'Um das Datum zu ändern, müssen alle Materialien entfernt werden.',
    confirmText: 'Entfernen',
    cancelText: 'Abbrechen',
    variant: 'warning',
  })
  if (!ok) return
  try {
    await apiClient.put(`/api/activities/${selectedActivity.value.id}/items`, { items: [] })
    await reloadDetailItems()
    showDateChangeWarning.value = false
    // Jetzt speichern
    await saveDraftEdit()
  } catch (err: any) {
    toast.error('Fehler beim Entfernen der Materialien: ' + (err.response?.data?.error || err.message))
  }
}

async function saveDraftEdit() {
  if (!selectedActivity.value || !activityDetail.value) return

  // Wenn Datum geändert und Material vorhanden -> warnen
  if (showDateChangeWarning.value && detailItems.value.length > 0) {
    return // User muss erst Material löschen
  }

  try {
    const payload: Record<string, any> = {
      name: draftEditData.value.name,
      usage_start: draftEditData.value.usage_start ? new Date(draftEditData.value.usage_start).toISOString() : null,
      usage_end: draftEditData.value.usage_end ? new Date(draftEditData.value.usage_end).toISOString() : null,
      planning_start: draftEditData.value.planning_start ? new Date(draftEditData.value.planning_start).toISOString() : null,
      planning_end: draftEditData.value.planning_end ? new Date(draftEditData.value.planning_end).toISOString() : null,
      notes: draftEditData.value.notes || null,
      group_id: draftEditData.value.group_id || null,
    }

    const response = await apiClient.patch(`/api/activities/${selectedActivity.value.id}`, payload)
    activityDetail.value = response.data

    // Auch die Liste aktualisieren
    if (selectedActivity.value) {
      selectedActivity.value.name = response.data.name
      if (response.data.group_name) {
        selectedActivity.value.groupName = response.data.group_name
      }
    }

    isEditingDraft.value = false
    showDateChangeWarning.value = false
    await loadActivities()
  } catch (err: any) {
    toast.error('Fehler beim Speichern: ' + (err.response?.data?.error || err.message))
  }
}

function toLocalDatetimeInput(isoStr: string): string {
  if (!isoStr) return ''
  const d = new Date(isoStr)
  const tz = authStore.departmentTimezone || 'Europe/Zurich'
  // Formatiere in der konfigurierten Timezone für datetime-local Input
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: tz,
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', hour12: false,
  }).formatToParts(d)
  const get = (type: string) => parts.find(p => p.type === type)?.value || '00'
  return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`
}

// Detail Material-Suche State (für Materialchef+)
const detailMatSearch = ref('')
const detailMatSearchResults = ref<any[]>([])
const isDetailMatLoading = ref(false)
const showDetailMatDropdown = ref(false)
const detailMatActiveIndex = ref(-1)
const detailMaterialSource = ref<'internal' | 'js'>('internal')
let detailMatSearchTimer: ReturnType<typeof setTimeout> | null = null

// Workflow-State
const availableTransitions = ref<StatusTransition[]>([])
const packItems = ref<PackItem[]>([])
const isLoadingPackItems = ref(false)
const packContainers = ref<ActivityPackContainer[]>([])
const selectedPackContainerId = ref('')
const selectedPackContainerItems = ref<ActivityPackContainerItem[]>([])
const newPackContainerLabel = ref('')
const newPackContainerItemMaterialId = ref('')
const newPackContainerItemQty = ref(1)
const issueReports = ref<IssueReport[]>([])
const isLoadingIssues = ref(false)
const returnItems = ref<ReturnItem[]>([])
const isLoadingReturns = ref(false)
const workshopCostTickets = ref<WorkshopTicket[]>([])
const isLoadingWorkshopCosts = ref(false)

// QR-Code für Auftrag-Status (Link zur Aktivität)
const activityQrDataUrl = ref<string>('')
const activityQrUrl = computed(() => {
  if (!selectedActivity.value?.id || !departmentId.value) return ''
  return `${window.location.origin}/${departmentId.value}/activities/${selectedActivity.value.id}/packlist`
})
watch([selectedActivity, departmentId], async () => {
  const url = activityQrUrl.value
  if (!url) {
    activityQrDataUrl.value = ''
    return
  }
  try {
    activityQrDataUrl.value = await QRCode.toDataURL(url, { width: 160, margin: 1 })
  } catch {
    activityQrDataUrl.value = ''
  }
}, { immediate: true })

function printActivity() {
  window.print()
}

async function loadPackContainers() {
  if (!selectedActivity.value?.id) return
  try {
    packContainers.value = await getActivityPackContainers(selectedActivity.value.id)
    if (!packContainers.value.some((c) => c.id === selectedPackContainerId.value)) {
      selectedPackContainerId.value = packContainers.value[0]?.id || ''
    }
    await loadSelectedPackContainerItems()
  } catch (err) {
    console.error('Fehler beim Laden der Kisten-Instanzen:', err)
    packContainers.value = []
    selectedPackContainerItems.value = []
  }
}

async function loadSelectedPackContainerItems() {
  if (!selectedActivity.value?.id || !selectedPackContainerId.value) {
    selectedPackContainerItems.value = []
    return
  }
  selectedPackContainerItems.value = await getActivityPackContainerItems(
    selectedActivity.value.id,
    selectedPackContainerId.value
  ).catch(() => [])
}

async function createPackContainer() {
  const label = newPackContainerLabel.value.trim()
  if (!label || !selectedActivity.value?.id) return
  try {
    await createActivityPackContainer(selectedActivity.value.id, { label })
    newPackContainerLabel.value = ''
    await loadPackContainers()
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Kiste konnte nicht erstellt werden')
  }
}

async function addItemToPackContainer() {
  if (!selectedActivity.value?.id || !selectedPackContainerId.value || !newPackContainerItemMaterialId.value) return
  try {
    await createActivityPackContainerItem(selectedActivity.value.id, selectedPackContainerId.value, {
      material_item_id: newPackContainerItemMaterialId.value,
      quantity_packed: Math.max(1, newPackContainerItemQty.value || 1),
    })
    newPackContainerItemMaterialId.value = ''
    newPackContainerItemQty.value = 1
    await loadSelectedPackContainerItems()
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Container-Inhalt konnte nicht hinzugefügt werden')
  }
}

async function removeItemFromPackContainer(itemId: string) {
  if (!selectedActivity.value?.id || !selectedPackContainerId.value) return
  try {
    await deleteActivityPackContainerItem(selectedActivity.value.id, selectedPackContainerId.value, itemId)
    await loadSelectedPackContainerItems()
  } catch (err: any) {
    toast.error(err.response?.data?.error || 'Container-Inhalt konnte nicht entfernt werden')
  }
}

// Issue-Report Formular
const showIssueForm = ref(false)
const newIssue = ref({
  materialItemId: '',
  materialName: '',
  type: 'repair' as string,
  quantity: 1,
  description: '',
})
// Suchbare Material-Auswahl im Issue-Formular
const issueMatSearch = ref('')
const showIssueMatDropdown = ref(false)
const filteredDetailItems = computed(() => {
  if (detailMaterialSource.value === 'js') {
    return detailItems.value.filter((item: any) => item.isJsMaterial)
  }
  return detailItems.value.filter((item: any) => !item.isJsMaterial)
})
const issueMatFiltered = computed(() => {
  const q = issueMatSearch.value.toLowerCase().trim()
  if (!q) return detailItems.value
  return detailItems.value.filter((item: any) =>
    item.materialName?.toLowerCase().includes(q)
  )
})

function setDetailMaterialSource(source: 'internal' | 'js') {
  if (source === 'js' && !canUseDetailJsMaterialSource.value) {
    return
  }
  if (detailMaterialSource.value === source) return
  detailMaterialSource.value = source
  detailMatSearchResults.value = []
  detailMatActiveIndex.value = -1
  if (detailMatSearch.value.trim().length >= 2) {
    searchDetailMaterials()
  }
}

// Detail-Tabs dynamisch basierend auf Status
const detailTabs = computed(() => {
  const tabs = [
    { key: 'overview', label: 'Übersicht' },
    { key: 'material', label: 'Material' },
  ]
  
  const status = selectedActivity.value?.status
  if (!status) return tabs

  // Packliste: ab Status "packing" sichtbar
  if (['packing', 'packed', 'issued', 'returned', 'completed'].includes(status)) {
    tabs.push({ key: 'packlist', label: 'Auftrag Status' })
  }

  // Reparaturen/Verluste + Verbrauchsmaterial: ab Status "issued" sichtbar
  if (['issued', 'returned', 'completed'].includes(status)) {
    tabs.push({ key: 'issues', label: 'Reparaturen / Verluste' })
    tabs.push({ key: 'consumables', label: 'Verbrauchsmaterial' })
  }

  // Kosten: ab Status "packing" sichtbar (sobald Material zugeordnet)
  if (['packing', 'packed', 'issued', 'returned', 'completed'].includes(status)) {
    tabs.push({ key: 'costs', label: 'Kosten' })
  }

  // Rückgabe: ab Status "returned" sichtbar
  if (['returned', 'completed'].includes(status)) {
    tabs.push({ key: 'returns', label: 'Rückgabe' })
  }

  if (['camp', 'event'].includes(selectedActivity.value?.type || '')) {
    tabs.push({ key: 'invited_departments', label: 'Eingeladene Departments' })
  }

  tabs.push({ key: 'history', label: 'Verlauf' })
  return tabs
})

const invitedDepartmentsDetail = computed(() => {
  const raw = activityDetail.value?.invited_departments
  if (!Array.isArray(raw)) return []
  return raw.filter((entry: any) => entry && (entry.name || entry.organisation_name)).map((entry: any) => ({
    id: entry.id || '',
    name: entry.name || 'Unbekanntes Department',
    organisation_name: entry.organisation_name || '',
    status: entry.status || 'pending',
  }))
})

const typeFilterChips = [
  { type: 'activity', label: 'Aktivität' },
  { type: 'camp', label: 'Lager' },
  { type: 'event', label: 'Event' },
  { type: 'external', label: 'Extern' },
]

// Wizard step tracking (accordion)
const activeStep = ref(1)
const draftId = ref<string | null>(null)  // ID des Entwurfs in der DB
const isSaving = ref(false)
const lastSavedAt = ref<string | null>(null)

// Template refs for auto-focus
const nameInputRef = ref<HTMLInputElement | null>(null)
const customerInputRef = ref<HTMLInputElement | null>(null)
const notesInputRef = ref<HTMLTextAreaElement | null>(null)

// Gruppen-Auswahl State
interface MyGroup {
  id: string
  name: string
  parent_id: string | null
  level: number
  role: string | null        // 'leader', 'member', oder null
  selectable: boolean         // true = User darf wählen, false = ausgegraut
  is_direct_member: boolean
  member_count: number
  hasChildren?: boolean
}
const myGroups = ref<MyGroup[]>([])
const isLoadingGroups = ref(false)
const groupSearchQuery = ref('')

// Adress-Picker State (für Extern)
const showAddressModal = ref(false)
const addressModalDefaultType = ref<'event' | 'customer'>('event')
const departmentAddresses = ref<Address[]>([])
const selectedAddress = ref<Address | null>(null)
const addressSearchQuery = ref('')
const isLoadingAddresses = ref(false)
const showDepartmentInvitePanel = ref(false)
const departmentInviteQuery = ref('')
const departmentInviteResults = ref<DepartmentSearchResult[]>([])
const invitedDepartments = ref<DepartmentSearchResult[]>([])
const isDepartmentInviteLoading = ref(false)
let departmentInviteSearchTimer: ReturnType<typeof setTimeout> | null = null

// Quick-Mode: Expandable sections
const showQuickMaterialSearch = ref(false)
const showQuickNotes = ref(false)
const showGroupDropdown = ref(false)
const showGroupDropdownWizard = ref(false)

// Material-Vorschläge
const materialSuggestions = ref<MaterialSuggestion[]>([])
const isLoadingSuggestions = ref(false)
const suggestionsLabel = ref('')

// Aktivität: Einzeltag-Felder
const activityDate = ref('')
const activityTimeStart = ref('14:00')
const activityTimeEnd = ref('17:00')

// Konfigurierbare Defaults (werden aus DepartmentSettings geladen)
const activityDefaults = ref<ActivityDefaults>({
  defaultTimeStart: '14:00',
  defaultTimeEnd: '17:00',
  materialLeadMinutes: 60,   // Materialvorlaufzeit in Minuten
  materialLagMinutes: 60,    // Materialnachlaufzeit in Minuten
  campMaterialLeadDays: 1,   // Vorlauf für Lager/Events in Tagen
  campMaterialLagDays: 1,    // Nachlauf für Lager/Events in Tagen
})

// Settings aus API laden
async function loadActivityDefaults() {
  try {
    const defaults = await getActivityDefaults(departmentId.value)
    activityDefaults.value = defaults
    // Initiale Zeiten auch aktualisieren
    activityTimeStart.value = defaults.defaultTimeStart
    activityTimeEnd.value = defaults.defaultTimeEnd
  } catch (err) {
    console.warn('Konnte Aktivitäts-Defaults nicht laden, verwende Standardwerte:', err)
  }
}

// Click-Outside Handler für Group-Dropdowns
function handleGlobalClick(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.group-picker')) {
    showGroupDropdown.value = false
    showGroupDropdownWizard.value = false
    groupSearchQuery.value = ''
  }
}

onMounted(() => {
  loadActivities()
  loadActivityDefaults()
  document.addEventListener('click', handleGlobalClick)
})

onUnmounted(() => {
  document.removeEventListener('click', handleGlobalClick)
  if (departmentInviteSearchTimer) {
    clearTimeout(departmentInviteSearchTimer)
    departmentInviteSearchTimer = null
  }
})

interface Activity {
  id: string
  no?: string
  name: string
  departmentId?: string
  departmentName?: string
  type: 'activity' | 'camp' | 'event' | 'external'
  status: 'draft' | 'submitted' | 'approved' | 'packing' | 'packed' | 'issued' | 'returned' | 'completed' | 'cancelled'
  invitedDepartments?: Array<{ id?: string; name?: string; organisation_name?: string; status?: string }>
  customerName?: string
  groupName?: string
  usageStart?: string
  usageEnd?: string
  itemCount?: number
  totalPrice?: number
  createdAt: string
  updatedAt: string
}

// Workflow-bezogene Typen
interface StatusTransition {
  status: string
  label: string
  allowed: boolean
  reason?: string
}

interface PackItem {
  id: string
  materialItemId: string
  materialName: string
  categoryName: string | null
  categoryId: string | null
  packSize: number | null
  packUnit: string | null
  quantityOrdered: number
  quantityPacked: number
  quantityIssued: number
  quantityReturned: number
  conditionOut: string
  batchNumbers?: string
  notes?: string
  isFullyPacked: boolean
  isFullyIssued: boolean
  isFullyReturned: boolean
  packDifference: number
  issueDifference: number
  returnDifference: number
  isConsumable: boolean
  isJsMaterial?: boolean
  externalSource?: string | null
  packedAt?: string
}

interface IssueReport {
  id: string
  materialItemId?: string
  materialName?: string
  type: 'repair' | 'loss' | 'consumption' | 'damage'
  typeLabel: string
  quantity: number
  description?: string
  photoUrl?: string
  notes?: string
  resolved: boolean
  resolvedAt?: string
  reportedAt: string
  isJsMaterial?: boolean
  externalSource?: string | null
}

interface ReturnItem {
  id: string
  materialItemId: string
  materialName: string
  quantityPacked: number
  quantityReturned: number
  quantityDamaged: number
  quantityMissing: number
  quantityOk: number
  conditionIn: string
  notes?: string
  hasDifferences: boolean
  returnedAt?: string
  isJsMaterial?: boolean
  externalSource?: string | null
}

interface SelectedMaterialItem {
  materialItemId: string
  materialName: string
  sourceDepartmentId?: string | null
  sourceDepartmentName?: string | null
  quantity: number
  availableQuantity: number
  priority: string
  isConsumable: boolean
  isJsMaterial?: boolean
  externalSource?: string | null
  unitPrice: number | null
  lineTotal: number | null
  priceType: 'rental_day' | 'rental_week' | 'rental_month' | 'sale' | 'free'
  salePrice: string | null
  rentalPriceDay: string | null
  rentalPriceWeek: string | null
  rentalPriceMonth: string | null
}

const newActivity = ref({
  name: '',
  groupId: null as string | null,
  usageStart: '',
  usageEnd: '',
  planningStart: '',
  planningEnd: '',
  customerName: '',
  customerEmail: '',
  customerPhone: '',
  addressId: null as string | null,
  notes: '',
  pricingMode: 'item_price' as 'set_price' | 'item_price',
  setPrice: null as number | null,
  selectedItems: [] as SelectedMaterialItem[],
})

const activities = ref<Activity[]>([])
const isLoading = ref(false)

function mapActivityListItem(a: any): Activity {
  return {
    id: a.id,
    no: a.no ? `#${String(a.no).padStart(3, '0')}` : undefined,
    name: a.name,
    departmentId: a.department_id,
    departmentName: a.department_name,
    type: a.type,
    status: a.status,
    invitedDepartments: Array.isArray(a.invited_departments) ? a.invited_departments : [],
    customerName: a.customer_name,
    groupName: a.group_name,
    usageStart: a.usage_start,
    usageEnd: a.usage_end,
    itemCount: a.item_count ?? 0,
    totalPrice: a.total_price,
    createdAt: a.created_at,
    updatedAt: a.updated_at,
  }
}

function getActivityShareHint(activity: Activity): string | null {
  if (activity.departmentId && activity.departmentId !== departmentId.value) {
    return `Geteilt von: ${activity.departmentName || 'anderem Department'}`
  }
  const accepted = (activity.invitedDepartments || []).filter((entry) => entry?.status === 'accepted')
  if (accepted.length > 0) {
    const names = accepted
      .map((entry) => entry?.name)
      .filter((name): name is string => !!name)
      .slice(0, 2)
    if (names.length === 0) return 'Geteilt mit anderen Departments'
    const suffix = accepted.length > 2 ? ` +${accepted.length - 2}` : ''
    return `Geteilt mit: ${names.join(', ')}${suffix}`
  }
  return null
}

function getActivityShareStatus(activity: Activity): string | null {
  const invited = activity.invitedDepartments || []
  if (activity.departmentId && activity.departmentId !== departmentId.value) {
    const ownInvite = invited.find((entry) => entry?.id === departmentId.value)
    if (!ownInvite) return null
    if (ownInvite.status === 'accepted') return 'Status: angenommen'
    if (ownInvite.status === 'rejected') return 'Status: abgelehnt'
    return 'Status: ausstehend'
  }

  if (invited.length === 0) return null
  const accepted = invited.filter((entry) => entry?.status === 'accepted').length
  const pending = invited.filter((entry) => entry?.status === 'pending').length
  const rejected = invited.filter((entry) => entry?.status === 'rejected').length

  const parts: string[] = []
  if (accepted > 0) parts.push(`${accepted} angenommen`)
  if (pending > 0) parts.push(`${pending} ausstehend`)
  if (rejected > 0) parts.push(`${rejected} abgelehnt`)
  return parts.length > 0 ? `Freigabe: ${parts.join(' · ')}` : null
}

// === Aktivitäten aus der API laden ===
async function loadActivities() {
  isLoading.value = true
  try {
    const response = await apiClient.get('/api/activities', {
      params: {
        department_id: departmentId.value,
      }
    })
    activities.value = (response.data || []).map((a: any) => mapActivityListItem(a))
  } catch (err: any) {
    console.error('Fehler beim Laden der Aktivitäten:', err)
    const msg = err?.code === 'ECONNABORTED' ? 'Zeitüberschreitung – Backend antwortet nicht.' : (err?.response?.data?.error || err?.message)
    toast.error('Aktivitäten konnten nicht geladen werden: ' + msg)
  } finally {
    isLoading.value = false
  }
}

// === Auto-Save: Entwurf bei jedem Step-Wechsel speichern ===
async function autoSaveDraft() {
  if (isSaving.value) return
  if (!selectedTemplate.value || !newActivity.value.name.trim()) return // Mindestens Typ + Name

  isSaving.value = true
  try {
    // Datums-Felder zusammenbauen (wie bei createActivity)
    let usageStart = newActivity.value.usageStart
    let usageEnd = newActivity.value.usageEnd
    let planningStart = newActivity.value.planningStart
    let planningEnd = newActivity.value.planningEnd

    if (selectedTemplate.value === 'activity' && activityDate.value) {
      const startLocal = `${activityDate.value}T${activityTimeStart.value}:00`
      const endLocal = `${activityDate.value}T${activityTimeEnd.value}:00`
      const start = new Date(startLocal)
      const end = new Date(endLocal)
      // Alles einheitlich als UTC (ISO) senden
      usageStart = start.toISOString()
      usageEnd = end.toISOString()
      const pickup = new Date(start)
      pickup.setMinutes(pickup.getMinutes() - (activityDefaults.value.materialLeadMinutes ?? 60))
      planningStart = pickup.toISOString()
      const returnTime = new Date(end)
      returnTime.setMinutes(returnTime.getMinutes() + (activityDefaults.value.materialLagMinutes ?? 60))
      planningEnd = returnTime.toISOString()
    }

    // Alle Datumswerte einheitlich als UTC (ISO) konvertieren
    const toISO = (val: string | null): string | null => {
      if (!val) return null
      // Wenn bereits ISO mit Timezone (enthält 'Z' oder '+'), direkt verwenden
      if (val.includes('Z') || val.includes('+')) return val
      // datetime-local Wert (z.B. "2026-02-10T14:00") → in UTC konvertieren
      return new Date(val).toISOString()
    }

    const payload: Record<string, any> = {
      department_id: departmentId.value,
      type: selectedTemplate.value,
      name: newActivity.value.name,
      status: 'draft',
      usage_start: toISO(usageStart),
      usage_end: toISO(usageEnd),
      planning_start: toISO(planningStart),
      planning_end: toISO(planningEnd),
      notes: newActivity.value.notes || null,
      invited_departments: invitedDepartments.value.map((entry) => ({
        id: entry.id,
        name: entry.name,
        organisation_name: entry.organisation_name,
      })),
    }

    if (newActivity.value.groupId) payload.group_id = newActivity.value.groupId
    if (newActivity.value.customerName) payload.customer_name = newActivity.value.customerName
    if (newActivity.value.customerEmail) payload.customer_email = newActivity.value.customerEmail
    if (newActivity.value.customerPhone) payload.customer_phone = newActivity.value.customerPhone
    if (newActivity.value.addressId) payload.address_id = newActivity.value.addressId

    // Preismodus
    payload.pricing_mode = newActivity.value.pricingMode
    if (newActivity.value.pricingMode === 'set_price' && newActivity.value.setPrice != null) {
      payload.total_price = String(newActivity.value.setPrice)
    }

    if (draftId.value) {
      // Update bestehenden Entwurf
      await apiClient.patch(`/api/activities/${draftId.value}`, payload)
    } else {
      // Neuen Entwurf erstellen
      const response = await apiClient.post('/api/activities', payload)
      draftId.value = response.data.id
    }

    // Items sync: wenn Items vorhanden, diese auch speichern
    if (draftId.value && newActivity.value.selectedItems.length > 0) {
      await apiClient.put(`/api/activities/${draftId.value}/items`, {
        items: newActivity.value.selectedItems.map(item => ({
          material_item_id: item.materialItemId,
          quantity: item.quantity,
          priority: item.priority,
          unit_price: item.unitPrice != null ? String(item.unitPrice) : null,
          line_total: item.lineTotal != null ? String(item.lineTotal) : null,
          price_type: item.priceType,
        }))
      })
    }

    lastSavedAt.value = new Date().toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit' })
    console.log(`💾 Entwurf gespeichert (${draftId.value}) um ${lastSavedAt.value}`)
  } catch (err) {
    console.error('Auto-Save fehlgeschlagen:', err)
    throw err
  } finally {
    isSaving.value = false
  }
}

// === Nächster Samstag berechnen (wie v4) ===
function getNextSaturday(): string {
  const today = new Date()
  const daysUntilSaturday = (6 - today.getDay() + 7) % 7 || 7
  const nextSat = new Date(today)
  nextSat.setDate(today.getDate() + daysUntilSaturday)
  return nextSat.toISOString().split('T')[0] // YYYY-MM-DD
}

// Heute als min-Datum
const todayStr = computed(() => new Date().toISOString().split('T')[0])
const todayTimeStr = computed(() => {
  const now = new Date()
  return now.toISOString().slice(0, 16) // YYYY-MM-DDTHH:mm
})

// === Step numbers ===
// Old wizard (kept for reference): 1=Typ, 2=Name, 3=Zeitraum, 34=Gruppe, 35=Material
// New wizard (camp/event/extern): 1=Grunddaten, 2=Zeitraum, 3=Material, 4=Zusammenfassung
const groupStepNumber = 34     // Legacy: Gruppe auswählen (only used in old accordion for activity quick mode)
const materialStepNumber = 35  // Legacy: Material-Auswahl (only used in old accordion for activity quick mode)
const notesStepNumber = computed(() => selectedTemplate.value === 'external' ? 6 : 5)
// New wizard step numbers
const WIZARD_STEP_GRUNDDATEN = 1
const WIZARD_STEP_ZEITRAUM = 2
const WIZARD_STEP_MATERIAL = 3
const WIZARD_STEP_SUMMARY = 4
const wizardStep = ref(1)

// === Step 3 completion check ===
const isStep3Complete = computed(() => {
  if (selectedTemplate.value === 'activity') {
    return !!activityDate.value && !!activityTimeStart.value && !!activityTimeEnd.value
  }
  return !!newActivity.value.usageStart && !!newActivity.value.usageEnd && !!newActivity.value.planningStart && !!newActivity.value.planningEnd
})

// === Template selection → set defaults + auto-advance ===
function selectTemplate(type: string) {
  selectedTemplate.value = type
  showDepartmentInvitePanel.value = false
  departmentInviteQuery.value = ''
  departmentInviteResults.value = []
  invitedDepartments.value = []

  // Set defaults per type
  if (type === 'activity') {
    activityDate.value = getNextSaturday()
    activityTimeStart.value = activityDefaults.value.defaultTimeStart
    activityTimeEnd.value = activityDefaults.value.defaultTimeEnd
    // Quick mode: Load groups + auto-select
    loadMyGroups().then(() => autoSelectGroup())
  } else {
    newActivity.value.usageStart = ''
    newActivity.value.usageEnd = ''
    newActivity.value.planningStart = ''
    newActivity.value.planningEnd = ''
    // Wizard mode: Reset to step 1, load groups
    wizardStep.value = WIZARD_STEP_GRUNDDATEN
    loadMyGroups().then(() => autoSelectGroup())
    loadDepartmentAddresses()
  }

  // Preise neu berechnen (intern vs. extern)
  recalculateAllPrices()

  // Old accordion: advance to Name step
  activeStep.value = 2
  nextTick(() => {
    nameInputRef.value?.focus()
  })
}

// === Advance from Step 2 (Name) → Step 3 (Dates) + auto-save ===
function advanceFromName() {
  if (newActivity.value.name.trim()) {
    activeStep.value = 3
    autoSaveDraft() // Erstmals speichern (Typ + Name)
  }
}

// === Advance from Step 3 (Dates) → Step 34 (Gruppe) ===
function advanceFromDates() {
  if (isStep3Complete.value) {
    activeStep.value = groupStepNumber // Gruppe auswählen
    loadMyGroups() // Gruppen laden
    autoSaveDraft() // Zeitraum speichern
  }
}

// === Advance from Step 34 (Gruppe) → Step 35 (Material) ===
function advanceFromGroup() {
  if (newActivity.value.groupId) {
    activeStep.value = materialStepNumber
    autoSaveDraft() // Gruppe speichern
  }
}

// === Advance from Step 3.5 (Material) → Step 4 (Customer) or Notes ===
function advanceFromMaterial() {
  if (selectedTemplate.value === 'external') {
    activeStep.value = 4
    nextTick(() => {
      customerInputRef.value?.focus()
    })
  } else {
    activeStep.value = notesStepNumber.value
    nextTick(() => {
      notesInputRef.value?.focus()
    })
  }
  autoSaveDraft() // Material-Auswahl speichern
}

// === Advance from Step 4 (Customer) → Step 5 (Notes) + auto-save ===
function advanceFromCustomer() {
  if (newActivity.value.customerName?.trim()) {
    activeStep.value = notesStepNumber.value
    nextTick(() => {
      notesInputRef.value?.focus()
    })
    autoSaveDraft() // Kundeninfo speichern
  }
}

// === Material-Auswahl: Such-basiert (Autocomplete) ===
const materialSearchResults = ref<any[]>([])
const isMaterialLoading = ref(false)
const materialSearch = ref('')
const showMaterialDropdown = ref(false)
const materialSearchActiveIndex = ref(-1)
const materialSearchInput = ref<{ focus: () => void } | null>(null)
const materialSource = ref<'all' | 'internal' | 'js'>('internal')
const canUseJsMaterialSource = computed(() => selectedTemplate.value === 'camp' || selectedTemplate.value === 'event')
const showJsOrderForm = ref(false)
const jsOrderForm = ref({
  courseType: '',
  participants: null as number | null,
  deliveryDate: '',
  returnDate: '',
  logistics: 'lieferung' as 'lieferung' | 'abholung',
  contactPerson: '',
  notes: '',
})
let materialSearchTimer: ReturnType<typeof setTimeout> | null = null

const activityMaterialLookupFetcher = createAvailabilityMaterialLookupFetcher(() => {
  const dateRange = getMaterialDateRange()
  if (!dateRange) return null
  return {
    departmentId: departmentId.value,
    activityId: draftId.value || undefined,
    startDate: dateRange.startDate,
    endDate: dateRange.endDate,
    source: canUseJsMaterialSource.value ? materialSource.value : 'internal',
    includeGlobalJs: canUseJsMaterialSource.value,
    limit: 20,
  }
})

const detailMaterialLookupFetcher = createAvailabilityMaterialLookupFetcher(() => {
  if (!selectedActivity.value?.id) return null
  const startDate = activityDetail.value?.planning_start || activityDetail.value?.usage_start
  const endDate = activityDetail.value?.planning_end || activityDetail.value?.usage_end
  return {
    departmentId: departmentId.value,
    activityId: selectedActivity.value.id,
    excludeActivityId: selectedActivity.value.id,
    startDate: startDate || undefined,
    endDate: endDate || undefined,
    source: canUseDetailJsMaterialSource.value ? detailMaterialSource.value : 'internal',
    includeGlobalJs: canUseDetailJsMaterialSource.value,
    limit: 20,
  }
})

function setMaterialSource(source: 'all' | 'internal' | 'js') {
  if (!canUseJsMaterialSource.value && source !== 'internal') {
    return
  }
  if (materialSource.value === source) return
  materialSource.value = source
  materialSearchResults.value = []
  materialSearchActiveIndex.value = -1
  if (materialSearch.value.trim().length >= 2) {
    searchMaterials()
  }
}

function resetJsOrderForm() {
  jsOrderForm.value = {
    courseType: '',
    participants: null,
    deliveryDate: '',
    returnDate: '',
    logistics: 'lieferung',
    contactPerson: '',
    notes: '',
  }
}

function applyJsOrderToActivityNotes() {
  const logisticsText = jsOrderForm.value.logistics === 'abholung'
    ? 'Abholung im J&S Lager'
    : 'Lieferung ins Lager'
  const lines = [
    '[J&S BESTELLFORMULAR]',
    `Kursart: ${jsOrderForm.value.courseType || '-'}`,
    `Teilnehmende: ${jsOrderForm.value.participants || '-'}`,
    `Lieferdatum: ${jsOrderForm.value.deliveryDate || '-'}`,
    `Ruecklieferung: ${jsOrderForm.value.returnDate || '-'}`,
    `Logistik: ${logisticsText}`,
    `Kontaktperson: ${jsOrderForm.value.contactPerson || '-'}`,
    `Bemerkungen: ${jsOrderForm.value.notes || '-'}`,
  ]
  const block = lines.join('\n')
  newActivity.value.notes = newActivity.value.notes?.trim()
    ? `${newActivity.value.notes.trim()}\n\n${block}`
    : block
  toast.success('J&S-Bestellformular in Notizen übernommen.')
}

function normalizeJsName(name: string): string {
  return (name || '')
    .toLowerCase()
    .replace(/ä/g, 'ae')
    .replace(/ö/g, 'oe')
    .replace(/ü/g, 'ue')
    .replace(/ß/g, 'ss')
}

function roundUpTo(value: number, step: number): number {
  if (step <= 1) return Math.ceil(value)
  return Math.ceil(value / step) * step
}

function getJsAllowedQty(item: SelectedMaterialItem): number | null {
  if (!item.isJsMaterial) return null

  const n = normalizeJsName(item.materialName)
  const participants = Number(jsOrderForm.value.participants || 0)
  const hasParticipants = participants > 0

  const byParticipants = (perCount: number, roundTo?: number, cap?: number): number | null => {
    if (!hasParticipants) return null
    let qty = participants / perCount
    qty = roundTo ? roundUpTo(qty, roundTo) : Math.ceil(qty)
    if (cap) qty = Math.min(qty, cap)
    return Math.max(0, qty)
  }

  if (n.includes('bindestrick')) return byParticipants(1, 5, 50)
  if (n.includes('wolldecke')) return byParticipants(0.5, 5) // 2 pro Teilnehmer/in
  if (n.includes('kessel 15')) return byParticipants(6)
  if (n.includes('kesselaufsatz')) return byParticipants(6)
  if (n.includes('handbeil')) return byParticipants(4)
  if (n.includes('kochkessel 12')) return byParticipants(8)
  if (n.includes('kompass recta')) return byParticipants(2, 5)
  if (n.includes('kompass silva')) return byParticipants(2, 5)
  if (n.includes('manipulierseil')) return byParticipants(2)
  if (n.includes('pickel')) return byParticipants(4)
  if (n.includes('beinstulpe')) return byParticipants(1)
  if (n.includes('schwimmwesten')) return byParticipants(1, undefined, 20)
  if (n.includes('schneeschaufel')) return byParticipants(1, undefined, 15)
  if (n.includes('sonnenbrille')) return byParticipants(1)
  if (n.includes('spaten')) return byParticipants(4)
  if (n.includes('spisetraeger 20 l')) return byParticipants(18)
  if (n.includes('zelttasche')) return byParticipants(1, 5)
  if (n.includes('ausschusszelttuch')) return byParticipants(1, 10)
  if (n.includes('zelttuch')) return byParticipants(1, 10)

  // Pro Kurs/Lager (fixe Richtmenge)
  if (n.includes('badminton (1 netz')) return 3
  if (n.includes('volleyball (1 netz')) return 1
  if (n.includes('badminton/volleyball set kombiniert')) return 2
  if (n.includes('ballset')) return 2

  return null
}

function isJsQuantityExceeded(item: SelectedMaterialItem): boolean {
  const max = getJsAllowedQty(item)
  if (max == null) return false
  return item.quantity > max
}

function needsJsParticipants(item: SelectedMaterialItem): boolean {
  if (!item.isJsMaterial) return false
  const n = normalizeJsName(item.materialName)
  const fixedPerCourse =
    n.includes('badminton (1 netz') ||
    n.includes('volleyball (1 netz') ||
    n.includes('badminton/volleyball set kombiniert') ||
    n.includes('ballset')
  if (fixedPerCourse) return false
  return !jsOrderForm.value.participants || jsOrderForm.value.participants <= 0
}

function getMaterialDateRange(): { startDate: string, endDate: string } | null {
  let startDate = newActivity.value.planningStart || newActivity.value.usageStart
  let endDate = newActivity.value.planningEnd || newActivity.value.usageEnd

  if (selectedTemplate.value === 'activity' && activityDate.value) {
    const lead = activityDefaults.value.materialLeadMinutes ?? 60
    const lag = activityDefaults.value.materialLagMinutes ?? 60
    const start = new Date(`${activityDate.value}T${activityTimeStart.value}:00`)
    const pickup = new Date(start)
    pickup.setMinutes(pickup.getMinutes() - lead)
    startDate = pickup.toISOString()
    const end = new Date(`${activityDate.value}T${activityTimeEnd.value}:00`)
    const returnTime = new Date(end)
    returnTime.setMinutes(returnTime.getMinutes() + lag)
    endDate = returnTime.toISOString()
  }

  if (!startDate || !endDate) return null
  return { startDate, endDate }
}

function onMaterialSearchInput() {
  // Debounce: 300ms nach letztem Tastendruck suchen
  if (materialSearchTimer) clearTimeout(materialSearchTimer)
  
  if (materialSearch.value.length < 2) {
    materialSearchResults.value = []
    showMaterialDropdown.value = false
    materialSearchActiveIndex.value = -1
    return
  }

  showMaterialDropdown.value = true
  materialSearchActiveIndex.value = -1
  materialSearchTimer = setTimeout(() => {
    searchMaterials()
  }, 300)
}

async function searchMaterials() {
  const query = materialSearch.value.trim()
  if (query.length < 2) return

  isMaterialLoading.value = true
  try {
    materialSearchResults.value = await activityMaterialLookupFetcher(query)
    showMaterialDropdown.value = true
    materialSearchActiveIndex.value = materialSearchResults.value.length > 0 ? 0 : -1
  } catch (err) {
    console.error('Fehler beim Suchen der Materialien:', err)
    materialSearchResults.value = []
    materialSearchActiveIndex.value = -1
  } finally {
    isMaterialLoading.value = false
  }
}

function moveMaterialSearchActive(step: 1 | -1) {
  if (!materialSearchResults.value.length) return
  if (materialSearchActiveIndex.value < 0) {
    materialSearchActiveIndex.value = 0
    return
  }
  const len = materialSearchResults.value.length
  materialSearchActiveIndex.value = (materialSearchActiveIndex.value + step + len) % len
}

function acceptMaterialSearchSelection() {
  if (!materialSearchResults.value.length) return
  const index = materialSearchActiveIndex.value >= 0 ? materialSearchActiveIndex.value : 0
  const selected = materialSearchResults.value[index]
  if (!selected || selected.availableForPeriod <= 0) return
  addMaterialWithQty(selected, 1)
}

function handleActivityLookupSelect(selected: any) {
  if (!selected || selected.availableForPeriod <= 0) return
  addMaterialWithQty(selected, 1)
}

function calculateItemPrice(item: SelectedMaterialItem): { unitPrice: number | null, lineTotal: number | null, priceType: SelectedMaterialItem['priceType'] } {
  const isExternal = selectedTemplate.value === 'external'
  
  // Verbrauchsmaterial: immer sale_price (auch intern wenn gewünscht)
  if (item.isConsumable && item.salePrice) {
    const up = Number(item.salePrice)
    return { unitPrice: up, lineTotal: up * item.quantity, priceType: 'sale' }
  }
  
  // Intern: gratis
  if (!isExternal) {
    return { unitPrice: null, lineTotal: null, priceType: 'free' }
  }
  
  // Extern: automatische Preisberechnung nach Dauer
  const dateRange = getMaterialDateRange()
  if (!dateRange) return { unitPrice: null, lineTotal: null, priceType: 'free' }
  
  const start = new Date(dateRange.startDate)
  const end = new Date(dateRange.endDate)
  const days = Math.max(1, Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)))
  
  if (days >= 28 && item.rentalPriceMonth) {
    const months = Math.ceil(days / 30)
    const up = Number(item.rentalPriceMonth) * months
    return { unitPrice: up, lineTotal: up * item.quantity, priceType: 'rental_month' }
  } else if (days >= 7 && item.rentalPriceWeek) {
    const weeks = Math.ceil(days / 7)
    const up = Number(item.rentalPriceWeek) * weeks
    return { unitPrice: up, lineTotal: up * item.quantity, priceType: 'rental_week' }
  } else if (item.rentalPriceDay) {
    const up = Number(item.rentalPriceDay) * days
    return { unitPrice: up, lineTotal: up * item.quantity, priceType: 'rental_day' }
  }
  
  return { unitPrice: null, lineTotal: null, priceType: 'free' }
}

function recalculateAllPrices() {
  for (const item of newActivity.value.selectedItems) {
    const { unitPrice, lineTotal, priceType } = calculateItemPrice(item)
    item.unitPrice = unitPrice
    item.lineTotal = lineTotal
    item.priceType = priceType
  }
}

const estimatedTotalPrice = computed(() => {
  if (newActivity.value.pricingMode === 'set_price') {
    return newActivity.value.setPrice || 0
  }
  return newActivity.value.selectedItems.reduce((sum, item) => {
    return sum + (item.lineTotal || 0)
  }, 0)
})

const isExternalActivity = computed(() => selectedTemplate.value === 'external')

function addMaterialWithQty(mat: any, qty: number) {
  if (mat.availableForPeriod <= 0) return
  const actualQty = Math.min(qty, mat.availableForPeriod)
  
  // Prüfen ob schon hinzugefügt
  const existing = newActivity.value.selectedItems.find(i => i.materialItemId === mat.materialItemId)
  if (existing) {
    existing.quantity = Math.min(existing.quantity + actualQty, existing.availableQuantity)
    // Preis neu berechnen
    const { unitPrice, lineTotal, priceType } = calculateItemPrice(existing)
    existing.unitPrice = unitPrice
    existing.lineTotal = lineTotal
    existing.priceType = priceType
  } else {
    const newItem: SelectedMaterialItem = {
      materialItemId: mat.materialItemId,
      materialName: mat.name,
      quantity: actualQty,
      availableQuantity: mat.availableForPeriod,
      priority: 'normal',
      isConsumable: mat.isConsumable || false,
      isJsMaterial: mat.isJsMaterial || false,
      externalSource: mat.externalSource || null,
      unitPrice: null,
      lineTotal: null,
      priceType: 'free',
      salePrice: mat.salePrice || null,
      rentalPriceDay: mat.rentalPriceDay || null,
      rentalPriceWeek: mat.rentalPriceWeek || null,
      rentalPriceMonth: mat.rentalPriceMonth || null,
    }
    // Preis berechnen
    const { unitPrice, lineTotal, priceType } = calculateItemPrice(newItem)
    newItem.unitPrice = unitPrice
    newItem.lineTotal = lineTotal
    newItem.priceType = priceType
    
    newActivity.value.selectedItems.push(newItem)
  }
  
  // Suchfeld leeren und Dropdown schliessen
  materialSearch.value = ''
  materialSearchResults.value = []
  showMaterialDropdown.value = false
  materialSearchActiveIndex.value = -1
  // Fokus zurück auf Suchfeld
  nextTick(() => materialSearchInput.value?.focus())
  autoSaveDraft()
}

function setMaterialQty(materialItemId: string, qty: number) {
  const item = newActivity.value.selectedItems.find(i => i.materialItemId === materialItemId)
  if (item) {
    item.quantity = Math.min(qty, item.availableQuantity)
    // Preis neu berechnen
    const { unitPrice, lineTotal, priceType } = calculateItemPrice(item)
    item.unitPrice = unitPrice
    item.lineTotal = lineTotal
    item.priceType = priceType
    autoSaveDraft()
  }
}

// Material-Auswahl Hilfsfunktionen
function isMaterialSelected(materialItemId: string): boolean {
  return newActivity.value.selectedItems.some(i => i.materialItemId === materialItemId)
}

function removeMaterial(materialItemId: string) {
  newActivity.value.selectedItems = newActivity.value.selectedItems.filter(i => i.materialItemId !== materialItemId)
}

function changeMaterialQty(materialItemId: string, delta: number) {
  const item = newActivity.value.selectedItems.find(i => i.materialItemId === materialItemId)
  if (!item) return
  const newQty = item.quantity + delta
  if (newQty <= 0) {
    removeMaterial(materialItemId)
  } else if (newQty <= item.availableQuantity) {
    item.quantity = newQty
    // Preis neu berechnen
    const { unitPrice, lineTotal, priceType } = calculateItemPrice(item)
    item.unitPrice = unitPrice
    item.lineTotal = lineTotal
    item.priceType = priceType
  }
}

// === Gruppen laden ===
async function loadMyGroups() {
  if (isLoadingGroups.value) return
  isLoadingGroups.value = true
  try {
    const response = await apiClient.get(`/api/departments/${departmentId.value}/my-groups`)
    const groups = response.data || []
    // hasChildren Flag berechnen
    const parentIds = new Set(groups.map((g: any) => g.parent_id).filter(Boolean))
    myGroups.value = groups.map((g: any) => ({
      id: g.id,
      name: g.name,
      parent_id: g.parent_id,
      level: g.level ?? 0,
      role: g.role ?? null,
      selectable: g.selectable ?? false,
      is_direct_member: g.is_direct_member ?? false,
      member_count: g.member_count ?? 0,
      hasChildren: parentIds.has(g.id) || groups.some((c: any) => c.parent_id === g.id),
    }))
  } catch (err) {
    console.error('Fehler beim Laden der Gruppen:', err)
    myGroups.value = []
  } finally {
    isLoadingGroups.value = false
  }
}

const filteredGroups = computed(() => {
  if (!groupSearchQuery.value) return myGroups.value
  const q = groupSearchQuery.value.toLowerCase()
  return myGroups.value.filter(g => g.name.toLowerCase().includes(q))
})

// Nur wählbare Gruppen (für Dropdown im Quick-Modus)
const selectableGroups = computed(() => myGroups.value.filter(g => g.selectable))

function selectGroup(grp: MyGroup) {
  if (!grp.selectable) return // Ausgegraut → nicht wählbar
  newActivity.value.groupId = grp.id
  // Auto-advance nach kurzer Pause (nur im alten Wizard-Modus)
  if (wizardMode.value !== 'quick') {
    setTimeout(() => {
      advanceFromGroup()
    }, 200)
  }
}

function getSelectedGroupName(): string {
  const grp = myGroups.value.find(g => g.id === newActivity.value.groupId)
  return grp?.name || '–'
}

// Auto-Select Gruppe wenn User nur in einer Gruppe ist
function autoSelectGroup() {
  if (newActivity.value.groupId) return // Bereits ausgewählt
  const selectable = myGroups.value.filter(g => g.selectable)
  if (selectable.length === 1) {
    newActivity.value.groupId = selectable[0].id
  }
}

// Gruppen/Material/Adressen laden wenn der jeweilige Step geöffnet wird
watch(activeStep, (step) => {
  if (step === groupStepNumber) {
    loadMyGroups()
  }
  if (step === materialStepNumber) {
    // Fokus auf Suchfeld wenn Material-Step geöffnet wird
    nextTick(() => materialSearchInput.value?.focus())
  }
  // Adressen laden wenn Kunden-Step bei Extern geöffnet wird
  if (step === 4 && selectedTemplate.value === 'external') {
    loadDepartmentAddresses()
  }
})

// Wizard-Step-Watcher (für neuen 4-Step Wizard)
watch(wizardStep, (step) => {
  if (step === WIZARD_STEP_GRUNDDATEN) {
    loadMyGroups()
    // Adressen laden für Standort/Adresse
    loadDepartmentAddresses()
  }
  if (step === WIZARD_STEP_MATERIAL) {
    nextTick(() => materialSearchInput.value?.focus())
    loadMaterialSuggestions()
  }
})

watch(departmentInviteQuery, (value) => {
  if (!showDepartmentInvitePanel.value || (selectedTemplate.value !== 'camp' && selectedTemplate.value !== 'event')) {
    departmentInviteResults.value = []
    isDepartmentInviteLoading.value = false
    return
  }
  if (departmentInviteSearchTimer) {
    clearTimeout(departmentInviteSearchTimer)
    departmentInviteSearchTimer = null
  }
  const q = value.trim()
  if (q.length < 2) {
    departmentInviteResults.value = []
    isDepartmentInviteLoading.value = false
    return
  }
  departmentInviteSearchTimer = setTimeout(async () => {
    isDepartmentInviteLoading.value = true
    try {
      const results = await searchJoinableDepartments(q)
      departmentInviteResults.value = results.slice(0, 10)
    } catch (err) {
      console.error('Fehler bei Department-Suche:', err)
      departmentInviteResults.value = []
    } finally {
      isDepartmentInviteLoading.value = false
    }
  }, 250)
})

watch(canUseJsMaterialSource, (allowed) => {
  if (allowed) return

  materialSource.value = 'internal'
  showJsOrderForm.value = false
  resetJsOrderForm()
  materialSearchResults.value = []
  materialSearchActiveIndex.value = -1

  const before = newActivity.value.selectedItems.length
  newActivity.value.selectedItems = newActivity.value.selectedItems.filter(i => !i.isJsMaterial)
  const removed = before - newActivity.value.selectedItems.length
  if (removed > 0) {
    toast.info('J&S-Material wurde entfernt, da dieser Typ nur bei Event/Camp erlaubt ist.')
  }
})

watch(canUseDetailJsMaterialSource, (allowed) => {
  if (allowed) return
  detailMaterialSource.value = 'internal'
  if (detailMatSearch.value.trim().length >= 2) {
    searchDetailMaterials()
  }
})

watch(() => selectedActivity.value?.id, () => {
  detailMaterialSource.value = 'internal'
  detailMatSearchResults.value = []
  detailMatActiveIndex.value = -1
})

// Suggestions neu laden, wenn Gruppe sich ändert (Quick-Modus)
watch(() => newActivity.value.groupId, (groupId) => {
  if (groupId && wizardMode.value === 'quick') {
    loadMaterialSuggestions()
  }
})

// === Neue Wizard-Advance-Funktionen (4-Step Wizard) ===
function wizardAdvanceFromGrunddaten() {
  // Validate: Name muss gesetzt sein
  if (!newActivity.value.name.trim()) return
  // Bei Camp: Gruppe muss gewählt sein
  if (selectedTemplate.value === 'camp' && !newActivity.value.groupId) return
  // Bei Extern: Kundenname muss gesetzt sein
  if (selectedTemplate.value === 'external' && !newActivity.value.customerName?.trim()) return
  
  wizardStep.value = WIZARD_STEP_ZEITRAUM
  autoSaveDraft()
}

function wizardAdvanceFromZeitraum() {
  if (!isStep3Complete.value) return
  wizardStep.value = WIZARD_STEP_MATERIAL
  autoSaveDraft()
}

function wizardAdvanceFromMaterial() {
  wizardStep.value = WIZARD_STEP_SUMMARY
  autoSaveDraft()
}

// Wizard Step 1 valid check
const isWizardStep1Valid = computed(() => {
  if (!newActivity.value.name.trim()) return false
  if (selectedTemplate.value === 'camp' && !newActivity.value.groupId) return false
  if (selectedTemplate.value === 'external' && !newActivity.value.customerName?.trim()) return false
  return true
})

// === Material-Vorschläge laden ===
async function loadMaterialSuggestions() {
  if (!departmentId.value) return
  isLoadingSuggestions.value = true
  try {
    // Wochentag aus dem gewählten Datum berechnen (ISO: 1=Mo..7=So)
    let dayOfWeek = 0
    if (selectedTemplate.value === 'activity' && activityDate.value) {
      const d = new Date(activityDate.value)
      dayOfWeek = d.getDay() === 0 ? 7 : d.getDay() // JS: 0=So -> ISO: 7=So
    } else if (newActivity.value.usageStart) {
      const d = new Date(newActivity.value.usageStart)
      dayOfWeek = d.getDay() === 0 ? 7 : d.getDay()
    }

    const response = await getMaterialSuggestions({
      department_id: departmentId.value,
      group_id: newActivity.value.groupId || undefined,
      day_of_week: dayOfWeek || undefined,
      type: selectedTemplate.value || 'activity',
      limit: 8,
      min_usage: 2,
    })

    materialSuggestions.value = response.suggestions

    // Label generieren
    if (response.suggestions.length > 0) {
      const first = response.suggestions[0]
      const groupName = getSelectedGroupName()
      const dayNames = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']
      if (first.source === 'group_weekday' && dayOfWeek) {
        suggestionsLabel.value = `${dayNames[dayOfWeek]}s üblich${groupName ? ' für ' + groupName : ''}`
      } else if (first.source === 'group') {
        suggestionsLabel.value = `Häufig${groupName ? ' für ' + groupName : ''}`
      } else {
        suggestionsLabel.value = 'Zuletzt von dir verwendet'
      }
    } else {
      suggestionsLabel.value = ''
    }
  } catch (e) {
    console.warn('Material-Vorschläge konnten nicht geladen werden:', e)
    materialSuggestions.value = []
    suggestionsLabel.value = ''
  } finally {
    isLoadingSuggestions.value = false
  }
}

// Suggestion als Material hinzufügen
function addSuggestion(suggestion: MaterialSuggestion) {
  // Prüfen ob schon hinzugefügt
  if (isMaterialSelected(suggestion.material_item_id)) {
    // Bereits drin → entfernen
    removeMaterial(suggestion.material_item_id)
    return
  }
  // Als Material hinzufügen mit durchschnittlicher Menge
  newActivity.value.selectedItems.push({
    materialItemId: suggestion.material_item_id,
    materialName: suggestion.name,
    quantity: suggestion.avg_quantity,
    availableQuantity: 999, // Wird beim Submit nochmal geprüft
    priority: 'normal',
    isConsumable: false,
    unitPrice: 0,
    lineTotal: 0,
    priceType: 'free',
    salePrice: null,
    rentalPriceDay: null,
    rentalPriceWeek: null,
    rentalPriceMonth: null,
  } as any)
  recalculateAllPrices()
}

// === Adress-Picker Funktionen ===
async function loadDepartmentAddresses() {
  isLoadingAddresses.value = true
  try {
    const isCampTemplate = selectedTemplate.value === 'camp'
    const response = await getAddresses(departmentId.value, isCampTemplate ? 'event' : undefined)
    const addresses = response.addresses || []

    if (isCampTemplate) {
      departmentAddresses.value = [...addresses]
        .sort((a, b) => getAddressSortTimestamp(b) - getAddressSortTimestamp(a))
        .slice(0, 3)
      return
    }

    departmentAddresses.value = addresses
  } catch (err) {
    console.error('Fehler beim Laden der Adressen:', err)
    departmentAddresses.value = []
  } finally {
    isLoadingAddresses.value = false
  }
}

interface AddressWithTimestamps extends Address {
  created_at?: string | null
  updated_at?: string | null
}

function getAddressSortTimestamp(address: AddressWithTimestamps): number {
  const rawTimestamp = address.created_at || address.updated_at
  if (!rawTimestamp) return 0
  const parsed = new Date(rawTimestamp).getTime()
  return Number.isNaN(parsed) ? 0 : parsed
}

const filteredAddresses = computed(() => {
  if (!addressSearchQuery.value) return departmentAddresses.value
  const q = addressSearchQuery.value.toLowerCase()
  return departmentAddresses.value.filter(a =>
    a.full_address?.toLowerCase().includes(q) ||
    a.name?.toLowerCase().includes(q) ||
    a.company?.toLowerCase().includes(q) ||
    a.street?.toLowerCase().includes(q) ||
    a.city?.toLowerCase().includes(q)
  )
})

const displayedDepartmentInviteResults = computed(() => departmentInviteResults.value.slice(0, 10))
const departmentInviteActionLabel = computed(() => selectedTemplate.value === 'camp' ? 'Zum Camp einladen' : 'Zum Anlass einladen')

function selectAddress(address: Address) {
  selectedAddress.value = address
  newActivity.value.addressId = address.id
  addressSearchQuery.value = ''
}

function clearSelectedAddress() {
  selectedAddress.value = null
  newActivity.value.addressId = null
}

function openAddressModal(type?: 'event' | 'customer') {
  addressModalDefaultType.value = type || (selectedTemplate.value === 'external' ? 'customer' : 'event')
  showAddressModal.value = true
}

function toggleDepartmentInvitePanel() {
  showDepartmentInvitePanel.value = !showDepartmentInvitePanel.value
  if (!showDepartmentInvitePanel.value) {
    departmentInviteQuery.value = ''
    departmentInviteResults.value = []
  }
}

function addInvitedDepartment(department: DepartmentSearchResult) {
  if (invitedDepartments.value.some((entry) => entry.id === department.id)) {
    toast.info('Department ist bereits in der Einladungsliste.')
    return
  }
  invitedDepartments.value.push(department)
  toast.success(`${department.name} zur Einladungsliste hinzugefuegt.`)
}

function removeInvitedDepartment(departmentIdToRemove: string) {
  invitedDepartments.value = invitedDepartments.value.filter((entry) => entry.id !== departmentIdToRemove)
}

function getMaterialSourceLabel(item: any): string {
  if (item?.sourceDepartmentName) {
    if (item.sourceDepartmentId && item.sourceDepartmentId === departmentId.value) {
      return 'Eigenes Department'
    }
    return item.sourceDepartmentName
  }
  if (item?.isJsMaterial) return 'J&S'
  if (item?.externalSource) return item.externalSource
  return 'Eigenes Department'
}

function onAddressCreated(savedAddress?: Address) {
  showAddressModal.value = false
  if (selectedTemplate.value === 'external' && addressModalDefaultType.value === 'customer' && savedAddress) {
    const suggestedName = savedAddress.name || savedAddress.company || ''
    if (suggestedName) {
      newActivity.value.customerName = suggestedName
    }
  }
  // Adressen neu laden, um die neue Adresse in der Liste zu haben
  loadDepartmentAddresses()
}

// === Auto-fill: Material Abholen/Zurückbringen (Camp/Event/Extern) ===
watch(() => newActivity.value.usageStart, (newStart) => {
  if (!newStart || selectedTemplate.value === 'activity') return
  // Material abholen = X Tage vor Event-Start (aus Konfig)
  const start = new Date(newStart)
  const pickup = new Date(start)
  pickup.setDate(pickup.getDate() - activityDefaults.value.campMaterialLeadDays)
  newActivity.value.planningStart = pickup.toISOString().slice(0, 16)
})

watch(() => newActivity.value.usageEnd, (newEnd) => {
  if (!newEnd || selectedTemplate.value === 'activity') return
  // Material zurückbringen = X Tage nach Event-Ende (aus Konfig)
  const end = new Date(newEnd)
  const returnDate = new Date(end)
  returnDate.setDate(returnDate.getDate() + activityDefaults.value.campMaterialLagDays)
  newActivity.value.planningEnd = returnDate.toISOString().slice(0, 16)
})

// === Zeitraum-Titel pro Typ ===
const zeitraumTitle = computed(() => {
  const titles: Record<string, string> = {
    activity: 'Wann ist die Aktivität?',
    camp: 'Lager-Zeitraum & Material',
    event: 'Event-Zeitraum & Material',
    external: 'Zeitraum & Material',
  }
  return titles[selectedTemplate.value] || 'Zeitraum'
})

// === Dauer-Anzeige ===
const activityDuration = computed(() => {
  if (!activityDate.value || !activityTimeStart.value || !activityTimeEnd.value) return ''
  const start = new Date(`${activityDate.value}T${activityTimeStart.value}`)
  const end = new Date(`${activityDate.value}T${activityTimeEnd.value}`)
  const diffHours = Math.round((end.getTime() - start.getTime()) / (1000 * 60 * 60) * 10) / 10
  if (diffHours <= 0) return '⚠️ Ende muss nach Start liegen'
  const dayName = start.toLocaleDateString('de-CH', { weekday: 'long' })
  return `${dayName}, ${diffHours} Stunden`
})

const eventDuration = computed(() => {
  if (!newActivity.value.usageStart || !newActivity.value.usageEnd) return ''
  const start = new Date(newActivity.value.usageStart)
  const end = new Date(newActivity.value.usageEnd)
  const diffMs = end.getTime() - start.getTime()
  if (diffMs <= 0) return '⚠️ Ende muss nach Start liegen'
  const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24))
  return `${diffDays} Tag${diffDays !== 1 ? 'e' : ''}`
})

// Anstehend = Status nicht abgeschlossen/storniert UND Nutzungszeitraum noch nicht vorbei (usage_end >= heute)
function isUpcomingActivity(a: Activity): boolean {
  if (!['draft', 'submitted', 'approved', 'packing', 'packed', 'issued', 'returned'].includes(a.status)) return false
  if (!a.usageEnd) return true // Kein Enddatum = Draft oder noch offen
  const endDate = new Date(a.usageEnd)
  const todayStart = new Date()
  todayStart.setHours(0, 0, 0, 0)
  return endDate >= todayStart
}

// Vergangen = abgeschlossen ODER Nutzungszeitraum vorbei (usage_end < heute)
function isPastActivity(a: Activity): boolean {
  if (a.status === 'cancelled') return false
  if (a.status === 'completed') return true
  if (!a.usageEnd) return false
  const endDate = new Date(a.usageEnd)
  const todayStart = new Date()
  todayStart.setHours(0, 0, 0, 0)
  return endDate < todayStart
}

// Tabs
const tabs = computed(() => [
  { key: 'upcoming', label: 'Anstehend', count: activities.value.filter(isUpcomingActivity).length },
  { key: 'past', label: 'Vergangen', count: activities.value.filter(isPastActivity).length },
  { key: 'all', label: 'Alle', count: activities.value.length },
  { key: 'cancelled', label: 'Storniert', count: activities.value.filter(a => a.status === 'cancelled').length },
])

// 4 Typen wie gewünscht
const activityTemplates = [
  {
    type: 'activity',
    name: 'Aktivität',
    description: 'Eintägige Aktivität mit Materialausleihe',
    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>'
  },
  {
    type: 'camp',
    name: 'Lager / Camps',
    description: 'Pfadilager, Klassenlager, Ferienlager etc.',
    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21l9-18 9 18H3z"/><path d="M12 21V9"/></svg>'
  },
  {
    type: 'event',
    name: 'Anlässe / Events',
    description: 'Veranstaltungen, Feste, Konzerte etc.',
    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'
  },
  {
    type: 'external',
    name: 'Extern',
    description: 'Vermietung an externe Kunden / Dritte',
    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>'
  },
]

// === Validation: Was fehlt noch? ===
const missingSteps = computed(() => {
  const missing: string[] = []
  if (!selectedTemplate.value) {
    missing.push('Typ auswählen')
  }
  if (!newActivity.value.name.trim()) {
    missing.push('Name eingeben')
  }
  // Datum-Validierung pro Typ
  if (selectedTemplate.value === 'activity') {
    if (!activityDate.value) missing.push('Datum auswählen')
    if (!activityTimeStart.value || !activityTimeEnd.value) missing.push('Uhrzeit eingeben')
  } else if (selectedTemplate.value) {
    if (!newActivity.value.usageStart) missing.push('Startdatum auswählen')
    if (!newActivity.value.usageEnd) missing.push('Enddatum auswählen')
    if (!newActivity.value.planningStart) missing.push('Material-Abholdatum auswählen')
    if (!newActivity.value.planningEnd) missing.push('Material-Rückgabedatum auswählen')
  }
  // Gruppe Pflicht (bei activity + camp immer, bei event/external optional)
  if ((selectedTemplate.value === 'activity' || selectedTemplate.value === 'camp') && !newActivity.value.groupId) {
    missing.push('Gruppe auswählen')
  }
  // Material Pflicht (immer)
  if (selectedTemplate.value && newActivity.value.selectedItems.length === 0) {
    missing.push('Material hinzufügen')
  }
  // Extern: Kunde Pflicht
  if (selectedTemplate.value === 'external' && !newActivity.value.customerName?.trim()) {
    missing.push('Kundenname eingeben')
  }
  return missing
})

const canSubmit = computed(() => missingSteps.value.length === 0)

// Filtered + Sorted activities
const filteredActivities = computed(() => {
  let result = activities.value

  // Tab-Filter
  if (activeTab.value === 'upcoming') {
    result = result.filter(isUpcomingActivity)
  } else if (activeTab.value === 'past') {
    result = result.filter(isPastActivity)
  } else if (activeTab.value === 'cancelled') {
    result = result.filter(a => a.status === 'cancelled')
  }

  // Typ-Filter
  if (activeTypeFilter.value) {
    result = result.filter(a => a.type === activeTypeFilter.value)
  }

  // Text-Suche
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(a => 
      a.name.toLowerCase().includes(q) || 
      a.customerName?.toLowerCase().includes(q) ||
      a.groupName?.toLowerCase().includes(q) ||
      a.no?.toLowerCase().includes(q)
    )
  }

  // Sortierung
  result = [...result].sort((a, b) => {
    let cmp = 0
    // Vergangen: immer nach Datum absteigend (heute/neueste zuerst)
    if (activeTab.value === 'past') {
      const da = new Date(a.usageEnd || a.usageStart || 0).getTime()
      const db = new Date(b.usageEnd || b.usageStart || 0).getTime()
      return db - da // absteigend = neueste zuerst
    }
    if (sortField.value === 'name') {
      cmp = a.name.localeCompare(b.name, 'de')
    } else if (sortField.value === 'date') {
      const da = a.usageStart ? new Date(a.usageStart).getTime() : 0
      const db = b.usageStart ? new Date(b.usageStart).getTime() : 0
      cmp = da - db
    } else if (sortField.value === 'price') {
      cmp = (a.totalPrice || 0) - (b.totalPrice || 0)
    }
    return sortDir.value === 'desc' ? -cmp : cmp
  })

  return result
})

// Helpers
function getTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    activity: 'Aktivität',
    camp: 'Lager',
    event: 'Event',
    external: 'Extern',
  }
  return labels[type] || type
}

function getStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    draft: 'Entwurf',
    submitted: 'Eingereicht',
    approved: 'Bestätigt',
    packing: 'Wird gepackt',
    packed: 'Gepackt',
    issued: 'Ausgegeben',
    returned: 'Retour',
    completed: 'Abgeschlossen',
    cancelled: 'Storniert',
    // Legacy (Abwärtskompatibel)
    confirmed: 'Bestätigt',
    active: 'Aktiv',
  }
  return labels[status] || status
}

function getConditionLabel(condition: string): string {
  const labels: Record<string, string> = {
    ok: 'OK',
    leicht_beschaedigt: 'Leicht beschädigt',
    beschaedigt: 'Beschädigt',
    defekt: 'Defekt',
  }
  return labels[condition] || condition
}

function getNamePlaceholder(): string {
  const placeholders: Record<string, string> = {
    activity: 'z.B. Aktivität Mausjagdt...',
    camp: 'z.B. Pfingstlager 2026, Sommerlager Bern...',
    event: 'z.B. Sommerfest 2026, Konzert im Park...',
    external: 'z.B. Hochzeit Müller, Firmenanlass Weber AG...',
  }
  return placeholders[selectedTemplate.value] || 'Name eingeben...'
}

function getSelectedTemplateIcon(): string {
  const tmpl = activityTemplates.find(t => t.type === selectedTemplate.value)
  return tmpl?.icon || ''
}

function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatDateTime(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const tz = authStore.departmentTimezone || 'Europe/Zurich'
  return d.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: tz }) + 
    ' ' + d.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit', timeZone: tz })
}

function formatDateTimeShort(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const tz = authStore.departmentTimezone || 'Europe/Zurich'
  return d.toLocaleDateString('de-CH', { day: '2-digit', month: 'short', timeZone: tz }) + 
    ' ' + d.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit', timeZone: tz })
}

// Mit Sekunden für Meldungen (damit mehrere zur gleichen Minute unterscheidbar sind)
function formatDateTimeWithSeconds(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const tz = authStore.departmentTimezone || 'Europe/Zurich'
  return d.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: tz }) + 
    ' ' + d.toLocaleTimeString('de-CH', { hour: '2-digit', minute: '2-digit', second: '2-digit', timeZone: tz })
}

function resetWizard() {
  selectedTemplate.value = ''
  activeStep.value = 1
  wizardStep.value = 1
  draftId.value = null
  lastSavedAt.value = null
  activityDate.value = ''
  activityTimeStart.value = activityDefaults.value.defaultTimeStart
  activityTimeEnd.value = activityDefaults.value.defaultTimeEnd
  selectedAddress.value = null
  addressSearchQuery.value = ''
  groupSearchQuery.value = ''
  myGroups.value = []
  showQuickMaterialSearch.value = false
  showQuickNotes.value = false
  showGroupDropdown.value = false
  showGroupDropdownWizard.value = false
  showDepartmentInvitePanel.value = false
  departmentInviteQuery.value = ''
  departmentInviteResults.value = []
  invitedDepartments.value = []
  if (departmentInviteSearchTimer) {
    clearTimeout(departmentInviteSearchTimer)
    departmentInviteSearchTimer = null
  }
  materialSuggestions.value = []
  suggestionsLabel.value = ''
  materialSource.value = 'internal'
  showJsOrderForm.value = false
  resetJsOrderForm()
  newActivity.value = {
    name: '',
    groupId: null,
    usageStart: '',
    usageEnd: '',
    planningStart: '',
    planningEnd: '',
    customerName: '',
    customerEmail: '',
    customerPhone: '',
    addressId: null,
    notes: '',
    pricingMode: 'item_price',
    setPrice: null,
    selectedItems: [],
  }
}

function openNewWizard() {
  showNewDialog.value = true
  // Aktivität als Standard vorauswählen
  nextTick(() => {
    selectTemplate('activity')
  })
}

function handleCloseWizard(returnToDashboard = true) {
  showNewDialog.value = false
  resetWizard()
  if (returnToDashboard && route.query.from === 'dashboard' && departmentId.value) {
    router.replace(`/${departmentId.value}`)
  }
}

// Keyboard: Enter = nächster Schritt oder Submit
function handleWizardEnter(e: KeyboardEvent) {
  // Nicht in Textareas abfangen
  if ((e.target as HTMLElement)?.tagName === 'TEXTAREA') return
  // Nicht in Material-Suchfeld (da Enter dort das Dropdown steuert)
  if ((e.target as HTMLElement)?.classList.contains('mat-search-input')) return

  if (wizardMode.value === 'quick') {
    // Quick-Modus: direkt erstellen wenn alles bereit
    if (canSubmit.value) {
      createActivity()
    }
  } else {
    // Wizard-Modus: Step-abhängig
    if (wizardStep.value === WIZARD_STEP_GRUNDDATEN && isWizardStep1Valid.value) {
      wizardAdvanceFromGrunddaten()
    } else if (wizardStep.value === WIZARD_STEP_ZEITRAUM && isStep3Complete.value) {
      wizardAdvanceFromZeitraum()
    } else if (wizardStep.value === WIZARD_STEP_MATERIAL) {
      wizardAdvanceFromMaterial()
    } else if (wizardStep.value === WIZARD_STEP_SUMMARY && canSubmit.value) {
      createActivity()
    }
  }
}

function selectActivity(activity: Activity) {
  selectedActivity.value = activity
}

// Bestimmt den initialen Tab basierend auf dem Activity-Status
const PACK_STATUSES = ['packing', 'packed', 'issued', 'returned']

function getInitialTab(status: string): string {
  if (PACK_STATUSES.includes(status)) return 'packlist'
  return 'overview'
}

async function openActivity(activity: Activity) {
  selectedActivity.value = activity
  activityDetail.value = null
  const initialTab = getInitialTab(activity.status)
  activeDetailTab.value = initialTab

  detailTabsStore.addOrUpdateTab({
    id: activity.id,
    type: 'activity',
    label: activity.name || activity.no || `Aktivität ${activity.id}`,
    departmentId: departmentId.value,
    path: `/${departmentId.value}/activities/${activity.id}`,
  })
  detailItems.value = []
  activityHistory.value = []
  packItems.value = []
  issueReports.value = []
  returnItems.value = []
  workshopCostTickets.value = []
  availableTransitions.value = []
  isEditingDraft.value = false
  showDateChangeWarning.value = false
  showDetail.value = true

  // URL aktualisieren
  router.replace(`/${departmentId.value}/activities/${activity.id}/${initialTab}`)

  // Detail-Daten + Transitions parallel laden
  try {
    const [detailResponse] = await Promise.all([
      apiClient.get(`/api/activities/${activity.id}`),
      loadTransitions(),
    ])
    activityDetail.value = detailResponse.data
  } catch (err) {
    console.error('Fehler beim Laden der Detail-Daten:', err)
  }

  // Tab-Daten direkt laden (Watcher wird nicht getriggert wenn Tab gleich bleibt)
  if (initialTab === 'packlist') {
    activePackStage.value = autoPackStage.value
    await loadPackItems()
    initMoveQtyInputs()
  } else if (initialTab === 'material') {
    await reloadDetailItems()
  }
}

function closeDetail() {
  // Tab bleibt offen, Änderungen bleiben erhalten (keep-alive)
  showDetail.value = false
  selectedActivity.value = null
  activityDetail.value = null
  isEditingDraft.value = false
  showDateChangeWarning.value = false
  router.replace(`/${departmentId.value}/activities`)
}

function switchDetailTab(tabKey: string) {
  activeDetailTab.value = tabKey
  // URL aktualisieren mit aktuellem Tab
  if (selectedActivity.value) {
    router.replace(`/${departmentId.value}/activities/${selectedActivity.value.id}/${tabKey}`)
  }
}

// Route-Params überwachen: Detail-Ansicht aus URL wiederherstellen
watch(
  () => route.params,
  async (params) => {
    const activityId = params.activityId as string | undefined
    const tab = params.tab as string | undefined

    if (activityId && !showDetail.value) {
      // Aktivität aus URL öffnen (Direktnavigation / Browser-Refresh)
      const activity = activities.value.find(a => a.id === activityId)
      if (activity) {
        await openActivity(activity)
        if (tab) {
          activeDetailTab.value = tab
        }
      } else {
        // Aktivität noch nicht geladen → versuche direkt aus API zu laden
        try {
          const response = await apiClient.get(`/api/activities/${activityId}`)
          if (response.data) {
            const act: Activity = {
              id: response.data.id,
              no: response.data.no,
              name: response.data.name,
              type: response.data.type,
              status: response.data.status,
              usageStart: response.data.usage_start,
              usageEnd: response.data.usage_end,
              groupName: response.data.group_name || null,
              itemCount: response.data.item_count || 0,
              createdAt: response.data.created_at,
              updatedAt: response.data.updated_at,
            }
            selectedActivity.value = act
            activityDetail.value = response.data
            activeDetailTab.value = tab || getInitialTab(act.status)
            detailItems.value = []
            activityHistory.value = []
            packItems.value = []
            issueReports.value = []
            returnItems.value = []
            workshopCostTickets.value = []
            availableTransitions.value = []
            showDetail.value = true

            detailTabsStore.addOrUpdateTab({
              id: act.id,
              type: 'activity',
              label: act.name || act.no || `Aktivität ${act.id}`,
              departmentId: departmentId.value,
              path: `/${departmentId.value}/activities/${act.id}`,
            })

            await loadTransitions()

            // Tab-Daten direkt laden
            const activeTab = activeDetailTab.value
            if (activeTab === 'packlist') {
              activePackStage.value = autoPackStage.value
              await loadPackItems()
              initMoveQtyInputs()
            } else if (activeTab === 'material') {
              await reloadDetailItems()
            }
          }
        } catch (err) {
          console.error('Aktivität aus URL nicht gefunden:', err)
          router.replace(`/${departmentId.value}/activities`)
        }
      }
    } else if (activityId && showDetail.value && tab && tab !== activeDetailTab.value) {
      // Nur Tab wechseln (z.B. Browser back/forward)
      activeDetailTab.value = tab
    } else if (!activityId && showDetail.value) {
      // URL zeigt Liste an, aber Detail ist offen → schliessen (Tab bleibt offen)
      showDetail.value = false
      selectedActivity.value = null
      activityDetail.value = null
    }
  },
  { immediate: true }
)

// Query ?new=1: Wizard direkt öffnen (z.B. vom Dashboard)
watch(
  () => route.query.new,
  (val) => {
    if (val === '1' && !showNewDialog.value) {
      openNewWizard()
      const q = { ...route.query }
      delete q.new
      router.replace({ path: route.path, query: q })
    }
  },
  { immediate: true }
)

// Query ?q=: Header-Suche: Suchbegriff übernehmen
watch(
  () => route.query.q,
  (q) => {
    if (route.path.includes('/activities')) {
      searchQuery.value = (q as string) ?? ''
    }
  },
  { immediate: true }
)

// ═══════════════════════════════════════════════
// DETAIL: MATERIAL HINZUFÜGEN (MW+ Rollen)
// ═══════════════════════════════════════════════

function onDetailMatSearchInput() {
  if (detailMatSearchTimer) clearTimeout(detailMatSearchTimer)

  if (detailMatSearch.value.length < 2) {
    detailMatSearchResults.value = []
    showDetailMatDropdown.value = false
    detailMatActiveIndex.value = -1
    return
  }

  showDetailMatDropdown.value = true
  detailMatActiveIndex.value = -1
  detailMatSearchTimer = setTimeout(() => {
    searchDetailMaterials()
  }, 300)
}

async function searchDetailMaterials() {
  if (!selectedActivity.value) return
  const query = detailMatSearch.value.trim()
  if (query.length < 2) return

  isDetailMatLoading.value = true
  try {
    detailMatSearchResults.value = await detailMaterialLookupFetcher(query)
    showDetailMatDropdown.value = true
    detailMatActiveIndex.value = detailMatSearchResults.value.length > 0 ? 0 : -1
  } catch (err) {
    console.error('Fehler beim Suchen der Materialien:', err)
    detailMatSearchResults.value = []
    detailMatActiveIndex.value = -1
  } finally {
    isDetailMatLoading.value = false
  }
}

function moveDetailMatActive(step: 1 | -1) {
  if (!detailMatSearchResults.value.length) return
  if (detailMatActiveIndex.value < 0) {
    detailMatActiveIndex.value = 0
    return
  }
  const len = detailMatSearchResults.value.length
  detailMatActiveIndex.value = (detailMatActiveIndex.value + step + len) % len
}

function acceptDetailMatSelection() {
  if (!detailMatSearchResults.value.length) return
  const index = detailMatActiveIndex.value >= 0 ? detailMatActiveIndex.value : 0
  const selected = detailMatSearchResults.value[index]
  if (!selected || selected.availableForPeriod <= 0) return
  addDetailMaterial(selected, 1)
}

function handleDetailLookupSelect(selected: any) {
  if (!selected || selected.availableForPeriod <= 0) return
  addDetailMaterial(selected, 1)
}

async function addDetailMaterial(mat: any, qty: number) {
  if (!selectedActivity.value || mat.availableForPeriod <= 0) return
  const actualQty = Math.min(qty, mat.availableForPeriod)

  try {
    await apiClient.post(`/api/activities/${selectedActivity.value.id}/items`, {
      material_item_id: mat.materialItemId,
      quantity: actualQty,
      priority: 'normal',
    })
    // Materialien neu laden
    await reloadDetailItems()
    // Dropdown Suche leeren
    detailMatSearch.value = ''
    detailMatSearchResults.value = []
    showDetailMatDropdown.value = false
    detailMatActiveIndex.value = -1
  } catch (err: any) {
    toast.error('Fehler beim Hinzufügen: ' + (err.response?.data?.error || err.message))
  }
}

async function changeDetailMaterialQty(item: any, delta: number) {
  if (!selectedActivity.value) return
  const newQty = item.quantity + delta
  if (newQty <= 0) {
    await removeDetailMaterial(item)
    return
  }

  // Bei Erhöhung: Verfügbarkeit prüfen
  if (delta > 0) {
    try {
      const startDate = activityDetail.value?.planning_start || activityDetail.value?.usage_start
      const endDate = activityDetail.value?.planning_end || activityDetail.value?.usage_end
      const params: any = {
        departmentId: departmentId.value,
        activityId: selectedActivity.value.id,
        search: item.materialName,
        limit: 1,
        excludeActivityId: selectedActivity.value.id,
      }
      if (startDate && endDate) {
        params.startDate = startDate
        params.endDate = endDate
      }
      const response = await apiClient.get('/api/materials/available-for-period', { params })
      const found = (response.data || []).find((m: any) => m.materialItemId === item.materialItemId)
      const available = found ? found.availableForPeriod : 0

      if (newQty > item.quantity + available) {
        toast.warning(`Nicht genug verfügbar! Aktuell verfügbar: ${available} Stk. (zusätzlich zur bestehenden Bestellung)`)
        return
      }
    } catch (err) {
      console.error('Verfügbarkeitsprüfung fehlgeschlagen:', err)
      // Bei Fehler trotzdem erlauben, Backend validiert ggf. nochmal
    }
  }

  try {
    // Sync via PUT: aktuelle Items mit geänderter Menge
    const items = detailItems.value.map(i => ({
      material_item_id: i.materialItemId,
      quantity: i.id === item.id ? newQty : i.quantity,
      priority: 'normal',
    }))
    await apiClient.put(`/api/activities/${selectedActivity.value.id}/items`, { items })
    await reloadDetailItems()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

async function removeDetailMaterial(item: any) {
  if (!selectedActivity.value) return
  const ok = await confirm.confirm({
    title: 'Material entfernen?',
    message: `"${item.materialName}" wirklich aus der Aktivität entfernen?`,
    confirmText: 'Entfernen',
    cancelText: 'Abbrechen',
    variant: 'warning',
  })
  if (!ok) return

  try {
    // Sync via PUT: alle Items ausser dem zu entfernenden
    const items = detailItems.value
      .filter(i => i.id !== item.id)
      .map(i => ({
        material_item_id: i.materialItemId,
        quantity: i.quantity,
        priority: 'normal',
      }))
    await apiClient.put(`/api/activities/${selectedActivity.value.id}/items`, { items })
    await reloadDetailItems()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

function mapDetailItemResponse(item: any) {
  return {
    id: item.id,
    materialItemId: item.material_item_id,
    materialName: item.material_name || item.materialName || 'Unbekannt',
    sourceDepartmentId: item.source_department_id || item.sourceDepartmentId || null,
    sourceDepartmentName: item.source_department_name || item.sourceDepartmentName || null,
    quantity: item.quantity,
    unitPrice: item.unit_price != null ? Number(item.unit_price) : null,
    lineTotal: item.line_total != null ? Number(item.line_total) : null,
    priceType: item.price_type || 'free',
    packSize: item.pack_size || null,
    packUnit: item.pack_unit || null,
    isConsumable: item.is_consumable || false,
    salePrice: item.sale_price || null,
    isJsMaterial: item.is_js_material || false,
    externalSource: item.external_source || null,
  }
}

async function reloadDetailItems() {
  if (!selectedActivity.value) return
  isLoadingDetailItems.value = true
  try {
    const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/items`)
    detailItems.value = (response.data || []).map((item: any) => mapDetailItemResponse(item))
  } catch (err) {
    console.error('Fehler beim Laden der Materialien:', err)
  } finally {
    isLoadingDetailItems.value = false
  }
}

async function loadWorkshopCostsForActivity() {
  if (!selectedActivity.value) return
  isLoadingWorkshopCosts.value = true
  try {
    workshopCostTickets.value = await getWorkshopTickets(departmentId.value, {
      activity_id: selectedActivity.value.id,
    })
  } catch (err) {
    console.error('Fehler beim Laden der Werkstattkosten:', err)
    workshopCostTickets.value = []
  } finally {
    isLoadingWorkshopCosts.value = false
  }
}

// Detail-Tab wechseln → Daten nachladen
watch(activeDetailTab, async (tab) => {
  if (!selectedActivity.value) return

  if (tab === 'material' && detailItems.value.length === 0) {
    isLoadingDetailItems.value = true
    try {
      const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/items`)
      detailItems.value = (response.data || []).map((item: any) => mapDetailItemResponse(item))
    } catch (err) {
      console.error('Fehler beim Laden der Materialien:', err)
    } finally {
      isLoadingDetailItems.value = false
    }
  }

  if (tab === 'history' && activityHistory.value.length === 0) {
    isLoadingHistory.value = true
    try {
      const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/history`)
      activityHistory.value = (response.data || []).map((h: any) => ({
        id: h.id,
        action: h.action,
        changes: h.changes,
        createdAt: h.created_at,
        userId: h.user_id,
      }))
    } catch (err) {
      console.error('Fehler beim Laden des Verlaufs:', err)
    } finally {
      isLoadingHistory.value = false
    }
  }

  // Packliste laden + Stufe auto-setzen
  if (tab === 'packlist') {
    activePackStage.value = autoPackStage.value
    if (packItems.value.length === 0) {
      await loadPackItems()
    }
    initMoveQtyInputs()
  }

  // Reparaturen / Verluste laden
  if (tab === 'issues') {
    if (issueReports.value.length === 0) await loadIssues()
    await loadWorkshopCostsForActivity()
  }

  // Verbrauchsmaterial laden (Issues + Material-Items benötigt)
  if (tab === 'consumables') {
    if (issueReports.value.length === 0) await loadIssues()
    if (detailItems.value.length === 0) {
      const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/items`)
      detailItems.value = (response.data || []).map((item: any) => mapDetailItemResponse(item))
    }
    // Qty-Inputs initialisieren
    for (const ci of consumableItems.value) {
      if (!consumableQtyInputs.value[ci.materialItemId]) {
        consumableQtyInputs.value[ci.materialItemId] = 1
      }
    }
  }

  // Kosten laden (Material-Items + Issues benötigt)
  if (tab === 'costs') {
    if (detailItems.value.length === 0) await reloadDetailItems()
    if (issueReports.value.length === 0) await loadIssues()
    if (workshopCostTickets.value.length === 0) await loadWorkshopCostsForActivity()
  }

  // Rückgabe laden
  if (tab === 'returns' && returnItems.value.length === 0) {
    await loadReturnItems()
  }
})

// ═══════════════════════════════════════════════
// STATUS-ÄNDERUNGEN & WORKFLOW
// ═══════════════════════════════════════════════

// Transitions für den aktuellen User laden
async function loadTransitions() {
  if (!selectedActivity.value) return
  try {
    const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/transitions`)
    availableTransitions.value = (response.data?.transitions || []).map((t: any) => ({
      status: t.status,
      label: t.label,
      allowed: t.allowed,
      reason: t.reason,
    }))
  } catch (err) {
    console.error('Fehler beim Laden der Transitions:', err)
    availableTransitions.value = []
  }
}

async function changeActivityStatus(newStatus: string, comment?: string) {
  if (!selectedActivity.value) return
  try {
    const payload: any = { status: newStatus }
    if (comment) payload.comment = comment

    await apiClient.patch(`/api/activities/${selectedActivity.value.id}/status`, payload)
    selectedActivity.value.status = newStatus as Activity['status']
    
    // Transitions + Liste neu laden
    await Promise.all([loadActivities(), loadTransitions()])

    // Nach Wechsel zu packing/packed/issued/returned → automatisch Packliste öffnen + Stufe weiterschalten
    if (PACK_STATUSES.includes(newStatus)) {
      switchDetailTab('packlist')
      packItems.value = [] // Reset damit sie neu geladen werden
      await loadPackItems()
      // Passenden Stufen-Tab aktivieren
      activePackStage.value = autoPackStage.value
      initMoveQtyInputs()
    }
  } catch (err: any) {
    const apiError = err.response?.data
    if (apiError?.code === 'activity_completion_blocked') {
      completionBlockers.value = apiError.blockers || null
      showCompletionBlockedModal.value = true
      toast.warning(apiError.error || 'Aktivität kann noch nicht abgeschlossen werden')
      return
    }
    toast.error('Fehler: ' + (apiError?.error || err.message))
  }
}

function closeCompletionBlockedModal() {
  showCompletionBlockedModal.value = false
}

function openWorkshopFromCompletionBlocker(ticketId?: string) {
  closeCompletionBlockedModal()
  if (ticketId) {
    router.push({ path: `/${departmentId.value}/workshop`, query: { ticket: ticketId } })
    return
  }
  router.push(`/${departmentId.value}/workshop`)
}

// Prüft ob ein Zielstatus ein Pack-Workflow-Schritt ist (Aktion gehört in den Auftrag-Status-Tab)
function isPackWorkflowTarget(targetStatus: string): boolean {
  return ['packed', 'issued', 'returned'].includes(targetStatus)
}

// Navigiert zum Auftrag-Status-Tab und setzt den passenden Stufen-Tab
function navigateToPackTab(targetStatus: string) {
  switchDetailTab('packlist')
  // Passende Stufe aktivieren
  if (targetStatus === 'packed') {
    activePackStage.value = 'confirmed_packed'
  } else if (targetStatus === 'issued') {
    activePackStage.value = 'packed_issued'
  } else if (targetStatus === 'returned') {
    activePackStage.value = 'issued_returned'
  }
  initMoveQtyInputs()
}

async function handleTransition(targetStatus: string) {
  // Spezialfall: Zurückweisung (approved → submitted)
  if (selectedActivity.value?.status === 'approved' && targetStatus === 'submitted') {
    const comment = await prompt.prompt({
      title: 'Zurückweisen',
      message: 'Bitte gib einen Grund für die Zurückweisung an. Die Bestellung geht zurück an den Gruppenleiter.',
      placeholder: 'z.B. Material nicht verfügbar, Termin passt nicht...',
      confirmText: 'Zurückweisen',
      cancelText: 'Abbrechen',
      required: true,
    })
    if (comment === null) return // abgebrochen
    changeActivityStatus(targetStatus, comment)
    return
  }

  // Bestätigungs-Dialoge für wichtige Übergänge
  const confirmConfigs: Record<string, { title: string; message: string; variant?: 'info' | 'warning' | 'danger' }> = {
    submitted: {
      title: 'Bestellung einreichen?',
      message: 'Die Bestellung wird an den Materialwart gesendet.',
      variant: 'info',
    },
    issued: {
      title: 'Material als ausgegeben markieren?',
      message: 'Ab hier können Meldungen (Schaden, Reparatur) erstellt werden.',
      variant: 'warning',
    },
    completed: {
      title: 'Aktivität abschliessen?',
      message: 'Bestandsänderungen werden verbucht.',
      variant: 'warning',
    },
  }

  const cfg = confirmConfigs[targetStatus]
  if (cfg) {
    const ok = await confirm.confirm({
      ...cfg,
      confirmText: targetStatus === 'submitted' ? 'Einreichen' : targetStatus === 'completed' ? 'Abschliessen' : 'Bestätigen',
      cancelText: 'Abbrechen',
    })
    if (!ok) return
  }

  changeActivityStatus(targetStatus)
}

async function cancelActivity() {
  const ok = await confirm.confirm({
    title: 'Aktivität stornieren?',
    message: 'Die Aktivität wird endgültig storniert.',
    confirmText: 'Stornieren',
    cancelText: 'Abbrechen',
    variant: 'danger',
  })
  if (ok) {
    changeActivityStatus('cancelled')
  }
}

function getTransitionBtnClass(status: string): string {
  const classes: Record<string, string> = {
    submitted: 'btn-primary',
    approved: 'btn-success',
    packing: 'btn-info',
    packed: 'btn-info',
    issued: 'btn-warning',
    returned: 'btn-secondary',
    completed: 'btn-success',
  }
  return classes[status] || 'btn-secondary'
}

function getTransitionIcon(status: string): string {
  const icons: Record<string, string> = {
    submitted: '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    approved: '<polyline points="20 6 9 17 4 12"/>',
    packing: '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>',
    packed: '<polyline points="20 6 9 17 4 12"/>',
    issued: '<polygon points="5 3 19 12 5 21 5 3"/>',
    returned: '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
    completed: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
  }
  return icons[status] || ''
}

// ═══════════════════════════════════════════════
// PACKLISTE
// ═══════════════════════════════════════════════

async function loadPackItems() {
  if (!selectedActivity.value) return
  isLoadingPackItems.value = true
  try {
    const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/pack-items`)
    packItems.value = (response.data || []).map((pi: any) => ({
      id: pi.id,
      materialItemId: pi.material_item_id,
      materialName: pi.material_name,
      categoryName: pi.category_name || null,
      categoryId: pi.category_id || null,
      packSize: pi.pack_size || null,
      packUnit: pi.pack_unit || null,
      quantityOrdered: pi.quantity_ordered,
      quantityPacked: pi.quantity_packed,
      quantityIssued: pi.quantity_issued ?? 0,
      quantityReturned: pi.quantity_returned ?? 0,
      conditionOut: pi.condition_out,
      batchNumbers: pi.batch_numbers,
      notes: pi.notes,
      isFullyPacked: pi.is_fully_packed,
      isFullyIssued: pi.is_fully_issued ?? false,
      isFullyReturned: pi.is_fully_returned ?? false,
      packDifference: pi.pack_difference,
      issueDifference: pi.issue_difference ?? 0,
      returnDifference: pi.return_difference ?? 0,
      isConsumable: pi.is_consumable || false,
      isJsMaterial: pi.is_js_material || false,
      externalSource: pi.external_source || null,
      packedAt: pi.packed_at,
    }))
    
    // Falls leer und Status packing → automatisch initialisieren
    if (packItems.value.length === 0 && selectedActivity.value.status === 'packing') {
      await initPackItems()
    }
    await loadPackContainers()
  } catch (err) {
    console.error('Fehler beim Laden der Packliste:', err)
  } finally {
    isLoadingPackItems.value = false
    initMoveQtyInputs()
  }
}

async function initPackItems() {
  if (!selectedActivity.value) return
  try {
    await apiClient.post(`/api/activities/${selectedActivity.value.id}/pack-items/init`)
    await loadPackItems()
  } catch (err) {
    console.error('Fehler beim Initialisieren der Packliste:', err)
  }
}

async function updatePackItem(packItem: PackItem, field: string, value: any) {
  if (!selectedActivity.value) return
  try {
    await apiClient.patch(`/api/activities/${selectedActivity.value.id}/pack-items/${packItem.id}`, {
      [field]: value,
    })
    // Lokal aktualisieren
    const idx = packItems.value.findIndex(p => p.id === packItem.id)
    if (idx !== -1) {
      (packItems.value[idx] as any)[field === 'quantity_packed' ? 'quantityPacked' : field === 'condition_out' ? 'conditionOut' : field] = value
      packItems.value[idx].isFullyPacked = packItems.value[idx].quantityPacked >= packItems.value[idx].quantityOrdered
      packItems.value[idx].packDifference = packItems.value[idx].quantityOrdered - packItems.value[idx].quantityPacked
    }
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

// ── Packliste: 4-Stufen Workflow Board ──
// Stufen: Bestätigt → Gepackt → Am Event → Retour
// Immer 2 Panels sichtbar, Navigation per Tab

type PackStage = 'confirmed_packed' | 'packed_issued' | 'issued_returned'

const PACK_STAGES: { key: PackStage; leftLabel: string; rightLabel: string; leftField: string; rightField: string }[] = [
  { key: 'confirmed_packed', leftLabel: 'Bestätigt', rightLabel: 'Gepackt', leftField: 'pack', rightField: 'packed' },
  { key: 'packed_issued', leftLabel: 'Gepackt', rightLabel: 'Am Event', leftField: 'issue', rightField: 'issued' },
  { key: 'issued_returned', leftLabel: 'Am Event', rightLabel: 'Retour', leftField: 'return', rightField: 'returned' },
]

const activePackStage = ref<PackStage>('confirmed_packed')

// Auto-Auswahl der Stufe basierend auf Activity-Status
const autoPackStage = computed<PackStage>(() => {
  const status = selectedActivity.value?.status
  if (status === 'packed') return 'packed_issued'       // Gepackt → zeige "Gepackt | Am Event"
  if (status === 'issued') return 'issued_returned'     // Am Event → zeige "Am Event | Retour"
  if (status === 'returned') return 'issued_returned'
  return 'confirmed_packed' // packing
})

// Aktives Stufen-Config
const activeStageConfig = computed(() => PACK_STAGES.find(s => s.key === activePackStage.value) || PACK_STAGES[0])

// Pro Stufe: Items für linkes und rechtes Panel berechnen
function getStageLeftQty(item: PackItem): number {
  switch (activePackStage.value) {
    case 'confirmed_packed': return item.quantityOrdered - item.quantityPacked
    case 'packed_issued': return item.quantityPacked - item.quantityIssued
    case 'issued_returned': return item.quantityIssued - item.quantityReturned
    default: return 0
  }
}

function getStageRightQty(item: PackItem): number {
  switch (activePackStage.value) {
    case 'confirmed_packed': return item.quantityPacked
    case 'packed_issued': return item.quantityIssued
    case 'issued_returned': return item.quantityReturned
    default: return 0
  }
}

function getStageTotalQty(item: PackItem): number {
  switch (activePackStage.value) {
    case 'confirmed_packed': return item.quantityOrdered
    case 'packed_issued': return item.quantityPacked
    case 'issued_returned': return item.quantityIssued
    default: return 0
  }
}

// Items mit Restmenge > 0 auf der linken Seite
const stageLeftItems = computed(() => packItems.value.filter(p => getStageLeftQty(p) > 0))
// Items die bereits verschoben wurden (rechte Seite)
const stageRightItems = computed(() => packItems.value.filter(p => getStageRightQty(p) > 0))

// Fortschritt der aktuellen Stufe
const stageProgress = computed(() => {
  const total = packItems.value.reduce((sum, p) => sum + getStageTotalQty(p), 0)
  const done = packItems.value.reduce((sum, p) => sum + getStageRightQty(p), 0)
  return total > 0 ? Math.round((done / total) * 100) : 0
})
const jsWorkflowSummary = computed(() => {
  const jsPackItems = packItems.value.filter(item => item.isJsMaterial)
  const losses = issueReports.value
    .filter(issue => issue.isJsMaterial && issue.type === 'loss')
    .reduce((sum, issue) => sum + (issue.quantity || 0), 0)

  return {
    items: jsPackItems.length,
    received: jsPackItems.reduce((sum, item) => sum + (item.quantityIssued || 0), 0),
    returned: jsPackItems.reduce((sum, item) => sum + (item.quantityReturned || 0), 0),
    losses,
  }
})

// Der passende Workflow-Transition-Button für die aktuelle Stufe (bei 100%)
const nextWorkflowTransition = computed(() => {
  // Welcher Zielstatus passt zur aktuellen Stufe?
  const stageToStatus: Record<string, string> = {
    'confirmed_packed': 'packed',   // Bestätigt→Gepackt fertig → "Gepackt markieren"
    'packed_issued': 'issued',      // Gepackt→AmEvent fertig → "Ausgeben"
    'issued_returned': 'returned',  // AmEvent→Retour fertig → "Retour erfassen" (wird vom completed abgelöst)
  }
  const targetStatus = stageToStatus[activePackStage.value]
  if (!targetStatus) return null
  
  // Prüfen ob diese Transition verfügbar und erlaubt ist
  const transition = availableTransitions.value.find(t => t.status === targetStatus && t.allowed)
  return transition || null
})

// Workflow-Button in der Fortschrittsleiste: bei < 100% mit Warnung, danach nächste Stufe
async function handleWorkflowTransition() {
  if (!nextWorkflowTransition.value) return
  
  if (stageProgress.value < 100) {
    const remaining = stageLeftItems.value.length
    const ok = await confirm.confirm({
      title: `Achtung: ${stageProgress.value}% abgeschlossen!`,
      message: `${remaining} Position(en) wurden noch nicht verschoben. Trotzdem als "${nextWorkflowTransition.value.label}" fortfahren?`,
      confirmText: 'Fortfahren',
      cancelText: 'Abbrechen',
      variant: 'warning',
    })
    if (!ok) return
  }
  
  // Status wechseln → changeActivityStatus kümmert sich um Stage-Wechsel + Tab
  await changeActivityStatus(nextWorkflowTransition.value.status)
}

// Gruppierung nach Kategorie
interface PackGroup {
  categoryName: string
  collapsed: boolean
  items: PackItem[]
}

const collapsedGroups = ref<Record<string, boolean>>({})

function groupPackItems(items: PackItem[]): PackGroup[] {
  const groups: Record<string, PackItem[]> = {}
  for (const item of items) {
    const cat = item.categoryName || 'Ohne Kategorie'
    if (!groups[cat]) groups[cat] = []
    groups[cat].push(item)
  }
  return Object.entries(groups)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([name, items]) => ({
      categoryName: name,
      collapsed: !!collapsedGroups.value[name],
      items,
    }))
}

const groupsLeft = computed(() => groupPackItems(stageLeftItems.value))
const groupsRight = computed(() => groupPackItems(stageRightItems.value))

function toggleGroup(groupName: string) {
  collapsedGroups.value[groupName] = !collapsedGroups.value[groupName]
}

// ── Move-Logik: Item zur nächsten Stufe verschieben ──

const moveQtyInputs = ref<Record<string, number>>({})
const moveBackQtyInputs = ref<Record<string, number>>({})

// Setzt die Mengen-Inputs für alle Items auf den aktuellen Restwert
function initMoveQtyInputs() {
  for (const item of packItems.value) {
    const leftQty = getStageLeftQty(item)
    moveQtyInputs.value[item.id] = Math.max(0, leftQty)
    const rightQty = getStageRightQty(item)
    moveBackQtyInputs.value[item.id] = Math.max(0, rightQty)
  }
}

function getBackendStage(): string {
  switch (activePackStage.value) {
    case 'confirmed_packed': return 'packed'
    case 'packed_issued': return 'issued'
    case 'issued_returned': return 'returned'
    default: return 'packed'
  }
}

async function moveToNextStage(item: PackItem, qty?: number) {
  if (!selectedActivity.value) return
  const moveQty = qty ?? moveQtyInputs.value[item.id] ?? getStageLeftQty(item)
  if (moveQty <= 0) return

  try {
    const response = await apiClient.post(
      `/api/activities/${selectedActivity.value.id}/pack-items/${item.id}/move`,
      { stage: getBackendStage(), quantity: moveQty }
    )
    // Lokal aktualisieren aus der Server-Antwort
    const updated = response.data
    const idx = packItems.value.findIndex(p => p.id === item.id)
    if (idx !== -1) {
      packItems.value[idx].quantityPacked = updated.quantity_packed
      packItems.value[idx].quantityIssued = updated.quantity_issued
      packItems.value[idx].quantityReturned = updated.quantity_returned
      packItems.value[idx].isFullyPacked = updated.is_fully_packed
      packItems.value[idx].isFullyIssued = updated.is_fully_issued
      packItems.value[idx].isFullyReturned = updated.is_fully_returned
      packItems.value[idx].packDifference = updated.pack_difference
      packItems.value[idx].issueDifference = updated.issue_difference
      packItems.value[idx].returnDifference = updated.return_difference
    }
    // Input auf neuen Restwert setzen
    const newLeft = getStageLeftQty(packItems.value[packItems.value.findIndex(p => p.id === item.id)])
    moveQtyInputs.value[item.id] = Math.max(0, newLeft)
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

async function moveToPrevStage(item: PackItem) {
  if (!selectedActivity.value) return
  const moveQty = moveBackQtyInputs.value[item.id] ?? getStageRightQty(item)
  if (moveQty <= 0) return

  try {
    const response = await apiClient.post(
      `/api/activities/${selectedActivity.value.id}/pack-items/${item.id}/moveback`,
      { stage: getBackendStage(), quantity: moveQty }
    )
    // Lokal aktualisieren aus der Server-Antwort
    const updated = response.data
    const idx = packItems.value.findIndex(p => p.id === item.id)
    if (idx !== -1) {
      packItems.value[idx].quantityPacked = updated.quantity_packed
      packItems.value[idx].quantityIssued = updated.quantity_issued
      packItems.value[idx].quantityReturned = updated.quantity_returned
      packItems.value[idx].isFullyPacked = updated.is_fully_packed
      packItems.value[idx].isFullyIssued = updated.is_fully_issued
      packItems.value[idx].isFullyReturned = updated.is_fully_returned
      packItems.value[idx].packDifference = updated.pack_difference
      packItems.value[idx].issueDifference = updated.issue_difference
      packItems.value[idx].returnDifference = updated.return_difference
    }
    // Inputs aktualisieren
    const newLeft = getStageLeftQty(packItems.value[idx])
    const newRight = getStageRightQty(packItems.value[idx])
    moveQtyInputs.value[item.id] = Math.max(0, newLeft)
    moveBackQtyInputs.value[item.id] = Math.max(0, newRight)
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

async function moveAllToNextStage() {
  if (!selectedActivity.value) return
  try {
    await apiClient.post(
      `/api/activities/${selectedActivity.value.id}/pack-items/move-all`,
      { stage: getBackendStage() }
    )
    // Packliste neu laden für konsistenten State
    await loadPackItems()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

// ── 3-Punkte-Menü: Pack-Item bearbeiten / Meldung erstellen ──

const showPackEditModal = ref(false)
const packEditItem = ref<PackItem | null>(null)
const packEditQty = ref(0)
const packEditCondition = ref('ok')
const packEditNotes = ref('')
const packEditAction = ref<'edit' | 'loss' | 'repair' | 'consumption'>('edit')
const isPackEditSubmitting = ref(false)

function openPackEditModal(item: PackItem, action: 'edit' | 'loss' | 'repair' | 'consumption' = 'edit') {
  packEditItem.value = item
  packEditAction.value = action
  if (action === 'edit') {
    // Je nach Stufe die richtige Menge anzeigen
    switch (activePackStage.value) {
      case 'confirmed_packed': packEditQty.value = item.quantityPacked; break
      case 'packed_issued': packEditQty.value = item.quantityIssued; break
      case 'issued_returned': packEditQty.value = item.quantityReturned; break
    }
    packEditCondition.value = item.conditionOut || 'ok'
    packEditNotes.value = item.notes || ''
  } else {
    packEditQty.value = 1
    packEditNotes.value = ''
  }
  showPackEditModal.value = true
}

function closePackEditModal() {
  showPackEditModal.value = false
  packEditItem.value = null
}

async function confirmPackEdit() {
  if (!packEditItem.value || !selectedActivity.value) return
  if (isPackEditSubmitting.value) return

  isPackEditSubmitting.value = true
  try {
    if (packEditAction.value === 'loss' || packEditAction.value === 'repair' || packEditAction.value === 'consumption') {
      // Verlust, Reparatur oder Verbrauch melden → Issue Report erstellen
      const actionType = packEditAction.value
      const response = await apiClient.post(`/api/activities/${selectedActivity.value.id}/issues`, {
        material_item_id: packEditItem.value.materialItemId,
        type: actionType,
        quantity: packEditQty.value,
        description: packEditNotes.value || null,
      })
      closePackEditModal()
      // Hinweis wenn Werkstatt-Ticket erstellt (bei Verlust/Reparatur/Schaden)
      if (response.data?.workshop_ticket_created) {
        const msg = actionType === 'loss'
          ? 'Verlust gemeldet. Ein Abschreibungs-Ticket wurde in der Werkstatt erstellt.'
          : 'Meldung erstellt. Ein Reparatur-Ticket wurde in der Werkstatt erstellt.'
        toast.success(msg)
      }
      // Issues nachladen falls Tab offen
      await loadIssues()
      // Bei Verlust/Verbrauch: PackItems neu laden (Menge wurde im Backend reduziert)
      if (actionType === 'loss' || actionType === 'consumption') {
        await loadPackItems()
        initMoveQtyInputs()
      }
      return
    }

    // Menge ändern
    const updateData: Record<string, any> = {
      condition_out: packEditCondition.value,
      notes: packEditNotes.value || null,
    }
    // Je nach Stufe die richtige Menge aktualisieren
    switch (activePackStage.value) {
      case 'confirmed_packed': updateData.quantity_packed = packEditQty.value; break
      case 'packed_issued': updateData.quantity_issued = packEditQty.value; break
      case 'issued_returned': updateData.quantity_returned = packEditQty.value; break
    }

    const response = await apiClient.patch(
      `/api/activities/${selectedActivity.value.id}/pack-items/${packEditItem.value.id}`,
      updateData
    )
    const updated = response.data
    const idx = packItems.value.findIndex(p => p.id === packEditItem.value!.id)
    if (idx !== -1) {
      packItems.value[idx].quantityPacked = updated.quantity_packed
      packItems.value[idx].quantityIssued = updated.quantity_issued
      packItems.value[idx].quantityReturned = updated.quantity_returned
      packItems.value[idx].conditionOut = updated.condition_out
      packItems.value[idx].notes = updated.notes
      packItems.value[idx].isFullyPacked = updated.is_fully_packed
      packItems.value[idx].isFullyIssued = updated.is_fully_issued
      packItems.value[idx].isFullyReturned = updated.is_fully_returned
      packItems.value[idx].packDifference = updated.pack_difference
      packItems.value[idx].issueDifference = updated.issue_difference
      packItems.value[idx].returnDifference = updated.return_difference
    }
    closePackEditModal()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  } finally {
    isPackEditSubmitting.value = false
  }
}

// Schnell-Move: komplette Restmenge verschieben (1-Klick)
async function quickMove(item: PackItem) {
  const leftQty = getStageLeftQty(item)
  if (leftQty > 0) {
    await moveToNextStage(item, leftQty)
  }
}

// ═══════════════════════════════════════════════
// MELDUNGEN (Issue Reports)
// ═══════════════════════════════════════════════

async function loadIssues() {
  if (!selectedActivity.value) return
  isLoadingIssues.value = true
  try {
    const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/issues`)
    issueReports.value = (response.data || []).map((ir: any) => ({
      id: ir.id,
      materialItemId: ir.material_item_id,
      materialName: ir.material_name,
      type: ir.type,
      typeLabel: ir.type_label,
      quantity: ir.quantity,
      description: ir.description,
      photoUrl: ir.photo_url,
      notes: ir.notes,
      resolved: ir.resolved,
      resolvedAt: ir.resolved_at,
      reportedAt: ir.reported_at,
      isJsMaterial: ir.is_js_material || false,
      externalSource: ir.external_source || null,
    }))
  } catch (err) {
    console.error('Fehler beim Laden der Meldungen:', err)
  } finally {
    isLoadingIssues.value = false
  }
}

function selectIssueMaterial(item: any) {
  newIssue.value.materialItemId = item.materialItemId
  newIssue.value.materialName = item.materialName
  issueMatSearch.value = ''
  showIssueMatDropdown.value = false
}

async function createIssue() {
  if (!selectedActivity.value) return
  // Material ist Pflicht
  if (!newIssue.value.materialItemId) {
    toast.warning('Bitte wähle ein Material aus.')
    return
  }
  try {
    await apiClient.post(`/api/activities/${selectedActivity.value.id}/issues`, {
      material_item_id: newIssue.value.materialItemId || null,
      type: newIssue.value.type,
      quantity: newIssue.value.quantity,
      description: newIssue.value.description,
    })
    showIssueForm.value = false
    newIssue.value = { materialItemId: '', materialName: '', type: 'repair', quantity: 1, description: '' }
    issueMatSearch.value = ''
    showIssueMatDropdown.value = false
    await loadIssues()
    await loadWorkshopCostsForActivity()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

// Gefilterte Listen: Reparaturen/Verluste vs. Verbrauch (sortiert nach Zeit absteigend, dann ID)
const issueReportsFiltered = computed(() =>
  issueReports.value
    .filter(ir => ['repair', 'loss', 'damage'].includes(ir.type))
    .sort((a, b) => {
      const ta = new Date(a.reportedAt).getTime()
      const tb = new Date(b.reportedAt).getTime()
      if (tb !== ta) return tb - ta
      return (b.id || '').localeCompare(a.id || '')
    })
)
const consumptionReports = computed(() =>
  issueReports.value.filter(ir => ir.type === 'consumption')
)

const workshopTicketsByIssueId = computed(() => {
  const map = new Map<string, WorkshopTicket>()
  for (const t of workshopCostTickets.value) {
    if (t.issue_report_id) {
      map.set(t.issue_report_id, t)
    }
  }
  return map
})

function getWorkshopTicketForIssue(issueId: string): WorkshopTicket | null {
  return workshopTicketsByIssueId.value.get(issueId) || null
}

function getWorkshopStatusLabel(status: string): string {
  const labels: Record<string, string> = {
    open: 'Offen',
    in_progress: 'In Arbeit',
    waiting_parts: 'Wartet auf Teile',
    completed: 'Erledigt',
    cancelled: 'Storniert',
  }
  return labels[status] || status || '—'
}

function openWorkshopForIssue(issueId: string) {
  const ticket = getWorkshopTicketForIssue(issueId)
  const query = ticket?.id ? `?ticket=${ticket.id}` : ''
  router.push(`/${departmentId.value}/workshop${query}`)
}

// Verbrauchsmaterial-Items aus der Material-Liste
const consumableItems = computed(() =>
  detailItems.value.filter((item: any) => item.isConsumable)
)

// Wie viel wurde von einem Material bereits verbraucht?
function getConsumableUsed(materialItemId: string): number {
  return consumptionReports.value
    .filter(cr => cr.materialItemId === materialItemId)
    .reduce((sum, cr) => sum + cr.quantity, 0)
}

// ═══ Kosten-Tab: Computed Properties ═══
const costConsumableItems = computed(() =>
  detailItems.value.filter((item: any) => item.isConsumable)
)

const costRentalItems = computed(() =>
  detailItems.value.filter((item: any) => !item.isConsumable)
)

const costConsumableTotal = computed(() => {
  return costConsumableItems.value.reduce((sum, item: any) => {
    if (!item.salePrice) return sum
    const used = getConsumableUsed(item.materialItemId)
    // Nur tatsächlich verbrauchte Menge zählt
    if (used <= 0) return sum
    return sum + Number(item.salePrice) * used
  }, 0)
})

const costRentalTotal = computed(() => {
  return costRentalItems.value.reduce((sum, item: any) => {
    return sum + (item.lineTotal || 0)
  }, 0)
})

const costGrandTotal = computed(() => costConsumableTotal.value + costRentalTotal.value)

const costLossItems = computed(() =>
  issueReports.value.filter(ir => ir.type === 'loss')
)

const workshopResolvedTickets = computed(() =>
  workshopCostTickets.value.filter(t => t.status === 'completed' && (t.resolution_action === 'repaired' || t.resolution_action === 'writeoff'))
)

const costRepairTickets = computed(() =>
  workshopResolvedTickets.value.filter(t => t.resolution_action === 'repaired')
)

const costWriteoffTickets = computed(() =>
  workshopResolvedTickets.value.filter(t => t.resolution_action === 'writeoff')
)

const costRepairTotal = computed(() => {
  return costRepairTickets.value.reduce((sum, t) => sum + Number(t.actual_cost || 0), 0)
})

const costWriteoffTotal = computed(() => {
  return costWriteoffTickets.value.reduce((sum, t) => sum + Number(t.actual_cost || 0), 0)
})

const costExternalGrandTotal = computed(() => {
  if (selectedActivity.value?.type !== 'external') return 0
  return costConsumableTotal.value + costRentalTotal.value + costRepairTotal.value + costWriteoffTotal.value
})

const costInternalGrandTotal = computed(() => {
  return costConsumableTotal.value + costRepairTotal.value + costWriteoffTotal.value
})

// Mengen-Inputs für Verbrauch
const consumableQtyInputs = ref<Record<string, number>>({})

// Verbrauch buchen (= Issue Report mit Typ "consumption")
async function reportConsumption(item: any) {
  if (!selectedActivity.value) return
  const qty = consumableQtyInputs.value[item.materialItemId] || 1
  try {
    await apiClient.post(`/api/activities/${selectedActivity.value.id}/issues`, {
      material_item_id: item.materialItemId,
      type: 'consumption',
      quantity: qty,
      description: null,
    })
    // Inputs zurücksetzen + Issues neu laden
    consumableQtyInputs.value[item.materialItemId] = 1
    await loadIssues()
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

// ═══════════════════════════════════════════════
// RÜCKGABE
// ═══════════════════════════════════════════════

async function loadReturnItems() {
  if (!selectedActivity.value) return
  isLoadingReturns.value = true
  try {
    const response = await apiClient.get(`/api/activities/${selectedActivity.value.id}/return-items`)
    returnItems.value = (response.data || []).map((ri: any) => ({
      id: ri.id,
      materialItemId: ri.material_item_id,
      materialName: ri.material_name,
      quantityPacked: ri.quantity_packed,
      quantityReturned: ri.quantity_returned,
      quantityDamaged: ri.quantity_damaged,
      quantityMissing: ri.quantity_missing,
      quantityOk: ri.quantity_ok,
      conditionIn: ri.condition_in,
      notes: ri.notes,
      hasDifferences: ri.has_differences,
      returnedAt: ri.returned_at,
      isJsMaterial: ri.is_js_material || false,
      externalSource: ri.external_source || null,
    }))
    
    // Falls leer und Status returned → automatisch initialisieren
    if (returnItems.value.length === 0 && selectedActivity.value.status === 'returned') {
      await initReturnItems()
    }
  } catch (err) {
    console.error('Fehler beim Laden der Rückgabeliste:', err)
  } finally {
    isLoadingReturns.value = false
  }
}

async function initReturnItems() {
  if (!selectedActivity.value) return
  try {
    await apiClient.post(`/api/activities/${selectedActivity.value.id}/return-items/init`)
    await loadReturnItems()
  } catch (err) {
    console.error('Fehler beim Initialisieren der Rückgabeliste:', err)
  }
}

function onReturnConditionChange(returnItem: ReturnItem, event: Event) {
  const target = event.target as HTMLSelectElement | null
  updateReturnItem(returnItem, 'condition_in', target?.value || 'ok')
}

async function updateReturnItem(returnItem: ReturnItem, field: string, value: any) {
  if (!selectedActivity.value) return
  try {
    await apiClient.patch(`/api/activities/${selectedActivity.value.id}/return-items/${returnItem.id}`, {
      [field]: value,
    })
    // Lokal aktualisieren
    const idx = returnItems.value.findIndex(r => r.id === returnItem.id)
    if (idx !== -1) {
      const fieldMap: Record<string, string> = {
        quantity_returned: 'quantityReturned',
        quantity_damaged: 'quantityDamaged', 
        quantity_missing: 'quantityMissing',
        condition_in: 'conditionIn',
        notes: 'notes',
      }
      const localField = fieldMap[field] || field
      ;(returnItems.value[idx] as any)[localField] = value
      returnItems.value[idx].hasDifferences = returnItems.value[idx].quantityDamaged > 0 || returnItems.value[idx].quantityMissing > 0
      returnItems.value[idx].quantityOk = Math.max(0, returnItems.value[idx].quantityReturned - returnItems.value[idx].quantityDamaged)
    }
  } catch (err: any) {
    toast.error('Fehler: ' + (err.response?.data?.error || err.message))
  }
}

function getHistoryActionLabel(action: string): string {
  const labels: Record<string, string> = {
    created: 'Erstellt',
    updated: 'Bearbeitet',
    deleted: 'Gelöscht',
    status_changed: 'Status geändert',
    material_added: 'Material hinzugefügt',
    material_removed: 'Material entfernt',
    issue_reported: 'Meldung erstellt',
    pack_started: 'Packen begonnen',
    return_started: 'Rückgabe begonnen',
  }
  return labels[action] || action
}

// Sortierung
function toggleSort(field: string) {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDir.value = 'asc'
  }
}

// Datums-Hilfsfunktionen
function isSameDay(date1: string, date2: string): boolean {
  const d1 = new Date(date1)
  const d2 = new Date(date2)
  return d1.toDateString() === d2.toDateString()
}

function formatDateShort(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH', { day: '2-digit', month: 'short' })
}

function getRelativeDate(dateStr: string): string {
  const now = new Date()
  const d = new Date(dateStr)
  const diffMs = d.getTime() - now.getTime()
  const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24))
  
  if (diffDays === 0) return 'heute'
  if (diffDays === 1) return 'morgen'
  if (diffDays === -1) return 'gestern'
  if (diffDays > 0 && diffDays <= 7) return `in ${diffDays} Tagen`
  if (diffDays < 0 && diffDays >= -7) return `vor ${Math.abs(diffDays)} Tagen`
  if (diffDays > 7 && diffDays <= 30) return `in ${Math.ceil(diffDays / 7)} Wochen`
  if (diffDays < -7 && diffDays >= -30) return `vor ${Math.ceil(Math.abs(diffDays) / 7)} Wochen`
  return ''
}

const isCreating = ref(false)

async function createActivity() {
  if (isCreating.value) return
  isCreating.value = true
  try {
    // Letztmalig alles speichern (inkl. Notizen)
    await autoSaveDraft()

    const savedDraftId = draftId.value
    if (!savedDraftId) {
      throw new Error('Aktivität konnte nicht gespeichert werden (keine Entwurf-ID erhalten).')
    }

    // Quick-Modus: direkt einreichen (draft → submitted)
    const isQuickMode = wizardMode.value === 'quick'
    if (isQuickMode && savedDraftId) {
      await apiClient.patch(`/api/activities/${savedDraftId}/status`, {
        status: 'submitted'
      })
    }

    // Wizard schliessen (bei from=dashboard: auf Aktivitäten bleiben, nicht zurück zum Dashboard)
    handleCloseWizard(false)

    await loadActivities()

    // Erfolgsmeldung anzeigen
    if (isQuickMode) {
      toast.success('Aktivität wurde eingereicht')
    } else {
      toast.success('Aktivität als Entwurf gespeichert')
    }

    // Detailansicht des erstellten/eingereichten Eintrags öffnen
    if (savedDraftId) {
      let created = activities.value.find(a => a.id === savedDraftId)
      if (!created) {
        try {
          const detailResponse = await apiClient.get(`/api/activities/${savedDraftId}`)
          created = mapActivityListItem(detailResponse.data)
          activities.value = [created, ...activities.value.filter(a => a.id !== created!.id)]
        } catch (err) {
          console.warn('Neu erstellte Aktivität ist noch nicht in der Liste sichtbar:', err)
        }
      }
      if (created) {
        openActivity(created)
      }
    }
  } catch (err: any) {
    console.error('Fehler beim Erstellen der Aktivität:', err)
    toast.error('Fehler beim Erstellen: ' + (err.response?.data?.error || err.message))
  } finally {
    isCreating.value = false
  }
}
</script>

<style scoped>
.activities-view {
  padding: 24px;
  max-width: 1400px;
  position: relative;
}

/* Header */

.header-left h1 {
  font-size: 24px;
  font-weight: 700;
  color: var(--color-text, #1a1a2e);
  margin: 0;
}

.subtitle {
  font-size: 14px;
  color: var(--color-text-muted, #6b7280);
  margin-top: 4px;
  display: block;
}

/* Buttons come from central ui/buttons.css */

.btn-icon {
  width: 16px;
  height: 16px;
}

/* Filter Bar */
.filter-bar {
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--color-border, #e5e7eb);
  padding-bottom: 0;
}

/* Table */

.activity-row.row-draft {
  background: #fffbeb;
}

.activity-row.row-draft:hover {
  background: #fef3c7;
}

.col-status {
  width: 40px;
  text-align: center;
}

.status-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #9ca3af;
}

.status-dot.draft { background: #f59e0b; }
.status-dot.submitted { background: #3b82f6; }
.status-dot.approved { background: #10b981; }
.status-dot.packing { background: #6366f1; }
.status-dot.packed { background: #4f46e5; }
.status-dot.issued { background: #f97316; }
.status-dot.returned { background: #ec4899; }
.status-dot.completed { background: #6b7280; }
.status-dot.cancelled { background: #ef4444; }
/* Legacy */
.status-dot.confirmed { background: #3b82f6; }
.status-dot.active { background: #10b981; }

.activity-name {
  font-weight: 500;
}

.activity-no {
  font-size: 12px;
  color: var(--color-text-muted, #6b7280);
}

.activity-share-hint {
  margin-top: 2px;
  font-size: 11px;
  color: #6366f1;
  font-weight: 500;
}

.activity-share-status {
  margin-top: 1px;
  font-size: 11px;
  color: #6b7280;
}

.type-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
}

.type-badge.activity { background: #dbeafe; color: #1d4ed8; }
.type-badge.event { background: #fce7f3; color: #be185d; }
.type-badge.camp { background: #d1fae5; color: #065f46; }
.type-badge.external { background: #fef3c7; color: #92400e; }

.status-label {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}

.status-label.draft { background: #fef3c7; color: #92400e; }
.status-label.submitted { background: #dbeafe; color: #1e40af; }
.status-label.approved { background: #d1fae5; color: #065f46; }
.status-label.packing { background: #e0e7ff; color: #3730a3; }
.status-label.packed { background: #c7d2fe; color: #4338ca; }
.status-label.issued { background: #fed7aa; color: #9a3412; }
.status-label.returned { background: #fbcfe8; color: #9d174d; }
.status-label.completed { background: #f3f4f6; color: #4b5563; }
.status-label.cancelled { background: #fee2e2; color: #991b1b; }
/* Legacy */
.status-label.confirmed { background: #dbeafe; color: #1e40af; }
.status-label.active { background: #d1fae5; color: #065f46; }

/* Stats Bar */
.stats-bar {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 12px 20px;
  background: white;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 8px;
  min-width: 90px;
}

.stat-value {
  font-size: 22px;
  font-weight: 700;
  color: #1f2937;
}

.stat-value.stat-draft { color: #f59e0b; }
.stat-value.stat-submitted { color: #3b82f6; }
.stat-value.stat-approved { color: #10b981; }
.stat-value.stat-issued { color: #f97316; }
.stat-value.stat-completed { color: #6b7280; }

.stat-label {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

/* Type Filter Chips */
.filter-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.type-filter-chips {
  display: flex;
  gap: 4px;
}

.type-chip {
  padding: 5px 12px;
  font-size: 12px;
  font-weight: 500;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  background: white;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.15s;
}

.type-chip:hover {
  border-color: var(--color-primary, #4f46e5);
  color: var(--color-primary, #4f46e5);
}

.type-chip.active {
  background: var(--color-primary, #4f46e5);
  color: white;
  border-color: var(--color-primary, #4f46e5);
}

/* Search Box */
.search-box {
  position: relative;
  display: flex;
  align-items: center;
}

.search-box .search-icon {
  position: absolute;
  left: 10px;
  color: #9ca3af;
  pointer-events: none;
}

.search-box .search-input {
  padding-left: 32px;
}

/* Sort */
.activities-table thead th[class*="col-name"],
.activities-table thead th[class*="col-period"],
.activities-table thead th[class*="col-price"] {
  cursor: pointer;
  user-select: none;
}

.activities-table thead th:hover {
  color: var(--color-primary, #4f46e5);
}

.sort-icon {
  font-size: 11px;
  margin-left: 4px;
}

/* Customer/Group Column */
.customer-group {
  display: block;
  font-weight: 500;
  font-size: 13px;
  color: #1f2937;
}

.customer-name {
  display: block;
  font-size: 12px;
  color: #6b7280;
}

/* Period Column */
.period-display {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.period-date {
  font-size: 13px;
  font-weight: 500;
}

.period-separator {
  font-size: 11px;
  color: #9ca3af;
}

.period-relative {
  font-size: 11px;
  color: #6b7280;
  font-style: italic;
}

/* Items Badge */
.items-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 22px;
  padding: 0 6px;
  background: #eff6ff;
  color: #2563eb;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}

/* Price */
.price-display {
  font-weight: 500;
  font-size: 13px;
}

/* Table Footer */
.table-footer {
  padding: 10px 16px;
  font-size: 12px;
  color: #6b7280;
  border-top: 1px solid #f3f4f6;
  text-align: right;
}

/* Loading */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px 24px;
  gap: 12px;
  color: #6b7280;
}

.text-muted {
  color: var(--color-text-muted, #9ca3af);
}

/* ============================
   Detail-Panel
   ============================ */
.activity-detail-panel {
  animation: slideIn 0.2s ease-out;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateX(20px); }
  to { opacity: 1; transform: translateX(0); }
}

.detail-header {
  margin-bottom: 20px;
}

.detail-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: white;
  font-size: 13px;
  color: #6b7280;
  cursor: pointer;
  margin-bottom: 16px;
  transition: all 0.15s;
}

.detail-back-btn:hover {
  background: #f3f4f6;
  color: #1f2937;
}

.detail-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 4px;
  flex-wrap: wrap;
}

.detail-title {
  font-size: 22px;
  font-weight: 700;
  color: #1f2937;
  margin: 0;
  flex: 1;
}

.detail-subtitle-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 12px;
  flex-wrap: wrap;
  font-size: 13px;
  color: #6b7280;
}

.detail-subtitle-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.detail-subtitle-item svg {
  color: #9ca3af;
  flex-shrink: 0;
}

.detail-subtitle-badge {
  font-size: 11px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.detail-subtitle-badge.external {
  background: #fef3c7;
  color: #92400e;
}

.detail-subtitle-sep {
  color: #d1d5db;
  font-weight: 700;
  font-size: 16px;
}

.detail-subtitle-planning {
  color: #7c3aed;
}
.detail-subtitle-planning svg {
  color: #a78bfa !important;
}

.detail-notes-bar {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  margin-bottom: 12px;
  padding: 6px 10px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 6px;
  font-size: 13px;
  color: #92400e;
}

.detail-notes-bar svg {
  flex-shrink: 0;
  margin-top: 1px;
  color: #d97706;
}

.detail-notes-text {
  white-space: pre-wrap;
  word-break: break-word;
}

.detail-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

/* Bestand-Ändern Button (inline) */
.btn-adjust-sm {
  background: none;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 3px 6px;
  cursor: pointer;
  color: #6b7280;
  display: inline-flex;
  align-items: center;
  margin-left: 8px;
  transition: all 0.15s;
}

.btn-adjust-sm:hover {
  border-color: #3b82f6;
  color: #3b82f6;
  background: #eff6ff;
}

/* Bestand-Ändern Modal */
.adjust-modal {
  max-width: 440px;
}

.adjust-material-name {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 16px;
}

.adjust-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 20px;
}

.adjust-info-item {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px 12px;
}

.adjust-info-label {
  display: block;
  font-size: 11px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.adjust-info-value {
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
}

.adjust-input-group label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 6px;
}

.adjust-qty-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.adjust-qty-input {
  width: 80px;
  text-align: center;
  font-size: 18px;
  font-weight: 600;
  padding: 8px;
}

.adjust-warning {
  display: block;
  margin-top: 8px;
  font-size: 13px;
  color: #dc2626;
  font-weight: 500;
}

/* Set-Schnellbuttons im Modal */
.adjust-set-buttons {
  margin-top: 16px;
  padding: 12px;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}
.adjust-set-buttons label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin-bottom: 8px;
}
.adjust-set-row {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.adjust-set-row .mat-set-btn {
  padding: 6px 12px;
  font-size: 13px;
  border-radius: 6px;
  cursor: pointer;
  border: 1px solid #93c5fd;
  background: #eff6ff;
  color: #2563eb;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1px;
  transition: all 0.15s;
}
.adjust-set-row .mat-set-btn:hover:not(:disabled) {
  background: #2563eb;
  color: white;
  border-color: #2563eb;
}
.adjust-set-row .mat-set-btn.active {
  background: #2563eb;
  color: white;
  border-color: #1d4ed8;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.3);
}
.adjust-set-row .mat-set-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.set-btn-detail {
  font-size: 10px;
  opacity: 0.7;
}

/* Menge-Zusammenfassung unter Eingabe */
.adjust-qty-summary {
  margin-top: 6px;
  font-size: 13px;
  color: #6366f1;
  font-weight: 500;
}

/* Sets-Info in der Bestandsanzeige */
.adjust-info-sets {
  font-size: 12px;
  color: #6b7280;
  font-weight: 400;
}

/* Set-Info neben Menge in der Material-Liste */
.mat-qty-sets {
  font-size: 11px;
  color: #6b7280;
  margin: 0 2px;
}

/* Klickbare Zeile */
.row-clickable {
  cursor: pointer;
}
.row-clickable:hover {
  background: #f0f9ff;
}

/* Draft-Edit-Bar */
.draft-edit-bar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 12px;
}

.draft-edit-actions {
  display: flex;
  gap: 10px;
  padding-top: 8px;
}

/* Datum-Änderungs-Warnung */
.date-change-warning {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  margin-top: 12px;
  background: #fef2f2;
  border: 1px solid #fca5a5;
  border-radius: 8px;
  color: #991b1b;
  font-size: 13px;
  line-height: 1.5;
}

.date-change-warning svg {
  flex-shrink: 0;
  margin-top: 1px;
  color: #dc2626;
}

.date-change-warning-content {
  flex: 1;
}

/* Entwurf-Hinweis-Banner */
.draft-hint-banner {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 16px;
  margin: 0 0 16px 0;
  background: #fef9c3;
  border: 1px solid #facc15;
  border-radius: 8px;
  color: #854d0e;
  font-size: 13.5px;
  line-height: 1.5;
}

.draft-hint-banner svg {
  flex-shrink: 0;
  margin-top: 1px;
  color: #ca8a04;
}

/* Detail Tabs */
.detail-tab-bar {
  display: flex;
  gap: 0;
  border-bottom: 2px solid #e5e7eb;
  margin-bottom: 20px;
}

.detail-tab {
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 500;
  color: #6b7280;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  cursor: pointer;
  transition: all 0.15s;
}

.detail-tab:hover {
  color: var(--color-primary, #4f46e5);
}

.detail-tab.active {
  color: var(--color-primary, #4f46e5);
  border-bottom-color: var(--color-primary, #4f46e5);
}

/* Detail Body */
.detail-body {
  min-height: 200px;
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.detail-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 16px;
}

.detail-card-full {
  grid-column: 1 / -1;
}

.detail-card-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 12px 0;
  padding-bottom: 8px;
  border-bottom: 1px solid #f3f4f6;
}

.detail-field {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 6px 0;
  gap: 12px;
}

.detail-label {
  font-size: 13px;
  color: #6b7280;
  flex-shrink: 0;
}

.detail-value {
  font-size: 13px;
  font-weight: 500;
  color: #1f2937;
  text-align: right;
}

.detail-price {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-primary, #4f46e5);
}

/* ═══ Pricing Mode ═══ */
.pricing-section {
  margin-top: 12px;
  padding: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.pricing-mode-toggle {
  display: flex;
  gap: 0;
  margin-bottom: 12px;
  background: #e2e8f0;
  border-radius: 8px;
  padding: 3px;
}

.pricing-mode-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 12px;
  border: none;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  background: transparent;
  color: #64748b;
  transition: all 0.2s;
}

.pricing-mode-btn.active {
  background: white;
  color: #1e293b;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.pricing-mode-btn:hover:not(.active) {
  color: #334155;
}

/* Price Calculator (Einzelpreis) */
.price-calculator {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px;
}

.calc-rows {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.calc-row {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  padding: 4px 0;
  border-bottom: 1px dotted #f1f5f9;
}

.calc-row:last-child {
  border-bottom: none;
}

.calc-name {
  flex: 1;
  color: #374151;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.calc-detail {
  color: #6b7280;
  font-size: 12px;
  white-space: nowrap;
}

.calc-total {
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
  min-width: 80px;
  text-align: right;
}

.calc-sum {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
  padding-top: 8px;
  border-top: 2px solid #e2e8f0;
}

.calc-sum-label {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.calc-sum-value {
  font-size: 16px;
  font-weight: 700;
  color: #059669;
}

.calc-empty {
  text-align: center;
  color: #94a3b8;
  font-size: 13px;
  padding: 8px;
}

.calc-hint {
  text-align: center;
  color: #94a3b8;
  font-size: 12px;
  margin-top: 8px;
}

/* Set Price Input */
.set-price-input {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.set-price-label {
  font-size: 12px;
  font-weight: 500;
  color: #475569;
}

.set-price-field {
  display: flex;
  align-items: center;
  gap: 0;
}

.set-price-currency {
  padding: 10px 12px;
  background: #f1f5f9;
  border: 1px solid #d1d5db;
  border-right: none;
  border-radius: 8px 0 0 8px;
  font-size: 14px;
  font-weight: 600;
  color: #475569;
}

.set-price-value {
  flex: 1;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 0 8px 8px 0;
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  outline: none;
}

.set-price-value:focus {
  border-color: #6366f1;
  box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.set-price-value::placeholder {
  color: #cbd5e1;
  font-weight: 400;
}

/* Pricing Mode Badge (Detail View) */
.pricing-mode-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
}

.pricing-mode-badge.set_price {
  background: #ede9fe;
  color: #6d28d9;
}

.pricing-mode-badge.item_price {
  background: #ecfdf5;
  color: #059669;
}

/* Price Breakdown (Detail View) */
.detail-price-breakdown {
  margin: 4px 0 8px;
  padding: 8px;
  background: #f8fafc;
  border-radius: 6px;
  font-size: 12px;
}

.breakdown-row {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 3px 0;
}

.breakdown-name {
  flex: 1;
  color: #475569;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.breakdown-calc {
  color: #94a3b8;
  font-size: 11px;
  white-space: nowrap;
}

.breakdown-total {
  font-weight: 600;
  color: #1e293b;
  white-space: nowrap;
  min-width: 70px;
  text-align: right;
}

.detail-notes {
  font-size: 14px;
  color: #374151;
  white-space: pre-wrap;
  margin: 0;
  line-height: 1.6;
}

.detail-meta {
  display: flex;
  gap: 16px;
  padding-top: 16px;
  border-top: 1px solid #f3f4f6;
  font-size: 12px;
  color: #9ca3af;
}

.detail-meta-id {
  font-family: monospace;
  font-weight: 600;
  color: #6b7280;
}

/* Badges */
.badge-green {
  display: inline-block;
  padding: 1px 8px;
  background: #d1fae5;
  color: #065f46;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 500;
  margin-left: 6px;
}

.badge-yellow {
  display: inline-block;
  padding: 1px 8px;
  background: #fef3c7;
  color: #92400e;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 500;
  margin-left: 6px;
}

/* Detail Material List */
.detail-material-list {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.detail-material-header {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(140px, 1fr) auto;
  gap: 12px;
  padding: 8px 16px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
}

.detail-material-row {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(140px, 1fr) auto;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  border-bottom: 1px solid #f3f4f6;
}

.detail-material-row:last-child {
  border-bottom: none;
}

.detail-material-name {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
}

.detail-material-price {
  display: inline-block;
  font-size: 11px;
  color: #16a34a;
  font-weight: 500;
  margin-left: 8px;
}

.detail-material-origin {
  font-size: 13px;
  color: #64748b;
}

.detail-material-total-price {
  color: #6b7280;
  font-weight: 400;
}

.detail-material-qty {
  font-size: 14px;
  color: #6b7280;
}

.detail-material-qty strong {
  color: var(--color-primary, #4f46e5);
  font-size: 16px;
}

.detail-material-total {
  padding: 10px 16px;
  background: #f9fafb;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  border-top: 1px solid #e5e7eb;
}

.detail-material-qty {
  display: flex;
  align-items: center;
  gap: 6px;
}

.detail-material-add {
  position: relative;
  margin-bottom: 16px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px 16px;
  background: #f9fafb;
}

.detail-material-add-header {
  margin-bottom: 8px;
}

.detail-material-add-header .detail-card-title {
  margin: 0;
  font-size: 14px;
}

.detail-material-search-wrapper {
  position: relative;
}

.detail-mat-search-input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
  background: #fff;
  transition: border-color 0.15s;
}

.detail-mat-search-input:focus {
  outline: none;
  border-color: var(--color-primary, #4f46e5);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.btn-qty {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  background: #fff;
  color: #374151;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-qty:hover {
  background: #f3f4f6;
  border-color: #9ca3af;
}

.btn-remove-sm {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border: none;
  border-radius: 4px;
  background: transparent;
  color: #ef4444;
  font-size: 13px;
  cursor: pointer;
  margin-left: 4px;
  transition: all 0.15s;
}

.btn-remove-sm:hover {
  background: #fef2f2;
}

/* Detail History */
.detail-history {
  position: relative;
}

.history-entry {
  display: flex;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid #f3f4f6;
}

.history-entry:last-child {
  border-bottom: none;
}

.history-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #d1d5db;
  flex-shrink: 0;
  margin-top: 5px;
}

.history-dot.created { background: #10b981; }
.history-dot.updated { background: #3b82f6; }
.history-dot.deleted { background: #ef4444; }
.history-dot.status_changed { background: #f59e0b; }

.history-content {
  flex: 1;
  min-width: 0;
}

.history-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
}

.history-action {
  font-size: 13px;
  font-weight: 600;
  color: #1f2937;
}

.history-time {
  font-size: 12px;
  color: #9ca3af;
}

.history-changes {
  margin-top: 4px;
}

.history-change {
  font-size: 12px;
  color: #6b7280;
  padding: 2px 0;
}

.history-field {
  font-weight: 500;
  color: #374151;
  margin-right: 4px;
}

.history-old {
  text-decoration: line-through;
  color: #dc2626;
}

.history-arrow {
  margin: 0 4px;
  color: #9ca3af;
}

.history-new {
  color: #059669;
  font-weight: 500;
}

.loading-hint, .empty-hint {
  padding: 40px 24px;
  text-align: center;
  color: #9ca3af;
  font-size: 14px;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px 24px !important;
}

.empty-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.empty-icon {
  width: 48px;
  height: 48px;
  stroke: var(--color-text-muted, #9ca3af);
}

.empty-content p {
  color: var(--color-text-muted, #6b7280);
  font-size: 15px;
  margin: 0;
}

/* ============================
   Wizard Modal (wie Material)
   ============================ */
.wizard-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
  animation: overlayFadeIn 0.2s ease;
  backdrop-filter: blur(2px);
}
@keyframes overlayFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.wizard-modal {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 950px;
  height: 85vh;
  min-height: 600px;
  max-height: 900px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  animation: modalSlideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes modalSlideIn {
  from { opacity: 0; transform: scale(0.96) translateY(12px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}

/* Header */
.wizard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 24px;
  border-bottom: 1px solid #e5e7eb;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 10px;
}

.header-title h2 {
  font-size: 14px;
  font-weight: 700;
  color: #374151;
  letter-spacing: 0.3px;
  margin: 0;
}

.header-type-badge {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  flex-shrink: 0;
}
.header-type-badge.camp { background: #fef3c7; color: #92400e; }
.header-type-badge.event { background: #e0e7ff; color: #3730a3; }
.header-type-badge.external { background: #d1fae5; color: #065f46; }
.header-type-badge svg { width: 16px; height: 16px; }

.header-step-indicator {
  font-size: 11px;
  font-weight: 500;
  color: #9ca3af;
  background: #f3f4f6;
  padding: 2px 8px;
  border-radius: 10px;
}

.close-btn {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  padding: 6px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
  min-width: 36px;
  min-height: 36px;
}

.close-btn:hover {
  color: #ef4444;
  background: #fef2f2;
}

.close-btn:active {
  transform: scale(0.92);
}

/* Content Layout */
.wizard-content {
  display: flex;
  flex: 1;
  overflow: hidden;
  min-height: 0;
}

.wizard-form {
  flex: 1;
  padding: 24px;
  overflow-y: auto;
}

.wizard-sidebar {
  width: 280px;
  background: #f9fafb;
  border-left: 1px solid #e5e7eb;
  padding: 24px;
  overflow-y: auto;
}

.wizard-sidebar h3 {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
  margin: 0 0 16px 0;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Step Sections */
.step-section {
  margin-bottom: 24px;
  border-radius: 10px;
  border: 1px solid transparent;
  padding: 12px 16px;
  transition: all 0.25s ease;
}

.step-section:not(.collapsed) {
  background: #fafbfc;
  border-color: #e5e7eb;
}

.step-section.collapsed {
  padding: 8px 16px;
  margin-bottom: 8px;
  background: white;
  border-color: #f3f4f6;
}

.step-section.collapsed:hover {
  border-color: #d1d5db;
}

.step-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}

.step-section.collapsed .step-header {
  margin-bottom: 0;
}

.step-header.clickable {
  cursor: pointer;
}

.step-header.clickable:hover .step-title {
  color: var(--color-primary, #4f46e5);
}

.step-number {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: var(--color-primary, #4f46e5);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 600;
  flex-shrink: 0;
  transition: background 0.2s;
}

.step-number.done {
  background: #10b981;
  font-size: 12px;
}

.step-title {
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
  transition: color 0.2s;
  white-space: nowrap;
}

.step-section.collapsed .step-title {
  font-size: 13px;
  color: #6b7280;
}

/* Step summary (inline after title when collapsed) */
.step-summary {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
  flex-shrink: 0;
}

.summary-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
}

.summary-chip.activity { background: #dbeafe; color: #1d4ed8; }
.summary-chip.camp { background: #d1fae5; color: #065f46; }
.summary-chip.event { background: #fce7f3; color: #be185d; }
.summary-chip.external { background: #fef3c7; color: #92400e; }

.summary-icon {
  width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
}

.summary-icon svg {
  width: 100%;
  height: 100%;
}

.summary-text {
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.summary-truncate {
  max-width: 180px;
}

.edit-step-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: none;
  background: #f3f4f6;
  border-radius: 4px;
  color: #6b7280;
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.15s;
}

.edit-step-btn:hover {
  background: #e5e7eb;
  color: var(--color-primary, #4f46e5);
}

.step-content {
  padding-left: 36px;
}

/* Step slide transition */
.step-slide-enter-active {
  transition: all 0.25s ease;
}

.step-slide-leave-active {
  transition: all 0.2s ease;
}

.step-slide-enter-from {
  opacity: 0;
  max-height: 0;
  transform: translateY(-8px);
}

.step-slide-enter-to {
  opacity: 1;
  max-height: 500px;
}

.step-slide-leave-from {
  opacity: 1;
  max-height: 500px;
}

.step-slide-leave-to {
  opacity: 0;
  max-height: 0;
  transform: translateY(-8px);
}

.mt-8 {
  margin-top: 8px;
}

/* Type Options (wie Material-Typ-Auswahl) */
.type-options {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.type-option {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px 16px;
  border: 2px solid #e5e7eb;
  border-radius: 10px;
  background: white;
  cursor: pointer;
  transition: all 0.2s;
  text-align: left;
}

.type-option:hover {
  border-color: var(--color-primary, #4f46e5);
  background: #f9fafb;
}

.type-option.active {
  border-color: var(--color-primary, #4f46e5);
  background: #eef2ff;
}

.type-icon {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  color: var(--color-primary, #4f46e5);
}

.type-icon svg {
  width: 100%;
  height: 100%;
}

.type-icon.activity { color: #3b82f6; }
.type-icon.camp { color: #10b981; }
.type-icon.event { color: #ec4899; }
.type-icon.external { color: #f59e0b; }

.type-text {
  display: flex;
  flex-direction: column;
}

.type-name {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}

.type-desc {
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

/* Form Fields */
.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  margin-bottom: 6px;
}

.form-input::placeholder {
  color: #9ca3af;
}

select.form-input {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239ca3af' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 32px;
  cursor: pointer;
}

.form-label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 4px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

/* 3-Spalten für Aktivität (Datum + Von + Bis) */
.form-row:has(input[type="date"]):has(input[type="time"]) {
  grid-template-columns: 1.5fr 1fr 1fr;
}

/* Zeitbereich Labels */
.time-section-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 12px;
  padding-bottom: 6px;
  border-bottom: 1px solid #e5e7eb;
}

.time-section-label svg {
  flex-shrink: 0;
  color: var(--color-primary, #4f46e5);
}

.mt-16 {
  margin-top: 16px;
}

/* Dauer-Anzeige */
.duration-info {
  padding: 8px 12px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  font-size: 13px;
  color: #166534;
  margin-top: 8px;
}

/* Preview Sidebar */
.activity-preview {
  text-align: center;
  margin-bottom: 20px;
  padding: 20px 16px;
  background: white;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
}

.preview-icon {
  width: 48px;
  height: 48px;
  margin: 0 auto 12px;
}

.preview-icon svg {
  width: 100%;
  height: 100%;
}

.preview-type-icon {
  width: 48px;
  height: 48px;
  margin: 0 auto;
}

.preview-type-icon svg {
  width: 100%;
  height: 100%;
}

.preview-icon.activity .preview-type-icon { color: #3b82f6; }
.preview-icon.camp .preview-type-icon { color: #10b981; }
.preview-icon.event .preview-type-icon { color: #ec4899; }
.preview-icon.external .preview-type-icon { color: #f59e0b; }
.preview-icon.empty { color: #9ca3af; }

.preview-info h4 {
  font-size: 16px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
  word-break: break-word;
}

.preview-badge {
  display: inline-block;
  padding: 3px 12px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 500;
}

.preview-badge.activity { background: #dbeafe; color: #1d4ed8; }
.preview-badge.camp { background: #d1fae5; color: #065f46; }
.preview-badge.event { background: #fce7f3; color: #be185d; }
.preview-badge.external { background: #fef3c7; color: #92400e; }

.preview-details {
  background: white;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  padding: 14px 16px;
  margin-bottom: 16px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
}

.info-row + .info-row {
  border-top: 1px solid #f3f4f6;
}

.info-label {
  font-size: 12px;
  color: #6b7280;
}

.info-value {
  font-size: 13px;
  font-weight: 500;
  color: #1f2937;
}

.preview-missing {
  background: #fef3c7;
  border: 1px solid #fde68a;
  border-radius: 10px;
  padding: 14px 16px;
}

.missing-header {
  font-size: 12px;
  font-weight: 600;
  color: #92400e;
  margin-bottom: 6px;
}

.preview-missing ul {
  margin: 0;
  padding-left: 18px;
  list-style: disc;
}

.preview-missing li {
  font-size: 12px;
  color: #92400e;
  margin-bottom: 2px;
}

/* Footer */
.wizard-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 24px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
  border-radius: 0 0 12px 12px;
}

.footer-actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.missing-hint {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #92400e;
}

.missing-icon {
  font-size: 14px;
}

.missing-text {
  font-size: 12px;
}

/* === Group Step === */
.group-search {
  margin-bottom: 12px;
}

.group-list {
  max-height: 340px;
  overflow-y: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.group-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  cursor: pointer;
  border-bottom: 1px solid #f3f4f6;
  transition: all 0.15s;
}

.group-item:last-child {
  border-bottom: none;
}

.group-item:hover:not(.disabled) {
  background: #f0f9ff;
}

.group-item.selected {
  background: #eff6ff;
  border-left: 3px solid var(--color-primary, #4f46e5);
}

/* Ausgegraut: nicht-wählbare Gruppen */
.group-item.disabled {
  cursor: not-allowed;
  opacity: 0.5;
  background: #f9fafb;
}
.group-item.disabled:hover {
  background: #f9fafb;
}
.group-item.disabled .group-item-name {
  color: #9ca3af;
}
.group-item.disabled .group-item-icon {
  color: #d1d5db;
}

/* Mitglied-Highlight */
.group-item.is-member {
  border-left: 3px solid transparent;
}
.group-item.is-member:hover {
  border-left-color: #c7d2fe;
}

.group-item-icon {
  flex-shrink: 0;
  color: #6b7280;
  width: 18px;
  height: 18px;
}

.group-item.selected .group-item-icon {
  color: var(--color-primary, #4f46e5);
}

.group-item-info {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.group-item-name {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.group-member-count {
  font-size: 11px;
  color: #9ca3af;
  white-space: nowrap;
}

.group-item-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.group-role-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 10px;
  font-weight: 500;
  white-space: nowrap;
}

.group-role-badge.role-leader {
  background: #fef3c7;
  color: #92400e;
}

.group-role-badge.role-member {
  background: #e0e7ff;
  color: #4338ca;
}

.group-role-badge.role-none {
  background: #f3f4f6;
  color: #9ca3af;
  font-weight: 400;
}

.group-item-check {
  flex-shrink: 0;
  color: var(--color-primary, #4f46e5);
}

.summary-chip.group {
  background: #e0e7ff;
  color: #4338ca;
}

/* === Material Step === */
.step-hint {
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 12px;
}

/* === Material Autocomplete Suche === */
.mat-autocomplete {
  position: relative;
  margin-bottom: 12px;
}

.mat-search-wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.mat-search-icon {
  position: absolute;
  left: 12px;
  color: #9ca3af;
  pointer-events: none;
}

.mat-search-input {
  padding-left: 36px !important;
  font-size: 14px;
}

.mat-search-spinner {
  position: absolute;
  right: 12px;
  font-size: 16px;
  animation: spin 1s linear infinite;
  color: #6b7280;
}

/* Dropdown */
.mat-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 50;
  background: white;
  border: 1px solid #e5e7eb;
  border-top: none;
  border-radius: 0 0 8px 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  max-height: 320px;
  overflow-y: auto;
}

.mat-dropdown-loading,
.mat-dropdown-empty {
  padding: 16px;
  text-align: center;
  color: #9ca3af;
  font-size: 13px;
}

.mat-dropdown-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.1s;
}

.mat-dropdown-item:last-child {
  border-bottom: none;
}

.mat-dropdown-item:hover:not(.already-added):not(.unavailable) {
  background: #f0fdf4;
}

.mat-dropdown-item.active:not(.already-added):not(.unavailable) {
  background: #f0fdf4;
}

.mat-dropdown-item.already-added {
  background: #f0f9ff;
}

.mat-dropdown-item.unavailable {
  opacity: 0.5;
}

.mat-dropdown-info {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  flex: 1;
}

.mat-dropdown-name {
  font-size: 14px;
  font-weight: 500;
  color: #1f2937;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mat-source-switch {
  display: inline-flex;
  gap: 4px;
  padding: 4px;
  margin: 8px 0;
  background: #f3f4f6;
  border-radius: 8px;
}

.mat-source-btn {
  border: none;
  background: transparent;
  color: #4b5563;
  font-size: 12px;
  font-weight: 600;
  border-radius: 6px;
  padding: 6px 10px;
  cursor: pointer;
}

.mat-source-btn.active {
  background: #ffffff;
  color: #1f2937;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.mat-source-hint {
  margin: 8px 0;
  font-size: 12px;
  color: #6b7280;
}

.js-order-card {
  border: 1px solid #fde68a;
  background: #fffbeb;
  border-radius: 8px;
  padding: 10px;
  margin: 8px 0 10px;
}

.js-order-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.js-order-body {
  margin-top: 8px;
}

.js-order-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 8px;
}

.mat-source-badge {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 6px;
  border-radius: 999px;
  border: 1px solid #f59e0b;
  background: #fffbeb;
  color: #92400e;
  font-size: 10px;
  font-weight: 700;
  vertical-align: middle;
}

.mat-dropdown-stock {
  font-size: 12px;
  flex-shrink: 0;
}

.mat-pack-hint {
  font-size: 10px;
  margin-left: 2px;
}

.mat-dropdown-actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  margin-left: 8px;
}

.mat-quick-btn {
  padding: 3px 10px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: white;
  color: #374151;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}

.mat-quick-btn:hover {
  background: var(--color-primary, #4f46e5);
  color: white;
  border-color: var(--color-primary, #4f46e5);
}

.mat-quick-btn.sm {
  padding: 2px 6px;
  font-size: 11px;
}

/* Set-Buttons Styling */
.mat-set-btn {
  background: #eff6ff;
  border-color: #93c5fd;
  color: #2563eb;
}

.mat-set-btn:hover {
  background: #2563eb;
  color: white;
  border-color: #2563eb;
}

.mat-btn-divider {
  color: #d1d5db;
  font-size: 14px;
  margin: 0 2px;
  user-select: none;
}

/* Pack-Badge (neben Materialname) */
.mat-pack-badge {
  display: inline-block;
  font-size: 10px;
  font-weight: 500;
  color: #6366f1;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  border-radius: 4px;
  padding: 1px 5px;
  margin-left: 6px;
  vertical-align: middle;
}

.mat-already-badge {
  font-size: 11px;
  color: #2563eb;
  font-weight: 500;
}

.mat-unavailable-badge {
  font-size: 11px;
  color: #dc2626;
  font-weight: 500;
}

/* Dropdown Transition */
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}
.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

/* === Ausgewählte Material-Liste === */
.mat-selected {
  margin-top: 16px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.mat-selected-header {
  padding: 8px 12px;
  background: #eff6ff;
  font-size: 13px;
  color: #1e40af;
  display: flex;
  align-items: center;
  gap: 6px;
  border-bottom: 1px solid #dbeafe;
}

.mat-selected-list {
  max-height: 240px;
  overflow-y: auto;
}

.mat-selected-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 12px;
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.1s;
}

.mat-selected-row:last-child {
  border-bottom: none;
}

.mat-selected-row:hover {
  background: #f9fafb;
}

.mat-selected-name {
  font-size: 13px;
  font-weight: 500;
  color: #1f2937;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1;
  min-width: 0;
}

.mat-selected-controls {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
  margin-left: 8px;
}

.mat-quick-add {
  display: flex;
  gap: 2px;
  margin-left: 4px;
}

.mat-avail-hint {
  font-size: 11px;
  color: #9ca3af;
  margin-left: 4px;
  white-space: nowrap;
}

.material-qty.qty-exceeded {
  color: #dc2626;
  font-weight: 700;
}

.js-qty-warning {
  font-size: 11px;
  color: #dc2626;
  font-weight: 600;
  white-space: nowrap;
}

.js-qty-hint {
  font-size: 11px;
  color: #b45309;
  white-space: nowrap;
}

.text-green { color: #059669; }
.text-red { color: #dc2626; }

.mat-empty-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #d97706;
  margin-top: 8px;
  padding: 8px 12px;
  background: #fffbeb;
  border: 1px solid #fef3c7;
  border-radius: 6px;
}
.mat-empty-hint svg {
  flex-shrink: 0;
  color: #f59e0b;
}
.text-muted { color: #9ca3af; }

.btn-qty {
  width: 28px;
  height: 28px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  background: white;
  font-size: 16px;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
}

.btn-qty:hover:not(:disabled) {
  background: #f3f4f6;
  border-color: #9ca3af;
}

.btn-qty:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.material-qty {
  font-size: 14px;
  font-weight: 600;
  min-width: 24px;
  text-align: center;
  color: var(--color-primary, #4f46e5);
}

.btn-remove {
  width: 24px;
  height: 24px;
  border: none;
  background: none;
  font-size: 14px;
  color: #dc2626;
  cursor: pointer;
  margin-left: 4px;
  border-radius: 4px;
  transition: background 0.15s;
}

.btn-remove:hover {
  background: #fef2f2;
}

.summary-chip.material {
  background: #dbeafe;
  color: #1d4ed8;
}

/* === Draft/Save Indicator === */
.footer-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.draft-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #6b7280;
  padding: 4px 10px;
  background: #f3f4f6;
  border-radius: 12px;
}

.draft-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #f59e0b;
}

.draft-time {
  color: #9ca3af;
}

.saving-indicator {
  font-size: 12px;
  color: #6b7280;
  animation: pulse 1s infinite;
}

.footer-kbd-hint {
  font-size: 11px;
  color: #9ca3af;
  display: flex;
  align-items: center;
  gap: 4px;
}
.footer-kbd-hint kbd {
  display: inline-block;
  padding: 1px 5px;
  font-size: 10px;
  font-family: inherit;
  color: #6b7280;
  background: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 3px;
  box-shadow: 0 1px 0 #d1d5db;
}

/* Loading Button */
.btn-loading {
  pointer-events: none;
  opacity: 0.8;
}
.btn-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* ═══ Adress-Picker Styles ═══ */
.address-picker-divider {
  display: flex;
  align-items: center;
  margin: 16px 0 12px;
  gap: 12px;
}
.address-picker-divider::before,
.address-picker-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #e2e8f0;
}
.address-picker-divider span {
  font-size: 13px;
  font-weight: 600;
  color: #64748b;
  white-space: nowrap;
}

/* Ausgewählte Adresse */
.selected-address-card {
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 10px;
  padding: 14px;
  margin-bottom: 12px;
}
.address-card-content {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.address-card-icon {
  color: #16a34a;
  flex-shrink: 0;
  margin-top: 2px;
}
.address-card-details {
  flex: 1;
  min-width: 0;
}
.address-card-name {
  font-weight: 600;
  font-size: 14px;
  color: #15803d;
  margin-bottom: 2px;
}
.address-card-line {
  font-size: 13px;
  color: #374151;
  line-height: 1.4;
}
.address-card-info {
  font-size: 12px;
  color: #6b7280;
  font-style: italic;
  margin-top: 4px;
}
.address-card-remove {
  background: none;
  border: none;
  color: #9ca3af;
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  flex-shrink: 0;
  transition: all 0.15s;
}
.address-card-remove:hover {
  background: #fee2e2;
  color: #dc2626;
}

/* Adress-Suche */
.address-picker {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.address-search-box {
  position: relative;
}
.address-search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  pointer-events: none;
}
.address-search-input {
  padding-left: 36px !important;
}

/* Ergebnisliste */
.address-results-list {
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fafafa;
}
.address-result-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  cursor: pointer;
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.1s;
}
.address-result-item:last-child {
  border-bottom: none;
}
.address-result-item:hover {
  background: #eef2ff;
}
.address-result-icon {
  color: #6366f1;
  flex-shrink: 0;
  margin-top: 2px;
}
.address-result-details {
  flex: 1;
  min-width: 0;
}
.address-result-name {
  font-size: 13px;
  font-weight: 600;
  color: #1e293b;
  display: flex;
  align-items: center;
  gap: 6px;
}
.address-type-tag {
  font-size: 10px;
  font-weight: 500;
  padding: 1px 6px;
  background: #e0e7ff;
  color: #4338ca;
  border-radius: 4px;
}
.address-result-line {
  font-size: 12px;
  color: #64748b;
  line-height: 1.4;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.address-no-results {
  padding: 16px;
  text-align: center;
  font-size: 13px;
  color: #94a3b8;
  font-style: italic;
}
.address-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px;
  font-size: 13px;
  color: #64748b;
}
.address-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid #e2e8f0;
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: addr-spin 0.6s linear infinite;
}
@keyframes addr-spin {
  to { transform: rotate(360deg); }
}

/* Neue Adresse Button */
.btn-create-address {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: #eef2ff;
  border: 1px dashed #a5b4fc;
  border-radius: 8px;
  color: #4338ca;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
  align-self: flex-start;
}
.btn-create-address:hover {
  background: #e0e7ff;
  border-color: #818cf8;
  border-style: solid;
}

.department-invite-panel {
  margin-top: 10px;
  padding: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #f8fafc;
  display: grid;
  gap: 8px;
}

.department-invite-results {
  display: grid;
  gap: 6px;
}

.department-invite-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 8px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
}

.department-invite-info {
  min-width: 0;
}

.department-invite-name {
  font-size: 13px;
  font-weight: 600;
  color: #111827;
}

.department-invite-org {
  font-size: 12px;
  color: #64748b;
}

.department-invited-list {
  margin-top: 4px;
  display: grid;
  gap: 6px;
}

.department-invited-title {
  font-size: 12px;
  font-weight: 600;
  color: #475569;
}

.department-invited-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 8px;
  background: #eef2ff;
  font-size: 12px;
  color: #3730a3;
}

.invite-status-badge {
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
}

.invite-status-badge.pending {
  background: #fef3c7;
  color: #92400e;
}

.invite-status-badge.accepted {
  background: #dcfce7;
  color: #166534;
}

.invite-status-badge.rejected {
  background: #fee2e2;
  color: #991b1b;
}

/* ═══════════════════════════════════════════════ */
/* WORKFLOW TABS: Packliste, Meldungen, Rückgabe  */
/* ═══════════════════════════════════════════════ */

/* ── Packliste: 4-Stufen Workflow Board ── */
.pack-workflow {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

/* Stufen-Tabs */
.pack-stage-tabs {
  display: flex;
  gap: 4px;
  background: #f1f5f9;
  border-radius: 10px;
  padding: 4px;
}

.pack-stage-tab {
  flex: 1;
  padding: 8px 12px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: #64748b;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  text-align: center;
}

.pack-stage-tab:hover {
  background: #e2e8f0;
  color: #334155;
}

.pack-stage-tab.active {
  background: white;
  color: #4f46e5;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.stage-arrow {
  color: #94a3b8;
  margin: 0 2px;
}

.pack-stage-tab.active .stage-arrow {
  color: #a5b4fc;
}

.pack-panels {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  min-height: 200px;
}

.pack-panel {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.pack-panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #f8fafc;
  border-bottom: 1px solid #e5e7eb;
  font-weight: 600;
  font-size: 13px;
  color: #1e293b;
}

.pack-panel-header-done {
  background: #f0fdf4;
  border-bottom-color: #bbf7d0;
}

.pack-panel-title {
  text-transform: uppercase;
  letter-spacing: 0.3px;
  font-size: 12px;
}

.pack-panel-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 24px;
  padding: 0 8px;
  border-radius: 12px;
  background: #e5e7eb;
  font-size: 12px;
  font-weight: 700;
  color: #374151;
}

.pack-panel-header-done .pack-panel-count {
  background: #bbf7d0;
  color: #166534;
}

.pack-panel-empty {
  padding: 32px 16px;
  text-align: center;
  color: #9ca3af;
  font-size: 14px;
}

/* Gruppen (Kategorien) */
.pack-group {
  border-bottom: 1px solid #f1f5f9;
}

.pack-group:last-child {
  border-bottom: none;
}

.pack-group-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 16px;
  cursor: pointer;
  background: #fafbfc;
  border-bottom: 1px solid #f1f5f9;
  user-select: none;
  transition: background 0.15s;
}

.pack-group-header:hover {
  background: #f1f5f9;
}

.pack-group-header-done {
  background: #f7fef9;
}

.pack-group-header-done:hover {
  background: #ecfdf5;
}

.pack-group-name {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  color: #6b7280;
}

.pack-group-toggle {
  font-size: 10px;
  color: #9ca3af;
}

/* Pack Cards (einzelne Items) */
.pack-card {
  padding: 10px 16px;
  border-bottom: 1px solid #f8fafc;
  transition: background 0.15s;
}

.pack-card:hover {
  background: #f8fafc;
}

.pack-card:last-child {
  border-bottom: none;
}

.pack-card-done {
  opacity: 0.8;
}

.pack-card-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

.pack-card-info {
  flex: 1;
  min-width: 0;
}

.pack-card-name {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pack-card-detail {
  display: block;
  font-size: 11px;
  color: #9ca3af;
  margin-top: 2px;
}

.pack-card-actions {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

/* Schnell-Pack Button (Menge + Check) */
.btn-pack-quick {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  background: #eff6ff;
  border: 1px solid #93c5fd;
  border-radius: 6px;
  color: #2563eb;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-pack-quick:hover {
  background: #2563eb;
  color: white;
  border-color: #2563eb;
}

.pack-qty-badge {
  font-size: 13px;
  font-weight: 700;
}

/* 3-Punkte Menu Button */
.btn-pack-menu {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  padding: 0;
  background: none;
  border: 1px solid transparent;
  border-radius: 6px;
  color: #9ca3af;
  cursor: pointer;
  transition: all 0.15s;
}

.btn-pack-menu:hover {
  background: #f1f5f9;
  border-color: #e5e7eb;
  color: #374151;
}

/* Pack-Edit Modal */
.pack-edit-modal {
  max-width: 420px;
}

.pack-edit-name {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 4px;
}

.pack-edit-ordered {
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 16px;
}

.pack-edit-field {
  margin-bottom: 14px;
}

.pack-edit-set-btns {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 8px;
  flex-wrap: wrap;
}

.pack-edit-set-hint {
  font-size: 11px;
  color: #9ca3af;
  margin-left: 4px;
}

.pack-edit-field label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  margin-bottom: 4px;
}

/* Move-Inline Input (immer sichtbar) */
.pack-move-inline {
  display: flex;
  align-items: center;
  gap: 0;
}

.pack-move-input {
  width: 48px;
  height: 32px;
  padding: 2px 4px;
  border: 1px solid #d1d5db;
  border-right: none;
  border-radius: 6px 0 0 6px;
  font-size: 14px;
  font-weight: 600;
  text-align: center;
  color: #1e293b;
  outline: none;
  background: white;
}

.pack-move-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.15);
  z-index: 1;
  position: relative;
}

.btn-move-arrow {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 32px;
  padding: 0;
  background: #2563eb;
  border: 1px solid #2563eb;
  border-radius: 0 6px 6px 0;
  color: white;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-move-arrow:hover {
  background: #1d4ed8;
  border-color: #1d4ed8;
}

.btn-move-arrow:active {
  background: #1e40af;
}

/* Rechte Seite: Zurück-Button + Input */
.pack-card-actions-left {
  display: flex;
  align-items: center;
  gap: 0;
  flex-shrink: 0;
  margin-right: 8px;
}

.btn-moveback-arrow {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 32px;
  padding: 0;
  background: #64748b;
  border: 1px solid #64748b;
  border-radius: 6px 0 0 6px;
  color: white;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-moveback-arrow:hover {
  background: #475569;
  border-color: #475569;
}

.btn-moveback-arrow:active {
  background: #334155;
}

.pack-moveback-input {
  width: 48px;
  height: 32px;
  padding: 2px 4px;
  border: 1px solid #d1d5db;
  border-left: none;
  border-radius: 0 6px 6px 0;
  font-size: 14px;
  font-weight: 600;
  text-align: center;
  color: #1e293b;
  outline: none;
  background: white;
}

.pack-moveback-input:focus {
  border-color: #64748b;
  box-shadow: 0 0 0 2px rgba(100, 116, 139, 0.15);
  z-index: 1;
  position: relative;
}

/* Direkte Issue-Buttons (Verlust/Reparatur) im Am-Event-Stage */
.btn-issue-quick {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 8px;
  border-radius: 5px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  border: 1px solid;
  white-space: nowrap;
}

.btn-issue-loss {
  background: #fef2f2;
  border-color: #fca5a5;
  color: #dc2626;
}

.btn-issue-loss:hover {
  background: #dc2626;
  color: white;
  border-color: #dc2626;
}

.btn-issue-repair {
  background: #fffbeb;
  border-color: #fcd34d;
  color: #b45309;
}

.btn-issue-repair:hover {
  background: #b45309;
  color: white;
  border-color: #b45309;
}

.btn-issue-consumed {
  background: #f0fdf4;
  border-color: #86efac;
  color: #16a34a;
}

.btn-issue-consumed:hover {
  background: #16a34a;
  color: white;
  border-color: #16a34a;
}

/* Buttons in der Progress Bar */
.pack-progress-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-move-all {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
}

.btn-progress-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 600;
  padding: 5px 14px;
  border-radius: 6px;
}

/* Voller Fortschritt: Puls-Animation */
.btn-progress-action:not(.btn-progress-warn) {
  animation: pulseGlow 2s ease-in-out infinite;
}

@keyframes pulseGlow {
  0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.3); }
  50% { box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
}

/* Nicht komplett: Warnung-Stil */
.btn-progress-warn {
  opacity: 0.75;
  border-style: dashed;
}

.btn-progress-warn:hover {
  opacity: 1;
}

.btn-progress-warn-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 1px 6px;
  border-radius: 10px;
  background: rgba(0, 0, 0, 0.15);
  font-size: 10px;
  font-weight: 700;
  margin-left: 2px;
}

.pack-progress-fill.progress-complete {
  background: linear-gradient(90deg, #22c55e, #16a34a);
}

/* Action-Buttons im Edit Modal */
.pack-edit-actions-bar {
  display: flex;
  gap: 8px;
  margin: 12px 0 16px;
  padding: 10px;
  background: #f8fafc;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

@media (max-width: 768px) {
  .pack-panels {
    grid-template-columns: 1fr;
  }
  .pack-stage-tabs {
    flex-direction: column;
  }
}

/* Workflow-Tabelle (Pack + Return) */
.workflow-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.workflow-table thead th {
  background: #f8fafc;
  border-bottom: 2px solid #e2e8f0;
  padding: 10px 12px;
  text-align: left;
  font-weight: 600;
  font-size: 12px;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.workflow-table tbody td {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

.workflow-table tbody tr:hover {
  background: #f8fafc;
}

.workflow-table .col-qty {
  width: 80px;
  text-align: center;
}

.workflow-table .col-condition {
  width: 160px;
}

.workflow-table .col-notes {
  width: 50px;
  text-align: center;
}

.workflow-table .col-status-icon {
  width: 40px;
  text-align: center;
}

.workflow-table .material-name {
  font-weight: 500;
  color: #1e293b;
}

/* Quantity Input */
.qty-input {
  width: 60px;
  padding: 4px 8px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 13px;
  text-align: center;
  background: white;
}

.qty-input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
}

.qty-input.qty-damaged,
.qty-input.qty-missing {
  border-color: #fca5a5;
}

.qty-input.qty-damaged:focus,
.qty-input.qty-missing:focus {
  border-color: #ef4444;
  box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15);
}

/* Condition Select */
.condition-select {
  padding: 4px 8px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 12px;
  background: white;
  cursor: pointer;
}

.condition-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 500;
}

.condition-badge.ok { background: #d1fae5; color: #065f46; }
.condition-badge.leicht_beschaedigt { background: #fef3c7; color: #92400e; }
.condition-badge.beschaedigt { background: #fed7aa; color: #9a3412; }
.condition-badge.defekt { background: #fee2e2; color: #991b1b; }

/* Row States */
.row-complete {
  background: #f0fdf4 !important;
}

.row-incomplete {
  background: #fffbeb !important;
}

.row-difference {
  background: #fff1f2 !important;
}

/* Pack Progress Bar */
.pack-progress-bar {
  margin-bottom: 16px;
  padding: 12px 16px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.pack-progress-info {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: #475569;
  margin-bottom: 6px;
}

.pack-percent {
  font-weight: 600;
  color: #4f46e5;
}

.pack-progress-track {
  height: 6px;
  background: #e2e8f0;
  border-radius: 3px;
  overflow: hidden;
}

.pack-progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #6366f1, #4f46e5);
  border-radius: 3px;
  transition: width 0.3s ease;
}

.js-workflow-summary {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  margin: -6px 0 14px;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid #fde68a;
  background: #fffbeb;
  color: #92400e;
  font-size: 13px;
}

.pack-qr-print-bar {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 16px;
  padding: 12px 16px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}
.pack-qr-box {
  display: flex;
  align-items: center;
  gap: 12px;
}
.pack-qr-img {
  width: 64px;
  height: 64px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
}
.pack-qr-label {
  font-size: 12px;
  color: #6b7280;
}
.pack-print-btn {
  margin-left: auto;
}

/* Verbrauchsmaterial Tab */
/* ═══ Kosten-Tab ═══ */
.costs-overview {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.costs-section {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 16px;
}

.costs-section-warn {
  border-color: #fca5a5;
  background: #fef2f2;
}

.costs-section-title {
  font-size: 15px;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 12px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.costs-icon {
  font-size: 16px;
}

.costs-empty {
  font-size: 13px;
  color: #9ca3af;
  font-style: italic;
}

.costs-table {
  display: flex;
  flex-direction: column;
}

.costs-row {
  display: grid;
  grid-template-columns: 1fr 70px 80px 90px 100px;
  gap: 8px;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid #f3f4f6;
  font-size: 13px;
}

.costs-row:last-child { border-bottom: none; }

.costs-row-header {
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  border-bottom: 2px solid #e5e7eb;
}

.costs-col-name {
  font-weight: 500;
  color: #1f2937;
}

.costs-col-qty,
.costs-col-used,
.costs-col-price {
  text-align: center;
  color: #6b7280;
}

.costs-col-total {
  text-align: right;
  font-weight: 500;
  color: #1f2937;
}

.costs-loss-desc {
  font-weight: 400;
  color: #dc2626;
  font-size: 12px;
}

.costs-subtotal {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0 0;
  margin-top: 4px;
  border-top: 2px solid #e5e7eb;
  font-size: 14px;
  color: #374151;
}

.costs-total-section {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 16px;
}

.costs-total-section.costs-final {
  background: #f0fdf4;
  border-color: #86efac;
}

.costs-total-label {
  font-size: 16px;
  margin-bottom: 12px;
}

.costs-total-hint {
  font-size: 12px;
  color: #9ca3af;
  font-weight: 400;
  margin-left: 8px;
}

.costs-total-rows {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.costs-total-row {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
  color: #374151;
}

.costs-grand-total {
  font-size: 18px;
  font-weight: 700;
  color: #1f2937;
  padding-top: 8px;
  border-top: 2px solid #cbd5e1;
}

.costs-final .costs-grand-total {
  color: #059669;
  border-top-color: #86efac;
}

.consumables-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.consumable-hint {
  padding: 10px 14px;
  background: #fffbeb;
  border: 1px solid #fcd34d;
  border-radius: 8px;
  font-size: 12px;
  color: #92400e;
  margin-bottom: 8px;
}

.consumable-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  gap: 16px;
  transition: border-color 0.15s;
}

.consumable-card:hover {
  border-color: #d97706;
}

.consumable-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.consumable-name {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
}

.consumable-ordered {
  font-size: 12px;
  color: #6b7280;
}

.consumable-used {
  font-size: 12px;
  color: #b45309;
  font-weight: 600;
}

.consumable-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.consumable-qty-row {
  display: flex;
  align-items: center;
  gap: 0;
}

.consumable-qty-input {
  width: 48px;
  height: 32px;
  padding: 2px 4px;
  border: 1px solid #d1d5db;
  border-left: none;
  border-right: none;
  font-size: 14px;
  font-weight: 600;
  text-align: center;
  color: #1e293b;
  outline: none;
}

.consumable-qty-input:focus {
  border-color: #d97706;
  box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.15);
  z-index: 1;
  position: relative;
}

.consumable-qty-row .btn-qty {
  border-radius: 0;
}

.consumable-qty-row .btn-qty:first-child {
  border-radius: 6px 0 0 6px;
}

.consumable-qty-row .btn-qty:last-child {
  border-radius: 0 6px 6px 0;
}

/* Verbrauch-Historie */
.consumable-history {
  margin-top: 24px;
  border-top: 1px solid #e5e7eb;
  padding-top: 16px;
}

.consumable-history h4 {
  font-size: 13px;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  margin: 0 0 10px;
}

.consumable-history-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid #f1f5f9;
  font-size: 13px;
}

.consumable-history-item:last-child {
  border-bottom: none;
}

.consumable-history-name {
  font-weight: 500;
  color: #1e293b;
}

.consumable-history-qty {
  font-weight: 700;
  color: #b45309;
}

.consumable-history-time {
  color: #9ca3af;
  font-size: 11px;
}

.consumable-history-desc {
  color: #6b7280;
  font-size: 12px;
  font-style: italic;
}

/* Issue Report Styles */
.issue-actions {
  margin-bottom: 16px;
}

.issue-form {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
}

.issue-form h4 {
  margin: 0 0 12px;
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
}

.issue-form .form-row {
  margin-bottom: 12px;
}

.issue-form .form-row label {
  display: block;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
  margin-bottom: 4px;
}

.issue-required {
  color: #ef4444;
  font-weight: 700;
}

.input-required {
  border-color: #fca5a5 !important;
}
.input-required::placeholder {
  color: #f87171;
}

.issue-form .form-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
}

/* Issue Material Autocomplete */
.issue-mat-autocomplete {
  position: relative;
}

.issue-mat-selected {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #eff6ff;
  border: 1px solid #93c5fd;
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 13px;
  color: #1e40af;
}

.issue-mat-selected-name {
  flex: 1;
  font-weight: 500;
}

.issue-mat-clear {
  background: none;
  border: none;
  font-size: 18px;
  color: #6b7280;
  cursor: pointer;
  padding: 0 2px;
  line-height: 1;
}
.issue-mat-clear:hover { color: #ef4444; }

.issue-mat-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 50;
  background: white;
  border: 1px solid #e5e7eb;
  border-top: none;
  border-radius: 0 0 8px 8px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  max-height: 260px;
  overflow-y: auto;
}

.issue-mat-dropdown-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  font-size: 13px;
  cursor: pointer;
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.1s;
}
.issue-mat-dropdown-item:last-child { border-bottom: none; }
.issue-mat-dropdown-item:hover { background: #f0fdf4; }

.issue-mat-dropdown-general {
  color: #6b7280;
  font-style: italic;
}
.issue-mat-dropdown-general:hover { background: #f8fafc; }

.issue-mat-dropdown-empty {
  padding: 12px;
  text-align: center;
  color: #9ca3af;
  font-size: 12px;
}

.issue-mat-item-name {
  display: flex;
  align-items: center;
  gap: 4px;
  font-weight: 500;
  color: #1f2937;
}

.issue-mat-item-qty {
  font-size: 11px;
  color: #6b7280;
  flex-shrink: 0;
}

.issues-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.issue-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px 16px;
  border-left: 4px solid #f59e0b;
}

.issue-card.resolved {
  opacity: 0.7;
  border-left-color: #10b981;
}

.issue-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
  flex-wrap: wrap;
}

.issue-type-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.issue-type-badge.damage { background: #fee2e2; color: #991b1b; }
.issue-type-badge.repair { background: #fed7aa; color: #9a3412; }
.issue-type-badge.loss { background: #fef3c7; color: #92400e; }
.issue-type-badge.consumption { background: #dbeafe; color: #1e40af; }

.issue-material {
  font-weight: 500;
  color: #1e293b;
}

.issue-qty {
  color: #6b7280;
  font-size: 12px;
}

.issue-time {
  font-size: 11px;
  color: #94a3b8;
  margin-left: auto;
}

.issue-description {
  font-size: 13px;
  color: #475569;
  margin-bottom: 8px;
  line-height: 1.4;
}

.issue-footer {
  display: flex;
  align-items: center;
  gap: 8px;
}

.issue-resolved {
  font-size: 12px;
  color: #059669;
}

.issue-workshop-state {
  font-size: 12px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  background: #e5e7eb;
  color: #4b5563;
}

.issue-workshop-state.open {
  background: #fef3c7;
  color: #92400e;
}

.issue-workshop-state.in_progress {
  background: #dbeafe;
  color: #1e40af;
}

.issue-workshop-state.waiting_parts {
  background: #fce7f3;
  color: #9d174d;
}

.issue-workshop-state.completed {
  background: #dcfce7;
  color: #166534;
}

.issue-workshop-state.cancelled {
  background: #f3f4f6;
  color: #6b7280;
}

.issue-workshop-missing {
  font-size: 12px;
  color: #b45309;
}

/* Button "In Werkstatt öffnen" – sichtbarer Text auch beim Hover */
.btn-workshop-open {
  background: #f3f4f6;
  border: 1px solid #9ca3af;
  color: #1f2937;
}
.btn-workshop-open:hover {
  background: #4f46e5;
  border-color: #4f46e5;
  color: white;
}

/* Return Summary */
.return-summary {
  margin-bottom: 16px;
  padding: 12px 16px;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.return-summary .summary-item {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}

.return-summary .summary-item.has-issues {
  color: #dc2626;
  font-weight: 600;
}

.summary-label {
  color: #475569;
}

.summary-value {
  font-weight: 600;
}

.text-danger {
  color: #dc2626 !important;
  font-weight: 600;
}

/* Button Styles (Workflow) */
/* Notes Hint */
.notes-hint {
  cursor: help;
  font-size: 14px;
}

/* Status Icons */
.check-icon, .partial-icon, .pending-icon, .warning-icon {
  font-size: 16px;
}

/* Workflow Status-Flow Indicator (optional in Übersicht) */
.workflow-progress {
  display: flex;
  align-items: center;
  gap: 2px;
  margin-top: 8px;
}

.workflow-step {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #e2e8f0;
}

.workflow-step.active {
  background: #6366f1;
}

.workflow-step.done {
  background: #10b981;
}

.workflow-step.cancelled {
  background: #ef4444;
}

/* ═══════════════════════════════════════════════════ */
/* ═══ QUICK MODE ═══════════════════════════════════ */
/* ═══════════════════════════════════════════════════ */

.wizard-content.quick-mode {
  display: flex;
  justify-content: flex-start;
}

.quick-form {
  max-width: 520px;
  width: 100%;
  padding: 24px 20px 24px 6px !important;
}

.quick-divider {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 20px 0 12px;
}
.quick-divider::before,
.quick-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #e5e7eb;
}
.quick-divider span {
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #9ca3af;
  white-space: nowrap;
}

.quick-expand-section {
  margin-bottom: 8px;
}

.quick-expand-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 10px 12px;
  border: 1px dashed #d1d5db;
  border-radius: 8px;
  background: transparent;
  color: #6b7280;
  font-size: 13px;
  cursor: pointer;
  transition: all 0.15s;
}
.quick-expand-btn:hover {
  border-color: #6366f1;
  color: #4f46e5;
  background: #f5f3ff;
}
.quick-expand-btn.expanded {
  border-color: #6366f1;
  border-style: solid;
  color: #4f46e5;
  background: #f5f3ff;
}
.quick-expand-chevron {
  margin-left: auto;
  transition: transform 0.2s;
}
.quick-expand-btn.expanded .quick-expand-chevron {
  transform: rotate(180deg);
}
.quick-expand-badge {
  background: #6366f1;
  color: white;
  border-radius: 10px;
  padding: 1px 7px;
  font-size: 11px;
  font-weight: 600;
}
.quick-expand-content {
  padding: 12px 0 4px;
}

.form-input-placeholder {
  color: #9ca3af;
  font-style: italic;
  display: flex;
  align-items: center;
  height: 38px;
  padding: 0 12px;
}

/* ═══════════════════════════════════════════════════ */
/* ═══ 4-STEP WIZARD PROGRESS BAR ═════════════════ */
/* ═══════════════════════════════════════════════════ */

.wizard-progress {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 0 20px;
  margin-bottom: 20px;
  border-bottom: 1px solid #e5e7eb;
  position: relative;
}
.wizard-progress::after {
  content: '';
  position: absolute;
  top: 14px;
  left: 40px;
  right: 40px;
  height: 2px;
  background: #e5e7eb;
  z-index: 0;
}

.wizard-progress-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  cursor: default;
  z-index: 1;
}
.wizard-progress-step.done {
  cursor: pointer;
}

.wizard-progress-number {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 600;
  background: #f3f4f6;
  color: #9ca3af;
  border: 2px solid #e5e7eb;
  transition: all 0.2s;
}
.wizard-progress-step.active .wizard-progress-number {
  background: #6366f1;
  color: white;
  border-color: #6366f1;
  box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}
.wizard-progress-step.done .wizard-progress-number {
  background: #10b981;
  color: white;
  border-color: #10b981;
}

.wizard-progress-label {
  font-size: 11px;
  font-weight: 500;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.wizard-progress-step.active .wizard-progress-label {
  color: #4f46e5;
  font-weight: 600;
}
.wizard-progress-step.done .wizard-progress-label {
  color: #059669;
}

.wizard-step-content {
  animation: stepFadeIn 0.25s ease;
}
@keyframes stepFadeIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

.wizard-step-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}

/* ═══════════════════════════════════════════════════ */
/* ═══ SUMMARY CARD (Step 4) ══════════════════════ */
/* ═══════════════════════════════════════════════════ */

.summary-card {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 16px 20px;
}
.summary-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 6px 0;
}
.summary-row:not(:last-child) {
  border-bottom: 1px solid #f3f4f6;
}
.summary-label {
  flex: 0 0 100px;
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  text-align: right;
}
.summary-value {
  flex: 1;
  font-size: 13px;
  color: #111827;
}
.summary-value-bold {
  font-weight: 600;
}

.wizard-draft-hint {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 16px;
  padding: 10px 14px;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 8px;
  font-size: 12px;
  color: #1e40af;
  line-height: 1.5;
}
.wizard-draft-hint svg {
  flex-shrink: 0;
  margin-top: 1px;
  color: #3b82f6;
}
.wizard-draft-hint strong {
  font-weight: 600;
}

/* ═══ FORM ROW 3 (3 Spalten) ═══ */
.form-row-3 {
  display: grid;
  grid-template-columns: 1.2fr 0.9fr 0.9fr;
  gap: 12px;
}

/* ═══════════════════════════════════════════════════ */
/* ═══ HIERARCHICAL GROUP PICKER ══════════════════ */
/* ═══════════════════════════════════════════════════ */

.group-picker {
  position: relative;
}

.group-picker-trigger {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  text-align: left;
  background: white;
  width: 100%;
}
.group-picker-trigger.has-value {
  color: #111827;
  font-weight: 500;
}
.group-picker-placeholder {
  color: #9ca3af;
  font-weight: 400;
}
.group-picker-icon {
  flex-shrink: 0;
  color: #6366f1;
}
.group-picker-chevron {
  margin-left: auto;
  flex-shrink: 0;
  color: #9ca3af;
  transition: transform 0.2s;
}
.group-picker-trigger.open .group-picker-chevron {
  transform: rotate(180deg);
}

.group-dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  z-index: 50;
  max-height: 280px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.group-dropdown-search {
  padding: 8px;
  border-bottom: 1px solid #f3f4f6;
}
.group-dropdown-search .form-input {
  font-size: 13px;
  padding: 6px 10px;
}

.group-dropdown-list {
  overflow-y: auto;
  overscroll-behavior: contain;
}

.group-dropdown-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  cursor: pointer;
  transition: background 0.1s;
  font-size: 13px;
  color: #374151;
  border-bottom: 1px solid #f9fafb;
  min-height: 36px;
}
.group-dropdown-item:hover:not(.disabled) {
  background: #f5f3ff;
}
.group-dropdown-item.selected {
  background: #eef2ff;
  color: #4338ca;
  font-weight: 600;
}
.group-dropdown-item.disabled {
  cursor: default;
  color: #9ca3af;
}
.group-dropdown-item.is-folder {
  font-weight: 600;
  color: #6b7280;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  padding-top: 10px;
}

.group-item-svg {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
  color: #9ca3af;
}
.group-dropdown-item.selected .group-item-svg {
  color: #6366f1;
}
.group-dropdown-item.is-folder .group-item-svg {
  color: #d1d5db;
}

.group-dropdown-name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.group-dropdown-role {
  flex-shrink: 0;
  font-size: 10px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 8px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.role-leader {
  background: #fef3c7;
  color: #92400e;
}
.role-member {
  background: #e0e7ff;
  color: #3730a3;
}
.role-none {
  background: transparent;
  color: #d1d5db;
  font-size: 12px;
}

.group-dropdown-check {
  flex-shrink: 0;
  color: #6366f1;
}

.group-dropdown-empty {
  padding: 16px;
  text-align: center;
  color: #9ca3af;
  font-size: 13px;
}

/* ═══════════════════════════════════════════════════ */
/* ═══ TYPE SELECTION CHIPS ════════════════════════ */
/* ═══════════════════════════════════════════════════ */

.type-chip-row {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 16px;
}

.type-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border: 2px solid #e5e7eb;
  border-radius: 10px;
  background: white;
  color: #6b7280;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  justify-content: center;
  min-width: 0;
  white-space: normal;
  line-height: 1.2;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}

.type-chip:hover {
  border-color: #c7d2fe;
  background: #f5f3ff;
  color: #4338ca;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.1);
}

.type-chip:active {
  transform: scale(0.97);
}

.type-chip.active {
  border-color: #6366f1;
  background: #eef2ff;
  color: #4338ca;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.type-chip-icon {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.type-chip-icon svg {
  width: 18px;
  height: 18px;
}

/* Aktiver Chip: typ-spezifische Akzentfarben */
.type-chip.active.activity {
  border-color: #6366f1;
  background: #eef2ff;
  color: #4338ca;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}
.type-chip.active.camp {
  border-color: #f59e0b;
  background: #fffbeb;
  color: #92400e;
  box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.12);
}
.type-chip.active.event {
  border-color: #818cf8;
  background: #eef2ff;
  color: #3730a3;
  box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.15);
}
.type-chip.active.external {
  border-color: #10b981;
  background: #ecfdf5;
  color: #065f46;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
}

/* Inaktive Typ-Icons: dezente Farbe */
.type-chip .type-chip-icon { color: #9ca3af; transition: color 0.2s; }
.type-chip:hover .type-chip-icon { color: #6366f1; }
.type-chip.active.activity .type-chip-icon { color: #6366f1; }
.type-chip.active.camp .type-chip-icon { color: #d97706; }
.type-chip.active.event .type-chip-icon { color: #4f46e5; }
.type-chip.active.external .type-chip-icon { color: #059669; }

.type-chip-name {
  font-size: 13px;
}

/* ═══════════════════════════════════════════════════ */
/* ═══ SUGGESTION CHIPS ════════════════════════════ */
/* ═══════════════════════════════════════════════════ */

.suggestion-chips {
  margin-bottom: 12px;
}
.suggestion-label {
  font-size: 12px;
  font-weight: 500;
  color: #6b7280;
  margin-bottom: 6px;
}
.suggestion-label.suggestion-loading {
  font-style: italic;
  color: #9ca3af;
}
.suggestion-list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.suggestion-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  border: 1px solid #d1d5db;
  border-radius: 16px;
  background: #f9fafb;
  color: #374151;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  white-space: nowrap;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
}
.suggestion-chip:hover {
  border-color: #6366f1;
  background: #eef2ff;
  color: #4338ca;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.15);
}
.suggestion-chip:active {
  transform: scale(0.96);
  box-shadow: none;
}
.suggestion-chip.active {
  border-color: #6366f1;
  background: #6366f1;
  color: white;
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
}
.suggestion-chip.active:hover {
  background: #4f46e5;
  box-shadow: 0 2px 12px rgba(99, 102, 241, 0.4);
}
.suggestion-chip-plus {
  font-weight: 700;
  font-size: 13px;
  transition: transform 0.2s;
}
.suggestion-chip:hover .suggestion-chip-plus {
  transform: scale(1.15);
}
.suggestion-chip-qty {
  font-size: 11px;
  color: #9ca3af;
  font-weight: 500;
}
.suggestion-chip.active .suggestion-chip-qty {
  color: rgba(255, 255, 255, 0.75);
}

/* ═══ MOBILE RESPONSIVE ═══ */

/* Tablet */
@media (max-width: 768px) {
  .wizard-overlay {
    padding: 10px;
  }
  .wizard-modal {
    max-width: 100%;
    height: 95vh;
    max-height: 100vh;
    border-radius: 8px;
  }
  .wizard-sidebar {
    width: 220px;
    padding: 16px;
  }
  .wizard-sidebar h3 {
    font-size: 12px;
    margin-bottom: 12px;
  }
  .wizard-content {
    flex-direction: row;
  }
  .wizard-form {
    padding: 16px;
  }
  .wizard-header {
    padding: 12px 16px;
  }
  .wizard-header h2 {
    font-size: 15px;
  }
  .wizard-footer {
    padding: 10px 16px;
  }
  .form-row {
    flex-direction: column;
    gap: 8px;
  }
}

/* Mobile */
@media (max-width: 640px) {
  .wizard-overlay {
    padding: 0;
  }
  .wizard-modal {
    height: 100vh;
    max-height: 100vh;
    min-height: auto;
  }
  .wizard-sidebar {
    display: none;
  }
  .wizard-content {
    flex-direction: column;
  }

  /* Group Picker: volle Breite */
  .group-dropdown {
    max-height: 240px;
  }
  .group-dropdown-item {
    min-height: 44px;
    font-size: 14px;
  }
  .group-picker-trigger {
    min-height: 44px;
  }

  /* Type Chips: 2x2 Grid auf Mobile */
  .type-chip-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
  }
  .type-chip {
    padding: 10px 8px;
    font-size: 12px;
    min-height: 44px;
  }
  .quick-form {
    padding: 14px 16px !important;
  }
  .form-row-3 {
    grid-template-columns: 1fr;
  }

  /* Wizard Progress: nur Zahlen, kein Label */
  .wizard-progress {
    padding: 0 0 14px;
    margin-bottom: 14px;
  }
  .wizard-progress-label {
    display: none;
  }
  .wizard-progress-number {
    width: 32px;
    height: 32px;
    font-size: 14px;
  }
  .wizard-progress::after {
    top: 16px;
    left: 24px;
    right: 24px;
  }

  /* Touch-freundliche Inputs */
  .form-input {
    min-height: 44px;
    font-size: 16px !important; /* Verhindert iOS Zoom */
  }
  select.form-input {
    min-height: 44px;
  }
  .form-textarea {
    min-height: 80px;
  }

  /* Touch-freundliche Buttons */
  .btn {
    min-height: 44px;
    padding: 10px 16px;
  }
  .btn-next {
    min-height: 44px;
    width: 100%;
    justify-content: center;
  }
  .btn-secondary {
    min-height: 44px;
  }

  /* Footer: vertikal stapeln bei wenig Platz */
  .wizard-footer {
    flex-direction: column;
    gap: 8px;
    padding: 10px 16px;
  }
  .footer-left {
    width: 100%;
    text-align: center;
  }
  .footer-actions {
    width: 100%;
    flex-direction: column;
    gap: 8px;
  }
  .footer-actions .btn {
    width: 100%;
    justify-content: center;
  }
  .footer-actions .btn-primary {
    order: -1; /* Primary-Button oben */
  }
  .missing-hint {
    text-align: center;
    justify-content: center;
  }
  .footer-kbd-hint {
    display: none;
  }

  /* Quick-Expand: größere Touch-Targets */
  .quick-expand-btn {
    min-height: 44px;
    padding: 12px 14px;
  }

  /* Suggestion-Chips: scrollen statt wrappen */
  .suggestion-list {
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 4px;
    scrollbar-width: none;
  }
  .suggestion-list::-webkit-scrollbar {
    display: none;
  }
  .suggestion-chip {
    padding: 8px 12px;
    font-size: 13px;
    flex-shrink: 0;
  }

  /* Material-Suchfeld */
  .mat-search-input {
    font-size: 16px !important;
  }
  .mat-dropdown-item {
    padding: 10px 12px;
  }
  .mat-quick-btn {
    min-width: 36px;
    min-height: 36px;
  }

  /* Wizard Step Actions */
  .wizard-step-actions {
    flex-direction: column;
    gap: 8px;
  }
  .wizard-step-actions .btn {
    width: 100%;
    justify-content: center;
  }
  .wizard-step-actions .btn-next {
    order: -1;
  }

  /* Summary-Karte kompakter */
  .summary-card {
    padding: 12px 14px;
  }
  .summary-label {
    flex: 0 0 80px;
    font-size: 11px;
  }
  .summary-value {
    font-size: 12px;
  }

  /* Adresse */
  .selected-address-card {
    padding: 10px;
  }
  .address-result-item {
    padding: 10px 12px;
    min-height: 44px;
  }
}

/* ═══ SAFE AREA (Notch-Handling) ═══ */
@supports (padding-bottom: env(safe-area-inset-bottom)) {
  @media (max-width: 640px) {
    .wizard-footer {
      padding-bottom: calc(10px + env(safe-area-inset-bottom));
    }
  }
}
</style>
