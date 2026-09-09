<template>
  <div class="zusagen-page">
    <p class="tab-intro">{{ t('grossanlass.beschaffung.zusagen.intro') }}</p>

    <div class="zusagen-toolbar">
      <EButton variant="primary" size="small" @click="openCreate()">
        {{ t('grossanlass.beschaffung.zusagen.createAction') }}
      </EButton>
      <ESearchField
        v-model="query"
        class="zusagen-toolbar__search"
        :label="t('grossanlass.beschaffung.zusagen.search')"
      />
      <ESelect
        v-model="groupBy"
        class="zusagen-toolbar__select"
        :label="t('grossanlass.beschaffung.zusagen.groupBy')"
        :items="groupItems"
        hide-details
      />
      <ESelect
        v-model="sortBy"
        class="zusagen-toolbar__select"
        :label="t('grossanlass.beschaffung.zusagen.sort')"
        :items="sortItems"
        hide-details
      />
      <div class="zusagen-toolbar__expand">
        <EButton variant="text" size="small" @click="expandAll">
          {{ t('grossanlass.beschaffung.zusagen.expandAll') }}
        </EButton>
        <EButton variant="text" size="small" @click="collapseAll">
          {{ t('grossanlass.beschaffung.zusagen.collapseAll') }}
        </EButton>
      </div>
    </div>

    <EEmptyState
      v-if="filteredRows.length === 0"
      variant="default"
      icon="mdi-handshake-outline"
      :title="t('grossanlass.beschaffung.zusagen.noMatchTitle')"
      :description="t('grossanlass.beschaffung.zusagen.noMatchDescription')"
    />

    <v-expansion-panels
      v-else
      v-model="openGroups"
      multiple
      class="e-accordions"
    >
      <v-expansion-panel
        v-for="group in grouped"
        :key="group.id"
        :value="group.id"
      >
        <v-expansion-panel-title>
          <span class="panel-head">
            <span class="panel-head__label">
              {{ group.label }}
              <span v-if="group.articleRows.length" class="panel-head__count">{{ group.articleRows.length }}</span>
              <span v-if="group.heldCount > 0" class="zusagen-badge is-held">
                {{ t('grossanlass.beschaffung.zusagen.heldCount', { count: group.heldCount }) }}
              </span>
              <span v-if="group.wideCount > 0" class="fein-badge fein-badge--wide">
                {{ t('grossanlass.planung.feinPartner.delta.wide') }}
              </span>
            </span>
          </span>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <div v-if="group.inquiry && groupBy === 'source'" class="take-panel">
            <div class="take-panel__head">
              <div>
                <strong>{{ t('grossanlass.beschaffung.zusagen.takeTitle', { partner: group.label }) }}</strong>
                <p>{{ t('grossanlass.beschaffung.zusagen.takeHint') }}</p>
              </div>
              <EButton
                variant="secondary"
                size="small"
                class="take-panel__bulk"
                :disabled="group.articleRows.length === 0"
                :title="group.articleRows.length === 0
                  ? t('grossanlass.beschaffung.zusagen.bulkWindowNeedsArticles')
                  : undefined"
                @click="openBulkWindow(group)"
              >
                {{ t('grossanlass.beschaffung.zusagen.bulkWindowAction') }}
              </EButton>
            </div>
            <p class="take-panel__fein">{{ t('grossanlass.beschaffung.zusagen.alignHint') }}</p>
            <p v-if="group.takeLines.length === 0" class="take-panel__empty">
              {{ t('grossanlass.beschaffung.zusagen.takeEmpty') }}
            </p>
            <div v-else class="take-align">
              <div class="take-align__toolbar">
                <span class="take-align__all">{{ t('grossanlass.beschaffung.zusagen.alignScaleAll') }}</span>
                <div class="take-align__scales" role="tablist">
                  <button
                    v-for="item in alignScales"
                    :key="item.id"
                    type="button"
                    class="take-align__scale-btn"
                    :class="{ 'take-align__scale-btn--active': groupScale(group) === item.id }"
                    @click="setGroupScale(group, item.id)"
                  >
                    {{ item.label }}
                  </button>
                </div>
                <ul class="take-align__legend">
                  <li>
                    <span class="take-align__swatch take-align__swatch--wish" />
                    {{ t('grossanlass.beschaffung.zusagen.alignWish') }}
                  </li>
                  <li>
                    <span class="take-align__swatch take-align__swatch--wide" />
                    {{ t('grossanlass.planung.feinPartner.delta.wide') }}
                  </li>
                  <li>
                    <span class="take-align__swatch take-align__swatch--firm" />
                    {{ t('grossanlass.beschaffung.zusagen.alignFirm') }}
                  </li>
                  <li>
                    <span class="take-align__swatch take-align__swatch--handover" />
                    {{ t('grossanlass.beschaffung.zusagen.alignHandover') }}
                  </li>
                  <li>
                    <span class="take-align__swatch take-align__swatch--giveback" />
                    {{ t('grossanlass.beschaffung.zusagen.alignReturn') }}
                  </li>
                </ul>
              </div>
              <article
                v-for="line in group.takeLines"
                :key="line.id"
                class="take-item"
                :class="`take-item--${rowForLine(group.inquiry.id, line.id)?.delta || 'none'}`"
              >
                <div class="take-item__top">
                  <div>
                    <strong>{{ line.label }}</strong>
                    <span v-if="line.category_name" class="take-table__meta">{{ line.category_name }}</span>
                    <span v-if="line.wish_count > 1" class="zusagen-badge is-bundle">
                      {{ t('grossanlass.beschaffung.zusagen.viewWishesBundled', { count: line.wish_count }) }}
                    </span>
                    <span
                      v-if="rowForLine(group.inquiry.id, line.id)"
                      class="zusagen-badge"
                      :class="rowForLine(group.inquiry.id, line.id)?.released ? 'is-open' : 'is-held'"
                    >
                      {{ rowForLine(group.inquiry.id, line.id)?.released
                        ? t('grossanlass.materials.zusage.releasedShort')
                        : t('grossanlass.materials.zusage.heldShort') }}
                    </span>
                    <span
                      v-if="rowForLine(group.inquiry.id, line.id)?.delta === 'wide'"
                      class="fein-badge fein-badge--wide"
                    >
                      {{ t('grossanlass.planung.feinPartner.delta.wide') }}
                    </span>
                    <p v-if="wishTextForLine(group.inquiry.id, line)" class="take-item__wish">
                      {{ wishTextForLine(group.inquiry.id, line) }}
                    </p>
                    <p v-if="takeWindowText(group.inquiry.id, line)" class="take-table__meta">
                      {{ takeWindowText(group.inquiry.id, line) }}
                    </p>
                  </div>
                  <div class="take-item__qty">
                    <GrossanlassProcurementCoverage
                      :line="line"
                      stack
                      :other-taken="takenElsewhere(group.inquiry.id, line.id)"
                      :here-taken="Number(takeQty[takeKey(group.inquiry.id, line.id)] ?? 0)"
                      :link-loans="false"
                    />
                    <AutoSaveField
                      :model-value="takeQty[takeKey(group.inquiry.id, line.id)] ?? 0"
                      :baseline="takenByInquiry(group.inquiry.id, line.id)"
                      :label="t('grossanlass.beschaffung.zusagen.takeHere')"
                      type="number"
                      :min="0"
                      span-class="take-table__autosave"
                      :save="(value) => saveTakeLine(group.inquiry, line, value)"
                      @update:model-value="setTakeQty(group.inquiry.id, line.id, $event)"
                    />
                  </div>
                  <div class="take-table__open">
                    <EButton variant="text" size="small" @click="goBedarfLine(line.id)">
                      {{ t('grossanlass.beschaffung.zusagen.openBedarf') }}
                    </EButton>
                    <EButton
                      variant="secondary"
                      size="small"
                      :disabled="!articleIdForLine(group.inquiry.id, line.id)"
                      :title="articleIdForLine(group.inquiry.id, line.id)
                        ? undefined
                        : t('grossanlass.beschaffung.zusagen.takeWindowNeedsSave')"
                      @click="openWindowForLine(group.inquiry.id, line.id)"
                    >
                      {{ rowForLine(group.inquiry.id, line.id)?.hasWindow
                        ? t('grossanlass.beschaffung.zusagen.windowEdit')
                        : t('grossanlass.beschaffung.zusagen.windowAction') }}
                    </EButton>
                    <EButton
                      variant="secondary"
                      size="small"
                      :disabled="!wishesOf(line).length"
                      :title="wishesOf(line).length
                        ? undefined
                        : t('grossanlass.beschaffung.zusagen.wishMissing')"
                      @click="openWishBundle(wishesOf(line), line.label, line.quantity)"
                    >
                      {{ t('grossanlass.beschaffung.zusagen.openFein') }}
                    </EButton>
                    <EButton
                      v-if="rowForLine(group.inquiry.id, line.id)"
                      variant="secondary"
                      size="small"
                      :disabled="(rowForLine(group.inquiry.id, line.id)?.quantity ?? 0) <= 0"
                      @click="toggleReleased(rowForLine(group.inquiry.id, line.id)!)"
                    >
                      {{ rowForLine(group.inquiry.id, line.id)?.released
                        ? t('grossanlass.beschaffung.zusagen.toggleReleaseOff')
                        : t('grossanlass.beschaffung.zusagen.toggleReleaseOn') }}
                    </EButton>
                  </div>
                </div>
                <div v-if="line.quotes.length" class="take-item__kauf">
                  <p class="take-item__kauf-line">
                    <strong>{{ t('grossanlass.beschaffung.zusagen.takeKaufLabel') }}</strong>
                    {{ t('grossanlass.beschaffung.zusagen.takeKaufQuote', {
                      supplier: selectedQuoteOf(line)?.supplier || line.quotes[0].supplier,
                      amount: formatChf(selectedQuoteOf(line)?.amount_chf ?? line.quotes[0].amount_chf),
                    }) }}
                    <span
                      v-if="procurementIsOrdered(line)"
                      class="take-item__kauf-status take-item__kauf-status--ordered"
                    >
                      {{ t('grossanlass.beschaffung.zusagen.takeBuy') }}
                      {{ procurementOrderedQty(line) }}
                    </span>
                    <span v-if="selectedQuoteOf(line)" class="take-item__kauf-picked">
                      {{ t('grossanlass.beschaffung.offerten.selected') }}
                    </span>
                  </p>
                  <p class="take-item__kauf-more">
                    {{ t('grossanlass.beschaffung.zusagen.takeKaufMore', {
                      supplier: selectedQuoteOf(line)?.supplier || line.quotes[0].supplier,
                      count: line.quotes.length,
                    }) }}
                  </p>
                  <div class="take-item__kauf-actions">
                    <EButton variant="text" size="small" @click="viewQuoteOf(line)">
                      {{ t('grossanlass.beschaffung.zusagen.viewQuote') }}
                    </EButton>
                    <EButton
                      v-if="selectedQuoteOf(line)"
                      variant="text"
                      size="small"
                      @click="goOrderLine(line.id)"
                    >
                      {{ t('grossanlass.beschaffung.offerten.toOrder') }}
                    </EButton>
                    <EButton
                      variant="secondary"
                      size="small"
                      @click="goMoreQuotes(line)"
                    >
                      {{ t('grossanlass.beschaffung.offerten.addAfterSelected') }}
                    </EButton>
                  </div>
                </div>
                <div class="take-item__align">
                  <div class="take-align__scales take-align__scales--item" role="tablist">
                    <button
                      v-for="item in alignScales"
                      :key="item.id"
                      type="button"
                      class="take-align__scale-btn"
                      :class="{ 'take-align__scale-btn--active': scaleOfLine(group.inquiry.id, line) === item.id }"
                      @click="setLineScale(group.inquiry.id, line.id, item.id)"
                    >
                      {{ item.label }}
                    </button>
                  </div>
                  <GrossanlassZusageAlignStrip
                    :scale="scaleOfLine(group.inquiry.id, line)"
                    :anchor-ymd="lineAnchor(group.inquiry.id, line)"
                    :span-months="scaleOfLine(group.inquiry.id, line) === 'month' && !hasAlignAnchor(lineScaleKey(group.inquiry.id, line.id))"
                  :wish-from-iso="wishIsoForLine(group.inquiry.id, line).from"
                  :wish-to-iso="wishIsoForLine(group.inquiry.id, line).to"
                  :present-from-iso="rowForLine(group.inquiry.id, line.id)?.presentFromIso"
                  :present-to-iso="rowForLine(group.inquiry.id, line.id)?.presentToIso"
                  :handover-from-iso="rowForLine(group.inquiry.id, line.id)?.handoverIso"
                  :handover-to-iso="rowForLine(group.inquiry.id, line.id)?.handoverToIso"
                  :return-from-iso="rowForLine(group.inquiry.id, line.id)?.returnIso"
                  :return-to-iso="rowForLine(group.inquiry.id, line.id)?.returnToIso"
                  :wide="rowForLine(group.inquiry.id, line.id)?.delta === 'wide'"
                  show-head
                  @open-day="openLineDay(group.inquiry.id, line.id, $event)"
                  @shift="shiftLineAnchor(group.inquiry.id, line.id, $event)"
                />
                </div>
                <p
                  v-if="rowForLine(group.inquiry.id, line.id)?.delta === 'wide'"
                  class="zusagen-card__hint"
                >
                  {{ t('grossanlass.planung.feinPartner.advice.wide') }}
                </p>
              </article>
            </div>
          </div>
          <ul v-if="leftoverArticles(group).length" class="zusagen-list">
            <li
              v-for="row in leftoverArticles(group)"
              :key="row.id"
              class="zusagen-card"
              :class="`zusagen-card--${row.delta}`"
            >
              <div class="zusagen-card__head">
                <div>
                  <strong>{{ t('grossanlass.beschaffung.zusagen.qtyLabel', { count: row.quantity, name: row.name }) }}</strong>
                  <span class="zusagen-badge" :class="row.released ? 'is-open' : 'is-held'">
                    {{ row.released
                      ? t('grossanlass.materials.zusage.releasedShort')
                      : t('grossanlass.materials.zusage.heldShort') }}
                  </span>
                  <span v-if="(matchLineForRow(row)?.wish_count ?? 0) > 1" class="zusagen-badge is-bundle">
                    {{ t('grossanlass.beschaffung.zusagen.viewWishesBundled', {
                      count: matchLineForRow(row)!.wish_count,
                    }) }}
                  </span>
                </div>
                <span class="fein-badge" :class="{ 'fein-badge--wide': row.delta === 'wide' }">
                  {{ t(`grossanlass.planung.feinPartner.delta.${row.delta}`) }}
                </span>
              </div>
              <p>{{ t('grossanlass.planung.feinPartner.partnerWindow', {
                partner: row.source,
                from: row.partnerFrom,
                to: row.partnerTo,
              }) }}</p>
              <p>
                {{ t('grossanlass.beschaffung.zusagen.handoverReturn', {
                  handover: row.handover,
                  giveback: row.giveback,
                }) }}
              </p>
              <p v-if="row.wishLabel">{{ t('grossanlass.planung.feinPartner.wishWindow', {
                wish: row.wishLabel,
                from: row.wishFrom,
                to: row.wishTo,
              }) }}</p>
              <p v-if="quoteForRow(row)" class="take-table__meta">
                {{ t('grossanlass.beschaffung.zusagen.fromQuoteHint', { supplier: quoteForRow(row)!.supplier }) }}
                · {{ formatChf(quoteForRow(row)!.amount_chf) }}
              </p>
              <GrossanlassProcurementCoverage
                v-if="matchLineForRow(row)"
                :line="matchLineForRow(row)!"
                stack
                :other-taken="takenExceptRow(row.id, matchLineForRow(row)!.id)"
                :here-taken="row.quantity"
                :link-loans="false"
              />
              <p v-if="row.delta !== 'none'" class="zusagen-card__hint">
                {{ t(`grossanlass.planung.feinPartner.advice.${row.delta}`) }}
              </p>
              <div class="take-item__align">
                <div class="take-align__scales take-align__scales--item" role="tablist">
                  <button
                    v-for="item in alignScales"
                    :key="item.id"
                    type="button"
                    class="take-align__scale-btn"
                    :class="{ 'take-align__scale-btn--active': scaleOfRow(row) === item.id }"
                    @click="setRowScale(row.id, item.id)"
                  >
                    {{ item.label }}
                  </button>
                </div>
              <GrossanlassZusageAlignStrip
                :scale="scaleOfRow(row)"
                :anchor-ymd="rowAnchor(row)"
                :span-months="scaleOfRow(row) === 'month' && !hasAlignAnchor(rowScaleKey(row.id))"
                :wish-from-iso="row.wishFromIso"
                :wish-to-iso="row.wishToIso"
                :present-from-iso="row.presentFromIso"
                :present-to-iso="row.presentToIso"
                :handover-from-iso="row.handoverIso"
                :handover-to-iso="row.handoverToIso"
                :return-from-iso="row.returnIso"
                :return-to-iso="row.returnToIso"
                :wide="row.delta === 'wide'"
                show-head
                @open-day="openRowDay(row.id, $event)"
                @shift="shiftRowAnchor(row.id, $event)"
              />
              </div>
              <div class="zusagen-card__actions">
                <EButton
                  v-if="matchLineForRow(row) && !row.fromLineId"
                  variant="primary"
                  size="small"
                  @click="attachLeftoverToBedarf(row)"
                >
                  {{ t('grossanlass.beschaffung.zusagen.attachToBedarf') }}
                </EButton>
                <EButton
                  v-if="quoteForRow(row)"
                  variant="secondary"
                  size="small"
                  @click="viewQuoteForRow(row)"
                >
                  {{ t('grossanlass.beschaffung.zusagen.viewQuote') }}
                </EButton>
                <EButton
                  v-if="matchLineForRow(row)"
                  variant="text"
                  size="small"
                  @click="goBedarfLine(matchLineForRow(row)!.id)"
                >
                  {{ t('grossanlass.beschaffung.zusagen.openBedarf') }}
                </EButton>
                <EButton
                  :variant="row.delta === 'wide' || row.delta === 'none' ? 'primary' : 'secondary'"
                  size="small"
                  :disabled="!wishesForRow(row).length"
                  :title="wishesForRow(row).length
                    ? undefined
                    : t('grossanlass.beschaffung.zusagen.wishMissing')"
                  @click="openWishBundle(
                    wishesForRow(row),
                    matchLineForRow(row)?.label || row.name,
                    matchLineForRow(row)?.quantity ?? row.quantity,
                  )"
                >
                  {{ t('grossanlass.beschaffung.zusagen.openFein') }}
                </EButton>
                <EButton
                  :variant="row.delta === 'wide' || row.delta === 'none' ? 'secondary' : 'primary'"
                  size="small"
                  @click="openWindow(row)"
                >
                  {{ row.hasWindow
                    ? t('grossanlass.beschaffung.zusagen.windowEdit')
                    : t('grossanlass.beschaffung.zusagen.windowAction') }}
                </EButton>
                <EButton
                  variant="secondary"
                  size="small"
                  :disabled="row.quantity <= 0"
                  :title="row.quantity <= 0
                    ? t('grossanlass.beschaffung.zusagen.takeQtyHeld')
                    : undefined"
                  @click="toggleReleased(row)"
                >
                  {{ row.released
                    ? t('grossanlass.beschaffung.zusagen.toggleReleaseOff')
                    : t('grossanlass.beschaffung.zusagen.toggleReleaseOn') }}
                </EButton>
              </div>
            </li>
          </ul>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>

    <GrossanlassZusageCreatePreviewDialog
      v-model="createOpen"
      :preset="createPreset"
      @created="onCreated"
    />

    <EDialog
      v-model="windowOpen"
      :title="windowBulkPartner
        ? t('grossanlass.beschaffung.zusagen.bulkWindowTitle', { partner: windowBulkPartner })
        : t('grossanlass.beschaffung.zusagen.windowTitle')"
      :max-width="640"
      scrollable
    >
      <p class="window-hint">{{ windowBulkPartner
        ? t('grossanlass.beschaffung.zusagen.bulkWindowHint', { count: windowTargetIds.length })
        : t('grossanlass.beschaffung.zusagen.windowHint') }}</p>
      <p v-if="windowWishText" class="take-item__wish">{{ windowWishText }}</p>
      <h3 class="window-section">{{ t('grossanlass.materials.zusage.sectionPresent') }}</h3>
      <EDateRangeField
        v-model:start="presentFromDate"
        v-model:end="presentToDate"
        :department-id="departmentId"
        :label="t('grossanlass.materials.zusage.fieldPresent')"
        allow-past
      />
      <div class="window-grid">
        <ETimeField v-model="presentFromTime" :label="t('grossanlass.materialUebersicht.fieldFromTime')" />
        <ETimeField v-model="presentToTime" :label="t('grossanlass.materialUebersicht.fieldToTime')" />
      </div>
      <h3 class="window-section">{{ t('grossanlass.materials.zusage.sectionHandover') }}</h3>
      <EDateField
        v-model="handoverDate"
        :department-id="departmentId"
        :label="t('grossanlass.materials.zusage.fieldHandoverDay')"
        :view-date="presentFromDate"
        allow-past
      />
      <div class="window-grid">
        <ETimeField v-model="handoverFromTime" :label="t('grossanlass.materials.zusage.fieldFrom')" />
        <ETimeField v-model="handoverToTime" :label="t('grossanlass.materials.zusage.fieldTo')" />
      </div>
      <p class="window-hint window-hint--muted">{{ t('grossanlass.materials.zusage.inboundHow') }}</p>
      <div class="window-toggle" role="tablist" :aria-label="t('grossanlass.materials.zusage.inboundHow')">
        <button
          type="button"
          role="tab"
          :aria-selected="inboundMode === 'pickup'"
          class="window-toggle__btn"
          :class="{ 'window-toggle__btn--on': inboundMode === 'pickup' }"
          @click="inboundMode = 'pickup'"
        >
          {{ t('grossanlass.materials.zusage.inboundPickup') }}
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="inboundMode === 'delivery'"
          class="window-toggle__btn"
          :class="{ 'window-toggle__btn--on': inboundMode === 'delivery' }"
          @click="inboundMode = 'delivery'"
        >
          {{ t('grossanlass.materials.zusage.inboundDelivery') }}
        </button>
      </div>
      <p class="window-hint window-hint--muted">
        {{ inboundMode === 'pickup'
          ? t('grossanlass.materials.zusage.inboundHintPickup')
          : t('grossanlass.materials.zusage.inboundHintDelivery') }}
      </p>
      <h3 class="window-section">{{ t('grossanlass.materials.zusage.sectionReturn') }}</h3>
      <EDateField
        v-model="returnDate"
        :department-id="departmentId"
        :label="t('grossanlass.materials.zusage.fieldReturnDay')"
        :view-date="presentToDate"
        allow-past
      />
      <div class="window-grid">
        <ETimeField v-model="returnFromTime" :label="t('grossanlass.materials.zusage.fieldFrom')" />
        <ETimeField v-model="returnToTime" :label="t('grossanlass.materials.zusage.fieldTo')" />
      </div>
      <ESwitch
        v-if="windowTargetIds.length === 1"
        v-model="windowReleased"
        :disabled="windowQtyZero"
        :label="t('grossanlass.materials.zusage.fieldRelease')"
        :hint="windowQtyZero
          ? t('grossanlass.beschaffung.zusagen.takeQtyHeld')
          : t('grossanlass.materials.zusage.fieldReleaseHint')"
        persistent-hint
      />
      <template #actions>
        <EButton variant="secondary" size="small" @click="windowOpen = false">
          {{ t('common.cancel') }}
        </EButton>
        <EButton
          variant="primary"
          size="small"
          :disabled="!presentFromDate || !presentToDate || windowTargetIds.length === 0"
          :loading="windowSaving"
          @click="saveWindow"
        >
          {{ windowBulkPartner
            ? t('grossanlass.beschaffung.zusagen.bulkWindowSave', { count: windowTargetIds.length })
            : t('grossanlass.beschaffung.zusagen.windowSave') }}
        </EButton>
      </template>
    </EDialog>

    <GrossanlassProcurementWishBundleDialog
      v-if="viewWishes.length"
      v-model="viewWishDialogOpen"
      :department-id="departmentId"
      :wishes="viewWishes"
      :line-label="viewWishesLabel"
      :line-quantity="viewWishesQty"
      :calendar-periods="calendarPeriods"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton, EDateField, EDateRangeField, EDialog, ESearchField, ESelect, ESwitch, ETimeField } from '@/components/form/base'
