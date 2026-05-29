<template>
  <div class="material-detail-view activity-detail-view">
    <header class="detail-header">
      <div class="header-left">
        <button type="button" class="back-btn" @click="handleClose">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M12 19l-7-7 7-7" />
          </svg>
          {{ t('activities.detail.backToList') }}
        </button>
        <div class="header-title activity-detail-header-title">
          <span v-if="noLabel" class="material-code">{{ noLabel }}</span>
          <span v-if="activity" class="type-badge" :class="activity.type">{{ activityTypeLabelDetail(activity.type) }}</span>
          <h1>{{ activity?.name ?? t('activities.detail.fallbackTitle') }}</h1>
          <span v-if="activity" class="status-label" :class="activityStatusClass(activity.status)">{{ activityStatusLabelDetail(activity.status) }}</span>
        </div>
      </div>
      <div v-if="activity && !loadError" class="header-actions activity-detail-workflow-actions">
        <template v-if="canManageActivityQr">
          <button
            v-if="showGenerateActivityQrButton"
            type="button"
            class="btn-outline btn-sm"
            :disabled="isGeneratingActivityPublicCode"
            @click="generateActivityPublicCode"
          >
            {{ isGeneratingActivityPublicCode ? t('activities.detail.qrGenLoading') : t('activities.detail.qrGenCreate') }}
          </button>
          <PublicQrTag
            v-if="activityPublicUrl"
            class="header-qr-tag"
            :url="activityPublicUrl"
            :code="activity.public_code"
            :size="64"
            :clickable="true"
            :image-label="activity.name"
            :image-entity-id="activity.id"
            @activate="openActivityQrActionModal"
          />
        </template>
        <button
          v-for="tr in workflowTransitions"
          :key="tr.status"
          type="button"
          class="btn-outline btn-sm"
          :disabled="isTransitioning || !tr.allowed"
          :title="!tr.allowed && tr.reason ? tr.reason : undefined"
          @click="onTransition(tr)"
        >
          {{ transitionActionLabel(tr) }}
        </button>
        <button
          v-if="cancelTransition"
          type="button"
          class="btn-outline btn-sm activity-danger-outline"
          :disabled="isTransitioning"
          @click="onCancelActivity"
        >
          {{ cancelTransition ? transitionActionLabel(cancelTransition) : '' }}
        </button>
        <button
          v-if="showDamageReportEntry"
          type="button"
          class="btn-outline btn-sm"
          @click="openDamageReport()"
        >
          {{ t('activities.detail.reportDamage') }}
        </button>
        <button type="button" class="btn-outline" @click="handleClose">{{ t('activities.detail.close') }}</button>
      </div>
    </header>

    <div v-if="isLoading" class="loading-container">
      <div class="spinner"></div>
      <p>{{ t('activities.detail.loading') }}</p>
    </div>

    <div v-else-if="loadError" class="loading-container activity-detail-error">
      <p>{{ loadError }}</p>
      <button type="button" class="btn-primary" @click="reload">{{ t('common.retry') }}</button>
      <button type="button" class="btn-outline" @click="handleClose">{{ t('activities.detail.loadErrorBack') }}</button>
    </div>

    <div v-else-if="activity" class="detail-content">
      <div v-if="activity.status === 'draft'" class="draft-hint-banner">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="12" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <span>
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
        </span>
      </div>

      <ActivityCompletionChecklist
        v-if="canManageMaterials && activity.status === 'returned' && completionBlockers"
        :blockers="completionBlockers"
        :activity-id="activityId"
        :host-department-id="departmentId"
        @go-tab="onCompletionGoTab"
      />

      <div v-if="showMemberScopeStatusHint" class="draft-hint-banner member-scope-hint-banner">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
          <circle cx="12" cy="12" r="10" />
          <line x1="12" y1="8" x2="12" y2="12" />
          <line x1="12" y1="16" x2="12.01" y2="16" />
        </svg>
        <span>{{ t('activities.detail.memberScopeStatusHint') }}</span>
      </div>

      <nav class="tab-nav">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          class="tab-btn"
          :class="{ active: activeTab === tab.id }"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </nav>

      <div class="content-layout activity-detail-content-layout">
        <main class="content-main">
          <!-- Übersicht -->
          <section v-if="activeTab === 'overview'" class="tab-content">
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
                  <div v-if="activity.total_price != null" class="form-group">
                    <label>{{ t('activities.detail.labelTotalPrice') }}</label>
                    <p class="activity-readonly-value">CHF {{ Number(activity.total_price).toFixed(2) }}</p>
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
                  </li>
                </ul>
              </div>

              <div v-if="activity.notes" class="section-card activity-tab-panel-card">
                <h2 class="section-title activity-tab-subsection-title">{{ t('activities.detail.sectionNotes') }}</h2>
                <p class="activity-notes">{{ activity.notes }}</p>
              </div>
            </template>
          </section>

          <!-- Material -->
          <section v-else-if="activeTab === 'material'" class="tab-content">
            <ActivityTabHeader :title="t('common.material')" />
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
                :invited-departments="activity.invited_departments ?? []"
                :disabled="addingDraftMaterial"
                hint-variant="draft"
                @add-quantity="onDraftAddQuantity"
                @scope-change="onMaterialLookupScopeChange"
              />
              <p v-if="addingDraftMaterial" class="activity-inline-loading activity-draft-adding">
                <span class="spinner spinner-sm"></span>
                <span>{{ t('activities.detail.addingMaterial') }}</span>
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
                  :invited-departments="activity.invited_departments ?? []"
                  :disabled="addingDraftMaterial"
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
                :invited-departments="activity.invited_departments ?? []"
                :disabled="addingDraftMaterial"
                hint-variant="draft"
                @add-quantity="onDraftAddQuantity"
                @scope-change="onMaterialLookupScopeChange"
              />
              <p v-if="addingDraftMaterial" class="activity-inline-loading activity-draft-adding">
                <span class="spinner spinner-sm"></span>
                <span>{{ t('activities.detail.addingMaterial') }}</span>
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

            <div class="section-card activity-tab-panel-card">
              <h2 class="section-title activity-tab-subsection-title">{{ t('activities.detail.materialPositionsTitle') }}</h2>
              <div v-if="itemsLoading" class="activity-inline-loading">
                <div class="spinner spinner-sm"></div>
                <span>{{ t('activities.detail.itemsLoading') }}</span>
              </div>
              <div v-else-if="activityItems.length === 0" class="text-muted">
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
                  :empty-text="t('activities.detail.noPositions')"
                  @update:model-value="onDraftLinesTableUpdate"
                  @remove-line="onDraftTableRemoveLine"
                />
                <div v-if="hasDraftQtyChanges" class="activity-qty-save-row">
                  <button
                    type="button"
                    class="btn-primary btn-sm"
                    :disabled="syncingQuantities"
                    @click="saveDraftQuantities"
                  >
                    {{ syncingQuantities ? t('activities.detail.saveQtySaving') : t('activities.detail.saveQty') }}
                  </button>
                </div>
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
                    <tr v-for="row in activityItems" :key="row.id">
                      <td>
                        <div class="activity-item-name-block">
                          <span class="activity-item-name">{{ row.material_name }}</span>
                          <span
                            v-if="row.material_type === 'physical_combo'"
                            class="activity-combo-badge"
                            :title="t('activities.detail.comboPhysicalTitle')"
                            >{{ t('activities.detail.comboPhysicalShort') }}</span
                          >
                          <span
                            v-else-if="row.material_type === 'virtual_combo'"
                            class="activity-combo-badge activity-combo-badge--virtual"
                            :title="t('activities.detail.comboVirtualTitle')"
                            >{{ t('activities.detail.comboVirtualShort') }}</span
                          >
                          <span v-if="row.is_js_material" class="activity-js-tag">J&amp;S</span>
                          <span
                            v-if="row.is_replenishment"
                            class="activity-replenishment-badge"
                            :title="t('activities.detail.replenishmentBadge')"
                          >{{ t('activities.detail.replenishmentBadge') }}</span>
                          <div v-if="row.linked_container_label" class="activity-combo-kiste text-muted">
                            {{ t('activities.detail.crateLine', { label: row.linked_container_label }) }}
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
            </div>
          </section>

          <!-- Packliste -->
          <section v-else-if="activeTab === 'packs'" class="tab-content">
            <ActivityPackListTab
              ref="packListTabRef"
              v-if="activity"
              :activity-id="activityId"
              :department-id="departmentId"
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
              :can-add-activity-material="
                canAddActivityMaterial && (activity.status === 'packing' || activity.status === 'packed')
              "
              :can-request-consumable-nachbuchung="canRequestConsumableNachbuchung"
              :activity-type-for-material-add="activityTypeForMat"
              :planning-start-iso="activity.planning_start ?? null"
              :planning-end-iso="activity.planning_end ?? null"
              :quantity-by-material-item-id-for-add="quantityByMaterialItemId"
              :saved-quantity-by-material-item-id-for-add="savedQuantityByMaterialItemId"
              :invited-departments-for-add="activity.invited_departments ?? []"
              :adding-activity-material="addingDraftMaterial"
              @workflow-next="onPackListWorkflowNext"
              @activity-items-changed="onPackListActivityItemsChanged"
              @open-issue-wizard="onPackIssueWizard"
              @open-consumption-modal="onOpenConsumptionModal"
              @request-nachbuchung="openNachbuchungModal"
              @add-activity-material="onDraftAddQuantity"
              @material-scope-change="onMaterialLookupScopeChange"
            />
          </section>

          <!-- Reparaturen / Verluste (wie v4.01) -->
          <section v-else-if="activeTab === 'issues'" class="tab-content">
            <ActivityIssuesTab
              :activity-id="activityId"
              :can-create="showDamageReportEntry"
              :read-only-hint="showIssuesTabReadOnlyHint"
              :reload-token="issuesReloadToken"
              @open-wizard="openDamageReport()"
            />
          </section>

          <section v-else-if="activeTab === 'consumables'" class="tab-content">
            <ActivityConsumablesTab
              :activity-id="activityId"
              :can-create="showConsumptionBooking"
              :can-add-activity-material="canAddActivityMaterial"
              :can-request-consumable-replenishment="canRequestConsumableNachbuchung"
              :reload-token="consumablesReloadToken"
              @request-nachbuchung="openNachbuchungModal"
              @consumption-booked="onConsumableBooked"
              @edit-consumption="onEditConsumption"
            />
          </section>

          <section v-else-if="activeTab === 'costs'" class="tab-content">
            <ActivityCostsTab
              v-if="activity"
              :activity-id="activityId"
              :department-id="departmentId"
              :activity-type="activity.type"
              :activity-status="activity.status"
              :reload-token="costsReloadToken"
            />
          </section>

          <!-- Verlauf -->
          <section v-else-if="activeTab === 'history'" class="tab-content">
            <ActivityHistoryTab :activity-id="activityId" />
          </section>
        </main>
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

    <PublicQrActionModal
      :open="showActivityQrActionModal"
      :label="activity?.name"
      :code="activity?.public_code"
      :url="activityPublicUrl"
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
  ensureActivityPublicCode,
  getActivity,
  getActivityIssues,
  getActivityItems,
  getActivityTransitions,
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
import ActivityMaterialAvailabilityLookup from '@/components/activities/ActivityMaterialAvailabilityLookup.vue'
import ActivityMaterialLinesTable from '@/components/activities/shared/ActivityMaterialLinesTable.vue'
import ActivityDraftOverviewForm from '@/components/activities/ActivityDraftOverviewForm.vue'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'
import ActivityCostsTab from '@/components/activities/ActivityCostsTab.vue'
import ActivityCompletionChecklist from '@/components/activities/ActivityCompletionChecklist.vue'
import ActivityPackListTab from '@/components/activities/ActivityPackListTab.vue'
import ActivityIssuesTab from '@/components/activities/ActivityIssuesTab.vue'
import ActivityConsumablesTab from '@/components/activities/ActivityConsumablesTab.vue'
import ActivityHistoryTab from '@/components/activities/ActivityHistoryTab.vue'
import ActivityConsumptionModal from '@/components/activities/ActivityConsumptionModal.vue'
import ActivityConsumableNachbuchungModal from '@/components/activities/ActivityConsumableNachbuchungModal.vue'
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
import type { MaterialScopeTab } from '@/components/activities/shared/activityMaterialAvailabilityScope'
import { useActivityGroupMemberScope } from '@/composables/useActivityGroupMemberScope'
import {
  isDepartmentBasicMemberRole,
  useDepartmentMemberRole,
} from '@/composables/useDepartmentMemberRole'
import { useBackgroundPoll } from '@/composables/useBackgroundPoll'
import { useConfirm } from '@/composables/useConfirm'
import { usePageHeadStore } from '@/stores/pageHead'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import { useToast } from '@/composables/useToast'
import { resolveActivityPublicUrl } from '@/utils/publicQrUrl'
import { activityStatusClass, activityStatusI18nKey } from '@/utils/activityStatus'
import QRCode from 'qrcode'

