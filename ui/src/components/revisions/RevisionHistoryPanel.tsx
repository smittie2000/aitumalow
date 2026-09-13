import { useCallback, useEffect, useState } from 'react'
import { Check, GitCompareArrows, History, Loader2, RefreshCw } from 'lucide-react'
import type { WorkflowRevision, WorkflowRevisionComparison } from '../../api/types'
import { apiErrorMessage } from '../../api/client'
import { useEditorSdk } from '../../sdk/EditorSdkContext'
import {
  useRegistryStoreApi,
  useWorkflowEditorStore,
} from '../../stores/EditorRuntimeProvider'
import { ConfirmDialog } from '../shared/ConfirmDialog'
import { WorkflowRevisionDiffDialog } from './WorkflowRevisionDiffDialog'

export function RevisionHistoryPanel() {
  const sdk = useEditorSdk()
  const registryStore = useRegistryStoreApi()
  const workflow = useWorkflowEditorStore((state) => state.workflow)
  const editingBlocked = useWorkflowEditorStore((state) => state.isEditing || !!state.failedEdit || state.editConflict || Object.keys(state.nodeDrafts).length > 0)
  const loadWorkflow = useWorkflowEditorStore((state) => state.loadWorkflow)
  const [revisions, setRevisions] = useState<WorkflowRevision[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [isRestoring, setIsRestoring] = useState(false)
  const [revisionToRestore, setRevisionToRestore] = useState<WorkflowRevision | null>(null)
  const [comparison, setComparison] = useState<WorkflowRevisionComparison | null>(null)
  const [comparisonRevisionId, setComparisonRevisionId] = useState<number | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const fetchRevisions = useCallback(async () => {
    if (!workflow) return
    setIsLoading(true)
    setError(null)
    try {
      const response = await sdk.revisions.list(workflow.id)
      setRevisions(response.data)
    } catch (caught) {
      setError(apiErrorMessage(caught, 'Version history could not be loaded.'))
    } finally {
      setIsLoading(false)
    }
  }, [sdk.revisions, workflow])

  const compareDraft = async (revision: WorkflowRevision) => {
    if (!workflow || comparisonRevisionId !== null) return

    setComparisonRevisionId(revision.id)
    setError(null)
    setMessage(null)
    try {
      const response = await sdk.revisions.compareDraft(workflow.id, revision.id)
      setComparison(response.data)
    } catch (caught) {
      setError(apiErrorMessage(caught, 'The version comparison could not be loaded.'))
    } finally {
      setComparisonRevisionId(null)
    }
  }

  useEffect(() => {
    if (!workflow) return

    let cancelled = false
    void sdk.revisions.list(workflow.id)
      .then((response) => {
        if (cancelled) return
        setRevisions(response.data)
        setError(null)
      })
      .catch((caught: unknown) => {
        if (cancelled) return
        setError(apiErrorMessage(caught, 'Version history could not be loaded.'))
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [sdk.revisions, workflow])

  const restoreDraft = async () => {
    if (!workflow || !revisionToRestore || isRestoring || editingBlocked) return

    setIsRestoring(true)
    setError(null)
    setMessage(null)
    try {
      await sdk.revisions.restoreDraft(workflow.id, revisionToRestore.id)
      await loadWorkflow(workflow.id, registryStore.getState().getByKey)
      setMessage(`Draft restored from v${revisionToRestore.version}. The live version is unchanged.`)
      setRevisionToRestore(null)
    } catch (caught) {
      setError(apiErrorMessage(caught, 'The draft could not be restored.'))
    } finally {
      setIsRestoring(false)
    }
  }

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between px-1">
        <span className="text-xs font-semibold text-gray-600 dark:text-gray-400">Version History</span>
        <button
          type="button"
          onClick={() => void fetchRevisions()}
          className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
          title="Refresh"
          aria-label="Refresh version history"
        >
          <RefreshCw size={12} className={isLoading ? 'animate-spin' : ''} />
        </button>
      </div>

      {message && (
        <div className="rounded-md bg-green-50 px-2 py-2 text-[11px] text-green-700 dark:bg-green-900/30 dark:text-green-300">
          {message}
        </div>
      )}
      {error && (
        <div className="rounded-md bg-red-50 px-2 py-2 text-[11px] text-red-700 dark:bg-red-900/30 dark:text-red-300">
          {error}
        </div>
      )}

      {!isLoading && revisions.length === 0 ? (
        <div className="py-5 text-center text-xs text-gray-400 dark:text-gray-500">
          <History size={18} className="mx-auto mb-1" />
          No published versions yet
        </div>
      ) : (
        <div className="space-y-1">
          {revisions.map((revision) => (
            <div
              key={revision.id}
              className="rounded-md border border-gray-200 px-2 py-2 dark:border-gray-700"
            >
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <div className="flex items-center gap-1.5">
                    <span className="text-xs font-semibold text-gray-700 dark:text-gray-200">
                      v{revision.version}
                    </span>
                    {revision.is_active && (
                      <span className="flex items-center gap-0.5 rounded-full bg-green-100 px-1.5 py-0.5 text-[9px] font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">
                        <Check size={9} /> Live
                      </span>
                    )}
                  </div>
                  <p className="mt-0.5 truncate text-[10px] text-gray-400 dark:text-gray-500">
                    {new Date(revision.published_at).toLocaleString()}
                  </p>
                  {revision.published_by_reference && (
                    <p className="truncate text-[10px] text-gray-400 dark:text-gray-500">
                      {revision.published_by_reference}
                    </p>
                  )}
                </div>
                <button
                  type="button"
                  onClick={() => void compareDraft(revision)}
                  disabled={isRestoring || comparisonRevisionId !== null || editingBlocked}
                  className="flex shrink-0 items-center gap-1 rounded px-1.5 py-1 text-[10px] text-blue-600 hover:bg-blue-50 disabled:opacity-50 dark:text-blue-400 dark:hover:bg-blue-900/30"
                  title="Compare this version with the editable draft"
                >
                  {comparisonRevisionId === revision.id
                    ? <Loader2 size={10} className="animate-spin" />
                    : <GitCompareArrows size={10} />}
                  Review
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      <p className="px-1 text-[10px] leading-4 text-gray-400 dark:text-gray-500">
        Restoring changes only the editable draft. Publish when it is ready to become live.
      </p>

      <ConfirmDialog
        open={revisionToRestore !== null}
        title="Restore version to draft"
        message={revisionToRestore
          ? `Replace the current editable draft with v${revisionToRestore.version}? The live version and existing runs will not change.`
          : ''}
        confirmLabel={isRestoring ? 'Restoring...' : 'Restore draft'}
        variant="primary"
        onConfirm={() => void restoreDraft()}
        onCancel={() => !isRestoring && setRevisionToRestore(null)}
      />
      {comparison && (
        <WorkflowRevisionDiffDialog
          comparison={comparison}
          onClose={() => setComparison(null)}
          onRestore={() => {
            setRevisionToRestore(comparison.revision)
            setComparison(null)
          }}
        />
      )}
    </div>
  )
}
