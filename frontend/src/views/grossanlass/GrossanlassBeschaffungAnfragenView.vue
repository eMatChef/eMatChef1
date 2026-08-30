<template>
  <div class="ga-anfragen">
    <p class="tab-intro">{{ t('grossanlass.beschaffung.anfragen.intro') }}</p>

    <ELoadingState v-if="isLoading" variant="list" :message="t('common.loading')" />

    <div v-else class="ga-anfragen__loaded">
    <div class="review-strip">
      <span class="review-strip__label">{{ t('grossanlass.beschaffung.anfragen.reviewLabel') }}</span>
      <button
        type="button"
        class="review-strip__btn"
        :class="{ 'is-active': reviewKind === 'nocat' }"
        :disabled="firmsWithoutCategory.length === 0"
        @click="startReview('nocat')"
      >
        {{ t('grossanlass.beschaffung.anfragen.reviewNoCategory', { count: firmsWithoutCategory.length }) }}
      </button>
      <button
        type="button"
        class="review-strip__btn"
        :class="{ 'is-active': reviewKind === 'nomail' }"
        :disabled="firmsWithoutEmail.length === 0"
        @click="startReview('nomail')"
      >
        {{ t('grossanlass.beschaffung.anfragen.reviewNoEmail', { count: firmsWithoutEmail.length }) }}
      </button>
    </div>
    <div class="ga-anfragen-accordions-wrap">
    <v-expansion-panels v-model="openSections" multiple class="e-accordions">
      <v-expansion-panel value="map">
        <v-expansion-panel-title>
          <span class="panel-head">
            <span class="panel-head__label">
              {{ t('grossanlass.beschaffung.anfragen.viewMap') }}
              <span class="panel-head__count">
                {{ t('grossanlass.beschaffung.anfragen.mapCount', { shown: mapPins.length, total: filteredFirms.length }) }}
              </span>
            </span>
          </span>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="map-panel">
            <div class="map-toolbar">
              <label class="map-radius">
                <span>{{ t('grossanlass.beschaffung.anfragen.mapRadius') }}</span>
                <select v-model.number="radiusKm">
                  <option :value="10">10 km</option>
                  <option :value="20">20 km</option>
                  <option :value="30">30 km</option>
                  <option :value="50">50 km</option>
                  <option :value="100">100 km</option>
                  <option :value="0">{{ t('grossanlass.beschaffung.anfragen.mapRadiusAll') }}</option>
                </select>
              </label>
              <EButton variant="secondary" size="small" :disabled="!mapSelectableIds.length" @click="selectMapVisible">
                {{ t('grossanlass.beschaffung.anfragen.mapSelectVisible', { count: mapSelectableIds.length }) }}
              </EButton>
              <EButton
                v-if="unmappedCount > 0"
                variant="secondary"
                size="small"
                :loading="isGeocoding"
                @click="geocodeMissingPlaces"
              >
                {{ t('grossanlass.beschaffung.anfragen.mapGeocode', { count: unmappedCount }) }}
              </EButton>
              <span class="muted">{{ t('grossanlass.beschaffung.anfragen.mapZoomHint') }}</span>
            </div>
            <p v-if="!venuePin" class="muted">{{ t('grossanlass.beschaffung.anfragen.mapNoVenue') }}</p>
            <GrossanlassInquiryMap
              v-if="openSections.includes('map')"
              :pins="mapPins"
              :venue="venuePin"
              :radius-km="radiusKm || null"
              :selected-id="previewFirma?.id ?? null"
              :active="openSections.includes('map')"
              @select="onMapSelect"
            />
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="mail" class="mail-panel">
        <v-expansion-panel-title>
          <span class="panel-head panel-head--mail">
            <span class="panel-head__label">
              {{ t('grossanlass.beschaffung.anfragen.gmailTitle') }}
              <span class="panel-head__count">
                {{ gmailStatus?.connected
                  ? (gmailStatus.email || '')
                  : t('grossanlass.beschaffung.anfragen.gmailHeaderDisconnected') }}
              </span>
              <span v-if="unmatched.length" class="panel-badge">
                {{ t('grossanlass.beschaffung.anfragen.gmailUnmatchedCount', { count: unmatched.length }) }}
              </span>
            </span>
          </span>
        </v-expansion-panel-title>
        <div class="mail-panel-actions">
          <EButton
            v-if="!gmailStatus?.connected && canConnectGmail"
            variant="secondary"
            size="x-small"
            @click="goGmailSettings"
          >
            {{ t('grossanlass.beschaffung.anfragen.gmailConnect') }}
          </EButton>
          <EButton v-else variant="secondary" size="x-small" @click="goGmailSettings">
            {{ t('grossanlass.beschaffung.anfragen.gmailSettings') }}
          </EButton>
          <EButton
            variant="secondary"
            size="x-small"
            :disabled="!gmailStatus?.connected"
            :loading="isSyncing"
            @click="syncGmail"
          >
            {{ t('grossanlass.beschaffung.anfragen.gmailSync') }}
          </EButton>
        </div>
        <v-expansion-panel-text>
          <p v-if="gmailStatus?.connected" class="muted">
            {{ t('grossanlass.beschaffung.anfragen.gmailConnected', { email: gmailStatus.email || '' }) }}
          </p>
          <p v-else class="muted">{{ t('grossanlass.beschaffung.anfragen.gmailDisconnected') }}</p>
          <template v-if="gmailStatus?.connected">
          <section v-if="unmatched.length" class="unmatched">
            <h2>{{ t('grossanlass.beschaffung.anfragen.unmatchedTitle') }}</h2>
            <p class="muted">{{ t('grossanlass.beschaffung.anfragen.unmatchedHint') }}</p>
      <article v-for="mail in unmatched" :key="mail.id" class="unmatched-card">
        <header>
          <strong>{{ mail.from_name || mail.from_email || '—' }}</strong>
          <span class="meta">{{ mail.from_email }} · {{ mail.subject }}</span>
        </header>
        <pre class="unmatched-body">{{ mail.body }}</pre>
        <div class="unmatched-actions">
          <select v-model="assignTarget[mail.id]" class="unmatched-select">
            <option value="">{{ t('grossanlass.beschaffung.anfragen.unmatchedAssignPick') }}</option>
            <option v-for="firma in firms" :key="firma.id" :value="firma.id">
              {{ firma.name }} ({{ firma.reference || firma.id }})
            </option>
          </select>
          <EButton
            variant="secondary"
            size="small"
            :disabled="!assignTarget[mail.id]"
            :loading="unmatchedBusy === mail.id"
            @click="assignMail(mail)"
          >
            {{ t('grossanlass.beschaffung.anfragen.unmatchedAssign') }}
          </EButton>
          <EButton
            v-if="canTakeInquiry"
            variant="secondary"
            size="small"
            :loading="unmatchedBusy === mail.id"
            @click="createFromMail(mail)"
          >
            {{ t('grossanlass.beschaffung.anfragen.unmatchedNew') }}
          </EButton>
          <EButton
            variant="text"
            size="small"
            :loading="unmatchedBusy === mail.id"
            @click="discardMail(mail)"
          >
            {{ t('grossanlass.beschaffung.anfragen.unmatchedDiscard') }}
          </EButton>
          <EButton
            v-if="mail.gmail_open_url"
            variant="text"
            size="small"
            @click="windowOpen(mail.gmail_open_url)"
          >
            {{ t('grossanlass.beschaffung.anfragen.openGmail') }}
          </EButton>
        </div>
      </article>
          </section>
          <p v-else class="muted">{{ t('grossanlass.beschaffung.anfragen.unmatchedEmpty') }}</p>
          </template>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="categories">
        <v-expansion-panel-title>
          <span class="panel-head">
            <span class="panel-head__label">
              {{ t('grossanlass.beschaffung.anfragen.categoriesPanel') }}
              <span class="panel-head__count">{{ categoryPanelCount }}</span>
            </span>
            <span
              v-if="canManageProcurement"
              class="panel-head__settings"
              role="link"
              tabindex="0"
              @click.stop="goCategorySettings"
              @keydown.enter.stop.prevent="goCategorySettings"
            >
              {{ t('grossanlass.beschaffung.bedarf.categoriesOpenSettings') }}
            </span>
          </span>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <p class="muted categories-panel-hint">{{ t('grossanlass.beschaffung.anfragen.categoriesPanelHint') }}</p>
          <div v-if="procurementCategories.length" class="category-filter-tree">
            <button
              type="button"
              class="cat-filter-extra"
              :class="{ 'is-active': categoryFilter === '' }"
              @click="categoryFilter = ''"
            >
              {{ t('grossanlass.beschaffung.anfragen.filterAll') }}
            </button>
            <GrossanlassProcurementCategoryManager
              hide-heading
              mode="filter"
              :department-id="departmentId"
              :categories="procurementCategories"
              :selected-id="categoryFilter"
              @select="categoryFilter = $event"
            />
            <button
              type="button"
              class="cat-filter-extra"
              :class="{ 'is-active': categoryFilter === '_none' }"
              @click="categoryFilter = '_none'"
            >
              {{ t('grossanlass.beschaffung.anfragen.noPackage') }}
            </button>
          </div>
          <p v-else-if="categoriesLoadError" class="muted meta--warn">{{ categoriesLoadError }}</p>
          <p v-else class="muted">{{ t('grossanlass.beschaffung.anfragen.categoriesEmpty') }}</p>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="firms">
        <v-expansion-panel-title>
          <span class="panel-head">
            <span class="panel-head__label">
              {{ t('grossanlass.beschaffung.anfragen.viewFirms') }}
              <span class="panel-head__count">{{ firms.length }}</span>
            </span>
          </span>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
    <div v-if="firms.length" class="status-stats" role="group">
      <button
        v-for="row in statusCounts"
        :key="row.status"
        type="button"
        class="status-stat"
        :class="{ 'is-active': statusFilter === row.status }"
        @click="statusFilter = statusFilter === row.status ? '' : row.status"
      >
        <span class="status-chip" :class="`status-chip--${row.status}`">
          {{ t(`grossanlass.beschaffung.anfragen.status.${row.status}`) }}
        </span>
        <strong>{{ row.count }}</strong>
      </button>
    </div>

    <div class="ga-anfragen__toolbar">
      <div class="view-toggle" role="tablist">
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'firms' }"
          @click="view = 'firms'"
        >
          {{ t('grossanlass.beschaffung.anfragen.viewFirms') }}
        </button>
        <button
          type="button"
          class="view-toggle__btn"
          :class="{ 'is-active': view === 'category' }"
          @click="view = 'category'"
        >
          {{ t('grossanlass.beschaffung.anfragen.viewCategory') }}
        </button>
      </div>
      <div class="filter-chips" role="group">
        <button
          type="button"
          class="filter-chip"
          :class="{ 'is-active': emailFilter === '' }"
          @click="emailFilter = ''"
        >
          {{ t('grossanlass.beschaffung.anfragen.filterEmailAll') }}
        </button>
        <button
          type="button"
          class="filter-chip"
          :class="{ 'is-active': emailFilter === 'ready' }"
          @click="emailFilter = 'ready'"
        >
          {{ t('grossanlass.beschaffung.anfragen.filterReady') }}
        </button>
        <button
          type="button"
          class="filter-chip"
          :class="{ 'is-active': emailFilter === 'missing' }"
          @click="emailFilter = 'missing'"
        >
          {{ t('grossanlass.beschaffung.anfragen.filterEmailMissing') }}
        </button>
      </div>
      <ESearchField
        v-model="query"
        class="ga-anfragen__search"
        :label="t('grossanlass.beschaffung.anfragen.search')"
      />
      <input
        v-if="canTakeInquiry"
        ref="csvInput"
        type="file"
        accept=".csv,text/csv"
        class="csv-input"
        @change="onCsvFile"
      >
      <EButton v-if="canTakeInquiry" variant="secondary" size="small" @click="downloadCsvTemplate">
        {{ t('grossanlass.beschaffung.anfragen.csvTemplate') }}
      </EButton>
      <EButton v-if="canTakeInquiry" variant="secondary" size="small" :loading="isCsvImporting" @click="csvInput?.click()">
        {{ t('grossanlass.beschaffung.anfragen.csvImport') }}
      </EButton>
      <EButton v-if="canTakeInquiry" variant="secondary" size="small" :loading="isImporting" @click="importTips">
        {{ t('grossanlass.beschaffung.anfragen.importTips') }}
      </EButton>
      <EButton v-if="canTakeInquiry" variant="secondary" size="small" @click="openCreate()">
        {{ t('grossanlass.beschaffung.anfragen.addFirm') }}
      </EButton>
      <EButton
        v-if="canCreateMailDrafts"
        variant="primary"
        size="small"
        :disabled="!selectedDraftable.length"
        @click="draftsOpen = true"
      >
        {{ t('grossanlass.beschaffung.anfragen.createDrafts', { count: selectedDraftable.length || 0 }) }}
      </EButton>
      <EButton
        v-if="canTakeInquiry"
        variant="danger"
        size="small"
        :disabled="!selectedDeletable.length"
        :loading="isSaving"
        @click="deleteSelected"
      >
        {{ t('grossanlass.beschaffung.anfragen.deleteSelected', { count: selectedDeletable.length || 0 }) }}
      </EButton>
    </div>

    <EEmptyState
      v-if="firms.length === 0"
      variant="create"
      icon="mdi-email-multiple-outline"
      :title="t('grossanlass.beschaffung.anfragen.emptyTitle')"
      :description="t('grossanlass.beschaffung.anfragen.emptyDescription')"
    />

    <EEmptyState
      v-else-if="filteredFirms.length === 0"
      variant="search"
      icon="mdi-filter-off-outline"
      :title="t('grossanlass.beschaffung.anfragen.filterEmptyTitle')"
      :description="t('grossanlass.beschaffung.anfragen.filterEmptyDescription')"
    />

    <div v-else-if="view === 'firms'" class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th class="col-check">
              <input
                type="checkbox"
                :checked="allVisibleSelected"
                :indeterminate="someVisibleSelected && !allVisibleSelected"
                :title="t('grossanlass.beschaffung.anfragen.selectHint')"
                @change="toggleAllVisible"
              >
            </th>
            <th :aria-sort="firmSortAria('name')">
              <button type="button" class="th-sort" @click="toggleFirmSort('name')">
                {{ t('grossanlass.beschaffung.anfragen.colFirm') }}
                <span class="sort-indicator">{{ firmSortMark('name') }}</span>
              </button>
            </th>
            <th :aria-sort="firmSortAria('place')">
              <button type="button" class="th-sort" @click="toggleFirmSort('place')">
                {{ t('grossanlass.beschaffung.anfragen.placeLabel') }}
                <span class="sort-indicator">{{ firmSortMark('place') }}</span>
              </button>
            </th>
            <th :aria-sort="firmSortAria('reference')">
              <button type="button" class="th-sort" @click="toggleFirmSort('reference')">
                {{ t('grossanlass.beschaffung.anfragen.colReference') }}
                <span class="sort-indicator">{{ firmSortMark('reference') }}</span>
              </button>
            </th>
            <th :aria-sort="firmSortAria('packages')">
              <button type="button" class="th-sort" @click="toggleFirmSort('packages')">
                {{ t('grossanlass.beschaffung.anfragen.colPackages') }}
                <span class="sort-indicator">{{ firmSortMark('packages') }}</span>
              </button>
            </th>
            <th :aria-sort="firmSortAria('status')">
              <button type="button" class="th-sort" @click="toggleFirmSort('status')">
                {{ t('grossanlass.beschaffung.anfragen.colStatus') }}
                <span class="sort-indicator">{{ firmSortMark('status') }}</span>
              </button>
            </th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="firma in sortedFirms"
            :key="firma.id"
            :class="{ 'is-blocked': !isReadyForMail(firma) }"
          >
            <td class="col-check">
              <input
                type="checkbox"
                :checked="selected.includes(firma.id)"
                :disabled="inquiryMailLocked(firma)"
                :title="t('grossanlass.beschaffung.anfragen.selectHint')"
                @change="toggle(firma.id)"
              >
            </td>
            <td>
              <strong>{{ firma.name }}</strong>
              <span class="meta">
                {{ firmMeta(firma) }}
              </span>
              <span v-if="!firma.category_ids.length" class="meta meta--warn">
                {{ t('grossanlass.beschaffung.anfragen.missingPackage') }}
              </span>
              <span v-if="firma.tip_from" class="meta">{{ t('grossanlass.beschaffung.anfragen.tipFrom', { ressort: firma.tip_from }) }}</span>
              <span v-if="firma.tip_submitted_by" class="meta meta--submitter">
                {{ t('grossanlass.beschaffung.anfragen.tipSubmitter', { name: firma.tip_submitted_by.name }) }}
                <a
                  v-if="firma.tip_submitted_by.email"
                  class="submitter-mail"
                  :href="submitterMailto(firma)"
                >{{ t('grossanlass.beschaffung.anfragen.tipSubmitterAsk') }}</a>
              </span>
            </td>
            <td>{{ firma.place || '—' }}</td>
            <td>
              <code class="ref-id">{{ firma.reference || firma.id }}</code>
            </td>
            <td>
              <button
                type="button"
                class="pkg-edit"
                @click="inquiryMailLocked(firma) ? openPreview(firma) : openEditFirm(firma)"
              >
                <span v-if="!firma.category_ids.length" class="pkg-chip pkg-chip--empty">
                  {{ t('grossanlass.beschaffung.anfragen.noPackage') }}
                </span>
                <span v-for="categoryId in firmMailItemIds(firma)" :key="categoryId" class="pkg-chip">
                  {{ categoryLabel(categoryId) }}
                </span>
                <span v-if="firmMailItemExtra(firma) > 0" class="pkg-chip pkg-chip--more">
                  +{{ firmMailItemExtra(firma) }}
                </span>
              </button>
            </td>
            <td>
              <span class="status-chip" :class="`status-chip--${inquiryMailPhase(firma)}`">
                {{ t(`grossanlass.beschaffung.anfragen.status.${inquiryMailPhase(firma)}`) }}
              </span>
            </td>
            <td>
              <template v-if="inquiryMailLocked(firma)">
                <EButton variant="text" size="small" @click="openPreview(firma)">
                  {{ t('grossanlass.beschaffung.anfragen.tabMailThread') }}
                </EButton>
                <EButton
                  v-if="firma.gmail_open_url"
                  variant="text"
                  size="small"
                  @click="openGmailFor(firma)"
                >
                  {{ t('grossanlass.beschaffung.anfragen.openGmail') }}
                </EButton>
                <EButton
                  v-if="firma.status === 'zusage'"
                  variant="text"
                  size="small"
                  @click="goZuteilung"
                >
                  {{ t('grossanlass.beschaffung.anfragen.goZuteilung') }}
                </EButton>
              </template>
              <template v-else>
                <EButton variant="text" size="small" @click="openEditFirm(firma)">
                  {{ t('common.edit') }}
                </EButton>
                <EButton variant="text" size="small" @click="openPreview(firma)">
                  {{ t('grossanlass.beschaffung.anfragen.preview') }}
                </EButton>
                <EButton variant="text" size="small" @click="deleteFirm(firma)">
                  {{ t('common.delete') }}
                </EButton>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="view === 'category'" class="category-list">
      <section v-for="block in categoryBlocks" :key="block.id" class="category-card">
        <h3>
          {{ block.label }} <span>{{ block.firms.length }}</span>
          <EButton
            v-if="block.id !== '_none'"
            variant="text"
            size="small"
            @click="openCreate(block.id)"
          >
            {{ t('grossanlass.beschaffung.anfragen.addFirmToArea') }}
          </EButton>
        </h3>
        <ul>
          <li v-for="firma in block.firms" :key="firma.id">
            <div>
              <strong>{{ firma.name }}</strong>
              <span class="meta">{{ firmMeta(firma) }}</span>
              <span v-if="firma.tip_submitted_by" class="meta meta--submitter">
                {{ t('grossanlass.beschaffung.anfragen.tipSubmitter', { name: firma.tip_submitted_by.name }) }}
                <a
                  v-if="firma.tip_submitted_by.email"
                  class="submitter-mail"
                  :href="submitterMailto(firma)"
                >{{ t('grossanlass.beschaffung.anfragen.tipSubmitterAsk') }}</a>
              </span>
            </div>
            <span class="status-chip" :class="`status-chip--${inquiryMailPhase(firma)}`">
              {{ t(`grossanlass.beschaffung.anfragen.status.${inquiryMailPhase(firma)}`) }}
            </span>
            <template v-if="inquiryMailLocked(firma)">
              <EButton variant="text" size="small" @click="openPreview(firma)">
                {{ t('grossanlass.beschaffung.anfragen.tabMailThread') }}
              </EButton>
              <EButton
                v-if="firma.gmail_open_url"
                variant="text"
                size="small"
                @click="openGmailFor(firma)"
              >
                {{ t('grossanlass.beschaffung.anfragen.openGmail') }}
              </EButton>
              <EButton
                v-if="firma.status === 'zusage'"
                variant="text"
                size="small"
                @click="goZuteilung"
              >
                {{ t('grossanlass.beschaffung.anfragen.goZuteilung') }}
              </EButton>
            </template>
            <template v-else>
              <EButton variant="text" size="small" @click="openEditFirm(firma)">
                {{ t('common.edit') }}
              </EButton>
              <EButton variant="text" size="small" @click="openPreview(firma)">
                {{ t('grossanlass.beschaffung.anfragen.preview') }}
              </EButton>
              <EButton variant="text" size="small" @click="deleteFirm(firma)">
                {{ t('common.delete') }}
              </EButton>
            </template>
          </li>
        </ul>
      </section>
    </div>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
    </div>
    </div>

    <EDialog
      v-model="firmModalOpen"
      :title="firmModalTitle"
      max-width="800"
    >
      <v-tabs
        v-model="firmModalTab"
        color="primary"
        class="firm-modal-tabs"
        density="comfortable"
      >
        <v-tab value="firm">{{ t('grossanlass.beschaffung.anfragen.tabFirm') }}</v-tab>
        <v-tab value="mail">{{ firmMailTabLabel }}</v-tab>
      </v-tabs>

      <v-tabs-window v-model="firmModalTab" class="firm-modal-window">
        <v-tabs-window-item value="firm">
          <fieldset class="firm-fields" :disabled="previewMailLocked">
          <p
            v-if="previewFirma?.tip_submitted_by"
            class="submitter-card"
          >
            <span>
              {{ t('grossanlass.beschaffung.anfragen.tipSubmitter', { name: previewFirma.tip_submitted_by.name }) }}
              <template v-if="previewFirma.tip_from">
                · {{ previewFirma.tip_from }}
              </template>
            </span>
            <a
              v-if="previewFirma.tip_submitted_by.email"
              class="submitter-mail"
              :href="submitterMailto(previewFirma)"
            >{{ t('grossanlass.beschaffung.anfragen.tipSubmitterAsk') }}</a>
          </p>
          <p class="review-hint">{{ t('grossanlass.beschaffung.anfragen.mailFieldsHint') }}</p>
          <div class="firm-name-wrap">
            <ETextField v-model="editForm.name" :label="t('grossanlass.beschaffung.anfragen.colFirm')" hide-details="auto" />
          </div>
          <ETextField v-model="editForm.place" :label="t('grossanlass.beschaffung.anfragen.placeLabel')" hide-details="auto" class="mb-2" />
          <ETextField v-model="editForm.website" :label="t('grossanlass.beschaffung.anfragen.websiteLabel')" hide-details="auto" class="mb-2" />
          <p class="review-hint">{{ t('grossanlass.beschaffung.anfragen.packagesLabel') }}</p>
          <GrossanlassCategoryCheckboxTree
            v-if="procurementCategories.length"
            v-model="editForm.categoryIds"
            :categories="procurementCategories"
          />
          <p v-else class="muted">{{ t('grossanlass.beschaffung.anfragen.packagesHint') }}</p>
          <ETextField v-model="editForm.offering" :label="t('grossanlass.beschaffung.anfragen.offeringLabel')" hide-details="auto" class="mb-2" textarea rows="2" />
          <ETextField v-model="editForm.notes" :label="t('grossanlass.beschaffung.anfragen.notesLabel')" hide-details="auto" class="mb-2" textarea rows="2" />
          <div class="contact-name-row">
            <ESelect
              v-model="editForm.contactSalutation"
              :label="t('grossanlass.beschaffung.anfragen.contactSalutationLabel')"
              :items="salutationItems"
              clearable
              hide-details="auto"
            />
            <ETextField v-model="editForm.contactFirstName" :label="t('grossanlass.beschaffung.anfragen.contactFirstNameLabel')" hide-details="auto" />
            <ETextField v-model="editForm.contactLastName" :label="t('grossanlass.beschaffung.anfragen.contactLastNameLabel')" hide-details="auto" />
          </div>
          <ETextField v-model="editForm.email" :label="t('grossanlass.beschaffung.anfragen.emailLabel')" hide-details="auto" class="mb-2" />
          <ETextField v-model="editForm.phone" :label="t('grossanlass.beschaffung.anfragen.phoneLabel')" hide-details="auto" class="mb-2">
            <template #append-inner>
              <a
                v-if="firmTelHref(editForm.phone)"
                class="phone-tel-link"
                :href="firmTelHref(editForm.phone)"
              >
                {{ t('grossanlass.beschaffung.anfragen.webLookupCall') }}
              </a>
            </template>
          </ETextField>
          <GrossanlassInquiryWebLookupPanel
            :key="editFirma?.id ?? 'edit'"
            v-model="editForm"
            :department-id="departmentId"
          />
          </fieldset>
        </v-tabs-window-item>

        <v-tabs-window-item value="mail">
          <template v-if="previewFirma">
            <p class="mail-kicker">
              {{ t('grossanlass.beschaffung.anfragen.previewTo', {
                email: previewFirma.email || t('grossanlass.beschaffung.anfragen.missingEmail'),
              }) }}
            </p>
            <span class="status-chip" :class="`status-chip--${inquiryMailPhase(previewFirma)}`">
              {{ t(`grossanlass.beschaffung.anfragen.status.${inquiryMailPhase(previewFirma)}`) }}
            </span>
            <template v-if="previewMailLocked">
              <p v-if="previewStatus === 'gesendet'" class="review-hint">
                {{ t('grossanlass.beschaffung.anfragen.nextAfterSent') }}
              </p>
              <ELoadingState
                v-if="conversationLoading"
                variant="inline"
                :message="t('grossanlass.beschaffung.anfragen.convLoading')"
              />
              <div v-else class="mail-conv">
                <section class="mail-conv__block">
                  <h3>{{ t('grossanlass.beschaffung.anfragen.convSent') }}</h3>
                  <article v-for="(msg, index) in conversation.sent" :key="'sent-' + index" class="mail-conv__card">
                    <p class="mail-conv__meta">
                      {{ formatThreadAt(msg.at) }}
                      <template v-if="msg.subject"> · {{ msg.subject }}</template>
                    </p>
                    <div v-if="isHtmlMail(msg.text)" class="mail-conv__html" v-html="mailBodyHtml(msg.text)"></div>
                    <pre v-else class="mail-conv__text">{{ msg.text }}</pre>
                  </article>
                  <p v-if="!conversation.sent.length" class="muted">
                    {{ t('grossanlass.beschaffung.anfragen.convSentEmpty') }}
                  </p>
                </section>
                <section class="mail-conv__block">
                  <h3>{{ t('grossanlass.beschaffung.anfragen.convReply') }}</h3>
                  <article v-for="(msg, index) in conversation.replies" :key="'reply-' + index" class="mail-conv__card mail-conv__card--firm">
                    <p class="mail-conv__meta">
                      {{ formatThreadAt(msg.at) }}
                      <template v-if="msg.from"> · {{ msg.from }}</template>
                      <template v-if="msg.subject"> · {{ msg.subject }}</template>
                    </p>
                    <div v-if="isHtmlMail(msg.text)" class="mail-conv__html" v-html="mailBodyHtml(msg.text)"></div>
                    <pre v-else class="mail-conv__text">{{ msg.text }}</pre>
                  </article>
                  <p v-if="!conversation.replies.length" class="muted">
                    {{ t('grossanlass.beschaffung.anfragen.convReplyEmpty') }}
                  </p>
                </section>
                <section class="mail-conv__block">
                  <h3>{{ t('grossanlass.beschaffung.anfragen.convHistory') }}</h3>
                  <ol v-if="conversation.history.length" class="mail-conv__history">
                    <li v-for="(row, index) in conversation.history" :key="'hist-' + index">
                      <time v-if="row.at" :datetime="row.at">{{ formatThreadAt(row.at) }}</time>
                      <span>{{ row.text }}</span>
                    </li>
                  </ol>
                  <p v-else class="muted">{{ t('grossanlass.beschaffung.anfragen.convHistoryEmpty') }}</p>
                </section>
              </div>
            </template>
            <template v-else>
            <p
              v-if="!isReadyForMail(previewFirma)"
              class="mail-block"
            >
              {{ t('grossanlass.beschaffung.anfragen.previewBlocked') }}
            </p>
            <p class="review-hint">{{ t('grossanlass.beschaffung.anfragen.previewEditHint') }}</p>
            <ELoadingState
              v-if="isPreviewLoading"
              variant="inline"
              :message="t('grossanlass.beschaffung.anfragen.draftsPreviewLoading')"
            />
            <template v-else>
              <ETextField
                v-model="mailEditSubject"
                :label="t('grossanlass.beschaffung.anfragen.previewSubject')"
                :disabled="previewMailLocked"
                hide-details="auto"
                class="mb-2"
              />
              <div class="mail-editor">
                <TiptapEditor
                  :key="previewFirma.id"
                  v-model="mailEditBody"
                  :disabled="previewMailLocked"
                />
              </div>
              <div class="mail-positions" :class="mailPositionsClass(previewMailCheck)">
                <template v-if="previewMailCheck.unexpected.length">
                  <p>
                    {{ t('grossanlass.beschaffung.anfragen.positionsUnexpected', {
                      names: previewMailCheck.unexpected.join(', '),
                    }) }}
                  </p>
                  <p
                    v-for="row in previewMailCheck.unexpectedQuotes"
                    :key="row.name"
                    class="mail-positions__quote"
                  >
                    {{ t('grossanlass.beschaffung.anfragen.positionsUnexpectedQuote', { quote: row.quote }) }}
                  </p>
                </template>
                <p v-else-if="previewMailCheck.omitted.length">
                  {{ t('grossanlass.beschaffung.anfragen.positionsOmitted', {
                    names: previewMailCheck.omitted.join(', '),
                  }) }}
                </p>
                <p v-else-if="(livePreview?.positions?.allowed.length ?? 0) > 0">
                  {{ t('grossanlass.beschaffung.anfragen.positionsOk') }}
                </p>
                <p v-else>
                  {{ t('grossanlass.beschaffung.anfragen.positionsEmpty') }}
                </p>
              </div>
              <div v-if="previewPdfGroups.length" class="pdf-items">
                <p class="pdf-items__title">{{ t('grossanlass.beschaffung.anfragen.pdfItemsTitle') }}</p>
                <p class="muted">{{ t('grossanlass.beschaffung.anfragen.pdfItemsHint') }}</p>
                <div class="pdf-items__actions" v-if="!previewMailLocked">
                  <EButton variant="text" size="small" @click="setPreviewPdfRest">
                    {{ t('grossanlass.beschaffung.anfragen.pdfItemsRest') }}
                  </EButton>
                  <EButton variant="text" size="small" @click="setPreviewPdfAll">
                    {{ t('grossanlass.beschaffung.anfragen.pdfItemsAll') }}
                  </EButton>
                </div>
                <div v-for="group in previewPdfGroups" :key="group.category || 'all'">
                  <p v-if="group.category" class="pdf-items__cat">{{ group.category }}</p>
                  <ECheckbox
                    v-for="name in group.items"
                    :key="name"
                    :model-value="previewPdfItems.includes(name)"
                    :label="name"
                    :disabled="previewMailLocked"
                    hide-details
                    @update:model-value="(on) => togglePreviewPdf(name, !!on)"
                  />
                </div>
              </div>
            </template>
            <p
              v-for="name in previewMail.attachments"
              :key="name"
              class="mail-attach"
            >
              {{ t('grossanlass.beschaffung.anfragen.previewAttachment', { name }) }}
            </p>
            <p class="muted">{{ t('grossanlass.beschaffung.anfragen.mailTabEmptyThread') }}</p>
            </template>
            <p v-if="previewStatus === 'antwort'" class="review-hint">
              {{ t('grossanlass.beschaffung.anfragen.nextAfterReply') }}
            </p>
            <p v-if="previewStatus === 'zusage'" class="review-hint">
              {{ t('grossanlass.beschaffung.anfragen.nextAfterYes') }}
            </p>
          </template>
        </v-tabs-window-item>
      </v-tabs-window>

      <template #actions>
        <EButton variant="secondary" size="small" @click="closeFirmModal">
          {{ t('common.close') }}
        </EButton>
        <template v-if="firmModalTab === 'firm'">
          <EButton
            v-if="!previewMailLocked"
            variant="danger"
            size="small"
            :loading="isSaving"
            @click="deleteFirm(editFirma)"
          >
            {{ t('common.delete') }}
          </EButton>
          <EButton
            v-if="reviewQueue.length"
            variant="secondary"
            size="small"
            :disabled="isSaving"
            @click="advanceReview"
          >
            {{ t('grossanlass.beschaffung.anfragen.reviewSkip') }}
          </EButton>
          <EButton
            v-if="!previewMailLocked"
            variant="primary"
            size="small"
            :disabled="!editForm.name.trim()"
            :loading="isSaving"
            @click="saveEditFirm"
          >
            {{ reviewQueue.length
              ? t('grossanlass.beschaffung.anfragen.reviewSaveNext')
              : t('common.save') }}
          </EButton>
        </template>
        <template v-else>
          <EButton
            v-if="canPushPreviewDraft"
            variant="primary"
            size="small"
            :disabled="previewMailCheck.unexpected.length > 0 || !mailEditSubject.trim()"
            :loading="isDraftingOne"
            @click="createDraftFromPreview"
          >
            {{ t('grossanlass.beschaffung.anfragen.createDraftFromPreview') }}
          </EButton>
          <EButton
            variant="primary"
            size="small"
            :disabled="!previewFirma?.gmail_open_url"
            @click="openGmail"
          >
            {{ t('grossanlass.beschaffung.anfragen.openGmail') }}
          </EButton>
          <EButton
            v-if="previewStatus === 'entwurf' && canSendMail"
            variant="primary"
            size="small"
            @click="markPreviewSent"
          >
            {{ t('grossanlass.beschaffung.anfragen.markSent') }}
          </EButton>
          <EButton
            v-if="previewStatus === 'gesendet'"
            variant="secondary"
            size="small"
            @click="replyPreview"
          >
            {{ t('grossanlass.beschaffung.anfragen.simulateReply') }}
          </EButton>
          <EButton
            v-if="previewStatus === 'gesendet' || previewStatus === 'antwort' || previewStatus === 'zusage' || previewStatus === 'absage'"
            variant="primary"
            size="small"
            :disabled="!gmailStatus?.connected"
            @click="openReplyDraft"
          >
            {{ t('grossanlass.beschaffung.anfragen.replyDraft') }}
          </EButton>
          <EButton
            v-if="previewStatus === 'antwort'"
            variant="secondary"
            size="small"
            @click="rejectPreview"
          >
            {{ t('grossanlass.beschaffung.anfragen.markAbsage') }}
          </EButton>
          <EButton
            v-if="previewStatus === 'antwort'"
            variant="primary"
            size="small"
            @click="acceptPreview"
          >
            {{ t('grossanlass.beschaffung.anfragen.markZusage') }}
          </EButton>
          <EButton
            v-if="previewStatus === 'zusage'"
            variant="primary"
            size="small"
            @click="goZuteilung"
          >
            {{ t('grossanlass.beschaffung.anfragen.goZuteilung') }}
          </EButton>
        </template>
      </template>
    </EDialog>

    <EDialog
      v-model="replyDraftOpen"
      :title="t('grossanlass.beschaffung.anfragen.replyDraftTitle')"
      max-width="520"
    >
      <p class="review-hint">{{ t('grossanlass.beschaffung.anfragen.replyDraftHint') }}</p>
      <div class="reply-kinds">
        <button
          v-for="kind in replyKinds"
          :key="kind"
          type="button"
          class="reply-kind"
          :class="{ 'is-active': replyKind === kind }"
          @click="replyKind = kind"
        >
          {{ t(`grossanlass.einstellungen.anfragenEmail.kinds.${kind}`) }}
        </button>
      </div>
      <template #actions>
        <EButton variant="secondary" size="small" @click="replyDraftOpen = false">
          {{ t('common.close') }}
        </EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!replyKind || !gmailStatus?.connected"
          :loading="isReplyDrafting"
          @click="confirmReplyDraft"
        >
          {{ t('grossanlass.beschaffung.anfragen.replyDraftConfirm') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog v-model="createOpen" :title="t('grossanlass.beschaffung.anfragen.addFirm')" :max-width="640">
      <p class="review-hint">{{ t('grossanlass.beschaffung.anfragen.mailFieldsHint') }}</p>
      <div class="firm-name-wrap">
        <ETextField v-model="createForm.name" :label="t('grossanlass.beschaffung.anfragen.colFirm')" hide-details="auto" />
      </div>
      <ETextField v-model="createForm.place" :label="t('grossanlass.beschaffung.anfragen.placeLabel')" hide-details="auto" class="mb-2" />
      <ETextField v-model="createForm.website" :label="t('grossanlass.beschaffung.anfragen.websiteLabel')" hide-details="auto" class="mb-2" />
      <p class="review-hint">{{ t('grossanlass.beschaffung.anfragen.packagesLabel') }}</p>
      <GrossanlassCategoryCheckboxTree
        v-if="procurementCategories.length"
        v-model="createForm.categoryIds"
        :categories="procurementCategories"
      />
      <ETextField
        v-else
        v-model="createForm.categoriesText"
        :label="t('grossanlass.beschaffung.anfragen.packagesLabel')"
        :hint="t('grossanlass.beschaffung.anfragen.packagesHint')"
        hide-details="auto"
        class="mb-2"
      />
      <ETextField v-model="createForm.offering" :label="t('grossanlass.beschaffung.anfragen.offeringLabel')" hide-details="auto" class="mb-2" textarea rows="2" />
      <ETextField v-model="createForm.notes" :label="t('grossanlass.beschaffung.anfragen.notesLabel')" hide-details="auto" class="mb-2" textarea rows="2" />
      <div class="contact-name-row">
        <ESelect
          v-model="createForm.contactSalutation"
          :label="t('grossanlass.beschaffung.anfragen.contactSalutationLabel')"
          :items="salutationItems"
          clearable
          hide-details="auto"
        />
        <ETextField v-model="createForm.contactFirstName" :label="t('grossanlass.beschaffung.anfragen.contactFirstNameLabel')" hide-details="auto" />
        <ETextField v-model="createForm.contactLastName" :label="t('grossanlass.beschaffung.anfragen.contactLastNameLabel')" hide-details="auto" />
      </div>
      <ETextField v-model="createForm.email" :label="t('grossanlass.beschaffung.anfragen.emailLabel')" hide-details="auto" class="mb-2" />
      <ETextField v-model="createForm.phone" :label="t('grossanlass.beschaffung.anfragen.phoneLabel')" hide-details="auto" class="mb-2">
        <template #append-inner>
          <a
            v-if="firmTelHref(createForm.phone)"
            class="phone-tel-link"
            :href="firmTelHref(createForm.phone)"
          >
            {{ t('grossanlass.beschaffung.anfragen.webLookupCall') }}
          </a>
        </template>
      </ETextField>
      <GrossanlassInquiryWebLookupPanel
        v-model="createForm"
        :department-id="departmentId"
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="createOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" size="small" :disabled="!createForm.name.trim()" :loading="isSaving" @click="createFirm">
          {{ t('common.add') }}
        </EButton>
      </template>
    </EDialog>

    <EDialog
      v-model="draftsOpen"
      :title="t('grossanlass.beschaffung.anfragen.draftsTitle')"
      max-width="800"
    >
      <p class="review-hint">{{ t('grossanlass.beschaffung.anfragen.draftsHint') }}</p>
      <EButton variant="text" size="small" class="mb-2" @click="goGmailSettings">
        {{ t('grossanlass.beschaffung.anfragen.draftsOpenTemplates') }}
      </EButton>
      <div class="draft-review">
        <ul class="draft-list">
          <li
            v-for="firma in selectedDraftable"
            :key="firma.id"
            :class="{ 'is-active': draftReviewId === firma.id }"
          >
            <button type="button" class="draft-list__btn" @click="draftReviewId = firma.id">
              <strong>{{ firma.name }}</strong>
              <span>{{ firma.email }}</span>
            </button>
          </li>
        </ul>
        <div class="draft-preview">
          <ELoadingState
            v-if="isPreviewingDrafts"
            variant="inline"
            :message="t('grossanlass.beschaffung.anfragen.draftsPreviewLoading')"
          />
          <p v-else-if="draftPreviewError" class="review-hint">
            {{ t('grossanlass.beschaffung.anfragen.draftsPreviewError') }}
          </p>
          <template v-else-if="activeDraftPreview && activeDraftEdit">
            <p class="mail-kicker">{{ activeDraftPreview.to }}</p>
            <ETextField
              v-model="activeDraftEdit.subject"
              :label="t('grossanlass.beschaffung.anfragen.previewSubject')"
              hide-details="auto"
              class="mb-2"
            />
            <div class="mail-editor">
              <TiptapEditor
                :key="activeDraftPreview.inquiry_id"
                v-model="activeDraftEdit.body"
              />
            </div>
            <p
              v-for="name in draftAttachmentNames(activeDraftPreview)"
              :key="name"
              class="mail-attach"
            >
              {{ t('grossanlass.beschaffung.anfragen.previewAttachment', { name }) }}
            </p>
            <div class="mail-positions" :class="mailPositionsClass(activeDraftCheck)">
              <template v-if="activeDraftCheck.unexpected.length">
                <p>
                  {{ t('grossanlass.beschaffung.anfragen.positionsUnexpected', {
                    names: activeDraftCheck.unexpected.join(', '),
                  }) }}
                </p>
                <p
                  v-for="row in activeDraftCheck.unexpectedQuotes"
                  :key="row.name"
                  class="mail-positions__quote"
                >
                  {{ t('grossanlass.beschaffung.anfragen.positionsUnexpectedQuote', { quote: row.quote }) }}
                </p>
              </template>
              <p v-else-if="activeDraftCheck.omitted.length">
                {{ t('grossanlass.beschaffung.anfragen.positionsOmitted', {
                  names: activeDraftCheck.omitted.join(', '),
                }) }}
              </p>
              <p v-else-if="(activeDraftPreview.positions?.allowed.length ?? 0) > 0">
                {{ t('grossanlass.beschaffung.anfragen.positionsOk') }}
              </p>
              <p v-else>
                {{ t('grossanlass.beschaffung.anfragen.positionsEmpty') }}
              </p>
            </div>
            <div v-if="draftPdfGroups.length" class="pdf-items">
              <p class="pdf-items__title">{{ t('grossanlass.beschaffung.anfragen.pdfItemsTitle') }}</p>
              <p class="muted">{{ t('grossanlass.beschaffung.anfragen.pdfItemsHint') }}</p>
              <div class="pdf-items__actions">
                <EButton variant="text" size="small" @click="setDraftPdfRest">
                  {{ t('grossanlass.beschaffung.anfragen.pdfItemsRest') }}
                </EButton>
                <EButton variant="text" size="small" @click="setDraftPdfAll">
                  {{ t('grossanlass.beschaffung.anfragen.pdfItemsAll') }}
                </EButton>
              </div>
              <div v-for="group in draftPdfGroups" :key="group.category || 'all'">
                <p v-if="group.category" class="pdf-items__cat">{{ group.category }}</p>
                <ECheckbox
                  v-for="name in group.items"
                  :key="name"
                  :model-value="(activeDraftEdit?.pdfItems ?? []).includes(name)"
                  :label="name"
                  hide-details
                  @update:model-value="(on) => toggleDraftPdf(name, !!on)"
                />
              </div>
            </div>
          </template>
        </div>
      </div>
      <template #actions>
        <EButton variant="secondary" size="small" @click="draftsOpen = false">
          {{ t('common.close') }}
        </EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!gmailStatus?.connected || selectedDraftable.length === 0 || activeDraftCheck.unexpected.length > 0"
          :loading="isDrafting"
          @click="confirmDrafts"
        >
          {{ t('grossanlass.beschaffung.anfragen.draftsConfirm') }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onActivated, onDeactivated, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { useBackgroundPoll } from '@/composables/useBackgroundPoll'
import { useAuthStore } from '@/stores/auth'
import {
  gaCanConnectGmail,
  gaCanCreateMailDrafts,
  gaCanManageProcurement,
  gaCanSendMail,
  gaCanTakeInquiry,
} from '@/utils/grossanlassAccess'
import { EButton, ECheckbox, EDialog, ESearchField, ESelect, ETextField } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import ELoadingState from '@/components/layout/ELoadingState.vue'
import TiptapEditor from '@/components/site/TiptapEditor.vue'
import GrossanlassCategoryCheckboxTree from '@/components/grossanlass/GrossanlassCategoryCheckboxTree.vue'
import GrossanlassProcurementCategoryManager from '@/components/grossanlass/GrossanlassProcurementCategoryManager.vue'
import {
  defaultPdfItemLabels,
  matchInquiryMailPositions,
  type InquiryMailPositionCheck,
} from '@/utils/grossanlassInquiryMailPositions'
import {
  assignGrossanlassGmailUnmatched,
  createGrossanlassInquiry,
  createGrossanlassInquiryDrafts,
  createGrossanlassInquiryReplyDraft,
  deleteGrossanlassInquiries,
  deleteGrossanlassInquiry,
  discardGrossanlassGmailUnmatched,
  geocodeGrossanlassInquiries,
  getGrossanlassGmailUnmatched,
  getGrossanlassInquiries,
  getGrossanlassInquiryConversation,
  importGrossanlassInquiryCsv,
  importGrossanlassInquiryTips,
  markGrossanlassInquiriesSent,
  recordGrossanlassInquiryReply,
  syncGrossanlassInquiryGmail,
  unmatchedToGrossanlassInquiry,
  updateGrossanlassInquiry,
  type GrossanlassGmailUnmatched,
  type GrossanlassInquiry,
  type GrossanlassInquiryConversation,
} from '@/api/grossanlassInquiries'
import { getAddress } from '@/api/addresses'
import { getGrossanlassPlanung } from '@/api/grossanlassPlanung'
import GrossanlassInquiryMap, { type InquiryMapPin } from '@/components/grossanlass/GrossanlassInquiryMap.vue'
import GrossanlassInquiryWebLookupPanel from '@/components/grossanlass/GrossanlassInquiryWebLookupPanel.vue'
import {
  getGrossanlassGmailStatus,
  previewGrossanlassMail,
  previewGrossanlassMails,
  type GrossanlassGmailStatus,
  type GrossanlassMailBatchPreview,
  type GrossanlassMailPreview,
} from '@/api/grossanlassGmail'
import {
  listGrossanlassProcurementCategories,
  type GrossanlassProcurementCategory,
} from '@/api/grossanlassProcurement'
import { sanitizeMailHtml } from '@/utils/sanitizeHtml'
import {
  isPackageProcurementCategory,
  leafIdsOfProcurementCategorySelection,
  normalizeProcurementCategorySelection,
  selectionMatchesProcurementCategoryFilter,
  toggleProcurementCategorySelection,
} from '@/utils/grossanlassProcurementCategoryTree'

type InquiryMailPhase =
  | 'kein_entwurf'
  | 'entwurf'
  | 'gmail_entwurf'
  | 'gesendet'
  | 'antwort'
  | 'zusage'
  | 'absage'
  | 'vorschlag'

type FirmFormFields = {
  name: string
  place: string
  website: string
  offering: string
  notes: string
  contactSalutation: '' | 'herr' | 'frau'
  contactFirstName: string
  contactLastName: string
  email: string
  phone: string
  categoryIds: string[]
  categoriesText: string
}

function emptyFirmForm(categoryId?: string): FirmFormFields {
  return {
    name: '',
    place: '',
    website: '',
    offering: '',
    notes: '',
    contactSalutation: '',
    contactFirstName: '',
    contactLastName: '',
    email: '',
    phone: '',
    categoryIds: categoryId
      ? toggleProcurementCategorySelection(procurementCategories.value, [], categoryId, true)
      : [],
    categoriesText: '',
  }
}

function assignFirmForm(target: FirmFormFields, source: FirmFormFields) {
  target.name = source.name
  target.place = source.place
  target.website = source.website
  target.offering = source.offering
  target.notes = source.notes
  target.contactSalutation = source.contactSalutation
  target.contactFirstName = source.contactFirstName
  target.contactLastName = source.contactLastName
  target.email = source.email
  target.phone = source.phone
  target.categoryIds = [...source.categoryIds]
  target.categoriesText = source.categoriesText
}

function splitContactName(full: string): { first: string; last: string } {
  const trimmed = full.trim().replace(/\s+/g, ' ')
  if (!trimmed) return { first: '', last: '' }
  const i = trimmed.indexOf(' ')
  if (i < 0) return { first: trimmed, last: '' }
  return { first: trimmed.slice(0, i), last: trimmed.slice(i + 1).trim() }
}

function inquiryContactParts(firma: GrossanlassInquiry): {
  salutation: '' | 'herr' | 'frau'
  first: string
  last: string
  full: string
} {
  let first = (firma.contact_first_name || '').trim()
  let last = (firma.contact_last_name || '').trim()
  if (!first && !last && firma.contact_name) {
    const split = splitContactName(firma.contact_name)
    first = split.first
    last = split.last
  }
  const raw = (firma.contact_salutation || '').trim().toLowerCase()
  const salutation: '' | 'herr' | 'frau' = raw === 'herr' || raw === 'frau' ? raw : ''
  const label = salutation === 'herr' ? 'Herr' : salutation === 'frau' ? 'Frau' : ''
  const full = [label, first, last].filter(Boolean).join(' ') || (firma.contact_name || '').trim()
  return { salutation, first, last, full }
}

function formFromInquiry(firma: GrossanlassInquiry): FirmFormFields {
  const contact = inquiryContactParts(firma)
  return {
    name: firma.name,
    place: firma.place,
    website: firma.website || '',
    offering: firma.offering || '',
    notes: firma.notes || '',
    contactSalutation: contact.salutation,
    contactFirstName: contact.first,
    contactLastName: contact.last,
    email: firma.email,
    phone: firma.phone || '',
    categoryIds: normalizeProcurementCategorySelection(procurementCategories.value, firma.category_ids),
    categoriesText: '',
  }
}

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const canConnectGmail = computed(() => gaCanConnectGmail(authStore.currentDepartmentRole))
const canCreateMailDrafts = computed(() => gaCanCreateMailDrafts(authStore.currentDepartmentRole))
const canSendMail = computed(() => gaCanSendMail(authStore.currentDepartmentRole))
const canTakeInquiry = computed(() => gaCanTakeInquiry(authStore.currentDepartmentRole))
const canManageProcurement = computed(() => gaCanManageProcurement(authStore.currentDepartmentRole))
const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()

const salutationItems = computed(() => [
  { title: t('grossanlass.beschaffung.anfragen.salutationHerr'), value: 'herr' },
  { title: t('grossanlass.beschaffung.anfragen.salutationFrau'), value: 'frau' },
])

function categoryLabel(categoryId: string): string {
  const rows = procurementCategories.value
  const byId = rows.find((row) => row.id === categoryId)
  if (byId) return byId.name
  const lower = categoryId.trim().toLowerCase()
  const byName = rows.find((row) => row.name.trim().toLowerCase() === lower)
  return byName?.name ?? categoryId
}

const MAIL_ITEM_CHIP_LIMIT = 8

function firmMailItemIds(firma: GrossanlassInquiry): string[] {
  const ids = leafIdsOfProcurementCategorySelection(procurementCategories.value, firma.category_ids)
  return ids.slice(0, MAIL_ITEM_CHIP_LIMIT)
}

function firmMailItemExtra(firma: GrossanlassInquiry): number {
  const ids = leafIdsOfProcurementCategorySelection(procurementCategories.value, firma.category_ids)
  return Math.max(0, ids.length - MAIL_ITEM_CHIP_LIMIT)
}

function firmMatchesCategoryFilter(firma: GrossanlassInquiry, filterId: string): boolean {
  return selectionMatchesProcurementCategoryFilter(
    procurementCategories.value,
    firma.category_ids,
    filterId,
  )
}

const departmentId = computed(
  () => (route.params.departmentId as string) || authStore.activeDepartmentId || '',
)

const view = ref<'firms' | 'category'>('firms')
const openSections = ref<string[]>(['firms'])
const query = ref('')
type FirmSortKey = 'name' | 'place' | 'reference' | 'packages' | 'status'
const firmSortKey = ref<FirmSortKey>('name')
const firmSortDir = ref<'asc' | 'desc'>('asc')
const selected = ref<string[]>([])
const firmModalOpen = ref(false)
const firmModalTab = ref<'firm' | 'mail'>('mail')
const draftsOpen = ref(false)
const createOpen = ref(false)
const isLoading = ref(true)
const isSaving = ref(false)
const isImporting = ref(false)
const isCsvImporting = ref(false)
const csvInput = ref<HTMLInputElement | null>(null)
const isDrafting = ref(false)
const isDraftingOne = ref(false)
const isPreviewLoading = ref(false)
const conversationLoading = ref(false)
const conversation = ref<GrossanlassInquiryConversation>({
  sent: [],
  replies: [],
  history: [],
  gmail_open_url: null,
})
const mailEditSubject = ref('')
const mailEditBody = ref('')
const previewPdfItems = ref<string[]>([])
const previewPdfTouched = ref(false)
const draftEdits = reactive<Record<string, { subject: string; body: string; pdfItems: string[]; pdfTouched: boolean }>>({})
const isSyncing = ref(false)
const firms = ref<GrossanlassInquiry[]>([])
const unmatched = ref<GrossanlassGmailUnmatched[]>([])
const assignTarget = reactive<Record<string, string>>({})
const unmatchedBusy = ref<string | null>(null)
const replyDraftOpen = ref(false)
const replyKind = ref('praezisieren')
const isReplyDrafting = ref(false)
const TAKE_REPLY_KINDS = ['nehmen', 'nicht_genommen', 'zusage_ok', 'dank_absage'] as const
const ALL_REPLY_KINDS = ['praezisieren', 'zusage_ok', 'dank_absage', 'nicht_genommen', 'nachfassen', 'nehmen'] as const
const replyKinds = computed(() =>
  canTakeInquiry.value
    ? [...ALL_REPLY_KINDS]
    : ALL_REPLY_KINDS.filter((kind) => !(TAKE_REPLY_KINDS as readonly string[]).includes(kind)),
)
const previewFirma = ref<GrossanlassInquiry | null>(null)
const gmailStatus = ref<GrossanlassGmailStatus | null>(null)
const livePreview = ref<GrossanlassMailPreview | null>(null)
const draftPreviews = ref<GrossanlassMailBatchPreview[]>([])
const draftReviewId = ref<string | null>(null)
const isPreviewingDrafts = ref(false)
const draftPreviewError = ref(false)
const createForm = reactive(emptyFirmForm())
const procurementCategories = ref<GrossanlassProcurementCategory[]>([])
const categoriesLoadError = ref('')
const categoryFilter = ref('')
const emailFilter = ref<'' | 'ready' | 'missing'>('')
const statusFilter = ref('')
const pageOpen = ref(true)
const editFirma = ref<GrossanlassInquiry | null>(null)
const editForm = reactive(emptyFirmForm())
const radiusKm = ref(30)
const isGeocoding = ref(false)
const venuePin = ref<{ latitude: number; longitude: number; label: string } | null>(null)
const reviewKind = ref<'nocat' | 'nomail' | null>(null)
const reviewQueue = ref<string[]>([])
const reviewIndex = ref(0)

const PHASE_COLORS: Record<InquiryMailPhase, string> = {
  kein_entwurf: '#94a3b8',
  entwurf: '#3b82f6',
  gmail_entwurf: '#6366f1',
  gesendet: '#0891b2',
  antwort: '#ca8a04',
  zusage: '#16a34a',
  absage: '#dc2626',
  vorschlag: '#9333ea',
}

function haversineKm(
  a: { latitude: number; longitude: number },
  b: { latitude: number; longitude: number },
): number {
  const toRad = (deg: number) => (deg * Math.PI) / 180
  const dLat = toRad(b.latitude - a.latitude)
  const dLng = toRad(b.longitude - a.longitude)
  const sinLat = Math.sin(dLat / 2)
  const sinLng = Math.sin(dLng / 2)
  const h = sinLat * sinLat + Math.cos(toRad(a.latitude)) * Math.cos(toRad(b.latitude)) * sinLng * sinLng
  return 6371 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h))
}

const categoryPickRows = computed(() => {
  const rows = [...procurementCategories.value]
  const out: { id: string; name: string; depth: number }[] = []
  const childrenOf = (parentId: string | null) =>
    rows
      .filter((cat) => (cat.parent_id ?? null) === parentId)
      .sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name, 'de'))
  const walk = (parentId: string | null, depth: number) => {
    for (const cat of childrenOf(parentId)) {
      out.push({ id: cat.id, name: cat.name, depth })
      walk(cat.id, depth + 1)
    }
  }
  walk(null, 0)
  const seen = new Set(out.map((row) => row.id))
  for (const cat of rows) {
    if (!seen.has(cat.id)) {
      out.push({ id: cat.id, name: cat.name, depth: cat.parent_id ? 1 : 0 })
    }
  }
  return out.filter((row) => isPackageProcurementCategory(rows, row.id))
})

