<template>
  <div class="activity-pack-list-tab">
    <div v-if="loading" class="section-card">
      <p class="activity-inline-loading">
        <span class="spinner spinner-sm"></span>
        <span>{{ t('activities.packList.loading') }}</span>
      </p>
    </div>

    <div v-else-if="loadError" class="section-card">
      <p class="text-muted">{{ loadError }}</p>
      <button type="button" class="btn-outline btn-sm" @click="loadAll">{{ t('activities.common.retry') }}</button>
    </div>

    <template v-else>
      <template v-if="packItems.length === 0">
        <ActivityTabHeader :title="t('activities.packList.title')" />
        <div class="section-card activity-tab-panel-card">
          <p class="text-muted">{{ t('activities.packList.emptyPositions') }}</p>
          <button
            v-if="canInitPackList"
            type="button"
            class="btn-primary btn-sm"
            :disabled="initLoading"
            @click="onInitPackList"
          >
            {{ initLoading ? t('activities.packList.initCreating') : t('activities.packList.initStart') }}
          </button>
        </div>
      </template>

      <template v-else>
        <ActivityTabHeader :title="t('activities.packList.titleWorkflow')">
          <p
            v-if="memberReturnHandoffComplete"
            class="activity-pack-readonly-hint activity-pack-readonly-hint--handoff text-muted"
          >
            {{ t('activities.packList.readonlyHintReturnedHandoff') }}
          </p>
          <p
            v-else-if="!packListEditable && !memberAwaitingMwPack"
            class="activity-pack-readonly-hint text-muted"
          >
            {{ t('activities.packList.readonlyHint') }}
          </p>
        </ActivityTabHeader>

        <div v-if="showPackMaterialAddPanel" class="section-card pack-add-material-card">
          <button
            type="button"
            class="pack-add-material-toggle"
            :aria-expanded="packAddMaterialExpanded"
            @click="packAddMaterialExpanded = !packAddMaterialExpanded"
            >
            <span class="pack-add-material-chevron" aria-hidden="true">{{ packAddMaterialExpanded ? '▼' : '▶' }}</span>
            <span class="pack-add-material-toggle-title">{{ t('activities.packList.addMaterialToggleTitle') }}</span>
          </button>
          <div v-show="packAddMaterialExpanded" class="pack-add-material-body">
            <p class="pack-add-material-summary text-muted">{{ t('activities.packList.addMaterialToggleSummary') }}</p>
            <p class="field-hint text-muted pack-add-material-hint">{{ t('activities.packList.addMaterialHint') }}</p>
            <div v-if="showPackMaterialAddTarget" class="pack-add-material-target">
              <label class="pack-add-material-target-label">
                <span>{{ t('activities.packList.addMaterialTargetLabel') }}</span>
                <select v-model="materialAddTargetKey" class="form-select pack-add-material-target-select">
                  <option
                    v-for="entry in materialAddTargetEntries"
                    :key="'add-target-' + entry.key"
                    :value="entry.key"
                  >
                    {{ entry.label }}
                  </option>
                </select>
              </label>
              <p
                v-if="materialAddTargetKey !== 'loose'"
                class="field-hint text-muted pack-add-material-target-hint"
              >
                {{ t('activities.packList.addMaterialTargetCrateHint') }}
              </p>
            </div>
            <p
              v-else-if="
                showPackContainersUi &&
                activePackStage === 'confirmed_packed' &&
                !hasKisteAddTargetsOnActivity &&
                packContainers.length === 0
              "
              class="field-hint text-muted pack-add-material-target-hint"
            >
              {{ t('activities.packList.addMaterialTargetNoCrates') }}
            </p>
            <ActivityMaterialAvailabilityLookup
              v-if="packMaterialAddLookupReady"
              :department-id="departmentId"
              :activity-id="activityId"
              :activity-type="activityTypeForMaterialAdd"
              :planning-start-iso="planningStartIso ?? null"
              :planning-end-iso="planningEndIso ?? null"
              :quantity-by-material-item-id="quantityByMaterialItemIdForAdd"
              :saved-quantity-by-material-item-id="savedQuantityByMaterialItemIdForAdd"
              :invited-departments="invitedDepartmentsForAdd"
              :disabled="addingActivityMaterial"
              :search-reset-key="reloadToken"
              :repeat-add-from-search="true"
              hint-variant="draft"
              @add-quantity="onPackTabAddMaterialQuantity"
              @scope-change="onPackTabMaterialScopeChange"
            />
            <p v-if="addingActivityMaterial" class="activity-inline-loading activity-draft-adding">
              <span class="spinner spinner-sm"></span>
              <span>{{ t('activities.detail.addingMaterial') }}</span>
            </p>
          </div>
        </div>


      <div
        class="pack-workflow"
        :class="{
          'pack-workflow--crate-target-active': hasActiveCrateTarget,
          'pack-workflow--readonly': packWorkflowReadOnly,
          'pack-workflow--member-preview': memberAwaitingMwPack,
        }"
      >
        <div
          v-if="memberAwaitingMwPack"
          class="pack-member-preview-banner"
          role="note"
        >
          <p class="pack-member-preview-banner-title">{{ t('activities.packList.memberPackPreviewHint') }}</p>
        </div>
        <div
          v-if="showMwOpenPackingWorkBanner"
          class="pack-mw-open-packing-banner"
          role="note"
        >
          <p class="pack-mw-open-packing-banner-title">
            {{ t('activities.packList.mwOpenPackingWorkBannerTitle') }}
          </p>
          <p class="pack-mw-open-packing-banner-body text-muted">
            {{ t('activities.packList.mwOpenPackingWorkBannerBody', {
              stage: packStageViewLabel('confirmed_packed'),
            }) }}
          </p>
        </div>
        <div
          v-if="showMwLooseCrateAssignmentBanner"
          class="pack-mw-open-packing-banner pack-mw-loose-crate-banner"
          role="note"
        >
          <p class="pack-mw-open-packing-banner-title">
            {{ t('activities.packList.mwLooseCrateAssignmentBannerTitle') }}
          </p>
          <p class="pack-mw-open-packing-banner-body text-muted">
            {{ t('activities.packList.mwLooseCrateAssignmentBannerBody', {
              stage: packStageViewLabel('confirmed_packed'),
            }) }}
          </p>
        </div>
        <div
          v-if="showMwHandoffBanner"
          class="pack-mw-handoff-banner"
          role="note"
        >
          <p class="pack-mw-handoff-banner-title">{{ t('activities.packList.mwHandoffBannerTitle') }}</p>
          <p class="pack-mw-handoff-banner-body text-muted">{{ t('activities.packList.mwHandoffBannerBody') }}</p>
        </div>
        <div
          v-if="showMwReturnHandoffBanner"
          class="pack-mw-handoff-banner"
          role="note"
        >
          <p class="pack-mw-handoff-banner-title">{{ t('activities.packList.mwReturnHandoffBannerTitle') }}</p>
          <p class="pack-mw-handoff-banner-body text-muted">{{ t('activities.packList.mwReturnHandoffBannerBody') }}</p>
        </div>

        <div
          v-if="showMemberMwPackIncompleteBanner"
          class="pack-member-incomplete-banner"
          role="note"
        >
          <p class="pack-member-incomplete-banner-title">
            {{ t('activities.packList.memberIncompletePackTitle') }}
          </p>
          <p class="pack-member-incomplete-banner-body text-muted">
            {{ memberMwPackIncompleteHint }}
          </p>
          <div class="pack-member-incomplete-list">
            <div
              v-for="pi in memberMwPackIncompleteItems"
              :key="'incomplete-' + pi.id"
              class="pack-member-incomplete-item"
            >
              <PackMaterialRow :item="pi" :show-linked-kiste="isPhysicalComboPackItem(pi)">
                <template #detail>
                  <p class="pack-member-incomplete-qty text-muted">
                    {{
                      t('activities.packList.memberIncompletePackQty', {
                        packed: pi.quantityPacked,
                        ordered: pi.quantityOrdered,
                      })
                    }}
                  </p>
                </template>
              </PackMaterialRow>
              <PackCrateShellInlinePanel
                v-if="isPhysicalComboPackItem(pi) && peekSectionsForShellPackItem(pi).length > 0"
                class="pack-member-incomplete-bom"
                :sections="peekSectionsForShellPackItem(pi)"
                :empty-hint="crateShellPeekEmptyHint(pi)"
                :check-pack-item="pi"
                :loose-issue-container-id="null"
                :stage-right-label="activeStageConfig.rightLabel"
                :reality-banner="null"
                :show-template-toggle="false"
                :use-reality-view="true"
                separate-section-rows
                :default-expanded="true"
              />
            </div>
          </div>
        </div>

        <div v-if="packStageKeys.length > 1" class="pack-stage-tabs">
          <button
            v-for="st in packStagesForUi"
            :key="st.key"
            type="button"
            class="pack-stage-tab"
            :class="{ active: activePackStage === st.key }"
            @click="setStage(st.key)"
          >
            {{ st.leftLabel }} <span class="stage-arrow">→</span> {{ st.rightLabel }}
          </button>
        </div>

        <div
          v-if="showPackStageOpenIssueRemainderBanner"
          class="pack-stage-view-only-banner pack-stage-open-issue-banner"
          role="note"
        >
          <p class="pack-stage-view-only-banner__title">
            {{ t('activities.packList.packStageOpenIssueRemainderTitle') }}
          </p>
          <p class="pack-stage-view-only-banner__hint text-muted">
            {{ t('activities.packList.packStageOpenIssueRemainderHint') }}
          </p>
        </div>
        <div
          v-if="isActivityCompleted"
          class="pack-stage-view-only-banner pack-stage-completed-banner"
          role="note"
        >
          <p class="pack-stage-view-only-banner__title">
            {{ t('activities.packList.packStageCompletedBannerTitle') }}
          </p>
          <p class="pack-stage-view-only-banner__hint text-muted">
            {{ t('activities.packList.packStageCompletedBannerHint') }}
          </p>
        </div>
        <div v-else-if="showPackStageViewOnlyBanner" class="pack-stage-view-only-banner" role="note">
          <p class="pack-stage-view-only-banner__title">
            {{
              t('activities.packList.packStageViewOnlyBanner', {
                stageLeft: activeStageConfig.leftLabel,
                stageRight: activeStageConfig.rightLabel,
              })
            }}
          </p>
          <p
            v-if="!isActiveStatusPackStage"
            class="pack-stage-view-only-banner__hint text-muted"
          >
            {{
              t('activities.packList.packStageViewOnlyStatusHint', {
                stageLeft: statusStageConfig.leftLabel,
                stageRight: statusStageConfig.rightLabel,
              })
            }}
          </p>
          <p class="pack-stage-view-only-banner__hint text-muted">
            {{
              isViewingFuturePackStage
                ? t('activities.packList.packStageViewFutureHint')
                : t('activities.packList.packStageViewPastHint')
            }}
          </p>
        </div>

        <div
          v-if="showPendingOutboundCrateCheckBanner"
          class="pack-crate-check-pending-banner"
          role="note"
        >
          <p class="pack-crate-check-pending-banner__title">
            {{ t('activities.packList.crateCheckPendingBannerTitle') }}
          </p>
          <p class="pack-crate-check-pending-banner__body text-muted">
            {{ t('activities.packList.crateCheckPendingBannerBody') }}
          </p>
          <ul v-if="pendingOutboundCrateCheckLabels.length > 0" class="pack-crate-check-pending-banner__list">
            <li v-for="label in pendingOutboundCrateCheckLabels" :key="label">{{ label }}</li>
          </ul>
        </div>

        <div v-if="showPackStageProgress" class="pack-progress-bar">
          <div class="pack-progress-info">
            <div class="pack-progress-left">
              <button
                v-if="showWorkflowRevertButton && previousWorkflowTransition"
                type="button"
                class="btn btn-xs btn-outline btn-workflow-revert"
                :disabled="isTransitioningPackWorkflow"
                :title="workflowRevertVisibleLabel"
                @click="onWorkflowRevertClick"
              >
                {{ workflowRevertVisibleLabel }}
              </button>
              <span :title="stageProgressPendingTitle">{{
                showMwGroupHandoffBanner
                  ? t('activities.packList.progressPercentGroup', {
                      pct: stageProgress,
                      stage: activeStageConfig.rightLabel,
                    })
                  : t('activities.packList.progressPercent', {
                      pct: stageProgress,
                      stage: activeStageConfig.rightLabel,
                    })
              }}</span>
            </div>
            <div class="pack-progress-actions">
              <button
                v-if="showMoveAllToEventQuickButton"
                type="button"
                class="btn btn-xs btn-outline btn-move-all"
                :disabled="moveAllLoading"
                :title="moveAllToEventQuickLabel"
                @click="onMoveAllToNextStageClick"
              >
                {{ moveAllToEventQuickLabel }}
              </button>
              <button
                v-if="showPartialTakenToEventUpperButton"
                type="button"
                class="btn btn-xs btn-outline btn-move-all"
                :disabled="!showPackOperateControls || isTransitioningPackWorkflow"
                :title="partialTakenToEventLabel"
                @click="onPackWorkflowStatusToEventClick"
              >
                {{ partialTakenToEventLabel }}
              </button>
              <button
                v-if="showContinueAfterTransportBackButton"
                type="button"
                class="btn btn-sm btn-progress-action btn-outline"
                :class="{ 'btn-progress-warn': stageProgress < 100 }"
                :disabled="!showPackOperateControls"
                :title="continueAfterTransportBackTitle"
                @click="onContinueAfterTransportBackClick"
              >
                {{ continueAfterTransportBackLabel }}
                <span v-if="stageProgress < 100" class="btn-progress-warn-badge">{{ stageProgress }}%</span>
              </button>
              <button
                v-if="showPackWorkflowToEventButton && nextWorkflowTransition"
                type="button"
                class="btn btn-sm btn-progress-action btn-outline"
                :class="{ 'btn-progress-warn': workflowButtonStageProgress < 100 }"
                :disabled="!showPackOperateControls || isTransitioningPackWorkflow"
                :title="packWorkflowToEventButtonLabel"
                @click="onPackWorkflowStatusToEventClick"
              >
                {{ packWorkflowToEventButtonLabel }}
                <span v-if="workflowButtonStageProgress < 100" class="btn-progress-warn-badge">{{
                  workflowButtonStageProgress
                }}%</span>
              </button>
              <template v-if="!packIssueToEventCombined">
                <button
                  v-if="showPackOperateControls && stageLeftHeaderCount > 0"
                  type="button"
                  class="btn btn-xs btn-outline btn-move-all"
                  :disabled="moveAllLoading"
                  :title="moveAllStageButtonLabel"
                  @click="onMoveAllToNextStageClick"
                >
                  {{ moveAllStageButtonLabel }}
                </button>
                <button
                  v-if="nextWorkflowTransition"
                  type="button"
                  class="btn btn-sm btn-progress-action btn-outline"
                  :class="{ 'btn-progress-warn': workflowButtonStageProgress < 100 }"
                  :disabled="!showPackOperateControls"
                  :title="nextWorkflowTransitionLabel"
                  @click="handleWorkflowTransition"
                >
                  {{ nextWorkflowTransitionLabel }}
                  <span v-if="workflowButtonStageProgress < 100" class="btn-progress-warn-badge">{{
                    workflowButtonStageProgress
                  }}%</span>
                </button>
              </template>
            </div>
          </div>
          <div
            v-if="stageProgress < 100 && stageProgressPendingLines.length > 0"
            class="pack-progress-pending"
            role="region"
            :aria-label="t('activities.packList.progressPendingTitle', { stage: activeStageConfig.rightLabel })"
          >
            <button
              type="button"
              class="pack-progress-pending-accordion"
              :aria-expanded="progressPendingOpen"
              @click="progressPendingOpen = !progressPendingOpen"
            >
              <span class="pack-progress-pending-title">{{
                t('activities.packList.progressPendingTitle', { stage: activeStageConfig.rightLabel })
              }}</span>
              <span class="pack-workflow-section-badge">{{ stageProgressPendingLines.length }}</span>
              <span class="pack-group-toggle">{{ progressPendingOpen ? '▼' : '▶' }}</span>
            </button>
            <div v-show="progressPendingOpen" class="pack-progress-pending-body">
              <p class="pack-progress-pending-intro text-muted">
                {{ t('activities.packList.progressPendingIntro') }}
              </p>
              <ul class="pack-progress-pending-list">
                <li v-for="line in stageProgressPendingLines" :key="line.key">
                  <span class="pack-progress-pending-line-qty">{{
                    t('activities.packList.progressPendingLineShort', {
                      qty: line.qty,
                      material: line.material,
                    })
                  }}</span>
                  <span class="pack-progress-pending-action">{{ line.actionHint }}</span>
                </li>
              </ul>
            </div>
          </div>
          <div class="pack-progress-track">
            <div
              class="pack-progress-fill"
              :class="{ 'progress-complete': stageProgress === 100 }"
              :style="{ width: stageProgress + '%' }"
            />
          </div>
        </div>

        <!-- Retour: blauer Lager-Hinweis ausgeblendet (Gruppe bucht über Pfeile in der Liste). -->

        <div
          v-if="isPackUnpackStage(activePackStage)"
          class="pack-return-stock-hint pack-unpack-warehouse-hint"
          role="note"
        >
          <p class="pack-return-stock-hint-title">{{ t('activities.packList.unpackWarehouseTitle') }}</p>
          <p class="pack-return-stock-hint-body text-muted">
            {{ t('activities.packList.unpackWarehouseBody') }}
            <router-link
              v-if="departmentId"
              :to="`/${departmentId}/materials`"
              class="pack-return-stock-hint-link"
            >{{ t('activities.packList.materialsLink') }}</router-link>
          </p>
        </div>

        <div v-if="jsWorkflowSummary.items > 0" class="js-workflow-summary">
          <span class="mat-source-badge">{{ t('activities.common.jsBadge') }}</span>
          <span>{{ t('activities.packList.jsSummaryPositions') }} <strong>{{ jsWorkflowSummary.items }}</strong></span>
          <span>{{ t('activities.packList.jsSummaryReceived') }} <strong>{{ jsWorkflowSummary.received }}</strong></span>
          <span>{{ t('activities.packList.jsSummaryReturned') }} <strong>{{ jsWorkflowSummary.returned }}</strong></span>
        </div>

        <div class="pack-panels">
          <div class="pack-panel pack-panel-left">
            <div
              class="pack-panel-header"
              :class="{ 'pack-panel-header-done': isPackConfirmedStage(activePackStage) }"
            >
              <span class="pack-panel-title">{{ activeStageConfig.leftLabel }}</span>
              <span class="pack-panel-count">{{ stageLeftHeaderCount }}</span>
            </div>
            <p
              v-if="hasActiveCrateTarget && isPackForwardToEventStage(activePackStage) && showPackOperateControls"
              class="pack-active-crate-banner"
              role="status"
            >
              {{ t('activities.packList.activeCrateAssignHint', { label: activePackTargetCrateLabel }) }}
            </p>
            <div
              v-if="stageLeftItems.length === 0 && !leftPanelHasKistenEventReturn && !leftPanelHasKistenUnpack"
              class="pack-panel-empty"
            >
              <template v-if="memberAwaitingMwPack">
                {{ t('activities.packList.memberPackPreviewLeftEmpty') }}
              </template>
              <template v-else-if="activePackStage === 'packed_transport_to' && stageRightHeaderCount > 0">
                {{ t('activities.packList.transportLeftEmptyAllOnRight') }}
              </template>
              <template v-else-if="activePackStage === 'packed_transport_to'">
                {{ t('activities.packList.transportLeftEmptyNothingYet') }}
              </template>
              <template v-else-if="isPackForwardToEventStage(activePackStage) && packedIssueWarehouseOnlyInContainers">
                {{ t('activities.packList.warehouseOnlyInContainers') }}
              </template>
              <template v-else>{{ t('activities.packList.allMoved') }}</template>
            </div>
            <div v-for="group in groupsLeft" :key="'l-' + group.categoryName" class="pack-group">
              <div class="pack-group-header" @click="toggleGroup('l-' + group.categoryName)">
                <span class="pack-group-name">{{ group.categoryName }}</span>
                <span class="pack-group-toggle">{{ collapsedGroups['l-' + group.categoryName] ? '▶' : '▼' }}</span>
              </div>
              <div v-if="!collapsedGroups['l-' + group.categoryName]" class="pack-group-items">
                <template v-for="pi in group.items" :key="pi.id">
                <PackCrateShellPackItemRow
                  v-if="showPackContainersUi && isCrateShellPackItem(pi, packContainers) && !isOrphanShellWithoutPackContainer(pi)"
                  :shell-pack-item="pi"
                  :stage-right-label="activeStageConfig.rightLabel"
                  :show-storage-location="showPackStorageLocation(activePackStage, 'left')"
                />
                <PackMaterialRow
                  v-else
                  :item="pi"
                  :show-storage-location="showPackStorageLocation(activePackStage, 'left')"
                  :show-linked-kiste="showPackStorageLocation(activePackStage, 'left')"
                >
                  <template #detail>
                    <PackMaterialRowDetail
                      :item="pi"
                      :stage="activePackStage"
                      :workflow-profile="packWorkflowProfile"
                      :stage-right-label="activeStageConfig.rightLabel"
                      :retour-accounting="
                        isPackUnpackStage(activePackStage)
                          ? retourAccountingForUnpackLoose(pi)
                          : undefined
                      "
                      side="left"
                      :loose-qty="looseQtyForPackItem(pi)"
                      :consumed-at-event="
                        pi.isConsumable && consumableShowsZeroOnStageLeft(pi)
                          ? consumableBookedConsumptionQty(pi)
                          : undefined
                      "
                      :qty-in-containers="qtyInContainersForItem(pi)"
                    />
                  </template>
                  <template #trailing>
                    <PackUnpackStoreControls
                      v-if="showPackForwardControls && isPackUnpackStage(activePackStage) && packIssueForwardMax(pi) > 0"
                      :qty="moveQtyInputs[pi.id] ?? packIssueForwardMax(pi)"
                      :max="packIssueForwardMax(pi)"
                      :disabled="movingId === pi.id"
                      :confirm-title="forwardMoveTitleForItem(pi)"
                      @update:qty="setMoveQtyForItem(pi.id, $event)"
                      @store="(q) => moveToNextStage(pi, q)"
                    />
                    <PackMoveControls
                      v-else-if="
                        showPackForwardControls &&
                        (showCrateAssignUpControls(pi)
                          ? crateAssignUpMax(pi) >= 1
                          : !isPackForwardToEventStage(activePackStage) || packIssueForwardMax(pi) > 0)
                      "
                      direction="forward"
                      :into-crate="showCrateAssignUpControls(pi)"
                      :qty="
                        moveQtyInputs[pi.id] ??
                        (showCrateAssignUpControls(pi) ? crateAssignUpMax(pi) : packIssueForwardMax(pi))
                      "
                      :max="packForwardMoveControlLimits(pi).max"
                      :input-max="packForwardMoveControlLimits(pi).inputMax"
                      :warn-if-below="packForwardMoveControlLimits(pi).warnIfBelow"
                      :disabled="movingId === pi.id || (showCrateAssignUpControls(pi) && containerMutationLoading)"
                      :forward-title="
                        showCrateAssignUpControls(pi)
                          ? assignUpTitleForItem(pi)
                          : forwardMoveTitleForItem(pi)
                      "
                      @update:qty="setMoveQtyForItem(pi.id, $event)"
                      @move="
                        (q) =>
                          showCrateAssignUpControls(pi)
                            ? onCrateAssignUpClick(pi, q)
                            : moveToNextStage(pi, q)
                      "
                    />
                  </template>
                  <template #info-extra>
                    <PackIssueQuickActions
                      v-if="showPackIssueForPackItem(pi)"
                      :is-consumable="pi.isConsumable"
                      :material-item-id="pi.materialItemId"
                      :show-consumption="showConsumableConsumptionForPackItem(pi)"
                      @consumed="emitConsumptionFromPackItem(pi)"
                      @loss="emitIssueWizard(pi, 'loss')"
                      @repair="emitIssueWizard(pi, 'repair')"
                    />
                  </template>
                </PackMaterialRow>
                </template>
              </div>
            </div>


            <PackStepCrateSection
              v-if="
                showPackContainersUi &&
                isPackForwardToEventStage(activePackStage) &&
                (packContainersSortedWarehouseOnlyVisible.length > 0 ||
                  (packContainers.length === 0 && canManageMaterials))
              "
              :preset="PACK_CRATE_SECTION_FORWARD_WAREHOUSE_LEFT"
              :show-empty-hint="packContainers.length === 0"
            >
              <PackStepContainerCard
                v-for="c in packContainersSortedWarehouseOnlyVisible"
                :key="'issue-' + c.id"
                :container="c"
                mode="warehouse_issue"
                :stage-right-label="activeStageConfig.rightLabel"
                :use-subsections="false"
                :show-storage-location="showPackStorageLocation(activePackStage, 'left')"
              />
            </PackStepCrateSection>

            <PackStepCrateSection
              v-if="
                showPackContainersUi &&
                isPackReturnPipelineStage(activePackStage) &&
                packContainersAtEventForReturnLeft.length > 0
              "
              :preset="PACK_CRATE_SECTION_RETURN_AT_EVENT_LEFT"
            >
              <PackStepContainerCard
                v-for="c in packContainersAtEventForReturnLeft"
                :key="'ret-cr-left-' + c.id"
                :container="c"
                mode="at_event_return"
              />
            </PackStepCrateSection>

            <PackStepCrateSection
              v-if="
                showPackContainersUi &&
                isPackUnpackStage(activePackStage) &&
                packContainersPendingUnpackLeft.length > 0
              "
              :preset="PACK_CRATE_SECTION_UNPACK_WAREHOUSE_LEFT"
            >
              <PackUnpackWarehouseContainerCard
                v-for="c in packContainersPendingUnpackLeft"
                :key="'unpack-cr-left-' + c.id"
                :container="c"
                variant="pending"
              />
            </PackStepCrateSection>

            <div
              v-if="isPackReturnOrUnpackWarehouseStage(activePackStage) && groupsNotTakenForReturn.length > 0"
              class="pack-workflow-section pack-workflow-section--not-taken"
            >
              <button
                type="button"
                class="pack-workflow-section-accordion"
                :aria-expanded="!isReturnSectionCollapsed('not-taken')"
                @click="toggleReturnSection('not-taken')"
              >
                <span class="pack-workflow-section-title">{{ t('activities.packList.sectionNotTakenForReturn') }}</span>
                <span class="pack-workflow-section-badge">{{ stageReturnNotTakenCount }}</span>
                <span class="pack-group-toggle">{{ isReturnSectionCollapsed('not-taken') ? '▶' : '▼' }}</span>
              </button>
              <div v-show="!isReturnSectionCollapsed('not-taken')" class="pack-workflow-section-accordion-body">
                <p class="pack-containers-at-event-hint text-muted">
                  {{
                    isPackUnpackStage(activePackStage)
                      ? t('activities.packList.hintNotTakenForUnpack')
                      : t('activities.packList.hintNotTakenForReturn')
                  }}
                </p>
                <div v-for="g in groupsNotTakenForReturn" :key="'nt-g-' + g.categoryName" class="pack-group">
                  <div class="pack-group-header pack-group-header-done pack-group-header-static">
                    <span class="pack-group-name">{{ g.categoryName }}</span>
                  </div>
                  <div class="pack-group-items">
                    <PackMaterialRow
                      v-for="pi in g.items"
                      :key="'nt-pi-' + pi.id"
                      :item="pi"
                      :show-storage-location="showPackStorageLocation(activePackStage, 'left')"
                      :show-linked-kiste="showPackStorageLocation(activePackStage, 'left')"
                    >
                      <template #detail>
                        <span class="pack-card-detail text-muted pack-card-detail-stack">
                          <span v-if="notTakenQtyForReturn(pi) > 0">
                            {{ t('activities.packList.notTakenForReturnQty', { n: notTakenQtyForReturn(pi) }) }}
                          </span>
                          <span v-if="notTakenToEventQtyForMaterial(pi.materialItemId) > 0">
                            {{
                              t('activities.packList.notTakenIssuedQty', {
                                n: notTakenToEventQtyForMaterial(pi.materialItemId),
                                issued: pi.quantityIssued ?? 0,
                              })
                            }}
                          </span>
                        </span>
                      </template>
                    </PackMaterialRow>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="pack-panel pack-panel-right">
            <div class="pack-panel-header pack-panel-header-done pack-panel-header--split">
              <div class="pack-panel-header-main">
                <span class="pack-panel-title">{{ activeStageConfig.rightLabel }}</span>
                <span class="pack-panel-count">{{ stageRightHeaderCount }}</span>
              </div>
              <div v-if="showPackContainersUi && showPackOperateControls && activePackStage === 'confirmed_packed'" class="pack-panel-header-actions">
                <button
                  type="button"
                  class="btn btn-xs btn-primary pack-add-container-btn"
                  :disabled="containerMutationLoading"
                  :title="t('activities.packList.addPackCrateSingleTitle')"
                  @click="openAddContainerModal"
                >
                  {{ t('activities.packList.addPackCrateSingleButton') }}
                </button>
              </div>
            </div>
            <PackCrateTargetPicker v-if="showPackCrateTargetPickerTop" />

            <PackStepCrateSection
              v-if="
                showPackContainersUi &&
                activePackStage === 'confirmed_packed' &&
                packContainersForConfirmedPackedRight.length > 0
              "
              :preset="PACK_CRATE_SECTION_CONFIRMED_PACKED_RIGHT"
              :show-hint="showPackOperateControls"
            >
              <PackStepContainerCard
                v-for="c in packContainersForConfirmedPackedRight"
                :key="'packed-cr-' + c.id"
                :container="c"
                mode="confirmed_packed_target"
              />
            </PackStepCrateSection>


            <div v-if="!rightPanelHasEventContent" class="pack-panel-empty">
              {{ t('activities.packList.rightPanelEmpty') }}
            </div>

            <PackStepMirrorSection
              v-if="
                isPackUnpackStage(activePackStage) &&
                (packContainersStoredForUnpackRight.length > 0 || groupsStoredLoose.length > 0)
              "
              :preset="PACK_MIRROR_SECTION_UNPACK_STORED"
            >
              <template v-if="packContainersStoredForUnpackRight.length > 0" #crates>
                <PackUnpackWarehouseContainerCard
                  v-for="c in packContainersStoredForUnpackRight"
                  :key="'unpack-cr-stored-' + c.id"
                  :container="c"
                  variant="stored"
                />
              </template>
              <template v-if="groupsStoredLoose.length > 0" #loose>
              <div v-for="g in groupsStoredLoose" :key="'stored-g-' + g.categoryName" class="pack-group">
                <div
                  class="pack-group-header pack-group-header-done"
                  @click="toggleGroup('stored-cat-' + g.categoryName)"
                >
                  <span class="pack-group-name">{{ g.categoryName }}</span>
                  <span class="pack-group-toggle">{{ collapsedGroups['stored-cat-' + g.categoryName] ? '▶' : '▼' }}</span>
                </div>
                <div v-if="!collapsedGroups['stored-cat-' + g.categoryName]" class="pack-group-items">
                  <PackMaterialRow
                    v-for="pi in g.items"
                    :key="'stored-pi-' + pi.id"
                    :item="pi"
                    :show-storage-location="showPackStorageLocation(activePackStage, 'right')"
                    :show-linked-kiste="showPackStorageLocation(activePackStage, 'right')"
                  >
                    <template #leading>
                      <PackUnpackUnstoreControls
                        v-if="showPackUnpackStoredMoveBack(pi)"
                        :qty="moveBackQtyInputs[pi.id] ?? rightQtyForMoveBack(pi)"
                        :max="rightQtyForMoveBack(pi)"
                        :disabled="movingId === pi.id"
                        @update:qty="setMoveBackQtyForItem(pi.id, $event)"
                        @move="(q) => unstoreLooseFromWarehouse(pi, q)"
                      />
                    </template>
                    <template #detail>
                      <PackMaterialRowDetail
                        :item="pi"
                        :stage="activePackStage"
                        :workflow-profile="packWorkflowProfile"
                        :stage-right-label="activeStageConfig.rightLabel"
                        side="right"
                        use-detail-stack
                      />
                    </template>
                  </PackMaterialRow>
                </div>
              </div>
              </template>
            </PackStepMirrorSection>


            <PackStepMirrorSection
              v-if="
                isPackReturnStage(activePackStage) &&
                (packContainersReturnedForReturnRight.length > 0 || groupsReturned.length > 0)
              "
              :preset="PACK_MIRROR_SECTION_RETURN_DONE"
            >
              <template v-if="packContainersReturnedForReturnRight.length > 0" #crates>
                    <PackStepContainerCard
                      v-for="c in packContainersReturnedForReturnRight"
                      :key="'ret-mirror-' + c.id"
                      :container="c"
                      mode="at_event_return_mirror"
                      container-dom-id-prefix="pack-container-returned-"
                    />
              </template>
              <template v-if="groupsReturned.length > 0" #loose>
                  <div v-for="g in groupsReturned" :key="'ret-g-' + g.categoryName" class="pack-group">
                    <div
                      class="pack-group-header pack-group-header-done"
                      @click="toggleGroup('ret-cat-' + g.categoryName)"
                    >
                      <span class="pack-group-name">{{ g.categoryName }}</span>
                      <span class="pack-group-toggle">{{ collapsedGroups['ret-cat-' + g.categoryName] ? '▶' : '▼' }}</span>
                    </div>
                    <div v-if="!collapsedGroups['ret-cat-' + g.categoryName]" class="pack-group-items">
                      <PackMaterialRow
                        v-for="pi in g.items"
                        :key="'ret-pi-' + pi.id"
                        :item="pi"
                        :show-storage-location="showPackStorageLocation(activePackStage, 'right')"
                        :show-linked-kiste="showPackStorageLocation(activePackStage, 'right')"
                      >
                        <template #leading>
                          <PackMoveControls
                            v-if="showPackBackwardControls"
                            direction="back"
                            :qty="moveBackQtyInputs[pi.id] ?? rightQtyForMoveBack(pi)"
                            :max="rightQtyForMoveBack(pi)"
                            :disabled="movingId === pi.id"
                            :back-title="t('activities.common.backTitle')"
                            @update:qty="setMoveBackQtyForItem(pi.id, $event)"
                            @move="(q) => moveToPrevStage(pi, q)"
                          />
                        </template>
                        <template #detail>
                          <PackMaterialRowDetail
                            :item="pi"
                            :stage="activePackStage"
                            :workflow-profile="packWorkflowProfile"
                            :stage-right-label="activeStageConfig.rightLabel"
                            :consumed-at-event="pi.isConsumable ? consumableBookedConsumptionQty(pi) : undefined"
                            side="right"
                            use-detail-stack
                          />
                        </template>
                        <template #info-extra>
                          <PackIssueQuickActions
                            v-if="showPackIssueForPackItem(pi)"
                            :is-consumable="pi.isConsumable"
                            :material-item-id="pi.materialItemId"
                            :show-consumption="showConsumableConsumptionForPackItem(pi)"
                            @consumed="emitConsumptionFromPackItem(pi)"
                            @loss="emitIssueWizard(pi, 'loss')"
                            @repair="emitIssueWizard(pi, 'repair')"
                          />
                        </template>
                      </PackMaterialRow>
                    </div>
                  </div>
              </template>
            </PackStepMirrorSection>


            <div
              v-if="isPackReturnStage(activePackStage) && groupsConsumableOverview.length > 0"
              class="pack-workflow-section pack-workflow-section--consumed"
            >
              <button
                type="button"
                class="pack-workflow-section-accordion"
                :aria-expanded="!isReturnSectionCollapsed('consumption')"
                @click="toggleReturnSection('consumption')"
              >
                <span class="pack-workflow-section-title">{{ t('activities.packList.sectionConsumedForReturn') }}</span>
                <span class="pack-workflow-section-badge">{{ stageConsumableOverviewCount }}</span>
                <span class="pack-group-toggle">{{ isReturnSectionCollapsed('consumption') ? '▶' : '▼' }}</span>
              </button>
              <div v-show="!isReturnSectionCollapsed('consumption')" class="pack-workflow-section-accordion-body">
                <p class="pack-containers-at-event-hint text-muted">
                  {{
                    canManageMaterials
                      ? t('activities.packList.hintConsumableOverviewMw')
                      : t('activities.packList.hintConsumableOverviewUser')
                  }}
                </p>
                <div v-for="g in groupsConsumableOverview" :key="'cons-g-' + g.categoryName" class="pack-group">
                  <div class="pack-group-header pack-group-header-done pack-group-header-static">
                    <span class="pack-group-name">{{ g.categoryName }}</span>
                  </div>
                  <div class="pack-group-items">
                    <PackMaterialRow
                      v-for="pi in g.items"
                      :key="'cons-pi-' + pi.id"
                      :item="pi"
                      :show-storage-location="showPackStorageLocation(activePackStage, 'right')"
                      :show-linked-kiste="showPackStorageLocation(activePackStage, 'right')"
                    >
                      <template #detail>
                        <span class="pack-card-detail text-muted">
                          {{ consumableOverviewDetailText(pi) }}
                        </span>
                      </template>
                      <template #info-extra>
                        <PackIssueQuickActions
                          v-if="showPackIssueForPackItem(pi) || consumableConsumptionRemaining(pi) > 0"
                          :is-consumable="true"
                          :material-item-id="pi.materialItemId"
                          :show-consumption="showConsumableConsumptionForPackItem(pi)"
                          @consumed="emitConsumptionFromPackItem(pi)"
                        />
                      </template>
                    </PackMaterialRow>
                  </div>
                </div>
              </div>
            </div>



            <PackStepMirrorSection
              v-if="showRightProgressMirrorSection"
              :preset="rightProgressMirrorPreset"
              :show-crates-hint="showPackOperateControls && isPackForwardToEventStage(activePackStage)"
            >
              <template v-if="packContainersWithIssuedAtEvent.length > 0" #crates>
                <PackStepContainerCard
                  v-for="c in packContainersWithIssuedAtEvent"
                  :key="'mirror-cr-' + activePackStage + '-' + c.id"
                  :container="c"
                  mode="warehouse_issue_mirror"
                  :stage-right-label="activeStageConfig.rightLabel"
                  :container-dom-id-prefix="rightProgressMirrorPreset.containerDomIdPrefix"
                  :use-subsections="false"
                  :show-storage-location="showPackStorageLocation(activePackStage, 'right')"
                />
              </template>
              <template v-if="stageRightLooseMirrorItems.length > 0" #loose>
                <p
                  v-if="hasActiveCrateTarget && showPackOperateControls && isPackForwardToEventStage(activePackStage)"
                  class="pack-active-crate-banner pack-active-crate-banner--inline"
                  role="status"
                >
                  {{
                    t('activities.packList.activeCrateAssignHintLooseAtEvent', {
                      label: activePackTargetCrateLabel,
                    })
                  }}
                </p>
                <div v-for="g in groupsRightMirrorLoose" :key="'mirror-loose-g-' + activePackStage + '-' + g.categoryName" class="pack-group">
                  <div
                    class="pack-group-header pack-group-header-done"
                    @click="toggleGroup('evt-loose-cat-' + g.categoryName)"
                  >
                    <span class="pack-group-name">{{ g.categoryName }}</span>
                    <span class="pack-group-toggle">{{ collapsedGroups['evt-loose-cat-' + g.categoryName] ? '▶' : '▼' }}</span>
                  </div>
                  <div v-if="!collapsedGroups['evt-loose-cat-' + g.categoryName]" class="pack-group-items">
                    <PackMaterialRow
                      v-for="pi in g.items"
                      :key="'evt-pi-' + pi.id"
                      :item="pi"
                      :show-storage-location="showPackStorageLocation(activePackStage, 'right')"
                      :show-linked-kiste="showPackStorageLocation(activePackStage, 'right')"
                    >
                      <template #leading>
                        <PackMoveControls
                          v-if="
                            showPackBackwardControls &&
                            !isPackUnpackStage(activePackStage) &&
                            !showCrateAssignUpControlsLooseAtEvent(pi)
                          "
                          direction="back"
                          :qty="moveBackQtyInputs[pi.id] ?? rightQtyForMoveBack(pi)"
                          :max="rightQtyForMoveBack(pi)"
                          :disabled="movingId === pi.id"
                          :back-title="t('activities.common.backTitle')"
                          @update:qty="setMoveBackQtyForItem(pi.id, $event)"
                          @move="(q) => moveToPrevStage(pi, q)"
                        />
                      </template>
                      <template #trailing>
                        <PackMoveControls
                          v-if="
                            showPackForwardControls &&
                            !isPackUnpackStage(activePackStage) &&
                            showCrateAssignUpControlsLooseAtEvent(pi)
                          "
                          direction="forward"
                          :into-crate="true"
                          :qty="moveQtyInputs[pi.id] ?? crateAssignLooseAtEventMax(pi)"
                          :max="crateAssignLooseAtEventMax(pi)"
                          :disabled="movingId === pi.id || containerMutationLoading"
                          :forward-title="assignUpTitleForItem(pi, 'loose-at-event')"
                          @update:qty="setMoveQtyForItem(pi.id, $event)"
                          @move="(q) => onCrateAssignUpClick(pi, q, 'loose-at-event')"
                        />
                      </template>
                      <template #detail>
                        <PackMaterialRowDetail
                          :item="pi"
                          :stage="activePackStage"
                          :workflow-profile="packWorkflowProfile"
                          :stage-right-label="activeStageConfig.rightLabel"
                          side="right"
                          use-detail-stack
                          :loose-issued-at-event="looseQtyOnRightMirror(pi)"
                          :loose-qty="looseQtyOnRightMirror(pi)"
                          :consumed-at-event="
                            pi.isConsumable ? consumableBookedConsumptionQty(pi) : undefined
                          "
                          :qty-in-containers="
                            activePackStage === 'packed_transport_to'
                              ? transportToQtyInContainersForMaterial(pi.materialItemId)
                              : issuedQtyInContainersForMaterial(pi.materialItemId)
                          "
                        />
                      </template>
                      <template #info-extra>
                        <PackIssueQuickActions
                          v-if="showPackIssueForPackItem(pi)"
                          :is-consumable="pi.isConsumable"
                          :material-item-id="pi.materialItemId"
                          :show-consumption="showConsumableConsumptionForPackItem(pi)"
                          @consumed="emitConsumptionFromPackItem(pi)"
                          @loss="emitIssueWizard(pi, 'loss')"
                          @repair="emitIssueWizard(pi, 'repair')"
                        />
                      </template>
                    </PackMaterialRow>
                  </div>
                </div>
              </template>
            </PackStepMirrorSection>

            <PackStepMirrorSection
              v-if="
                showPackContainersUi &&
                activePackStage === 'confirmed_packed' &&
                rightLoseSectionHasItems
              "
              :preset="PACK_MIRROR_SECTION_CONFIRMED_PACKED_LOOSE"
            >
              <template #loose>
              <div
                v-if="ohneBehaelterGroups.length > 0"
                class="pack-group pack-group-ohne-outer"
                :class="{ 'pack-group-ohne-outer--loose-target': activePackTarget?.kind === 'loose' }"
              >
                <div class="pack-group-header pack-group-header-done" @click="toggleGroup('r-ohne-behaelter')">
                  <span class="pack-group-name">{{ t('activities.packList.groupWithoutContainer') }}</span>
                  <span class="pack-group-toggle">{{ collapsedGroups['r-ohne-behaelter'] ? '▶' : '▼' }}</span>
                </div>
                <div v-if="!collapsedGroups['r-ohne-behaelter']" class="pack-group-ohne-inner">
                  <div
                    v-for="cat in ohneBehaelterGroups"
                    :key="'ohne-' + cat.categoryName"
                    class="pack-group pack-group-sub"
                  >
                    <div
                      class="pack-group-header pack-group-header-done pack-group-header-sub"
                      @click="toggleGroup(ohneCatCollapseKey(cat.categoryName))"
                    >
                      <span class="pack-group-name">{{ cat.categoryName }}</span>
                      <span class="pack-group-toggle">{{
                        collapsedGroups[ohneCatCollapseKey(cat.categoryName)] ? '▶' : '▼'
                      }}</span>
                    </div>
                    <div v-if="!collapsedGroups[ohneCatCollapseKey(cat.categoryName)]" class="pack-group-items">
                      <PackMaterialRow
                        v-for="pi in cat.items"
                        :key="pi.id"
                        :item="pi"
                        :show-storage-location="showPackStorageLocation(activePackStage, 'right')"
                        :show-linked-kiste="showPackStorageLocation(activePackStage, 'right')"
                      >
                                            <template #leading>
                                              <PackMoveControls
                                                v-if="showPackMoveBackControlsForItem(pi)"
                                                direction="back"
                                                :into-crate="showCrateAssignUpControls(pi)"
                                                :qty="
                                                  showCrateAssignUpControls(pi)
                                                    ? (moveQtyInputs[pi.id] ?? crateAssignUpMax(pi))
                                                    : (moveBackQtyInputs[pi.id] ?? rightQtyForMoveBack(pi))
                                                "
                                                :max="
                                                  showCrateAssignUpControls(pi)
                                                    ? crateAssignUpMax(pi)
                                                    : rightQtyForMoveBack(pi)
                                                "
                                                :disabled="
                                                  movingId === pi.id ||
                                                  (showCrateAssignUpControls(pi) && containerMutationLoading)
                                                "
                                                :back-title="t('activities.common.backTitle')"
                                                :forward-title="assignUpTitleForItem(pi)"
                                                @update:qty="
                                                  showCrateAssignUpControls(pi)
                                                    ? setMoveQtyForItem(pi.id, $event)
                                                    : setMoveBackQtyForItem(pi.id, $event)
                                                "
                                                @move="
                                                  (q) =>
                                                    showCrateAssignUpControls(pi)
                                                      ? onCrateAssignUpClick(pi, q)
                                                      : moveToPrevStage(pi, q)
                                                "
                                              />
                                            </template>
                                            <template #detail>
                                              <PackMaterialRowDetail
                                                :item="pi"
                                                :stage="activePackStage"
                                                :workflow-profile="packWorkflowProfile"
                                                :stage-right-label="activeStageConfig.rightLabel"
                                                side="right"
                                                :loose-qty="looseQtyForPackItem(pi)"
                                                :qty-in-containers="qtyInContainersForItem(pi)"
                                                :loose-issued-at-event="looseIssuedAtEvent(pi)"
                                                use-detail-stack
                                              />
                                            </template>
                                            <template #info-extra>
                                              <PackIssueQuickActions
                                                v-if="showPackIssueForPackItem(pi)"
                                                :is-consumable="pi.isConsumable"
                                                :material-item-id="pi.materialItemId"
                                                :show-consumption="showConsumableConsumptionForPackItem(pi)"
                                                @consumed="emitConsumptionFromPackItem(pi)"
                                                @loss="emitIssueWizard(pi, 'loss')"
                                                @repair="emitIssueWizard(pi, 'repair')"
                                              />
                                            </template>
                                            <template #trailing>
                                              <button
                                                v-if="showShellCrateCheckButton(pi)"
                                                type="button"
                                                class="btn-outline btn-sm pack-shell-crate-check-btn"
                                                :disabled="movingId === pi.id || shellForwardSubmitting"
                                                @click="openShellCrateCheckOnlyModal(pi)"
                                              >
                                                {{ shellCrateCheckButtonLabel(pi) }}
                                              </button>
                                            </template>
                      </PackMaterialRow>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="loosePackItemsPartial.length > 0" class="pack-group">
                <div class="pack-group-header pack-group-header-done" @click="toggleGroup('r-loose-partial')">
                  <span class="pack-group-name">{{ t('activities.packList.groupAlsoInContainers') }}</span>
                  <span class="pack-group-toggle">{{ collapsedGroups['r-loose-partial'] ? '▶' : '▼' }}</span>
                </div>
                <div v-if="!collapsedGroups['r-loose-partial']" class="pack-group-items">
                  <PackMaterialRow
                    v-for="pi in loosePackItemsPartial"
                    :key="'lp-' + pi.id"
                    :item="pi"
                    :show-storage-location="showPackStorageLocation(activePackStage, 'right')"
                    :show-linked-kiste="showPackStorageLocation(activePackStage, 'right')"
                  >
                                        <template #leading>
                                          <PackMoveControls
                                            v-if="showPackMoveBackControlsForItem(pi)"
                                            direction="back"
                                            :into-crate="showCrateAssignUpControls(pi)"
                                            :qty="
                                              showCrateAssignUpControls(pi)
                                                ? (moveQtyInputs[pi.id] ?? crateAssignUpMax(pi))
                                                : (moveBackQtyInputs[pi.id] ?? rightQtyForMoveBack(pi))
                                            "
                                            :max="
                                              showCrateAssignUpControls(pi)
                                                ? crateAssignUpMax(pi)
                                                : rightQtyForMoveBack(pi)
                                            "
                                            :disabled="
                                              movingId === pi.id ||
                                              (showCrateAssignUpControls(pi) && containerMutationLoading)
                                            "
                                            :back-title="t('activities.common.backTitle')"
                                            :forward-title="assignUpTitleForItem(pi)"
                                            @update:qty="
                                              showCrateAssignUpControls(pi)
                                                ? setMoveQtyForItem(pi.id, $event)
                                                : setMoveBackQtyForItem(pi.id, $event)
                                            "
                                            @move="
                                              (q) =>
                                                showCrateAssignUpControls(pi)
                                                  ? onCrateAssignUpClick(pi, q)
                                                  : moveToPrevStage(pi, q)
                                            "
                                          />
                                        </template>
                                        <template #detail>
                                          <PackMaterialRowDetail
                                            :item="pi"
                                            :stage="activePackStage"
                                            :workflow-profile="packWorkflowProfile"
                                            :stage-right-label="activeStageConfig.rightLabel"
                                            side="right"
                                            :loose-qty="looseQtyForPackItem(pi)"
                                            :qty-in-containers="qtyInContainersForItem(pi)"
                                            :loose-issued-at-event="looseIssuedAtEvent(pi)"
                                            use-detail-stack
                                          />
                                        </template>
                                        <template #info-extra>
                                          <PackIssueQuickActions
                                            v-if="showPackIssueForPackItem(pi)"
                                            :is-consumable="pi.isConsumable"
                                            :material-item-id="pi.materialItemId"
                                            :show-consumption="showConsumableConsumptionForPackItem(pi)"
                                            @consumed="emitConsumptionFromPackItem(pi)"
                                            @loss="emitIssueWizard(pi, 'loss')"
                                            @repair="emitIssueWizard(pi, 'repair')"
                                          />
                                        </template>
                                        <template #trailing>
                                          <button
                                            v-if="showShellCrateCheckButton(pi)"
                                            type="button"
                                            class="btn-outline btn-sm pack-shell-crate-check-btn"
                                            :disabled="movingId === pi.id || shellForwardSubmitting"
                                            @click="openShellCrateCheckOnlyModal(pi)"
                                          >
                                            {{ shellCrateCheckButtonLabel(pi) }}
                                          </button>
                                        </template>
                  </PackMaterialRow>
                </div>
              </div>
              </template>
            </PackStepMirrorSection>


          </div>
        </div>
      </div>
      </template>

      <!-- Modal: Behälter anlegen -->
      <div
        v-if="showAddContainerModal"
        class="pack-modal-backdrop"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pack-modal-add-title"
        @click.self="showAddContainerModal = false"
      >
        <div class="pack-modal" @click.stop>
          <h3 id="pack-modal-add-title" class="pack-modal-title">{{ t('activities.packList.modalAddTitle') }}</h3>
          <p class="pack-modal-hint pack-modal-hint--sm text-muted" v-html="t('activities.packList.modalAddHint')"></p>
          <div v-if="stockBatchesLoading" class="pack-modal-loading text-muted">{{ t('activities.packList.modalLoadingBatches') }}</div>
          <template v-else>
            <label v-if="availableStockBatches.length > 0" class="pack-modal-label">
              <span>{{ t('activities.packList.modalBatchLabel') }}</span>
              <select v-model="selectedStockBatchId" class="form-select">
                <option value="">{{ t('activities.packList.modalSelectPlaceholder') }}</option>
                <option v-for="b in availableStockBatches" :key="b.id" :value="b.id">
                  {{ containerBatchOptionLabel(b) }}
                </option>
              </select>
            </label>
            <p v-else class="pack-modal-empty text-muted">
              {{ t('activities.packList.modalNoBatch') }}
            </p>
          </template>
          <div class="pack-modal-actions">
            <button type="button" class="btn-outline btn-sm" @click="showAddContainerModal = false">{{ t('activities.common.cancel') }}</button>
            <button
              type="button"
              class="btn-primary btn-sm"
              :disabled="containerMutationLoading || stockBatchesLoading || !canSubmitAddContainer"
              @click="submitAddContainer"
            >
              {{ t('activities.packList.modalAdd') }}
            </button>
          </div>
        </div>
      </div>

    </template>

    <PackCrateShellForwardModal
      :open="shellForwardModalOpen"
      :label="shellForwardLabel"
      :move-qty="shellForwardMoveQty"
      :sections="shellForwardSections"
      :department-id="departmentId"
      :container-batch-id="shellForwardContainerBatchId"
      :loose-stock-by-mid="shellForwardLooseStock"
      :stock-loading="shellForwardStockLoading"
      :history-replenish-by-key="shellForwardHistoryReplenishByKey"
      :history-prefill-hint="shellForwardHistoryPrefillHint"
      :can-report-issues="showPackIssueActions"
      :group-mode="shellForwardGroupMode"
      :check-only="shellForwardCheckOnlyMode"
      :submit-error="shellForwardSubmitError"
      :submitting="shellForwardSubmitting"
      :empty-hint="shellForwardEmptyHint"
      :embedded-issues-by-line-key="shellForwardEmbeddedIssuesByLineKey"
      :repack-issue-reviews="shellForwardRepackIssueReviews"
      :orphan-issues="shellForwardOrphanIssues"
      :initial-line-reviews="shellForwardInitialLineReviews"
      :pack-item-id="shellForwardItem?.id ?? null"
      @cancel="closeShellForwardModal"
      @submit="onShellForwardSubmit"
      @set-repack-review="onShellForwardRepackReview"
    />

    <PackCrateShellBackModal
      v-if="shellBackItem"
      :open="shellBackModalOpen"
      :shell-pack-item="shellBackItem"
      :move-qty="shellBackMoveQty"
      :from-stage-label="shellBackFromLabel"
      :to-stage-label="shellBackToLabel"
      :label="shellBackLabel"
      :peek-sections="shellBackPeekSections"
      :deviations="shellBackDeviations"
      :last-check-date-label="shellBackLastCheckDateLabel"
      :acknowledged="shellBackAcknowledged"
      :submitting="shellBackSubmitting"
      @update:acknowledged="shellBackAcknowledged = $event"
      @cancel="closeShellBackModal"
      @confirm="onShellBackConfirm"
    />

    <PhysicalComboIssueComponentModal
      :open="physicalComboIssueModalOpen"
      :loading="physicalComboIssueModalLoading"
      :issue-type="physicalComboIssueModalIssueType"
      :shell-pack-item="physicalComboIssueModalPi"
      :sections="physicalComboIssueModalSections"
      @cancel="closePhysicalComboIssueModal"
      @confirm="onPhysicalComboIssueConfirm"
    />

    <PackReturnCrateModal
      v-if="returnCrateModalContainer"
      :open="returnCrateModalOpen"
      :container-label="returnCrateModalContainer.label"
      :contents-loading="returnCrateModalContentsLoading"
      :contents-error="returnCrateModalContentsError"
      :no-linked-batch="returnCrateModalNoLinkedBatch"
      :partition="returnCrateModalPartition"
      :lines="returnCrateModalLines"
      :not-taken-reminders="[]"
      :not-taken-line="() => ''"
      :can-report-issues="showPackIssueActions"
      :can-report-consumption="showPackConsumptionActions"
      :submitting="returnCrateModalSubmitting"
      :submit-disabled="returnCrateModalSubmitDisabled"
      @update:lines="returnCrateModalLines = $event"
      @report-loss="(mid, qty) => emitIssueWizardByMaterialId(mid, 'loss', qty)"
      @report-repair="(mid, qty) => emitIssueWizardByMaterialId(mid, 'repair', qty)"
      @report-consumption="
        (mid, name) => emitConsumptionForMaterialId(mid, { materialName: name })
      "
      @cancel="closeReturnCrateModal"
      @submit="onReturnCrateModalSubmit"
    />
  </div>
</template>

<script setup lang="ts">
defineOptions({ name: 'ActivityPackListTab' })
import { computed, nextTick, provide, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import type { ActivityApiType, ActivityIssueReportRow, ActivityItemRow, ActivityTransitionRow } from '@/api/activities'
import ActivityMaterialAvailabilityLookup from '@/components/activities/ActivityMaterialAvailabilityLookup.vue'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'
import type { MaterialScopeTab } from '@/components/activities/shared/activityMaterialAvailabilityScope'
import { getActivityHistory, getActivityIssues, getActivityItems, createActivityIssue } from '@/api/activities'
import {
  getPackCrateCheckLooseStock,
  postPackCrateCheck,
  type PackCrateCheckRequest,
} from '@/api/activityPackCrateCheck'
import PackCrateShellBackModal from '@/components/activities/PackCrateShellBackModal.vue'
import PackReturnCrateModal, {
  type ReturnCrateLineEdit,
  type ReturnCratePartitionView,
} from '@/components/activities/PackReturnCrateModal.vue'
import PackIssueQuickActions from '@/components/activities/PackIssueQuickActions.vue'
import PackMaterialRow from '@/components/activities/PackMaterialRow.vue'
import PackMaterialRowDetail from '@/components/activities/PackMaterialRowDetail.vue'
import PackMoveControls from '@/components/activities/PackMoveControls.vue'
import PackUnpackStoreControls from '@/components/activities/PackUnpackStoreControls.vue'
import PackUnpackUnstoreControls from '@/components/activities/PackUnpackUnstoreControls.vue'
import {
  type PackStage,
  getBackendStage as computeBackendStage,
  getStageLeftQty as computeStageLeftQty,
  getStageRightQty as computeStageRightQty,
  getStageTotalQty as computeStageTotalQty,
  groupActivityPackItemsByCategory,
  isPackConfirmedStage,
  isPackForwardToEventStage,
  isPackReturnStage,
  isPackReturnPipelineStage,
  isPackLogisticsReturnStage,
  isPackReturnOrUnpackWarehouseStage,
  isPackUnpackStage,
  packStageKeysForProfileAndRole,
  showPackStorageLocation,
  workflowTargetStatusForStage,
  resolvePackWorkflowTransitionStage,
  isPackWorkflowStatusToEventStage,
  isPackWorkflowStatusToReturnedStage,
  activityStatusRevertTarget,
  isPackWorkflowRevertFromReturnedStage,
} from '@/components/activities/packStageQuantities'
import {
  isPackCrateCheckStage,
  packCrateCheckLegForStage,
  isPackReturnCrateCheckStage,
  crateCheckSnapshotKey,
  type PackCrateCheckLeg,
} from '@/components/activities/packCrateCheckLeg'
import {
  consumedQtyForMaterial as consumedQtyFromIssues,
  lossQtyForMaterial as lossQtyFromIssues,
  repairQtyForMaterial as repairQtyFromIssues,
  notTakenToEventQtyForMaterial as notTakenToEventQtyFromIssues,
  packRetourAccountingSnapshot,
  qtyAttributedToIssuedLine,
  type PackRetourAccounting,
} from '@/components/activities/packNotTakenHelpers'
import {
  autoPackStageForProfile,
  packWorkflowProfileForActivityType,
  showPackContainersForProfile,
} from '@/components/activities/packWorkflowProfile'
import { activityTransitionActionLabel } from '@/components/activities/activityTransitionLabels'
import PackCrateShellForwardModal from '@/components/activities/PackCrateShellForwardModal.vue'
import PackCrateShellPackItemRow from '@/components/activities/PackCrateShellPackItemRow.vue'
import PackCrateTargetPicker from '@/components/activities/PackCrateTargetPicker.vue'
import PackStepContainerCard from '@/components/activities/PackStepContainerCard.vue'
import PackStepCrateSection from '@/components/activities/PackStepCrateSection.vue'
import PackStepMirrorSection from '@/components/activities/PackStepMirrorSection.vue'
import PackUnpackWarehouseContainerCard from '@/components/activities/PackUnpackWarehouseContainerCard.vue'
import {
  PACK_CRATE_SECTION_CONFIRMED_PACKED_RIGHT,
  PACK_CRATE_SECTION_FORWARD_WAREHOUSE_LEFT,
  PACK_CRATE_SECTION_RETURN_AT_EVENT_LEFT,
  PACK_CRATE_SECTION_UNPACK_WAREHOUSE_LEFT,
  PACK_MIRROR_SECTION_CONFIRMED_PACKED_LOOSE,
  PACK_MIRROR_SECTION_FORWARD_AT_EVENT,
  PACK_MIRROR_SECTION_TRANSPORT_BACK_DONE,
  packMirrorSectionPresetForRight,
  PACK_MIRROR_SECTION_RETURN_DONE,
  PACK_MIRROR_SECTION_UNPACK_STORED,
} from '@/components/activities/packStepUi'
import { confirmWorkflowStatusTransition } from '@/components/activities/usePackWorkflowConfirm'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'
import {
  applyCountedQtyToReview,
  defaultLineReview,
  shellForwardExpectedQty,
  shellForwardLineKey,
  type ShellForwardCheckLine,
  type ShellForwardLineReview,
} from '@/components/activities/packCrateForwardCheck'
import {
  buildShellCrateBackDeviations,
  crateShellForwardPeekSections,
  crateShellPeekSectionsForPackItem,
  isCrateShellPackItem,
  isNonActionableContainerLine,
  isPackContainerMergedIntoStageLeftRow,
  packContainerItemSectionsWithReality,
  packShellContainerForPackItem,
  isPhysicalComboAsSet,
  isOrphanShellWithoutPackContainer as isOrphanShellWithoutPackContainerRow,
  hideShellPackItemOnConfirmedPackedLeft,
  packContainerVisibleOnConfirmedPackedRight,
  peekSectionsForShellContainer,
  linkedShellCombosNeedingPackContainer,
  crateShellExcludedFromLooseForwardList,
} from '@/components/activities/packShellCrateHelpers'
import { isPhysicalComboPackItem } from '@/components/activities/packMaterialDisplay'
import type { ComboComponent } from '@/api/materials'
import { getComboComponents } from '@/api/materials'
import type { RackContentsItem } from '@/api/storageLocations'
import PackCrateShellInlinePanel, {
  type PackCrateShellPeekSection,
} from '@/components/activities/PackCrateShellInlinePanel.vue'
import PhysicalComboIssueComponentModal from '@/components/activities/PhysicalComboIssueComponentModal.vue'
import {
  findPhysicalComboShellPackItem,
  physicalComboHasSelectableIssueComponents,
  type PackIssueWizardEmitPayload,
  type PhysicalComboIssueSelection,
} from '@/components/activities/physicalComboIssueFlow'
import {
  indexLatestCrateCheckByPackItemId,
  indexLatestCrateCheckByPackItemAndLeg,
  overlayForContainerMaterial,
  containerLineIssueFraction,
  displayQtyInCrateAfterCheck,
  type CrateCheckSnapshot,
  type CrateCheckLineOverlay,
} from '@/components/activities/packCrateCheckReality'
import {
  buildGroupPrefillLineReviewsFromSnapshot,
  formatGroupCrateCheckPrefillHint,
} from '@/components/activities/packCrateCheckPrefill'
import {
  getPackItems,
  postInitPackItems,
  postMoveAllPackItems,
  postMoveBackPackItem,
  postMovePackItem,
  type ActivityPackItem,
  type PackMoveStage,
} from '@/api/activityPackItems'
import {
  createActivityPackContainer,
  createActivityPackContainerItem,
  deleteActivityPackContainer,
  deleteActivityPackContainerItem,
  getActivityPackContainerItems,
  getActivityPackContainers,
  issueAllPackContainerItems,
  returnAllPackContainerItems,
  unissueAllPackContainerItems,
  updateActivityPackContainerItem,
  type ActivityPackContainer,
  type ActivityPackContainerItem,
} from '@/api/activityContainers'
import {
  getContainerBatchContents,
  getContainerBatches,
  type ContainerBatch,
} from '@/api/storageLocations'
import { useConfirm } from '@/composables/useConfirm'
import { useDepartmentMemberRole } from '@/composables/useDepartmentMemberRole'
import { useBackgroundPoll } from '@/composables/useBackgroundPoll'
import { useToast } from '@/composables/useToast'
import { packItemsLiveSyncSignature } from '@/utils/packItemsLiveSync'

const { t, te, locale } = useI18n()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()
const { canManageMaterials } = useDepartmentMemberRole()
const authStore = useAuthStore()
const packCrateCheckUserId = computed(() => (authStore.userId ?? '').trim())

/** Mehrere MW/Geräte/Sessions: Packliste im Hintergrund aktualisieren (ohne Lade-Overlay). */
const PACK_LIST_POLL_MS = 4000

function isPackQtyInputFocused(): boolean {
  const el = document.activeElement
  return el instanceof HTMLInputElement && el.closest('.activity-pack-list-tab') != null
}

function isPackListInteractionBusy(): boolean {
  return (
    loading.value ||
    initLoading.value ||
    movingId.value !== null ||
    containerMutationLoading.value ||
    containerBulkLoadingId.value !== null ||
    isPackQtyInputFocused()
  )
}

async function refreshPackListSilent(): Promise<void> {
  if (isPackListInteractionBusy()) return
  try {
    const prevSig = packItemsLiveSyncSignature(packItems.value)
    const items = await getPackItems(props.activityId)
    const nextSig = packItemsLiveSyncSignature(items)
    if (prevSig === nextSig) return
    packItems.value = items
    initMoveQtyInputs()
    await loadContainersData()
    await refreshCrateCheckSnapshots()
    emit('activityItemsChanged')
  } catch {
    /* Poll-Fehler ignorieren — nächster Tick */
  }
}

function containerBatchOptionLabel(b: ContainerBatch): string {
  const base = (b.display_label || b.label || b.material_name || t('activities.common.crate')).trim()
  if (b.storage_empty === true) return `${base} ${t('activities.packList.batchEmptySuffix')}`
  if (b.storage_empty === false) return `${base} ${t('activities.packList.batchWithContentSuffix')}`
  return base
}

const props = withDefaults(
  defineProps<{
    activityId: string
    /** Für Auswahl bestehender Lager-Kisten (GET /container-batches) */
    departmentId?: string
    status: string
    /** activity | camp | event — steuert Quick- vs. Logistik-Packworkflow */
    activityType?: string
    /** Anzeigename des Anlasses (Bestätigungsdialoge) */
    activityName?: string
    packListEditable: boolean
    transitions: ActivityTransitionRow[]
    /** Meldungen Verlust/Reparatur (MW in Retour; Gruppe nur «Am Event») */
    canReportIssues?: boolean
    /** Verbrauch buchen (Gruppe/Leiter ab «Am Event») */
    canReportConsumption?: boolean
    /** Parent erhöht nach Verbrauchsbuchung → Packliste neu laden */
    reloadToken?: number
    /** Parent erhöht wenn Verbrauchs-Modal ohne Buchung geschlossen wurde */
    consumptionModalCancelledToken?: number
    /** Parent: «Retour ohne Verbrauch» nach Retour-Pfeil */
    consumptionModalReturnWithoutConsumptionToken?: number
    /** packing/gepackt + can_edit_activity_material: Hinzu-Material in der Packliste (nicht Material-Tab) */
    canAddActivityMaterial?: boolean
    /** Nachlieferung für Verbrauch (auch in Retour / Ausgepackt) */
    canRequestConsumableNachbuchung?: boolean
    activityTypeForMaterialAdd?: ActivityApiType
    planningStartIso?: string | null
    planningEndIso?: string | null
    quantityByMaterialItemIdForAdd?: Record<string, number>
    savedQuantityByMaterialItemIdForAdd?: Record<string, number>
    invitedDepartmentsForAdd?: { id: string; name?: string | null; status?: string | null }[]
    addingActivityMaterial?: boolean
  }>(),
  {
    departmentId: '',
    activityType: 'activity',
    activityName: '',
    canReportIssues: false,
    canReportConsumption: false,
    reloadToken: 0,
    consumptionModalCancelledToken: 0,
    consumptionModalReturnWithoutConsumptionToken: 0,
    canAddActivityMaterial: false,
    canRequestConsumableNachbuchung: false,
    activityTypeForMaterialAdd: 'activity',
    planningStartIso: null,
    planningEndIso: null,
    quantityByMaterialItemIdForAdd: () => ({}),
    savedQuantityByMaterialItemIdForAdd: () => ({}),
    invitedDepartmentsForAdd: () => [],
    addingActivityMaterial: false,
  },
)

const packWorkflowProfile = computed(() => packWorkflowProfileForActivityType(props.activityType ?? 'activity'))

/** activity + camp/event: Gruppe/Ersteller ab «gepackt»; external: nur MW */
const isGroupHandoffProfile = computed(
  () => packWorkflowProfile.value === 'quick' || packWorkflowProfile.value === 'logistics',
)

function activityTypeLabel(): string {
  const type = props.activityType ?? 'activity'
  const key = `activities.types.${type}`
  return te(key) ? t(key) : type
}

function activityDisplayName(): string {
  const name = (props.activityName ?? '').trim()
  return name || t('activities.devicesPack.untitled')
}

/**
 * Gruppenmitglied/User: Packliste erst ab «gepackt» — nicht während MW packt
 * (submitted / approved / packing).
 */
const memberAwaitingMwPack = computed(
  () =>
    !canManageMaterials.value &&
    isGroupHandoffProfile.value &&
    ['submitted', 'approved', 'packing'].includes(props.status),
)

/** Gruppe/User: vom MW noch nicht (vollständig) gepackt — sonst in Stufen-Listen unsichtbar (quantity_packed = 0). */
const memberMwPackIncompleteItems = computed(() => {
  if (canManageMaterials.value) return []
  if (!isGroupHandoffProfile.value) return []
  if (!['packing', 'packed', 'at_event'].includes(props.status)) return []
  return packItems.value
    .filter((pi) => {
      const ordered = pi.quantityOrdered ?? 0
      const packed = pi.quantityPacked ?? 0
      return ordered > 0 && packed < ordered
    })
    .sort((a, b) => (a.materialName ?? '').localeCompare(b.materialName ?? '', locale.value))
})

const showMemberMwPackIncompleteBanner = computed(
  () => memberMwPackIncompleteItems.value.length > 0,
)

const memberMwPackIncompleteHint = computed(() =>
  memberAwaitingMwPack.value
    ? t('activities.packList.memberIncompletePackHintPacking')
    : t('activities.packList.memberIncompletePackHintPacked'),
)

/** Gruppe/User: Retour an MW übergeben — Packliste nur noch Ansicht (MW lagert ein). */
const memberReturnHandoffComplete = computed(
  () =>
    !canManageMaterials.value &&
    isGroupHandoffProfile.value &&
    props.status === 'returned',
)

const packWorkflowReadOnly = computed(
  () => !showPackOperateControls.value && !memberAwaitingMwPack.value,
)

const isActivityCompleted = computed(() => props.status === 'completed')

/** Nach Abschluss keine Fortschritts-/Warn-Leiste (Material kann historisch «Gepackt» geblieben sein). */
const showPackStageProgress = computed(() => !isActivityCompleted.value)

/** MW/DC: Material gepackt — Gruppe soll Transport/Event übernehmen (activity / camp / event) */
const showMwHandoffBanner = computed(
  () =>
    canManageMaterials.value &&
    isGroupHandoffProfile.value &&
    props.status === 'packed',
)

/** MW/DC: Am Event — Gruppe soll Retour/Transport zurück erfassen (activity / camp / event) */
const showMwReturnHandoffBanner = computed(
  () =>
    canManageMaterials.value &&
    isGroupHandoffProfile.value &&
    props.status === 'at_event',
)

const showMwGroupHandoffBanner = computed(
  () => showMwHandoffBanner.value || showMwReturnHandoffBanner.value,
)

/**
 * MW/DC: Gruppe ist für Ausgabe/Retour zuständig (Status packed / at_event) — activity / camp / event.
 * Bei «external» arbeitet der MW durchgehend.
 */
const mwGroupHandoffActive = computed(
  () =>
    canManageMaterials.value &&
    isGroupHandoffProfile.value &&
    (props.status === 'packed' || props.status === 'at_event'),
)

/** Noch nicht vollständig gepackt (Nachlieferung / vergessenes Material / Zelt 0/1). */
const hasMwOpenPackingWork = computed(() => {
  if (!canManageMaterials.value) return false
  return packItems.value.some((pi) => {
    const ordered = pi.quantityOrdered ?? 0
    const packed = pi.quantityPacked ?? 0
    return ordered > 0 && packed < ordered
  })
})

/** Nachbuchung: schon «gepackt», aber noch lose ohne Pack-Kiste (Tab Bestätigt → Gepackt). */
const hasMwLooseCrateAssignmentWork = computed(() => {
  if (!canManageMaterials.value || !showPackContainersUi.value) return false
  const st = props.status || ''
  if (st !== 'packing' && st !== 'packed' && st !== 'at_event') return false
  return packItems.value.some((pi) => {
    if (isPhysicalComboPackItem(pi, packContainers.value) && isPhysicalComboAsSet(pi, packContainers.value)) {
      return false
    }
    return looseQtyForPackItem(pi, 'confirmed_packed') >= 1
  })
})

const hasMwConfirmedPackedTabWork = computed(
  () => hasMwOpenPackingWork.value || hasMwLooseCrateAssignmentWork.value,
)

/** Während Gruppen-Übergabe: Zeilen-Pfeile auf erlaubten Tabs (Ausgabe/Retour mit Handoff-Dialog). */
function mwHandoffAllowsPackLineControls(stage: PackStage): boolean {
  if (!mwGroupHandoffActive.value) return false
  if (isPackConfirmedStage(stage) && hasMwConfirmedPackedTabWork.value) return true
  if (isPackForwardToEventStage(stage)) return true
  if (isPackReturnCrateCheckStage(stage)) return true
  return false
}

const showMwOpenPackingWorkBanner = computed(
  () => mwGroupHandoffActive.value && hasMwOpenPackingWork.value,
)

const showMwLooseCrateAssignmentBanner = computed(
  () =>
    canManageMaterials.value &&
    hasMwLooseCrateAssignmentWork.value &&
    (props.status === 'packed' || props.status === 'at_event'),
)

function toastMwPackListRevertLockedForGroup(): void {
  toast.info(t('activities.packList.mwHandoffRevertLocked'))
}

/** Tab passend zum Aktivitäts-Status (nicht manuell vorgezogen/zurück). */
const statusPackStage = computed(() =>
  autoPackStageForProfile(packWorkflowProfile.value, props.status, canManageMaterials.value),
)

const packStageTabOffset = computed(() => {
  const keys = packStageKeys.value
  const activeIdx = keys.indexOf(activePackStage.value)
  const statusIdx = keys.indexOf(statusPackStage.value)
  if (activeIdx < 0 || statusIdx < 0) return 0
  return activeIdx - statusIdx
})

const isActiveStatusPackStage = computed(() => packStageTabOffset.value === 0)
const isViewingPastPackStage = computed(() => packStageTabOffset.value < 0)
const isViewingFuturePackStage = computed(() => packStageTabOffset.value > 0)

/**
 * Ein Tab voraus (z. B. Status «Am Event», Ansicht «Transport zurück → Retour»):
 * Gruppe/Leader darf Retour buchen; MW ebenfalls.
 */
const isViewingOneStepAheadWithWork = computed(() => {
  if (!props.packListEditable || packStageTabOffset.value !== 1) return false
  /** Retour-Tab: auch wenn links «Alles verschoben» — Verbrauch/Nachlieferung rechts. */
  if (
    isPackReturnStage(activePackStage.value) ||
    isPackWorkflowStatusToReturnedStage(activePackStage.value, packWorkflowProfile.value)
  ) {
    return true
  }
  if (stageLeftHeaderCount.value <= 0) return false
  return canManageMaterials.value
})

/**
 * Status schon «Am Event», aber Rest steht noch «Gepackt» — Tab Gepackt→Event bleibt für Vorwärtsbuchung offen.
 */
const allowPastStageForwardForOpenIssue = computed(() => {
  if (!props.packListEditable) return false
  if (!isViewingPastPackStage.value) return false
  if (!isPackForwardToEventStage(activePackStage.value)) return false
  if (props.status !== 'at_event') return false
  return stageLeftHeaderCount.value > 0
})

const showPackStageOpenIssueRemainderBanner = computed(() => allowPastStageForwardForOpenIssue.value)

const statusStageConfig = computed(() => {
  const key = statusPackStage.value
  return {
    key,
    leftLabel: t(`activities.packList.stages.${key}.left`),
    rightLabel: t(`activities.packList.stages.${key}.right`),
  }
})

const showPackStageViewOnlyBanner = computed(
  () =>
    props.packListEditable &&
    packStageKeys.value.length > 1 &&
    !isActiveStatusPackStage.value &&
    !isViewingOneStepAheadWithWork.value &&
    !allowPastStageForwardForOpenIssue.value &&
    !(hasMwConfirmedPackedTabWork.value && isPackConfirmedStage(activePackStage.value)),
)

const showPackOperateControls = computed(
  () =>
    props.packListEditable &&
    !memberReturnHandoffComplete.value &&
    (isActiveStatusPackStage.value ||
      isViewingOneStepAheadWithWork.value ||
      allowPastStageForwardForOpenIssue.value ||
      (hasMwConfirmedPackedTabWork.value && isPackConfirmedStage(activePackStage.value))),
)

/** Vorwärts (→): aktiver Tab; bei Retour-Status nur noch Einlagern auf «Retour → Ausgepackt». */
const showPackForwardControls = computed(() => {
  if (!props.packListEditable) return false
  if (props.status === 'returned') {
    if (!isPackUnpackStage(activePackStage.value) || !isActiveStatusPackStage.value) return false
  }
  if (allowPastStageForwardForOpenIssue.value) return true
  if (isViewingOneStepAheadWithWork.value) return true
  if (isActiveStatusPackStage.value) {
    if (mwGroupHandoffActive.value && !mwHandoffAllowsPackLineControls(activePackStage.value)) {
      return false
    }
    return true
  }
  if (mwHandoffAllowsPackLineControls(activePackStage.value)) return true
  return false
})

/** Rückwärts (←): nur aktiver Tab — MW während Gruppen-Übergabe gesperrt. */
const showPackBackwardControls = computed(() => {
  if (!props.packListEditable) return false
  if (mwGroupHandoffActive.value) {
    return isPackConfirmedStage(activePackStage.value) && hasMwOpenPackingWork.value
  }
  if (!isActiveStatusPackStage.value) {
    return (
      canManageMaterials.value &&
      isPackConfirmedStage(activePackStage.value) &&
      hasMwConfirmedPackedTabWork.value
    )
  }
  return true
})

function packStageViewLabel(key: PackStage): string {
  return `${t(`activities.packList.stages.${key}.left`)} → ${t(`activities.packList.stages.${key}.right`)}`
}

async function confirmPackStageForwardAllowed(): Promise<boolean> {
  if (!props.packListEditable) return false
  if (allowPastStageForwardForOpenIssue.value) return true
  if (
    showPackForwardControls.value &&
    (isActiveStatusPackStage.value ||
      isViewingOneStepAheadWithWork.value ||
      mwHandoffAllowsPackLineControls(activePackStage.value))
  ) {
    return true
  }
  if (!showPackForwardControls.value) {
    toast.info(t('activities.packList.toastPackStageViewOnly'))
    return false
  }
  if (canManageMaterials.value && isViewingPastPackStage.value) {
    return confirmDialog({
      title: t('activities.packList.mwOffStageForwardConfirmTitle'),
      message: t('activities.packList.mwOffStageForwardConfirmMessage', {
        viewStage: packStageViewLabel(activePackStage.value),
        statusStage: packStageViewLabel(statusPackStage.value),
      }),
      confirmText: t('activities.packList.mwOffStageForwardConfirmProceed'),
      cancelText: t('activities.common.cancel'),
      variant: 'warning',
    })
  }
  toast.info(t('activities.packList.toastPackStageViewOnly'))
  return false
}

async function confirmPackStageBackwardAllowed(): Promise<boolean> {
  if (!showPackBackwardControls.value) {
    toast.info(t('activities.packList.toastPackStageViewOnly'))
    return false
  }
  if (isViewingFuturePackStage.value) {
    toast.info(t('activities.packList.toastPackStageViewOnly'))
    return false
  }
  if (!isActiveStatusPackStage.value) {
    if (canManageMaterials.value && isViewingPastPackStage.value) {
      return confirmDialog({
        title: t('activities.packList.mwOffStageBackConfirmTitle'),
        message: t('activities.packList.mwOffStageBackConfirmMessage', {
          viewStage: packStageViewLabel(activePackStage.value),
          statusStage: packStageViewLabel(statusPackStage.value),
        }),
        confirmText: t('activities.packList.mwOffStageBackConfirmProceed'),
        cancelText: t('activities.common.cancel'),
        variant: 'warning',
      })
    }
    toast.info(t('activities.packList.toastPackStageViewOnly'))
    return false
  }
  if (
    isPackReturnPipelineStage(activePackStage.value) &&
    showMwReturnHandoffBanner.value &&
    !(await confirmMwHandoffBeforeReturn())
  ) {
    return false
  }
  if (
    isPackForwardToEventStage(activePackStage.value) &&
    showMwHandoffBanner.value &&
    !(await confirmMwHandoffBeforeIssueToEvent())
  ) {
    return false
  }
  return true
}

async function confirmMwHandoffBeforeIssueToEvent(skipForCrateCheck = false): Promise<boolean> {
  if (skipForCrateCheck || !showMwHandoffBanner.value) return true
  return confirmDialog({
    title: t('activities.packList.mwHandoffConfirmTitle'),
    message: t('activities.packList.mwHandoffConfirmMessage'),
    confirmText: t('activities.packList.mwHandoffConfirmProceed'),
    cancelText: t('activities.common.cancel'),
    variant: 'warning',
  })
}

/** MW/DC: Aktivitäts-Status «Am Event» setzen — Hinweis Gruppe umgehen */
async function confirmMwHandoffWorkflowToEvent(): Promise<boolean> {
  if (!showMwHandoffBanner.value) return true
  return confirmDialog({
    title: t('activities.packList.mwHandoffCombinedConfirmTitle'),
    message: t('activities.packList.mwHandoffWorkflowConfirmMessage'),
    confirmText: t('activities.packList.mwHandoffWorkflowConfirmProceed'),
    cancelText: t('activities.common.cancel'),
    variant: 'warning',
  })
}

async function confirmMwHandoffBeforeReturn(): Promise<boolean> {
  if (!showMwReturnHandoffBanner.value) return true
  return confirmDialog({
    title: t('activities.packList.mwReturnHandoffConfirmTitle'),
    message: t('activities.packList.mwReturnHandoffConfirmMessage'),
    confirmText: t('activities.packList.mwReturnHandoffConfirmProceed'),
    cancelText: t('activities.common.cancel'),
    variant: 'warning',
  })
}

/** MW/DC: Aktivitäts-Status «Retour» setzen — Hinweis Gruppe umgehen */
async function confirmMwHandoffWorkflowToReturned(): Promise<boolean> {
  if (!showMwReturnHandoffBanner.value) return true
  return confirmDialog({
    title: t('activities.packList.mwReturnHandoffWorkflowConfirmTitle'),
    message: t('activities.packList.mwReturnHandoffWorkflowConfirmMessage'),
    confirmText: t('activities.packList.mwReturnHandoffWorkflowConfirmProceed'),
    cancelText: t('activities.common.cancel'),
    variant: 'warning',
  })
}

/** Ausgepackt → noch einzulagern: Buchung korrigieren, obwohl physisch evtl. schon im Lager */
async function confirmUnpackUnstoreFromWarehouse(qty: number, materialLabel: string): Promise<boolean> {
  const name = materialLabel.trim() || t('activities.common.material')
  return confirmDialog({
    title: t('activities.packList.confirmUnpackUnstoreTitle'),
    message: t('activities.packList.confirmUnpackUnstoreMessage', { qty, name }),
    confirmText: t('activities.packList.confirmUnpackUnstoreProceed'),
    cancelText: t('activities.common.cancel'),
    variant: 'warning',
  })
}

/** Gepackt → Bestätigt: physisch zurück ins Lager legen, sonst Bestand/Verfügbarkeit falsch */
async function confirmPackedBackToConfirmed(): Promise<boolean> {
  if (!isPackConfirmedStage(activePackStage.value)) return true
  return confirmDialog({
    title: t('activities.packList.confirmPackedBackTitle'),
    message: t('activities.packList.confirmPackedBackMessage'),
    confirmText: t('activities.packList.confirmPackedBackProceed'),
    cancelText: t('activities.common.cancel'),
    variant: 'warning',
  })
}

/** Nur wenn Workflow «Am Event buchen» geklickt wurde (Status at_event), nicht bei «gepackt» + Packbuchungen. */
const activityStatusAllowsIssueReports = computed(() => {
  const s = props.status
  return s === 'at_event' || s === 'returned'
})

const showPackIssueActions = computed(
  () =>
    activityStatusAllowsIssueReports.value &&
    showPackOperateControls.value &&
    props.canReportIssues !== false,
)

const showPackConsumptionActions = computed(
  () =>
    activityStatusAllowsIssueReports.value &&
    showPackOperateControls.value &&
    props.canReportConsumption !== false,
)

/**
 * Inline-Verbrauch (+/−): nur Quick-Profil Tab «Am Event → Retour».
 * Camp/Event (logistics): inkl. «Transport (zurück)» bewusst nur Button «Verbrauch buchen».
 */
const useConsumableInlineAdjust = computed(() => activePackStage.value === 'at_event_returned')

/** Verbrauch liegt nur noch in Kistenzeilen am Event (nicht lose in der Retour-Spalte). */
function consumableStillOnlyInCrateAtReturn(pi: ActivityPackItem): boolean {
  if (!isPackReturnStage(activePackStage.value)) return false
  if (containerStillAtEventQtyForMaterial(pi.materialItemId) <= 0) return false
  return looseQtyStillAtEventForReturn(pi) <= 0
}

/** Verbrauchsmaterial ab «gepackt» auf der Packliste (auch wenn am Event bereits 0). */
function consumableOnPackListFromPacked(pi: ActivityPackItem): boolean {
  if (!pi.isConsumable) return false
  return (
    (pi.quantityPacked ?? 0) > 0 ||
    consumableBookedConsumptionQty(pi) > 0 ||
    (pi.quantityIssued ?? 0) > 0 ||
    (pi.quantityReturned ?? 0) > 0
  )
}

/** Bereits in einer späteren Pipeline-Stufe als der aktuelle Tab (dann nicht mehr links mit «0»). */
function consumableQtyAlreadyBeyondCurrentStage(pi: ActivityPackItem): boolean {
  const stage = activePackStage.value
  if (stage === 'confirmed_packed') {
    return (pi.quantityPacked ?? 0) > 0
  }
  if (stage === 'packed_transport_to') {
    return (
      (pi.quantityTransportTo ?? 0) > 0 ||
      (pi.quantityIssued ?? 0) > 0 ||
      (pi.quantityTransportBack ?? 0) > 0
    )
  }
  if (stage === 'transport_to_at_event' || stage === 'packed_at_event') {
    return (pi.quantityTransportBack ?? 0) > 0 || (pi.quantityReturned ?? 0) > 0
  }
  if (stage === 'at_event_transport_back') {
    return (pi.quantityReturned ?? 0) > 0
  }
  return false
}

/**
 * Verbrauchsmaterial links: «0 Stk.» solange verbraucht, aber noch nicht mit → auf die rechte
 * Seite dieses Tabs gebucht (egal ob Packen, Transport oder Event — nicht im Lager).
 */
function consumableShowsZeroOnStageLeft(pi: ActivityPackItem): boolean {
  if (!pi.isConsumable || consumableBookedConsumptionQty(pi) <= 0) return false
  if (isPackReturnStage(activePackStage.value) || isPackUnpackStage(activePackStage.value)) return false
  if (effectiveStageLeftQty(pi) > 0) return false
  if (getStageRightQty(pi) > 0) return false
  if (consumableQtyAlreadyBeyondCurrentStage(pi)) return false
  return true
}

/** Nachlieferung: Verbrauchsmaterial von Pack-Stufe bis Retour (nicht Ausgepackt / abgeschlossen). */
function showConsumableNachbuchungForPackItem(pi: ActivityPackItem): boolean {
  if (!pi.isConsumable || props.canRequestConsumableNachbuchung !== true) return false
  if (isPackUnpackStage(activePackStage.value)) return false
  if (props.status === 'completed' || props.status === 'cancelled') return false
  if (isPackConfirmedStage(activePackStage.value)) return false
  return (
    isPackForwardToEventStage(activePackStage.value) ||
    isPackReturnPipelineStage(activePackStage.value) ||
    isPackReturnStage(activePackStage.value)
  )
}

function showConsumableNachbuchungForMaterial(materialItemId: string): boolean {
  const pi = packItemForMaterialItemId(materialItemId)
  return pi ? showConsumableNachbuchungForPackItem(pi) : false
}

/** Verbrauch buchen + Nachlieferung in der Materialzeile. */
function showConsumablePackActionsForPackItem(pi: ActivityPackItem): boolean {
  if (!pi.isConsumable) return false
  if (showConsumableNachbuchungForPackItem(pi)) return true
  return showConsumableConsumptionForPackItem(pi)
}

/** Verbrauch buchen (User/Leader + MW): solange noch offen und Material am Event / in Retour. */
function showConsumableConsumptionForPackItem(pi: ActivityPackItem): boolean {
  if (!pi.isConsumable || !showPackConsumptionActions.value) return false
  if (consumableConsumptionRemaining(pi) <= 0) return false
  if (isPackForwardToEventStage(activePackStage.value)) {
    return looseIssuedAtEvent(pi) > 0 || issuedQtyInContainersForMaterial(pi.materialItemId) > 0
  }
  if (isPackReturnStage(activePackStage.value)) {
    if (consumableStillOnlyInCrateAtReturn(pi)) return false
    return consumableConsumptionRemaining(pi) > 0
  }
  return (pi.quantityIssued ?? 0) > 0
}

/** Verlust/Reparatur/Verbrauch nur für lose «Am Event»-Menge (nicht Rest «Gepackt» links). */
function showPackIssueForPackItem(pi: ActivityPackItem): boolean {
  if (!showPackIssueActions.value && !showPackConsumptionActions.value) return false
  if (pi.isConsumable) return showConsumablePackActionsForPackItem(pi)
  if (!showPackIssueActions.value) return false
  if (isPackForwardToEventStage(activePackStage.value)) {
    return looseIssuedAtEvent(pi) > 0
  }
  if (isPackReturnStage(activePackStage.value)) {
    return Math.max(0, (pi.quantityIssued ?? 0) - (pi.quantityReturned ?? 0)) > 0
  }
  if (isPackUnpackStage(activePackStage.value)) {
    if (pi.isConsumable) return false
    return pendingStoreLooseQtyForPackItem(pi) > 0
  }
  return (pi.quantityIssued ?? 0) > 0
}

function showKisteMeldungForContainer(containerId: string): boolean {
  if (!showPackIssueActions.value) return false
  return containerHasIssuedAtEvent(containerId)
}

/** Verlust/Reparatur/Verbrauch für Kistenzeile — nur wenn Aktivität «Am Event» und Inhalt wirklich ausgegeben. */
function showPackIssueForContainerLine(ci: ActivityPackContainerItem, containerId: string): boolean {
  if (!showPackIssueActions.value && !showPackConsumptionActions.value) return false
  const pi = packItems.value.find((p) => p.materialItemId === ci.material_item_id)
  if (isPackUnpackStage(activePackStage.value)) {
    if (pi?.isConsumable) return false
    if (!showPackIssueActions.value) return false
    if ((ci.quantity_issued ?? 0) < 1) return false
    return containerLineRemainingStore(ci) > 0
  }
  if (pi?.isConsumable) {
    if (!showPackConsumptionActions.value) return false
    if (useConsumableInlineAdjust.value) return true
    if (consumableConsumptionRemaining(pi) <= 0) return false
    if (isPackReturnStage(activePackStage.value)) {
      return Math.max(0, (ci.quantity_issued ?? 0) - (ci.quantity_returned ?? 0)) > 0
    }
    if (isPackForwardToEventStage(activePackStage.value)) {
      return (
        containerHasIssuedAtEvent(containerId) &&
        Math.max(0, (ci.quantity_issued ?? 0) - (ci.quantity_returned ?? 0)) > 0
      )
    }
    return (pi.quantityIssued ?? 0) > 0
  }
  if (!showPackIssueActions.value) return false
  if ((ci.quantity_issued ?? 0) < 1) return false
  if (isPackForwardToEventStage(activePackStage.value)) {
    return containerHasIssuedAtEvent(containerId)
  }
  if (isPackReturnStage(activePackStage.value)) {
    return Math.max(0, (ci.quantity_issued ?? 0) - (ci.quantity_returned ?? 0)) > 0
  }
  return (ci.quantity_issued ?? 0) > 0
}

function showPackIssueForShellUnpack(containerId: string): boolean {
  const sh = shellPackItemForContainer(containerId)
  if (!sh) return false
  if (sh.isConsumable) return showConsumableConsumptionForPackItem(sh)
  if (!showPackIssueActions.value) return false
  return containerShellPendingStoreQty(containerId) > 0
}

const emit = defineEmits<{
  workflowNext: [transition: ActivityTransitionRow]
  /** Nach Kistenwahl: Backend legt ActivityItem an — Parent soll Materialliste neu laden */
  activityItemsChanged: []
  openIssueWizard: [payload: PackIssueWizardEmitPayload]
  openConsumptionModal: [
    payload: {
      materialItemId: string
      materialName: string
      packSize: number | null
      packUnit: string | null
      linkedContainerLabel?: string | null
      returnQty?: number
    },
  ]
  requestNachbuchung: [
    payload: {
      materialItemId: string
      materialLabel: string
      packSize: number | null
      packUnit: string | null
      packStage?: import('./packStageQuantities').PackStage
    },
  ]
  addActivityMaterial: [payload: { material: { materialItemId: string }; quantity: number }]
  materialScopeChange: [payload: { tab: MaterialScopeTab; singlePartnerDepartmentId: string | null }]
}>()

const showPackMaterialAddPanel = computed(
  () =>
    props.canAddActivityMaterial === true &&
    (props.status === 'packing' || props.status === 'packed'),
)

const packMaterialAddLookupReady = computed(
  () =>
    showPackMaterialAddPanel.value &&
    !!props.departmentId?.trim() &&
    !!props.activityId?.trim(),
)

const packAddMaterialExpanded = ref(false)

type PendingMaterialCrateAssign = {
  materialItemId: string
  quantity: number
  containerId?: string
  comboPackItemId?: string
}
const pendingMaterialAssignToContainer = ref<PendingMaterialCrateAssign | null>(null)

type PendingConsumableReturn =
  | { kind: 'pack-item'; packItemId: string; qty: number }
  | { kind: 'container-line'; containerId: string; containerItemId: string; qty: number }

const pendingConsumableReturn = ref<PendingConsumableReturn | null>(null)

/** Behälter ohne Lager-Batch, nur für Phys.-Kombi «Einbuchen in» (ohne verknüpfte Kiste) */
const shellComboVirtualContainerByPackItemId = ref<Record<string, string>>({})

function isOrphanShellWithoutPackContainer(pi: ActivityPackItem): boolean {
  return isOrphanShellWithoutPackContainerRow(pi, packContainers.value, activePackStage.value)
}

/** Kisten-Ziele fürs Einbuchen: Phys.-Kombi mit Kiste oder Pack-Behälter mit Lager-Batch */
const hasKisteAddTargetsOnActivity = computed(
  () =>
    packContainers.value.some((c) => (c.container_batch_id ?? '').trim() !== '') ||
    packItems.value.some((p) => isCrateShellPackItem(p, packContainers.value)),
)

const physicalComboPackItemsSorted = computed(() =>
  [...packItems.value]
    .filter((p) => p.materialType === 'physical_combo')
    .sort((a, b) => a.materialName.localeCompare(b.materialName, locale.value)),
)

function physicalComboAddTargetLabel(pi: ActivityPackItem): string {
  const name = (pi.materialName ?? '').trim()
  const kiste = (pi.linkedContainerLabel ?? '').trim()
  if (kiste && kiste !== name) return `${name} (${kiste})`
  return name || kiste || t('activities.detail.comboPhysicalShort')
}

const materialAddTargetEntries = computed(() => {
  void locale.value
  const entries: { key: string; label: string }[] = [
    { key: 'loose', label: t('activities.packList.addMaterialTargetLoose') },
  ]
  const listedContainerIds = new Set<string>()

  for (const pi of physicalComboPackItemsSorted.value) {
    if (!isCrateShellPackItem(pi, packContainers.value)) continue
    const shellC = packShellContainerForPackItem(pi, packContainers.value)
    if (shellC) {
      listedContainerIds.add(shellC.id)
      entries.push({ key: `container:${shellC.id}`, label: physicalComboAddTargetLabel(pi) })
    } else {
      entries.push({ key: `combo:${pi.id}`, label: physicalComboAddTargetLabel(pi) })
    }
  }

  for (const c of packContainersSorted.value) {
    if (listedContainerIds.has(c.id)) continue
    if (!(c.container_batch_id ?? '').trim()) continue
    listedContainerIds.add(c.id)
    entries.push({ key: `container:${c.id}`, label: c.label })
  }

  return entries
})

const showPackMaterialAddTarget = computed(
  () =>
    showPackMaterialAddPanel.value &&
    showPackContainersUi.value &&
    activePackStage.value === 'confirmed_packed' &&
    materialAddTargetEntries.value.length > 1,
)

const materialAddTargetKey = computed({
  get(): string {
    const tgt = activePackTarget.value
    if (tgt?.kind === 'container') return `container:${tgt.containerId}`
    if (tgt?.kind === 'combo') return `combo:${tgt.packItemId}`
    return 'loose'
  },
  set(value: string) {
    if (value.startsWith('container:')) {
      activePackTarget.value = { kind: 'container', containerId: value.slice('container:'.length) }
    } else if (value.startsWith('combo:')) {
      activePackTarget.value = { kind: 'combo', packItemId: value.slice('combo:'.length) }
    } else {
      activePackTarget.value = { kind: 'loose' }
    }
  },
})

watch(
  () => props.status,
  (s) => {
    if (s !== 'packing' && s !== 'packed') {
      packAddMaterialExpanded.value = false
    }
    if (s === 'completed') {
      const stage = autoPackStageForProfile(
        packWorkflowProfile.value,
        s,
        canManageMaterials.value,
      )
      if (packStageKeys.value.includes(stage)) {
        activePackStage.value = stage
      }
    }
  },
)

function onPackTabAddMaterialQuantity(payload: { material: { materialItemId: string }; quantity: number }) {
  const tgt = activePackTarget.value
  if (showPackContainersUi.value && activePackStage.value === 'confirmed_packed') {
    if (tgt?.kind === 'container') {
      pendingMaterialAssignToContainer.value = {
        materialItemId: payload.material.materialItemId,
        quantity: payload.quantity,
        containerId: tgt.containerId,
      }
    } else if (tgt?.kind === 'combo') {
      pendingMaterialAssignToContainer.value = {
        materialItemId: payload.material.materialItemId,
        quantity: payload.quantity,
        comboPackItemId: tgt.packItemId,
      }
    } else {
      pendingMaterialAssignToContainer.value = null
    }
  } else {
    pendingMaterialAssignToContainer.value = null
  }
  emit('addActivityMaterial', payload)
}

/** Behälter-ID fürs Einbuchen: Pack-Kiste oder Phys.-Kombi-Ziel */
async function resolveContainerIdForActiveTarget(): Promise<string | null> {
  const tgt = activePackTarget.value
  if (!tgt) return null
  if (tgt.kind === 'container') return tgt.containerId
  if (tgt.kind === 'combo') return ensurePackContainerForShellCombo(tgt.packItemId)
  return null
}

async function ensurePackContainerForShellCombo(packItemId: string): Promise<string | null> {
  const pi = packItems.value.find((p) => p.id === packItemId)
  if (!pi || pi.materialType !== 'physical_combo') return null

  const linked = shellPackContainerForItem(pi)
  if (linked) return linked.id

  const virtualId = shellComboVirtualContainerByPackItemId.value[packItemId]
  if (virtualId && packContainers.value.some((c) => c.id === virtualId)) {
    return virtualId
  }

  const label = physicalComboAddTargetLabel(pi).slice(0, 120)
  const batchId = (pi.linkedContainerBatchId ?? '').trim()

  containerMutationLoading.value = true
  try {
    const created = await createActivityPackContainer(props.activityId, {
      label: label || t('activities.common.crate'),
      ...(batchId ? { container_batch_id: batchId } : {}),
    })
    await loadContainersData()
    if (batchId) {
      const items = await getPackItems(props.activityId)
      packItems.value = items
      initMoveQtyInputs()
      emit('activityItemsChanged')
    } else {
      shellComboVirtualContainerByPackItemId.value = {
        ...shellComboVirtualContainerByPackItemId.value,
        [packItemId]: created.id,
      }
    }
    const afterLink = shellPackContainerForItem(pi)
    return afterLink?.id ?? created.id
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastContainerAddFailed'))
    return null
  } finally {
    containerMutationLoading.value = false
  }
}

async function fulfillPendingMaterialAssignToContainer(attempt = 0): Promise<void> {
  const pending = pendingMaterialAssignToContainer.value
  if (!pending) return

  let containerId = pending.containerId
  if (!containerId && pending.comboPackItemId) {
    const shellPi = packItems.value.find((p) => p.id === pending.comboPackItemId)
    if (
      shellPi
      && isPhysicalComboAsSet(shellPi, packContainers.value)
      && pending.materialItemId === shellPi.materialItemId
    ) {
      pendingMaterialAssignToContainer.value = null
      return
    }
    containerId = (await ensurePackContainerForShellCombo(pending.comboPackItemId)) ?? undefined
    if (containerId) {
      pendingMaterialAssignToContainer.value = { ...pending, containerId }
    }
  }
  if (!containerId) {
    if (attempt < 8 && pending.comboPackItemId) {
      await new Promise((r) => setTimeout(r, 150))
      return fulfillPendingMaterialAssignToContainer(attempt + 1)
    }
    pendingMaterialAssignToContainer.value = null
    toast.error(t('activities.packList.toastMaterialAddToCrateFailed'))
    return
  }

  const pi = packItems.value.find((p) => p.materialItemId === pending.materialItemId)
  if (!pi) {
    if (attempt < 8) {
      await new Promise((r) => setTimeout(r, 150))
      return fulfillPendingMaterialAssignToContainer(attempt + 1)
    }
    pendingMaterialAssignToContainer.value = null
    toast.error(t('activities.packList.toastMaterialAddToCrateFailed'))
    return
  }

  if (activePackTarget.value?.kind !== 'container' || activePackTarget.value.containerId !== containerId) {
    activePackTarget.value = { kind: 'container', containerId }
  }

  try {
    if (props.status === 'packing' && activePackStage.value === 'confirmed_packed') {
      const left = getStageLeftQty(pi)
      if (left > 0) {
        const moveQty = Math.min(pending.quantity, left)
        await moveToNextStage(pi, moveQty)
        pendingMaterialAssignToContainer.value = null
        return
      }
    }

    const loose = looseQtyForPackItem(pi)
    if (loose < 1) {
      if (attempt < 8) {
        await new Promise((r) => setTimeout(r, 150))
        return fulfillPendingMaterialAssignToContainer(attempt + 1)
      }
      pendingMaterialAssignToContainer.value = null
      toast.error(t('activities.packList.toastMaterialAddToCrateFailed'))
      return
    }

    const q = Math.min(pending.quantity, loose)
    await assignMaterialToContainer(pi, containerId, q, {
      successMessage: t('activities.packList.toastMaterialAddedToCrate', {
        label: packContainers.value.find((c) => c.id === containerId)?.label ?? '',
      }),
    })
    pendingMaterialAssignToContainer.value = null
  } catch {
    pendingMaterialAssignToContainer.value = null
  }
}

function onPackTabMaterialScopeChange(payload: { tab: MaterialScopeTab; singlePartnerDepartmentId: string | null }) {
  emit('materialScopeChange', payload)
}

const physicalComboIssueModalOpen = ref(false)
const physicalComboIssueModalLoading = ref(false)
const physicalComboIssueModalPi = ref<ActivityPackItem | null>(null)
const physicalComboIssueModalIssueType = ref<'loss' | 'repair'>('loss')
const physicalComboIssueModalSections = ref<PackCrateShellPeekSection[]>([])

function closePhysicalComboIssueModal() {
  physicalComboIssueModalOpen.value = false
  physicalComboIssueModalPi.value = null
  physicalComboIssueModalSections.value = []
}

function emitIssueWizardPayload(payload: PackIssueWizardEmitPayload) {
  emit('openIssueWizard', payload)
}

function emitIssueWizardSelections(
  selections: PhysicalComboIssueSelection[],
  issueType: 'loss' | 'repair',
) {
  if (selections.length === 0) return
  if (selections.length === 1) {
    emitIssueWizardPayload({
      materialItemId: selections[0].materialItemId,
      issueType,
      quantity: selections[0].quantity,
    })
    return
  }
  emitIssueWizardPayload({
    items: selections.map((s) => ({
      materialItemId: s.materialItemId,
      issueType,
      quantity: s.quantity,
    })),
  })
}

async function openPhysicalComboIssuePicker(
  pi: ActivityPackItem,
  issueType: 'loss' | 'repair',
): Promise<void> {
  physicalComboIssueModalPi.value = pi
  physicalComboIssueModalIssueType.value = issueType
  physicalComboIssueModalOpen.value = true
  physicalComboIssueModalLoading.value = true
  physicalComboIssueModalSections.value = []
  try {
    let combo = comboComponentsByMaterialId.value[pi.materialItemId] ?? []
    if (combo.length === 0) {
      combo = await getComboComponents(pi.materialItemId)
      comboComponentsByMaterialId.value = {
        ...comboComponentsByMaterialId.value,
        [pi.materialItemId]: combo,
      }
    }
    physicalComboIssueModalSections.value = peekSectionsForShellPackItem(pi)
    if (!physicalComboHasSelectableIssueComponents(physicalComboIssueModalSections.value)) {
      closePhysicalComboIssueModal()
      emitIssueWizardPayload({
        materialItemId: pi.materialItemId,
        issueType,
      })
    }
  } finally {
    physicalComboIssueModalLoading.value = false
  }
}

function onPhysicalComboIssueConfirm(selections: PhysicalComboIssueSelection[]) {
  const issueType = physicalComboIssueModalIssueType.value
  closePhysicalComboIssueModal()
  emitIssueWizardSelections(selections, issueType)
}

async function tryOpenPhysicalComboIssuePicker(
  pi: ActivityPackItem,
  issueType: 'loss' | 'repair',
  quantity?: number,
): Promise<boolean> {
  if (!isPhysicalComboPackItem(pi)) return false
  if (quantity != null && quantity > 0) {
    emitIssueWizardPayload({ materialItemId: pi.materialItemId, issueType, quantity })
    return true
  }
  const cached = comboComponentsByMaterialId.value[pi.materialItemId] ?? []
  let sections = cached.length > 0 ? peekSectionsForShellPackItem(pi) : []
  if (!physicalComboHasSelectableIssueComponents(sections) && cached.length === 0) {
    await openPhysicalComboIssuePicker(pi, issueType)
    return true
  }
  if (!physicalComboHasSelectableIssueComponents(sections)) {
    return false
  }
  await openPhysicalComboIssuePicker(pi, issueType)
  return true
}

function emitIssueWizard(pi: ActivityPackItem, issueType: 'loss' | 'repair', quantity?: number) {
  if (!showPackIssueActions.value) return
  void tryOpenPhysicalComboIssuePicker(pi, issueType, quantity).then((handled) => {
    if (!handled) {
      emitIssueWizardPayload({ materialItemId: pi.materialItemId, issueType, quantity })
    }
  })
}

function emitConsumptionFromPackItem(pi: ActivityPackItem) {
  if (!showConsumableConsumptionForPackItem(pi)) return
  openConsumptionModalForPackItem(pi)
}

function openConsumptionModalForPackItem(pi: ActivityPackItem, returnQty?: number) {
  if (!props.packListEditable) return
  emit('openConsumptionModal', {
    materialItemId: pi.materialItemId,
    materialName: pi.materialName,
    packSize: pi.packSize,
    packUnit: pi.packUnit,
    linkedContainerLabel: pi.linkedContainerLabel,
    returnQty: returnQty != null && returnQty > 0 ? returnQty : undefined,
  })
}

function shouldOpenConsumptionModalOnReturn(pi: ActivityPackItem | undefined): boolean {
  if (!pi?.isConsumable || !showPackConsumptionActions.value) return false
  /** Camp/Event: Verbrauch nicht automatisch beim Retour-Pfeil — Nutzer klickt «Verbrauch buchen». */
  if (packWorkflowProfile.value === 'logistics') return false
  return isPackReturnStage(activePackStage.value)
}

function beginConsumableReturnForPackItem(item: ActivityPackItem, returnQty: number): void {
  pendingConsumableReturn.value = { kind: 'pack-item', packItemId: item.id, qty: returnQty }
  openConsumptionModalForPackItem(item, returnQty)
}

function beginConsumableReturnForContainerLine(
  containerId: string,
  ci: ActivityPackContainerItem,
  returnQty: number,
): void {
  const pi = packItems.value.find((p) => p.materialItemId === ci.material_item_id)
  if (!pi) {
    toast.error(t('activities.packList.toastNoPackLine'))
    return
  }
  pendingConsumableReturn.value = {
    kind: 'container-line',
    containerId,
    containerItemId: ci.id,
    qty: returnQty,
  }
  openConsumptionModalForPackItem(pi, returnQty)
}

async function fulfillPendingConsumableReturn(): Promise<void> {
  const pending = pendingConsumableReturn.value
  if (!pending) return
  pendingConsumableReturn.value = null

  if (pending.kind === 'pack-item') {
    const item = packItems.value.find((p) => p.id === pending.packItemId)
    if (!item) return
    const returnQty = resolveConsumableReturnQty(item, pending.qty)
    if (returnQty <= 0) {
      toast.info(t('activities.packList.toastConsumableAllUsedNothingToReturn'))
      return
    }
    await executeMoveToNextStage(item, returnQty)
    return
  }

  const lines = containerItemsByContainerId.value[pending.containerId] ?? []
  const ci = lines.find((line) => line.id === pending.containerItemId)
  if (!ci) return
  await executeReturnContainerLineToWarehouse(pending.containerId, ci, pending.qty)
  const batch = pendingReturnCrateBatch.value
  if (
    batch?.remaining[0]?.kind === 'line' &&
    batch.remaining[0].containerItemId === pending.containerItemId
  ) {
    batch.remaining.shift()
  }
  await continueReturnCrateBatch()
}

function consumedQtyForMaterial(materialItemId: string, issuedCap?: number): number {
  const raw = consumedQtyFromIssues(materialItemId, packIssues.value)
  if (issuedCap == null || issuedCap < 1) return raw
  return Math.min(raw, issuedCap)
}

function lossQtyForMaterial(materialItemId: string): number {
  return lossQtyFromIssues(materialItemId, packIssues.value)
}

function repairQtyForMaterial(materialItemId: string): number {
  return repairQtyFromIssues(materialItemId, packIssues.value)
}

function notTakenToEventQtyForMaterial(materialItemId: string): number {
  return notTakenToEventQtyFromIssues(materialItemId, packIssues.value)
}

function replenishmentQtyForMaterial(materialItemId: string): number {
  return activityItemsForAccounting.value
    .filter(
      (row) =>
        row.material_item_id === materialItemId &&
        row.is_consumable === true &&
        row.is_replenishment === true,
    )
    .reduce((sum, row) => sum + (row.quantity ?? 0), 0)
}

/** Ursprüngliche Aktivitätsmenge Verbrauch (ohne Nachlieferungs-Zeilen). */
function consumableInitialBookedQty(materialItemId: string): number {
  return activityItemsForAccounting.value
    .filter(
      (row) =>
        row.material_item_id === materialItemId &&
        row.is_consumable === true &&
        row.is_replenishment !== true,
    )
    .reduce((sum, row) => sum + (row.quantity ?? 0), 0)
}

/** Gebucht + Nachlieferung — Basis für Verbrauch offen / Fortschritt. */
function consumableTotalBookedQty(materialItemId: string): number {
  return consumableInitialBookedQty(materialItemId) + replenishmentQtyForMaterial(materialItemId)
}

function retourAccountingForUnpackLoose(pi: ActivityPackItem): PackRetourAccounting {
  const returned = pi.quantityReturned ?? 0
  const neverIssuedLoose = loosePackedNeverIssuedQty(pi)
  const notTakenFromIssues = notTakenToEventQtyForMaterial(pi.materialItemId)
  const consumed = consumedQtyForMaterial(pi.materialItemId)
  const loss = lossQtyForMaterial(pi.materialItemId)
  const repair = repairQtyForMaterial(pi.materialItemId)
  const replenishment = replenishmentQtyForMaterial(pi.materialItemId)
  return packRetourAccountingSnapshot({
    quantityPacked: pi.quantityPacked ?? 0,
    quantityOrdered: pi.quantityOrdered ?? 0,
    quantityIssued: pi.quantityIssued ?? 0,
    returned,
    neverIssuedLoose,
    notTakenFromIssues,
    consumed,
    loss,
    repair,
    replenishment,
  })
}

function retourAccountingForContainerLine(ci: ActivityPackContainerItem): PackRetourAccounting {
  const returned = ci.quantity_returned ?? 0
  const linePacked = ci.quantity_packed ?? 0
  let lineIssued = ci.quantity_issued ?? 0
  const pi = packItemForMaterialItemId(ci.material_item_id)
  if (lineIssued <= 0 && linePacked > 0 && pi && (pi.quantityIssued ?? 0) > 0) {
    const totalPacked = Math.max(0, pi.quantityPacked ?? 0)
    if (totalPacked > 0) {
      lineIssued = Math.min(linePacked, Math.round(((pi.quantityIssued ?? 0) * linePacked) / totalPacked))
    } else {
      lineIssued = Math.min(linePacked, pi.quantityIssued ?? 0)
    }
  }
  const lineNeverIssued = Math.max(0, linePacked - lineIssued)
  const totalIssued = Math.max(lineIssued, pi?.quantityIssued ?? 0)
  const totalReplenishment = replenishmentQtyForMaterial(ci.material_item_id)
  const notTakenFromIssues = qtyAttributedToIssuedLine(
    lineIssued,
    totalIssued,
    notTakenToEventQtyForMaterial(ci.material_item_id),
  )
  const totalConsumed = consumedQtyForMaterial(ci.material_item_id)
  const consumed = qtyAttributedToIssuedLine(lineIssued, totalIssued, totalConsumed)
  const totalLoss = lossQtyForMaterial(ci.material_item_id)
  const loss = qtyAttributedToIssuedLine(lineIssued, totalIssued, totalLoss)
  const totalRepair = repairQtyForMaterial(ci.material_item_id)
  const repair = qtyAttributedToIssuedLine(lineIssued, totalIssued, totalRepair)
  const replenishment = qtyAttributedToIssuedLine(lineIssued, totalIssued, totalReplenishment)
  return packRetourAccountingSnapshot({
    quantityPacked: linePacked,
    quantityOrdered: pi?.quantityOrdered ?? linePacked,
    quantityIssued: lineIssued,
    returned,
    neverIssuedLoose: lineNeverIssued,
    notTakenFromIssues,
    consumed,
    loss,
    repair,
    replenishment,
  })
}

function expectedReturnQtyForUnpack(pi: ActivityPackItem): number {
  return retourAccountingForUnpackLoose(pi).expectedReturn
}

function expectedContainerLineReturnQty(ci: ActivityPackContainerItem): number {
  return retourAccountingForContainerLine(ci).expectedReturn
}

function consumableBookedConsumptionQty(pi: ActivityPackItem): number {
  if (!pi.isConsumable) return 0
  return consumedQtyForMaterial(pi.materialItemId)
}

/** Gebuchte Aktivitätsmenge (wie Verbrauchs-Modal: activity_items.quantity). */
function bookedQtyForMaterialOnActivity(materialItemId: string): number {
  return activityItemsForAccounting.value
    .filter((row) => row.material_item_id === materialItemId)
    .reduce((sum, row) => sum + (row.quantity ?? 0), 0)
}

/** Noch buchbarer Verbrauch: (gebucht + Nachlieferung) − bereits verbraucht. */
function consumableBookableRemaining(pi: ActivityPackItem): number {
  if (!pi.isConsumable) return 0
  return Math.max(
    0,
    consumableTotalBookedQty(pi.materialItemId) - consumableBookedConsumptionQty(pi),
  )
}

function consumableHasPendingStore(pi: ActivityPackItem): boolean {
  if (pendingStoreLooseQtyForPackItem(pi) > 0) return true
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id === pi.materialItemId && containerLineRemainingStore(ci) > 0) {
        return true
      }
    }
  }
  return false
}

const consumableInlineQtyInputs = ref<Record<string, number>>({})
const consumableInlinePostingId = ref<string | null>(null)

function consumableInlineQtyFor(materialItemId: string): number {
  return consumableInlineQtyInputs.value[materialItemId] ?? 0
}

function setConsumableInlineQty(materialItemId: string, qty: number) {
  const max = maxInlineConsumptionQtyForMaterial(materialItemId)
  const n = Number.isFinite(qty) ? Math.floor(qty) : 0
  consumableInlineQtyInputs.value = {
    ...consumableInlineQtyInputs.value,
    [materialItemId]: Math.max(0, Math.min(max, n)),
  }
}

function maxInlineConsumptionQtyForMaterial(materialItemId: string): number {
  const pi = packItemForMaterialItemId(materialItemId)
  if (!pi) return 99
  const bookable = consumableBookableRemaining(pi)
  return Math.max(bookable, 1)
}

function emitConsumableNachbuchungForPackItem(pi: ActivityPackItem) {
  emit('requestNachbuchung', {
    materialItemId: pi.materialItemId,
    materialLabel: pi.materialName,
    packSize: pi.packSize,
    packUnit: pi.packUnit,
    packStage: activePackStage.value,
  })
}

function emitConsumableNachbuchungForMaterial(materialItemId: string) {
  const pi = packItemForMaterialItemId(materialItemId)
  if (pi) emitConsumableNachbuchungForPackItem(pi)
}

async function submitConsumableInlineForMaterial(materialItemId: string) {
  const pi = packItemForMaterialItemId(materialItemId)
  if (!pi || consumableInlinePostingId.value) return
  const qty = consumableInlineQtyFor(materialItemId)
  if (qty < 1) return
  const bookable = consumableBookableRemaining(pi)
  if (qty > bookable) {
    if (props.canRequestConsumableNachbuchung) {
      emitConsumableNachbuchungForPackItem(pi)
      return
    }
    toast.error(t('activities.packList.consumableInlineOverBooked', { max: bookable }))
    return
  }
  consumableInlinePostingId.value = materialItemId
  try {
    await createActivityIssue(props.activityId, {
      material_item_id: materialItemId,
      type: 'consumption',
      quantity: qty,
      description: null,
    })
    consumableInlineQtyInputs.value = { ...consumableInlineQtyInputs.value, [materialItemId]: 0 }
    emit('activityItemsChanged')
    await loadAll()
    toast.success(t('activities.packList.consumableInlineBooked', { n: qty }))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.consumableInlineBookFailed'))
  } finally {
    consumableInlinePostingId.value = null
  }
}

/** Retour-Stufe: erledigt = retourniert + gebuchter Verbrauch */
function consumableReturnDoneQty(pi: ActivityPackItem): number {
  const returned = pi.quantityReturned ?? 0
  return returned + consumableBookedConsumptionQty(pi)
}

/** Verbrauchsmaterial: physisch noch retournierbar */
function consumablePhysicalReturnMax(pi: ActivityPackItem): number {
  const atEvent = looseQtyStillAtEventForReturn(pi)
  if (!pi.isConsumable) return atEvent
  const accountingLeft = Math.max(
    0,
    consumableTotalBookedQty(pi.materialItemId) -
      consumableBookedConsumptionQty(pi) -
      (pi.quantityReturned ?? 0),
  )
  return Math.min(atEvent, accountingLeft)
}

function resolveConsumableReturnQty(item: ActivityPackItem, moveQty: number): number {
  if (!isPackReturnStage(activePackStage.value) || !item.isConsumable) return moveQty
  return Math.min(moveQty, consumablePhysicalReturnMax(item))
}

function isReturnSectionCollapsed(key: string): boolean {
  return collapsedReturnSections.value[key] === true
}

function toggleReturnSection(key: string) {
  collapsedReturnSections.value = {
    ...collapsedReturnSections.value,
    [key]: !isReturnSectionCollapsed(key),
  }
}

/** Verbrauch offen: (gebucht + Nachlieferung) − retourniert − verbraucht. */
function consumableConsumptionRemaining(pi: ActivityPackItem): number {
  if (!pi.isConsumable) return 0
  const returned = pi.quantityReturned ?? 0
  const consumed = consumableBookedConsumptionQty(pi)
  return Math.max(0, consumableTotalBookedQty(pi.materialItemId) - returned - consumed)
}

/** Kurztext für Retour-Übersicht «Verbrauch» (rechte Spalte). */
function consumableOverviewDetailText(pi: ActivityPackItem): string {
  const initial = consumableInitialBookedQty(pi.materialItemId)
  const replenishment = replenishmentQtyForMaterial(pi.materialItemId)
  const issued = pi.quantityIssued ?? 0
  const consumed = consumableBookedConsumptionQty(pi)
  const returned = pi.quantityReturned ?? 0
  const stored = pi.quantityStored ?? 0
  const open = consumableConsumptionRemaining(pi)
  let text =
    replenishment > 0
      ? t('activities.packList.consumableOverviewSummaryReplenishment', {
          booked: initial,
          replenishment,
          consumed,
          returned,
        })
      : t('activities.packList.consumableOverviewSummary', {
          booked: initial > 0 ? initial : consumableTotalBookedQty(pi.materialItemId),
          consumed,
          returned,
        })
  if (issued > 0 && issued !== initial + replenishment) {
    text += ` · ${t('activities.packList.consumableOverviewIssued', { n: issued })}`
  }
  if (stored > 0) {
    text += ` · ${t('activities.packList.consumableOverviewStored', { n: stored })}`
  }
  if (open > 0) {
    text += ` · ${t('activities.packList.consumableOverviewRemaining', { n: open })}`
  }
  return text
}

function isConsumablePackLine(pi: ActivityPackItem): boolean {
  if (!pi.isConsumable) return false
  if (isOrphanShellWithoutPackContainer(pi)) return false
  if (isCrateShellPackItem(pi, packContainers.value)) return false
  return true
}

/** Verbrauch zu material_item_id (Behälter/Kistenzeile); optional Anzeigetext aus UI */
function emitConsumptionForMaterialId(
  materialItemId: string,
  hints?: { materialName?: string; linkedContainerLabel?: string | null },
) {
  if (!materialItemId || !showPackConsumptionActions.value) return
  const pi = packItems.value.find((p) => p.materialItemId === materialItemId)
  if (pi) {
    if (!showConsumableConsumptionForPackItem(pi)) return
    emitConsumptionFromPackItem(pi)
    return
  }
  if (!showPackConsumptionActions.value) return
  emit('openConsumptionModal', {
    materialItemId,
    materialName: (hints?.materialName && hints.materialName.trim()) || t('activities.common.material'),
    packSize: null,
    packUnit: null,
    linkedContainerLabel: hints?.linkedContainerLabel ?? null,
  })
}

/** Meldung zu einer material_item_id (Behälterzeile / Kisten-Stückliste), auch wenn keine lose Pack-Zeile existiert */
function emitIssueWizardByMaterialId(
  materialItemId: string,
  issueType: 'loss' | 'repair',
  quantity?: number,
) {
  if (!showPackIssueActions.value || !materialItemId) return
  const shellPi = findPhysicalComboShellPackItem(materialItemId, packItems.value)
  if (shellPi) {
    void tryOpenPhysicalComboIssuePicker(shellPi, issueType, quantity).then((handled) => {
      if (!handled) {
        emitIssueWizardPayload({ materialItemId, issueType, quantity })
      }
    })
    return
  }
  const pi = packItems.value.find((p) => p.materialItemId === materialItemId)
  if (pi) {
    emitIssueWizard(pi, issueType, quantity)
  } else {
    emitIssueWizardPayload({ materialItemId, issueType, quantity })
  }
}

function isPackMaterialConsumable(materialItemId: string): boolean {
  return packItems.value.some((p) => p.materialItemId === materialItemId && p.isConsumable)
}

function shellMaterialIdForContainer(containerId: string): string | null {
  const id = shellPackItemForContainer(containerId)?.materialItemId
  return id ?? null
}

const packItems = ref<ActivityPackItem[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const initLoading = ref(false)
const moveAllLoading = ref(false)
const isTransitioningPackWorkflow = ref(false)
const movingId = ref<string | null>(null)

const activePackStage = ref<PackStage>('confirmed_packed')
/** Letzter Aktivitäts-Status nach loadAll — Tab nur bei Statuswechsel zurücksetzen. */
const lastLoadedActivityStatus = ref<string | null>(null)
/** Späterer Pipeline-Tab als Aktivitäts-Status (z. B. «weiter zur Retour») — über Reloads beibehalten. */
const userPackStageAheadOfStatus = ref<PackStage | null>(null)

const packStageKeys = computed(() =>
  packStageKeysForProfileAndRole(packWorkflowProfile.value, canManageMaterials.value),
)

const packStagesForUi = computed(() =>
  packStageKeys.value.map((key) => ({
    key,
    leftLabel: t(`activities.packList.stages.${key}.left`),
    rightLabel: t(`activities.packList.stages.${key}.right`),
  })),
)

const activeStageConfig = computed(() => {
  const keys = packStageKeys.value
  const key = keys.includes(activePackStage.value) ? activePackStage.value : keys[0] ?? 'confirmed_packed'
  return {
    key,
    leftLabel: t(`activities.packList.stages.${key}.left`),
    rightLabel: t(`activities.packList.stages.${key}.right`),
  }
})

const collapsedGroups = ref<Record<string, boolean>>({})
const progressPendingOpen = ref(false)
/** Retour-Spalte: Akkordeons standardmässig offen (true = zugeklappt). */
const collapsedReturnSections = ref<Record<string, boolean>>({})

const packIssues = ref<ActivityIssueReportRow[]>([])
const activityItemsForAccounting = ref<ActivityItemRow[]>([])
const moveQtyInputs = ref<Record<string, number>>({})
const moveBackQtyInputs = ref<Record<string, number>>({})

/** Pack-Kisten (Bestätigt → Gepackt), optional zur lose-Menge */
const packContainers = ref<ActivityPackContainer[]>([])
const containerItemsByContainerId = ref<Record<string, ActivityPackContainerItem[]>>({})
const collapsedPackContainers = ref<Record<string, boolean>>({})
/** true = Unterabschnitt zugeklappt; undefined = zugeklappt (Standard) */
const collapsedPackContainerSubsections = ref<Record<string, boolean>>({})
/** Lager-Vorlage pro Pack-Behälter (material_id aus Kisteninhalt) */
const containerWarehouseTemplateByContainerId = ref<Record<string, Set<string>>>({})
const containerWarehouseContentsByContainerId = ref<Record<string, RackContentsItem[]>>({})
const comboComponentsByMaterialId = ref<Record<string, ComboComponent[]>>({})
const activityCrateCheckSnapshots = ref<Record<string, CrateCheckSnapshot>>({})
const useCrateRealityByPackItemId = ref<Record<string, boolean>>({})
/** Menge zum Herausnehmen aus Behälter (Pfeil + Eingabe), Schlüssel containerId:itemId */
const containerPullQtyInputs = ref<Record<string, number>>({})
/** Gepackt → Am Event: Teilmenge aus Behälterzeile ins Event (Schlüssel wie pull) */
const containerIssueLineInputs = ref<Record<string, number>>({})
/** Am Event → Gepackt: Teilmenge aus Behälterzeile zurück (Schlüssel wie oben) */
const containerUnissueLineInputs = ref<Record<string, number>>({})

/** Aktives Ziel: lose Menge, Behälter oder Phys.-Kombi (ohne Lager-Kiste) */
type ActivePackTarget =
  | { kind: 'loose' }
  | { kind: 'container'; containerId: string }
  | { kind: 'combo'; packItemId: string }
const activePackTarget = ref<ActivePackTarget | null>(null)
const containerMutationLoading = ref(false)
/** Ganzer Behälter: Issue / Return / Unissue */
const containerBulkLoadingId = ref<string | null>(null)

const showAddContainerModal = ref(false)
const stockContainerBatches = ref<ContainerBatch[]>([])
const stockBatchesLoading = ref(false)
const selectedStockBatchId = ref('')

/** Lager-Kisten, die noch nicht dieser Packliste zugeordnet sind (pro Aktivität einmal pro Batch); leer zuerst */
const availableStockBatches = computed(() => {
  const used = new Set(
    packContainers.value.map((c) => c.container_batch_id).filter((id): id is string => !!id),
  )
  const rows = stockContainerBatches.value.filter((b) => !used.has(b.id))
  return [...rows].sort((a, b) => {
    const score = (x: ContainerBatch) => (x.storage_empty === true ? 0 : x.storage_empty === false ? 1 : 2)
    const d = score(a) - score(b)
    if (d !== 0) return d
    const la = (a.display_label || a.label || a.material_name || '').toString()
    const lb = (b.display_label || b.label || b.material_name || '').toString()
    return la.localeCompare(lb, locale.value)
  })
})

const canSubmitAddContainer = computed(() => {
  if (stockBatchesLoading.value) return false
  return !!selectedStockBatchId.value
})

const shellForwardModalOpen = ref(false)
const shellForwardItem = ref<ActivityPackItem | null>(null)
const shellForwardMoveQty = ref(0)
const shellForwardSections = ref<PackCrateShellPeekSection[]>([])
const shellForwardLooseStock = ref<Record<string, number>>({})
const shellForwardStockLoading = ref(false)
const shellForwardSubmitting = ref(false)
const shellForwardSubmitError = ref<string | null>(null)
const shellForwardGroupMode = computed(() => !canManageMaterials.value)
const shellForwardCheckOnlyMode = computed(() => shellForwardPendingAction.value.kind === 'check_only')
const shellForwardHistoryReplenishByKey = ref<Record<string, boolean>>({})
const shellForwardHistoryPrefillHint = ref<string | null>(null)
const shellForwardRepackIssueReviews = ref<Record<string, 'ok' | 'problem' | null>>({})
const shellForwardEmbeddedIssuesByLineKey = ref<Record<string, ActivityIssueReportRow[]>>({})
const shellForwardOrphanIssues = ref<ActivityIssueReportRow[]>([])
const shellForwardInitialLineReviews = ref<Record<string, ShellForwardLineReview> | null>(null)

type ShellCheckDraft = {
  lineReviews: Record<string, ShellForwardLineReview>
  historyReplenishByKey: Record<string, boolean>
}

const shellCheckDraftByPackItemId = ref<Record<string, ShellCheckDraft>>({})

type ShellForwardPendingAction =
  | { kind: 'pack_move' }
  | { kind: 'issue_container'; containerId: string }
  | { kind: 'issue_container_shell'; containerId: string }
  | { kind: 'issue_container_line'; containerId: string; containerItemId: string; qty: number }
  | { kind: 'return_container_modal'; containerId: string }
  | { kind: 'return_container_shell'; containerId: string; qty: number }
  | { kind: 'return_container_line'; containerId: string; containerItemId: string; qty: number }
  | { kind: 'check_only' }

const shellForwardPendingAction = ref<ShellForwardPendingAction>({ kind: 'pack_move' })

const departmentId = computed(() => (props.departmentId ?? '').trim())

const shellForwardLabel = computed(() => {
  const pi = shellForwardItem.value
  if (!pi) return ''
  const c = packShellContainerForPackItem(pi, packContainers.value)
  return (c?.label ?? pi.materialName).trim() || pi.materialName
})

const shellForwardContainerBatchId = computed(() => {
  const pi = shellForwardItem.value
  if (!pi) return null
  const c = packShellContainerForPackItem(pi, packContainers.value)
  return c?.container_batch_id ?? pi.linkedContainerBatchId ?? null
})

const shellForwardEmptyHint = computed(() => {
  const pi = shellForwardItem.value
  if (!pi) return ''
  if (packShellContainerForPackItem(pi, packContainers.value)) {
    return t('activities.packList.cratePeekEmptyLinkedCrate')
  }
  return t('activities.packList.cratePeekNoShellYet')
})

function peekSectionTitles() {
  return {
    all: t('activities.packList.shellForwardSectionAll'),
    fixed: t('activities.packList.shellForwardSectionFixed'),
    extra: t('activities.packList.shellForwardSectionExtra'),
  }
}

function shellCrateCheckDoneForPackItem(pi: ActivityPackItem): boolean {
  const leg = packCrateCheckLegForStage(activePackStage.value)
  if (!leg || !packCrateCheckUserId.value) return false
  const snaps = activityCrateCheckSnapshots.value
  return Boolean(snaps[crateCheckSnapshotKey(pi.id, leg)])
}

function crateCheckSnapForPackItem(
  pi: ActivityPackItem,
  leg?: PackCrateCheckLeg,
): CrateCheckSnapshot | undefined {
  const resolved = leg ?? packCrateCheckLegForStage(activePackStage.value)
  if (!resolved) return undefined
  return activityCrateCheckSnapshots.value[crateCheckSnapshotKey(pi.id, resolved)]
}

function isShellCrateCheckEligible(pi: ActivityPackItem): boolean {
  if (pi.materialType === 'physical_combo') return true
  return packShellContainerForPackItem(pi, packContainers.value) != null
}

/** Kistencheck: je eingeloggtem Benutzer 1× pro Etappe (outbound / return / warehouse_store). */
function needsShellCratePresenceConfirm(pi: ActivityPackItem): boolean {
  const stage = activePackStage.value
  if (!isPackCrateCheckStage(stage)) return false
  if (!isShellCrateCheckEligible(pi)) return false
  if (shellCrateCheckDoneForPackItem(pi)) return false
  return true
}

function showRepeatShellCrateCheckButton(pi: ActivityPackItem): boolean {
  return (
    !canManageMaterials.value &&
    isPackCrateCheckStage(activePackStage.value) &&
    isShellCrateCheckEligible(pi)
  )
}

/** Kistencheck-Button: ausstehend oder (Gruppe) erneut prüfen — z. B. rechts unter «Ohne Behälter». */
function showShellCrateCheckButton(pi: ActivityPackItem): boolean {
  if (!showPackOperateControls.value) return false
  if (!isPackCrateCheckStage(activePackStage.value)) return false
  if (!isShellCrateCheckEligible(pi)) return false
  if (needsShellCratePresenceConfirm(pi)) return true
  return showRepeatShellCrateCheckButton(pi)
}

function shellCrateCheckButtonLabel(pi: ActivityPackItem): string {
  return needsShellCratePresenceConfirm(pi)
    ? t('activities.packList.startCrateCheck')
    : t('activities.packList.repeatCrateCheck')
}

/** Phys.-Kombi / Kiste: Hinweg-Kistencheck noch offen (auch wenn schon teilweise transportiert/ausgegeben). */
function packItemNeedsOutboundCrateCheck(pi: ActivityPackItem): boolean {
  if (!isShellCrateCheckEligible(pi)) return false
  if (shellCrateCheckDoneForPackItem(pi)) return false
  return (pi.quantityPacked ?? 0) > 0
}

const pendingOutboundCrateCheckItems = computed(() =>
  packItems.value.filter((pi) => packItemNeedsOutboundCrateCheck(pi)),
)

const pendingOutboundCrateCheckLabels = computed(() =>
  pendingOutboundCrateCheckItems.value.map((pi) => {
    const c = packShellContainerForPackItem(pi, packContainers.value)
    const label = (c?.label ?? pi.linkedContainerLabel ?? '').trim()
    return label && label !== pi.materialName ? `${label} – ${pi.materialName}` : pi.materialName
  }),
)

const showPendingOutboundCrateCheckBanner = computed(
  () =>
    showPackOperateControls.value &&
    isPackCrateCheckStage(activePackStage.value) &&
    isPackForwardToEventStage(activePackStage.value) &&
    pendingOutboundCrateCheckItems.value.length > 0,
)

async function openShellCrateCheckOnlyModal(item: ActivityPackItem) {
  const max = Math.max(1, packIssueForwardMax(item))
  await openShellCrateForwardModal(item, max, { kind: 'check_only' })
}

function shellCheckLinesFromSections(sections: PackCrateShellPeekSection[]): ShellForwardCheckLine[] {
  const out: ShellForwardCheckLine[] = []
  for (const sec of sections) {
    const isExtra = sec.subsectionKey === 'extra'
    for (const line of sec.lines) {
      out.push({
        key: shellForwardLineKey(sec.subsectionKey, line.id),
        subsectionKey: sec.subsectionKey,
        materialName: line.materialName,
        quantity: shellForwardExpectedQty(isExtra, line.quantity),
        materialItemId: (line.materialItemId ?? '').trim() || null,
        isExtra,
        serialHint: (line.serialHint ?? '').trim() || null,
      })
    }
  }
  return out
}

function shellForwardSectionsForItem(item: ActivityPackItem): PackCrateShellPeekSection[] {
  const shellC = packShellContainerForPackItem(item, packContainers.value)
  const warehouseMids = shellC ? containerWarehouseTemplateByContainerId.value[shellC.id] : undefined
  const warehouseContents = shellC ? containerWarehouseContentsByContainerId.value[shellC.id] : undefined
  const comboComponents = comboComponentsByMaterialId.value[item.materialItemId] ?? []
  return crateShellForwardPeekSections(
    item,
    packContainers.value,
    containerItemsByContainerId.value,
    warehouseMids,
    warehouseContents,
    comboComponents,
    peekSectionTitles(),
    t('activities.common.material'),
  )
}

function ensureShellCheckDraft(item: ActivityPackItem): ShellCheckDraft {
  const existing = shellCheckDraftByPackItemId.value[item.id]
  if (existing) return existing
  const sections = shellForwardSectionsForItem(item)
  const lineReviews: Record<string, ShellForwardLineReview> = {}
  for (const line of shellCheckLinesFromSections(sections)) {
    lineReviews[line.key] = defaultLineReview(line.quantity)
  }
  const draft: ShellCheckDraft = { lineReviews, historyReplenishByKey: {} }
  shellCheckDraftByPackItemId.value = { ...shellCheckDraftByPackItemId.value, [item.id]: draft }
  return draft
}

/** Inline-Check aus — Kistencheck nur beim Verschieben (Modal). */
function shellCheckPendingForPackItem(_pi: ActivityPackItem): boolean {
  return false
}

function shellCheckReviewForLine(packItemId: string, key: string, expectedQty: number): ShellForwardLineReview {
  const draft = shellCheckDraftByPackItemId.value[packItemId]
  return draft?.lineReviews[key] ?? defaultLineReview(expectedQty)
}

function shellCheckHistoryReplenishForKey(packItemId: string, key: string): boolean {
  return Boolean(shellCheckDraftByPackItemId.value[packItemId]?.historyReplenishByKey[key])
}

function shellCheckPatchLine(
  packItemId: string,
  key: string,
  expectedQty: number,
  isExtra: boolean,
  patch: Partial<ShellForwardLineReview>,
) {
  const pi = packItems.value.find((p) => p.id === packItemId)
  if (!pi) return
  const draft = ensureShellCheckDraft(pi)
  const cur = draft.lineReviews[key] ?? defaultLineReview(expectedQty)
  const touchesLineCount =
    patch.countedQty !== undefined || patch.status !== undefined || patch.resolution !== undefined
  const merged = touchesLineCount
    ? applyCountedQtyToReview({ ...cur, ...patch }, expectedQty, isExtra)
    : { ...cur, ...patch }
  draft.lineReviews = { ...draft.lineReviews, [key]: merged }
  shellCheckDraftByPackItemId.value = { ...shellCheckDraftByPackItemId.value, [packItemId]: { ...draft } }
  if (shellForwardItem.value?.id === packItemId) {
    shellForwardInitialLineReviews.value = { ...draft.lineReviews }
  }
}

function shellCheckSetLineOk(
  packItemId: string,
  key: string,
  expectedQty: number,
  isExtra: boolean,
) {
  shellCheckPatchLine(packItemId, key, expectedQty, isExtra, {
    countedQty: expectedQty,
    status: 'ok',
    resolution: null,
    inventoryPhase: 'none',
  })
}

function closeShellForwardModal() {
  shellForwardModalOpen.value = false
  shellForwardItem.value = null
  shellForwardPendingAction.value = { kind: 'pack_move' }
}

async function openShellCrateForwardModal(
  item: ActivityPackItem,
  moveQty: number,
  pending: ShellForwardPendingAction = { kind: 'pack_move' },
) {
  shellForwardItem.value = item
  shellForwardMoveQty.value = moveQty
  shellForwardPendingAction.value = pending
  const shellC = packShellContainerForPackItem(item, packContainers.value)
  const warehouseMids = shellC
    ? containerWarehouseTemplateByContainerId.value[shellC.id]
    : undefined
  const warehouseContents = shellC
    ? containerWarehouseContentsByContainerId.value[shellC.id]
    : undefined
  let comboComponents = comboComponentsByMaterialId.value[item.materialItemId] ?? []
  if (item.materialType === 'physical_combo' && comboComponents.length === 0) {
    try {
      comboComponents = await getComboComponents(item.materialItemId)
      comboComponentsByMaterialId.value = {
        ...comboComponentsByMaterialId.value,
        [item.materialItemId]: comboComponents,
      }
    } catch {
      comboComponents = []
    }
  }

  shellForwardSections.value = shellForwardSectionsForItem(item)
  shellForwardHistoryReplenishByKey.value = {}
  shellForwardHistoryPrefillHint.value = null
  shellForwardInitialLineReviews.value = null
  shellForwardRepackIssueReviews.value = {}
  shellForwardEmbeddedIssuesByLineKey.value = {}
  shellForwardOrphanIssues.value = []
  shellForwardLooseStock.value = {}
  shellForwardSubmitError.value = null

  const mids = new Set<string>()
  for (const sec of shellForwardSections.value) {
    for (const line of sec.lines) {
      const mid = (line.materialItemId ?? '').trim()
      if (mid) mids.add(mid)
    }
  }

  shellForwardStockLoading.value = true
  try {
    const [stock, history, issues] = await Promise.all([
      mids.size > 0
        ? getPackCrateCheckLooseStock(props.activityId, item.id, [...mids])
        : Promise.resolve({} as Record<string, number>),
      getActivityHistory(props.activityId),
      showPackIssueActions.value ? getActivityIssues(props.activityId) : Promise.resolve([]),
    ])
    shellForwardLooseStock.value = stock
    const snaps = indexLatestCrateCheckByPackItemAndLeg(history, {
      userId: packCrateCheckUserId.value,
    })
    activityCrateCheckSnapshots.value = { ...activityCrateCheckSnapshots.value, ...snaps }

    const draft = shellCheckDraftByPackItemId.value[item.id]
    if (draft?.lineReviews && Object.keys(draft.lineReviews).length > 0) {
      shellForwardInitialLineReviews.value = draft.lineReviews
    } else {
      const leg = packCrateCheckLegForStage(activePackStage.value) ?? 'outbound'
      const snap = snaps[crateCheckSnapshotKey(item.id, leg)]
      const checkLines = shellCheckLinesFromSections(shellForwardSections.value)
      if (snap && checkLines.length > 0) {
        const { reviews, replenishByKey } = buildGroupPrefillLineReviewsFromSnapshot(
          checkLines,
          snap,
        )
        shellForwardInitialLineReviews.value = reviews
        shellForwardHistoryReplenishByKey.value = replenishByKey
        shellForwardHistoryPrefillHint.value = formatGroupCrateCheckPrefillHint(snap, t)
      }
    }

    if (showPackIssueActions.value && issues.length > 0) {
      shellForwardOrphanIssues.value = issues.filter((r) => !r.resolved)
    }
    shellForwardModalOpen.value = true
  } catch {
    shellForwardLooseStock.value = {}
    shellForwardModalOpen.value = true
  } finally {
    shellForwardStockLoading.value = false
  }
}

function mapShellForwardSubmitError(raw: string | undefined): string {
  const msg = (raw ?? '').trim()
  if (!msg) return t('activities.packList.shellForwardCheckFailed')
  if (msg.includes('Kombi') || msg.includes('Rakokiste') || msg.includes('Pack-Kisten')) {
    return t('activities.packList.shellForwardSubmitErrorNotCrate')
  }
  if (msg.includes('Packliste') || msg.includes('nicht bearbeitet')) {
    return t('activities.packList.shellForwardSubmitErrorNotEditable')
  }
  return t('activities.packList.shellForwardCheckFailed')
}

async function onShellForwardSubmit(payload: PackCrateCheckRequest) {
  const item = shellForwardItem.value
  const qty = shellForwardMoveQty.value
  if (!item || qty < 1) return
  shellForwardSubmitting.value = true
  shellForwardSubmitError.value = null
  try {
    const leg = packCrateCheckLegForStage(activePackStage.value) ?? 'outbound'
    const res = await postPackCrateCheck(props.activityId, item.id, {
      ...payload,
      check_leg: payload.check_leg ?? leg,
    })
    if (!res.ok) {
      shellForwardSubmitError.value = mapShellForwardSubmitError(
        (res as { error?: string }).error,
      )
      return
    }
    await refreshCrateCheckSnapshots()
    const nextDrafts = { ...shellCheckDraftByPackItemId.value }
    delete nextDrafts[item.id]
    shellCheckDraftByPackItemId.value = nextDrafts
    const wasCheckOnly = shellForwardPendingAction.value.kind === 'check_only'
    closeShellForwardModal()
    initContainerIssueLineInputs()
    await executeShellForwardPendingAfterCheck(item)
    if (wasCheckOnly) {
      toast.success(t('activities.packList.shellForwardCheckSavedToast'))
    } else if (payload.result === 'ok') {
      toast.success(t('activities.packList.shellForwardCheckOkToast'))
    } else if (shellForwardGroupMode.value) {
      toast.info(t('activities.packList.shellForwardIncompleteToastGroup'))
    } else {
      toast.info(t('activities.packList.shellForwardIncompleteToastMw'))
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    shellForwardSubmitError.value = mapShellForwardSubmitError(e.response?.data?.error || e.message)
  } finally {
    shellForwardSubmitting.value = false
  }
}

function onShellForwardRepackReview(issueId: string, status: 'ok' | 'problem') {
  shellForwardRepackIssueReviews.value = {
    ...shellForwardRepackIssueReviews.value,
    [issueId]: status,
  }
}

/** Retour: Kisten-Shell zurück (Gepackt ← Am Event) */
const shellBackModalOpen = ref(false)
const shellBackItem = ref<ActivityPackItem | null>(null)
const shellBackMoveQty = ref(0)
const shellBackAcknowledged = ref(false)
const shellBackSubmitting = ref(false)

const returnCrateModalOpen = ref(false)
const returnCrateModalContainer = ref<ActivityPackContainer | null>(null)
const returnCrateModalLines = ref<ReturnCrateLineEdit[]>([])
const returnCrateModalSubmitting = ref(false)
const returnCrateModalContentsLoading = ref(false)
const returnCrateModalContentsError = ref(false)

type ReturnCrateBatchStep = {
  kind: 'shell' | 'line'
  containerItemId?: string
  qty: number
}

type PendingReturnCrateBatch = {
  containerId: string
  remaining: ReturnCrateBatchStep[]
}

const pendingReturnCrateBatch = ref<PendingReturnCrateBatch | null>(null)
const containerReturnLineInputs = ref<Record<string, number>>({})
const containerShellReturnInputs = ref<Record<string, number>>({})
const containerStoreLineInputs = ref<Record<string, number>>({})
const containerShellStoreInputs = ref<Record<string, number>>({})

function returnProgressNotTakenQty(pi: ActivityPackItem): number {
  if (!isPackReturnStage(activePackStage.value)) return 0
  return notTakenQtyForReturn(pi)
}

function buildReturnCratePartition(containerId: string): ReturnCratePartitionView {
  const c = packContainers.value.find((x) => x.id === containerId)
  const empty: ReturnCratePartitionView = {
    shellQty: 0,
    shellIsExtra: false,
    shellMaterialName: '',
    extraLines: [],
    standardLines: [],
    hasWarehouseTemplate: false,
  }
  if (!c) return empty

  const warehouseMids = containerWarehouseTemplateByContainerId.value[containerId]
  const hasWarehouseTemplate = (warehouseMids?.size ?? 0) > 0
  const shellQty =
    containerInnerReturnableUnits(containerId) > 0 ? 0 : containerShellStillAtEventQty(containerId)
  const shellMid = (c.container_material_item_id ?? '').trim()
  const shellPi = shellMid ? packItems.value.find((p) => p.materialItemId === shellMid) : undefined
  const shellIsExtra = Boolean(shellMid && shellQty > 0 && warehouseMids && !warehouseMids.has(shellMid))

  const standardLines: ActivityPackContainerItem[] = []
  const extraLines: ActivityPackContainerItem[] = []
  for (const sec of packContainerItemSectionsForContainer(c)) {
    for (const ci of sec.lines) {
      if (isNonActionableContainerLine(ci)) continue
      if (containerLineRemainingReturn(ci, containerId) <= 0) continue
      if (sec.subsectionKey === 'extra') extraLines.push(ci)
      else standardLines.push(ci)
    }
  }

  return {
    shellQty,
    shellIsExtra,
    shellMaterialName: shellPi?.materialName ?? '',
    extraLines,
    standardLines,
    hasWarehouseTemplate,
  }
}

const returnCrateModalPartition = computed((): ReturnCratePartitionView => {
  const c = returnCrateModalContainer.value
  if (!c) {
    return {
      shellQty: 0,
      shellIsExtra: false,
      shellMaterialName: '',
      extraLines: [],
      standardLines: [],
      hasWarehouseTemplate: false,
    }
  }
  return buildReturnCratePartition(c.id)
})

const returnCrateModalNoLinkedBatch = computed(
  () => !(returnCrateModalContainer.value?.container_batch_id ?? '').trim(),
)

function returnCrateConsumableState(materialItemId: string | null): {
  consumptionDone: boolean
  consumptionOpen: number
} {
  if (!materialItemId) return { consumptionDone: true, consumptionOpen: 0 }
  const pi = packItems.value.find((p) => p.materialItemId === materialItemId)
  if (!pi?.isConsumable) return { consumptionDone: false, consumptionOpen: 0 }
  const consumptionOpen = consumableConsumptionRemaining(pi)
  return { consumptionDone: consumptionOpen <= 0, consumptionOpen }
}

const returnCrateModalSubmitDisabled = computed(() => {
  if (returnCrateModalSubmitting.value || returnCrateModalContentsLoading.value) return true

  const openConsumables = returnCrateModalLines.value.some(
    (line) => line.isConsumable && !line.consumptionDone && line.consumptionOpen > 0,
  )
  if (openConsumables) return true

  const hasReturnSelection = returnCrateModalLines.value.some(
    (line) => !line.isConsumable && line.included && line.qty > 0,
  )
  if (hasReturnSelection) return false

  const hasReturnableNonConsumables = returnCrateModalLines.value.some((line) => !line.isConsumable && line.max > 0)
  return hasReturnableNonConsumables
})

function buildReturnCrateModalLines(containerId: string): ReturnCrateLineEdit[] {
  const partition = buildReturnCratePartition(containerId)
  const container = packContainers.value.find((x) => x.id === containerId)
  const shellMaterialId = (container?.container_material_item_id ?? '').trim() || null
  const extraIds = new Set(partition.extraLines.map((line) => line.id))
  const lines: ReturnCrateLineEdit[] = []

  for (const ci of [...partition.extraLines, ...partition.standardLines]) {
    const max = containerLineRemainingReturn(ci, containerId)
    const materialItemId = ci.material_item_id ?? null
    const isConsumable = materialItemId ? isPackMaterialConsumable(materialItemId) : false
    const consumption = isConsumable ? returnCrateConsumableState(materialItemId) : null
    lines.push({
      id: ci.id,
      kind: 'line',
      containerItemId: ci.id,
      materialItemId,
      materialName: ci.material_name || t('activities.common.material'),
      max,
      issued: ci.quantity_issued ?? 0,
      included: !isConsumable,
      qty: isConsumable ? 0 : max,
      isExtra: extraIds.has(ci.id),
      isConsumable,
      consumptionDone: consumption?.consumptionDone ?? false,
      consumptionOpen: consumption?.consumptionOpen ?? 0,
    })
  }

  if (partition.shellQty > 0) {
    const shellConsumable = shellMaterialId ? isPackMaterialConsumable(shellMaterialId) : false
    const shellConsumption = shellConsumable ? returnCrateConsumableState(shellMaterialId) : null
    lines.push({
      id: 'shell',
      kind: 'shell',
      materialItemId: shellMaterialId,
      materialName: partition.shellMaterialName || t('activities.packList.shellMaterialLine'),
      max: partition.shellQty,
      issued: partition.shellQty,
      included: !shellConsumable,
      qty: shellConsumable ? 0 : partition.shellQty,
      isExtra: partition.shellIsExtra,
      isConsumable: shellConsumable,
      consumptionDone: shellConsumption?.consumptionDone ?? false,
      consumptionOpen: shellConsumption?.consumptionOpen ?? 0,
    })
  }

  return lines
}

function syncReturnCrateModalLines(): void {
  const container = returnCrateModalContainer.value
  if (!container) return
  const prevById = new Map(returnCrateModalLines.value.map((line) => [line.id, line]))
  returnCrateModalLines.value = buildReturnCrateModalLines(container.id).map((line) => {
    if (line.isConsumable) return line
    const prev = prevById.get(line.id)
    if (!prev) return line
    return {
      ...line,
      included: prev.included,
      qty: Math.min(Math.max(0, prev.qty), line.max),
    }
  })
}

function closeReturnCrateModal(): void {
  returnCrateModalOpen.value = false
  returnCrateModalContainer.value = null
  returnCrateModalLines.value = []
  returnCrateModalContentsLoading.value = false
  returnCrateModalContentsError.value = false
}

async function openReturnCrateModal(c: ActivityPackContainer): Promise<void> {
  returnCrateModalContainer.value = c
  returnCrateModalLines.value = []
  returnCrateModalContentsError.value = false
  returnCrateModalContentsLoading.value = true
  returnCrateModalOpen.value = true
  try {
    await loadWarehouseTemplatesForContainers()
    returnCrateModalLines.value = buildReturnCrateModalLines(c.id)
  } catch {
    returnCrateModalContentsError.value = true
    returnCrateModalLines.value = buildReturnCrateModalLines(c.id)
  } finally {
    returnCrateModalContentsLoading.value = false
  }
}

async function continueReturnCrateBatch(): Promise<void> {
  const job = pendingReturnCrateBatch.value
  if (!job) return

  while (job.remaining.length > 0) {
    const step = job.remaining[0]
    if (step.kind === 'shell') {
      job.remaining.shift()
      await returnContainerShellToWarehouse(job.containerId, step.qty)
      continue
    }

    const containerLines = containerItemsByContainerId.value[job.containerId] ?? []
    const ci = containerLines.find((line) => line.id === step.containerItemId)
    if (!ci) {
      job.remaining.shift()
      continue
    }

    const pi = packItems.value.find((p) => p.materialItemId === ci.material_item_id)
    if (shouldOpenConsumptionModalOnReturn(pi)) {
      beginConsumableReturnForContainerLine(job.containerId, ci, step.qty)
      return
    }

    job.remaining.shift()
    await executeReturnContainerLineToWarehouse(job.containerId, ci, step.qty)
  }

  pendingReturnCrateBatch.value = null
  toast.success(t('activities.packList.toastReturnContainer'))
}

function returnCrateModalCanCompleteWithoutMoves(): boolean {
  if (returnCrateModalLines.value.length < 1) return false
  const openConsumables = returnCrateModalLines.value.some(
    (line) => line.isConsumable && !line.consumptionDone && line.consumptionOpen > 0,
  )
  if (openConsumables) return false
  const hasReturnableNonConsumables = returnCrateModalLines.value.some(
    (line) => !line.isConsumable && line.max > 0,
  )
  return !hasReturnableNonConsumables
}

async function onReturnCrateModalSubmit(): Promise<void> {
  const c = returnCrateModalContainer.value
  if (!c || returnCrateModalSubmitDisabled.value) return

  const steps: ReturnCrateBatchStep[] = returnCrateModalLines.value
    .filter((line) => !line.isConsumable && line.included && line.qty > 0)
    .map((line) => ({
      kind: line.kind === 'shell' ? 'shell' : 'line',
      containerItemId: line.containerItemId,
      qty: line.qty,
    }))
    .sort((a, b) => (a.kind === 'shell' ? 1 : 0) - (b.kind === 'shell' ? 1 : 0))

  if (steps.length === 0) {
    if (!returnCrateModalCanCompleteWithoutMoves()) return
    closeReturnCrateModal()
    toast.success(t('activities.packList.toastReturnCrateCheckComplete'))
    return
  }

  returnCrateModalSubmitting.value = true
  try {
    pendingReturnCrateBatch.value = { containerId: c.id, remaining: steps }
    closeReturnCrateModal()
    await continueReturnCrateBatch()
  } finally {
    returnCrateModalSubmitting.value = false
  }
}

function initContainerReturnLineInputs(): void {
  const next: Record<string, number> = { ...containerReturnLineInputs.value }
  for (const c of packContainers.value) {
    for (const sec of packContainerItemSectionsForContainer(c)) {
      for (const ci of sec.lines) {
        if (isNonActionableContainerLine(ci)) continue
        const max = containerLineRemainingReturn(ci, c.id)
        if (max < 1) continue
        const k = containerIssueLineKey(c.id, ci.id)
        if (next[k] == null || !Number.isFinite(next[k]) || next[k] < 1) {
          next[k] = max
        }
      }
    }
  }
  containerReturnLineInputs.value = next
}

function containerShellReturnKey(containerId: string): string {
  return `shell-return:${containerId}`
}

function initContainerShellReturnInputs(): void {
  const next: Record<string, number> = { ...containerShellReturnInputs.value }
  for (const c of packContainers.value) {
    const max = containerShellStillAtEventQty(c.id)
    if (max < 1) continue
    const k = containerShellReturnKey(c.id)
    if (next[k] == null || !Number.isFinite(next[k]) || next[k] < 1) {
      next[k] = max
    }
  }
  containerShellReturnInputs.value = next
}

function containerShellReturnInputValue(containerId: string): number {
  const max = Math.max(1, containerShellStillAtEventQty(containerId))
  const k = containerShellReturnKey(containerId)
  const raw = containerShellReturnInputs.value[k]
  if (Number.isFinite(raw) && raw > 0) return Math.min(raw, max)
  containerShellReturnInputs.value = { ...containerShellReturnInputs.value, [k]: max }
  return max
}

function setContainerShellReturnInput(containerId: string, value: number | string): void {
  const max = Math.max(1, containerShellStillAtEventQty(containerId))
  const k = containerShellReturnKey(containerId)
  let qty = Math.floor(Number(value))
  if (!Number.isFinite(qty) || qty < 1) qty = max
  containerShellReturnInputs.value = { ...containerShellReturnInputs.value, [k]: Math.min(qty, max) }
}

async function executeReturnContainerShellToWarehouse(
  containerId: string,
  shell: ActivityPackItem,
  qty: number,
): Promise<void> {
  containerMutationLoading.value = true
  try {
    await postMovePackItem(props.activityId, shell.id, { stage: 'returned', quantity: qty })
    const items = await getPackItems(props.activityId)
    packItems.value = items
    initMoveQtyInputs()
    initContainerShellReturnInputs()
    await loadContainersData()
    emit('activityItemsChanged')
    toast.success(t('activities.packList.toastReturnLineSuccess', { qty }))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastReturnFailed'))
  } finally {
    containerMutationLoading.value = false
  }
}

async function returnContainerShellToWarehouse(containerId: string, qtyOverride?: number): Promise<void> {
  if (!isPackReturnStage(activePackStage.value)) return
  if (containerInnerReturnableUnits(containerId) > 0) {
    toast.error(t('activities.packList.toastReturnShellAfterContents'))
    return
  }
  const shell = shellPackItemForContainer(containerId)
  if (!shell) return
  const max = containerShellStillAtEventQty(containerId)
  if (max < 1) return
  let qty =
    qtyOverride != null
      ? Math.floor(qtyOverride)
      : Math.floor(containerShellReturnInputValue(containerId))
  qty = Math.min(Math.max(1, qty), max)
  setContainerShellReturnInput(containerId, qty)
  if (needsShellCratePresenceConfirm(shell)) {
    await openShellCrateForwardModal(shell, qty, { kind: 'return_container_shell', containerId, qty })
    return
  }
  await executeReturnContainerShellToWarehouse(containerId, shell, qty)
}

function containerReturnLineInputValue(containerId: string, ci: ActivityPackContainerItem): number {
  const k = containerIssueLineKey(containerId, ci.id)
  const max = Math.max(1, containerLineRemainingReturn(ci, containerId))
  const raw = containerReturnLineInputs.value[k]
  if (Number.isFinite(raw) && raw > 0) return Math.min(raw, max)
  containerReturnLineInputs.value = { ...containerReturnLineInputs.value, [k]: max }
  return max
}

function setContainerReturnLineInput(
  containerId: string,
  ci: ActivityPackContainerItem,
  value: number | string,
): void {
  const k = containerIssueLineKey(containerId, ci.id)
  const max = Math.max(1, containerLineRemainingReturn(ci, containerId))
  let qty = Math.floor(Number(value))
  if (!Number.isFinite(qty) || qty < 1) qty = max
  containerReturnLineInputs.value = { ...containerReturnLineInputs.value, [k]: Math.min(qty, max) }
}

async function executeReturnContainerLineToWarehouse(
  containerId: string,
  ci: ActivityPackContainerItem,
  qty: number,
): Promise<void> {
  const pi = packItems.value.find((p) => p.materialItemId === ci.material_item_id)
  if (!pi) {
    toast.error(t('activities.packList.toastNoPackLine'))
    return
  }
  const max = containerLineRemainingReturn(ci, containerId)
  let moveQty = Math.min(Math.max(1, Math.floor(qty)), max)
  if (moveQty < 1) return
  containerMutationLoading.value = true
  try {
    await postMovePackItem(props.activityId, pi.id, { stage: 'returned', quantity: moveQty })
    const cap = Math.max(ci.quantity_issued ?? 0, ci.quantity_packed ?? 0)
    await updateActivityPackContainerItem(props.activityId, containerId, ci.id, {
      quantity_returned: Math.min((ci.quantity_returned ?? 0) + moveQty, cap > 0 ? cap : moveQty),
    })
    const items = await getPackItems(props.activityId)
    packItems.value = items
    initMoveQtyInputs()
    initContainerReturnLineInputs()
    await loadContainersData()
    emit('activityItemsChanged')
    toast.success(t('activities.packList.toastReturnLineSuccess', { qty: moveQty }))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastReturnFailed'))
  } finally {
    containerMutationLoading.value = false
  }
}

async function returnContainerLineToWarehouse(containerId: string, ci: ActivityPackContainerItem): Promise<void> {
  if (!isPackReturnStage(activePackStage.value)) return
  if (isNonActionableContainerLine(ci)) return
  const max = containerLineRemainingReturn(ci, containerId)
  if (max < 1) return

  const container = packContainers.value.find((x) => x.id === containerId)
  const shell = shellPackItemForContainer(containerId)
  if (shell && needsShellCratePresenceConfirm(shell)) {
    let qty = Math.floor(Number(containerReturnLineInputValue(containerId, ci)))
    if (!Number.isFinite(qty) || qty < 1) qty = max
    qty = Math.min(qty, max)
    setContainerReturnLineInput(containerId, ci, qty)
    await openShellCrateForwardModal(shell, qty, {
      kind: 'return_container_line',
      containerId,
      containerItemId: ci.id,
      qty,
    })
    return
  }

  if (container && !hasSeenReturnWholeCratePrompt(containerId)) {
    const returnWhole = await confirmDialog({
      title: t('activities.packList.confirmReturnWholeCrateTitle', { label: container.label }),
      message: t('activities.packList.confirmReturnWholeCrateMessage', { label: container.label }),
      confirmText: t('activities.packList.confirmReturnWholeCrateYes'),
      cancelText: t('activities.packList.confirmReturnWholeCrateNo'),
    })
    markReturnWholeCratePromptSeen(containerId)
    if (returnWhole) {
      await openReturnCrateModal(container)
      return
    }
  }

  let qty = Math.floor(Number(containerReturnLineInputValue(containerId, ci)))
  if (!Number.isFinite(qty) || qty < 1) qty = max
  qty = Math.min(qty, max)
  setContainerReturnLineInput(containerId, ci, qty)
  const pi = packItems.value.find((p) => p.materialItemId === ci.material_item_id)
  if (shouldOpenConsumptionModalOnReturn(pi)) {
    beginConsumableReturnForContainerLine(containerId, ci, qty)
    return
  }
  await executeReturnContainerLineToWarehouse(containerId, ci, qty)
}

function containerLineRemainingStore(ci: ActivityPackContainerItem): number {
  if (isPackUnpackStage(activePackStage.value)) {
    const acct = retourAccountingForContainerLine(ci)
    return Math.max(0, acct.retourTotal - (ci.quantity_stored ?? 0))
  }
  return Math.max(0, (ci.quantity_returned ?? 0) - (ci.quantity_stored ?? 0))
}

function containerShellPendingStoreQty(containerId: string): number {
  const sh = shellPackItemForContainer(containerId)
  if (!sh) return 0
  if (isPackUnpackStage(activePackStage.value)) {
    const acct = retourAccountingForUnpackLoose(sh)
    return Math.max(0, acct.retourTotal - (sh.quantityStored ?? 0))
  }
  return Math.max(0, (sh.quantityReturned ?? 0) - (sh.quantityStored ?? 0))
}

function containerShellStoreKey(containerId: string): string {
  return `shell-store:${containerId}`
}

function initContainerStoreLineInputs(): void {
  const next: Record<string, number> = { ...containerStoreLineInputs.value }
  for (const c of packContainers.value) {
    for (const sec of packContainerItemSectionsForContainer(c)) {
      for (const ci of sec.lines) {
        const target = resolveActionableContainerLine(c.id, ci)
        if (isNonActionableContainerLine(target)) continue
        const max = containerLineRemainingStore(target)
        if (max < 1) continue
        const k = containerIssueLineKey(c.id, target.id)
        if (next[k] == null || !Number.isFinite(next[k]) || next[k] < 1) {
          next[k] = max
        }
      }
    }
  }
  containerStoreLineInputs.value = next
}

function initContainerShellStoreInputs(): void {
  const next: Record<string, number> = { ...containerShellStoreInputs.value }
  for (const c of packContainers.value) {
    const max = containerShellPendingStoreQty(c.id)
    if (max < 1) continue
    const k = containerShellStoreKey(c.id)
    if (next[k] == null || !Number.isFinite(next[k]) || next[k] < 1) {
      next[k] = max
    }
  }
  containerShellStoreInputs.value = next
}

function containerStoreLineInputValue(containerId: string, ci: ActivityPackContainerItem): number {
  const target = resolveActionableContainerLine(containerId, ci)
  const k = containerIssueLineKey(containerId, target.id)
  const max = Math.max(1, containerLineRemainingStore(target))
  const raw = containerStoreLineInputs.value[k]
  if (Number.isFinite(raw) && raw > 0) return Math.min(raw, max)
  containerStoreLineInputs.value = { ...containerStoreLineInputs.value, [k]: max }
  return max
}

function setContainerStoreLineInput(
  containerId: string,
  ci: ActivityPackContainerItem,
  value: number | string,
): void {
  const target = resolveActionableContainerLine(containerId, ci)
  const k = containerIssueLineKey(containerId, target.id)
  const max = Math.max(1, containerLineRemainingStore(target))
  let qty = Math.floor(Number(value))
  if (!Number.isFinite(qty) || qty < 1) qty = max
  containerStoreLineInputs.value = { ...containerStoreLineInputs.value, [k]: Math.min(qty, max) }
}

function containerShellStoreInputValue(containerId: string): number {
  const max = Math.max(1, containerShellPendingStoreQty(containerId))
  const k = containerShellStoreKey(containerId)
  const raw = containerShellStoreInputs.value[k]
  if (Number.isFinite(raw) && raw > 0) return Math.min(raw, max)
  containerShellStoreInputs.value = { ...containerShellStoreInputs.value, [k]: max }
  return max
}

function setContainerShellStoreInput(containerId: string, value: number | string): void {
  const max = Math.max(1, containerShellPendingStoreQty(containerId))
  const k = containerShellStoreKey(containerId)
  let qty = Math.floor(Number(value))
  if (!Number.isFinite(qty) || qty < 1) qty = max
  containerShellStoreInputs.value = { ...containerShellStoreInputs.value, [k]: Math.min(qty, max) }
}

function resolveActionableContainerLine(
  containerId: string,
  ci: ActivityPackContainerItem,
): ActivityPackContainerItem {
  if (!isNonActionableContainerLine(ci)) return ci
  const mid = (ci.material_item_id ?? '').trim()
  if (!mid) return ci
  const real = (containerItemsByContainerId.value[containerId] ?? []).find(
    (row) => row.material_item_id === mid && !isNonActionableContainerLine(row),
  )
  return real ?? ci
}

async function executeStoreContainerLineToWarehouse(
  containerId: string,
  ci: ActivityPackContainerItem,
  qty: number,
): Promise<void> {
  const target = resolveActionableContainerLine(containerId, ci)
  if (isNonActionableContainerLine(target)) {
    toast.error(t('activities.packList.toastNoPackLine'))
    return
  }
  const pi = packItems.value.find((p) => p.materialItemId === target.material_item_id)
  if (!pi) {
    toast.error(t('activities.packList.toastNoPackLine'))
    return
  }
  const max = containerLineRemainingStore(target)
  const moveQty = Math.min(Math.max(1, Math.floor(qty)), max)
  if (moveQty < 1) return
  const storedCap = Math.max(
    target.quantity_returned ?? 0,
    retourAccountingForContainerLine(target).retourTotal,
  )
  containerMutationLoading.value = true
  try {
    await postMovePackItem(props.activityId, pi.id, { stage: 'stored', quantity: moveQty })
    await updateActivityPackContainerItem(props.activityId, containerId, target.id, {
      quantity_stored: Math.min((target.quantity_stored ?? 0) + moveQty, storedCap),
    })
    const items = await getPackItems(props.activityId)
    packItems.value = items
    initMoveQtyInputs()
    initContainerStoreLineInputs()
    await loadContainersData()
    emit('activityItemsChanged')
    toast.success(t('activities.packList.toastStoreLineSuccess', { qty: moveQty }))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastMoveFailed'))
  } finally {
    containerMutationLoading.value = false
  }
}

async function storeContainerLineToWarehouse(containerId: string, ci: ActivityPackContainerItem): Promise<void> {
  if (!isPackUnpackStage(activePackStage.value)) return
  const target = resolveActionableContainerLine(containerId, ci)
  if (isNonActionableContainerLine(target)) return
  const max = containerLineRemainingStore(target)
  if (max < 1) return
  let qty = Math.floor(Number(containerStoreLineInputValue(containerId, ci)))
  if (!Number.isFinite(qty) || qty < 1) qty = max
  qty = Math.min(qty, max)
  setContainerStoreLineInput(containerId, ci, qty)
  await executeStoreContainerLineToWarehouse(containerId, target, qty)
}

async function unstoreContainerLineFromWarehouse(
  containerId: string,
  ci: ActivityPackContainerItem,
  qty?: number,
): Promise<void> {
  if (!isPackUnpackStage(activePackStage.value)) return
  if (!(await confirmPackStageBackwardAllowed())) return
  const target = resolveActionableContainerLine(containerId, ci)
  if (isNonActionableContainerLine(target)) return
  const stored = target.quantity_stored ?? 0
  if (stored < 1) return
  const pi = packItemForMaterialItemId(target.material_item_id)
  if (!pi) {
    toast.error(t('activities.packList.toastNoPackLine'))
    return
  }
  let moveQty = qty != null ? Math.floor(qty) : stored
  if (!Number.isFinite(moveQty) || moveQty < 1) moveQty = stored
  moveQty = Math.min(moveQty, stored)
  const label = target.material_name ?? pi.materialName ?? ''
  if (!(await confirmUnpackUnstoreFromWarehouse(moveQty, label))) return
  containerMutationLoading.value = true
  try {
    const updated = await postMoveBackPackItem(props.activityId, pi.id, {
      stage: 'stored',
      quantity: moveQty,
    })
    await updateActivityPackContainerItem(props.activityId, containerId, target.id, {
      quantity_stored: Math.max(0, stored - moveQty),
    })
    applyUpdatedItem(updated)
    await loadContainersData()
    initMoveQtyInputs()
    initContainerStoreLineInputs()
    emit('activityItemsChanged')
    toast.success(t('activities.packList.toastUnstoreLineSuccess', { qty: moveQty }))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastMoveBackFailed'))
  } finally {
    containerMutationLoading.value = false
  }
}

async function unstoreContainerShellFromWarehouse(containerId: string, qty?: number): Promise<void> {
  if (!isPackUnpackStage(activePackStage.value)) return
  if (!(await confirmPackStageBackwardAllowed())) return
  const shell = shellPackItemForContainer(containerId)
  if (!shell) return
  const stored = shell.quantityStored ?? 0
  if (stored < 1) return
  let moveQty = qty != null ? Math.floor(qty) : stored
  if (!Number.isFinite(moveQty) || moveQty < 1) moveQty = stored
  moveQty = Math.min(moveQty, stored)
  if (!(await confirmUnpackUnstoreFromWarehouse(moveQty, shell.materialName ?? ''))) return
  containerMutationLoading.value = true
  try {
    const updated = await postMoveBackPackItem(props.activityId, shell.id, {
      stage: 'stored',
      quantity: moveQty,
    })
    applyUpdatedItem(updated)
    await loadContainersData()
    initMoveQtyInputs()
    initContainerShellStoreInputs()
    emit('activityItemsChanged')
    toast.success(t('activities.packList.toastUnstoreLineSuccess', { qty: moveQty }))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastMoveBackFailed'))
  } finally {
    containerMutationLoading.value = false
  }
}

async function storeContainerShellToWarehouse(containerId: string, qtyOverride?: number): Promise<void> {
  if (!isPackUnpackStage(activePackStage.value)) return
  const shell = shellPackItemForContainer(containerId)
  if (!shell) return
  const max = containerShellPendingStoreQty(containerId)
  if (max < 1) return
  let qty =
    qtyOverride != null ? Math.floor(qtyOverride) : Math.floor(containerShellStoreInputValue(containerId))
  qty = Math.min(Math.max(1, qty), max)
  setContainerShellStoreInput(containerId, qty)
  containerMutationLoading.value = true
  try {
    await postMovePackItem(props.activityId, shell.id, { stage: 'stored', quantity: qty })
    const items = await getPackItems(props.activityId)
    packItems.value = items
    initMoveQtyInputs()
    initContainerShellStoreInputs()
    await loadContainersData()
    emit('activityItemsChanged')
    toast.success(t('activities.packList.toastStoreLineSuccess', { qty }))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastMoveFailed'))
  } finally {
    containerMutationLoading.value = false
  }
}

async function storePhysicalComboContainerWhole(containerId: string): Promise<void> {
  if (!isPackUnpackStage(activePackStage.value)) return
  const shell = shellPackItemForContainer(containerId)
  if (!shell || shell.materialType !== 'physical_combo') return
  containerBulkLoadingId.value = containerId
  try {
    const container = packContainers.value.find((c) => c.id === containerId)
    if (!container) return
    for (const sec of packContainerItemSectionsForContainer(container)) {
      for (const ci of sec.lines) {
        if (isNonActionableContainerLine(ci)) continue
        const max = containerLineRemainingStore(ci)
        if (max < 1) continue
        await executeStoreContainerLineToWarehouse(containerId, ci, max)
      }
    }
    const shellMax = containerShellPendingStoreQty(containerId)
    if (shellMax > 0) {
      await storeContainerShellToWarehouse(containerId, shellMax)
    }
    toast.success(t('activities.packList.toastStoreContainer'))
  } finally {
    containerBulkLoadingId.value = null
  }
}

function isPhysicalComboContainer(containerId: string): boolean {
  const sh = shellPackItemForContainer(containerId)
  return sh?.materialType === 'physical_combo'
}

const shellBackLabel = computed(() => {
  const pi = shellBackItem.value
  if (!pi) return ''
  const c = packShellContainerForPackItem(pi, packContainers.value)
  return (c?.label ?? pi.materialName).trim() || pi.materialName
})

const shellBackFromLabel = computed(() => activeStageConfig.value.leftLabel)
const shellBackToLabel = computed(() => {
  if (isPackForwardToEventStage(activePackStage.value) && packWorkflowProfile.value === 'logistics') {
    return t('activities.packList.stages.confirmed_packed.left')
  }
  return activeStageConfig.value.rightLabel
})

const shellBackPeekSections = computed((): PackCrateShellPeekSection[] => {
  const pi = shellBackItem.value
  if (!pi) return []
  return crateShellPeekSectionsForPackItem(
    pi,
    packContainers.value,
    containerItemsByContainerId.value,
    containerWarehouseTemplateByContainerId.value,
    containerWarehouseContentsByContainerId.value,
    comboComponentsByMaterialId.value,
    peekSectionTitles(),
    t('activities.common.material'),
    activityCrateCheckSnapshots.value,
    true,
  )
})

const shellBackDeviations = computed(() => {
  const pi = shellBackItem.value
  if (!pi) return []
  return buildShellCrateBackDeviations(crateCheckSnapForPackItem(pi, 'outbound'), t)
})

const shellBackLastCheckDateLabel = computed(() => {
  const pi = shellBackItem.value
  if (!pi) return null
  const snap = crateCheckSnapForPackItem(pi, 'outbound')
  if (!snap?.created_at) return null
  return new Date(snap.created_at).toLocaleString(locale.value)
})

function isPackContainerCollapsed(containerId: string): boolean {
  const explicit = collapsedPackContainers.value[containerId]
  if (explicit !== undefined) return explicit
  return true
}

function hasAnyCrateCheckSnapForPackItem(packItemId: string): boolean {
  if (!packCrateCheckUserId.value) return false
  const prefix = `${packItemId}:`
  return Object.keys(activityCrateCheckSnapshots.value).some((k) => k.startsWith(prefix))
}

function useCrateRealityForPackItem(packItemId: string): boolean {
  if (canManageMaterials.value && hasAnyCrateCheckSnapForPackItem(packItemId)) {
    return true
  }
  if (!canManageMaterials.value) return true
  return useCrateRealityByPackItemId.value[packItemId] !== false
}

function peekSectionsForShellContainerCtx(c: ActivityPackContainer): PackCrateShellPeekSection[] {
  const shellPi = shellPackItemForContainer(c.id)
  const combo =
    shellPi != null ? comboComponentsByMaterialId.value[shellPi.materialItemId] ?? [] : []
  return peekSectionsForShellContainer(
    c,
    containerItemsByContainerId.value,
    containerWarehouseTemplateByContainerId.value,
    containerWarehouseContentsByContainerId.value,
    combo,
    peekSectionTitles(),
    t('activities.common.material'),
    shellPi?.id,
    activityCrateCheckSnapshots.value,
    shellPi ? useCrateRealityForPackItem(shellPi.id) : false,
    shellPi,
  )
}

function isPackContainerSubsectionCollapsed(containerId: string, subsectionKey: string): boolean {
  const k = `${containerId}:${subsectionKey}`
  if (k in collapsedPackContainerSubsections.value) {
    return collapsedPackContainerSubsections.value[k] === true
  }
  return true
}

function togglePackContainerSubsection(containerId: string, subsectionKey: string) {
  const k = `${containerId}:${subsectionKey}`
  collapsedPackContainerSubsections.value = {
    ...collapsedPackContainerSubsections.value,
    [k]: !isPackContainerSubsectionCollapsed(containerId, subsectionKey),
  }
}

function packContainerItemSectionsForContainer(c: ActivityPackContainer) {
  const shellPi = shellPackItemForContainer(c.id)
  const combo =
    shellPi != null ? comboComponentsByMaterialId.value[shellPi.materialItemId] ?? [] : []
  return packContainerItemSectionsWithReality(
    c,
    containerItemsByContainerId.value,
    containerWarehouseTemplateByContainerId.value[c.id],
    containerWarehouseContentsByContainerId.value[c.id],
    combo,
    peekSectionTitles(),
    t('activities.common.material'),
    shellPi?.id,
    activityCrateCheckSnapshots.value,
    shellPi ? useCrateRealityForPackItem(shellPi.id) : false,
    shellPi,
  )
}

function peekSectionsForShellPackItem(pi: ActivityPackItem): PackCrateShellPeekSection[] {
  return crateShellPeekSectionsForPackItem(
    pi,
    packContainers.value,
    containerItemsByContainerId.value,
    containerWarehouseTemplateByContainerId.value,
    containerWarehouseContentsByContainerId.value,
    comboComponentsByMaterialId.value,
    peekSectionTitles(),
    t('activities.common.material'),
    activityCrateCheckSnapshots.value,
    useCrateRealityByPackItemId.value[pi.id] !== false,
  )
}

function crateShellPeekEmptyHint(pi: ActivityPackItem): string {
  if (packShellContainerForPackItem(pi, packContainers.value)) {
    return t('activities.packList.cratePeekEmptyLinkedCrate')
  }
  const combo = comboComponentsByMaterialId.value[pi.materialItemId] ?? []
  if (combo.length === 0) {
    return t('activities.packList.cratePeekComboBomEmpty')
  }
  return t('activities.packList.cratePeekNoShellYet')
}

/** Kein Inline-Banner — Kistencheck steht im Verlauf; Ist-Mengen gelten still im Hintergrund. */
function showCrateTemplateToggle(_pi: ActivityPackItem): boolean {
  return false
}

function crateRealityBannerForPackItem(_pi: ActivityPackItem): string | null {
  return null
}

function toggleCrateRealityView(_pi: ActivityPackItem) {
  /* Toggle entfernt — keine Packliste/Ist-Umschaltung in der Kiste. */
}

function needsShellCrateBackConfirm(pi: ActivityPackItem): boolean {
  if (!isPackForwardToEventStage(activePackStage.value)) return false
  return isCrateShellPackItem(pi, packContainers.value)
}

function closeShellBackModal() {
  shellBackModalOpen.value = false
  shellBackItem.value = null
  shellBackAcknowledged.value = false
}

async function refreshCrateCheckSnapshots() {
  try {
    const history = await getActivityHistory(props.activityId)
    activityCrateCheckSnapshots.value = indexLatestCrateCheckByPackItemAndLeg(history, {
      userId: packCrateCheckUserId.value,
    })
  } catch {
    activityCrateCheckSnapshots.value = {}
  }
}

async function openShellCrateBackModal(item: ActivityPackItem, moveQty: number) {
  await refreshCrateCheckSnapshots()
  shellBackItem.value = item
  shellBackMoveQty.value = moveQty
  shellBackAcknowledged.value = false
  shellBackModalOpen.value = true
}

async function onShellBackConfirm() {
  const item = shellBackItem.value
  const qty = shellBackMoveQty.value
  if (!item || qty < 1) return
  shellBackSubmitting.value = true
  try {
    closeShellBackModal()
    await executeMoveToPrevStage(item, qty)
  } finally {
    shellBackSubmitting.value = false
  }
}

function isPackContainerMerged(c: ActivityPackContainer): boolean {
  if (isPackForwardToEventStage(activePackStage.value) && containerHasIssuedAtEvent(c.id)) {
    return false
  }
  return isPackContainerMergedIntoStageLeftRow(
    c,
    packContainers.value,
    stageLeftItems.value,
    activePackStage.value,
    (p) => getStageLeftQty(p),
  )
}

/** Noch in der aktuellen Hinweg-Stufe buchbar (Transport hin vs. Am Event). */
function packItemRemainingAtForwardStage(pi: ActivityPackItem): number {
  const stage = activePackStage.value
  if (stage === 'packed_transport_to') {
    return Math.max(0, (pi.quantityPacked ?? 0) - (pi.quantityTransportTo ?? 0))
  }
  if (stage === 'transport_to_at_event' || stage === 'packed_at_event') {
    return Math.max(0, (pi.quantityTransportTo ?? 0) - (pi.quantityIssued ?? 0))
  }
  return Math.max(0, (pi.quantityPacked ?? 0) - (pi.quantityIssued ?? 0))
}

function containerLineRemainingAtForwardStage(ci: ActivityPackContainerItem): number {
  if (isNonActionableContainerLine(ci)) return 0
  const packed = ci.quantity_packed ?? 0
  const transported = ci.quantity_transport_to ?? 0
  const issued = ci.quantity_issued ?? 0
  const stage = activePackStage.value
  if (stage === 'packed_transport_to') {
    return Math.max(0, packed - transported)
  }
  if (stage === 'transport_to_at_event' || stage === 'packed_at_event') {
    return Math.max(0, transported - issued)
  }
  return Math.max(0, packed - issued)
}

/** Behälter & lose/in-Behälter-Aufteilung auch bei «Gepackt → Am Event» (linkes «Gepackt» wie zuvor rechts) */
const showPackContainersUi = computed(() =>
  showPackContainersForProfile(packWorkflowProfile.value, activePackStage.value),
)

function packedQtyBaseForContainerSplit(pi: ActivityPackItem): number {
  const stage = activePackStage.value
  if (stage === 'confirmed_packed') return getStageRightQty(pi)
  if (stage === 'packed_transport_to') return Math.max(0, pi.quantityPacked ?? 0)
  if (stage === 'transport_to_at_event') return Math.max(0, pi.quantityTransportTo ?? 0)
  if (isPackForwardToEventStage(stage)) return Math.max(0, pi.quantityPacked ?? 0)
  return 0
}

function containerQtySumForMaterial(
  materialItemId: string,
  field: 'packed' | 'transport_to' | 'issued' | 'transport_back' | 'returned',
): number {
  let sum = 0
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id !== materialItemId) continue
      switch (field) {
        case 'packed':
          sum += ci.quantity_packed ?? 0
          break
        case 'transport_to':
          sum += ci.quantity_transport_to ?? 0
          break
        case 'issued':
          sum += ci.quantity_issued ?? 0
          break
        case 'transport_back':
          sum += ci.quantity_transport_back ?? 0
          break
        case 'returned':
          sum += ci.quantity_returned ?? 0
          break
        default:
          break
      }
    }
    const sh = shellPackItemForContainer(c.id)
    if (sh?.materialItemId === materialItemId) {
      switch (field) {
        case 'packed':
          sum += sh.quantityPacked ?? 0
          break
        case 'transport_to':
          sum += sh.quantityTransportTo ?? 0
          break
        case 'issued':
          sum += sh.quantityIssued ?? 0
          break
        case 'transport_back':
          sum += sh.quantityTransportBack ?? 0
          break
        case 'returned':
          sum += sh.quantityReturned ?? 0
          break
        default:
          break
      }
    }
  }
  return sum
}

function transportToQtyInContainersForMaterial(materialItemId: string): number {
  return containerQtySumForMaterial(materialItemId, 'transport_to')
}

/** Rest in Kisten/Shell für Hinweg-Stufe (nicht lose) — z. B. noch zu transportieren. */
function forwardRemainingInContainersForMaterial(materialItemId: string, stage: PackStage): number {
  if (!isPackForwardToEventStage(stage)) return 0
  let sum = 0
  for (const c of packContainers.value) {
    const shell = shellPackItemForContainer(c.id)
    if (shell?.materialItemId === materialItemId) {
      sum += Math.max(0, getStageLeftQtyForStage(shell, stage))
      continue
    }
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id !== materialItemId) continue
      if (isNonActionableContainerLine(ci)) continue
      const packed = ci.quantity_packed ?? 0
      const transported = ci.quantity_transport_to ?? 0
      const issued = ci.quantity_issued ?? 0
      if (stage === 'packed_transport_to') {
        sum += Math.max(0, packed - transported)
      } else if (stage === 'transport_to_at_event' || stage === 'packed_at_event') {
        sum += Math.max(0, transported - issued)
      }
    }
  }
  return sum
}

function transportBackQtyInContainersForMaterial(materialItemId: string): number {
  return containerQtySumForMaterial(materialItemId, 'transport_back')
}

const assignedQtyByMaterialId = computed(() => {
  const m: Record<string, number> = {}
  for (const c of packContainers.value) {
    const rows = containerItemsByContainerId.value[c.id] ?? []
    for (const it of rows) {
      const mid = it.material_item_id
      m[mid] = (m[mid] ?? 0) + (it.quantity_packed ?? 0)
    }
  }
  return m
})

function shellVirtualContainerMap(): Record<string, string> {
  return shellComboVirtualContainerByPackItemId.value
}

function shellPackContainerForItem(pi: ActivityPackItem): ActivityPackContainer | undefined {
  return packShellContainerForPackItem(pi, packContainers.value, shellVirtualContainerMap())
}

function looseQtyForPackItem(pi: ActivityPackItem, stageOverride?: PackStage): number {
  const stage = stageOverride ?? activePackStage.value
  if (isPackReturnStage(stage)) return getStageRightQtyForStage(pi, stage)
  if (stage !== 'confirmed_packed' && !isPackForwardToEventStage(stage)) {
    return getStageRightQtyForStage(pi, stage)
  }
  if (
    isPackForwardToEventStage(stage) &&
    crateShellExcludedFromLooseForwardList(
      pi,
      packContainers.value,
      true,
      shellVirtualContainerMap(),
      stage,
    )
  ) {
    return 0
  }
  if (stage === 'confirmed_packed' && isPhysicalComboAsSet(pi, packContainers.value, shellVirtualContainerMap())) {
    const left = getStageLeftQtyForStage(pi, stage)
    if (left > 0) return left
    const inContainers = assignedQtyByMaterialId.value[pi.materialItemId] ?? 0
    return Math.max(0, getStageRightQtyForStage(pi, stage) - inContainers)
  }
  const total =
    stage === 'confirmed_packed'
      ? getStageRightQtyForStage(pi, stage)
      : stage === 'packed_transport_to'
        ? Math.max(0, pi.quantityPacked ?? 0)
        : stage === 'transport_to_at_event'
          ? Math.max(0, pi.quantityTransportTo ?? 0)
          : Math.max(0, pi.quantityPacked ?? 0)
  const assigned = assignedQtyByMaterialId.value[pi.materialItemId] ?? 0
  const gap = crateCheckGapForMaterial(pi.materialItemId)
  const gapAdjust = gap > 0 && assigned > 0 ? gap : 0
  const physicalLoose = Math.max(0, total - assigned - gapAdjust)
  if (stage === 'packed_transport_to') {
    const leftTotal = getStageLeftQtyForStage(pi, stage)
    const inContainers = forwardRemainingInContainersForMaterial(pi.materialItemId, stage)
    return Math.max(0, leftTotal - inContainers)
  }
  if (isPackForwardToEventStage(stage)) {
    if (stage === 'transport_to_at_event') {
      const issuedLoose = Math.max(
        0,
        (pi.quantityIssued ?? 0) - issuedQtyInContainersForMaterial(pi.materialItemId),
      )
      return Math.max(0, physicalLoose - issuedLoose)
    }
    return physicalLoose
  }
  return physicalLoose
}

function qtyInContainersForItem(pi: ActivityPackItem): number {
  if (activePackStage.value !== 'confirmed_packed' && !isPackForwardToEventStage(activePackStage.value)) {
    return 0
  }
  const total = packedQtyBaseForContainerSplit(pi)
  const assigned = assignedQtyByMaterialId.value[pi.materialItemId] ?? 0
  if (activePackStage.value === 'confirmed_packed') {
    return Math.min(total, assigned)
  }
  return Math.max(0, getStageLeftQty(pi) - looseQtyForPackItem(pi))
}

function containerItemCount(containerId: string): number {
  const c = packContainers.value.find((x) => x.id === containerId)
  if (!c) return (containerItemsByContainerId.value[containerId] ?? []).length
  const n = packContainerItemSectionsForContainer(c).reduce((sum, s) => sum + s.lines.length, 0)
  if (n > 0) return n
  const sh = shellPackItemForContainer(containerId)
  if (sh && (getStageLeftQty(sh) > 0 || getStageRightQty(sh) > 0)) return 1
  return 0
}

/** Vorschau- / Check-Zeilen — nicht aus Behälter ziehen */
function isVirtualWarehouseContainerLine(ci: ActivityPackContainerItem): boolean {
  if (isNonActionableContainerLine(ci)) return true
  for (const c of packContainers.value) {
    const mid = (c.container_material_item_id ?? '').trim()
    if (mid && ci.material_item_id === mid) return true
  }
  return false
}

/** Pack-Position der Lager-Kiste (Charge) — wie Backend applyShellPackItemForBulkWorkflow */
function shellPackItemForContainer(containerId: string): ActivityPackItem | undefined {
  const c = packContainers.value.find((x) => x.id === containerId)
  if (!c) return undefined
  const mid = (c.container_material_item_id ?? '').trim()
  if (mid) {
    const byMid = packItems.value.find((p) => p.materialItemId === mid)
    if (byMid) return byMid
  }
  const bid = (c.container_batch_id ?? '').trim()
  if (bid) {
    const byBatch = packItems.value.find((p) => (p.linkedContainerBatchId ?? '').trim() === bid)
    if (byBatch) return byBatch
  }
  return undefined
}

const hasActiveCrateTarget = computed(() => {
  const tgt = activePackTarget.value
  return tgt?.kind === 'container' || tgt?.kind === 'combo'
})

/** Max. Stück in die gewählte Kiste einbuchen (links: lose «Gepackt») */
function crateAssignUpMax(pi: ActivityPackItem): number {
  if (!hasActiveCrateTarget.value) {
    const fwd = packIssueForwardMax(pi)
    if (fwd >= 1) return fwd
    if (activePackStage.value === 'confirmed_packed') {
      return looseQtyForPackItem(pi)
    }
    return 0
  }
  return Math.max(0, looseQtyForPackItem(pi))
}

/** Max. Stück von lose «Am Event» in die gewählte Kiste */
function crateAssignLooseAtEventMax(pi: ActivityPackItem): number {
  return Math.max(0, looseIssuedAtEvent(pi))
}

function showCrateAssignUpControls(pi: ActivityPackItem): boolean {
  if (!showPackForwardControls.value) return false
  if (!hasActiveCrateTarget.value) return false
  if (isPhysicalComboPackItem(pi)) return false
  if (isPackForwardToEventStage(activePackStage.value)) {
    if (!showPackContainersUi.value) return false
    if (getStageLeftQty(pi) < 1) return false
    return crateAssignUpMax(pi) >= 1
  }
  if (activePackStage.value === 'confirmed_packed') {
    return crateAssignUpMax(pi) >= 1
  }
  return false
}

/** Rechts unter «Lose»: lose «Am Event» in gewählte Kiste (grüner Pfeil nach oben) */
function showCrateAssignUpControlsLooseAtEvent(pi: ActivityPackItem): boolean {
  if (!showPackForwardControls.value) return false
  if (!isPackForwardToEventStage(activePackStage.value)) return false
  if (!showPackContainersUi.value) return false
  if (!hasActiveCrateTarget.value) return false
  if (isPhysicalComboPackItem(pi)) return false
  return crateAssignLooseAtEventMax(pi) >= 1
}

function showPackUnpackStoredMoveBack(pi: ActivityPackItem): boolean {
  return (
    isPackUnpackStage(activePackStage.value) &&
    showPackOperateControls.value &&
    storedLooseQtyForPackItem(pi) > 0
  )
}

function showPackMoveBackControlsForItem(pi: ActivityPackItem): boolean {
  if (isPackUnpackStage(activePackStage.value)) {
    return showPackUnpackStoredMoveBack(pi)
  }
  if (props.status === 'returned') return false
  if (showCrateAssignUpControls(pi)) {
    return showPackForwardControls.value && crateAssignUpMax(pi) >= 1
  }
  return showPackBackwardControls.value && rightQtyForMoveBack(pi) > 0
}

/** Kiste hat eingebuchten Inhalt (nicht nur leere Hülle am Event) */
function containerHasAssignedContents(containerId: string): boolean {
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    if (isNonActionableContainerLine(ci)) continue
    if ((ci.quantity_packed ?? 0) > 0 || (ci.quantity_issued ?? 0) > 0) return true
  }
  return false
}

function assignUpTitleForItem(
  pi: ActivityPackItem,
  source: CrateAssignSource = 'packed-left',
): string {
  const max = source === 'loose-at-event' ? crateAssignLooseAtEventMax(pi) : crateAssignUpMax(pi)
  const qty = resolveCrateAssignQty(pi, max)
  const label = activePackTargetCrateLabel.value || t('activities.packList.crateTargetFallback')
  return t('activities.packList.titleAssignQtyToCrate', { qty: qty > 0 ? qty : max, label })
}

function resolveCrateAssignQty(pi: ActivityPackItem, max: number): number {
  if (max < 1) return 0
  const raw = moveQtyInputs.value[pi.id]
  const parsed = Number(raw)
  if (Number.isFinite(parsed) && parsed > 0) {
    return Math.min(max, Math.floor(parsed))
  }
  return max
}

type CrateAssignSource = 'packed-left' | 'loose-at-event'

async function onCrateAssignUpClick(
  pi: ActivityPackItem,
  qtyFromControl?: number,
  source: CrateAssignSource = 'packed-left',
) {
  if (!(await confirmPackStageForwardAllowed())) return
  const tgt = activePackTarget.value
  if (!tgt || (tgt.kind !== 'container' && tgt.kind !== 'combo')) return

  const maxLoose =
    source === 'loose-at-event' ? crateAssignLooseAtEventMax(pi) : looseQtyForPackItem(pi)
  if (maxLoose < 1) return
  const assignQty =
    qtyFromControl != null && qtyFromControl > 0
      ? Math.min(maxLoose, Math.floor(qtyFromControl))
      : resolveCrateAssignQty(pi, maxLoose)
  if (assignQty < 1) return

  if (tgt.kind === 'container') {
    await assignDirectToActiveContainer(pi, tgt.containerId, assignQty, source)
    return
  }
  const containerId = await ensurePackContainerForShellCombo(tgt.packItemId)
  if (!containerId) return
  activePackTarget.value = { kind: 'container', containerId }
  await assignDirectToActiveContainer(pi, containerId, assignQty, source)
}

/** Nach «Kiste ans Event»: als Einbuch-Ziel wählen (Picker + Pfeil in Kiste). */
function selectContainerTargetAfterIssueToEvent(containerId: string) {
  if (!isPackForwardToEventStage(activePackStage.value)) return
  selectActiveContainer(containerId)
}

/** Summe «Am Event» über Behälterzeilen + Kisten-Shell (pro Material) — Aufteilung lose vs. Behälter rechts */
function issuedQtyInContainersForMaterial(materialItemId: string): number {
  let sum = 0
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id === materialItemId) {
        sum += ci.quantity_issued ?? 0
      }
    }
    if (c.container_material_item_id === materialItemId) {
      const sh = shellPackItemForContainer(c.id)
      if (sh?.materialItemId === materialItemId) {
        sum += sh.quantityIssued ?? 0
      }
    }
  }
  return sum
}

/** Rechte Spalte: lose Menge der aktuellen Hinweg-Stufe (Transport / Am Event). */
function looseQtyOnRightMirror(pi: ActivityPackItem): number {
  const stage = activePackStage.value
  if (stage === 'packed_transport_to') {
    return Math.max(0, (pi.quantityTransportTo ?? 0) - transportToQtyInContainersForMaterial(pi.materialItemId))
  }
  if (stage === 'transport_to_at_event' || stage === 'packed_at_event') {
    const inContainers = issuedQtyInContainersForMaterial(pi.materialItemId)
    let loose = Math.max(0, (pi.quantityIssued ?? 0) - inContainers)
    if (loose < 1) return 0
    const gap = crateCheckGapForMaterial(pi.materialItemId)
    if (gap > 0 && inContainers > 0) {
      loose = Math.max(0, loose - gap)
    }
    return loose
  }
  return getStageRightQty(pi)
}

/** Lose Menge Retour-Transport (rechts «Transport zurück»). */
function looseTransportBackOnRight(pi: ActivityPackItem): number {
  const inContainers = transportBackQtyInContainersForMaterial(pi.materialItemId)
  return Math.max(0, (pi.quantityTransportBack ?? 0) - inContainers)
}

/** «Gepackt → Am Event»: nur lose ausgebene Menge (ohne Behälterbuchungen) */
function looseIssuedAtEvent(pi: ActivityPackItem): number {
  if (!isPackForwardToEventStage(activePackStage.value)) return getStageRightQty(pi)
  return looseQtyOnRightMirror(pi)
}

function containerHasProgressOnRightForStage(containerId: string): boolean {
  const stage = activePackStage.value
  const sh = shellPackItemForContainer(containerId)
  if (stage === 'packed_transport_to') {
    if (sh && (sh.quantityTransportTo ?? 0) > 0) return true
    for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
      if ((ci.quantity_transport_to ?? 0) > 0) return true
    }
    return false
  }
  if (stage === 'transport_to_at_event' || stage === 'packed_at_event') {
    return containerHasIssuedAtEvent(containerId)
  }
  if (stage === 'at_event_transport_back') {
    if (sh && (sh.quantityTransportBack ?? 0) > 0) return true
    for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
      if ((ci.quantity_transport_back ?? 0) > 0) return true
    }
    return false
  }
  if (stage === 'transport_back_returned' && packWorkflowProfile.value === 'logistics') {
    return (
      containerTransportBackReturnableUnits(containerId) <= 0 &&
      containerReturnedAsWhole(containerId)
    )
  }
  if (isPackReturnStage(stage)) {
    return containerReturnedAsWhole(containerId)
  }
  return containerHasIssuedAtEvent(containerId)
}

function rightQtyForMoveBack(pi: ActivityPackItem): number {
  if (isPackUnpackStage(activePackStage.value)) {
    return storedLooseQtyForPackItem(pi)
  }
  if (isPackForwardToEventStage(activePackStage.value)) {
    if (isCrateShellPackItem(pi, packContainers.value)) {
      return getStageRightQty(pi)
    }
    return looseQtyOnRightMirror(pi)
  }
  if (activePackStage.value === 'at_event_transport_back') {
    return looseTransportBackOnRight(pi)
  }
  if (isPackReturnStage(activePackStage.value)) {
    const looseRet = returnedLooseQtyForPackItem(pi)
    if (looseRet > 0) return looseRet
  }
  return getStageRightQty(pi)
}

function crateCheckOverlayForContainerLine(ci: ActivityPackContainerItem): CrateCheckLineOverlay | undefined {
  const containerId = ci.pack_container_id
  if (!containerId) return undefined
  const shell = shellPackItemForContainer(containerId)
  if (!shell) return undefined
  if (!useCrateRealityForPackItem(shell.id)) return undefined
  const snap = crateCheckSnapForPackItem(shell, 'outbound')
  return overlayForContainerMaterial(snap, ci.material_item_id ?? '')
}

function containerLineIssueDisplay(ci: ActivityPackContainerItem): {
  rem: number
  packed: number
  missingFromPlan: number
} {
  const base = containerLineIssueFraction(ci, crateCheckOverlayForContainerLine(ci))
  const containerId = packContainerIdForContainerItem(ci)
  if (containerId && containerContentsTravelWithShellAtEvent(containerId)) {
    return { rem: 0, packed: base.packed, missingFromPlan: base.missingFromPlan }
  }
  if (isPackForwardToEventStage(activePackStage.value)) {
    return { ...base, rem: containerLineRemainingAtForwardStage(ci) }
  }
  return base
}

function containerLineRemainingIssue(ci: ActivityPackContainerItem): number {
  if (isNonActionableContainerLine(ci)) return 0
  return containerLineIssueDisplay(ci).rem
}

/** Noch ausgebbar laut Pack-Position (Material gesamt) */
function containerLinePackRemaining(ci: ActivityPackContainerItem): number {
  if (isNonActionableContainerLine(ci)) return 0
  const containerId = packContainerIdForContainerItem(ci)
  if (containerId && containerContentsTravelWithShellAtEvent(containerId)) {
    return 0
  }
  const overlay = crateCheckOverlayForContainerLine(ci)
  if (overlay) {
    if (activePackStage.value === 'packed_transport_to') {
      return Math.max(0, displayQtyInCrateAfterCheck(overlay) - (ci.quantity_transport_to ?? 0))
    }
    return Math.max(0, displayQtyInCrateAfterCheck(overlay) - (ci.quantity_issued ?? 0))
  }
  const pi = packItems.value.find((x) => x.materialItemId === ci.material_item_id)
  return pi ? packItemRemainingAtForwardStage(pi) : 0
}

/** Anzeige «Am Event»: bei mitgereister Kiste Ist aus Check, nicht nur quantity_issued. */
function containerLineIssuedDisplayQty(ci: ActivityPackContainerItem): number {
  const issued = ci.quantity_issued ?? 0
  const containerId = packContainerIdForContainerItem(ci)
  if (containerId && containerContentsTravelWithShellAtEvent(containerId)) {
    return Math.max(issued, containerLineInCrateQty(ci))
  }
  return issued
}

function containerLineIssuedDisplayPacked(ci: ActivityPackContainerItem): number {
  const packed = containerLineIssueDisplay(ci).packed
  return packed > 0 ? packed : ci.quantity_packed ?? 0
}

/**
 * Wie Backend issue_all: min(Zeilenrest, Pack-Rest).
 * Drift: Behälter hat quantity_issued == quantity_packed, Packliste aber noch Rest → trotzdem buchbar.
 */
function containerLineIssueableMax(ci: ActivityPackContainerItem): number {
  if (isNonActionableContainerLine(ci)) return 0
  const containerId = packContainerIdForContainerItem(ci)
  if (containerId && containerContentsTravelWithShellAtEvent(containerId)) {
    return 0
  }
  const overlay = crateCheckOverlayForContainerLine(ci)
  if (overlay && isPackForwardToEventStage(activePackStage.value)) {
    const inCrate = displayQtyInCrateAfterCheck(overlay)
    if (activePackStage.value === 'packed_transport_to') {
      return Math.max(0, inCrate - (ci.quantity_transport_to ?? 0))
    }
    const issued = ci.quantity_issued ?? 0
    return Math.max(0, inCrate - issued)
  }
  const p = ci.quantity_packed ?? 0
  const lineRem =
    isPackForwardToEventStage(activePackStage.value)
      ? containerLineRemainingAtForwardStage(ci)
      : containerLineRemainingIssue(ci)
  const packRem = containerLinePackRemaining(ci)
  const m = Math.min(lineRem, packRem)
  if (m > 0) {
    return m
  }
  if (p > 0 && packRem > 0 && lineRem === 0) {
    return Math.min(p, packRem)
  }
  return 0
}

/** Nur Zeileninhalt (ohne Kisten-Shell) — für «leere Kiste, nur Behälter mitnehmen» */
function containerLinesIssueableUnits(containerId: string): number {
  let sum = 0
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    sum += containerLineIssueableMax(ci)
  }
  return sum
}

/** Stück noch nicht «Am Event» (Gepackt → Event): Inhalt + Kisten-Material — konsistent mit issue-all */
function containerIssueableUnits(containerId: string): number {
  return containerLinesIssueableUnits(containerId) + containerShellIssueableUnits(containerId)
}

/** Shell der Pack-Kiste: buchbar je nach Pipeline-Stufe (Transport hin / Am Event). */
function containerShellIssueableUnits(containerId: string): number {
  const shell = shellPackItemForContainer(containerId)
  if (!shell) return 0
  return packItemRemainingAtForwardStage(shell)
}

/**
 * Leere Pack-Kiste mitnehmen: Inhalt wurde lose ans Event gebucht, Behälter steht noch links.
 * Erlaubt 1× Shell auch wenn packed−issued = 0 (Shell noch nicht als «gepackt» gezählt).
 */
function containerShellTakeMax(containerId: string): number {
  if (!isPackForwardToEventStage(activePackStage.value)) return 0
  if (containerHasProgressOnRightForStage(containerId)) return 0
  const c = packContainers.value.find((x) => x.id === containerId)
  if (!c?.container_batch_id && !c?.container_material_item_id) return 0
  const shellRem = containerShellIssueableUnits(containerId)
  if (shellRem > 0) return shellRem
  if (containerLinesIssueableUnits(containerId) > 0) return 0
  const shell = shellPackItemForContainer(containerId)
  if (!shell) return 0
  if (activePackStage.value === 'packed_transport_to') {
    if ((shell.quantityTransportTo ?? 0) > 0) return 0
  } else if ((shell.quantityIssued ?? 0) > 0) {
    return 0
  }
  return 1
}

/** Bereits «Am Event» gebucht, zurück nach Gepackt (min Zeile, Packliste) */
function containerLineUnissueableMax(ci: ActivityPackContainerItem): number {
  if (isNonActionableContainerLine(ci)) return 0
  const containerId = packContainerIdForContainerItem(ci)
  const issued = ci.quantity_issued ?? 0
  const pi = packItems.value.find((x) => x.materialItemId === ci.material_item_id)
  const packCan = pi ? Math.max(0, pi.quantityIssued - (pi.quantityReturned ?? 0)) : 0
  if (containerId && containerContentsTravelWithShellAtEvent(containerId)) {
    const implied = Math.max(issued, containerLineInCrateQty(ci))
    return Math.min(implied, packCan)
  }
  return Math.min(issued, packCan)
}

/** Ganzer Behälter: Stück zurücknehmbar (Zeilen + Shell) */
function containerUnissueableUnits(containerId: string): number {
  let sum = 0
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    sum += containerLineUnissueableMax(ci)
  }
  const shell = shellPackItemForContainer(containerId)
  if (shell) {
    sum += Math.max(0, shell.quantityIssued - (shell.quantityReturned ?? 0))
  }
  return sum
}

/** Packinhalt in der Kiste, noch nicht retourniert (ohne Leergehäuse). */
function containerInnerReturnableUnits(containerId: string): number {
  let sum = 0
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    sum += containerLineRemainingReturn(ci, containerId)
  }
  return sum
}

/** Leergehäuse / Behälter-Material noch am Event. */
function containerShellReturnableUnits(containerId: string): number {
  const shell = shellPackItemForContainer(containerId)
  if (!shell) return 0
  return Math.max(0, shell.quantityIssued - shell.quantityReturned)
}

/** Stück am Event, noch nicht retour (Event → Retour): zuerst Inhalt, danach Behälter. */
function containerReturnableUnits(containerId: string): number {
  const inner = containerInnerReturnableUnits(containerId)
  if (inner > 0) return inner
  return containerShellReturnableUnits(containerId)
}

function containerShellTransportBackReturnableUnits(containerId: string): number {
  const shell = shellPackItemForContainer(containerId)
  if (!shell) return 0
  return Math.max(0, (shell.quantityTransportBack ?? 0) - (shell.quantityReturned ?? 0))
}

function containerInnerTransportBackReturnableUnits(containerId: string): number {
  let sum = 0
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    if (isNonActionableContainerLine(ci)) continue
    sum += Math.max(0, (ci.quantity_transport_back ?? 0) - (ci.quantity_returned ?? 0))
  }
  return sum
}

/** Camp/Event Tab «Transport (zurück) → Retour»: noch nicht retournierte Menge in der Kiste. */
function containerTransportBackReturnableUnits(containerId: string): number {
  const inner = containerInnerTransportBackReturnableUnits(containerId)
  if (inner > 0) return inner
  return containerShellTransportBackReturnableUnits(containerId)
}

function returnWholeCratePromptStorageKey(containerId: string): string {
  return `ematchef-return-whole-crate-prompt:${props.activityId}:${containerId}`
}

function hasSeenReturnWholeCratePrompt(containerId: string): boolean {
  try {
    return sessionStorage.getItem(returnWholeCratePromptStorageKey(containerId)) === '1'
  } catch {
    return false
  }
}

function markReturnWholeCratePromptSeen(containerId: string): void {
  try {
    sessionStorage.setItem(returnWholeCratePromptStorageKey(containerId), '1')
  } catch {
    /* ignore */
  }
}

function crateReturnQtyForMaterial(materialItemId: string): number {
  let sum = 0
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id === materialItemId) {
        sum += containerLineRemainingReturn(ci, c.id)
      }
    }
  }
  return sum
}

/** Gepackt, aber nie ans Event (lose) — aus dem Lager, noch nicht retourniert/eingelagert */
function loosePackedNeverIssuedQty(pi: ActivityPackItem): number {
  const packedNeverIssued = Math.max(0, pi.quantityPacked - pi.quantityIssued)
  const inCrates = crateReturnQtyForMaterial(pi.materialItemId)
  return Math.max(0, packedNeverIssued - inCrates)
}

/** Nie gepackt oder gepackt aber nie ans Event (lose, nicht in Kisten-Zeilen) */
function notTakenQtyForReturn(pi: ActivityPackItem): number {
  if (!isPackReturnOrUnpackWarehouseStage(activePackStage.value)) return 0
  const notPacked = Math.max(0, pi.quantityOrdered - pi.quantityPacked)
  let total = notPacked + loosePackedNeverIssuedQty(pi)
  if (pi.isConsumable && total > 0) {
    const consumed = consumableBookedConsumptionQty(pi)
    total = Math.max(0, total - Math.min(consumed, total))
  }
  return total
}

function containerLineRemainingReturn(ci: ActivityPackContainerItem, containerId?: string): number {
  if (isNonActionableContainerLine(ci)) return 0
  if (
    activePackStage.value === 'transport_back_returned' &&
    packWorkflowProfile.value === 'logistics'
  ) {
    return Math.max(0, (ci.quantity_transport_back ?? 0) - (ci.quantity_returned ?? 0))
  }
  const i = ci.quantity_issued ?? 0
  const r = ci.quantity_returned ?? 0
  const issuedRemain = Math.max(0, i - r)
  if (issuedRemain > 0) return issuedRemain
  if (!isPackReturnStage(activePackStage.value)) return 0
  const p = ci.quantity_packed ?? 0
  if (p <= r || i > 0) return 0
  // In Kiste gepackt, nie lose ans Event — Retour (Kiste am Event oder noch im Lager)
  if (containerId && p > r) {
    return p - r
  }
  return 0
}

/** Stück dieses Materials noch in Behälterzeilen am Event (nicht lose) — für Aufteilung in Stufe Event→Retour */
function containerStillAtEventQtyForMaterial(materialItemId: string): number {
  let sum = 0
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id === materialItemId) {
        sum += containerLineRemainingReturn(ci, c.id)
      }
    }
  }
  for (const c of packContainers.value) {
    if (c.container_material_item_id !== materialItemId) continue
    const sh = shellPackItemForContainer(c.id)
    if (sh?.materialItemId === materialItemId) {
      sum += Math.max(0, (sh.quantityIssued ?? 0) - (sh.quantityReturned ?? 0))
    }
  }
  return sum
}

function containerShellStillAtEventQty(containerId: string): number {
  const sh = shellPackItemForContainer(containerId)
  if (!sh) return 0
  return Math.max(0, (sh.quantityIssued ?? 0) - (sh.quantityReturned ?? 0))
}

/** Lose Menge noch am Event (ohne Behälteranteil) — Stufe Event→Retour */
function looseQtyStillAtEventForReturn(pi: ActivityPackItem): number {
  if (!isPackReturnStage(activePackStage.value)) return getStageLeftQty(pi)
  if (activePackStage.value === 'transport_back_returned' && packWorkflowProfile.value === 'logistics') {
    return looseQtyStillOnTransportBackForReturn(pi)
  }
  if (packContainers.value.length === 0) return getStageLeftQty(pi)
  return Math.max(0, getStageLeftQty(pi) - containerStillAtEventQtyForMaterial(pi.materialItemId))
}

/** Lose Rest auf «Transport (zurück) → Retour» (ohne Kistenanteil). */
function looseQtyStillOnTransportBackForReturn(pi: ActivityPackItem): number {
  if (activePackStage.value !== 'transport_back_returned' || packWorkflowProfile.value !== 'logistics') {
    return getStageLeftQty(pi)
  }
  if (packContainers.value.length === 0) return getStageLeftQty(pi)
  return Math.max(0, getStageLeftQty(pi) - transportBackQtyInContainersForMaterial(pi.materialItemId))
}

async function executeIssueContainerToEvent(containerId: string) {
  containerBulkLoadingId.value = containerId
  try {
    await issueAllPackContainerItems(props.activityId, containerId)
    toast.success(t('activities.packList.toastIssueContainer'))
    await loadAll()
    selectContainerTargetAfterIssueToEvent(containerId)
    emit('activityItemsChanged')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastIssueContainerFailed'))
  } finally {
    containerBulkLoadingId.value = null
  }
}

async function issueContainerToEvent(c: ActivityPackContainer) {
  if (containerBulkLoadingId.value) return
  if (!(await confirmPackStageForwardAllowed())) return
  const shell = shellPackItemForContainer(c.id)
  if (shell && needsShellCratePresenceConfirm(shell)) {
    const max = Math.max(packIssueForwardMax(shell), containerShellTakeMax(c.id))
    if (max >= 1) {
      await openShellCrateForwardModal(shell, max, { kind: 'issue_container', containerId: c.id })
    }
    return
  }
  if (!(await confirmMwHandoffBeforeIssueToEvent())) return
  await executeIssueContainerToEvent(c.id)
}

async function executeIssueContainerShellOnlyToEvent(containerId: string) {
  containerBulkLoadingId.value = containerId
  try {
    const shell = shellPackItemForContainer(containerId)
    if (!shell) {
      await executeIssueContainerToEvent(containerId)
      return
    }
    const shellRem = containerShellIssueableUnits(containerId)
    if (shellRem > 0) {
      await issueAllPackContainerItems(props.activityId, containerId)
    } else {
      const qty = containerShellTakeMax(containerId)
      if (qty < 1) return
      await postMovePackItem(props.activityId, shell.id, { stage: 'at_event', quantity: qty })
    }
    toast.success(t('activities.packList.toastIssueContainerShell'))
    await loadAll()
    selectContainerTargetAfterIssueToEvent(containerId)
    emit('activityItemsChanged')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(
      e.response?.data?.error || e.message || t('activities.packList.toastIssueContainerShellFailed'),
    )
  } finally {
    containerBulkLoadingId.value = null
  }
}

async function issueContainerShellOnlyToEvent(c: ActivityPackContainer) {
  if (containerBulkLoadingId.value) return
  if (containerShellTakeMax(c.id) < 1) return
  const shell = shellPackItemForContainer(c.id)
  if (shell && needsShellCratePresenceConfirm(shell)) {
    const max = containerShellTakeMax(c.id)
    if (max >= 1) {
      await openShellCrateForwardModal(shell, max, { kind: 'issue_container_shell', containerId: c.id })
    }
    return
  }
  if (!(await confirmMwHandoffBeforeIssueToEvent())) return
  await executeIssueContainerShellOnlyToEvent(c.id)
}

async function executeShellForwardPendingAfterCheck(item: ActivityPackItem) {
  const pending = shellForwardPendingAction.value
  if (pending.kind === 'check_only') return
  const qty = shellForwardMoveQty.value
  if (pending.kind === 'pack_move') {
    await executeMoveToNextStage(item, qty)
  } else if (pending.kind === 'issue_container') {
    await executeIssueContainerToEvent(pending.containerId)
  } else if (pending.kind === 'issue_container_shell') {
    await executeIssueContainerShellOnlyToEvent(pending.containerId)
  } else if (pending.kind === 'issue_container_line') {
    const ci = (containerItemsByContainerId.value[pending.containerId] ?? []).find(
      (row) => row.id === pending.containerItemId,
    )
    if (ci) {
      await executeIssueContainerLineToEvent(pending.containerId, ci, pending.qty)
    }
  } else if (pending.kind === 'return_container_modal') {
    const c = packContainers.value.find((x) => x.id === pending.containerId)
    if (c) await openReturnCrateModal(c)
  } else if (pending.kind === 'return_container_shell') {
    const shell = shellPackItemForContainer(pending.containerId)
    if (shell) {
      await executeReturnContainerShellToWarehouse(pending.containerId, shell, pending.qty)
    }
  } else if (pending.kind === 'return_container_line') {
    const ci = (containerItemsByContainerId.value[pending.containerId] ?? []).find(
      (row) => row.id === pending.containerItemId,
    )
    if (ci) {
      const pi = packItems.value.find((p) => p.materialItemId === ci.material_item_id)
      if (shouldOpenConsumptionModalOnReturn(pi)) {
        beginConsumableReturnForContainerLine(pending.containerId, ci, pending.qty)
      } else {
        await executeReturnContainerLineToWarehouse(pending.containerId, ci, pending.qty)
      }
    }
  }
  const shellC = packShellContainerForPackItem(item, packContainers.value)
  if (
    shellC &&
    pending.kind !== 'return_container_modal' &&
    pending.kind !== 'return_container_shell' &&
    pending.kind !== 'return_container_line'
  ) {
    await syncContainerContentsWithShellAtEvent(shellC.id)
  }
}

async function unissueContainerToPacked(c: ActivityPackContainer) {
  if (containerBulkLoadingId.value) return
  if (!(await confirmPackStageBackwardAllowed())) return
  if (containerUnissueableUnits(c.id) < 1) return
  containerBulkLoadingId.value = c.id
  try {
    await unissueAllPackContainerItems(props.activityId, c.id)
    toast.success(t('activities.packList.toastUnissueContainer'))
    await loadAll()
    emit('activityItemsChanged')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastUnissueFailed'))
  } finally {
    containerBulkLoadingId.value = null
  }
}

async function returnContainerToWarehouse(c: ActivityPackContainer) {
  if (containerBulkLoadingId.value) return
  const shell = shellPackItemForContainer(c.id)
  if (shell && needsShellCratePresenceConfirm(shell)) {
    const max = Math.max(1, containerReturnableUnits(c.id))
    await openShellCrateForwardModal(shell, max, { kind: 'return_container_modal', containerId: c.id })
    return
  }
  await openReturnCrateModal(c)
}

async function executeReturnContainerToWarehouse(containerId: string) {
  if (containerBulkLoadingId.value) return
  containerBulkLoadingId.value = containerId
  try {
    await returnAllPackContainerItems(props.activityId, containerId)
    toast.success(t('activities.packList.toastReturnContainer'))
    await loadAll()
    emit('activityItemsChanged')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastReturnFailed'))
  } finally {
    containerBulkLoadingId.value = null
  }
}

function togglePackContainerCollapsed(containerId: string) {
  collapsedPackContainers.value = {
    ...collapsedPackContainers.value,
    [containerId]: !collapsedPackContainers.value[containerId],
  }
}

function containerPullKey(containerId: string, itemId: string): string {
  return `${containerId}:${itemId}`
}

function containerIssueLineKey(containerId: string, itemId: string): string {
  return containerPullKey(containerId, itemId)
}

function initContainerPullQtyInputs(): void {
  const next: Record<string, number> = { ...containerPullQtyInputs.value }
  for (const c of packContainers.value) {
    const seen = new Set<string>()
    const lines = packContainerItemSectionsForContainer(c).flatMap((s) => s.lines)
    for (const ci of [...lines, ...(containerItemsByContainerId.value[c.id] ?? [])]) {
      if (seen.has(ci.id)) continue
      seen.add(ci.id)
      if (isNonActionableContainerLine(ci)) continue
      const k = containerPullKey(c.id, ci.id)
      const max = Math.max(0, ci.quantity_packed ?? 0)
      if (max < 1) continue
      if (next[k] == null || !Number.isFinite(next[k]) || next[k] < 1) {
        next[k] = max
      }
    }
  }
  containerPullQtyInputs.value = next
}

function containerPullInputValue(containerId: string, ci: ActivityPackContainerItem): number {
  const k = containerPullKey(containerId, ci.id)
  const raw = containerPullQtyInputs.value[k]
  const max = Math.max(1, ci.quantity_packed ?? 1)
  if (Number.isFinite(raw) && raw > 0) return Math.min(raw, max)
  containerPullQtyInputs.value = { ...containerPullQtyInputs.value, [k]: max }
  return max
}

function setContainerPullInput(
  containerId: string,
  ci: ActivityPackContainerItem,
  value: number | string,
): void {
  const k = containerPullKey(containerId, ci.id)
  const max = Math.max(1, ci.quantity_packed ?? 1)
  let qty = Math.floor(Number(value))
  if (!Number.isFinite(qty) || qty < 1) qty = max
  containerPullQtyInputs.value = { ...containerPullQtyInputs.value, [k]: Math.min(qty, max) }
}

function initContainerIssueLineInputs(): void {
  const next: Record<string, number> = { ...containerIssueLineInputs.value }
  for (const c of packContainers.value) {
    const seen = new Set<string>()
    const lines = packContainerItemSectionsForContainer(c).flatMap((s) => s.lines)
    for (const ci of [...lines, ...(containerItemsByContainerId.value[c.id] ?? [])]) {
      if (seen.has(ci.id)) continue
      seen.add(ci.id)
      if (isNonActionableContainerLine(ci)) continue
      const k = containerIssueLineKey(c.id, ci.id)
      const max = containerLineIssueableMax(ci)
      if (next[k] == null || !Number.isFinite(next[k]) || next[k] < 1) {
        next[k] = max > 0 ? max : 1
      } else if (max > 0 && (next[k] ?? 0) > max) {
        next[k] = max
      }
    }
  }
  containerIssueLineInputs.value = next
}

function containerIssueLineInputValue(containerId: string, ci: ActivityPackContainerItem): number {
  const k = containerIssueLineKey(containerId, ci.id)
  const raw = containerIssueLineInputs.value[k]
  if (Number.isFinite(raw) && raw > 0) return raw
  const max = containerLineIssueableMax(ci)
  const fallback = max > 0 ? max : 1
  containerIssueLineInputs.value = { ...containerIssueLineInputs.value, [k]: fallback }
  return fallback
}

function setContainerIssueLineInput(
  containerId: string,
  ci: ActivityPackContainerItem,
  value: number | string,
): void {
  const k = containerIssueLineKey(containerId, ci.id)
  const max = containerLineIssueableMax(ci)
  let qty = Math.floor(Number(value))
  if (!Number.isFinite(qty) || qty < 1) qty = max > 0 ? max : 1
  containerIssueLineInputs.value = { ...containerIssueLineInputs.value, [k]: Math.min(qty, max > 0 ? max : qty) }
}

function containerIssueLineLooseTitle(containerId: string, ci: ActivityPackContainerItem): string {
  const count = containerIssueLineInputValue(containerId, ci)
  if (showMwHandoffBanner.value) {
    return t('activities.packList.issueLineLooseTitleMw', { count })
  }
  const crate = (packContainers.value.find((c) => c.id === containerId)?.label ?? '').trim()
  if (crate) {
    return t('activities.packList.issueLineLooseWithoutCrateTitle', { count, crate })
  }
  return t('activities.packList.issueLineLooseTitle', { count })
}

async function confirmIssueLooseWithoutCrate(
  _containerId: string,
  ci: ActivityPackContainerItem,
  qty: number,
): Promise<boolean> {
  const material = (ci.material_name ?? '').trim() || t('activities.common.material')
  return confirmDialog({
    title: t('activities.packList.confirmIssueLooseWithoutCrateTitle'),
    message: t('activities.packList.confirmIssueLooseWithoutCrateMessage', {
      qty,
      material,
      activityName: activityDisplayName(),
      activityType: activityTypeLabel(),
    }),
    confirmText: t('activities.packList.confirmIssueLooseWithoutCrateProceed'),
    cancelText: t('activities.common.cancel'),
    variant: 'warning',
  })
}

function initContainerUnissueLineInputs(): void {
  const next: Record<string, number> = { ...containerUnissueLineInputs.value }
  for (const c of packContainers.value) {
    const seen = new Set<string>()
    const lines = packContainerItemSectionsForContainer(c).flatMap((s) => s.lines)
    for (const ci of [...lines, ...(containerItemsByContainerId.value[c.id] ?? [])]) {
      if (seen.has(ci.id)) continue
      seen.add(ci.id)
      if (isNonActionableContainerLine(ci)) continue
      const k = containerIssueLineKey(c.id, ci.id)
      const max = containerLineUnissueableMax(ci)
      if (next[k] == null || !Number.isFinite(next[k]) || next[k] < 1) {
        next[k] = max > 0 ? max : 1
      } else if (max > 0 && (next[k] ?? 0) > max) {
        next[k] = max
      }
    }
  }
  containerUnissueLineInputs.value = next
}

function containerUnissueLineInputValue(containerId: string, ci: ActivityPackContainerItem): number {
  const k = containerIssueLineKey(containerId, ci.id)
  const raw = containerUnissueLineInputs.value[k]
  if (Number.isFinite(raw) && raw > 0) return raw
  const max = containerLineUnissueableMax(ci)
  const fallback = max > 0 ? max : 1
  containerUnissueLineInputs.value = { ...containerUnissueLineInputs.value, [k]: fallback }
  return fallback
}

function setContainerUnissueLineInput(
  containerId: string,
  ci: ActivityPackContainerItem,
  value: number | string,
): void {
  const k = containerIssueLineKey(containerId, ci.id)
  const max = containerLineUnissueableMax(ci)
  let qty = Math.floor(Number(value))
  if (!Number.isFinite(qty) || qty < 1) qty = max > 0 ? max : 1
  containerUnissueLineInputs.value = {
    ...containerUnissueLineInputs.value,
    [k]: Math.min(qty, max > 0 ? max : qty),
  }
}

async function loadWarehouseTemplatesForContainers(): Promise<void> {
  const templateNext: Record<string, Set<string>> = {}
  const contentsNext: Record<string, RackContentsItem[]> = {}
  await Promise.all(
    packContainers.value.map(async (c) => {
      const batchId = (c.container_batch_id ?? '').trim()
      if (!batchId) return
      try {
        const data = await getContainerBatchContents(batchId)
        const mids = new Set<string>()
        const contents: RackContentsItem[] = []
        for (const row of data.contents ?? []) {
          const mid = (row.material_id ?? '').trim()
          if (mid) mids.add(mid)
          contents.push(row)
        }
        templateNext[c.id] = mids
        contentsNext[c.id] = contents
      } catch {
        /* Lager-Vorlage optional */
      }
    }),
  )
  containerWarehouseTemplateByContainerId.value = templateNext
  containerWarehouseContentsByContainerId.value = contentsNext
}

async function loadComboComponentsForShellPackItems(): Promise<void> {
  const mids = [
    ...new Set(
      packItems.value.filter((p) => p.materialType === 'physical_combo').map((p) => p.materialItemId),
    ),
  ].filter(Boolean)
  if (mids.length === 0) {
    comboComponentsByMaterialId.value = {}
    return
  }
  const next: Record<string, ComboComponent[]> = {}
  await Promise.all(
    mids.map(async (mid) => {
      try {
        next[mid] = await getComboComponents(mid)
      } catch {
        next[mid] = []
      }
    }),
  )
  comboComponentsByMaterialId.value = next
}

/** Phys.-Kombi mit Lager-Charge: Pack-Behälter anlegen, damit Kiste nicht lose + leer doppelt erscheint. */
async function syncLinkedShellPackContainers(): Promise<void> {
  if (!props.packListEditable) return
  const needing = linkedShellCombosNeedingPackContainer(
    packItems.value,
    packContainers.value,
    shellVirtualContainerMap(),
  )
  for (const pi of needing) {
    const packed = pi.quantityPacked ?? 0
    const ordered = pi.quantityOrdered ?? 0
    if (packed < 1 && ordered < 1) continue
    await ensurePackContainerForShellCombo(pi.id)
  }
}

async function loadContainersData(): Promise<void> {
  try {
    const list = await getActivityPackContainers(props.activityId)
    packContainers.value = [...list].sort((a, b) => a.label.localeCompare(b.label, locale.value))
    const map: Record<string, ActivityPackContainerItem[]> = {}
    await Promise.all(
      packContainers.value.map(async (c) => {
        try {
          map[c.id] = await getActivityPackContainerItems(props.activityId, c.id)
        } catch {
          map[c.id] = []
        }
      }),
    )
    containerItemsByContainerId.value = map
    await loadWarehouseTemplatesForContainers()
    await loadComboComponentsForShellPackItems()
    initContainerPullQtyInputs()
    initContainerIssueLineInputs()
    initContainerUnissueLineInputs()
    initContainerReturnLineInputs()
    initContainerShellReturnInputs()
    initContainerStoreLineInputs()
    initContainerShellStoreInputs()
    if (isPackForwardToEventStage(activePackStage.value)) {
      for (const c of packContainers.value) {
        if (!containerContentsTravelWithShellAtEvent(c.id)) continue
        let drift = 0
        for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
          if (isNonActionableContainerLine(ci)) continue
          drift += Math.max(0, containerLineInCrateQty(ci) - (ci.quantity_issued ?? 0))
        }
        if (drift > 0) await syncContainerContentsWithShellAtEvent(c.id)
      }
    }
  } catch {
    packContainers.value = []
    containerItemsByContainerId.value = {}
    containerWarehouseTemplateByContainerId.value = {}
    containerWarehouseContentsByContainerId.value = {}
    containerPullQtyInputs.value = {}
    containerIssueLineInputs.value = {}
    containerUnissueLineInputs.value = {}
  }
}

async function loadStockContainerBatches(): Promise<void> {
  if (!props.departmentId?.trim()) {
    stockContainerBatches.value = []
    return
  }
  stockBatchesLoading.value = true
  try {
    stockContainerBatches.value = await getContainerBatches(props.departmentId, {
      activityId: props.activityId,
    })
  } catch {
    stockContainerBatches.value = []
    toast.error(t('activities.packList.toastStockBatchesFailed'))
  } finally {
    stockBatchesLoading.value = false
  }
}

async function openAddContainerModal() {
  selectedStockBatchId.value = ''
  showAddContainerModal.value = true
  await loadStockContainerBatches()
  if (availableStockBatches.value.length === 1) {
    selectedStockBatchId.value = availableStockBatches.value[0].id
  }
}

async function submitAddContainer() {
  if (!canSubmitAddContainer.value) return

  const batch = stockContainerBatches.value.find((b) => b.id === selectedStockBatchId.value)
  const containerBatchId = selectedStockBatchId.value
  const raw =
    batch?.display_label?.trim() ||
    [batch?.serial_number, batch?.label || batch?.material_name].filter(Boolean).join(' – ') ||
    batch?.material_name ||
    t('activities.common.crate')
  const label = raw.slice(0, 120)

  containerMutationLoading.value = true
  try {
    const created = await createActivityPackContainer(props.activityId, {
      label,
      container_batch_id: containerBatchId,
    })
    await loadContainersData()
    if (containerBatchId) {
      const items = await getPackItems(props.activityId)
      packItems.value = items
      initMoveQtyInputs()
      emit('activityItemsChanged')
    }
    showAddContainerModal.value = false
    collapsedPackContainers.value = {
      ...collapsedPackContainers.value,
      [created.id]: false,
    }
    selectActiveContainer(created.id)
    await nextTick()
    const scrollId =
      isPackForwardToEventStage(activePackStage.value)
        ? `pack-container-issue-${created.id}`
        : `pack-container-${created.id}`
    document.getElementById(scrollId)?.scrollIntoView({
      behavior: 'smooth',
      block: 'nearest',
    })
    toast.success(t('activities.packList.toastContainerAdded'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastContainerAddFailed'))
  } finally {
    containerMutationLoading.value = false
  }
}

function selectActiveContainer(containerId: string) {
  activePackTarget.value = { kind: 'container', containerId }
}

function toggleActiveContainer(containerId: string) {
  const tgt = activePackTarget.value
  if (tgt?.kind === 'container' && tgt.containerId === containerId) {
    activePackTarget.value = null
    return
  }
  selectActiveContainer(containerId)
}

function selectActiveLoose() {
  activePackTarget.value = { kind: 'loose' }
}

function selectActiveCombo(packItemId: string) {
  const pi = packItems.value.find((p) => p.id === packItemId)
  const linked = pi ? packShellContainerForPackItem(pi, packContainers.value) : undefined
  if (linked) {
    activePackTarget.value = { kind: 'container', containerId: linked.id }
    return
  }
  activePackTarget.value = { kind: 'combo', packItemId }
}

function toggleActiveLoose() {
  if (activePackTarget.value?.kind === 'loose') {
    activePackTarget.value = null
    return
  }
  selectActiveLoose()
}

function toggleActiveCombo(packItemId: string) {
  const pi = packItems.value.find((p) => p.id === packItemId)
  const linked = pi ? packShellContainerForPackItem(pi, packContainers.value) : undefined
  if (linked) {
    toggleActiveContainer(linked.id)
    return
  }
  const tgt = activePackTarget.value
  if (tgt?.kind === 'combo' && tgt.packItemId === packItemId) {
    activePackTarget.value = null
    return
  }
  selectActiveCombo(packItemId)
}

const activePackTargetCrateLabel = computed(() => {
  const tgt = activePackTarget.value
  if (tgt?.kind === 'container') {
    return packContainers.value.find((c) => c.id === tgt.containerId)?.label ?? ''
  }
  if (tgt?.kind === 'combo') {
    const pi = packItems.value.find((p) => p.id === tgt.packItemId)
    return pi ? physicalComboAddTargetLabel(pi) : ''
  }
  return ''
})

function forwardMoveTitleForItem(_pi: ActivityPackItem): string {
  const tgt = activePackTarget.value
  if (
    activePackStage.value === 'confirmed_packed' &&
    (tgt?.kind === 'container' || tgt?.kind === 'combo')
  ) {
    const label = activePackTargetCrateLabel.value
    if (label) {
      return t('activities.packList.titleMoveToCrate', {
        stage: activeStageConfig.value.rightLabel,
        label,
      })
    }
  }
  return t('activities.packList.titleMoveTo', { stage: activeStageConfig.value.rightLabel })
}

async function assignMaterialToContainer(
  pi: ActivityPackItem,
  containerId: string,
  qty: number,
  opts?: { successMessage?: string | null },
) {
  const max = looseQtyForPackItem(pi)
  const q = Math.min(Math.max(1, Math.floor(qty)), max)
  if (q < 1 || q > max) return

  containerMutationLoading.value = true
  try {
    const items = containerItemsByContainerId.value[containerId] ?? []
    const existing = items.find((row) => row.material_item_id === pi.materialItemId)
    if (existing) {
      await updateActivityPackContainerItem(props.activityId, containerId, existing.id, {
        quantity_packed: existing.quantity_packed + q,
      })
    } else {
      await createActivityPackContainerItem(props.activityId, containerId, {
        material_item_id: pi.materialItemId,
        quantity_packed: q,
      })
    }
    await loadContainersData()
    if (opts?.successMessage !== null) {
      toast.success(opts?.successMessage ?? t('activities.packList.toastMaterialInContainer'))
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastAssignFailed'))
  } finally {
    containerMutationLoading.value = false
  }
}

async function assignLooseAtEventToContainer(
  pi: ActivityPackItem,
  containerId: string,
  qty: number,
) {
  const max = crateAssignLooseAtEventMax(pi)
  const q = Math.min(max, Math.max(1, Math.floor(qty)))
  if (q < 1) return

  movingId.value = pi.id
  containerMutationLoading.value = true
  try {
    const items = containerItemsByContainerId.value[containerId] ?? []
    const existing = items.find((row) => row.material_item_id === pi.materialItemId)
    if (existing) {
      await updateActivityPackContainerItem(props.activityId, containerId, existing.id, {
        quantity_packed: existing.quantity_packed + q,
        quantity_issued: (existing.quantity_issued ?? 0) + q,
      })
    } else {
      await createActivityPackContainerItem(props.activityId, containerId, {
        material_item_id: pi.materialItemId,
        quantity_packed: q,
        quantity_issued: q,
      })
    }
    await loadContainersData()
    emit('activityItemsChanged')
    toast.success(t('activities.packList.toastMoveToContainerDirect'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastAssignFailed'))
  } finally {
    movingId.value = null
    containerMutationLoading.value = false
  }
}

async function assignDirectToActiveContainer(
  pi: ActivityPackItem,
  containerId: string,
  qty?: number,
  source: CrateAssignSource = 'packed-left',
) {
  if (
    source === 'loose-at-event' &&
    isPackForwardToEventStage(activePackStage.value) &&
    containerHasIssuedAtEvent(containerId)
  ) {
    const max = crateAssignLooseAtEventMax(pi)
    const q =
      qty != null && qty > 0 ? Math.min(max, Math.max(1, Math.floor(qty))) : max
    if (q < 1) return
    await assignLooseAtEventToContainer(pi, containerId, q)
    return
  }

  const max = looseQtyForPackItem(pi)
  if (max < 1) return
  const q =
    qty != null && qty > 0 ? Math.min(max, Math.max(1, Math.floor(qty))) : max
  if (
    isPackForwardToEventStage(activePackStage.value) &&
    containerHasIssuedAtEvent(containerId)
  ) {
    if (!(await confirmMwHandoffBeforeIssueToEvent())) return
    movingId.value = pi.id
    containerMutationLoading.value = true
    try {
      const updated = await postMovePackItem(props.activityId, pi.id, {
        stage: 'at_event',
        quantity: q,
      })
      applyUpdatedItem(updated)
      const items = containerItemsByContainerId.value[containerId] ?? []
      const existing = items.find((row) => row.material_item_id === pi.materialItemId)
      if (existing) {
        await updateActivityPackContainerItem(props.activityId, containerId, existing.id, {
          quantity_packed: existing.quantity_packed + q,
          quantity_issued: (existing.quantity_issued ?? 0) + q,
        })
      } else {
        await createActivityPackContainerItem(props.activityId, containerId, {
          material_item_id: pi.materialItemId,
          quantity_packed: q,
          quantity_issued: q,
        })
      }
      await loadContainersData()
      emit('activityItemsChanged')
      toast.success(t('activities.packList.toastMoveToContainerDirect'))
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string } }; message?: string }
      toast.error(e.response?.data?.error || e.message || t('activities.packList.toastAssignFailed'))
    } finally {
      movingId.value = null
      containerMutationLoading.value = false
    }
    return
  }
  await assignMaterialToContainer(pi, containerId, q, {
    successMessage: t('activities.packList.toastMoveToContainerDirect'),
  })
}

async function pullFromContainer(containerId: string, ci: ActivityPackContainerItem) {
  if (!(await confirmPackStageBackwardAllowed())) return
  if (isNonActionableContainerLine(ci)) return
  const k = containerPullKey(containerId, ci.id)
  let qty = Math.floor(Number(containerPullQtyInputs.value[k] ?? ci.quantity_packed))
  if (!Number.isFinite(qty) || qty < 1) qty = 1
  qty = Math.min(qty, ci.quantity_packed)
  if (qty < 1) return

  containerMutationLoading.value = true
  try {
    const newQty = ci.quantity_packed - qty
    if (newQty <= 0) {
      await deleteActivityPackContainerItem(props.activityId, containerId, ci.id)
    } else {
      await updateActivityPackContainerItem(props.activityId, containerId, ci.id, {
        quantity_packed: newQty,
      })
    }
    await loadContainersData()
    toast.success(
      newQty <= 0
        ? t('activities.packList.toastPullRemoved')
        : t('activities.packList.toastPullPartial', { qty }),
    )
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastAdjustFailed'))
  } finally {
    containerMutationLoading.value = false
  }
}

async function executeIssueContainerLineToEvent(
  containerId: string,
  ci: ActivityPackContainerItem,
  qty: number,
) {
  const pi = packItems.value.find((p) => p.materialItemId === ci.material_item_id)
  if (!pi) {
    toast.error(t('activities.packList.toastNoPackLine'))
    return
  }

  containerMutationLoading.value = true
  try {
    await postMovePackItem(props.activityId, pi.id, { stage: 'at_event', quantity: qty })
    const packedBefore = ci.quantity_packed ?? 0
    const newPacked = Math.max(0, packedBefore - qty)
    if (newPacked <= 0) {
      await deleteActivityPackContainerItem(props.activityId, containerId, ci.id)
    } else {
      await updateActivityPackContainerItem(props.activityId, containerId, ci.id, {
        quantity_packed: newPacked,
      })
    }
    const items = await getPackItems(props.activityId)
    packItems.value = items
    initMoveQtyInputs()
    await loadContainersData()
    emit('activityItemsChanged')
    toast.success(t('activities.packList.toastIssueLineLooseSuccess', { qty }))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastIssueLineFailed'))
    try {
      const items = await getPackItems(props.activityId)
      packItems.value = items
      initMoveQtyInputs()
      await loadContainersData()
    } catch {
      /* ignore secondary failure */
    }
  } finally {
    containerMutationLoading.value = false
  }
}

async function issueContainerLineToEvent(containerId: string, ci: ActivityPackContainerItem) {
  if (!(await confirmPackStageForwardAllowed())) return
  if (!isPackForwardToEventStage(activePackStage.value)) return
  if (isNonActionableContainerLine(ci)) return
  const max = containerLineIssueableMax(ci)
  if (max < 1) {
    toast.error(t('activities.packList.toastNothingLeftToIssue'))
    return
  }
  let qty = Math.floor(Number(containerIssueLineInputValue(containerId, ci)))
  if (!Number.isFinite(qty) || qty < 1) qty = max
  qty = Math.min(qty, max)
  if (qty < 1) return
  setContainerIssueLineInput(containerId, ci, qty)

  const shell = shellPackItemForContainer(containerId)
  if (shell && needsShellCratePresenceConfirm(shell)) {
    await openShellCrateForwardModal(shell, Math.max(packIssueForwardMax(shell), qty), {
      kind: 'issue_container_line',
      containerId,
      containerItemId: ci.id,
      qty,
    })
    return
  }

  if (!(await confirmIssueLooseWithoutCrate(containerId, ci, qty))) return

  await executeIssueContainerLineToEvent(containerId, ci, qty)
}

async function unissueContainerLineToPacked(containerId: string, ci: ActivityPackContainerItem) {
  if (!(await confirmPackStageBackwardAllowed())) return
  if (!isPackForwardToEventStage(activePackStage.value)) return
  if (isNonActionableContainerLine(ci)) return
  await syncContainerContentsWithShellAtEvent(containerId)
  const freshCi = (containerItemsByContainerId.value[containerId] ?? []).find((row) => row.id === ci.id) ?? ci
  const max = containerLineUnissueableMax(freshCi)
  if (max < 1) {
    toast.error(t('activities.packList.toastNothingToUnissue'))
    return
  }
  const k = containerIssueLineKey(containerId, ci.id)
  let qty = Math.floor(Number(containerUnissueLineInputs.value[k] ?? 0))
  if (!Number.isFinite(qty) || qty < 1) qty = max
  qty = Math.min(qty, max)
  if (qty < 1) return
  containerUnissueLineInputs.value = { ...containerUnissueLineInputs.value, [k]: qty }

  const pi = packItems.value.find((p) => p.materialItemId === freshCi.material_item_id)
  if (!pi) {
    toast.error(t('activities.packList.toastNoPackLine'))
    return
  }

  const ret = freshCi.quantity_returned ?? 0
  containerMutationLoading.value = true
  try {
    await postMoveBackPackItem(props.activityId, pi.id, { stage: getBackendStage(), quantity: qty })
    await updateActivityPackContainerItem(props.activityId, containerId, freshCi.id, {
      quantity_issued: Math.max(ret, (freshCi.quantity_issued ?? 0) - qty),
    })
    const items = await getPackItems(props.activityId)
    packItems.value = items
    initMoveQtyInputs()
    await loadContainersData()
    emit('activityItemsChanged')
    toast.success(t('activities.packList.toastUnissueLineSuccess', { qty }))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastUnissueFailed'))
    try {
      const items = await getPackItems(props.activityId)
      packItems.value = items
      initMoveQtyInputs()
      await loadContainersData()
    } catch {
      /* ignore secondary failure */
    }
  } finally {
    containerMutationLoading.value = false
  }
}

async function confirmDeleteContainer(c: ActivityPackContainer) {
  const ok = await confirmDialog({
    title: t('activities.packList.confirmDeleteTitle'),
    message: t('activities.packList.confirmDeleteMessage', { label: c.label }),
    confirmText: t('activities.common.delete'),
    cancelText: t('activities.common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  const deletedId = c.id
  const shellPackItemId = shellPackItemForContainer(deletedId)?.id
  containerMutationLoading.value = true
  try {
    await deleteActivityPackContainer(props.activityId, deletedId)
    if (
      activePackTarget.value?.kind === 'container' &&
      activePackTarget.value.containerId === deletedId
    ) {
      activePackTarget.value = null
    }
    if (shellPackItemId) {
      const nextVirtual = { ...shellComboVirtualContainerByPackItemId.value }
      delete nextVirtual[shellPackItemId]
      shellComboVirtualContainerByPackItemId.value = nextVirtual
    }
    await loadAll()
    emit('activityItemsChanged')
    toast.success(t('activities.packList.toastContainerDeleted'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastDeleteFailed'))
  } finally {
    containerMutationLoading.value = false
  }
}

const canInitPackList = computed(
  () => props.packListEditable && props.status === 'packing' && packItems.value.length === 0,
)

function getStageLeftQtyForStage(item: ActivityPackItem, stage: PackStage): number {
  return computeStageLeftQty(item, stage, packWorkflowProfile.value)
}

function getStageRightQtyForStage(item: ActivityPackItem, stage: PackStage): number {
  return computeStageRightQty(item, stage, packWorkflowProfile.value)
}

function getStageLeftQty(item: ActivityPackItem): number {
  return getStageLeftQtyForStage(item, activePackStage.value)
}

function getStageRightQty(item: ActivityPackItem): number {
  return getStageRightQtyForStage(item, activePackStage.value)
}

function getStageProgressDoneQty(item: ActivityPackItem): number {
  if (isPackReturnStage(activePackStage.value) && item.isConsumable) {
    return consumableReturnDoneQty(item)
  }
  if (isPackReturnStage(activePackStage.value)) {
    return getStageRightQty(item) + returnProgressNotTakenQty(item)
  }
  return getStageRightQty(item)
}

function crateCheckGapForMaterial(materialItemId: string): number {
  let gap = 0
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id !== materialItemId) continue
      const overlay = crateCheckOverlayForContainerLine(ci)
      if (overlay) {
        gap = Math.max(gap, Math.max(0, overlay.sollQty - overlay.countedQty))
      }
    }
  }
  return gap
}

function pendingIssueableInCratesForMaterial(materialItemId: string): number {
  let sum = 0
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id === materialItemId) {
        sum += containerLineIssueableMax(ci)
      }
    }
  }
  return sum
}

/** Noch ans Event buchbar — lose + Kiste (nach Kistencheck-Ist, nicht Packlisten-Soll). */
function pendingIssuePartsForPackItem(pi: ActivityPackItem): StageProgressPendingParts {
  if (isPackForwardToEventStage(activePackStage.value)) {
    const shellC = isCrateShellPackItem(pi, packContainers.value)
      ? packShellContainerForPackItem(pi, packContainers.value)
      : undefined
    if (shellC && containerHasIssuedAtEvent(shellC.id)) {
      return { total: 0, loosePart: 0, inCratePart: 0, crateCheckGap: 0 }
    }
    const loosePart = shellC
      ? Math.max(0, containerShellTakeMax(shellC.id))
      : Math.max(0, looseQtyForPackItem(pi))
    const inCratePart = isCrateShellPackItem(pi, packContainers.value)
      ? 0
      : pendingIssueableInCratesForMaterial(pi.materialItemId)
    const crateCheckGap = crateCheckGapForMaterial(pi.materialItemId)
    return {
      total: loosePart + inCratePart,
      loosePart,
      inCratePart,
      crateCheckGap,
    }
  }
  if (isPackReturnStage(activePackStage.value)) {
    if (pi.isConsumable) {
      const consumptionOpen = consumableConsumptionRemaining(pi)
      const loosePart =
        consumptionOpen > 0 ? 0 : looseQtyStillAtEventForReturn(pi)
      const inCratePart = isCrateShellPackItem(pi, packContainers.value)
        ? 0
        : crateReturnQtyForMaterial(pi.materialItemId)
      return {
        total: consumptionOpen + loosePart + inCratePart,
        loosePart,
        inCratePart,
        consumptionOpen,
        crateCheckGap: 0,
      }
    }
    const loosePart = looseQtyStillAtEventForReturn(pi)
    const inCratePart = isCrateShellPackItem(pi, packContainers.value)
      ? 0
      : crateReturnQtyForMaterial(pi.materialItemId)
    return {
      total: loosePart + inCratePart,
      loosePart,
      inCratePart,
      crateCheckGap: 0,
    }
  }
  const pending = getStagePendingQtyClassic(pi)
  return { total: pending, loosePart: pending, inCratePart: 0, crateCheckGap: 0 }
}

function getStagePendingQtyClassic(item: ActivityPackItem): number {
  const total = getStageTotalQty(item)
  if (total <= 0) return 0
  let done: number
  if (activePackStage.value !== 'confirmed_packed') {
    done = getStageProgressDoneQty(item)
  } else {
    const leftRaw = getStageLeftQty(item)
    const shells = packContainerBatchCountByMaterialItemId.value[item.materialItemId] ?? 0
    const virtualPacked = Math.min(Math.max(0, shells), leftRaw)
    done = getStageRightQty(item) + virtualPacked
  }
  return Math.max(0, total - done)
}

function getStagePendingQty(item: ActivityPackItem): number {
  if (isPackForwardToEventStage(activePackStage.value)) {
    return pendingIssuePartsForPackItem(item).total
  }
  return getStagePendingQtyClassic(item)
}

function pendingCrateLabelsForMaterial(materialItemId: string): string[] {
  const labels = new Set<string>()
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id === materialItemId && containerLineIssueableMax(ci) > 0) {
        labels.add(c.label)
      }
    }
    const shell = shellPackItemForContainer(c.id)
    if (shell?.materialItemId === materialItemId && containerShellIssueableUnits(c.id) > 0) {
      labels.add(c.label)
    }
  }
  return [...labels].sort((a, b) => a.localeCompare(b, locale.value))
}

type StageProgressPendingParts = {
  total: number
  loosePart: number
  inCratePart: number
  crateCheckGap: number
  consumptionOpen?: number
}

type StageProgressPendingLine = {
  key: string
  qty: number
  material: string
  actionHint: string
}

function pendingReturnCrateLabelsForMaterial(materialItemId: string): string[] {
  const labels = new Set<string>()
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id === materialItemId && containerLineRemainingReturn(ci, c.id) > 0) {
        labels.add(c.label)
      }
    }
    const shell = shellPackItemForContainer(c.id)
    if (shell?.materialItemId === materialItemId && containerShellStillAtEventQty(c.id) > 0) {
      labels.add(c.label)
    }
  }
  return [...labels].sort((a, b) => a.localeCompare(b, locale.value))
}

function progressPendingActionHint(
  pending: number,
  loosePart: number,
  inCratePart: number,
  crateLabels: string[],
  crateCheckGap = 0,
  consumptionOpen = 0,
): string {
  const crate = crateLabels.join(', ')
  if (isPackReturnStage(activePackStage.value)) {
    if (consumptionOpen > 0 && loosePart <= 0 && inCratePart <= 0) {
      return t('activities.packList.progressPendingConsumableConsumption')
    }
    if (consumptionOpen > 0 && (loosePart > 0 || inCratePart > 0)) {
      return t('activities.packList.progressPendingConsumableMixed', { n: consumptionOpen })
    }
    if (inCratePart > 0 && loosePart <= 0) {
      return crate
        ? t('activities.packList.progressPendingReturnActionInCrate', { crate })
        : t('activities.packList.progressPendingReturnActionInCrateGeneric')
    }
    if (loosePart > 0 && inCratePart <= 0) {
      return t('activities.packList.progressPendingReturnActionLoose')
    }
    if (loosePart > 0 && inCratePart > 0) {
      return crate
        ? t('activities.packList.progressPendingReturnActionMixed', {
            loose: loosePart,
            inCrate: inCratePart,
            crate,
          })
        : t('activities.packList.progressPendingReturnActionMixedNoCrate', {
            loose: loosePart,
            inCrate: inCratePart,
          })
    }
    return t('activities.packList.progressPendingReturnActionDefault')
  }
  if (inCratePart > 0 && loosePart <= 0) {
    if (crateCheckGap > 0 && crate) {
      return t('activities.packList.progressPendingActionInCrateWithGap', {
        crate,
        missing: crateCheckGap,
      })
    }
    return crate
      ? t('activities.packList.progressPendingActionInCrate', { crate })
      : t('activities.packList.progressPendingActionInCrateGeneric')
  }
  if (loosePart > 0 && inCratePart <= 0) {
    return t('activities.packList.progressPendingActionLoose')
  }
  if (loosePart > 0 && inCratePart > 0) {
    return crate
      ? t('activities.packList.progressPendingActionMixed', { loose: loosePart, inCrate: inCratePart, crate })
      : t('activities.packList.progressPendingActionMixedNoCrate', { loose: loosePart, inCrate: inCratePart })
  }
  return t('activities.packList.progressPendingActionDefault')
}

const stageProgressPendingLines = computed((): StageProgressPendingLine[] => {
  const lines: StageProgressPendingLine[] = []
  for (const p of packItems.value) {
    if (isOrphanShellWithoutPackContainer(p)) continue
    const parts = pendingIssuePartsForPackItem(p)
    if (parts.total <= 0) continue
    if (
      parts.inCratePart <= 0 &&
      parts.loosePart <= 0 &&
      (parts.consumptionOpen ?? 0) <= 0
    ) {
      continue
    }
    const crates = isPackReturnStage(activePackStage.value)
      ? pendingReturnCrateLabelsForMaterial(p.materialItemId)
      : pendingCrateLabelsForMaterial(p.materialItemId)
    lines.push({
      key: p.id,
      qty: parts.consumptionOpen && parts.loosePart <= 0 && parts.inCratePart <= 0
        ? parts.consumptionOpen
        : parts.total,
      material: (p.materialName ?? '').trim() || t('activities.common.material'),
      actionHint: progressPendingActionHint(
        parts.total,
        parts.loosePart,
        parts.inCratePart,
        crates,
        parts.crateCheckGap,
        parts.consumptionOpen ?? 0,
      ),
    })
  }
  return lines.sort((a, b) => a.material.localeCompare(b.material, locale.value))
})

const stageProgressPendingTitle = computed(() => {
  if (stageProgress.value >= 100 || stageProgressPendingLines.value.length === 0) return undefined
  const header = t('activities.packList.progressPendingTitle', {
    stage: activeStageConfig.value.rightLabel,
  })
  const body = stageProgressPendingLines.value
    .map((line) => {
      const short = t('activities.packList.progressPendingLineShort', {
        qty: line.qty,
        material: line.material,
      })
      return `${short} — ${line.actionHint}`
    })
    .join('\n')
  return `${header}\n${body}`
})

function stageProgressPendingConfirmMessage(
  variant: 'status' | 'transition' | 'return' = 'status',
): string {
  const lines = stageProgressPendingLines.value
  if (lines.length === 0) {
    if (variant === 'return') {
      return t('activities.packList.confirmReturnWorkflowStatusMessage', {
        count: stageLeftHeaderCount.value,
      })
    }
    if (variant === 'transition') {
      return t('activities.packList.confirmWorkflowMessage', { count: stageLeftItems.value.length })
    }
    return t('activities.packList.confirmWorkflowStatusMessage', {
      count: stageLeftHeaderCount.value,
    })
  }
  const list = lines
    .map((line) => {
      const short = t('activities.packList.progressPendingLineShort', {
        qty: line.qty,
        material: line.material,
      })
      return `${short} — ${line.actionHint}`
    })
    .join('\n')
  if (variant === 'return') {
    return t('activities.packList.confirmReturnWorkflowStatusMessageList', { list })
  }
  return variant === 'transition'
    ? t('activities.packList.confirmWorkflowPendingMessageList', { list })
    : t('activities.packList.confirmWorkflowStatusMessageList', { list })
}

function getStageTotalQty(item: ActivityPackItem): number {
  const raw = computeStageTotalQty(item, activePackStage.value, packWorkflowProfile.value)
  if (isPackUnpackStage(activePackStage.value)) {
    return retourAccountingForUnpackLoose(item).retourTotal
  }
  if (isPackReturnStage(activePackStage.value) && item.isConsumable) {
    const booked = consumableTotalBookedQty(item.materialItemId)
    return Math.max(consumableReturnDoneQty(item), Math.min(raw, booked > 0 ? booked : raw))
  }
  if (isPackReturnStage(activePackStage.value)) {
    return raw + returnProgressNotTakenQty(item)
  }
  if (isPackForwardToEventStage(activePackStage.value)) {
    const gap = crateCheckGapForMaterial(item.materialItemId)
    return Math.max(getStageRightQty(item), raw - gap)
  }
  return raw
}

/** Max. Stück die links per Pfeil buchbar sind (nur lose bei Gepackt→Event / Event→Retour) */
function packIssueForwardMax(pi: ActivityPackItem): number {
  if (isPackForwardToEventStage(activePackStage.value)) {
    if (isCrateShellPackItem(pi, packContainers.value)) {
      return getStageLeftQty(pi)
    }
    return Math.min(looseQtyForPackItem(pi), getStageLeftQty(pi))
  }
  if (isPackReturnStage(activePackStage.value)) {
    if (activePackStage.value === 'transport_back_returned' && packWorkflowProfile.value === 'logistics') {
      return Math.max(consumablePhysicalReturnMax(pi), looseQtyStillOnTransportBackForReturn(pi))
    }
    return consumablePhysicalReturnMax(pi)
  }
  if (isPackUnpackStage(activePackStage.value)) {
    return pendingStoreLooseQtyForPackItem(pi)
  }
  return effectiveStageLeftQty(pi)
}

/** MW «Bestätigt → Gepackt»: Eingabe bis bestellte Menge (Teilpacken mit Hinweis). */
function packForwardInputQtyMax(pi: ActivityPackItem): number {
  if (activePackStage.value === 'confirmed_packed' && canManageMaterials.value) {
    return Math.max(1, pi.quantityOrdered ?? 0)
  }
  return packIssueForwardMax(pi)
}

function packForwardWarnBelowOrdered(pi: ActivityPackItem): number | undefined {
  if (activePackStage.value !== 'confirmed_packed' || !canManageMaterials.value) {
    return undefined
  }
  const ordered = Math.max(0, pi.quantityOrdered ?? 0)
  return ordered > 0 ? ordered : undefined
}

function packForwardMoveControlLimits(pi: ActivityPackItem): {
  max: number
  inputMax: number
  warnIfBelow?: number
} {
  if (showCrateAssignUpControls(pi)) {
    const m = crateAssignUpMax(pi)
    return { max: m, inputMax: m }
  }
  return {
    max: packIssueForwardMax(pi),
    inputMax: packForwardInputQtyMax(pi),
    warnIfBelow: packForwardWarnBelowOrdered(pi),
  }
}

/** Auf «Gepackt → Am Event»: oben nur lose Restmenge; was nur in Behältern liegt, erscheint unten bei den Behältern. */
/** Anzahl Pack-Behälter mit zugeordneter Lager-Kiste pro Material — diese Einheiten nicht links per Pfeil schieben. */
const packContainerBatchCountByMaterialItemId = computed(() => {
  const m: Record<string, number> = {}
  for (const c of packContainers.value) {
    if (!(c.container_batch_id ?? '').trim()) continue
    const directMid = (c.container_material_item_id ?? '').trim()
    if (directMid) {
      m[directMid] = (m[directMid] ?? 0) + 1
      continue
    }
    for (const pi of packItems.value) {
      if (!isCrateShellPackItem(pi, packContainers.value)) continue
      if (packShellContainerForPackItem(pi, packContainers.value)?.id === c.id) {
        m[pi.materialItemId] = (m[pi.materialItemId] ?? 0) + 1
        break
      }
    }
  }
  return m
})

/** Sichtbare Restmenge links: Roh-Rest minus Einheiten, die bereits als Kisten-Batch am Behälter hängen. */
function effectiveStageLeftQty(p: ActivityPackItem): number {
  if (isPackUnpackStage(activePackStage.value)) {
    const acct = retourAccountingForUnpackLoose(p)
    return Math.max(0, acct.retourTotal - (p.quantityStored ?? 0))
  }
  if (activePackStage.value !== 'confirmed_packed') {
    return getStageLeftQty(p)
  }
  const raw = getStageLeftQty(p)
  if (isPhysicalComboAsSet(p, packContainers.value)) return raw
  const shells = packContainerBatchCountByMaterialItemId.value[p.materialItemId] ?? 0
  if (shells <= 0) return raw
  return Math.max(0, raw - Math.min(shells, raw))
}

/**
 * Gepackt → Am Event: links nur Positionen mit noch lose ausgebbarer Restmenge.
 * Reine Behälter-Reste nur unter «Kisten», nicht doppelt als Karte oben.
 */
const stageLeftItems = computed(() =>
  packItems.value.filter((p) => {
    if (isOrphanShellWithoutPackContainer(p)) return false
    if (
      showPackContainersUi.value &&
      isPackForwardToEventStage(activePackStage.value) &&
      crateShellExcludedFromLooseForwardList(
        p,
        packContainers.value,
        true,
        shellVirtualContainerMap(),
        activePackStage.value,
      )
    ) {
      return false
    }
    if (
      hideShellPackItemOnConfirmedPackedLeft(
        p,
        packContainers.value,
        activePackStage.value,
        showPackContainersUi.value,
      )
    ) {
      return false
    }
    if (effectiveStageLeftQty(p) <= 0 && !consumableShowsZeroOnStageLeft(p)) return false
    if (
      isPackForwardToEventStage(activePackStage.value) &&
      showPackContainersUi.value &&
      getStageLeftQty(p) > 0 &&
      looseQtyForPackItem(p) <= 0 &&
      !isCrateShellPackItem(p, packContainers.value)
    ) {
      return false
    }
    if (isPackReturnStage(activePackStage.value) && p.isConsumable) {
      if (consumableConsumptionRemaining(p) > 0) return false
      if (consumablePhysicalReturnMax(p) <= 0 && !consumableShowsZeroOnStageLeft(p)) return false
    }
    if (
      isPackReturnStage(activePackStage.value) &&
      packContainers.value.length > 0 &&
      getStageLeftQty(p) > 0
    ) {
      if (looseQtyStillAtEventForReturn(p) <= 0) return false
    }
    if (
      isPackUnpackStage(activePackStage.value) &&
      packContainers.value.length > 0 &&
      getStageLeftQty(p) > 0
    ) {
      if (pendingStoreLooseQtyForPackItem(p) <= 0) return false
    }
    if (
      isPackUnpackStage(activePackStage.value) &&
      showPackContainersUi.value &&
      isCrateShellPackItem(p, packContainers.value) &&
      packShellContainerForPackItem(p, packContainers.value) != null
    ) {
      return false
    }
    return true
  }),
)

const packContainersSortedWarehouseOnlyVisible = computed(() =>
  packContainersSortedWarehouseOnly.value.filter((c) => !isPackContainerMerged(c)),
)

const stageLeftHeaderCount = computed(() => {
  if (isPackForwardToEventStage(activePackStage.value) && showPackContainersUi.value) {
    return stageLeftItems.value.length + packContainersSortedWarehouseOnlyVisible.value.length
  }
  if (isPackReturnPipelineStage(activePackStage.value) && showPackContainersUi.value) {
    return stageLeftItems.value.length + packContainersAtEventForReturnLeft.value.length
  }
  if (isPackUnpackStage(activePackStage.value) && showPackContainersUi.value) {
    return stageLeftItems.value.length + packContainersPendingUnpackLeft.value.length
  }
  return stageLeftItems.value.length
})

const stageRightHeaderCount = computed(() => {
  if (isPackUnpackStage(activePackStage.value)) {
    const looseCount = groupsStoredLoose.value.reduce((n, g) => n + g.items.length, 0)
    return looseCount + packContainersStoredForUnpackRight.value.length
  }
  if (isPackReturnStage(activePackStage.value)) {
    return stageReturnedLooseItems.value.length + packContainersReturnedForReturnRight.value.length
  }
  return stageRightItems.value.length
})

/** Nur-in-Behältern-Hinweis: noch Lagerbestand, aber keine Zeile mehr oben (alles in Kisten). */
const packedIssueWarehouseOnlyInContainers = computed(() => {
  if (!isPackForwardToEventStage(activePackStage.value)) return false
  return packItems.value.some((p) => getStageLeftQty(p) > 0 && looseQtyForPackItem(p) <= 0)
})
const stageRightItems = computed(() => packItems.value.filter((p) => getStageRightQty(p) > 0))

const rightPanelHasEventContent = computed(() => {
  if (isPackUnpackStage(activePackStage.value)) {
    return (
      packContainersStoredForUnpackRight.value.length > 0 || groupsStoredLoose.value.length > 0
    )
  }
  if (isPackReturnStage(activePackStage.value)) {
    return (
      packContainersReturnedForReturnRight.value.length > 0 ||
      groupsReturned.value.length > 0 ||
      groupsNotTakenForReturn.value.length > 0 ||
      groupsConsumableOverview.value.length > 0
    )
  }
  if (!showPackContainersUi.value) {
    return stageRightItems.value.length > 0
  }
  if (showRightProgressMirrorSection.value) {
    return true
  }
  if (isPackReturnPipelineStage(activePackStage.value) && rightLoseSectionHasItems.value) {
    return true
  }
  return rightLoseSectionHasItems.value || packContainers.value.length > 0
})

const stageProgress = computed(() => stageProgressPercentForPackStage(activePackStage.value))

function stageProgressPercentForPackStage(stage: PackStage): number {
  const profile = packWorkflowProfile.value
  let total = 0
  let done = 0
  for (const p of packItems.value) {
    let itemTotal = computeStageTotalQty(p, stage, profile)
    if (isPackUnpackStage(stage)) {
      itemTotal = retourAccountingForUnpackLoose(p).retourTotal
    } else if (isPackReturnStage(stage) && p.isConsumable) {
      const booked = consumableTotalBookedQty(p.materialItemId)
      itemTotal = Math.max(
        consumableReturnDoneQty(p),
        Math.min(itemTotal, booked > 0 ? booked : itemTotal),
      )
    } else if (isPackReturnStage(stage)) {
      itemTotal += returnProgressNotTakenQty(p)
    } else if (isPackForwardToEventStage(stage)) {
      const gap = crateCheckGapForMaterial(p.materialItemId)
      itemTotal = Math.max(getStageRightQtyForStage(p, stage), itemTotal - gap)
    }

    let itemDone: number
    if (isPackReturnStage(stage) && p.isConsumable) {
      itemDone = consumableReturnDoneQty(p)
    } else if (isPackReturnStage(stage)) {
      itemDone = getStageRightQtyForStage(p, stage) + returnProgressNotTakenQty(p)
    } else if (stage === 'confirmed_packed') {
      const leftRaw = getStageLeftQtyForStage(p, stage)
      const shells = packContainerBatchCountByMaterialItemId.value[p.materialItemId] ?? 0
      const virtualPacked = Math.min(Math.max(0, shells), leftRaw)
      itemDone = getStageRightQtyForStage(p, stage) + virtualPacked
    } else {
      itemDone = getStageRightQtyForStage(p, stage)
    }

    total += itemTotal
    done += itemDone
  }
  return total > 0 ? Math.round((done / total) * 100) : 0
}

const jsWorkflowSummary = computed(() => {
  const js = packItems.value.filter((i) => i.isJsMaterial)
  return {
    items: js.length,
    received: js.reduce((s, i) => s + (i.quantityIssued || 0), 0),
    returned: js.reduce((s, i) => s + (i.quantityReturned || 0), 0),
  }
})

const packWorkflowTransitionContext = computed(() => {
  const resolved = resolvePackWorkflowTransitionStage(
    activePackStage.value,
    isActiveStatusPackStage.value,
    packStageKeys.value,
    props.status,
    packWorkflowProfile.value,
  )
  if (!resolved) return null
  const tr =
    props.transitions.find((t) => t.status === resolved.targetStatus && t.allowed) ??
    props.transitions.find((t) => t.status === resolved.targetStatus)
  if (!tr) return null
  return { transition: tr, confirmStage: resolved.confirmStage }
})

const nextWorkflowTransition = computed(() => packWorkflowTransitionContext.value?.transition ?? null)

const packWorkflowConfirmStage = computed(
  () => packWorkflowTransitionContext.value?.confirmStage ?? activePackStage.value,
)

const workflowButtonStageProgress = computed(() =>
  stageProgressPercentForPackStage(packWorkflowConfirmStage.value),
)

const nextPackStageKey = computed((): PackStage | null => {
  const keys = packStageKeys.value
  const idx = keys.indexOf(activePackStage.value)
  if (idx < 0 || idx >= keys.length - 1) return null
  return keys[idx + 1] ?? null
})

/** Tab «Am Event → Transport (zurück)»: weiter zur Retour-Buchung (kein Status «Retour» hier). */
const showContinueAfterTransportBackButton = computed(
  () =>
    props.packListEditable &&
    isActiveStatusPackStage.value &&
    props.status === 'at_event' &&
    packWorkflowProfile.value === 'logistics' &&
    activePackStage.value === 'at_event_transport_back' &&
    nextPackStageKey.value === 'transport_back_returned',
)

const continueAfterTransportBackLabel = computed(() =>
  t('activities.packList.continueAfterTransportBack', {
    stage: activeStageConfig.value.rightLabel,
  }),
)

const continueAfterTransportBackTitle = computed(() =>
  t('activities.packList.continueAfterTransportBackTitle', {
    stage: activeStageConfig.value.rightLabel,
    nextLeft: nextPackStageKey.value
      ? t(`activities.packList.stages.${nextPackStageKey.value}.left`)
      : '',
    nextRight: nextPackStageKey.value
      ? t(`activities.packList.stages.${nextPackStageKey.value}.right`)
      : '',
  }),
)

function onContinueAfterTransportBackClick() {
  const next = nextPackStageKey.value
  if (!next) return
  setStage(next)
}

const nextWorkflowTransitionLabel = computed(() => {
  const tr = nextWorkflowTransition.value
  if (!tr) return ''
  return activityTransitionActionLabel(tr.status, props.status, t, te, tr.label)
})

const previousWorkflowTransition = computed(() => {
  if (!canManageMaterials.value) return null
  const target = activityStatusRevertTarget(props.status, packWorkflowProfile.value)
  if (!target) return null
  return props.transitions.find((t) => t.status === target && t.allowed) ?? null
})

const previousWorkflowTransitionLabel = computed(() => {
  const tr = previousWorkflowTransition.value
  if (!tr) return ''
  return activityTransitionActionLabel(tr.status, props.status, t, te, tr.label)
})

const showWorkflowRevertButton = computed(() => {
  if (!canManageMaterials.value || mwGroupHandoffActive.value) return false
  const tr = previousWorkflowTransition.value
  if (!tr) return false
  if (props.status === 'returned' && tr.status === 'at_event') {
    return isPackWorkflowRevertFromReturnedStage(activePackStage.value, packWorkflowProfile.value)
  }
  return true
})

const workflowRevertVisibleLabel = computed(() => {
  const label = previousWorkflowTransitionLabel.value
  return label ? `← ${label}` : ''
})

const moveAllStageButtonLabel = computed(() =>
  showMwGroupHandoffBanner.value
    ? t('activities.packList.moveAllMw', { stage: activeStageConfig.value.rightLabel })
    : t('activities.packList.moveAll', { stage: activeStageConfig.value.rightLabel }),
)

const moveAllToEventQuickLabel = computed(() =>
  showMwHandoffBanner.value
    ? t('activities.packList.moveAllToEventMw')
    : t('activities.packList.moveAllToEvent'),
)

const partialTakenToEventLabel = computed(() =>
  showMwHandoffBanner.value
    ? t('activities.packList.partialTakenToEventMw')
    : t('activities.packList.partialTakenToEvent'),
)

async function confirmWorkflowRevert(transition: ActivityTransitionRow): Promise<boolean> {
  if (transition.status === 'packing') {
    return confirmDialog({
      title: t('activities.packList.workflowRevertToPackingTitle'),
      message: t('activities.packList.workflowRevertToPackingMessage'),
      confirmText: t('activities.packList.workflowRevertToPackingProceed'),
      cancelText: t('activities.common.cancel'),
      variant: 'warning',
    })
  }
  if (transition.status === 'packed') {
    return confirmDialog({
      title: t('activities.packList.workflowRevertToPackedTitle'),
      message: t('activities.packList.workflowRevertToPackedMessage'),
      confirmText: t('activities.packList.workflowRevertToPackedProceed'),
      cancelText: t('activities.common.cancel'),
      variant: 'warning',
    })
  }
  if (transition.status === 'at_event') {
    return confirmDialog({
      title: t('activities.packList.workflowRevertToAtEventTitle'),
      message: t('activities.packList.workflowRevertToAtEventMessage'),
      confirmText: t('activities.packList.workflowRevertToAtEventProceed'),
      cancelText: t('activities.common.cancel'),
      variant: 'warning',
    })
  }
  return true
}

async function onWorkflowRevertClick() {
  const transition = previousWorkflowTransition.value
  if (!transition?.allowed || !props.packListEditable) return
  if (mwGroupHandoffActive.value) {
    toastMwPackListRevertLockedForGroup()
    return
  }
  if (!(await confirmWorkflowRevert(transition))) return
  isTransitioningPackWorkflow.value = true
  try {
    emit('workflowNext', transition)
  } finally {
    isTransitioningPackWorkflow.value = false
  }
}

/** Quick: Gepackt→Am Event kombiniert; Camp/Event: nur Tab «Transport→Am Event» + Status «Am Event». */
const packIssueToEventCombined = computed(
  () =>
    isPackWorkflowStatusToEventStage(packWorkflowConfirmStage.value, packWorkflowProfile.value) &&
    nextWorkflowTransition.value?.status === 'at_event',
)

/** Mindestens ein Stück/Kiste jemals ans Event gebucht (für Retour-Status) */
const hasAnythingEverIssuedAtEvent = computed(() => {
  if (packItems.value.some((p) => (p.quantityIssued ?? 0) > 0)) return true
  return packContainersWithIssuedAtEvent.value.length > 0
})

/** Mindestens ein Stück/Kiste wirklich «Am Event» (lose oder als Kiste), nicht nur gepackt links */
const hasAnythingIssuedAtEvent = computed(() => {
  if (!packIssueToEventCombined.value) return false
  if (packContainersWithIssuedAtEvent.value.length > 0) return true
  return stageRightItemsLooseIssued.value.length > 0
})

/** Teilmenge: schon etwas ans Event, links noch «Gepackt». */
const packIssueToEventHasPartialTake = computed(
  () =>
    packIssueToEventCombined.value &&
    hasAnythingIssuedAtEvent.value &&
    stageLeftHeaderCount.value > 0,
)

/** Schnellbutton: alles von Gepackt → Am Event — leer rechts, oder Rest-Nachbuchung bei Status «Am Event». */
const showMoveAllToEventQuickButton = computed(() => {
  if (!showPackOperateControls.value || stageLeftHeaderCount.value <= 0) return false
  if (allowPastStageForwardForOpenIssue.value) return true
  return packIssueToEventCombined.value && !hasAnythingIssuedAtEvent.value
})

const showPartialTakenToEventUpperButton = computed(
  () =>
    packIssueToEventCombined.value &&
    showPackOperateControls.value &&
    packIssueToEventHasPartialTake.value,
)

/** Status «Am Event» — wenn schon etwas mitgenommen wurde. */
const showPackWorkflowToEventButton = computed(
  () => packIssueToEventCombined.value && hasAnythingIssuedAtEvent.value,
)

const packWorkflowToEventButtonLabel = computed(() =>
  packIssueToEventHasPartialTake.value
    ? partialTakenToEventLabel.value
    : nextWorkflowTransitionLabel.value,
)

function applyActivePackStageAfterLoad(): void {
  const keys = packStageKeys.value
  const statusStage = autoPackStageForProfile(
    packWorkflowProfile.value,
    props.status,
    canManageMaterials.value,
  )
  const prevStatus = lastLoadedActivityStatus.value
  const prevStage = activePackStage.value
  lastLoadedActivityStatus.value = props.status

  const statusChanged = prevStatus !== null && prevStatus !== props.status
  const isFirstLoad = prevStatus === null
  const statusIdx = keys.indexOf(statusStage)
  const activeIdx = keys.indexOf(activePackStage.value)

  if (statusChanged) {
    userPackStageAheadOfStatus.value = null
  }

  const aheadFromUser = userPackStageAheadOfStatus.value
  const aheadFromActive =
    !statusChanged &&
    !isFirstLoad &&
    statusIdx >= 0 &&
    activeIdx > statusIdx &&
    keys.includes(activePackStage.value)
      ? activePackStage.value
      : null
  const aheadStage =
    aheadFromUser && keys.includes(aheadFromUser) ? aheadFromUser : aheadFromActive

  const preserveAhead =
    !statusChanged &&
    !isFirstLoad &&
    aheadStage != null &&
    statusIdx >= 0 &&
    keys.indexOf(aheadStage) > statusIdx

  const snapToStatus =
    isFirstLoad ||
    statusChanged ||
    !keys.includes(prevStage) ||
    activeIdx < 0 ||
    (statusIdx >= 0 && activeIdx < statusIdx && !preserveAhead)

  if (preserveAhead) {
    activePackStage.value = aheadStage
    userPackStageAheadOfStatus.value = aheadStage
  } else if (snapToStatus) {
    activePackStage.value = keys.includes(statusStage) ? statusStage : (keys[0] ?? 'confirmed_packed')
    if (statusIdx < 0 || keys.indexOf(activePackStage.value) <= statusIdx) {
      userPackStageAheadOfStatus.value = null
    }
  }
  if (!keys.includes(activePackStage.value)) {
    activePackStage.value = keys.includes(statusStage) ? statusStage : (keys[0] ?? 'confirmed_packed')
  }
}

function groupPackItems(items: ActivityPackItem[]) {
  void locale.value
  const grouped = groupActivityPackItemsByCategory(items, t('activities.common.categoryOther'))
  return [...grouped].sort((a, b) => a.categoryName.localeCompare(b.categoryName, locale.value))
}

/** Phys.-Kombi-Kiste rechts unter «Gepackt» — im Kisten-Picker, nicht in «Lose» */
const stageRightCrateShellItems = computed(() =>
  stageRightItems.value.filter((p) => isCrateShellPackItem(p, packContainers.value)),
)

function isRightLooseListPackItem(p: ActivityPackItem): boolean {
  return !isCrateShellPackItem(p, packContainers.value)
}

const groupsLeft = computed(() => {
  void locale.value
  return groupPackItems(stageLeftItems.value)
})

/** Lager-Kisten / Pack-Behälter alphabetisch nach Anzeigename */
const packContainersSorted = computed(() =>
  [...packContainers.value].sort((a, b) => a.label.localeCompare(b.label, locale.value)),
)

/** Behälter «am Event» nur wenn die Kiste selbst (Shell) oder Zeilen als Kisteninhalt ausgegeben wurden — nicht bei «lose mitnehmen». */
function containerHasIssuedAtEvent(containerId: string): boolean {
  const sh = shellPackItemForContainer(containerId)
  if (sh != null) {
    return (sh.quantityIssued ?? 0) > 0
  }
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    if ((ci.quantity_issued ?? 0) > 0) return true
  }
  return false
}

/** Kiste am Event: Inhalt reist mit — keine separaten → auf Zeilen. */
function containerContentsTravelWithShellAtEvent(containerId: string): boolean {
  return isPackForwardToEventStage(activePackStage.value) && containerHasIssuedAtEvent(containerId)
}

function packContainerIdForContainerItem(ci: ActivityPackContainerItem): string | null {
  const shellPrefix = 'shell-'
  if (ci.id.startsWith(shellPrefix)) {
    return ci.id.slice(shellPrefix.length)
  }
  for (const c of packContainers.value) {
    if ((containerItemsByContainerId.value[c.id] ?? []).some((row) => row.id === ci.id)) {
      return c.id
    }
  }
  return null
}

function containerLineInCrateQty(ci: ActivityPackContainerItem): number {
  const overlay = crateCheckOverlayForContainerLine(ci)
  if (overlay) return displayQtyInCrateAfterCheck(overlay)
  return ci.quantity_packed ?? 0
}

async function syncContainerContentsWithShellAtEvent(containerId: string) {
  if (!containerContentsTravelWithShellAtEvent(containerId)) return
  let drift = 0
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    if (isNonActionableContainerLine(ci)) continue
    drift += Math.max(0, containerLineInCrateQty(ci) - (ci.quantity_issued ?? 0))
  }
  if (drift < 1) return
  try {
    await issueAllPackContainerItems(props.activityId, containerId)
    const items = await getPackItems(props.activityId)
    packItems.value = items
    initMoveQtyInputs()
    await loadContainersData()
  } catch {
    /* still show UI fix if sync fails */
  }
}

const packContainersWithIssuedAtEvent = computed(() =>
  packContainersSorted.value.filter((c) => containerHasProgressOnRightForStage(c.id)),
)

const rightProgressMirrorPreset = computed(
  () => packMirrorSectionPresetForRight(activePackStage.value) ?? PACK_MIRROR_SECTION_FORWARD_AT_EVENT,
)

const stageRightLooseMirrorItems = computed(() =>
  packItems.value.filter((p) => {
    if (!isRightLooseListPackItem(p)) return false
    if (activePackStage.value === 'at_event_transport_back') {
      return looseTransportBackOnRight(p) > 0
    }
    if (isPackForwardToEventStage(activePackStage.value)) {
      return looseQtyOnRightMirror(p) > 0
    }
    return false
  }),
)

const groupsRightMirrorLoose = computed(() => {
  void locale.value
  return groupPackItems(stageRightLooseMirrorItems.value)
})

const showRightProgressMirrorSection = computed(() => {
  if (isPackForwardToEventStage(activePackStage.value)) {
    return (
      packContainersWithIssuedAtEvent.value.length > 0 || stageRightLooseMirrorItems.value.length > 0
    )
  }
  if (activePackStage.value === 'at_event_transport_back') {
    return (
      packContainersWithIssuedAtEvent.value.length > 0 || stageRightLooseMirrorItems.value.length > 0
    )
  }
  return false
})

/** Phys.-Kombi rechts «Gepackt» ohne Pack-Behälter — nur dann Kisten-Picker oben */
const packCratePickerShellOnlyItems = computed(() =>
  stageRightCrateShellItems.value.filter((pi) => shellPackContainerForItem(pi) == null),
)

/**
 * Kisten-Auswahl nur an einer Stelle:
 * - links unter «Gepackt» (noch am Lager), oder
 * - unten «Bereits ans Event», oder
 * - oben Picker nur wenn weder links noch am Event wählbar
 */
const showPackCrateTargetPickerTop = computed(() => {
  if (!showPackContainersUi.value || activePackStage.value !== 'confirmed_packed') return false
  if (packCratePickerShellOnlyItems.value.length === 0) return false
  if (
    packContainersWithIssuedAtEvent.value.length === 0 &&
    packContainersSortedWarehouseOnlyVisible.value.length === 0
  ) {
    return true
  }
  return packContainersForConfirmedPackedRight.value.length === 0
})

function returnedQtyInContainersForMaterial(materialItemId: string): number {
  let sum = 0
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id === materialItemId) {
        sum += ci.quantity_returned ?? 0
      }
    }
  }
  return sum
}

function returnedLooseQtyForPackItem(pi: ActivityPackItem): number {
  const returned = pi.quantityReturned ?? 0
  if (returned <= 0) return 0
  return Math.max(0, returned - returnedQtyInContainersForMaterial(pi.materialItemId))
}

function pendingStoreInContainersForMaterial(materialItemId: string): number {
  let sum = 0
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id === materialItemId) {
        sum += containerLineRemainingStore(ci)
      }
    }
  }
  return sum
}

function pendingStoreLooseQtyForPackItem(pi: ActivityPackItem): number {
  if (isPackUnpackStage(activePackStage.value)) {
    const acct = retourAccountingForUnpackLoose(pi)
    const totalPending = Math.max(0, acct.retourTotal - (pi.quantityStored ?? 0))
    if (totalPending <= 0) return 0
    return Math.max(0, totalPending - pendingStoreInContainersForMaterial(pi.materialItemId))
  }
  const pending = Math.max(0, (pi.quantityReturned ?? 0) - (pi.quantityStored ?? 0))
  if (pending <= 0) return 0
  return Math.max(0, pending - returnedQtyInContainersForMaterial(pi.materialItemId))
}

function storedQtyInContainersForMaterial(materialItemId: string): number {
  let sum = 0
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id === materialItemId) {
        sum += ci.quantity_stored ?? 0
      }
    }
  }
  return sum
}

/** In Phys.-Kombi eingelagert — dort als Kistenblock, nicht lose. */
function storedQtyInPhysicalComboContainersForMaterial(materialItemId: string): number {
  let sum = 0
  for (const c of packContainers.value) {
    const sh = shellPackItemForContainer(c.id)
    if (!sh || !isPhysicalComboPackItem(sh)) continue
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id === materialItemId) {
        sum += ci.quantity_stored ?? 0
      }
    }
  }
  return sum
}

function storedLooseQtyForPackItem(pi: ActivityPackItem): number {
  const stored = pi.quantityStored ?? 0
  if (stored <= 0) return 0
  if (isPackUnpackStage(activePackStage.value)) {
    // Rakokiste / Event-Kiste: einzeln eingelagert → lose; nur Phys.-Kombi in der Kistenkarte
    const inPhysCombo = storedQtyInPhysicalComboContainersForMaterial(pi.materialItemId)
    return Math.max(0, stored - inPhysCombo)
  }
  const inContainers = returnedQtyInContainersForMaterial(pi.materialItemId)
  const looseReturned = Math.max(0, (pi.quantityReturned ?? 0) - inContainers)
  return Math.min(stored, looseReturned)
}

function packItemForMaterialItemId(materialItemId: string): ActivityPackItem | undefined {
  return packItems.value.find((p) => p.materialItemId === materialItemId)
}

function containerReturnedInnerLines(containerId: string): ActivityPackContainerItem[] {
  return (containerItemsByContainerId.value[containerId] ?? []).filter((ci) => {
    if (isVirtualWarehouseContainerLine(ci)) return false
    if (isPackUnpackStage(activePackStage.value)) {
      return containerLineRemainingStore(ci) > 0 || (ci.quantity_stored ?? 0) > 0
    }
    return (ci.quantity_returned ?? 0) > 0
  })
}

function containerPendingStoreUnits(containerId: string): number {
  if (isPackUnpackStage(activePackStage.value)) {
    let sum = 0
    for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
      if (isVirtualWarehouseContainerLine(ci)) continue
      sum += containerLineRemainingStore(ci)
    }
    return sum + containerShellPendingStoreQty(containerId)
  }
  let sum = 0
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    sum += Math.max(0, (ci.quantity_returned ?? 0) - (ci.quantity_stored ?? 0))
  }
  const shell = shellPackItemForContainer(containerId)
  if (shell) {
    sum += Math.max(0, (shell.quantityReturned ?? 0) - (shell.quantityStored ?? 0))
  }
  return sum
}

function containerShowsReturnedShell(containerId: string): boolean {
  const c = packContainers.value.find((x) => x.id === containerId)
  if (!c?.container_material_item_id) return false
  const sh = shellPackItemForContainer(containerId)
  if (!sh || (sh.quantityReturned ?? 0) < 1) return false
  if (containerReturnedInnerLines(containerId).length > 0) return true
  return (containerItemsByContainerId.value[containerId] ?? []).some(
    (ci) => (ci.quantity_packed ?? 0) > 0 && (ci.quantity_returned ?? 0) > 0,
  )
}

function containerReturnedContentUnits(containerId: string): number {
  let sum = 0
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    sum += ci.quantity_returned ?? 0
  }
  const shell = shellPackItemForContainer(containerId)
  if (shell) {
    sum += shell.quantityReturned ?? 0
  }
  return sum
}

function containerHasPackedInnerLines(containerId: string): boolean {
  return (containerItemsByContainerId.value[containerId] ?? []).some((ci) => {
    if (isNonActionableContainerLine(ci)) return false
    return Math.max(ci.quantity_packed ?? 0, ci.quantity_issued ?? 0) > 0
  })
}

/**
 * Rechts «Bereits retourniert» als Kistenkarte: nur wenn die Kiste als Ganzes retourniert wurde.
 * Nur-Inhalt oder nur Behälter lose → Lose-Spalte, nicht die Kistenkarte.
 */
function containerReturnedAsWhole(containerId: string): boolean {
  if (containerReturnableUnits(containerId) > 0) return false
  if (containerReturnedContentUnits(containerId) <= 0) return false

  const sh = shellPackItemForContainer(containerId)
  const shellReturned = (sh?.quantityReturned ?? 0) > 0
  const innerReturned = (containerItemsByContainerId.value[containerId] ?? []).some(
    (ci) => !isVirtualWarehouseContainerLine(ci) && (ci.quantity_returned ?? 0) > 0,
  )

  if (sh && isPhysicalComboPackItem(sh)) {
    return shellReturned
  }

  if (!containerHasPackedInnerLines(containerId)) {
    return false
  }

  return shellReturned && innerReturned
}

function containerStoredContentUnits(containerId: string): number {
  let sum = 0
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    sum += ci.quantity_stored ?? 0
  }
  const shell = shellPackItemForContainer(containerId)
  if (shell) {
    sum += shell.quantityStored ?? 0
  }
  return sum
}

function containerStoredInnerUnits(containerId: string): number {
  let sum = 0
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    if (isVirtualWarehouseContainerLine(ci)) continue
    sum += ci.quantity_stored ?? 0
  }
  return sum
}

/** Anzeige «eingelagert» auf der Kistenkarte — ohne einzeln eingelagerte Rakokiste-Shell. */
function containerStoredDisplayUnits(containerId: string): number {
  const inner = containerStoredInnerUnits(containerId)
  const sh = shellPackItemForContainer(containerId)
  if (sh && isPhysicalComboPackItem(sh)) {
    return inner + (sh.quantityStored ?? 0)
  }
  return inner
}

function isIndividuallyStorableCrateShell(pi: ActivityPackItem): boolean {
  return isCrateShellPackItem(pi, packContainers.value) && !isPhysicalComboPackItem(pi)
}

function storedShellLooseQtyForPackItem(pi: ActivityPackItem): number {
  if (!isIndividuallyStorableCrateShell(pi)) return 0
  return pi.quantityStored ?? 0
}

function containerShowsPendingUnpackShell(containerId: string): boolean {
  return containerShellPendingStoreQty(containerId) > 0
}

function containerShowsStoredShell(containerId: string): boolean {
  const sh = shellPackItemForContainer(containerId)
  if (!sh || (sh.quantityStored ?? 0) <= 0) return false
  // Rakokiste / verknüpfte Kiste: Shell einzeln → lose rechts; nur Phys.-Kombi in der Karte
  return isPhysicalComboPackItem(sh)
}

/** Stufe Retour → Ausgepackt: Kisten mit offenem Einlagern (links). */
const packContainersPendingUnpackLeft = computed(() => {
  if (!isPackUnpackStage(activePackStage.value)) return []
  return packContainersSorted.value.filter((c) => containerPendingStoreUnits(c.id) > 0)
})

/** Stufe Retour → Ausgepackt: nur Phys.-Kombi rechts als zusammengehörige Kiste. */
const packContainersStoredForUnpackRight = computed(() => {
  if (!isPackUnpackStage(activePackStage.value)) return []
  return packContainersSorted.value.filter((c) => {
    const sh = shellPackItemForContainer(c.id)
    if (!sh || !isPhysicalComboPackItem(sh)) return false
    return containerStoredInnerUnits(c.id) > 0 || (sh.quantityStored ?? 0) > 0
  })
})

/** Stufe Am Event → Retour: Kisten mit Retour-Bestand (am Event oder noch im Lager mit Inhalt) */
const packContainersWithReturnableAtEvent = computed(() => {
  if (!isPackReturnPipelineStage(activePackStage.value)) {
    return packContainersWithIssuedAtEvent.value.filter((c) => containerReturnableUnits(c.id) > 0)
  }
  return packContainersSorted.value.filter((c) => containerReturnableUnits(c.id) > 0)
})

/** Links Retour: Kisten mit offenem Bestand (Event→Retour oder Transport zurück→Retour). */
const packContainersAtEventForReturnLeft = computed(() => {
  if (!isPackReturnPipelineStage(activePackStage.value)) return []
  if (activePackStage.value === 'transport_back_returned' && packWorkflowProfile.value === 'logistics') {
    return packContainersSorted.value.filter((c) => containerTransportBackReturnableUnits(c.id) > 0)
  }
  return packContainersSorted.value.filter((c) => containerReturnableUnits(c.id) > 0)
})

/** Rechts Retour: als Ganzes retournierte Kisten unter «Bereits retourniert». */
const packContainersReturnedForReturnRight = computed(() => {
  if (!isPackReturnStage(activePackStage.value)) return []
  return packContainersSorted.value.filter((c) => containerReturnedAsWhole(c.id))
})

const stageReturnNotTakenItems = computed(() =>
  packItems.value.filter((p) => {
    if (!isPackReturnOrUnpackWarehouseStage(activePackStage.value)) return false
    if (isOrphanShellWithoutPackContainer(p)) return false
    if (isCrateShellPackItem(p, packContainers.value)) return false
    if (
      isPackUnpackStage(activePackStage.value) &&
      (pendingStoreLooseQtyForPackItem(p) > 0 || (p.quantityReturned ?? 0) > 0)
    ) {
      return false
    }
    if (notTakenQtyForReturn(p) > 0) return true
    return notTakenToEventQtyForMaterial(p.materialItemId) > 0
  }),
)

const groupsNotTakenForReturn = computed(() => {
  void locale.value
  return groupPackItems(stageReturnNotTakenItems.value)
})

const stageReturnNotTakenCount = computed(() => stageReturnNotTakenItems.value.length)

const stageConsumableOverviewItems = computed(() =>
  packItems.value.filter((p) => {
    if (!isPackReturnOrUnpackWarehouseStage(activePackStage.value)) return false
    if (!isConsumablePackLine(p)) return false
    if (consumableStillOnlyInCrateAtReturn(p)) return false
    if (consumableConsumptionRemaining(p) > 0) return true
    if ((p.quantityReturned ?? 0) > 0 || (p.quantityStored ?? 0) > 0) return true
    if (consumableBookedConsumptionQty(p) > 0 && consumableConsumptionRemaining(p) <= 0) {
      return false
    }
    return consumableBookedConsumptionQty(p) > 0
  }),
)

const groupsConsumableOverview = computed(() => {
  void locale.value
  return groupPackItems(stageConsumableOverviewItems.value)
})

const stageConsumableOverviewCount = computed(() => stageConsumableOverviewItems.value.length)

const stageReturnConsumedItems = computed(() =>
  packItems.value.filter((p) => {
    if (!isPackReturnOrUnpackWarehouseStage(activePackStage.value)) return false
    if (!p.isConsumable) return false
    if (isOrphanShellWithoutPackContainer(p)) return false
    if (isCrateShellPackItem(p, packContainers.value)) return false
    return consumableBookedConsumptionQty(p) > 0
  }),
)

const groupsConsumedForReturn = computed(() => {
  void locale.value
  return groupPackItems(stageReturnConsumedItems.value)
})

const stageReturnConsumedCount = computed(() => stageReturnConsumedItems.value.length)

const leftPanelHasKistenEventReturn = computed(
  () =>
    isPackReturnPipelineStage(activePackStage.value) &&
    packContainersAtEventForReturnLeft.value.length > 0,
)

const leftPanelHasKistenUnpack = computed(
  () => isPackUnpackStage(activePackStage.value) && packContainersPendingUnpackLeft.value.length > 0,
)

/** Links: Kisten solange noch nicht in der rechten Ziel-Stufe dieser Pipeline-Phase. */
function containerStillOnWarehouseForActiveStage(containerId: string): boolean {
  const stage = activePackStage.value
  if (stage === 'packed_transport_to') {
    const sh = shellPackItemForContainer(containerId)
    if (sh && getStageLeftQty(sh) > 0) return true
    for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
      if (containerLineRemainingAtForwardStage(ci) > 0) return true
    }
    return false
  }
  if (stage === 'transport_to_at_event') {
    const sh = shellPackItemForContainer(containerId)
    if (sh && getStageLeftQty(sh) > 0) return true
    for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
      if (containerLineRemainingAtForwardStage(ci) > 0) return true
    }
    return false
  }
  if (stage === 'at_event_transport_back') {
    const sh = shellPackItemForContainer(containerId)
    if (sh) return getStageLeftQty(sh) > 0
    return containerReturnableUnits(containerId) > 0
  }
  if (stage === 'transport_back_returned') {
    return containerTransportBackReturnableUnits(containerId) > 0
  }
  return !containerHasIssuedAtEvent(containerId)
}

const packContainersSortedWarehouseOnly = computed(() =>
  packContainersSorted.value.filter((c) => containerStillOnWarehouseForActiveStage(c.id)),
)

function containerHasPackedContent(containerId: string): boolean {
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    if ((ci.quantity_packed ?? 0) > 0) return true
  }
  const sh = shellPackItemForContainer(containerId)
  return (sh?.quantityPacked ?? 0) > 0
}

/** Rechts «Gepackt»: nur Kisten mit tatsächlich gepackter Shell / Inhalt (nicht leerer Auto-Behälter). */
const packContainersForConfirmedPackedRight = computed(() => {
  if (activePackStage.value !== 'confirmed_packed') return []
  return packContainersSortedWarehouseOnly.value.filter((c) => {
    if (isPackContainerMerged(c)) return false
    return packContainerVisibleOnConfirmedPackedRight(
      c.id,
      shellPackItemForContainer(c.id),
      containerHasPackedContent(c.id),
    )
  })
})

/** @deprecated alias — use stageRightLooseMirrorItems */
const stageRightItemsLooseIssued = stageRightLooseMirrorItems

const groupsAtEventLoose = groupsRightMirrorLoose

const stageReturnedLooseItems = computed(() =>
  packItems.value.filter((p) => {
    if (!isPackReturnStage(activePackStage.value)) return false
    if (isOrphanShellWithoutPackContainer(p)) return false
    if (isCrateShellPackItem(p, packContainers.value)) {
      const shellContainer = packShellContainerForPackItem(p, packContainers.value)
      if (shellContainer && containerReturnedAsWhole(shellContainer.id)) return false
      if (isIndividuallyStorableCrateShell(p)) {
        return (p.quantityReturned ?? 0) > 0
      }
      return false
    }
    return returnedLooseQtyForPackItem(p) > 0
  }),
)

const stageStoredLooseItems = computed(() =>
  packItems.value.filter((p) => {
    if (!isPackUnpackStage(activePackStage.value)) return false
    if (isOrphanShellWithoutPackContainer(p)) return false
    if (storedShellLooseQtyForPackItem(p) > 0) return true
    if (isCrateShellPackItem(p, packContainers.value)) return false
    return storedLooseQtyForPackItem(p) > 0
  }),
)

const groupsReturned = computed(() => {
  void locale.value
  if (!isPackReturnStage(activePackStage.value)) return []
  return groupPackItems(stageReturnedLooseItems.value)
})

const groupsStoredLoose = computed(() => {
  void locale.value
  if (!isPackUnpackStage(activePackStage.value)) return []
  return groupPackItems(stageStoredLooseItems.value)
})

/** «Ohne Behälter»: nur lose Gepackt-Menge, gruppiert nach Kategorie */
const ohneBehaelterGroups = computed(() => {
  void locale.value
  if (!showPackContainersUi.value) return []
  if (activePackStage.value === 'confirmed_packed') {
    const items = stageRightItems.value.filter(
      (p) =>
        isRightLooseListPackItem(p) && getStageRightQty(p) > 0 && qtyInContainersForItem(p) === 0,
    )
    return groupPackItems(items)
  }
  if (isPackForwardToEventStage(activePackStage.value)) {
    const items = packItems.value.filter((p) => {
      if (!isRightLooseListPackItem(p)) return false
      if (activePackStage.value === 'packed_transport_to') {
        return (
          looseQtyOnRightMirror(p) > 0 && transportToQtyInContainersForMaterial(p.materialItemId) === 0
        )
      }
      return looseQtyOnRightMirror(p) > 0 && issuedQtyInContainersForMaterial(p.materialItemId) === 0
    })
    return groupPackItems(items)
  }
  if (activePackStage.value === 'at_event_transport_back') {
    const items = packItems.value.filter(
      (p) =>
        isRightLooseListPackItem(p) &&
        looseTransportBackOnRight(p) > 0 &&
        transportBackQtyInContainersForMaterial(p.materialItemId) === 0,
    )
    return groupPackItems(items)
  }
  return []
})

/** Teilweise lose, teils schon in Behälter gelegt */
const loosePackItemsPartial = computed(() => {
  if (!showPackContainersUi.value) return []
  if (activePackStage.value === 'confirmed_packed') {
    return stageRightItems.value.filter(
      (p) =>
        isRightLooseListPackItem(p) && looseQtyForPackItem(p) > 0 && qtyInContainersForItem(p) > 0,
    )
  }
  if (isPackForwardToEventStage(activePackStage.value)) {
    return packItems.value.filter((p) => {
      if (!isRightLooseListPackItem(p)) return false
      if (activePackStage.value === 'packed_transport_to') {
        return looseQtyOnRightMirror(p) > 0 && transportToQtyInContainersForMaterial(p.materialItemId) > 0
      }
      return looseQtyOnRightMirror(p) > 0 && issuedQtyInContainersForMaterial(p.materialItemId) > 0
    })
  }
  if (activePackStage.value === 'at_event_transport_back') {
    return packItems.value.filter(
      (p) =>
        isRightLooseListPackItem(p) &&
        looseTransportBackOnRight(p) > 0 &&
        transportBackQtyInContainersForMaterial(p.materialItemId) > 0,
    )
  }
  return []
})

const rightLoseSectionHasItems = computed(
  () =>
    ohneBehaelterGroups.value.length > 0 || loosePackItemsPartial.value.length > 0,
)

function ohneCatCollapseKey(categoryName: string): string {
  return `r-ohne-cat-${categoryName}`
}

function toggleGroup(key: string) {
  collapsedGroups.value[key] = !collapsedGroups.value[key]
}

function setStage(key: PackStage) {
  activePackStage.value = key
  const keys = packStageKeys.value
  const statusIdx = keys.indexOf(statusPackStage.value)
  const keyIdx = keys.indexOf(key)
  if (statusIdx >= 0 && keyIdx > statusIdx) {
    userPackStageAheadOfStatus.value = key
  } else if (statusIdx >= 0 && keyIdx <= statusIdx) {
    userPackStageAheadOfStatus.value = null
  }
  initMoveQtyInputs()
}

function getBackendStage(): PackMoveStage {
  return computeBackendStage(activePackStage.value)
}

function clampMoveQtyForPackItem(
  pi: ActivityPackItem,
  rawQty: number,
  direction: 'forward' | 'back',
  mode: 'input' | 'move' = 'move',
): number {
  let qty = Math.floor(Number(rawQty))
  if (!Number.isFinite(qty) || qty < 1) qty = 1
  let max = 0
  if (direction === 'back') {
    max = rightQtyForMoveBack(pi)
  } else if (mode === 'input') {
    max = packForwardMoveControlLimits(pi).inputMax
  } else {
    max = packForwardMoveControlLimits(pi).max
  }
  if (max > 0) return Math.min(qty, max)
  return qty
}

function setMoveQtyForItem(itemId: string, qty: number) {
  const pi = packItems.value.find((p) => p.id === itemId)
  const v = pi
    ? clampMoveQtyForPackItem(pi, qty, 'forward', 'input')
    : Math.max(1, Math.floor(Number(qty)) || 1)
  moveQtyInputs.value = { ...moveQtyInputs.value, [itemId]: v }
}

function setMoveBackQtyForItem(itemId: string, qty: number) {
  const pi = packItems.value.find((p) => p.id === itemId)
  const v = pi ? clampMoveQtyForPackItem(pi, qty, 'back') : Math.max(1, Math.floor(Number(qty)) || 1)
  moveBackQtyInputs.value = { ...moveBackQtyInputs.value, [itemId]: v }
}

function initMoveQtyInputs() {
  for (const item of packItems.value) {
    const leftQty = packIssueForwardMax(item)
    const looseQty =
      activePackStage.value === 'confirmed_packed' ? looseQtyForPackItem(item) : 0
    moveQtyInputs.value[item.id] = Math.max(0, leftQty > 0 ? leftQty : looseQty)
    const rightQty = rightQtyForMoveBack(item)
    moveBackQtyInputs.value[item.id] = Math.max(0, rightQty)
  }
}

function resolveForwardMoveQty(item: ActivityPackItem, qty?: number): number {
  const maxMove = packIssueForwardMax(item)
  if (maxMove < 1) return 0
  if (qty != null && qty > 0) return Math.min(maxMove, Math.floor(qty))
  return resolveCrateAssignQty(item, maxMove)
}

function applyUpdatedItem(updated: ActivityPackItem) {
  const idx = packItems.value.findIndex((p) => p.id === updated.id)
  if (idx !== -1) packItems.value[idx] = updated
  initMoveQtyInputs()
}

async function moveToNextStage(item: ActivityPackItem, qty?: number) {
  if (!(await confirmPackStageForwardAllowed())) return
  if (
    activePackStage.value === 'confirmed_packed' &&
    isCrateShellPackItem(item, packContainers.value) &&
    (item.linkedContainerBatchId ?? '').trim() !== '' &&
    shellPackContainerForItem(item) == null
  ) {
    const containerId = await ensurePackContainerForShellCombo(item.id)
    if (!containerId) return
  }
  const raw = qty ?? moveQtyInputs.value[item.id]
  const moveQty =
    qty != null && qty > 0
      ? clampMoveQtyForPackItem(item, raw, 'forward')
      : resolveForwardMoveQty(item, qty)
  if (moveQty <= 0) return
  moveQtyInputs.value = { ...moveQtyInputs.value, [item.id]: moveQty }
  if (needsShellCratePresenceConfirm(item)) {
    await openShellCrateForwardModal(item, moveQty)
    return
  }
  if (isPackForwardToEventStage(activePackStage.value) && !(await confirmMwHandoffBeforeIssueToEvent())) {
    return
  }
  if (isPackReturnStage(activePackStage.value) && !(await confirmMwHandoffBeforeReturn())) {
    return
  }
  const returnQty = resolveConsumableReturnQty(item, moveQty)
  if (returnQty <= 0) {
    toast.info(t('activities.packList.toastConsumableAllUsedNothingToReturn'))
    return
  }
  if (shouldOpenConsumptionModalOnReturn(item)) {
    beginConsumableReturnForPackItem(item, returnQty)
    return
  }
  await executeMoveToNextStage(item, returnQty)
}

async function executeMoveToNextStage(item: ActivityPackItem, moveQty: number) {
  movingId.value = item.id
  try {
    const updated = await postMovePackItem(props.activityId, item.id, {
      stage: getBackendStage(),
      quantity: moveQty,
    })
    applyUpdatedItem(updated)

    /** Optional: nur bei gewähltem Kisten-Ziel in Behälter einbuchen (kein Auto-Behälter für Phys.-Kombi-Set). */
    if (activePackStage.value === 'confirmed_packed' && moveQty > 0 && hasActiveCrateTarget.value) {
      await loadContainersData()
      const containerId = await resolveContainerIdForActiveTarget()
      if (containerId) {
        const looseAfter = looseQtyForPackItem(updated)
        const intoContainer = Math.min(moveQty, looseAfter)
        if (intoContainer >= 1) {
          if (activePackTarget.value?.kind === 'combo') {
            activePackTarget.value = { kind: 'container', containerId }
          }
          await assignMaterialToContainer(updated, containerId, intoContainer, {
            successMessage: t('activities.packList.toastMoveToContainerDirect'),
          })
        }
      }
    }
    if (isPackForwardToEventStage(activePackStage.value) && isCrateShellPackItem(updated, packContainers.value)) {
      const shellC = packShellContainerForPackItem(updated, packContainers.value)
      if (shellC) await syncContainerContentsWithShellAtEvent(shellC.id)
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastMoveFailed'))
  } finally {
    movingId.value = null
  }
}

async function unstoreLooseFromWarehouse(item: ActivityPackItem, qty?: number) {
  if (!(await confirmPackStageBackwardAllowed())) return
  const raw = qty ?? moveBackQtyInputs.value[item.id] ?? rightQtyForMoveBack(item)
  const moveQty = clampMoveQtyForPackItem(item, raw, 'back')
  if (moveQty <= 0) return
  if (!(await confirmUnpackUnstoreFromWarehouse(moveQty, item.materialName ?? ''))) return
  moveBackQtyInputs.value = { ...moveBackQtyInputs.value, [item.id]: moveQty }
  movingId.value = item.id
  try {
    await executeMoveToPrevStage(item, moveQty)
    toast.success(t('activities.packList.toastUnstoreLineSuccess', { qty: moveQty }))
  } finally {
    movingId.value = null
  }
}

async function moveToPrevStage(item: ActivityPackItem, qty?: number) {
  if (isPackUnpackStage(activePackStage.value) && storedLooseQtyForPackItem(item) > 0) {
    await unstoreLooseFromWarehouse(item, qty)
    return
  }
  if (!(await confirmPackStageBackwardAllowed())) return
  const raw = qty ?? moveBackQtyInputs.value[item.id] ?? rightQtyForMoveBack(item)
  const moveQty = clampMoveQtyForPackItem(item, raw, 'back')
  if (moveQty <= 0) return
  moveBackQtyInputs.value = { ...moveBackQtyInputs.value, [item.id]: moveQty }
  if (isPackConfirmedStage(activePackStage.value) && !(await confirmPackedBackToConfirmed())) {
    return
  }
  if (needsShellCrateBackConfirm(item)) {
    await openShellCrateBackModal(item, moveQty)
    return
  }
  await executeMoveToPrevStage(item, moveQty)
}

function preferredContainerIdForMaterialRestore(materialItemId: string): string | null {
  for (const c of packContainers.value) {
    const lines = containerItemsByContainerId.value[c.id] ?? []
    if (lines.some((ci) => ci.material_item_id === materialItemId && !isNonActionableContainerLine(ci))) {
      return c.id
    }
  }
  return packContainers.value[0]?.id ?? null
}

/** Nach «lose mitnehmen»: Menge wieder in Pack-Kiste einbuchen (Zurück-Pfeil rechts). */
async function restoreContainerPackedAfterLooseUnissue(pi: ActivityPackItem, qty: number) {
  if (!isPackForwardToEventStage(activePackStage.value) || qty < 1) return
  const containerId = preferredContainerIdForMaterialRestore(pi.materialItemId)
  if (!containerId) return
  await assignMaterialToContainer(pi, containerId, qty, { successMessage: null })
}

async function executeMoveToPrevStage(item: ActivityPackItem, moveQty: number) {
  movingId.value = item.id
  try {
    const restoreLooseToContainer =
      isPackForwardToEventStage(activePackStage.value) &&
      !isCrateShellPackItem(item, packContainers.value) &&
      looseIssuedAtEvent(item) > 0
    const updated = await postMoveBackPackItem(props.activityId, item.id, {
      stage: getBackendStage(),
      quantity: moveQty,
    })
    if (restoreLooseToContainer) {
      try {
        await restoreContainerPackedAfterLooseUnissue(item, moveQty)
        await loadContainersData()
      } catch {
        /* Packliste wurde zurückgebucht; Kisten-Zeile optional */
      }
    }
    applyUpdatedItem(updated)
    emit('activityItemsChanged')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastMoveBackFailed'))
  } finally {
    movingId.value = null
  }
}

async function onMoveAllToNextStageClick() {
  if (isPackReturnStage(activePackStage.value)) {
    if (!(await confirmMwHandoffBeforeReturn())) return
  } else if (!(await confirmMwHandoffBeforeIssueToEvent())) {
    return
  }
  await moveAllToNextStage()
}

async function executeMoveAllPackStageForward(): Promise<void> {
  if (isPackForwardToEventStage(activePackStage.value)) {
    const missingCheck = pendingOutboundCrateCheckItems.value.filter((pi) => getStageLeftQty(pi) > 0)
    if (missingCheck.length > 0) {
      toast.error(
        t('activities.packList.toastMoveAllBlockedCrateCheck', {
          count: missingCheck.length,
          names: missingCheck.map((pi) => pi.materialName).join(', '),
        }),
      )
      return
    }
  }
  /** Nur «Transport→Am Event»: Kisten komplett ausgeben — nicht bei «Gepackt→Transport (hin)». */
  if (packIssueToEventCombined.value && packContainers.value.length > 0) {
    for (const c of packContainers.value) {
      await issueAllPackContainerItems(props.activityId, c.id)
    }
  }
  await postMoveAllPackItems(props.activityId, getBackendStage())
  await loadAll()
  emit('activityItemsChanged')
}

async function moveAllToNextStage() {
  if (!(await confirmPackStageForwardAllowed())) return
  moveAllLoading.value = true
  try {
    await executeMoveAllPackStageForward()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastMoveAllFailed'))
  } finally {
    moveAllLoading.value = false
  }
}

function focusPackStageForWorkflowConfirm(): void {
  const stage = packWorkflowConfirmStage.value
  if (stage === activePackStage.value) return
  activePackStage.value = stage
  initMoveQtyInputs()
}

async function confirmAtEventStatusTransition(): Promise<boolean> {
  return confirmWorkflowStatusTransition({
    kind: 'at_event',
    stageProgress: workflowButtonStageProgress.value,
    getPendingMessage: (variant) => stageProgressPendingConfirmMessage(variant),
    hasMinimum: () => hasAnythingIssuedAtEvent.value,
    confirmMwHandoff: confirmMwHandoffWorkflowToEvent,
    t,
    confirmDialog,
    toast,
  })
}

async function confirmReturnStatusTransition(): Promise<boolean> {
  return confirmWorkflowStatusTransition({
    kind: 'returned',
    stageProgress: workflowButtonStageProgress.value,
    getPendingMessage: (variant) => stageProgressPendingConfirmMessage(variant),
    hasMinimum: () => hasAnythingEverIssuedAtEvent.value,
    confirmMwHandoff: confirmMwHandoffWorkflowToReturned,
    t,
    confirmDialog,
    toast,
  })
}

/** Gleiche Prüfungen wie Packlisten-Buttons — auch für Workflow-Button in der Kopfzeile. */
function isWorkflowStatusRevertForMw(transition: ActivityTransitionRow): boolean {
  const s = props.status
  if (s === 'packed' && transition.status === 'packing') return true
  if (s === 'at_event' && transition.status === 'packed') return true
  if (s === 'returned' && transition.status === 'at_event') return true
  return false
}

async function confirmBeforeWorkflowTransition(transition: ActivityTransitionRow): Promise<boolean> {
  if (!transition.allowed) return false
  if (!props.packListEditable) return true

  if (mwGroupHandoffActive.value && isWorkflowStatusRevertForMw(transition)) {
    toastMwPackListRevertLockedForGroup()
    return false
  }

  const revertTarget = activityStatusRevertTarget(props.status, packWorkflowProfile.value)
  if (revertTarget && transition.status === revertTarget) {
    if (props.status === 'returned' && transition.status === 'at_event') {
      if (!isPackWorkflowRevertFromReturnedStage(activePackStage.value, packWorkflowProfile.value)) {
        toast.info(t('activities.packList.toastPackStageViewOnly'))
        return false
      }
    }
    return confirmWorkflowRevert(transition)
  }

  if (transition.status === 'at_event') {
    return confirmAtEventStatusTransition()
  }

  if (transition.status === 'returned') {
    return confirmReturnStatusTransition()
  }

  const target = workflowTargetStatusForStage(
    packWorkflowConfirmStage.value,
    props.status,
    packWorkflowProfile.value,
  )
  if (transition.status !== target) return true

  if (
    showMwHandoffBanner.value &&
    transition.status === 'at_event' &&
    !(await confirmMwHandoffBeforeIssueToEvent())
  ) {
    return false
  }

  if (workflowButtonStageProgress.value < 100) {
    const ok = await confirmDialog({
      title: t('activities.packList.confirmWorkflowTitle', { pct: workflowButtonStageProgress.value }),
      message:
        stageProgressPendingLines.value.length > 0
          ? stageProgressPendingConfirmMessage('transition')
          : t('activities.packList.confirmWorkflowMessage', { count: stageLeftItems.value.length }),
      confirmText: t('activities.common.continue'),
      cancelText: t('activities.common.cancel'),
      variant: 'warning',
    })
    if (!ok) return false
  }

  return true
}

async function onPackWorkflowStatusToEventClick() {
  if (!props.packListEditable) return
  const transition = nextWorkflowTransition.value
  if (!transition?.allowed || transition.status !== 'at_event') return
  focusPackStageForWorkflowConfirm()
  if (!(await confirmAtEventStatusTransition())) return

  isTransitioningPackWorkflow.value = true
  try {
    emit('workflowNext', transition)
  } finally {
    isTransitioningPackWorkflow.value = false
  }
}

async function handleWorkflowTransition() {
  const transition = nextWorkflowTransition.value
  if (!transition || !props.packListEditable) return
  focusPackStageForWorkflowConfirm()
  if (!(await confirmBeforeWorkflowTransition(transition))) return
  emit('workflowNext', transition)
  if (transition.status === 'packed') {
    activePackStage.value = autoPackStageForProfile(
      packWorkflowProfile.value,
      'packed',
      canManageMaterials.value,
    )
    initMoveQtyInputs()
  }
}

async function onInitPackList() {
  initLoading.value = true
  try {
    await postInitPackItems(props.activityId)
    await loadAll()
    toast.success(t('activities.packList.toastPackListCreated'))
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastPackListCreateFailed'))
  } finally {
    initLoading.value = false
  }
}

async function loadAll() {
  loading.value = true
  loadError.value = null
  try {
    const items = await getPackItems(props.activityId)
    packItems.value = items
    const needsIssueReports =
      activityStatusAllowsIssueReports.value ||
      items.some((i) => i.isConsumable) ||
      isPackReturnOrUnpackWarehouseStage(statusPackStage.value)
    if (needsIssueReports) {
      const [issues, orderItems] = await Promise.all([
        getActivityIssues(props.activityId).catch(() => []),
        getActivityItems(props.activityId).catch(() => []),
      ])
      packIssues.value = issues
      activityItemsForAccounting.value = orderItems
    } else {
      packIssues.value = []
      activityItemsForAccounting.value = []
    }
    applyActivePackStageAfterLoad()
    initMoveQtyInputs()
    await loadComboComponentsForShellPackItems()
    await loadContainersData()
    await syncLinkedShellPackContainers()
    await refreshCrateCheckSnapshots()
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    loadError.value = e.response?.data?.error || e.message || t('activities.packList.toastLoadFailed')
    packItems.value = []
  } finally {
    loading.value = false
  }
}

provide(PACK_WAREHOUSE_ISSUE_INJECT_KEY, {
  packListEditable: showPackOperateControls,
  packForwardEditable: showPackForwardControls,
  packBackwardEditable: showPackBackwardControls,
  memberAwaitingMwPack,
  canManageMaterials,
  canReportIssues: showPackIssueActions,
  canReportConsumption: showPackConsumptionActions,
  showKisteMeldungForContainer,
  showPackIssueForContainerLine,
  showPackIssueForShellUnpack,
  packContainerIdForContainerItem,
  activePackStage,
  moveQtyInputs,
  setMoveQtyForItem,
  forwardMoveTitleForItem,
  packContainers,
  activePackTarget,
  selectActiveContainer,
  selectActiveCombo,
  selectActiveLoose,
  toggleActiveLoose,
  toggleActiveCombo,
  stageRightCrateShellItems,
  shellPackItemForContainer,
  movingId,
  containerBulkLoadingId,
  containerMutationLoading,
  containerItemsByContainerId,
  containerPullQtyInputs,
  containerPullInputValue,
  setContainerPullInput,
  containerIssueLineInputs,
  containerIssueLineInputValue,
  setContainerIssueLineInput,
  containerIssueLineLooseTitle,
  containerUnissueLineInputs,
  containerUnissueLineInputValue,
  setContainerUnissueLineInput,
  isPackContainerCollapsed,
  isPackContainerSubsectionCollapsed,
  togglePackContainerCollapsed,
  togglePackContainerSubsection,
  peekSectionsForShellPackItem,
  peekSectionsForShellContainer: peekSectionsForShellContainerCtx,
  crateShellPeekEmptyHint,
  packIssueForwardMax,
  packForwardInputQtyMax,
  packForwardWarnBelowOrdered,
  packForwardMoveControlLimits,
  moveToNextStage,
  showShellCrateCheckButton,
  openShellCrateCheckOnlyModal,
  shellCrateCheckButtonLabel,
  shellForwardSubmitting,
  toggleActiveContainer,
  containerHasIssuedAtEvent,
  containerHasAssignedContents,
  containerItemCount,
  containerUnissueableUnits,
  containerIssueableUnits,
  containerShellTakeMax,
  unissueContainerToPacked,
  issueContainerToEvent,
  issueContainerShellOnlyToEvent,
  isPackMaterialConsumable,
  emitConsumptionForMaterialId,
  emitIssueWizardByMaterialId,
  packContainerItemSections: packContainerItemSectionsForContainer,
  containerLineUnissueableMax,
  containerLineIssueableMax,
  unissueContainerLineToPacked,
  containerIssueLineKey,
  pullFromContainer,
  containerPullKey,
  isVirtualWarehouseContainerLine,
  containerLineRemainingIssue,
  containerLineIssueDisplay,
  containerLinePackRemaining,
  containerLineIssuedDisplayQty,
  containerLineIssuedDisplayPacked,
  containerContentsTravelWithShellAtEvent,
  issueContainerLineToEvent,
  crateRealityBannerForPackItem,
  showCrateTemplateToggle,
  useCrateRealityForPackItem,
  toggleCrateRealityView,
  returnContainerToWarehouse,
  returnContainerLineToWarehouse,
  returnContainerShellToWarehouse,
  containerReturnLineInputValue,
  setContainerReturnLineInput,
  containerShellReturnInputValue,
  setContainerShellReturnInput,
  containerReturnableUnits: (containerId: string) =>
    activePackStage.value === 'transport_back_returned' && packWorkflowProfile.value === 'logistics'
      ? containerTransportBackReturnableUnits(containerId)
      : containerReturnableUnits(containerId),
  containerInnerReturnableUnits: (containerId: string) =>
    activePackStage.value === 'transport_back_returned' && packWorkflowProfile.value === 'logistics'
      ? containerInnerTransportBackReturnableUnits(containerId)
      : containerInnerReturnableUnits(containerId),
  containerShellReturnableUnits,
  containerLineRemainingReturn,
  containerShellStillAtEventQty: (containerId: string) =>
    activePackStage.value === 'transport_back_returned' && packWorkflowProfile.value === 'logistics'
      ? containerShellTransportBackReturnableUnits(containerId)
      : containerShellStillAtEventQty(containerId),
  shellMaterialIdForContainer,
  packItemForMaterialItemId,
  resolveActionableContainerLine,
  containerReturnedInnerLines,
  containerReturnedContentUnits,
  containerPendingStoreUnits,
  containerStoredContentUnits,
  containerStoredDisplayUnits,
  containerShowsReturnedShell,
  containerShowsPendingUnpackShell,
  containerShowsStoredShell,
  containerLineRemainingStore,
  containerShellPendingStoreQty,
  containerStoreLineInputValue,
  setContainerStoreLineInput,
  containerShellStoreInputValue,
  setContainerShellStoreInput,
  storeContainerLineToWarehouse,
  unstoreContainerLineFromWarehouse,
  unstoreContainerShellFromWarehouse,
  unpackLineStoredQty: (containerId: string, ci: ActivityPackContainerItem) =>
    resolveActionableContainerLine(containerId, ci).quantity_stored ?? 0,
  unpackShellStoredQty: (containerId: string) => {
    const sh = shellPackItemForContainer(containerId)
    return sh?.quantityStored ?? 0
  },
  storeContainerShellToWarehouse,
  storePhysicalComboContainerWhole,
  isPhysicalComboContainer,
  expectedContainerLineReturnQty,
  retourAccountingForContainerLine,
  notTakenToEventQtyForMaterial,
  consumedQtyForMaterial,
  confirmDeleteContainer,
  shellCheckPendingForPackItem,
  shellCheckReviewForLine,
  shellCheckPatchLine,
  shellCheckSetLineOk,
  shellCheckHistoryReplenishForKey,
  useConsumableInlineAdjust: () => useConsumableInlineAdjust.value,
  canRequestConsumableNachbuchung: computed(() => props.canRequestConsumableNachbuchung === true),
  showConsumableNachbuchungForMaterial,
  showConsumableConsumptionForMaterial: (materialItemId: string) => {
    const pi = packItemForMaterialItemId(materialItemId)
    return pi ? showConsumableConsumptionForPackItem(pi) : false
  },
  consumableInlineQtyFor,
  setConsumableInlineQty,
  maxInlineConsumptionQtyForMaterial,
  submitConsumableInlineForMaterial,
  emitConsumableNachbuchungForMaterial,
  consumableInlinePostingId,
})

watch(
  () => [props.status, packWorkflowProfile.value, canManageMaterials.value] as const,
  () => {
    applyActivePackStageAfterLoad()
    initMoveQtyInputs()
  },
)

watch(
  () => [props.activityId, props.status] as const,
  () => {
    void loadAll()
  },
  { immediate: true },
)

watch(packCrateCheckUserId, () => {
  void refreshCrateCheckSnapshots()
})

watch(
  () => props.reloadToken ?? 0,
  async (token, prev) => {
    if (token !== prev && token > 0) {
      await loadAll()
      if (returnCrateModalOpen.value) {
        syncReturnCrateModalLines()
      }
      if (pendingMaterialAssignToContainer.value && !props.addingActivityMaterial) {
        await fulfillPendingMaterialAssignToContainer()
      }
      if (pendingConsumableReturn.value) {
        await fulfillPendingConsumableReturn()
      }
    }
  },
)

watch(hasMwLooseCrateAssignmentWork, (work, wasWork) => {
  if (!work || wasWork) return
  /** Nutzer hat Pipeline-Tab bewusst vorgezogen (z. B. «weiter zur Retour») — nicht zurückspringen. */
  if (packStageTabOffset.value > 0) return
  const st = props.status || ''
  if (st === 'at_event' || st === 'returned') {
    const stage = 'confirmed_packed'
    if (packStageKeys.value.includes(stage) && activePackStage.value !== stage) {
      activePackStage.value = stage
      initMoveQtyInputs()
      toast.info(t('activities.packList.mwLooseCrateAssignmentTabSwitchToast'))
    }
    return
  }
  if (!packStageKeys.value.includes('confirmed_packed')) return
  if (activePackStage.value === 'confirmed_packed') return
  activePackStage.value = 'confirmed_packed'
  initMoveQtyInputs()
  toast.info(t('activities.packList.mwLooseCrateAssignmentTabSwitchToast'))
})

watch(
  () => props.consumptionModalReturnWithoutConsumptionToken ?? 0,
  async (token, prev) => {
    if (token !== prev && token > 0 && pendingConsumableReturn.value) {
      await fulfillPendingConsumableReturn()
    }
  },
)

watch(
  () => props.consumptionModalCancelledToken ?? 0,
  (token, prev) => {
    if (token !== prev && token > 0) {
      const hadPendingReturn =
        pendingConsumableReturn.value != null || pendingReturnCrateBatch.value != null
      pendingConsumableReturn.value = null
      pendingReturnCrateBatch.value = null
      if (hadPendingReturn) {
        toast.info(t('activities.packList.toastReturnMoveCancelled'))
      }
    }
  },
)

watch(
  () => props.addingActivityMaterial,
  async (adding, wasAdding) => {
    if (wasAdding && !adding && pendingMaterialAssignToContainer.value) {
      await fulfillPendingMaterialAssignToContainer()
    }
  },
)

useBackgroundPoll({
  intervalMs: PACK_LIST_POLL_MS,
  enabled: true,
  isBusy: isPackListInteractionBusy,
  poll: refreshPackListSilent,
})

watch(
  packContainers,
  (list) => {
    const tgt = activePackTarget.value
    if (tgt?.kind === 'container' && !list.some((c) => c.id === tgt.containerId)) {
      activePackTarget.value = null
      return
    }
    if (tgt?.kind === 'combo') {
      const pi = packItems.value.find((p) => p.id === tgt.packItemId)
      if (!pi || pi.materialType !== 'physical_combo') {
        activePackTarget.value = null
      }
    }
  },
  { deep: true },
)

watch(packItems, (items) => {
  const tgt = activePackTarget.value
  if (tgt?.kind !== 'combo') return
  const pi = items.find((p) => p.id === tgt.packItemId)
  if (!pi || pi.materialType !== 'physical_combo') {
    activePackTarget.value = null
  }
})

defineExpose({
  confirmBeforeWorkflowTransition,
})
</script>

<style scoped src="@/styles/material-detail-view.css"></style>
<style scoped>
@import '@/styles/views/activities/detail-workflow.css';

.activity-pack-readonly-hint {
  margin: 6px 0 0;
  font-size: 14px;
}

.pack-card-issue-quick-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-top: 10px;
}

.pack-container-header-title-block {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.pack-container-header-actions {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
  margin-left: auto;
  padding-right: 8px;
}

.btn-move-all {
  font-size: 12px;
}

.btn-progress-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-progress-warn {
  border-color: #f59e0b;
  color: #b45309;
}

.btn-progress-warn-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 6px;
  background: #fef3c7;
  color: #b45309;
}

.js-workflow-summary {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  font-size: 13px;
  color: #475569;
}

.pack-group-ohne-outer {
  margin-bottom: 6px;
}

.pack-group-ohne-inner {
  margin-top: 6px;
  padding-left: 10px;
  border-left: 2px solid #cbd5e1;
}

.pack-group-sub {
  margin-bottom: 8px;
}

.pack-group-sub:last-child {
  margin-bottom: 0;
}

.pack-group-header-sub {
  font-size: 13px;
  padding: 8px 10px;
}

.pack-card-name-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.pack-card-kiste {
  font-size: 12px;
  line-height: 1.35;
  margin: 0;
  padding-left: 0;
}

.pack-card-storage {
  font-size: 12px;
  line-height: 1.35;
  margin: 0;
}

.pack-card-storage-stack {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1 1 100%;
  min-width: 0;
}

.pack-panel-header--split {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.pack-panel-header-main {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pack-add-container-btn {
  flex-shrink: 0;
}

.pack-panel-header-actions {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.pack-workflow-section {
  margin-top: 10px;
}

.pack-workflow-section--lose,
.pack-workflow-section--at-event,
.pack-workflow-section--at-event-mirror,
.pack-workflow-section--at-event-loose {
  margin-bottom: 4px;
}

.pack-workflow-section-title {
  margin: 0 0 8px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
}

.pack-workflow-section-accordion {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  margin: 0 0 8px;
  padding: 0;
  border: none;
  background: none;
  cursor: pointer;
  text-align: left;
}

.pack-workflow-section-accordion .pack-workflow-section-title {
  margin: 0;
  flex: 1;
}

.pack-workflow-section-badge {
  flex-shrink: 0;
  min-width: 1.25rem;
  padding: 0 6px;
  border-radius: 999px;
  background: #e2e8f0;
  color: #475569;
  font-size: 11px;
  font-weight: 700;
  line-height: 1.4;
  text-align: center;
}

.pack-workflow-section-accordion-body {
  padding-top: 2px;
}

.pack-group-header-static {
  cursor: default;
}

.pack-card-detail-stack {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  min-width: 0;
}

.pack-card-footer {
  display: flex;
  justify-content: center;
  padding-top: 8px;
  margin-top: 6px;
  border-top: 1px dashed #e2e8f0;
}

.pack-card-footer :deep(.pack-card-actions-assign-up) {
  justify-content: center;
}

.pack-containers-section {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #e5e7eb;
}

.pack-containers-heading {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
}

.pack-containers-title {
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  margin: 0;
}

/** Kisten als Kinder unter der Überschrift «Behälter» */
.pack-containers-children {
  margin-left: 2px;
  padding-left: 14px;
  border-left: 2px solid #cbd5e1;
}

.pack-containers-empty-hint {
  margin: 0 0 10px;
  font-size: 12px;
  line-height: 1.45;
  max-width: 36rem;
}

.pack-container-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  margin-bottom: 8px;
  background: #fafafa;
  overflow: hidden;
}

.pack-container-header-row {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  width: 100%;
}

.pack-container-header-main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-width: 0;
  flex-wrap: wrap;
}

.pack-container-header-meta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.pack-container-header-move--inline {
  margin: 0;
  padding: 0;
}

.btn-move-arrow--container-header {
  flex-shrink: 0;
}

.pack-move-input--container {
  width: 3.25rem;
  text-align: center;
  font-size: 13px;
}

.pack-container-chevron-btn {
  flex-shrink: 0;
  width: 2.25rem;
  padding: 10px 0 10px 8px;
  margin: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  color: #64748b;
  align-self: center;
}

.pack-container-chevron-btn:hover {
  color: #334155;
}

.pack-container-select-main {
  flex: 1;
  display: flex;
  align-items: center;
  min-width: 0;
  padding: 10px 8px 10px 4px;
  margin: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  text-align: left;
  font: inherit;
  color: inherit;
}

.pack-container-select-main:hover {
  background: #f1f5f9;
}

.pack-target-loose {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 4px 10px;
  border-radius: 999px;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #475569;
  cursor: pointer;
}

.pack-target-loose:hover {
  border-color: #94a3b8;
}

.pack-target-loose--active {
  border-color: var(--color-primary);
  background: var(--color-primary-muted-bg);
  color: var(--color-primary-dark);
}

.pack-group-ohne-outer--loose-target {
  background: rgba(236, 253, 245, 0.85);
  border: 2px solid #86efac;
  border-radius: 10px;
  box-shadow: 0 0 0 1px rgba(22, 163, 74, 0.15);
}

.pack-container-chevron {
  width: 1.25rem;
  flex-shrink: 0;
  font-size: 12px;
  color: #64748b;
}

.pack-container-name {
  flex: 1;
  font-weight: 600;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pack-container-chip {
  font-size: 12px;
  flex-shrink: 0;
}

.pack-container-inner {
  padding: 0 12px 12px 2.25rem;
  border-top: 1px solid #e5e7eb;
  background: #fff;
}

.pack-container-kiste-meldung-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 10px;
  padding: 8px 12px 8px 2.25rem;
  border-top: 1px solid #e5e7eb;
  background: #fafafa;
}

.pack-container-kiste-meldung-label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  margin-right: 2px;
}

.pack-container-empty {
  margin: 8px 0;
  font-size: 13px;
}

.pack-container-delete {
  margin-top: 8px;
  padding: 0;
  font-size: 12px;
  color: #b91c1c;
  background: none;
  border: none;
  cursor: pointer;
  text-decoration: underline;
}

.pack-container-delete:hover {
  color: #991b1b;
}

.pack-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(15, 23, 42, 0.45);
}

.pack-modal {
  width: 100%;
  max-width: 400px;
  padding: 20px 22px;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
}

.pack-modal-title {
  margin: 0 0 12px;
  font-size: 1.1rem;
}

.pack-modal-material {
  margin: 0 0 8px;
  font-weight: 600;
}

.pack-modal-hint {
  margin: 0 0 14px;
  font-size: 13px;
}

.pack-modal-hint--sm {
  font-size: 11px;
  line-height: 1.45;
}

.pack-modal-loading {
  margin: 0 0 12px;
  font-size: 13px;
}

.pack-modal-empty {
  margin: 0 0 12px;
  font-size: 13px;
  line-height: 1.45;
}

.pack-modal-label {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 14px;
  font-size: 13px;
}

.pack-modal-label span:first-child {
  font-weight: 500;
  color: #475569;
}

.pack-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 8px;
}

.pack-return-stock-hint {
  margin: 0 0 14px;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid #bae6fd;
  background: #f0f9ff;
}

.pack-return-stock-hint-title {
  margin: 0 0 6px;
  font-size: 13px;
  font-weight: 600;
  color: #0c4a6e;
}

.pack-return-stock-hint-body {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
}

.pack-return-stock-hint-link {
  margin-left: 6px;
  color: #2563eb;
  font-weight: 500;
  white-space: nowrap;
}

/* Packliste — ein Layout für alle Aktivitätstypen; Tabs/Stufen kommen vom Profil (quick/external/logistics). */
.activity-pack-list-tab:has(.pack-workflow) .pack-list-header-card {
  padding: 8px 12px;
  margin-bottom: 6px;
}

.activity-pack-list-tab:has(.pack-workflow) .pack-add-material-card {
  padding: 6px 8px 8px;
  margin-bottom: 6px;
}

.activity-pack-list-tab:has(.pack-workflow) .pack-add-material-toggle {
  padding: 4px 6px;
  margin-bottom: 0;
}

.activity-pack-list-tab:has(.pack-workflow) .pack-add-material-toggle-title {
  font-size: 12px;
}

.activity-pack-list-tab:has(.pack-workflow) .pack-add-material-body {
  padding-top: 4px;
}

.activity-pack-list-tab:has(.pack-workflow) .pack-add-material-summary,
.activity-pack-list-tab:has(.pack-workflow) .pack-add-material-hint {
  margin-bottom: 6px;
  font-size: 11px;
}

.activity-pack-list-tab:has(.pack-workflow) .section-title {
  font-size: 0.95rem;
  margin-bottom: 4px;
}

.activity-pack-list-tab:has(.pack-workflow) .activity-tab-header-card .section-title {
  margin-bottom: 0;
}

.pack-workflow {
  gap: 6px;
}

.pack-workflow .pack-stage-tabs {
  padding: 2px;
  gap: 2px;
  border-radius: 6px;
}

.pack-workflow .pack-stage-tab {
  padding: 4px 6px;
  font-size: 10px;
  border-radius: 5px;
}

.pack-workflow .pack-progress-bar {
  margin-bottom: 6px;
  padding: 6px 8px;
}

.pack-workflow .pack-progress-info {
  font-size: 11px;
  margin-bottom: 3px;
}

.pack-workflow .pack-progress-track {
  height: 4px;
}

.pack-workflow .pack-panels {
  gap: 8px;
  min-height: 100px;
}

.pack-workflow .pack-panel {
  border-radius: 6px;
}

.pack-workflow .pack-panel-header {
  padding: 5px 8px;
  font-size: 11px;
}

.pack-workflow .pack-panel-title {
  font-size: 10px;
}

.pack-workflow .pack-panel-count {
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  font-size: 10px;
}

.pack-workflow .pack-panel-empty {
  padding: 10px 8px;
  font-size: 11px;
}

.pack-workflow .pack-group-header {
  padding: 4px 8px;
}

.pack-workflow .pack-group-name {
  font-size: 10px;
}

.pack-workflow .pack-group-header-sub {
  padding: 3px 6px;
  font-size: 11px;
}

.pack-workflow .pack-card {
  padding: 4px 8px;
}

.pack-workflow .pack-card-main {
  gap: 6px;
  align-items: center;
}

.pack-workflow .pack-card-name {
  font-size: 11px;
  line-height: 1.25;
}

.pack-workflow :deep(.pack-card-name-block) {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 2px;
}

.pack-workflow :deep(.pack-card-name) {
  flex: none;
}

.pack-workflow :deep(.pack-card-kiste),
.pack-workflow :deep(.pack-card-storage-stack) {
  flex: none;
  width: 100%;
}

.pack-workflow :deep(.pack-card-storage) {
  flex: none;
  display: block;
}

.pack-workflow .pack-card-detail,
.pack-workflow .pack-card-kiste,
.pack-workflow .pack-card-storage {
  font-size: 10px;
  line-height: 1.25;
  margin: 0;
}

.pack-workflow .pack-card-detail-stack {
  gap: 1px;
}

.pack-workflow .pack-card-detail {
  font-size: 10px;
}

.pack-workflow .pack-combo-badge {
  font-size: 9px;
  padding: 1px 4px;
  margin-left: 2px;
}

.pack-workflow .mat-source-badge {
  font-size: 9px;
  padding: 1px 4px;
}

.pack-workflow .pack-move-input,
.pack-workflow .pack-moveback-input {
  width: 34px;
  height: 22px;
  font-size: 11px;
}

.pack-workflow .btn-move-arrow,
.pack-workflow .btn-moveback-arrow {
  width: 24px;
  height: 22px;
}

.pack-workflow .btn-move-arrow svg,
.pack-workflow .btn-moveback-arrow svg {
  width: 12px;
  height: 12px;
}

.pack-workflow .pack-workflow-section {
  margin-top: 4px;
}

.pack-workflow .pack-workflow-section-title {
  margin: 0 0 2px;
  font-size: 9px;
}

.pack-workflow .pack-containers-section {
  margin-top: 4px;
  padding-top: 4px;
}

.pack-workflow .pack-containers-children {
  padding-left: 8px;
}

.pack-workflow .pack-container-card {
  margin-bottom: 4px;
  border-radius: 6px;
}

.pack-workflow .pack-container-chevron-btn {
  width: 1.5rem;
  padding: 4px 0 4px 4px;
}

.pack-workflow .pack-container-select-main {
  padding: 4px 4px 4px 2px;
}

.pack-workflow .pack-container-name {
  font-size: 11px;
}

.pack-workflow .pack-container-chip {
  font-size: 10px;
}

.pack-workflow .pack-container-inner {
  padding: 0 6px 6px 1.5rem;
}

.pack-workflow .pack-container-line {
  padding: 3px 0;
  font-size: 11px;
  gap: 4px;
}

.pack-workflow .pack-container-subsection-toggle {
  padding: 2px 0;
  font-size: 11px;
}

.pack-workflow .pack-crate-picker-head {
  margin-bottom: 4px;
}

.pack-workflow .pack-crate-picker-title {
  font-size: 10px;
  margin-bottom: 1px;
}

.pack-workflow .pack-crate-picker-hint {
  font-size: 10px;
  line-height: 1.3;
}

.pack-workflow .pack-crate-picker-list {
  gap: 3px;
}

.pack-workflow .pack-target-loose {
  font-size: 9px;
  padding: 2px 6px;
}

.pack-workflow .pack-group-ohne-inner {
  margin-top: 2px;
  padding-left: 6px;
}

.pack-workflow .js-workflow-summary {
  margin: -2px 0 6px;
  padding: 5px 8px;
  font-size: 11px;
  gap: 6px;
}

.pack-workflow .pack-add-container-btn {
  font-size: 11px;
  padding: 2px 8px;
}

.pack-workflow :deep(.pack-combo-crate-inline__name) {
  font-size: 11px;
}

.pack-workflow :deep(.pack-combo-crate-inline__qty),
.pack-workflow :deep(.pack-combo-crate-inline__serial) {
  font-size: 9px;
}

.pack-workflow :deep(.pack-crate-shell-check-line__name) {
  font-size: 11px;
}

.pack-workflow :deep(.pack-crate-shell-check-line__soll),
.pack-workflow :deep(.pack-crate-shell-check-line__serial) {
  font-size: 10px;
}

.pack-workflow :deep(.shell-forward-variance-btn) {
  width: 22px;
  height: 22px;
  font-size: 13px;
}

.pack-workflow :deep(.pack-shell-forward-count-input) {
  width: 3.5ch;
  min-width: 2.75rem;
  max-width: 4rem;
  padding: 1px 3px;
  font-size: 11px;
}

@media (max-width: 768px) {
  .pack-panels {
    grid-template-columns: 1fr;
  }
}
</style>

