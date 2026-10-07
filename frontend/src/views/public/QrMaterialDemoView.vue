<template>
  <div class="shop">
    <header class="shop__header" role="banner">
      <a class="shop__brand" href="/qr-demo/material" @click.prevent="goList">
        <EmcLogoMark size="sm" />
        <span>eMatChef <small>{{ t('grossanlass.verkauf.public.brand') }}</small></span>
      </a>
      <div class="shop__header-actions">
        <v-chip size="x-small" variant="tonal" color="warning">{{ t('grossanlass.verkauf.public.demo') }}</v-chip>
        <v-chip v-if="viewer.kind === 'department'" size="small" variant="flat" color="primary" prepend-icon="mdi-account-group-outline" closable @click:close="logoutViewer()">
          {{ viewer.department }}
        </v-chip>
        <EButton v-else size="small" variant="secondary" @click="loginOpen = true">{{ t('grossanlass.verkauf.public.login') }}</EButton>
        <EButton size="small" variant="primary" @click="cartOpen = true">
          <v-icon icon="mdi-cart-outline" start size="18" />
          {{ t('grossanlass.verkauf.public.cart') }} ({{ cart.length }})
        </EButton>
        <EButton v-if="showAdmin" size="small" variant="text" @click="goAdmin">{{ t('grossanlass.verkauf.public.toAdmin') }}</EButton>
      </div>
    </header>

    <main class="shop__main">
      <!-- Detail -->
      <template v-if="detail">
        <EButton variant="text" size="small" @click="goList">
          <v-icon icon="mdi-arrow-left" start size="18" /> {{ t('grossanlass.verkauf.public.back') }}
        </EButton>
        <article class="detail">
          <div class="detail__gallery">
            <div class="detail__img"><v-icon icon="mdi-image-outline" size="56" /><small>{{ t('grossanlass.verkauf.noImage') }}</small></div>
            <div v-if="detail.images > 1" class="detail__thumbs">
              <span v-for="index in detail.images" :key="index" class="thumb"><v-icon icon="mdi-image-outline" size="20" /></span>
            </div>
          </div>
          <div class="detail__info">
            <h1>{{ detail.name }}</h1>
            <p class="detail__price">{{ detail.priceChf > 0 ? `CHF ${detail.priceChf.toFixed(2)}` : t('grossanlass.verkauf.plan.priceOpen') }} <small v-if="detail.priceChf > 0">{{ t('grossanlass.verkauf.public.perPiece') }}</small></p>
            <p class="detail__chips">
              <v-chip size="small" variant="tonal" :color="CONDITION_COLOR[detail.condition]">{{ detail.phase === 'afterUse' ? t(`grossanlass.verkauf.condition.${detail.condition}`) : t(`grossanlass.verkauf.public.expected.${detail.expectedCondition}`) }}</v-chip>
              <v-chip size="small" variant="flat" :color="available(detail) > 0 ? 'success' : 'grey'">
                {{ available(detail) > 0 ? t('grossanlass.verkauf.public.availableQty', { n: available(detail) }) : t('grossanlass.verkauf.public.soldOut') }}
              </v-chip>
              <v-chip v-if="detail.visibility === 'departments'" size="small" variant="outlined" prepend-icon="mdi-lock-outline">
                {{ t('grossanlass.verkauf.visibility.departments') }}
              </v-chip>
            </p>
            <v-alert v-if="detail.phase !== 'afterUse'" type="info" variant="tonal" density="compact" class="detail__phase">
              <strong>{{ t('grossanlass.verkauf.public.inUse') }}</strong><br>
              {{ t('grossanlass.verkauf.public.expectedFrom', { date: detail.availableFrom }) }}<br>
              {{ t(`grossanlass.verkauf.public.expected.${detail.expectedCondition}`) }}<br>
              <small>{{ t('grossanlass.verkauf.public.confirmAfter') }}</small>
            </v-alert>
            <p class="detail__text">{{ detail.description || t('grossanlass.verkauf.public.noDescription') }}</p>
            <p class="detail__meta"><v-icon icon="mdi-map-marker-outline" size="16" /> {{ t('grossanlass.verkauf.public.pickupAt', { place: detail.pickup }) }}</p>
            <p class="detail__meta"><v-icon icon="mdi-calendar-check-outline" size="16" /> {{ t('grossanlass.verkauf.fromDate', { date: detail.availableFrom }) }}</p>

            <v-alert v-if="needsLogin(detail)" type="info" variant="tonal" density="compact" class="mt-2">
              {{ t('grossanlass.verkauf.public.loginNeeded') }}
              <template #append>
                <EButton size="small" variant="primary" @click="loginOpen = true">{{ t('grossanlass.verkauf.public.paths.login') }}</EButton>
                <EButton size="small" variant="secondary" class="ml-2" @click="openOnboardingStandalone">{{ t('grossanlass.verkauf.public.paths.onboardingShort') }}</EButton>
              </template>
            </v-alert>
            <div v-else-if="available(detail) > 0" class="detail__buy">
              <input v-model.number="qty" class="qty" type="number" min="1" :max="available(detail)" :aria-label="t('grossanlass.rueckbau.decide.qty')">
              <EButton variant="primary" size="large" @click="add(detail.id)">
                <v-icon icon="mdi-cart-plus" start size="20" /> {{ t('grossanlass.verkauf.public.addInterest') }}
              </EButton>
            </div>
          </div>
        </article>
      </template>

      <!-- Liste -->
      <template v-else>
        <section class="shop__hero">
          <h1>{{ t('grossanlass.verkauf.public.title') }}</h1>
          <p>{{ t('grossanlass.verkauf.public.subtitle') }}</p>
        </section>

        <section class="shop__filters">
          <ESearchField v-model="search" :label="t('grossanlass.verkauf.public.search')" />
          <ESelect v-model="conditionFilter" :items="conditionItems" :label="t('grossanlass.verkauf.offer.condition')" clearable hide-details />
          <ESelect v-model="pickupFilter" :items="pickupItems" :label="t('grossanlass.verkauf.offer.pickup')" clearable hide-details />
        </section>

        <v-alert v-if="viewer.kind === 'guest' && locked.length" type="info" variant="tonal" density="compact">
          {{ t('grossanlass.verkauf.public.lockedHint', { n: locked.length }) }}
          <template #append>
            <EButton size="small" variant="primary" @click="loginOpen = true">{{ t('grossanlass.verkauf.public.paths.login') }}</EButton>
            <EButton size="small" variant="secondary" class="ml-2" @click="openOnboardingStandalone">{{ t('grossanlass.verkauf.public.paths.onboardingShort') }}</EButton>
          </template>
        </v-alert>

        <EEmptyState v-if="!filteredOpen.length" variant="generic" icon="mdi-tag-off-outline" :title="t('grossanlass.verkauf.public.emptyTitle')" :description="t('grossanlass.verkauf.public.emptyText')" />

        <div class="shop__grid">
          <article v-for="offer in filteredOpen" :key="offer.id" class="card">
            <button type="button" class="card__img" :aria-label="offer.name" @click="openDetail(offer.id)">
              <v-icon icon="mdi-image-outline" size="34" />
            </button>
            <div class="card__body">
              <h3><a href="#" @click.prevent="openDetail(offer.id)">{{ offer.name }}</a></h3>
              <p class="card__price">{{ offer.priceChf > 0 ? `CHF ${offer.priceChf.toFixed(2)}` : t('grossanlass.verkauf.plan.priceOpen') }}</p>
              <p class="card__meta">
                <v-chip size="x-small" variant="tonal" :color="CONDITION_COLOR[offer.condition]">{{ offer.phase === 'afterUse' ? t(`grossanlass.verkauf.condition.${offer.condition}`) : t(`grossanlass.verkauf.public.expected.${offer.expectedCondition}`) }}</v-chip>
                <span>{{ available(offer) > 0 ? t('grossanlass.verkauf.public.availableQty', { n: available(offer) }) : t('grossanlass.verkauf.public.soldOut') }}</span>
              </p>
              <p v-if="offer.phase !== 'afterUse'" class="card__meta card__meta--phase">
                <v-icon icon="mdi-hammer-wrench" size="14" /> {{ t('grossanlass.verkauf.public.inUse') }}
                · {{ t('grossanlass.verkauf.public.expectedFromShort', { date: offer.availableFrom }) }}
              </p>
              <p class="card__meta"><v-icon icon="mdi-map-marker-outline" size="14" /> {{ offer.pickup }}</p>
              <EButton size="small" variant="primary" :disabled="available(offer) === 0" @click="openDetail(offer.id)">{{ t('grossanlass.verkauf.public.details') }}</EButton>
            </div>
          </article>

          <article v-for="offer in locked" :key="`l-${offer.id}`" class="card card--locked">
            <div class="card__img"><v-icon icon="mdi-lock-outline" size="30" /></div>
            <div class="card__body">
              <h3>{{ offer.name }}</h3>
              <p class="card__meta">{{ t('grossanlass.verkauf.public.departmentsOnly') }}</p>
              <EButton size="small" variant="secondary" @click="loginOpen = true">{{ t('grossanlass.verkauf.public.login') }}</EButton>
            </div>
          </article>
        </div>
      </template>
    </main>

    <!-- Login (Demo) -->
    <EDialog v-model="loginOpen" :title="t('grossanlass.verkauf.public.loginTitle')" max-width="440" :retain-focus="false">
      <p class="note">{{ t('grossanlass.verkauf.public.loginNote') }}</p>
      <ESelect v-model="loginDepartment" :items="departments" :label="t('grossanlass.rueckbau.sale.department')" hide-details />
      <template #actions>
        <EButton variant="secondary" @click="loginOpen = false">{{ t('common.cancel') }}</EButton>
        <EButton variant="primary" :disabled="!loginDepartment" @click="doLogin">{{ t('grossanlass.verkauf.public.loginAction') }}</EButton>
      </template>
    </EDialog>

    <!-- Demo-Onboarding -->
    <EDialog v-model="onboardingOpen" :title="t('grossanlass.verkauf.public.onboarding.title')" max-width="520" :retain-focus="false">
      <ol class="onb-steps">
        <li v-for="(label, index) in onboardingSteps" :key="label" :class="{ 'is-done': index < onboardingStep, 'is-current': index === onboardingStep }">
          <span class="dot">{{ index < onboardingStep ? '✓' : index + 1 }}</span>{{ label }}
        </li>
      </ol>
      <p v-if="onboardingRequestIds.length" class="note">
        <v-icon icon="mdi-bookmark-check-outline" size="16" /> {{ t('grossanlass.verkauf.public.onboarding.reservationKept', { n: onboardingRequestIds.length }) }}
      </p>
      <template v-if="onboardingStep === 0">
        <ETextField v-model="onb.department" :label="t('grossanlass.verkauf.public.onboarding.department')" hide-details="auto" />
        <ETextField v-model="onb.person" :label="t('grossanlass.verkauf.public.onboarding.person')" hide-details="auto" />
        <ETextField v-model="onb.email" type="email" :label="t('grossanlass.verkauf.public.email')" hide-details="auto" />
      </template>
      <template v-else-if="onboardingStep === 1">
        <p>{{ t('grossanlass.verkauf.public.onboarding.inviteText', { email: onb.email }) }}</p>
        <p class="note">{{ t('grossanlass.verkauf.public.onboarding.demoNote') }}</p>
      </template>
      <template v-else>
        <v-alert type="success" variant="tonal">{{ t('grossanlass.verkauf.public.onboarding.done', { department: onb.department }) }}</v-alert>
        <p class="note">{{ t('grossanlass.verkauf.public.onboarding.linkedNote') }}</p>
      </template>
      <template #actions>
        <EButton variant="secondary" @click="onboardingOpen = false">{{ t('common.close') }}</EButton>
        <EButton v-if="onboardingStep < 2" variant="primary" :disabled="onboardingStep === 0 && !onbValid" @click="onboardingStep += 1">
          {{ onboardingStep === 0 ? t('grossanlass.verkauf.public.onboarding.sendInvite') : t('grossanlass.verkauf.public.onboarding.simulateSetup') }}
        </EButton>
        <EButton v-else variant="primary" @click="finishOnboarding">{{ t('grossanlass.verkauf.public.onboarding.finish') }}</EButton>
      </template>
    </EDialog>

    <!-- Auswahl / Warenkorb -->
    <EDialog v-model="cartOpen" :title="t('grossanlass.verkauf.public.cartTitle')" max-width="560" :retain-focus="false">
      <template v-if="submitted.length">
        <v-alert type="success" variant="tonal">{{ t('grossanlass.verkauf.public.submitted', { n: submitted.length }) }}</v-alert>
        <p class="note">{{ t('grossanlass.verkauf.public.submittedNote') }}</p>
        <EButton v-if="needsOnboardingNext" variant="primary" @click="startOnboardingFromSubmitted">
          {{ t('grossanlass.verkauf.public.onboarding.continue') }}
        </EButton>
      </template>
      <template v-else-if="!cart.length">
        <p class="note">{{ t('grossanlass.verkauf.public.cartEmpty') }}</p>
      </template>
      <template v-else>
        <ul class="cart">
          <li v-for="line in cart" :key="line.offerId">
            <span>
              <strong>{{ offerName(line.offerId) }}</strong>
              <small>CHF {{ (line.qty * offerPrice(line.offerId)).toFixed(2) }}</small>
            </span>
            <input class="qty" type="number" min="1" :value="line.qty" :aria-label="t('grossanlass.rueckbau.decide.qty')" @change="setCartQty(line.offerId, Number(($event.target as HTMLInputElement).value))">
            <EButton variant="text" size="small" :aria-label="t('common.delete')" @click="removeFromCart(line.offerId)"><v-icon icon="mdi-close" size="18" /></EButton>
          </li>
        </ul>
        <p class="cart__total">{{ t('grossanlass.verkauf.public.total') }}: <strong>CHF {{ total.toFixed(2) }}</strong></p>

        <h4 class="paths__title">{{ t('grossanlass.verkauf.public.paths.title') }}</h4>
        <div class="paths" role="radiogroup" :aria-label="t('grossanlass.verkauf.public.paths.title')">
          <button
            v-for="option in pathOptions"
            :key="option"
            type="button"
            role="radio"
            :aria-checked="path === option"
            class="path"
            :class="{ 'path--active': path === option }"
            @click="choosePath(option)"
          >
            <v-icon :icon="PATH_ICON[option]" size="22" />
            <span>
              <strong>{{ t(`grossanlass.verkauf.public.paths.${option}`) }}</strong>
              <small>{{ t(`grossanlass.verkauf.public.paths.${option}Text`) }}</small>
            </span>
          </button>
        </div>

        <div v-if="path" class="path-form">
          <template v-if="path === 'login'">
            <v-alert v-if="viewer.kind !== 'department'" type="info" variant="tonal" density="compact">
              {{ t('grossanlass.verkauf.public.paths.loginFirst') }}
              <template #append><EButton size="small" variant="primary" @click="loginOpen = true">{{ t('grossanlass.verkauf.public.login') }}</EButton></template>
            </v-alert>
            <p v-else class="note">{{ t('grossanlass.verkauf.public.asDepartment', { department: viewer.department }) }}</p>
          </template>
          <template v-else-if="path === 'onboarding'">
            <p class="note">{{ t('grossanlass.verkauf.public.paths.onboardingNote') }}</p>
          </template>

          <ETextField v-model="form.name" :label="path === 'login' ? t('grossanlass.verkauf.public.contactPerson') : t('grossanlass.verkauf.public.fullName')" hide-details="auto" />
          <ETextField v-if="path !== 'login'" v-model="form.organisation" :label="t('grossanlass.verkauf.public.organisation')" hide-details="auto" />
          <ETextField v-model="form.contact" type="email" :label="t('grossanlass.verkauf.public.email')" hide-details="auto" />
          <ETextField v-if="path === 'external'" v-model="form.phone" :label="t('grossanlass.verkauf.public.phoneOptional')" hide-details="auto" />
          <ETextarea v-model="form.note" :label="t('grossanlass.verkauf.public.note')" rows="2" hide-details />
          <v-btn-toggle v-model="form.handover" mandatory density="compact" color="primary" variant="outlined">
            <v-btn value="pickup" size="small">{{ t('grossanlass.verkauf.handover.pickup') }}</v-btn>
            <v-btn value="transport" size="small">{{ t('grossanlass.verkauf.handover.transport') }}</v-btn>
          </v-btn-toggle>
          <p v-if="path === 'external'" class="note">{{ t('grossanlass.verkauf.public.paths.externalNote') }}</p>
        </div>
      </template>
      <template #actions>
        <EButton variant="secondary" @click="closeCart">{{ t('common.close') }}</EButton>
        <EButton v-if="cart.length && !submitted.length" variant="primary" :disabled="!canSend" @click="send">
          {{ sendLabel }}
        </EButton>
      </template>
    </EDialog>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import EmcLogoMark from '@/components/brand/EmcLogoMark.vue'