defineOptions({ name: 'ActivityDetailView' })

/** Workflow-Schritte ab Einreichung — nur MW/DC/Gruppenchef (nicht u + Gruppenmitglied). */
const MANAGER_WORKFLOW_TRANSITION_STATUSES = new Set([
  'approved',
  'packing',
  'packed',
  'at_event',
  'returned',
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
const authStore = useAuthStore()

const ACTIVITY_TAB_IDS = ['overview', 'material', 'packs', 'issues', 'consumables', 'costs', 'history'] as const
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
const pageHeadStore = usePageHeadStore()
const headerNotificationsStore = useHeaderNotificationsStore()
const { t, te, locale } = useI18n()
const { canManageMaterials } = useDepartmentMemberRole()

function transitionActionLabel(tr: ActivityTransitionRow): string {
  return activityTransitionActionLabel(tr.status, activity.value?.status, t, te, tr.label)
}

/** Camp/Event: «Retour erfassen» erst im Pack-Tab «Transport (zurück)→Retour», nicht in der Kopfzeile bei «Am Event». */
function hideReturnedTransitionInActivityHeader(targetStatus: string): boolean {
  const act = activity.value
  if (!act || targetStatus !== 'returned' || act.status !== 'at_event') return false
  return packWorkflowProfileForActivityType(act.type || 'activity') === 'logistics'
}

const activity = ref<ActivityDetail | null>(null)
const isGeneratingActivityPublicCode = ref(false)
const showActivityQrActionModal = ref(false)

/** Ab «Wird gepackt»: Storno nur noch MW/DC (Material bereits aus dem Lager genommen / gepackt). */
const STATUSES_STAFF_ONLY_CANCEL = [
  'packing',
  'packed',
  'at_event',
  'returned',
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

/** Wie v4.01: Packliste erst ab «Wird gepackt», nicht schon bei «Bestätigt». */
const STATUSES_WITH_PACKS_TAB = [
  'packing',
  'packed',
  'at_event',
  'returned',
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

/** Reparaturen / Verluste: ab «Am Event» (Material ausgegeben) */
const STATUSES_WITH_ISSUES_TAB = ['at_event', 'returned', 'completed'] as const

/** Gruppe: Meldungen ab «Wird gepackt» nur lesen; neue Meldungen erst ab «Am Event». */
const MEMBER_ISSUES_PREVIEW_STATUSES = ['packing', 'packed'] as const

/** Verbrauchsmaterial buchen: erst ab «Am Event» */
const STATUSES_WITH_CONSUMABLES_TAB = ['at_event', 'returned', 'completed'] as const

const showIssuesTab = computed(() => {
  const s = activity.value?.status
  if (!s) return false
  if ((STATUSES_WITH_ISSUES_TAB as readonly string[]).includes(s)) return true
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

const hasConsumableItems = computed(() =>
  activityItems.value.some((row) => row.is_consumable === true),
)

const showConsumablesTab = computed(() => {
  const s = activity.value?.status
  if (!s) return false
  if (!(STATUSES_WITH_CONSUMABLES_TAB as readonly string[]).includes(s)) return false
  return hasConsumableItems.value
})

const activityIssues = ref<ActivityIssueReportRow[]>([])

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
  if (status && ['packing', 'packed', 'at_event', 'returned'].includes(status)) return 'packs'
  return 'overview'
}

const tabs = computed(() => {
  const out: { id: ActivityTabId; label: string }[] = [
    { id: 'overview', label: t('activities.detail.tabOverview') },
    { id: 'material', label: t('common.material') },
  ]
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

const packListTabRef = ref<ActivityPackListTabExpose | null>(null)

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
const activityItems = ref<ActivityItemRow[]>([])
const isLoading = ref(true)
const itemsLoading = ref(false)
const loadError = ref<string | null>(null)
const isTransitioning = ref(false)
const activeTab = ref<ActivityTabId>('overview')

watch(showPacksTab, (show) => {
  if (!show && activeTab.value === 'packs') {
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
    // Quick-Modus: kein «Bestätigen» — Material ist bei Einreichung bereits final
    if (activity.value?.type === 'activity' && t.status === 'approved') return false
    if (hideReturnedTransitionInActivityHeader(t.status)) return false
    return true
  }),
)

const activityTypeForMat = computed(
  (): ActivityApiType => (activity.value?.type || 'activity') as ActivityApiType,
)

/** Summe aktueller Mengen pro Material-Item (Entwurf: inkl. nicht gespeicherter Änderungen) */
const quantityByMaterialItemId = computed(() => {
  const m: Record<string, number> = {}
  for (const r of activityItems.value) {
    m[r.material_item_id] = (m[r.material_item_id] ?? 0) + draftQty(r)
  }
  return m
})

/** Gespeicherte Summen pro Material (API) — für Verfügbarkeit in der Suche vs. Entwurf */
const savedQuantityByMaterialItemId = computed(() => {
  const m: Record<string, number> = {}
  for (const r of activityItems.value) {
    m[r.material_item_id] = (m[r.material_item_id] ?? 0) + r.quantity
  }
  return m
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

/** Wie v4.01: Übersicht nur im Entwurf bearbeitbar (kein PATCH-Formular nach Einreichung). */
const showOverviewEditForm = computed(
  () => !!activity.value && activity.value.status === 'draft',
)

/** Entwurf (bestehende Regeln) oder nach Einreichung: Host-MW/DC bis einschliesslich «gepackt». */
const showMaterialLookup = computed(() => {
  const a = activity.value
  if (!a) return false
  if (a.status === 'draft') return !!a.can_edit_draft_material
  return !!a.can_edit_activity_material
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

/** «Material hinzufügen» im Material-Tab (MW/DC mit Bearbeitungsrecht, bis «Am Event»). */
const showMaterialAddOnMaterialTab = computed(
  () => showMaterialLookup.value && !isBasicDepartmentMember.value,
)

const materialLinesForEditableTable = computed((): ActivityMaterialLine[] => {
  if (!showMaterialLookup.value) return []
  return activityItems.value.map((r) => ({
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
    source_department_name: r.source_department_name ?? null,
    line_total: r.line_total,
    is_js_material: r.is_js_material,
    tracking_type: r.tracking_type ?? null,
    is_container: !!r.is_container,
  }))
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

/** Reparaturen / Verluste: erst nach «Am Event buchen»; User/Gruppe nicht mehr nach «Retour erfassen». */
const showDamageReportEntry = computed(() => {
  const a = activity.value
  if (!a) return false
  if (a.status === 'completed') return false
  if (a.can_report_issues === false) return false
  const s = a.status
  if (s !== 'at_event' && s !== 'returned') return false
  if (s === 'returned' && !canReportDamageAsMaterialStaff.value) return false
  return true
})

/** Verbrauch buchen: User/Leader ab «Am Event» und in Retour (Modal / Tab «Verbrauch»). Buch-Buchhaltung erst MW beim Einlagern/Abschluss. */
const showConsumptionBooking = computed(() => {
  const a = activity.value
  if (!a || a.status === 'completed') return false
  if (a.can_report_issues === false) return false
  return a.status === 'at_event' || a.status === 'returned'
})

/** Nachbuchung zur Aktivität (addActivityItem) — wie Tab «Material» */
const canAddActivityMaterial = computed(() => activity.value?.can_edit_activity_material === true)

/** Nachlieferung Verbrauchsmaterial: MW/DC oder Gruppe/Ersteller ab «Am Event». */
const canRequestConsumableNachbuchung = computed(
  () =>
    activity.value?.can_request_consumable_replenishment === true ||
    canAddActivityMaterial.value,
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

watch(issuesReloadToken, () => {
  void loadActivityIssues()
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
  consumptionModalPreset.value = payload
  consumptionModalOpen.value = true
}

function onEditConsumption(payload: ConsumptionModalPreset) {
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
  nachbuchungPackStage.value = payload.packStage?.trim() || null
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
  const m: Record<string, number> = {}
  for (const r of activityItems.value) {
    m[r.id] = r.quantity
  }
  draftQuantities.value = m
}

function onDraftLinesTableUpdate(lines: ActivityMaterialLine[]) {
  const next = { ...draftQuantities.value }
  for (const line of lines) {
    if (line.activity_item_id) {
      next[line.activity_item_id] = line.quantity
    }
  }
  draftQuantities.value = next
}

function onDraftTableRemoveLine({ line }: { line: ActivityMaterialLine; index: number }) {
  const row = activityItems.value.find((r) => r.id === line.activity_item_id)
  if (row) void onRemoveDraftItem(row)
}

async function saveDraftQuantities() {
  const a = activity.value
  if (!a) return
  const can =
    (a.status === 'draft' && a.can_edit_draft_material) ||
    (a.status !== 'draft' && a.can_edit_activity_material)
  if (!can) return
  syncingQuantities.value = true
  try {
    await syncActivityItems(props.activityId, {
      items: activityItems.value.map((r) => ({
        material_item_id: r.material_item_id,
        quantity: draftQty(r),
        priority: r.priority ?? undefined,
      })),
    })
    toast.success(t('activities.detail.toastQtySaved'))
    await loadItems()
    await refreshActivityTotalsFromApi()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.detail.toastQtySaveFailed'))
  } finally {
    syncingQuantities.value = false
  }
}

async function onDraftOverviewSaved() {
  await reload()
}

function activityTypeLabelDetail(type: string): string {
  const key = `activities.types.${type}` as const
  return te(key) ? t(key) : type
}

function activityStatusLabelDetail(status: string): string {
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
  void router.push(`/${props.departmentId}/activities`)
}

async function loadActivityIssues() {
  try {
    activityIssues.value = await getActivityIssues(props.activityId)
  } catch {
    activityIssues.value = []
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
  draftQuantities.value = {}
  try {
    const [detail, tr, items] = await Promise.all([
      getActivity(props.activityId),
      getActivityTransitions(props.activityId),
      getActivityItems(props.activityId).catch(() => [] as ActivityItemRow[]),
      loadGroupsForDepartment(props.departmentId),
      loadActivityIssues(),
    ])
    activity.value = detail
    transitions.value = tr.transitions || []
    completionBlockers.value = tr.completion_blockers ?? null
    activityItems.value = items
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

async function loadItems() {
  itemsLoading.value = true
  try {
    activityItems.value = await getActivityItems(props.activityId)
    initDraftQuantitiesFromItems()
  } catch {
    activityItems.value = []
    draftQuantities.value = {}
    toast.error(t('activities.detail.toastItemsLoadFailed'))
  } finally {
    itemsLoading.value = false
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
  const d = await getActivity(props.activityId)
  if (!activity.value) return
  applyActivityDetailPatch(d)
}

async function refreshCompletionBlockers() {
  if (activity.value?.status !== 'returned') {
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
      getActivity(props.activityId),
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

async function onDraftAddQuantity(payload: { material: { materialItemId: string }; quantity: number }) {
  const mid = payload.material?.materialItemId
  const a = activity.value
  if (!mid || !a) return
  const can =
    (a.status === 'draft' && a.can_edit_draft_material) ||
    (a.status !== 'draft' && a.can_edit_activity_material) ||
    !!a.can_add_forgotten_material
  if (!can) return
  addingDraftMaterial.value = true
  try {
    await addActivityItem(props.activityId, {
      material_item_id: mid,
      quantity: payload.quantity,
    })
    toast.success(t('activities.detail.toastMaterialAdded'))
    await loadItems()
    await refreshActivityTotalsFromApi()
    if (a.status === 'packing' || a.status === 'packed') {
      packListReloadToken.value += 1
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.detail.toastMaterialAddFailed'))
  } finally {
    addingDraftMaterial.value = false
  }
}

const PACK_PIPELINE_ITEM_STATUSES = ['packed', 'at_event', 'returned'] as const

function activityItemHasPackProgress(row: ActivityItemRow): boolean {
  const st = (row.status ?? '').trim()
  return (PACK_PIPELINE_ITEM_STATUSES as readonly string[]).includes(st)
}

async function onRemoveDraftItem(row: ActivityItemRow) {
  const a = activity.value
  if (!a) return
  const can =
    (a.status === 'draft' && a.can_edit_draft_material) ||
    (a.status !== 'draft' && a.can_edit_activity_material)
  if (!can) return

  const status = a.status || ''
  if (
    (STATUSES_AT_OR_AFTER_PACKING as readonly string[]).includes(status) &&
    activityItemHasPackProgress(row)
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
    await removeActivityItem(props.activityId, row.id)
    toast.success(t('activities.detail.toastPositionRemoved'))
    await loadItems()
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

async function onTransition(
  transition: ActivityTransitionRow,
  options?: { skipPackConfirm?: boolean },
) {
  if (!transition.allowed || isTransitioning.value) return

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
    const detail = await getActivity(props.activityId)
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
  mergeActivityQuery({ tab })
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
  if (newTab === 'material' && activity.value) {
    void loadItems()
  }
})

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
