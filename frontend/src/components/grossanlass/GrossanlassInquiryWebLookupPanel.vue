<template>
  <div class="web-lookup">
    <div class="web-lookup__bar">
      <EButton
        variant="secondary"
        size="small"
        :disabled="!canSearch"
        :loading="isLookingUp"
        @click="searchWeb"
      >
        {{ t('grossanlass.beschaffung.anfragen.webLookup') }}
      </EButton>
      <a
        v-if="browserSearchUrl"
        class="web-lookup__browser"
        :href="browserSearchUrl"
        target="_blank"
        rel="noopener noreferrer"
      >
        {{ t('grossanlass.beschaffung.anfragen.webLookupOpenSearch') }}
      </a>
      <a
        v-if="phoneSearchUrl"
        class="web-lookup__browser"
        :href="phoneSearchUrl"
        target="_blank"
        rel="noopener noreferrer"
      >
        {{ t('grossanlass.beschaffung.anfragen.webLookupOpenPhoneSearch') }}
      </a>
      <a
        v-if="formTelHref"
        class="web-lookup__browser"
        :href="formTelHref"
      >
        {{ t('grossanlass.beschaffung.anfragen.webLookupCall') }}
      </a>
    </div>
    <p class="web-lookup__hint">{{ t('grossanlass.beschaffung.anfragen.webLookupHint') }}</p>

    <p v-if="error" class="web-lookup__error">{{ error }}</p>

    <div v-if="result && !isLookingUp" class="web-lookup__hits">
      <p v-if="!hasHits" class="web-lookup__empty">
        {{ t('grossanlass.beschaffung.anfragen.webLookupEmpty') }}
      </p>
      <template v-else>
        <EButton
          v-if="canApplyEmpty"
          variant="primary"
          size="x-small"
          class="web-lookup__fill"
          @click="applyEmpty"
        >
          {{ t('grossanlass.beschaffung.anfragen.webLookupApplyEmpty') }}
        </EButton>
        <div v-if="result.website" class="web-lookup__row">
          <span class="web-lookup__label">{{ t('grossanlass.beschaffung.anfragen.websiteLabel') }}</span>
          <span class="web-lookup__value">{{ result.website }}</span>
          <EButton variant="text" size="x-small" @click="applyWebsite(result.website)">
            {{ applied.website === result.website
              ? t('grossanlass.beschaffung.anfragen.webLookupApplied')
              : t('grossanlass.beschaffung.anfragen.webLookupApply') }}
          </EButton>
        </div>
        <div v-for="hit in result.emails" :key="`e-${hit.value}`" class="web-lookup__row">
          <span class="web-lookup__label">{{ t('grossanlass.beschaffung.anfragen.emailLabel') }}</span>
          <span class="web-lookup__value">
            {{ hit.value }}
            <span class="web-lookup__src">{{ hostLabel(hit.source) }}</span>
          </span>
          <EButton variant="text" size="x-small" @click="applyEmail(hit.value)">
            {{ applied.email === hit.value
              ? t('grossanlass.beschaffung.anfragen.webLookupApplied')
              : t('grossanlass.beschaffung.anfragen.webLookupApply') }}
          </EButton>
        </div>
        <div v-for="hit in result.phones" :key="`p-${hit.value}`" class="web-lookup__row">
          <span class="web-lookup__label">{{ t('grossanlass.beschaffung.anfragen.phoneLabel') }}</span>
          <span class="web-lookup__value">
            <a class="web-lookup__tel" :href="telHref(hit.value)">{{ hit.value }}</a>
            <span class="web-lookup__src">{{ hostLabel(hit.source) }}</span>
          </span>
          <EButton variant="text" size="x-small" @click="applyPhone(hit.value)">
            {{ applied.phone === hit.value
              ? t('grossanlass.beschaffung.anfragen.webLookupApplied')
              : t('grossanlass.beschaffung.anfragen.webLookupApply') }}
          </EButton>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { lookupGrossanlassInquiryWeb, type GrossanlassWebLookupResult } from '@/api/grossanlassInquiries'
import EButton from '@/components/form/base/EButton.vue'

type LookupForm = {
  name: string
  place: string
  website: string
  email: string
  phone: string
}

const props = defineProps<{
  departmentId: string
}>()

const form = defineModel<LookupForm>({ required: true })
const { t, locale } = useI18n()

const isLookingUp = ref(false)
const result = ref<GrossanlassWebLookupResult | null>(null)
const error = ref('')
const applied = reactive({ website: '', email: '', phone: '' })