import { AutoSaveField } from '@/components/common/autoSave'
import type { AutoSaveFieldValue } from '@/components/common/autoSave/types'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import GrossanlassZusageCreatePreviewDialog from '@/views/grossanlass/GrossanlassZusageCreatePreviewDialog.vue'
import GrossanlassZusageAlignStrip from '@/views/grossanlass/GrossanlassZusageAlignStrip.vue'
import GrossanlassProcurementWishBundleDialog from '@/components/grossanlass/GrossanlassProcurementWishBundleDialog.vue'
import GrossanlassProcurementCoverage from '@/components/grossanlass/GrossanlassProcurementCoverage.vue'
import {
  combineIso,
  feinDeltaKind,
  formatGaIsoLabel,
  isoDatePart,
  isoTimePart,
  type GaZusageArticle,
  type GaZusageOrigin,
} from '@/views/grossanlass/grossanlassZusagePreviewData'
import {
  parseLocalDate,
  type GaCalendarScale,
} from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import { normalizeDepartmentTimeHHMM } from '@/utils/activityPlanningFromDefaults'
import { type GaZusageCreateDraft } from '@/views/grossanlass/grossanlassZusagePreviewStore'
import { useToast } from '@/composables/useToast'
import {
  createGrossanlassCommitment,
  getGrossanlassCommitments,
  updateGrossanlassCommitment,
  type GrossanlassCommitment,
  type GrossanlassCommitmentPayload,
} from '@/api/grossanlassCommitments'
import { getGrossanlassInquiries, type GrossanlassInquiry } from '@/api/grossanlassInquiries'
import { resolveMediaPreviewUrl } from '@/api/media'
import {
  formatChf,
  getGrossanlassBedarfOverview,
  type GrossanlassBedarfOverview,
  type GrossanlassProcurementCategory,
  type GrossanlassProcurementLine,
  type GrossanlassProcurementPoolWish,
  type GrossanlassProcurementQuote,
} from '@/api/grossanlassProcurement'
import { descendantIdsOfProcurementCategory } from '@/utils/grossanlassProcurementCategoryTree'
import { procurementIsOrdered, procurementOrderedQty } from '@/utils/grossanlassProcurementCoverage'
import { listDepartmentCalendarPeriods, type DepartmentCalendarPeriod } from '@/api/calendarPeriods'
import { getGrossanlassPlanung } from '@/api/grossanlassPlanung'
import { ensureInboundEinsatz } from '@/views/grossanlass/gaPickupEinsatz'
import { resolveWishNeedPeriod } from '@/utils/grossanlassWishPeriod'

