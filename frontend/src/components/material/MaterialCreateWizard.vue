<template>
  <Teleport to="body">
    <div
      v-if="showDialog"
      key="material-wizard"
      class="material-wizard-overlay"
      role="presentation"
      @mousedown.self.prevent
      @click.self.prevent
    >
      <div class="material-wizard-modal" role="dialog" aria-modal="true" @click.stop>
        <!-- Header -->
        <div class="material-wizard-header">
          <div class="material-wizard-header-title">
            <h2>{{
              isAddBatchMode
                ? t('components.materialCreateWizard.titleAddBatch')
                : t('components.materialCreateWizard.titleCreateMaterial')
            }}</h2>
            <button class="help-btn" :title="t('components.materialCreateWizard.helpTitle')">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
              </svg>
            </button>
          </div>
          <button class="close-btn" @click="handleClose">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="material-wizard-body">
          <!-- Content -->
          <div class="material-wizard-content">
            <!-- Left: Form Steps -->
            <div ref="wizardFormRef" class="material-wizard-form" @focusin="onWizardFormFocusIn">
            
            <!-- Add Batch Mode: Anzeige des ausgewählten Materials -->
            <div v-if="isAddBatchMode && selectedExistingMaterial" class="selected-material-banner">
              <div class="banner-content">
                <div class="banner-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                  </svg>
                </div>
                <div class="banner-info">
                  <span class="banner-label">{{ t('components.materialCreateWizard.bannerNewLotFor') }}</span>
                  <span class="banner-name">{{ selectedExistingMaterial.name }}</span>
                  <span class="banner-details">
                    {{ selectedExistingMaterial.category?.name || t('components.materialCreateWizard.noCategory') }} •
                    {{ t('components.materialCreateWizard.bannerCurrentPcs', { n: selectedExistingMaterial.total_stock }) }}
                  </span>
                </div>
                <button
                  type="button"
                  class="banner-close"
                  @click="reloadWizardFromBatchBanner"
                  :title="t('components.materialCreateWizard.bannerRestartTitle')"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- SCHRITT 0: Erstellmodus wählen (inline für zuverlässige Anzeige) -->
            <div v-if="shouldRenderCreationMode" class="wizard-start-block" data-step="creation_mode">
              <div class="step-header">
                <span class="step-title">{{ t('components.materialCreateWizard.creationPrompt') }}</span>
              </div>
              <div class="step-content">
                <div class="creation-mode-cards">
                  <div class="creation-mode-card" data-onboarding="material-creation-individual" @click="selectCreationMode('individual')">
                    <div class="mode-card-icon individual">
                      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                      </svg>
                    </div>
                    <div class="mode-card-content">
                      <div class="mode-card-head">
                        <span class="mode-card-title">{{ t('components.materialCreateWizard.modeIndividualTitle') }}</span>
                        <span class="mode-card-badge mode-card-badge--individual">{{ t('components.materialCreateWizard.badgeIndividual') }}</span>
                      </div>
                      <span class="mode-card-desc">{{ t('components.materialCreateWizard.modeIndividualDesc') }}</span>
                      <span class="mode-card-when">{{ t('components.materialCreateWizard.modeIndividualWhen') }}</span>
                    </div>
                  </div>
                  <div class="creation-mode-card" @click="selectCreationMode('physical_combo')">
                    <div class="mode-card-icon physical">
                      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <path d="M3 9h18M9 21V9"/>
                      </svg>
                    </div>
                    <div class="mode-card-content">
                      <div class="mode-card-head">
                        <span class="mode-card-title">{{ t('components.materialCreateWizard.modePhysicalComboTitle') }}</span>
                        <span class="mode-card-badge mode-card-badge--physical">{{ t('components.materialCreateWizard.badgePhysicalCombo') }}</span>
                      </div>
                      <span class="mode-card-desc">{{ t('components.materialCreateWizard.modePhysicalComboDesc') }}</span>
                      <span class="mode-card-when">{{ t('components.materialCreateWizard.modePhysicalComboWhen') }}</span>
                    </div>
                  </div>
                  <div class="creation-mode-card" @click="selectCreationMode('virtual_combo')">
                    <div class="mode-card-icon virtual">
                      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6l4 2"/>
                      </svg>
                    </div>
                    <div class="mode-card-content">
                      <div class="mode-card-head">
                        <span class="mode-card-title">{{ t('components.materialCreateWizard.modeVirtualComboTitle') }}</span>
                        <span class="mode-card-badge mode-card-badge--virtual">{{ t('components.materialCreateWizard.badgeVirtualCombo') }}</span>
                      </div>
                      <span class="mode-card-desc">{{ t('components.materialCreateWizard.modeVirtualComboDesc') }}</span>
                      <span class="mode-card-when">{{ t('components.materialCreateWizard.modeVirtualComboWhen') }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Step: Allgemeine Informationen (sichtbar wenn Modus gewählt); Banner + Vorlage innerhalb des Schritts -->
            <div v-if="!isAddBatchMode && creationMode" class="step-section" data-step="general" data-onboarding="material-wizard-general">
              <div class="step-header step-header--clickable" @click="toggleStep('general')">
                <span class="step-title">{{
                  creationMode === 'virtual_combo'
                    ? t('components.materialCreateWizard.stepDefineCombo')
                    : t('components.materialCreateWizard.stepGeneralInfo')
                }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('general') }">▾</span>
              </div>
              
              <div v-show="isStepOpen('general')" class="step-content">
                <!-- Modus-Banner (wenn Modus gewählt) -->
                <SelectedModeBanner
                  v-if="!isAddBatchMode && creationMode"
                  :creation-mode="creationMode"
                  :template-name="selectedTemplate?.name ?? null"
                  :template-manufacturer="selectedTemplate?.manufacturer ?? null"
                  :inventory-source-label="selectedContainerBatchContents?.container_label ?? null"
                  @reset="resetWizardForModeChange"
                />

                <!-- Hülle-Hinweis: Kombo wird als Entwurf angelegt, Stückliste/Optionen im Detail-Tab -->
                <div
                  v-if="creationMode === 'physical_combo' || creationMode === 'virtual_combo'"
                  class="combo-shell-hint"
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                  </svg>
                  <p>
                    {{ t('components.materialCreateWizard.comboShellHintDraft') }}
                    <strong>{{ t('components.materialCreateWizard.comboShellHintDetailTab') }}</strong>
                  </p>
                </div>

                <!-- Template-Auswahl (bei allen Modi möglich, wenn noch kein Template gewählt) -->
                <TemplatePickerSection
                  v-if="!isAddBatchMode && creationMode && !isFromTemplate && !isFromContainerBatchContents"
                  :search="templateSearch"
                  :show-dropdown="showTemplateDropdown"
                  :filtered-templates="filteredTemplateList"
                  :show-container-batch-picker="creationMode === 'physical_combo' || creationMode === 'virtual_combo'"
                  :container-batch-id="containerContentsBatchId"
                  :container-batches="containerBatches"
                  :is-loading-container-contents="isLoadingContainerContents"
                  :selected-container-contents="selectedContainerBatchContents"
                  @update:search="onTemplateSearchUpdate"
                  @focus="showTemplateDropdown = true; searchTemplates()"
                  @blur="hideTemplateDropdownDelayed"
                  @select="selectTemplate"
                  @update:container-batch-id="onContainerBatchIdForContentsChange"
                  @load-container-contents="loadContainerBatchContents"
                />

                <!-- Virtuelle Kombo: Name -->
                <div v-if="creationMode === 'virtual_combo'" class="virtual-combo-fields">
                  <div
                    v-if="isFromTemplate && selectedTemplate?.missing_required_components?.length && hasTemplateConfigurator"
                    class="configurator-missing-hint"
                  >
                    <p>{{ t('components.materialCreateWizard.configuratorMissingComponents') }}</p>
                    <ul>
                      <li v-for="mc in selectedTemplate!.missing_required_components!" :key="mc.component_type">
                        {{ mc.expected_name }}
                        <span class="configurator-missing-type">({{ mc.name }})</span>
                      </li>
                    </ul>
                  </div>
                  <MaterialNameInput
                    ref="articleNameInputRef"
                    :model-value="formData.name"
                    :label="t('components.materialCreateWizard.virtualComboNameLabel')"
                    :placeholder="t('components.materialCreateWizard.virtualComboNamePlaceholder')"
                    :is-checking-name="isCheckingName"
                    :name-exists="nameExists"
                    :show-suggestions="false"
                    :name-suggestions="[]"
                    @update:model-value="formData.name = $event"
                    @input="checkNameDebounced"
                    @focus="handleNameInputFocus"
                    @blur="handleNameInputBlur"
                  />
                </div>

                <!-- Einzelartikel-Modus mit Vorlage: Info statt Name-Eingabe -->
                <div v-else-if="isFromTemplate && creationMode === 'individual'" class="individual-mode-info">
                  <div class="info-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <circle cx="12" cy="12" r="10"/>
                      <line x1="12" y1="16" x2="12" y2="12"/>
                      <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <p>
                      {{ t('components.materialCreateWizard.fromTemplateInfo1') }}
                      {{ t('components.materialCreateWizard.fromTemplateInfo2') }}
                    </p>
                  </div>
                </div>

                <!-- Name-Eingabe (bei Kombo-Modi oder normalem Modus) -->
                <div
                  v-if="creationMode !== 'virtual_combo' && (!isFromTemplate || creationMode !== 'individual')"
                  data-onboarding="material-wizard-article-name"
                >
                  <MaterialNameInput
                    ref="articleNameInputRef"
                    :model-value="formData.name"
                    :label="isFromTemplate ? t('components.materialCreateWizard.comboNameLabel') : t('components.materialCreateWizard.articleNameLabel')"
                    :placeholder="isFromTemplate ? t('components.materialCreateWizard.comboNamePlaceholder') : t('components.materialCreateWizard.articleNamePlaceholder')"
                    :is-checking-name="isCheckingName"
                    :name-exists="nameExists"
                    :show-suggestions="showNameSuggestions"
                    :name-suggestions="nameSuggestions"
                    @update:model-value="formData.name = $event"
                    @input="checkNameDebounced"
                    @focus="handleNameInputFocus"
                    @blur="handleNameInputBlur"
                    @select-suggestion="selectNameSuggestion"
                  />
                </div>
                <!-- Slider-Toggles (nicht bei Vorlage) -->
                <div
                  v-if="!isFromTemplate || creationMode === 'individual'"
                  data-onboarding="material-wizard-type-toggles"
                >
                  <MaterialTypeToggles
                    :is-consumable="formData.is_consumable"
                    :is-food="formData.is_food"
                    @update:is-consumable="formData.is_consumable = $event"
                    @update:is-food="formData.is_food = $event"
                  />
                </div>
              </div>
            </div>

            <!-- Kategorie: Einzelartikel + Kombinationen (mit/ohne Vorlage); wählbar sobald Modus feststeht — bei Vorlage nach Vorlagenwahl -->
            <div
              v-if="
                !isAddBatchMode &&
                (creationMode === 'individual' ||
                  creationMode === 'physical_combo' ||
                  creationMode === 'virtual_combo') &&
                (!isFromTemplate || selectedTemplate)
              "
              class="step-section"
              data-step="category"
              data-onboarding="material-wizard-category"
            >
              <div class="step-header step-header--clickable" @click="toggleStep('category')">
                <span class="step-title">{{ t('components.materialCreateWizard.stepCategory') }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('category') }">▾</span>
              </div>
              
              <div v-show="isStepOpen('category')" class="step-content">
                <div
                  v-if="nameExists && duplicateNameMaterial && !isAddBatchMode"
                  class="name-duplicate-hint"
                >
                  <p class="name-duplicate-hint__text">
                    {{ t('components.materialCreateWizard.dupHintA') }}<strong>{{
                      t('components.materialCreateWizard.dupHintBold')
                    }}</strong>{{ t('components.materialCreateWizard.dupHintB') }}
                  </p>
                  <div class="name-duplicate-hint__actions">
                    <button type="button" class="btn-secondary btn-sm" @click="selectNameSuggestion(duplicateNameMaterial)">
                      {{ t('components.materialCreateWizard.btnAddStock') }}
                    </button>
                    <RouterLink
                      class="btn-secondary btn-sm name-duplicate-hint__link"
                      :to="`/${departmentId}/materials/${duplicateNameMaterial.id}`"
                    >
                      {{ t('components.materialCreateWizard.btnToMaterial') }}
                    </RouterLink>
                  </div>
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialCreateWizard.labelPickCategory') }}</label>
                  <div ref="categoryAutocompleteRef" class="autocomplete-wrapper">
                    <input 
                      v-model="categorySearch" 
                      type="text" 
                      class="form-input"
                      :placeholder="t('components.materialCreateWizard.categorySearchPlaceholder')"
                      @input="searchCategories"
                      @focus="onCategoryInputFocus"
                      @blur="hideCategoryDropdownDelayed"
                      @keydown="onCategorySearchKeydown"
                    />
                    <button
                      type="button"
                      class="add-inline-btn"
                      @click="openAddCategoryModal"
                      :title="t('components.materialCreateWizard.addCategoryTitle')"
                    >+</button>
                  </div>
                  <p v-if="selectedCategory" class="selected-address">
                    ✓ {{ getCategoryPath(selectedCategory) }}
                    <button type="button" class="clear-selection" @click="clearCategory">×</button>
                  </p>
                </div>
              </div>
            </div>

            <!-- Bestandsverfolgung — bei Esswaren übersprungen (immer Massenartikel) -->
            <div
              v-if="!isAddBatchMode && !isFromTemplate && creationMode === 'individual' && formData.material_type === 'physical' && !formData.is_food && formData.category_id"
              class="step-section"
              data-step="tracking"
            >
              <div class="step-header step-header--clickable" @click="toggleStep('tracking')">
                <span class="step-title">{{ t('components.materialCreateWizard.stepTracking') }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('tracking') }">▾</span>
              </div>

              <div v-show="isStepOpen('tracking')" class="step-content">
                <div class="tracking-options" data-onboarding="material-wizard-tracking">
                  <button
                    :class="['tracking-option', { active: formData.tracking_type === 'serialized' }]"
                    type="button"
                    @click="selectTrackingType('serialized')"
                  >
                    <div class="tracking-icon">
                      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                      </svg>
                    </div>
                    <div class="tracking-text">
                      <span class="tracking-name">{{ t('components.materialCreateWizard.trackingSerialized') }}</span>
                      <span class="tracking-desc">{{ t('components.materialCreateWizard.trackingSerializedDesc') }}</span>
                    </div>
                  </button>

                  <button
                    :class="['tracking-option', { active: formData.tracking_type === 'bulk' }]"
                    type="button"
                    @click="selectTrackingType('bulk')"
                  >
                    <div class="tracking-icon">
                      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                      </svg>
                    </div>
                    <div class="tracking-text">
                      <span class="tracking-name">{{ t('components.materialCreateWizard.trackingBulk') }}</span>
                      <span class="tracking-desc">{{ t('components.materialCreateWizard.trackingBulkDesc') }}</span>
                    </div>
                  </button>
                </div>
              </div>
            </div>

            <!-- ========== TEMPLATE-MODUS: Komponenten-Eingabe ========== -->
            <div v-if="((isFromTemplate && selectedTemplate) || (isFromContainerBatchContents && selectedContainerBatchContents)) && creationMode && (creationMode === 'individual' || (formData.name && !nameExists))" class="step-section" data-step="template_components">
              <div class="step-header step-header--clickable" @click="toggleStep('template_components')">
                <span class="step-title">{{ t('components.materialCreateWizard.stepComponents') }}</span>
                <span class="step-badge">{{ t('components.materialCreateWizard.partsBadge', { n: componentInputs.length }) }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('template_components') }">▾</span>
              </div>

              <div v-show="isStepOpen('template_components')" class="step-content">
                <div class="component-inputs-list">
                  <div
                    v-for="(ci, idx) in componentInputs"
                    :key="ci.component_type + idx"
                    class="component-input-card"
                    :class="{ 'is-optional': ci.is_optional }"
                  >
                    <div class="comp-card-header">
                      <div class="comp-card-info">
                        <span class="comp-card-name">{{ ci.name }}</span>
                        <span class="comp-card-meta">
                          <span v-if="ci.tracking === 'serialized'">{{
                            t('common.serialNumber')
                          }}</span>
                          <span v-else>{{
                            t('components.materialCreateWizard.compBulkMetaPcs', { n: templateBulkMetaQty(ci) })
                          }}</span>
                          <span v-if="ci.is_optional" class="comp-optional-badge">{{
                            t('common.optional')
                          }}</span>
                          <span v-if="ci.component_source === 'self_provided'" class="comp-optional-badge comp-selfprovided-badge">{{
                            t('components.materialDetail.selfProvidedShortBadge')
                          }}</span>
                        </span>
                      </div>
                      <!-- Virtuelle Kombo: Keine Auswahl nötig, wird bei Ausgabe zugewiesen -->
                      <div v-if="creationMode === 'virtual_combo'" class="comp-card-mode">
                        <span class="comp-mode-info">{{ t('components.materialCreateWizard.compAssignAtIssue') }}</span>
                      </div>
                      <!-- Selbst mitbringen: kein Lager, Hinweis für Aktivitäts-Besteller -->
                      <div v-else-if="ci.component_source === 'self_provided'" class="comp-card-mode">
                        <span class="comp-mode-info">{{ t('components.materialCreateWizard.compSelfProvidedMode') }}</span>
                      </div>
                      <!-- Andere Modi: Neu/Bestand Toggle -->
                      <div v-else class="comp-card-mode">
                        <button
                          type="button"
                          :class="['comp-mode-btn', { active: ci.mode === 'new' }]"
                          @click="ci.mode = 'new'"
                        >{{ t('components.materialCreateWizard.compNewBuy') }}</button>
                        <button
                          type="button"
                          :class="['comp-mode-btn', { active: ci.mode === 'existing' }]"
                          @click="ci.mode = 'existing'"
                        >{{ t('components.materialCreateWizard.compFromStock') }}</button>
                      </div>
                    </div>

                    <div v-if="ci._match_state && creationMode !== 'virtual_combo' && ci.component_source !== 'self_provided'" class="comp-resolve-status">
                      <p v-if="ci._match_state === 'found' && ci._selectedMaterial" class="comp-resolve comp-resolve--found">
                        {{ t('components.materialCreateWizard.compResolveFound', { name: ci._selectedMaterial.name }) }}
                      </p>
                      <p v-else-if="ci._match_state === 'missing'" class="comp-resolve comp-resolve--missing">
                        {{ t('components.materialCreateWizard.compResolveMissing', { name: ci._expected_name || ci.name }) }}
                      </p>
                      <div v-else-if="ci._match_state === 'ambiguous'" class="comp-resolve comp-resolve--ambiguous">
                        <p>{{ t('components.materialCreateWizard.compResolveAmbiguous') }}</p>
                        <div v-if="ci._candidates?.length" class="comp-candidate-list">
                          <button
                            v-for="candidate in ci._candidates"
                            :key="candidate.id"
                            type="button"
                            class="btn-secondary comp-candidate-btn"
                            @click="pickComponentCandidate(ci, candidate)"
                          >
                            {{ candidate.name }}
                          </button>
                        </div>
                      </div>
                    </div>

                    <div class="comp-card-body">

                      <!-- Virtual Combo: Nur Info, keine Eingabe -->
                      <div v-if="creationMode === 'virtual_combo'" class="comp-virtual-info">
                        <span class="comp-virtual-text">{{ t('components.materialCreateWizard.compVirtualAssignDesc') }}</span>
                      </div>

                      <!-- Selbst mitbringen: Hinweis, keine Lagerzuweisung -->
                      <div v-else-if="ci.component_source === 'self_provided'" class="comp-virtual-info comp-selfprovided-info">
                        <span class="comp-virtual-text">{{ t('components.materialCreateWizard.compSelfProvidedDesc', { n: ci.qty || ci.required_qty }) }}</span>
                      </div>

                      <!-- ══════ SERIALISIERT ══════ -->

                      <!-- Serialisiert: Neu kaufen → SN + Preis eingeben -->
                      <template v-else-if="creationMode !== 'virtual_combo' && ci.tracking === 'serialized' && ci.mode === 'new'">
                        <div class="form-row">
                          <div class="form-group">
                            <label>{{ t('common.serialNumber') }}</label>
                            <input
                              v-model="ci.serial_number"
                              type="text"
                              class="form-input"
                              :placeholder="
                                t('components.materialCreateWizard.phSerialExample', {
                                  ex: ci.component_type.toUpperCase().substring(0, 3) + '-001',
                                })
                              "
                            />
                          </div>
                          <div class="form-group">
                            <label>{{ t('components.materialCreateWizard.purchasePriceChfPerPc') }}</label>
                            <div class="price-input">
                              <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                              <input
                                v-model="ci.unit_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-input"
                                :placeholder="t('components.materialCreateWizard.phPriceZero')"
                              />
                            </div>
                          </div>
                        </div>
                      </template>

                      <!-- Serialisiert: Aus Lager → Material suchen + SN wählen -->
                      <template v-else-if="creationMode !== 'virtual_combo' && ci.tracking === 'serialized' && ci.mode === 'existing'">
                        <div class="comp-existing-search">
                          <div v-if="!ci._selectedMaterial" class="form-group">
                            <GlobalSearchInput
                              v-model="ci._materialSearch"
                              mode="inline"
                              pick-on-select
                              teleport-dropdown
                              :department-id="departmentId"
                              default-type="material"
                              :placeholder="t('components.materialCreateWizard.searchArticle')"
                              :pick-empty-text="t('components.materialCreateWizard.compSearchNoMatches', { query: ci._materialSearch || '' })"
                              class="material-wizard-comp-search"
                              @select="onCompMaterialSearchSelect(ci, $event)"
                            />
                          </div>

                          <!-- Gewähltes Material + SN-Auswahl -->
                          <div v-if="ci.material_id && ci._selectedMaterial" class="comp-selected-material">
                            <div class="comp-selected-header">
                              <span class="comp-selected-check">✓</span>
                              <span class="comp-selected-name">{{ ci._selectedMaterial.name }}</span>
                              <button type="button" class="clear-selection" @click="clearExistingMaterial(ci)">×</button>
                            </div>

                            <div v-if="ci._availableBatches?.length > 0" class="comp-batch-select">
                              <label>{{ t('components.materialCreateWizard.labelPickSerial') }}</label>
                              <select v-model="ci.batch_id" class="form-select">
                                <option value="">{{ t('components.materialCreateWizard.selectSerialPlaceholder') }}</option>
                                <option
                                  v-for="batch in ci._availableBatches"
                                  :key="batch.id"
                                  :value="batch.id"
                                >
                                  {{ t('components.materialCreateWizard.snDisplay') }}
                                  {{ batch.serial_number || batch.label || batch.id }}
                                </option>
                              </select>
                            </div>
                            <div v-else class="comp-no-batches">
                              {{ t('components.materialCreateWizard.noFreeSerialsInStock') }}
                            </div>
                          </div>
                        </div>
                      </template>

                      <!-- ══════ BULK (Massenartikel) ══════ -->

                      <!-- Bulk: Neu kaufen → nur Menge + Preis (keine SN: die gibt es nur bei serialisierten Artikeln) -->
                      <template v-else-if="creationMode !== 'virtual_combo' && ci.tracking === 'bulk' && ci.mode === 'new'">
                        <div class="form-row">
                          <div class="form-group">
                            <label>{{ t('components.materialCreateWizard.qtyNewPurchase') }}</label>
                            <input
                              v-model.number="ci.qty"
                              type="number"
                              :min="ci.is_optional ? 0 : 1"
                              class="form-input"
                              @update:modelValue="() => normalizeBulkQty(ci)"
                            />
                          </div>
                          <div class="form-group">
                            <label>{{ t('components.materialCreateWizard.purchasePriceChfPerPc') }}</label>
                            <div class="price-input">
                              <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                              <input
                                v-model="ci.unit_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-input"
                                :placeholder="t('components.materialCreateWizard.phPriceZero')"
                              />
                            </div>
                          </div>
                        </div>
                        <p v-if="ci.is_optional && !ci.qty" class="form-hint optional-bulk-zero-hint">
                          {{ t('components.materialCreateWizard.optionalBulkZeroHint') }}
                        </p>
                        <p v-else-if="ci.qty > 0" class="comp-bulk-info">{{
                          t('components.materialCreateWizard.bulkInfoAddedToStock')
                        }}</p>
                      </template>

                      <!-- Bulk: Aus Lager → Material wählen + Menge, kein Batch nötig -->
                      <template v-else-if="creationMode !== 'virtual_combo' && ci.tracking === 'bulk' && ci.mode === 'existing'">
                        <div class="comp-bulk-existing">
                          <div v-if="!ci._selectedMaterial" class="form-group">
                            <GlobalSearchInput
                              v-model="ci._materialSearch"
                              mode="inline"
                              pick-on-select
                              teleport-dropdown
                              :department-id="departmentId"
                              default-type="material"
                              :placeholder="t('components.materialCreateWizard.labelWhichStockArticle')"
                              :pick-empty-text="t('components.materialCreateWizard.compSearchNoMatches', { query: ci._materialSearch || '' })"
                              class="material-wizard-comp-search"
                              @select="onCompMaterialSearchSelect(ci, $event)"
                            />
                          </div>

                          <!-- Material gewählt → Menge + Bestand anzeigen -->
                          <div v-else>
                            <div class="comp-selected-material">
                              <div class="comp-selected-header">
                                <span class="comp-selected-check">✓</span>
                                <span class="comp-selected-name">{{ ci._selectedMaterial.name }}</span>
                                <span class="comp-selected-stock">{{
                                  t('components.materialCreateWizard.stockFree', {
                                    n: ci._selectedMaterial.free_stock ?? ci._selectedMaterial.total_stock,
                                  })
                                }}</span>
                                <button type="button" class="clear-selection" @click="clearExistingMaterial(ci)">×</button>
                              </div>
                            </div>
                            <div class="form-row" style="margin-top: 8px;">
                              <div class="form-group">
                                <label>{{ t('components.materialCreateWizard.labelQtyAssign') }}</label>
                                <input
                                  v-model.number="ci.qty"
                                  type="number"
                                  :min="ci.is_optional ? 0 : 1"
                                  class="form-input"
                                  @update:modelValue="() => normalizeBulkQty(ci)"
                                />
                              </div>
                              <div class="form-group comp-stock-info">
                                <label>&nbsp;</label>
                                <div class="comp-stock-display">
                                  <span class="comp-stock-value" :class="{ 'is-low': (ci._selectedMaterial.free_stock ?? ci._selectedMaterial.total_stock) < ci.qty }">
                                    {{
                                      t('components.materialCreateWizard.stockFreeVsNeeded', {
                                        free: ci._selectedMaterial.free_stock ?? ci._selectedMaterial.total_stock,
                                        need: ci.qty,
                                      })
                                    }}
                                  </span>
                                </div>
                              </div>
                            </div>
                            <p v-if="(ci._selectedMaterial.free_stock ?? ci._selectedMaterial.total_stock) < ci.qty" class="comp-stock-warning">
                              {{
                                t('components.materialCreateWizard.stockShortByPcs', {
                                  n: ci.qty - (ci._selectedMaterial.free_stock ?? ci._selectedMaterial.total_stock),
                                })
                              }}
                            </p>
                            <p v-else class="comp-bulk-info">{{ t('components.materialCreateWizard.bulkInfoFromStockToTent') }}</p>
                          </div>
                        </div>
                      </template>

                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ========== TEMPLATE-MODUS: Zelt-Details (nur bei Kombo-Modi) ========== -->
            <div v-if="isFromTemplate && selectedTemplate && creationMode && creationMode !== 'individual' && formData.name && !nameExists" class="step-section" data-step="template_tent">
              <div class="step-header step-header--clickable" @click="toggleStep('template_tent')">
                <span class="step-title">{{ t('components.materialCreateWizard.stepTentDetails') }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('template_tent') }">▾</span>
              </div>

              <div v-show="isStepOpen('template_tent')" class="step-content">
                <div class="form-row">
                  <div class="form-group">
                    <label>{{ t('components.materialCreateWizard.labelTentType') }}</label>
                    <select v-model="tentForm.tent_type" class="form-select">
                      <option value="">{{ t('components.materialCreateWizard.selectChooseDash') }}</option>
                      <option value="gruppenzelt">{{ t('components.materialCreateWizard.tentTypeGroup') }}</option>
                      <option value="sonstiges">{{ t('components.materialCreateWizard.tentTypeOther') }}</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>{{ t('components.materialCreateWizard.labelCapacityPersons') }}</label>
                    <input
                      v-model.number="tentForm.tent_capacity"
                      type="number"
                      min="1"
                      class="form-input"
                      :placeholder="t('components.materialCreateWizard.phCapacityExample')"
                    />
                  </div>
                </div>

              </div>
            </div>

            <!-- ========== TEMPLATE ODER COMBO AUS KISTE: Kauf & Lagerung (nach Komponenten) ========== -->
            <div v-if="showTemplatePurchaseStep" class="step-section" data-step="template_purchase">
              <div class="step-header step-header--clickable" @click="toggleStep('template_purchase')">
                <span class="step-title">{{ t('components.materialCreateWizard.stepPurchaseStorage') }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('template_purchase') }">▾</span>
              </div>

              <div v-show="isStepOpen('template_purchase')" class="step-content">
                <div class="form-group">
                  <label>{{ t('components.materialCreateWizard.labelStorageSite') }}</label>
                  <div class="select-with-add">
                    <select v-model="formData.storage_address_id" class="form-select">
                      <option value="">{{ t('components.materialCreateWizard.selectStorageSite') }}</option>
                      <option v-for="addr in storageAddresses" :key="addr.id" :value="addr.id">
                        {{ addr.name || addr.street_line }}
                      </option>
                    </select>
                    <button
                      type="button"
                      class="add-btn"
                      @click="openAddStorageModal"
                      :title="t('components.materialCreateWizard.addStorageSiteTitle')"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                      </svg>
                    </button>
                  </div>
                  <template v-if="creationMode === 'individual'">
                    <div class="autocomplete-wrapper">
                      <input
                        v-model="formData.location_rack"
                        type="text"
                        class="form-input"
                        :placeholder="t('components.materialCreateWizard.phRackFreestyle')"
                        @input="searchRackCategories"
                        @focus="showRackDropdown = true; searchRackCategories()"
                        @blur="hideRackDropdownDelayed"
                      />
                      <button
                        type="button"
                        class="add-inline-btn"
                        @click="addRackCategory"
                        :title="t('components.materialCreateWizard.addRackUnderSiteTitle')"
                      >+</button>
                      <div v-if="showRackDropdown" class="autocomplete-dropdown">
                        <div
                          v-for="rack in filteredRackOptions"
                          :key="rack.id"
                          class="autocomplete-item"
                          @mousedown="selectRackCategory(rack)"
                        >
                          <span class="item-name">{{ rack.name }}</span>
                        </div>
                        <div
                          v-if="filteredRackOptions.length === 0 && formData.location_rack.trim().length >= 2"
                          class="autocomplete-item create-new"
                          @mousedown="addRackCategory"
                        >
                          <span class="item-name">{{
                            t('components.materialCreateWizard.createRackNamed', { name: formData.location_rack.trim() })
                          }}</span>
                        </div>
                      </div>
                    </div>
                    <input
                      v-model="formData.location_slot"
                      type="text"
                      class="form-input"
                      :placeholder="t('components.materialCreateWizard.phSlotFreestyle')"
                    />
                    <p class="field-hint">{{ t('components.materialCreateWizard.hintRackSlotFreestyle') }}</p>
                  </template>

                  <div v-if="creationMode === 'physical_combo'" class="form-group physical-combo-main-storage">
                    <label class="form-label-sm">{{ t('components.materialCreateWizard.labelMainStorageCombo') }}</label>
                    <p v-if="hasComboLinkedContainerFromComponent" class="field-hint">
                      {{ t('components.materialCreateWizard.comboLinkedContainerStorageHint') }}
                    </p>
                    <p v-else class="field-hint">
                      {{ t('components.materialCreateWizard.physicalComboStorageHintA') }}<strong>{{
                        t('components.materialCreateWizard.wordOr')
                      }}</strong>{{ t('components.materialCreateWizard.physicalComboStorageHintB') }}
                    </p>
                    <div v-if="!hasComboLinkedContainerFromComponent" class="stock-location-mode mb-2">
                      <div class="lagerung-switch" role="tablist">
                        <button
                          type="button"
                          class="lagerung-btn"
                          :class="{ active: formData.stock_location_mode === 'slot' }"
                          @click="formData.stock_location_mode = 'slot'; formData.stock_container_batch_id = ''"
                        >
                          {{ t('components.materialCreateWizard.tabGestellFach') }}
                        </button>
                        <button
                          type="button"
                          class="lagerung-btn"
                          :class="{ active: formData.stock_location_mode === 'kiste' }"
                          @click="formData.stock_location_mode = 'kiste'; formData.rack_id = ''; formData.slot_id = ''; formData.location_rack = ''; formData.location_slot = ''"
                        >
                          {{ t('components.materialCreateWizard.tabKisteTasche') }}
                        </button>
                      </div>
                    </div>
                    <template v-if="formData.stock_location_mode === 'slot' || hasComboLinkedContainerFromComponent">
                      <StorageLocationPicker
                        variant="compact"
                        class="material-wizard-storage-picker"
                        :rack-id="String(formData.rack_id || '')"
                        :slot-id="String(formData.slot_id || '')"
                        :racks="storageRacks"
                        :slot-list="slotsForPhysicalComboGestellFach"
                        :rack-label-formatter="formatRackOptionLabel"
                        :rack-option-title-formatter="(r) => rackPreviewTitles[r.id] || ''"
                        :slot-label-formatter="(slot) => formatSlotOptionLabel(String(formData.rack_id || ''), slot)"
                        :slot-option-title-formatter="(s) => slotPreviewTitles[`${String(formData.rack_id || '')}:${String(s.id)}`] || ''"
                        :show-empty-slot-hint="true"
                        :rack-label="t('components.materialCreateWizard.rackLabel')"
                        :slot-label="t('components.materialCreateWizard.slotLabel')"
                        :rack-placeholder="t('components.materialCreateWizard.rackPlaceholderDash')"
                        :slot-placeholder="t('components.materialCreateWizard.slotPlaceholderDash')"
                        @rackListMouseenter="prefetchVisibleRackPreviews(storageRacks)"
                        @slotListMouseenter="prefetchSlotPreviewsForRack(String(formData.rack_id || ''))"
                        @update:rackId="onStorageLocationRackUpdate"
                        @update:slotId="(v) => (formData.slot_id = String(v ?? ''))"
                      />
                    </template>
                    <template v-else>
                      <select
                        v-model="formData.stock_container_batch_id"
                        class="form-select"
                        @mouseenter="prefetchContainerPreviews()"
                        :title="getContainerPreviewTitle(formData.stock_container_batch_id)"
                      >
                        <option value="">{{ t('components.materialCreateWizard.selectPickBoxDash') }}</option>
                        <option
                          v-for="cb in containerBatches"
                          :key="cb.id"
                          :value="cb.id"
                          :title="formatContainerBatchOptionFullLabel(cb)"
                        >
                          {{ formatContainerBatchOptionFullLabel(cb) }}
                        </option>
                      </select>
                    </template>
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label>{{ t('components.materialCreateWizard.labelPurchaseDate') }}</label>
                    <input
                      v-model="formData.purchase_date"
                      type="date"
                      class="form-input"
                    />
                  </div>
                  <div class="form-group">
                    <label>{{ t('components.materialCreateWizard.labelInvoiceNumber') }}</label>
                    <input
                      v-model="formData.invoice_number"
                      type="text"
                      class="form-input"
                      :placeholder="t('common.optional')"
                    />
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label>{{ t('common.manufacturer') }}</label>
                    <div class="autocomplete-wrapper">
                      <input
                        v-model="manufacturerSearch"
                        type="text"
                        class="form-input"
                        :placeholder="t('components.materialCreateWizard.phSearchManufacturer')"
                        @input="searchManufacturers"
                        @focus="showManufacturerDropdown = true"
                        @blur="hideManufacturerDropdownDelayed"
                      />
                      <div v-if="showManufacturerDropdown && manufacturerSearch.length >= 2" class="autocomplete-dropdown">
                        <div
                          v-for="addr in filteredManufacturers"
                          :key="addr.id"
                          class="autocomplete-item"
                          @mousedown="selectManufacturer(addr)"
                        >
                          <span class="item-name">{{ addr.name || addr.company }}</span>
                          <span class="item-city">{{ addr.city }}</span>
                        </div>
                      </div>
                    </div>
                    <p v-if="selectedManufacturer" class="selected-address">
                      {{ selectedManufacturer.name || selectedManufacturer.company }}
                      <button type="button" class="clear-selection" @click="clearManufacturer">×</button>
                    </p>
                  </div>
                  <div class="form-group">
                    <label>{{ t('components.materialCreateWizard.labelPurchasedFrom') }}</label>
                    <div class="autocomplete-wrapper">
                      <input
                        v-model="supplierSearch"
                        type="text"
                        class="form-input"
                        :placeholder="t('components.materialCreateWizard.phSearchSupplier')"
                        @input="searchSuppliers"
                        @focus="showSupplierDropdown = true"
                        @blur="hideSupplierDropdownDelayed"
                      />
                      <div v-if="showSupplierDropdown && supplierSearch.length >= 2" class="autocomplete-dropdown">
                        <div
                          v-for="addr in filteredSuppliers"
                          :key="addr.id"
                          class="autocomplete-item"
                          @mousedown="selectSupplier(addr)"
                        >
                          <span class="item-name">{{ addr.name || addr.company }}</span>
                          <span class="item-city">{{ addr.city }}</span>
                        </div>
                      </div>
                    </div>
                    <p v-if="selectedSupplier" class="selected-address">
                      {{ selectedSupplier.name || selectedSupplier.company }}
                      <button type="button" class="clear-selection" @click="clearSupplier">×</button>
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Manuelle Kombi (ohne Vorlage/Kiste): Artikel suchen — nicht bei Vorlage/Kisten-Inhalt (das ist „Komponenten“) -->
            <div
              v-if="
                !isAddBatchMode &&
                !isFromTemplate &&
                !isFromContainerBatchContents &&
                (formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo') &&
                formData.name.trim() &&
                !nameExists &&
                formData.category_id
              "
              class="step-section"
              data-step="combo_articles"
            >
              <div class="step-header step-header--clickable" @click="toggleStep('combo_articles')">
                <span class="step-title">{{ t('components.materialCreateWizard.stepComboArticlesTitle') }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('combo_articles') }">▾</span>
              </div>
              
              <div v-show="isStepOpen('combo_articles')" class="step-content">
                <p class="step-hint">{{ t('components.materialCreateWizard.comboArticlesMinHint') }}</p>
                
                <!-- Physische Kombo: wie Vorlagen-Komponenten (Neu kaufen / Aus Lager) -->
                <div v-if="formData.material_type === 'physical_combo'" class="component-inputs-list">
                  <div
                    v-for="(mat, index) in selectedComboMaterials"
                    :key="mat.draftId"
                    class="component-input-card"
                  >
                    <div class="comp-card-header">
                      <div class="comp-card-info">
                        <span class="comp-card-name">{{
                          mat.name.trim() || t('components.materialCreateWizard.comboArticleUnnamed')
                        }}</span>
                        <span class="comp-card-meta">
                          <span v-if="mat.tracking === 'serialized'">{{ t('common.serialNumber') }}</span>
                          <span v-else>{{ t('components.materialCreateWizard.compBulkMetaPcs', { n: mat.qty }) }}</span>
                        </span>
                      </div>
                      <div class="comp-card-mode">
                        <button
                          type="button"
                          :class="['comp-mode-btn', { active: mat.mode === 'new' }]"
                          @click="setComboArticleMode(mat, 'new')"
                        >{{ t('components.materialCreateWizard.compNewBuy') }}</button>
                        <button
                          type="button"
                          :class="['comp-mode-btn', { active: mat.mode === 'existing' }]"
                          @click="setComboArticleMode(mat, 'existing')"
                        >{{ t('components.materialCreateWizard.compFromStock') }}</button>
                      </div>
                      <button type="button" class="combo-remove" @click="removeComboMaterial(index)">×</button>
                    </div>

                    <div
                      v-if="mat.mode === 'existing' && !mat.id && mat.name.trim()"
                      class="comp-resolve-status"
                    >
                      <p class="comp-resolve comp-resolve--missing">
                        {{ t('components.materialCreateWizard.compResolveMissing', { name: mat.name.trim() }) }}
                      </p>
                    </div>

                    <div class="comp-card-body">
                      <template v-if="mat.mode === 'new'">
                        <MaterialNameInput
                          v-if="!mat.id"
                          :model-value="mat.name"
                          :label="t('components.materialCreateWizard.articleNameLabel')"
                          :placeholder="t('components.materialCreateWizard.articleNamePlaceholder')"
                          :is-checking-name="!!mat._isCheckingName"
                          :name-exists="!!mat._nameExists"
                          :show-suggestions="!!mat._showNameSuggestions"
                          :name-suggestions="mat._nameSuggestions || []"
                          @update:model-value="mat.name = $event"
                          @input="checkComboArticleNameDebounced(mat)"
                          @focus="handleComboArticleNameFocus(mat)"
                          @blur="handleComboArticleNameBlur(mat)"
                          @select-suggestion="selectComboArticleNameSuggestion(mat, $event)"
                        />
                        <div v-if="mat.id && mat.mode === 'new'" class="comp-existing-new-batch">
                          <p class="comp-existing-new-batch__text">
                            {{
                              t('components.materialCreateWizard.comboArticleExistingNewBatchBanner', {
                                name: mat.name,
                                tracking: comboArticleTrackingLabel(mat.tracking),
                              })
                            }}
                          </p>
                          <button
                            type="button"
                            class="btn-secondary btn-sm"
                            @click="clearComboArticleExistingLink(mat)"
                          >
                            {{ t('components.materialCreateWizard.comboArticleClearExistingLink') }}
                          </button>
                        </div>
                        <div
                          v-if="!mat.id && mat._nameExists && mat._duplicateMaterial"
                          class="name-duplicate-hint name-duplicate-hint--compact"
                        >
                          <p class="name-duplicate-hint__text">
                            {{
                              t('components.materialCreateWizard.comboArticleDupHint', {
                                name: mat._duplicateMaterial.name,
                                tracking: comboArticleTrackingLabel(
                                  mat._duplicateMaterial.tracking_type === 'serialized'
                                    ? 'serialized'
                                    : 'bulk',
                                ),
                              })
                            }}
                          </p>
                          <div class="name-duplicate-hint__actions">
                            <button
                              type="button"
                              class="btn-secondary btn-sm"
                              @click="useExistingComboArticleForNewBatch(mat, mat._duplicateMaterial)"
                            >
                              {{ t('components.materialCreateWizard.comboArticleUseExistingNewBatch') }}
                            </button>
                            <RouterLink
                              class="btn-secondary btn-sm name-duplicate-hint__link"
                              :to="`/${departmentId}/materials/${mat._duplicateMaterial.id}`"
                            >
                              {{ t('components.materialCreateWizard.btnToMaterial') }}
                            </RouterLink>
                          </div>
                        </div>
                        <div v-if="!mat.id" class="form-group">
                          <label>{{ t('components.materialCreateWizard.labelTracking') }}</label>
                          <div class="comp-tracking-toggle">
                            <button
                              type="button"
                              :class="['comp-mode-btn', { active: mat.tracking === 'serialized' }]"
                              @click="setComboArticleTracking(mat, 'serialized')"
                            >{{ t('components.materialCreateWizard.trackingSerializedShort') }}</button>
                            <button
                              type="button"
                              :class="['comp-mode-btn', { active: mat.tracking === 'bulk' }]"
                              @click="setComboArticleTracking(mat, 'bulk')"
                            >{{ t('components.materialCreateWizard.trackingBulkShort') }}</button>
                          </div>
                        </div>

                        <template v-if="mat.tracking === 'serialized'">
                        <div class="form-row">
                          <div class="form-group">
                            <label>{{ t('common.serialNumber') }}</label>
                            <input
                              v-model="mat.serial_number"
                              type="text"
                              class="form-input"
                                :placeholder="
                                  t('components.materialCreateWizard.phSerialExample', { ex: 'SN-001' })
                                "
                            />
                          </div>
                          <div class="form-group">
                            <label>{{ t('components.materialCreateWizard.purchasePriceChfPerPc') }}</label>
                            <div class="price-input">
                              <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                              <input
                                v-model="mat.unit_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-input"
                                :placeholder="t('components.materialCreateWizard.phPriceZero')"
                              />
                            </div>
                          </div>
                        </div>
                      </template>

                        <template v-else>
                        <div class="form-row">
                          <div class="form-group">
                            <label>{{ t('components.materialCreateWizard.qtyNewPurchase') }}</label>
                            <input v-model.number="mat.qty" type="number" min="1" class="form-input" />
                          </div>
                          <div class="form-group">
                            <label>{{ t('components.materialCreateWizard.purchasePriceChfPerPc') }}</label>
                            <div class="price-input">
                              <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                              <input
                                v-model="mat.unit_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-input"
                                :placeholder="t('components.materialCreateWizard.phPriceZero')"
                              />
                            </div>
                          </div>
                        </div>
                        <p class="comp-bulk-info">{{ t('components.materialCreateWizard.bulkInfoAddedToStock') }}</p>
                        </template>

                        <div
                          v-if="canMarkComboArticleAsLinkedContainer(mat)"
                          class="form-row mb-2"
                        >
                          <label class="checkbox-label material-wizard-container-flag">
                            <input
                              type="checkbox"
                              :checked="mat.is_linked_container"
                              @change="setComboArticleLinkedContainer(mat, ($event.target as HTMLInputElement).checked)"
                            />
                            <span>{{ t('components.materialCreateWizard.comboLinkedContainerCheckbox') }}</span>
                          </label>
                        </div>
                      </template>

                      <template v-else>
                        <div v-if="!mat.id" class="comp-existing-search">
                          <div class="form-group">
                            <GlobalSearchInput
                              v-model="mat._materialSearch"
                              mode="inline"
                              pick-on-select
                              teleport-dropdown
                              :department-id="departmentId"
                              default-type="material"
                              :placeholder="t('components.materialCreateWizard.searchArticle')"
                              :pick-empty-text="
                                t('components.materialCreateWizard.compSearchNoMatches', {
                                  query: mat._materialSearch || '',
                                })
                              "
                              class="material-wizard-comp-search"
                              @select="onComboArticleSearchSelect(mat, $event)"
                            />
                          </div>
                        </div>

                        <div v-if="mat.id" class="comp-selected-material">
                          <div class="comp-selected-header">
                            <span class="comp-selected-check">✓</span>
                            <span class="comp-selected-name">{{ mat.name }}</span>
                            <button type="button" class="clear-selection" @click="clearComboArticleSelection(mat)">×</button>
                          </div>
                        </div>

                        <template v-if="mat.id && mat.tracking === 'serialized'">
                          <div v-if="mat._availableBatches?.length" class="comp-batch-select">
                            <label>{{ t('components.materialCreateWizard.labelPickSerial') }}</label>
                            <select v-model="mat.batch_id" class="form-select">
                              <option value="">{{ t('components.materialCreateWizard.selectSerialPlaceholder') }}</option>
                              <option
                                v-for="batch in mat._availableBatches"
                                :key="batch.id"
                                :value="batch.id"
                              >
                                {{ t('components.materialCreateWizard.snDisplay') }}
                                {{ batch.serial_number || batch.label || batch.id }}
                              </option>
                            </select>
                          </div>
                          <div v-else class="comp-no-batches">
                            {{ t('components.materialCreateWizard.noFreeSerialsInStock') }}
                          </div>
                          <div
                            v-if="canMarkComboArticleAsLinkedContainer(mat)"
                            class="form-row mb-2"
                          >
                            <label class="checkbox-label material-wizard-container-flag">
                              <input
                                type="checkbox"
                                :checked="mat.is_linked_container"
                                :disabled="!mat.batch_id"
                                @change="setComboArticleLinkedContainer(mat, ($event.target as HTMLInputElement).checked)"
                              />
                              <span>{{ t('components.materialCreateWizard.comboLinkedContainerCheckbox') }}</span>
                            </label>
                          </div>
                        </template>

                        <template v-else-if="mat.id && mat.tracking === 'bulk'">
                          <div class="form-row" style="margin-top: 8px;">
                          <div class="form-group">
                            <label>{{ t('components.materialCreateWizard.labelQtyAssign') }}</label>
                            <input
                              v-model.number="mat.qty"
                              type="number"
                              min="1"
                              :max="mat.free_stock ?? mat.total_stock"
                              class="form-input"
                            />
                          </div>
                          <div class="form-group comp-stock-info">
                            <label>&nbsp;</label>
                            <div class="comp-stock-display">
                              <span
                                class="comp-stock-value"
                                :class="{ 'is-low': (mat.free_stock ?? mat.total_stock) < mat.qty }"
                              >
                                {{
                                  t('components.materialCreateWizard.stockFreeVsNeeded', {
                                    free: mat.free_stock ?? mat.total_stock,
                                    need: mat.qty,
                                  })
                                }}
                              </span>
                            </div>
                          </div>
                        </div>
                        <p v-if="(mat.free_stock ?? mat.total_stock) < mat.qty" class="comp-stock-warning">
                          {{
                            t('components.materialCreateWizard.stockShortByPcs', {
                              n: mat.qty - (mat.free_stock ?? mat.total_stock),
                            })
                          }}
                        </p>
                          <p v-else class="comp-bulk-info">{{
                            t('components.materialCreateWizard.bulkInfoFromStockToTent')
                          }}</p>
                          <div
                            v-if="canMarkComboArticleAsLinkedContainer(mat)"
                            class="form-row mb-2"
                          >
                            <label class="checkbox-label material-wizard-container-flag">
                              <input
                                type="checkbox"
                                :checked="mat.is_linked_container"
                                @change="setComboArticleLinkedContainer(mat, ($event.target as HTMLInputElement).checked)"
                              />
                              <span>{{ t('components.materialCreateWizard.comboLinkedContainerCheckbox') }}</span>
                            </label>
                          </div>
                        </template>
                      </template>
                    </div>
                  </div>

                  <button type="button" class="btn-outline-small combo-add-article-btn" @click="addEmptyComboArticle">
                    + {{ t('components.materialCreateWizard.comboAddArticle') }}
                  </button>
                </div>

                <!-- Virtuelle Kombo: Lager-Teile + selbst mitbringen -->
                <template v-else>
                <p class="step-hint">{{ t('components.materialCreateWizard.comboVirtualArticlesHint') }}</p>
                <div class="combo-search">
                  <label class="combo-search-label">{{ t('components.materialCreateWizard.comboSearchStockLabel') }}</label>
                  <input 
                    v-model="comboMaterialSearch" 
                    type="text" 
                    class="form-input"
                    :placeholder="t('components.materialCreateWizard.comboMaterialSearchPlaceholder')"
                    @input="searchComboMaterials"
                  />
                  <div v-if="comboMaterialSearch.trim().length >= 1 && filteredComboMaterials.length > 0" class="combo-dropdown">
                    <div 
                      v-for="mat in filteredComboMaterials" 
                      :key="mat.id"
                      class="combo-item"
                      @click="addComboMaterial(mat)"
                    >
                      <div class="combo-item-info">
                        <span class="combo-item-name">{{ mat.name }}</span>
                        <span class="combo-item-cat">{{
                          mat.category?.name || t('components.materialCreateWizard.noCategory')
                        }}</span>
                      </div>
                      <span class="combo-item-stock">{{
                        t('components.materialCreateWizard.comboStockPcs', { n: mat.total_stock })
                      }}</span>
                    </div>
                  </div>
                  <div v-else-if="comboMaterialSearch.trim().length >= 1 && filteredComboMaterials.length === 0" class="combo-dropdown">
                    <div class="combo-empty">{{ t('components.materialCreateWizard.comboNoMaterialsFound') }}</div>
                  </div>
                </div>
                <button
                  type="button"
                  class="btn-outline-small combo-add-article-btn"
                  @click="addEmptySelfProvidedComboArticle"
                >
                  + {{ t('components.materialCreateWizard.comboAddSelfProvided') }}
                </button>

                <div v-if="selectedComboMaterials.length > 0" class="combo-list">
                  <div 
                    v-for="(mat, index) in selectedComboMaterials" 
                    :key="mat.draftId"
                    class="combo-list-item"
                    :class="{ 'combo-list-item--self-provided': mat.component_source === 'self_provided' }"
                  >
                    <span class="combo-list-num">{{ index + 1 }}.</span>
                    <div class="combo-list-info">
                      <div class="combo-list-name-row">
                        <input
                          v-if="mat.component_source === 'self_provided'"
                          v-model="mat.name"
                          type="text"
                          class="form-input combo-list-name-input"
                          :placeholder="t('components.materialCreateWizard.comboSelfProvidedNamePlaceholder')"
                        />
                        <span v-else class="combo-list-name">{{ mat.name }}</span>
                        <span
                          v-if="mat.component_source === 'self_provided'"
                          class="comp-optional-badge comp-selfprovided-badge"
                        >{{ t('components.materialDetail.selfProvidedShortBadge') }}</span>
                      </div>
                      <span v-if="mat.component_source === 'stock' && mat.category?.name" class="combo-list-cat">{{
                        mat.category?.name
                      }}</span>
                      <p
                        v-if="mat.component_source === 'self_provided'"
                        class="combo-list-self-hint"
                      >
                        {{ t('components.materialCreateWizard.compSelfProvidedDesc', { n: mat.qty || 1 }) }}
                      </p>
                    </div>
                    <div class="combo-list-qty">
                      <label>{{ t('components.materialCreateWizard.labelQtyShort') }}</label>
                      <input 
                        v-model.number="mat.qty" 
                        type="number" 
                        min="1" 
                        class="qty-input"
                      />
                    </div>
                    <button type="button" class="combo-remove" @click="removeComboMaterial(index)">×</button>
                  </div>
                </div>
                <p v-else class="combo-empty-hint">{{ t('components.materialCreateWizard.comboNoArticlesYet') }}</p>
                </template>

                <p v-if="selectedComboMaterials.filter(isComboArticleDone).length < 2" class="combo-warning">
                  ⚠️ {{ t('components.materialCreateWizard.comboMinTwoRequired') }}
                </p>
                <p
                  v-if="
                    formData.material_type === 'virtual_combo' &&
                    selectedComboMaterials.filter(isComboArticleDone).length >= 2 &&
                    !virtualComboHasStockArticle
                  "
                  class="combo-warning"
                >
                  ⚠️ {{ t('components.materialCreateWizard.comboMinOneStockRequired') }}
                </p>
              </div>
            </div>

            <!-- Lagerplatz / Details / Vermietung folgen als eigene Akkordeons -->

            <!-- Batch Formular: Im Batch-Modus ODER bei physical Einzelartikel ohne Vorlage mit tracking -->
            <div
              v-if="isAddBatchMode || (!isFromTemplate && creationMode === 'individual' && formData.material_type === 'physical' && formData.tracking_type)"
              class="step-section"
              data-step="stock"
              data-onboarding="material-wizard-stock"
            >
              <div class="step-header step-header--clickable" @click="toggleStep('stock')">
                <span class="step-title">{{
                  isAddBatchMode ? t('components.materialCreateWizard.stepNewLot') : t('components.materialCreateWizard.stepInitialStock')
                }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('stock') }">▾</span>
              </div>
              
              <div v-show="isStepOpen('stock')" class="step-content">
                <div class="batch-form">
                  <div v-if="formData.tracking_type === 'bulk' || isAddBatchMode" class="form-row mb-2">
                    <div
                      v-if="!isAddBatchMode && !formData.is_food"
                      class="form-group span-full"
                      data-onboarding="material-wizard-stock-unit"
                    >
                      <div class="stock-unit-options" role="tablist">
                        <button
                          v-for="opt in stockUnitOptions"
                          :key="opt.value"
                          type="button"
                          class="qty-entry-mode-btn"
                          :class="{ active: stock_unit === opt.value }"
                          @click="setStockUnit(opt.value)"
                        >
                          {{ opt.label }}
                        </button>
                      </div>
                      <p class="field-hint">{{ wizardStockUnitHint }}</p>
                    </div>
                    <!-- Esswaren: Chargen zeilenweise (#1, #2 …) — Menge, Kaufdatum, Ablaufdatum -->
                    <template v-if="formData.is_food && !isAddBatchMode">
                      <div
                        class="food-lots form-group span-full"
                        data-onboarding="material-wizard-food-lots"
                      >
                        <div class="food-lot-row">
                          <span class="food-lot-num" aria-hidden="true">#1</span>
                          <div class="food-lot-fields">
                            <div class="form-group" data-onboarding="material-wizard-stock-qty">
                              <label>{{ t('components.materialCreateWizard.labelFoodExtraLotQty') }}</label>
                              <input
                                v-model.number="wizardDisplayQty"
                                type="number"
                                min="0"
                                class="form-input"
                                :placeholder="t('components.materialCreateWizard.phFoodExtraLotQty')"
                              />
                            </div>
                            <div class="form-group" data-onboarding="material-wizard-stock-purchase-date">
                              <label>{{ t('components.materialCreateWizard.labelPurchaseDateFoodOptional') }}</label>
                              <input
                                v-model="formData.purchase_date"
                                type="date"
                                class="form-input"
                              />
                            </div>
                            <div class="form-group">
                              <label>
                                {{ t('components.materialCreateWizard.labelExpiryDate') }}
                                <span class="required">*</span>
                              </label>
                              <input
                                v-model="formData.expiry_date"
                                type="date"
                                class="form-input"
                                :class="{ 'is-invalid': !formData.expiry_date && formData.initial_qty > 0 }"
                              />
                            </div>
                          </div>
                          <!-- Platzhalter: gleiche Feldbreite wie Zeilen mit × -->
                          <div class="food-lot-actions" aria-hidden="true" />
                        </div>

                        <div
                          v-for="(lot, lotIndex) in foodExtraLots"
                          :key="lot.id"
                          class="food-lot-row"
                        >
                          <span class="food-lot-num" aria-hidden="true">#{{ lotIndex + 2 }}</span>
                          <div class="food-lot-fields">
                            <div class="form-group">
                              <label>{{ t('components.materialCreateWizard.labelFoodExtraLotQty') }}</label>
                              <input
                                v-model.number="lot.qty"
                                type="number"
                                min="1"
                                step="1"
                                class="form-input"
                                :class="{ 'is-invalid': lot.qty < 1 }"
                                :placeholder="t('components.materialCreateWizard.phFoodExtraLotQty')"
                              />
                            </div>
                            <div class="form-group">
                              <label>{{ t('components.materialCreateWizard.labelPurchaseDateFoodOptional') }}</label>
                              <input
                                v-model="lot.purchase_date"
                                type="date"
                                class="form-input"
                              />
                            </div>
                            <div class="form-group">
                              <label>
                                {{ t('components.materialCreateWizard.labelExpiryDate') }}
                                <span class="required">*</span>
                              </label>
                              <input
                                v-model="lot.expiry_date"
                                type="date"
                                class="form-input"
                                :class="{ 'is-invalid': !lot.expiry_date }"
                              />
                            </div>
                          </div>
                          <div class="food-lot-actions">
                            <button
                              type="button"
                              class="combo-remove"
                              :title="t('components.materialCreateWizard.btnRemoveFoodExtraLot')"
                              :aria-label="t('components.materialCreateWizard.btnRemoveFoodExtraLot')"
                              @click="removeFoodExtraLot(lotIndex)"
                            >
                              ×
                            </button>
                          </div>
                        </div>

                        <button
                          type="button"
                          class="btn-outline-small"
                          @click="addFoodExtraLot"
                        >
                          {{ t('components.materialCreateWizard.btnAddFoodExtraLot') }}
                        </button>
                        <p class="field-hint mt-1">{{ t('components.materialCreateWizard.hintFoodExtraLots') }}</p>
                      </div>
                    </template>

                    <!-- Nicht-Esswaren / Charge hinzufügen: bisherige Felder -->
                    <template v-else>
                    <div class="form-group" data-onboarding="material-wizard-stock-qty">
                      <label>{{ wizardQuantityLabel }}</label>
                      <div v-if="wizardShowQtyEntryModes" class="qty-entry-modes" role="tablist">
                        <button
                          type="button"
                          class="qty-entry-mode-btn"
                          :class="{ active: qtyEntryMode === 'base' }"
                          @click="setQtyEntryMode('base')"
                        >
                          {{ wizardStockUnitLabel }}
                        </button>
                        <button
                          v-if="wizardCanUsePackEntry"
                          type="button"
                          class="qty-entry-mode-btn"
                          :class="{ active: qtyEntryMode === 'pack' }"
                          @click="setQtyEntryMode('pack')"
                        >
                          {{ wizardPackUnitLabel }}
                        </button>
                        <button
                          v-if="wizardCanUseContentEntry"
                          type="button"
                          class="qty-entry-mode-btn"
                          :class="{ active: qtyEntryMode === 'content' }"
                          @click="setQtyEntryMode('content')"
                        >
                          m
                        </button>
                      </div>
                      <input
                        v-model.number="wizardDisplayQty"
                        type="number"
                        min="0"
                        class="form-input"
                        :placeholder="wizardQtyPlaceholder"
                      />
                      <p v-if="wizardQtyEntryHint" class="field-hint">{{ wizardQtyEntryHint }}</p>
                    </div>
                    <div class="form-group" data-onboarding="material-wizard-stock-purchase-date">
                      <label>
                        {{ t('components.materialCreateWizard.labelPurchaseDateFoodOptional') }}
                        <span v-if="!formData.is_food" class="required">*</span>
                      </label>
                      <input
                        v-model="formData.purchase_date"
                        type="date"
                        class="form-input"
                        :class="{ 'is-invalid': !formData.is_food && !formData.purchase_date && formData.initial_qty > 0 }"
                        :required="!formData.is_food"
                      />
                    </div>
                    <div v-if="formData.is_food || showExpiryDateForNonFood" class="form-group">
                      <label>
                        {{ t('components.materialCreateWizard.labelExpiryDate') }}
                        <span v-if="formData.is_food" class="required">*</span>
                      </label>
                      <input
                        v-model="formData.expiry_date"
                        type="date"
                        class="form-input"
                        :class="{ 'is-invalid': formData.is_food && !formData.expiry_date && formData.initial_qty > 0 }"
                      />
                    </div>
                    </template>
                  </div>

                  <!-- Anschaffungspreis / Rechnung – bei Menge (Neuanlage + Add-Batch) -->
                  <div data-onboarding="material-wizard-stock-purchase-price">
                  <div
                    v-if="purchasePriceRequired || foodPurchasePriceSectionVisible"
                    class="slider-toggle-group pack-toggle-inline mt-2 mb-2"
                  >
                    <label class="toggle-label">
                      <span class="toggle-wrapper">
                        <input
                          type="checkbox"
                          class="toggle-input"
                          :checked="purchasePriceInputMode === 'total'"
                          @change="onPurchasePriceModeToggle"
                        />
                        <span class="toggle-slider toggle-slider--blue"></span>
                      </span>
                      <span class="toggle-text">
                        <span class="toggle-title">{{ t('components.materialCreateWizard.toggleDistributeTotalTitle') }}</span>
                        <span class="toggle-desc">{{ t('components.materialCreateWizard.toggleDistributeTotalDesc') }}</span>
                      </span>
                    </label>
                    <transition name="slide-down">
                      <div v-if="purchasePriceInputMode === 'unit'" key="pp-unit" class="slider-details pack-details mt-2">
                        <div class="form-row">
                        <div class="form-group">
                            <label>{{ wizardPurchasePriceLabel }}</label>
                          <div class="price-input">
                            <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                            <input
                              v-model.number="formData.unit_price"
                              type="number"
                              step="0.01"
                              min="0"
                              class="form-input"
                              :placeholder="t('components.materialCreateWizard.phPriceZero')"
                            />
                          </div>
                          <p
                            v-if="packSaleToUnitSaleChf != null && purchasePriceInputMode === 'unit'"
                            class="field-hint"
                          >
                            {{
                              t('components.materialCreateWizard.packApproxPerPiece', {
                                price: packSaleToUnitSaleChf.toFixed(2),
                              })
                            }}
                          </p>
                        </div>
                          <div class="form-group">
                            <label>{{ t('components.materialCreateWizard.labelPurchaseShippingChf') }}</label>
                            <div class="price-input">
                              <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                              <input
                                v-model="purchaseShippingChf"
                                type="text"
                                inputmode="decimal"
                                class="form-input"
                                :placeholder="t('components.materialCreateWizard.phPriceZero')"
                              />
                            </div>
                          </div>
                        </div>
                        <p v-if="purchasePriceContextQty > 0 && parseChfInput(purchaseShippingChf) > 0" class="field-hint">
                          {{
                            t('components.materialCreateWizard.hintShippingPerPiece', {
                              price: effectivePurchaseUnitPrice.toFixed(2),
                              qty: purchasePriceContextQty,
                            })
                          }}
                        </p>
                      </div>
                      <div v-else key="pp-total" class="slider-details pack-details mt-2">
                        <div class="form-row">
                          <div class="form-group">
                            <label>{{ t('components.materialCreateWizard.labelPurchaseTotalWaresChf') }}</label>
                            <div class="price-input">
                              <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                              <input
                                v-model="purchaseTotalWaresChf"
                                type="text"
                                inputmode="decimal"
                                class="form-input"
                                :placeholder="t('components.materialCreateWizard.phPriceZero')"
                              />
                            </div>
                          </div>
                          <div class="form-group">
                            <label>{{ t('components.materialCreateWizard.labelPurchaseShippingChf') }}</label>
                            <div class="price-input">
                              <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                              <input
                                v-model="purchaseShippingChf"
                                type="text"
                                inputmode="decimal"
                                class="form-input"
                                :placeholder="t('components.materialCreateWizard.phPriceZero')"
                              />
                            </div>
                          </div>
                        </div>
                        <p v-if="purchasePriceContextQty > 0" class="field-hint">
                          {{
                            t('components.materialCreateWizard.hintDerivedUnitPrice', {
                              price: effectivePurchaseUnitPrice.toFixed(2),
                              qty: purchasePriceContextQty,
                            })
                          }}
                        </p>
                      </div>
                    </transition>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label>{{ t('components.materialCreateWizard.labelInvoiceNumber') }}</label>
                      <input 
                        v-model="formData.invoice_number" 
                        type="text" 
                        class="form-input"
                        :placeholder="t('common.optional')"
                      />
                    </div>
                  </div>

                  <div
                    v-if="purchasePriceRequired && !formData.is_food && departmentHasAccountingRole(props.departmentId)"
                    class="form-row mt-2"
                  >
                    <div class="form-group span-full">
                      <PurchaseReceiptFileInput
                        v-model="purchaseReceiptFile"
                        :label="t('components.purchaseReceipt.label')"
                        :hint="t('components.purchaseReceipt.hint')"
                      />
                    </div>
                  </div>
                  </div>

                  <div
                    v-if="formData.tracking_type !== 'serialized'"
                    class="form-row"
                    data-onboarding="material-wizard-stock-codes"
                  >
                    <div class="form-group">
                      <label>
                        {{ t('components.materialDetail.labelCode') }}
                        <span class="optional">{{ t('components.materialDetail.optionalShort') }}</span>
                      </label>
                      <input
                        v-model="formData.barcode_tag"
                        type="text"
                        class="form-input"
                        :placeholder="t('components.materialDetail.codePlaceholder')"
                      />
                    </div>
                    <div class="form-group">
                      <label>
                        {{ t('components.materialDetail.labelEan') }}
                        <span class="optional">{{ t('components.materialDetail.optionalShort') }}</span>
                      </label>
                      <input v-model="formData.ean" type="text" class="form-input" :placeholder="t('components.batchModal.eanPlaceholder')" />
                    </div>
                  </div>

                  <div v-if="!formData.is_food && formData.tracking_type !== 'serialized'" class="slider-toggle-group pack-toggle-inline">
                    <label class="toggle-label">
                      <span class="toggle-wrapper">
                        <input type="checkbox" v-model="showExpiryDateForNonFood" class="toggle-input" />
                        <span class="toggle-slider toggle-slider--blue"></span>
                      </span>
                      <span class="toggle-text">
                        <span class="toggle-title">{{ t('components.materialCreateWizard.toggleShowExpiryTitle') }}</span>
                        <span class="toggle-desc">{{ t('components.materialCreateWizard.toggleShowExpiryDesc') }}</span>
                      </span>
                    </label>
                  </div>

                  <!-- Serialisiert: Seriennummer-Tabelle -->
                  <div v-if="formData.tracking_type === 'serialized'" class="serial-numbers-section">
                    <div class="serial-header">
                      <label>{{
                        t('components.materialCreateWizard.serialNumbersWithCount', { count: serializedQty })
                      }}</label>
                      <div style="display:flex; gap:8px; align-items:center;">
                        <button type="button" class="add-serial-btn" @click="addSerialNumber">
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                          </svg>
                          {{ t('components.materialCreateWizard.btnAddRow') }}
                        </button>
                        <button type="button" class="add-serial-btn" @click="toggleSerialScanner()">
                          {{
                            serialScannerActive
                              ? t('components.materialCreateWizard.scannerStop')
                              : t('components.materialCreateWizard.scannerStart')
                          }}
                        </button>
                      </div>
                    </div>
                    <div class="slider-toggle-group">
                      <label class="toggle-label">
                        <span class="toggle-wrapper">
                          <input type="checkbox" v-model="serialAutoGenerateEnabled" class="toggle-input" />
                          <span class="toggle-slider toggle-slider--blue"></span>
                        </span>
                        <span class="toggle-text">
                          <span class="toggle-title">{{ t('components.materialCreateWizard.serialAutoGenTitle') }}</span>
                          <span class="toggle-desc">{{ t('components.materialCreateWizard.serialAutoGenDesc') }}</span>
                        </span>
                      </label>
                      <transition name="slide-down">
                        <div v-if="serialAutoGenerateEnabled" class="slider-details">
                          <div class="serial-auto-generate-row">
                            <div class="serial-auto-field serial-auto-field-prefix">
                              <label>{{ t('components.materialCreateWizard.labelPrefixShort') }}</label>
                              <input
                                v-model="autoGenPrefix"
                                type="text"
                                class="form-input form-input-sm"
                                :placeholder="suggestedSerialPrefix || ''"
                              />
                            </div>
                            <div class="serial-auto-field">
                              <label>{{ t('components.materialCreateWizard.labelStartNumberShort') }}</label>
                              <input v-model.number="autoGenStart" type="number" min="1" class="form-input form-input-sm" />
                            </div>
                            <div class="serial-auto-field">
                              <label>{{ t('components.materialCreateWizard.labelDigitsShort') }}</label>
                              <input v-model.number="autoGenPad" type="number" min="1" max="6" class="form-input form-input-sm" />
                            </div>
                            <div class="serial-auto-field">
                              <label>{{ t('components.materialCreateWizard.labelCountShort') }}</label>
                              <input v-model.number="autoGenCount" type="number" min="1" class="form-input form-input-sm" />
                            </div>
                            <label
                              v-if="serialNumbers.length === 0"
                              class="serial-auto-container-flag checkbox-label"
                            >
                              <input type="checkbox" v-model="serialAllAsContainers" />
                              <span>{{ t('components.materialCreateWizard.serialAllAsContainersTitle') }}</span>
                            </label>
                            <button type="button" class="add-serial-btn add-serial-btn-secondary" @click="generateSerialNumbers">
                              {{ t('components.materialCreateWizard.btnGenerateSerialList') }}
                            </button>
                            <span class="serial-auto-preview">{{ autoGenPreview }}</span>
                          </div>
                          <p
                            v-if="serialNumbers.length === 0"
                            class="field-hint serial-all-containers-hint"
                          >{{ t('components.materialCreateWizard.serialAllAsContainersDesc') }}</p>
                        </div>
                      </transition>
                    </div>
                    <div v-if="serialNumbers.length > 0" class="serial-bulk-container-row">
                      <label class="checkbox-label">
                        <input type="checkbox" v-model="serialAllAsContainers" />
                        <span>{{ t('components.materialCreateWizard.serialAllAsContainersTitle') }}</span>
                      </label>
                    </div>
                    <BarcodeScannerPanel
                      v-if="serialScannerActive"
                      :active="serialScannerActive"
                      mode="all"
                      :hint="t('components.materialCreateWizard.serialScannerHint')"
                      @detected="onSerialDetected"
                      @error="onSerialScannerError"
                    />
                    
                    <div v-if="serialNumbers.length > 0" class="serial-cards" data-onboarding="material-wizard-stock-codes">
                      <article
                        v-for="(entry, index) in serialNumbers"
                        :key="entry.id"
                        class="serial-card"
                      >
                        <header class="serial-card__header">
                          <span class="serial-card__index">#{{ index + 1 }}</span>
                          <div class="serial-card__actions">
                            <button
                              type="button"
                              class="remove-serial-btn"
                              @click="openSerialScannerFor(entry.id)"
                              :title="t('components.materialCreateWizard.titleScanSerialRow')"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="5" width="18" height="14" rx="2"/>
                                <line x1="7" y1="9" x2="17" y2="9"/>
                                <line x1="7" y1="13" x2="12" y2="13"/>
                              </svg>
                            </button>
                            <button
                              type="button"
                              class="remove-serial-btn"
                              @click="removeSerialNumber(entry.id)"
                              :title="t('components.materialCreateWizard.btnRemoveRowTitle')"
                            >
                              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                              </svg>
                            </button>
                          </div>
                        </header>

                        <div class="serial-card__grid">
                          <div class="form-group serial-card__field serial-card__field--serial">
                            <label>{{ t('components.materialDetail.thSerial') }}</label>
                            <input
                              v-model="entry.serial_number"
                              type="text"
                              class="form-input"
                              :placeholder="t('components.materialCreateWizard.phEnterSerialNumber')"
                              @keydown.enter.prevent="addSerialNumber"
                            />
                          </div>
                          <div class="form-group serial-card__field">
                            <label>{{ t('components.materialDetail.thLabel') }}</label>
                            <input
                              v-model="entry.label"
                              type="text"
                              class="form-input"
                              :placeholder="t('components.materialCreateWizard.phInstanceLabelExample')"
                            />
                          </div>
                          <div class="form-group serial-card__field">
                            <label>{{ t('components.materialDetail.labelCode') }}</label>
                            <input
                              v-model="entry.barcode_tag"
                              type="text"
                              class="form-input"
                              :placeholder="t('components.materialDetail.codePlaceholder')"
                            />
                          </div>
                          <div class="form-group serial-card__field">
                            <label>{{ t('components.materialDetail.labelEan') }}</label>
                            <input
                              v-model="entry.ean"
                              type="text"
                              class="form-input"
                              :placeholder="t('components.batchModal.eanPlaceholder')"
                            />
                          </div>
                          <div class="form-group serial-card__field serial-card__field--note">
                            <label>{{ t('components.materialDetail.thNote') }}</label>
                            <input
                              v-model="entry.notes"
                              type="text"
                              class="form-input"
                              :placeholder="t('common.optional')"
                            />
                          </div>
                          <label class="checkbox-label serial-card__container">
                            <input
                              type="checkbox"
                              v-model="entry.is_container"
                              :title="t('components.materialCreateWizard.serialIsContainerLong')"
                            />
                            <span>{{ t('components.materialDetail.thContainer') }}</span>
                          </label>
                        </div>

                        <div v-if="!serialLocationSameForAll" class="serial-card__location">
                          <div class="form-group serial-card__field serial-card__field--mode">
                            <label>{{ t('components.materialCreateWizard.labelLocationKindShort') }}</label>
                            <select
                              v-model="entry.location_mode"
                              class="form-select"
                              @change="entry.rack_id=''; entry.slot_id=''; entry.container_batch_id=''"
                            >
                              <option value="slot">{{ t('components.materialCreateWizard.tabGestellFach') }}</option>
                              <option value="kiste">{{ t('components.materialCreateWizard.tabKisteTasche') }}</option>
                            </select>
                          </div>
                          <template v-if="entry.location_mode === 'slot'">
                            <div class="form-group serial-card__field">
                              <label>{{ t('components.materialCreateWizard.labelStorageSiteSm') }}</label>
                              <select
                                v-model="entry.storage_address_id"
                                class="form-select"
                                @change="onSerialEntryStorageAddressChange(entry)"
                              >
                                <option v-for="addr in storageAddresses" :key="addr.id" :value="addr.id">{{ addr.name || addr.street_line }}</option>
                              </select>
                            </div>
                            <div class="form-group serial-card__field">
                              <label>{{ t('components.materialCreateWizard.rackLabel') }}</label>
                              <select
                                v-model="entry.rack_id"
                                class="form-select"
                                @change="onSerialEntryRackChange(entry)"
                                @mouseenter="prefetchRackPreview(entry.rack_id)"
                                :title="getRackPreviewTitle(entry.rack_id)"
                              >
                                <option value="" disabled>{{ t('components.materialCreateWizard.selectRackDash') }}</option>
                                <option
                                  v-for="rack in getRacksForSerialEntry(entry)"
                                  :key="rack.id"
                                  :value="String(rack.id)"
                                  :title="getRackPreviewTitle(rack.id)"
                                >
                                  {{ rack.name }}
                                </option>
                              </select>
                            </div>
                            <div class="form-group serial-card__field">
                              <label>{{ t('components.materialCreateWizard.slotLabel') }}</label>
                              <select
                                v-model="entry.slot_id"
                                class="form-select"
                                :disabled="!entry.rack_id"
                                @mouseenter="prefetchSlotPreview(entry.rack_id, entry.slot_id)"
                                :title="getSlotPreviewTitle(entry.rack_id, entry.slot_id)"
                              >
                                <option value="" disabled>{{ t('components.materialCreateWizard.selectPickSlotDash') }}</option>
                                <option
                                  v-for="slot in (entry.rack_id ? (slotsByRackId[String(entry.rack_id)] || []) : [])"
                                  :key="slot.id"
                                  :value="String(slot.id)"
                                  :title="getSlotPreviewTitle(entry.rack_id, slot.id)"
                                >
                                  {{ formatSlotOptionLabel(entry.rack_id, slot) }}
                                </option>
                              </select>
                            </div>
                          </template>
                          <div v-else class="form-group serial-card__field serial-card__field--box">
                            <label>{{ t('components.materialCreateWizard.labelBoxBagSm') }}</label>
                            <select
                              v-model="entry.container_batch_id"
                              class="form-select"
                              @mouseenter="prefetchContainerPreviews()"
                              :title="getContainerPreviewTitle(entry.container_batch_id)"
                            >
                              <option value="">{{ t('components.materialCreateWizard.selectPickBoxDash') }}</option>
                              <option
                                v-for="cb in containerBatches"
                                :key="cb.id"
                                :value="cb.id"
                                :title="formatContainerBatchOptionFullLabel(cb)"
                              >
                                {{ formatContainerBatchOptionFullLabel(cb) }}
                              </option>
                            </select>
                          </div>
                        </div>
                      </article>
                    </div>
                    
                    <div v-else class="empty-serials">
                      <p>{{ t('components.materialCreateWizard.emptySerialsHint') }}</p>
                      <button type="button" class="add-first-btn" @click="addSerialNumber">
                        + {{ t('components.materialCreateWizard.btnAddFirstSerial') }}
                      </button>
                    </div>
                    <p v-if="!serialLocationSameForAll && hasInvalidSerialLocations" class="field-hint is-invalid">
                      {{ t('components.materialCreateWizard.invalidSerialLocationHint') }}
                    </p>
                    <p v-if="serialDuplicateHint" class="field-hint is-invalid">
                      {{ serialDuplicateHint }}
                    </p>

                    <!-- Verpackungseinheit – sobald Menge (Seriennummern) erfasst -->
                    <div v-if="serializedQty > 0" class="slider-toggle-group pack-toggle-inline mt-3">
                      <label class="toggle-label">
                        <span class="toggle-wrapper">
                          <input type="checkbox" v-model="packUnitEnabled" class="toggle-input" />
                          <span class="toggle-slider toggle-slider--blue"></span>
                        </span>
                        <span class="toggle-text">
                          <span class="toggle-title">{{ t('components.materialDetail.sectionPackaging') }}</span>
                          <span class="toggle-desc">{{ t('components.materialDetail.packagingHint') }}</span>
                        </span>
                      </label>
                      <transition name="slide-down">
                        <div v-if="packUnitEnabled" class="slider-details pack-details">
                          <div class="form-row">
                            <div class="form-group">
                              <label>{{ t('components.materialDetail.labelPiecesPerUnit') }}</label>
                              <input
                                v-model.number="formData.pack_size"
                                type="number"
                                min="2"
                                class="form-input"
                                :placeholder="t('components.materialDetail.packSizePlaceholder')"
                              />
                            </div>
                            <div class="form-group">
                              <label>{{ t('components.materialDetail.labelDesignation') }}</label>
                              <select v-model="formData.pack_unit" class="form-input">
                                <option value="">{{ t('components.materialDetail.packUnitNone') }}</option>
                                <option :value="PACK_UNIT_BUNDLE">{{ t('components.materialDetail.packUnitBundle') }}</option>
                                <option value="Kiste">{{ t('components.materialDetail.packUnitKiste') }}</option>
                                <option value="Karton">{{ t('components.materialDetail.packUnitKarton') }}</option>
                                <option value="Sack">{{ t('components.materialDetail.packUnitSack') }}</option>
                                <option value="Rolle">{{ t('components.materialDetail.packUnitRolle') }}</option>
                                <option value="Palette">{{ t('components.materialDetail.packUnitPalette') }}</option>
                                <option value="Set">{{ t('components.materialDetail.packUnitSet') }}</option>
                                <option value="Paket">{{ t('components.materialDetail.packUnitPaket') }}</option>
                              </select>
                            </div>
                            <div v-if="formData.is_consumable || formData.is_food" class="form-group">
                              <label>
                                {{ t('components.materialCreateWizard.labelPackSalePriceChf') }}
                                <span class="optional-label">({{ t('common.optional') }})</span>
                              </label>
                              <div class="price-input">
                                <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                                <input
                                  v-model.number="formData.pack_sale_price_chf"
                                  type="number"
                                  step="0.05"
                                  min="0"
                                  class="form-input"
                                  :placeholder="t('components.materialCreateWizard.phPriceZero')"
                                />
                              </div>
                              <p
                                v-if="formData.pack_size && formData.pack_size >= 2 && formData.pack_sale_price_chf && formData.pack_sale_price_chf > 0"
                                class="field-hint"
                              >
                                {{
                                  t('components.materialCreateWizard.packApproxPerPiece', {
                                    price: (formData.pack_sale_price_chf / formData.pack_size).toFixed(2),
                                  })
                                }}
                              </p>
                            </div>
                          </div>
                          <p v-if="formData.pack_size && formData.pack_unit" class="pack-preview">
                            {{
                              t('components.materialDetail.packPreview', {
                                stock: serializedQty,
                                packs: Math.floor(serializedQty / formData.pack_size),
                                unit: formData.pack_unit,
                                per: formData.pack_size,
                              })
                            }}<span v-if="serializedQty % formData.pack_size !== 0">{{
                              t('components.materialDetail.packPreviewRemain', { rem: serializedQty % formData.pack_size })
                            }}</span>
                          </p>
                        </div>
                      </transition>
                    </div>
                  </div>

                  <!-- Massenartikel: Normale Mengen-Eingabe -->
                  <div v-else>
                    <!-- Verpackungseinheit – sobald eine Anzahl eingetragen wurde (Stk oder Meter) -->
                    <div
                      v-if="!formData.is_food && formData.initial_qty > 0 && (stock_unit === 'Stk' || stock_unit === 'm')"
                      class="slider-toggle-group pack-toggle-inline"
                    >
                      <label class="toggle-label">
                        <span class="toggle-wrapper">
                          <input type="checkbox" v-model="packUnitEnabled" class="toggle-input" />
                          <span class="toggle-slider toggle-slider--blue"></span>
                        </span>
                        <span class="toggle-text">
                          <span class="toggle-title">{{ t('components.materialDetail.sectionPackaging') }}</span>
                          <span class="toggle-desc">{{
                            stock_unit === 'm'
                              ? t('components.materialDetail.packagingHintMeter')
                              : t('components.materialDetail.packagingHint')
                          }}</span>
                        </span>
                      </label>
                      <transition name="slide-down">
                        <div v-if="packUnitEnabled" class="slider-details pack-details">
                          <div class="form-row">
                            <div class="form-group">
                              <label>{{
                                stock_unit === 'm'
                                  ? t('components.materialDetail.labelMetersPerUnit')
                                  : t('components.materialDetail.labelPiecesPerUnit')
                              }}</label>
                              <input
                                v-model.number="formData.pack_size"
                                type="number"
                                min="2"
                                class="form-input"
                                :placeholder="t('components.materialDetail.packSizePlaceholder')"
                              />
                            </div>
                            <div class="form-group">
                              <label>{{ t('components.materialDetail.labelDesignation') }}</label>
                              <select v-model="formData.pack_unit" class="form-input">
                                <option value="">{{ t('components.materialDetail.packUnitNone') }}</option>
                                <option :value="PACK_UNIT_BUNDLE">{{ t('components.materialDetail.packUnitBundle') }}</option>
                                <option value="Kiste">{{ t('components.materialDetail.packUnitKiste') }}</option>
                                <option value="Karton">{{ t('components.materialDetail.packUnitKarton') }}</option>
                                <option value="Sack">{{ t('components.materialDetail.packUnitSack') }}</option>
                                <option value="Rolle">{{ t('components.materialDetail.packUnitRolle') }}</option>
                                <option value="Palette">{{ t('components.materialDetail.packUnitPalette') }}</option>
                                <option value="Set">{{ t('components.materialDetail.packUnitSet') }}</option>
                                <option value="Paket">{{ t('components.materialDetail.packUnitPaket') }}</option>
                              </select>
                            </div>
                            <div v-if="formData.is_consumable || formData.is_food" class="form-group">
                              <label>
                                {{ t('components.materialCreateWizard.labelPackSalePriceChf') }}
                                <span class="optional-label">({{ t('common.optional') }})</span>
                              </label>
                              <div class="price-input">
                                <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                                <input
                                  v-model.number="formData.pack_sale_price_chf"
                                  type="number"
                                  step="0.05"
                                  min="0"
                                  class="form-input"
                                  :placeholder="t('components.materialCreateWizard.phPriceZero')"
                                />
                              </div>
                              <p
                                v-if="formData.pack_size && formData.pack_size >= 2 && formData.pack_sale_price_chf && formData.pack_sale_price_chf > 0"
                                class="field-hint"
                              >
                                {{
                                  t('components.materialCreateWizard.packApproxPerPiece', {
                                    price: (formData.pack_sale_price_chf / formData.pack_size).toFixed(2),
                                  })
                                }}
                              </p>
                            </div>
                          </div>
                          <p v-if="formData.pack_size && formData.pack_unit" class="pack-preview">
                            {{
                              t('components.materialDetail.packPreview', {
                                stock: formData.initial_qty || 0,
                                packs: Math.floor((formData.initial_qty || 0) / formData.pack_size),
                                unit: formData.pack_unit,
                                per: formData.pack_size,
                              })
                            }}<span v-if="formData.initial_qty && (formData.initial_qty % formData.pack_size) !== 0">{{
                              t('components.materialDetail.packPreviewRemain', {
                                rem: formData.initial_qty % formData.pack_size,
                              })
                            }}</span>
                          </p>
                        </div>
                      </transition>
                    </div>
                  </div>

                  <!-- Gemeinsame Felder (Kaufdatum bei Serialisiert) -->
                  <div v-if="formData.tracking_type === 'serialized'" class="form-row mt-3">
                    <div class="form-group">
                      <label>
                        {{ t('components.materialCreateWizard.labelPurchaseDate') }}
                        <span v-if="!formData.is_food" class="required">*</span>
                      </label>
                      <input 
                        v-model="formData.purchase_date" 
                        type="date" 
                        class="form-input"
                        :class="{ 'is-invalid': !formData.is_food && !formData.purchase_date && serializedQty > 0 }"
                        :required="!formData.is_food"
                      />
                    </div>
                  </div>

                  <!-- Kauf/Lieferant: im Add-Batch-Modus hier; bei Neuanlage unter Details -->
                  <template v-if="isAddBatchMode">
                  <div class="form-row">
                    <div class="form-group">
                      <label>{{ t('common.manufacturer') }}</label>
                      <div class="autocomplete-wrapper">
                        <input 
                          v-model="manufacturerSearch" 
                          type="text" 
                          class="form-input"
                          :placeholder="t('components.materialCreateWizard.phSearchManufacturer')"
                          @input="searchManufacturers"
                          @focus="showManufacturerDropdown = true"
                          @blur="hideManufacturerDropdownDelayed"
                        />
                        <button
                          type="button"
                          class="add-inline-btn"
                          @click="openAddManufacturerModal"
                          :title="t('components.materialCreateWizard.addManufacturerInlineTitle')"
                        >
                          +
                        </button>
                        <div v-if="showManufacturerDropdown && manufacturerSearch.length >= 2" class="autocomplete-dropdown">
                          <div 
                            v-for="addr in filteredManufacturers" 
                            :key="addr.id"
                            class="autocomplete-item"
                            @mousedown="selectManufacturer(addr)"
                          >
                            <span class="item-name">{{ addr.name || addr.company }}</span>
                            <span class="item-city">{{ addr.city }}</span>
                          </div>
                          <div 
                            v-if="filteredManufacturers.length === 0" 
                            class="autocomplete-item create-new"
                            @mousedown="openAddManufacturerModal"
                          >
                            <span class="item-name">{{
                              t('components.materialCreateWizard.createManufacturerNamed', { name: manufacturerSearch })
                            }}</span>
                          </div>
                        </div>
                      </div>
                      <p v-if="selectedManufacturer" class="selected-address">
                        ✓ {{ selectedManufacturer.name || selectedManufacturer.company }}
                        <button type="button" class="clear-selection" @click="clearManufacturer">×</button>
                      </p>
                    </div>
                    <div class="form-group">
                      <label>{{ t('components.materialCreateWizard.labelPurchasedFrom') }}</label>
                      <div class="autocomplete-wrapper">
                        <input 
                          v-model="supplierSearch" 
                          type="text" 
                          class="form-input"
                          :placeholder="t('components.materialCreateWizard.phSearchSupplier')"
                          @input="searchSuppliers"
                          @focus="showSupplierDropdown = true"
                          @blur="hideSupplierDropdownDelayed"
                        />
                        <button
                          type="button"
                          class="add-inline-btn"
                          @click="openAddSupplierModal"
                          :title="t('components.materialCreateWizard.addSupplierInlineTitle')"
                        >
                          +
                        </button>
                        <div v-if="showSupplierDropdown && supplierSearch.length >= 2" class="autocomplete-dropdown">
                          <div 
                            v-for="addr in filteredSuppliers" 
                            :key="addr.id"
                            class="autocomplete-item"
                            @mousedown="selectSupplier(addr)"
                          >
                            <span class="item-name">{{ addr.name || addr.company }}</span>
                            <span class="item-city">{{ addr.city }}</span>
                          </div>
                          <div 
                            v-if="filteredSuppliers.length === 0" 
                            class="autocomplete-item create-new"
                            @mousedown="openAddSupplierModal"
                          >
                            <span class="item-name">{{
                              t('components.materialCreateWizard.createSupplierNamed', { name: supplierSearch })
                            }}</span>
                          </div>
                        </div>
                      </div>
                      <p v-if="selectedSupplier" class="selected-address">
                        ✓ {{ selectedSupplier.name || selectedSupplier.company }}
                        <button type="button" class="clear-selection" @click="clearSupplier">×</button>
                      </p>
                    </div>
                  </div>

                  </template>
                </div>
              </div>
            </div>

            <!-- Lagerplatz -->
            <div
              v-if="isAddBatchMode || (!isFromTemplate && creationMode === 'individual' && formData.material_type === 'physical' && formData.tracking_type)"
              class="step-section"
              data-step="storage"
              data-onboarding="material-wizard-storage"
            >
              <div class="step-header step-header--clickable" @click="toggleStep('storage')">
                <span class="step-title">{{ t('components.materialCreateWizard.sectionStorageLocation') }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('storage') }">▾</span>
              </div>

              <div v-show="isStepOpen('storage')" class="step-content">
                  <div
                    v-if="formData.tracking_type === 'bulk' || isAddBatchMode"
                    class="form-row mb-2"
                    data-onboarding="material-wizard-stock-split"
                  >
                    <label class="toggle-label">
                      <span class="toggle-wrapper">
                        <input type="checkbox" v-model="formData.split_allocations" class="toggle-input" :disabled="!stockInputReady" />
                        <span class="toggle-slider toggle-slider--blue"></span>
                      </span>
                      <span class="toggle-text">
                        <span class="toggle-title">{{ t('components.materialCreateWizard.toggleSplitLocationsTitle') }}</span>
                        <span class="toggle-desc">{{ t('components.materialCreateWizard.toggleSplitLocationsDesc') }}</span>
                      </span>
                    </label>
                  </div>

                  <div v-if="formData.tracking_type === 'serialized'" class="form-row mb-2">
                    <label class="toggle-label">
                      <span class="toggle-wrapper">
                        <input type="checkbox" v-model="serialLocationSameForAll" class="toggle-input" />
                        <span class="toggle-slider toggle-slider--blue"></span>
                      </span>
                      <span class="toggle-text">
                        <span class="toggle-title">{{ t('components.materialCreateWizard.toggleSameSerialLocationTitle') }}</span>
                        <span class="toggle-desc">{{ t('components.materialCreateWizard.toggleSameSerialLocationDesc') }}</span>
                      </span>
                    </label>
                  </div>

                  <div
                    v-if="formData.tracking_type === 'serialized' || formData.tracking_type === 'bulk' || isAddBatchMode"
                    data-onboarding="material-wizard-stock-storage"
                  >
                  <div class="form-group">
                    <template v-if="!(formData.tracking_type === 'serialized' && !serialLocationSameForAll)">
                    <template v-if="!((formData.tracking_type === 'bulk' || isAddBatchMode) && formData.split_allocations)">
                      <label>{{ t('components.materialCreateWizard.labelStorageSite') }}</label>
                      <div class="select-with-add">
                        <select v-model="formData.storage_address_id" class="form-select">
                          <option value="">{{ t('components.materialCreateWizard.selectStorageSite') }}</option>
                          <option v-for="addr in storageAddresses" :key="addr.id" :value="addr.id">
                            {{ addr.name || addr.street_line }}
                          </option>
                        </select>
                        <button
                      type="button"
                      class="add-btn"
                      @click="openAddStorageModal"
                      :title="t('components.materialCreateWizard.addStorageSiteTitle')"
                    >
                          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                          </svg>
                        </button>
                      </div>
                    </template>

                  <!-- Allokations-Tabelle (Bulk, wenn Aufteilung aktiv) -->
                  <div v-if="(formData.tracking_type === 'bulk' || isAddBatchMode) && formData.split_allocations" class="allocations-section">
                    <div class="allocations-header">
                      <div>
                      <label>{{
                        t('components.materialCreateWizard.allocationsSumLabel', { n: formData.initial_qty })
                      }}</label>
                        <p class="field-hint">{{ t('components.materialCreateWizard.allocationsModeHint') }}</p>
                      </div>
                      <button type="button" class="add-serial-btn" :disabled="!canAddAllocationRow" @click="addAllocationRow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                          <line x1="12" y1="5" x2="12" y2="19"/>
                          <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        {{ t('components.materialCreateWizard.btnAddRow') }}
                      </button>
                    </div>
                    <div class="allocations-table-wrap">
                      <table class="allocations-table">
                        <thead>
                          <tr>
                            <th>{{ t('components.materialCreateWizard.thQty') }}</th>
                            <th>{{ t('components.materialCreateWizard.thKind') }}</th>
                            <th>{{ t('components.materialCreateWizard.thStorageSiteCol') }}</th>
                            <th></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr
                            v-for="row in initialAllocations"
                            :key="row.id"
                            :class="{ 'allocation-row--duplicate-crate': allocationRowHasDuplicateKiste(row, initialAllocations) }"
                          >
                            <td>
                              <input
                                v-model.number="row.qty"
                                type="number"
                                min="1"
                                class="form-input form-input--sm"
                                :placeholder="t('components.materialCreateWizard.phQtyPlaceholder')"
                              />
                            </td>
                            <td class="alloc-mode-cell">
                              <div class="lagerung-switch lagerung-switch--compact" role="tablist">
                                <button
                                  type="button"
                                  class="lagerung-btn"
                                  :class="{ active: row.mode === 'slot' }"
                                  @click="setAllocationRowMode(row, 'slot')"
                                >
                                  {{ t('components.materialCreateWizard.tabGestellFach') }}
                                </button>
                                <button
                                  type="button"
                                  class="lagerung-btn"
                                  :class="{ active: row.mode === 'kiste' }"
                                  @click="setAllocationRowMode(row, 'kiste')"
                                >
                                  {{ t('components.materialCreateWizard.tabKisteTasche') }}
                                </button>
                              </div>
                            </td>
                            <td>
                              <template v-if="row.mode === 'slot'">
                                <label class="form-label-sm">{{ t('components.materialCreateWizard.labelStorageSiteSm') }}</label>
                                <select
                                  v-model="row.storage_address_id"
                                  class="form-select form-select--sm"
                                  @change="onAllocationStorageAddressChange(row)"
                                >
                                  <option v-for="addr in storageAddresses" :key="addr.id" :value="addr.id">
                                    {{ addr.name || addr.street_line }}
                                  </option>
                                </select>
                                <label class="form-label-sm">{{ t('components.materialCreateWizard.labelRackSm') }}</label>
                                <select
                                  v-model="row.rack_id"
                                  class="form-select form-select--sm"
                                  @change="row.slot_id = ''; void loadSlotsForRack(String(row.rack_id ?? ''))"
                                  @mouseenter="prefetchRackPreview(row.rack_id)"
                                  :title="getRackPreviewTitle(row.rack_id)"
                                >
                                  <option value="" disabled>{{ t('components.materialCreateWizard.selectRackDash') }}</option>
                                  <option
                                    v-for="r in getRacksForAllocationRow(row)"
                                    :key="r.id"
                                    :value="String(r.id)"
                                    :title="getRackPreviewTitle(r.id)"
                                  >
                                    {{ r.name }}
                                  </option>
                                </select>
                                <label class="form-label-sm">{{ t('components.materialCreateWizard.labelSlotSm') }}</label>
                                <select
                                  v-model="row.slot_id"
                                  class="form-select form-select--sm"
                                  :disabled="!row.rack_id"
                                  @mouseenter="prefetchSlotPreview(row.rack_id, row.slot_id)"
                                  :title="getSlotPreviewTitle(row.rack_id, row.slot_id)"
                                >
                                  <option value="" disabled>{{ t('components.materialCreateWizard.selectPickSlotDash') }}</option>
                                  <option
                                    v-for="s in (row.rack_id ? (slotsByRackId[String(row.rack_id)] || []) : [])"
                                    :key="s.id"
                                    :value="String(s.id)"
                                    :title="getSlotPreviewTitle(row.rack_id, s.id)"
                                  >
                                    {{ formatSlotOptionLabel(row.rack_id, s) }}
                                  </option>
                                </select>
                              </template>
                              <template v-else>
                                <label class="form-label-sm">{{ t('components.materialCreateWizard.labelBoxBagSm') }}</label>
                                <select
                                  v-model="row.container_batch_id"
                                  class="form-select form-select--sm"
                                  @mouseenter="prefetchContainerPreviews()"
                                  :title="getContainerPreviewTitle(row.container_batch_id)"
                                >
                                  <option value="">{{ t('components.materialCreateWizard.selectPickBoxDash') }}</option>
                                  <option
                                    v-for="cb in containerBatches"
                                    :key="cb.id"
                                    :value="cb.id"
                                    :disabled="isContainerBatchUsedInOtherRow(initialAllocations, cb.id, row.id)"
                                    :title="
                                      isContainerBatchUsedInOtherRow(initialAllocations, cb.id, row.id)
                                        ? t('components.materialCreateWizard.allocCrateAlreadyUsedInRow')
                                        : formatContainerBatchOptionFullLabel(cb)
                                    "
                                  >
                                    {{ formatContainerBatchOptionFullLabel(cb) }}
                                  </option>
                                </select>
                              </template>
                            </td>
                            <td>
                              <button
                                type="button"
                                class="remove-row-btn"
                                @click="removeAllocationRow(row.id)"
                                :title="t('common.remove')"
                              >×</button>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                    <p
                      v-if="hasDuplicateKisteContainers(initialAllocations)"
                      class="field-hint allocation-duplicate-crate-hint"
                      role="status"
                    >
                      {{ t('components.materialCreateWizard.allocDuplicateCrateHint') }}
                    </p>
                    <p v-if="initialAllocations.length > 0 && !allocationSumValid" class="field-hint is-invalid">
                      {{
                        t('components.materialCreateWizard.allocSumMismatch', {
                          qty: formData.initial_qty,
                          sum: allocationSum,
                        })
                      }}
                    </p>
                  </div>

                  <!-- Einzelner Lagerplatz oder Kiste (wenn keine Aufteilung) -->
                  <div v-if="!((formData.tracking_type === 'bulk' || isAddBatchMode) && formData.split_allocations)" class="form-group">
                    <div class="stock-location-mode mb-2">
                      <label class="form-label-sm">{{ t('components.materialCreateWizard.labelMainStorage') }}</label>
                      <div class="lagerung-switch" role="tablist">
                        <button
                          type="button"
                          class="lagerung-btn"
                          :class="{ active: formData.stock_location_mode === 'slot' }"
                          @click="formData.stock_location_mode = 'slot'; formData.stock_container_batch_id = ''"
                        >
                          {{ t('components.materialCreateWizard.tabGestellFach') }}
                        </button>
                        <button
                          type="button"
                          class="lagerung-btn"
                          :class="{ active: formData.stock_location_mode === 'kiste' }"
                          @click="formData.stock_location_mode = 'kiste'; formData.rack_id = ''; formData.slot_id = ''; formData.location_rack = ''; formData.location_slot = ''"
                        >
                          {{ t('components.materialCreateWizard.tabKisteTasche') }}
                        </button>
                        <button
                          type="button"
                          class="lagerung-btn"
                          :class="{ active: formData.stock_location_mode === 'loose' }"
                          @click="setLooseStorageMode"
                        >
                          {{ t('components.materialCreateWizard.tabLooseStorage') }}
                        </button>
                      </div>
                    </div>
                    <template v-if="formData.stock_location_mode === 'slot'">
                      <StorageLocationPicker
                        variant="compact"
                        class="material-wizard-storage-picker"
                        :rack-id="String(formData.rack_id || '')"
                        :slot-id="String(formData.slot_id || '')"
                        :racks="storageRacks"
                        :slot-list="storageSlots"
                        :rack-label-formatter="formatRackOptionLabel"
                        :rack-option-title-formatter="(r) => rackPreviewTitles[r.id] || ''"
                        :slot-label-formatter="(slot) => formatSlotOptionLabel(String(formData.rack_id || ''), slot)"
                        :slot-option-title-formatter="(s) => slotPreviewTitles[`${String(formData.rack_id || '')}:${String(s.id)}`] || ''"
                        :show-empty-slot-hint="true"
                        :rack-label="t('components.materialCreateWizard.rackLabel')"
                        :slot-label="t('components.materialCreateWizard.slotLabel')"
                        :rack-placeholder="t('components.materialCreateWizard.rackPlaceholderDash')"
                        :slot-placeholder="t('components.materialCreateWizard.slotPlaceholderDash')"
                        @rackListMouseenter="prefetchVisibleRackPreviews(storageRacks)"
                        @slotListMouseenter="prefetchSlotPreviewsForRack(String(formData.rack_id || ''))"
                        @update:rackId="onStorageLocationRackUpdate"
                        @update:slotId="(v) => (formData.slot_id = String(v ?? ''))"
                      />
                    </template>
                    <template v-else-if="formData.stock_location_mode === 'loose'">
                      <p class="field-hint">{{ t('components.materialCreateWizard.looseStorageHint') }}</p>
                    </template>
                    <template v-else>
                      <select
                        v-model="formData.stock_container_batch_id"
                        class="form-select"
                        @mouseenter="prefetchContainerPreviews()"
                        :title="getContainerPreviewTitle(formData.stock_container_batch_id)"
                      >
                        <option value="">{{ t('components.materialCreateWizard.selectPickBoxDash') }}</option>
                        <option
                          v-for="cb in containerBatches"
                          :key="cb.id"
                          :value="cb.id"
                          :title="formatContainerBatchOptionFullLabel(cb)"
                        >
                          {{ formatContainerBatchOptionFullLabel(cb) }}
                        </option>
                      </select>
                    </template>
                  </div>
                    </template>
                  </div>
                  </div>

                  <div
                    v-if="(formData.tracking_type === 'bulk' || isAddBatchMode) && !isFromTemplate && !formData.is_food"
                    class="form-row mb-2"
                  >
                    <label class="checkbox-label material-wizard-container-flag">
                      <input type="checkbox" v-model="formData.is_container" />
                      <span>{{ t('components.materialCreateWizard.checkboxContainerLong') }}</span>
                    </label>
                  </div>

              </div>
            </div>

            <!-- Details (optional) – bei Kombi erst nach Name + Kategorie (gleiche Stufe wie Materialwahl) -->
            <div
              v-if="!isAddBatchMode && !isFromTemplate && creationMode && ((formData.material_type === 'physical' && formData.tracking_type) || ((formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo') && formData.name.trim() && !nameExists && formData.category_id))"
              class="step-section"
              data-step="details"
              data-onboarding="material-wizard-details"
            >
              <div class="step-header step-header--clickable" @click="toggleStep('details')">
                <span class="step-title">{{ t('components.materialCreateWizard.stepDetails') }}</span>
                <span class="step-badge optional">{{ t('common.optional') }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('details') }">▾</span>
              </div>

              <div v-show="isStepOpen('details')" class="step-content">
                <p class="step-hint">{{ t('components.materialCreateWizard.stepDetailsHint') }}</p>

                <!-- Kauf & Lieferant (bei physical + tracking; Add-Batch bleibt im Bestands-Schritt) -->
                <div
                  v-if="formData.material_type === 'physical' && formData.tracking_type"
                  class="details-subsection"
                >
                  <h4 class="subsection-title">{{ t('components.materialCreateWizard.sectionPurchaseAndSupplier') }}</h4>
                  <div class="form-row">
                    <div class="form-group">
                      <label>{{ t('common.manufacturer') }}</label>
                      <div class="autocomplete-wrapper">
                        <input 
                          v-model="manufacturerSearch" 
                          type="text" 
                          class="form-input"
                          :placeholder="t('components.materialCreateWizard.phSearchManufacturer')"
                          @input="searchManufacturers"
                          @focus="showManufacturerDropdown = true"
                          @blur="hideManufacturerDropdownDelayed"
                        />
                        <button
                          type="button"
                          class="add-inline-btn"
                          @click="openAddManufacturerModal"
                          :title="t('components.materialCreateWizard.addManufacturerInlineTitle')"
                        >
                          +
                        </button>
                        <div v-if="showManufacturerDropdown && manufacturerSearch.length >= 2" class="autocomplete-dropdown">
                          <div 
                            v-for="addr in filteredManufacturers" 
                            :key="addr.id"
                            class="autocomplete-item"
                            @mousedown="selectManufacturer(addr)"
                          >
                            <span class="item-name">{{ addr.name || addr.company }}</span>
                            <span class="item-city">{{ addr.city }}</span>
                          </div>
                          <div 
                            v-if="filteredManufacturers.length === 0" 
                            class="autocomplete-item create-new"
                            @mousedown="openAddManufacturerModal"
                          >
                            <span class="item-name">{{
                              t('components.materialCreateWizard.createManufacturerNamed', { name: manufacturerSearch })
                            }}</span>
                          </div>
                        </div>
                      </div>
                      <p v-if="selectedManufacturer" class="selected-address">
                        ✓ {{ selectedManufacturer.name || selectedManufacturer.company }}
                        <button type="button" class="clear-selection" @click="clearManufacturer">×</button>
                      </p>
                    </div>
                    <div class="form-group">
                      <label>{{ t('components.materialCreateWizard.labelPurchasedFrom') }}</label>
                      <div class="autocomplete-wrapper">
                        <input 
                          v-model="supplierSearch" 
                          type="text" 
                          class="form-input"
                          :placeholder="t('components.materialCreateWizard.phSearchSupplier')"
                          @input="searchSuppliers"
                          @focus="showSupplierDropdown = true"
                          @blur="hideSupplierDropdownDelayed"
                        />
                        <button
                          type="button"
                          class="add-inline-btn"
                          @click="openAddSupplierModal"
                          :title="t('components.materialCreateWizard.addSupplierInlineTitle')"
                        >
                          +
                        </button>
                        <div v-if="showSupplierDropdown && supplierSearch.length >= 2" class="autocomplete-dropdown">
                          <div 
                            v-for="addr in filteredSuppliers" 
                            :key="addr.id"
                            class="autocomplete-item"
                            @mousedown="selectSupplier(addr)"
                          >
                            <span class="item-name">{{ addr.name || addr.company }}</span>
                            <span class="item-city">{{ addr.city }}</span>
                          </div>
                          <div 
                            v-if="filteredSuppliers.length === 0" 
                            class="autocomplete-item create-new"
                            @mousedown="openAddSupplierModal"
                          >
                            <span class="item-name">{{
                              t('components.materialCreateWizard.createSupplierNamed', { name: supplierSearch })
                            }}</span>
                          </div>
                        </div>
                      </div>
                      <p v-if="selectedSupplier" class="selected-address">
                        ✓ {{ selectedSupplier.name || selectedSupplier.company }}
                        <button type="button" class="clear-selection" @click="clearSupplier">×</button>
                      </p>
                    </div>
                  </div>

                </div>

                <!-- Material-Details (nicht bei Esswaren) -->
                <div v-if="!formData.is_food" class="details-subsection">
                  <h4 class="subsection-title">{{ t('common.material') }}</h4>
                  <div class="form-grid-details">
                    <div class="form-group">
                      <label>{{ t('components.materialDetail.labelModel') }}</label>
                      <input v-model="formData.model" type="text" class="form-input" />
                    </div>
                  </div>
                </div>

                <!-- Details (Maße, Gewicht, etc.) -->
                <div class="details-subsection">
                  <h4 class="subsection-title">{{ t('components.materialDetail.sectionDetails') }}</h4>
                  <div class="form-grid-details">
                    <MaterialMetricInput
                      v-model="formData.weight"
                      :label="t('components.materialDetail.labelWeightKg')"
                      unit="kg"
                    />
                    <div v-if="!formData.is_food" class="form-group">
                      <label>{{ t('components.materialDetail.labelColor') }}</label>
                      <input v-model="formData.color" type="text" class="form-input" />
                    </div>
                    <div v-if="!formData.is_food" class="form-group">
                      <label>{{ t('components.materialDetail.labelWarranty') }}</label>
                      <input v-model="formData.warranty_until" type="date" class="form-input" />
                    </div>
                  </div>
                  <div class="form-grid-details form-grid-details--dims">
                    <MaterialMetricInput
                      v-model="formData.size_length"
                      :label="wizardSizeLengthLabel"
                      unit="cm"
                    />
                    <MaterialMetricInput
                      v-model="formData.size_width"
                      :label="t('components.materialDetail.labelWidthCm')"
                      unit="cm"
                    />
                    <MaterialMetricInput
                      v-model="formData.size_height"
                      :label="t('components.materialDetail.labelHeightCm')"
                      unit="cm"
                    />
                  </div>
                  <div class="form-group mt-2">
                    <label>{{ t('components.materialDetail.labelDescription') }}</label>
                    <textarea
                      v-model="formData.description"
                      class="form-textarea"
                      rows="3"
                      :placeholder="t('components.materialCreateWizard.phDescriptionOptional')"
                    ></textarea>
                  </div>
                </div>

                <!-- Packmaß (Verpackungseinheit) — nicht bei Esswaren -->
                <div v-if="!formData.is_food" class="details-subsection">
                  <h4 class="subsection-title">{{ t('components.materialDetail.sectionPackDimensions') }}</h4>
                  <p class="step-hint">{{ t('components.materialDetail.packDimensionsHint') }}</p>
                  <div class="form-grid-details">
                    <MaterialMetricInput
                      v-model="formData.pack_weight"
                      :label="t('components.materialDetail.labelPackWeightKg')"
                      unit="kg"
                    />
                    <MaterialMetricInput
                      v-model="formData.pack_size_length"
                      :label="t('components.materialDetail.labelPackLengthCm')"
                      unit="cm"
                    />
                    <MaterialMetricInput
                      v-model="formData.pack_size_width"
                      :label="t('components.materialDetail.labelPackWidthCm')"
                      unit="cm"
                    />
                    <MaterialMetricInput
                      v-model="formData.pack_size_height"
                      :label="t('components.materialDetail.labelPackHeightCm')"
                      unit="cm"
                    />
                  </div>
                </div>

                <!-- Preise & Verrechnung (Verbrauch / Esswaren) -->
                <div
                  v-if="formData.is_consumable || formData.is_food"
                  class="details-subsection"
                  data-onboarding="material-wizard-consumable-pricing"
                >
                  <h4 class="subsection-title">{{ t('components.materialDetail.tabConsumablePricing') }}</h4>
                  <MaterialConsumablePricingPanel
                    v-model:sale-price="formData.sale_price"
                    v-model:reference-purchase-unit-chf="formData.reference_purchase_unit_chf"
                    v-model:external-sale-price-chf="formData.external_sale_price_chf"
                    v-model:pack-sale-price-chf="formData.pack_sale_price_chf"
                    v-model:min-stock="formData.min_stock"
                    :pack-size="formData.pack_size"
                    :pack-unit="formData.pack_unit"
                    :is-meter-stock="stock_unit === 'm'"
                    :batches="wizardDraftBatches"
                    :is-consumable="formData.is_consumable"
                    :is-food="formData.is_food"
                    :total-stock="formData.initial_qty || 0"
                    mode="wizard"
                  />
                  <p class="step-hint mt-2">
                    {{ t('components.materialCreateWizard.hintPackUnitAtQuantityStep') }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Vermietung (optional) – nicht bei Verbrauch / Esswaren -->
            <div
              v-if="!isAddBatchMode && !isFromTemplate && creationMode && ((formData.material_type === 'physical' && formData.tracking_type) || ((formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo') && formData.name.trim() && !nameExists && formData.category_id)) && !formData.is_consumable && !formData.is_food"
              class="step-section"
              data-step="rental"
              data-onboarding="material-wizard-rental"
            >
              <div class="step-header step-header--clickable" @click="toggleStep('rental')">
                <span class="step-title">{{ t('components.materialCreateWizard.stepRental') }}</span>
                <span class="step-badge optional">{{ t('common.optional') }}</span>
                <span class="step-chevron" :class="{ open: isStepOpen('rental') }">▾</span>
              </div>

              <div v-show="isStepOpen('rental')" class="step-content">
                <p class="step-hint">{{ t('components.materialCreateWizard.stepRentalHint') }}</p>
                <div class="details-subsection">
                  <div class="slider-toggle-group pack-toggle-inline mb-3">
                    <label class="toggle-label">
                      <span class="toggle-wrapper">
                        <input type="checkbox" v-model="formData.is_rentable" class="toggle-input" />
                        <span class="toggle-slider toggle-slider--blue"></span>
                      </span>
                      <span class="toggle-text">
                        <span class="toggle-title">{{ t('components.materialDetail.toggleRentableTitle') }}</span>
                        <span class="toggle-desc">{{ t('components.materialDetail.toggleRentableDesc') }}</span>
                      </span>
                    </label>
                  </div>
                  <template v-if="formData.is_rentable">
                  <RentalPriceAmortizationCalculator
                    v-if="formData.material_type === 'physical' && formData.tracking_type && !formData.is_consumable && !formData.is_food"
                    v-model="formData.rental_calc_params"
                    :defaults="rentalAmortDefaults"
                    :historical-basis-chf="wizardRentalHistoricalBasisChf"
                    :piece-count="wizardRentalPieceCount ?? undefined"
                    context="wizard"
                    @apply="onWizardRentalCalculatorApply"
                  />
                  <div class="form-grid-details">
                    <div class="form-group">
                      <label>{{ t('components.materialDetail.historyFields.rental_price_day') }}</label>
                      <div class="price-input">
                        <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                        <input
                          v-model="formData.rental_price_day"
                          type="text"
                          class="form-input"
                          :placeholder="t('components.materialCreateWizard.phPriceZero')"
                        />
                      </div>
                    </div>
                    <div class="form-group">
                      <label>{{ t('components.materialDetail.historyFields.rental_price_week') }}</label>
                      <div class="price-input">
                        <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                        <input
                          v-model="formData.rental_price_week"
                          type="text"
                          class="form-input"
                          :placeholder="t('components.materialCreateWizard.phPriceZero')"
                        />
                      </div>
                    </div>
                    <div class="form-group">
                      <label>{{ t('components.materialDetail.historyFields.rental_price_month') }}</label>
                      <div class="price-input">
                        <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                        <input
                          v-model="formData.rental_price_month"
                          type="text"
                          class="form-input"
                          :placeholder="t('components.materialCreateWizard.phPriceZero')"
                        />
                      </div>
                    </div>
                    <div class="form-group">
                      <label>{{ t('components.materialDetail.historyFields.rental_deposit') }}</label>
                      <div class="price-input">
                        <span class="currency">{{ t('components.materialDetail.currencyFr') }}</span>
                        <input
                          v-model="formData.rental_deposit"
                          type="text"
                          class="form-input"
                          :placeholder="t('components.materialCreateWizard.phPriceZero')"
                        />
                      </div>
                    </div>
                    <div class="form-group">
                      <label>{{ t('components.materialDetail.historyFields.rental_lead_days') }}</label>
                      <input
                        v-model.number="formData.rental_lead_days"
                        type="number"
                        class="form-input"
                        :placeholder="t('components.materialCreateWizard.phDashUnset')"
                      />
                    </div>
                    <div class="form-group">
                      <label>{{ t('components.materialDetail.historyFields.rental_max_days') }}</label>
                      <input
                        v-model.number="formData.rental_max_days"
                        type="number"
                        class="form-input"
                        :placeholder="t('components.materialCreateWizard.phDashUnset')"
                      />
                    </div>
                  </div>
                  <div class="rental-options">
                    <div class="checkbox-group rental-options__checks">
                      <label class="checkbox-label">
                        <input type="checkbox" v-model="formData.rental_external_allowed" />
                        <span>{{ t('components.materialCreateWizard.rentalCheckboxExternalAllowed') }}</span>
                      </label>
                      <label class="checkbox-label">
                        <input type="checkbox" v-model="formData.rental_requires_approval" />
                        <span>{{ t('components.materialCreateWizard.rentalCheckboxRequiresApproval') }}</span>
                      </label>
                    </div>
                    <div v-if="formData.rental_external_allowed" class="form-group rental-options__field">
                      <label>{{ t('components.materialCreateWizard.labelRentalExternalScope') }}</label>
                      <select v-model="formData.rental_scope" class="form-input">
                        <option value="">{{ t('components.materialDetail.reservationUnset') }}</option>
                        <option value="department">{{ t('components.materialCreateWizard.rentalScopeOptionDepartment') }}</option>
                        <option value="organisation">{{ t('components.materialCreateWizard.rentalScopeOptionOrganisation') }}</option>
                        <option value="public">{{ t('components.materialCreateWizard.rentalScopeOptionPublic') }}</option>
                      </select>
                    </div>
                    <div class="form-group rental-options__field">
                      <label>{{ t('components.materialDetail.historyFields.rental_notes') }}</label>
                      <textarea
                        v-model="formData.rental_notes"
                        class="form-textarea"
                        rows="2"
                        :placeholder="t('components.materialCreateWizard.phRentalNotes')"
                      ></textarea>
                    </div>
                  </div>
                  </template>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Right: Sidebar "Mein Material" (Geschwister von material-wizard-content innerhalb body) -->
        <MaterialPreviewSidebar
          :is-add-batch-mode="isAddBatchMode"
          :selected-material-name="selectedExistingMaterial?.name ?? null"
          :material-name="formData.name"
          :category-path="selectedCategory ? getCategoryPath(selectedCategory) : null"
          :material-type="formData.material_type"
          :initial-qty="formData.initial_qty"
          :is-consumable="formData.is_consumable"
          :is-food="formData.is_food"
          :is-js-material="formData.is_js_material"
          :sale-price="formData.sale_price"
          :reference-purchase-unit-chf="formData.reference_purchase_unit_chf"
          :min-stock="formData.min_stock"
          :pack-size="formData.pack_size"
          :pack-unit="formData.pack_unit"
          :pack-sale-price-chf="formData.pack_sale_price_chf"
          :external-source="formData.external_source"
          :is-from-template="isFromTemplate"
          :is-from-container-batch-contents="isFromContainerBatchContents"
          :template-name="selectedTemplate?.name ?? null"
          :tent-capacity="tentForm.tent_capacity"
          :component-inputs="componentInputs"
          :is-component-done="isComponentDone"
          :storage-address-with-location="storageAddressWithLocation"
          :tracking-type="formData.tracking_type"
          :tracking-type-label="formData.tracking_type ? trackingTypeLabels[formData.tracking_type] : ''"
          :combo-articles-count="selectedComboMaterials.length"
          :material-type-labels="materialTypeLabels"
        />
      
        <!-- Ende material-wizard-body -->
      </div>
        <!-- Footer (direkt im Modal, grid-row: 3) -->
        <WizardFooter
          v-model:create-another="createAnother"
          :missing-steps="missingSteps"
          :can-submit="canSubmit"
          :is-submitting="isSubmitting"
          :is-add-batch-mode="isAddBatchMode"
          :is-from-template="isFromTemplate"
          :creation-mode="creationMode"
          @close="handleClose"
          @submit="handleSubmit"
          @jump-to-missing="jumpToMissingStep"
        />
    </div>
    </div>

    <!-- Zentrale Adress-Modal -->
    <AddressModal
      v-if="showAddressModal"
      :department-id="departmentId"
      :default-type="addressModalType === 'manufacturer' ? 'supplier' : addressModalType"
      :default-name="addressModalDefaultName"
      @close="showAddressModal = false"
      @saved="handleAddressSaved"
    />

    <!-- Kategorie-Modal -->
    <CategoryModal
      v-if="showCategoryModal"
      :department-id="departmentId"
      :default-name="categoryModalDefaultName"
      @close="showCategoryModal = false"
      @saved="handleCategorySaved"
    />

    <!-- Kategorie-Dropdown: zweites Kind desselben Teleports (unter body, nach Overlay), damit nichts abgeschnitten wird -->
    <div
      v-if="showDialog && showCategoryDropdown"
      class="autocomplete-dropdown category-dropdown material-wizard-category-dropdown-portal"
      :style="categoryDropdownFixedStyle"
      role="listbox"
    >
      <template v-if="allCategories.length === 0">
        <div class="autocomplete-item autocomplete-empty">
          <span class="item-name">{{ t('components.materialCreateWizard.categoryDropdownEmpty') }}</span>
        </div>
      </template>
      <template v-else>
        <div
          v-for="cat in filteredCategories"
          :key="cat.id"
          class="autocomplete-item"
          :class="{ 'is-child': cat.parent_id }"
          @mousedown.prevent="selectCategory(cat)"
        >
          <span class="item-name">
            <span v-if="cat.parent_id" class="cat-indent">└ </span>{{ cat.name }}
            <span v-if="isRepairPartsCategory(cat)" class="cat-system-hint">
              — {{ t('components.materialCreateWizard.repairPartsCategoryHint') }}
            </span>
          </span>
          <span class="item-count">{{
            t('components.materialCreateWizard.categoryMaterialCount', { n: cat.material_count ?? 0 })
          }}</span>
        </div>
        <div
          v-if="filteredCategories.length === 0 && categorySearch.trim().length > 0 && categorySearch.trim().length < 2"
          class="autocomplete-item autocomplete-empty"
        >
          <span class="item-name">{{ t('components.materialCreateWizard.categoryKeepTypingHint') }}</span>
        </div>
        <div
          v-if="filteredCategories.length === 0 && categorySearch.trim().length >= 2"
          class="autocomplete-item create-new"
          @mousedown.prevent="openAddCategoryModal"
        >
          <span class="item-name">{{
            t('components.materialCreateWizard.createCategoryNamed', { name: categorySearch })
          }}</span>
        </div>
      </template>
    </div>
  </Teleport>

  <EDialog
    v-model="stockLengthDialogOpen"
    :max-width="400"
    :title="t('components.materialDetail.stockUnitLengthDialogTitle')"
  >
    <p class="length-dialog-hint">{{ t('components.materialDetail.stockUnitLengthDialogHint') }}</p>
    <ETextField
      v-model="stockLengthMetersInput"
      type="number"
      min="0.01"
      step="any"
      class="mb-3"
      :label="t('components.materialDetail.stockUnitLengthDialogLabel')"
      :placeholder="t('components.materialDetail.stockUnitLengthDialogPlaceholder')"
      hide-details="auto"
      autofocus
    />
    <template #actions>
      <EButton variant="secondary" size="small" @click="stockLengthDialogOpen = false">
        {{ t('common.cancel') }}
      </EButton>
      <EButton
        variant="primary"
        size="small"
        :disabled="!wizardParsedLengthMeters"
        @click="confirmStockLengthDialog"
      >
        {{ t('common.save') }}
      </EButton>
    </template>
  </EDialog>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  createMaterial,
  getMaterials,
  getMaterial,
  addBatch,
  createComboFromContainerBatch,
  createComboManual,
  type CreateMaterialRequest,
  type AddBatchRequest,
  type CreateComboFromContainerBatchRequest,
  type CreateComboManualRequest,
  type CreateComboManualComponentInput,
  type Material,
  type MaterialBatch,
  type AddBatchMultiResponse,
  type ComponentSource,
} from '@/api/materials'
import { getAddresses, getMaterialWizardSuppliers, type Address } from '@/api/addresses'
import { createCategory, getCategories, type Category } from '@/api/categories'
import { getWorkshopSettings } from '@/api/departmentSettings'
import {
  createStorageRack,
  getRackContents,
  getStorageOverview,
  getContainerBatchContents,
  getContainerBatches,
  type StorageRack,
  type StorageSlot,
  type StorageOverviewResponse,
  type ContainerBatchContentsResponse,
} from '@/api/storageLocations'
import { isContainerNamedStorageSlot } from '@/utils/storageSlotDisplay'
import {
  formatFachSelectPreviewLine,
  formatRackSlotsDirectPreview,
  summarizeMaterialsForPreview,
} from '@/utils/storageSlotContentPreview'
import {
  formatContainerBatchOptionFullLabel,
  formatContainerBatchSelectLabel,
} from '@/utils/containerBatchLabel'
import {
  allocationRowHasDuplicateKiste,
  hasDuplicateKisteContainers,
  isContainerBatchUsedInOtherRow,
} from '@/utils/allocationStorageHints'
import { getTemplates, getTemplate, createMaterialFromTemplate, type Template, type TemplateComponent, type CreateMaterialComponentInput } from '@/api/templates'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useI18n } from 'vue-i18n'
import {
  departmentHasAccountingRole,
  enqueuePendingCostBookingAfterPurchase,
} from '@/composables/useCostBookingFollowUp'
import PurchaseReceiptFileInput from '@/components/material/PurchaseReceiptFileInput.vue'
import { useHeaderNotificationsStore } from '@/stores/headerNotifications'
import AddressModal from '@/components/AddressModal.vue'
import CategoryModal from '@/components/CategoryModal.vue'
import BarcodeScannerPanel from '@/components/common/BarcodeScannerPanel.vue'
import SelectedModeBanner from '@/components/material/wizard/SelectedModeBanner.vue'
import TemplatePickerSection from '@/components/material/wizard/TemplatePickerSection.vue'
import MaterialPreviewSidebar from '@/components/material/wizard/MaterialPreviewSidebar.vue'
import WizardFooter from '@/components/material/wizard/WizardFooter.vue'
import MaterialNameInput from '@/components/material/wizard/MaterialNameInput.vue'
import RentalPriceAmortizationCalculator from '@/components/material/RentalPriceAmortizationCalculator.vue'
import MaterialConsumablePricingPanel from '@/components/material/MaterialConsumablePricingPanel.vue'
import MaterialMetricInput from '@/components/material/MaterialMetricInput.vue'
import { normalizeMaterialMetricInput } from '@/utils/materialMetricUnits'
import { EButton, EDialog, ETextField } from '@/components/form/base'
import {
  applyMaterialUnitSuffixToName,
  getStockUnitLabel,
  hasContentPerPiece,
  isMeterStockUnit,
  isPackagingUnit,
  meterUnitPricePerMeterFromPerPiece,
  parseMaterialChfInput,
  parseSizeLengthCm,
  resolveStoredMeterStockUnitPrice,
  sizeLengthCmToMeters,
} from '@/utils/materialStockUnit'
import {
  filterUserSelectableCategories,
  isRepairPartsCategory,
} from '@/utils/repairPartsCategory'
import {
  getRentalAmortizationDefaults,
  DEFAULT_RENTAL_AMORTIZATION,
  type RentalAmortizationDefaults,
} from '@/api/departmentSettings'
import type { RentalCalcParams } from '@/utils/rentalPriceAmortization'
import MaterialTypeToggles from '@/components/material/wizard/MaterialTypeToggles.vue'
import StorageLocationPicker from '@/components/storage/StorageLocationPicker.vue'
import { createBasicMaterialLookupFetcher } from '@/composables/useMaterialLookup'
import GlobalSearchInput from '@/components/common/GlobalSearchInput.vue'
import type { SearchSuggestion } from '@/composables/useSearchNavigation'
import { useStorageStructure } from '@/composables/useStorageStructure'
import { unitPriceFromPackSaleChf } from '@/utils/packPricing'
import { localizedBarcodeScannerError } from '@/utils/barcodeScannerErrors'
import { buildMaterialWizardPrefillFromMaterial } from '@/utils/materialWizardPrefill'
import { ONBOARDING_TOUR_QUERY, ONBOARDING_TOUR_STEP_QUERY } from '@/config/onboardingTours'
import '@/styles/material-wizard.css'

const route = useRoute()

const props = withDefaults(
  defineProps<{
  departmentId: string
  modelValue: boolean
    /** Onboarding: System-Kategorie «Repair-Parts» nicht anbieten */
    excludeRepairPartsCategory?: boolean
    /** Bestehendes Material als Vorlage (z. B. Werkstatt-Abschreibung → Ersatzteil) */
    prefillFromMaterialId?: string | null
    prefillForceTrackingType?: 'bulk' | 'serialized'
    prefillSourceNote?: string
  }>(),
  {
    excludeRepairPartsCategory: false,
    prefillFromMaterialId: null,
    prefillForceTrackingType: undefined,
    prefillSourceNote: '',
  },
)

/** Zentrale Lagerstruktur: Gestelle + Fächer (gleiche Quelle wie BatchModal, Activities, …) */
const {
  slotsByRackId,
  loadSlotsEnsuringDefault,
  loadRacks,
  racks: racksFromStructure,
} = useStorageStructure(
  () => props.departmentId
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'created': [material: any]
}>()

const toast = useToast()
const { confirm: confirmDialog } = useConfirm()
const { t } = useI18n()

function setLooseStorageMode() {
  formData.stock_location_mode = 'loose'
  formData.stock_container_batch_id = ''
  formData.rack_id = ''
  formData.slot_id = ''
  formData.location_rack = ''
  formData.location_slot = ''
}
const headerNotificationsStore = useHeaderNotificationsStore()
const PACK_UNIT_BUNDLE = 'Bündel'
const articleNameInputRef = ref<HTMLInputElement | null>(null)
const wizardFormRef = ref<HTMLElement | null>(null)
const categoryAutocompleteRef = ref<HTMLElement | null>(null)
/** Fixed-Position unterhalb des Feldes (Teleport zu body), damit nichts vom Wizard-Scroll abgeschnitten wird */
const categoryDropdownFixedStyle = ref<Record<string, string>>({
  position: 'fixed',
  top: '0px',
  left: '0px',
  width: '0px',
  zIndex: '99999',
})
let categoryDropdownPositionListenersBound = false
let categoryPositionHandler: (() => void) | null = null
let openInitRunId = 0

const showDialog = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})
const shouldShowCreationMode = computed(() => !isAddBatchMode.value && !creationMode.value)

