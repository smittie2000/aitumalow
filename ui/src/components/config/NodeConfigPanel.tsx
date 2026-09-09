import { useState, useEffect, useCallback } from 'react'
import { X, Save, Play, Loader2, Settings, Database, Pin, PinOff, BookOpen, ChevronDown, ChevronRight, ArrowDownLeft } from 'lucide-react'
import { useShallow } from 'zustand/react/shallow'
import { useRunStore, useWorkflowEditorStore } from '../../stores/EditorRuntimeProvider'
import { useEditorSdk } from '../../sdk/EditorSdkContext'
import type { AvailableVariablesResponse } from '../../api/types'
import { DynamicForm } from './DynamicForm'
import { VariablePanel } from './VariablePanel'
import { JsonViewer } from '../shared/JsonViewer'
import { NodeRunStatusBadge } from '../shared/StatusBadge'
import { TestNodeInputModal } from '../execution/TestNodeInputModal'
import { MarkdownRenderer } from '../shared/MarkdownRenderer'
import { getUpstreamInputs, type UpstreamInput } from '../../lib/upstreamData'

type Tab = 'config' | 'output' | 'docs'

interface NodeConfigPanelProps {
  onTabChange?: (tab: Tab) => void
}

export function NodeConfigPanel({ onTabChange }: NodeConfigPanelProps) {
  const { nodes } = useEditorSdk()
  const { workflow, selectedApiNode, selectedRegistryNode, selectNode, updateNodeConfig, updateNodeLabel, setNodeLabel, setNodeConfig, pinNode, unpinNode } =
    useWorkflowEditorStore(useShallow((state) => ({
      workflow: state.workflow,
      selectedApiNode: state.selectedApiNode,
      selectedRegistryNode: state.selectedRegistryNode,
      selectNode: state.selectNode,
      updateNodeConfig: state.updateNodeConfig,
      updateNodeLabel: state.updateNodeLabel,
      setNodeLabel: state.setNodeLabel,
      setNodeConfig: state.setNodeConfig,
      pinNode: state.pinNode,
      unpinNode: state.unpinNode,
    })))
  const { nodeTestResults, isTestingNode, testNode } = useRunStore(useShallow((state) => ({
    nodeTestResults: state.nodeTestResults,
    isTestingNode: state.isTestingNode,
    testNode: state.testNode,
  })))

  const [localConfig, setLocalConfig] = useState<Record<string, unknown>>(selectedApiNode?.config ?? {})
  const [localLabel, setLocalLabel] = useState(selectedApiNode?.name ?? '')
  const [isDirty, setIsDirty] = useState(false)
  const [isSaving, setIsSaving] = useState(false)
  const [tab, setTabState] = useState<Tab>('config')
  const setTab = useCallback((t: Tab) => {
    setTabState(t)
    onTabChange?.(t)
  }, [onTabChange])
  const [showTestModal, setShowTestModal] = useState(false)
  const [variables, setVariables] = useState<AvailableVariablesResponse | null>(null)

  useEffect(() => {
    if (!workflow || !selectedApiNode) return
    nodes.availableVariables(workflow.id, selectedApiNode.id)
      .then(setVariables)
      .catch(() => setVariables(null))
  }, [workflow?.id, selectedApiNode?.id]) // eslint-disable-line react-hooks/exhaustive-deps

  const handleConfigChange = useCallback((key: string, value: unknown) => {
    setLocalConfig((prev) => {
      const updated = { ...prev, [key]: value }
      if (selectedApiNode?.type === 'annotation') {
        setNodeConfig(selectedApiNode.id, updated)
      }
      return updated
    })
    setIsDirty(true)
  }, [selectedApiNode, setNodeConfig])

  const handleSave = async () => {
    if (!selectedApiNode) return
    setIsSaving(true)
    try {
      if (localLabel !== (selectedApiNode.name ?? '')) {
        await updateNodeLabel(selectedApiNode.id, localLabel)
      }
      await updateNodeConfig(selectedApiNode.id, localConfig)
      setIsDirty(false)
    } finally {
      setIsSaving(false)
    }
  }

  const handleTestNode = async (payload: Record<string, unknown>) => {
    if (!workflow || !selectedApiNode) return
    await testNode(workflow.id, selectedApiNode.id, payload)
    setShowTestModal(false)
    setTab('output')
  }

  const [isPinning, setIsPinning] = useState(false)

  const handlePinOutput = async () => {
    if (!selectedApiNode || !nodeResult?.output) return
    setIsPinning(true)
    try {
      await pinNode(selectedApiNode.id, {
        source: 'manual',
        input: nodeResult.input ? [nodeResult.input] : undefined,
        output: nodeResult.output as Record<string, unknown[]>,
      })
    } finally {
      setIsPinning(false)
    }
  }

  const handleUnpin = async () => {
    if (!selectedApiNode) return
    setIsPinning(true)
    try {
      await unpinNode(selectedApiNode.id)
    } finally {
      setIsPinning(false)
    }
  }

  if (!selectedApiNode || !selectedRegistryNode) {
    return (
      <div className="flex h-full items-center justify-center p-4 text-center text-sm text-gray-400 dark:text-gray-500">
        Select a step to configure it
      </div>
    )
  }

  const isAnnotation = selectedApiNode.type === 'annotation'
  const nodeResult = nodeTestResults?.[selectedApiNode.id]
  const pinnedData = selectedApiNode.pinned_data
  const docContent = isAnnotation ? null : selectedRegistryNode.documentation

  return (
    <div className="flex h-full flex-col">
      {/* Header */}
      <div className="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-4 py-3">
        <div className="min-w-0">
          <input
            type="text"
            value={localLabel}
            onChange={(e) => {
              setLocalLabel(e.target.value)
              setIsDirty(true)
              if (selectedApiNode) {
                setNodeLabel(selectedApiNode.id, e.target.value)
              }
            }}
            className="w-full truncate border-none bg-transparent text-sm font-semibold text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-0"
            placeholder="Step name"
            aria-label="Step name"
          />
          <div className="text-[10px] text-gray-400 dark:text-gray-500">{selectedRegistryNode.name}</div>
        </div>
        <button
          type="button"
          aria-label="Close step settings"
          onClick={() => selectNode(null)}
          className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
        >
          <X size={16} />
        </button>
      </div>

      {/* Tabs */}
      {!isAnnotation && (
        <div className="flex border-b border-gray-200 dark:border-gray-700">
          <button
            type="button"
            onClick={() => setTab('config')}
            className={`flex flex-1 items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium ${
              tab === 'config'
                ? 'border-b-2 border-blue-600 text-blue-600'
                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
            }`}
          >
            <Settings size={12} /> Settings
          </button>
          <button
            type="button"
            onClick={() => setTab('output')}
            className={`flex flex-1 items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium ${
              tab === 'output'
                ? 'border-b-2 border-blue-600 text-blue-600'
                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
            }`}
          >
            <Database size={12} /> Output
            {nodeResult && (
              <span
                className={`ml-1 inline-block h-1.5 w-1.5 rounded-full ${
                  nodeResult.status === 'completed' ? 'bg-green-500' : nodeResult.status === 'failed' ? 'bg-red-500' : 'bg-gray-400'
                }`}
              />
            )}
          </button>
          {docContent && (
            <button
              type="button"
              onClick={() => setTab('docs')}
              className={`flex flex-1 items-center justify-center gap-1.5 px-3 py-2 text-xs font-medium ${
                tab === 'docs'
                  ? 'border-b-2 border-blue-600 text-blue-600'
                  : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
              }`}
            >
              <BookOpen size={12} /> Docs
            </button>
          )}
        </div>
      )}

      {/* Pinned Data Banner */}
      {!isAnnotation && pinnedData && (pinnedData.input || pinnedData.output) && (
        <div className="flex items-center justify-between border-b border-orange-200 bg-orange-50 px-4 py-2 dark:border-orange-800 dark:bg-orange-900/20">
          <div className="flex items-center gap-1.5 text-xs text-orange-700 dark:text-orange-400">
            <Pin size={12} />
            <span>
              Pinned {pinnedData.output ? 'output' : 'input'}
              {pinnedData.input && pinnedData.output ? ' & input' : ''}
            </span>
          </div>
          <button
            type="button"
            onClick={handleUnpin}
            disabled={isPinning}
            className="flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-medium text-orange-600 hover:bg-orange-100 dark:text-orange-400 dark:hover:bg-orange-900/40"
          >
            <PinOff size={10} /> Unpin
          </button>
        </div>
      )}

      {/* Tab Content */}
      <div className="flex-1 overflow-y-auto px-4 py-3">
        {tab === 'config' ? (
          <div className="space-y-4">
            {selectedRegistryNode.description && <p className="text-xs leading-relaxed text-gray-500 dark:text-gray-400">{selectedRegistryNode.description}</p>}
            <DynamicForm
              schema={selectedRegistryNode.config_schema}
              values={localConfig}
              onChange={handleConfigChange}
              variables={variables}
              workflowId={workflow?.id}
            />
            {variables && (
              <div className="border-t border-gray-200 pt-3 dark:border-gray-700">
                <VariablePanel
                  data={variables}
                  onInsert={(expr) => navigator.clipboard.writeText(expr)}
                />
              </div>
            )}
          </div>
        ) : tab === 'docs' && docContent ? (
          <MarkdownRenderer markdown={docContent} />
        ) : (
          <NodeOutputView
            nodeResult={nodeResult}
            pinnedData={pinnedData}
            onPin={handlePinOutput}
            onUnpin={handleUnpin}
            isPinning={isPinning}
            selectedNodeId={String(selectedApiNode.id)}
          />
        )}
      </div>

      {/* Footer */}
      <div className="border-t border-gray-200 dark:border-gray-700 px-4 py-3">
        <div className="flex gap-2">
          {isDirty && tab === 'config' && (
            <button
              type="button"
              onClick={handleSave}
              disabled={isSaving}
              className="flex flex-1 items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            >
              <Save size={14} />
              {isSaving ? 'Saving...' : 'Save'}
            </button>
          )}
          {!isAnnotation && tab !== 'docs' && (
            <button
              type="button"
              onClick={() => setShowTestModal(true)}
              disabled={isTestingNode}
              className={`flex items-center justify-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 ${
                isDirty && tab === 'config' ? '' : 'flex-1'
              }`}
            >
              {isTestingNode ? (
                <Loader2 size={14} className="animate-spin" />
              ) : (
                <Play size={14} />
              )}
              {isTestingNode ? 'Testing...' : 'Test'}
            </button>
          )}
        </div>
      </div>

      {showTestModal && (
        <TestNodeInputModal
          nodeName={localLabel || selectedApiNode.node_key}
          onRun={handleTestNode}
          onClose={() => setShowTestModal(false)}
          isRunning={isTestingNode}
          initialPayload={
            pinnedData?.input
              ? JSON.stringify(pinnedData.input, null, 2)
              : undefined
          }
        />
      )}
    </div>
  )
}

