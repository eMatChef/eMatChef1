<template>
  <v-navigation-drawer
    v-model="open"
    location="right"
    temporary
    :width="mdAndUp ? 440 : undefined"
    :class="{ 'drawer--full': !mdAndUp }"
    class="helper-drawer"
  >
    <div v-if="helper" class="drawer">
      <header class="drawer__head">
        <div>
          <h3>{{ helper.name }}</h3>
          <p>{{ helper.org }}</p>
        </div>
        <v-btn icon="mdi-close" variant="text" :aria-label="t('common.close')" @click="open = false" />
      </header>

      <v-chip size="small" variant="flat" :color="STATUS_COLOR[status]">{{ t(`grossanlass.helferpool.status.${status}`) }}</v-chip>

      <section>
        <h4>{{ t('grossanlass.helferpool.contact') }}</h4>
        <p><v-icon icon="mdi-email-outline" size="14" /> {{ helper.email }}</p>
        <p><v-icon icon="mdi-phone-outline" size="14" /> {{ helper.phone }}</p>
        <p v-if="helper.note" class="drawer__note">„{{ helper.note }}“</p>
      </section>

      <section>
        <h4>{{ t('grossanlass.helferpool.skills') }}</h4>
        <div class="chips">
          <v-chip v-for="skill in helper.skills" :key="skill" size="small" variant="tonal" color="primary">{{ skill }}</v-chip>
        </div>
        <h4>{{ t('grossanlass.helferpool.licenses') }}</h4>
        <div class="chips">
          <v-chip v-for="license in helper.licenses" :key="license" size="small" variant="outlined" prepend-icon="mdi-card-account-details-outline">{{ license }}</v-chip>
          <span v-if="!helper.licenses.length" class="muted">{{ t('grossanlass.helferpool.noLicense') }}</span>
        </div>
      </section>

      <section>
        <h4>{{ t('grossanlass.helferpool.availability') }}</h4>
        <ul class="list">
          <li v-for="window in helper.windows" :key="window.from.toISOString()">{{ windowLabel(window, locale) }}</li>
        </ul>
      </section>

      <section>
        <h4>{{ t('grossanlass.helferpool.assignments') }}</h4>
        <p v-if="!assignments.length" class="muted">{{ t('grossanlass.helferpool.noAssignments') }}</p>
        <ul class="list">
          <li v-for="entry in assignments" :key="`${entry.source}-${entry.id}`">
            <v-icon :icon="entry.source === 'tour' ? 'mdi-truck-fast-outline' : 'mdi-clipboard-check-outline'" size="14" />
            <span><strong>{{ entry.label }}</strong><small>{{ entry.origin }} · {{ windowLabel({ from: entry.startsAt, to: entry.endsAt }, locale) }}</small></span>
          </li>
        </ul>
      </section>

      <section>
        <h4>{{ t('grossanlass.helferpool.freeWindows') }}</h4>
        <p v-if="!free.length" class="muted">{{ t('grossanlass.helferpool.noFree') }}</p>
        <ul class="list">
          <li v-for="window in free" :key="window.from.toISOString()" class="free">{{ windowLabel(window, locale) }}</li>
        </ul>
      </section>
    </div>
  </v-navigation-drawer>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDisplay } from 'vuetify'
import { assignmentsOf, freeWindows, helperStatus, type GaHelper, type GaTimeWindow } from './gaHelferMock'
import { STATUS_COLOR, windowLabel } from './gaHelferUi'

const props = defineProps<{ modelValue: boolean; helper: GaHelper | null; period: GaTimeWindow }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
const { t, locale } = useI18n()
const { mdAndUp } = useDisplay()

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})
const status = computed(() => (props.helper ? helperStatus(props.helper, props.period) : 'free'))
const assignments = computed(() => (props.helper ? assignmentsOf(props.helper) : []))
const free = computed(() => (props.helper ? freeWindows(props.helper, props.period) : []))
</script>

<style scoped>
.drawer {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
}
.drawer__head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}
.drawer__head h3 {
  margin: 0;
}
.drawer__head p {
  margin: 0;
  color: #64748b;
}
section h4 {
  margin: 0 0 6px;
  font-size: 0.72rem;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: #64748b;
}
section p {
  margin: 0 0 4px;
  font-size: 0.9rem;
}
.drawer__note {
  color: #64748b;
  font-style: italic;
}
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}
.list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 6px;
  font-size: 0.88rem;
}
.list li {
  display: flex;
  gap: 8px;
  align-items: flex-start;
}
.list small {
  display: block;
  color: #64748b;
}
.free {
  color: #15803d;
  font-weight: 600;
}
.muted {
  color: #94a3b8;
  font-size: 0.86rem;
}
</style>