const storageAddresses = ref<Address[]>([])
const isSubmitting = ref(false)
const createAnother = ref(false)
const isCheckingName = ref(false)
const nameExists = ref(false)
/** Treffer mit exakt gleichem Namen (für Link + „Bestand hinzufügen“) */
const duplicateNameMaterial = ref<any | null>(null)
const nameSuggestions = ref<any[]>([])
const showNameSuggestions = ref(false)
const isNameInputFocused = ref(false)
let nameCheckTimeout: ReturnType<typeof setTimeout> | null = null
const materialNameLookupFetcher = createBasicMaterialLookupFetcher(() => props.departmentId)
/** Mindestzeichen, bevor die Artikel-Dropdown-Suche läuft */
const NAME_SUGGEST_MIN_CHARS = 2
const NAME_SUGGEST_LIMIT = 10

// Modus: neues Material erstellen oder Batch zu bestehendem hinzufügen
const isAddBatchMode = ref(false)
const selectedExistingMaterial = ref<any>(null)

// Adress-Modal State
const showAddressModal = ref(false)
const addressModalType = ref<'storage' | 'supplier' | 'manufacturer'>('storage')
const addressModalDefaultName = ref('')

// Kategorie State
const showCategoryModal = ref(false)
const categoryModalDefaultName = ref('')
const allCategories = ref<Category[]>([])
const categorySearch = ref('')
const showCategoryDropdown = ref(false)
const filteredCategories = ref<Category[]>([])
const selectedCategory = ref<Category | null>(null)
const showRackDropdown = ref(false)
const filteredRackOptions = ref<StorageRack[]>([])
const isCreatingRack = ref(false)
/** Alle Regale der Abteilung (wird aus useStorageStructure.loadRacks gefüllt) */
const allStorageRacks = ref<StorageRack[]>([])
const storageSlots = ref<StorageSlot[]>([])
const rackPreviewTitles = ref<Record<string, string>>({})
const slotPreviewTitles = ref<Record<string, string>>({})
const containerPreviewTitles = ref<Record<string, string>>({})
const storageOverviewCache = ref<StorageOverviewResponse | null>(null)

