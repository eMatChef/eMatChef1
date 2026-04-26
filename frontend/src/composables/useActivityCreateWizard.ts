import { computed, nextTick, ref, watchEffect } from 'vue'
import {
  createActivity,
  patchActivity,
  syncActivityItems,
  type ActivityCreatedResponse,
  type ActivityDetail,
  type ActivityItemRow,
} from '@/api/activities'
import type { ActivityDefaults } from '@/api/departmentSettings'
import type { Group, GroupMember } from '@/api/groups'
import { useAuthStore } from '@/stores/auth'
import {
  computeMaterialPlanningFromUsage,
  defaultUsageWindowFromDepartmentDefaults,
} from '@/utils/activityPlanningFromDefaults'
import {
  getPlanningUsageViolation,
  planningUsageViolationMessage,
} from '@/utils/activityPlanningUsageConstraint'

export type ActivityCreateType = 'activity' | 'camp' | 'event' | 'external'

export type ActivityCreateLayoutMode = 'single' | 'stepper'

/** Stabile Keys für fehlende Schritte — Anzeige nur via i18n, nicht vergleichbarer Klartext */
export type ActivityMissingStepKey =
  | 'choose_type'
  | 'enter_name'
  | 'choose_group'
  | 'choose_venue'
  | 'choose_tenant_address'
  | 'complete_date_ranges'
  | 'check_date_range'
  | 'pickup_outside_usage'
  | 'choose_material'

export type InvitedDepartmentStatus = 'pending' | 'accepted' | 'rejected'

/** Auswahl im Wizard — wird als invited_departments gespeichert; Status nach API-Antwort */
export interface InvitedDepartmentDraft {
  id: string
  name: string
  organisation_name: string
  group_id?: string | null
  group_name?: string | null
  status?: InvitedDepartmentStatus
}

export const ACTIVITY_CREATE_STEP_KEYS = ['grunddaten', 'zeitraum', 'material', 'uebersicht'] as const

export type ActivityCreateStepKey = (typeof ACTIVITY_CREATE_STEP_KEYS)[number]

/** Eine Materialzeile im Erstell-Wizard (vor API sync) — optional mit API-Item-Id für die Detailansicht */
export interface ActivityMaterialLine {
  material_item_id: string
  material_name: string
  /** physical | physical_combo | virtual_combo */
  material_type?: string | null
  linked_container_label?: string | null
  quantity: number
  /** availableForPeriod bei Auswahl — für Mengen-Buttons in der Liste */
  period_availability_cap?: number
  pack_size?: number | null
  pack_unit?: string | null
  /** Nur Entwurfs-Detail: Zeile aus GET …/items */
  activity_item_id?: string | null
  /** Gespeicherte Menge (API) — für Verfügbarkeit, wenn Entwurf von DB abweicht */
  saved_quantity?: number
  source_department_name?: string | null
  line_total?: string | number | null
  is_js_material?: boolean
  /** Wie GET …/items — bei Kiste aus Packliste oft serialized trotz bulk in Stammdaten */
  tracking_type?: 'serialized' | 'bulk' | null
  /** Wie GET …/items */
  is_container?: boolean
}

function pickDefaultGroupForLeader(groups: Group[], userId: string | null): string | null {
  if (!userId || !groups.length) return null
  type Row = { g: Group; mem: GroupMember }
  const rows: Row[] = []
  for (const g of groups) {
    const mem = g.members?.find((m) => m.user_id === userId && m.is_leader)
    if (mem) rows.push({ g, mem })
  }
  if (rows.length === 0) return null
  const primary = rows.find(({ mem }) => mem.is_primary)
  return (primary ?? rows[0]).g.id
}

const STATIC_STEP_TITLES: Record<'zeitraum' | 'material' | 'uebersicht', string> = {
  zeitraum: 'Zeitraum',
  material: 'Material',
  uebersicht: 'Übersicht',
}

/** Kurz-Titel in der Zeile „Schritt x von y: …“ (nicht der lange Formular-Titel in Schritt 1). */
const STEP_PROGRESS_LABELS: Record<ActivityCreateStepKey, string> = {
  grunddaten: 'Stammdaten',
  zeitraum: 'Zeitraum',
  material: 'Material',
  uebersicht: 'Übersicht',
}

