/**
 * Hilfsrechnung für Vermietpreise aus Anschaffungskosten und erwarteter Nutzung (vereinfachte Amortisation).
 * Keine Steuer-/Fi-Logik – nur Break-even über die angegebene Gesamtnutzung.
 */

export interface BatchLike {
  qty?: number
  unit_price?: string | null
  status?: string | null
}

/**
 * Summe (Menge × Stückpreis) über aktive Chargen mit numerischem Stückpreis.
 */
export function sumAcquisitionBasisFromBatches(batches: BatchLike[] | undefined | null): number | null {
  if (!Array.isArray(batches) || batches.length === 0) return null
  let sum = 0
  let has = false
  for (const b of batches) {
    if (b.status && b.status !== 'active') continue
    const qty = Number(b.qty ?? 0)
    if (!Number.isFinite(qty) || qty <= 0) continue
    const raw = b.unit_price
    if (raw == null || raw === '') continue
    const up = Number(raw)
    if (!Number.isFinite(up) || up < 0) continue
    sum += qty * up
    has = true
  }
  return has ? sum : null
}

/**
 * Stückzahl wie bei {@link sumAcquisitionBasisFromBatches}: aktive Chargen mit Stückpreis (Summe Mengen).
 */
export function sumAcquisitionPieceCountFromBatches(batches: BatchLike[] | undefined | null): number | null {
  if (!Array.isArray(batches) || batches.length === 0) return null
  let q = 0
  let has = false
  for (const b of batches) {
    if (b.status && b.status !== 'active') continue
    const qty = Number(b.qty ?? 0)
    if (!Number.isFinite(qty) || qty <= 0) continue
    const raw = b.unit_price
    if (raw == null || raw === '') continue
    const up = Number(raw)
    if (!Number.isFinite(up) || up < 0) continue
    q += qty
    has = true
  }
  return has ? q : null
}

/**
 * Anschaffungsanteil für eine Stücklistenzeile (Kombi): mittlerer Stückpreis aus aktiven Chargen × Menge im Set.
 */
export function comboLineAcquisitionChf(batches: BatchLike[] | undefined | null, qtyInSet: number): number | null {
  const basis = sumAcquisitionBasisFromBatches(batches)
  const pieces = sumAcquisitionPieceCountFromBatches(batches)
  if (basis == null || pieces == null || pieces <= 0) return null
  const perPiece = basis / pieces
  if (!Number.isFinite(perPiece) || perPiece < 0) return null
  const q = Number(qtyInSet)
  if (!Number.isFinite(q) || q < 0) return null
  return perPiece * q
}

/**
 * Schweizer 5-Rappen-Rundung (0,05-CHF-Schritte).
 */
export function roundChfToFiveRappen(n: number): number {
  if (!Number.isFinite(n)) return 0
  return Math.round(n * 20) / 20
}

/** Anzeige nach 5-Rappen-Rundung (zwei Nachkommastellen). */
export function formatChfFiveRappenString(n: number): string {
  if (!Number.isFinite(n)) return '0.00'
  return roundChfToFiveRappen(n).toFixed(2)
}

export interface RentalAmortizationInput {
  /** Anschaffungs- oder Wiederbeschaffungsbasis (CHF) */
  basisChf: number
  /** Jahre bis erwarteter Neukauf / Ende der Nutzungsdauer */
  yearsToReplacement: number
  /** Erwartete interne Vermietungstage pro Jahr (Verein intern) */
  internalDaysPerYear: number
  /** Erwartete externe Vermietungstage pro Jahr (Dritte) */
  externalDaysPerYear: number
  /** Optionaler Aufschlag auf den reinen Break-even-Tagespreis (z. B. 20 = 20 %) */
  markupPercent?: number
  /**
   * Anzahl Stück (Bestand), auf die sich die Gesamtbasis bezieht.
   * Wenn &gt; 1: Amortisation auf **Anschaffung pro Stück** (= Basis ÷ Stückzahl); Vorschlag = Mietpreis **pro Stück**.
   */
  pieceCount?: number
}

export interface RentalPriceSuggestion {
  /** Pro Stück, 5 Rappen (Vermietfeld) */
  day: string
  week: string
  month: string
  yearsToReplacement: number
  internalDaysPerYear: number
  externalDaysPerYear: number
  /** intern + extern */
  totalDaysPerYear: number
  /** yearsToReplacement × totalDaysPerYear */
  totalRentalDays: number
  /** Break-even / Tag pro Stück ohne Aufschlag */
  dailyBreakEven: string
  /** Verwendete Stückzahl (mindestens 1) */
  pieceCountUsed: number
  /** Wenn &gt; 1: gleicher Tagessatz × Stückzahl (alle Stück gleichzeitig vermietet) */
  dayTotalAllPieces: string | null
  weekTotalAllPieces: string | null
  monthTotalAllPieces: string | null
  dailyBreakEvenTotalAllPieces: string | null
}