// Allokationen für mehrere Lagerplätze (Bulk)
interface AllocationRow {
  id: number
  mode: 'slot' | 'kiste'
  storage_address_id: string
  rack_id: string
  slot_id: string
  container_batch_id: string
  qty: number
}
let allocationIdCounter = 0
const initialAllocations = ref<AllocationRow[]>([])
const containerBatches = ref<import('@/api/storageLocations').ContainerBatch[]>([])

function normalizeAllocationRowsToTotal() {
  if (!formData.split_allocations) return
  if (formData.initial_qty <= 0) {
    initialAllocations.value = []
    return
  }
  if (initialAllocations.value.length === 0) {
    addAllocationRow()
    return
  }
  const sanitizedRows = initialAllocations.value.map((row) => ({
    ...row,
    qty: Math.max(0, Number(row.qty || 0)),
  }))
  // Eine Zeile mit Menge < Soll: nicht die erste Zeile auf die volle Sollmenge hochziehen,
  // sondern eine zweite Zeile mit dem Rest (entspricht „+ Zeile hinzufügen“ mit Restmenge).
  if (sanitizedRows.length === 1) {
    const q0 = sanitizedRows[0].qty
    if (q0 > 0 && q0 < formData.initial_qty) {
      initialAllocations.value = [
        sanitizedRows[0],
        {
          id: ++allocationIdCounter,
          mode: sanitizedRows[0].mode,
          storage_address_id: sanitizedRows[0].storage_address_id || getPreferredStorageAddressId(),
          rack_id: '',
          slot_id: '',
          container_batch_id: '',
          qty: formData.initial_qty - q0,
        },
      ]
      return
    }
  }
  const sumBeforeLast = sanitizedRows
    .slice(0, -1)
    .reduce((sum, row) => sum + row.qty, 0)
  const remainingForLast = Math.max(formData.initial_qty - sumBeforeLast, 0)
  sanitizedRows[sanitizedRows.length - 1].qty = remainingForLast
  initialAllocations.value = sanitizedRows
}

