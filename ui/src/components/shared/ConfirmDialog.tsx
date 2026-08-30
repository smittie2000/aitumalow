import { createPortal } from 'react-dom'
import { useEditorPortalTarget } from '../../stores/EditorRuntimeProvider'

interface ConfirmDialogProps {
  open: boolean
  title: string
  message: string
  confirmLabel?: string
  variant?: 'danger' | 'primary'
  onConfirm: () => void
  onCancel: () => void
}

export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel = 'Delete',
  variant = 'danger',
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  const portalTarget = useEditorPortalTarget()
  if (!open) return null
  const titleId = 'confirm-dialog-' + title.toLowerCase().replace(/[^a-z0-9]+/g, '-')

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" role="presentation">
      <div
        className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800 dark:shadow-2xl dark:shadow-black/40"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
      >
        <h3 id={titleId} className="text-lg font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{message}</p>
        <div className="mt-4 flex justify-end gap-2">
          <button
            type="button"
            onClick={onCancel}
            className="rounded-md px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={onConfirm}
            className={`rounded-md px-3 py-1.5 text-sm text-white ${
              variant === 'danger'
                ? 'bg-red-600 hover:bg-red-700'
                : 'bg-blue-600 hover:bg-blue-700'
            }`}
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>,
    portalTarget,
  )
}
