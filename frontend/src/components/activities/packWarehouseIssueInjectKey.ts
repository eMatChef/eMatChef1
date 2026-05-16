import type { InjectionKey } from 'vue'

/** Kontext für PackWarehouseIssueContainerCard (Gepackt → Am Event, linke Behälter) */
export const PACK_WAREHOUSE_ISSUE_INJECT_KEY: InjectionKey<Record<string, unknown>> = Symbol(
  'packWarehouseIssueInject',
)
