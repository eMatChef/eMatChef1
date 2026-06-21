<template>
  <div class="material-detail-view">
    <!-- Header mit Schließen/Speichern -->
    <header class="detail-header">
      <div class="header-left">
        <EButton
          variant="secondary"
          size="small"
          class="material-detail-back-btn"
          @click="handleClose"
        >
          <v-icon icon="mdi-arrow-left" start size="20" />
          {{ t('components.materialDetail.backToList') }}
        </EButton>
        <div class="header-title">
          <span v-if="!isUserMaterialsBrowseOnly && material.barcode_tag" class="material-code">{{ material.barcode_tag }}</span>
          <h1>{{ materialDisplayName }}</h1>
          <span v-if="isComboDraft" class="combo-draft-badge">
            {{ t('components.materialDetail.comboDraftBadge') }}
          </span>
          <span v-if="material.open_loss_reports > 0" class="loss-report-badge">
            {{ t('components.materialDetail.lossReportBadge', { detail: openLossLabel }) }}
          </span>
        </div>
      </div>
      <div class="header-actions">
        <template v-if="canManageMaterials">
          <div
            v-if="showLinkedContainerQrHeader"
            class="header-linked-qr"
            :title="t('components.materialDetail.qrTitleLinkedCombo')"
          >
            <PublicQrTag
              :url="linkedContainerQrBatch?.public_url"
              :code="linkedContainerQrBatch?.public_code"
              :size="40"
              :clickable="true"
              :image-label="linkedContainerQrBatch?.material_name || material.name"
              :image-entity-id="linkedContainerQrBatch?.id"
              @activate="openLinkedContainerQrModal"
            />
            <EButton
              variant="secondary"
              size="small"
              class="header-qr-serial-shortcut"
              :title="t('components.materialDetail.titleOpenBatchQr')"
              @click="openLinkedContainerQrModal"
            >
              {{ t('components.materialDetail.qrCodes') }}
            </EButton>
          </div>
          <EButton
            v-else-if="showEnsureLinkedContainerQrButton"
            variant="secondary"
            size="small"
            :disabled="isGeneratingPublicCode"
            :title="t('components.materialDetail.qrTitleLinkedCombo')"
            @click="ensureLinkedContainerPublicCode"
          >
            {{ isGeneratingPublicCode ? t('components.materialDetail.qrGenLoading') : t('components.materialDetail.qrEnsureLinkedSack') }}
          </EButton>
          <EButton
            v-else-if="showGenerateQrButton"
            variant="secondary"
            size="small"
            :disabled="isGeneratingPublicCode"
            :title="qrGenerateButtonTitle"
            @click="generateMaterialPublicCode"
          >
            {{ qrGenerateButtonLabel }}
          </EButton>
          <EButton
            v-else-if="showHeaderQrShortcut"
            variant="secondary"
            size="small"
            class="header-qr-serial-shortcut"
            :title="t('components.materialDetail.titleOpenBatchQr')"
            @click="openStockTabWithQrPanel"
          >
            {{ t('components.materialDetail.qrCodes') }}
          </EButton>
          <EButton variant="secondary" size="small" @click="handleClose">{{ t('components.materialDetail.close') }}</EButton>
          <EButton
            v-if="hasManualUnsavedChanges || isSaving"
            variant="primary"
            size="small"
            @click="save"
            :disabled="!hasManualUnsavedChanges || isSaving"
            :loading="isSaving"
          >
            {{ isSaving ? t('common.saving') : t('common.save') }}
          </EButton>
        </template>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="isLoading" class="loading-container">
      <div class="spinner"></div>
      <p>{{ t('components.materialDetail.loading') }}</p>
    </div>

    <!-- Content: Tabs fix, nur content-layout scrollt -->
    <div v-else class="detail-body">
      <v-tabs
        v-if="!isUserMaterialsBrowseOnly"
        v-model="activeTab"
        class="material-detail-tabs"
        align-tabs="start"
        color="primary"
        show-arrows
      >
        <v-tab v-for="tab in tabs" :key="tab.id" :value="tab.id">
          {{ tab.label }}
        </v-tab>
      </v-tabs>

      <div class="detail-content">
      <div class="content-layout">
        <!-- Main Content (Left) -->
        <main class="content-main">
          <v-tabs-window v-model="activeTab" class="material-detail-tabs-window">
          <!-- Tab: Daten (User: nur Anzeige, Felder mit Wert) -->
          <v-tabs-window-item value="data" class="material-detail-window-item">
          <section v-if="isUserMaterialsBrowseOnly" class="tab-content">
            <div
              v-for="section in userReadOnlySections"
              :key="section.title"
              class="section-card"
            >
              <h2 class="section-title">{{ section.title }}</h2>
              <dl class="user-readonly-fields">
                <div
                  v-for="field in section.fields"
                  :key="field.label"
                  class="user-readonly-row"
                >
                  <dt>{{ field.label }}</dt>
                  <dd>{{ field.value }}</dd>
                </div>
              </dl>
            </div>
            <div
              v-if="material.material_type === 'physical_combo' && material.linked_container_batch"
              class="section-card"
            >
              <h2 class="section-title">{{ t('components.materialDetail.refKisteLabel') }}</h2>
              <router-link
                class="linked-kiste-link"
                :to="`/${departmentId}/materials/${material.linked_container_batch.material_id}`"
              >
                {{ material.linked_container_batch.display_label }}
              </router-link>
            </div>
            <p v-if="userReadOnlySections.length === 0" class="user-readonly-empty">
              {{ t('components.materialDetail.userReadOnlyNoFields') }}
            </p>
          </section>

          <!-- Tab: Daten (Bearbeitung) -->
          <section v-else class="tab-content">
            <div
              v-if="isComboMaterialView && !isVirtualComboView && canManageMaterials && hasAnyQrForPrint"
              class="stock-qr-collapsible section-card"
            >
              <button
                type="button"
                class="stock-qr-toggle"
                :aria-expanded="stockQrPanelExpanded"
                aria-controls="combo-qr-panel"
                @click="stockQrPanelExpanded = !stockQrPanelExpanded"
              >
                <span class="stock-qr-toggle-label">{{ t('components.materialDetail.modalQrActionTitle') }}</span>
                <span class="stock-qr-toggle-chevron" :class="{ 'is-open': stockQrPanelExpanded }" aria-hidden="true">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9" />
                  </svg>
                </span>
              </button>
              <div
                v-show="stockQrPanelExpanded"
                id="combo-qr-panel"
                class="stock-qr-panel"
                role="region"
              >
                <p v-if="isPhysicalComboFromLinkedContainer" class="qr-panel-hint">
                  {{ t('components.materialDetail.qrComboLinkedHint') }}
                  <router-link
                    v-if="material.linked_container_batch?.material_id"
                    class="linked-kiste-link"
                    :to="`/${departmentId}/materials/${material.linked_container_batch.material_id}`"
                  >
                    {{ material.linked_container_batch.display_label }}
                  </router-link>
                </p>
                <p class="qr-panel-hint">{{ t('components.materialDetail.qrPrintAllHint') }}</p>
                <div class="modal-actions stock-qr-panel-actions">
                  <button type="button" class="btn-outline btn-sm" @click="handleQrAddAllToPrintCart">
                    {{ t('components.materialDetail.btnAddToPrintCart') }}
                  </button>
                  <button type="button" class="btn-primary btn-sm" @click="handleQrPrintAllFromPanel">
                    {{ t('common.print') }}
                  </button>
                </div>
                <ul v-if="printableQrRows.length" class="stock-qr-batch-list">
                  <li v-for="batch in printableQrRows" :key="batch.id" class="stock-qr-batch-row">
                    <PublicQrTag
                      :url="batch.public_url"
                      :code="batch.public_code"
                      :size="48"
                      :clickable="true"
                      :image-label="material.name"
                      :image-entity-id="batch.id"
                      @activate="openQrActionModalForBatch(batch)"
                    />
                    <span class="stock-qr-batch-label">{{ batchPrintLine(batch) }}</span>
                    <button type="button" class="btn-outline btn-sm" @click="openQrActionModalForBatch(batch)">
                      {{ t('common.actions') }}
                    </button>
                  </li>
                </ul>
              </div>
            </div>

            <div class="section-card">
              <h2 class="section-title">{{ t('common.material') }}</h2>
              
              <div class="form-grid">
                <AutoSaveField
                  v-model="formData.name"
                  :baseline="savedFormBaselines.name"
                  :label="t('components.materialDetail.labelNameDb')"
                  :span-class="isVirtualComboView ? 'form-group span-full' : 'form-group span-2'"
                  :save="(v) => saveMaterialField('name', v)"
                />
                
                <template v-if="!isVirtualComboView">
                  <AutoSaveField
                    v-model="formData.barcode_tag"
                    :baseline="savedFormBaselines.barcode_tag"
                    :label="`${t('components.materialDetail.labelCode')} (${t('components.materialDetail.optionalShort')})`"
                    :placeholder="t('components.materialDetail.codePlaceholder')"
                    :save="(v) => saveMaterialField('barcode_tag', v)"
                  />
                  
                  <AutoSaveField
                    v-model="formData.category_id"
                    :baseline="savedFormBaselines.category_id"
                    :label="t('components.materialDetail.labelCategory')"
                    :save="(v) => saveMaterialField('category_id', v)"
                  >
                    <template #default="{ onFocus, onBlur, onChange }">
                      <CategoryAutocompleteInput
                        v-model="formData.category_id"
                        :categories="categories"
                        :department-id="departmentId"
                        @focus="onFocus"
                        @blur="onBlur"
                        @change="onChange"
                        @reload-categories="handleCategoryReloadFromPicker"
                      />
                    </template>
                  </AutoSaveField>
                  
                  <AutoSaveField
                    v-model="formData.manufacturer"
                    :baseline="savedFormBaselines.manufacturer"
                    :label="t('common.manufacturer')"
                    :save="(v) => saveMaterialField('manufacturer', v)"
                  />
                  
                  <AutoSaveField
                    v-model="formData.model"
                    :baseline="savedFormBaselines.model"
                    :label="t('components.materialDetail.labelModel')"
                    :save="(v) => saveMaterialField('model', v)"
                  />
                </template>
              </div>
            </div>

            <div class="section-card">
              <div class="section-header-row">
                <h2 class="section-title">{{ t('components.materialDetail.sectionProperties') }}</h2>
                <span class="property-badge">{{ propertyBadgeText }}</span>
              </div>
              
              <div v-if="isVirtualComboView" class="properties-grid">
                <div class="property-item">
                  <span class="property-label">{{ t('components.materialDetail.propPhysicalVirtual') }}</span>
                  <span class="property-value">{{ propertyBadgeText }}</span>
                </div>
                <div v-if="isComboDraft" class="property-item">
                  <span class="property-label">{{ t('components.materialDetail.thCompositionState') }}</span>
                  <span class="property-value">{{ t('components.materialDetail.comboDraftBadge') }}</span>
                </div>
                <div v-else class="property-item">
                  <span class="property-label">{{ t('components.materialDetail.thCompositionState') }}</span>
                  <span class="property-value">{{ t('components.materialDetail.comboReadyBadge') }}</span>
                </div>
              </div>
              <template v-else>
                <div class="properties-grid">
                  <div class="property-item">
                    <span class="property-label">{{ t('components.materialDetail.propPhysicalVirtual') }}</span>
                    <span class="property-value">{{ t('components.materialDetail.propPhysicalMaterials') }}</span>
                  </div>
                  <div class="property-item">
                    <span class="property-label">{{ t('components.materialDetail.propRentalSale') }}</span>
                    <span class="property-value">{{ t('components.materialDetail.propRental') }}</span>
                  </div>
                  <div
                    v-if="material.tracking_type === 'bulk'"
                    class="property-item property-item--checkbox"
                  >
                    <AutoSaveField
                      v-model="formData.is_container"
                      :baseline="savedFormBaselines.is_container"
                      :label="t('components.materialDetail.containerCheckbox')"
                      type="checkbox"
                      :checkbox-label="t('components.materialDetail.containerCheckbox')"
                      span-class="property-item property-item--checkbox autosave-checkbox-field"
                      :save="(v) => saveMaterialField('is_container', v)"
                    />
                    <p class="form-hint text-muted mt-1">
                      {{ t('components.materialDetail.containerCheckboxHint') }}
                    </p>
                  </div>
                  <div class="property-item">
                    <span class="property-label">{{ t('components.materialDetail.labelSource') }}</span>
                    <span class="property-value">{{ formData.is_js_material ? t('components.materialDetail.sourceJs') : t('components.materialDetail.sourceInternal') }}</span>
                  </div>
                </div>

                <div v-if="canManageJsMaterial" class="checkbox-group mt-4">
                  <AutoSaveField
                    v-model="formData.is_js_material"
                    :baseline="savedFormBaselines.is_js_material"
                    :label="t('components.materialDetail.jsMaterialGlobal')"
                    type="checkbox"
                    :checkbox-label="t('components.materialDetail.jsMaterialGlobal')"
                    span-class="autosave-checkbox-field"
                    :save="(v) => saveMaterialField('is_js_material', v)"
                  />
                  <AutoSaveField
                    v-if="formData.is_js_material"
                    v-model="formData.external_source"
                    :baseline="savedFormBaselines.external_source"
                    :label="t('components.materialDetail.labelExternalSource')"
                    :placeholder="t('components.materialDetail.externalSourcePlaceholder')"
                    span-class="form-group mt-2"
                    :save="(v) => saveMaterialField('external_source', v)"
                  />
                </div>
                <div v-else-if="formData.is_js_material" class="form-group mt-4">
                  <label>{{ t('components.materialDetail.labelExternalSource') }}</label>
                  <input :value="formData.external_source || 'js_ch'" type="text" class="form-input" disabled />
                </div>
                
                <div
                  v-if="material.material_type === 'physical_combo' && material.linked_container_batch"
                  class="linked-kiste-banner mt-4"
                >
                  <span class="linked-kiste-label">{{ t('components.materialDetail.refKisteLabel') }}</span>
                  <p class="linked-kiste-desc">
                    {{ t('components.materialDetail.refKisteDesc') }}
                  </p>
                  <router-link
                    class="linked-kiste-link"
                    :to="`/${departmentId}/materials/${material.linked_container_batch.material_id}`"
                  >
                    {{ material.linked_container_batch.display_label }}
                  </router-link>
                </div>
              </template>
            </div>

            <div v-if="!isVirtualComboView" class="section-card">
              <h2 class="section-title">{{ t('components.materialDetail.sectionDetails') }}</h2>
              <p v-if="isMeterStockMaterial" class="section-hint">
                {{ t('components.materialDetail.lengthRequiredForMeterHint') }}
              </p>
              
              <div class="form-grid">
                <AutoSaveField
                  v-model="formData.ean"
                  :baseline="savedFormBaselines.ean"
                  :label="t('components.materialDetail.labelEan')"
                  :save="(v) => saveMaterialField('ean', v)"
                />
                <AutoSaveField
                  v-model="formData.weight"
                  :baseline="savedFormBaselines.weight"
                  :label="t('components.materialDetail.labelWeightKg')"
                  type="number"
                  :save="(v) => saveMaterialField('weight', v)"
                />
                <AutoSaveField
                  v-model="formData.color"
                  :baseline="savedFormBaselines.color"
                  :label="t('components.materialDetail.labelColor')"
                  :save="(v) => saveMaterialField('color', v)"
                />
                <AutoSaveField
                  v-model="formData.size_length"
                  :baseline="savedFormBaselines.size_length"
                  :label="sizeLengthFieldLabel"
                  type="number"
                  :save="(v) => saveMaterialField('size_length', v)"
                />
                <AutoSaveField
                  v-model="formData.size_width"
                  :baseline="savedFormBaselines.size_width"
                  :label="t('components.materialDetail.labelWidthCm')"
                  type="number"
                  :save="(v) => saveMaterialField('size_width', v)"
                />
                <AutoSaveField
                  v-model="formData.size_height"
                  :baseline="savedFormBaselines.size_height"
                  :label="t('components.materialDetail.labelHeightCm')"
                  type="number"
                  :save="(v) => saveMaterialField('size_height', v)"
                />
                <AutoSaveField
                  v-model="formData.warranty_until"
                  :baseline="savedFormBaselines.warranty_until"
                  :label="t('components.materialDetail.labelWarranty')"
                  type="date"
                  :save="(v) => saveMaterialField('warranty_until', v)"
                />
              </div>

              <AutoSaveField
                v-model="formData.description"
                :baseline="savedFormBaselines.description"
                :label="t('components.materialDetail.labelDescription')"
                type="textarea"
                :rows="3"
                :placeholder="t('components.materialDetail.phDescriptionOptional')"
                span-class="form-group span-full mt-4"
                :save="(v) => saveMaterialField('description', v)"
              />
            </div>

            <div v-if="showDetailStockUnitSection && !isVirtualComboView" class="section-card section-card--stock-unit">
              <h2 class="section-title">{{ t('components.materialDetail.sectionStockUnit') }}</h2>
              <MaterialDetailStockUnitField
                :show-label="false"
                :material-id="props.materialId"
                :material-name="formData.name"
                :pack-unit="formData.pack_unit"
                :pack-size="formData.pack_size"
                :size-length-cm="formData.size_length"
                :packaging-active="detailPackagingActive"
                :tracking-type="material.tracking_type"
                :disabled="!canManageMaterials"
                @saved="onDetailStockUnitSaved"
              />
            </div>

            <!-- Verpackungseinheit (bei Verbrauch/Essen siehe Kosten) -->
            <div
              v-if="!isVirtualComboView && !material.is_consumable && !material.is_food && !isMeterStockMaterial"
              class="section-card"
            >
              <h2 class="section-title">{{ t('components.materialDetail.sectionPackaging') }}</h2>
              <p class="section-hint">{{ t('components.materialDetail.packagingHint') }}</p>
              
              <div class="form-grid">
                <AutoSaveField
                  v-model="formData.pack_size"
                  :baseline="savedFormBaselines.pack_size"
                  :label="t('components.materialDetail.labelPiecesPerUnit')"
                  type="number"
                  :min="2"
                  :placeholder="t('components.materialDetail.packSizePlaceholder')"
                  :save="(v) => saveMaterialField('pack_size', v)"
                />
                <AutoSaveField
                  v-model="formData.pack_unit"
                  :baseline="savedFormBaselines.pack_unit"
                  :label="t('components.materialDetail.labelDesignation')"
                  type="select"
                  :options="packUnitSelectOptions"
                  :save="(v) => saveMaterialField('pack_unit', v)"
                />
                <AutoSaveField
                  v-if="formData.pack_unit && !PACK_UNIT_VALUES.includes(formData.pack_unit)"
                  v-model="formData.pack_unit"
                  :baseline="savedFormBaselines.pack_unit"
                  :label="t('components.materialDetail.packUnitCustomPlaceholder')"
                  :placeholder="t('components.materialDetail.packUnitCustomPlaceholder')"
                  span-class="form-group span-full"
                  :save="(v) => saveMaterialField('pack_unit', v)"
                />
              </div>
              <p v-if="formData.pack_size && formData.pack_unit" class="pack-preview">
                {{ t('components.materialDetail.packPreview', { stock: material.total_stock || 80, packs: Math.floor((material.total_stock || 80) / formData.pack_size), unit: formData.pack_unit, per: formData.pack_size }) }}
                <span v-if="(material.total_stock || 80) % formData.pack_size !== 0">{{ t('components.materialDetail.packPreviewRemain', { rem: (material.total_stock || 80) % formData.pack_size }) }}</span>
              </p>
            </div>

            <div v-if="!isVirtualComboView && !material.is_consumable && !material.is_food" class="section-card">
              <h2 class="section-title">{{ t('components.materialDetail.sectionPackDimensions') }}</h2>
              <p class="section-hint">{{ t('components.materialDetail.packDimensionsHint') }}</p>
              <div class="form-grid">
                <AutoSaveField
                  v-model="formData.pack_weight"
                  :baseline="savedFormBaselines.pack_weight"
                  :label="t('components.materialDetail.labelPackWeightKg')"
                  type="number"
                  :save="(v) => saveMaterialField('pack_weight', v)"
                />
                <AutoSaveField
                  v-model="formData.pack_size_length"
                  :baseline="savedFormBaselines.pack_size_length"
                  :label="t('components.materialDetail.labelPackLengthCm')"
                  type="number"
                  :save="(v) => saveMaterialField('pack_size_length', v)"
                />
                <AutoSaveField
                  v-model="formData.pack_size_width"
                  :baseline="savedFormBaselines.pack_size_width"
                  :label="t('components.materialDetail.labelPackWidthCm')"
                  type="number"
                  :save="(v) => saveMaterialField('pack_size_width', v)"
                />
                <AutoSaveField
                  v-model="formData.pack_size_height"
                  :baseline="savedFormBaselines.pack_size_height"
                  :label="t('components.materialDetail.labelPackHeightCm')"
                  type="number"
                  :save="(v) => saveMaterialField('pack_size_height', v)"
                />
              </div>
            </div>

            <div v-if="!isVirtualComboView && (material.is_consumable || material.is_food)" class="section-card">
              <h2 class="section-title">{{ t('components.materialDetail.sectionCosts') }}</h2>
              <p class="section-hint">{{ t('components.materialDetail.costsHint') }}</p>
              <div v-if="material.is_consumable" class="costs-hint-banner">
                <span>{{ t('components.materialDetail.costsConsumableBanner') }}</span>
              </div>
              <div v-if="material.is_food" class="costs-hint-banner costs-hint-banner--food">
                <span>{{ t('components.materialDetail.costsFoodBanner') }}</span>
              </div>
              <div class="form-grid">
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelSalePrice') }} <span class="field-required-star">*</span></label>
                  <div class="input-with-prefix">
                    <span class="prefix">{{ t('components.materialDetail.currencyFr') }}</span>
                    <input
                      v-model.number="formData.sale_price"
                      type="number"
                      step="0.05"
                      min="0"
                      class="form-input"
                      :placeholder="t('components.materialDetail.phPriceZero')"
                    />
                  </div>
                  <p class="form-hint">{{ t('components.materialDetail.hintSalePerPiece') }}</p>
                  <div
                    v-if="packSaleDerivedUnitPrice != null"
                    class="pack-sale-to-unit"
                  >
                    <p class="pack-sale-to-unit__text" v-text="packSaleCalcLine" />
                    <button
                      type="button"
                      class="btn-outline btn-sm pack-sale-to-unit__btn"
                      @click="applyPackSaleToUnitSalePrice"
                    >
                      {{ t('components.materialDetail.applyPackToUnit') }}
                    </button>
                  </div>
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelRefPurchase') }} <span class="field-required-star">*</span></label>
                  <div class="input-with-prefix">
                    <span class="prefix">{{ t('components.materialDetail.currencyFr') }}</span>
                    <input
                      v-model.number="formData.reference_purchase_unit_chf"
                      type="number"
                      step="0.05"
                      min="0"
                      class="form-input"
                      :placeholder="t('components.materialDetail.phPriceZero')"
                    />
                  </div>
                  <p class="form-hint">{{ t('components.materialDetail.hintRefPurchase') }}</p>
                </div>
                <div v-if="material.is_consumable" class="form-group">
                  <label>{{ t('components.materialDetail.labelMinStock') }} <span class="optional">{{ t('components.materialDetail.optionalParen') }}</span></label>
                  <input v-model.number="formData.min_stock" type="number" min="0" class="form-input" :placeholder="t('components.materialDetail.packSizePlaceholder')" />
                </div>
              </div>

              <h3 class="subsection-heading-kosten">{{ t('components.materialDetail.sectionPackaging') }}</h3>
              <p class="section-hint">{{ t('components.materialDetail.costsPackagingOptionalHint') }}</p>
              <div class="form-grid">
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
                  <div class="pack-unit-select">
                    <select v-model="formData.pack_unit" class="form-select">
                      <option value="">{{ t('components.materialDetail.packUnitNone') }}</option>
                      <option :value="PACK_UNIT_BUNDLE">{{ t('components.materialDetail.packUnitBundle') }}</option>
                      <option :value="PACK_UNIT_KISTE">{{ t('components.materialDetail.packUnitKiste') }}</option>
                      <option :value="PACK_UNIT_KARTON">{{ t('components.materialDetail.packUnitKarton') }}</option>
                      <option :value="PACK_UNIT_SACK">{{ t('components.materialDetail.packUnitSack') }}</option>
                      <option :value="PACK_UNIT_ROLLE">{{ t('components.materialDetail.packUnitRolle') }}</option>
                      <option :value="PACK_UNIT_PALETTE">{{ t('components.materialDetail.packUnitPalette') }}</option>
                      <option :value="PACK_UNIT_SET">{{ t('components.materialDetail.packUnitSet') }}</option>
                      <option :value="PACK_UNIT_PAKET">{{ t('components.materialDetail.packUnitPaket') }}</option>
                    </select>
                    <input
                      v-if="formData.pack_unit && !PACK_UNIT_VALUES.includes(formData.pack_unit)"
                      v-model="formData.pack_unit"
                      type="text"
                      class="form-input mt-1"
                      :placeholder="t('components.materialDetail.packUnitCustomPlaceholder')"
                    />
                  </div>
                </div>
                <div class="form-group">
                  <label>{{ t('components.materialDetail.labelPackSalePerUnit') }} <span class="optional">{{ t('components.materialDetail.optionalParen') }}</span></label>
                  <div class="input-with-prefix">
                    <span class="prefix">{{ t('components.materialDetail.currencyFr') }}</span>
                    <input
                      v-model.number="formData.pack_sale_price_chf"
                      type="number"
                      step="0.05"
                      min="0"
                      class="form-input"
                      :placeholder="t('components.materialDetail.phPriceZero')"
                    />
                  </div>
                  <p
                    v-if="formData.pack_size && formData.pack_size >= 2 && formData.pack_sale_price_chf && formData.pack_sale_price_chf > 0"
                    class="form-hint"
                  >
                    {{ t('components.materialDetail.hintPackEquivPerPiece', { price: (formData.pack_sale_price_chf / formData.pack_size).toFixed(2) }) }}
                  </p>
                </div>
              </div>
              <p v-if="formData.pack_size && formData.pack_unit" class="pack-preview">
                {{ t('components.materialDetail.packPreview', { stock: material.total_stock || 0, packs: Math.floor((material.total_stock || 0) / formData.pack_size), unit: formData.pack_unit, per: formData.pack_size }) }}
                <span v-if="(material.total_stock || 0) % formData.pack_size !== 0">{{ t('components.materialDetail.packPreviewRemain', { rem: (material.total_stock || 0) % formData.pack_size }) }}</span>
              </p>

              <h3 class="subsection-heading-kosten">{{ t('components.materialDetail.sectionPackDimensions') }}</h3>
              <p class="section-hint">{{ t('components.materialDetail.packDimensionsHint') }}</p>
              <div class="form-grid">
                <AutoSaveField
                  v-model="formData.pack_weight"
                  :baseline="savedFormBaselines.pack_weight"
                  :label="t('components.materialDetail.labelPackWeightKg')"
                  type="number"
                  :save="(v) => saveMaterialField('pack_weight', v)"
                />
                <AutoSaveField
                  v-model="formData.pack_size_length"
                  :baseline="savedFormBaselines.pack_size_length"
                  :label="t('components.materialDetail.labelPackLengthCm')"
                  type="number"
                  :save="(v) => saveMaterialField('pack_size_length', v)"
                />
                <AutoSaveField
                  v-model="formData.pack_size_width"
                  :baseline="savedFormBaselines.pack_size_width"
                  :label="t('components.materialDetail.labelPackWidthCm')"
                  type="number"
                  :save="(v) => saveMaterialField('pack_size_width', v)"
                />
                <AutoSaveField
                  v-model="formData.pack_size_height"
                  :baseline="savedFormBaselines.pack_size_height"
                  :label="t('components.materialDetail.labelPackHeightCm')"
                  type="number"
                  :save="(v) => saveMaterialField('pack_size_height', v)"
                />
              </div>
            </div>
          </section>
          </v-tabs-window-item>

          <!-- Tab: Bestand -->
          <v-tabs-window-item value="stock" class="material-detail-window-item">
          <section class="tab-content">
            <div
              v-if="canManageMaterials && hasAnyQrForPrint"
              class="stock-qr-collapsible section-card"
            >
              <button
                type="button"
                class="stock-qr-toggle"
                :aria-expanded="stockQrPanelExpanded"
                aria-controls="stock-qr-panel"
                @click="stockQrPanelExpanded = !stockQrPanelExpanded"
              >
                <span class="stock-qr-toggle-label">{{ t('components.materialDetail.modalQrActionTitle') }}</span>
                <span class="stock-qr-toggle-chevron" :class="{ 'is-open': stockQrPanelExpanded }" aria-hidden="true">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9" />
                  </svg>
                </span>
              </button>
              <div
                v-show="stockQrPanelExpanded"
                id="stock-qr-panel"
                class="stock-qr-panel"
                role="region"
              >
                <p class="qr-panel-hint">{{ t('components.materialDetail.qrPrintAllHint') }}</p>
                <div class="modal-actions stock-qr-panel-actions">
                  <button type="button" class="btn-outline btn-sm" @click="handleQrAddAllToPrintCart">
                    {{ t('components.materialDetail.btnAddToPrintCart') }}
                  </button>
                  <button type="button" class="btn-primary btn-sm" @click="handleQrPrintAllFromPanel">
                    {{ t('common.print') }}
                  </button>
                </div>
                <ul v-if="printableQrRows.length" class="stock-qr-batch-list">
                  <li v-for="batch in printableQrRows" :key="batch.id" class="stock-qr-batch-row">
                    <PublicQrTag
                      :url="batch.public_url"
                      :code="batch.public_code"
                      :size="48"
                      :clickable="true"
                      :image-label="material.name"
                      :image-entity-id="batch.id"
                      @activate="openQrActionModalForBatch(batch)"
                    />
                    <span class="stock-qr-batch-label">{{ batchPrintLine(batch) }}</span>
                    <button type="button" class="btn-outline btn-sm" @click="openQrActionModalForBatch(batch)">
                      {{ t('common.actions') }}
                    </button>
                  </li>
                </ul>
              </div>
            </div>

            <div class="section-card">
              <div class="section-header-row">
                <h2 class="section-title">{{ t('components.materialDetail.sectionStock') }}</h2>
                <div class="section-actions">
                  <button
                    v-if="material.tracking_type !== 'serialized'"
                    class="btn-stock-action btn-stock-action-move"
                    :disabled="activeBatches.length === 0"
                    @click="openMoveQuantityFromHeader"
                  >
                    {{ t('components.materialDetail.btnMoveQuantity') }}
                  </button>
                  <button class="btn-stock-action btn-stock-action-add" @click="openAddBatchModal">
                    <v-icon icon="mdi-plus" size="16" />
                    {{ t('components.materialDetail.btnAddBatch') }}
                  </button>
                  <button v-if="splitSourceBatches.length > 0" class="btn-outline-small" @click="openSplitModal">
                    {{ t('components.materialDetail.btnSplitBulkSerial') }}
                  </button>
                </div>
              </div>
              
              <div class="stock-summary">
                <div class="stock-stat">
                  <span class="stock-number">{{ formatMaterialStockQtyPrimary(material.total_stock) }}</span>
                  <span class="stock-label">{{ t('components.materialDetail.stockLabelTotal') }}</span>
                  <span v-if="material.pack_size && material.pack_unit && !isMeterStockMaterial" class="stock-pack-info">
                    {{ Math.floor(material.total_stock / material.pack_size) }} {{ material.pack_unit }}
                    <template v-if="material.total_stock % material.pack_size !== 0">{{ t('components.materialDetail.stockPackRemain', { n: material.total_stock % material.pack_size }) }}</template>
                  </span>
                </div>
                <div class="stock-stat warehouse">
                  <span class="stock-number">{{ formatMaterialStockQtyPrimary(material.in_warehouse ?? availableStock) }}</span>
                  <span class="stock-label">{{ t('components.materialDetail.stockLabelInWarehouse') }}</span>
                </div>
                <div class="stock-stat issued" v-if="material.issued_out > 0">
                  <span class="stock-number">{{ material.issued_out }}</span>
                  <span class="stock-label">{{ t('components.materialDetail.stockLabelOut') }}</span>
                </div>
                <div class="stock-stat reserved-stat" v-if="material.reserved > 0">
                  <span class="stock-number">{{ material.reserved }}</span>
                  <span class="stock-label">{{ t('components.materialDetail.stockLabelReserved') }}</span>
                </div>
                <div class="stock-stat repair-stat" v-if="material.repair_stock > 0">
                  <span class="stock-number">{{ material.repair_stock }}</span>
                  <span class="stock-label">{{ t('components.materialDetail.stockLabelRepair') }}</span>
                </div>
                <div class="stock-stat" v-if="(material.defect_stock || defectStock) > 0">
                  <span class="stock-number defect">{{ material.defect_stock || defectStock }}</span>
                  <span class="stock-label">{{ t('components.materialDetail.stockLabelDefect') }}</span>
                </div>
                <div class="stock-stat available">
                  <span class="stock-number">{{ formatMaterialStockQtyPrimary(material.available ?? availableStock) }}</span>
                  <span class="stock-label">{{ t('components.materialDetail.stockLabelAvailable') }}</span>
                </div>
                <div v-if="(material.combo_allocated || 0) > 0" class="stock-stat combo-alloc-stat">
                  <span class="stock-number">{{ material.combo_allocated }}</span>
                  <span class="stock-label">{{ t('components.materialDetail.stockLabelInCombos') }}</span>
                </div>
              </div>
              <div
                v-if="material.combo_allocations && material.combo_allocations.length > 0"
                class="combo-allocation-breakdown"
              >
                <p class="combo-allocation-breakdown-title">{{ t('components.materialDetail.stockComboBreakdownTitle') }}</p>
                <p class="combo-allocation-breakdown-explain">{{ t('components.materialDetail.stockComboBreakdownExplain') }}</p>
                <ul class="combo-allocation-breakdown-list">
                  <li v-for="row in material.combo_allocations" :key="row.parent_material_id">
                    <router-link
                      class="combo-allocation-link"
                      :to="`/${departmentId}/materials/${row.parent_material_id}`"
                    >
                      {{ row.parent_name }}
                    </router-link>
                    <span class="combo-allocation-qty">{{ t('settings.storage.overviewLineQty', { qty: row.qty }) }}</span>
                  </li>
                </ul>
                <p v-if="(material.free_stock ?? 0) > 0" class="combo-allocation-free">
                  {{ t('components.materialDetail.stockComboBreakdownFree', { n: material.free_stock }) }}
                </p>
              </div>

              <p
                v-if="(material.issued_out ?? 0) > 0 && activeBatches.length > 0"
                class="stock-location-issued-hint"
              >
                {{
                  t('components.materialDetail.stockLocationIssuedHint', {
                    booked: material.total_stock ?? 0,
                    issued: material.issued_out ?? 0,
                    inWarehouse: material.in_warehouse ?? 0,
                  })
                }}
              </p>

              <MaterialStockBatchesDataTable
                v-if="activeBatches.length > 0"
                :items="sortedActiveBatches"
                :can-manage-materials="canManageMaterials"
                :show-move-qty="material.tracking_type !== 'serialized'"
                :material-name="material.name"
                :pack-unit="material.pack_unit"
                :pack-size="material.pack_size"
                :size-length-cm="material.size_length"
                :status-labels="statusLabels"
                :sort-key="stockSortKey"
                :sort-dir="stockSortDir"
                :em-dash="t('components.materialDetail.emDash')"
                :currency-fr="t('components.materialDetail.currencyFr')"
                :format-date="formatDate"
                :location-entries="buildBatchLocationEntries"
                @toggle-sort="toggleStockSort"
                @edit="openEditBatchModal"
                @move="openMoveQuantityModal"
                @qr-activate="openQrActionModalForBatch"
                @open-container="(e) => openContainerMaterial(e.containerMaterialId!, e.containerBatchId, e.containerSearchSeed)"
              />
              
              <div v-else class="empty-batches">
                <p>{{ t('components.materialDetail.emptyNoBatches') }}</p>
                <button class="btn-outline" @click="openAddBatchModal">
                  {{ t('components.materialDetail.emptyAddFirstBatch') }}
                </button>
              </div>
            </div>
          </section>
          </v-tabs-window-item>

          <!-- Tab: Gelagert in -->
          <v-tabs-window-item v-if="!isVirtualComboView" value="stored-in" class="material-detail-window-item">
          <section class="tab-content">
            <div class="section-card">
              <div class="section-header-row">
                <h2 class="section-title">{{ t('components.materialDetail.sectionStoredInTitle') }}</h2>
              </div>
              <StorageTreeView
                :key="storageTreeRefreshKey"
                :department-id="props.departmentId"
                :material-id="props.materialId"
                :readonly="true"
                :allow-move-actions="true"
                :allow-open-actions="material.material_type === 'physical_combo'"
                :embedded-detail-material-id="props.materialId"
              />
            </div>
          </section>
          </v-tabs-window-item>

          <!-- Tab: Zusammensetzung (physische / virtuelle Kombination) -->
          <v-tabs-window-item v-if="isComboMaterialView" value="composition" class="material-detail-window-item">
          <section class="tab-content">
            <div class="section-card composition-tab-card">
              <div class="section-header-row composition-tab-head">
                <div>
                  <h2 class="section-title">
                    {{ t('components.materialDetail.tabComposition') }}
                    <span v-if="isConfigurator" class="composition-configurator-badge" :title="t('components.comboOptions.configuratorBadgeHint')">
                      <span aria-hidden="true">{{ COMBO_BADGE.configurable }}</span>
                      {{ t('components.comboOptions.configuratorBadge') }}
                    </span>
                  </h2>
                  <p class="composition-tab-intro">
                    {{
                      material.material_type === 'physical_combo'
                        ? physicalComboRefContainerName
                          ? t('components.materialDetail.compositionIntroPhysical', {
                              refContainer: physicalComboRefContainerName,
                            })
                          : t('components.materialDetail.compositionIntroPhysicalGeneric')
                        : material.material_type === 'virtual_combo'
                          ? t('components.materialDetail.compositionIntroVirtual')
                          : t('components.materialDetail.compositionIntro')
                    }}
                  </p>
                </div>
                <div class="composition-tab-actions">
                  <button
                    v-if="isComboDraft"
                    type="button"
                    class="btn-primary btn-sm composition-finalize-btn"
                    :disabled="finalizingCombo"
                    @click="finalizeComboNow"
                  >
                    {{ finalizingCombo ? t('components.materialDetail.comboFinalizeSubmitting') : t('components.materialDetail.btnFinalizeCombo') }}
                  </button>
                  <button
                    v-if="material.linked_container_batch"
                    type="button"
                    class="btn-outline-small"
                    :title="linkedContainerLabelForRelease"
                    @click="openLinkedContainerStoredInTab"
                  >
                    {{ t('components.materialDetail.btnOpenRefContainer') }}
                  </button>
                  <button
                    v-if="isVirtualComboView"
                    type="button"
                    class="btn-outline-small"
                    :class="{ 'is-active': showComboOptionsEditor }"
                    @click="showComboOptionsEditor = !showComboOptionsEditor"
                  >
                    {{ t('components.comboOptions.btnToggleEditor') }}
                  </button>
                  <button type="button" class="btn-primary btn-sm" @click="openAddCompositionModal">
                    {{ t('components.materialDetail.btnAddFromStock') }}
                  </button>
                  <button type="button" class="btn-outline-small" @click="emitCreateMaterialForComposition">
                    {{ t('components.materialDetail.btnCreateNewMaterial') }}
                  </button>
                  <button type="button" class="btn-outline-small" :disabled="comboComponentsLoading" @click="loadComboComponentsForTab">
                    {{ t('common.refresh') }}
                  </button>
                </div>
              </div>

              <div v-if="comboComponentsLoading" class="loading-container composition-loading">
                <div class="spinner"></div>
                <p>{{ t('components.materialDetail.compositionLoading') }}</p>
              </div>
              <div v-else-if="comboComponentsList.length === 0" class="empty-used-in">
                <p>{{ t('components.materialDetail.compositionEmpty') }}</p>
                <p class="text-muted composition-hint">
                  {{ t('components.materialDetail.compositionEmptyHint') }}
                </p>
              </div>
              <div v-else class="composition-table-wrapper">
              <table class="used-in-table composition-table">
                <thead>
                  <tr>
                    <th>{{ t('components.materialDetail.thComponent') }}</th>
                    <th>{{ t('common.role') }}</th>
                    <th>{{ t('components.materialDetail.thQty') }}</th>
                    <th>{{ t('common.serialNumber') }}</th>
                    <th>{{ t('components.materialDetail.thBatchStatus') }}</th>
                    <th>{{ t('components.materialDetail.thAssignment') }}</th>
                    <th class="composition-state-th" :title="t('components.materialDetail.thCompositionStateHint')">
                      {{ t('components.materialDetail.thCompositionState') }}
                    </th>
                    <th class="composition-actions-th">{{ t('common.actions') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="comp in comboComponentsList" :key="comp.id" class="used-in-row">
                    <td>
                      <button type="button" class="link-btn composition-comp-link" @click="openComponentMaterialDetail(comp.component_material.id)">
                        {{ comp.component_material.name }}
                      </button>
                      <span
                        v-if="isCompositionLinkedContainer(comp)"
                        class="composition-ref-container-badge"
                        :title="t('components.materialDetail.refContainerBadgeHint')"
                      >
                        {{ t('components.materialDetail.refContainerBadge') }}
                      </span>
                      <span v-if="isVirtualComboView && comp.is_optional" class="composition-optional-badge">{{ t('components.materialDetail.optionalShortBadge') }}</span>
                      <span
                        v-if="isVirtualComboView && comp.component_source === 'self_provided'"
                        class="composition-optional-badge composition-selfprovided-badge"
                        :title="t('components.materialDetail.hintComponentSource')"
                      >{{ t('components.materialDetail.selfProvidedShortBadge') }}</span>
                    </td>
                    <td>{{ comp.component_role || t('components.materialDetail.emDash') }}</td>
                    <td>{{ comp.qty }}</td>
                    <td>
                      <span v-if="comp.component_batch?.serial_number" class="serial-code">{{ comp.component_batch.serial_number }}</span>
                      <span v-else class="text-muted">{{ t('components.materialDetail.emDash') }}</span>
                    </td>
                    <td>
                      <span
                        v-if="comp.component_batch"
                        class="status-badge"
                        :class="comp.component_batch.status"
                      >
                        {{ statusLabels[comp.component_batch.status] || comp.component_batch.status }}
                      </span>
                      <span v-else class="text-muted">{{ t('components.materialDetail.emDash') }}</span>
                    </td>
                    <td class="composition-assignment-cell">
                      <span
                        class="assignment-badge"
                        :class="comp.assignment_mode === 'fixed' ? 'fix' : comp.assignment_mode"
                        :title="comboAssignmentLabels[comp.assignment_mode] || comp.assignment_mode"
                      >
                        {{ comboAssignmentLabelsShort[comp.assignment_mode] || comp.assignment_mode }}
                      </span>
                    </td>
                    <td class="composition-status-cell">
                      <span v-if="comp.is_awaiting" class="composition-dot composition-dot--await" :title="t('components.materialDetail.titleCompositionAwait')" />
                      <span v-else-if="comp.is_assigned" class="composition-dot composition-dot--ok" :title="t('components.materialDetail.titleCompositionAssigned')" />
                      <span v-else class="composition-dot composition-dot--linked" :title="t('components.materialDetail.titleCompositionLinked')" />
                    </td>
                    <td class="composition-actions-cell">
                      <div class="composition-row-actions">
                        <TableIconButton
                          icon="mdi-pencil"
                          :title="t('common.edit')"
                          :aria-label="t('components.materialDetail.ariaEditComposition')"
                          @click="openEditCompositionModal(comp)"
                        />
                        <TableIconButton
                          icon="mdi-delete-outline"
                          danger
                          :title="t('components.materialDetail.titleRemoveFromCombo')"
                          :aria-label="t('components.materialDetail.ariaDeleteComposition')"
                          :disabled="deletingCompositionId === comp.id"
                          :loading="deletingCompositionId === comp.id"
                          @click="confirmDeleteComposition(comp)"
                        />
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
              </div>
            </div>

            <!-- Konfigurator: Auswahl-Gruppen & Optionen (Weg B, Paket 6) -->
            <div v-if="isVirtualComboView && showComboOptionsEditor" class="section-card composition-options-card">
              <ComboOptionsEditor
                :material-id="props.materialId"
                :department-id="props.departmentId"
                :options="comboOptionsList"
                :groups="comboOptionGroupsList"
                @reload="reloadComboOptions"
              />
            </div>

            <!-- Verwandtes Zubehör (Empfehlung, kein Stücklisten-Teil) -->
            <div class="section-card composition-accessories-card">
              <div class="section-header-row composition-tab-head">
                <div>
                  <h2 class="section-title">{{ t('components.materialDetail.accessoriesTitle') }}</h2>
                  <p class="composition-tab-intro">{{ t('components.materialDetail.accessoriesIntro') }}</p>
                </div>
                <div class="composition-tab-actions">
                  <button type="button" class="btn-primary btn-sm" @click="openAddAccessoryModal">
                    {{ t('components.materialDetail.btnAddAccessory') }}
                  </button>
                </div>
              </div>

              <div v-if="relatedAccessoriesLoading" class="loading-container composition-loading">
                <div class="spinner"></div>
                <p>{{ t('components.materialDetail.accessoriesLoading') }}</p>
              </div>
              <div v-else-if="relatedAccessoriesList.length === 0" class="empty-used-in">
                <p>{{ t('components.materialDetail.accessoriesEmpty') }}</p>
              </div>
              <div v-else class="composition-table-wrapper">
                <table class="used-in-table composition-table">
                  <thead>
                    <tr>
                      <th>{{ t('components.materialDetail.thAccessory') }}</th>
                      <th>{{ t('components.materialDetail.thTotalStock') }}</th>
                      <th class="composition-actions-th">{{ t('components.materialDetail.thActions') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="acc in relatedAccessoriesList" :key="acc.id" class="used-in-row">
                      <td>
                        <button type="button" class="link-btn composition-comp-link" @click="openComponentMaterialDetail(acc.accessory_material.id)">
                          {{ acc.accessory_material.name }}
                        </button>
                      </td>
                      <td>{{ acc.accessory_material.total_stock }}</td>
                      <td class="composition-actions-cell">
                        <TableIconButton
                          icon="mdi-delete-outline"
                          danger
                          :title="t('components.materialDetail.accessoryRemoveTitle')"
                          :disabled="deletingAccessoryId === acc.id"
                          :loading="deletingAccessoryId === acc.id"
                          @click="confirmDeleteAccessory(acc)"
                        />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
          </v-tabs-window-item>

          <!-- Tab: Inhalt Kiste/Tasche -->
          <v-tabs-window-item v-if="showContainerContentTab" value="container-content" class="material-detail-window-item">
          <section class="tab-content">
            <div class="container-content-layout">
              <div class="section-card container-content-main-card">
                <div class="section-header-row container-content-header-row">
                  <h2 class="section-title">{{ t('components.materialDetail.sectionContainerContentTitle') }}</h2>
                  <br class="container-content-header-break" />
                  <div class="detail-inline-filters">
                    <div v-if="storedInContainerOptions.length > 0" class="detail-inline-field">
                      <label class="detail-inline-label">{{ t('components.materialDetail.labelContainerBag') }}</label>
                      <select v-model="containerContentBatchId" class="detail-inline-select">
                        <option value="">{{ t('components.materialDetail.optPickContainerBag') }}</option>
                        <option v-for="option in storedInContainerOptions" :key="option.id" :value="option.id" :title="option.label">
                          {{ option.label }}
                        </option>
                      </select>
                    </div>
                    <div v-else class="detail-inline-info-box">
                      {{ t('components.materialDetail.noContainersForArticle') }}
                    </div>
                    <button
                      v-if="canAddItemsToSelectedContainer"
                      type="button"
                      class="btn-outline-small container-add-btn"
                      @click="openAddToContainerModal"
                    >
                      {{ t('components.materialDetail.btnAddItemPlus') }}
                    </button>
                  </div>
                </div>

                <div v-if="!containerContentBatchId && storedInContainerOptions.length === 0" class="empty-used-in">
                  <p>{{ t('components.materialDetail.containerEmptyNoInstances') }}</p>
                </div>
                <div v-else-if="!containerContentBatchId" class="empty-used-in">
                  <p>{{ t('components.materialDetail.containerSelectFirst') }}</p>
                </div>
                <div v-else-if="isLoadingContainerContentOverview" class="loading-container" style="padding: 32px;">
                  <div class="spinner"></div>
                  <p>{{ t('components.materialDetail.containerLoadingOverview') }}</p>
                </div>
                <template v-else-if="selectedContainerPhysicalCombo">
                  <div class="container-combo-linked-banner">
                    <p class="container-combo-linked-hint">
                      {{ t('components.materialDetail.containerContentPhysicalComboHint') }}
                    </p>
                    <router-link
                      class="combo-allocation-link container-combo-linked-link"
                      :to="`/${departmentId}/materials/${selectedContainerPhysicalCombo.id}?tab=composition`"
                    >
                      {{ selectedContainerPhysicalCombo.name }}
                      <span class="container-combo-linked-link-suffix">
                        → {{ t('components.materialDetail.containerContentOpenCombo') }}
                      </span>
                    </router-link>
                  </div>
                  <p v-if="containerContentRows.length === 0" class="empty-used-in">
                    {{ t('components.materialDetail.containerEmptyNoContents') }}
                  </p>
                  <table v-else class="used-in-table container-content-table container-content-table--readonly">
                    <thead>
                      <tr>
                        <th>{{ t('components.materialDetail.thArticle') }}</th>
                        <th>{{ t('components.materialDetail.thQty') }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in containerContentRows" :key="row.materialId" class="used-in-row">
                        <td>{{ row.materialName }}</td>
                        <td>{{ t('components.materialDetail.qtyPieces', { qty: row.qty }) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </template>
                <div v-else-if="containerContentRows.length === 0" class="empty-used-in">
                  <p>{{ t('components.materialDetail.containerEmptyNoContents') }}</p>
                </div>
                <table v-else class="used-in-table container-content-table">
                  <thead>
                    <tr>
                      <th>{{ t('components.materialDetail.thArticle') }}</th>
                      <th>{{ t('components.materialDetail.thQty') }}</th>
                      <th class="action-cell">{{ t('components.materialDetail.thAction') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in containerContentRows" :key="row.materialId" class="used-in-row">
                      <td>{{ row.materialName }}</td>
                      <td>{{ t('components.materialDetail.qtyPieces', { qty: row.qty }) }}</td>
                      <td class="action-cell">
                        <button class="btn-outline-small" @click="openMaterialById(row.materialId)">
                          {{ t('components.materialDetail.btnOpenArticle') }}
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>

              </div>

              <aside v-if="!selectedContainerPhysicalCombo" class="container-content-sidebar">
                <div class="section-card">
                  <div class="section-header-row">
                    <h2 class="section-title">{{ t('components.materialDetail.sectionContainerDetailsTitle') }}</h2>
                  </div>
                  <div v-if="!containerContentBatchId" class="empty-used-in">
                    <p>{{ t('components.materialDetail.containerNoSelection') }}</p>
                  </div>
                  <div v-else-if="isLoadingContainerEditor" class="loading-container" style="padding: 24px;">
                    <div class="spinner"></div>
                    <p>{{ t('components.materialDetail.containerLoadingEditor') }}</p>
                  </div>
                  <div v-else-if="!containerEditorBatchId" class="empty-used-in">
                    <p>{{ t('components.materialDetail.containerEditorLoadFailed') }}</p>
                  </div>
                  <div v-else class="form-grid autosave-form-grid">
                    <AutoSaveField
                      v-model="containerEditorForm.serial_number"
                      :baseline="containerEditorBaselines.serial_number"
                      :label="t('common.serialNumber')"
                      span-class="form-group span-full"
                      :save="(v) => saveContainerEditorField('serial_number', v)"
                    />
                    <AutoSaveField
                      v-model="containerEditorForm.label"
                      :baseline="containerEditorBaselines.label"
                      :label="t('components.materialDetail.labelBatchLabel')"
                      span-class="form-group span-full"
                      :save="(v) => saveContainerEditorField('label', v)"
                    />
                    <AutoSaveField
                      v-model="containerEditorForm.status"
                      :baseline="containerEditorBaselines.status"
                      :label="t('common.status')"
                      type="select"
                      span-class="form-group span-full"
                      :options="containerEditorStatusOptions"
                      :save="(v) => saveContainerEditorField('status', v)"
                    />
                    <AutoSaveField
                      v-model="containerEditorForm.notes"
                      :baseline="containerEditorBaselines.notes"
                      :label="t('components.materialDetail.labelNote')"
                      type="textarea"
                      :rows="3"
                      span-class="form-group span-full"
                      :save="(v) => saveContainerEditorField('notes', v)"
                    />
                  </div>
                </div>
              </aside>
            </div>
          </section>
          </v-tabs-window-item>

          <!-- Tab: Seriennummern (nur bei serialisierten Materialien) -->
          <v-tabs-window-item v-if="material.tracking_type === 'serialized'" value="serials" class="material-detail-window-item">
          <section class="tab-content">
            <div class="section-card">
              <div class="section-header-row">
                <h2 class="section-title">{{ t('components.materialDetail.sectionSerialsTitle') }}</h2>
                <button class="btn-outline-small" @click="openAddBatchModal">
                  <v-icon icon="mdi-plus" size="16" />
                  {{ t('components.materialDetail.btnAddBatch') }}
                </button>
              </div>
              
              <div class="serial-summary">
                <span class="serial-count">{{ t('components.materialDetail.serialCountLine', { count: serialBatches.length }) }}</span>
              </div>

              <MaterialSerialBatchesDataTable
                v-if="serialBatches.length > 0"
                :items="sortedSerialBatches"
                :material-name="material.name"
                :status-labels="statusLabels"
                :sort-key="serialSortKey"
                :sort-dir="serialSortDir"
                :em-dash="t('components.materialDetail.emDash')"
                :container-saving="serialIsContainerSaving"
                :format-date="formatDate"
                :location-entries="buildBatchLocationEntries"
                @toggle-sort="toggleSerialSort"
                @edit="openEditBatchModal"
                @qr-activate="openQrActionModalForBatch"
                @container-change="onSerialBatchIsContainerChange"
                @open-container="(e) => openContainerMaterial(e.containerMaterialId!, e.containerBatchId, e.containerSearchSeed)"
              />

              <div v-else class="empty-serials">
                <p>{{ t('components.materialDetail.emptyNoSerials') }}</p>
                <button class="btn-outline" @click="openAddBatchModal">
                  {{ t('components.materialDetail.emptyAddFirstBatch') }}
                </button>
              </div>
            </div>
          </section>
          </v-tabs-window-item>

          <!-- Tab: Werkstatt -->
          <v-tabs-window-item v-if="!isVirtualComboView" value="workshop" class="material-detail-window-item">
          <section class="tab-content">
            <div class="section-card">
              <h2 class="section-title">{{ t('components.materialDetail.sectionWorkshopTitle') }}</h2>
              <p class="form-hint" style="margin-top: 0">
                {{ t('components.materialDetail.workshopIntro') }}
              </p>
              <div v-if="workshopTicketsLoading" class="loading-inline">
                <div class="spinner"></div>
                <span>{{ t('components.materialDetail.workshopLoadingTickets') }}</span>
              </div>
              <div v-else-if="workshopTickets.length === 0" class="empty-serials">
                <p>{{ t('components.materialDetail.workshopEmpty') }}</p>
                <div class="workshop-tab-actions">
                  <router-link
                    class="btn-primary"
                    :to="{ path: `/${departmentId}/workshop`, query: { material_id: materialId } }"
                  >
                    {{ t('components.materialDetail.btnWorkshopFiltered') }}
                  </router-link>
                </div>
                <p class="form-hint">{{ t('components.materialDetail.workshopHintCreateTicket') }}</p>
              </div>
              <div v-else>
                <MaterialWorkshopTicketsDataTable
                  :items="workshopTickets"
                  :department-id="departmentId"
                  :material-id="materialId"
                  :em-dash="t('components.materialDetail.emDash')"
                />
                <div class="workshop-tab-actions mt-3">
                  <router-link
                    class="btn-outline btn-sm"
                    :to="{ path: `/${departmentId}/workshop`, query: { material_id: materialId } }"
                  >
                    {{ t('components.materialDetail.btnAllInWorkshop') }}
                  </router-link>
                </div>
              </div>
            </div>
          </section>
          </v-tabs-window-item>

          <!-- Tab: Vermietung -->
          <v-tabs-window-item v-if="!isVirtualComboView && !material.is_consumable && !material.is_food" value="rental" class="material-detail-window-item">
          <section class="tab-content">
            <div class="section-card">
              <h2 class="section-title">{{ t('components.materialDetail.sectionRentalTitle') }}</h2>

              <div class="rental-accordion-item">
                <button
                  type="button"
                  class="rental-accordion-trigger"
                  :aria-expanded="rentalActivitiesOpen"
                  @click="rentalActivitiesOpen = !rentalActivitiesOpen"
                >
                  <v-icon
                    class="rental-accordion-chevron"
                    :icon="rentalActivitiesOpen ? 'mdi-chevron-down' : 'mdi-chevron-right'"
                    size="small"
                    aria-hidden="true"
                  />
                  <span class="rental-accordion-title">{{ t('components.materialDetail.rentalAccordionActivitiesTitle') }}</span>
                  <span v-if="rentalActivityBookingsTotalQty > 0" class="rental-accordion-badge">
                    {{ t('settings.storage.overviewLineQty', { qty: rentalActivityBookingsTotalQty }) }}
                  </span>
                </button>
                <div v-show="rentalActivitiesOpen" class="rental-accordion-body">
                  <p class="rental-accordion-intro">{{ t('components.materialDetail.rentalAccordionActivitiesIntro') }}</p>
                  <div v-if="rentalActivityBookingsLoading" class="loading-inline combo-rental-loading">
                    <div class="spinner"></div>
                    <span>{{ t('components.materialDetail.rentalActivityBookingsLoading') }}</span>
                  </div>
                  <p v-else-if="rentalActivityBookingsError" class="form-hint text-warning">{{ rentalActivityBookingsError }}</p>
                  <p v-else-if="rentalActivityBookings.length === 0" class="empty-serials combo-rental-empty">
                    {{ t('components.materialDetail.rentalActivityBookingsEmpty') }}
                  </p>
                  <MaterialRentalActivityBookingsDataTable
                    v-else
                    :items="rentalActivityBookings"
                    :department-id="departmentId"
                    :em-dash="t('components.materialDetail.emDash')"
                    :format-period="formatRentalActivityPeriod"
                    :status-label="rentalActivityStatusLabel"
                    :kind-label="rentalBookingKindLabel"
                    :kind-class="rentalBookingKindClass"
                  />
                </div>
              </div>

              <div class="rental-accordion-item">
                <button
                  type="button"
                  class="rental-accordion-trigger"
                  :aria-expanded="rentalPricingOpen"
                  @click="rentalPricingOpen = !rentalPricingOpen"
                >
                  <v-icon
                    class="rental-accordion-chevron"
                    :icon="rentalPricingOpen ? 'mdi-chevron-down' : 'mdi-chevron-right'"
                    size="small"
                    aria-hidden="true"
                  />
                  <span class="rental-accordion-title">{{ t('components.materialDetail.rentalAccordionPricingTitle') }}</span>
                </button>
                <div v-show="rentalPricingOpen" class="rental-accordion-body">

              <div
                v-if="material.material_type === 'physical_combo' || material.material_type === 'virtual_combo'"
                class="combo-rental-basis-section"
              >
                <h3 class="section-subtitle combo-rental-basis-title">{{ t('components.materialDetail.rentalComboBasisTitle') }}</h3>
                <p class="form-hint combo-rental-basis-intro">
                  {{ t('components.materialDetail.rentalComboBasisIntro') }}
                </p>
                <div v-if="comboRentalLoading" class="loading-inline combo-rental-loading">
                  <div class="spinner"></div>
                  <span>{{ t('components.materialDetail.rentalLoadingComponents') }}</span>
                </div>
                <p v-else-if="comboRentalError" class="form-hint text-warning">{{ comboRentalError }}</p>
                <p v-else-if="comboRentalRows.length === 0" class="empty-serials combo-rental-empty">
                  {{ t('components.materialDetail.rentalEmptyBom') }}
                </p>
                <template v-else>
                  <MaterialComboRentalBasisDataTable
                    :items="comboRentalRows"
                    :show-optional-badge="isVirtualComboView"
                    :total-basis-chf="rentalAcquisitionBasisChf"
                    :currency-fr="t('components.materialDetail.currencyFr')"
                    :em-dash="t('components.materialDetail.emDash')"
                    :format-chf="formatChfFiveRappenString"
                  />
                  <p v-if="comboRentalHasGap" class="form-hint combo-rental-gap-hint">
                    {{ t('components.materialDetail.rentalGapHint') }}
                  </p>
                </template>
              </div>

              <RentalPriceAmortizationCalculator
                v-if="showRentalAmortizationCalculator"
                v-model="formData.rental_calc_params"
                :defaults="rentalAmortizationDefaults"
                :historical-basis-chf="rentalAcquisitionBasisChf"
                :piece-count="rentalAcquisitionPieceCount ?? undefined"
                :context="rentalAmortizationContext"
                @apply="onRentalCalculatorApply"
              />
              
              <div class="form-grid">
                <AutoSaveField
                  v-model="formData.rental_price_day"
                  :baseline="savedFormBaselines.rental_price_day"
                  :label="t('components.materialDetail.labelDayPrice')"
                  :save="(v) => saveMaterialField('rental_price_day', v)"
                />
                <AutoSaveField
                  v-model="formData.rental_price_week"
                  :baseline="savedFormBaselines.rental_price_week"
                  :label="t('components.materialDetail.labelWeekPrice')"
                  :save="(v) => saveMaterialField('rental_price_week', v)"
                />
                <AutoSaveField
                  v-model="formData.rental_price_month"
                  :baseline="savedFormBaselines.rental_price_month"
                  :label="t('components.materialDetail.labelMonthPrice')"
                  :save="(v) => saveMaterialField('rental_price_month', v)"
                />
                <AutoSaveField
                  v-model="formData.rental_deposit"
                  :baseline="savedFormBaselines.rental_deposit"
                  :label="t('components.materialDetail.labelDeposit')"
                  :save="(v) => saveMaterialField('rental_deposit', v)"
                />
                <AutoSaveField
                  v-model="formData.rental_lead_days"
                  :baseline="savedFormBaselines.rental_lead_days"
                  :label="t('components.materialDetail.labelLeadDays')"
                  type="number"
                  :save="(v) => saveMaterialField('rental_lead_days', v)"
                />
                <AutoSaveField
                  v-model="formData.rental_max_days"
                  :baseline="savedFormBaselines.rental_max_days"
                  :label="t('components.materialDetail.labelMaxRentalDays')"
                  type="number"
                  :save="(v) => saveMaterialField('rental_max_days', v)"
                />
              </div>

              <div class="checkbox-group mt-4">
                <AutoSaveField
                  v-model="formData.rental_external_allowed"
                  :baseline="savedFormBaselines.rental_external_allowed"
                  :label="t('components.materialDetail.chkExternalRental')"
                  type="checkbox"
                  :checkbox-label="t('components.materialDetail.chkExternalRental')"
                  span-class="autosave-checkbox-field"
                  :save="(v) => saveMaterialField('rental_external_allowed', v)"
                />
                <AutoSaveField
                  v-model="formData.rental_requires_approval"
                  :baseline="savedFormBaselines.rental_requires_approval"
                  :label="t('components.materialDetail.chkApprovalRequired')"
                  type="checkbox"
                  :checkbox-label="t('components.materialDetail.chkApprovalRequired')"
                  span-class="autosave-checkbox-field"
                  :save="(v) => saveMaterialField('rental_requires_approval', v)"
                />
              </div>

              <div v-if="formData.rental_external_allowed" class="span-full mt-2">
                <AutoSaveField
                  v-model="formData.rental_scope"
                  :baseline="savedFormBaselines.rental_scope"
                  :label="t('components.materialDetail.labelExternalRentalScope')"
                  type="select"
                  span-class="form-group span-full"
                  :options="rentalScopeOptions"
                  :save="(v) => saveMaterialField('rental_scope', v)"
                />
                <p class="form-hint">{{ t('components.materialDetail.hintRentalScope') }}</p>
              </div>

              <AutoSaveField
                v-model="formData.rental_notes"
                :baseline="savedFormBaselines.rental_notes"
                :label="t('components.materialDetail.labelRentalNotes')"
                type="textarea"
                :rows="3"
                :placeholder="t('components.materialDetail.phRentalNotes')"
                span-class="form-group span-full mt-4"
                :save="(v) => saveMaterialField('rental_notes', v)"
              />
                </div>
              </div>
            </div>
          </section>
          </v-tabs-window-item>
          <!-- Tab: Archiv -->
          <v-tabs-window-item v-if="!isVirtualComboView" value="archive" class="material-detail-window-item">
          <section class="tab-content">
            <div class="section-card">
              <div class="section-header-row">
                <h2 class="section-title">{{ t('components.materialDetail.sectionArchiveTitle') }}</h2>
                <span class="archive-info-badge">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                  </svg>
                  {{ t('components.materialDetail.archiveInfoHint') }}
                </span>
              </div>

              <MaterialArchiveBatchesDataTable
                v-if="archivedBatches.length > 0"
                :items="archivedBatches"
                :status-labels="statusLabels"
                :em-dash="t('components.materialDetail.emDash')"
                :currency-fr="t('components.materialDetail.currencyFr')"
                :format-date="formatDate"
                :display-qty="getArchivedBatchDisplayQty"
              />

              <div v-else class="empty-archive">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
                  <polyline points="20 12 20 22 4 22 4 12"/>
                  <rect x="2" y="7" width="20" height="5"/>
                  <line x1="12" y1="22" x2="12" y2="12"/>
                  <path d="M12 12H7.5a2.5 2.5 0 0 1 0-5C11 7 12 12 12 12z"/>
                  <path d="M12 12h4.5a2.5 2.5 0 0 0 0-5C13 7 12 12 12 12z"/>
                </svg>
                <p>{{ t('components.materialDetail.emptyNoArchivedBatches') }}</p>
                <span class="empty-archive-hint">{{ t('components.materialDetail.emptyArchiveStatusesHint') }}</span>
              </div>
            </div>
          </section>
          </v-tabs-window-item>

          <!-- Tab: Verwendet in -->
          <v-tabs-window-item value="used-in" class="material-detail-window-item">
          <section class="tab-content">
            <div class="section-card">
              <div class="section-header-row">
                <h2 class="section-title">{{ t('components.materialDetail.sectionUsedInCombos') }}</h2>
                <div class="detail-inline-filters">
                  <input
                    v-model="usedInSearch"
                    type="text"
                    class="detail-inline-search"
                    :placeholder="t('components.materialDetail.usedInSearchPlaceholder')"
                  />
                </div>
              </div>

              <div v-if="isLoadingUsedIn" class="loading-container" style="padding: 40px;">
                <div class="spinner"></div>
                <p>{{ t('components.materialDetail.loadingShort') }}</p>
              </div>

              <div v-else-if="usedInEntries.length === 0" class="empty-used-in">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
                  <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <p>{{ t('components.materialDetail.usedInEmpty') }}</p>
              </div>
              <div v-else-if="filteredUsedInEntries.length === 0" class="empty-used-in">
                <p>{{ t('components.materialDetail.usedInNoSearchHits') }}</p>
              </div>

              <table v-else class="used-in-table">
                <thead>
                  <tr>
                    <th>{{ t('components.materialDetail.thCombo') }}</th>
                    <th>{{ t('components.materialDetail.thComboType') }}</th>
                    <th>{{ t('common.role') }}</th>
                    <th>{{ t('common.serialNumber') }}</th>
                    <th>{{ t('components.materialDetail.thQty') }}</th>
                    <th>{{ t('components.materialDetail.thAssignment') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr 
                    v-for="entry in filteredUsedInEntries"
                    :key="entry.combo_id + (entry.batch_id || '')"
                    class="used-in-row"
                  >
                    <td>
                      <span class="used-in-link" @click="navigateToCombo(entry.combo_id)">
                        {{ entry.combo_name }}
                      </span>
                    </td>
                    <td>
                      <span class="used-in-type-badge" :class="entry.material_type">
                        {{ entry.material_type === 'physical_combo' ? t('components.materialDetail.typePhysicalShort') : t('components.materialDetail.typeVirtualShort') }}
                      </span>
                    </td>
                    <td>{{ entry.component_role || t('components.materialDetail.emDash') }}</td>
                    <td>
                      <span v-if="entry.batch_serial" class="serial-code">{{ entry.batch_serial }}</span>
                      <span v-else class="no-serial">{{ t('components.materialDetail.emDash') }}</span>
                    </td>
                    <td>{{ entry.qty }}</td>
                    <td>
                      <span class="assignment-badge" :class="entry.assignment_mode === 'fixed' ? 'fix' : entry.assignment_mode">
                        {{ usedInAssignmentLabels[entry.assignment_mode] || entry.assignment_mode }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
          </v-tabs-window-item>

          <!-- Tab: History Log -->
          <v-tabs-window-item value="history" class="material-detail-window-item">
          <section class="tab-content">
            <div class="section-card history-card">
              <h2 class="section-title">{{ t('components.materialDetail.sectionHistoryTitle') }}</h2>
              
              <div v-if="isLoadingHistory" class="loading-container" style="padding: 40px;">
                <div class="spinner"></div>
                <p>{{ t('components.materialDetail.historyLoading') }}</p>
              </div>

              <div v-else-if="historyEntries.length === 0" class="empty-history">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
                  <circle cx="12" cy="12" r="10"/>
                  <polyline points="12 6 12 12 16 14"/>
                </svg>
                <p>{{ t('components.materialDetail.historyEmpty') }}</p>
              </div>

              <div v-else class="history-layout">
                <!-- Linke Seite: Speicherungen (Liste) -->
                <div class="history-list">
                  <div class="history-list-header">
                    <span class="history-list-title">{{ t('components.materialDetail.historyListSaves') }}</span>
                  </div>
                  <div class="history-list-content">
                    <div class="history-list-table">
                      <div class="history-list-th">
                        <span class="th-time">{{ t('components.materialDetail.historyThTime') }}</span>
                        <span class="th-user">{{ t('components.materialDetail.historyThUser') }}</span>
                      </div>
                      <div 
                        v-for="entry in historyEntries" 
                        :key="entry.id"
                        class="history-list-row"
                        :class="{ selected: selectedHistoryEntry?.id === entry.id }"
                        @click="selectedHistoryEntry = entry"
                      >
                        <span class="row-time">
                          <span class="time-date">{{ formatHistoryDate(entry.created_at) }}</span>
                          <span class="time-clock">{{ formatHistoryTime(entry.created_at) }}</span>
                        </span>
                        <span class="row-user">{{ entry.user?.name || t('components.materialDetail.historyUserSystem') }}</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Rechte Seite: Änderungen -->
                <div class="history-changes">
                  <div class="history-changes-header">
                    <span class="history-changes-title">{{ t('components.materialDetail.historyChangesTitle') }}</span>
                    <div v-if="selectedHistoryEntry" class="history-action-badge" :class="selectedHistoryEntry.action">
                      {{ actionLabels[selectedHistoryEntry.action] || selectedHistoryEntry.action }}
                    </div>
                  </div>
                  
                  <div v-if="!selectedHistoryEntry" class="history-empty-selection">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                      <polyline points="14 2 14 8 20 8"/>
                      <line x1="16" y1="13" x2="8" y2="13"/>
                      <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                    <p>{{ t('components.materialDetail.historySelectSaveHint') }}</p>
                  </div>

                  <div v-else-if="selectedHistoryEntry.action === 'created'" class="history-created-info">
                    <div class="created-badge">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 5v14M5 12h14"/>
                      </svg>
                      {{ t('components.materialDetail.historyCreatedBadge') }}
                    </div>
                    <p class="created-hint">{{ t('components.materialDetail.historyCreatedHint') }}</p>
                  </div>

                  <div v-else-if="selectedHistoryEntry.action === 'batch_added'" class="history-created-info batch-action">
                    <div class="created-badge batch-badge">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                      </svg>
                      {{ t('components.materialDetail.historyBatchAddedBadge') }}
                    </div>
                    <div v-if="Object.keys(selectedHistoryEntry.changes).length > 0" class="history-changes-list">
                      <div 
                        v-for="(change, fieldName) in selectedHistoryEntry.changes" 
                        :key="fieldName"
                        class="history-change-item"
                      >
                        <div class="change-field">{{ historyFieldLabel(String(fieldName)) }}</div>
                        <div class="change-values">
                          <div class="change-new-only">
                            <span class="change-value">{{ formatChangeValue(change.new) }}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div v-else-if="Object.keys(selectedHistoryEntry.changes).length === 0" class="history-no-changes">
                    <p>{{ t('components.materialDetail.historyNoFieldChanges') }}</p>
                  </div>

                  <div v-else class="history-changes-list">
                    <div 
                      v-for="(change, fieldName) in selectedHistoryEntry.changes" 
                      :key="fieldName"
                      class="history-change-item"
                    >
                      <div class="change-field">{{ historyFieldLabel(String(fieldName)) }}</div>
                      <div class="change-values">
                        <div class="change-old">
                          <span class="change-label">{{ t('components.materialDetail.historyLabelBefore') }}</span>
                          <span class="change-value">{{ formatChangeValue(change.old) }}</span>
                        </div>
                        <div class="change-arrow">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                          </svg>
                        </div>
                        <div class="change-new">
                          <span class="change-label">{{ t('components.materialDetail.historyLabelAfter') }}</span>
                          <span class="change-value">{{ formatChangeValue(change.new) }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </section>
          </v-tabs-window-item>
          </v-tabs-window>
        </main>

        <!-- Sidebar (Right) -->
        <aside v-if="activeTab === 'data'" class="content-sidebar">
          <!-- Abbildung -->
          <div class="sidebar-card">
            <div class="sidebar-header">
              <h3>{{ t('components.materialDetail.sidebarImage') }}</h3>
            </div>
            <div class="image-slot">
              <svg v-if="!materialPrimaryImageUrl" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21 15 16 10 5 21"/>
              </svg>
              <img v-else :src="materialPrimaryImageUrl" :alt="t('components.materialDetail.altMaterialImage')" />
            </div>
            <MaterialImagePicker
              v-if="canManageMaterials"
              :has-image="!!materialPrimaryImageUrl"
              :search-query="material.name"
              :upload-file="uploadMaterialPhotoFile"
              :import-url="importMaterialPhotoFromUrlFn"
              @uploaded="onMaterialPhotoUploaded"
              @error="onMaterialPhotoError"
            />
          </div>

          <!-- Bestand Quick View (virtuelle Kombo: nur Link zur Stückliste) -->
          <div v-if="!isVirtualComboView || canManageMaterials" class="sidebar-card">
            <div class="sidebar-header">
              <h3>{{ isVirtualComboView ? t('components.materialDetail.tabComposition') : t('components.materialDetail.sidebarStockQuick') }}</h3>
              <button
                v-if="canManageMaterials && isComboMaterialView"
                type="button"
                class="link-btn"
                @click="activeTab = 'composition'"
              >
                {{ isVirtualComboView ? t('components.materialDetail.linkOpenComposition') : t('components.materialDetail.tabComposition') }}
              </button>
              <button
                v-else-if="canManageMaterials"
                type="button"
                class="link-btn"
                @click="activeTab = 'stock'"
              >
                {{ t('components.materialDetail.linkChangeStock') }}
              </button>
            </div>
            <div v-if="isVirtualComboView" class="stock-quick stock-quick--virtual-combo">
              <p class="text-muted virtual-combo-sidebar-hint">
                {{ t('components.materialDetail.virtualComboSidebarHint') }}
              </p>
            </div>
            <div v-else class="stock-quick">
              <div class="stock-row stock-row-total">
                <span>{{ t('components.materialDetail.stockLabelTotal') }}</span>
                <span class="stock-val">{{ material.total_stock }}</span>
              </div>
              <template v-if="!isUserMaterialsBrowseOnly">
              <div v-if="material.pack_size && material.pack_unit" class="stock-row">
                <span>{{ material.pack_unit }}</span>
                <span class="stock-val">{{ packUnitCount }} à {{ material.pack_size }}</span>
              </div>
              <div v-if="material.pack_size && material.pack_unit && packLooseCount > 0" class="stock-row">
                <span>{{ t('components.materialDetail.stockLoosePieces') }}</span>
                <span class="stock-val">{{ packLooseCount }}</span>
              </div>
              <div class="stock-row-separator"></div>
              <div class="stock-row">
                <span>{{ t('components.materialDetail.stockLabelInWarehouse') }}</span>
                <span class="stock-val warehouse">{{ material.in_warehouse ?? availableStock }}</span>
              </div>
              <div class="stock-row" v-if="material.issued_out > 0">
                <span>{{ t('components.materialDetail.stockLabelOut') }}</span>
                <span class="stock-val issued">{{ material.issued_out }}</span>
              </div>
              <div class="stock-row" v-if="material.reserved > 0">
                <span>{{ t('components.materialDetail.stockLabelReserved') }}</span>
                <span class="stock-val reserved">{{ material.reserved }}</span>
              </div>
              <div class="stock-row" v-if="material.repair_stock > 0">
                <span>{{ t('components.materialDetail.stockLabelRepair') }}</span>
                <span class="stock-val repair">{{ material.repair_stock }}</span>
              </div>
              <div class="stock-row" v-if="material.defect_stock > 0 || defectStock > 0">
                <span>{{ t('components.materialDetail.stockLabelDefect') }}</span>
                <span class="stock-val defect">{{ material.defect_stock || defectStock }}</span>
              </div>
              <div class="stock-row" v-if="archivedStock > 0">
                <span>{{ t('components.materialDetail.stockWrittenOffArchive') }}</span>
                <span class="stock-val archived">{{ archivedStock }}</span>
              </div>
              <div class="stock-row stock-row-loss" v-if="material.open_loss_reports > 0">
                <span>{{ t('components.materialDetail.sidebarLossReported') }}</span>
                <span class="stock-val loss">{{ openLossLabel }}</span>
              </div>
              <div class="stock-row-separator"></div>
              <div class="stock-row stock-row-highlight">
                <span>{{ t('components.materialDetail.stockLabelAvailable') }}</span>
                <span class="stock-val available">{{ material.available ?? availableStock }}</span>
              </div>
              </template>
            </div>
          </div>

          <!-- Kategorie Quick View -->
          <div class="sidebar-card" v-if="material.category">
            <div class="sidebar-header">
              <h3>{{ t('components.materialDetail.sidebarCategory') }}</h3>
            </div>
            <div class="category-path">
              {{ getCategoryPath() }}
            </div>
          </div>
        </aside>
      </div>
      </div>
    </div>

    <!-- Batch Modal (Add / Edit) -->
    <BatchModal
      v-if="showBatchModal && material"
      :key="`${material.id}-${editingBatch?.id ?? 'new'}`"
      :material-id="props.materialId"
      :department-id="props.departmentId"
      :batch="editingBatch"
      :tracking-type="material.tracking_type ?? null"
      :is-serialized="material?.tracking_type === 'serialized'"
      :material-name="material?.name || ''"
      :existing-batches="batches"
      :pack-unit="material.pack_unit"
      :pack-size="material.pack_size"
      :size-length-cm="material.size_length"
      :reference-purchase-unit-chf="material.reference_purchase_unit_chf"
      :unit-price-optional="isRepairPartMaterial"
      :combo-storage-context="editingBatchComboContext"
      @close="closeBatchModal"
      @saved="handleBatchSaved"
    />

    <SplitModal
      v-model="showSplitModal"
      v-if="material"
      :material-id="props.materialId"
      :department-id="props.departmentId"
      :material-name="material?.name || ''"
      :source-batches="splitSourceBatches"
      :existing-batches="batches"
      @saved="handleSplitSaved"
    />
    <MoveQuantityModal
      v-if="showMoveModal && moveBatch"
      :material-id="materialId"
      :department-id="departmentId"
      :batch="moveBatch"
      @close="showMoveModal = false; moveBatch = null"
      @saved="handleMoveSaved"
    />
    <RemoveCompositionReleaseModal
      v-if="pendingRemoveComposition && linkedContainerBatchIdForRelease"
      :department-id="departmentId"
      :component-material-id="pendingRemoveComposition.component_material.id"
      :component-name="pendingRemoveComposition.component_material.name"
      :qty="pendingRemoveComposition.qty"
      :source-container-batch-id="linkedContainerBatchIdForRelease"
      :source-container-label="linkedContainerLabelForRelease"
      :submitting="deletingCompositionId === pendingRemoveComposition.id"
      @cancel="cancelRemoveCompositionRelease"
      @confirm="executeRemoveCompositionWithRelease"
    />

    <EDialog
      v-model="showAddToContainerModal"
      :title="t('components.materialDetail.modalAddToContainerTitle')"
      :max-width="560"
    >
        <div class="form-group">
          <label>{{ t('components.materialDetail.labelSearchArticle') }}</label>
          <MaterialLookupInput
            v-model="addToContainerSearch"
            :fetcher="addToContainerMaterialFetcher"
            :min-chars="1"
            :max-suggestions="5"
            :placeholder="t('components.materialDetail.phSearchMaterial')"
            :loading-text="t('components.materialDetail.lookupLoading')"
            :empty-text="t('components.materialDetail.lookupEmptyHits', { query: addToContainerSearch || '' })"
            :get-result-label="formatAddToContainerLookupLabel"
            :get-result-secondary="formatAddToContainerLookupSecondary"
            @select="handleAddToContainerLookupSelect"
          />
        </div>

        <div v-if="addToContainerSourceMaterial" class="form-group">
          <label>{{ t('components.materialDetail.labelSelectedArticle') }}</label>
          <div class="selected-source-material">
            <div class="name">{{ addToContainerSourceMaterial.name }}</div>
            <div class="meta">{{ t('components.materialDetail.metaTotalStock', { n: addToContainerSourceMaterial.total_stock || 0 }) }}</div>
            <button type="button" class="btn-outline-small" @click="clearAddToContainerMaterialSelection">{{ t('components.materialDetail.btnChangeSelection') }}</button>
          </div>
        </div>

        <div v-if="addToContainerSourceMaterialId" class="form-group">
          <label>{{ t('components.materialDetail.labelBatchOrSerial') }}</label>
          <select v-model="addToContainerSourceBatchId" class="form-select" @change="handleAddToContainerBatchChange">
            <option value="">{{ t('components.materialDetail.optSelectSource') }}</option>
            <option v-for="batch in addToContainerSourceBatches" :key="batch.id" :value="batch.id">
              {{ formatSourceBatchOption(batch) }}
            </option>
          </select>
        </div>

        <div v-if="selectedAddToContainerAllocations.length > 0" class="form-group">
          <label>{{ t('components.materialDetail.labelFromSourceLocation') }}</label>
          <select v-model="addToContainerSourceAllocationId" class="form-select">
            <option value="">{{ t('components.materialDetail.optSelectSource') }}</option>
            <option v-for="alloc in selectedAddToContainerAllocations" :key="alloc.id" :value="alloc.id">
              {{ formatAllocationLocationInline(alloc) }} – {{ t('components.materialDetail.qtyPieces', { qty: alloc.qty }) }}
            </option>
          </select>
        </div>

        <div v-if="selectedAddToContainerBatch" class="form-group">
          <label>{{ t('components.materialDetail.thQty') }}</label>
          <input v-model.number="addToContainerQty" type="number" min="1" :max="addToContainerMaxQty" class="form-input" />
          <p class="batch-field-hint">{{ t('components.materialDetail.hintMaxQty', { n: addToContainerMaxQty }) }}</p>
        </div>

        <p v-if="addToContainerError" class="error-text">{{ addToContainerError }}</p>
      <template #actions>
        <EButton variant="secondary" size="small" @click="closeAddToContainerModal">{{ t('common.cancel') }}</EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!canSubmitAddToContainer || isAddingToContainer"
          :loading="isAddingToContainer"
          @click="submitAddToContainer"
        >
          {{ isAddingToContainer ? t('components.materialDetail.modalAddToContainerSubmitting') : t('common.add') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="showAddCompositionModal"
      :max-width="720"
      card-class="composition-add-modal"
    >
      <template #title>
        <div class="composition-add-modal-header">
          <h3>{{ t('components.materialDetail.modalAddCompositionTitle') }}</h3>
          <p class="text-muted composition-add-modal-intro">
            {{ t('components.materialDetail.modalAddCompositionIntro') }}
          </p>
        </div>
      </template>
        <div class="composition-add-modal-body">
        <div class="form-group">
          <label>{{ t('components.materialDetail.labelSearchArticle') }}</label>
          <MaterialLookupInput
            v-model="addCompositionSearch"
            :fetcher="compositionMaterialFetcher"
            :min-chars="1"
            :max-suggestions="8"
            :placeholder="t('components.materialDetail.phNameOrCode')"
            :loading-text="t('components.materialDetail.lookupLoadingEllipsis')"
            :empty-text="t('components.materialDetail.lookupEmptyHits', { query: addCompositionSearch || '' })"
            :get-result-label="compositionLookupLabel"
            :get-result-secondary="formatCompositionLookupSecondary"
            @select="handleCompositionMaterialSelect"
          />
        </div>
        <div v-if="addCompositionSelected" class="form-group">
          <label>{{ t('components.materialDetail.labelSelectedArticle') }}</label>
          <div class="selected-source-material">
            <div class="name">{{ addCompositionSelected.name }}</div>
            <div class="meta">{{ t('components.materialDetail.metaTotalStock', { n: addCompositionSelected.total_stock ?? 0 }) }}</div>
            <button type="button" class="btn-outline-small" @click="clearAddCompositionSelection">{{ t('components.materialDetail.btnChangeSelection') }}</button>
          </div>
        </div>
        <div v-if="addCompositionSelected" class="form-group">
          <label>{{ t('components.materialDetail.thQty') }}</label>
          <input
            v-model.number="addCompositionQty"
            type="number"
            :min="addCompositionOptional ? 0 : 1"
            :max="addCompositionStockCap ?? undefined"
            class="form-input"
            @input="clampAddCompositionQty"
            @blur="clampAddCompositionQty"
          />
          <p v-if="addCompositionOptional" class="batch-field-hint">
            {{ t('components.materialDetail.hintOptionalQtyZero') }}
          </p>
          <p v-if="addCompositionStockCap !== null && addCompositionStockCap > 0" class="batch-field-hint">
            {{ t('components.materialDetail.hintMaxQty', { n: addCompositionStockCap }) }}
          </p>
          <p v-else-if="addCompositionStockCap === 0" class="error-text">{{ t('components.materialDetail.errAddCompositionNoStock') }}</p>
          <p v-if="addCompositionAllocatesToLinkedCrate" class="batch-field-hint">
            {{ t('components.materialDetail.hintAddCompositionToCrate') }}
          </p>
          <div
            v-if="addCompositionAllocatesToLinkedCrate && addCompositionStockLocationRows.length > 0"
            class="composition-stock-preview"
          >
            <p class="composition-stock-preview-title">{{ t('components.materialDetail.addCompositionStockWhereTitle') }}</p>
            <ul class="composition-stock-preview-list">
              <li v-for="(row, idx) in addCompositionStockLocationRows" :key="`loc-${idx}`">
                {{ formatStorageRowLabel(row) }}
                <span class="composition-stock-preview-qty">{{ t('components.materialDetail.qtyPieces', { qty: row.qty }) }}</span>
              </li>
            </ul>
          </div>
          <div
            v-if="addCompositionAllocatesToLinkedCrate && addCompositionTakePreview && addCompositionTakePreview.lines.length > 0"
            class="composition-stock-preview composition-stock-preview--move"
          >
            <p class="composition-stock-preview-title">{{ t('components.materialDetail.addCompositionTakePreviewTitle') }}</p>
            <ul class="composition-stock-preview-list">
              <li v-for="(line, idx) in addCompositionTakePreview.lines" :key="`take-${idx}`">
                {{ line.label }}
                <span class="composition-stock-preview-qty">→ {{ t('components.materialDetail.qtyPieces', { qty: line.qty }) }}</span>
              </li>
            </ul>
            <p class="batch-field-hint">
              {{ t('components.materialDetail.addCompositionTakePreviewTo', { target: addCompositionTakePreview.toLabel }) }}
            </p>
            <p v-if="addCompositionTakePreview.remaining > 0" class="error-text">
              {{ t('components.materialDetail.addCompositionTakePreviewShort', { n: addCompositionTakePreview.remaining }) }}
            </p>
          </div>
        </div>
        <div v-if="addCompositionSelected" class="form-group">
          <label>{{ t('components.materialDetail.labelRoleOptional') }}</label>
          <input v-model="addCompositionRole" type="text" class="form-input" :placeholder="t('components.materialDetail.phRoleExamples')" />
          <p class="batch-field-hint">{{ t('components.materialDetail.hintRoleInCombo') }}</p>
        </div>
        <div v-if="addCompositionSelected && isVirtualComboView" class="form-group">
          <label class="checkbox-label">
            <input v-model="addCompositionOptional" type="checkbox" @change="clampAddCompositionQty" />
            {{ t('components.materialDetail.labelOptionalForCombo') }}
          </label>
        </div>
        <div v-if="addCompositionSelected && isVirtualComboView" class="form-group">
          <label>{{ t('components.materialDetail.labelComponentSource') }}</label>
          <select v-model="addCompositionSource" class="form-select">
            <option value="stock">{{ t('components.materialDetail.componentSourceStock') }}</option>
            <option value="self_provided">{{ t('components.materialDetail.componentSourceSelfProvided') }}</option>
          </select>
          <p class="batch-field-hint">{{ t('components.materialDetail.hintComponentSource') }}</p>
        </div>
        <p v-if="addCompositionError" class="error-text">{{ addCompositionError }}</p>
        </div>
        <div v-if="addCompositionSelected" class="composition-add-modal-selects">
          <div class="form-group">
            <label>{{ t('components.materialDetail.labelAssignmentMode') }}</label>
            <select v-model="addCompositionMode" class="form-select">
              <option value="bulk">{{ comboAssignmentLabels.bulk }}</option>
              <option value="fixed">{{ comboAssignmentLabels.fixed }}</option>
              <option value="assigned">{{ comboAssignmentLabels.assigned }}</option>
              <option value="on_issue">{{ comboAssignmentLabels.on_issue }}</option>
            </select>
          </div>
        </div>
      <template #actions>
        <div class="composition-add-modal-footer">
          <EButton variant="secondary" size="small" @click="closeAddCompositionModal">{{ t('common.cancel') }}</EButton>
          <EButton
            variant="primary"
            size="small"
            :disabled="!canSubmitAddComposition || addCompositionSubmitting"
            :loading="addCompositionSubmitting"
            @click="submitAddComposition"
          >
            {{ addCompositionSubmitting ? t('components.materialDetail.modalAddCompositionSubmitting') : t('common.add') }}
          </EButton>
        </div>
      </template>
    </EDialog>

    <EDialog
      v-model="showAddAccessoryModal"
      :max-width="720"
      card-class="composition-add-modal"
    >
      <template #title>
        <div class="composition-add-modal-header">
          <h3>{{ t('components.materialDetail.modalAddAccessoryTitle') }}</h3>
          <p class="text-muted composition-add-modal-intro">
            {{ t('components.materialDetail.modalAddAccessoryIntro') }}
          </p>
        </div>
      </template>
        <div class="composition-add-modal-body">
          <div class="form-group">
            <label>{{ t('components.materialDetail.labelSearchArticle') }}</label>
            <MaterialLookupInput
              v-model="addAccessorySearch"
              :fetcher="accessoryMaterialFetcher"
              :min-chars="1"
              :max-suggestions="8"
              :placeholder="t('components.materialDetail.phNameOrCode')"
              :loading-text="t('components.materialDetail.lookupLoadingEllipsis')"
              :empty-text="t('components.materialDetail.lookupEmptyHits', { query: addAccessorySearch || '' })"
              :get-result-label="accessoryLookupLabel"
              :get-result-secondary="formatCompositionLookupSecondary"
              @select="handleAccessorySelect"
            />
          </div>
          <div v-if="addAccessorySelected" class="form-group">
            <label>{{ t('components.materialDetail.labelSelectedArticle') }}</label>
            <div class="selected-source-material">
              <div class="name">{{ addAccessorySelected.name }}</div>
              <div class="meta">{{ t('components.materialDetail.metaTotalStock', { n: addAccessorySelected.total_stock ?? 0 }) }}</div>
              <button type="button" class="btn-outline-small" @click="clearAddAccessorySelection">{{ t('components.materialDetail.btnChangeSelection') }}</button>
            </div>
          </div>
          <p v-if="addAccessoryError" class="error-text">{{ addAccessoryError }}</p>
        </div>
      <template #actions>
        <div class="composition-add-modal-footer">
          <EButton variant="secondary" size="small" @click="closeAddAccessoryModal">{{ t('common.cancel') }}</EButton>
          <EButton
            variant="primary"
            size="small"
            :disabled="!addAccessorySelected || addAccessorySubmitting"
            :loading="addAccessorySubmitting"
            @click="submitAddAccessory"
          >
            {{ addAccessorySubmitting ? t('components.materialDetail.modalAddAccessorySubmitting') : t('components.materialDetail.btnAddAccessory') }}
          </EButton>
        </div>
      </template>
    </EDialog>

    <EDialog
      v-if="editCompositionComp"
      v-model="showEditCompositionModal"
      :max-width="720"
      card-class="composition-add-modal"
    >
      <template #title>
        <div class="composition-add-modal-header">
          <h3>{{ t('components.materialDetail.modalEditCompositionTitle') }}</h3>
          <p class="text-muted composition-add-modal-intro">
            <strong>{{ editCompositionComp.component_material.name }}</strong>
          </p>
        </div>
      </template>
        <div class="composition-add-modal-body">
        <template v-if="isEditCompositionLinkedContainer">
          <div class="form-group composition-ref-container-panel">
            <p class="batch-field-hint">{{ t('components.materialDetail.editRefContainerIntro') }}</p>
            <p class="batch-field-hint text-muted">{{ linkedContainerLabelForRelease }}</p>
            <div class="composition-ref-container-actions">
              <button type="button" class="btn-outline-small" @click="openLinkedContainerStoredInTab">
                {{ t('components.materialDetail.btnOpenRefContainer') }}
              </button>
            </div>
            <div v-if="editLinkedContainerContentsLoading" class="text-muted composition-ref-container-loading">
              {{ t('components.materialDetail.editRefContainerLoading') }}
            </div>
            <div v-else-if="editLinkedContainerContentRows.length === 0" class="empty-used-in">
              <p>{{ t('components.materialDetail.editRefContainerEmpty') }}</p>
            </div>
            <div v-else class="composition-table-wrapper">
              <table class="used-in-table composition-table composition-ref-container-table">
                <thead>
                  <tr>
                    <th>{{ t('components.materialDetail.thComponent') }}</th>
                    <th>{{ t('components.materialDetail.editRefContainerThStored') }}</th>
                    <th>{{ t('components.materialDetail.editRefContainerThPlanned') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in editLinkedContainerContentRows" :key="row.materialId">
                    <td>{{ row.materialName }}</td>
                    <td>{{ t('components.materialDetail.qtyPieces', { qty: row.storedQty }) }}</td>
                    <td>
                      <span v-if="row.plannedQty !== null">{{ t('components.materialDetail.qtyPieces', { qty: row.plannedQty }) }}</span>
                      <span v-else class="text-muted">{{ t('components.materialDetail.emDash') }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="batch-field-hint">{{ t('components.materialDetail.editRefContainerHint') }}</p>
          </div>
          <div class="form-group">
            <label>{{ t('components.materialDetail.thQty') }}</label>
            <input
              :value="1"
              type="number"
              class="form-input"
              disabled
            />
            <p class="batch-field-hint">{{ t('components.materialDetail.editRefContainerQtyFixed') }}</p>
          </div>
        </template>
        <div v-else class="form-group">
          <label>{{ t('components.materialDetail.thQty') }}</label>
          <input
            v-model.number="editCompositionQty"
            type="number"
            :min="editCompositionOptional ? 0 : 1"
            :max="editCompositionStockCap ?? undefined"
            class="form-input"
            @input="clampEditCompositionQty"
            @blur="clampEditCompositionQty"
          />
          <p v-if="editCompositionOptional" class="batch-field-hint">
            {{ t('components.materialDetail.hintOptionalQtyZero') }}
          </p>
          <p v-if="editCompositionStockCap !== null && editCompositionStockCap > 0" class="batch-field-hint">
            {{ t('components.materialDetail.hintMaxQty', { n: editCompositionStockCap }) }}
          </p>
          <p v-if="addCompositionAllocatesToLinkedCrate" class="batch-field-hint">
            {{ t('components.materialDetail.hintEditCompositionToCrate') }}
          </p>
          <p v-if="addCompositionAllocatesToLinkedCrate" class="batch-field-hint">
            {{
              t('components.materialDetail.editCompositionFreeStockHint', {
                n: editCompositionMaterialFreeStock,
              })
            }}
          </p>
          <p
            v-if="addCompositionAllocatesToLinkedCrate && editComponentStoredInLinkedContainer !== null"
            class="batch-field-hint composition-ref-container-stored-hint"
          >
            {{
              t('components.materialDetail.editCompositionStoredInRefContainer', {
                n: editComponentStoredInLinkedContainer,
                label: linkedContainerLabelForRelease,
              })
            }}
          </p>
          <div
            v-if="addCompositionAllocatesToLinkedCrate && editCompositionStockLocationRows.length > 0"
            class="composition-stock-preview"
          >
            <p class="composition-stock-preview-title">{{ t('components.materialDetail.addCompositionStockWhereTitle') }}</p>
            <ul class="composition-stock-preview-list">
              <li v-for="(row, idx) in editCompositionStockLocationRows" :key="`edit-loc-${idx}`">
                {{ formatStorageRowLabel(row) }}
                <span class="composition-stock-preview-qty">{{ t('components.materialDetail.qtyPieces', { qty: row.qty }) }}</span>
              </li>
            </ul>
          </div>
          <div
            v-if="addCompositionAllocatesToLinkedCrate && editCompositionTakePreview && editCompositionTakePreview.lines.length > 0"
            class="composition-stock-preview composition-stock-preview--move"
          >
            <p class="composition-stock-preview-title">{{ t('components.materialDetail.editCompositionTakePreviewTitle') }}</p>
            <ul class="composition-stock-preview-list">
              <li v-for="(line, idx) in editCompositionTakePreview.lines" :key="`edit-take-${idx}`">
                {{ line.label }}
                <span class="composition-stock-preview-qty">→ {{ t('components.materialDetail.qtyPieces', { qty: line.qty }) }}</span>
              </li>
            </ul>
            <p class="batch-field-hint">
              {{ t('components.materialDetail.addCompositionTakePreviewTo', { target: editCompositionTakePreview.toLabel }) }}
            </p>
            <p v-if="editCompositionTakePreview.remaining > 0" class="error-text">
              {{ t('components.materialDetail.addCompositionTakePreviewShort', { n: editCompositionTakePreview.remaining }) }}
            </p>
          </div>
        </div>
        <div class="form-group">
          <label>{{ t('components.materialDetail.labelRoleOptional') }}</label>
          <input v-model="editCompositionRole" type="text" class="form-input" :placeholder="t('components.materialDetail.phRoleExamples')" />
          <p class="batch-field-hint">{{ t('components.materialDetail.hintRoleInCombo') }}</p>
        </div>
        <div v-if="isVirtualComboView" class="form-group">
          <label class="checkbox-label">
            <input v-model="editCompositionOptional" type="checkbox" @change="clampEditCompositionQty" />
            {{ t('components.materialDetail.labelOptionalForCombo') }}
          </label>
        </div>
        <div v-if="isVirtualComboView" class="form-group">
          <label>{{ t('components.materialDetail.labelComponentSource') }}</label>
          <select v-model="editCompositionSource" class="form-select">
            <option value="stock">{{ t('components.materialDetail.componentSourceStock') }}</option>
            <option value="self_provided">{{ t('components.materialDetail.componentSourceSelfProvided') }}</option>
          </select>
          <p class="batch-field-hint">{{ t('components.materialDetail.hintComponentSource') }}</p>
        </div>
        <p v-if="editCompositionError" class="error-text">{{ editCompositionError }}</p>
        </div>
        <div class="composition-add-modal-selects">
          <div class="form-group">
            <label>{{ t('components.materialDetail.labelAssignmentMode') }}</label>
            <select v-model="editCompositionMode" class="form-select">
            <option value="bulk">{{ comboAssignmentLabels.bulk }}</option>
            <option value="fixed">{{ comboAssignmentLabels.fixed }}</option>
            <option value="assigned">{{ comboAssignmentLabels.assigned }}</option>
            <option value="on_issue">{{ comboAssignmentLabels.on_issue }}</option>
            </select>
          </div>
          <div v-if="editCompositionBatchesLoading" class="form-group text-muted">{{ t('components.materialDetail.loadingBatchesEllipsis') }}</div>
          <div v-else-if="editCompositionBatches.length > 0" class="form-group">
            <label>{{ t('components.materialDetail.labelAssignedBatchOptional') }}</label>
            <select v-model="editCompositionBatchId" class="form-select">
              <option value="">{{ t('components.materialDetail.optNoBatch') }}</option>
              <option v-for="b in editCompositionBatches" :key="b.id" :value="b.id">
                {{ formatCompositionBatchOption(b) }}
              </option>
            </select>
            <p class="batch-field-hint">{{ t('components.materialDetail.hintAssignedBatchSerie') }}</p>
            <p class="batch-field-hint">{{ t('components.materialDetail.hintAssignedBatchInCombo') }}</p>
          </div>
          <div v-if="canOfferSetAsLinkedContainer" class="form-group composition-set-ref-container-field">
            <label class="checkbox-label">
              <input v-model="editCompositionSetAsLinkedContainer" type="checkbox" />
              {{ t('components.materialDetail.labelSetAsRefContainer') }}
            </label>
            <p class="batch-field-hint">{{ t('components.materialDetail.hintSetAsRefContainer') }}</p>
            <p
              v-if="editCompositionSetAsLinkedContainer && linkedContainerBatchIdForRelease"
              class="batch-field-hint composition-set-ref-container-warn"
            >
              {{
                t('components.materialDetail.hintReplaceRefContainerInline', {
                  current: linkedContainerLabelForRelease,
                  next: editCompositionLinkedContainerCandidateLabel,
                })
              }}
            </p>
          </div>
        </div>
      <template #actions>
        <div class="composition-add-modal-footer">
          <EButton variant="secondary" size="small" @click="closeEditCompositionModal">{{ t('common.cancel') }}</EButton>
          <EButton
            variant="primary"
            size="small"
            :disabled="editCompositionSubmitting || !canSubmitEditComposition"
            :loading="editCompositionSubmitting"
            @click="submitEditComposition"
          >
            {{ editCompositionSubmitting ? t('components.materialDetail.modalEditCompositionSaving') : t('common.save') }}
          </EButton>
        </div>
      </template>
    </EDialog>

    <PublicQrActionModal
      :open="showQrActionModal && qrActionMode === 'batch'"
      :label="qrActionLabel"
      :code="qrActionCode"
      :url="qrActionUrl"
      @close="closeQrActionModal"
      @add-to-print-cart="handleQrAddToPrintCart"
      @print="handleQrPrint"
    />

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onDeactivated, watch, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import QRCode from 'qrcode'
import {
  getMaterial,
  getMaterials,
  updateMaterial,
  uploadMaterialPhoto,
  importMaterialPhotoFromUrl,
  updateBatch,
  moveBatchQuantity,
  getMaterialHistory,
  getMaterialUsedIn,
  getMaterialActivityBookings,
  type MaterialActivityBookingRow,
  ensureMaterialPublicCode,
  getMaterialStorageLocations,
  getComboComponents,
  addComboComponent,
  updateComboComponent,
  deleteComboComponent,
  finalizeCombo,
  getRelatedAccessories,
  addRelatedAccessory,
  deleteRelatedAccessory,
  type RelatedAccessory,
  type DeleteComboComponentRequest,
  type Material,
  type MaterialHistoryEntry,
  type MaterialBatch,
  type BatchStorageAllocation,
  type UsedInEntry,
  type AddBatchMultiResponse,
  type MaterialStorageLocationsResponse,
  type MaterialStorageLocationRow,
  type ComboComponent,
  type ComboOption,
  type ComboOptionGroup,
  type ComponentSource,
  type UpdateComboComponentRequest,
} from '@/api/materials'
import { addPrintCartItem, addPrintCartItemsBulk } from '@/api/tasks'
import { useDetailTabsStore } from '@/stores/detailTabs'
import { getCategories, type Category } from '@/api/categories'
import {
  getContainerBatches,
  getContainerBatchContents,
  getStorageOverview,
  type ContainerBatch,
  type ContainerBatchContentsResponse,
  type StorageOverviewResponse,
} from '@/api/storageLocations'
import {
  containerBatchFromLinkedRef,
  formatContainerBatchOptionFullLabel,
  formatPhysicalComboLinkedContainerLabel,
} from '@/utils/containerBatchLabel'
import { usePhysicalComboWarningStore } from '@/stores/physicalComboWarning'
import {
  sumAcquisitionBasisFromBatches,
  sumAcquisitionPieceCountFromBatches,
  comboLineAcquisitionChf,
  roundChfToFiveRappen,
  formatChfFiveRappenString,
  type RentalCalcParams,
} from '@/utils/rentalPriceAmortization'
import {
  getRentalAmortizationDefaults,
  getWorkshopSettings,
  DEFAULT_RENTAL_AMORTIZATION,
  type RentalAmortizationDefaults,
} from '@/api/departmentSettings'
import { getWorkshopTickets, type WorkshopTicket } from '@/api/workshop'
import RentalPriceAmortizationCalculator from '@/components/material/RentalPriceAmortizationCalculator.vue'
import { AutoSaveField, useFormFieldBaselines } from '@/components/common/autoSave'
import { normalizeMaterialMetricInput } from '@/utils/materialMetricUnits'
import {
  applyMaterialUnitSuffixToName,
  formatMaterialDisplayName,
  formatStockQty,
  formatStockUnitSettingLabel,
  getStockUnitLabel,
  isMeterStockUnit,
  isPackagingUnit,
  parseSizeLengthCm,
} from '@/utils/materialStockUnit'
import SplitModal from '@/components/material/SplitModal.vue'
import { useAuthStore } from '@/stores/auth'
import { isDepartmentBasicMemberRole } from '@/composables/useDepartmentMemberRole'
import { usePageHeadStore } from '@/stores/pageHead'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useI18n } from 'vue-i18n'
import { printHtmlDocument } from '@/utils/printHtml'
import BatchModal from '@/components/material/BatchModal.vue'
import MaterialDetailStockUnitField from '@/components/material/MaterialDetailStockUnitField.vue'
import MaterialStockBatchesDataTable from '@/components/material/MaterialStockBatchesDataTable.vue'
import MaterialSerialBatchesDataTable from '@/components/material/MaterialSerialBatchesDataTable.vue'
import MaterialArchiveBatchesDataTable from '@/components/material/MaterialArchiveBatchesDataTable.vue'
import MaterialWorkshopTicketsDataTable from '@/components/material/MaterialWorkshopTicketsDataTable.vue'
import MaterialRentalActivityBookingsDataTable from '@/components/material/MaterialRentalActivityBookingsDataTable.vue'
import MaterialComboRentalBasisDataTable from '@/components/material/MaterialComboRentalBasisDataTable.vue'
import TableIconButton from '@/components/common/TableIconButton.vue'
import MoveQuantityModal from '@/components/material/MoveQuantityModal.vue'
import RemoveCompositionReleaseModal from '@/components/material/RemoveCompositionReleaseModal.vue'
import {
  formatStorageRowLabel,
  previewTakeForLinkedCrate,
} from '@/utils/compositionStockLocations'
import { resolveBatchComboStorageContext } from '@/utils/batchComboStorageContext'
import StorageTreeView from '@/components/storage/StorageTreeView.vue'
import MaterialLookupInput from '@/components/common/MaterialLookupInput.vue'
import ComboOptionsEditor from '@/components/material/ComboOptionsEditor.vue'
import CategoryAutocompleteInput from '@/components/common/CategoryAutocompleteInput.vue'
import { createBasicMaterialLookupFetcher } from '@/composables/useMaterialLookup'
import PublicQrTag from '@/components/common/PublicQrTag.vue'
import PublicQrActionModal from '@/components/common/PublicQrActionModal.vue'
import { unitPriceFromPackSaleChf } from '@/utils/packPricing'
import { isPrintableBatchPublicUrl } from '@/utils/publicQrUrl'
import { isComboMaterial as isComboMaterialType, COMBO_BADGE } from '@/utils/comboDisplay'
import MaterialImagePicker from '@/components/media/MaterialImagePicker.vue'
import { EButton, EDialog } from '@/components/form/base'

interface Props {
  materialId: string
  departmentId: string
  /** Batch-ID aus URL (z.B. von Lagerübersicht) – öffnet BatchModal zur Slot-Zuordnung */
  initialBatchId?: string
}

const props = defineProps<Props>()
const router = useRouter()
const route = useRoute()
const pageHeadStore = usePageHeadStore()
const authStore = useAuthStore()
const detailTabsStore = useDetailTabsStore()
const toast = useToast()
const { confirm: confirmDialog } = useConfirm()
const { t, tm, locale, te } = useI18n()
const physicalComboWarningStore = usePhysicalComboWarningStore()
const PACK_UNIT_BUNDLE = 'Bündel'
const PACK_UNIT_KISTE = 'Kiste'
const PACK_UNIT_KARTON = 'Karton'
const PACK_UNIT_SACK = 'Sack'
const PACK_UNIT_ROLLE = 'Rolle'
const PACK_UNIT_PALETTE = 'Palette'
const PACK_UNIT_SET = 'Set'
const PACK_UNIT_PAKET = 'Paket'
const PACK_UNIT_VALUES = [
  PACK_UNIT_BUNDLE,
  PACK_UNIT_KISTE,
  PACK_UNIT_KARTON,
  PACK_UNIT_SACK,
  PACK_UNIT_ROLLE,
  PACK_UNIT_PALETTE,
  PACK_UNIT_SET,
  PACK_UNIT_PAKET,
  '',
]

const packUnitSelectOptions = computed(() => [
  { value: '', label: t('components.materialDetail.packUnitNone') },
  { value: PACK_UNIT_BUNDLE, label: t('components.materialDetail.packUnitBundle') },
  { value: PACK_UNIT_KISTE, label: t('components.materialDetail.packUnitKiste') },
  { value: PACK_UNIT_KARTON, label: t('components.materialDetail.packUnitKarton') },
  { value: PACK_UNIT_SACK, label: t('components.materialDetail.packUnitSack') },
  { value: PACK_UNIT_ROLLE, label: t('components.materialDetail.packUnitRolle') },
  { value: PACK_UNIT_PALETTE, label: t('components.materialDetail.packUnitPalette') },
  { value: PACK_UNIT_SET, label: t('components.materialDetail.packUnitSet') },
  { value: PACK_UNIT_PAKET, label: t('components.materialDetail.packUnitPaket') },
])

const isMeterStockMaterial = computed(() => isMeterStockUnit(formData.pack_unit))

const sizeLengthFieldLabel = computed(() => {
  const base = t('components.materialDetail.labelLengthCm')
  return isMeterStockMaterial.value ? `${base} *` : base
})

const materialDisplayName = computed(() => {
  const m = material.value
  if (!m?.name) return ''
  return formatMaterialDisplayName(m.name, m.pack_unit, m.pack_size, m.size_length)
})

const showDetailStockUnitSection = computed(() => {
  const m = material.value
  if (!m || m.is_consumable || m.is_food) return false
  if (m.material_type && m.material_type !== 'physical') return false
  return m.tracking_type === 'bulk' || m.tracking_type === 'serialized'
})

const detailPackagingActive = computed(() => isPackagingUnit(formData.pack_unit))

function onDetailStockUnitSaved(updated: Material) {
  applyMaterialSoftUpdate(updated)
  emit('updated', updated)
}

const rentalScopeOptions = computed(() => [
  { value: '', label: t('components.materialDetail.rentalScopeUnset') },
  { value: 'department', label: t('components.materialDetail.rentalScopeDepartment') },
  { value: 'organisation', label: t('components.materialDetail.rentalScopeOrganisation') },
  { value: 'public', label: t('components.materialDetail.rentalScopePublic') },
])

function sortLocale(): string {
  return String(locale.value || 'de').replace('_', '-')
}

function dateLocaleForIntl(): string {
  const s = sortLocale().toLowerCase()
  if (s.startsWith('de')) return 'de-CH'
  if (s.startsWith('en')) return 'en-GB'
  return sortLocale()
}

const DETAIL_QUERY_KEYS = {
  tab: 'tab',
  containerBatch: 'containerBatch',
  containerSearch: 'containerSearch',
  legacyStoredInContainerBatch: 'storedInContainerBatch',
  legacyStoredInSearch: 'storedInSearch',
  usedInSearch: 'usedInSearch',
} as const

const emit = defineEmits<{
  close: []
  updated: [material: Material]
  'open-create-for-composition': [{ parentMaterialId: string }]
}>()

const canManageJsMaterial = computed(() => {
  const role = String(authStore.currentDepartmentRole || '').toLowerCase()
  if (role === 'sa' || role === 'superadmin') return true
  return (authStore.userRoles || []).some((r: string) => r.toLowerCase() === 'role_superadmin')
})

const departmentRole = computed(() => String(authStore.currentDepartmentRole || 'u').toLowerCase())
const isUserMaterialsBrowseOnly = computed(() => isDepartmentBasicMemberRole(departmentRole.value))
const canManageMaterials = computed(() =>
  ['mw', 'dc', 'matwart', 'depchef'].includes(departmentRole.value)
)

const materialPrimaryImageUrl = computed(() => {
  const photos = material.value.photos
  if (photos?.length && photos[0]?.url) return photos[0].url
  return material.value.image_url || null
})

// State
const material = ref<Material>({} as Material)
const batches = ref<any[]>([])
const rentalAmortizationDefaults = ref<RentalAmortizationDefaults>({ ...DEFAULT_RENTAL_AMORTIZATION })
const workshopTickets = ref<WorkshopTicket[]>([])
const workshopTicketsLoading = ref(false)

/** Stückliste für Kombos (Tab „Zusammensetzung“) */
const comboComponentsList = ref<ComboComponent[]>([])
const comboComponentsLoading = ref(false)

/** Konfigurator: Options-Gruppen + Optionen (Weg B, Paket 6) – nur virtuelle Kombo */
const comboOptionsList = ref<ComboOption[]>([])
const comboOptionGroupsList = ref<ComboOptionGroup[]>([])
const showComboOptionsEditor = ref(false)

/** Verwandtes Zubehör (Empfehlung, kein Stücklisten-Teil) */
const relatedAccessoriesList = ref<RelatedAccessory[]>([])
const relatedAccessoriesLoading = ref(false)
const showAddAccessoryModal = ref(false)
const addAccessorySearch = ref('')
const addAccessorySelected = ref<Material | null>(null)
const addAccessorySubmitting = ref(false)
const addAccessoryError = ref('')
const deletingAccessoryId = ref<string | null>(null)
/** Lagerbaum nach Zusammensetzung+Einlagerung neu laden */
const storageTreeRefreshKey = ref(0)

const showAddCompositionModal = ref(false)
const addCompositionSearch = ref('')
const addCompositionSelected = ref<Material | null>(null)
/** Detail-GET mit Lager-Aufteilung für Kappen im Hinzufügen-Dialog */
const addCompositionSourceDetail = ref<Material | null>(null)
const addCompositionStockLocations = ref<MaterialStorageLocationsResponse | null>(null)
const addCompositionQty = ref(1)
const addCompositionRole = ref('')
const addCompositionOptional = ref(false)
const addCompositionSource = ref<ComponentSource>('stock')
const addCompositionMode = ref<'fixed' | 'assigned' | 'on_issue' | 'bulk'>('bulk')
const addCompositionError = ref('')
const addCompositionSubmitting = ref(false)

const showEditCompositionModal = ref(false)
const editCompositionComp = ref<ComboComponent | null>(null)
const editCompositionQty = ref(1)
const editCompositionRole = ref('')
const editCompositionOptional = ref(false)
const editCompositionSource = ref<ComponentSource>('stock')
const editCompositionMode = ref<'fixed' | 'assigned' | 'on_issue' | 'bulk'>('bulk')
const editCompositionBatchId = ref('')
const editCompositionBatches = ref<MaterialBatch[]>([])
const editCompositionBatchesLoading = ref(false)
const editCompositionBaseQty = ref(1)
const editCompositionMaterialFreeStock = ref(0)
const editCompositionSourceDetail = ref<Material | null>(null)
const editCompositionStockLocations = ref<MaterialStorageLocationsResponse | null>(null)
const editLinkedContainerContents = ref<ContainerBatchContentsResponse | null>(null)
const editLinkedContainerContentsLoading = ref(false)
/** Beim Bearbeiten einer normalen Komponente: Menge dieses Artikels bereits im Referenz-Sack. */
const editComponentStoredInLinkedContainer = ref<number | null>(null)
const editCompositionSetAsLinkedContainer = ref(false)
const editCompositionError = ref('')
const editCompositionSubmitting = ref(false)
const deletingCompositionId = ref<string | null>(null)
const pendingRemoveComposition = ref<ComboComponent | null>(null)
const finalizingCombo = ref(false)

const linkedContainerBatchIdForRelease = computed(() => {
  const m = material.value
  if (!m) return null
  return m.linked_container_batch_id || m.linked_container_batch?.id || null
})

const linkedContainerLabelForRelease = computed(() => {
  const linked = material.value?.linked_container_batch
  if (!linked) return material.value?.name || '–'
  const name = (linked.material_name || linked.label || linked.display_label || '').trim()
  return name || '–'
})

/** Name des Referenz-Sacks/Kiste (nur wenn verknüpft) — für Einleitungstexte. */
const physicalComboRefContainerName = computed((): string | null => {
  const linked = material.value?.linked_container_batch
  if (!linked) return null
  const name = (linked.material_name || linked.label || linked.display_label || '').trim()
  return name || null
})

const addCompositionStockCap = computed((): number | null => {
  const m = addCompositionSourceDetail.value || addCompositionSelected.value
  if (!m) return null
  if (addCompositionAllocatesToLinkedCrate.value) {
    const outside = Math.max(0, m.stock_outside_containers ?? 0)
    const inContainers = Math.max(0, m.stock_in_containers ?? 0)
    const movable = outside + inContainers
    if (movable > 0) return movable
    return Math.max(0, Math.floor(m.total_stock ?? 0))
  }
  const loose = typeof m.free_stock === 'number' && Number.isFinite(m.free_stock) ? m.free_stock : m.total_stock
  if (typeof loose !== 'number' || !Number.isFinite(loose)) return null
  return Math.max(0, Math.floor(loose))
})

const addCompositionAllocatesToLinkedCrate = computed(
  () =>
    material.value?.material_type === 'physical_combo' &&
    !!(material.value?.linked_container_batch_id || material.value?.linked_container_batch?.id),
)

/** Bearbeitung der Referenz-Sack/Kiste-Zeile in der Stückliste (nicht Umbuchung in die Kiste selbst). */
const editCompositionEffectiveBatchId = computed((): string => {
  const bid = (editCompositionBatchId.value || '').trim()
  if (bid) return bid
  return (editCompositionComp.value?.component_batch?.id || '').trim()
})

const editCompositionEffectiveBatch = computed((): MaterialBatch | null => {
  const bid = editCompositionEffectiveBatchId.value
  if (!bid) return null
  return editCompositionBatches.value.find((b) => b.id === bid) ?? null
})

/** Sack/Kiste mit zugewiesener Charge kann als Referenz-Behälter der physischen Kombo gesetzt werden. */
const canOfferSetAsLinkedContainer = computed(() => {
  if (material.value?.material_type !== 'physical_combo') return false
  if (isEditCompositionLinkedContainer.value) return false
  if (!editCompositionEffectiveBatchId.value) return false
  const m = editCompositionSourceDetail.value
  const batch = editCompositionEffectiveBatch.value
  if (!m) return false
  if (m.is_container || batch?.is_container) return true
  return m.tracking_type === 'serialized'
})

const editCompositionLinkedContainerCandidateLabel = computed((): string => {
  const comp = editCompositionComp.value
  const batch = editCompositionEffectiveBatch.value
  const name = (comp?.component_material.name || '').trim()
  if (!batch) return name || '–'
  const sn = (batch.serial_number || '').trim()
  if (!name) return sn || '–'
  if (sn && sn !== name) return `${name} (${sn})`
  return name
})

const isEditCompositionLinkedContainer = computed(() => {
  const comp = editCompositionComp.value
  const linkedBatchId = (linkedContainerBatchIdForRelease.value || '').trim()
  if (!comp || !linkedBatchId) return false
  const compBatchId = (comp.component_batch?.id || '').trim()
  if (compBatchId && compBatchId === linkedBatchId) return true
  const linkedMatId = (material.value?.linked_container_batch?.material_id || '').trim()
  return !!(
    linkedMatId &&
    comp.component_material.id === linkedMatId &&
    compBatchId === linkedBatchId
  )
})

type EditLinkedContainerContentRow = {
  materialId: string
  materialName: string
  storedQty: number
  plannedQty: number | null
}

const editLinkedContainerContentRows = computed((): EditLinkedContainerContentRow[] => {
  if (!isEditCompositionLinkedContainer.value) return []
  const linkedBatchId = (linkedContainerBatchIdForRelease.value || '').trim()
  const storedByMat = new Map<string, { name: string; qty: number }>()
  for (const line of editLinkedContainerContents.value?.contents || []) {
    const mid = (line.material_id || '').trim()
    if (!mid) continue
    const prev = storedByMat.get(mid)
    storedByMat.set(mid, {
      name: line.material_name || prev?.name || mid,
      qty: (prev?.qty ?? 0) + (line.qty ?? 0),
    })
  }
  const rows: EditLinkedContainerContentRow[] = []
  const seen = new Set<string>()
  for (const comp of comboComponentsList.value) {
    const mid = comp.component_material.id
    const compBatchId = (comp.component_batch?.id || '').trim()
    if (compBatchId && compBatchId === linkedBatchId) continue
    if (comp.component_material.id === props.materialId) continue
    seen.add(mid)
    const stored = storedByMat.get(mid)
    rows.push({
      materialId: mid,
      materialName: comp.component_material.name,
      storedQty: stored?.qty ?? 0,
      plannedQty: comp.qty,
    })
  }
  for (const [mid, stored] of storedByMat) {
    if (seen.has(mid) || mid === props.materialId) continue
    rows.push({
      materialId: mid,
      materialName: stored.name,
      storedQty: stored.qty,
      plannedQty: null,
    })
  }
  return rows.sort((a, b) => a.materialName.localeCompare(b.materialName, sortLocale()))
})

const addCompositionStockLocationRows = computed((): MaterialStorageLocationRow[] => {
  const rows = addCompositionStockLocations.value?.direct ?? []
  return rows.filter((r) => (r.qty || 0) > 0)
})

const addCompositionTakePreview = computed(() => {
  if (!addCompositionAllocatesToLinkedCrate.value || !addCompositionSourceDetail.value) return null
  const targetId = linkedContainerBatchIdForRelease.value
  if (!targetId) return null
  const qty = Math.max(1, addCompositionQty.value || 1)
  return previewTakeForLinkedCrate(
    addCompositionSourceDetail.value,
    targetId,
    linkedContainerLabelForRelease.value,
    qty,
    containerBatches.value,
  )
})

const editCompositionStockCap = computed((): number | null => {
  if (!editCompositionComp.value || !addCompositionAllocatesToLinkedCrate.value) return null
  if (isEditCompositionLinkedContainer.value) return 1
  return Math.max(1, editCompositionBaseQty.value + Math.max(0, editCompositionMaterialFreeStock.value))
})

const editCompositionQtyIncrease = computed(() =>
  Math.max(0, (editCompositionQty.value || 0) - editCompositionBaseQty.value),
)

const editCompositionStockLocationRows = computed((): MaterialStorageLocationRow[] => {
  const rows = editCompositionStockLocations.value?.direct ?? []
  return rows.filter((r) => (r.qty || 0) > 0)
})

const editCompositionTakePreview = computed(() => {
  if (!addCompositionAllocatesToLinkedCrate.value || !editCompositionSourceDetail.value) return null
  const delta = editCompositionQtyIncrease.value
  if (delta <= 0) return null
  const targetId = linkedContainerBatchIdForRelease.value
  if (!targetId) return null
  return previewTakeForLinkedCrate(
    editCompositionSourceDetail.value,
    targetId,
    linkedContainerLabelForRelease.value,
    delta,
    containerBatches.value,
  )
})

function compositionQtyMin(optional: boolean): number {
  return optional ? 0 : 1
}

function clampEditCompositionQty() {
  const min = compositionQtyMin(editCompositionOptional.value)
  const q = editCompositionQty.value ?? 0
  if (q < min) editCompositionQty.value = min
  if (editCompositionOptional.value && q === 0) return
  const cap = editCompositionStockCap.value
  if (cap === null) return
  if (q > cap) editCompositionQty.value = cap
}

const canSubmitAddComposition = computed(() => {
  if (!addCompositionSelected.value) return false
  const q = addCompositionQty.value ?? 0
  if (addCompositionOptional.value && q === 0) return true
  if (q < 1) return false
  const cap = addCompositionStockCap.value
  if (cap === 0) return false
  if (cap !== null && q > cap) return false
  if (addCompositionTakePreview.value && addCompositionTakePreview.value.remaining > 0) return false
  return true
})

const canSubmitEditComposition = computed(() => {
  if (!editCompositionComp.value) return false
  if (isEditCompositionLinkedContainer.value) return (editCompositionQty.value ?? 0) === 1
  const q = editCompositionQty.value ?? 0
  if (editCompositionOptional.value && q === 0) return true
  if (q < 1) return false
  const cap = editCompositionStockCap.value
  if (cap !== null && q > cap) return false
  if (editCompositionTakePreview.value && editCompositionTakePreview.value.remaining > 0) return false
  return true
})

const comboAssignmentLabels = computed((): Record<string, string> => ({
  fixed: t('components.materialDetail.assignmentFixed'),
  assigned: t('components.materialDetail.assignmentAssigned'),
  on_issue: t('components.materialDetail.assignmentOnIssue'),
  bulk: t('components.materialDetail.assignmentBulk'),
}))

/** Kurzlabels im Tab Zusammensetzung (voller Text per title) – weniger horizontales Scrollen */
const comboAssignmentLabelsShort = computed((): Record<string, string> => ({
  fixed: t('components.materialDetail.assignmentShortFixed'),
  assigned: t('components.materialDetail.assignmentShortAssigned'),
  on_issue: t('components.materialDetail.assignmentShortOnIssue'),
  bulk: t('components.materialDetail.assignmentShortBulk'),
}))

const isComboMaterialView = computed(() => isComboMaterialType(material.value))

/** „optional“ (Zubehör-Toggle) ist nur bei virtueller Kombo sinnvoll; physische Kombo kennt das nicht. */
const isVirtualComboView = computed(() => material.value?.material_type === 'virtual_combo')

/** Kombo-Entwurf („in Bearbeitung“, nicht buchbar). */
const isComboDraft = computed(() => isComboMaterialView.value && material.value?.combo_status === 'draft')

/** Abgeleitete „Konfigurator“-Eigenschaft: virtuelle Kombo mit ≥ 1 Options-Gruppe. */
const isConfigurator = computed(() => isVirtualComboView.value && comboOptionGroupsList.value.length > 0)

/** Editor neu laden (Optionen/Gruppen änderten sich). */
async function reloadComboOptions() {
  await loadMaterial({ preserveComboComponents: true })
}

/** Zeilen für „Anschaffung aus Zusammensetzung“ (Vermietung-Tab, Kombis) */
const comboRentalRows = ref<
  Array<{
    componentId: string
    name: string
    qty: number
    perPieceChf: number | null
    lineChf: number | null
    optional: boolean
  }>
>([])
const comboRentalLoading = ref(false)
const comboRentalError = ref('')
const rentalActivitiesOpen = ref(true)
const rentalPricingOpen = ref(false)
const rentalActivityBookings = ref<MaterialActivityBookingRow[]>([])
const rentalActivityBookingsLoading = ref(false)
const rentalActivityBookingsError = ref('')
const rentalActivityBookingsTotalQty = computed(() =>
  rentalActivityBookings.value.reduce((sum, row) => sum + (row.qty || 0), 0),
)

const acquisitionBasisChf = computed(() => sumAcquisitionBasisFromBatches(batches.value))
const acquisitionPieceCount = computed(() => sumAcquisitionPieceCountFromBatches(batches.value))

const comboRentalHasGap = computed(() => comboRentalRows.value.some((r) => r.lineChf == null))

const rentalAcquisitionBasisChf = computed((): number | null => {
  if (isComboMaterialView.value) {
    let sum = 0
    let has = false
    for (const r of comboRentalRows.value) {
      if (r.lineChf != null) {
        sum += r.lineChf
        has = true
      }
    }
    if (!has) return null
    return roundChfToFiveRappen(sum)
  }
  return acquisitionBasisChf.value
})

const rentalAcquisitionPieceCount = computed(() => {
  if (isComboMaterialView.value) return 1
  return acquisitionPieceCount.value
})

const showRentalAmortizationCalculator = computed(() => {
  const m = material.value
  if (!m?.material_type) return false
  if (m.is_consumable || m.is_food) return false
  if (m.material_type === 'physical') return !!m.tracking_type
  if (m.material_type === 'physical_combo' || m.material_type === 'virtual_combo') return true
  return false
})

const rentalAmortizationContext = computed<'batches' | 'combo'>(() =>
  isComboMaterialView.value ? 'combo' : 'batches'
)

function rentalActivityStatusLabel(status: string): string {
  const key = `activities.status.${status}` as const
  return te(key) ? t(key) : status
}

function rentalBookingKindClass(kind: MaterialActivityBookingRow['booking_kind']): string {
  if (kind === 'issued') return 'rental-booking-kind--issued'
  if (kind === 'draft') return 'rental-booking-kind--draft'
  return 'rental-booking-kind--reserved'
}

function rentalBookingKindLabel(kind: MaterialActivityBookingRow['booking_kind']): string {
  if (kind === 'issued') return t('components.materialDetail.rentalActivityBookingsKindIssued')
  if (kind === 'draft') return t('components.materialDetail.rentalActivityBookingsKindDraft')
  return t('components.materialDetail.rentalActivityBookingsKindReserved')
}

function formatRentalActivityPeriod(row: MaterialActivityBookingRow): string {
  const start = row.usage_start ? formatRentalDateTime(row.usage_start) : ''
  const end = row.usage_end ? formatRentalDateTime(row.usage_end) : ''
  if (start && end) return `${start} – ${end}`
  if (start) return start
  if (end) return end
  return t('components.materialDetail.emDash')
}

function formatRentalDateTime(iso: string): string {
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

async function loadRentalActivityBookings() {
  if (!props.materialId) return
  rentalActivityBookingsLoading.value = true
  rentalActivityBookingsError.value = ''
  try {
    rentalActivityBookings.value = await getMaterialActivityBookings(props.materialId, props.departmentId)
  } catch {
    rentalActivityBookingsError.value = t('components.materialDetail.rentalActivityBookingsError')
    rentalActivityBookings.value = []
  } finally {
    rentalActivityBookingsLoading.value = false
  }
}

async function loadComboRentalBreakdown() {
  if (!props.materialId) return
  const mt = material.value?.material_type
  if (mt !== 'physical_combo' && mt !== 'virtual_combo') return
  comboRentalLoading.value = true
  comboRentalError.value = ''
  try {
    const list = await getComboComponents(props.materialId)
    const sorted = [...list].sort((a, b) => {
      const o = (a.sort_order ?? 0) - (b.sort_order ?? 0)
      if (o !== 0) return o
      return String(a.component_material?.name || '').localeCompare(
        String(b.component_material?.name || ''),
        sortLocale()
      )
    })
    const ids = [...new Set(sorted.map((c) => c.component_material.id))]
    const materialsById = new Map<string, Material>()
    await Promise.all(
      ids.map(async (id) => {
        try {
          materialsById.set(id, await getMaterial(id))
        } catch {
          /* Zeile ohne Chargen/Preis */
        }
      })
    )
    comboRentalRows.value = sorted.map((comp) => {
      const m = materialsById.get(comp.component_material.id)
      const bs = m?.batches
      const basis = sumAcquisitionBasisFromBatches(bs)
      const pieces = sumAcquisitionPieceCountFromBatches(bs)
      let perPieceChf: number | null = null
      if (basis != null && pieces != null && pieces > 0) {
        const pp = basis / pieces
        perPieceChf = Number.isFinite(pp) && pp >= 0 ? pp : null
      }
      return {
        componentId: comp.component_material.id,
        name: comp.component_material.name,
        qty: comp.qty,
        perPieceChf,
        lineChf: comboLineAcquisitionChf(bs, comp.qty),
        optional: comp.is_optional,
      }
    })
  } catch {
    comboRentalError.value = t('components.materialDetail.errComboRentalLoad')
    comboRentalRows.value = []
  } finally {
    comboRentalLoading.value = false
  }
}

function onRentalCalculatorApply(p: { day: string; week: string; month: string }) {
  const rentalChanged =
    formData.rental_price_day !== p.day ||
    formData.rental_price_week !== p.week ||
    formData.rental_price_month !== p.month
  formData.rental_price_day = p.day
  formData.rental_price_week = p.week
  formData.rental_price_month = p.month
  void nextTick(async () => {
    if (rentalChanged) {
      try {
        await Promise.all([
          saveMaterialField('rental_price_day', p.day),
          saveMaterialField('rental_price_week', p.week),
          saveMaterialField('rental_price_month', p.month),
        ])
        toast.success(t('components.materialDetail.toastRentalSuggestionApplied'))
      } catch {
        toast.error(t('components.materialDetail.errSaveMaterial'))
      }
    } else {
      toast.info(t('components.materialDetail.toastRentalPricesUnchanged'))
    }
  })
}
const containerBatches = ref<ContainerBatch[]>([])
/** Für Lagerplatz-Spalte: direkte Zeilen + physische Kombi (Elternlager) */
const materialStorageLocations = ref<MaterialStorageLocationsResponse | null>(null)
const categories = ref<Category[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const isGeneratingPublicCode = ref(false)
const isAutoEnsuringLinkedContainerQr = ref(false)
const activeTab = ref('data')
const containerContentBatchId = ref('')
const containerContentSearch = ref('')
const usedInSearch = ref('')
const syncingFromRoute = ref(false)
const isLoadingContainerBatches = ref(false)
const hasLoadedContainerBatches = ref(false)
const containerContentOverview = ref<StorageOverviewResponse | null>(null)
const isLoadingContainerContentOverview = ref(false)
const isLoadingContainerEditor = ref(false)
const isSavingContainerEditor = ref(false)
const containerEditorMaterialId = ref('')
const containerEditorBatchId = ref('')
const containerEditorOriginal = ref('')
const containerEditorForm = reactive({
  serial_number: '',
  label: '',
  status: 'active',
  notes: '',
})
const { baselines: containerEditorBaselines, syncBaselines: syncContainerEditorBaselines } =
  useFormFieldBaselines(containerEditorForm)
const showAddToContainerModal = ref(false)
const isLoadingSourceMaterial = ref(false)
const isAddingToContainer = ref(false)
const addToContainerError = ref('')
const addToContainerSearch = ref('')
const addToContainerSourceMaterialId = ref('')
const addToContainerSourceMaterial = ref<Material | null>(null)
const addToContainerSourceBatchId = ref('')
const addToContainerSourceAllocationId = ref('')
const addToContainerQty = ref(1)
const addToContainerMaterialCatalog = ref<Material[] | null>(null)
const isLoadingAddToContainerCatalog = ref(false)

type QrActionMode = 'batch' | 'all'
const showQrActionModal = ref(false)
const stockQrPanelExpanded = ref(false)
const qrActionMode = ref<QrActionMode>('batch')
const qrActionLabel = ref('')
const qrActionCode = ref('')
const qrActionUrl = ref('')
const qrActionEntityId = ref('')

// Batch Modal State
const showBatchModal = ref(false)
const editingBatch = ref<MaterialBatch | null>(null)

const editingBatchComboContext = computed(() => {
  const batch = editingBatch.value
  if (!batch) return null
  return resolveBatchComboStorageContext(
    batch,
    materialStorageLocations.value,
    containerBatches.value,
  )
})
const sparePartsCategoryId = ref('')

const isRepairPartMaterial = computed(() => {
  const categoryId = material.value?.category?.id
  return !!categoryId && categoryId === sparePartsCategoryId.value
})
const showSplitModal = ref(false)
const showMoveModal = ref(false)
const moveBatch = ref<MaterialBatch | null>(null)

// History State
const historyEntries = ref<MaterialHistoryEntry[]>([])
const selectedHistoryEntry = ref<MaterialHistoryEntry | null>(null)
const isLoadingHistory = ref(false)

// Used-In State
const usedInEntries = ref<UsedInEntry[]>([])
const isLoadingUsedIn = ref(false)

// Form Data
const formData = reactive({
  name: '',
  description: '',
  barcode_tag: '',
  category_id: '',
  storage_address_id: '',
  manufacturer: '',
  model: '',
  ean: '',
  weight: '',
  color: '',
  size_length: '',
  size_width: '',
  size_height: '',
  warranty_until: '',
  rental_price_day: '',
  rental_price_week: '',
  rental_price_month: '',
  rental_deposit: '',
  rental_lead_days: null as number | null,
  rental_max_days: null as number | null,
  rental_external_allowed: false,
  rental_scope: '' as string,
  rental_requires_approval: false,
  rental_notes: '',
  rental_calc_params: null as RentalCalcParams | null,
  pack_size: null as number | null,
  pack_unit: '',
  pack_weight: '',
  pack_size_length: '',
  pack_size_width: '',
  pack_size_height: '',
  is_container: false,
  is_js_material: false,
  external_source: '',
  sale_price: null as number | null,
  reference_purchase_unit_chf: null as number | null,
  min_stock: null as number | null,
  pack_sale_price_chf: null as number | null,
})

const { baselines: savedFormBaselines, syncBaselines: syncSavedFormBaselines, syncBaselineFor: syncSavedFormBaselineFor } =
  useFormFieldBaselines(formData)

// Original data for change detection
let originalFormData = ''

type MaterialFormField = keyof typeof formData

function emptyToNull(v: unknown) {
  if (v == null) return null
  if (typeof v === 'string' && v.trim() === '') return null
  return v
}

function buildMaterialFieldPayload(field: MaterialFormField, value: unknown, m: Material): Record<string, unknown> {
  const payload: Record<string, unknown> = {}

  switch (field) {
    case 'name':
      payload.name = String(value ?? '')
      break
    case 'description':
      payload.description = emptyToNull(value)
      break
    case 'barcode_tag':
      payload.barcode_tag = emptyToNull(value)
      break
    case 'category_id':
      payload.category_id = emptyToNull(value)
      break
    case 'storage_address_id':
      payload.storage_address_id = emptyToNull(value)
      break
    case 'manufacturer':
    case 'model':
    case 'ean':
    case 'color':
      payload[field] = emptyToNull(value)
      break
    case 'weight':
      payload.weight = normalizeMaterialMetricInput(String(value ?? ''), 'kg')
      break
    case 'size_length':
    case 'size_width':
    case 'size_height':
      payload[field] = normalizeMaterialMetricInput(String(value ?? ''), 'cm')
      break
    case 'warranty_until':
      payload.warranty_until = emptyToNull(value)
      break
    case 'rental_price_day':
    case 'rental_price_week':
    case 'rental_price_month':
    case 'rental_deposit':
      payload[field] = emptyToNull(value)
      break
    case 'rental_lead_days':
    case 'rental_max_days':
      payload[field] = value == null || value === '' ? null : Number(value)
      break
    case 'rental_external_allowed':
    case 'rental_requires_approval':
      payload[field] = !!value
      break
    case 'rental_scope':
      payload.rental_scope = formData.rental_external_allowed ? emptyToNull(value) : null
      break
    case 'rental_notes':
      payload.rental_notes = emptyToNull(value)
      break
    case 'rental_calc_params':
      payload.rental_calc_params = value ?? null
      break
    case 'pack_size':
      payload.pack_size = value == null || value === '' ? null : Number(value)
      break
    case 'pack_unit':
      payload.pack_unit = emptyToNull(value)
      break
    case 'pack_sale_price_chf':
      payload.pack_sale_price_chf =
        value != null && Number(value) > 0 ? String(Number(value)) : null
      break
    case 'pack_weight':
      payload.pack_weight = normalizeMaterialMetricInput(String(value ?? ''), 'kg')
      break
    case 'pack_size_length':
    case 'pack_size_width':
    case 'pack_size_height':
      payload[field] = normalizeMaterialMetricInput(String(value ?? ''), 'cm')
      break
    case 'is_container':
      if (m.tracking_type === 'bulk') payload.is_container = !!value
      break
    case 'is_js_material':
      if (canManageJsMaterial.value) {
        payload.is_js_material = !!value
        payload.external_source = value ? (formData.external_source || 'js_ch') : null
      }
      break
    case 'external_source':
      if (canManageJsMaterial.value) {
        payload.is_js_material = formData.is_js_material
        payload.external_source = formData.is_js_material ? (String(value || 'js_ch')) : null
      }
      break
    case 'sale_price':
      payload.sale_price = value != null && Number(value) > 0 ? String(Number(value)) : null
      break
    case 'reference_purchase_unit_chf':
      payload.reference_purchase_unit_chf =
        value != null && Number(value) > 0 ? String(Number(value)) : null
      break
    case 'min_stock':
      payload.min_stock = value == null || value === '' ? null : Number(value)
      break
    default:
      break
  }

  return payload
}

async function saveMaterialField(field: MaterialFormField, value: unknown): Promise<void> {
  if (!canManageMaterials.value) return
  const m = material.value
  if (!m?.id) return

  if (m.is_consumable || m.is_food) {
    const sp = field === 'sale_price' ? value : formData.sale_price
    const rp = field === 'reference_purchase_unit_chf' ? value : formData.reference_purchase_unit_chf
    if (field === 'sale_price' || field === 'reference_purchase_unit_chf') {
      if (sp == null || Number(sp) <= 0 || rp == null || Number(rp) <= 0) {
        throw new Error(t('components.materialDetail.errConsumablePricesRequired'))
      }
    }
  }

  const payload = buildMaterialFieldPayload(field, value, m)
  if (Object.keys(payload).length === 0) return

  if (field === 'size_length' && isMeterStockUnit(formData.pack_unit)) {
    const cm = payload.size_length as string | null
    if (!parseSizeLengthCm(cm)) {
      throw new Error(t('components.materialDetail.stockUnitLengthRequired'))
    }
    const newName = applyMaterialUnitSuffixToName(formData.name, 'm', null, cm)
    if (newName !== formData.name.trim()) payload.name = newName
  }

  if (field === 'pack_unit' || field === 'pack_size') {
    const pu: string | null =
      field === 'pack_unit'
        ? (() => {
            const s = String(value ?? '').trim()
            return s === '' ? null : s
          })()
        : formData.pack_unit?.trim()
          ? formData.pack_unit.trim()
          : null
    const ps =
      field === 'pack_size'
        ? value == null || value === ''
          ? null
          : Number(value)
        : formData.pack_size
    const sizeLen = isMeterStockUnit(pu ?? formData.pack_unit) ? formData.size_length : null
    const newName = applyMaterialUnitSuffixToName(formData.name, pu, ps, sizeLen)
    if (newName !== formData.name.trim()) payload.name = newName
  }

  const updated = await updateMaterial(props.materialId, payload)
  applyMaterialSoftUpdate(updated, field)
  if (payload.name) {
    assignFormFieldFromMaterial('name', updated)
    syncSavedFormBaselineFor('name')
    patchOriginalFormField('name')
  }
  emit('updated', updated)

  if (activeTab.value === 'history') {
    loadHistory()
  } else {
    historyEntries.value = []
  }
}

async function saveContainerEditorField(
  field: 'serial_number' | 'label' | 'status' | 'notes',
  value: unknown,
): Promise<void> {
  if (!containerEditorMaterialId.value || !containerEditorBatchId.value) return
  const payload: Record<string, unknown> = {}
  if (field === 'serial_number') payload.serial_number = value ? String(value) : null
  if (field === 'label') payload.label = value ? String(value) : null
  if (field === 'status') payload.status = value || 'active'
  if (field === 'notes') payload.notes = value ? String(value) : null

  await updateBatch(containerEditorMaterialId.value, containerEditorBatchId.value, payload)
  containerEditorOriginal.value = JSON.stringify(containerEditorForm)
  syncContainerEditorBaselines()
  await Promise.all([loadContainerBatches(), loadContainerContentOverview()])
  if (containerEditorMaterialId.value === props.materialId) {
    await loadMaterial({ preserveComboComponents: true, silent: true })
  }
}

function normalizeQueryString(value: unknown): string {
  if (Array.isArray(value)) return typeof value[0] === 'string' ? value[0] : ''
  return typeof value === 'string' ? value : ''
}

function mergeAndReplaceQuery(updates: Record<string, string | undefined>) {
  const nextQuery: Record<string, any> = { ...route.query }
  Object.entries(updates).forEach(([key, value]) => {
    if (value && value.trim() !== '') {
      nextQuery[key] = value
    } else {
      delete nextQuery[key]
    }
  })
  const normalizeQueryRecord = (value: Record<string, any>) => {
    const normalized: Record<string, string> = {}
    Object.keys(value).sort().forEach((key) => {
      const str = normalizeQueryString(value[key]).trim()
      if (str) normalized[key] = str
    })
    return normalized
  }
  const currentNormalized = normalizeQueryRecord(route.query as Record<string, any>)
  const nextNormalized = normalizeQueryRecord(nextQuery)
  if (JSON.stringify(currentNormalized) === JSON.stringify(nextNormalized)) return
  router.replace({ path: route.path, query: nextQuery })
}

function resolveContainerBatchById(batchId: string): ContainerBatch | null {
  const id = batchId.trim()
  if (!id) return null
  const fromList = containerBatches.value.find((b) => b.id === id)
  if (fromList) return fromList
  const linked = material.value?.linked_container_batch
  if (linked?.id === id) return containerBatchFromLinkedRef(linked)
  return null
}

function labelForContainerBatchOption(batch: ContainerBatch): string {
  const comboFromApi = (batch.physical_combo_name || '').trim()
  if (comboFromApi) {
    return formatPhysicalComboLinkedContainerLabel(comboFromApi, batch)
  }
  const m = material.value
  const linkedId = (m?.linked_container_batch_id || m?.linked_container_batch?.id || '').trim()
  if (m?.material_type === 'physical_combo' && linkedId && batch.id === linkedId) {
    return formatPhysicalComboLinkedContainerLabel(m.name || '', batch)
  }
  return formatContainerBatchOptionFullLabel(batch)
}

const storedInContainerOptions = computed(() =>
  containerBatches.value
    .map((batch) => ({
      id: batch.id,
      label: labelForContainerBatchOption(batch),
    }))
    .sort((a, b) => a.label.localeCompare(b.label, sortLocale())),
)

/** Physische Kombo, deren Referenz-Kiste gerade gewählt ist (Inhalt nur lesen). */
const selectedContainerPhysicalCombo = computed((): { id: string; name: string } | null => {
  const batchId = (containerContentBatchId.value || '').trim()
  if (!batchId) return null
  const batch = containerBatches.value.find((b) => b.id === batchId)
  if (batch?.physical_combo_id && batch.physical_combo_name) {
    return { id: batch.physical_combo_id, name: batch.physical_combo_name }
  }
  const m = material.value
  if (
    m?.material_type === 'physical_combo' &&
    linkedContainerBatchIdForRelease.value === batchId
  ) {
    return { id: props.materialId, name: m.name || '' }
  }
  return null
})

const canAddItemsToSelectedContainer = computed(
  () => !!containerContentBatchId.value && !selectedContainerPhysicalCombo.value,
)

const hasMaterialContainerContext = computed(() => {
  const ms = materialStorageLocations.value
  if (ms?.direct?.some((r) => (r.container_batch_id || '').trim() !== '')) return true
  if (ms?.via_physical_combo?.some((v) => (v.parent_linked_container_batch_id || '').trim() !== '')) {
    return true
  }
  if (
    material.value?.material_type === 'physical_combo' &&
    !!(material.value.linked_container_batch_id || material.value.linked_container_batch?.id)
  ) {
    return true
  }
  return false
})

/** Tab „Inhalt Kiste/Tasche“ wenn Kistenbezug (eigene Kiste, Lager in Kiste) oder Deep-Link – nicht bei phys. Kombi (Inhalt = Stückliste). */
const showContainerContentTab = computed(() => {
  if (material.value?.material_type === 'physical_combo') return false
  const hasContainerContext =
    !!normalizeQueryString(route.query[DETAIL_QUERY_KEYS.containerBatch]) ||
    !!normalizeQueryString(route.query[DETAIL_QUERY_KEYS.legacyStoredInContainerBatch])
  if (hasContainerContext) return true
  if (hasMaterialContainerContext.value) return true
  if (storedInContainerOptions.value.length > 0) return true
  if (material.value.tracking_type !== 'serialized') return false
  return false
})

// Dynamische Tabs: Kombos fokussiert auf Stückliste & Lager (ohne Bestand/Serien/Verwendet-in-Kette wie Einzelmaterial)
const tabs = computed(() => {
  if (isUserMaterialsBrowseOnly.value) {
    return [{ id: 'data', label: t('components.materialDetail.tabData') }]
  }
  if (isVirtualComboView.value) {
    return [
      { id: 'data', label: t('components.materialDetail.tabData') },
      { id: 'composition', label: t('components.materialDetail.tabComposition') },
      { id: 'history', label: t('components.materialDetail.tabHistory') },
    ]
  }
  if (isComboMaterialView.value) {
    const comboTabs = [
      { id: 'data', label: t('components.materialDetail.tabData') },
      { id: 'stored-in', label: t('components.materialDetail.tabStoredIn') },
      { id: 'composition', label: t('components.materialDetail.tabComposition') },
      { id: 'workshop', label: t('components.materialDetail.tabWorkshop') },
    ]
    if (showContainerContentTab.value) {
      comboTabs.splice(3, 0, {
        id: 'container-content',
        label: t('components.materialDetail.tabContainerContent'),
      })
    }
    if (!material.value.is_consumable && !material.value.is_food) {
      comboTabs.push({ id: 'rental', label: t('components.materialDetail.tabRental') })
    }
    const archCount = archivedBatches.value.length
    comboTabs.push(
      {
        id: 'archive',
        label:
          archCount > 0
            ? t('components.materialDetail.tabArchiveCount', { count: archCount })
            : t('components.materialDetail.tabArchive'),
      },
      { id: 'history', label: t('components.materialDetail.tabHistory') }
    )
    return comboTabs
  }

  const baseTabs = [
    { id: 'data', label: t('components.materialDetail.tabData') },
    { id: 'stock', label: t('components.materialDetail.tabStock') },
    { id: 'stored-in', label: t('components.materialDetail.tabStoredIn') },
  ]
  if (material.value.tracking_type === 'serialized') {
    baseTabs.push({ id: 'serials', label: t('components.materialDetail.tabSerials') })
  }
  if (showContainerContentTab.value) {
    baseTabs.push({ id: 'container-content', label: t('components.materialDetail.tabContainerContent') })
  }
  const usedCount = usedInEntries.value.length
  baseTabs.push({
    id: 'used-in',
    label:
      usedCount > 0
        ? t('components.materialDetail.tabUsedInCount', { count: usedCount })
        : t('components.materialDetail.tabUsedIn'),
  })
  baseTabs.push({ id: 'workshop', label: t('components.materialDetail.tabWorkshop') })
  if (!material.value.is_consumable && !material.value.is_food) {
    baseTabs.push({ id: 'rental', label: t('components.materialDetail.tabRental') })
  }
  const archN = archivedBatches.value.length
  baseTabs.push({
    id: 'archive',
    label:
      archN > 0
        ? t('components.materialDetail.tabArchiveCount', { count: archN })
        : t('components.materialDetail.tabArchive'),
  })
  baseTabs.push({ id: 'history', label: t('components.materialDetail.tabHistory') })
  return baseTabs
})

const tabIds = computed(() => tabs.value.map((tab) => tab.id))

function resolveTabId(rawTab: unknown): string {
  const normalized = normalizeQueryString(rawTab)
  return tabIds.value.includes(normalized) ? normalized : 'data'
}

function setActiveTab(tabId: string) {
  if (!tabIds.value.includes(tabId)) return
  activeTab.value = tabId
}

const selectedContainerBatch = computed(() => {
  if (!containerContentBatchId.value) return null
  return resolveContainerBatchById(containerContentBatchId.value)
})

const containerContentRows = computed(() => {
  const overview = containerContentOverview.value
  const selectedId = containerContentBatchId.value
  if (!overview || !selectedId) return [] as Array<{ materialId: string; materialName: string; qty: number }>
  const grouped = new Map<string, { materialId: string; materialName: string; qty: number }>()
  for (const rack of overview.racks || []) {
    for (const slot of rack.slots || []) {
      for (const item of slot.contents || []) {
        if ((item.container_batch_id || '') !== selectedId) continue
        const existing = grouped.get(item.material_id)
        if (existing) {
          existing.qty += Number(item.qty || 0)
        } else {
          grouped.set(item.material_id, {
            materialId: item.material_id,
            materialName: item.material_name,
            qty: Number(item.qty || 0),
          })
        }
      }
    }
  }
  return Array.from(grouped.values()).sort((a, b) => a.materialName.localeCompare(b.materialName, sortLocale()))
})

const containerEditorDirty = computed(() => {
  if (!containerEditorBatchId.value) return false
  return JSON.stringify(containerEditorForm) !== containerEditorOriginal.value
})

const containerEditorStatusOptions = computed(() => [
  { value: 'active', label: t('components.materialDetail.batchStatusActive') },
  { value: 'defect', label: t('components.materialDetail.batchStatusDefect') },
  { value: 'repair', label: t('components.materialDetail.batchStatusRepair') },
  { value: 'lost', label: t('components.materialDetail.batchStatusLost') },
  { value: 'disposed', label: t('components.materialDetail.batchStatusDisposed') },
])

const addToContainerSourceBatches = computed(() => {
  const batchesList = addToContainerSourceMaterial.value?.batches || []
  return batchesList.filter((batch: any) => (batch.qty || 0) > 0 && batch.status === 'active')
})

const selectedAddToContainerBatch = computed(() => {
  return addToContainerSourceBatches.value.find((batch: any) => String(batch.id) === String(addToContainerSourceBatchId.value)) || null
})

const selectedAddToContainerAllocations = computed((): BatchStorageAllocation[] => {
  const allocs = selectedAddToContainerBatch.value?.allocations
  if (!allocs || !Array.isArray(allocs)) return []
  return allocs
})

const addToContainerMaxQty = computed(() => {
  const allocs = selectedAddToContainerAllocations.value
  if (allocs.length > 0 && addToContainerSourceAllocationId.value) {
    const selected = allocs.find((a) => a.id === addToContainerSourceAllocationId.value)
    if (selected) return Number(selected.qty || 0)
  }
  return Number(selectedAddToContainerBatch.value?.qty || 0)
})

const canSubmitAddToContainer = computed(() => {
  if (!containerContentBatchId.value) return false
  if (!addToContainerSourceMaterialId.value) return false
  if (!selectedAddToContainerBatch.value) return false
  if (selectedAddToContainerAllocations.value.length > 1 && !addToContainerSourceAllocationId.value) return false
  if (addToContainerQty.value < 1) return false
  if (addToContainerQty.value > addToContainerMaxQty.value) return false
  return true
})

// Seriennummern extrahieren (Batches mit is_serialized = true)
const serialBatches = computed(() => {
  return batches.value.filter(b => b.serial_number)
})

const batchesWithPrintableQr = computed(() =>
  (batches.value || []).filter((batch: any) => isPrintableBatchPublicUrl(batch?.public_url))
)

const linkedContainerQrBatch = computed((): MaterialBatch | null => {
  const linked = material.value?.linked_container_batch
  if (!linked?.id) return null
  return {
    id: linked.id,
    qty: 1,
    unit_price: null,
    acquired_on: '',
    expiry_date: null,
    status: 'active',
    batch_type: 'initial',
    is_initial: false,
    label: linked.label,
    notes: null,
    serial_number: linked.serial_number,
    public_code: linked.public_code ?? null,
    public_url: linked.public_url ?? null,
  }
})

const hasLinkedContainerPrintableQr = computed(
  () => isPrintableBatchPublicUrl(linkedContainerQrBatch.value?.public_url),
)

const printableQrRows = computed((): MaterialBatch[] => {
  const rows = [...batchesWithPrintableQr.value]
  const linked = linkedContainerQrBatch.value
  if (
    isPhysicalComboFromLinkedContainer.value &&
    linked &&
    isPrintableBatchPublicUrl(linked.public_url) &&
    !rows.some((r) => r.id === linked.id)
  ) {
    rows.unshift(linked)
  }
  return rows
})

const hasAnyQrForPrint = computed(
  () => batchesWithPrintableQr.value.length > 0 || hasLinkedContainerPrintableQr.value,
)

const showLinkedContainerQrHeader = computed(
  () =>
    !isLoading.value &&
    isPhysicalComboFromLinkedContainer.value &&
    hasLinkedContainerPrintableQr.value,
)

const showEnsureLinkedContainerQrButton = computed(
  () =>
    !isLoading.value &&
    isPhysicalComboFromLinkedContainer.value &&
    !hasLinkedContainerPrintableQr.value,
)

/** Physische Combo, die mit einer konkreten Kisten-Charge verknüpft ist (QR von der Kiste übernommen). */
const isPhysicalComboFromLinkedContainer = computed(
  () =>
    material.value?.material_type === 'physical_combo' &&
    !!(material.value as { linked_container_batch_id?: string | null }).linked_container_batch_id
)

const qrGenerateButtonLabel = computed(() => {
  if (isGeneratingPublicCode.value) return t('components.materialDetail.qrGenLoading')
  if (isPhysicalComboFromLinkedContainer.value) return t('components.materialDetail.qrGenEnsure')
  return t('components.materialDetail.qrGenCreate')
})

const qrGenerateButtonTitle = computed(() => {
  if (isPhysicalComboFromLinkedContainer.value) {
    return t('components.materialDetail.qrTitleLinkedCombo')
  }
  return ''
})

/** Einstieg „QR-Codes drucken“ im Header, wenn mindestens eine Charge eine Etiketten-URL hat. */
const showHeaderQrShortcut = computed(
  () =>
    !isLoading.value &&
    !isVirtualComboView.value &&
    hasAnyQrForPrint.value &&
    !showLinkedContainerQrHeader.value,
)

const showGenerateQrButton = computed(() => {
  if (isLoading.value) return false
  if (isVirtualComboView.value) return false
  if (isPhysicalComboFromLinkedContainer.value) return false

  const materialMissing = String(material.value?.public_code || '').trim() === ''
  if (material.value?.tracking_type !== 'serialized') {
    const batchMissing = (batches.value || []).some(
      (b: any) => String(b?.public_code || '').trim() === ''
    )
    return materialMissing || batchMissing
  }

  const missingSerialCount = serialBatches.value.filter((batch: any) => {
    const serial = String(batch?.serial_number || '').trim()
    if (!serial) return false
    return String(batch?.public_code || '').trim() === ''
  }).length

  /** Nur physische Combo: Anfangsbatch kann ohne Seriennummer sein – dort ein Batch-QR (z. B. Kisten-Referenz). Sonst: serialisiert = nur QR pro Seriennummer. */
  const nonSerialBatchMissingQr =
    material.value?.material_type === 'physical_combo' &&
    (batches.value || []).some((b: any) => {
      if (String(b?.serial_number || '').trim() !== '') return false
      return String(b?.public_code || '').trim() === ''
    })

  return materialMissing || missingSerialCount > 0 || nonSerialBatchMissingQr
})

/** Aus Verkaufspreis pro VE ÷ Stück pro VE (wenn beides gesetzt). */
const packSaleDerivedUnitPrice = computed(() => {
  const pp = formData.pack_sale_price_chf
  const ps = formData.pack_size
  if (pp == null || ps == null) return null
  return unitPriceFromPackSaleChf(Number(pp), Number(ps))
})

const packSaleCalcLine = computed(() => {
  const up = packSaleDerivedUnitPrice.value
  if (up == null) return ''
  const pp = formData.pack_sale_price_chf
  return t('components.materialDetail.packSaleCalcLine', {
    packPrice: pp != null ? Number(pp).toFixed(2) : '—',
    packUnit: formData.pack_unit?.trim() ? formData.pack_unit : t('components.materialDetail.unitGeneric'),
    packSize: formData.pack_size != null ? String(formData.pack_size) : '—',
    unitPrice: up.toFixed(2),
  })
})

function applyPackSaleToUnitSalePrice() {
  const v = packSaleDerivedUnitPrice.value
  if (v == null) return
  formData.sale_price = v
  toast.success(t('components.materialDetail.toastPackSaleApplied'))
}

const statusLabels = computed((): Record<string, string> => ({
  active: t('components.materialDetail.batchStatusActive'),
  defect: t('components.materialDetail.batchStatusDefect'),
  repair: t('components.materialDetail.batchStatusRepair'),
  lost: t('components.materialDetail.batchStatusLost'),
  disposed: t('components.materialDetail.batchStatusDisposed'),
  split_to_serial: t('components.materialDetail.batchStatusSplitToSerial'),
}))

// Archiv-Status: Batches die nicht mehr aktiv im Bestand sind
const archivedStatuses = ['lost', 'disposed', 'split_to_serial']

// Aktive Batches (für Bestand-Tab) – sortiert nach Kaufdatum (neueste zuerst)
// Batches mit qty=0 (z.B. nach Split) ausblenden – bleiben in DB für Historie
const activeBatches = computed(() => {
  return batches.value
    .filter(b => !archivedStatuses.includes(b.status) && (b.qty || 0) > 0)
    .sort((a, b) => (b.acquired_on || '').localeCompare(a.acquired_on || '', sortLocale()))
})

// Archivierte Batches (für Archiv-Tab) – sortiert nach Kaufdatum (neueste zuerst)
const archivedBatches = computed(() => {
  return batches.value
    .filter(b => archivedStatuses.includes(b.status))
    .sort((a, b) => (b.acquired_on || '').localeCompare(a.acquired_on || '', sortLocale()))
})

const splitSourceBatches = computed(() =>
  activeBatches.value.filter((batch) => !batch.serial_number && batch.status === 'active' && (batch.qty || 0) > 0)
)

const stockSortKey = ref<string | null>(null)
const stockSortDir = ref<'asc' | 'desc'>('desc')
const serialSortKey = ref<string | null>(null)
const serialSortDir = ref<'asc' | 'desc'>('desc')
/** batchId → true während PATCH is_container */
const serialIsContainerSaving = reactive<Record<string, boolean>>({})

// Computed
/** Felder ohne AutoSaveField – nur diese lösen den Header-«Speichern»-Button aus */
const MANUAL_SAVE_FIELD_KEYS = [
  'sale_price',
  'reference_purchase_unit_chf',
  'min_stock',
  'pack_sale_price_chf',
  'rental_calc_params',
  'storage_address_id',
] as const satisfies readonly MaterialFormField[]

const MANUAL_PACK_FIELD_KEYS = [
  'pack_size',
  'pack_unit',
] as const satisfies readonly MaterialFormField[]

function pickFormFields(source: Record<string, unknown>, keys: readonly MaterialFormField[]) {
  const picked: Record<string, unknown> = {}
  for (const key of keys) picked[key] = source[key]
  return picked
}

const manualSaveFieldKeys = computed((): MaterialFormField[] => {
  const keys: MaterialFormField[] = [...MANUAL_SAVE_FIELD_KEYS]
  if (material.value?.is_consumable || material.value?.is_food) {
    keys.push(...MANUAL_PACK_FIELD_KEYS)
  }
  return keys
})

const hasManualUnsavedChanges = computed(() => {
  const keys = manualSaveFieldKeys.value
  const original = JSON.parse(originalFormData || '{}') as Record<string, unknown>
  return (
    JSON.stringify(pickFormFields(formData, keys)) !== JSON.stringify(pickFormFields(original, keys))
  )
})

const propertyBadgeText = computed(() => {
  const m = material.value
  if (!m) return ''

  if (m.is_food) return t('components.materialDetail.badgeFood')
  if (m.is_consumable) return t('components.materialDetail.badgeConsumable')

  const mt = m.material_type || 'physical'

  if (mt === 'virtual_combo') return t('components.materialDetail.badgeVirtualCombo')
  if (mt === 'physical_combo') return t('components.materialDetail.badgePhysicalCombo')

  if (mt === 'physical') {
    const tt = m.tracking_type
    if (tt === 'bulk') return t('components.materialDetail.badgePhysicalBulk')
    if (tt === 'serialized') return t('components.materialDetail.badgePhysicalSerialized')
    return t('components.materialDetail.badgePhysical')
  }

  return t('common.material')
})

const availableStock = computed(() => {
  return batches.value
    .filter(b => b.status === 'active')
    .reduce((sum, b) => sum + b.qty, 0)
})

const defectStock = computed(() => {
  return batches.value
    .filter(b => b.status === 'defect' || b.status === 'repair')
    .reduce((sum, b) => sum + b.qty, 0)
})

const archivedStock = computed(() => {
  return archivedBatches.value.reduce((sum, b) => sum + Math.max(0, b.qty || 0), 0)
})

const packUnitCount = computed(() => {
  const size = material.value.pack_size || 0
  if (!size || size <= 0) return 0
  return Math.floor((material.value.total_stock || 0) / size)
})

const packLooseCount = computed(() => {
  const size = material.value.pack_size || 0
  if (!size || size <= 0) return 0
  return (material.value.total_stock || 0) % size
})

const openLossLabel = computed(() => {
  const reports = material.value.open_loss_reports || 0
  const qty = material.value.open_loss_qty || 0
  return t('components.materialDetail.openLossLine', { reports, qty })
})

// Methods
async function loadComboComponentsForTab() {
  if (!props.materialId || !isComboMaterialView.value) return
  comboComponentsLoading.value = true
  try {
    comboComponentsList.value = await getComboComponents(props.materialId)
  } catch (err) {
    console.error(t('components.materialDetail.logErrorComposition'), err)
    toast.error(t('components.materialDetail.errCompositionLoad'))
  } finally {
    comboComponentsLoading.value = false
  }
}

async function compositionMaterialFetcher(query: string) {
  const fetcher = createBasicMaterialLookupFetcher(() => props.departmentId)
  const items = await fetcher(query)
  return items
    .filter((m) => m.id !== props.materialId)
    .map((m) => {
      const existing = comboComponentsList.value.find((c) => c.component_material.id === m.id)
      return existing ? { ...m, _alreadyInCompositionCompId: existing.id } : m
    })
}

function compositionLookupLabel(item: Record<string, unknown>) {
  const name = String(item?.name ?? '')
  if (item?._alreadyInCompositionCompId) {
    return `${name} (${t('components.materialDetail.badgeAlreadyInComposition')})`
  }
  return name
}

function formatCompositionLookupSecondary(item: Record<string, unknown>) {
  const t = item?.tracking_type
  const mt = item?.material_type
  if (!t && !mt) return ''
  return [t, mt].filter(Boolean).join(' · ')
}

function openAddCompositionModal() {
  addCompositionSearch.value = ''
  addCompositionSelected.value = null
  addCompositionSourceDetail.value = null
  addCompositionStockLocations.value = null
  addCompositionQty.value = 1
  addCompositionRole.value = ''
  addCompositionOptional.value = false
  addCompositionSource.value = 'stock'
  addCompositionError.value = ''
  addCompositionMode.value =
    material.value?.material_type === 'virtual_combo' ? 'on_issue' : 'bulk'
  showAddCompositionModal.value = true
}

function clampAddCompositionQty() {
  const min = compositionQtyMin(addCompositionOptional.value)
  const q = addCompositionQty.value ?? 0
  if (q < min) addCompositionQty.value = min
  if (addCompositionOptional.value && q === 0) return
  const cap = addCompositionStockCap.value
  if (cap === null) return
  if (q > cap) addCompositionQty.value = Math.max(min, cap)
  if (cap > 0 && (addCompositionQty.value ?? 0) < 1) addCompositionQty.value = 1
}

function closeAddCompositionModal() {
  showAddCompositionModal.value = false
}

function clearAddCompositionSelection() {
  addCompositionSelected.value = null
  addCompositionSourceDetail.value = null
  addCompositionStockLocations.value = null
  addCompositionSearch.value = ''
}

async function handleCompositionMaterialSelect(item: Record<string, unknown>) {
  const existingCompId = item?._alreadyInCompositionCompId as string | undefined
  if (existingCompId) {
    const comp = comboComponentsList.value.find((c) => c.id === existingCompId)
    if (comp) {
      showAddCompositionModal.value = false
      addCompositionSearch.value = ''
      addCompositionSelected.value = null
      openEditCompositionModal(comp)
      return
    }
  }
  const { _alreadyInCompositionCompId: _a, ...rest } = item as Record<string, unknown>
  void _a
  addCompositionSelected.value = rest as unknown as Material
  addCompositionSourceDetail.value = null
  addCompositionStockLocations.value = null
  try {
    addCompositionSourceDetail.value = await getMaterial(String(rest.id))
  } catch {
    addCompositionSourceDetail.value = addCompositionSelected.value
  }
  if (addCompositionAllocatesToLinkedCrate.value) {
    try {
      addCompositionStockLocations.value = await getMaterialStorageLocations(String(rest.id), props.departmentId)
    } catch {
      addCompositionStockLocations.value = null
    }
  }
  void nextTick(() => clampAddCompositionQty())
}

function emitCreateMaterialForComposition() {
  emit('open-create-for-composition', { parentMaterialId: props.materialId })
}

async function submitAddComposition() {
  if (!addCompositionSelected.value) return
  addCompositionSubmitting.value = true
  addCompositionError.value = ''
  try {
    const maxSort = comboComponentsList.value.reduce(
      (acc, c) => Math.max(acc, c.sort_order ?? 0),
      -1
    )
    const addRole = addCompositionRole.value.trim()
    const addOptional = isVirtualComboView.value && addCompositionOptional.value
    const addQty = addOptional
      ? Math.max(0, Math.floor(Number(addCompositionQty.value) || 0))
      : Math.max(1, addCompositionQty.value || 1)
    await addComboComponent(props.materialId, {
      component_material_id: addCompositionSelected.value.id,
      qty: addQty,
      component_role: addRole === '' ? null : addRole,
      is_optional: addOptional,
      component_source: isVirtualComboView.value ? addCompositionSource.value : 'stock',
      assignment_mode: addCompositionMode.value,
      sort_order: maxSort + 1,
      allocate_to_linked_container: addCompositionAllocatesToLinkedCrate.value,
    })
    toast.success(t('components.materialDetail.toastCompositionAdded'))
    showAddCompositionModal.value = false
    await loadComboComponentsForTab()
    await loadMaterial()
    storageTreeRefreshKey.value += 1
    emit('updated', material.value)
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { error?: string } } }
    addCompositionError.value = ax.response?.data?.error || t('components.materialDetail.errCompositionAdd')
  } finally {
    addCompositionSubmitting.value = false
  }
}

async function finalizeComboNow() {
  if (finalizingCombo.value) return
  finalizingCombo.value = true
  try {
    const updated = await finalizeCombo(props.materialId)
    material.value = updated
    toast.success(t('components.materialDetail.toastComboFinalized'))
    emit('updated', material.value)
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { error?: string } } }
    toast.error(ax.response?.data?.error || t('components.materialDetail.errComboFinalize'))
  } finally {
    finalizingCombo.value = false
  }
}

// ── Verwandtes Zubehör ──
async function loadRelatedAccessories() {
  if (!props.materialId || !isComboMaterialView.value) return
  relatedAccessoriesLoading.value = true
  try {
    relatedAccessoriesList.value = await getRelatedAccessories(props.materialId)
  } catch (err) {
    console.error(t('components.materialDetail.logErrorAccessories'), err)
    toast.error(t('components.materialDetail.errAccessoriesLoad'))
  } finally {
    relatedAccessoriesLoading.value = false
  }
}

function openAddAccessoryModal() {
  addAccessorySearch.value = ''
  addAccessorySelected.value = null
  addAccessoryError.value = ''
  showAddAccessoryModal.value = true
}

function closeAddAccessoryModal() {
  showAddAccessoryModal.value = false
}

async function accessoryMaterialFetcher(query: string) {
  const fetcher = createBasicMaterialLookupFetcher(() => props.departmentId)
  const items = await fetcher(query)
  const linkedIds = new Set(relatedAccessoriesList.value.map((a) => a.accessory_material.id))
  return items
    .filter((m) => m.id !== props.materialId)
    .map((m) => (linkedIds.has(m.id) ? { ...m, _alreadyAccessory: true } : m))
}

function accessoryLookupLabel(item: Record<string, unknown>) {
  const name = String(item?.name ?? '')
  if (item?._alreadyAccessory) {
    return `${name} (${t('components.materialDetail.badgeAlreadyAccessory')})`
  }
  return name
}

function handleAccessorySelect(item: Record<string, unknown>) {
  if (item?._alreadyAccessory) return
  const { _alreadyAccessory: _a, ...rest } = item as Record<string, unknown>
  void _a
  addAccessorySelected.value = rest as unknown as Material
}

function clearAddAccessorySelection() {
  addAccessorySelected.value = null
  addAccessorySearch.value = ''
}

async function submitAddAccessory() {
  if (!addAccessorySelected.value) return
  addAccessorySubmitting.value = true
  addAccessoryError.value = ''
  try {
    await addRelatedAccessory(props.materialId, {
      accessory_material_id: addAccessorySelected.value.id,
    })
    toast.success(t('components.materialDetail.toastAccessoryAdded'))
    showAddAccessoryModal.value = false
    await loadRelatedAccessories()
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { error?: string } } }
    addAccessoryError.value = ax.response?.data?.error || t('components.materialDetail.errAccessoryAdd')
  } finally {
    addAccessorySubmitting.value = false
  }
}

async function confirmDeleteAccessory(acc: RelatedAccessory) {
  const ok = await confirmDialog({
    title: t('components.materialDetail.accessoryRemoveTitle'),
    message: t('components.materialDetail.accessoryRemoveMessage', { name: acc.accessory_material.name }),
    confirmText: t('components.materialDetail.accessoryRemoveConfirm'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  deletingAccessoryId.value = acc.id
  try {
    await deleteRelatedAccessory(props.materialId, acc.id)
    toast.success(t('components.materialDetail.toastAccessoryRemoved'))
    await loadRelatedAccessories()
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { error?: string } } }
    toast.error(ax.response?.data?.error || t('components.materialDetail.errAccessoryRemove'))
  } finally {
    deletingAccessoryId.value = null
  }
}

function formatCompositionBatchOption(b: MaterialBatch) {
  const sn = (b.serial_number || '').trim()
  const lb = (b.label || '').trim()
  const qty = typeof b.qty === 'number' && Number.isFinite(b.qty) ? b.qty : 0
  const datePart = (b.acquired_on || '').trim()
    ? formatDate(b.acquired_on)
    : t('components.materialDetail.batchOptionNoDate')
  let head: string
  if (sn && lb) head = `${sn} · ${lb}`
  else if (sn) head = sn
  else if (lb) head = lb
  else head = t('components.materialDetail.batchOptionUnlabeledLot', { id: b.id.slice(0, 8) })
  return t('components.materialDetail.batchOptionWithDateQty', { date: datePart, label: head, qty })
}

function isCompositionLinkedContainer(comp: ComboComponent): boolean {
  const linkedBatchId = (linkedContainerBatchIdForRelease.value || '').trim()
  if (!linkedBatchId) return false
  return (comp.component_batch?.id || '').trim() === linkedBatchId
}

async function openEditCompositionModal(comp: ComboComponent) {
  editCompositionComp.value = comp
  editCompositionBaseQty.value = comp.qty
  editCompositionQty.value = comp.qty
  editCompositionRole.value = comp.component_role || ''
  editCompositionOptional.value = comp.is_optional
  editCompositionSource.value = comp.component_source ?? 'stock'
  editCompositionMode.value = comp.assignment_mode as 'fixed' | 'assigned' | 'on_issue' | 'bulk'
  editCompositionBatchId.value = comp.component_batch?.id || ''
  editCompositionError.value = ''
  editCompositionBatches.value = []
  editCompositionSourceDetail.value = null
  editCompositionStockLocations.value = null
  editLinkedContainerContents.value = null
  editComponentStoredInLinkedContainer.value = null
  editCompositionSetAsLinkedContainer.value = false
  showEditCompositionModal.value = true
  editCompositionBatchesLoading.value = true
  const linkedBatchId = (linkedContainerBatchIdForRelease.value || '').trim()
  const compBatchId = (comp.component_batch?.id || '').trim()
  const editingLinkedContainer =
    !!linkedBatchId &&
    (compBatchId === linkedBatchId ||
      comp.component_material.id === (material.value?.linked_container_batch?.material_id || '').trim())
  try {
    const m = await getMaterial(comp.component_material.id)
    editCompositionSourceDetail.value = m
    editCompositionMaterialFreeStock.value = Math.max(0, m.free_stock ?? 0)
    editCompositionBatches.value = [...(m.batches || [])].sort((a, b) =>
      (b.acquired_on || '').localeCompare(a.acquired_on || '', sortLocale()),
    )
    if (editingLinkedContainer && linkedBatchId) {
      editLinkedContainerContentsLoading.value = true
      try {
        editLinkedContainerContents.value = await getContainerBatchContents(linkedBatchId)
      } catch {
        editLinkedContainerContents.value = null
      } finally {
        editLinkedContainerContentsLoading.value = false
      }
    } else if (addCompositionAllocatesToLinkedCrate.value) {
      const loads: Promise<void>[] = []
      loads.push(
        getMaterialStorageLocations(comp.component_material.id, props.departmentId)
          .then((res) => {
            editCompositionStockLocations.value = res
          })
          .catch(() => {
            editCompositionStockLocations.value = null
          }),
      )
      if (linkedBatchId) {
        loads.push(
          getContainerBatchContents(linkedBatchId)
            .then((res) => {
              const line = (res.contents || []).find(
                (c) => (c.material_id || '').trim() === comp.component_material.id,
              )
              editComponentStoredInLinkedContainer.value = line?.qty ?? 0
            })
            .catch(() => {
              editComponentStoredInLinkedContainer.value = null
            }),
        )
      }
      await Promise.all(loads)
    }
  } catch {
    editCompositionMaterialFreeStock.value = 0
    editCompositionBatches.value = []
    editCompositionSourceDetail.value = null
    editCompositionStockLocations.value = null
    editLinkedContainerContents.value = null
    editComponentStoredInLinkedContainer.value = null
  } finally {
    editCompositionBatchesLoading.value = false
  }
}

function closeEditCompositionModal() {
  showEditCompositionModal.value = false
  editCompositionComp.value = null
}

watch(showEditCompositionModal, (open, prev) => {
  if (prev && !open) editCompositionComp.value = null
})

watch(editCompositionEffectiveBatchId, (bid) => {
  if (!bid) editCompositionSetAsLinkedContainer.value = false
})

function mergeComboComponentInList(updated: ComboComponent) {
  comboComponentsList.value = comboComponentsList.value.map((c) => (c.id === updated.id ? updated : c))
}

async function submitEditComposition() {
  const comp = editCompositionComp.value
  if (!comp) return

  if (editCompositionSetAsLinkedContainer.value) {
    const existingId = (linkedContainerBatchIdForRelease.value || '').trim()
    const newId = editCompositionEffectiveBatchId.value
    if (!newId) {
      editCompositionError.value = t('components.materialDetail.errSetRefContainerNeedsBatch')
      return
    }
    if (existingId && existingId !== newId) {
      const ok = await confirmDialog({
        title: t('components.materialDetail.confirmReplaceRefContainerTitle'),
        message: t('components.materialDetail.confirmReplaceRefContainerMessage', {
          current: linkedContainerLabelForRelease.value,
          next: editCompositionLinkedContainerCandidateLabel.value,
        }),
        confirmText: t('components.materialDetail.confirmReplaceRefContainerAction'),
        cancelText: t('common.cancel'),
        variant: 'warning',
      })
      if (!ok) return
    }
  }

  editCompositionSubmitting.value = true
  editCompositionError.value = ''
  try {
    const roleTrimmed = editCompositionRole.value.trim()
    const editOptional = isVirtualComboView.value && editCompositionOptional.value
    const payload: UpdateComboComponentRequest = {
      qty: editOptional
        ? Math.max(0, Math.floor(Number(editCompositionQty.value) || 0))
        : Math.max(1, editCompositionQty.value || 1),
      component_role: roleTrimmed === '' ? null : roleTrimmed,
      is_optional: editOptional,
      component_source: isVirtualComboView.value ? editCompositionSource.value : 'stock',
      assignment_mode: editCompositionMode.value,
    }
    if (editCompositionBatches.value.length > 0) {
      const bid = editCompositionBatchId.value
      payload.component_batch_id =
        bid && editCompositionBatches.value.some((b) => b.id === bid) ? bid : null
    }
    if (editCompositionSetAsLinkedContainer.value) {
      payload.set_as_linked_container = true
      payload.qty = 1
      payload.allocate_to_linked_container = false
    } else {
      payload.allocate_to_linked_container =
        addCompositionAllocatesToLinkedCrate.value && !isEditCompositionLinkedContainer.value
    }
    const updated = await updateComboComponent(props.materialId, comp.id, payload)
    mergeComboComponentInList(updated)
    toast.success(t('components.materialDetail.toastCompositionSaved'))
    closeEditCompositionModal()
    await loadComboComponentsForTab()
    await loadMaterial({ preserveComboComponents: true })
    storageTreeRefreshKey.value += 1
    emit('updated', material.value)
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { error?: string } } }
    editCompositionError.value = ax.response?.data?.error || t('components.materialDetail.errCompositionSave')
  } finally {
    editCompositionSubmitting.value = false
  }
}

async function confirmDeleteComposition(comp: ComboComponent) {
  if (addCompositionAllocatesToLinkedCrate.value && linkedContainerBatchIdForRelease.value) {
    pendingRemoveComposition.value = comp
    return
  }
  const name = comp.component_material.name
  const qty = comp.qty
  const ok = await confirmDialog({
    title: t('components.materialDetail.confirmRemoveCompositionTitle'),
    message: t('components.materialDetail.confirmRemoveCompositionMessageVirtual', { name, qty }),
    confirmText: t('components.materialDetail.confirmRemoveCompositionAction'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  await executeRemoveComposition(comp)
}

function cancelRemoveCompositionRelease() {
  pendingRemoveComposition.value = null
}

async function executeRemoveCompositionWithRelease(payload: DeleteComboComponentRequest) {
  const comp = pendingRemoveComposition.value
  if (!comp) return
  const ok = await executeRemoveComposition(comp, payload)
  if (ok) {
    pendingRemoveComposition.value = null
  }
}

async function executeRemoveComposition(
  comp: ComboComponent,
  releasePayload?: DeleteComboComponentRequest,
): Promise<boolean> {
  const name = comp.component_material.name
  const qty = comp.qty
  const isPhysicalCrate = !!releasePayload
  deletingCompositionId.value = comp.id
  try {
    await deleteComboComponent(props.materialId, comp.id, releasePayload)
    toast.success(
      isPhysicalCrate
        ? t('components.materialDetail.toastCompositionRemoved', { name, qty })
        : t('components.materialDetail.toastCompositionRemovedVirtual', { name, qty }),
    )
    await loadComboComponentsForTab()
    await loadMaterial()
    storageTreeRefreshKey.value += 1
    emit('updated', material.value)
    return true
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { error?: string } } }
    toast.error(ax.response?.data?.error || t('components.materialDetail.errCompositionDelete'))
    return false
  } finally {
    deletingCompositionId.value = null
  }
}

function openComponentMaterialDetail(componentMaterialId: string) {
  if (!componentMaterialId) return
  router.push({ path: `/${props.departmentId}/materials/${componentMaterialId}` })
}

async function loadMaterialStorageLocations() {
  if (!props.materialId || !props.departmentId) {
    materialStorageLocations.value = null
    return
  }
  try {
    materialStorageLocations.value = await getMaterialStorageLocations(props.materialId, props.departmentId)
  } catch {
    materialStorageLocations.value = null
  }
}

watch(
  () => props.materialId,
  () => {
    hasLoadedContainerBatches.value = false
    containerBatches.value = []
    containerContentBatchId.value = ''
  },
)

async function uploadMaterialPhotoFile(file: File) {
  if (!material.value.id) {
    throw new Error(t('components.materialDetail.uploadPhotoError'))
  }
  return uploadMaterialPhoto(material.value.id, file)
}

async function importMaterialPhotoFromUrlFn(url: string) {
  if (!material.value.id) {
    throw new Error(t('components.materialDetail.uploadPhotoError'))
  }
  return importMaterialPhotoFromUrl(material.value.id, url)
}

function onMaterialPhotoUploaded(result: unknown) {
  const data = result as { photos: Material['photos']; image_url: string | null }
  material.value = {
    ...material.value,
    photos: data.photos,
    image_url: data.image_url,
  }
  toast.success(t('media.uploadSuccess'))
}

function onMaterialPhotoError(message: string) {
  toast.error(message || t('media.uploadError'))
}

async function maybeAutoEnsureComboPublicCodes(data: Material) {
  if (data.material_type !== 'physical_combo' && data.material_type !== 'virtual_combo') return data
  if (isAutoEnsuringLinkedContainerQr.value || isGeneratingPublicCode.value) return data

  const linked = data.linked_container_batch
  const missingLinkedSackQr =
    data.material_type === 'physical_combo' &&
    !!(linked?.material_id || '').trim() &&
    !isPrintableBatchPublicUrl(linked?.public_url)
  const missingComponentBatchQr = (data.combo_components ?? []).some(
    (comp) => comp.component_batch?.id && !isPrintableBatchPublicUrl(comp.component_batch?.public_url),
  )
  if (!missingLinkedSackQr && !missingComponentBatchQr) return data

  isAutoEnsuringLinkedContainerQr.value = true
  try {
    await ensureMaterialPublicCode(props.materialId)
    return await getMaterial(props.materialId)
  } catch (err) {
    console.warn(t('components.materialDetail.logErrorPublicQr'), err)
    return data
  } finally {
    isAutoEnsuringLinkedContainerQr.value = false
  }
}

async function loadMaterial(opts?: { preserveComboComponents?: boolean; silent?: boolean }) {
  if (!opts?.silent) isLoading.value = true
  try {
    let data = await getMaterial(props.materialId)
    data = await maybeAutoEnsureComboPublicCodes(data)
    material.value = data
    batches.value = data.batches || []
    if (!opts?.preserveComboComponents) {
      if (data.material_type === 'physical_combo' || data.material_type === 'virtual_combo') {
        comboComponentsList.value = data.combo_components ?? []
      } else {
        comboComponentsList.value = []
      }
    }
    if (data.material_type === 'virtual_combo') {
      comboOptionsList.value = data.combo_options ?? []
      comboOptionGroupsList.value = data.combo_option_groups ?? []
    } else {
      comboOptionsList.value = []
      comboOptionGroupsList.value = []
    }

    if (opts?.silent) {
      updateMaterialDetailTabLabel(data)
      return
    }

    populateFormData(data)
    originalFormData = JSON.stringify(formData)
    syncSavedFormBaselines()

    updateMaterialDetailTabLabel(data)
    await loadMaterialStorageLocations()
    void nextTick(() => {
      if (
        activeTab.value === 'rental' &&
        (data.material_type === 'physical_combo' || data.material_type === 'virtual_combo')
      ) {
        void loadComboRentalBreakdown()
      }
    })
  } catch (err) {
    console.error(t('components.materialDetail.logErrorLoadMaterial'), err)
    if (!opts?.silent) materialStorageLocations.value = null
  } finally {
    if (!opts?.silent) isLoading.value = false
  }
}

async function generateMaterialPublicCode() {
  if (!props.materialId || isGeneratingPublicCode.value) return
  if (isVirtualComboView.value) return
  const linkedKisteCombo = isPhysicalComboFromLinkedContainer.value
  isGeneratingPublicCode.value = true
  try {
    await ensureMaterialPublicCode(props.materialId)
    await loadMaterial()
    emit('updated', material.value)
    if (linkedKisteCombo) {
      toast.success(t('components.materialDetail.toastQrLinkedCombo'))
    } else {
      toast.success(t('components.materialDetail.toastQrCreated'))
    }
  } catch (err: any) {
    console.error(t('components.materialDetail.logErrorPublicQr'), err)
    toast.error(
      err?.response?.data?.error ||
        (linkedKisteCombo ? t('components.materialDetail.errQrCreateLinked') : t('components.materialDetail.errQrCreateGeneric'))
    )
  } finally {
    isGeneratingPublicCode.value = false
  }
}

async function ensureLinkedContainerPublicCode() {
  const sackMaterialId = (material.value?.linked_container_batch?.material_id || '').trim()
  if (!sackMaterialId || isGeneratingPublicCode.value) return
  isGeneratingPublicCode.value = true
  try {
    await ensureMaterialPublicCode(sackMaterialId)
    await loadMaterial()
    emit('updated', material.value)
    toast.success(t('components.materialDetail.toastQrLinkedSackCreated'))
    if (hasLinkedContainerPrintableQr.value) {
      openLinkedContainerQrModal()
    }
  } catch (err: unknown) {
    const ax = err as { response?: { data?: { error?: string } } }
    console.error(t('components.materialDetail.logErrorPublicQr'), err)
    toast.error(ax.response?.data?.error || t('components.materialDetail.errQrCreateLinked'))
  } finally {
    isGeneratingPublicCode.value = false
  }
}

function openLinkedContainerQrModal() {
  const batch = linkedContainerQrBatch.value
  if (!batch) return
  openQrActionModalForBatch(batch)
}

async function loadCategories() {
  try {
    categories.value = await getCategories(props.departmentId)
  } catch (err) {
    console.error(t('components.materialDetail.logErrorLoadCategories'), err)
  }
}

async function handleCategoryReloadFromPicker(saved?: Category) {
  await loadCategories()
  if (saved?.id) {
    formData.category_id = saved.id
  }
}

async function loadContainerBatches() {
  if (isLoadingContainerBatches.value) return
  isLoadingContainerBatches.value = true
  try {
    containerBatches.value = await getContainerBatches(props.departmentId, {
      forMaterialId: props.materialId || undefined,
    })
    hasLoadedContainerBatches.value = true
  } catch (err) {
    console.error(t('components.materialDetail.logErrorLoadContainerBatches'), err)
    containerBatches.value = []
    hasLoadedContainerBatches.value = false
  } finally {
    isLoadingContainerBatches.value = false
  }
}

async function ensureContainerBatchesLoaded() {
  if (hasLoadedContainerBatches.value) return
  await loadContainerBatches()
}

async function loadContainerContentOverview() {
  if (!props.departmentId) return
  isLoadingContainerContentOverview.value = true
  try {
    containerContentOverview.value = await getStorageOverview(props.departmentId)
  } catch (err) {
    console.error(t('components.materialDetail.logErrorLoadContainerContent'), err)
    containerContentOverview.value = { racks: [] }
  } finally {
    isLoadingContainerContentOverview.value = false
  }
}

function openMaterialById(materialId: string) {
  if (!materialId) return
  router.push({ path: `/${props.departmentId}/materials/${materialId}` })
}

function populateFormData(m: Material) {
  formData.name = m.name || ''
  formData.description = m.description || ''
  formData.barcode_tag = m.barcode_tag || ''
  formData.category_id = m.category?.id || ''
  formData.storage_address_id = m.storage_address?.id || ''
  formData.manufacturer = m.manufacturer || ''
  formData.model = m.model || ''
  formData.ean = m.ean || ''
  formData.weight = normalizeMaterialMetricInput(m.weight, 'kg') ?? ''
  formData.color = m.color || ''
  formData.size_length = normalizeMaterialMetricInput(m.size_length, 'cm') ?? ''
  formData.size_width = normalizeMaterialMetricInput(m.size_width, 'cm') ?? ''
  formData.size_height = normalizeMaterialMetricInput(m.size_height, 'cm') ?? ''
  formData.warranty_until = m.warranty_until || ''
  formData.rental_price_day = m.rental_price_day || ''
  formData.rental_price_week = m.rental_price_week || ''
  formData.rental_price_month = m.rental_price_month || ''
  formData.rental_deposit = m.rental_deposit || ''
  formData.rental_lead_days = m.rental_lead_days || null
  formData.rental_max_days = m.rental_max_days || null
  formData.rental_external_allowed = m.rental_external_allowed || false
  formData.rental_scope = m.rental_scope || ''
  formData.rental_requires_approval = m.rental_requires_approval || false
  formData.rental_notes = m.rental_notes || ''
  formData.rental_calc_params = m.rental_calc_params ? { ...m.rental_calc_params } : null
  formData.pack_size = m.pack_size || null
  formData.pack_unit = m.pack_unit || ''
  formData.pack_weight = normalizeMaterialMetricInput(m.pack_weight, 'kg') ?? ''
  formData.pack_size_length = normalizeMaterialMetricInput(m.pack_size_length, 'cm') ?? ''
  formData.pack_size_width = normalizeMaterialMetricInput(m.pack_size_width, 'cm') ?? ''
  formData.pack_size_height = normalizeMaterialMetricInput(m.pack_size_height, 'cm') ?? ''
  formData.is_container = m.is_container ?? false
  formData.is_js_material = m.is_js_material || false
  formData.external_source = m.external_source || ''
  formData.sale_price = parseMaterialPriceNum(m.sale_price)
  formData.reference_purchase_unit_chf = parseMaterialPriceNum(m.reference_purchase_unit_chf)
  formData.min_stock = m.min_stock ?? null
  formData.pack_sale_price_chf = parseMaterialPriceNum(m.pack_sale_price_chf)
}

function parseMaterialPriceNum(v: string | null | undefined): number | null {
  if (v == null || v === '') return null
  const n = Number(v)
  return Number.isFinite(n) ? n : null
}

function assignFormFieldFromMaterial(field: MaterialFormField, m: Material) {
  switch (field) {
    case 'name':
      formData.name = m.name || ''
      break
    case 'description':
      formData.description = m.description || ''
      break
    case 'barcode_tag':
      formData.barcode_tag = m.barcode_tag || ''
      break
    case 'category_id':
      formData.category_id = m.category?.id || ''
      break
    case 'storage_address_id':
      formData.storage_address_id = m.storage_address?.id || ''
      break
    case 'manufacturer':
      formData.manufacturer = m.manufacturer || ''
      break
    case 'model':
      formData.model = m.model || ''
      break
    case 'ean':
      formData.ean = m.ean || ''
      break
    case 'weight':
      formData.weight = normalizeMaterialMetricInput(m.weight, 'kg') ?? ''
      break
    case 'color':
      formData.color = m.color || ''
      break
    case 'size_length':
      formData.size_length = normalizeMaterialMetricInput(m.size_length, 'cm') ?? ''
      break
    case 'size_width':
      formData.size_width = normalizeMaterialMetricInput(m.size_width, 'cm') ?? ''
      break
    case 'size_height':
      formData.size_height = normalizeMaterialMetricInput(m.size_height, 'cm') ?? ''
      break
    case 'warranty_until':
      formData.warranty_until = m.warranty_until || ''
      break
    case 'rental_price_day':
      formData.rental_price_day = m.rental_price_day || ''
      break
    case 'rental_price_week':
      formData.rental_price_week = m.rental_price_week || ''
      break
    case 'rental_price_month':
      formData.rental_price_month = m.rental_price_month || ''
      break
    case 'rental_deposit':
      formData.rental_deposit = m.rental_deposit || ''
      break
    case 'rental_lead_days':
      formData.rental_lead_days = m.rental_lead_days || null
      break
    case 'rental_max_days':
      formData.rental_max_days = m.rental_max_days || null
      break
    case 'rental_external_allowed':
      formData.rental_external_allowed = m.rental_external_allowed || false
      break
    case 'rental_scope':
      formData.rental_scope = m.rental_scope || ''
      break
    case 'rental_requires_approval':
      formData.rental_requires_approval = m.rental_requires_approval || false
      break
    case 'rental_notes':
      formData.rental_notes = m.rental_notes || ''
      break
    case 'rental_calc_params':
      formData.rental_calc_params = m.rental_calc_params ? { ...m.rental_calc_params } : null
      break
    case 'pack_size':
      formData.pack_size = m.pack_size || null
      break
    case 'pack_unit':
      formData.pack_unit = m.pack_unit || ''
      break
    case 'pack_weight':
      formData.pack_weight = normalizeMaterialMetricInput(m.pack_weight, 'kg') ?? ''
      break
    case 'pack_size_length':
      formData.pack_size_length = normalizeMaterialMetricInput(m.pack_size_length, 'cm') ?? ''
      break
    case 'pack_size_width':
      formData.pack_size_width = normalizeMaterialMetricInput(m.pack_size_width, 'cm') ?? ''
      break
    case 'pack_size_height':
      formData.pack_size_height = normalizeMaterialMetricInput(m.pack_size_height, 'cm') ?? ''
      break
    case 'is_container':
      formData.is_container = m.is_container ?? false
      break
    case 'is_js_material':
      formData.is_js_material = m.is_js_material || false
      break
    case 'external_source':
      formData.external_source = m.external_source || ''
      break
    case 'sale_price':
      formData.sale_price = parseMaterialPriceNum(m.sale_price)
      break
    case 'reference_purchase_unit_chf':
      formData.reference_purchase_unit_chf = parseMaterialPriceNum(m.reference_purchase_unit_chf)
      break
    case 'min_stock':
      formData.min_stock = m.min_stock ?? null
      break
    case 'pack_sale_price_chf':
      formData.pack_sale_price_chf = parseMaterialPriceNum(m.pack_sale_price_chf)
      break
  }
}

function patchOriginalFormField(field: MaterialFormField) {
  const parsed = JSON.parse(originalFormData || '{}') as Record<string, unknown>
  parsed[field] = formData[field]
  originalFormData = JSON.stringify(parsed)
}

function updateMaterialDetailTabLabel(m: Material) {
  detailTabsStore.addOrUpdateTab({
    id: props.materialId,
    type: 'material',
    label: m.name || t('components.materialDetail.tabFallbackMaterialName', { id: props.materialId }),
    departmentId: props.departmentId,
    path: `/${props.departmentId}/materials/${props.materialId}`,
  })
}

function applyMaterialSoftUpdate(updated: Material, touchedField?: MaterialFormField) {
  material.value = updated
  if (updated.batches) batches.value = updated.batches
  updateMaterialDetailTabLabel(updated)

  if (touchedField) {
    assignFormFieldFromMaterial(touchedField, updated)
    syncSavedFormBaselineFor(touchedField)
    patchOriginalFormField(touchedField)
    return
  }

  populateFormData(updated)
  originalFormData = JSON.stringify(formData)
  syncSavedFormBaselines()
}

function getCategoryPath(): string {
  if (!formData.category_id && !material.value.category?.id) return ''
  return getCategoryPathById(formData.category_id || material.value.category?.id || '')
}

function getCategoryPathById(categoryId: string): string {
  if (!categoryId) return ''
  const mapById = new Map(categories.value.map((c) => [c.id, c]))
  const visited = new Set<string>()
  const parts: string[] = []
  let currentId: string | null = categoryId

  while (currentId) {
    if (visited.has(currentId)) break
    visited.add(currentId)
    const current = mapById.get(currentId)
    if (!current) break
    parts.push(current.name)
    currentId = current.parent_id || null
  }

  return parts.reverse().join(' → ')
}

type ReadOnlyField = { label: string; value: string }
type ReadOnlySection = { title: string; fields: ReadOnlyField[] }

function hasReadOnlyValue(value: unknown): boolean {
  if (value === null || value === undefined) return false
  if (typeof value === 'string') return value.trim() !== ''
  if (typeof value === 'number') return !Number.isNaN(value)
  if (typeof value === 'boolean') return value
  return true
}

function pushReadOnlyField(
  fields: ReadOnlyField[],
  label: string,
  value: unknown,
  format?: (v: unknown) => string
) {
  if (!hasReadOnlyValue(value)) return
  fields.push({ label, value: format ? format(value) : String(value) })
}

const userReadOnlySections = computed((): ReadOnlySection[] => {
  const m = material.value
  const sections: ReadOnlySection[] = []

  const materialFields: ReadOnlyField[] = []
  pushReadOnlyField(materialFields, t('components.materialDetail.labelNameDb'), m?.name)
  pushReadOnlyField(materialFields, t('components.materialDetail.labelCode'), m?.barcode_tag)
  pushReadOnlyField(materialFields, t('components.materialDetail.labelCategory'), getCategoryPath())
  pushReadOnlyField(materialFields, t('common.manufacturer'), m?.manufacturer)
  pushReadOnlyField(materialFields, t('components.materialDetail.labelModel'), m?.model)
  if (materialFields.length > 0) {
    sections.push({ title: t('common.material'), fields: materialFields })
  }

  const propertyFields: ReadOnlyField[] = []
  pushReadOnlyField(propertyFields, t('components.materialDetail.propPhysicalVirtual'), propertyBadgeText.value)
  if (m?.is_js_material) {
    pushReadOnlyField(
      propertyFields,
      t('components.materialDetail.labelSource'),
      t('components.materialDetail.sourceJs')
    )
    pushReadOnlyField(propertyFields, t('components.materialDetail.labelExternalSource'), m?.external_source)
  }
  if (propertyFields.length > 0) {
    sections.push({ title: t('components.materialDetail.sectionProperties'), fields: propertyFields })
  }

  const detailFields: ReadOnlyField[] = []
  pushReadOnlyField(detailFields, t('components.materialDetail.labelEan'), m?.ean)
  pushReadOnlyField(detailFields, t('components.materialDetail.labelWeightKg'), m?.weight, (v) => `${v} kg`)
  pushReadOnlyField(detailFields, t('components.materialDetail.labelColor'), m?.color)
  pushReadOnlyField(detailFields, t('components.materialDetail.labelLengthCm'), m?.size_length, (v) => `${v} cm`)
  pushReadOnlyField(detailFields, t('components.materialDetail.labelWidthCm'), m?.size_width, (v) => `${v} cm`)
  pushReadOnlyField(detailFields, t('components.materialDetail.labelHeightCm'), m?.size_height, (v) => `${v} cm`)
  pushReadOnlyField(detailFields, t('components.materialDetail.labelWarranty'), m?.warranty_until)
  pushReadOnlyField(detailFields, t('components.materialDetail.labelDescription'), m?.description)
  if (detailFields.length > 0) {
    sections.push({ title: t('components.materialDetail.sectionDetails'), fields: detailFields })
  }

  if (
    m &&
    !m.is_consumable &&
    !m.is_food &&
    (m.tracking_type === 'bulk' || m.tracking_type === 'serialized') &&
    (!m.material_type || m.material_type === 'physical')
  ) {
    const stockUnitFields: ReadOnlyField[] = []
    pushReadOnlyField(
      stockUnitFields,
      t('components.materialDetail.labelStockUnitReadonly'),
      formatStockUnitSettingLabel(m.pack_unit, m.pack_size),
    )
    pushReadOnlyField(
      stockUnitFields,
      t('components.materialDetail.labelNameDb'),
      formatMaterialDisplayName(m.name, m.pack_unit, m.pack_size, m.size_length),
    )
    sections.push({ title: t('components.materialDetail.sectionStockUnit'), fields: stockUnitFields })
  }

  if (!m?.is_consumable && !m?.is_food && m?.pack_size && m?.pack_unit && isPackagingUnit(m.pack_unit)) {
    const packFields: ReadOnlyField[] = []
    pushReadOnlyField(packFields, t('components.materialDetail.labelPiecesPerUnit'), m.pack_size)
    pushReadOnlyField(packFields, t('components.materialDetail.labelDesignation'), m.pack_unit)
    sections.push({ title: t('components.materialDetail.sectionPackaging'), fields: packFields })
  }

  const packDimFields: ReadOnlyField[] = []
  pushReadOnlyField(packDimFields, t('components.materialDetail.labelPackWeightKg'), m?.pack_weight, (v) => `${v} kg`)
  pushReadOnlyField(packDimFields, t('components.materialDetail.labelPackLengthCm'), m?.pack_size_length, (v) => `${v} cm`)
  pushReadOnlyField(packDimFields, t('components.materialDetail.labelPackWidthCm'), m?.pack_size_width, (v) => `${v} cm`)
  pushReadOnlyField(packDimFields, t('components.materialDetail.labelPackHeightCm'), m?.pack_size_height, (v) => `${v} cm`)
  if (packDimFields.length > 0) {
    sections.push({ title: t('components.materialDetail.sectionPackDimensions'), fields: packDimFields })
  }

  if (m?.is_consumable || m?.is_food) {
    const costFields: ReadOnlyField[] = []
    pushReadOnlyField(
      costFields,
      t('components.materialDetail.labelSalePrice'),
      m?.sale_price,
      (v) => `${v} ${t('components.materialDetail.currencyFr')}`
    )
    pushReadOnlyField(
      costFields,
      t('components.materialDetail.labelRefPurchase'),
      m?.reference_purchase_unit_chf,
      (v) => `${v} ${t('components.materialDetail.currencyFr')}`
    )
    if (m?.min_stock != null && m.min_stock > 0) {
      pushReadOnlyField(costFields, t('components.materialDetail.labelMinStock'), m.min_stock)
    }
    if (costFields.length > 0) {
      sections.push({ title: t('components.materialDetail.sectionCosts'), fields: costFields })
    }
  }

  return sections
})

watch(isUserMaterialsBrowseOnly, (browseOnly) => {
  if (browseOnly) activeTab.value = 'data'
}, { immediate: true })

function formatDate(dateStr: string): string {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString(dateLocaleForIntl())
}

function formatPiecesAtLength(count: number, per: string, _total: string): string {
  return t('components.materialDetail.stockQtyPiecesAtLength', { count, per })
}

function formatMaterialStockQtyPrimary(qty: number | null | undefined): string {
  const n = Number(qty)
  if (!Number.isFinite(n)) return `0 ${getStockUnitLabel(material.value?.pack_unit)}`
  return formatStockQty(
    n,
    material.value?.pack_unit,
    material.value?.pack_size,
    material.value?.size_length,
    formatPiecesAtLength,
  )
}

/** @deprecated use formatMaterialStockQtyPrimary */
function formatMaterialStockQty(qty: number | null | undefined): string {
  return formatMaterialStockQtyPrimary(qty)
}

type BatchLocationEntry = {
  text: string
  containerMaterialId: string | null
  containerBatchId: string | null
  containerSearchSeed: string
}

/** Standort · Gestell (Regal) · Fach – aus API-Feldern; fehlende Teile werden weggelassen. */
function formatAllocationLocationLine(a: {
  storage_address_name?: string | null
  rack?: { name?: string } | null
  slot?: { name?: string } | null
  rack_name?: string
  slot_name?: string
}): string {
  const standort = (a.storage_address_name || '').trim()
  const gestell = (a.rack?.name || a.rack_name || '').trim()
  const fach = (a.slot?.name || a.slot_name || '').trim()
  const parts: string[] = []
  if (standort) parts.push(standort)
  if (gestell) parts.push(gestell)
  if (fach) parts.push(fach)
  return parts.length ? parts.join(' · ') : '-'
}

function formatStorageLocationRow(loc: MaterialStorageLocationRow): string {
  const line = formatAllocationLocationLine({
    storage_address_name: loc.storage_address_name,
    rack: loc.rack_name ? { name: loc.rack_name } : null,
    slot: loc.slot_name ? { name: loc.slot_name } : null,
  })
  if (line !== '-') return line
  const label = (loc.location_label || '').trim()
  if (label) {
    const addr = (loc.storage_address_name || '').trim()
    return addr ? `${addr} · ${label}` : label
  }
  return '-'
}

const buildBatchLocationEntries = (batch: any): BatchLocationEntry[] => {
  const allocations = batch?.allocations
  if (allocations && Array.isArray(allocations) && allocations.length > 0) {
    const resolveContainerBatch = (containerBatchId?: string | null) => {
      if (!containerBatchId) return null
      return containerBatches.value.find((b: any) => b.id === containerBatchId) || batches.value.find((b: any) => b.id === containerBatchId) || null
    }

    return allocations
      .map((a: any) => {
        const loc = formatAllocationLocationLine(a)
        const fallbackContainer = resolveContainerBatch(a.container_batch_id)
        const resolvedContainer = a.container_batch
          ? { ...fallbackContainer, ...a.container_batch }
          : fallbackContainer
        const containerLabel = resolvedContainer?.label || resolvedContainer?.serial_number
        const containerMaterial = resolvedContainer?.material_name
        const containerMaterialId = resolvedContainer?.material_id || null
        const containerBatchId = resolvedContainer?.id || a.container_batch_id || null
        const containerLoc = resolvedContainer
          ? formatAllocationLocationLine({
              storage_address_name: resolvedContainer.storage_address_name,
              rack: resolvedContainer.rack,
              slot: resolvedContainer.slot,
            })
          : ''
        const containerSearchSeed = String(containerLabel || containerMaterial || '').trim()
        if (containerLabel) {
          const materialSuffix = containerMaterial && containerMaterial !== containerLabel ? ` – ${containerMaterial}` : ''
          const detail = (containerLoc || loc) ? ` (${containerLoc || loc})` : ''
          return {
            text: t('components.materialDetail.batchLocationInLabelledContainer', {
              qty: a.qty,
              label: containerLabel,
              materialSuffix,
              detail,
            }),
            containerMaterialId,
            containerBatchId,
            containerSearchSeed,
          }
        }
        if (a.container_batch_id) {
          const fallbackName = containerMaterial || t('components.materialDetail.fallbackContainerShort')
          const detail = (containerLoc || loc) ? ` (${containerLoc || loc})` : ''
          return {
            text: t('components.materialDetail.batchLocationQtyInNamedPlace', {
              qty: a.qty,
              name: fallbackName,
              detail,
            }),
            containerMaterialId,
            containerBatchId,
            containerSearchSeed,
          }
        }
        return {
          text: t('components.materialDetail.batchLocationQtyInPlace', { qty: a.qty, place: loc }),
          containerMaterialId: null,
          containerBatchId: null,
          containerSearchSeed: '',
        }
      })
      .filter((entry: BatchLocationEntry | null): entry is BatchLocationEntry => !!entry)
  }

  const ms = materialStorageLocations.value
  const directRows = ms?.direct?.filter((r) => String(r.batch_id) === String(batch?.id)) ?? []
  if (directRows.length > 0) {
    return directRows.map((row) => ({
      text: t('components.materialDetail.batchLocationQtyInPlace', {
        qty: row.qty,
        place: formatStorageLocationRow(row),
      }),
      containerMaterialId: null,
      containerBatchId: null,
      containerSearchSeed: '',
    }))
  }

  // Nur Felder am Batch (ohne Material-Hauptlager), damit Kombi-Zeilen nicht verdeckt werden
  const fromBatchOnly = formatAllocationLocationLine({
    storage_address_name: batch?.storage_address_name ?? null,
    rack: batch?.rack,
    slot: batch?.slot,
    rack_name: batch?.rack_name,
    slot_name: batch?.slot_name,
  })
  if (fromBatchOnly !== '-') {
    const q = batch?.qty ?? 1
    return [
      {
        text: t('components.materialDetail.batchLocationQtyInPlace', { qty: q, place: fromBatchOnly }),
        containerMaterialId: null,
        containerBatchId: null,
        containerSearchSeed: '',
      },
    ]
  }

  // Physische Kombi: gleicher realer Ort wie die Kombi-Einheit (API liefert Gestell/Fach).
  // Nur Stücklisten-Zeilen, die dieser Charge zugeordnet sind (component_batch_id), damit jede
  // Seriennummer den passenden Eltern-Kombi-Ort sieht — nicht alle Kombis für alle Zeilen.
  const combos = ms?.via_physical_combo ?? []
  const bid = batch?.id != null ? String(batch.id) : ''
  if (combos.length > 0) {
    const out: BatchLocationEntry[] = []
    for (const block of combos) {
      const linked = block.component_batch_id != null && String(block.component_batch_id).trim() !== ''
      if (linked && String(block.component_batch_id) !== bid) {
        continue
      }
      if (!linked) {
        // Ohne zugewiesene Serien-Charge: keinen Eltern-Lagerplatz jeder Instanz zuschreiben
        continue
      }
      const parentName = (block.parent_name || '').trim() || t('components.materialDetail.fallbackParentComboName')
      for (const loc of block.locations || []) {
        const locText = formatStorageLocationRow(loc)
        if (locText === '-' || !locText.trim()) continue
        const q = Number(batch?.qty ?? loc.qty ?? 1) || 1
        out.push({
          text: t('components.materialDetail.batchLocationViaCombo', {
            qty: q,
            place: locText,
            parent: parentName,
          }),
          containerMaterialId: null,
          containerBatchId: null,
          containerSearchSeed: '',
        })
      }
    }
    if (out.length > 0) {
      return out
    }
  }

  // Fallback: Material-Hauptlagerort + ggf. Batch-Rack
  const fallbackLine = formatAllocationLocationLine({
    storage_address_name: batch?.storage_address_name ?? material.value?.storage_address?.name,
    rack: batch?.rack,
    slot: batch?.slot,
    rack_name: batch?.rack_name,
    slot_name: batch?.slot_name,
  })
  if (fallbackLine !== '-') {
    const q = batch?.qty ?? 1
    return [
      {
        text: `${q} in ${fallbackLine}`,
        containerMaterialId: null,
        containerBatchId: null,
        containerSearchSeed: '',
      },
    ]
  }
  return [{ text: '-', containerMaterialId: null, containerBatchId: null, containerSearchSeed: '' }]
}

function getBatchLocationSortText(batch: any): string {
  const entries = buildBatchLocationEntries(batch)
  return (entries[0]?.text || '').trim()
}

function parseBatchUnitPrice(batch: any): number {
  const v = batch?.unit_price
  if (v == null || v === '') return Number.NaN
  const n = Number(String(v).replace(',', '.'))
  return Number.isFinite(n) ? n : Number.NaN
}

function compareStockBatches(a: any, b: any, key: string): number {
  switch (key) {
    case 'acquired_on':
      return (a.acquired_on || '').localeCompare(b.acquired_on || '', sortLocale())
    case 'qty':
      return (a.qty || 0) - (b.qty || 0)
    case 'label':
      return (a.label || '').localeCompare(b.label || '', sortLocale(), { sensitivity: 'base' })
    case 'unit_price': {
      const pa = parseBatchUnitPrice(a)
      const pb = parseBatchUnitPrice(b)
      const na = Number.isFinite(pa) ? pa : -Infinity
      const nb = Number.isFinite(pb) ? pb : -Infinity
      return na - nb
    }
    case 'location':
      return getBatchLocationSortText(a).localeCompare(getBatchLocationSortText(b), sortLocale(), { sensitivity: 'base' })
    case 'status':
      return (a.status || '').localeCompare(b.status || '', sortLocale())
    case 'notes':
      return (a.notes || '').localeCompare(b.notes || '', sortLocale(), { sensitivity: 'base' })
    default:
      return 0
  }
}

function compareSerialBatches(a: any, b: any, key: string): number {
  switch (key) {
    case 'serial_number':
      return (a.serial_number || '').localeCompare(b.serial_number || '', sortLocale(), { sensitivity: 'base', numeric: true })
    case 'public_code':
      return (a.public_code || '').localeCompare(b.public_code || '', sortLocale(), { sensitivity: 'base' })
    case 'label':
      return (a.label || '').localeCompare(b.label || '', sortLocale(), { sensitivity: 'base' })
    case 'is_container':
      return (a.is_container ? 1 : 0) - (b.is_container ? 1 : 0)
    case 'acquired_on':
      return (a.acquired_on || '').localeCompare(b.acquired_on || '', sortLocale())
    case 'location':
      return getBatchLocationSortText(a).localeCompare(getBatchLocationSortText(b), sortLocale(), { sensitivity: 'base' })
    case 'status':
      return (a.status || '').localeCompare(b.status || '', sortLocale())
    case 'notes':
      return (a.notes || '').localeCompare(b.notes || '', sortLocale(), { sensitivity: 'base' })
    default:
      return 0
  }
}

const sortedActiveBatches = computed(() => {
  const rows = [...activeBatches.value]
  const key = stockSortKey.value
  if (!key) return rows
  const factor = stockSortDir.value === 'asc' ? 1 : -1
  rows.sort((a, b) => factor * compareStockBatches(a, b, key))
  return rows
})

const sortedSerialBatches = computed(() => {
  const rows = [...serialBatches.value]
  const key = serialSortKey.value
  if (!key) return rows
  const factor = serialSortDir.value === 'asc' ? 1 : -1
  rows.sort((a, b) => factor * compareSerialBatches(a, b, key))
  return rows
})

function toggleStockSort(key: string) {
  if (stockSortKey.value === key) {
    stockSortDir.value = stockSortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    stockSortKey.value = key
    stockSortDir.value = 'desc'
  }
}

function toggleSerialSort(key: string) {
  if (serialSortKey.value === key) {
    serialSortDir.value = serialSortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    serialSortKey.value = key
    serialSortDir.value = 'desc'
  }
}

async function onSerialBatchIsContainerChange(batch: MaterialBatch, value: boolean) {
  const id = String(batch.id || '')
  if (!id || serialIsContainerSaving[id]) return
  if (!!batch.is_container === value) return
  serialIsContainerSaving[id] = true
  try {
    await updateBatch(props.materialId, id, { is_container: value })
    await loadMaterial()
    await ensureContainerBatchesLoaded()
    emit('updated', material.value)
  } catch (e: any) {
    toast.error(e?.response?.data?.error || t('components.materialDetail.errContainerFlagSave'))
  } finally {
    delete serialIsContainerSaving[id]
  }
}

function openLinkedContainerStoredInTab() {
  mergeAndReplaceQuery({ [DETAIL_QUERY_KEYS.tab]: 'stored-in' })
}

function openContainerMaterial(materialId: string, containerBatchId?: string | null, containerSearchSeed?: string) {
  if (!materialId) return
  const query: Record<string, string> = {
    [DETAIL_QUERY_KEYS.tab]: 'container-content',
  }
  // Prefer exact container instance selection via dropdown (batch id).
  if (containerBatchId) query[DETAIL_QUERY_KEYS.containerBatch] = containerBatchId
  else if (containerSearchSeed) query[DETAIL_QUERY_KEYS.containerSearch] = containerSearchSeed
  router.push({ path: `/${props.departmentId}/materials/${materialId}`, query })
}

async function loadContainerEditorForSelectedBatch() {
  const selected = selectedContainerBatch.value
  const inferredMaterialId = selected?.material_id
    || (selected?.id && batches.value.some((b: any) => String(b.id) === String(selected.id)) ? props.materialId : '')
  if (!selected?.id || !inferredMaterialId) {
    containerEditorMaterialId.value = ''
    containerEditorBatchId.value = ''
    containerEditorOriginal.value = ''
    containerEditorForm.serial_number = ''
    containerEditorForm.label = ''
    containerEditorForm.status = 'active'
    containerEditorForm.notes = ''
    return
  }
  isLoadingContainerEditor.value = true
  try {
    const m = await getMaterial(inferredMaterialId)
    const batch = (m.batches || []).find((b: any) => String(b.id) === String(selected.id))
    if (!batch) {
      containerEditorMaterialId.value = ''
      containerEditorBatchId.value = ''
      containerEditorOriginal.value = ''
      return
    }
    containerEditorMaterialId.value = inferredMaterialId
    containerEditorBatchId.value = batch.id
    containerEditorForm.serial_number = batch.serial_number || ''
    containerEditorForm.label = batch.label || ''
    containerEditorForm.status = batch.status || 'active'
    containerEditorForm.notes = batch.notes || ''
    containerEditorOriginal.value = JSON.stringify(containerEditorForm)
    syncContainerEditorBaselines()
  } catch (err) {
    console.error(t('components.materialDetail.logErrorLoadContainerEditor'), err)
  } finally {
    isLoadingContainerEditor.value = false
  }
}

async function saveContainerEditor() {
  if (!containerEditorMaterialId.value || !containerEditorBatchId.value) return
  if (!containerEditorDirty.value) return
  isSavingContainerEditor.value = true
  try {
    await updateBatch(containerEditorMaterialId.value, containerEditorBatchId.value, {
      serial_number: containerEditorForm.serial_number || null,
      label: containerEditorForm.label || null,
      status: containerEditorForm.status,
      notes: containerEditorForm.notes || null,
    })
    containerEditorOriginal.value = JSON.stringify(containerEditorForm)
    syncContainerEditorBaselines()
    await Promise.all([loadContainerBatches(), loadContainerContentOverview()])
    if (containerEditorMaterialId.value === props.materialId) {
      await loadMaterial()
    }
    toast.success(t('components.materialDetail.toastContainerSaved'))
  } catch (err: any) {
    console.error(t('components.materialDetail.logErrorSaveContainerEditor'), err)
    toast.error(err?.response?.data?.error || t('components.materialDetail.errSaveGeneric'))
  } finally {
    isSavingContainerEditor.value = false
  }
}

function resetAddToContainerState() {
  addToContainerError.value = ''
  addToContainerSearch.value = ''
  addToContainerSourceMaterialId.value = ''
  addToContainerSourceMaterial.value = null
  addToContainerSourceBatchId.value = ''
  addToContainerSourceAllocationId.value = ''
  addToContainerQty.value = 1
}

async function ensureAddToContainerMaterialCatalog() {
  if (addToContainerMaterialCatalog.value || isLoadingAddToContainerCatalog.value) return
  isLoadingAddToContainerCatalog.value = true
  try {
    const all = await getMaterials(props.departmentId)
    addToContainerMaterialCatalog.value = all
      .filter((m) => (m.total_stock || 0) > 0)
      .sort((a, b) => (a.name || '').localeCompare(b.name || '', sortLocale()))
  } catch (err) {
    console.error(t('components.materialDetail.logErrorLoadMaterialCatalog'), err)
    addToContainerMaterialCatalog.value = []
  } finally {
    isLoadingAddToContainerCatalog.value = false
  }
}

async function addToContainerMaterialFetcher(rawQuery: string) {
  const query = String(rawQuery || '').trim().toLocaleLowerCase(sortLocale())
  if (!query) return []
  await ensureAddToContainerMaterialCatalog()
  const list = addToContainerMaterialCatalog.value || []
  return list
    .filter((m) => String(m.name || '').toLocaleLowerCase(sortLocale()).includes(query))
    .slice(0, 5)
}

async function selectAddToContainerMaterial(mat: Material) {
  addToContainerSourceMaterialId.value = mat.id
  addToContainerSearch.value = mat.name || ''
  await loadAddToContainerSourceMaterial()
}

function handleAddToContainerLookupSelect(mat: any) {
  selectAddToContainerMaterial(mat as Material)
}

function formatAddToContainerLookupLabel(item: any) {
  return String(item?.name || '').trim() || t('contacts.unnamed')
}

function formatAddToContainerLookupSecondary(item: any) {
  const stock = Number(item?.total_stock ?? item?.totalStock ?? 0)
  return `${stock} / ${stock}`
}

function clearAddToContainerMaterialSelection() {
  addToContainerSourceMaterialId.value = ''
  addToContainerSourceMaterial.value = null
  addToContainerSourceBatchId.value = ''
  addToContainerSourceAllocationId.value = ''
  addToContainerQty.value = 1
  addToContainerSearch.value = ''
}

function handleAddToContainerBatchChange() {
  const firstAlloc = selectedAddToContainerAllocations.value[0]
  addToContainerSourceAllocationId.value = firstAlloc?.id || ''
  addToContainerQty.value = 1
}

async function loadAddToContainerSourceMaterial() {
  if (!addToContainerSourceMaterialId.value) {
    addToContainerSourceMaterial.value = null
    addToContainerSourceBatchId.value = ''
    addToContainerSourceAllocationId.value = ''
    addToContainerQty.value = 1
    return
  }
  isLoadingSourceMaterial.value = true
  addToContainerError.value = ''
  try {
    const materialDetails = await getMaterial(addToContainerSourceMaterialId.value)
    addToContainerSourceMaterial.value = materialDetails
    const firstBatch = (materialDetails.batches || []).find((batch: any) => (batch.qty || 0) > 0 && batch.status === 'active')
    addToContainerSourceBatchId.value = firstBatch?.id || ''
    addToContainerSourceAllocationId.value = firstBatch?.allocations?.[0]?.id || ''
    addToContainerQty.value = 1
  } catch (err: any) {
    console.error(t('components.materialDetail.logErrorLoadSourceForContainer'), err)
    addToContainerError.value = err?.response?.data?.error || t('components.materialDetail.errLoadSourceMaterial')
    addToContainerSourceMaterial.value = null
  } finally {
    isLoadingSourceMaterial.value = false
  }
}

function formatAllocationLocationInline(a: BatchStorageAllocation): string {
  const cb = a.container_batch
  const containerLabel = cb?.label || cb?.serial_number
  if (containerLabel) {
    const materialSuffix =
      cb?.material_name && cb.material_name !== containerLabel ? ` – ${cb.material_name}` : ''
    return t('components.materialDetail.allocationLineContainer', { label: containerLabel, extra: materialSuffix })
  }
  const rackName = a.rack?.name || a.rack_id
  const slotName = a.slot?.name || a.slot_id
  if (slotName) {
    return t('components.materialDetail.allocationLineRackSlot', { rack: rackName, slot: slotName })
  }
  return t('components.materialDetail.allocationLineRackOnly', { rack: String(rackName || '-') })
}

function formatSourceBatchOption(batch: any): string {
  const serial = (batch.serial_number || '').trim()
  const label = (batch.label || '').trim()
  const head =
    label ||
    serial ||
    t('components.materialDetail.batchOptionUnlabeledLot', { id: String(batch.id).slice(-6) })
  const serialSuffix = serial && serial !== head ? ` · ${serial}` : ''
  return `${head}${serialSuffix} · ${t('components.materialDetail.qtyPieces', { qty: batch.qty })}`
}

async function openAddToContainerModal() {
  if (!containerContentBatchId.value) return
  if (showAddToContainerModal.value) return
  resetAddToContainerState()
  showAddToContainerModal.value = true
}

function closeAddToContainerModal() {
  showAddToContainerModal.value = false
  resetAddToContainerState()
}

watch(showAddToContainerModal, (open, prev) => {
  if (prev && !open) resetAddToContainerState()
})

function openQrActionModalForBatch(batch: any) {
  qrActionMode.value = 'batch'
  qrActionEntityId.value = String(batch?.id || '')
  qrActionLabel.value = batchPrintLine(batch)
  qrActionCode.value = String(batch?.public_code || '')
  qrActionUrl.value = String(batch?.public_url || '')
  showQrActionModal.value = true
}

/** Kontextabhängige Druckzeile unter dem QR: S/N bei Seriennummer, sonst Charge (Label oder ID-Endung). */
function batchPrintLine(batch: any): string {
  const serial = String(batch?.serial_number || '').trim()
  if (serial) return t('components.materialDetail.qrPrintLineSerial', { value: serial })
  const label = String(batch?.label || '').trim()
  if (label) return t('components.materialDetail.qrPrintLineBatch', { value: label })
  return t('components.materialDetail.qrPrintLineBatchFallback', {
    suffix: String(batch?.id || '').slice(-6),
  })
}

function openStockTabWithQrPanel() {
  prepareQrActionAll()
  if (tabIds.value.includes('stock')) {
    setActiveTab('stock')
  } else if (isComboMaterialView.value) {
    setActiveTab('data')
  }
  stockQrPanelExpanded.value = true
}

function prepareQrActionAll() {
  qrActionMode.value = 'all'
  qrActionEntityId.value = ''
  qrActionLabel.value = material.value?.name || t('common.material')
  qrActionCode.value = ''
  qrActionUrl.value = ''
}

async function handleQrAddAllToPrintCart() {
  prepareQrActionAll()
  await handleQrAddToPrintCart()
}

async function handleQrPrintAllFromPanel() {
  prepareQrActionAll()
  await handleQrPrint()
}

function closeQrActionModal() {
  showQrActionModal.value = false
}

async function handleQrAddToPrintCart() {
  if (!props.departmentId) {
    toast.error(t('components.materialDetail.errNoDepartment'))
    return
  }

  const materialName = material.value?.name || t('components.materialDetail.fallbackMaterialDisplayName')

  if (qrActionMode.value === 'all') {
    const payloads: Array<{
      department_id: string
      entity_type: string
      entity_id: string
      label: string
      public_code?: string | null
      public_url: string
    }> = []

    for (const batch of printableQrRows.value) {
      const url = String(batch?.public_url || '').trim()
      if (!isPrintableBatchPublicUrl(url)) continue
      payloads.push({
        department_id: props.departmentId,
        entity_type: 'batch',
        entity_id: String(batch?.id || ''),
        label: t('components.materialDetail.qrCartLabel', { material: materialName, line: batchPrintLine(batch) }),
        public_code: String(batch?.public_code || '') || null,
        public_url: url,
      })
    }

    if (payloads.length === 0) {
      toast.info(t('components.materialDetail.toastPrintCartNoCodes'))
      return
    }

    try {
      const result = await addPrintCartItemsBulk(props.departmentId, payloads)
      toast.success(
        t('components.materialDetail.toastPrintCartUpdated', {
          created: result.created_count,
          skipped: result.skipped_count,
        })
      )
      closeQrActionModal()
    } catch (err: any) {
      toast.error(err?.response?.data?.error || t('components.materialDetail.errPrintCartUpdate'))
    }
    return
  }

  const url = qrActionUrl.value.trim()
  const entityId = qrActionEntityId.value.trim()
  if (!url || !entityId) {
    toast.info(t('components.materialDetail.toastNoValidQrLink'))
    return
  }

  try {
    const result = await addPrintCartItem({
      department_id: props.departmentId,
      entity_type: 'batch',
      entity_id: entityId,
      label: t('components.materialDetail.qrCartLabel', { material: materialName, line: qrActionLabel.value || 'QR' }),
      public_code: qrActionCode.value || null,
      public_url: url,
    })
    toast.success(
      result.created ? t('components.materialDetail.toastPrintCartAdded') : t('components.materialDetail.toastPrintCartAlready')
    )
    closeQrActionModal()
  } catch (err: any) {
    toast.error(err?.response?.data?.error || t('components.materialDetail.errPrintCartAdd'))
  }
}

function escapeHtml(raw: string): string {
  return String(raw || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')
}

async function buildPrintRowsForAllQrs(): Promise<Array<{ line: string; code: string; qrDataUrl: string }>> {
  const rows: Array<{ line: string; code: string; qrDataUrl: string }> = []
  const tasks: Array<Promise<void>> = []

  for (const batch of printableQrRows.value) {
    const url = String(batch?.public_url || '').trim()
    if (!isPrintableBatchPublicUrl(url)) continue
    const line = batchPrintLine(batch)
    const code = String(batch?.public_code || '').trim()
    tasks.push((async () => {
      const qrDataUrl = await QRCode.toDataURL(url, { width: 220, margin: 1 })
      rows.push({ line, code, qrDataUrl })
    })())
  }

  await Promise.all(tasks)
  return rows
}

async function handleQrPrint() {
  const materialName = material.value?.name || t('components.materialDetail.fallbackMaterialDisplayName')
  if (qrActionMode.value === 'all') {
    const rows = await buildPrintRowsForAllQrs()
    if (rows.length === 0) {
      toast.info(t('components.materialDetail.toastNoQrToPrint'))
      return
    }
    const cards = rows
      .map((row) => `
        <div class="card">
          <img src="${row.qrDataUrl}" alt="${escapeHtml(t('components.materialDetail.qrAlt'))}" />
          <div class="material">${escapeHtml(materialName)}</div>
          <div class="title">${escapeHtml(row.line)}</div>
          <div class="code">${escapeHtml(row.code || '-')}</div>
        </div>
      `)
      .join('')
    printHtmlDocument(`<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>${escapeHtml(
      t('components.materialDetail.qrPrintAllDocTitle', {
        name: material.value?.name || t('common.material'),
      })
    )}</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 18px; }
    h1 { margin: 0 0 14px; font-size: 18px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; }
    .card { border: 1px solid #d1d5db; border-radius: 10px; padding: 10px; text-align: center; page-break-inside: avoid; }
    img { width: 160px; height: 160px; object-fit: contain; }
    .material { margin-top: 8px; font-weight: 700; font-size: 14px; }
    .title { margin-top: 3px; font-size: 12px; color: #374151; }
    .code { margin-top: 3px; font-family: monospace; color: #4b5563; font-size: 11px; }
  </style>
</head>
<body>
  <h1>${escapeHtml(
      t('components.materialDetail.qrPrintAllDocHeading', {
        name: material.value?.name || t('common.material'),
      })
    )}</h1>
  <div class="grid">${cards}</div>
</body>
</html>`)
    closeQrActionModal()
    return
  }

  const url = qrActionUrl.value.trim()
  if (!url) {
    toast.info(t('components.materialDetail.toastNoPublicLink'))
    return
  }
  const qrDataUrl = await QRCode.toDataURL(url, { width: 300, margin: 1 })
  printHtmlDocument(`<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>${escapeHtml(t('components.materialDetail.qrPrintSingleDocTitle', { name: qrActionLabel.value }))}</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .card { max-width: 360px; border: 1px solid #d1d5db; border-radius: 10px; padding: 14px; text-align: center; }
    img { width: 240px; height: 240px; object-fit: contain; }
    .material { margin-top: 10px; font-weight: 700; font-size: 15px; }
    .title { margin-top: 4px; font-size: 13px; color: #374151; }
    .code { margin-top: 4px; font-family: monospace; color: #4b5563; font-size: 12px; }
  </style>
</head>
<body>
  <div class="card">
    <img src="${qrDataUrl}" alt="${escapeHtml(t('components.materialDetail.qrAlt'))}" />
    <div class="material">${escapeHtml(materialName)}</div>
    <div class="title">${escapeHtml(qrActionLabel.value)}</div>
    <div class="code">${escapeHtml(qrActionCode.value || '-')}</div>
  </div>
</body>
</html>`)
  closeQrActionModal()
}

async function submitAddToContainer() {
  if (!canSubmitAddToContainer.value || !selectedAddToContainerBatch.value) return
  if (
    containerContentBatchId.value &&
    !(await physicalComboWarningStore.confirmContainerMove([containerContentBatchId.value]))
  ) {
    return
  }
  isAddingToContainer.value = true
  addToContainerError.value = ''
  try {
    await moveBatchQuantity(addToContainerSourceMaterialId.value, selectedAddToContainerBatch.value.id, {
      from_allocation_id: addToContainerSourceAllocationId.value || null,
      to_container_batch_id: containerContentBatchId.value,
      qty: addToContainerQty.value,
    })
    closeAddToContainerModal()
    await loadContainerContentOverview()
    await loadContainerEditorForSelectedBatch()
    await loadContainerBatches()
    await loadMaterial()
    toast.success(t('components.materialDetail.toastAddedToContainer'))
  } catch (err: any) {
    console.error(t('components.materialDetail.logErrorAddToContainer'), err)
    addToContainerError.value = err?.response?.data?.error || t('components.materialDetail.errMoveToContainer')
  } finally {
    isAddingToContainer.value = false
  }
}

async function save() {
  if (material.value.is_consumable || material.value.is_food) {
    const sp = formData.sale_price
    const rp = formData.reference_purchase_unit_chf
    if (sp == null || Number(sp) <= 0 || rp == null || Number(rp) <= 0) {
      toast.error(t('components.materialDetail.errConsumablePricesRequired'))
      return
    }
  }
  isSaving.value = true
  try {
    const payload: any = {
      name: formData.name,
      description: formData.description || null,
      barcode_tag: formData.barcode_tag || null,
      category_id: formData.category_id || null,
      storage_address_id: formData.storage_address_id || null,
      manufacturer: formData.manufacturer || null,
      model: formData.model || null,
      ean: formData.ean || null,
      weight: normalizeMaterialMetricInput(formData.weight, 'kg'),
      color: formData.color || null,
      size_length: normalizeMaterialMetricInput(formData.size_length, 'cm'),
      size_width: normalizeMaterialMetricInput(formData.size_width, 'cm'),
      size_height: normalizeMaterialMetricInput(formData.size_height, 'cm'),
      warranty_until: formData.warranty_until || null,
      rental_price_day: formData.rental_price_day || null,
      rental_price_week: formData.rental_price_week || null,
      rental_price_month: formData.rental_price_month || null,
      rental_deposit: formData.rental_deposit || null,
      rental_lead_days: formData.rental_lead_days,
      rental_max_days: formData.rental_max_days,
      rental_external_allowed: formData.rental_external_allowed,
      rental_scope: formData.rental_external_allowed ? (formData.rental_scope || null) : null,
      rental_requires_approval: formData.rental_requires_approval,
      rental_notes: formData.rental_notes || null,
      rental_calc_params: formData.rental_calc_params,
      pack_size: formData.pack_size || null,
      pack_unit: formData.pack_unit || null,
      pack_sale_price_chf:
        formData.pack_sale_price_chf != null && formData.pack_sale_price_chf > 0
          ? String(formData.pack_sale_price_chf)
          : null,
      pack_weight: normalizeMaterialMetricInput(formData.pack_weight, 'kg'),
      pack_size_length: normalizeMaterialMetricInput(formData.pack_size_length, 'cm'),
      pack_size_width: normalizeMaterialMetricInput(formData.pack_size_width, 'cm'),
      pack_size_height: normalizeMaterialMetricInput(formData.pack_size_height, 'cm'),
    }
    if (material.value.tracking_type === 'bulk') {
      payload.is_container = formData.is_container
    }

    if (canManageJsMaterial.value) {
      payload.is_js_material = formData.is_js_material
      payload.external_source = formData.is_js_material ? (formData.external_source || 'js_ch') : null
    }

    if (material.value.is_consumable || material.value.is_food) {
      payload.sale_price =
        formData.sale_price != null && formData.sale_price > 0 ? String(formData.sale_price) : null
      payload.reference_purchase_unit_chf =
        formData.reference_purchase_unit_chf != null && formData.reference_purchase_unit_chf > 0
          ? String(formData.reference_purchase_unit_chf)
          : null
    }
    if (material.value.is_consumable) {
      payload.min_stock = formData.min_stock
    }

    const updated = await updateMaterial(props.materialId, payload)
    
    originalFormData = JSON.stringify(formData)
    syncSavedFormBaselines()
    void nextTick(() => {
      detailTabsStore.setTabDirty(props.materialId, 'material', props.departmentId, false)
    })
    emit('updated', updated)
    
    // History aktualisieren falls Tab aktiv
    if (activeTab.value === 'history') {
      loadHistory()
    } else {
      // History-Cache leeren, damit beim Tab-Wechsel neu geladen wird
      historyEntries.value = []
    }
  } catch (err: any) {
    console.error(t('components.materialDetail.logErrorSaveMaterial'), err)
    toast.error(err?.response?.data?.error || t('components.materialDetail.errSaveMaterial'))
  } finally {
    isSaving.value = false
  }
}

// History Labels
const actionLabels = computed((): Record<string, string> => ({
  created: t('components.materialDetail.historyActionCreated'),
  updated: t('components.materialDetail.historyActionUpdated'),
  deleted: t('components.materialDetail.historyActionDeleted'),
  batch_added: t('components.materialDetail.historyActionBatchAdded'),
  batch_updated: t('components.materialDetail.historyActionBatchUpdated'),
}))

function historyFieldLabel(fieldName: string): string {
  const rows = tm('components.materialDetail.historyFields') as Record<string, string> | undefined
  if (rows && typeof rows[fieldName] === 'string') return rows[fieldName] as string
  return fieldName
}

// Used-In Labels
const usedInAssignmentLabels = computed((): Record<string, string> => ({
  fixed: t('components.materialDetail.assignmentFixed'),
  assigned: t('components.materialDetail.assignmentAssigned'),
  on_issue: t('components.materialDetail.assignmentOnIssue'),
  bulk: t('components.materialDetail.assignmentBulk'),
}))

const filteredUsedInEntries = computed(() => {
  const q = usedInSearch.value.trim().toLocaleLowerCase(sortLocale())
  if (!q) return usedInEntries.value
  return usedInEntries.value.filter((entry) => {
    const assignmentLabel = (
      usedInAssignmentLabels.value[entry.assignment_mode] ||
      entry.assignment_mode ||
      ''
    ).toLocaleLowerCase(sortLocale())
    const typeLabel = (
      entry.material_type === 'physical_combo'
        ? t('components.materialDetail.typePhysicalShort')
        : t('components.materialDetail.typeVirtualShort')
    ).toLocaleLowerCase(sortLocale())
    const haystack = [
      entry.combo_name,
      entry.component_role || '',
      entry.batch_serial || '',
      assignmentLabel,
      typeLabel,
    ]
      .join(' ')
      .toLocaleLowerCase(sortLocale())
    return haystack.includes(q)
  })
})

async function loadUsedIn() {
  isLoadingUsedIn.value = true
  try {
    usedInEntries.value = await getMaterialUsedIn(props.materialId)
  } catch (err) {
    console.error(t('components.materialDetail.logErrorLoadUsedIn'), err)
    usedInEntries.value = []
  } finally {
    isLoadingUsedIn.value = false
  }
}

function navigateToCombo(comboId: string) {
  router.push(`/${props.departmentId}/materials/${comboId}`)
}

async function loadHistory() {
  isLoadingHistory.value = true
  selectedHistoryEntry.value = null
  try {
    historyEntries.value = await getMaterialHistory(props.materialId)
  } catch (err) {
    console.error(t('components.materialDetail.logErrorLoadHistory'), err)
    historyEntries.value = []
  } finally {
    isLoadingHistory.value = false
  }
}

function formatHistoryDate(dateStr: string): string {
  const d = new Date(dateStr)
  return d.toLocaleDateString(dateLocaleForIntl(), { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function formatHistoryTime(dateStr: string): string {
  const d = new Date(dateStr)
  return d.toLocaleTimeString(dateLocaleForIntl(), { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

function formatChangeValue(val: any): string {
  if (val === null || val === undefined || val === '') return t('components.materialDetail.emDash')
  if (typeof val === 'boolean') return val ? t('components.materialDetail.yes') : t('components.materialDetail.no')
  return String(val)
}

function openAddBatchModal() {
  editingBatch.value = null
  showBatchModal.value = true
}

function getArchivedBatchDisplayQty(batch: any): number {
  if (batch.status === 'split_to_serial' && (batch.qty || 0) === 0) {
    const splitCount = batches.value.filter((b: any) => b.source_batch_id === batch.id).length
    return splitCount > 0 ? splitCount : 0
  }
  return batch.qty ?? 0
}

function openEditBatchModal(batch: any) {
  if (!batch?.id) return
  if (showBatchModal.value && editingBatch.value?.id === batch.id) return
  closeQrActionModal()
  showAddToContainerModal.value = false
  showSplitModal.value = false
  showMoveModal.value = false
  moveBatch.value = null
  void loadMaterialStorageLocations()
  void ensureContainerBatchesLoaded()
  editingBatch.value = batch
  showBatchModal.value = true
}

function openMoveQuantityModal(batch: any) {
  moveBatch.value = batch
  showMoveModal.value = true
}

function openMoveQuantityFromHeader() {
  if (material.value.tracking_type === 'serialized') return
  const candidate = activeBatches.value.find((b: any) => (b.qty || 0) > 0)
  if (!candidate) {
    toast.info(t('components.materialDetail.toastNoMovableBatch'))
    return
  }
  openMoveQuantityModal(candidate)
}

function handleMoveSaved(result: { id: string; qty: number; rack_id: string | null; slot_id: string | null; allocations?: any[] }) {
  const idx = batches.value.findIndex((b: any) => b.id === result.id)
  if (idx >= 0) {
    const b = batches.value[idx] as any
    b.qty = result.qty
    b.rack_id = result.rack_id
    b.slot_id = result.slot_id
    if (result.allocations) {
      b.allocations = result.allocations
    }
  }
  loadMaterial()
}

function closeBatchModal() {
  showBatchModal.value = false
  editingBatch.value = null
  // Query-Parameter entfernen, damit Modal nicht erneut öffnet
  if (route.query.batch) {
    const q = { ...route.query }
    delete q.batch
    router.replace({ path: route.path, query: q })
  }
}

function handleClose() {
  // Zurück zur Liste: Tab im Header bleibt offen (nur × im Header entfernt Chip)
  emit('close')
}

function openSplitModal() {
  if (splitSourceBatches.value.length === 0) return
  showSplitModal.value = true
}

async function handleSplitSaved() {
  await loadMaterial()
  if (activeTab.value === 'history') {
    await loadHistory()
  }
  toast.success(t('components.materialDetail.toastSplitDone'))
}

async function handleBatchSaved(result: MaterialBatch | AddBatchMultiResponse) {
  if (editingBatch.value) {
    // Update: Batch in lokaler Liste aktualisieren
    const batch = result as MaterialBatch
    const idx = batches.value.findIndex(b => b.id === batch.id)
    if (idx >= 0) {
      batches.value[idx] = { ...batches.value[idx], ...batch }
    }
    if (activeTab.value === 'container-content') {
      await loadContainerContentOverview()
    }
    closeBatchModal()
    return
  }

  if (activeTab.value === 'container-content') {
    await loadContainerContentOverview()
  }
  await loadMaterial()
  toast.success(t('components.materialDetail.toastBatchAdded'))
  closeBatchModal()
}

watch([tabIds, () => route.query[DETAIL_QUERY_KEYS.tab], isLoading], ([ids, routeTab, loading]) => {
  if (loading) {
    const raw = normalizeQueryString(routeTab)
    if (raw && activeTab.value !== raw) {
      syncingFromRoute.value = true
      activeTab.value = raw
      syncingFromRoute.value = false
    }
    return
  }
  const currentRouteTab = normalizeQueryString(routeTab)
  if (!currentRouteTab) {
    if (!ids.includes(activeTab.value)) {
      syncingFromRoute.value = true
      activeTab.value = ids.includes('data') ? 'data' : (ids[0] || 'data')
      syncingFromRoute.value = false
    }
    return
  }
  const resolved = resolveTabId(routeTab)
  if (activeTab.value !== resolved) {
    syncingFromRoute.value = true
    activeTab.value = resolved
    syncingFromRoute.value = false
  }
  if (currentRouteTab && ids.includes(resolved) && currentRouteTab !== resolved) {
    mergeAndReplaceQuery({ [DETAIL_QUERY_KEYS.tab]: resolved })
  }
}, { immediate: true })

watch(
  () => normalizeQueryString(route.query[DETAIL_QUERY_KEYS.containerBatch]) || normalizeQueryString(route.query[DETAIL_QUERY_KEYS.legacyStoredInContainerBatch]),
  (next) => {
  if (containerContentBatchId.value !== next) {
    syncingFromRoute.value = true
    containerContentBatchId.value = next
    syncingFromRoute.value = false
  }
}, { immediate: true })

watch(
  () => normalizeQueryString(route.query[DETAIL_QUERY_KEYS.containerSearch]) || normalizeQueryString(route.query[DETAIL_QUERY_KEYS.legacyStoredInSearch]),
  (next) => {
  if (containerContentSearch.value !== next) {
    syncingFromRoute.value = true
    containerContentSearch.value = next
    syncingFromRoute.value = false
  }
}, { immediate: true })

watch(() => route.query[DETAIL_QUERY_KEYS.usedInSearch], (value) => {
  const next = normalizeQueryString(value)
  if (usedInSearch.value !== next) {
    syncingFromRoute.value = true
    usedInSearch.value = next
    syncingFromRoute.value = false
  }
}, { immediate: true })

watch(containerContentBatchId, (value) => {
  if (syncingFromRoute.value) return
  const current = normalizeQueryString(route.query[DETAIL_QUERY_KEYS.containerBatch]) || normalizeQueryString(route.query[DETAIL_QUERY_KEYS.legacyStoredInContainerBatch])
  if (current === value) return
  mergeAndReplaceQuery({
    [DETAIL_QUERY_KEYS.containerBatch]: value || undefined,
    [DETAIL_QUERY_KEYS.legacyStoredInContainerBatch]: undefined,
  })
})

watch(containerContentSearch, (value) => {
  if (syncingFromRoute.value) return
  const current = normalizeQueryString(route.query[DETAIL_QUERY_KEYS.containerSearch]) || normalizeQueryString(route.query[DETAIL_QUERY_KEYS.legacyStoredInSearch])
  if (current === value) return
  mergeAndReplaceQuery({
    [DETAIL_QUERY_KEYS.containerSearch]: value || undefined,
    [DETAIL_QUERY_KEYS.legacyStoredInSearch]: undefined,
  })
})

function preferredContainerBatchIdForMaterial(): string | null {
  const linkedId = linkedContainerBatchIdForRelease.value
  if (linkedId && storedInContainerOptions.value.some((o) => o.id === linkedId)) {
    return linkedId
  }
  const ms = materialStorageLocations.value
  for (const block of ms?.via_physical_combo ?? []) {
    const cid = (block.parent_linked_container_batch_id || '').trim()
    if (cid && storedInContainerOptions.value.some((o) => o.id === cid)) return cid
  }
  for (const row of ms?.direct ?? []) {
    const cid = (row.container_batch_id || '').trim()
    if (cid && storedInContainerOptions.value.some((o) => o.id === cid)) return cid
  }
  return storedInContainerOptions.value[0]?.id ?? null
}

watch(
  () => [
    material.value?.id,
    storedInContainerOptions.value.map((o) => o.id).join(','),
    materialStorageLocations.value,
  ],
  () => {
    if (containerContentBatchId.value) return
    const preferred = preferredContainerBatchIdForMaterial()
    if (preferred) containerContentBatchId.value = preferred
  },
  { immediate: true },
)

watch([activeTab, containerContentBatchId], ([tab, batchId]) => {
  if (tab !== 'container-content') return
  if (!batchId) {
    loadContainerEditorForSelectedBatch()
    return
  }
  loadContainerEditorForSelectedBatch()
}, { immediate: true })

watch(selectedContainerBatch, () => {
  if (activeTab.value !== 'container-content') return
  loadContainerEditorForSelectedBatch()
})

watch(usedInSearch, (value) => {
  if (syncingFromRoute.value) return
  if (normalizeQueryString(route.query[DETAIL_QUERY_KEYS.usedInSearch]) === value) return
  mergeAndReplaceQuery({ [DETAIL_QUERY_KEYS.usedInSearch]: value || undefined })
})

// Daten laden wenn Tab gewechselt wird
watch(activeTab, (newTab) => {
  const currentRouteTab = normalizeQueryString(route.query[DETAIL_QUERY_KEYS.tab])
  const clearOnLeave: Record<string, string | undefined> = {}
  if (newTab !== 'container-content') {
    clearOnLeave[DETAIL_QUERY_KEYS.containerBatch] = undefined
    clearOnLeave[DETAIL_QUERY_KEYS.containerSearch] = undefined
    clearOnLeave[DETAIL_QUERY_KEYS.legacyStoredInContainerBatch] = undefined
    clearOnLeave[DETAIL_QUERY_KEYS.legacyStoredInSearch] = undefined
  }
  if (newTab !== 'used-in') {
    clearOnLeave[DETAIL_QUERY_KEYS.usedInSearch] = undefined
  }
  if (!syncingFromRoute.value) {
    if (newTab === 'data') {
      if (currentRouteTab) {
        mergeAndReplaceQuery({ [DETAIL_QUERY_KEYS.tab]: undefined, ...clearOnLeave })
      } else if (Object.keys(clearOnLeave).length > 0) {
        mergeAndReplaceQuery(clearOnLeave)
      }
    } else if (currentRouteTab !== newTab) {
      mergeAndReplaceQuery({ [DETAIL_QUERY_KEYS.tab]: newTab, ...clearOnLeave })
    } else if (Object.keys(clearOnLeave).length > 0) {
      mergeAndReplaceQuery(clearOnLeave)
    }
  }
  if (newTab === 'history' && historyEntries.value.length === 0) {
    loadHistory()
  }
  if (newTab === 'used-in' && usedInEntries.value.length === 0) {
    loadUsedIn()
  }
  if (newTab === 'composition') {
    void loadComboComponentsForTab()
    void loadRelatedAccessories()
  }
  if (newTab === 'stock' || newTab === 'serials' || newTab === 'stored-in' || newTab === 'container-content') {
    ensureContainerBatchesLoaded()
  }
  if (newTab === 'container-content') {
    loadContainerContentOverview()
  }
  if (newTab === 'workshop') {
    void loadWorkshopTicketsForMaterial()
  }
  if (newTab === 'rental') {
    void loadRentalActivityBookings()
    if (isComboMaterialView.value) {
      void loadComboRentalBreakdown()
    }
  }
}, { immediate: true })

watch(hasManualUnsavedChanges, (dirty) => {
  detailTabsStore.setTabDirty(props.materialId, 'material', props.departmentId, dirty)
}, { immediate: true })

watch(
  materialDisplayName,
  (name) => {
    const label = String(name || '').trim()
    if (!label) return
    pageHeadStore.setDynamic(`${label} · eMatChef`, `${label} – Materialdetails in eMatChef.`)
  },
  { immediate: true }
)

// Bei initialBatchId aus Lagerübersicht: BatchModal öffnen zur Slot-Zuordnung
const openedInitialBatchFor = ref<string | null>(null)
watch(
  () => props.initialBatchId,
  (id) => {
    if (!id) openedInitialBatchFor.value = null
  }
)
watch(
  () => ({
    initialBatchId: props.initialBatchId,
    loading: isLoading.value,
    batchIds: batches.value.map((x: any) => x.id).join(','),
  }),
  (state) => {
    const batchId = state.initialBatchId
    if (!batchId || state.loading) return
    if (openedInitialBatchFor.value === String(batchId)) return
    const batch = batches.value.find((x: any) => String(x.id) === String(batchId))
    if (batch) {
      openedInitialBatchFor.value = String(batchId)
      openEditBatchModal(batch)
    }
  },
  { immediate: true }
)

async function loadRentalDefaults() {
  try {
    rentalAmortizationDefaults.value = await getRentalAmortizationDefaults(props.departmentId)
  } catch {
    rentalAmortizationDefaults.value = { ...DEFAULT_RENTAL_AMORTIZATION }
  }
}

async function loadWorkshopTicketsForMaterial() {
  if (!props.materialId || !props.departmentId) return
  workshopTicketsLoading.value = true
  try {
    workshopTickets.value = await getWorkshopTickets(props.departmentId, { material_item_id: props.materialId })
  } catch {
    workshopTickets.value = []
  } finally {
    workshopTicketsLoading.value = false
  }
}

/** keep-alive (AppLayout) + Teleport: Modals hängen sonst im document.body und stapeln sich bei jedem Routenwechsel */
function closeAllDetailModals() {
  showBatchModal.value = false
  showSplitModal.value = false
  showMoveModal.value = false
  showAddToContainerModal.value = false
  showAddCompositionModal.value = false
  showEditCompositionModal.value = false
  showQrActionModal.value = false
  editingBatch.value = null
  moveBatch.value = null
}

onDeactivated(() => {
  closeAllDetailModals()
})

async function loadSparePartsCategoryId() {
  try {
    const settings = await getWorkshopSettings(props.departmentId)
    sparePartsCategoryId.value = settings.sparePartsCategoryId || ''
  } catch {
    sparePartsCategoryId.value = ''
  }
}

onMounted(() => {
  void Promise.all([loadMaterial(), loadCategories(), loadRentalDefaults(), loadSparePartsCategoryId()])
})
</script>

<style scoped src="@/styles/material-detail-view.css"></style>
<style scoped>
.composition-configurator-badge {
  display: inline-block;
  margin-left: 0.5rem;
  font-size: 0.68rem;
  font-weight: 600;
  padding: 0.1rem 0.5rem;
  border-radius: 999px;
  background: #ede9fe;
  color: #6d28d9;
  vertical-align: middle;
}
.composition-options-card {
  margin-top: 0.75rem;
}
.btn-outline-small.is-active {
  background: #ede9fe;
  border-color: #c4b5fd;
  color: #6d28d9;
}
.workshop-tab-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}
.workshop-tickets-mini .muted {
  color: #64748b;
}
.field-required-star {
  color: #b91c1c;
  font-weight: 600;
}
.loading-inline {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* Sortierbare Tabellenköpfe (Bestand / Seriennummern) */
.th-sort-cell {
  vertical-align: middle;
}

.batch-table .detail-th-sort,
.serials-table .detail-th-sort {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 6px;
  padding: 2px 0;
  margin: 0;
  border: none;
  background: transparent;
  font: inherit;
  font-size: 11px;
  font-weight: 600;
  text-align: left;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #6b7280;
  cursor: pointer;
}

.batch-table .detail-th-sort:hover,
.serials-table .detail-th-sort:hover {
  color: #059669;
}

.detail-th-sort > span:first-child {
  min-width: 0;
  text-align: left;
}

.detail-th-sort-arrows {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0;
  line-height: 1;
  flex-shrink: 0;
  margin-left: 2px;
}

.detail-sort-chev {
  font-size: 8px;
  line-height: 1;
  color: #d1d5db;
  transition: color 0.15s ease;
}

.detail-sort-chev.active {
  color: #059669;
}

.combo-allocation-breakdown {
  margin: 12px 0 16px;
  padding: 10px 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.combo-allocation-breakdown-explain {
  margin: -4px 0 8px;
  font-size: 11px;
  color: #6b7280;
  line-height: 1.35;
}

.stock-location-issued-hint {
  margin: 0 0 12px;
  padding: 10px 12px;
  font-size: 12px;
  line-height: 1.4;
  color: #92400e;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
}

.combo-allocation-breakdown-title {
  margin: 0 0 8px;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
}

.combo-allocation-breakdown-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.combo-allocation-breakdown-list li {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  font-size: 13px;
}

.combo-allocation-link {
  color: #2563eb;
  font-weight: 500;
  text-decoration: none;
}

.combo-allocation-link:hover {
  text-decoration: underline;
}

.combo-allocation-qty {
  color: #6b7280;
  font-size: 12px;
}

.combo-allocation-free {
  margin: 8px 0 0;
  font-size: 12px;
  color: #6b7280;
}

.stock-stat.combo-alloc-stat .stock-number {
  color: #7c3aed;
}

.composition-state-th {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: #6b7280;
  white-space: normal;
  max-width: 6rem;
  line-height: 1.2;
}

.user-readonly-fields {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.user-readonly-row {
  display: grid;
  grid-template-columns: minmax(8rem, 11rem) 1fr;
  gap: 0.75rem 1rem;
  align-items: baseline;
}

.user-readonly-row dt {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #6b7280;
}

.user-readonly-row dd {
  margin: 0;
  font-size: 0.9375rem;
  color: #111827;
  white-space: pre-wrap;
}

.user-readonly-empty {
  margin: 0;
  color: #6b7280;
  font-size: 0.9375rem;
}

.stock-qr-collapsible {
  margin-bottom: 1rem;
  padding: 0;
  overflow: hidden;
}

.stock-qr-toggle {
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  border: none;
  background: #f8fafc;
  font: inherit;
  font-weight: 600;
  color: #0f172a;
  cursor: pointer;
  text-align: left;
}

.stock-qr-toggle:hover {
  background: #f1f5f9;
}

.stock-qr-toggle-chevron {
  display: flex;
  color: #64748b;
  transition: transform 0.2s ease;
}

.stock-qr-toggle-chevron.is-open {
  transform: rotate(180deg);
}

.stock-qr-panel {
  padding: 0 16px 16px;
  border-top: 1px solid #e2e8f0;
}

.qr-panel-hint {
  margin: 12px 0;
  color: #64748b;
  font-size: 0.9rem;
}

.stock-qr-panel-actions {
  margin-bottom: 12px;
}

.stock-qr-batch-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.stock-qr-batch-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
}

.stock-qr-batch-label {
  flex: 1;
  font-size: 0.9rem;
  color: #334155;
}
</style>
