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
          <p v-if="!packListEditable && !memberAwaitingMwPack" class="activity-pack-readonly-hint text-muted">
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


      <div class="pack-workflow pack-workflow--compact">
        <div class="pack-stage-tabs">
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

        <div v-if="!memberAwaitingMwPack" class="pack-progress-bar">
          <div class="pack-progress-info">
            <span>{{ t('activities.packList.progressPercent', { pct: stageProgress, stage: activeStageConfig.rightLabel }) }}</span>
            <div class="pack-progress-actions">
              <button
                v-if="
                  packListEditable &&
                  !isPackUnpackStage(activePackStage) &&
                  stageLeftHeaderCount > 0
                "
                type="button"
                class="btn btn-xs btn-outline btn-move-all"
                :disabled="moveAllLoading"
                @click="moveAllToNextStage"
              >
                {{ t('activities.packList.moveAll', { stage: activeStageConfig.rightLabel }) }}
              </button>
              <button
                v-if="nextWorkflowTransition"
                type="button"
                class="btn btn-sm btn-progress-action btn-outline"
                :class="{ 'btn-progress-warn': stageProgress < 100 }"
                :disabled="!packListEditable"
                @click="handleWorkflowTransition"
              >
                {{ nextWorkflowTransition.label }}
                <span v-if="stageProgress < 100" class="btn-progress-warn-badge">{{ stageProgress }}%</span>
              </button>
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

        <div
          v-if="isPackReturnStage(activePackStage)"
          class="pack-return-stock-hint"
          role="note"
        >
          <p class="pack-return-stock-hint-title">{{ t('activities.packList.returnStockTitle') }}</p>
          <p class="pack-return-stock-hint-body text-muted">
            {{ t('activities.packList.returnStockBody') }}
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
            <div class="pack-panel-header">
              <span class="pack-panel-title">{{ activeStageConfig.leftLabel }}</span>
              <span class="pack-panel-count">{{ stageLeftHeaderCount }}</span>
            </div>
            <div
              v-if="stageLeftItems.length === 0 && !leftPanelHasKistenEventReturn"
              class="pack-panel-empty"
            >
              <template v-if="memberAwaitingMwPack">
                {{ t('activities.packList.memberAwaitingPackEmpty') }}
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
                  v-if="showPackContainersUi && pi.materialType === 'physical_combo'"
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
                      side="left"
                      :loose-qty="looseQtyForPackItem(pi)"
                      :qty-in-containers="qtyInContainersForItem(pi)"
                    />
                  </template>
                  <template #info-extra>
                    <PackIssueQuickActions
                      v-if="canReportIssues && isPackReturnStage(activePackStage)"
                      :is-consumable="pi.isConsumable"
                      @consumed="emitConsumptionFromPackItem(pi)"
                      @loss="emitIssueWizard(pi, 'loss')"
                      @repair="emitIssueWizard(pi, 'repair')"
                    />
                  </template>
                  <template v-if="!showCrateAssignUpControls(pi)" #trailing>
                    <button
                      v-if="showAssignToContainerButton(pi)"
                      type="button"
                      class="btn-outline btn-sm pack-assign-btn"
                      :disabled="containerMutationLoading"
                      @click="onAssignButtonClick(pi)"
                    >
                      {{ t('activities.packList.assignToContainer') }}
                    </button>
                    <PackMoveControls
                      v-if="
                        packListEditable &&
                        !isPackUnpackStage(activePackStage) &&
                        (!isPackForwardToEventStage(activePackStage) || packIssueForwardMax(pi) > 0)
                      "
                      direction="forward"
                      :qty="moveQtyInputs[pi.id] ?? 0"
                      :max="packIssueForwardMax(pi)"
                      :disabled="movingId === pi.id"
                      :forward-title="forwardMoveTitleForItem(pi)"
                      @update:qty="setMoveQtyForItem(pi.id, $event)"
                      @move="moveToNextStage(pi)"
                    />
                  </template>
                  <template v-if="showCrateAssignUpControls(pi)" #footer>
                    <div class="pack-card-footer">
                      <PackMoveControls
                        direction="assign-up"
                        :qty="moveQtyInputs[pi.id] ?? crateAssignUpMax(pi)"
                        :max="crateAssignUpMax(pi)"
                        :disabled="movingId === pi.id || containerMutationLoading"
                        :forward-title="assignUpTitleForItem(pi)"
                        @update:qty="setMoveQtyForItem(pi.id, $event)"
                        @move="onCrateAssignUpClick(pi)"
                      />
                    </div>
                  </template>
                </PackMaterialRow>
                </template>
              </div>
            </div>

            <!-- Am Event → Retour: Kisten mit Bestand noch am Event (wie rechts bei «Gepackt → Am Event») -->
            <div
              v-if="isPackReturnStage(activePackStage) && packContainersWithReturnableAtEvent.length > 0"
              class="pack-workflow-section pack-workflow-section--kisten pack-workflow-section--event-return-kisten"
            >
              <div class="pack-workflow-section-title">{{ t('activities.packList.sectionKisten') }}</div>
              <div class="pack-containers-section">
                <div class="pack-containers-heading">
                  <span class="pack-containers-title text-muted">{{ t('activities.packList.sectionContainers') }}</span>
                </div>
                <div class="pack-containers-children" role="group" :aria-label="t('activities.packList.ariaContainersAtEvent')">
                  <PackEventReturnContainerCard
                    v-for="c in packContainersWithReturnableAtEvent"
                    :key="'ev-ret-' + c.id"
                    :container="c"
                  />
                </div>
              </div>
            </div>

            <div
              v-if="
                showPackContainersUi &&
                isPackForwardToEventStage(activePackStage) &&
                (packContainers.length > 0 || canManageMaterials)
              "
              class="pack-workflow-section pack-workflow-section--kisten"
            >
              <div class="pack-workflow-section-title">{{ t('activities.packList.sectionKisten') }}</div>
              <div class="pack-containers-section">
              <div class="pack-containers-heading">
                <span class="pack-containers-title text-muted">{{ t('activities.packList.sectionContainers') }}</span>
              </div>
              <div class="pack-containers-children" role="group" :aria-label="t('activities.packList.ariaContainersThisList')">
                <p v-if="packContainers.length === 0" class="pack-containers-empty-hint text-muted">
                  {{ t('activities.packList.hintNoContainersIssue') }}
                </p>
                <p
                  v-else-if="packContainersSortedWarehouseOnlyVisible.length === 0"
                  class="pack-containers-empty-hint text-muted"
                >
                  {{ t('activities.packList.hintContainersOnRight', { stage: activeStageConfig.rightLabel }) }}
                </p>
                <PackWarehouseIssueContainerCard
                  v-for="c in packContainersSortedWarehouseOnlyVisible"
                  :key="'issue-' + c.id"
                  :container="c"
                  :stage-right-label="activeStageConfig.rightLabel"
                  :show-storage-location="showPackStorageLocation(activePackStage, 'left')"
                />
              </div>
              </div>
            </div>
          </div>

          <div class="pack-panel pack-panel-right">
            <div class="pack-panel-header pack-panel-header-done pack-panel-header--split">
              <div class="pack-panel-header-main">
                <span class="pack-panel-title">{{ activeStageConfig.rightLabel }}</span>
                <span class="pack-panel-count">{{ stageRightItems.length }}</span>
              </div>
              <div v-if="showPackContainersUi && packListEditable && activePackStage === 'confirmed_packed'" class="pack-panel-header-actions">
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
            <PackCrateTargetPicker
              v-if="showPackContainersUi && activePackStage === 'confirmed_packed'"
            />

            <div v-if="!rightPanelHasEventContent" class="pack-panel-empty">
              {{
                memberAwaitingMwPack
                  ? t('activities.packList.memberAwaitingPackEmpty')
                  : t('activities.packList.rightPanelEmpty')
              }}
            </div>

            <div
              v-if="isPackReturnStage(activePackStage) && groupsReturned.length > 0"
              class="pack-workflow-section pack-workflow-section--returned"
            >
              <div class="pack-workflow-section-title">{{ t('activities.packList.sectionReturned') }}</div>
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
                        v-if="packListEditable && !isPackUnpackStage(activePackStage)"
                        direction="back"
                        :qty="moveBackQtyInputs[pi.id] ?? 0"
                        :max="rightQtyForMoveBack(pi)"
                        :disabled="movingId === pi.id"
                        :back-title="t('activities.common.backTitle')"
                        @update:qty="setMoveBackQtyForItem(pi.id, $event)"
                        @move="moveToPrevStage(pi)"
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
                    <template #info-extra>
                      <PackIssueQuickActions
                        v-if="canReportIssues"
                        :is-consumable="pi.isConsumable"
                        @consumed="emitConsumptionFromPackItem(pi)"
                        @loss="emitIssueWizard(pi, 'loss')"
                        @repair="emitIssueWizard(pi, 'repair')"
                      />
                    </template>
                  </PackMaterialRow>
                </div>
              </div>
            </div>

            <div
              v-if="
                isPackForwardToEventStage(activePackStage) &&
                (packContainersWithIssuedAtEvent.length > 0 || stageRightItemsLooseIssued.length > 0)
              "
              class="pack-workflow-section pack-workflow-section--at-event"
            >
              <div class="pack-workflow-section-title">{{ t('activities.packList.sectionAlreadyAtEvent') }}</div>

              <div
                v-if="packContainersWithIssuedAtEvent.length > 0"
                class="pack-workflow-section pack-workflow-section--kisten pack-workflow-section--at-event-mirror"
              >
                <div class="pack-workflow-section-title">{{ t('activities.packList.sectionKisten') }}</div>
                <div class="pack-containers-section">
                  <div class="pack-containers-heading">
                    <span class="pack-containers-title text-muted">{{ t('activities.packList.sectionContainers') }}</span>
                  </div>
                                  <div class="pack-containers-children" role="group" :aria-label="t('activities.packList.ariaContainersAtEventMirror')">
                  <PackWarehouseIssueContainerCard
                    v-for="c in packContainersWithIssuedAtEvent"
                    :key="'at-ev-' + c.id"
                    :container="c"
                    :stage-right-label="activeStageConfig.rightLabel"
                    container-dom-id-prefix="pack-container-at-event-"
                    :use-subsections="false"
                    :show-storage-location="showPackStorageLocation(activePackStage, 'right')"
                  />
                </div>
              </div>
            </div>

              <div v-if="stageRightItemsLooseIssued.length > 0" class="pack-workflow-section pack-workflow-section--at-event-loose">
                <div class="pack-workflow-section-title">{{ t('activities.packList.sectionLoose') }}</div>
                <div v-for="g in groupsAtEventLoose" :key="'evt-loose-g-' + g.categoryName" class="pack-group">
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
                          v-if="packListEditable && !isPackUnpackStage(activePackStage)"
                          direction="back"
                          :qty="moveBackQtyInputs[pi.id] ?? 0"
                          :max="rightQtyForMoveBack(pi)"
                          :disabled="movingId === pi.id"
                          :back-title="t('activities.common.backTitle')"
                          @update:qty="setMoveBackQtyForItem(pi.id, $event)"
                          @move="moveToPrevStage(pi)"
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
                      <template #info-extra>
                        <PackIssueQuickActions
                          v-if="canReportIssues && isPackForwardToEventStage(activePackStage)"
                          :is-consumable="pi.isConsumable"
                          @consumed="emitConsumptionFromPackItem(pi)"
                          @loss="emitIssueWizard(pi, 'loss')"
                          @repair="emitIssueWizard(pi, 'repair')"
                        />
                      </template>
                    </PackMaterialRow>
                  </div>
                </div>
              </div>
            </div>

            <!-- Lose: Ohne Behälter (nach Kategorie), gemischt, nur in Behältern (flach) -->
            <div
              v-if="
                showPackContainersUi &&
                activePackStage === 'confirmed_packed' &&
                rightLoseSectionHasItems
              "
              class="pack-workflow-section pack-workflow-section--lose"
            >
              <div class="pack-workflow-section-title">{{ t('activities.packList.sectionLoose') }}</div>

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
                                                v-if="
                                                  packListEditable &&
                                                  !isPackUnpackStage(activePackStage) &&
                                                  !showCrateAssignUpControls(pi)
                                                "
                                                direction="back"
                                                :qty="moveBackQtyInputs[pi.id] ?? 0"
                                                :max="rightQtyForMoveBack(pi)"
                                                :disabled="movingId === pi.id"
                                                :back-title="t('activities.common.backTitle')"
                                                @update:qty="setMoveBackQtyForItem(pi.id, $event)"
                                                @move="moveToPrevStage(pi)"
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
                                                v-if="canReportIssues && isPackForwardToEventStage(activePackStage)"
                                                :is-consumable="pi.isConsumable"
                                                @consumed="emitConsumptionFromPackItem(pi)"
                                                @loss="emitIssueWizard(pi, 'loss')"
                                                @repair="emitIssueWizard(pi, 'repair')"
                                              />
                                            </template>
                                            <template v-if="showCrateAssignUpControls(pi)" #footer>
                                              <div class="pack-card-footer">
                                                <PackMoveControls
                                                  direction="assign-up"
                                                  :qty="moveQtyInputs[pi.id] ?? crateAssignUpMax(pi)"
                                                  :max="crateAssignUpMax(pi)"
                                                  :disabled="movingId === pi.id || containerMutationLoading"
                                                  :forward-title="assignUpTitleForItem(pi)"
                                                  @update:qty="setMoveQtyForItem(pi.id, $event)"
                                                  @move="onCrateAssignUpClick(pi)"
                                                />
                                              </div>
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
                                            v-if="
                                              packListEditable &&
                                              !isPackUnpackStage(activePackStage) &&
                                              !showCrateAssignUpControls(pi)
                                            "
                                            direction="back"
                                            :qty="moveBackQtyInputs[pi.id] ?? 0"
                                            :max="rightQtyForMoveBack(pi)"
                                            :disabled="movingId === pi.id"
                                            :back-title="t('activities.common.backTitle')"
                                            @update:qty="setMoveBackQtyForItem(pi.id, $event)"
                                            @move="moveToPrevStage(pi)"
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
                                            v-if="canReportIssues && isPackForwardToEventStage(activePackStage)"
                                            :is-consumable="pi.isConsumable"
                                            @consumed="emitConsumptionFromPackItem(pi)"
                                            @loss="emitIssueWizard(pi, 'loss')"
                                            @repair="emitIssueWizard(pi, 'repair')"
                                          />
                                        </template>
                                        <template v-if="showCrateAssignUpControls(pi)" #footer>
                                          <div class="pack-card-footer">
                                            <PackMoveControls
                                              direction="assign-up"
                                              :qty="moveQtyInputs[pi.id] ?? crateAssignUpMax(pi)"
                                              :max="crateAssignUpMax(pi)"
                                              :disabled="movingId === pi.id || containerMutationLoading"
                                              :forward-title="assignUpTitleForItem(pi)"
                                              @update:qty="setMoveQtyForItem(pi.id, $event)"
                                              @move="onCrateAssignUpClick(pi)"
                                            />
                                          </div>
                                        </template>
                  </PackMaterialRow>
                </div>
              </div>
            </div>


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

      <!-- Modal: In Behälter legen -->
      <div
        v-if="assignModalOpen && assignTarget"
        class="pack-modal-backdrop"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pack-modal-assign-title"
        @click.self="assignModalOpen = false"
      >
        <div class="pack-modal" @click.stop>
          <h3 id="pack-modal-assign-title" class="pack-modal-title">{{ t('activities.packList.modalAssignTitle') }}</h3>
          <p class="pack-modal-material">{{ assignTarget.materialName }}</p>
          <p class="pack-modal-hint text-muted">
            {{ t('activities.packList.modalAssignMax', { max: assignMaxQty }) }}
          </p>
          <label class="pack-modal-label">
            <span>{{ t('activities.packList.modalAssignContainerLabel') }}</span>
            <select v-model="assignContainerId" class="form-select">
              <option value="" disabled>{{ t('activities.packList.modalAssignSelectContainer') }}</option>
              <option v-for="c in packContainers" :key="c.id" :value="c.id">{{ c.label }}</option>
            </select>
          </label>
          <p v-if="packContainers.length === 0" class="text-muted pack-modal-hint">
            {{ t('activities.packList.modalAssignNoContainers') }}
          </p>
          <label class="pack-modal-label">
            <span>{{ t('activities.packList.modalQty') }}</span>
            <input
              v-model.number="assignQty"
              type="number"
              class="form-input"
              min="1"
              :max="assignMaxQty"
            />
          </label>
          <div class="pack-modal-actions">
            <button type="button" class="btn-outline btn-sm" @click="assignModalOpen = false">{{ t('activities.common.cancel') }}</button>
            <button
              type="button"
              class="btn-primary btn-sm"
              :disabled="assignQty < 1 || assignQty > assignMaxQty || !assignContainerId"
              @click="submitAssignToContainer"
            >
              {{ t('activities.packList.modalAssignSubmit') }}
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
      :can-report-issues="canReportIssues"
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
  </div>
