import { unref, type InjectionKey, type MaybeRef } from 'vue'

/** Kontext für PackWarehouseIssueContainerCard (Gepackt → Am Event, linke Behälter) */
export const PACK_WAREHOUSE_ISSUE_INJECT_KEY: InjectionKey<Record<string, unknown>> = Symbol(
  'packWarehouseIssueInject',
)

/** Refs im provide-Objekt korrekt auslesen (Boolean(ref) wäre sonst immer true). */
export function injectPackCtxBool(ctx: Record<string, unknown>, key: string): boolean {
  return Boolean(unref(ctx[key] as MaybeRef<boolean | undefined>))
}
