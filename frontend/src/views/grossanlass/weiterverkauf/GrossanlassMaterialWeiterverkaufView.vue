<template>
  <div class="resale">
    <header class="resale__head">
      <p class="resale__intro">
        {{ t('grossanlass.verkauf.intro') }}
        <v-chip size="x-small" variant="tonal" color="warning" class="ml-2">{{ t('grossanlass.verkauf.prototype') }}</v-chip>
      </p>
      <div class="resale__head-actions">
        <EButton variant="secondary" @click="openPublic">
          <v-icon icon="mdi-open-in-new" start size="18" /> {{ t('grossanlass.verkauf.viewPublic') }}
        </EButton>
        <EButton v-if="tab === 'offers'" variant="secondary" @click="afterOpen = true">
          <v-icon icon="mdi-cart-arrow-right" start size="18" /> {{ t('grossanlass.verkauf.after.button') }}
        </EButton>
        <EButton v-if="tab === 'offers'" variant="primary" @click="openOffer(null)">
          <v-icon icon="mdi-plus" start size="18" /> {{ t('grossanlass.verkauf.newOffer') }}
        </EButton>
      </div>
    </header>

    <ol class="resale__flow" :aria-label="t('grossanlass.verkauf.flow.label')">
      <li v-for="(step, index) in flowSteps" :key="step"><span class="dot">{{ index + 1 }}</span>{{ t(`grossanlass.verkauf.flow.${step}`) }}</li>
    </ol>

    <v-tabs v-model="tab" class="materials-view-tabs" color="primary" show-arrows>
      <v-tab value="offers">{{ t('grossanlass.verkauf.tab.offers') }}</v-tab>
      <v-tab value="requests">
        {{ t('grossanlass.verkauf.tab.requests') }}
        <v-chip v-if="newCount" size="x-small" variant="flat" color="warning" class="ml-2">{{ newCount }}</v-chip>
      </v-tab>
      <v-tab value="sold">{{ t('grossanlass.verkauf.tab.sold') }}</v-tab>
    </v-tabs>

    <!-- Angebote -->
    <template v-if="tab === 'offers'">
      <EEmptyState v-if="!offers.length" variant="generic" icon="mdi-tag-outline" :title="t('grossanlass.verkauf.emptyOffers')" :description="t('grossanlass.verkauf.emptyOffersText')" />
      <div class="resale__grid">
        <article v-for="offer in offers" :key="offer.id" class="offer" :class="`offer--${offer.status}`">
          <div class="offer__img">
            <v-icon icon="mdi-image-outline" size="32" />
            <small>{{ offer.images ? t('grossanlass.verkauf.images', { n: offer.images }) : t('grossanlass.verkauf.noImage') }}</small>
          </div>
          <div class="offer__body">
            <header class="offer__head">
              <h4>{{ offer.name }}</h4>
              <v-chip size="x-small" variant="flat" :color="STATUS_COLOR[offer.status]">{{ t(`grossanlass.verkauf.status.${offer.status}`) }}</v-chip>
            </header>
            <p class="offer__price">{{ offer.priceChf > 0 ? `CHF ${offer.priceChf.toFixed(2)}` : t('grossanlass.verkauf.plan.priceOpen') }}</p>
            <p class="offer__phase">
              <v-chip size="x-small" variant="flat" :color="PHASE_COLOR[offer.phase]">{{ t(`grossanlass.verkauf.plan.phase.${offer.phase}`) }}</v-chip>
              <v-chip v-if="offer.phase !== 'afterUse'" size="x-small" variant="outlined">{{ t(`grossanlass.verkauf.plan.expected.${offer.expectedCondition}`) }}</v-chip>
              <v-chip v-if="offer.earlyPublish && offer.phase !== 'afterUse'" size="x-small" variant="outlined" prepend-icon="mdi-earth">{{ t('grossanlass.verkauf.plan.early') }}</v-chip>
            </p>
            <div class="offer__lifecycle">
              <div>
                <span>{{ t('grossanlass.verkauf.plan.useTitle') }}</span>
                <b v-if="offer.purchasedQty !== null">{{ t('grossanlass.verkauf.plan.usedOf', { used: offer.useQty ?? 0, bought: offer.purchasedQty }) }}</b>
                <b v-else>–</b>
              </div>
              <div>
                <span>{{ t('grossanlass.verkauf.plan.afterTitle') }}</span>
                <b>{{ offer.confirmed ? t('grossanlass.verkauf.plan.confirmedLine', { sellable: sellableQty(offer), planned: offer.qty }) : t('grossanlass.verkauf.plan.plannedLine', { n: offer.qty }) }}</b>
              </div>
            </div>
            <p v-if="offer.confirmed" class="offer__split">
              <v-chip v-for="key in GA_CONFIRMED_KEYS.filter((k) => offer.confirmed![k] > 0)" :key="key" size="x-small" variant="tonal" :color="key === 'good' ? 'success' : key === 'wear' ? 'warning' : 'error'">
                {{ offer.confirmed[key] }}× {{ t(`grossanlass.rueckbau.sale.split.${key}`) }}
              </v-chip>
            </p>
            <v-alert v-for="warning in warningsOf(offer)" :key="warning.kind" :type="warning.kind === 'reservedExceeds' ? 'error' : 'warning'" variant="tonal" density="compact">
              <template v-if="warning.kind === 'reservedExceeds'">{{ t('grossanlass.verkauf.plan.warnReserved', warning) }}</template>
              <template v-else>{{ t('grossanlass.verkauf.plan.warnDeviates', warning) }}</template>
            </v-alert>
            <p class="offer__meta">
              <v-chip size="x-small" variant="tonal" :color="CONDITION_COLOR[offer.condition]">{{ t(`grossanlass.verkauf.condition.${offer.condition}`) }}</v-chip>
              <v-chip size="x-small" variant="outlined" :prepend-icon="offer.visibility === 'public' ? 'mdi-earth' : 'mdi-lock-outline'">
                {{ t(`grossanlass.verkauf.visibility.${offer.visibility}`) }}
              </v-chip>
            </p>
            <dl class="offer__stats">
              <div><dt>{{ t('grossanlass.verkauf.plannedLabel') }}</dt><dd>{{ offer.qty }}</dd></div>
              <div><dt>{{ t('grossanlass.verkauf.reserved') }}</dt><dd>{{ stats(offer).reserved }}</dd></div>
              <div><dt>{{ offer.phase === 'afterUse' ? t('grossanlass.verkauf.available') : t('grossanlass.verkauf.expectedAvailable') }}</dt><dd>{{ stats(offer).available }}</dd></div>
              <div><dt>{{ t('grossanlass.verkauf.soldLabel') }}</dt><dd>{{ stats(offer).sold }}</dd></div>
            </dl>
            <p class="offer__meta"><v-icon icon="mdi-source-branch" size="14" /> {{ offer.origin }}</p>
            <p class="offer__meta"><v-icon icon="mdi-map-marker-outline" size="14" /> {{ offer.pickup }} · {{ offer.phase === 'afterUse' ? t('grossanlass.verkauf.fromDate', { date: offer.availableFrom }) : t('grossanlass.verkauf.plan.expectedFrom', { date: offer.availableFrom }) }}</p>
            <footer class="offer__actions">
              <EButton size="small" variant="secondary" @click="openOffer(offer.id)">{{ t('grossanlass.verkauf.edit') }}</EButton>
              <EButton v-if="offer.status !== 'published'" size="small" variant="primary" @click="publish(offer.id)">{{ t('grossanlass.verkauf.publish') }}</EButton>
              <EButton v-else size="small" variant="secondary" @click="setOfferStatus(offer.id, 'paused')">{{ t('grossanlass.verkauf.pause') }}</EButton>
              <v-chip v-if="stats(offer).open" size="x-small" variant="flat" color="warning">{{ t('grossanlass.verkauf.openRequests', { n: stats(offer).open }) }}</v-chip>
            </footer>
          </div>
        </article>
      </div>
    </template>

    <!-- Anfragen & Reservierungen -->
    <template v-else-if="tab === 'requests'">
      <EEmptyState v-if="!activeRequests.length" variant="generic" icon="mdi-inbox-outline" :title="t('grossanlass.verkauf.emptyRequests')" :description="t('grossanlass.verkauf.emptyRequestsText')" />
      <div class="requests">
        <article v-for="req in activeRequests" :key="req.id" class="req" :class="`req--${req.status}`">
          <header class="req__head">
            <div>
              <strong>{{ req.requesterName }}</strong>
              <v-chip size="x-small" variant="tonal" class="ml-2" :color="KIND_COLOR[req.requesterKind]" :prepend-icon="KIND_ICON[req.requesterKind]">
                {{ t(`grossanlass.verkauf.requester.${req.requesterKind}`) }}
              </v-chip>
            </div>
            <v-chip size="small" variant="flat" :color="REQ_COLOR[req.status]">{{ t(`grossanlass.verkauf.requestStatus.${req.status}`) }}</v-chip>
          </header>
          <v-alert v-if="requestWarning(req)" type="warning" variant="tonal" density="compact">{{ t('grossanlass.verkauf.plan.warnRequest') }}</v-alert>
          <p class="req__line">{{ req.qty }}× {{ offerName(req.offerId) }} · CHF {{ (req.qty * offerPrice(req.offerId)).toFixed(2) }}</p>
          <p v-if="req.organisation && req.organisation !== req.requesterName" class="req__line">
            <v-icon icon="mdi-domain" size="14" /> {{ req.organisation }}
          </p>
          <p v-if="req.requesterKind === 'onboarding'" class="req__onboarding">
            <v-icon icon="mdi-rocket-launch-outline" size="14" />
            {{ req.onboarding === 'linked' ? t('grossanlass.verkauf.onboarding.linked') : t('grossanlass.verkauf.onboarding.invited') }}
          </p>
          <p class="req__line">
            <v-icon :icon="req.handover === 'transport' ? 'mdi-truck-fast-outline' : 'mdi-hand-extended-outline'" size="14" />
            {{ t(`grossanlass.verkauf.handover.${req.handover}`) }} · {{ req.contact }}<template v-if="req.phone"> · {{ req.phone }}</template>
          </p>
          <p v-if="req.note" class="req__note">„{{ req.note }}“</p>
          <p v-if="req.staged && !req.handoverCode" class="req__code"><v-icon icon="mdi-package-variant-closed-check" size="14" /> {{ t('grossanlass.verkauf.staged') }}</p>
          <p v-if="req.handoverCode" class="req__code">
            <v-icon icon="mdi-qrcode" size="14" /> {{ t('grossanlass.verkauf.handoverCode', { code: req.handoverCode }) }}
            <template v-if="req.staged"> · <v-icon icon="mdi-package-variant-closed-check" size="14" /> {{ t('grossanlass.verkauf.staged') }}</template>
            <template v-if="req.transportSent"> · <v-icon icon="mdi-truck-fast-outline" size="14" /> {{ t('grossanlass.verkauf.transportSent') }}</template>
            <template v-else-if="req.handover === 'pickup'"> · {{ t('grossanlass.verkauf.selfPickup') }}</template>
          </p>
          <footer class="req__actions">
            <EButton v-if="req.status === 'new'" size="small" variant="secondary" @click="decline(req.id)">{{ t('grossanlass.verkauf.decline') }}</EButton>
            <EButton v-if="req.status === 'new'" size="small" variant="primary" @click="reserve(req.id)">{{ t('grossanlass.verkauf.reserve') }}</EButton>
            <EButton v-if="req.status === 'reserved'" size="small" variant="primary" @click="prepare(req.id)">{{ t('grossanlass.verkauf.prepareHandover') }}</EButton>
            <EButton v-if="req.status === 'reserved'" size="small" variant="secondary" @click="decline(req.id)">{{ t('grossanlass.verkauf.decline') }}</EButton>
            <EButton v-if="req.status === 'confirmed'" size="small" variant="primary" @click="complete(req.id)">{{ t('grossanlass.verkauf.completeHandover') }}</EButton>
          </footer>
        </article>
      </div>
    </template>

    <!-- Verkauft -->
    <template v-else>
      <div class="resale__kpis">
        <div class="kpi"><strong>{{ soldRequests.length }}</strong><span>{{ t('grossanlass.verkauf.soldCount') }}</span></div>
        <div class="kpi"><strong>CHF {{ revenue.toFixed(2) }}</strong><span>{{ t('grossanlass.verkauf.revenue') }}</span></div>
      </div>
      <EEmptyState v-if="!soldRequests.length" variant="generic" icon="mdi-check-all" :title="t('grossanlass.verkauf.emptySold')" :description="t('grossanlass.verkauf.emptySoldText')" />
      <table v-else class="sold-table">
        <thead>
          <tr>
            <th>{{ t('grossanlass.verkauf.col.item') }}</th><th>{{ t('grossanlass.verkauf.col.buyer') }}</th>
            <th>{{ t('grossanlass.verkauf.col.qty') }}</th><th>{{ t('grossanlass.verkauf.col.amount') }}</th><th>{{ t('grossanlass.verkauf.col.handover') }}</th><th>{{ t('grossanlass.verkauf.col.after') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="req in soldRequests" :key="req.id">
            <td>{{ offerName(req.offerId) }}</td>
            <td>{{ req.requesterName }}</td>
            <td>{{ req.qty }}</td>
            <td>CHF {{ (req.qty * offerPrice(req.offerId)).toFixed(2) }}</td>
            <td>{{ t(`grossanlass.verkauf.handover.${req.handover}`) }}</td>
            <td>
              <v-chip size="x-small" variant="tonal" :color="followUpAfterHandover(req) === 'takeover' ? 'info' : 'success'">
                {{ followUpAfterHandover(req) === 'takeover' ? t('grossanlass.verkauf.afterTakeover') : t('grossanlass.verkauf.afterClosed') }}
              </v-chip>
            </td>
          </tr>
        </tbody>
      </table>
    </template>

    <GrossanlassVerkaufOfferDialog v-model="offerOpen" :offer-id="offerId" />
    <GrossanlassVerbleibDialog v-model="afterOpen" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { EButton } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useToast } from '@/composables/useToast'
import GrossanlassVerbleibDialog from './GrossanlassVerbleibDialog.vue'
import GrossanlassVerkaufOfferDialog from './GrossanlassVerkaufOfferDialog.vue'
import {
  completeHandover,
  followUpAfterHandover,
  declineRequest,
  GA_CONFIRMED_KEYS,
  offerById,
  offerWarnings,
  sellableQty,
  offerStats,
  prepareHandover,
  requestsOf,
  reserveRequest,
  setOfferStatus,
  soldRevenue,
  useGaVerkaufMock,
  type GaOffer,
  type GaOfferStatus,
  type GaRequestStatus,
  type GaRequesterKind,
} from './gaVerkaufMock'

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const { offers, requests } = useGaVerkaufMock()

const flowSteps = ['rueckbau', 'resale', 'publish', 'public', 'request', 'confirm', 'handover', 'sold'] as const
const STATUS_COLOR: Record<GaOfferStatus, string> = { draft: 'grey', published: 'success', paused: 'warning' }
const REQ_COLOR: Record<GaRequestStatus, string> = { new: 'warning', reserved: 'info', confirmed: 'success', declined: 'grey', handed: 'success' }
const PHASE_COLOR = { planned: 'grey', inUse: 'info', afterUse: 'success' } as const
function warningsOf(offer: GaOffer) {
  void requests.value
  return offerWarnings(offer)
}
/** Anfrage betroffen, wenn das Angebot mehr reserviert hat, als verkaufbar ist. */
function requestWarning(req: { offerId: string; status: string }): boolean {
  const offer = offerById(req.offerId)
  return !!offer && (req.status === 'reserved' || req.status === 'confirmed') && warningsOf(offer).some((entry) => entry.kind === 'reservedExceeds')
}
const KIND_COLOR: Record<GaRequesterKind, string> = { department: 'primary', onboarding: 'info', external: 'grey' }
const KIND_ICON: Record<GaRequesterKind, string> = {
  department: 'mdi-account-group-outline',
  onboarding: 'mdi-rocket-launch-outline',
  external: 'mdi-account-outline',
}
const CONDITION_COLOR = { good: 'success', used: 'warning', damaged: 'error' } as const

const tab = ref<'offers' | 'requests' | 'sold'>('offers')
const afterOpen = ref(false)
const offerOpen = ref(false)
const offerId = ref<string | null>(null)

function stats(offer: GaOffer) {
  void requests.value
  return offerStats(offer)
}
const activeRequests = computed(() => {
  void requests.value
  return requestsOf(['new', 'reserved', 'confirmed'])
})
const soldRequests = computed(() => {
  void requests.value
  return requestsOf(['handed'])
})
const newCount = computed(() => requests.value.filter((row) => row.status === 'new').length)
const revenue = computed(() => {
  void requests.value
  return soldRevenue()
})

function offerName(id: string): string {
  return offerById(id)?.name ?? ''
}
function offerPrice(id: string): number {
  return offerById(id)?.priceChf ?? 0
}
function openOffer(id: string | null) {
  offerId.value = id
  offerOpen.value = true
}
function openPublic() {
  void router.push('/qr-demo/material')
}
function publish(id: string) {
  if (setOfferStatus(id, 'published')) toast.success(t('grossanlass.verkauf.published'))
}
function reserve(id: string) {
  if (reserveRequest(id)) toast.success(t('grossanlass.verkauf.reservedToast'))
  else toast.error(t('grossanlass.verkauf.notEnough'))
}
function decline(id: string) {
  if (declineRequest(id)) toast.success(t('grossanlass.verkauf.declinedToast'))
}
function prepare(id: string) {
  const request = requests.value.find((row) => row.id === id)
  if (prepareHandover(id)) {
    toast.success(request?.handover === 'transport' ? t('grossanlass.verkauf.transportToast') : t('grossanlass.verkauf.pickupToast'))
  }
}
function complete(id: string) {
  if (completeHandover(id)) toast.success(t('grossanlass.verkauf.soldToast'))
}
</script>

<style scoped>
.resale {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 4px 0 28px;
}
.resale__head {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
}
.resale__intro {
  margin: 0;
  color: #64748b;
  font-size: 0.9rem;
  flex: 1 1 320px;
}
.resale__head-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.resale__flow {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 12px;
  margin: 0;
  padding: 10px 12px;
  list-style: none;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  font-size: 0.8rem;
}
.resale__flow li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.resale__flow li:not(:last-child)::after {
  content: '→';
  margin-left: 6px;
  color: #94a3b8;
}
.resale__flow .dot {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #ecfdf5;
  color: #065f46;
  font-size: 0.7rem;
  font-weight: 700;
}
.resale__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
  gap: 12px;
}
.offer {
  display: flex;
  flex-direction: column;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #fff;
  overflow: hidden;
}
.offer--paused {
  opacity: 0.8;
}
.offer__img {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  min-height: 96px;
  background: #f1f5f9;
  color: #94a3b8;
}
.offer__body {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px 14px 14px;
}
.offer__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}
.offer__head h4 {
  margin: 0;
  font-size: 1rem;
}
.offer__price {
  margin: 0;
  font-weight: 700;
  color: #0f766e;
}
.offer__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 0.84rem;
  color: #475569;
}
.offer__phase,
.offer__split {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin: 0;
}
.offer__lifecycle {
  display: grid;
  gap: 6px;
  padding: 8px 10px;
  border-radius: 10px;
  background: #f8fafc;
  font-size: 0.84rem;
}
.offer__lifecycle span {
  display: block;
  font-size: 0.68rem;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748b;
}
.offer__stats {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
  margin: 0;
}
.offer__stats > div {
  padding: 6px 8px;
  border-radius: 10px;
  background: #f8fafc;
}
.offer__stats dt {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
}
.offer__stats dd {
  margin: 0;
  font-size: 1.2rem;
  font-weight: 700;
}
.offer__actions,
.req__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
}
.requests {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 12px;
}
.req {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 14px;
  border: 1px solid #e5e7eb;
  border-left: 4px solid #f59e0b;
  border-radius: 12px;
  background: #fff;
}
.req--reserved {
  border-left-color: #2563eb;
}
.req--confirmed {
  border-left-color: #16a34a;
}
.req__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.req__line,
.req__note,
.req__code {
  margin: 0;
  font-size: 0.86rem;
  color: #334155;
}
.req__note {
  color: #64748b;
  font-style: italic;
}
.req__onboarding {
  margin: 0;
  font-size: 0.84rem;
  color: #1d4ed8;
}
.req__code {
  color: #065f46;
  font-weight: 600;
}
.resale__kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 10px;
}
.kpi {
  display: flex;
  flex-direction: column;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
}
.kpi strong {
  font-size: 1.5rem;
}
.kpi span {
  font-size: 0.78rem;
  color: #64748b;
}
.sold-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  font-size: 0.86rem;
}
.sold-table th,
.sold-table td {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  text-align: left;
}
.sold-table th {
  background: #f8fafc;
}
</style>
