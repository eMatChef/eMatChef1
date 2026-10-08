import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import {
  getDepartmentClock,
  resetDepartmentClock,
  setDepartmentClock,
  type DepartmentClock,
} from '@/api/departmentClock'

/**
 * Fachzeit des aktiven Departments (Quelle: Backend-BusinessClock).
 * `now` ist naive Wandzeit (wie alle Grossanlass-Zeiten) und wird ohne Zeitzonen-Umrechnung angezeigt, damit
 * Header und Backend-Vergleiche dieselbe Zeit meinen. Die Uhr läuft lokal ab dem Abruf weiter; `revision` steigt
 * nach jeder Zeitreise, damit Ansichten über ihr normales Laden neu aufgebaut werden.
 */
const pad = (n: number) => String(n).padStart(2, '0')

/** `YYYY-MM-DDTHH:mm:ss` aus den lokalen Feldern (keine Zeitzonen-Umrechnung). */
export function formatWallClock(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

/** Naive Wandzeit des Servers als lokale Felder lesen. */
export function parseWallClock(value: string): Date {
  const [datePart, timePart = '00:00:00'] = value.split('T')
  const [y, mo, d] = datePart.split('-').map(Number)
  const [h, mi, s] = timePart.split(':').map(Number)
  return new Date(y, mo - 1, d, h, mi, s || 0)
}

export const useBusinessClockStore = defineStore('businessClock', () => {
  const departmentId = ref<string | null>(null)
  const clock = ref<DepartmentClock | null>(null)
  const tick = ref(Date.now())
  const fetchedAt = ref(Date.now())
  const revision = ref(0)
  let timer: ReturnType<typeof setInterval> | null = null

  const canTravel = computed(() => clock.value?.can_travel === true)
  const mode = computed(() => clock.value?.mode ?? 'real')
  /** Angezeigte Fachzeit (Wandzeit-Felder als lokale Felder), läuft sichtbar weiter. */
  const displayNow = computed(() => {
    const base = clock.value ? parseWallClock(clock.value.now) : new Date(tick.value)
    return new Date(base.getTime() + (clock.value ? tick.value - fetchedAt.value : 0))
  })

  function startTicking() {
    if (timer) return
    timer = setInterval(() => {
      tick.value = Date.now()
    }, 1000)
  }

  function stopTicking() {
    if (timer) clearInterval(timer)
    timer = null
  }

  function apply(next: DepartmentClock) {
    clock.value = next
    fetchedAt.value = tick.value = Date.now()
  }

  async function load(id: string | null) {
    departmentId.value = id
    if (!id) {
      clock.value = null
      stopTicking()
      return
    }
    try {
      const next = await getDepartmentClock(id)
      if (departmentId.value !== id) return
      apply(next)
      if (next.can_travel) startTicking()
      else stopTicking()
    } catch {
      // Kein Zugriff o. Ä.: keine Uhr anzeigen.
      if (departmentId.value === id) clock.value = null
      stopTicking()
    }
  }

  async function travelTo(target: Date) {
    if (!departmentId.value) return
    apply(await setDepartmentClock(departmentId.value, formatWallClock(target)))
    revision.value++
  }

  async function shift(deltaMs: number) {
    await travelTo(new Date(displayNow.value.getTime() + deltaMs))
  }

  async function reset() {
    if (!departmentId.value) return
    apply(await resetDepartmentClock(departmentId.value))
    revision.value++
  }

  return { clock, canTravel, mode, displayNow, revision, load, travelTo, shift, reset, stopTicking }
})