const categoryPanelCount = computed(() => {
  if (categoryFilter.value === '_none') return t('grossanlass.beschaffung.anfragen.noPackage')
  if (categoryFilter.value) return categoryLabel(categoryFilter.value)
  return String(categoryPickRows.value.length)
})

const statusKeys: InquiryMailPhase[] = [
  'kein_entwurf',
  'entwurf',
  'gmail_entwurf',
  'gesendet',
  'antwort',
  'zusage',
  'absage',
  'vorschlag',
]

function mailPositionsClass(check: InquiryMailPositionCheck): string {
  if (check.unexpected.length) return 'mail-positions--bad'
  if (check.omitted.length) return 'mail-positions--warn'
  return 'mail-positions--ok'
}

function isReadyForMail(firma: GrossanlassInquiry): boolean {
  return !!firma.email && firma.category_ids.length > 0
}

function inquiryMailPhase(firma: GrossanlassInquiry): InquiryMailPhase {
  if (firma.status === 'entwurf') {
    if (firma.gmail_draft_id) return 'gmail_entwurf'
    if (isReadyForMail(firma)) return 'entwurf'
    return 'kein_entwurf'
  }
  return firma.status
}

function inquiryMailLocked(firma: GrossanlassInquiry): boolean {
  return ['gesendet', 'antwort', 'zusage', 'absage'].includes(inquiryMailPhase(firma))
}