function setAllocationRowMode(row: AllocationRow, mode: 'slot' | 'kiste') {
  if (row.mode === mode) return
  row.mode = mode
  row.rack_id = ''
  row.slot_id = ''
  row.container_batch_id = ''
}

function addAllocationRow() {
  const remainingQty = Math.max(formData.initial_qty - allocationSum.value, 0)
  if (remainingQty <= 0) return
  const lastRow = initialAllocations.value[initialAllocations.value.length - 1]
  const lastMode = lastRow?.mode ?? 'slot'
  initialAllocations.value.push({
    id: ++allocationIdCounter,
    mode: lastMode,
    storage_address_id: lastRow?.storage_address_id ?? getPreferredStorageAddressId(),
    rack_id: '',
    slot_id: '',
    container_batch_id: '',
    qty: remainingQty
  })
}

function removeAllocationRow(id: number) {
  initialAllocations.value = initialAllocations.value.filter((r) => r.id !== id)
  normalizeAllocationRowsToTotal()
}

/** API/JSON kann IDs als Zahl liefern; <select> liefert Strings — vereinheitlichen für Vergleiche und v-model. */
function normalizeStorageSlot(slot: StorageSlot): StorageSlot {
  return {
    ...slot,
    id: String(slot.id),
    rack_id: String(slot.rack_id),
  }
}

function normalizeStorageSlots(slots: StorageSlot[]): StorageSlot[] {
  return slots.map(normalizeStorageSlot)
}

async function loadSlotsForRack(rackId: string): Promise<StorageSlot[]> {
  const id = String(rackId ?? '').trim()
  if (!id) return []
  const raw = await loadSlotsEnsuringDefault(id).catch(() => [] as StorageSlot[])
  const slots = normalizeStorageSlots(raw)
  // String-IDs für <select>-Vergleiche (API kann Zahlen liefern)
  slotsByRackId.value = { ...slotsByRackId.value, [id]: slots }
  await prefetchSlotPreviewsForRack(id)
  return slots
}

function getRacksForAllocationRow(row: AllocationRow): StorageRack[] {
  if (!row.storage_address_id) return allStorageRacks.value
  return allStorageRacks.value.filter((rack) => rack.storage_address_id === row.storage_address_id)
}

function onAllocationStorageAddressChange(row: AllocationRow) {
  row.rack_id = ''
  row.slot_id = ''
}

const allocationSum = computed(() =>
  initialAllocations.value.reduce((sum, r) => sum + (r.qty || 0), 0)
)
const canAddAllocationRow = computed(() =>
  formData.initial_qty > 0 && allocationSum.value < formData.initial_qty
)
const relevantAllocationRows = computed(() =>
  initialAllocations.value.filter((row) => row.qty > 0)
)
const hasInvalidAllocationRows = computed(() =>
  relevantAllocationRows.value.some((row) => {
    if (row.mode === 'slot') return !row.rack_id || !row.slot_id
    if (!row.container_batch_id) return true
    return allocationRowHasDuplicateKiste(row, initialAllocations.value)
  }),
)
const hasRelevantAllocationRows = computed(() => relevantAllocationRows.value.length > 0)
const allocationSumValid = computed(() =>
  formData.initial_qty > 0 && allocationSum.value === formData.initial_qty
)

// Hersteller Autocomplete
const manufacturerSearch = ref('')
const showManufacturerDropdown = ref(false)
const allManufacturers = ref<Address[]>([])
const filteredManufacturers = ref<Address[]>([])
const selectedManufacturer = ref<Address | null>(null)

// Lieferant Autocomplete
const supplierSearch = ref('')
const showSupplierDropdown = ref(false)
const allSuppliers = ref<Address[]>([])
const filteredSuppliers = ref<Address[]>([])
const selectedSupplier = ref<Address | null>(null)

// Kombinations-Materialien
const comboMaterialSearch = ref('')
const allMaterials = ref<any[]>([])
const filteredComboMaterials = ref<any[]>([])
interface ComboArticleInput {
  draftId: string
  id: string
  name: string
  category?: { name?: string }
  total_stock: number
  free_stock?: number
  tracking: 'serialized' | 'bulk'
  qty: number
  mode: 'new' | 'existing'
  component_source: ComponentSource
  serial_number: string
  unit_price: string
  batch_id: string
  is_linked_container: boolean
  is_container_material?: boolean
  _materialSearch?: string
  _availableBatches?: MaterialBatch[]
  _nameExists?: boolean
  _duplicateMaterial?: { id: string; name: string } | null
  _nameSuggestions?: Array<{
    id: string
    name: string
    category?: { name?: string }
    total_stock: number
    free_stock?: number
    tracking_type?: string
  }>
  _showNameSuggestions?: boolean
  _isCheckingName?: boolean
}

const selectedComboMaterials = ref<ComboArticleInput[]>([])
let comboDraftCounter = 0

// ============ Template-Modus ============
const isFromTemplate = ref(false)
const isFromContainerBatchContents = ref(false)
const containerContentsBatchId = ref('')
const selectedContainerBatchContents = ref<ContainerBatchContentsResponse | null>(null)
const isLoadingContainerContents = ref(false)
const selectedTemplate = ref<Template | null>(null)
const hasTemplateConfigurator = computed(() => {
  const tpl = selectedTemplate.value
  if (!tpl) return false
  return (tpl.option_groups?.length ?? 0) > 0 || (tpl.options?.length ?? 0) > 0
})

const stock_unit = ref<'Stk' | 'm'>('Stk')
const qtyEntryMode = ref<'base' | 'pack' | 'content'>('base')
const stockLengthDialogOpen = ref(false)
const stockLengthMetersInput = ref('')

/** Esswaren: zusätzliche Chargen beim Anlegen (je Menge + Kaufdatum + Ablaufdatum). */
type FoodExtraLotDraft = { id: string; qty: number; purchase_date: string; expiry_date: string }
let foodExtraLotSeq = 0
const foodExtraLots = ref<FoodExtraLotDraft[]>([])

function addFoodExtraLot() {
  foodExtraLots.value.push({
    id: `food-extra-${++foodExtraLotSeq}`,
    qty: 0,
    purchase_date: formData.purchase_date || getTodayIso(),
    expiry_date: '',
  })
}

function removeFoodExtraLot(index: number) {
  foodExtraLots.value.splice(index, 1)
}

function clearFoodExtraLots() {
  foodExtraLots.value = []
}

const foodExtraLotsValid = computed(() => {
  if (!formData.is_food || isAddBatchMode.value) return true
  return foodExtraLots.value.every((lot) => lot.qty >= 1 && !!lot.expiry_date)
})

const foodLotsTotalQty = computed(() => {
  if (!formData.is_food || isAddBatchMode.value) {
    return Math.max(0, Math.floor(Number(formData.initial_qty) || 0))
  }
  const base = Math.max(0, Math.floor(Number(formData.initial_qty) || 0))
  const extra = foodExtraLots.value.reduce(
    (sum, lot) => sum + (lot.qty >= 1 ? Math.floor(lot.qty) : 0),
    0,
  )
  return base + extra
})

function locationPayloadForExtraFoodLot(): Pick<
  AddBatchRequest,
  'rack_id' | 'slot_id' | 'container_batch_id'
> {
  if (formData.split_allocations && hasRelevantAllocationRows.value) {
    const first = initialAllocations.value.find(
      (r) => r.qty > 0 && (r.mode === 'slot' ? r.rack_id : r.container_batch_id),
    )
    if (first?.mode === 'kiste' && first.container_batch_id) {
      return { container_batch_id: first.container_batch_id, rack_id: null, slot_id: null }
    }
    if (first?.mode === 'slot' && first.rack_id) {
      return {
        rack_id: first.rack_id,
        slot_id: first.slot_id || null,
        container_batch_id: undefined,
      }
    }
  }
  if (formData.stock_location_mode === 'kiste' && formData.stock_container_batch_id) {
    return {
      container_batch_id: formData.stock_container_batch_id,
      rack_id: null,
      slot_id: null,
    }
  }
  return {
    rack_id: formData.rack_id || null,
    slot_id: formData.slot_id || null,
    container_batch_id: undefined,
  }
}

const stockUnitOptions = computed(() => [
  { value: 'Stk' as const, label: t('workshop.repairPartsList.unitStkShort') },
  { value: 'm' as const, label: 'm' },
])

const wizardMeterPieceLengthM = computed(() => sizeLengthCmToMeters(formData.size_length))

const wizardUseMeterQtyByCount = computed(
  () =>
    stock_unit.value === 'm' &&
    wizardMeterPieceLengthM.value != null &&
    !wizardCanUsePackEntry.value,
)

const wizardPurchasePriceLabel = computed(() => {
  const per = wizardMeterPieceLengthM.value
  if (wizardUseMeterQtyByCount.value && per != null) {
    return t('components.batchModal.unitPricePerPiece', { per })
  }
  return t('components.materialCreateWizard.purchasePriceChfPerPc')
})

function resolveWizardStoredUnitPrice(): string | null {
  return resolveStoredMeterStockUnitPrice(
    formData.unit_price,
    wizardUseMeterQtyByCount.value,
    wizardMeterPieceLengthM.value,
  )
}

function resolveWizardReferencePurchaseFromUnitPrice(uiUnitPrice: number): number {
  if (!Number.isFinite(uiUnitPrice) || uiUnitPrice < 0) return 0
  if (uiUnitPrice === 0) return 0
  const per = wizardMeterPieceLengthM.value
  if (wizardUseMeterQtyByCount.value && per != null) {
    const perM = meterUnitPricePerMeterFromPerPiece(uiUnitPrice, per)
    return perM ?? uiUnitPrice
  }
  return uiUnitPrice
}

const wizardSizeLengthLabel = computed(() => {
  const base = t('components.materialDetail.labelLengthCm')
  return stock_unit.value === 'm' ? `${base} *` : base
})

const wizardParsedLengthMeters = computed((): number | null => {
  const raw = stockLengthMetersInput.value.trim()
  if (!raw) return null
  const n = Number(raw.replace(',', '.'))
  if (!Number.isFinite(n) || n <= 0) return null
  return n
})

const wizardEffectivePackUnit = computed((): string | null => {
  if (isAddBatchMode.value && selectedExistingMaterial.value) {
    const m = selectedExistingMaterial.value
    if (isMeterStockUnit(m.pack_unit) && m.packaging_unit) return m.packaging_unit
    return m.pack_unit ?? null
  }
  if (stock_unit.value === 'm') {
    if (packUnitEnabled.value && formData.pack_unit && isPackagingUnit(formData.pack_unit)) {
      return formData.pack_unit
    }
    return stock_unit.value
  }
  if (packUnitEnabled.value && formData.pack_unit && isPackagingUnit(formData.pack_unit)) {
    return formData.pack_unit
  }
  return stock_unit.value
})

const wizardEffectivePackSize = computed((): number | null => {
  if (isAddBatchMode.value && selectedExistingMaterial.value) {
    const size = selectedExistingMaterial.value.pack_size
    return size && size >= 2 ? size : null
  }
  if (packUnitEnabled.value && isPackagingUnit(formData.pack_unit)) {
    return formData.pack_size && formData.pack_size >= 2 ? formData.pack_size : null
  }
  return null
})

const wizardStockUnitLabel = computed(() =>
  stock_unit.value === 'm' ? 'm' : getStockUnitLabel(wizardEffectivePackUnit.value),
)

const wizardQuantityLabel = computed(() => {
  if (wizardUseMeterQtyByCount.value) return t('components.batchModal.quantityCountLabel')
  return t('components.batchModal.quantityWithUnit', { unit: wizardStockUnitLabel.value })
})

const wizardStockUnitHint = computed(() => {
  if (isAddBatchMode.value) return ''
  if (stock_unit.value === 'm') return t('components.materialDetail.stockUnitHintMeterWithLength')
  return t('components.materialCreateWizard.stockUnitHintStk')
})

function setStockUnit(unit: 'Stk' | 'm') {
  if (unit === 'm' && !parseSizeLengthCm(formData.size_length)) {
    stockLengthMetersInput.value = ''
    stockLengthDialogOpen.value = true
    return
  }
  stock_unit.value = unit
  qtyEntryMode.value = 'base'
}

function confirmStockLengthDialog() {
  const meters = wizardParsedLengthMeters.value
  if (meters == null) return
  formData.size_length = String(Math.round(meters * 100))
  stock_unit.value = 'm'
  qtyEntryMode.value = 'base'
  if (packUnitEnabled.value && (!formData.pack_size || formData.pack_size < 2)) {
    formData.pack_size = Math.max(2, Math.round(meters))
  }
  stockLengthDialogOpen.value = false
  stockLengthMetersInput.value = ''
}

function setQtyEntryMode(mode: 'base' | 'pack' | 'content') {
  qtyEntryMode.value = mode
}

const wizardCanUsePackEntry = computed(
  () => isPackagingUnit(wizardEffectivePackUnit.value) && !!wizardEffectivePackSize.value,
)
const wizardCanUseContentEntry = computed(() =>
  hasContentPerPiece(wizardEffectivePackUnit.value, wizardEffectivePackSize.value),
)
const wizardShowQtyEntryModes = computed(
  () => wizardCanUsePackEntry.value || wizardCanUseContentEntry.value,
)

const wizardPackUnitLabel = computed(() => wizardEffectivePackUnit.value || '')

const wizardQtyPlaceholder = computed(() => {
  if (qtyEntryMode.value === 'pack' && wizardPackUnitLabel.value) {
    return t('components.batchModal.qtyPlaceholderPack', { unit: wizardPackUnitLabel.value })
  }
  if (wizardUseMeterQtyByCount.value) {
    return t('components.batchModal.qtyPlaceholderCount')
  }
  if (qtyEntryMode.value === 'content' || stock_unit.value === 'm') {
    return t('components.batchModal.qtyPlaceholderMeters')
  }
  return t('components.materialCreateWizard.phQtyPlaceholder')
})

const wizardDisplayQty = computed({
  get(): number {
    const per = wizardMeterPieceLengthM.value
    if (wizardUseMeterQtyByCount.value && per) {
      return formData.initial_qty > 0 ? Math.max(1, Math.round(formData.initial_qty / per)) : 0
    }
    const size = wizardEffectivePackSize.value || 0
    if (qtyEntryMode.value === 'pack' && wizardCanUsePackEntry.value && size > 0) {
      return formData.initial_qty > 0 ? Math.max(1, Math.round(formData.initial_qty / size)) : 0
    }
    if (qtyEntryMode.value === 'content' && wizardCanUseContentEntry.value && size > 0) {
      return formData.initial_qty > 0 ? formData.initial_qty * size : 0
    }
    return formData.initial_qty
  },
  set(raw: number) {
    const n = Number(raw)
    const per = wizardMeterPieceLengthM.value
    if (wizardUseMeterQtyByCount.value && per) {
      if (!Number.isFinite(n) || n < 0) {
        formData.initial_qty = 0
        return
      }
      formData.initial_qty = n > 0 ? Math.max(1, Math.round(n * per)) : 0
      return
    }
    const size = wizardEffectivePackSize.value || 0
    if (!Number.isFinite(n) || n < 0) {
      formData.initial_qty = 0
      return
    }
    if (qtyEntryMode.value === 'pack' && wizardCanUsePackEntry.value && size > 0) {
      formData.initial_qty = n > 0 ? Math.max(1, Math.round(n * size)) : 0
      return
    }
    if (qtyEntryMode.value === 'content' && wizardCanUseContentEntry.value && size > 0) {
      formData.initial_qty = n > 0 ? Math.max(1, Math.round(n / size)) : 0
      return
    }
    formData.initial_qty = Math.max(0, Math.round(n))
  },
})

const wizardQtyEntryHint = computed(() => {
  const per = wizardMeterPieceLengthM.value
  if (wizardUseMeterQtyByCount.value && per) {
    return t('components.batchModal.meterQtyTotalHint', {
      count: wizardDisplayQty.value,
      per,
      total: formData.initial_qty,
    })
  }
  const size = wizardEffectivePackSize.value || 0
  if (qtyEntryMode.value === 'pack' && wizardCanUsePackEntry.value && wizardPackUnitLabel.value) {
    return t('components.batchModal.qtyHintPack', {
      count: wizardDisplayQty.value,
      unit: wizardPackUnitLabel.value,
      total: formData.initial_qty,
      stockUnit: wizardStockUnitLabel.value,
    })
  }
  if (qtyEntryMode.value === 'content' && wizardCanUseContentEntry.value && size > 0) {
    return t('components.batchModal.qtyHintContent', {
      meters: wizardDisplayQty.value,
      per: size,
      pieces: formData.initial_qty,
    })
  }
  if (wizardCanUseContentEntry.value && qtyEntryMode.value === 'base') {
    return t('components.batchModal.qtyHintPerPiece', { per: size, unit: 'm' })
  }
  if (wizardCanUsePackEntry.value && qtyEntryMode.value === 'base' && wizardPackUnitLabel.value) {
    return t('components.batchModal.qtyHintPackSize', { per: size, unit: wizardPackUnitLabel.value })
  }
  return ''
})

function resolveWizardPackFields(): {
  pack_unit: string | null
  pack_size: number | null
  packaging_unit: string | null
} {
  if (stock_unit.value === 'm') {
    if (packUnitEnabled.value && formData.pack_unit && isPackagingUnit(formData.pack_unit)) {
      return {
        pack_unit: 'm',
        pack_size: formData.pack_size && formData.pack_size >= 2 ? formData.pack_size : null,
        packaging_unit: formData.pack_unit,
      }
    }
    return { pack_unit: 'm', pack_size: null, packaging_unit: null }
  }
  if (packUnitEnabled.value && formData.pack_unit && isPackagingUnit(formData.pack_unit)) {
    return {
      pack_unit: formData.pack_unit,
      pack_size: formData.pack_size && formData.pack_size >= 2 ? formData.pack_size : null,
      packaging_unit: null,
    }
  }
  return { pack_unit: stock_unit.value === 'Stk' ? 'Stk' : null, pack_size: null, packaging_unit: null }
}

watch(stock_unit, () => {
  qtyEntryMode.value = 'base'
})