/** Erster Schritt im Stepper: Bezeichnung je nach Aktivitätstyp */
export function grunddatenStepTitle(t: ActivityCreateType | null): string {
  switch (t) {
    case 'camp':
      return 'Name des Lagers'
    case 'event':
      return 'Name des Events'
    case 'external':
      return 'Name der externen Nutzung'
    case 'activity':
      return 'Name der Aktivität'
    default:
      return 'Name der Aktivität'
  }
}

export function useActivityCreateWizard() {
  const selectedActivityType = ref<ActivityCreateType | null>(null)
  const wizardStepIndex = ref(0)

  const formName = ref('')

  /** Aktivität: Nutzung (wie v4.01 usage_*) */
  const usageStartAt = ref<Date | null>(null)
  const usageEndAt = ref<Date | null>(null)
  /** Material: Abholung / Rückgabe (planning_*) */
  const planningStartAt = ref<Date | null>(null)
  const planningEndAt = ref<Date | null>(null)

  const activityDefaults = ref<ActivityDefaults | null>(null)
  /** Wenn true, ändern sich Materialzeiten mit bei Nutzungsänderung (Abteilungs-Standard) */
  const planningSynced = ref(true)

  const groupsForWizard = ref<Group[]>([])
  const selectedGroupId = ref<string | null>(null)
  /** Bei Typ „extern“: Adresse des Mieters / Anbieters (Department-Adressen) */
  const customerAddressId = ref<string | null>(null)
  /** Lager, Event, extern: Eventstandort (Department-Adressen) */
  const venueAddressId = ref<string | null>(null)

  /** Gewählte Materialpositionen (nach Anlage per PUT /activities/:id/items) */
  const materialLines = ref<ActivityMaterialLine[]>([])

  /** Server-Entwurf für Stepper (Lager/Event/extern): nach „Weiter“ wie v4.01-Zwischenstand */
  const draftActivityId = ref<string | null>(null)

  /** Lager/Event: weitere Departments einladen (erscheint bei Gast-Departments in der Glocke) */
  const invitedDepartments = ref<InvitedDepartmentDraft[]>([])

  /** Optionale Notizen (Stepper: Übersicht; wird mit Payload gesendet) */
  const activityNotes = ref('')

  /** true nach hydrateFromActivityDetail — verhindert, dass watchEffect die Zeiten aus Abteilungs-Defaults überschreibt */
  const skipSeedPlanningFromDefaults = ref(false)

  const layoutMode = computed<ActivityCreateLayoutMode>(() => {
    if (!selectedActivityType.value) return 'single'
    return selectedActivityType.value === 'activity' ? 'single' : 'stepper'
  })

  const stepKeys = computed(() => [...ACTIVITY_CREATE_STEP_KEYS])

  const currentStepKey = computed<ActivityCreateStepKey | null>(() => {
    if (!selectedActivityType.value || layoutMode.value === 'single') return null
    return stepKeys.value[wizardStepIndex.value] ?? 'grunddaten'
  })

  const stepTitles = computed<Record<ActivityCreateStepKey, string>>(() => ({
    grunddaten: grunddatenStepTitle(selectedActivityType.value),
    ...STATIC_STEP_TITLES,
  }))

  /** Zeile oben im Stepper: „Schritt n von m: …“ */
  const currentStepProgressLabel = computed(() => {
    const k = currentStepKey.value
    if (!k) return ''
    return STEP_PROGRESS_LABELS[k]
  })

  const invalidUsageOrder = computed(() => {
    if (!usageStartAt.value || !usageEndAt.value) return false
    return usageEndAt.value.getTime() < usageStartAt.value.getTime()
  })

  const invalidPlanningOrder = computed(() => {
    if (!planningStartAt.value || !planningEndAt.value) return false
    return planningEndAt.value.getTime() < planningStartAt.value.getTime()
  })

  const invalidDateRange = computed(() => invalidUsageOrder.value || invalidPlanningOrder.value)

  const planningUsageInvalid = computed(() => {
    if (!usageStartAt.value || !usageEndAt.value || !planningStartAt.value || !planningEndAt.value) return false
    if (invalidUsageOrder.value) return false
    const v = getPlanningUsageViolation(
      planningStartAt.value,
      planningEndAt.value,
      usageStartAt.value,
      usageEndAt.value,
    )
    return v.pickup || v.return
  })

  const planningUsageInvalidHint = computed(() => {
    if (!usageStartAt.value || !usageEndAt.value || !planningStartAt.value || !planningEndAt.value) return null
    if (invalidUsageOrder.value) return null
    return planningUsageViolationMessage(
      getPlanningUsageViolation(
        planningStartAt.value,
        planningEndAt.value,
        usageStartAt.value,
        usageEndAt.value,
      ),
    )
  })

  /**
   * Gruppe Pflicht: interne Aktivität + Lager (wenn Gruppen existieren).
   * Event: optional (v4.01) — hier kein Pflicht-Flag.
   */
  const needsGroupRequired = computed(
    () =>
      !!selectedActivityType.value &&
      (selectedActivityType.value === 'activity' || selectedActivityType.value === 'camp') &&
      groupsForWizard.value.length > 0,
  )

  const needsVenue = computed(
    () =>
      !!selectedActivityType.value &&
      (selectedActivityType.value === 'camp' ||
        selectedActivityType.value === 'event' ||
        selectedActivityType.value === 'external'),
  )

  const needsCustomerAddress = computed(() => selectedActivityType.value === 'external')

  const datesComplete = computed(
    () =>
      !!usageStartAt.value &&
      !!usageEndAt.value &&
      !!planningStartAt.value &&
      !!planningEndAt.value,
  )

  const missingSteps = computed((): ActivityMissingStepKey[] => {
    const missing: ActivityMissingStepKey[] = []
    if (!selectedActivityType.value) {
      missing.push('choose_type')
      return missing
    }
    if (!formName.value.trim()) missing.push('enter_name')
    if (needsGroupRequired.value && !selectedGroupId.value) missing.push('choose_group')
    if (needsVenue.value && !venueAddressId.value) missing.push('choose_venue')
    if (needsCustomerAddress.value && !customerAddressId.value) missing.push('choose_tenant_address')
    if (!datesComplete.value) missing.push('complete_date_ranges')
    if (invalidDateRange.value) missing.push('check_date_range')
    if (planningUsageInvalid.value) missing.push('pickup_outside_usage')
    if (materialLines.value.length === 0) missing.push('choose_material')
    return missing
  })

  const canAdvanceFromCurrentStep = computed(() => {
    const key = stepKeys.value[wizardStepIndex.value]
    if (key === 'grunddaten') {
      if (!formName.value.trim()) return false
      if (needsGroupRequired.value && !selectedGroupId.value) return false
      if (needsVenue.value && !venueAddressId.value) return false
      if (needsCustomerAddress.value && !customerAddressId.value) return false
      return true
    }
    if (key === 'zeitraum') {
      if (!datesComplete.value) return false
      if (invalidDateRange.value) return false
      if (planningUsageInvalid.value) return false
      return true
    }
    if (key === 'material') {
      return materialLines.value.length > 0
    }
    return true
  })

  const isLastStep = computed(() => wizardStepIndex.value >= stepKeys.value.length - 1)

  const canSubmit = computed(() => {
    if (missingSteps.value.length > 0) return false
    if (layoutMode.value === 'stepper' && !isLastStep.value) return false
    return true
  })

  function setActivityDefaults(defaults: ActivityDefaults) {
    activityDefaults.value = defaults
  }

  function setWizardGroups(groups: Group[]) {
    groupsForWizard.value = groups
    const auth = useAuthStore()
    const uid = auth.userId ?? null
    const t = selectedActivityType.value
    if (
      (t === 'activity' || t === 'camp' || t === 'event') &&
      selectedGroupId.value === null
    ) {
      selectedGroupId.value = pickDefaultGroupForLeader(groups, uid)
    }
  }

  function seedUsageAndPlanning() {
    const def = activityDefaults.value
    const typ = selectedActivityType.value
    if (!def || !typ) return
    const { usageStart, usageEnd } = defaultUsageWindowFromDepartmentDefaults(def)
    usageStartAt.value = usageStart
    usageEndAt.value = usageEnd
    const { planningStart, planningEnd } = computeMaterialPlanningFromUsage(usageStart, usageEnd, def, typ)
    planningStartAt.value = planningStart
    planningEndAt.value = planningEnd
    planningSynced.value = true
  }

  /**
   * Immer wenn Abteilungs-Defaults UND Aktivitätstyp gesetzt sind: Nutzung + Planung aus Fixzeiten setzen.
   * Deckt die Reihenfolge „Typ zuerst, API später“ und umgekehrt ab (ohne Race mit leeren Zeiten).
   */
  watchEffect(() => {
    if (skipSeedPlanningFromDefaults.value) return
    const def = activityDefaults.value
    const typ = selectedActivityType.value
    if (!def || !typ) return
    seedUsageAndPlanning()
  })

  /** Bei jeder Änderung der Nutzung: Materialzeiten wie „Vorbelegung aus Nutzung neu berechnen“. */
  function notifyUsageChanged() {
    resyncPlanningFromUsage()
  }

  function notifyPlanningTouched() {
    planningSynced.value = false
  }

  function resyncPlanningFromUsage() {
    const def = activityDefaults.value
    const typ = selectedActivityType.value
    if (!def || !typ || !usageStartAt.value || !usageEndAt.value) return
    const { planningStart, planningEnd } = computeMaterialPlanningFromUsage(
      usageStartAt.value,
      usageEndAt.value,
      def,
      typ,
    )
    planningStartAt.value = planningStart
    planningEndAt.value = planningEnd
    planningSynced.value = true
  }

  /**
   * Typ wählen oder wechseln. Erneuter Klick auf denselben Typ ändert nichts (wie Material-Wizard).
   * Wechsel auf einen anderen Typ: gesamtes Formular zurücksetzen (Name leer, Zeiten aus Abteilungs-Defaults).
   * @returns true, wenn der Typ gewechselt wurde (für Scroll/Fehler zurücksetzen im UI)
   */
  function selectActivityType(t: ActivityCreateType): boolean {
    const previous = selectedActivityType.value
    if (previous === t) {
      return false
    }
    skipSeedPlanningFromDefaults.value = false
    selectedActivityType.value = t
    wizardStepIndex.value = 0
    if (previous !== null) {
      formName.value = ''
    }
    const auth = useAuthStore()
    if (t === 'activity' || t === 'camp' || t === 'event') {
      selectedGroupId.value = pickDefaultGroupForLeader(groupsForWizard.value, auth.userId ?? null)
    } else {
      selectedGroupId.value = null
    }
    customerAddressId.value = null
    venueAddressId.value = null
    materialLines.value = []
    draftActivityId.value = null
    invitedDepartments.value = []
    activityNotes.value = ''
    return previous !== null
  }

  function resetWizard() {
    skipSeedPlanningFromDefaults.value = false
    selectedActivityType.value = null
    wizardStepIndex.value = 0
    formName.value = ''
    usageStartAt.value = null
    usageEndAt.value = null
    planningStartAt.value = null
    planningEndAt.value = null
    activityDefaults.value = null
    planningSynced.value = true
    groupsForWizard.value = []
    selectedGroupId.value = null
    customerAddressId.value = null
    venueAddressId.value = null
    materialLines.value = []
    draftActivityId.value = null
    invitedDepartments.value = []
    activityNotes.value = ''
  }

  /**
   * Speichert den aktuellen Stand als Entwurf auf dem Server (POST oder PATCH), optional Material-Sync.
   * Nur für Stepper-Typen (camp, event, extern).
   */
  function applyInvitedDepartmentsApiResponse(data: ActivityCreatedResponse | undefined | null) {
    const rows = data?.invited_departments
    if (!Array.isArray(rows)) return
    const next: InvitedDepartmentDraft[] = []
    for (const r of rows) {
      if (!r || typeof r.id !== 'string' || !r.id.trim()) continue
      const st = typeof r.status === 'string' ? r.status : 'pending'
      const status: InvitedDepartmentStatus =
        st === 'accepted' || st === 'rejected' ? st : 'pending'
      next.push({
        id: r.id,
        name: typeof r.name === 'string' && r.name.trim() ? r.name.trim() : r.id,
        organisation_name: typeof r.organisation_name === 'string' ? r.organisation_name : '',
        group_id: r.group_id ?? null,
        group_name: r.group_name ?? null,
        status,
      })
    }
    invitedDepartments.value = next
  }

  async function saveDraftStep(departmentId: string): Promise<{ ok: true } | { ok: false; message: string }> {
    if (layoutMode.value !== 'stepper') return { ok: true }
    const full = buildCreatePayload(departmentId, { wizardCreateCompleted: false })
    const { department_id: _d, ...patchBody } = full
    try {
      if (!draftActivityId.value) {
        const created = await createActivity(full)
        const id = created?.id ? String(created.id) : ''
        if (!id) return { ok: false, message: 'Keine Aktivitäts-ID von der API.' }
        draftActivityId.value = id
        applyInvitedDepartmentsApiResponse(created)
      } else {
        const updated = await patchActivity(draftActivityId.value, patchBody)
        applyInvitedDepartmentsApiResponse(updated)
      }
      const id = draftActivityId.value
      if (id && materialLines.value.length > 0) {
        await syncActivityItems(id, {
          items: materialLines.value.map((l) => ({
            material_item_id: l.material_item_id,
            quantity: l.quantity,
            priority: 'normal' as const,
          })),
        })
      }
      return { ok: true }
    } catch (e: unknown) {
      const err = e as { response?: { data?: { error?: string } }; message?: string }
      const msg = err?.response?.data?.error || err?.message || 'Speichern fehlgeschlagen.'
      return { ok: false, message: msg }
    }
  }

  function attemptNext(): boolean {
    if (layoutMode.value !== 'stepper') return false
    if (!canAdvanceFromCurrentStep.value) return false
    if (wizardStepIndex.value < stepKeys.value.length - 1) {
      wizardStepIndex.value += 1
      return true
    }
    return false
  }

  function prevStep() {
    if (wizardStepIndex.value > 0) wizardStepIndex.value -= 1
  }

  function jumpToMissingStep(key: ActivityMissingStepKey) {
    if (key === 'enter_name') {
      if (layoutMode.value === 'stepper') wizardStepIndex.value = 0
    }
    if (key === 'choose_group' || key === 'choose_venue' || key === 'choose_tenant_address') {
      if (layoutMode.value === 'stepper') wizardStepIndex.value = 0
    }
    if (key === 'complete_date_ranges' || key === 'check_date_range' || key === 'pickup_outside_usage') {
      if (layoutMode.value === 'stepper') wizardStepIndex.value = 1
    }
    if (key === 'choose_material') {
      if (layoutMode.value === 'stepper') wizardStepIndex.value = 2
    }
  }

  function buildCreatePayload(
    departmentId: string,
    opts?: { wizardCreateCompleted?: boolean },
  ) {
    const name = formName.value.trim()
    const payload: {
      department_id: string
      name: string
      type: ActivityCreateType
      status: string
      group_id?: string
      address_id?: string
      venue_address_id?: string
      usage_start?: string
      usage_end?: string
      planning_start?: string
      planning_end?: string
      invited_departments?: { id: string; name: string; organisation_name: string; group_id?: string }[]
      notes?: string
      create_wizard_completed?: boolean
    } = {
      department_id: departmentId,
      name,
      type: selectedActivityType.value!,
      /** Immer Entwurf: Material ist nur bei status=draft per API/Detail bearbeitbar; Einreichen erfolgt in der Detailansicht. */
      status: 'draft',
    }
    if (
      (selectedActivityType.value === 'activity' ||
        selectedActivityType.value === 'camp' ||
        selectedActivityType.value === 'event') &&
      selectedGroupId.value
    ) {
      payload.group_id = selectedGroupId.value
    }
    // Vollständige ISO-8601-Zeitstempel in UTC (z. B. 2026-04-04T12:15:00.000Z)
    if (usageStartAt.value) payload.usage_start = usageStartAt.value.toISOString()
    if (usageEndAt.value) payload.usage_end = usageEndAt.value.toISOString()
    if (planningStartAt.value) payload.planning_start = planningStartAt.value.toISOString()
    if (planningEndAt.value) payload.planning_end = planningEndAt.value.toISOString()
    if (selectedActivityType.value === 'external' && customerAddressId.value) {
      payload.address_id = customerAddressId.value
    }
    if (
      (selectedActivityType.value === 'camp' ||
        selectedActivityType.value === 'event' ||
        selectedActivityType.value === 'external') &&
      venueAddressId.value
    ) {
      payload.venue_address_id = venueAddressId.value
    }
    if (selectedActivityType.value === 'camp' || selectedActivityType.value === 'event') {
      const rows = invitedDepartments.value
        .filter((d) => d.id !== departmentId)
        .map((d) => {
          const row: { id: string; name: string; organisation_name: string; group_id?: string } = {
            id: d.id,
            name: d.name.trim() || d.id,
            organisation_name: (d.organisation_name || '').trim(),
          }
          const gid = d.group_id?.trim()
          if (gid) row.group_id = gid
          return row
        })
      payload.invited_departments = rows
    }
    payload.notes = activityNotes.value.trim()
    if (opts?.wizardCreateCompleted !== undefined) {
      payload.create_wizard_completed = opts.wizardCreateCompleted
    }
    return payload
  }

  /** Nach Wizard-Ende: direkt einreichen (POST draft → sync items → PATCH status), nicht bei Lager/Event-Entwurf; extern nur als MW. */
  function shouldAutoSubmitAfterWizard(): boolean {
    const t = selectedActivityType.value
    if (!t) return false
    if (t === 'activity') return true
    if (t === 'external') {
      const role = useAuthStore().currentDepartmentRole
      return role === 'mw'
    }
    return false
  }

  function parseIsoToDate(iso: string | null | undefined): Date | null {
    if (!iso) return null
    const d = new Date(iso)
    return Number.isNaN(d.getTime()) ? null : d
  }

  /**
   * Server-Entwurf in den Wizard laden (Fortsetzen nach Redirect von der Detail-Route).
   */
  async function hydrateFromActivityDetail(detail: ActivityDetail, items: ActivityItemRow[]): Promise<void> {
    skipSeedPlanningFromDefaults.value = true
    const t = detail.type as ActivityCreateType
    selectedActivityType.value = t
    wizardStepIndex.value = 0
    formName.value = detail.name || ''
    usageStartAt.value = parseIsoToDate(detail.usage_start ?? undefined)
    usageEndAt.value = parseIsoToDate(detail.usage_end ?? undefined)
    planningStartAt.value = parseIsoToDate(detail.planning_start ?? undefined)
    planningEndAt.value = parseIsoToDate(detail.planning_end ?? undefined)
    selectedGroupId.value = detail.group_id ?? null
    customerAddressId.value = detail.address_id ?? null
    venueAddressId.value = detail.venue_address_id ?? null
    activityNotes.value = (detail.notes ?? '').trim()
    draftActivityId.value = detail.id
    applyInvitedDepartmentsApiResponse(detail)
    materialLines.value = items.map((i) => ({
      material_item_id: i.material_item_id,
      material_name: i.material_name,
      quantity: i.quantity,
      pack_size: i.pack_size ?? null,
      pack_unit: i.pack_unit ?? null,
      tracking_type: i.tracking_type ?? null,
      is_container: !!i.is_container,
    }))
    planningSynced.value = true
    await nextTick()
    const first = missingSteps.value[0]
    if (first) {
      jumpToMissingStep(first)
    } else {
      wizardStepIndex.value = Math.max(0, stepKeys.value.length - 1)
    }
  }

  return {
    selectedActivityType,
    groupsForWizard,
    selectedGroupId,
    customerAddressId,
    venueAddressId,
    materialLines,
    invitedDepartments,
    activityNotes,
    setWizardGroups,
    layoutMode,
    wizardStepIndex,
    stepKeys,
    currentStepKey,
    currentStepProgressLabel,
    formName,
    usageStartAt,
    usageEndAt,
    planningStartAt,
    planningEndAt,
    activityDefaults,
    planningSynced,
    missingSteps,
    canSubmit,
    canAdvanceFromCurrentStep,
    isLastStep,
    setActivityDefaults,
    seedUsageAndPlanning,
    notifyUsageChanged,
    notifyPlanningTouched,
    resyncPlanningFromUsage,
    selectActivityType,
    resetWizard,
    attemptNext,
    prevStep,
    jumpToMissingStep,
    buildCreatePayload,
    stepTitles,
    draftActivityId,
    saveDraftStep,
    applyInvitedDepartmentsApiResponse,
    hydrateFromActivityDetail,
    shouldAutoSubmitAfterWizard,
  }
}