type GroupBy = 'source' | 'family' | 'status'
type SortBy = 'name' | 'handover' | 'return' | 'status'
type ZusageRow = {
  id: string
  name: string
  source: string
  family: 'vehicle' | 'material'
  origin: GaZusageOrigin
  released: boolean
  delta: 'wide' | 'fit' | 'none'
  handoverIso: string
  handoverToIso: string
  returnIso: string
  returnToIso: string
  partnerFrom: string
  partnerTo: string
  presentFromIso: string
  presentToIso: string
  handover: string
  giveback: string
  wishLabel: string
  wishFrom: string
  wishTo: string
  wishFromIso: string
  wishToIso: string
  inquiryId: string | null
  isShell: boolean
  fromLineId: string
  quantity: number
  hasWindow: boolean
}

type TakeGroup = {
  id: string
  label: string
  rows: ZusageRow[]
  articleRows: ZusageRow[]
  inquiry: GrossanlassInquiry | null
  takeLines: GrossanlassProcurementLine[]
  heldCount: number
  wideCount: number
}

const AUTO_EXPAND_MAX = 8

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const createOpen = ref(false)
const createPreset = ref<Partial<GaZusageCreateDraft> | null>(null)
const query = ref('')
const groupBy = ref<GroupBy>('source')
const sortBy = ref<SortBy>('handover')
const openGroups = ref<string[]>([])
const articles = ref<GrossanlassCommitment[]>([])
const inquiries = ref<GrossanlassInquiry[]>([])
const lines = ref<GrossanlassProcurementLine[]>([])
const categories = ref<GrossanlassProcurementCategory[]>([])
const calendarPeriods = ref<DepartmentCalendarPeriod[]>([])
const takeQty = reactive<Record<string, number>>({})
const alignScale = reactive<Record<string, GaCalendarScale>>({})
const alignAnchor = reactive<Record<string, string>>({})
const isLoading = ref(false)
const windowOpen = ref(false)
const viewWishDialogOpen = ref(false)
const viewWishes = ref<GrossanlassProcurementPoolWish[]>([])
const viewWishesLabel = ref('')
const viewWishesQty = ref(0)
const windowSaving = ref(false)
const windowTargetIds = ref<string[]>([])
const windowBulkPartner = ref('')
const windowReleased = ref(false)
const presentFromDate = ref('')
const presentToDate = ref('')
const presentFromTime = ref('08:00')
const presentToTime = ref('18:00')
const handoverDate = ref('')
const handoverFromTime = ref('07:00')
const handoverToTime = ref('08:00')
const inboundMode = ref<'pickup' | 'delivery'>('pickup')
const logisticsGroupId = ref<string | null>(null)
const returnDate = ref('')
const returnFromTime = ref('08:00')
const returnToTime = ref('12:00')

