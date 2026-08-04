<template>
  <div class="material-detail-view activity-detail-view" :class="detailDisplayClasses">
    <header class="detail-header activity-detail-header">
      <div class="header-left">
        <EButton
          v-if="smAndUp"
          variant="secondary"
          size="small"
          class="activity-detail-back-btn"
          @click="handleClose"
        >
          <v-icon icon="mdi-arrow-left" start size="20" />
          {{ t('activities.detail.backToList') }}
        </EButton>
        <div class="header-title activity-detail-header-title">
          <v-chip v-if="noLabel" size="small" variant="tonal" color="primary" class="activity-detail-no-chip">
            {{ noLabel }}
          </v-chip>
          <span v-if="activity" class="type-badge" :class="activity.type">{{ activityTypeLabelDetail(activity.type) }}</span>
          <h1>{{ activity?.name ?? t('activities.detail.fallbackTitle') }}</h1>
          <span
            v-if="activity && showPackJourneyStepBadge"
            class="status-label status-label--journey"
            :class="activityStatusClass(activity.status)"
          >{{ packJourneyStepLabelDetail }}</span>
          <span
            v-else-if="activity"
            class="status-label"
            :class="activityStatusClass(activity.status)"
          >{{ activityStatusLabelDetail(activity.status) }}</span>
        </div>
      </div>
      <div v-if="activity && !loadError" class="header-actions activity-detail-workflow-actions">
        <div
          v-if="canManageActivityQr"
          class="activity-detail-header-qr"
        >
          <EButton
            v-if="showGenerateActivityQrButton"
            variant="secondary"
            size="small"
            class="activity-header-action-btn"
            :disabled="isGeneratingActivityPublicCode"
            :loading="isGeneratingActivityPublicCode"
            @click="generateActivityPublicCode"
          >
            {{ isGeneratingActivityPublicCode ? t('activities.detail.qrGenLoading') : t('activities.detail.qrGenCreate') }}
          </EButton>
          <PublicQrTag
            v-if="activityPublicUrl"
            class="header-qr-tag"
            :url="activityPublicUrl"
            :code="activity.public_code"
            :size="headerQrSize"
            :clickable="true"
            :image-label="activity.name"
            :image-entity-id="activity.id"
            @activate="openActivityQrActionModal"
          />
        </div>
        <div class="activity-detail-header-buttons">
          <EButton
            v-for="tr in workflowTransitions"
            :key="tr.status"
            variant="secondary"
            size="small"
            class="activity-header-action-btn"
            :disabled="isTransitioning || !tr.allowed"
            :title="!tr.allowed && tr.reason ? tr.reason : transitionActionLabel(tr)"
            @click="onTransition(tr)"
          >
            {{ transitionActionLabel(tr) }}
          </EButton>
          <EButton
            v-if="cancelTransition"
            variant="danger"
            size="small"
            class="activity-header-action-btn activity-header-cancel-btn"
            :disabled="isTransitioning"
            :title="cancelTransition ? transitionActionLabel(cancelTransition) : undefined"
            @click="onCancelActivity"
          >
            {{ cancelTransition ? transitionActionLabel(cancelTransition) : '' }}
          </EButton>
          <EButton
            v-if="showDamageReportInActivityHeader"
            variant="secondary"
            size="small"
            class="activity-header-action-btn"
            @click="openDamageReport()"
          >
            {{ t('activities.detail.reportDamage') }}
          </EButton>
          <EButton
            variant="secondary"
            size="small"
            class="activity-header-action-btn"
            @click="handleClose"
          >
            {{ t('activities.detail.close') }}
          </EButton>
        </div>
      </div>
    </header>

    <v-tabs
      v-if="activity && !isLoading && !loadError"
      v-model="activeTab"
      class="activity-detail-tabs"
      align-tabs="start"
      color="primary"
      show-arrows
    >
      <v-tab v-for="tab in tabs" :key="tab.id" :value="tab.id">
        {{ tab.label }}
      </v-tab>
    </v-tabs>

    <ELoadingState
      v-if="isLoading"
      variant="page"
      class="activity-detail-loading"
      :message="t('activities.detail.loading')"
    />

    <div v-else-if="loadError" class="activity-detail-error-state">
      <p>{{ loadError }}</p>
      <div class="activity-detail-error-actions">
        <EButton variant="primary" size="small" @click="reload">{{ t('common.retry') }}</EButton>
        <EButton variant="secondary" size="small" @click="handleClose">{{ t('activities.detail.loadErrorBack') }}</EButton>
      </div>
    </div>

    <div v-else-if="activity" class="activity-detail-scroll">
      <v-alert
        v-if="activity.status === 'draft'"
        type="warning"
        variant="tonal"
        density="compact"
        class="activity-draft-alert"
        icon="mdi-information-outline"
      >
        <strong>{{ t('activities.detail.draftLabel') }}</strong>
        <template v-if="activity.type === 'event' && !activity.group_id">
          {{ t('activities.detail.draftBannerEventNoGroup') }}
        </template>
        <template v-else>
          {{ t('activities.detail.draftBannerWithGroup') }}
        </template>
        <template
          v-if="
            isRestrictedGroupMember &&
            !canCreateCampAndEvent &&
            (activity.type === 'camp' || activity.type === 'event' || activity.type === 'external')
          "
        >
          {{ t('activities.detail.draftBannerSubmitMemberCamp') }}
        </template>
        <template v-else-if="activity.type === 'camp' || activity.type === 'event'">
          {{ t('activities.detail.draftBannerSubmitCampEvent') }}
        </template>
        <template v-else>
          {{ t('activities.detail.draftBannerSubmit') }}
        </template>
      </v-alert>

      <ActivityCompletionChecklist
        v-if="showMaterialCompletionChecklist"
        :blockers="completionBlockers"
        :activity-id="activityId"
        :host-department-id="departmentId"
        :activity-status="activity.status ?? ''"
        :activity-type="activity.type"
        :group-name="activity.group_name"
        :external-customer-label="activity.external_customer_label"
        :activity-name="activity.name"
        :costs-released="Boolean(activity.costs_released)"
        :costs-release-loading="costsReleaseInProgress"
        :costs-total="costsPreviewTotal"
        @go-tab="onCompletionGoTab"
        @release-costs="openCostsReleaseConfirm()"
      />

      <v-alert
        v-if="showMemberScopeStatusHint"
        type="info"
        variant="tonal"
        density="compact"
        class="activity-member-scope-alert"
        icon="mdi-information-outline"
      >
        {{ t('activities.detail.memberScopeStatusHint') }}
      </v-alert>

      <v-alert
        v-if="showMemberPostReturnHandoffLockHint"
        type="info"
        variant="tonal"
        density="compact"
        class="activity-member-scope-alert"
        icon="mdi-lock-outline"
      >
        {{ t('activities.detail.memberPostReturnHandoffHint') }}
      </v-alert>

      <div class="activity-detail-tabs-window">
          <ActivityDetailTabPane tab-id="overview" :active-tab="activeTab" eager>
            <div class="activity-detail-tab-panel tab-content">
            <ActivityDraftOverviewForm
              v-if="showOverviewEditForm && activity"
              ref="draftOverviewFormRef"
              :activity="activity"
              :department-id="departmentId"
              :usage-dates-locked="(activity.item_count ?? 0) > 0"
              @saved="onDraftOverviewSaved"
            />
            <template v-else-if="activity">
              <ActivityTabHeader :title="t('activities.detail.tabOverview')" />
              <div class="section-card activity-tab-panel-card">
                <h2 class="section-title activity-tab-subsection-title">{{ t('activities.detail.sectionPeriod') }}</h2>
                <div class="form-grid">
                  <div class="form-group span-2">
                    <label>{{ t('activities.detail.labelUsage') }}</label>
                    <p class="activity-readonly-value">
                      <template v-if="activity.usage_start">
                        {{ formatDateTime(activity.usage_start) }}
                        –
                        {{ formatDateTime(activity.usage_end || '') }}
                      </template>
                      <span v-else class="text-muted">{{ t('activities.detail.usageNotSet') }}</span>
                    </p>
                  </div>
                  <div v-if="activity.planning_start" class="form-group span-2">
                    <label>{{ t('activities.detail.labelMaterialPickupReturn') }}</label>
                    <p class="activity-readonly-value">
                      {{ formatDateTime(activity.planning_start) }} – {{ formatDateTime(activity.planning_end || '') }}
                    </p>
                  </div>
                </div>
              </div>

              <div class="section-card activity-tab-panel-card">
                <h2 class="section-title activity-tab-subsection-title">{{ t('activities.detail.sectionOrg') }}</h2>
                <div class="form-grid">
                  <div class="form-group">
                    <label>{{ t('activities.detail.labelDepartment') }}</label>
                    <p class="activity-readonly-value">{{ activity.department_name ?? t('activities.wizard.form.summaryEmpty') }}</p>
                  </div>
                  <div class="form-group">
                    <label>{{ t('common.group') }}</label>
                    <p class="activity-readonly-value">{{ activity.group_name || t('activities.wizard.form.summaryEmpty') }}</p>
                  </div>
                  <ActivityDetailUserLine
                    class="form-group"
                    :label="t('activities.detail.labelCreatedBy')"
                    :user="activity.created_by_user"
                    :at="activity.created_at"
                    :empty-label="t('activities.wizard.form.summaryEmpty')"
                    :format-when="formatDateTime"
                  />
                  <ActivityDetailUserLine
                    v-if="activity.submitted_at"
                    class="form-group"
                    :label="t('activities.detail.labelSubmittedBy')"
                    :user="activity.submitted_by_user"
                    :at="activity.submitted_at"
                    :empty-label="t('activities.wizard.form.summaryEmpty')"
                    :format-when="formatDateTime"
                  />
                  <div v-if="activity.total_price != null" class="form-group">
                    <label>{{ t('activities.detail.labelTotalPrice') }}</label>
                    <p class="activity-readonly-value">CHF {{ Number(activity.total_price).toFixed(2) }}</p>
                  </div>
                  <div
                    v-if="activity.wants_js_material && (activity.type === 'camp' || activity.type === 'event')"
                    class="form-group span-2"
                  >
                    <label>{{ t('activities.jsMaterial.sectionLabel') }}</label>
                    <p class="activity-readonly-value">
                      <span class="activity-js-tag">{{ t('activities.common.jsBadge') }}</span>
                      {{ t('activities.jsMaterial.badgeIncluded') }}
                      <template
                        v-if="activity.participant_count != null && activity.participant_count >= 1"
                      >
                        · {{ t('activities.jsMaterial.participantCountSummary', { count: activity.participant_count }) }}
                      </template>
                    </p>
                  </div>
                </div>
              </div>

              <div
                v-if="activity.invited_departments && activity.invited_departments.length > 0"
                class="section-card activity-tab-panel-card"
              >
                <h2 class="section-title activity-tab-subsection-title">{{ t('activities.detail.sectionInvitedDepartments') }}</h2>
                <ul class="activity-invite-list">
                  <li v-for="(inv, idx) in activity.invited_departments" :key="inv.id || idx" class="activity-invite-row">
                    <span class="activity-invite-name">{{ inv.name || inv.id }}</span>
                    <span v-if="inv.organisation_name" class="text-muted">({{ inv.organisation_name }})</span>
                    <span class="invite-status" :class="inviteStatusClass(inv.status)">{{ inviteStatusLabel(inv.status) }}</span>
                    <span v-if="inv.group_name" class="text-muted">· {{ inv.group_name }}</span>
                    <span
                      v-else-if="inv.status === 'accepted'"
                      class="text-muted"
                    >· {{ t('activities.detail.inviteGroupNotSet') }}</span>
                  </li>
                </ul>
              </div>

              <div
                v-if="showGuestInviteGroupAssign"
                class="section-card activity-tab-panel-card activity-guest-invite-group-card"
              >
                <h2 class="section-title activity-tab-subsection-title">{{ t('activities.detail.guestInviteGroupTitle') }}</h2>
                <p class="text-muted activity-guest-invite-group-hint">{{ t('activities.detail.guestInviteGroupHint') }}</p>
                <div class="form-group">
                  <label for="guest-invite-group-select">{{ t('common.group') }}</label>
                  <select
                    id="guest-invite-group-select"
                    v-model="guestInviteGroupId"
                    class="form-input"
                    :disabled="guestInviteGroupSaving || guestInviteGroupsLoading"
                  >
                    <option value="">{{ t('activities.detail.guestInviteGroupPlaceholder') }}</option>
                    <option v-for="g in guestInviteFlatGroups" :key="g.id" :value="g.id">
                      {{ guestInviteGroupLabel(g) }}
                    </option>
                  </select>
                </div>
                <EButton
                  variant="primary"
                  size="small"
                  :disabled="!guestInviteGroupId || guestInviteGroupSaving"
                  :loading="guestInviteGroupSaving"
                  @click="saveGuestInviteGroup"
                >
                  {{ t('activities.detail.guestInviteGroupSave') }}
                </EButton>
              </div>

              <div v-if="activity.notes" class="section-card activity-tab-panel-card">
                <h2 class="section-title activity-tab-subsection-title">{{ t('activities.detail.sectionNotes') }}</h2>
                <p class="activity-notes">{{ activity.notes }}</p>
              </div>
            </template>
            </div>
          </ActivityDetailTabPane>

          <ActivityDetailTabPane tab-id="material" :active-tab="activeTab" eager>
            <div class="activity-detail-tab-panel tab-content activity-material-tab">
            <ActivityTabHeader :title="t('common.material')">
              <p class="activity-material-tab-hint text-muted">
                {{ t('activities.materialTab.planningHint') }}
              </p>
            </ActivityTabHeader>
            <div
              v-if="showDraftMaterialAddForGroup"
              class="section-card activity-tab-panel-card activity-draft-material-add-card"
            >
              <h2 class="section-title activity-tab-subsection-title">{{ t('activities.detail.materialAddTitle') }}</h2>
              <p class="text-muted activity-draft-material-add-hint">
                {{ t('activities.detail.draftMaterialAddHint') }}
              </p>
              <ActivityMaterialAvailabilityLookup
                :department-id="departmentId"
                :activity-id="activityId"
                :activity-type="activityTypeForMat"
                :planning-start-iso="activity.planning_start"
                :planning-end-iso="activity.planning_end"
                :quantity-by-material-item-id="quantityByMaterialItemId"
                :saved-quantity-by-material-item-id="savedQuantityByMaterialItemId"
                :standalone-quantity-by-material-item-id="standaloneQuantityByMaterialItemId"
                :invited-departments="activity.invited_departments ?? []"
                :disabled="addingDraftMaterial"
                :repeat-add-from-search="true"
                hint-variant="draft"
                @add-quantity="onDraftAddQuantity"
                @scope-change="onMaterialLookupScopeChange"
              />
              <p v-if="addingDraftMaterial" class="activity-draft-adding">
                <ELoadingState
                  variant="inline"
                  :message="t('activities.detail.addingMaterial')"
                />
              </p>
            </div>
            <div
              v-if="showForgottenMaterialAccordion"
              class="section-card pack-add-material-card activity-forgotten-material-card"
            >
              <button
                type="button"
                class="pack-add-material-toggle"
                :aria-expanded="forgottenMaterialExpanded"
                @click="forgottenMaterialExpanded = !forgottenMaterialExpanded"
              >
                <span class="pack-add-material-chevron" aria-hidden="true">{{
                  forgottenMaterialExpanded ? '▼' : '▶'
                }}</span>
                <span class="pack-add-material-toggle-title">{{
                  t('activities.detail.forgottenMaterialToggleTitle')
                }}</span>
              </button>
              <div v-show="forgottenMaterialExpanded" class="pack-add-material-body">
                <p class="pack-add-material-summary text-muted">
                  {{ t('activities.detail.forgottenMaterialToggleSummary') }}
                </p>
                <ActivityMaterialAvailabilityLookup
                  :department-id="departmentId"
                  :activity-id="activityId"
                  :activity-type="activityTypeForMat"
                  :planning-start-iso="activity.planning_start"
                  :planning-end-iso="activity.planning_end"
                  :quantity-by-material-item-id="quantityByMaterialItemId"
                  :saved-quantity-by-material-item-id="savedQuantityByMaterialItemId"
                  :standalone-quantity-by-material-item-id="standaloneQuantityByMaterialItemId"
                  :invited-departments="activity.invited_departments ?? []"
                  :disabled="addingDraftMaterial"
                  :repeat-add-from-search="true"
                  hint-variant="draft"
                  @add-quantity="onDraftAddQuantity"
                  @scope-change="onMaterialLookupScopeChange"
                />
                <p v-if="addingDraftMaterial" class="activity-inline-loading activity-draft-adding">
                  <span class="spinner spinner-sm"></span>
                  <span>{{ t('activities.detail.addingMaterial') }}</span>
                </p>
              </div>
            </div>
            <div v-if="showMaterialAddOnMaterialTab" class="section-card activity-tab-panel-card">
              <h2 class="section-title activity-tab-subsection-title">{{ t('activities.detail.materialAddTitle') }}</h2>
              <ActivityMaterialAvailabilityLookup
                :department-id="departmentId"
                :activity-id="activityId"
                :activity-type="activityTypeForMat"
                :planning-start-iso="activity.planning_start"
                :planning-end-iso="activity.planning_end"
                :quantity-by-material-item-id="quantityByMaterialItemId"
                :saved-quantity-by-material-item-id="savedQuantityByMaterialItemId"
                :standalone-quantity-by-material-item-id="standaloneQuantityByMaterialItemId"
                :invited-departments="activity.invited_departments ?? []"
                :disabled="addingDraftMaterial"
                :repeat-add-from-search="true"
                hint-variant="draft"
                @add-quantity="onDraftAddQuantity"
                @scope-change="onMaterialLookupScopeChange"
              />
              <p v-if="addingDraftMaterial" class="activity-draft-adding">
                <ELoadingState
                  variant="inline"
                  :message="t('activities.detail.addingMaterial')"
                />
              </p>
            </div>
            <div
              v-else-if="activity.status === 'draft' && !activity.can_edit_draft_material"
              class="section-card activity-tab-panel-card activity-draft-mat-denied"
            >
              <p class="text-muted">
                {{ t('activities.detail.draftMaterialDenied') }}
              </p>
            </div>

            <ActivityTabPanelShell
              :loading="itemsShowFullLoading"
              :refreshing="itemsRefreshing"
              :loading-message="t('activities.detail.itemsLoading')"
              loading-class="activity-items-loading"
            >
              <h2 class="section-title activity-tab-subsection-title">{{ t('activities.detail.materialPositionsTitle') }}</h2>
              <div v-if="activityItems.length === 0" class="text-muted">
                {{
                  showDraftMaterialAddForGroup
                    ? t('activities.detail.draftNoPositionsYet')
                    : t('activities.detail.noPositions')
                }}
              </div>
              <div v-else-if="showMaterialLookup" class="activity-items-table-wrap">
                <ActivityMaterialLinesTable
                  :model-value="materialLinesForEditableTable"
                  :department-id="departmentId"
                  :activity-id="activityId"
                  :planning-start-at="planningStartDate"
                  :planning-end-at="planningEndDate"
                  :material-scope-tab="materialLookupScopeTab"
                  :material-scope-has-partners="hasAcceptedPartnerDepts"
                  :material-scope-single-partner-id="materialLookupSinglePartnerId"
                  variant="detail-draft"
                  :show-source-and-totals="true"
                  :show-line-total="hasLineTotals"
                  :disabled="syncingQuantities || addingDraftMaterial"
                  :packing-stage-quantity-readonly="false"
                  :removing-item-id="removingItemId"
                  :child-quantity-by-material-item-id="childQuantityByMaterialItemId"
                  :virtual-combo-pack-mode-editable="virtualComboPackModeEditable"
                  :empty-text="t('activities.detail.noPositions')"
                  @update:model-value="onDraftLinesTableUpdate"
                  @remove-line="onDraftTableRemoveLine"
                  @pack-mode-change="onVirtualComboPackModeChange"
                  @reconfigure-virtual-combo="onReconfigureVirtualCombo"
                />
                <ComboConfiguratorDialog
                  v-if="reconfigureVirtualComboState"
                  :combo-id="reconfigureVirtualComboState.materialItemId"
                  :combo-name="reconfigureVirtualComboState.materialName"
                  :department-id="departmentId"
                  :activity-id="activityId"
                  :start-iso="activity?.planning_start ?? null"
                  :end-iso="activity?.planning_end ?? null"
                  :initial-quantity="reconfigureVirtualComboState.quantity"
                  :initial-selected-option-ids="reconfigureVirtualComboState.selectedOptionIds"
                  :initial-pack-mode="reconfigureVirtualComboState.packMode"
                  :initial-self-provided-acknowledged="reconfigureVirtualComboState.selfProvidedAcknowledged"
                  :standalone-quantity-by-material-item-id="standaloneQuantityByMaterialItemId"
                  @confirm="onReconfigureVirtualComboConfirm"
                  @cancel="reconfigureVirtualComboState = null"
                />
                <p v-if="syncingQuantities" class="activity-qty-autosave-hint text-muted">
                  {{ t('activities.detail.saveQtySaving') }}
                </p>
              </div>
              <div v-else class="activity-items-table-wrap">
                <table class="activity-items-table">
                  <thead>
                    <tr>
                      <th>{{ t('common.material') }}</th>
                      <th>{{ t('activities.detail.tableQty') }}</th>
                      <th>{{ t('activities.detail.tableSource') }}</th>
                      <th v-if="hasLineTotals">{{ t('activities.detail.tableLine') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in topLevelActivityItems" :key="row.id">
                      <td>
                        <div class="activity-item-name-block">
                          <span class="activity-item-name">{{ row.material_name }}</span>
                          <span
                            v-if="row.material_type === 'physical_combo'"
                            class="activity-combo-badge"
                            :title="t('activities.detail.comboPhysicalTitle')"
                            ><span aria-hidden="true">{{ COMBO_BADGE.physical }}</span> {{ t('activities.detail.comboPhysicalShort') }}</span
                          >
                          <span
                            v-else-if="row.material_type === 'virtual_combo'"
                            class="activity-combo-badge activity-combo-badge--virtual"
                            :title="t('activities.detail.comboVirtualTitle')"
                            ><span aria-hidden="true">{{ COMBO_BADGE.virtual }}</span> {{ t('activities.detail.comboVirtualShort') }}</span
                          >
                          <span v-if="row.is_js_material" class="activity-js-tag">J&amp;S</span>
                          <span
                            v-if="row.is_replenishment"
                            class="activity-replenishment-badge"
                            :title="t('activities.detail.replenishmentBadge')"
                          >{{ t('activities.detail.replenishmentBadge') }}</span>
                          <div v-if="row.linked_container_label" class="activity-combo-kiste text-muted">
                            {{ row.linked_container_label }}
                          </div>
                          <!-- Set-Anzeige „wie Kiste": Hülle + aufgelöste Teile als Inhalt -->
                          <div
                            v-if="comboSetContent(row)"
                            class="activity-combo-set-content"
                          >
                            <span class="activity-combo-set-title text-muted">
                              <span aria-hidden="true">{{ COMBO_BADGE.crate }}</span>
                              {{ t('activities.detail.comboSetContentTitle') }}
                            </span>
                            <ul class="activity-combo-set-list">
                              <li
                                v-for="c in comboSetContent(row)!.resolved"
                                :key="`r-${c.component_material_id}`"
                              >
                                {{ c.total_qty }}× {{ c.name }}
                              </li>
                              <li
                                v-for="c in comboSetContent(row)!.selfProvided"
                                :key="`s-${c.component_material_id}`"
                                class="activity-combo-set-self"
                              >
                                {{ c.total_qty }}× {{ c.name }}
                                <span class="text-muted">· {{ t('activities.detail.comboSetSelfProvided') }}</span>
                              </li>
                            </ul>
                          </div>
                        </div>
                      </td>
                      <td>{{ row.quantity }}</td>
                      <td>
                        <span class="text-muted">{{ row.source_department_name || t('activities.wizard.form.summaryEmpty') }}</span>
                      </td>
                      <td v-if="hasLineTotals">
                        <span v-if="row.line_total != null">CHF {{ formatMoney(row.line_total) }}</span>
                        <span v-else>{{ t('activities.wizard.form.summaryEmpty') }}</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </ActivityTabPanelShell>
          </div>
          </ActivityDetailTabPane>

          <ActivityDetailTabPane v-if="showJsOrderCard" tab-id="js" :active-tab="activeTab" eager>
            <div class="activity-detail-tab-panel tab-content">
              <ActivityJsTabView
                :activity-id="activityId"
                :department-id="departmentId"
                :can-edit="canEditJsOrder"
                :participant-count="activity?.participant_count ?? null"
                :venue-address-id="activity?.viewer_venue_address_id ?? activity?.venue_address_id ?? null"
                :js-delivery-address-id="activity?.js_delivery_address_id ?? null"
                @activity-updated="onJsActivityUpdated"
                @go-overview="activeTab = 'overview'"
              />
            </div>
          </ActivityDetailTabPane>

          <ActivityDetailTabPane v-if="showVehiclesTab" tab-id="vehicles" :active-tab="activeTab" eager>
            <div class="activity-detail-tab-panel tab-content">
              <ActivityVehiclesTab
                v-if="activity"
                :activity-id="activityId"
                :department-id="departmentId"
                :can-manage="canManageActivityVehicles"
                @assignments-changed="onActivityVehiclesChanged"
              />
            </div>
          </ActivityDetailTabPane>

          <ActivityDetailTabPane v-if="showPacksTab" tab-id="packs" :active-tab="activeTab" eager>
            <div class="activity-detail-tab-panel tab-content activity-detail-tab-panel--packs">
            <ActivityMaterialJourneyView
              v-if="activity && !useLegacyPackUi"
              ref="materialJourneyRef"
              :department-id="departmentId"
              :activity-id="activityId"
              :activity-created-by-user-id="activity.created_by_user_id ?? null"
              :transitions="transitions"
              :can-report-issues="showDamageReportEntry"
              :can-report-consumption="showConsumptionBooking"
              :can-request-consumable-nachbuchung="canRequestConsumableNachbuchung"
              :consumable-material-item-ids="consumableMaterialItemIds"
              :reload-token="packListReloadToken"
              :vehicles-reload-token="vehiclesReloadToken"
              :consumption-modal-cancelled-token="consumptionModalCancelledToken"
              :consumption-modal-return-without-consumption-token="consumptionModalReturnWithoutConsumptionToken"
              embedded
              @status-changed="onJourneyStatusChanged"
              @packing-header-ready="onPackingHeaderReady"
              @store-header-ready="onStoreHeaderReady"
              @open-issue-wizard="onPackIssueWizard"
              @open-consumption-modal="onOpenConsumptionModal"
              @request-nachbuchung="openNachbuchungModal"
            />
            <ActivityPackListTab
              v-else-if="activity"
              ref="packListTabRef"
              :activity-id="activityId"
              :department-id="departmentId"
              :activity-created-by-user-id="activity.created_by_user_id ?? null"
              :status="activity.status"
              :activity-type="activity.type"
              :activity-name="activity.name"
              :pack-list-editable="activity.is_pack_list_editable === true"
              :transitions="transitions"
              :can-report-issues="showDamageReportEntry"
              :can-report-consumption="showConsumptionBooking"
              :reload-token="packListReloadToken"
              :consumption-modal-cancelled-token="consumptionModalCancelledToken"
              :consumption-modal-return-without-consumption-token="consumptionModalReturnWithoutConsumptionToken"
              :can-add-activity-material="canAddActivityMaterial"
              :can-request-consumable-nachbuchung="canRequestConsumableNachbuchung"
              :activity-type-for-material-add="activityTypeForMat"
              :planning-start-iso="activity.planning_start ?? null"
              :planning-end-iso="activity.planning_end ?? null"
              :quantity-by-material-item-id-for-add="quantityByMaterialItemId"
              :saved-quantity-by-material-item-id-for-add="savedQuantityByMaterialItemId"
              :invited-departments-for-add="activity.invited_departments ?? []"
              :adding-activity-material="addingDraftMaterial"
              :virtual-combo-self-provided-hints="virtualComboSelfProvidedHints"
              @workflow-next="onPackListWorkflowNext"
              @packing-header-ready="onPackingHeaderReady"
              @activity-items-changed="onPackListActivityItemsChanged"
              @open-issue-wizard="onPackIssueWizard"
              @open-consumption-modal="onOpenConsumptionModal"
              @request-nachbuchung="openNachbuchungModal"
              @add-activity-material="onDraftAddQuantity"
              @material-scope-change="onMaterialLookupScopeChange"
            />
            </div>
          </ActivityDetailTabPane>

          <ActivityDetailTabPane v-if="showIssuesTab" tab-id="issues" :active-tab="activeTab" eager>
            <div class="activity-detail-tab-panel tab-content">
            <ActivityIssuesTab
              :activity-id="activityId"
              :reports="activityIssues"
              :reports-ready="issuesDataReady"
              :can-create="showDamageReportEntry"
              :read-only-hint="showIssuesTabReadOnlyHint"
              @open-wizard="openDamageReport()"
            />
            </div>
          </ActivityDetailTabPane>

          <ActivityDetailTabPane v-if="showConsumablesTab" tab-id="consumables" :active-tab="activeTab" eager>
            <div class="activity-detail-tab-panel tab-content">
            <ActivityConsumablesTab
              :activity-id="activityId"
              :activity-type="activity?.type"
              :can-create="showConsumptionBooking"
              :can-add-activity-material="canAddActivityMaterial"
              :can-request-consumable-replenishment="canRequestConsumableNachbuchung"
              :replenishment-pack-stage="currentReplenishmentPackStage"
              :reload-token="consumablesReloadToken"
              @request-nachbuchung="openNachbuchungModal"
              @consumption-booked="onConsumableBooked"
              @edit-consumption="onEditConsumption"
            />
            </div>
          </ActivityDetailTabPane>

          <ActivityDetailTabPane v-if="showCostsTab" tab-id="costs" :active-tab="activeTab" eager>
            <div class="activity-detail-tab-panel tab-content">
            <ActivityCostsTab
              v-if="activity"
              :activity-id="activityId"
              :department-id="departmentId"
              :activity-type="activity.type"
              :activity-status="activity.status"
              :reload-token="costsReloadToken"
              :group-name="activity.group_name"
              :external-customer-label="activity.external_customer_label"
              :activity-name="activity.name"
              :costs-released="Boolean(activity.costs_released)"
              :costs-release-loading="costsReleaseInProgress"
              :can-release-costs="canReleaseCosts"
              :collection-note="activity.collection_note ?? null"
              :collection-note-amount="activity.collection_note_amount ?? null"
              :can-edit-collection-note="canManageMaterials"
              @release-costs="openCostsReleaseConfirm"
              @costs-preview="onCostsPreview"
              @collection-note-updated="onCollectionNoteUpdated"
            />
            </div>
          </ActivityDetailTabPane>

          <ActivityDetailTabPane tab-id="history" :active-tab="activeTab" eager>
            <div class="activity-detail-tab-panel tab-content">
              <ActivityHistoryTab :activity-id="activityId" />
            </div>
          </ActivityDetailTabPane>
      </div>
    </div>

    <DamageReportWizard
      :is-open="damageReportOpen"
      :department-id="departmentId"
      :preset-activity-id="activityId"
      :preset-material-item-id="damageReportPresets.materialItemId ?? null"
      :preset-issue-type="damageReportPresets.issueType ?? null"
      :preset-quantity="damageReportPresets.quantity ?? null"
      @close="onDamageWizardClose"
      @success="onDamageReportSuccess"
    />
    <ActivityConsumptionModal
      :is-open="consumptionModalOpen"
      :activity-id="activityId"
      :preset="consumptionModalPreset"
      :can-add-activity-material="canRequestConsumableNachbuchung"
      @close="onConsumptionModalClose"
      @success="onConsumptionModalSuccess"
      @return-without-consumption="onConsumptionModalReturnWithoutConsumption"
      @request-nachbuchung="onConsumptionModalRequestNachbuchung"
      @deleted="onConsumptionModalDeleted"
    />
    <ActivityConsumableNachbuchungModal
      :is-open="nachbuchungOpen"
      :activity-id="activityId"
      :department-id="departmentId"
      :material-item-id="nachbuchungMaterialId"
      :material-label="nachbuchungMaterialLabel"
      :pack-size="nachbuchungPackSize"
      :pack-unit="nachbuchungPackUnit"
      :replenishment-pack-stage="nachbuchungPackStage"
      :show-warehouse-material-hint="canAddActivityMaterial"
      @close="onNachbuchungModalClose"
      @success="onNachbuchungModalSuccess"
    />

    <EDialog
      v-model="costsReleaseConfirmOpen"
      :title="t('activities.costs.releaseConfirmTitle')"
      :max-width="480"
      :persistent="costsReleaseInProgress"
      card-variant="outlined"
    >
      <div class="costs-release-confirm">
        <p class="costs-release-confirm__lead text-muted">
          {{ t('activities.costs.releaseConfirmLead') }}
        </p>
        <p class="costs-release-confirm__charge">{{ costsReleaseConfirmChargeLabel }}</p>
        <div v-if="costsReleaseConfirmTotal != null" class="costs-release-confirm__total">
          <span>{{ t('activities.costs.releaseTotal') }}</span>
          <strong>CHF {{ costsReleaseConfirmTotalLabel }}</strong>
        </div>
        <p class="costs-release-confirm__hint text-muted">
          {{ t('activities.costs.releaseHint') }}
        </p>
      </div>
      <template #actions>
        <v-spacer />
        <EButton variant="secondary" :disabled="costsReleaseInProgress" @click="costsReleaseConfirmOpen = false">
          {{ t('common.cancel') }}
        </EButton>
        <EButton
          variant="primary"
          :loading="costsReleaseInProgress"
          @click="confirmCostsRelease()"
        >
          {{ t('activities.costs.releaseAction') }}
        </EButton>
      </template>
    </EDialog>

    <PublicQrActionModal
      :open="showActivityQrActionModal"
      :label="activity?.name"
      :code="activity?.public_code"
      :url="activityPublicUrl"
      :image-label="activity?.name"
      :image-entity-id="activity?.id"
      @close="closeActivityQrActionModal"
      @add-to-print-cart="handleActivityQrAddToPrintCart"
      @print="handleActivityQrPrint"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, unref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import {
  addActivityItem,
  assignDepartmentInviteGroup,
  ensureActivityPublicCode,
  getActivity,
  getActivityIssues,
  getActivityItems,
  getActivityTransitions,
  patchActivity,
  patchActivityStatus,
  removeActivityItem,
  syncActivityItems,
  type ActivityApiType,
  type ActivityDetail,
  type ActivityIssueReportRow,
  type ActivityItemRow,
  type ActivityCompletionBlockers,
  type ActivityTransitionRow,
} from '@/api/activities'
import { getPackItems, type ActivityPackItem } from '@/api/activityPackItems'
import ActivityMaterialAvailabilityLookup from '@/components/activities/ActivityMaterialAvailabilityLookup.vue'
import ComboConfiguratorDialog from '@/components/activities/ComboConfiguratorDialog.vue'
import ActivityMaterialLinesTable from '@/components/activities/shared/ActivityMaterialLinesTable.vue'
import ActivityDraftOverviewForm from '@/components/activities/ActivityDraftOverviewForm.vue'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'
import ActivityDetailUserLine from '@/components/activities/ActivityDetailUserLine.vue'
import ActivityCostsTab from '@/components/activities/ActivityCostsTab.vue'
import ActivityVehiclesTab from '@/components/activities/ActivityVehiclesTab.vue'
import ActivityCompletionChecklist from '@/components/activities/ActivityCompletionChecklist.vue'
import ActivityPackListTab from '@/components/activities/ActivityPackListTab.vue'
import ActivityMaterialJourneyView from '@/components/activities/ActivityMaterialJourneyView.vue'
import ActivityDetailTabPane from '@/components/activities/ActivityDetailTabPane.vue'
import ActivityIssuesTab from '@/components/activities/ActivityIssuesTab.vue'
import ActivityTabPanelShell from '@/components/activities/ActivityTabPanelShell.vue'
import ActivityConsumablesTab from '@/components/activities/ActivityConsumablesTab.vue'
import ActivityHistoryTab from '@/components/activities/ActivityHistoryTab.vue'
import ActivityConsumptionModal from '@/components/activities/ActivityConsumptionModal.vue'
import ActivityConsumableNachbuchungModal from '@/components/activities/ActivityConsumableNachbuchungModal.vue'
import ActivityJsTabView from '@/components/activities/ActivityJsTabView.vue'
import { activityTransitionActionLabel } from '@/components/activities/activityTransitionLabels'
import { packWorkflowProfileForActivityType } from '@/components/activities/packWorkflowProfile'
import DamageReportWizard from '@/components/DamageReportWizard.vue'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import PublicQrActionModal from '@/components/common/PublicQrActionModal.vue'
import { addPrintCartItem } from '@/api/tasks'
import { printHtmlDocument } from '@/utils/printHtml'
import { useAuthStore } from '@/stores/auth'
import type { ConsumptionModalPreset } from '@/components/activities/ActivityConsumptionModal.vue'
import type { ActivityMaterialLine } from '@/composables/useActivityCreateWizard'
import { COMBO_BADGE } from '@/utils/comboDisplay'
import {
  buildConsolidatedActivitySyncItems,
  childQuantityByMaterialItemIdFromItems,
  hasDuplicateMergeableStandaloneItems,
  hasDuplicateVirtualComboParents,
  isActivityItemVisibleInMaterialTable,
  isMergeableStandaloneTopLevelItem,
  extraStandaloneQtyForMaterial,
  mergeStandaloneRowsForMaterialTable,
  mergeVirtualComboParentRowsForMaterialTable,
  mergedVirtualComboPackMode,
  reservedQuantityByMaterialItemId,
  shouldIncludeTopLevelInVirtualComboSync,
  virtualComboStandaloneReduceByMaterialId,
} from '@/utils/virtualComboMaterial'
import type { MaterialScopeTab } from '@/components/activities/shared/activityMaterialAvailabilityScope'
import { getGroups, type Group } from '@/api/groups'
import { useActivityGroupMemberScope } from '@/composables/useActivityGroupMemberScope'
import { useActivityTabLoad } from '@/composables/useActivityTabLoad'
import { flattenGroupsWithLevel, type GroupWithLevel } from '@/utils/groupHierarchy'
import {
  isDepartmentBasicMemberRole,
  useDepartmentMemberRole,
} from '@/composables/useDepartmentMemberRole'
import { useBackgroundPoll } from '@/composables/useBackgroundPoll'
import { useConfirm } from '@/composables/useConfirm'
import { useDisplayHostClasses } from '@/composables/useDisplayHostClasses'
import { useSmAndUp } from '@/composables/useSmAndUp'
import { usePageHeadStore } from '@/stores/pageHead'
import { useDetailTabsStore } from '@/stores/detailTabs'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import { useToast } from '@/composables/useToast'
import { resolvePackUiPreference } from '@/utils/packUiPreference'
import { resolveActivityPublicUrl } from '@/utils/publicQrUrl'
import { activityStatusClass, activityStatusI18nKey } from '@/utils/activityStatus'
import {
  allowsAtEventToReturnedHandoff,
  allowsPackedToAtEventHandoff,
  activityAllowsConsumptionBooking,
  activityAllowsDamageReport,
  activityAllowsIssueReports,
  isMemberPostReturnHandoffLock,
  resolveActiveJourneyStep,
  resolveEffectiveActiveJourneyStep,
} from '@/utils/materialJourneyNavigation'
import { isJourneyStepWorkComplete } from '@/utils/materialJourneyStepWorkStatus'
import {
  isValidJourneyStep,
  materialJourneyStepI18nKey,
  replenishmentPackStageForContext,
  type JourneyStep,
} from '@/components/activities/materialJourneySteps'
import type { PackStage } from '@/components/activities/packStageQuantities'
import { resolveActivityPrimaryChargeTarget } from '@/utils/activityChargeTarget'
import { EButton, EDialog } from '@/components/form/base'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import QRCode from 'qrcode'

defineOptions({ name: 'ActivityDetailView' })

/** Workflow-Schritte ab Einreichung — nur MW/DC/Gruppenchef (nicht u + Gruppenmitglied). */
const MANAGER_WORKFLOW_TRANSITION_STATUSES = new Set([
  'approved',
  'packing',
  'packed',
  'transport_out',
  'at_event',
  'transport_back',
  'returned',
  'storing',
  'completed',
])

/** Typ «activity»: Gruppenmitglied darf nur Material am Event / Retour bestätigen. */
const MEMBER_ACTIVITY_PACK_HANDOFF_STATUSES = new Set(['at_event', 'returned'])

const { isRestrictedGroupMember, canCreateCampAndEvent, canSubmitActivityType, loadGroupsForDepartment } =
  useActivityGroupMemberScope()

const props = defineProps<{
  departmentId: string
  activityId: string
}>()

const route = useRoute()
const router = useRouter()
const detailTabsStore = useDetailTabsStore()
const authStore = useAuthStore()

const ACTIVITY_TAB_IDS = ['overview', 'material', 'js', 'vehicles', 'packs', 'issues', 'consumables', 'costs', 'history'] as const
type ActivityTabId = (typeof ACTIVITY_TAB_IDS)[number]

function mergeActivityQuery(updates: Record<string, string | undefined>) {
  const nextQuery = { ...route.query } as Record<string, string | string[] | null | undefined>
  for (const [k, v] of Object.entries(updates)) {
    if (v === undefined || v === '') delete nextQuery[k]
    else nextQuery[k] = v
  }
  void router.replace({ path: route.path, query: nextQuery })
}
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()
const smAndUp = useSmAndUp()
const detailDisplayClasses = useDisplayHostClasses('activity-detail-view')
const pageHeadStore = usePageHeadStore()
const headerNotificationsStore = useHeaderNotificationsStore()
const { t, te, locale } = useI18n()
const { canManageMaterials } = useDepartmentMemberRole()

function transitionActionLabel(tr: ActivityTransitionRow): string {
  return activityTransitionActionLabel(tr.status, activity.value?.status, t, te, tr.label)
}

/** Camp/Event: nur frühe Übergänge in der Kopfzeile (Einreichen … Packen); Rest über Material-Journey. */
const LOGISTICS_HEADER_HIDDEN_WORKFLOW_TARGETS = new Set([
  'transport_out',
  'at_event',
  'transport_back',
  'returned',
  'storing',
  'completed',
])

/** Camp/Event Logistics: Übergänge laufen über Material-Journey (Touren, «Material zurückführen», Retour), nicht Kopfzeile. */
function hideLogisticsHandoffTransitionInActivityHeader(targetStatus: string): boolean {
  const act = activity.value
  if (!act) return false
  if (packWorkflowProfileForActivityType(act.type || 'activity') !== 'logistics') return false
  return LOGISTICS_HEADER_HIDDEN_WORKFLOW_TARGETS.has(targetStatus)
}

/** Quick/External: Material-Übergänge (Ausgabe, Retour) laufen über Material-Journey / Packliste. */
function hideQuickHandoffTransitionInActivityHeader(targetStatus: string): boolean {
  const act = activity.value
  if (!act) return false
  const profile = packWorkflowProfileForActivityType(act.type || 'activity')
  if (profile === 'logistics') return false
  if (activeTab.value !== 'packs') {
    return (
      targetStatus === 'at_event' ||
      targetStatus === 'returned' ||
      targetStatus === 'storing' ||
      targetStatus === 'completed'
    )
  }
  if (useLegacyPackUi.value) return targetStatus === 'at_event'
  if (targetStatus === 'at_event') return true
  if (targetStatus === 'returned') return true
  return false
}

/** Quick/External: «Einlagern starten» über Journey; «Abschliessen» auf Packliste wenn Einlagern erledigt. */
function hideQuickStoringTransitionInActivityHeader(targetStatus: string): boolean {
  if (targetStatus !== 'storing' && targetStatus !== 'completed') return false
  const act = activity.value
  if (!act) return false
  const profile = packWorkflowProfileForActivityType(act.type || 'activity')
  if (profile === 'logistics') return false
  if (!canManageMaterials.value) return targetStatus === 'storing'
  if (!useLegacyPackUi.value) {
    if (
      targetStatus === 'completed' &&
      activeTab.value === 'packs' &&
      (act.status ?? '') === 'storing' &&
      storeStageCompleteForHeader.value
    ) {
      return false
    }
    return true
  }
  if (activeTab.value !== 'packs') return true
  if (targetStatus !== 'storing') return false
  const s = act.status ?? ''
  if (s !== 'returned' && s !== 'storing') return true
  if (
    s === 'returned' &&
    !isJourneyStepWorkComplete('return', profile, packItemsSnapshot.value, [], {})
  ) {
    return true
  }
  return false
}

const activity = ref<ActivityDetail | null>(null)
const isGeneratingActivityPublicCode = ref(false)
const showActivityQrActionModal = ref(false)

/** Ab «Wird gepackt»: Storno nur noch MW/DC (Material bereits aus dem Lager genommen / gepackt). */
const STATUSES_STAFF_ONLY_CANCEL = [
  'packing',
  'packed',
  'transport_out',
  'at_event',
  'transport_back',
  'returned',
  'storing',
  'completed',
  'cancelled',
] as const

const departmentRole = computed(() => String(authStore.currentDepartmentRole || 'u').toLowerCase())
const canManageActivityCancel = computed(() =>
  ['mw', 'dc', 'matwart', 'depchef', 'org', 'sa'].includes(departmentRole.value),
)
/** Storno ab Packen: MW/DC-Warnung — Material wird in der Packliste zurückgebucht. */
const STATUSES_CANCEL_PACK_WARNING = ['packing', 'packed'] as const
const canManageActivityQr = computed(() =>
  ['mw', 'dc', 'matwart', 'depchef'].includes(departmentRole.value)
)
const activityPublicUrl = computed(() =>
  resolveActivityPublicUrl(activity.value?.public_url, activity.value?.public_code),
)
const showGenerateActivityQrButton = computed(
  () => canManageActivityQr.value && !activityPublicUrl.value && !!activity.value
)

/** Mobile: QR kompakt (40px); Desktop: 64px — Tap öffnet QR-Dialog */
const headerQrSize = computed(() => (smAndUp.value ? 64 : 40))

/** Wie v4.01: Packliste erst ab «Wird gepackt», nicht schon bei «Bestätigt». */
const STATUSES_WITH_PACKS_TAB = [
  'packing',
  'packed',
  'transport_out',
  'at_event',
  'transport_back',
  'returned',
  'storing',
  'completed',
] as const

/** Lager/Event/Aktivität: Gruppe übernimmt Transport & Retour (nicht external). */
const isGroupHandoffActivityType = computed(() => {
  const typ = activity.value?.type
  return typ === 'activity' || typ === 'camp' || typ === 'event'
})

/** Untergruppe / Gruppenmitglied: Packliste schon ab Eingereicht/Bestätigt einsehen (nur Ansicht). */
const showMemberEarlyPackPreview = computed(() => {
  const s = activity.value?.status
  if (!s || !isRestrictedGroupMember.value || !isGroupHandoffActivityType.value) return false
  return s === 'submitted' || s === 'approved'
})

const showPacksTab = computed(() => {
  const s = activity.value?.status
  if (!s) return false
  if ((STATUSES_WITH_PACKS_TAB as readonly string[]).includes(s)) return true
  return showMemberEarlyPackPreview.value
})

/** Fuhrpark-Planung nur Camp/Event — ab Entwurf (MW/DC/Ersteller) bzw. ab Packliste für alle. */
const showVehiclesTab = computed(() => {
  const act = activity.value
  if (!act) return false
  const type = act.type || ''
  if (type !== 'camp' && type !== 'event') return false
  const s = act.status
  if (showPacksTab.value) return true
  if (['draft', 'submitted', 'approved'].includes(s ?? '') && canManageActivityVehicles.value) return true
  return false
})

const canManageActivityVehicles = computed(() => {
  const act = activity.value
  if (!act) return false
  if (canManageMaterials.value) return true
  if (isMemberPostReturnHandoffLock(act, false)) return false
  return act.created_by_user_id === authStore.userId
})

const useLegacyPackUi = computed(() => resolvePackUiPreference(route.query) === 'legacy')

const packWorkflowProfile = computed(() =>
  packWorkflowProfileForActivityType(activity.value?.type ?? 'activity'),
)

/** Aktiver Journey-Schritt aus URL (neue Packliste), sonst null. */
const activePackJourneyStep = computed((): JourneyStep | null => {
  if (useLegacyPackUi.value) return null
  const raw = route.query.packStep
  const s = (Array.isArray(raw) ? raw[0] : raw)?.toString().trim()
  if (!s) return null
  const profile = packWorkflowProfile.value
  return isValidJourneyStep(s, profile) ? s : null
})

/** Pipeline-Stufe für Verbrauchsmaterial-Nachlieferung am aktuellen Schritt. */
const currentReplenishmentPackStage = computed((): PackStage | null => {
  const a = activity.value
  if (!a) return null
  return replenishmentPackStageForContext(a.status ?? 'draft', packWorkflowProfile.value, {
    journeyStep: activePackJourneyStep.value,
    canManageMaterials: canManageMaterials.value,
  })
})

const showPackJourneyStepBadge = computed(() => {
  if (!activity.value || useLegacyPackUi.value || activeTab.value !== 'packs') return false
  const s = activity.value.status ?? ''
  return ['packing', 'packed', 'transport_out', 'at_event', 'transport_back', 'returned', 'storing'].includes(s)
})

const packJourneyStepLabelDetail = computed(() => {
  if (!activity.value) return ''
  const profile = packWorkflowProfile.value
  const dbStep = resolveEffectiveActiveJourneyStep(
    activity.value,
    profile,
    canManageMaterials.value,
  )
  const key = materialJourneyStepI18nKey(dbStep, profile, {
    activityStatus: activity.value.status ?? '',
  })
  return te(key) ? t(key) : dbStep
})

/** MW-Abschluss-Checkliste ab Retour/Einlagern — einheitlich über allen Tabs (inkl. Packliste). */
const showMaterialCompletionChecklist = computed(() => {
  if (!canManageMaterials.value || !activity.value || !completionBlockers.value) return false
  const s = activity.value.status ?? ''
  return ['returned', 'storing'].includes(s)
})

/** Kosten freigeben erst, wenn nichts mehr zum Einlagern offen ist. */
const canReleaseCosts = computed(() => {
  if (!activity.value) return false
  const s = activity.value.status ?? ''
  if (!['returned', 'storing'].includes(s)) return false
  return (completionBlockers.value?.unstored_pack_items_count ?? 0) === 0
})

/** Reparaturen / Verluste: ab «Am Event» (Material ausgegeben) */
const STATUSES_WITH_ISSUES_TAB = ['at_event', 'transport_back', 'returned', 'storing', 'completed'] as const

/** Gruppe: Meldungen ab «Wird gepackt» nur lesen; neue Meldungen erst ab «Am Event». */
const MEMBER_ISSUES_PREVIEW_STATUSES = ['packing', 'packed'] as const

/** Verbrauchsmaterial buchen: erst ab «Am Event» */
const STATUSES_WITH_CONSUMABLES_TAB = ['at_event', 'transport_back', 'returned', 'storing', 'completed'] as const

const showIssuesTab = computed(() => {
  const s = activity.value?.status
  if (!s) return false
  if ((STATUSES_WITH_ISSUES_TAB as readonly string[]).includes(s)) return true
  if (
    activity.value &&
    activityAllowsIssueReports(activity.value, packWorkflowProfile.value, canManageMaterials.value)
  ) {
    return true
  }
  if (
    isRestrictedGroupMember.value &&
    isGroupHandoffActivityType.value &&
    (MEMBER_ISSUES_PREVIEW_STATUSES as readonly string[]).includes(s)
  ) {
    return true
  }
  return false
})

const showIssuesTabReadOnlyHint = computed(
  () => showIssuesTab.value && !showDamageReportEntry.value && isRestrictedGroupMember.value,
)

/** Hinweis für Mitglieder in Parent-/Untergruppen-Zweig vor «Am Event». */
const showMemberScopeStatusHint = computed(() => {
  const s = activity.value?.status
  if (!s || !isRestrictedGroupMember.value || !isGroupHandoffActivityType.value) return false
  return ['submitted', 'approved', 'packing', 'packed'].includes(s)
})

/** Gruppe nach Retour-Übergabe: Hinweis nur ausserhalb Packliste (dort zeigt der Journey-Banner). */
const showMemberPostReturnHandoffLockHint = computed(() => {
  if (!isMemberPostReturnHandoffLock(activity.value, canManageMaterials.value)) return false
  if (activeTab.value === 'packs' && !useLegacyPackUi.value) return false
  return true
})

function activityItemIsConsumable(row: ActivityItemRow): boolean {
  return row.is_consumable === true
}

const packItemsSnapshot = ref<ActivityPackItem[]>([])

const hasConsumableItems = computed(() =>
  activityItems.value.some(activityItemIsConsumable) ||
  packItemsSnapshot.value.some((pi) => pi.isConsumable),
)

const consumableMaterialItemIds = computed(() => {
  const ids = new Set<string>()
  for (const row of activityItems.value) {
    if (activityItemIsConsumable(row) && row.material_item_id) {
      ids.add(row.material_item_id)
    }
  }
  for (const pi of packItemsSnapshot.value) {
    if (pi.isConsumable) ids.add(pi.materialItemId)
  }
  return [...ids]
})

const showConsumablesTab = computed(() => {
  const s = activity.value?.status
  if (!s || !hasConsumableItems.value) return false
  if ((STATUSES_WITH_CONSUMABLES_TAB as readonly string[]).includes(s)) return true
  return (
    activity.value != null &&
    activityAllowsConsumptionBooking(activity.value, packWorkflowProfile.value, canManageMaterials.value)
  )
})

const activityIssues = ref<ActivityIssueReportRow[]>([])
const issuesDataReady = ref(false)

/** Reparatur / Verlust / Schaden — löst für Gruppe den Tab «Kosten» aus */
const COSTS_TAB_ISSUE_TYPES = ['repair', 'loss', 'damage'] as const

const hasRepairOrLossIssues = computed(() =>
  activityIssues.value.some((r) =>
    (COSTS_TAB_ISSUE_TYPES as readonly string[]).includes(r.type),
  ),
)

/**
 * Kosten: ab «Wird gepackt».
 * Gruppe: erst bei Verbrauchsmaterial auf der Aktivität oder gemeldeten Reparaturen/Verlusten.
 * MW/DC: ab Packen (Abrechnung/Kontrolle).
 */
const showCostsTab = computed(() => {
  if (!showPacksTab.value) return false
  if (canReportDamageAsMaterialStaff.value) return true
  return hasConsumableItems.value || hasRepairOrLossIssues.value
})

/** Ohne ?tab=: v4.01 — Packliste als Start nur bei packing…returned (nicht bei completed). */
function defaultTabWhenNoQuery(status: string | undefined): ActivityTabId {
  if (status && ['packing', 'packed', 'transport_out', 'at_event', 'transport_back', 'returned', 'storing'].includes(status)) return 'packs'
  return 'overview'
}

const tabs = computed(() => {
  const out: { id: ActivityTabId; label: string }[] = [
    { id: 'overview', label: t('activities.detail.tabOverview') },
    { id: 'material', label: t('common.material') },
  ]
  if (showJsOrderCard.value) {
    out.push({ id: 'js', label: t('activities.jsMaterial.tabTitle') })
  }
  if (showVehiclesTab.value) {
    out.push({ id: 'vehicles', label: t('activities.detail.tabVehicles') })
  }
  if (showPacksTab.value) {
    out.push({ id: 'packs', label: t('activities.detail.tabPacks') })
  }
  if (showConsumablesTab.value) {
    out.push({ id: 'consumables', label: t('activities.detail.tabConsumables') })
  }
  if (showIssuesTab.value) {
    out.push({ id: 'issues', label: t('activities.detail.tabIssues') })
  }
  if (showCostsTab.value) {
    out.push({ id: 'costs', label: t('activities.detail.tabCosts') })
  }
  out.push({ id: 'history', label: t('activities.detail.tabHistory') })
  return out
})

const tabIds = computed(() => tabs.value.map((tab) => tab.id))

function normalizeActivityTabQuery(value: unknown): ActivityTabId | null {
  const raw = Array.isArray(value) ? value[0] : value
  const s = typeof raw === 'string' ? raw.trim() : ''
  if (!s) return null
  const ids = tabIds.value as readonly string[]
  return ids.includes(s) ? (s as ActivityTabId) : null
}

const transitions = ref<ActivityTransitionRow[]>([])
const completionBlockers = ref<ActivityCompletionBlockers | null>(null)

type ActivityPackListTabExpose = {
  confirmBeforeWorkflowTransition: (transition: ActivityTransitionRow) => Promise<boolean>
}

type ActivityMaterialJourneyViewExpose = {
  showPackCompletePanel: boolean
  markPacked: () => Promise<void>
}

const packListTabRef = ref<ActivityPackListTabExpose | null>(null)
const materialJourneyRef = ref<ActivityMaterialJourneyViewExpose | null>(null)

/** «Gepackt markieren» in der Kopfzeile — nur wenn Packliste/Journey meldet: alles erledigt. */
const packingStageCompleteForHeader = ref(false)
const storeStageCompleteForHeader = ref(false)

function onPackingHeaderReady(ready: boolean): void {
  packingStageCompleteForHeader.value = ready
}

function onStoreHeaderReady(ready: boolean): void {
  storeStageCompleteForHeader.value = ready
  if (ready) void refreshCompletionBlockers()
}

watch(
  () => activity.value?.status,
  (status) => {
    if (status !== 'packing') packingStageCompleteForHeader.value = false
    if (!['returned', 'storing'].includes(status ?? '')) storeStageCompleteForHeader.value = false
  },
)

function transitionNeedsPackListConfirmation(transition: ActivityTransitionRow): boolean {
  const a = activity.value
  if (!a?.is_pack_list_editable) return false
  const s = a.status
  if (transition.status === 'packing' && s === 'packed') return false
  if (transition.status === 'packed' && s === 'at_event') return false
  if (transition.status === 'at_event' && s === 'returned') return false
  if (transition.status === 'at_event' && (s === 'packed' || s === 'packing')) return true
  if (transition.status === 'returned' && s === 'at_event') return true
  if (transition.status === 'at_event' && s === 'returned') return true
  if (transition.status === 'packed' && s === 'packing') return true
  return false
}

async function ensurePackListTabForTransition(): Promise<ActivityPackListTabExpose | null> {
  if (activeTab.value !== 'packs') {
    activeTab.value = 'packs'
    mergeActivityQuery({ tab: 'packs' })
    await nextTick()
  }
  return packListTabRef.value
}
const {
  showFullLoading: itemsShowFullLoading,
  isRefreshing: itemsRefreshing,
  resetTabLoad: resetItemsTabLoad,
  markHydrated: markItemsHydrated,
  withTabLoad: withItemsLoad,
} = useActivityTabLoad()
const activityItems = ref<ActivityItemRow[]>([])
const isLoading = ref(true)
const loadError = ref<string | null>(null)
const isTransitioning = ref(false)
const activeTab = ref<ActivityTabId>('overview')

watch(showPacksTab, (show) => {
  if (!show && activeTab.value === 'packs') {
    activeTab.value = 'overview'
    mergeActivityQuery({ tab: 'overview' })
  }
})

watch(showVehiclesTab, (show) => {
  if (!show && activeTab.value === 'vehicles') {
    activeTab.value = 'overview'
    mergeActivityQuery({ tab: 'overview' })
  }
})

watch(showIssuesTab, (show) => {
  if (!show && activeTab.value === 'issues') {
    activeTab.value = 'overview'
    mergeActivityQuery({ tab: 'overview' })
  }
})

watch(showConsumablesTab, (show) => {
  if (!show && activeTab.value === 'consumables') {
    activeTab.value = 'overview'
    mergeActivityQuery({ tab: 'overview' })
  }
})

watch(showCostsTab, (show) => {
  if (!show && activeTab.value === 'costs') {
    activeTab.value = 'overview'
    mergeActivityQuery({ tab: 'overview' })
  }
})
const addingDraftMaterial = ref(false)
const removingItemId = ref<string | null>(null)
const draftQuantities = ref<Record<string, number>>({})
const draftPackModes = ref<Record<string, 'together' | 'loose'>>({})
const syncingQuantities = ref(false)
const draftOverviewFormRef = ref<InstanceType<typeof ActivityDraftOverviewForm> | null>(null)

/** Detail: nahezu live für alle Beteiligten (Status, Entwurf, Material, Übergänge). */
const ACTIVITY_DETAIL_LIVE_POLL_MS = 4000

const noLabel = computed(() => {
  const n = activity.value?.no
  if (n == null) return ''
  return `#${String(n).padStart(3, '0')}`
})

/**
 * Workflow-Buttons außer Stornieren.
 * - Tab «Packliste»: kein Übergang zum Ziel «packing» (Packliste ist schon offen).
 * - Status «Gepackt»: kein Button zur Korrektur packing in der Kopfzeile (irreführend / nicht gewünscht).
 */
const workflowTransitions = computed(() =>
  transitions.value.filter((t) => {
    if (t.status === 'cancelled') return false
    // approved → submitted = «Zurückweisen» (nicht Entwurf einreichen)
    if (
      isRestrictedGroupMember.value &&
      t.status === 'submitted' &&
      activity.value?.status !== 'draft'
    ) {
      return false
    }
    if (
      isRestrictedGroupMember.value &&
      activity.value?.type === 'activity' &&
      activity.value.status !== 'draft' &&
      !MEMBER_ACTIVITY_PACK_HANDOFF_STATUSES.has(t.status)
    ) {
      return false
    }
    if (
      isRestrictedGroupMember.value &&
      activity.value?.type !== 'activity' &&
      MANAGER_WORKFLOW_TRANSITION_STATUSES.has(t.status)
    ) {
      return false
    }
    if (
      t.status === 'submitted' &&
      activity.value &&
      !canSubmitActivityType(activity.value.type || 'activity', activity.value.can_submit_activity)
    ) {
      return false
    }
    if (activeTab.value === 'packs' && t.status === 'packing') return false
    if (activity.value?.status === 'packed' && t.status === 'packing') return false
    const s = activity.value?.status
    if (s === 'at_event' && t.status === 'packed') return false
    if (s === 'returned' && t.status === 'at_event') return false
    if (s === 'storing' && (t.status === 'returned' || t.status === 'at_event')) return false
    if (
      s === 'packed' &&
      t.status === 'at_event' &&
      activity.value &&
      !allowsPackedToAtEventHandoff(activity.value, packWorkflowProfile.value)
    ) {
      return false
    }
    if (
      s === 'transport_out' &&
      t.status === 'at_event' &&
      activity.value &&
      !allowsPackedToAtEventHandoff(activity.value, packWorkflowProfile.value)
    ) {
      return false
    }
    if (
      (s === 'at_event' || s === 'transport_back') &&
      t.status === 'returned' &&
      activity.value &&
      !allowsAtEventToReturnedHandoff(activity.value, packWorkflowProfile.value)
    ) {
      return false
    }
    // Quick-Modus: kein «Bestätigen» — Material ist bei Einreichung bereits final
    if (activity.value?.type === 'activity' && t.status === 'approved') return false
    if (hideLogisticsHandoffTransitionInActivityHeader(t.status)) return false
    if (hideQuickHandoffTransitionInActivityHeader(t.status)) return false
    if (hideQuickStoringTransitionInActivityHeader(t.status)) return false
    if (s === 'packing' && t.status === 'packed' && !packingStageCompleteForHeader.value) {
      return false
    }
    if (s === 'storing' && t.status === 'completed' && !storeStageCompleteForHeader.value) {
      return false
    }
    if (s === 'returned' && t.status === 'completed') return false
    return true
  }),
)

const activityTypeForMat = computed(
  (): ActivityApiType => (activity.value?.type || 'activity') as ActivityApiType,
)

/** Summe aktueller Mengen pro Material (Entwurf): ohne Doppelzählung Kind-Zeilen vs. Kombo-Snapshot. */
const quantityByMaterialItemId = computed(() => {
  const fromReserved = reservedQuantityByMaterialItemId(activityItems.value, (r) =>
    draftQty(r as ActivityItemRow),
  )
  const fromChildMap = childQuantityByMaterialItemId.value
  const ids = new Set([...Object.keys(fromReserved), ...Object.keys(fromChildMap)])
  const m: Record<string, number> = {}
  for (const id of ids) {
    m[id] = Math.max(fromReserved[id] ?? 0, fromChildMap[id] ?? 0)
  }
  return m
})

/** Gespeicherte Summen pro Material (API) — für Verfügbarkeit in der Suche vs. Entwurf */
const savedQuantityByMaterialItemId = computed(() =>
  reservedQuantityByMaterialItemId(activityItems.value, (r) => r.quantity ?? 0),
)

/** Nur eigenständige Einzelpositionen (kein Kombo-Kind, keine Kombo-Hülle) — für Suche / „Kombinieren?". */
const standaloneQuantityByMaterialItemId = computed(() => {
  const m: Record<string, number> = {}
  for (const r of activityItems.value) {
    if (r.parent_activity_item_id) continue
    if (r.material_type === 'physical_combo' || r.material_type === 'virtual_combo') continue
    m[r.material_item_id] = (m[r.material_item_id] ?? 0) + draftQty(r)
  }
  return m
})

/** Aufgelöste Kind-Zeilen virtueller Kombos (Entwurf-Mengen) — für Kombo-Floor in der Tabelle. */
const childQuantityByMaterialItemId = computed(() =>
  childQuantityByMaterialItemIdFromItems(activityItems.value, {
    draftPackModeByItemId: draftPackModes.value,
    quantityFor: (r) => {
      const id = r.activity_item_id ?? r.id
      const row = id ? activityItems.value.find((x) => x.id === id) : undefined
      return row ? draftQty(row) : Math.max(0, r.quantity ?? 0)
    },
  }),
)

/** pack_mode änderbar bis vor «gepackt» (draft / submitted / approved). */
const virtualComboPackModeEditable = computed(() => {
  const status = activity.value?.status ?? ''
  return ['draft', 'submitted', 'approved'].includes(status) && showMaterialLookup.value
})

function parsePlanningDate(iso: string | undefined | null): Date | null {
  if (!iso) return null
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? null : d
}

const planningStartDate = computed(() => parsePlanningDate(activity.value?.planning_start))
const planningEndDate = computed(() => parsePlanningDate(activity.value?.planning_end))

/** Gemeinsame Tabellenkomponente (Wizard / Detail-Entwurf) */
const materialLookupScopeTab = ref<MaterialScopeTab>('own')
const materialLookupSinglePartnerId = ref<string | null>(null)

function onMaterialLookupScopeChange(payload: {
  tab: MaterialScopeTab
  singlePartnerDepartmentId: string | null
}) {
  materialLookupScopeTab.value = payload.tab
  materialLookupSinglePartnerId.value = payload.singlePartnerDepartmentId
}

const hasAcceptedPartnerDepts = computed(() =>
  (activity.value?.invited_departments ?? []).some((i) => (i.status ?? 'pending') === 'accepted'),
)

const guestInviteContext = computed(() => activity.value?.guest_invite_for_viewer ?? null)

const showGuestInviteGroupAssign = computed(
  () => !!guestInviteContext.value?.can_assign_group && !!guestInviteContext.value.department_id,
)

const guestInviteGroups = ref<Group[]>([])
const guestInviteGroupsLoading = ref(false)
const guestInviteGroupId = ref('')
const guestInviteGroupSaving = ref(false)

const guestInviteFlatGroups = computed(() => flattenGroupsWithLevel(guestInviteGroups.value))

function guestInviteGroupLabel(g: GroupWithLevel): string {
  const indent = g._level > 0 ? `${'— '.repeat(g._level)}` : ''
  return `${indent}${g.name}`
}

async function loadGuestInviteGroups(deptId: string) {
  guestInviteGroupsLoading.value = true
  try {
    guestInviteGroups.value = await getGroups(deptId)
  } catch {
    guestInviteGroups.value = []
  } finally {
    guestInviteGroupsLoading.value = false
  }
}

watch(
  () => [guestInviteContext.value?.department_id, guestInviteContext.value?.group_id] as const,
  async ([deptId, groupId]) => {
    guestInviteGroupId.value = groupId ?? ''
    if (!deptId) {
      guestInviteGroups.value = []
      return
    }
    await loadGuestInviteGroups(deptId)
  },
  { immediate: true },
)

async function saveGuestInviteGroup() {
  const ctx = guestInviteContext.value
  const groupId = guestInviteGroupId.value.trim()
  if (!ctx?.department_id || !groupId || !activity.value) return
  guestInviteGroupSaving.value = true
  try {
    const result = await assignDepartmentInviteGroup(props.activityId, {
      departmentId: ctx.department_id,
      groupId,
    })
    const invites = [...(activity.value.invited_departments ?? [])]
    const idx = invites.findIndex((inv) => inv.id === ctx.department_id)
    if (idx >= 0) {
      invites[idx] = {
        ...invites[idx],
        group_id: result.group_id,
        group_name: result.group_name,
      }
      activity.value = { ...activity.value, invited_departments: invites, guest_invite_for_viewer: {
        ...ctx,
        group_id: result.group_id,
        group_name: result.group_name,
      } }
    }
    toast.success(t('activities.detail.guestInviteGroupSaved'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } } }
    toast.error(e?.response?.data?.error || t('activities.detail.guestInviteGroupSaveFailed'))
  } finally {
    guestInviteGroupSaving.value = false
  }
}

/**
 * Entwurfs-Detail mit AutoSave: nur Lager / Event / extern (Typ «activity» ohne Entwurfmodus).
 * Erstell-Wizard nutzt normale E*-Felder ohne AutoSave.
 */
const showOverviewEditForm = computed(() => {
  const a = activity.value
  if (!a || a.status !== 'draft') return false
  const typ = (a.type || 'activity') as ActivityApiType
  return typ === 'camp' || typ === 'event' || typ === 'external'
})

/** MW/DC: Materialzeilen bearbeiten bis vor «Retour» (API-Flag oder lokale Rolle). */
const MW_ACTIVITY_MATERIAL_EDIT_STATUSES = [
  'submitted',
  'approved',
  'packing',
  'packed',
  'transport_out',
  'at_event',
  'transport_back',
] as const

const canEditActivityMaterialLines = computed(() => {
  const a = activity.value
  if (!a) return false
  if (a.status === 'draft') return !!a.can_edit_draft_material
  if (a.can_edit_activity_material) return true
  return (
    canManageMaterials.value &&
    (MW_ACTIVITY_MATERIAL_EDIT_STATUSES as readonly string[]).includes(a.status ?? '')
  )
})

const showMaterialLookup = computed(() => canEditActivityMaterialLines.value)

const showJsOrderCard = computed(() => {
  const a = activity.value
  if (!a) return false
  return a.wants_js_material === true && (a.type === 'camp' || a.type === 'event')
})

const canEditJsOrder = computed(() => {
  // Bestellformular folgt Material-Rechten. Empfang/Retour-Checks im J+S-Tab
  // bleiben für die Gruppe nach Übergabe über checksReadonly offen.
  if (isMemberPostReturnHandoffLock(activity.value, canManageMaterials.value)) return false
  return showMaterialLookup.value
})

watch(showJsOrderCard, (show) => {
  if (!show && activeTab.value === 'js') {
    activeTab.value = 'overview'
    mergeActivityQuery({ tab: undefined })
  }
})

/** Status ab «Annehmen & Packen» — Accordion «vergessen» ausblenden. */
const STATUSES_AT_OR_AFTER_PACKING = [
  'packing',
  'packed',
  'at_event',
  'returned',
  'completed',
  'cancelled',
] as const

const isBasicDepartmentMember = computed(() => isDepartmentBasicMemberRole(departmentRole.value))

/** u–l3: Accordion «vergessen» ab «eingereicht», nicht im Entwurf (dort: Materialtabelle). Bis «packing» aus. */
const showForgottenMaterialAccordion = computed(() => {
  const a = activity.value
  if (!a || !isBasicDepartmentMember.value) return false
  const status = a.status || ''
  if (status === 'draft') return false
  if ((STATUSES_AT_OR_AFTER_PACKING as readonly string[]).includes(status)) return false
  return !!a.can_add_forgotten_material
})

const forgottenMaterialExpanded = ref(false)

/** Entwurf: Gruppe/User/L1–L3 — Material suchen und hinzufügen (ohne «vergessen»-Akkordeon). */
const showDraftMaterialAddForGroup = computed(() => {
  const a = activity.value
  if (!a || a.status !== 'draft') return false
  return !!a.can_edit_draft_material && isBasicDepartmentMember.value
})

/** «Material hinzufügen» im Material-Tab (MW/DC mit Bearbeitungsrecht, bis vor «Retour»). */
const showMaterialAddOnMaterialTab = computed(
  () => canEditActivityMaterialLines.value && canManageMaterials.value,
)

const materialLinesForEditableTable = computed((): ActivityMaterialLine[] => {
  if (!showMaterialLookup.value) return []
  const items = activityItems.value
  const packModes = draftPackModes.value
  const qtyFor = (r: ActivityItemRow) => draftQty(r)
  const seenLooseChild = new Set<string>()
  const visible = items.filter((r) => {
    if (!isActivityItemVisibleInMaterialTable(r, items, packModes)) return false
    if (!r.parent_activity_item_id) return true
    const key = `${r.parent_activity_item_id}:${r.material_item_id}`
    if (seenLooseChild.has(key)) return false
    seenLooseChild.add(key)
    return true
  })


  const syntheticLooseChildren: ActivityMaterialLine[] = []
  for (const { representative: parent, members } of mergeVirtualComboParentRowsForMaterialTable(
    items.filter((r) => r.material_type === 'virtual_combo' && !r.parent_activity_item_id),
  )) {
    if (draftPackMode(parent) !== 'loose') continue
    if (members.some((m) => items.some((c) => c.parent_activity_item_id === m.id))) continue
    const comboQty = Math.max(1, qtyFor(parent))
    for (const c of parent.config_snapshot?.resolved_components ?? []) {
      const mid = c.component_material_id
      if (!mid) continue
      const total =
        typeof c.total_qty === 'number'
          ? c.total_qty
          : Math.max(0, (c.qty_per_combo ?? 0) * comboQty)
      if (total <= 0) continue
      const name =
        c.name ||
        items.find((i) => i.material_item_id === mid)?.material_name ||
        mid
      syntheticLooseChildren.push({
        material_item_id: mid,
        material_name: name,
        quantity: total,
        saved_quantity: total,
        parent_activity_item_id: parent.id,
        activity_item_id: `__loose_child_${parent.id}_${mid}`,
        source_department_name: parent.source_department_name ?? null,
        tracking_type: items.find((i) => i.material_item_id === mid)?.tracking_type ?? null,
      })
    }
  }

  const standaloneLines = mergeStandaloneRowsForMaterialTable(
    visible.filter(
      (r) =>
        !r.parent_activity_item_id &&
        r.material_type !== 'virtual_combo' &&
        r.material_type !== 'physical_combo',
    ),
  ).flatMap(({ representative, members }) => {
    const line = activityItemToMaterialLine(representative)
    const standaloneRaw = members.reduce((sum, r) => sum + qtyFor(r), 0)
    const extraQty = extraStandaloneQtyForMaterial(
      line.material_item_id,
      standaloneRaw,
      items,
      packModes,
      (r) => draftQty(r as ActivityItemRow),
    )
    if (extraQty <= 0) return []
    line.quantity = extraQty
    const savedRaw = members.reduce((sum, r) => sum + (r.quantity ?? 0), 0)
    line.saved_quantity = extraStandaloneQtyForMaterial(
      line.material_item_id,
      savedRaw,
      items,
      packModes,
      (r) => r.quantity ?? 0,
    )
    return [line]
  })

  const childLineByMaterial = new Map<string, ActivityItemRow>()
  for (const r of visible) {
    if (!r.parent_activity_item_id) continue
    const existing = childLineByMaterial.get(r.material_item_id)
    if (!existing || qtyFor(r) > qtyFor(existing)) {
      childLineByMaterial.set(r.material_item_id, r)
    }
  }
  const childLines = [...childLineByMaterial.values()].map((r) => activityItemToMaterialLine(r))

  const comboParents = mergeVirtualComboParentRowsForMaterialTable(
    visible.filter((r) => r.material_type === 'virtual_combo' && !r.parent_activity_item_id),
  ).map(({ representative, members }) => {
    const line = activityItemToMaterialLine(representative)
    const mode = mergedVirtualComboPackMode(members, packModes)
    if (line.config_snapshot) {
      line.config_snapshot = { ...line.config_snapshot, pack_mode: mode }
    }
    line.pack_mode = mode
    return line
  })

  return [...comboParents, ...childLines, ...syntheticLooseChildren, ...standaloneLines]
})

/** Vor Sync: Kombo-Set-Bedarf von eigenständigen Zeilen abziehen (Extra bleibt). */
function splitStandaloneForVirtualCombosInDraft(): boolean {
  const reduceByMaterial = virtualComboStandaloneReduceByMaterialId(
    activityItems.value,
    draftPackModes.value,
    (r) => draftQty(r as ActivityItemRow),
  )
  let changed = false
  const nextQty = { ...draftQuantities.value }
  for (const [materialId, reduceTotal] of Object.entries(reduceByMaterial)) {
    let remaining = reduceTotal
    for (const row of activityItems.value) {
      if (!isMergeableStandaloneTopLevelItem(row)) continue
      if (row.material_item_id !== materialId) continue
      const current = draftQty(row)
      const take = Math.min(remaining, current)
      const reduced = current - take
      if (reduced !== current) {
        nextQty[row.id] = reduced
        changed = true
      }
      remaining -= take
      if (remaining <= 0) break
    }
  }
  if (changed) draftQuantities.value = nextQty
  return changed
}

function buildDraftSyncItems(itemsOverride?: ActivityItemRow[]) {
  const items = itemsOverride ?? activityItems.value
  const standaloneRawByMaterial = new Map<string, number>()
  for (const r of items) {
    if (!isMergeableStandaloneTopLevelItem(r)) continue
    const mid = r.material_item_id
    standaloneRawByMaterial.set(mid, (standaloneRawByMaterial.get(mid) ?? 0) + draftQty(r))
  }
  const syncExtraByMaterial = new Map<string, number>()
  for (const [mid, raw] of standaloneRawByMaterial) {
    syncExtraByMaterial.set(
      mid,
      extraStandaloneQtyForMaterial(mid, raw, items, draftPackModes.value, (row) =>
        draftQty(row as ActivityItemRow),
      ),
    )
  }
  const allocatedExtra = new Map<string, number>()

  return buildConsolidatedActivitySyncItems(items, {
    quantityFor: (r) => {
      if (!isMergeableStandaloneTopLevelItem(r)) return draftQty(r)
      const mid = r.material_item_id
      const totalExtra = syncExtraByMaterial.get(mid) ?? 0
      const allocated = allocatedExtra.get(mid) ?? 0
      const remaining = Math.max(0, totalExtra - allocated)
      const qty = Math.min(draftQty(r), remaining)
      allocatedExtra.set(mid, allocated + qty)
      return qty
    },
    includeRow: (r) => shouldIncludeTopLevelInVirtualComboSync(r, items, draftPackModes.value),
    extrasForRow: virtualComboSyncExtras,
  })
}

function activityItemToMaterialLine(r: ActivityItemRow): ActivityMaterialLine {
  return {
    material_item_id: r.material_item_id,
    material_name: r.material_name,
    material_type: r.material_type ?? null,
    linked_container_label: r.linked_container_label ?? null,
    quantity: draftQty(r),
    saved_quantity: r.quantity,
    period_availability_cap: undefined,
    pack_size: r.pack_size,
    pack_unit: r.pack_unit,
    activity_item_id: r.id,
    parent_activity_item_id: r.parent_activity_item_id ?? null,
    source_department_name: r.source_department_name ?? null,
    line_total: r.line_total,
    is_js_material: r.is_js_material,
    tracking_type: r.tracking_type ?? null,
    is_container: !!r.is_container,
    config_snapshot: r.config_snapshot
      ? {
          ...r.config_snapshot,
          pack_mode: draftPackMode(r),
        }
      : null,
    pack_mode: draftPackMode(r),
    is_replenishment: r.is_replenishment === true,
  }
}

/** Read-only-Tabelle: nur Eltern-/Normalzeilen (Kombo-Inhalt wird verschachtelt gezeigt). */
const topLevelActivityItems = computed((): ActivityItemRow[] =>
  activityItems.value.filter((r) => !r.parent_activity_item_id),
)

/**
 * Set-Inhalt einer gebuchten virtuellen Kombo „wie Kiste" (aus dem config_snapshot).
 * Gibt `null` zurück, wenn keine aufgelösten Teile vorliegen.
 */
function comboSetContent(row: ActivityItemRow): {
  resolved: NonNullable<ActivityItemRow['config_snapshot']>['resolved_components']
  selfProvided: NonNullable<ActivityItemRow['config_snapshot']>['self_provided']
} | null {
  if (row.material_type !== 'virtual_combo' && row.material_type !== 'physical_combo') return null
  const snap = row.config_snapshot
  const resolved = snap?.resolved_components ?? []
  const selfProvided = snap?.self_provided ?? []
  if (resolved.length === 0 && selfProvided.length === 0) return null
  return { resolved, selfProvided }
}

function draftPackMode(row: ActivityItemRow): 'together' | 'loose' {
  const d = draftPackModes.value[row.id]
  if (d === 'together' || d === 'loose') return d
  const snap = row.config_snapshot?.pack_mode
  return snap === 'together' ? 'together' : 'loose'
}

function virtualComboSyncExtras(r: ActivityItemRow): {
  selected_option_ids?: string[]
  pack_mode?: 'together' | 'loose'
  self_provided_acknowledged?: boolean
} {
  if (r.material_type !== 'virtual_combo') return {}
  const snap = r.config_snapshot
  return {
    ...(snap?.selected_option_ids ? { selected_option_ids: snap.selected_option_ids } : {}),
    pack_mode: draftPackMode(r),
    ...(snap?.self_provided_acknowledged ? { self_provided_acknowledged: true } : {}),
  }
}

const virtualComboSelfProvidedHints = computed(() => {
  const out: Array<{
    comboName: string
    items: Array<{ name: string; total_qty: number }>
  }> = []
  for (const r of activityItems.value) {
    if (r.material_type !== 'virtual_combo' || r.parent_activity_item_id) continue
    const sp = r.config_snapshot?.self_provided ?? []
    if (sp.length === 0) continue
    out.push({
      comboName: r.material_name,
      items: sp.map((x) => ({ name: x.name, total_qty: x.total_qty })),
    })
  }
  return out
})

const cancelTransition = computed(() => {
  const tr = transitions.value.find((t) => t.status === 'cancelled' && t.allowed)
  if (!tr) return undefined
  const s = activity.value?.status
  if (
    s &&
    !canManageActivityCancel.value &&
    (STATUSES_STAFF_ONLY_CANCEL as readonly string[]).includes(s)
  ) {
    return undefined
  }
  return tr
})

/** MW/DC: Schaden/Reparatur/Verlust auch nach «Retour erfassen» (Auspacken). */
const canReportDamageAsMaterialStaff = computed(() =>
  ['mw', 'dc', 'matwart', 'depchef'].includes(departmentRole.value),
)

/** Reparaturen / Verluste: ab Journey-Schritt «Am Anlass» (auch bei Status «Gepackt»). */
const showDamageReportEntry = computed(() => {
  const a = activity.value
  if (!a) return false
  return activityAllowsDamageReport(
    a,
    packWorkflowProfile.value,
    canReportDamageAsMaterialStaff.value,
    canManageMaterials.value,
  )
})

/** Meldungen inline in Material-Journey — «Schaden melden» oben auf Packliste ausblenden. */
const showDamageReportInActivityHeader = computed(() => {
  if (!showDamageReportEntry.value) return false
  if (activeTab.value !== 'packs' || useLegacyPackUi.value) return true
  return false
})

/** Verbrauch buchen: ab Journey-Schritt «Am Anlass» (auch bei Status «Gepackt»). */
const showConsumptionBooking = computed(() => {
  const a = activity.value
  if (!a) return false
  return activityAllowsConsumptionBooking(a, packWorkflowProfile.value, canManageMaterials.value)
})

/** Nachbuchung zur Aktivität (addActivityItem) — wie Tab «Material» */
const canAddActivityMaterial = computed(() => canEditActivityMaterialLines.value)

/** Nachlieferung Verbrauchsmaterial — nur wenn API es erlaubt (Gruppe nur «Am Event», nicht nach Retour an MW). */
const canRequestConsumableNachbuchung = computed(
  () => activity.value?.can_request_consumable_replenishment === true,
)

const damageReportOpen = ref(false)
const damageReportPresets = ref<{
  materialItemId?: string
  issueType?: 'damage' | 'repair' | 'loss'
  quantity?: number
}>({})
const damageReportQueue = ref<
  Array<{
    materialItemId: string
    issueType: 'damage' | 'repair' | 'loss'
    quantity?: number
  }>
>([])
const issuesReloadToken = ref(0)
const consumablesReloadToken = ref(0)
const costsReloadToken = ref(0)
const packListReloadToken = ref(0)
const vehiclesReloadToken = ref(0)

watch(issuesReloadToken, () => {
  void loadActivityIssues()
})

watch(packListReloadToken, () => {
  void loadPackItemsSnapshot()
})

watch(showPacksTab, (show) => {
  if (show) void loadPackItemsSnapshot()
  else packItemsSnapshot.value = []
})

const consumptionModalOpen = ref(false)
const consumptionModalPreset = ref<ConsumptionModalPreset | null>(null)
const consumptionModalCancelledToken = ref(0)
const consumptionModalReturnWithoutConsumptionToken = ref(0)
const skipNextConsumptionModalCloseCancel = ref(false)

const nachbuchungOpen = ref(false)
const nachbuchungMaterialId = ref('')
const nachbuchungMaterialLabel = ref('')
const nachbuchungPackSize = ref<number | null>(null)
const nachbuchungPackUnit = ref<string | null>(null)
const nachbuchungPackStage = ref<string | null>(null)

function openDamageReport(opts?: {
  materialItemId?: string
  issueType?: 'damage' | 'repair' | 'loss'
  quantity?: number
}) {
  damageReportPresets.value =
    opts && (opts.materialItemId != null || opts.issueType != null || opts.quantity != null) ? { ...opts } : {}
  damageReportOpen.value = true
}

function onDamageWizardClose() {
  damageReportQueue.value = []
  damageReportOpen.value = false
  damageReportPresets.value = {}
}

async function openNextDamageReportFromQueue() {
  const next = damageReportQueue.value.shift()
  if (!next) return
  damageReportOpen.value = false
  damageReportPresets.value = {}
  await nextTick()
  openDamageReport({
    materialItemId: next.materialItemId,
    issueType: next.issueType,
    quantity: next.quantity,
  })
}

function onOpenConsumptionModal(payload: ConsumptionModalPreset) {
  if (!showConsumptionBooking.value) return
  consumptionModalPreset.value = payload
  consumptionModalOpen.value = true
}

function onEditConsumption(payload: ConsumptionModalPreset) {
  if (payload.editIssueId) {
    if (!activity.value?.can_report_issues) return
  } else if (!showConsumptionBooking.value) {
    return
  }
  consumptionModalPreset.value = payload
  consumptionModalOpen.value = true
}

function onConsumptionModalDeleted() {
  skipNextConsumptionModalCloseCancel.value = true
  issuesReloadToken.value += 1
  consumablesReloadToken.value += 1
  costsReloadToken.value += 1
  packListReloadToken.value += 1
  toast.success(t('activities.detail.toastConsumptionDeleted'))
  void loadItems().catch(() => {})
  void refreshActivityTotalsFromApi().catch(() => {})
}

function onConsumptionModalClose() {
  if (!skipNextConsumptionModalCloseCancel.value) {
    consumptionModalCancelledToken.value += 1
  }
  skipNextConsumptionModalCloseCancel.value = false
  consumptionModalOpen.value = false
  consumptionModalPreset.value = null
}

function onConsumptionModalReturnWithoutConsumption() {
  consumptionModalReturnWithoutConsumptionToken.value += 1
  consumptionModalOpen.value = false
  consumptionModalPreset.value = null
}

function openNachbuchungModal(payload: {
  materialItemId: string
  materialLabel: string
  packSize?: number | null
  packUnit?: string | null
  packStage?: string
}) {
  nachbuchungMaterialId.value = payload.materialItemId
  nachbuchungMaterialLabel.value = payload.materialLabel
  nachbuchungPackSize.value = payload.packSize ?? null
  nachbuchungPackUnit.value = payload.packUnit ?? null
  nachbuchungPackStage.value =
    payload.packStage?.trim() || currentReplenishmentPackStage.value || null
  nachbuchungOpen.value = true
}

function onNachbuchungModalClose() {
  nachbuchungOpen.value = false
  nachbuchungPackSize.value = null
  nachbuchungPackUnit.value = null
  nachbuchungPackStage.value = null
}

function onConsumptionModalRequestNachbuchung() {
  const p = consumptionModalPreset.value
  if (!p) return
  consumptionModalOpen.value = false
  openNachbuchungModal({
    materialItemId: p.materialItemId,
    materialLabel: p.linkedContainerLabel?.trim()
      ? `${p.linkedContainerLabel.trim()} — ${p.materialName}`
      : p.materialName,
    packSize: p.packSize ?? null,
    packUnit: p.packUnit ?? null,
  })
}

async function onNachbuchungModalSuccess() {
  nachbuchungOpen.value = false
  nachbuchungPackSize.value = null
  nachbuchungPackUnit.value = null
  nachbuchungPackStage.value = null
  consumablesReloadToken.value += 1
  costsReloadToken.value += 1
  packListReloadToken.value += 1
  toast.success(t('activities.detail.toastNachbuchungAdded'))
  try {
    await loadItems()
    await refreshActivityTotalsFromApi()
  } catch {
    /* ignore */
  }
}

function onConsumableBooked() {
  issuesReloadToken.value += 1
  costsReloadToken.value += 1
}

async function onConsumptionModalSuccess() {
  skipNextConsumptionModalCloseCancel.value = true
  const wasEdit = Boolean((consumptionModalPreset.value?.editIssueId ?? '').trim())
  issuesReloadToken.value += 1
  consumablesReloadToken.value += 1
  costsReloadToken.value += 1
  packListReloadToken.value += 1
  toast.success(
    wasEdit
      ? t('activities.detail.toastConsumptionUpdated')
      : t('activities.detail.toastConsumptionBooked'),
  )
  try {
    await loadItems()
    await refreshActivityTotalsFromApi()
  } catch {
    /* ignore */
  }
}

function onPackIssueWizard(
  payload:
    | {
        materialItemId: string
        issueType: 'loss' | 'repair'
        quantity?: number
      }
    | {
        items: Array<{
          materialItemId: string
          issueType: 'loss' | 'repair'
          quantity?: number
        }>
      },
) {
  if ('items' in payload && payload.items.length > 0) {
    const [first, ...rest] = payload.items
    damageReportQueue.value = rest.map((item) => ({
      materialItemId: item.materialItemId,
      issueType: item.issueType,
      quantity: item.quantity,
    }))
    openDamageReport({
      materialItemId: first.materialItemId,
      issueType: first.issueType,
      quantity: first.quantity,
    })
    return
  }
  if ('materialItemId' in payload) {
    damageReportQueue.value = []
    openDamageReport({
      materialItemId: payload.materialItemId,
      issueType: payload.issueType,
      quantity: payload.quantity,
    })
  }
}

const hasLineTotals = computed(() => activityItems.value.some((i) => i.line_total != null))

const hasDraftQtyChanges = computed(() =>
  activityItems.value.some((r) => draftQty(r) !== r.quantity),
)

function draftQty(row: ActivityItemRow): number {
  const v = draftQuantities.value[row.id]
  return v !== undefined ? v : row.quantity
}

function initDraftQuantitiesFromItems() {
  const qty: Record<string, number> = {}
  const pack: Record<string, 'together' | 'loose'> = {}
  for (const r of activityItems.value) {
    qty[r.id] = r.quantity
    if (r.material_type === 'virtual_combo') {
      pack[r.id] = r.config_snapshot?.pack_mode === 'together' ? 'together' : 'loose'
    }
  }
  draftQuantities.value = qty
  draftPackModes.value = pack
}

function onDraftLinesTableUpdate(lines: ActivityMaterialLine[]) {
  const nextQty = { ...draftQuantities.value }
  const nextPack = { ...draftPackModes.value }
  let qtyChanged = false
  for (const line of lines) {
    if (!line.activity_item_id || line.activity_item_id.startsWith('__loose_child_')) continue
    const prevQty = nextQty[line.activity_item_id]
    if (prevQty !== line.quantity) qtyChanged = true
    nextQty[line.activity_item_id] = line.quantity
    if (line.material_type === 'virtual_combo') {
      const mode = line.config_snapshot?.pack_mode ?? line.pack_mode
      if (mode === 'together' || mode === 'loose') {
        for (const r of activityItems.value) {
          if (r.material_type !== 'virtual_combo' || r.parent_activity_item_id) continue
          if (r.material_item_id !== line.material_item_id || !r.id) continue
          nextPack[r.id] = mode
        }
      }
    }
    if (
      line.material_type !== 'virtual_combo' &&
      line.material_type !== 'physical_combo' &&
      !line.parent_activity_item_id
    ) {
      for (const r of activityItems.value) {
        if (!isMergeableStandaloneTopLevelItem(r)) continue
        if (r.material_item_id !== line.material_item_id) continue
        if (r.id === line.activity_item_id) continue
        nextQty[r.id] = 0
      }
    }
  }
  draftQuantities.value = nextQty
  draftPackModes.value = nextPack
  if (qtyChanged) scheduleDraftQuantitiesAutoSave()
}

function applySavedQuantitiesFromDraft() {
  activityItems.value = activityItems.value.map((r) => ({
    ...r,
    quantity: draftQty(r),
  }))
  initDraftQuantitiesFromItems()
}

let draftQtyAutoSaveTimer: ReturnType<typeof setTimeout> | null = null

function scheduleDraftQuantitiesAutoSave() {
  if (syncingQuantities.value) return
  if (draftQtyAutoSaveTimer) clearTimeout(draftQtyAutoSaveTimer)
  draftQtyAutoSaveTimer = setTimeout(() => {
    draftQtyAutoSaveTimer = null
    if (hasDraftQtyChanges.value && !syncingQuantities.value) {
      void saveDraftQuantities({ successToastKey: null, soft: true })
    }
  }, 900)
}

onBeforeUnmount(() => {
  if (draftQtyAutoSaveTimer) clearTimeout(draftQtyAutoSaveTimer)
})

function onDraftTableRemoveLine({ line }: { line: ActivityMaterialLine; index: number }) {
  const row = activityItems.value.find((r) => r.id === line.activity_item_id)
  if (row) void onRemoveDraftItem(row)
}

async function saveDraftQuantities(options?: { successToastKey?: string | null; soft?: boolean }) {
  const a = activity.value
  if (!a) return
  if (!canEditActivityMaterialLines.value) return
  if (syncingQuantities.value) return
  syncingQuantities.value = true
  try {
    await syncActivityItems(props.activityId, { items: buildDraftSyncItems() })
    const toastKey = options?.successToastKey
    if (toastKey !== null) {
      toast.success(t(toastKey ?? 'activities.detail.toastQtySaved'))
    }
    if (options?.soft) {
      applySavedQuantitiesFromDraft()
      await refreshActivityTotalsFromApi()
    } else {
      await loadItems({ skipAutoConsolidate: true })
      await refreshActivityTotalsFromApi()
    }
    if (
      (STATUSES_AT_OR_AFTER_PACKING as readonly string[]).includes(a.status || '')
    ) {
      packListReloadToken.value += 1
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.detail.toastQtySaveFailed'))
    await loadItems({ skipAutoConsolidate: true })
  } finally {
    syncingQuantities.value = false
  }
}

async function onVirtualComboPackModeChange(payload: {
  line: ActivityMaterialLine
  mode: 'together' | 'loose'
}) {
  if (!virtualComboPackModeEditable.value) return
  const mid = payload.line.material_item_id
  const nextPack = { ...draftPackModes.value }
  for (const r of activityItems.value) {
    if (r.material_type !== 'virtual_combo' || r.parent_activity_item_id) continue
    if (r.material_item_id !== mid) continue
    if (r.id) nextPack[r.id] = payload.mode
  }
  draftPackModes.value = nextPack
  await saveDraftQuantities({ successToastKey: 'activities.detail.toastPackModeSaved' })
}

type ReconfigureVirtualComboState = {
  activityItemId: string
  materialItemId: string
  materialName: string
  quantity: number
  selectedOptionIds: string[]
  packMode: 'together' | 'loose'
  selfProvidedAcknowledged: boolean
}

const reconfigureVirtualComboState = ref<ReconfigureVirtualComboState | null>(null)

function onReconfigureVirtualCombo(payload: { line: ActivityMaterialLine; index: number }) {
  if (!virtualComboPackModeEditable.value) return
  const line = payload.line
  if (line.material_type !== 'virtual_combo' || !line.activity_item_id) return
  const snap = line.config_snapshot
  reconfigureVirtualComboState.value = {
    activityItemId: line.activity_item_id,
    materialItemId: line.material_item_id,
    materialName: line.material_name,
    quantity: Math.max(1, line.quantity),
    selectedOptionIds: [...(snap?.selected_option_ids ?? [])],
    packMode: snap?.pack_mode === 'together' ? 'together' : 'loose',
    selfProvidedAcknowledged: Boolean(snap?.self_provided_acknowledged),
  }
}

async function onReconfigureVirtualComboConfirm(payload: {
  selectedOptionIds: string[]
  quantity: number
  packMode: 'together' | 'loose'
  selfProvidedAcknowledged: boolean
}) {
  const state = reconfigureVirtualComboState.value
  reconfigureVirtualComboState.value = null
  if (!state || !virtualComboPackModeEditable.value) return

  const itemId = state.activityItemId
  activityItems.value = activityItems.value.map((r) => {
    if (r.id !== itemId) return r
    const qty = Math.max(1, payload.quantity)
    const prevSnap = r.config_snapshot
    return {
      ...r,
      quantity: qty,
      config_snapshot: {
        combo_qty: qty,
        selected_option_ids: [...payload.selectedOptionIds],
        pack_mode: payload.packMode,
        self_provided_acknowledged: payload.selfProvidedAcknowledged,
        resolved_components: prevSnap?.resolved_components,
        self_provided: prevSnap?.self_provided,
        self_provided_acknowledged_at: prevSnap?.self_provided_acknowledged_at,
        self_provided_acknowledged_by_user_id: prevSnap?.self_provided_acknowledged_by_user_id,
      },
    }
  })
  draftQuantities.value = {
    ...draftQuantities.value,
    [itemId]: Math.max(1, payload.quantity),
  }
  draftPackModes.value = {
    ...draftPackModes.value,
    [itemId]: payload.packMode,
  }
  await saveDraftQuantities({ successToastKey: 'activities.detail.toastVirtualComboReconfigured' })
}

async function onDraftOverviewSaved() {
  await reloadActivityDetailSoft()
}

async function onJsActivityUpdated() {
  await reloadActivityDetailSoft()
}

/** Nach Auto-Save in der Übersicht: Daten nachladen ohne Vollseiten-Spinner */
async function reloadActivityDetailSoft(): Promise<void> {
  if (!activity.value) return
  try {
    const prevName = activity.value.name
    const [d, tr] = await Promise.all([
      getActivity(props.activityId, props.departmentId),
      getActivityTransitions(props.activityId),
    ])
    applyActivityDetailPatch(d)
    transitions.value = tr.transitions || []
    completionBlockers.value = tr.completion_blockers ?? null
    if ((d.name ?? '') !== (prevName ?? '')) {
      pageHeadStore.setDynamic(
        t('activities.detail.pageTitleSuffix', { name: d.name ?? prevName }),
        `${activityTypeLabelDetail(d.type || '')} · ${activityStatusLabelDetail(d.status || '')}`,
      )
    }
  } catch {
    /* stiller Refresh — kein Spinner, Fehler ignorieren */
  }
}

function activityTypeLabelDetail(type: string): string {
  const key = `activities.types.${type}` as const
  return te(key) ? t(key) : type
}

function activityStatusLabelDetail(status: string): string {
  const act = activity.value
  if (act && status === 'at_event' && packWorkflowProfile.value !== 'logistics') {
    const quickKey = 'activities.status.at_event_quick' as const
    if (te(quickKey)) return t(quickKey)
  }
  const key = `activities.status.${activityStatusI18nKey(status)}` as const
  return te(key) ? t(key) : status
}

function formatDateTime(iso: string): string {
  if (!iso) return t('activities.wizard.form.summaryEmpty')
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  const locTag = String(locale.value ?? '').startsWith('de') ? 'de-CH' : 'en-CH'
  return d.toLocaleString(locTag, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatMoney(v: string | number): string {
  const n = typeof v === 'string' ? parseFloat(v) : v
  if (Number.isNaN(n)) return String(v)
  return n.toFixed(2)
}

function inviteStatusLabel(status?: string): string {
  if (status === 'accepted') return t('activities.detail.inviteAccepted')
  if (status === 'rejected') return t('activities.detail.inviteRejected')
  return t('activities.detail.invitePending')
}

function inviteStatusClass(status?: string): string {
  if (status === 'accepted') return 'accepted'
  if (status === 'rejected') return 'rejected'
  return 'pending'
}

async function generateActivityPublicCode() {
  if (!props.activityId || isGeneratingActivityPublicCode.value) return
  isGeneratingActivityPublicCode.value = true
  try {
    activity.value = await ensureActivityPublicCode(props.activityId)
    toast.success(t('activities.detail.toastQrCreated'))
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('activities.detail.errQrCreate'))
  } finally {
    isGeneratingActivityPublicCode.value = false
  }
}

function openActivityQrActionModal() {
  showActivityQrActionModal.value = true
}

function closeActivityQrActionModal() {
  showActivityQrActionModal.value = false
}

async function handleActivityQrAddToPrintCart() {
  const act = activity.value
  const url = activityPublicUrl.value
  if (!act?.id || !url) {
    toast.info(t('activities.detail.toastNoPublicLink'))
    return
  }
  try {
    const result = await addPrintCartItem({
      department_id: props.departmentId,
      entity_type: 'activity',
      entity_id: act.id,
      label: act.name || t('activities.detail.fallbackTitle'),
      public_code: act.public_code || null,
      public_url: url,
    })
    toast.success(
      result.created ? t('activities.detail.toastPrintCartAdded') : t('activities.detail.toastPrintCartAlready')
    )
    closeActivityQrActionModal()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('activities.detail.errPrintCartAdd'))
  }
}

async function handleActivityQrPrint() {
  const url = activityPublicUrl.value
  const act = activity.value
  if (!url || !act) {
    toast.info(t('activities.detail.toastNoPublicLink'))
    return
  }
  const qrDataUrl = await QRCode.toDataURL(url, { width: 300, margin: 1 })
  printHtmlDocument(`<!doctype html>
<html><head><meta charset="utf-8" /><title>${act.name}</title>
<style>body{font-family:Arial,sans-serif;text-align:center;padding:24px}img{width:280px;height:280px}.title{margin-top:12px;font-weight:700}.code{font-family:monospace;color:#64748b;margin-top:6px}</style>
</head><body>
<img src="${qrDataUrl}" alt="QR" />
<div class="title">${act.name}</div>
<div class="code">${act.public_code || ''}</div>
</body></html>`)
  closeActivityQrActionModal()
}

function handleClose() {
  // Zurück zur Liste: Tab im Header bleibt offen (nur × im Header entfernt Chip)
  void router.push(`/${props.departmentId}/activities`)
}

async function loadActivityIssues() {
  try {
    activityIssues.value = await getActivityIssues(props.activityId)
  } catch {
    activityIssues.value = []
  } finally {
    issuesDataReady.value = true
  }
}

async function reload() {
  loadError.value = null
  isLoading.value = true
  activity.value = null
  transitions.value = []
  completionBlockers.value = null
  activityItems.value = []
  activityIssues.value = []
  issuesDataReady.value = false
  resetItemsTabLoad()
  draftQuantities.value = {}
  draftPackModes.value = {}
  try {
    const [detail, tr, items] = await Promise.all([
      getActivity(props.activityId, props.departmentId),
      getActivityTransitions(props.activityId),
      getActivityItems(props.activityId).catch(() => [] as ActivityItemRow[]),
      loadGroupsForDepartment(props.departmentId),
      loadActivityIssues(),
    ])
    activity.value = detail
    transitions.value = tr.transitions || []
    completionBlockers.value = tr.completion_blockers ?? null
    activityItems.value = items
    markItemsHydrated()
    if (activeTab.value === 'material') {
      initDraftQuantitiesFromItems()
    }
    pageHeadStore.setDynamic(
      t('activities.detail.pageTitleSuffix', { name: detail.name }),
      `${activityTypeLabelDetail(detail.type || '')} · ${activityStatusLabelDetail(detail.status || '')}`,
    )
    if (activeTab.value === 'material') {
      void loadItems()
    }
    void loadPackItemsSnapshot()
  } catch (err: unknown) {
    const e = err as { response?: { status?: number; data?: { error?: string } }; message?: string }
    const msg =
      e.response?.status === 404
        ? t('activities.detail.loadNotFound')
        : e.response?.data?.error || e.message || t('activities.detail.loadFailed')
    loadError.value = msg
    pageHeadStore.setDynamic(t('activities.detail.pageErrorTitle'), msg)
  } finally {
    isLoading.value = false
  }
}

async function loadPackItemsSnapshot(): Promise<void> {
  if (!showPacksTab.value) {
    packItemsSnapshot.value = []
    return
  }
  try {
    packItemsSnapshot.value = await getPackItems(props.activityId)
  } catch {
    packItemsSnapshot.value = []
  }
}

async function loadItems(opts?: { forceFull?: boolean; skipAutoConsolidate?: boolean }) {
  await withItemsLoad(async () => {
    try {
      activityItems.value = await getActivityItems(props.activityId)
      initDraftQuantitiesFromItems()
      if (
        !opts?.skipAutoConsolidate &&
        canEditActivityMaterialLines.value &&
        (hasDuplicateMergeableStandaloneItems(activityItems.value) ||
          hasDuplicateVirtualComboParents(activityItems.value))
      ) {
        await consolidateDuplicateActivityItems()
      }
      if (!opts?.skipAutoConsolidate && canEditActivityMaterialLines.value) {
        if (splitStandaloneForVirtualCombosInDraft()) {
          await saveDraftQuantities({ successToastKey: null })
        }
      }
    } catch {
      activityItems.value = []
      draftQuantities.value = {}
      draftPackModes.value = {}
      toast.error(t('activities.detail.toastItemsLoadFailed'))
    }
  }, opts)
}

/** Doppelte eigenständige Zeilen und virtuelle Kombos (gleiches Material) in der DB zusammenführen. */
async function consolidateDuplicateActivityItems() {
  if (
    syncingQuantities.value ||
    !canEditActivityMaterialLines.value ||
    (!hasDuplicateMergeableStandaloneItems(activityItems.value) &&
      !hasDuplicateVirtualComboParents(activityItems.value))
  ) {
    return
  }
  syncingQuantities.value = true
  try {
    splitStandaloneForVirtualCombosInDraft()
    await syncActivityItems(props.activityId, { items: buildDraftSyncItems() })
    activityItems.value = await getActivityItems(props.activityId)
    initDraftQuantitiesFromItems()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(
      e.response?.data?.error ||
        e.message ||
        t('activities.detail.toastConsolidateFailed'),
    )
  } finally {
    syncingQuantities.value = false
  }
}

/** Packliste: Kiste als Behälter gewählt → Backend ergänzt ActivityItem; Materialliste & Summen aktualisieren */
async function onPackListActivityItemsChanged() {
  await loadItems()
  await refreshActivityTotalsFromApi()
  if (activity.value?.status === 'returned') {
    await refreshCompletionBlockers()
  }
}

async function onDamageReportSuccess() {
  if (damageReportQueue.value.length > 0) {
    issuesReloadToken.value += 1
    toast.success(t('activities.detail.toastIssueRecorded'))
    await openNextDamageReportFromQueue()
    return
  }
  damageReportOpen.value = false
  damageReportPresets.value = {}
  issuesReloadToken.value += 1
  costsReloadToken.value += 1
  headerNotificationsStore.requestRefresh()
  toast.success(t('activities.detail.toastIssueRecorded'))
  try {
    await Promise.all([
      loadItems(),
      loadActivityIssues(),
      refreshActivityTotalsFromApi(),
      refreshCompletionBlockers(),
    ])
  } catch {
    /* loadItems / refresh bereits mit Toast */
  }
}

async function refreshActivityTotalsFromApi() {
  const d = await getActivity(props.activityId, props.departmentId)
  if (!activity.value) return
  applyActivityDetailPatch(d)
}

async function refreshCompletionBlockers() {
  const status = activity.value?.status ?? ''
  if (status !== 'returned' && status !== 'storing') {
    completionBlockers.value = null
    return
  }
  try {
    const tr = await getActivityTransitions(props.activityId)
    completionBlockers.value = tr.completion_blockers ?? null
  } catch {
    /* optional */
  }
}

const ACTIVITY_PACK_LIVE_SYNC_STATUSES = new Set(['packing', 'packed', 'at_event', 'returned'])

function applyActivityDetailPatch(d: ActivityDetail): void {
  if (!activity.value) return
  Object.assign(activity.value, d)
}

function isDetailLivePollBusy(): boolean {
  if (isLoading.value || isTransitioning.value) return true
  if (syncingQuantities.value || addingDraftMaterial.value) return true
  if (removingItemId.value) return true
  if (hasDraftQtyChanges.value) return true
  const draftForm = draftOverviewFormRef.value
  if (unref(draftForm?.hasUnsavedChanges)) return true
  if (unref(draftForm?.isSaving)) return true
  return false
}

async function refreshActivityDetailSilent(): Promise<void> {
  if (!activity.value || loadError.value) return
  if (isDetailLivePollBusy()) return
  try {
    const prevStatus = activity.value.status
    const prevUpdated = activity.value.updated_at
    const prevItemCount = activity.value.item_count
    const prevName = activity.value.name

    const [d, tr] = await Promise.all([
      getActivity(props.activityId, props.departmentId),
      getActivityTransitions(props.activityId),
    ])

    applyActivityDetailPatch(d)
    transitions.value = tr.transitions || []
    completionBlockers.value = tr.completion_blockers ?? null

    const statusChanged = d.status !== prevStatus
    const metaChanged =
      statusChanged ||
      d.updated_at !== prevUpdated ||
      d.item_count !== prevItemCount ||
      (d.name ?? '') !== (prevName ?? '')

    if (metaChanged) {
      pageHeadStore.setDynamic(
        t('activities.detail.pageTitleSuffix', { name: d.name ?? prevName }),
        `${activityTypeLabelDetail(d.type || '')} · ${activityStatusLabelDetail(d.status || '')}`,
      )
    }

    const itemsMayHaveChanged =
      d.item_count !== prevItemCount ||
      (activeTab.value === 'material' && d.updated_at !== prevUpdated)

    if (itemsMayHaveChanged) {
      const items = await getActivityItems(props.activityId).catch(() => null)
      if (items) {
        activityItems.value = items
        if (activeTab.value === 'material' && showMaterialLookup.value) {
          initDraftQuantitiesFromItems()
        }
      }
    }

    if (
      statusChanged &&
      (ACTIVITY_PACK_LIVE_SYNC_STATUSES.has(d.status || '') ||
        ACTIVITY_PACK_LIVE_SYNC_STATUSES.has(prevStatus || ''))
    ) {
      packListReloadToken.value += 1
    }
  } catch {
    /* Poll-Fehler ignorieren */
  }
}

async function onDraftAddQuantity(payload: {
  material: { materialItemId: string }
  quantity: number
  selectedOptionIds?: string[]
  packMode?: 'together' | 'loose'
  selfProvidedAcknowledged?: boolean
  combineParts?: Array<{ materialItemId: string; reduceBy: number }>
}) {
  const mid = payload.material?.materialItemId
  const a = activity.value
  if (!mid || !a) return
  if (!canEditActivityMaterialLines.value && !a.can_add_forgotten_material) return
  addingDraftMaterial.value = true
  try {
    await addActivityItem(props.activityId, {
      material_item_id: mid,
      quantity: payload.quantity,
      ...(payload.selectedOptionIds ? { selected_option_ids: payload.selectedOptionIds } : {}),
      ...(payload.packMode ? { pack_mode: payload.packMode } : {}),
      ...(payload.selfProvidedAcknowledged ? { self_provided_acknowledged: true } : {}),
    })
    await loadItems({ skipAutoConsolidate: true })
    await consolidateDuplicateActivityItems()
    if (payload.combineParts && payload.combineParts.length > 0) {
      await applyCombineReductions(payload.combineParts)
    }
    toast.success(t('activities.detail.toastMaterialAdded'))
    await refreshActivityTotalsFromApi()
    if (a.status === 'packing' || a.status === 'packed' || a.status === 'transport_out' || a.status === 'at_event' || a.status === 'transport_back') {
      packListReloadToken.value += 1
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.detail.toastMaterialAddFailed'))
  } finally {
    addingDraftMaterial.value = false
  }
}

/**
 * „Kombinieren?": nur eigenständige Einzelpositionen um den freigegebenen Bedarf reduzieren
 * (Extra bleibt, z. B. 50 − 39 = 11). Kein erneutes splitStandalone — das wäre Doppelabzug.
 */
async function applyCombineReductions(
  parts: Array<{ materialItemId: string; reduceBy: number }>,
): Promise<void> {
  const reduceMap = new Map<string, number>()
  for (const p of parts) {
    if (p.reduceBy > 0) reduceMap.set(p.materialItemId, (reduceMap.get(p.materialItemId) ?? 0) + p.reduceBy)
  }
  if (reduceMap.size === 0) return

  let changed = false
  const nextQty = { ...draftQuantities.value }
  for (const [materialId, reduceTotal] of reduceMap) {
    let remaining = reduceTotal
    for (const row of activityItems.value) {
      if (!isMergeableStandaloneTopLevelItem(row)) continue
      if (row.material_item_id !== materialId) continue
      const current = draftQty(row)
      const take = Math.min(remaining, current)
      const reduced = current - take
      if (reduced !== current) {
        nextQty[row.id] = reduced
        changed = true
      }
      remaining -= take
      if (remaining <= 0) break
    }
  }
  if (!changed) return
  draftQuantities.value = nextQty
  await saveDraftQuantities({ successToastKey: null })
}

const PACK_PIPELINE_ITEM_STATUSES = ['packed', 'at_event', 'returned'] as const

function activityItemHasPackProgress(row: ActivityItemRow): boolean {
  const st = (row.status ?? '').trim()
  return (PACK_PIPELINE_ITEM_STATUSES as readonly string[]).includes(st)
}

function virtualComboChildHasPackProgress(parentId: string): boolean {
  return activityItems.value.some(
    (r) => r.parent_activity_item_id === parentId && activityItemHasPackProgress(r),
  )
}

async function onRemoveDraftItem(row: ActivityItemRow) {
  const a = activity.value
  if (!a) return
  if (!canEditActivityMaterialLines.value) return
  if (row.parent_activity_item_id) return

  if (row.material_type === 'virtual_combo') {
    const okCombo = await confirmDialog({
      title: t('activities.detail.confirmRemoveVirtualComboTitle'),
      message: t('activities.detail.confirmRemoveVirtualComboMessage', { name: row.material_name }),
      confirmText: t('common.remove'),
      cancelText: t('common.cancel'),
      variant: 'warning',
    })
    if (!okCombo) return
  }

  const status = a.status || ''
  const hasPackProgress =
    activityItemHasPackProgress(row) ||
    (row.material_type === 'virtual_combo' && virtualComboChildHasPackProgress(row.id))
  if (
    (STATUSES_AT_OR_AFTER_PACKING as readonly string[]).includes(status) &&
    hasPackProgress
  ) {
    const ok = await confirmDialog({
      title: t('activities.detail.confirmRemovePackedTitle'),
      message: t('activities.detail.confirmRemovePackedMessage', { name: row.material_name }),
      confirmText: t('common.remove'),
      cancelText: t('common.cancel'),
      variant: 'warning',
    })
    if (!ok) return
  }

  removingItemId.value = row.id
  try {
    if (row.material_type === 'virtual_combo') {
      const comboRows = activityItems.value.filter(
        (r) =>
          r.material_type === 'virtual_combo' &&
          !r.parent_activity_item_id &&
          r.material_item_id === row.material_item_id,
      )
      const parentIds = new Set(comboRows.map((r) => r.id))
      const itemsWithoutCombo = activityItems.value.filter(
        (r) =>
          !(
            r.material_type === 'virtual_combo' &&
            !r.parent_activity_item_id &&
            r.material_item_id === row.material_item_id
          ) && !(r.parent_activity_item_id && parentIds.has(r.parent_activity_item_id)),
      )
      await syncActivityItems(props.activityId, { items: buildDraftSyncItems(itemsWithoutCombo) })
    } else if (isMergeableStandaloneTopLevelItem(row)) {
      const items = buildDraftSyncItems().filter((i) => i.material_item_id !== row.material_item_id)
      await syncActivityItems(props.activityId, { items })
    } else {
      await removeActivityItem(props.activityId, row.id)
    }
    toast.success(t('activities.detail.toastPositionRemoved'))
    await loadItems({ skipAutoConsolidate: true })
    await refreshActivityTotalsFromApi()
    if (status === 'packing' || status === 'packed') {
      packListReloadToken.value += 1
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.detail.toastPositionRemoveFailed'))
  } finally {
    removingItemId.value = null
  }
}

async function onPackListWorkflowNext(transition: ActivityTransitionRow) {
  await onTransition(transition, { skipPackConfirm: true })
}

function onActivityVehiclesChanged(): void {
  vehiclesReloadToken.value += 1
}

async function onJourneyStatusChanged(): Promise<void> {
  try {
    const detail = await getActivity(props.activityId, props.departmentId)
    activity.value = detail
    pageHeadStore.setDynamic(
      t('activities.detail.pageTitleSuffix', { name: detail.name }),
      `${activityTypeLabelDetail(detail.type || '')} · ${activityStatusLabelDetail(detail.status || '')}`,
    )
    headerNotificationsStore.requestRefresh()
    const trNext = await getActivityTransitions(props.activityId)
    transitions.value = trNext.transitions || []
    completionBlockers.value = trNext.completion_blockers ?? null
    packListReloadToken.value += 1
    if (detail.status === 'storing' && !useLegacyPackUi.value && canManageMaterials.value) {
      activeTab.value = 'packs'
      mergeActivityQuery({ tab: 'packs', packStep: 'store' })
    }
  } catch {
    /* Journey hat bereits lokal aktualisiert — Kopfzeile best-effort nachziehen */
  }
}

async function onTransition(
  transition: ActivityTransitionRow,
  options?: { skipPackConfirm?: boolean },
) {
  if (!transition.allowed || isTransitioning.value) return

  if (
    transition.status === 'packed' &&
    activity.value?.status === 'packing' &&
    !useLegacyPackUi.value
  ) {
    const journey = materialJourneyRef.value
    if (!journey?.showPackCompletePanel) return
    await journey.markPacked()
    return
  }

  if (
    !options?.skipPackConfirm &&
    transitionNeedsPackListConfirmation(transition)
  ) {
    const packTab = await ensurePackListTabForTransition()
    if (!packTab) {
      toast.error(t('activities.detail.toastPackListRequiredForTransition'))
      return
    }
    if (!(await packTab.confirmBeforeWorkflowTransition(transition))) return
  }

  isTransitioning.value = true
  try {
    await patchActivityStatus(props.activityId, { status: transition.status })
    const detail = await getActivity(props.activityId, props.departmentId)
    activity.value = detail
    pageHeadStore.setDynamic(
      t('activities.detail.pageTitleSuffix', { name: detail.name }),
      `${activityTypeLabelDetail(detail.type || '')} · ${activityStatusLabelDetail(detail.status || '')}`,
    )
    toast.success(t('activities.detail.toastStatusChanged', { status: activityStatusLabelDetail(detail.status || '') }))
    headerNotificationsStore.requestRefresh()
    const trNext = await getActivityTransitions(props.activityId)
    transitions.value = trNext.transitions || []
    completionBlockers.value = trNext.completion_blockers ?? null
    if (detail.status === 'packing') {
      activeTab.value = 'packs'
      mergeActivityQuery({ tab: 'packs' })
    }
    if (detail.status === 'storing' && !useLegacyPackUi.value && canManageMaterials.value) {
      activeTab.value = 'packs'
      mergeActivityQuery({ tab: 'packs', packStep: 'store' })
    }
    if (detail.status === 'completed' && activeTab.value === 'packs') {
      packListReloadToken.value += 1
    }
    if (activeTab.value === 'material') {
      await loadItems()
    }
  } catch (err: unknown) {
    const e = err as {
      response?: {
        data?: { error?: string; code?: string; blockers?: ActivityCompletionBlockers }
      }
      message?: string
    }
    if (e.response?.data?.code === 'activity_completion_blocked' && e.response.data.blockers) {
      completionBlockers.value = e.response.data.blockers
      toast.error(e.response?.data?.error || t('activities.detail.toastStatusChangeFailed'))
    } else {
      toast.error(e.response?.data?.error || e.message || t('activities.detail.toastStatusChangeFailed'))
    }
  } finally {
    isTransitioning.value = false
  }
}

function onCompletionGoTab(tab: 'packs' | 'issues' | 'costs') {
  activeTab.value = tab
  const updates: Record<string, string | undefined> = { tab }
  if (tab === 'packs' && activity.value && !useLegacyPackUi.value) {
    const s = activity.value.status ?? ''
    if (s === 'storing') updates.packStep = 'store'
    else if (s === 'returned') updates.packStep = canManageMaterials.value ? 'store' : 'return'
    else if (s === 'at_event') updates.packStep = 'return'
    else if (s === 'packed' || s === 'transport_out') updates.packStep = 'issue'
    else if (s === 'packing') updates.packStep = 'pack'
  }
  mergeActivityQuery(updates)
}

const costsReleaseInProgress = ref(false)
const costsReleaseConfirmOpen = ref(false)
const costsReleaseConfirmTotal = ref<number | null>(null)
const costsPreviewTotal = ref<number | null>(null)

const costsReleaseConfirmChargeLabel = computed(() => {
  const a = activity.value
  if (!a) return t('activities.costs.chargeUnknown')
  const target = resolveActivityPrimaryChargeTarget({
    activityType: a.type ?? 'activity',
    groupName: a.group_name,
    externalCustomerLabel: a.external_customer_label,
    activityName: a.name,
  })
  if (target.kind === 'group' && target.label) {
    return t('activities.costs.chargeGroup', { name: target.label })
  }
  if (target.kind === 'external_customer' && target.label) {
    return t('activities.costs.chargeExternal', { name: target.label })
  }
  return t('activities.costs.chargeUnknown')
})

const costsReleaseConfirmTotalLabel = computed(() => {
  const n = costsReleaseConfirmTotal.value
  if (n == null || Number.isNaN(n)) return '—'
  return n.toFixed(2)
})

function onCostsPreview(payload: { total: number }): void {
  costsPreviewTotal.value = payload.total
  if (costsReleaseConfirmOpen.value && costsReleaseConfirmTotal.value == null) {
    costsReleaseConfirmTotal.value = payload.total
  }
}

function onCollectionNoteUpdated(payload: {
  collection_note: 'cash' | 'invoice' | null
  collection_note_amount: number | null
  collection_note_at: string | null
}): void {
  if (!activity.value) return
  activity.value.collection_note = payload.collection_note
  activity.value.collection_note_amount = payload.collection_note_amount
  activity.value.collection_note_at = payload.collection_note_at
}

/** Bestätigung wie beim Einlagern: Übersicht nochmals sehen, dann freigeben. */
function openCostsReleaseConfirm(payload?: { total?: number }): void {
  if (!activity.value || costsReleaseInProgress.value) return
  if (activity.value.costs_released) return
  if (!canReleaseCosts.value) return
  const total = payload?.total ?? costsPreviewTotal.value
  costsReleaseConfirmTotal.value =
    total != null && !Number.isNaN(total) ? total : null
  // Aus der Checkliste: Kosten-Tab öffnen, damit die Übersicht im Hintergrund sichtbar ist
  if (activeTab.value !== 'costs') {
    activeTab.value = 'costs'
    mergeActivityQuery({ tab: 'costs' })
    costsReloadToken.value += 1
  }
  costsReleaseConfirmOpen.value = true
}

async function confirmCostsRelease(): Promise<void> {
  if (!activity.value || costsReleaseInProgress.value) return
  if (activity.value.costs_released) {
    costsReleaseConfirmOpen.value = false
    return
  }
  costsReleaseInProgress.value = true
  try {
    const updated = await patchActivity(props.activityId, { costs_released: true })
    Object.assign(activity.value, updated)
    activity.value.costs_released = true
    if (completionBlockers.value) {
      completionBlockers.value = {
        ...completionBlockers.value,
        costs_released: true,
      }
    }
    await refreshCompletionBlockers()
    costsReloadToken.value += 1
    costsReleaseConfirmOpen.value = false
    toast.success(t('activities.costs.releaseDone'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.detail.toastStatusChangeFailed'))
  } finally {
    costsReleaseInProgress.value = false
  }
}

async function onCancelActivity() {
  if (!cancelTransition.value) return
  const status = activity.value?.status
  const needsPackCancelWarning =
    canManageActivityCancel.value &&
    !!status &&
    (STATUSES_CANCEL_PACK_WARNING as readonly string[]).includes(status)
  const ok = await confirmDialog({
    title: t(
      needsPackCancelWarning
        ? 'activities.detail.confirmCancelPackTitle'
        : 'activities.detail.confirmCancelTitle',
    ),
    message: t(
      needsPackCancelWarning
        ? 'activities.detail.confirmCancelPackMessage'
        : 'activities.detail.confirmCancelMessage',
    ),
    confirmText: t('activities.detail.confirmCancelAction'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  await onTransition(cancelTransition.value)
}

watch(
  () => props.activityId,
  () => {
    void reload()
  },
  { immediate: true },
)

/** Tab aus ?tab=; ohne Query: v4.01-Default (Packliste nur ab packing … returned). */
watch(
  () => [props.activityId, route.query.tab, tabIds.value.join(','), activity.value?.status] as const,
  () => {
    const raw = route.query.tab
    const rawStr = Array.isArray(raw) ? String(raw[0] ?? '') : typeof raw === 'string' ? raw : ''
    const hasTabInQuery = rawStr.trim().length > 0
    const normalized = normalizeActivityTabQuery(raw)
    const resolved =
      normalized ??
      (!hasTabInQuery && activity.value ? defaultTabWhenNoQuery(activity.value.status) : 'overview')
    if (activeTab.value !== resolved) {
      activeTab.value = resolved
    }
    if (hasTabInQuery && normalizeActivityTabQuery(raw) === null) {
      mergeActivityQuery({ tab: undefined })
    }
  },
  { immediate: true },
)

watch(activeTab, (newTab) => {
  const fromQuery = normalizeActivityTabQuery(route.query.tab)
  if (fromQuery !== newTab) {
    mergeActivityQuery({ tab: newTab })
  }
})

watch(
  () => {
    const name = activity.value?.name
    if (name && String(name).trim()) return String(name).trim()
    const no = activity.value?.no
    if (no != null && String(no).trim()) return String(no).trim()
    return ''
  },
  (label) => {
    if (!label) return
    detailTabsStore.addOrUpdateTab({
      id: props.activityId,
      type: 'activity',
      label,
      departmentId: props.departmentId,
      path: `/${props.departmentId}/activities/${props.activityId}`,
    })
  }
)

onBeforeUnmount(() => {
  pageHeadStore.clearDynamic()
})

useBackgroundPoll({
  intervalMs: ACTIVITY_DETAIL_LIVE_POLL_MS,
  enabled: computed(() => !!activity.value && !loadError.value),
  isBusy: isDetailLivePollBusy,
  poll: refreshActivityDetailSilent,
})
</script>

<style scoped src="@/styles/material-detail-view.css"></style>
<style scoped src="@/styles/views/activities/detail-panel.css"></style>
<style scoped src="@/styles/views/activities/detail-workflow.css"></style>
<style scoped src="@/styles/views/activities/activity-material-tab.css"></style>
<style scoped>
.activity-detail-view.material-detail-view {
  height: auto;
  min-height: 0;
  overflow: visible;
}

.activity-detail-view :deep(.detail-header) {
  position: static !important;
  top: auto !important;
}

.costs-release-confirm__lead {
  margin: 0 0 12px;
  font-size: 0.9rem;
}

.costs-release-confirm__charge {
  margin: 0 0 12px;
  font-size: 1.05rem;
  font-weight: 600;
}

.costs-release-confirm__total {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 12px;
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  font-size: 0.95rem;
}

.costs-release-confirm__hint {
  margin: 0;
  font-size: 0.85rem;
}
</style>
