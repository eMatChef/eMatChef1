<template>
  <section class="helper-dir">
    <div class="helper-dir__bar">
      <div class="helper-dir__search">
        <ESearchField v-model="query" :label="t('grossanlass.einstellungen.helperDir.search')" />
      </div>
      <v-btn-toggle v-model="kind" mandatory density="comfortable" variant="outlined" divided color="primary" class="helper-dir__filter">
        <v-btn value="all" size="small">{{ t('grossanlass.einstellungen.helperDir.filterAll') }}</v-btn>
        <v-btn value="account" size="small">{{ t('grossanlass.einstellungen.helperDir.filterAccount') }}</v-btn>
        <v-btn value="external" size="small">{{ t('grossanlass.einstellungen.helperDir.filterExternal') }}</v-btn>
      </v-btn-toggle>
      <v-menu>
        <template #activator="{ props: menuProps }">
          <EButton v-bind="menuProps" variant="primary" data-testid="helper-dir-add">
            <v-icon icon="mdi-account-plus" start :size="20" />
            {{ t('grossanlass.einstellungen.helperDir.addHelper') }}
          </EButton>
        </template>
        <v-list density="compact">
          <v-list-item prepend-icon="mdi-account-outline" :title="t('grossanlass.einstellungen.helperDir.addExternal')" data-testid="helper-dir-add-external" @click="openCreate" />
          <v-list-item prepend-icon="mdi-account-key-outline" :title="t('grossanlass.einstellungen.helperDir.addAccount')" @click="emit('add-account')" />
        </v-list>
      </v-menu>
    </div>

    <p v-if="error" class="helper-dir__error" role="alert">{{ error }}</p>
    <p v-else class="helper-dir__count" aria-live="polite">
      {{ t('grossanlass.einstellungen.helperDir.count', { shown: shown.length, total: helpers.length }) }}
    </p>

    <ul v-if="shown.length" class="helper-dir__list">
      <li v-for="helper in shown" :key="helper.id" class="helper-dir__row" data-testid="helper-dir-row">
        <div class="helper-dir__main">
          <strong>{{ fullName(helper) || helper.email }}</strong>
          <span v-if="helper.email" class="helper-dir__muted">{{ helper.email }}</span>
          <span v-if="helper.phone" class="helper-dir__muted">{{ helper.phone }}</span>
        </div>
        <div v-if="helper.skills?.length || helper.drive_license?.classes.length" class="helper-dir__chips" data-testid="helper-dir-profile">
          <v-chip v-for="skill in helper.skills ?? []" :key="skill" size="x-small" variant="tonal">{{ skill }}</v-chip>
          <v-chip v-for="code in helper.drive_license?.classes ?? []" :key="code" size="x-small" variant="outlined" prepend-icon="mdi-card-account-details-outline">{{ t(`grossanlass.chain.drive.classes.${code}`) }}</v-chip>
        </div>
        <v-chip size="x-small" variant="tonal" :color="helper.has_login ? 'primary' : 'grey'">
          {{ helper.has_login ? t('grossanlass.einstellungen.helperDir.withAccount') : t('grossanlass.einstellungen.helperDir.withoutAccount') }}
        </v-chip>
        <EButton size="small" variant="secondary" @click="openDrawer(helper.id)">{{ t('grossanlass.einstellungen.helperDir.details') }}</EButton>
      </li>
    </ul>
    <p v-else-if="!loading" class="helper-dir__muted">{{ t('grossanlass.einstellungen.helperDir.empty') }}</p>

    <GrossanlassHelferDrawer v-model="drawerOpen" :department-id="departmentId" :helper-id="drawerHelperId" :schedule="null" :version="drawerVersion" @edit="openEdit" @changed="load" />
    <GrossanlassHelferFormDialog v-model="formOpen" :department-id="departmentId" :helper="formHelper" @saved="onSaved">
      <template v-if="!formHelper" #hint>{{ t('grossanlass.einstellungen.helperDir.externalHint') }}</template>
    </GrossanlassHelferFormDialog>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { listGaHelpers, type GaHelper } from '@/api/grossanlassStaffing'
import { EButton, ESearchField } from '@/components/form/base'
import { apiErrorMessage } from '@/utils/apiErrorMessage'
import GrossanlassHelferDrawer from './GrossanlassHelferDrawer.vue'
import GrossanlassHelferFormDialog from './GrossanlassHelferFormDialog.vue'
import { fullName, helperSearchText } from './gaHelferUi'

/**
 * Helfer-Unterreiter der GA-Benutzerverwaltung: alle aktiven Helfer aus `department_grossanlass_helper`
 * (mit und ohne Konto). Details und Bearbeitung nutzen dieselben Komponenten wie der Helferpool.
 */
const props = defineProps<{ departmentId: string }>()
const emit = defineEmits<{ 'add-account': [] }>()
const { t } = useI18n()

const helpers = ref<GaHelper[]>([])
const loading = ref(false)
const error = ref('')
const query = ref('')
const kind = ref<'all' | 'account' | 'external'>('all')
const drawerOpen = ref(false)
const drawerHelperId = ref<string | null>(null)
const formOpen = ref(false)
const formHelper = ref<GaHelper | null>(null)

const shown = computed(() => {
  const q = (query.value ?? '').trim().toLowerCase()
  return helpers.value.filter((h) => {
    if (kind.value === 'account' && !h.has_login) return false
    if (kind.value === 'external' && h.has_login) return false
    return q === '' || helperSearchText(h).includes(q)
  })
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    helpers.value = await listGaHelpers(props.departmentId, { status: 'active', limit: 500 })
  } catch (err) {
    error.value = apiErrorMessage(err, t('grossanlass.einstellungen.helperDir.loadError'))
  } finally {
    loading.value = false
  }
}

function openDrawer(id: string) {
  drawerHelperId.value = id
  drawerOpen.value = true
}
function openCreate() {
  formHelper.value = null
  formOpen.value = true
}
function openEdit(helper: GaHelper) {
  formHelper.value = helper
  formOpen.value = true
}
const drawerVersion = ref(0)
async function onSaved() {
  drawerVersion.value += 1 // offenes Detail zeigt die gespeicherten Werte
  await load()
}

onMounted(load)
defineExpose({ reload: load })
</script>

<style scoped>
.helper-dir__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  max-width: 40%;
}
.helper-dir__bar {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
}
.helper-dir__search {
  flex: 1 1 220px;
  min-width: 0;
}
.helper-dir__count,
.helper-dir__muted {
  color: #64748b;
  font-size: 0.86rem;
}
.helper-dir__error {
  color: #b91c1c;
}
.helper-dir__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 6px;
}
.helper-dir__row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}
.helper-dir__main {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 2px 12px;
  align-items: baseline;
}
</style>