// Verpackungseinheit Toggle – setzt Verpackungsfelder zurück wenn deaktiviert
const packUnitEnabled = computed({
  get: () => {
    return !!(formData.pack_size && formData.pack_unit && isPackagingUnit(formData.pack_unit))
  },
  set: (val: boolean) => {
    if (!val) {
      if (isPackagingUnit(formData.pack_unit)) {
      formData.pack_size = null
      formData.pack_unit = ''
      }
      formData.pack_sale_price_chf = null
    } else if (!formData.pack_size) {
      const fromLength = wizardMeterPieceLengthM.value
      formData.pack_size =
        stock_unit.value === 'm' && fromLength != null && fromLength >= 2
          ? Math.round(fromLength)
          : 10
      if (!formData.pack_unit) formData.pack_unit = 'Rolle'
    }
  },
})

/** Stückpreis aus Packungspreis ÷ Stück pro Einheit (Verbrauch/Essen, wenn beides gesetzt). */
const packSaleToUnitSaleChf = computed(() => {
  if (!(formData.is_consumable || formData.is_food)) return null
  const pp = formData.pack_sale_price_chf
  const ps = formData.pack_size
  if (pp == null || ps == null) return null
  return unitPriceFromPackSaleChf(Number(pp), Number(ps))
})

function applyPackSaleToWizardUnitSale() {
  const v = packSaleToUnitSaleChf.value
  if (v == null) return
  formData.sale_price = v
  toast.success(t('components.materialCreateWizard.toastPackUnitPriceApplied'))
}

/** Letzter aus Verpackungspreis ÷ Stück/VE berechneter Stückpreis (für Anschaffung/Verkauf). */
let lastPackDerivedPurchaseUnitChf: number | null = null

function canOverwritePriceWithPackDerived(
  current: number | null | undefined,
  lastAuto: number | null,
): boolean {
  const n = Number(current)
  if (!Number.isFinite(n) || n <= 0) return true
  if (lastAuto == null) return false
  return Math.abs(n - lastAuto) < 0.001
}

/** Anschaffung (und optional Verkauf/Referenz) aus Verpackungspreis ÷ Stück pro VE. */
function syncPurchasePricesFromPackSale() {
  const derived = packSaleToUnitSaleChf.value
  if (derived == null) {
    lastPackDerivedPurchaseUnitChf = null
    return
  }

  if (purchasePriceInputMode.value === 'unit') {
    if (canOverwritePriceWithPackDerived(formData.unit_price, lastPackDerivedPurchaseUnitChf)) {
      formData.unit_price = derived
    }
  }

  if (formData.is_consumable || formData.is_food) {
    if (canOverwritePriceWithPackDerived(formData.sale_price, lastPackDerivedPurchaseUnitChf)) {
      formData.sale_price = derived
    }
    if (
      canOverwritePriceWithPackDerived(
        formData.reference_purchase_unit_chf,
        lastPackDerivedPurchaseUnitChf,
      )
    ) {
      formData.reference_purchase_unit_chf = resolveWizardReferencePurchaseFromUnitPrice(derived)
    }
  }

  lastPackDerivedPurchaseUnitChf = derived
}

const templateSearch = ref('')
const availableTemplates = ref<Template[]>([])
const filteredTemplateList = ref<Template[]>([])
const showTemplateDropdown = ref(false)
const templateComponents = ref<TemplateComponent[]>([])

// Benutzereingaben pro Template-Komponente
interface ComponentInput {
  component_type: string
  name: string
  tracking: 'serialized' | 'bulk'
  required_qty: number
  is_optional: boolean
  component_source: ComponentSource
  mode: 'new' | 'existing'
  serial_number: string
  qty: number
  unit_price: string
  material_id: string
  batch_id: string
  assignment_mode: 'fixed' | 'assigned' | 'on_issue' | 'bulk'
  // UI-Hilfsfelder für "aus Bestand" Modus
  _materialSearch?: string
  _showDropdown?: boolean
  _filteredMaterials?: any[]
  _selectedMaterial?: any
  _availableBatches?: any[]
  _match_state?: 'found' | 'missing' | 'ambiguous'
  _expected_name?: string
  _candidates?: { id: string; name: string }[]
}
const componentInputs = ref<ComponentInput[]>([])

type StepId =
  | 'creation_mode'
  | 'general'
  | 'category'
  | 'template_components'
  | 'template_tent'
  | 'template_purchase'
  | 'tracking'
  | 'combo_articles'
  | 'details'
  | 'rental'
  | 'stock'
  | 'storage'

const activeStep = ref<StepId | ''>('')
const expandAllVisibleSteps = ref(true)
/** true: nur ein Schritt aufgeklappt (manuell per Kopfzeile oder Fokus); false: Auto-Sync mit letztem Schritt. */
const accordionUserControlled = ref(false)
/** Während Vorlagen-Laden: keine Schritt-Syncs / kein Kategorie-Sprung, bis „Allgemeines“ gesetzt ist */
const templateLoadInProgress = ref(false)

/**
 * Material-Touren steuern das Wizard-Akkordeon: sonst springt Blur vom Artikelnamen
 * sofort auf Kategorie und blendet Name/Toggles aus — Spotlight und Tour-Text passen dann nicht.
 */
function wizardAccordionStepForMaterialTour(): StepId | null {
  const tourId = route.query[ONBOARDING_TOUR_QUERY]
  const step = Number(route.query[ONBOARDING_TOUR_STEP_QUERY] || 0)
  if (tourId === 'material-create') {
    if (step === 4 || step === 5) return 'general'
    if (step === 6) return 'category'
    if (step === 7) return 'tracking'
    if (step >= 8 && step <= 12) return 'stock'
    if (step === 13 || step === 14) return 'storage'
    if (step === 15) return 'details'
    if (step === 16) return 'rental'
  }
  if (tourId === 'material-consumable') {
    if (step === 4 || step === 5) return 'general'
    if (step === 6) return 'category'
    if (step === 7) return 'tracking'
    if (step === 8 || step === 9) return 'stock'
    if (step === 10) return 'details'
  }
  if (tourId === 'material-food') {
    if (step === 4 || step === 5) return 'general'
    if (step === 6) return 'category'
    if (step === 7) return 'stock'
    if (step === 8) return 'details'
  }
  return null
}

// Erstellungsmodus
const creationMode = ref<'' | 'individual' | 'physical_combo' | 'virtual_combo'>('')
const getTodayIso = () => new Date().toISOString().slice(0, 10)

watch(
  () =>
    [
      route.query[ONBOARDING_TOUR_QUERY],
      route.query[ONBOARDING_TOUR_STEP_QUERY],
      showDialog.value,
      creationMode.value,
    ] as const,
  async () => {
    if (!showDialog.value || !creationMode.value) return
    const target = wizardAccordionStepForMaterialTour()
    if (!target) return
    expandAllVisibleSteps.value = false
    accordionUserControlled.value = true
    activeStep.value = target
    await nextTick()
    requestAnimationFrame(() => {
      const onboardingByStep: Record<string, string> = {
        general: 'material-wizard-article-name',
        category: 'material-wizard-category',
        tracking: 'material-wizard-tracking',
        stock: 'material-wizard-stock-unit',
        storage: 'material-wizard-stock-storage',
        details: 'material-wizard-details',
        rental: 'material-wizard-rental',
      }
      const tourStep = Number(route.query[ONBOARDING_TOUR_STEP_QUERY] || 0)
      const stockByTourStep: Record<number, string> = {
        8: 'material-wizard-stock-unit',
        9: 'material-wizard-stock-qty',
        10: 'material-wizard-stock-purchase-date',
        11: 'material-wizard-stock-purchase-price',
        12: 'material-wizard-stock-codes',
        13: 'material-wizard-stock-split',
        14: 'material-wizard-stock-storage',
      }
      const consumableByTourStep: Record<number, string> = {
        7: 'material-wizard-tracking',
        8: 'material-wizard-stock-unit',
        9: 'material-wizard-stock-qty',
        10: 'material-wizard-consumable-pricing',
      }
      const foodByTourStep: Record<number, string> = {
        4: 'material-wizard-article-name',
        5: 'material-wizard-toggle-food',
        7: 'material-wizard-food-lots',
        8: 'material-wizard-consumable-pricing',
      }
      const tourId = route.query[ONBOARDING_TOUR_QUERY]
      const attr =
        tourId === 'material-food' && foodByTourStep[tourStep]
          ? foodByTourStep[tourStep]
          : tourId === 'material-consumable' && consumableByTourStep[tourStep]
            ? consumableByTourStep[tourStep]
            : (target === 'stock' || target === 'storage') && stockByTourStep[tourStep]
              ? stockByTourStep[tourStep]
              : onboardingByStep[target]
      if (!attr) return
      const el = document.querySelector(`[data-onboarding="${attr}"]`) as HTMLElement | null
      const form = document.querySelector('.material-wizard-form') as HTMLElement | null
      if (el && form) {
        const parentRect = form.getBoundingClientRect()
        const elRect = el.getBoundingClientRect()
        const offset = elRect.top - parentRect.top + form.scrollTop
        form.scrollTo({ top: Math.max(0, offset - 32), behavior: 'auto' })
      } else {
        el?.scrollIntoView({ behavior: 'auto', block: 'nearest' })
      }
    })
  },
)

// Zelt-Details
const tentForm = reactive({
  tent_type: '' as string,
  tent_capacity: null as number | null,
})

const formData = reactive({
  name: '',
  storage_address_id: '',
  location_rack: '',
  location_slot: '',
  rack_id: '',
  slot_id: '',
  category_id: '' as string,
  material_type: '' as '' | 'physical' | 'physical_combo' | 'virtual_combo',
  tracking_type: '' as '' | 'serialized' | 'bulk',
  is_consumable: false,
  is_food: false,
  is_js_material: false,
  external_source: '' as string,
  sale_price: null as number | null,
  external_sale_price_chf: null as number | null,
  reference_purchase_unit_chf: null as number | null,
  min_stock: null as number | null,
  pack_size: null as number | null,
  pack_unit: '' as string,
  pack_sale_price_chf: null as number | null,
  pack_weight: '' as string,
  pack_size_length: '' as string,
  pack_size_width: '' as string,
  pack_size_height: '' as string,
  initial_qty: 0,
  purchase_date: getTodayIso(),
  expiry_date: '',
  manufacturer: '',
  supplier_id: '',
  unit_price: 0,
  invoice_number: '',
  notes: '',
  // Details (wie in MaterialDetailView)
  barcode_tag: '' as string,
  description: '' as string,
  model: '' as string,
  ean: '' as string,
  weight: '' as string,
  color: '' as string,
  size_length: '' as string,
  size_width: '' as string,
  size_height: '' as string,
  warranty_until: '' as string,
  // Vermietung
  rental_price_day: '' as string,
  rental_price_week: '' as string,
  rental_price_month: '' as string,
  rental_deposit: '' as string,
  rental_lead_days: null as number | null,
  rental_max_days: null as number | null,
  rental_external_allowed: false,
  is_rentable: true,
  rental_scope: '' as string,
  rental_requires_approval: false,
  rental_notes: '' as string,
  rental_calc_params: null as RentalCalcParams | null,
  split_allocations: false,
  stock_location_mode: 'slot' as 'slot' | 'kiste' | 'loose',
  stock_container_batch_id: '' as string,
  /** Massenartikel: gesamter Artikel ist Behälter; bei serialisiert pro Zeile in serialNumbers */
  is_container: false
})

const rentalAmortDefaults = ref<RentalAmortizationDefaults>({ ...DEFAULT_RENTAL_AMORTIZATION })

/** Alle Regale der Abteilung aus der zentralen API aktualisieren */
async function refreshDepartmentRacks(): Promise<void> {
  await loadRacks().catch(() => [])
  allStorageRacks.value = [...racksFromStructure.value]
}

/** Regale für gewählten Lagerstandort (ohne zweiten API-Call beim Wechsel) */
const storageRacks = computed(() => {
  const all = allStorageRacks.value
  const addr = String(formData.storage_address_id ?? '').trim()
  if (!addr) return all
  return all.filter(
    (rack) => String(rack.storage_address_id ?? '').trim() === addr
  )
})

/** Physische Kombo „Gestell/Fach“: Fächer mit Kisten-Namen nur unter „Kiste/Tasche“. */
const slotsForPhysicalComboGestellFach = computed(() => {
  if (creationMode.value !== 'physical_combo' || formData.stock_location_mode !== 'slot') {
    return storageSlots.value
  }
  return storageSlots.value.filter((s) => !isContainerNamedStorageSlot(s.name))
})

watch(
  () => [slotsForPhysicalComboGestellFach.value, formData.slot_id, creationMode.value, formData.stock_location_mode] as const,
  () => {
    if (creationMode.value !== 'physical_combo' || formData.stock_location_mode !== 'slot') return
    const allowed = new Set(slotsForPhysicalComboGestellFach.value.map((s) => String(s.id)))
    if (formData.slot_id && !allowed.has(String(formData.slot_id))) {
      formData.slot_id = ''
    }
  }
)

// Seriennummern für serialisierte Artikel
interface SerialNumberEntry {
  id: number
  serial_number: string
  label: string
  notes: string
  /** eMatChef-/Intern-Code (scanbar) */
  barcode_tag: string
  /** Hersteller-/Fremdcode / EAN (scanbar) */
  ean: string
  /** Diese Instanz ist ein Behälter (erscheint in Container-Listen) */
  is_container: boolean
  location_mode: 'slot' | 'kiste'
  storage_address_id: string
  rack_id: string
  slot_id: string
  container_batch_id: string
}
const serialNumbers = ref<SerialNumberEntry[]>([])
let serialIdCounter = 0
const serialScannerActive = ref(false)
const serialScannerTargetId = ref<number | null>(null)
const showExpiryDateForNonFood = ref(false)

// Automatisch erzeugen
const autoGenPrefix = ref('')
const autoGenStart = ref(1)
const autoGenPad = ref(3)
const autoGenCount = ref(5)
const serialAutoGenerateEnabled = ref(false)
const serialLocationSameForAll = ref(true)
const serialAllAsContainers = ref(false)

watch(serialAllAsContainers, (value) => {
  for (const entry of serialNumbers.value) {
    entry.is_container = value
  }
})

const suggestedSerialPrefix = computed(() => {
  const name = (formData.name || '').trim()
  return name ? `${name}-` : ''
})

type WizardStockPrefs = {
  storage_address_id?: string
  stock_location_mode?: 'slot' | 'kiste' | 'loose'
  autoGenPrefix?: string
  autoGenStart?: number
  autoGenPad?: number
  autoGenCount?: number
  serialAutoGenerateEnabled?: boolean
  serialLocationSameForAll?: boolean
  serialAllAsContainers?: boolean
}

const wizardStockPrefsKey = computed(() => `materialWizard.stockPrefs.${props.departmentId}`)