import { EButton, EDialog, ESearchField, ESelect, ETextField, ETextarea } from '@/components/form/base'
import EEmptyState from '@/components/layout/EEmptyState.vue'
import { useAuthStore } from '@/stores/auth'
import { isDevToolsEnvironment } from '@/utils/devEnvironmentBanner'
import {
  addToCart,
  cartTotal,
  completeOnboarding,
  loginAsDepartment,
  logoutViewer,
  offerById,
  offerStats,
  removeFromCart,
  setCartQty,
  submitCart,
  useGaVerkaufMock,
  visibleFor,
  type GaOffer,
  type GaOfferCondition,
  type GaOfferRequest,
} from '@/views/grossanlass/weiterverkauf/gaVerkaufMock'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { cart, viewer, requests, offers, departments } = useGaVerkaufMock()

const CONDITION_COLOR: Record<GaOfferCondition, string> = { good: 'success', used: 'warning', damaged: 'error' }

const search = ref('')
const conditionFilter = ref<GaOfferCondition | null>(null)
const pickupFilter = ref<string | null>(null)
const qty = ref(1)
const loginOpen = ref(false)
const loginDepartment = ref<string | null>(null)
const cartOpen = ref(false)
const submitted = ref<GaOfferRequest[]>([])
type PathId = 'login' | 'onboarding' | 'external'
const pathOptions: PathId[] = ['login', 'onboarding', 'external']
const PATH_ICON: Record<PathId, string> = { login: 'mdi-login', onboarding: 'mdi-rocket-launch-outline', external: 'mdi-email-outline' }
const path = ref<PathId | null>(null)
const form = reactive({ name: '', organisation: '', contact: '', phone: '', note: '', handover: 'pickup' as 'pickup' | 'transport' })

