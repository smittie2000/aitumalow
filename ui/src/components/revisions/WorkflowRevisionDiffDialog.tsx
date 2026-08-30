import { useMemo, useState } from 'react'
import { createPortal } from 'react-dom'
import { ChevronDown, ChevronRight, GitCompareArrows, RotateCcw, X } from 'lucide-react'
import type { WorkflowRevisionComparison } from '../../api/types'
import {
  buildWorkflowDefinitionChanges,
  formatWorkflowDiffValue,
  type WorkflowDefinitionChange,
} from '../../lib/workflowDefinitionDiff'
import { useEditorPortalTarget } from '../../stores/EditorRuntimeProvider'

interface Props {
  comparison: WorkflowRevisionComparison
  onClose: () => void
  onRestore: () => void
}

const changeTone: Record<WorkflowDefinitionChange['kind'], string> = {
  added: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
  removed: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
  changed: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
}

function ChangeCard({ change }: { change: WorkflowDefinitionChange }) {
  const [expanded, setExpanded] = useState(false)
  const hasDetails = change.before !== undefined || change.after !== undefined

  return (
    <div className="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
      <button
        type="button"
        onClick={() => hasDetails && setExpanded((value) => !value)}
        className="flex w-full items-start gap-2 px-3 py-2.5 text-left"
        aria-expanded={hasDetails ? expanded : undefined}
      >
        <span className="mt-0.5 text-gray-400 dark:text-gray-500">
          {hasDetails
            ? expanded ? <ChevronDown size={14} /> : <ChevronRight size={14} />
            : <span className="inline-block w-3.5" />}
        </span>
        <span className="min-w-0 flex-1">
          <span className="flex flex-wrap items-center gap-2">
            <span className="truncate text-xs font-semibold text-gray-800 dark:text-gray-200">
              {change.label}
            </span>
            <span className={`rounded-full px-1.5 py-0.5 text-[9px] font-semibold uppercase ${changeTone[change.kind]}`}>
              {change.kind}
            </span>
            <span className="text-[9px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
              {change.target}
            </span>
          </span>
          <span className="mt-0.5 block text-[11px] text-gray-500 dark:text-gray-400">
            {change.summary}
          </span>
        </span>
      </button>

      {expanded && (
        <div className="grid gap-px border-t border-gray-200 bg-gray-200 dark:border-gray-700 dark:bg-gray-700 md:grid-cols-2">
          <div className="min-w-0 bg-red-50/60 p-3 dark:bg-red-950/20">
            <p className="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-red-600 dark:text-red-400">
              Published version
            </p>
            {change.before === undefined
              ? <p className="text-xs italic text-gray-400">Not present</p>
              : <pre className="max-h-64 overflow-auto whitespace-pre-wrap break-words text-[10px] leading-4 text-gray-700 dark:text-gray-300">{formatWorkflowDiffValue(change.before)}</pre>}
          </div>
          <div className="min-w-0 bg-green-50/60 p-3 dark:bg-green-950/20">
            <p className="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-green-600 dark:text-green-400">
              Current draft
            </p>
            {change.after === undefined
              ? <p className="text-xs italic text-gray-400">Not present</p>
              : <pre className="max-h-64 overflow-auto whitespace-pre-wrap break-words text-[10px] leading-4 text-gray-700 dark:text-gray-300">{formatWorkflowDiffValue(change.after)}</pre>}
          </div>
        </div>
      )}
    </div>
  )
}

export function WorkflowRevisionDiffDialog({ comparison, onClose, onRestore }: Props) {
  const portalTarget = useEditorPortalTarget()
  const changes = useMemo(() => buildWorkflowDefinitionChanges(
    comparison.revision_definition,
    comparison.draft_definition,
  ), [comparison])
  const counts = changes.reduce(
    (result, change) => ({ ...result, [change.kind]: result[change.kind] + 1 }),
    { added: 0, removed: 0, changed: 0 },
  )
  const titleId = `revision-diff-${comparison.revision.id}`

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3" role="presentation">
      <div
        className="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-gray-50 shadow-2xl dark:bg-gray-900"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
      >
        <div className="flex items-start justify-between gap-4 border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-700 dark:bg-gray-800">
          <div>
            <div className="flex items-center gap-2">
              <GitCompareArrows size={18} className="text-blue-600 dark:text-blue-400" />
              <h2 id={titleId} className="text-base font-semibold text-gray-900 dark:text-gray-100">
                Version {comparison.revision.version} → current draft
              </h2>
            </div>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Review graph, configuration, layout, pinned data, and execution-setting changes before replacing the draft.
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
            aria-label="Close version comparison"
          >
            <X size={18} />
          </button>
        </div>

        <div className="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-white px-5 py-2.5 text-[10px] dark:border-gray-700 dark:bg-gray-800">
          <span className="rounded-full bg-green-100 px-2 py-1 font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">
            {counts.added} added
          </span>
          <span className="rounded-full bg-red-100 px-2 py-1 font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300">
            {counts.removed} removed
          </span>
          <span className="rounded-full bg-amber-100 px-2 py-1 font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
            {counts.changed} changed
          </span>
          {comparison.revision.is_active && (
            <span className="ml-auto rounded-full bg-blue-100 px-2 py-1 font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
              Live version
            </span>
          )}
        </div>

        <div className="min-h-0 flex-1 space-y-2 overflow-y-auto p-4">
          {changes.length === 0 ? (
            <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-8 text-center text-sm text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-300">
              The current draft matches version {comparison.revision.version}.
            </div>
          ) : changes.map((change) => <ChangeCard key={change.id} change={change} />)}
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-white px-5 py-3 dark:border-gray-700 dark:bg-gray-800">
          <p className="text-[10px] text-gray-500 dark:text-gray-400">
            Restoring changes only the editable draft; the live version and existing runs stay unchanged.
          </p>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={onClose}
              className="rounded-md px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              Close
            </button>
            <button
              type="button"
              onClick={onRestore}
              className="flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
            >
              <RotateCcw size={12} /> Restore version {comparison.revision.version}
            </button>
          </div>
        </div>
      </div>
    </div>,
    portalTarget,
  )
}
