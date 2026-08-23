import { reactive } from 'vue'
import { listExtraGuestLoans } from '@/views/grossanlass/grossanlassChainPreviewStore'
import type { GaEinsatzResource } from '@/views/grossanlass/grossanlassEinsatzPreviewData'
import {
  createGuestLoans,
  type GaGuestLoan,
  type GaGuestLoanStatus,
} from '@/views/grossanlass/grossanlassGaestePreviewData'

type Translate = (key: string, values?: Record<string, string | number>) => string

const state = reactive({
  status: {} as Record<string, GaGuestLoanStatus>,
})

export function listGuestLoans(t: Translate): GaGuestLoan[] {
  const base = createGuestLoans(t).map((loan) => {
    const status = state.status[loan.id] ?? loan.status
    return {
      ...loan,
      status,
      bookable: status === 'accepted',
    }
  })
  const extras: GaGuestLoan[] = listExtraGuestLoans().map((loan) => {
    const status = state.status[loan.id] ?? 'offered'
    const departmentName = t(
      loan.departmentId === 'zh'
        ? 'grossanlass.materials.sourceZuerich'
        : loan.departmentId === 'us'
          ? 'grossanlass.materials.sourceUster'
          : 'grossanlass.materials.sourceWinterthur',
    )
    return {
      id: loan.id,
      departmentId: loan.departmentId,
      departmentName,
      name: loan.name,
      qty: loan.qty,
      family: loan.family,
      fromLabel: '03.09.27',
      toLabel: '06.09.27',
      presentFromIso: '2027-09-03T08:00:00',
      presentToIso: '2027-09-06T18:00:00',
      status,
      bookable: status === 'accepted',
    }
  })
  return [...base, ...extras]
}

export function setGuestLoanStatus(id: string, status: GaGuestLoanStatus) {
  state.status[id] = status
}

export function acceptedGuestLoanResources(t: Translate): GaEinsatzResource[] {
  return listGuestLoans(t)
    .filter((loan) => loan.bookable)
    .map((loan) => ({
      id: loan.id,
      name: `${loan.name} (${loan.departmentName})`,
      family: loan.family,
      stayMode: 'return' as const,
      categoryId: loan.family === 'vehicle' ? 'fahrzeuge' : 'infra',
      kind: loan.family === 'vehicle' ? 'unique' : 'quantity',
      stock: loan.qty,
      presentFromIso: loan.presentFromIso,
      presentToIso: loan.presentToIso,
      released: true,
    }))
}