const onboardingOpen = ref(false)
const onboardingStep = ref(0)
const onboardingRequestIds = ref<string[]>([])
const onb = reactive({ department: '', person: '', email: '' })
const onboardingSteps = computed(() => [
  t('grossanlass.verkauf.public.onboarding.steps.details'),
  t('grossanlass.verkauf.public.onboarding.steps.invite'),
  t('grossanlass.verkauf.public.onboarding.steps.linked'),
])
const onbValid = computed(() => onb.department.trim().length > 0 && onb.person.trim().length > 0 && onb.email.includes('@'))

const showAdmin = computed(() => isDevToolsEnvironment())
const detail = computed(() => (route.params.offerId ? offerById(String(route.params.offerId)) : undefined))
const visible = computed(() => {
  void offers.value
  return visibleFor(viewer.value)
})
const locked = computed(() => visible.value.locked)
const conditionItems = computed(() => (['good', 'used', 'damaged'] as const).map((value) => ({ value, title: t(`grossanlass.verkauf.condition.${value}`) })))
const pickupItems = computed(() => [...new Set(offers.value.map((offer) => offer.pickup))])
const filteredOpen = computed(() => {
  const q = search.value.trim().toLowerCase()
  return visible.value.open.filter((offer) =>
    (!q || `${offer.name} ${offer.description}`.toLowerCase().includes(q))
    && (!conditionFilter.value || offer.condition === conditionFilter.value)
    && (!pickupFilter.value || offer.pickup === pickupFilter.value),
  )
})
const total = computed(() => {
  void cart.value
  return cartTotal()
})