interface NodeOutputViewProps {
  nodeResult?: { status: string; input: Record<string, unknown> | null; output: Record<string, unknown> | null; error_message: string | null; duration_ms: number | null } | null
  pinnedData?: { input?: unknown[]; output?: Record<string, unknown[]>; source_run_id?: number | null } | null
  onPin: () => void
  onUnpin: () => void
  isPinning: boolean
  selectedNodeId: string
}

function NodeOutputView({ nodeResult, pinnedData, onPin, onUnpin, isPinning, selectedNodeId }: NodeOutputViewProps) {
  const hasPinned = !!(pinnedData?.input || pinnedData?.output)
  const rfEdges = useWorkflowEditorStore((s) => s.rfEdges)
  const rfNodes = useWorkflowEditorStore((s) => s.rfNodes)
  const nodeTestResults = useRunStore((s) => s.nodeTestResults)

  const upstreamInputs = getUpstreamInputs(selectedNodeId, rfEdges, rfNodes, nodeTestResults)
  const hasUpstream = upstreamInputs.length > 0

  if (!nodeResult && !hasPinned && !hasUpstream) {
    return (
      <div className="flex flex-col items-center justify-center py-12 text-center">
        <Database size={32} className="mb-3 text-gray-300 dark:text-gray-600" />
        <p className="text-sm text-gray-400 dark:text-gray-500">No test output yet</p>
        <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
          Click <strong>Test</strong> or hover a node and click <strong>Run</strong> to execute
        </p>
      </div>
    )
  }

  return (
    <div className="space-y-3">
      {/* Upstream Incoming Data */}
      {hasUpstream && (
        <div className="space-y-2">
          {upstreamInputs.map((up) => (
            <UpstreamInputSection key={`${up.nodeId}-${up.sourcePort}`} upstream={up} />
          ))}
        </div>
      )}

      {/* Pinned Data Section */}
      {hasPinned && !nodeResult && (
        <>
          {pinnedData?.input && (
            <div>
              <p className="mb-1 text-[10px] font-medium uppercase tracking-wide text-orange-500">Pinned Input</p>
              <JsonViewer data={pinnedData.input} maxHeight="200px" />
            </div>
          )}
          {pinnedData?.output && (
            <div>
              <p className="mb-1 text-[10px] font-medium uppercase tracking-wide text-orange-500">Pinned Output</p>
              <JsonViewer data={pinnedData.output} maxHeight="300px" />
            </div>
          )}
        </>
      )}

      {/* Test Result */}
      {nodeResult && (
        <>
          {/* Status + Pin Button */}
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <NodeRunStatusBadge status={nodeResult.status as 'completed' | 'failed' | 'running' | 'pending' | 'skipped'} />
              {nodeResult.duration_ms != null && (
                <span className="text-xs text-gray-500 dark:text-gray-400">{nodeResult.duration_ms}ms</span>
              )}
            </div>
            {nodeResult.status === 'completed' && nodeResult.output && (
              <button
                type="button"
                onClick={hasPinned ? onUnpin : onPin}
                disabled={isPinning}
                className={`flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium disabled:opacity-50 ${
                  hasPinned
                    ? 'text-orange-600 hover:bg-orange-50 dark:text-orange-400 dark:hover:bg-orange-900/30'
                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
                }`}
                title={hasPinned ? 'Unpin test data' : 'Pin this output as test data'}
              >
                {hasPinned ? <PinOff size={12} /> : <Pin size={12} />}
                {hasPinned ? 'Unpin' : 'Pin'}
              </button>
            )}
          </div>

          {/* Error */}
          {nodeResult.error_message && (
            <div className="rounded-md bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-900/30 dark:text-red-400">
              {nodeResult.error_message}
            </div>
          )}

          {/* Input */}
          <div>
            <p className="mb-1 text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Input</p>
            <JsonViewer data={nodeResult.input} maxHeight="200px" />
          </div>

          {/* Output */}
          <div>
            <p className="mb-1 text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Output</p>
            <JsonViewer data={nodeResult.output} maxHeight="300px" />
          </div>
        </>
      )}
    </div>
  )
}

function UpstreamInputSection({ upstream }: { upstream: UpstreamInput }) {
  const [expanded, setExpanded] = useState(true)

  return (
    <div className="rounded-md border border-blue-200 bg-blue-50/50 dark:border-blue-800 dark:bg-blue-900/20">
      <button
        type="button"
        onClick={() => setExpanded(!expanded)}
        className="flex w-full items-center gap-1.5 px-2.5 py-1.5 text-left"
      >
        {expanded ? <ChevronDown size={12} className="text-blue-500" /> : <ChevronRight size={12} className="text-blue-500" />}
        <ArrowDownLeft size={10} className="text-blue-400" />
        <span className="text-[11px] font-medium text-blue-700 dark:text-blue-300">
          {upstream.nodeLabel}
        </span>
        <span className="text-[9px] text-blue-400 dark:text-blue-500">
          ({upstream.sourcePort})
        </span>
        {upstream.source === 'pinned' && (
          <Pin size={9} className="ml-auto text-orange-400" />
        )}
      </button>
      {expanded && (
        <div className="border-t border-blue-200 px-2.5 py-2 dark:border-blue-800">
          <JsonViewer data={upstream.data} maxHeight="200px" />
        </div>
      )}
    </div>
  )
}
