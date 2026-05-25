import {
  WORKFLOW_STATUS_CONFIRM_CONFIG,
  type WorkflowStatusConfirmKind,
} from '@/components/activities/packStepUi'
import type { ComposerTranslation } from 'vue-i18n'

type ConfirmDialogFn = (opts: {
  title: string
  message: string
  confirmText: string
  cancelText: string
  variant: 'warning' | 'info' | 'danger'
}) => Promise<boolean>

type ToastFn = { error: (msg: string) => void }

export interface ConfirmWorkflowStatusOptions {
  kind: WorkflowStatusConfirmKind
  stageProgress: number
  getPendingMessage: (variant: 'status' | 'return' | 'transition') => string
  hasMinimum: () => boolean
  confirmMwHandoff: () => Promise<boolean>
  t: ComposerTranslation
  confirmDialog: ConfirmDialogFn
  toast: ToastFn
}

export async function confirmWorkflowStatusTransition(
  opts: ConfirmWorkflowStatusOptions,
): Promise<boolean> {
  const cfg = WORKFLOW_STATUS_CONFIRM_CONFIG[opts.kind]
  if (!opts.hasMinimum()) {
    opts.toast.error(opts.t(cfg.toastNothingKey))
    return false
  }
  if (!(await opts.confirmMwHandoff())) return false
  if (opts.stageProgress < 100) {
    const ok = await opts.confirmDialog({
      title: opts.t(cfg.confirmTitleKey, { pct: opts.stageProgress }),
      message: opts.getPendingMessage(cfg.pendingVariant),
      confirmText: opts.t(cfg.confirmProceedKey),
      cancelText: opts.t('activities.common.cancel'),
      variant: 'warning',
    })
    if (!ok) return false
  }
  return true
}