const departmentId = computed(() => String(route.params.departmentId || ''))

const windowQtyZero = computed(() => {
  const id = windowTargetIds.value[0]
  if (!id || windowTargetIds.value.length !== 1) return false
  return (articles.value.find((item) => item.id === id)?.quantity ?? 0) <= 0
})

const windowWishText = computed(() => {
  if (windowBulkPartner.value) return ''
  const id = windowTargetIds.value[0]
  if (!id) return ''
  const article = articles.value.find((item) => item.id === id)
  if (!article) return ''
  const need = resolvedNeedForArticle(article)
  if (!need.from || !need.to) return ''
  return t('grossanlass.planung.feinPartner.wishWindow', {
    wish: need.label,
    from: formatGaIsoLabel(need.from, locale.value),
    to: formatGaIsoLabel(need.to, locale.value),
  })
})

const groupItems = computed(() => [
  { title: t('grossanlass.beschaffung.zusagen.groupByPartner'), value: 'source' },
  { title: t('grossanlass.beschaffung.zusagen.groupByFamily'), value: 'family' },
  { title: t('grossanlass.beschaffung.zusagen.groupByStatus'), value: 'status' },
])

const sortItems = computed(() => [
  { title: t('grossanlass.beschaffung.zusagen.sortName'), value: 'name' },
  { title: t('grossanlass.beschaffung.zusagen.sortHandover'), value: 'handover' },
  { title: t('grossanlass.beschaffung.zusagen.sortReturn'), value: 'return' },
  { title: t('grossanlass.beschaffung.zusagen.sortStatus'), value: 'status' },
])

const alignScales = computed(() => [
  { id: 'day' as const, label: t('grossanlass.materialUebersicht.scaleDay') },
  { id: 'week' as const, label: t('grossanlass.materialUebersicht.scaleWeek') },
  { id: 'month' as const, label: t('grossanlass.materialUebersicht.scaleMonth') },
])

function deltaOf(article: GrossanlassCommitment): 'wide' | 'fit' | 'none' {
  const need = resolvedNeedForArticle(article)
  if (!need.from || !need.to || !article.present_from || !article.present_to) return 'none'
  return feinDeltaKind({
    presentFromIso: article.present_from,
    presentToIso: article.present_to,
    feinWish: {
      label: need.label,
      ressort: '',
      fromIso: need.from,
      toIso: need.to,
    },
  } as GaZusageArticle)
}

function isPartnerShell(article: GrossanlassCommitment): boolean {
  return !article.item_details?.from_line_id && article.name === article.source && !article.wish_label
}

function takeKey(inquiryId: string, lineId: string): string {
  return `${inquiryId}:${lineId}`
}

function setTakeQty(inquiryId: string, lineId: string, value: string | number | null) {
  takeQty[takeKey(inquiryId, lineId)] = Math.max(0, Number(value) || 0)
}

function takenByInquiry(inquiryId: string, lineId: string): number {
  return articles.value
    .filter((article) =>
      countsAsPartnerTake(article)
      && article.inquiry_id === inquiryId
      && article.item_details?.from_line_id === lineId,
    )
    .reduce((sum, article) => sum + article.quantity, 0)
}

function takenElsewhere(inquiryId: string, lineId: string): number {
  return articles.value
    .filter((article) =>
      countsAsPartnerTake(article)
      && article.inquiry_id !== inquiryId
      && article.item_details?.from_line_id === lineId,
    )
    .reduce((sum, article) => sum + article.quantity, 0)
}

function takenExceptRow(rowId: string, lineId: string): number {
  return articles.value
    .filter((article) =>
      countsAsPartnerTake(article)
      && article.id !== rowId
      && article.item_details?.from_line_id === lineId,
    )
    .reduce((sum, article) => sum + article.quantity, 0)
}

function countsAsPartnerTake(article: GrossanlassCommitment): boolean {
  return !articleIsQuoteKauf(article)
}

function articleIsQuoteKauf(article: GrossanlassCommitment): boolean {
  if (article.inquiry_id) return false
  const line = lineForArticle(article)
  if (!line?.quotes.length) return article.origin === 'buy' || article.origin === 'buy_resale'
  return line.quotes.some((quote) => quote.supplier.toLowerCase() === article.source.toLowerCase())
}

function isQuoteBackedKauf(row: ZusageRow): boolean {
  if (row.inquiryId) return false
  const line = matchLineForRow(row)
  if (!line?.quotes.length) return row.origin === 'buy' || row.origin === 'buy_resale'
  return line.quotes.some((quote) => quote.supplier.toLowerCase() === row.source.toLowerCase())
}

function wishesOf(line: GrossanlassProcurementLine | null | undefined): GrossanlassProcurementPoolWish[] {
  return line?.source_wishes ?? []
}

function categoryIdsForInquiry(inquiry: GrossanlassInquiry): Set<string> {
  const ids = new Set<string>()
  for (const categoryId of inquiry.category_ids) {
    for (const id of descendantIdsOfProcurementCategory(categories.value, categoryId)) {
      ids.add(id)
    }
  }
  return ids
}

function takeLinesForInquiry(inquiry: GrossanlassInquiry): GrossanlassProcurementLine[] {
  const ids = categoryIdsForInquiry(inquiry)
  const match = ids.size
    ? lines.value.filter((line) => line.category_id != null && ids.has(line.category_id))
    : lines.value
  return [...match].sort((a, b) => a.label.localeCompare(b.label, locale.value))
}

function inquiryForGroup(groupRows: ZusageRow[], label: string): GrossanlassInquiry | null {
  const inquiryId = groupRows.find((row) => row.inquiryId)?.inquiryId
  if (inquiryId) return inquiries.value.find((row) => row.id === inquiryId) ?? null
  return inquiries.value.find((row) => row.status === 'zusage' && row.name === label) ?? null
}

function syncTakeQty() {
  for (const inquiry of inquiries.value) {
    if (inquiry.status !== 'zusage') continue
    for (const line of takeLinesForInquiry(inquiry)) {
      const key = takeKey(inquiry.id, line.id)
      if (takeQty[key] == null) takeQty[key] = takenByInquiry(inquiry.id, line.id)
    }
  }
}

