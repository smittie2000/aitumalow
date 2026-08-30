import { useState } from 'react'
import { createPortal } from 'react-dom'
import { X, Play, Loader2 } from 'lucide-react'
import { useEditorPortalTarget } from '../../stores/EditorRuntimeProvider'

interface Props {
  nodeName: string
  onRun: (payload: Record<string, unknown>) => void
  onClose: () => void
  isRunning: boolean
  initialPayload?: string
}

export function TestNodeInputModal({ nodeName, onRun, onClose, isRunning, initialPayload }: Props) {
  const portalTarget = useEditorPortalTarget()
  const [payloadText, setPayloadText] = useState(initialPayload ?? '[\n  \n]')
  const [error, setError] = useState<string | null>(null)

  const handleRun = () => {
    setError(null)
    let parsed: unknown
    try {
      parsed = JSON.parse(payloadText)
    } catch {
      setError('Invalid JSON payload')
      return
    }
    onRun(parsed as Record<string, unknown>)
  }

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800 dark:shadow-2xl dark:shadow-black/40">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Test Node
          </h2>
          <button
            onClick={onClose}
            className="rounded p-1 text-gray-400 hover:bg-gray-100 dark:text-gray-500 dark:hover:bg-gray-700"
          >
            <X size={18} />
          </button>
        </div>

        <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
          Executes the workflow from the trigger up to and including <strong className="text-gray-700 dark:text-gray-300">{nodeName}</strong>.
        </p>

        <div className="mt-4">
          <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            Trigger Payload (JSON)
            {initialPayload && (
              <span className="ml-2 text-[10px] font-normal text-orange-500">pinned</span>
            )}
          </label>
          <textarea
            value={payloadText}
            onChange={(e) => setPayloadText(e.target.value)}
            className="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-xs focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            rows={8}
            placeholder='[{"name": "Alice", "email": "alice@example.com"}]'
          />
        </div>

        {error && (
          <div className="mt-2 rounded-md bg-red-50 px-3 py-2 text-xs text-red-600 dark:bg-red-900/30 dark:text-red-400">
            {error}
          </div>
        )}

        <div className="mt-4 flex justify-end gap-2">
          <button
            onClick={onClose}
            className="rounded-md px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
          >
            Cancel
          </button>
          <button
            onClick={handleRun}
            disabled={isRunning}
            className="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {isRunning ? (
              <Loader2 size={14} className="animate-spin" />
            ) : (
              <Play size={14} />
            )}
            {isRunning ? 'Running...' : `Run up to ${nodeName}`}
          </button>
        </div>
      </div>
    </div>,
    portalTarget,
  )
}