function available(offer: GaOffer): number {
  void requests.value
  return offerStats(offer).available
}
function needsLogin(offer: GaOffer): boolean {
  return offer.visibility === 'departments' && viewer.value.kind !== 'department'
}
function offerName(id: string): string {
  return offerById(id)?.name ?? ''
}
function offerPrice(id: string): number {
  return offerById(id)?.priceChf ?? 0
}
function openDetail(id: string) {
  qty.value = 1
  void router.push(`/qr-demo/material/${id}`)
}
function goList() {
  void router.push('/qr-demo/material')
}
function goAdmin() {
  const dept = authStore.activeDepartmentId
  void router.push(dept ? `/${dept}/material/weiterverkauf` : '/login')
}
function add(id: string) {
  if (addToCart(id, qty.value)) cartOpen.value = true
}
function doLogin() {
  if (!loginDepartment.value) return
  loginAsDepartment(loginDepartment.value)
  path.value = 'login'
  loginOpen.value = false
}
function choosePath(option: PathId) {
  path.value = option
  if (option === 'login' && viewer.value.kind !== 'department') loginOpen.value = true
}
const canSend = computed(() => {
  if (!path.value || !form.name.trim() || !form.contact.includes('@')) return false
  if (path.value === 'login') return viewer.value.kind === 'department'
  return form.organisation.trim().length > 0
})
const sendLabel = computed(() =>
  path.value === 'onboarding' ? t('grossanlass.verkauf.public.paths.sendOnboarding') : t('grossanlass.verkauf.public.send'),
)
const needsOnboardingNext = computed(() => submitted.value.some((row) => row.requesterKind === 'onboarding'))
function send() {
  if (!path.value) return
  const kind = path.value === 'login' ? 'department' : path.value === 'onboarding' ? 'onboarding' : 'external'
  const department = viewer.value.kind === 'department' ? viewer.value.department : ''
  submitted.value = submitCart({
    name: form.name.trim(),
    organisation: kind === 'department' ? department : form.organisation.trim(),
    contact: form.contact.trim(),
    phone: kind === 'external' ? form.phone.trim() : '',
    kind,
    handover: form.handover,
    note: form.note.trim(),
  })
  if (kind === 'onboarding') {
    onb.department = form.organisation.trim()
    onb.person = form.name.trim()
    onb.email = form.contact.trim()
  }
}
function closeCart() {
  cartOpen.value = false
  submitted.value = []
  path.value = null
}
function startOnboardingFromSubmitted() {
  onboardingRequestIds.value = submitted.value.filter((row) => row.requesterKind === 'onboarding').map((row) => row.id)
  onboardingStep.value = 1
  cartOpen.value = false
  submitted.value = []
  onboardingOpen.value = true
}
function openOnboardingStandalone() {
  onboardingRequestIds.value = []
  onboardingStep.value = 0
  onboardingOpen.value = true
}
function finishOnboarding() {
  completeOnboarding(onboardingRequestIds.value, onb.department)
  onboardingOpen.value = false
}
</script>