function loadWizardStockPrefs(): WizardStockPrefs {
  try {
    const raw = localStorage.getItem(wizardStockPrefsKey.value)
    if (!raw) return {}
    const parsed = JSON.parse(raw) as WizardStockPrefs
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

function saveWizardStockPrefs() {
  try {
    const payload: WizardStockPrefs = {
      storage_address_id: formData.storage_address_id || '',
      stock_location_mode: formData.stock_location_mode,
      autoGenPrefix: autoGenPrefix.value || '',
      autoGenStart: Math.max(1, autoGenStart.value || 1),
      autoGenPad: Math.max(1, Math.min(6, autoGenPad.value || 3)),
      autoGenCount: Math.max(1, Math.min(100, autoGenCount.value || 1)),
      serialAutoGenerateEnabled: !!serialAutoGenerateEnabled.value,
      serialLocationSameForAll: !!serialLocationSameForAll.value,
      serialAllAsContainers: !!serialAllAsContainers.value,
    }
    localStorage.setItem(wizardStockPrefsKey.value, JSON.stringify(payload))
  } catch {
    // Ignore localStorage failures (private mode, quota, etc.)
  }
}

function applyWizardStockPrefs() {
  const prefs = loadWizardStockPrefs()
  if (prefs.storage_address_id) formData.storage_address_id = String(prefs.storage_address_id)
  if (
    prefs.stock_location_mode === 'slot' ||
    prefs.stock_location_mode === 'kiste' ||
    prefs.stock_location_mode === 'loose'
  ) {
    formData.stock_location_mode = prefs.stock_location_mode
  }
  if (typeof prefs.autoGenPrefix === 'string') {
    const normalizedPrefix = prefs.autoGenPrefix.trim()
    autoGenPrefix.value = normalizedPrefix.toUpperCase() === 'KISTE-' ? '' : prefs.autoGenPrefix
  }
  if (typeof prefs.autoGenStart === 'number') autoGenStart.value = Math.max(1, prefs.autoGenStart)
  if (typeof prefs.autoGenPad === 'number') autoGenPad.value = Math.max(1, Math.min(6, prefs.autoGenPad))
  if (typeof prefs.autoGenCount === 'number') autoGenCount.value = Math.max(1, Math.min(100, prefs.autoGenCount))
  if (typeof prefs.serialAutoGenerateEnabled === 'boolean') serialAutoGenerateEnabled.value = prefs.serialAutoGenerateEnabled
  if (typeof prefs.serialLocationSameForAll === 'boolean') serialLocationSameForAll.value = prefs.serialLocationSameForAll
  if (typeof prefs.serialAllAsContainers === 'boolean') serialAllAsContainers.value = prefs.serialAllAsContainers
}

const autoGenPreview = computed(() => {
  const prefix = (autoGenPrefix.value || '').trim() || suggestedSerialPrefix.value
  const start = Math.max(1, autoGenStart.value)
  const pad = Math.max(1, Math.min(6, autoGenPad.value || 3))
  const count = Math.max(1, Math.min(100, autoGenCount.value || 1))
  if (count <= 2) {
    return Array.from({ length: count }, (_, i) => prefix + String(start + i).padStart(pad, '0')).join(', ')
  }
  const first = prefix + String(start).padStart(pad, '0')
  const last = prefix + String(start + count - 1).padStart(pad, '0')
  return `${first} … ${last} (${count} Stk.)`
})

function generateSerialNumbers() {
  const prefix = (autoGenPrefix.value || '').trim() || suggestedSerialPrefix.value
  const start = Math.max(1, autoGenStart.value)
  const pad = Math.max(1, Math.min(6, autoGenPad.value || 3))
  const count = Math.max(1, Math.min(100, autoGenCount.value || 1))
  serialNumbers.value = Array.from({ length: count }, (_, i) => ({
    id: ++serialIdCounter,
    serial_number: prefix + String(start + i).padStart(pad, '0'),
    label: '',
    notes: '',
    barcode_tag: '',
    ean: '',
    is_container: serialAllAsContainers.value,
    location_mode: 'slot',
    storage_address_id: getPreferredStorageAddressId(),
    rack_id: '',
    slot_id: '',
    container_batch_id: ''
  }))
}

function addSerialNumber() {
  serialNumbers.value.push({
    id: ++serialIdCounter,
    serial_number: '',
    label: '',
    notes: '',
    barcode_tag: '',
    ean: '',
    is_container: serialAllAsContainers.value,
    location_mode: 'slot',
    storage_address_id: getPreferredStorageAddressId(),
    rack_id: '',
    slot_id: '',
    container_batch_id: ''
  })
}

function ensureInitialSerialRow() {
  if (serialNumbers.value.length === 0) {
    addSerialNumber()
  }
}

function getRacksForSerialEntry(entry: SerialNumberEntry): StorageRack[] {
  if (!entry.storage_address_id) return allStorageRacks.value
  return allStorageRacks.value.filter((rack) => rack.storage_address_id === entry.storage_address_id)
}

function getPreferredStorageAddressId(): string {
  return formData.storage_address_id || storageAddresses.value[0]?.id || ''
}

function onSerialEntryStorageAddressChange(entry: SerialNumberEntry) {
  entry.rack_id = ''
  entry.slot_id = ''
}

async function onSerialEntryRackChange(entry: SerialNumberEntry) {
  entry.slot_id = ''
  if (entry.rack_id) {
    await loadSlotsForRack(String(entry.rack_id))
  }
}

const hasInvalidSerialLocations = computed(() =>
  !serialLocationSameForAll.value &&
  serialNumbers.value
    .filter((entry) => entry.serial_number.trim())
    .some((entry) =>
      entry.location_mode === 'kiste'
        ? !entry.container_batch_id
        : (!entry.rack_id || !entry.slot_id)
    )
)

const duplicateSerialNumbers = computed(() => {
  const counts = new Map<string, number>()
  const rawByNormalized = new Map<string, string>()
  for (const entry of serialNumbers.value) {
    const raw = (entry.serial_number || '').trim()
    if (!raw) continue
    const normalized = raw.toLowerCase()
    counts.set(normalized, (counts.get(normalized) || 0) + 1)
    if (!rawByNormalized.has(normalized)) rawByNormalized.set(normalized, raw)
  }
  return Array.from(counts.entries())
    .filter(([, count]) => count > 1)
    .map(([normalized]) => rawByNormalized.get(normalized) || normalized)
})

const hasDuplicateSerialNumbers = computed(() => duplicateSerialNumbers.value.length > 0)

const serialDuplicateHint = computed(() => {
  if (!hasDuplicateSerialNumbers.value) return ''
  const m = 'components.materialCreateWizard'
  const list = duplicateSerialNumbers.value.slice(0, 3).join(', ')
  const more =
    duplicateSerialNumbers.value.length > 3 ? t(`${m}.serialDuplicateMore`) : ''
  return t(`${m}.serialDuplicateHint`, { list, more })
})

function getSerialRowTitle(entry: SerialNumberEntry, index: number): string {
  const m = 'components.materialCreateWizard'
  const sn = (entry.serial_number || '').trim()
  const n = index + 1
  return sn ? t(`${m}.serialRowTitleWithSn`, { n, sn }) : t(`${m}.serialRowTitleNumberOnly`, { n })
}

function removeSerialNumber(id: number) {
  serialNumbers.value = serialNumbers.value.filter(s => s.id !== id)
  if (serialScannerTargetId.value === id) {
    serialScannerTargetId.value = null
  }
}

function openSerialScannerFor(id: number) {
  serialScannerTargetId.value = id
  serialScannerActive.value = true
}

function stopSerialScanner() {
  serialScannerActive.value = false
}

function toggleSerialScanner() {
  if (serialScannerActive.value) {
    stopSerialScanner()
    return
  }
  const firstEmpty = serialNumbers.value.find(s => !s.serial_number.trim())
  serialScannerTargetId.value = firstEmpty?.id ?? serialNumbers.value[0]?.id ?? null
  if (!serialScannerTargetId.value) {
    addSerialNumber()
    serialScannerTargetId.value = serialNumbers.value[serialNumbers.value.length - 1]?.id ?? null
  }
  serialScannerActive.value = true
}

function onSerialDetected(payload: { text: string }) {
  const value = payload.text.trim()
  if (!value) return

  let target = serialNumbers.value.find(s => s.id === serialScannerTargetId.value)
  if (!target) {
    target = serialNumbers.value.find(s => !s.serial_number.trim())
  }
  if (!target) {
    addSerialNumber()
    target = serialNumbers.value[serialNumbers.value.length - 1]
  }

  target.serial_number = value

  const nextEmpty = serialNumbers.value.find(s => !s.serial_number.trim())
  serialScannerTargetId.value = nextEmpty?.id ?? null
  if (!nextEmpty) {
    addSerialNumber()
    serialScannerTargetId.value = serialNumbers.value[serialNumbers.value.length - 1]?.id ?? null
  }
}

function onSerialScannerError(message: string) {
  toast.error(localizedBarcodeScannerError(message, t))
}

// Automatisch initial_qty bei Seriennummern aktualisieren
const serializedQty = computed(() => serialNumbers.value.filter(s => s.serial_number.trim()).length)
const stockInputReady = computed(() => {
  if (formData.tracking_type === 'serialized') return serializedQty.value > 0
  return formData.initial_qty > 0
})

/** Anschaffung: direkt pro Stück oder Gesamt (Waren + optional Lieferung) auf Stück verteilen */
const purchasePriceInputMode = ref<'unit' | 'total'>('unit')
const purchaseTotalWaresChf = ref('')
const purchaseShippingChf = ref('')
const purchaseReceiptFile = ref<File | null>(null)

function parseChfInput(s: string): number {
  const n = parseFloat(String(s ?? '').replace(/\s/g, '').replace(',', '.'))
  return Number.isFinite(n) ? n : 0
}

const purchasePriceContextQty = computed(() => {
  if (wizardUseMeterQtyByCount.value) {
    return Math.max(0, wizardDisplayQty.value)
  }
  if (isAddBatchMode.value) return Math.max(0, Math.floor(Number(formData.initial_qty) || 0))
  if (formData.material_type !== 'physical') return 0
  if (formData.tracking_type === 'serialized') return serializedQty.value
  if (formData.is_food) return foodLotsTotalQty.value
  return Math.max(0, Math.floor(Number(formData.initial_qty) || 0))
})

const purchasePriceRequired = computed(() => {
  // Esswaren: Anschaffung oft über Aktivität/Camp/Event — Preis darf 0.00 sein, Quittung/Buchhaltung entfällt.
  if (formData.is_food) return false
  if (isAddBatchMode.value) return formData.initial_qty > 0
  if (formData.material_type !== 'physical') return false
  if (!formData.tracking_type) return false
  if (formData.tracking_type === 'serialized') return serializedQty.value > 0
  return formData.initial_qty > 0
})

/** Esswaren: Anschaffungspreis optional (inkl. 0.00 als Referenz). */
const foodPurchasePriceSectionVisible = computed(() => {
  if (!formData.is_food) return false
  if (isAddBatchMode.value) return formData.initial_qty > 0
  return foodLotsTotalQty.value > 0
})

const effectivePurchaseUnitPrice = computed(() => {
  const qty = purchasePriceContextQty.value
  if (qty <= 0) return 0
  const shipping = parseChfInput(purchaseShippingChf.value)
  if (purchasePriceInputMode.value === 'unit') {
    const up = Number(formData.unit_price)
    const base = Number.isFinite(up) && up > 0 ? up : 0
    if (base <= 0 && shipping <= 0) return 0
    return Math.round((base + shipping / qty) * 100) / 100
  }
  const sum = parseChfInput(purchaseTotalWaresChf.value) + shipping
  if (sum <= 0) return 0
  return Math.round((sum / qty) * 100) / 100
})

/** Entwurfs-Chargen für Anschaffungs-Akkordeon (Wizard hat noch keine Chargen-API). */
const wizardDraftBatches = computed((): MaterialBatch[] => {
  const up = effectivePurchaseUnitPrice.value
  if (up <= 0) return []
  const unitPrice = String(up)
  const fallbackDate = formData.purchase_date || new Date().toISOString().slice(0, 10)

  if (formData.is_food && !isAddBatchMode.value) {
    const rows: MaterialBatch[] = []
    const q1 = Math.max(0, Math.floor(Number(formData.initial_qty) || 0))
    if (q1 > 0) {
      rows.push({
        id: 'wizard-draft-1',
        qty: q1,
        unit_price: unitPrice,
        acquired_on: formData.purchase_date || fallbackDate,
        expiry_date: formData.expiry_date || null,
        is_initial: true,
      } as MaterialBatch)
    }
    foodExtraLots.value.forEach((lot, i) => {
      if (lot.qty < 1) return
      rows.push({
        id: `wizard-draft-${i + 2}`,
        qty: Math.floor(lot.qty),
        unit_price: unitPrice,
        acquired_on: lot.purchase_date || fallbackDate,
        expiry_date: lot.expiry_date || null,
        is_initial: false,
      } as MaterialBatch)
    })
    return rows
  }

  const qty = purchasePriceContextQty.value || formData.initial_qty || 0
  if (qty <= 0) return []
  return [
    {
      id: 'wizard-draft',
      qty,
      unit_price: unitPrice,
      acquired_on: fallbackDate,
      is_initial: true,
    } as MaterialBatch,
  ]
})

/** Einkaufspreis Referenz (Kosten) aus Anschaffung vorausfüllen; Esswaren inkl. 0.00 */
watch(
  () => [effectivePurchaseUnitPrice.value, formData.is_consumable, formData.is_food, formData.unit_price],
  () => {
    if (!(formData.is_consumable || formData.is_food)) return
    const up = effectivePurchaseUnitPrice.value
    const unitEntered =
      formData.unit_price != null && Number.isFinite(Number(formData.unit_price))
        ? Number(formData.unit_price)
        : null

    // Esswaren: Referenz aus Anschaffung (auch 0.00), Verkaufspreis = EK (kein Aufschlag / keine Doppelzahlung)
    if (formData.is_food) {
      const source =
        up > 0
          ? up
          : unitEntered != null && unitEntered >= 0
            ? unitEntered
            : null
      if (source == null) return
      const perM = resolveWizardReferencePurchaseFromUnitPrice(source)
      const rounded = Math.round(perM * 100) / 100
      formData.reference_purchase_unit_chf = rounded
      formData.sale_price = rounded
      return
    }

    if (up <= 0) return
    const ref = formData.reference_purchase_unit_chf
    if (ref == null || Number(ref) <= 0) {
      const perM = resolveWizardReferencePurchaseFromUnitPrice(up)
      formData.reference_purchase_unit_chf = Math.round(perM * 100) / 100
    }
  }
)

/** Esswaren: Verkaufspreis immer = Referenz-EK (manuelle Referenz-Änderung mitziehen). */
watch(
  () => [formData.is_food, formData.reference_purchase_unit_chf, foodLotsTotalQty.value],
  () => {
    if (!formData.is_food) return
    const rp = formData.reference_purchase_unit_chf
    if (rp == null || !Number.isFinite(Number(rp))) {
      // Ohne Anschaffung: 0.00 als gültige Referenz von Anfang an
      if (foodLotsTotalQty.value > 0 && formData.reference_purchase_unit_chf == null) {
        formData.reference_purchase_unit_chf = 0
        formData.sale_price = 0
      }
      return
    }
    formData.sale_price = Math.round(Number(rp) * 100) / 100
  }
)

watch(
  [purchasePriceInputMode, purchaseTotalWaresChf, purchaseShippingChf, purchasePriceContextQty],
  () => {
    if (purchasePriceInputMode.value !== 'total') return
    const qty = purchasePriceContextQty.value
    if (qty <= 0) return
    const sum = parseChfInput(purchaseTotalWaresChf.value) + parseChfInput(purchaseShippingChf.value)
    if (sum > 0) {
      formData.unit_price = Math.round((sum / qty) * 100) / 100
    }
  }
)

watch(purchasePriceInputMode, (m, prev) => {
  if (m === 'total' && prev === 'unit') {
    const qty = purchasePriceContextQty.value
    const up = Number(formData.unit_price)
    if (qty > 0 && up > 0 && !purchaseTotalWaresChf.value.trim()) {
      purchaseTotalWaresChf.value = (up * qty).toFixed(2)
    }
  }
  if (m === 'unit' && prev === 'total') {
    purchaseTotalWaresChf.value = ''
    purchaseShippingChf.value = ''
  }
})

watch(
  () =>
    [
      packSaleToUnitSaleChf.value,
      purchasePriceInputMode.value,
      formData.pack_sale_price_chf,
      formData.pack_size,
      formData.is_consumable,
      formData.is_food,
    ] as const,
  () => syncPurchasePricesFromPackSale(),
)

function onPurchasePriceModeToggle(ev: Event) {
  const el = ev.target as HTMLInputElement | null
  purchasePriceInputMode.value = el?.checked ? 'total' : 'unit'
}

watch(
  () => formData.tracking_type,
  (type) => {
    if (type === 'serialized') {
      ensureInitialSerialRow()
    }
  }
)

watch(serialLocationSameForAll, (sameForAll) => {
  if (sameForAll) return
  serialNumbers.value.forEach((entry) => {
    if (!entry.storage_address_id) {
      entry.storage_address_id = formData.storage_address_id || ''
    }
  })
})

const materialTypeLabels = computed(() => ({
  physical: t('components.materialCreateWizard.typePhysicalArticle'),
  physical_combo: t('components.materialCreateWizard.typePhysicalCombo'),
  virtual_combo: t('components.materialCreateWizard.typeVirtualCombo'),
}))

const trackingTypeLabels = computed(() => ({
  serialized: t('components.materialCreateWizard.trackingSerializedShort'),
  bulk: t('components.materialCreateWizard.trackingBulkShort'),
}))

const storageAddressName = computed(() => {
  if (!formData.storage_address_id) return ''
  const addr = storageAddresses.value.find(a => a.id === formData.storage_address_id)
  return addr ? (addr.name || addr.street_line) : ''
})

const storageAddressWithLocation = computed(() => {
  const addrName = storageAddressName.value

  // Physische Kombi in Kiste/Tasche: Lagerzeile folgt der gewählten Kiste (ändert sich mit Dropdown)
  if (
    formData.material_type === 'physical_combo' &&
    formData.stock_location_mode === 'kiste' &&
    formData.stock_container_batch_id
  ) {
    const cb = containerBatches.value.find(
      (b) => String(b.id) === String(formData.stock_container_batch_id)
    )
    const kisteLine = cb ? formatContainerBatchSelectLabel(cb) : t('components.materialCreateWizard.fallbackBoxLine')
    if (addrName) return `${addrName} • ${kisteLine}`
    return kisteLine
  }

  if (!addrName) return null
  if (!formData.rack_id) return addrName
  const rack = storageRacks.value.find((r) => String(r.id) === String(formData.rack_id))
  const rackName = rack?.name || ''
  if (!rackName) return addrName
  if (formData.slot_id) {
    const slots = slotsByRackId.value[String(formData.rack_id)] || []
    const slot = slots.find((s) => String(s.id) === String(formData.slot_id))
    if (slot?.name) return `${addrName} • ${rackName}, ${slot.name}`
  }
  return `${addrName} • ${rackName}`
})

const requiresExpiryDate = computed(() => {
  if (!formData.is_food) return false
  if (formData.tracking_type === 'serialized') return false
  if (isAddBatchMode.value) return formData.initial_qty > 0
  return formData.initial_qty > 0
})

const requiresPurchaseDate = computed(() => {
  if (formData.is_food) return false
  if (isAddBatchMode.value) return formData.initial_qty > 0
  if (formData.tracking_type === 'serialized') return serializedQty.value > 0
  return formData.initial_qty > 0
})

/** Nach Komponenten: „Kauf & Lagerung“ – nicht für virtuelle Kombis (nur Planung). */
const showTemplatePurchaseStep = computed(() => {
  if (!creationMode.value) return false
  const nameReady =
    creationMode.value === 'individual' || (!!formData.name.trim() && !nameExists.value)
  if (!nameReady) return false
  if (isFromTemplate.value && selectedTemplate.value) {
    return creationMode.value === 'individual' || creationMode.value === 'physical_combo'
  }
  if (
    isFromContainerBatchContents.value &&
    selectedContainerBatchContents.value &&
    creationMode.value === 'physical_combo'
  ) {
    return true
  }
  if (
    !isFromTemplate.value &&
    !isFromContainerBatchContents.value &&
    creationMode.value === 'physical_combo' &&
    selectedComboMaterials.value.filter(isComboArticleDone).length >= 2 &&
    !!formData.category_id
  ) {
    return true
  }
  return false
})

const visibleStepIds = computed<StepId[]>(() => {
  const steps: StepId[] = []

  if (!isAddBatchMode.value && !creationMode.value) steps.push('creation_mode')
  if (!isAddBatchMode.value && !!creationMode.value) steps.push('general')

  if (
    !isAddBatchMode.value &&
    (creationMode.value === 'individual' ||
      creationMode.value === 'physical_combo' ||
      creationMode.value === 'virtual_combo') &&
    (!isFromTemplate.value || selectedTemplate.value)
  ) {
    steps.push('category')
  }

  if ((isFromTemplate.value && selectedTemplate.value) || (isFromContainerBatchContents.value && selectedContainerBatchContents.value)) {
    if (creationMode.value && (creationMode.value === 'individual' || (formData.name && !nameExists.value))) {
      steps.push('template_components')
    }
  }

  if (isFromTemplate.value && selectedTemplate.value && creationMode.value && creationMode.value !== 'individual' && formData.name && !nameExists.value) {
    steps.push('template_tent')
  }

  const comboNameReady =
    !!formData.name.trim() && !nameExists.value

  if (
    !isAddBatchMode.value &&
    !isFromTemplate.value &&
    !isFromContainerBatchContents.value &&
    (formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo') &&
    comboNameReady &&
    !!formData.category_id
  ) {
    steps.push('combo_articles')
  }

  if (showTemplatePurchaseStep.value) {
    steps.push('template_purchase')
  }

  if (
    !isAddBatchMode.value &&
    !isFromTemplate.value &&
    creationMode.value === 'individual' &&
    formData.material_type === 'physical' &&
    !formData.is_food &&
    !!formData.category_id
  ) {
    steps.push('tracking')
  }

  // Bestandseinheit + Lagerplatz + Details + Vermietung (peer-Akkordeons)
  if (isAddBatchMode.value || (!isFromTemplate.value && creationMode.value === 'individual' && formData.material_type === 'physical' && formData.tracking_type)) {
    steps.push('stock')
    steps.push('storage')
  }

  if (
    !isAddBatchMode.value &&
    !isFromTemplate.value &&
    creationMode.value &&
    ((formData.material_type === 'physical' && formData.tracking_type) ||
      ((formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo') &&
        comboNameReady &&
        !!formData.category_id))
  ) {
    steps.push('details')
    if (!formData.is_consumable && !formData.is_food) {
      steps.push('rental')
    }
  }

  return steps
})

function nextVisibleStepAfter(from: StepId): StepId | null {
  const steps = visibleStepIds.value
  const i = steps.indexOf(from)
  if (i === -1 || i >= steps.length - 1) return null
  return steps[i + 1]
}

/** Nach Wahl der Bestandsverfolgung: Bestandseinheit + Lagerplatz + Details (+ Vermietung) gleichzeitig sichtbar. */
const isDetailsAndStockPairVisible = computed(() => {
  const steps = visibleStepIds.value
  if (steps.includes('stock') && steps.includes('storage')) return true
  if (!formData.tracking_type) return false
  return steps.includes('details') && steps.includes('stock')
})

function isDetailsStockRentalGroupStep(step: StepId): boolean {
  return step === 'details' || step === 'stock' || step === 'storage' || step === 'rental'
}

function isStepOpen(step: StepId): boolean {
  if (expandAllVisibleSteps.value) {
    return visibleStepIds.value.includes(step)
  }
  if (isDetailsAndStockPairVisible.value && isDetailsStockRentalGroupStep(step)) {
    return true
  }
  return activeStep.value === step
}

function toggleStep(step: StepId): void {
  if (expandAllVisibleSteps.value) {
    expandAllVisibleSteps.value = false
    accordionUserControlled.value = true
    activeStep.value = step
    return
  }
  if (isDetailsAndStockPairVisible.value && isDetailsStockRentalGroupStep(step)) {
    activeStep.value = step
    return
  }
  activeStep.value = activeStep.value === step ? '' : step
}

function onWizardFormFocusIn(e: FocusEvent) {
  if (expandAllVisibleSteps.value) return
  // Tour hält den passenden Wizard-Schritt offen (kein Fokus-Sprung auf Kategorie etc.)
  if (wizardAccordionStepForMaterialTour()) return
  const el = e.target as HTMLElement | null
  if (!el?.closest) return
  const section = el.closest('.step-section[data-step]')
  if (!section) return
  const step = section.getAttribute('data-step') as StepId | null
  if (step && visibleStepIds.value.includes(step)) {
    activeStep.value = step
  }
}

function onCategorySearchKeydown(e: KeyboardEvent) {
  if (e.key !== 'Tab' || e.shiftKey) return
  if (expandAllVisibleSteps.value || !accordionUserControlled.value) return
  const next = nextVisibleStepAfter('category')
  if (!next) return
  e.preventDefault()
  activeStep.value = next
  void nextTick(() => {
    const container = document.querySelector(`.material-wizard-form .step-section[data-step="${next}"]`)
    const focusable = container?.querySelector<HTMLElement>(
      'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'
    )
    focusable?.focus()
  })
}

async function jumpToMissingStep(step: StepId | string): Promise<void> {
  activeStep.value = step as StepId
  await nextTick()
  const el = document.querySelector(
    `.material-wizard-form .step-section[data-step="${step}"], .material-wizard-form [data-step="${step}"]`
  )
  if (el && 'scrollIntoView' in el) {
    ;(el as HTMLElement).scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

const canSubmit = computed(() => {
  // Im Batch-Modus: Menge > 0 und Kaufdatum erforderlich
  if (isAddBatchMode.value) {
    if (formData.initial_qty <= 0) return false
    if (requiresPurchaseDate.value && !formData.purchase_date) return false
    if (requiresExpiryDate.value && !formData.expiry_date) return false
    if (formData.split_allocations && (!allocationSumValid.value || !hasRelevantAllocationRows.value || hasInvalidAllocationRows.value)) return false
    if (purchasePriceRequired.value && effectivePurchaseUnitPrice.value <= 0) return false
    return true
  }
  
  // Erstellungsmodus muss gewählt sein
  if (!creationMode.value) return false

  // ── Combo aus Kisten-Inhalt ──
  if (isFromContainerBatchContents.value && selectedContainerBatchContents.value && containerContentsBatchId.value) {
    if (!formData.name.trim()) return false
    if (nameExists.value) return false
    if (!formData.category_id) return false
    if (formData.material_type === 'physical_combo') {
      if (formData.stock_location_mode === 'kiste' && !formData.stock_container_batch_id) return false
      if (formData.stock_location_mode === 'slot' && (!formData.rack_id || !formData.slot_id)) return false
    }
    return true
  }

  // ── Virtuelle Kombo (ohne Vorlage): Name + Kategorie + Stückliste ──
  if (creationMode.value === 'virtual_combo') {
    if (!formData.name.trim()) return false
    if (nameExists.value) return false
    if (!formData.category_id) return false
    const doneArticles = selectedComboMaterials.value.filter(isComboArticleDone)
    if (doneArticles.length < 2) return false
    if (!doneArticles.some((m) => m.component_source === 'stock')) return false
    return true
  }

  // ── Mit Vorlage (Einzelartikel oder Physische Kombo) ──
  if (isFromTemplate.value && selectedTemplate.value) {
    if (!formData.category_id) return false

    // Physische Kombo: Name ist Pflicht
    if (creationMode.value === 'physical_combo') {
      if (!formData.name.trim()) return false
      if (nameExists.value) return false
    }

    // Pflichtkomponenten müssen ausgefüllt sein
    for (const ci of componentInputs.value) {
      if (ci.is_optional || ci.component_source === 'self_provided') continue
      if (ci.mode === 'new') {
        if (ci.tracking === 'serialized' && !ci.serial_number.trim()) return false
        if (ci.tracking === 'bulk' && ci.qty < 1) return false
      } else {
        if (ci.tracking === 'serialized') {
          if (!ci.material_id || !ci.batch_id) return false
        } else {
          if (!ci.material_id || ci.qty < 1) return false
        }
      }
    }
    if (creationMode.value === 'physical_combo') {
      if (formData.stock_location_mode === 'kiste' && !formData.stock_container_batch_id) return false
      if (formData.stock_location_mode === 'slot' && (!formData.rack_id || !formData.slot_id)) return false
    }
    return true
  }

  // ── Einzelartikel ohne Vorlage (manuell) ──
  if (!formData.name.trim()) return false
  if (nameExists.value) return false
  if (
    !isFromTemplate.value &&
    (creationMode.value === 'individual' ||
      creationMode.value === 'physical_combo' ||
      creationMode.value === 'virtual_combo') &&
    !formData.category_id
  ) {
    return false
  }
  if (!formData.material_type) return false
  if (stock_unit.value === 'm' && !parseSizeLengthCm(formData.size_length)) return false
  
  if (formData.material_type === 'physical') {
    if (!formData.tracking_type) return false
    if (requiresPurchaseDate.value && !formData.purchase_date) return false
    if (requiresExpiryDate.value && !formData.expiry_date) return false
    if (formData.tracking_type === 'serialized') {
      if (serializedQty.value < 1) return false
      if (hasDuplicateSerialNumbers.value) return false
    } else {
      if (formData.initial_qty < 1) return false
      if (formData.split_allocations && (!allocationSumValid.value || !hasRelevantAllocationRows.value || hasInvalidAllocationRows.value)) return false
    }
    if (!foodExtraLotsValid.value) return false
    if (formData.tracking_type === 'serialized') {
      if (!serialLocationSameForAll.value && hasInvalidSerialLocations.value) return false
      if (serialLocationSameForAll.value && formData.stock_location_mode === 'kiste' && serializedQty.value > 0 && !formData.stock_container_batch_id) return false
      if (serialLocationSameForAll.value && formData.stock_location_mode === 'slot' && serializedQty.value > 0 && (!formData.rack_id || !formData.slot_id)) return false
    } else {
      if (!formData.split_allocations && formData.stock_location_mode === 'kiste' && formData.initial_qty > 0 && !formData.stock_container_batch_id) return false
      if (
        !formData.split_allocations &&
        formData.stock_location_mode === 'slot' &&
        formData.initial_qty > 0 &&
        (!formData.rack_id || !formData.slot_id)
      ) {
        return false
      }
      // 'loose': Gestell/Fach bewusst leer — erlaubt (Bestätigung beim Speichern)
    }
    if (purchasePriceRequired.value && effectivePurchaseUnitPrice.value <= 0) return false
    if (formData.is_consumable || formData.is_food) {
      const sp = formData.sale_price
      const rp = formData.reference_purchase_unit_chf
      // Esswaren: 0.00 ist gültig (bereits über Aktivität/Camp/Event bezahlt)
      if (formData.is_food) {
        if (sp == null || !Number.isFinite(Number(sp)) || Number(sp) < 0) return false
        if (rp == null || !Number.isFinite(Number(rp)) || Number(rp) < 0) return false
      } else if (sp == null || Number(sp) <= 0 || rp == null || Number(rp) <= 0) {
        return false
      }
    }
  }

  if (formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo') {
    const completeCount = selectedComboMaterials.value.filter(isComboArticleDone).length
    if (completeCount < 2) return false
    if (formData.material_type === 'physical_combo' && !isFromTemplate.value && !isFromContainerBatchContents.value) {
      for (const mat of selectedComboMaterials.value) {
        if (isComboArticlePartiallyFilled(mat) && !isComboArticleDone(mat)) return false
      }
      if (formData.stock_location_mode === 'kiste' && !formData.stock_container_batch_id) return false
      if (formData.stock_location_mode === 'slot' && (!formData.rack_id || !formData.slot_id)) return false
    }
  }
  
  return true
})

/** Fehlende Pflichtfelder: stabiler Schritt + übersetztes Label (Footer / „Springen zu“). */
const missingSteps = computed((): Array<{ step: StepId; label: string }> => {
  const missing: Array<{ step: StepId; label: string }> = []
  const m = 'components.materialCreateWizard'

  const push = (step: StepId, labelKey: string, params?: Record<string, unknown>) => {
    missing.push({ step, label: params ? t(labelKey, params) : t(labelKey) })
  }

  if (isAddBatchMode.value) {
    if (formData.initial_qty <= 0) {
      push('stock', `${m}.missingEnterQty`)
    }
    if (requiresPurchaseDate.value && !formData.purchase_date) {
      push('stock', `${m}.missingEnterPurchaseDate`)
    }
    if (requiresExpiryDate.value && !formData.expiry_date) {
      push('stock', `${m}.missingEnterExpiryDate`)
    }
    if (formData.split_allocations) {
      if (!allocationSumValid.value || !hasRelevantAllocationRows.value || hasInvalidAllocationRows.value) {
        push('storage', `${m}.missingAllocationsSum`, {
          qty: formData.initial_qty,
          unit: wizardStockUnitLabel.value,
        })
      }
    }
    if (purchasePriceRequired.value && effectivePurchaseUnitPrice.value <= 0) {
      push('stock', `${m}.missingEnterPurchasePrice`)
    }
    return missing
  }

  if (!creationMode.value) {
    push('creation_mode', `${m}.missingSelectCreationMode`)
    return missing
  }

  if (isFromContainerBatchContents.value && selectedContainerBatchContents.value && containerContentsBatchId.value) {
    if (!formData.name.trim()) {
      push('general', `${m}.missingEnterComboName`)
    } else if (nameExists.value) {
      push('general', `${m}.missingNameExists`)
    }
    if (!formData.category_id) {
      push('category', `${m}.missingSelectCategory`)
    }
    if (formData.material_type === 'physical_combo') {
      if (formData.stock_location_mode === 'kiste' && !formData.stock_container_batch_id) {
        push('template_purchase', `${m}.missingPickBoxForCombo`)
      }
      if (formData.stock_location_mode === 'slot' && (!formData.rack_id || !formData.slot_id)) {
        push('template_purchase', `${m}.missingPickRackSlotForCombo`)
      }
    }
    return missing
  }

  if (creationMode.value === 'virtual_combo') {
    if (!formData.name.trim()) push('general', `${m}.missingEnterComboName`)
    else if (nameExists.value) push('general', `${m}.missingNameExists`)
    else if (!formData.category_id) push('category', `${m}.missingSelectCategory`)
    else if (selectedComboMaterials.value.filter(isComboArticleDone).length < 2) {
      push('combo_articles', `${m}.missingAddTwoArticles`)
    } else if (!virtualComboHasStockArticle.value) {
      push('combo_articles', `${m}.comboMinOneStockRequired`)
    }
    return missing
  }

  if (isFromTemplate.value && selectedTemplate.value) {
    if (!formData.category_id) {
      push('category', `${m}.missingSelectCategory`)
    }
    if (creationMode.value === 'physical_combo' && !formData.name.trim()) {
      push('general', `${m}.missingEnterComboName`)
    }
    for (const ci of componentInputs.value) {
      if (ci.is_optional || ci.component_source === 'self_provided') continue
      if (ci.mode === 'new') {
        if (ci.tracking === 'serialized' && !ci.serial_number.trim()) {
          push('template_components', `${m}.missingEnterSnForComp`, { name: ci.name })
          break
        }
        if (ci.tracking === 'bulk' && ci.qty < 1) {
          push('template_components', `${m}.missingEnterQtyForComp`, { name: ci.name })
          break
        }
      } else {
        if (ci.tracking === 'serialized') {
          if (!ci.material_id) {
            push('template_components', `${m}.missingPickArticleForComp`, { name: ci.name })
            break
          }
          if (!ci.batch_id) {
            push('template_components', `${m}.missingPickSnForComp`, { name: ci.name })
            break
          }
        } else {
          if (!ci.material_id) {
            push('template_components', `${m}.missingPickArticleForComp`, { name: ci.name })
            break
          }
          if (ci.qty < 1) {
            push('template_components', `${m}.missingEnterQtyForComp`, { name: ci.name })
            break
          }
        }
      }
    }
    if (creationMode.value === 'physical_combo') {
      if (formData.stock_location_mode === 'kiste' && !formData.stock_container_batch_id) {
        push('template_purchase', `${m}.missingPickBoxForCombo`)
      }
      if (formData.stock_location_mode === 'slot' && (!formData.rack_id || !formData.slot_id)) {
        push('template_purchase', `${m}.missingPickRackSlotForCombo`)
      }
    }
    return missing
  }

  if (!formData.name.trim()) {
    push('general', `${m}.missingEnterArticleName`)
  } else if (nameExists.value) {
    push('general', `${m}.missingNameExists`)
  }

  if (
    !isFromTemplate.value &&
    (creationMode.value === 'individual' ||
      creationMode.value === 'physical_combo' ||
      creationMode.value === 'virtual_combo') &&
    !formData.category_id
  ) {
    push('category', `${m}.missingSelectCategory`)
  }

  if (formData.material_type === 'physical') {
    if (!formData.tracking_type) {
      push('tracking', `${m}.missingSelectTracking`)
    } else {
      if (requiresPurchaseDate.value && !formData.purchase_date) {
        push('stock', `${m}.missingEnterPurchaseDate`)
      }
      if (requiresExpiryDate.value && !formData.expiry_date) {
        push('stock', `${m}.missingEnterExpiryDate`)
      }
      if (formData.tracking_type === 'serialized') {
        if (serializedQty.value < 1) {
          push('stock', `${m}.missingMinOneSerial`)
        }
        if (hasDuplicateSerialNumbers.value) {
          push('stock', `${m}.missingRemoveDupSerials`)
        }
      } else if (formData.initial_qty < 1) {
        push('stock', `${m}.missingMinOnePiece`)
      }
      if (!foodExtraLotsValid.value) {
        push('stock', `${m}.missingFoodExtraLot`)
      }
      if (formData.tracking_type === 'serialized') {
        if (!serialLocationSameForAll.value) {
          if (hasInvalidSerialLocations.value) {
            push('stock', `${m}.missingPickLocationPerSerial`)
          }
        } else if (serializedQty.value > 0) {
          if (formData.stock_location_mode === 'kiste' && !formData.stock_container_batch_id) {
            push('storage', `${m}.missingPickBox`)
          }
          if (formData.stock_location_mode === 'slot' && (!formData.rack_id || !formData.slot_id)) {
            push('storage', `${m}.missingPickRackSlot`)
          }
        }
      } else {
        const hasStockInput = formData.initial_qty > 0
        if (hasStockInput && !formData.split_allocations) {
          if (formData.stock_location_mode === 'kiste' && !formData.stock_container_batch_id) {
            push('storage', `${m}.missingPickBox`)
          }
          if (formData.stock_location_mode === 'slot' && (!formData.rack_id || !formData.slot_id)) {
            push('storage', `${m}.missingPickRackSlot`)
          }
        }
      }
    }
    if (purchasePriceRequired.value && effectivePurchaseUnitPrice.value <= 0) {
      push('stock', `${m}.missingEnterPurchasePrice`)
    }
    if (formData.is_consumable || formData.is_food) {
      const sp = formData.sale_price
      const rp = formData.reference_purchase_unit_chf
      if (formData.is_food) {
        if (sp == null || !Number.isFinite(Number(sp)) || Number(sp) < 0) {
          push('details', `${m}.missingEnterSalePrice`)
        }
        if (rp == null || !Number.isFinite(Number(rp)) || Number(rp) < 0) {
          push('details', `${m}.missingEnterRefPurchasePrice`)
        }
      } else {
        if (sp == null || Number(sp) <= 0) {
          push('details', `${m}.missingEnterSalePrice`)
        }
        if (rp == null || Number(rp) <= 0) {
          push('details', `${m}.missingEnterRefPurchasePrice`)
        }
      }
    }
  }

  if (
    (formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo') &&
    selectedComboMaterials.value.filter(isComboArticleDone).length < 2
  ) {
    push('combo_articles', `${m}.missingAddTwoArticles`)
  } else if (
    formData.material_type === 'physical_combo' &&
    !isFromTemplate.value &&
    !isFromContainerBatchContents.value
  ) {
    for (const mat of selectedComboMaterials.value) {
      if (!isComboArticlePartiallyFilled(mat) || isComboArticleDone(mat)) continue
      const label = mat.name.trim() || t('components.materialCreateWizard.comboArticleUnnamed')
      if (mat.mode === 'new') {
        if (!mat.id && !mat.name.trim()) {
          push('combo_articles', `${m}.missingEnterArticleName`)
          break
        }
        if (mat.tracking === 'serialized' && !mat.serial_number.trim()) {
          push('combo_articles', `${m}.missingEnterSnForComp`, { name: label })
          break
        }
        if (mat.tracking === 'bulk' && mat.qty < 1) {
          push('combo_articles', `${m}.missingEnterQtyForComp`, { name: label })
          break
        }
      } else if (!mat.id) {
        push('combo_articles', `${m}.missingPickArticleForComp`, { name: label })
        break
      } else if (mat.tracking === 'serialized' && !mat.batch_id) {
        push('combo_articles', `${m}.missingPickSnForComp`, { name: label })
        break
      } else if (mat.tracking === 'bulk' && mat.qty < 1) {
        push('combo_articles', `${m}.missingEnterQtyForComp`, { name: label })
        break
      }
    }
    if (
      !hasComboLinkedContainerFromComponent.value &&
      formData.stock_location_mode === 'kiste' &&
      !formData.stock_container_batch_id
    ) {
      push('template_purchase', `${m}.missingPickBoxForCombo`)
    }
    if (
      (hasComboLinkedContainerFromComponent.value || formData.stock_location_mode === 'slot') &&
      (!formData.rack_id || !formData.slot_id)
    ) {
      push('template_purchase', `${m}.missingPickRackSlotForCombo`)
    }
  }
  return missing
})
const shouldRenderCreationMode = computed(() => {
  if (shouldShowCreationMode.value) return true
  return missingSteps.value[0]?.step === 'creation_mode'
})

function scrollWizardFormToTop(): void {
  if (wizardFormRef.value) {
    wizardFormRef.value.scrollTop = 0
  }
}

/** Formular leeren; optional gespeicherte Serien-/Lager-Präferenzen wiederherstellen. */
function resetForm(options: { restoreStockPrefs?: boolean } = {}) {
  const { restoreStockPrefs = true } = options
  formData.name = ''
  formData.storage_address_id = ''
  formData.location_rack = ''
  formData.location_slot = ''
  formData.rack_id = ''
  formData.slot_id = ''
  formData.split_allocations = false
  initialAllocations.value = []
  slotsByRackId.value = {}
  showRackDropdown.value = false
  filteredRackOptions.value = []
  formData.category_id = ''
  formData.material_type = ''
  formData.tracking_type = ''
  formData.is_consumable = false
  formData.is_food = false
  formData.is_js_material = false
  formData.external_source = ''
  formData.sale_price = null
  formData.external_sale_price_chf = null
  formData.reference_purchase_unit_chf = null
  formData.min_stock = null
  formData.pack_size = null
  formData.pack_unit = ''
  formData.pack_sale_price_chf = null
  formData.pack_weight = ''
  formData.pack_size_length = ''
  formData.pack_size_width = ''
  formData.pack_size_height = ''
  formData.initial_qty = 0
  stock_unit.value = 'Stk'
  qtyEntryMode.value = 'base'
  formData.purchase_date = getTodayIso()
  formData.expiry_date = ''
  clearFoodExtraLots()
  showExpiryDateForNonFood.value = false
  formData.manufacturer = ''
  formData.supplier_id = ''
  formData.unit_price = 0
  lastPackDerivedPurchaseUnitChf = null
  purchasePriceInputMode.value = 'unit'
  purchaseTotalWaresChf.value = ''
  purchaseShippingChf.value = ''
  purchaseReceiptFile.value = null
  formData.invoice_number = ''
  formData.notes = ''
  formData.barcode_tag = ''
  formData.description = ''
  formData.model = ''
  formData.ean = ''
  formData.weight = ''
  formData.color = ''
  formData.size_length = ''
  formData.size_width = ''
  formData.size_height = ''
  formData.warranty_until = ''
  formData.rental_price_day = ''
  formData.rental_price_week = ''
  formData.rental_price_month = ''
  formData.rental_deposit = ''
  formData.rental_lead_days = null
  formData.rental_max_days = null
  formData.rental_external_allowed = false
  formData.is_rentable = true
  formData.rental_scope = ''
  formData.rental_requires_approval = false
  formData.rental_notes = ''
  formData.rental_calc_params = null
  formData.stock_location_mode = 'slot'
  formData.stock_container_batch_id = ''
  formData.is_container = false
  nameExists.value = false
  duplicateNameMaterial.value = null
  nameSuggestions.value = []
  showNameSuggestions.value = false
  isAddBatchMode.value = false
  selectedExistingMaterial.value = null
  manufacturerSearch.value = ''
  selectedManufacturer.value = null
  supplierSearch.value = ''
  selectedSupplier.value = null
  comboMaterialSearch.value = ''
  selectedComboMaterials.value = []
  categorySearch.value = ''
  selectedCategory.value = null
  // Seriennummern zurücksetzen
  serialNumbers.value = []
  serialIdCounter = 0
  serialScannerActive.value = false
  serialScannerTargetId.value = null
  serialLocationSameForAll.value = true
  serialAllAsContainers.value = false
  serialAutoGenerateEnabled.value = false
  autoGenPrefix.value = ''
  autoGenStart.value = 1
  autoGenPad.value = 3
  autoGenCount.value = 5
  // Template-Modus zurücksetzen
  isFromTemplate.value = false
  selectedTemplate.value = null
  isFromContainerBatchContents.value = false
  containerContentsBatchId.value = ''
  selectedContainerBatchContents.value = null
  creationMode.value = ''
  templateSearch.value = ''
  templateComponents.value = []
  componentInputs.value = []
  tentForm.tent_type = ''
  tentForm.tent_capacity = null

  templateLoadInProgress.value = false
  expandAllVisibleSteps.value = true
  accordionUserControlled.value = false
  activeStep.value = ''

  scrollWizardFormToTop()

  if (restoreStockPrefs) {
    applyWizardStockPrefs()
  }
}

function resetFormForNewMaterial(): void {
  resetForm({ restoreStockPrefs: false })
}

function handleClose() {
  showDialog.value = false
  resetFormForNewMaterial()
}

function selectTrackingType(type: 'serialized' | 'bulk') {
  if (type === 'serialized' && formData.is_food) return
  formData.tracking_type = type
  void goToStepAfterTrackingSelected()
}

/** Nach Wahl „Wie wird der Lagerbestand verfolgt?“: zu „Bestandseinheit“ (peer mit Lagerplatz / Details / Vermietung). */
async function goToStepAfterTrackingSelected(): Promise<void> {
  // Tour hält den Tracking-Schritt offen — sonst scrollt der Abschnitt weg und das Spotlight landet am Header.
  if (wizardAccordionStepForMaterialTour()) return
  if (isAddBatchMode.value || isFromTemplate.value || creationMode.value !== 'individual') return
  await nextTick()
  const steps = visibleStepIds.value
  const target: StepId | null = steps.includes('stock') ? 'stock' : nextVisibleStepAfter('tracking')
  if (!target) return
  expandAllVisibleSteps.value = false
  accordionUserControlled.value = true
  activeStep.value = target
  await nextTick()
  const el = document.querySelector(
    `.material-wizard-form .step-section[data-step="${target}"], .material-wizard-form [data-step="${target}"]`
  )
  if (el && 'scrollIntoView' in el) {
    ;(el as HTMLElement).scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

/** Duplikat-Prüfung (Blur + Debounce); setzt Treffer für Link / Bestand hinzufügen. */
async function performNameDuplicateCheck(): Promise<void> {
  if (!formData.name.trim()) {
    nameExists.value = false
    duplicateNameMaterial.value = null
    nameSuggestions.value = []
    isCheckingName.value = false
    return
  }
  isCheckingName.value = true
  try {
    const query = formData.name.trim().toLowerCase()
    const materials = await materialNameLookupFetcher(formData.name.trim())
    const exact = materials.find((m) => m.name.toLowerCase() === query)
    nameExists.value = !!exact
    duplicateNameMaterial.value = exact ?? null
    nameSuggestions.value = materials
      .filter((m) => m.name.toLowerCase().includes(query))
      .slice(0, NAME_SUGGEST_LIMIT)
  } catch {
    nameExists.value = false
    duplicateNameMaterial.value = null
    nameSuggestions.value = []
  } finally {
    isCheckingName.value = false
  }
}

/** Während der Eingabe: passende Artikel für Dropdown (ohne Duplikat-Spinner). */
async function refreshNameSuggestionsFromQuery(): Promise<void> {
  const q = formData.name.trim()
  if (q.length < NAME_SUGGEST_MIN_CHARS) {
    nameSuggestions.value = []
    showNameSuggestions.value = false
    return
  }
  try {
    const materials = await materialNameLookupFetcher(q)
    const ql = q.toLowerCase()
    nameSuggestions.value = materials
      .filter((m) => m.name.toLowerCase().includes(ql))
      .slice(0, NAME_SUGGEST_LIMIT)
    showNameSuggestions.value = nameSuggestions.value.length > 0
  } catch {
    nameSuggestions.value = []
    showNameSuggestions.value = false
  }
}

function checkNameDebounced() {
  if (nameCheckTimeout) clearTimeout(nameCheckTimeout)
  const q = formData.name.trim()
  if (!q) {
    nameExists.value = false
    duplicateNameMaterial.value = null
    nameSuggestions.value = []
    showNameSuggestions.value = false
    return
  }
  if (q.length < NAME_SUGGEST_MIN_CHARS) {
    nameSuggestions.value = []
    showNameSuggestions.value = false
    return
  }
  nameCheckTimeout = setTimeout(() => {
    void refreshNameSuggestionsFromQuery()
  }, 350)
}

function hideNameSuggestionsDelayed() {
  setTimeout(() => { showNameSuggestions.value = false }, 200)
}

function handleNameInputFocus() {
  isNameInputFocused.value = true
  showNameSuggestions.value = nameSuggestions.value.length > 0
}

async function handleNameInputBlur() {
  isNameInputFocused.value = false
  showNameSuggestions.value = false
  hideNameSuggestionsDelayed()
  if (nameCheckTimeout) {
    clearTimeout(nameCheckTimeout)
    nameCheckTimeout = null
  }
  void performNameDuplicateCheck()
  // Kein Auto-Sprung zur Kategorie: sonst schließt «Allgemeines» und Verbrauch/Esswaren
  // sind nicht mehr wählbar. Kategorie bleibt als geschlossenes Akkordeon sichtbar.
}

function selectNameSuggestion(material: any) {
  // Wechsle in "Batch hinzufügen" Modus
  isAddBatchMode.value = true
  selectedExistingMaterial.value = material
  formData.name = material.name
  showNameSuggestions.value = false
  nameExists.value = false // Im Batch-Modus ist das OK
  duplicateNameMaterial.value = null
  
  // Setze die Material-Eigenschaften aus dem existierenden Material
  formData.storage_address_id = material.storage_address?.id != null ? String(material.storage_address.id) : ''
  const rawLocation = String(material.location || '').trim()
  if (!rawLocation) {
    formData.location_rack = ''
    formData.location_slot = ''
    formData.rack_id = ''
    formData.slot_id = ''
  } else if (rawLocation.includes('/')) {
    const [rack, slot] = rawLocation.split('/').map((part: string) => part.trim())
    formData.location_rack = rack || ''
    formData.location_slot = slot || ''
    formData.rack_id = ''
    formData.slot_id = ''
  } else {
    formData.location_rack = rawLocation
    formData.location_slot = ''
    formData.rack_id = ''
    formData.slot_id = ''
  }
  formData.is_food = !!material.is_food
  formData.expiry_date = ''
}

function exitAddBatchMode() {
  isAddBatchMode.value = false
  selectedExistingMaterial.value = null
  formData.name = ''
  nameExists.value = false
  duplicateNameMaterial.value = null
}

/** Grünes Banner (Charge-Modus): Wizard wie beim Öffnen zurücksetzen und Daten neu laden. */
async function reloadWizardFromBatchBanner(): Promise<void> {
  await initializeOnOpen()
}

async function loadData() {
  try {
    const storageResult = await getAddresses(props.departmentId, 'storage').catch(() => ({ addresses: [] }))
    storageAddresses.value = storageResult.addresses || []
    
    if (storageAddresses.value.length > 0 && !formData.storage_address_id) {
      const defaultStorage = storageAddresses.value.find(a => 
        a.name?.toLowerCase().includes('standard') || a.is_primary
      ) || storageAddresses.value[0]
      formData.storage_address_id = String(defaultStorage.id ?? '')
    }
    const preferredStorageAddressId = getPreferredStorageAddressId()
    initialAllocations.value.forEach((row) => {
      if (!row.storage_address_id) row.storage_address_id = preferredStorageAddressId
    })
    serialNumbers.value.forEach((entry) => {
      if (!entry.storage_address_id) entry.storage_address_id = preferredStorageAddressId
    })

    await refreshDepartmentRacks()
    await prefetchVisibleRackPreviews(storageRacks.value)
    containerBatches.value = await getContainerBatches(props.departmentId).catch(() => [])
    await prefetchContainerPreviews()
    searchRackCategories()
    
    const supplierResult = await getMaterialWizardSuppliers(props.departmentId).catch(() => ({ addresses: [] }))
    const mergedSuppliers = supplierResult.addresses || []
    allManufacturers.value = mergedSuppliers
    allSuppliers.value = mergedSuppliers
    
    const materialsResult = await getMaterials(props.departmentId).catch(() => [])
    allMaterials.value = materialsResult || []
    
    // Repair-Parts-Kategorie sicherstellen (Materialseite), dann Kategorien laden
    if (!props.excludeRepairPartsCategory) {
      await getWorkshopSettings(props.departmentId).catch(() => null)
    }
    const categoriesResult = await getCategories(props.departmentId).catch(() => [])
    allCategories.value = categoriesResult || []
    applyFoodCategoryIfAvailable()
    searchCategories()

    // Vorlagen laden
    const templatesResult = await getTemplates(props.departmentId, true).catch(() => [])
    availableTemplates.value = templatesResult || []

    try {
      rentalAmortDefaults.value = await getRentalAmortizationDefaults(props.departmentId)
    } catch {
      rentalAmortDefaults.value = { ...DEFAULT_RENTAL_AMORTIZATION }
    }
  } catch (err) {
    console.error(t('components.materialCreateWizard.logErrorInitLoad'), err)
  }
}

function findFoodCategory(): Category | null {
  const categories = allCategories.value || []
  if (!categories.length) return null

  const normalized = (value: string) =>
    value
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .trim()

  const exactNames = new Set(['esswaren', 'lebensmittel', 'food'])
  const exactMatch = categories.find((cat) => exactNames.has(normalized(cat.name)))
  if (exactMatch) return exactMatch

  return categories.find((cat) => {
    const name = normalized(cat.name)
    return (
      name.includes('esswaren') ||
      name.includes('lebensmittel') ||
      name.includes('getrank') ||
      name.includes('snack') ||
      name.includes('food')
    )
  }) || null
}

function applyFoodCategoryIfAvailable(): void {
  if (!formData.is_food || isAddBatchMode.value) return
  const foodCategory = findFoodCategory()
  if (!foodCategory) return
  selectedCategory.value = foodCategory
  formData.category_id = foodCategory.id
  categorySearch.value = foodCategory.name
}

// Hersteller Suche
function searchManufacturers() {
  const query = manufacturerSearch.value.toLowerCase().trim()
  if (!query) {
    filteredManufacturers.value = allManufacturers.value.slice(0, 10)
    return
  }
  filteredManufacturers.value = allManufacturers.value
    .filter(a => (a.name?.toLowerCase().includes(query)) || (a.company?.toLowerCase().includes(query)))
    .slice(0, 10)
}

function selectManufacturer(addr: Address) {
  selectedManufacturer.value = addr
  manufacturerSearch.value = addr.name || addr.company || ''
  showManufacturerDropdown.value = false
  formData.manufacturer = addr.name || addr.company || ''
}

function clearManufacturer() {
  selectedManufacturer.value = null
  manufacturerSearch.value = ''
  formData.manufacturer = ''
}

function applyTemplateManufacturer(manufacturer: string | null | undefined) {
  const value = (manufacturer || '').trim()
  formData.manufacturer = value
  manufacturerSearch.value = value

  if (!value) {
    selectedManufacturer.value = null
    return
  }

  const valueLower = value.toLowerCase()
  const existing = allManufacturers.value.find(a => {
    const name = (a.name || '').trim().toLowerCase()
    const company = (a.company || '').trim().toLowerCase()
    return name === valueLower || company === valueLower
  }) || null

  selectedManufacturer.value = existing
}

function hideManufacturerDropdownDelayed() {
  setTimeout(() => { showManufacturerDropdown.value = false }, 200)
}

function updateCategoryDropdownPosition(retry = 0) {
  const wrap = categoryAutocompleteRef.value
  if (!wrap) return
  const input = wrap.querySelector('input')
  const r = (input ?? wrap).getBoundingClientRect()
  if ((r.width < 2 || r.height < 2) && retry < 30) {
    requestAnimationFrame(() => updateCategoryDropdownPosition(retry + 1))
    return
  }
  const w = Math.max(Math.round(r.width), 240)
  categoryDropdownFixedStyle.value = {
    position: 'fixed',
    top: `${Math.round(r.bottom + 4)}px`,
    left: `${Math.round(r.left)}px`,
    width: `${w}px`,
    zIndex: '99999',
  }
}

function bindCategoryDropdownPositionListeners() {
  if (categoryDropdownPositionListenersBound) return
  categoryDropdownPositionListenersBound = true
  categoryPositionHandler = () => updateCategoryDropdownPosition(0)
  window.addEventListener('resize', categoryPositionHandler)
  wizardFormRef.value?.addEventListener('scroll', categoryPositionHandler, { passive: true })
}

function unbindCategoryDropdownPositionListeners() {
  if (!categoryDropdownPositionListenersBound || !categoryPositionHandler) return
  categoryDropdownPositionListenersBound = false
  window.removeEventListener('resize', categoryPositionHandler)
  wizardFormRef.value?.removeEventListener('scroll', categoryPositionHandler)
  categoryPositionHandler = null
}

function onCategoryInputFocus() {
  showCategoryDropdown.value = true
  searchCategories()
  void nextTick(() => {
    updateCategoryDropdownPosition(0)
    requestAnimationFrame(() => updateCategoryDropdownPosition(0))
  })
}

function buildHierarchicalCategoryList(pool: Category[]): Category[] {
  const sorted: Category[] = []
  const mainCats = pool.filter((c) => !c.parent_id)
  mainCats.forEach((main) => {
    sorted.push(main)
    sorted.push(...pool.filter((c) => c.parent_id === main.id))
  })
  return sorted
}

function wizardCategoryPool(): Category[] {
  if (props.excludeRepairPartsCategory) {
    return filterUserSelectableCategories(allCategories.value)
  }
  return allCategories.value
}

// Kategorie Suche
function searchCategories() {
  const query = categorySearch.value.toLowerCase().trim()
  const pool = wizardCategoryPool()
  if (!query) {
    let sorted = buildHierarchicalCategoryList(pool)
    if (!props.excludeRepairPartsCategory) {
      const repairIdx = sorted.findIndex((c) => isRepairPartsCategory(c))
      if (repairIdx > 0) {
        const [repairCat] = sorted.splice(repairIdx, 1)
        sorted.unshift(repairCat)
      }
    }
    filteredCategories.value = sorted.slice(0, 20)
  } else {
    filteredCategories.value = pool
      .filter((c) => c.name.toLowerCase().includes(query))
      .slice(0, 20)
  }
  nextTick(() => updateCategoryDropdownPosition(0))
}

function selectCategory(cat: Category) {
  selectedCategory.value = cat
  categorySearch.value = cat.name
  formData.category_id = cat.id
  showCategoryDropdown.value = false
}

function clearCategory() {
  selectedCategory.value = null
  categorySearch.value = ''
  formData.category_id = ''
}

function hideCategoryDropdownDelayed() {
  setTimeout(() => { showCategoryDropdown.value = false }, 200)
}

function getCategoryPath(cat: Category): string {
  if (!cat.parent_id) return cat.name
  const parent = allCategories.value.find(c => c.id === cat.parent_id)
  return parent ? `${parent.name} › ${cat.name}` : cat.name
}

function normalizeName(value: string): string {
  return value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
}

function searchRackCategories() {
  const query = normalizeName(formData.location_rack)
  const racks = storageRacks.value
  if (!query) {
    filteredRackOptions.value = racks.slice(0, 15)
    return
  }
  filteredRackOptions.value = racks
    .filter((rack) => normalizeName(rack.name).includes(query))
    .slice(0, 15)
}

async function selectRackCategory(rack: StorageRack) {
  formData.location_rack = rack.name
  formData.rack_id = String(rack.id)
  const slots = await loadSlotsForRack(String(rack.id))
  storageSlots.value = slots
  const matching = storageSlots.value.find((slot) => normalizeName(slot.name) === normalizeName(formData.location_slot))
  formData.slot_id = matching?.id ? String(matching.id) : ''
  showRackDropdown.value = false
}

function hideRackDropdownDelayed() {
  setTimeout(() => { showRackDropdown.value = false }, 200)
}

/** Vorschlag für Artikelnamen der Combo aus Kisten-Daten (Label/Display; Nutzer kann editieren). */
function suggestedArticleNameFromContainerBatch(cb: import('@/api/storageLocations').ContainerBatch | undefined): string {
  if (!cb) return ''
  const d = (cb.display_label || '').trim()
  if (d) return d
  const label = (cb.label || '').trim()
  if (label) return label
  const serial = (cb.serial_number || '').trim()
  if (serial) return serial
  return (cb.material_name || '').trim() || 'Kombination'
}

function resolveContainerBatchLabel(containerBatchId: string): string {
  const cb = containerBatches.value.find((c) => c.id === containerBatchId)
  if (!cb) return ''
  return (cb.label || cb.serial_number || cb.display_label || cb.material_name || '').trim()
}

function formatSlotOptionLabel(rackId: string, slot: StorageSlot): string {
  const key = `${rackId}:${String(slot.id)}`
  const preview = (slotPreviewTitles.value[key] || '').trim()
  const base = slot.name
  if (preview) {
    return `${base} · ${preview}`
  }
  return base
}

function formatRackOptionLabel(rack: StorageRack): string {
  // Nur Gestellname in der Liste — Inhaltsvorschau bleibt im title-Tooltip (weniger Verwirrung).
  return rack.name
}

async function prefetchRackPreview(rackId: string) {
  if (!rackId || rackPreviewTitles.value[rackId]) return
  if (!storageOverviewCache.value) {
    storageOverviewCache.value = await getStorageOverview(props.departmentId).catch(() => null)
  }
  const overviewRack = storageOverviewCache.value?.racks?.find((r) => r.id === rackId)
  const resolve = (id: string) => resolveContainerBatchLabel(id)

  let text = ''
  if (overviewRack?.slots?.length) {
    text = formatRackSlotsDirectPreview(overviewRack.slots, resolve).trim()
  }
  if (!text) {
    const data = await getRackContents(rackId).catch(() => null)
    const items = (data?.contents || []).map((c: { material_name: string; qty: number }) => ({
      material_name: c.material_name || t('common.material'),
      qty: Number(c.qty || 0),
    }))
    text = summarizeMaterialsForPreview(items)
  }
  if (!text.trim()) text = 'Leer'

  rackPreviewTitles.value = {
    ...rackPreviewTitles.value,
    [rackId]: text,
  }
}

async function prefetchSlotPreview(rackId: string, slotId: string) {
  if (!rackId || !slotId) return
  const key = `${rackId}:${slotId}`
  if (slotPreviewTitles.value[key]) return
  if (!storageOverviewCache.value) {
    storageOverviewCache.value = await getStorageOverview(props.departmentId).catch(() => null)
  }
  const rack = storageOverviewCache.value?.racks?.find((r) => r.id === rackId)
  const slot = rack?.slots?.find((s) => String(s.id) === String(slotId))
  const line = formatFachSelectPreviewLine(slot?.contents || [])
  slotPreviewTitles.value = {
    ...slotPreviewTitles.value,
    [key]: line,
  }
}

async function prefetchVisibleRackPreviews(racks: StorageRack[]) {
  const sample = racks.slice(0, 20)
  await Promise.all(sample.map((rack) => prefetchRackPreview(rack.id)))
}

async function prefetchSlotPreviewsForRack(rackId: string) {
  if (!rackId) return
  if (!storageOverviewCache.value) {
    storageOverviewCache.value = await getStorageOverview(props.departmentId).catch(() => null)
  }
  const rack = storageOverviewCache.value?.racks?.find((r) => r.id === rackId)
  if (!rack?.slots?.length) return
  const next: Record<string, string> = { ...slotPreviewTitles.value }
  for (const slot of rack.slots) {
    const key = `${rackId}:${String(slot.id)}`
    if (next[key]) continue
    next[key] = formatFachSelectPreviewLine(slot.contents || [])
  }
  slotPreviewTitles.value = next
}

async function prefetchContainerPreviews() {
  if (!containerBatches.value.length) return
  if (!storageOverviewCache.value) {
    storageOverviewCache.value = await getStorageOverview(props.departmentId).catch(() => null)
  }
  const grouped: Record<string, Array<{ material_name: string; qty: number }>> = {}
  for (const rack of storageOverviewCache.value?.racks || []) {
    for (const slot of rack.slots || []) {
      for (const content of slot.contents || []) {
        const containerId = (content.container_batch_id || '').trim()
        if (!containerId) continue
        if (!grouped[containerId]) grouped[containerId] = []
        grouped[containerId].push({
          material_name: content.material_name || t('common.material'),
          qty: Number(content.qty || 0),
        })
      }
    }
  }
  const next: Record<string, string> = { ...containerPreviewTitles.value }
  for (const cb of containerBatches.value) {
    if (next[cb.id]) continue
    next[cb.id] = summarizeMaterialsForPreview(grouped[cb.id] || [])
  }
  containerPreviewTitles.value = next
}

function getContainerPreviewTitle(containerBatchId: string): string {
  if (!containerBatchId) return t('components.materialCreateWizard.pickBoxShort')
  const cb = containerBatches.value.find((c) => c.id === containerBatchId)
  if (cb) return formatContainerBatchOptionFullLabel(cb)
  return containerPreviewTitles.value[containerBatchId] || t('components.materialCreateWizard.previewHoverLoading')
}

function getRackPreviewTitle(rackId: string): string {
  if (!rackId) return t('components.materialCreateWizard.pickRackShort')
  return rackPreviewTitles.value[rackId] || t('components.materialCreateWizard.previewHoverLoading')
}

function getSlotPreviewTitle(rackId: string, slotId: string): string {
  if (!slotId) return t('components.materialCreateWizard.pickSlotShort')
  return slotPreviewTitles.value[`${rackId}:${slotId}`] || t('components.materialCreateWizard.previewHoverLoading')
}

/** Ein Handler: zuerst rack_id setzen, dann Slots laden (kein zweites rackChange-Event nötig). */
async function onStorageLocationRackUpdate(rackId: string) {
  const rid = String(rackId ?? '').trim()
  formData.rack_id = rid
  formData.slot_id = ''
  if (!rid) {
    storageSlots.value = []
    return
  }
  const slots = await loadSlotsForRack(rid)
  // Verhindert leere Fächer nach parallel Lagerstandort-Wechsel / Typ-Koersion (Zahl vs. String)
  if (String(formData.rack_id ?? '').trim() !== rid) return
  storageSlots.value = slots
}

async function addRackCategory() {
  if (isCreatingRack.value) return
  const rackName = formData.location_rack.trim()
  if (rackName.length < 2) {
    toast.error(t('components.materialCreateWizard.toastEnterRackNameFirst'))
    return
  }
  if (!formData.storage_address_id) {
    toast.error(t('components.materialCreateWizard.toastPickStorageFirst'))
    return
  }

  try {
    isCreatingRack.value = true
    const existingRack = storageRacks.value.find((rack) =>
      normalizeName(rack.name) === normalizeName(rackName)
    )
    if (existingRack) {
      formData.location_rack = existingRack.name
      formData.rack_id = String(existingRack.id)
      const slots = await loadSlotsForRack(String(existingRack.id))
      storageSlots.value = slots
      formData.slot_id = slots[0]?.id ? String(slots[0].id) : ''
      searchRackCategories()
      toast.success(t('components.materialCreateWizard.toastRackAlreadyExists'))
      return
    }

    const createdRack = await createStorageRack({
      department_id: props.departmentId,
      name: rackName,
      storage_address_id: formData.storage_address_id,
    })

    // Sofortige UI-Synchronisierung: neu erstelltes Regal direkt lokal sichtbar machen.
    // API-Responses enthalten je nach Endpoint nicht immer konsistente Typen/Felder.
    const normalizedCreatedRack: StorageRack = {
      ...createdRack,
      id: String((createdRack as any).id),
      storage_address_id: String(
        (createdRack as any).storage_address_id ?? formData.storage_address_id ?? ''
      ),
    }
    const existsLocally = allStorageRacks.value.some(
      (rack) => String(rack.id) === String(normalizedCreatedRack.id)
    )
    if (!existsLocally) {
      allStorageRacks.value = [...allStorageRacks.value, normalizedCreatedRack]
    }
    searchRackCategories()

    await refreshDepartmentRacks()
    containerBatches.value = await getContainerBatches(props.departmentId).catch(() => [])
    await prefetchContainerPreviews()
    formData.location_rack = normalizedCreatedRack.name
    formData.rack_id = String(normalizedCreatedRack.id)
    const createdSlots = await loadSlotsForRack(String(normalizedCreatedRack.id))
    storageSlots.value = createdSlots
    formData.slot_id = createdSlots[0]?.id ? String(createdSlots[0].id) : ''
    searchRackCategories()
    toast.success(t('components.materialCreateWizard.toastRackCreated'))
  } catch (err: any) {
    const apiError = String(err?.response?.data?.error || '')
    const conflict =
      err?.response?.status === 409 ||
      /namenskonflikt|name.*exist|already exists|duplicate/i.test(apiError)

    if (conflict) {
      // Falls der Request serverseitig bereits erfolgreich war (Doppelklick/Race), Rack neu laden und übernehmen.
      await refreshDepartmentRacks()
      const recoveredRack = storageRacks.value.find(
        (rack) =>
          normalizeName(rack.name) === normalizeName(rackName) &&
          String(rack.storage_address_id || '') === String(formData.storage_address_id || '')
      )
      if (recoveredRack) {
        formData.location_rack = recoveredRack.name
        formData.rack_id = String(recoveredRack.id)
        const slots = await loadSlotsForRack(String(recoveredRack.id))
        storageSlots.value = slots
        formData.slot_id = slots[0]?.id ? String(slots[0].id) : ''
        searchRackCategories()
        toast.success(t('components.materialCreateWizard.toastRackAlreadyExists'))
        return
      }
    }

    toast.error(apiError || t('components.materialCreateWizard.toastRackCreateFailed'))
  } finally {
    isCreatingRack.value = false
  }
}

function openAddCategoryModal() {
  categoryModalDefaultName.value = categorySearch.value.trim()
  showCategoryModal.value = true
}

async function handleCategorySaved(newCategory: Category) {
  showCategoryModal.value = false
  
  // Kategorien neu laden
  const categoriesResult = await getCategories(props.departmentId).catch(() => [])
  allCategories.value = categoriesResult || []
  searchCategories()
  
  // Neue Kategorie auswählen
  selectCategory(newCategory)
  categoryModalDefaultName.value = ''
}

// Lieferant Suche
function searchSuppliers() {
  const query = supplierSearch.value.toLowerCase().trim()
  if (!query) {
    filteredSuppliers.value = allSuppliers.value.slice(0, 10)
    return
  }
  filteredSuppliers.value = allSuppliers.value
    .filter(a => (a.name?.toLowerCase().includes(query)) || (a.company?.toLowerCase().includes(query)))
    .slice(0, 10)
}

function selectSupplier(addr: Address) {
  selectedSupplier.value = addr
  supplierSearch.value = addr.name || addr.company || ''
  showSupplierDropdown.value = false
  formData.supplier_id = addr.id
}

function clearSupplier() {
  selectedSupplier.value = null
  supplierSearch.value = ''
  formData.supplier_id = ''
}

function hideSupplierDropdownDelayed() {
  setTimeout(() => { showSupplierDropdown.value = false }, 200)
}

const virtualComboHasStockArticle = computed(() =>
  selectedComboMaterials.value
    .filter(isComboArticleDone)
    .some((m) => m.component_source === 'stock'),
)

function isComboArticleDone(mat: ComboArticleInput): boolean {
  if (mat.component_source === 'self_provided') {
    return !!mat.name.trim() && mat.qty > 0
  }
  const label = mat.name.trim()
  if (mat.mode === 'new') {
    if (!mat.id && !label) return false
    if (mat.tracking === 'serialized') return !!mat.serial_number.trim()
    return mat.qty > 0
  }
  if (!mat.id) return false
  if (mat.tracking === 'serialized') return !!mat.batch_id
  return mat.qty > 0
}

function isComboArticlePartiallyFilled(mat: ComboArticleInput): boolean {
  return !!(
    mat.id ||
    mat.name.trim() ||
    mat.serial_number.trim() ||
    mat.batch_id ||
    mat.unit_price ||
    (mat._materialSearch || '').trim()
  )
}

function createEmptyComboArticle(mode: 'new' | 'existing' = 'new'): ComboArticleInput {
  return {
    draftId: `combo-draft-${++comboDraftCounter}`,
    id: '',
    name: '',
    total_stock: 0,
    free_stock: 0,
    tracking: 'serialized',
    qty: 1,
    mode,
    component_source: 'stock',
    serial_number: '',
    unit_price: '',
    batch_id: '',
    is_linked_container: false,
    _materialSearch: '',
    _availableBatches: [],
    _nameExists: false,
    _duplicateMaterial: null,
    _nameSuggestions: [],
    _showNameSuggestions: false,
    _isCheckingName: false,
  }
}

const comboArticleNameCheckTimeouts: Record<string, ReturnType<typeof setTimeout>> = {}

function filterPhysicalMaterialsForComboLookup(materials: any[]): any[] {
  return materials.filter((m) => m.material_type === 'physical')
}

async function refreshComboArticleNameSuggestions(mat: ComboArticleInput): Promise<void> {
  const q = mat.name.trim()
  if (q.length < NAME_SUGGEST_MIN_CHARS) {
    mat._nameSuggestions = []
    mat._showNameSuggestions = false
    return
  }
  try {
    const materials = filterPhysicalMaterialsForComboLookup(await materialNameLookupFetcher(q))
    const ql = q.toLowerCase()
    mat._nameSuggestions = materials
      .filter((m) => m.name.toLowerCase().includes(ql))
      .slice(0, NAME_SUGGEST_LIMIT)
    mat._showNameSuggestions = (mat._nameSuggestions?.length ?? 0) > 0
  } catch {
    mat._nameSuggestions = []
    mat._showNameSuggestions = false
  }
}

function checkComboArticleNameDebounced(mat: ComboArticleInput): void {
  const existing = comboArticleNameCheckTimeouts[mat.draftId]
  if (existing) clearTimeout(existing)
  const q = mat.name.trim()
  if (!q) {
    mat._nameExists = false
    mat._duplicateMaterial = null
    mat._nameSuggestions = []
    mat._showNameSuggestions = false
    return
  }
  if (q.length < NAME_SUGGEST_MIN_CHARS) {
    mat._nameSuggestions = []
    mat._showNameSuggestions = false
    return
  }
  comboArticleNameCheckTimeouts[mat.draftId] = setTimeout(() => {
    void refreshComboArticleNameSuggestions(mat)
  }, 350)
}

async function performComboArticleNameDuplicateCheck(mat: ComboArticleInput): Promise<void> {
  const q = mat.name.trim()
  if (!q || mat.mode !== 'new' || mat.id) {
    mat._nameExists = false
    mat._duplicateMaterial = null
    mat._isCheckingName = false
    return
  }
  mat._isCheckingName = true
  try {
    const materials = filterPhysicalMaterialsForComboLookup(await materialNameLookupFetcher(q))
    const exact = materials.find((m) => m.name.toLowerCase() === q.toLowerCase())
    mat._nameExists = !!exact
    mat._duplicateMaterial = exact ?? null
    const ql = q.toLowerCase()
    mat._nameSuggestions = materials
      .filter((m) => m.name.toLowerCase().includes(ql))
      .slice(0, NAME_SUGGEST_LIMIT)
  } catch {
    mat._nameExists = false
    mat._duplicateMaterial = null
    mat._nameSuggestions = []
  } finally {
    mat._isCheckingName = false
  }
}

function handleComboArticleNameFocus(mat: ComboArticleInput): void {
  mat._showNameSuggestions = (mat._nameSuggestions?.length ?? 0) > 0
}

function handleComboArticleNameBlur(mat: ComboArticleInput): void {
  setTimeout(() => {
    mat._showNameSuggestions = false
  }, 200)
  const pending = comboArticleNameCheckTimeouts[mat.draftId]
  if (pending) {
    clearTimeout(pending)
    delete comboArticleNameCheckTimeouts[mat.draftId]
  }
  void performComboArticleNameDuplicateCheck(mat)
}

function comboArticleTrackingLabel(tracking: 'serialized' | 'bulk'): string {
  return tracking === 'serialized'
    ? t('components.materialCreateWizard.trackingSerializedShort')
    : t('components.materialCreateWizard.trackingBulkShort')
}

function applyExistingComboArticleForNewBatch(mat: ComboArticleInput, material: any): void {
  mat._showNameSuggestions = false
  mat._nameExists = false
  mat._duplicateMaterial = null
  mat._nameSuggestions = []
  mat.mode = 'new'
  mat.id = material.id
  mat.name = material.name
  mat.tracking = material.tracking_type === 'serialized' ? 'serialized' : 'bulk'
  mat.is_container_material = !!material.is_container
  mat.category = material.category
  mat.total_stock = material.total_stock ?? 0
  mat.free_stock = material.free_stock ?? material.total_stock ?? 0
  mat.serial_number = ''
  mat.batch_id = ''
  mat.unit_price = ''
  mat._materialSearch = material.name
  if (mat.tracking === 'bulk' && mat.qty < 1) mat.qty = 1
}

async function useExistingComboArticleForNewBatch(mat: ComboArticleInput, material: any): Promise<void> {
  applyExistingComboArticleForNewBatch(mat, material)
}

function clearComboArticleExistingLink(mat: ComboArticleInput): void {
  const keepName = mat.name
  mat.id = ''
  mat.batch_id = ''
  mat.serial_number = ''
  mat.is_container_material = undefined
  mat.is_linked_container = false
  mat.total_stock = 0
  mat.free_stock = 0
  mat._nameExists = false
  mat._duplicateMaterial = null
  mat.name = keepName
}

async function selectComboArticleNameSuggestion(mat: ComboArticleInput, material: any): Promise<void> {
  applyExistingComboArticleForNewBatch(mat, material)
}

const comboLinkedContainerDraft = computed(() =>
  selectedComboMaterials.value.find((mat) => mat.is_linked_container) ?? null,
)

const hasComboLinkedContainerFromComponent = computed(
  () => comboLinkedContainerDraft.value !== null,
)

function canMarkComboArticleAsLinkedContainer(mat: ComboArticleInput): boolean {
  if (formData.material_type !== 'physical_combo') return false
  if (mat.mode === 'new') return true
  if (mat.mode === 'existing' && mat.id) {
    return mat.is_container_material === true || mat.tracking === 'serialized'
  }
  return false
}

function setComboArticleLinkedContainer(mat: ComboArticleInput, checked: boolean) {
  if (!checked) {
    mat.is_linked_container = false
    return
  }
  for (const other of selectedComboMaterials.value) {
    other.is_linked_container = other.draftId === mat.draftId
  }
  if (hasComboLinkedContainerFromComponent.value) {
    formData.stock_location_mode = 'slot'
    formData.stock_container_batch_id = ''
  }
}

function addEmptyComboArticle() {
  selectedComboMaterials.value.push(createEmptyComboArticle('new'))
}

function addEmptySelfProvidedComboArticle() {
  selectedComboMaterials.value.push({
    ...createEmptyComboArticle('new'),
    component_source: 'self_provided',
    tracking: 'bulk',
    mode: 'new',
  })
}

function setComboArticleTracking(mat: ComboArticleInput, tracking: 'serialized' | 'bulk') {
  if (mat.id) return
  mat.tracking = tracking
  mat.serial_number = ''
  mat.batch_id = ''
  if (tracking === 'bulk' && mat.qty < 1) mat.qty = 1
}

watch(hasComboLinkedContainerFromComponent, (hasLinked) => {
  if (hasLinked) {
    formData.stock_location_mode = 'slot'
    formData.stock_container_batch_id = ''
  }
})

async function loadComboArticleBatches(mat: ComboArticleInput) {
  if (!mat.id || mat.tracking !== 'serialized') return
  try {
    const fullMaterial = await getMaterial(mat.id)
    mat._availableBatches = (fullMaterial.batches || []).filter(
      (b: MaterialBatch) => b.status === 'active' && b.serial_number,
    )
  } catch {
    mat._availableBatches = []
  }
}

async function setComboArticleMode(mat: ComboArticleInput, mode: 'new' | 'existing') {
  mat.mode = mode
  mat.serial_number = ''
  mat.unit_price = ''
  mat.batch_id = ''
  mat.is_linked_container = false
  mat.is_container_material = undefined
  mat._nameExists = false
  mat._duplicateMaterial = null
  mat._nameSuggestions = []
  mat._showNameSuggestions = false
  mat._isCheckingName = false
  if (mode === 'existing') {
    if (mat.name.trim() && !mat._materialSearch) {
      mat._materialSearch = mat.name.trim()
    }
    if (mat.id && mat.tracking === 'serialized') {
    await loadComboArticleBatches(mat)
  }
  } else if (mat.id) {
    clearComboArticleSelection(mat)
  }
}

async function onComboArticleSearchSelect(mat: ComboArticleInput, suggestion: { type: string; id: string }) {
  if (suggestion.type !== 'material') return
  try {
    const loaded = await getMaterial(suggestion.id)
    if (loaded.material_type !== 'physical') {
      toast.error(t('components.materialCreateWizard.compSearchOnlyPhysical'))
      return
    }
    mat.id = loaded.id
    mat.name = loaded.name
    mat.category = loaded.category ?? undefined
    mat.total_stock = loaded.total_stock ?? 0
    mat.free_stock = loaded.free_stock ?? loaded.total_stock ?? 0
    mat.tracking = loaded.tracking_type === 'serialized' ? 'serialized' : 'bulk'
    mat.is_container_material = !!loaded.is_container
    mat._materialSearch = loaded.name
    mat.batch_id = ''
    mat.is_linked_container = false
    if (mat.tracking === 'serialized') {
      await loadComboArticleBatches(mat)
    }
  } catch (err) {
    console.error(t('components.materialCreateWizard.logErrorLoadMaterial'), err)
    toast.error(t('components.materialCreateWizard.compSearchLoadFailed'))
  }
}

function clearComboArticleSelection(mat: ComboArticleInput) {
  const keepName = mat.name
  mat.id = ''
  mat.batch_id = ''
  mat.is_linked_container = false
  mat.is_container_material = undefined
  mat._materialSearch = keepName
  mat._availableBatches = []
  mat.total_stock = 0
  mat.free_stock = 0
}

// Kombinations-Material Suche (zentrale API wie MaterialLookup)
let comboMaterialSearchToken = 0
async function searchComboMaterials() {
  const query = comboMaterialSearch.value.trim()
  if (query.length < 1) {
    filteredComboMaterials.value = []
    return
  }
  const token = ++comboMaterialSearchToken
  try {
    const materials = await materialNameLookupFetcher(query)
    if (token !== comboMaterialSearchToken) return
    const selectedIds = selectedComboMaterials.value.map((m) => m.id)
    filteredComboMaterials.value = materials
      .filter(
        (m: any) =>
          m.material_type === 'physical' &&
          !selectedIds.includes(m.id)
      )
      .slice(0, 15)
  } catch {
    if (token === comboMaterialSearchToken) filteredComboMaterials.value = []
  }
}

async function addComboMaterial(mat: any) {
  const tracking = (mat.tracking_type === 'serialized' ? 'serialized' : 'bulk') as 'serialized' | 'bulk'
  const entry: ComboArticleInput = {
    ...createEmptyComboArticle('existing'),
    id: mat.id,
    name: mat.name,
    category: mat.category,
    total_stock: mat.total_stock ?? 0,
    free_stock: mat.free_stock ?? mat.total_stock ?? 0,
    tracking,
    component_source: 'stock',
    _materialSearch: mat.name,
  }
  selectedComboMaterials.value.push(entry)
  if (tracking === 'serialized') {
    await loadComboArticleBatches(entry)
  }
  comboMaterialSearch.value = ''
  filteredComboMaterials.value = []
}

function ensureInitialComboArticleRow() {
  if (
    formData.material_type !== 'physical_combo' ||
    isFromTemplate.value ||
    isFromContainerBatchContents.value ||
    selectedComboMaterials.value.length > 0
  ) {
    return
  }
  addEmptyComboArticle()
}

function removeComboMaterial(index: number) {
  selectedComboMaterials.value.splice(index, 1)
}

// Adress-Modal öffnen
function openAddStorageModal() {
  addressModalType.value = 'storage'
  addressModalDefaultName.value = ''
  showAddressModal.value = true
}

function openAddManufacturerModal() {
  addressModalType.value = 'manufacturer'
  addressModalDefaultName.value = manufacturerSearch.value.trim()
  showAddressModal.value = true
}

function openAddSupplierModal() {
  addressModalType.value = 'supplier'
  addressModalDefaultName.value = supplierSearch.value.trim()
  showAddressModal.value = true
}

async function handleAddressSaved() {
  const savedName = addressModalDefaultName.value.toLowerCase()
  const savedType = addressModalType.value
  
  showAddressModal.value = false
  await loadData()
  
  // Automatisch die neu erstellte Adresse auswählen
  if (savedName && savedType !== 'storage') {
    const newAddress = allSuppliers.value.find(a => 
      (a.name?.toLowerCase() === savedName) || 
      (a.company?.toLowerCase() === savedName)
    )
    
    if (newAddress) {
      if (savedType === 'manufacturer') {
        selectManufacturer(newAddress)
      } else if (savedType === 'supplier') {
        selectSupplier(newAddress)
      }
    }
  }
  
  addressModalDefaultName.value = ''
}

// ============ Template-Funktionen ============
function onTemplateSearchUpdate(value: string) {
  templateSearch.value = value
  searchTemplates()
}

function searchTemplates() {
  const query = templateSearch.value.toLowerCase().trim()
  if (!query) {
    filteredTemplateList.value = availableTemplates.value.slice(0, 30)
    return
  }
  filteredTemplateList.value = availableTemplates.value
    .filter(t =>
      t.name.toLowerCase().includes(query) ||
      (t.manufacturer?.toLowerCase().includes(query)) ||
      (t.model?.toLowerCase().includes(query))
    )
    .slice(0, 30)
}

/** Menge in der Kartenzeile; bei optional + Bulk darf 0× angezeigt werden. */
function templateBulkMetaQty(ci: ComponentInput): number {
  if (ci.tracking === 'bulk' && ci.is_optional) {
    const q = Number(ci.qty)
    if (!Number.isNaN(q) && q <= 0) return 0
  }
  return Math.max(1, Number(ci.qty) || Number(ci.required_qty) || 1)
}

/** Nur Mengenbegrenzung für Bulk (keine SN — die sind nur bei serialisierten Komponenten). */
function normalizeBulkQty(ci: ComponentInput) {
  if (ci.tracking !== 'bulk') return
  let n = Math.floor(Number(ci.qty))
  if (Number.isNaN(n)) n = ci.is_optional ? 0 : 1
  if (!ci.is_optional && n < 1) n = 1
  if (ci.is_optional && n < 0) n = 0
  ci.qty = n
}

/** Optionale Komponente weglassen (kein API-Eintrag), wenn Menge 0 bzw. bei serialisiert ohne SN. */
function includeTemplateComponentInPayload(ci: ComponentInput): boolean {
  if (ci.component_source === 'self_provided') return true
  if (!ci.is_optional) return true
  if (ci.mode === 'new') {
    if (ci.tracking === 'serialized') return !!(ci.serial_number || '').trim()
    if (ci.tracking === 'bulk') return Number(ci.qty) > 0
    return true
  }
  if (ci.mode === 'existing') {
    if (!ci.material_id) return false
    if (ci.tracking === 'bulk') return Number(ci.qty) > 0
    return true
  }
  return true
}

async function selectTemplate(template: Template) {
  templateSearch.value = template.name
  showTemplateDropdown.value = false

  try {
    templateLoadInProgress.value = true
    accordionUserControlled.value = true
    expandAllVisibleSteps.value = false

    // Lade die vollständige Vorlage mit Komponenten
    const fullTemplate = await getTemplate(template.id, props.departmentId)
    selectedTemplate.value = fullTemplate
    isFromTemplate.value = true
    templateComponents.value = fullTemplate.components || []

    // Formular vorausfüllen
    formData.material_type = fullTemplate.material_type || 'physical_combo'
    formData.name = (fullTemplate.name || '').trim()
    if (fullTemplate.category) {
      formData.category_id = fullTemplate.category.id
      selectedCategory.value = { 
        id: fullTemplate.category.id, 
        name: fullTemplate.category.name, 
        parent_id: null,
        material_count: 0 
      } as Category
      categorySearch.value = fullTemplate.category.name
    }

    // Zelt-Details
    tentForm.tent_type = fullTemplate.tent_type || 'gruppenzelt'
    tentForm.tent_capacity = fullTemplate.capacity || null

    // Hersteller aus Vorlage automatisch übernehmen (bleibt manuell änderbar)
    applyTemplateManufacturer(fullTemplate.manufacturer)

    // Komponenten-Eingaben initialisieren (Auflösung vom Backend)
    componentInputs.value = (fullTemplate.components || []).map((comp) =>
      buildComponentInputFromTemplate(comp, formData.material_type),
    )

    for (const ci of componentInputs.value) {
      if (ci.tracking === 'serialized' && ci.mode === 'existing' && ci.material_id && ci._selectedMaterial) {
        await selectExistingMaterial(ci, ci._selectedMaterial)
      }
    }

    await performNameDuplicateCheck()
    activeStep.value = 'general'
    await nextTick()
    templateLoadInProgress.value = false
    await scrollWizardStepIntoView('general')
    await nextTick()
    const nameEl = articleNameInputRef.value as { focus?: () => void } | null
    nameEl?.focus?.()
  } catch (err) {
    templateLoadInProgress.value = false
    console.error(t('components.materialCreateWizard.logErrorLoadTemplate'), err)
  }
}

function onContainerContentsPickerChange() {
  selectedContainerBatchContents.value = null
  isFromContainerBatchContents.value = false
  componentInputs.value = []
}

function onContainerBatchIdForContentsChange(v: string) {
  containerContentsBatchId.value = v
  onContainerContentsPickerChange()
  if (v) {
    const cb = containerBatches.value.find((b) => String(b.id) === String(v))
    if (cb) {
      formData.name = suggestedArticleNameFromContainerBatch(cb)
      void performNameDuplicateCheck()
    }
    void loadContainerBatchContents()
  }
}

async function scrollWizardStepIntoView(step: StepId | ''): Promise<void> {
  if (!step) return
  await nextTick()
  const el = document.querySelector(
    `.material-wizard-form .step-section[data-step="${step}"]`
  ) as HTMLElement | null
  if (el && 'scrollIntoView' in el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

async function loadContainerBatchContents() {
  if (!containerContentsBatchId.value) return
  isLoadingContainerContents.value = true
  selectedContainerBatchContents.value = null
  try {
    const data = await getContainerBatchContents(containerContentsBatchId.value)
    if (!data.contents.length) {
      toast.error(t('components.materialCreateWizard.toastContainerEmpty'))
      return
    }
    accordionUserControlled.value = true
    expandAllVisibleSteps.value = false
    selectedContainerBatchContents.value = data
    isFromContainerBatchContents.value = true
    isFromTemplate.value = false
    selectedTemplate.value = null

    await refreshDepartmentRacks()

    const batchFromList = containerBatches.value.find((b) => String(b.id) === String(containerContentsBatchId.value))
    const labelFromApi = (data.container_label || '').trim()
    const labelFromBatch = (batchFromList?.label || batchFromList?.serial_number || '').trim()
    formData.name =
      labelFromApi ||
      labelFromBatch ||
      suggestedArticleNameFromContainerBatch(batchFromList)
    await performNameDuplicateCheck()

    componentInputs.value = data.contents.map((c) => {
      const isBulk = c.tracking_type !== 'serialized'
      const mat = allMaterials.value.find(m => m.id === c.material_id)
      const defaultMode = formData.material_type === 'physical_combo' ? 'fixed' : (isBulk ? 'bulk' : 'assigned')
      return {
        component_type: (c.material_name || 'material').toLowerCase().replace(/\s+/g, '_'),
        name: c.material_name,
        tracking: (c.tracking_type || 'bulk') as 'serialized' | 'bulk',
        required_qty: c.qty,
        is_optional: false,
        component_source: 'stock' as ComponentSource,
        mode: 'existing' as const,
        serial_number: '',
        qty: c.qty,
        unit_price: '',
        material_id: c.material_id,
        batch_id: '',
        assignment_mode: defaultMode,
        _materialSearch: mat?.name || c.material_name,
        _showDropdown: false,
        _filteredMaterials: [],
        _selectedMaterial: mat || null,
        _availableBatches: [],
      } as ComponentInput
    })

    // Vorauswahl Lager: dieselbe Kiste wie die Quelle; Lagerstandort aus dem Gestell der Kiste
    formData.stock_location_mode = 'kiste'
    formData.stock_container_batch_id = String(containerContentsBatchId.value)
    formData.rack_id = ''
    formData.slot_id = ''
    formData.location_rack = ''
    formData.location_slot = ''
    if (batchFromList?.rack_id) {
      const rack = allStorageRacks.value.find((r) => String(r.id) === String(batchFromList.rack_id))
      if (rack?.storage_address_id) {
        formData.storage_address_id = String(rack.storage_address_id)
      }
    }

    await nextTick()
    // Immer zuerst „Allgemeine Informationen“: Artikelname (Kisten-Label) prüfen/anpassen, dann Kategorie & Komponenten
    activeStep.value = 'general'
    await scrollWizardStepIntoView('general')
    await nextTick()
    const nameEl = articleNameInputRef.value as { focus?: () => void } | null
    nameEl?.focus?.()
  } catch (err) {
    console.error(t('components.materialCreateWizard.logErrorLoadContainerContents'), err)
    toast.error(t('components.materialCreateWizard.toastContainerLoadFailed'))
  } finally {
    isLoadingContainerContents.value = false
  }
}

async function selectCreationMode(mode: 'individual' | 'physical_combo' | 'virtual_combo') {
  creationMode.value = mode
  // Bei Combo-Modus: Gestelle + Kisten-Liste (für „Aus Kiste übernehmen“)
  if (mode === 'physical_combo' || mode === 'virtual_combo') {
    await refreshDepartmentRacks()
    if (!containerBatches.value.length) {
      containerBatches.value = await getContainerBatches(props.departmentId).catch(() => [])
    }
  }
  // Material-Typ automatisch setzen (Einzelartikel = physischer Artikel; Kombis über Erstellmodus)
  if (mode === 'individual') {
    formData.material_type = 'physical'
    // Vorherige Auswahlreste entfernen, damit der Wizard bei "Allgemeine Informationen" startet
    formData.tracking_type = ''
    formData.category_id = ''
    selectedCategory.value = null
    categorySearch.value = ''
    // Bei Einzelartikel: Name ist nicht erforderlich, setze Platzhalter
    formData.name = ''
  } else if (mode === 'physical_combo') {
    formData.material_type = 'physical_combo'
    ensureInitialComboArticleRow()
  } else {
    formData.material_type = 'virtual_combo'
  }

  // Accordion: nur ein Bereich offen; nach unten gehen / Fokus schließt den oberen (über onWizardFormFocusIn + activeStep)
  expandAllVisibleSteps.value = false
  accordionUserControlled.value = true
  activeStep.value = 'general'

  // Bei Einzelartikel direkt in das Artikelnamen-Feld springen
  if (mode === 'individual') {
    nextTick(() => {
      articleNameInputRef.value?.focus()
      articleNameInputRef.value?.select()
    })
  }
}

/** Vollständiger Formular-Neustart (Erstellmodus erneut wählen) — vermeidet Restzustände beim Moduswechsel. */
function resetWizardForModeChange() {
  resetForm()
  void nextTick(() => {
    wizardFormRef.value?.scrollTo({ top: 0, behavior: 'smooth' })
  })
}

function clearTemplate() {
  templateLoadInProgress.value = false
  isFromTemplate.value = false
  selectedTemplate.value = null
  templateSearch.value = ''
  templateComponents.value = []
  componentInputs.value = []
  isFromContainerBatchContents.value = false
  containerContentsBatchId.value = ''
  selectedContainerBatchContents.value = null
  tentForm.tent_type = ''
  tentForm.tent_capacity = null
  // Reset auch die automatisch gesetzten Felder
  formData.category_id = ''
  selectedCategory.value = null
  categorySearch.value = ''
}

function hideTemplateDropdownDelayed() {
  setTimeout(() => { showTemplateDropdown.value = false }, 200)
}

// ============ "Aus Bestand" Funktionen pro Komponente (zentrale Suche) ============
async function onCompMaterialSearchSelect(ci: ComponentInput, suggestion: SearchSuggestion) {
  if (suggestion.type !== 'material') return
  try {
    const mat = await getMaterial(suggestion.id)
    if (mat.material_type !== 'physical') {
      toast.error(t('components.materialCreateWizard.compSearchOnlyPhysical'))
      return
    }
    if (ci.tracking === 'serialized') {
      await selectExistingMaterial(ci, mat)
    } else {
      selectBulkMaterial(ci, mat)
    }
  } catch (err) {
    console.error(t('components.materialCreateWizard.logErrorLoadMaterial'), err)
    toast.error(t('components.materialCreateWizard.compSearchLoadFailed'))
  }
}

async function selectExistingMaterial(ci: ComponentInput, mat: any) {
  ci.material_id = mat.id
  ci._selectedMaterial = mat
  ci._materialSearch = mat.name

  // Für serialisierte Teile: Batches laden
  if (ci.tracking === 'serialized') {
    try {
      const fullMaterial = await getMaterial(mat.id)
      // Nur aktive Batches mit SN und Status "ok" anzeigen
      ci._availableBatches = (fullMaterial.batches || []).filter((b: any) =>
        b.status === 'active' && b.serial_number
      )
    } catch (err) {
      console.error(t('components.materialCreateWizard.logErrorLoadBatches'), err)
      ci._availableBatches = []
    }
  }
}

function clearExistingMaterial(ci: ComponentInput) {
  ci.material_id = ''
  ci.batch_id = ''
  ci._selectedMaterial = null
  ci._materialSearch = ''
  ci._availableBatches = []
}

function buildComponentInputFromTemplate(comp: TemplateComponent, materialType: string): ComponentInput {
  const isBulk = comp.tracking === 'bulk'
  const defaultMode = materialType === 'physical_combo' ? 'fixed' : (isBulk ? 'bulk' : 'assigned')
  const expectedName = comp.expected_name || comp.name
  const matchState = comp.match_state
  const compOptional = materialType === 'virtual_combo' ? comp.is_optional : false
  const rq = Math.max(1, comp.required_qty || 1)
  const optionalBulkStartZero = compOptional && isBulk
  const initialQty = optionalBulkStartZero ? 0 : rq

  let mode: 'new' | 'existing' = 'existing'
  let material_id = ''
  let selectedMaterial: { id: string; name: string } | null = null
  let search = expectedName

  if (matchState === 'found' && comp.matched_material_id) {
    material_id = comp.matched_material_id
    selectedMaterial = allMaterials.value.find((m) => m.id === material_id) ?? null
    search = selectedMaterial?.name || expectedName
  } else if (!matchState && isBulk) {
    const autoMaterial = allMaterials.value.find(
      (m) => m.material_type === 'physical' && m.name.toLowerCase() === expectedName.toLowerCase(),
    )
    if (autoMaterial) {
      material_id = autoMaterial.id
      selectedMaterial = autoMaterial
      search = autoMaterial.name
    }
  }

  const isSelfProvided = comp.component_source === 'self_provided'

  return {
    component_type: comp.component_type,
    name: comp.name,
    tracking: comp.tracking,
    required_qty: comp.required_qty,
    is_optional: compOptional,
    component_source: comp.component_source ?? 'stock',
    mode: isSelfProvided ? 'new' : mode,
    serial_number: '',
    qty: initialQty,
    unit_price: '',
    material_id,
    batch_id: '',
    assignment_mode: defaultMode,
    _materialSearch: search,
    _showDropdown: false,
    _filteredMaterials: [],
    _selectedMaterial: selectedMaterial,
    _availableBatches: [],
    _match_state: matchState,
    _expected_name: expectedName,
    _candidates: comp.candidates ?? [],
  }
}

async function pickComponentCandidate(ci: ComponentInput, candidate: { id: string; name: string }) {
  await selectExistingMaterial(ci, candidate)
  ci._match_state = 'found'
}

// Bulk-Material auswählen (vereinfacht, kein Batch nötig)
function selectBulkMaterial(ci: ComponentInput, mat: any) {
  ci.material_id = mat.id
  ci._selectedMaterial = mat
  ci._materialSearch = mat.name
}

function isComponentDone(ci: ComponentInput): boolean {
  if (ci.component_source === 'self_provided') return true
  if (ci.is_optional) {
    if (ci.mode === 'new' && ci.tracking === 'bulk' && !(ci.qty && ci.qty > 0)) return true
    if (ci.mode === 'new' && ci.tracking === 'serialized' && !(ci.serial_number || '').trim()) return true
    if (ci.mode === 'existing' && !ci.material_id) return true
    if (ci.mode === 'existing' && ci.tracking === 'bulk' && ci.material_id && (!(ci.qty) || ci.qty <= 0)) return true
  }
  if (ci.mode === 'new') {
    return ci.tracking === 'serialized' ? !!ci.serial_number : ci.qty > 0
  }
  // existing
  if (!ci.material_id) return false
  if (ci.tracking === 'serialized') return !!ci.batch_id
  return ci.qty > 0
}

function buildCombinedLocation(): string | null {
  const selectedRack = formData.rack_id
    ? storageRacks.value.find((entry) => String(entry.id) === String(formData.rack_id))?.name || ''
    : ''
  const selectedSlot = formData.slot_id
    ? storageSlots.value.find((entry) => String(entry.id) === String(formData.slot_id))?.name || ''
    : ''
  const rack = (selectedRack || formData.location_rack).trim()
  const slot = (selectedSlot || formData.location_slot).trim()
  if (!rack && !slot) return null
  if (rack && slot) return `${rack} / ${slot}`
  return rack || slot
}

async function ensureStorageSelection(): Promise<void> {
  if (!formData.rack_id) {
    formData.slot_id = ''
    formData.location_rack = ''
    formData.location_slot = ''
    storageSlots.value = []
    return
  }

  storageSlots.value = await loadSlotsForRack(String(formData.rack_id))
  if (
    formData.slot_id &&
    !storageSlots.value.some((entry) => String(entry.id) === String(formData.slot_id))
  ) {
    formData.slot_id = ''
  }

  const rackName =
    storageRacks.value.find((entry) => String(entry.id) === String(formData.rack_id))?.name || ''
  const slotName = formData.slot_id
    ? storageSlots.value.find((entry) => String(entry.id) === String(formData.slot_id))?.name || ''
    : ''
  formData.location_rack = rackName
  formData.location_slot = slotName
}

function isPhysicalComboCreation(): boolean {
  return creationMode.value === 'physical_combo' || formData.material_type === 'physical_combo'
}

/** Neukäufe in der Stückliste (mode=new mit Stückpreis) — Basis für Buchhaltungs-Follow-up. */
function computeComboNewPurchaseTotalChf(): number {
  let total = 0
  if (
    isPhysicalComboCreation() &&
    !isFromTemplate.value &&
    !isFromContainerBatchContents.value
  ) {
    for (const mat of selectedComboMaterials.value) {
      if (!isComboArticleDone(mat) || mat.mode !== 'new') continue
      const up = parseChfInput(String(mat.unit_price ?? ''))
      if (up <= 0) continue
      const qty = mat.tracking === 'serialized' ? 1 : Math.max(1, Math.floor(Number(mat.qty) || 1))
      total += up * qty
    }
  } else if (isFromTemplate.value && creationMode.value === 'physical_combo') {
    for (const ci of componentInputs.value) {
      if (!includeTemplateComponentInPayload(ci)) continue
      if (ci.mode !== 'new' || ci.component_source === 'self_provided') continue
      const up = parseChfInput(String(ci.unit_price ?? ''))
      if (up <= 0) continue
      const qty = ci.tracking === 'serialized' ? 1 : Math.max(1, Math.floor(Number(ci.qty) || 1))
      total += up * qty
    }
  }
  return Math.round(total * 100) / 100
}

function resolveComboFollowUpBatchId(
  result: Material,
  wizardComponents?: ComboArticleInput[],
): string | undefined {
  const linkedId = (result.linked_container_batch_id || result.linked_container_batch?.id || '').trim()
  if (wizardComponents?.length) {
    const newLinked = wizardComponents.find(
      (m) => m.mode === 'new' && m.is_linked_container && isComboArticleDone(m),
    )
    if (newLinked && linkedId) return linkedId
    for (const mat of wizardComponents.filter((m) => m.mode === 'new' && isComboArticleDone(m))) {
      const comp = result.combo_components?.find((c) => {
        if (mat.id && c.component_material.id === mat.id) return true
        const name = mat.name.trim()
        return !!name && c.component_material.name.trim() === name
      })
      const batchId = comp?.component_batch?.id
      if (batchId) return batchId
    }
  } else if (isFromTemplate.value) {
    for (const ci of componentInputs.value) {
      if (!includeTemplateComponentInPayload(ci) || ci.mode !== 'new') continue
      const comp = result.combo_components?.find((c) => {
        if (ci.material_id && c.component_material.id === ci.material_id) return true
        const name = (ci.name || ci.component_type || '').trim()
        return !!name && c.component_material.name.trim() === name
      })
      const batchId = comp?.component_batch?.id
      if (batchId) return batchId
    }
  }
  if (linkedId) return linkedId
  return result.combo_components?.find((c) => c.component_batch?.id)?.component_batch?.id
}

/** CHF-Gesamtbetrag für Buchhaltungs-Hinweis (Stückpreis × Menge bzw. Serienzeilen / Combo-Neukäufe). */
function computeWizardPurchaseTotalChf(): number {
  if (isPhysicalComboCreation()) {
    return computeComboNewPurchaseTotalChf()
  }
  const shipping = parseChfInput(purchaseShippingChf.value)
  if (purchasePriceInputMode.value === 'total') {
    const sum = parseChfInput(purchaseTotalWaresChf.value) + shipping
    return sum > 0 ? sum : 0
  }
  const up = parseMaterialChfInput(formData.unit_price)
  if (up <= 0 && shipping <= 0) return 0
  if (formData.tracking_type === 'serialized') {
    const n = serialNumbers.value.filter((s) => (s.serial_number || '').trim()).length
    return up * n + shipping
  }
  if (wizardUseMeterQtyByCount.value) {
    return up * wizardDisplayQty.value + shipping
  }
  const q = Number(formData.initial_qty) || 0
  return up * q + shipping
}

/** Für Vermiet-Amortisationsrechner: gleiche Basis wie Buchhaltungs-Hinweis (Stückpreis × Menge). */
const wizardRentalHistoricalBasisChf = computed((): number | null => {
  const t = computeWizardPurchaseTotalChf()
  return t > 0 ? t : null
})

/** Stückzahl für Vermiet-Rechner (wie Stückpreis × Menge). */
const wizardRentalPieceCount = computed((): number | null => {
  const q = purchasePriceContextQty.value
  return q > 0 ? q : null
})

function onWizardRentalCalculatorApply(p: { day: string; week: string; month: string }) {
  const rentalChanged =
    formData.rental_price_day !== p.day ||
    formData.rental_price_week !== p.week ||
    formData.rental_price_month !== p.month
  formData.rental_price_day = p.day
  formData.rental_price_week = p.week
  formData.rental_price_month = p.month
  if (rentalChanged) {
    toast.success(t('components.materialCreateWizard.toastRentalSuggestionApplied'))
  } else {
    toast.info(t('components.materialCreateWizard.toastRentalPricesUnchanged'))
  }
}

function buildWizardCostReceiptHint(): string {
  if (isAddBatchMode.value && selectedExistingMaterial.value) {
    return t('components.materialCreateWizard.receiptLot', {
      name: selectedExistingMaterial.value.name || t('common.material'),
    })
  }
  if (isPhysicalComboCreation() && computeComboNewPurchaseTotalChf() > 0 && formData.name?.trim()) {
    return t('components.materialCreateWizard.receiptComboPurchase', { name: formData.name.trim() })
  }
  if (formData.name?.trim()) {
    return t('components.materialCreateWizard.receiptPurchase', { name: formData.name.trim() })
  }
  return ''
}

function batchIdFromAddBatchResult(r: MaterialBatch | AddBatchMultiResponse): string | undefined {
  if ('created_batches' in r && Array.isArray(r.created_batches) && r.created_batches.length > 0) {
    return r.created_batches[0].id
  }
  if ('id' in r && typeof (r as MaterialBatch).id === 'string') {
    return (r as MaterialBatch).id
  }
  return undefined
}

async function handleSubmit() {
  if (!canSubmit.value || isSubmitting.value) return

  if (formData.stock_location_mode === 'loose') {
    const ok = await confirmDialog({
      title: t('components.materialCreateWizard.looseStorageConfirmTitle'),
      message: t('components.materialCreateWizard.looseStorageConfirmMessage'),
      confirmText: t('components.materialCreateWizard.looseStorageConfirmOk'),
      variant: 'warning',
    })
    if (!ok) return
  }
  
  isSubmitting.value = true
  
  try {
    if (!isAddBatchMode.value && formData.name.trim()) {
      await performNameDuplicateCheck()
    }
    if (!canSubmit.value) {
      return
    }

    let successMessage = t('components.materialCreateWizard.successMaterialCreated')
    let followUpBatchId: string | undefined
    let followUpMaterialItemId: string | undefined
    const combinedLocation = buildCombinedLocation()
    const includeExpiryDate = formData.tracking_type !== 'serialized' && (formData.is_food || showExpiryDateForNonFood.value)
    const expiryDatePayload = includeExpiryDate ? (formData.expiry_date || undefined) : undefined
    if (!formData.split_allocations && formData.stock_location_mode === 'slot') {
      await ensureStorageSelection()
    }

    // Im Batch-Modus: Batch zu bestehendem Material hinzufügen
    if (isAddBatchMode.value && selectedExistingMaterial.value) {
      const batchPayload: AddBatchRequest = {
        qty: formData.initial_qty,
        acquired_on: formData.purchase_date || undefined,
        expiry_date: expiryDatePayload || null,
        unit_price: resolveWizardStoredUnitPrice(),
        supplier_id: formData.supplier_id || null,
        ean: formData.ean || null,
        barcode_tag: formData.barcode_tag || null,
        ...(formData.split_allocations && hasRelevantAllocationRows.value && allocationSumValid.value && !hasInvalidAllocationRows.value
          ? {
              allocations: initialAllocations.value
                .filter((r) => r.qty > 0 && (r.mode === 'slot' ? r.rack_id : r.container_batch_id))
                .map((r) =>
                  r.mode === 'kiste'
                    ? { container_batch_id: r.container_batch_id, qty: r.qty }
                    : { rack_id: r.rack_id, slot_id: r.slot_id || undefined, qty: r.qty }
                )
            }
          : {
              rack_id: formData.rack_id || null,
              slot_id: formData.slot_id || null
            }),
      }
      
      const batchRes = await addBatch(selectedExistingMaterial.value.id, batchPayload)
      followUpBatchId = batchIdFromAddBatchResult(batchRes)
      followUpMaterialItemId = selectedExistingMaterial.value.id

      // Material mit neuen Daten emittieren
      emit('created', { 
        ...selectedExistingMaterial.value, 
        total_stock: selectedExistingMaterial.value.total_stock + formData.initial_qty 
      })
      successMessage = t('components.materialCreateWizard.successBatchAdded')
    } else if (isFromContainerBatchContents.value && containerContentsBatchId.value) {
      // Combo aus Kisten-Inhalt erstellen
      if (formData.material_type === 'physical_combo' && formData.stock_location_mode === 'slot') {
        await ensureStorageSelection()
      }
      const comboFromKistePayload: CreateComboFromContainerBatchRequest = {
        container_batch_id: containerContentsBatchId.value,
        name: formData.name.trim(),
        department_id: props.departmentId,
        material_type: formData.material_type === 'virtual_combo' ? 'virtual_combo' : 'physical_combo',
        category_id: formData.category_id || null,
        storage_address_id: formData.storage_address_id || null,
        purchase_date: formData.purchase_date || undefined,
      }
      if (formData.material_type === 'physical_combo') {
        if (formData.stock_location_mode === 'kiste' && formData.stock_container_batch_id) {
          comboFromKistePayload.initial_container_batch_id = formData.stock_container_batch_id
        } else {
          comboFromKistePayload.initial_rack_id = formData.rack_id || undefined
          comboFromKistePayload.initial_slot_id = formData.slot_id || undefined
        }
      }
      const result = await createComboFromContainerBatch(comboFromKistePayload)
      emit('created', result)
      successMessage = t('components.materialCreateWizard.successComboFromBox')
    } else if (
      (creationMode.value === 'physical_combo' || creationMode.value === 'virtual_combo') &&
      !isFromTemplate.value &&
      !isFromContainerBatchContents.value
    ) {
      if (creationMode.value === 'physical_combo' && formData.stock_location_mode === 'slot') {
        await ensureStorageSelection()
      }
      const components: CreateComboManualComponentInput[] = selectedComboMaterials.value
        .filter(isComboArticleDone)
        .map((mat) => {
        const comp: CreateComboManualComponentInput = {
          qty: mat.qty,
          component_source: mat.component_source,
        }
        if (formData.material_type === 'physical_combo') {
          comp.mode = mat.mode
        } else if (mat.component_source === 'self_provided') {
          comp.mode = 'new'
          comp.name = mat.name.trim()
          comp.tracking_type = 'bulk'
        }
        if (mat.component_source === 'stock' && mat.id) {
          comp.material_id = mat.id
        } else if (mat.component_source === 'stock' && !mat.id) {
          comp.name = mat.name.trim()
          comp.tracking_type = mat.tracking
        }
        if (formData.material_type === 'physical_combo' && mat.mode === 'new') {
            if (mat.tracking === 'serialized') {
              comp.serial_number = mat.serial_number.trim()
            }
            if (mat.unit_price) comp.unit_price = mat.unit_price
        } else if (formData.material_type === 'physical_combo' && mat.tracking === 'serialized' && mat.batch_id) {
            comp.batch_id = mat.batch_id
        }
        if (formData.material_type === 'physical_combo' && mat.is_linked_container) {
          comp.is_linked_container = true
        }
        return comp
      })
      const comboPayload: CreateComboManualRequest = {
        department_id: props.departmentId,
        name: formData.name.trim(),
        material_type: formData.material_type === 'virtual_combo' ? 'virtual_combo' : 'physical_combo',
        category_id: formData.category_id || null,
        storage_address_id: formData.storage_address_id || null,
        purchase_date: formData.purchase_date || undefined,
        supplier_id: formData.supplier_id || undefined,
        components,
      }
      if (creationMode.value === 'physical_combo') {
        if (
          !hasComboLinkedContainerFromComponent.value &&
          formData.stock_location_mode === 'kiste' &&
          formData.stock_container_batch_id
        ) {
          comboPayload.initial_container_batch_id = formData.stock_container_batch_id
        } else {
          comboPayload.initial_rack_id = formData.rack_id || undefined
          comboPayload.initial_slot_id = formData.slot_id || undefined
        }
      }
      const result = await createComboManual(comboPayload)
      followUpBatchId = resolveComboFollowUpBatchId(
        result,
        selectedComboMaterials.value.filter(isComboArticleDone),
      )
      followUpMaterialItemId = result.id
      emit('created', result)
      successMessage =
        creationMode.value === 'physical_combo'
          ? t('components.materialCreateWizard.successPhysComboFromTemplate')
          : t('components.materialCreateWizard.successVirtComboFromTemplate')
    } else if (isFromTemplate.value && selectedTemplate.value) {
      // Template-Modus: Material aus Vorlage erstellen
      const mode = creationMode.value as 'individual' | 'physical_combo' | 'virtual_combo'

      // Komponenten-Daten zusammenstellen (bei virtual_combo minimal)
      const components: CreateMaterialComponentInput[] = mode === 'virtual_combo' 
        ? componentInputs.value.map(ci => ({
            component_type: ci.component_type,
            mode: 'new' as const,
            qty: ci.required_qty,
          }))
        : componentInputs.value
            .filter(includeTemplateComponentInPayload)
            .map((ci) => {
            const comp: CreateMaterialComponentInput = {
              component_type: ci.component_type,
              mode: ci.component_source === 'self_provided' ? 'new' : ci.mode,
              assignment_mode: ci.assignment_mode,
            }
            if (ci.component_source === 'self_provided') {
              comp.qty = Math.max(1, ci.qty || ci.required_qty || 1)
            } else if (ci.mode === 'new') {
              if (ci.tracking === 'serialized') {
                comp.serial_number = ci.serial_number
              } else {
                normalizeBulkQty(ci)
                comp.qty = ci.qty
              }
              if (ci.unit_price) {
                comp.unit_price = ci.unit_price
              }
            } else {
              if (ci.material_id) comp.material_id = ci.material_id
              if (ci.batch_id) comp.batch_id = ci.batch_id
              if (ci.tracking === 'bulk') comp.qty = ci.qty
            }
            return comp
          })

      const templatePayload: any = {
        department_id: props.departmentId,
        creation_mode: mode,
        storage_address_id: formData.storage_address_id || undefined,
        category_id: formData.category_id || undefined,
        purchase_date: formData.purchase_date || undefined,
        supplier_id: formData.supplier_id || undefined,
        manufacturer: formData.manufacturer || undefined,
        components
      }

      // Name nur bei Kombo-Modi
      if (mode !== 'individual') {
        templatePayload.name = formData.name.trim()
      }

      // Zelt-Details nur bei Kombo-Modi
      if (mode !== 'individual') {
        templatePayload.tent_type = tentForm.tent_type || undefined
        templatePayload.tent_capacity = tentForm.tent_capacity || undefined
      }

      if (mode === 'physical_combo') {
        if (formData.stock_location_mode === 'slot') {
          await ensureStorageSelection()
        }
        if (formData.stock_location_mode === 'kiste' && formData.stock_container_batch_id) {
          templatePayload.initial_container_batch_id = formData.stock_container_batch_id
        } else {
          templatePayload.initial_rack_id = formData.rack_id || undefined
          templatePayload.initial_slot_id = formData.slot_id || undefined
        }
      }

      const result = await createMaterialFromTemplate(selectedTemplate.value.id, templatePayload)
      
      // Bei individual: emittieren wir ein Fake-Material-Objekt
      if (mode === 'individual' && result.articles) {
        emit('created', {
          id: result.articles[0]?.id,
          name: t('components.materialCreateWizard.emitCreatedArticleName'),
        } as any)
        successMessage = t('components.materialCreateWizard.successArticleFromTemplate')
      } else if (result.material) {
        if (mode === 'physical_combo') {
          followUpBatchId = resolveComboFollowUpBatchId(result.material as unknown as Material)
          followUpMaterialItemId = result.material.id
        }
        emit('created', result.material as any)
        successMessage =
          mode === 'physical_combo'
            ? t('components.materialCreateWizard.successPhysComboFromTemplate')
            : t('components.materialCreateWizard.successVirtComboFromTemplate')
      }
    } else {
      // Normaler Modus: Neues Material erstellen
      const resolvedPack = resolveWizardPackFields()
      // Meter + VE: Stammlänge an Meter/VE anbinden (Charge speichert eigene Länge beim Wareneingang)
      if (stock_unit.value === 'm' && resolvedPack.pack_size && resolvedPack.pack_size >= 2) {
        formData.size_length = String(Math.round(resolvedPack.pack_size * 100))
      }
      const payload: CreateMaterialRequest = {
        department_id: props.departmentId,
        name: applyMaterialUnitSuffixToName(
          formData.name.trim(),
          resolvedPack.pack_unit,
          resolvedPack.pack_size,
          stock_unit.value === 'm' ? parseSizeLengthCm(formData.size_length) : null,
        ),
        category_id: formData.category_id || null,
        storage_address_id: formData.storage_address_id || null,
        location: combinedLocation,
        manufacturer: formData.manufacturer || null,
        material_type: formData.material_type || 'physical',
        tracking_type: formData.tracking_type || null,
        is_container: formData.is_container,
        is_consumable: formData.is_consumable,
        is_food: formData.is_food,
        is_js_material: formData.is_js_material,
        external_source: formData.is_js_material ? (formData.external_source || 'js_ch') : null,
        sale_price:
          formData.sale_price != null && Number.isFinite(Number(formData.sale_price))
            ? String(formData.sale_price)
            : null,
        external_sale_price_chf:
          formData.external_sale_price_chf != null && formData.external_sale_price_chf > 0
            ? String(formData.external_sale_price_chf)
            : null,
        reference_purchase_unit_chf:
          formData.reference_purchase_unit_chf != null &&
          Number.isFinite(Number(formData.reference_purchase_unit_chf))
            ? String(formData.reference_purchase_unit_chf)
            : null,
        min_stock: formData.min_stock,
        pack_size: resolvedPack.pack_size,
        pack_unit: resolvedPack.pack_unit,
        packaging_unit: resolvedPack.packaging_unit,
        pack_sale_price_chf:
          formData.pack_sale_price_chf != null && formData.pack_sale_price_chf > 0
            ? String(formData.pack_sale_price_chf)
            : null,
        pack_weight: normalizeMaterialMetricInput(formData.pack_weight, 'kg'),
        pack_size_length: normalizeMaterialMetricInput(formData.pack_size_length, 'cm'),
        pack_size_width: normalizeMaterialMetricInput(formData.pack_size_width, 'cm'),
        pack_size_height: normalizeMaterialMetricInput(formData.pack_size_height, 'cm'),
        initial_acquired_on: formData.purchase_date,
        initial_expiry_date: expiryDatePayload,
        initial_unit_price: resolveWizardStoredUnitPrice() ?? undefined,
        initial_supplier_id: formData.supplier_id || undefined,
        ...(formData.split_allocations && hasRelevantAllocationRows.value && allocationSumValid.value && !hasInvalidAllocationRows.value
          ? {
              initial_allocations: initialAllocations.value
                .filter((r) => r.qty > 0 && (r.mode === 'slot' ? r.rack_id : r.container_batch_id))
                .map((r) =>
                  r.mode === 'kiste'
                    ? { container_batch_id: r.container_batch_id, qty: r.qty }
                    : { rack_id: r.rack_id, slot_id: r.slot_id || undefined, qty: r.qty }
                )
            }
          : formData.stock_location_mode === 'kiste' && formData.stock_container_batch_id
            ? { initial_container_batch_id: formData.stock_container_batch_id }
            : formData.stock_location_mode === 'loose'
              ? {}
              : {
                  initial_rack_id: formData.rack_id || undefined,
                  initial_slot_id: formData.slot_id || undefined
                }),
        // Details (wie in MaterialDetailView)
        description: formData.description || null,
        model: formData.model || null,
        initial_barcode_tag: formData.tracking_type === 'serialized' ? null : formData.barcode_tag || null,
        initial_ean: formData.tracking_type === 'serialized' ? null : formData.ean || null,
        weight: normalizeMaterialMetricInput(formData.weight, 'kg'),
        color: formData.color || null,
        size_length: normalizeMaterialMetricInput(formData.size_length, 'cm'),
        size_width: normalizeMaterialMetricInput(formData.size_width, 'cm'),
        size_height: normalizeMaterialMetricInput(formData.size_height, 'cm'),
        warranty_until: formData.warranty_until || null,
        // Vermietung
        rental_price_day: formData.rental_price_day || null,
        rental_price_week: formData.rental_price_week || null,
        rental_price_month: formData.rental_price_month || null,
        rental_deposit: formData.rental_deposit || null,
        rental_lead_days: formData.rental_lead_days,
        rental_max_days: formData.rental_max_days,
        rental_external_allowed: formData.rental_external_allowed,
        is_rentable: formData.is_rentable,
        rental_scope: formData.rental_external_allowed ? (formData.rental_scope || null) : null,
        rental_requires_approval: formData.rental_requires_approval,
        rental_notes: formData.rental_notes || null,
        rental_calc_params: formData.rental_calc_params,
      }
      
      // Bei serialisierten Artikeln: Seriennummern mitsenden
      if (formData.tracking_type === 'serialized' && serialNumbers.value.length > 0) {
        payload.serial_numbers = serialNumbers.value
          .filter(s => s.serial_number.trim())
          .map(s => ({
            serial_number: s.serial_number.trim(),
            label: s.label?.trim() || '',
            notes: s.notes || '',
            barcode_tag: s.barcode_tag?.trim() || null,
            ean: s.ean?.trim() || null,
            is_container: !!s.is_container,
            ...(serialLocationSameForAll.value
              ? {}
              : (s.location_mode === 'kiste'
                ? { container_batch_id: s.container_batch_id || undefined }
                : { rack_id: s.rack_id || undefined, slot_id: s.slot_id || undefined }))
          }))
      } else {
        // Bei Massenartikeln: normale Menge
        payload.initial_qty = formData.initial_qty > 0 ? formData.initial_qty : undefined
      }
      
      const material = await createMaterial(payload)
      followUpMaterialItemId = material.id
      followUpBatchId =
        material.batches?.find((b) => b.is_initial)?.id ?? material.batches?.[0]?.id

      if (formData.is_food && foodExtraLots.value.length > 0) {
        const loc = locationPayloadForExtraFoodLot()
        const unitPrice = resolveWizardStoredUnitPrice()
        for (const lot of foodExtraLots.value) {
          if (lot.qty < 1 || !lot.expiry_date) continue
          await addBatch(material.id, {
            qty: lot.qty,
            acquired_on: lot.purchase_date || formData.purchase_date || undefined,
            expiry_date: lot.expiry_date,
            unit_price: unitPrice,
            supplier_id: formData.supplier_id || null,
            ean: formData.ean || null,
            barcode_tag: formData.barcode_tag || null,
            ...loc,
          })
        }
        // Bestand in Emit-Payload grob aktualisieren (Detail lädt sonst neu)
        const extraQty = foodExtraLots.value.reduce(
          (sum, lot) => sum + (lot.qty >= 1 && lot.expiry_date ? lot.qty : 0),
          0,
        )
        emit('created', {
          ...material,
          total_stock: (material.total_stock || 0) + extraQty,
        })
      } else {
        emit('created', material)
      }
      successMessage = t('components.materialCreateWizard.successMaterialCreated')
    }

    toast.success(successMessage)

    // Esswaren: bereits über Aktivität/Camp/Event bezahlt — keine Buchhaltungs-Kostenbuchung
    if (
      !formData.is_food &&
      (await enqueuePendingCostBookingAfterPurchase({
        departmentId: props.departmentId,
        totalChf: computeWizardPurchaseTotalChf(),
        purchaseDateIso: formData.purchase_date || undefined,
        receiptHint: buildWizardCostReceiptHint() || undefined,
        materialBatchId: followUpBatchId ?? null,
        materialItemId: followUpMaterialItemId ?? null,
        receiptFile: purchaseReceiptFile.value,
      }))
    ) {
      toast.info(t('components.batchModal.costBookingInfo'))
      headerNotificationsStore.requestRefresh()
    }

    if (createAnother.value) {
      resetFormForNewMaterial()
      nextTick(() => {
        scrollCreationModeIntoView()
      })
    } else {
      handleClose()
    }
  } catch (err: any) {
    console.error(t('components.materialCreateWizard.logCreateMaterialError'), err)
    toast.error(
      err?.response?.data?.error ||
        err?.message ||
        (isAddBatchMode.value
          ? t('components.materialCreateWizard.errorAddBatch')
          : t('components.materialCreateWizard.errorCreateMaterial'))
    )
  } finally {
    isSubmitting.value = false
  }
}

watch(() => props.modelValue, (val) => {
  if (!val) return
  void initializeOnOpen()
})

function scrollCreationModeIntoView(): void {
  const formEl = wizardFormRef.value
  if (!formEl) return

  const stepEl = formEl.querySelector('.step-section[data-step="creation_mode"]') as HTMLElement | null
  if (stepEl) {
    formEl.scrollTop = Math.max(stepEl.offsetTop - 8, 0)
    return
  }
  formEl.scrollTop = 0
}

async function applyPrefillFromSourceMaterial(materialId: string): Promise<void> {
  const material = await getMaterial(materialId)
  const draft = buildMaterialWizardPrefillFromMaterial(material, {
    skip: ['name', 'category'],
    forceTrackingType: props.prefillForceTrackingType,
    sourceNote: props.prefillSourceNote || undefined,
  })

  creationMode.value = draft.creationMode
  formData.material_type = draft.material_type
  formData.tracking_type = draft.tracking_type
  formData.storage_address_id = draft.storage_address_id
  formData.description = draft.description
  formData.manufacturer = draft.manufacturer
  formData.model = draft.model
  formData.color = draft.color
  formData.weight = draft.weight
  formData.size_length = draft.size_length
  formData.size_width = draft.size_width
  formData.size_height = draft.size_height
  formData.reference_purchase_unit_chf = draft.reference_purchase_unit_chf
  formData.sale_price = draft.sale_price
  formData.min_stock = draft.min_stock
  formData.pack_size = draft.pack_size
  formData.pack_unit = draft.pack_unit
  formData.pack_sale_price_chf = draft.pack_sale_price_chf
  formData.pack_weight = draft.pack_weight
  formData.pack_size_length = draft.pack_size_length
  formData.pack_size_width = draft.pack_size_width
  formData.pack_size_height = draft.pack_size_height
  formData.is_consumable = draft.is_consumable
  formData.is_food = draft.is_food
  formData.rental_price_day = draft.rental_price_day
  formData.rental_price_week = draft.rental_price_week
  formData.rental_price_month = draft.rental_price_month
  formData.rental_deposit = draft.rental_deposit
  formData.rental_lead_days = draft.rental_lead_days
  formData.rental_max_days = draft.rental_max_days
  formData.rental_external_allowed = draft.rental_external_allowed
  formData.rental_scope = draft.rental_scope
  formData.rental_requires_approval = draft.rental_requires_approval
  formData.rental_notes = draft.rental_notes
  formData.unit_price = draft.unit_price
  formData.notes = draft.notes
  stock_unit.value = draft.stock_unit
  qtyEntryMode.value = 'base'
  formData.initial_qty = 0

  if (draft.manufacturer) {
    manufacturerSearch.value = draft.manufacturer
  }

  expandAllVisibleSteps.value = false
  accordionUserControlled.value = true
  activeStep.value = 'general'

  await nextTick()
  articleNameInputRef.value?.focus()
}

async function initializeOnOpen(): Promise<void> {
  const runId = ++openInitRunId
  resetFormForNewMaterial()
  await nextTick()
  if (runId !== openInitRunId) return
  scrollCreationModeIntoView()

  await loadData()
  if (runId !== openInitRunId) return

  const prefillId = props.prefillFromMaterialId?.trim()
  if (prefillId) {
    try {
      await applyPrefillFromSourceMaterial(prefillId)
    } catch (err) {
      console.error('Failed to prefill material wizard:', err)
      toast.error(t('components.materialCreateWizard.errorCreateMaterial'))
    }
  }

  await nextTick()
  if (runId !== openInitRunId) return
  scrollCreationModeIntoView()
}

// Accordion: nach Formular-Reset alle Schritte offen; nach Erstellmodus-Wahl oder Klick auf eine Kopfzeile nur noch ein Schritt.
watch(activeStep, (step) => {
  if (step === 'combo_articles') {
    ensureInitialComboArticleRow()
  }
})

watch(visibleStepIds, (steps) => {
  if (steps.length === 0) {
    activeStep.value = ''
    return
  }

  if (templateLoadInProgress.value) {
    return
  }

  const lastVisibleStep = steps[steps.length - 1]

  if (expandAllVisibleSteps.value) {
    if (!activeStep.value || !steps.includes(activeStep.value as StepId) || activeStep.value !== lastVisibleStep) {
      activeStep.value = lastVisibleStep
    }
    return
  }

  if (activeStep.value && !steps.includes(activeStep.value as StepId)) {
    activeStep.value = lastVisibleStep
    return
  }

  if (
    activeStep.value === 'general' &&
    steps.includes('general') &&
    (isNameInputFocused.value || showNameSuggestions.value)
  ) {
    return
  }

  // Material-Tour: Wizard-Schritt bleibt am Tour-Schritt (kein Auto-Sprung)
  if (wizardAccordionStepForMaterialTour()) {
    return
  }

  // Kombi ohne Vorlage: erst Kategorie — nicht erzwingen bei „Combo aus Kiste“ (Name/Label zuerst im Allgemeinen-Block)
  if (
    !expandAllVisibleSteps.value &&
    activeStep.value === 'general' &&
    steps.includes('category') &&
    (formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo') &&
    formData.name.trim() &&
    !nameExists.value &&
    !formData.category_id &&
    !isFromContainerBatchContents.value &&
    !isFromTemplate.value
  ) {
    activeStep.value = 'category'
    return
  }

  // Kombi ohne Vorlage/Kiste: Namen + Kategorie erfüllt → manuelle Artikelwahl
  if (
    !expandAllVisibleSteps.value &&
    activeStep.value === 'general' &&
    steps.includes('combo_articles') &&
    (formData.material_type === 'physical_combo' || formData.material_type === 'virtual_combo') &&
    formData.name.trim() &&
    !nameExists.value &&
    formData.category_id &&
    !isFromContainerBatchContents.value &&
    !isFromTemplate.value
  ) {
    activeStep.value = 'combo_articles'
    return
  }

  // Combo aus Kiste: erst Kategorie, dann Komponenten (nicht direkt vom Namen weg)
  if (
    !expandAllVisibleSteps.value &&
    activeStep.value === 'general' &&
    steps.includes('template_components') &&
    isFromContainerBatchContents.value &&
    formData.name.trim() &&
    !nameExists.value &&
    formData.category_id
  ) {
    activeStep.value = 'template_components'
    return
  }

  // Nach Kisten-Laden: „Komponenten“ nicht auf letzten Schritt zurücksetzen
  if (
    !expandAllVisibleSteps.value &&
    activeStep.value === 'template_components' &&
    steps.includes('template_components') &&
    (isFromTemplate.value || isFromContainerBatchContents.value)
  ) {
    return
  }

  if (accordionUserControlled.value) {
    if (activeStep.value && steps.includes(activeStep.value as StepId)) {
      return
    }
    activeStep.value = lastVisibleStep
    return
  }

  if (!activeStep.value || !steps.includes(activeStep.value as StepId) || activeStep.value !== lastVisibleStep) {
    activeStep.value = lastVisibleStep
  }
}, { immediate: true })

watch(
  () => formData.category_id,
  (id) => {
    if (!id || expandAllVisibleSteps.value || !accordionUserControlled.value || templateLoadInProgress.value) return
    if (wizardAccordionStepForMaterialTour()) return
    // Esswaren: Tracking entfällt → direkt Bestand
    if (formData.is_food && visibleStepIds.value.includes('stock')) {
      activeStep.value = 'stock'
      return
    }
    const next = nextVisibleStepAfter('category')
    if (next) activeStep.value = next
  }
)

watch(() => formData.is_food, (isFood) => {
  if (!isFood) {
    clearFoodExtraLots()
    return
  }
  showExpiryDateForNonFood.value = false
  // Esswaren bleiben ein physischer Einzelartikel.
  formData.material_type = 'physical'
  selectedComboMaterials.value = []
  comboMaterialSearch.value = ''
  // Esswaren: immer Massenartikel — kein Serialisiert, Tracking-Schritt entfällt.
  formData.tracking_type = 'bulk'
  // Esswaren: immer Stück, nie Meter / keine VE / kein Behälter.
  stock_unit.value = 'Stk'
  qtyEntryMode.value = 'base'
  packUnitEnabled.value = false
  formData.is_container = false
  formData.model = ''
  formData.color = ''
  formData.warranty_until = ''
  applyFoodCategoryIfAvailable()
  void nextTick(() => {
    if (wizardAccordionStepForMaterialTour()) return
    if (expandAllVisibleSteps.value) return
    // Nach Esswaren: direkt Bestand (Tracking-Wahl überspringen)
    if (formData.category_id && visibleStepIds.value.includes('stock')) {
      expandAllVisibleSteps.value = false
      accordionUserControlled.value = true
      activeStep.value = 'stock'
    }
  })
}, { immediate: true })

/** Verbrauchsmaterial bleibt physischer Einzelartikel; Bestandsverfolgung wählt der User nach Kategorie. */
watch(
  () => formData.is_consumable,
  (isConsumable) => {
    if (!isConsumable) return
    formData.material_type = 'physical'
  },
)

watch(showExpiryDateForNonFood, (enabled) => {
  if (!enabled && !formData.is_food) {
    formData.expiry_date = ''
  }
})

watch(allCategories, () => {
  applyFoodCategoryIfAvailable()
})

watch(showCategoryDropdown, async (open) => {
  if (open) {
    await nextTick()
    updateCategoryDropdownPosition(0)
    bindCategoryDropdownPositionListeners()
  } else {
    unbindCategoryDropdownPositionListeners()
  }
})

watch(showDialog, (open) => {
  if (typeof document !== 'undefined') {
    document.body.classList.toggle('material-wizard-open', open)
  }
  if (!open) {
    showCategoryDropdown.value = false
    unbindCategoryDropdownPositionListeners()
  }
}, { immediate: true })

watch(() => formData.split_allocations, (isSplit) => {
  if (isSplit) {
    normalizeAllocationRowsToTotal()
    return
  }
  initialAllocations.value = []
})

watch(() => formData.initial_qty, () => {
  normalizeAllocationRowsToTotal()
})

/** Bei Aufteilung: fehlende Menge automatisch als neue Zeile (Restmenge), sobald Summe < Soll. */
watch(
  [allocationSum, () => formData.split_allocations, () => formData.initial_qty],
  () => {
    if (!formData.split_allocations || formData.initial_qty <= 0) return
    if (allocationSum.value >= formData.initial_qty) return
    addAllocationRow()
  },
  { flush: 'post' },
)

watch(stockInputReady, (ready) => {
  if (!ready) {
    formData.split_allocations = false
  }
})

/** Gleiche ID als Zahl vs. String darf keinen Reset auslösen (sonst: API liefert Fächer, UI bleibt leer). */
function normalizeStorageAddressIdForWatch(value: unknown): string {
  return String(value ?? '').trim()
}

watch(
  () => normalizeStorageAddressIdForWatch(formData.storage_address_id),
  async (newAddr, oldAddr) => {
    if (newAddr === oldAddr) return
    formData.rack_id = ''
    formData.slot_id = ''
    formData.location_rack = ''
    formData.location_slot = ''
    storageSlots.value = []
    slotsByRackId.value = {}
    await prefetchVisibleRackPreviews(storageRacks.value)
    saveWizardStockPrefs()
  }
)

watch(
  () => [
    formData.stock_location_mode,
    autoGenPrefix.value,
    autoGenStart.value,
    autoGenPad.value,
    autoGenCount.value,
    serialAutoGenerateEnabled.value,
    serialLocationSameForAll.value,
    serialAllAsContainers.value,
  ] as const,
  () => {
    saveWizardStockPrefs()
  }
)

onMounted(() => {
  if (props.modelValue) {
    void initializeOnOpen()
  }
})

onUnmounted(() => {
  if (typeof document !== 'undefined') {
    document.body.classList.remove('material-wizard-open')
  }
  unbindCategoryDropdownPositionListeners()
})
</script>
