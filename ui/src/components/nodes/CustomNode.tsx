import { memo, useCallback, createElement } from 'react'
import { Handle, Position, type NodeProps } from '@xyflow/react'
import type { CustomNodeData } from '../../lib/mappers'
import { NODE_TYPE_COLORS } from '../../lib/constants'
import { capabilityIcon } from './nodeStyles'
import { useActionPicker } from '../editor/ActionPickerContext'
import type { NodeType } from '../../api/types'
import { useRunStore, useWorkflowEditorStore } from '../../stores/EditorRuntimeProvider'
import { AlertTriangle, CheckCircle2, XCircle, Loader2, Pin, Play, Plus } from 'lucide-react'

function CustomNodeComponent({ data, selected }: NodeProps) {
  const nodeData = data as unknown as CustomNodeData
  const colors = NODE_TYPE_COLORS[nodeData.nodeType as NodeType] ?? NODE_TYPE_COLORS.action
  const icon = capabilityIcon(nodeData.registryNode?.icon ?? '', nodeData.nodeType as NodeType)
  const openActionPicker = useActionPicker()
  const edges = useWorkflowEditorStore((state) => state.rfEdges)

  const inputPorts = nodeData.inputPorts ?? []
  const outputPorts = nodeData.outputPorts ?? []
  const apiNodeId = nodeData.apiNode?.id


  const testResult = useRunStore((state) => apiNodeId ? state.nodeTestResults?.[apiNodeId] : undefined)
  const testGraphHash = useRunStore((state) => state.testGraphHash)
  const graphHash = useWorkflowEditorStore((state) => state.graphHash)
  const testStale = testGraphHash !== graphHash
  const hasNodeTestResults = useRunStore((state) => state.nodeTestResults !== null)
  const isTestingNode = useRunStore((s) => s.isTestingNode)
  const requestNodeTest = useRunStore((s) => s.requestNodeTest)
  const hasPinnedData = !!(nodeData.apiNode?.pinned_data?.input || nodeData.apiNode?.pinned_data?.output)
  const isInvalid = nodeData.invalid === true

  const handleRunClick = useCallback((e: React.MouseEvent) => {
    e.stopPropagation()
    if (apiNodeId && !isTestingNode) {
      requestNodeTest(apiNodeId)
    }
  }, [apiNodeId, isTestingNode, requestNodeTest])

  return (
    <div
      className={`group relative w-60 rounded-xl border border-gray-200 bg-white dark:border-gray-600 dark:bg-gray-800 shadow-sm ${
        isInvalid
          ? 'ring-2 ring-red-500 shadow-red-200 dark:shadow-red-950'
          : selected ? 'ring-2 ring-blue-400' : ''
      }`}
      style={{ minHeight: 88, minWidth: Math.max(240, Math.max(inputPorts.length, outputPorts.length) * 100) }}
      aria-invalid={isInvalid || undefined}
      title={nodeData.validationMessage ?? undefined}
    >
      {/* Pinned Data Badge */}
      {hasPinnedData && (
        <div className="absolute -left-1.5 -top-1.5 z-10" title="Pinned test data">
          <Pin size={14} className="rounded-full bg-white text-orange-500 dark:bg-gray-800" />
        </div>
      )}

      {isInvalid && (
        <div
          className="absolute -right-1.5 -top-1.5 z-20 rounded-full bg-white text-red-500 dark:bg-gray-800"
          aria-label="Node has validation errors"
        >
          <AlertTriangle size={16} />
        </div>
      )}

      {/* Test Status Badge */}
      {(testResult || (isTestingNode && !hasNodeTestResults)) && (
        <div title={testStale ? 'Earlier draft test — test again for current results' : 'Latest draft test'} className={`absolute -top-1.5 z-10 ${isInvalid ? 'right-4' : '-right-1.5'} ${testStale ? 'opacity-40 grayscale' : ''}`}>
          {testResult?.status === 'completed' && (
            <CheckCircle2 size={16} className="rounded-full bg-white text-green-500 dark:bg-gray-800" />
          )}
          {testResult?.status === 'failed' && (
            <XCircle size={16} className="rounded-full bg-white text-red-500 dark:bg-gray-800" />
          )}
          {testResult?.status === 'running' && (
            <Loader2 size={16} className="animate-spin rounded-full bg-white text-blue-500 dark:bg-gray-800" />
          )}
        </div>
      )}

      {/* Input Handles */}
      {inputPorts.map((port, i) => {
        const leftPercent =
          inputPorts.length === 1 ? 50 : 20 + (60 / (inputPorts.length - 1)) * i
        return (
          <Handle
            key={`in-${port}`}
            type="target"
            position={Position.Top}
            id={port}
            style={{ left: `${leftPercent}%` }}
            className="h-2.5! w-2.5! border-2! border-white! bg-gray-400!"
          />
        )
      })}

      <div className="flex min-h-22 items-center gap-3 px-4 py-4">
        <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-700 ${colors.text}`}>{createElement(icon, { size: 23 })}</span>
        <div className="min-w-0 flex-1">
          <span className="block text-sm font-semibold leading-snug text-gray-800 dark:text-gray-100">{nodeData.label}</span>
          <span className="mt-1 block text-[11px] capitalize text-gray-400">{nodeData.nodeType === 'trigger' ? 'Trigger' : nodeData.nodeType === 'condition' ? 'Branch' : nodeData.registryNode?.category || nodeData.nodeType}</span>
        </div>
      </div>
      <button type="button" aria-label={`Test ${nodeData.label}`} onClick={handleRunClick} disabled={isTestingNode} className="nodrag nopan absolute -top-7 right-0 flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-[10px] text-gray-600 opacity-0 transition-opacity hover:text-blue-600 focus:opacity-100 group-hover:opacity-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300" title="Run up to this step">
        {isTestingNode ? <Loader2 size={10} className="animate-spin" /> : <Play size={10} />} Test step
      </button>
      {outputPorts.map((port, i) => {
        const leftPercent = outputPorts.length === 1 ? 50 : ((i + 1) / (outputPorts.length + 1)) * 100
        return (
          <div key={port}>
            <Handle type="source" position={Position.Bottom} id={port} style={{ left: `${leftPercent}%` }} className="h-3! w-3! border-2! border-white! bg-gray-400! hover:bg-blue-500!" />
            <div className="pointer-events-none absolute top-full flex -translate-x-1/2 flex-col items-center gap-1 pt-3" style={{ left: `${leftPercent}%` }}>
              {outputPorts.length > 1 && <span className="whitespace-nowrap rounded bg-gray-50 px-1 text-[10px] text-gray-500 dark:bg-gray-900 dark:text-gray-300">{port}</span>}
              {!edges.some((edge) => edge.source === String(apiNodeId) && edge.sourceHandle === port) && <button type="button" onClick={(event) => { event.stopPropagation(); openActionPicker({ source: { nodeId: String(apiNodeId), port } }) }} aria-label={`Add action after ${nodeData.label}, ${port}`} className="nodrag nopan pointer-events-auto flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-500 shadow-sm hover:border-blue-500 hover:text-blue-600 focus-visible:outline-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"><Plus size={13} /></button>}
            </div>
          </div>
        )
      })}
    </div>
  )
}

export const CustomNode = memo(CustomNodeComponent)
