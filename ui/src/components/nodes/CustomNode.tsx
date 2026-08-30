import { memo, useState, useCallback } from 'react'
import { Handle, Position, type NodeProps } from '@xyflow/react'
import type { CustomNodeData } from '../../lib/mappers'
import { NODE_TYPE_COLORS } from '../../lib/constants'
import { NODE_TYPE_ICON } from './nodeStyles'
import type { NodeType } from '../../api/types'
import { useRunStore } from '../../stores/EditorRuntimeProvider'
import { CheckCircle2, XCircle, Loader2, Pin, Play } from 'lucide-react'

function CustomNodeComponent({ data, selected }: NodeProps) {
  const nodeData = data as unknown as CustomNodeData
  const colors = NODE_TYPE_COLORS[nodeData.nodeType as NodeType] ?? NODE_TYPE_COLORS.action
  const Icon = NODE_TYPE_ICON[nodeData.nodeType as NodeType] ?? NODE_TYPE_ICON.action

  const inputPorts = nodeData.inputPorts ?? []
  const outputPorts = nodeData.outputPorts ?? []
  const apiNodeId = nodeData.apiNode?.id

  const [hoveredPort, setHoveredPort] = useState<string | null>(null)

  const testResult = useRunStore((state) => apiNodeId ? state.nodeTestResults?.[apiNodeId] : undefined)
  const hasNodeTestResults = useRunStore((state) => state.nodeTestResults !== null)
  const isTestingNode = useRunStore((s) => s.isTestingNode)
  const requestNodeTest = useRunStore((s) => s.requestNodeTest)
  const hasPinnedData = !!(nodeData.apiNode?.pinned_data?.input || nodeData.apiNode?.pinned_data?.output)

  const handleRunClick = useCallback((e: React.MouseEvent) => {
    e.stopPropagation()
    if (apiNodeId && !isTestingNode) {
      requestNodeTest(apiNodeId)
    }
  }, [apiNodeId, isTestingNode, requestNodeTest])

  return (
    <div
      className={`group relative min-w-40 rounded-lg border-l-4 bg-white dark:bg-gray-800 shadow-md ${colors.border} ${
        selected ? 'ring-2 ring-blue-400' : ''
      }`}
    >
      {/* Pinned Data Badge */}
      {hasPinnedData && (
        <div className="absolute -left-1.5 -top-1.5 z-10" title="Pinned test data">
          <Pin size={14} className="rounded-full bg-white text-orange-500 dark:bg-gray-800" />
        </div>
      )}

      {/* Test Status Badge */}
      {(testResult || (isTestingNode && !hasNodeTestResults)) && (
        <div className="absolute -right-1.5 -top-1.5 z-10">
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
        const topPercent =
          inputPorts.length === 1 ? 50 : 20 + (60 / (inputPorts.length - 1)) * i
        return (
          <Handle
            key={`in-${port}`}
            type="target"
            position={Position.Left}
            id={port}
            style={{ top: `${topPercent}%` }}
            className="h-2.5! w-2.5! border-2! border-white! bg-gray-400!"
          />
        )
      })}

      {/* Node Body */}
      <div className="px-3 py-2">
        <div className="flex items-center gap-1.5">
          <Icon size={14} className={colors.text} />
          <span className="truncate text-xs font-semibold text-gray-800 dark:text-gray-200">
            {nodeData.label}
          </span>
        </div>
        <div className="flex items-center justify-between mt-0.5">
          <span className="text-[10px] text-gray-400 dark:text-gray-500">{nodeData.nodeKey}</span>
          <button
            type="button"
            onClick={handleRunClick}
            disabled={isTestingNode}
            className="flex items-center gap-0.5 rounded px-1 py-0.5 text-[9px] font-medium text-green-600 opacity-0 transition-opacity hover:bg-green-50 group-hover:opacity-100 disabled:opacity-50 dark:text-green-400 dark:hover:bg-green-900/30 nopan"
            title="Run up to this node"
          >
            {isTestingNode ? <Loader2 size={9} className="animate-spin" /> : <Play size={9} />}
            Run
          </button>
        </div>
      </div>

      {/* Output Port Labels */}
      {outputPorts.length > 1 && (
        <div className="border-t border-gray-100 dark:border-gray-700 px-3 py-1">
          <div className="flex flex-wrap gap-1">
            {outputPorts.map((port) => (
              <span
                key={port}
                className={`cursor-default rounded px-1 py-0.5 text-[9px] transition-colors ${
                  hoveredPort === port
                    ? 'bg-blue-100 text-blue-700 ring-1 ring-blue-300'
                    : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'
                }`}
                onMouseEnter={() => setHoveredPort(port)}
                onMouseLeave={() => setHoveredPort(null)}
              >
                {port}
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Output Handles */}
      {outputPorts.map((port, i) => {
        const topPercent =
          outputPorts.length === 1 ? 50 : 20 + (60 / (outputPorts.length - 1)) * i
        return (
          <Handle
            key={`out-${port}`}
            type="source"
            position={Position.Right}
            id={port}
            style={{ top: `${topPercent}%` }}
            className={`border-2! border-white! transition-all ${
              hoveredPort === port
                ? 'h-3.5! w-3.5! bg-blue-600!'
                : 'h-2.5! w-2.5! bg-blue-500!'
            }`}
            onMouseEnter={() => setHoveredPort(port)}
            onMouseLeave={() => setHoveredPort(null)}
          />
        )
      })}
    </div>
  )
}

export const CustomNode = memo(CustomNodeComponent)