const rows = computed<ZusageRow[]>(() =>
  articles.value.map((article) => {
    const need = resolvedNeedForArticle(article)
    return {
    id: article.id,
    name: article.name,
    source: article.source,
    family: article.family,
    origin: article.origin,
    released: article.released,
    delta: deltaOf(article),
    handoverIso: article.handover_from || '',
    handoverToIso: article.handover_to || '',
    returnIso: article.return_from || '',
    returnToIso: article.return_to || '',
    partnerFrom: article.present_from ? formatGaIsoLabel(article.present_from, locale.value) : '—',
    partnerTo: article.present_to ? formatGaIsoLabel(article.present_to, locale.value) : '—',
    presentFromIso: article.present_from || '',
    presentToIso: article.present_to || '',
    handover: article.handover_from ? formatGaIsoLabel(article.handover_from, locale.value) : '—',
    giveback: article.return_from ? formatGaIsoLabel(article.return_from, locale.value) : '—',
    wishLabel: need.label,
    wishFrom: need.from ? formatGaIsoLabel(need.from, locale.value) : '',
    wishTo: need.to ? formatGaIsoLabel(need.to, locale.value) : '',
    wishFromIso: need.from,
    wishToIso: need.to,
    inquiryId: article.inquiry_id,
    isShell: isPartnerShell(article),
    fromLineId: article.item_details?.from_line_id || '',
    quantity: article.quantity,
    hasWindow: Boolean(article.present_from && article.present_to),
  }
  }),
)

const filteredRows = computed(() => {
  const q = query.value.trim().toLowerCase()
  const list = rows.value.filter((row) => !isQuoteBackedKauf(row))
  const matched = q
    ? list.filter((row) =>
        [row.name, row.source, row.wishLabel].some((value) => value.toLowerCase().includes(q)),
      )
    : list
  return [...matched].sort(compareRows)
})

const grouped = computed<TakeGroup[]>(() => {
  const buckets = new Map<string, { id: string; label: string; rows: ZusageRow[] }>()
  for (const row of filteredRows.value) {
    const { id, label } = groupMeta(row)
    const bucket = buckets.get(id) ?? { id, label, rows: [] }
    bucket.rows.push(row)
    buckets.set(id, bucket)
  }
  return [...buckets.values()]
    .map((group) => {
      const articleRows = group.rows.filter((row) => !row.isShell)
      const inquiry = inquiryForGroup(group.rows, group.label)
      return {
        ...group,
        articleRows,
        inquiry,
        takeLines: inquiry ? takeLinesForInquiry(inquiry) : [],
        heldCount: articleRows.filter((row) => !row.released).length,
        wideCount: articleRows.filter((row) => row.delta === 'wide').length,
      }
    })
    .filter((group) => group.articleRows.length > 0 || (group.inquiry != null && group.takeLines.length > 0))
    .sort((a, b) => a.label.localeCompare(b.label, locale.value))
})

function groupMeta(row: ZusageRow): { id: string; label: string } {
  if (groupBy.value === 'family') {
    return {
      id: row.family,
      label: row.family === 'vehicle'
        ? t('grossanlass.materials.zusage.familyVehicle')
        : t('grossanlass.materials.zusage.familyMaterial'),
    }
  }
  if (groupBy.value === 'status') {
    return {
      id: row.released ? 'released' : 'held',
      label: row.released
        ? t('grossanlass.materials.zusage.releasedShort')
        : t('grossanlass.materials.zusage.heldShort'),
    }
  }
  return { id: `source:${row.source}`, label: row.source }
}

function compareRows(a: ZusageRow, b: ZusageRow): number {
  if (sortBy.value === 'handover') {
    return a.handoverIso.localeCompare(b.handoverIso) || a.name.localeCompare(b.name, locale.value)
  }
  if (sortBy.value === 'return') {
    return a.returnIso.localeCompare(b.returnIso) || a.name.localeCompare(b.name, locale.value)
  }
  if (sortBy.value === 'status') {
    return Number(a.released) - Number(b.released) || a.name.localeCompare(b.name, locale.value)
  }
  return a.name.localeCompare(b.name, locale.value)
}

function defaultOpenIds(): string[] {
  const ids = grouped.value.map((group) => group.id)
  const focus = String(route.query.inquiry || '')
  const focusId = grouped.value.find((group) => group.inquiry?.id === focus)?.id
  const base = query.value.trim() || filteredRows.value.length <= AUTO_EXPAND_MAX
    ? ids
    : grouped.value
      .filter((group) => group.heldCount > 0 || group.wideCount > 0 || group.inquiry != null)
      .map((group) => group.id)
  if (focusId && !base.includes(focusId)) return [...base, focusId]
  return base
}

function expandAll() {
  openGroups.value = grouped.value.map((group) => group.id)
}

function collapseAll() {
  openGroups.value = []
}

function openCreate(preset?: Partial<GaZusageCreateDraft>) {
  createPreset.value = preset ?? { origin: 'loan' }
  createOpen.value = true
}

function rowForLine(inquiryId: string, lineId: string): ZusageRow | null {
  return rows.value.find(
    (row) => row.inquiryId === inquiryId && row.fromLineId === lineId,
  ) ?? null
}

function articleIdForLine(inquiryId: string, lineId: string): string | null {
  return rowForLine(inquiryId, lineId)?.id ?? null
}

function leftoverArticles(group: TakeGroup): ZusageRow[] {
  if (!group.inquiry) return group.articleRows
  const taken = new Set(
    group.takeLines
      .map((line) => rowForLine(group.inquiry!.id, line.id)?.id)
      .filter((id): id is string => Boolean(id)),
  )
  return group.articleRows.filter((row) => !taken.has(row.id))
}

function normalizeNeedLabel(value: string): string {
  return value.toLowerCase().replace(/^\d+\s*[×x]\s*/, '').trim()
}

function matchLineForRow(row: ZusageRow): GrossanlassProcurementLine | null {
  if (row.fromLineId) {
    return lines.value.find((line) => line.id === row.fromLineId) ?? null
  }
  const needle = normalizeNeedLabel(row.name)
  if (!needle) return null
  return lines.value.find((line) => line.label.toLowerCase() === needle)
    ?? lines.value.find((line) => {
      const label = line.label.toLowerCase()
      return label.includes(needle) || needle.includes(label)
    })
    ?? null
}

function quoteForRow(row: ZusageRow): GrossanlassProcurementQuote | null {
  const line = matchLineForRow(row)
  if (!line) return null
  const bySupplier = line.quotes.find((quote) => quote.supplier.toLowerCase() === row.source.toLowerCase())
  return bySupplier || line.quotes.find((quote) => quote.selected) || null
}

function viewQuoteForRow(row: ZusageRow) {
  const quote = quoteForRow(row)
  if (quote?.pdf_url) {
    window.open(resolveMediaPreviewUrl(quote.pdf_url), '_blank', 'noopener')
    return
  }
  const line = matchLineForRow(row)
  const dept = departmentId.value
  if (!dept || !line) return
  void router.push({
    path: `/${dept}/beschaffung/offerten`,
    query: { line: line.id, supplier: row.source },
  })
}

function selectedQuoteOf(line: GrossanlassProcurementLine): GrossanlassProcurementQuote | null {
  return line.quotes.find((quote) => quote.selected) ?? null
}

function viewQuoteOf(line: GrossanlassProcurementLine) {
  const quote = selectedQuoteOf(line) || line.quotes[0]
  if (quote?.pdf_url) {
    window.open(resolveMediaPreviewUrl(quote.pdf_url), '_blank', 'noopener')
    return
  }
  goMoreQuotes(line)
}

function goOrderLine(lineId: string) {
  const dept = departmentId.value
  if (!dept) return
  void router.push({ path: `/${dept}/beschaffung/bestellungen`, query: { line: lineId } })
}

function goMoreQuotes(line: GrossanlassProcurementLine) {
  const dept = departmentId.value
  if (!dept) return
  const supplier = selectedQuoteOf(line)?.supplier || line.quotes[0]?.supplier || ''
  void router.push({
    path: `/${dept}/beschaffung/offerten`,
    query: { line: line.id, supplier },
  })
}

