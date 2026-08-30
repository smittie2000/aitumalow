import { X, RotateCw, Ban, AlertTriangle, Loader2 } from 'lucide-react'
import type { WorkflowRun } from '../../api/types'
import { RunStatusBadge, NodeRunStatusBadge } from '../shared/StatusBadge'
import { JsonViewer } from '../shared/JsonViewer'
import { useEditorPortalTarget, useRunStore, useWorkflowEditorStore } from '../../stores/EditorRuntimeProvider'
import { useReactFlow } from '@xyflow/react'
import { useState } from 'react'
import { createPortal } from 'react-dom'

interface Props {
  run: WorkflowRun
  onClose: () => void
}

export function RunDetailModal({ run, onClose }: Props) {
  const portalTarget = useEditorPortalTarget()
  const { cancelRun, replayRun, isReplaying } = useRunStore()
  const selectNode = useWorkflowEditorStore((s) => s.selectNode)
  const rfNodes = useWorkflowEditorStore((s) => s.rfNodes)
  const { fitView } = useReactFlow()
  const [expandedNode, setExpandedNode] = useState<number | null>(null)

  const focusNode = (nodeId: number) => {
    const nodeIdStr = String(nodeId)
    const node = rfNodes.find((n) => n.id === nodeIdStr)
    if (!node) return
    selectNode(nodeIdStr)
    onClose()
    setTimeout(() => {
      fitView({ nodes: [{ id: nodeIdStr }], duration: 400, padding: 0.5 })
    }, 50)
  }

  const canCancel = run.status === 'running' || run.status === 'waiting'

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="flex max-h-[80vh] w-full max-w-3xl flex-col rounded-lg bg-white shadow-xl dark:bg-gray-800 dark:shadow-2xl dark:shadow-black/40 mx-4">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
          <div>
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Run #{run.id}</h3>
            <div className="mt-1 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
              <RunStatusBadge status={run.status} />
              {run.duration_ms != null && <span>{run.duration_ms}ms</span>}
              {run.started_at && <span>{new Date(run.started_at).toLocaleString()}</span>}
            </div>
          </div>
          <div className="flex items-center gap-2">
            {canCancel && (
              <button
                type="button"
                onClick={() => cancelRun(run.id)}
                className="flex items-center gap-1 rounded-md bg-red-50 px-2 py-1 text-xs text-red-600 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50"
              >
                <Ban size={12} /> Cancel
              </button>
            )}
            <button
              type="button"
              onClick={() => replayRun(run.id)}
              disabled={isReplaying}
              className="flex items-center gap-1 rounded-md bg-blue-50 px-2 py-1 text-xs text-blue-600 hover:bg-blue-100 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50"
            >
              {isReplaying ? <Loader2 size={12} className="animate-spin" /> : <RotateCw size={12} />}
              {isReplaying ? 'Replaying...' : 'Replay'}
            </button>
            <button
              type="button" onClick={onClose} className="rounded p-1 text-gray-400 hover:bg-gray-100 dark:text-gray-500 dark:hover:bg-gray-700">
              <X size={18} />
            </button>
          </div>
        </div>

        {/* Error */}
        {run.error_message && (() => {
          try {
            const parsed = JSON.parse(run.error_message)
            if (parsed.errors) {
              return (
                <div className="mx-5 mt-3 rounded-md bg-red-50 px-3 py-2 dark:bg-red-900/30">
                  <p className="mb-1.5 text-xs font-medium text-red-700 dark:text-red-400">{parsed.message}</p>
                  <ul className="list-disc space-y-0.5 pl-4 text-xs text-red-600 dark:text-red-400/80">
                    {parsed.errors.map((err: string, i: number) => (
                      <li key={i}>{err}</li>
                    ))}
                  </ul>
                </div>
              )
            }
          } catch {
            // not JSON, fall through
          }
          return (
            <div className="mx-5 mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-900/30 dark:text-red-400">
              {run.error_message}
            </div>
          )
        })()}

        {/* Node Runs */}
        <div className="flex-1 overflow-y-auto px-5 py-4">
          {!run.node_runs || run.node_runs.length === 0 ? (
            <p className="text-center text-sm text-gray-400 dark:text-gray-500">No node runs recorded</p>
          ) : (
            <table className="w-full text-xs">
              <thead>
                <tr className="border-b border-gray-100 text-left text-gray-500 dark:border-gray-700 dark:text-gray-400">
                  <th className="pb-2 font-medium">Node</th>
                  <th className="pb-2 font-medium">Status</th>
                  <th className="pb-2 font-medium">Duration</th>
                  <th className="pb-2 font-medium">Attempts</th>
                  <th className="pb-2 font-medium">Error</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                {run.node_runs.map((nr) => (
                  <>
                    <tr
                      key={nr.id}
                      className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700"
                      onClick={() => setExpandedNode(expandedNode === nr.id ? null : nr.id)}
                    >
                      <td className="py-2 font-mono dark:text-gray-300">
                        <span className="flex items-center gap-1">
                          #{nr.node_id}
                          {nr.status === 'failed' && (
                            <button
                              type="button"
                              onClick={(e) => {
                                e.stopPropagation()
                                focusNode(nr.node_id)
                              }}
                              title="Locate failed node on canvas"
                              className="rounded p-0.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30"
                            >
                              <AlertTriangle size={12} />
                            </button>
                          )}
                        </span>
                      </td>
                      <td className="py-2">
                        <NodeRunStatusBadge status={nr.status} />
                      </td>
                      <td className="py-2 text-gray-500 dark:text-gray-400">{nr.duration_ms ? `${nr.duration_ms}ms` : '-'}</td>
                      <td className="py-2 text-gray-500 dark:text-gray-400">{nr.attempts ?? '-'}</td>
                      <td className="max-w-[200px] truncate py-2 text-red-500">{nr.error_message || '-'}</td>
                    </tr>
                    {expandedNode === nr.id && (
                      <tr key={`${nr.id}-detail`}>
                        <td colSpan={5} className="bg-gray-50 p-3 dark:bg-gray-900">
                          <div className="grid grid-cols-2 gap-3">
                            <div>
                              <p className="mb-1 text-[10px] font-medium text-gray-500 dark:text-gray-400">Input</p>
                              <JsonViewer data={nr.input} maxHeight="200px" />
                            </div>
                            <div>
                              <p className="mb-1 text-[10px] font-medium text-gray-500 dark:text-gray-400">Output</p>
                              <JsonViewer data={nr.output} maxHeight="200px" />
                            </div>
                          </div>
                        </td>
                      </tr>
                    )}
                  </>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>,
    portalTarget,
  )
}