function formatThreadAt(iso?: string): string {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return iso
  return date.toLocaleString('de-CH', { dateStyle: 'short', timeStyle: 'short' })
}

function isHtmlMail(text?: string): boolean {
  const value = (text ?? '').trim()
  return /<[a-z][\s\S]*>/i.test(value)
}

function mailBodyHtml(text?: string): string {
  return sanitizeMailHtml(text ?? '')
}

function emptyConversation(): GrossanlassInquiryConversation {
  return { sent: [], replies: [], history: [], gmail_open_url: null }
}

function canDraft(firma: GrossanlassInquiry): boolean {
  return isReadyForMail(firma) && !inquiryMailLocked(firma)
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  try {
    firms.value = await getGrossanlassInquiries(departmentId.value)
    gmailStatus.value = await getGrossanlassGmailStatus(departmentId.value)
    try {
      categoriesLoadError.value = ''
      procurementCategories.value = await listGrossanlassProcurementCategories(departmentId.value)
    } catch (e: unknown) {
      procurementCategories.value = []
      const err = e as { response?: { data?: { error?: string }; status?: number } }
      categoriesLoadError.value = err.response?.data?.error
        || t('grossanlass.beschaffung.anfragen.categoriesLoadError')
    }
    applySystemCategoryQuery()
    try {
      if (gmailStatus.value?.connected) {
        unmatched.value = await getGrossanlassGmailUnmatched(departmentId.value)
      }
    } catch {
      unmatched.value = []
    }
    await loadVenuePin()
    if (unmappedCount.value > 0) void geocodeMissingPlaces()
    openMailIfUnmatched()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.loadError'))
  } finally {
    isLoading.value = false
  }
}

