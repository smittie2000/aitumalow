import { useEffect, useState } from 'react'
import { Clock, RefreshCw } from 'lucide-react'
import { useRunStore, useWorkflowEditorStore } from '../../stores/EditorRuntimeProvider'
import { RunStatusBadge } from '../shared/StatusBadge'
import { RunDetailModal } from './RunDetailModal'

export function RunHistoryPanel() {
  const workflow = useWorkflowEditorStore((s) => s.workflow)
  const { runs, isLoading, fetchRuns, selectedRun, fetchRunDetail, clearSelectedRun } =
    useRunStore()
  const [showDetail, setShowDetail] = useState(false)

  useEffect(() => {
    if (workflow?.id) fetchRuns(workflow.id)
  }, [workflow?.id, fetchRuns])

  const handleViewRun = async (runId: number) => {
    await fetchRunDetail(runId)
    setShowDetail(true)
  }

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between px-1">
        <span className="text-xs font-semibold text-gray-600 dark:text-gray-400">Run History</span>
        <button
          onClick={() => workflow?.id && fetchRuns(workflow.id)}
          className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
          title="Refresh"
        >
          <RefreshCw size={12} className={isLoading ? 'animate-spin' : ''} />
        </button>
      </div>

      {runs.length === 0 ? (
        <p className="py-4 text-center text-xs text-gray-400 dark:text-gray-500">No runs yet</p>
      ) : (
        <div className="space-y-1">
          {runs.map((run) => (
            <button
              key={run.id}
              onClick={() => handleViewRun(run.id)}
              className="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-xs hover:bg-gray-100 dark:hover:bg-gray-700"
            >
              <RunStatusBadge status={run.status} />
              <span className="flex-1 truncate text-gray-600 dark:text-gray-400">#{run.id}</span>
              {run.duration_ms != null && (
                <span className="flex items-center gap-0.5 text-[10px] text-gray-400 dark:text-gray-500">
                  <Clock size={10} />
                  {run.duration_ms}ms
                </span>
              )}
            </button>
          ))}
        </div>
      )}

      {showDetail && selectedRun && (
        <RunDetailModal
          run={selectedRun}
          onClose={() => {
            setShowDetail(false)
            clearSelectedRun()
          }}
        />
      )}
    </div>
  )
}
