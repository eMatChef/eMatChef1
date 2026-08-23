<template>
  <div class="ga-preview-page">
    <GrossanlassPreviewBanner />
    <p class="ga-preview-intro">{{ t('grossanlass.chain.cardsIntro') }}</p>

    <div class="toolbar">
      <EButton variant="primary" size="small" :disabled="!unprinted.length" @click="printAll">
        {{ t('grossanlass.chain.printAllCards', { count: unprinted.length }) }}
      </EButton>
    </div>

    <div class="layout">
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ t('grossanlass.chain.colPerson') }}</th>
            <th>{{ t('grossanlass.chain.colRessort') }}</th>
            <th>{{ t('grossanlass.chain.colCard') }}</th>
            <th>{{ t('grossanlass.chain.colDrive') }}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="card in cards"
            :key="card.id"
            :class="{ 'is-active': previewId === card.id }"
            @click="previewId = card.id"
          >
            <td>
              <strong>{{ card.name }}</strong>
              <span class="meta">{{ card.role }} · {{ card.code }}</span>
            </td>
            <td>{{ card.ressort }}</td>
            <td>
              <span class="chip" :class="{ ok: card.printed }">
                {{ card.printed ? t('grossanlass.chain.cardPrinted') : t('grossanlass.chain.cardMissing') }}
              </span>
            </td>
            <td>
              <label class="drive">
                <input
                  type="checkbox"
                  :checked="card.mayDrive"
                  @change="setUserMayDrive(card.id, ($event.target as HTMLInputElement).checked)"
                  @click.stop
                >
                {{ t('grossanlass.chain.mayDrive') }}
              </label>
            </td>
            <td>
              <EButton variant="secondary" size="small" :disabled="card.printed" @click.stop="printOne(card.id)">
                {{ t('grossanlass.chain.printCard') }}
              </EButton>
            </td>
          </tr>
        </tbody>
      </table>

      <aside v-if="preview" class="badge" aria-label="Badge preview">
        <p class="badge__kicker">PFF 2027 · eMatChef</p>
        <p class="badge__name">{{ preview.name }}</p>
        <p class="badge__ressort">{{ preview.ressort }} · {{ preview.role }}</p>
        <div class="badge__qr" aria-hidden="true">
          <span v-for="n in 36" :key="n" class="dot" :class="{ on: n % 3 !== 0 }" />
        </div>
        <p class="badge__code">{{ preview.code }}</p>
        <p v-if="preview.mayDrive" class="badge__drive">{{ t('grossanlass.chain.mayDriveBadge') }}</p>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import GrossanlassPreviewBanner from '@/components/grossanlass/GrossanlassPreviewBanner.vue'
import { EButton } from '@/components/form/base'
import {
  listUserCards,
  printUnprintedUserCards,
  printUserCard,
  setUserMayDrive,
} from '@/views/grossanlass/grossanlassChainPreviewStore'

const { t } = useI18n()
const toast = useToast()
const cards = computed(() => listUserCards())
const previewId = ref(cards.value[0]?.id ?? '')
const preview = computed(() => cards.value.find((row) => row.id === previewId.value) ?? cards.value[0] ?? null)
const unprinted = computed(() => cards.value.filter((row) => !row.printed))

function printOne(id: string) {
  printUserCard(id)
  previewId.value = id
  toast.success(t('grossanlass.chain.cardPrintedToast'))
}

function printAll() {
  printUnprintedUserCards()
  toast.success(t('grossanlass.chain.cardPrintedToast'))
}
</script>

<style scoped>
.ga-preview-page { padding: 8px 0 24px; }
.ga-preview-intro { margin: 0 0 14px; color: #64748b; font-size: 0.9rem; max-width: 640px; }
.toolbar { margin-bottom: 12px; }
.layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 220px;
  gap: 16px;
  align-items: start;
}
.data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
.data-table th, .data-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; text-align: left; vertical-align: top; }
.data-table th { background: #f8fafc; }
.data-table tr { cursor: pointer; }
.data-table tr.is-active { background: #ecfdf3; }
.meta { display: block; color: #64748b; font-size: 0.75rem; margin-top: 2px; }
.chip { font-size: 0.72rem; font-weight: 700; padding: 1px 8px; border-radius: 999px; background: #ffedd5; color: #c2410c; }
.chip.ok { background: #dcfce7; color: #166534; }
.drive { display: inline-flex; gap: 6px; align-items: center; font-size: 0.8rem; }
.badge {
  border: 1px solid #166534;
  border-radius: 14px;
  padding: 16px 14px;
  background: linear-gradient(180deg, #ecfdf3 0%, #fff 55%);
  text-align: center;
}
.badge__kicker { margin: 0; font-size: 0.68rem; letter-spacing: 0.08em; text-transform: uppercase; color: #166534; font-weight: 700; }
.badge__name { margin: 8px 0 0; font-size: 1.15rem; font-weight: 800; }
.badge__ressort { margin: 2px 0 12px; color: #64748b; font-size: 0.8rem; }
.badge__qr {
  width: 96px;
  height: 96px;
  margin: 0 auto 8px;
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 3px;
  background: #fff;
  border: 1px solid #e5e7eb;
  padding: 8px;
}
.dot { background: #e5e7eb; }
.dot.on { background: #0f172a; }
.badge__code { margin: 0; font-family: ui-monospace, monospace; font-size: 0.72rem; color: #334155; }
.badge__drive { margin: 8px 0 0; font-size: 0.72rem; font-weight: 700; color: #166534; }
@media (max-width: 860px) {
  .layout { grid-template-columns: 1fr; }
}
</style>