async function attachLeftoverToBedarf(row: ZusageRow) {
  const line = matchLineForRow(row)
  const article = articles.value.find((item) => item.id === row.id)
  if (!line || !article || !departmentId.value) return
  const need = resolvedNeedForLine(line)
  try {
    const updated = await updateGrossanlassCommitment(departmentId.value, article.id, {
      origin: article.inquiry_id ? article.origin : 'buy',
      wish_label: need.label || line.label,
      wish_from: need.from || null,
      wish_to: need.to || null,
      category_id: line.category_id,
      item_details: {
        ...article.item_details,
        from_line_id: line.id,
      },
    })
    articles.value = articles.value.map((item) => (item.id === updated.id ? updated : item))
    toast.success(t('grossanlass.beschaffung.zusagen.attachSuccess'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

function dateYmd(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function isosForLine(inquiryId: string, line: GrossanlassProcurementLine): string[] {
  const wish = wishIsoForLine(inquiryId, line)
  const row = rowForLine(inquiryId, line.id)
  return [
    wish.from,
    wish.to,
    row?.presentFromIso,
    row?.presentToIso,
    row?.handoverIso,
    row?.returnIso,
  ].filter((iso): iso is string => Boolean(iso))
}

function wishIsosForLine(inquiryId: string, line: GrossanlassProcurementLine): string[] {
  const wish = wishIsoForLine(inquiryId, line)
  return [wish.from, wish.to].filter((iso): iso is string => Boolean(iso))
}

function firstYmd(isos: string[]): string {
  const ymds = isos.map((iso) => isoDatePart(iso)).filter(Boolean).sort()
  return ymds[0] || dateYmd(new Date())
}

function lineAnchor(inquiryId: string, line: GrossanlassProcurementLine): string {
  const key = lineScaleKey(inquiryId, line.id)
  if (alignAnchor[key]) return alignAnchor[key]
  const wish = wishIsosForLine(inquiryId, line)
  return firstYmd(wish.length ? wish : isosForLine(inquiryId, line))
}

function rowAnchor(row: ZusageRow): string {
  const key = rowScaleKey(row.id)
  if (alignAnchor[key]) return alignAnchor[key]
  const wish = [row.wishFromIso, row.wishToIso].filter(Boolean)
  return firstYmd(wish.length ? wish : [row.presentFromIso, row.presentToIso, row.handoverIso, row.returnIso])
}

function spanDays(isos: string[]): number {
  const times = isos.map((iso) => parseLocalDate(iso).getTime()).filter((ms) => Number.isFinite(ms))
  if (times.length < 2) return 0
  return (Math.max(...times) - Math.min(...times)) / 86_400_000
}

function scaleFromIsos(isos: string[]): GaCalendarScale {
  const days = spanDays(isos)
  if (days <= 1.5) return 'day'
  if (days <= 10) return 'week'
  return 'month'
}

function lineScaleKey(inquiryId: string, lineId: string): string {
  return `line:${inquiryId}:${lineId}`
}

function rowScaleKey(rowId: string): string {
  return `row:${rowId}`
}

function scaleOfLine(inquiryId: string, line: GrossanlassProcurementLine): GaCalendarScale {
  const key = lineScaleKey(inquiryId, line.id)
  if (!alignScale[key]) {
    const wish = wishIsosForLine(inquiryId, line)
    alignScale[key] = scaleFromIsos(wish.length ? wish : isosForLine(inquiryId, line))
  }
  return alignScale[key]
}

function scaleOfRow(row: ZusageRow): GaCalendarScale {
  const key = rowScaleKey(row.id)
  if (!alignScale[key]) {
    const wish = [row.wishFromIso, row.wishToIso].filter(Boolean)
    alignScale[key] = scaleFromIsos(wish.length ? wish : [row.presentFromIso, row.presentToIso, row.wishFromIso, row.wishToIso])
  }
  return alignScale[key]
}

function setLineScale(inquiryId: string, lineId: string, scale: GaCalendarScale) {
  const key = lineScaleKey(inquiryId, lineId)
  alignScale[key] = scale
  if (scale === 'month') delete alignAnchor[key]
}

function setRowScale(rowId: string, scale: GaCalendarScale) {
  const key = rowScaleKey(rowId)
  alignScale[key] = scale
  if (scale === 'month') delete alignAnchor[key]
}

function hasAlignAnchor(key: string): boolean {
  return Boolean(alignAnchor[key])
}

function openLineDay(inquiryId: string, lineId: string, ymd: string) {
  const key = lineScaleKey(inquiryId, lineId)
  alignAnchor[key] = ymd
  alignScale[key] = 'day'
}

function openRowDay(rowId: string, ymd: string) {
  const key = rowScaleKey(rowId)
  alignAnchor[key] = ymd
  alignScale[key] = 'day'
}

function shiftLineAnchor(inquiryId: string, lineId: string, ymd: string) {
  alignAnchor[lineScaleKey(inquiryId, lineId)] = ymd
}

function shiftRowAnchor(rowId: string, ymd: string) {
  alignAnchor[rowScaleKey(rowId)] = ymd
}

function groupScaleKeys(group: TakeGroup): string[] {
  const keys: string[] = []
  if (group.inquiry) {
    for (const line of group.takeLines) keys.push(lineScaleKey(group.inquiry.id, line.id))
  }
  for (const row of leftoverArticles(group)) keys.push(rowScaleKey(row.id))
  return keys
}

function groupScale(group: TakeGroup): GaCalendarScale | '' {
  const keys = groupScaleKeys(group)
  if (!keys.length) return ''
  const scales = keys.map((key) => alignScale[key])
  if (scales.some((scale) => !scale)) return ''
  const first = scales[0]
  return scales.every((scale) => scale === first) ? first : ''
}

function setGroupScale(group: TakeGroup, scale: GaCalendarScale) {
  if (group.inquiry) {
    for (const line of group.takeLines) setLineScale(group.inquiry.id, line.id, scale)
  }
  for (const row of leftoverArticles(group)) setRowScale(row.id, scale)
}

function lineForArticle(article: GrossanlassCommitment): GrossanlassProcurementLine | undefined {
  const lineId = article.item_details?.from_line_id
  if (lineId) {
    const byId = lines.value.find((line) => line.id === lineId)
    if (byId) return byId
  }
  const needle = normalizeNeedLabel(article.name)
  if (!needle) return undefined
  return lines.value.find((line) => line.label.toLowerCase() === needle)
}

function resolvedNeedForLine(line: GrossanlassProcurementLine): { from: string; to: string; label: string } {
  const wish = line.source_wishes[0]
  const need = resolveWishNeedPeriod(wish, calendarPeriods.value)
  return {
    from: need?.from || '',
    to: need?.to || '',
    label: wish?.label || line.label,
  }
}

function resolvedNeedForArticle(article: GrossanlassCommitment): { from: string; to: string; label: string } {
  const line = lineForArticle(article)
  if (line) return resolvedNeedForLine(line)
  const need = resolveWishNeedPeriod({
    valid_from: article.wish_from,
    valid_to: article.wish_to,
  }, calendarPeriods.value)
  return {
    from: need?.from || '',
    to: need?.to || '',
    label: article.wish_label || article.name,
  }
}

function wishIsoForLine(_inquiryId: string, line: GrossanlassProcurementLine): { from: string; to: string; label: string } {
  return resolvedNeedForLine(line)
}

function wishTextForLine(inquiryId: string, line: GrossanlassProcurementLine): string {
  const wish = wishIsoForLine(inquiryId, line)
  if (!wish.from || !wish.to) return ''
  return t('grossanlass.planung.feinPartner.wishWindow', {
    wish: wish.label,
    from: formatGaIsoLabel(wish.from, locale.value),
    to: formatGaIsoLabel(wish.to, locale.value),
  })
}

function takeWindowText(inquiryId: string, line: GrossanlassProcurementLine): string {
  const row = rowForLine(inquiryId, line.id)
  if (row?.hasWindow) {
    return t('grossanlass.beschaffung.zusagen.takeWindowFirm', {
      from: row.partnerFrom,
      to: row.partnerTo,
    })
  }
  const window = wishWindow(line)
  if (!window.from || !window.to) return ''
  return t('grossanlass.beschaffung.zusagen.takeWindowWish', {
    from: formatGaIsoLabel(window.from, locale.value),
    to: formatGaIsoLabel(window.to, locale.value),
  })
}

function sourceWishForLine(line: GrossanlassProcurementLine | undefined | null): GrossanlassProcurementPoolWish | null {
  return line?.source_wishes[0] ?? null
}

function sourceWishForRow(row: ZusageRow): GrossanlassProcurementPoolWish | null {
  if (row.fromLineId) {
    const line = lines.value.find((item) => item.id === row.fromLineId)
    const wish = sourceWishForLine(line)
    if (wish) return wish
  }
  const article = articles.value.find((item) => item.id === row.id)
  return article ? sourceWishForLine(lineForArticle(article)) : null
}

function wishesForRow(row: ZusageRow): GrossanlassProcurementPoolWish[] {
  const fromLine = wishesOf(matchLineForRow(row))
  if (fromLine.length) return fromLine
  const wish = sourceWishForRow(row)
  return wish ? [wish] : []
}

function openWishBundle(
  wishes: GrossanlassProcurementPoolWish[],
  label?: string,
  quantity?: number,
) {
  if (!wishes.length) return
  viewWishes.value = wishes
  viewWishesLabel.value = label || wishes[0]?.label || ''
  viewWishesQty.value = quantity ?? wishes.reduce((sum, wish) => sum + wish.quantity, 0)
  viewWishDialogOpen.value = true
}

function openWindowForLine(inquiryId: string, lineId: string) {
  const row = rowForLine(inquiryId, lineId)
  if (row) openWindow(row)
}

function goBedarfLine(lineId: string) {
  const dept = departmentId.value
  if (!dept) return
  void router.push({ path: `/${dept}/beschaffung/bedarf`, query: { line: lineId } })
}

function wishWindow(line: GrossanlassProcurementLine): { from: string | null; to: string | null } {
  const need = resolvedNeedForLine(line)
  return { from: need.from || null, to: need.to || null }
}

let takeSaveQueue: Promise<void> = Promise.resolve()

function enqueueTakeSave(task: () => Promise<void>): Promise<void> {
  const run = takeSaveQueue.then(task, task)
  takeSaveQueue = run.then(() => undefined, () => undefined)
  return run
}

async function saveTakeLine(
  inquiry: GrossanlassInquiry | null,
  line: GrossanlassProcurementLine,
  value: AutoSaveFieldValue,
): Promise<void> {
  if (!inquiry || !departmentId.value) return
  const qty = Math.max(0, Number(value) || 0)
  takeQty[takeKey(inquiry.id, line.id)] = qty
  await enqueueTakeSave(() => persistTakeLine(inquiry, line, qty))
}

async function persistTakeLine(
  inquiry: GrossanlassInquiry,
  line: GrossanlassProcurementLine,
  qty: number,
): Promise<void> {
  if (!departmentId.value) return
  const existing = articles.value.find(
    (article) => article.inquiry_id === inquiry.id && article.item_details?.from_line_id === line.id,
  )
  const needWindow = wishWindow(line)
  try {
    if (qty <= 0) {
      if (!existing) return
      const wasReleased = existing.released
      const updated = await updateGrossanlassCommitment(departmentId.value, existing.id, {
        quantity: 0,
        released: false,
      })
      articles.value = articles.value.map((item) => (item.id === updated.id ? updated : item))
      if (wasReleased || existing.quantity > 0) {
        toast.success(t('grossanlass.beschaffung.zusagen.takeQtyHeld'))
      }
      return
    }
    if (existing) {
      const updated = await updateGrossanlassCommitment(departmentId.value, existing.id, {
        quantity: qty,
        wish_label: line.label,
        wish_from: needWindow.from,
        wish_to: needWindow.to,
      })
      articles.value = articles.value.map((item) => (item.id === updated.id ? updated : item))
      return
    }
    const shell = articles.value.find((article) => article.inquiry_id === inquiry.id && isPartnerShell(article))
    const payload: GrossanlassCommitmentPayload = {
      name: line.label,
      source: inquiry.name,
      family: line.wish_kind === 'fahrzeug' ? 'vehicle' : 'material',
      origin: shell?.origin ?? 'loan',
      quantity: qty,
      inquiry_id: inquiry.id,
      category_id: line.category_id,
      wish_label: line.label,
      wish_from: needWindow.from,
      wish_to: needWindow.to,
      item_details: {
        ...(shell?.item_details ?? {}),
        from_line_id: line.id,
        inbound_status: 'expected',
        inbound_mode: 'pickup',
      },
    }
    if (shell) {
      const updated = await updateGrossanlassCommitment(departmentId.value, shell.id, payload)
      articles.value = articles.value.map((item) => (item.id === updated.id ? updated : item))
      return
    }
    const created = await createGrossanlassCommitment(departmentId.value, payload)
    articles.value = [...articles.value, created]
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    throw new Error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

function splitIso(iso: string | null | undefined, fallbackTime: string): { date: string; time: string } {
  if (!iso) return { date: '', time: fallbackTime }
  return {
    date: isoDatePart(iso),
    time: normalizeDepartmentTimeHHMM(isoTimePart(iso) || fallbackTime),
  }
}

function isoOrNull(date: string, time: string): string | null {
  if (!date.trim()) return null
  return combineIso(date, time || '08:00')
}

function fillWindowForm(article: GrossanlassCommitment) {
  const need = resolvedNeedForArticle(article)
  const presentFrom = splitIso(article.present_from || need.from, '08:00')
  const presentTo = splitIso(article.present_to || need.to, '18:00')
  const handoverFrom = splitIso(article.handover_from, '07:00')
  const handoverTo = splitIso(article.handover_to, '08:00')
  const returnFrom = splitIso(article.return_from, '08:00')
  const returnTo = splitIso(article.return_to, '12:00')
  windowReleased.value = article.released
  presentFromDate.value = presentFrom.date
  presentToDate.value = presentTo.date
  presentFromTime.value = presentFrom.time
  presentToTime.value = presentTo.time
  handoverDate.value = handoverFrom.date || presentFrom.date
  handoverFromTime.value = handoverFrom.time
  handoverToTime.value = handoverTo.time
  inboundMode.value = article.origin === 'loan'
    ? (article.item_details?.inbound_mode === 'delivery' ? 'delivery' : 'pickup')
    : (article.item_details?.inbound_mode === 'pickup' ? 'pickup' : 'delivery')
  returnDate.value = returnFrom.date || presentTo.date
  returnFromTime.value = returnFrom.time
  returnToTime.value = returnTo.time
}

function openWindow(row: ZusageRow) {
  const article = articles.value.find((item) => item.id === row.id)
  if (!article) return
  fillWindowForm(article)
  windowTargetIds.value = [article.id]
  windowBulkPartner.value = ''
  windowOpen.value = true
}

function openBulkWindow(group: TakeGroup) {
  const ids = group.articleRows.map((row) => row.id)
  if (!ids.length) return
  const seeded = articles.value.find((item) => ids.includes(item.id) && item.present_from && item.present_to)
    ?? articles.value.find((item) => ids.includes(item.id))
  if (!seeded) return
  fillWindowForm(seeded)
  windowTargetIds.value = ids
  windowBulkPartner.value = group.label
  windowOpen.value = true
}

function windowPayload(includeReleased: boolean, article?: GrossanlassCommitment): Partial<GrossanlassCommitmentPayload> {
  const payload: GrossanlassCommitmentPayload = {
    present_from: isoOrNull(presentFromDate.value, presentFromTime.value),
    present_to: isoOrNull(presentToDate.value, presentToTime.value),
    handover_from: isoOrNull(handoverDate.value, handoverFromTime.value),
    handover_to: isoOrNull(handoverDate.value, handoverToTime.value),
    return_from: isoOrNull(returnDate.value, returnFromTime.value),
    return_to: isoOrNull(returnDate.value, returnToTime.value),
  }
  if (article) {
    const need = resolvedNeedForArticle(article)
    payload.wish_label = need.label || article.wish_label
    payload.wish_from = need.from || null
    payload.wish_to = need.to || null
    payload.item_details = {
      ...article.item_details,
      inbound_mode: inboundMode.value,
    }
  }
  if (includeReleased) payload.released = windowReleased.value
  return payload
}

async function saveWindow() {
  const ids = windowTargetIds.value
  if (!departmentId.value || !ids.length || !presentFromDate.value || !presentToDate.value) return
  windowSaving.value = true
  try {
    const updatedRows = await Promise.all(
      ids.map((id) => {
        const article = articles.value.find((item) => item.id === id)
        return updateGrossanlassCommitment(
          departmentId.value,
          id,
          windowPayload(ids.length === 1, article),
        )
      }),
    )
    const byId = new Map(updatedRows.map((row) => [row.id, row]))
    articles.value = articles.value.map((item) => byId.get(item.id) ?? item)
    const inboundNext: GrossanlassCommitment[] = []
    for (const row of updatedRows) {
      try {
        inboundNext.push(await ensureInboundEinsatz(
          departmentId.value,
          row,
          inboundMode.value === 'delivery'
            ? t('grossanlass.materialUebersicht.wareneingang.deliveryWho', { partner: row.source })
            : t('grossanlass.materialUebersicht.wareneingang.pickupWho', { partner: row.source }),
          logisticsGroupId.value,
        ))
      } catch {
        inboundNext.push(row)
        toast.error(t('grossanlass.materialUebersicht.wareneingang.inboundCreateError'))
      }
    }
    const inboundById = new Map(inboundNext.map((row) => [row.id, row]))
    articles.value = articles.value.map((item) => inboundById.get(item.id) ?? item)
    windowOpen.value = false
    toast.success(
      windowBulkPartner.value
        ? t('grossanlass.beschaffung.zusagen.bulkWindowSaved', { count: ids.length })
        : t('grossanlass.beschaffung.zusagen.windowSaved'),
    )
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    windowSaving.value = false
  }
}

async function toggleReleased(row: ZusageRow) {
  if (!departmentId.value) return
  try {
    const updated = await updateGrossanlassCommitment(departmentId.value, row.id, { released: !row.released })
    articles.value = articles.value.map((item) => (item.id === updated.id ? updated : item))
    toast.success(t('grossanlass.beschaffung.zusagen.releasedToast'))
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  }
}

async function load() {
  if (!departmentId.value) return
  isLoading.value = true
  try {
    const [commitmentRows, inquiryRows, overview, periods, planung] = await Promise.all([
      getGrossanlassCommitments(departmentId.value),
      getGrossanlassInquiries(departmentId.value).catch(() => [] as GrossanlassInquiry[]),
      getGrossanlassBedarfOverview(departmentId.value).catch(() => null),
      listDepartmentCalendarPeriods(departmentId.value).catch(() => [] as DepartmentCalendarPeriod[]),
      getGrossanlassPlanung(departmentId.value).catch(() => null),
    ])
    calendarPeriods.value = periods
    logisticsGroupId.value = planung?.config.logistics_group_id || null
    articles.value = commitmentRows
    inquiries.value = inquiryRows.filter((row) => row.status === 'zusage')
    lines.value = overview?.lines ?? []
    categories.value = overview?.categories ?? []
    syncTakeQty()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string } } }
    toast.error(err.response?.data?.error || t('grossanlass.beschaffung.zusagen.loadError'))
  } finally {
    isLoading.value = false
    openGroups.value = defaultOpenIds()
  }
}

function onCreated() {
  void router.replace({ path: route.path, query: {} })
  void load()
}

function presetFromQuery(): Partial<GaZusageCreateDraft> | null {
  const name = String(route.query.name || '').trim()
  const source = String(route.query.partner || '').trim()
  if (!name && !source) return null
  const family = route.query.family === 'vehicle' ? 'vehicle' : 'material'
  return {
    name,
    source,
    family,
    origin: 'loan',
    fromLineId: String(route.query.line || '') || undefined,
  }
}

watch([presentFromDate, presentToDate], ([from, to], previous) => {
  if (!windowOpen.value) return
  const prevFrom = previous?.[0] ?? ''
  const prevTo = previous?.[1] ?? ''
  if (from && (!handoverDate.value || handoverDate.value === prevFrom)) {
    handoverDate.value = from
  }
  if (to && (!returnDate.value || returnDate.value === prevTo)) {
    returnDate.value = to
  }
})

watch([groupBy, query], () => {
  openGroups.value = defaultOpenIds()
})

onMounted(() => {
  void load()
  const preset = presetFromQuery()
  if (preset) openCreate(preset)
})
</script>

<style scoped>
.zusagen-page { padding: 8px 0 24px; }
.tab-intro { margin: 0 0 12px; color: #64748b; font-size: 0.9rem; }
.zusagen-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 12px;
  align-items: flex-end;
  margin-bottom: 14px;
}
.zusagen-toolbar__search { flex: 1 1 200px; min-width: min(100%, 180px); }
.zusagen-toolbar__select { flex: 0 1 180px; min-width: 150px; }
.zusagen-toolbar__expand {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-left: auto;
}
.zusagen-list {
  list-style: none;
  margin: 0 0 8px;
  padding: 0;
  display: grid;
  gap: 10px;
}
.zusagen-card {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px 14px;
  display: grid;
  gap: 6px;
  font-size: 0.85rem;
}
.zusagen-card p { margin: 0; color: #334155; }
.zusagen-card__head {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  align-items: flex-start;
}
.zusagen-card__head > div {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.zusagen-badge,
.fein-badge {
  font-size: 0.72rem;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 999px;
}
.zusagen-badge.is-held { background: #ffedd5; color: #c2410c; }
.zusagen-badge.is-open { background: #dcfce7; color: #166534; }
.zusagen-badge.is-bundle { background: #e2e8f0; color: #334155; }
.fein-badge { background: #e2e8f0; color: #334155; }
.fein-badge--wide,
.zusagen-card--wide .fein-badge { background: #ffedd5; color: #c2410c; }
.zusagen-card--fit .fein-badge { background: #dcfce7; color: #166534; }
.zusagen-card__hint { color: #9a3412; font-weight: 600; }
.zusagen-card__actions {
  margin-top: 4px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.window-hint {
  margin: 0 0 8px;
  color: #475569;
  font-size: 0.9rem;
}
.window-hint--muted { color: #64748b; font-size: 0.82rem; }
.window-toggle {
  display: grid;
  grid-template-columns: 1fr 1fr;
  margin: 0 0 8px;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  overflow: hidden;
}
.window-toggle__btn {
  min-height: 48px;
  padding: 10px 8px;
  border: 0;
  background: #fff;
  font-size: 0.9rem;
  font-weight: 700;
  color: #334155;
  cursor: pointer;
}
.window-toggle__btn + .window-toggle__btn { border-left: 1px solid #e5e7eb; }
.window-toggle__btn--on { background: #0f766e; color: #fff; }
.window-section {
  margin: 12px 0 6px;
  font-size: 0.95rem;
}
.window-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.take-panel {
  background: #fff;
  border: 1px solid #dbeafe;
  border-radius: 10px;
  padding: 12px 14px;
  margin-bottom: 12px;
  display: grid;
  gap: 10px;
}
.take-panel__head {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  justify-content: space-between;
  align-items: flex-start;
}
.take-panel__bulk {
  flex: 0 0 auto;
  margin-left: auto;
}
.take-panel__head p,
.take-panel__fein,
.take-panel__empty {
  margin: 4px 0 0;
  color: #475569;
  font-size: 0.85rem;
}
.take-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.take-table th,
.take-table td {
  text-align: left;
  padding: 8px 8px 8px 0;
  vertical-align: top;
  border-bottom: 1px solid #e2e8f0;
}
.take-table th { color: #64748b; font-weight: 600; font-size: 0.75rem; }
.take-table__meta {
  display: block;
  color: #64748b;
  font-size: 0.75rem;
}
.take-table .fein-badge {
  display: inline-block;
  margin-top: 4px;
}
.take-table__autosave {
  max-width: 132px;
  margin: 0;
}
.take-table__autosave :deep(.autosave-label) {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}
.take-table__open {
  white-space: nowrap;
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  justify-content: flex-end;
}
.take-align {
  display: grid;
  gap: 10px;
}
.take-align__sticky {
  position: sticky;
  top: 0;
  z-index: 3;
  display: grid;
  gap: 6px;
  background: #fff;
  padding-bottom: 4px;
}
.take-align__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
}
.take-align__all {
  font-size: 0.75rem;
  font-weight: 700;
  color: #64748b;
}
.take-align__scales--item {
  margin-bottom: 4px;
}
.take-item__align {
  display: grid;
  gap: 4px;
  min-width: 0;
}
.take-align__scales,
.take-align__nav {
  display: inline-flex;
  align-items: center;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
}
.take-align__scale-btn,
.take-align__nav-btn {
  border: 0;
  background: transparent;
  padding: 6px 10px;
  font-size: 0.82rem;
  font-weight: 500;
  color: #6b7280;
  cursor: pointer;
}
.take-align__scale-btn--active {
  background: var(--color-primary-muted-bg, #dcfce7);
  color: var(--color-primary-dark, #166534);
}
.take-align__nav strong {
  min-width: 160px;
  text-align: center;
  font-size: 0.85rem;
  padding: 0 8px;
}
.take-align__legend {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 12px;
  list-style: none;
  margin: 0;
  padding: 0;
  font-size: 0.72rem;
  color: #6b7280;
}
.take-align__legend li {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.take-align__swatch {
  width: 16px;
  height: 8px;
  border-radius: 3px;
  flex-shrink: 0;
}
.take-align__swatch--wish {
  background: color-mix(in srgb, var(--color-primary) 16%, transparent);
  box-shadow: inset 0 0 0 2px var(--color-primary);
}
.take-align__swatch--wide { background: #fdba74; box-shadow: 0 0 0 1px #c2410c; }
.take-align__swatch--firm { background: var(--activity-status-packing, #0ea5e9); }
.take-align__swatch--handover { background: #0f766e; }
.take-align__swatch--giveback { background: #7c3aed; }
.take-item {
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 10px 12px;
  display: grid;
  gap: 8px;
  background: #f8fafc;
}
.take-item--wide { border-color: #fdba74; }
.take-item__top {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 16px;
  justify-content: space-between;
  align-items: flex-start;
}
.take-item__top > div:first-child {
  flex: 1 1 220px;
  min-width: 0;
}
.take-item__wish {
  margin: 4px 0 0;
  font-size: 0.82rem;
  color: #334155;
}
.take-item__qty {
  display: flex;
  flex-wrap: nowrap;
  gap: 12px 16px;
  align-items: flex-start;
  font-size: 0.8rem;
  color: #475569;
}
.take-item__qty :deep(.proc-coverage) {
  flex: 0 1 auto;
  min-width: 9.5rem;
}
.take-item__qty small {
  display: block;
  color: #64748b;
}
.take-item__wish-sum {
  display: block;
  margin-top: 2px;
  font-size: 0.78rem;
  font-weight: 500;
  color: #334155;
}
.take-item__over {
  color: #b45309 !important;
  font-weight: 600;
}
.take-item__kauf {
  margin: 8px 0 4px;
  padding: 10px 12px;
  border: 1px solid #bfdbfe;
  border-radius: 10px;
  background: #eff6ff;
}
.take-item__kauf-line {
  margin: 0;
  font-size: 0.84rem;
  color: #1e3a5f;
}
.take-item__kauf-status,
.take-item__kauf-picked {
  display: inline-block;
  margin-left: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 1px 8px;
  border-radius: 999px;
  background: #dbeafe;
  color: #1d4ed8;
}
.take-item__kauf-status--ordered {
  background: #fce7f3;
  color: #9d174d;
}
.take-item__kauf-more {
  margin: 4px 0 0;
  font-size: 0.78rem;
  color: #334155;
}
.take-item__kauf-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 6px;
}
.take-item .zusagen-badge,
.take-item .fein-badge {
  display: inline-block;
  margin-left: 6px;
  vertical-align: middle;
}
</style>