const canSearch = computed(() => props.departmentId !== '' && form.value.name.trim() !== '')
const searchQuery = computed(() => {
  if (result.value?.query) return result.value.query
  const name = form.value.name.trim()
  if (!name) return ''
  return `${name} ${form.value.place.trim()} Kontakt E-Mail`.trim()
})
const browserSearchUrl = computed(() => {
  const query = searchQuery.value
  if (!query) return ''
  return userWebSearchUrl(query, locale.value)
})
const formTelHref = computed(() => telHref(form.value.phone))
const phoneSearchUrl = computed(() => {
  const phone = form.value.phone.trim()
  if (!phone || !telHref(phone)) return ''
  return userWebSearchUrl(phone, locale.value)
})
const hasHits = computed(() => {
  const row = result.value
  if (!row) return false
  return Boolean(row.website) || row.emails.length > 0 || row.phones.length > 0
})
const canApplyEmpty = computed(() => {
  if (!hasHits.value || !result.value) return false
  return (!form.value.website.trim() && Boolean(result.value.website))
    || (!form.value.email.trim() && result.value.emails.length > 0)
    || (!form.value.phone.trim() && result.value.phones.length > 0)
})

function hostLabel(url: string): string {
  try {
    return new URL(url).host.replace(/^www\./, '')
  } catch {
    return url
  }
}

function telHref(phone: string): string {
  const compact = phone.trim().replace(/[^\d+]/g, '')
  const digits = compact.replace(/\D/g, '')
  if (digits.length < 6) return ''
  return `tel:${compact}`
}

function userWebSearchUrl(query: string, localeCode: string): string {
  const german = localeCode.toLowerCase().startsWith('de')
  const host = german ? 'www.google.ch' : 'www.google.com'
  const hl = german ? 'de' : 'en'
  return `https://${host}/search?hl=${hl}&q=${encodeURIComponent(query)}`
}

function applyWebsite(value: string) {
  form.value.website = value
  applied.website = value
}

function applyEmail(value: string) {
  form.value.email = value
  applied.email = value
}

function applyPhone(value: string) {
  form.value.phone = value
  applied.phone = value
}

function applyEmpty() {
  const row = result.value
  if (!row) return
  if (!form.value.website.trim() && row.website) applyWebsite(row.website)
  if (!form.value.email.trim() && row.emails[0]) applyEmail(row.emails[0].value)
  if (!form.value.phone.trim() && row.phones[0]) applyPhone(row.phones[0].value)
}

async function searchWeb() {
  error.value = ''
  result.value = null
  applied.website = ''
  applied.email = ''
  applied.phone = ''
  if (!canSearch.value) {
    error.value = t('grossanlass.beschaffung.anfragen.webLookupNeedName')
    return
  }
  isLookingUp.value = true
  try {
    result.value = await lookupGrossanlassInquiryWeb(props.departmentId, {
      name: form.value.name.trim(),
      place: form.value.place.trim(),
      website: form.value.website.trim(),
    })
  } catch (e) {
    const err = e as { response?: { data?: { error?: string } } }
    error.value = err.response?.data?.error || t('grossanlass.beschaffung.anfragen.webLookupError')
  } finally {
    isLookingUp.value = false
  }
}
</script>

<style scoped>
.web-lookup {
  margin: 4px 0 14px;
  padding: 10px 12px;
  border: 1px dashed #cbd5e1;
  border-radius: 10px;
  background: #f8fafc;
}
.web-lookup__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
}
.web-lookup__browser {
  font-size: 0.8rem;
  color: #1d4ed8;
  text-decoration: none;
}
.web-lookup__browser:hover {
  text-decoration: underline;
}
.web-lookup__hint,
.web-lookup__empty {
  margin: 8px 0 0;
  color: #64748b;
  font-size: 0.78rem;
}
.web-lookup__error {
  margin: 8px 0 0;
  color: #b91c1c;
  font-size: 0.82rem;
}
.web-lookup__hits {
  display: grid;
  gap: 6px;
  margin-top: 10px;
}
.web-lookup__fill {
  justify-self: start;
  margin-bottom: 4px;
}
.web-lookup__row {
  display: grid;
  grid-template-columns: 5.5rem minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
  padding: 4px 0;
  border-top: 1px solid #e2e8f0;
}
.web-lookup__label {
  font-size: 0.72rem;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}
.web-lookup__value {
  min-width: 0;
  font-size: 0.85rem;
  word-break: break-all;
}
.web-lookup__tel {
  color: #1d4ed8;
  text-decoration: none;
  word-break: break-all;
}
.web-lookup__tel:hover {
  text-decoration: underline;
}
.web-lookup__src {
  display: block;
  color: #94a3b8;
  font-size: 0.7rem;
  word-break: break-all;
}
</style>