/**
 * Grobe Schätzung des Wiederbeschaffungswerts beim Neukauf:
 * historische Anschaffung × (1 + jährliche Preissteigerung)^Jahre bis Neukauf.
 * Kein exakter Index – nur Planungsgrösse für die Kalkulationsbasis.
 */
export function projectReplacementBasisChf(
  historicalBasisChf: number,
  yearsToReplacement: number,
  priceIncreasePercentPerYear: number
): number | null {
  if (!Number.isFinite(historicalBasisChf) || historicalBasisChf <= 0) return null
  if (!Number.isFinite(yearsToReplacement) || yearsToReplacement <= 0) return null
  const p = Number(priceIncreasePercentPerYear)
  if (!Number.isFinite(p)) return null
  const factor = (1 + p / 100) ** yearsToReplacement
  if (!Number.isFinite(factor) || factor <= 0) return null
  return roundChfToFiveRappen(historicalBasisChf * factor)
}

/**
 * Linearer Break-even: Basis wird über (Jahre bis Neukauf × (interne + externe Miettage/Jahr)) verteilt.
 * Ein Tag „in Gebrauch“ (intern oder extern) trägt gleich zur Amortisation bei – der Vorschlag ist ein **gemittelter** Tagessatz.
 * Woche = 7× Tag, Monat ≈ 30× Tag (wie bei Aktivitätskosten).
 */
export function suggestRentalPricesFromAmortization(
  input: RentalAmortizationInput
): RentalPriceSuggestion | null {
  const basis = input.basisChf
  const years = Number(input.yearsToReplacement)
  const intD = Number(input.internalDaysPerYear)
  const extD = Number(input.externalDaysPerYear)
  const markup = Number(input.markupPercent ?? 0)

  if (!Number.isFinite(basis) || basis <= 0) return null
  if (!Number.isFinite(years) || years <= 0) return null
  if (!Number.isFinite(intD) || intD < 0) return null
  if (!Number.isFinite(extD) || extD < 0) return null

  const totalDaysPerYear = intD + extD
  if (!Number.isFinite(totalDaysPerYear) || totalDaysPerYear <= 0) return null

  const totalRentalDays = years * totalDaysPerYear
  if (!Number.isFinite(totalRentalDays) || totalRentalDays <= 0) return null

  const pcRaw = Number(input.pieceCount ?? 1)
  const pieceCountUsed = Number.isFinite(pcRaw) && pcRaw > 0 ? Math.max(1, Math.floor(pcRaw)) : 1
  const basisPerPiece = basis / pieceCountUsed

  const dailyBreakEvenRaw = basisPerPiece / totalRentalDays
  const m = Number.isFinite(markup) ? markup : 0
  const factor = 1 + m / 100
  const dailyRaw = dailyBreakEvenRaw * factor

  const dayTotalRaw = pieceCountUsed > 1 ? dailyRaw * pieceCountUsed : null
  const dailyBreakEvenTotalRaw = pieceCountUsed > 1 ? dailyBreakEvenRaw * pieceCountUsed : null

  return {
    dailyBreakEven: formatChfFiveRappenString(dailyBreakEvenRaw),
    day: formatChfFiveRappenString(dailyRaw),
    week: formatChfFiveRappenString(dailyRaw * 7),
    month: formatChfFiveRappenString(dailyRaw * 30),
    yearsToReplacement: years,
    internalDaysPerYear: intD,
    externalDaysPerYear: extD,
    totalDaysPerYear,
    totalRentalDays,
    pieceCountUsed,
    dayTotalAllPieces: dayTotalRaw != null ? formatChfFiveRappenString(dayTotalRaw) : null,
    weekTotalAllPieces:
      dayTotalRaw != null ? formatChfFiveRappenString(dayTotalRaw * 7) : null,
    monthTotalAllPieces:
      dayTotalRaw != null ? formatChfFiveRappenString(dayTotalRaw * 30) : null,
    dailyBreakEvenTotalAllPieces:
      dailyBreakEvenTotalRaw != null ? formatChfFiveRappenString(dailyBreakEvenTotalRaw) : null,
  }
}

/** Gespeicherte Eingaben des Amortisationsrechners pro Material (API: rental_calc_params) */
export interface RentalCalcParams {
  basis_override?: string | null
  price_increase_percent_per_year?: number
  years_to_replacement?: number
  internal_days_per_year?: number
  external_days_per_year?: number
  markup_percent?: number
}