<style scoped>
.shop {
  min-height: 100dvh;
  overflow-y: auto;
  background: #f8fafc;
  color: #0f172a;
}
.shop__header {
  position: sticky;
  top: 0;
  z-index: 5;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px 16px;
  padding: 10px 16px;
  border-bottom: 1px solid #e5e7eb;
  background: #fff;
}
.shop__brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: inherit;
  font-weight: 700;
  text-decoration: none;
}
.shop__brand small {
  margin-left: 4px;
  color: #64748b;
  font-weight: 500;
}
.shop__header-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.shop__main {
  display: flex;
  flex-direction: column;
  gap: 16px;
  max-width: 1100px;
  margin: 0 auto;
  padding: 20px 16px 48px;
}
.shop__hero h1 {
  margin: 0 0 4px;
  font-size: 1.6rem;
}
.shop__hero p {
  margin: 0;
  color: #64748b;
}
.shop__filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 10px;
}
.shop__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 14px;
}
.card {
  display: flex;
  flex-direction: column;
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #fff;
  overflow: hidden;
}
.card--locked {
  opacity: 0.8;
}
.card__img {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 130px;
  border: 0;
  background: #e2e8f0;
  color: #94a3b8;
  cursor: pointer;
}
.card__body {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  padding: 12px 14px 14px;
}
.card__body h3 {
  margin: 0;
  font-size: 1rem;
}
.card__body h3 a {
  color: inherit;
  text-decoration: none;
}
.card__price {
  margin: 0;
  font-weight: 700;
  color: #0f766e;
}
.card__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  margin: 0;
  font-size: 0.84rem;
  color: #475569;
}
.detail {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 24px;
  padding: 18px;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  background: #fff;
}
.detail__img {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 280px;
  border-radius: 12px;
  background: #e2e8f0;
  color: #94a3b8;
}
.detail__thumbs {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}
.thumb {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  border-radius: 8px;
  background: #e2e8f0;
  color: #94a3b8;
}
.detail__info {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.detail__info h1 {
  margin: 0;
  font-size: 1.5rem;
}
.detail__price {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 700;
  color: #0f766e;
}
.detail__price small {
  color: #64748b;
  font-weight: 500;
  font-size: 0.8rem;
}
.detail__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0;
}
.card__meta--phase {
  color: #0369a1;
}
.detail__text {
  margin: 0;
  color: #334155;
}
.detail__meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  color: #475569;
  font-size: 0.9rem;
}
.detail__buy {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 6px;
}
.qty {
  width: 76px;
  min-height: 44px;
  padding: 4px 8px;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  font-size: 1rem;
}
.note {
  margin: 8px 0;
  color: #64748b;
  font-size: 0.86rem;
}
.cart {
  display: grid;
  gap: 8px;
  margin: 0 0 8px;
  padding: 0;
  list-style: none;
}
.cart li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  gap: 8px;
  align-items: center;
}
.cart small {
  display: block;
  color: #64748b;
}
.paths__title {
  margin: 8px 0 6px;
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #475569;
}
.paths {
  display: grid;
  gap: 8px;
}
.path {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  text-align: left;
  cursor: pointer;
}
.path small {
  display: block;
  color: #64748b;
}
.path--active {
  border: 2px solid #059669;
  background: #ecfdf5;
}
.path-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 12px;
}
.onb-steps {
  display: flex;
  gap: 12px;
  margin: 0 0 12px;
  padding: 0;
  list-style: none;
  font-size: 0.8rem;
  color: #94a3b8;
}
.onb-steps li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.onb-steps .dot {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  border: 2px solid currentColor;
  font-size: 0.7rem;
}
.onb-steps .is-current {
  color: #059669;
  font-weight: 700;
}
.onb-steps .is-done {
  color: #0f766e;
}
.cart__total {
  margin: 4px 0 12px;
}
@media (max-width: 720px) {
  .detail {
    grid-template-columns: 1fr;
  }
}
</style>