function goCategorySettings() {
  void router.push(`/${departmentId.value}/einstellungen/kategorien`)
}

function applySystemCategoryQuery() {
  if (String(route.query.system || '') !== 'js') return
  const js = procurementCategories.value.find((cat) => cat.system_key === 'js')
  if (js) categoryFilter.value = js.id
}

function replaceFirm(next: GrossanlassInquiry) {
  firms.value = firms.value.map((row) => (row.id === next.id ? next : row))
  if (previewFirma.value?.id === next.id) previewFirma.value = next
}

function onMapSelect(id: string) {
  const firma = firms.value.find((row) => row.id === id)
  if (firma) void openFirmModal(firma, 'mail')
}

function selectMapVisible() {
  selected.value = [...new Set([...selected.value, ...mapSelectableIds.value])]
  if (!openSections.value.includes('firms')) {
    openSections.value = [...openSections.value, 'firms']
  }
}

async function geocodeMissingPlaces() {
  if (!departmentId.value || isGeocoding.value) return
  isGeocoding.value = true
  try {
    for (let i = 0; i < 20; i++) {
      const result = await geocodeGrossanlassInquiries(departmentId.value)
      for (const row of result.updated) replaceFirm(row)
      if (result.geocoded === 0) break
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isGeocoding.value = false
  }
}

async function loadVenuePin() {
  if (!departmentId.value) {
    venuePin.value = null
    return
  }
  try {
    const pack = await getGrossanlassPlanung(departmentId.value)
    const venueId = pack.config?.venue_address_id
    if (!venueId) {
      venuePin.value = null
      return
    }
    const { address } = await getAddress(venueId)
    if (address.latitude == null || address.longitude == null) {
      venuePin.value = null
      return
    }
    venuePin.value = {
      latitude: address.latitude,
      longitude: address.longitude,
      label: address.name || address.city || t('grossanlass.planung.stammdaten.location'),
    }
  } catch {
    venuePin.value = null
  }
}

function compareFirmText(a: string, b: string): number {
  const left = a.trim()
  const right = b.trim()
  if (!left && !right) return 0
  if (!left) return 1
  if (!right) return -1
  return left.localeCompare(right, 'de', { sensitivity: 'base', numeric: true })
}

function firmPackagesKey(firma: GrossanlassInquiry): string {
  return leafIdsOfProcurementCategorySelection(procurementCategories.value, firma.category_ids)
    .map((id) => categoryLabel(id))
    .join(', ')
}

function compareFirms(a: GrossanlassInquiry, b: GrossanlassInquiry): number {
  let cmp = 0
  if (firmSortKey.value === 'name') {
    cmp = compareFirmText(a.name, b.name)
  } else if (firmSortKey.value === 'place') {
    cmp = compareFirmText(a.place, b.place)
  } else if (firmSortKey.value === 'reference') {
    cmp = compareFirmText(a.reference || a.id, b.reference || b.id)
  } else if (firmSortKey.value === 'packages') {
    cmp = compareFirmText(firmPackagesKey(a), firmPackagesKey(b))
  } else {
    cmp = statusKeys.indexOf(inquiryMailPhase(a)) - statusKeys.indexOf(inquiryMailPhase(b))
  }
  if (cmp === 0) cmp = compareFirmText(a.name, b.name)
  if (cmp === 0) cmp = a.id.localeCompare(b.id)
  return firmSortDir.value === 'asc' ? cmp : -cmp
}

function toggleFirmSort(key: FirmSortKey) {
  if (firmSortKey.value === key) {
    firmSortDir.value = firmSortDir.value === 'asc' ? 'desc' : 'asc'
    return
  }
  firmSortKey.value = key
  firmSortDir.value = 'asc'
}

function firmSortAria(key: FirmSortKey): 'none' | 'ascending' | 'descending' {
  if (firmSortKey.value !== key) return 'none'
  return firmSortDir.value === 'asc' ? 'ascending' : 'descending'
}

function firmSortMark(key: FirmSortKey): string {
  if (firmSortKey.value !== key) return ''
  return firmSortDir.value === 'asc' ? '↑' : '↓'
}

const firmsWithoutCategory = computed(() =>
  firms.value.filter((firma) => firma.category_ids.length === 0),
)
const firmsWithoutEmail = computed(() =>
  firms.value.filter((firma) => !firma.email.trim()),
)
const firmModalTitle = computed(() => {
  const name = previewFirma.value?.name
    || t('grossanlass.beschaffung.anfragen.editFirmTitle', { name: '' })
  if (!reviewQueue.value.length) return name
  return `${name} · ${t('grossanlass.beschaffung.anfragen.reviewProgress', {
    current: reviewIndex.value + 1,
    total: reviewQueue.value.length,
  })}`
})

const filteredFirms = computed(() => {
  const q = query.value.trim().toLowerCase()
  return firms.value.filter((firma) => {
    if (categoryFilter.value === '_none' && firma.category_ids.length) return false
    if (categoryFilter.value && categoryFilter.value !== '_none' && !firmMatchesCategoryFilter(firma, categoryFilter.value)) {
      return false
    }
    if (emailFilter.value === 'missing' && firma.email) return false
    if (emailFilter.value === 'ready' && !isReadyForMail(firma)) return false
    if (statusFilter.value && inquiryMailPhase(firma) !== statusFilter.value) return false
    if (!q) return true
    const packages = firma.category_ids.map((id) => categoryLabel(id)).join(' ')
    const submitter = firma.tip_submitted_by
      ? `${firma.tip_submitted_by.name} ${firma.tip_submitted_by.email}`
      : ''
    return `${firma.name} ${firma.place} ${firma.website} ${firma.offering} ${firma.notes} ${inquiryContactParts(firma).full} ${firma.email} ${firma.phone} ${packages} ${firma.tip_from || ''} ${submitter}`
      .toLowerCase()
      .includes(q)
  })
})

const sortedFirms = computed(() => [...filteredFirms.value].sort(compareFirms))

const selectableVisibleIds = computed(() =>
  filteredFirms.value.filter((firma) => !inquiryMailLocked(firma)).map((firma) => firma.id),
)
const allVisibleSelected = computed(
  () =>
    selectableVisibleIds.value.length > 0
    && selectableVisibleIds.value.every((id) => selected.value.includes(id)),
)
const someVisibleSelected = computed(
  () => selectableVisibleIds.value.some((id) => selected.value.includes(id)),
)
const selectedFirms = computed(() => firms.value.filter((firma) => selected.value.includes(firma.id)))
const selectedDraftable = computed(() => selectedFirms.value.filter(canDraft))
const selectedDeletable = computed(() => selectedFirms.value.filter((firma) => !inquiryMailLocked(firma)))

const unmappedCount = computed(
  () => firms.value.filter((firma) => firma.place && (firma.latitude == null || firma.longitude == null)).length,
)

const firmsInRadius = computed(() => {
  const venue = venuePin.value
  const km = radiusKm.value
  return filteredFirms.value.filter((firma) => {
    if (firma.latitude == null || firma.longitude == null) return false
    if (!venue || !km) return true
    return haversineKm(venue, { latitude: firma.latitude, longitude: firma.longitude }) <= km
  })
})

const mapPins = computed<InquiryMapPin[]>(() =>
  firmsInRadius.value
    .filter((firma) => firma.latitude != null && firma.longitude != null)
    .map((firma) => ({
      id: firma.id,
      label: firma.name,
      latitude: firma.latitude as number,
      longitude: firma.longitude as number,
      color: PHASE_COLORS[inquiryMailPhase(firma)],
      meta: [firma.place, firma.category_ids.map((id) => categoryLabel(id)).join(', ')].filter(Boolean).join(' · '),
    })),
)

const mapSelectableIds = computed(() => firmsInRadius.value.filter(canDraft).map((firma) => firma.id))

function openMailIfUnmatched() {
  if (!unmatched.value.length || openSections.value.includes('mail')) return
  openSections.value = [...openSections.value, 'mail']
}

const statusCounts = computed(() =>
  statusKeys
    .map((status) => ({
      status,
      count: firms.value.filter((firma) => inquiryMailPhase(firma) === status).length,
    }))
    .filter((row) => row.count > 0),
)

const previewMail = computed(() => {
  if (livePreview.value) {
    const raw = (livePreview.value.attachments ?? []).filter(Boolean)
    if (!raw.length && livePreview.value.attachment_filename) {
      raw.push(livePreview.value.attachment_filename)
    }
    const eventFiles = raw.filter((name) => !name.startsWith('Materialliste-'))
    const materialFile = raw.find((name) => name.startsWith('Materialliste-'))
    const attachments = [...eventFiles]
    if (previewPdfItems.value.length && materialFile) {
      attachments.push(materialFile)
    } else if (previewPdfItems.value.length) {
      attachments.push('Materialliste.pdf')
    }
    return {
      subject: livePreview.value.subject,
      body: livePreview.value.body,
      attachments,
    }
  }
  return { subject: '', body: '', attachments: [] as string[] }
})

function draftAttachmentNames(row: GrossanlassMailBatchPreview): string[] {
  const names = (row.attachments ?? []).filter(Boolean)
  if (!names.length && row.attachment_filename) names.push(row.attachment_filename)
  const edit = draftEdits[row.inquiry_id]
  const eventFiles = names.filter((name) => !name.startsWith('Materialliste-'))
  const materialFile = names.find((name) => name.startsWith('Materialliste-'))
  const pdfItems = edit?.pdfItems ?? []
  if (!pdfItems.length) return eventFiles
  return [...eventFiles, materialFile || 'Materialliste.pdf']
}

function positionGroups(
  preview: { positions?: GrossanlassMailPreview['positions'] } | null,
): { category: string; items: string[] }[] {
  const groups = preview?.positions?.groups
  if (groups?.length) {
    return groups.filter((row) => row.items.length)
  }
  const allowed = preview?.positions?.allowed ?? []
  return allowed.length ? [{ category: '', items: allowed }] : []
}

const previewPdfGroups = computed(() => positionGroups(livePreview.value))
const draftPdfGroups = computed(() => positionGroups(activeDraftPreview.value))

function syncPreviewPdfFromBody() {
  if (previewPdfTouched.value) return
  previewPdfItems.value = defaultPdfItemLabels(
    livePreview.value?.positions?.allowed ?? [],
    previewMailCheck.value.omitted,
  )
}

function togglePreviewPdf(name: string, on: boolean) {
  previewPdfTouched.value = true
  if (on) {
    if (!previewPdfItems.value.includes(name)) previewPdfItems.value = [...previewPdfItems.value, name]
    return
  }
  previewPdfItems.value = previewPdfItems.value.filter((item) => item !== name)
}

function setPreviewPdfRest() {
  previewPdfTouched.value = true
  previewPdfItems.value = [...previewMailCheck.value.omitted]
}

function setPreviewPdfAll() {
  previewPdfTouched.value = true
  previewPdfItems.value = [...(livePreview.value?.positions?.allowed ?? [])]
}

function toggleDraftPdf(name: string, on: boolean) {
  const id = activeDraftPreview.value?.inquiry_id
  const edit = id ? draftEdits[id] : null
  if (!edit) return
  edit.pdfTouched = true
  if (on) {
    if (!edit.pdfItems.includes(name)) edit.pdfItems = [...edit.pdfItems, name]
    return
  }
  edit.pdfItems = edit.pdfItems.filter((item) => item !== name)
}

function setDraftPdfRest() {
  const id = activeDraftPreview.value?.inquiry_id
  const edit = id ? draftEdits[id] : null
  if (!edit) return
  edit.pdfTouched = true
  edit.pdfItems = [...activeDraftCheck.value.omitted]
}

function setDraftPdfAll() {
  const id = activeDraftPreview.value?.inquiry_id
  const edit = id ? draftEdits[id] : null
  if (!edit) return
  edit.pdfTouched = true
  edit.pdfItems = [...(activeDraftPreview.value?.positions?.allowed ?? [])]
}

const previewMailCheck = computed(() =>
  matchInquiryMailPositions(
    mailEditBody.value,
    livePreview.value?.positions?.allowed ?? [],
    livePreview.value?.positions?.other ?? [],
  ),
)

function pdfItemsFromPreview(
  body: string,
  preview: { positions?: GrossanlassMailPreview['positions'] } | null,
): string[] {
  const allowed = preview?.positions?.allowed ?? []
  const check = matchInquiryMailPositions(body, allowed, preview?.positions?.other ?? [])
  return defaultPdfItemLabels(allowed, check.omitted)
}

watch([mailEditBody, livePreview], () => {
  syncPreviewPdfFromBody()
})

watch(
  draftEdits,
  () => {
    for (const row of draftPreviews.value) {
      const edit = draftEdits[row.inquiry_id]
      if (!edit || edit.pdfTouched) continue
      const next = pdfItemsFromPreview(edit.body, row)
      if (edit.pdfItems.join('\u0001') !== next.join('\u0001')) {
        edit.pdfItems = next
      }
    }
  },
  { deep: true },
)

const canPushPreviewDraft = computed(() => {
  const firma = previewFirma.value
  if (!firma || !canCreateMailDrafts.value) return false
  const phase = inquiryMailPhase(firma)
  return isReadyForMail(firma) && ['kein_entwurf', 'entwurf', 'gmail_entwurf', 'vorschlag'].includes(phase)
})

const activeDraftPreview = computed(
  () => draftPreviews.value.find((row) => row.inquiry_id === draftReviewId.value) ?? draftPreviews.value[0] ?? null,
)

const activeDraftEdit = computed(() => {
  const id = activeDraftPreview.value?.inquiry_id
  if (!id) return null
  return draftEdits[id] ?? null
})

const activeDraftCheck = computed(() =>
  matchInquiryMailPositions(
    activeDraftEdit.value?.body ?? '',
    activeDraftPreview.value?.positions?.allowed ?? [],
    activeDraftPreview.value?.positions?.other ?? [],
  ),
)

const previewStatus = computed(() => previewFirma.value?.status ?? 'entwurf')
const previewThread = computed(() => previewFirma.value?.thread ?? [])
const previewMailLocked = computed(() =>
  previewFirma.value ? inquiryMailLocked(previewFirma.value) : false,
)
const firmMailTabLabel = computed(() =>
  previewThread.value.length
    ? t('grossanlass.beschaffung.anfragen.tabMailThread')
    : t('grossanlass.beschaffung.anfragen.tabMailDraft'),
)

const categoryBlocks = computed(() => {
  const byId = new Map<string, GrossanlassInquiry[]>()
  const packageCats = procurementCategories.value.filter((cat) =>
    isPackageProcurementCategory(procurementCategories.value, cat.id),
  )
  for (const firma of sortedFirms.value) {
    const keys = firma.category_ids.length
      ? packageCats.filter((cat) => firmMatchesCategoryFilter(firma, cat.id)).map((cat) => cat.id)
      : ['_none']
    const uniqueKeys = keys.length ? keys : ['_none']
    for (const key of uniqueKeys) {
      const list = byId.get(key) ?? []
      list.push(firma)
      byId.set(key, list)
    }
  }
  const blocks = packageCats.map((cat) => ({
    id: cat.id,
    label: `${cat.parent_id ? '↳ ' : ''}${cat.name}`,
    firms: byId.get(cat.id) ?? [],
  }))
  const none = byId.get('_none') ?? []
  if (none.length) {
    blocks.push({
      id: '_none',
      label: t('grossanlass.beschaffung.anfragen.noPackage'),
      firms: none,
    })
  }
  const known = new Set(blocks.map((row) => row.id))
  for (const [id, list] of byId) {
    if (known.has(id)) continue
    blocks.push({ id, label: categoryLabel(id), firms: list })
  }
  if (categoryFilter.value) {
    return blocks.filter((row) => row.id === categoryFilter.value)
  }
  return blocks
})

function toggle(id: string) {
  const firma = firms.value.find((row) => row.id === id)
  if (firma && inquiryMailLocked(firma)) return
  selected.value = selected.value.includes(id)
    ? selected.value.filter((item) => item !== id)
    : [...selected.value, id]
}

function toggleAllVisible() {
  if (allVisibleSelected.value) {
    selected.value = selected.value.filter((id) => !selectableVisibleIds.value.includes(id))
    return
  }
  selected.value = [...new Set([...selected.value, ...selectableVisibleIds.value])]
}

function goGmailSettings() {
  const dept = departmentId.value
  if (!dept) return
  void router.push(`/${dept}/einstellungen/anfragen-email`)
}

function goZuteilung() {
  firmModalOpen.value = false
  const dept = departmentId.value
  if (!dept) return
  void router.push(`/${dept}/beschaffung/zusagen`)
}

function firmMeta(firma: GrossanlassInquiry): string {
  return [
    inquiryContactParts(firma).full,
    firma.phone,
    firma.email || t('grossanlass.beschaffung.anfragen.missingEmail'),
  ].filter(Boolean).join(' · ')
}

function firmTelHref(phone: string): string {
  const compact = phone.trim().replace(/[^\d+]/g, '')
  const digits = compact.replace(/\D/g, '')
  if (digits.length < 6) return ''
  return `tel:${compact}`
}

function submitterMailto(firma: GrossanlassInquiry): string {
  const email = firma.tip_submitted_by?.email?.trim() || ''
  if (!email) return ''
  const subject = encodeURIComponent(`eMatChef: Firma «${firma.name}»`)
  return `mailto:${email}?subject=${subject}`
}

function firmWritePayload(form: FirmFormFields) {
  return {
    name: form.name.trim(),
    place: form.place.trim(),
    website: form.website.trim(),
    offering: form.offering.trim(),
    notes: form.notes.trim(),
    contact_salutation: form.contactSalutation || '',
    contact_first_name: form.contactFirstName.trim(),
    contact_last_name: form.contactLastName.trim(),
    email: form.email.trim(),
    phone: form.phone.trim(),
    category_ids: procurementCategories.value.length ? form.categoryIds : form.categoriesText,
  }
}

function openCreate(categoryId?: string) {
  assignFirmForm(createForm, emptyFirmForm(categoryId))
  createOpen.value = true
}

function stopReview() {
  reviewKind.value = null
  reviewQueue.value = []
  reviewIndex.value = 0
}

function closeFirmModal() {
  firmModalOpen.value = false
  conversation.value = emptyConversation()
  livePreview.value = null
}

function startReview(kind: 'nocat' | 'nomail') {
  const source = kind === 'nocat' ? firmsWithoutCategory.value : firmsWithoutEmail.value
  const list = [...source].sort((a, b) => a.name.localeCompare(b.name, 'de'))
  if (list.length === 0) {
    toast.info(t('grossanlass.beschaffung.anfragen.reviewEmpty'))
    return
  }
  if (kind === 'nocat') {
    categoryFilter.value = '_none'
    emailFilter.value = ''
  } else {
    emailFilter.value = 'missing'
    categoryFilter.value = ''
  }
  statusFilter.value = ''
  reviewKind.value = kind
  reviewQueue.value = list.map((firma) => firma.id)
  reviewIndex.value = 0
  void openFirmModal(list[0], 'firm')
}

function advanceReview() {
  if (reviewQueue.value.length === 0) {
    firmModalOpen.value = false
    return
  }
  let nextIndex = reviewIndex.value + 1
  while (nextIndex < reviewQueue.value.length) {
    const next = firms.value.find((firma) => firma.id === reviewQueue.value[nextIndex])
    if (next) {
      reviewIndex.value = nextIndex
      void openFirmModal(next, 'firm')
      return
    }
    nextIndex += 1
  }
  stopReview()
  firmModalOpen.value = false
  toast.success(t('grossanlass.beschaffung.anfragen.reviewDone'))
}

function openEditFirm(firma: GrossanlassInquiry) {
  void openFirmModal(firma, inquiryMailLocked(firma) ? 'mail' : 'firm')
}

async function saveEditFirm() {
  if (!departmentId.value || !editFirma.value || !editForm.name.trim()) return
  isSaving.value = true
  try {
    const next = await updateGrossanlassInquiry(departmentId.value, editFirma.value.id, firmWritePayload(editForm))
    replaceFirm(next)
    previewFirma.value = next
    editFirma.value = next
    toast.success(t('grossanlass.beschaffung.anfragen.createdToast'))
    if (reviewQueue.value.length) {
      advanceReview()
    } else {
      void loadFirmMailPreview(next)
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isSaving.value = false
  }
}

async function deleteFirm(firma: GrossanlassInquiry | null) {
  if (!firma || !departmentId.value) return
  const ok = await confirm.confirm({
    title: t('grossanlass.beschaffung.anfragen.deleteTitle'),
    message: t('grossanlass.beschaffung.anfragen.deleteMessage', { name: firma.name }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  isSaving.value = true
  try {
    await deleteGrossanlassInquiry(departmentId.value, firma.id)
    const reviewing = reviewQueue.value.length > 0
    removeFirmsFromList([firma.id], reviewing)
    toast.success(t('grossanlass.beschaffung.anfragen.deleteToast'))
    if (reviewing) advanceReview()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.deleteError'))
  } finally {
    isSaving.value = false
  }
}

function removeFirmsFromList(ids: string[], keepModal = false) {
  const gone = new Set(ids)
  firms.value = firms.value.filter((row) => !gone.has(row.id))
  selected.value = selected.value.filter((id) => !gone.has(id))
  if (keepModal) return
  if (
    (editFirma.value && gone.has(editFirma.value.id))
    || (previewFirma.value && gone.has(previewFirma.value.id))
  ) {
    firmModalOpen.value = false
    editFirma.value = null
    previewFirma.value = null
  }
}

async function deleteSelected() {
  if (!departmentId.value) return
  const ids = selected.value.filter((id) => {
    const firma = firms.value.find((row) => row.id === id)
    return firma && !inquiryMailLocked(firma)
  })
  if (ids.length === 0) return
  const count = ids.length
  const ok = await confirm.confirm({
    title: t('grossanlass.beschaffung.anfragen.deleteSelectedTitle'),
    message: t('grossanlass.beschaffung.anfragen.deleteSelectedMessage', { count }),
    confirmText: t('common.delete'),
    cancelText: t('common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  isSaving.value = true
  try {
    const result = await deleteGrossanlassInquiries(departmentId.value, ids)
    removeFirmsFromList(result.deleted)
    toast.success(t('grossanlass.beschaffung.anfragen.deleteSelectedToast', { count: result.deleted.length }))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.deleteError'))
  } finally {
    isSaving.value = false
  }
}

async function loadConversation(firma: GrossanlassInquiry) {
  conversation.value = emptyConversation()
  livePreview.value = null
  mailEditSubject.value = ''
  mailEditBody.value = ''
  previewPdfItems.value = []
  previewPdfTouched.value = false
  if (!departmentId.value) return
  conversationLoading.value = true
  try {
    conversation.value = await getGrossanlassInquiryConversation(departmentId.value, firma.id)
  } catch {
    conversation.value = emptyConversation()
  } finally {
    conversationLoading.value = false
  }
}

async function loadFirmMailPreview(firma: GrossanlassInquiry) {
  livePreview.value = null
  mailEditSubject.value = ''
  mailEditBody.value = ''
  previewPdfItems.value = []
  previewPdfTouched.value = false
  if (!departmentId.value) return
  isPreviewLoading.value = true
  try {
    livePreview.value = await previewGrossanlassMail(departmentId.value, {
      kind: firma.status === 'antwort' || firma.status === 'zusage' ? 'praezisieren' : 'anfrage',
      inquiry_id: firma.id,
    })
    mailEditSubject.value = livePreview.value.subject
    mailEditBody.value = livePreview.value.body
    previewPdfTouched.value = false
    previewPdfItems.value = pdfItemsFromPreview(livePreview.value.body, livePreview.value)
  } catch {
    livePreview.value = null
    previewPdfItems.value = []
    previewPdfTouched.value = false
  } finally {
    isPreviewLoading.value = false
  }
}

async function openFirmModal(firma: GrossanlassInquiry, tab: 'firm' | 'mail' = 'mail') {
  if (reviewQueue.value.length && reviewQueue.value[reviewIndex.value] !== firma.id) {
    stopReview()
  }
  editFirma.value = firma
  previewFirma.value = firma
  assignFirmForm(editForm, formFromInquiry(firma))
  firmModalTab.value = tab
  firmModalOpen.value = true
  if (inquiryMailLocked(firma)) {
    await loadConversation(firma)
    return
  }
  await loadFirmMailPreview(firma)
}

async function openPreview(firma: GrossanlassInquiry) {
  await openFirmModal(firma, 'mail')
}

watch(firmModalOpen, (open) => {
  if (!open) stopReview()
})

watch(draftsOpen, async (open) => {
  if (!open || !departmentId.value) return
  draftReviewId.value = selectedDraftable.value[0]?.id ?? null
  isPreviewingDrafts.value = true
  draftPreviewError.value = false
  for (const key of Object.keys(draftEdits)) {
    delete draftEdits[key]
  }
  try {
    draftPreviews.value = await previewGrossanlassMails(
      departmentId.value,
      selectedDraftable.value.map((firma) => firma.id),
      'anfrage',
    )
    for (const row of draftPreviews.value) {
      const check = matchInquiryMailPositions(
        row.body,
        row.positions?.allowed ?? [],
        row.positions?.other ?? [],
      )
      draftEdits[row.inquiry_id] = {
        subject: row.subject,
        body: row.body,
        pdfItems: defaultPdfItemLabels(row.positions?.allowed ?? [], check.omitted),
        pdfTouched: false,
      }
    }
    if (!draftReviewId.value) {
      draftReviewId.value = draftPreviews.value[0]?.inquiry_id ?? null
    }
  } catch {
    draftPreviews.value = []
    draftPreviewError.value = true
  } finally {
    isPreviewingDrafts.value = false
  }
})

function openGmail() {
  const url = previewFirma.value?.gmail_open_url
  if (url) window.open(url, '_blank', 'noopener')
}

function openGmailFor(firma: GrossanlassInquiry) {
  if (firma.gmail_open_url) window.open(firma.gmail_open_url, '_blank', 'noopener')
}

async function syncGmail() {
  await runGmailSync(false)
}

async function syncGmailSilent() {
  await runGmailSync(true)
}

async function runGmailSync(silent: boolean) {
  if (!departmentId.value || !gmailStatus.value?.connected) return
  if (isSyncing.value) return
  isSyncing.value = true
  try {
    const result = await syncGrossanlassInquiryGmail(departmentId.value)
    result.updated.forEach(replaceFirm)
    unmatched.value = result.unmatched
    openMailIfUnmatched()
    if (!silent) {
      toast.success(t('grossanlass.beschaffung.anfragen.gmailSyncToast', {
        count: result.updated.length,
        unmatched: result.unmatched.length,
      }))
    }
  } catch (e: unknown) {
    if (silent) return
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isSyncing.value = false
  }
}

async function markPreviewSent() {
  if (!previewFirma.value || !departmentId.value) return
  try {
    const updated = await markGrossanlassInquiriesSent(departmentId.value, [previewFirma.value.id])
    updated.forEach(replaceFirm)
    toast.success(t('grossanlass.beschaffung.anfragen.sentToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  }
}

async function replyPreview() {
  if (!previewFirma.value || !departmentId.value) return
  try {
    replaceFirm(await recordGrossanlassInquiryReply(departmentId.value, previewFirma.value.id))
    toast.success(t('grossanlass.beschaffung.anfragen.replyToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  }
}

async function acceptPreview() {
  if (!previewFirma.value || !departmentId.value) return
  try {
    replaceFirm(
      await updateGrossanlassInquiry(departmentId.value, previewFirma.value.id, { status: 'zusage' }),
    )
    toast.success(t('grossanlass.beschaffung.anfragen.zusageToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  }
}

async function rejectPreview() {
  if (!previewFirma.value || !departmentId.value) return
  try {
    replaceFirm(
      await updateGrossanlassInquiry(departmentId.value, previewFirma.value.id, { status: 'absage' }),
    )
    toast.success(t('grossanlass.beschaffung.anfragen.absageToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  }
}

function openReplyDraft() {
  const status = previewFirma.value?.status
  replyKind.value = status === 'antwort' || status === 'zusage' ? 'praezisieren' : 'nachfassen'
  replyDraftOpen.value = true
}

async function confirmReplyDraft() {
  if (!previewFirma.value || !departmentId.value || !replyKind.value) return
  isReplyDrafting.value = true
  try {
    replaceFirm(await createGrossanlassInquiryReplyDraft(departmentId.value, previewFirma.value.id, replyKind.value))
    replyDraftOpen.value = false
    toast.success(t('grossanlass.beschaffung.anfragen.replyDraftToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isReplyDrafting.value = false
  }
}

function windowOpen(url: string) {
  window.open(url, '_blank', 'noopener')
}

async function assignMail(mail: GrossanlassGmailUnmatched) {
  const inquiryId = assignTarget[mail.id]
  if (!departmentId.value || !inquiryId) return
  unmatchedBusy.value = mail.id
  try {
    const result = await assignGrossanlassGmailUnmatched(departmentId.value, mail.id, inquiryId)
    replaceFirm(result.inquiry)
    unmatched.value = result.unmatched
    toast.success(t('grossanlass.beschaffung.anfragen.unmatchedAssigned'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    unmatchedBusy.value = null
  }
}

async function createFromMail(mail: GrossanlassGmailUnmatched) {
  if (!departmentId.value) return
  unmatchedBusy.value = mail.id
  try {
    const result = await unmatchedToGrossanlassInquiry(departmentId.value, mail.id, {
      name: mail.from_name || mail.from_email,
      email: mail.from_email,
    })
    firms.value = [result.inquiry, ...firms.value.filter((row) => row.id !== result.inquiry.id)]
    unmatched.value = result.unmatched
    toast.success(t('grossanlass.beschaffung.anfragen.unmatchedCreated'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    unmatchedBusy.value = null
  }
}

async function discardMail(mail: GrossanlassGmailUnmatched) {
  if (!departmentId.value) return
  unmatchedBusy.value = mail.id
  try {
    unmatched.value = await discardGrossanlassGmailUnmatched(departmentId.value, mail.id)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    unmatchedBusy.value = null
  }
}

async function confirmDrafts() {
  if (!departmentId.value) return
  if (!gmailStatus.value?.connected) {
    toast.error(t('grossanlass.beschaffung.anfragen.draftsNeedGmail'))
    goGmailSettings()
    return
  }
  const ids = selectedDraftable.value.map((firma) => firma.id)
  if (ids.length === 0) {
    toast.error(t('grossanlass.beschaffung.anfragen.missingPackage'))
    return
  }
  for (const firma of selectedDraftable.value) {
    const preview = draftPreviews.value.find((row) => row.inquiry_id === firma.id)
    const edit = draftEdits[firma.id]
    const check = matchInquiryMailPositions(
      edit?.body ?? preview?.body ?? '',
      preview?.positions?.allowed ?? [],
      preview?.positions?.other ?? [],
    )
    if (check.unexpected.length) {
      draftReviewId.value = firma.id
      toast.error(t('grossanlass.beschaffung.anfragen.positionsUnexpected', {
        names: check.unexpected.join(', '),
      }))
      return
    }
  }
  isDrafting.value = true
  try {
    const overrides: Record<string, { subject?: string; body?: string; pdf_items?: string[] }> = {}
    for (const id of ids) {
      const edit = draftEdits[id]
      if (edit) {
        overrides[id] = { subject: edit.subject, body: edit.body, pdf_items: edit.pdfItems }
      }
    }
    const updated = await createGrossanlassInquiryDrafts(departmentId.value, ids, overrides)
    updated.forEach(replaceFirm)
    draftsOpen.value = false
    selected.value = []
    toast.success(t('grossanlass.beschaffung.anfragen.gmailDraftToast', { count: updated.length }))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isDrafting.value = false
  }
}

async function createDraftFromPreview() {
  const firma = previewFirma.value
  if (!firma || !departmentId.value) return
  if (!gmailStatus.value?.connected) {
    toast.error(t('grossanlass.beschaffung.anfragen.draftsNeedGmail'))
    goGmailSettings()
    return
  }
  if (previewMailCheck.value.unexpected.length) {
    toast.error(t('grossanlass.beschaffung.anfragen.positionsUnexpected', {
      names: previewMailCheck.value.unexpected.join(', '),
    }))
    return
  }
  isDraftingOne.value = true
  try {
    const updated = await createGrossanlassInquiryDrafts(
      departmentId.value,
      [firma.id],
      {
        [firma.id]: {
          subject: mailEditSubject.value,
          body: mailEditBody.value,
          pdf_items: previewPdfItems.value,
        },
      },
    )
    updated.forEach(replaceFirm)
    toast.success(t('grossanlass.beschaffung.anfragen.gmailDraftToast', { count: updated.length }))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isDraftingOne.value = false
  }
}

async function importTips() {
  if (!departmentId.value) return
  isImporting.value = true
  try {
    const created = await importGrossanlassInquiryTips(departmentId.value)
    toast.success(t('grossanlass.beschaffung.anfragen.importedTips', { count: created.length }))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isImporting.value = false
  }
}

const CSV_TEMPLATE = 'Firma;Ort / Adresse;Webseite;Branche / Typ;Was;Hinweise;Anrede;Vorname;Nachname;E-Mail;Telefon;Bemerkung\nMuster AG;Bern;https://muster.example;Fahrzeuge;Anhänger;nur Anfrage;Herr;Hans;Muster;info@muster.example;031 000 00 00;intern\n'

function downloadCsvTemplate() {
  const blob = new Blob([CSV_TEMPLATE], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'anfragen-vorlage.csv'
  link.click()
  URL.revokeObjectURL(url)
}

async function onCsvFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || !departmentId.value) return
  isCsvImporting.value = true
  try {
    const csv = await file.text()
    const result = await importGrossanlassInquiryCsv(departmentId.value, csv)
    firms.value = [...result.created, ...firms.value]
    toast.success(t('grossanlass.beschaffung.anfragen.csvImportToast', {
      count: result.created.length,
      skipped: result.skipped,
    }), 15000)
    if (result.errors.length) {
      toast.error(t('grossanlass.beschaffung.anfragen.csvImportErrors', { count: result.errors.length }), 15000)
    }
    if (result.created.length) void geocodeMissingPlaces()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isCsvImporting.value = false
  }
}

async function createFirm() {
  if (!departmentId.value || !createForm.name.trim()) return
  isSaving.value = true
  try {
    const created = await createGrossanlassInquiry(departmentId.value, firmWritePayload(createForm))
    firms.value = [created, ...firms.value]
    assignFirmForm(createForm, emptyFirmForm())
    createOpen.value = false
    toast.success(t('grossanlass.beschaffung.anfragen.createdToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.anfragen.saveError'))
  } finally {
    isSaving.value = false
  }
}

async function refreshGmailStrip() {
  if (!departmentId.value) return
  try {
    gmailStatus.value = await getGrossanlassGmailStatus(departmentId.value)
  } catch {
    /* keep last known status */
  }
}

const gmailPoll = useBackgroundPoll({
  intervalMs: 20_000,
  enabled: computed(
    () => pageOpen.value && !!departmentId.value && !!gmailStatus.value?.connected && !isLoading.value,
  ),
  isBusy: () =>
    isSyncing.value
    || isSaving.value
    || isDrafting.value
    || isReplyDrafting.value
    || draftsOpen.value
    || isImporting.value
    || isCsvImporting.value,
  poll: () => syncGmailSilent(),
})

onMounted(() => {
  void load().then(() => {
    if (gmailStatus.value?.connected) gmailPoll.tick()
  })
})

watch(
  () => String(route.query.system || ''),
  () => applySystemCategoryQuery(),
)

onActivated(() => {
  pageOpen.value = true
  void refreshGmailStrip()
})

onDeactivated(() => {
  pageOpen.value = false
})

onUnmounted(() => {
  pageOpen.value = false
})
</script>


<style scoped>
.ga-anfragen { padding: 4px 0 24px; }
.tab-intro { margin: 0 0 16px; color: #64748b; font-size: 0.9rem; }
.review-strip {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 0 0 10px;
}
.review-strip__label {
  font-size: 0.85rem;
  font-weight: 650;
  color: #0f172a;
  margin-right: 4px;
}
.review-strip__btn {
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 10px;
  padding: 8px 12px;
  font: inherit;
  font-size: 0.85rem;
  font-weight: 600;
  color: #334155;
  cursor: pointer;
}
.review-strip__btn:hover:not(:disabled) {
  border-color: #93c5fd;
  background: #eff6ff;
}
.review-strip__btn.is-active {
  border-color: #6366f1;
  background: #eef2ff;
  color: #3730a3;
}
.review-strip__btn:disabled {
  opacity: 0.45;
  cursor: default;
}
.status-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0 0 14px;
}
.status-stat {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 10px;
  padding: 8px 10px;
  cursor: pointer;
  font: inherit;
}
.status-stat strong { font-size: 0.95rem; }
.status-stat.is-active {
  border-color: var(--color-primary, #16a34a);
  background: var(--color-primary-muted-bg, #ecfdf3);
}
.ga-anfragen-accordions-wrap { position: relative; margin-top: 8px; }
.categories-panel-hint { margin: 0 0 10px; }
.category-filter-tree {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.cat-filter-extra {
  padding: 8px 10px 8px 46px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  font: inherit;
  font-weight: 600;
  color: #334155;
  text-align: left;
  cursor: pointer;
}
.cat-filter-extra.is-active {
  border-color: var(--color-primary, #16a34a);
  background: var(--color-primary-muted-bg, #ecfdf3);
  color: var(--color-primary-dark, #166534);
}
.mail-panel { position: relative; }
.mail-panel-actions {
  position: absolute;
  top: 50%;
  right: 52px;
  z-index: 3;
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  align-items: center;
  gap: 6px;
  max-width: calc(100% - 14rem);
  transform: translateY(-50%);
  pointer-events: none;
}
.mail-panel-actions :deep(.v-btn) {
  pointer-events: auto;
  min-height: 28px !important;
  height: 28px;
  font-size: 0.75rem;
  font-weight: 500;
  padding-inline: 10px;
}
.panel-head--mail { padding-right: 15.5rem; }
.panel-badge {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  background: #fef3c7;
  color: #92400e;
}
.muted { margin: 0 0 8px; color: #64748b; font-size: 0.82rem; }
.unmatched {
  margin: 0 0 8px;
  padding: 12px 14px;
  border: 1px solid #fde68a;
  border-radius: 10px;
  background: #fffbeb;
}
.unmatched h2 { margin: 0 0 4px; font-size: 1rem; }
.unmatched .muted { margin: 0 0 10px; color: #64748b; font-size: 0.82rem; }
.unmatched-card {
  background: #fff;
  border: 1px solid #fef3c7;
  border-radius: 8px;
  padding: 10px 12px;
  margin-bottom: 8px;
}
.unmatched-card header strong { display: block; }
.unmatched-body {
  white-space: pre-wrap;
  font: inherit;
  font-size: 0.82rem;
  margin: 8px 0;
  max-height: 8rem;
  overflow: auto;
}
.unmatched-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.unmatched-select {
  min-width: 12rem;
  flex: 1 1 12rem;
  font: inherit;
  font-size: 0.85rem;
  padding: 6px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
}
.thread-text {
  margin: 4px 0 0;
  white-space: pre-wrap;
  font: inherit;
}
.reply-kinds {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.reply-kind {
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 8px;
  padding: 8px 10px;
  font: inherit;
  font-size: 0.85rem;
  cursor: pointer;
}
.reply-kind.is-active {
  outline: 2px solid #93c5fd;
  background: #eff6ff;
}
.ga-anfragen__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 12px;
  align-items: center;
  margin-bottom: 14px;
}
.ga-anfragen__search { flex: 1 1 220px; min-width: min(100%, 200px); }
.csv-input { display: none; }
.view-toggle {
  display: inline-flex;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
}
.view-toggle__btn {
  border: 0;
  background: transparent;
  padding: 8px 12px;
  font: inherit;
  font-size: 0.85rem;
  color: #64748b;
  cursor: pointer;
}
.view-toggle__btn.is-active {
  background: var(--color-primary-muted-bg, #ecfdf3);
  color: var(--color-primary-dark, #166534);
}
.map-panel { display: grid; gap: 10px; margin-bottom: 4px; }
.map-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.map-radius {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 0.85rem;
  color: #334155;
}
.map-radius select {
  font: inherit;
  padding: 6px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
}
.mail-attach {
  margin: 8px 0 0;
  padding: 8px 10px;
  background: #f8fafc;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  font-size: 0.85rem;
  color: #334155;
}
.filter-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  flex: 1 1 100%;
}
.filter-chip {
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 999px;
  padding: 4px 10px;
  font: inherit;
  font-size: 0.78rem;
  color: #475569;
  cursor: pointer;
}
.filter-chip.is-active {
  border-color: var(--color-primary, #16a34a);
  background: var(--color-primary-muted-bg, #ecfdf3);
  color: var(--color-primary-dark, #166534);
  font-weight: 600;
}
.pkg-edit {
  display: flex;
  flex-wrap: wrap;
  gap: 0;
  border: 0;
  background: transparent;
  padding: 0;
  cursor: pointer;
  text-align: left;
  font: inherit;
}
.pkg-chip--empty {
  font-weight: 500;
  color: #94a3b8;
}
.table-wrap { overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.data-table th, .data-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; text-align: left; vertical-align: top; }
.data-table th { background: #f8fafc; font-weight: 600; }
.th-sort {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  font: inherit;
  font-weight: 600;
  color: inherit;
  cursor: pointer;
  text-align: left;
}
.th-sort:hover { color: #0f172a; }
.sort-indicator {
  font-size: 0.7rem;
  color: #64748b;
}
.col-check { width: 36px; }
.meta { display: block; color: #64748b; font-size: 0.75rem; margin-top: 2px; }
.meta--warn { color: #c2410c; }
.meta--submitter {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.submitter-mail {
  color: #1d4ed8;
  font-weight: 600;
  text-decoration: none;
}
.submitter-mail:hover { text-decoration: underline; }
.submitter-card {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px 12px;
  margin: 0 0 12px;
  padding: 10px 12px;
  border-radius: 8px;
  background: #f5f3ff;
  color: #5b21b6;
  font-size: 0.85rem;
}
.mail-block {
  margin: 0 0 10px;
  padding: 8px 10px;
  border-radius: 8px;
  background: #fff7ed;
  color: #9a3412;
  font-size: 0.82rem;
}
.ref-id {
  font-size: 0.78rem;
  background: #f1f5f9;
  padding: 2px 6px;
  border-radius: 4px;
}
.is-blocked td { background: #fff7ed; }
.pkg-chip {
  display: inline-flex;
  margin: 0 4px 4px 0;
  padding: 2px 8px;
  border-radius: 999px;
  background: #f1f5f9;
  font-size: 0.72rem;
  font-weight: 600;
}
.pkg-chip--more {
  background: #e2e8f0;
  font-weight: 700;
}
.firm-name-wrap {
  display: block;
  margin: 0 0 12px;
}
.firm-fields {
  border: 0;
  margin: 0;
  padding: 0;
  min-inline-size: 0;
}
.firm-fields:disabled {
  opacity: 0.72;
}
.firm-name-wrap :deep(.v-field__input) {
  min-height: 52px;
  font-size: 1.08rem;
}
.contact-name-row {
  display: grid;
  grid-template-columns: minmax(7.5rem, 0.7fr) 1fr 1fr;
  gap: 8px;
  margin: 0 0 8px;
}
.phone-tel-link {
  font-size: 0.78rem;
  font-weight: 600;
  color: #1d4ed8;
  text-decoration: none;
  white-space: nowrap;
  padding-right: 4px;
}
.phone-tel-link:hover {
  text-decoration: underline;
}
.status-chip {
  display: inline-flex;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
}
.status-chip--kein_entwurf { background: #f1f5f9; color: #475569; }
.status-chip--entwurf { background: #eff6ff; color: #1d4ed8; }
.status-chip--gmail_entwurf { background: #e0e7ff; color: #3730a3; }
.status-chip--gesendet { background: #ecfeff; color: #0e7490; }
.status-chip--antwort { background: #fef9c3; color: #a16207; }
.status-chip--zusage { background: #dcfce7; color: #15803d; }
.status-chip--absage { background: #fee2e2; color: #b91c1c; }
.status-chip--vorschlag { background: #f3e8ff; color: #7e22ce; }
.category-list { display: grid; gap: 12px; }
.category-card {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  padding: 12px 14px;
}
.category-card h3 {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  justify-content: flex-start;
  margin: 0 0 8px;
  font-size: 0.9rem;
}
.category-card h3 span { color: #64748b; font-weight: 500; margin-right: auto; }
.category-card ul { list-style: none; margin: 0; padding: 0; }
.category-card li {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0;
  border-top: 1px solid #f1f5f9;
}
.firm-modal-tabs { margin: 0 0 12px; }
.firm-modal-window { min-height: 12rem; }
.mail-kicker, .review-hint { margin: 0 0 8px; color: #64748b; font-size: 0.82rem; }
.mail-subject { margin: 0 0 10px; font-weight: 700; }
.mail-editor {
  margin: 0 0 10px;
}
.mail-editor :deep(.tiptap-content) {
  max-height: 280px;
  overflow: auto;
}
.mail-positions {
  margin: 0 0 10px;
  padding: 8px 10px;
  border-radius: 8px;
  font-size: 0.82rem;
  line-height: 1.4;
}
.mail-positions p { margin: 0; }
.mail-positions__quote {
  margin-top: 6px;
  font-style: italic;
}
.mail-positions--ok { background: #ecfdf5; color: #047857; }
.mail-positions--warn { background: #fffbeb; color: #b45309; }
.pdf-items {
  margin: 0 0 12px;
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
}
.pdf-items__title {
  margin: 0 0 4px;
  font-size: 0.85rem;
  font-weight: 600;
}
.pdf-items__cat {
  margin: 8px 0 2px;
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
}
.pdf-items__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin: 0 0 6px;
}
.mail-positions--bad { background: #fef2f2; color: #b91c1c; }
.mail-attach { margin: 0 0 8px; color: #64748b; font-size: 0.82rem; }
.mail-conv {
  display: grid;
  gap: 16px;
  margin-top: 12px;
}
.mail-conv__block h3 {
  margin: 0 0 8px;
  font-size: 0.82rem;
  font-weight: 700;
  color: #334155;
}
.mail-conv__card {
  background: #f8fafc;
  border-radius: 8px;
  padding: 10px 12px;
}
.mail-conv__card--firm {
  background: #eff6ff;
}
.mail-conv__meta {
  margin: 0 0 8px;
  font-size: 0.75rem;
  color: #64748b;
}
.mail-conv__text {
  margin: 0;
  white-space: pre-wrap;
  font: inherit;
  font-size: 0.9rem;
  line-height: 1.45;
}
.mail-conv__html {
  font-size: 0.9rem;
  line-height: 1.45;
}
.mail-conv__html :deep(p) { margin: 0 0 0.6em; }
.mail-conv__history {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 6px;
}
.mail-conv__history li {
  display: grid;
  gap: 2px;
  font-size: 0.82rem;
  color: #334155;
}
.mail-conv__history time {
  font-size: 0.72rem;
  color: #64748b;
}
.thread { list-style: none; margin: 12px 0 0; padding: 0; display: grid; gap: 8px; }
.thread li {
  background: #f8fafc;
  border-radius: 8px;
  padding: 8px 10px;
  font-size: 0.82rem;
}
.thread strong { display: block; font-size: 0.72rem; color: #64748b; }
.draft-review {
  display: grid;
  grid-template-columns: minmax(10rem, 13rem) 1fr;
  gap: 12px;
  align-items: start;
}
@media (max-width: 640px) {
  .draft-review { grid-template-columns: 1fr; }
}
.draft-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
.draft-list li.is-active { outline: 2px solid #93c5fd; border-radius: 8px; }
.draft-list__btn {
  display: flex;
  flex-direction: column;
  gap: 2px;
  width: 100%;
  text-align: left;
  border: 1px solid #e5e7eb;
  background: #fff;
  border-radius: 8px;
  padding: 8px 10px;
  cursor: pointer;
  font: inherit;
}
.draft-list__btn span { color: #64748b; font-size: 0.75rem; }
.draft-preview { min-width: 0; }
</style>