</template>

<script setup lang="ts">
defineOptions({ name: 'ActivityPackListTab' })
import { computed, nextTick, provide, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { ActivityApiType, ActivityIssueReportRow, ActivityTransitionRow } from '@/api/activities'
import ActivityMaterialAvailabilityLookup from '@/components/activities/ActivityMaterialAvailabilityLookup.vue'
import ActivityTabHeader from '@/components/activities/ActivityTabHeader.vue'
import type { MaterialScopeTab } from '@/components/activities/shared/activityMaterialAvailabilityScope'
import { getActivityHistory, getActivityIssues } from '@/api/activities'
import {
  getPackCrateCheckLooseStock,
  postPackCrateCheck,
  type PackCrateCheckRequest,
} from '@/api/activityPackCrateCheck'
import PackCrateShellBackModal from '@/components/activities/PackCrateShellBackModal.vue'
import PackIssueQuickActions from '@/components/activities/PackIssueQuickActions.vue'
import PackMaterialRow from '@/components/activities/PackMaterialRow.vue'
import PackMaterialRowDetail from '@/components/activities/PackMaterialRowDetail.vue'
import PackMoveControls from '@/components/activities/PackMoveControls.vue'
import {
  type PackStage,
  getBackendStage as computeBackendStage,
  getStageLeftQty as computeStageLeftQty,
  getStageRightQty as computeStageRightQty,
  getStageTotalQty as computeStageTotalQty,
  groupActivityPackItemsByCategory,
  isPackConfirmedStage,
  isPackCrateCheckStage,
  isPackForwardToEventStage,
  isPackReturnStage,
  isPackUnpackStage,
  packStageKeysForProfileAndRole,
  showPackStorageLocation,
  workflowTargetStatusForStage,
} from '@/components/activities/packStageQuantities'
import {
  autoPackStageForProfile,
  packWorkflowProfileForActivityType,
  showPackContainersForProfile,
} from '@/components/activities/packWorkflowProfile'
import PackCrateShellForwardModal from '@/components/activities/PackCrateShellForwardModal.vue'
import PackCrateShellPackItemRow from '@/components/activities/PackCrateShellPackItemRow.vue'
import PackCrateTargetPicker from '@/components/activities/PackCrateTargetPicker.vue'
import PackEventReturnContainerCard from '@/components/activities/PackEventReturnContainerCard.vue'
import PackWarehouseIssueContainerCard from '@/components/activities/PackWarehouseIssueContainerCard.vue'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'
import {
  applyCountedQtyToReview,
  defaultLineReview,
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
  peekSectionsForShellContainer,
} from '@/components/activities/packShellCrateHelpers'
import { isPhysicalComboPackItem } from '@/components/activities/packMaterialDisplay'
import type { ComboComponent } from '@/api/materials'
import { getComboComponents } from '@/api/materials'
import type { RackContentsItem } from '@/api/storageLocations'
import type { PackCrateShellPeekSection } from '@/components/activities/PackCrateShellInlinePanel.vue'
import {
  indexLatestCrateCheckByPackItemId,
  type CrateCheckSnapshot,
} from '@/components/activities/packCrateCheckReality'
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
import { useToast } from '@/composables/useToast'

const { t, locale } = useI18n()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()
const { canManageMaterials } = useDepartmentMemberRole()

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
    packListEditable: boolean
    transitions: ActivityTransitionRow[]
    /** Meldungen (v4.01): Schnellbuttons in Packliste wenn Status issued/returned */
    canReportIssues?: boolean
    /** Parent erhöht nach Verbrauchsbuchung → Packliste neu laden */
    reloadToken?: number
    /** packing/gepackt + can_edit_activity_material: Hinzu-Material in der Packliste (nicht Material-Tab) */
    canAddActivityMaterial?: boolean
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
    canReportIssues: false,
    reloadToken: 0,
    canAddActivityMaterial: false,
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

/** Gruppenmitglied: bis MW «Gepackt» markiert hat, keine irreführende «Gepackt»-Spalte. */
const memberAwaitingMwPack = computed(
  () =>
    !canManageMaterials.value &&
    packWorkflowProfile.value === 'quick' &&
    ['submitted', 'approved', 'packing'].includes(props.status),
)

const emit = defineEmits<{
  workflowNext: [transition: ActivityTransitionRow]
  /** Nach Kistenwahl: Backend legt ActivityItem an — Parent soll Materialliste neu laden */
  activityItemsChanged: []
  openIssueWizard: [payload: { materialItemId: string; issueType: 'loss' | 'repair' }]
  openConsumptionModal: [
    payload: {
      materialItemId: string
      materialName: string
      packSize: number | null
      packUnit: string | null
      linkedContainerLabel?: string | null
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

/** Behälter ohne Lager-Batch, nur für Phys.-Kombi «Einbuchen in» (ohne verknüpfte Kiste) */
const shellComboVirtualContainerByPackItemId = ref<Record<string, string>>({})

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

  const linked = packShellContainerForPackItem(pi, packContainers.value)
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
    if (!batchId) {
      shellComboVirtualContainerByPackItemId.value = {
        ...shellComboVirtualContainerByPackItemId.value,
        [packItemId]: created.id,
      }
    }
    const afterLink = packShellContainerForPackItem(pi, packContainers.value)
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
        await executeMoveToNextStage(pi, moveQty)
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

function emitIssueWizard(pi: ActivityPackItem, issueType: 'loss' | 'repair') {
  if (!props.canReportIssues) return
  emit('openIssueWizard', { materialItemId: pi.materialItemId, issueType })
}

function emitConsumptionFromPackItem(pi: ActivityPackItem) {
  if (!props.canReportIssues) return
  emit('openConsumptionModal', {
    materialItemId: pi.materialItemId,
    materialName: pi.materialName,
    packSize: pi.packSize,
    packUnit: pi.packUnit,
    linkedContainerLabel: pi.linkedContainerLabel,
  })
}

/** Verbrauch zu material_item_id (Behälter/Kistenzeile); optional Anzeigetext aus UI */
function emitConsumptionForMaterialId(
  materialItemId: string,
  hints?: { materialName?: string; linkedContainerLabel?: string | null },
) {
  if (!props.canReportIssues || !materialItemId) return
  const pi = packItems.value.find((p) => p.materialItemId === materialItemId)
  if (pi) {
    emitConsumptionFromPackItem(pi)
    return
  }
  emit('openConsumptionModal', {
    materialItemId,
    materialName: (hints?.materialName && hints.materialName.trim()) || t('activities.common.material'),
    packSize: null,
    packUnit: null,
    linkedContainerLabel: hints?.linkedContainerLabel ?? null,
  })
}

/** Meldung zu einer material_item_id (Behälterzeile / Kisten-Stückliste), auch wenn keine lose Pack-Zeile existiert */
function emitIssueWizardByMaterialId(materialItemId: string, issueType: 'loss' | 'repair') {
  if (!props.canReportIssues || !materialItemId) return
  const pi = packItems.value.find((p) => p.materialItemId === materialItemId)
  if (pi) {
    emitIssueWizard(pi, issueType)
  } else {
    emit('openIssueWizard', { materialItemId, issueType })
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
const movingId = ref<string | null>(null)

const activePackStage = ref<PackStage>('confirmed_packed')

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
const moveQtyInputs = ref<Record<string, number>>({})
const moveBackQtyInputs = ref<Record<string, number>>({})

/** Pack-Kisten (Bestätigt → Gepackt), optional zur lose-Menge */
const packContainers = ref<ActivityPackContainer[]>([])
const containerItemsByContainerId = ref<Record<string, ActivityPackContainerItem[]>>({})
const collapsedPackContainers = ref<Record<string, boolean>>({})
/** true/undefined = Unterabschnitt zu */
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

const assignModalOpen = ref(false)
const assignTarget = ref<ActivityPackItem | null>(null)
const assignContainerId = ref('')
const assignQty = ref(1)

/** Kistencheck vor «Gepackt → Am Event» (Phys.-Kombi-Shell) */
const shellForwardModalOpen = ref(false)
const shellForwardItem = ref<ActivityPackItem | null>(null)
const shellForwardMoveQty = ref(0)
const shellForwardSections = ref<PackCrateShellPeekSection[]>([])
const shellForwardLooseStock = ref<Record<string, number>>({})
const shellForwardStockLoading = ref(false)
const shellForwardSubmitting = ref(false)
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
  | { kind: 'issue_container_line'; containerId: string; containerItemId: string; qty: number }

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

function shellCrateCheckDoneForPackItem(packItemId: string): boolean {
  return Boolean(activityCrateCheckSnapshots.value[packItemId])
}

/** Kistencheck einmal pro Aktivität: bei «Bestätigt → Gepackt» oder «Gepackt → Am Event». */
function needsShellCratePresenceConfirm(pi: ActivityPackItem): boolean {
  const stage = activePackStage.value
  if (!isPackCrateCheckStage(stage)) return false
  if (pi.materialType !== 'physical_combo') return false
  if (shellCrateCheckDoneForPackItem(pi.id)) return false
  return true
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
        quantity: line.quantity,
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

function shellCheckPendingForPackItem(pi: ActivityPackItem): boolean {
  return needsShellCratePresenceConfirm(pi)
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
  const comboComponents = comboComponentsByMaterialId.value[item.materialItemId] ?? []

  shellForwardSections.value = shellForwardSectionsForItem(item)
  shellForwardHistoryReplenishByKey.value = {}
  shellForwardHistoryPrefillHint.value = null
  shellForwardInitialLineReviews.value = null
  shellForwardRepackIssueReviews.value = {}
  shellForwardEmbeddedIssuesByLineKey.value = {}
  shellForwardOrphanIssues.value = []
  shellForwardLooseStock.value = {}
  shellForwardModalOpen.value = true

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
      props.canReportIssues ? getActivityIssues(props.activityId) : Promise.resolve([]),
    ])
    shellForwardLooseStock.value = stock
    const snaps = indexLatestCrateCheckByPackItemId(history)
    // Kein Vorausfüllen aus letztem Check — Mini-Inventur startet leer (grau).
    if (props.canReportIssues && issues.length > 0) {
      shellForwardOrphanIssues.value = issues.filter((r) => !r.resolved)
    }
  } catch {
    shellForwardLooseStock.value = {}
  } finally {
    shellForwardStockLoading.value = false
  }
}

async function onShellForwardSubmit(payload: PackCrateCheckRequest) {
  const item = shellForwardItem.value
  const qty = shellForwardMoveQty.value
  if (!item || qty < 1) return
  shellForwardSubmitting.value = true
  try {
    const res = await postPackCrateCheck(props.activityId, item.id, payload)
    if (!res.ok) {
      toast.error(t('activities.packList.shellForwardCheckFailed'))
      return
    }
    await refreshCrateCheckSnapshots()
    const nextDrafts = { ...shellCheckDraftByPackItemId.value }
    delete nextDrafts[item.id]
    shellCheckDraftByPackItemId.value = nextDrafts
    closeShellForwardModal()
    await executeShellForwardPendingAfterCheck(item)
    if (payload.result === 'ok') {
      toast.success(t('activities.packList.shellForwardCheckOkToast'))
    } else {
      toast.info(
        t('activities.packList.shellForwardIncompleteToast', {
          label: shellForwardLabel.value,
          missing: '—',
          note: '—',
        }),
      )
    }
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(
      e.response?.data?.error || e.message || t('activities.packList.shellForwardCheckFailed'),
    )
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
  return buildShellCrateBackDeviations(activityCrateCheckSnapshots.value[pi.id], t)
})

const shellBackLastCheckDateLabel = computed(() => {
  const pi = shellBackItem.value
  if (!pi) return null
  const snap = activityCrateCheckSnapshots.value[pi.id]
  if (!snap?.created_at) return null
  return new Date(snap.created_at).toLocaleString(locale.value)
})

function isPackContainerCollapsed(containerId: string): boolean {
  return Boolean(collapsedPackContainers.value[containerId])
}

function useCrateRealityForPackItem(packItemId: string): boolean {
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
  )
}

function isPackContainerSubsectionCollapsed(containerId: string, subsectionKey: string): boolean {
  const k = `${containerId}:${subsectionKey}`
  return collapsedPackContainerSubsections.value[k] !== false
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

function showCrateTemplateToggle(pi: ActivityPackItem): boolean {
  return Boolean(activityCrateCheckSnapshots.value[pi.id])
}

function crateRealityBannerForPackItem(pi: ActivityPackItem): string | null {
  if (!showCrateTemplateToggle(pi)) return null
  if (useCrateRealityByPackItemId.value[pi.id] === false) {
    return t('activities.packList.cratePeekTemplateViewBanner')
  }
  return t('activities.packList.cratePeekRealityViewBanner')
}

function toggleCrateRealityView(pi: ActivityPackItem) {
  const cur = useCrateRealityByPackItemId.value[pi.id] !== false
  useCrateRealityByPackItemId.value = { ...useCrateRealityByPackItemId.value, [pi.id]: !cur }
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
    activityCrateCheckSnapshots.value = indexLatestCrateCheckByPackItemId(history)
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
  )
}

/** Behälter & lose/in-Behälter-Aufteilung auch bei «Gepackt → Am Event» (linkes «Gepackt» wie zuvor rechts) */
const showPackContainersUi = computed(() =>
  showPackContainersForProfile(packWorkflowProfile.value, activePackStage.value),
)

function packedQtyBaseForContainerSplit(pi: ActivityPackItem): number {
  if (activePackStage.value === 'confirmed_packed') return getStageRightQty(pi)
  if (isPackForwardToEventStage(activePackStage.value)) return Math.max(0, pi.quantityPacked)
  return 0
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

function looseQtyForPackItem(pi: ActivityPackItem): number {
  if (isPackReturnStage(activePackStage.value)) return getStageRightQty(pi)
  if (activePackStage.value !== 'confirmed_packed' && !isPackForwardToEventStage(activePackStage.value)) {
    return getStageRightQty(pi)
  }
  const total = packedQtyBaseForContainerSplit(pi)
  const assigned = assignedQtyByMaterialId.value[pi.materialItemId] ?? 0
  const physicalLoose = Math.max(0, total - assigned)
  if (isPackForwardToEventStage(activePackStage.value)) {
    return Math.max(0, physicalLoose - looseIssuedAtEvent(pi))
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

const assignMaxQty = computed(() => {
  const t = assignTarget.value
  if (!t) return 0
  return looseQtyForPackItem(t)
})

function containerItemCount(containerId: string): number {
  const c = packContainers.value.find((x) => x.id === containerId)
  if (!c) return (containerItemsByContainerId.value[containerId] ?? []).length
  return packContainerItemSectionsForContainer(c).reduce((n, s) => n + s.lines.length, 0)
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

/** Phys.-Kombi-Pack-Position zur Kisten-Charge — nur Inventar-Typ physical_combo */
function shellPackItemForContainer(containerId: string): ActivityPackItem | undefined {
  const c = packContainers.value.find((x) => x.id === containerId)
  if (!c) return undefined
  const mid = (c.container_material_item_id ?? '').trim()
  if (mid) {
    const byMid = packItems.value.find((p) => p.materialItemId === mid)
    if (byMid && isPhysicalComboPackItem(byMid)) return byMid
  }
  const bid = (c.container_batch_id ?? '').trim()
  if (bid) {
    const byBatch = packItems.value.find((p) => (p.linkedContainerBatchId ?? '').trim() === bid)
    if (byBatch && isPhysicalComboPackItem(byBatch)) return byBatch
  }
  return undefined
}

const hasActiveCrateTarget = computed(() => {
  const tgt = activePackTarget.value
  return tgt?.kind === 'container' || tgt?.kind === 'combo'
})

/** Max. Stück für Pfeil-nach-oben: links «noch bestätigt» oder rechts lose «gepackt» */
function crateAssignUpMax(pi: ActivityPackItem): number {
  const fwd = packIssueForwardMax(pi)
  if (fwd >= 1) return fwd
  if (activePackStage.value === 'confirmed_packed') {
    return looseQtyForPackItem(pi)
  }
  return 0
}

function showCrateAssignUpControls(pi: ActivityPackItem): boolean {
  if (!props.packListEditable) return false
  if (activePackStage.value !== 'confirmed_packed') return false
  if (!hasActiveCrateTarget.value) return false
  if (isPhysicalComboPackItem(pi)) return false
  return crateAssignUpMax(pi) >= 1
}

function showAssignToContainerButton(pi: ActivityPackItem): boolean {
  if (!props.packListEditable) return false
  if (!showPackContainersUi.value) return false
  if (activePackStage.value !== 'confirmed_packed') return false
  if (hasActiveCrateTarget.value) return false
  if (isPhysicalComboPackItem(pi)) return false
  if (packContainers.value.length === 0) return false
  return looseQtyForPackItem(pi) >= 1
}

function assignUpTitleForItem(pi: ActivityPackItem): string {
  const max = crateAssignUpMax(pi)
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

async function onCrateAssignUpClick(pi: ActivityPackItem) {
  const tgt = activePackTarget.value
  if (!tgt || (tgt.kind !== 'container' && tgt.kind !== 'combo')) return

  const maxForward = packIssueForwardMax(pi)
  if (maxForward >= 1) {
    const moveQty = resolveForwardMoveQty(pi)
    if (moveQty < 1) return
    await moveToNextStage(pi, moveQty)
    return
  }

  const maxLoose = looseQtyForPackItem(pi)
  if (maxLoose < 1) return
  const assignQty = resolveCrateAssignQty(pi, maxLoose)
  if (assignQty < 1) return

  if (tgt.kind === 'container') {
    await assignDirectToActiveContainer(pi, tgt.containerId, assignQty)
    return
  }
  const containerId = await ensurePackContainerForShellCombo(tgt.packItemId)
  if (!containerId) return
  activePackTarget.value = { kind: 'container', containerId }
  await assignDirectToActiveContainer(pi, containerId, assignQty)
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

/** «Gepackt → Am Event»: nur lose ausgebene Menge (ohne Behälterbuchungen) */
function looseIssuedAtEvent(pi: ActivityPackItem): number {
  if (!isPackForwardToEventStage(activePackStage.value)) return getStageRightQty(pi)
  return Math.max(0, (pi.quantityIssued ?? 0) - issuedQtyInContainersForMaterial(pi.materialItemId))
}

function rightQtyForMoveBack(pi: ActivityPackItem): number {
  if (isPackForwardToEventStage(activePackStage.value)) {
    if (isCrateShellPackItem(pi, packContainers.value)) {
      return getStageRightQty(pi)
    }
    return looseIssuedAtEvent(pi)
  }
  return getStageRightQty(pi)
}

function containerLineRemainingIssue(ci: ActivityPackContainerItem): number {
  if (isNonActionableContainerLine(ci)) return 0
  const p = ci.quantity_packed ?? 0
  const i = ci.quantity_issued ?? 0
  return Math.max(0, p - i)
}

/** Noch ausgebbar laut Pack-Position (Material gesamt) */
function containerLinePackRemaining(ci: ActivityPackContainerItem): number {
  if (isNonActionableContainerLine(ci)) return 0
  const pi = packItems.value.find((x) => x.materialItemId === ci.material_item_id)
  return pi ? Math.max(0, pi.quantityPacked - pi.quantityIssued) : 0
}

/**
 * Wie Backend issue_all: min(Zeilenrest, Pack-Rest).
 * Drift: Behälter hat quantity_issued == quantity_packed, Packliste aber noch Rest → trotzdem buchbar.
 */
function containerLineIssueableMax(ci: ActivityPackContainerItem): number {
  if (isNonActionableContainerLine(ci)) return 0
  const p = ci.quantity_packed ?? 0
  const lineRem = containerLineRemainingIssue(ci)
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

/** Stück noch nicht «Am Event» (Gepackt → Event): Inhalt + Kisten-Material — konsistent mit issue-all */
function containerIssueableUnits(containerId: string): number {
  let sum = 0
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    sum += containerLineIssueableMax(ci)
  }
  const shell = shellPackItemForContainer(containerId)
  if (shell) {
    sum += Math.max(0, shell.quantityPacked - shell.quantityIssued)
  }
  return sum
}

/** Bereits «Am Event» gebucht, zurück nach Gepackt (min Zeile, Packliste) */
function containerLineUnissueableMax(ci: ActivityPackContainerItem): number {
  if (isNonActionableContainerLine(ci)) return 0
  const issued = ci.quantity_issued ?? 0
  const pi = packItems.value.find((x) => x.materialItemId === ci.material_item_id)
  const packCan = pi ? Math.max(0, pi.quantityIssued - (pi.quantityReturned ?? 0)) : 0
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

/** Stück am Event, noch nicht retour (Event → Retour): Inhalt + Kiste */
function containerReturnableUnits(containerId: string): number {
  let sum = 0
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    sum += containerLineRemainingReturn(ci)
  }
  const shell = shellPackItemForContainer(containerId)
  if (shell) {
    sum += Math.max(0, shell.quantityIssued - shell.quantityReturned)
  }
  return sum
}

function containerLineRemainingReturn(ci: ActivityPackContainerItem): number {
  if (isNonActionableContainerLine(ci)) return 0
  const i = ci.quantity_issued ?? 0
  const r = ci.quantity_returned ?? 0
  return Math.max(0, i - r)
}

/** Stück dieses Materials noch in Behälterzeilen am Event (nicht lose) — für Aufteilung in Stufe Event→Retour */
function containerStillAtEventQtyForMaterial(materialItemId: string): number {
  let sum = 0
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      if (ci.material_item_id === materialItemId) {
        sum += containerLineRemainingReturn(ci)
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
  if (packContainers.value.length === 0) return getStageLeftQty(pi)
  return Math.max(0, getStageLeftQty(pi) - containerStillAtEventQtyForMaterial(pi.materialItemId))
}

async function executeIssueContainerToEvent(containerId: string) {
  containerBulkLoadingId.value = containerId
  try {
    await issueAllPackContainerItems(props.activityId, containerId)
    toast.success(t('activities.packList.toastIssueContainer'))
    await loadAll()
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
  const shell = shellPackItemForContainer(c.id)
  if (shell && needsShellCratePresenceConfirm(shell)) {
    const max = packIssueForwardMax(shell)
    if (max >= 1) {
      await openShellCrateForwardModal(shell, max, { kind: 'issue_container', containerId: c.id })
    }
    return
  }
  await executeIssueContainerToEvent(c.id)
}

async function executeShellForwardPendingAfterCheck(item: ActivityPackItem) {
  const pending = shellForwardPendingAction.value
  const qty = shellForwardMoveQty.value
  if (pending.kind === 'pack_move') {
    await executeMoveToNextStage(item, qty)
    return
  }
  if (pending.kind === 'issue_container') {
    await executeIssueContainerToEvent(pending.containerId)
    return
  }
  if (pending.kind === 'issue_container_line') {
    const ci = (containerItemsByContainerId.value[pending.containerId] ?? []).find(
      (row) => row.id === pending.containerItemId,
    )
    if (ci) {
      await executeIssueContainerLineToEvent(pending.containerId, ci, pending.qty)
    }
  }
}

async function unissueContainerToPacked(c: ActivityPackContainer) {
  if (containerBulkLoadingId.value) return
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
  containerBulkLoadingId.value = c.id
  try {
    await returnAllPackContainerItems(props.activityId, c.id)
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
  const next: Record<string, number> = {}
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      next[containerPullKey(c.id, ci.id)] = ci.quantity_packed
    }
  }
  containerPullQtyInputs.value = next
}

function initContainerIssueLineInputs(): void {
  const next: Record<string, number> = {}
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      const max = containerLineIssueableMax(ci)
      next[containerIssueLineKey(c.id, ci.id)] = max > 0 ? max : 1
    }
  }
  containerIssueLineInputs.value = next
}

function initContainerUnissueLineInputs(): void {
  const next: Record<string, number> = {}
  for (const c of packContainers.value) {
    for (const ci of containerItemsByContainerId.value[c.id] ?? []) {
      const max = containerLineUnissueableMax(ci)
      next[containerIssueLineKey(c.id, ci.id)] = max > 0 ? max : 1
    }
  }
  containerUnissueLineInputs.value = next
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

async function loadContainersData(): Promise<void> {
  try {
    const list = await getActivityPackContainers(props.activityId)
    packContainers.value = [...list].sort((a, b) => a.label.localeCompare(b.label, locale.value))
    const map: Record<string, ActivityPackContainerItem[]> = {}
    await Promise.all(
      packContainers.value.map(async (c) => {
        map[c.id] = await getActivityPackContainerItems(props.activityId, c.id)
      }),
    )
    containerItemsByContainerId.value = map
    await loadWarehouseTemplatesForContainers()
    await loadComboComponentsForShellPackItems()
    initContainerPullQtyInputs()
    initContainerIssueLineInputs()
    initContainerUnissueLineInputs()
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

function onAssignButtonClick(pi: ActivityPackItem) {
  if (!props.packListEditable) return
  const tgt = activePackTarget.value
  if (tgt?.kind === 'loose') {
    toast.info(t('activities.packList.toastAssignLooseActive'))
    return
  }
  if (tgt?.kind === 'container') {
    void assignDirectToActiveContainer(pi, tgt.containerId)
    return
  }
  if (tgt?.kind === 'combo') {
    void assignDirectToActiveCombo(pi, tgt.packItemId)
    return
  }
  openAssignModal(pi)
}

async function assignDirectToActiveCombo(pi: ActivityPackItem, comboPackItemId: string) {
  const containerId = await ensurePackContainerForShellCombo(comboPackItemId)
  if (!containerId) return
  activePackTarget.value = { kind: 'container', containerId }
  await assignDirectToActiveContainer(pi, containerId)
}

function openAssignModal(pi: ActivityPackItem) {
  assignTarget.value = pi
  assignQty.value = Math.max(1, looseQtyForPackItem(pi))
  assignContainerId.value =
    activePackTarget.value?.kind === 'container'
      ? activePackTarget.value.containerId
      : packContainers.value.length === 1
        ? packContainers.value[0].id
        : ''
  assignModalOpen.value = true
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

async function assignDirectToActiveContainer(
  pi: ActivityPackItem,
  containerId: string,
  qty?: number,
) {
  const max = looseQtyForPackItem(pi)
  if (max < 1) return
  const q =
    qty != null && qty > 0 ? Math.min(max, Math.max(1, Math.floor(qty))) : max
  await assignMaterialToContainer(pi, containerId, q, {
    successMessage: t('activities.packList.toastMoveToContainerDirect'),
  })
}

async function submitAssignToContainer() {
  const pi = assignTarget.value
  const cid = assignContainerId.value
  if (!pi || !cid) return
  const q = Math.min(Math.max(1, Math.floor(assignQty.value)), assignMaxQty.value)
  if (q < 1 || q > assignMaxQty.value) return

  await assignMaterialToContainer(pi, cid, q)
  assignModalOpen.value = false
  assignTarget.value = null
}

async function pullFromContainer(containerId: string, ci: ActivityPackContainerItem) {
  if (!props.packListEditable) return
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
    const p = ci.quantity_packed ?? 0
    await updateActivityPackContainerItem(props.activityId, containerId, ci.id, {
      quantity_issued: Math.min(p, (ci.quantity_issued ?? 0) + qty),
    })
    const items = await getPackItems(props.activityId)
    packItems.value = items
    initMoveQtyInputs()
    await loadContainersData()
    emit('activityItemsChanged')
    toast.success(t('activities.packList.toastIssueLineSuccess', { qty }))
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
  if (!props.packListEditable || !isPackForwardToEventStage(activePackStage.value)) return
  if (isNonActionableContainerLine(ci)) return
  const max = containerLineIssueableMax(ci)
  if (max < 1) {
    toast.error(t('activities.packList.toastNothingLeftToIssue'))
    return
  }
  const k = containerIssueLineKey(containerId, ci.id)
  let qty = Math.floor(Number(containerIssueLineInputs.value[k] ?? 0))
  if (!Number.isFinite(qty) || qty < 1) qty = max
  qty = Math.min(qty, max)
  if (qty < 1) return

  const shell = shellPackItemForContainer(containerId)
  if (shell && needsShellCratePresenceConfirm(shell)) {
    const shellMax = packIssueForwardMax(shell)
    if (shellMax >= 1) {
      await openShellCrateForwardModal(shell, shellMax, {
        kind: 'issue_container_line',
        containerId,
        containerItemId: ci.id,
        qty,
      })
    }
    return
  }

  await executeIssueContainerLineToEvent(containerId, ci, qty)
}

async function unissueContainerLineToPacked(containerId: string, ci: ActivityPackContainerItem) {
  if (!props.packListEditable || !isPackForwardToEventStage(activePackStage.value)) return
  if (isNonActionableContainerLine(ci)) return
  const max = containerLineUnissueableMax(ci)
  if (max < 1) {
    toast.error(t('activities.packList.toastNothingToUnissue'))
    return
  }
  const k = containerIssueLineKey(containerId, ci.id)
  let qty = Math.floor(Number(containerUnissueLineInputs.value[k] ?? 0))
  if (!Number.isFinite(qty) || qty < 1) qty = max
  qty = Math.min(qty, max)
  if (qty < 1) return

  const pi = packItems.value.find((p) => p.materialItemId === ci.material_item_id)
  if (!pi) {
    toast.error(t('activities.packList.toastNoPackLine'))
    return
  }

  const ret = ci.quantity_returned ?? 0
  containerMutationLoading.value = true
  try {
    await postMoveBackPackItem(props.activityId, pi.id, { stage: 'at_event', quantity: qty })
    await updateActivityPackContainerItem(props.activityId, containerId, ci.id, {
      quantity_issued: Math.max(ret, (ci.quantity_issued ?? 0) - qty),
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
  containerMutationLoading.value = true
  try {
    await deleteActivityPackContainer(props.activityId, deletedId)
    if (
      activePackTarget.value?.kind === 'container' &&
      activePackTarget.value.containerId === deletedId
    ) {
      activePackTarget.value = null
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

function getStageLeftQty(item: ActivityPackItem): number {
  return computeStageLeftQty(item, activePackStage.value, packWorkflowProfile.value)
}

function getStageRightQty(item: ActivityPackItem): number {
  return computeStageRightQty(item, activePackStage.value, packWorkflowProfile.value)
}

function getStageTotalQty(item: ActivityPackItem): number {
  return computeStageTotalQty(item, activePackStage.value, packWorkflowProfile.value)
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
    return looseQtyStillAtEventForReturn(pi)
  }
  return effectiveStageLeftQty(pi)
}

/** Auf «Gepackt → Am Event»: oben nur lose Restmenge; was nur in Behältern liegt, erscheint unten bei den Behältern. */
/** Anzahl Pack-Behälter mit zugeordneter Lager-Kiste pro Material — diese Einheiten nicht links per Pfeil schieben. */
const packContainerBatchCountByMaterialItemId = computed(() => {
  const m: Record<string, number> = {}
  for (const c of packContainers.value) {
    if (!c.container_batch_id || !c.container_material_item_id) continue
    const id = c.container_material_item_id
    m[id] = (m[id] ?? 0) + 1
  }
  return m
})

/** Sichtbare Restmenge links: Roh-Rest minus Einheiten, die bereits als Kisten-Batch am Behälter hängen. */
function effectiveStageLeftQty(p: ActivityPackItem): number {
  if (activePackStage.value !== 'confirmed_packed') {
    return getStageLeftQty(p)
  }
  const raw = getStageLeftQty(p)
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
    if (effectiveStageLeftQty(p) <= 0) return false
    if (
      isPackForwardToEventStage(activePackStage.value) &&
      showPackContainersUi.value &&
      getStageLeftQty(p) > 0 &&
      looseQtyForPackItem(p) <= 0 &&
      !isCrateShellPackItem(p, packContainers.value)
    ) {
      return false
    }
    if (
      isPackReturnStage(activePackStage.value) &&
      packContainers.value.length > 0 &&
      getStageLeftQty(p) > 0
    ) {
      if (looseQtyStillAtEventForReturn(p) <= 0) return false
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
  if (isPackReturnStage(activePackStage.value) && packContainers.value.length > 0) {
    return stageLeftItems.value.length + packContainersWithReturnableAtEvent.value.length
  }
  return stageLeftItems.value.length
})

/** Nur-in-Behältern-Hinweis: noch Lagerbestand, aber keine Zeile mehr oben (alles in Kisten). */
const packedIssueWarehouseOnlyInContainers = computed(() => {
  if (!isPackForwardToEventStage(activePackStage.value)) return false
  return packItems.value.some((p) => getStageLeftQty(p) > 0 && looseQtyForPackItem(p) <= 0)
})
const stageRightItems = computed(() => packItems.value.filter((p) => getStageRightQty(p) > 0))

const rightPanelHasEventContent = computed(() => {
  if (isPackReturnStage(activePackStage.value)) {
    return groupsReturned.value.length > 0
  }
  if (!showPackContainersUi.value) {
    return stageRightItems.value.length > 0
  }
  if (isPackForwardToEventStage(activePackStage.value)) {
    return (
      packContainersWithIssuedAtEvent.value.length > 0 || stageRightItemsLooseIssued.value.length > 0
    )
  }
  return rightLoseSectionHasItems.value || packContainers.value.length > 0
})

const stageProgress = computed(() => {
  const total = packItems.value.reduce((sum, p) => sum + getStageTotalQty(p), 0)
  const done = packItems.value.reduce((sum, p) => {
    if (activePackStage.value !== 'confirmed_packed') {
      return sum + getStageRightQty(p)
    }
    const leftRaw = getStageLeftQty(p)
    const shells = packContainerBatchCountByMaterialItemId.value[p.materialItemId] ?? 0
    const virtualPacked = Math.min(Math.max(0, shells), leftRaw)
    return sum + getStageRightQty(p) + virtualPacked
  }, 0)
  return total > 0 ? Math.round((done / total) * 100) : 0
})

const jsWorkflowSummary = computed(() => {
  const js = packItems.value.filter((i) => i.isJsMaterial)
  return {
    items: js.length,
    received: js.reduce((s, i) => s + (i.quantityIssued || 0), 0),
    returned: js.reduce((s, i) => s + (i.quantityReturned || 0), 0),
  }
})

const nextWorkflowTransition = computed(() => {
  const target = workflowTargetStatusForStage(activePackStage.value, props.status)
  if (!target) return null
  return props.transitions.find((t) => t.status === target && t.allowed) ?? null
})

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

function containerHasIssuedAtEvent(containerId: string): boolean {
  const sh = shellPackItemForContainer(containerId)
  if (sh != null) {
    if ((sh.quantityIssued ?? 0) > 0) return true
    if (isPackForwardToEventStage(activePackStage.value) && getStageRightQty(sh) > 0) return true
  }
  for (const ci of containerItemsByContainerId.value[containerId] ?? []) {
    if ((ci.quantity_issued ?? 0) > 0) return true
  }
  return false
}

const packContainersWithIssuedAtEvent = computed(() =>
  packContainersSorted.value.filter((c) => containerHasIssuedAtEvent(c.id)),
)

/** Stufe Am Event → Retour: Kisten mit noch retournierbarem Bestand (linke Spalte) */
const packContainersWithReturnableAtEvent = computed(() =>
  packContainersWithIssuedAtEvent.value.filter((c) => containerReturnableUnits(c.id) > 0),
)

const leftPanelHasKistenEventReturn = computed(
  () =>
    isPackReturnStage(activePackStage.value) && packContainersWithReturnableAtEvent.value.length > 0,
)

/** Links: Behälter nur solange noch keine Ausgabe «Am Event» — sonst nur rechts */
const packContainersSortedWarehouseOnly = computed(() =>
  packContainersSorted.value.filter((c) => !containerHasIssuedAtEvent(c.id)),
)

const stageRightItemsLooseIssued = computed(() =>
  packItems.value.filter((p) => getStageRightQty(p) > 0 && looseIssuedAtEvent(p) > 0),
)

const groupsAtEventLoose = computed(() => {
  void locale.value
  return groupPackItems(stageRightItemsLooseIssued.value)
})

const groupsReturned = computed(() => {
  void locale.value
  if (!isPackReturnStage(activePackStage.value)) return []
  return groupPackItems(stageRightItems.value)
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
    const items = packItems.value.filter(
      (p) =>
        looseIssuedAtEvent(p) > 0 && issuedQtyInContainersForMaterial(p.materialItemId) === 0,
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
    return packItems.value.filter(
      (p) =>
        looseIssuedAtEvent(p) > 0 && issuedQtyInContainersForMaterial(p.materialItemId) > 0,
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
  initMoveQtyInputs()
}

function getBackendStage(): PackMoveStage {
  return computeBackendStage(activePackStage.value)
}

function setMoveQtyForItem(itemId: string, qty: number) {
  moveQtyInputs.value = { ...moveQtyInputs.value, [itemId]: qty }
}

function setMoveBackQtyForItem(itemId: string, qty: number) {
  moveBackQtyInputs.value = { ...moveBackQtyInputs.value, [itemId]: qty }
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
  if (!props.packListEditable) return
  const moveQty = resolveForwardMoveQty(item, qty)
  if (moveQty <= 0) return
  if (needsShellCratePresenceConfirm(item)) {
    await openShellCrateForwardModal(item, moveQty)
    return
  }
  await executeMoveToNextStage(item, moveQty)
}

async function executeMoveToNextStage(item: ActivityPackItem, moveQty: number) {
  movingId.value = item.id
  try {
    const updated = await postMovePackItem(props.activityId, item.id, {
      stage: getBackendStage(),
      quantity: moveQty,
    })
    applyUpdatedItem(updated)

    /** Aktives Kisten-Ziel: Menge nicht nur lose in Gepackt, sondern gleich in die Kiste legen */
    if (activePackStage.value === 'confirmed_packed' && moveQty > 0) {
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
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastMoveFailed'))
  } finally {
    movingId.value = null
  }
}

async function moveToPrevStage(item: ActivityPackItem, qty?: number) {
  if (!props.packListEditable) return
  const moveQty = Math.min(
    rightQtyForMoveBack(item),
    qty ?? moveBackQtyInputs.value[item.id] ?? rightQtyForMoveBack(item),
  )
  if (moveQty <= 0) return
  if (needsShellCrateBackConfirm(item)) {
    await openShellCrateBackModal(item, moveQty)
    return
  }
  await executeMoveToPrevStage(item, moveQty)
}

async function executeMoveToPrevStage(item: ActivityPackItem, moveQty: number) {
  movingId.value = item.id
  try {
    const updated = await postMoveBackPackItem(props.activityId, item.id, {
      stage: getBackendStage(),
      quantity: moveQty,
    })
    applyUpdatedItem(updated)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastMoveBackFailed'))
  } finally {
    movingId.value = null
  }
}

async function moveAllToNextStage() {
  if (!props.packListEditable) return
  moveAllLoading.value = true
  try {
    /** Gepackt → Am Event: zuerst alle Behälter (Inhalt + Kiste), sonst würde move-all issued=packed setzen und issue-all nichts mehr buchen. */
    if (isPackForwardToEventStage(activePackStage.value) && packContainers.value.length > 0) {
      for (const c of packContainers.value) {
        await issueAllPackContainerItems(props.activityId, c.id)
      }
    }
    await postMoveAllPackItems(props.activityId, getBackendStage())
    await loadAll()
    emit('activityItemsChanged')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { error?: string } }; message?: string }
    toast.error(e.response?.data?.error || e.message || t('activities.packList.toastMoveAllFailed'))
  } finally {
    moveAllLoading.value = false
  }
}

async function handleWorkflowTransition() {
  const transition = nextWorkflowTransition.value
  if (!transition || !props.packListEditable) return
  if (stageProgress.value < 100) {
    const ok = await confirmDialog({
      title: t('activities.packList.confirmWorkflowTitle', { pct: stageProgress.value }),
      message: t('activities.packList.confirmWorkflowMessage', { count: stageLeftItems.value.length }),
      confirmText: t('activities.common.continue'),
      cancelText: t('activities.common.cancel'),
      variant: 'warning',
    })
    if (!ok) return
  }
  emit('workflowNext', transition)
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
    activePackStage.value = autoPackStageForProfile(
      packWorkflowProfile.value,
      props.status,
      canManageMaterials.value,
    )
    const keys = packStageKeys.value
    if (!keys.includes(activePackStage.value)) {
      activePackStage.value = keys[0] ?? 'confirmed_packed'
    }
    initMoveQtyInputs()
    await loadComboComponentsForShellPackItems()
    await loadContainersData()
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
  packListEditable: computed(() => props.packListEditable),
  canManageMaterials,
  canReportIssues: computed(() => props.canReportIssues),
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
  containerIssueLineInputs,
  containerUnissueLineInputs,
  isPackContainerCollapsed,
  isPackContainerSubsectionCollapsed,
  togglePackContainerCollapsed,
  togglePackContainerSubsection,
  peekSectionsForShellPackItem,
  peekSectionsForShellContainer: peekSectionsForShellContainerCtx,
  crateShellPeekEmptyHint,
  packIssueForwardMax,
  moveToNextStage,
  toggleActiveContainer,
  containerItemCount,
  containerUnissueableUnits,
  containerIssueableUnits,
  unissueContainerToPacked,
  issueContainerToEvent,
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
  containerLinePackRemaining,
  issueContainerLineToEvent,
  crateRealityBannerForPackItem,
  showCrateTemplateToggle,
  useCrateRealityForPackItem,
  toggleCrateRealityView,
  returnContainerToWarehouse,
  containerReturnableUnits,
  containerLineRemainingReturn,
  containerShellStillAtEventQty,
  shellMaterialIdForContainer,
  confirmDeleteContainer,
  shellCheckPendingForPackItem,
  shellCheckReviewForLine,
  shellCheckPatchLine,
  shellCheckSetLineOk,
  shellCheckHistoryReplenishForKey,
})

watch(
  () => [props.activityId, props.status] as const,
  () => {
    void loadAll()
  },
  { immediate: true },
)

watch(
  () => props.reloadToken ?? 0,
  async (token, prev) => {
    if (token !== prev && token > 0) {
      await loadAll()
      if (pendingMaterialAssignToContainer.value && !props.addingActivityMaterial) {
        await fulfillPendingMaterialAssignToContainer()
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

.mat-source-badge {
  display: inline-block;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  background: #e0e7ff;
  color: #4338ca;
}

.pack-card-name-block {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.pack-combo-badge {
  font-size: 10px;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
  background: #ede9fe;
  color: #5b21b6;
  margin-left: 4px;
  vertical-align: middle;
}

.pack-combo-badge--virtual {
  background: #f3e8ff;
  color: #7c3aed;
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

.pack-card-detail-stack {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  min-width: 0;
}

.pack-assign-btn {
  flex-shrink: 0;
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
  border-color: #2563eb;
  background: #eff6ff;
  color: #1d4ed8;
}

.pack-group-ohne-outer--loose-target {
  background: rgba(239, 246, 255, 0.55);
  border-radius: 10px;
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

.pack-container-line {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 8px 0;
  font-size: 13px;
  border-bottom: 1px solid #f1f5f9;
}

.pack-container-line--issue-row {
  align-items: center;
  flex-wrap: wrap;
  justify-content: space-between;
}

.pack-container-line--stacked.pack-container-line--issue-row {
  align-items: flex-start;
}

.pack-container-line--stacked:not(.pack-container-line--issue-row) {
  flex-direction: column;
  align-items: stretch;
  gap: 8px;
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

.pack-container-line-issue-quick {
  flex: 1 1 100%;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  justify-content: flex-end;
  padding-top: 2px;
}

.pack-container-line--stacked:not(.pack-container-line--issue-row) .pack-container-line-issue-quick {
  flex: none;
  justify-content: flex-start;
  padding-top: 0;
}

.pack-container-line:last-of-type {
  border-bottom: none;
}

.pack-container-line-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 8px 12px;
}

.pack-container-line-name {
  flex: 1;
  min-width: 0;
}

.pack-container-line-qty {
  color: #64748b;
  font-variant-numeric: tabular-nums;
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

/* Kompakte Packliste — mehr Inhalt auf dem Bildschirm */
.activity-pack-list-tab:has(.pack-workflow--compact) .pack-list-header-card {
  padding: 8px 12px;
  margin-bottom: 6px;
}

.activity-pack-list-tab:has(.pack-workflow--compact) .pack-add-material-card {
  padding: 6px 8px 8px;
  margin-bottom: 6px;
}

.activity-pack-list-tab:has(.pack-workflow--compact) .pack-add-material-toggle {
  padding: 4px 6px;
  margin-bottom: 0;
}

.activity-pack-list-tab:has(.pack-workflow--compact) .pack-add-material-toggle-title {
  font-size: 12px;
}

.activity-pack-list-tab:has(.pack-workflow--compact) .pack-add-material-body {
  padding-top: 4px;
}

.activity-pack-list-tab:has(.pack-workflow--compact) .pack-add-material-summary,
.activity-pack-list-tab:has(.pack-workflow--compact) .pack-add-material-hint {
  margin-bottom: 6px;
  font-size: 11px;
}

.activity-pack-list-tab:has(.pack-workflow--compact) .section-title {
  font-size: 0.95rem;
  margin-bottom: 4px;
}

.activity-pack-list-tab:has(.pack-workflow--compact) .activity-tab-header-card .section-title {
  margin-bottom: 0;
}

.pack-workflow--compact {
  gap: 6px;
}

.pack-workflow--compact .pack-stage-tabs {
  padding: 2px;
  gap: 2px;
  border-radius: 6px;
}

.pack-workflow--compact .pack-stage-tab {
  padding: 4px 6px;
  font-size: 10px;
  border-radius: 5px;
}

.pack-workflow--compact .pack-progress-bar {
  margin-bottom: 6px;
  padding: 6px 8px;
}

.pack-workflow--compact .pack-progress-info {
  font-size: 11px;
  margin-bottom: 3px;
}

.pack-workflow--compact .pack-progress-track {
  height: 4px;
}

.pack-workflow--compact .pack-panels {
  gap: 8px;
  min-height: 100px;
}

.pack-workflow--compact .pack-panel {
  border-radius: 6px;
}

.pack-workflow--compact .pack-panel-header {
  padding: 5px 8px;
  font-size: 11px;
}

.pack-workflow--compact .pack-panel-title {
  font-size: 10px;
}

.pack-workflow--compact .pack-panel-count {
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  font-size: 10px;
}

.pack-workflow--compact .pack-panel-empty {
  padding: 10px 8px;
  font-size: 11px;
}

.pack-workflow--compact .pack-group-header {
  padding: 4px 8px;
}

.pack-workflow--compact .pack-group-name {
  font-size: 10px;
}

.pack-workflow--compact .pack-group-header-sub {
  padding: 3px 6px;
  font-size: 11px;
}

.pack-workflow--compact .pack-card {
  padding: 4px 8px;
}

.pack-workflow--compact .pack-card-main {
  gap: 6px;
  align-items: center;
}

.pack-workflow--compact .pack-card-name {
  font-size: 11px;
  line-height: 1.25;
}

.pack-workflow--compact :deep(.pack-card-name-block) {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  column-gap: 0.4em;
  row-gap: 0;
  gap: 0;
}

.pack-workflow--compact :deep(.pack-card-name) {
  flex: 1 1 100%;
}

.pack-workflow--compact :deep(.pack-card-kiste),
.pack-workflow--compact :deep(.pack-card-storage) {
  flex: 0 1 auto;
}

.pack-workflow--compact .pack-card-detail,
.pack-workflow--compact .pack-card-kiste,
.pack-workflow--compact .pack-card-storage {
  font-size: 10px;
  line-height: 1.25;
  margin: 0;
}

.pack-workflow--compact .pack-card-detail-stack {
  gap: 1px;
}

.pack-workflow--compact .pack-card-detail {
  font-size: 10px;
}

.pack-workflow--compact .pack-combo-badge {
  font-size: 9px;
  padding: 1px 4px;
  margin-left: 2px;
}

.pack-workflow--compact .mat-source-badge {
  font-size: 9px;
  padding: 1px 4px;
}

.pack-workflow--compact .pack-move-input,
.pack-workflow--compact .pack-moveback-input {
  width: 34px;
  height: 22px;
  font-size: 11px;
}

.pack-workflow--compact .btn-move-arrow,
.pack-workflow--compact .btn-moveback-arrow {
  width: 24px;
  height: 22px;
}

.pack-workflow--compact .btn-move-arrow svg,
.pack-workflow--compact .btn-moveback-arrow svg {
  width: 12px;
  height: 12px;
}

.pack-workflow--compact .pack-workflow-section {
  margin-top: 4px;
}

.pack-workflow--compact .pack-workflow-section-title {
  margin: 0 0 2px;
  font-size: 9px;
}

.pack-workflow--compact .pack-containers-section {
  margin-top: 4px;
  padding-top: 4px;
}

.pack-workflow--compact .pack-containers-children {
  padding-left: 8px;
}

.pack-workflow--compact .pack-container-card {
  margin-bottom: 4px;
  border-radius: 6px;
}

.pack-workflow--compact .pack-container-chevron-btn {
  width: 1.5rem;
  padding: 4px 0 4px 4px;
}

.pack-workflow--compact .pack-container-select-main {
  padding: 4px 4px 4px 2px;
}

.pack-workflow--compact .pack-container-name {
  font-size: 11px;
}

.pack-workflow--compact .pack-container-chip {
  font-size: 10px;
}

.pack-workflow--compact .pack-container-inner {
  padding: 0 6px 6px 1.5rem;
}

.pack-workflow--compact .pack-container-line {
  padding: 3px 0;
  font-size: 11px;
  gap: 4px;
}

.pack-workflow--compact .pack-container-subsection-toggle {
  padding: 2px 0;
  font-size: 11px;
}

.pack-workflow--compact .pack-crate-picker-head {
  margin-bottom: 4px;
}

.pack-workflow--compact .pack-crate-picker-title {
  font-size: 10px;
  margin-bottom: 1px;
}

.pack-workflow--compact .pack-crate-picker-hint {
  font-size: 10px;
  line-height: 1.3;
}

.pack-workflow--compact .pack-crate-picker-list {
  gap: 3px;
}

.pack-workflow--compact .pack-target-loose {
  font-size: 9px;
  padding: 2px 6px;
}

.pack-workflow--compact .pack-group-ohne-inner {
  margin-top: 2px;
  padding-left: 6px;
}

.pack-workflow--compact .js-workflow-summary {
  margin: -2px 0 6px;
  padding: 5px 8px;
  font-size: 11px;
  gap: 6px;
}

.pack-workflow--compact .pack-add-container-btn {
  font-size: 11px;
  padding: 2px 8px;
}

.pack-workflow--compact :deep(.pack-combo-crate-inline__name) {
  font-size: 11px;
}

.pack-workflow--compact :deep(.pack-combo-crate-inline__qty),
.pack-workflow--compact :deep(.pack-combo-crate-inline__serial) {
  font-size: 9px;
}

.pack-workflow--compact :deep(.pack-crate-shell-check-line__name) {
  font-size: 11px;
}

.pack-workflow--compact :deep(.pack-crate-shell-check-line__soll),
.pack-workflow--compact :deep(.pack-crate-shell-check-line__serial) {
  font-size: 10px;
}

.pack-workflow--compact :deep(.shell-forward-variance-btn) {
  width: 22px;
  height: 22px;
  font-size: 13px;
}

.pack-workflow--compact :deep(.pack-shell-forward-count-input) {
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

