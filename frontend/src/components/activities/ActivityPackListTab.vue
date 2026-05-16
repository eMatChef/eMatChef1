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

    <div v-else-if="packItems.length === 0" class="section-card">
      <h2 class="section-title">{{ t('activities.packList.title') }}</h2>
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

    <template v-else>
      <div class="section-card">
        <h2 class="section-title">{{ t('activities.packList.titleWorkflow') }}</h2>
        <p v-if="!packListEditable" class="activity-pack-readonly-hint text-muted">
          {{ t('activities.packList.readonlyHint') }}
        </p>
      </div>

      <div class="pack-workflow">
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

        <div class="pack-progress-bar">
          <div class="pack-progress-info">
            <span>{{ t('activities.packList.progressPercent', { pct: stageProgress, stage: activeStageConfig.rightLabel }) }}</span>
            <div class="pack-progress-actions">
              <button
                v-if="packListEditable && stageLeftHeaderCount > 0"
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
          v-if="activePackStage === 'issued_returned'"
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
              <template v-if="activePackStage === 'packed_issued' && packedIssueWarehouseOnlyInContainers">
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
                  v-if="
                    activePackStage === 'packed_issued' &&
                    showPackContainersUi &&
                    isCrateShellPackItem(pi, packContainers)
                  "
                  :shell-pack-item="pi"
                  :stage-right-label="activeStageConfig.rightLabel"
                />
                <PackMaterialRow
                  v-else
                  :item="pi"
                  :show-rack="activePackStage === 'confirmed_packed'"
                >
                  <template #detail>
                    <PackMaterialRowDetail
                      :item="pi"
                      :stage="activePackStage"
                      :stage-right-label="activeStageConfig.rightLabel"
                      side="left"
                      :loose-qty="looseQtyForPackItem(pi)"
                      :qty-in-containers="qtyInContainersForItem(pi)"
                    />
                  </template>
                  <template #info-extra>
                    <PackIssueQuickActions
                      v-if="canReportIssues && activePackStage === 'issued_returned'"
                      :is-consumable="pi.isConsumable"
                      @consumed="emitConsumptionFromPackItem(pi)"
                      @loss="emitIssueWizard(pi, 'loss')"
                      @repair="emitIssueWizard(pi, 'repair')"
                    />
                  </template>
                  <template #trailing>
                    <PackMoveControls
                      v-if="packListEditable && (activePackStage !== 'packed_issued' || packIssueForwardMax(pi) > 0)"
                      direction="forward"
                      :qty="moveQtyInputs[pi.id] ?? 0"
                      :max="packIssueForwardMax(pi)"
                      :disabled="movingId === pi.id"
                      :forward-title="t('activities.packList.titleMoveTo', { stage: activeStageConfig.rightLabel })"
                      @update:qty="setMoveQtyForItem(pi.id, $event)"
                      @move="moveToNextStage(pi)"
                    />
                  </template>
                </PackMaterialRow>
                </template>
              </div>
            </div>

            <!-- Am Event → Retour: Kisten mit Bestand noch am Event (wie rechts bei «Gepackt → Am Event») -->
            <div
              v-if="activePackStage === 'issued_returned' && packContainersWithReturnableAtEvent.length > 0"
              class="pack-workflow-section pack-workflow-section--kisten pack-workflow-section--event-return-kisten"
            >
              <div class="pack-workflow-section-title">{{ t('activities.packList.sectionKisten') }}</div>
              <div class="pack-containers-section">
                <div class="pack-containers-heading">
                  <span class="pack-containers-title text-muted">{{ t('activities.packList.sectionContainers') }}</span>
                </div>
                <div class="pack-containers-children" role="group" :aria-label="t('activities.packList.ariaContainersAtEvent')">
                  <div
                    v-for="c in packContainersWithReturnableAtEvent"
                    :id="'pack-container-event-ret-' + c.id"
                    :key="'ev-ret-' + c.id"
                    class="pack-container-card"
                  >
                    <div class="pack-container-header-row">
                      <button
                        type="button"
                        class="pack-container-chevron-btn"
                        :aria-expanded="!collapsedPackContainers[c.id]"
                        :aria-label="t('activities.packList.ariaToggleContainer')"
                        @click.stop="togglePackContainerCollapsed(c.id)"
                      >
                        <span class="pack-container-chevron" aria-hidden="true">{{
                          collapsedPackContainers[c.id] ? '▶' : '▼'
                        }}</span>
                      </button>
                      <div class="pack-container-header-main">
                        <div class="pack-container-header-title-block">
                          <span class="pack-container-name">{{ c.label }}</span>
                          <span class="pack-container-chip text-muted">{{ t('activities.common.itemsUnit', { count: containerItemCount(c.id) }) }}</span>
                        </div>
                      </div>
                      <div
                        v-if="packListEditable && containerReturnableUnits(c.id) > 0"
                        class="pack-container-header-actions"
                        @click.stop
                      >
                        <button
                          type="button"
                          class="btn btn-xs btn-primary"
                          :disabled="containerBulkLoadingId === c.id"
                          :title="t('activities.packList.stockPiecesTitle', { count: containerReturnableUnits(c.id) })"
                          @click="returnContainerToWarehouse(c)"
                        >
                          {{ t('activities.packList.allToReturn') }}
                        </button>
                      </div>
                    </div>
                    <div
                      v-if="canReportIssues && c.container_material_item_id"
                      class="pack-container-kiste-meldung-row"
                      @click.stop
                    >
                      <span class="pack-container-kiste-meldung-label">{{ t('activities.common.crate') }}</span>
                      <template v-if="isPackMaterialConsumable(String(c.container_material_item_id))">
                        <button
                          type="button"
                          class="btn-issue-quick btn-issue-consumed"
                          @click="
                            emitConsumptionForMaterialId(String(c.container_material_item_id), {
                              linkedContainerLabel: c.label,
                            })
                          "
                        >
                          {{ t('activities.common.issueConsumed') }}
                        </button>
                      </template>
                      <template v-else>
                        <button
                          type="button"
                          class="btn-issue-quick btn-issue-loss"
                          @click="emitIssueWizardByMaterialId(String(c.container_material_item_id), 'loss')"
                        >
                          {{ t('activities.common.issueLoss') }}
                        </button>
                        <button
                          type="button"
                          class="btn-issue-quick btn-issue-repair"
                          @click="emitIssueWizardByMaterialId(String(c.container_material_item_id), 'repair')"
                        >
                          {{ t('activities.common.issueRepair') }}
                        </button>
                      </template>
                    </div>
                    <div v-show="!collapsedPackContainers[c.id]" class="pack-container-inner">
                      <div
                        v-for="ci in containerItemsByContainerId[c.id] ?? []"
                        :key="'ev-ret-ci-' + ci.id"
                        class="pack-container-line pack-container-line--stacked"
                      >
                        <div class="pack-container-line-main">
                          <span class="pack-container-line-name">{{ ci.material_name || t('activities.common.material') }}</span>
                          <span class="pack-container-line-qty text-muted">
                            <template v-if="containerLineRemainingReturn(ci) > 0">
                              {{ t('activities.packList.lineStillAtEvent', { n: containerLineRemainingReturn(ci) }) }}
                            </template>
                            <template v-else>{{ t('activities.packList.returnRecorded') }}</template>
                          </span>
                        </div>
                        <div
                          v-if="
                            canReportIssues &&
                            ci.material_item_id &&
                            containerLineRemainingReturn(ci) > 0
                          "
                          class="pack-container-line-issue-quick"
                          @click.stop
                        >
                          <template v-if="isPackMaterialConsumable(ci.material_item_id)">
                            <button
                              type="button"
                              class="btn-issue-quick btn-issue-consumed"
                              @click="
                                emitConsumptionForMaterialId(ci.material_item_id, {
                                  materialName: ci.material_name,
                                  linkedContainerLabel: ci.batch_label || ci.serial_number || null,
                                })
                              "
                            >
                              {{ t('activities.common.issueConsumed') }}
                            </button>
                          </template>
                          <template v-else>
                            <button
                              type="button"
                              class="btn-issue-quick btn-issue-loss"
                              @click="emitIssueWizardByMaterialId(ci.material_item_id, 'loss')"
                            >
                              {{ t('activities.common.issueLoss') }}
                            </button>
                            <button
                              type="button"
                              class="btn-issue-quick btn-issue-repair"
                              @click="emitIssueWizardByMaterialId(ci.material_item_id, 'repair')"
                            >
                              {{ t('activities.common.issueRepair') }}
                            </button>
                          </template>
                        </div>
                      </div>
                      <div
                        v-if="containerShellStillAtEventQty(c.id) > 0"
                        class="pack-container-line pack-container-line--shell pack-container-line--stacked"
                      >
                        <div class="pack-container-line-main">
                          <span class="pack-container-line-name">{{ t('activities.packList.shellMaterialLine') }}</span>
                          <span class="pack-container-line-qty text-muted">
                            {{ t('activities.packList.shellStillAtEvent', { n: containerShellStillAtEventQty(c.id) }) }}
                          </span>
                        </div>
                        <div
                          v-if="canReportIssues && shellMaterialIdForContainer(c.id)"
                          class="pack-container-line-issue-quick"
                          @click.stop
                        >
                          <template
                            v-if="isPackMaterialConsumable(shellMaterialIdForContainer(c.id) || '')"
                          >
                            <button
                              type="button"
                              class="btn-issue-quick btn-issue-consumed"
                              @click="
                                emitConsumptionForMaterialId(shellMaterialIdForContainer(c.id) || '', {
                                  linkedContainerLabel: c.label,
                                })
                              "
                            >
                              {{ t('activities.common.issueConsumed') }}
                            </button>
                          </template>
                          <template v-else>
                            <button
                              type="button"
                              class="btn-issue-quick btn-issue-loss"
                              @click="
                                emitIssueWizardByMaterialId(shellMaterialIdForContainer(c.id) || '', 'loss')
                              "
                            >
                              {{ t('activities.common.issueLoss') }}
                            </button>
                            <button
                              type="button"
                              class="btn-issue-quick btn-issue-repair"
                              @click="
                                emitIssueWizardByMaterialId(shellMaterialIdForContainer(c.id) || '', 'repair')
                              "
                            >
                              {{ t('activities.common.issueRepair') }}
                            </button>
                          </template>
                        </div>
                      </div>
                      <p
                        v-if="
                          (containerItemsByContainerId[c.id] ?? []).length === 0 &&
                          containerShellStillAtEventQty(c.id) <= 0
                        "
                        class="pack-container-empty text-muted"
                      >
                        {{ t('activities.packList.noLines') }}
                      </p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div
              v-if="showPackContainersUi && activePackStage === 'packed_issued'"
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
                />
          <div class="pack-panel pack-panel-right">
            <div class="pack-panel-header pack-panel-header-done pack-panel-header--split">
              <div class="pack-panel-header-main">
                <span class="pack-panel-title">{{ activeStageConfig.rightLabel }}</span>
                <span class="pack-panel-count">{{ stageRightItems.length }}</span>
              </div>
              <div v-if="showPackContainersUi && packListEditable && activePackStage === 'confirmed_packed'" class="pack-panel-header-actions">
                <button
                  type="button"
                  class="btn btn-xs btn-outline pack-add-container-btn"
                  :disabled="containerMutationLoading"
                  :title="t('activities.packList.addContainerTitle')"
                  @click="openAddContainerModal"
                >
                  {{ t('activities.packList.addContainerButton') }}
                </button>
                <button
                  type="button"
                  class="btn btn-xs btn-primary pack-add-container-btn"
                  :disabled="containerMutationLoading"
                  :title="t('activities.packList.addCrateTitle')"
                  @click="openAddContainerModal"
                >
                  {{ t('activities.packList.addCrateButton') }}
                </button>
              </div>
            </div>
            <div v-if="!rightPanelHasEventContent" class="pack-panel-empty">
              {{ t('activities.packList.rightPanelEmpty') }}
            </div>

            <div
              v-if="
                activePackStage === 'packed_issued' &&
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
                    <div
                      v-for="c in packContainersWithIssuedAtEvent"
                      :id="'pack-container-at-event-' + c.id"
                      :key="'at-ev-' + c.id"
                      class="pack-container-card"
                      :class="{
                        'pack-container-card--target':
                          activePackTarget?.kind === 'container' && activePackTarget.containerId === c.id,
                      }"
                    >
                      <div class="pack-container-header-row">
                        <button
                          type="button"
                          class="pack-container-chevron-btn"
                          :aria-expanded="!collapsedPackContainers[c.id]"
                          :aria-label="t('activities.packList.ariaToggleContainer')"
                          @click.stop="togglePackContainerCollapsed(c.id)"
                        >
                          <span class="pack-container-chevron" aria-hidden="true">{{
                            collapsedPackContainers[c.id] ? '▶' : '▼'
                          }}</span>
                        </button>
                        <div class="pack-container-header-main">
                          <div class="pack-container-header-title-block">
                            <button
                              type="button"
                              class="pack-container-select-main"
                              :aria-pressed="
                                activePackTarget?.kind === 'container' && activePackTarget.containerId === c.id
                              "
                              @click="toggleActiveContainer(c.id)"
                            >
                              <span class="pack-container-name">{{ c.label }}</span>
                            </button>
                            <span class="pack-container-chip text-muted">{{ t('activities.common.itemsUnit', { count: containerItemCount(c.id) }) }}</span>
                          </div>
                        </div>
                        <div
                          v-if="
                            packListEditable &&
                            (containerUnissueableUnits(c.id) > 0 || containerIssueableUnits(c.id) > 0)
                          "
                          class="pack-container-header-actions"
                          @click.stop
                        >
                          <button
                            v-if="containerUnissueableUnits(c.id) > 0"
                            type="button"
                            class="btn-moveback-arrow btn-move-arrow--container-header"
                            :disabled="containerBulkLoadingId === c.id"
                            :title="t('activities.packList.unissueTitle', { count: containerUnissueableUnits(c.id) })"
                            @click="unissueContainerToPacked(c)"
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                              <path d="M19 12H5" />
                              <polyline points="12 19 5 12 12 5" />
                            </svg>
                          </button>
                          <button
                            v-if="containerIssueableUnits(c.id) > 0"
                            type="button"
                            class="btn-move-arrow btn-move-arrow--container-header"
                            :disabled="containerBulkLoadingId === c.id"
                            :title="
                              t('activities.packList.issueRestTitle', {
                                stage: activeStageConfig.rightLabel,
                                count: containerIssueableUnits(c.id),
                              })
                            "
                            @click="issueContainerToEvent(c)"
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                              <path d="M5 12h14" />
                              <polyline points="12 5 19 12 12 19" />
                            </svg>
                          </button>
                        </div>
                      </div>
                      <div
                        v-if="canReportIssues && c.container_material_item_id"
                        class="pack-container-kiste-meldung-row"
                        @click.stop
                      >
                        <span class="pack-container-kiste-meldung-label">{{ t('activities.common.crate') }}</span>
                        <template v-if="isPackMaterialConsumable(String(c.container_material_item_id))">
                          <button
                            type="button"
                            class="btn-issue-quick btn-issue-consumed"
                            @click="
                            emitConsumptionForMaterialId(String(c.container_material_item_id), {
                              linkedContainerLabel: c.label,
                            })
                          "
                          >
                            {{ t('activities.common.issueConsumed') }}
                          </button>
                        </template>
                        <template v-else>
                          <button
                            type="button"
                            class="btn-issue-quick btn-issue-loss"
                            @click="emitIssueWizardByMaterialId(String(c.container_material_item_id), 'loss')"
                          >
                            {{ t('activities.common.issueLoss') }}
                          </button>
                          <button
                            type="button"
                            class="btn-issue-quick btn-issue-repair"
                            @click="emitIssueWizardByMaterialId(String(c.container_material_item_id), 'repair')"
                          >
                            {{ t('activities.common.issueRepair') }}
                          </button>
                        </template>
                      </div>
                      <div v-show="!collapsedPackContainers[c.id]" class="pack-container-inner">
                        <div
                          v-for="ci in containerItemsByContainerId[c.id] ?? []"
                          :key="'at-ev-ci-' + ci.id"
                          class="pack-container-line pack-container-line--issue-row pack-container-line--stacked"
                        >
                          <div
                            v-if="packListEditable && containerLineUnissueableMax(ci) > 0"
                            class="pack-card-actions pack-card-actions-left"
                          >
                            <button
                              type="button"
                              class="btn-moveback-arrow"
                              :disabled="containerMutationLoading"
                              :title="t('activities.packList.unissueLineTitle', { max: containerLineUnissueableMax(ci) })"
                              @click="unissueContainerLineToPacked(c.id, ci)"
                            >
                              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M19 12H5" />
                                <polyline points="12 19 5 12 12 5" />
                              </svg>
                            </button>
                            <input
                              v-model.number="containerUnissueLineInputs[containerIssueLineKey(c.id, ci.id)]"
                              type="number"
                              min="1"
                              :max="containerLineUnissueableMax(ci)"
                              class="pack-moveback-input"
                              @keyup.enter="unissueContainerLineToPacked(c.id, ci)"
                            />
                          </div>
                          <div
                            v-if="packListEditable && activePackTarget?.kind === 'loose'"
                            class="pack-card-actions pack-card-actions-left"
                          >
                            <button
                              type="button"
                              class="btn-moveback-arrow"
                              :disabled="containerMutationLoading"
                              :title="t('activities.packList.pullLooseTitle')"
                              @click="pullFromContainer(c.id, ci)"
                            >
                              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M19 12H5" />
                                <polyline points="12 19 5 12 12 5" />
                              </svg>
                            </button>
                            <input
                              v-model.number="containerPullQtyInputs[containerPullKey(c.id, ci.id)]"
                              type="number"
                              min="1"
                              :max="ci.quantity_packed"
                              class="pack-moveback-input"
                              @keyup.enter="pullFromContainer(c.id, ci)"
                            />
                          </div>
                          <div class="pack-container-line-main">
                            <span class="pack-container-line-name">{{ ci.material_name || t('activities.common.material') }}</span>
                            <span class="pack-container-line-qty text-muted">
                              <template v-if="containerLineRemainingIssue(ci) > 0">
                                {{
                                  t('activities.packList.lineNotYetIssued', {
                                    rem: containerLineRemainingIssue(ci),
                                    packed: ci.quantity_packed,
                                    stage: activeStageConfig.rightLabel,
                                  })
                                }}
                              </template>
                              <template v-else-if="containerLinePackRemaining(ci) > 0">
                                {{ t('activities.packList.packListNotYetAtStage', { stage: activeStageConfig.rightLabel }) }}
                              </template>
                              <template v-else>
                                {{
                                  t('activities.packList.issuedFraction', {
                                    issued: ci.quantity_issued ?? 0,
                                    packed: ci.quantity_packed,
                                    stage: activeStageConfig.rightLabel,
                                  })
                                }}
                              </template>
                            </span>
                          </div>
                          <div
                            v-if="packListEditable && containerLineIssueableMax(ci) > 0"
                            class="pack-card-actions"
                          >
                            <div class="pack-move-inline">
                              <input
                                v-model.number="containerIssueLineInputs[containerIssueLineKey(c.id, ci.id)]"
                                type="number"
                                min="1"
                                :max="containerLineIssueableMax(ci)"
                                class="pack-move-input"
                                @keyup.enter="issueContainerLineToEvent(c.id, ci)"
                              />
                              <button
                                type="button"
                                class="btn-move-arrow"
                                :disabled="containerMutationLoading"
                                :title="t('activities.packList.issueLinePackTitle', { stage: activeStageConfig.rightLabel })"
                                @click="issueContainerLineToEvent(c.id, ci)"
                              >
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                  <path d="M5 12h14" />
                                  <polyline points="12 5 19 12 12 19" />
                                </svg>
                              </button>
                            </div>
                          </div>
                          <div
                            v-if="
                              canReportIssues &&
                              ci.material_item_id &&
                              (ci.quantity_issued ?? 0) > 0
                            "
                            class="pack-container-line-issue-quick"
                            @click.stop
                          >
                            <template v-if="isPackMaterialConsumable(ci.material_item_id)">
                              <button
                                type="button"
                                class="btn-issue-quick btn-issue-consumed"
                                @click="
                                  emitConsumptionForMaterialId(ci.material_item_id, {
                                    materialName: ci.material_name,
                                    linkedContainerLabel: ci.batch_label || ci.serial_number || null,
                                  })
                                "
                              >
                                {{ t('activities.common.issueConsumed') }}
                              </button>
                            </template>
                            <template v-else>
                              <button
                                type="button"
                                class="btn-issue-quick btn-issue-loss"
                                @click="emitIssueWizardByMaterialId(ci.material_item_id, 'loss')"
                              >
                                {{ t('activities.common.issueLoss') }}
                              </button>
                              <button
                                type="button"
                                class="btn-issue-quick btn-issue-repair"
                                @click="emitIssueWizardByMaterialId(ci.material_item_id, 'repair')"
                              >
                                {{ t('activities.common.issueRepair') }}
                              </button>
                            </template>
                          </div>
                        </div>
                        <p
                          v-if="(containerItemsByContainerId[c.id] ?? []).length === 0"
                          class="pack-container-empty text-muted"
                        >
                          {{ t('activities.packList.nothingAssigned') }}
                        </p>
                      </div>
                    </div>
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
                    >
                      <template #leading>
                        <PackMoveControls
                          v-if="packListEditable"
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
                          :stage-right-label="activeStageConfig.rightLabel"
                          side="right"
                          use-detail-stack
                        />
                      </template>
                      <template #info-extra>
                        <PackIssueQuickActions
                          v-if="canReportIssues && activePackStage === 'packed_issued'"
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
                                            :show-rack="activePackStage === 'confirmed_packed'"
                                          >
                                            <template #leading>
                                              <PackMoveControls
                                                v-if="packListEditable"
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
                                                :stage-right-label="activeStageConfig.rightLabel"
                                                side="right"
                                                :loose-qty="looseQtyForPackItem(pi)"
                                                :qty-in-containers="qtyInContainersForItem(pi)"
                                                :loose-issued-at-event="looseIssuedAtEvent(pi)"
                                                use-detail-stack
                                              >
                                                <button
                                                  v-if="
                                                    packListEditable &&
                                                    activePackStage === 'confirmed_packed' &&
                                                    looseQtyForPackItem(pi) > 0 &&
                                                    packContainers.length > 0 &&
                                                    !isCrateShellPackItem(pi, packContainers)
                                                  "
                                                  type="button"
                                                  class="btn btn-xs btn-outline pack-assign-btn"
                                                  :disabled="containerMutationLoading"
                                                  @click="onAssignButtonClick(pi)"
                                                >
                                                  {{ t('activities.packList.assignToContainer') }}
                                                </button>
                                              </PackMaterialRowDetail>
                                            </template>
                                            <template #info-extra>
                                              <PackIssueQuickActions
                                                v-if="canReportIssues && activePackStage === 'packed_issued'"
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
                    :show-rack="activePackStage === 'confirmed_packed'"
                  >
                                        <template #leading>
                                          <PackMoveControls
                                            v-if="packListEditable"
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
                                            :stage-right-label="activeStageConfig.rightLabel"
                                            side="right"
                                            :loose-qty="looseQtyForPackItem(pi)"
                                            :qty-in-containers="qtyInContainersForItem(pi)"
                                            :loose-issued-at-event="looseIssuedAtEvent(pi)"
                                            use-detail-stack
                                          >
                                            <button
                                              v-if="
                                                packListEditable &&
                                                activePackStage === 'confirmed_packed' &&
                                                looseQtyForPackItem(pi) > 0 &&
                                                packContainers.length > 0 &&
                                                !isCrateShellPackItem(pi, packContainers)
                                              "
                                              type="button"
                                              class="btn btn-xs btn-outline pack-assign-btn"
                                              :disabled="containerMutationLoading"
                                              @click="onAssignButtonClick(pi)"
                                            >
                                              {{ t('activities.packList.assignToContainer') }}
                                            </button>
                                          </PackMaterialRowDetail>
                                        </template>
                                        <template #info-extra>
                                          <PackIssueQuickActions
                                            v-if="canReportIssues && activePackStage === 'packed_issued'"
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
              v-if="showPackContainersUi && activePackStage === 'confirmed_packed'"
              class="pack-workflow-section pack-workflow-section--kisten"
            >
              <div class="pack-workflow-section-title">{{ t('activities.packList.sectionKisten') }}</div>
              <div class="pack-containers-section">
              <div class="pack-containers-heading">
                <span class="pack-containers-title text-muted">{{ t('activities.packList.sectionContainers') }}</span>
                <button
                  v-if="packListEditable"
                  type="button"
                  class="pack-target-loose"
                  :class="{ 'pack-target-loose--active': activePackTarget?.kind === 'loose' }"
                  :title="t('activities.packList.targetLooseTitle')"
                  @click="toggleActiveLoose"
                >
                  {{ t('activities.packList.sectionLoose') }}
                </button>
              </div>
              <div class="pack-containers-children" role="group" :aria-label="t('activities.packList.ariaContainersThisList')">
                <p v-if="packContainers.length === 0" class="pack-containers-empty-hint text-muted">
                  {{ t('activities.packList.hintNoContainersConfirmed', { stage: activeStageConfig.rightLabel }) }}
                </p>
                <div
                  v-for="c in packContainersSorted"
                  :id="'pack-container-' + c.id"
                  :key="c.id"
                  class="pack-container-card"
                  :class="{
                    'pack-container-card--target':
                      activePackTarget?.kind === 'container' && activePackTarget.containerId === c.id,
                  }"
                >
                <div class="pack-container-header-row">
                  <button
                    type="button"
                    class="pack-container-chevron-btn"
                    :aria-expanded="!collapsedPackContainers[c.id]"
                    :aria-label="t('activities.packList.ariaToggleContainer')"
                    @click.stop="togglePackContainerCollapsed(c.id)"
                  >
                    <span class="pack-container-chevron" aria-hidden="true">{{
                      collapsedPackContainers[c.id] ? '▶' : '▼'
                    }}</span>
                  </button>
                  <div class="pack-container-header-main">
                    <button
                      type="button"
                      class="pack-container-select-main"
                      :aria-pressed="
                        activePackTarget?.kind === 'container' && activePackTarget.containerId === c.id
                      "
                      @click="toggleActiveContainer(c.id)"
                    >
                      <span class="pack-container-name">{{ c.label }}</span>
                    </button>
                    <div class="pack-container-header-meta">
                      <span class="pack-container-chip text-muted">{{ t('activities.common.itemsUnit', { count: containerItemCount(c.id) }) }}</span>
                    </div>
                  </div>
                </div>
                <div v-show="!collapsedPackContainers[c.id]" class="pack-container-inner">
                  <div
                    v-for="ci in containerItemsByContainerId[c.id] ?? []"
                    :key="ci.id"
                    class="pack-container-line"
                  >
                    <div v-if="packListEditable" class="pack-card-actions pack-card-actions-left">
                      <button
                        type="button"
                        class="btn-moveback-arrow"
                        :disabled="containerMutationLoading"
                        :title="t('activities.packList.pullFromContainerTitle')"
                        @click="pullFromContainer(c.id, ci)"
                      >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                          <path d="M19 12H5" />
                          <polyline points="12 19 5 12 12 5" />
                        </svg>
                      </button>
                      <input
                        v-model.number="containerPullQtyInputs[containerPullKey(c.id, ci.id)]"
                        type="number"
                        min="1"
                        :max="ci.quantity_packed"
                        class="pack-moveback-input"
                        @keyup.enter="pullFromContainer(c.id, ci)"
                      />
                    </div>
                    <div class="pack-container-line-main">
                      <span class="pack-container-line-name">{{ ci.material_name || t('activities.common.material') }}</span>
                      <span class="pack-container-line-qty">{{ t('activities.packList.qtyInContainerLine', { n: ci.quantity_packed }) }}</span>
                    </div>
                  </div>
                  <p
                    v-if="(containerItemsByContainerId[c.id] ?? []).length === 0"
                    class="pack-container-empty text-muted"
                  >
                    {{ t('activities.packList.nothingAssigned') }}
                  </p>
                  <button
                    v-if="packListEditable"
                    type="button"
                    class="pack-container-delete"
                    :disabled="containerMutationLoading"
                    @click="confirmDeleteContainer(c)"
                  >
                    {{ t('activities.packList.deleteContainer') }}
                  </button>
                </div>
                </div>
              </div>
              </div>
            </div>
          </div>
        </div>
      </div>

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
import type { ActivityIssueReportRow, ActivityTransitionRow } from '@/api/activities'
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
  PACK_STAGE_KEYS,
  autoPackStageForStatus,
  getBackendStage as computeBackendStage,
  getStageLeftQty as computeStageLeftQty,
  getStageRightQty as computeStageRightQty,
  getStageTotalQty as computeStageTotalQty,
  groupActivityPackItemsByCategory,
  workflowTargetStatusForStage,
} from '@/components/activities/packStageQuantities'
import PackCrateShellForwardModal from '@/components/activities/PackCrateShellForwardModal.vue'
import PackCrateShellPackItemRow from '@/components/activities/PackCrateShellPackItemRow.vue'
import PackWarehouseIssueContainerCard from '@/components/activities/PackWarehouseIssueContainerCard.vue'
import { PACK_WAREHOUSE_ISSUE_INJECT_KEY } from '@/components/activities/packWarehouseIssueInjectKey'
import {
  buildShellCrateBackDeviations,
  crateShellForwardPeekSections,
  crateShellPeekSectionsForPackItem,
  isCrateShellPackItem,
  isPackContainerMergedIntoStageLeftRow,
  packContainerItemSections,
  packShellContainerForPackItem,
  peekSectionsForShellContainer,
} from '@/components/activities/packShellCrateHelpers'
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
import { useToast } from '@/composables/useToast'

const { t, locale } = useI18n()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()

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
    packListEditable: boolean
    transitions: ActivityTransitionRow[]
    /** Meldungen (v4.01): Schnellbuttons in Packliste wenn Status issued/returned */
    canReportIssues?: boolean
    /** Parent erhöht nach Verbrauchsbuchung → Packliste neu laden */
    reloadToken?: number
  }>(),
  { departmentId: '', canReportIssues: false, reloadToken: 0 },
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
}>()

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

const packStagesForUi = computed(() =>
  PACK_STAGE_KEYS.map((key) => ({
    key,
    leftLabel: t(`activities.packList.stages.${key}.left`),
    rightLabel: t(`activities.packList.stages.${key}.right`),
  })),
)

const activeStageConfig = computed(() => {
  const key = PACK_STAGE_KEYS.includes(activePackStage.value)
    ? activePackStage.value
    : 'confirmed_packed'
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
const activityCrateCheckSnapshots = ref<Record<string, CrateCheckSnapshot>>({})
const useCrateRealityByPackItemId = ref<Record<string, boolean>>({})
/** Menge zum Herausnehmen aus Behälter (Pfeil + Eingabe), Schlüssel containerId:itemId */
const containerPullQtyInputs = ref<Record<string, number>>({})
/** Gepackt → Am Event: Teilmenge aus Behälterzeile ins Event (Schlüssel wie pull) */
const containerIssueLineInputs = ref<Record<string, number>>({})
/** Am Event → Gepackt: Teilmenge aus Behälterzeile zurück (Schlüssel wie oben) */
const containerUnissueLineInputs = ref<Record<string, number>>({})

/** Aktives Ziel: lose Menge ODER ein Behälter für Direktzuweisung «In Behälter» */
type ActivePackTarget = { kind: 'loose' } | { kind: 'container'; containerId: string }
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
  if (stage !== 'confirmed_packed' && stage !== 'packed_issued') return false
  if (!isCrateShellPackItem(pi, packContainers.value)) return false
  if (shellCrateCheckDoneForPackItem(pi.id)) return false
  return true
}

function closeShellForwardModal() {
  shellForwardModalOpen.value = false
  shellForwardItem.value = null
}

async function openShellCrateForwardModal(item: ActivityPackItem, moveQty: number) {
  shellForwardItem.value = item
  shellForwardMoveQty.value = moveQty
  const shellC = packShellContainerForPackItem(item, packContainers.value)
  const warehouseMids = shellC
    ? containerWarehouseTemplateByContainerId.value[shellC.id]
    : undefined
  shellForwardSections.value = crateShellForwardPeekSections(
    item,
    packContainers.value,
    containerItemsByContainerId.value,
    warehouseMids,
    peekSectionTitles(),
    t('activities.common.material'),
  )
  shellForwardHistoryReplenishByKey.value = {}
  shellForwardHistoryPrefillHint.value = null
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
    const snap = snaps[item.id]
    if (snap?.created_at) {
      shellForwardHistoryPrefillHint.value = t('activities.packList.shellForwardHistoryPrefillHint', {
        date: new Date(snap.created_at).toLocaleString(locale.value),
      })
    }
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
    closeShellForwardModal()
    await executeMoveToNextStage(item, qty)
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
  if (activePackStage.value === 'packed_issued') {
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
  return peekSectionsForShellContainer(
    c,
    containerItemsByContainerId.value,
    containerWarehouseTemplateByContainerId.value,
    peekSectionTitles(),
    t('activities.common.material'),
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
  return packContainerItemSections(
    c.id,
    containerItemsByContainerId.value,
    containerWarehouseTemplateByContainerId.value[c.id],
    peekSectionTitles(),
  )
}

function peekSectionsForShellPackItem(pi: ActivityPackItem): PackCrateShellPeekSection[] {
  return crateShellPeekSectionsForPackItem(
    pi,
    packContainers.value,
    containerItemsByContainerId.value,
    containerWarehouseTemplateByContainerId.value,
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
  if (activePackStage.value !== 'packed_issued') return false
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
  return isPackContainerMergedIntoStageLeftRow(
    c,
    packContainers.value,
    stageLeftItems.value,
    activePackStage.value,
  )
}

/** Behälter & lose/in-Behälter-Aufteilung auch bei «Gepackt → Am Event» (linkes «Gepackt» wie zuvor rechts) */
const showPackContainersUi = computed(
  () =>
    activePackStage.value === 'confirmed_packed' || activePackStage.value === 'packed_issued',
)

function packedQtyBaseForContainerSplit(pi: ActivityPackItem): number {
  if (activePackStage.value === 'confirmed_packed') return getStageRightQty(pi)
  if (activePackStage.value === 'packed_issued') return Math.max(0, pi.quantityPacked)
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
  if (activePackStage.value === 'issued_returned') return getStageRightQty(pi)
  if (activePackStage.value !== 'confirmed_packed' && activePackStage.value !== 'packed_issued') {
    return getStageRightQty(pi)
  }
  const total = packedQtyBaseForContainerSplit(pi)
  const assigned = assignedQtyByMaterialId.value[pi.materialItemId] ?? 0
  const physicalLoose = Math.max(0, total - assigned)
  if (activePackStage.value === 'packed_issued') {
    return Math.max(0, physicalLoose - looseIssuedAtEvent(pi))
  }
  return physicalLoose
}

function qtyInContainersForItem(pi: ActivityPackItem): number {
  if (activePackStage.value !== 'confirmed_packed' && activePackStage.value !== 'packed_issued') {
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
  return (containerItemsByContainerId.value[containerId] ?? []).length
}

/** Kisten-Shell-Zeile im Behälter — nicht als lose Zeile ziehen */
function isVirtualWarehouseContainerLine(ci: ActivityPackContainerItem): boolean {
  for (const c of packContainers.value) {
    const mid = (c.container_material_item_id ?? '').trim()
    if (mid && ci.material_item_id === mid) return true
  }
  return false
}

/** Pack-Position der zugeordneten Kisten-Charge (Holzharassen o. ä.) — nicht in container_items, aber mitziehen */
function shellPackItemForContainer(containerId: string): ActivityPackItem | undefined {
  const c = packContainers.value.find((x) => x.id === containerId)
  const mid = c?.container_material_item_id
  if (!mid) return undefined
  return packItems.value.find((p) => p.materialItemId === mid)
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
  if (activePackStage.value !== 'packed_issued') return getStageRightQty(pi)
  return Math.max(0, (pi.quantityIssued ?? 0) - issuedQtyInContainersForMaterial(pi.materialItemId))
}

function rightQtyForMoveBack(pi: ActivityPackItem): number {
  if (activePackStage.value === 'packed_issued') {
    if (isCrateShellPackItem(pi, packContainers.value)) {
      return getStageRightQty(pi)
    }
    return looseIssuedAtEvent(pi)
  }
  return getStageRightQty(pi)
}

function containerLineRemainingIssue(ci: ActivityPackContainerItem): number {
  const p = ci.quantity_packed ?? 0
  const i = ci.quantity_issued ?? 0
  return Math.max(0, p - i)
}

/** Noch ausgebbar laut Pack-Position (Material gesamt) */
function containerLinePackRemaining(ci: ActivityPackContainerItem): number {
  const pi = packItems.value.find((x) => x.materialItemId === ci.material_item_id)
  return pi ? Math.max(0, pi.quantityPacked - pi.quantityIssued) : 0
}

/**
 * Wie Backend issue_all: min(Zeilenrest, Pack-Rest).
 * Drift: Behälter hat quantity_issued == quantity_packed, Packliste aber noch Rest → trotzdem buchbar.
 */
function containerLineIssueableMax(ci: ActivityPackContainerItem): number {
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
  if (activePackStage.value !== 'issued_returned') return getStageLeftQty(pi)
  if (packContainers.value.length === 0) return getStageLeftQty(pi)
  return Math.max(0, getStageLeftQty(pi) - containerStillAtEventQtyForMaterial(pi.materialItemId))
}

async function issueContainerToEvent(c: ActivityPackContainer) {
  if (containerBulkLoadingId.value) return
  const shell = shellPackItemForContainer(c.id)
  if (shell && needsShellCratePresenceConfirm(shell)) {
    const max = packIssueForwardMax(shell)
    if (max >= 1) {
      await openShellCrateForwardModal(shell, max)
    }
    return
  }
  containerBulkLoadingId.value = c.id
  try {
    await issueAllPackContainerItems(props.activityId, c.id)
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
  const next: Record<string, Set<string>> = {}
  await Promise.all(
    packContainers.value.map(async (c) => {
      const batchId = (c.container_batch_id ?? '').trim()
      if (!batchId) return
      try {
        const data = await getContainerBatchContents(batchId)
        const mids = new Set<string>()
        for (const row of data.contents ?? []) {
          const mid = (row.material_id ?? '').trim()
          if (mid) mids.add(mid)
        }
        next[c.id] = mids
      } catch {
        /* Lager-Vorlage optional */
      }
    }),
  )
  containerWarehouseTemplateByContainerId.value = next
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
    initContainerPullQtyInputs()
    initContainerIssueLineInputs()
    initContainerUnissueLineInputs()
  } catch {
    packContainers.value = []
    containerItemsByContainerId.value = {}
    containerWarehouseTemplateByContainerId.value = {}
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
      activePackStage.value === 'packed_issued'
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

function toggleActiveContainer(containerId: string) {
  if (activePackTarget.value?.kind === 'container' && activePackTarget.value.containerId === containerId) {
    activePackTarget.value = null
  } else {
    activePackTarget.value = { kind: 'container', containerId }
  }
}

function toggleActiveLoose() {
  if (activePackTarget.value?.kind === 'loose') {
    activePackTarget.value = null
  } else {
    activePackTarget.value = { kind: 'loose' }
  }
}

function onAssignButtonClick(pi: ActivityPackItem) {
  if (!props.packListEditable || packContainers.value.length === 0) return
  const tgt = activePackTarget.value
  if (tgt?.kind === 'loose') {
    toast.info(t('activities.packList.toastAssignLooseActive'))
    return
  }
  if (tgt?.kind === 'container') {
    void assignDirectToActiveContainer(pi, tgt.containerId)
    return
  }
  openAssignModal(pi)
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

async function assignDirectToActiveContainer(pi: ActivityPackItem, containerId: string) {
  const max = looseQtyForPackItem(pi)
  if (max < 1) return
  await assignMaterialToContainer(pi, containerId, max)
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

async function issueContainerLineToEvent(containerId: string, ci: ActivityPackContainerItem) {
  if (!props.packListEditable || activePackStage.value !== 'packed_issued') return
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

  const pi = packItems.value.find((p) => p.materialItemId === ci.material_item_id)
  if (!pi) {
    toast.error(t('activities.packList.toastNoPackLine'))
    return
  }

  containerMutationLoading.value = true
  try {
    await postMovePackItem(props.activityId, pi.id, { stage: 'issued', quantity: qty })
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

async function unissueContainerLineToPacked(containerId: string, ci: ActivityPackContainerItem) {
  if (!props.packListEditable || activePackStage.value !== 'packed_issued') return
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
    await postMoveBackPackItem(props.activityId, pi.id, { stage: 'issued', quantity: qty })
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
  containerMutationLoading.value = true
  try {
    await deleteActivityPackContainer(props.activityId, c.id)
    await loadContainersData()
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
  return computeStageLeftQty(item, activePackStage.value)
}

function getStageRightQty(item: ActivityPackItem): number {
  return computeStageRightQty(item, activePackStage.value)
}

function getStageTotalQty(item: ActivityPackItem): number {
  return computeStageTotalQty(item, activePackStage.value)
}

/** Max. Stück die links per Pfeil buchbar sind (nur lose bei Gepackt→Event / Event→Retour) */
function packIssueForwardMax(pi: ActivityPackItem): number {
  if (activePackStage.value === 'packed_issued') {
    if (isCrateShellPackItem(pi, packContainers.value)) {
      return getStageLeftQty(pi)
    }
    return Math.min(looseQtyForPackItem(pi), getStageLeftQty(pi))
  }
  if (activePackStage.value === 'issued_returned') {
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
      activePackStage.value === 'packed_issued' &&
      showPackContainersUi.value &&
      getStageLeftQty(p) > 0 &&
      looseQtyForPackItem(p) <= 0 &&
      !isCrateShellPackItem(p, packContainers.value)
    ) {
      return false
    }
    if (
      activePackStage.value === 'issued_returned' &&
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
  if (activePackStage.value === 'packed_issued' && showPackContainersUi.value) {
    return stageLeftItems.value.length + packContainersSortedWarehouseOnlyVisible.value.length
  }
  if (activePackStage.value === 'issued_returned' && packContainers.value.length > 0) {
    return stageLeftItems.value.length + packContainersWithReturnableAtEvent.value.length
  }
  return stageLeftItems.value.length
})

/** Nur-in-Behältern-Hinweis: noch Lagerbestand, aber keine Zeile mehr oben (alles in Kisten). */
const packedIssueWarehouseOnlyInContainers = computed(() => {
  if (activePackStage.value !== 'packed_issued') return false
  return packItems.value.some((p) => getStageLeftQty(p) > 0 && looseQtyForPackItem(p) <= 0)
})
const stageRightItems = computed(() => packItems.value.filter((p) => getStageRightQty(p) > 0))

const rightPanelHasEventContent = computed(() => {
  if (!showPackContainersUi.value) {
    return stageRightItems.value.length > 0
  }
  if (activePackStage.value === 'packed_issued') {
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
  if (sh != null && (sh.quantityIssued ?? 0) > 0) return true
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
    activePackStage.value === 'issued_returned' && packContainersWithReturnableAtEvent.value.length > 0,
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

/** «Ohne Behälter»: nur lose Gepackt-Menge, gruppiert nach Kategorie */
const ohneBehaelterGroups = computed(() => {
  void locale.value
  if (!showPackContainersUi.value) return []
  if (activePackStage.value === 'confirmed_packed') {
    const items = stageRightItems.value.filter(
      (p) => getStageRightQty(p) > 0 && qtyInContainersForItem(p) === 0,
    )
    return groupPackItems(items)
  }
  if (activePackStage.value === 'packed_issued') {
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
      (p) => looseQtyForPackItem(p) > 0 && qtyInContainersForItem(p) > 0,
    )
  }
  if (activePackStage.value === 'packed_issued') {
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
    moveQtyInputs.value[item.id] = Math.max(0, leftQty)
    const rightQty = rightQtyForMoveBack(item)
    moveBackQtyInputs.value[item.id] = Math.max(0, rightQty)
  }
}

function applyUpdatedItem(updated: ActivityPackItem) {
  const idx = packItems.value.findIndex((p) => p.id === updated.id)
  if (idx !== -1) packItems.value[idx] = updated
  initMoveQtyInputs()
}

async function moveToNextStage(item: ActivityPackItem, qty?: number) {
  if (!props.packListEditable) return
  const maxMove = packIssueForwardMax(item)
  const moveQty = Math.min(
    maxMove,
    qty ?? moveQtyInputs.value[item.id] ?? maxMove,
  )
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

    /** Aktiver Behälter: dieselbe Menge nicht nur «lose» in Gepackt, sondern gleich in die Kiste legen */
    const target = activePackTarget.value
    if (activePackStage.value === 'confirmed_packed' && target?.kind === 'container' && moveQty > 0) {
      const looseAfter = looseQtyForPackItem(updated)
      const intoContainer = Math.min(moveQty, looseAfter)
      if (intoContainer >= 1) {
        await assignMaterialToContainer(updated, target.containerId, intoContainer, {
          successMessage: t('activities.packList.toastMoveToContainerDirect'),
        })
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
    if (activePackStage.value === 'packed_issued' && packContainers.value.length > 0) {
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
    activePackStage.value = autoPackStageForStatus(props.status)
    initMoveQtyInputs()
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
  canReportIssues: computed(() => props.canReportIssues),
  activePackTarget,
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
  (token, prev) => {
    if (token !== prev && token > 0) {
      void loadAll()
    }
  },
)

watch(
  packContainers,
  (list) => {
    const t = activePackTarget.value
    if (t?.kind !== 'container') return
    if (!list.some((c) => c.id === t.containerId)) {
      activePackTarget.value = null
    }
  },
  { deep: true },
)
</script>

<style scoped src="@/styles/material-detail-view.css"></style>
<style scoped>
@import '@/styles/views/activities/detail-workflow.css';

.activity-pack-readonly-hint {
  margin: 0 0 8px;
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

.pack-container-card--target {
  border-color: #2563eb;
  box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.2);
  background: #eff6ff;
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

@media (max-width: 768px) {
  .pack-panels {
    grid-template-columns: 1fr;
  }
}
</style>
